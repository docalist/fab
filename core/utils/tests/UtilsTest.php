<?php

class UtilsTest extends AutoTestCase 
{

    function setUp()
    {
    }
    function tearDown()
    {
    }
    
//    function testCompare()
//    {
//    	$this->assertNoDiff
//        (
//'
//<a>
//    essai
//</a>
//',
//'
//<a>essais
//</a>
//
//'
//        );
//    }
    
    function testGetExtension()
    {
        $tests=array
        (
            // simple nom de fichier
            '' => '',
            'test' => '',
            'test.' => '.',
            'test.txt' => '.txt',
            'test.txt.bak' => '.bak',
            
            // répertoire simple à la linux + nom de fichier
            'temp/' => '',
            'temp/test' => '',
            'temp/test.' => '.',
            'temp/test.txt' => '.txt',
            'temp/test.txt.bak' => '.bak',

            // répertoire simple à la windows + nom de fichier
            'c:\\temp\\' => '',
            'c:\\temp\\test' => '',
            'c:\\temp\\test.' => '.',
            'c:\\temp\\test.txt' => '.txt',
            'c:\\temp\\test.txt.bak' => '.bak',
            
            // répertoire contenant une extension
            '.txt/user.documents/temp/' => '',
            '/user.documents/temp.files/' => '',
            '/user.documents/temp.files/test' => '',
            '/user.documents/temp.files/test.' => '.',
            '/user.documents/temp.files/test.txt' => '.txt',
            '/user.documents/temp.files/test.txt.bak' => '.bak',

            // chemin unc contenant une extension
            '\\\\bdspserver.bdsp.tm.fr\\d$\\temp\\' => '',
            '\\\\bdspserver.bdsp.tm.fr\\d$\\temp\\test' => '',
            '\\\\bdspserver.bdsp.tm.fr\\d$\\temp\\test.' => '.',
            '\\\\bdspserver.bdsp.tm.fr\\d$\\temp\\test.txt' => '.txt',
            '\\\\bdspserver.bdsp.tm.fr\\d$\\temp\\test.txt.bak' => '.bak',

        );
        foreach($tests as $path=>$result)
            $this->assertNoDiff(Utils::getExtension($path), $result, "Erreur pour getExtension('$path')");
    }
    
    function testDefaultExtension()
    {
        $tests=array
        (
            '' => '.ext',
            'test' => 'test.ext',
            'test.' => 'test.',
            'test.txt' => 'test.txt',
            'test.txt.bak' => 'test.txt.bak',
            
            // répertoire simple à la linux + nom de fichier
            'temp/' => 'temp/.ext',
            'temp/test' => 'temp/test.ext',
            'temp/test.' => 'temp/test.',
            'temp/test.txt' => 'temp/test.txt',
            'temp/test.txt.bak' => 'temp/test.txt.bak',
        );
        foreach($tests as $path=>$result)
        {
            $this->assertNoDiff(Utils::defaultExtension($path,'ext'), $result, "Erreur pour defaultExtension('$path', 'ext')");
            $this->assertNoDiff(Utils::defaultExtension($path,'.ext'), $result, "Erreur pour defaultExtension('$path', '.ext')");
        }
    }
    
    function testSetExtension()
    {
        $tests=array
        (
            // simple nom de fichier
            '' => '.ext',
            'test' => 'test.ext',
            'test.' => 'test.ext',
            'test.txt' => 'test.ext',
            'test.txt.bak' => 'test.txt.ext',
            
            // répertoire simple à la linux + nom de fichier
            'temp/' => 'temp/.ext',
            'temp/test' => 'temp/test.ext',
            'temp/test.' => 'temp/test.ext',
            'temp/test.txt' => 'temp/test.ext',
            'temp/test.txt.bak' => 'temp/test.txt.ext',

            // répertoire simple à la windows + nom de fichier
            'c:\\temp\\' => 'c:\\temp\\.ext',
            'c:\\temp\\test' => 'c:\\temp\\test.ext',
            'c:\\temp\\test.' => 'c:\\temp\\test.ext',
            'c:\\temp\\test.txt' => 'c:\\temp\\test.ext',
            'c:\\temp\\test.txt.bak' => 'c:\\temp\\test.txt.ext',
            
            // répertoire contenant une extension
            '/user.documents/temp.files/' => '/user.documents/temp.files/.ext',
            '/user.documents/temp.files/test' => '/user.documents/temp.files/test.ext',
            '/user.documents/temp.files/test.' => '/user.documents/temp.files/test.ext',
            '/user.documents/temp.files/test.txt' => '/user.documents/temp.files/test.ext',
            '/user.documents/temp.files/test.txt.bak' => '/user.documents/temp.files/test.txt.ext',
            

            // chemin unc contenant une extension
            '\\\\bdspserver.bdsp.tm.fr\\d$\\temp\\' => '\\\\bdspserver.bdsp.tm.fr\\d$\\temp\\.ext',
            '\\\\bdspserver.bdsp.tm.fr\\d$\\temp\\test' => '\\\\bdspserver.bdsp.tm.fr\\d$\\temp\\test.ext',
            '\\\\bdspserver.bdsp.tm.fr\\d$\\temp\\test.' => '\\\\bdspserver.bdsp.tm.fr\\d$\\temp\\test.ext',
            '\\\\bdspserver.bdsp.tm.fr\\d$\\temp\\test.txt' => '\\\\bdspserver.bdsp.tm.fr\\d$\\temp\\test.ext',
            '\\\\bdspserver.bdsp.tm.fr\\d$\\temp\\test.txt.bak' => '\\\\bdspserver.bdsp.tm.fr\\d$\\temp\\test.txt.ext'
        );
              
        foreach($tests as $path=>$result)
        {
            $this->assertNoDiff(Utils::setExtension($path,'ext'), $result, "Erreur pour setExtension('$path', 'ext')");
            $this->assertNoDiff(Utils::setExtension($path,'.ext'), $result, "Erreur pour setExtension('$path', '.ext')");
        }
    }
    
    function testMakeDirectory()
    {
        // crée un fichier temporaire pour avoir un path où on peut écrire
        $this->assertTrue(false !== $file=tempnam('kzbrvg!!!not existant dir so tempnam create the file in temp', ''), 'tempname a échoué');

        $this->assertFalse(Utils::makeDirectory($file), "makeDirectory('$file') a retourné true alors qu'il existe déjà un fichier portant ce nom");
        unlink($file);
        $this->assertTrue(Utils::makeDirectory($file) && is_dir($file), "makeDirectory('$file') n'a pas réussie a créer le répertoire demandé");
        $mod=fileperms($file) & 0777;
        $this->assertTrue($mod===0777, "le répertoire makeDirectory('$file') a les droits $mod au lieu de 777");

        $this->assertTrue(Utils::makeDirectory($file), "makeDirectory('$file') a retourné false alors que le répertoire demandé existe déjà");
        rmdir($file);

        $file2=$file.'/this/is/a/test';
        $this->assertTrue(Utils::makeDirectory($file2) && is_dir($file), "makeDirectory('$file2') n'a pas réussie a créer la hiérarchie de répertoires demandée");

        rmdir($file.'/this/is/a/test');
        rmdir($file.'/this/is/a');
        rmdir($file.'/this/is');
        rmdir($file.'/this');
        rmdir($file);
    }
    
    function testIsRelativePath_and_IsAbsolutePath()
    {
        $tests=array
        (
            '' => true,
            'a' => true,
            'test' => true,
            'test/toto' => true,
            'test\\toto' => true,
            '/' => false,
            '\\' => false,
            '/test' => false,
            '\\test' => false,
            '/temp/test' => false,
            'c:\\temp\\test' => false,
            '\\\\bdspserver\\d$\\temp\\test' => false,
        );
        foreach($tests as $path=>$result)
        {
            $r=Utils::isRelativePath($path);
            $this->assertTrue($r=== $result, "path=[$path], result=[$r], attendu=[$result]");

            $r=Utils::isAbsolutePath($path);
            $result=! $result;
            $this->assertTrue($r=== $result, "path=[$path], result=[$r], attendu=[$result]");
        }
    }
    
    function testMakePath()
    {
        $tests=array
        (
            '' => array('','','',''),
            'temp/test1.txt' => array('temp','test1.txt'),
            'c:/temp/dm/test2.txt' => array('c:\\temp','dm','test2.txt'),
            'c:/temp/dm/test3.txt' => array('c:\\temp\\','\\dm','\\test3.txt'),
            '/temp/dm/test4.txt' => array('/temp/','/dm/','test4.txt'),
        );
        foreach($tests as $result=>$args)
        {
            $result=strtr($result, '/', DIRECTORY_SEPARATOR);
            $this->assertNoDiff
            (
                $result, 
                call_user_func_array(array('Utils','makePath'), $args),
                "Erreur pour makePath('".implode("', '", $args)."')"
            );
        }
    }
    
    function testCleanPath()
    {
        $tests=array
        (
            '' => '',
            'a' => 'a',
            '/a/b/./c/' => '/a/b/c/',
            '/a/b/.htaccess' => '/a/b/.htaccess',
            '/./././c/' => '/c/',
            '/a/b/../c/' => '/a/c/',
            '/../b/' => '/../b/',
            '/a/../b/../c/' => '/c/',
            '/a/../../etc/' => '/../etc/',
            '/a/b/c/../../../etc/' => '/etc/',
            '..//./../dir4//./dir5/dir6/..//dir7/' => '../../dir4/dir5/dir7/',
            '../../../etc/shadow'=>'../../../etc/shadow',
            'a/b/./c' => 'a/b/c',
            'a/../b/../c' => 'c',
            'a/../b/../c/..' => '',
            'a/../b/../c/../..' => '..',
            'c:\\temp\\toto'=>'c:/temp/toto',
            'c:\\temp\\..\\toto'=>'c:/toto',
            'c:\\temp\\..\\toto\\'=>'c:/toto/',
            'c:\\temp\\..\\toto\\..\\..\\'=>'c:/../',
            
        );
        foreach($tests as $path=>$result)
        {
            $result=strtr($result, '/', DIRECTORY_SEPARATOR);
            $r=Utils::cleanPath($path);
            $this->assertTrue($r=== $result, "path=[$path], result=[$r], attendu=[$result]");
        }
    }
    
    function testIsCleanPath()
    {
        $tests=array
        (
            '' => true,
            'a' => true,
            '/a/b/./c/' => false,
            '/a/b/.htaccess' => true,
            '/./././c/' => false,
            '/a/b/../c/' => false,
            '/../b/' => false,
            '..' => false,
            'c:\\temp\\toto'=>true,
            'c:\\temp\\..\\toto'=>false,
            'c:\\temp\\..\\toto\\'=>false,
            
        );
        foreach($tests as $path=>$result)
        {
            $result=strtr($result, '/', DIRECTORY_SEPARATOR);
            $r=Utils::isCleanPath($path);
            $this->assertTrue($r== $result, "path=[$path], result=[$r], attendu=[$result]");
        }
    }
    
    function testSearchFile_and_searchFileNoCase()
    {
        $file=basename(__FILE__);
        $dir=dirname(__FILE__);
        
        // tests qui retournent tous __FILE__
        $tests=array
        (
            array($file, $dir),
            array($file, '/', '/tmp', $dir),
            array($file, $dir, '/tmp', '/'),
            array($file, '/', '/tmp', "$dir/../tests/"),
        );
        
        foreach($tests as $args)
        {
            $this->assertEquals
            (
                __FILE__, 
                call_user_func_array(array('Utils','searchFile'), $args),
                "Erreur pour searchFile('".implode("', '", $args)."')"
            );
            $this->assertEquals
            (
                __FILE__, 
                call_user_func_array(array('Utils','searchFileNoCase'), $args),
                "Erreur pour searchFileNoCase('".implode("', '", $args)."')"
            );
            
            $args[0]=strtoupper($args[0]);
            
            $this->assertEquals
            (
                __FILE__, 
                call_user_func_array(array('Utils','searchFileNoCase'), $args),
                "Erreur pour searchFileNoCase('".implode("', '", $args)."')"
            );
        }

        // tests qui doivent retourner false (fichier non trouvé)
        $tests=array
        (
            array($file),
            array($file, "$dir/.."),
            array($file, realpath("$dir/..")),
            array($file, "$dir/..", "$dir/../..", '/'),
            array('does.not.exists', '/', $dir),
        );
        
        foreach($tests as $args)
        {
            $this->assertFalse
            (
                call_user_func_array(array('Utils','searchFile'), $args),
                "Erreur pour searchFile('".implode("', '", $args)."')"
            );

            $this->assertFalse
            (
                call_user_func_array(array('Utils','searchFileNoCase'), $args),
                "Erreur pour searchFileNoCase('".implode("', '", $args)."')"
            );

            $args[0]=strtoupper($args[0]);

            $this->assertFalse
            (
                call_user_func_array(array('Utils','searchFileNoCase'), $args),
                "Erreur pour searchFileNoCase('".implode("', '", $args)."')"
            );
        }
    }
    
    function testLastDay()
    {
        for ($year=2000; $year<2005; $year++)
        {
            foreach (array(1,3,5,7,8,10,12) as $month)
               $this->assertEquals(31, Utils::lastDay($month,$year), "Nombre de jours incorrecte pour le mois numéro $month : ");
            foreach (array(4,6,9,11) as $month)
               $this->assertEquals(30, Utils::lastDay($month,$year), "Nombre de jours incorrecte pour le mois numéro $month : ");
        }
        foreach (array(2000=>29,2001=>28,2002=>28,2003=>28,2004=>29,2005=>28) as $year=>$nb)
           $this->assertEquals($nb, Utils::lastDay(2,$year), "Nombre de jours incorrecte pour le mois numéro $month : ");
           
    }
    public function testfileDateRange()
    {
        $this->runTestFile(dirname(__FILE__).'/dateRange.testfile',array($this,'dateRangeCallback'));
    }

    public function dateRangeCallback($src)
    {
        list($from,$to)=explode(',', trim($src));
        return implode("\n",Utils::dateRange($from,$to));
    }
    
}
?>