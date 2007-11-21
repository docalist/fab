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
    
    private $showCompiledSource=false;
    
    // Les données qui sont passées aux templates lors de leur instanciation
    private $data = array
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
        
        
    function setUp()
    {
        Cache::addCache(dirname(__FILE__), dirname(__FILE__) . '/data/cache');
        Config::set('templates.removehtmlcomments',true);
    }
    
    function tearDown()
    {
    }
    
    
    // Fonction de test des template match et son callback
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
    
    
    // Fonction de test du parseur d'expressions
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
     * fonction de test du compilateur de templates et les fonctions de callback associées
     * 
     * On teste les différents 'tags' du gestionnaire en passant les données de toutes les manières possibles :
     * fonction de callback, tableau, méthode de callback, etc.
     */
    public function testfileTemplateCompiler()
    {
        config::set('cache.enabled', false);
//        foreach(array('ObjectMagicProperties') as $h)
//        foreach(array('Array','FunctionCallback','MethodCallback','ObjectProperties','ObjectMagicProperties','ArrayAccessObject') as $h)
        foreach(array('Array') as $h)
//        foreach(array('Array','FunctionCallback','MethodCallback','ObjectProperties','ObjectMagicProperties',) as $h)
        {
//            $this->runTestFile(dirname(__FILE__).'/temp.testfile', array($this, "templateCompilerCallbackWith$h"));

            $this->runTestFile(dirname(__FILE__).'/Template.def.testfile', array($this, "templateCompilerCallbackWith$h"));
            $this->runTestFile(dirname(__FILE__).'/Template.iftag.testfile', array($this, "templateCompilerCallbackWith$h"));
            $this->runTestFile(dirname(__FILE__).'/Template.opt.testfile', array($this, "templateCompilerCallbackWith$h"));
            $this->runTestFile(dirname(__FILE__).'/Template.switch.testfile', array($this, "templateCompilerCallbackWith$h"));
            $this->runTestFile(dirname(__FILE__).'/Template.loop.testfile', array($this, "templateCompilerCallbackWith$h"));
            $this->runTestFile(dirname(__FILE__).'/Template.fill.testfile', array($this, "templateCompilerCallbackWith$h"));
            $this->runTestFile(dirname(__FILE__).'/Template.strip.testfile', array($this, "templateCompilerCallbackWith$h"));
            $this->runTestFile(dirname(__FILE__).'/Template.test.testfile', array($this, "templateCompilerCallbackWith$h"));
            $this->runTestFile(dirname(__FILE__).'/Template.misc.testfile', array($this, "templateCompilerCallbackWith$h"));
            $this->runTestFile(dirname(__FILE__).'/Template.iffalsebug.testfile', array($this, "templateCompilerCallbackWith$h"));
        }
    }
    
    public function templateCompilerCallbackWithArray($template)
    {
        if ($template === '') return '';

        if ($this->showCompiledSource)
        {
            $result=TemplateCompiler::compile($template, array($this->data));
            echo '<pre style="border: 1px solid red; background-color: #eee;">', htmlentities($result), '</pre>';
        }

        $tmp=dirname(__FILE__) . '/tempfile.txt';
        file_put_contents($tmp, $template);
        ob_start();
        
        Template::run($tmp, $this->data);
        
        $result=ob_get_clean();
        unlink($tmp);
        
        return $result;
    }
    
    public function templateCompilerCallbackWithFunctionCallback($template)
    {
        if ($template === '') return '';

        if ($this->showCompiledSource)
        {
            $result=TemplateCompiler::compile($template, array('callbackFunction'));
            echo '<pre style="border: 1px solid red; background-color: #eee;">', htmlentities($result), '</pre>';
        }
        
        $tmp=dirname(__FILE__) . '/tempfile.txt';
        file_put_contents($tmp, $template);
        ob_start();
        
        Template::run($tmp, 'callbackFunction');
        
        $result=ob_get_clean();
        unlink($tmp);
        
        return $result;
    }
    
    public function templateCompilerCallbackWithMethodCallback($template)
    {
        if ($template === '') return '';

        if ($this->showCompiledSource)
        {
            $result=TemplateCompiler::compile($template, array(array($this, 'callbackMethod')));
            echo '<pre style="border: 1px solid red; background-color: #eee;">', htmlentities($result), '</pre>';
        }
        
        $tmp=dirname(__FILE__) . '/tempfile.txt';
        file_put_contents($tmp, $template);
        ob_start();
        
        Template::run($tmp, array($this, 'callbackMethod'));
        
        $result=ob_get_clean();
        unlink($tmp);
        
        return $result;
    }
    
    public function templateCompilerCallbackWithObjectProperties($template)
    {
        if ($template === '') return '';

        if ($this->showCompiledSource)
        {
            $result=TemplateCompiler::compile($template, array(new ObjectProperties()));
            echo '<pre style="border: 1px solid red; background-color: #eee;">', htmlentities($result), '</pre>';
        }
        
        $tmp=dirname(__FILE__) . '/tempfile.txt';
        file_put_contents($tmp, $template);
        ob_start();
        
        Template::run($tmp, new ObjectProperties());
        
        $result=ob_get_clean();
        unlink($tmp);
        
        return $result;
    }
    
    public function templateCompilerCallbackWithObjectMagicProperties($template)
    {
        if ($template === '') return '';

        if ($this->showCompiledSource)
        {
            $result=TemplateCompiler::compile($template, array(new ObjectMagicProperties($this->data)));
            echo '<pre style="border: 1px solid red; background-color: #eee;">', htmlentities($result), '</pre>';
        }
        
        $tmp=dirname(__FILE__) . '/tempfile.txt';
        file_put_contents($tmp, $template);
        ob_start();
        
        Template::run($tmp, new ObjectMagicProperties($this->data));
        
        $result=ob_get_clean();
        unlink($tmp);
        
        return $result;
    }

    public function templateCompilerCallbackWithArrayAccessObject($template)
    {
        if ($template === '') return '';

        if ($this->showCompiledSource)
        {
            $result=TemplateCompiler::compile($template, array(new ArrayAccessObject($this->data)));
            echo '<pre style="border: 1px solid red; background-color: #eee;">', htmlentities($result), '</pre>';
        }
        
        $tmp=dirname(__FILE__) . '/tempfile.txt';
        file_put_contents($tmp, $template);
        ob_start();
        
        Template::run($tmp, new ArrayAccessObject($this->data));
        
        $result=ob_get_clean();
        unlink($tmp);
        
        return $result;
    }

    
    function callbackMethod($var)
    {
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

// les variables sont des noms reconnus par une fonction globale
function callbackFunction($var)
{
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

// les variables sont des propriétés publiques de l'objet
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

// les variables sont des propriétés "magiques" de l'objet (retournées par la méthode magique __get())
class ObjectMagicProperties
{
    private $data=null;
    
    public function __construct($data)
    {
        if (! is_array($data))
            throw new Exception('Vous devez passer un tableau pour créer un objet '.get_class());
        $this->data=$data;
    }
    
    public function __get($offset)
    {
        if (array_key_exists($offset,$this->data)) return $this->data[$offset];
    }
      
}

// les variables sont des clés reconnues par un objet implémentant l'interface ArrayAccess
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
        return array_key_exists($offset,$this->data);
    }
    public function offsetGet($offset)
    {
        // un objet ArrayAccess DOIT générer une exception si on lui demande une
        // clé qui n'existe pas (sinon, il répond "la variable existe" pour n'importe
        // quelle variable)
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