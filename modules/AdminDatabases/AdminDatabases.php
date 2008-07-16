<?php

/**
 * @package     fab
 * @subpackage  Admin
 * @author      Daniel M�nard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id$
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
     * @var XapianDatabaseDriver
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
     * Retourne un tableau permettant de construire un fil d'ariane 
     * (breadcrumbs).
     * 
     * {@inheritdoc}
     * 
     * La m�thode ajoute au tableau retourn� par la classe parente le nom du
     * fichier �ventuel pass� en param�tre dans <code>$file</code>.
     * 
     * @return array
     */
    protected function getBreadCrumbsArray()
    {
        $breadCrumbs=parent::getBreadCrumbsArray();

        // Si on a un nom de base en param�tre, on l'ajoute
        if ($file=$this->request->get('database'))
            $breadCrumbs[$this->request->getUrl()]=$file;
        
        return $breadCrumbs;    
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
         * XML 1.0 d�finit la liste des caract�res unicode autoris�s dans 
         * un fichier xml de la fa�on suivante :
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
         * Dans notre cas, la chaine pass�e est de l'ansi. On ne teste que les
         * caract�res < 255
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
        echo 'caract�re ill�gal : ' , var_export($match[0], true), ', code=', ord($match[0]), '<br />';
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
            $xml->endElement($tag);
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
             * XML 1.0 d�finit la liste des caract�res unicode autoris�s dans 
             * un fichier xml de la fa�on suivante :
             * 
             * #x9 | #xA | #xD | [#x20-#xD7FF] | [#xE000-#xFFFD] | [#x10000-#x10FFFF]
             * (source : http://www.w3.org/TR/REC-xml/#charsets)
             * 
             * Tout autre caract�re fera que le fichier xml sera mal form� et ne
             * pourra pas �tre charg�.
             * 
             * Dans fab, un champ, au moins en th�orie, peut contenir des 
             * caract�res binaires. On a le cas dans ascoweb avec la notice 
             * 90691 dont le r�sum� contient "\x05\x05" (?).
             * 
             * Il faut donc qu'on v�rifie tous les caract�res. Dans notre cas, 
             * la chaine pass�e est de l'ansi. On ne teste donc que les 
             * caract�res [0-255].
             * 
             * Si la chaine pass�e est "propre", on l'�crit telle quelle. Sinon,
             * on va encoder la totalit� de la chaine en base64 et on va ajouter
             * l'attribut base64="true" au tag.
             * 
             * Remarque : dans notre traitement on suppose implicitement que
             * si une chaine ansi ne contient pas de caract�res ill�gaux, la
             * chaine obtenue apr�s appel de utf8_encode() n'en contiendra pas
             * non plus.
             * 
             */
            if (0===preg_match('~[^\x09\x0A\x0D\x20-\xFF]~u', $value))
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

                $xml->endElement($tag);
            }
            return;
        }
        
        throw new Exception('Type de valeur non g�r� : ', debug_zval_dump($value));
    }
    
     /**
     * Backup
     * 
     * Fonction r�cup�r�e du r�pertoire 
     * WebApache\Fab septembre 2007, avant checked out from google\modules\DatabaseAdmin
     */ 
    public function actionBackup($database, $taskTime='', $taskRepeat='')
    {
        // D�termine un titre pour la t�che de dump
        $title=sprintf
        (
            'Dump %s de la base %s', 
            ($taskRepeat ? 'p�riodique' : 'ponctuel'), 
            $database
        );
        
        // V�rifie que le r�pertoire /data/backup existe, essaie de le cr�er sinon
        $dir=Runtime::$root.'data' . DIRECTORY_SEPARATOR . 'backup' . DIRECTORY_SEPARATOR;
        if (!is_dir($dir))
        {
            if (! @mkdir($dir))
                throw new Exception('Impossible de cr�er le r�pertoire ' . $dir);
        }
        
        // Partie interface utilisateur
        if (! User::hasAccess('cli'))
        {
            // 1. Permet � l'utilisateur de planifier le dump 
            if ($taskTime==='')
            {
                Template::Run('backup.html', array('error'=>''));
                return;
            }
            
            // 2. Cr�e la t�che
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
        echo '<h1>', $title, '</h1>';
        
        echo '<p>Date du dump : ', strftime('%x %X'), '</p>';
                        
        $selection=Database::open($database, true);
        $selection->search('', array('_sort'=>'+', "_max"=>-1));
        $count=$selection->count();
        echo '<p>La base contient ', $count, ' notices</p>';
        
        // D�termine le path du fichier � g�n�rer
        $path=$dir . $database . '-' . strftime('%Y%m%d-%H%M%S') . '.xml.gz';
        
        echo '<p>G�n�ration du fichier ', $path, '</p>';
        if (file_exists($path))
        {
            throw new Exception('Le fichier de dump existe d�j�.');
        }

        $gzPath='compress.zlib://'.$path;
        // � tester : stockage sur un ftp distant ?
        
        // Ouvre le fichier
        $xml=new XmlWriter();
        $xml->openUri($gzPath);
        //$xml->setIndent(true);
        $xml->setIndentString('    ');
        
        // G�n�re le prologue xml
        $xml->startDocument('1.0', 'UTF-8', 'yes');
        
        // Tag racine
        $xml->startElement('database');     // <database>
        
        // Ajoute une r�f�rence de schema.  Permet d'utiliser xsi:nil pour les
        // valeurs null et xsi:type pour indiquer le type d'un champ
        $xml->writeAttribute('xmlns:xsd', 'http://www.w3.org/2001/XMLSchema');
        $xml->writeAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        
        // Autres attributs du tag racine
        $xml->writeAttribute('name', $database);
        $xml->writeAttribute('timestamp', time());
        $xml->writeAttribute('date', strftime('%x %X'));
               
        // Sch�ma de la base
        $xml->writeRaw("\n");
        $xml->writeRaw($selection->getSchema()->toXml(false, '    '));
        
        // Donn�es
        $xml->startElement('records');     // <records>
        foreach($selection as $i=>$record)
        {
            $xml->startElement('row');      // <row>
            foreach($record as $field=>$value)
            {
                $this->xmlWriteField($xml, $field, $value);
            }
            $xml->endElement();             // </row>
            TaskManager::progress($i, $count);
        }
        $xml->endElement();                 // </records>
        
        // spellings, synonyms, meta data...
        
        // Finalise le fichier xml
        $xml->endElement();                 // </database>
        $xml->flush();
        $xml=null;
        TaskManager::progress();
        
        echo '<p>Dump termin�.</p>';
        echo '<p>Taille du fichier : ', Utils::formatSize(filesize($path)), '</p>';
        
        // Suppression des dump les plus anciens
        $maxDumps=$this->ConfigDatabaseGet('maxdumps', $database, 7);  // Nombre maximum de dumps par base de donn�es (default : 7 jours)
        $maxDays=$this->ConfigDatabaseGet('maxdays', $database, 0);    // Nombre de jours pendant lesquels les dumps sont conserv�s (default : tous)

        echo '<h2>Suppression �ventuelle des dumps ant�rieurs</h2>';
        echo '<p>Param�tres : </p>';
        echo '<ul>';
        echo '<li>Nombre maximum de dumps autoris�s pour la base ', $database, ' : ', ($maxDumps===0 ? 'illimit�' : $maxDumps), '</li>';
        echo '<li>Dur�e de conservation des dumps de la base ', $database, ' : ', ($maxDays===0 ? 'illimit�e' : $maxDays.' jour(s)'), '</li>';
        echo '</ul>';
        
        // Calcule la date "maintenant moins maxDays, � minuit"
        $t=getDate(($maxDays===0) ? 0 : time());
        $t=mktime(0,0,0,$t['mon'],$t['mday']-$maxDays+1,$t['year']);
        
        // Nom minimum d'un fichier
        $minName=$database . '-' . strftime('%Y%m%d-%H%M%S', $t);
        
        if ($maxDumps===0) $maxDumps=PHP_INT_MAX;
        
        $files=glob($dir . $database . '-????????-??????.xml.gz');
        sort($files);
        
        echo '<p>Fichiers supprim�s : </p>';
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
     * Fonction utilitaire utilis�e par actionBackup pour
     * r�cup�rer la valeur de maxDumps et maxDays dans la config.
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
            throw new Exception('Configuration : valeur incorrecte pour la cl� ' . $key . ', entier attendu.');
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
                throw new DumpException('Valeur non support�e pour l\'attribut xsi:type : ' . $node->getAttribute('xsi:type')); 
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
                        throw new DumpException('seuls des blancs sont autoris�s entre des tags <item>'.var_export($child->nodeValue, true));
//                    echo 'noeud type texte contenant des clancs<br />';
                    break;
                    
                default:
                    throw new Exception('Type de noeud non autoris� entre des tags <item>' . var_export($child,true));
            }
        }
        return $value;
    }
    
    public function actionRestore($database, $filename=null)
    {
        // Ouvre le fichier
        $xml=new XMLReader();
        $xml->open(dirname(__FILE__).'/dump.xml');
        $xml->read();
        echo 'initial : ', $xml->name,  '<br />';
        
        // <database>
        $this->expect($xml, 'database');
        $xml->read();
        
        // <schema>
        $this->expect($xml, 'schema');
            
        $doc=new DOMDocument();
        $doc->appendChild($xml->expand());
        $dbs=new DatabaseSchema($doc->saveXML()); 
        echo $dbs->validate() ? 'sch�ma valide' : 'sch�ma invalide', '<br />';
        // var_export($dbs->toXml());
        $xml->next();
        
        // <records>
        $this->expect($xml, 'records');
        $xml->read();
        
        while($xml->name !== 'records' || $xml->nodeType !== XMLReader::END_ELEMENT)
        {
            $this->expect($xml, 'row');
            $xml->read();

            while($xml->name !== 'row' || $xml->nodeType !== XMLReader::END_ELEMENT)
            {
                while($xml->nodeType !== XMLReader::ELEMENT) $xml->read();
                
                $field=$xml->name;
                $value=$this->fieldValue($xml->expand());
                if (is_array($value))
                    echo "<b>$field</b>", ': ', var_export(implode('�', $value),true), '<br />';
                else
                    echo "<b>$field</b>", ': ', var_export($value,true), '<br />';
                $xml->next();
            }
            echo '<hr />';
            $xml->read();
            
        }
        $xml->read(); // </records>
        echo $xml->name, ' : ', $this->nodeType($xml), ' = ', $xml->value, '<br />';
        echo 'apr�s records: ', $xml->name,  '<br />';
        
        
        $xml->close();
        $xml=null;
        die('ok');
        
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