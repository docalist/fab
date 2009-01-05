<?php
/**
 * @package     fab
 * @subpackage  TaskManager
 * @author      Daniel Ménard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id$
 */

/**
 * Représente une tâche qui sera exécutée ultérieurement par le 
 * {@link TaskManager gestionnaire de tâches}.
 * 
 * Une tâche est principalement constituée {@link setRequest() d'une requête} 
 * qui sera exécutée à une {@link setTime() heure donnée} et qui 
 * éventuellement {@link setRepat() se répète}.
 * 
 * Elle comporte également différents attributs ({@link setLabel() un titre}, 
 * {@link setStatus() un état}, ...) et différentes informations de gestion
 * ({@link getId() identifiant unique}, {@link getCreation() date de création},
 * date de {@link getNext() prochaine} et de {@link getLast() dernière} 
 * exécution, {@link getOutput() sortie générée} lors de la dernière exécution, 
 * etc.)
 * 
 * La classe Task est une interface agile permettant de créer et de modifier des 
 * tâches.
 * 
 * La majorité des méthodes retourne la tâche en cours ce qui permet de 
 * chainer les méthodes.
 * 
 * Exemple :
 * <code>
 *     $task=new Task();
 * 
 *     $task
 *         ->setRequest($this->request->setAction('doBackup'))  // Lancer une sauvegarde
 *         ->setTime(0)                                         // Exécuter "dès que possible"
 *         ->setRepeat('1 j./lun-ven')                          // Puis tous les jours du lundi au vendredi 
 *         ->setLabel('Sauvegarde de la base')                  // Le titre de la tâche 
 *         ->save();
 *     
 *     echo 'La tâche ', $task->getId(), ' a été créée.'; 
 * </code> 
 * 
 * Assez souvent, la requête correspondant à la tâche à créer est similaire
 * (voire identique) à la requête appellée pour créer la tâche (par exemple
 * on appelle une action 'backup' qui va vérifier tous les paramètres fournis 
 * puis qui va créer une tâche 'doBackup' en lui passant tous ces les paramètres).
 * 
 * Néanmoins, vous pouvez modifier comme vous le souhaitez la requête en cours
 * (comme dans l'exemple ci-dessus ou on a simplement changé l'action à exécuter)
 * ou créer un nouvel {@link Request objet Request} de toute pièce.
 * 
 * Exemple :
 * <code>
 *     // Crée d'abord la requête  
 *     $request=new Request();
 *     $request
 *         ->setModule('Backup')
 *         ->setAction('backupDatabase')
 *         ->set('database', 'mydb');
 *         ->set('format', 'xml');
 *         ->set('filename', 'backup.xml');
 *
 *     // Puis planifie la tâche 
 *     $task=new Task();
 *     $task
 *         ->setRequest($request)   // Lancer une sauvegarde
 *         ->setTime(time()+86400)  // demain
 *         ->save();
 * </code> 
 * 
 * @package     fab
 * @subpackage  TaskManager
 */
class Task
{
    /**
     * Constantes représentant le statut des tâches (cf {@link getStatus()})
     */
    const Disabled='disabled';  // tâche désactivée
    const Waiting='waiting';    // tâche en attente (ce n'est pas encore l'heure de l'exécuter)
    const Starting='starting';  // tâche en train de démarrer (lancée par le TaskManager)
    const Running='running';    // tâche en cours d'exécution
    const Done='done';          // tâche terminée
    const Error='error';        // tâche en erreur (lancement impossible, erreur durant l'exécution...)
    const Expired='expired';    // heure d'exécution prévue dépassée
    
    /**
     * Numéro unique identifiant la tâche
     * L'id n'existe que pour une tâche qui a été {@link save() enregistrée}
     * 
     * @var null|int
     */
    private $id=null;

    /**
     * Libellé de la tâche
     * 
     * @var string
     */
    private $label='';
    
    /**
     * Statut de la tâche
     * 
     * @var string
     */
    private $status=self::Waiting;
    
    /**
     * Path complet du répertoire racine de l'application propriétaire de la 
     * tâche.
     * 
     * Correspond à la valeur de {@link Runtime::$root} au moment où la tâche 
     * est créée.
     * 
     * @var path
     */
    private $applicationRoot='';
    
    /**
     * Url complète du front controler utilisé pour créer la tâche
     *
     * @var string
     */
    private $url='';
    
    /**
     * Date/heure à laquelle la tâche a été créée
     * 
     * @var timestamp
     */
    private $creation=null;
    
    /**
     * Date/heure à laquelle la tâche est planifiée
     * (null = jamais, 0=dès que possible)
     * 
     * @var null|0|timestamp
     */
    private $time=null;
    
    /**
     * Information de répétition de la tâche
     * (null = ne pas répéter)
     * 
     * @var string
     */
    private $repeat=null;

    /**
     * Module à charger pour exécuter la tâche
     * 
     * @var string
     */
    private $module=null;
    
    /**
     * Action à appeler pour exécuter la tâche
     * 
     * @var string
     */
    private $action=null;
    
    /**
     * Paramètres à passer à l'action pour exécuter la tâche
     * 
     * @var string
     */
    private $parameters=null;
    
    /**
     * Date/heure de la prochaine exécution de la tâche
     * 
     * @var null|0|timestamp
     */
    private $next=null;

    /**
     * Date/heure de la dernière exécution de la tâche
     * 
     * @var null|timestamp
     */
    private $last=null;
    
    /**
     * Path du fichier contenant la sortie générée par la tâche
     * 
     * @var path
     */
    private $output=null;
    
    
    /**
     * Crée une nouvelle tâche ou charge une tâche existante.
     * 
     * Si aucun ID de tâche n'est indiqué, une nouvelle tâche est créée. Dans 
     * le cas contraire l'ID indiqué et recherché dans la base et la tâche 
     * correspondante est chargée.
     * 
     * La tâche est créée/chargée uniquement en mémoire : les modifications 
     * apportées ne seront enregistrées dans la base de données que lorsque 
     * {@link save()} sera appelée.
     * 
     * En interne, il est également possible de créer une tâche en passant en
     * paramètre un objet {@link Database} ouvert sur l'enregistrement de tâche 
     * à charger.
     * 
     * @param null|int|Database $id l'identifiant de la tâche à charger ou null 
     * pour créer une nouvelle tâche ou une sélection pour créer une tâche à 
     * partir de l'enregistrement en cours.
     * 
     * @throws Exception si l'identifiant indiqué n'est pas correct 
     */
    public function __construct($id=null)
    {
        // Crée une nouvelle tâche (em mémoire, sera réellement créée si save est appelée)
        if (is_null($id))
        {
            $this->creation=self::timestampToString(time());
            $this->applicationRoot=Runtime::$root;
            $this->status=self::Disabled;
            $this->url=Utils::getHost() . Runtime::$realHome . Runtime::$fcName;
        }
        
        // Charge la tâche indiquée
        else
        {
            if ($id instanceof Database)
            {
                $tasks=$id;
            }
            else
            {
                // Ouvre la base de données (readonly)
                $database=TaskManager::getDatabasePath();
                $tasks=Database::open($database, true, 'xapian');
                
                // Recherche la tâche indiquée
                if( !$tasks->search('ID='.$id))
                    throw new Exception(sprintf('La tâche %s n\'existe pas',$id));
            }
            
            // Charge toutes les propriétés
            foreach($this as $property=>&$value)
                $value=$tasks[$property];
            
            // Ferme la base
            unset($tasks);
        }
    }

    /**
     * Méthode statique permettant de créer une nouvelle tâche ou de charger 
     * une tâche existante.
     * 
     * Php ne permet pas d'appeller une méthode sur un objet juste créé avec
     * l'opérateur new (<code>new Task()->save()</code>). Cette méthode 
     * contourne en permettant d'écrire <code>Task::create()->save()</code>.
     *
     * @param null|int|Database $id l'identifiant de la tâche à charger ou null 
     * pour créer une nouvelle tâche ou une sélection pour créer une tâche à 
     * partir de l'enregistrement en cours.
     * 
     * @throws Exception si l'identifiant indiqué n'est pas correct
     *  
     * @return Task
     */
    public static function create($id=null)
    {
        return new Task($id);
    }
    
    /**
     * Enregistre la tâche.
     *
     * S'il s'agit d'une nouvelle tâche, un nouvel enregistrement est créé
     * dans la base de données et un {@link getId() identifiant unique} est 
     * attribué à la tâche, sinon les modifications apportées à la tâche sont
     * enregistrées.
     * 
     * Lors de l'enregistrement, la {@link getNext() date/heure de prochaine 
     * exécution} de la tâche est mise à jour.
     * 
     * Si celle-ci est correcte, un signal est envoyé au {@link TaskManager
     * gestionnaire de tâches} pour qu'il tienne compte des modifications 
     * apportées à la tâche.
     * 
     * Remarque :
     * Si vous avez besoin très tôt de l'identifiant de la tâche (par exemple
     * pour indiquer dans des fichiers l'id de la tâche associée) vous pouvez
     * appeler {@link save()} juste après avoir créé une tâche.
     * 
     * Exemple :
     * <code>
     *     // On crée la tâche
     *     $task=new Task();
     * 
     *     // Et on l'enregistre aussitôt pour qu'un ID soit alloué
     *     $task->save(); 
     * 
     *     // On récupère l'ID obtenu et on fait quelque chose avec
     *     $id=$task->getId();
     *     // ...
     * 
     *     // On termine le paramétrage de la tâche et l'enregistre à nouveau
     *     $task->setRequest($request)->setTime(0)->save();
     * </code>
     * 
     * @return Task $this pour permettre le chainage des appels de méthodes
     * 
     * @throws Exception Si la tâche en cours n'existe pas (c'est un cas rare
     * qui peut subvenir si la tâche est supprimée via l'interface du 
     * TaskManager entre le moment où la tâche est chargée et le moment ou elle 
     * est enregistrée).
     */
    public function save()
    {
        // Ouvre la base de données (read/write)
        $database=TaskManager::getDatabasePath();
        $tasks=Database::open($database, false, 'xapian');
        
        // Nouvelle tâche jamais enregistrée (ie n'a pas d'ID) : addRecord
        if (is_null($this->id))
        {
            $tasks->addRecord();
        }
        
        // Sinon : updateRecord
        else
        {
            if( !$tasks->search('ID='.$this->id))
                throw new Exception(sprintf('Erreur interne : la tâche %s n\'existe pas', $this->id));
            $tasks->editRecord();
        }
        
        $this->next=self::timestampToString($this->computeNext());
        
        //$signal=(($this->status===Task::Waiting) && ($this->next !== $tasks['next']));
        $signal=($this->status===Task::Waiting);
        
        // Copie toutes nos propriétés dans l'enreg
        foreach($this as $property=>$value)
            $tasks[$property]=$value;
        
        // Enregistre la tâche
        $tasks->saveRecord();
        
        // Recopie dans l'autre sens (ie : récupère l'ID, lastUpdate éventuel...)
        foreach($this as $property=>&$value)
            $value=$tasks[$property];
            
        // Ferme la base
        unset($tasks);
        
        if ($signal)
        {
            TaskManager::daemonUpdate();
        }
        return $this;
    }
    
    /**
     * Retourne le path du répertoire racine de l'application qui a créé cette 
     * tâche.
     * 
     * i.e. à quel site appartient cette tâche
     *
     * @return path
     */
    public function getApplicationRoot()
    {
        return $this->applicationRoot;
    }

    /**
     * Retourne l'url de la page d'accueil de l'application qui a créé cette 
     * tâche.
     *
     * @return path
     */
    public function getUrl()
    {
        return $this->url;
    }
    
    /**
     * Définit la requête à exécuter pour lancer cette tâche
     *
     * @param Request $request
     * 
     * @return Task $this pour permettre le chainage des appels de méthodes
     */
    public function setRequest(Request $request)
    {
        $this->module=$request->getModule();
        $this->action=$request->getAction();
        $this->parameters=$request->getParameters();
        return $this;    
    }
    
    /**
     * Retourne la requête qui sera exécutée lorsque la tâche sera lancée
     *
     * @return Request
     */
    public function getRequest()
    {
        $request=new Request(is_null($this->parameters) ? array() : $this->parameters);
        return $request->setModule($this->module)->setAction($this->action);    
    }
/*    
    public function getModule()
    {
        return $this->module;
    }

    public function setModule($module)
    {
        $this->module=$module;
        return $this;
    }
    
  
A REMPLACER PAR :

    setRequest(Request $request)
    getRequest() : Request
---
 
    public function getAction()
    {
        return $this->action;
    }

    public function setAction($action)
    {
        $this->action=$action;
        return $this;
    }
    
    public function getParameters()
    {
        return $this->parameters;
        
//        if (is_null($this->parameters)) return null;
//        return unserialize($this->parameters);
    }

    public function setParameters($parameters)
    {
        $this->parameters=$parameters;
//        if (is_null($parameters))
//            $this->parameters=null;
//        else
//            $this->parameters=serialize($parameters);
        return $this;
    }
*/    
    /**
     * Retourne l'identifiant unique de la tâche
     *
     * @param bool $crypted par défaut, la fonction retourne un ID crypté,
     * indiquer false pour avoir l'ID réel.
     * 
     * todo: voir si on garde, expliquer le but
     * 
     * @return null|int|string
     */
    public function getId($crypted=true)
    {
        if ($crypted) return $this->id; // todo: cryptage/décryptage
        return $this->id;
    }
    
    /**
     * Retourne la date/heure à laquelle l'exécution de la tâche est prévue.
     * 
     * @return false|null|0|timestamp l'heure d'exécution prévue pour la tâche :
     * - null : tâche non planifiée, ne sera jamais exécutée
     * - 0 : exécuter la tâche dès que possible
     * - timestamp : un entier indiquant l'heure d'exécution prévue
     */
    public function getTime()
    {
        return self::stringToTimestamp($this->time);
    }

    
    /**
     * Modifie la date/heure à laquelle l'exécution de la tâche est prévue.
     * 
     * @param null|0|timestamp $time l'heure d'exécution prévue pour la tâche.
     * 
     * Les valeurs autorisées sont :
     * - null : tâche non planifiée, ne sera jamais exécutée
     * - 0 : exécuter la tâche dès que possible
     * - timestamp : un entier indiquant l'heure d'exécution prévue
     * 
     * @return Task $this pour permettre le chainage des appels de méthodes
     */
    public function setTime($time)
    {
        $this->time=self::timestampToString($time);
        return $this;
    }
    
    /**
     * Indique si la tâche est récurrente ou non et, si elle l'est, la manière
     * dont elle sera répétée. 
     *
     * @return null|string la fonction retourne null si la tâche n'est pas 
     * récurrente ou une chaine de caractères indiquant les informations de 
     * répétition sinon.
     * 
     * Voir la fonction {@link setRepeat()} pour le format de la chaine 
     * retournée.
     */
    public function getRepeat()
    {
        return $this->repeat;
    }
    
    /**
     * Définit la manière dont une tâche récurrente sera répétée.
     * 
     * Par défaut, une tâche nouvellement créée ne s'exécutera qu'une seule fois
     * mais il est possible de définir une tâche récurrente (faire une 
     * sauvegarde toutes les nuits, nettoyer les fichiers temporaires toutes les
     * 12 heures...) en appelant setRepeat() avec une chaine indiquant comment
     * la tâche doit se répéter.
     * 
     * La chaine se compose de deux parties :
     * - un nombre indiquant la fréquence à laquelle la tâche sera répétée
     * (toutes les heures, tous les 2 jours, tous les mois...)
     * - un filtre optionnel indiquant des restrictions sur les dates autorisées
     * (seulement la nuit, uniquement en semaine...)
     * 
     * La fréquence de répétition doit être exprimée sous la forme d'un entier 
     * (positif et non nul) suivi d'une unité de temps (par exemple '10 min.'
     * pour une tâche à exécuter toutes les dix minutes).
     * 
     * Les unités de temps acceptées sont :
     * - secondes : 's', 'sec', 'second', 'seconde' 
     * - minutes : 'mn', 'min', 'minute' 
     * - heures : 'h', 'hour', 'heure'
     * - jours : 'd', 'j', 'day', 'jour'
     * - mois : 'mon', 'month', 'monthe', 'moi'
     *
     * L'unité peut être ou non suivie d'un point ou d'un 's' final
     * ('10 min' == '10 min.', '10 minutes' == '10 minutes').
     * 
     * Remarque :
     * Il n'existe pas d'unités pour indiquer 'une année' ou 'un trimestre' : 
     * vous devez indiquer la durée en mois.
     * 
     * Le filtre, s'il est présent, doit être constitué d'une suite d'éléments
     * exprimés dans l'unité de temps indiquée avant. Chaque élément peut être
     * un élément unique ou une période indiquée par deux éléments séparés par
     * un tiret.
     * 
     * Exemples :
     * - "1 mois" : tâche exécutée tous les mois.
     * - "1 h./8-12,14-18" : toutes les heures, mais seulement de 8h à 12h et de
     *   14h à 18h
     * - "1 jour/lun-mar" : tous les jours, mais seulement les jours en semaine
     * - "2 jours/1-15,lun-mar,ven" : tous les deux jours, mais seulement si ça
     * tombe sur un jour compris entre le 1er et le 15 du mois ou alors si le
     * jour obtenu est un lundi, un mardi ou un vendredi
     * - "1 jour/sam" : tous les samedis
     * 
     * Remarques : les espaces sont autorisés un peu partout (entre le nombre
     * et l'unité, entre les éléments des filtres, etc.)
     * 
     * @param null|string $repeat soit une chaine indiquant la manière dont la 
     * tâche doit être répétée, soit null pour indiquer que la tâche n'est pas 
     * récurrente.
     * 
     * @return Task $this pour permettre le chainage des appels de méthodes
     */
    public function setRepeat($repeat=null)
    {
        if ($repeat===false || $repeat===0 || $repeat==='') $repeat=null;
        $this->repeat=$repeat;
        return $this;
    }

    /**
     * Retourne la date de dernière exécution de la tâche ou null si la tâche 
     * n'a pas encore été lancée.
     * 
     * @return timestamp|null un entier représentant la date/heure de dernière
     * exécution de la tâche ou null
     */
    public function getLast()
    {
        return self::stringToTimestamp($this->last);
    }
    
    /**
     * Modifie la date de dernière exécution de la tâche.
     *
     * @param timestamp|null $last un entier représentant la date/heure de
     * dernière exécution ou null si la tâche pour indiquer que la tâche n'a 
     * pas encore été lancée.
     * 
     * @return Task $this pour permettre le chainage des appels de méthodes
     */
    public function setLast($last)
    {
        $this->last=self::timestampToString($last);
        return $this;
    }
    
    /**
     * Retourne la date à laquelle la tâche a été créée.
     *
     * @return timestamp un entier représentant la date/heure à laquelle la 
     * tâche a été créée.
     */
    public function getCreation()
    {
        return self::stringToTimestamp($this->creation);
    }

    /**
     * Retourne la date de prochaine exécution prévue pour la tâche.
     * 
     * todo: pas clair, expliquer la différence entre {@link getTime} et getNext() 
     * 
     * @return null|0|timestamp un entier représentant l'heure d'exécution 
     * prévue pour la tâche :
     * - null : tâche non planifiée, ne sera jamais exécutée
     * - 0 : exécuter la tâche dès que possible
     * - timestamp : un entier indiquant l'heure d'exécution prévue
     * 
     */
    public function getNext()
    {
        return self::stringToTimestamp($this->next);
    }

    /**
     * Retourne le statut actuel de la tâche
     *
     * @return string l'une des constantes de statut définies dans la 
     * classe Task :
     * {@link Waiting}, {@link Starting}, {@link Running}, {@link Done},
     * {@link Error}, {@link Disabled} et {@link Expired}
     */
    public function getStatus()
    {
        return $this->status;
    }
    
    /**
     * Modifie le statut de la tâche
     *
     * @param string $status l'une des constantes de statut définies dans la 
     * classe Task :
     * {@link Waiting}, {@link Starting}, {@link Running}, {@link Done},
     * {@link Error}, {@link Disabled} et {@link Expired}
     *   
     * @return Task $this pour permettre le chainage des appels de méthodes
     * 
     * @throws OutOfRangeException si le statut indiqué n'est pas valide 
     */
    public function setStatus($status)
    {
        switch ($status)
        {
            case self::Waiting:
            case self::Starting:
            case self::Running:
            case self::Done:
            case self::Error:
            case self::Disabled:
            case self::Expired:
                $this->status=$status;
                break;
            default:
                throw new OutOfRangeException
                (
                    sprintf('Statut de tâche invalide : %s', $status)
                );
        }
        return $this;
    }
    
    /**
     * Retourne le libellé (le titre) de la tâche
     *
     * @return string
     */
    public function getLabel()
    {
        return $this->label;
    }
    
    /**
     * Modifie le libellé (le titre) d'une tâche
     *
     * @param string $label
     * @return Task $this pour permettre le chainage des appels de méthodes
     */
    public function setLabel($label)
    {
        $this->label=$label;
        return $this;
    }

    /**
     * Retourne le path du fichier qui contient la sortie générée lors de la
     * dernière exécution de la tâche ou null si aucun nom de fichier de sortie
     * n'a été définit.
     *
     * @return path|null
     */
    public function getOutput()
    {
        return $this->output;
    }
    
    /**
     * Modifie le path du fichier qui contient la sortie générée par la tâche
     *
     * @param path $output
     * 
     * @return Task $this pour permettre le chainage des appels de méthodes
     */
    public function setOutput($output)
    {
        $this->output=$output;
        return $this;
    }
    
    /**
     * Convertit un nom (jour, lundi, mars) ou une abréviation (j., lun,
     * mar) utilisée dans la date de programmation d'une tâche et retourne
     * le numéro interne correspondant.
     * 
     * Si l'argument passé est déjà sous la forme d'un numéro, retourne ce
     * numéro.
     * 
     * 
     * @param mixed $value un entier ou une chaine à convertir.
     * @param string $what le type d'abréviation recherché. Doit être une des
     * valeurs suivantes : 'units' (unités de temps telles que jours, mois...),
     * 'minutes', 'hours', 'mday' (noms de jours) ou 'mon' (noms de mois).
     * 
     * @return int
     * 
     * @throws Exception si l'abréviation utilisée n'est pas reconnue.
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
     * Calcule la date et l'heure de prochaine exécution d'une tâche.
     * 
     * Par défaut, la fonction retourne la date de prochaine exécution de la 
     * tâche (ie la prochaine date/heure qui soit supérieure à l'heure actuelle)
     * mais vous pouvez passer un timestamp en paramètre pour obtenir la 
     * date/heure qui suit immédiatement ce timestamp.
     * 
     * Cela permet par exemple de calculer des dates dans le passé ou bien
     * les dates suivantes d'exécution.
     * 
     * La fonction fait de son mieux pour conserver la date et l'heure 
     * d'exécution initialement prévues pour la tâche (par exemple si une tâche 
     * a été initialement programmée à 12h 53min 25sec et qu'elle se répête 
     * toutes les heures, les minutes et les secondes seront à chaque fois 
     * conservées).
     * 
     * Cependant ce n'est pas toujours possible : si une tâche est programmée 
     * le 31 janvier avec l'option "répéter tous les mois", la fonction ne pourra
     * pas retourner "31 février" et ajustera la date en conséquence (2 ou 3
     * mars selon que l'année est bissextile ou non).
     *
     * @param timestamp $now
     * @return null|timestamp
     */
    public function computeNext($now=null)
    {
        // Récupère l'heure actuelle
        if (is_null($now)) $now=time();

        $next=$this->getNext();
        $time=$this->getTime();
        $last=$this->getLast();
        
        if (! is_null($next) && ($next>=$now)) return $next;
        

        // Si la tâche n'est pas planifiée, prochaine exécution=jamais
        if ($time===null) return null;
        
        // Si la tâche a déjà été exécutée et qu'elle n'est pas répétitive, prochaine exécution=jamais
        if (!is_null($last) && is_null($this->repeat)) return null;
            
        // Si la tâche est planifiée "dès que possible" et n'a pas encore été exécutée, prochaine exécution=dès que possible
        if ($time===0 && is_null($last)) return 0;
        
        // Si la tâche est planifiée pour plus tard, prochaine exécution=date indiquée
        if ($time > $now) return $time;
        
        // Si la tâche était planifiée mais n'a pas été exécutée à l'heure souhaitée, prochaine exécution=heure prévue (+erreur tâche dépassée)
        if ($time<=$now && is_null($last)) return $time; // $now;
        
        // La tâche n'est pas répétitive, prochaine exécution : jamais
        if (is_null($this->repeat)) return null;
        
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
        
        // Heure de début des calculs : l'heure d'exécution prévue (si <> asap), heure de création sinon
        $next=$time; // et si 0 ?
        if ($next===0) $next=$this->getCreation();
        
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
            if ($k > 100) die('INFINITE LOOP here');
        } 
                
        // Si on n'a aucun filtre, terminé
        if (is_null($filterString))
            return $next;
        
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
                    if (isset($filter[$t[$unit]])) return $next;
                    break;
                case 'mday':
                    if (isset($filter[$t[$unit]]) or isset($filter[$t['wday']+1000])) return $next;
                    break;
            }
    
            // Passe à la date suivante et recommence 
            $t[$unit]+=$nb;
            $next=mktime($t['hours'],$t['minutes'],$t['seconds'],$t['mon'],$t['mday'],$t['year']);
        }
        
        // Stocke et retourne le résultat
        return $next;
    }
    
    
    /**
     * Convertit un timestamp en chaine utilisable pour le tri xapian
     * (en attendant qu'on crée des clés de tri correct pour les entiers)
     *
     * Attention : fonction temporaire, ne pas utiliser.
     * 
     * @param null|0|timestamp $timestamp
     * @return string
     */
    public static function timestampToString($timestamp)
    {
        if (is_null($timestamp)) return null;
        //if (! is_int($timestamp)) return gettype($timestamp). ' not int'; // erreur
        if ($timestamp===0) return '0';
        return strftime('%Y%m%d%H%M%S',$timestamp);
    }
    
    /**
     * Convertit une chaine en timestamp
     *
     * Attention : fonction temporaire, ne pas utiliser.
     * 
     * @param string $string
     * @return timestamp
     */
    public static function stringToTimestamp($string)
    {
        if (is_null($string)) return null;
        if ($string==='0') return 0;
        if (strlen($string)!==14 || !ctype_digit($string)) return null; // erreur
        // AAAAMMJJHHMMSS
        // 01234567890123
        return mktime
        (
            (int)substr($string,  8, 2), // heures
            (int)substr($string, 10, 2), // minutes
            (int)substr($string, 12, 2), // secondes
            (int)substr($string,  4, 2), // mois
            (int)substr($string,  6, 2), // jour
            (int)substr($string,  0, 4)  // année
        );
    }
}
//
//for($i=1; $i<10000; $i++)
//{
//    $j=strtr($i/3.14,',','.');
//    //$x=base64_encode($j);
//    $x=convert_uuencode($j);
//    //$y=(double)base64_decode($x);
//    $y=(double)convert_uudecode($x);
//    
//    $k=(int)round($y*3.14);
//    echo 'i=', $i, ', j=', $j, ', x=', $x, ', y=', $y, ', k=', $k, ($i===$k ? '. ok.' : '. ERREUR.'), '<br />';
//}
//die();
/*
echo '<pre>';
function GenerationCle($Texte,$CleDEncryptage)
  {
  $CleDEncryptage = md5($CleDEncryptage);
  $Compteur=0;
  $VariableTemp = "";
  for ($Ctr=0;$Ctr<strlen($Texte);$Ctr++)
    {
    if ($Compteur==strlen($CleDEncryptage))
      $Compteur=0;
    $VariableTemp.= substr($Texte,$Ctr,1) ^ substr($CleDEncryptage,$Compteur,1);
    $Compteur++;
    }
  return $VariableTemp;
  }

function Crypte($Texte,$Cle)
  {
  srand((double)microtime()*1000000);
  $CleDEncryptage = md5(rand(0,32000) );
  $Compteur=0;
  $VariableTemp = "";
  for ($Ctr=0;$Ctr<strlen($Texte);$Ctr++)
    {
    if ($Compteur==strlen($CleDEncryptage))
      $Compteur=0;
    $VariableTemp.= substr($CleDEncryptage,$Compteur,1).(substr($Texte,$Ctr,1) ^ substr($CleDEncryptage,$Compteur,1) );
    $Compteur++;
    }
  return base64_encode(GenerationCle($VariableTemp,$Cle) );
  }

function Decrypte($Texte,$Cle)
  {
  $Texte = GenerationCle(base64_decode($Texte),$Cle);
  $VariableTemp = "";
  for ($Ctr=0;$Ctr<strlen($Texte);$Ctr++)
    {
    $md5 = substr($Texte,$Ctr,1);
    $Ctr++;
    $VariableTemp.= (substr($Texte,$Ctr,1) ^ $md5);
    }
  return $VariableTemp;
  }
//Exemple de l'appel aux fonctions Crypte et Decrypte :

$Cle = "MotDePasseSuperSecret";
$MonTexte = 20;
$TexteCrypte = Crypte($MonTexte,$Cle);
$TexteClair = Decrypte($TexteCrypte,$Cle);
echo "Texte original : $MonTexte <Br>";
echo "Texte crypté : $TexteCrypte <Br>";
echo "Texte décrypté : $TexteClair <Br>";
die();
*/
?>