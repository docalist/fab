<?php
/**
 * @package     fab
 * @subpackage  cache
 * @author 		Daniel Ménard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id$
 */



/**
 * Le gestionnaire de cache
 * 
 * @package     fab
 * @subpackage  cache
 */

final class Cache
{
    /**
     * @var array $caches Liste des caches gérés par le gestionnaire de cache.
     * 
     * Chaque item du tableau est un tableau contenant trois éléments :
     * <li>le répertoire racine des fichiers qui seront mis en cache
     * <li>le répertoire à utiliser pour la version en cache des fichiers
     */
    private static $caches=array();
    
    /**
     * constructeur
     * 
     * Le constructeur est privé : il n'est pas possible d'instancier la
     * classe. Utilisez directement les méthodes statiques proposées.
     */
    private function __construct()
    {
    }

    public static function addCache($root, $cacheDir)
    {
        self::$caches[]=array
        (
            rtrim($root,'/\\') . DIRECTORY_SEPARATOR,
            rtrim($cacheDir,'/\\') . DIRECTORY_SEPARATOR
        );
    }
    
    /**
     * Retourne le path de la version en cache du fichier dont le nom est 
     * passé en paramètre
     * 
     * @param string $path le path du fichier qui sera lu ou écrit dans le cache
     * @return string le path de la version en cache de ce fichier.
     */
    public static function getPath($path, &$cacheNumber=null)
    {
        foreach(self::$caches as $cacheNumber=>$cache)
        {
        	$root=& $cache[0];
            if (strncasecmp($root, $path, strlen($root))==0)
                return $cache[1] . substr($path, strlen($root)) . ".php";
        }
        throw new Exception("Le fichier '$path' ne peut pas figurer dans le cache");
    }

    /**
     * Indique si un fichier figure ou non dans le cache et s'il est à jour.
     * 
     * @param string $path le path du fichier à tester
     * @param int date/heure minimale du fichier présent dans le cache pour
     * qu'il soit considéré comme à jour.
     * @return bool true si le fichier est dans le cache, false sinon
     */
    public static function has($path, $minTime=0)
    {
        if (! file_exists($path=self::getPath($path))) return false;
        return ($minTime==0) || (filemtime($path) > $minTime);
    }

    /**
     * Retourne la date de dernière modification d'un fichier en cache
     * 
     * @param string $path le path du fichier dont on veut la date
     * @return int la date/heure de dernière modification du fichier ou zéro
     * si le fichier n'est pas présent dans le cache
     */
    public static function lastModified($path)
    {
        return (file_exists($path=self::getPath($path)) ? filemtime($path) : 0);
    }

    /**
     * Stocke des données en cache
     * 
     * Le path indiqué peut contenir des noms de répertoires, ceux-ci seront 
     * créés s'il y a lieu.
     *  
     * @param string $path le path du fichier à écrire
     * @param string $data les données à écrire
     */
    public static function set($path, $data)
    {
        $path=self::getPath($path);
        Utils::makeDirectory(dirname($path));
        file_put_contents($path, $data, LOCK_EX);
    }

    /**
     * Charge des données depuis le cache
     * 
     * @param string $path le path du fichier à lire
     * @return string les données lues ou une chaine vide si le fichier n'existe
     * pas ou ne peut pas être lu.
     */
    public static function get($path)
    {
        return file_get_contents(self::getPath($path));
    }

    /**
     * Supprime du cache le fichier indiqué
     * Aucune erreur n'est générée si le fichier n'était pas en cache.
     * 
     * La fonction supprime également tous les répertoires de path, dès lors 
     * que ceux-ci sont vides.
     * 
     * @param string $path le path du fichier à supprimer du cache
     */
    public static function remove($path)
    {
        $index=0; // évite 'var not initialized' sous eclipse 
        $path=self::getPath($path, $index);
        @ unlink($path);
        $minLen = strlen(self::$caches[$index][1]);
        for (;;)
        {
            $path = dirname($path);
            if (strlen($path) < $minLen)
                break;
            if (!@ rmdir($path))
                break;
        }
    }

    /**
     * Vide le cache
     * 
     * clear permet de nettoyer le cache en supprimant soit tous les
     * fichiers, soit les fichiers antérieurs à une date de donnée.
     * 
     * On peut soit vider le cache en entier, soit spécifier un sous-répertoire
     * à partir duquel le nettoyage commence.
     * 
     * La fonction tente également de supprimer tous les répertoires vides.
     * 
     * Lorsque l'intégralité du cache est vidée, le répertoire cacheDirectory
     * est lui-même supprimé.
     * 
     * @param  string  $path répertoire à partir duquel il faut commencer le
     * nettoyage, ou une chaine vide pour nettoyer l'intégralité du cache.
     * @param  $minTime  date/heure minimale des fichiers à supprimer. Tous 
     * les fichiers dont la date de dernière modification est inférieure ou 
     * égale à l'heure spécifiée seront supprimés. Indiquer zéro (c'est la 
     * valeur par défaut) pour supprimer tous les fichiers.
     * @return boolean true si le cache (ou tout au moins la partie spécifiée 
     * par $path) a été entièrement vidé. Il est normal que la fonction retourne 
     * false lorsque vous mentionnez le paramètre $minTime : cela signifie
     * simplement que certains fichiers, plus récents que l'heure indiquée
     * n'ont pas été supprimés.
    */
//    public static function clear($path = '', $minTime = 0)
//    {
//        // Crée un path absolu et garantit qu'on vide uniquement des répertoires du cache
//        if (! $cd = self :: $cacheDirectory) 
//            die("Le cache n'a pas été initialisé. Utilisez Cache:setCacheDir");
//        
//        if (substr($path, 0, strlen($cd)) != $cd)
//            $path = Utils :: makePath($cd, $path);
//
//        if (!($dir = opendir($path)))
//            die("Impossible d'ouvrir le répertoire $dir");
//
//        $result = true;
//        while ($file = readdir($dir))
//        {
//            if (($file != '.') && ($file != '..'))
//            {
//                $file2 = Utils :: makePath($path, $file);
//                if (is_file($file2))
//                {
//                    if ($minTime == 0 || (filemtime($file2) <= $minTime))
//                        $result = $result && @ unlink($file2);
//                }
//                elseif (is_dir($file2))
//                    $result = $result and (self :: clear($file2, $minTime));
//            }
//        }
//        @rmdir($path);
//        closedir($dir);
//        return $result;
//    }

}
?>