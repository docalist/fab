<?php
/* 
 * fabtask est un script php ex�cut� en ligne de commande sous la forme d'un
 * d�mon ou d'un service.
 * 
 * Il se charge :
 * 
 * - d'ex�cuter les t�ches plannifi�es lorsque leur heure est venue
 * 
 * - d'ajouter ou de retirer une t�che � la liste des t�ches � ex�cuter
 * 
 * Probl�me : il faut qu'on ait un script qui surveille en permanence l'arriv�e
 * de nouvelles t�ches � ex�cuter et l'heure de d�marrage des t�ches
 * programm�es. D'un autre cot�, il ne faut pas que le script consomme toutes
 * les ressources du serveur (par exemple en scrutant en permanence un
 * r�pertoire pour constater qu'il n'y a rien � faire).
 * 
 * La solution que j'ai retenue est la suivante. fabtasks est �crit sous la
 * forme d'un serveur de sockets destin� � �tre ex�cut� en ligne de commande
 * (soit � partir d'un shell, soit sous la forme d'un service windows ou d'un
 * d�mon *nix, soit spawn� � partir d'un autre script php)
 * 
 * En g�n�ral, pour pouvoir utiliser les sockets standards de php, il faut
 * ajouter la librairie 'sockets' dans les extensions de php.ini, ce qui est
 * g�nant (il faut modifier la conf php). Cependant, il existe deux fonctions
 * stream_socket_server et stream_socket_client qui font partie en standard de
 * php et qui semblent faire l'affaire.
 * 
 * Int�r�t des sockets :
 * 
 * - on sait facilement si le gestionnaire de t�ches est en cours d'ex�cution ou
 * non (si �a r�pond sur le port convenu et que c'est la r�ponse attendue,
 * c'est le cas.)
 * 
 * - on peut facilement envoyer des commandes au gestionnaire, notamment lui
 * demander de s'arr�ter (pas besoin de faire un 'kill', on envoie une commande
 * stop au serveur qui sort de sa boucle.)
 * 
 * - le gestionnaire n'a pas besoin de "surveiller" un fichier ou un r�pertoire,
 * ce qui est co�teux en ressources et pose des probl�mes de r�activit� : soit
 * on regarde souvent (toutes les secondes), le serveur est tr�s r�actif mais
 * mange beaucoup de ressources ; soit on regarde peu souvent (toutes les 5
 * minutes), c'est peu co�teux, mais le serveur met 5 minutes avant de voir
 * qu'il y a une nouvelle t�che. L�, quand on cr�e une t�che, on peut envoyer
 * directement une commande au serveur en lui donnant le nom de la t�che �
 * ex�cuter, et il la re�oit aussit�t. En fait le serveur dort (usage cpu=0%,
 * le programme est bloqu�) tant qu'il ne re�oit pas de nouvelle connexion.
 * 
 * - le m�canisme des timeouts pr�sents dans les sockets fait bien notre affaire
 * si on sait que la prochaine t�che � ex�cuter devra l'�tre dans une heure, on
 * peut se mettre en attente de connexion avec un time-out de une heure. Si on a
 * une connexion on la traite et on recommence (en ayant calcul� le nouveau
 * time-out), sinon, si on est sortit sur time-out, c'est l'heure de lancer la
 * t�che.
 * 
 * 
 */
require_once('../Runtime.php');
Runtime::setup('debug');
die();
function main()
{
    // D�termine les options du gestionnaire de t�ches
    $port=85; // Config::get('taskmanager.port', 85);
    $startTime=date('d/m/y H:i:s');
    
    // D�marre le serveur
    $errno=0; // �vite warning 'var not initialized'
    $errstr='';
    $socket = stream_socket_server('tcp://127.0.0.1:85', $errno, $errstr);
    if (!$socket)
        die("Impossible de d�marrer fabtasks : $errstr ($errno)\n");
    
    $client='';
    while (true)
    {
        // D�termine dans combien de temps la prochaine t�che doit �tre ex�cut�e
        $timeout=24*60*60; // (en secondes) = 24h
        //$timeout=$taskTime - time();
         
        // Attend que quelqu'un se connecte
        out('en attente de connexion');
        if ($conn = @stream_socket_accept($socket, $timeout, $client))
        {
            // Extrait la requ�te
            $message= fread($conn, 100);
            $cmd=strtok($message, ' ');
            $param=substr($message, strlen($cmd));
            
            // Traite la commande
            $result='OK';
            switch($cmd)
            {
                case 'running?':
                    $result='yes';
                    break;
                case 'status':
                    $result='D�marr� depuis le ' . $startTime;
                    break;
            	case 'quit': 
                    break;
//                case 'add':
                default: 
                    $result='error';
            }
            
            // Envoie la r�ponse au client
            out('Commande : <' . $message . '>, R�sultat : <' . $result . '>', $client);
            fputs ($conn, $result);
            fclose ($conn);
            
            // Si on a re�u une commande d'arr�t, termin�, sinon on recommence
            if ($cmd=='quit') break;
        }
        
        // On est sorti en time out, a priori, rien � faire
        else
        {
            out('aucune connexion pendant le temps indiqu�, mais je tourne toujours !');            	
        }
    }
        
    // Arr�te le serveur
    out('Arr�t du gestionnaire de t�ches');
    fclose($socket);
}

function out($message, $client=null)
{
    echo date('d/m/y H:i:s');
    if ($client) echo ' (', $client, ')';
    echo ' - ', $message, "\n";
}	

out('D�marrage du gestionnaire de t�ches');
date_default_timezone_set(@date_default_timezone_get());
main();
out('Le gestionnaire de t�ches est arr�t�');
?>