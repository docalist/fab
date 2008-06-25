<?php
/**
 * @package     fab
 * @subpackage  module
 * @author 		Daniel M�nard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id$
 */


/**
 * Gestionnaire de modules et classe anc�tre pour tous les modules de fab.
 * 
 * @package     fab
 * @subpackage  module
 */
abstract class Module
{
    public $path;
    public $module;
    public $action;
    public $method;
    public $searchPath=array();
    public $config=null;
    
    /**
     * La requ�te en cours
     *
     * @var Request
     */
    public $request=null;
    
    /**
     * Cr�e une instance du module dont le nom est pass� en param�tre.
     * 
     * La fonction se charge de charger le code source du module, de cr�er un
     * nouvel objet et de charger sa configuration
     * 
     * @param string $module le nom du module � instancier
     * @return Module une instance du module
     * 
     * @throws ModuleNotFoundException si le module n'existe pas
     * @throws ModuleException si le module n'est pas valide (par exemple 
     * pseudo module sans cl� 'module=' dans la config).
     */
    public static function loadModule($module, $fab=false)
    {
        // Recherche le r�pertoire contenant le module demand�
        if ($fab)
            $moduleDirectory=Utils::searchFileNoCase
            (
                $module, 
                Runtime::$fabRoot.'modules' // r�pertoire "/modules" du framework
            );
        else
            $moduleDirectory=Utils::searchFileNoCase
            (
                $module, 
                Runtime::$root.'modules',   // r�pertoire "/modules" de l'application 
                Runtime::$fabRoot.'modules' // r�pertoire "/modules" du framework
            );
        
        // G�n�re une exception si on ne le trouve pas
        if (false === $moduleDirectory)
        {
            throw new ModuleNotFoundException($module);
        }
        
        // Le nom du r�pertoire nous donne le nom exact du module
        $h=basename($moduleDirectory);
//        if ($h!==$module)
//            echo 'Casse diff�rente sur le nom du module. Demand�=',$module, ', r�el=', $h, '<br />';
        $module=$h;
        
        $moduleDirectory .= DIRECTORY_SEPARATOR;
        
        // V�rifie que le module est activ�
        // if ($config['disabled']) 
        //     throw new Exception('Le module '.$module.' est d�sactiv�');
            
        // Si le module a un fichier php, c'est un vrai module, on le charge 
        if (file_exists($path=$moduleDirectory.$module.'.php'))
        {
            // Inclut le source du module
            if (!class_exists($module))
            {
                debug && Debug::log('Chargement du module %s, type: module php, path: %s', $module, $path);
                require_once($path);
                if (!class_exists($module))
                {
                    throw new ModuleException("Le module '$module' est invalide : la classe '$module' n'est pas d�finie dans le fichier $module.php");
                }
            }
            
            // V�rifie que c'est bien une classe d�scendant de 'Module'
            if (! is_subclass_of($module, 'Module'))
                throw new ModuleException("Le module '$module' est invalide : il n'h�rite pas de la classe anc�tre 'Module' de fab");
                            
            // Cr�e une nouvelle instance du module
            $object=new $module();

            $object->searchPath=array(Runtime::$fabRoot.'core'.DIRECTORY_SEPARATOR.'template'.DIRECTORY_SEPARATOR); // fixme: on ne dervait pas fixer le searchpath ici
            
            $transformer=array($object, 'compileConfiguration');
            
            // Cr�e la liste des classes dont h�rite le module
            $ancestors=array();
            $class=new ReflectionClass($module);
            while ($class !== false)
            {
                array_unshift($ancestors, $class);
                $class=$class->getParentClass();
            }

            // Configuration le module 
            $config=array();
            foreach($ancestors as $class)
            {
                // Fusionne la config de l'anc�tre avec la config actuelle
                Config::mergeConfig($config, self::getConfig($class->getName(), $transformer));
                
                // Ajoute le r�pertoire de l'an�tre dans le searchPath du module
                $dir=dirname($class->getFileName());
                array_unshift($object->searchPath, $dir.DIRECTORY_SEPARATOR);
                
                // Si l'application � un r�pertoire portant le m�me nom, on l'ajoute aussi dans le searchPath
                // Pour surcharger des templates, etc.
                /*
                    en fait n'a pas de sens : si on cr�e un r�pertoire ayant le
                    m�me nom, il sera consid�r� comme un pseudo module
                */
                if (strncmp($dir, Runtime::$fabRoot, strlen(Runtime::$fabRoot))===0)
                {
                    $appdir=Runtime::$root.substr($dir, strlen(Runtime::$fabRoot));
                    if (file_exists($appdir))
                        array_unshift($object->searchPath, $appdir.DIRECTORY_SEPARATOR);
                }
                
            }
            
            // Stocke la config du module
            $object->config=$config;            
        }
        
        // Sinon, il s'agit d'un pseudo-module : on doit avoir un fichier de config avec une cl� 'module'
        else
        {
            $config=self::getConfig($module);
            // pb: � ce stade on ne sait pas quel transformer utiliser.
            // du coup la config est charg�e (et donc stock�e en cache) sans
            // aucune transformation. Cons�quence : si on cr�e un pseudo module
            // qui h�rite d'un module utilisant un transformer sp�cifique 
            // (exemple routing), on va merger une config compil�e (celle du
            // vrai module) avec une config non compil�e (celle du pseudo module)
            // ce qui fera n'importe quoi.
            // Donc : un pseudo module ne peut pas h�riter d'un module ayant un
            // transformer sp�cifique (il faut faire un vrai module dans ce cas). 
            // Question : comment v�rifier �a ?
            

            debug && Debug::log('Chargement du pseudo-module %s', $module);
            
            if (isset($config['module']))
            {
                // Charge le module correspondant
                $parent=self::loadModule($config['module']);

                // Applique la config du pseudo-module � la config du module
                Config::mergeConfig($parent->config, $config);
                
                if (! class_exists($module))
                {
                    eval
                    (
                        sprintf
                        (
                            '
                                /**
                                  * %1$s est un pseudo-module qui h�rite de {@link %2$s}.
                                  */
                                class %1$s extends %2$s
                                {
                                }
                            ',
                            $module, 
                            $config['module']
                        )
                    );
                }
                                
                $object=new $module();
                $object->config=$parent->config;
                $object->searchPath=$parent->searchPath;
            }
            else
            {
                // pseudo module implicite : on a juste un r�pertoire toto dans
                // le r�pertoire modules de l'application, mais on n'a ni fichier
                // toto.php ni config sp�cifique indiquant de quel module doit
                // h�riter toto. Dans ce cas, consid�re qu'on veut cr�er un 
                // nouveau module qui h�rite du module de m�me nom existant dans fab.
                $object=self::loadModule($module, true);
                
                // dans ce cas pr�cis, pas de merge de la config car elle est
                // d�j� charg�e par l'appel au loadModule ci-dessus puisque c'est 
                // le m�me nom
                
            }
            
    
            
            // Met � jour le searchPath du module
            array_unshift($object->searchPath, $moduleDirectory);
        }
        
        // Stocke le path du module et son nom exact
        $object->path=$moduleDirectory;
        $object->module=$module;
        debug && Debug::log($module . ' : %o', $object);

        return $object;        
    }
    
    // retourne la config du module indiqu� (fab + app) et (normale + env)
    // ne remonte pas les ascendants
    private static function getConfig($module, array $transformer=null)
    {
        $config=array();
        $dir='config'.DIRECTORY_SEPARATOR.$module.'.';
        foreach(array(Runtime::$fabRoot, Runtime::$root) as $root)
        {
            // Charge la configuration normale
            if (file_exists($path = $path=$root.$dir.'config'))
            {
                Config::mergeConfig($config, Config::loadFile($path, $transformer));
            }
                
            // Charge la configuration sp�cifique � l'environnement en cours
            if (! empty(Runtime::$env))   
            {
                if (file_exists($path=$root.$dir.Runtime::$env.'.config'))
                {
                    Config::mergeConfig($config, Config::loadFile($path, $transformer));
                }
            }
        }
        return $config;
    }
    
    /**
     * Compile la configuration du module avant que celle-ci ne soit mise en 
     * cache par le framework.
     * 
     * La m�thode par d�faut ne fait rien : elle retourne inchang�e la 
     * configuration qu'on lui passe en param�tre mais un module peut surcharger 
     * cette m�thode s'il a une configuration sp�cifique qui doit �tre compil�e 
     * (exemple : Routing).
     *
     * @param array $config
     * @return array
     */
    public function compileConfiguration(array $config)
    {
        return $config;
    }
    
    private function configureAction($action)
    { 
        $trace=false;
    
        $this->action=$action;
        
        // Utilise la r�flexion pour tester si l'action est une m�thode du module
        $class=new ReflectionObject($this);
        if($trace)echo 'configureAction(', $action, ')<br />';
        
        $action='action'.$action;
        
        // Le tableau $configs contiendra toutes les configs qu'on rencontre (pseudo action 1 -> psued action 2 -> vrai action)
        $configs=array();
        
        // Si l'action n'est pas une m�thode de la classe, c'est une pseudo action : on suit la chaine
        $isMethod=true;
        while (! $class->hasMethod($action))
        {
            if($trace)echo $action, " n'est pas une m�thode de l'objet ", $class->getName(), '<br />';
            
            // Teste si la config contient une section ayant le nom exact de l'action
            $config=null;
            if (isset($this->config[$action]))
            {
                if($trace)echo "La cl� $action est d�finie dans la config<br/>";
                $config=$this->config[$action];
            }
            
            // Sinon, r�-essaie en ignorant la casse de l'action
            else
            {
                if($trace)echo "La cl� $action n'est pas d�finie dans la config<br/>Soit la casse n'est pas bonne, soit ce n'est pas une pseudo action<br />";
                if($trace) echo "Lancement d'une recherche insensible � la casse de la cl� $action<br />";
                
                if (Config::get('urlignorecase'))
                {
                    foreach($this->config as $key=>$value)
                    {
                        if(strcasecmp($key, $action)===0)
                        {
                            $action=$key;
                            $config=$value;
                            if($trace)echo 'Cl� trouv�e. Nom indiqu� dans la config : ', $action, '<br />';
                            break; // sort du for
                        }
                    }
                }
                if (is_null($config))
                {
                    // Il n'y a rien dans la config pour cette action
                    // C'est soit une erreur (bad action) soit un truc que le module g�rera lui m�me (exemple : fabweb)
                    // On ne peut pas remonter plus loin, exit while
                    if($trace)echo 'La config ne contient aucune section ', $action, '<br />';
                    if($trace)echo "Soit c'est une erreur (bad action), soit un truc sp�cifique au module (exemple fabweb)<br />";
                    $isMethod=false;
                    break;
                }
            }

            // Stocke la config obtenue (on la chargera plus tard)
            $configs=array($action=>$config) + $configs;
            
            // Si la cl� action n'est pas d�finie, on ne peut pas remonter plus, exit while
            if (!isset($config['action'])) 
            {
                if($trace)echo "La config de l'action $action n'a pas de cl� 'action', on ne peut pas remonter plus<br/>";
//                pre($config);
                $isMethod=false;
                break;
            }
            
            // On a une nouvelle action, on continue � remonter la chaine
//            array_unshift($configs, $config);
            $action=$config['action'];
        }
        
        if ($isMethod)
        {
            if ($class->getMethod($action)->getName() !== $action)
            {
                echo "Casse diff�rente dans le nom de l'action indiqu�=", $action, ', r�el=',$class->getMethod($action)->getName(), '<br />';
            }
                
            // M�morise le nom de la m�thode qui devra �tre appell�e
            $action=$this->method=$class->getMethod($action)->getName(); // On n'utilise pas directement '$action' mais la reflection pour avoir le nom exact de l'action, avec la bonne casse. Cela permet aux fonctions qui testent le nom de l'action (exemple : homepage) d'avoir le bon nom
        
            if (isset($this->config[$action]))
            {
                if($trace)echo "La vrai action $action a une config<br/>";
                $configs=array($action=>$this->config[$action]) + $configs;
            }
            else
            {
                if($trace)echo "La vrai action $action n'a pas de config<br/>";
            }
        }
        else
        {
            if($trace) echo "L'action $action n'aboutit pas � une m�thode. Le module devra g�rer lui m�me <br />";
        }
                    
        // Cr�e la configuration finale de l'action en fusionnant toutes les config otenues
//        debug && pre('Liste des configs � charger : ', $configs);
        foreach($configs as $config)
        {
            Config::mergeConfig($this->config, $config);
        }
        
//        // Enl�ve de la config toutes les cl�s 'actionXXX'
//        foreach($this->config as $key=>$value)
//        {
//            if (strncmp($key, 'action', 6)===0)
//                unset($this->config[$key]);
//        }
//echo 'Searchpath du module : ', var_export($this->searchPath,true), '<br />';
Utils::$searchPath=$this->searchPath; // fixme: hack pour que le code qui utilise Utils::searchPath fonctionne
//echo 'Config pour l\'action : <pre>';
//print_r($this->config);
//echo '</pre>';

//Config::clear();
//echo 'Config g�n�rale : <pre>';
Config::addArray($this->config);    // fixme: objectif : uniquement $this->config mais pb pour la config transversale (autoincludes...) en attendant : on recopie dans config g�n�rale
//print_r(Config::getAll());
//echo '</pre>';
//die();                
    }
    
    /**
     * Lance l'ex�cution d'un module
     */
    public static function run(Request $request)
    {
        self::runAs($request, $request->getModule(), $request->getAction());
    }
    
    public static function getModuleFor(Request $request)
    {
        Utils::clearSearchPath();
        $module=self::loadModule($request->getModule());
        $module->configureAction($request->getAction());
        $module->request=$request;
        return $module;
    }
    
    public static function runAs(Request $request, $module, $action)
    {
        Utils::clearSearchPath();
        $module=self::loadModule($module);
        $module->request=$request;
        $module->configureAction($action);
        $module->execute();
        
        // Exp�rimental : enregistre les donn�es du formulaire si un param�tre "_autosave" a �t� transmis
        if ($request->has('_autosave'))
        {
            Runtime::startSession();
            $_SESSION['autosave'][$request->get('_autosave')]=$request->clear('_autosave')->getParameters();
        }
    }
    
    // Experimental : do not use, r�cup�ration des donn�es enregistr�es pour un formulaire    
    public static function formData($name)
    {
        Runtime::startSession();
    
        if (!isset($_SESSION['autosave'][$name])) return array();
        return $_SESSION['autosave'][$name];
    }


    private function runAction()
    {
        // Utilise la reflexion pour examiner les param�tres de l'action
        $reflectionModule=new ReflectionObject($this);
        $reflectionMethod=$reflectionModule->getMethod($this->method);
        $params=$reflectionMethod->getParameters();
        
        // On va construire un tableau args contenant tous les param�tres
        $args=array();
        foreach($params as $i=>$param)
        {
            // R�cup�re le nom du param�tre
            $name=$param->getName();
            
            // La requ�te a une valeur non vide pour ce param�tre : on le v�rifie et on l'ajoute
            if ($this->request->has($name))
            {
                $value=$this->request->get($name);
        
                if ($value!=='' && !is_null($value))
                {
                    // Tableau attendu : caste la valeur en tableau
                    if ($param->isArray() && !is_array($value))
                    {
                        $args[$name]=array($value);
                        continue;
                    }
                    
                    // Objet attendu, v�rifie le type
                    if ($class=$param->getClass())
                    {
                        $class=$class->getName();
                        if (! is_null($class) && !$value instanceof $class)
                        {
                            throw new InvalidArgumentException
                            (
                                sprintf
                                (
                                    '%s doit �tre un objet de type %s', 
                                    $name, 
                                    $class
                                )
                            );
                        }
                        $args[$name]=$value;
                        continue;
                    }
                    
                    // tout est ok
                    $args[$name]=$value;
                    continue;
                }
            }
            
            // Sinon, on utilise la valeur par d�faut s'il y en a une
            if (!$param->isDefaultValueAvailable())
            {
                throw new InvalidArgumentException
                (
                    sprintf
                    (
                        '%s est obligatoire', 
                        $name
                    )
                );
            }
            
            // ok
            $args[$name]=$param->getDefaultValue();
        }
        
        // Appelle la m�thode avec la liste d'arguments obtenus
        debug && Debug::log('Appel de la m�thode %s->%s()', get_class($this), $this->method);
        $reflectionMethod->invokeArgs($this, $args);
    }
    
    /**
     * Fonction appel�e avant l'ex�cution de l'action demand�e.
     * 
     * Par d�faut, preExecute v�rifie que l'utilisateur dispose des droits
     * requis pour ex�cuter l'action demand�e (cl� 'access' du fichier de
     * configuration).
     * 
     * Les modules d�riv�s peuvent utiliser cette fonction pour
     * r�aliser des pr�- initialisations ou g�rer des pseudo- actions. Si vous
     * surchargez cette m�thode, pensez � appeler parent::preExecute().
     * 
     * Une autre utilisation de cette fonction est d'interrompre le traitement en
     * retournant 'true'.
     * 
     * @return bool true si l'ex�cution de l'action doit �tre interrompue, false
     * pour continuer normalement.
     */
    public function preExecute()
    {
    }	
    
    static $layoutSent=false;
    public final function execute()
    {
        debug && Debug::log('Ex�cution de %s', get_class($this));

        if (Utils::isAjax())
        {
            $this->setLayout('none');
            Config::set('debug', false);
            Config::set('showdebug', false);
            header('Content-Type: text/html; charset=ISO-8859-1'); // TODO : avoir une rubrique php dans general.config permettant de "forcer" les options de php.ini
        }

        // Pr�-ex�cution   
        debug && Debug::log('Appel de %s->preExecute()', get_class($this));
        if ($this->preExecute() === true) 
        {
            debug && Debug::log('%s->preExecute a retourn� %s, termin�', get_class($this), 'true');
            return;	
        }

        // V�rifie les droits
        $access=Config::get('access');
        if (! empty($access)) User::checkAccess($access);
        
        // V�rifie que l'action existe
//        pre($this->method);
        if ( is_null($this->method) || ! method_exists($this, $this->method))
        {
            throw new ModuleActionNotFoundException($this->module, $this->action);        
        }
        
        // A ce stade, le module doit avoir d�finit le layout, les CSS/JS, le titre, les metas
        // et compagnie, soit via son fichier de configuration, soit via des appels � setLayout,
        // addCSS, addJavascript, etc.
        // On lance l'ex�cution du layout choisi. Le layout contient obligatoirement une balise 
        // [contents]. C'est au moment o� celle-ci sera �valu�e par notre callback (layoutCallback)
        // que la m�thode action du module sera appell�e.
        
        if (Config::get('sessions.use'))
            Runtime::startSession();
            
        if (self::$layoutSent)
        {
            $this->runAction();
            return;
        }   
        self::$layoutSent=true;
             
        // D�termine le th�me et le layout � utiliser
        $theme='themes' . DIRECTORY_SEPARATOR . Config::get('theme') . DIRECTORY_SEPARATOR;
        $defaultTheme='themes' . DIRECTORY_SEPARATOR . 'default' . DIRECTORY_SEPARATOR;
        $layout=Config::get('layout');
        if (strcasecmp($layout,'none')==0)
        {
            $this->runAction();
        }
        else
        {
            $path=Utils::searchFile
            (
                $layout,                                // On recherche le layout :
                Runtime::$root.$theme,                  // Th�me en cours, dans l'application 
                Runtime::$fabRoot.$theme,               // Th�me en cours, dans le framework
                Runtime::$root.$defaultTheme,           // Th�me par d�faut, dans l'application
                Runtime::$fabRoot.$defaultTheme         // Th�me par d�faut, dans le framework
            );
            if (!$path)
                throw new Exception("Impossible de trouver le layout $layout");
            
            debug && Debug::log('Ex�cution du layout %s', $path);
            
            // Ex�cute le layout, qui se chargera d'ex�cuter l'action
            Template::run($path, array($this,'layoutCallback'));
        }
                
        // Post-ex�cution
        debug && Debug::log('Appel de %s->postExecute()', get_class($this));
        $this->postExecute();
    }

    public function postExecute()
    {
        
    }
    
    public static function forward($fabUrl)
    {
        Routing::dispatch($fabUrl);
        Runtime::shutdown();
    }
    
    /**
     * Convertit l'alias d'un script javascript ou d'une feuille de style
     * 
     * La fonction utilise les alias d�finis dans la configuration 
     * (cl�s jsalias et cssalias) et traduit les path qui figurent dans cette
     * liste.
     *
     * @param string|array $path le path � convertir ou un tableau de path �
     * convertir
     * @param string $alias la cl� d'alias � utiliser ('cssalias' ou 'jsalias')
     * @return array un tableau contenant le ou les path(s) converti(s)     
     */
    private function CssOrJsPath($path, $aliasKey)
    {
        if (is_null($path)) return array();
        $alias=Config::get($aliasKey);
        
        $isArray=is_array($path);
        $path=(array)$path;
        
        foreach($path as & $item)
        {
            if (isset($alias[$item]))
                $item=$alias[$item];
        }
        return $path;
    }
    
    public function layoutCallback($name)
    {
    	switch($name)
        {
        	case 'title':
                if (Template::$isCompiling) return true;

                return Config::get('title','Votre site web');
                
        	case 'CSS':
                $result='';
        	    foreach($this->CssOrJsPath(Config::get('css'), 'alias') as $path)
        	    {
        	        $path=Routing::linkFor($path);
                    $result.='<link rel="stylesheet" type="text/css" href="'.$path.'" media="all" />' . "\n    ";
        	    }
        	    return $result;
        	    
            case 'JS':
                $result='';
                foreach($this->CssOrJsPath(Config::get('js'), 'alias') as $path)
                {
                    $path=Routing::linkFor($path);
                    $result.='<script type="text/javascript" src="'.$path.'"></script>' . "\n    ";
                }
                return $result;

            case 'contents':
                if (Template::$isCompiling) return true;
                
                $this->runAction();
                return '';
            
        }
    }
    
    public function setLayout($path)
    {
        Config::set('layout', $path);
    }
    
//    // TODO: devrait pas �tre l�, etc.
//    private function convertCssOrJsPath($path, $defaultExtension, $defaultDir, $defaultSubDir)
//    {
//        // Si c'est une url absolue (http://xxx), on ne fait rien
//        if (substr($path, 0, 7)!='http://') // TODO: on a plusieurs fois des trucs comme �a dans le code, faire des fonctions
//        {
//        
//            // Ajoute l'extension par d�faut des feuilles de style
//            $path=Utils::defaultExtension($path, $defaultExtension); // TODO: dans config
//            
//            // Si c'est un chemin relatif, on cherche dans /web/styles
//            if (Utils::isRelativePath($path))
//            {
//                // Si on n'a pr�cis� que le nom ('styles'), m�me r�pertoire que le nom du th�me 
//                if ($defaultSubDir != '' && dirname($path)=='.')
//                    $path="$defaultSubDir/$path";
//                    
////                $path = Runtime::$realHome . "$defaultDir/$path";
//                return Routing::linkFor("$defaultDir/$path");
//            }
//            
//            // sinon (chemin absolu style '/xxx/yyy') on ajoute simplement $home
//            else
//            {
//                //$path=rtrim(Runtime::$realHome,'/') . $path;
//                return Routing::linkFor($path);
//            }
//        }
//        return $path;        
//    }
    
}

/**
 * Exception de base g�n�r�e par Module
 * 
 * @package     fab
 * @subpackage  module
 */
class ModuleException extends Exception
{
}

/**
 * Exception g�n�r�e par Module si le module � charger n'existe pas
 * 
 * @package     fab
 * @subpackage  module
 */
class ModuleNotFoundException extends ModuleException
{
    public function __construct($module)
    {
        parent::__construct(sprintf('Le module %s n\'existe pas', $module));
    }
}

/**
 * Exception g�n�r�e par Module si l'action demand�e n'existe pas
 * 
 * @package     fab
 * @subpackage  module
 */
class ModuleActionNotFoundException extends ModuleException
{
    public function __construct($module, $action)
    {
        parent::__construct(sprintf('L\'action %s du module %s n\'existe pas', $action, $module));
    }
}


?>