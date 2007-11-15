<?php
class DatabaseInspector extends DatabaseModule
{
    public function preExecute()
    {
        if (! $database=Utils::get($_REQUEST['database']))
            throw new Exception('Pour utiliser '.__CLASS__.' la base de données à utiliser doit être indiquée en paramètre');
        Config::set('database', $database);
    }
    
    public function actionSearchForm()
    {
        $this->openDatabase();
        parent::actionSearchForm();
    }
    
    private function showSpaces($value)
    {
        return str_replace
        (
            array(' ', "\t", "\n"),
            array
            (
                '<span class="space"> </span>',
                '<span class="tab"> </span>',
                '<span class="para"> </span><br />',
            ),
            $value
        );
    }
    public function dump($value)
    {
        if (is_null($value))
            return '<span class="value">null</span>';
        if (is_bool($value))
            return '<span class="type">bool</span> <span class="value">'. ($value ? 'true' : 'false') . '</span>';
        if (is_int($value))
            return '<span class="type">int</span> <span class="value">'. $value . '</span>';
        if (is_float($value))
            return '<span class="type">float</span> <span class="value">'. $value . '</span>';
        if (is_string($value))
            return '<span class="type">string('.strlen($value).')</span> <span class="value">' . $this->showSpaces($value) . '</span>';
        if (is_array($value))
            return '<span class="type">array('.count($value).')</span> <span class="value"><ol><li>' . implode('</li><li>', array_map(array($this,'dump'), $value)) . '</span></li></ol>';
        return 'unknown' . var_dump($value); 
    }
}
?>