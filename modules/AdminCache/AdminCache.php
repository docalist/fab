<?php

/**
 * @package     fab
 * @subpackage  Admin
 * @author      Daniel M�nard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id$
 */

/**
 * Module de gestion du cache.
 * 
 * @package     fab
 * @subpackage  Admin
 */
class AdminCache extends AdminFiles
{
    /**
     * Retourne le path du cache de l'application.
     * 
     * Le path retourn� est construit � partir des �lements suivants :
     * - le r�pertoire du cache de l'application ({@link Runtime::$root})
     * - le r�pertoire �ventuel indiqu� dans le param�tre <code>directory</code>
     *   indiqu� en query string
     * 
     * Une exception est g�n�r�e si le r�pertoire obtenu n'existe pas ou ne
     * d�signe pas un r�pertoire.
     * 
     * Le chemin retourn� contient toujours un slash final.
     *
     * @throws Exception si le path n'existe pas.
     * @return string le path obtenu.
     */
    public function getDirectory()
    {
        $path=Utils::makePath
        (
            realpath(Cache::getPath(Runtime::$root).'../..'), // On ne travaille que dans le cache de cette application 
            $this->request->get('directory'),   // R�pertoire �ventuel pass� en param�tre
            DIRECTORY_SEPARATOR                 // Garantit qu'on a un slash final
        );
        
        return $path;
    }    
    
    /**
     * Retourne le path d'une icone pour le fichier ou le dossier pass� en
     * param�tre.
     *
     * @param string $path
     * @return string
     */
    public function getFileIcon($path)
    {
        if (is_dir($path)) 
            return parent::getFileIcon($path);
        return parent::getFileIcon($path.'.php');
    }
    
    public function getEditorSyntax($file)
    {
        return 'php';
    }
}

?>