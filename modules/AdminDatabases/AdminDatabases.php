<?php

/**
 * @package     fab
 * @subpackage  Admin
 * @author      Daniel Ménard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id$
 */

/**
 * Module d'administration permettant de gérer les bases de données de
 * l'application.
 *
 * Ce module permet de lister les bases de données de l'application et offre des
 * fonctions permettant de {@link actionNew() créer une nouvelle base}, de
 * {@link actionSetSchema() modifier la structure} d'une base existante en lui
 * appliquant un nouveau {@link DatabaseSchema schéma} et de lancer une
 * {@link actionReindex() réindexation complète} de la base.
 *
 * @package     fab
 * @subpackage  Admin
 */
class AdminDatabases extends Admin
{
    /**
     * La base en cours.
     *
     * Cette propriété n'est utilisée que par {@link actionReindex()} pour
     * permettre aux templates d'accéder à la base de données en cours
     * (par exemple pour afficher le nombre de notices).
     *
     * @var XapianDatabaseDriver
     */
    public $selection;


    /**
     * Retourne la liste des bases de données connues du système.
     *
     * La méthode utilise le fichier de configuration
     * {@link /AdminConfig#db.config db.config} pour établir la liste des bases
     * de données.
     *
     * @return array|null un tableau contenant le nom des bases référencées dans
     * le fichier de configuration. Le tableau obtenu est trié par ordre
     * alphabétique. La méthode retourne <code>null</code> si aucune base n'est
     * définie.
     */
    public static function getDatabases()
    {
        $db=Config::get('db');

        if (is_array($db))
        {
            $db=array_keys($db);
            sort($db, SORT_LOCALE_STRING);
            return $db;
        }
        return null;
    }


    /**
     * Retourne des informations sur la base dont le nom est passé en paramètre.
     *
     * @param string $name le nom de la base à examiner.
     *
     * @return StdClass un objet contenant les propriétés suivantes :
     * - <code>type</code> : le type de base de données
     * - <code>path</code> : le path exact de la base
     * - <code>count</code> : le nombre total d'enregistrements dans la base
     * - <code>error</code> : un message d'erreur si la base de données indiquée
     *   n'existe pas ou ne peut pas être ouverte
     */
    public static function getDatabaseInfo($name)
    {
        $info=new StdClass();
        $info->type=Config::get("db.$name.type");
        $info->path=null;
        $info->count=null;
        $info->error=null;

        try
        {
            $base=Database::open($name);
        }
        catch (Exception $e)
        {
            $info->error=$e->getMessage();
            return;
        }
        $info->path=$base->getPath();
        $info->count=$base->totalCount();

        $schema=$base->getSchema();
        $info->label=$schema->label;
        $info->description=$schema->description;

        return $info;
    }

    /**
     * Retourne un tableau permettant de construire un fil d'ariane
     * (breadcrumbs).
     *
     * {@inheritdoc}
     *
     * La méthode ajoute au tableau retourné par la classe parente le nom du
     * fichier éventuel passé en paramètre dans <code>$file</code>.
     *
     * @return array
     */
    protected function getBreadCrumbsArray()
    {
        $breadCrumbs=parent::getBreadCrumbsArray();

        // Si on a un nom de base en paramêtre, on l'ajoute
        if ($file=$this->request->get('database'))
            $breadCrumbs[$this->request->getUrl()]=$file;

        return $breadCrumbs;
    }


    /**
     * Page d'accueil du module d'administration des bases de données.
     *
     * Affiche la liste des bases de données de l'application.
     *
     * La méthode exécute le template définit dans la clé
     * <code><template></code> du fichier de configuration en lui passant
     * en paramètre une variable <code>$database</code> contenant la liste
     * des bases telle que retournée par {@link getDatabases()}.
     */
    public function actionIndex()
    {
        Template::run
        (
            Config::get('template'),
            array('databases'=>self::getDatabases())
        );
    }


    /**
     * Lance une réindexation complète de la base de données dont le
     * nom est passé en paramètre.
     *
     * Dans un premier temps, on affiche une page à l'utilisateur lui indiquant
     * comment fonctionne la réindexation et lui demandant de confirmer son
     * choix.
     *
     * La page affichée correspond au template indiqué dans la clé
     * <code><template></code> du fichier de configuration. Celui-ci est
     * appellé avec une variable <code>$database</code> qui indique le nom
     * de la base de données à réindexer.
     *
     * Ce template doit réappeller l'action Reindex en passant en paramètre
     * la valeur <code>true</code> pour le paramètre <code>$confirm</code>.
     *
     * La méthode crée alors une {@link Task tâche} au sein du
     * {@link /TaskManager gestionnaire de tâches} qui se charge d'effectuer
     * la réindexation.
     *
     * Remarque :
     * Si la base de données est vide (aucun document), la méthode Reindex
     * refusera de lancer la réindexation et affichera un message d'erreur
     * indiquant que c'est inutile.
     *
     * @param string $database le nom de la base à réindexer.
     * @param bool $confirm le flag de confirmation.
     */
    public function actionReindex($database, $confirm=false)
    {
        // Si on est en ligne de commande, lance la réindexation proprement dite
        if (User::hasAccess('cli'))
        {
            // Ouvre la base en écriture (pour la verrouiller)
            $this->selection=Database::open($database, false);

            // Lance la réindexation
            $this->selection->reindex();
            return;
        }

        // Sinon, interface web : demande confirmation et crée la tâche

        // Ouvre la base et vérifie qu'elle contient des notices
        $this->selection=Database::open($database, true);
        $this->selection->search(null, array('_max'=>-1, '_sort'=>'+'));
        if ($this->selection->count()==0)
        {
            echo '<p>La base ', $database, ' ne contient aucun document, il est inutile de lancer une réindexation complète.</p>';
            return;
        }

        // Demande confirmation à l'utilisateur
        if (!$confirm)
        {
            Template::run
            (
                config::get('template'),
                array('database'=>$database)
            );
            return;
        }

        // Crée une tâche au sein du gestionnaire de tâches
        $id=Task::create()
            ->setRequest($this->request)
            ->setTime(0)
            ->setLabel('Réindexation complète de la base ' . $database)
            ->setStatus(Task::Waiting)
            ->save()
            ->getId();

        Runtime::redirect('/TaskManager/TaskStatus?id='.$id);
    }


    /**
     * Modifie la structure d'une base de données existante en lui appliquant
     * un nouveau {@link DatabaseSchema schéma}.
     *
     * La méthode commence par afficher le template
     * <code>chooseSchema.html</code> avec une variable <code>$database</code>
     * qui indique le nom de la base de données à modifier.
     *
     * Ce template contient des slots qui utilisent l'action
     * {AdminSchemas::actionChoose()} pour présenter à l'utilisateur la liste
     * des schémas disponibles dans l'application et dans fab.
     *
     * L'utilisateur choisit alors le schéma qu'il souhaite appliquer à la base.
     *
     * La méthode va alors effectuer une comparaison entre le schéma actuel
     * de la base de données et le schéma choisi par l'utilisateur.
     *
     * Si les schémas sont identiques, le template <code>nodiff.html</code>
     * est affiché.
     *
     * Dans le cas contraire, la méthode va afficher la liste de toutes les
     * modifications apportées (champs ajoutés, supprimés...) et va demander
     * à l'utilisateur de confirmer qu'il veut appliquer ce nouveau schéma à
     * la base.
     *
     * Elle exécute pour cela le template indiqué dans la clé
     * <code><template></code> du fichier de configuration en lui passant en
     * paramètre :
     * - <code>$database</code> : le nom de la base qui va être modifiée ;
     * - <code>$schema</code> : le nom du nouveau schema qui va être appliqué à
     *   la base ;
     * - <code>$changes</code> : la liste des différences entre le schéma actuel
     *   de la base de données et le nouveau schéma. Cette liste est établie
     *   en appellant la méthode {@link DatabaseSchema::compare()} du nouveau
     *   schéma.
     * - <code>$confirm</code> : la valeur <code>false</code> indiquant que
     *   la modification de la base n'a pas encore été effectuée.
     *
     * Si l'utilisateur confirme son choix, la méthode va alors appliquer le
     * nouveau schéma à la base puis va réafficher le même template avec cette
     * fois-ci la variable <code>$confirm</code> à <code>true</code>.
     *
     * Ce second appel permet d'afficher à l'utilisateur un réacapitulatif de
     * ce qui a été effectué et de lui proposer de lancer une
     * {@link actionReindex() réindexation complète de la base} s'il y a lieu.
     *
     * @param string $database le nom de la base à réindexer.
     * @param string $schema le nom du schema à appliquer.
     * @param bool $confirm un flag indiquant si l'utilisateur a confirmé
     * don choix.
     */
    public function actionSetSchema($database, $schema='', $confirm=false)
    {
        // Choisit le schéma à appliquer à la base
        if($schema==='')
        {
            $schemas=AdminSchemas::getSchemas();
            Template::run
            (
                'chooseSchema.html',
                array
                (
                    'database' => $database
                )
            );
            return;
        }

        // Vérifie que le schéma indiqué existe
        if (Utils::isRelativePath($schema) || ! file_exists($schema))
            throw new Exception('Le schéma '. basename($schema) . "n'existe pas");

        // Charge le schéma
        $newSchema=new DatabaseSchema(file_get_contents($schema));

        // Ouvre la base de données et récupère le schéma actuel de la base
        $this->selection=Database::open($database, !$confirm); // confirm=false -> readonly=true, confirm=true->readonly=false
        $oldSchema=$this->selection->getSchema();

        // Compare l'ancien et la nouveau schémas
        $changes=$newSchema->compare($oldSchema);

        // Affiche une erreur si aucune modification n'a été apportée
        if (count($changes)===0)
        {
            Template::run
            (
                'nodiff.html',
                array
                (
                    'database'=>$database,
                    'schema'=>$schema
                )
            );

            return;
        }

        // Affiche la liste des modifications apportées et demande confirmation à l'utilisateur
        if (! $confirm)
        {
            // Affiche le template de confirmation
            Template::run
            (
                config::get('template'),
                array
                (
                    'confirm'=>$confirm,
                    'database'=>$database,
                    'schema'=>$schema,
                    'changes'=>$changes
                )
            );

            return;
        }

        // Applique la nouvelle structure à la base
        $this->selection->setSchema($newSchema);

        // Affiche le résultat et propose (éventuellement) de réindexer
        Template::run
        (
            config::get('template'),
            array
            (
                'confirm'=>$confirm,
                'database'=>$database,
                'schema'=>$schema,
                'changes'=>$changes
            )
        );
    }


    /**
     * Crée une nouvelle base de données.
     *
     * La méthode commence par demander à l'utilisateur le nom de la base
     * de données à créer et vérifie que ce nom est correct.
     *
     * Elle utilise pour cela le template <code>new.html</code> qui est appellé
     * avec une variable <code>$database</code> contenant le nom de la base
     * à créer et une variable <code>$error</code> qui contiendra un message
     * d'erreur si le nom de la base indiquée n'est pas correct (il existe déjà
     * une base de données ou un dossier portant ce nom).
     *
     * Elle demande ensuite le nom du {@link DatabaseSchema schéma} à utiliser
     * et vérifie que celui-ci est correct.
     *
     * Elle utilise pour cela le template <code>newChooseSchema.html</code> qui
     * est appellé avec une variable <code>$database</code> contenant le nom de
     * la base à créer, une variable <code>$schema</code> contenant le nom
     * du schéma choisi et une variable <code>$error</code> qui contiendra un
     * message d'erreur si une erreur est trouvée dans le schéma (schéma
     * inexistant, non valide, etc.)
     *
     * Si tout est correct, la méthode crée ensuite la base de données dans le
     * répertoire <code>/data/db/</code> de l'application puis crée un nouvel
     * alias dans le fichier {@link /AdminConfig#db.config db.config} de l'application.
     *
     * Enfin, l'utilisateur est redirigé vers la {@link actionIndex() page
     * d'accueil} du module sur la base de données créée.
     *
     * @param string $database le nom de la base à créer.
     * @param string $schema le path du schéma à utiliser pour
     * la structure initiale de la base de données.
     */
    public function actionNew($database='', $schema='')
    {
        $error='';

        // Vérifie le nom de la base indiquée
        if ($database !== '')
        {
            if (! is_null(Config::get('db.'.$database)))
                $error="Il existe déjà une base de données nommée $database. ";
            else
            {
                $path=Runtime::$root . 'data/db/' . $database;
                if (is_dir($path))
                    $error="Il existe déjà un dossier $database dans le répertoire data/db de l'application.";
            }
        }

        // Demande le nom de la base à créer
        if ($database === '' || $error !== '')
        {
            Template::run
            (
                'new.html',
                array
                (
                    'database' => $database,
                    'error'=>$error
                )
            );
            return;
        }

        // Vérifie le nom du schéma indiqué
        if ($schema !== '')
        {
            if (! file_exists($schema))
                $error = 'Le schéma <strong>' . basename($schema) . "</strong> n'existe pas.";
            else
            {
                $dbs=new DatabaseSchema(file_get_contents($schema));
                if (true !== $errors=$dbs->validate())
                    $error = "Impossible d'utiliser le schéma <strong>" . basename($schema) . "</strong> :<br />" . implode('<br />', $errors);
            }
        }

        // Affiche le template si nécessaire
        if ($schema === '' || $error !== '')
        {
            Template::run
            (
                'newChooseSchema.html',
                array
                (
                    'database' => $database,
                    'schema' => $schema,
                    'error'=>$error
                )
            );
            return;
        }

        // OK, on a tous les paramètres et ils sont tous vérifiés


        // Crée la base
        Database::create($path, $dbs, 'xapian');

        // Charge le fichier de config db.config
        $pathConfig=Runtime::$root.'config' . DIRECTORY_SEPARATOR . 'db.config';
        if (file_exists($pathConfig))
            $config=Config::loadXml(file_get_contents($pathConfig));
        else
            $config=array();

        // Ajoute un alias
        $config[$database]=array
        (
            'type'=>'xapian',
            'path'=>$database // $path ?
        );

        // Sauvegarde le fichier de config
        ob_start();
        Config::toXml('config', $config);
        $data=ob_get_clean();
        file_put_contents($pathConfig, $data);

        // Redirige vers la page d'accueil
        Runtime::redirect('/'.$this->module);
    }

    private function xmlEntities($data)
    {
        /*
         * XML 1.0 définit la liste des caractères unicode autorisés dans
         * un fichier xml de la façon suivante :
         *
         * #x9 | #xA | #xD | [#x20-#xD7FF] | [#xE000-#xFFFD] | [#x10000-#x10FFFF]
         * (source : http://www.w3.org/TR/REC-xml/#charsets)
         *
         */

//        $result=preg_replace_callback
//        (
//            '~[^\x09\x0A\x0D\x20-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]~u',
//            array($this, 'xmlEntitiesCallback'),
//            $data
//        );

        /*
         * Dans notre cas, la chaine passée est de l'ansi. On ne teste que les
         * caractères < 255
         */
        $result=preg_replace_callback
        (
            '~[^\x09\x0A\x0D\x20-\xFF]~u',
            array($this, 'xmlEntitiesCallback'),
            $data
        );
        echo var_export($result, true), '<br />';
        return $result;
    }

    private function xmlEntitiesCallback($match)
    {
        echo 'caractère illégal : ' , var_export($match[0], true), ', code=', ord($match[0]), '<br />';
        return '&#'.ord($match[0]).';';

    }

    private function xmlWriteField(XmlWriter $xml, $tag, $value)
    {
        if (is_null($value)) return;

        if (is_array($value))
        {
            $xml->startElement($tag);
            foreach($value as $item)
            {
                $this->xmlWriteField($xml, 'item', $item);
            }
            $xml->endElement();
            return;
        }

        if (is_bool($value))
        {
            $xml->writeElement(utf8_encode($tag), $value ? 'TRUE' : 'FALSE');
            return;
        }

        if (is_int($value) || is_float($value))
        {
            $xml->writeElement(utf8_encode($tag), (string) $value);
            return;
        }

        if (is_string($value))
        {
            /*
             * XML 1.0 définit la liste des caractères unicode autorisés dans
             * un fichier xml de la façon suivante :
             *
             * #x9 | #xA | #xD | [#x20-#xD7FF] | [#xE000-#xFFFD] | [#x10000-#x10FFFF]
             * (source : http://www.w3.org/TR/REC-xml/#charsets)
             *
             * Tout autre caractère fera que le fichier xml sera mal formé et ne
             * pourra pas être chargé.
             *
             * Dans fab, un champ, au moins en théorie, peut contenir des
             * caractères binaires. On a le cas dans ascoweb avec la notice
             * 90691 dont le résumé contient "\x05\x05" (?).
             *
             * Il faut donc qu'on vérifie tous les caractères. Dans notre cas,
             * la chaine passée est de l'ansi. On ne teste donc que les
             * caractères [0-255].
             *
             * Si la chaine passée est "propre", on l'écrit telle quelle. Sinon,
             * on va encoder la totalité de la chaine en base64 et on va ajouter
             * l'attribut base64="true" au tag.
             *
             * Remarque : dans notre traitement on suppose implicitement que
             * si une chaine ansi ne contient pas de caractères illégaux, la
             * chaine obtenue après appel de utf8_encode() n'en contiendra pas
             * non plus.
             *
             */
            if (0===preg_match('~[^\x09\x0A\x0D\x20-\xFF]~', $value))
            {
                $xml->writeElement(utf8_encode($tag), utf8_encode($value));
            }
            else
            {
                $xml->startElement($tag);

//                $xml->writeAttribute('xsi:type', 'xsd:hexBinary');
//                $xml->text(bin2hex($value));

                $xml->writeAttribute('xsi:type', 'xsd:base64Binary');
                $xml->text(base64_encode($value));

                $xml->endElement();
            }
            return;
        }

        throw new Exception('Type de valeur non géré : ', debug_zval_dump($value));
    }

     /**
     * Backup
     *
     * Fonction récupérée du répertoire
     * WebApache\Fab septembre 2007, avant checked out from google\modules\DatabaseAdmin
     */
    public function actionBackup($database, $taskTime='', $taskRepeat='')
    {
        // Vérifie que le répertoire /data/backup existe, essaie de le créer sinon
        $dir=Runtime::$root.'data' . DIRECTORY_SEPARATOR . 'backup' . DIRECTORY_SEPARATOR;
        if (!is_dir($dir))
        {
            if (! @mkdir($dir))
                throw new Exception('Impossible de créer le répertoire ' . $dir);
        }

        // Partie interface utilisateur
        if (! User::hasAccess('cli'))
        {
            // 1. Permet à l'utilisateur de planifier le dump
            if ($taskTime==='')
            {
                Template::Run('backup.html', array('error'=>''));
                return;
            }

            // Détermine un titre pour la tâche de dump
            $title=sprintf
            (
                'Dump %s de la base %s',
                ($taskRepeat ? 'périodique' : 'ponctuel'),
                $database
            );

            // 2. Crée la tâche
            $id=Task::create()
                ->setRequest($this->request->keepOnly('database'))
                ->setTime($taskTime)
                ->setRepeat($taskRepeat)
                ->setLabel($title)
                ->setStatus(Task::Waiting)
                ->save()
                ->getId();

            if ($taskTime===0 || abs(time()-$taskTime)<30)
                Runtime::redirect('/TaskManager/TaskStatus?id='.$id);
            else
                Runtime::redirect('/TaskManager/Index');
            return;
        }

        // 3. Lance le dump
        echo '<h1>', sprintf('Dump de la base %s', $database), '</h1>';

        echo '<p>Date du dump : ', strftime('%x %X'), '</p>';

        $selection=Database::open($database, true);
        $selection->search('', array('_sort'=>'+', "_max"=>-1));
        $count=$selection->count();
        echo '<p>La base contient ', $count, ' notices</p>';

        // Détermine le path du fichier à générer
        $path=$database . '-' . strftime('%Y%m%d-%H%M%S') . '.xml.gz';
        echo '<p>Génération du fichier ', $path, '</p>';
        $path=$dir . $path;

        if (file_exists($path))
        {
            throw new Exception('Le fichier de dump existe déjà.');
        }

        $gzPath='compress.zlib://'.$path;
        // à tester : stockage sur un ftp distant ?

        // Ouvre le fichier
        $xml=new XmlWriter();
        $xml->openUri($gzPath);
        //$xml->setIndent(true);
        $xml->setIndentString('    ');

        // Génère le prologue xml
        $xml->startDocument('1.0', 'UTF-8', 'yes');

        // Tag racine
        $xml->startElement('database');     // <database>

        // Ajoute une référence de schema.  Permet d'utiliser xsi:nil pour les
        // valeurs null et xsi:type pour indiquer le type d'un champ
        $xml->writeAttribute('xmlns:xsd', 'http://www.w3.org/2001/XMLSchema');
        $xml->writeAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');

        // Autres attributs du tag racine
        $xml->writeAttribute('name', $database);
        $xml->writeAttribute('timestamp', time());
        $xml->writeAttribute('date', strftime('%x %X'));

        // Schéma de la base
        $xml->writeRaw("\n");
        $xml->writeRaw($selection->getSchema()->toXml(false, '    '));

        // Données
        $xml->startElement('records');     // <records>

        $xml->writeAttribute('count', $count);

        $start=microtime(true);
        foreach($selection as $i=>$record)
        {
            $xml->startElement('row');      // <row>
            foreach($record as $field=>$value)
            {
                $this->xmlWriteField($xml, $field, $value);
            }
            $xml->endElement();             // </row>

            $time=microtime(true);
            if (($time-$start)>1)
            {
                TaskManager::progress($i, $count);
                $start=$time;
            }
        }
        $xml->endElement();                 // </records>

        // spellings, synonyms, meta data...

        // Finalise le fichier xml
        $xml->endElement();                 // </database>
        $xml->flush();
        $xml=null;
        TaskManager::progress();

        echo '<p>Dump terminé.</p>';
        echo '<p>Taille du fichier : ', Utils::formatSize(filesize($path)), '</p>';

        // Suppression des dump les plus anciens
        $maxDumps=$this->ConfigDatabaseGet('maxdumps', $database, 7);  // Nombre maximum de dumps par base de données (default : 7 jours)
        $maxDays=$this->ConfigDatabaseGet('maxdays', $database, 0);    // Nombre de jours pendant lesquels les dumps sont conservés (default : tous)

        echo '<h2>Suppression éventuelle des dumps antérieurs</h2>';
        echo '<p>Paramètres : </p>';
        echo '<ul>';
        echo '<li>Nombre maximum de dumps autorisés pour la base ', $database, ' : ', ($maxDumps===0 ? 'illimité' : $maxDumps), '</li>';
        echo '<li>Durée de conservation des dumps de la base ', $database, ' : ', ($maxDays===0 ? 'illimitée' : $maxDays.' jour(s)'), '</li>';
        echo '</ul>';

        // Calcule la date "maintenant moins maxDays, à minuit"
        $t=getDate(($maxDays===0) ? 0 : time());
        $t=mktime(0,0,0,$t['mon'],$t['mday']-$maxDays+1,$t['year']);

        // Nom minimum d'un fichier
        $minName=$database . '-' . strftime('%Y%m%d-%H%M%S', $t);

        if ($maxDumps===0) $maxDumps=PHP_INT_MAX;

        $files=glob($dir . $database . '-????????-??????.xml.gz');
        sort($files);

        echo '<p>Fichiers supprimés : </p>';
        $nb=0;
        echo '<ul>';
        foreach($files as $index=>$path)
        {
            $file=basename($path);
            if (($file<$minName) || ($index<(count($files)-$maxDumps)))
            {
                echo '<li>Suppression de ', $file;
                if (! @unlink($path)) echo ' : erreur'; else ++$nb;
                echo '</li>';
            }
        }
        if ($nb===0) echo '<li>aucun.</li>';
        echo '</ul>';

    }
    /**
     * Fonction utilitaire utilisée par actionBackup pour
     * récupérer la valeur de maxDumps et maxDays dans la config.
     *
     * @param string $key
     * @param string $database
     * @param int $default
     */
    private function ConfigDatabaseGet($key, $database, $default)
    {
        $t=Config::get($key, $default);
        if (is_array($t))
        {
            $t=Config::get("$key.$database", $default);
        }
        if (! is_int($t))
            throw new Exception('Configuration : valeur incorrecte pour la clé ' . $key . ', entier attendu.');
        return $t;
    }

    private function expect(XMLReader $xml, $name)
    {
        if ($xml->name !== $name) $xml->next($name);
        if ($xml->name !== $name)
            throw new DumpException('<'. $name . '> attendu : ' . $this->nodeType($xml));
    }

    private function nodeType($xml)
    {
        switch($xml->nodeType)
        {
            case XMLReader::NONE: return 'none';
            case XMLReader::ELEMENT: return 'element';
            case XMLReader::ATTRIBUTE: return 'attribute';
            case XMLReader::TEXT: return 'texte';
            case XMLReader::CDATA: return 'cdata';
            case XMLReader::ENTITY_REF: return 'entity_ref';
            case XMLReader::ENTITY: return 'entity';
            case XMLReader::PI: return 'PI';
            case XMLReader::COMMENT: return 'comment';
            case XMLReader::DOC: return 'doc';
            case XMLReader::DOC_TYPE: return 'doc_type';
            case XMLReader::DOC_FRAGMENT: return 'doc_fragment';
            case XMLReader::NOTATION: return 'notation';
            case XMLReader::WHITESPACE: return 'whitespace';
            case XMLReader::SIGNIFICANT_WHITESPACE: return 'significant_whitespace';
            case XMLReader::END_ELEMENT: return 'end_element';
            case XMLReader::END_ENTITY: return 'end_entity';
            case XMLReader::XML_DECLARATION: return 'xml_declaration';
            default : return 'type inconnu';
        }
    }

    private function decode(DOMElement $node)
    {
        $value=$node->nodeValue;
        switch ($node->getAttribute('xsi:type'))
        {
            case '':
                $value=utf8_decode($value);
                break;

            case 'xsd:base64Binary':
                $value=base64_decode($value);
                break;

            default:
                throw new DumpException('Valeur non supportée pour l\'attribut xsi:type : ' . $node->getAttribute('xsi:type'));
        }
        return $value;
    }

    private function fieldValue(DOMElement $node)
    {
//        $doc=new DOMDocument();
//        $doc->appendChild($node);
//        echo '<textarea cols="120" rows="10">', $doc->saveXML(), '</textarea>';

        if (!$node->hasChildNodes())
        {
//            echo 'champ sans fils, chaine vide<br />';
            return '';
        }

        if ($node->childNodes->length===1 && $node->firstChild->nodeType===XML_TEXT_NODE)
        {
            return $this->decode($node);
        }

//        echo 'champ avec ', $node->childNodes->length, ' fils<br />';
        $value=array();
        foreach ($node->childNodes as $child)
        {
//            echo 'fils : ', var_export($child, true), '<br />';
            switch($child->nodeType)
            {
                case XML_ELEMENT_NODE:
                    if ($child->nodeName !== 'item')
                        throw new DumpException('<item> attendu');
//                    echo 'nouvel item : ', $child->nodeValue, '<br />';
                    $value[]=$this->decode($child);
                    break;

                case XML_TEXT_NODE:
                    if (! $child->isWhitespaceInElementContent())
                        throw new DumpException('seuls des blancs sont autorisés entre des tags <item>'.var_export($child->nodeValue, true));
//                    echo 'noeud type texte contenant des clancs<br />';
                    break;

                default:
                    throw new Exception('Type de noeud non autorisé entre des tags <item>' . var_export($child,true));
            }
        }
        return $value;
    }

    public function actionRestore($database, $file='', $confirm=false)
    {
        // Path du répertoire contenant les backups
        $dir=Runtime::$root.'data' . DIRECTORY_SEPARATOR . 'backup' . DIRECTORY_SEPARATOR;

        // Choix du fichier de dump à restaurer
        if ($file==='')
        {
            // Détermine la liste des dumps pour cette base
            $files=array();
            $otherFiles=array();
            foreach(glob($dir . '*.xml.gz') as $path)
            {
                $file=basename($path);
                if (stripos($file, $database) === 0)
                    $files[$path]=basename($path);
                else
                    $otherFiles[$path]=basename($path);
            }

            // Trie les dumps par nom (et donc par date) décroissante
            ksort($files, SORT_LOCALE_STRING);
            ksort($otherFiles, SORT_LOCALE_STRING);

            // Demande à l'utilisateur de choisir le dump
            Template::Run
            (
                'chooseDump.html',
                array
                (
                    'database'=>$database,
                    'files'=>$files,
                    'otherFiles'=>$otherFiles
                )
            );
            return;
        }

        // Détermine le path complet du fichier de dump et vérifie qu'il existe
        $path=$dir . $file;
        if (!file_exists($path))
            throw new Exception('Le fichier de dump ' . $file . ' n\'existe pas');

        // Demande de confirmation
        if (! $confirm)
        {
            // Demande à l'utilisateur de choisir le dump
            Template::Run
            (
                'confirmRestore.html',
                array
                (
                    'database'=>$database,
                    'file'=>$file
                )
            );
            return;
        }

        // Crée une tâche au sein du taskmanager
        if (! User::hasAccess('cli'))
        {
            // 2. Crée la tâche
            $id=Task::create()
                ->setRequest($this->request)
                ->setTime(0)
                ->setRepeat(null)
                ->setLabel('Restauration de la base ' . $database . ' à partir du fichier ' . $file)
                ->setStatus(Task::Waiting)
                ->save()
                ->getId();

//            if ($taskTime===0 || abs(time()-$taskTime)<30)
                Runtime::redirect('/TaskManager/TaskStatus?id='.$id);
//            else
//                Runtime::redirect('/TaskManager/Index');
//            return;
        }

        $gzPath='compress.zlib://'.$path;

        echo '<ol>';

        // Ouvre le fichier
        echo '<li>Ouverture du fichier ', $file, '...</li>';
        $xml=new XMLReader();
        $xml->open($gzPath);
        $xml->read();

        // <database>
        $this->expect($xml, 'database');
        $xml->read();

        // <schema>
        echo '<li>Chargement du schéma de la base à partir du fichier dump...</li>';
        $this->expect($xml, 'schema');
        $doc=new DOMDocument();
        $doc->appendChild($xml->expand());
        $dbs=new DatabaseSchema($doc->saveXML());
        if (! $dbs->validate())
            throw new Exception('Le schéma enregistré dans le fichier dump ' . $file . ' n\'est pas valide. Impossible de poursuivre la restauration de la base.');

        // Crée la base une fois qu'on a le schéma
        echo '<li>Suppression de la base de données existante...</li>';
        echo '<li>Création de la base ', $database, ' à partir du schéma du dump...</li>';
        $db = Database::create($database, $dbs, 'xapian');

        $xml->next();

        // <records>
        $this->expect($xml, 'records');
        $count=$xml->getAttribute('count');
        $xml->read();

        echo '<li>Chargement des ', $count, ' enregistrements présents dans le fichier de dump...</li>';
        $i=0;
        $start=microtime(true);
        while($xml->name !== 'records' || $xml->nodeType !== XMLReader::END_ELEMENT)
        {
            $this->expect($xml, 'row');
            $xml->read();

            $db->addRecord();
            while($xml->name !== 'row' || $xml->nodeType !== XMLReader::END_ELEMENT)
            {
                while($xml->nodeType !== XMLReader::ELEMENT) $xml->read();

                $field=$xml->name;
                $node=$xml->expand();
                if ($node===false)
                {
                    echo 'Une erreur s\'est produite, expand()  retourné false<br />';
                    echo 'nodeType : ', $this->nodeType($xml), '<br />';
                    echo 'tag name : ', $xml->name, '<br />';
                    echo 'tag value: ', $xml->value, '<br />';
                    echo 'libxml error: ', var_export(libxml_get_last_error(), true), '<br />';
                    echo 'line : ', xml_get_current_line_number($xml), '<br />';
                    echo 'col : ', xml_get_current_column_number($xml), '<br />';
                    return;
                }
                $value=$this->fieldValue($node);

                $db[$field]=$value;
                $xml->next();
            }

            $db->saveRecord();
            $i++;

            $time=microtime(true);
            if (($time-$start)>1)
            {
                TaskManager::progress($i, $count);
                $start=$time;
            }
            $xml->read();

        }
        $xml->read(); // </records>

        $xml->close();
        $xml=null;
        $db=null;
        echo '<li>Flush de la base...</li>';

        TaskManager::progress();

        echo '<li>La restauration est terminée.</li>';
        echo '</ol>';
    }


    /**
     * Supprime la base de données dont le nom est passé en paramètre.
     *
     * La base est complètement supprimée. Si la base est représentée
     * par un répertoire (cas d'une base xapian, par exemple), l'intégralité
     * du répertoire est supprimée, y compris si le répertoire contient des
     * fichiers qui n'ont rien à vir avec la base.
     *
     * @param string $database le nom de la base à supprimer.
     * @param bool $confirm un flag de confirmation.
     */
    public function actionDelete($database, $confirm=false)
    {
        if (! $confirm)
        {
            Template::run('confirmDelete.html', array('database'=>$database));
            return ;
        }

        // Utilise /config/db.config pour convertir l'alias en chemin et déterminer le type de base
        $path=Config::get("db.$database.path", $database);

        // Si c'est un chemin relatif, recherche dans /data/db
        if (Utils::isRelativePath($path))
            $path=Utils::makePath(Runtime::$root, 'data/db', $path);


        if (! $this->delete($path))
            throw new Exception('La suppression de la base a échouée.');

        // Charge le fichier de config db.config
        $pathConfig=Runtime::$root.'config' . DIRECTORY_SEPARATOR . 'db.config';
        if (file_exists($pathConfig))
        {
            $config=Config::loadXml(file_get_contents($pathConfig));

            // Ajoute un alias
            if (isset($config[$database]))
            {
                unset($config[$database]);
                // Sauvegarde le fichier de config
                ob_start();
                Config::toXml('config', $config);
                $data=ob_get_clean();
                file_put_contents($pathConfig, $data);
            }
        }

        // Redirige vers la page d'accueil
        Runtime::redirect('/'.$this->module);
    }

    /**
     * Delete a file, or a folder and its contents
     *
     * @author      Aidan Lister {@link aidan@php.net}
     * @version     1.0.3
     * @link        http://aidanlister.com/repos/v/function.rmdirr.php
     * @param       string   $dirname    Directory to delete
     * @return      bool     Returns TRUE on success, FALSE on failure
     */
    private function delete($dirname) // todo: !DRY , figure déjà dans AdminFiles
    {
        // Sanity check
        if (!file_exists($dirname)) {
            return false;
        }

        // Simple delete for a file
        if (is_file($dirname) || is_link($dirname)) {
            return unlink($dirname);
        }

        // Loop through the folder
        $dir = dir($dirname);
        while (false !== $entry = $dir->read()) {
            // Skip pointers
            if ($entry == '.' || $entry == '..') {
                continue;
            }

            // Recurse
            $this->delete($dirname . DIRECTORY_SEPARATOR . $entry);
        }

        // Clean up
        $dir->close();
        return rmdir($dirname);
    }

}

class DumpException extends Exception
{
    public function __construct($message)
    {
        parent::__construct('Dump invalide : ' . htmlspecialchars($message));
    }
}
?>