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
        return parent::actionSearchForm();
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

    public function guessLookupTable($indexOrAlias)
    {
        $dbs=$this->selection->getSchema();
        $bestnb=0;
        $besttable='';

        // Cas 1 : c'est un index de base
        if (! isset($indexOrAlias->indices))
        {
            $fields=$indexOrAlias->fields;
        }

        // Cas 2 : c'est un alias
        else
        {
            $fields=array();
            foreach($indexOrAlias->indices as $name=>$index)
            {
                foreach($dbs->indices[$name]->fields as $name=>$field)
                {
                    $fields[$name]=true;
                }
            }
        }

        foreach($dbs->lookuptables as $lookupname=>$lookuptable)
        {
            $nb=0;
            foreach($lookuptable->fields as $fieldname=>$field)
            {
                if (isset($fields[$fieldname])) ++$nb;
            }
            if ($nb>$bestnb)
            {
                $bestnb=$nb;
                $besttable=$lookupname;
            }
        }
        if ($bestnb<count($fields)) return null;
        return $besttable;
    }
}
?>