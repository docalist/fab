<?php

/**
 * @package     fab
 * @subpackage  Admin
 * @author      Daniel M�nard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id$
 */

/**
 * Module d'administration des bases de donn�es
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
     * Construit la liste des bases de donn�es connues du syst�me (i.e. 
     * r�f�renc�es dans le fichier de configuration db.config)
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
     * Retourne des informations sur la base dont le nom est pass� en param�tre.
     *  
     * @return StdClass un objet contenant les propri�t�s suivantes :
     * - type : le type de base de donn�es
     * - path : le path exact de la base
     * - count : le nombre total d'enregistrements dans la base  
     * - error : un message d'erreur si la base de donn�es indiqu�e n'existe
     *   pas ou ne peut pas �tre ouverte
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
     * Page d'accueil du module d'administration des bases de donn�es.
     * 
     * Affiche la liste des bases de donn�es r�f�renc�es dans le fichier
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
     * Lance une r�indexation compl�te de la base de donn�es dont le 
     * nom est pass� en param�tre.
     * 
     * Dans un premier temps, on affiche une page � l'utilisateur lui indiquant 
     * comment fonctionne la r�indexation et lui demandant de confirmer son
     * choix.
     * 
     * Dans un second temps (une fois qu'on a la confirmation), on cr�e une 
     * t�che au sein du gestionnaire de t�ches.
     * 
     * Enfin, la r�indexation a proprement parler est ex�cut�e par le 
     * {@link TaskManager}.
     *
     * @param string $database le nom de la base � r�indexer
     * @param boolean $confirm le flag de confirmation
     */
    public function actionReindex($database, $confirm=false)
    {
        // Si on est en ligne de commande, lance la r�indexation proprement dite
        if (User::hasAccess('cli'))
        {
            // Ouvre la base en �criture (pour la verrouiller)
            $this->selection=Database::open($database, false);
            
            // Lance la r�indexation
            $this->selection->reindex();
            return;
        }
        
        // Sinon, interface web : demande confirmation et cr�e la t�che
        
        // Ouvre la base et v�rifie qu'elle contient des notices
        $this->selection=Database::open($database, true);
        $this->selection->search(null, array('_max'=>-1, '_sort'=>'+'));
        if ($this->selection->count()==0)
        {
            echo '<p>La base ', $database, ' ne contient aucun document, il est inutile de lancer une r�indexation compl�te.</p>';
            return;
        }
        
        // Demande confirmation � l'utilisateur
        if (!$confirm)
        {
            Template::run
            (
                config::get('template'),
                array('database'=>$database)
            );
            return;
        }

        // Cr�e une t�che au sein du gestionnaire de t�ches
        $id=Task::create()
            ->setRequest($this->request)
            ->setTime(0)
            ->setLabel('R�indexation compl�te de la base ' . $database)
            ->setStatus(Task::Waiting)
            ->save()
            ->getId();
            
        Runtime::redirect('/TaskManager/TaskStatus?id='.$id);
    }
    
    public function actionSetSchema($database, $schema='', $confirm=false)
    {
        // Choisit le sch�ma � appliquer � la base
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
        
        // V�rifie que le sch�ma indiqu� existe
        if (Utils::isRelativePath($schema) || ! file_exists($schema))
            throw new Exception('Le sch�ma '. basename($schema) . "n'existe pas");

        // Charge le sch�ma
        $newSchema=new DatabaseSchema(file_get_contents($schema));

        // Ouvre la base de donn�es et r�cup�re le sch�ma actuel de la base
        $this->selection=Database::open($database, !$confirm); // confirm=false -> readonly=true, confirm=true->readonly=false
        $oldSchema=$this->selection->getSchema();
        
        // Compare l'ancien et la nouveau sch�mas
        $changes=$newSchema->compare($oldSchema);

        // Affiche une erreur si aucune modification n'a �t� apport�e
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

        // Affiche la liste des modifications apport�es et demande confirmation � l'utilisateur
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
        
        // Applique la nouvelle structure � la base
        $this->selection->setSchema($newSchema);
        
        // Affiche le r�sultat et propose (�ventuellement) de r�indexer
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
     * Cr�e une nouvelle base de donn�es.
     * 
     * 1. Demande � l'utilisateur le nom de la base � cr�er, g�n�re
     * une erreur s'il existe d�j� une base portant ce nom.
     * 2. Demande � l'utilisateur le nom du sch�ma � utiliser.
     * 3. Cr�e la base. 
     *
     * @param string $database le nom de la base � cr�er.
     * @param string $schema le path du sch�ma � utiliser pour
     * la structure initiale de la base de donn�es.
     */
    public function actionNew($database='', $schema='')
    {
        $error='';
        
        // V�rifie le nom de la base indiqu�e
        if ($database !== '')
        {
            if (! is_null(Config::get('db.'.$database)))
                $error="Il existe d�j� une base de donn�es nomm�e $database. ";
            else
            {
                $path=Runtime::$root . 'data/db/' . $database;
                if (is_dir($path))
                    $error="Il existe d�j� un dossier $database dans le r�pertoire data/db de l'application.";
            }
        }
        
        // Demande le nom de la base � cr�er
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

        // V�rifie le nom du sch�ma indiqu�
        if ($schema !== '')
        {
            if (! file_exists($schema))
                $error = 'Le sch�ma <strong>' . basename($schema) . "</strong> n'existe pas.";
            else
            {
                $dbs=new DatabaseSchema(file_get_contents($schema));
                if (true !== $errors=$dbs->validate())
                    $error = "Impossible d'utiliser le sch�ma <strong>" . basename($schema) . "</strong> :<br />" . implode('<br />', $errors);
            }
        }
        
        // Affiche le template si n�cessaire
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
        
        // OK, on a tous les param�tres et ils sont tous v�rifi�s

        
        // Cr�e la base
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