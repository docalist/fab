<?php
/**
 * @package     fab
 * @subpackage  request
 * @author      Daniel Ménard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id$
 */

/**
 * Une requête représentant l'environnement et les paramètres de l'action
 * qui sera exécutée.
 * 
 * L'objet Request est destiné à éviter que les tableaux $_GET, $_POST, etc.
 * soient accédés directement.
 * 
 * Certaines fonctions retourne $this pour permettre de chainer les appels de 
 * méthode :
 * 
 * <code>
 * $request
 *     ->setModule('thesaurus')
 *     ->setAction('search')
 *     ->set('query', 'health');
 * </code>
 * 
 * Request propose également des fonctions (chainées) permettant de valider
 * les paramètres de la requête :
 * 
 * <code>
 * $request
 *     ->required('ref')
 *     ->int()
 *     ->unique()
 *     ->min(1);
 * </code>
 *  
 * @package     fab
 * @subpackage  request
 */
class Request
{
    /**
     * Les paramètres de la requête
     *
     * @var array
     */
    private $_parameters=array();
    
    /**
     * Le nom exact du module auquel est destinée cette requête ou null
     * si la requête n'a pas encore été routée
     *
     * @var string|null
     */
    private $_module=null;

    /**
     * Le nom exact de l'action à laquelle est destinée cette requête ou null
     * si la requête n'a pas encore été routée
     *
     * @var string|null
     */
    private $_action=null;
    
    /**
     * Nom de la variable en cours de validation
     *
     * @var string
     */
    private $_checkName;
    
    /**
     * Valeur actuelle de la variable en cours de validation
     *
     * @var mixed
     */
    private $_check;

    /**
     * Construit un nouvel objet Request avec les paramètres indiqués.
     * 
     * Des paramètres supplémentaires peuvent être ajoutés à la requête
     * en utilisant {@link set()} et {@link add()}
     * 
     * @param array $parameters ... des tableaux contenant les paramètres 
     * initiaux de la requête.
     */
    public function __construct(array $parameters=array())
    {
        $this->_parameters=$parameters;
    
        $args=func_get_args();
        array_shift($args);
        foreach($args as $arg)
        {
            $this->addParameters($arg);
        }
    }
    
    /**
     * Méthode statique permettant de créer un nouvel objet Request avec les 
     * paramètres indiqués.
     * 
     * Php ne permet pas de chainer des méthodes après un appel à new :
     * <code>$request=new Request()->setAction('/');</code> génère une erreur.
     * 
     * La méthode statique create permet de contourner le problème en écrivant :
     * <code>$request=Request::create()->setAction('/');</code>
     * 
     * @param array $parameters ... des tableaux contenant les paramètres 
     * initiaux de la requête.
     */
    public static function create(array $parameters=array())
    {
        return new self($parameters);
    }
    
    /**
     * Clone la requête en cours
     *
     * @return Request
     */
    public function copy()
    {
        return clone $this;
    }

    /**
     * Ajoute un tableau de paramètres à la requête
     *
     * @param array $parameters
     * @return Request $this pour permettre le chainage des appels de méthodes
     */
    public function addParameters(array $parameters)
    {
        foreach($parameters as $key=>$value)
        {
            // Si la clé n'existe pas déjà, on l'insère à la fin du tableau
            if (!array_key_exists($key, $this->_parameters))
            {
                $this->_parameters[$key]=$value;
                continue;
            }
            
            // Existe déjà, c'est un tableau, ajoute la valeur à la fin
            if (is_array($value))
            {
                if (is_array($value))   // tableau + tableau
                {
                    $this->_parameters[$key]=array_merge($this->_parameters[$key], $value);
                }
                else                    // tableau + valeur
                {
                    $this->_parameters[$key][]=$value;
                }
            }    

            // Existe déjà, simple valeur, crée un tableau contenant la valeur existante et la valeur indiquée
            else
            {
                if (is_array($value))   // valeur + tableau
                {
                    $this->_parameters[$key]=array_merge(array($this->_parameters[$key]), $value);
                }
                else                    // valeur + valeur
                {
                    $this->_parameters[$key]=array($this->_parameters[$key], $value);
                }
            }
        }
        return $this;
    }
    
    /**
     * Retourne le nom exact du module auquel est destiné la requête ou null
     * si la requête n'a pas encore été routée
     *
     * @return string|null
     */
    public function getModule()
    {
        return $this->_module;
    }
    
    
    /**
     * Modifie le nom du module auquel est destinée la requête
     *
     * @param string $module
     * @return Request $this pour permettre le chainage des appels de méthodes
     */
    public function setModule($module=null)
    {
        $this->_module=$module;
        return $this;
    }
    
    
    /**
     * Retourne le nom exact de l'action à laquelle est destinée la requête ou 
     * null si la requête n'a pas encore été routée
     *
     * @return string|null
     */
    public function getAction()
    {
        return $this->_action;
    }
    
    
    /**
     * Modifie le nom de l'action à laquelle est destinée la requête
     *
     * @param string $action la nouvelle action de la requête
     * @return Request $this pour permettre le chainage des appels de méthodes
     */
    public function setAction($action=null)
    {
        $this->_action=$action;
        return $this;
    }
    
    
    /**
     * Retourne la valeur du paramètre indiqué ou null si le nom indiqué ne 
     * figure pas dans les paramètres de la requête.
     * 
     * __get est une méthode magique de php qui permet d'accéder aux paramètres
     * de la requête comme s'il s'agissait de propriétés de l'objet Request
     * (par exemple <code>$request->item</code>)
     *
     * La méthode {@link get()} est similaire mais permet d'indiquer une valeur
     * par défaut.
     * 
     * @param string $key
     * @return mixed
     */
    public function __get($key)
    {
        if (array_key_exists($key, $this->_parameters))
            return $this->_parameters[$key];
        return null;
    }
    
    
    /**
     * Retourne la valeur du paramètre indiqué ou la valeur par défaut spécifiée
     * si le nom indiqué ne figure pas dans les paramètres de la requête.
     * 
     * get est similaire à {@link __get()} mais permet d'indiquer une valeur par
     * défaut (par exemple <code>$request->get('item', 'abc')</code>)
     * 
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get($key, $default=null)
    {
        if (is_null($value=$this->__get($key))) 
            return $default;
        else
            return $value;
    }
    
    
    /**
     * Modifie la valeur de paramètre indiqué
     * 
     * __set est une méthode magique de php qui permet de modifier les 
     * paramètres de la requête comme s'il s'agissait de propriétés de 
     * l'objet Request (par exemple <code>$request->item = 12</code>)
     * 
     * Set remplace complètement la valeur existante. Pour ajouter une valeur
     * à un paramètre existant, utiliser {@link add()}
     *
     * @param string $key
     * @param mixed $value
     */
    public function __set($key, $value)
    {
        $this->_parameters[$key]=$value;    
    }
    
    
    /**
     * Alias de {@link __set()}
     * 
     * Exemple : $request->set('item', 12)
     *
     * @param string $key
     * @param mixed $value
     * @return Request $this pour permettre le chainage des appels de méthodes
     */
    public function set($key, $value)
    {
        $this->__set($key, $value);
        return $this;    
    }
    
    /**
     * Supprime tous les paramètres de la requête sauf ceux dont le nom est 
     * indiqué en paramètre.
     * 
     * Exemple : 
     * <code>
     * $request->keepOnly('REF'); // supprime tous sauf REF
     * </code>
     *
     * @param string $arg nom du premier paramètre à conserver. Vous pouvez 
     * indiquer autant d'argument arg que nécessaire 
     * @return Request $this pour permettre le chainage des appels de méthodes
     */
    public function keepOnly($arg)
    {
        $args=func_get_args();
        $this->_parameters=array_intersect_key($this->_parameters, array_flip($args));
        return $this;        
    }
    
    /**
     * Détruit le paramètre indiqué
     * 
     * __unset est une méthode magique de php qui permet de supprimer les 
     * paramètres de la requête comme s'il s'agissait de propriétés de 
     * l'objet Request (par exemple <code>unset($request->item)</code>)
     * 
     * @param string $key
     */
    public function __unset($key)
    {
        unset($this->_parameters[$key]);
    }
    
    
    /**
     * Alias de {@link __unset()}
     * 
     * Exemple : <code>$request->clear('item')</code>
     *
     * @param string $key
     * @return Request $this pour permettre le chainage des appels de méthodes
     * 
     * @todo : accepter plusieurs paramètres pour permettre de vider
     * plusieurs arguments d'un coup
     */
    public function clear($key=null)
    {
        if (is_null($key))
        {
            $this->_parameters=array();
        }
        else
        {
            unset($this->_parameters[$key]);
        }
        return $this;
    }

    /**
     * Supprime de la requête tous les paramètres dont la valeur est
     * une chaine vide, un tableau vide ou la valeur null.
     * 
     * @return Request $this pour permettre le chainage des appels de méthodes
     */
    public function clearNull()
    {
        foreach($this->_parameters as $key=>&$value)
        {
            if ($value===null or $value==='' or $value===array())
            {
                unset($this->_parameters[$key]);
            }
            elseif(is_array($value))
            {
                foreach($value as $key=>$item)
                {
                    if ($item===null or $item==='' or $item===array())
                    {
                        unset($value[$key]);
                    }
                }
            }
        }
        return $this;
    }
    
    /**
     * Indique si la requête contient des paramètres 
     *
     * @return bool
     */
    public function hasParameters()
    {
        return count($this->_parameters)!==0;
    }
    
    
    /**
     * Retourne la liste des paramètres présents dans la requête 
     *
     * @return array
     */
    public function getParameters()
    {
        return $this->_parameters;
    }
    
    
    /**
     * Détermine si le paramètre indiqué est défini dans la requête
     * 
     * __isset() est une méthode magique de php qui permet de tester l'existence
     * d'un paramètre comme s'il s'agissait d'une propriété de l'objet Request.
     * 
     * La fonction {@link has()} fait la même chose mais prend le nom de 
     * l'argument en paramètre.
     * 
     * @param string $key
     * @return bool
     */
    public function __isset($key)
    {
        return isset($this->_parameters[$key]);
    }
    
    /**
     * Détermine si le paramètre indiqué existe dans la requête
     * 
     * La fonction retourne true même si le paramètre à la valeur null
     * 
     * @param string $key
     * @return bool
     */
    public function has($key)
    {
        return array_key_exists($key, $this->_parameters);
    }
    
    
    /**
     * Ajoute une valeur au paramètre indiqué
     * 
     * Add ajoute le paramètre indiqué à la liste des paramètres de la requête.
     * Si le paramètre indiqué existait déjà, la valeur existante est transformée 
     * en tableau et la valeur indiquée est ajoutée au tableau obtenu.
     * 
     * Pour remplacer complètement la valeur d'un paramètre existant, utiliser 
     * {@link set()}
     *
     * @param string $key
     * @param mixed $value
     * @return Request $this pour permettre le chainage des appels de méthodes
     */
    public function add($key, $value)
    {
        // Si la clé n'existe pas déjà, on l'insère à la fin du tableau
        if (!array_key_exists($key, $this->_parameters))
        {
            $this->_parameters[$key]=$value;
            return $this;
        }
        
        // La clé existe déjà
        $item=& $this->_parameters[$key];
        
        // Si c'est déjà un tableau, ajoute la valeur à la fin du tableau
        if (is_array($item))
            $item[]=$value;
            
        // Sinon, crée un tableau contenant la valeur existante et la valeur indiquée
        else
            $item=array($item, $value);
            
        return $this;
    }
    
    
    /**
     * Retourne la valeur d'un paramètre figurant dans un autre tableau
     * que {@link _parameters} ou la valeur par défaut indiquée
     *
     * @param array $array le tableau dans lequel la clé indiquée va être 
     * recherchée
     * @param string $key la clé à rechercher
     * @param mixed $default la valeur par défaut à retourner si $key ne figure 
     * pas dans le tableau $array
     * @return string|null
     */
    protected function other($array, $key, $default=null)
    {
        if (array_key_exists($key, $array))
            return $array[$key];
        return $default;
    }
    
    
    /**
     * Retourne la valeur d'une variable du server
     *
     * @param string $key
     * @param mixed $default
     * @return string|null
     */
    public function server($key, $default=null)
    {
        return $this->other($_SERVER, $key, $default);
    }

    
    /**
     * Retourne la valeur d'une variable d'environnement
     *
     * @param string $key
     * @param mixed $default
     * @return string|null
     */
    public function env($key, $default=null)
    {
        return $this->other($_ENV, $key, $default);
    }

    
    /**
     * Retourne la valeur d'un cookie
     *
     * @param string $key
     * @param mixed $default
     * @return string|null
     */
    public function cookie($key, $default=null)
    {
        return $this->other($_COOKIE, $key, $default);
    }
    
    
    /**
     * Détermine si la requête en cours est une requête ajax ou non.
     * 
     * La détection est basée sur la présence ou non de l'entête http
     * <code>X_REQUESTED_WITH</code> qui est ajouté à la requête http par 
     * les librairies ajax les plus courante (cas de prototype, jquery, YUI,  
     * mais pas de dojo).
     * 
     * @return bool true si la requête http contient un entête 
     * <code>X_REQUESTED_WITH</code> contenant la valeur
     * <code>XMLHttpRequest</code> (sensible à la casse)
     */
    public function isAjax()
    {
        return ($this->server('HTTP_X_REQUESTED_WITH') === 'XMLHttpRequest');
    }
    
    
    /**
     * Fonction exécutée à chaque fois qu'une fonction de validation est appellée.
     * 
     * Si $key est non null, une nouvelle validation commence et la fonction
     * retourne la valeur de ce paramètre.
     * 
     * Si $ref est null, la validation en cours continue et la fonction retourne
     * la valeur actuelle du paramètre en cours.
     * 
     * Une exception est générée si check est appellée avec $key==null et qu'il 
     * n'y a pas de validation en cours et si check() est appellée avec un nom
     * alors qu'il y a déjà une validation en cours. 
     *
     * @param string|null $key
     * @return mixed
     */
    private function check($key=null)
    {
        if (is_null($key))
        {
            if (is_null($this->_checkName))
            {
                $stack=debug_backtrace();
                throw new BadMethodCallException(sprintf('%s::%s() appellée sans indiquer le nom du paramètre à vérifier', __CLASS__, $stack[1]['function']));
            }
        }
        else
        {
            if (!is_null($this->_checkName))
            {
                $stack=debug_backtrace();
                throw new BadMethodCallException(sprintf('Appel de %s::%s("%s") alors que "%s" est déjà en cours de validation. Oubli de ok() au dernier appel ?' , __CLASS__, $stack[1]['function'], $key, $this->_checkName));
            }
            $this->_check=$this->get($key);
            $this->_checkName=$key;
        }
        return $this->_check;    
    }
    
    
    /**
     * Validation : vérifie un paramètre requis
     * 
     * Le paramètre est considéré comme 'absent' si sa valeur est null, un 
     * tableau vide, une chaine vide ou une chaine ne contenant que des blancs.
     * 
     * Si la valeur du paramètre est un tableau, le test est appliqué à chacun
     * des éléments du tableau.
     * 
     * La valeur actuelle du paramètre en cours de validation n'est pas modifiée
     * par le test.
     * 
     * Exemples d'utilisation :
     * 
     * <code>
     * $request->required('item')->ok();
     * $request->bool('item')->required()->ok();
     * </code>
     * 
     * @param string|null $key
     * @return Request $this pour permettre le chainage des appels de méthodes
     * @throws RequestParameterRequired si le test échoue
     */
    public function required($key=null)
    {
        $value=(array)$this->check($key);
        
        if (count($value)===0)
        {
            throw new RequestParameterRequired($this->_checkName);
        }
            
        foreach((array)$value as $value)
        {
            if (is_null($value) || (is_string($value) && trim($value)===''))
            {
                throw new RequestParameterRequired($this->_checkName);
            }
        }
                
        return $this;
    }
    
    /**
     * Validation : définit la valeur par défaut d'un paramètre de la requête
     *
     * @param string|null $key
     * @param scalar $default
     * 
     * @return Request $this pour permettre le chainage des appels de méthodes
     */
    public function defaults($key, $default=null)
    {
        if (is_null($default))
        {
            $default=$key;
            $key=null;
        }
        $value=$this->check($key);
        
        if (is_null($value))
        {
            $this->_check=$default;
        }
            
        return $this;
    }
    

    /**
     * Validation : vérifie un booléen
     * 
     * bool() reconnaît les booléens mais aussi les chaines 
     * <code>'true','on', '1' </code> et <code>'false','off','0'</code>
     * (quelque soit la casse et les espaces de début ou de fin éventuels).
     * 
     * Si la valeur du paramètre est un tableau, le test est appliqué à chacun
     * des éléments du tableau.
     * 
     * A l'issue du test, la valeur du paramètre en cours de validation est 
     * toujours un booléen ou un tableau de booléens
     *  
     * Exemples d'utilisation :
     * <code>
     * $request->bool('flag')->ok();
     * $request->required('flag')->bool()->ok();
     * </code>
     * 
     * @param string|null $key
     * @return Request $this pour permettre le chainage des appels de méthodes
     * 
     * @throws RequestParameterBoolExpected si le test échoue
     */
    public function bool($key=null)
    {
        $this->check($key);
        foreach((array)$this->_check as $i=>$value)
        {
            if (is_bool($value))
            {
                // ok
            }
            elseif (is_int($value) && ($value===0 || $value===1))
            {
                $value=(bool)$value;
            }
            elseif (is_string($value))
            {
                $t=array('true'=>true, 'false'=>false, 'on'=>true, 'off'=>false, '1'=>true, '0'=>false);
                $key=strtolower(trim($value));
                if (array_key_exists($key,$t))
                {
                    $value=$t[$key];
                }
                else
                {
                    throw new RequestParameterBoolExpected($this->_checkName, $value);
                }
            }
            else
            {
                throw new RequestParameterBoolExpected($this->_checkName, $value);
            }
            if (is_array($this->_check))
            {
                $this->_check[$i]=$value;
            }
            else
            {
                $this->_check=$value;
            }
        }
        return $this;
    }

    
    /**
     * Validation : vérifie un booléen
     * 
     * int() reconnaît les entiers mais aussi les chaines représentant un entier
     * (les espaces de début ou de fin éventuels sont ignorés).
     * 
     * Si la valeur du paramètre est un tableau, le test est appliqué à chacun
     * des éléments du tableau.
     * 
     * A l'issue du test, la valeur du paramètre en cours de validation est 
     * toujours un entier ou un tableau d'entiers.
     *  
     * Exemples d'utilisation :
     * 
     * <code>
     * $request->int('nb')->ok();
     * $request->required('nb')->int()->ok();
     * </code>
     * 
     * @param string|null $key
     * @return Request $this pour permettre le chainage des appels de méthodes
     * 
     * @throws RequestParameterIntExpected si le test échoue
     */
    public function int($key=null)
    {
        $this->check($key);
        
        foreach((array)$this->_check as $i=>$value)
        {
            if (is_int($value))
            {
            }
            elseif (is_string($value) && ctype_digit(trim($value)))
            {
                $value=(int)$value;
            }
            elseif (is_float($value) && (round($value,0)==$value) && ($value > -PHP_INT_MAX-1) && ($value < PHP_INT_MAX))
            {
                $value=(int)$value;
            }
            else
            {
                throw new RequestParameterIntExpected($this->_checkName, $value);
            }
            
            if (is_array($this->_check))
            {
                $this->_check[$i]=$value;
            }
            else
            {
                $this->_check=$value;
            }
        }
        return $this;
    }
    
    /* todo:
    public function float($key)
    {
        return $this;
        
    }
    public function regexp($key, $regexp)
    {
        return $this;
        
    }
    */
    
    
    /**
     * Validation : vérifie qu'un paramètre est supérieur ou égal au minimum
     * autorisé
     * 
     * Si la valeur du paramètre est un tableau, le test est appliqué à chacun
     * des éléments du tableau.
     * 
     * A l'issue du test, la valeur du paramètre en cours de validation est 
     * toujours du même type que l'argument $min indiqué.
     *  
     * Exemples d'utilisation :
     * 
     * <code>
     * $request->min('nb',5)->ok();
     * $request->min('author','azimov')->ok();
     * $request->required('nb')->min(5)->ok();
     * </code>
     * 
     * @param string|null $key
     * @param scalar $min
     * @return Request $this pour permettre le chainage des appels de méthodes
     * 
     * @throws RequestParameterMinExpected si le test échoue
     */
    public function min($key, $min=null)
    {
        if (is_null($min))
        {
            $min=$key;
            $key=null;
        }
        
        $this->check($key);
        
        foreach((array)$this->_check as $i=>$value)
        {
            if (is_array($this->_check))
            {
                settype($this->_check[$i], gettype($min));
            }
            else
            {
                settype($this->_check, gettype($min));
            }
            if ($value >= $min) 
            {
                continue;
            }
            throw new RequestParameterMinExpected($this->_checkName, $value, $min);
        }
        
        return $this;
    }
    
    
    /**
     * Validation : vérifie qu'un paramètre est inférieur ou égal au maximum
     * autorisé
     * 
     * Si la valeur du paramètre est un tableau, le test est appliqué à chacun
     * des éléments du tableau.
     * 
     * A l'issue du test, la valeur du paramètre en cours de validation est 
     * toujours du même type que l'argument $max indiqué.
     *  
     * Exemples d'utilisation :
     * 
     * <code>
     * $request->max('nb',20)->ok();
     * $request->max('author','bradbury')->ok();
     * $request->required('nb')->max(20)->ok();
     * </code>
     * 
     * @param string|null $key
     * @param scalar $max
     * 
     * @return Request $this pour permettre le chainage des appels de méthodes
     * 
     * @throws RequestParameterMaxExpected si le test échoue
     */
    public function max($key, $max=null)
    {
        if (is_null($max))
        {
            $max=$key;
            $key=null;
        }
        $this->check($key);
        foreach((array)$this->_check as $i=>$value)
        {
            if (is_array($this->_check))
            {
                settype($this->_check[$i], gettype($max));
            }
            else
            {
                settype($this->_check, gettype($max));
            }
            
            if ($value <= $max) 
            {
                continue;
            }
            
            throw new RequestParameterMaxExpected($this->_checkName, $value, $max);
        }
        return $this;
    }
    
    
    /**
     * Validation : vérifie qu'un paramètre contient l'une des valeurs autorisées
     * 
     * Si la valeur du paramètre est un tableau, le test est appliqué à chacun
     * des éléments du tableau.
     * 
     * Si les valeurs autorisées sont des chaines, la casse des caractères et
     * les éventuels espaces de début et de fin sont ignorés.
     * 
     * A l'issue du test, la valeur du paramètre en cours de validation est 
     * toujours strictement identique à l'une des valeurs autorisées.
     *  
     * Exemples d'utilisation :
     * 
     * <code>
     * $request->oneof('nb',2,4,6)->ok();
     * $request->oneof('author', 'azimov', 'bradbury')->ok();
     * $request->required('nb')->oneof(2,4,6)->ok();
     * </code>
     * 
     * @param string|null $key
     * @return Request $this pour permettre le chainage des appels de méthodes
     * 
     * @throws RequestParameterBadValue si le test échoue
     */
    public function oneof($key)
    {
        $values=func_get_args();
        if (is_null($this->_checkName))
        {
            $key=array_shift($values);
        }
        else
        {
            $key=null;
        }

        $this->check($key);
        
        foreach((array)$this->_check as $i=>$value)
        {
            foreach($values as $allowed)
            {
                if ($allowed===$value || (is_string($allowed) && strtolower(trim($allowed))===strtolower(trim($value))))
                {
                    if (is_array($this->_check))
                    {
                        $this->_check[$i]=$allowed;
                    }
                    else
                    {
                        $this->_check=$allowed;
                    }
                    
                    continue 2;
                }
            }
            throw new RequestParameterBadValue($this->_checkName, $value);
        }
        return $this;
    }
    
    
    /**
     * Validation : vérifie qu'un paramètre n'a qu'une seule valeur (ie n'est pas
     * un tableau)
     * 
     * Si le paramètre est un scalaire, le test réussit. Si le paramètre est un
     * tableau ne contenant qu'un seul élément, celui-ci est transformé en
     * scalaire. Dans tous les autres cas, le test échoue.
     * 
     * Exemples d'utilisation :
     * <code>
     * $request->unique('nb')->ok();
     * $request->required('ref')->unique()->ok();
     * </code>
     * 
     * @param string|null $key
     * @return Request $this pour permettre le chainage des appels de méthodes
     * 
     * @throws RequestParameterUniqueValueExpected si le paramètre est un tableau
     * contenant plusieurs éléments
     */
    public function unique($key=null)
    {
        $value=$this->check($key);
        if (is_scalar($value))
        {
            return $this;
        }
        
        if (is_array($value))
        {
            if (count($value)===0)
            {
                $this->_check=null;
                return $this;
            }
            if (count($value)===1)
            {
                $this->_check=array_shift($this->_check);
                return $this;
            }
            throw new RequestParameterUniqueValueExpected($this->_checkName, $this->_check);
        }

        return $this;
    }
    
    /**
     * Validation : transforme un paramètre en tableau de valeurs
     * 
     * Le paramètre en cours est transformé en tableau si ce n'en est pas
     * déjà un.
     * 
     * A l'issue du test, la valeur du paramètre en cours de validation est 
     * toujours un tableau.
     * 
     * Exemples d'utilisation :
     * <code>
     * $request->asArray('refs')->ok();
     * $request->required('refs')->asArray()->ok();
     * </code>
     * 
     * @param string|null $key
     * @return Request $this pour permettre le chainage des appels de méthodes
     */
    public function asArray($key=null)
    {
        $this->_check=(array)$this->check($key);
        return $this;
    }
    

    /**
     * Validation : transforme un paramètre en tableau et vérifie le nombre 
     * d'éléments qu'il possède.
     * 
     * Si $min et $max ont été indiqués, le test réussit si le nombre d'éléments
     * du tableau est compris entre min et max (bornes incluses).
     * 
     * Si seul $min est indiqué, le test réussit si le tableau a exactement $min
     * éléments.
     * 
     * A l'issue du test, la valeur du paramètre en cours de validation est 
     * toujours un tableau.
     * 
     *  
     * Exemples d'utilisation :
     * 
     * <code>
     * $request->count('refs',2)->ok(); // ok si exactement 2 éléments
     * $request->required('refs')->count(2,3)->ok(); // ok si 2 ou 3 éléments
     * </code>
     * 
     * @param string|null $key
     * @param int $min
     * @param int $max
     * 
     * @return Request $this pour permettre le chainage des appels de méthodes
     * 
     * @throws RequestParameterCountException si le test échoue
     */
    public function count($key, $min=null, $max=null)
    {
        switch(func_num_args())
        {
            case 1: // count(2)
                $min=$key;
                $key=null;
                break;
            case 2: // count(2, 3) ou count('t', 2)
                if (is_int($key))
                {
                    $min=$key;
                    $key=null;
                }
            //case 3: // count('t', 2, 3)
        }
        $this->_check=(array)$this->check($key);
        if (is_null($max))
        {
            if (count($this->_check) !== $min)
            {
                throw new RequestParameterCountException($this->_checkName, $this->_check, $min);
            }
        }
        else
        {
            if (count($this->_check) < $min || count($this->_check) > $max)
            {
                throw new RequestParameterCountException($this->_checkName, $this->_check, $min, $max);
            }
        }
        return $this;
    }
    
    
    /**
     * Validation : termine la validation d'un paramètre et retourne la valeur
     * finale du paramètre.
     * 
     * Exemple d'utilisation :
     * 
     * <code>
     * $request->set('flag','on')->bool()->ok(); // returns true
     * </code>
     *  
     * @param string|null $key
     * @return mixed
     */
    public function ok($key=null)
    {
        $this->_checkName=null;
        return $this->_check;
    }
    
    /**
     * Retourne la requête en cours sous la forme d'une url indiquant le module,
     * l'action et les paramètres actuels de la requête
     *
     * @return string
     */
    public function getUrl()
    {
        return 
            '/' . $this->_module . '/' . $this->_action 
            . Routing::buildQueryString($this->_parameters, true);
    }
    
    /**
     * Alias de {@link getUrl()} : retourne la requête en cours sous forme
     * d'url.
     * 
     * __toString est une méthode magique de php qui est appellée lorsque PHP
     * a besoin de convertir un objet en chaine de caractères.
     * 
     * @return string
     */
    public function __toString()
    {
        return $this->getUrl();
    }
}
/*
class HttpRequest extends Request 
{
    
}

class CliRequest extends Request
{
    public function __construct()
    {
        parent::__construct($_SERVER['argv']);        
    }
}
*/
/**
 * Classe de base des exceptions générées par les fonctions de validation
 * de paramètres de {@link Request}
 *  
 * @package     fab
 * @subpackage  module
 */
class RequestParameterException extends Exception
{
    public function __construct($message)
    {
        parent::__construct('Requête incorrecte : '.$message);
    }
};


/**
 * Exception générée par {@link Request::required()} lorsqu'un paramètre
 * est absent
 *  
 * @package     fab
 * @subpackage  module
 */
class RequestParameterRequired extends RequestParameterException 
{
    public function __construct($param)
    {
        parent::__construct(sprintf('paramètre %s requis', $param));
    }
};


/**
 * Exception générée par {@link Request::oneof()} lorsqu'un paramètre
 * a une valeur autorisée
 *  
 * @package     fab
 * @subpackage  module
 */
class RequestParameterBadValue extends RequestParameterException 
{
    public function __construct($param, $value, $message='valeur incorrecte')
    {
        parent::__construct(sprintf('%s=%s, %s', $param, Utils::varExport($value,true), $message));
    }
};


/**
 * Exception générée par {@link Request::unique()} lorsqu'un paramètre
 * a plusieurs valeurs
 *  
 * @package     fab
 * @subpackage  module
 */
class RequestParameterUniqueValueExpected extends RequestParameterBadValue 
{
    public function __construct($param, $value)
    {
        parent::__construct($param, $value, 'valeur unique attendue');
    }
};


/**
 * Exception générée par {@link Request::bool()} lorsqu'un paramètre
 * n'est pas un booléen
 *  
 * @package     fab
 * @subpackage  module
 */
class RequestParameterBoolExpected extends RequestParameterBadValue 
{
    public function __construct($param, $value)
    {
        parent::__construct($param, $value, 'booléen attendu');
    }
};


/**
 * Exception générée par {@link Request::int()} lorsqu'un paramètre
 * n'est pas un entier
 *  
 * @package     fab
 * @subpackage  module
 */
class RequestParameterIntExpected extends RequestParameterBadValue 
{
    public function __construct($param, $value)
    {
        parent::__construct($param, $value, 'entier attendu');
    }
};


/**
 * Exception générée par {@link Request::min()} lorsqu'un paramètre
 * est inférieur au minimum autorisé
 *  
 * @package     fab
 * @subpackage  module
 */
class RequestParameterMinExpected extends RequestParameterBadValue 
{
    public function __construct($param, $value, $min)
    {
        parent::__construct($param, $value, sprintf('minimum attendu : %s', $min));
    }
};


/**
 * Exception générée par {@link Request::max()} lorsqu'un paramètre
 * dépasse le maximum autorisé
 *  
 * @package     fab
 * @subpackage  module
 */
class RequestParameterMaxExpected extends RequestParameterBadValue 
{
    public function __construct($param, $value, $max)
    {
        parent::__construct($param, $value, sprintf('maximum attendu : %s', $max));
    }
};


/**
 * Exception générée par {@link Request::count()} lorsqu'un paramètre
 * n'a pas le nombre correct de valeurs attendues
 *  
 * @package     fab
 * @subpackage  module
 */
class RequestParameterCountException extends RequestParameterBadValue 
{
    public function __construct($param, $value, $min, $max=null)
    {
        if (is_null($max))
            parent::__construct($param, $value, sprintf('%s valeurs attendues', $min));
        else
            parent::__construct($param, $value, sprintf('de %s à %s valeurs attendues', $min, $max));
    }
};

?>