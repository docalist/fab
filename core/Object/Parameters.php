<?php
/**
 * @package     fab
 * @subpackage  core
 * @author      Daniel Ménard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id$
 */

/**
 * Classe représentant une collection de paramètres constitués d'un nom et d'une
 * valeur associée.
 *
 * Certaines méthodes retourne $this pour permettre de chainer les appels de
 * méthodes :
 *
 * <code>
 * $parameters
 *     ->set('query', 'health')
 *     ->set('max', 10)
 *     ->set('format', 'html');
 * </code>
 *
 * Parameters propose également des méthodes (chainées) permettant de valider
 * aisément les paramètres :
 *
 * <code>
 * $parameters
 *     ->required('ref')
 *     ->int()
 *     ->unique()
 *     ->min(1);
 * </code>
 *
 *
 * @package     fab
 * @subpackage  core
 */
class Parameters
{
    /**
     * Les paramètres de la requête
     *
     * @var array
     */
    private $_parameters=array();

    /**
     * Nom du paramètre en cours de validation
     *
     * @var string
     */
    private $_checkName;

    /**
     * Valeur actuelle du paramètre en cours de validation
     *
     * @var mixed
     */
    private $_check;


    /**
     * Construit une nouvelle collection de paramètres.
     *
     * Des paramètres supplémentaires peuvent être ajoutés à la collection
     * en utilisant {@link set()} et {@link add()}
     *
     * @param array $parameters ... des tableaux contenant les paramètres
     * initiaux de la collection.
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
     * Méthode statique permettant de créer une nouvelle collection de
     * paramètres.
     *
     * Php ne permet pas de chainer des méthodes après un appel à new :
     * <code>$parameters=new Parameters()->set('max', 10);</code>
     * génère une erreur.
     *
     * La méthode statique create permet de contourner le problème en écrivant :
     * <code>$parameters=Parameters::create()->set('max', 10);</code>
     *
     * @param array $parameters ... des tableaux contenant les paramètres
     * initiaux de la collection.
     */
    public static function create(array $parameters=array())
    {
        $class=__CLASS__;
        $request=new $class();
        $args=func_get_args();
        foreach($args as $arg)
        {
            $request->addParameters($arg);
        }
        return $request;
    }


    /**
     * Clone la collection en cours.
     *
     * @return Parameters
     */
    public function copy()
    {
        return clone $this;
    }


    /**
     * Ajoute un tableau de paramètres à la collection.
     *
     * @param array $parameters
     * @return Parameters $this pour permettre le chainage des appels de méthodes.
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
            if (is_array($this->_parameters[$key]))
            {
                // tableau + tableau
                if (is_array($value))
                    $this->_parameters[$key]=array_merge($this->_parameters[$key], $value);

                // tableau + valeur
                else
                    $this->_parameters[$key][]=$value;
            }

            // Existe déjà, simple valeur, crée un tableau contenant la valeur existante et la valeur indiquée
            else
            {
                // valeur + tableau
                if (is_array($value))
                    $this->_parameters[$key]=array_merge(array($this->_parameters[$key]), $value);

                // valeur + valeur
                else
                    $this->_parameters[$key]=array($this->_parameters[$key], $value);
            }
        }
        return $this;
    }


    /**
     * Retourne la valeur du paramètre indiqué ou null si la collection ne
     * contient pas le paramètre demandé.
     *
     * __get est une méthode magique de php qui permet d'accéder aux paramètres
     * de la collection comme s'il s'agissait de propriétés de l'objet
     * Parameters (par exemple <code>$parameters->max</code>)
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
     * si le paramètre indiqué ne figure pas dans la collection.
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
     * Modifie la valeur d'un paramètre.
     *
     * __set est une méthode magique de php qui permet de modifier un
     * paramètre comme s'il s'agissait d'une propriété de l'objet Parameters
     * (par exemple <code>$parametrs->max = 10</code>)
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
     * Modifie la valeur d'un paramètre.
     *
     * Exemple : $parameters->set('item', 12)
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
     * Supprime le paramètre indiqué
     *
     * __unset est une méthode magique de php qui permet de supprimer un
     * paramètre comme s'il s'agissait d'une propriété de l'objet Parameters
     * (par exemple <code>unset($parameters->max)</code>)
     *
     * @param string $key
     */
    public function __unset($key)
    {
        unset($this->_parameters[$key]);
    }


    /**
     * Supprime des paramètres de la collection.
     *
     * La méthode <code>clear()</code> permet de supprimer :
     * - tous les paramètres qui figure dans la collection,
     * - un paramètre unique,
     * - une valeur précise d'un paramètre.
     *
     * Exemples :
     * - <code>$parameters->clear('item')</code> // supprime le paramètre
     *   item de la requête ;
     * - <code>$parameters->clear('item', 'article')</code> // supprime la
     *   valeur 'article' du paramètre item de la requête.
     *
     * @param string $key le nom du paramètre à supprimer.
     * @param mixed $value optionnel : la valeur à effacer.
     * Par défaut (lorsque $value n'est pas indiqué, clear efface complètement
     * le paramétre indiqué par $key. Si $value est indiqué et que $key désigne
     * un tableau, seule la valeur indiquée va être supprimée de la requête.
     * Si $key désigne un scalaire, le paramètre ne sera supprimé que si la valeur
     * associée correspond à $value.
     *
     * @return Parameters $this pour permettre le chainage des appels de méthodes.
     */
    public function clear($key=null, $value=null)
    {
        if (is_null($key))
        {
            $this->_parameters=array();
        }
        else
        {
            if (is_null($value))
            {
                unset($this->_parameters[$key]);
            }
            else
            {
                if (array_key_exists($key, $this->_parameters))
                {
                    $v=$this->_parameters[$key];
                    if (is_scalar($v))
                    {
                        if ($v === $value)
                            unset($this->_parameters[$key]);
                    }
                    else
                    {
                        foreach($this->_parameters[$key] as $k=>$v)
                            if ($v === $value) unset($this->_parameters[$key][$k]);
                        if (empty($this->_parameters[$key]))
                            unset($this->_parameters[$key]);
                    }
                }
            }
        }
        return $this;
    }

    /**
     * Supprime tous les paramètres sauf ceux dont le nom est indiqué en
     * paramètre.
     *
     * Exemple :
     * <code>
     * $parameters->keepOnly('max', 'format'); // supprime tous sauf max et format
     * </code>
     *
     * @param string $arg nom du premier paramètre à conserver. Vous pouvez
     * indiquer autant d'argument arg que nécessaire
     * @return Parameters $this pour permettre le chainage des appels de méthodes.
     */
    public function keepOnly($arg)
    {
        $args=func_get_args();
        $this->_parameters=array_intersect_key($this->_parameters, array_flip($args));
        return $this;
    }


    /**
     * Supprime tous les paramètres vides.
     *
     * La méthode <code>clearNull()</code> supprime de la collection tous les
     * paramètres dont la valeur est une chaine vide, un tableau vide ou la valeur null.
     *
     * @return Parameters $this pour permettre le chainage des appels de méthodes.
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
     * Indique si la collection contient des paramètres.
     *
     * @return bool
     */
    public function hasParameters()
    {
        return count($this->_parameters)!==0;
    }


    /**
     * Retourne tous les paramètres présents dans la collection.
     *
     * @return array
     */
    public function getParameters()
    {
        return $this->_parameters;
    }


    /**
     * Détermine si le paramètre indiqué existe.
     *
     * __isset() est une méthode magique de php qui permet de tester l'existence
     * d'un paramètre comme s'il s'agissait d'une propriété de l'objet Parameters.
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
     * Détermine si le paramètre indiqué existe.
     *
     * La fonction retourne true même si le paramètre à la valeur null
     *
     * @param string $key le nom du paramètre à tester.
     * @param mixed $value optionnel, la valeur à tester. Lorsque $value
     * est indiquée, la méthode retourne true si le paramètre $key est définit
     * et s'il contient la valeur $value.
     *
     * @return bool
     */
    public function has($key, $value=null)
    {
        if (! array_key_exists($key, $this->_parameters)) return false;
        if (is_null($value)) return true;

        foreach((array) $this->_parameters[$key] as $v)
            if ($v === $value) return true;
        return false;
    }


    /**
     * Ajoute une valeur au paramètre indiqué.
     *
     * Add ajoute le paramètre indiqué à la liste des paramètres de la requête.
     * Si le paramètre indiqué existait déjà, la valeur existante est transformée
     * en tableau et la valeur indiquée est ajoutée au tableau obtenu.
     *
     * Pour remplacer complètement la valeur d'un paramètre existant, utiliser
     * la méthode {@link set()}.
     *
     * @param string $key
     * @param mixed $value
     * @return Request $this pour permettre le chainage des appels de méthodes.
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
     * Validation : génère une exception si le paramètre indiqué n'existe pas.
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
     * $parameters->required('item')->ok();
     * $parametes->bool('item')->required()->ok();
     * </code>
     *
     * @param string|null $key
     * @return Parameters $this pour permettre le chainage des appels de méthodes
     * @throws ParametersParameterRequired si le test échoue
     */
    public function required($key=null)
    {
        $value=(array)$this->check($key);

        if (count($value)===0)
        {
            throw new ParameterParameterRequired($this->_checkName);
        }

        foreach((array)$value as $value)
        {
            if (is_null($value) || (is_string($value) && trim($value)===''))
            {
                throw new ParametersParameterRequired($this->_checkName);
            }
        }

        return $this;
    }

    /**
     * Validation : donne une valeur par défaut à un paramètre si celui-ci n'est
     * pas déjà définit.
     *
     * @param string|null $key
     * @param scalar $default
     *
     * @return Parameters $this pour permettre le chainage des appels de méthodes.
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
     * Validation : génère une exception si le paramètre indiqué n'est pas un
     * booléen.
     *
     * La méthode <code>bool()</code> reconnaît les booléens mais aussi les
     * chaines <code>'true','on', '1' </code> et <code>'false','off','0'</code>
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
     * $parameters->bool('flag')->ok();
     * $parameters->required('flag')->bool()->ok();
     * </code>
     *
     * @param string|null $key
     * @return Parameters $this pour permettre le chainage des appels de
     * méthodes.
     *
     * @throws ParametersParameterBoolExpected si le test échoue
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
                    throw new ParametersParameterBoolExpected($this->_checkName, $value);
                }
            }
            else
            {
                throw new ParametersParameterBoolExpected($this->_checkName, $value);
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
     * Validation : génère une exception si le paramètre indiqué n'est pas un
     * entier.
     *
     * La méthode <code>int()</code> reconnaît les entiers mais aussi les
     * chaines représentant un entier (les espaces de début ou de fin éventuels
     * sont ignorés).
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
     * $parameters->int('nb')->ok();
     * $parameters->required('nb')->int()->ok();
     * </code>
     *
     * @param string|null $key
     * @return Parameters $this pour permettre le chainage des appels de méthodes
     *
     * @throws ParametersParameterIntExpected si le test échoue
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
                throw new ParametersParameterIntExpected($this->_checkName, $value);
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
     * Validation : Génère une exception si le paramètre indiqué est inférieur
     * au minimum autorisé.
     *
     * Si la valeur du paramètre est un tableau, le test est appliqué à chacun
     * des éléments du tableau.
     *
     * A l'issue du test, la valeur du paramètre en cours de validation est
     * toujours du même type que l'argument <code>$min</code> indiqué.
     *
     * Exemples d'utilisation :
     *
     * <code>
     * $parameters->min('nb',5)->ok();
     * $parameters->min('author','azimov')->ok();
     * $parameters->required('nb')->min(5)->ok();
     * </code>
     *
     * @param string|null $key
     * @param scalar $min
     * @return Parameters $this pour permettre le chainage des appels de
     * méthodes.
     *
     * @throws ParametersParameterMinExpected si le test échoue
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
            throw new ParametersParameterMinExpected($this->_checkName, $value, $min);
        }

        return $this;
    }


    /**
     * Validation : génère une exception si le paramètre indiqué est supérieur
     * au maximum autorisé.
     *
     * Si la valeur du paramètre est un tableau, le test est appliqué à chacun
     * des éléments du tableau.
     *
     * A l'issue du test, la valeur du paramètre en cours de validation est
     * toujours du même type que l'argument <code>$max</code> indiqué.
     *
     * Exemples d'utilisation :
     *
     * <code>
     * $parameters->max('nb',20)->ok();
     * $parameters->max('author','bradbury')->ok();
     * $parameters->required('nb')->max(20)->ok();
     * </code>
     *
     * @param string|null $key
     * @param scalar $max
     *
     * @return Parameters $this pour permettre le chainage des appels de méthodes
     *
     * @throws ParamatersParameterMaxExpected si le test échoue
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

            throw new ParametersParameterMaxExpected($this->_checkName, $value, $max);
        }
        return $this;
    }


    /**
     * Validation : génère une exception si le paramètre indiqué contient une
     * valeur non autorisée.
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
     * $parameters->oneof('nb',2,4,6)->ok();
     * $parameters->oneof('author', 'azimov', 'bradbury')->ok();
     * $parameters->required('nb')->oneof(2,4,6)->ok();
     * </code>
     *
     * @param string|null $key
     * @return Parameters $this pour permettre le chainage des appels de
     * méthodes.
     *
     * @throws ParametersParameterBadValue si le test échoue.
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
            throw new ParametersParameterBadValue($this->_checkName, $value);
        }
        return $this;
    }


    /**
     * Validation : génère une exception si le paramètre indiqué est multivalué.
     *
     * Si le paramètre est un scalaire, le test réussit. Si le paramètre est un
     * tableau ne contenant qu'un seul élément, celui-ci est transformé en
     * scalaire. Dans tous les autres cas, le test échoue.
     *
     * Exemples d'utilisation :
     * <code>
     * $parameters->unique('nb')->ok();
     * $parameters->required('ref')->unique()->ok();
     * </code>
     *
     * @param string|null $key
     * @return Parameters $this pour permettre le chainage des appels de
     * méthodes.
     *
     * @throws ParametersParameterUniqueValueExpected si le paramètre est un
     * tableau contenant plusieurs éléments.
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
            throw new ParametersParameterUniqueValueExpected($this->_checkName, $this->_check);
        }

        return $this;
    }


    /**
     * Validation : transforme un paramètre en tableau de valeurs.
     *
     * Le paramètre en cours est transformé en tableau si ce n'en est pas
     * déjà un.
     *
     * A l'issue du test, la valeur du paramètre en cours de validation est
     * toujours un tableau.
     *
     * Exemples d'utilisation :
     * <code>
     * $parameters->asArray('ref')->ok();
     * $parameters->required('ref')->asArray()->ok();
     * </code>
     *
     * @param string|null $key
     * @return Parameters $this pour permettre le chainage des appels de
     * méthodes.
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
     * Si <code>$min</code> et <code>$max</code> ont été indiqués, le test
     * réussit si le nombre d'éléments du tableau est compris entre les deux
     *  (bornes incluses).
     *
     * Si seul <code>$min</code> est indiqué, le test réussit si le tableau a
     * exactement <code>$min</code> éléments.
     *
     * A l'issue du test, la valeur du paramètre en cours de validation est
     * toujours un tableau.
     *
     * Exemples d'utilisation :
     *
     * <code>
     * $parameters->count('refs',2)->ok(); // ok si exactement 2 éléments
     * $parameters->required('refs')->count(2,3)->ok(); // ok si 2 ou 3 éléments
     * </code>
     *
     * @param string|null $key
     * @param int $min
     * @param int $max
     *
     * @return Parameters $this pour permettre le chainage des appels de
     * méthodes.
     *
     * @throws ParametersParameterCountException si le test échoue
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
                throw new ParametersParameterCountException($this->_checkName, $this->_check, $min);
            }
        }
        else
        {
            if (count($this->_check) < $min || count($this->_check) > $max)
            {
                throw new ParametersParameterCountException($this->_checkName, $this->_check, $min, $max);
            }
        }
        return $this;
    }


    /**
     * Validation : termine la validation d'un paramètre et retourne sa valeur
     * finale.
     *
     * Exemple d'utilisation :
     *
     * <code>
     * $parameters->set('flag','on')->bool()->ok(); // returns true
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
     * Retourne les paramètres en cours sous la forme d'une query string.
     *
     * @return string
     */
    public function asQueryString()
    {
        return Routing::buildQueryString($this->_parameters, true);
    }

    /**
     * Alias de {@link asQueryString()} : retourne la requête en cours sous forme
     * d'url.
     *
     * __toString est une méthode magique de php qui est appellée lorsque PHP
     * a besoin de convertir un objet en chaine de caractères.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->asQueryString();
    }
}

/**
 * Classe de base des exceptions générées par les fonctions de validation
 * de paramètres de {@link Parameters}
 *
 * @package     fab
 * @subpackage  core
 */
class ParametersParameterException extends Exception
{
    public function __construct($message)
    {
        parent::__construct('Erreur : '.$message);
    }
};


/**
 * Exception générée par {@link Parameters::required()} lorsqu'un paramètre
 * est absent
 *
 * @package     fab
 * @subpackage  core
 */
class ParametersParameterRequired extends ParametersParameterException
{
    public function __construct($param)
    {
        parent::__construct(sprintf('paramètre %s requis', $param));
    }
};


/**
 * Exception générée par {@link Parameters::oneof()} lorsqu'un paramètre
 * contient une valeur non autorisée.
 *
 * @package     fab
 * @subpackage  core
 */
class ParametersParameterBadValue extends ParametersParameterException
{
    public function __construct($param, $value, $message='valeur incorrecte')
    {
        parent::__construct(sprintf('%s=%s, %s', $param, Utils::varExport($value,true), $message));
    }
};


/**
 * Exception générée par {@link Parameters::unique()} lorsqu'un paramètre
 * est multivalué.
 *
 * @package     fab
 * @subpackage  core
 */
class ParametersParameterUniqueValueExpected extends ParametersParameterBadValue
{
    public function __construct($param, $value)
    {
        parent::__construct($param, $value, 'valeur unique attendue');
    }
};


/**
 * Exception générée par {@link Parameters::bool()} lorsqu'un paramètre
 * n'est pas un booléen
 *
 * @package     fab
 * @subpackage  core
 */
class ParametersParameterBoolExpected extends ParametersParameterBadValue
{
    public function __construct($param, $value)
    {
        parent::__construct($param, $value, 'booléen attendu');
    }
};


/**
 * Exception générée par {@link Parameters::int()} lorsqu'un paramètre
 * n'est pas un entier.
 *
 * @package     fab
 * @subpackage  core
 */
class ParametersParameterIntExpected extends ParametersParameterBadValue
{
    public function __construct($param, $value)
    {
        parent::__construct($param, $value, 'entier attendu');
    }
};


/**
 * Exception générée par {@link Parameters::min()} lorsqu'un paramètre
 * est inférieur au minimum autorisé.
 *
 * @package     fab
 * @subpackage  core
 */
class ParametersParameterMinExpected extends ParametersParameterBadValue
{
    public function __construct($param, $value, $min)
    {
        parent::__construct($param, $value, sprintf('minimum attendu : %s', $min));
    }
};


/**
 * Exception générée par {@link Parameters::max()} lorsqu'un paramètre
 * dépasse le maximum autorisé.
 *
 * @package     fab
 * @subpackage  core
 */
class ParametersParameterMaxExpected extends ParametersParameterBadValue
{
    public function __construct($param, $value, $max)
    {
        parent::__construct($param, $value, sprintf('maximum attendu : %s', $max));
    }
};


/**
 * Exception générée par {@link Parameters::count()} lorsqu'un paramètre
 * n'a pas le nombre correct de valeurs attendues.
 *
 * @package     fab
 * @subpackage  core
 */
class ParametersParameterCountException extends ParametersParameterBadValue
{
    public function __construct($param, $value, $min, $max=null)
    {
        if (is_null($max))
            parent::__construct($param, $value, sprintf('%s valeurs attendues', $min));
        else
            parent::__construct($param, $value, sprintf('de %s à %s valeurs attendues', $min, $max));
    }
};