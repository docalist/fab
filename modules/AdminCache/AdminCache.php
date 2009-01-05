<?php
/**
 * @package     fab
 * @subpackage  Admin
 * @author      Daniel Ménard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id$
 */

/**
 * Module d'administration du cache.
 * 
 * Ce module d'administration permet de visualiser le 
 * {@link /AdminCache contenu du cache} et de supprimer tout ou partie des 
 * fichiers qu'il contient.
 * 
 * AdminCache hérite de {@link AdminFiles}. Aucune méthode n'est
 * introduite par ce module, seules quelques méthodes héritées et quelques
 * templates sont modifiés.
 * 
 * Consultez la {@link Cache documentation de la classe Cache} pour plus 
 * d'informations sur les fichiers que fab met en cache.
 * 
 * @package     fab
 * @subpackage  Admin
 */
class AdminCache extends AdminFiles
{
    /**
     * Retourne le path complet du répertoire de travail de AdminCache.
     * 
     * Le path retourné est construit à partir des élements suivants :
     * - le répertoire racine contenant le cache de l'application,
     * - le répertoire éventuel indiqué dans le paramètre <code>directory</code>
     *   indiqué en query string.
     * 
     * Une exception est générée si le répertoire obtenu n'existe pas ou ne
     * désigne pas un répertoire.
     * 
     * Le chemin retourné contient toujours un slash final.
     *
     * @throws Exception est générée si :
     * - le répertoire obtenu contient des séquences de la forme 
     *   <code>/../</code> ;
     * - le répertoire obtenu n'existe pas ou désigne autre chose qu'un 
     *   répertoire.
     *  
     * @return string le path obtenu.
     */
    public function getDirectory()
    {
        // Détermine le path du cache
        $path=Utils::makePath
        (
            realpath(Cache::getPath(Runtime::$root).'../..'), // On ne travaille que dans le cache de cette application 
            $this->request->get('directory'),   // Répertoire éventuel passé en paramètre
            DIRECTORY_SEPARATOR                 // Garantit qu'on a un slash final
        );
        
        // Vérifie qu'on n'a pas de '..'
        $this->checkPath($path, 'Répertoire de travail');
        
        // Vérifie que c'est un répertoire et que celui-ci existe
        if (!is_dir($path))
            throw new Exception("Le répertoire indiqué n'existe pas.");
            
        // Retourne le résultat
        return $path;
    }    

    
    /**
     * Détermine l'icone à afficher pour représenter le type du fichier ou du
     * dossier passé en paramètre.
     *  
     * Cette méthode surcharge {@link AdminFiles::getFileIcon()} car les 
     * fichiers présents dans le cache de l'application sont tous des fichiers
     * php, même s'ils ont une extension différente.
     *
     * @param string $path
     * @return string une url de la forme 
     * <code>/FawWeb/modules/AdminFiles/images/filetypes/icon.png</code>
     */
    public function getFileIcon($path)
    {
        if (is_dir($path)) 
            return parent::getFileIcon($path);
        return parent::getFileIcon($path.'.php');
    }
    
    /**
     * Détermine le type de coloration syntaxique à appliquer aux fichier 
     * du cache.
     *
     * {@inheritdoc}
     * 
     * Comme les fichiers présents dans le cache de l'application sont tous des 
     * fichiers (même s'ils ont une extension différente), cette version de
     * <code>getEditorSyntax</code> retourne toujours la syntaxe 
     * <code>php</code>.
     *   
     * @param string $file le nom ou le path d'un fichier.
     * 
     * @return string la chaine <code>'php'</code>
     */
    public function getEditorSyntax($file)
    {
        return 'php';
    }
}

?>