<?php
/**
 * @package     fab
 * @subpackage  modules
 * @author      Daniel Ménard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id$
 */

/**
 * Méthodes de dédoublonnage basée sur la comparaison des tokens 
 * 
 * @package     fab
 * @subpackage  modules
 */

class DedupTokens extends DedupMethod
{
    /**
     * Retourne une équation de recherche contenant les mots significatifs
     * présents dans la valeur passée en paramètre.
     *
     * Si la valeur passée en paramètre est un tableau, celui-ci est linéarisé 
     * (concaténation des articles).
     * 
     * Si un même mot apparait plusieurs fois, il n'apparaitra qu'une seule 
     * fois dans l'équation finale.
     * 
     * Si la valeur passée en paramètre est vide (null ou chaine vide) un 
     * tableau vide est retournée.
     * 
     * @param null|string|array $value
     * @return array
     */
    protected function getTokens($value)
    {
        static $operators=array
        (
            'et'=>0, 'ou'=>0, 'sauf'=>0, 'but'=>0, 
            'and'=>0, 'or'=>0, 'not'=>0, 'xor'=>0, 'near'=>0
        );
        
        static $lastValue=null;
        static $tokens=array();
        
        if ($value===$lastValue) return $tokens;
        $lastValue=$value;
        
        // Si value est un tableau, on le linéarise
        if (is_array($value)) $value=implode('¤', $value);

        // Extrait les tokens
        $tokens=Utils::tokenize($value);
        
        // Dédoublonne
        $tokens=array_flip($tokens);

        // Supprime tous les mots qui posent problème dans une équation (opérateurs)
        $tokens=array_diff_key($tokens, $operators);
        
        // Retourne le résultat
        return $tokens;
    }
    
    public function getEquation($value)
    {
        // Extrait les tokens
        $tokens=$this->getTokens($value);
        
        // Concatène les tokens
        $equation=implode(' ', array_keys($tokens));
        
        if (count($tokens)>1) $equation='(' . $equation . ')';
        return $equation;
    }

    public function compare($a, $b)
    {
        // Tokenize les deux valeurs
        $ta=$this->getTokens($a);
        if (count($ta)===0) return 0;
        
        $tb=$this->getTokens($b);
        if (count($tb)===0) return 0;
        
        // Liste des tokens communs aux deux
        $common=array_intersect_key($ta,$tb);

        if (debug) echo count($ta), ' tokens dans la notice : ', implode(', ', array_keys($ta)), '<br />';
        if (debug) echo count($tb), ' tokens dans le doublon : ', implode(', ', array_keys($tb)), '<br />';
        if (debug) echo count($common), ' tokens en communs : ', implode(', ', array_keys($common)), '<br />';
        
        $score=count($common)*2*100 / (count($ta)+count($tb));
        if (debug) echo 'Score obtenu : ', $score, '%<br />';
        
        return $score;
    }
}
