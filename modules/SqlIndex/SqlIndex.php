<?php
/**
 * @package     fab
 * @subpackage  modules
 * @author      Daniel Ménard <Daniel.Menard@ehesp.fr>
 * @version     SVN: $Id$
 */

/**
 * Ce module permet d'indexer des données SQL présentes dans une base de
 * données relationnelle accessible via PDO (mysql, sqlite, etc.)
 *
 * Les données à indexer sont définies via des requêtes SQL indiquées dans la
 * configuration du module.
 *
 * @package     fab
 * @subpackage  modules
 */
class SqlIndex extends Module
{
    /**
     * Crée une connexion PDO à la base de données SQL utilisée pour l'indexation.
     *
     * Par défaut, la méthode utilise les informations indiquées dans le fichier de
     * configuration du module (dsn, user, password...) pour se connecter à la base.
     *
     * Les modules descendant peuvent surcharger cette méthode pour mettre en oeuvre d'autres
     * méthodes de connexion.
     *
     * Par exemple, si les paramètres de connexion sont déjà disponibles ailleurs (dans le
     * fichier de config de Wordpress, par exemple), il est souhaitable de surcharger cette
     * méthode pour éviter de dupliquer l'information.
     *
     * @return PDO une connexion ouverte à la base de données.
     * @throws PDOException si la tentative de connexion à la base de données échoue.
     */
    protected function getDatabaseConnection()
    {
        $dsn = Config::get("source.dsn");
        if (empty($dsn))
            throw new Exception("La chaine de connexion à la base de données SQL n'a pas été indiquée.");

        $username = Config::get("source.username");
        $password = Config::get("source.password");
        $options = (array) Config::get("source.driver_options");

        return new PDO($dsn, $username, $password, $options);
    }


    /**
     * Signale une erreur dans une requête SQL.
     *
     * @param PDO|PDOStatement $source la source de l'erreur
     * @param string $sql optionnel, la requête sql exécutée.
     */
    protected function reportSqlError($source, $sql=null)
    {
        if (is_null($sql) && $source instanceof PDOStatement) $sql = $source->querystring;

        $error = $source->errorInfo();
        echo "<p>Une erreur est survenue lors de l'exécution de la requête : <pre>$sql</pre>";
        echo $error[2], "</p>";
    }

    /**
     * Indexe les données
     */
    public function actionIndex($confirm = false)
    {
        // Récupère le nom de la base Xapian à créer
        $database = Config::get('database');
        if (empty($database))
            throw new Exception("Le nom de la base de données à créer n'a pas été indiqué");

        // Détermine son path
        $databasePath = Config::get("db.$database.path", $database);
        if (Utils::isRelativePath($databasePath))
            $databasePath = Utils::makePath(Runtime::$root, 'data/db', $databasePath);

        // Récupère le schéma à utiliser pour la base xapian
        $schema = Config::get('schema');
        if (empty($schema))
            throw new Exception("Le nom du schéma à utiliser n'a pas été indiqué.");

        // Détermine son path
        if (false === $schema=Utils::searchFile($schema, Runtime::$root . 'data/schemas/'))
            throw new Exception('Impossible de trouver le schéma indiqué dans la config.');

        // Crée une connexion à la base de données SQL
        $db = $this->getDatabaseConnection();

        echo "<h1>", Config::get('title'), '</h1>';

        // Demande confirmation
        if (!$confirm)
        {
            printf
            (
                "<p>Vous allez lancer l'indexation des données de la base de données <strong>%s</strong> (%s, %s) dans la base Xapian <strong>%s</strong>.</p>",
                Config::get('source.label'),
                $db->getAttribute(PDO::ATTR_DRIVER_NAME),
                $db->getAttribute(PDO::ATTR_CONNECTION_STATUS),
                $database
            );
            echo "<p>La base <strong>$database</strong> va être écrasée puis sera reconstruite.</p>";

            echo '<p><a href="?confirm=true">Cliquez ici pour confirmer que c\'est bien ça que vous voulez faire...</a></p>';
            return;
        }

        // Crée une tâche au sein du taskmanager
        if (! User::hasAccess('cli'))
        {
            $id=Task::create()
                ->setRequest($this->request)
                ->setTime(0)
                ->setRepeat(null)
                ->setLabel('Indexation de la base sql ' . Config::get('source.label') . ' dans la base ' . $database)
                ->setStatus(Task::Waiting)
                ->save()
                ->getId();

            Runtime::redirect('/TaskManager/TaskStatus?id='.$id);
        }

        echo "<p>1. Création de la base xapian $database...</p>";

        // Charge le schéma de la base Xapian
        $dbs=new DatabaseSchema(file_get_contents($schema));

        // Crée la base Xapian
        echo "<p>2. Connexion à la base de données SQL...</p>";
        $xapianDb=Database::create($database, $dbs, 'xapian');

        // Pre-query
        echo "<p>3. Exécution des requêtes de pré-traitement...</p>";
        foreach ((array) Config::get("data.before") as $sql)
            if (false === $result = $db->exec($sql))
                return $this->reportSqlError($db, $sql);


        echo "<p>4. Indexation des données...</p>";
        $total = 0;
        echo '<ul>';
        foreach((array)Config::get("data.datasets") as $name=>$dataset)
        {
            echo "<li>";
            echo "<p>Jeu de données ", is_int($name) ? ("numéro ".($name+1)) : "\"$name\"", "...</p>";
            $nb = 0;

            // Prepare les statements pour les "other-fields"
            echo "<p>Préparation des requêtes 'other-fields'...</p>";
            $other = array(); // chaque item contient : le statement et un tableau contenant les noms des paramètres
            foreach((array)Config::get("data.datasets.$name.other-fields") as $sql)
            {
                $statement = $db->prepare($sql);
                $matches = array();
                preg_match_all('~:([a-z0-9_]+)~i', $sql, $matches);
                $other[] = array($statement, $matches[1]);
            }

            // Exécute la requête principale
            echo "<p>Exécution de la requête principale...</p>";
            $sql = Config::get("data.datasets.$name.query");
            if (false === $records = $db->query($sql, PDO::FETCH_ASSOC))
            {
                return $this->reportSqlError($db, $sql);
            }

            // Indexe toutes les réponses
            echo "<p>Indexation...</p>";
            foreach($records as $record)
            {
                // Exécute chacune des requêtes "other-field" pour le record obtenu.
                foreach($other as $item)
                {
                    $statement = $item[0];
                    foreach($item[1] as $field)
                        $statement->bindValue($field, $record[$field]);

                    if (! $statement->execute())
                        return $this->reportSqlError($statement);

                    foreach($statement->fetchAll(PDO::FETCH_ASSOC) as $row)
                    {
                        foreach($row as $field=>$value)
                        {
                            if (isset($record[$field]))
                            {
                                if (is_array($record[$field]))
                                    $record[$field][] = $value;
                                else
                                    $record[$field] = array($record[$field], $value);
                            }
                            else
                            {
                                $record[$field] = $value;
                            }
                        }
                    }
                }

                // Eclate les champs en champs articles
                foreach((array)Config::get("data.datasets.$name.split") as $field => $sep)
                    if (isset($record[$field]))
                        $record[$field] = explode(trim($sep), $record[$field]);

                // Crée l'enregistrement xapian
                $xapianDb->addRecord();
                foreach($record as $field=>$value)
                {
                    if (is_array($value))
                        foreach ($value as $item)
                            $item = strip_tags($item);
                    else
                        $value = strip_tags($value);

                    $xapianDb[$field] = $value;
                }
                $xapianDb->saveRecord();
                ++$nb;

                // debug
//                $record['Content'] = 'supprimé';
//                echo "<pre>", var_export($record, true), "</pre>";
            }
            echo Utils::pluralize('<p>%d enregistrement{s} ajouté{s} dans la base Xapian.</p>', $nb);
            echo "</li>";
            $total += $nb;
        }
        echo '</ul>';

        // Post-query
        echo "<p>Exécution des requêtes de post-traitement...</p>";
        foreach ((array) Config::get("data.after") as $sql)
            if (false === $result = $db->exec($sql))
                return $this->reportSqlError($db, $sql);

        echo Utils::pluralize('<p>Terminé. La base Xapian contient %d enregistrement{s} au total.</p>', $total);
    }
}