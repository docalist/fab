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
    // Garder synchro avec Utils::tokenize.
    protected static $charFroms = '\'-AaÀÁÂÃÄÅàáâãäåBbCcÇçDdÐðEeÈÉÊËèéêëFfGgHhIiÌÍÎÏìíîïJjKkLlMmNnÑñOoÒÓÔÕÖòóôõöPpQqRrSsßTtÞþUuÙÚÛÜùúûüVvWwXxYyÝýÿZzŒœØŒœØ';
    protected static $charTo    =  '  aaaaaaaaaaaaaabbccccddddeeeeeeeeeeffgghhiiiiiiiiiijjkkllmmnnnnooooooooooooppqqrrsssttttuuuuuuuuuuvvwwxxyyyyyzzœœœæææ';

    // Remarque : les chaines ci-dessus sont "triées" de telle façon que les
    // itérateurs utilisés par SimpleTableLookup ne font que avancer.
    // Voir la méthode sortByChar() qui figure en commentaires à la fin de
    // cette classe.

    /**
     * L'itérateur xapian de début tel que retourné par
     * XapianDatabase::allterms_begin().
     *
     * @var XapianTermIterator
     */
    protected $begin=null;

    /**
     * L'itérateur xapian de fin tel que retourné par
     * XapianDatabase::allterms_end().
     *
     * @var XapianTermIterator
     */
    protected $end=null;

    /**
     * Le nombre maximum de suggestions à retourner.
     *
     * @var int
     */
    protected $max=null;

    /**
     * Flag indiquant s'il faut trier les suggestions par ordre alphabétique
     * (false) ou par nombre d'occurences (true).
     *
     * @var bool
     */
    protected $sortByFrequency=null;

    /**
     * Préfixe xapian des termes à rechercher.
     *
     * @var string
     */
    protected $prefix='';

    /**
     * Tableau contenant la version tokenisée des mots pour lesquels on veut
     * obtenir des suggestions.
     *
     * @var array
     */
    protected $words=null;

    /**
     * Le format (style sprintf) à utiliser pour mettre les mots de
     * l'utilisateur en surbrillance dans les suggestions obtenues.
     *
     * @var string
     */
    protected $format='';

    /**
     * Définit les itérateurs "all_terms" à utiliser.
     *
     * @param XapianTermIterator $begin
     * @param XapianTermIterator$end
     */
    public function setIterators(XapianTermIterator $begin, XapianTermIterator $end)
    {
        $this->begin=$begin;
        $this->end=$end;
    }

    /**
     * Définit le nombre maximum de suggestion à retourner.
     *
     * (0 ou négatif = pas de limites).
     *
     * @param int $max
     */
    public function setMax($max)
    {
        $max=(int)$max;
        if ($max<=0) $max=PHP_INT_MAX;
        $this->max=$max;
    }

    /**
     * Indique s'il faut trier les suggestions par nombre décroissant
     * d'occurences dans la base.
     *
     * Par défaut, les suggestions sont triées par ordre alphabétique (le
     * lookup retourne les $max premières). En appelant setSortByFrequency(true),
     * les suggestions seront triées par ordre décroissant d'occurences (le
     * lookup retournera les $max entrées les plus courantes).
     *
     * @param bool $flag
     */
    public function setSortByFrequency($flag=true)
    {
        $this->sortByFrequency=$flag;
    }

    /**
     * Définit le format à utiliser pour la mise en surbrillance des termes de
     * recherche de l'utilisateur au sein de chacun des suggestions trouvées.
     *
     * $format est une chaine qui sera appliquée à chacune des entrées en
     * utilisant la fonction sprintf() de php.
     *
     * Exemple de format : <strong>%s</strong>.
     *
     * Si $format est null ou s'il s'agit d'une chaine vide, aucune surbrillance
     * ne sera appliquée.
     *
     * @param string $format
     */
    public function setFormat($format)
    {
        if ($format==='') $format=null;
        $this->format=$format;
    }

    /**
     * Définit le préfixe des termes à utiliser pour la recherche des
     * suggestions.
     *
     * @param string $prefix
     */
    public function setPrefix($prefix='')
    {
        $this->prefix=$prefix;
    }

    /**
     * Trie le tableau de suggestions par ordre alphabétique (si
     * $sortByFrequency est à false) ou par nombre d'occurence (si
     * $sortByFrequency est à true).
     *
     * @param $result le tableau de suggestions à trier.
     */
    protected function sort(array & $result)
    {
        // Tri par occurences
        if ($this->sortByFrequency)
            arsort($result, SORT_NUMERIC);

        // Tri alpha
        else
            ksort($result, SORT_LOCALE_STRING);
    }

    /**
     * Recherche des suggestions pour les termes passés en paramètre.
     *
     * @param string $term une chaine contenant les mots pour lesquels on
     * souhaite obtenir des suggestions.
     *
     * @return array un tableau contenant les suggestions obtenues. Chaque clé
     * du tableau contient une suggestion et la valeur associée contient le
     * nombre d'occurences de cette entrée dans la base.
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
    public function lookup($term)
    {
        //$this->sortByChar(); die();

        // Extrait les mots à rechercher
        $this->words=Utils::tokenize($term);

        // Recherche pour chaque mot les entrées correspondantes
        $result=$this->lookupWords();

        // Trie les résultats
        $this->sort($result);

        // Limite à $max réponses
        if ($this->max < PHP_INT_MAX)
            $result=array_slice($result, 0, $this->max);

        // Retourne le tableau obtenu
        return $result;
    }

    /**
     * Recherche des suggestions pour chacun des mots présents dans l'expression
     * indiquée par l'utilisateur.
     *
     * @return array les suggestions obtenues.
     */
    protected function lookupWords()
    {
        $result=array();
        foreach($this->words as $word)
        {
            $t=array();
            $this->getTerms($word, $t);
            $result = $result + $t;
        }
        return $result;
    }

    /**
     * Met en surbrillance (selon le format en cours) les termes de recherche
     * de l'utilisateur dans l'entrée passée en paramètre.
     *
     * @param string $entry l'entrée à surligner
     * @param string $alphanumEntry une version "pauvre" (ne contenant que des
     * lettres et des chiffres) de $entry.
     * @return string la chaine $entry dans laquelle chaque occurence des mots
     * de l'utilisateur a été remplacée par le format en cours.
     */
    protected function highlight($entry, $alphanumEntry)
    {
        // Si aucun format de surbrillance n'a été indiqué, terminé
        if (is_null($this->format)) return $entry;

        // On va rechercher les mots dans alphanum et on va les remplacer par
        // sprintf($format) das la chaine $entry en ajustant à chaque fois les
        // positions pour gérer le décalage obtenu.
        $dec=0;
        $alphanumEntry = ' ' . $alphanumEntry;
        foreach($this->words as $required)
        {
            $pt=0;
            while (false !== $pt=strpos($alphanumEntry, ' '.$required, $pt))
            {
                $word=substr($entry, $pt+$dec, strlen($required));
                $replace=sprintf($this->format, $word);
                $entry=substr_replace($entry, $replace, $pt+$dec, strlen($required));
                $dec += (strlen($replace) - strlen($word));
                $pt += strlen($required);
            }
        }

        // Retourne la chaine obtenue
        return $entry;
    }

    /**
     * Recherche des suggestions pour le mot passé en paramètre.
     *
     * @param string $word le mot pour lequel on veut des suggestions (le mot
     * passé en paramètre est toujours la version "tokenisée" ne contenant que
     * des lettres minuscules non accentuées et des chiffres).
     *
     * @param $result un tableau dans lequel les suggestions obtenues seront
     * ajoutées.
     */
    abstract public function getTerms($word, array & $result);

    /**
     * Méthode interne utilisée pour trier les chaines charFroms et charTo
     * de telle manière que les itérateurs xapian ne fasse que "avancer" (ie
     * pas de retour en arrière).
     *
     * Pour cela, on trie les caractères d'abord par "charTo" puis par
     * "charFrom".
     */
    /* Cette fonction est volontairement en commentaires, l'activer en cas de besoin...
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

        // Affiche le résultat
        if ($charFroms===$from && $charTo===$to)
            echo 'Les tables sont déjà correctement triées<br />';
        else
        {
            echo 'Les tables ne sont pas triées :<br />';
            echo 'charFrom :';
            echo 'OLD:<code>', var_export($charFroms,true), '</code><br />';
            echo 'NEW:<code>', var_export($from,true), '</code><br />';
            echo 'charTo :';
            echo 'OLD:<code>', var_export($charTo,true), '</code><br />';
            echo 'NEW:<code>', var_export($to,true), '</code><br />';
        }
    }
    */
}

/**
 * LookupHelper permettant de rechercher des suggestions parmi les termes
 * simples présent dans l'index xapian.
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
     * Recherche des suggestions pour chacun des mots présents dans l'expression
     * indiquée par l'utilisateur.
     *
     * @return array les suggestions obtenues.
     */
    protected function lookupWords()
    {
        $result=array();
        $this->getTerms(end($this->words), $result);
        return $result;
    }

    /**
     * @inheritdoc
     */
    public function getTerms($word, array & $result)
    {
        // On va rechercher toutes les entrées qui commencent par le préfixe et le mot indiqués
        $start=$this->prefix . $word;

        // Va au début de la liste
        $this->begin->skip_to($start);

        // Boucle tant que les entrées commencent par ce qu'on cherche
        while (! $this->begin->equals($this->end))
        {
            // Récupère l'entrée en cours
            $entry=$this->begin->get_term();

            // Si elle ne commence pas par start, terminé
            if (strncmp($start, $entry, strlen($start)) !== 0) break;

            // L'entrée est de la forme <préfixe><terme>
            $entry=substr($entry, strlen($this->prefix));

            // Met les termes en surbrillance
            if (!is_null($this->format)) $entry=$this->highlight($entry, $entry);

            // Stocke la suggestion dans le tableau résultat
            $result[$entry]=$this->begin->get_termfreq();

            // Si on a trouvé assez de suggestions, terminé
            if (!$this->sortByFrequency && (count($result) >= $this->max)) break;

            // Passe à l'entrée suivante dans l'index
            $this->begin->next();
        }
    }
}

/**
 * LookupHelper permettant de rechercher des suggestions parmi les articles
 * présents dans l'index xapian.
 *
 * Ce helper recherche les articles qui commencent par l'un des mots indiqués
 * par l'utilisateur et ne retient que ceux contenant tous les mots (ou début
 * de mot) figurant dans l'expression recherchée.
 *
 * @package     fab
 * @subpackage  database
 */
class ValueLookup extends LookupHelper
{
    /**
     * @inheritdoc
     */
    public function getTerms($word, array & $result)
    {
        // On va rechercher toutes les entrées qui commencent par le préfixe et le mot indiqués
        $start=$this->prefix . '_' . $word;

        // Va au début de la liste
        $this->begin->skip_to($start);

        // Boucle tant que les entrées commencent par ce qu'on cherche
        while (! $this->begin->equals($this->end))
        {
            // Récupère l'entrée en cours
            $entry=$this->begin->get_term();

            // Si elle ne commence pas par start, terminé
            if (strncmp($start, $entry, strlen($start)) !== 0) break;

            // L'entrée est de la forme <préfixe><article>
            $entry=substr($entry, strlen($this->prefix));

            // Vérifie qu'elle contient tous les mots demandés (dans n'importe quel ordre)
            $ok=true;
            foreach($this->words as $required)
            {
                if (false === strpos($entry, '_' . $required))
                {
                    $ok=false;
                    break ;
                }
            }

            // OK, on a trouvé une suggestion
            if ($ok)
            {
                // Supprime les underscore présents dans l'article
                $entry=trim(strtr($entry, '_', ' '));

                // Met les termes en surbrillance
                if (!is_null($this->format)) $entry=$this->highlight($entry, $entry);

                // Stocke la suggestion dans le tableau résultat
                $result[$entry]=$this->begin->get_termfreq();

                // Si on a trouvé assez de suggestions, terminé
                if (!$this->sortByFrequency && (count($result) >= $this->max)) break;
            }

            // Passe à l'entrée suivante dans l'index
            $this->begin->next();
        }
    }
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
     * Liste des helpers qui ont été ajoutés via {@link add()}
     *
     * @var array
     */
    protected $items=array();

    /**
     * Ajoute un LookupHelper
     *
     * @param LookupHelper $item
     */
    public function add(LookupHelper $item)
    {
        $this->items[]=$item;
    }

    /**
     * @inheritdoc
     */
    public function getTerms($word, array & $result)
    {
        foreach($this->items as $item)
        {
            $item->words=$this->words;
            $item->getTerms($word, $result);
        }
    }
}

/**
 * LookupHelper permettant de rechercher des suggestions au sein d'une table de
 * lookup simple.
 *
 * Ce helper recherche la forme riche des entrées qui commencent par l'un des
 * mots indiqués par l'utilisateur et ne retient que celles contenant tous les
 * mots (ou début de mot) figurant dans l'expression recherchée.
 *
 * @package     fab
 * @subpackage  database
 */
class SimpleTableLookup extends LookupHelper
{
    /**
     * @inheritdoc
     */
    public function getTerms($word, array & $result)
    {
        $this->recurse($result, $word);
    }

    /**
     * Méthode récursive utilisée par {@link getTerms()} pour rechercher les
     * suggestions.
     *
     * Chaque niveau de récursion correspond à une lettre du mot étudié (en
     * commen_ant à gauche). A chaque niveau, on génère toutes les variantes
     * possibles pour cette lettre et on teste s'il existe des entrées
     * commençant par ce début de mot.
     *
     * @param $result le tableau de résultat dans lequel seront stockées les
     * suggestions obtenues.
     * @param $word le mot en cours
     * @param $offset la position en cours au sein de word
     */
    private function recurse(array & $result, $word, $offset=0)
    {
        // Stocke l'entrée en cours de l'index xapian. Cette propriété est
        // statique pour qu'on puisse "élaguer" l'arbre de recherche sans avoir
        // à consulter l'index quel que soit le niveau de récursion en cours.
        static $entry;

        // A chaque fois qu'on commence un nouveau mot, réinitialise $entry.
        if ($offset===0) $entry='';

        // Détermine le caractère pour lequel on va générer toutes les variantes
        // à ce niveau de récursion.
        $char=$word[$offset];

        // Pour générer toutes les formes possibles de la lettre en cours, on
        // utilise les chaines charFrom et charTo "à l'envers" : on recherche
        // le caractère dans charTo et on récupère la lettre accentuée
        // correspondante dans charFroms.
        $i=0;
        while (false !== $i=strpos(self::$charTo, $char, $i))
        {
            // Modifie le mot avec la variante en cours
            $word[$offset]=self::$charFroms[$i++];

            // On va tester s'il existe des entrées avec ce début de mot
            $start=$this->prefix.substr($word, 0, $offset+1);

            // Si l'entrée en cours est plus grande que start, cela veut dire
            // qu'il n'y a aucune entrée commençant par start, donc ce n'est
            // pas la peine de continuer à générer toutes les formes possibles
            // du mot recherché (élagage).
            if (strncmp($start, $entry, strlen($start))<0) continue;

            // Positionne l'index sur les entrées qui commencent par start
            $this->begin->skip_to($start);

            // Récupère l'entrée en cours
            $entry=$this->begin->get_term();

            // Si elle ne commence pas par start, terminé
            if (strncmp($start, $entry, strlen($start)) !== 0) continue;

            // Si on est en fin de mot, stocke les suggestions obtenues
            if ($offset===strlen($word)-1)
            {
                // Examine toutes les entrées qui commencent par start
                for(;;)
                {
                    // L'entrée est de la forme <préfixe><forme riche de l'entrée>
                    $entry=substr($entry, strlen($this->prefix));

                    // Crée la forme "pauvre" de l'entrée à partir de la forme "riche"
                    $h=Utils::convertString($entry, 'alphanum');

                    // remarque : on doit utiliser convertString et non pas
                    // strtr(charfroms,charto) car l'entrée est au format "riche"

                    // Vérifie que l'entrée contient tous les mots recherchés
                    $ok=true;
                    foreach($this->words as $required)
                    {
                        if (false === strpos(' ' . $h, ' ' . $required))
                        {
                            $ok=false;
                            break ;
                        }
                    }

                    // OK, on a trouvé une suggestion
                    if ($ok)
                    {
                        // Met les termes en surbrillance
                        if (!is_null($this->format)) $entry=$this->highlight($entry, $h);

                        // Stocke la suggestion dans le tableau résultat
                        $result[$entry]=$this->begin->get_termfreq();

                        // Si on a trouvé assez de suggestions, terminé
                        if (!$this->sortByFrequency && (count($result) >= $this->max)) return;
                    }

                    // Passe à l'entrée suivante dans l'index
                    $this->begin->next(); // todo: compare à end() ?

                    // Récupère l'entrée en cours
                    $entry=$this->begin->get_term();

                    // Si elle ne commence pas par start, terminé
                    if (strncmp($start, $entry, strlen($start)) !== 0) break;
                }
            }

            // Sinon, récursive et génére toutes les variantes possibles pour la lettre suivante du mot
            else
            {
                // Passe à la lettre suivante
                $this->recurse($result, $word, $offset+1);

                // Si on a trouvé assez de suggestions, terminé
                if (!$this->sortByFrequency && (count($result) >= $this->max)) return;
            }
        }
    }
}

/**
 * LookupHelper permettant de rechercher des suggestions au sien d'une table
 * de lookup inversée.
 *
 * Ce helper recherche la forme riche des entrées qui contiennent l'un des
 * mots indiqués par l'utilisateur et ne retient que celles contenant tous les
 * mots (ou début de mot) figurant dans l'expression recherchée.
 *
 * @package     fab
 * @subpackage  database
 */
class InvertedTableLookup extends LookupHelper
{
    public function getTerms($word, array & $result)
    {
        // On va rechercher toutes les entrées qui commencent par le préfixe et le mot indiqués
        $start=$this->prefix.$word;

        // Va au début de la liste
        $this->begin->skip_to($start);

        // Boucle tant que les entrées commencent par ce qu'on cherche
        while ( ! $this->begin->equals($this->end))
        {
            // Récupère l'entrée en cours
            $entry=$this->begin->get_term();

            // Si elle ne commence pas par start, terminé
            if (strncmp($start, $entry, strlen($start)) !== 0) break;

            // L'entrée est de la forme <mot tokenisé>=<forme riche de l'entrée>
            $entry=substr($entry, strpos($entry, '=')+1);

            // Crée la forme "pauvre" de l'entrée à partir de la forme "riche"
            $h=Utils::convertString($entry, 'alphanum');

            // remarque : on doit utiliser convertString et non pas
            // strtr(charfroms,charto) car l'entrée est au format "riche"

            // Vérifie que l'entrée contient tous les mots recherchés
            $ok=true;
            foreach($this->words as $required)
            {
                if (false === strpos(' ' . $h, ' ' . $required))
                {
                    $ok=false;
                    break ;
                }
            }

            // OK, on a trouvé une suggestion
            if ($ok)
            {
                // Met les termes en surbrillance
                if (!is_null($this->format)) $entry=$this->highlight($entry, $h);

                // Stocke la suggestion dans le tableau résultat
                $result[$entry]=$this->begin->get_termfreq();

                // Si on a trouvé assez de suggestions, terminé
                if (!$this->sortByFrequency && (count($result) >= $this->max)) return;
            }

            // Passe à l'entrée suivante dans l'index
            $this->begin->next();
        }
    }
}

?>