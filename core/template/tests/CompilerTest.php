<?php

/*
 * Classe de tests unitaires du système de templates basée sur PHPUnit 3
 * 
 * S'assurer du bon fonctionnement du système de templates par rapport au plus grand nombre
 * de cas d'utilisations possible : bonnes syntaxes, mauvaises qui génèrent des exceptions, etc.
 */

require_once(dirname(__FILE__).'/../TemplateCompiler.php');
require_once(dirname(__FILE__).'/../TemplateCode.php');

class CompilerTest extends AutoTestCase
{
    public function testfileCompile()
    {
//        $this->runTestFile(dirname(__FILE__).'/Template.opt.testfile', array($this, 'compileCallback'));
        $this->runTestFile(dirname(__FILE__).'/slots.testfile', array($this, 'compileCallback'));
//        $this->runTestFile(dirname(__FILE__).'/Template.compile.testfile', array($this, 'compileCallback'));
//        $this->runTestFile(dirname(__FILE__).'/Template.compile.strip_et_test.testfile', array($this, 'compileCallback'));
    }
    
    public function compileCallback($template)
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

        $result=TemplateCompiler::compile
        (
            $template, 
            array
            (
//                $data
//                new ObjectProperties()
//                new ObjectMagicProperties()
//                array($this, 'callbackMethod')    // méthode callback
//                'callbackFunction'    // fonctioncallback
                new ArrayAccessObject($data)
            )
        );
        
        if (false !== $pt=strpos($result, '?>'))
            $result=substr($result, $pt+2);
        if (substr($result, -9)==='<?php }?>')
            $result=substr($result, 0, -9);

        return $result;
        
    }

    // callback utilisé à des fins de tests unitaires : retourne le type de la variable
    function callbackMethod($var)
    {
        // le callback n'est actif que pour une source de données appelée de la manière : $callbackVar
        // pour éviter que le callback ne s'active quand on ne le veux pas : risquerait d'avoir des effets
        // de bord sur certains tests
    //        if($var === 'callbackVar')
    //            return 'callbackTest appelé pour ' . $var; 
    
        switch($var)
        {
            case 'varFalse': return false;
            case 'varAut': return 'Spécialiste en santé publique'; 
            case 'varTitorigA': return 'Titre original de niveau \'analytique\'';
            case 'varTitorigM': return 'Titre original de niveau "monographique"';
            case 'varNull': return 0;// can't return null from a callback
            case 'varZero': return 0;
            case 'varEmptyString': return '';
            case 'varA': return 'A';
            case 'varTrois': return 3;
            case 'arrayCinq': return array(0, 1, 2, 3, 4, 5);
            case 'assocArray': return array('key1'=>'valeur 1', 'key2'=>'valeur 2');
            case 'emptyArray': return array();
        }
    }
    
}
class ObjectProperties
{
    public $varFalse=false;
    public $varAut='Spécialiste en santé publique'; 
    public $varTitorigA='Titre original de niveau \'analytique\'';
    public $varTitorigM='Titre original de niveau "monographique"';
    public $varNull=null;
    public $varZero=0;
    public $varEmptyString='';
    public $varA='A';
    public $varTrois=3;
    public $arrayCinq=array(0, 1, 2, 3, 4, 5);
    public $assocArray=array('key1'=>'valeur 1', 'key2'=>'valeur 2');
    public $emptyArray=array();
}   

// callback utilisé à des fins de tests unitaires : retourne le type de la variable
function callbackFunction($var)
{
    // le callback n'est actif que pour une source de données appelée de la manière : $callbackVar
    // pour éviter que le callback ne s'active quand on ne le veux pas : risquerait d'avoir des effets
    // de bord sur certains tests
//        if($var === 'callbackVar')
//            return 'callbackTest appelé pour ' . $var; 

    switch($var)
    {
        case 'varFalse': return false;
        case 'varAut': return 'Spécialiste en santé publique'; 
        case 'varTitorigA': return 'Titre original de niveau \'analytique\'';
        case 'varTitorigM': return 'Titre original de niveau "monographique"';
        case 'varNull': return 0;// can't return null from a callback
        case 'varZero': return 0;
        case 'varEmptyString': return '';
        case 'varA': return 'A';
        case 'varTrois': return 3;
        case 'arrayCinq': return array(0, 1, 2, 3, 4, 5);
        case 'assocArray': return array('key1'=>'valeur 1', 'key2'=>'valeur 2');
        case 'emptyArray': return array();
    }
}

class ArrayAccessObject implements ArrayAccess
{
    private $data=null;
    
    public function __construct($data)
    {
        if (! is_array($data))
            throw new Exception('Vous devez passer un tableau pour créer un objet '.get_class());
        $this->data=$data;
    }
    
    public function offsetExists($offset)
    {
        return isset($this->data[$offset]);
    }
    public function offsetGet($offset)
    {
        if (isset($this->data[$offset])) return $this->data[$offset];
        throw new Exception("Undefined index $offset");
    }   
    public function offsetSet($offset, $value)
    {
        throw new exception('Un objet ' . get_class() . ' n\'est pas modifiable');
    }   
    public function offsetUnset($offset)
    {
        throw new exception('Un objet ' . get_class() . ' n\'est pas modifiable');
    }   
}

?>