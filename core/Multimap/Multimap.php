<?php
/**
 * @package     fab
 * @subpackage  core
 * @author      Daniel Ménard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id$
 */

/**
 * Classe représentant une collection dans laquelle chaque clé est associée à une
 * ou plusieurs valeurs.
 *
 * Les clés du Multimap peuvent être des entiers ou des chaines. Elles doivent être
 * uniques et sont stockées dans l'ordre dans lequel elles sont insérées dans la
 * collection.
 *
 * Les données associées aux clés peuvent être de n'importe quel type. Lorsque
 * plusieurs données sont associées à une même clé, celles-ci sont stockées et
 * retournées à l'utilisateur sous la forme d'un tableau php.
 *
 *
 * <h2>Vue d'ensemble :</h2>
 *
 * La classe Multimap contient des méthodes permettant :
 * - de créer et de dupliquer une collection : {@link __construct()}, {@link create()},
 *   {@link copy()} ;
 * - de définir les options de la collection : {@link compareMode()},
 *   {@link keyDelimiter()} ;
 * - d'ajouter des données dans la collection : {@link add()}, {@link addMany()} ;
 * - de récupérer les données associées à une clé : {@link get()} ;
 * - de modifier les données d'une clé : {@link set()} ;
 * - de supprimer une valeur, une clé ou la totalité de la collection : {@link clear()} ;
 * - d'obtenir des informations sur une collection : {@link count()}, {@link isEmpty()},
 *   {@link emptyValue()}, {@link has()} ;
 * - de filtrer les données d'une collection : {@link keepOnly()}, {@link filter()} ;
 * - de parcourir ou de modifier les données en utilisant un callback : {@link apply()},
 *   {@link run()} ;
 * - d'obtenir une représentation de la collection : {@link __toString()},
 *   {@link toArray()}, {@link __toJson()}.
 *
 * La plupart des méthodes retournent <code>$this</code> ce qui permet d'enchainer
 * plusieurs appels de méthode en une seule étape :
 *
 * <code>
 * $map->add('query', 'health')->set('max', 10)->clear('format');
 * </code>
 *
 *
 * <h2>Traitement des valeurs vides : </h2>
 * La classe Multimap supprime automatiquement les valeurs vides. Lorsqu'une donnée
 * vide est ajoutée ou lorsqu'une donnée existante est {@link apply() transformée}
 * en une donnée vide, celle-ci est automatiquement supprimée. C'est la méthode
 * {@link emptyValue()} qui définit ce qu'est une valeur vide. Par défaut les
 * valeurs <code>null</code>, <code>''</code> (chaine vide) et <code>array()</code>
 * (tableau ne contenant aucun élément) sont considérées comme des données vides,
 * mais les classes descendantes peuvent surcharger cette méthode si nécessaire.
 *
 * Exemple :
 * <code>
 * $map->add('item', ''); // ne fait rien
 * $map->add('item', 'aaa')->apply('trim', 'item', 'a'); // la clé item est supprimée après apply
 * </code>
 *
 * Aucun dédoublonnage n'est effectué sur les données associées à une clé :
 * <code>
 * $map->add('item', 'data')->add('item', 'data')->get('item'); // array('data','data')
 * </code>
 *
 * <h2>Désignation des clés : </h2>
 * Plusieurs méthodes acceptent en paramètre un argument <code>$key</code> qui permet
 * d'indiquer la ou les clés de la collection auxquelles le traitement s'applique.
 *
 * A chaque fois, vous pouvez indiquer, au choix :
 * - le nom d'une clé unique,
 * - un tableau contenant les noms des clés,
 * - plusieurs clés séparées par le {@link keyDelimiter() délimiteur de clé} définit
 *   pour la collection (par défaut il s'agit de la virgule),
 * - la chaine <code>'*'</code> pour désigner la totalité des clés existantes.
 *
 * Exemples d'utilisation :
 *
 * <code>
 * $map->add('key1,key2', 'data'); // ajoute la donnée 'data' dans les clés 'key1' et 'key2'
 * $map->add(array('key1','key2'), 'data'); // idem
 * $map->clear('key1,key2'); // supprime les clés 'key1' et 'key2'
 * $map->get('key1,key2'); // retourne la première des clés qui existent
 * $map->keepOnly('key1,key2'); // supprime tout sauf 'key1' et 'key2'
 * $map->apply('trim', 'key1,key2'); // applique la fonction trim() au données des clés 'key1' et 'key2'
 * $map->apply('trim', '*'); // applique la fonction trim() à toutes les données
 * $map->has('*', 'N/A'); // la donnée 'N/A' est-elle présente dans l'une des clés ?
 * </code>
 *
 * <h2>Comparaison des données :</h2>
 *
 * Les méthodes {@link has()} et {@link clear()} permettent de tester l'existence ou
 * de supprimer certaines données. Pour fonctionner, elles utilisent une fonction de
 * comparaison qui testent si l'argument passé à la fonction est égal à la donnée
 * testée.
 *
 * Par défaut, la collection utilise la méthode de comparaison {@link CMP_EQUAL} qui
 * effectue une comparaison siple, non typée, de ses arguments (<code>==</code>).
 *
 * Vous pouvez changer la méthode de comparaison utilisée avec la méthode
 * {@link compareMode()} en lui passant en paramètre l'une des constantes
 * {@link CMP_EQUAL CMP_*} ou le nom de votre propre fonction de comparaison.
 *
 * {@link has()} et {@link clear()} acceptent également un paramètre
 * <code>$compareMode</code> qui permet d'utiliser ponctuellement une autre méthode de
 * comparaison que celle définie par défaut pour la collection.
 *
 * <h2>Utilisation d'un multimap comme un objet :</h2>
 *
 * Multimap définit les méthodes magiques __get(), __set(), __isset() et __unset() qui vous
 * permettent de l'utiliser comme un objet et de manipuler les clés comme s'il s'agissait de
 * propriétés :
 *
 * <code>
 * $map = new Multimap();
 * $map->key = $value; // appelle {@link __set()}
 * echo $map->key; // appelle {@link __get()}
 * if (isset($map->key)) // appelle {@link __isset()}
 *     unset($map->key); // appelle {@link __unset()}
 * </code>
 *
 * <h2>Utilisation comme un tableau :</h2>
 *
 * Multimap implémente les interfaces ArrayObject, IteratorAggregate et Countable, qui vous
 * permettent de l'utiliser comme un tableau php standard :
 *
 * <code>
 * $map = new Multimap();
 * $map['key'] = $value; // appelle {@link offsetSet()}
 * echo $map['key']; // appelle {@link offsetGet()}
 * if (isset($map['key'])) // appelle {@link offsetExists()}
 *     unset($map['key']); // appelle {@link offsetUnset()}
 * echo count($map); // appelle {@link count()}
 * foreach($map as $key=>$value) // appelle {@link getIterator()}
 *    echo "$key=$value\n";
 * </code>
 *
 * Le délimiteur de clé est pris en compte lorsque vous utilisez la syntaxe tableau :
 * <code>
 * $map['a,b,c'] = 'item'; // 'a' = 'b' = 'c' = 'item'
 * echo $map['e,f,a']; // retourne la première clé renseignée : 'a' => 'item'
 * </code>
 *
 * @package     fab
 * @subpackage  core
 */
class Multimap implements Countable, ArrayAccess, IteratorAggregate
{

    // --------------------------------------------------------------------------------
    // Propriétés
    // --------------------------------------------------------------------------------

    /**
     * Les données présentes dans la collection.
     *
     * @var array
     */
    protected $data = array();


    /**
     * Caractère utilisé comme délimiteur pour séparer les noms de clés.
     *
     * @var string
     */
    protected $keyDelimiter = ',';


    // --------------------------------------------------------------------------------
    // Modes de comparaison
    // --------------------------------------------------------------------------------


    /**
     * {@link compareMode() CompareMode} : teste si les valeurs sont égales (==).
     *
     * @var int
     */
    const CMP_EQUAL = 1;

    /**
     * {@link compareMode() CompareMode} : teste si les valeurs sont identiques (===).
     *
     * @var int
     */
    const CMP_IDENTICAL = 2;

    /**
     * {@link compareMode() CompareMode} : convertit les valeurs en chaines puis
     * teste si elles sont égales en ignorant la casse des caractères.
     * strcasecmp().
     *
     * @var int
     */
    const CMP_IGNORE_CASE = 3;

    /**
     * {@link compareMode() CompareMode} : convertit les valeurs en chaines, applique
     * trim() et teste si elles sont égales.
     *
     * @var int
     */
    const CMP_TRIM = 4;

    /**
     * {@link compareMode() CompareMode} : convertit les valeurs en chaines, applique
     * tokenize() et teste si elles sont égales
     *
     * @var int
     */
    const CMP_TOKENIZE = 5;


    /**
     * Le mode de comparaison en cours.
     *
     * @var int une des constantes CMP_XXX
     */
    protected $compareMode;


    /**
     * Le callback correspondant au mode de comparaison en cours.
     *
     * @var callback
     */
    protected $compare;


    // --------------------------------------------------------------------------------
    // Création, construction, clonage
    // --------------------------------------------------------------------------------

    /**
     * Méthode statique permettant de créer une nouvelle collection.
     *
     * Php ne permet pas de chainer des méthodes après un appel à new :
     * <code>$map = new Multimap()->add('max', 10);</code> génère une erreur.
     *
     * La méthode create permet de contourner le problème en écrivant :
     * <code>$map = Multimap::create($_GET, $_POST)->add('max', 10);</code>
     *
     * @param mixed $data ... optionnel, un ou plusieurs tableaux (ou objets itérables)
     * représentant les données initiales de la collection. Chaque paramètre est ajouté
     * au multimap en utilisant la méthode {@link add()}.
     *
     * @return $this
     */
    public static function create($data = null)
    {
        $map = new self();

        $args = func_get_args();
        foreach($args as $arg)
            $map->addMany($arg);

        return $map;
    }


    /**
     * Crée un multimap.
     *
     * @param mixed $data ... optionnel, un ou plusieurs tableaux (ou objets itérables)
     * représentant les données initiales de la collection. Chaque paramètre est ajouté
     * au multimap en utilisant la méthode {@link addMany()}.
     */
    public function __construct($data = null)
    {
        $this->compareMode(self::CMP_EQUAL);
        $args = func_get_args();
        foreach($args as $arg)
            $this->addMany($arg);
    }


    /**
     * Clone la collection en cours.
     *
     * @return $this
     */
    public function copy()
    {
        return clone $this;
    }


    // --------------------------------------------------------------------------------
    // Fonctions de comparaison
    // --------------------------------------------------------------------------------


    /**
     * Retourne ou modifie le mode de comparaison par défaut utilisé pour tester si deux
     * valeurs sont égales.
     *
     * Le mode de comparaison est utilisé par la méthode {@link has()} et {@link clear()}pour
     * tester si une clé contient une valeur donnée.
     *
     * @param null|int|callback $mode
     * @return int|callback|$this retourne le mode de comparaison en cours si $mode vaut null,
     * <code>$this</code> sinon.
     */
    public function compareMode($mode = null)
    {
        if (is_null($mode)) return $this->compareMode ===0 ? $this->compare : $this->compareMode;

        switch ($mode)
        {
            case self::CMP_EQUAL:
                $callback = array($this, 'cmpEqual');
                break;

            case self::CMP_IDENTICAL:
                $callback = array($this, 'cmpIdentical');
                break;

            case self::CMP_IGNORE_CASE:
                $callback = array($this, 'cmpIgnoreCase');
                break;

            case self::CMP_TRIM:
                $callback = array($this, 'cmpTrim');
                break;

            case self::CMP_TOKENIZE:
                $callback = array($this, 'cmpTokenize');
                break;

            default: // $mode est un callback
                if (! is_callable($mode)) throw new Exception('CompareMode : Callback invalide.');
                $callback = $mode;
                $mode = 0;
        }
        $this->compareMode = $mode;
        $this->compare = $callback;

        return $this;
    }


    /**
     * Compare deux valeurs et retourne vrai si elles sont égales.
     *
     * @param mixed $a première valeur à comparer.
     * @param mixed $b seconde valeur à comparer
     *
     * @param int|callback $compareMode mode de comparaison à utiliser. Lorsque $compareMode
     * vaut <code>null</code>, le {@link compareMode() mode de comparaison par défaut}
     * définit pour la collection est utilisé.
     *
     * @return bool <code>true</code> si les deux valeurs sont égales, <code>false</code>
     * sinon.
     */
    protected function compare($a, $b, $compareMode = null)
    {
        if (! is_null($compareMode))
        {
            $oldMode = $this->compareMode();
            $this->compareMode($compareMode);
        }
        $result = call_user_func($this->compare, $a, $b);
        if (! is_null($compareMode)) $oldMode = $this->compareMode($compareMode);
        return $result;
    }


    /**
     * Chaine source utilisée par {@link tokenize()} et par {@link cmpIgnoreCase()}.
     *
     * @var string
     */
    private static $charFroms = '\'-ABCDEFGHIJKLMNOPQRSTUVWXYZŒœÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèéêëìíîïðñòóôõöùúûüýþÿ';


    /**
     * Chaine résultat utilisée par {@link tokenize()} et par {@link cmpIgnoreCase()}.
     *
     * @var string
     */
    private static $charTo    =  '  abcdefghijklmnopqrstuvwxyzœœaaaaaaæceeeeiiiidnoooooœuuuuytsaaaaaaæceeeeiiiidnooooouuuuyty';


    /**
     * Retourne la version tokenisée du texte passé en paramètre.
     *
     * @param string $text
     * @return string
     */
    protected static function tokenize($text)
    {
        // Convertit les caractères
        $text = strtr($text, self::$charFroms, self::$charTo);

        // Gère les lettres doubles
        $text = strtr($text, array('æ'=>'ae', 'œ'=>'oe'));

        // Retourne un tableau contenant tous les mots présents
        return implode(' ', str_word_count($text, 1, '0123456789@_'));
    }


    /**
     * Callback utilisé pour {@link CMP_EQUAL}.
     *
     * @param mixed $a
     * @param mixed $b
     * @return bool
     */
    protected function cmpEqual($a, $b)
    {
        return $a == $b;
    }


    /**
     * Callback utilisé pour {@link CMP_IDENTICAL}.
     *
     * @param mixed $a
     * @param mixed $b
     * @return bool
     */
    protected function cmpIdentical($a, $b)
    {
        return $a === $b;
    }


    /**
     * Callback utilisé pour {@link CMP_TRIM}.
     *
     * @param mixed $a
     * @param mixed $b
     * @return bool
     */
    protected function cmpTrim($a, $b)
    {
        return trim($a) === trim($b);
    }


    /**
     * Callback utilisé pour {@link CMP_IGNORE_CASE}.
     *
     * @param mixed $a
     * @param mixed $b
     * @return bool
     */
    protected function cmpIgnoreCase($a, $b)
    {
        return
            trim(strtr($a, self::$charFroms, self::$charTo))
            ===
            trim(strtr($b, self::$charFroms, self::$charTo));
    }


    /**
     * Callback utilisé pour {@link CMP_TOKENIZE}.
     *
     * @param mixed $a
     * @param mixed $b
     * @return bool
     */
    protected function cmpTokenize($a, $b)
    {
        return self::tokenize($a) === self::tokenize($b);
    }


    // --------------------------------------------------------------------------------
    // Délimiteur de clé
    // --------------------------------------------------------------------------------


    /**
     * Retourne ou modifie le délimiteur utilisé pour séparer les noms de clés.
     *
     * Par défaut, le délimiteur utilisé est la virgule.
     * Toutes les méthodes qui acceptent une clé en paramètre utilisent ce délimiteur.
     *
     * Exemple :
     * <code>
     * // La ligne :
     * $map->add('clé1,clé2', 'value');
     *
     * // Est équivalente à :
     * $map->add('clé1', 'value')->add('clé2', 'value');
     * </code>
     *
     * @param null|string $delimiter le nouveau délimiteur à utiliser
     *
     * @return string|$this retourne le délimiteur en cours si <code>$delimiter</code>
     * vaut null, <code>$this</code> sinon.
     */
    public function keyDelimiter($delimiter = null)
    {
        if (is_null($delimiter)) return $this->keyDelimiter;
        $this->keyDelimiter = trim($delimiter);
        return $this;
    }


    // --------------------------------------------------------------------------------
    // Gestion des valeurs vides
    // --------------------------------------------------------------------------------


    /**
     * Indique si la valeur passée en paramêtre est considérée comme vide par le multimap.
     *
     * Les valeurs vides sont automatiquement supprimées de la liste des valeurs associées
     * à une clé.
     *
     * Par défaut, la méthode retourne <code>true</code> pour les valeurs null,
     * '' (chaine vide), array() et <code>false</code> pour toutes les autres.
     *
     * Les classes descendantes peuvent surcharger cette méthode pour changer la sémantique
     * du mot "vide".
     *
     * @param mixed $value la valeur à tester.
     *
     * @return bool true si la valeur est vide.
     */
    public function emptyValue($value)
    {
        return $value === '' || count($value) === 0;
    }


    // --------------------------------------------------------------------------------
    // Ajout, modification, récupération, suppression de données
    // --------------------------------------------------------------------------------


    /**
     * Analyse le paramètre <code>$key</code> passé à une méthode et retourne un
     * tableau contenant les noms rééls des clés à utiliser.
     *
     * @param mixed $key la chaine ou le tableau à analyser.
     * @return array un tableau contenant les noms des clés.
     */
    protected function parseKey($key)
    {
        if (is_string($key)) $key = trim($key);

        if (is_null($key) || $key === '' || $key === array() || $key === '*')
            return array_keys($this->data);

        if (is_array($key)) return $key;

        return array_map('trim', explode($this->keyDelimiter, $key));
    }


    /**
     * Ajoute une valeur unique à une clé de la collection.
     *
     * La méthode add() ajoute une nouvelle valeur dans les données associées aux
     * clés indiquées. Pour remplacer complètement les données associées aux clés,
     * utilisez la méthode {@link set()}.
     *
     * Les données {@link emptyValue() vides} sont ignorées.
     *
     * @param scalar $key les clés auxquelles ajouter la valeur.
     * @param mixed $value la valeur à ajouter.
     * @return $this
     */
    public function add($key, $value = null)
    {
        if (! (is_string($key) || is_int($key)))
            throw new Exception('Clé incorrecte');

        if ($this->emptyValue($value)) return $this;

        foreach($this->parseKey($key) as $key)
        {
            if (array_key_exists($key, $this->data))
                $this->data[$key][] = $value;
            else
                $this->data[$key] = array($value);
        }

        return $this;

    }


    /**
     * Ajoute plusieurs clés à la collection ou plusieurs valeurs à une clé.
     *
     *
     * La méthode <code>addMany()</code> peut être appellée avec un ou plusieurs
     * paramètres.
     *
     * Lorsqu'elle est appellée avec un seul paramètre, celui-ci doit être tableau
     * ou un objet itérable contenant des clés et des valeurs qui seront ajoutées
     * à la collection.
     *
     * Exemples :
     *
     * - add($array) : ajoute les données du tableau passé en paramètre
     *   Equivalent à : foreach(array as key=>value) add(key, value)
     *
     * - add($multimap) : ajoute les données de la collection passée en paramètre
     *   Equivalent à : foreach(Multimap->toArray() as key=>value) add(key, value)
     *
     * - add($object) : ajoute les propriétés de l'objet passé en paramètre
     *   Equivalent à : foreach((array)$object as key=>value) add(key, value)
     *
     * Lorsque <code>addMany()</code> est appellée avec plusieurs paramètres, le
     * premier paramètre désigne la ou les clés auxquelles il faut ajouter des
     * valeurs et les autres paramètres doivent être des tableaux ou des objets
     * itérables contenant des données qui seront ajoutées aux clés spécifiées.
     *
     * Exemples :
     *
     * - add(key, array) : ajoute toutes les données présentes de array à la clé key.
     *   Equivalent de : foreach(array as value) add(key, value)
     *   Les clés de array sont ignorées.
     *
     * - add(key, multimap) : ajoute toutes les valeurs présentes dans le multimap comme valeurs
     *   associées à la clé key.
     *   Equivalent de : foreach(Multimap->toArray() as value) add(key, value)
     *   Les clés existantes du multimap sont ignorées.
     *
     * - Tout autre type de valeur génère une exception. Une exception est également générée si
     *   la clé passée en paramêtre n'est pas un scalaire.
     *
     * @param mixed $key (optionnel) la ou les clés à modifier.
     * @param array|object|Traversable $data un ou plusieurs tableaux contenant les données ou les
     * clés à ajouter.
     *
     * @return this
     */
    public function addMany($key, $data=null)
    {
        // Premier cas : une clé a été indiquée
        if (is_string($key) || is_int($key))
        {
            $args = func_get_args();
            array_shift($args);
            foreach($args as $data)
            {
                if (! (is_array($data) || $data instanceof Traversable || is_object($data)))
                    throw new BadMethodCallException('Tableau ou objet itérable attendu.');

                if ($data instanceof Multimap)
                    $data = $data->toArray();

                foreach($data as $value)
                    $this->add($key, $value);
            }
        }

        // Second cas : pas de clé, que des tableaux
        else
        {
            $args = func_get_args();
            foreach($args as $data)
            {
                if (! (is_array($data) || $data instanceof Traversable || is_object($data)))
                    throw new BadMethodCallException('Tableau ou objet itérable attendu.');

                if ($data instanceof Multimap)
                {
                    foreach($data->toArray() as $key=>$value)
                        if (is_array($value))
                            $this->addMany($key, $value);
                        else
                            $this->add($key, $value);
                }
                else
                    foreach($data as $key=>$value)
                        $this->add($key, $value);
            }

        }

        return $this;
    }



    /**
     * Retourne les données associées à la clé indiquée ou la valeur par défaut
     * si la clé demandée ne figure pas dans la collection.
     *
     * Exemples :
     * <code>
     * $map->get('key'); // retourne la donnée associée à 'key' ou null si elle n'existe pas
     * $map->get('key', 'n/a'); // retourne la donnée associée à 'key' ou 'n/a' si elle n'existe pas
     * $map->get('key1,key2'); // retourne le contenu de la première clé non-vide ou null
     * </code>
     *
     * get est similaire à {@link __get()} mais permet d'indiquer une valeur par
     * défaut (par exemple <code>$map->get('item', 'abc')</code>)
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get($key, $default=null)
    {
        foreach($this->parseKey($key) as $key)
            if (isset($this->data[$key]))
                return count($this->data[$key])===1 ? reset($this->data[$key]) : $this->data[$key];

        return $default;
    }


    /**
     * Remplace les données associées à une clé.
     *
     * Si la valeur indiquée est {@link emptyValue() vide}, la clé est supprimée.
     *
     * Exemples :
     * <code>
     * $map->set('item', 12); // remplace le contenu existant de 'item' par la valeur 12
     * $map->set('item'); // Equivalent à $map->clear('item')
     * $map->set('item', array(1,2)); // remplace le contenu existant de 'item' par la valeur array(1,2)
     * $map->set('key1,key2', 12); // Initialise les clés key1 et key2 à 12
     * </code>
     *
     * @param string $key
     * @param mixed $value
     *
     * @return $this
     */
    public function set($key, $value = null)
    {
        if ($this->emptyValue($value)) return $this->clear($key);

        foreach($this->parseKey($key) as $key)
            $this->data[$key] = is_array($value) ? $value : array($value);

        return $this;
    }


    /**
     * Supprime des clés ou des données de la collection.
     *
     * La méthode <code>clear()</code> permet de supprimer :
     * - toutes les clés qui figure dans la collection : <code>$map->clear();</code>
     * - une clé unique : <code>$map->clear('max');</code>
     * - plusieurs clés : <code>$map->clear('start,max,format');</code>
     * - une donnée particulière associée à une ou plusieurs clés :
     * <code>$map->clear('TypDoc,TypDocB', 'Article');</code>
     *
     * @param mixed $key le ou les clés à supprimer.
     * @param mixed $value la valeur à supprimer
     * @param mixed $compareMode méthode de comparaison à utiliser pour comparer $value aux
     * données de la clé.
     *
     * @return $this
     */
    public function clear($key=null, $value=null, $compareMode = null)
    {
        // Aucune clé indiquée, on vide toute la collection
        if (is_null($key))
        {
            $this->data = array();
            return $this;
        }

        // Aucune valeur indiquée : supprime toutes les clés indiquées
        if (is_null($value))
        {
            foreach($this->parseKey($key) as $key)
                unset($this->data[$key]);

            return $this;
        }

        // Supprime les valeurs indiquées dans les clés indiquées
        foreach($this->parseKey($key) as $key)
        {
            if (! array_key_exists($key, $this->data)) continue;

            $v = $this->data[$key];

            foreach($this->data[$key] as $k => $v)
                if ($this->compare($v, $value, $compareMode)) unset($this->data[$key][$k]);

            if (count($this->data[$key]) === 0)
                unset($this->data[$key]);
        }

        return $this;
    }


    /**
     * Transfère le contenu d'une ou plusieurs clés vers d'autres clés.
     *
     * La méthode <code>move()</code> permet de déplacer, de concaténer ou de dupliquer des champs.
     * Le contenu existant des clés destination est écrasé.
     *
     * Exemples :
     * <code>
     * // Transfère TITFRAN Dans TitOrigA
     * $map->move('TITFRAN', 'TitOrigA');
     *
     * // Transfère tous les champ mots-clés dans le champ MotsCles
     * $map->move('MOTSCLE1,MOTSCLE2,MOTSCLE3,MOTSCLE4,PERIODE', 'MotsCles');
     *
     * // Recopie MotsCles dans NouvDesc
     * $map->move('MotsCles', 'MotsCles,NouvDesc');
     *
     * // Ajoute NouvDesc à MotsCles
     * $map->move('MotsCles,NouvDesc', 'MotsCles');
     * </code>
     *
     * @param string $from une ou plusieurs clés sources.
     * @param string $to une ou plusieurs clés destination.
     * @return $this
     */
    public function move($from, $to)
    {
        // Récupère toutes les données
        $data = $this->getAll($from);

        // Vide les clés de $from qui ne figurent pas dans $to
        // On pourrait faire directement clear($from) mais dans ce cas, cela changerait l'ordre
        //  des clés pour un appel comme move('a,b', 'a')
        $from = $this->parseKey($from);
        $to = $this->parseKey($to);

        $diff = array_diff($from, $to);
        if ($diff) $this->clear($diff);

        // Aucune donnée : supprime les clés destination
        if (count($data)===0)
            return $this->clear($to);

        // Stocke les données dans les clés destination
        foreach($to as $to)
            $this->data[$to] = $data;

        return $this;
    }

    /**
     * @return array
     *
     * @param $key
     */
    public function getAll($key)
    {
        $args = func_get_args();
        $t = array();
        foreach($args as $key)
        {
            foreach($this->parseKey($key) as $key)
            {
                if (! isset($this->data[$key])) continue;
                $t = array_merge($t, $this->data[$key]);
            }
        }
        return $t;
    }

    /**
     * Supprime toutes les clés sauf celles indiquées.
     *
     * Exemple :
     * <code>
     * $map->keepOnly('start,max', 'format'); // supprime tous sauf start, max et format
     * </code>
     *
     * @param mixed $key un ou plusieurs paramètres indiquant le ou les noms des clés
     * à conserver.
     *
     * @return $this
     */
    public function keepOnly($key)
    {
        $keys = array();
        $args = func_get_args();
        foreach ($args as & $key)
            $keys += array_flip($this->parseKey($key));

        $this->data = array_intersect_key($this->data, $keys);

        return $this;
    }

    // --------------------------------------------------------------------------------
    // Information
    // --------------------------------------------------------------------------------

    /**
     * Indique si la collection ou la clé indiquée est vide.
     *
     * Lorsque isEmpty() est appellée sans paramètres, la méthode retourne true si la
     * collection est vide.
     *
     * Si $key est indiquée, la méthode retourne true si aucune des clés indiquées
     * n'existe.
     *
     * Exemples :
     * <code>
     * Multimap::create()->isEmpty(); // aucun élément dans la collection retourne true
     *
     * $map = Multimap::create(array('a'=>1, 'z'=>26));
     * $map->isEmpty('a'); // false
     * $map->isEmpty('b'); // true
     * $map->isEmpty('p,z'); // false
     * $map->isEmpty('p,q,r,s'); // true
     * $map->isEmpty('*'); // identique à $map->isEmpty() : false
     * </code>
     *
     * @param mixed $key la ou les clés à tester.
     *
     * @return bool
     */
    public function isEmpty($key = null)
    {
        if (is_null($key))
            return count($this->data) === 0;

        foreach ($this->parseKey($key) as $key)
            if (array_key_exists($key, $this->data)) return false;

        return true;
    }


    /**
     * Détermine si la collection contient la clé ou la valeur indiquées.
     *
     * Lorsque has() est appellée avec un seul paramètre, la méthode retourne true
     * si la collection contient au moins l'une des clés indiquées.
     *
     * Lorsque has() est appellée avec une clé et une valeur, la méthode retourne
     * true si au moins l'un des clés indiquées contient la valeur indiquée.
     *
     * Exemples :
     * <code>
     * $map = Multimap::create(array('a'=>'A', 'b' => array('BB','BC')));
     * $map->has('a'); // true
     * $map->has('c'); // false
     * $map->has('b', 'BC'); // true
     * $map->has('b', 'BD'); // false
     * </code>
     *
     * @param mixed $key la ou les clés recherchées.
     * @param mixed $value optionnel, la ou les valeurs à tester.
     * @param mixed $compareMode méthode de comparaison à utiliser pour comparer $value aux
     * données de la clé.
     *
     * @return bool
     */
    public function has($key, $value=null, $compareMode = null)
    {
        foreach ($this->parseKey($key) as $key)
        {
            if (! array_key_exists($key, $this->data)) continue;
            if (is_null($value)) return true;
            foreach ($this->data[$key] as $v)
                foreach((array)$value as $item)
                    if ($this->compare($v, $item, $compareMode)) return true;
        }
        return false;
    }


    // --------------------------------------------------------------------------------
    // Conversion
    // --------------------------------------------------------------------------------


    /**
     * Retourne une représentation textuelle de la collection.
     *
     * __toString est une méthode magique de php qui est appellée lorsque PHP
     * a besoin de convertir un objet en chaine de caractères.
     *
     * @return string La méthode retourne une chaine qui contient le nom de la classe,
     * le nombre d'éléments dans la collection et un var_export() des données.
     */
    public function __toString()
    {
        $cli = php_sapi_name()=='cli';

        ob_start();
        echo $cli ? "\n" : '<pre>';

        echo get_class($this);

        if ($this->isEmpty())
            echo " vide\n";
        else
        {
            echo  ' : ' , count($this->data) , " item(s) = \n";
            foreach ($this->data as $key => $data)
            {
                echo $key, "\n";
                if (count($data) === 1)
                {
                    $data = reset($data);
                    echo '  ', var_export($data, true), "\n";
                }
                else
                {
                    foreach($data as $i=>$value)
                        echo '  ', $i, '. ', var_export($value, true), "\n";
                }
            }
        }

        echo $cli ? "\n" : '</pre>';
        return ob_get_clean();
    }


    /**
     * Retourne un tableau contenant les données présentes dans la collection.
     *
     * @return array
     */
    public function toArray()
    {
        $result = $this->data;
        foreach ($result as & $data)
            if (count($data)===1) $data = reset($data);

        return $result;
    }


    /**
     * Retourne une représentation JSON des données de la collection.
     *
     * @return string
     */
    public function toJson()
    {
        return json_encode($this->toArray());
    }


    // --------------------------------------------------------------------------------
    // Parcourt et application de callbacks
    // --------------------------------------------------------------------------------

    /**
     * Applique un callback aux données qui figurent dans une ou plusieurs clés.
     *
     * La méthode apply() permet d'appliquer un callback (fonction, méthode, closure...) à toutes
     * les données associées à une ou plusieurs des clés de la collection.
     *
     * Exemples d'utilisation :
     * <code>
     * // Faire un trim sur tous les champs et sur tous les articles
     * $map->apply('trim');
     *
     * // Transformer des dates en format "aaa-mm-jj" en format Bdsp
     * $map->apply('strtr', 'DatEdit,DatOrig', '-', '/'); // 2011-02-02 -> 2011/02/02
     *
     * // Supprimer la mention "pp." qui figure au début d'une pagination
     * $map->apply('pregReplace', 'PageColl', '~p+\.?\s*(\d+)-(\d+)~', '$1-$2')
     * </code>
     *
     * @param callback $callback le nom du callback à appeller pour chacune des valeurs associées
     * aux clés indiquées dans $key. Il peut s'agir du nom d'une méthode de la classe en cours,
     * du nom d'une fonction globale, d'un tableau ou d'une closure.
     *
     * Le callback recevra en paramètres la valeur à transformer et les éventuels arguments
     * supplémentaires passés à apply(). Il doit retourner la valeur modifiée.
     *
     * Le callback doit avoir la signature suivante :
     * <code>protected function callback(mixed $value) returns string</code>
     *
     * ou, si vous utilisez les arguments optionnels :
     * <code>protected function callback(mixed $value, $arg1, ...) returns string</code>
     *
     * @param mixed $key la ou les clés pour lesquelles le callback sera appellé.
     *
     * @param mixed $args ... optionnel, des argument supplémentaires à passer au callback.
     *
     * @return $this
     */
    public function apply($callback, $key=null, $args=null)
    {
        // Détermine si le callback est une méthode de la classe ou une fonction globale
        if (is_string($callback) && method_exists($this, $callback))
            $callback = array($this, $callback);

        if (! is_callable($callback))
            throw new Exception('Callback non trouvé : ' . var_export($callback, true));

        // Détermine les arguments à passer au callback
        $args = func_get_args();
        $args = array_slice($args, 1);

        // Transforme tous les champs
        foreach($this->parseKey($key) as $key)
        {
            if (! isset($this->data[$key])) continue;

            foreach($this->data[$key] as $i => & $value)
            {
                $args[0] = $value;
                if ($this->emptyValue($value = call_user_func_array($callback, $args)))
                    unset($this->data[$key][$i]);
            }
            if (count($this->data[$key]) === 0)
                unset($this->data[$key]);
        }
        return $this;
    }


    /**
     * Exécute un callback sur les données qui figurent dans une ou plusieurs clés.
     *
     * La méthode run() permet d'exécuter un callback (fonction, méthode, closure...)
     * pour toutes les données associées à une ou plusieurs des clés de la collection.
     *
     * Exemple d'utilisation :
     * <code>
     * $map->run('dump', '*', "%s = %s\n");
     *
     * function dump($key, $value, $format)
     * {
     *     printf($format, $key, $value);
     * }
     * </code>
     *
     * @param callback $callback le nom du callback à appeller pour chacune des valeurs
     * associées aux clés indiquées dans $key. Il peut s'agir du nom d'une méthode de la
     * classe en cours, du nom d'une fonction globale, d'un tableau ou d'une closure.
     *
     * Le callback recevra en paramètres :
     * - la clé en cours,
     * - la valeur,
     * - les éventuels arguments supplémentaires passés run().
     *
     * Le callback doit avoir la signature suivante :
     * protected function callback(scalar $key, mixed $value) returns boolean
     *
     * ou, si vous utilisez les arguments optionnels :
     * protected function callback(scalar $key, mixed $value, ...) returns boolean
     *
     * Si le callback retourne false, le parcourt des clés est interrompu.
     *
     * @param mixed $key la ou les clés pour lesquelles le callback sera appellé.
     *
     * @param mixed $args ... optionnel, des argument supplémentaires à passer au callback.
     *
     * @return $this
     */
    public function run($callback, $key=null, $args=null)
    {
        // Détermine si le callback est une méthode de la classe ou une fonction globale
        if (is_string($callback) && method_exists($this, $callback))
            $callback = array($this, $callback);

        if (! is_callable($callback))
            throw new Exception('Callback non trouvé : ' . var_export($callback, true));

        // Détermine les arguments à passer au callback
        $args = func_get_args();

        // Parcourt toutes les clés
        foreach($this->parseKey($key) as $key)
        {
            if (! isset($this->data[$key])) continue;

            $args[0] = $key;

            foreach($this->data[$key] as & $value)
            {
                $args[1] = $value;
                if (false === call_user_func_array($callback, $args)) break;
            }
        }
        return $this;
    }


    /**
     * Filtre les données et ne conserve qui celles qui passent le filtre indiqué.
     *
     * La méthode filter() permet d'exécuter un callback (fonction, méthode, closure...)
     * pour toutes les données associées à une ou plusieurs des clés de la collection.
     *
     * Seules les données pour lesquelles le filtre retourne <code>true</code> sont
     * conservées dans la collection et la méthode retourne un tableau contenant les
     * données supprimées.
     *
     * Exemple d'utilisation :
     * <code>
     * // ne conserve que les entiers et retourne un tableau avec toutes les clés qui contenaient
     * // autre chose qu'un entier.
     * $bad = $map->filter('is_int');
     * </code>
     *
     * @param callback $callback le nom du callback à appeller pour chacune des valeurs
     * associées aux clés indiquées dans $key. Il peut s'agir du nom d'une méthode de la
     * classe en cours, du nom d'une fonction globale, d'un tableau ou d'une closure.
     *
     * Le callback recevra en paramètres :
     * - la valeur,
     * - la clé en cours,
     * - les éventuels arguments supplémentaires passés à filter().
     *
     * Le callback doit avoir la signature suivante :
     * protected function callback(mixed $value, scalar $key) returns boolean
     *
     * ou, si vous utilisez les arguments optionnels :
     * protected function callback(mixed $value, scalar $key, ...) returns boolean
     *
     * @param mixed $key la ou les clés pour lesquelles le callback sera appellé.
     *
     * @param mixed $args ... optionnel, des argument supplémentaires à passer au filtre.
     *
     * @return array
     */
    public function filter($callback, $key=null, $args=null)
    {
        // Détermine si le callback est une méthode de la classe ou une fonction globale
        if (is_string($callback) && method_exists($this, $callback))
            $callback = array($this, $callback);

        if (! is_callable($callback))
            throw new Exception('Callback non trouvé : ' . var_export($callback, true));

        // Détermine les arguments à passer au callback
        $args = func_get_args();

        // Parcourt toutes les clés
        $result = array();
        foreach($this->parseKey($key) as $key)
        {
            if (! isset($this->data[$key])) continue;

            $args[1] = $key;
            foreach($this->data[$key] as $i=>$value)
            {
                $args[0] = $value;
                if (! call_user_func_array($callback, $args))
                {
                    $result[] = $value;
                    unset($this->data[$key][$i]);
                }
            }
            if (count($this->data[$key]) === 0)
                unset($this->data[$key]);
        }

        // Retourne un tableau (éventuellement vide) contenant les valeurs filtrées
        return $result;
    }


    // --------------------------------------------------------------------------------
    // Méthodes magiques de php, traitement des clés comme des propriétés de l'objet
    // --------------------------------------------------------------------------------


    /**
     * Détermine si la clé indiquée existe.
     *
     * __isset() est une méthode magique de php qui permet de tester l'existence
     * d'une clé comme s'il s'agissait d'une propriété de l'objet Multimap.
     *
     * Exemple :
     * <code>$map = new Multimap('item'); echo isset($map->key); // true </code>
     *
     * La fonction {@link has()} peut faire la même chose mais prend le nom de
     * l'argument en paramètre.
     *
     * @param string $key
     * @return bool
     */
    public function __isset($key)
    {
        return $this->has($key);
    }


    /**
     * Retourne les données associées à la clé indiquée ou null
     * si la clé demandée ne figure pas dans la collection.
     *
     * __get est une méthode magique de php qui permet d'accéder aux paramètres
     * de la collection comme s'il s'agissait de propriétés de l'objet
     * Multimap (par exemple <code>$map->max</code>)
     *
     * La méthode {@link get()} est similaire mais permet d'indiquer une valeur
     * par défaut.
     *
     * @param string $key
     * @return mixed
     */
    public function __get($key)
    {
        return $this->get($key);
    }


    /**
     * Modifie les données associées à une clé.
     *
     * __set est une méthode magique de php qui permet de modifier une
     * clé comme s'il s'agissait d'une propriété de l'objet Multimap
     * (par exemple <code>$map->max = 10</code>)
     *
     * Set remplace complètement les données associées à la clé. Pour ajouter une valeur
     * à une clé existant, utilisez {@link add()}
     *
     * @param string $key
     * @param mixed $value
     */
    public function __set($key, $value)
    {
        $this->set($key, $value);
    }


    /**
     * Supprime la clé indiquée.
     *
     * __unset est une méthode magique de php qui permet de supprimer une
     * clé de la collection comme s'il s'agissait d'une propriété de l'objet Multimap
     * (par exemple <code>unset($map->max)</code>)
     *
     * @param string $key
     */
    public function __unset($key)
    {
        $this->clear($key);
    }


    // --------------------------------------------------------------------------------
    // Interface Countable
    // --------------------------------------------------------------------------------


    /**
     * Retourne le nombre de clés présentes dans la collection ou le nombre de données
     * associées à la clé ou aux clés indiquées.
     *
     * @implements Countable
     *
     * @param mixed $key la ou les clés à compter.
     *
     * @return int
     */
    public function count($key = null)
    {
        if (is_null($key)) return count($this->data);

        $count = 0;
        foreach($this->parseKey($key) as $key)
            if (isset($this->data[$key])) $count += count($this->data[$key]);

        return $count;
    }


    // --------------------------------------------------------------------------------
    // Interface ArrayAccess
    // --------------------------------------------------------------------------------

    /**
     * Indique si la clé indiquée existe.
     *
     * @implements ArrayAccess
     *
     * @param scalar $key
     * @return bool
     */
    public function offsetExists($key)
    {
        return $this->has($key);
    }


    /**
     * Retourne les données associées à la clé indiquée.
     *
     * @implements ArrayAccess
     *
     * @param scalar $key
     * @return mixed
     */
    public function offsetGet($key)
    {
        return $this->get($key);
    }


    /**
     * Modifie les données associées à la clé indiquée.
     *
     * @implements ArrayAccess
     *
     * @param scalar $key
     * @param mixed $value
     * @return $this
     */
    public function offsetSet($key, $value)
    {
        return $this->set($key, $value);
    }


    /**
     * Supprime les données associées à la clé indiquée.
     *
     * @implements ArrayAccess
     *
     * @param mixed $key
     * @return $this
     */
    public function offsetUnset($key)
    {
        return $this->clear($key);
    }


    // --------------------------------------------------------------------------------
    // Interface IteratorAggregate
    // --------------------------------------------------------------------------------


    /**
     * Retourne un itérateur permettant d'utiliser un multimap dans une boucle foreach.
     *
     *
     * @implements IteratorAggregate
     *
     * @return object L'itérateur obtenu n'est utilisable qu'en lecture. Une boucle de la forme
     * <code>foreach($map as & $value)</code> provoquera une erreur.
     */
    public function getIterator()
    {
        return new ArrayIterator($this->toArray());
    }

    // todo
    // has() retourne true si on a l'une des clés/valeurs indiquées (OU).
    // has All retournerait true si on les a toutes (ET).
    //
    // hasAll($key) : retourne true si la collection contient toutes les clés indiquées
    // hasAll($key, $value) : retourne true si toutes les clés existent et qu'elles contiennent toutes value
    // si value est un tableau : retourne true si toutes les clés existent et qu'elles contiennent toutes les value indiquées
    /*
    public function hasAll($key)
    {
        hasAll('a,b,c', array('ITEM1','ITEM2'));
    }
    */
}