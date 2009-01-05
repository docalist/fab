<?php

//$t=array('a'=>'A', 'b'=>'B', 'c'=>'C');
////$t2=array_map(null, $t, array());
//$t2=array_fill_keys(array_keys($t), null);
//
//var_export($t2);
//die();
define('DB_PATH', Runtime::$root.'data/db/ascodocpsy');
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
    /**
     * @var XapianDatabaseDriver
     */
    public $selection;
    
    /*
     * affiche la liste des bases de données référencées dans db.config
     * et un bouton permettant de créer une nouvelle base
     */
    public function NUactionIndex()
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
    private function NUgetDatabases()
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
     * Construit la liste des schémas disponibles
     *
     * @return array(DatabaseSchema) un tableau contenant tous les schémas 
     * disponibles.
     */
    private static function NUgetTemplates()
    {
        $templates=array();
        
        // Construit la liste
        foreach(array(Runtime::$fabRoot, Runtime::$root) as $path)
        {
            $path.='data/schemas/';
            if (false === $files=glob($path.'*.xml')) continue;

            foreach($files as $file)
            {
                try
                {
                    $name=basename($file);
                    $templates[basename($file)]=new DatabaseSchema(file_get_contents($file));
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
     * - les deux : crée la base, redirige vers EditSchema?type=db&name=xxx
     * 
     * @param string name le nom de la base de données à créer
     * @param string def le nom du schéma contenant la structure de la base de 
     * données à créer
     * 
     * @throws Exception s'il existe déjà une base de données ayant le nom
     * indiqué ou si le template spécifié n'existe pas
     */
    public function NUactionNewDatabase()
    {
        $dir='data/schemas/';
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
                $dbs=new DatabaseSchema(file_get_contents($template));
            }
            
            // Aucune erreur : crée la base
            if (!$errors)
            {
                // Crée la base
                $db=Database::create($path, $dbs, 'xapian');

                // Ajoute un alias dans db.yaml
                throw new Exception('Non implementé : ajouter un alias dans le fichier xml db.config');
                $configPath=Runtime::$root.'config' . DIRECTORY_SEPARATOR . 'db.yaml';
                $t=Config::loadFile($configPath);
                $t[$name]=array('type'=>'xapian', 'path'=>$path);
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
    
    public function NUactionSetSchema($database, $schema, $confirm=false)
    {
        // Détermine le path exact du schéma indiqué
        $schema=Utils::defaultExtension($schema, '.xml');
        $path=Utils::searchFile
        (
            'data/schemas/' . $schema,
            Runtime::$root, 
            Runtime::$fabRoot
        );
        
        if ($path === false)
            throw new Exception("Impossible de trouver le schéma indiqué : $schema");
            
        // Charge le schéma
        $newDbs=new DatabaseSchema(file_get_contents($path));
        
        // Ouvre la base de données et récupère le schéma actuel de la base
        $this->selection=Database::open($database, !$confirm); // confirm=false -> readonly=true, confirm=true->readonly=false
        $oldDbs=$this->selection->getSchema();
        
        // Compare l'ancien et la nouveau schémas
        $changes=$newDbs->compare($oldDbs);

        // variables utilitaires pour avoir des liens sur le nom de la base et du schéma
        $linkDatabase=sprintf
        (
            '<a href="%s" title="%s"><strong>%s</strong></a>',
            Routing::linkFor('/DatabaseInspector/SearchForm?database=' . $database),
            'Inspecter...', 
            $database
        );
        $linkSchema=sprintf
        (
            '<a href="%s" title="%s"><strong>%s</strong></a>',
            Routing::linkFor('/DatabaseAdmin/EditSchema?template=' . $schema), 
            'Editer...', 
            $schema
        );
        
        // Affiche une erreur si aucune modification n'a été apportée
        if (count($changes)===0)
        {
            echo "<p>La base de données $linkDatabase et le modèle $linkSchema",
                 ' ont la même structure, aucune modification n\'est nécessaire.</p>';
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
                    'changes'=>$changes,
                    'linkDatabase'=>$linkDatabase,
                    'linkSchema'=>$linkSchema
                )
            );
            
            return;
        }
        
        // Applique la nouvelle structure à la base
        $this->selection->setSchema($newDbs);
        
        // Affiche le résultat et propose (éventuellement) de réindexer
        Template::run
        (
            config::get('template'),
            array
            (
                'confirm'=>$confirm, 
                'database'=>$database, 
                'schema'=>$schema, 
                'changes'=>$changes,
                'linkDatabase'=>$linkDatabase,
                'linkSchema'=>$linkSchema
            )
        );
    }
    
    public function actionCompare($old,$new)
    {
        //header('content-type: text/plain;charset=ISO-8859-1;');
        
        $dir=Runtime::$root . 'data/schemas/';
        
        $old=Utils::defaultExtension($old, '.xml');
        $new=Utils::defaultExtension($new, '.xml');
        
        echo '<h1>Comparaison des schémas ', $old, ' et ', $new, '</h1>';
        
        $old=new DatabaseSchema(file_get_contents($dir.$old));
        $new=new DatabaseSchema(file_get_contents($dir.$new));
        
        $changes=$new->compare($old);
        
        
        if (count($changes)===0)
            echo '<p>Aucune modification n\'a été détectée.</p>';
        else
        {
            echo '<ul>', "\n";
            $color=array(0=>'green', 1=>'orange', '2'=>'red');
            foreach($changes as $change=>$level)
            {
                echo '    <li style="color:',$color[$level], '">', $change, ' (', $level, ')', '</li>', "\n";
            }
            echo '</ul>', "\n";
            
            echo '<div style="border: 1px solid #000; background-color: #fff; font-size: 1.5em; margin: 1em; padding: 0 1em">';
            switch(max($changes))
            {
                case 0:
                    echo '<p style="color:green">Toutes les modifications peuvent être prises en compte immédiatement, il est inutile de réindexer la base (0).</p>';
                    break;
                case 1:
                    echo '<p style="color:orange">Les modifications apportées peuvent être prises en compte immédiatement, mais le fait de réindexer la base permettrait de purger les données qui ne sont plus nécessaires (1).</p>';
                    break;
                case 2:
                    echo '<p style="color:red">Une ou plusieurs des modifications apportées nécessitent une réindexation de la base (2).</p>';
                    break;
                default: 
                    echo '<p>gloups! max erroné</p>';
            }
            echo '</div>';
        }
    }
    
    /**
     * Crée ou édite un schéma
     *
     * @return unknown
     */
    public function NUactionEditSchema($template='', $new=false)
    {
        $dir='data/schemas/';
        $fabDir=Runtime::$fabRoot.$dir;
        $appDir=Runtime::$root.$dir;

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
                    $errors[]="Il existe déjà un schéma portant ce nom : '" . $template . '"';
                }
                else
                {
                    $dbs=new DatabaseSchema();
                    file_put_contents($appDir.$template,$dbs->toXml());
                }
                $title='Création du schéma '.$template;
            }
            
            // Cas 2 : édition d'un modèle existant
            else
            {
                // Cas 2.1 : on édite un modèle de l'application
                if (file_exists($appDir.$template))
                {
                    $dbs=new DatabaseSchema(file_get_contents($appDir.$template));
                }
    
                else
                {            
                    // Cas 2.2 : c'est un template de fab, on le recopie dans app
                    if (file_exists($fabDir.$template))
                    {
                        $dbs=new DatabaseSchema(file_get_contents($fabDir.$template));
                        file_put_contents($appDir.$template,$dbs->toXml());
                    }
                    else
                    {
                        $errors[]='Le fichier indiqué n\'existe pas : "' . $template . '"';
                    }
                }
                $title='Modification du schéma '.$template;
            }
        }
                
        // Aucun paramètre ou erreur : affiche la liste des templates disponibles
        if (! $dbs)
        {
            return Template::run
            (
                'schemas.html',
                array
                (
                    'templates'=>self::getTemplates(),
                    'error'=>implode('<br />', $errors),
                    'template'=>$template
                )
            );
        }
        
        // Valide et redresse le schéma, ignore les éventuelles erreurs
        $dbs->validate();

        // Charge le schéma dans l'éditeur
        Template::run
        (
            'dbedit.html',
            array
            (
                'schema'=>$dbs->toJson(), // hum.... envoie de l'utf-8 dans une page html déclarée en iso-8859-1...
                'saveUrl'=>'SaveSchema',
                'saveParams'=>"{template:'$template'}",
                'title'=>$title
            )
        );
    }
    
    /**
     * Vérifie et sauvegarde le schéma.
     * 
     * Cette action permet d'enregistrer un schéma modifié avec l'éditeur de 
     * structure.
     * 
     * Elle commence par valider le schéma passé en paramètre. Si des 
     * erreurs sont détectées, une réponse au format JSON est générée. Cette
     * réponse contient un tableau contenant la liste des erreurs rencontrées.
     * La réponse sera interprétée par l'éditeur de schéma qui affiche la
     * liste des erreurs à l'utilisateur.
     * 
     * Si aucune erreur n'a été détectée, le schéma va être enregistré.
     * L'endroit où le schéma va être enregistrée va être déterminé par les
     * variables passées en paramètre. Pour éviter de faire apparaître des 
     * path complets dans les url (ce qui présenterait un risque de sécurité),
     * la destination est déterminée par deux variables (type et name) qui sont 
     * détaillées ci-dessous. Une fois le nouveau schéma enregistré, une
     * chaine de caractères au format JSON est retournée à l'éditeur. Elle 
     * indique l'url vers laquelle l'utilisateur va être redirigé. 
     * 
     * @param string json une chaine de caractères au format JSON contenant le
     * schéma à valider et à enregistrer.
     * 
     * @param string type le type du fichier dans lequel le schéma sera 
     * enregistré si il est correct. 
     * 
     * Type peut prendre les valeurs suivantes :
     * <li>'fab' : un modèle de fab</li>
     * <li>'app' : un modèle de l'application</li>
     * 
     * @param string name le nom du fichier dans lequel le modèle sera enregistré. 
     *
     */
    public function NUactionSaveSchema()
    {
        $json=Utils::get($_POST['schema']);
        
        $dbs=new DatabaseSchema($json);
        
        // Valide le schéma et détecte les erreurs éventuelles
        $result=$dbs->validate();
        
        // S'il y a des erreurs, retourne un tableau JSON contenant la liste
        if ($result !== true)
        {
            header('Content-type: application/json; charset=iso-8859-1');
            echo json_encode(Utils::utf8Encode($result));
            return;
        }
        
        // Compile le schéma (attribution des ID, etc.)
        $dbs->compile();
        
        // Met à jour la date de dernière modification (et de création éventuellement)
        $dbs->setLastUpdate();
        
        // Aucune erreur : sauvegarde le schéma
        $dir='data/schemas/';
        $fabDir=Runtime::$fabRoot.$dir;
        $appDir=Runtime::$root.$dir;
            
        // Récupère les paramètres
        $template=Utils::get($_POST['template']);

        // Vérifie que le fichier existe
        if (!file_exists($appDir.$template))
            throw new Exception('Le fichier indiqué n\'existe pas');
        
        // Enregistre le schéma
        file_put_contents($appDir.$template, $dbs->toXml());
        
        // Retourne l'url vers laquelle on redirige l'utilisateur
        header('Content-type: application/json; charset=iso-8859-1');
        echo json_encode('EditSchema');
    }


    public function actionAscoLoad()
    {
        while (ob_get_level()) ob_end_flush();

        set_time_limit(0);

        // crée la base
        echo 'Création de la base xapian dans ', DB_PATH, '<br />';
//        $xapianDb=Database::open(DB_PATH, false);

        $dbs=new DatabaseSchema(file_get_contents(Runtime::$root . 'data/schemas/ascodocpsy.xml'));
        $xapianDb=Database::create(DB_PATH, $dbs, 'xapian');
        
        
        // Importe des notices de la base bis dans la base xapian
        echo 'Ouverture de la base BIS : ', BIS_PATH, '<br />';
        $bisDb=Database::open(BIS_PATH, true, 'bis');

        echo 'Lancement d\'une recherche * dans la base BIS<br />';
        if (!$bisDb->search('*', array('_sort'=>'+','_start'=>1,'_max'=>-1)))
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
        $xapianDb=Database::open(DB_PATH, false, 'xapian');
        $xapianDb->deleteAllRecords();
        echo 'done';
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
    public function NUactionReindex($database, $confirm=false)
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
            echo '<p>La base ne contient aucun document, il est inutile de lancer la réindexation.</p>';
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
        $db=Database::open(DB_PATH, true, 'xapian');
        $prefix=$_SERVER['QUERY_STRING'];
        $db->dumpTerms($prefix);
    	
    }
}
?>