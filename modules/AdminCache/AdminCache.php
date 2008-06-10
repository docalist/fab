<?php

/**
 * @package     fab
 * @subpackage  Admin
 * @author      Daniel Ménard <Daniel.Menard@bdsp.tm.fr>
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
     * Le path retourné est construit à partir des élements suivants :
     * - le répertoire du cache de l'application ({@link Runtime::$root})
     * - le répertoire éventuel indiqué dans le paramètre <code>directory</code>
     *   indiqué en query string
     * 
     * Une exception est générée si le répertoire obtenu n'existe pas ou ne
     * désigne pas un répertoire.
     * 
     * Le chemin retourné contient toujours un slash final.
     *
     * @throws Exception si le path n'existe pas.
     * @return string le path obtenu.
     */
    public function getDirectory()
    {
        $path=Utils::makePath
        (
            realpath(Cache::getPath(Runtime::$root).'../..'), // On ne travaille que dans le cache de cette application 
            $this->request->get('directory'),   // Répertoire éventuel passé en paramètre
            DIRECTORY_SEPARATOR                 // Garantit qu'on a un slash final
        );
        
        return $path;
    }    
    
    /**
     * Retourne le path d'une icone pour le fichier ou le dossier passé en
     * paramètre.
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