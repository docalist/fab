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
    private $reverse=false; // true : afficher en ordre inverse
    private $count=0;   // le nombre de notices pour l'utilisateur (=count-start)
    private $rank=0; // le "rang" de la notice en cours
    private $fields=null; // un raccourci vers $this->selection->fields
    private $fieldsIterator=null;
    
    private $start=0;
    private $nb=0;
    
    protected function doCreate($database, $def, $options=null)
    {
        throw new Exception('non implémenté');
    }
        
    protected function doOpen($database, $readOnly=true)
    {
        $bis=new COM("Bis.Engine");
        $dataset='ascodocpsy';
        if ($readOnly)
            $this->selection=$bis->openSelection($database, $dataset);
        else
            $this->selection=$bis->OpenDatabase($database, false, false)->openSelection($dataset);
        unset($bis);
        $this->fields=$this->selection->fields;
        $this->fieldsIterator=new BisDatabaseRecord($this, $this->fields);
    }
        
    public function search($equation=null, $options=null)
    {
        // a priori, pas de réponses
        $this->eof=true;

        // Analyse les options indiquées (start et sort) 
        if (is_array($options))
        {
            $sort=isset($options['sort']) ? $options['sort'] : null;
            $start=isset($options['start']) ? ((int)$options['start'])-1 : 0;
            if ($start<0) $start=0;
            $nb=isset($options['nb']) ? ((int)$options['nb']) : 10;
            if ($nb<1) $nb=1;
        }
        else
        {
            $sort=null;
            $start=10;
        }
        $this->start=$start+1;
        $this->nb=$nb;
        //echo 'equation=', $equation, ', options=', print_r($options,true), ', sort=', $sort, ', start=', $start, "\n";
        
        // Lance la recherche
        $this->rank=0;
        $this->selection->equation=$equation;
        
        // Pas de réponse ? return false
        $this->count=$this->selection->count();
        if ($this->count==0) return false;
        
        // Si start est supérieur à count, return false
        if ($this->count<0 or $this->count<=$start)
        {
            $this->selection->moveLast();
            $this->selection->moveNext();
            return false;	
        }
        
        $this->rank=$start+1;
        
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
        $this->eof=false;
        return true;
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
            case 'start': return $this->start;
            case 'nb': return $this->nb;
            default: return null;
        }
    }
    
    public function moveNext()
    {
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

    public function fields()
    {
        return $this->fieldsIterator;
    }

    protected function getField($offset)
    {
//        return $this->selection->fields->item($which)->value;
//        return $this->selection[$offset];
        return $this->fields[$offset];
    }
    protected function setField($offset, $value)
    {
        $this->selection[$offset]=$value;
    }
    
    public function add()
    {
        $this->selection->addNew();
    }
    public function edit()
    {
        $this->selection->edit();
    }
    public function save()
    {
        $this->selection->save();
    }
    public function cancel()
    {
        $this->selection->cancelUpdate();
    }
    public function delete()
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
    private $fields=null;
    private $current=1;
    
    public function __construct(Database $parent, & $fields)
    {
        parent::__construct($parent);
        $this->fields= & $fields;   
    }
    
    /* Début de l'interface Countable */

    public function count()
    {
        return $this->fields->count;    
    }
    
    /* Fin de l'interface Countable */

    
    /* Début de l'interface Iterator */

    public function rewind()
    {
        $this->current=1;
    }

    public function current()
    {
        return $this->fields[$this->current]->value;
    }

    public function key()
    {
        return $this->fields[$this->current]->name;
    }

    public function next()
    {
        ++$this->current;
    }

    public function valid()
    {
        return $this->current<=$this->fields->count;
    }
    
    /* Fin de l'interface Iterator */
    
}

?>
