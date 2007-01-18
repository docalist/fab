<?php

/*
 * Classe de tests unitaires du système de templates basée sur PHPUnit 3
 * 
 * S'assurer du bon fonctionnement du système de templates par rapport au plus grand nombre
 * de cas d'utilisations possible : bonnes syntaxes, mauvaises qui génèrent des exceptions, etc.
 */


class TemplateTest extends AutoTestCase
{
    
    // la source de données qu'on passe au template : toujours la même...
    private $tplDataSrc=array
    (
        // formats de variables supportés dans les templates
        'empty'=>'',
        'vAccentuée'=>'Valeur 1',
        'camelCase'=>'Valeur 4',
        'var_with_underscore'=>'Valeur 5',
        'vValNoSpaces'=>'Valeur18',
        'arrayVal'=>array('Elément 1', 'Elément 2', 'Elément 3'),
        'arrayArray'=>array(array('a', 'b', 'c'), array(0, 1, 2, 3, 4), array('lalalala'), array()),
        
        // formats de variables considérés comme non valides
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
     * A partir du nom du fichier de test, récupère ces données et prépare l'environnement de tests avant
     * d'appeler les fonctions qui vont les exécuter
     * 
     * Appelé par les fonctions de type testNomDuTest
     * 
     * @param $testFile string représentant le nom du fichier de tests relativement au répertoire dirname(__FILE__).'/data/'
     */ 
    function performTest($testFile)
    {
        // 'data/template.htm' est réservé pour le fichier temporaire nécessaire à Template::run
        if ($testFile == 'template.htm' || $testFile == 'template.html')
            throw new Exception('Aucun fichier de test ne peut s\'appeler "template.htm"'); 
            
        if (($tests = file_get_contents(dirname(__FILE__) . '\\data\\' . $testFile)) === false)
            throw new Exception('Fichier non trouvé : ' . dirname(__FILE__) . '\\data\\' . $testFile);
        
        
        //TODO: fonction qui teste si fichier de test est bien formatté
        
        $tests = explode('====', $tests);   // chaque test est séparé du suivant par '===='
        
        // TODO : virer
        echo 'NOMBRE DE TESTS : ' . count($tests) . '<br />';
        
        foreach($tests as $test)
        {
            // chaque test est au format suivant :
            // --TEST--Chaîne représentant le nom du test
            // --FILE--Chaîne représentant contenu du template
            // --EXPECT--Chaîne représentant le résultat attendu après instanciation du template
            //
            // Il faut donc récupérer ses chaînes et en supprimer le tag de début,
            // à savoir ('--TEST--', '--FILE--' et '--EXPECT--')
            
            list($title, $text) = explode('--FILE--', $test);
            list(,$title) = explode('--TEST--', $title);
            list($template, $expected) = explode('--EXPECT--', $text);
            
            // TODO: enlever ligne ci-dessous
            echo 'TITLE = ' . $title . '<br />TEMPLATE = ' . $template . '<br />';


            // HACK: comme on ne peut pas exécuter directement un template, on passe par un fichier temp
            $path=dirname(__FILE__) . '/data/template.htm';
            file_put_contents($path, $template);
            
            // Exécute le template et bufferise le résultat
            ob_start();
            Template::Run($path, $this->tplDataSrc);
            $result=ob_get_clean();     // sortie effectuée par le template
            
            // Vérifie que le résultat attendu obtenu et le résultat obtenu sont identiques
            $this->assertNoDiff($expected, $result, $title);
        }
    }
    
    
    
    
    
    /*
     * Similaire à performTest mis à part que les tests en question doivent générer des exceptions
     * 
     * Appelé par les fonctions de type testNomDuTest_Exceptions
     * 
     * @param $testFile string représentant le nom du fichier de tests relativement au répertoire dirname(__FILE__).'/data/'
     */ 
    function performTest_Exceptions($testFile)
    {
        // 'data/template.htm' est réservé pour le fichier temporaire nécessaire à Template::run
        if ($testFile == 'template.htm' || $testFile == 'template.html')
            throw new Exception('Aucun fichier de test ne peut s\'appeler "template.htm"'); 
            
        if (($tests = file_get_contents(dirname(__FILE__) . '\\data\\' . $testFile)) === false)
            throw new Exception('Fichier non trouvé : ' . dirname(__FILE__) . '\\data\\' . $testFile);
        
        //TODO: fonction qui teste si fichier de test est bien formatté

        
        $tests = explode('====', $tests);   // chaque test est séparé du suivant par '===='
        
        // TODO : virer
        echo 'NOMBRE DE TESTS : ' . count($tests) . '<br />';
        
        foreach($tests as $test)
        {
            // chaque test est au format suivant :
            // --TEST--Chaîne représentant le nom du test
            // --FILE--Chaîne représentant contenu du template
            //
            // Il faut donc récupérer ses chaînes et en supprimer le tag de début,
            // à savoir ('--TEST--' et '--FILE--')
            
            list($title, $template) = explode('--FILE--', $test);
            list(, $title) = explode('--TEST--', $title);

            // TODO: enlever ligne ci-dessous
            echo 'TITLE = ' . $title . '<br />TEMPLATE = ' . $template . '<br />';

            // HACK: comme on ne peut pas exécuter directement un template, on passe par un fichier temp
            $path=dirname(__FILE__) . '/data/template.htm';
            file_put_contents($path, $template);
            
            
            try
            {
                // Exécute le template et bufferise le résultat
                ob_start();
                Template::Run($path, $this->tplDataSrc);
                $result=ob_get_clean();     // sortie effectuée par le template
                
            }
            catch (Exception $e)
            {
                // TODO: enlever la ligne suivante une fois que Daniel l'aura ajouté à son compilateur de templates
                
                ob_end_clean();                   // Ferme la bufferisation ouverte par le compilateur de templates
                continue;
            }
            
            $this->fail();
        }
    }
    
    
    
    
    
    
    /*
     * Test des zones de données simples qui ne génèrent pas d'exception
     */
    function testDataZone()
    {
        $this->performTest('dataZone.htm');
    }
    
    
    /*
     * Test des zones de données simples qui doivent générer une exception
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
     * Test des blocs et des tags optionnels devant générer une exception
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
       * Test des conditionnelles devant générer des exceptions
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
        * Test des boucles devant générer des exceptions
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
          * Test des templates internes qui doivent générer des exceptions
          */
          function testInternalTpl_Exceptions()
          {
              $this->performTest_Exceptions('InternalTpl_Exceptions.htm');
          }
}
?>