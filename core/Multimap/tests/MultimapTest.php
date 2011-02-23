<?php
require dirname(__FILE__) . '/../Multimap.php';
class MultimapTest extends AutoTestCase
{

    // tests unitaires

    function check($data, $expected, $message = '')
    {
        if ($data instanceof Multimap)
            $data = $data->toArray();

        $this->assertSame($data, $expected, $message);
    }

        // compareMode et autres
    public function testCompareMode()
    {
        $map=Multimap::create();
        $this->check($map->compareMode(), Multimap::CMP_EQUAL, 'compare mode par défaut');
        $this->check($map->compareMode(Multimap::CMP_TOKENIZE)->compareMode(), Multimap::CMP_TOKENIZE);
        function f($a,$b){}
        $this->check($map->compareMode('f')->compareMode(), 'f');
    }

    // Test de la méthode has()
    public function testHas()
    {
        $map=Multimap::create()->addMany('item', array('A', 10, '20', '   B   ', 'êté', '  fête ', "(+auJOURd'hui-)", 'corsaire', 'PIRATE' ));
        $this->check($map->has('item', 'A', Multimap::CMP_EQUAL), true);
        $this->check($map->has('item', 'a', Multimap::CMP_EQUAL), false);
        $this->check($map->has('item', 'B', Multimap::CMP_EQUAL), false);
        $this->check($map->has('item', 10, Multimap::CMP_EQUAL), true);
        $this->check($map->has('item', '10', Multimap::CMP_EQUAL), true);
        $this->check($map->has('item', 20, Multimap::CMP_EQUAL), true);
        $this->check($map->has('item', '20', Multimap::CMP_EQUAL), true);
        $this->check($map->has('item', 10, Multimap::CMP_IDENTICAL), true);
        $this->check($map->has('item', '10', Multimap::CMP_IDENTICAL), false);
        $this->check($map->has('item', 20, Multimap::CMP_IDENTICAL), false);
        $this->check($map->has('item', '20', Multimap::CMP_IDENTICAL), true);
        $this->check($map->has('item', 'B', Multimap::CMP_TRIM), true);
        $this->check($map->has('item', 'ete', Multimap::CMP_EQUAL), false);
        $this->check($map->has('item', 'ete', Multimap::CMP_IGNORE_CASE), true);
        $this->check($map->has('item', 'fete', Multimap::CMP_IGNORE_CASE), true);
        $this->check($map->has('item', 'Etë', Multimap::CMP_IGNORE_CASE), true);
        $this->check($map->has('item', 'fEtê', Multimap::CMP_IGNORE_CASE), true);
        $this->check($map->has('item', "aujourd'hui", Multimap::CMP_IGNORE_CASE), false);
        $this->check($map->has('item', "aujourd'hui", Multimap::CMP_TOKENIZE), true);
        $this->check($map->has('item', "âüjourd'++++++HUI", Multimap::CMP_TOKENIZE), true);
        function tok($a,$b){return substr($a, 0, 3) === substr($b, 0, 3);}
        $this->check($map->has('item', "corridor", 'tok'), true);
        $this->check($map->has('item', "pire", 'tok'), false);
        $this->check($map->has('item', "PIRe", 'tok'), true);


        // has()
        $map = Multimap::create(array('a'=>'A', 'b' => 'BB'))->add('b', 'BC');
        $this->check($map->has('a'), true);
        $this->check($map->has('c,b,a'), true);
        $this->check($map->has('c'), false);
        $this->check($map->has('b', 'BC'), true);
        $this->check($map->has('b', 'BD'), false);
        $this->check($map->has('z,y,x,a', 'A'), true);
        $this->check($map->has('z,y,x,a,b', array('BF','BE','BD')), false); // aucune des valeurs indiquées
        $this->check($map->has('z,y,x,a,b', array('BE','BD','BC')), true); // found BC


    }

        // test de la méthode emptyValue
    public function testEmptyValue()
    {
        $data = array(null, true, false, 0, 0.0, 1, 3.14, '', ' ', '0', "\0", array(), array(null), array(0), array(array()),);
        $expected = array(true, false, false, false, false, false, false, true, false, false, false, true, false, false, false,);
        $map = Multimap::create();
        foreach($data as $i=>$value)
            $this->check($map->emptyValue($value), $expected[$i], "emptyValue(" .  strtr(var_export($value,true), "\r\n", "  ") . ")");
    }

    // Test de la méthode statique create
    public function testCreate()
    {
        // Création de Multimaps vides
        $this->check(new Multimap, array());
        $this->check(Multimap::create(), array());
        $this->check(Multimap::create()->clear(), array());

        // Création de multimaps avec des données initiales (teste create et add)
        $this->check(Multimap::create(array('a')), array(0=>'a'));
        $this->check(Multimap::create(array('a', 'b')), array(0=>'a', 1=>'b'));
        $this->check(Multimap::create(array('a'=>'A', 'b'=>'B')), array('a'=>'A', 'b'=>'B'));
        $this->check(Multimap::create(array('a', 'b'), array('a', 'b')), array(0=>array('a','a'), 1=>array('b','b')));
    }

    // Test de clonage
    public function testCopy()
    {
        // objet utilisé pour les tests
        $object = new StdClass;
        $object->flag=true; $object->pi=3.14;

        // Copy / clonage
        $map1 = Multimap::create()->addMany(array('a','b'))->addMany(array('b','c'))->add('k', $object)->add('toto,titi','x');
        $map2 = $map1->copy();
        $this->check($map1->toArray(), $map2->toArray(), 'copy');
    }

    // Test de la méthode clear()
    public function testClear()
    {
        // Clear
        $this->check(Multimap::create()->clear(), array());
        $this->check(Multimap::create(array('a'=>'A'))->clear(), array());
        $this->check(Multimap::create(array('a'=>'A'))->clear('a'), array());
        $this->check(Multimap::create(array('a'=>'A'))->clear('a', 'A'), array());
        $this->check(Multimap::create(array('a'=>'A'))->clear('b'), array('a'=>'A'));
        $this->check(Multimap::create(array('a'=>'A'))->clear('b','A'), array('a'=>'A'));
        $this->check(Multimap::create(array('a'=>'A'), array('a'=>'B'))->clear('a','B'), array('a'=>'A'));
        $this->check(Multimap::create(array('a'=>'A'))->clear('a', 'A', Multimap::CMP_IGNORE_CASE), array());
        $this->check(Multimap::create(array('a'=>'  A   '))->clear('a', 'A', Multimap::CMP_TRIM), array());
        $this->check(Multimap::create(array('a'=>'  été   '))->clear('a', 'ETE', Multimap::CMP_TOKENIZE), array());
    }


    // Key delimiter
    public function testKeyDelimiter()
    {
        $this->check(Multimap::create()->keyDelimiter(), ',', 'délimiteur de clé par défaut : virgule');
        $this->check(Multimap::create()->keyDelimiter(';')->keyDelimiter(), ';', 'changement du délimiteur de clé par défaut');
        $this->check(Multimap::create()->keyDelimiter(';')->add('a;b', 'A'), array('a'=>'A', 'b'=>'A'), 'add("a;b") avec un point virgule');
    }

    public function testAdd()
    {
        // objet utilisé pour les tests
        $object = new StdClass;
        $object->flag=true; $object->pi=3.14;

        // Add
        $this->check(Multimap::create()->add('a', 'A'), array('a'=>'A'), 'add(key,scalar)');
        $this->check(Multimap::create()->add('a,b', 'A'), array('a'=>'A', 'b'=>'A'), 'add("a,b",scalar)');
        $this->check($map = Multimap::create()->add('a', array('A','B')), array('a'=>array('A','B')), 'add(key,array)');
        $map = Multimap::create(array('a'=>'A', 'b'=>'B'));
        $this->check(Multimap::create()->add('map', $map), array('map'=>$map), 'add(key, multimap)');

        $this->check(Multimap::create()->add('a', $object), array('a'=>$object));
        $this->check(Multimap::create()->add('a', 'zz')->add('a', array(1,2,3)), array('a'=>array('zz',array(1,2,3))));

        $this->check(Multimap::create()->add('toto','x')->add('toto','x'), array('toto'=>array('x','x')));
        $this->check($map = Multimap::create()->add('toto,titi','x'), array('toto'=>'x', 'titi'=>'x'));
    }

    public function testAddMany()
    {
        // objet utilisé pour les tests
        $object = new StdClass;
        $object->flag=true; $object->pi=3.14;

        // AddMany avec un seul argument
        $this->check(Multimap::create()->addMany(array('a','b')), array('a','b'), 'addMany(array)');
        $this->check($map = Multimap::create()->addMany(array('a','b'))->addMany(array('b','c')), array(0=>array('a','b'), 1=>array('b','c')), 'addMany(array)->addMany(array)');
        $this->check(Multimap::create($map), array(0=>array('a','b'), 1=>array('b','c')), 'create(multimap)');
        $this->check(Multimap::create()->addMany($object), array('flag'=>true,'pi'=>3.14), 'addMany(object)');
        $this->check(Multimap::create()->addMany(array('a','b'), array('a','b')), array(0=>array('a','a'),1=>array('b','b')), 'addMany(array,array)');

        // AddMany avec un seul argument
        $this->check(Multimap::create()->addMany('item', array('a','b')), array('item'=>array('a','b')), 'addMany(key,array)');
        $this->check(Multimap::create()->addMany('item', array('a','b'), array('a','b')), array('item'=>array('a','b','a','b')), 'addMany(key, array,array)');
        $this->check(Multimap::create()->addMany('item', $object), array('item'=>array(true,3.14)), 'addMany(key,object)');

        // add() versus addMany(key,data) versus addMany(data)
        $map = Multimap::create(array('a','b'));
        $this->check(Multimap::create()->add('item', $map), array('item'=>$map)); // ajout d'une valeur unique à la clé 'item'
        $this->check(Multimap::create()->addMany('item', $map), array('item'=> array(0=>'a',1=>'b'))); // ajout de plusieurs valeurs à une clé unique
        $this->check(Multimap::create()->addMany($map), array(0=>'a',1=>'b')); // ajoute les clés contenue dans le tableau dans la collection

        $this->check(Multimap::create()->addMany($object), (array)$object); // on obtient un tableau contenant les propriétés de l'objet

        // addMany(multimap)
        $map = Multimap::create(array('a'=>'A','b'=>'B'), array('a'=>'AA','b'=>'BB'), array('a'=>'AAA','b'=>'BBB'));
        $copy = Multimap::create()->addMany($map);

        // Bug dans record
        $record=Multimap::create()
            ->add('Ident', '3101')
            ->add('Ident', '123456')
            ->add('Ident', 'HOP114')
            ->add('Ident', 'Bdsp')
            ->add('Ident', 'C')
            ->add('Ident', 'ds')
        ;
        $this->check($record, array('Ident' => Array('3101', '123456', 'HOP114', 'Bdsp', 'C', 'ds')));
        $bdsp = new Multimap($record);
        $this->check($bdsp, array('Ident' => Array('3101', '123456', 'HOP114', 'Bdsp', 'C', 'ds')));
    }

    public function testGet()
    {
        // objet utilisé pour les tests
        $object = new StdClass;
        $object->flag=true; $object->pi=3.14;

        // get()
        $this->check(Multimap::create()->get('item'), null);
        $this->check(Multimap::create()->get('item', 'def'), 'def');
        $this->check(Multimap::create()->get('item,item2'), null);
        $this->check(Multimap::create()->get('item,item2', 'def'), 'def');
        $this->check(Multimap::create(array('a'=>'A'))->get('a'), 'A');
        $this->check(Multimap::create(array('a'=>'A'))->get('x,y,z,a'), 'A');
        $this->check(Multimap::create(array('a'=>'A'))->get('x,y,z,b'), null);
        $this->check(Multimap::create(array('a'=>'A'))->get('*'), 'A');
        $this->check(Multimap::create(array('a'=>array(1,2,3)))->get('*'), array(1,2,3));
        $this->check(Multimap::create(array('a'=>$object))->get('*'), $object);
        $map=Multimap::create(array('x'=>'X'));
        $this->check(print_r(Multimap::create(array('a'=>$map))->get('a'), true), print_r($map,true));
    }

    public function testGetAll()
    {
        // objet utilisé pour les tests
        $object = new StdClass;
        $object->flag=true; $object->pi=3.14;

        // get()
        $this->check(Multimap::create()->getAll('item'), array());
        $this->check(Multimap::create()->getAll('item', 'def'), array());
        $this->check(Multimap::create()->getAll('item,item2'), array());
        $this->check(Multimap::create()->getAll('*'), array());

        $this->check(Multimap::create(array('a'=>'A'))->getAll('a'), array('A'));
        $this->check(Multimap::create(array('a'=>'A'))->getAll('x,y,z,a'), array('A'));
        $this->check(Multimap::create(array('a'=>'A'))->getAll('x,y,z,b'), array());
        $this->check(Multimap::create(array('a'=>'A'))->getAll('*'), array('A'));
        $this->check(Multimap::create(array('a'=>array(1,2,3)))->getAll('*'), array(array(1,2,3)));
        $this->check(Multimap::create(array('a'=>$object))->getAll('*'), array($object));

        $this->check(Multimap::create(array('a'=>'A', 'b'=>'B', 'c'=>'C'))->getAll('a,b,c'), array('A','B','C'));
        $this->check(Multimap::create(array('a'=>'A', 'b'=>'B', 'c'=>'C'))->getAll('a','b','c'), array('A','B','C'));

        $this->check(Multimap::create(array('a'=>'A', 'b'=>'B', 'c'=>'C'))->add('a,b,c','X')->getAll('a,b,c'), array('A','X','B','X','C','X'));
    }

    public function testSet()
    {
        // objet utilisé pour les tests
        $object = new StdClass;
        $object->flag=true; $object->pi=3.14;

        // set()
        $map=Multimap::create();
        $this->check($map->set('item', 12), array('item'=>12));
        $this->check($map->set('item'), array());
        $this->check($map->add('item', 'toto')->set('item', array(1,2)), array('item'=>array(1,2))); // remplace le contenu existant de 'item' par la valeur array(1,2)
        $this->check($map->set('item', $object), array('item'=>$object));
    }

    public function testMove()
    {
        // Transfert d'un champ dans un autre
        $map = Multimap::create(array('a'=>'A', 'b'=>'B', 'c'=>'C'));
        $map->move('a','b');
        $this->check($map, array('b'=>'A','c'=>'C'));

        // Transfert plusieurs champs dans un autre
        $map = Multimap::create(array('a'=>'A', 'b'=>'B', 'c'=>'C'));
        $map->move('a,c,z','b');
        $this->check($map, array('b'=>array('A','C')));

        // Transfert d'un champ vers plusieurs destination
        $map = Multimap::create(array('a'=>'A', 'b'=>'B', 'c'=>'C'));
        $map->move('a','b,z');
        $this->check($map, array('b'=>'A','c'=>'C','z'=>'A'));

        // Recopie d'un champ dans un autre
        $map = Multimap::create(array('a'=>'A', 'b'=>'B', 'c'=>'C'));
        $map->move('a','a,b');
        $this->check($map, array('a'=>'A', 'b'=>'A', 'c'=>'C'));

        // Concaténation d'un champ avec un autre
        $map = Multimap::create(array('a'=>'A', 'b'=>'B', 'c'=>'C'));
        $map->move('a,b','a');
        $this->check($map, array('a'=>array('A','B'), 'c'=>'C'));

        // Transfert d'un champ multivalué
        $map = Multimap::create(array('a'=>'A'), array('a'=>'AA'), array('a'=>'AAA'));
        $map->move('a','b');
        $this->check($map, array('b'=>array('A','AA','AAA')));

        // Transfert d'un champ multivalué
        $map = Multimap::create(array('a'=>'A', 'b'=>'B'), array('a'=>'AA', 'b'=>'BB'), array('a'=>'AAA', 'b'=>'BBB'));
        $map->move('a,b','a');
        $this->check($map, array('a'=>array('A','AA','AAA','B','BB','BBB')));

        // Transfert d'un champ multivalué
        $map = Multimap::create(array('a'=>'A', 'b'=>'B'), array('a'=>'AA', 'b'=>'BB'), array('a'=>'AAA', 'b'=>'BBB'));
        $map->move('ident,a','ident');
        $map->move('ident,b','ident');
        $map->move('ident','a');
        $this->check($map, array('a'=>array('A','AA','AAA','B','BB','BBB')));

        // Transfert d'un champ multivalué
        $map = Multimap::create(array('a'=>'A', 'b'=>'B'), array('a'=>'AA', 'b'=>'BB'), array('a'=>'AAA', 'b'=>'BBB'));
        $map->move('a,b','c');
        $this->check($map, array('c'=>array('A','AA','AAA','B','BB','BBB')));
    }

    public function testKeepOnly()
    {
        // keepOnly
        $this->check(Multimap::create(array_flip(array('a','b','c','d','e','f')))->keepOnly('e'), array('e'=>4));
        $this->check(Multimap::create(array_flip(array('a','b')))->keepOnly('z'), array());
        $this->check(Multimap::create(array_flip(array('a','b','c','d','e','f')))->keepOnly('e', 'a,f'), array('a'=>0, 'e'=>4, 'f'=>5));
    }

    public function testIsEmpty()
    {
        // isEmpty
        $this->check(Multimap::create()->isEmpty(), true);
        $map = Multimap::create(array('a'=>1, 'z'=>26));
        $this->check($map->isEmpty(), false);
        $this->check($map->isEmpty('a'), false);
        $this->check($map->isEmpty('b'), true);
        $this->check($map->isEmpty('p,z'), false);
        $this->check($map->isEmpty('p,q,r,s'), true);
        $this->check($map->isEmpty('*'), false);
        $this->check(Multimap::create()->isEmpty('*'), true);
    }

    public function testCountable()
    {
        // Countable
        $this->check(count(Multimap::create()), 0);

        $map = new MultiMap(array(1,2));
        $this->check(count($map), 2);
        $this->check($map->count(), 2);

        // count(key)
        $this->check(Multimap::create(array('a'=>'A'))->count('a'), 1);
        $this->check(Multimap::create(array('a'=>'A'))->count('b'), 0);
        $this->check(Multimap::create(array('a'=>'A'), array('a'=>'AA'))->count('a'), 2);
    }

    public function testObjectProperties()
    {
        // Méthodes magiques
        $map = new MultiMap(array('a'=>'A', 'b'=>'B', 'c,d'=>'other'));
        $this->check(isset($map->a), true);
        $this->check(isset($map->A), false);
        $this->check($map->A, null);
        unset($map->a);
        $this->check(isset($map->a), false);
        $map->a = "A";
        $this->check(isset($map->a), true);

        $this->check(isset($map->c), true);
        $this->check(isset($map->d), true);

        $map->a = array('A','B');
        $this->check($map->a, array('A','B'));
        //unset($map->a[0]);
        $t = $map->a;
        unset($t[0]);
        $map->a = $t;
        $this->check($map->a, 'B');
    }

    public function testArrayAccess()
    {
        // ArrayAccess
        $map = new Multimap();
        $map['a'] = 'A';
        $map['b'] = 'B';
        $map['c'] = 'C';
        $map['d,e'] = 'other';
        unset($map['b']);
        unset($map['B']);
        $this->check($map, array('a'=>'A', 'c'=>'C', 'd'=>'other', 'e'=>'other'));
        $this->check(isset($map['a']), true);
        $this->check(isset($map['A']), false);
        $this->check(isset($map['d']), true);
        $this->check(isset($map['e']), true);
        $this->check($map['a'], 'A');
        $this->check($map['b'], null);
        $this->check($map['x,y,z,a'], 'A');
        unset($map['e,d']);
        $this->check($map, array('a'=>'A', 'c'=>'C'));
    }


    public function testIterator()
    {
        // Iterator
        $map = new Multimap(array('a'=>'A', 'b'=>'B', 'c' => array('i','j','k')));
        $h = '';
        foreach($map as $key=>&$value)
            if (is_array($value)) $h.="$key={" . implode(',',$value) . '},'; else $h.="$key=$value,";
        $this->check($h, 'a=A,b=B,c={i,j,k},', 'foreach');
    }


    // apply
    public function testApply()
    {
        $this->check(Multimap::create(array('a'=>'      A ', 'b'=>'   B  '))->apply('trim'), array('a'=>'A', 'b'=>'B'));

        function removeA($value)
        {
            if ($value==='A') return '';
            return $value;
        }

        $this->check(Multimap::create(array('a'=>'AA', 'b'=>'B'), array('a'=>'A'))->apply('removeA'), array('a'=>'AA', 'b'=>'B'));
    }

    public function testRun()
    {
        // run
        function dump($key, $value, $format) { printf($format, $key, $value); }
        $map=new Multimap(array('a'=>'A', 'b'=>'B'));
        ob_start();
        $map->run('dump', '*', "%s=%s,");
        $this->check(ob_get_clean(), 'a=A,b=B,');
    }


    public function testFilter()
    {
        function alwaysFalse($value,$key){}
        function alwaysTrue($value,$key){return true;}
        function isInt($value) { return is_int($value); }// comme is_int() mais évite le warning "is_int accepte exactement 1 paramètre"
        function isUppercase($c){return strtoupper($c)===$c;}

        // Filtre qui enlève tout
        $this->check(Multimap::create()->filter('alwaysFalse'), array());

        // Filtre qui enlève tout
        $map = Multimap::create(array('a'=>'A', 'b'=>'B', 'c' => array('i','j','k')));
        $this->check($map->filter('alwaysFalse'), array('A', 'B', array('i','j','k')));
        $this->check($map, array());

        // Filtre qui n'enlève rien
        $map = Multimap::create(array('a'=>'A', 'b'=>'B', 'c' => array('i','j','k')));
        $this->check($map->filter('alwaysTrue'), array());
        $this->check($map, array('a'=>'A', 'b'=>'B', 'c' => array('i','j','k')));

        // Filtre qui enlève tous les entiers
        $map = Multimap::create(array(10,20,'a','12',array(5)));
        $this->check($map->filter('isInt'), array('a','12',array(5)));
        $this->check($map, array(10,20));

        // Filtre qui ne garde que les majuscules
        $map = Multimap::create(array('a'=>'a', 'b'=>'b'), array('a'=>'A', 'b'=>'B'), array('a'=>'a', 'b'=>'b'));
        $this->check($map->filter('isUppercase'), array('a','a','b','b'));
        $this->check($map, array('a'=>'A','b'=>'B'));

    }

    public function testToString()
    {
        $this->assertNoDiff(Multimap::create()->__toString(), "<pre>Multimap vide</pre>");
        $this->assertNoDiff(Multimap::create(array('a'=>1, 'z'=>26))->__toString(),"<pre>Multimap : 2 item(s) = a 1 z 26 </pre>");
    }

    public function testJson()
    {
        $this->check(Multimap::create()->toJson(), '[]');
        $this->check(Multimap::create(array('a'=>1, 'z'=>26))->toJson(),'{"a":1,"z":26}');
    }
}