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
class AdminDatabases extends Admin
{
    /**
     * @var XapianDatabaseDriver2
     */
    public $selection;
    
    
    /**
     * Construit la liste des bases de données connues du système (i.e. 
     * référencées dans le fichier de configuration db.config)
     * 
     * @return array un tableau contenant les noms des bases
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
     * @return StdClass un objet contenant les propriétés suivantes :
     * - type : le type de base de données
     * - path : le path exact de la base
     * - count : le nombre total d'enregistrements dans la base  
     * - error : un message d'erreur si la base de données indiquée n'existe
     *   pas ou ne peut pas être ouverte
     */
    public static function getDatabaseInfo($name)
    {
        $info=new StdClass();
        $info->error=null;
        $info->type=Config::get("db.$name.type");
        $info->path=null;
        $info->count=null;

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
     * Affiche la liste des bases de données référencées dans le fichier
     * de configuration <code>db.config</code>.
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
     * Dans un second temps (une fois qu'on a la confirmation), on crée une 
     * tâche au sein du gestionnaire de tâches.
     * 
     * Enfin, la réindexation a proprement parler est exécutée par le 
     * {@link TaskManager}.
     *
     * @param string $database le nom de la base à réindexer
     * @param boolean $confirm le flag de confirmation
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
                    'database' => $database,
                    'schemas'=>$schemas
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
     * 1. Demande à l'utilisateur le nom de la base à créer, génère
     * une erreur s'il existe déjà une base portant ce nom.
     * 2. Demande à l'utilisateur le nom du schéma à utiliser.
     * 3. Crée la base. 
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