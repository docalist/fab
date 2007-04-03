<?php

/*
 * Classe de tests unitaires du syst�me de templates bas�e sur PHPUnit 3
 * 
 * S'assurer du bon fonctionnement du syst�me de templates par rapport au plus grand nombre
 * de cas d'utilisations possible : bonnes syntaxes, mauvaises qui g�n�rent des exceptions, etc.
 */

require_once(dirname(__FILE__).'/../TemplateCompiler.php');
require_once(dirname(__FILE__).'/../TemplateCode.php');

class CompilerTest extends AutoTestCase
{
    public function testfileCompile()
    {
        $this->runTestFile(dirname(__FILE__).'/Template.compile.testfile', array($this, 'compileCallback'));
    }
    
    public function compileCallback($template)
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
            'varEmptyString'=>''
                        
        );

        $result=TemplateCompiler::compile($template, array($data));
        
        if (false !== $pt=strpos($result, '?>'))
            $result=substr($result, $pt+2);
        if (substr($result, -9)==='<?php }?>')
            $result=substr($result, 0, -9);
        return $result;
        
    }
}
?>