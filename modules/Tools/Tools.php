<?php
/**
 * Le module tools contient un certain nombre de tâches utilitaires
 * (envoyer un mail, phpinfo, ...)
 */
 
class Tools extends Module
{
    public function actionAjax()
    {
    	var_export(Utils::isAjax());
    }
    
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

    /**
     * action PhpInfo - affiche les informations php
     */
    public function actionPhpInfo()
    {
        phpinfo();
    }

    public function actionTestTask()
    {
        
        // On est "en ligne" : crée la tâche
        if (! User::hasAccess('cli'))
        {
            // si aucun param, afficher le formulaire de choix
            // sinon...
            
            // Vérifie que le gestionnaire de tâche est démarré
            if (! TaskManager::isRunning())
                throw new Exception('Le gestionnaire de tâches n\'est pas démarré.');

            // Ajoute une tâche au gestionnaire de tâches
//            $id=TaskManager::addTask('/tools/testTask',time()-10*60,null,'date dépassée no-repeat');
//            echo "Tâche $id, dépassée<br />"; 

            $id=TaskManager::addTask('/tools/testTask',0,null,'dès que possible no-repeat');
            echo "Tâche $id, dès que possible<br />"; 

            $id=TaskManager::addTask('/tools/testTask', 0, '12 mois','dès que possible, tous les ans');
            echo "Tâche $id, dès que possible, tous les 12 mois<br />"; 

            $id=TaskManager::addTask('/tools/testTask', time(), "12 mois", 'maintenant(time) tous les ans');
            echo "Tâche $id, maintenant(time), tous les 12 mois<br />"; 

            $id=TaskManager::addTask('/tools/testTask', time()+5, "30 sec", "dans 5 sec, toutes les 20 sec");
            echo "Tâche $id, dans 5 secondes, toutes les 30 secondes<br />"; 


            // Redirige vers la page d'état de la tâche
            //Runtime::redirect('/taskmanager/taskstatus?id='.$id);
            return;
        }
        
        // On est en ligne de commande : exécute la tâche
        echo "Démarrage de la tâche de test<br />";
        
        for($i=0; $i<5; $i++)
        {
            TaskManager::progress("Etape $i", 5);
            for($j=1; $j<=5; $j++)
            {
                TaskManager::progress($j);
                sleep(1);
            }
            
        }
        throw new Exception('erreur volontaire');
        echo "Fin de la tâche<br />";
    }
    
    public function actionSlot()
    {
    	Template::run('slot.html');
    }
}

?>
