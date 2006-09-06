<?php
require_once ('../lib/simpletest/reporter.php');

class MyReporter extends HtmlReporter
{

    function __construct()
    {
        $this->HtmlReporter();
    }

    function paintPass($message)
    {
        parent :: paintPass($message);
        print "<span class=\"pass\">Pass</span>: ";
        $breadcrumb = $this->getTestList();
        array_shift($breadcrumb);
        array_shift($breadcrumb);
        print implode("::", $breadcrumb);
        print " -&gt; $message<br />\n";
    }

    function paintFail($message)
    {
        parent :: paintFail($message);
        print "<span class=\"fail\">Fail</span>: ";
        $breadcrumb = $this->getTestList();
        array_shift($breadcrumb);
        array_shift($breadcrumb);
        print implode("::", $breadcrumb);
        print " -&gt; ".$this->_htmlEntities($message)."<br />\n";
    }

    function _getCss()
    {
        return parent :: _getCss().' .pass { color: green; }';
    }
}

class AedReporter extends SimpleReporter
{
    /**
     *    Starts the display with no results in.
     *    @access public
     */
    function AedReporter()
    {
        $this->SimpleReporter();
    }

    /**
     *    Paints the start of a group test. Will also paint
     *    the page header and footer if this is the
     *    first test. Will stash the size if the first
     *    start.
     *    @param string $test_name   Name of test that is starting.
     *    @param integer $size       Number of test cases starting.
     *    @access public
     */
    function paintGroupStart($test_name, $size)
    {
        parent :: paintGroupStart($test_name, $size);
//        echo "paintGroupStart($test_name,$size)\n";
    }

    /**
     *    Paints the end of a group test. Will paint the page
     *    footer if the stack of tests has unwound.
     *    @param string $test_name   Name of test that is ending.
     *    @param integer $progress   Number of test cases ending.
     *    @access public
     */
    function paintGroupEnd($test_name)
    {
        parent :: paintGroupEnd($test_name);
//        echo "pauntGroupEnd($test_name)\n";
    }

    /**
     *    Paints the start of a test case. Will also paint
     *    the page header and footer if this is the
     *    first test. Will stash the size if the first
     *    start.
     *    @param string $test_name   Name of test that is starting.
     *    @access public
     */
    function paintCaseStart($test_name)
    {
        parent :: paintCaseStart($test_name);
        echo "<h2>test : $test_name</h2>\n";
    }

    /**
     *    Paints the end of a test case. Will paint the page
     *    footer if the stack of tests has unwound.
     *    @param string $test_name   Name of test that is ending.
     *    @access public
     */
    function paintCaseEnd($test_name)
    {
        parent :: paintCaseEnd($test_name);
    }

    /**
     *    Paints the start of a test method.
     *    @param string $test_name   Name of test that is starting.
     *    @access public
     */
    function paintMethodStart($test_name)
    {
        parent :: paintMethodStart($test_name);
        echo "<h3>$test_name</h3>\n";
        echo "<ul>\n";
    }

    /**
     *    Paints the end of a test method. Will paint the page
     *    footer if the stack of tests has unwound.
     *    @param string $test_name   Name of test that is ending.
     *    @access public
     */
    function paintMethodEnd($test_name)
    {
        parent :: paintMethodEnd($test_name);
        echo "</ul> <!-- fin de $test_name -->\n";
    }

    /**
     *    Paints the test document header.
     *    @param string $test_name     First test top level
     *                                 to start.
     *    @access public
     *    @abstract
     */
    function paintHeader($test_name)
    {
        parent :: paintHeader($test_name);
//        echo "paintHeader($test_name)\n";
        echo "<html>
<head>
<style>
body
{
   font-size: 76%;
   font-family: arial;
   background-color: #eee;
}
h2
{
   color: darkblue;
}
h3
{
   color: blue;
}
li
{
   font-family: 'courier new';
}
li.pass
{
   color: green;
}
li.fail
{
   color: red;
}
p.error
{
    color: red;
    border: 1px solid red;
    margin: 0;
    padding: 0.3em;
}
</style>
</head>
<body>
";
    }

    /**
     *    Paints the test document footer.
     *    @param string $test_name        The top level test.
     *    @access public
     *    @abstract
     */
    function paintFooter($test_name)
    {
        parent :: paintFooter($test_name);
//        echo "paintFooter($test_name)\n";
            $colour = ($this->getFailCount() + $this->getExceptionCount() > 0 ? "red" : "green");
            print "<div style=\"";
            print "padding: 8px; margin-top: 1em; background-color: $colour; color: white;";
            print "\">";
            print $this->getTestCaseProgress() . "/" . $this->getTestCaseCount();
            print " test cases complete:\n";
            print "<strong>" . $this->getPassCount() . "</strong> passes, ";
            print "<strong>" . $this->getFailCount() . "</strong> fails and ";
            print "<strong>" . $this->getExceptionCount() . "</strong> exceptions.";
echo "</body>
</html>";
    }

    /**
     *    Increments the pass count.
     *    @param string $message        Message is ignored.
     *    @access public
     */
    function paintPass($message)
    {
        parent::paintPass($message);
        echo "   <li class=\"pass\">$message</li>\n";
    }

    /**
     *    Increments the fail count.
     *    @param string $message        Message is ignored.
     *    @access public
     */
    function paintFail($message)
    {
        parent::paintFail($message);
        echo "   <li class=\"fail\">$message</li>\n";
    }

    /**
     *    Deals with PHP 4 throwing an error or PHP 5
     *    throwing an exception.
     *    @param string $message    Text of error formatted by
     *                              the test case.
     *    @access public
     */
    function paintError($message)
    {
        parent::paintError($message);
        echo '<p class="error">' . $message . '</p>';
    }

    /**
     *    Paints a simple supplementary message.
     *    @param string $message        Text to display.
     *    @access public
     */
    function paintMessage($message)
    {
        parent::paintMessage($message);
        echo "paintMessage($message)\n";
    }

    /**
     *    Paints a formatted ASCII message such as a
     *    variable dump.
     *    @param string $message        Text to display.
     *    @access public
     */
    function paintFormattedMessage($message)
    {
        parent::paintFormattedMessage($message);
        echo "paintFormattedMessage($message)\n";
    }

}
?>