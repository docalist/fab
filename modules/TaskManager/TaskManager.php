<?php


/*
 * Created on 12 juil. 06
 *
 * To change the template for this generated file go to
 * Window - Preferences - PHPeclipse - PHP - Code Templates
 */

class TaskManager extends Module
{
    const Pending=1;
    const Starting=2;
    const Running=3;
    const Done=4;
    const Error=5;
    const Disabled=6;

/*
        création de la tâche                        ->  Pending
        début d'exécution                           ->  Running
            la tâche se termine normallement            ->  Done
            une exception survient durant l'exéction    ->  Error
        
        

*/
    public static $id='';
    
	public function preExecute()
	{
		if (User :: hasAccess('cli'))
			$this->setLayout('none');
	}

	// ================================================================
	// LE GESTIONNAIRE DE TACHES PROPREMENT DIT
	// ================================================================
	private static function out($message, $client = null)
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
    static $nn=0;
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

        // Charge la liste de toutes les tâches à exécuter
        $tasks=self::loadTasks();

        // Calcule la date de prochaine exécution de chacune des tâches et trie
        self::sortTasks($tasks, true);
        self::listTasks($tasks);

		while (true)
        {
            // Récupère la prochaine tâche à exécuter
            if (count($tasks)==0) 
                $task=false; 
            else
            { 
                reset($tasks);
                $task=& $tasks[key($tasks)];
                self::out('Prochaine tâche à exécuter : '.$task['id']);
            }
            
            // Calcule le temps à attendre avant la prochaine exécution (timeout)
            if ( $task===false )
            {
                $timeout = 24 * 60 * 60; // aucune tâche en attente : time out de 24h
            }
            else
            {
            	if ($task['time']==0) // exécuter dès que possible
                    $timeout=0.0;
                else
                {
                    $timeout=$task['nexttime']-time();
                    if ($timeout<0) // la tâche n'a pas été exécutée à l'heure prévue
                    {
                        self::out('Tâche '.$task['id'].' : date d\'exécution dépassée ('.$task['nextexecstring'].'), exécution maintenant');
                        $timeout=0.0;
                    }                    
                }
            }
            
			// actuellement (php 5.1.4) si on met un timeout de plus de 4294, on a un overflow
			// voir bug http://bugs.php.net/bug.php?id=38096 
			if ($timeout > 4294.0)
            {
				$timeout = 4294.0; // = 1 heure, 11 minutes et 34 secondes
            }

			// Attend que quelqu'un se connecte ou que le timeout expire
			self::out('en attente de connexion, timeout='.$timeout);
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
						$result = 'Démarré depuis le ' . $startTime;
						break;
                    case 'add':
                        $tasks[]=$tt=self::loadTask($param);    // Ajoute la tâche
                        self::out('ADD TASK : '.$param . ' : '. var_export($tt,true));
                        self::sortTasks($tasks);            // Retrie la liste pour déterminer la prochaine
                        self::listTasks($tasks);
                        break;
                    case 'list':
                        $result=serialize($tasks);
                        break;
                    case 'settaskstatus':
                        $id=strtok($param, ' ');
                        $status=trim(substr($param, strlen($id)));
                        if (! isset($tasks[$id]))
                        {
                            $result='error : bad ID';
                        }
                        else
                        {
                            $tasks[$id]['status']=$status;
                            self::storeTask($tasks[$id]);	
                        }
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
                if ($task!==false)
                {
                    // Modifie la date de dernière exécution et le statut de la tâche
                    $task['lasttime']=time();
                    if ($task['time']==0) $task['time']=$task['lasttime']; // dès que possible = maintenant

                    $task['status']='starting'; // on ne peut pas appeller request sinon on s'appelle nous même
                    self::storeTask($task);
                     
                    // Lance la tâche
                	self::out('Exécution de la tâche '. $task['id'] . ' : ' . $task['task']. "\n");
                    if (++$nn>100) die();
                    self::runBackgroundModule('/TaskManager/RunTask?id=' . $task['id'], $task['root']);
                    
                    // S'il s'agit d'une tâche répêtée, calcule la date de la prochaine exécution
                    if ($task['repeat'])
                    {
                        // Force sortTasks à calculer la date de prochaine exécution
                        unset($task['nexttime']);
                        self::sortTasks($tasks);
                        self::listTasks($tasks);
                    }

                    // Sinon, supprime la tâche de la liste
                    else
                    {
                        array_shift($tasks);
                    }
                }
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
     * Charge la tâche indiquée
     * 
     * @param string $path le path complet du fichier de tâche à charger.
     * @return array un tableau contenant les paramètres de la tâche
     */
    private static function loadTask($id, $path=null)
    {
        // Détermine le path du fichier tâche
        if (is_null($path))
        {
            $dir=Runtime::$fabRoot.'data'.DIRECTORY_SEPARATOR.'tasks'.DIRECTORY_SEPARATOR;
            $path=$dir.$id.'.task';
        }
                
        // Charge le fichier tâche
        $task=require($path);

        // Vérifie qu'on a un ID valide
        if (!isset($task['id']))
            throw new Exception("La tâche $path n'a pas d'ID");
        return $task;
    }
    
    /**
     * Charge toutes les tâches à partir du répertoire /data/tasks du framework.
     * 
     * @return array un tableau contenant les tâches chargées
     */
    private static function loadTasks()
    {
        // Détermine le répertoire où sont stockées les tâches (/data/tasks dans fabRoot)
        $dir=Runtime::$fabRoot.'data'.DIRECTORY_SEPARATOR.'tasks'.DIRECTORY_SEPARATOR;
        
        echo 'chargement des tâches à partir de ' . $dir . "\n";
        $tasks=array();
        foreach(glob($dir . '*.task', GLOB_NOSORT) as $path)
        {
            // Charge le fichier tâche
            $task=self::loadTask(null, $path);
                
            // Vérifie que l'ID est unique
            $id=$task['id'];
            if (isset($tasks[$id]))
                throw new Exception("Les tâches $path et $tasks[$id][path] ont le même ID");
            
            $tasks[$id]=$task;
        }
        return $tasks;
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
        if ( !$task=self::loadTask($id))
            die("Impossible de charger la tâche $id\n");
        
        $url=$task['task'];
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
        self::request("settaskstatus $id running");

        // Exécute la tâche
        try
        {
            Routing::dispatch($url);
        }
        
        // Une erreur s'est produite
        catch (Exception $e)
        {
            self::request("settaskstatus $id error");
            ExceptionManager::handleException($e,false);
            ob_end_flush();
        	return;
        }

        // Indique que la tâche s'est exécutée correctement
        self::request("settaskstatus $id done");
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
    private static function convertAbbreviation($value, $what='units')
    {
        static $convert=array
        (
            'units'=>array
            (
                's'=>'seconds',
                'sec'=>'seconds',
                'second'=>'seconds',
                'seconde'=>'seconds',
        
                'mn'=>'minutes',
                'min'=>'minutes',
                'minute'=>'minutes',
        
                'h'=>'hours',
                'hour'=>'hours',
                'heure'=>'hours',
        
                'd'=>'mday',
                'j'=>'mday',
                'day'=>'mday',
                'jour'=>'mday',
        
                'mon'=>'mon',
                'month'=>'mon',
                'monthe'=>'mon',
                'moi'=>'mon', // comme le s est enlevé mois->moi
            ),
            'seconds'=>array(),
            'minutes'=>array(),
            'hours'=>array(),
            'mday'=>array
            (
                'dimanche'=>1000,   // jours = numéro wday retourné par getdate + 1000
                'lundi'=>1001,
                'mardi'=>1002,
                'mercredi'=>1003,
                'jeudi'=>1004,
                'vendredi'=>1005,
                'samedi'=>1006,
                
                'dim'=>1000,
                'lun'=>1001,
                'mar'=>1002,
                'mer'=>1003,
                'jeu'=>1004,
                'ven'=>1005,
                'sam'=>1006,
                
                'sunday'=>1000,
                'monday'=>1001,
                'tuesday'=>1002,
                'wednesday'=>1003,
                'thursday'=>1004,
                'friday'=>1005,
                'saturday'=>1006,
                
                'sun'=>1000,
                'mon'=>1001,
                'tue'=>1002,
                'wed'=>1003,
                'thu'=>1004,
                'fri'=>1005,
                'sat'=>1006,
            ),
            'mon'=>array
            (
                'janvier'=>1,
                'février'=>2,
                'fevrier'=>2,
                'mars'=>3,
                'avril'=>4,
                'mai'=>5,
                'juin'=>6,
                'juillet'=>7,
                'août'=>8,
                'aout'=>8,
                'septembre'=>9,
                'octobre'=>10,
                'novembre'=>11,
                'décembre'=>12,
                'decembre'=>12,
        
                'january'=>1,
                'february'=>2,
                'march'=>3,
                'april'=>4,
                'may'=>5,
                'june'=>6,
                'july'=>7,
                'august'=>8,
                'september'=>9,
                'october'=>10,
                'november'=>11,
                'december'=>12,
        
                'jan'=>1,
                'fév'=>2,
                'fev'=>2,
                'feb'=>2,
                'mar'=>3,
                'avr'=>4,
                'apr'=>4,
                'mai'=>5,
                'may'=>5,
                'juil'=>7,
                'jul'=>7,
                'aug'=>7,
                'sep'=>9,
                'oct'=>10,
                'nov'=>11,
                'déc'=>12,
                'dec'=>12,
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
    private static function computeNextTime(& $task)
    {
        // Pour chaque unité valide, $minmax donne le minimum et le maximum autorisés
        static $minmax=array
        (
            'seconds'=>array(0,59),
            'minutes'=>array(0,59),
            'hours'=>array(0,23),
            'mday'=>array(1,31),
            'mon'=>array(1,12),
            'wday'=>array(1000,1006)
        );
    
        // Durée en secondes de chacune des périodes autorisées
        static $duration=array
        (
            'seconds'=>1,
            'minutes'=>60,
            'hours'=>3600,
            'mday'=>86400,
        );
        
        // Récupère l'heure d'exécution initialement programmée
        $time=$task['time'];
        $repeat=$task['repeat'];
        $now=time();

         self::out("nextTime, now=$now, tâche=".var_export($task,true));
                
        // Analyse $repeat pour extraire le nombre, l'unité et le filtre 
        $nb=$unit=$sep=$filterString=null;
        sscanf($repeat, '%d%[^/,]%[/,]%s', $nb, $unit, $sep, $filterString);
        if ($nb<=0)
            throw new Exception('nombre d\'unités invalide : ' . $repeat);
        
        // Convertit l'unité indiquée en unité php telle que retournée par getdate()
        $unit=self::convertAbbreviation(trim($unit), 'units');
        
        // Si la tâche est programmée "dès que possible" et n'a pas encore été exécutée, prochaine exécution=maintenant
        if (($time==0 || $time<=$now) && !isset($task['lasttime']))
        {
        	$nextTime=$now;
            self::out("time=0 ou dépassé et tâche pas encore exécutée : maintenant");
        }
        
        // Si la tâche est programmé pour plus tard, prochaine exécution=date indiquée
        elseif ($time>$now)
        {
        	$nextTime=$time;
            self::out("time est à une date dans le futur, s'exécutera à la date indiquée");
        }

        // Time est à une date dépassée mais a déjà été dépassée : troouve une date postérieure à maintenant
        else
        {
            self::out("time est à une date passée, recherche d'une date future");
            $nextTime=$time;
            
            // Essaie de déterminer l'heure de prochaine exécution (début + n fois la période indiquée)
            if ($unit!='mon')// non utilisable pour les mois car ils ont des durées variables
            {
                $nextTime += ($nb * $duration[$unit]) * (floor(($now-$nextTime)/($nb * $duration[$unit]))+1);
                self::out("saut rapide de $nb $unit");
            }
            
            // Incrémente timestamp avec la période demandée juqu'à ce qu'on trouve une date dans le futur
            $t=getdate($nextTime);
            $k=0;
            while ($nextTime<=$now)
            {
                $t[$unit]+=$nb;
                $nextTime=mktime($t['hours'],$t['minutes'],$t['seconds'],$t['mon'],$t['mday'],$t['year']);
                $k++;
                self::out("boucle");
                if ($k > 100) die('INFINITE LOOP here');
            } 
        }
                
        // Si on n'a aucun filtre, terminé
        if (is_null($filterString))
        {
            $task['nexttime']=$nextTime;
            return $nextTime;
        }
    
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
                    throw new Exception('Filtre invalide, '.$min.' n\'est pas une valeur de type '.$tag.' correcte');                
            }                
    
            // Génère toutes les valeurs entre $min et $max
            $k=0;
            for ($i=$min;;)
            {
                $filter[$i]=true;
                ++$i;
                if ($i>$max) break;
                if ($i>$minmax[$tag][1]) $i=$minmax[$tag][0];
                if(++$k>32) 
                {
                    echo 'intervalle de ',$min, ' à ', $max, ', tag=', $tag, ', min=', $minmax[$tag][0], ', max=', $minmax[$tag][1], '<br />';
                    throw new Exception('Filtre invalide, vérifiez que l\'unité correspond au filtre'); 
                }
            }
        }
    
        // Regarde si le filtre accepte la date obtenue, sinon incréemente la date de nb unités et recommence
        for(;;)
        {
            // Teste si la date en cours passe le filtre
            $t=getdate($nextTime);
            switch($unit)
            {
                case 'seconds':
                case 'minutes':
                case 'hours':
                case 'mon':
                    if (isset($filter[$t[$unit]])) return $nextTime;
                    break;
                case 'mday':
                    if (isset($filter[$t[$unit]]) or isset($filter[$t['wday']+1000])) return $nextTime;
                    break;
            }
    
            // Passe à la date suivante et recommence 
            $t[$unit]+=$nb;
            $nextTime=mktime($t['hours'],$t['minutes'],$t['seconds'],$t['mon'],$t['mday'],$t['year']);
        }
        
        // Stocke et retourne le résultat
        $task['nexttime']=$nextTime;
        return $nextTime;
        
    }
    
    /**
     * Exécute un module en tâche de fond.
     * 
     * @param string $fabUrl la fab url (/module/action?params) à exécuter
     * @param string $root la racine de site à passer à Runtime::setup() lors du
     * démarrage de la fab url
     */
    private static function runBackgroundModule($fabUrl, $root='')
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
echo $cmd;
        // Sous windows, utilise wscript.shell pour lancer le process en tâche de fond
        if ( substr(PHP_OS,0,3) == 'WIN')
        { 
            $WshShell = new COM("WScript.Shell");
            $oExec = $WshShell->Run($cmd, 0, false);
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
        global $tasks;

        $data=array();
        if (self::isRunning())
        {
            $data['running']=true;
            $data['status']=self::status();
            
//            $tasks=unserialize(self::request('list'));
            $tasks=self::loadTasks();

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
        self :: start();
        //Runtime::redirect('index');
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
        self :: restart();
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
		if (self :: isRunning())
			if (!self :: stop())
				return false;
		return self :: start();
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
	private static function request($command, & $error = '')
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
    public static function addTask($task, $datetime = 0, $repeat=null, $title='')
	{
        // Crée la tâche
        $task=array
        (
            'root'=>Runtime::$root,
            'task'=>$task,
            'time'=>$datetime,
            'repeat'=>$repeat,
            'status'=>'pending',
            'creation'=>time(),
            'title'=>$title?$title:$task
        );
        
        // Calcule l'heure de prochaine exécution pour vérifier que $repeat est valide
        if (is_null($repeat)) 
        {
            if ($datetime!=0 && $datetime<time())
                throw new Exception("La date d'exécution de la tâche est dépassée, la tâche ne sera jamais exécutée");
        }
        else
        {
            self::computeNextTime($task);	
        }   
        
        // Stocke la tâche
        $id=self::storeTask($task);
        
        // Signale au démon qu'il a du boulot
        self::request('add ' . $id);
        
        // Retourne l'ID de la tâche créée
        return $id;
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
//passthru('psexec');
//        TaskManager::addTask('/base/sort', time()+10, '2min');
        TaskManager::addTask('/base/sort', mktime(1,0,0 , 7,28,2006), '2 jours/lun-ven');
return;
        TaskManager::addTask('/mail/to?body=nuit de ven à sam à 3:10:20 du mat', mktime(3,10,20 , 7,21,2006));
        TaskManager::addTask('/mail/to?body=tlj lundi-vendredi à 07:01:02', mktime(7,1,2), '1 jour/lun-ven');
        TaskManager::addTask('/mail/to?body=toutes les 30 min', time(), '30 min');
        TaskManager::addTask('/mail/to?body=toutes les 3 heures la nuit', time(), '3 heures/20-23,0-8');
        TaskManager::addTask('/mail/to?body=toutes les 6 heures tjrs', time(), '6 heures');
        TaskManager::addTask('/mail/to?body=toutes les 9 heures tjrs', time(), '9 heures');
        TaskManager::addTask('/mail/to?body=tous les jours', time(), '1 jour');
return;
$now=time();

//        TaskManager::addTask('/mail/to?body=toutes les 10 sec', $now, '10 sec');
//        TaskManager::addTask('/mail/to?body=toutes les 20 sec', $now, '20 sec');
//        TaskManager::addTask('/mail/to?body=toutes les 30 sec', $now, '30 sec');
//        TaskManager::addTask('/mail/to?body=toutes les 40 sec', $now, '40 sec');
//        TaskManager::addTask('/mail/to?body=toutes les 50 sec', $now, '50 sec');
        TaskManager::addTask('/mail/to?body=toutes les 1 min', $now, '1 min');
        TaskManager::addTask('/mail/to?body=toutes les 2 min', $now, '2 min');
        TaskManager::addTask('/mail/to?body=toutes les 4 min', $now, '4 min');
//        TaskManager::addTask('/mail/to?body=toutes les 2 minutes', mktime(16,01,02 , 3,3,2003), '2 min.');
//        TaskManager::addTask('/mail/to?body=toutes les 10 sec', mktime(16,01,02 , 4,4,2004), '10 sec');
//        TaskManager::addTask('/mail/to?body=toutes les 20 sec', mktime(16,01,02 , 3,3,2003), '20 sec');
    } 
}
?>
