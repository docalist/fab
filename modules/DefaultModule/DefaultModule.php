<?php
/*
 * Created on 22 mai 06
 *
 * To change the template for this generated file go to
 * Window - Preferences - PHPeclipse - PHP - Code Templates
 */
class DefaultModule extends Module
{
	public function actionIndex()
    {
    	echo "<p>Bravo, vous affichez le résultat de l'action par défaut du module par défaut !</p>";
        print_r($_COOKIE);
    }
}
?>
