<?php
/**
 * @package     fab
 * @subpackage  template
 * @author 		Daniel Ménard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id: Template.php 105 2006-09-21 16:30:25Z dmenard $
 */


/*
 * TODO :
 * - avoir l'équivalent des walktables
 * - blocs <fill>/</fill> 
 * - TableOfContents
 * - HandleLinks (conversion des liens en fonction des routes)
 * - Includes (un autre template)
 * 
 */
/**
 * Gestionnaire de templates
 * 
 * @package     fab
 * @subpackage  template
 */
class Template
{
    /**
     * @var boolean Indique s'il faut ou non forcer une recompilation complète
     * des templates, que ceux-ci aient été modifiés ou non.
     * @access public 
     */
    public static $forceCompile=false;

    /**
     * @var boolean Indique si le gestionnaire de template doit vérifier ou
     * non si les templates ont été modifiés depuis leur dernière compilation.
     * @access public
     */
    public static $checkTime=true;
    
    /**
     * @var boolean Indique si le cache est utilisé ou non. Si vous n'utilisez
     * pas le cache, les templates seront compilés à chaque fois et aucun
     * fichier compilé ne sera créé. 
     * @access public
     */
    public static $useCache=true;


    /**
     * @var array Pile utilisée pour enregistrer l'état du gestionnaire de
     * templates et permettre à {@link run()} d'être réentrante. Voir {@link
     * saveState()} et {@link restoreState()}
     */
    private static $stateStack=array();
    
    /**
     * @var array Tableau utilisé pour gérer les blocs <opt> imbriqués (voir
     * {@link optBegin()} et {@link optEnd()})
     * @access private
     */
    private static $optFilled=array(0);
    
    /**
     * @var integer Indique le niveau d'imbrication des blocs <opt> (voir {@link
     * optBegin()} et {@link optEnd()})
     * @access private
     */
    private static $optLevel=0;
    
    /**
     * @var string Liste des callbacks à appeller pour déterminer la valeur d'un
     * champ. Il s'agit d'un copie de l'argument $callback  indiqué par
     * l'utilisateur lors de l'appel à {@link run()}.
     * 
     * @access private
     */
    private static $dataCallbacks;
    private static $dataObjects;
    private static $dataVars;
    

    /**
     * @var string Flag utilisé par certains callback pour savoir s'il faut ou
     * non insérer les tags de début et de fin de php. Cf par exemple {@link
     * handleFieldsCallback()}.
     * @access private
     */
    private static $inPhp=false;
    
    /**
     * @var array Tableau utilisé par {@link hashPhpBlocks} pour protéger les
     * blocs php générés par les balises.
     * @access private
     */
    private static $phpBlocks=array();
    
    /**
     * @var string Path complet du template en cours d'exécution.
     * Utilisé pour résoudre les chemins relatifs (tables, sous-templates...)
     * @access private
     */
    private static $template='';
    
    /**
     * @var string Lors de la génération d'un template (cf {@link generate()}),
     * nom du jeu de générateurs à utiliser. Correspond à un sous-répertoire du
     * répertoire "template_generators"
     * @access private
     */
    private static $genSet;
    
    /**
     * @var string Lors de la génération d'un template (cf {@link generate()}),
     * item en cours de génération
     * @access private
     */
    private static $genItem;
    
    
    /**
     * @var array Utilisé par {@link generateId()} pour générer des identifiants
     * unique. Le tableau contient, pour chaque nom utilisé lors d'un appel à
     * {@link generateId()} le dernier numéro utilisé.
     */
    private static $usedId;
    public static $data=null;
    
    /**
     * constructeur
     * 
     * Le constructeur est privé : il n'est pas possible d'instancier la
     * classe. Utilisez directement les méthodes statiques proposées.
     */
    private function __construct()
    {
    }

    const PHP_START_TAG='<?php ';
    const PHP_END_TAG=" ?>";
    
    public static function setup()
    {
        self::$useCache=Config::get('cache.enabled', false);
        self::$forceCompile=Config::get('templates.forcecompile', false);
        self::$checkTime=Config::get('templates.checktime', false);
    }
    
    public static function getLevel()
    {
    	return count(self::$stateStack);
    }
    
    /**
     * Exécute un template, en le recompilant au préalable si nécessaire.
     * 
     * La fonction run est réentrante : on peut appeller run sur un template qui
     * lui même va appeller run pour un sous-template et ainsi de suite.
     * 
     * @param string $template le nom ou le chemin, relatif ou absolu, du
     * template à exécuter. Si vous indiquez un chemin relatif, le template est
     * recherché dans le répertoire du script appellant puis dans le répertoire
     * 'templates' de l'application et enfin dans le répertoire 'templates' du
     * framework.
     * 
     * @param mixed $dataSources indique les sources de données à utiliser
     * pour déterminer la valeur des balises de champs présentes dans le
     * template.
     * 
     * Les gestionnaire de templates reconnait trois sources de données :
     * 
     * 1. Des fonctions de callback.  Une fonction de callback est une fonction
     * ou une méthode qui prend en argument le nom du champ recherché et
     * retourne une valeur. Si votre template contient une balise de champ
     * '[toto]', les fonctions de callback que vous indiquez seront appellé les
     * unes après les autres jusqu'à ce que l'une d'entre elles retourne une
     * valeur.
     * 
     * Lorsque vous indiquez une fonction callback, il peut s'agir :
     * 
     * - d'une fonction globale : indiquez dans le tableau $dataSources une
     * chaine de caractères contenant le nom de la fonction à appeller (exemple
     * : 'mycallback')
     * 
     * - d'une méthode statique de classe : indiquez dans le tableau
     * $dataSources soit une chaine de caractères contenant le nom de la classe
     * suivi de '::' puis du nom de la méthode statique à appeller (exemple :
     * 'Template:: postCallback') soit un tableau à deux éléments contenant à
     * l'index zéro le nom de la classe et à l'index 1 le nom de la méthode
     * statique à appeller (exemple : array ('Template', 'postCallback'))
     * 
     * - d'une méthode d'objet : indiquez dans le tableau $dataSources un
     * tableau à deux éléments contenant à l'index zéro l'objet et à l'index 1
     * le nom de la méthode à appeller (exemple : array ($this, 'postCallback'))
     * 
     * 2. Des propriétés d'objets. Indiquez dans le tableau $dataSources l'objet
     * à utiliser. Si le gestionnaire de template rencontre une balise de champ
     * '[toto]' dans le template, il regardera si votre objet contient une
     * propriété nommée 'toto' et si c'est le cas utilisera la valeur obtenue.
     * 
     * n.b. vous pouvez, dans votre objet, utiliser la méthode magique '__get'
     * pour créer de pseudo propriétés.
     * 
     * 3. Des valeurs : tous les éléments du tableau $dataSources dont la clé
     * est alphanumérique (i.e. ce n'est pas un simple index) sont considérées
     * comme des valeurs.
     * 
     * Si votre template contient une balise de champ '[toto]' et que vous avez
     * passé comme élément dans le tableau : 'toto'=>'xxx', c'est cette valeur
     * qui sera utilisée.
     * 
     * @param string $callbacks les nom des fonctions callback à utiliser pour
     * instancier le template. Vous pouvez indiquer une fonction unique ou
     * plusieurs en séparant leurs noms par une virgule. Ce paramètre est
     * optionnel : si vous n'indiquez pas de fonctions callbacks, la fonction
     * essaiera d'utiliser une fonction dont le nom correspond au nom du
     * script appellant suffixé avec "_callback".
     */
    public static function run($template /* $dataSource1, $dataSource2, ..., $dataSourceN */ )
    {
        debug && Debug::log('Exécution du template %s', $template);
        if (count(self::$stateStack)==0)
        {
            // On est au niveau zéro, c'est un tout nouveau template, réinitialise les id utilisés.
            self::$usedId=array();
        }

        $parentDir=dirname(self::$template);

        // Sauvegarde l'état
        self::saveState();

        // Détermine le path du répertoire du script qui nous appelle
        $caller=dirname(Utils::callerScript()).DIRECTORY_SEPARATOR;
        
        // Recherche le template
        $sav=$template;
        if (strncasecmp($caller, Runtime::$fabRoot, strlen(Runtime::$fabRoot)) ==0)
        {
            // module du framework : le répertoire templates de l'application est prioritaire
            $template=Utils::searchFile
            (
                $template,                          // On recherche le template :
                Runtime::$root . 'templates',       // 1. dans le répertoire 'templates' de l'application
                $caller,                            // 2. dans le répertoire du script appellant
                Runtime::$fabRoot . 'templates',     // 3. dans le répertoire 'templates' du framework
                $parentDir
            );
        }
        else
        {        
            // module de l'application : le répertoire du script appellant est prioritaire
            $template=Utils::searchFile
            (
                $template,                          // On recherche le template :
                $caller,                            // 1. dans le répertoire du script appellant
                Runtime::$root . 'templates',       // 2. dans le répertoire 'templates' de l'application
                Runtime::$fabRoot . 'templates',     // 3. dans le répertoire 'templates' du framework
                $parentDir
            );
        }
        if (! $template) 
            throw new Exception("Impossible de trouver le template $sav");

        debug && Debug::log("Path du template : '%s'", $template);
        self::$template=$template; // enregistre le path du template en cours (cf walkTable)
        
        self::$data=func_get_args();    // new
        array_shift(self::$data);
//        debug && Debug::log('Données du formulaire %s : %s', $sav, self::$data);

        self::$dataCallbacks=self::$dataObjects=self::$dataVars=array();    // old
        $nb=func_num_args();
        for ($i=1; $i<$nb; $i++)
        {
            $value=func_get_arg($i);
            
            if (is_object($value))              // Un objet, exemple : $this
                self::$dataObjects[]=$value;
            elseif (is_string($value))          // Une fonction globale, exemple : 'mycallback' 
            {                                   // Ou une méthode statique, exemple : 'template::callback'
                $value=rtrim($value, '()');
                if (strpos($value, '::') !== false)
                    $value=explode('::', $value);
                self::$dataCallbacks[]=$value;
            }
            elseif (is_array($value))           // Un tableau de valeur ou un tableau callback
//            elseif (is_array($value) or($value instanceof Iterator and $value instanceOf ArrayAccess))
            {

                if (is_callable($value))        // C'est un callback
                    self::$dataCallbacks[]=$value;                    	
                else
                {
                    // TODO: à faire seulement en mode debug ou en environnement test
                    foreach ($value as $key=>$item)
                        if (! is_string($key)) 
                            throw new Exception("Les clés d'un tableau de valeur doivent être des chaines de caractères. Callback passé : " . Debug::dump($key) . ', ' . Debug::dump($item));
                    self::$dataVars=array_merge(self::$dataVars, $value);
                }
            }
            else
                echo "Je ne sais pas quoi faire de l'argument numéro ", ($i+1), " passé à template::run <br />", print_r($value), "<br />";
        }
            
        // Compile le template s'il y a besoin
        if (true or self::needsCompilation($template)) // TODO : à virer
        {
//            echo "<pre>Recompilation du template $template</pre>";
            //
            debug && Debug::notice("'%s' doit être compilé", $template);
            
            // Charge le contenu du template
            if ( ! $source=file_get_contents($template, 1) )
                throw new Exception("Le template '$template' est introuvable.");
            
            // Teste si c'est un template généré
            if (Utils::getExtension($template)=='.yaml') // TODO: à revoir
            {
                // Autorise l'emploi des tabulations (une tab=4 espaces) dans le source yaml
                $source=str_replace("\t", '    ', $source);
                
                //echo "<h1>Description du template :</h1>";
                //highlight_string($source);
                debug && Debug::notice("Génération du template html à partir du fichier yaml");
                self::generate($source);
                
                // Nettoyage
                //$source=str_replace(self::PHP_END_TAG."\r", self::PHP_END_TAG."\n", $source);
//                echo "<h1>Template généré: </h1>";
//                highlight_string($source);
//                echo "<hr />";
            }
                        
            // Compile le code
            debug && Debug::log('Compilation du source');
            require_once dirname(__FILE__) . '/TemplateCompiler.php';
            $source=TemplateCompiler::compile($source);

            // Nettoyage
            // si la balise de fin de php est \r, elle est mangée (cf http://fr2.php.net/manual/fr/language.basic-syntax.instruction-separation.php)
//            $source=str_replace(self::PHP_END_TAG."\r", self::PHP_END_TAG." \r", $source);
//            $source=preg_replace('~[\r\n][ \t]*([\r\n])~', '$1', $source);
            $source=preg_replace("~([\r\n])\s+$~m", '$1', $source);
            
            // Stocke le template dans le cache et l'exécute
            if (self::$useCache)
            {
                debug && Debug::log("Mise en cache de '%s'", $template);
                Cache::set($template, $source);
                debug && Debug::log("Exécution à partir du cache");
                require(Cache::getPath($template));
            }
            else
            {
                debug && Debug::log("Cache désactivé, evaluation du template compilé");
                eval(self::PHP_END_TAG . $source);
            }            
        }
        
        // Sinon, exécute le template à partir du cache
        else
        {
            debug && Debug::log("Exécution à partir du cache");
            require(Cache::getPath($template));
        }

        // restaure l'état du gestionnaire
        self::restoreState();
    }        

    /**
     * Génère un identifiant unique (au sein de la page) pour l'identifiant
     * passé en paramètre
     * @param string id le nom pour lequel il faut générer un identifiant ('id'
     * si absent)
     * @return string un identifiant unique de la forme 'name', 'name2',
     * 'name3...'
     */
    private static function generateId($name='id')
    {
        if (isset(self::$usedId[$name]))
            return $name . '-' . (++self::$usedId[$name] + 1);
        else
        {
            self::$usedId[$name]=0;
        	return $name;
        }        
    }
    
    /**
     * Teste si un template a besoin d'être recompilé en comparant la version
     * en cache avec la version source.
     * 
     * La fonction prend également en compte les options {@link $forceCompile}
     * et {link $checkTime}
     * 
     * @param string $template path du template à vérifier
     * @param string autres si le template dépend d'autres fichiers, vous pouvez
     * les indiquer.
     * 
     * @return boolean vrai si le template doit être recompilé, false sinon.
     */
    private static function needsCompilation($template /* ... */)
    {
        // Si forceCompile est à true, on recompile systématiquement
        if (self::$forceCompile) return true;
        
        // Si le cache est désactivé, on recompile systématiquement
        if (! self::$useCache) return true;
        
        // si le fichier n'est pas encore dans le cache, il faut le générer
        $mtime=Cache::lastModified($template);
        if ( $mtime==0 ) return true; 
        
        // Si $checkTime est à false, terminé
        if (! self::$checkTime) return false; 

        // Compare la date du fichier en cache avec celle de chaque dépendance
        $argc=func_num_args();
        for($i=0; $i<$argc; $i++)
            if ($mtime<=filemtime(func_get_arg($i)) ) 
                return true;
        
        // Aucune des dépendances n'est plus récente que le fichier indiqué
        return false;
    }
    /**
     * Fonction appellée au début d'un bloc <opt>
     * @internal cette fonction ne doit être appellée que depuis un template.
     * @access public
     */
    public static function optBegin()
    {
        self::$optFilled[++self::$optLevel]=0;
        ob_start(); 
    }

    /**
     * Fonction appellée à la fin d'un bloc <opt>
     * @internal cette fonction ne doit être appellée que depuis un template.
     * @access public
     */
    public static function optEnd($minimum=1)
    {
        // Si on a rencontré au moins un champ non vide 
        if (self::$optFilled[self::$optLevel--]>=$minimum)
        {
            // Indique à l'éventuel bloc opt parent qu'on est renseigné 
            self::$optFilled[self::$optLevel]++ ;
            
            // Envoit le contenu du bloc
            ob_end_flush();
        }
        
        // Sinon, vide le contenu du bloc
        else
        {
            ob_end_clean();
        }
    }
    
    public static function getDataSource($name, & $bindingName, & $bindingValue, & $code)
    {
        debug && Debug::log('%s', $name);
        
        // Parcours toutes les sources de données
        foreach (self::$data as $i=>$data)
        {
            // Objet
            if (is_object($data))
            {
                // Propriété d'un objet
                if (property_exists($data, $name))
                {
                    debug && Debug::log('C\'est une propriété de l\'objet %s', get_class($data));
                    $code=$bindingName='$'.$name;
                    $bindingValue='& Template::$data['.$i.']->'.$name;
                    return true;
                }
                
                // Clé d'un objet ArrayAccess
                if ($data instanceof ArrayAccess)
                {
                    try
                    {
                        debug && Debug::log('Tentative d\'accès à %s[\'%s\']', get_class($data), $name);
                        $value=$data[$name]; // essaie d'accéder, pas d'erreur ?

                        $code=$bindingName='$'.$name;
                        $bindingValue='& Template::$data['.$i.'][\''.$name.'\']';
                        return true;
                    }
                    catch(Exception $e)
                    {
                        debug && Debug::log('Génère une erreur %s', $e->getMessage());
                    }
                }
                else
                    debug && Debug::log('Ce n\'est pas une clé de l\'objet %s', get_class($data));
            }

            // Clé d'un tableau de données
            if (is_array($data) && array_key_exists($name, $data)) 
            {
                debug && Debug::log('C\'est une clé du tableau de données');
                $code=$bindingName='$'.$name;
                $bindingValue='& Template::$data['.$i.'][\''.$name.'\']';
                return true;
            }

            // Fonction de callback
            if (is_callable($data))
            {
                ob_start();
                $value=@call_user_func($data, $name);
                ob_end_clean();
                
                // Si la fonction retourne autre chose que "null", terminé
                if ( ! is_null($value) )
                {
                    $bindingName='$callback';
                    if ($i) $bindingName .= $i;
                    $bindingValue='& Template::$data['.$i.']';
//                    $bindingValue.= 'print_r('.$bindingName.')';
                    $code=$bindingName.'(\''.$name.'\')';
                    $code='call_user_func(' . $bindingName.', \''.$name.'\')';
                    return true;
                    //return 'call_user_func(Template::$data['.$i.'], \''.$name.'\')';
                }
            }
            
            //echo('Datasource incorrecte : <pre>'.print_r($data, true). '</pre>');
        }
        //echo('Aucune source ne connait <pre>'. $name.'</pre>');
        return false;
    }

    private static function filled($x)
    {
        if ($x != '') 
            self::$optFilled[self::$optLevel]++;
    	return $x;
    }
    
    /**
     * Enregistre l'état du gestionnaire de template. 
     * Utilisé pour permettre à {@link run()} d'être réentrant
     */
    private static function saveState()
    {
//        array_push(self::$stateStack, array(self::$template,self::$callbacks,self::$genItem));
        array_push
        (
            self::$stateStack, 
            array
            (
                'template'      => self::$template,
                'dataVars'      => self::$dataVars,
                'dataObjects'   => self::$dataObjects,
                'dataCallbacks' => self::$dataCallbacks,
                'genItem'       => self::$genItem,
                'data'          => self::$data
            )
        );
    }

    /**
     * Restaure l'état du gestionnaire de template.
     * Utilisé pour permettre à {@link run()} d'être réentrant
     */
    private static function restoreState()
    {
//        list(self::$template, self::$callbacks,self::$genItem)=array_pop(self::$stateStack);
        $t=array_pop(self::$stateStack);
        self::$template         =$t['template'];
        self::$dataVars         =$t['dataVars'];
        self::$dataObjects      =$t['dataObjects'];
        self::$dataCallbacks    =$t['dataCallbacks'];
        self::$genItem          =$t['genItem'];
        self::$data             =$t['data'];
    }
    
}

?>
