<?php
//require_once ('../Cache.php');

class CacheTest extends AutoTestCase
{

    function setUp()
    {
    }
    function tearDown()
    {
    }
    function testCache()
    {
        // on utilise des noms de fichiers bidons, relatifs au répertoire du fichier CacheTest.php
        // les fichiers vont être mis en cache dans le cache de l'application, sous répertoire fab/core/cache/tests/testdata
        
        $basedir=dirname(__FILE__).'/testdata';
        $cacheDir=Cache::getPath($basedir);

        $dir1="$basedir/group1";
        $dir2="$basedir/group1/subgroup1";

        $path="$dir1/file1.cache";
        $path2="$dir2/file2.cache";

        $data="123456AbCd\tfdsfds";

        // Essaie d'accéder à un fichier qui n'existe pas
        $this->assertFalse(Cache::has($path), "fichier [$path] n'existe pas déjà");
        $this->assertFalse(Cache::get($path), "si on essaie de le lire, cache::get() retourne false");
        $this->assertTrue(Cache::lastModified($path)===0, "et sa date de dernière modification vaut zéro");
        
        // Ajoute file1.cache
        $this->assertTrue(Cache::set($path, $data),"Ajout du fichier [$path]");
        $this->assertTrue(Cache::has($path), "fichier [$path] existe");
        $this->assertTrue(Cache::get($path)===$data, "les données sont ok");
        $date=Cache::lastModified($path);
        $this->assertTrue($date<=time() && $date>=time()-1, "et sa date de dernière modification semble correcte");
        
        // Supprime le fichier
        Cache::remove($path);
        $this->assertFalse(Cache::has($path), "fichier [$path] n'existe plus");
        
        // Comme le fichier était le seul fichier ajouté, le fait de le supprimer supprime les répertoires
        $this->assertFalse(is_dir($dir2), "rép [$dir2] n'existe pas");
        $this->assertFalse(is_dir($dir1), "rép [$dir1] n'existe pas");
        clearstatcache();
//        $this->assertFalse(is_dir("$cacheDir"), "rép [$cacheDir] (sscacheDir) n'existe plus");
        
        // maintenant ajoute deux fichiers
        $this->assertTrue(Cache::set($path, $data), "Ajout du fichier [$path]");
        $this->assertTrue(Cache::set($path2, $data), "Ajout du fichier [$path2]");
        $this->assertTrue(Cache::has($path), "fichier [$path] existe");
        $this->assertTrue(Cache::get($path)===$data, "les données sont ok");
        $this->assertTrue(Cache::has($path2), "fichier [$path2] existe");
        $this->assertTrue(Cache::get($path2)===$data, "les données sont ok");

        // Supprime le premier des deux fichiers
        Cache::remove($path);
        clearstatcache();
        $this->assertFalse(Cache::has($path), "fichier [$path] n'existe plus");
        $this->assertTrue(Cache::has($path2), "fichier [$path2] existe encore");
        
        // Le répertoire du premier fichier a dû être supprimé mais pas celui du second
        $this->assertFalse(is_dir($dir1), "rép [$dir1] n'existe plus");
        clearstatcache();
//        $this->assertTrue(is_dir($dir2), "rép [".Cache::getPath($dir2)."] existe ssencore");

        // Supprime le second fichier
        Cache::remove($path2);
        $this->assertFalse(Cache::has($path2), "fichier [$path2] n'existe plus");
        $this->assertFalse(is_dir("$cacheDir$dir1"), "rép [$cacheDir$dir1] n'existe plus");
        
        // Ajoute à nouveau les deux fichiers
        $this->assertTrue(Cache::set($path, $data), "Ajout du fichier [$path]");
        $this->assertTrue(Cache::set($path2, $data), "Ajout du fichier [$path2]");

        touch(Cache::getPath($path), time()-1000);
        $this->assertTrue(Cache::has($path));
        $this->assertFalse(Cache::has($path, time()-500), "$path n'est pas à jour");
//        Cache::clear('', time()-500);
//        $this->assertFalse(Cache::has($path), "fichier [$path] n'existe plus");
//        $this->assertTrue(Cache::has($path2), "fichier [$path] existe encore");
        
//        $this->assertTrue(Cache::set($path, $data), "Ajout du fichier [$path]");
//        $this->assertTrue(Cache::set($path2, $data),"Ajout du fichier [$path2]");
//        Cache::clear();        
//        $this->assertFalse(Cache::has($path), "fichier [$path] n'existe plus");
//        $this->assertFalse(Cache::has($path2), "fichier [$path] n'existe plus");

        Cache::remove($path);
        Cache::remove($path2);

    }
}
?>