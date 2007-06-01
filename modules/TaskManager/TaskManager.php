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
        cr�ation de la t�che                        ->  Pending
        d�but d'ex�cution                           ->  Running
            la t�che se termine normallement            ->  Done
            une exception survient durant l'ex�ction    ->  Error
        
        

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
	 * Le d�mon du taskmanager est un process qui ne peut �tre ex�cut�
	 * que en ligne de commande et qui tourne ind�finiment.
	 * 
	 * Son r�le est d'ex�cuter les t�ches programm�es quand leur heure est
	 * venue.
	 * 
	 * C'est �galement un server bas� sur des sockets qui r�ponds aux requ�tes
	 * adress�es par les clients.
	 */
	public function actionDaemon()
	{
    static $nn=0;
		self::out('D�marrage du gestionnaire de t�ches');

		// D�termine les options du gestionnaire de t�ches
		$port = Config :: get('taskmanager.port');
		$startTime = date('d/m/y H:i:s');

		// D�marre le serveur
		$errno = 0; // �vite warning 'var not initialized'
		$errstr = '';
		$socket = stream_socket_server('tcp://127.0.0.1:' . $port, $errno, $errstr);
		if (!$socket)
			die("Impossible de d�marrer fabtasks : $errstr ($errno)\n");

		$client = '';

        // Charge la liste de toutes les t�ches � ex�cuter
        $tasks=self::loadTasks();

        // Calcule la date de prochaine ex�cution de chacune des t�ches et trie
        self::sortTasks($tasks, true);
        self::listTasks($tasks);

		while (true)
        {
            // R�cup�re la prochaine t�che � ex�cuter
            if (count($tasks)==0) 
                $task=false; 
            else
            { 
                reset($tasks);
                $task=& $tasks[key($tasks)];
                self::out('Prochaine t�che � ex�cuter : '.$task['id']);
            }
            
            // Calcule le temps � attendre avant la prochaine ex�cution (timeout)
            if ( $task===false )
            {
                $timeout = 24 * 60 * 60; // aucune t�che en attente : time out de 24h
            }
            else
            {
            	if ($task['time']==0) // ex�cuter d�s que possible
                    $timeout=0.0;
                else
                {
                    $timeout=$task['nexttime']-time();
                    if ($timeout<0) // la t�che n'a pas �t� ex�cut�e � l'heure pr�vue
                    {
                        self::out('T�che '.$task['id'].' : date d\'ex�cution d�pass�e ('.$task['nextexecstring'].'), ex�cution maintenant');
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
				// Extrait la requ�te
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
						$result = 'D�marr� depuis le ' . $startTime;
						break;
                    case 'add':
                        $tasks[]=$tt=self::loadTask($param);    // Ajoute la t�che
                        self::out('ADD TASK : '.$param . ' : '. var_export($tt,true));
                        self::sortTasks($tasks);            // Retrie la liste pour d�terminer la prochaine
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

				// Envoie la r�ponse au client
				fputs($conn, $result);
				fclose($conn);

				// Si on a re�u une commande d'arr�t, termin�, sinon on recommence
				if ($cmd == 'quit') break;
			}

			// On est sorti en time out, ex�cute la t�che en attente s'il y en a une
			else
			{
                if ($task!==false)
                {
                    // Modifie la date de derni�re ex�cution et le statut de la t�che
                    $task['lasttime']=time();
                    if ($task['time']==0) $task['time']=$task['lasttime']; // d�s que possible = maintenant

                    $task['status']='starting'; // on ne peut pas appeller request sinon on s'appelle nous m�me
                    self::storeTask($task);
                     
                    // Lance la t�che
                	self::out('Ex�cution de la t�che '. $task['id'] . ' : ' . $task['task']. "\n");
                    if (++$nn>100) die();
                    self::runBackgroundModule('/TaskManager/RunTask?id=' . $task['id'], $task['root']);
                    
                    // S'il s'agit d'une t�che r�p�t�e, calcule la date de la prochaine ex�cution
                    if ($task['repeat'])
                    {
                        // Force sortTasks � calculer la date de prochaine ex�cution
                        unset($task['nexttime']);
                        self::sortTasks($tasks);
                        self::listTasks($tasks);
                    }

                    // Sinon, supprime la t�che de la liste
                    else
                    {
                        array_shift($tasks);
                    }
                }
                else
                    self::out('aucune connexion pendant le temps indiqu�, mais je tourne toujours !');
			}
		}

		// Arr�te le serveur
		self::out('Arr�t du gestionnaire de t�ches');
		fclose($socket);
		self::out('Le gestionnaire de t�ches est arr�t�');
		Runtime :: shutdown();
	}

    /**
     * Charge la t�che indiqu�e
     * 
     * @param string $path le path complet du fichier de t�che � charger.
     * @return array un tableau contenant les param�tres de la t�che
     */
    private static function loadTask($id, $path=null)
    {
        // D�termine le path du fichier t�che
        if (is_null($path))
        {
            $dir=Runtime::$fabRoot.'data'.DIRECTORY_SEPARATOR.'tasks'.DIRECTORY_SEPARATOR;
            $path=$dir.$id.'.task';
        }
                
        // Charge le fichier t�che
        $task=require($path);

        // V�rifie qu'on a un ID valide
        if (!isset($task['id']))
            throw new Exception("La t�che $path n'a pas d'ID");
        return $task;
    }
    
    /**
     * Charge toutes les t�ches � partir du r�pertoire /data/tasks du framework.
     * 
     * @return array un tableau contenant les t�ches charg�es
     */
    private static function loadTasks()
    {
        // D�termine le r�pertoire o� sont stock�es les t�ches (/data/tasks dans fabRoot)
        $dir=Runtime::$fabRoot.'data'.DIRECTORY_SEPARATOR.'tasks'.DIRECTORY_SEPARATOR;
        
        echo 'chargement des t�ches � partir de ' . $dir . "\n";
        $tasks=array();
        foreach(glob($dir . '*.task', GLOB_NOSORT) as $path)
        {
            // Charge le fichier t�che
            $task=self::loadTask(null, $path);
                
            // V�rifie que l'ID est unique
            $id=$task['id'];
            if (isset($tasks[$id]))
                throw new Exception("Les t�ches $path et $tasks[$id][path] ont le m�me ID");
            
            $tasks[$id]=$task;
        }
        return $tasks;
    }
    
    /**
     * Enregistre une t�che.
     * 
     * Si la t�che � enregistrer a d�j� un num�ro d'id, le
     * fichier de t�che existant est mis � jour. Dans le cas contraire, un ID
     * est d�termin� pour la t�che et un nouveau fichier de t�che est cr��.
     * 
     * @param array $task le tableau d�crivant la t�che
     * @return int l'ID de la t�che
     */
    private static function storeTask($task)
    {
        // D�termine le r�pertoire o� sont stock�es les t�ches (/data/tasks dans fabRoot)
        $dir=Runtime::$fabRoot.'data'.DIRECTORY_SEPARATOR.'tasks'.DIRECTORY_SEPARATOR;
        
        // Si la t�che n'a pas encore d'id, on lui en affecte un
        if ( ! isset($task['id']))
        {
            for ($i=1; ; $i++)
            {
                if ($i>=1000)
                    die('storeTask : pb attribution num�ro de tache');
                    
                if (! file_exists($dir.$i.'.task'))
                {
                    $task['id']=$i;
                    break;
                }
            }
        }
        
        // Ouvre le fichier, en le cr�ant si n�cessaire
        $path=$dir . $task['id'].'.task';
        $file=fopen($path, 'w');
        if ($file===false)
            throw new Exception('Impossible d\'ouvrir le fichier de t�che ' . $path);

        // Enregistre la t�che
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
        
        // Retourne l'id de la t�che
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
     * Lance l'ex�cution d'une t�che
     */
    public function actionRunTask()
    {
        // R�cup�re l'ID de la t�che � ex�cuter
        if ( ! $id=Utils::get($_REQUEST['id']))
            die("L'ID de la t�che � ex�cuter n'a pas �t� indiqu�\n");

        // Charge la t�che
        if ( !$task=self::loadTask($id))
            die("Impossible de charger la t�che $id\n");
        
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

        // M�morise l'ID de la t�che en cours (utilis� par progress et taskOutputHandler)
        self::$id=$id;

        // Redirige la sortie vers le fichier id_t�che.output
        ob_start(array('TaskManager', 'taskOutputHandler'), 2);//, 4096, false);
        // ob_implicit_flush(true); // aucun effet en CLI. Le 2 ci-dessus est un workaround
        // cf : http://fr2.php.net/manual/en/function.ob-implicit-flush.php#60973
        
        // Indique que la t�che est en cours d'ex�cution
        self::request("settaskstatus $id running");

        // Ex�cute la t�che
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

        // Indique que la t�che s'est ex�cut�e correctement
        self::request("settaskstatus $id done");
        ob_end_flush();
    }
    
    /**
     * Gestionnaire ob_start utilis� pour capturer la sortie des t�ches
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
     * Trie le tableau de t�ches pass� en param�tre par date/heure de prochaine
     * ex�cution.
     * 
     * @param array $tasks le tableau de t�ches � trier.
     */
    private static function sortTasks(&$tasks, $recalc=false)
    {
//self::out('SortTasks. Liste des t�ches : '.var_export($tasks,true));
    	// Calcule la date de prochaine ex�cution de toutes les t�ches
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
            $task['timestring']=($task['time']==0) ? 'd�s que possible' : date('d/m/y H:i:s', $task['time']);
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
                // Cas particulier : les t�ches non ex�cutables en dernier
                if (is_null($a['nexttime'])) return 1;
                if (is_null($b['nexttime'])) return -1;
                
                // 1er crit�re : par date de prochaine ex�cution (la plus proche en t�te)
                if ($diff=($a['nexttime']-$b['nexttime'])) return $diff;
                
                // 2nd crit�re : par date de derni�re ex�cution (la plus anciennement ex�cut�e en t�te)
                if ($diff=((float)(@$a['lasttime']-@$b['lasttime']))) return $diff>0 ? 1 : -1;
                
                // 3�me crit�re : par date de cr�ation de la t�che
                return $a['creation']-$b['creation'];
            }
        }
        
        uasort($tasks, 'sortTasksCallback');
    }
    

    /**
     * Convertit un nom (jour, lundi, mars) ou une abbr�viation (j., lun,
     *  mar) utilis�e dans la date de programmation d'une t�che et retourne
     * le num�ro correspondant.
     * 
     * Si l'argument pass� est d�j� sous la forme d'un num�ro, retourne ce
     * num�ro.
     * 
     * G�n�re une exception si l'abbr�viation utilis�e n'est pas reconnue.
     * 
     * @param mixed $value un entier ou une chaine � convertir.
     * @param string $what le type d'abbr�viation recherch�. Doit �tre une des
     * valeurs suivantes : 'units' (unit�s de temps telles que jours, mois...),
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
                'moi'=>'mon', // comme le s est enlev� mois->moi
            ),
            'seconds'=>array(),
            'minutes'=>array(),
            'hours'=>array(),
            'mday'=>array
            (
                'dimanche'=>1000,   // jours = num�ro wday retourn� par getdate + 1000
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
                'f�vrier'=>2,
                'fevrier'=>2,
                'mars'=>3,
                'avril'=>4,
                'mai'=>5,
                'juin'=>6,
                'juillet'=>7,
                'ao�t'=>8,
                'aout'=>8,
                'septembre'=>9,
                'octobre'=>10,
                'novembre'=>11,
                'd�cembre'=>12,
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
                'f�v'=>2,
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
                'd�c'=>12,
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
                case 'units':   throw new Exception($value . ' n\'est pas une unit� de temps valide');
                case 'seconds': throw new Exception($value . ' ne correspond pas � des seconds');
                case 'minutes': throw new Exception($value . ' ne correspond pas � des minutes');
                case 'hours':   throw new Exception($value . ' ne correspond pas � des heures');
                case 'mday':    throw new Exception($value . ' n\'est pas un nom de jour valide');
                case 'mon':     throw new Exception($value . ' n\'est pas un nom de mois valide');
                default:        throw new Exception($value . ' n\'est pas une unit� ' . $what . ' valide');
            } 
        return $convert[$what][$value];     
    }


    /**
     * Calcule la date/heure de prochaine ex�cution d'une t�che r�p�titive.
     * 
     * @param int $time la date/heure initiale � laquelle la t�che a �t�
     * planifi�e. La fonction fait de son mieux pour conserver les �l�ments
     * indiqu�s (par exemple si une t�che est programm�e � 12h 53min 25sec,
     * r�p�ter toutes les heures, les minutes et les secondes seront
     * conserv�es). Cependant ce n'est pas toujours possible : si une t�che est
     * programmm�e le 31 janvier/r�p�ter tous les mois, la fonction ne pourra
     * pas retourner "31 f�vrier" et ajustera la date en cons�quence (2 ou 3
     * mars selon que l'ann�e est bissextile ou non).
     * 
     * En g�n�ral, vous passerez dans $time la date initialement fix�e pour la
     * t�che ou la date de derni�re ex�cution de la t�che. N�anmoins, vous
     * pouvez aussi passer en param�tre une date dans le futur : dans ce cas,
     * vous obtiendrez la date d'ex�cution qui suit cette date (utile par
     * exemple pour obtenir les n prochaines dates d'ex�cution d'une t�che).
     * 
     * @param string $repeat la chaine indiquant la mani�re dont la t�che
     * doit �tre r�p�t�e. La chaine doit �tre sous la forme d'un entier positif
     * non nul suivi d'une unit� de temps reconnue par {@link
     * convertAbbreviation} (exemples : '1 h.', '2 jours', '3 mois') et peut
     * �tre �ventuellement suivie d'un slash ou d'une virgule et d'un filtre
     * indiquant des restrictions sur les dates autoris�es.
     * 
     * Le filtre, s'il est pr�sent, doit �tre constitu� d'une suite d'�l�ments
     * exprim�s dans l'unit� de temps indiqu�e avant. Chaque �l�ment peut �tre
     * un �l�ment unique ou une p�riode indiqu�e par deux �l�ments s�par�s par
     * un tiret.
     * 
     * Remarques : des espaces sont autoris�s un peu partout (entre le nombre
     * et l'unit�, entre les �l�ments des filtres, etc.)
     * 
     * Exemples:
     * 
     * - "1 mois"" : tous les mois, sans conditions.
     * 
     * - "1 h./8-12,14-18" : toutes les heures, mais seulement de 8h � 12h et de
     * 14h � 18h
     * 
     * - "2 jours/1-15,lun-mar,ven" : tous les deux jours, mais seulement si �a
     * tombe sur un jour compris entre le 1er et le 15 du mois ou alors si le
     * jour obtenu est un lundi, un mardi ou un vendredi
     * 
     * @return int la date/heure de prochaine ex�cution de la t�che, ou null si
     * la t�che ne doit plus jamais �tre ex�cut�e (ou en cas de probl�me).
     */
    private static function computeNextTime(& $task)
    {
        // Pour chaque unit� valide, $minmax donne le minimum et le maximum autoris�s
        static $minmax=array
        (
            'seconds'=>array(0,59),
            'minutes'=>array(0,59),
            'hours'=>array(0,23),
            'mday'=>array(1,31),
            'mon'=>array(1,12),
            'wday'=>array(1000,1006)
        );
    
        // Dur�e en secondes de chacune des p�riodes autoris�es
        static $duration=array
        (
            'seconds'=>1,
            'minutes'=>60,
            'hours'=>3600,
            'mday'=>86400,
        );
        
        // R�cup�re l'heure d'ex�cution initialement programm�e
        $time=$task['time'];
        $repeat=$task['repeat'];
        $now=time();

         self::out("nextTime, now=$now, t�che=".var_export($task,true));
                
        // Analyse $repeat pour extraire le nombre, l'unit� et le filtre 
        $nb=$unit=$sep=$filterString=null;
        sscanf($repeat, '%d%[^/,]%[/,]%s', $nb, $unit, $sep, $filterString);
        if ($nb<=0)
            throw new Exception('nombre d\'unit�s invalide : ' . $repeat);
        
        // Convertit l'unit� indiqu�e en unit� php telle que retourn�e par getdate()
        $unit=self::convertAbbreviation(trim($unit), 'units');
        
        // Si la t�che est programm�e "d�s que possible" et n'a pas encore �t� ex�cut�e, prochaine ex�cution=maintenant
        if (($time==0 || $time<=$now) && !isset($task['lasttime']))
        {
        	$nextTime=$now;
            self::out("time=0 ou d�pass� et t�che pas encore ex�cut�e : maintenant");
        }
        
        // Si la t�che est programm� pour plus tard, prochaine ex�cution=date indiqu�e
        elseif ($time>$now)
        {
        	$nextTime=$time;
            self::out("time est � une date dans le futur, s'ex�cutera � la date indiqu�e");
        }

        // Time est � une date d�pass�e mais a d�j� �t� d�pass�e : troouve une date post�rieure � maintenant
        else
        {
            self::out("time est � une date pass�e, recherche d'une date future");
            $nextTime=$time;
            
            // Essaie de d�terminer l'heure de prochaine ex�cution (d�but + n fois la p�riode indiqu�e)
            if ($unit!='mon')// non utilisable pour les mois car ils ont des dur�es variables
            {
                $nextTime += ($nb * $duration[$unit]) * (floor(($now-$nextTime)/($nb * $duration[$unit]))+1);
                self::out("saut rapide de $nb $unit");
            }
            
            // Incr�mente timestamp avec la p�riode demand�e juqu'� ce qu'on trouve une date dans le futur
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
                
        // Si on n'a aucun filtre, termin�
        if (is_null($filterString))
        {
            $task['nexttime']=$nextTime;
            return $nextTime;
        }
    
        // Si on a un filtre, cr�e un tableau contenant toutes les valeurs autoris�es
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
                    throw new Exception('Intervalle invalide : '.$max.' n\'est pas du m�me type que l\'�l�ment de d�but de p�riode');
                if ($max<$minmax[$tag][0] or $max>$minmax[$tag][1]) 
                    throw new Exception('Filtre invalide, '.$min.' n\'est pas une valeur de type '.$tag.' correcte');                
            }                
    
            // G�n�re toutes les valeurs entre $min et $max
            $k=0;
            for ($i=$min;;)
            {
                $filter[$i]=true;
                ++$i;
                if ($i>$max) break;
                if ($i>$minmax[$tag][1]) $i=$minmax[$tag][0];
                if(++$k>32) 
                {
                    echo 'intervalle de ',$min, ' � ', $max, ', tag=', $tag, ', min=', $minmax[$tag][0], ', max=', $minmax[$tag][1], '<br />';
                    throw new Exception('Filtre invalide, v�rifiez que l\'unit� correspond au filtre'); 
                }
            }
        }
    
        // Regarde si le filtre accepte la date obtenue, sinon incr�emente la date de nb unit�s et recommence
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
    
            // Passe � la date suivante et recommence 
            $t[$unit]+=$nb;
            $nextTime=mktime($t['hours'],$t['minutes'],$t['seconds'],$t['mon'],$t['mday'],$t['year']);
        }
        
        // Stocke et retourne le r�sultat
        $task['nexttime']=$nextTime;
        return $nextTime;
        
    }
    
    /**
     * Ex�cute un module en t�che de fond.
     * 
     * @param string $fabUrl la fab url (/module/action?params) � ex�cuter
     * @param string $root la racine de site � passer � Runtime::setup() lors du
     * d�marrage de la fab url
     */
    private static function runBackgroundModule($fabUrl, $root='')
    {
        // D�termine le path de l'ex�cutable php-cli
        if (!$cmd = Config :: get('taskmanager.php', ''))
            throw new Exception('Le path de l\'ex�cutable php ne figure pas dans la config');

        // V�rifie que le programme php obtenu existe et est ex�cutable
        if (!is_executable($cmd))
            throw new Exception("Le programme php (cli) est introuvable ou n'est pas ex�cutable");

        // Si le path contient des espaces, ajoute des guillemets
        if ( (strpos($cmd, ' ') !== false) and (substr($cmd,0,1) !=='"') )
            $cmd = '"' . $cmd . '"';

        // D�termine les options �ventuelles � ajouter � l'ex�cutable
        $args = Config :: get('taskmanager.phpargs');
        if ($args)
            $cmd .= ' ' . $args;

        // Ajoute au path php la faburl � ex�cuter
        $cmd .=' -f '.Runtime::$fabRoot.'bin'.DIRECTORY_SEPARATOR.'fab.php -- '.$fabUrl;        
        if ($root) $cmd .= ' '.$root;
        
        debug && Debug :: log('Exec %s', $cmd);
echo $cmd;
        // Sous windows, utilise wscript.shell pour lancer le process en t�che de fond
        if ( substr(PHP_OS,0,3) == 'WIN')
        { 
            $WshShell = new COM("WScript.Shell");
            $oExec = $WshShell->Run($cmd, 0, false);
        }
        // Sinon, consid�re qu'on est sous *nix et utilise le & final
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
     * Affiche le statut du gestionnaire de t�ches, les contr�les d'arr�t et de
     * red�marrage du d�mon et la liste des t�ches en attente
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
            $data['status']='non d�marr�';
            $tasks=array();
            $data['hastasks']=false;
            $data['tasks']=$tasks;
        	
        }
        
        $data['now']=date('d/m/y H:i:s');
        Template::run('list.html', $data, array('tasks'=>$tasks));
    }

    /**
     * Affiche le statut d'une t�che, le r�sultat de sa derni�re ex�cution, la
     * progression de l'�tape en cours
     */
    public function actionTaskStatus()
    {
        // R�cup�re l'ID de la t�che � ex�cuter
        if ( ! $id=Utils::get($_GET['id']))
            die("L'ID de la t�che � ex�cuter n'a pas �t� indiqu�\n");
        
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
    header('Content-Type: text/html; charset=iso-8859-1'); // pas de caract�res accentu�s sinon    
        // R�cup�re l'ID de la t�che � ex�cuter
        if ( ! $id=Utils::get($_GET['id']))
            die("L'ID de la t�che � ex�cuter n'a pas �t� indiqu�\n");
        
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
     * D�marre le d�mon
     */
    public function actionStart()
    {
        self :: start();
        //Runtime::redirect('index');
    }

    /**
     * Arr�te le d�mon
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
     * Red�marre le d�mon
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
	 * Indique si le gestionnaire de t�ches est en cours d'ex�cution ou non
	 * 
	 * @return boolean
	 */
	public static function isRunning()
	{
		return self :: request('running?') == 'yes';
	}

	/**
	 * D�marre le gestionnaire de t�ches.
	 * 
	 * G�n�re une exception en cas d'erreur (gestionnaire d�j� d�marr�,
	 * impossible de lancer le process, etc.)
	 * 
	 * @return boolean true si le serveur a pu �tre d�marr�, faux sinon.
	 */
	public static function start()
	{
		if (self :: isRunning())
			throw new Exception('Le gestionnaire de t�ches est d�j� lanc�');
		// � voir : pour *nix : nohup

        self::runBackgroundModule('/TaskManager/daemon');
        
        sleep(1); // on lui laisse un peu de temps pour d�marrer
	}

	/**
	 * Arr�te le gestionnaire de t�ches.
	 * 
	 * G�n�re une exception en cas d'erreur (gestionnaire non d�marr�,
	 * impossible de lancer le process, etc.)
	 * 
	 * @return boolean true si le serveur a pu �tre arr�t�, faux sinon.
	 */
	public static function stop()
	{
		if (!self :: isRunning())
			throw new Exception('Le gestionnaire de t�ches n\'est pas lanc�');

		return self :: request('quit');
	}

	/**
	 * Red�marre le gestionnaire de t�ches. Equivalent � un stop suivi d'un
	 * start.
	 * 
	 * @return boolean true si le serveur a pu �tre red�marr�, faux sinon.
	 */
	public static function restart()
	{
		if (self :: isRunning())
			if (!self :: stop())
				return false;
		return self :: start();
	}

	/**
	 * Indique le statut du gestionnaire de t�ches (non d�marr�, lanc�
	 * depuis telle date...)
	 * 
	 * @return boolean string
	 */
	public static function status()
	{
		if (!self :: isRunning())
			return 'Le gestionnaire de t�ches n\'est pas lanc�';
		return self :: request('status');
	}

	/**
	 * Envoie une requ�te au gestionnaire de t�ches et retourne la r�ponse
	 * obtenue
	 */
	private static function request($command, & $error = '')
	{
		$port = Config :: get('taskmanager.port');
		$timeout = Config :: get('taskmanager.timeout'); // en secondes, un float
		$timeout = 0.5;

		$errno = 0; // �vite warning 'var not initialized'
		$errstr = '';

		// Cr�e une connexion au serveur
        $socket = @stream_socket_client('tcp://127.0.0.1:' . $port, $errno, $errstr, $timeout,STREAM_CLIENT_CONNECT);
//        $socket = stream_socket_client('tcp://127.0.0.1:' . $port);
//die('here');
		if (!is_resource($socket))
		{
			$error = "$errstr ($errno)";
			return null;
		}

		// D�finit le timeout 
		$timeoutSeconds = (int) $timeout;
		$timeoutMicroseconds = ($timeout - ((int) $timeout)) * 1000000;
		stream_set_timeout($socket, $timeoutSeconds, $timeoutMicroseconds);

		// Envoie la commande
		$ret = @ fwrite($socket, $command);
		if ($ret === false or $ret === 0)
		{
			// BUG non �lucid� : on a occasionnellement une erreur 10054 : connection reset by peer
			// lorsque cela se produit il n'est plus possible de faire quoique ce soit, m�me si
			// le serveur distant est d�marr� (test� et constat� uniquement lors d'un start)
			fclose($socket);
			return;
		}
		fflush($socket);

		// Lit la r�ponse (le timeout s'applique)
		$response = stream_get_contents($socket);

		// Ferme la connexion
		fclose($socket);

		// Retourne la r�ponse obtenue
		return $response;
	}

	// ================================================================
	// CREATION DE T�CHES
	// ================================================================
	/**
     * Ajoute une t�che
     * 
     * @param string $task une fabUrl indiquant le module et l'action � ex�cuter
     * ainsi que tous les param�tres n�cessaires. 
     * 
     * Exemple : /module/action?param1=x&param2=y
     * 
     * @param int datetime un timestamp repr�sentant la date et l'heure �
     * laquelle la t�che doit �tre ex�cut�e ou z�ro pour indiquer 'd�s que
     * possible'
     * 
     * @param string $repeat une chaine d�crivant la mani�re dont la t�che doit
     * �tre r�p�t�e ou null si la t�che n'est � ex�cuter qu'une fois. Voir la
     * fonction {@link nextTime()} pour une description du format et des valeurs
     * autoris�es.
     * 
     * @param string $title un titre optionnel permettant de d�crire la t�che.
     * Si vous n'indiquez pas de titre, $task est utilis� � la place.
	 */
    public static function addTask($task, $datetime = 0, $repeat=null, $title='')
	{
        // Cr�e la t�che
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
        
        // Calcule l'heure de prochaine ex�cution pour v�rifier que $repeat est valide
        if (is_null($repeat)) 
        {
            if ($datetime!=0 && $datetime<time())
                throw new Exception("La date d'ex�cution de la t�che est d�pass�e, la t�che ne sera jamais ex�cut�e");
        }
        else
        {
            self::computeNextTime($task);	
        }   
        
        // Stocke la t�che
        $id=self::storeTask($task);
        
        // Signale au d�mon qu'il a du boulot
        self::request('add ' . $id);
        
        // Retourne l'ID de la t�che cr��e
        return $id;
    }
    
    /**
     * Permet � une t�che en cours d'ex�cution de faire �tat de sa progression.
     * 
     * Progress fonctionne comme une suite de barres de progressions.
     * 
     * Typiquement, l'ex�cution va se d�rouler en plusieurs �tapes. A chaque
     * �tape, progress va �tre appell�e avec un libell� (type string) d�crivant
     * l'�tape en cours, et �ventuellement un 'max' qui indique le nombre de pas
     * pour cette �tape.
     * 
     * Chaque �tape va ensuite se d�rouler pas par pas. A chaque pas, progress
     * va �tre appell� avec le num�ro du pas en cours (type int)), et
     * �ventuellement un max (type int) ou un libell� (type string) du pas en
     * cours.
     * 
     * Exemples d'utilisation :
     * <code>
     * TaskManager::progress('1. Calcul des cl�s', $selection->count);
     * 
     * for ($i...) TaskManager::progress($i);
     * 
     * TaskManager::progress ('2. Tri de la base', $selection->count);
     * 
     * for ($i...) TaskManager::progress($i, "notice $ref");
     * </code>
     * 
     * Remarque : $max peut �tre indiqu� indiff�remment avec le titre ou avec le
     * pas :
     * <code>
     * TaskManager::progress('1. Calcul des cl�s');
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
        TaskManager::addTask('/mail/to?body=nuit de ven � sam � 3:10:20 du mat', mktime(3,10,20 , 7,21,2006));
        TaskManager::addTask('/mail/to?body=tlj lundi-vendredi � 07:01:02', mktime(7,1,2), '1 jour/lun-ven');
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
