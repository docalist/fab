<?php
/**
 * @package     fab
 * @subpackage  Admin
 * @author      Daniel M�nard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id$
 */

/**
 * Module d'administration du site.
 * Page d'accueil du site d'administration d'une application fab et classe de 
 * base pour tous les autres modules d'administration.
 *  
 * Admin repr�sente le 
 * {@link http://fr.wikipedia.org/wiki/Back_office_(informatique) 'BackOffice'} 
 * de l'application. C'est un point d'entr�e unique vers les diff�rents modules
 * d'administration disponibles dans l'application.
 * 
 * Il ne comporte {@link actionIndex() qu'une seule action} qui affiche la
 * liste des modules d'administration disponibles.
 * 
 * C'est �galement la classe anc�tre de tous les modules d'administration.
 *  
 * @package     fab
 * @subpackage  Admin
 */
class Admin extends Module
{
    /**
     * Retourne le titre � du module d'administration
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
     * Retourne l'url de l'icone � afficher pour ce module d'administration
     * 
     * @return string
     */
    public function getIcon()
    {
        $icon=Config::get('icon');
        if ($icon && Utils::isRelativePath($icon)) // fixme: ne pas faire �a ici, int�grer dans le routing
            $icon='/FabWeb/modules/' . __CLASS__ . '/images/' . $icon;
        
        return $icon;
    }
    
    /**
     * Affiche la liste des modules d'administration disponibles.
     * 
     * Cette action charge chacun des modules indiqu�s dans la cl� 
     * <code>modules</code> du fichier de configuration et construit un tableau 
     * qui pour chacun des modules trouv�s indique :
     * 
     * - <code>title</code> : le titre du module d'administration ;
     * - <code>description</code> : sa description ;
     * - <code>icon</code> : l'url de l'icone � afficher pour ce module ;
     * - <code>link</code> : l'url de l'action index de ce module.
     * 
     * Elle appelle ensuite le template indiqu� dans la cl� 
     * <code>template</code> du fichier de configuration en lui passant en 
     * param�tre une variable <code>modules</code> contenant le tableau obtenu.
     * 
     * @throws LogicException Une exception est g�n�r�e si la configuration 
     * indique des modules qui ne sont pas des modules d'administration, 
     * c'est-�-dire des modules qui ne descendent pas de la class 
     * <code>Admin</code>.
     */
    public function actionIndex()
    {
        // D�termine le path du template qui sera ex�cut�
        // fixme: on est oblig� de le faire ici, car on charge un peu plus
        // bas d'autres modules qui vont �craser notre config et notre searchPath
        // et du coup, notre template ne sera plus trouv� lors de l'appel
        // � Template::run
        $template=Utils::searchFile(Config::get('template'));

        // sauvegarde notre config
        $config=Config::getAll();
        
        // Cr�e la requ�te utilis�e pour charge chacun des modules d'admin 
        $request=Request::create()->setAction('/');

        // Cr�e un tableau indiquant pour chacun des modules indiqu�s dans la
        // config : son titre, sa description, l'url de son icone et l'url 
        // vers son action index
        $modules=array();
        
        foreach (Config::get('modules') as $moduleName=>$options)
        {
            // Charge le module indiqu�
            $module=Module::getModuleFor($request->setModule($moduleName));
            
            // V�rifie que c'est bien un module d'administration
            if (! $module instanceOf Admin)
                throw new LogicException("Le module $moduleName indiqu� dans la config n'est pas un module d'administration.");

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
                
        // Ex�cute le template
        Template::run
        (
            $template,
            array('modules'=>$modules)
            
        );
    }
}
?>