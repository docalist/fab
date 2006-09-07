<?php
/*
 * Created on 7 sept. 06
 *
 * To change the template for this generated file go to
 * Window - Preferences - PHPeclipse - PHP - Code Templates
 */
require_once (Runtime::$fabRoot.'lib/xapian/xapian.php');

class XapianDefinition
{
    private $def=null;
    
    public function load($path)
    {
        $this->def=Utils::loadYaml($path);
    }
    
    public function valid()
    {
    	if (is_null($this->def)) 
            return "Aucune définition n'a été chargée";
        
        if ($error=$this->validFields() !== true)
            return $error;
                
        return true;
    }
    
    private function validFields()
    {
    	if (! isset($this->def['fields']))
            return 'Section \'fields\' inexistante';

        $fields=$this->def['fields'];
        
        if (count($fields))
            return 'La section fields ne contient aucun champ';
        
        foreach($fields as $name=>$field)
        {
        	if ($error=$this->validField($name, $field) !==true)
                return $error;
        }

        return true;
    }
    
    private function validField($name, $field)
    {
    	if (! isset($field['type']))
            return "Le type du champ $name n'a pas été indiqué";
        $type=$field['type'];
        switch($type)
        {
        	case 'text': break;
            case 'memo': break;
            case 'int': break;
            case 'bool': break;
            case 'date': break;
            case 'datetime': break;
            default: return "Type de champ incorrect pour $name";
        }
        return true;
    }
}

class XapianDatabase extends Module
{
    public function actionIndex()
    {
        echo "XapianDatabase, index";
    }

    public function actionCreateDatabase()
    {
    	echo "create";
        $def=new XapianDefinition();
        $def->load(dirname(__FILE__) . '/db.yaml');
        echo $def->valid();
    }	

}
?>
