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
 * Les gestionnaire de routes permet d'avoir des urls s�mantiques. Il travaille
 * � partir d'un fichier de configuration (routes.yaml) qui pour chacune des
 * urls que l'utilisateur peut appeller d�finit le module et l'action �
 * appeller ainsi que les param�tres � passer.
 * 
 * Le fichier est aussi utilis� en sens inverse : lorsque dans un template on
 * fait un lien vers une autre action ou un autre module, le lien est
 * automatiquement ajust� en fonction de la configuration.
 * 
 * @package     fab
 * @subpackage  routing
 */
class Routing
{
    /**
     * Transformer appell� par Config::load lorsqu'un fichier de routes est
     * compil�.
     * 
     * @param array $config tableau yaml contenant les routes
     * @return array tableau contenant la version compil�e des routes
     */
    public static function transform($config)
    {
        $routes = array ();
        // Analyse chacune des routes indiqu�es
        foreach ($config as $name => $route)
        {

            // Extrait l'url
            if (!empty($route['url']))
                $url=$route['url'];
            else
                $url='/';

            // D�termine si l'url accepte ou non des query-strings
            if (substr($url, -1)=='*')
            {
            	$queryString=true;
                $url=rtrim($url, '*');
            }
            else
                $queryString=false;
                
            // Construit l'expression r�guli�re correspondante            
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
                    die("Syntaxe incorrecte dans la route $name : '$url', signe ']' attendu apr�s le caract�re $pt");
                
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
            
            // supprime url et with, ce qui reste=les autres param�tres � utiliser lors de l'appel au module
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

        // Retourne le r�sultat            
        return $routes;
    }
    
    /**
     * Trie les routes
     * 
     * Pour d�terminer le module � appeller, le gestionnaire de routes examine
     * les r�gles une par une, dans l'ordre o� elles apparaissent. Il faut donc
     * que les r�gles soient tri�es de fa�on � ce que les r�gles les plus
     * sp�cifiques apparaissent avant les r�gles les plus g�n�rales.
     * Trier les r�gles par sp�cificit� est compliqu�. Pour compare deux
     * routes $a et $b, on va comparer plusieurs param�tres :
     * 
     * - $na (resp. $nb) : le nombre de 'champs' pr�sents dans la route $a
     * (resp. $b))
     * 
     * - $qa (resp. $qb) : vrai si la r�gle $a (resp. $b) accepte des
     * arguments suppl�mentaires (query string).
     * 
     * L'algorithme de tri utilise ces variables pour d�terminer si la route $a
     * doit �tre avant $b ($a &lt; $b), si elle doit �tre apr�s ($a &gt; $b) ou
     * si les deux routes ont la m�me priorit� (==).
     * 
     * Quand deux routes sont de m�me priorit� ($a==$b), on effectue un tri
     * alphab�tique d�croissant sur la partie 'texte' des routes (ie les url
     * indiqu�ee, d�barrass�es des �ventuels champs pr�sents). Ce tri n'est pas
     * n�cessaire pour le bon fonctionnement du routeur, mais permet de
     * regrouper ensemble les actions d'un m�me module. (Remarque : tri
     * d�croissant pour que /base/[ref] soit apr�s /base/[ref]/modifier, ie
     * la route la plus longue en premier).
     * 
     * Au final, on obtient un tableau logique de d�cision (cf source) qui
     * d�termine l'ordre de tri.
     * 
     * Remarque : une r�gle * (qui accepte des query string) est g�r�e comme
     * deux r�gles distinctes : une r�gle sans l'�toile (qui sera plut�t en
     * d�but de liste) et une r�gle avec �toile qui arrivera plut�t en fin de
     * liste.
     * 
     * Exemple avec les routes /base/search* et /base/[titre]
     * todo: d�velopper
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
     * Initialise la route � utiliser pour l'url pass�e en param�tre.
     * 
     * Si aucune route ne peut �tre �tablie pour cette url, dispatch un 'Not
     * found'.
     * 
     * @param string $url l'url � examiner
     */
    public static function setupRouteFor($url)
    {
        Debug::log("Recherche d'une route pour %s", $url);
        $matches=array(); // supprime warning 'variable not initialized'
        foreach (Config::get('routes') as $name=>$route)
        {
            if (preg_match($route['match'], $url, $matches))
            {
                // Ajoute les variables dans $_GET/$_REQUEST ou dans $_POST/$_REQUEST selon la m�thode
                if (Utils::isGet())$t= & $_GET; else $t = & $_POST;          
                
                // r�initialise le module et l'action en cours
                unset($t['module'], $t['action']);  // sinon on obtient un tableau de modules
                                                    // si dispatch est appell�e plusieurs fois
                
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
                Debug::notice('Route trouv�e : ' . Debug::dump($t));
                return;       
            }
        }
        
        Debug::notice('Aucune route trouv�e pour %s', $url);
        // Aucune route trouv�e : 404 page non trouv�e
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
     * D�compose une fab url et retourne un tableau contenant les cl�s 'module',
     * 'action' et chacun des param�trs pr�sents dans l'url.
     * 
     * S'il s'agit d'une faburl relative, le module et �ventuellement l'action
     * sont d�termin�s par le module et l'action en cours.
     * 
     * S'il s'agit d'un lien vers la home page, module est mis � '/' et action �
     * 'index'. TODO: c'est ce qu'on veut ???
     */
    private static function parseFabUrl($url)
    {
        // S�pare la query string du reste
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
                $action='index'; // action par d�faut
            }
            else
            {
                $module=substr($url, 0, $pt);
                $action=substr($url, $pt+1);
                if ($action=='') $action='index'; else $action=rtrim($action, '/');
            }
            if (strlen($module)>1) $module=ltrim($module, '/');
        }

        // pas de slash au d�but -> lien vers une action du module en cours
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
     * Transforme un lien. Cette fonction est utilis�e pour les templates dans
     * lesquels les liens sont cr��s sous la forme
     * 
     * /webs/show?ref=12&titre=essai
     * 
     * La fonction utilise les r�gles de routage pour d�terminer la fa�on dont
     * le lien doit �tre affich� � l'utilisateur.
     * 
     * /webs/12-essai
     * 
     * Remarque : linkFor ne fait aucun encodage : c'est � l'appellant de s'assurer que 
     * les caract�res sp�ciaux pr�sents dans l'url, et notamment dans la query string, 
     * sont correctement encod�s. Autrement dit : l'url doit �tre syntaxiquement correcte.
     * 
     * @param string $url l'url � transformer
     * @return string l'url � afficher � l'utilisateur
     */
    public static function linkFor($url, $absolute=false)
    {
        // Pas touche aux liens qui pr�cisent un protocole (http, mailto, etc)
        if (preg_match('~^[a-z]{3,6}:~',$url)) return $url;

        // D�compose l'url
        $t=self::parseFabUrl($url);
//        echo Debug::dump($t) . '<br />';

        if ($t['module']=='/')
            return ($absolute ? Utils::getHost() : '') . rtrim(Runtime::$home,'/') . $url;
        
        // Si le lien pointe vers un r�pertoire existant du r�pertoire web,
        // il ne faut pas chercher � convertir le lien en module/action, mais juste
        // ajouter la racine du site web.
        // exemple: /images/logo.gif -> /apache/web/debug.php/images/logo.gif
        // remarque : peut-importe que le fichier existe r�ellement ou non (�a peut �tre un
        // script, il peut y avoir une redirection dans le .htaccess, etc..), on ne teste que
        // l'existence du r�pertoire de plus haut niveau.
        
        if (file_exists(Runtime::$webRoot . $t['module']))
            return ($absolute ? Utils::getHost() : '') . rtrim(Runtime::$realHome,'/') . $url;

        // Examine toutes les routes une par une
        foreach (Config::get('routes') as $name=>$route)
        {
            if (isset($route['names'])) $names=&$route['names']; else $names=array(); 
            if (isset($route['args'])) $args=&$route['args']; else $args=array(); 

            // D�termine si la route accepte une querystring
            $acceptQuery=strpos($route['url'], '*'); // * dans la route = accepte params suppl�mentaires

            // V�rifie que le nombre de param�tres indiqu�s dans l'url colle avec la route
            $nb=count($args)+count($names); // nombre total de param�tres requis par la route
            if ($route['querystring']===false)   // pas de query string : le nb de param doit coller exactement
                if (count($t) != $nb) continue;
            else                        // query string possible : on doit avoir au moins les params requis 
                if (count($t)<$nb) continue;
            
            // Si les arguments de la route (names+args) ne sont pas tous dans l'url, au suivant
//            if (count(array_diff(array_keys($t), $names, array_keys($args))))
//            {
//                continue;
//            }

            // Tous les noms pr�sents dans la route doivent avoir �t� indiqu�s dans la fab url
            foreach ($names as $key)
                if (!isset($t[$key])) continue 2;
    
            // Toutes les variables de la route doivent avoir �t� indiqu�es dans la fab url 
            foreach ($args as $key => $value)
                if (!isset($t[$key]) || $t[$key] != $value) continue 2;

            // Les param�trs de l'url doivent correspondre aux reg exp de la route
            if (isset($route['with']))
            {
                foreach ($route['with'] as $key => $re)
                    if (preg_match($re, $t[$key])===0) continue 2;
            }    
            // On a trouv� notre route !
            $result=rtrim(Runtime::$home,'/') . $route['url'];
            
            // Evite de faire appara�tre l'action par d�faut dans les urls
            if ($t['action']=='index') $t['action']='';
            
            // Remplace tous les noms pr�sents dans l'url par leur valeur 
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

            // Retourne le r�sultat
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
        // peut aussi �tre utile pour g�n�rer un email html, exporter une liste de liens (flash-email sfsp, etc.)
//        echo "<li>LinkFor $url</li>"; 
        if ($url=='') return '';
         
        // Pas touche aux liens qui pr�cisent un protocole (http, mailto, etc)
        if (preg_match('~^[a-z]{3,6}:~',$url)) return $url;
            
        // Si l'utilisateur veut une url absolue, d�termine l'adresse du serveur
        $host=$absolute ? Utils::getHost() : '';
        
        // D�compose l'url en module, action, querystring
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

        // Ajuste les valeurs par d�faut
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
        // Si le lien pointe vers un r�pertoire qui existe dans le r�pertoire,
        // il ne faut pas chercher � convertir le lien en module/action, mais juste
        // ajouter la racine du site web.
        // exemple: /images/logo.gif -> /apache/web/images/logo.gif
        // et non pas module 'images', action 'logo.gif'
        if (file_exists(Runtime::$webRoot . $module))
        {
//            echo 'FILEEXISTS. ' . $url . ' lien retourn� : ' . $host . rtrim(Runtime::$realHome,'/') . $url . '<br />' . "\n";
            return $host . rtrim(Runtime::$realHome,'/') . $url;
        }
        
        // Construit un tableau urlName contenant le module, l'action et tous les param�tres indiqu�s
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
            // Toutes les valeurs par d�faut indiqu�es dans la route doivent correspondre
            // � celles indiqu�es dans l'url ? 
            foreach ($args as $key => $value)
            {
                if (isset($names[$key])) continue;
    
                if (!isset($urlNames[$key]) || $urlNames[$key] != $value) 
                {
                //  echo "<p>$key='$value' ne colle pas avec ce qu'il y a dans urlArgs"; 
                    continue 2;
                }
            }

            // On a trouv� notre route !
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