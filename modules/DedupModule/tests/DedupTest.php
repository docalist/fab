<?php
require_once(dirname(__FILE__).'/../DedupModule.php');

class DedupTest extends AutoTestCase 
{
    private $db;
    private $method;
    
    public function __construct()
    {
        $this->db=new XapianDatabaseDriver;
    }
    
    function testDedupTokens()
    {
        $tests=array
        (

        );
        foreach($tests as $path=>$result)
            $this->assertNoDiff(Utils::getExtension($path), $result, "Erreur pour getExtension('$path')");
    }
    

    public function testfileDedupTokens()
    {
        $this->method=new DedupTokens($this->db);
        $this->runTestFile(dirname(__FILE__).'/DedupTokens.getEquation.testfile',array($this,'getEquationCallback'));
        $this->runTestFile(dirname(__FILE__).'/DedupTokens.compare.testfile',array($this,'compareCallback'));
    }

    public function testfileDedupValues()
    {
        $this->method=new DedupValues($this->db);
        $this->runTestFile(dirname(__FILE__).'/DedupValues.getEquation.testfile',array($this,'getEquationCallback'));
        $this->runTestFile(dirname(__FILE__).'/DedupValues.compare.testfile',array($this,'compareCallback'));
    }

    public function getEquationCallback($src)
    {
        if (strpos($src, '¤') !== false) $src=explode('¤', $src);
        
        return $this->method->getEquation($src);
    }
    
    public function compareCallback($src)
    {
        list($a, $b) = explode("\n", $src, 2);
        
        if (strpos($a, '¤') !== false) $a=explode('¤', $a);
        if (strpos($b, '¤') !== false) $b=explode('¤', $b);
        
        return round($this->method->compare($a, $b),2);
    }
}
?>