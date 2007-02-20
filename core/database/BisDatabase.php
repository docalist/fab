<?php

/**
 * @package     fab
 * @subpackage  database
 * @author 		Daniel Ménard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id: Database.php 198 2006-11-15 17:03:39Z dmenard $
 */


/**
 * Représente une base de données Bis
 * 
 * @package     fab
 * @subpackage  database
 */
class BisDatabase extends Database
{
    /**
     * @var COM.Bis.Selection l'objet COM contenant la sélection en cours
     */
    private $selection=null;
    
    /**
     * @var boolean true : afficher en ordre inverse
     */
    private $reverse=false;
    
    /**
     * @var int le nombre de notices pour l'utilisateur
     */ 
    private $count=0;   
    
    /**
     * @var int le "rang" de la notice en cours
     */
    private $rank=0;
    
    /**
     * @var int le rang de la première notice à retourner
     */
    private $start=0;
    
    /**
     * @var int le nombre maximum de notices à retourner
     */
    private $max=0;
    
    private $maxRank=PHP_INT_MAX;
    
    protected function doCreate($database, $def, $options=null)
    {
        throw new Exception('non implémenté');
    }
        
    protected function doOpen($database, $readOnly=true)
    {
        // Ouvre la base de données
        $bis=new COM('Bis.Engine');
        $db=$bis->openDatabase($database, false, $readOnly);
        
        // Détermine le nom du premier (et normallement unique) dataset
        $dataset=$db->datasets(1)->name;
        
        // Ouvre une sélection sur ce dataset
        $this->selection=$db->openSelection($dataset);

        // Crée l'objet record
        $this->record=new BisDatabaseRecord($this, $this->selection->fields);
    }
        
    public function search($equation=null, $options=null)
    {
        
        // a priori, pas de réponses
        $this->eof=true;

        // Analyse les options indiquées (start et sort) 
        if (is_array($options))
        {
            $sort=isset($options['_sort']) ? $options['_sort'] : null;
//            if (is_array($sort))
//                foreach ($sort as $i)
//                    if ($i) { $sort=$i; break;}

            $start=isset($options['_start']) ? ((int)$options['_start'])-1 : 0;
//            if (is_array($start))
//                foreach ($start as $i)
//                    if ($i) { $start=$i; break;}
            if ($start<0) $start=0;

            $max=isset($options['_max']) ? $options['_max'] : 10;
            if (is_array($max))
                foreach ($max as $i)
                    if ($i) { $max=$i; break;}
            if (is_numeric($max))
            {
                $max=(int)$max;
                if ($max<0) $max=10;
            }
            else
                $max=10;
        }
        else
        {
            $sort=null;
            $start=10;
            $max=-1;
        }
        $this->start=$start+1;
        $this->max=$max;
        //echo 'equation=', $equation, ', options=', print_r($options,true), ', sort=', $sort, ', start=', $start, "\n";
        
        // Lance la recherche
        $this->rank=0;
        $this->selection->equation=$equation;
        
        // Pas de réponse ? return false
        $this->count=$this->selection->count();
        if ($this->count==0) return false;
        
        // Si start est supérieur à count, return false
        if ($this->start>$this->count)
        {
            $this->selection->moveLast();
            $this->selection->moveNext();
            return false;	
        }

        $this->rank=$this->start;
        
        $this->maxRank=($this->max==-1 ? PHP_INT_MAX : $this->start+$this->max-1);

        // Gère l'ordre de tri et va sur la start-ième réponse
        switch($sort)
        {
        	case '%':
            case '-': 
                $this->reverse=true;
                $this->selection->moveLast(); 
                while ($start--) $this->selection->movePrevious();
                break;
                
            default:
                $this->reverse=false;
                while ($start--) $this->selection->moveNext();
        }
        
        // Retourne le résultat
        return ! $this->eof=($this->max === 0);
    }

    public function count($countType=0)
    {
    	return $this->count;
    }

    public function searchInfo($what)
    {        
    	switch ($what)
        {
        	case 'equation': return $this->selection->equation;
            case 'rank': return $this->rank;
            case '_start': return $this->start;
            case 'max': return $this->max;
            default: return null;
        }
    }
    
    public function moveNext()
    {
        if ( $this->rank >= $this->maxRank )
            return !$this->eof=true;
            
        $this->rank++;
        if ($this->reverse) 
        {
            $this->selection->movePrevious();
            return !$this->eof=$this->selection->bof;
        }
        else
        {
            $this->selection->moveNext();
            return !$this->eof=$this->selection->eof;
        }
    }

    public function addRecord()
    {
        $this->selection->addNew();
    }

    public function editRecord()
    {
        $this->selection->edit();
    }

    public function saveRecord()
    {
        $this->selection->update();

//        for ($i=1; $i<=$this->selection->fields->count(); $i++)
//        {
//            echo $this->selection->fields($i)->name,
//            ':',
//            $this->selection->fields($i)->value,
//            "<br />\n";
//        }
    }

    public function cancelUpdate()
    {
        $this->selection->cancelUpdate();
    }

    public function deleteRecord()
    {
        $this->selection->delete();
    }
}

/**
 * Représente un enregistrement dans une base {@link BisDatabase}
 * 
 * @package     fab
 * @subpackage  database
 */
class BisDatabaseRecord extends DatabaseRecord
{
    /**
     * @var COM.Bis.Fields Liste des champs de cet enregistrement
     */
    private $fields=null;
    
    /**
     * @var int Numéro du champ en corus (utilisé pour Iterator)
     */
    private $current=0;
    
    /**
     * {@inheritdoc}
     * 
     * @param COM.Bis.Fields $fields l'objet BIS.Fields contenant la liste
     * des champs de la base
     */
    public function __construct(Database $parent, & $fields)
    {
        parent::__construct($parent);
        $this->fields= & $fields;   
    }

    /* <ArrayAccess> */

    public function offsetSet($offset, $value)
    {
        $this->fields[$offset]=$value;
    }

    public function offsetGet($offset)
    {
//        $h=$this->fields[$offset]->value;
//        if (strpos($h, '¨') == false) return $h;
//        return explode('¨', $h);
        
//        if (isChampArticle($offset))
//            $h=explode($h, SepUsedInDatabase());
//            
        return $this->fields[$offset]->value;
        //return (string) $this->fields[$offset]->value;
        //return $this->fields->item($offset)->value;
    }

    public function offsetUnset($offset)
    {
        $this->fields[$offset]=null;
    }

    public function offsetExists($offset)
    {
        return !is_null($this->fields[$offset]);
    }

    /* </ArrayAccess> */
    

    /* <Countable> */

    public function count()
    {
        return $this->fields->count;    
    }

    /* </Countable> */

    
    /* <Iterator> */
    
    public function rewind()
    {
        $this->current=1;
//        echo "Rewind. This.current=", $this->current, "<br />";
    }

    public function current()
    {
//        echo "Appel de current(). This.current=",$this->current," (",$this->fields[$this->current]->name,")","<br />";
        return $this->fields[$this->current]->value;
    }

    public function key()
    {
//        echo "Appel de key(). This.current=",$this->current," (",$this->fields[$this->current]->name,")","<br />";
        return $this->fields[$this->current]->name;
    }

    public function next()
    {
//        echo "Appel de next. This.current passe à ", ($this->current+1),"<br />";
        ++$this->current;
    }

    public function valid()
    {
        return $this->current<=$this->fields->count;
    }
    
    /* </Iterator> */
    
}

?>
