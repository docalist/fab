<?php
/**
 * @package     fab
 * @subpackage  config
 * @author 		Daniel Ménard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id$
 */

/**
 * Stocke les paramètres de configuration de l'application
 * 
 * @package 	fab
 * @subpackage 	config
 */
class Config
{
    /**
     * @var array le tableau qui contient les paramètres de la configuration
     * en cours
     * @access private 
     */
    private static $config = array
    (
        'config' => array('checktime'=>true)
        // config.checktime est définit dans le fichier 'general.yaml'
        // si on ne pré-initialise pas 'config.checktime' a true, il ne sera jamais
        // vérifié. On force donc la vérif pour celui-là, pour les autres, c'est ce
        // qu'il y a dans la config qui sera pris en compte.
    );
    

    /**
     * Charge un fichier de configuration, mais sans le fusionner avec la
     * configuration en cours. Retourne le tableau obtenu
     */
    public static function loadFile($yamlPath, $transformer='')
    {
        // Vérifie que le fichier demandé existe
        if (!$path=realpath($yamlPath))
            throw new Exception("Impossible de trouver le fichier de configuration '$yamlPath'");
        
        // Retourne le fichier depuis le cache s'il existe et est à jour
        $cache=Config::get('cache.enabled');
        if ( $cache && Cache::has($path, Config::get('config.checktime')?filemtime($path):0) )
        {
            Debug::log("Chargement de '%s' à partir du cache", $yamlPath);
            return require(Cache::getPath($path));
        }
        
        // Sinon, charge le fichier réel et le stocke en cache
        Debug::log("Chargement de '%s' : compilation", $yamlPath);

        // Charge le fichier
        $data=Utils::loadYaml($path);
                
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
                    "// Fichier généré automatiquement à partir de '%s'\n".
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
        self::addArray(self::loadFile($yamlPath, $transformer), $section);
    }


    /**
     * Fusionne la configuration en cours avec le tableau passé en paramètre.
     * 
     * @param array $parameters un tableau associatif contenant les
     * options à intégrer dans la configuration en cours.
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
    }


    /**
     * Fusionne le tableau passé en paramètre avec la configuration en cours
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
     * @param mixed  $default la valeur à retourner si l'option demandée
     * n'existe pas 
     *
     * @return mixed La valeur de l'option si elle existe ou la valeur par
     * défaut passée en paramètre sinon.
     */
    public static function & get($name, $default = null)
    {
        $config=& self::$config;
        foreach (explode('.', $name) as $name)
        {
        	if ( ! array_key_exists($name, $config)) return $default;
            $config=& $config[$name];
        }
        return $config;
    }


    /**
     * Modifie une option de configuration.
     *
     * Si l'option est déjà définie dans la configuration en cours, la valeur
     * existante est écrasée, même s'il s'agit d'un tableau. Utiliser
     * {@link add} pour fusionner des valeurs.
     *
     * @param string $name Le nom de l'option à changer.
     * @param mixed  $value La valeur à définir.
     */
    public static function set($name, $value)
    {
        $config=& self::$config;
        foreach (explode('.', $name) as $name)
        {
            $config=& $config[$name];
            if (! is_array($config)) $config=array();
        }
        $config=$value;
    }
    

    /**
     * Ajoute un paramètre sans écraser la valeur éventuellement déjà
     * présente.
     * 
     * @param string $name le nom de l'option à modifier
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
     * Retourne la totalité de la configuration en cours
     *
     * @return array un tableau associatif contenant les paramètres de 
     * configuration
     */
    public static function getAll()
    {
        return self :: $config;
    }


    /**
     * Réinitialise la configuration
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
        
        // vider une clé spécifique
        $code='unset(self::$config';
        foreach (explode('.', $name) as $name)
            $code.="['$name']";
        $code.=');';

        eval($code);

        // c'est pas beau de faire du eval, mais je n'ai pas trouvé d'autre solution
        // boucler sur le tableau en faisant des références comme dans ::set ne
        // fonctionne pas : quand on fait unset d'un référence, on ne supprime que 
        // la référence, pas la variable référencée.         
    }
}
?>