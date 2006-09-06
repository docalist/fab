<?php
class Message extends Module
{
    public function preExecute()
    {
        echo Config::get('message');
        return true;
    }
}
?>
