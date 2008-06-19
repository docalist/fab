<?php
/**
 * @package     fab
 * @subpackage  cache
 * @author 		Daniel Ménard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id$
 */

/**
 * Gestionnaire de cache.
 * 
 * Fab intègre un système de cache qui permet de stocker sous forme de fichier
 * la version compilée des {@link Config fichiers de configuration} et des 
 * {@link Template templates} utilisés dans l'application.
 * 
 * Ce système améliore grandement les performances de fab : charger un fichier 
 * xml, le valider et extraire les valeurs indiquées est une opération qui prend
 * du temps. Les performances obtenues seraient médiocres si ce traitement 
 * devait être fait à chaque fois, pour chaque requête et pour chacun des 
 * fichiers de configuration utilisés.
 * 
 * Il en va de même pour les templates qui peuvent contenir toute une variété
 * de tags et de variables différents, des templates match, des slots, etc.
 *    
 * Avec le gestionnaire de cache de fab, lorsqu'un fichier est utilisé pour la 
 * première fois, il est chargé, analysé et compilé pour produire un fichier 
 * contenant du code source PHP optimisé contenant la même information que celle 
 * figurant dans le fichier d'origine.
 * 
 * Lors des utilisations suivantes, l'application regarde si la version compilée
 * du fichier demandé figure ou non dans le cache. Si c'est le cas, et que 
 * celui-ci est à jour, le fichier PHP est directement utilisé avec un simple
 * {@link http://php.net/include include}.
 * 
 * Remarque : 
 * Le fonctionnement de ce système de cache a été conçu pour être compatible 
 * avec des accélérateurs de code PHP tels que {@link http://php.net/apc APC},
 * ce qui permet d'améliorer encore plus les performances.
 * Dans ce cas, dès la première utilisation, le 
 * {@link http://fr.wikipedia.org/wiki/Bytecode byte code} de la version compilée 
 * sera stocké en mémoire partagée par l'accélérateur de code et sera 
 * immédiatement utilisable par les requêtes suivantes, sans qu'il soit 
 * nécessaire de charger le fichier php.
 * 
 * Par défaut, le système de cache vérifie systématiquement que le fichier 
 * original n'a pas été modifié depuis la dernière compilation.
 * 
 * Configuration :
 * - La configuration du système de cache se trouve dans le fichier 
 *   {@link /AdminFiles/Edit?directory=config&file=config.php config.php}
 *   du répertoire {@link /AdminConfig /config}.
 * 
 * Emplacement et hiérarchie du cache : 
 * - Par défaut, fab détermine automatiquement le path du répertoire dans lequel
 *   seront stockés les fichiers compilés mais il est possible d'indiquer un 
 *   répertoire dans la clé <code>path</code> du fichier de config.
 * 
 * - Au sein de ce répertoire, fab va créer un répertoire pour chacun des 
 *   environnements existants pour l'application (normal, debug, test, etc.)
 * 
 * - Chacun de ces répertoires contient ensuite un répertoire pour les fichiers
 *   de fab (répertoire <code>fab</code>) et un répertoire pour les fichiers 
 *   de l'application (répertoire <code>app</code>).
 * 
 * - On retrouve ensuite la même hiérarchie de fichiers que dans l'application
 *   et dans fab (le cache utilise le chemin relatif du fichier à compiler pour
 *   déterminer le path exact du fichier mis en cache).
 * 
 * Remarque :
 * fab dispose d'un {@link /AdminCache module d'administration} nommé 
 * {@link AdminCache} qui permet de visualiser et d'effacer tout ou partie des 
 * fichiers présents dans le cache.
 * 
 * @package     fab
 * @subpackage  cache
 */

final class Cache
{
    /**
     * La liste des caches gérés par le gestionnaire de cache.
     * 
     * Chaque item du tableau est un tableau contenant deux éléments :
     * - le répertoire racine des fichiers qui seront mis en cache ;
     * - le répertoire à utiliser pour la version en cache des fichiers.
     * 
     * @var array 
     */
    private static $caches=array();
    
    
    /**
     * Constructeur.
     * 
     * Le constructeur est privé : il n'est pas possible d'instancier la
     * classe. Utilisez directement les méthodes statiques proposées.
     */
    private function __construct()
    {
    }

    
    /**
     * Crée un nouveau cache.
     * 
     * @param string $root la racine des fichiers qui pourront être stockés
     * dans ce cache. Seuls les fichiers dont le path commence par <code>$root</code>
     * pourront être stockés.
     * 
     * @param string $cacheDir le path du répertoire dans lequel les fichiers
     * de cache seront stockés.
     * 
     * @return bool true si le cache a été créé, false dans le cas contraire
     * (droits insuffisants pour créer le répertoire, chemin erroné...)
     */
    public static function addCache($root, $cacheDir)
    {
        $root=rtrim($root,'/\\') . DIRECTORY_SEPARATOR;
        $cacheDir=rtrim($cacheDir,'/\\') . DIRECTORY_SEPARATOR;
        if (! is_dir($cacheDir))
        {
            if (! Utils::makeDirectory($cacheDir)) return false;
        }
        else
        {
        	if (! is_writable($cacheDir)) return false;
        }
            
        self::$caches[]=array($root, $cacheDir);
        return true;
    }
    
    
    /**
     * Retourne le path de la version en cache du fichier dont le path est 
     * passé en paramètre.
     * 
     * @param string $path le path du fichier qui sera lu ou écrit dans le cache.
     * 
     * @param int $cacheNumber une variable optionnelle permettant de récupérer
     * le numéro interne du cache contenant le path indiqué.
     * 
     * @return string le path de la version en cache de ce fichier.
     * 
     * @throws Exception si le fichier indiqué ne peut pas figurer dans le
     * cache.
     */
    public static function getPath($path, &$cacheNumber=null)
    {
        foreach(self::$caches as $cacheNumber=>$cache)
        {
        	$root=& $cache[0];
            if (strncasecmp($root, $path, strlen($root))==0)
                return $cache[1] . substr($path, strlen($root))/* . ".php"*/;
        }
        throw new Exception("Le fichier '$path' ne peut pas figurer dans le cache");
    }

    
    /**
     * Indique si un fichier figure ou non dans le cache et s'il est à jour.
     * 
     * @param string $path le path du fichier à tester.
     * 
     * @param timestamp $minTime date/heure minimale du fichier présent dans le 
     * cache pour qu'il soit considéré comme à jour.
     * 
     * @return bool true si le fichier est dans le cache et est à jour, false 
     * sinon.
     */
    public static function has($path, $minTime=0)
    {
        if (! file_exists($path=self::getPath($path))) return false;
        return ($minTime==0) || (filemtime($path) > $minTime);
    }

    
    /**
     * Retourne la date de dernière modification d'un fichier présent dans le 
     * cache.
     * 
     * @param string $path le path du fichier dont on veut connaître la date de
     * dernière modification.
     * 
     * @return timestamp la date/heure de dernière modification du fichier ou 
     * zéro si le fichier n'est pas présent dans le cache.
     */
    public static function lastModified($path)
    {
        return (file_exists($path=self::getPath($path)) ? filemtime($path) : 0);
    }

    
    /**
     * Stocke la version compilée d'un fichier dans le cache.
     * 
     * Le path indiqué peut contenir des noms de répertoires, ceux-ci seront 
     * créés s'il y a lieu.
     *  
     * @param string $path le path du fichier à stocker.
     * 
     * @param string $data le contenu du fichier à stocker.
     * 
     * @return bool true si le fichier a été mis en cache, false sinon (erreur
     * d'écriture, droits insuffisants...)
     */
    public static function set($path, $data)
    {
        $path=self::getPath($path);
        if (! is_dir($dir=dirname($path))) 
            if (! Utils::makeDirectory($dir))
                return false;
         // Créée les fichiers avec tous les droits.
         // Raison : lors d'une mise à jour d'apache, le 'user' utilisé par le daemon apache peut changer (daemon, nobody, www-data...)
         // Si des fichiers sont créés dans le cache par l'utilisateur 'daemon', par exemple, et que par la suite, on passe à
         // 'www-data', on ne pourra pas écraser le fichier existant.
         // En faisant umask(0), tout le monde a les droits en lecture/écriture
        umask(0);
        return (false !== @file_put_contents($path, $data, LOCK_EX));
    }

    
    /**
     * Charge la version compilée d'un fichier à partir du cache.
     * 
     * @param string $path le path du fichier à charger.
     * 
     * @return string les données lues ou false si le fichier n'existe
     * pas ou ne peut pas être lu.
     */
    public static function get($path)
    {
        return @file_get_contents(self::getPath($path));
    }

    
    /**
     * Supprime un fichier du cache.
     * 
     * Aucune erreur n'est générée si le fichier indiqué ne figure pas dans 
     * le cache.
     * 
     * La fonction essaie également de supprimer tous les répertoires, dès lors 
     * que ceux-ci sont vides.
     * 
     * @param string $path le path du fichier à supprimer du cache.
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