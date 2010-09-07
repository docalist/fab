<?php
/**
 * @package     fab
 * @subpackage  runtime
 * @author      Daniel Ménard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id$
 */

/**
 * Le coeur de fab
 *
 * Vocabulaire : le site web = la partie visible sur internet (la page d'index,
 * les images, css, javascript, etc.), plus exactement, il s'agit de tout le
 * contenu qui peut être appellé au travers d'un navigateur - >$webroot= le path
 * du répertoire correspondant
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
 *
 * @package     fab
 * @subpackage  runtime
 */

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
    private static $fcInUrl;
    public static $fcName;

    public static $env='';

    public static $queryString=''; // initialisé par repairgetpost

    public static $baseConfig=null;

    /**
     * Indique si fab est en cours d'initialisation. Ce flag est mis à true
     * lorsque {@link setup()} est appellée et le reste tant qu'on n'a pas
     * chargé la configuration de base du site (config.php, general.config).
     *
     * @var bool
     */
    public static $initializing=false;

    /**
     * La requête correspondant à l'url demandée par le navigateur
     *
     * @var Request
     */
    public static $request=null;

    // Vérifie qu'on a l'environnement minimum nécessaire à l'exécution de l'application
    // et que la configuration de php est "correcte"
    public static function checkRequirements()
    {
        // Options qu'on vérifie mais qu'on ne peut poas modifier (magic quotes, etc...)
        if (ini_get('short_open_tag'))
            throw new Exception("Impossible de lancer l'application : l'option 'short_open_tag' de votre fichier 'php.ini' est à 'on'");

        // Options qu'on peut changer dynamiquement
        // ini_set('option à changer', 0));
    }

    private static function setupPaths()
    {
        // Initialise $fabRoot : la racine du framework
        self::$fabRoot=dirname(__FILE__) . DIRECTORY_SEPARATOR;

        // Path du script auquel a échu la requête demandée par l'utilisateur
        if (isset($_SERVER['SCRIPT_FILENAME']))
            $path=$_SERVER['SCRIPT_FILENAME'];
        else
            die("Impossible d'initialiser l'application, SCRIPT_FILENAME non disponible");

        // Apparemment sous windows+apache+sapi, le path peut être parfois retourné avec des / et non pas des \
        // Du coup, le cache dis que ce n'est pas la bonne root.
        // On corrige
        $path=strtr($path, '/', DIRECTORY_SEPARATOR);

        // Nom du front controler = le nom du script qui traite la requête (index.php, debug.php...)
        self::$fcName=basename($path);

        // Path du répertoire web de l'application = le répertoire qui contient le front controler
        self::$webRoot=dirname($path) . DIRECTORY_SEPARATOR ;

        // Path de l'application = par convention, le répertoire parent du répertoire web de l'application
        self::$root= dirname(self::$webRoot) . DIRECTORY_SEPARATOR;

        // Initialisation en mode CLI (ligne de commande)
        if (php_sapi_name()=='cli')
        {
            ignore_user_abort(true);    // à mettre ailleurs
            set_time_limit(0);          // à mettre ailleurs

            // Détermine le module, l'action et les paramètres à exécuter (Arg 1)
            self::$url = isset($_SERVER['argv'][1]) ? $_SERVER['argv'][1] : '/';

            $pt=strpos(self::$url, '?');
            if ($pt!==false)
            {
                echo 'query string, pt=', $pt, "\n";
                $_SERVER['QUERY_STRING']=substr(self::$url, $pt+1);
                self::$url=substr(self::$url, 0, $pt);
                echo 'url=', self::$url, "<br />\n";
                parse_str($_SERVER['QUERY_STRING'], $_GET);
                $_REQUEST=$_GET;
            }
            else
                $_SERVER['QUERY_STRING']='';

            // Détermine l'url de la page d'accueil de l'application (Arg 2, optionnel)
            if (isset($_SERVER['argv'][2]))
            {
                // Découpe l'url et stocke les différents bouts au bon endroit
                $url=parse_url($_SERVER['argv'][2]);

                $_SERVER['HTTPS']='off';

                $_SERVER['SERVER_PORT']=isset($url['port']) ? $url['port'] : '80';
                $_SERVER['SERVER_NAME']=$url['host'];

                self::$home=$url['path']; //$_SERVER['argv'][2];
                self::$realHome=dirname(self::$home);

                // Garantit que home et realHome contiennent toujours un slash final
                self::$realHome=rtrim(self::$realHome,'/\\').'/';
                self::$home=rtrim(self::$home,'/').'/';
            }

            $_SERVER['REQUEST_METHOD']='GET';
            self::$fcInUrl=null;
        }

        // Initialisation en modes CGI, SAPI...
        else
        {
            // Url demandée par l'utilisateur
            if (isset($_SERVER['REQUEST_URI']))
                self::$url=$_SERVER['REQUEST_URI'];
            else
                die("Impossible d'initialiser l'application, REQUEST_URI non disponible");

            // Supprime la query string de l'url
            if (false !== $pt=strpos(self::$url,'?'))
                self::$url=substr(self::$url,0,$pt);

            // Préfixe de l'url : partie de l'url entre le nom du serveur et le nom du front controler
            if (false !== $pt=stripos(self::$url, self::$fcName))
            {
                // l'url demandée contient le nom du front controler
                self::$realHome=substr(self::$url, 0, $pt);
                self::$home=self::$realHome.self::$fcName;
                self::$fcInUrl=true;
            }
            else
            {
                if (isset($_SERVER['ORIG_PATH_INFO']))
                    $path=$_SERVER['ORIG_PATH_INFO'];
                else
                    $path=$_SERVER['SCRIPT_NAME'];//SCRIPT_FILENAME

                if (false=== $pt=strpos($path, self::$fcName))
                    die("Impossible d'initialiser l'application : url redirigée mais nom du script non trouvé dans ORIG_PATH_INFO");

                self::$fcInUrl=false;

                self::$home=self::$realHome=substr(self::$url,0,$pt);
            }

            // Garantit que home et realHome contiennent toujours un slash final
            self::$realHome=rtrim(self::$realHome,'/').'/';
            self::$home=rtrim(self::$home,'/').'/';

            if (strlen(self::$url)<strlen(self::$home))
            {
                self::$url='';
            }
            else
            {
                self::$url=substr(self::$url, strlen(self::$home)-1);
            }
        }
    }

    /**
     * Vérifie que l'url demandée par l'utilisateur correspond à l'url réelle de la page demandée.
     *
     * - si les smarturls sont activées et que l'adresse comporte le nom du script d'entrée,
     * redirige l'utilisateur vers l'url sans nom du script
     *
     * - si les smarturls sont désactivées et que l'adresse ne mentionne pas le nom du script
     * d'entrée, redirige vers l'url comportant le nom du script
     *
     * - si la "home page" est appellée sans slash final, redirige l'utilisateur vers la home
     * page avec un slash (xxx/index.php -> xxx/index.php/)
     */
    private static function checkSmartUrls()
    {
        if (!is_bool(self::$fcInUrl)) // fcName=null : mode cli, ignorer le test
            return;

        if (! Utils::isGet())  // on ne sait pas faire de redirect si on est en POST, inutile de tester
            return;

        $smartUrls=Config::get('smarturls',false);

        // fc dans l'url && SmartUrl=off -> url sans fc
        if ($smartUrls && self::$fcInUrl)
        {
            if (false===$pt=strrpos(rtrim(self::$home,'/'),'/'))
                die("redirection impossible, valeur erronée pour 'home'");

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
     *
     * @param string $env L'environnement à utiliser ou une chaine vide pour
     * utiliser l'environnement normal.
     */
    public static function setup($env='')
    {
        self::$initializing=true;

        // Mémorise l'heure de début au xas où l'option timer soit activée
        $startTime=microtime(true);

        // Mémorise l'environnement d'exécution demandé
        self::$env=($env=='' ? 'normal' : $env);

        // Vérifie qu'on a la configuration minimale requise pour fab
        self::checkRequirements();

        // Définit les chemins utilisés
        self::setupPaths();

        // Charge le gestionnaire de classes
        spl_autoload_register(array(__CLASS__, 'autoload'));

        // initialement, l'autoload ne peut charger que les classes core de fab.
        // dès que la config sera chargée, il saura charger tout le reste

        // Charge la configuration de base (fichiers /config/config.*.php)
        self::setupBaseConfig();

        // Initialise le cache
        self::setupCache();

        // Charge la configuration générale (fichiers /config/general.*.config)
        self::setupGeneralConfig();

        self::$initializing=false;

        // Charge la configuration des bases de données (fichiers /config/db.*.config)
        if (file_exists($path=Runtime::$fabRoot.'config' . DIRECTORY_SEPARATOR . 'db.config'))
            Config::load($path, 'db');
        if (file_exists($path=Runtime::$root.'config' . DIRECTORY_SEPARATOR . 'db.config'))
            Config::load($path, 'db');

        if (!empty(Runtime::$env))   // charge la config spécifique à l'environnement
        {
            if (file_exists($path=Runtime::$fabRoot.'config'.DIRECTORY_SEPARATOR.'db.' . Runtime::$env . '.config'))
                Config::load($path, 'db');
            if (file_exists($path=Runtime::$root.'config'.DIRECTORY_SEPARATOR.'db.' . Runtime::$env . '.config'))
                Config::load($path, 'db');
        }

        // Vérifie que les smarturls sont respectées, redirige si besoin est
        self::checkSmartUrls();

        // Définit le fuseau horaire utilisé par les fonctions date de php
        self::setupTimeZone();
        setlocale(LC_ALL, 'French_France', 'fr');

        // Répare les tableaux $_GET, $_POST et $_REQUEST
        Utils::repairGetPostRequest();

        // Définit la constante utilisée pour le mode debug
        define('debug', (bool)config::get('debug',false));

        // Définit la constante utilisée pour le timer
        // remarque : en mode cli, le timer est toujours désactivé.
        // raison : les tâches peuvent être très longues et du coup, le
        // nombre total d'objets timer créés peut suffire à consommer toute
        // la mémoire disponible.
        if (php_sapi_name()=='cli')
            define('timer', false);
        else
            define('timer', (bool)config::get('timer',false));

        // Chronomètre le temps d'intialisation de fab
        if (timer)
        {
            Timer::reset($startTime);
            Timer::enter('Initialisation de fab', $startTime);
            Timer::enter('Configuration de base', $startTime);
            Timer::leave();
        }

        debug && Debug::notice('Initialisation de fab en mode "%s"', $env ? $env : 'normal');
        debug && Debug::notice("Module/action demandés par l'utilisateur : " . self::$url);

        // Initialise le gestionnaire d'exceptions
        timer && Timer::enter('Gestionnaire d\'exceptions');
            debug && Debug::log("Initialisation du gestionnaire d'exceptions");
            Module::loadModule('ExceptionManager')->install();
        timer && Timer::leave();

        // Charge les routes (fichiers /config/routes.*.config)
        timer && Timer::enter('Chargement des routes');
            debug && Debug::log('Initialisation des routes');
            self::setupRoutes();
        timer && Timer::leave();

        // Initialise le gestionnaire de sécurité
        timer && Timer::enter('Chargement de la sécurité');
            debug && Debug::log('Initialisation du gestionnaire de sécurité');
            User::$user=Module::loadModule(Config::get('security.handler'));
        timer && Timer::leave();

        // L'initialisation est terminée
        timer && Timer::leave();

        // Exécute le module et l'action demandés
        timer && Timer::enter('Exécution de ' . self::$url);
            debug && Debug::log("Lancement de l'application");
            self::$baseConfig=Config::getAll(); // hack pour permettre à runSlot de repartir de la bonne config
            Routing::dispatch(self::$url);
        timer && Timer::leave();

        self::shutdown();
    }

    // Modules requis : Debug
    // variables utilisées : fabRoot, root, env
    private static function setupBaseConfig()
    {
        require_once(self::$fabRoot . 'config' . DIRECTORY_SEPARATOR . 'config.php');
        if (file_exists($path=self::$root . 'config' . DIRECTORY_SEPARATOR . 'config.php'))
            require_once $path;

        if (!empty(self::$env))   // charge la config spécifique à l'environnement
        {
            if (file_exists($path=self::$fabRoot.'config'.DIRECTORY_SEPARATOR.'config.' . self::$env . '.php'))
                require_once $path;

            if (file_exists($path=self::$root.'config'.DIRECTORY_SEPARATOR.'config.' . self::$env . '.php'))
                require_once $path;
        }
    }

    // Modules requis : Debug, Config
    // variables utilisées : fabRoot, root, env
    private static function setupGeneralConfig()
    {
        Config::load(self::$fabRoot.'config' . DIRECTORY_SEPARATOR . 'general.config');
        if (file_exists($path=self::$root.'config' . DIRECTORY_SEPARATOR . 'general.config'))
            Config::load($path);

        if (!empty(self::$env))   // charge la config spécifique à l'environnement
        {
            if (file_exists($path=self::$fabRoot.'config'.DIRECTORY_SEPARATOR.'general.' . self::$env . '.config'))
                Config::load($path);
            if (file_exists($path=self::$root.'config'.DIRECTORY_SEPARATOR.'general.' . self::$env . '.config'))
                Config::load($path);
        }
    }

    // Modules requis : Config, Utils, Debug
    // variables utilisées : fabRoot, root
    private static function setupCache()
    {
        if (Config::get('cache.enabled'))
        {
            // Détermine le nom de l'application
            $appname=basename(self::$root);

            // Détermine le path de base du cache
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

            // Détermine le path du cache de l'application et de fab
            $path.=DIRECTORY_SEPARATOR.'fabcache'.DIRECTORY_SEPARATOR.$appname;
            $appPath=$path.DIRECTORY_SEPARATOR.self::$env.DIRECTORY_SEPARATOR.'app';
            $fabPath=$path.DIRECTORY_SEPARATOR.self::$env.DIRECTORY_SEPARATOR.'fab';

            // Créée les caches
            if (Cache::addCache(self::$root, $appPath) && Cache::addCache(self::$fabRoot, $fabPath))
            {
                // ok
            }
            else
            {
                Config::set('cache.enabled', false);
            }
        }
    }

    // Modules requis : Config
    private static function setupTimeZone()
    {
        $timeZone=Config::get('timezone');
        date_default_timezone_set($timeZone && $timeZone!='default' ? $timeZone : @date_default_timezone_get());
    }

    private static function setupRoutes()
    {
        // Charge d'abord les routes de fab
        Config::load(self::$fabRoot.'config' . DIRECTORY_SEPARATOR . 'routing.config', 'routing', array('Routing','transform'));

        // Puis les routes de l'application
        if (file_exists($path = self::$root.'config' . DIRECTORY_SEPARATOR . 'routing.config'))
            Config::load($path, 'routing', array('Routing','transform'));

        // Puis les routes spécifiques à l'environnement en cours
        if (!empty(self::$env))
        {
            if (file_exists($path=self::$fabRoot.'config'.DIRECTORY_SEPARATOR.'routing.' . self::$env . '.config'))
                Config::load($path, 'routing', array('Routing','transform'));
            if (file_exists($path=self::$root.'config'.DIRECTORY_SEPARATOR.'routing.' . self::$env . '.config'))
                Config::load($path, 'routing', array('Routing','transform'));
        }
    }

    public static function shutdown()
    {
        if (Config::get('showdebug'))
        {
            debug && Debug::log("Application terminée");
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


    /**
     * Démarre la session si ce n'est pas déjà fait
     */
    public static function startSession()
    {
        if (session_id()=='') // TODO : utiliser un objet global 'Session' pour le paramétrage
        {
            session_name(Config::get('sessions.id'));
            session_set_cookie_params(Config::userGet('sessions.lifetime'), self::$home);
            //session_set_cookie_params(Config::get('sessions.lifetime'));
            session_cache_limiter('none');

            @session_start();
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
        if ($url instanceOf Request) $url=$url->getUrl();

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
     * Essaie de charger le fichier qui contient la définition de la classe
     * indiquée en paramètre.
     *
     * Cette fonction n'est pas destinée à être appellée directement : c'est une
     * fonction magique que php appelle lorsqu'il ne trouve pas la définition
     * d'une classe.
     *
     * @param string $className le nom de la classe qui n'a pas été trouvée.
     */
    public static function autoload($class)
    {
        // Classes dont on a besoin avant que la configuration ne soit chargée
        static $core=array
        (
            //'Debug'=>'core/debug/Debug.php',
            'Utils'=>'core/utils/Utils.php',
            'Config'=>'core/config/Config.php',
            'Cache'=>'core/cache/Cache.php',
        );

        // Classes "core" de fab
        if (isset($core[$class]))
        {
            $path=$core[$class];
            $root=self::$fabRoot;
        }

        // Classes définies par l'application
        elseif ($path=Config::get('autoload.'.$class))
        {
            $root=self::$root;
        }

        // Classes définies par fab
        elseif ($path=Config::get('fabautoload.'.$class))
        {
            $root=self::$fabRoot;
        }

        // Modules
        else
        {
            // Modules de l'application
            $path='modules'.DIRECTORY_SEPARATOR.$class.DIRECTORY_SEPARATOR.$class.'.php';
            $root=Runtime::$root;
            if (!file_exists($root.$path))
            {
                // Modules de fab
                $root=Runtime::$fabRoot;
                if (!file_exists($root.$path))
                {
                    // Classe non trouvée
                    return false;
                }
            }
        }

        $path=$root. ltrim(strtr($path,'/', DIRECTORY_SEPARATOR),DIRECTORY_SEPARATOR);
        //printf('Autoload %s (%s)<br/>', $class, $path);
        require($path);
        // autoload n'est appellée que si la classe n'existe pas, on peut donc
        // se ontenter de require, require_once est inutile.
        defined('debug') && debug && Debug::log('Autoload %s (%s)', $class, $path);
    }

    /**
     * Retourne la version en cours de fab, sous forme d'une chaine de
     * caractère.
     *
     * La méthode analyse l'url du fichier Runtime.php au sein du dépôt
     * subversion pour déterminer la version de fab (exemple :
     * http://fab.googlecode.com/svn/tags/0.11.0/Runtime.php = version 0.11.0)
     *
     * Le numéro de version retourné peut être :
     * - le nom d'un tag dans le dépôt subversion, composé d'une chaine de trois
     *   chiffres séparés par un point (par exemple 0.11.0).
     * - le nom d'une branche dans le dépôt subversion (la syntaxe peut varier,
     *   mais il s'agit en général d'un numéro de tag suivie d'un label).
     * - la chaine 'trunk' qui indique qu'il s'agit de la version en cours de
     *   développement.
     * - le booléen false si le numéro de version n'a pas pu être déterminé
     *   automatiquement.
     *
     * Remarque :
     * Si vous utilisez les tags, vous pouvez dans votre application utiliser la
     * fonction php version_compare() pour vérifier que la version installée de
     * fab est au moins celle que vous attendez :
     * <code>
     *      if (version_compare(Runtime::getVersion(), '0.11.0', '<=')
     *          die('La version de fab que vous utilisez est obsolète');
     * </code>
     *
     * @var string|false le numéro de version de fab.
     */

    public static function getVersion()
    {
        // Le contenu de la variable ci-dessous est mis à jour automatiquement
        // par subversion lors d'un checkout. Ne pas modifier.
        $headUrl='$HeadURL$';

        if (false === $pt=strpos($headUrl, '/svn/'))
            return false;

        $t=explode('/', substr($headUrl, $pt+5), 3);
        if ($t[0] === 'trunk')
            return 'trunk';

        return $version=$t[1];
    }
}
?>