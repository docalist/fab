<?php
require_once(dirname(__FILE__).'/../XapianDatabase2.php');
class QueryParserTest extends AutoTestCase
{
    private $db;
    
    public function __construct()
    {
        //$this->db=new XapianDatabaseDriver();
        $this->db=Database::open('testdm');
        $this->db->search();
    }
    
    public function testfileQueryParser()
    {
        $this->runTestFile(dirname(__FILE__).'/QueryParser.testfile',array($this,'queryParserCallback'));
    }

    public function queryParserCallback($equation)
    {
        $query=$this->db->parseQuery($equation);
        
        $h=$query->get_description();
        
        $h=utf8_decode($h);
        $h=substr($h, 14, -1);
        $h=preg_replace('~:\(pos=\d+?\)~', '', $h);
        if (strlen($h)>2 && $h[0]==='(' && $h[strlen($h)-1]===')')
            $h=substr($h, 1,-1);
        return $h;
    }

}
?>
