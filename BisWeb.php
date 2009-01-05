<?php

define('DEFAULT_DB','Ascodocpsy');
define('DEFAULT_DS','ascodocpsy');
define('SEPARATOR', " / ");

function openselection($Equation, $ReadOnly=true, $database=DEFAULT_DB, $dataset=DEFAULT_DS) // TODO : OpenSelection : gérer ReadOnly
{

// on n'utilise plus bis.ini !
// TODO: à mettre en config
$database=Runtime::$root . "data/db/$database.bed"; 
    //com_load_typelib("c:/program files/fichiers communs/Bdsp/BIS/Bis4.dll");
    //echo "TLB chargée";
    $Bis=new COM("Bis.PHPEngine");
    if ($ReadOnly==true)
    {
        try
        {
            $Selection=$Bis->OpenSelection($database, $dataset, $Equation);
        }
        catch (Exception $e)
        {
            die("<pre>Erreur lors de l'ouverture de la base de données : \n" . $e->getMessage());
        }
    }
    else
    {
        $db=$database; // TODO : dans BIS, mettre databasename en byval
        $ds=$dataset; // TODO : dans BIS, mettre datasetname en byval
        try
        {
            $Selection=$Bis->OpenWriteSelection($db, $ds, $Equation);
        }
        catch (Exception $e)
        {
            die("<pre>Erreur lors de l'ouverture de la base de données : \n" . $e->getMessage());
        }
    }
    //$Selection=null;
    //$Bis=null;
    //die('connexion ouvert');
    return $Selection;
}

function getrecord($selection)
{
    $r=array();
    
    for ($i=1; $i<=$selection->fieldscount; $i++)
        $r[$selection->fieldname($i)]=$selection->field($i);
    return $r;
}


/*
 * sauvegarde des données dans la sélection passée en paramètre.
 * Les données effectivement stockées sont retournées par la ou les fonctions
 * de callback données en second argument (séparateur ':').
 * 
 * si une clé d'enreg (REF) a été passée en querystring ou en postdata
 * la notice existante est mise à jour sinon une nouvelle notice est créée
 */
function bis_save($selection, $callbacks='', $ref=null)
{
    $t=explode(':', $callbacks);
    
    if (! $ref)
    {
        $key=$selection->fieldname(1);
        $ref=@$_REQUEST[$key];
    }

    if ($ref)
    {
        $selection->add($ref);
        $selection->edit();
    }
    else
    {
        $selection->addnew();
    }

    for ($i=2; $i <= $selection->fieldscount; $i++)
    {
        $name=$selection->fieldname($i);
        $value=$selection->field($i);
        
        // appelle les callbacks dans l'ordre
        $save=false;
        foreach($t as $function)
        {
            $save |= $function($name, $value);
            //echo "function $function : save=$save<br />";
        }

        if ($save)
        {
            if ( is_array($value) )
            {
                $value=array_filter($value);
                $value=implode(SEPARATOR, $value);               
            }
                
            $selection->setfield($i, $value);
        }
    }
    $selection->update();
    
    return true;
}

/*
 * fonctions callbacks prédéfinies pour bis_save
 */
 /*
function set_post($name, &$value)
{
    @$value=$_POST[$name];
    if ( is_array($value) ) $value=implode(SEPARATOR, $value);
}
*/
function set_dates($name, &$value)
{
    switch ($name)
    {
        case 'Creation':
            if (! $value) $value=date('Ymd');
            break;
                
        case 'LastUpdate':
            $value=date('Ymd');
            break;
            
        default:
            return false;
    };
    return true;
}


?>