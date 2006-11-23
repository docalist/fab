<?php

/*
 * TextTable objet d'accès à une table au format csv
 * 
 * A partir d'un fichier format CSV , créer un objet qu'on peut parcourir comme un tableau :
 * implémente les interfaces Iterator
 *
 * @param $filePath passé au constructeur est le chemin d'accès au fichier CVS à lire
 */
 class TextTable implements Iterator
 {
    
    private $file=false;        // Descripteur du fichier CVS passé utilisé comme source de données
    private $eof=true;          // True si eof atteint
    private $delimiter=null;    // délimiteur du fichier csv
    private $enclosure=null;    // TODO: faire doc
    private $ignoreEmpty;       // true si on ignore les enregistrements "vides" (contenant seulement $delimiter, espaces ou $enclosure)
    private $header=null;       // La ligne d'entête
    private $record=null;       // L'enreg en cours
    private $line=0;            // Numéro de la ligne enfjdskfds cours 
    
    /*
     * @param $filePath string chemin d'accès au fichier
     * @param $delimiter char délimiteur d'enregistrements (tabulation par défaut)
     * @param $ignoreEmptyRecord bool indiquant si les enregistrements vides sont ignorés (false par défaut)
     */
    public function __construct($filePath, $ignoreEmptyRecord=true, $delimiter="\t", $enclosure='"')
    {
        $this->delimiter=$delimiter;
        $this->enclosure=$enclosure;
        $this->ignoreEmpty = $ignoreEmptyRecord;
        
        if(! is_file($filePath))
            throw new Exception('Fichier non trouvée : ' . $filePath);
                
        $this->file = @fopen($filePath, 'r'); // @ = pas de warning si file not found
        if ($this->file===false)
            throw new Exception('Table non trouvée : '.$filePath);

        // Charge la ligne d'entête contenant les noms de champs
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
        // return le numéro de la ligne en cours
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
     * Lit une ligne dans le fichier en passant éventuellement les lignes vides
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