<?php
/**
 * @package     fab
 * @subpackage  response
 * @author      Daniel Ménard <Daniel.Menard@ehesp.fr>
 * @version     SVN: $Id$
 */

/**
 * Représente une réponse dotée d'un layout.
 *
 * (patterns two step view, composite view, etc.)
 *
 * @package     fab
 * @subpackage  response
 */
class LayoutResponse extends Response
{
    /**
     * Retourne le path du layout utilisé pour cette réponse.
     *
     * Le layout utilisé est définit dans la config (clés theme et layout).
     *
     * @return string le chemin complet du template à utiliser comme layout ou <code>false</code>
     * si aucun layout n'a été définit dans la configuration.
     */
    protected function getLayout()
    {
        // Détermine le thème et le layout à utiliser
        $theme='themes' . DIRECTORY_SEPARATOR . Config::get('theme') . DIRECTORY_SEPARATOR;
        $defaultTheme='themes' . DIRECTORY_SEPARATOR . 'default' . DIRECTORY_SEPARATOR;
        $layout=Config::get('layout');

        if (strcasecmp($layout,'none')==0) return false;

        $path=Utils::searchFile
        (
            $layout,                                // On recherche le layout :
            Runtime::$root.$theme,                  // Thème en cours, dans l'application
            Runtime::$fabRoot.$theme,               // Thème en cours, dans le framework
            Runtime::$root.$defaultTheme,           // Thème par défaut, dans l'application
            Runtime::$fabRoot.$defaultTheme         // Thème par défaut, dans le framework
        );

        if (!$path)
            throw new Exception("Impossible de trouver le layout $layout");

        return $path;
    }


    /**
     * Exécute le layout et envoie le résultat sur la sortie standard.
     *
     * Dans notre cas (LayoutResponse), la méthode travaille en collaboration avec la méthode
     * runAction() de Module. La méthode outputLayout() se contente en fait d'envoyer le layout.
     *
     * Celui-ci, lors de son exécution, va appeller Module::runAction() qui (c'est là qu'est la
     * collaboration) va appeller la méthode outputContent() pour afficher le contenu utile de la
     * réponse.
     *
     * @param object $context le contexte d'exécution du template utilisé comme layout (typiquement
     * le module qui a exécuté la requête).
     *
     * @return LayoutResponse $this
     */
    public function outputLayout($context)
    {
        $this->outputHeaders();

        if (false === ($layout = $this->getLayout()))
            return $this->outputContent();

        Template::runInternal($layout, array(array('this'=>$context)));

        return $this;
    }
}