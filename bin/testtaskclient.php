<?php

send_message('127.0.0.1','86','Message to send...');

function send_message($ipServer,$portServer,$message)
{
  $fp = @stream_socket_client("tcp://$ipServer:$portServer", $errno, $errstr, 0.5);
  if (!$fp)
  {
     echo "ERREUR : $errno - $errstr<br />\n";
  }
  else
  {
     fwrite($fp,"$message\n");
     $response =  fread($fp, 4);
     if ($response != "OK\n")
       {echo 'The command couldn\'t be executed...\ncause :'.$response;}
     else
       {echo 'Execution successfull...';}
     fclose($fp);
  }
}
?>