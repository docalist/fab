<?php
/**
 * @package     fab
 * @subpackage  modules
 * @author      Daniel Ménard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id$
 */

/**
 * Méthodes de dédoublonnage basée sur la comparaison des années.
 * 
 * Seules les années écrites sous la forme d'un nombre de quatre chiffres 
 * commençant par 1 ou 2 sont prises en comptes (i.e. 1998 ou 2007 mais pas 98) 
 * 
 * @package     fab
 * @subpackage  modules
 */

class DedupYears extends DedupTokens
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
        // Si c'est un tableau, on le linéarise
        if (is_array($value)) $value=implode('¤', $value);
        
        if (preg_match_all('~\b(?:1|2)\d{3}\b~', $value, $matches))
            return array_flip($matches[0]);
        else
            return array();
    }
}
