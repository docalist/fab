<?php
/**
 * @package     fab
 * @subpackage  module
 * @author 		Daniel M�nard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id$
 */


/**
 * Gestionnaire de modules et classe anc�tre pour tous les modules de fab.
 * @package     fab
 * @subpackage  module
 */
class Module
{
    public $path;
    public $module;
    public $action;
    
    /**
     * Cr�e une instance du module dont le nom est pass� en param�tre.
     * 
     * La fonction se charge de rechercher le fichier source du module dans le
     * r�pertoire 'modules' de l'application ou sinon dans celui du framework.
     * 
     * Une erreur est g�n�r�e si le module ne peut pas �tre trouv�.
     * 
     * @param string $module nom du module � instancier
     * @return object une instance du module
     */
    public static function loadModule($module, $action='')
    {
        // Recherche dans les modules un r�pertoire dont le nom correspond au module demand�.
        // On recherche : 
        // 1. dans le r�pertoire 'modules' de l'application 
        // 2. dans le r�pertoire 'modules' du framework
        // 404 si on n'a pas trouv�.
        if (! $dir=Utils::searchFileNoCase($module, Runtime::$root.'modules', Runtime::$fabRoot.'modules'))
        {
            debug && Debug::warning("Le module %s n'existe pas", $module);
            if (debug)
                throw new Exception("Module non trouv� : $module");
            else
                Routing::notFound();
        }
        
        // Le r�pertoire obtenu nous donne le nom exact du module si la casse est diff�rente
        $module=basename($dir);

        $dir .= DIRECTORY_SEPARATOR;
        
        // Si le module a un fichier php, c'est un vrai module, on le charge 
        if (file_exists($path=$dir.$module.'.php'))
        {
            // Inclut le source du module
            require_once($path);
            debug && Debug::log('Chargement du module %s, type: module php, path: %s', $module, $path);
            
            // Cr�e une nouvelle instance
            $object=new $module();
            
            // Charge la config du module
            $object->loadConfig();
        }
        
        // Sinon, il s'agit d'un pseudo-module : on doit avoir un fichier de config avec une cl� 'module'
        else
        {
            // Charge le fichier de configuration du module
            $tempConfig=Config::loadFile($path=$dir.'config.yaml'); // �viter de charger 2 fois la config
            if (! isset($tempConfig['module']))
                throw new Exception("Le pseudo-module '$module' est invalide : pas de php et la cl� 'module' n'est pas d�finie dans le fichier de configuration");
             
            debug && Debug::log('Chargement du module %s, type: pseudo-module (h�rite de %s), path: ', $module, $tempConfig['module'], $dir);
             
            // R�cursive jusqu'� ce qu'on trouve (et qu'on charge) un vrai module    
            $object=self::loadModule($tempConfig['module']);
    
            // Applique la config du module (le tableau temporaire) � la config en cours
            //debug && Debug::log('Application de la configuration du module %s', $module);
            Config::addArray($tempConfig);
            Utils::addSearchPath($dir);        
            
        }

        if (! empty($action))
            $action=self::loadActionConfig($action);

        // Conserve le path du module pour r�soudre les chemins relatifs
        $object->path=$dir;
        $object->module=$module;
        $object->action=$action;

        return $object;
    }

    /**
     * Charge r�cursivement la configuration d'un module, en commen�ant par
     * l'anc�tre de plus haut niveau du module et en descendant dans la
     * hi�rarchie.
     * 
     * Exemple: Chargement de la configuration d'un module de classe 'D' avec :
     * <li>module A extends Module
     * <li>module B extends A
     * <li>module C, pseudo module changeant la configuration de B
     * <li>module D, pseudo module changeant la configuration de C
     * 
     * On va commencer par charger l'�ventuel fichier config.yaml pr�sent dans
     * le r�pertoire du module A, puis celui du module B, puis celui du module C
     * et ainsi de suite.
     * 
     * @access private
     */
    private function loadConfig()
    {
        self::loadConfigRecurse(new ReflectionClass(get_class($this)));
    }
    
    /**
     * Fonction utilitaire utilis�e par {@link loadConfig()}
     */
    private static function loadConfigRecurse(ReflectionClass $class)
    {
        $parent=$class->getParentClass();
        if ($parent && $parent->getName() != 'Module')
        {
            debug && Debug::log('Le module %s h�rite du module %s', $class->getName(), $parent->getName());
            self::loadConfigRecurse($parent);
        }
        $dir=dirname($class->getFileName()).DIRECTORY_SEPARATOR;
        
        Utils::addSearchPath($dir);        
        
        $noConfig=true;
        if (file_exists($path=$dir.'config.yaml'))
        {
            Config::load($path);
            $noConfig=false;
        }
        if (!empty(Runtime::$env))   // charge la config sp�cifique � l'environnement
        {
            if (file_exists($path=$dir.'config.' . Runtime::$env . '.yaml'))
            {
                Config::load($path);
                $noConfig=false;
            }
        }
        if ($noConfig) debug && Debug::log('Pas de fichier de config pour le module %s', $class->getName());
    }
    

    private static function loadActionConfig($action)
    {
        $tempConfig=Config::get($action);
        debug && $oldAction=$action;
        if (! isset($tempConfig)) return $action;

        if (isset($tempConfig['action']))
        {
            debug && Debug::log('%s est une pseudo-action qui repose sur l\'action %s', $action, $tempConfig['action']);
            $action=self::loadActionConfig($tempConfig['action']);
        }

        debug && Debug::log("Application de la config de l'action %s", $oldAction);
        Config::addArray($tempConfig);
        return $action;
    }
    
    /**
     * Lance l'ex�cution d'un module
     */
    public static function run()
    {
        Utils::clearSearchPath();
        self::loadModule
        (
            $_REQUEST['module'], 
            Utils::get($_REQUEST['action'], 'index')
        )->execute();
    }

    /**
     * Fonction appell�e avant l'ex�cution de l'action demand�e.
     * 
     * Par d�faut, preExecute v�rifie que l'utilisateur dispose des droits
     * requis pour ex�cuter l'action demand�e (cl� 'access' du fichier de
     * configuration).
     * 
     * Les modules d�riv�s peuvent utiliser cette fonction pour
     * r�aliser des pr�- initialisations ou g�rer des pseudo- actions. Si vous
     * surchargez cette m�thode, pensez � appeller parent::preExecute().
     * 
     * Une autre utilisation de cette fonction et d'interrompre le traitement en
     * retournant 'true'. 
     * 
     * @return bool true si l'ex�cution de l'action doit �tre interrompu, false
     * pour continuer normallement.
     */
    public function preExecute()
    {
    }	
    
    static $layoutSent=false;
    public final function execute()
    {
        
        debug && Debug::log('Ex�cution de %s', get_class($this));

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
        
        $this->method='action'. ucfirst($this->action);
        if ( ! method_exists($this, $this->method))
        {
            debug && Debug::warning('Action non trouv�e : %s', $this->method);
            if (debug)
                throw new Exception("Action non trouv�e : $this->method");
            else
                Routing::notFound();
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
            $method=$this->method;
            debug && Debug::log('Appel de la m�thode %s->%s()', get_class($this), $method);
            $this->$method();
            return;
        }   
        self::$layoutSent=true;
             
        // D�termine le th�me et le layout � utiliser
        $theme='themes' . DIRECTORY_SEPARATOR . Config::get('theme') . DIRECTORY_SEPARATOR;
        $defaultTheme='themes' . DIRECTORY_SEPARATOR . 'default' . DIRECTORY_SEPARATOR;
        $layout=Config::get('layout');
        if (strcasecmp($layout,'none')==0)
        {
            $method=$this->method;
            debug && Debug::log('Appel de la m�thode %s->%s()', get_class($this), $method);
            $this->$method();
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
    
    public function layoutCallback($name)
    {
    	switch($name)
        {
        	case 'title':
                if (Template::$isCompiling) return true;

                return Config::get('title','Votre site web');
                
            case 'CSS':
                if (Template::$isCompiling) return true;

                if(is_null($t=Config::get('CSS'))) return '';
                if (! is_array($t) ) $t=array($t);
                $h='';
                foreach($t as $css)
                {
                    $css=$this->convertCssOrJsPath($css, '.css', '/styles', Config::get('theme'));
                    // TODO: si on utilise le th�me par d�faut il faudrait pouvoir linker la feuille du th�me
                    // pb : elle n'est pas dans l'espace web, donc il faut un truc qui l'envoie...
                    $h.='<link rel="stylesheet" type="text/css" href="'.$css.'" media="all" />' . "\n    ";
                }
                return trim($h);
                
            case 'JS':
                if (Template::$isCompiling) return true;

                if (is_null($t=Config::get('JS'))) return '';
                if (! is_array($t) ) $t=array($t);
                $h='';
                foreach($t as $js)
                {
                    $js=$this->convertCssOrJsPath($js, '.js', '/js', '');
                    // TODO: si on utilise le th�me par d�faut il faudrait pouvoir linker le js du th�me
                    // pb : n'est pas dans l'espace web, donc il faut un truc qui l'envoie...
                    $h.='<script type="text/javascript" src="'.$js.'"></script>' . "\n    ";
                }
                return trim($h);
                
            case 'contents':
                if (Template::$isCompiling) return true;
                
                $method=$this->method;
               // debug && Debug::log('Appel de la m�thode %s->%s()', get_class($this), $method);
                $this->$method();
                return '';
            
        }
    }
    
    public function setLayout($path)
    {
        Config::set('layout', $path);
    }
    
    // TODO: devrait pas �tre l�, etc.
    private function convertCssOrJsPath($path, $defaultExtension, $defaultDir, $defaultSubDir)
    {
        // Si c'est une url absolue (http://xxx), on ne fait rien
        if (substr($path, 0, 7)!='http://') // TODO: on a plusieurs fois des trucs comme �a dans le code, faire des fonctions
        {
        
            // Ajoute l'extension par d�faut des feuilles de style
            $path=Utils::defaultExtension($path, $defaultExtension); // TODO: dans config
            
            // Si c'est un chemin relatif, on cherche dans /web/styles
            if (Utils::isRelativePath($path))
            {
                // Si on n'a pr�cis� que le nom ('styles'), m�me r�pertoire que le nom du th�me 
                if ($defaultSubDir != '' && dirname($path)=='.')
                    $path="$defaultSubDir/$path";
                    
//                $path = Runtime::$realHome . "$defaultDir/$path";
                return Routing::linkFor("$defaultDir/$path");
            }
            
            // sinon (chemin absolu style '/xxx/yyy') on ajoute simplement $home
            else
            {
                //$path=rtrim(Runtime::$realHome,'/') . $path;
                return Routing::linkFor($path);
            }
        }
        return $path;        
    }
    
    public function addCSS($path)
    {
        Config::add('CSS', $path);
    }
    public function addJavascript($path)
    {
        Config::add('JS', $path);
    }
    public function setTitle($title)
    {
    	Config::set('title', $title);
    }
    
}

?>