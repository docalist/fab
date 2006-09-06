<?php
/* 
 * fabtask est un script php exécuté en ligne de commande sous la forme d'un
 * démon ou d'un service.
 * 
 * Il se charge :
 * 
 * - d'exécuter les tâches plannifiées lorsque leur heure est venue
 * 
 * - d'ajouter ou de retirer une tâche à la liste des tâches à exécuter
 * 
 * Problème : il faut qu'on ait un script qui surveille en permanence l'arrivée
 * de nouvelles tâches à exécuter et l'heure de démarrage des tâches
 * programmées. D'un autre coté, il ne faut pas que le script consomme toutes
 * les ressources du serveur (par exemple en scrutant en permanence un
 * répertoire pour constater qu'il n'y a rien à faire).
 * 
 * La solution que j'ai retenue est la suivante. fabtasks est écrit sous la
 * forme d'un serveur de sockets destiné à être exécuté en ligne de commande
 * (soit à partir d'un shell, soit sous la forme d'un service windows ou d'un
 * démon *nix, soit spawné à partir d'un autre script php)
 * 
 * En général, pour pouvoir utiliser les sockets standards de php, il faut
 * ajouter la librairie 'sockets' dans les extensions de php.ini, ce qui est
 * génant (il faut modifier la conf php). Cependant, il existe deux fonctions
 * stream_socket_server et stream_socket_client qui font partie en standard de
 * php et qui semblent faire l'affaire.
 * 
 * Intérêt des sockets :
 * 
 * - on sait facilement si le gestionnaire de tâches est en cours d'exécution ou
 * non (si ça répond sur le port convenu et que c'est la réponse attendue,
 * c'est le cas.)
 * 
 * - on peut facilement envoyer des commandes au gestionnaire, notamment lui
 * demander de s'arrêter (pas besoin de faire un 'kill', on envoie une commande
 * stop au serveur qui sort de sa boucle.)
 * 
 * - le gestionnaire n'a pas besoin de "surveiller" un fichier ou un répertoire,
 * ce qui est coûteux en ressources et pose des problèmes de réactivité : soit
 * on regarde souvent (toutes les secondes), le serveur est très réactif mais
 * mange beaucoup de ressources ; soit on regarde peu souvent (toutes les 5
 * minutes), c'est peu coûteux, mais le serveur met 5 minutes avant de voir
 * qu'il y a une nouvelle tâche. Là, quand on crée une tâche, on peut envoyer
 * directement une commande au serveur en lui donnant le nom de la tâche à
 * exécuter, et il la reçoit aussitôt. En fait le serveur dort (usage cpu=0%,
 * le programme est bloqué) tant qu'il ne reçoit pas de nouvelle connexion.
 * 
 * - le mécanisme des timeouts présents dans les sockets fait bien notre affaire
 * si on sait que la prochaine tâche à exécuter devra l'être dans une heure, on
 * peut se mettre en attente de connexion avec un time-out de une heure. Si on a
 * une connexion on la traite et on recommence (en ayant calculé le nouveau
 * time-out), sinon, si on est sortit sur time-out, c'est l'heure de lancer la
 * tâche.
 * 
 * 
 */
require_once('../Runtime.php');
Runtime::setup('debug');
die();
function main()
{
    // Détermine les options du gestionnaire de tâches
    $port=85; // Config::get('taskmanager.port', 85);
    $startTime=date('d/m/y H:i:s');
    
    // Démarre le serveur
    $errno=0; // évite warning 'var not initialized'
    $errstr='';
    $socket = stream_socket_server('tcp://127.0.0.1:85', $errno, $errstr);
    if (!$socket)
        die("Impossible de démarrer fabtasks : $errstr ($errno)\n");
    
    $client='';
    while (true)
    {
        // Détermine dans combien de temps la prochaine tâche doit être exécutée
        $timeout=24*60*60; // (en secondes) = 24h
        //$timeout=$taskTime - time();
         
        // Attend que quelqu'un se connecte
        out('en attente de connexion');
        if ($conn = @stream_socket_accept($socket, $timeout, $client))
        {
            // Extrait la requête
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
                    $result='Démarré depuis le ' . $startTime;
                    break;
            	case 'quit': 
                    break;
//                case 'add':
                default: 
                    $result='error';
            }
            
            // Envoie la réponse au client
            out('Commande : <' . $message . '>, Résultat : <' . $result . '>', $client);
            fputs ($conn, $result);
            fclose ($conn);
            
            // Si on a reçu une commande d'arrêt, terminé, sinon on recommence
            if ($cmd=='quit') break;
        }
        
        // On est sorti en time out, a priori, rien à faire
        else
        {
            out('aucune connexion pendant le temps indiqué, mais je tourne toujours !');            	
        }
    }
        
    // Arrête le serveur
    out('Arrêt du gestionnaire de tâches');
    fclose($socket);
}

function out($message, $client=null)
{
    echo date('d/m/y H:i:s');
    if ($client) echo ' (', $client, ')';
    echo ' - ', $message, "\n";
}	

out('Démarrage du gestionnaire de tâches');
date_default_timezone_set(@date_default_timezone_get());
main();
out('Le gestionnaire de tâches est arrêté');
?>