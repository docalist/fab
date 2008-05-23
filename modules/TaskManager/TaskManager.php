<?php
/**
 * @package     fab
 * @subpackage  TaskManager
 * @author      Daniel Ménard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id$
 */

/**
 * Le gestionnaire de tâches de fab.
 * 
 * Normalement, une requête exécutée sur le serveur web ne dispose que de 
 * ressources (très) limitées pour s'exécuter (par exemple : temps d'exécution 
 * maximal de 30 secondes, mémoire maximale autorisée de 8 Mo, etc.)
 * 
 * Un serveur http comme {@link http://httpd.apache.org Apache} mettra fin au 
 * processus correspondant à la requête dès qu'il détectera que les limites ont
 * été atteintes. Il est également susceptible d'arrêter le processus s'il 
 * détecte que le navigateur qui en est à l'origine a cessé d'attendre la 
 * réponse (il a changé de page par exemple).
 * 
 * Dans ces conditions, il est impossible d'exécuter des opérations "lourdes"
 * telles que import/export de fichiers de notices, sauvegarde de la base, 
 * réindexation complète, modifications et suppressions en série, etc.
 * 
 * Non seulement, l'opération ne pourra pas s'exécuter jusqu'au bout, mais en 
 * plus le système sera très probablement laissé dans un état instable si le
 * processus est stoppé en plein milieu d'exécution (certaines notices ont été
 * importées mais pas toutes, la sauvegarde ne contient qu'une partie de la base 
 * et est donc inutilisable, les modifications en série n'ont été apportées qu'à
 * certaines notices sans qu'on puisse déteminer facilement lesquelles, etc.)
 * 
 * Habituellement, la réponse à ce problème consiste à augmenter les limites
 * autorisées à l'aide de fonction php telles que 
 * {@link http://php.net/set_time_limit set_time_limit}, 
 * {@link http://php.net/ignore_user_abort ignore_user_abort()} ou
 * {@link http://php.net/apache_reset_timeout apache_reset_timeout} mais la 
 * solution obtenue est en général peu satisfaisante (ces fonctions sont parfois
 * désactivées, les scripts exécutés doivent être modifiés de façon ad hoc, cela
 * peut représenter un risque de sécurité, etc.)
 * 
 * Un autre problème courant est lié au fait que certaines ressources 
 * nécessaires à la bonne exécution du script peuvent, temporairement, ne pas 
 * être disponibles.
 * 
 * On peut imaginer par exemple un script qui envoie par e-mail à l'utilisateur 
 * ses codes d'accès une fois que celui-ci s'est inscrit sur le site. Si au
 * moment d'envoyer l'e-mail le serveur de messagerie n'est pas disponible, 
 * l'e-mail ne sera jamais envoyé et l'utilisateur ne recevra jamais ses codes
 * d'accès.
 * 
 * Le TaskManager de fab est une réponse à ces problèmes : il fournit un 
 * environnement d'exécution spécifique (indépendant du serveur http utilisé)
 * dans lequel des tâches telles que celles décrites ci-dessus vont pouvoir être 
 * exécutées.
 * 
 * Dans la pratique, au lieu d'exécuter un traitement long et/ou important 
 * dans le script qui traite la requête adressée par le navigateur, on va créer
 * une tâche qui sera ensuite exécutée par le gestionnaire de tâches.
 * 
 * Le gestionnaire de tâches offre également d'autres services :
 * - possibilité de programmer une tâche pour qu'elle s'exécute dès que possible
 *   ou à une date ultérieure.
 * - possibilité de programmer une tâche récurrente qui s'exécutera 
 *   périodiquement à une heure et selon un intervalle définis.
 * - l'administrateur du site dispose d'une interface web lui permettant de 
 *   consulter l'historique des tâches exécutées, la liste des tâches qui n'ont
 *   pas pu être lancées. L'interface lui permet également d'annuler des tâches
 *   en attente ou de relancer une tâche qui ne s'est pas exécutée correctement.
 * - La sortie générée par l'exécution d'une tâche est conservée, ce qui permet
 *   à l'administrateur de contrôler la bonne exécution de celle-ci. De manière
 *   générale, l'ensemble des tâches exécutées constitue un historique complet
 *   de toutes les tâches d'administration liées au site.
 * 
 * Techniquement, la classe TaskManager a plusieurs facettes :
 * - Un {@link actionDaemon() démon} (sous Windows on parlerait d'un service) 
 *   qui tourne en permanence et se charge d'exécuter les tâches au bon moment.
 * - Un serveur de sockets qui écoute sur un port TCP et permet la communication
 *   inter-process entre le gestionnaire de tâches et les scripts de 
 *   l'application
 * - Une API utilisable dans les applications pour manipuler les tâches et le 
 *   démon : {@link isRunning()}, {@link status()}, {@link start()}, 
 *   {@link stop()}, {@link daemonUpdate()}. 
 * - Un module standard de fab qui hérite de DatabaseModule : l'ensemble des 
 *   tâches est géré sous forme de base de données. Toutes les actions classiques 
 *   de DatabaseModule (search, show, load, delete...) sont disponibles.
 * - Des actions spécifiques telles que {@link actionStart()},
 *   {@link actionStop()}, {@link actionRestart()}, {@link actionTaskStatus()}
 *   permettant de contrôler l'exécution du TaskManager et des tâches.
 *  
 * @package     fab
 * @subpackage  TaskManager
 */
class TaskManager extends DatabaseModule
{
    /**
     * Identifiant de la tâche en cours d'exécution.
     * 
     * N'est définit que lorsqu'on {@link actionRunTask()} est appellée.
     * Permet à {@link progress()} et au {@link taskOutputHandler() 
     * captureHandler} de savoir à quelle tâche ils ont affaire. 
     *
     * @var int
     */
    private static $id=null;
    
    /**
     * Path du fichier utilisé pour capturer la sortie générée lors 
     * de l'exécution d'une tâche.
     *
     * N'existe que lorsqu'on est passé dans {@link actionRunTask()}.
     * Permet au captureHandler de savoir où écrire les données.
     * 
     * @var string
     */
    private static $outputFile=null;
    
    
    /**
     * Retourne le path complet de la base de données utilisée par le 
     * gestionnaire de tâches.
     *
     * Raison : a base utilisée doit être unique pour un serveur donnée et donc
     * n'est pas stockée dans le répertoire data/db d'une application mais
     * dans le répertoire data/db de fab. Du coup, il ne faut pas qu'on passe
     * par le système d'alias (db.config) habituel.
     * 
     * Remarque : le path obtenu contient toujours un slash ou un antislash
     * final.
     * 
     * @return string
     */
    public static function getDatabasePath()
    {
        return Runtime::$fabRoot
            . 'data'    . DIRECTORY_SEPARATOR
            . 'db'      . DIRECTORY_SEPARATOR
            . 'tasks'   . DIRECTORY_SEPARATOR;
    }

    /**
     * Retourne le prath complet du répertoire dans lequel sont stockés les 
     * fichiers de sortie générés par les tâches.
     *
     * Remarque : le path obtenu contient toujours un slash ou un antislash
     * final.
     * 
     * @return string
     */
    public static function getOutputDirectory()
    {
        return self::getDatabasePath()
            . DIRECTORY_SEPARATOR
            . 'files' . DIRECTORY_SEPARATOR;
    }
    /**
     * Méthode appellée avant l'exécution d'une action du TaskManager.
     * 
     * On fait deux choses :
     * - on définit la base de données utilisée (la base 'Tasks') en modifiant
     *   dynamiquement la configuration
     * - pour l'action {@link actionSearch()}, on définit les filtres à 
     *   appliquer aux équations de recherche en fonction du paramètre 'done'
     *   éventuellement passé en paramètre.
     */
    public function preExecute()
    {
        // Indique à toutes les actions de ce module où et comment ouvrir la base tasks
        $database=self::getDatabasePath();
        Config::set('database', $database);
        Config::set("db.$database.type", 'xapian2');
        
        // Si la base tasks n'existe pas encore, on essaie de la créer de façon transparente
        if (!file_exists($database))
        {
            $path=Runtime::$fabRoot
                . 'data'              . DIRECTORY_SEPARATOR
                . 'DatabaseTemplates' . DIRECTORY_SEPARATOR
                . 'tasks.xml';

            try
            {
                if (! file_exists($path))
                    throw new Exception("Structure tasks.xml non trouvée");
    
                $dbs=new DatabaseStructure(file_get_contents($path));
                Database::create($database, $dbs, 'xapian2');
                if (!@mkdir($path=self::getOutputDirectory(), 0777))
                    throw new Exception("Impossible de créer le répertoire $path");
            }
            catch (Exception $e)
            {
                throw new Exception("Erreur de configuration : la base tasks n'existe pas et il n'est pas possible de la créer maintenant (".$e->getMessage().")");            
            }
        }
        
        // Pour l'action search ajoute un filtre si l'option "masquer l'historique" est active
        if ($this->method==='actionSearch')
        {
            if (!$this->request->bool('done')->defaults(false)->ok())
                $this->request->add('_filter', 'last='.strftime('%Y%m%d*').' OR (not status:done)');
        }
    }
    
	// ================================================================
	// LE GESTIONNAIRE DE TACHES PROPREMENT DIT
	// ================================================================
	
    /**
     * Fonction de débogage permettant de suivre l'exécution du démon lorsque
     * celui-ci est lancé directement en ligne de commande.
     *
     * @param string $message
     * @param string $client
     */
	private static function out($message, $client = null)
	{
		echo date('d/m/y H:i:s');
		if ($client)
			echo ' (', $client, ')';
		echo ' - ', Utils::convertString($message,'CP1252 to CP850'), "\n";
		flush();
		while (ob_get_level())
			ob_end_flush();
	}

    /**
     * Retourne la prochaine tâche à exécuter
     * 
     * La fonction lance une recherche dans la base en triant les réponses
     * par date de prochaine exécution.
     * 
     * Remarque : utilisée uniquement par {@link actionDaemon()}.
     * 
     * @return null|Task retourne null s'il n'y a aucune tâche en attente et
     * un objet Task correspondant à la première réponse obtenue sinon. 
     */
	private function getNextTask()
	{
        $this->openDatabase(true);
        $equation=sprintf('Status:%s', Task::Waiting);

        if ($this->select($equation, 1, 0, 'next+')) 
            $task=new Task($this->selection);
        else
            $task=null;
        
        unset($this->selection);
        self::out("Recherche de la prochaine tâche à exécuter. Equation=$equation, Tâche=".(is_null($task) ? 'null' : $task->getId(false)));
        return $task;
	}
	 
	/**
	 * Le démon du taskmanager est un process destiné à être exécuté en ligne 
	 * de commande et qui tourne indéfiniment.
	 * 
	 * Son rôle est d'exécuter les tâches programmées quand leur heure est
	 * venue.
	 * 
	 * C'est également un server basé sur des sockets TCP qui réponds aux 
	 * requêtes adressées par les clients.
	 */
	public function actionDaemon()
	{
		// Tableau stockant l'état de progression des tâches
	    $progress=array();
		
	    echo "\n\n\n";
	    self::out('Démarrage du gestionnaire de tâches');

		// Détermine les options du gestionnaire de tâches
		$port = Config :: get('taskmanager.port');
		$startTime = date('d/m/y H:i:s');

		// Démarre le serveur
		$errno = 0; // évite warning 'var not initialized'
		$errstr = '';
		$socket = stream_socket_server('tcp://127.0.0.1:' . $port, $errno, $errstr);
		if (!$socket)
			die("Impossible de démarrer le gestionnaire de tâches : $errstr ($errno)\n");

		$client = '';

        // Charge la liste des tâches à exécuter
        //$tasks=new TaskList();
        
        // todo : repérer les tâches qui n'ont pas été exécutées à l'heure prévue
        $task=$this->getNextTask();
		while (true)
        {
            // début calcul du timeout
            
            // Calcule le temps à attendre avant l'exécution de la prochaine tâche (timeout)
            if ( is_null($task) )
            {
                $timeout = 24 * 60 * 60; // aucune tâche en attente : time out de 24h
                $expired=false;
            }
            else
            {
                $next=$task->getNext();
                
                if ($next===0)
                    $diff=time() - $task->getCreation();
                else
                    $diff=time() - $next;
                
                $expired=($diff>60); // on tolère une minute de marge
                    
                // Si l'heure d'exécution prévue est dépassée, passe la tâche en statut 'Expired'
                if ($expired)
                {
                    // ie : soit next est dépassé, soit la tâche était 
                    // programmée "dès que possible", mais il s'est passé 
                    // beaucoup de temps depuis sa création
                    if ($next===0)
                        $h='exécution=asap, création='.strftime('%H:%M:%S',$task->getCreation()). ', now='.strftime('%H:%M:%S'). ', diff='.$diff.', > 60';
                    else
                        $h='exécution prévue à '.strftime('%H:%M:%S',$next).', now='.strftime('%H:%M:%S').', diff='.$diff.', > 60';
                    self::out('Tâche '.$task->getId(false).' : date d\'exécution dépassée ('.$h.')');
                    
                    $task->setStatus(Task::Expired)->save();
                    $task=null;
                    $timeout=0.1; // on va regarder s'il y a une requête puis on étudiera la tâche suivante    
                }
                else
                {
                    if ($next==0) // exécuter dès que possible
                    {
                        $timeout=0.1;
                    }
                    else
                    {
                        $timeout=(float)$task->getNext()-time();
                        if ($timeout<0)
                        {
                            self::out('Erreur interne : timeout négatif');
                            $timeout=0;
                        }                    
                    }
                }
            }
            
            // fin calcul du timeout
            
			// actuellement (php 5.1.4) si on met un timeout de plus de 4294, on a un overflow
			// voir bug http://bugs.php.net/bug.php?id=38096 
			if ($timeout > 4294) $timeout = 4294; // = 1 heure, 11 minutes et 34 secondes

			// Attend que quelqu'un se connecte ou que le timeout expire
			self::out('en attente de connexion, timeout='.$timeout.(is_null($task) ? ', aucune tâche en attente' : (', prochaine tâche : '.$task->getId(false))));
			if ($conn = @ stream_socket_accept($socket, $timeout, $client))
			{
				// Extrait la requête
				$message = fread($conn, 1024);
				$cmd = strtok($message, ' ');
				$param = trim(substr($message, strlen($cmd)));

                self::out('Request : '.$cmd);
                				
				// Traite la commande
				$result = 'OK';
				switch ($cmd)
				{
					case 'running?':
						$result = 'yes';
						break;
					case 'status':
//						$result = 'Démarré depuis le ' . $startTime . ' sur tcp#' . $port;
						$result=sprintf
						(
                            'Démarré depuis le %s (PID : %d, port : tcp %d, serveur : %s, mem used : %s)', 
                            $startTime, getmypid(),$port, php_uname('n'), memory_get_usage().'/'.memory_get_usage(true)
                        );
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
                    case 'update':
                        $task=$this->getNextTask();
                        break;
                        
                    case 'echo':
                        self::out($param);
                        break;
                        
                    case 'setprogress':
                        
                        sscanf($param, '%d %d %d', $id, $step, $max);
                        
                        // setprogress id : supprime l'entrée progress[id]    
                        if ($step===0 && $max===0) 
                        {
                            unset($progress[$id]);
                        }
                            
                        // setprogress id step : modifie step, garde le max existant
                        elseif($max===0)
                        {
                            if (isset($progress[$id]))
                                $progress[$id][0]=$step;
                            else
                                $progress[$id]=array($step,999999); // erreur max jamais indiqué
                        }
                        
                        // stocke tout
                        else
                        {
                            $progress[$id]=array($step,$max);
                        }
                        break;
                        
                    case 'getprogress':
                        $id=(int)$param;
                        if (isset($progress[$id]))
                            $result=$progress[$id][0] . ' ' . $progress[$id][1];
                        else
                            $result='';
                        break;
                        
                    case 'quit':
						break;
						
					default :
						$result = 'error : bad command';
				}

				// Envoie la réponse au client
				fputs($conn, $result);
				fclose($conn);

				// Si on a reçu une commande d'arrêt, terminé, sinon on recommence
				if ($cmd == 'quit') break;
				
				if ($expired) $task=$this->getNextTask();
			}

			// On est sorti en time out, exécute la tâche en attente s'il y en a une
			else
			{
                self::out('Dans le else');
			    
			    if (! is_null($task))
                {
                    self::out('Dans le if');
                    // Modifie la date de dernière exécution et le statut de la tâche
                    $task->setLast(time());
                    $task->setStatus(Task::Starting);
                    $task->save();
                                         
                    // Lance la tâche
                    self::out('Lancement de la tâche '. $task->getId(false));
                    self::runBackgroundModule('/TaskManager/RunTask?id=' . $task->getId(false), $task->getApplicationRoot(), $task->getUrl());

                    $task=null; // indique que la tâche a été exécutée, il faut chercher la suivante
                }
                self::out('appel de getNextTask()');
                $task=$this->getNextTask();
			}
		}

		// Arrête le serveur
		self::out('Arrêt du gestionnaire de tâches');
		fclose($socket);
		self::out('Le gestionnaire de tâches est arrêté');
		Runtime :: shutdown();
	}

    /**
     * Lance l'exécution d'une tâche
     * 
     * L'action RunTask est appellée par le {@link actionDaemon() démon} pour 
     * lancer une tâche. Elle ne doit être appellée qu'en ligne de commande.
     * 
     * La fonction commence par charger la tâche dont l'id a été indiqué en
     * paramètre.
     * 
     * Elle installe ensuite un {@link taskOutputHandler() gestionnaire 
     * ob_start()} qui va se charger d'écrire dans un fichier tout ce que la
     * tâche à exécuter écrira sur la sortie standard.
     * 
     * La tâche est alors passée en statut {@link Task::Running} et le nom
     * du fichier de sortie généré est stocké dans l'attribut 
     * {@link Task::getOutput() OutputFile} de la tâche.
     * 
     * Enfin elle lance l'exécution de la tâche en demandant au module Routing
     * {@link Routing::run() d'exécuter} la requête associée à la tâche à 
     * exécuter.
     * 
     * A la fin de l'exécution, la tâche est automatiquement passée en statut
     * {@link Task::Done}. Si une erreur (i.e. une exception) survient durant 
     * l'exécution de la tâche, celle-ci est capturée et la tâche est alors 
     * passée en statut {@link Task::Error}.
     * 
     * Remarques :
     * - Le fichier de sortie est stocké dans le sous-répertoire 'files' de la
     *   base de données.
     * - Pour une tâche récurrente, le fichier de sortie généré sera écrasé à 
     *   chaque nouvelle exécution de la tâche. Il serait assez simple d'adapter
     *   le code pour conserver le résultat des n dernières exécutions.
     * - Le gestionnaire ob_start est paramétré de telle façon que la sortie
     *   générée par la tâche soit "flushée" dès que possible.
     * 
     * @param int $id l'identifiant unique de la tâche à exécuter.  
     */
    public function actionRunTask($id)
    {
        // Récupère l'ID de la tâche à exécuter
        $id=$this->request->int('id')->required()->min(1)->ok();
        
        // Charge la tâche indiquée
        $task=new Task($id);
        
        // Détermine le path du fichier de sortie de la tâche
        $path=self::getOutputDirectory().$task->getId(false) . '.html';

        // On se charge nous même d'ouvrir (et de fermer, cf plus bas) le fichier
        // Car si on laisse ouputHandler le faire, on n'a aucun moyen de récupérer les pb éventuels (fichier non trouvé, etc.)
        if (false === self::$outputFile=@fopen($path, 'w')) 
        {
            $task->setStatus(Task::Error)->setLabel($task->getLabel()." -- Erreur : impossible d'ouvrir le fichier $path en écriture")->save();
            // todo: on n'a aucun champ dans la base pour stocker l'erreur, pour le moment on met dans le label
            // en même temps, ce type d'erreur ne se produira pas si tout est bien configuré
            return;
        }
        
        // Mémorise l'ID et le OutputFile de la tâche (utilisé par progress et taskOutputHandler)
        self::$id=$task->getId(false);

        // A partir de maintenant, redirige tous les echo vers le fichier OutputFile
        ob_start(array('TaskManager', 'taskOutputHandler'), 2);//, 4096, false);
        // ob_implicit_flush(true); // aucun effet en CLI. Le 2 ci-dessus est un workaround
        // cf : http://fr2.php.net/manual/en/function.ob-implicit-flush.php#60973
        
        // Indique que l'exécution a démarré
        $start=time();
        $task->setStatus(Task::Running)->setLast($start)->setOutput(self::$outputFile)->save();
        TaskManager::request('echo Début de la tâche #'.self::$id);
        
        echo sprintf
        (
            '<div class="taskinfo">Tâche #%s : %s<br />Date d\'exécution : %s<br />Requête exécutée : %s<br /></div>',
            self::$id, $task->getLabel(), strftime('%x %X'), $this->request
        );        
        
        // Construit la requête à exécuter
        $request=$task->getRequest();
        
        // Exécute la tâche
        try
        {
            Module::run($request);
        }
        
        // Une erreur s'est produite
        catch (Exception $e)
        {
            // ferme la barre de progression éventuelle
            self::progress();

            $task->setStatus(Task::Error)->save();
            //ExceptionManager::handleException($e, false);
            throw $e;
            ob_end_flush();
            fclose(self::$outputFile);
        	return;
        }

        // Ferme la barre de progression éventuelle
        self::progress();
        
        // Indique que la tâche s'est exécutée correctement
        $task->setStatus($task->getRepeat() ? Task::Waiting : Task::Done)->save();

        echo sprintf
        (
            '<div class="taskinfo">Tâche #%s : terminée<br />Fin d\'exécution : %s<br />Durée d\'exécution : %s<br /></div>',
            self::$id, strftime('%x %X'), Utils::friendlyElapsedTime(time()-$start)
        );        
        
        ob_end_flush();
        fclose(self::$outputFile);
    }
    
    /**
     * Gestionnaire ob_start utilisé pour capturer la sortie des tâches
     * 
     * Ces gestionnaire est installé et désinstallé par {@link actionRunTask()}.
     * 
     * Cette fonction est automatiquement appellée par php lorsque la tâche
     * en cours d'exécution écrit quelque chose sur la sortie standard, elle ne
     * doit pas être appellée directement.
     * 
     * Consultez la {@link http://php.net/ob_start documentation de ob_start()}
     * pour plus d'informations.
     * 
     * Remarques :
     * - dans l'idéal, cette méthode devrait être déclarée 'private'
     *   mais php exige que les callback utilisés avec ob_start soient 'public'.
     * - La classe {@link Utils} contient également des méthodes permettant 
     *   de capturer la sortie standard dans un fichier. Le code a été répété
     *   car dans la version actuelle, la classe Utils n'autorise qu'un niveau
     *   de capture. Si une tâche a elle-même besoin de capturer quelque chose
     *   (par exemple pour générer un fichier d'export), on aurait été bloqué.
     * 
     * @param string $buffer les données à écrire
     * 
     * @param int $phase un champ de bits constitué des constantes 
     * PHP_OUTPUT_HANDLER_START, PHP_OUTPUT_HANDLER_CONT et PHP_OUTPUT_HANDLER_END
     * de php.
     * 
     * @return bool la fonction retourne false en cas d'erreur (si le fichier
     * de sortie ne peut pas être ouvert en écriture.
     */
    public static function taskOutputHandler($buffer, $phase)
    {
        fwrite(self::$outputFile, $buffer);
        return ; // ne pas mettre return true, sinon php affiche '111111...'
    }
    
    /**
     * Exécute une action d'un module fab en tâche de fond.
     * 
     * La fonction crée un nouveau process qui chargera le module indiqué
     * et lancera l'exécution de l'action indiquée.
     * 
     * Pour cela, elle récupère dans la configuration le path de l'exécutable
     * php à utiliser et le lance avec en paramètres le path du fichier
     * <code>/bin/fab.php</code> de fab et les arguments nécessaires pour 
     * lancer le module et l'action demandés.
     * 
     * La manière dont l'exécutable php est lancé dépend du système 
     * d'exploitation utilisé :
     * - sous linux, le process est simplement lancé en utilisant une ligne de 
     *   commande du style :
     * <code>
     *      /usr/bin/php -f /var/fab/bin/fab.php -- /module/action?params /var/www/approot &
     * </code>
     *   C'est le <code>&</code> final qui permet de faire en sorte que 
     *   l'application soit lancée en tâche de fond.
     * - sous windows, c'est en général l'exécutable <code>php-win.exe</code>
     *   qui est utilisé. Pour le lancer en tâche de fond, une instance du
     *   composant ActiveX <code>WScript.Shell</code> de Windows est créée et la
     *   méthode <code>Run</code> est utilisée pour lancer l'exécutable php en
     *   tâche de fond. Consultez la 
     *   {@link http://msdn2.microsoft.com/en-us/library/d5fk67ky(VS.85).aspx 
     *   documentation de Microsoft} pour plus d'informations.
     * 
     *  
     * @param string $fabUrl la fab url (/module/action?params) à exécuter
     * @param string $root la racine de l'application à passer en paramètre à 
     * {@link Runtime::setup()}.
     */
    private static function runBackgroundModule($fabUrl, $root='', $home='')
    {
        // Détermine le path de l'exécutable php-cli
        if (!$cmd = Config :: get('taskmanager.php', ''))
            throw new Exception('Le path de l\'exécutable php ne figure pas dans la config');

        // Vérifie que le programme php obtenu existe et est exécutable
        if (!is_executable($cmd))
            throw new Exception("Impossible de trouver $cmd");

        // Si le path contient des espaces, ajoute des guillemets
        $cmd=escapeshellarg($cmd); // escapeshellcmd ne fait pas ce qu'on veut. bizarre
        
        // Détermine les options éventuelles à passer à php
        $args = Config :: get('taskmanager.phpargs');
        if ($args)
            $cmd .= ' ' . $args;
            
        // Ajoute le path du fichier php à exécuter
        if ($home)        
            $phpFile = $root . 'web' . DIRECTORY_SEPARATOR . basename($home);
        else
            $phpFile = Runtime::$webRoot . Runtime::$fcName;
        
        $cmd .= ' ' . escapeshellarg($phpFile);

        // Argument 1 : module/action à exécuter
        $cmd .= ' ' . escapeshellarg($fabUrl);
        
        // Argument 2 : url de la page d'accueil de l'application
        if ($home)
            $cmd .= ' ' . escapeshellarg($home);
        
        // Sous windows, on utilise wscript.shell pour lancer le process en tâche de fond
        if ( substr(PHP_OS,0,3) == 'WIN')
        { 
            $WshShell = new COM("WScript.Shell");
            $oExec = $WshShell->Run($cmd, 0, false);
        }

        // Sinon, on considère qu'on est sous *nix et utilise le & final
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
     * Affiche le statut d'une tâche, le résultat de sa dernière exécution, la
     * progression de l'étape en cours.
     * 
     * @param int $id l'identifiant de la tâche à afficher.
     * @param int $start un offset indiquant la partie de la sortie générée
     * par la tâche à récupérer.
     */
    public function actionTaskStatus($id, $start=0)
    {
        // Récupère l'ID de la tâche à exécuter
        self::$id=$this->request->int('id')->required()->min(1)->ok();
        
        // Charge la tâche indiquée
        $task=new Task($id);
        
        $outputFile=self::getOutputDirectory().$task->getId(false) . '.html';
        
        $ftell=0;

        if (file_exists($outputFile))
        {
            $file=fopen($outputFile, 'r');
            if ($start) fseek($file, $start); 
            fpassthru($file);
            $ftell=ftell($file);
            fclose($file);
        }
                
        switch ($status=$task->getStatus())
        {
            case Task::Waiting :
            case Task::Starting :
            case Task::Running :
                           
                $progress=self::request('getprogress '.$id);
                if ($progress==='')
                    $step=$max=0;
                else
                    sscanf($progress, '%d %d', $step, $max);
                
                echo sprintf
                (
                    '<span id="updater" url="%s" step="%d" max="%d"></span>',
                    Routing::linkFor($this->request->copy()->set('start', $ftell)->getUrl()),
                    $step, $max
                );
                break;
                
            case Task::Done:
            case Task::Error:
                break;
                
            case Task::Disabled:
            case Task::Expired:
                echo '<p>La tâche est en statut "', $status, '".</p>';
                break;
        }        
    }
    
    /**
     * Action permettant à l'utilisateur de démarrer le démon.
     * 
     * La fonction {@link start()} est appellée puis l'utilisateur est redirigé 
     * vers la page d'accueil du gestionnaire de tâches.
     */
    public function actionStart()
    {
        self::start();
        Runtime::redirect('index');
    }

    /**
     * Action permettant à l'utilisateur d'arrêter le démon.
     * 
     * La fonction {@link stop()} est appellée puis l'utilisateur est redirigé 
     * vers la page d'accueil du gestionnaire de tâches.
     */
    public function actionStop()
    {
        try
        {
            self::stop();
        }
        catch (Exception $e)
        {
            echo 'Erreur : ', $e->getMessage();
            return;
        }
        Runtime::redirect('index');
    }

    /**
     * Action permettant à l'utilisateur de redémarrer le démon.
     * 
     * La fonction {@link restart()} est appellée puis l'utilisateur est 
     * redirigé vers la page d'accueil du gestionnaire de tâches.
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
	 * Indique si le gestionnaire de tâches est en cours d'exécution ou non.
	 * 
	 * @return bool
	 */
	public static function isRunning()
	{
		return self::request('running?') == 'yes';
	}

	/**
	 * Démarre le gestionnaire de tâches.
	 * 
	 * Génère une exception en cas d'erreur (gestionnaire déjà démarré,
	 * impossible de lancer le process, etc.)
	 * 
	 * Remarque : une pause de une seconde est appliquée après avoir lancé le
	 * gestionnaire de tâches pour laisser le temps au démon de s'initialiser
	 * et de démarrer.
	 * 
	 * @return bool true si le serveur a pu être démarré, faux sinon.
	 */
	public static function start()
	{
		if (self::isRunning())
			throw new Exception('Le gestionnaire de tâches est déjà lancé');
		// à voir : pour *nix : nohup

        self::runBackgroundModule('/TaskManager/Daemon');
        
        sleep(1); // on lui laisse un peu de temps pour démarrer
        
        return self::isRunning();
	}

	/**
	 * Arrête le gestionnaire de tâches.
	 * 
	 * Génère une exception en cas d'erreur (gestionnaire non démarré,
	 * impossible de lancer le process, etc.)
	 * 
	 * @return bool true si le serveur a pu être arrêté, faux sinon.
	 */
	public static function stop()
	{
		if (!self::isRunning())
			throw new Exception('Le gestionnaire de tâches n\'est pas lancé');

		return self :: request('quit');
	}

	/**
	 * Redémarre le gestionnaire de tâches. Equivalent à un stop suivi d'un
	 * start.
	 * 
	 * @return bool true si le serveur a pu être redémarré, faux sinon.
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
	 * @return bool string
	 */
	public static function status()
	{
		if (!self::isRunning())
			return 'Le gestionnaire de tâches n\'est pas lancé';
		return self::request('status');
	}


    /**
     * Envoie une requête demandant au démon de rafraichir ses données.
     * 
     * Cette fonction est utilisée lorsque des modifications sont apportées
     * à la base des tâches (création ou suppression d'une tâche, nouvelle
     * programmation, etc.).
     * 
     * Elle permet de dire au démon que la liste des tâches en attente a 
     * peut-être changé et qu'il doit mettre à jour ses strctures de données
     * internes.
     */
    public static function daemonUpdate()
    {
        self::request('update');
    }
	
    /**
	 * Envoie une requête au démon du gestionnaire de tâches et retourne la 
	 * réponse obtenue.
	 * 
	 * @param string $command la commande à envoyer au démon.
	 * @param string $error une variable qui en sortie recevra les éventuelles
	 * erreurs TCP obtenues lors de la requête.
	 * @return string la réponse retournée par le démon.
	 */
	private static function request($command, & $error = '')
	{
		$port = Config::get('taskmanager.port');
		$timeout = Config::get('taskmanager.timeout'); // en secondes, un float
		$timeout = 0.5;

		$errno = 0; // évite warning 'var not initialized'
		$errstr = '';

		// Crée une connexion au serveur
        $socket = @stream_socket_client('tcp://127.0.0.1:' . $port, $errno, $errstr, $timeout,STREAM_CLIENT_CONNECT);

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
     * Permet à une tâche en cours d'exécution de faire état de sa progression.
     * 
     * Le but de la fonction progress est de créer une barre de progression
     * qui sera affichée dans le {@link actionTaskStatus() statut de la tâche}
     * pendant que celle-ci est en cours d'exécution.
     * 
     * La fonction calcule un pourcentage à partir des valeurs indiquées (étape
     * en cours et nombre total d'étapes) et ce pourcentage sera utilisé pour
     * mettre à jour le "niveau de remplissage" de la barre de progression.
     *  
     * Si progress a déjà été appellé en indiquant le nombre total d'étapes,
     * vous pouvez, dans l'omettre dans les appels suivants.
     * 
     * Si la fonction est appellée sans aucun paramètres, la barre de progression
     * est fermée. Cette étape n'est en général pas utile car la barre de 
     * progression est de toute façon fermée automatiquement à la fin de 
     * l'exécution de la tâche. De même, si vous ouvrez une nouvelle barre de
     * progression (un spécifiant un nouveau max, ça ferme automatiquement la 
     * barre de progression précédente).
     * 
     * @param int $step l'étape en cours
     * @param int $max nombre total d'étapes
     */
    public static function progress($step=0, $max=0)
    {
        self::request(sprintf('setprogress %d %d %d', self::$id, $step, $max));
    }

    /**
     * Fonction de test pour le TaskManager, à supprimer
     *
     */
    public function actionTest()
    {
        $t=array
        (
//            null, null, null,
            0, null, null,
//            2, null, array('p1'=>10, 'p2'=>20, 'p3'=>array(4,5,6), 'p4'=>5.1111123),
//            3, null, null,
//            4, null, null,
//            1, '20 sec', null,
//            0, '10 sec', null,
        );
        
        echo '<pre>';
        for($i=0; $i<count($t); $i=$i+3)
        {
            $time=$t[$i];
            $repeat=$t[$i+1];
            $parameters=$t[$i+2];
            
            if ($time===0)
                $title='Asap';
            else
            {
                $title='Dans '.$time.' min.';
                $title="Un essai de titre particulièrement long pour une tâche (histoire de voir comment ça s'affiche et comment le navigateur gère le retour à la ligne)";
                $title='test';
                $time=time()+$time*60;
            }
            
            if (! is_null($parameters)) $title.='<br /> params='.Utils::varExport($parameters,true);
            
            $task=new Task();
            $task->setLabel($title);
            $task->setTime($time);
            //$task->setLast($time);
            if (! is_null($repeat))
                $task->setRepeat($repeat);
                
            $request=new Request();
            $request
                ->setModule('TaskManager')
                ->setAction('TestTask');
            if (! is_null($parameters))
                $request->addParameters($parameters);
            $task->setRequest($request);
            $task->setStatus(Task::Waiting);
            $task->save();
            Runtime::redirect('/TaskManager/TaskStatus?id='.$task->getId(false));
            //echo 'Id de la tâche créée : ', $task->getId(false), "\n";
        }
    }

    /**
     * Tâche de test pour le TaskManager, à supprimer
     *
     */
    public function actionTestTask()
    {
        echo 'Début d\'exécution de la tâche. Mon id=', self::$id, ', url=',$this->request,'<br />';
        for($i=1;$i<3;$i++)
        {
            echo '<h1>Etape ', $i, '</h1>';
            for($j=1;$j<=100;$j++)
            {
                TaskManager::progress($j, 100);
                usleep(200000);
                if (rand(0,9)>8) echo '<p>aléatoire</p>';
            }
            echo '<p>Etape ', $i, ' terminée.</p>';
        }
        echo 'Fin d\'exécution de la tâche<br />';
    }
}
?>