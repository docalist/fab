<?php

/**
 * @package     fab
 * @subpackage  modules
 * @author      Daniel Ménard <Daniel.Menard@bdsp.tm.fr>
 * @author      Séverine Ferron <Severine.Ferron@bdsp.tm.fr>
 * @version     SVN: $Id$
 */


/**
 * Module standard d'import
 * 
 * @package     fab
 * @subpackage  modules
 */
class ImportModule extends DatabaseModule
{
    public function preExecute()
    {
        // Filtre sur les fichiers qui n'ont pas encore été importés
        if (!$this->request->bool('done')->defaults(false)->ok())
            $this->request->add('_filter', 'NOT status:(import_*)');
    }
    
    public function actionDelete($confirm=0)
    {
        // todo: not dry, copier/coller intégral de ce qu'on a dans DatabaseModule
        // si on avait les événements dans fab, on pourrait juste avoir un event "onBeforeDelete, unlink($record[path])"
        
        // Ouvre la base de données
        $this->openDatabase(false);

        // Récupère l'équation de recherche qui donne les enregistrements à supprimer
        $this->equation=$this->getEquation();

        // Paramètre equation manquant
        if (is_null($this->equation))
            return $this->showError('Le ou les numéros des notices à supprimer n\'ont pas été indiqués.');

        // Aucune réponse
        if (! $this->select($this->equation, -1) )
            return $this->showError("Aucune réponse. Equation : $this->equation");

        // TODO: déléguer au TaskManager

        // Supprime toutes les notices de la sélection
        foreach($this->selection as $record)
        {
            $path=$this->selection['Path'];
            if ($path !=='' && !is_null($path))
            {
                echo 'suppression de ', $this->selection['Path'], '<br />';
                if (!@unlink($this->selection['Path']))   // seule différence par rapport au actionDelete de DatabaseModule
                    echo 'erreur durant la suppression du fichier tmp';
            }
            $this->selection->deleteRecord();
        }
        
        // Détermine le template à utiliser
        if (! $template=$this->getTemplate())
        {
            echo '<p>Notice supprimée.</p>';
            return;
        }

        // Détermine le callback à utiliser
        $callback=$this->getCallback();

        // Exécute le template
        Template::run
        (
            $template,  
            array($this, $callback),
            $this->selection->record  //fixme : pas de sens : on passe un record supprimé + il peut y en avoir plusieurs
        );
    }
    
    /**
     * Crée une nouvelle tâche dans le gestionnaire de tâches pour importer les
     * fichiers dont les numéros de REF sont passés en paramètre
     *
     * @param array $REF les numéros de REF des fichiers à importer. Seuls les
     * fichiers en statut 'upload_ok' seront acceptés (une exception sera 
     * générée dans le cas contraire).
     */
    public function actionNewImport(array $REF, $now, $date, $time)
    {
        // Vérifie les paramètres
        $now=$this->request->bool('now')->defaults(false)->ok();
        // todo: vérifier les autres, vérifier le format de date et time
        
        // Détermine l'heure d'exécution
        if ($now)
        {
            $time=0;
        }
        else
        {
            $time=mktime // hour min sec month day year
            (
                substr($time,0,2), substr($time,2,2), substr($time, 4, 2),
                substr($date,4,2), substr($date,6,2), substr($date, 0, 4)
            );
            if ($time < time())
            {
                echo "<p>Erreur : l'heure indiquée est déjà dépassée.</p>";
                return;    
            }
        }
        // echo strftime('%x %X', $time);
        
        // Ouvre la base de données en écriture
        $this->openDatabase(false);
//        echo 'Base ouverte en écriture<br />';        
        
        // Crée une équation à partir des numéros de REF indiqués
        $equation='Status:upload_ok AND REF:(' . implode(' ', $REF) . ')'; // équation du style REF:(5 8 12)
//        echo 'Equation de recherche : <code>', $equation, '</code><br />';
        
        // Recherche les Refs indiquées (max=toutes, ordre des enregs dans la base)
        $this->selection->search($equation, array('_max'=>-1, '_sort'=>'+'));
//        echo 'Nombre de réponses : ', $this->selection->count(), '<br />';
        
        // Si on a moins de réponses que de REF, c'est qu'au moins une des REF n'était pas valide
        if ($this->selection->count() !== count($REF))
            throw new Exception('Certains des numéros indiqués ne sont pas valides');
            
//        echo 'Numéros de REF OK, création de la tâche<br />';

        if ((count($REF)===1))
            $title='Import d\'un fichier dans la base';
        else
            $title='Import de '.count($REF).' fichiers dans la base';
        
        // Crée la tâche maintenant pour récupérer son ID
        $task=new Task();
        $taskId=$task->save()->getId();
        
//        echo 'taskId : ', $taskId, '<br />';

        // Pour chaque enreg, stocke le numéro de tâche et met à jour le statut        
//        echo 'Mise à jour du statut des enregs<br />';
        foreach($this->selection as $record)
        {          
            $this->selection->editRecord();
            $record['Status']='task';
            $record['TaskId']=$taskId;
            $this->selection->saveRecord();
            echo 'Enreg REF=', $record['REF'], ' modifié<br />';
        }

        if ($time!==0 && $time<time()) $time=time(); // au cas où la mise à jour de la base ait pris beaucoup de temps
        
        // Initialise les paramètres de la tâche
        $task
            ->setRequest($this->request->setAction('Import')->keepOnly('REF'))
            ->setTime($time)
            ->setLabel($title)
            ->setStatus(Task::Waiting)
            ->save();

        if ($time===0)
            Runtime::redirect('/TaskManager/TaskStatus?id='.$taskId);
        else
            Runtime::redirect('/TaskManager/Index');
            //Runtime::redirect('/TaskManager/Index?_equation=id:'.$taskId);
    }
    
    /**
     * Réalise l'import des fichiers dont les numéros de REF sont passés en
     * paramètres.
     * 
     * Appellé par le TaskManager lorsque la tâche est lancée.
     *
     * @param array $REF numéros de référence des fichiers à importer
     */
    public function actionImport(array $REF)
    {
        // Ouvre la base de données en écriture
        $this->openDatabase(false);
        
        // Affiche le titre
        $nbFiles=count($REF);
        if ($nbFiles===1)
            echo '<h2>Import d\'un fichier dans la base (ref ', $REF[0], ')</h2>';
        else
            echo '<h2>Import de ', $nbFiles, ' fichiers dans la base</h2>';
        
        // Initialise les numéros de première et dernière notice
        $firstRef=$lastRef=0;
        
        foreach($REF as $i=>$REF)
        {
            if ($nbFiles>1)
                echo '<h3>Fichier n°', $i+1, ' (ref ', $REF, ')</h3>';
            
            // Crée l'équation de recherche
            // On filtre sur les fichiers autorisés à être importés
            $equation='Status:task AND REF:'.$REF;

            // Ouvre la base de données en écriture
//            $this->openDatabase(false);
//            echo '1ère ouverture de la base en écriture<br />';

            // Recherche la fiche du fichier à importer
            if (! $this->selection->search($equation, array('_max'=>-1, '_sort'=>'+')))
            {
                echo "<p style='color:red; font-weight: bold;'>Numéro de référence invalide : $REF (le fichier correspondant n'existe pas)</p>";
                //unset($this->selection);
                continue;
            }
            
            // Récupère le path et le nom du fichier à importer
            $path=$this->selection['Path'];
            $fileName=$this->selection['FileName'];
            
            echo '<p>Début de l\'import du fichier ', $fileName, ' le ', strftime('%x à %X'), '</p>';

            // Met à jour le statut du fichier
            $this->selection->editRecord();
            $this->selection['Status']='import_running';
            $this->selection->saveRecord();

            // Ferme la base
//            unset($this->selection);
//            echo '1ère fermeture de la base<br />';
            
            // Détermine le callback à utiliser pour l'import des fichiers
            $callback=$this->callback(Config::get('importcallback'));
            
            // Aucun callback, l'import ne peut pas se faire
            if (is_null($callback))
            {
                $ok=false;
                $msg='Import du fichier impossible : Erreur interne : le callback d\'import n\'a pas été défini en configuration';
                echo '<p>', $msg, '</p>';
            }
            
            // Import du fichier
            else
            {
                // Détermine le path du fichier dans lequel seront stockées les notices erronées
                // Ces fichiers sont stockés dans le même répertoire que les fichiers à importer,
                // en étant préfixés par 'err'
                $errorFile=tempnam(Utils::makePath($this->selection->getPath(), 'files'),'err'); 
                
                // todo : revoir le paramètre $path quand selection['Path'] 
                // contiendra un path relatif            
                list($ok,$msg,$first,$last)=call_user_func($callback,$path,$errorFile);
            }
            
            // on lui passe en paramètres :
            // - $selection['Path'] : le path du fichier à importer
            // - $errorFile : le path d'un fichier dans lequel il peut
            //   stocker les notices erronnées (tmp_name('db/files', 'err'))
            // le callback retourne :
            // - le statut
            // - le contenu du champ Notes

            // - true ou null : ok, tout a bien marché (unlink $errorFile) 
            // - false : ? ça n'a pas marché, mais on ne sait pas pourquoi
            // - string (ou array de string ?) : liste des erreurs rencontrées

            // stocker dans Notes le message d'erreur + mention générée par nous
            // (heure de début et de fin, durée...)
            // status=import_ok, import_warning ou import_error
            
            // Ouvre de nouveau la base de données en écriture
//            $this->openDatabase(false);
//            echo '2e ouverture de la base en écriture<br />';
            
            // L'import s'est bien passé
            if ($ok)
            {
                // Supprime le fichier des notices erronées si toutes les notices ont été importées            
                unlink($errorFile);
                
                // Récupère les numéros de la première et dernière notice
                $firstRef=$firstRef===0 ? $first : min($firstRef,$first);
                $lastRef=max($lastRef,$last);
            }
            
            // Met à jour le statut du fichier et renseigne le champ Notes
            if ($this->selection->search("REF=$REF", array('_max'=>1)))
            {
                $this->selection->editRecord();                
                $this->selection['Status']=($ok) ? 'import_ok' : 'import_error';
                $this->selection['Notes'].=$msg;
                $this->selection->saveRecord();
            }
            
            // Ferme la base
//            unset($this->selection);
//            echo '2e fermeture de la base<br />';

            $time=strftime('%x à %X');
            echo '<p>Fin de l\'import le ', $time, '</p>';
        }
        
        // TODO : Créer une clé de config 'dedouble' (true, false) pour dire si on 
        // doit lancer le dédoublonnage automatiquement après l'import 
        
        // Dédoublonnage sur les notices importées
        if (! is_null($firstRef) && ! is_null($lastRef))
        {
            // Crée la requête
            $equation="REF:$firstRef";
            $equation.=$lastRef!==0 ? "..$lastRef" : '';
            $request=Request::create()->setModule('DedupModule')->setAction('Dedup')->set('_equation',$equation);
            
            // Titre de la tâche
            $label='Dédoublonnage ';
            $label.=$nbFiles===1 ? 'du fichier intégré' : "des $nbFiles fichiers intégrés";
            $label.=' dans la base '.Config::get('database').' le '.$time;

            // Crée une tâche au sein du gestionnaire de tâches
            $id=Task::create()
                ->setRequest($request)
                ->setTime(0)
                ->setLabel($label)
                ->setStatus(Task::Waiting)
                ->save()
                ->getId();
                    
            // Propose un lien vers le résultat du dédoublonnage
            echo '<p><a href="', Routing::linkFor('/TaskManager/TaskStatus?id='.$id),'">Voir le résultat du dédoublonnage réalisé sur les notices importées</a></p>';
        }
    }
    
    public function actionUpload()
    {
        // Ouvre la base de données en écriture
        $this->openDatabase(false);

        // Détermine le callback à utiliser pour la validation du fichier
        $callback=$this->callback(Config::userGet('validcallback'));
        
        // Répertoire dans lequel vont être stockés les fichiers uploadés
        $dir=Utils::makePath($this->selection->getPath(), 'files');
        
        foreach($_FILES as $file) // todo: on ne devrait pas utiliser $_FILES, request devrait avoir les méthodes pour gérer les fichiers
        {
            // Détermine un nom pour le fichier uploadé
            // todo : $path doit être un chemin relatif par rapport au répertoire de la base : files/ficXXX.tmp
            $path=tempnam($dir, 'fic');
            
            // Vérifie que le fichier temporaire a été créé dans le bon répertoire
            // Permet de vérifier que $dir existe, qu'on peut écrire dedans, etc.
            if (strpos($path,$dir)!==0)
                throw new Exception(sprintf('Impossible de créer un fichier dans le répertoire %s (erreur de configuration du serveur : vérifiez l\'existence et les droits de ce répertoire)', $dir));
                
            // Vérifie et copie le fichier uploadé
            $result=Utils::uploadFile($file, $path, $callback);

            switch(true)
            {
                case $result===true: // ok, on crée un enreg
                    $this->selection->addRecord();
                    
                    $this->selection['Path']=$path;
                    $this->selection['FileName']=$file['name'];
                    $this->selection['Size']=$file['size'];
                    $this->selection['Status']='upload_ok';
                  //$this->selection['TaskId']=null;
                  //$this->selection['Notes']='';
                    $this->selection['Creation']=$this->selection['LastUpdate']=time();
                    $this->selection['Ident']=User::get('login');
                    
                    $this->selection->saveRecord();                   
                    break;
                        
                case $result===false: // aucun fichier
                    unlink($path);
                    break; 
                    
                default: // erreur
                    unlink($path);
//                    echo $result;

                    $this->selection->addRecord();
                    
                    // todo : no dry
                    
                  //$this->selection['Path']=null; // le fichier *n'a pas* été stocké puisqu'on a eu une erreur
                    $this->selection['FileName']=$file['name'];
                    $this->selection['Size']=$file['size'];
                    $this->selection['Status']='upload_error';
                  //$this->selection['TaskId']=null;
                    $this->selection['Notes']=$result;
                    $this->selection['Creation']=$this->selection['LastUpdate']=time();
                    $this->selection['Ident']=User::get('login');
                    
                    $this->selection->saveRecord();
            }
        }
        
        Runtime::redirect('/'.$this->request->getModule().'/'); // todo: +anchor du premier ajouté
//        echo 'upload done';
    }
    

    /**
     * Génère un tableau d'horaires
     * 
     * La fonction timeSteps génère un tableau contenant tous les horaires
     * possibles entre l'heure de début et l'heure de fin indiquées en avançant
     * de $step minutes à chaque fois.
     * 
     * Exemples :
     * <code>
     * timeSteps(23, 1, 30) // de 23h00 à 01h00 du matin par tranches de 30 minutes
     * -> '23:00', '23:30', '00:00', '00:30', '01:00'
     * 
     * timeSteps(12, 13, 15) // de midi à treize heures par tranches d'un quart d'heure
     * -> '12:00', '12:15', '12:30', '12:45', '13:00'
     * </code>
     *
     * Remarques :
     * - si les paramètres indiqués sont en dehors de l'intervalle autorisé, ils
     *   seront ajustés (par exemple si vous indiquez 25 comme heure de début, 
     *   les horaires générés commenceront à 01h00 du matin).
     * - les horaires générés contiennent toujours les heures de début et de fin.
     * - si step vaut 0, seul l'horaire de début est généré :
     * <code>
     *     timeSteps(12, 12, 0) -> '12:00'
     * </code>
     * - si les horaires de début et de fin sont identiques, tous les horaires
     *   possibles sur 24 heures sont générés :
     * <code>
     *     timeSteps(12, 12, 60) // de midi à midi d'heure en heure
     *     -> '12:00', '13:00', '14:00' ... '09:00', '10:00', '11:00'
     * </code>  
     * 
     * @param int $start l'heure de début (de 0 à 23)
     * @param int $end l'heure de début (de 0 à 23)
     * @param int $step le "pas" à appliquer, en minutes (de 0 à 60)
     * @param string $format le format à utiliser pour générer les valeurs du
     * tableau retourné, tel que reconnu par la fonction 
     * {@link http://php.net/strftime strftime()} de php.
     *  
     * @return array un tableau de chaines contenant les différents horaires 
     * générés.
     * 
     * Les clés du tableau contiennent toujours l'horaire sous la forme
     * 'hhmmss' (i.e. heures minutes secondes sur deux chiffres) et les
     * valeurs associées contiennent le même horaire mais sous la forme indiquée
     * par $format.
     * 
     * Par défaut, $format stocke les valeurs avec le même format
     * que les clés, mais vous pouvez indiquer un format différent si vous
     * souhaitez présenter les horaires autrement à l'utilisateur :
     * 
     * Exemples :
     * <code>
     * // Format par défaut : clés et valeurs sont identiques
     * timeSteps(22, 23, 30) 
     * -> array('22:00'=>'22:00', '22:30'=>'22:30', '23:00'=>'23:00')
     * 
     * // Utilise le format préféré de représentation de l'heure (%X) :
     * timeSteps(22, 23, 30, '%X') 
     * -> array('22:00'=>'22:00:00', '22:30'=>'22:30:00', '23:00'=>'23:00:00')
     * 
     * // N'affiche que l'heure et un libellé
     * timeSteps(22, 23, 60, '%H heures') 
     * -> array('22:00'=>'22 heures', '23:00'=>'23 heures')
     * </code> 
     */
    public function timeSteps($start, $end, $step, $format='%H:%M')
    {
        // Si step vaut zéro, on ne génère que l'heure de début
        if ($step==0)
            return array(sprintf('%02d0000', $start)=>strftime($format, mktime($start, 0, 0)));
        
        // Ajuste start et end dans l'intervalle autorisé
        $start=$start % 24;
        if ($end<$start) $end+=24;
        
        // Calcule le nombre d'horaires à généré (zéro = journée complète)
        $nb=($end-$start)%24;
        if ($nb==0) $nb=24;
        
        // Génère tous les horaires
        $t=array();
        for($i=0;$i<$nb;$i++)
        {
            for ($j=0; $j<60/$step; $j++)
            {
                $t[sprintf('%02d%02d00', $start, $j*$step)] =
                    strftime($format, mktime($start, $j*$step, 0));
            }
            $start++;
            if ($start>=24) $start-=24;
        }
        
        // Ajoute l'horaire de fin (sauf si on génère une journée complète) 
        if ($nb!= 24) 
            $t[sprintf('%02d%02d00', $start, 0)]=strftime($format, mktime($start, 0, 0));
        
        // Terminé
        return $t;
    }
    
    /**
     * Vérifie et charge un callback.
     *
     * @param callback $callback le callback à vérifier et à initialiser.
     * Il peut s'agir :
     * - d'une chaine de caractères simple : dans ce cas, le callback doit être 
     *   une fonction globale définie par php ou par l'application.
     * - une chaine de caractères de la forme 'class::méthode'
     * - une chaine de caractères de la forme 'class->méthode'
     * - une chaine de caractères de la forme 'self::méthode'
     * - une chaine de caractères de la forme 'this->méthode'
     * 
     * un tableau de deux éléments contenant :
     * - (chaine,chaine) idem 'class::méthode'
     * - ('self',chaine) 'self::méthode'
     * - ('this',chaine) 'this->méthode'
     * - (objet,chaine)  'class->méthode'
     * 
     * @throws BadCallbackException si le callback n'est pas valide
     * 
     * @return callback le callback vérifié et modifié
     */
    public final function callback($callback)
    {
        // Si on nous passe null, retourne null
        if (is_null($callback)) return null;
        
        // Si c'est une chaine, analyse et transforme éventuellement en tableau
        if (is_string($callback))
        {
            if ($callback==='') return null;
            
            if (strpos($callback, '::'))
            {
                $callback=explode('::',$callback, 2);
                $static=true;
            }
            elseif (strpos($callback, '->'))
            {
                $callback=explode('->',$callback, 2);
                $static=false;
            }
            else // simple chaine, on traite le cas maintenant
            {
                if (is_callable($callback)) return $callback;
                throw new BadCallbackException($callback);
            }
        }
        
        // Tableau : vérifie qu'on a deux éléments numérotés 0 et 1
        elseif (is_array($callback))
        {
            if ( count($callback)!==2 || !isset($callback[0]) || !isset($callback[1]) )
                throw new BadCallbackException($callback);
                
            if (is_string($callback[0]))
                $static=true;
            elseif(is_object($callback[0]))
                $static=false;
            else 
                throw new BadCallbackException($callback);
        }
        
        // Autre chose : c'est une erreur
        else
        { 
            throw new BadCallbackException($callback);
        }
        
        // arrivé là on a forcément un tableau de la forme ('self'|'this'|'classe'|objet ; 'méthode')
        if (is_string($class=$callback[0]))
        {
            if ($class==='self')
            {
                $callback[0]=get_class($this);
            }
            elseif ($class==='this')
            {
                $callback[0]=$this;
            }
            else
            {
                if (class_exists($class, true))
                {
                    if (! $static) // classe existe, appel dynamique, crée une instance du module
                    {
                        if (is_subclass_of($class, 'Module'))
                        {
                            $callback[0]=Module::loadModule($class);
                            Config::addArray($callback[0]->config);    // fixme: objectif : uniquement $this->config mais pb pour la config transversale (autoincludes...) en attendant : on recopie dans config générale
                        }
                        else
                            $callback[0]=new $class();
                    }
                }
                else // la classe n'existe pas, charge le source du module
                {
                    try
                    {
                        $object=Module::loadModule($class);
                        // optimisation : if $static, ne charger que le source, pas la config
                        
                        if ($static)
                        {
                            $callback[0]=$class;
                        }
                        else
                        {
                            $callback[0]=$object;
                            Config::addArray($callback[0]->config);    // fixme: objectif : uniquement $this->config mais pb pour la config transversale (autoincludes...) en attendant : on recopie dans config générale
                        }
                        
                    }
                    catch (ModuleNotFoundException $e)
                    {
                        throw new BadCallbackException($callback);
                    }
                    catch (Exception $e)
                    {
                        throw $e;
                    }
                }
            }
        }
        
        // Vérifie et retourne le callback obtenu
        if (is_callable($callback)) return $callback;
        throw new BadCallbackException($callback);
        
    }
}

class BadCallbackException extends Exception
{
    public function __construct($callback)
    {
        parent::__construct(sprintf('Callback invalide : %s', Utils::varExport($callback, true))); 
    }
}
?>