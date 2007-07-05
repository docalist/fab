<?php


/*
 * Created on 12 juil. 06
 *
 * To change the template for this generated file go to
 * Window - Preferences - PHPeclipse - PHP - Code Templates
 */
 
/**
 * Une tâche programmée
 */
class Task
{
    /**
     * Constante de statut des tâches (cf {@link getStatus()})
     */
    const Pending=1;    // la tâche est en attente, créée mais pas encore exécutée
    const Starting=2;   // la tâche est en train de démarrer (le script a été lancé)
    const Running=3;    // la tâche commence à s'exécuter (le script a démarré)
    const Done=4;       // la tâche est terminée, son exécution s'est déroulée normallement
    const Error=5;      // la tâche est terminée mais une erreur est survenue pendant l'exécution
    const Disabled=6;   // la tâche est désactivée

    /**
     * path du répertoire racine de l'application propriétaire de la tâche
     */
    private $root='';
    
    /**
     *  faburl de la tâche à exécuter
     */
    private $task=null;
    
    /**
     * Numéro unique identifiant la tâche
     * L'id n'existe que pour une tâche qui a été enregistrée
     */
    private $id=null;

    /**
     * Date/heure à laquelle la tâche a été créée
     */
    private $creation=null;
    
    /**
     * Date/heure à laquelle la tâche est planifiée
     * (null = jamais, 0=dès que possible)
     */
    private $time=null;
    
    /**
     * Information de répétition de la tâche
     * (null = ne pas répéter)
     */
    private $repeat=null;

    /**
     * Date/heure de la prochaine exécution de la tâche
     */
    private $next=null;

    /**
     * Date/heure de la dernière exécution de la tâche
     */
    private $last=null;
    
    /**
     * Statut de la tâche
     */
    private $status=self::Pending;
    
    /**
     * Titre (nom) de la tâche
     */
    private $title='';
    
    /**
     * Crée une tâche en mémoire.
     * La tâche obtenue doit ensuite être paramétrée (setTask, setTime...) puis être sauvegardée
     */
    public function __construct()
    {
        $this->creation=time();
        $this->root=Runtime::$root;
        //$this->status=self::Pending;
    }
    
    
    public static function load($IdOrpath)
    {
        // Si on nous a passé une chaine, on considère que c'est le path d'un fichier existant
        if (is_string($IdOrpath))
            $path=$IdOrpath;
            
        // Sinon, on considère que c'est un id et on détermine le path du fichier correspondant
        else
        {
            $dir=Runtime::$fabRoot.'data'.DIRECTORY_SEPARATOR.'tasks'.DIRECTORY_SEPARATOR;
            $path=$dir.$IdOrpath.'.task';
        }
                
        // Vérifie que le fichier existe et qu'on peut le lire
        if (! is_readable($path))
            throw new Exception("Impossible de charger la tâche $path, fichier non lisible");
        
        // Désérialise le fichier
        $task=unserialize(file_get_contents($path));
        if (!is_object($task) || ! $task instanceof Task)
            throw new Exception("Impossible de charger la tâche $path, fichier non valide");
            
        // Ok
        return $task;
    }

    public function save()
    {
        // Détermine le répertoire où sont stockées les tâches (/data/tasks dans fabRoot)
        $dir=Runtime::$fabRoot.'data'.DIRECTORY_SEPARATOR.'tasks'.DIRECTORY_SEPARATOR;
        
        // Si la tâche a déjà un ID, écrase le fichier existant
        if ( ! is_null($this->id))
        {
            $path=$dir . $this->id . '.task';
            file_put_contents($path, serialize($this));
            return;
        }
        
        for ($i=1; $i<=1000; $i++)
        {
            // Essaie de créer le fichier
            $path=$dir . $i . '.task';
            $file=@fopen($path, 'x+'); // x+ : en écriture, échec si le fichier existe déjà, génère un warning
            
            // Si on a réussi à ouvrir le fichier, enregistre la tâche, terminé
            if ($file!==false)
            {
                $this->id=$i;
                fwrite($file, serialize($this));
                fclose($file);
                TaskManager::request('add ' . $this->id);            // Signale au démon qu'il a du boulot
                return;
            }
        }

        // Impossible d'affecter un ID à la tâche (plus de 1000 tâches ? droits insuffisants ?)
        throw new Exception("Impossible d'attribuer un ID à la tâche");
    }
    
    public function getRoot()
    {
    	return $this->root;
    }

    public function getTask()
    {
        return $this->task;
    }

    public function setTask($task)
    {
        $this->task=$task;
        // TODO : appeller Routing::setupRouteFor() pour vérifier que la fab url est valide
        // Problème : setupRouteFor modifie l'environnement. Il faudrait avoir deux fonctions distinctes : getRoute() et SetupRoute() 
    }
    
    public function getId()
    {
        return $this->id;
    }
    
    public function getTime($asString=false)
    {
        return $asString ? $this->formatTime($this->time) : $this->time;
    }

    public function setTime($time)
    {
        if ($time !=0 && $time<time())
            throw new Exception("Date d'exécution dépassée, la tâche ne sera jamais exécutée (temps actuel : ".time().", temps indiqué=". $time.")");
        $this->time=$time;
    }
    
    public function getRepeat()
    {
        return $this->repeat;
    }

    public function setRepeat($repeat)
    {
        if ($repeat===false || $repeat===0 || $repeat==='') $repeat=null;
        $this->repeat=$repeat;
    }

    private function formatTime($timestamp)
    {
        if (is_null($timestamp)) return 'jamais';
        if ($timestamp===0) return 'dès que possible';
        return date('d/m/y H:i:s', $timestamp); 
    }
    
    public function getLast($asString=false)
    {
        return $asString ? $this->formatTime($this->last) : $this->last;
    }
    
//    public function setLast($last)
//    {
//        $this->last=$last;
//    }
    
    public function getCreation($asString=false)
    {
        return $asString ? $this->formatTime($this->creation) : $this->creation;
    }

    public function getNext($asString=false)
    {
        return $asString ? $this->formatTime($this->next) : $this->next;
    }

    public function getStatus($asString=false)
    {
        if (!$asString) return $this->status;
        switch ($this->status)
        {
            case self::Pending:     return 'en attente';
            case self::Starting:    return 'démarrage';
            case self::Running:     return 'exécution';
            case self::Done:        return 'terminée';
            case self::Error:       return 'erreur';
            case self::Disabled:    return 'désactivée';
            default:                return 'statut inconnu ' . $this->status;
        }
    }
    
    public function setStatus($status)
    {
        switch ($this->status)
        {
            case self::Pending:
            case self::Starting:
            case self::Running:
            case self::Done:
            case self::Error:
            case self::Disabled:
                $this->status=$status;
                break;
            default:
                throw new Exception('Statut de tâche invalide : ' . $status);
        }
    }
    
    public function __toString()
    {
    	return sprintf
        (
            '#%d, creation=%s, last=%s, next=%s, "%s"',
            $this->getId(),
            $this->getCreation(true),
            $this->getLast(true),
            $this->getNext(true),
            $this->getTitle() ? $this->getTitle() : $this->getTask()
        );
    }
    public function getTitle()
    {
        return $this->title;
    }
    
    public function setTitle($title)
    {
    	$this->title=$title;
    }

    public function run()
    {
        // Chien de garde temporaire
        static $nn=0;
        if (++$nn>500) die('nb max de taches lancées atteint');
         
        // Modifie la date de dernière exécution et le statut de la tâche
        $this->last=time();
        $this->next=null;
        $this->setStatus(self::Starting);
        $this->save();
                             
        // Lance la tâche
        TaskManager::out('Exec '. $this);
        TaskManager::runBackgroundModule('/TaskManager/RunTask?id=' . $this->id, $this->root);
    }
    
    /**
     * Convertit un nom (jour, lundi, mars) ou une abbréviation (j., lun,
     *  mar) utilisée dans la date de programmation d'une tâche et retourne
     * le numéro correspondant.
     * 
     * Si l'argument passé est déjà sous la forme d'un numéro, retourne ce
     * numéro.
     * 
     * Génère une exception si l'abbréviation utilisée n'est pas reconnue.
     * 
     * @param mixed $value un entier ou une chaine à convertir.
     * @param string $what le type d'abbréviation recherché. Doit être une des
     * valeurs suivantes : 'units' (unités de temps telles que jours, mois...),
     * 'minutes', 'hours', 'mday' (noms de jours) ou 'mon' (noms de mois).
     */
    private function convertAbbreviation($value, $what='units')
    {
        static $convert=array
        (
            'units'=>array
            (
                's'=>'seconds', 'sec'=>'seconds', 'second'=>'seconds', 'seconde'=>'seconds',
                'mn'=>'minutes', 'min'=>'minutes', 'minute'=>'minutes',
                'h'=>'hours', 'hour'=>'hours', 'heure'=>'hours',
                'd'=>'mday', 'j'=>'mday', 'day'=>'mday', 'jour'=>'mday',
                'mon'=>'mon', 'month'=>'mon', 'monthe'=>'mon', 'moi'=>'mon', // comme le s est enlevé mois->moi
            ),
            'seconds'=>array(),
            'minutes'=>array(),
            'hours'=>array(),
            'mday'=>array   // jours = numéro wday retourné par getdate + 1000
            (
                'dimanche'=>1000, 'lundi'=>1001, 'mardi'=>1002, 'mercredi'=>1003, 'jeudi'=>1004, 'vendredi'=>1005, 'samedi'=>1006,
                'dim'=>1000, 'lun'=>1001, 'mar'=>1002, 'mer'=>1003, 'jeu'=>1004, 'ven'=>1005, 'sam'=>1006,
                'sunday'=>1000, 'monday'=>1001, 'tuesday'=>1002, 'wednesday'=>1003, 'thursday'=>1004, 'friday'=>1005, 'saturday'=>1006,
                'sun'=>1000, 'mon'=>1001, 'tue'=>1002, 'wed'=>1003, 'thu'=>1004, 'fri'=>1005, 'sat'=>1006,
            ),
            'mon'=>array
            (
                'janvier'=>1, 'février'=>2, 'fevrier'=>2, 'mars'=>3, 'avril'=>4, 'mai'=>5, 'juin'=>6,
                'juillet'=>7, 'août'=>8, 'aout'=>8, 'septembre'=>9, 'octobre'=>10, 'novembre'=>11, 'décembre'=>12, 'decembre'=>12,
        
                'january'=>1, 'february'=>2, 'march'=>3, 'april'=>4, 'may'=>5, 'june'=>6,
                'july'=>7, 'august'=>8, 'september'=>9, 'october'=>10, 'november'=>11, 'december'=>12,
        
                'jan'=>1, 'fév'=>2, 'fev'=>2, 'feb'=>2, 'mar'=>3, 'avr'=>4, 'apr'=>4, 'mai'=>5, 'may'=>5,
                'juil'=>7, 'jul'=>7, 'aug'=>7, 'sep'=>9, 'oct'=>10, 'nov'=>11, 'déc'=>12, 'dec'=>12,
            )
        );
    
        // Si la valeur est un nombre, on retourne ce nombre
        if (is_int($value) or ctype_digit($value)) return (int) $value; 
    
        // Fait la conversion
        if (!isset($convert[$what]))
            throw new Exception(__FUNCTION__ . ', argument incorrect : ' . $what);
            
        $value=rtrim(strtolower($value),'.');
        if ($value !='s') $value=rtrim($value, 's');
        
        if (!isset($convert[$what][$value]))
            switch($what)
            {
                case 'units':   throw new Exception($value . ' n\'est pas une unité de temps valide');
                case 'seconds': throw new Exception($value . ' ne correspond pas à des seconds');
                case 'minutes': throw new Exception($value . ' ne correspond pas à des minutes');
                case 'hours':   throw new Exception($value . ' ne correspond pas à des heures');
                case 'mday':    throw new Exception($value . ' n\'est pas un nom de jour valide');
                case 'mon':     throw new Exception($value . ' n\'est pas un nom de mois valide');
                default:        throw new Exception($value . ' n\'est pas une unité ' . $what . ' valide');
            } 
        return $convert[$what][$value];     
    }


    /**
     * Calcule la date/heure de prochaine exécution d'une tâche répétitive.
     * 
     * @param int $time la date/heure initiale à laquelle la tâche a été
     * planifiée. La fonction fait de son mieux pour conserver les éléments
     * indiqués (par exemple si une tâche est programmée à 12h 53min 25sec,
     * répêter toutes les heures, les minutes et les secondes seront
     * conservées). Cependant ce n'est pas toujours possible : si une tâche est
     * programmmée le 31 janvier/répéter tous les mois, la fonction ne pourra
     * pas retourner "31 février" et ajustera la date en conséquence (2 ou 3
     * mars selon que l'année est bissextile ou non).
     * 
     * En général, vous passerez dans $time la date initialement fixée pour la
     * tâche ou la date de dernière exécution de la tâche. Néanmoins, vous
     * pouvez aussi passer en paramètre une date dans le futur : dans ce cas,
     * vous obtiendrez la date d'exécution qui suit cette date (utile par
     * exemple pour obtenir les n prochaines dates d'exécution d'une tâche).
     * 
     * @param string $repeat la chaine indiquant la manière dont la tâche
     * doit être répétée. La chaine doit être sous la forme d'un entier positif
     * non nul suivi d'une unité de temps reconnue par {@link
     * convertAbbreviation} (exemples : '1 h.', '2 jours', '3 mois') et peut
     * être éventuellement suivie d'un slash ou d'une virgule et d'un filtre
     * indiquant des restrictions sur les dates autorisées.
     * 
     * Le filtre, s'il est présent, doit être constitué d'une suite d'éléments
     * exprimés dans l'unité de temps indiquée avant. Chaque élément peut être
     * un élément unique ou une période indiquée par deux éléments séparés par
     * un tiret.
     * 
     * Remarques : des espaces sont autorisés un peu partout (entre le nombre
     * et l'unité, entre les éléments des filtres, etc.)
     * 
     * Exemples:
     * 
     * - "1 mois"" : tous les mois, sans conditions.
     * 
     * - "1 h./8-12,14-18" : toutes les heures, mais seulement de 8h à 12h et de
     * 14h à 18h
     * 
     * - "2 jours/1-15,lun-mar,ven" : tous les deux jours, mais seulement si ça
     * tombe sur un jour compris entre le 1er et le 15 du mois ou alors si le
     * jour obtenu est un lundi, un mardi ou un vendredi
     * 
     * @return int la date/heure de prochaine exécution de la tâche, ou null si
     * la tâche ne doit plus jamais être exécutée (ou en cas de problème).
     */
    public function computeNext($now=null)
    {
        // Récupère l'heure actuelle
        if (is_null($now))$now=time();
//        TaskManager::out("computeNext, now=". $this->formatTime($now) . ", tâche=".$this);

        if (! is_null($this->next) && ($this->next>=$now)) return $this->next;
        

//        echo 'next(',$this->id,') : ';
        
        // Si la tâche n'est pas planifiée, prochaine exécution=jamais
        if ($this->time===null)
        {
//            echo "time=null : jamais\n";
            return $this->next=null;
        }
        // Si la tâche a déjà été exécutée et qu'elle n'est pas répétitive, prochaine exécution=jamais
        if (!is_null($this->last) && is_null($this->repeat))
        {
//            echo "déjà exécutée, non répétitive : jamais\n";
            return $this->next=null;
        }
            
        // Si la tâche est planifiée "dès que possible" et n'a pas encore été exécutée, prochaine exécution=maintenant
        if ($this->time===0 && is_null($this->last))
        {
//            echo "time=dès que possible, pas encore exécutée : maintenant\n";
            return $this->next=$now;
        }
        
        // Si la tâche est planifiée pour plus tard, prochaine exécution=date indiquée
        if ($this->time > $now)
        {
//            echo "time dans le futur : ", $this->formatTime($this->time),"\n";
            return $this->next=$this->time;
        }
        
        // Si la tâche était planifiée mais n'a pas été exécutée à l'heure souhaitée, prochaine exécution=maintenant
        if ($this->time<=$now && is_null($this->last))
        {
//            echo "time dépassé, pas encore exécutée : maintenant\n";
            return $this->next=$now;
        }
        
        // La tâche n'est pas répétitive, prochaine exécution : jamais
        if (is_null($this->repeat))
        {
//            echo "tâche non répétitive : jamais\n";
            return $this->next=null;
        }
        
        // On a un repeat qu'il faut analyser pour déterminer la prochaine date
        
        // Pour chaque unité valide, $minmax donne le minimum et le maximum autorisés
        static $minmax=array
        (
            'seconds'=>array(0,59), 'minutes'=>array(0,59), 'hours'=>array(0,23),
            'mday'=>array(1,31), 'mon'=>array(1,12), 'wday'=>array(1000,1006)
        );
    
        // Durée en secondes de chacune des périodes autorisées
        static $duration=array
        (
            'seconds'=>1, 'minutes'=>60, 'hours'=>3600, 'mday'=>86400,
        );

        // Analyse repeat pour extraire le nombre, l'unité et le filtre 
        $nb=$unit=$sep=$filterString=null;
        sscanf($this->repeat, '%d%[^/,]%[/,]%s', $nb, $unit, $sep, $filterString);
        if ($nb<=0)
            throw new Exception('nombre d\'unités invalide : ' . $this->repeat);
        
        // Convertit l'unité indiquée en unité php telle que retournée par getdate()
        $unit=self::convertAbbreviation(trim($unit), 'units');
        
        // Time est à une date dépassée mais a déjà été dépassée : trouve une date postérieure à maintenant
        $next=$this->time; // et si 0 ?
        if ($next==0) $next=$this->creation;
        
        // Essaie de déterminer l'heure de prochaine exécution (début + n fois la période indiquée)
        if ($unit!='mon')// non utilisable pour les mois car ils ont des durées variables
            $next+= ($nb * $duration[$unit]) * (floor(($now-$next)/($nb * $duration[$unit]))+1);

        // Incrémente avec la période demandée juqu'à ce qu'on trouve une date dans le futur
        $t=getdate($next);
        $k=0;
        while ($next<=$now)
        {
            $t[$unit]+=$nb;
            $next=mktime($t['hours'],$t['minutes'],$t['seconds'],$t['mon'],$t['mday'],$t['year']);
            $k++;
            TaskManager::out("boucle");
            if ($k > 100) die('INFINITE LOOP here');
        } 
                
        // Si on n'a aucun filtre, terminé
        if (is_null($filterString))
            return $this->next=$next;
        
        // Si on a un filtre, crée un tableau contenant toutes les valeurs autorisées
        $filter=array();
        $min=$max=null;
        foreach (explode(',', $filterString) as $range)
        {
            sscanf($range, '%[^-]-%[^-]', $min, $max);
                
            // Convertit min si ce n'est pas un entier
            $tag=$unit;
            $min=self::convertAbbreviation($min,$unit);
            if ($min>=1000) $tag='wday'; // nom de jour
            if ($min<$minmax[$tag][0] or $min>$minmax[$tag][1]) 
                throw new Exception('Filtre invalide, '.$min.' n\'est pas une valeur de type '.$tag.' correcte');                
    
            if (is_null($max)) 
                $max=$min;
            else
            {
                // Convertit max si ce n'est pas un entier
                $max=self::convertAbbreviation($max,$unit);
                if ($max>1000 && $tag!='wday')
                    throw new Exception('Intervalle invalide : '.$max.' n\'est pas du même type que l\'élément de début de période');
                if ($max<$minmax[$tag][0] or $max>$minmax[$tag][1]) 
                    throw new Exception('Filtre invalide, '.$max.' n\'est pas une valeur de type '.$tag.' correcte');                
            }                
    
            // Génère toutes les valeurs entre $min et $max
            $k=0;
            for ($i=$min;;)
            {
                $filter[$i]=true;
                ++$i;
                if ($i>$max) break;
                if ($i>$minmax[$tag][1]) $i=$minmax[$tag][0];
                if(++$k>60) 
                {
                    echo 'intervalle de ',$min, ' à ', $max, ', tag=', $tag, ', min=', $minmax[$tag][0], ', max=', $minmax[$tag][1], '<br />';
                    throw new Exception('Filtre invalide, vérifiez que l\'unité correspond au filtre'); 
                }
            }
        }
//        echo "Filtre des valeurs autorisées : ", var_export($filter, true), "\n";
        
        // Regarde si le filtre accepte la date obtenue, sinon incréemente la date de nb unités et recommence
        for(;;)
        {
            // Teste si la date en cours passe le filtre
            $t=getdate($next);
            switch($unit)
            {
                case 'seconds':
                case 'minutes':
                case 'hours':
                case 'mon':
                    if (isset($filter[$t[$unit]])) return $this->next=$next;
                    break;
                case 'mday':
                    if (isset($filter[$t[$unit]]) or isset($filter[$t['wday']+1000])) return $this->next=$next;
                    break;
            }
    
            // Passe à la date suivante et recommence 
            $t[$unit]+=$nb;
            $next=mktime($t['hours'],$t['minutes'],$t['seconds'],$t['mon'],$t['mday'],$t['year']);
        }
        
        // Stocke et retourne le résultat
        return $this->next=$next;
    }
}

class TaskList
{
    private $tasks;
    
    public function __construct()
    {
    	$this->refresh();
    }
    
    /**
     * Recharge la liste des tâches
     */
    public function refresh()
    {
        // Détermine le répertoire où sont stockées les tâches (/data/tasks dans fabRoot)
        $dir=Runtime::$fabRoot.'data'.DIRECTORY_SEPARATOR.'tasks'.DIRECTORY_SEPARATOR;
        echo 'Chargement des tâches à partir de ' . $dir . "\n";
        
        $this->tasks=array();
        foreach(glob($dir . '*.task', GLOB_NOSORT) as $path)
            $this->add(Task::load($path));
            
        $this->dump();
    }

    public function dump()
    {
        echo "Liste des tâches : \n";
        foreach($this->tasks as $task)
            echo $task, "\n";
        echo "\n";
    }   
     
    public function add(Task $task)
    {
        $this->tasks[$task->getId()] = $task;	
    }
    
    public function get($id)
    {
        if (isset($this->tasks[$id])) 
            return$this->tasks[$id];
        else 
            return null; 	
    }
    public function getAll()
    {
    	return $this->tasks;
    }
    /**
     * Retourne la prochaine tâche à exécuter ou null s'il n'y 
     * a aucune tâche en attente
     */
    public function getNext()
    {
        $best=null;
        $bestNext=null;
        $now=time();

//echo "Appel de getNext. Liste des tâches avant : \n";
//var_export($this->tasks);
//echo "\n";
        
        foreach($this->tasks as $key=>$task)
        {
            $next=$task->computeNext();
            //$next=$task->getNext();
            
            // on a une tâche à exécuter, peut-être est-ce la prochaine
            if (! is_null($next))
            {
                
                if 
                (
                        is_null($best)      // on n'en a pas d'autres, donc pour le moment, c'est la meilleure
                    ||
                        ($next < $bestNext)   // celle-ci est a une date plus proche que celle qu'on a
                    ||
                        (($next===$bestNext) && ($task->getLast()<$best->getLast())) // même next, dernière exécution antérieure
                )
                {
                    $best=$task;
                    $bestNext=$next;
                }
            }   
        }
//        $this->dump();
//        echo 'Prochaine tâche à exécuter : ', "\n", $best, "\n";
//echo "Liste des tâches après : \n";
//var_export($this->tasks);
//echo "\n";
//echo "Fin de getNext. Best=$best\n";
        return $best;
    	
    }
}

class TaskManager extends Module
{
    public static $id='';
    
	public function preExecute()
	{
		if (User :: hasAccess('cli'))
			$this->setLayout('none');
	}

	// ================================================================
	// LE GESTIONNAIRE DE TACHES PROPREMENT DIT
	// ================================================================
	public static function out($message, $client = null)
	{
		echo date('d/m/y H:i:s');
		if ($client)
			echo ' (', $client, ')';
		echo ' - ', $message, "\n";
		flush();
		while (ob_get_level())
			ob_end_flush();
	}

	/**
	 * Le démon du taskmanager est un process qui ne peut être exécuté
	 * que en ligne de commande et qui tourne indéfiniment.
	 * 
	 * Son rôle est d'exécuter les tâches programmées quand leur heure est
	 * venue.
	 * 
	 * C'est également un server basé sur des sockets qui réponds aux requêtes
	 * adressées par les clients.
	 */
	public function actionDaemon()
	{
		self::out('Démarrage du gestionnaire de tâches');

		// Détermine les options du gestionnaire de tâches
		$port = Config :: get('taskmanager.port');
		$startTime = date('d/m/y H:i:s');

		// Démarre le serveur
		$errno = 0; // évite warning 'var not initialized'
		$errstr = '';
		$socket = stream_socket_server('tcp://127.0.0.1:' . $port, $errno, $errstr);
		if (!$socket)
			die("Impossible de démarrer fabtasks : $errstr ($errno)\n");

		$client = '';

        // Charge la liste des tâches à exécuter
        $tasks=new TaskList();
        
		while (true)
        {
            // Récupère la prochaine tâche à exécuter
            $task=$tasks->getNext();
            
            // Calcule le temps à attendre avant l'exécution de la prochaine tâche (timeout)
            if ( is_null($task) )
            {
                $timeout = 24 * 60 * 60; // aucune tâche en attente : time out de 24h
            }
            else
            {
            	if ($task->getNext()==0) // exécuter dès que possible
                    $timeout=0;
                else
                {
                    $timeout=$task->getNext()-time();
                    if ($timeout<0) // la tâche n'a pas été exécutée à l'heure prévue
                    {
                        self::out('Tâche '.$task['id'].' : date d\'exécution dépassée ('.$task['nextexecstring'].'), exécution maintenant');
                        $timeout=0;
                    }                    
                }
            }
            
			// actuellement (php 5.1.4) si on met un timeout de plus de 4294, on a un overflow
			// voir bug http://bugs.php.net/bug.php?id=38096 
			if ($timeout > 4294) $timeout = 4294; // = 1 heure, 11 minutes et 34 secondes

			// Attend que quelqu'un se connecte ou que le timeout expire
//			self::out('en attente de connexion, timeout='.$timeout);
			if ($conn = @ stream_socket_accept($socket, $timeout, $client))
			{
				// Extrait la requête
				$message = fread($conn, 1024);
				$cmd = strtok($message, ' ');
				$param = trim(substr($message, strlen($cmd)));

				// Traite la commande
				$result = 'OK';
				switch ($cmd)
				{
					case 'running?':
						$result = 'yes';
						break;
					case 'status':
						$result = 'Démarré depuis le ' . $startTime . ' sur tcp#' . $port;
						break;
                    case 'add':
                        $tasks->add(Task::load((int)$param));    // Ajoute la tâche
                        self::out('ADD TASK : '.$param);
                        break;
                    case 'list':
                        $result=serialize($tasks);
                        break;
                    case 'settaskstatus':
                        $id=(int)strtok($param, ' ');
                        $status=(int)trim(substr($param, strlen($id)));
    
//                        self::out('taches en cours avant test ID  : '. var_export($tasks,true));
                        if (is_null($task=$tasks->get($id)))
                            $result='error, bad ID : ['.$id.']';
                        else
                        {
                            $task->setStatus($status);
                            $task->save();
                        }
                        self::out('SETTASKSTATUS : '.$id . ' ' . $status . ' : '. $result);
                        break;
					case 'quit' :
						break;
					default :
						$result = 'error : bad command';
				}

				// Envoie la réponse au client
				fputs($conn, $result);
				fclose($conn);

				// Si on a reçu une commande d'arrêt, terminé, sinon on recommence
				if ($cmd == 'quit') break;
			}

			// On est sorti en time out, exécute la tâche en attente s'il y en a une
			else
			{
                if (! is_null($task))
                    $task->run();
                else
                    self::out('aucune connexion pendant le temps indiqué, mais je tourne toujours !');
			}
		}

		// Arrête le serveur
		self::out('Arrêt du gestionnaire de tâches');
		fclose($socket);
		self::out('Le gestionnaire de tâches est arrêté');
		Runtime :: shutdown();
	}

    /**
     * Enregistre une tâche.
     * 
     * Si la tâche à enregistrer a déjà un numéro d'id, le
     * fichier de tâche existant est mis à jour. Dans le cas contraire, un ID
     * est déterminé pour la tâche et un nouveau fichier de tâche est créé.
     * 
     * @param array $task le tableau décrivant la tâche
     * @return int l'ID de la tâche
     */
    private static function storeTask($task)
    {
        // Détermine le répertoire où sont stockées les tâches (/data/tasks dans fabRoot)
        $dir=Runtime::$fabRoot.'data'.DIRECTORY_SEPARATOR.'tasks'.DIRECTORY_SEPARATOR;
        
        // Si la tâche n'a pas encore d'id, on lui en affecte un
        if ( ! isset($task['id']))
        {
            for ($i=1; ; $i++)
            {
                if ($i>=1000)
                    die('storeTask : pb attribution numéro de tache');
                    
                if (! file_exists($dir.$i.'.task'))
                {
                    $task['id']=$i;
                    break;
                }
            }
        }
        
        // Ouvre le fichier, en le créant si nécessaire
        $path=$dir . $task['id'].'.task';
        $file=fopen($path, 'w');
        if ($file===false)
            throw new Exception('Impossible d\'ouvrir le fichier de tâche ' . $path);

        // Enregistre la tâche
        fwrite
        (
            $file,
            sprintf
            (
                "<?php\nreturn %s;\n?>",
                var_export($task, true)
            )
        );        
        
        // Ferme le fichier
        fclose($file);
        
        // Retourne l'id de la tâche
        return $task['id'];
    }
    
    
    private static function listTasks($tasks)
    {
        var_export($tasks);
        return;
        foreach($tasks as $task)
        {
        	echo '- [TASK ', $task['id'], '], next=[', $task['nextexecstring'], '], last=[', @$task['lasttime'], '], ', $task['task'], "\n";
        }	
    }

    /**
     * Lance l'exécution d'une tâche
     */
    public function actionRunTask()
    {
        // Récupère l'ID de la tâche à exécuter
        if ( ! $id=Utils::get($_REQUEST['id']))
            die("L'ID de la tâche à exécuter n'a pas été indiqué\n");

        // Charge la tâche
        $task=Task::load((int)$id);
        
        $url=$task->getTask();
        $pt=strpos($url, '?');
        if ($pt!==false)
        {
            list($url, $querystring)=explode('?', $url);
            $_SERVER['QUERY_STRING']=$querystring;
            parse_str($querystring, $_GET);
            $_REQUEST=$_GET;
            Utils::repairGetPostRequest();
        }

        // Mémorise l'ID de la tâche en cours (utilisé par progress et taskOutputHandler)
        self::$id=$id;

        // Redirige la sortie vers le fichier id_tâche.output
        ob_start(array('TaskManager', 'taskOutputHandler'), 2);//, 4096, false);
        // ob_implicit_flush(true); // aucun effet en CLI. Le 2 ci-dessus est un workaround
        // cf : http://fr2.php.net/manual/en/function.ob-implicit-flush.php#60973
        
        // Indique que la tâche est en cours d'exécution
        self::request("settaskstatus $id ".Task::Running);

        // Exécute la tâche
        try
        {
            Routing::dispatch($url);
        }
        
        // Une erreur s'est produite
        catch (Exception $e)
        {
            self::request("settaskstatus $id ".Task::Error);
            ExceptionManager::handleException($e,false);
            ob_end_flush();
        	return;
        }

        // Indique que la tâche s'est exécutée correctement
        self::request("settaskstatus $id " . Task::Done);
        ob_end_flush();
    }
    
    /**
     * Gestionnaire ob_start utilisé pour capturer la sortie des tâches
     */
    public static function taskOutputHandler($buffer, $phase)
    {
        static $file=null;
        
        if ($phase & PHP_OUTPUT_HANDLER_START)
        {
            $dir=Runtime::$fabRoot.'data'.DIRECTORY_SEPARATOR.'tasks'.DIRECTORY_SEPARATOR;
            $file=fopen($dir.self::$id.'.output', 'w');
            if ($file===false) return false;
        }

        fwrite($file, $buffer);

        if ($phase & PHP_OUTPUT_HANDLER_END)
            fclose($file);

        return ;
    }
    
    
    
    /**
     * Trie le tableau de tâches passé en paramètre par date/heure de prochaine
     * exécution.
     * 
     * @param array $tasks le tableau de tâches à trier.
     */
    private static function sortTasks(&$tasks, $recalc=false)
    {
//self::out('SortTasks. Liste des tâches : '.var_export($tasks,true));
    	// Calcule la date de prochaine exécution de toutes les tâches
        $now=time();
        foreach($tasks as $key=>&$task)
        {
            if ($recalc or (! isset($task['nexttime'])))
            {
                if (isset($task['repeat']))
                    self::computeNextTime($task);
//                    $task['nexttime']=self::nextTime($task['time'], $task['repeat']);
                elseif ($task['time']==0)
                    $task['nexttime']=0;
                else
                    $task['nexttime']=($task['time']>$now) ? $task['time'] : null;
                self::storeTask($task);
            }
                            
            // TODO : juste pour le debug
            $task['timestring']=($task['time']==0) ? 'dès que possible' : date('d/m/y H:i:s', $task['time']);
            $task['nextexecstring']=
                is_null($task['nexttime'])
                ?
                'jamais'
                : 
                date('d/m/y H:i:s', ($task['nexttime']==0) ? $now: $task['nexttime']);
            
        }

        // Trie le tableau obtenu
        if (! function_exists('sortTasksCallback'))
        {
            function sortTasksCallback($a,$b)
            {
                // Cas particulier : les tâches non exécutables en dernier
                if (is_null($a['nexttime'])) return 1;
                if (is_null($b['nexttime'])) return -1;
                
                // 1er critère : par date de prochaine exécution (la plus proche en tête)
                if ($diff=($a['nexttime']-$b['nexttime'])) return $diff;
                
                // 2nd critère : par date de dernière exécution (la plus anciennement exécutée en tête)
                if ($diff=((float)(@$a['lasttime']-@$b['lasttime']))) return $diff>0 ? 1 : -1;
                
                // 3ème critère : par date de création de la tâche
                return $a['creation']-$b['creation'];
            }
        }
        
        uasort($tasks, 'sortTasksCallback');
    }
    

    /**
     * Exécute un module en tâche de fond.
     * 
     * @param string $fabUrl la fab url (/module/action?params) à exécuter
     * @param string $root la racine de site à passer à Runtime::setup() lors du
     * démarrage de la fab url
     */
    public static function runBackgroundModule($fabUrl, $root='')
    {
        // Détermine le path de l'exécutable php-cli
        if (!$cmd = Config :: get('taskmanager.php', ''))
            throw new Exception('Le path de l\'exécutable php ne figure pas dans la config');

        // Vérifie que le programme php obtenu existe et est exécutable
        if (!is_executable($cmd))
            throw new Exception("Le programme php (cli) est introuvable ou n'est pas exécutable");

        // Si le path contient des espaces, ajoute des guillemets
        if ( (strpos($cmd, ' ') !== false) and (substr($cmd,0,1) !=='"') )
            $cmd = '"' . $cmd . '"';

        // Détermine les options éventuelles à ajouter à l'exécutable
        $args = Config :: get('taskmanager.phpargs');
        if ($args)
            $cmd .= ' ' . $args;

        // Ajoute au path php la faburl à exécuter
        $cmd .=' -f '.Runtime::$fabRoot.'bin'.DIRECTORY_SEPARATOR.'fab.php -- '.$fabUrl;        
        if ($root) $cmd .= ' '.$root;
        
        debug && Debug :: log('Exec %s', $cmd);

        // Sous windows, utilise wscript.shell pour lancer le process en tâche de fond
        if ( substr(PHP_OS,0,3) == 'WIN')
        { 
            $WshShell = new COM("WScript.Shell");
            $oExec = $WshShell->Run($cmd, 0, false);
            echo "Commande lancée : ", $cmd, "\n";
        }
        // Sinon, considère qu'on est sous *nix et utilise le & final
        else
        {
        	$cmd .= ' &';
            exec($cmd);
        }
    }
    

    // ================================================================
    // ACTIONS DU MODULE GESTIONNAIRE DE TACHES
    // ================================================================

    /**
     * Affiche le statut du gestionnaire de tâches, les contrôles d'arrêt et de
     * redémarrage du démon et la liste des tâches en attente
     */
    public function actionIndex()
    {
        $data=array();
        if (self::isRunning())
        {
            $data['running']=true;
            $data['status']=self::status();
            
//            $tasks=unserialize(self::request('list'));
//            $tasks=self::loadTasks();
            $tasks=new TaskList();
            $tasks=$tasks->getAll(); // HACK : il faut que TaskList implemente Iterator
            $data['tasks']=$tasks;
        }
        else
        {
            $data['running']=false;
            $data['status']='non démarré';
            $tasks=array();
            $data['hastasks']=false;
            $data['tasks']=$tasks;
        	
        }
        
        $data['now']=date('d/m/y H:i:s');
        Template::run('list.html', $data, array('tasks'=>$tasks));
    }

    /**
     * Affiche le statut d'une tâche, le résultat de sa dernière exécution, la
     * progression de l'étape en cours
     */
    public function actionTaskStatus()
    {
        // Récupère l'ID de la tâche à exécuter
        if ( ! $id=Utils::get($_GET['id']))
            die("L'ID de la tâche à exécuter n'a pas été indiqué\n");
        
        $dir=Runtime::$fabRoot.'data'.DIRECTORY_SEPARATOR.'tasks'.DIRECTORY_SEPARATOR;

        if (file_exists($path=$dir.$id.'.progress'))
        {
            echo "Chargement de $path<br />";
            $t=require($path);
            $t['percent']=$t['max']==0 ? 0 : $t['step']*100 / $t['max']; 
        }
        else
        {
            echo "$path n'existe pas<br />";
            $t=array('percent'=>'');
        }                        
        if (file_exists($path=$dir.$id.'.output'))
        {
            echo "Chargement de $path<br />";
            $t['output']=file_get_contents($path);
        }
        else
        {
            echo "$path n'existe pas<br />";
            $t['output']='';
        }
        $t['seek']=strlen($t['output']);
        
        Template::run('taskStatus.html', $t);
    }
    public function actionTaskStatusUpdate()
    {
//    header('Content-Type: application/xhtml+xml; charset=iso-8859-1');    
    header('Content-Type: text/html; charset=iso-8859-1'); // pas de caractères accentués sinon    
        // Récupère l'ID de la tâche à exécuter
        if ( ! $id=Utils::get($_GET['id']))
            die("L'ID de la tâche à exécuter n'a pas été indiqué\n");
        
        $seek=Utils::get($_GET['seek'], 0);
        
        $dir=Runtime::$fabRoot.'data'.DIRECTORY_SEPARATOR.'tasks'.DIRECTORY_SEPARATOR;

        if (file_exists($path=$dir.$id.'.progress'))
        {
            $t=require($path);
            if (is_array($t))
                $t['percent']=$t['max']==0 ? 0 : round($t['step']*100 / $t['max'],2);
            else
                $t=array('percent'=>50);
        }
        else
            $t=array('percent'=>'');
                                
        if (file_exists($path=$dir.$id.'.output'))
        {
            $t['output']=file_get_contents($path, false, null, $seek);
            $t['seek']=$seek+strlen($t['output']);
        }
        else
        {
            $t['output']='';
            $t['seek']=0;
        }
        $t['id']=$id;
        Template::run('taskStatusUpdate.html', $t);
    }
     
    /**
     * Démarre le démon
     */
    public function actionStart()
    {
        self::start();
//        Runtime::redirect('index');
    }

    /**
     * Arrête le démon
     */
    public function actionStop()
    {
        try
        {
            self :: stop();
        }
        catch (Exception $e)
        {
            echo 'Erreur : ', $e->getMessage();
            return;
        }
        Runtime::redirect('index');
    }

    /**
     * Redémarre le démon
     */
    public function actionRestart()
    {
        self::restart();
        Runtime::redirect('index');
    }

	// ================================================================
	// API DU GESTIONNAIRE DE TACHES
	// ================================================================

	/**
	 * Indique si le gestionnaire de tâches est en cours d'exécution ou non
	 * 
	 * @return boolean
	 */
	public static function isRunning()
	{
		return self :: request('running?') == 'yes';
	}

	/**
	 * Démarre le gestionnaire de tâches.
	 * 
	 * Génère une exception en cas d'erreur (gestionnaire déjà démarré,
	 * impossible de lancer le process, etc.)
	 * 
	 * @return boolean true si le serveur a pu être démarré, faux sinon.
	 */
	public static function start()
	{
		if (self :: isRunning())
			throw new Exception('Le gestionnaire de tâches est déjà lancé');
		// à voir : pour *nix : nohup

        self::runBackgroundModule('/TaskManager/daemon');
        
        sleep(1); // on lui laisse un peu de temps pour démarrer
	}

	/**
	 * Arrête le gestionnaire de tâches.
	 * 
	 * Génère une exception en cas d'erreur (gestionnaire non démarré,
	 * impossible de lancer le process, etc.)
	 * 
	 * @return boolean true si le serveur a pu être arrêté, faux sinon.
	 */
	public static function stop()
	{
		if (!self :: isRunning())
			throw new Exception('Le gestionnaire de tâches n\'est pas lancé');

		return self :: request('quit');
	}

	/**
	 * Redémarre le gestionnaire de tâches. Equivalent à un stop suivi d'un
	 * start.
	 * 
	 * @return boolean true si le serveur a pu être redémarré, faux sinon.
	 */
	public static function restart()
	{
		if (self::isRunning())
			if (!self::stop())
				return false;
		return self::start();
	}

	/**
	 * Indique le statut du gestionnaire de tâches (non démarré, lancé
	 * depuis telle date...)
	 * 
	 * @return boolean string
	 */
	public static function status()
	{
		if (!self :: isRunning())
			return 'Le gestionnaire de tâches n\'est pas lancé';
		return self :: request('status');
	}

	/**
	 * Envoie une requête au gestionnaire de tâches et retourne la réponse
	 * obtenue
	 */
	public static function request($command, & $error = '')
	{
		$port = Config :: get('taskmanager.port');
		$timeout = Config :: get('taskmanager.timeout'); // en secondes, un float
		$timeout = 0.5;

		$errno = 0; // évite warning 'var not initialized'
		$errstr = '';

		// Crée une connexion au serveur
        $socket = @stream_socket_client('tcp://127.0.0.1:' . $port, $errno, $errstr, $timeout,STREAM_CLIENT_CONNECT);
//        $socket = stream_socket_client('tcp://127.0.0.1:' . $port);
//die('here');
		if (!is_resource($socket))
		{
			$error = "$errstr ($errno)";
			return null;
		}

		// Définit le timeout 
		$timeoutSeconds = (int) $timeout;
		$timeoutMicroseconds = ($timeout - ((int) $timeout)) * 1000000;
		stream_set_timeout($socket, $timeoutSeconds, $timeoutMicroseconds);

		// Envoie la commande
		$ret = @ fwrite($socket, $command);
		if ($ret === false or $ret === 0)
		{
			// BUG non élucidé : on a occasionnellement une erreur 10054 : connection reset by peer
			// lorsque cela se produit il n'est plus possible de faire quoique ce soit, même si
			// le serveur distant est démarré (testé et constaté uniquement lors d'un start)
			fclose($socket);
			return;
		}
		fflush($socket);

		// Lit la réponse (le timeout s'applique)
		$response = stream_get_contents($socket);

		// Ferme la connexion
		fclose($socket);

		// Retourne la réponse obtenue
		return $response;
	}

	// ================================================================
	// CREATION DE TÂCHES
	// ================================================================
	/**
     * Ajoute une tâche
     * 
     * @param string $task une fabUrl indiquant le module et l'action à exécuter
     * ainsi que tous les paramètres nécessaires. 
     * 
     * Exemple : /module/action?param1=x&param2=y
     * 
     * @param int datetime un timestamp représentant la date et l'heure à
     * laquelle la tâche doit être exécutée ou zéro pour indiquer 'dès que
     * possible'
     * 
     * @param string $repeat une chaine décrivant la manière dont la tâche doit
     * être répétée ou null si la tâche n'est à exécuter qu'une fois. Voir la
     * fonction {@link nextTime()} pour une description du format et des valeurs
     * autorisées.
     * 
     * @param string $title un titre optionnel permettant de décrire la tâche.
     * Si vous n'indiquez pas de titre, $task est utilisé à la place.
	 */
    public static function addTask($taskurl, $datetime = 0, $repeat=null, $title='')
	{
        // Crée la tâche
        $task=new Task();
        $task->setTask($taskurl);
        $task->setTime($datetime);
        $task->setRepeat($repeat);
        $task->setTitle($title);
        
        // Enregistre la tâche
        $task->save();        
        
        // Retourne l'ID de la tâche créée
        return $task->getId();
    }
    
    /**
     * Permet à une tâche en cours d'exécution de faire état de sa progression.
     * 
     * Progress fonctionne comme une suite de barres de progressions.
     * 
     * Typiquement, l'exécution va se dérouler en plusieurs étapes. A chaque
     * étape, progress va être appellée avec un libellé (type string) décrivant
     * l'étape en cours, et éventuellement un 'max' qui indique le nombre de pas
     * pour cette étape.
     * 
     * Chaque étape va ensuite se dérouler pas par pas. A chaque pas, progress
     * va être appellé avec le numéro du pas en cours (type int)), et
     * éventuellement un max (type int) ou un libellé (type string) du pas en
     * cours.
     * 
     * Exemples d'utilisation :
     * <code>
     * TaskManager::progress('1. Calcul des clés', $selection->count);
     * 
     * for ($i...) TaskManager::progress($i);
     * 
     * TaskManager::progress ('2. Tri de la base', $selection->count);
     * 
     * for ($i...) TaskManager::progress($i, "notice $ref");
     * </code>
     * 
     * Remarque : $max peut être indiqué indifféremment avec le titre ou avec le
     * pas :
     * <code>
     * TaskManager::progress('1. Calcul des clés');
     * 
     * for ($i...) TaskManager::progress($i, $selection->count);
     * </code>
     * 
     */
    public static function progress($step, $max = null)
    {
        static $curMax=0;
        
        $label=''; 

        if (is_int($step))
            $curStep=$step;
        else
        {
            echo '<h1>', $step, '</h1>', "\n";
            $curStep=0;
            $curMax=0;
        }
        if (!is_null($max))
            if (is_int($max)) $curMax=$max; else $label=$max;
        
        $dir=Runtime::$fabRoot.'data'.DIRECTORY_SEPARATOR.'tasks'.DIRECTORY_SEPARATOR;
        $file=fopen($dir.self::$id.'.progress', 'w');
        if ($file===false) return false;

        fwrite
        (
            $file,
            sprintf
            (
                "<?php\nreturn %s;\n?>",
                var_export
                (
                    array('step'=>$curStep, 'max'=>$curMax, 'label'=>$label), 
                    true
                )
            )
        );        
        fclose($file);        
    }

    
    public function actionTest()
    {
//echo '<pre>';
//$task=new Task();
//
//$task->setTitle("Essai de création d'une tâche programmée sérializée");
//$task->setTime(0);
//$task->setRepeat('4j/10-20');
//$task->setTask('/file/import');

//$task->save();
//
//echo 'Id de la tâche créée : ', $task->getId(), "\n";
//var_export($task);
//echo "\n";
//$id=$task->getId();
//
//$t=Task::load("D:/WebApache/fab/data/tasks/$id.task");
//echo 'tâche chargée', "\n";
//var_export($t);
//echo "\n";

//echo "Prochaines exécution : \n";
//$now=time();
//for ($i=1; $i<30; $i++)
//{
//    if (null===$task->computeNext($now))
//    {
//    	echo "La tâche ne s'exécutera plus\n";
//        break;
//    }
//    echo "<strong>Exécution : ", $task->getNext(true), "</strong>\n";
//    // exécution
//    $now=$task->getNext();
//    $task->setLast($now);
//}
//
//die();
//passthru('psexec');
//        TaskManager::addTask('/base/sort', time()+10, '2min');
//        TaskManager::addTask('/base/sort', mktime(1,0,0 , 7,28,2006), '2 jours/lun-ven');
//return;
//        TaskManager::addTask('/mail/to?body=nuit de ven à sam à 3:10:20 du mat', mktime(3,10,20 , 7,21,2006));
//        TaskManager::addTask('/mail/to?body=tlj lundi-vendredi à 07:01:02', mktime(7,1,2), '1 jour/lun-ven');
//        TaskManager::addTask('/mail/to?body=toutes les 30 min', time(), '30 min');
//        TaskManager::addTask('/mail/to?body=toutes les 3 heures la nuit', time(), '3 heures/20-23,0-8');
//        TaskManager::addTask('/mail/to?body=toutes les 6 heures tjrs', time(), '6 heures');
//        TaskManager::addTask('/mail/to?body=toutes les 9 heures tjrs', time(), '9 heures');
//        TaskManager::addTask('/mail/to?body=tous les jours', time(), '1 jour');
//return;
$now=time();

        TaskManager::addTask('/mail/to?body=toutes les 10 sec', $now, '10 sec');
        TaskManager::addTask('/mail/to?body=toutes les 20 sec', $now, '20 sec');
        TaskManager::addTask('/mail/to?body=toutes les 30 sec', $now, '30 sec');
        TaskManager::addTask('/mail/to?body=toutes les 40 sec', $now, '40 sec');
        TaskManager::addTask('/mail/to?body=toutes les 50 sec', $now, '50 sec');
        TaskManager::addTask('/mail/to?body=toutes les 1 min', $now, '1 min');
        TaskManager::addTask('/mail/to?body=toutes les 2 min', $now, '2 min');
        TaskManager::addTask('/mail/to?body=toutes les 4 min', $now, '4 min');
//        TaskManager::addTask('/mail/to?body=toutes les 2 minutes', mktime(16,01,02 , 3,3,2003), '2 min.');
//        TaskManager::addTask('/mail/to?body=toutes les 10 sec', mktime(16,01,02 , 4,4,2004), '10 sec');
//        TaskManager::addTask('/mail/to?body=toutes les 20 sec', mktime(16,01,02 , 3,3,2003), '20 sec');
    } 
}
?>
