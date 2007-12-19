<?php
/**
 * Module FabWeb - permet de rendre accessible sur le web les
 * fichiers présents dans le répertoire web du framework
 */
class FabWeb extends Module
{
    public function preExecute()
    {
        // types mimes autorisés pour le site fab
        $mimes=array
        (
            '.htm'  => 'text/html',
            '.html' => 'text/html',
            
            '.gif'  => 'image/gif',
            '.jpg'  => 'image/jpeg',
            '.png'  =>  'image/png',
                       
            '.css'  =>  'text/css',
            
            '.js'   =>  'application/x-javascript'    
        );
        
        // Vérifie que le fichier existe
        $path=Runtime::$fabRoot . 'web' . DIRECTORY_SEPARATOR . strtr($this->action, '/', DIRECTORY_SEPARATOR);
        if (! is_file($path)) Routing::notFound();
        
        // Détermine le type mime du fichier
        if (! $mime=Utils::get($mimes[Utils::getExtension($path)]))
            throw new Exception('Type mime non autorisé');
            
        
        // la suite est inspirée de : http://fr2.php.net/header
        // commentaire de pechkin at zeos dot net, 05-May-2006 03:00
        $size = filesize($path);
        $fileDate=filemtime($path);
        $date=gmdate('D, d M Y H:i:s', $fileDate).' GMT';
        
        if (isset($_SERVER['HTTP_RANGE']))  //Partial download
        {
            // parsing Range header
            if (preg_match("/^bytes=(\\d+)-(\\d*)$/", $_SERVER['HTTP_RANGE'], $matches))
            { 
                $from = $matches[1];
                $to = $matches[2];
                if(empty($to))
                {
                    $to = $size - 1;    // -1  because end byte is included
                                        // (From HTTP protocol: 'The last-byte-pos value gives 
                                        // the byte-offset of the last byte in the range; 
                                        // that is, the byte positions specified are inclusive')
                }
                $content_size = $to - $from + 1;
        
                header("HTTP/1.1 206 Partial Content");
                header("Last-Modified: $date");
                header("Content-Range: $from-$to/$size");
                header("Content-Length: $content_size");
                header("Content-Type: $mime");
        
                if($fh = fopen($path, "rb") == false) Routin::notFound();
                $bufsize=20*1024;
                fseek($fh, $from);
                $cur_pos = ftell($fh);
                while($cur_pos !== false && ftell($fh) + $bufsize < $to+1)
                {
                    echo fread($fh, $bufsize);
                    $cur_pos = ftell($fh);
                }
                echo fread($fh, $to+1 - $cur_pos);
                fclose($fh);
           }
           else
           {
               header("HTTP/1.1 500 Internal Server Error");// TODO: plutôt 'invalid request'
               exit;
           }
        }
        else // Usual download
        {
            // Checking if the client is validating his cache and if it is current.
            if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && (strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) <= $fileDate))
            {
                // Client's cache IS current, so we just respond '304 Not Modified'.
                header('Last-Modified: '.$date, true, 304);
                header('DmHeader: not modified');
            }
            else
            {
                header("HTTP/1.1 200 OK");
                header("Last-Modified: $date");
                header("Content-Length: $size");
                header("Content-Type: $mime");
                readfile($path);
            }        
        }                
        Config::set('showdebug', false);
        return true; // indique à fab qu'on a fini, ne pas exécuter d'action

    }
}
?>
