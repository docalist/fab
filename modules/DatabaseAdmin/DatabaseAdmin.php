<?php

//$t=array('a'=>'A', 'b'=>'B', 'c'=>'C');
////$t2=array_map(null, $t, array());
//$t2=array_fill_keys(array_keys($t), null);
//
//var_export($t2);
//die();
define('DB_PATH', Runtime::$root.'data/db/testdm');
define('BIS_PATH', Runtime::$root.'data/db/ascodocpsy.bed');

/**
 * @package     fab
 * @subpackage  modules
 * @author      Daniel Ménard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id
 */

/**
 * Module d'administration des bases de données
 * 
 * @package     fab
 * @subpackage  modules
 */

class DatabaseAdmin extends Module
{

    /*
     * affiche la liste des bases de données référencées dans db.config
     * et un bouton permettant de créer une nouvelle base
     */
    public function actionIndex()
    {
        
        Template::run
        (
            'dblist.htm',
            array
            (
                'databases'=>$this->getDatabases(),
            )
        );
    }

    
    /**
     * Construit la liste des bases de données connues du système (i.e. 
     * référencées dans le fichier de configuration db.config)
     * 
     * Pour chaque base de données, détermine quelques informations telles
     * que son type, son path, la taille, le nombre d'enregistrements, etc.
     * 
     * @return array
     */
    private function getDatabases()
    {
        $databases=array();
        
        foreach(Config::get('db') as $name=>$info)
        {
            $db=new StdClass();
            $db->error=null;
            $db->name=$name;
            $db->type=$info['type'];

            if (Utils::isRelativePath($info['path']))
                $db->path=Utils::searchFile($info['path'], Runtime::$root . 'data/db');
            else
                $db->path=$info['path'];
                
            $db->size=Utils::dirSize($db->path);
            
            try
            {
                $base=Database::open($name);
            }
            catch (Exception $e)
            {
                $db->error=$e->getMessage();
            }
            $db->count=$base->totalCount();
            $db->lastDocId=$base->lastDocId();
            $db->averageLength=$base->averageLength();
            
            $databases[]=$db;
        }
        return $databases;
    }
    
    /**
     * Construit la liste des modèles de structure de bases de données disponibles
     *
     * @return {array(DatabaseStructure)} un tableau contenant la structure de
     * chacun des modèles disponibles
     */
    private static function getTemplates()
    {
        $templates=array();
        
        // Construit la liste
        foreach(array(Runtime::$fabRoot, Runtime::$root) as $path)
        {
            $path.='data/DatabaseTemplates/';
            if (false === $files=glob($path.'*.xml')) continue;

            foreach($files as $file)
            {
                try
                {
                    $name=basename($file);
                    $templates[basename($file)]=new DatabaseStructure(file_get_contents($file));
                }
                catch(Exception $e)
                {
                    echo 'Impossible de charger le modèle ', $file, ' : ', $e->getMessage(), '<br />';
                }
            }
        }
        
        // Trie par ordre alphabétique du nom
        uksort($templates, 'strcoll');

        return $templates;
    }

    
    /**
     * Crée une nouvelle base de données
     * 
     * - aucun paramètre : affiche un formulaire "saisie du nom, choix du template"
     * - les deux : crée la base, redirige vers EditStructure?type=db&name=xxx
     * 
     * @param string name le nom de la base de données à créer
     * @param string def le nom du modèle de structure de base de données
     * 
     * @throws Exception s'il existe déjà une base de données ayant le nom
     * indiqué ou si le template spécifié n'existe pas
     */
    public function actionNewDatabase()
    {
        $dir='data/DatabaseTemplates/';
        $fabDir=Runtime::$fabRoot.$dir;
        $appDir=Runtime::$root.$dir;
        
        // Récupère les paramètres
        $name=Utils::get($_GET['name']);
        $template=Utils::get($_GET['template']);
        $errors=array();

        // On nous a passé des paramètres : on les vérifie et on crée la base
        if (isset($_GET['name']) || isset($_GET['template']))
        {
            // Vérifie le nom de la base
            if (!$name)
                $errors[]='Veuillez indiquer le nom de la base à créer';
            elseif (Config::get('db.'.$name))
                $errors[]='Il existe déjà un alias de base de données qui porte ce nom';
            elseif(is_dir($path=Runtime::$root.'data/db/'.$name) || is_file($path))
                $errors[]='Il existe déjà un fichier ou un répertoire avec ce nom dans le répertoire data/db';
            
            // Vérifie le template indiqué
            if (! $template)
                $errors[]="Veuillez choisir l'un des modèles proposés";
            elseif (!$template=Utils::searchFile($template, $appDir, $fabDir))
                $errors[]='Impossible de trouver le modèle indiqué';
            else
            {
                $dbs=new DatabaseStructure(file_get_contents($template));
            }
            
            // Aucune erreur : crée la base
            if (!$errors)
            {
                // Crée la base
                $db=Database::create($path, $dbs, 'xapian2');

                // Ajoute un alias dans db.yaml
                throw new Exception('Non implementé : ajouter un alias dans le fichier xml db.config');
                $configPath=Runtime::$root.'config' . DIRECTORY_SEPARATOR . 'db.yaml';
                $t=Config::loadFile($configPath);
                $t[$name]=array('type'=>'xapian2', 'path'=>$path);
                Utils::saveYaml($t, $configPath);
                
                // Ok
                Template::run
                (
                    "databaseCreated.html",
                    array
                    (
                        'name'=>$name,
                        'path'=>$path
                    )
                );
                return;
            }
        }
        
        // Aucun paramètre ou erreur : affiche le formulaire 
        Template::run
        (
            'newDatabase.html',
            array
            (
                'name'=>$name,
                'template'=>$template,
                'templates'=>self::getTemplates(),
                'error'=>implode('<br />', $errors)
            )
        );
    }
    
    /**
     * Crée ou édite un fichier modèle de structure de base de données
     *
     * @return unknown
     */
    public function actionEditStructure()
    {
        $dir='data/DatabaseTemplates/';
        $fabDir=Runtime::$fabRoot.$dir;
        $appDir=Runtime::$root.$dir;
            
        // Récupère les paramètres
        $template=Utils::get($_GET['template']);
        $new=Utils::get($_GET['new']);

        $dbs=null;
        $errors=array();
        if ($template)
        {
            // Cas 1 : Création d'un nouveau modèle
            if ($new)
            { 
                // Vérifie que le modèle à créer n'existe pas déjà
                $template=Utils::defaultExtension($template, '.xml');
                if (file_exists($appDir.$template))
                {
                    $errors[]="Il existe déjà un modèle de structure de base de données portant ce nom : '" . $template . '"';
                }
                else
                {
                    $dbs=new DatabaseStructure();
                    file_put_contents($appDir.$template,$dbs->toXml());
                }
            }
            
            // Cas 2 : édition d'un modèle existant
            else
            {
                // Cas 2.1 : on édite un modèle de l'application
                if (file_exists($appDir.$template))
                {
                    $dbs=new DatabaseStructure(file_get_contents($appDir.$template));
                }
    
                else
                {            
                    // Cas 2.2 : c'est un template de fab, on le recopie dans app
                    if (file_exists($fabDir.$template))
                    {
                        $dbs=new DatabaseStructure(file_get_contents($fabDir.$template));
                        file_put_contents($appDir.$template,$dbs->toXml());
                    }
                    else
                    {
                        $errors[]='Le fichier indiqué n\'existe pas : "' . $template . '"';
                    }
                }
            }
        }
                
        // Aucun paramètre ou erreur : affiche la liste des templates disponibles
        if (! $dbs)
        {
            return Template::run
            (
                'DatabaseTemplates.html',
                array
                (
                    'templates'=>self::getTemplates(),
                    'error'=>implode('<br />', $errors),
                    'template'=>$template
                )
            );
        }
        
        // Redresse la structure de la base, ignore les éventuelles erreurs
        $dbs->validate();
        
        // Charge la structure dans l'éditeur
        Template::run
        (
            'dbedit.html',
            array
            (
                'structure'=>$dbs->toJson(), // hum.... envoie de l'utf-8 dans une page html déclarée en iso-8859-1...
                'saveUrl'=>'saveStructure',
                'saveParams'=>"{template:'$template'}",
                'title'=>'Modification du modèle de structure '.$template
            )
        );
    }
    
    /**
     * Vérifie et sauvegarde la structure d'une base de données.
     * 
     * Cette action permet d'enregistrer une structure de base de données 
     * modifiée avec l'éditeur de structure.
     * 
     * Elle commence par valider la structure passée en paramètre. Si des 
     * erreurs sont détectées, une réponse au format JSON est générée. Cette
     * réponse contient un tableau contenant la liste des erreurs rencontrées.
     * La réponse sera interprétée par l'éditeur de structure qui affiche la
     * liste des erreurs à l'utilisateur.
     * 
     * Si aucune erreur n'a été détectée, la structure va être enregistrée.
     * L'endroit où la structure va être enregistrée va être déterminé par les
     * variables passées en paramètre. Pour éviter de faire apparaître des 
     * path complets dans les url (ce qui présenterait un risque de sécurité),
     * la destination est déterminée par deux variables (type et name) qui sont 
     * détaillées ci-dessous. Une fois la nouvelle structure enregistrée, une
     * chaine de caractères au format JSON est retournée à l'éditeur. Elle 
     * indique l'url vers laquelle l'utilisateur va être redirigé. 
     * 
     * @param string json une chaine de caractères au format JSON contenant la
     * structure de base de données à valider et à enregistrer.
     * 
     * @param string type le type du fichier dans lequel la structure sera 
     * enregistrée si elle est correcte. 
     * 
     * Type peut prendre les valeurs suivantes :
     * <li>'fab' : un modèle de fab</li>
     * <li>'app' : un modèle de l'application</li>
     * 
     * @param string name le nom du fichier dans lequel le modèle sera enregistré. 
     *
     */
    public function actionSaveStructure()
    {
        $json=Utils::get($_POST['structure']);
        $dbs=new DatabaseStructure($json);
        
        // Valide la structure et détecte les erreurs éventuelles
        $result=$dbs->validate();
        
        // S'il y a des erreurs, retourne un tableau JSON contenant la liste
        if ($result !== true)
        {
            header('Content-type: application/json; charset=iso-8859-1');
            echo json_encode(Utils::utf8Encode($result));
            return;
        }
        
        // Met à jour la date de dernière modification (et de création éventuellement)
        $dbs->setLastUpdate();
        
        // Aucune erreur : sauvegarde la structure
        $dir='data/DatabaseTemplates/';
        $fabDir=Runtime::$fabRoot.$dir;
        $appDir=Runtime::$root.$dir;
            
        // Récupère les paramètres
        $template=Utils::get($_POST['template']);

        // Vérifie que le fichier existe
        if (!file_exists($appDir.$template))
            throw new Exception('Le fichier indiqué n\'existe pas');
        
        // Enregistre la structure
        file_put_contents($appDir.$template, $dbs->toXml());
        
        // Retourne l'url vers laquelle on redirige l'utilisateur
        header('Content-type: application/json; charset=iso-8859-1');
        echo json_encode('EditStructure');
    }


    
    
    public function actionAscoLoad()
    {
        while (ob_get_level()) ob_end_flush();

        set_time_limit(0);

        // crée la base
        echo 'Création de la base xapian dans ', DB_PATH, '<br />';
//        $xapianDb=Database::open(DB_PATH, false);

        $dbs=new DatabaseStructure(file_get_contents('d:/webapache/ascoweb/data/DatabaseTemplates/ascodocpsy.xml'));
        $xapianDb=Database::create(DB_PATH, $dbs, 'xapian2');
        
        
        // Importe des notices de la base bis dans la base xapian
        echo 'Ouverture de la base BIS : ', BIS_PATH, '<br />';
        $bisDb=Database::open(BIS_PATH, true, 'bis');

        echo 'Lancement d\'une recherche * dans la base BIS<br />';
        if (!$bisDb->search('*', array('_sort'=>'-','_start'=>1,'_max'=>-1)))
            die('aucune réponse');

        echo '<hr />';
        echo $bisDb->count(), ' notices à charger à partir de la base BIS<br />';
        echo '<hr />';
        
        while(ob_get_level()) ob_end_flush();
        echo '<pre>';
        echo 'nb total de notices chargées; secondes depuis le début; secondes depuis précédent; nb de notices par seconde ; memory_usage ; memory_usage(true); memory_peak_usage ; memory_peak_usage(true)<br />';
        
        $nb=0;
        $startTime=$time=microtime(true);
        $nbRef=0;        
        foreach($bisDb as $record)
        {
            if ($nb %100 == 0)
            {
                $lastTime=$time;
                $time=microtime(true);
                echo sprintf
                (
                    '%6d ; %8.2f ; %6.2f ; %6.2f ; %10d; %10d ; %10d ; %10d<br />', 
                    $nb,
                    $time-$startTime,
                    $time-$lastTime,
                    $nb==0 ? 0.0: 100/($time-$lastTime),
                    memory_get_usage(),
                    memory_get_usage(true),
                    memory_get_peak_usage(),
                    memory_get_peak_usage(true)
                    
                );
                flush();
            }

            $xapianDb->addRecord();
            
            foreach($record as $name=>$value)
            {
                if ($name=='FinSaisie' || $name=='Valide' || $name=='REF') continue;
                
                if ($value=='') $value=null;
                
                if (! is_null($value) && in_array($name, array('Aut','Edit','Lieu','MotCle','Nomp','CanDes','EtatCol', 'Loc','ProdFich')))
                {
                    $value=array_map("trim",explode('/',$value));
                    if (count($value)===1) $value=$value[0];
                }
                
                $xapianDb[$name]=$value;
            }
            
            // Renseigne REF à l'aide du compteur.
            // On obtient ainsi un REF et un doc_id égaux
            $nbRef++;
            //$xapianDb['REF']=$nbRef;
            
            // Remplace les 2 champs FinSaisie et Valide par le champ Statut
            // FinSaisie=0 et Valide=0 : Statut=encours
            // FinSaisie=1 et Valide=0 : Statut=avalider
            // Valide=1 : Statut=valide 
            if ($record['Valide']==1)
                $xapianDb['Statut']='valide';
            else
                $xapianDb['Statut']=($record['FinSaisie']==1) ? 'avalider' : 'encours';
            
            // Initialise LienAnne, Doublon, LastAuthor
            $xapianDb['LienAnne']=$xapianDb['LastAuthor']=null;
            $xapianDb['Doublon']=false;

            $xapianDb->saveRecord();
            $nb++;
//            if ($nb>=10) break;
        }

        // infos du dernier lot chargé
        $lastTime=$time;
        $time=microtime(true);
        echo sprintf
        (
            '%6d ; %8.2f ; %6.2f ; %6.2f<br />', 
            $nb,
            $time-$startTime,
            $time-$lastTime,
            $nb==0 ? 0.0: 100/($time-$lastTime)
        );
        flush();
        
        echo 'Fermeture (et flush) de la base<br />';
        unset($bisDb);
        unset($xapianDb);

        // pour mesurer le temps de fermeture
        $lastTime=$time;
        $time=microtime(true);
        echo sprintf
        (
            '%6d ; %8.2f ; %6.2f ; %6.2f<br />', 
            $nb,
            $time-$startTime,
            $time-$lastTime,
            0
        );
        flush();
        
        echo 'Terminé<br />';
    }
    public function actionDeleteAllRecords()
    {
        set_time_limit(0);
        $xapianDb=Database::open(DB_PATH, false, 'xapian2');
        $xapianDb->deleteAllRecords();
        echo 'done';
    }
    
    public function actionReindex()
    {
        set_time_limit(0);
        $xapianDb=Database::open(DB_PATH, false, 'xapian2');
        $xapianDb->reindex();
        echo 'done';
    }
    public function actionBisToXapian()
    {
        $bisDb=Database::open('ascodocpsy', true);
                
        if (!$bisDb->search('Type=rapport', array('_sort'=>'+','_start'=>1,'_max'=>100)))
            die('aucune réponse');
            
        $xapianDb=Database::open(DB_PATH, false, 'xapian');

        foreach($bisDb as $record)
        {
            $xapianDb->addRecord();
            foreach($record as $name=>$value)
            {
                if ($value) echo $name, ' : ', $value, "<br />";
                $xapianDb[$name]=$value;
            }
            $xapianDb->saveRecord();
            echo '<hr />';
        }
        die('ok');
    }
    
    public function actionDumpTerms()
    {
        $db=Database::open(DB_PATH, true, 'xapian2');
        $prefix=$_SERVER['QUERY_STRING'];
        $db->dumpTerms($prefix);
    	
    }
}
?>