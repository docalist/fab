<?php
require_once(dirname(__FILE__).'/../Request.php');

class RequestTest extends AutoTestCase
{
    private $params=array
    (
        'ref'=>12,
        'item'=>'AAA'
    );
    
    public function testModuleAction()
    {
        // Crée la requête
        $request=new Request($this->params);
        
        // Initialement null
        $this->assertSame($request->getModule(), null);
        $this->assertSame($request->getAction(), null);
        
        // Définit
        $this->assertSame($request->setModule('mymod'), $request, 'setModule ne retourne pas $request');        
        $this->assertSame($request->setAction('myact'), $request, 'setAction ne retourne pas $request');        
        
        // Vérifie
        $this->assertSame($request->getModule(), 'mymod');
        $this->assertSame($request->getAction(), 'myact');

        // Clear
        $this->assertSame($request->setModule(), $request, 'setModule ne retourne pas $request');        
        $this->assertSame($request->setAction(), $request, 'setAction ne retourne pas $request');        
        
        // Vérifie
        $this->assertSame($request->getModule(), null);
        $this->assertSame($request->getAction(), null);
    }
    
    public function test_EmptyRequest()
    {
        // Crée une requête vide
        $request=new Request();
        
        // Vérifie que tout est vide
        $this->assertSame($request->has('ref'), false);
        $this->assertSame($request->has('item'), false);
        $this->assertSame($request->has('void'), false);
        
        $this->assertSame($request->get('ref'), null);
        $this->assertSame($request->get('item'), null);
        $this->assertSame($request->get('void'), null);
        
        $this->assertSame($request->ref, null);
        $this->assertSame($request->item, null);
        $this->assertSame($request->void, null);
        
        $this->assertSame($request->hasParameters(), false);
        $this->assertSame($request->getParameters(), array());
        
    }
    
    public function test_Get_Set_Has_GetParameters_Add_Clear()
    {
        // Crée la requête
        $request=new Request($this->params);
        
        // Vérifie que la valeur de quelques paramètres est correctement retournée
        $this->assertSame($request->has('ref'), true);
        $this->assertSame($request->get('ref'), 12);
        $this->assertSame($request->get('item'), 'AAA');
        $this->assertSame($request->has('item'), true);
        $this->assertSame($request->hasParameters(), true);        
        $this->assertSame($request->getParameters(), $this->params);        
        
        // Null pour les params qui n'existent pas
        $this->assertSame($request->has('void'), false);
        $this->assertSame($request->get('void'), null);
        
        // Modifie item, vérifie que set retourne $this
        $this->assertSame($request->set('item', 'BBB'), $request, 'set ne retourne pas $request');        
        $this->assertSame($request->has('item'), true);
        $this->assertSame($request->get('item'), 'BBB');
        
        // Clear item
        $this->assertSame($request->clear('item'), $request, 'clear ne retourne pas $request');        
        $this->assertSame($request->has('item'), false);
        $this->assertSame($request->get('item'), null);
        $this->assertSame($request->hasParameters(), true);        
        
        // Add après un set
        $this->assertSame($request->set('item', 'AAA'), $request, 'set ne retourne pas $request');        
        $this->assertSame($request->add('item', 'BBB'), $request, 'add ne retourne pas $request');        
        $this->assertSame($request->has('item'), true);
        $this->assertSame($request->get('item'), array('AAA','BBB'));
        $this->assertSame($request->getParameters(), array('ref'=>12, 'item'=>array('AAA','BBB')));        
        
        // Add après un clear d'item
        $this->assertSame($request->clear('item'), $request, 'clear ne retourne pas $request');        
        $this->assertSame($request->has('item'), false);
        $this->assertSame($request->add('item', 'AAA'), $request, 'set ne retourne pas $request');        
        $this->assertSame($request->add('item', 'BBB'), $request, 'add ne retourne pas $request');        
        $this->assertSame($request->has('item'), true);
        $this->assertSame($request->get('item'), array('AAA','BBB'));
        $this->assertSame($request->getParameters(), array('ref'=>12, 'item'=>array('AAA','BBB')));        
        
        // Clear all
        $this->assertSame($request->clear(), $request, 'clear ne retourne pas $request');        
        $this->assertSame($request->has('ref'), false);
        $this->assertSame($request->has('item'), false);
        $this->assertSame($request->hasParameters(), false);        
        $this->assertSame($request->getParameters(), array());        
        
    }

    public function testProperties()
    {
        // Crée la requête
        $request=new Request($this->params);
        
        // Vérifie que la valeur de quelques paramètres est correctement retournée
        $this->assertSame($request->ref, 12);
        $this->assertSame($request->item, 'AAA');
        
        // Null pour les params qui n'existent pas
        $this->assertSame($request->void, null);
        
        // Modifie item, vérifie que set retourne $this
        $request->item='BBB';        
        $this->assertSame($request->item, 'BBB');
        
        // Clear item
        unset($request->item);
        $this->assertSame($request->has('item'), false);
        $this->assertSame($request->item, null);
        $this->assertSame($request->hasParameters(), true);        
        
        // Add après un set
        $request->item='AAA';        
        $this->assertSame($request->add('item', 'BBB'), $request, 'add ne retourne pas $request');        
        $this->assertSame($request->item, array('AAA','BBB'));
        
        // Add après un clear d'item
        unset($request->item);        
        $this->assertSame($request->has('item'), false);
        $this->assertSame($request->add('item', 'AAA'), $request, 'set ne retourne pas $request');        
        $this->assertSame($request->add('item', 'BBB'), $request, 'add ne retourne pas $request');        
        $this->assertSame($request->has('item'), true);
        $this->assertSame($request->item, array('AAA','BBB'));
        
        // Clear all
        $this->assertSame($request->clear(), $request, 'clear ne retourne pas $request');        
        $this->assertSame($request->ref, null);
        $this->assertSame($request->item, null);
    }
    
    public function testChaining()
    {
        $request=new Request();
        
        $request
            ->setModule('thesaurus')
            ->setAction('search')
            ->set('query', 12)
            ->clear('query')
            ->add('tg','health')
            ->add('max','10')
            ->add('sort','%');
            
        $this->assertNoDiff
        (
            var_export($request, true), 
            "
            Request::__set_state
            (
                array
                (
                    '_parameters' => array
                    (
                        'tg' => 'health',
                        'max' => '10',
                        'sort' => '%',
                    ),
                    '_module' => 'thesaurus',
                    '_action' => 'search',
                    '_checkName' => NULL,
                    '_check' => NULL,
                )
            )"
        );
    }
    
    public function testRequiredIntMinMax()
    {
        foreach(array(12, 12.00000, '12', '   12', '0012','   0012 ', array(12,12,12), array(12.00, '  12 ', '00012 ')) as $ref)
        {
            $request=new Request(array('ref'=>$ref));
            
            $this->assertSame
            (
                $result=$request
                    ->required('ref')
                    ->int()
                    ->min(11)
                    ->max(20)
                    ->ok(),
                is_scalar($ref) ? 12 : array(12,12,12),
                'La validation a échouée pour ref='. var_export($ref,true) . ' : valeur obtenue='.var_export($result,true)
            );
        }

        $badrefs= array
        (
            array(null              , 'RequestParameterRequired'    ),
            array(''                , 'RequestParameterRequired'    ),
            array('    '            , 'RequestParameterRequired'    ),
            array('abc'             , 'RequestParameterIntExpected' ),
            array(12.458            , 'RequestParameterIntExpected' ),
            array(PHP_INT_MAX+1     , 'RequestParameterIntExpected' ),    // trop grand pour un int
            array(-PHP_INT_MAX-2    , 'RequestParameterIntExpected' ),     // trop petit
            array(0                 , 'RequestParameterMinExpected' ),
            array(10                , 'RequestParameterMinExpected' ),
            array(30                , 'RequestParameterMaxExpected' ),
            array(array()           , 'RequestParameterRequired'    ),  // required s'applique à chaque élémént
            array(array('')         , 'RequestParameterRequired'    ),
            array(array(null)       , 'RequestParameterRequired'    ),
            array(array(12,'abc',12), 'RequestParameterIntExpected' ),
            );  
                
        foreach($badrefs as $badref)
        {
            list($ref,$exception)=$badref;
            $request=new Request(array('ref'=>$ref));
            try
            {
                $result=$request
                    ->required('ref')
                    ->int()
                    ->min(11)
                    ->max(20)
                    ->ok();
                $fail=true;        
            }
     
            catch(Exception $e)
            {
                ob_start();
                var_dump($ref);                
                $this->assertTrue($e instanceof $exception, 'ref='.ob_get_clean().', exception générée : '.get_class($e).', attendue : '.$exception);
                $fail=false;
            }
            if ($fail)
            {
                ob_start();
                var_dump($ref);                
                $this->fail('Aucune exception n\'a été générée pour ref='.ob_get_clean());
            }            
        }
    }

    public function testBool()
    {
        $bools=array
        (
            'true'=>array(true,'true','TRUE  ', '   TrUe ', ' on ', ' ON ', 'On', 1, ' 1',array(true, 'TRUE', 'ON')),
            'false'=>array(false,'false', 'FALSE  ', '  FaLsE', 'off', " OFF\t", ' Off ', 0, '  0')
        );
        
        foreach($bools as $expected=>$bools)
        {
            $expected=($expected==='true' ? true : false);
            foreach($bools as $flag)
            {
                $request=new Request(array('flag'=>$flag));
                $this->assertSame
                (
                    $result=$request
                        ->bool('flag')
                        ->ok(),
                    is_scalar($flag) ? $expected : array($expected, $expected, $expected),
                    'La validation a échouée pour flag='. $flag . ' : valeur obtenue='.$result
                );
            }
        }

        $badbools= array
        (
            'null', -1, 2, 0.0, 'active', array(true, 'aa')
        );  
                
        foreach($badbools as $badbool)
        {
            $request=new Request(array('flag'=>$badbool));
            try
            {
                $result=$request
                    ->bool('flag');
                $fail=true;        
            }
     
            catch(Exception $e)
            {
                ob_start();
                var_dump($badbool);                
                $this->assertTrue($e instanceof RequestParameterBoolExpected, 'flag='.ob_get_clean().', exception générée : '.get_class($e).', attendue : RequestParameterBoolExpected');
                $fail=false;
            }
            if ($fail)
            {
                ob_start();
                var_dump($badbool);                
                $this->fail('Aucune exception n\'a été générée pour flag='.ob_get_clean());
            }            
        }
    }

    public function testOneOf()
    {
        $request=new Request(array('action'=>'ShOw'));
        $this->assertSame($request->required('action')->oneof('show')->ok(), 'show');
        
        $t=array
        (
            'search','show','load','save', 'SEARCH','SHOW','LOAD','SAVE', '   sEarCh  ','  sHow  ','  lOAd  ','  Save  ',
            array('show', 'search'),array('    ShOw', 'SeArCh')
        );
        foreach($t as $i=>$action)
        {
            $request=new Request(array('action'=>$action));
            $this->assertSame
            (
                $result=$request
                    ->oneof('action', 'search','show','load','save')
                    ->ok(),
                is_scalar($action) ? $t[$i % 4] : array('show','search'),
                'La validation a échouée pour action='. $action . ' : valeur obtenue='.$result
            );
        }

        $badvalues= array
        (
            'null', -1, 2, 0.0, 'active',
            array('show','search','bad') 
        );  
                
        foreach($badvalues as $badvalue)
        {
            $request=new Request(array('action'=>$badvalue));
            try
            {
                $result=$request
                    ->oneof('action', 'search','show','load','save')
                    ->ok();
                $fail=true;        
            }
     
            catch(Exception $e)
            {
                ob_start();
                var_dump($badvalue);                
                $this->assertTrue($e instanceof RequestParameterBadValue, 'value='.ob_get_clean().', exception générée : '.get_class($e).', attendue : RequestParameterBadValue');
                $fail=false;
            }
            if ($fail)
            {
                ob_start();
                var_dump($badvalue);                
                $this->fail('Aucune exception n\'a été générée pour value='.ob_get_clean());
            }            
        }
    }

    public function testUniqueMultipleCount()
    {
        $request=new Request(array('item'=>'un', 'item2'=>array('un'), 'item3'=>array(), 'items'=>array('un','deux')));
        $this->assertSame('un', $request->unique('item')->ok());
        $this->assertSame('un', $request->unique('item2')->ok());
        $this->assertSame(null, $request->unique('item3')->ok());
        $this->assertSame(array('un'), $request->asArray('item')->ok());
        $this->assertSame(array('un'), $request->asArray('item2')->ok());
        $this->assertSame(array('un'), $request->asArray('item')->count(1)->ok());
        $this->assertSame(array('un'), $request->count('item',1)->ok());
        $this->assertSame(array('un'), $request->count('item',1,10)->ok());
        $this->assertSame(array('un','deux'), $request->asArray('items')->ok());
        $this->assertSame(array('un','deux'), $request->asArray('items')->count(2)->ok());
        $this->assertSame(array('un','deux'), $request->asArray('items')->count(2,10)->ok());
        
        $bad=array
        (
            '$request->unique("items");'=>'RequestParameterUniqueValueExpected',
            '$request->count("item",0);'=>'RequestParameterCountException',
            '$request->count("items",5,6);'=>'RequestParameterCountException',
        );
        foreach($bad as $bad=>$exception)
        {
            $request->ok();
            try
            {
                eval($bad);
                $fail=true;        
            }
     
            catch(Exception $e)
            {
                $this->assertTrue($e instanceof $exception , 'value='.ob_get_clean().', exception générée : '.get_class($e).', attendue : '.$exception);
                $fail=false;
            }
            if ($fail)
            {
                $this->fail('Aucune exception n\'a été générée pour '.$bad);
            }
        }            
        
        
    }
    
    public function testValidationBadUsage()
    {
        $request=new Request(array('b'=>true, 'i'=>5));
        $bad=array
        (
            '$request->int();', // paramètre à vérifier non indiqué
            '$request->bool();',
            '$request->min(2);',
            '$request->max(3);',
            '$request->required();',
//          '$request->oneof("a", 1,2,3);', // ne génère jamais bad usage : prend le premier si pas de param en cours
        
            '$request->bool("b")->int("i");', // plusieurs appels avec param sans ok() avant
            '$request->int("i")->min("i",5);',
        );
        foreach($bad as $bad)
        {
            $request->ok();
            try
            {
                eval($bad);
                $fail=true;        
            }
     
            catch(Exception $e)
            {
                $this->assertTrue($e instanceof BadMethodCallException , 'value='.ob_get_clean().', exception générée : '.get_class($e).', attendue : BadMethodCallException ');
                $fail=false;
            }
            if ($fail)
            {
                $this->fail('Aucune exception n\'a été générée pour '.$bad);
            }
        }            
    }
}
?>