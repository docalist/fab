<?php

/*
 * Classe de tests unitaires des générateurs (contrôles utilisateurs standards basés sur les templates match)
 * 
 * S'assurer du bon fonctionnement des templates match pour :
 * - les cas d'utilisation normale dans un premier temps
 * - TODO: LES CAS DE "MAUVAISES" UTILISATION ENSUITE (Exceptions, etc.)
 */

require_once(dirname(__FILE__).'/../../../TemplateCompiler.php');
require_once(dirname(__FILE__).'/../../../TemplateCode.php');

class GeneratorsTest extends AutoTestCase
{
    
//    private $showCompiledSource=false;
    
//    // Les données qui sont passées aux templates lors de leur instanciation
//    private $data = array
//    (                     
//    );
        
        
    function setUp()
    {
        Cache::addCache(dirname(__FILE__), dirname(__FILE__) . '/cache');
        Config::set('templates.removehtmlcomments',true);
    }
    
    function tearDown()
    {
    }
    
    
    // Fonction de test des template match et son callback
    public function testfileTemplatesMatch()
    {
        // TODO: décommenter ces lignes
        $this->runTestFile(dirname(__FILE__).'/Generators.textbox.testfile', array($this,'generatorCallback'));
        $this->runTestFile(dirname(__FILE__).'/Generators.buttons.testfile', array($this,'generatorCallback'));
        $this->runTestFile(dirname(__FILE__).'/Generators.check.testfile', array($this,'generatorCallback'));
        $this->runTestFile(dirname(__FILE__).'/Generators.radio.testfile', array($this,'generatorCallback'));
        $this->runTestFile(dirname(__FILE__).'/Generators.select.testfile', array($this,'generatorCallback'));
        $this->runTestFile(dirname(__FILE__).'/Generators.misc.testfile', array($this,'generatorCallback'));
    }

    public function generatorCallback($template)
    {
        if ($template === '') return '';

        $tmp=dirname(__FILE__) . '/tempfile.txt';
        file_put_contents($tmp, $template);
        ob_start();
        
        Template::run($tmp);
        
        $result=ob_get_clean();
        unlink($tmp);
        
        return $result;
    }
}

?>