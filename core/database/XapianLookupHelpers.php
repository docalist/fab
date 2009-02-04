<?php
/**
 * @package     fab
 * @subpackage  database
 * @author      Daniel M�nard <Daniel.Menard@bdsp.tm.fr>
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
    protected static $charFroms = '\'-Aa������������BbCc��Dd��Ee��������FfGgHhIi��������JjKkLlMmNn��Oo����������PpQqRrSs�Tt��Uu��������VvWwXxYy���Zz��،��';
    protected static $charTo    =  '  aaaaaaaaaaaaaabbccccddddeeeeeeeeeeffgghhiiiiiiiiiijjkkllmmnnnnooooooooooooppqqrrsssttttuuuuuuuuuuvvwwxxyyyyyzz������';

    // Remarque : les chaines ci-dessus sont "tri�es" de telle fa�on que les
    // it�rateurs utilis�s par SimpleTableLookup ne font que avancer.
    // Voir la m�thode sortByChar() qui figure en commentaires � la fin de
    // cette classe.

    /**
     * L'it�rateur xapian de d�but tel que retourn� par
     * XapianDatabase::allterms_begin().
     *
     * @var XapianTermIterator
     */
    protected $begin=null;

    /**
     * L'it�rateur xapian de fin tel que retourn� par
     * XapianDatabase::allterms_end().
     *
     * @var XapianTermIterator
     */
    protected $end=null;

    /**
     * Le nombre maximum de suggestions � retourner.
     *
     * @var int
     */
    protected $max=null;

    /**
     * Flag indiquant s'il faut trier les suggestions par ordre alphab�tique
     * (false) ou par nombre d'occurences (true).
     *
     * @var bool
     */
    protected $sortByFrequency=null;

    /**
     * Pr�fixe xapian des termes � rechercher.
     *
     * @var string
     */
    protected $prefix='';

    /**
     * Tableau contenant la version tokenis�e des mots pour lesquels on veut
     * obtenir des suggestions.
     *
     * @var array
     */
    protected $words=null;

    /**
     * Le format (style sprintf) � utiliser pour mettre les mots de
     * l'utilisateur en surbrillance dans les suggestions obtenues.
     *
     * @var string
     */
    protected $format='';

    /**
     * D�finit les it�rateurs "all_terms" � utiliser.
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
     * D�finit le nombre maximum de suggestion � retourner.
     *
     * (0 ou n�gatif = pas de limites).
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
     * Indique s'il faut trier les suggestions par nombre d�croissant
     * d'occurences dans la base.
     *
     * Par d�faut, les suggestions sont tri�es par ordre alphab�tique (le
     * lookup retourne les $max premi�res). En appelant setSortByFrequency(true),
     * les suggestions seront tri�es par ordre d�croissant d'occurences (le
     * lookup retournera les $max entr�es les plus courantes).
     *
     * @param bool $flag
     */
    public function setSortByFrequency($flag=true)
    {
        $this->sortByFrequency=$flag;
    }

    /**
     * D�finit le format � utiliser pour la mise en surbrillance des termes de
     * recherche de l'utilisateur au sein de chacun des suggestions trouv�es.
     *
     * $format est une chaine qui sera appliqu�e � chacune des entr�es en
     * utilisant la fonction sprintf() de php.
     *
     * Exemple de format : <strong>%s</strong>.
     *
     * Si $format est null ou s'il s'agit d'une chaine vide, aucune surbrillance
     * ne sera appliqu�e.
     *
     * @param string $format
     */
    public function setFormat($format)
    {
        if ($format==='') $format=null;
        $this->format=$format;
    }

    /**
     * D�finit le pr�fixe des termes � utiliser pour la recherche des
     * suggestions.
     *
     * @param string $prefix
     */
    public function setPrefix($prefix='')
    {
        $this->prefix=$prefix;
    }

    /**
     * Trie le tableau de suggestions par ordre alphab�tique (si
     * $sortByFrequency est � false) ou par nombre d'occurence (si
     * $sortByFrequency est � true).
     *
     * @param $result le tableau de suggestions � trier.
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
     * Recherche des suggestions pour les termes pass�s en param�tre.
     *
     * @param string $term une chaine contenant les mots pour lesquels on
     * souhaite obtenir des suggestions.
     *
     * @return array un tableau contenant les suggestions obtenues. Chaque cl�
     * du tableau contient une suggestion et la valeur associ�e contient le
     * nombre d'occurences de cette entr�e dans la base.
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

        // Extrait les mots � rechercher
        $this->words=Utils::tokenize($term);

        // Recherche pour chaque mot les entr�es correspondantes
        $result=$this->lookupWords();

        // Trie les r�sultats
        $this->sort($result);

        // Limite � $max r�ponses
        if ($this->max < PHP_INT_MAX)
            $result=array_slice($result, 0, $this->max);

        // Retourne le tableau obtenu
        return $result;
    }

    /**
     * Recherche des suggestions pour chacun des mots pr�sents dans l'expression
     * indiqu�e par l'utilisateur.
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
     * de l'utilisateur dans l'entr�e pass�e en param�tre.
     *
     * @param string $entry l'entr�e � surligner
     * @param string $alphanumEntry une version "pauvre" (ne contenant que des
     * lettres et des chiffres) de $entry.
     * @return string la chaine $entry dans laquelle chaque occurence des mots
     * de l'utilisateur a �t� remplac�e par le format en cours.
     */
    protected function highlight($entry, $alphanumEntry)
    {
        // Si aucun format de surbrillance n'a �t� indiqu�, termin�
        if (is_null($this->format)) return $entry;

        // On va rechercher les mots dans alphanum et on va les remplacer par
        // sprintf($format) das la chaine $entry en ajustant � chaque fois les
        // positions pour g�rer le d�calage obtenu.
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
     * Recherche des suggestions pour le mot pass� en param�tre.
     *
     * @param string $word le mot pour lequel on veut des suggestions (le mot
     * pass� en param�tre est toujours la version "tokenis�e" ne contenant que
     * des lettres minuscules non accentu�es et des chiffres).
     *
     * @param $result un tableau dans lequel les suggestions obtenues seront
     * ajout�es.
     */
    abstract public function getTerms($word, array & $result);

    /**
     * M�thode interne utilis�e pour trier les chaines charFroms et charTo
     * de telle mani�re que les it�rateurs xapian ne fasse que "avancer" (ie
     * pas de retour en arri�re).
     *
     * Pour cela, on trie les caract�res d'abord par "charTo" puis par
     * "charFrom".
     */
    /* Cette fonction est volontairement en commentaires, l'activer en cas de besoin...
    private function sortByChar()
    {
        $charFroms = self::$charFroms;
        $charTo    = self::$charTo;

        // Cr�e un tableau de tableaux � partir des chaines
        $t=array();
        for ($i=0; $i<strlen($charTo); $i++)
        {
            $t[$charTo[$i]][]=$charFroms[$i];
        }

        // Tri des cl�s
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

        // Affiche le r�sultat
        if ($charFroms===$from && $charTo===$to)
            echo 'Les tables sont d�j� correctement tri�es<br />';
        else
        {
            echo 'Les tables ne sont pas tri�es :<br />';
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
 * simples pr�sent dans l'index xapian.
 *
 * Contrairement aux autres, ce helper ne sait pas faire des suggestions pour
 * une chaine contenant plusieurs mots (la raison est simple : les termes dans
 * l'index sont des mots uniques).
 *
 * Si une expression contenant plusieurs mots est recherch�e, seul le dernier
 * mot sera utilis� pour faire des suggestions.
 *
 * @package     fab
 * @subpackage  database
 */
class TermLookup extends LookupHelper
{
    /**
     * Recherche des suggestions pour chacun des mots pr�sents dans l'expression
     * indiqu�e par l'utilisateur.
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
        // On va rechercher toutes les entr�es qui commencent par le pr�fixe et le mot indiqu�s
        $start=$this->prefix . $word;

        // Va au d�but de la liste
        $this->begin->skip_to($start);

        // Boucle tant que les entr�es commencent par ce qu'on cherche
        while (! $this->begin->equals($this->end))
        {
            // R�cup�re l'entr�e en cours
            $entry=$this->begin->get_term();

            // Si elle ne commence pas par start, termin�
            if (strncmp($start, $entry, strlen($start)) !== 0) break;

            // L'entr�e est de la forme <pr�fixe><terme>
            $entry=substr($entry, strlen($this->prefix));

            // Met les termes en surbrillance
            if (!is_null($this->format)) $entry=$this->highlight($entry, $entry);

            // Stocke la suggestion dans le tableau r�sultat
            $result[$entry]=$this->begin->get_termfreq();

            // Si on a trouv� assez de suggestions, termin�
            if (!$this->sortByFrequency && (count($result) >= $this->max)) break;

            // Passe � l'entr�e suivante dans l'index
            $this->begin->next();
        }
    }
}

/**
 * LookupHelper permettant de rechercher des suggestions parmi les articles
 * pr�sents dans l'index xapian.
 *
 * Ce helper recherche les articles qui commencent par l'un des mots indiqu�s
 * par l'utilisateur et ne retient que ceux contenant tous les mots (ou d�but
 * de mot) figurant dans l'expression recherch�e.
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
        // On va rechercher toutes les entr�es qui commencent par le pr�fixe et le mot indiqu�s
        $start=$this->prefix . '_' . $word;

        // Va au d�but de la liste
        $this->begin->skip_to($start);

        // Boucle tant que les entr�es commencent par ce qu'on cherche
        while (! $this->begin->equals($this->end))
        {
            // R�cup�re l'entr�e en cours
            $entry=$this->begin->get_term();

            // Si elle ne commence pas par start, termin�
            if (strncmp($start, $entry, strlen($start)) !== 0) break;

            // L'entr�e est de la forme <pr�fixe><article>
            $entry=substr($entry, strlen($this->prefix));

            // V�rifie qu'elle contient tous les mots demand�s (dans n'importe quel ordre)
            $ok=true;
            foreach($this->words as $required)
            {
                if (false === strpos($entry, '_' . $required))
                {
                    $ok=false;
                    break ;
                }
            }

            // OK, on a trouv� une suggestion
            if ($ok)
            {
                // Supprime les underscore pr�sents dans l'article
                $entry=trim(strtr($entry, '_', ' '));

                // Met les termes en surbrillance
                if (!is_null($this->format)) $entry=$this->highlight($entry, $entry);

                // Stocke la suggestion dans le tableau r�sultat
                $result[$entry]=$this->begin->get_termfreq();

                // Si on a trouv� assez de suggestions, termin�
                if (!$this->sortByFrequency && (count($result) >= $this->max)) break;
            }

            // Passe � l'entr�e suivante dans l'index
            $this->begin->next();
        }
    }
}

/**
 * LookupHelper permettant de rechercher des suggestions au sein des index
 * composant un alias.
 *
 * AliasLookup est en fait un aggr�gateur qui combine les suggestions retourn�es
 * par les LookupHelper qu'il contient.
 *
 * @package     fab
 * @subpackage  database
 */
class AliasLookup extends LookupHelper
{
    /**
     * Liste des helpers qui ont �t� ajout�s via {@link add()}
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
 * Ce helper recherche la forme riche des entr�es qui commencent par l'un des
 * mots indiqu�s par l'utilisateur et ne retient que celles contenant tous les
 * mots (ou d�but de mot) figurant dans l'expression recherch�e.
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
     * M�thode r�cursive utilis�e par {@link getTerms()} pour rechercher les
     * suggestions.
     *
     * Chaque niveau de r�cursion correspond � une lettre du mot �tudi� (en
     * commen_ant � gauche). A chaque niveau, on g�n�re toutes les variantes
     * possibles pour cette lettre et on teste s'il existe des entr�es
     * commen�ant par ce d�but de mot.
     *
     * @param $result le tableau de r�sultat dans lequel seront stock�es les
     * suggestions obtenues.
     * @param $word le mot en cours
     * @param $offset la position en cours au sein de word
     */
    private function recurse(array & $result, $word, $offset=0)
    {
        // Stocke l'entr�e en cours de l'index xapian. Cette propri�t� est
        // statique pour qu'on puisse "�laguer" l'arbre de recherche sans avoir
        // � consulter l'index quel que soit le niveau de r�cursion en cours.
        static $entry;

        // A chaque fois qu'on commence un nouveau mot, r�initialise $entry.
        if ($offset===0) $entry='';

        // D�termine le caract�re pour lequel on va g�n�rer toutes les variantes
        // � ce niveau de r�cursion.
        $char=$word[$offset];

        // Pour g�n�rer toutes les formes possibles de la lettre en cours, on
        // utilise les chaines charFrom et charTo "� l'envers" : on recherche
        // le caract�re dans charTo et on r�cup�re la lettre accentu�e
        // correspondante dans charFroms.
        $i=0;
        while (false !== $i=strpos(self::$charTo, $char, $i))
        {
            // Modifie le mot avec la variante en cours
            $word[$offset]=self::$charFroms[$i++];

            // On va tester s'il existe des entr�es avec ce d�but de mot
            $start=$this->prefix.substr($word, 0, $offset+1);

            // Si l'entr�e en cours est plus grande que start, cela veut dire
            // qu'il n'y a aucune entr�e commen�ant par start, donc ce n'est
            // pas la peine de continuer � g�n�rer toutes les formes possibles
            // du mot recherch� (�lagage).
            if (strncmp($start, $entry, strlen($start))<0) continue;

            // Positionne l'index sur les entr�es qui commencent par start
            $this->begin->skip_to($start);

            // R�cup�re l'entr�e en cours
            $entry=$this->begin->get_term();

            // Si elle ne commence pas par start, termin�
            if (strncmp($start, $entry, strlen($start)) !== 0) continue;

            // Si on est en fin de mot, stocke les suggestions obtenues
            if ($offset===strlen($word)-1)
            {
                // Examine toutes les entr�es qui commencent par start
                for(;;)
                {
                    // L'entr�e est de la forme <pr�fixe><forme riche de l'entr�e>
                    $entry=substr($entry, strlen($this->prefix));

                    // Cr�e la forme "pauvre" de l'entr�e � partir de la forme "riche"
                    $h=Utils::convertString($entry, 'alphanum');

                    // remarque : on doit utiliser convertString et non pas
                    // strtr(charfroms,charto) car l'entr�e est au format "riche"

                    // V�rifie que l'entr�e contient tous les mots recherch�s
                    $ok=true;
                    foreach($this->words as $required)
                    {
                        if (false === strpos(' ' . $h, ' ' . $required))
                        {
                            $ok=false;
                            break ;
                        }
                    }

                    // OK, on a trouv� une suggestion
                    if ($ok)
                    {
                        // Met les termes en surbrillance
                        if (!is_null($this->format)) $entry=$this->highlight($entry, $h);

                        // Stocke la suggestion dans le tableau r�sultat
                        $result[$entry]=$this->begin->get_termfreq();

                        // Si on a trouv� assez de suggestions, termin�
                        if (!$this->sortByFrequency && (count($result) >= $this->max)) return;
                    }

                    // Passe � l'entr�e suivante dans l'index
                    $this->begin->next(); // todo: compare � end() ?

                    // R�cup�re l'entr�e en cours
                    $entry=$this->begin->get_term();

                    // Si elle ne commence pas par start, termin�
                    if (strncmp($start, $entry, strlen($start)) !== 0) break;
                }
            }

            // Sinon, r�cursive et g�n�re toutes les variantes possibles pour la lettre suivante du mot
            else
            {
                // Passe � la lettre suivante
                $this->recurse($result, $word, $offset+1);

                // Si on a trouv� assez de suggestions, termin�
                if (!$this->sortByFrequency && (count($result) >= $this->max)) return;
            }
        }
    }
}

/**
 * LookupHelper permettant de rechercher des suggestions au sien d'une table
 * de lookup invers�e.
 *
 * Ce helper recherche la forme riche des entr�es qui contiennent l'un des
 * mots indiqu�s par l'utilisateur et ne retient que celles contenant tous les
 * mots (ou d�but de mot) figurant dans l'expression recherch�e.
 *
 * @package     fab
 * @subpackage  database
 */
class InvertedTableLookup extends LookupHelper
{
    public function getTerms($word, array & $result)
    {
        // On va rechercher toutes les entr�es qui commencent par le pr�fixe et le mot indiqu�s
        $start=$this->prefix.$word;

        // Va au d�but de la liste
        $this->begin->skip_to($start);

        // Boucle tant que les entr�es commencent par ce qu'on cherche
        while ( ! $this->begin->equals($this->end))
        {
            // R�cup�re l'entr�e en cours
            $entry=$this->begin->get_term();

            // Si elle ne commence pas par start, termin�
            if (strncmp($start, $entry, strlen($start)) !== 0) break;

            // L'entr�e est de la forme <mot tokenis�>=<forme riche de l'entr�e>
            $entry=substr($entry, strpos($entry, '=')+1);

            // Cr�e la forme "pauvre" de l'entr�e � partir de la forme "riche"
            $h=Utils::convertString($entry, 'alphanum');

            // remarque : on doit utiliser convertString et non pas
            // strtr(charfroms,charto) car l'entr�e est au format "riche"

            // V�rifie que l'entr�e contient tous les mots recherch�s
            $ok=true;
            foreach($this->words as $required)
            {
                if (false === strpos(' ' . $h, ' ' . $required))
                {
                    $ok=false;
                    break ;
                }
            }

            // OK, on a trouv� une suggestion
            if ($ok)
            {
                // Met les termes en surbrillance
                if (!is_null($this->format)) $entry=$this->highlight($entry, $h);

                // Stocke la suggestion dans le tableau r�sultat
                $result[$entry]=$this->begin->get_termfreq();

                // Si on a trouv� assez de suggestions, termin�
                if (!$this->sortByFrequency && (count($result) >= $this->max)) return;
            }

            // Passe � l'entr�e suivante dans l'index
            $this->begin->next();
        }
    }
}

?>