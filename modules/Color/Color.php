<?php
require Runtime::$fabRoot . 'modules/Message/Message.php';

class Color extends Message
{
    public function preExecute()
    {
        echo 'je suis ' . Config::get('color');
        echo ' et le message en cours est [' . Config::get('message') . ']';
        return true;
    }
}
?>
