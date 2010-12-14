<?php
/**
 * @package     fab
 * @subpackage  database
 * @author      Daniel Ménard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id$
 */

/**
 * Classe de base pour les lookup sur une base Xapian.
 *
 * @package     fab
 * @subpackage  database
 */
abstract class LookupHelper
{
    /**
     * L'itérateur Xapian de début tel que retourné par
     * {@link XapianDatabase::allterms_begin()}.
     *
     * @var XapianTermIterator
     */
    protected $begin = null;


    /**
     * L'itérateur Xapian de fin tel que retourné par
     * {@link XapianDatabase::allterms_end()}.
     *
     * @var XapianTermIterator
     */
    protected $end = null;


    /**
     * Le nombre maximum de suggestions à retourner.
     *
     * @var int
     */
    protected $max = null;


    /**
     * Préfixe Xapian des termes à rechercher.
     *
     * @var string
     */
    protected $prefix = '';


    /**
     * Tableau contenant la version {@link Utils::tokenize() tokenisée} des mots pour
     * lesquels on veut obtenir des suggestions.
     *
     * @var array
     */
    protected $words = null;


    /**
     * Le format (style {@link http://php.net/sprintf sprintf}) à utiliser pour mettre les mots de
     * l'utilisateur en surbrillance dans les suggestions obtenues.
     *
     * @var string
     */
    protected $format = '';


    /**
     * Indique s'il faut ou non trier la liste de résultats obtenus.
     *
     * Pour {@link TermLookup} et {@link ValueLookup}, c'est inutile de trier : comme on parcourt
     * l'index de façon ascendante, les résultats sont déjà triés par ordre alphabétique sur les
     * versions tokenisées des suggestions trouvées.
     *
     * Les modules descendants ({@link SimpleTableLookup}) peuvent surcharger cette propriété
     * pour indiquer qu'un tri des résultats est requis.
     *
     * @var bool
     */
    protected $alreadySorted = true;


    /**
     * Active ou désactive les messages de débogage.
     *
     * @var bool
     */
    protected $debug=false;


    /**
     * Constructeur. Active le débogage si l'option 'debug' figure dans la query string
     * de la requête en cours.
     */
    public function __construct()
    {
        $this->debug = isset($_GET['debug']) && $_GET['debug'] !== 0;
    }


    /**
     * Définit les {@link XapianTermIterator itérateurs Xapian} à utiliser.
     *
     * @param XapianTermIterator $begin itérateur de début.
     * @param XapianTermIterator $end itérateur de fin.
     */
    public function setIterators(XapianTermIterator $begin, XapianTermIterator $end)
    {
        $this->begin = $begin;
        $this->end = $end;
    }


    /**
     * Définit le préfixe des termes à utiliser pour la recherche des
     * suggestions.
     *
     * @param string $prefix
     */
    public function setPrefix($prefix='')
    {
        $this->prefix = $prefix;
    }


    /**
     * Trie le tableau de suggestions par ordre alphabétique.
     *
     * @param array $result le tableau de suggestions à trier.
     * @param array $sortKeys le tableau contenant les clés de tri.
     */
    protected function sort(array & $result, array & $sortKeys)
    {
        array_multisort($sortKeys, array_keys($result), $result);
    }


    /**
     * Recherche des suggestions pour les termes passés en paramètre.
     *
     * @param string $value la chaine de recherche pour laquelle on souhaite obtenir
     * des suggestions.
     *
     * @param int $max le nombre maximum de suggestions à retourner
     * (valeur par défaut : 10, 0 : pas de limite).
     *
     * @param string $format Le format (style {@link http://php.net/sprintf sprintf}) à
     * utiliser pour mettre les mots de l'utilisateur en surbrillance dans les
     * suggestions obtenues.
     *
     * @return array un tableau contenant les suggestions obtenues. Chaque clé
     * du tableau contient une suggestion et la valeur associée contient le
     * nombre total d'occurences de cette entrée dans la base.
     *
     * Le tableau est trié par ordre alphabétique sur la version tokenisée des entrées.
     *
     * Exemple :
     * <code>
     * array
     * (
     *     'droit du malade' => 10,
     *     'information du malade' => 3
     * )
     * </code>
     */
    public function lookup($value, $max=10, $format='<strong>%s</strong>')
    {
        // Stocke le format utilisé pour la surbrillance
        $this->format = empty($format) ? null : $format;

        // Stocke le nombre de suggestions à retourner
        $this->max = ($max<=0) ? PHP_INT_MAX : $max;

        // Tokenize l'expression recherchée
        $this->words = Utils::tokenize($value);
        if (empty($this->words)) $this->words[] = ' ';

        // Recherche pour chaque mot les entrées correspondantes
        if ($this->debug)
            echo get_class($this), "::getEntries(), value=<code>$value</code>, max=<code>$max</code>, format=<code>$format</code><br />";

        $result = $sortKeys = array();
        $this->getEntries($result, $sortKeys);

        // Trie les résultats si c'est nécessaire
        if (! $this->alreadySorted)
            $this->sort($result, $sortKeys);

        if ($this->debug)
        {
            echo "<div style='float:left'>Clés de tri : <pre>", var_export($sortKeys,true), "</pre></div>";
            echo "<div style='float:left'>Résultats : <pre>", var_export($result,true), "</pre></div>";
            echo '<hr style="clear: both" />';
        }

        // Limite à $max réponses
        if (count($result) > $this->max)
            $result=array_slice($result, 0, $this->max);

        // Retourne le tableau obtenu
        return $result;
    }


    /**
     * Recherche les entrées de la table  qui commencent par l'expression indiquée par
     * l'utilisateur.
     *
     * @param array $result les entrées obtenues, sous la forme d'un tableau
     * <code>"entrée riche" => nombre total d'occurences dans la base</code>.
     *
     * @param array $sortKeys un tableau, synchronisé avec <code>$result</code>, contenant
     * les versions tokenisées des entrées trouvées. Ce tableau est utilisé pour
     * {@link sort() trier} les résultats obtenus par ordre alphabétique.
     */
    abstract protected function getEntries(array & $result, array & $sortKeys);


    /**
     * Met en surbrillance (selon le format en cours) les termes de recherche
     * de l'utilisateur dans l'entrée passée en paramètre.
     *
     * @param string $entry l'entrée à surligner.
     * @param int $length le nombre de caractères à mettre en surbrillance.
     * @return string la chaine <code>$entry</code> dans laquelle la sous-chaine de
     * 0 à <code>$length</code> est mise en surbrillance.
     */
    protected function highlight($entry, $length)
    {
        if (is_null($this->format)) return $entry;
        return sprintf($this->format, substr($entry, 0, $length)) . substr($entry, $length);
    }
}


/**
 * LookupHelper permettant de rechercher des suggestions parmi les termes
 * simples présent dans l'index Xapian.
 *
 * Contrairement aux autres, ce helper ne sait pas faire des suggestions pour
 * une chaine contenant plusieurs mots (la raison est simple : les termes dans
 * l'index sont des mots uniques).
 *
 * Si une expression contenant plusieurs mots est recherchée, seul le dernier
 * mot sera utilisé pour faire des suggestions.
 *
 * @package     fab
 * @subpackage  database
 */
class TermLookup extends LookupHelper
{
    /**
     * Retourne le terme à partir duquel va démarrer la recherche dans l'index.
     *
     * Pour un lookup de type {@link TermLookup}, il s'agit de la version tokenisée du
     * dernier mot figurant dans l'expression de recherche indiquée par l'utilisateur.
     *
     * @return string
     */
    protected function getSearchTerm()
    {
        return end($this->words);
    }


    /**
     * Formatte une entrée telle qu'elle doit être présentée à l'utilisateur.
     *
     * La méthode supprime le préfixe de l'entrée,
     *
     * @param string $entry l'entrée à formatter.
     * @return string l'entrée mise en forme.
     */
    protected function formatEntry($entry)
    {
        return substr($entry, strlen($this->prefix));
    }


    /**
     * @inheritdoc
     */
    protected function getEntries(array & $result, array & $sortKeys)
    {
        // Détermine l'entrée de début et la chaine recherchée
        $search = $this->getSearchTerm();
        $start = $this->prefix . $search;
        if ($this->debug) echo "search = $search, start=$start<br />";

        // Va au début de la liste
        if ($this->debug) echo "<b style='color:red'>skip_to('$start')</b><br />";
        $this->begin->skip_to($start);

        // Boucle tant que les entrées commencent par ce qu'on cherche
        while (! $this->begin->equals($this->end))
        {
            // Récupère l'entrée en cours
            $key = $this->begin->get_term();
            if ($this->debug) echo "<li><b style='color:green'>$key</b><br /></li>";

            // Si elle ne commence pas par la chaine recherchée, terminé
            if (strncmp($key, $start, strlen($start)) !== 0) break;

            // Formatte l'entrée telle qu'elle doit être présentée à l'utilisateur
            $key = $this->formatEntry($key);
            $entry = $this->highlight($key, strlen($search));

            // Stocke la suggestion obtenue dans les tableaux résultat
            if (isset($result[$entry]))
            {
                $result[$entry] = max($result[$entry], $this->begin->get_termfreq());
            }
            else
            {
                $result[$entry] = $this->begin->get_termfreq();
                $sortKeys[] = $key;
            }

            // Si on a trouvé assez de suggestions, terminé
            if (count($result) >= $this->max) break;

            // Passe à l'entrée suivante dans l'index
            $this->begin->next();
        }
        if ($this->debug) echo "done<br />";
    }
}

/**
 * LookupHelper permettant de rechercher des suggestions parmi les articles
 * présents dans l'index Xapian.
 *
 * Ce helper recherche les articles qui commencent par l'un des mots indiqués
 * par l'utilisateur et ne retient que ceux contenant tous les mots (ou début
 * de mot) figurant dans l'expression recherchée.
 *
 * @package     fab
 * @subpackage  database
 */
class ValueLookup extends TermLookup
{
    /*
     * Les résultats issus d'un index "Article" sont déjà correctement triés par ordre alphabétique.
     * Donc, c'est inutile de retrier les résultats.
     *
     * Nanmoins, j'ai trouvé un cas ou cette propriété n'est pas respectée : sur la table PasEng,
     * en recherchant la valeur "a", on obtient les entrées
     * 28:_a3_chromosome_
     * 28:_a_
     *
     * Comme on utilise un underscore à la fin des articles et que, selon l'ordre ascii, le chiffre
     * "3" (code = 51) est plus petit que l'underscore (code = 95), le tri obtenu n'est pas correct.
     *
     * Pour que l'erreur se produise, il faut qu'on ait à la fois, dans une table des articles,
     * un article et le même article suivi d'un chiffre (le underscore sera avant n'importe quelle
     * lettre).
     *
     * A priori, ce cas est super rare, donc j'ai considéré que ce n'était pas la peine de trier
     * à chaque fois pour si peu.
     *
     * Dans le cas contraire, activer la ligne ci-dessous.
     */
    //protected $alreadySorted = false;



    /**
     * Retourne le terme à partir duquel va démarrer la recherche dans l'index.
     *
     * Pour un lookup de type {@link ValueLookup}, il s'agit d'une chaine de la forme
     * <code>_mot1_mot2_mot3</code> construite à partir de la version tokenisée des mots qui
     * figurent dans l'expression de recherche indiquée par l'utilisateur.
     *
     * @return string
     */
    protected function getSearchTerm()
    {
        return '_' . implode('_', $this->words);
    }


    /**
     * Formatte une entrée telle qu'elle doit être présentée à l'utilisateur.
     *
     * En plus des traitements appliqués par la {@link TermLookup::formatEntry() méthode parente},
     * la méthode supprime les caractères "_" présents dans l'article.
     *
     * @param string $entry l'entrée à formatter.
     * @return string l'entrée mise en forme.
     */
    protected function formatEntry($entry)
    {
        return trim(strtr(parent::formatEntry($entry), '_', ' '));
    }


    /**
     * @inheritdoc
     */
    protected function highlight($entry, $length)
    {
        // Comme les articles commencent par un '_' initial qu'on a supprimé dans formatEntry(),
        // il faut retrancher 1 à $length.
        return parent::highlight($entry, $length-1);
    }
}


/**
 * LookupHelper permettant de rechercher des suggestions au sein d'une table de
 * lookup simple.
 *
 * Ce helper recherche la forme riche des entrées qui commencent par l'un des
 * mots indiqués par l'utilisateur et ne retient que celles qui commencent par l'expression
 * de recherche indiquée par l'utilisateur.
 *
 * @package     fab
 * @subpackage  database
 */
class SimpleTableLookup extends LookupHelper
{
    /**
     * $charFroms et $charTo représentent les tables de conversion de caractères utilisées pour
     * rechercher les entrées au format riche à partir de la chaine de recherche indiquée par
     * l'utilisateur.
     *
     * Remarque : les chaines sont "triées" de telle façon que les itérateurs utilisés
     * par {@link SimpleTableLookup} ne font que avancer (utiliser la méthode sortByChar()
     * qui figure en commentaires à la fin de cette classe).
     *
     * Par rapport aux chaines définies dans Utils::Tokenize(), j'ai ajouté tous les signes de
     * ponctuation "tapables au clavier" pour permettre de trouver des entrées telles que
     * "(LA) REVUE DU PRATICIEN".
     */
//  protected static $charFroms = '!"#$%&\'()*+,-./:;<=>?@[\\]^_`{|}~AaÀÁÂÃÄÅàáâãäåBbCcÇçDdĞğEeÈÉÊËèéêëFfGgHhIiÌÍÎÏìíîïJjKkLlMmNnÑñOoÒÓÔÕÖòóôõöPpQqRrSsßTtŞşUuÙÚÛÜùúûüVvWwXxYyİıÿZzŒœØÆæ';
//  protected static $charTo    =   '                                aaaaaaaaaaaaaabbccccddddeeeeeeeeeeffgghhiiiiiiiiiijjkkllmmnnnnooooooooooooppqqrrsssttttuuuuuuuuuuvvwwxxyyyyyzzœœœææ';

// Version simplifiée avec des lettres en moins
    protected static $charFroms = ' !"#$%&\'()*+,-./:;<=>?@[\\]^_`{|}~0123456789AaÀÁÂÃÄÅàáâãäåBbCcÇçDdĞğEeÈÉÊËèéêëFfGgHhIiìîïJjKkLlMmNnñOoôöPpQqRrSsTtŞşUuÙÚÛÜùúûüVvWwXxYyİıÿZzŒœØÆæ';

    /**
     * cf {@link $charFroms}.
     *
     * @var string
     */
    protected static $charTo    =   '                                 0123456789aaaaaaaaaaaaaabbccccddddeeeeeeeeeeffgghhiiiiijjkkllmmnnnooooppqqrrssttttuuuuuuuuuuvvwwxxyyyyyzzœœœææ';


    /**
     * @inheritdoc
     */
    protected $alreadySorted = false;


    /**
     * Stocke l'entrée d'index en cours.
     *
     * @var string
     */
    private $entry='';


    /**
     * @inheritdoc
     */
    protected function getEntries(array & $result, array & $sortKeys)
    {
        // $this->sortByChar(); die(); // Désactiver cette ligne pour trier charFroms et charTo correctement.

        if ($this->debug) echo '<pre>';

        // Construit la version tokenizée de la chaine recherchée
        // Entre chaque mot, on insère un caractère "¤" qui représente un espace de taille variable
        $this->search = implode('¤', $this->words);

        // Apparemment, une fois qu'un TermIterator a atteint la fin de la liste, tous les appels
        // ultérieurs à skip_to() "échouent" (i.e. on reste sur EOF).
        // Pour éviter cela, on clone le TermIterator utilisé.
        // Code de test :
        // $this->begin->skip_to('T5:~'); // eof===true (i.e. $this->begin->equals($this->end)
        // $this->begin->skip_to('T5:LA'); // eof encore à true, alors que cela ne devrait pas

        // Sauvegarde l'itérateur de début
        $begin = $this->begin;

        // Clone l'itérateur et recherche les entrées qui commencent par ce que l'utilisateur a tapé
        if ($this->debug) echo "<h1>this.search=$this->search</h1>";
        $this->begin = new XapianTermIterator($begin);
        $this->search($this->search, $result, $sortKeys);

        // Utilise l'itérateur initial et recherche les entrées qui commencent par un blanc
        // exemple : "(LA) REVUE DU PRATICIEN"
        if ($this->search !== ' ') // si value=='(', words=array(' '), this.search==' ' et c'est inutile de relancer la recherche
        {
            $this->begin = $begin;
            $this->search = '¤' . $this->search;
            $this->entry = ''; // Réinitialise l'index pour que la recherche recommence au début
            if ($this->debug) echo "<h1>this.search=$this->search</h1>";
            $this->search($this->search, $result, $sortKeys);
        }
    }


    /**
     * Génère toutes les variations de lettres possibles à l'offset indiqué et recherche les
     * entrées correspondantes.
     *
     * @param string $word
     * @param array $result
     * @param array $sortKeys
     * @param int $offset
     */
    protected function search($word, array & $result, array & $sortKeys, $offset=0)
    {
        // Appelle la méthode space() si c'est un "blanc" à géométrie variable
        if ($word[$offset] === '¤')
            $this->space($word, $result, $sortKeys, $offset);

        // Appelle la méthode letter() sinon
        else
            $this->letter($word, $result, $sortKeys, $offset);
    }


    /**
     * Traite les espaces trouvés dans la chaine de recherche tokenisée.
     *
     * Génère toutes les variations possibles à l'offset indiqué et recherche les entrées
     * correspondantes.
     *
     * @param string $word
     * @param array $result
     * @param array $sortKeys
     * @param int $offset
     */
    protected function space($word, array & $result, array & $sortKeys, $offset)
    {
        // Dans la chaine tokenisée, un espace représente de un à 7 caractères "blancs".
        // Dans les autcoll, par exemple, on : "(E.N.S.P.). .. Service" (7 blancs entre P et S)
        for ($repeat=1 ; $repeat <= 7 ; $repeat++)
        {
            $this->entry='';
            $h = substr($word, 0, $offset) . str_repeat(' ', $repeat) . substr($word, $offset+1);

            $this->variations($h, $result, $sortKeys, $offset);
        }

        // Suppression des espaces en trop
        // Exemple : l'utilisateur a tapé "BEH Web" et on a "BEHWeb" dans la table
        // On ne le fait pas si l'espace est au début ou à la fin (sinon, on sort une seconde fois
        // toutes les suggestions déjà trouvées)
        if ($offset > 0 && $offset < strlen($word)-1)
        {
            $this->entry='';
            $h = substr($word, 0, $offset) . substr($word, $offset+1);
            $this->variations($h, $result, $sortKeys, $offset);
        }
    }


    /**
     * Traite les lettres et les chiffres trouvés dans la chaine de recherche tokenisée.
     *
     * Génère toutes les variations possibles à l'offset indiqué et recherche les entrées
     * correspondantes.
     *
     * @param string $word
     * @param array $result
     * @param array $sortKeys
     * @param int $offset
     */
    protected function letter($word, array & $result, array & $sortKeys, $offset)
    {
        // Recherche toutes les variations possibles
        $this->variations($word, $result, $sortKeys, $offset);

        // Recherche de sigles
        // Exemple : l'utilisateur a tapé "SESI" et on a "S.E.S.I." dans la table
        if ($offset && ctype_alpha($word[$offset-1]))
        {
            // Réinitialise l'index pour que la recherche recommence au début
            $this->entry = '';

            // Insère un espace (un '.') juste après la lettre en cours
            $h = substr($word, 0, $offset) . ' ' . substr($word, $offset);

            // Teste s'il existe des entrées commençant par le terme obtenu
            $this->variations($h, $result, $sortKeys, $offset);
        }
    }

    /**
     * Génère toutes les variations de caractère possibles à l'offset indiqué et stocke les
     * entrées qui correspondent.
     *
     * @param string $word
     * @param array $result
     * @param array $sortKeys
     * @param int $offset
     */
    protected function variations($word, array & $result, array & $sortKeys, $offset)
    {
        // Détermine le caractère pour lequel on va générer toutes les variantes
        $char = $word[$offset];
        $lastPos = strlen($word)-1;

        // Génère toutes les variantes possibles pour ce caractère
        $i=0;
        while (false !== $i=strpos(self::$charTo, $char, $i))
        {
            // Modifie le mot avec la variante de la lettre en cours
            $word[$offset] = self::$charFroms[$i++];

            // Construit le préfixe utilisé pour la recherche
            $start = $this->prefix . substr($word, 0, $offset+1);

            // Passe à la variante suivante s'il n'y a aucune entrée qui commence par $start
            if (! $this->getEntry($start)) continue;

            // Si on vient de traiter la dernière lettre du mot , stocke les entrées obtenues
            if ($offset === $lastPos)
            {
                if ($this->debug) echo "MATCH<br />";
                $this->getMatches($start, $result, $sortKeys);
            }

            // Sinon, récursive et génére les variantes possibles à la position suivante
            else
            {
                // Passe à la lettre suivante
                $this->search($word, $result, $sortKeys, $offset+1);
            }
        }
    }

    /**
     * Stocke dans les tableaux résultats toutes les entrées qui commencent par le préfixe indiqué.
     *
     * @param string $start
     * @param array $result
     * @param array $sortKeys
     */
    protected function getMatches($start, array & $result, array & $sortKeys)
    {
        // debug, inutile
        if (! (strncmp($start, $this->entry, strlen($start)) === 0))
            if ($this->debug) echo "<li><b style='color:RED'>ERROR start = $start, entry=$this->entry</b><br /></li>";

        // Stocke toutes les entrées qui commencent par start
        $nb = 0;
        while (strncmp($start, $this->entry, strlen($start)) === 0)
        {
            if ($this->debug) echo "<li><b style='color:green'>$this->entry</b><br /></li>";

            // Supprime le préfixe de l'entrée (par exemple "T3:")
            $entry = substr($this->entry, strlen($this->prefix));

            // Détermine la clé utilisée pour trier l'entrée
            $key = implode(' ', Utils::tokenize($entry)) ;
            //$key = Utils::convertString($entry, 'alphanum');
            $key = implode('', Utils::tokenize($entry)) ;

            // Met les termes en surbrillance
            $entry = $this->highlight($entry, strlen($start)-strlen($this->prefix));

            // Stocke la suggestion dans le tableau résultat
            if (isset($result[$entry]))
            {
                $result[$entry] = max($result[$entry], $this->begin->get_termfreq());
            }
            else
            {
                $result[$entry] = $this->begin->get_termfreq();
                $sortKeys[] = $key;
            }

            // Si on a trouvé suffisamment d'entrées, terminé
            if (++$nb > $this->max) return;

            // Passe à l'entrée suivante
            $this->begin->next();

            if ($this->begin->equals($this->end))
            {
                if ($this->debug) echo "EOF (1), begin===end<br />";
                return;
            }


            $this->entry=$this->begin->get_term();
        }

        if ($this->debug) echo "Done : ",substr($this->entry,0,strlen($start)+15),"...<br />";
    }


    /**
     * Positionne l'index sur la première entrée qui commence par le préfixe indiqué.
     *
     * La propriété $this->entry est initialisée avec l'entrée trouvée.
     *
     * @param false|string $start retourne false si aucune entrée ne commence par $start ou
     * l'entrée trouvée sinon.
     *
     * @return bool <code>true</code> si une entrée commençant par <code>$start</code> a été
     * trouvée, <code>false</code> sinon.
     */
    protected function getEntry($start)
    {
        // Si l'entrée en cours est plus grande que $start, inutile de faire un appel à skip_to()
        if (strncmp($start, $this->entry, strlen($start)) < 0) return false;

        // Positionne l'index sur les entrées qui commencent par start
        if ($this->debug) echo "<b style='color:red'>skip_to('$start')</b><br />";
        $this->begin->skip_to($start);

        // Récupère l'entrée obtenue
        if ($this->begin->equals($this->end)) return false;
        $this->entry=$this->begin->get_term();

        // Si elle ne commence pas par start, terminé
        if (strncmp($start, $this->entry, strlen($start)) !== 0) return false;

        // OK, trouvé
        return true;
    }

    /**
     * Méthode interne utilisée pour trier les chaines charFroms et charTo
     * de telle manière que les itérateurs Xapian ne fasse que "avancer" (ie
     * pas de retour en arrière).
     *
     * Pour cela, on trie les caractères d'abord par "charTo" puis par
     * "charFrom".
     */
    /* Cette fonction est volontairement en commentaires, l'activer en cas de besoin... */
/*
    private function sortByChar()
    {
        $charFroms = self::$charFroms;
        $charTo    = self::$charTo;

        // Crée un tableau de tableaux à partir des chaines
        $t=array();
        for ($i=0; $i<strlen($charTo); $i++)
        {
            $t[$charTo[$i]][]=$charFroms[$i];
        }

        // Tri des clés
        ksort($t, SORT_REGULAR);

        // Tri des valeurs
        foreach($t as &$chars)
            sort($chars, SORT_REGULAR);
        unset($chars);

        // Reconstitue les chaines
        $from='';
        $to='';
        foreach($t as $key=>$chars)
        {
            foreach($chars as $char)
            {
                $from.=$char;
                $to.=$key;
            }

        }

        header('Content-type: text/plain; charset=CP1252');

        // Affiche le résultat
        echo "Anciennes tables :\n";
        echo '    protected static $charFroms = ', var_export($charFroms,true), ";\n";
        echo '    protected static $charTo    =   ', var_export($charTo,true), ";\n";

        if ($charFroms===$from && $charTo===$to)
            echo "\nLes tables sont déjà correctement triées.\n";
        else
        {
            echo "\nLes tables ne sont pas correctement triées :\n";
            echo "\nNouvelles tables :\n";
            echo '    protected static $charFroms = ', var_export($from,true), ";\n";
            echo '    protected static $charTo    =  ', var_export($to,true), ";\n";
        }
    }
*/
}

/**
 * LookupHelper permettant de rechercher des suggestions au sein des index
 * composant un alias.
 *
 * AliasLookup est en fait un aggrégateur qui combine les suggestions retournées
 * par les LookupHelper qu'il contient.
 *
 * @package     fab
 * @subpackage  database
 */
class AliasLookup extends LookupHelper
{
    /**
     * @inheritdoc
     */
    protected $alreadySorted = false;


    /**
     * Liste des helpers qui ont été ajoutés via {@link add()}
     *
     * @var array
     */
    protected $lookups=array();

    /**
     * Ajoute un LookupHelper
     *
     * @param LookupHelper $item
     */
    public function add(LookupHelper $lookup)
    {
        $this->lookups[] = $lookup;
    }

    /**
     * @inheritdoc
     */
    protected function getEntries(array & $result, array & $sortKeys)
    {
        foreach($this->lookups as $lookup)
        {
            $lookup->max = $this->max;
            $lookup->format = $this->format;
            $lookup->words = $this->words;
            if ($this->debug)
                echo get_class($lookup), "::getEntries(), max=<code>$this->max</code>, format=<code>$this->format</code><blockquote>";
            $lookup->getEntries($result, $sortKeys);
            if ($this->debug)
                echo '</blockquote>';
        }
    }
}