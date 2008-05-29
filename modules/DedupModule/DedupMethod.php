<?php
/**
 * @package     fab
 * @subpackage  modules
 * @author      Daniel M�nard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id$
 */

/**
 * Classe de base de toutes les m�thodes de d�doublonnage 
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