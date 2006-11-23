<?php

/*
 * TextTable objet d'acc�s � une table au format csv
 * 
 * A partir d'un fichier format CSV , cr�er un objet qu'on peut parcourir comme un tableau :
 * impl�mente les interfaces Iterator
 *
 * @param $filePath pass� au constructeur est le chemin d'acc�s au fichier CVS � lire
 */
 class TextTable implements Iterator
 {
    
    private $file=false;        // Descripteur du fichier CVS pass� utilis� comme source de donn�es
    private $eof=true;          // True si eof atteint
    private $delimiter=null;    // d�limiteur du fichier csv
    private $enclosure=null;    // TODO: faire doc
    private $ignoreEmpty;       // true si on ignore les enregistrements "vides" (contenant seulement $delimiter, espaces ou $enclosure)
    private $header=null;       // La ligne d'ent�te
    private $record=null;       // L'enreg en cours
    private $line=0;            // Num�ro de la ligne enfjdskfds cours 
    
    /*
     * @param $filePath string chemin d'acc�s au fichier
     * @param $delimiter char d�limiteur d'enregistrements (tabulation par d�faut)
     * @param $ignoreEmptyRecord bool indiquant si les enregistrements vides sont ignor�s (false par d�faut)
     */
    public function __construct($filePath, $ignoreEmptyRecord=true, $delimiter="\t", $enclosure='"')
    {
        $this->delimiter=$delimiter;
        $this->enclosure=$enclosure;
        $this->ignoreEmpty = $ignoreEmptyRecord;
        
        if(! is_file($filePath))
            throw new Exception('Fichier non trouv�e : ' . $filePath);
                
        $this->file = @fopen($filePath, 'r'); // @ = pas de warning si file not found
        if ($this->file===false)
            throw new Exception('Table non trouv�e : '.$filePath);

        // Charge la ligne d'ent�te contenant les noms de champs
        $this->header=$this->getLine();
    }
    
    public function __destruct()
    {
        if($this->file)
        {
            fclose($this->file);
            $this->file=false;
            $this->line=0;
            $this->delimiter="\t";
            $this->enclosure='"';
            $this->ignoreEmpty=true;
        }
    }
    
    // Interface Iterator
    public function rewind()
    {
        $this->next();
    }
 
    public function current()
    {
        return $this->record;
    }
     
    public function key()
    {
        // return le num�ro de la ligne en cours
        return $this->line;
    }
     
    public function next()
    {
        // Charge la ligne suivante
        if (false === $this->record=$this->getLine()) return;
        $this->record=array_combine($this->header, $this->record);
        ++$this->line;
        if (!isset($this->header['line']))
            $this->record['line']=$this->line;
    }
     
    public function valid()
    {
        return !$this->eof;
    }

    /*
     * Lit une ligne dans le fichier en passant �ventuellement les lignes vides
     * 
     * @return mixed false si eof ou fichier non ouvert, un tableau contenant la 
     * ligne lue sinon
     */
    private function getLine()
    {
        $this->eof=false; 
        for(;;)
        {
            if ((! $this->file) or (feof($this->file))) break; 
            $t=fgetcsv($this->file, 1024, $this->delimiter, $this->enclosure);
            if ($t===false) break;
            if (! $this->ignoreEmpty) return $t;
            if (trim(implode('', $t), ' ')!=='') return $t;
        }
        $this->eof=true;
        return false;
    }
}
// 
// 
// 
//echo '<pre>';
//
//$t=new TextTable(dirname(__FILE__).'\Pays.txt');
//foreach ($t as $line=>$record)
//    echo "Ligne $line : $record[Code]=$record[Label]\n";
?>