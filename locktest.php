<?php
date_default_timezone_set('Europe/Paris');
while(ob_get_level()) ob_end_flush();

set_time_limit(0);

// logging : le plus simple est d'utiliser file_put_contents('app.log', $data, FILE_APPEND)

// option 2 : simplement en changeant le nom du fichier, on peut créer un fichier
// compressé au format .gz : file_put_contents('compress.zlib://app.log', $data, FILE_APPEND)

// en théorie on devrait aussi pouvoir créer des fichiers zip avec "zip:" mais je n'ai pas réussi

$path=dirname(__FILE__).DIRECTORY_SEPARATOR.'lock.txt';
$path='file:///'.strtr($path,DIRECTORY_SEPARATOR,'/');
$path='compress.zlib://'.$path . '.gz';
echo 'path=', $path, '<br />';

$max=10000;
$start=time();
for($i=1; $i<=$max; $i++)
{
$file=fopen($path, 'a');
    $line=strftime('%x %X') . ', process ' . getmypid() . ', i='.$i.'/'.$max.', ligne de log' . "\n";
    echo $line, '<br />';
    flush();
  
    fputs($file, $line);
    //sleep(1);
fclose($file);
}
echo time()-$start, ' secondes';
?>