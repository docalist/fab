<?php
/**
 * @package     fab
 * @subpackage  template
 * @author 		Daniel M�nard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id: Template.php 105 2006-09-21 16:30:25Z dmenard $
 */


/*
 * TODO :
 * - avoir l'�quivalent des walktables
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
     * @var boolean Indique s'il faut ou non forcer une recompilation compl�te
     * des templates, que ceux-ci aient �t� modifi�s ou non.
     * @access public 
     */
    public static $forceCompile=false;

    /**
     * @var boolean Indique si le gestionnaire de template doit v�rifier ou
     * non si les templates ont �t� modifi�s depuis leur derni�re compilation.
     * @access public
     */
    public static $checkTime=true;
    
    /**
     * @var boolean Indique si le cache est utilis� ou non. Si vous n'utilisez
     * pas le cache, les templates seront compil�s � chaque fois et aucun
     * fichier compil� ne sera cr��. 
     * @access public
     */
    public static $useCache=true;


    /**
     * @var array Pile utilis�e pour enregistrer l'�tat du gestionnaire de
     * templates et permettre � {@link run()} d'�tre r�entrante. Voir {@link
     * saveState()} et {@link restoreState()}
     */
    private static $stateStack=array();
    
    /**
     * @var array Tableau utilis� pour g�rer les blocs <opt> imbriqu�s (voir
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
     * @var string Liste des callbacks � appeller pour d�terminer la valeur d'un
     * champ. Il s'agit d'un copie de l'argument $callback  indiqu� par
     * l'utilisateur lors de l'appel � {@link run()}.
     * 
     * @access private
     */
    private static $dataCallbacks;
    private static $dataObjects;
    private static $dataVars;
    

    /**
     * @var string Flag utilis� par certains callback pour savoir s'il faut ou
     * non ins�rer les tags de d�but et de fin de php. Cf par exemple {@link
     * handleFieldsCallback()}.
     * @access private
     */
    private static $inPhp=false;
    
    /**
     * @var array Tableau utilis� par {@link hashPhpBlocks} pour prot�ger les
     * blocs php g�n�r�s par les balises.
     * @access private
     */
    private static $phpBlocks=array();
    
    /**
     * @var string Path complet du template en cours d'ex�cution.
     * Utilis� pour r�soudre les chemins relatifs (tables, sous-templates...)
     * @access private
     */
    private static $template='';
    
    /**
     * @var string Lors de la g�n�ration d'un template (cf {@link generate()}),
     * nom du jeu de g�n�rateurs � utiliser. Correspond � un sous-r�pertoire du
     * r�pertoire "template_generators"
     * @access private
     */
    private static $genSet;
    
    /**
     * @var string Lors de la g�n�ration d'un template (cf {@link generate()}),
     * item en cours de g�n�ration
     * @access private
     */
    private static $genItem;
    
    
    /**
     * @var array Utilis� par {@link generateId()} pour g�n�rer des identifiants
     * unique. Le tableau contient, pour chaque nom utilis� lors d'un appel �
     * {@link generateId()} le dernier num�ro utilis�.
     */
    private static $usedId;
    public static $data=null;
    
    /**
     * constructeur
     * 
     * Le constructeur est priv� : il n'est pas possible d'instancier la
     * classe. Utilisez directement les m�thodes statiques propos�es.
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
     * Ex�cute un template, en le recompilant au pr�alable si n�cessaire.
     * 
     * La fonction run est r�entrante : on peut appeller run sur un template qui
     * lui m�me va appeller run pour un sous-template et ainsi de suite.
     * 
     * @param string $template le nom ou le chemin, relatif ou absolu, du
     * template � ex�cuter. Si vous indiquez un chemin relatif, le template est
     * recherch� dans le r�pertoire du script appellant puis dans le r�pertoire
     * 'templates' de l'application et enfin dans le r�pertoire 'templates' du
     * framework.
     * 
     * @param mixed $dataSources indique les sources de donn�es � utiliser
     * pour d�terminer la valeur des balises de champs pr�sentes dans le
     * template.
     * 
     * Les gestionnaire de templates reconnait trois sources de donn�es :
     * 
     * 1. Des fonctions de callback.  Une fonction de callback est une fonction
     * ou une m�thode qui prend en argument le nom du champ recherch� et
     * retourne une valeur. Si votre template contient une balise de champ
     * '[toto]', les fonctions de callback que vous indiquez seront appell� les
     * unes apr�s les autres jusqu'� ce que l'une d'entre elles retourne une
     * valeur.
     * 
     * Lorsque vous indiquez une fonction callback, il peut s'agir :
     * 
     * - d'une fonction globale : indiquez dans le tableau $dataSources une
     * chaine de caract�res contenant le nom de la fonction � appeller (exemple
     * : 'mycallback')
     * 
     * - d'une m�thode statique de classe : indiquez dans le tableau
     * $dataSources soit une chaine de caract�res contenant le nom de la classe
     * suivi de '::' puis du nom de la m�thode statique � appeller (exemple :
     * 'Template:: postCallback') soit un tableau � deux �l�ments contenant �
     * l'index z�ro le nom de la classe et � l'index 1 le nom de la m�thode
     * statique � appeller (exemple : array ('Template', 'postCallback'))
     * 
     * - d'une m�thode d'objet : indiquez dans le tableau $dataSources un
     * tableau � deux �l�ments contenant � l'index z�ro l'objet et � l'index 1
     * le nom de la m�thode � appeller (exemple : array ($this, 'postCallback'))
     * 
     * 2. Des propri�t�s d'objets. Indiquez dans le tableau $dataSources l'objet
     * � utiliser. Si le gestionnaire de template rencontre une balise de champ
     * '[toto]' dans le template, il regardera si votre objet contient une
     * propri�t� nomm�e 'toto' et si c'est le cas utilisera la valeur obtenue.
     * 
     * n.b. vous pouvez, dans votre objet, utiliser la m�thode magique '__get'
     * pour cr�er de pseudo propri�t�s.
     * 
     * 3. Des valeurs : tous les �l�ments du tableau $dataSources dont la cl�
     * est alphanum�rique (i.e. ce n'est pas un simple index) sont consid�r�es
     * comme des valeurs.
     * 
     * Si votre template contient une balise de champ '[toto]' et que vous avez
     * pass� comme �l�ment dans le tableau : 'toto'=>'xxx', c'est cette valeur
     * qui sera utilis�e.
     * 
     * @param string $callbacks les nom des fonctions callback � utiliser pour
     * instancier le template. Vous pouvez indiquer une fonction unique ou
     * plusieurs en s�parant leurs noms par une virgule. Ce param�tre est
     * optionnel : si vous n'indiquez pas de fonctions callbacks, la fonction
     * essaiera d'utiliser une fonction dont le nom correspond au nom du
     * script appellant suffix� avec "_callback".
     */
    public static function run($template /* $dataSource1, $dataSource2, ..., $dataSourceN */ )
    {
        debug && Debug::log('Ex�cution du template %s', $template);
        if (count(self::$stateStack)==0)
        {
            // On est au niveau z�ro, c'est un tout nouveau template, r�initialise les id utilis�s.
            self::$usedId=array();
        }

        $parentDir=dirname(self::$template);

        // Sauvegarde l'�tat
        self::saveState();

        // D�termine le path du r�pertoire du script qui nous appelle
        $caller=dirname(Utils::callerScript()).DIRECTORY_SEPARATOR;
        
        // Recherche le template
        $sav=$template;
        if (strncasecmp($caller, Runtime::$fabRoot, strlen(Runtime::$fabRoot)) ==0)
        {
            // module du framework : le r�pertoire templates de l'application est prioritaire
            $template=Utils::searchFile
            (
                $template,                          // On recherche le template :
                Runtime::$root . 'templates',       // 1. dans le r�pertoire 'templates' de l'application
                $caller,                            // 2. dans le r�pertoire du script appellant
                Runtime::$fabRoot . 'templates',     // 3. dans le r�pertoire 'templates' du framework
                $parentDir
            );
        }
        else
        {        
            // module de l'application : le r�pertoire du script appellant est prioritaire
            $template=Utils::searchFile
            (
                $template,                          // On recherche le template :
                $caller,                            // 1. dans le r�pertoire du script appellant
                Runtime::$root . 'templates',       // 2. dans le r�pertoire 'templates' de l'application
                Runtime::$fabRoot . 'templates',     // 3. dans le r�pertoire 'templates' du framework
                $parentDir
            );
        }
        if (! $template) 
            throw new Exception("Impossible de trouver le template $sav");

        debug && Debug::log("Path du template : '%s'", $template);
        self::$template=$template; // enregistre le path du template en cours (cf walkTable)
        
        self::$data=func_get_args();    // new
        array_shift(self::$data);
//        debug && Debug::log('Donn�es du formulaire %s : %s', $sav, self::$data);

        self::$dataCallbacks=self::$dataObjects=self::$dataVars=array();    // old
        $nb=func_num_args();
        for ($i=1; $i<$nb; $i++)
        {
            $value=func_get_arg($i);
            
            if (is_object($value))              // Un objet, exemple : $this
                self::$dataObjects[]=$value;
            elseif (is_string($value))          // Une fonction globale, exemple : 'mycallback' 
            {                                   // Ou une m�thode statique, exemple : 'template::callback'
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
                    // TODO: � faire seulement en mode debug ou en environnement test
                    foreach ($value as $key=>$item)
                        if (! is_string($key)) 
                            throw new Exception("Les cl�s d'un tableau de valeur doivent �tre des chaines de caract�res. Callback pass� : " . Debug::dump($key) . ', ' . Debug::dump($item));
                    self::$dataVars=array_merge(self::$dataVars, $value);
                }
            }
            else
                echo "Je ne sais pas quoi faire de l'argument num�ro ", ($i+1), " pass� � template::run <br />", print_r($value), "<br />";
        }
            
        // Compile le template s'il y a besoin
        if (true or self::needsCompilation($template)) // TODO : � virer
        {
//            echo "<pre>Recompilation du template $template</pre>";
            //
            debug && Debug::notice("'%s' doit �tre compil�", $template);
            
            // Charge le contenu du template
            if ( ! $source=file_get_contents($template, 1) )
                throw new Exception("Le template '$template' est introuvable.");
            
            // Teste si c'est un template g�n�r�
            if (Utils::getExtension($template)=='.yaml') // TODO: � revoir
            {
                // Autorise l'emploi des tabulations (une tab=4 espaces) dans le source yaml
                $source=str_replace("\t", '    ', $source);
                
                //echo "<h1>Description du template :</h1>";
                //highlight_string($source);
                debug && Debug::notice("G�n�ration du template html � partir du fichier yaml");
                self::generate($source);
                
                // Nettoyage
                //$source=str_replace(self::PHP_END_TAG."\r", self::PHP_END_TAG."\n", $source);
//                echo "<h1>Template g�n�r�: </h1>";
//                highlight_string($source);
//                echo "<hr />";
            }
                        
            // Compile le code
            debug && Debug::log('Compilation du source');
            require_once dirname(__FILE__) . '/TemplateCompiler.php';
            $source=TemplateCompiler::compile($source);

            // Nettoyage
            // si la balise de fin de php est \r, elle est mang�e (cf http://fr2.php.net/manual/fr/language.basic-syntax.instruction-separation.php)
//            $source=str_replace(self::PHP_END_TAG."\r", self::PHP_END_TAG." \r", $source);
//            $source=preg_replace('~[\r\n][ \t]*([\r\n])~', '$1', $source);
            $source=preg_replace("~([\r\n])\s+$~m", '$1', $source);
            
            // Stocke le template dans le cache et l'ex�cute
            if (self::$useCache)
            {
                debug && Debug::log("Mise en cache de '%s'", $template);
                Cache::set($template, $source);
                debug && Debug::log("Ex�cution � partir du cache");
                require(Cache::getPath($template));
            }
            else
            {
                debug && Debug::log("Cache d�sactiv�, evaluation du template compil�");
                eval(self::PHP_END_TAG . $source);
            }            
        }
        
        // Sinon, ex�cute le template � partir du cache
        else
        {
            debug && Debug::log("Ex�cution � partir du cache");
            require(Cache::getPath($template));
        }

        // restaure l'�tat du gestionnaire
        self::restoreState();
    }        

    /**
     * G�n�re un identifiant unique (au sein de la page) pour l'identifiant
     * pass� en param�tre
     * @param string id le nom pour lequel il faut g�n�rer un identifiant ('id'
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
     * Teste si un template a besoin d'�tre recompil� en comparant la version
     * en cache avec la version source.
     * 
     * La fonction prend �galement en compte les options {@link $forceCompile}
     * et {link $checkTime}
     * 
     * @param string $template path du template � v�rifier
     * @param string autres si le template d�pend d'autres fichiers, vous pouvez
     * les indiquer.
     * 
     * @return boolean vrai si le template doit �tre recompil�, false sinon.
     */
    private static function needsCompilation($template /* ... */)
    {
        // Si forceCompile est � true, on recompile syst�matiquement
        if (self::$forceCompile) return true;
        
        // Si le cache est d�sactiv�, on recompile syst�matiquement
        if (! self::$useCache) return true;
        
        // si le fichier n'est pas encore dans le cache, il faut le g�n�rer
        $mtime=Cache::lastModified($template);
        if ( $mtime==0 ) return true; 
        
        // Si $checkTime est � false, termin�
        if (! self::$checkTime) return false; 

        // Compare la date du fichier en cache avec celle de chaque d�pendance
        $argc=func_num_args();
        for($i=0; $i<$argc; $i++)
            if ($mtime<=filemtime(func_get_arg($i)) ) 
                return true;
        
        // Aucune des d�pendances n'est plus r�cente que le fichier indiqu�
        return false;
    }
    /**
     * Fonction appell�e au d�but d'un bloc <opt>
     * @internal cette fonction ne doit �tre appell�e que depuis un template.
     * @access public
     */
    public static function optBegin()
    {
        self::$optFilled[++self::$optLevel]=0;
        ob_start(); 
    }

    /**
     * Fonction appell�e � la fin d'un bloc <opt>
     * @internal cette fonction ne doit �tre appell�e que depuis un template.
     * @access public
     */
    public static function optEnd($minimum=1)
    {
        // Si on a rencontr� au moins un champ non vide 
        if (self::$optFilled[self::$optLevel--]>=$minimum)
        {
            // Indique � l'�ventuel bloc opt parent qu'on est renseign� 
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
        
        // Parcours toutes les sources de donn�es
        foreach (self::$data as $i=>$data)
        {
            // Objet
            if (is_object($data))
            {
                // Propri�t� d'un objet
                if (property_exists($data, $name))
                {
                    debug && Debug::log('C\'est une propri�t� de l\'objet %s', get_class($data));
                    $code=$bindingName='$'.$name;
                    $bindingValue='& Template::$data['.$i.']->'.$name;
                    return true;
                }
                
                // Cl� d'un objet ArrayAccess
                if ($data instanceof ArrayAccess)
                {
                    try
                    {
                        debug && Debug::log('Tentative d\'acc�s � %s[\'%s\']', get_class($data), $name);
                        $value=$data[$name]; // essaie d'acc�der, pas d'erreur ?

                        $code=$bindingName='$'.$name;
                        $bindingValue='& Template::$data['.$i.'][\''.$name.'\']';
                        return true;
                    }
                    catch(Exception $e)
                    {
                        debug && Debug::log('G�n�re une erreur %s', $e->getMessage());
                    }
                }
                else
                    debug && Debug::log('Ce n\'est pas une cl� de l\'objet %s', get_class($data));
            }

            // Cl� d'un tableau de donn�es
            if (is_array($data) && array_key_exists($name, $data)) 
            {
                debug && Debug::log('C\'est une cl� du tableau de donn�es');
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
                
                // Si la fonction retourne autre chose que "null", termin�
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
     * Enregistre l'�tat du gestionnaire de template. 
     * Utilis� pour permettre � {@link run()} d'�tre r�entrant
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
     * Restaure l'�tat du gestionnaire de template.
     * Utilis� pour permettre � {@link run()} d'�tre r�entrant
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
