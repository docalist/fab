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
     */
    private static $config = array
    (
        'config' => array('checktime'=>true)
        // config.checktime est définit dans le fichier 'general.config'
        // si on ne pré-initialise pas 'config.checktime' a true, il ne sera jamais
        // vérifié. On force donc la vérif pour celui-là, pour les autres, c'est ce
        // qu'il y a dans la config qui sera pris en compte.
    );
    

    /**
     * Charge un fichier de configuration XML.
     * 
     * loadFile charge le fichier indiqué, sans le fusionner avec la 
     * configuration en cours.
     * 
     * Il est possible d'indiquer un callback chargé de valider ou de modifier 
     * le tableau.
     * 
     * Si le cache est disponible, le tableau obtenu est mis en cache. Lors du
     * prochain appel, le fichier de configuration sera chargé directement à
     * partir du cache.
     * 
     * Le tableau final est retourné.
     *  
     * @param string $configPath le path du fichier de configuration à charger
     * 
     * @param callback $transformer une fonction callback optionnelle chargée 
     * de valider ou de modifier le tableau de configuration.
     * 
     * Si vous indiquez un callback, celui-ci doit prendre en paramètre un 
     * tableau et retourner un tableau.
     * 
     * @return array le tableau final
     */
    public static function loadFile($configPath, array $transformer=null)
    {
        // Vérifie que le fichier demandé existe
        if (false === $path=realpath($configPath))
            throw new Exception("Impossible de trouver le fichier de configuration '$configPath'");
        
        // Retourne le fichier depuis le cache s'il existe et est à jour
        $cache=Config::get('cache.enabled');
        if ( $cache && Cache::has($path, Config::get('config.checktime')?filemtime($path):0) )
        {
            Debug::log("Chargement de '%s' à partir du cache", $configPath);
            return require(Cache::getPath($path));
        }
        
        // Sinon, charge le fichier réel et le stocke en cache
        Debug::log("Chargement de '%s' : compilation", $configPath);

        // Charge le fichier
        $data=self::loadXml(file_get_contents($path));
    
        // Applique le transformer
        if ($transformer)
            $data=call_user_func($transformer, $data);

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
        
        // Retourne le tableau final
        return $data;
    }

    /**
     * Génère un fichier Xml à partir de la configuration en cours
     *
     * @param string $name nom du tag en cours
     * @param mixed $data données du tag en cours
     * @param string $indent indentation en cours
     */
    private static function toXml($name, $data, $indent='')
    {
        // Encode la clé en UTF8 une bonne fois pour toute
        $name=utf8_encode($name);
        
        if (is_null($data) || $data==='' || (is_array($data) && count($data)===0))
        {
            echo $indent, "<$name />\n";
        }
        elseif (is_bool($data))
        {
            echo $indent, "<$name>", ($data?'true':'false'), "</$name>\n";
        }
        elseif (is_string($data))
        {
            echo $indent, "<$name>", utf8_encode(htmlspecialchars($data, ENT_NOQUOTES)), "</$name>\n";
        }
        elseif (is_scalar($data))
        {
            echo $indent, "<$name>", $data, "</$name>\n";
        }
        elseif (is_array($data))
        {
            // Tableau avec des clés numériques : on répête le tag
            if (key($data)===0)
            {
                echo $indent, "<$name>\n";
                foreach($data as $item)
                {
                    self::toXml('item', $item, $indent.'    ');
                }
                echo $indent, "</$name>\n";
            }
            
            // Tableau avec des clés alpha : enumère les propriétés
            else
            {
                echo $indent, "<$name>\n";
                foreach($data as $childName=>$child)
                {
                    self::toXml($childName, $child, $indent.'    ');
                }
                echo $indent, "</$name>\n";
            }
        }
    }
    
    /**
     * Charge un tableau de configuration à partir du source xml passé en
     * paramètre
     *
     * @param string $source
     * @return array
     */
    private static function loadXml($source) 
    {
        // Crée un document XML
        $xml=new domDocument();
        $xml->preserveWhiteSpace=false;
    
        // gestion des erreurs : voir comment 1 à http://fr.php.net/manual/en/function.dom-domdocument-loadxml.php
        libxml_clear_errors(); // >PHP5.1
        libxml_use_internal_errors(true);// >PHP5.1
    
        // Charge le document
        if (! $xml->loadXml($source))
        {
            $h="Fichier de configuration incorrect, ce n'est pas un fichier xml valide :<br />\n"; 
            foreach (libxml_get_errors() as $error)
                $h.= "- ligne $error->line : $error->message<br />\n";
            libxml_clear_errors(); // libère la mémoire utilisée par les erreurs
            throw new Exception($h);
        }

        // Convertit la structure xml en objet
        $data=self::fromXml($xml->documentElement);

        return is_null($data) ? array() : $data;
    }
    
    /**
     * Fonction utilitaire récursive utilisée par {@link loadXml()} pour
     * convertit un noeud XML en valeur
     *
     * @param DOMElement $node
     * @return mixed
     */
    private static function fromXml(DOMElement $node)
    {
        // Balaye les noeuds fils pour déterminer la valeur de la propriété
        $value=null;
        $arrayType=0;
        foreach($node->childNodes as $child)
        {
            switch ($child->nodeType)
            {
                // Texte ou section cdata
                case XML_TEXT_NODE:
                case XML_CDATA_SECTION_NODE:
                    // Vérifie que la config ne mélange pas à la fois des noeuds et du texte
                    if (is_array($value))
                        throw new Exception('Le noeud '.$node->tagName.' contient à la fois des noeuds et du texte');
                        
                    // Stocke la valeur de la clé
                    $value.=$child->data;
                    break;
                    
                // Un tag
                case XML_ELEMENT_NODE:
                    // Vérifie que la config ne mélange pas à la fois des noeuds et du texte
                    if (is_string($value))
                        throw new Exception('Le noeud '.$node->tagName.' contient à la fois du texte et des noeuds');
                        
                    // Récupère la valeur de l'option
                    $item=self::fromXml($child);
                    
                    // Cas particulier : la valeur de la clé est un tableau d'items
                    if ($child->tagName==='item')
                    {
                        // Vérifie qu'on ne mélange pas options et items
                        if ($arrayType===2)
                            throw new Exception($node->tagName . ' contient à la fois des options et des items');
                            
                        // Aucun attribut pour un item
                        if ($child->hasAttributes())
                            throw new Exception("Un item ne peut pas avoir d'attributs");
                            
                        $value[]=$item; // si value===null php crée un array
                        $arrayType=1;
                        break;
                    }

                    // Vérifie qu'on ne mélange pas options et items
                    if ($arrayType===1)
                        throw new Exception($node->tagName . ' contient à la fois des options et des items');
                    $arrayType=2;
                    
                    // Les attributs sont interdits dans un fichier de config (sauf inherit)
                    $name=utf8_decode($child->tagName);
                    if ($child->hasAttributes())
                    {
                        foreach($child->attributes as $attribute)
                        {
                            if ($attribute->nodeName==='inherit')
                            {
                                switch(trim($attribute->nodeValue))
                                {
                                    case 'true':
                                        break;
                                    case 'false':
                                        $name='!'.$name;
                                        break;
                                    default:
                                        throw new Exception("Valeur incorrecte pour l'attribut 'inherit' de l'option '$name' : '$attribute->nodeValue'");
                                }
                            }
                            else
                                throw new Exception("L'attribut '$attribute->nodeName' n'est pas autorisé pour l'option '$name'");
                        }
                    }
                                
                    // Première fois qu'on rencontre cette clé
                    if (! isset($value[$name]))
                    {
                        $value[$name]=$item;
                    }
                    
                    // Clé déjà rencontrée : transforme en tableau
                    else
                    {
                        if (!is_array($value[$name]))
                            throw new Exception('Tag répété : '.$name);

                        $value[$name][]=$item;
                    }
                    break;

                // Types de noeud autorisés mais ignorés
                case XML_COMMENT_NODE:
                    break;
                    
                // Types de noeuds interdits
                default:
                    throw new Exception('type de noeud interdit');
            }
        }
        
        // Convertit les chaines en entiers, booléens ; décode l'utf8
        if (is_string($value))
        {
            $h=trim($value);
            if ($h==='') $value=null;
            elseif (is_numeric($h))
                $value=ctype_digit($h) ? (int)$h : (float)$h;
            elseif($h==='true')
                $value=true;
            elseif($h==='false')
                $value= false;
            else
                $value=utf8_decode($value);
            
        }

        // Retourne le résultat
        return $value;
    }

    
    /**
     * Charge un fichier de configuration et le fusionne avec la configuration
     * en cours
     * 
     * @param string $path le fichier de configuration à charger
     * @param string section la section dans laquelle le fichier sera chargé
     * @param callback fonction de callback à appliquer au tableau de configuration
     */
    public static function load($path, $section='', array $transformer=null)
    {
        self::addArray(self::loadFile($path, $transformer), $section);
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
     * Modifie le premier tableau passé en paramètre en ajoutant ou en modifiant
     * les options qui figure dans le second tableau passé en paramètre.
     *
     * Par défaut, la modification apportée consiste à fusionner les valeurs du
     * premier tableau avec celle du second. Mais si une clé commence par le 
     * caractère '!' (point d'exclamation), la fusion est désactivée et la valeur
     * associée vient purement et simplement remplacer la valeur existante. 
     * 
     * @access private
     */
    public static function mergeConfig(&$t1,$t2)
    {
        /*
            Modifs DM 20/12/07, gestion de l'attribut inherit="false"
            
            La gestion existante fonctionne lorsque deux tableaux sont fusionnés
            Mais si le tableau obtenu est à nouveau fusionné avec un autre, on
            n'a plus aucune info nous disant 'ne pas hériter'.

            J'ai modifié le code pour créer à chaque fois, dans le tableau
            résultat, une clé '!inherit' contenant la liste des clés qui ne
            doivent pas hériter de la config existante.

            Cela fonctionne, mais il subsiste dans la config des clés précédées
            d'un slash qui ne devrait pas apparaître (par exemple dans la config
            de l'action EditStructure de DatabaseAdmin). Dans la pratique, ce 
            n'est pas forcément génant parce que la config finale de premier 
            niveau n'a pas de slash, donc ça marche.

            Les slashs qui subsistent viennent du fait que si la clé n'existe
            pas déjà dans le tableau t1, on recopie directement la valeur
            provenant du tableau t2. Si cette valeur est elle même un tableau
            contenant des clés précédées d'un slash, celles-ci ne seront jamais 
            supprimées (en fait il faudrait faire un merge).   
          
         */
        if (isset($t2['!inherit']))
        {
            $noinherit=$t2['!inherit'];
            unset($t2['!inherit']);
        }
        foreach ($t2 as $key=>$value)
        {
//            if (is_array($value))
//            {
//                $temp=array();
//                self::mergeConfig($temp, $value);
//                $value=$temp;
//            }
            
            if (is_int($key))
                $t1[]=$value; 
            else 
            {
                if ($inherit = (substr($key, 0, 1)!=='!'))
                {
                    if (isset($noinherit[$key]))
                    {
                        $t1[$key]=$value;
                    }
                    else
                    {

                        if (array_key_exists($key,$t1) &&  
                            is_array($value) && is_array($old=&$t1[$key]))
                            self::mergeConfig($old,$value);
                        else
                            $t1[$key]=$value;

/*
                        if (is_array($value))
                        {
                            if (!isset($t1[$key]) || !is_array($t1[$key])) $t1[$key]=array();
                            self::mergeConfig($t1[$key],$value);
                        }
                        else
                        {
                            $t1[$key]=$value;
                        }
*/
                    }
                }
                else
                {
                    $key=substr($key,1);
                    $t1[$key]=$value;
                    $t1['!inherit'][$key]=true;
                }
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