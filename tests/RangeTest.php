<?php

require_once ('../core/debug/Debug.php');
require_once ('../Utils.php');
require_once ('../Runtime.php');
require_once ('../core/config/Config.php');
require_once ('../core/cache/Cache.php');
require_once ('../core/template/Template.php');

require_once('../core/helpers/Range/Range.php');

define('DEBUG', true);  // mettre � true si on est en phase de d�buggage

    


class RangeTest extends UnitTestCase
{
    function setUp()
    {
        Cache::addCache(dirname(__FILE__), dirname(__FILE__) . '/data/cache');
    }
    
    
    
    function tearDown()
    {
    }
   
   
   
    /*
     * En fonction des donn�es de $tests, cr�� un objet Range, boucle dessus et indique le statut
     * succ�s/�chec en fonction du r�sultat attendu
     * 
     * @param $tests tableau contenant les tests au format 'nom test' => array($d�but, $fin, [$pas]), array([$attendu1] , [$attendu2] [,...]))
     * 
     */
    function evaluateBehaviour($tests)
    {
        foreach($tests as $title=>$test)
        {
            $input = $test[0];      // donn�es d'entr�e
            $expected = $test[1];    // donn�es attendues en sortie
             
             
            if (! array_key_exists(2, $input ) )           // Y-a-t-il une variable correspondante au pas en param�tre?
                $range = new Range($input[0], $input[1]);
            else
                $range = new Range($input[0], $input[1], $input[2]);

                
            foreach($range as $output)
            {
                $result[]=$output;      // ajoute l'index d'it�ration en cours au tableau de r�sultat
            }
                
            if( (! $this->assertIdentical($expected, $result, $title)) && DEBUG )
            {
                echo 'RESULTAT : ';
                var_export($result);       // affiche r�sultat si test fail et mode d�buggage
                echo '<br>ATTENDU : ';
                var_export($expected);
            }
            
            // Pour le prochain test : prochain tour de la boucle principale
            unset($result);

        }
    }
    
    
    
    /*
     * Similaire � evaluateBehaviour sauf qu'on teste si les cas g�n�rent une exception
     * 
     * @param $tests tableau contenant les tests au format 'nom test' => array($d�but, $fin, [$pas])
     */
    function evaluateException($tests)
    {

        //TODO: virer code DEBUG
        echo '$count($tests) vaut 2<br>';
        
        foreach($tests as $title=>$test)
        {             
            //TODO: virer code DEBUG
            echo 'Une seule fois dans le foreach donc BUG : IL Y A NORMALEMENT 2 ELEMENTS DANS LE TABLEAU';
            
            
            try     // g�n�rera probablement une exception
            {
                
                if (! array_key_exists(2, $test) )           // Y-a-t-il une variable correspondante au pas en param�tre?
                    $range = new Range($test[0], $test[1]);
                else
                    $range = new Range($test[0], $test[1], $test[2]);
                    
                return $this->fail();
            }
            catch (Exception $e)    // une exception a �t� g�n�r�e donc succ�s
            {
                return $this->pass($title);   
            }
        }
    }
    
    
    
    /*
     * Tests de la classe Range
     */
    function testLoop()
    {    
        // La s�rie de tests qu'on souhaite r�aliser et qui ne doivent pas g�n�rer d'exception (les cas devant en g�n�rer sont test�s apr�s)
        // Chaque test est au format : "nom du test" => array( array(d�but, fin[, pas]), array([r�sultat1,[r�sultat2,...]])

        $testsNoException = array
        (
            'D�but < Fin avec un pas automatique' => array
            (
                array(2.3, 8),
                array(2.3, 3.3, 4.3, 5.3, 6.3, 7.3)
            ),
            
            'D�but > Fin avec un pas automatique' => array
            (
                array(5, 1),
                array(5, 4, 3, 2, 1)
            ),
            
            'D�but = Fin avec un pas automatique' => array
            (
                array(4.2, 4.2),
                array(4.2)
            ),
            
            'D�but < Fin avec un pas positif' => array
            (
                array(2.1, 7.1, 1.1),
                array(2.1, 3.2, 4.3, 5.4, 6.5)
            ),
            
            'D�but > Fin avec un pas n�gatif' => array
            (
                array(5.6, 0.74, -2),
                array(5.60000000000000001, 3.6, 1.6)
            ),
            
            'D�but = Fin avec un pas n�gatif' => array
            (
                array(0, 0, -1),
                array(0)
            )
        );
        
        //effectue les tests
        $this->evaluateBehaviour($testsNoException);
        
        
        // la s�rie de tests qui doivent g�n�rer une exception
        $exceptionExpected = array
        (
            'D�but < Fin avec un pas n�gatif' => array(5.2, 9, -3)
            ,
            'D�but > Fin avec un pas positif' => array(12.36, -2, 5.14)
        );
        
        $this->evaluateException($exceptionExpected);
    }
}   // end class

?>