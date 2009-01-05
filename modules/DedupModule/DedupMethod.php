<?php
/**
 * @package     fab
 * @subpackage  modules
 * @author      Daniel Ménard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id$
 */

/**
 * Classe de base de toutes les méthodes de dédoublonnage 
 * 
 * @package     fab
 * @subpackage  modules
 */

abstract class DedupMethod
{
    public function getEquation($value)
    {
        return $value;
    }
    
    public function compare($a, $b)
    {
        return ($a===$b) ? 100 : 0;
    }
}