<?php
/*
  
Fichier de configuration du site web, de l'application
Ce fichier contient uniquement les options de configuration qui ne peuvent
pas être stockées dans les fichiers de configuration.
Par exemple l'option 'cache actif : oui/non' ne peut pas être stockée
dans le fichier de config puisque celui-ci sera compilé avant d'être utilisé et que
pendant la compilation, on va essayer de mettre le résultat dans le cache alors 
que celui-ci n'a pas encore été initialisé. 
  
*/

Config::addArray
(
    array
    (
        // Paramètres du cache
        'cache'=>array
        (
            // Indique si on autorise ou non le cache (true/false)
            'enabled'   => true,
            
            // Path du répertoire dans lequel seront stockés les fichiers 
            // de l'application mis en cache. 
            // Il peut s'agir d'un chemin absolu (c:/temp/cache/) ou
            // d'un chemin relatif à la racine de l'application ($root) 
//            'path'      => 'cache',

            // Path du répertoire dans lequel seront stockés les fichiers 
            // du framework mis en cache. 
            // Il peut s'agir d'un chemin absolu (c:/temp/cache/) ou
            // d'un chemin relatif à la racine du framework ($fabRoot) 
//            'pathforfab'=> 'cachetest'
        )
    )
);
?>
