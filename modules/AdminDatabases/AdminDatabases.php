<?php

/**
 * @package     fab
 * @subpackage  Admin
 * @author      Daniel Ménard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id$
 */

/**
 * Module d'administration des bases de données
 * 
 * @package     fab
 * @subpackage  Admin
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
     * @var XapianDatabaseDriver2
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
        Database::create($path, $dbs, 'xapian2');

        // Charge le fichier de config db.config
        $pathConfig=Runtime::$root.'config' . DIRECTORY_SEPARATOR . 'db.config';
        if (file_exists($pathConfig))
            $config=Config::loadXml(file_get_contents($pathConfig));
        else
            $config=array();
            
        // Ajoute un alias
        $config[$database]=array
        (
            'type'=>'xapian2',
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
}
?>