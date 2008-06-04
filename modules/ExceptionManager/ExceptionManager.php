<?php
/**
 * @package     fab
 * @subpackage  module
 * @author 		Daniel Ménard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id$
 */


/**
 * Gestionnaire d'exceptions
 * 
 * @package     fab
 * @subpackage  exception
 */
class ExceptionManager extends Module
{
    public function install()
    {
        set_exception_handler(array($this,'handleException'));
        set_error_handler(array($this, 'handleError'), (E_ALL & !E_WARNING) | E_STRICT); // TODO config
    }
    
    /**
     * Affiche l'exception passée en paramètre.
     * Cette méthode n'est pas destinée à être appellée directement : elle sera
     * automatiquement exécutée si une erreur survient dans le programme.
     * 
     * @param Exception $exception l'exception à afficher
     * @param boolean $addCaller flag indiquant si la fonction appelante doit
     * être ou non affichée dans la pile des appels.
     */
    public function handleException(Exception $exception, $addCaller=true)
    {
        // TODO : voir si le template pourrait se charger de faire la boucle
        
        try
        {
            global $stack;

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

            
            // Détermine l'action et le template à utiliser en fonction de la config
            $template=$action=null;
            $classes=array(get_class($exception)=>get_class($exception)) + class_parents($exception);
            $exceptions=$this->config['exceptions'];
            foreach($classes as $class)
            {
                if (! isset($exceptions[$class])) continue;
                
                if (is_string($exceptions[$class]))
                {
                    if (is_null($template)) $template=$exceptions[$class];
                    continue;
                }

                if (is_array($exceptions[$class]))
                {
                    if (is_null($template) && isset($exceptions[$class]['template'])) $template=$exceptions[$class]['template'];
                    if (is_null($action) && isset($exceptions[$class]['action'])) $action=$exceptions[$class]['action'];
                    continue;
                }
            }
            
            // Fait en sorte que les templates soient recherchés par rapport à notre searchpath
            // et non pas par rapport au searchpath du module qui a généré l'exception
            // Inutile de sauvegarder le précédent : l'exécution va se terminer juste après
            foreach($this->searchPath as $path)
                Utils::addSearchPath($path);
                
            // Recherche le path exact du template par rapport à notre searchPath
            if (false === $path=Utils::searchFile($template)) $path=$template;
            
            Template::run
            (
                $path, 
                array
                (
                    'message'   => $exception->getMessage(),
                    'name'      => get_class($exception),
                    'errCode'   => $exception->getCode(),
                    'stack'     => $stack
                )
            );
            
            // Exécute l'action demandée
            if (!is_null($action) && $action !=='' && $action !=='none')
            {
                if ( method_exists($this, $action))
                    $this->$action($exception);
                else
                    echo "<hr />Warning : impossible d'exécuter l'action $action pour cette exception, cette méthode n'existe pas dans le module ", get_class($this), '.<br />';
            }
                        
        }
        catch (Exception $e)
        {
            echo "<h1>Une erreur s'est produite</h1>";
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
        Runtime::shutdown();
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
     * transforme en exceptions pour qu'elles soient gérées par le
     * gestionnaire d'exception.
     */
    public function handleError($code, $message, $file, $line)
    {
        echo 'erreur here : ', $message, '<br />';
        $this->handleException(new Exception($message, $code), false);
        // NOTREACHED
        exit(); // todo : exit si erreur, pas si warning + runtime::shutdown
    }
    
    protected function mail(Exception $exception)
    {
        // Charge les fichiers Swift
        require_once Runtime::$fabRoot . 'lib/SwiftMailer/Swift.php';
        require_once Runtime::$fabRoot . 'lib/SwiftMailer/Swift/Connection/SMTP.php';

        // Crée une nouvelle connexion Swift
        $swift = new Swift(new Swift_Connection_SMTP(ini_get('SMTP'))); // TODO: mettre dans la config de fab pour ne pas être obligé de changer php.ini
        
        // Crée le message
        $email = new Swift_Message('Exception sur '.Utils::getHost().rtrim(Runtime::$home,'/'));
        
        
        // Ajoute le corps du message
        ob_start();
        Template::run
        (
            $this->config['mail']['template'], 
            array
            (
                'exception'=>$exception,
                'request'=>Runtime::$request,
                'host'=>Utils::getHost().rtrim(Runtime::$home,'/'),
                'user'=>User::$user,
                '_SERVER'=>$_SERVER,
                '_COOKIE'=>$_COOKIE
            )
        );
//        die();
        $body=ob_get_clean();
        $email->attach(new Swift_Message_Part($body, 'text/html'));
        
        // Détermine l'émetteur
        $from=$to=new Swift_Address(Config::get('admin.email'), Config::get('admin.name'));
        
        // Détermine les destinataires
        if (isset($this->config['mail']['recipients']))
        {
            $to=new Swift_RecipientList();
            $to->add($this->config['mail']['recipients']);
        }

        // Envoie l'e-mail
        try
        {
            $swift->send($email, $to, $from);
        }
        catch (Exception $e)
        {
            $sent=false;
            echo '<hr />', __METHOD__, ' : impossible d\'envoyer l\'e-mail.<br />', $e->getMessage();
        }

        return true;
        
    }
}
?>
