<?php
/*
  
Fichier de configuration du site web, de l'application
Ce fichier contient uniquement les options de configuration qui ne peuvent
pas être stockées dans les fichiers de configuration yaml.
Par exemple l'option 'cache actif : oui/non' ne peut pas être stockée
dans le fichier yaml puisque celui-ci sera compilé avant d'être utilisé et que
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
            // Si cette clé est vide ou absente, fab essaiera de stocker
            // les fichiers dans le répertoire temporaire du système 
            // (/tmp, c:\temp...), sous-répertoire 'fabapps' puis sous
            // répertoire correspondant au nom de l'application
            // Dans tous les cas, deux sous-répertoires seront créés,
            // un pour fab, un pour l'application  
            'path'      => 'c:\\temp',
        )
    )
);
?>
