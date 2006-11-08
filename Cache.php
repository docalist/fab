<?php
/**
 * @package     fab
 * @subpackage  cache
 * @author 		Daniel M�nard <Daniel.Menard@bdsp.tm.fr>
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
     * @var array $caches Liste des caches g�r�s par le gestionnaire de cache.
     * 
     * Chaque item du tableau est un tableau contenant trois �l�ments :
     * <li>le r�pertoire racine des fichiers qui seront mis en cache
     * <li>le r�pertoire � utiliser pour la version en cache des fichiers
     */
    private static $caches=array();
    
    /**
     * constructeur
     * 
     * Le constructeur est priv� : il n'est pas possible d'instancier la
     * classe. Utilisez directement les m�thodes statiques propos�es.
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
     * pass� en param�tre
     * 
     * @param string $path le path du fichier qui sera lu ou �crit dans le cache
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
     * Indique si un fichier figure ou non dans le cache et s'il est � jour.
     * 
     * @param string $path le path du fichier � tester
     * @param int date/heure minimale du fichier pr�sent dans le cache pour
     * qu'il soit consid�r� comme � jour.
     * @return bool true si le fichier est dans le cache, false sinon
     */
    public static function has($path, $minTime=0)
    {
        if (! file_exists($path=self::getPath($path))) return false;
        return ($minTime==0) || (filemtime($path) > $minTime);
    }

    /**
     * Retourne la date de derni�re modification d'un fichier en cache
     * 
     * @param string $path le path du fichier dont on veut la date
     * @return int la date/heure de derni�re modification du fichier ou z�ro
     * si le fichier n'est pas pr�sent dans le cache
     */
    public static function lastModified($path)
    {
        return (file_exists($path=self::getPath($path)) ? filemtime($path) : 0);
    }

    /**
     * Stocke des donn�es en cache
     * 
     * Le path indiqu� peut contenir des noms de r�pertoires, ceux-ci seront 
     * cr��s s'il y a lieu.
     *  
     * @param string $path le path du fichier � �crire
     * @param string $data les donn�es � �crire
     */
    public static function set($path, $data)
    {
        $path=self::getPath($path);
        Utils::makeDirectory(dirname($path));
        file_put_contents($path, $data, LOCK_EX);
    }

    /**
     * Charge des donn�es depuis le cache
     * 
     * @param string $path le path du fichier � lire
     * @return string les donn�es lues ou une chaine vide si le fichier n'existe
     * pas ou ne peut pas �tre lu.
     */
    public static function get($path)
    {
        return file_get_contents(self::getPath($path));
    }

    /**
     * Supprime du cache le fichier indiqu�
     * Aucune erreur n'est g�n�r�e si le fichier n'�tait pas en cache.
     * 
     * La fonction supprime �galement tous les r�pertoires de path, d�s lors 
     * que ceux-ci sont vides.
     * 
     * @param string $path le path du fichier � supprimer du cache
     */
    public static function remove($path)
    {
        $index=0; // �vite 'var not initialized' sous eclipse 
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
     * fichiers, soit les fichiers ant�rieurs � une date de donn�e.
     * 
     * On peut soit vider le cache en entier, soit sp�cifier un sous-r�pertoire
     * � partir duquel le nettoyage commence.
     * 
     * La fonction tente �galement de supprimer tous les r�pertoires vides.
     * 
     * Lorsque l'int�gralit� du cache est vid�e, le r�pertoire cacheDirectory
     * est lui-m�me supprim�.
     * 
     * @param  string  $path r�pertoire � partir duquel il faut commencer le
     * nettoyage, ou une chaine vide pour nettoyer l'int�gralit� du cache.
     * @param  $minTime  date/heure minimale des fichiers � supprimer. Tous 
     * les fichiers dont la date de derni�re modification est inf�rieure ou 
     * �gale � l'heure sp�cifi�e seront supprim�s. Indiquer z�ro (c'est la 
     * valeur par d�faut) pour supprimer tous les fichiers.
     * @return boolean true si le cache (ou tout au moins la partie sp�cifi�e 
     * par $path) a �t� enti�rement vid�. Il est normal que la fonction retourne 
     * false lorsque vous mentionnez le param�tre $minTime : cela signifie
     * simplement que certains fichiers, plus r�cents que l'heure indiqu�e
     * n'ont pas �t� supprim�s.
    */
//    public static function clear($path = '', $minTime = 0)
//    {
//        // Cr�e un path absolu et garantit qu'on vide uniquement des r�pertoires du cache
//        if (! $cd = self :: $cacheDirectory) 
//            die("Le cache n'a pas �t� initialis�. Utilisez Cache:setCacheDir");
//        
//        if (substr($path, 0, strlen($cd)) != $cd)
//            $path = Utils :: makePath($cd, $path);
//
//        if (!($dir = opendir($path)))
//            die("Impossible d'ouvrir le r�pertoire $dir");
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