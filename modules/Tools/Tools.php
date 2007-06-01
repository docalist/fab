<?php
/**
 * Le module tools contient un certain nombre de t�ches utilitaires
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
        TaskManager::progress('Pr�paration du mail � envoyer',10);
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
        mail($to, '[Task] '.$message . ' ('.date('d/m/Y H:i:s').')', 'Message envoy� � ' . date('d/m/Y H:i:s')."\n\nMessage : $message", 'from: daniel.menard@bdsp.tm.fr');
        echo 'Message envoy� : ', $message, "\n";
        TaskManager::progress('termin�');
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
        
        // On est "en ligne" : cr�e la t�che
        if (! User::hasAccess('cli'))
        {
            // si aucun param, afficher le formulaire de choix
            // sinon...
            
            // V�rifie que le gestionnaire de t�che est d�marr�
            if (! TaskManager::isRunning())
                throw new Exception('Le gestionnaire de t�ches n\'est pas d�marr�.');

            // Ajoute une t�che au gestionnaire de t�ches
//            $id=TaskManager::addTask('/tools/testTask',time()-10*60,null,'date d�pass�e no-repeat');
//            echo "T�che $id, d�pass�e<br />"; 

            $id=TaskManager::addTask('/tools/testTask',0,null,'d�s que possible no-repeat');
            echo "T�che $id, d�s que possible<br />"; 

            $id=TaskManager::addTask('/tools/testTask', 0, '12 mois','d�s que possible, tous les ans');
            echo "T�che $id, d�s que possible, tous les 12 mois<br />"; 

            $id=TaskManager::addTask('/tools/testTask', time(), "12 mois", 'maintenant(time) tous les ans');
            echo "T�che $id, maintenant(time), tous les 12 mois<br />"; 

            $id=TaskManager::addTask('/tools/testTask', time()+5, "30 sec", "dans 5 sec, toutes les 20 sec");
            echo "T�che $id, dans 5 secondes, toutes les 30 secondes<br />"; 


            // Redirige vers la page d'�tat de la t�che
            //Runtime::redirect('/taskmanager/taskstatus?id='.$id);
            return;
        }
        
        // On est en ligne de commande : ex�cute la t�che
        echo "D�marrage de la t�che de test<br />";
        
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
        echo "Fin de la t�che<br />";
    }
    
    public function actionSlot()
    {
    	Template::run('slot.html');
    }
}

?>
