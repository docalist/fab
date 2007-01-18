<?php

/*
 * Classe de tests unitaires du syst�me de templates bas�e sur PHPUnit 3
 * 
 * S'assurer du bon fonctionnement du syst�me de templates par rapport au plus grand nombre
 * de cas d'utilisations possible : bonnes syntaxes, mauvaises qui g�n�rent des exceptions, etc.
 */


class TemplateTest extends AutoTestCase
{
    
    // la source de donn�es qu'on passe au template : toujours la m�me...
    private $tplDataSrc=array
    (
        // formats de variables support�s dans les templates
        'empty'=>'',
        'vAccentu�e'=>'Valeur 1',
        'camelCase'=>'Valeur 4',
        'var_with_underscore'=>'Valeur 5',
        'vValNoSpaces'=>'Valeur18',
        'arrayVal'=>array('El�ment 1', 'El�ment 2', 'El�ment 3'),
        'arrayArray'=>array(array('a', 'b', 'c'), array(0, 1, 2, 3, 4), array('lalalala'), array()),
        
        // formats de variables consid�r�s comme non valides
        '_underscore'=>'Valeur 2',
        '1Digit'=>'Valeur 3',
        '*star'=>'Valeur 6',
        '<lesserthan'=>'Valeur 7',
        '$startDollar'=>'Valeur 8',
        '^notFound'=>'Valeur 9',
        '!exclamation'=>'Valeur 10',
        '?question'=>'Valeur 11',
        '.dot'=>'Valeur 12',
        ';semicolon'=>'Valeur 13',
        ':colon'=>'Valeur 14',
        '>greatherthan'=>'Valeur 15',
        'greatherthan>'=>'Valeur 16',
        'vWith:Colon'=>'Valeur 17'
        
    );    
    
    
    function setUp()
    {
        Cache::addCache(dirname(__FILE__), dirname(__FILE__) . '/data/cache');
        Config::set('templates.removehtmlcomments',true);
    }
    
    function tearDown()
    {
    }
    
    
    
    /*
     * A partir du nom du fichier de test, r�cup�re ces donn�es et pr�pare l'environnement de tests avant
     * d'appeler les fonctions qui vont les ex�cuter
     * 
     * Appel� par les fonctions de type testNomDuTest
     * 
     * @param $testFile string repr�sentant le nom du fichier de tests relativement au r�pertoire dirname(__FILE__).'/data/'
     */ 
    function performTest($testFile)
    {
        // 'data/template.htm' est r�serv� pour le fichier temporaire n�cessaire � Template::run
        if ($testFile == 'template.htm' || $testFile == 'template.html')
            throw new Exception('Aucun fichier de test ne peut s\'appeler "template.htm"'); 
            
        if (($tests = file_get_contents(dirname(__FILE__) . '\\data\\' . $testFile)) === false)
            throw new Exception('Fichier non trouv� : ' . dirname(__FILE__) . '\\data\\' . $testFile);
        
        
        //TODO: fonction qui teste si fichier de test est bien formatt�
        
        $tests = explode('====', $tests);   // chaque test est s�par� du suivant par '===='
        
        // TODO : virer
        echo 'NOMBRE DE TESTS : ' . count($tests) . '<br />';
        
        foreach($tests as $test)
        {
            // chaque test est au format suivant :
            // --TEST--Cha�ne repr�sentant le nom du test
            // --FILE--Cha�ne repr�sentant contenu du template
            // --EXPECT--Cha�ne repr�sentant le r�sultat attendu apr�s instanciation du template
            //
            // Il faut donc r�cup�rer ses cha�nes et en supprimer le tag de d�but,
            // � savoir ('--TEST--', '--FILE--' et '--EXPECT--')
            
            list($title, $text) = explode('--FILE--', $test);
            list(,$title) = explode('--TEST--', $title);
            list($template, $expected) = explode('--EXPECT--', $text);
            
            // TODO: enlever ligne ci-dessous
            echo 'TITLE = ' . $title . '<br />TEMPLATE = ' . $template . '<br />';


            // HACK: comme on ne peut pas ex�cuter directement un template, on passe par un fichier temp
            $path=dirname(__FILE__) . '/data/template.htm';
            file_put_contents($path, $template);
            
            // Ex�cute le template et bufferise le r�sultat
            ob_start();
            Template::Run($path, $this->tplDataSrc);
            $result=ob_get_clean();     // sortie effectu�e par le template
            
            // V�rifie que le r�sultat attendu obtenu et le r�sultat obtenu sont identiques
            $this->assertNoDiff($expected, $result, $title);
        }
    }
    
    
    
    
    
    /*
     * Similaire � performTest mis � part que les tests en question doivent g�n�rer des exceptions
     * 
     * Appel� par les fonctions de type testNomDuTest_Exceptions
     * 
     * @param $testFile string repr�sentant le nom du fichier de tests relativement au r�pertoire dirname(__FILE__).'/data/'
     */ 
    function performTest_Exceptions($testFile)
    {
        // 'data/template.htm' est r�serv� pour le fichier temporaire n�cessaire � Template::run
        if ($testFile == 'template.htm' || $testFile == 'template.html')
            throw new Exception('Aucun fichier de test ne peut s\'appeler "template.htm"'); 
            
        if (($tests = file_get_contents(dirname(__FILE__) . '\\data\\' . $testFile)) === false)
            throw new Exception('Fichier non trouv� : ' . dirname(__FILE__) . '\\data\\' . $testFile);
        
        //TODO: fonction qui teste si fichier de test est bien formatt�

        
        $tests = explode('====', $tests);   // chaque test est s�par� du suivant par '===='
        
        // TODO : virer
        echo 'NOMBRE DE TESTS : ' . count($tests) . '<br />';
        
        foreach($tests as $test)
        {
            // chaque test est au format suivant :
            // --TEST--Cha�ne repr�sentant le nom du test
            // --FILE--Cha�ne repr�sentant contenu du template
            //
            // Il faut donc r�cup�rer ses cha�nes et en supprimer le tag de d�but,
            // � savoir ('--TEST--' et '--FILE--')
            
            list($title, $template) = explode('--FILE--', $test);
            list(, $title) = explode('--TEST--', $title);

            // TODO: enlever ligne ci-dessous
            echo 'TITLE = ' . $title . '<br />TEMPLATE = ' . $template . '<br />';

            // HACK: comme on ne peut pas ex�cuter directement un template, on passe par un fichier temp
            $path=dirname(__FILE__) . '/data/template.htm';
            file_put_contents($path, $template);
            
            
            try
            {
                // Ex�cute le template et bufferise le r�sultat
                ob_start();
                Template::Run($path, $this->tplDataSrc);
                $result=ob_get_clean();     // sortie effectu�e par le template
                
            }
            catch (Exception $e)
            {
                // TODO: enlever la ligne suivante une fois que Daniel l'aura ajout� � son compilateur de templates
                
                ob_end_clean();                   // Ferme la bufferisation ouverte par le compilateur de templates
                continue;
            }
            
            $this->fail();
        }
    }
    
    
    
    
    
    
    /*
     * Test des zones de donn�es simples qui ne g�n�rent pas d'exception
     */
    function testDataZone()
    {
        $this->performTest('dataZone.htm');
    }
    
    
    /*
     * Test des zones de donn�es simples qui doivent g�n�rer une exception
     */
    function testDataZone_Exceptions()
    {
        $this->performTest_Exceptions('dataZone_Exceptions.htm');
    }
    
    
    /*
     * Test des blocs et des tags optionnels :
     */
    function testOptional()
    {
        $this->performTest('optional.htm');
    }
    
    
    /*
     * Test des blocs et des tags optionnels devant g�n�rer une exception
     */
     function testOptional_Exceptions()
     {
         $this->performTest_Exceptions('optional_Exceptions.htm');
     }
     
     
     /*
      * Test des conditionnelles
      */
      function testConditions()
      {
          $this->performTest('conditions.htm');
      }
      
      
      /*
       * Test des conditionnelles devant g�n�rer des exceptions
       */
       function testConditions_Exceptions()
       {
           $this->performTest_Exceptions('conditions_Exceptions.htm');
       }
       
       
       /*
        * Test des boucles
        */
       function testLoops()
       {
           $this->performTest('loops.htm');
       }
       
       
       /*
        * Test des boucles devant g�n�rer des exceptions
        */
        function testLoops_Exceptions()
        {
            $this->performTest_Exceptions('loops_Exceptions.htm');
        }
        
        
        /*
         * Test des "templates internes"
         */
         function testInternalTpl()
         {
             $this->performTest('InternalTpl.htm');
         }
         
         
         /*
          * Test des templates internes qui doivent g�n�rer des exceptions
          */
          function testInternalTpl_Exceptions()
          {
              $this->performTest_Exceptions('InternalTpl_Exceptions.htm');
          }
}
?>