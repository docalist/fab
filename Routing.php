<?php
/**
 * @package     fab
 * @subpackage  routing
 * @author      dmenard
 * @version     SVN: $Id$
 */


/**
 * Gestionnaire de routes.
 * 
 * Les gestionnaire de routes permet d'avoir des urls sémantiques. Il travaille
 * à partir d'un fichier de configuration (routes.yaml) qui pour chacune des
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
     * Transformer appellé par Config::load lorsqu'un fichier de routes est
     * compilé.
     * 
     * @param array $config tableau yaml contenant les routes
     * @return array tableau contenant la version compilée des routes
     */
    public static function transform($config)
    {
        $routes = array ();
        // Analyse chacune des routes indiquées
        foreach ($config as $name => $route)
        {

            // Extrait l'url
            if (!empty($route['url']))
                $url=$route['url'];
            else
                $url='/';

            // Détermine si l'url accepte ou non des query-strings
            if (substr($url, -1)=='*')
            {
            	$queryString=true;
                $url=rtrim($url, '*');
            }
            else
                $queryString=false;
                
            // Construit l'expression régulière correspondante            
            $re='~^';
            $names=array();
            $pt=0;
            $with=!empty($route['with']) ? $route['with'] : array();
            $ignoreCase=false;
            while ( ($i=strpos($url, '[', $pt)) !== false )
            {
                $h=substr($url, $pt, $i-$pt);
                if (preg_match('~[A-Za-z]~',$h)) $ignoreCase=true;
                $re.=preg_quote($h, '~');
                $pt=$i+1;
                
                if ( ($i=strpos($url, ']', $pt)) === false)
                    die("Syntaxe incorrecte dans la route $name : '$url', signe ']' attendu après le caractère $pt");
                
                $h=substr($url, $pt, $i-$pt);
                $names[]=$h;
    
                if (empty($with[$h]))
                    $mask= '.+?';
                else
                {
                    $mask=$with[$h];
                    if (preg_match('~[A-Za-z]~',$mask)) $ignoreCase=true;
                    $with[$h]='~^' . $with[$h] . '$~';
                }

                $re.="($mask)";
                $pt=$i+1;
            }
            $h=substr($url, $pt);
            if (preg_match('~[A-Za-z]~',$h)) $ignoreCase=true;
            $re .= preg_quote($h, '~');
            $re .= '$~';
            if ($ignoreCase) $re.='i';

            // Stocke la route            
            $t=array('url'=>$url, 'match'=>$re, 'querystring'=>$queryString);
            if (count($names)) $t['names']=$names;
            if (count($with)) $t['with']=$with;
            
            // supprime url et with, ce qui reste=les autres paramètres à utiliser lors de l'appel au module
            unset($route['url'], $route['with']);
            if (count($route)) $t['args']=$route;
            $routes[]=$t;
            if ($queryString)
            {
            	$t['querystring']=false;
                $routes[]=$t;
            }
        }

        // Tri les routes
        usort($routes, array('Routing','sortRoutes'));

        // Retourne le résultat            
        return $routes;
    }
    
    /**
     * Trie les routes
     * 
     * Pour déterminer le module à appeller, le gestionnaire de routes examine
     * les règles une par une, dans l'ordre où elles apparaissent. Il faut donc
     * que les règles soient triées de façon à ce que les règles les plus
     * spécifiques apparaissent avant les règles les plus générales.
     * Trier les règles par spécificité est compliqué. Pour compare deux
     * routes $a et $b, on va comparer plusieurs paramètres :
     * 
     * - $na (resp. $nb) : le nombre de 'champs' présents dans la route $a
     * (resp. $b))
     * 
     * - $qa (resp. $qb) : vrai si la règle $a (resp. $b) accepte des
     * arguments supplémentaires (query string).
     * 
     * L'algorithme de tri utilise ces variables pour déterminer si la route $a
     * doit être avant $b ($a &lt; $b), si elle doit être après ($a &gt; $b) ou
     * si les deux routes ont la même priorité (==).
     * 
     * Quand deux routes sont de même priorité ($a==$b), on effectue un tri
     * alphabétique décroissant sur la partie 'texte' des routes (ie les url
     * indiquéee, débarrassées des éventuels champs présents). Ce tri n'est pas
     * nécessaire pour le bon fonctionnement du routeur, mais permet de
     * regrouper ensemble les actions d'un même module. (Remarque : tri
     * décroissant pour que /base/[ref] soit après /base/[ref]/modifier, ie
     * la route la plus longue en premier).
     * 
     * Au final, on obtient un tableau logique de décision (cf source) qui
     * détermine l'ordre de tri.
     * 
     * Remarque : une règle * (qui accepte des query string) est gérée comme
     * deux règles distinctes : une règle sans l'étoile (qui sera plutôt en
     * début de liste) et une règle avec étoile qui arrivera plutôt en fin de
     * liste.
     * 
     * Exemple avec les routes /base/search* et /base/[titre]
     * todo: développer
     */
    /*

                       +------------------------+-----------------------------------+
                       |         $na==0         |              $na!=0               |
                       +------------+-----------+-----------------+-----------------+
                       | $qa==false | $qa==true |    $qa==false   |    $qa==true    |
    +-----+------------+------------+-----------+-----------------+-----------------+
    | $nb | $qb==false |     ==     |    a>b    |       a>b       |       a>b       |
    |  =  +------------+------------+-----------+-----------------+-----------------+
    |  0  | $qb==true  |    a<b     |    ==     |       a<b       |       a<b       |
    +-----+------------+------------+-----------+-----+-----+-----+-----+-----+-----+
    |     |            |            |           |na<nb|na=nb|na>nb|na<nb|na=nb|na>nb|
    |     | $qb==false |    a<b     |    a>b    +-----+-----+-----+-----+-----+-----+
    | $nb |            |            |           | a>b | ==  | a<b | a>b | a>b | a<b |
    | !=  +------------+------------+-----------+-----+-----+-----+-----+-----+-----+
    |  0  |            |            |           |na<nb|na=nb|na>nb|na<nb|na=nb|na>nb|
    |     | $qb==true  |    a<b     |    a>b    +-----+-----+-----+-----+-----+-----+
    |     |            |            |           | a>b | a<b | a<b | a>b | ==  | a<b |
    +-----+------------+------------+-----------+-----+-----+-----+-----+-----+-----+

    */

    public static function sortRoutes($a,$b, &$h='')
    {
        $na=isset($a['names']) ? count($a['names']) : 0;
        $nb=isset($b['names']) ? count($b['names']) : 0;
        
        $qa=$a['querystring'];
        $qb=$b['querystring'];
        
        $h='$na=' . $na . ', $nb='.$nb.', $qa='.($qa?'true':'false').', $qb='.($qb?'true':'false');
        
        if (($na==$nb) && ($qa==$qb))
        {
            $wa=isset($a['with']) ? count($a['with']) : 0;
            $wb=isset($b['with']) ? count($b['with']) : 0;
            if (($wb-$wa)==0)
                return -strcmp(preg_replace('~\[.*?\]~','',$a['url']), preg_replace('~\[.*?\]~','',$b['url']));
            return $wb-$wa;
        }
        
        if 
        (
                (   ($na===0) && ($qa===false)                      )
            ||  (   ($nb===0) && ($qb===true)                       )
            ||  (   ($na!=0) && ($nb !=0) && ($na > $nb)            )
            ||  (   ($na===$nb) && ($qb===true) && ($qa===false)    )
        ) return -1;
        
        return 1;
    }
    
    /**
     * Initialise la route à utiliser pour l'url passée en paramètre.
     * 
     * Si aucune route ne peut être établie pour cette url, dispatch un 'Not
     * found'.
     * 
     * @param string $url l'url à examiner
     */
    public static function setupRouteFor($url)
    {
        Debug::log("Recherche d'une route pour %s", $url);
        $matches=array(); // supprime warning 'variable not initialized'
        foreach (Config::get('routes') as $name=>$route)
        {
            if (preg_match($route['match'], $url, $matches))
            {
                // Ajoute les variables dans $_GET/$_REQUEST ou dans $_POST/$_REQUEST selon la méthode
                if (Utils::isGet())$t= & $_GET; else $t = & $_POST;          
                
                // réinitialise le module et l'action en cours
                unset($t['module'], $t['action']);  // sinon on obtient un tableau de modules
                                                    // si dispatch est appellée plusieurs fois
                
                if (isset($route['args']))
                {
                    foreach ($route['args'] as $name=>$value)
                    {
                        if (isset($t[$name]))
                        {
                            if (is_array($t[$name]))
                                $t[$name][]=$value;
                            else
                                $t[$name]=array($t[$name], $value);
                        }
                        else
                        {
                            $t[$name]=$value;
                        }
                        $_REQUEST[$name]= & $t[$name];
                    }
                }       
                
                if (isset($route['names']))
                {
                    foreach ($route['names'] as $index=>$name)
                    {
                        $matches[$index+1]=urldecode($matches[$index+1]);
                        if (isset($t[$name]))
                        {
                            if (is_array($t[$name]))
                                $t[$name][]=$matches[$index+1];
                            else
                                $t[$name]=array($t[$name], $matches[$index+1]);
                        }
                        else
                        {
                            $t[$name]=$matches[$index+1];
                        }
                        $_REQUEST[$name]= & $t[$name];
                    }
                }
                Debug::notice('Route trouvée : ' . Debug::dump($t));
                return;       
            }
        }
        
        Debug::notice('Aucune route trouvée pour %s', $url);
        // Aucune route trouvée : 404 page non trouvée
        self::notFound();
    }
    
    public static function dispatch($url)
    {
        debug && Debug::log("Dispatch de l'url");
        self::setupRouteFor($url);
        Module::run();
    }
    
    public static function notFound()
    {
        static $nb=0;
        if (++$nb>3) die('loop dans notfound');
        self::dispatch('/NotFound/'); // TODO: mettre dans la config le nom du module 'not found'
        Runtime::shutdown();
    }
    
    /**
     * Décompose une fab url et retourne un tableau contenant les clés 'module',
     * 'action' et chacun des paramètrs présents dans l'url.
     * 
     * S'il s'agit d'une faburl relative, le module et éventuellement l'action
     * sont déterminés par le module et l'action en cours.
     * 
     * S'il s'agit d'un lien vers la home page, module est mis à '/' et action à
     * 'index'. TODO: c'est ce qu'on veut ???
     */
    private static function parseFabUrl($url)
    {
        // Sépare la query string du reste
        $pt=strpos($url, '?');
        if ($pt !== false)
        {
            $query=substr($url, $pt+1);
            $url=substr($url, 0, $pt);
        }
        else
            $query='';
        
        // Pas d'url -> on prends le module et l'action en cours
        if ($url=='')
        {
            $module=$_REQUEST['module']; // module en cours
            $action=$_REQUEST['action']; // action en cours
        }
        
        // Commence par un '/' -> lien vers un module
        elseif ($url{0} == '/')
        {
            $pt=strpos($url, '/', 1);
            if ($pt===false)
            {
                $module=$url;
                $action='index'; // action par défaut
            }
            else
            {
                $module=substr($url, 0, $pt);
                $action=substr($url, $pt+1);
                if ($action=='') $action='index'; else $action=rtrim($action, '/');
            }
            if (strlen($module)>1) $module=ltrim($module, '/');
        }

        // pas de slash au début -> lien vers une action du module en cours
        else
        {
            $module=$_REQUEST['module'];
            $action=rtrim($url,'/');
        }

        $t=array('module'=>$module, 'action'=>$action);
        if($query)
        {
            foreach (explode('&', $query) as $item)
            {
                @list($key,$value)=explode('=', $item, 2);
                if (isset($t[$key]))
                {
                    $t[$key].=','.$value;
//                    if (is_array($t[$key]))
//                        $t[$key][]=$value;
//                    else
//                        $t[$key]=array($t[$key], $value);
                }
                else
                    $t[$key]=$value;
            }
        }   
    	return $t;
    }
    
    /**
     * Transforme un lien. Cette fonction est utilisée pour les templates dans
     * lesquels les liens sont créés sous la forme
     * 
     * /webs/show?ref=12&titre=essai
     * 
     * La fonction utilise les règles de routage pour déterminer la façon dont
     * le lien doit être affiché à l'utilisateur.
     * 
     * /webs/12-essai
     * 
     * Remarque : linkFor ne fait aucun encodage : c'est à l'appellant de s'assurer que 
     * les caractères spéciaux présents dans l'url, et notamment dans la query string, 
     * sont correctement encodés. Autrement dit : l'url doit être syntaxiquement correcte.
     * 
     * @param string $url l'url à transformer
     * @return string l'url à afficher à l'utilisateur
     */
    public static function linkFor($url, $absolute=false)
    {
        // Pas touche aux liens qui précisent un protocole (http, mailto, etc)
        if (preg_match('~^[a-z]{3,6}:~',$url)) return $url;

        // Décompose l'url
        $t=self::parseFabUrl($url);
//        echo Debug::dump($t) . '<br />';

        if ($t['module']=='/')
            return ($absolute ? Utils::getHost() : '') . rtrim(Runtime::$home,'/') . $url;
        
        // Si le lien pointe vers un répertoire existant du répertoire web,
        // il ne faut pas chercher à convertir le lien en module/action, mais juste
        // ajouter la racine du site web.
        // exemple: /images/logo.gif -> /apache/web/debug.php/images/logo.gif
        // remarque : peut-importe que le fichier existe réellement ou non (ça peut être un
        // script, il peut y avoir une redirection dans le .htaccess, etc..), on ne teste que
        // l'existence du répertoire de plus haut niveau.
        
        if (file_exists(Runtime::$webRoot . $t['module']))
            return ($absolute ? Utils::getHost() : '') . rtrim(Runtime::$realHome,'/') . $url;

        // Examine toutes les routes une par une
        foreach (Config::get('routes') as $name=>$route)
        {
            if (isset($route['names'])) $names=&$route['names']; else $names=array(); 
            if (isset($route['args'])) $args=&$route['args']; else $args=array(); 

            // Détermine si la route accepte une querystring
            $acceptQuery=strpos($route['url'], '*'); // * dans la route = accepte params supplémentaires

            // Vérifie que le nombre de paramètres indiqués dans l'url colle avec la route
            $nb=count($args)+count($names); // nombre total de paramètres requis par la route
            if ($route['querystring']===false)   // pas de query string : le nb de param doit coller exactement
                if (count($t) != $nb) continue;
            else                        // query string possible : on doit avoir au moins les params requis 
                if (count($t)<$nb) continue;
            
            // Si les arguments de la route (names+args) ne sont pas tous dans l'url, au suivant
//            if (count(array_diff(array_keys($t), $names, array_keys($args))))
//            {
//                continue;
//            }

            // Tous les noms présents dans la route doivent avoir été indiqués dans la fab url
            foreach ($names as $key)
                if (!isset($t[$key])) continue 2;
    
            // Toutes les variables de la route doivent avoir été indiquées dans la fab url 
            foreach ($args as $key => $value)
                if (!isset($t[$key]) || $t[$key] != $value) continue 2;

            // Les paramètrs de l'url doivent correspondre aux reg exp de la route
            if (isset($route['with']))
            {
                foreach ($route['with'] as $key => $re)
                    if (preg_match($re, $t[$key])===0) continue 2;
            }    
            // On a trouvé notre route !
            $result=rtrim(Runtime::$home,'/') . $route['url'];
            
            // Evite de faire apparaître l'action par défaut dans les urls
            if ($t['action']=='index') $t['action']='';
            
            // Remplace tous les noms présents dans l'url par leur valeur 
            foreach($names as $field)
            {
                $value=$t[$field];
                if (is_array($value)) $value=implode(',', $value);
                $result=str_replace("[$field]", $value, $result);
                unset($t[$field]);
            }
            
            // Vide tous les arguments de la faburl qui font partie de la route
            foreach($args as $key=>$value)
                unset($t[$key]);
            
            // S'il reste quelque chose, on ajoute tout en query string
            if (count($t))
            {
                if (!$route['querystring']) die('big pb !');
                $result.='?';
                $i=0;
                foreach ($t as $field=>$value)
                    $result.=($i++==0?'':'&').$field.'='. (is_array($value)?implode('&' . $field.'=', $value):$value);
            }

            // Retourne le résultat
            debug && Debug::log('linkFor(%s)=%s', $url, ($absolute ? Utils::getHost() : '').$result);
            return ($absolute ? Utils::getHost() : '') . $result;
            
        }
        debug && Debug::warning('linkFor : aucun lien pour %s', $url);
        return '';
    }
   // TODO: traiter les 'with'
   // TODO: '/ConfigModule/' ne marche pas, bug dans setupRoute
    
    public static function oldlinkFor($url, $absolute=false)
    {
        // TODO : mettre absolute url dans la config (tout le site passe en url absolues)
        // peut aussi être utile pour générer un email html, exporter une liste de liens (flash-email sfsp, etc.)
//        echo "<li>LinkFor $url</li>"; 
        if ($url=='') return '';
         
        // Pas touche aux liens qui précisent un protocole (http, mailto, etc)
        if (preg_match('~^[a-z]{3,6}:~',$url)) return $url;
            
        // Si l'utilisateur veut une url absolue, détermine l'adresse du serveur
        $host=$absolute ? Utils::getHost() : '';
        
        // Décompose l'url en module, action, querystring
        $nb=preg_match
        (
            '~
                ^                       # url commence par 
                (?:
                    /
                    (\w+)               # $1 : module
                    /?
                )?
                (
                    \w+                 # $2 : action
                )?
                /?
                (?:
                    \?
                    (.*)                    # $3 : query string
                )?
            ~x',
            $url,
            $matches);

        // Ajuste les valeurs par défaut
        if (! empty($matches[1]))  // module en cours
            $module=$matches[1];
        else
            $module=($url=='/' ? 'DefaultModule' : $_REQUEST['module']);
            
        if (!empty($matches[2]))
            $action=$matches[2];
        else
            $action='index'; // TODO: utiliser la config
            
        if (!empty($matches[3]))
            $query =$matches[3];
        else
            $query='';
//        echo "\"></a><p>url: $url, Module : $module, action: $action, query: $query</p>";
//        echo "<pre>nb=$nb - " . print_r($matches,true)."</pre>";
//        echo "<pre>module:$module, action:$action, query:$query</pre>";
//return;
        // Si le lien pointe vers un répertoire qui existe dans le répertoire,
        // il ne faut pas chercher à convertir le lien en module/action, mais juste
        // ajouter la racine du site web.
        // exemple: /images/logo.gif -> /apache/web/images/logo.gif
        // et non pas module 'images', action 'logo.gif'
        if (file_exists(Runtime::$webRoot . $module))
        {
//            echo 'FILEEXISTS. ' . $url . ' lien retourné : ' . $host . rtrim(Runtime::$realHome,'/') . $url . '<br />' . "\n";
            return $host . rtrim(Runtime::$realHome,'/') . $url;
        }
        
        // Construit un tableau urlName contenant le module, l'action et tous les paramêtres indiqués
        $urlNames=array('module'=>$module, 'action'=>$action);
        if ($query != '')
        {
            foreach (explode('&', $query) as $item)
            {
                @list($key,$value)=explode('=', $item, 2);
                if (isset($urlNames[$key]))
                {
                    if (is_array($urlNames[$key]))
                        $urlNames[$key][]=$value;
                    else
                        $urlNames[$key]=array($urlNames[$key], $value);
                }
                else
                    $urlNames[$key]=$value;
            }
        }
//        $urlArgs=array();

        // Ok, maintenant essaie de trouver une route qui correspond
                
//        echo "<h1>Url : $url</h1><pre>urlNames=" . print_r($urlNames, true) .  "</pre>";           

        // Examine toutes les routes une par une
        foreach (Config::get('routes') as $name=>$route)
        {

            if (isset($route['names'])) $names=$route['names']; else $names=array(); 
            if (isset($route['args'])) $args=$route['args']; else $args=array(); 

//            echo "<li>Examen de la route <strong>${route['url']}</strong> <pre>Noms=" . print_r($names, true) . "\nArgs=" . print_r($args,true)."</pre>";           

            // Si les arguments de la route (names+args) ne sont pas tous dans l'url, au suivant
            if (count(array_diff(array_keys($urlNames), $names, array_keys($args))))
            {
                continue;
            }
            
//            foreach ($names as $key)
//            {
//                if (!isset($urlNames[$key])) 
//                {
//            //    	echo "<p>$key pas dans urlNames";
//                    continue 2;
//                }
//            }
    
            // we must match all defaults with value except if present in names
            // Toutes les valeurs par défaut indiquées dans la route doivent correspondre
            // à celles indiquées dans l'url ? 
            foreach ($args as $key => $value)
            {
                if (isset($names[$key])) continue;
    
                if (!isset($urlNames[$key]) || $urlNames[$key] != $value) 
                {
                //  echo "<p>$key='$value' ne colle pas avec ce qu'il y a dans urlArgs"; 
                    continue 2;
                }
            }

            // On a trouvé notre route !
            $result=$route['url'];
            foreach($names as $field)
            {
                $value=$urlNames[$field];
                if (is_array($value)) $value=implode(',', $value);
            	$result=str_replace("[$field]", $value, $result);
            }
            //if ($result{0}='/') $result=substr($result, 1);
//            if ($result=='')
//            {
//            	echo "route vide !<pre>";
//                print_r($route);
//                echo '</pre>';
//            }
            //echo "<li>LinkFor [$url]=[$result]";
            $result=rtrim(Runtime::$home,'/') . $result;
//            echo "<li>Result $host$result</li>"; 
            
            debug && Debug::log('linkFor(%s)=%s', $url, $host.$result);
            return $host . $result;
            //echo sprintf("<li style='color: red;font-weight:bold'>%2s : %s</li>", $name, $result);
            
        }
        debug && Debug::warning('linkFor : aucun lien pour %s. Retourne %s', $url, $host.$url);
        return '';
    }
}
?>