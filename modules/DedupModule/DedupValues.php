<?php
/**
 * @package     fab
 * @subpackage  modules
 * @author      Daniel Ménard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id$
 */

/**
 * Méthodes de dédoublonnage basée sur la comparaison des articles 
 * 
 * @package     fab
 * @subpackage  modules
 */

class DedupValues extends DedupTokens
{
    /**
     * Retourne une équation de recherche contenant les articles présents dans 
     * la valeur passée en paramètre.
     *
     * Si un même article apparait plusieurs fois, il n'apparaitra qu'une seule 
     * fois dans l'équation finale.
     * 
     * Si la valeur passée en paramètre est vide (null ou chaine vide) un 
     * tableau vide est retourné.
     * 
     * @param null|string|array $value
     * @return array
     */
    protected function getTokens($value)
    {
        $value=(array) $value;
        foreach ($value as &$item)
            $item='[' . implode(' ', Utils::tokenize($item)) . ']';
        return array_flip($value);
    }
}
