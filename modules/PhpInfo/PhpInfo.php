<?php
/**
 * Module PhpInfo - affiche la configuration php
 */
class PhpInfo extends Module
{
	public function actionIndex()
    {
        phpinfo();
    }
}
?>
