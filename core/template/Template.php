<?php
/**
 * @package     fab
 * @subpackage  template
 * @author 		Daniel Ménard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id$
 */


/*
 * TODO :
 * - FAIT, Youenn avoir l'équivalent des walktables
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
     * options de configuration utilisées :
     * 
     * templates.forcecompile : indique s'il faut ou non forcer une recompilation 
     * complète des templates, que ceux-ci aient été modifiés ou non.
     * 
     * templates.checktime : indique si le gestionnaire de template doit vérifier 
     * ou non si les templates ont été modifiés depuis leur dernière compilation.
     * 
     * cache.enabled: indique si le cache est utilisé ou non. Si vous n'utilisez
     * pas le cache, les templates seront compilés à chaque fois et aucun fichier 
     * compilé ne sera créé. 
     */


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
     * @var array Sources de données passées en paramètre 
     * 
     * @access private
     */
    public static $data=null;
    

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

    public static $isCompiling=0;
    
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
    public static function runSource($source /* $dataSource1, $dataSource2, ..., $dataSourceN */ )
    {
echo 'Template :<pre>';
echo htmlentities($source);
echo '</pre>';
        debug && Debug::log('Exécution du source %s', $source);

        // Sauvegarde l'état
        self::saveState();

        // Détermine le path du répertoire du script qui nous appelle
        $template=dirname(Utils::callerScript()).DIRECTORY_SEPARATOR;
        
        // Stocke le path du template
        debug && Debug::log("Path du template initialisé au path de l'appellant : '%s'", $template);
        self::$template=$template; // enregistre le path du template en cours (cf walkTable)
        
        // Stocke les sources de données passées en paramètre
        self::$data=func_get_args();
        array_shift(self::$data);
        array_unshift(self::$data,array('this'=>Utils::callerObject(2)));
        
        // Compile le template s'il y a besoin
        if (true)
        {
            debug && Debug::notice("'%s' doit être compilé", $template);
            
            // Compile le code
            debug && Debug::log('Compilation du source');
            require_once dirname(__FILE__) . '/TemplateCompiler.php';
            $source=TemplateCompiler::compile($source, self::$data);
            $source=utf8_decode($source);
            
echo 'Version compilée :<pre>';
echo htmlentities($source);
echo '</pre>';
return;
                
            // Stocke le template dans le cache et l'exécute
            if (config::get('cache.enabled'))
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
    
    public static function run($template /* $dataSource1, $dataSource2, ..., $dataSourceN */ )
    {
        debug && Debug::log('Exécution du template %s', $template);

        $parentDir=dirname(self::$template);

        // Sauvegarde l'état
        self::saveState();

        // Détermine le path du répertoire du script qui nous appelle
        $caller=dirname(Utils::callerScript()).DIRECTORY_SEPARATOR;
        
        // Recherche le template
        if (! file_exists($template))
        {
            $sav=$template;
            $template=Utils::searchFile
            (
                $template,                          // On recherche le template :
                $caller,                            // 2. dans le répertoire du script appellant
                $parentDir
            );
            if (! $template) 
                throw new Exception("Impossible de trouver le template $sav");
        }

        // Stocke le path du template
        debug && Debug::log("Path du template : '%s'", $template);
        self::$template=$template; // enregistre le path du template en cours (cf walkTable)
        
        // Stocke les sources de données passées en paramètre
        self::$data=func_get_args();
        array_shift(self::$data);
        array_unshift(self::$data,array('this'=>Utils::callerObject(2)));
        
        // Compile le template s'il y a besoin
        if (self::needsCompilation($template))
        {
            debug && Debug::notice("'%s' doit être compilé", $template);
            
            // Charge le contenu du template
            if ( false === $source=file_get_contents($template) )
                throw new Exception("Le template '$template' est introuvable.");
            
            // Compile le code
            debug && Debug::log('Compilation du source');
            require_once dirname(__FILE__) . '/TemplateCompiler.php';
            $source=TemplateCompiler::compile($source, self::$data);
//echo 'Version compilée :<pre>';
//echo htmlentities($source);
//echo '</pre>';
//          if (php_version < 6) ou  if (! PHP_IS_UTF8)
            $source=utf8_decode($source);
            // Stocke le template dans le cache et l'exécute
            if (config::get('cache.enabled'))
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
     * Teste si un template a besoin d'être recompilé en comparant la version
     * en cache avec la version source.
     * 
     * La fonction prend également en compte les options templates.forcecompile
     * et templates.checkTime
     * 
     * @param string $template path du template à vérifier
     * @param string autres si le template dépend d'autres fichiers, vous pouvez
     * les indiquer.
     * 
     * @return boolean vrai si le template doit être recompilé, false sinon.
     */
    private static function needsCompilation($template /* ... */)
    {
        // Si templates.forceCompile est à true, on recompile systématiquement
        if (Config::get('templates.forcecompile')) return true;

        // Si le cache est désactivé, on recompile systématiquement
        if (! Config::get('cache.enabled')) return true;
        
        
        // si le fichier n'est pas encore dans le cache, il faut le générer
        $mtime=Cache::lastModified($template);
        if ( $mtime==0 ) return true;
        
        // Si templates.checktime est à false, terminé
        if (! Config::get('templates.checktime')) return false;

        // Compare la date du fichier en cache avec celle de chaque dépendance
        $argc=func_num_args();
        for($i=0; $i<$argc; $i++)
            if ($mtime<=filemtime(func_get_arg($i)) ) return true;
             
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
    
    public static function filled($x)
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
        array_push
        (
            self::$stateStack, 
            array
            (
                'template'      => self::$template,
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
        $t=array_pop(self::$stateStack);
        self::$template         =$t['template'];
        self::$data             =$t['data'];
    }

    public static function runSlot($slotName, $defaultAction='')
    {
        debug && Debug::log('Exécution du slot %s', $slotName);
        if ('' === $action=Config::get('slot.'.$slotName, $defaultAction)) 
        {
            debug && Debug::log('Exécution du slot %s : aucune action définie', $slotName);
            return;	
        }
        
        debug && Debug::log('Exécution du slot %s : %s', $slotName, $action);
        Routing::dispatch($action);
    }    
}

?>
