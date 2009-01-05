<?php
class ConfigTest extends AutoTestCase
{
    public function testfileConfig()
    {
        $this->runTestFile(dirname(__FILE__).'/config.testfile',array($this,'configCallback'));
    }

    public function configCallback($xml)
    {
        return var_export(Config::loadXml('<?xml version="1.0" encoding="ISO-8859-1" standalone="yes"?>'."\n".$xml),true);
    }
    
    public function testfileMerge()
    {
        
        $this->runTestFile(dirname(__FILE__).'/merge.testfile',array($this,'mergeCallback'));
    }

    public function mergeCallback($xml)
    {
        $t=Config::loadXml('<?xml version="1.0" encoding="ISO-8859-1" standalone="yes"?>'."\n".$xml);
        $section='ConfigTestData';
        Config::set($section, $t['base']);
        Config::addArray($t['merge'], $section);
        return var_export(Config::get($section),true);
    }
}
?>
