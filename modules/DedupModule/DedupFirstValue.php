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

class DedupFirstValue extends DedupValues
{
    public function getEquation($value)
    {
        $t=$this->getTokens($value);
        if (count($t)===0) return '';
        return key($t);
    }
    
    public function compare($a, $b)
    {
        $first=$this->getEquation($a);
        $tb=$this->getTokens($b);

        return isset($tb[$first]) ? 100 : 0;
    }
}
