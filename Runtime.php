<?php
//while(ob_get_level()) ob_end_clean(); // TODO: utile ?


/**
 * Vocabulaire :
 * le site web = la partie visible sur internet (la page d'index, les
 * images, css, javascript, etc.), plus exactement, il s'agit de tout le
 * contenu qui peut être appellé au travers d'un navigateur
 * ->$webroot= le path du répertoire correspondant
 * 
 * l'application = l'ensemble de l'application, c'est à dire le site web et
 * toutes les autres librairies, scripts, fichiers de configuration,
 * données, etc. dont le site a besoin. Par convention, l'application
 * commence au répertoire au-dessus de webroot.
 * ->$root = le path du répertoire de l'application
 * remarque : une même application peut contenir plusieurs sites web (par
 * exemple web visible et site d'administration)
 * $root\web
 *      \admin
 * 
 * home : le préfixe commun à toutes les urls pour un site donné
 */
 
function test($url, $title='')
{
	if ($title) echo "<br /><h2><strong>$title</strong></h2>";
    echo "<li>Link for [$url]<br />";
    $h=Routing::linkFor($url);
    if ($h=='') $h='Aucune route';
    echo '<span style="color: red">'.$h.'</span>';
    echo '</li>';
}
class Runtime
{
    /**
     * @var string Path du répertoire racine du site web contenant la page
     * demandée par l'utilisateur (le front controler). webRoot est forcément un
     * sous- répertoire de {@link $root}. webRoot contient toujours un slash
     * (sous linux) ou un anti-slash (sous windows) à la fin.
     */
    public static $webRoot='';
    
    /**
     * @var string Path du répertoire racine de l'application. Par convention,
     * il s'agit toujours du répertoire parent de {@link $webroot}.$root
     * contient toujours un slash (sous linux) ou un anti-slash (sous windows) à
     * la fin.
     * @access public
     */
    public static $root='';
    
    /**
     * @var string Path du répertoire racine du framework. $fabRoot contient
     * toujours un slash (sous linux) ou un anti-slash (sous windows) à la fin.
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
     * Lorsque les smart urls sont désactivées, home contiendra toujours le nom
     * du front controler utilisé (par exemple /web/site1/index.php).
     */
    public static $home='';
    
    /**
     * idem home mais ne contient jamais le nom du FC. Utilisé par Routing::
     * linkFor lorsque l'url correspond à un fichier existant du site.
     */
    public static $realHome='';
    
    /**
     * @var string Adresse relative de la page demandée par l'utilisateur
     */
    public static $url='';
    

    public static $env='';
    
    private static $includePath=array();
    
    public static function addIncludePath($path)
    {
    	
    }
    
    /**
     * Initialise et lance l'application
     * @param string $path Path complet du script appellant (utiliser __FILE__)
     */
    public static function setup($env='')
    {
//        xdebug_enable();
//        xdebug_start_trace('c:/temp/profiles/trace', XDEBUG_TRACE_COMPUTERIZED);
//        xdebug_start_trace('c:/temp/profiles/trace');
        self::$env=$env;
        
        //Debug::log('Xdebug is enabled : %s', xdebug_is_enabled()?'true':'false');
         
        $fab_start_time=microtime(true);
    
        // Initialise $fabRoot : la racine du framework
        self::$fabRoot=dirname(__FILE__) . DIRECTORY_SEPARATOR;
        
        // Charge les fonctions utilitaires
        require_once self::$fabRoot.'Utils.php';
        
        // Fonctions de déboggage // TODO : seulement si mode debug
        require_once self::$fabRoot.'Debug.php';
        Debug::notice('Initialisation du framework en mode %s', $env ? $env : 'normal');
        
        // cas spécial, lancé en ligne de commande
        if (php_sapi_name()=='cli') 
        {
            ignore_user_abort(true);
            set_time_limit(0);

            // on récupère éventuellement la webroot à utiliser en second paramètre
            if (isset($_SERVER['argv'][2]))
                self::$root=self::$webRoot=$_SERVER['argv'][2];
            else
                self::$root=self::$webRoot=self::$fabRoot;
        }
        else
        {
            // Initialise $webRoot : racine du site
            $caller=Utils::callerScript();
            self::$webRoot=dirname($caller) . DIRECTORY_SEPARATOR;
    
            // Initialise $root : racine de l'application (répertoire parent de webroot)
            self::$root=realpath(self::$webRoot.DIRECTORY_SEPARATOR.'..') . DIRECTORY_SEPARATOR;
        }
                
        // Charge le gestionnaire de configuration
        require_once self::$fabRoot.'Config.php'; 
        
        // Charge la configuration de base (fichiers config.php application/fab/environnement)
        self::setupBaseConfig();                    // Modules requis : Debug
                                                    // variables utilisées : fabRoot, root, env
                        
        // Initialise le cache                      // Modules requis : Config, Utils, Debug
        self::setupCache();                         // variables utilisées : fabRoot, root             

        // Définit le fuseau horaire utilisé par les fonctions date de php
        self::setupTimeZone();                      // Modules requis : Config
        
        // Charge la configuration générale (fichiers general.yaml fab/application/environnement)
        self::setupGeneralConfig();                 // Modules requis : Debug, Config
                                                    // variables utilisées : fabRoot, root, env

        if (config::get('debug'))
            define('debug', true);
        else
            define('debug', false);
            
        // Initialise $url : l'adresse relative de la page demandée par l'utilisateur
        
        // TODO : faire en sorte que ça fonctionne sous IIS
        
        // Initialise $home : la "différence" entre server[DOCUMENT_ROOT] et webroot
        self::$realHome=self::$home=strtr(substr(self::$webRoot, strlen($_SERVER['DOCUMENT_ROOT'])), DIRECTORY_SEPARATOR, '/');

        // cas spécial, lancé en ligne de commande
        if (php_sapi_name()=='cli')
        { 
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
            

        }    
        else
        {
            self::$url=$_SERVER['REQUEST_URI'];
            self::$url=substr(self::$url, strlen(self::$home)-1); // avec smarturls

            // TODO : ne marche pas, à réétudier
            if (($pt=strpos(self::$url,'?')) !== false) self::$url=substr(self::$url, 0, $pt);
            if (self::$url=='') self::$url='/';
    
            // Redirige l'utilisateur si l'url qu'il a demandé ne correspond pas à l'option smarturls
            $fcName=basename($caller);
                
            // Si les smarturls sont actives (pas de FC) et que l'url mentionne le FC, redirection
            if (Config::get('smarturls'))
            {
                $fcName='/' . $fcName;
                if (Utils::isGet())  // on ne sait pas faire de redirect si on est en POST
                {
                    if (strncasecmp(self::$url, $fcName, strlen($fcName))==0) // l'url mentionne le FC
                    {
                        $h=rtrim(self::$home,'/') . substr(self::$url, strlen($fcName));
                        if (! empty($_SERVER['QUERY_STRING'])) $h.='?' . $_SERVER['QUERY_STRING'];
                        self::redirect($h, true);
                    }
                }
            }
    
            // Si les smarturls sont désactivées (indiquer le FC) et que l'url ne mentionne pas le FC, redirection
            else
            {
                self::$home .= $fcName . '/';
                if (Utils::isGet())  // on ne sait pas faire de redirect si on est en POST
                {
                    if (strncasecmp(self::$url, '/' . $fcName, strlen($fcName)+1)!=0) // l'url ne mentionne pas le fc
                    {
                        $h=self::$home . trim(self::$url,'/');
                        if (! empty($_SERVER['QUERY_STRING'])) $h.='?' . $_SERVER['QUERY_STRING'];
                        self::redirect($h, true);
                    }
                }
                self::$url=substr(self::$url, 1+strlen($fcName));
            }
        }
        debug && Debug::notice("Module/action demandés par l'utilisateur : " . self::$url);
        
        // Répare les tableaux $_GET, $_POST et $_REQUEST
        Utils::repairGetPostRequest();
        
        // Charge les routes - routes.yaml -
        debug && Debug::log('Initialisation du routeur');
        require_once self::$fabRoot.'Routing.php'; 
        self::setupRoutes();
        
        // Initialise le gestionnaire de templates
        debug && Debug::log('Initialisation du gestionnaire de templates');
        require_once self::$fabRoot.'Template.php'; 
        Template::setup();
        
        // Initialise le gestionnaire de modules
        debug && Debug::log('Initialisation du gestionnaire de modules');
        require_once self::$fabRoot.'Module.php'; 
        
        // Initialise le gestionnaire de sécurité
        debug && Debug::log('Initialisation du gestionnaire de sécurité');
        require_once self::$fabRoot.'modules/NoSecurity/NoSecurity.php'; // uniquement pour les classes qui implémentent 
        require_once self::$fabRoot.'User.php'; 

        User::$user=Module::loadModule(Config::get('security.handler'));
        //User::$user->rights=Config::get('rights');

        // Définit les paramètres de session, mais sans la démarrer (fait par Module::Execute seulement si nécessaire) 
//        session_name(Config::get('sessions.id'));
//        session_set_cookie_params(Config::get('sessions.lifetime'), self::$home);
////        session_set_cookie_params(Config::get('sessions.lifetime'));
//        session_cache_limiter('none');
        Runtime::startSession();
        

        // Initialise le gestionnaire d'exceptions
        debug && Debug::log("Initialisation du gestionnaire d'exceptions");
        require_once self::$fabRoot.'ExceptionManager.php';
        self::setupExceptions(); 

        // Includes supplémentaires
        // TODO: écrire un class manager pour ne pas inclure systématiquement tout
        require_once self::$fabRoot.'modules'.DIRECTORY_SEPARATOR.'BisDatabase'.DIRECTORY_SEPARATOR.'BisDatabase.php';        
        require_once self::$fabRoot.'BisWeb.php';

require_once self::$fabRoot.'modules'.DIRECTORY_SEPARATOR.'TaskManager/TaskManager.php';

        // Dispatch l'url
$fab_init_time=microtime(true);
        debug && Debug::log("Lancement de l'application");
        Routing::dispatch(self::$url);

        self::shutdown();
    }
    
    // Modules requis : Debug
    // variables utilisées : fabRoot, root, env
    private static function setupBaseConfig()
    {
        Debug::log('Chargement de la configuration initiale');
        require_once(self::$fabRoot . 'config' . DIRECTORY_SEPARATOR . 'config.php');
        if (file_exists($path=self::$root . 'config' . DIRECTORY_SEPARATOR . 'config.php'))
            require_once $path;

        if (!empty(self::$env))   // charge la config spécifique à l'environnement
        {
            if (file_exists($path=self::$root.'config'.DIRECTORY_SEPARATOR.'config.' . self::$env . '.php'))
                require_once $path;
            if (file_exists($path=self::$root.'config'.DIRECTORY_SEPARATOR.'config.' . self::$env . '.php'))
                require_once $path;
        }
    }

    // Modules requis : Debug, Config
    // variables utilisées : fabRoot, root, env
    private static function setupGeneralConfig()
    {
        Debug::log("Chargement de la configuration générale");
        Config::load(self::$fabRoot.'config' . DIRECTORY_SEPARATOR . 'general.yaml');
        if (file_exists($path=self::$root.'config' . DIRECTORY_SEPARATOR . 'general.yaml'))
            Config::load($path);

        if (!empty(self::$env))   // charge la config spécifique à l'environnement
        {
            if (file_exists($path=self::$fabRoot.'config'.DIRECTORY_SEPARATOR.'general.' . self::$env . '.yaml'))
                Config::load($path);
            if (file_exists($path=self::$root.'config'.DIRECTORY_SEPARATOR.'general.' . self::$env . '.yaml'))
                Config::load($path);
        }
    }
     
    // Modules requis : Config, Utils, Debug
    // variables utilisées : fabRoot, root
    private static function setupCache()
    {
        if (Config::get('cache.enabled'))
        {
            require_once self::$fabRoot.'Cache.php';
            
            // Cache de l'application
            $path=Config::get('cache.path');
            if (Utils::isRelativePath($path))
                $path=Utils::makePath(self::$root, $path);
            $path=Utils::cleanPath($path);
            Cache::addCache(self::$root, $path);
            
            // Cache du framework
            $fabPath=Config::get('cache.pathforfab');
            if (Utils::isRelativePath($fabPath))
                $fabPath=Utils::makePath(self::$fabRoot, $fabPath);
            $fabPath=Utils::cleanPath($fabPath);
            if ($path==$fabPath && self::$root!=self::$fabRoot) // root==fabRoot pour un script présent dans fab/bin
                throw new Exception("L'application et le framework doivent utiliser des caches différents (cache de l'application : [$path], cache du framework : [$fabPath])");
                
            Cache::addCache(self::$fabRoot, $fabPath);
            Debug::log('Cache initialisé. Application : %s, framework : %s', $path, $fabPath);
        }
        else
            Debug::notice('Cache désactivé');
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

        if (!empty(self::$env))   // charge les routes spécifiques à l'environnement (en premier pour qu'elles soient prioritaiers)
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
            case 2:        // routes étendues
                Config::load(self::$fabRoot.'config' . DIRECTORY_SEPARATOR . 'routes.complete.yaml', 'routes', 'Routing::transform');
                break;
            default:
                throw new Exception('Valeur incorrecte pour la clé defaultRoutes de votre fichier de config (0, 1 ou 2 attendus)');            
        }

        // TODO : revoir la gestion des 2 fichiers routes.yaml
        // chaque fichier est trié, mais l'ensemble ne l'est pas.
        // on inclut le notre en dernier, car on "sait" que nos règles sont très larges
        // mais cela relève du hack
    	
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

        if (debug)
        {        
            Debug::log("Application terminée");
            Debug::log('Temps total d\'exécution : %s secondes', sprintf('%.3f', (microtime(true) - $start_time )));
            Debug::showBar();
        }
        exit(0);
    }
    
    // TODO: php met comme last-modified la date du script php appellé
    // comme on a un seul script (FC) toutes les pages ont une date antique
    // trouver quelque chose (date du layout, date des templates...)
    // on pourrait avoir une fonction setlastmodified appellée (entre autres)
    // par Template::Run avec la date du template, la date de la table dans un loop
    // now() si on fait un loop sur une sélection, etc.
    // A chaque fois la fonction ferait : si nouvelle date supérieure à la précédente
    // on la prends.
    
    
    // TODO: faire doc
    public static function startSession()
    {
//    echo "<li>appel de startSes</li>";
        // TODO: à revoir, startSession est appellé par Module::execute et peut donc être appellé plusieurs fois si on chaine des modules.
        // Démare la session si ce n'est pas déjà fait
        if (session_id()=='') // TODO : utiliser un objet global 'Session' pour le paramétrage
        {
        session_name(Config::get('sessions.id'));
        session_set_cookie_params(Config::get('sessions.lifetime'), self::$home);
//        session_set_cookie_params(Config::get('sessions.lifetime'));
        session_cache_limiter('none');

            session_start();
            //echo "session démarrée. Path=" . session_save_path() . '/' . session_id();
        }
            
    }
    
    /**
     * Redirige l'utilisateur vers l'url indiquée.
     * 
     * Par défaut, l'url indiquée doit $etre de la forme /module/action et sera
     * automatiquement convertie en fonctions des règles de routage présentes
     * dans la configuration.
     * 
     * Pour rediriger l'utilisateur vers une url déjà construite (et supprimer
     * le routage), indiquer true comme second paramètre.
     * 
     * La page de redirection générée contient à la fois :
     * <li>un entête http : 'location: url'
     * <li>un meta http-equiv 'name="refresh", content="delay=0;url=url"'
     * <li>un script javascript : window.location="url";
     * 
     * @param string $url l'url vers laquelle l'utilisateur doit être redirigé
     * @param boolean $noRouting (optionnel, defaut : false) indiquer 'true'
     * pour désactiver le routage.
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
        Runtime::shutdown();
    }

            
}

/**
 * Essaie de charger le fichier qui contient la définition de la classe 
 * indiquée en paramètre.
 * 
 * Cette fonction n'est pas destinée à être appellée directement : c'est une
 * fonction magique que php appelle lorsqu'il ne trouve pas la définition d'une
 * classe demandée.
 * 
 * Pour que cette fonction marche, il faut que les fichiers de classes soient 
 * nommés selon la convention NomDeClasse.class.php
 * 
 * @param string $className le nom de la classe qui n'a pas été trouvée
 */
//function __autoload($className)
//{
//    $path="$className.class.php";
//    echo "__autoload('$className') -> require_once('$path')<br />\n";
//    require_once($path);
//}

?>