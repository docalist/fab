<?php

class UtilsTest extends AutoTestCase 
{

    function setUp()
    {
    }
    function tearDown()
    {
    }
    
    function testCompare()
    {
    	$this->assertNoDiff
        (
'
<a>
    essai
</a>
',
'
<a>essais
</a>

',
false
        );
    }
    
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
        {
            $liveResult=Utils::getExtension($path);
            $this->assertEquals($liveResult, $result, "path=[$path], result=[$liveResult], attendu=[$result]");
        }
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
        foreach($tests as $path=>$ext)
        {
            $h=$path;
            $result=Utils::defaultExtension($h, 'ext');
            $this->assertTrue($result===$ext && $h===$result, "path initial=[$path], path obtenu=[$h], result=[$result], attendu=[$ext]");
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
            $h1=$path;
            $r1=Utils::setExtension($h1, '.ext');

            $h2=$path;
            $r2=Utils::setExtension($h2, 'ext');

            $this->assertTrue
            (
                    $h1===$result        // on a le résultat attendu (ext avec .)
                &&  $r1===$h1            // la fonction retourne le chemin modifié
                &&  $h2===$result        // on a le résultat attendu (ext sans .)
                &&  $r2===$h2            // la fonction retourne le chemin modifié
                &&  $r1===$r2            // même résultat qu'on indique ou non un point
                ,
                "path initial=[$path], path obtenu=[$h1][$h2], result=[$r1][$r2], attendu=[$result]");
        }
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
            'temp/test.txt' => array('temp','test.txt'),
            'c:\\temp\\dm\\test.txt' => array('c:\\temp','dm','test.txt'),
            'c:\\temp\\dm\\test.txt' => array('c:\\temp\\','\\dm','\\test.txt'),
            '/temp/dm/test.txt' => array('/temp/','/dm/','test.txt'),
        );
        foreach($tests as $result=>$args)
        {
            $result=strtr($result, '/', DIRECTORY_SEPARATOR);
            $r=call_user_func_array(array('Utils','makePath'), $args);
            $this->assertTrue($r=== $result, "args=['" . join($args, '\',\'') . "'], result=[$r], attendu=[$result]");
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
}
?>