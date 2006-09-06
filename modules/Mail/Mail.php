<?php
class Mail extends Module
{
	public function actionTo()
    {
        TaskManager::progress('Préparation du mail à envoyer',10);
        for ($i=1; $i<=10; $i++)
        {
            TaskManager::progress($i,"notice $i");
            echo 'i=', $i, '<br />', "\n";
            sleep(1);
        }

        TaskManager::progress('Mise en attente inutile', 20);
        for ($i=1; $i<=20; $i++)
        {
            TaskManager::progress($i, "inutile $i");
            sleep(2);
        }

        TaskManager::progress('Envoi du message');

    	$to='daniel.menard@bdsp.tm.fr';
        $message=Utils::get($_REQUEST['body'], '(vide)');
        mail($to, '[Task] '.$message . ' ('.date('d/m/Y H:i:s').')', 'Message envoyé à ' . date('d/m/Y H:i:s')."\n\nMessage : $message", 'from: daniel.menard@bdsp.tm.fr');
        echo 'Message envoyé : ', $message, "\n";
        TaskManager::progress('terminé');
    }
}
?>
