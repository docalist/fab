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

    public function actionSetSchema($db, $schema)
    {
        require_once Runtime::$fabRoot . 'lib/xapian/xapian.php';
        
        $schema=new DatabaseSchema(file_get_contents($schema));
        $schema->compile();

        $db=new XapianWritableDatabase($db, Xapian::DB_OPEN);
        
        $db->set_metadata('fab_structure', '');
        $db->set_metadata('fab_structure_php', '');
        
        $db->set_metadata('schema', $schema->toXml());
        $db->set_metadata('schema_object', serialize($schema));
        
        
    }
}

?>
