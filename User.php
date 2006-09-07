<?php
// modif faite sur la copie locale de bdspserver 
 
/**
 * Gestion des droits et des utilisateurs
 * 
 * Un droit est compos� de deux parties : - une partie r�le telle que 'Admin',
 * 'Edit', etc. - une partie 'objet' � laquelle s'applique le r�le (Bdsp,
 * Webs...)
 * 
 * Chacune des deux parties doit commencer par une majuscule et ne doit contenir
 * ensuite que des minuscules. Les lettres accentu�es et autres signes sont
 * interdits.
 * 
 * Le nom du droit est obtenu en concat�nant les deux parties : AdminBdsp
 * (administrateur de l'objet bdsp), EditWebs (Editeur de l'objet webs)
 * 
 * Chacune des deux parties est optionnelle : l'absence de r�le signifie
 * 'tous les r�les', l'absence d'objet signifie 'tous les objets'. Ainsi,
 * 'Admin' tout seul signifie 'administrateur de tout' et 'Bdsp' tout seul
 * signifie 'tous les droits sur l'objet bdsp'.
 * 
 * L'ensemble des noms de r�les et l'ensemble des noms d'objets doivent
 * �tre disjoints (on ne peut pas avoir le m�me mot pour d�signer un r�le
 * et un objet). Par exemple il est interdit d'avoir � la fois un r�le
 * appell� "auteur" (quelqu'un qui aurait le droit d'�crire quelque chose)
 * et un objet "auteur" (par exemple une base de donn�es r�f�ren�ant tous
 * les auteurs) car cela conduirait � la possibilit� d'avoir un droit nomm�
 * AuteurAuteur.
 * 
 * Lorsqu'on requiert un droit (hasAccess, checkAccess...) les droits requis
 * peuvent �tre indiqu�s de diff�rentes fa�on :
 * 
 * - il peut s'agir d'un droit simple : hasAccess('admin')
 * 
 * - il peut s'agit de plusieurs droits possibles, s�par�s par une
 * virgule : hasAccess('admin, producteur'). Dans ce cas, l'utilisateur
 * obtiendra l'acc�s s'il dispose de l'un des droits indiqu�s
 * 
 * - il peut s'agir d'une combinaison de droits, s�par�s par un signe plus :
 * hasAccess('producteur+gestionnaire')
 * 
 * Il existe un pseudo-droit nomm� 'default' dont dispose automatiquement tous
 * les utilisateurs (i.e. hasAccess('default') retourne toujours true). Default
 * est notamment utilis� dans les fichiers de config.
 */
class User
{
    public static $user=null;
    
    
    /**
     * Teste si l'utilisateur est connect� (authentifi�)
     * 
     * @return boolean true si l'utilisateur est connect�, false s'il
     * s'agit d'un visiteur anonyme
     */
    public static function isConnected()
    {
        return self::$user->isConnected();
    }

    /**
     * V�rifie que l'utilisateur est connect� et l'envoie sur la page de
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
     * Teste si l'utilisateur dispose des droits indiqu�s.
     * 
     * @param string $level le ou les droit(s) � tester
     * @return boolean true si l'utilisateur dispose du droit requis,
     * false sinon
     */
    public static function hasAccess($rights)
    {
        return self::$user->hasAccess($rights);
    }

    /**
     * V�rifie que l'utilisateur dispose des droits indiqu�s et g�n�re une
     * erreur 'access denied' sinon.
     * 
     * @param string $level le droit � tester
     */
    public static function checkAccess($rights)
    {
        self::$user->checkAccess($rights);
    }
    
    /**
     * G�n�re une erreur 'access denied'
     */
    public static function accessDenied()
    {
        self::$user->accessDenied();
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
