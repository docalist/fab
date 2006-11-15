<?php
/**
 * @package     fab
 * @subpackage  user
 * @author 		Daniel Ménard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id$
 */

/**
 * Gestion des droits et des utilisateurs
 * 
 * @package     fab
 * @subpackage  user
 */
class User
{
    /**
     * @var User L'utilisateur en cours
     */
    public static $user=null;
    
    
    /**
     * Teste si l'utilisateur est connecté (authentifié)
     * 
     * @return boolean true si l'utilisateur est connecté, false s'il
     * s'agit d'un visiteur anonyme
     */
    public static function isConnected()
    {
        return self::$user->isConnected();
    }

    /**
     * Vérifie que l'utilisateur est connecté et l'envoie sur la page de
     * connexion si ce n'est pas le cas.
     */
    public static function checkConnected()
    {
        self::$user->checkConnected();
    }

    /**
     * Force la connexion d'un utilisateur en l'envoyant vers le formulaire de
     * connexion
     */
    public static function logon()
    {
        self::$user->logon();
    }

    /**
     * Teste si l'utilisateur dispose des droits indiqués.
     * 
     * @param string $level le ou les droit(s) à tester
     * @return boolean true si l'utilisateur dispose du droit requis,
     * false sinon
     */
    public static function hasAccess($rights)
    {
        return self::$user->hasAccess($rights);
    }

    /**
     * Vérifie que l'utilisateur dispose des droits indiqués et génère une
     * erreur 'access denied' sinon.
     * 
     * @param string $level le droit à tester
     */
    public static function checkAccess($rights)
    {
        self::$user->checkAccess($rights);
    }
    
    /**
     * Génère une erreur 'access denied'
     */
    public static function accessDenied()
    {
        self::$user->accessDenied();
    }
    
    public static function get($propertyName)
    {
        try
        {
        	return @self::$user->$propertyName;
        }
        catch (Exception $e)
        {
            return '';
        }
    }
    
    // dans $_SERVER, on pourrait extraire :
    // User::browser   : [HTTP_USER_AGENT] => Mozilla/5.0 (Windows; U; Windows NT 5.1; fr; rv:1.8.0.3) Gecko/20060426 Firefox/1.5.0.3
    // User::mimeTypes : [HTTP_ACCEPT] => text/xml,application/xml,application/xhtml+xml,text/html;q=0.9,text/plain;q=0.8,image/png,*/*;q=0.5
    // User::language  : [HTTP_ACCEPT_LANGUAGE] => fr-fr,fr;q=0.8,en-us;q=0.5,en;q=0.3
    // User::encoding  : [HTTP_ACCEPT_ENCODING] => gzip,deflate
    // User::charset   : [HTTP_ACCEPT_CHARSET] => ISO-8859-1,utf-8;q=0.7,*;q=0.7
    // User::ip        : [REMOTE_ADDR] => 192.168.13.100
    
}
?>
