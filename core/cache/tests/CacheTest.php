<?php
//require_once ('../Cache.php');

class CacheTest extends AutoTestCase
{

    function setUp()
    {
        Cache::addCache('group1', dirname(__FILE__).'/cache');
    }
    function tearDown()
    {
    }
    function testCache()
    {
        $cd=dirname(__FILE__).'/cache';
        
        $dir1="group1";
        $dir2="$dir1/subgroup1";

        $path="$dir2/file1.cache";
        $altPath="/$dir1/subgroup2/../subgroup1/file1.cache";

        $path2="$dir1/file2.cache";

        $data="123456AbCd\tfdsfds";
        
        $this->assertFalse(Cache::has($path), "fichier [$path] n'existe pas déjà");
        $this->assertTrue(Cache::get($path)==='', "si on le lit on obtient une chaine vide");
        $this->assertTrue(Cache::lastModified($path)===0, "et sa date de dernière modification vaut zéro");
        
        Cache::set($path, $data);
        $this->assertNoErrors("Ajout du fichier [$path]");
        $this->assertTrue(Cache::has($path), "fichier [$path] existe");
        $this->assertTrue(Cache::get($path)===$data, "les données sont ok");

        $date=Cache::lastModified($path);
        $this->assertTrue($date<=time() && $date>=time()-1, "et sa date de dernière modification semble correcte");
        
        $this->assertTrue(Cache::has($altPath), "fichier [$altPath] existe");
        $this->assertTrue(Cache::get($altPath)===$data, "les données sont ok");
        $this->assertTrue(is_dir("$cd$dir1"), "rép [$cd$dir1] existe");
        $this->assertTrue(is_dir("$cd$dir2"), "rép [$cd$dir2] existe");
        
        Cache::remove($path);
        $this->assertNoErrors("Suppression du fichier [$path]");
        $this->assertFalse(Cache::has($path), "fichier [$path] n'existe pas");
        $this->assertFalse(Cache::has($altPath), "fichier [$altPath] n'existe pas");
        $this->assertFalse(is_dir("$cd$dir2"), "rép [$cd$dir2] n'existe pas");
        $this->assertFalse(is_dir("$cd$dir1"), "rép [$cd$dir1] n'existe pas");
        $this->assertTrue(is_dir("$cd"), "rép [$cd] (cacheDir) existe toujours");
        
        Cache::set($path, $data);
        $this->assertNoErrors("Ajout du fichier [$path]");
        Cache::set($path2, $data);
        $this->assertNoErrors("Ajout du fichier [$path2]");
        $this->assertTrue(Cache::has($path), "fichier [$path] existe");
        $this->assertTrue(Cache::get($path)===$data, "les données sont ok");
        $this->assertTrue(Cache::has($path2), "fichier [$path2] existe");
        $this->assertTrue(Cache::get($path2)===$data, "les données sont ok");

        Cache::remove($path);
        $this->assertNoErrors("Suppression du fichier  [$path]");
        $this->assertFalse(Cache::has($path), "fichier [$path] n'existe plus");
        $this->assertTrue(Cache::has($path2), "fichier [$path2] existe encore");
        $this->assertFalse(is_dir("$cd$dir2"), "rép [$cd$dir2] n'existe plus");
        $this->assertTrue(is_dir("$cd$dir1"), "rép [$cd$dir1] existe encore");

        Cache::remove($path2);
        $this->assertNoErrors("Suppression du fichier [$path2]");
        $this->assertFalse(Cache::has($path2), "fichier [$path2] n'existe plus");
        $this->assertFalse(is_dir("$cd$dir1"), "rép [$cd$dir1] n'existe plus");
        
        Cache::set($path, $data);
        $this->assertNoErrors("Ajout du fichier [$path]");
        Cache::set($path2, $data);
        $this->assertNoErrors("Ajout du fichier [$path2]");

        touch(Cache::getPath($path), time()-1000);
        $this->assertTrue(Cache::has($path));
        $this->assertFalse(Cache::has($path, time()-500), "$path n'est pas à jour");
        Cache::clear('', time()-500);
        $this->assertFalse(Cache::has($path), "fichier [$path] n'existe plus");
        $this->assertTrue(Cache::has($path2), "fichier [$path] existe encore");
        
        Cache::set($path, $data);
        $this->assertNoErrors("Ajout du fichier [$path]");
        Cache::set($path2, $data);
        $this->assertNoErrors("Ajout du fichier [$path2]");
        Cache::clear();        
        $this->assertNoErrors("clear() [$path]");
        $this->assertFalse(Cache::has($path), "fichier [$path] n'existe plus");
        $this->assertFalse(Cache::has($path2), "fichier [$path] n'existe plus");
        $this->assertFalse(is_dir("$cd$dir1"), "rép [$cd$dir1] n'existe plus");
        $this->assertFalse(is_dir("$cd$dir2"), "rép [$cd$dir2] n'existe plus");
        $this->assertFalse(is_dir("$cd"), "rép [$cd] (cacheDir) n'existe plus");

    }
}
?>