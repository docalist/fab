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
        Template::Run('list.xhtml', array('tests'=>$this->tests));
    }
    
    public function actionRun()
    {
        if (! $files=Utils::get($_POST['test'], false))
        {
        	echo "<p>Aucun test n'a été indiqué</p>";
            return;
        }
        foreach((array) $files as $path)
        {
            $class=substr(basename($path), 0, -4); // /dir/CacheTest.php -> CacheTest

            debug && Debug::log('Exécution de %s', $path);

            require_once($path);

            $test=new $class();
            $suite = new PHPUnit_Framework_TestSuite($class);
             
            $result = new PHPUnit_Framework_TestResult;
            $result->addListener(new SimpleTestListener);
             
            // Run the tests.
            $suite->run($result);
            
            echo '<p>', 
                 ' Total:' , $result->count(),
                 ', pass: ', $result->count()-$result->failureCount(),
                 ', fail: ', $result->failureCount(),
                 '</p>';
        }

    	
    }
}

class SimpleTestListener implements PHPUnit_Framework_TestListener
{
    private $failed=0;
    private $odd=false;
    private $incomplete=false;
    
    public function addError(PHPUnit_Framework_Test $test, Exception $e, $time)
    {
        printf("Error while running test '%s'.\n", $test->getName());
        throw $e;
    }
 
    public function addFailure(PHPUnit_Framework_Test $test, PHPUnit_Framework_AssertionFailedError $e, $time)
    {
        $location=$e->getLocation();
        $line=$location['line'];
        
        if ($e instanceof AssertionNoDiffFailed)
        {
            echo '<li class="', ($this->odd?'odd ':''), 'fail">', $test->getName(), '(), ligne ', $line, ': ', htmlentities($e->getMessage()),
            '<div class="diff"><div id="div1">',$e->expected,'</div><div id="div2">',$e->result,'</div></div>',
            '<script>diff_divs("div1","div2")</script>',
             '</li>';
        }
        else
        {
            echo '<li class="', ($this->odd?'odd ':''), 'fail">', $test->getName(), '(), ligne ', $line, ': ', htmlentities($e->getMessage()),'</li>';
        }
        $this->failed++;
        $this->odd=!$this->odd;
    }
 
    public function addIncompleteTest(PHPUnit_Framework_Test $test, Exception $e, $time)
    {
//        printf("Test '%s' is incomplete.\n", $test->getName());
        $this->incomplete=($e->getMessage() ? $e->getMessage() : 'test incomplet');
    }
 
    public function addSkippedTest(PHPUnit_Framework_Test $test, Exception $e, $time)
    {
        printf("Test '%s' has been skipped.\n", $test->getName());
    }
 
    public function startTest(PHPUnit_Framework_Test $test)
    {
        $this->failed=0;
        $this->incomplete=false;
        debug && Debug::log('Test : %s()', $test->getName());
    }
 
    public function endTest(PHPUnit_Framework_Test $test, $time)
    {
        if ($this->failed==0)
        {
            if($this->incomplete)
                echo '<li class="', ($this->odd?'odd ':''), 'pass incomplete">', $test->getName(), '() ',$this->incomplete, '</li>';
            else
                echo '<li class="', ($this->odd?'odd ':''), 'pass">', $test->getName(), '()</li>';

            $this->odd=!$this->odd;
        }
    }
 
    public function startTestSuite(PHPUnit_Framework_TestSuite $suite)
    {
        echo '<h1>',$suite->getName(), '</h1>';
        echo '<ul>';
        $this->odd=true;
    }
 
    public function endTestSuite(PHPUnit_Framework_TestSuite $suite)
    {
        echo '</ul>';
    }
}

class AutoTestCase extends PHPUnit_Framework_TestCase
{
    /**
     * Supprime les 'blancs' présents dans la chaine passée en paramètre.
     * 
     * @param string $string la chaine à normaliser
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
        
        restore_error_handler(); // HACK: supprime le gestionnaire de fab à cause de Text_Diff (E_STRICT causes fatal error) 
        require_once(Runtime::$fabRoot.'lib/Text/Diff.php');
        require_once(Runtime::$fabRoot.'lib/Text/Diff/Renderer.php');
        require_once(Runtime::$fabRoot.'lib/Text/Diff/Renderer/unified.php');
        require_once(Runtime::$fabRoot.'lib/Text/Diff/Renderer/inline.php');
        $lines1=explode("\n", $h1);
        $lines2=explode("\n", $h2);
        
        $diff = new Text_Diff($lines1, $lines2);
        return $diff;
    }
    
    function assertNoDiff($expected, $result, $message='Le résultat obtenu est différent du résultat attendu')
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
        {
            
            throw new AssertionNoDiffFailed($expected, $result, $message);
//        	$this->fail('Le résultat obtenu est différent du résultat attendu');
        }
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
?>
