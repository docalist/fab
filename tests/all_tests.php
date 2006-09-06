<?php
$what=0;
if (isset($_REQUEST['all']))
{
    $what=1; // tous les tests   
}
if (isset($_REQUEST['selected']))
{
    $t=@$_REQUEST['t'];
    $what=2; // tests s�lectionn�s
    if (count($t)==0) $what=1;
}
$showpass=@$_REQUEST['showpass'];

if ($what)
{
    error_reporting(E_ALL);
    require_once('../lib/simpletest/unit_tester.php');
    require_once('../lib/simpletest/reporter.php');
    require_once('MyReporter.class.php');
    
    if ($what==1)
    {
        $test = new GroupTest('Ex�cution de tous les tests');
        foreach(glob("*Test.php", GLOB_NOSORT) as $file)
        {
            $test->addTestFile($file);
        }
    }
    else
    {
        $test = new GroupTest('Ex�cution des tests s�lectionn�s');
        foreach($t as $file)
        {
            $test->addTestFile($file);
        }
    };
    
    if ($showpass)
        $test->run(new AedReporter());
    else
        $test->run(new HtmlReporter());
            
    exit();
}
?>
<html>
<head></head>
<body>
    <h1>Tests de r�gression</h1>
    <form action="" method="GET">
        <fieldset style="float: left;">
            <legend>S�lectionnez les test � ex�cuter</legend>
            <input type="submit" name="all" value="Ex�cuter tous les tests" />
            <br />
            <br />
            <?php
                $i=0;
                foreach(glob("*Test.php", GLOB_NOSORT) as $file)
                {   
                    ?><input 
                        type="checkbox" 
                        name="t[]" 
                        id="<?php echo "file$i" ?>"
                        value="<?php echo $file ?>" />
                    <label for="<?php echo "file$i" ?>"><?php echo $file ?></label>
                    <br /> <?php
                    $i++;
                }   
            ?>
            <br />
            <input type="submit" name="selected" value="Ex�cuter les tests s�lectionn�s" />
        </fieldset>
        <fieldset style="float: left;">
            <legend>Options</legend>
            <div>
                <input type="checkbox" name="showpass" id="showpass" value="1" />
                <label for="showpass">Afficher �galement les tests r�ussis</label>
            </div>
        </fieldset>    
    </form>
</body>
</html>