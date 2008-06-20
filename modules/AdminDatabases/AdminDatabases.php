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
/**
 * Module d'administration permettant de g�rer les bases de donn�es de 
 * l'application. 
 * 
 * Ce module permet de lister les bases de donn�es de l'application et offre des 
 * fonctions permettant de {@link actionNew() cr�er une nouvelle base}, de 
 * {@link actionSetSchema() modifier la structure} d'une base existante en lui
 * appliquant un nouveau {@link DatabaseSchema sch�ma} et de lancer une 
 * {@link actionReindex() r�indexation compl�te} de la base.
 * 
 * @package     fab
 * @subpackage  Admin
 */
class AdminDatabases extends Admin
{
    /**
     * La base en cours.
     * 
     * Cette propri�t� n'est utilis�e que par {@link actionReindex()} pour 
     * permettre aux templates d'acc�der � la base de donn�es en cours
     * (par exemple pour afficher le nombre de notices).
     * 
     * @var XapianDatabaseDriver2
     */
    public $selection;
    
    
    /**
     * Retourne la liste des bases de donn�es connues du syst�me.
     * 
     * La m�thode utilise le fichier de configuration 
     * {@link /AdminConfig#db.config db.config} pour �tablir la liste des bases 
     * de donn�es.
     * 
     * @return array|null un tableau contenant le nom des bases r�f�renc�es dans 
     * le fichier de configuration. Le tableau obtenu est tri� par ordre 
     * alphab�tique. La m�thode retourne <code>null</code> si aucune base n'est 
     * d�finie. 
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
     * @param string $name le nom de la base � examiner.
     *
     * @return StdClass un objet contenant les propri�t�s suivantes :
     * - <code>type</code> : le type de base de donn�es
     * - <code>path</code> : le path exact de la base
     * - <code>count</code> : le nombre total d'enregistrements dans la base  
     * - <code>error</code> : un message d'erreur si la base de donn�es indiqu�e
     *   n'existe pas ou ne peut pas �tre ouverte
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
     * Page d'accueil du module d'administration des bases de donn�es.
     * 
     * Affiche la liste des bases de donn�es de l'application.
     * 
     * La m�thode ex�cute le template d�finit dans la cl�
     * <code><template></code> du fichier de configuration en lui passant
     * en param�tre une variable <code>$database</code> contenant la liste
     * des bases telle que retourn�e par {@link getDatabases()}.
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
     * La page affich�e correspond au template indiqu� dans la cl�
     * <code><template></code> du fichier de configuration. Celui-ci est
     * appell� avec une variable <code>$database</code> qui indique le nom
     * de la base de donn�es � r�indexer.
     * 
     * Ce template doit r�appeller l'action Reindex en passant en param�tre
     * la valeur <code>true</code> pour le param�tre <code>$confirm</code>.
     * 
     * La m�thode cr�e alors une {@link Task t�che} au sein du 
     * {@link /TaskManager gestionnaire de t�ches} qui se charge d'effectuer 
     * la r�indexation.
     * 
     * Remarque :
     * Si la base de donn�es est vide (aucun document), la m�thode Reindex
     * refusera de lancer la r�indexation et affichera un message d'erreur
     * indiquant que c'est inutile.
     *
     * @param string $database le nom de la base � r�indexer.
     * @param bool $confirm le flag de confirmation.
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
    

    /**
     * Modifie la structure d'une base de donn�es existante en lui appliquant
     * un nouveau {@link DatabaseSchema sch�ma}.
     * 
     * La m�thode commence par afficher le template 
     * <code>chooseSchema.html</code> avec une variable <code>$database</code> 
     * qui indique le nom de la base de donn�es � modifier.
     * 
     * Ce template contient des slots qui utilisent l'action 
     * {AdminSchemas::actionChoose()} pour pr�senter � l'utilisateur la liste 
     * des sch�mas disponibles dans l'application et dans fab.
     * 
     * L'utilisateur choisit alors le sch�ma qu'il souhaite appliquer � la base.
     * 
     * La m�thode va alors effectuer une comparaison entre le sch�ma actuel
     * de la base de donn�es et le sch�ma choisi par l'utilisateur.
     * 
     * Si les sch�mas sont identiques, le template <code>nodiff.html</code>
     * est affich�.
     * 
     * Dans le cas contraire, la m�thode va afficher la liste de toutes les
     * modifications apport�es (champs ajout�s, supprim�s...) et va demander
     * � l'utilisateur de confirmer qu'il veut appliquer ce nouveau sch�ma �
     * la base.
     * 
     * Elle ex�cute pour cela le template indiqu� dans la cl� 
     * <code><template></code> du fichier de configuration en lui passant en 
     * param�tre :
     * - <code>$database</code> : le nom de la base qui va �tre modifi�e ;
     * - <code>$schema</code> : le nom du nouveau schema qui va �tre appliqu� � 
     *   la base ;
     * - <code>$changes</code> : la liste des diff�rences entre le sch�ma actuel
     *   de la base de donn�es et le nouveau sch�ma. Cette liste est �tablie
     *   en appellant la m�thode {@link DatabaseSchema::compare()} du nouveau 
     *   sch�ma.
     * - <code>$confirm</code> : la valeur <code>false</code> indiquant que 
     *   la modification de la base n'a pas encore �t� effectu�e. 
     * 
     * Si l'utilisateur confirme son choix, la m�thode va alors appliquer le 
     * nouveau sch�ma � la base puis va r�afficher le m�me template avec cette
     * fois-ci la variable <code>$confirm</code> � <code>true</code>.
     * 
     * Ce second appel permet d'afficher � l'utilisateur un r�acapitulatif de
     * ce qui a �t� effectu� et de lui proposer de lancer une 
     * {@link actionReindex() r�indexation compl�te de la base} s'il y a lieu.
     *
     * @param string $database le nom de la base � r�indexer.
     * @param string $schema le nom du schema � appliquer.
     * @param bool $confirm un flag indiquant si l'utilisateur a confirm�
     * don choix.
     */
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
                    'database' => $database
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
     * La m�thode commence par demander � l'utilisateur le nom de la base
     * de donn�es � cr�er et v�rifie que ce nom est correct.
     * 
     * Elle utilise pour cela le template <code>new.html</code> qui est appell�
     * avec une variable <code>$database</code> contenant le nom de la base
     * � cr�er et une variable <code>$error</code> qui contiendra un message
     * d'erreur si le nom de la base indiqu�e n'est pas correct (il existe d�j�
     * une base de donn�es ou un dossier portant ce nom).
     * 
     * Elle demande ensuite le nom du {@link DatabaseSchema sch�ma} � utiliser
     * et v�rifie que celui-ci est correct.
     * 
     * Elle utilise pour cela le template <code>newChooseSchema.html</code> qui 
     * est appell� avec une variable <code>$database</code> contenant le nom de 
     * la base � cr�er, une variable <code>$schema</code> contenant le nom
     * du sch�ma choisi et une variable <code>$error</code> qui contiendra un 
     * message d'erreur si une erreur est trouv�e dans le sch�ma (sch�ma
     * inexistant, non valide, etc.)
     * 
     * Si tout est correct, la m�thode cr�e ensuite la base de donn�es dans le 
     * r�pertoire <code>/data/db/</code> de l'application puis cr�e un nouvel
     * alias dans le fichier {@link /AdminConfig#db.config db.config} de l'application.
     *
     * Enfin, l'utilisateur est redirig� vers la {@link actionIndex() page 
     * d'accueil} du module sur la base de donn�es cr��e.
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