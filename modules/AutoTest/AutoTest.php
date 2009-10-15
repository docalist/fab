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
        $searchpath = Config::get('searchpath');

        foreach($searchpath as $path)
        {
            $save = $path;
            if (strncasecmp($path, 'fab:', 4)===0)
            {
                $prefix = substr($path, 4);
                $root = Runtime::$fabRoot;
            }
            else
            {
                $prefix = $path;
                $root = Runtime::$root;
            }
            $prefix='/' . trim($prefix, '/') . '/';
            $path = Utils::makePath($root, $prefix);

            if (! is_dir($path))
                echo "Warning : le répertoire '$save' indiqué dans le fichier de confiruation AutoTest.config n'existe pas.";
            else
                $this->findTests($path, $prefix);
        }
        Template::Run('list.html', array('tests'=>$this->tests));
    }

    public function actionRun()
    {
        if (! $files=Utils::get($_POST['test'], false))
        {
        	echo "<p>Aucun test n'a été indiqué</p>";
            return;
        }

        $tests=new PHPUnit_Framework_TestSuite('AutoTest des composants sélectionnés');
        $result = new PHPUnit_Framework_TestResult;
        $result->addListener(new SimpleTestListener);

        $reportDir='';

        if (extension_loaded('xdebug') && Utils::get($_POST['codecoverage'], false)==='1')
        {
            $reportDir=Runtime::$webRoot.'codeCoverage/';
            if (!is_dir($reportDir))
            {
                echo "<p>Création du répertoire $reportDir pour le rapport de couverture de code...</p>";
            	if (!@mkdir($reportDir))
                {
                	throw new Exception("Impossible de créer le répertoire $reportDir pour le rapport de couverture de code");
                }
            }
            $reportUrl=Runtime::$realHome.'codeCoverage/index.html';
            $result->collectCodeCoverageInformation(true);
        }

        set_time_limit(0);
        while(@ob_end_flush());
        PHPUnit_Util_Filter::addFileToFilter(__FILE__);

        foreach((array) $files as $path)
        {
            $class=substr(basename($path), 0, -4); // /dir/CacheTest.php -> CacheTest

            debug && Debug::log('Exécution de %s', $path);

            require_once($path);
            PHPUnit_Util_Filter::addFileToFilter($path);
            $tests->addTest(new AutoTestSuite($class));
        }

        // Run the tests.
        $tests->run($result);

        $result->flushListeners();

        $successCount=$result->count()-$result->errorCount()-$result->failureCount()-$result->notImplementedCount()-$result->skippedCount();

        echo '<h1 style="clear: both;">Bilan des tests</h1>';
        if ($result->count()==0)
        {
            echo '<p>Aucun test n\'a été exécuté</p>';
        }
        else
        {
            $p=round(100 * $successCount / $result->count());
            $e=round(100 * $result->errorCount() / $result->count());
            $f=round(100 * $result->failureCount() / $result->count());
            $n=round(100 * $result->notImplementedCount() / $result->count());
            $s=round(100 * $result->skippedCount() / $result->count());


            echo '<div style="width:50%; height: 2em;border: 1px inset #000; background-color: green;">';
            echo '<div style="width:',$e,'%; background-color: red;height: 2em;float: left"></div>';
            echo '<div style="width:',$f,'%; background-color: darkred;height: 2em;float: left"></div>';
            echo '<div style="width:',$n,'%; background-color: grey;height: 2em;float: left"></div>';
            echo '<div style="width:',$s,'%; background-color: blue;height: 2em;float: left"></div>';
            echo '</div>';

            echo '<ul>';
            echo '<li class="odd">Total : ', $result->count(), '</li>';
            echo '<li class="error">errors : ', $result->errorCount(), ' ('.$e.'%)</li>';
            echo '<li class="fail odd">fail : ', $result->failureCount(), ' ('.$f.'%)</li>';
            echo '<li class="incomplete">not implemented : ', $result->notImplementedCount(), ' ('.$n.'%)</li>';
            echo '<li class="skip odd">skip : ', $result->skippedCount(), ' ('.$s.'%)</li>';
            echo '<li class="pass">pass : ', $successCount, ' ('.$p.'%)</li>';
            echo '</ul>';
        }
        if ($reportDir)
        {
            echo "<hr /><h1>Generation du rapport de couverture de code</h1>";
            PHPUnit_Util_Report::render($result, $reportDir);
            echo '<p>Le rapport a été généré dans le répertoire ', $reportDir, 'du serveur<br />';
            echo '<a href="'.$reportUrl.'">Cliquez sur ce lien pour le consulter</a></p>';
        }
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
        echo '<h1>',htmlentities($suite->getName()),'</h1>';
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
            echo '<li class="', ($this->odd?'odd ':''), 'pass">Ok. ', htmlentities($test->getName()), '</li>';
//        ob_end_flush();
        flush();
    }

    public function addError(PHPUnit_Framework_Test $test, Exception $e, $time)
    {
        echo '<li class="', ($this->odd?'odd ':''), 'error">Error. ', htmlentities($test->getName()), ' : ', htmlentities($e->getMessage());
//        echo '<pre>';
//        debug_print_backtrace();
//        echo '</pre>';
        echo '</li>';
        $this->success=false;

    }

    public function addFailure(PHPUnit_Framework_Test $test, PHPUnit_Framework_AssertionFailedError $e, $time)
    {
        if ($e instanceof AssertionNoDiffFailed)
        {
            echo
                '<li class="', ($this->odd?'odd ':''), 'fail">',
                    'Fail. ', htmlentities($test->getName()), /*' : ', htmlentities($e->getMessage()),*/
                    '<div class="diff">',
                        '<div id="div1">',htmlentities($e->expected),'</div>',
                        '<div id="div2">',htmlentities($e->result),'</div>',
                    '</div>',
                    '<script>diff_divs("div1","div2")</script>',
                 '</li>';
             echo 'Source du test : <pre>', htmlentities($test->test['file']), '</pre>';
        }
        else
        {
            echo '<li class="', ($this->odd?'odd ':''), 'fail">Fail. ', htmlentities($test->getName()), ' : ', htmlentities($e->getMessage()),'</li>';
        }
        $this->success=false;
    }

    public function addIncompleteTest(PHPUnit_Framework_Test $test, Exception $e, $time)
    {
        $reason=($e->getMessage() ? $e->getMessage() : 'test incomplet');
        echo '<li class="', ($this->odd?'odd ':''), 'incomplete">Incomplet. ', htmlentities($test->getName()), ' : ',htmlentities($reason), '</li>';
        $this->success=false;
    }

    public function addSkippedTest(PHPUnit_Framework_Test $test, Exception $e, $time)
    {
        echo '<li class="', ($this->odd?'odd ':''), 'skip">Skip. ', htmlentities($test->getName()), ' : ',htmlentities($e->getMessage()), '</li>';
        $this->success=false;
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

//    public function assertThat($value, PHPUnit_Framework_Constraint $constraint, $message = '')
//    {
//        parent::assertThat($value, $constraint, $message.'yyy');
//    }

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
            throw new AssertionNoDiffFailed($expected, $result, $message);
    }

    /**
     * Exécute chacun des test présents dans le fichier testfile en appellant pour chaque
     * test le callback indiqué.
     *
     * Un fichier testfile est un moyen pratique d'exécuter plusieurs tests consistant
     * à comparer le texte du résultat obtenu avec celui du résultat attendu.
     *
     * Le format des fichiers testfile est inspiré de celui des fichiers phpt de php.
     *
     * Le fichier contient plusieurs tests, séparés par une ligne commencant par "===="
     * (au moins quatre signes égal).
     *
     * Tout ce qui précède la première séparation est considéré comme du commentaire et
     * est ignoré. Cela permet de documenter le fichier (historique, numéro de version, etc.)
     *
     * Chaque test se compose de sections. Chaque section commence par une ligne de la forme
     * '--section--' (c'est-à-dire au moins deux tirets, le nom de la section puis au moins deux tirets)
     * et se termine au début de la section suivante ou à la fin du test.
     *
     * Des espaces sont autorisés avant et après les deux séries d'au moins deux tirets.
     * Le nom de la section n'est pas sensible à la casse des caractères.
     *
     * Dans chaque section, les espaces de début et de fin (espaces, tabulations, retours à la
     * ligne) sont ignorés.
     *
     * Les sections reconnues sont les suivantes :
     *
     * - --test-- : optionnel, un libellé décrivant le test effectué
     *
     * - --file-- : obligatoire, le source du test à exécuter. Il peut s'agir d'un bout de code
     * php, d'un template ou de n'importe quoi d'autre qui soit compatible avec le callback
     * utilisé.
     *
     * - --expect-- : obligatoire : le résultat attendu.
     *
     * - --expect exception ExceptionClassName-- : indique qu'on s'attend à ce que l'exécution du test génère
     * une exception. Si ExceptionClassName est indiqué, l'exception générée doit correspondre.
     *
     * - --comment-- : permet de commenter le test, de l'expliquer. Cette section est ignorée par le programme.
     *
     * - --skip-- : permet d'ignorer un test. Le contenu de cette section n'est pas utilisée par le programme,
     * mais vous pouvez y inclure des commentaires expliquant pourquoi le test est passé.
     *
     * @param string $path le path du fichier testfile à exécuter.
     *
     * @param callback $callback la fonction callback à appeller pour chacun des tests du fichier.
     * La fonction callback doit prendre en seul paramètre : le contenu de la section --file-- du fichier
     *
     * @return void
     */
    function runTestFile($path, $callback)
    {
        // Vérifie le callback
        if (! is_callable($callback))
            throw new Exception('Le callback indiqué ne peut pas être appellé');

        // Charge le fichier de tests
        //$tests=file_get_contents($path);
        $tests=file($path);
        foreach($tests as $line=>& $data)
        {
            if (preg_match('~\s*={4,}\s*~s', $data))
                $data .= "\n--line--\n".($line+2)."\n";
        }
        $tests=implode('',$tests);
        $tests=preg_split('~\s*={4,}\s*~s', $tests);

        // Ignore tout ce qui précède la première ligne de séparation des tests
        unset($tests[0]);

        if (is_string($callback)) $h=$callback; else $h=$callback[1];
        $testSuite=new PHPUnit_Framework_TestSuite($h.' - fichier de test ' . basename($path));

        // Initialise tous les tests
        foreach ($tests as $test)
        {
            // Sépare les différentes sections du test
            $t=preg_split('~^\s*-{2,}\s*([a-zA-Z ]+)\s*-{2,}\s*~ms', $test, -1, PREG_SPLIT_DELIM_CAPTURE);

            // Le premier élément doit être vide, sinon, c'est qu'on a du texte entre la ligne de '===' et la première section
            if (trim($t[0]) !== '')
                throw new Exception("Erreur dans le fichier de test '$path' : texte '$t[0]' trouvé après la ligne '====='");
            unset($t[0]);

            // on a maintenant un tableau dont les indices impairs contiennent les noms de section et les pairs les valeurs
            $test=array();
            $name='';
            foreach ($t as $i=>$section)
            {
                // Stocke et vérifie le nom de la section en cours
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
                        throw new Exception("Erreur dans le fichier de test '$path', section '$name' répétée pour l'un des test");

                    if (! in_array($name,array('test','file','expect','comment','skip', 'line')))
                        throw new Exception("Erreur dans le fichier de test '$path', section '$name' inconnue");
                }

                // Stocke le contenu de la section en cours
                else
                {
                    $test[$name]=trim($section);
                }
            }

            // Ignore les tests vides ne contenant aucune section (par exemple à la fin du fichier)
            if (count($test)===0) continue;
            if (count($test)==1 && isset($test['line'])) continue;

            // Vérifie que les sections obligatoires sont présentes
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
    public $test=null;

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
        // Exécute le test
        if (isset($test['skip']))
        {
            $this->markTestSkipped( isset($test['skip']) && $test['skip']!=='' ? $test['skip'] : '--skip-- indiqué');
        }
        else
        {
//            echo '<li>',isset($test['test']) ? $test['test'] : $test['file'];
            // on attend un échec
            if (isset($test['exception']))
            {
                try
                {
                    $result=call_user_func($this->callback, $test['file']);
                }
                catch (Exception $e)
                {
                    // Vérifie que c'est bien une exception du bon type
                    if ((get_class($e) !== $test['exception']) && (! is_subclass_of($e, $test['exception'])))
                        $this->fail("Le code a généré une exception de type '".get_class($e)."' au lieu d'une exception de type '".$test['exception']."'");

                    // Vérifie que les mots attendus figurent dans le message
                    if (trim($test['expect'])==='') return;
                    $diff=array_diff
                    (
                        preg_split('/\s+/', Utils::convertString($test['expect'])),
                        preg_split('/\s+/', Utils::convertString($e->getMessage()))
                    );
                    if (count($diff))
                        $this->fail("Le(s) mot(s) '".implode(', ', $diff)."' ne figure(nt) pas dans le message de l'exception obtenue : ".$e->getMessage());
//echo 'exception obtenue : [', $e->getMessage(), ']', "\n";
                    // Tout est OK
                    return;
                }
                // Aucune exception n'a été générée
                $this->fail("Le code aurait dû générer une exception de type ".$test['exception']);
            }

            // On attend un succès
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

    public function getName($withDataSet = TRUE)
    {
        $test=$this->test;
        $file=$test['file'];
        $expect=$test['expect'];
        $line=$test['line'];

        if (isset($test['test'])) // le test a un titre
        {
            $h='Ligne '.$line.', '.$test['test'];
            if (strpos($file, "\n")===false) // le source n'a qu'une ligne, on l'ajoute au titre
                $h .= ' : ' . $file . ' -> ' . $expect;

        }
        else
        {
        	$h=preg_replace('~\s+~',' ', $file);
            if (strlen($h)>75)
            {
                $h=wordwrap($file, 75, "\n", true);
                $h=substr($file, 0, strpos($file, "\n")) . ' ...';
            }
            $h='Ligne '.$line.', '.$h;
        }
        return $h;
    }

}

?>
