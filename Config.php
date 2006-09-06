<?php

//require_once('Utils.php');
//require_once('Cache.php');

/**
 * Stocke les param�tres de configuration de l'application
 *
 * @package    fab
 * @subpackage config
 * @author     Daniel M�nard <daniel.menard@bdsp.tm.fr>
 */
class Config
{
    /**
     * @var array le tableau qui contient les param�tres de la configuration
     * en cours
     * @access private 
     */
    private static $config = array
    (
        'config' => array('checktime'=>true)
        // config.checktime est d�finit dans le fichier 'general.yaml'
        // si on ne pr�-initialise pas 'config.checktime' a true, il ne sera jamais
        // v�rifi�. On force donc la v�rif pour celui-l�, pour les autres, c'est ce
        // qu'il y a dans la config qui sera pris en compte.
    );
    
    /**
     * @var array tableau qui indique le "parser" � utiliser en fonction de 
     * l'extension du fichier de configuration � charger
     * @access private
     */
    private static $parser =array
    (
        // Fichiers de configuration au format YAML
        '.yml' => 'loadYaml',
        '.yaml' => 'loadYaml',
        
        // Format windows .ini
        '.ini' => 'loadIni',
        
        // Fichier de conf � la apache
        '.cfg' => 'loadIni',
        '.conf' => 'loadIni'
    );
    
    /**
     * Charge un fichier de configuration, mais sans le fusionner avec la
     * configuration en cours. Retourne le tableau obtenu
     */
    public static function loadFile($yamlPath, $transformer='')
    {
        // V�rifie que le fichier demand� existe
        if (!$path=realpath($yamlPath))
            throw new Exception("Impossible de trouver le fichier de configuration '$yamlPath'");
        
        // Retourne le fichier depuis le cache s'il existe et est � jour
        $cache=Config::get('cache.enabled');
        if ( $cache && Cache::has($path, Config::get('config.checktime')?filemtime($path):0) )
        {
            Debug::log('A partir du cache'); 
            return require(Cache::getPath($path));
        }
        
        // Sinon, charge le fichier r�el et le stocke en cache
        Debug::log("Compilation de '%s'", $yamlPath); 

        // D�termine le parser � utiliser
        $method=self::$parser[Utils::getExtension($path)];
        
        // Charge le fichier
        $data=self::$method($path);
        
        // Applique le transformer
        if ($transformer)
            $data=call_user_func(explode('::', $transformer), $data);

        // Stocke le fichier en cache
        if ($cache)
        {
            Cache::set
            (
                $path, 
                sprintf
                (
                    "<?php\n".
                    "// Fichier g�n�r� automatiquement � partir de '%s'\n".
                    "// Ne pas modifier.\n".
                    "//\n".
                    "// Date : %s\n\n".
                    "return %s;\n". 
                    "?>",
                    $path,
                    @date('d/m/Y H:i:s'),
                    var_export($data, true)
                )
            );
        }
        return $data;
    }
    
    /**
     * Charge un fichier de configuration et le fusionne dans la configuration
     * en cours
     */
    public static function load($yamlPath, $section='', $transformer='')
    {
        Debug::log("Chargement de '%s'", $yamlPath);
        self::addArray(self::loadFile($yamlPath, $transformer), $section);
    }
    
    /**
     * Charge un fichier de configuration au format YAML
     * 
     * @param string $path le path du fichier � charger
     * @return array un tableau associatif contenant la configuration lue
     */
    private static function loadYaml($path)
    {
        // utilise l'extension syck.dll si elle est disponible
        if (function_exists('syck_load'))
            return syck_load($path);

        // utilise la classe spyc sinon
        require_once ('lib/spyc/spyc.php');
        $spyc = new Spyc();
        return $spyc->load($path);
    }

    /**
     * Charge un fichier ini
     * 
     * @param string $path le path du fichier ini � charger
     * @return array le tableau obtenu
     */
    function loadIni($path)
    {
        die('tester loadIni avant de l\utiliser !'); 
        // return parse_ini_file($path); // TODO: � �crire, tester, etc 
    }
     
    /**
     * Fusionne la configuration en cours avec le tableau pass� en param�tre.
     * 
     * @param array $parameters un tableau associatif contenant les
     * options � int�grer dans la configuration en cours.
     */
    public static function addArray($parameters = array (), $section='')
    {
        if ($section)
        {
            if (array_key_exists($section, self::$config))
            {
            	$t=& self::$config[$section];
                if (! is_array($t)) $t=array($t);
                self::mergeConfig($t, $parameters);
            }
            else
            {
                self::$config[$section]=$parameters;
            }
        }
        else
        {
            self::mergeConfig(self::$config, $parameters);
        	
        }
        
//        if ($section == '' ||
//            array_key_exists($section, self::$config) &&
//            is_array($old= &self::$config[$section]))
//        
//            self::mergeConfig(self::$config, $parameters);
//        else
//            self::$config[$section]=$parameters;
    }

    /**
     * Fusionne le tableau pass� en param�tre avec la configuration en cours
     * 
     * @access private
     */
    private static function mergeConfig(&$t1,$t2)
    {
        foreach ($t2 as $key=>$value)
        {
            if (is_int($key))
                $t1[]=$value; 
            else 
            {
                if (array_key_exists($key,$t1) &&  
                    is_array($value) && is_array($old=&$t1[$key]))
                    self::mergeConfig($old,$value);
                else
                    $t1[$key]=$value;
            }
        }
    }

    /**
     * Retourne la valeur d'une option de configuration
     *
     * @param string $name le nom de l'option de configuration.
     * @param mixed  $default la valeur � retourner si l'option demand�e
     * n'existe pas 
     *
     * @return mixed La valeur de l'option si elle existe ou la valeur par
     * d�faut pass�e en param�tre sinon.
     */
    public static function get($name, $default = null)
    {
        $config=& self::$config;
        foreach (explode('.', $name) as $name)
        {
        	if ( ! array_key_exists($name, $config)) return $default;
            $config=& $config[$name];
        }
        return $config;
//        return array_key_exists($name, self::$config) ? self :: $config[$name] : $default;
    }

    /**
     * Modifie une option de configuration.
     *
     * Si l'option est d�j� d�finie dans la configuration en cours, la valeur
     * existante est �cras�e, m�me s'il s'agit d'un tableau. Utilier
     * {@link add} pour fusionner des valeurs.
     *
     * @param string $name Le nom de l'option � changer.
     * @param mixed  $value La valeur � d�finir.
     */
    public static function set($name, $value)
    {
        $config=& self::$config;
        foreach (explode('.', $name) as $name)
        {
//            if ( ! array_key_exists($name, $config)) $config[$name]=array();	
            $config=& $config[$name];
            if (! is_array($config)) $config=array();
        }
        $config=$value;
    }
    
    /**
     * Ajoute un param�tre sans �craser la valeur �ventuellement d�j�
     * pr�sente.
     * 
     * @param string $name le nom de l'option � modifier
     * @param mixed $value la valeur
     */
    public static function add($name, $value)
    {
        $config=& self::$config;
        $t=explode('.', $name);
        $last=array_pop($t);
        foreach ($t as $name)
        {
            $config=& $config[$name];
            if (! is_array($config)) $config=array();
        }
        if ( array_key_exists($last, $config)) 
        {
            $config=& $config[$last];
            if (is_array($config)) $config[]=$value; else $config=array($config, $value);
        }
        else
        {  
            $config[$last]=$value;
        }
    }

    /**
     * Retourne la totalit� de la configuration en cours
     *
     * @return array un tableau associatif contenant les param�tres de 
     * configuration
     */
    public static function getAll()
    {
        return self :: $config;
    }

    /**
     * R�initialise la configuration
     *
     * @return void
     */
    public static function clear($name='')
    {
        if (empty($name))   // vider tout
        {
            self :: $config = null;
            self :: $config = array ();
            return;
        }
        
        // vider une cl� sp�cifique
        $code='unset(self::$config';
        foreach (explode('.', $name) as $name)
            $code.="['$name']";
        $code.=');';

        eval($code);

        // c'est pas beau de faire du eval, mais je n'ai pas trouv� d'autre solution
        // boucler sur le tableau en faisant des r�f�rences comme dans ::set ne
        // fonctionne pas : quand on fait unset d'un r�f�rence, on ne supprime que 
        // la r�f�rence, pas la variable r�f�renc�e.         
    }

}
?>