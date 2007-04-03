<?php

/*
 * Classe de tests unitaires du système de templates basée sur PHPUnit 3
 * 
 * S'assurer du bon fonctionnement du système de templates par rapport au plus grand nombre
 * de cas d'utilisations possible : bonnes syntaxes, mauvaises qui génèrent des exceptions, etc.
 */

require_once(dirname(__FILE__).'/../TemplateCompiler.php');
require_once(dirname(__FILE__).'/../TemplateCode.php');

class TemplateTest extends AutoTestCase
{
        
    function setUp()
    {
        Cache::addCache(dirname(__FILE__), dirname(__FILE__) . '/data/cache');
        Config::set('templates.removehtmlcomments',true);
    }
    
    function tearDown()
    {
    }
    
    public function testfileTemplatesMatch()
    {
        $this->runTestFile(dirname(__FILE__).'/MatchTemplates.testfile',array($this,'templatesMatchCallback'));
    }

    public function templatesMatchCallback($template)
    {
        $xml=new domDocument();
        
        TemplateCompiler::addCodePosition($template);
        $xml->loadXML($template);
        TemplateCompiler::compileMatches($xml);
    
        TemplateCompiler::removeCodePosition($xml);
        TemplateCompiler::removeCodePosition($template);
        $result=$xml->saveXml();
        if (substr($result,0,5)==='<?xml')
            $result=substr($result, strpos($result, '?>')+3);

        $result=rtrim($result, "\n\r");
        $result=preg_replace('~<template[^>]*/>~', '', $result);
        $result=preg_replace('~<template[^>]*>(.*?)</template>~s', '\1', $result);
        return $result;    	
    }
    
    public function testfileExpressionParser()
    {
        //$this->runTestFile(dirname(__FILE__).'/test.testfile',array($this,'expressionParserCallback')); return;

        $this->runTestFile(dirname(__FILE__).'/Expressions.base.testfile',array($this,'expressionParserCallback'));
        $this->runTestFile(dirname(__FILE__).'/Expressions.arrays.testfile',array($this,'expressionParserCallback'));
        $this->runTestFile(dirname(__FILE__).'/Expressions.colliers.testfile',array($this,'expressionParserCallback'));
        $this->runTestFile(dirname(__FILE__).'/Expressions.functions.testfile',array($this,'expressionParserCallback'));
        $this->runTestFile(dirname(__FILE__).'/Expressions.forbidden.operators.testfile',array($this,'expressionParserCallback'));
        $this->runTestFile(dirname(__FILE__).'/Expressions.forbidden.functions.testfile',array($this,'expressionParserCallback'));
        $this->runTestFile(dirname(__FILE__).'/Expressions.exclamation.testfile',array($this,'expressionParserCallback'));
        $this->runTestFile(dirname(__FILE__).'/Expressions.testfile',array($this,'expressionParserCallback'));
    }

    public function expressionParserCallback($expression)
    {
        
        TemplateCode::parseExpression($expression);
        return $expression;
    }
    
    /*
     * tests pour le compilateur de templates
     */
    public function testfileTemplateCompiler()
    {
        config::set('cache.enabled', false);
        $this->runTestFile(dirname(__FILE__).'/Template.iftag.testfile', array($this, 'templateCompilerCallback'));
        $this->runTestFile(dirname(__FILE__).'/Template.opt.testfile', array($this, 'templateCompilerCallback'));
        $this->runTestFile(dirname(__FILE__).'/Template.switch.testfile', array($this, 'templateCompilerCallback'));
        $this->runTestFile(dirname(__FILE__).'/Template.loop.testfile', array($this, 'templateCompilerCallback'));
    }
    
    public function templateCompilerCallback($template)
    {
        if ($template === '') return '';
        // la source de données qu'on passe aux templates
        $data = array
        (
            'varFalse'=>false,
            'varAut'=>'Spécialiste en santé publique', 
            'varTitorigA'=>'Titre original de niveau \'analytique\'',
            'varTitorigM'=>'Titre original de niveau "monographique"',
            'varNull'=>null,
            'varZero'=>0,
            'varEmptyString'=>'',
            'varA'=>'A',
            'varTrois'=>3,
            'arrayCinq'=>array(0, 1, 2, 3, 4, 5),
            'assocArray'=>array('key1'=>'valeur 1', 'key2'=>'valeur 2'),
            'emptyArray'=>array()
                        
        );

        $tmp=dirname(__FILE__) . '/tempfile.txt';
        file_put_contents($tmp, $template);
        ob_start();
        Template::run
        (
            $tmp,
            $data
        );
        $result=ob_get_clean();
        unlink($tmp);
        return $result;
    }
    
    /*
     * A partir du nom du fichier de test, récupère ces données et prépare l'environnement de tests avant
     * d'appeler les fonctions qui vont les exécuter
     * 
     * Appelé par les fonctions de type testNomDuTest
     * 
     * @param $testFile string représentant le nom du fichier de tests relativement au répertoire dirname(__FILE__).'/data/'
     */ 
//    function performTest($testFile)
//    {
//        // 'data/template.htm' est réservé pour le fichier temporaire nécessaire à Template::run
//        if ($testFile == 'template.htm' || $testFile == 'template.html')
//            throw new Exception('Aucun fichier de test ne peut s\'appeler "template.htm"'); 
//            
//        if (($tests = file_get_contents($path=dirname(__FILE__) . '/data/' . $testFile)) === false)
//            throw new Exception('Fichier non trouvé : ' . $path);
//        
//        
//        //TODO: fonction qui teste si fichier de test est bien formatté
//        
//        $tests = explode('====', $tests);   // chaque test est séparé du suivant par '===='
//        
//        // TODO : virer
//        echo 'NOMBRE DE TESTS : ' . count($tests) . '<br />';
//        
//        foreach($tests as $test)
//        {
//            // chaque test est au format suivant :
//            // --TEST--Chaîne représentant le nom du test
//            // --FILE--Chaîne représentant contenu du template
//            // --EXPECT--Chaîne représentant le résultat attendu après instanciation du template
//            //
//            // Il faut donc récupérer ses chaînes et en supprimer le tag de début,
//            // à savoir ('--TEST--', '--FILE--' et '--EXPECT--')
//            
//            list($title, $text) = explode('--FILE--', $test);
//            list(,$title) = explode('--TEST--', $title);
//            list($template, $expected) = explode('--EXPECT--', $text);
//            
//            // TODO: enlever ligne ci-dessous
//            echo 'TITLE = ' . $title . '<br />TEMPLATE = ' . $template . '<br />';
//
//
//            // HACK: comme on ne peut pas exécuter directement un template, on passe par un fichier temp
//            $path=dirname(__FILE__) . '/data/template.htm';
//            file_put_contents($path, $template);
//            
//            // Exécute le template et bufferise le résultat
//            ob_start();
//            Template::Run($path, $this->tplDataSrc);
//            $result=ob_get_clean();     // sortie effectuée par le template
//            
//            // Vérifie que le résultat attendu obtenu et le résultat obtenu sont identiques
//            $this->assertNoDiff($expected, $result, $title);
//        }
//    }
    
    
    
    
    
    /*
     * Similaire à performTest mis à part que les tests en question doivent générer des exceptions
     * 
     * Appelé par les fonctions de type testNomDuTest_Exceptions
     * 
     * @param $testFile string représentant le nom du fichier de tests relativement au répertoire dirname(__FILE__).'/data/'
     */ 
//    function performTest_Exceptions($testFile)
//    {
//        // 'data/template.htm' est réservé pour le fichier temporaire nécessaire à Template::run
//        if ($testFile == 'template.htm' || $testFile == 'template.html')
//            throw new Exception('Aucun fichier de test ne peut s\'appeler "template.htm"'); 
//            
//        if (($tests = file_get_contents(dirname(__FILE__) . '\\data\\' . $testFile)) === false)
//            throw new Exception('Fichier non trouvé : ' . dirname(__FILE__) . '\\data\\' . $testFile);
//        
//        //TODO: fonction qui teste si fichier de test est bien formatté
//
//        
//        $tests = explode('====', $tests);   // chaque test est séparé du suivant par '===='
//        
//        // TODO : virer
//        echo 'NOMBRE DE TESTS : ' . count($tests) . '<br />';
//        
//        foreach($tests as $test)
//        {
//            // chaque test est au format suivant :
//            // --TEST--Chaîne représentant le nom du test
//            // --FILE--Chaîne représentant contenu du template
//            //
//            // Il faut donc récupérer ses chaînes et en supprimer le tag de début,
//            // à savoir ('--TEST--' et '--FILE--')
//            
//            list($title, $template) = explode('--FILE--', $test);
//            list(, $title) = explode('--TEST--', $title);
//
//            // TODO: enlever ligne ci-dessous
//            echo 'TITLE = ' . $title . '<br />TEMPLATE = ' . $template . '<br />';
//
//            // HACK: comme on ne peut pas exécuter directement un template, on passe par un fichier temp
//            $path=dirname(__FILE__) . '/data/template.htm';
//            file_put_contents($path, $template);
//            
//            
//            try
//            {
//                // Exécute le template et bufferise le résultat
//                ob_start();
//                Template::Run($path, $this->tplDataSrc);
//                $result=ob_get_clean();     // sortie effectuée par le template
//                
//            }
//            catch (Exception $e)
//            {
//                // TODO: enlever la ligne suivante une fois que Daniel l'aura ajouté à son compilateur de templates
//                
//                ob_end_clean();                   // Ferme la bufferisation ouverte par le compilateur de templates
//                continue;
//            }
//            
//            $this->fail();
//        }
//    }
    
    
    
    
    
    
    /*
     * Test des zones de données simples qui ne génèrent pas d'exception
     */
//    function testDataZone()
//    {
//        $this->performTest('dataZone.htm');
//    }
    
    
    /*
     * Test des zones de données simples qui doivent générer une exception
     */
//    function nutestDataZone_Exceptions()
//    {
//        $this->performTest_Exceptions('dataZone_Exceptions.htm');
//    }
    
    
    /*
     * Test des blocs et des tags optionnels :
     */
//    function nutestOptional()
//    {
//        $this->performTest('optional.htm');
//    }
    
    
    /*
     * Test des blocs et des tags optionnels devant générer une exception
     */
//     function nutestOptional_Exceptions()
//     {
//         $this->performTest_Exceptions('optional_Exceptions.htm');
//     }
     
     
     /*
      * Test des conditionnelles
      */
//      function nutestConditions()
//      {
//          $this->performTest('conditions.htm');
//      }
      
      
      /*
       * Test des conditionnelles devant générer des exceptions
       */
//       function nutestConditions_Exceptions()
//       {
//           $this->performTest_Exceptions('conditions_Exceptions.htm');
//       }
       
       
       /*
        * Test des boucles
        */
//       function nutestLoops()
//       {
//           $this->performTest('loops.htm');
//       }
       
       
       /*
        * Test des boucles devant générer des exceptions
        */
//        function nutestLoops_Exceptions()
//        {
//            $this->performTest_Exceptions('loops_Exceptions.htm');
//        }
        
        
        /*
         * Test des "templates internes"
         */
//         function nutestInternalTpl()
//         {
//             $this->performTest('InternalTpl.htm');
//         }
         
         
         /*
          * Test des templates internes qui doivent générer des exceptions
          */
//          function nutestInternalTpl_Exceptions()
//          {
//              $this->performTest_Exceptions('InternalTpl_Exceptions.htm');
//          }
}
?>