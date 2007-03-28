<?php

// dans phpunit, tous les chemins sont semi absolus. fixe la racine
$old=set_include_path(Runtime::$fabRoot.'lib/');
require_once(Runtime::$fabRoot.'lib/PHPUnit/Framework.php');
require_once(Runtime::$fabRoot.'lib/PHPUnit/Runner/BaseTestRunner.php');

require_once(Runtime::$fabRoot.'lib/PHPUnit/Util/Timer.php');
require_once(Runtime::$fabRoot.'lib/PHPUnit/TextUI/ResultPrinter.php');
require_once(Runtime::$fabRoot.'lib/PHPUnit/TextUI/TestRunner.php');
//set_include_path($old);

class AutoTest extends Module
{
    private $tests=array();
    const TEST_DIR_PATTERN='tests';
    const TEST_FILE_PATTERN='*Test.php';
    
    public static $currentSuite=null;
    
    private function findTests($root, $prefix='/')
    {
        foreach(glob($root.'*', GLOB_ONLYDIR|GLOB_MARK) as $file)
        {
            $name=basename($file);
            
            if ($name===self::TEST_DIR_PATTERN)
                foreach(glob($root.$name.DIRECTORY_SEPARATOR.self::TEST_FILE_PATTERN) as $file)
            	   $this->tests[$prefix.self::TEST_DIR_PATTERN.'/'.basename($file)]=$file;
            else
                $files=$this->findTests($root.$name.DIRECTORY_SEPARATOR, $prefix.$name.'/');
        }
    }
    
    public function actionIndex()
    {
        $this->findTests(Runtime::$fabRoot);
        Template::Run('list.html', array('tests'=>$this->tests));
    }
    
    public function actionRun()
    {
        if (! $files=Utils::get($_POST['test'], false))
        {
        	echo "<p>Aucun test n'a �t� indiqu�</p>";
            return;
        }

        $tests=new PHPUnit_Framework_TestSuite('AutoTest des composants s�lectionn�s');
        $result = new PHPUnit_Framework_TestResult;
        $result->addListener(new SimpleTestListener);
        
        foreach((array) $files as $path)
        {
            $class=substr(basename($path), 0, -4); // /dir/CacheTest.php -> CacheTest

            debug && Debug::log('Ex�cution de %s', $path);

            require_once($path);

            //$test=new $class();
            $tests->addTest(new AutoTestSuite($class));
        }    
             
        // Run the tests.
        $tests->run($result);
        
        $successCount=$result->count()-$result->errorCount()-$result->failureCount()-$result->notImplementedCount()-$result->skippedCount();
        
        $p=round(100 * $successCount / $result->count());
        $e=round(100 * $result->errorCount() / $result->count());
        $f=round(100 * $result->failureCount() / $result->count());
        $n=round(100 * $result->notImplementedCount() / $result->count());
        $s=round(100 * $result->skippedCount() / $result->count());

        echo '<h1>Bilan des tests</h1>';
        echo '<ul>';
        echo '<li class="odd">Total : ', $result->count(), '</li>';
        echo '<li class="error">errors : ', $result->errorCount(), ' ('.$e.'%)</li>';
        echo '<li class="fail odd">fail : ', $result->failureCount(), ' ('.$f.'%)</li>';
        echo '<li class="incomplete">not implemented : ', $result->notImplementedCount(), ' ('.$n.'%)</li>';
        echo '<li class="skip odd">skip : ', $result->skippedCount(), ' ('.$s.'%)</li>';
        echo '<li class="pass">pass : ', $successCount, ' ('.$p.'%)</li>';
        echo '</ul>';
        
        echo '<div style="width:100%; border: 1px inset #000; background-color: green;float: left;">';
        echo '<div style="width:',$e,'%; background-color: red;height: 2em;float: left"></div>';
        echo '<div style="width:',$f,'%; background-color: darkred;height: 2em;float: left"></div>';
        echo '<div style="width:',$n,'%; background-color: grey;height: 2em;float: left"></div>';
        echo '<div style="width:',$s,'%; background-color: blue;height: 2em;float: left"></div>';
        echo '</div>';
    	
    }
}

class AutoTestSuite extends PHPUnit_Framework_TestSuite
{
    public function __construct($class)
    {
        $this->setName($class);
        $reflex = new ReflectionClass($class);

        foreach ($reflex->getMethods() as $method) 
        {
            $name=$method->getName();

            if (substr($name, 0, 8) == 'testfile')
            {    
                AutoTest::$currentSuite=$this;
                $method->invoke(new $class());
            }
            elseif (substr($name, 0, 4) == 'test')
            {    
                $test=new $class();
                $test->setName($name);
                $this->addTest($test);
            }
        }
    }    	
}

class SimpleTestListener implements PHPUnit_Framework_TestListener
{
    private $success=true;
    private $odd=false;
    
    public function startTestSuite(PHPUnit_Framework_TestSuite $suite)
    {
        static $first=true;
        
        if (strpos($suite->getName(), 'AutoTest')===false) echo '<li>';
        echo '<h1>',$suite->getName(),'</h1>';
        echo '<ul>';
        $this->odd=false;
        $first=false;
    }
 
    public function endTestSuite(PHPUnit_Framework_TestSuite $suite)
    {
        echo '</ul>';
        if (strpos($suite->getName(), 'AutoTest')!==false) echo '</li>';
    }

    public function startTest(PHPUnit_Framework_Test $test)
    {
        $this->success=true;
        $this->incomplete=false;
        $this->odd=!$this->odd;
    }
 
    public function endTest(PHPUnit_Framework_Test $test, $time)
    {
        if ($this->success)
            echo '<li class="', ($this->odd?'odd ':''), 'pass">', $test->getName(), '</li>';
    }
 
    public function addError(PHPUnit_Framework_Test $test, Exception $e, $time)
    {
        echo '<li class="', ($this->odd?'odd ':''), 'error">', $test->getName(), ' : ', htmlentities($e->getMessage()),'</li>';
        $this->success=false;
//        printf("Error while running test '%s'.\n", $test->getName());
//        echo '<strong style="color: red">', $e->getMessage(), '</strong>';
        //throw $e;
        //$this->failed++;
    }
 
    public function addFailure(PHPUnit_Framework_Test $test, PHPUnit_Framework_AssertionFailedError $e, $time)
    {
        $location=$e->getLocation();
        $line=$location['line'];
        
        if ($e instanceof AssertionNoDiffFailed)
        {
            echo '<li class="', ($this->odd?'odd ':''), 'fail">', $test->getName(), ' : ', htmlentities($e->getMessage()),
            '<div class="diff"><div id="div1">',htmlentities($e->expected),'</div><div id="div2">',htmlentities($e->result),'</div></div>',
            '<script>diff_divs("div1","div2")</script>',
             '</li>';
        }
        else
        {
            echo '<li class="', ($this->odd?'odd ':''), 'fail">', $test->getName(), ' : ', htmlentities($e->getMessage()),'</li>';
        }
        $this->success=false;
    }
 
    public function addIncompleteTest(PHPUnit_Framework_Test $test, Exception $e, $time)
    {
        $reason=($e->getMessage() ? $e->getMessage() : 'test incomplet');
        echo '<li class="', ($this->odd?'odd ':''), 'incomplete">', $test->getName(), ' : ',$reason, '</li>';
        $this->success=false;
    }
 
    public function addSkippedTest(PHPUnit_Framework_Test $test, Exception $e, $time)
    {
        echo '<li class="', ($this->odd?'odd ':''), 'skip">', $test->getName(), ' : ',$e->getMessage(), '</li>';
        $this->success=false;
    }
 
}

class AutoTestCase extends PHPUnit_Framework_TestCase
{
    /**
     * Supprime les 'blancs' pr�sents dans la chaine pass�e en param�tre.
     * 
     * @param string $string la chaine � normaliser
     * @return string
     */ 
    private function normalizeSpaces($string)
    {
        return preg_replace(array('~\s*\n\s*~','~\s+~'), '', $string);
    }

    private function getDiff($h1,$h2)
    {
        error_reporting(0);
        $old=set_include_path(Runtime::$fabRoot.'lib/');
        
        restore_error_handler(); // HACK: supprime le gestionnaire de fab � cause de Text_Diff (E_STRICT causes fatal error) 
        require_once(Runtime::$fabRoot.'lib/Text/Diff.php');
        require_once(Runtime::$fabRoot.'lib/Text/Diff/Renderer.php');
        require_once(Runtime::$fabRoot.'lib/Text/Diff/Renderer/unified.php');
        require_once(Runtime::$fabRoot.'lib/Text/Diff/Renderer/inline.php');
        $lines1=explode("\n", $h1);
        $lines2=explode("\n", $h2);
        
        $diff = new Text_Diff($lines1, $lines2);
        return $diff;
    }

//    public function assertThat($value, PHPUnit_Framework_Constraint $constraint, $message = '')
//    {
//        parent::assertThat($value, $constraint, $message.'yyy');
//    }
    
    function assertNoDiff($expected, $result, $message='Le r�sultat obtenu est diff�rent du r�sultat attendu')
    {
        if (true)
        {
        	$e=$this->normalizeSpaces($expected);
            $r=$this->normalizeSpaces($result);
        }
        else
        {
            $e=$expected;
            $r=$result;
        }
        if ($e !== $r)
            throw new AssertionNoDiffFailed($expected, $result, $message);
    }
    
    /**
     * Ex�cute chacun des test pr�sents dans le fichier testfile en appellant pour chaque
     * test le callback indiqu�.
     * 
     * Un fichier testfile est un moyen pratique d'ex�cuter plusieurs tests consistant 
     * � comparer le texte du r�sultat obtenu avec celui du r�sultat attendu.
     * 
     * Le format des fichiers testfile est inspir� de celui des fichiers phpt de php.
     * 
     * Le fichier contient plusieurs tests, s�par�s par une ligne commencant par "===="
     * (au moins quatre signes �gal).
     * 
     * Tout ce qui pr�c�de la premi�re s�paration est consid�r� comme du commentaire et
     * est ignor�. Cela permet de documenter le fichier (historique, num�ro de version, etc.)
     * 
     * Chaque test se compose de sections. Chaque section commence par une ligne de la forme
     * '--section--' (c'est-�-dire au moins deux tirets, le nom de la section puis au moins deux tirets)
     * et se termine au d�but de la section suivante ou � la fin du test.
     * 
     * Des espaces sont autoris�s avant et apr�s les deux s�ries d'au moins deux tirets. 
     * Le nom de la section n'est pas sensible � la casse des caract�res.
     * 
     * Dans chaque section, les espaces de d�but et de fin (espaces, tabulations, retours � la
     * ligne) sont ignor�s.
     * 
     * Les sections reconnues sont les suivantes :
     * 
     * - --test-- : optionnel, un libell� d�crivant le test effectu�
     * 
     * - --file-- : obligatoire, le source du test � ex�cuter. Il peut s'agir d'un bout de code
     * php, d'un template ou de n'importe quoi d'autre qui soit compatible avec le callback
     * utilis�.
     * 
     * - --expect-- : obligatoire : le r�sultat attendu.
     * 
     * - --expect exception ExceptionClassName-- : indique qu'on s'attend � ce que l'ex�cution du test g�n�re 
     * une exception. Si ExceptionClassName est indiqu�, l'exception g�n�r�e doit correspondre.
     * 
     * - --comment-- : permet de commenter le test, de l'expliquer. Cette section est ignor�e par le programme.
     * 
     * - --skip-- : permet d'ignorer un test. Le contenu de cette section n'est pas utilis�e par le programme,
     * mais vous pouvez y inclure des commentaires expliquant pourquoi le test est pass�.
     * 
     * @param string $path le path du fichier testfile � ex�cuter.
     * 
     * @param callback $callback la fonction callback � appeller pour chacun des tests du fichier. 
     * La fonction callback doit prendre en seul param�tre : le contenu de la section --file-- du fichier
     * 
     * @return void
     */
    function runTestFile($path, $callback)
    {
        // V�rifie le callback
        if (! is_callable($callback))
            throw new Exception('Le callback indiqu� ne peut pas �tre appell�');
            
        // Charge le fichier de tests
        $tests=file_get_contents($path);
        $tests=preg_split('~\s*={4,}\s*~s', $tests);
        
        // Ignore tout ce qui pr�c�de la premi�re ligne de s�paration des tests
        unset($tests[0]);

        $testSuite=new PHPUnit_Framework_TestSuite('Fichier de test ' . basename($path));
        
        // Ex�cute tous les tests
//        echo '<li>Ex�cution du fichier testfile ', $path, '<ul>';
        foreach ($tests as $test)
        {
            // S�pare les diff�rentes sections du test
            $t=preg_split('~^\s*-{2,}\s*([a-zA-Z ]+)\s*-{2,}\s*~ms', $test, -1, PREG_SPLIT_DELIM_CAPTURE);
            
            // Le premier �l�ment doit �tre vide, sinon, c'est qu'on a du texte entre la ligne de '===' et la premi�re section
            if (trim($t[0]) !== '')
                throw new Exception("Erreur dans le fichier de test '$path' : texte '$t[0]' trouv� apr�s la ligne '====='");
            unset($t[0]);
            
            // on a maintenant un tableau dont les indices impairs contiennent les noms de section et les pairs les valeurs
            $test=array();
            $name='';
            foreach ($t as $i=>$section)
            {
                // Stocke et v�rifie le nom de la section en cours 
                if ($i % 2 == 1)
                {
                    $name=strtolower($section);
                    if (strtolower(substr($section,0,6))==='expect')
                    {
                    	if ('' !== $exception=trim(substr($section,6)))
                        {
                            $test['exception']=$exception;
                        	$name='expect';
                        }
                    }
                    
                    if(isset($test[$name]))
                        throw new Exception("Erreur dans le fichier de test '$path', section '$name' r�p�t�e pour l'un des test");
                        
                    if (! in_array($name,array('test','file','expect','comment','skip')))
                        throw new Exception("Erreur dans le fichier de test '$path', section '$name' inconnue");
                } 
                
                // Stocke le contenu de la section en cours
                else
                {
                    $test[$name]=$section;
                }
            }
            
            // Ignore les tests vides ne contenant aucune section (par exemple � la fin du fichier)
            if (count($test)===0) continue;
            
            // V�rifie que les sections obligatoires sont pr�sentes
            if (!isset($test['skip']))
                foreach(array('file','expect') as $name)
                    if (! isset($test[$name]))
                        throw new Exception("Erreur dans le fichier de test '$path', section '$name' absente dans l'un des tests");

            $testCase=new AutoTestFile($test, $callback);                        
            $testSuite->addTest($testCase);
                                        
        }
        echo '</ul>';
        AutoTest::$currentSuite->addTest($testSuite);
    }
}

class AssertionNoDiffFailed extends PHPUnit_Framework_AssertionFailedError
{
	public $expected;
    public $result;
    
    public function __construct($expected,$result, $message)
    {
        parent::__construct($message);
        $this->expected=$expected;
        $this->result=$result;	
    }
}

class AutoTestFile extends AutoTestCase
{
    private $callback=null;
    private $test=null;
    
    public function __construct(Array $test, $callback)
    {
    	$this->test=$test;
        $this->callback=$callback;
        $this->setName('runit');
    }
//    public function ssrun(PHPUnit_Framework_TestResult $result = NULL)
    public function runit()
    {
        $test=$this->test;
        // Ex�cute le test
        if (isset($test['skip']))
        {
            $this->markTestSkipped( isset($test['skip']) && $test['skip']!=='' ? $test['skip'] : '--skip-- indiqu�');
        }
        else
        {
//            echo '<li>',isset($test['test']) ? $test['test'] : $test['file'];
            // on attend un �chec
            if (isset($test['exception']))
            {
                try
                {
                    $result=call_user_func($this->callback, $test['file']);
                }
                catch (Exception $e)
                {
                    // V�rifie que c'est bien une exception du bon type
                    if ((get_class($e) !== $test['exception']) && (! is_subclass_of($e, $test['exception'])))
                        $this->fail("Le code a g�n�r� une exception de type '".get_class($e)."' au lieu d'une exception de type '".$test['exception']."'"); 

                    // V�rifie que les mots attendus figurent dans le message
                    if (trim($test['expect'])==='') return;
                    $diff=array_diff
                    (
                        preg_split('/\s+/', Utils::convertString($test['expect'])),
                        preg_split('/\s+/', Utils::convertString($e->getMessage()))
                    );
                    if (count($diff))
                        $this->fail("Le(s) mot(s) '".implode(', ', $diff)."' ne figure(nt) pas dans le message de l'exception obtenue : ".$e->getMessage());

                    // Tout est OK
                    return;
                }
                // Aucune exception n'a �t� g�n�r�e
                $this->fail("Le code aurait d� g�n�rer une exception de type ".$test['exception']);	
            }

            // On attend un succ�s
            else
            {
                $result=call_user_func($this->callback, $test['file']);
                if (isset($test['test']))
                    $this->assertNoDiff($test['expect'],$result, $test['test']);
                else
                    $this->assertNoDiff($test['expect'],$result);
            }
        }           
    }

    public function getName()
    {
        $test=$this->test;
        return isset($test['test']) ? $test['test'] : $test['file'];
    }
    
}

?>
