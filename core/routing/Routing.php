<?php
/**
 * @package     fab
 * @subpackage  routing
 * @author 		Daniel Ménard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id$
 */


/**
 * Gestionnaire de routes.
 * 
 * Les gestionnaire de routes permet d'avoir des urls sémantiques. Il travaille
 * à partir d'un fichier de configuration (routes.config) qui pour chacune des
 * urls que l'utilisateur peut appeller définit le module et l'action à
 * appeller ainsi que les paramètres à passer.
 * 
 * Le fichier est aussi utilisé en sens inverse : lorsque dans un template on
 * fait un lien vers une autre action ou un autre module, le lien est
 * automatiquement ajusté en fonction de la configuration.
 * 
 * @package     fab
 * @subpackage  routing
 */
class Routing
{
    /**
     * Compile un tableau contenant des routes telles qu'elle figurent dans
     * le fichier de configuration XML en tableau indexé permettant à 
     * {@link linkFor()} et {@link routeFor()} de travailler de façon efficace.
     * 
     * La compilation consiste à créer deux index (un pour chacune des deux 
     * fonctions).
     * 
     * todo: voir (faire) doc pour plus de détails sur le format des tableaux
     * 
     * @param array $config tableau contenant les routes
     * @return array tableau contenant la version compilée des routes
     */
    public static function transform(array $routes)
    {
        // Les deux index qu'on va générer
        $urls=array();
        $modules=array();
        
        // Compile chaque route une par une, dans l'ordre indiqué
        foreach($routes as $route)
        {
            // Récupère et vérifie l'url indiquée dans la route
            if (!isset($route['url']) || '' === $url=trim($route['url']))
            {
                debug && Debug::warning('Route invalide, url non indiquée : %s', $route);
                continue;
            }
            
            if($url[0] !== '/')
            {
                debug && Debug::warning('Route invalide, l\'url ne commence pas par un slash : %s', $route);
                continue;
            }
            
            // Découpe l'url en parties et complète le tableau $urls
            $parts = & $urls;
            foreach(self::urlParts($url) as $part)
            {
                // Si urlignorecase=true, on stocke la version minu des bouts d'url, sinon, on stocke tel quel
                if (Config::get('urlignorecase')) $part=strtolower($part);
                
                // Variable à cet emplacement : ne stocke pas le nom mais juste '$'
                if ($part[0]==='$') $part='$';
                
                // Ajoute cette partie dans le tableau si elle n'existe pas déjà
                if (!isset($parts[$part])) $parts[$part]=array();
                
                // Continue avec le bout en cours
                $parts=& $parts[$part];
            }
            
            
            // Crée la liste des arguments ($xx) qui figurent dans l'url
            preg_match_all('~\$([A-Za-z0-9_]+)~', $url, $args, PREG_PATTERN_ORDER|PREG_OFFSET_CAPTURE);
            $args=$args[1]; // un tableau index=>(arg,position)

            // Vérifie et finalise les expressions régulières indiquées dans la route (with)
            if (isset($route['with']))
            {
                foreach($route['with'] as $name => & $regexp)
                {
                    foreach($args as $arg)
                    {
                        if ($arg[0]===$name)
                        {
                            $regexp='~^'.$regexp.'$~';
                            continue 2;
                        }
                    }
                    debug && Debug::warning('Route incorrecte, la variable %s indiquée dans l\'attribut with ne figure pas dans l\'url : %s', $name, $route);
                }
            }
            
            /*
               Crée deux versions de la route : 
               - $urlRoute qui sera stockée dans $urls et qui sera utilisée par
                 routeFor()
               - $modRoute qui sera stockée dans $modules et qui sera utilisée
                 par linkFor()

               Les deux versions ne différent que par la manière dont les 
               arguments sont stockés (propriété 'args' de la route).

               Pour $urlRoute : les arguments sont stockés dans l'ordre exact où
               ils apparaissent dans l'url. Le nom de l'argument est la clé et 
               la valeur associée est le numéro d'ordre de la variable dans l'url.
               Si une variable apparaît plusieurs fois dans l'url, la valeur est
               alors un tableau contenant (en ordre croissant) les différents
               occurences. 
               cf. routeForRecurse().

               Pour $modRoute : les arguments sont stockés dans l'ordre inverse
               de l'ordre dans lequel ils apparaissent dans l'url (parce que 
               linkFor fait le remplacement de la fin vers le début).
               Le nom de l'argument est la clé est la valeur associée est la 
               position exacte, au sein de l'url, à laquelle l'argument apparait.
               Si une variable apparaît plusieurs fois dans l'url, la valeur est
               alors un tableau contenant (en ordre décroissant, pour la même 
               raison) les différents positions. 
               Cette structure permet à linkFor() de remplacer la variable par 
               sa valeur directement au bon endroit, sans avoir à rechercher le 
               nom de la variable. 
            */
            $modRoute=$urlRoute=$route;
            if (count($args))
            {
                $modRoute['args']=$urlRoute['args']=array();
                foreach($args as $index=>$arg)
                {
                    $name=$arg[0];
                    $position=$arg[1]-1;// -1 = pour pointer sur le '$' et non pas sur le début du nom
                    self::arrayAppendKey ($urlRoute['args'], $name, $index);    // arg=>index, ordre normal 
                    self::arrayPrependKey($modRoute['args'], $name, $position); // arg=>position, ordre inverse
                }
            }
            $parts['@route']=$urlRoute;
            
            // Ajoute cette route dans le tableau $modules
            if (isset($modRoute['module']))
                $module=strtolower($modRoute['module']); // minusculise toujours le nom du module pour que linkFor('/xxx/yyy') soit insensible à la casse. Indépendant de l'option urlignorecase
            else
                $module='$';
            
            if (isset($modRoute['action']))
            {
                $action=strtolower($modRoute['action']); // minusculise toujours le nom de l'action, même raison que ci-dessus
                if (strncmp($action, 'action', 6)===0) 
                    $action=substr($action, 6);
            }
            else 
                $action='$';
                
            $modules[$module.'-'.$action][]=$modRoute;
        }

        // Construit le tableau résultat qui sera stocké dans la config
        $routes=array
        (
            'urls'=>$urls,
            'modules'=>$modules
        );

        // Retourne le tableau final de routes qui sera mis en cache
        return $routes;
    }
    
    /**
     * Dispatche une url
     * 
     * dispatch() analyse l'url passée en paramètre et examine les routes 
     * présentes dans la configuration pour déterminer le nom du module et de 
     * l'action correspondant à cette url.
     * 
     * Une exception est générée si aucune route n'accepte l'url examinée.
     * 
     * Dans le cas contraie, un objet {@link Request} contenant les paramètres
     * de la requête est créé, le module obtenu est chargé et l'exécution de 
     * l'action est lancée. 
     *
     * @param string $url l'url demandée par l'utilisateur
     * 
     * @throws Exception si aucune des routes présentes dans la configuration
     * en cours n'accepte l'url indiquée.
     */
    public static function dispatch($url)
    {
        // Détermine une route pour cette url
        if (! $route=self::routeFor($url))        
            throw new Exception('Route not found');

        // Crée et initialise un nouvel objet Request
        $request = Request::create($_GET, $_POST, $route['args'])
            ->setModule($route['module'])
            ->setAction($route['action']);
        
        // Hack : si c'est le premier dispatch qu'on exécute,
        // Stocke la requête obtenue dans le runtime pour
        // que Template::runSlot() puis y accéder
        if (is_null(Runtime::$request))    
            Runtime::$request=$request;
        
        Module::run($request);
    }
    
    
    /**
     * Détermine une route pour une url donnée
     * (l'url ne doit pas contenir de query string)
     *
     * @param string $url
     * @return array|false
     */
    public static function routeFor($url)
    {
        return self::routeForRecurse
        (
            Config::get('routes.urls'), // La partie "index par morceaux" des routes 
            self::urlParts($url)        // L'url découpée en morceaux
        );        
    }
    
    /**
     * Fonction récursive utilisée par {@link routeFor()} pour déterminer une
     * route à partir d'une url
     *
     * @param array $routes
     * @param unknown_type $parts
     * @param unknown_type $index
     * @param unknown_type $vars
     * @return unknown
     */
    private static function routeForRecurse(array $routes, array $parts, $index=0, $vars=array())
    {
        $trace=false;
        
        // On a étudié toutes les parties de l'url, teste les routes restantes
        if ($index>=count($parts))
        {
            // Vérifie qu'il existe des routes pour ce qu'on a étudié
            if(!isset($routes['@route'])) 
            {
                if($trace)echo 'pas de @route<br />';
                return false;
                
            }

            // Récupère la route obtenue
            $route=$routes['@route'];

            if($trace)
            {
                echo 'route à instancier : ', var_export($route), '<br />';
                echo 'Vars : ', var_export($vars, true), '<br />';
            }
                        
            // Vérifie que toutes les variables respectent le masque indiqué par 'with'
            if (isset($route['with']))
            {
                foreach($route['with'] as $name=>$regexp) // nécessaire si l'argument indiqué par le with est répété dans la route
                {
                    foreach((array) $route['args'][$name] as $i)
                    {
                        if (!preg_match($regexp, $vars[$i])) 
                        {
                            if($trace)echo 'argument ', $name, '=', $vars[$i], ' colle pas avec son with<br />';
                            return false;
                        }
                        else
                            if($trace)echo 'argument ', $name, '=', $vars[$i], ' colle avec son with=', $regexp, '<br />';
                    }                    
                }
            }
            
            // Stocke les arguments
            $args=array();
            if (isset($route['args']))
            {
                foreach($route['args'] as $name=>$index)
                {
                    foreach((array)$index as $index)
                    {
                        if($trace)echo 'storing args, name=', $name, ', value=', $vars[$index], '<br />';
                        
                        if (!isset($route[$name]) && ($name==='module' || $name==='action'))
                        {
                            if ($name==='action')
                                $route[$name]=$vars[$index];
                            else
                                $route[$name]=$vars[$index];
                        }
                        else
                            self::arrayAppendKey($args, $name, $vars[$index]);
                    }
                }
            }
            
            if (isset($route['add']))
            {
                foreach($route['add'] as $name=>$value)
                {
                    self::arrayAppendKey($args, $name, $value);
                }
            }
            
            $route['args']=$args;
            
            if($trace) echo '<pre>', var_export($route,true), '</pre>';
            
            // Trouvé !
            return $route;
        }
        
        // Ignore la casse dans les urls si l'option urlignorecase=true
        $part=$parts[$index];
        if (Config::get('urlignorecase')) $part=strtolower($part);
        
//        var_export($routes);
        // Recherche parmi les routes qui ont cette partie à cet endroit
        if (isset($routes[$part]))
        {
            if($trace)echo 'texte. part=', $part, '<blockquote style="border: 1px solid #888;margin:0 2em">';
            $route=self::routeForRecurse($routes[$part], $parts, $index+1, $vars);
            if($trace)echo '</blockquote>';
            if ($route !== false) return $route; 
        }
        else if($trace) echo 'routes[',$part,'] not set<br />';
        
        // Sinon, rechercher parmi les routes qui autorisent une variable à cet endroit
        if (isset($routes['$']))
        {
            //$vars[]=$part;
            $varIndex=count($vars);
            $vars[$varIndex]='';
            if($trace)echo 'index=', $index, ', var=', $vars[$varIndex], '<br />';
            for($i=$index+1; $i<=count($parts); $i++)
            {
                $vars[$varIndex].=urldecode($parts[$i-1]);
                if($trace)echo 'var. part=', $part, ', var=', $vars[$varIndex], '<blockquote style="border: 1px solid #888;margin:0 2em">';
                $route=self::routeForRecurse($routes['$'], $parts, $i, $vars);
                if($trace)echo '</blockquote>';
                if ($route !== false) return $route;
            }
        }
        else if($trace)echo '$ not set';

        // Aucune route ne convient dans cette branche
        if($trace)echo 'ni "', $part, '" ni variable ne sont autorisés ici<br />';
        if($trace)var_export($routes);
        
        return false;
    }
    
    
    
    
    
    
    /**
     * Génère un lien à partir d'une fab url.
     * 
     * Une fab url est en général de la forme "/module/action?querystring",
     * mais il est possible d'omettre le module, l'action ou les deux :
     * 
     * - /module/action?query : action "action" du module "module"
     * - /module/?query : action par défaut (index) du module module
     * - /module?query : todo: autorisé ou non ? redirection ou non ?
     * - /?query : action par défaut du module par défaut (la page d'accueil du site)
     * - action?query : action "action" du module en cours
     * - ?query : action en cours du module en cours
     * 
     * La fonction utilise le module, l'action et les paramètres indiqués en 
     * query string pour déterminer, parmi les routes présentes dans la 
     * configuration en cours, celle qui s'applique.
     * 
     * Si aucune route n'est trouvée, un warning est émis et l'url est retournée
     * telle quelle. Sinon, la fonction instancie la route trouvée et retourne 
     * l'url obtenue.
     * 
     * Par défaut, la fonction crée des liens relatifs (qui ne mentionnent ni
     * le protocole ni le nom de domaine). Vous pouvez indiquer absolute=true 
     * pour générer des urls absolues (utile par exemple pour générer un e-mail 
     * au format html).
     * 
     * todo: les liens retournés sont de la forme /index.php/xxx/yyy, c'est-à-dire
     * qu'ils partent toujours de la racine du site. Ce serait bien de pouvoir
     * générer des liens relatifs au module et à l'action en cours (par exemple,
     * si je suis dans '/base/search?xxx' et que j'appelle 'show?yyy', générer 
     * simplement 'show?yyy' et non pas '/base/show?yyy' ; si je suis déjà
     * dans '/base/show?yyy' et que j'appelle 'show?zzz', générer simplement
     * '?zzz'. 
     *
     * @param string $url la fab url pour laquelle on souhaite créer un lien
     * 
     * @param boolean $absolute indique s'il faut générer un lien relatif ou non
     * (par défaut : false)
     * todo: est-que cela ne devrait pas plutôt être dans la config ?
     * 
     * @return string
     */
    public static function linkFor($url, $absolute=false)
    {
        $url=(string)$url; // au cas où on nous passe autre chose qu'une chaine (par exemple un objet Request)
        
        // Si ce n'est pas une fab url, on retourne l'url telle quelle
        if (preg_match('~^(?:[a-z]{3,10}:|\?|#)~',$url))  // commence par un nom de protocole, une query string ou un hash (#)
            return $url;

        $ori=$url;
        
        // Supprime l'ancre éventuelle
        $pt=strpos($url, '#');
        if ($pt === false)
            $anchor='';
        else
        {
            $anchor=substr($url, $pt);
            $url=substr($url, 0, $pt);
        }
        
        // Analyse la query string, initialise $args()
        $args=array();
        $pt=strpos($url, '?');
        if ($pt === false)
            $query='';
        else
        {
            $query=substr($url, $pt+1);
            if (strlen(ltrim($query)))
            {
                foreach (explode('&', $query) as $arg)
                {
                    list($name,$value)=array_pad(explode('=', $arg), 2, null);
                    self::arrayAppendKey($args, $name, $value);
                }
            }            
            $url=substr($url, 0, $pt);
        }

        // Détermine le module et l'action demandés
        
        // Pas d'url -> on prends le module et l'action en cours
        if ($url==='')
        {
            $module=Runtime::$request->getModule(); // module en cours
            $action=Runtime::$request->getAction(); // action en cours
            if (strncmp($action, 'action', 6)===0)
                $action=substr($action, 6);
        }
        
        // Raccourci pour la page d'accueil du site
        elseif($url==='/')
        {
            // Ajoute la webroot
            $link= Runtime::$home . self::buildQueryString($args);
            
            // Crée une url absolue si ça a été demandé
            if ($absolute)
                $link=Utils::getHost() . $link . $anchor;
            return $link;
        }
        
        // Commence par un '/' -> lien vers un module
        elseif ($url[0] === '/')
        {
            $url=ltrim($url, '/'); // supprime le slash initial
            $pt=strpos($url, '/');
            if ($pt===false)
            {
                $module=$url;
                $action='index'; // action par défaut
            }
            else
            {
                $module=substr($url, 0, $pt);
                $action=substr($url, $pt+1);
                if ($action=='') $action='index';
            }

            // Si le "module" est un sous-répertoire existant du répertoire web
            // de l'application on retourne un lien réel vers celui-ci.
            // Peu-importe que le fichier existe réellement ou non (il peut y 
            // avoir une redirection dans le .htaccess, etc...), on ne teste que
            // l'existence du répertoire de plus haut niveau (ie $module).
            if (file_exists(Runtime::$webRoot . $module))
            {
                return ($absolute ? Utils::getHost() : '') . Runtime::$realHome . $url . $anchor;
            }
        }

        // pas de slash au début -> lien vers une action du module en cours
        else
        {
            $module=Runtime::$request->getModule(); // module en cours
            $action=$url; // rtrim($url,'/');
        }

        // supprime le slash final de l'action
        if (strpos($action, '/')===strlen($action)-1) // enlève le '/' de fin dans 'index/' mais pas dans '/styles/default/'
        {
            $action=rtrim($action,'/');
        }
        
        if (false === $link=self::linkForRoute($module, $action, $args))
        {
            debug && Debug::warning('linkFor : aucune route applicable pour l\'url %s', $url);
            $link=$ori;
        }
        
        // Ajoute la webroot
        $link= rtrim(Runtime::$home,'/') . $link;
        
        // Crée une url absolue si ça a été demandé
        if ($absolute)
            $link=Utils::getHost() . $link;
            
        // Retourne le résultat
//        debug && Debug::log('linkFor(%s)=%s', $url, $link);
        return $link . $anchor;
    }
    
    private static function linkForRoute($module, $action, array $args=array())
    {
        if (!defined('trace')) define('trace',false);
        
        $ori=$args;
        if(trace) echo '<h1>linkForRoute</h1>module=', $module, ', action=', $action, ', args=', var_export($args,true), '<br /><pre>';

        // linkForRoute('/xxx/yyy') n'est pas sensible à la casse. Transform stocke toujours la version minu, donc il faut rechercher de la même façon
        $lowerModule=strtolower($module);
        $lowerAction=strtolower($action);
        
        // Récupère la liste des routes possibles pour ce couple (module,action)
        $routes=Config::get("routes.modules.$lowerModule-$lowerAction");
        if (is_null($routes))
        {
            if(trace)echo 'Pas de routes pour ', "routes.modules.$lowerModule-$lowerAction", '<br />';
            $routes=Config::get("routes.modules.\$-$lowerAction");
            if (is_null($routes))
            {
                if(trace)echo 'Pas de routes pour ', "routes.modules.\$-$lowerAction", '<br />';
                $routes=Config::get('routes.modules.$-$');
                if (is_null($routes))
                {
                    if(trace)echo 'Pas de routes pour ', 'routes.modules.$-$', '<br />';
                    if(trace)echo '<pre>', var_export(Config::get('routes'), true), '</pre>';
                    if ($action==='index') $action='';
                    $url='/'.$module.'/'.$action.self::buildQueryString($args);
                    if (trace)echo 'Aucune route pour module=', $module, ' & action=', $action, ', reconstruction de l\'url, result=', $url, '<br /></pre>';
                    return $url;
                }
                
            }
        }
        
        
        // Ne garde que celles qui conviennent
        foreach($routes as $route)
        {
            $args=$ori;
            
            if(trace)echo 'Route étudiée : ', var_export($route,true), '<br />';

            // Si la route a des arguments, il faut les vérifier
            if (isset($route['args']))
            {
                // Cas particulier : module et route sont définis par l'url, il faut les considérer comme des paramètres
                if (isset($route['args']['module']))
                    self::arrayPrependKey($args, 'module', $module);
                if (isset($route['args']['action']))
                    self::arrayPrependKey($args, 'action', $action);
                
                // On doit avoir au moins autant d'arguments que dans la route
                if (count($args) < count($route['args']))
                {
                    if(trace)echo 'nombre de args insuffisant<br />';
                    continue;
                }
                
                // Chacun des arguments de la route doit être présent
                foreach($route['args'] as $name=>$value)
                {
                    if (!isset($args[$name]))
                    {
                        if(trace)echo'arg ', $name, ' non fourni<br />';
                        continue 2;
                    }
                    if(is_array($value) && count($args[$name])<count($value))
                    {
                        if(trace) echo 'nb de valeurs fournies pour arg ', $name, ' insuffisant<br />';
                        continue 2;
                    }   
                }
                
                // Les arguments doivent avoir le bon type (regexp) 
                if (isset($route['with']))
                {
                    foreach($route['with'] as $name=>$regexp)
                    {
                        foreach((array)$args[$name] as $value)
                            if (!preg_match($regexp, $value)) 
                            {
                                if(trace)echo 'valeur ', $value, 'de arg ', $name, ' ne correspond pas au masque ', $regexp, '<br />';
                                continue 3;
                            }
                    }
                }
            }
             
            // Si on a des 'add' dans l'url ils doivent figurer dans les arguments
            if (isset($route['add']))
            {
                foreach ($route['add'] as $name=>$value)
                {
                    if (!isset($args[$name])) 
                    {
                        if(trace)echo 'ADD ', $name, ' non fourni<br />';
                        continue 2; // le add de la route n'a pas été indiqué
                    }
                    
                    if (is_array($value))
                    {
                        if (count($value) > count($args[$name]))
                        {
                            if(trace) echo 'nombre de valeurs pour ADD ', $name, ' insuffisant<br />';
                            continue 2; // n valeurs spécifiées dans le add, moins que ça dans les args
                        }
                            
                        foreach($value as $value)
                        {
                            if (false === $i=array_search($value, $args[$name], false)) // non-strict
                            {
                                if(trace) echo 'valeur ', $value, ' du ADD ', $name, ' non fournie<br />';
                                continue 3; // la valeur indiquée dans le add n'est pas dans les args
                            }
                            unset($args[$name][$i]);
                        }
                        if (count($args[$name])===0)
                        unset($args[$name]);
                    }
                    else
                    {
                        if ($value!=$args[$name]) // non-strict
                        {
                            if(trace) echo 'valeur ', $value, ' du ADD ', $name, ' non fournie<br />';
                            continue 2; // la valeur indiquée dans le add est différente
                        }
                        unset($args[$name]);
                    }
                }
            }
                
            // Tout est ok, crée le lien en instanciant l'url
            $url=$route['url'];
            if (isset($route['args']))
            {
                foreach($route['args'] as $name=>$position)
                {
                    foreach((array)$position as $position)
                    {
                        if (is_array($args[$name]))
                        {
                            $arg=array_pop($args[$name]);
                        }
                        else
                        {
                            $arg=$args[$name];
                            unset($args[$name]);
                        }
                        $url=substr_replace($url, $arg, $position, strlen($name)+1); // +1 pour le signe $
                    }
                }
            }
                        
            // S'il reste des arguments, on les ajoute en query-string
            if(trace)echo 'url instanciée : ', $url, ', args restants : ', var_export($args,true), '<br />';
            $url.=self::buildQueryString($args);
            
            return $url;
        }
        
        return false;
    }
    
    
    public static function notFound()
    {
        static $nb=0;
        if (++$nb>3) die('loop dans notfound');
        self::dispatch('/NotFound/'); // TODO: mettre dans la config le nom du module 'not found'
        Runtime::shutdown();
    }
    
    
    /**
     * Découpe une url en morceaux
     *
     * @param string $url
     * @return array
     */
    public static function urlParts($url)
    {
        // Ignore le slash de début, évite un niveau d'indirection inutile
        $url=ltrim($url,'/');
        $parts=array();
        for($start=0; $start<strlen($url);)
        {
            if ($url[$start]==='$')
            {
                $len=1+strspn($url, 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ_', $start+1);
            }
            else
            {
                $len=strcspn($url, '-,;/$.+', $start);
            }
                
            $parts[]=substr($url, $start, $len);
            $start+=$len;
            if ($start < strlen($url) && $url[$start]!=='$')
            {
                $parts[]=$url[$start];
                ++$start;
            }
        }
        return $parts;
    }
    
    /**
     * Ajoute une clé et une valeur dans un tableau.
     * 
     * La fonction ajoute la clé et la valeur indiquées à la fin du tableau.
     * Si la clé existe déjà dans le tableau, la valeur existante est convertie 
     * en tableau et la valeur indiquée est ajoutée à la fin du tableau obtenu.
     * 
     * Cette fonction est utile pour gérer une liste de clés auxquelles peuvent 
     * être associées une ou plusieurs valeurs. Le tableau obtenu contiendra
     * toutes les clés indiquées, dans l'ordre dans lequel elles ont été 
     * rencontrées pour la première fois, et chaque clé indiquera la ou les 
     * valeurs associées. Pour chaque clé, count(value) indique le nombre de
     * fois ou la clé a été rencontrée.
     * 
     * Remarque : la valeur doit être de type scalaire ou objet, cela ne 
     * marchera pas si vous passez un tableau.
     * 
     * La fonction {@link arrayPrependKey()} est très similaire mais effectue
     * les insertions en ordre inverse. 
     * 
     * @param array $array le tableau à modifier
     * @param scalar $key la clé à ajouter au tableau
     * @param scalar|object $value la valeur associée à la clé
     */
    private static function arrayAppendKey(array & $array, $key, $value)
    {
        // Si la clé n'existe pas déjà, on l'insère à la fin du tableau
        if (!array_key_exists($key, $array))
        {
            $array[$key]=$value;
            return;
        }
        
        // La clé existe déjà
        $item=& $array[$key];
        
        // Si c'est déjà un tableau, ajoute la valeur à la fin du tableau
        if (is_array($item))
            $item[]=$value;
            
        // Sinon, crée un tableau contenant la valeur existante et la valeur indiquée
        else
            $item=array($item, $value);
    }
    
    /**
     * Ajoute une clé et une valeur dans un tableau.
     * 
     * La fonction ajoute la clé et la valeur indiquées au début du tableau.
     * Si la clé existe déjà dans le tableau, la valeur existante est convertie 
     * en tableau et la valeur indiquée est ajoutée au début du tableau obtenu.
     * 
     * Cette fonction est utile pour gérer une liste de clés auxquelles peuvent 
     * être associées une ou plusieurs valeurs. Le tableau obtenu contiendra
     * toutes les clés indiquées, en ordre inverse de l'ordre dans lequel elles 
     * ont été rencontrées pour la première fois, et chaque clé indiquera la ou 
     * les valeurs associées (en ordre inverse également).
     * 
     * Remarque : la valeur doit être de type scalaire ou objet, cela ne 
     * marchera pas si vous passez un tableau.
     * 
     * La fonction {@link arrayAppendKey()} est très similaire mais effectue
     * les insertions en ordre normal. 
     * 
     * @param array $array le tableau à modifier
     * @param scalar $key la clé à ajouter au tableau
     * @param scalar|object $value la valeur associée à la clé
     */
    private static function arrayPrependKey(array & $array, $key, $value)
    {
        // Si la clé n'existe pas déjà, on l'insère au début du tableau
        if (!array_key_exists($key, $array))
        {
            $array=array($key=>$value) + $array; // y-a-t-il un autre moyen ?
            return;
        }
        
        // La clé existe déjà
        $item=& $array[$key];
        
        // Si c'est déjà un tableau, ajoute la valeur au début du tableau
        if (is_array($item))
            array_unshift($item, $value);
            
        // Sinon, crée un tableau contenant la valeur indiquée et la valeur existante
        else
            $item=array($value, $item);
    }
    
    /**
     * Construit une query string à partir de la liste d'arguments passés en
     * paramètre.
     * 
     * Contrairement à la fonction php standard 
     * {@link http://php.net/http_build_query http_build_query()}, cette 
     * méthode gère correctement les arguments multi-valués (ie elle ne génère 
     * pas de crochets).
     * 
     * Par défaut, la fonction considère que les arguments sont déjà encodés
     * correctement. Dans le cas contraire, vous pouvez passer 'encode=true'
     * en paramètre. Dans ce cas, le nom et la valeur des arguments sera encodée 
     * en utilisant la fonction php {@link http://php.net/rawurlencode rawurlencode()} 
     * conformément à la {@link http://www.faqs.org/rfcs/rfc1738 RFC 1738}. 
     *
     * Exemple :
     * <code>
     * $args=array('ref'=>12, 'mcl'=>array('être', 'ne pas être'));
     * resultat : ?ref=12&mcl=%EAtre&mcl=ne%20pas%20%EAtre
     * </code>
     * 
     * @param array $args les arguments à mettre en query string
     * 
     * @param bool $encode indique s'il faut encoder ou non les données avec
     * la fonction php rawurlencode() (default : true)
     * 
     * @param null|string $separator le séparateur à utiliser entre les 
     * paramètres. Par défaut ($separator=null), la valeur de l'option de 
     * configuration 'arg_separator.output' de php.ini est utilisée (en général
     * il s'agit de la chaine '&').
     * 
     * @return string une chaine vide si $args était vide, une query string 
     * valide commençant par '?' sinon.
     * 
     * @see http://www.faqs.org/rfcs/rfc1738
     */
    public static function buildQueryString(array $args, $encode=false, $separator=null)
    {
        if (is_null($separator)) $separator=ini_get('arg_separator.output');
        
        $query='';
        if ($encode)
        {
            foreach($args as $name=>$value)
            {
                if (is_null($value))
                    $query.=(strlen($query) ? $separator : '?').rawurlencode($name);
                else
                {
                    foreach((array)$value as $value)
                        $query.=(strlen($query) ? $separator : '?').rawurlencode($name).'='.rawurlencode($value);
                }
            }
        }
        else
        {
            foreach($args as $name=>$value)
            {
                if (is_null($value))
                    $query.=(strlen($query) ? $separator : '?').$name;
                else
                {
                    foreach((array)$value as $value)
                        $query.=(strlen($query) ? $separator : '?').$name.'='.$value;
                }
            }
        }
        return $query;
    }
}
?>