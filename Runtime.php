<?php
/**
 * @package     fab
 * @subpackage  runtime
 * @author 		Daniel M�nard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id: Runtime.php 235 2006-12-20 09:02:07Z dmenard $
 */


/**
 * Le coeur de fab
 * 
 * Vocabulaire : le site web = la partie visible sur internet (la page d'index,
 * les images, css, javascript, etc.), plus exactement, il s'agit de tout le
 * contenu qui peut �tre appell� au travers d'un navigateur - >$webroot= le path
 * du r�pertoire correspondant
 * 
 * l'application = l'ensemble de l'application, c'est � dire le site web et
 * toutes les autres librairies, scripts, fichiers de configuration,
 * donn�es, etc. dont le site a besoin. Par convention, l'application
 * commence au r�pertoire au-dessus de webroot.
 * ->$root = le path du r�pertoire de l'application
 * remarque : une m�me application peut contenir plusieurs sites web (par
 * exemple web visible et site d'administration)
 * $root\web
 *      \admin
 * 
 * home : le pr�fixe commun � toutes les urls pour un site donn�
 * 
 * @package     fab
 * @subpackage  runtime
 */
 
class Runtime
{
    /**
     * La version en cours de fab, sous forme de chaine de caract�res.
     * 
     * Cette constante doit �tre mise � jour chaque fois qu'une nouvelle 
     * release de fab est faite.
     * 
     * Vous pouvez utiliser la fonction php version_compare() pour v�rifier
     * que la version install�e de fab est au moins celle que vous attendez :
     * <code>
     *      if (version_compare(Runtime::version, '0.5.0', '<=')
     *          die('La version version 0.5.0 ou sup�rieure de fab est requise');
     * </code>
     */
    const Version='0.5.0';
    
    /**
     * @var string Path du r�pertoire racine du site web contenant la page
     * demand�e par l'utilisateur (le front controler). webRoot est forc�ment un
     * sous- r�pertoire de {@link $root}. webRoot contient toujours un slash
     * (sous linux) ou un anti-slash (sous windows) � la fin.
     */
    public static $webRoot='';
    
    /**
     * @var string Path du r�pertoire racine de l'application. Par convention,
     * il s'agit toujours du r�pertoire parent de {@link $webroot}.$root
     * contient toujours un slash (sous linux) ou un anti-slash (sous windows) �
     * la fin.
     * @access public
     */
    public static $root='';
    
    /**
     * @var string Path du r�pertoire racine du framework. $fabRoot contient
     * toujours un slash (sous linux) ou un anti-slash (sous windows) � la fin.
     * @access public
     */
    public static $fabRoot='';
    
    /**
     * @var string Racine du site web. Cette racine figurera dans toutes les
     * urls du site.
     * 
     * Exemple : si le site se trouve a l'adresse http://apache/web/site1, home
     * aura la valeur /web/site1/
     * 
     * Lorsque les smart urls sont d�sactiv�es, home contiendra toujours le nom
     * du front controler utilis� (par exemple /web/site1/index.php).
     */
    public static $home='';
    
    /**
     * idem home mais ne contient jamais le nom du FC. Utilis� par Routing::
     * linkFor lorsque l'url correspond � un fichier existant du site.
     */
    public static $realHome='';
    
    /**
     * @var string Adresse relative de la page demand�e par l'utilisateur
     */
    public static $url='';
    private static $fcInUrl;
    private static $fcName;
    
    public static $env='';
    
    public static $queryString=''; // initialis� par repairgetpost
    
    // V�rifie qu'on a l'environnement minimum n�cessaire � l'ex�cution de l'application
    // et que la configuration de php est "correcte"
    public static function checkRequirements()
    {
    	// Options qu'on v�rifie mais qu'on ne peut poas modifier (magic quotes, etc...)
        if (ini_get('short_open_tag'))
            throw new Exception("Impossible de lancer l'application : l'option 'short_open_tag' de votre fichier 'php.ini' est � 'on'");
        
        // Options qu'on peut changer dynamiquement
        // ini_set('option � changer', 0));
    }
    
    private static function setupPaths()
    {
        // Initialise $fabRoot : la racine du framework
        self::$fabRoot=dirname(__FILE__) . DIRECTORY_SEPARATOR;

        // Cas particulier : lanc� en ligne de commande
        if (php_sapi_name()=='cli') 
        {
            ignore_user_abort(true);    // � mettre ailleurs
            set_time_limit(0);          // � mettre ailleurs

            // on r�cup�re �ventuellement la webroot � utiliser en second param�tre
            if (isset($_SERVER['argv'][2]))
                self::$root=self::$webRoot=$_SERVER['argv'][2];
            else
                self::$root=self::$webRoot=self::$fabRoot;

            // d�termine "l'url" demand�e, la query_string, etc.
            self::$url=$_SERVER['argv'][1];
            $pt=strpos(self::$url, '?');
            if ($pt!==false)
            {
                $_SERVER['QUERY_STRING']=substr(self::$url, $pt+1);
                self::$url=substr(self::$url, 0, $pt);
                parse_str($_SERVER['QUERY_STRING'], $_GET);
                $_REQUEST=$_GET;
            }
            else
                $_SERVER['QUERY_STRING']='';
            
            $_SERVER['REQUEST_METHOD']='GET';
            self::$fcInUrl=null;
            return;
        }

        // Path du script auquel a �chu la requ�te demand�e par l'utilisateur
        if (isset($_SERVER['SCRIPT_FILENAME']))
            $path=$_SERVER['SCRIPT_FILENAME'];
        else
            die("Impossible d'initialiser l'application, SCRIPT_FILENAME non disponible");
        
        // Nom du front controler = le nom du script qui traite la requ�te (index.php, debug.php...)
        self::$fcName=basename($path);
        
        // Path du r�pertoire web de l'application = le r�pertoire qui contient le front controler
        self::$webRoot=dirname($path) . DIRECTORY_SEPARATOR ;
        
        // Path de l'application = par convention, le r�pertoire parent du r�pertoire web de l'application
        self::$root= dirname(self::$webRoot) . DIRECTORY_SEPARATOR;
        
        // Url demand�e par l'utilisateur
        if (isset($_SERVER['REQUEST_URI']))
            self::$url=$_SERVER['REQUEST_URI'];
        else
            die("Impossible d'initialiser l'application, REQUEST_URI non disponible");

        // Pr�fixe de l'url : partie de l'url entre le nom du serveur et le nom du front controler
        if (false !== $pt=stripos(self::$url, self::$fcName))
        {
            // l'url demand�e contient le nom du front controler
            self::$realHome=substr(self::$url, 0, $pt);
            self::$home=self::$realHome.self::$fcName;
            self::$fcInUrl=true;
        }
        else
        {
            if (isset($_SERVER['ORIG_PATH_INFO']))
                $path=$_SERVER['ORIG_PATH_INFO'];
            else
                die("Impossible d'initialiser l'application : url redirig�e (sans front controler) mais ORIG_PATH_INFO non disponible");

            if (false=== $pt=strpos($path, self::$fcName))
                die("Impossible d'initialiser l'application : url redirig�e mais nom du script non trouv� dans ORIG_PATH_INFO");

            self::$fcInUrl=false;

            self::$home=self::$realHome=substr(self::$url,0,$pt);
        }

        // garantit que home et realHome contiennent toujours un slash final
        self::$realHome=rtrim(self::$realHome,'/').'/';
        self::$home=rtrim(self::$home,'/').'/';
        
        // ajuste self::url pour qu'elle ne contienne que le module/action demand� par l'utilisateur
        if(strncasecmp(self::$url,self::$home,strlen(self::$home)-1)!==0) // debug
        {
            var_dump(self::$url);
            echo '<hr />';
            var_dump(self::$home);
            die("erreur interne lors de l'examen de l'url");
        }
             
        if (strlen(self::$url)<strlen(self::$home))
            self::$url='';
        else
        {
            self::$url=substr(self::$url, strlen(self::$home)-1);
            if (false !== $pt=strpos(self::$url,'?'))
                self::$url=substr(self::$url,0,$pt);
        }
    }

    /**
     * V�rifie que l'url demand�e par l'utilisateur correspond � l'url r�elle de la page demand�e.
     * 
     * - si les smarturls sont activ�es et que l'adresse comporte le nom du script d'entr�e,
     * redirige l'utilisateur vers l'url sans nom du script
     * 
     * - si les smarturls sont d�sactiv�es et que l'adresse ne mentionne pas le nom du script
     * d'entr�e, redirige vers l'url comportant le nom du script
     * 
     * - si la "home page" est appell�e sans slash final, redirige l'utilisateur vers la home
     * page avec un slash (xxx/index.php -> xxx/index.php/)
     */
    private static function checkSmartUrls()
    {

//echo '<h1>FINAL</h1>';
//echo '<br /><big>';
//echo'self::$fabRoot : ', self::$fabRoot, '<br />';
//echo'self::$url : ', self::$url, '<br />';
//echo'self::fcName : ', self::$fcName, '<br />';
//echo'self::$webRoot : ', self::$webRoot, '<br />';
//echo'self::$root : ', self::$root, '<br />';
//echo'self::$realHome : ', self::$home, '<br />';
//echo'self::$home : ', self::$home, '<br />';
//echo 'host : ', Utils::getHost(), '<br />';
//echo 'query string : ', $_SERVER['QUERY_STRING'], '<br />';
//echo '</big>';

        if (!is_bool(self::$fcInUrl)) // fcName=null : mode cli, ignorer le test
            return;
            
        if (! Utils::isGet())  // on ne sait pas faire de redirect si on est en POST, inutile de tester
            return;
        
        $smartUrls=Config::get('smarturls',false);

        // fc dans l'url && SmartUrl=off -> url sans fc 
        if ($smartUrls && self::$fcInUrl)
        {
            if (false===$pt=strrpos(rtrim(self::$home,'/'),'/'))
                die("redirection impossible, valeur erron�e pour 'home'");

            $url=substr(self::$home, 0, $pt+1).ltrim(self::$url,'/');
            
            if (! empty($_SERVER['QUERY_STRING'])) $url.='?' . $_SERVER['QUERY_STRING'];
            self::redirect($url, true);
        }

        // pas de fc dans l'url && smarturls=on -> url avec fc
        if (!$smartUrls && !self::$fcInUrl)
        {
            $url=self::$home.self::$fcName.self::$url;
            if (! empty($_SERVER['QUERY_STRING'])) $url.='?' . $_SERVER['QUERY_STRING'];
            self::redirect($url, true);
        }

        if (self::$url==='')
        {
            if (self::$fcInUrl)
            { 
                $url=self::$home.self::$url;
                if (! empty($_SERVER['QUERY_STRING'])) $url.='?' . $_SERVER['QUERY_STRING'];
                self::redirect($url, true);
            }
            else
                self::$url='/';
        }
    }   
     
    /**
     * Initialise et lance l'application
     * @param string $path Path complet du script appellant (utiliser __FILE__)
     */
    public static function setup($env='')
    {
        self::checkRequirements();
        self::setupPaths();

        spl_autoload_register(array(__CLASS__, 'autoload'));
                
//        xdebug_enable();
//        xdebug_start_trace('c:/temp/profiles/trace', XDEBUG_TRACE_COMPUTERIZED);
//        xdebug_start_trace('c:/temp/profiles/trace');
        self::$env=($env=='' ? 'normal' : $env);

        //Debug::log('Xdebug is enabled : %s', xdebug_is_enabled()?'true':'false');
         
        $fab_start_time=microtime(true);
    
        // Charge les fonctions utilitaires
        //require_once self::$fabRoot.'core/utils/Utils.php'; 
        
        // Fonctions de d�boggage // TODO : seulement si mode debug
        //require_once self::$fabRoot.'core/debug/Debug.php';
        Debug::notice('Initialisation du framework en mode %s', $env ? $env : 'normal');
        
        // Charge le gestionnaire de configuration
        //require_once self::$fabRoot.'core/config/Config.php'; 
        
        // Charge la configuration de base (fichiers config.php application/fab/environnement)
        self::setupBaseConfig();                    // Modules requis : Debug
                                                    // variables utilis�es : fabRoot, root, env
                        
        // Initialise le cache                      // Modules requis : Config, Utils, Debug
        self::setupCache();                         // variables utilis�es : fabRoot, root             

        // D�finit le fuseau horaire utilis� par les fonctions date de php
        self::setupTimeZone();                      // Modules requis : Config
        
        // Charge la configuration g�n�rale (fichiers general.yaml fab/application/environnement)
        self::setupGeneralConfig();                 // Modules requis : Debug, Config
                                                    // variables utilis�es : fabRoot, root, env

        self::checkSmartUrls();

        if (config::get('debug'))
            define('debug', true);
        else
            define('debug', false);
    
//echo '<h1>FINAL</h1>';
//echo '<br /><big>';
//echo'self::$fabRoot : ', self::$fabRoot, '<br />';
//echo'self::$url : ', self::$url, '<br />';
//echo'self::fcName : ', self::$fcName, '<br />';
//echo'self::$webRoot : ', self::$webRoot, '<br />';
//echo'self::$root : ', self::$root, '<br />';
//echo'self::$realHome : ', self::$home, '<br />';
//echo'self::$home : ', self::$home, '<br />';
//echo '</big>';

        debug && Debug::notice("Module/action demand�s par l'utilisateur : " . self::$url);
        
        // R�pare les tableaux $_GET, $_POST et $_REQUEST
        Utils::repairGetPostRequest();
        
        // Charge les routes - routes.yaml -
        debug && Debug::log('Initialisation du routeur');
        //require_once self::$fabRoot.'core/routing/Routing.php'; 
        self::setupRoutes();
        
        // Initialise le gestionnaire de templates
        debug && Debug::log('Initialisation du gestionnaire de templates');
        //require_once self::$fabRoot.'core/template/Template.php';
        Template::setup();
        
        // Initialise le gestionnaire de modules
        debug && Debug::log('Initialisation du gestionnaire de modules');
        //require_once self::$fabRoot.'core/module/Module.php'; 
        
        // Initialise le gestionnaire de s�curit�
        debug && Debug::log('Initialisation du gestionnaire de s�curit�');
        //require_once self::$fabRoot.'modules/NoSecurity/NoSecurity.php'; // uniquement pour les classes qui impl�mentent 
        //require_once self::$fabRoot.'core/user/User.php'; 

        User::$user=Module::loadModule(Config::get('security.handler'));
        //User::$user->rights=Config::get('rights');

        // D�finit les param�tres de session, mais sans la d�marrer (fait par Module::Execute seulement si n�cessaire) 
//        session_name(Config::get('sessions.id'));
//        session_set_cookie_params(Config::get('sessions.lifetime'), self::$home);
////        session_set_cookie_params(Config::get('sessions.lifetime'));
//        session_cache_limiter('none');
        //Runtime::startSession();

        // Initialise le gestionnaire d'exceptions
        debug && Debug::log("Initialisation du gestionnaire d'exceptions");
        //require_once self::$fabRoot.'core/exception/ExceptionManager.php';
        self::setupExceptions(); 
        
        // Includes suppl�mentaires
        // TODO: �crire un class manager pour ne pas inclure syst�matiquement tout (voir du cot� du gestionnaire de modules)
        //require_once self::$fabRoot.'core/database/Database.php';
//        require_once self::$fabRoot.'modules'.DIRECTORY_SEPARATOR.'DatabaseModule/DatabaseModule.php';
//		require_once self::$fabRoot.'modules'.DIRECTORY_SEPARATOR.'CartModule/CartModule.php';
//		
//        require_once self::$fabRoot.'modules'.DIRECTORY_SEPARATOR.'TaskManager/TaskManager.php';
//        require_once self::$fabRoot.'core/helpers/TextTable/TextTable.php';

        /**
         * Charge les fichiers de configuration de base de donn�es (db.yaml, db.
         * debug.yaml...) dans la configuration en cours.
         * 
         * L'ordre de chargement est le suivant :
         * 
         * - fichier db.yaml de fab (si existant)
         * 
         * - fichier db.$env.yaml de fab (si existant)
         * 
         * - fichier db.yaml de l'application (si existant)
         * 
         * - fichier db.$env.yaml de l'application (si existant)
         */
        debug && Debug::log("Chargement de la configuration des bases de donn�es");
        if (file_exists($path=Runtime::$fabRoot.'config' . DIRECTORY_SEPARATOR . 'db.yaml'))
            Config::load($path, 'db');
        if (file_exists($path=Runtime::$root.'config' . DIRECTORY_SEPARATOR . 'db.yaml'))
            Config::load($path, 'db');
        
        if (!empty(Runtime::$env))   // charge la config sp�cifique � l'environnement
        {
            if (file_exists($path=Runtime::$fabRoot.'config'.DIRECTORY_SEPARATOR.'db.' . Runtime::$env . '.yaml'))
                Config::load($path, 'db');
            if (file_exists($path=Runtime::$root.'config'.DIRECTORY_SEPARATOR.'db.' . Runtime::$env . '.yaml'))
                Config::load($path, 'db');
        }
        
        // Dispatch l'url
$fab_init_time=microtime(true);
        debug && Debug::log("Lancement de l'application");
        Routing::dispatch(self::$url);

        self::shutdown();
    }
    
    // Modules requis : Debug
    // variables utilis�es : fabRoot, root, env
    private static function setupBaseConfig()
    {
        Debug::log('Chargement de la configuration initiale');
        require_once(self::$fabRoot . 'config' . DIRECTORY_SEPARATOR . 'config.php');
        if (file_exists($path=self::$root . 'config' . DIRECTORY_SEPARATOR . 'config.php'))
        {
            Debug::log('Chargement de %s', $path);
            require_once $path;
        }
        
        if (!empty(self::$env))   // charge la config sp�cifique � l'environnement
        {
            if (file_exists($path=self::$fabRoot.'config'.DIRECTORY_SEPARATOR.'config.' . self::$env . '.php'))
            {
                Debug::log('Chargement de %s', $path);
                require_once $path;
            }
            if (file_exists($path=self::$root.'config'.DIRECTORY_SEPARATOR.'config.' . self::$env . '.php'))
            {
                Debug::log('Chargement de %s', $path);
                require_once $path;
            }
        }
    }

    // Modules requis : Debug, Config
    // variables utilis�es : fabRoot, root, env
    private static function setupGeneralConfig()
    {
        Debug::log("Chargement de la configuration g�n�rale");
        Config::load(self::$fabRoot.'config' . DIRECTORY_SEPARATOR . 'general.yaml');
        if (file_exists($path=self::$root.'config' . DIRECTORY_SEPARATOR . 'general.yaml'))
            Config::load($path);

        if (!empty(self::$env))   // charge la config sp�cifique � l'environnement
        {
            if (file_exists($path=self::$fabRoot.'config'.DIRECTORY_SEPARATOR.'general.' . self::$env . '.yaml'))
                Config::load($path);
            if (file_exists($path=self::$root.'config'.DIRECTORY_SEPARATOR.'general.' . self::$env . '.yaml'))
                Config::load($path);
        }
    }
     
    // Modules requis : Config, Utils, Debug
    // variables utilis�es : fabRoot, root
    private static function setupCache()
    {
        if (Config::get('cache.enabled'))
        {
            //require_once self::$fabRoot.'core/cache/Cache.php';
            
            // D�termine le nom de l'application
            $appname=basename(self::$root);

            // D�termine le path de base du cache
            if (is_null($path=Config::get('cache.path')))
            {
                $path=Utils::getTempDirectory();
            }
            else
            {
                if (Utils::isRelativePath($path))
                    $path=Utils::makePath(self::$root, $path);
                $path=Utils::cleanPath($path);
            }
            
            // D�termine le path du cache de l'application et de fab
            $path.=DIRECTORY_SEPARATOR.'fabcache'.DIRECTORY_SEPARATOR.$appname;
            $appPath=$path.DIRECTORY_SEPARATOR.self::$env.DIRECTORY_SEPARATOR.'app';
            $fabPath=$path.DIRECTORY_SEPARATOR.self::$env.DIRECTORY_SEPARATOR.'fab';
            
            // Cr��e les caches
            if (Cache::addCache(self::$root, $appPath) && Cache::addCache(self::$fabRoot, $fabPath)) 
                Debug::log('Cache initialis�. Application : %s, framework : %s', $appPath, $fabPath);
            else
            {
                Config::set('cache.enabled', false);
                Debug::warning('Cache d�sactiv� : impossible d\'utiliser les r�pertoires indiqu�s (application : %s, framework : %s', $appPath, $fabPath);
            }
        }
        else
            Debug::notice('Le cache est d�sactiv� dans la config');
    }
    
    // Modules requis : Config
    private static function setupTimeZone()
    {
        $timeZone=Config::get('timezone');
        date_default_timezone_set($timeZone && $timeZone!='default' ? $timeZone : @date_default_timezone_get());
    }
    
    private static function setupRoutes()
    {
        $defaultRoutes=Config::get('defaultroutes'); // indique s'il faut charger ou non les routes de fab

        if (!empty(self::$env))   // charge les routes sp�cifiques � l'environnement (en premier pour qu'elles soient prioritaiers)
        {
            if (file_exists($path=self::$root.'config'.DIRECTORY_SEPARATOR.'routes.' . self::$env . '.yaml'))
                Config::load($path, 'routes', 'Routing::transform');
            if ($defaultRoutes!=0 && file_exists($path=self::$fabRoot.'config'.DIRECTORY_SEPARATOR.'routes.' . self::$env . '.yaml'))
                Config::load($path, 'routes', 'Routing::transform');
        }

        if (file_exists($path = self::$root.'config' . DIRECTORY_SEPARATOR . 'routes.yaml'))
            Config::load($path, 'routes', 'Routing::transform');

        switch ($defaultRoutes) 
        {
            case 0: break; // on ne charge rien
            case 1:        // routes minimales
                Config::load(self::$fabRoot.'config' . DIRECTORY_SEPARATOR . 'routes.minimal.yaml', 'routes', 'Routing::transform');
                break;
            case 2:        // routes �tendues
                Config::load(self::$fabRoot.'config' . DIRECTORY_SEPARATOR . 'routes.complete.yaml', 'routes', 'Routing::transform');
                break;
            default:
                throw new Exception('Valeur incorrecte pour la cl� defaultRoutes de votre fichier de config (0, 1 ou 2 attendus)');            
        }

        // TODO : revoir la gestion des 2 fichiers routes.yaml
        // chaque fichier est tri�, mais l'ensemble ne l'est pas.
        // on inclut le notre en dernier, car on "sait" que nos r�gles sont tr�s larges
        // mais cela rel�ve du hack
    	
    }
    
    private static function setupExceptions()
    {
        set_exception_handler(array('ExceptionManager','handleException'));
        
        // Transforme les erreurs standards en exceptions fatales (nb : toutes, y compris les warning et les e_strict)
        set_error_handler(array('ExceptionManager', 'handleError'), (E_ALL & !E_WARNING) | E_STRICT); // TODO config
    }
    
    public static function shutdown()
    {
        global $start_time;

        if (Config::get('showdebug'))
        {        
            debug && Debug::log("Application termin�e");
            Debug::log('Temps total d\'ex�cution : %s secondes', sprintf('%.3f', (microtime(true) - $start_time )));
            Debug::showBar();
        }
        exit(0);
    }
    
    // TODO: php met comme last-modified la date du script php appell�
    // comme on a un seul script (FC) toutes les pages ont une date antique
    // trouver quelque chose (date du layout, date des templates...)
    // on pourrait avoir une fonction setlastmodified appell�e (entre autres)
    // par Template::Run avec la date du template, la date de la table dans un loop
    // now() si on fait un loop sur une s�lection, etc.
    // A chaque fois la fonction ferait : si nouvelle date sup�rieure � la pr�c�dente
    // on la prends.
    
    
    /**
     * D�mare la session si ce n'est pas d�j� fait
     */
    public static function startSession()
    {
        if (session_id()=='') // TODO : utiliser un objet global 'Session' pour le param�trage
        {
            session_name(Config::get('sessions.id'));
            session_set_cookie_params(Config::get('sessions.lifetime'), self::$home);
            //session_set_cookie_params(Config::get('sessions.lifetime'));
            session_cache_limiter('none');

            session_start();
        }
            
    }
    
    /**
     * Redirige l'utilisateur vers l'url indiqu�e.
     * 
     * Par d�faut, l'url indiqu�e doit $etre de la forme /module/action et sera
     * automatiquement convertie en fonctions des r�gles de routage pr�sentes
     * dans la configuration.
     * 
     * Pour rediriger l'utilisateur vers une url d�j� construite (et supprimer
     * le routage), indiquer true comme second param�tre.
     * 
     * La page de redirection g�n�r�e contient � la fois :
     * <li>un ent�te http : 'location: url'
     * <li>un meta http-equiv 'name="refresh", content="delay=0;url=url"'
     * <li>un script javascript : window.location="url";
     * 
     * @param string $url l'url vers laquelle l'utilisateur doit �tre redirig�
     * @param boolean $noRouting (optionnel, defaut : false) indiquer 'true'
     * pour d�sactiver le routage.
     */
    public static function redirect($url, $noRouting=false)
    {
        if ($noRouting && (preg_match('~^[a-z]{3,6}:~',$url)==0))
            $url=Utils::getHost() . $url;
        else
            $url=Routing::linkFor($url, true);
        if (empty($url))
        {
            echo 'ERREUR : redirection vers une url vide<br />', "\n";
            return;
        }
        header("Location: $url");
        
        $url=htmlentities($url);
        echo sprintf
        (
            '<html>' .
            '<head>' . 
            '<meta http-equiv="refresh" content="0;url=%s"/>' .
            '<script type="text/javascript">' .
            'window.location="%s";' .
            '</script>' .
            '</head>' .
            '<body>' .
            '<p>This page has moved to <a href="%s">%s</a></p>' .
            '</body>' .
            '</html>',
            $url, $url, $url, $url
        );

        exit(0);
        
        Runtime::shutdown();
        
    }

    /**
     * Essaie de charger le fichier qui contient la d�finition de la classe 
     * indiqu�e en param�tre.
     * 
     * Cette fonction n'est pas destin�e � �tre appell�e directement : c'est une
     * fonction magique que php appelle lorsqu'il ne trouve pas la d�finition d'une
     * classe demand�e.
     * 
     * Pour que cette fonction marche, il faut que les fichiers de classes soient 
     * nomm�s selon la convention NomDeClasse.class.php
     * 
     * @param string $className le nom de la classe qui n'a pas �t� trouv�e
     */
    public static function autoload($class)
    {
        static $core='';
        static $dir=array
        (
            'Utils'=>'core/utils/Utils.php',
            'Cache'=>'core/cache/Cache.php',
            'Debug'=>'core/debug/Debug.php',
            'Config'=>'core/config/Config.php',
            'Routing'=>'core/routing/Routing.php',
            'Template'=>'core/template/Template.php',
            'User'=>'core/user/User.php',
            'Module'=>'core/module/Module.php',
            'NoSecurity'=>'modules/NoSecurity/NoSecurity.php',
            'ExceptionManager'=>'core/exception/ExceptionManager.php',
            'Database'=>'core/database/Database.php',
            'DatabaseModule'=>'modules/DatabaseModule/DatabaseModule.php',
            'TextTable'=>'core/helpers/TextTable/TextTable.php',
            'BisDatabase'=>'core/database/BisDatabase.php',
            'XapianDatabaseDriver'=>'core/database/XapianDatabase.php',
            'XapianDatabaseDriver2'=>'core/database/XapianDatabase2.php',
            'DatabaseStructure'=>'core/database/DatabaseStructure.php',
            'TemplateCompiler'=>'core/template/TemplateCompiler.php',
            'TemplateCode'=>'core/template/TemplateCode.php',
            'TemplateEnvironment'=>'core/template/TemplateEnvironment.php',
            'TaskManager'=>'modules/TaskManager/TaskManager.php',
        
        );
        if (!isset($dir[$class])) return;
        $path=self::$fabRoot. strtr($dir[$class],'/', DIRECTORY_SEPARATOR);
//        echo "__autoload('$class') -> require_once('$path')<br />\n";
        require_once($path);
    }
    
}


?>