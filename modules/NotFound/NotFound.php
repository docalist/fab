<?php
/**
 * Module NotFound - retourne une erreur '404 - fichier non trouvé'
 */
class NotFound extends Module
{
    public function preExecute()
    {
        header("HTTP/1.0 404 Not Found");
        $this->setLayout('error.htm');
//        $this->addCSS('main.css');
//        $this->addCSS('help.css');
//        $this->addJavascript('library.js');
//        $this->addJavascript('controls.js');
        $this->setTitle('Erreur 404');
    }

	public function actionIndex()
    {
        Template::run('NotFound.htm');
        //echo '<pre>';
        //debug_print_backtrace();
    }
}
?>
