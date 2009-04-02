<?php
/**
 * @package     fab
 * @subpackage  debug
 * @author 		Daniel Ménard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id$
 */

/**
 * Fonctions de débogage
 *
 * @package     fab
 * @subpackage  debug
 */
class Debug
{
    public static $log=array();

    public static $log2=array();

    const LOG=0, NOTICE=1, WARNING=2;
    private static function logMessage($level, $args)
    {
        if (count($args)>1)
            $h=call_user_func_array(array('self','sprintfColor'), $args);
        else
            $h=$args[0];
        self::$log[]=array(Utils::callLevel()-2, Utils::callerClass(3), $level, $h);

//            $args[0].=' (%s)';
//            $args[]=$log[0].'-'.$log[1];
            $json='';
            foreach($args as $i=>$arg)
            {
                if ($i) $json.=',';
                $json.=json_encode(Utils::utf8Encode($arg));
            }
        self::$log2[]=array(Utils::callLevel()-2, Utils::callerClass(3), $level, $json);
    }

    public static function log()
    {
        $t=func_get_args();
        self::logMessage(self::LOG, $t);
    }

    public static function notice()
    {
        $t=func_get_args();
        self::logMessage(self::NOTICE, $t);
    }

    public static function warning()
    {
        $t=func_get_args();
        self::logMessage(self::WARNING, $t);
    }

    public static function sprintfColor()
    {
        $t=func_get_args();
        $format=array_shift($t);
        foreach($t as &$value)
        	$value=Debug::dump($value);
        return vsprintf($format, $t);
    }

    public static function dump($var, $sortkeys=true)
    {
        static $id=0;
        static $level=0;
        static $seen=array();

        if (! isset($var) || is_null($var))
            return '<span class="debugDumpNull">null</span>';

        if (is_string($var))
        {
            $h=str_replace
            (
                array(' ', "\n", "\t", '<', '>'),
                array('·', '\n', '&rarr;', '&lt;','&gt;'),
                $var
            );
            return '<span class="debugDumpString" title="type: string; len: '.strlen($var).'">'
                .   ($h=='' ? '(chaine vide)': $h)
                .   '</span>';
        }

        if (is_bool($var))
            return '<span class="debugDumpBool" title="type: boolean">'.($var?'true':'false').'</span>';

        if (is_int($var))
            return '<span class="debugDumpInt" title="type: integer">'.$var.'</span>';

        if (is_float($var))
            return '<span class="debugDumpInt" title="type: float">'.$var.'</span>';

        if (is_array($var))
        {
            if (count($var)==0)
                return '<span class="debugDumpArray" title="type: array; count: 0">array()</span>';

            $h='<span class="debugDumpArray" title="type: array; count: '.count($var).'">';
            $h.='<a href="#" onclick="debugToggle(\'dumpvar'.$id.'\');return false;">array&nbsp;...</a>';
            $h.='</span>';

            $h.='<div class="debugDumpArrayItems" id="dumpvar'.$id.'" style="display:'.($level==0?'block':'block').';">';
            $id++;
            $level++;
            $seen[]=&$var;
            if ($sortkeys) uksort($var, 'strnatcasecmp');
            foreach($var as $key=>$value)
                $h.='<span class="debugDumpArrayKey">'.self::dump($key, $sortkeys) . '</span> =&gt; ' . self::dump($value,$sortkeys) . '<br />';
            array_pop($seen);
            $level--;
            $h.='<div class="debugDumpArrayItemsEnd"></div>';
            $h.='</div>';
            return $h;
        }

        if (is_object($var))
        {
            $h='<span class="debugDumpObject">';
            $h.='<a href="#" onclick="debugToggle(\'dumpvar'.$id.'\');return false;">Object('.get_class($var).')&nbsp;...</a>';
            $h.='</span>';

            $h.='<div class="debugDumpObjectItems" id="dumpvar'.$id.'" style="display:'.($level==0?'block':'none').';">';

            if (in_array($var, $seen, true))
                $h.='***récursion***';
            else
            {
                $id++;
                $level++;
                $seen[]=&$var;

    //            uksort($var, 'strnatcasecmp');
                try // ne marche pas s'il s'agit d'un objet COM (exemple : selection)
                {
                    $t=get_object_vars($var);
                    if ($t) foreach($t as $key=>$value)
                        $h.='<span class="debugDumpObjectKey">'.self::dump('$'.$key, $sortkeys) . '</span> =&gt; ' . self::dump($value,$sortkeys) . '<br />';
                }
                catch (Exception $e)
                {

                }
                $level--;
                array_pop($seen);
            }
            $h.='<div class="debugDumpObjectItemsEnd"></div>';
            $h.='</div>';
            return $h;

        }
        return 'type non géré dans dump : ' . var_export($var,1);

    }
    private static function showLog(&$i=0)
    {
        $nb=count(Debug::$log);
        $log=&Debug::$log[$i];
        $level=$log[0];
        echo str_repeat('    ', $level-1).'<ul id="log'.$i.'" style="display: '.($i==0?'block':'block').'">' . "\n";

        for(;;)
        {
            $i++;
            if ($i<$nb && Debug::$log[$i][0]>$level)
            {
                $onclick='onclick="debugToggle(\'log'.$i.'\');return false;"';
                echo str_repeat('    ', $level),"<li class=\"debugLog$log[2]\">";
                echo "<a href=\"#\" $onclick><strong>$log[1]</strong> - $log[3] »»»</a>\n";
                self::showLog($i);
                $log=&Debug::$log[$i];
                echo str_repeat('    ', $level),"</li>\n";
            }
            else
                echo str_repeat('    ', $level),"<li class=\"debugLog$log[2]\"><strong>$log[1]</strong> - $log[3]</li>\n";

            if ($i >= $nb) break;
            $log=&Debug::$log[$i];

            if ($log[0]<$level) break;
        }
        if ($i<$nb) echo '<div class="debugDumpLogEnd"></div>';

        echo str_repeat('    ', $level-1),"</ul>\n";
    }

    private static function firebugShowLog()
    {

//echo '<pre>';
//print_r(self::$log2);
//echo '</pre>';
        echo '<script>';
        echo 'function showPhpDebugInformation(){';
//        for($i=0; $i<count(self::$log); $i++)


        echo 'console.group("$_REQUEST");';
        echo 'console.log(', json_encode(Utils::utf8Encode($_REQUEST)), ');';
        echo 'console.groupEnd();';

        echo 'console.group("Configuration");';
        echo 'console.log(', json_encode(Utils::utf8Encode(Config::getAll())), ');';
        echo 'console.groupEnd();';

        echo 'console.group("Include/require");';
        echo 'console.log("%s",', json_encode(Utils::utf8Encode((object)get_included_files())), ');';
        echo 'console.groupEnd();';

        echo 'console.group("Trace de l\'exécution");';
        $level=self::$log2[0][0];
        foreach(self::$log2 as $j=>$log)
        {

            $h='';
            $args=$log[3];
//            $args[0]='%s : '.$args[0];
//            array_splice($args,1,0,$log[0].'-'.$log[1]);

//            $args[0].=' (%s)';
//            $args[]=$log[0].'-'.$log[1];
//
//            foreach($args as $i=>$arg)
//            {
//                if ($i) $h.=',';
//                $h.=json_encode(utf8_encode($arg));
//            }
            //$h=$args[3];
            //$h='0='.$log[0].', 1='.$log[1].', 2='.$log[2].', 3='.$log[3];
            switch($log[2])
            {
                case self::LOG:
                    $type='log';
                    break;

                case self::NOTICE:
                    $type='info';
                    break;

                case self::WARNING:
                    $type='warn';
                    break;
            }

            if (@self::$log2[$j+1][0]>$level)
            {
                  $type='group';
//                if ($type=='log')
//                    $type='group';
//                else
//                    echo "console.group('here');\n";
            }
            //$level=$log[0];

            echo "console.$type($args);\n";
            if((@self::$log2[$j+1][0]<$level))
            {
                //echo 'console.warn("'.'log[0]='. $log[0]. ', level=',$level.'");';
                echo "console.groupEnd();\n";
            }
            $level=@self::$log2[$j+1][0];
        }
        echo 'console.groupEnd();';
        echo '}';
        // echo "showPhpDebugInformation();";
        echo '</script>';

        echo '<p onclick="showPhpDebugInformation();">Afficher les informations de débogage dans firebug</p>';
//            $i++;
//            if ($i<$nb && Debug::$log[$i][0]>$level)
//            {
//                $onclick='onclick="debugToggle(\'log'.$i.'\');return false;"';
//                echo str_repeat('    ', $level),"<li class=\"debugLog$log[2]\">";
//                echo "<a href=\"#\" $onclick><strong>$log[1]</strong> - $log[3] »»»</a>\n";
//                self::showLog($i);
//                $log=&Debug::$log[$i];
//                echo str_repeat('    ', $level),"</li>\n";
//            }
//            else
//                echo str_repeat('    ', $level),"<li class=\"debugLog$log[2]\"><strong>$log[1]</strong> - $log[3]</li>\n";
//
//            if ($i >= $nb) break;
//            $log=&Debug::$log[$i];
//
//            if ($log[0]<$level) break;
//        }
//        if ($i<$nb) echo '<div class="debugDumpLogEnd"></div>';
//
//        echo str_repeat('    ', $level-1),"</ul>\n";
    }

    public static function showBar()
    {
        self::firebugShowLog();
        return;
        echo '<div id="debugWebBar">';
        echo '<h1>Barre de débogage</h1>';
        echo '<div id="debugWebBarContent">';

        echo '<div class="debugLog">'; // trace : panel
            echo '<div class="accordionTabTitleBar">Trace du programme</div>'; // trace : header
            echo '<div class="accordionTabContentBox">'."\n"; // trace : content
            self::showLog();
            echo '</div>'; // fin de trace:content
        echo '</div>'; // fin de trace:panel

        echo '<div>'; // config : panel
            echo '<div class="accordionTabTitleBar">Configuration</div>'; // config : header
            echo '<div class="accordionTabContentBox">'; // config : content
            echo Debug::dump(Config::getAll());
            echo '</div>'; // fin de config:content
        echo '</div>'; // fin de config:panel

        echo '<div>'; // Runtime : panel
            echo '<div class="accordionTabTitleBar">Runtime</div>'; // Runtime : header
            echo '<div class="accordionTabContentBox">'; // Runtime : content
            $class=new ReflectionClass('Runtime');
            echo Debug::dump($class->getStaticProperties());
            echo '</div>'; // fin de runtime:content
        echo '</div>'; // fin de runtime:panel

        echo '<div>'; // Request : panel
            echo '<div class="accordionTabTitleBar">Request</div>'; // Request: header
            echo '<div class="accordionTabContentBox">'; // Request : content
            echo Debug::dump($_REQUEST);
            echo '</div>'; // fin de Request:content
        echo '</div>'; // fin de Request:panel

        echo '<div>'; // User : panel
            echo '<div class="accordionTabTitleBar">User</div>'; // User: header
            echo '<div class="accordionTabContentBox">'; // User : content
            echo Debug::dump(User::$user);
            echo '</div>'; // fin de User:content
        echo '</div>'; // fin de User:panel

        echo '<div>'; // Cookies : panel
            echo '<div class="accordionTabTitleBar">Cookies</div>'; // Cookies: header
            echo '<div class="accordionTabContentBox">'; // Cookies : content
            echo Debug::dump($_COOKIE);
            echo '</div>'; // fin de Cookies:content
        echo '</div>'; // fin de Cookies:panel

        echo '<div>'; // Includes et require
            echo '<div class="accordionTabTitleBar">Includes/requires</div>'; // header
            echo '<div class="accordionTabContentBox">'; // content
            echo Debug::dump(get_included_files());
            echo '</div>'; // fin de content
        echo '</div>';

        echo '</div>'; // debugWebBarContent
        echo '</div>'; // debugWebBar
       // echo '<script type="text/javascript">new Rico.Accordion( $("debugWebBarContent"), {panelHeight:400} );</script>';
    }
}
?>