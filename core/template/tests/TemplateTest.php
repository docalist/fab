<?php

/*
 * Classe de tests unitaires du syst�me de templates bas�e sur PHPUnit 3
 * 
 * S'assurer du bon fonctionnement du syst�me de templates par rapport au plus grand nombre
 * de cas d'utilisations possible : bonnes syntaxes, mauvaises qui g�n�rent des exceptions, etc.
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
        // la source de donn�es qu'on passe aux templates
        $data = array
        (
            'varFalse'=>false,
            'varAut'=>'Sp�cialiste en sant� publique', 
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
     * A partir du nom du fichier de test, r�cup�re ces donn�es et pr�pare l'environnement de tests avant
     * d'appeler les fonctions qui vont les ex�cuter
     * 
     * Appel� par les fonctions de type testNomDuTest
     * 
     * @param $testFile string repr�sentant le nom du fichier de tests relativement au r�pertoire dirname(__FILE__).'/data/'
     */ 
//    function performTest($testFile)
//    {
//        // 'data/template.htm' est r�serv� pour le fichier temporaire n�cessaire � Template::run
//        if ($testFile == 'template.htm' || $testFile == 'template.html')
//            throw new Exception('Aucun fichier de test ne peut s\'appeler "template.htm"'); 
//            
//        if (($tests = file_get_contents($path=dirname(__FILE__) . '/data/' . $testFile)) === false)
//            throw new Exception('Fichier non trouv� : ' . $path);
//        
//        
//        //TODO: fonction qui teste si fichier de test est bien formatt�
//        
//        $tests = explode('====', $tests);   // chaque test est s�par� du suivant par '===='
//        
//        // TODO : virer
//        echo 'NOMBRE DE TESTS : ' . count($tests) . '<br />';
//        
//        foreach($tests as $test)
//        {
//            // chaque test est au format suivant :
//            // --TEST--Cha�ne repr�sentant le nom du test
//            // --FILE--Cha�ne repr�sentant contenu du template
//            // --EXPECT--Cha�ne repr�sentant le r�sultat attendu apr�s instanciation du template
//            //
//            // Il faut donc r�cup�rer ses cha�nes et en supprimer le tag de d�but,
//            // � savoir ('--TEST--', '--FILE--' et '--EXPECT--')
//            
//            list($title, $text) = explode('--FILE--', $test);
//            list(,$title) = explode('--TEST--', $title);
//            list($template, $expected) = explode('--EXPECT--', $text);
//            
//            // TODO: enlever ligne ci-dessous
//            echo 'TITLE = ' . $title . '<br />TEMPLATE = ' . $template . '<br />';
//
//
//            // HACK: comme on ne peut pas ex�cuter directement un template, on passe par un fichier temp
//            $path=dirname(__FILE__) . '/data/template.htm';
//            file_put_contents($path, $template);
//            
//            // Ex�cute le template et bufferise le r�sultat
//            ob_start();
//            Template::Run($path, $this->tplDataSrc);
//            $result=ob_get_clean();     // sortie effectu�e par le template
//            
//            // V�rifie que le r�sultat attendu obtenu et le r�sultat obtenu sont identiques
//            $this->assertNoDiff($expected, $result, $title);
//        }
//    }
    
    
    
    
    
    /*
     * Similaire � performTest mis � part que les tests en question doivent g�n�rer des exceptions
     * 
     * Appel� par les fonctions de type testNomDuTest_Exceptions
     * 
     * @param $testFile string repr�sentant le nom du fichier de tests relativement au r�pertoire dirname(__FILE__).'/data/'
     */ 
//    function performTest_Exceptions($testFile)
//    {
//        // 'data/template.htm' est r�serv� pour le fichier temporaire n�cessaire � Template::run
//        if ($testFile == 'template.htm' || $testFile == 'template.html')
//            throw new Exception('Aucun fichier de test ne peut s\'appeler "template.htm"'); 
//            
//        if (($tests = file_get_contents(dirname(__FILE__) . '\\data\\' . $testFile)) === false)
//            throw new Exception('Fichier non trouv� : ' . dirname(__FILE__) . '\\data\\' . $testFile);
//        
//        //TODO: fonction qui teste si fichier de test est bien formatt�
//
//        
//        $tests = explode('====', $tests);   // chaque test est s�par� du suivant par '===='
//        
//        // TODO : virer
//        echo 'NOMBRE DE TESTS : ' . count($tests) . '<br />';
//        
//        foreach($tests as $test)
//        {
//            // chaque test est au format suivant :
//            // --TEST--Cha�ne repr�sentant le nom du test
//            // --FILE--Cha�ne repr�sentant contenu du template
//            //
//            // Il faut donc r�cup�rer ses cha�nes et en supprimer le tag de d�but,
//            // � savoir ('--TEST--' et '--FILE--')
//            
//            list($title, $template) = explode('--FILE--', $test);
//            list(, $title) = explode('--TEST--', $title);
//
//            // TODO: enlever ligne ci-dessous
//            echo 'TITLE = ' . $title . '<br />TEMPLATE = ' . $template . '<br />';
//
//            // HACK: comme on ne peut pas ex�cuter directement un template, on passe par un fichier temp
//            $path=dirname(__FILE__) . '/data/template.htm';
//            file_put_contents($path, $template);
//            
//            
//            try
//            {
//                // Ex�cute le template et bufferise le r�sultat
//                ob_start();
//                Template::Run($path, $this->tplDataSrc);
//                $result=ob_get_clean();     // sortie effectu�e par le template
//                
//            }
//            catch (Exception $e)
//            {
//                // TODO: enlever la ligne suivante une fois que Daniel l'aura ajout� � son compilateur de templates
//                
//                ob_end_clean();                   // Ferme la bufferisation ouverte par le compilateur de templates
//                continue;
//            }
//            
//            $this->fail();
//        }
//    }
    
    
    
    
    
    
    /*
     * Test des zones de donn�es simples qui ne g�n�rent pas d'exception
     */
//    function testDataZone()
//    {
//        $this->performTest('dataZone.htm');
//    }
    
    
    /*
     * Test des zones de donn�es simples qui doivent g�n�rer une exception
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
     * Test des blocs et des tags optionnels devant g�n�rer une exception
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
       * Test des conditionnelles devant g�n�rer des exceptions
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
        * Test des boucles devant g�n�rer des exceptions
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
          * Test des templates internes qui doivent g�n�rer des exceptions
          */
//          function nutestInternalTpl_Exceptions()
//          {
//              $this->performTest_Exceptions('InternalTpl_Exceptions.htm');
//          }
}
?>