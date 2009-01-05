<?php

require_once(Runtime::$fabRoot.'core/helpers/Range/Range.php');

define('DEBUG', true);  // mettre à true si on est en phase de débuggage

//if(DEBUG)
//    set_time_limit(2);      // par sécurité


class RangeTest extends AutoTestCase
{
    function setUp()
    {
    }
    
    function tearDown()
    {
    }
   
   
    /*
     * En fonction des données de $tests, créé un objet Range, boucle dessus et indique le statut
     * succès/échec en fonction du résultat attendu
     * 
     * @param $tests tableau contenant les tests au format 'nom test' => array(array($début, $fin, [$pas]), array([$attendu1] , [$attendu2] [,...]))
     * 
     */
    function evaluateBehaviour($tests)
    {
        foreach($tests as $title=>$test)
        {
            $input = $test[0];      // données d'entrée
            $expected = $test[1];    // données attendues en sortie
             
            // Y-a-t-il une variable correspondante au pas en paramètre?
            if (! array_key_exists(2, $input ) )           
                $range = new Range($input[0], $input[1]);
            else
                $range = new Range($input[0], $input[1], $input[2]);
            
            // ajoute l'index d'itération en cours au tableau de résultat
            foreach($range as $output)
                $result[]=$output;      

            $this->assertNoDiff($expected, $result, $title);
            
            // Pour le prochain test : prochain tour de la boucle principale
            unset($result);

        }
    }
    
    
    
    /*
     * Similaire à evaluateBehaviour sauf qu'on teste si les cas génèrent une exception
     * 
     * @param $tests tableau contenant les tests au format 'nom test' => array($début, $fin, [$pas])
     */
    function evaluateException($tests)
    {
        
        foreach($tests as $title=>$test)
        {             

            try     // génèrera probablement une exception
            {                
                if (! array_key_exists(2, $test) )           // Y-a-t-il une variable correspondante au pas en paramètre?
                    $range = new Range($test[0], $test[1]);
                else
                    $range = new Range($test[0], $test[1], $test[2]);

            }
            catch (Exception $e)    // une exception a été générée donc succès
            {
                continue;  
            }
            
            return $this->fail();
        }
    }
    
    
    
    /*
     * Tests de la classe Range
     */
    function testLoop()
    {    
        // La série de tests qu'on souhaite réaliser et qui ne doivent pas générer d'exception (les cas devant en générer sont testés après)
        // Chaque test est au format : "nom du test" => array( array(début, fin[, pas]), array([résultat1,[résultat2,...]])

        $testsNoException = array
        (
            'Début < Fin avec un pas automatique' => array
            (
                array(2.3, 8),
                array(2.3, 3.3, 4.3, 5.3, 6.3, 7.3)
            ),
            
            'Début > Fin avec un pas automatique' => array
            (
                array(5, 1),
                array(5, 4, 3, 2, 1)
            ),
            
            'Début = Fin avec un pas automatique' => array
            (
                array(4.2, 4.2),
                array(4.2)
            ),
            
            'Début < Fin avec un pas positif' => array
            (
                array(2.1, 7.1, 1.1),
                array(2.1, 3.2, 4.3, 5.4, 6.5)
            ),
            
            'Début > Fin avec un pas négatif' => array
            (
                array(5.6, 0.74, -2),
                array(5.60000000000000001, 3.6, 1.6)
            ),
            
            'Début = Fin avec un pas négatif' => array
            (
                array(0, 0, -1),
                array(0)
            ),
            
            // Même chose avec des caractères maintenant
            
            'Caractères - Début < Fin avec un pas automatique' => array
            (
                array('a', 'd'),
                array('a', 'b', 'c', 'd')
            ),
            
            'Caractères - Début > Fin avec un pas automatique' => array
            (
                array('g', 'c'),
                array('g', 'f', 'e', 'd', 'c')
            ),
            
            'Caractères - Début = Fin avec un pas automatique' => array
            (
                array('v', 'v'),
                array('v')
            ),
            
            'Caractères - Début < Fin avec un pas positif' => array
            (
                array('a', 'k', 3),
                array('a', 'd', 'g', 'j'), 
            ),
            
            'Caractères - Début > Fin avec un pas négatif' => array
            (
                array('G', 'C', -2),
                array('G', 'E', 'C')
            ),
            
            'Caractères - Début = Fin avec un pas négatif' => array
            (
                array('s', 's', -6),
                array('s')
            )
        );
        
        //effectue les tests
        $this->evaluateBehaviour($testsNoException);
        
        
        // la série de tests qui doivent générer une exception
        $exceptionExpected = array
        (
            'Début < Fin avec un pas négatif' => array(5.2, 9, -3),
            
            'Début > Fin avec un pas positif' => array(12.36, -2, 5.14),
            
            'Un entier et un caractère' => array(10, 'g'),
            
            'Un caractère et un réel' => array('j', -2.31),
            
            'Début et fin de type caractère avec un pas de type float' => array('h', 'l', 2.4),
            
            'Un des arguments est une chaîne de caractère' => array('A string', 'f'),
            
            'Début est une minuscule et Fin est une majuscule' => array('f', 'A'),
            
            'Début est une majuscule et Fin est une minuscule' => array('s', 'Y')
        );
        
        $this->evaluateException($exceptionExpected);
    }
}   // end class

?>