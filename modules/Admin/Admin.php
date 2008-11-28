<?php
/**
 * @package     fab
 * @subpackage  Admin
 * @author      Daniel Ménard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id$
 */

/**
 * Module d'administration de l'application.
 * 
 * Le module Admin constitue le 
 * {@link http://fr.wikipedia.org/wiki/Back_office_(informatique) 'BackOffice'} 
 * de l'application : il ne comporte {@link actionIndex() qu'une seule action} 
 * qui correspond à la page d'accueil du site d'administration de l'application
 * et qui affiche la liste des modules d'administration disponibles.
 * 
 * La classe Admin est également la classe ancêtre de tous les autres modules 
 * d'administration disponibles.
 * 
 * Cliquez ici pour accéder au {@link /Admin site d'administration de l'application}.
 *  
 * @package     fab
 * @subpackage  Admin
 */
class Admin extends Module
{
    /**
     * Retourne le titre du module d'administration.
     * 
     * Cette méthode est appellée automatiquement par la 
     * {@link Admin::actionIndex() page d'accueil} du 
     * {@link /Admin site d'administration} pour afficher la liste des modules 
     * d'administration disponibles.
     * 
     * Elle retourne le titre indiqué dans la clé
     * <code><title></code> de la configuration.
     * 
     * Si aucun titre n'est indiqué, elle retourne le nom de la classe en cours.
     * 
     * Cette méthode peut être surchargée par les modules d'administration
     * descendants de cette classe si une implémentation différente est 
     * souhaitée.
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
     * Retourne la description du module d'administration.
     * 
     * Cette méthode est appellée automatiquement par la 
     * {@link Admin::actionIndex() page d'accueil} du 
     * {@link /Admin site d'administration} pour afficher la liste des modules 
     * d'administration disponibles.
     *  
     * Le but est d'indiquer à l'utilisateur, en quelques lignes, le rôle
     * de chacun des modules d'administration.
     * 
     * Par défaut, <code>getDescription()</code> retourne le contenu de la clé
     * <code><description></code> de la configuration ou null si aucune 
     * description n'est disponible.
     * 
     * Cette méthode peut être surchargée par les modules d'administration
     * descendants de cette classe pour générer une description spécifique.
     * 
     * @return null|string
     */
    public function getDescription()
    {
        return Config::get('description');
    }
    
    
    /**
     * Retourne l'url du logo à afficher pour ce module d'administration.
     * 
     * Cette méthode est appellée automatiquement par la 
     * {@link Admin::actionIndex() page d'accueil} du 
     * {@link /Admin site d'administration} pour afficher la liste des modules 
     * d'administration disponibles.
     * 
     * La méthode récupère le nom de l'image indiqué dans la clé 
     * <code><icon></code> du fichier de configuration.
     * 
     * S'il s'agit d'un chemin relatif, celui-ci est transformé en chemin de la
     * forme <code>/FabWeb/modules/<nom du module>/images/<nom du logo></code>.
     * 
     * Si aucun logo n'est indiqué dans la configuration, la méthode 
     * retourne null.
     *  
     * @return null|string
     */
    public function getIcon()
    {
        $icon=Config::get('icon');
        if ($icon && Utils::isRelativePath($icon)) // fixme: ne pas faire ça ici, intégrer dans le routing
            $icon='/FabWeb/modules/' . __CLASS__ . '/images/' . $icon;
        
        // fixme: ne marche pas si le module d'administration fait partie de l'application
        
        return $icon;
    }

    /**
     * Retourne un tableau permettant de construire un fil d'ariane 
     * (breadcrumbs).
     * 
     * Les clés du tableau retourné contiennent les liens (non routés) des 
     * différentes éléments composant le fil d'ariane. Les valeurs associées
     * contiennent le libellé à afficher à l'utilisateur.
     * 
     * @return array
     * @see getBreadCrumbs()
     */
    protected function getBreadCrumbsArray()
    {
        if (get_class($this)===__CLASS__)
            return array
            (
                '/Admin' => 'Administration', 
            );
            
        return array
        (
            '/Admin' => 'Administration', 
            'index' => $this->getTitle()
        );
    }
    
    /**
     * Retourne un tableau permettant de construire un fil d'ariane 
     * (breadcrumbs).
     * 
     * Exemple : Administration » Gestion des fichiers » Modules
     *
     * La méthode retourne le même tableau que {@link getBreadCrumbsArray()}
     * si ce n'est que le dernier élément n'a pas de lien (la clé correspondante
     * est 0).
     * 
     * @return array
     */ 
    final public function getBreadCrumbs()
    {
        $breadCrumbs=$this->getBreadCrumbsArray();
        array_push($breadCrumbs, array_pop($breadCrumbs));
        return $breadCrumbs;
    }
    
    /**
     * Affiche la liste des modules d'administration disponibles.
     * 
     * Cette action charge chacun des modules indiqués dans la clé 
     * <code><modules></code> du fichier de configuration et construit un tableau 
     * qui pour chacun des modules trouvés indique :
     * 
     * - <code>title</code> : le titre du module d'administration tel que 
     *   retourné par la méthode {@link getTitle()} de ce module ;
     * - <code>description</code> : la description telle que retourné par la 
     *   méthode {@link getDescription()} de ce module;
     * - <code>icon</code> : l'url de l'icone à afficher pour ce module, telle
     *   que retournée par la méthode {@link getIcon()} de ce module ;
     * - <code>link</code> : l'url de l'action index de ce module.
     * 
     * Elle appelle ensuite le template indiqué dans la clé 
     * <code><template></code> du fichier de configuration en lui passant en 
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
        
        // Crée la requête utilisée pour charger chacun des modules d'admin 
        $request=Request::create()->setAction('');

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