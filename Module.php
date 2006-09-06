<?php
class Module
{
    public $path;
    public $module;
    public $action;
    
    /**
     * Crée une instance du module dont le nom est passé en paramètre.
     * 
     * La fonction se charge de rechercher le fichier source du module dans le
     * répertoire 'modules' de l'application ou sinon dans celui du framework.
     * 
     * Une erreur est générée si le module ne peut pas être trouvé.
     * 
     * @param string $module nom du module à instancier
     * @return object une instance du module
     */
    public static function loadModule($module, $action='')
    {
        debug && Debug::log('Recherche du module %s', $module);
        
        // Recherche dans les modules un répertoire dont le nom correspond au module demandé.
        // On recherche : 
        // 1. dans le répertoire 'modules' de l'application 
        // 2. dans le répertoire 'modules' du framework
        // 404 si on n'a pas trouvé.
        if (! $dir=Utils::searchFileNoCase($module, Runtime::$root.'modules', Runtime::$fabRoot.'modules'))
            throw new Exception("Module non trouvé : $module");
            //Routing::notFound();
 
        // Le répertoire obtenu nous donne le nom exact du module si la casse est différente
        $module=basename($dir);

        debug && Debug::log('Module %s trouvé dans %s', $module, $dir);

        $dir .= DIRECTORY_SEPARATOR;
        
        // Si le module a un fichier php, c'est un vrai module, on le charge 
        if (file_exists($path=$dir.$module.'.php'))
        {
            debug && Debug::log('Le module %s est un vrai module', $module);
            
            // Inclut le source du module
            require_once($path);
            debug && Debug::log('Chargement de %s', basename($path));
            
            // Crée une nouvelle instance
            $object=new $module();
            
            // Charge la config du module
            $object->loadConfig();
        }
        
        // Sinon, il s'agit d'un pseudo-module : on doit avoir un fichier de config avec une clé 'module'
        else
        {
            debug && Debug::log('%s est un pseudo-module, chargement du fichier de config correspondant', $module);

            // Charge le fichier de configuration du module
            $tempConfig=Config::loadFile($path=$dir.'config.yaml'); // éviter de charger 2 fois la config
            if (! isset($tempConfig['module']))
                throw new Exception("Le pseudo-module '$module' est invalide : pas de php et la clé 'module' n'est pas définie dans le fichier de configuration");
             
            debug && Debug::log('%s utilise le module %s', $module, $tempConfig['module']);
             
            // Récursive jusqu'à ce qu'on trouve (et qu'on charge) un vrai module    
            $object=self::loadModule($tempConfig['module']);
    
            // Applique la config du module (le tableau temporaire) à la config en cours
            debug && Debug::log('Application de la configuration du module %s', $module);
            Config::addArray($tempConfig);
        }

        if (! empty($action))
        { 
            $action=self::loadActionConfig($action);
//            $tempConfig=Config::get($action);
//            
//            // Si on a une config spécifique à l'action, on l'applique
//            if (isset($tempConfig))
//            {
//
//                if (isset($tempConfig['action']))
//                {
//                    debug && Debug::log('%s est une pseudo-action qui repose sur l\'action %s', $action, $tempConfig['action']);
//                    $newAction=$tempConfig['action'];
//                    unset($tempConfig['action']);
//                }
//
//                debug && Debug::log("Application de la configuration spécifique à l'action %s", $action);
//                Config::addArray($tempConfig);
//                //Config::clear($action);
//            }    
        }

        // Conserve le path du module pour résoudre les chemins relatifs
        $object->path=$dir;
        $object->module=$module;
        $object->action=$action;


        return $object;
    }

    /**
     * Charge récursivement la configuration d'un module, en commençant par
     * l'ancêtre de plus haut niveau du module et en descendant dans la
     * hiérarchie.
     * 
     * Exemple: Chargement de la configuration d'un module de classe 'D' avec :
     * <li>module A extends Module
     * <li>module B extends A
     * <li>module C, pseudo module changeant la configuration de B
     * <li>module D, pseudo module changeant la configuration de C
     * 
     * On va commencer par charger l'éventuel fichier config.yaml présent dans
     * le répertoire du module A, puis celui du module B, puis celui du module C
     * et ainsi de suite.
     * 
     * @access private
     */
    private function loadConfig()
    {
        debug && Debug::log('Chargement de la configuration du module %s', get_class($this));
        self::loadConfigRecurse(new ReflectionClass(get_class($this)));
    }
    
    /**
     * Fonction utilitaire utilisée par {@link loadConfig()}
     */
    private static function loadConfigRecurse(ReflectionClass $class)
    {
        $parent=$class->getParentClass();
        if ($parent && $parent->getName() != 'Module')
        {
            debug && Debug::log('Le module %s hérite du module %s', $class->getName(), $parent->getName());
            self::loadConfigRecurse($parent);
        }
        $dir=dirname($class->getFileName()).DIRECTORY_SEPARATOR;
        if (file_exists($path=$dir.'config.yaml'))
            Config::load($path);

        if (!empty(Runtime::$env))   // charge la config spécifique à l'environnement
        {
            if (file_exists($path=$dir.'config.' . Runtime::$env . '.yaml'))
                Config::load($path);
        }


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
     * Lance l'exécution d'un module
     */
    public static function run()
    {
        self::loadModule
        (
            $_REQUEST['module'], 
            Utils::get($_REQUEST['action'], 'index')
        )->execute();
    }

    /**
     * Fonction appellée avant l'exécution de l'action demandée.
     * 
     * Par défaut, preExecute vérifie que l'utilisateur dispose des droits
     * requis pour exécuter l'action demandée (clé 'access' du fichier de
     * configuration).
     * 
     * Les modules dérivés peuvent utiliser cette fonction pour
     * réaliser des pré- initialisations ou gérer des pseudo- actions. Si vous
     * surchargez cette méthode, pensez à appeller parent::preExecute().
     * 
     * Une autre utilisation de cette fonction et d'interrompre le traitement en
     * retournant 'true'. 
     * 
     * @return bool true si l'exécution de l'action doit être interrompu, false
     * pour continuer normallement.
     */
    public function preExecute()
    {
    }	
    
    public final function execute()
    {
        debug && Debug::log('Exécution de %s', get_class($this));

        // Pré-exécution   
        debug && Debug::log('Appel de %s->preExecute()', get_class($this));
        if ($this->preExecute() === true) 
        {
            debug && Debug::log('%s->preExecute a retourné %s, terminé', get_class($this), 'true');
            return;	
        }

        // Vérifie les droits
        $access=Config::get('access');
        if (! empty($access)) User::checkAccess($access);
        
        $this->method='action'. ucfirst($this->action);
        if ( ! method_exists($this, $this->method))
            throw new Exception("Action non trouvée : $this->method");
            //Routing::notFound();

        // A ce stade, le module doit avoir définit le layout, les CSS/JS, le titre, les metas
        // et compagnie, soit via son fichier de configuration, soit via des appels à setLayout,
        // addCSS, addJavascript, etc.
        // On lance l'exécution du layout choisi. Le layout contient obligatoirement une balise 
        // [contents]. C'est au moment où celle-ci sera évaluée par notre callback (layoutCallback)
        // que la méthode action du module sera appellée.
        
        if (Config::get('sessions.use'))
            Runtime::startSession();
            
        // Détermine le thème et le layout à utiliser
        $theme='themes' . DIRECTORY_SEPARATOR . Config::get('theme') . DIRECTORY_SEPARATOR;
        $defaultTheme='themes' . DIRECTORY_SEPARATOR . 'default' . DIRECTORY_SEPARATOR;
        $layout=Config::get('layout');
        if (strcasecmp($layout,'none')==0)
        {
            $method=$this->method;
            debug && Debug::log('Appel de la méthode %s->%s()', get_class($this), $method);
            $this->$method();
        }
        else
        {
            $path=Utils::searchFile
            (
                $layout,                                // On recherche le layout :
                Runtime::$root.$theme,                  // Thème en cours, dans l'application 
                $path=Runtime::$fabRoot,                // Thème en cours, dans le framework
                $path=Runtime::$root.$defaultTheme,     // Thème par défaut, dans l'application
                $path=Runtime::$fabRoot.$defaultTheme   // Thème par défaut, dans le framework
            );
            if (!$path)
                throw new Exception("Impossible de trouver le layout $layout");
            
            debug && Debug::log('Exécution du layout %s', $path);
            
            // Exécute le layout, qui se chargera d'exécuter l'action
            Template::run($path, array($this,'layoutCallback'));
        }
                
        // Post-exécution
        debug && Debug::log('Appel de %s->postExecute()', get_class($this));
        $this->postExecute();
    }

    public function postExecute()
    {
        
    }
    
    
    public function layoutCallback($name)
    {
    	switch($name)
        {
        	case 'title':
                return Config::get('title');
                
            case 'CSS':
                if(is_null($t=Config::get('CSS'))) return '';
                if (! is_array($t) ) $t=array($t);
                $h='';
                foreach($t as $css)
                {
                    $css=$this->convertCssOrJsPath($css, '.css', 'styles', Config::get('theme'));
                    // TODO: si on utilise le thème par défaut il faudrait pouvoir linker la feuille du thème
                    // pb : elle n'est pas dans l'espace web, donc il faut un truc qui l'envoie...
                    $h.='<link rel="stylesheet" type="text/css" href="'.$css.'" media="all" />' . "\n    ";
                }
                return trim($h);
                
            case 'JS':
                if (is_null($t=Config::get('JS'))) return '';
                if (! is_array($t) ) $t=array($t);
                $h='';
                foreach($t as $js)
                {
                    $js=$this->convertCssOrJsPath($js, '.js', 'js', '');
                    // TODO: si on utilise le thème par défaut il faudrait pouvoir linker le js du thème
                    // pb : n'est pas dans l'espace web, donc il faut un truc qui l'envoie...
                    $h.='<script type="text/javascript" src="'.$js.'"></script>' . "\n    ";
                }
                return trim($h);
                
            case 'contents':
                $method=$this->method;
                debug && Debug::log('Appel de la méthode %s->%s()', get_class($this), $method);
                $this->$method();
                return '';
            
        }
    }
    
    public function setLayout($path)
    {
        Config::set('layout', $path);
    }
    
    // TODO: devrait pas être là, etc.
    private function convertCssOrJsPath($path, $defaultExtension, $defaultDir, $defaultSubDir)
    {
        // Si c'est une url absolue (http://xxx), on ne fait rien
        if (substr($path, 0, 7)!='http://') // TODO: on a plusieurs fois des trucs comme ça dans le code, faire des fonctions
        {
        
            // Ajoute l'extension par défaut des feuilles de style
            Utils::defaultExtension($path, $defaultExtension); // TODO: dans config
            
            // Si c'est un chemin relatif, on cherche dans /web/styles
            if (Utils::isRelativePath($path))
            {
                // Si on n'a précisé que le nom ('styles'), même répertoire que le nom du thème 
                if ($defaultSubDir != '' && dirname($path)=='.')
                    $path="$defaultSubDir/$path";
                    
                $path = Runtime::$realHome . "$defaultDir/$path";
            }
            
            // sinon (chemin absolu style '/xxx/yyy') on ajoute simplement $home
            else
            {
                $path=rtrim(Runtime::$realHome,'/') . $path;
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