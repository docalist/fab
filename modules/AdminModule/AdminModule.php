<?php

/**
 * @package     fab
 * @subpackage  AdminModules
 * @author      Daniel M�nard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id$
 */

/**
 * Classe abstraite repr�sentant un module d'administration
 * 
 * @package     fab
 * @subpackage  AdminModules
 */
abstract class AdminModule extends Module
{
    /**
     * Retourne le titre � du module d'administration
     * 
     * @return string
     */
    public function getTitle()
    {
        $title=Config::get('title');
        if ($title) return $title;
        return get_class($this);
    }
    
    /**
     * Retourne le menu du module d'administration
     * 
     * @return string
     */
    public function getDescription()
    {
        return Config::get('description');
    }
    
    /**
     * Retourne l'url de l'icone � afficher pour ce module d'administration
     * 
     * @return string
     */
    public function getIcon()
    {
        return Config::get('icon');
    }
}
?>