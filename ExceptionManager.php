<?php
/**
 * @package     fab
 * @subpackage  exceptions
 * @author 		Daniel Ménard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id$
 */


/**
 * Gestionnaire d'exceptions
 * 
 * @package     fab
 * @subpackage  exceptions
 */

class ExceptionManager
{
    /**
     * Affiche l'exception passée en paramètre.
     * Cette fonction n'est pas destinée à être appellée directement : elle sera
     * automatiquement exécutée si une erreur survient dans le programme.
     * 
     * @param Exception $exception l'exception à afficher
     * @param boolean $addCaller flag indiquant si la fonction appelante doit
     * être ou non affichée dans la pile des appels.
     * @access private
     */
    public static function handleException(Exception $exception, $addCaller=true)
    {
        try
        {
            global $stack;

            $data['message']    = $exception->getMessage();
            $data['name']       = get_class($exception);
            $data['errCode']    = $exception->getCode();
        
            $trace = $exception->getTrace();
            if ($addCaller)
            {
                array_unshift($trace, array(
                  'function' => '',
                  'file'     => ($exception->getFile() != null) ? $exception->getFile() : 'n/a',
                  'line'     => ($exception->getLine() != null) ? $exception->getLine() : 'n/a',
                  'args'     => array(),
                ));
            }
    
            for ($i = 0, $count = count($trace); $i < $count; $i++)
            {
                $line = isset($trace[$i]['line']) ? $trace[$i]['line'] : 'n/a';
                $file = isset($trace[$i]['file']) ? $trace[$i]['file'] : 'n/a';
                
                if (isset($trace[$i+1]))
                {
                    $t=$trace[$i+1];
                    $args = isset($t['args']) ? $t['args'] : array();
                    $function=$t['function'];
                    if (isset($t['class'])) $function=$t['class'].$t['type'].$function;
                    $function .= '(' . self::getArguments($args, false) . ')';                    
                    $function=explode('<br />', highlight_string("<?php //\n$function\n", true));
                    $function=$function[1];
                    $function=substr($function, strlen('</span>')).'</span>';
                    $function=str_replace(',&nbsp;', ', ', $function);
                }
                else
                    $function='{main}';
                    
                $code=self::getSource($file, $line);
        
                $stack[]=array
                (
                    'function'=>$function,
                    'file'=>$file,
                    'code'=>$code,
                    'line'=>$line
                );
                                    
            }
            Template::run('Exception.htm', $data);
        }
        catch (Exception $e)
        {
            echo "'<h1>Une erreur s'est produite</h1>";
            echo '<h2>Message : ' . $exception->getMessage() . ' (code : ' . $exception->getCode() . ')<h2>';
            echo '<p>Fichier : ' . $exception->getFile() . ', ligne ' . $exception->getLine() . '</p>';
            echo '<p>Pile des appels : </p>';
            echo '<pre>' . $exception->getTraceAsString() . '</pre>';
            
            echo "<p>Remarque : une erreur interne s'est également produite dans le gestionnaire d'exceptions, "
                . "ce qui explique pourquoi l'erreur ci-dessus est affichée en format 'brut' :</p>";
            echo '<h2>Message : ' . $e->getMessage() . ' (code : ' . $e->getCode() . ')<h2>';
            echo '<p>Fichier : ' . $e->getFile() . ', ligne ' . $e->getLine() . '</p>';
            echo '<p>Pile des appels : </p>';
            echo '<pre>' . $e->getTraceAsString() . '</pre>';
                 
        }
        Debug::showBar();
        exit();
        
    }

    /**
     * Formatte les arguments d'un appel de fonction dans le backstrace d'une
     * exception.
     * Appellé uniquement par {@link handleException}
     * @param array $args les arguments à formatter
     * @return string
     * @access private
     */
    private static function getArguments($args)
    {
        $result = '';
        foreach ($args as $key => $value)
        {
            if (! is_int($key)) $h = "$key="; else $h='';
             
            if (is_object($value))
                $h .= "object('".get_class($value)."')";
            else if (is_array($value))
                $h .= 'array(' . self::getArguments($value).')';
            else if (is_string($value))
                $h = "'$value'";
            else if (is_null($value))
                $h .= 'null';
            else
                $h = "$key='".$value."'";
                
            $result .= ($result ? ', ': '') . $h;
        }
        return $result;
    }

    /**
     * Extrait le code source du fichier contenant l'erreur.
     * Appellé uniquement
     * par {@link handleException}
     * @param array $file le fichier contenant le code source
     * @param interger le numéro de la ligne où s'est produite l'erreur
     * @return string
     * @access private
     */
    private static function getSource($file, $line)
    {
        $nb=10; // nombre de ligne à afficher avant et après la ligne en erreur 
        
        if (is_readable($file))
        {
            $content=highlight_file($file, true);
            $content=str_replace(array('<code>','/<code>'), '', $content);
            $content = preg_split('#<br />#', $content);
    
            $lines = array();
            for ($i = max($line - $nb, 0), $max = min($line + $nb, count($content)); $i <= $max; $i++)
            {
                if (isset($content[$i - 1]))
                {
                    $h=trim($content[$i - 1]);
                    if ($h=='') $h='&nbsp;';
                    $lines[] = '<li'.($i == $line ? ' class="selected"' : '').'>'.$h.'</li>';
                }
            }
            
            return '<ol start="'.max(1,$line - $nb).'">'.implode("\n", $lines).'</ol>';
        }
    }

    /**
     * Gestionnaire d'erreurs. Transforme les erreurs "classiques" de php et les
     * transforme en exceptions pour qu'elles soient gérées par notre
     * gestionnaire d'exception.
     * 
     * Installé par la fonction {@link setup}
     * @access private
     */
    public static function handleError($code, $message, $file, $line)
    {
        self::handleException(new Exception($message, $code), false);
        exit(); // todo : exit si erreur, pas si warning + runtime::shutdown
    }
}
?>
