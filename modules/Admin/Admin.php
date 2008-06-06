<?php
/**
 * @package     fab
 * @subpackage  Admin
 * @author      Daniel Ménard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id$
 */

/**
 * Module d'administration du site.
 * Page d'accueil du site d'administration d'une application fab et classe de 
 * base pour tous les autres modules d'administration.
 *  
 * Admin représente le 
 * {@link http://fr.wikipedia.org/wiki/Back_office_(informatique) 'BackOffice'} 
 * de l'application. C'est un point d'entrée unique vers les différents modules
 * d'administration disponibles dans l'application.
 * 
 * Il ne comporte {@link actionIndex() qu'une seule action} qui affiche la
 * liste des modules d'administration disponibles.
 * 
 * C'est également la classe ancêtre de tous les modules d'administration.
 *  
 * @package     fab
 * @subpackage  Admin
 */
class Admin extends Module
{
    /**
     * Retourne le titre à du module d'administration
     * 
     * @return string
     */
    public function getTitle()
    {
        $title=Config::get('title');
        if ($title) return $title;
        return get_class($this);
    }
    
    /**
     * Retourne le menu du module d'administration
     * 
     * @return string
     */
    public function getDescription()
    {
        return Config::get('description');
    }
    
    /**
     * Retourne l'url de l'icone à afficher pour ce module d'administration
     * 
     * @return string
     */
    public function getIcon()
    {
        $icon=Config::get('icon');
        if ($icon && Utils::isRelativePath($icon)) // fixme: ne pas faire ça ici, intégrer dans le routing
            $icon='/FabWeb/modules/' . __CLASS__ . '/images/' . $icon;
        
        return $icon;
    }
    
    /**
     * Affiche la liste des modules d'administration disponibles.
     * 
     * Cette action charge chacun des modules indiqués dans la clé 
     * <code>modules</code> du fichier de configuration et construit un tableau 
     * qui pour chacun des modules trouvés indique :
     * 
     * - <code>title</code> : le titre du module d'administration ;
     * - <code>description</code> : sa description ;
     * - <code>icon</code> : l'url de l'icone à afficher pour ce module ;
     * - <code>link</code> : l'url de l'action index de ce module.
     * 
     * Elle appelle ensuite le template indiqué dans la clé 
     * <code>template</code> du fichier de configuration en lui passant en 
     * paramètre une variable <code>modules</code> contenant le tableau obtenu.
     * 
     * @throws LogicException Une exception est générée si la configuration 
     * indique des modules qui ne sont pas des modules d'administration, 
     * c'est-à-dire des modules qui ne descendent pas de la class 
     * <code>Admin</code>.
     */
    public function actionIndex()
    {
        // Détermine le path du template qui sera exécuté
        // fixme: on est obligé de le faire ici, car on charge un peu plus
        // bas d'autres modules qui vont écraser notre config et notre searchPath
        // et du coup, notre template ne sera plus trouvé lors de l'appel
        // à Template::run
        $template=Utils::searchFile(Config::get('template'));

        // sauvegarde notre config
        $config=Config::getAll();
        
        // Crée la requête utilisée pour charge chacun des modules d'admin 
        $request=Request::create()->setAction('/');

        // Crée un tableau indiquant pour chacun des modules indiqués dans la
        // config : son titre, sa description, l'url de son icone et l'url 
        // vers son action index
        $modules=array();
        
        foreach (Config::get('modules') as $moduleName=>$options)
        {
            // Charge le module indiqué
            $module=Module::getModuleFor($request->setModule($moduleName));
            
            // Vérifie que c'est bien un module d'administration
            if (! $module instanceOf Admin)
                throw new LogicException("Le module $moduleName indiqué dans la config n'est pas un module d'administration.");

            // Ajoute le module dans le tableau
            $modules[$moduleName]=array
            (
                'title'=>$module->getTitle(),
                'description'=>$module->getDescription(),
                'icon'=>$module->getIcon(),
                'link'=>$request->getUrl(),
            );
        }
        
        // Restaure notre config
        Config::clear();
        Config::addArray($config);
                
        // Exécute le template
        Template::run
        (
            $template,
            array('modules'=>$modules)
            
        );
    }
}
?>