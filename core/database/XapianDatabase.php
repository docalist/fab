<?php

/**
 * @package     fab
 * @subpackage  database
 * @author      Daniel M�nard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id: Database.php 198 2006-11-15 17:03:39Z dmenard $
 */

require_once Runtime::$fabRoot . 'lib/xapian/xapian.php';

/**
 * Repr�sente une base de donn�es Xapian
 * 
 * @package     fab
 * @subpackage  database
 */
class XapianDatabaseDriver extends Database
{
    // Num�ro de l'enreg qui contient une copie de la structure de la base
    const ConfigDocId=1; // = 4294967295 = (2^32)-1
    
    // l'objet XapianDatabase
    private $xapianDatabase=null;
    
    private $structure=null;
    
    private $fields=array();
    
    /**
     * @var XapianQueryParser l'analyseur d'�quations de xapian
     * @access private
     */
    private $queryParser=null; // l'analyseur d'�quations

    /**
     * @var XapianEnquire l'environnement de recherche. Vaut null tant que search n'a pas �t� appell�e.
     * @access private
     */
    private $enquire=null; // l'environnement de recherche

    /**
     * @var XapianMSet les r�sultats de la recherche. Vaut null tant que search n'a pas �t� appell�e.
     * @access private
     */
    private $mset=null; // la s�lection

    /**
     * @var XapianIterator L'iterateur sur les r�sultats de la recherche.
     * Vaut null tant que search n'a pas �t� appell�e
     * @access private
     */
    private $iterator=null; // le msetiterator
    
    /**
     * @var XapianDocument le document en cours parmi les r�sultats de la recherche
     */
    private $doc=null;
    
    private $parseOptions=0; // options donn�es � parse_Query
    
    
    /**
     * G�n�re une chaine repr�sentant un entier encod� avec un nombre variable d'octets.
     * Les chaines obtenues respecte l'ordre de tri des entiers.
     * 
     * La chaine g�n�r�e contient 1 octet de longueur indiquant le nombre de caract�res 
     * utilis�s pour repr�senter l'entier puis autant de les octets (MSB en premier) 
     * repr�sentant l'entier
     * 
     * Exemples :
     * 0 = 0                    // un octet
     * 1 = 1 1                  // deux octets
     * 2 = 1 2
     * ...
     * 255 = 1 255
     * 256 = 2 1 0              // trois octets
     * 257 = 2 1 1
     * ...
     * 511 = 2 1 255
     * 512 = 2 2 0
     * ...
     * 65535 = 2 255 255
     * 65536 = 3 1 0 0          // quatre octets
     * 65537 = 3 1 0 1
     * ...
     * 16777215 = 3 255 255 255
     * 16777216 = 4 1 0 0 0     // cinq octets
     * 16777217 = 4 1 0 0 1
     * ...
     * 
     * @param int $value l'entier � encoder
     * @param string $buffer optionnel : la chaine dans laquelle le r�sultat
     * doit �tre �crit.
     * @param int $i optionnel : la position dans $string � laquelle le r�sultat
     * sera �crit. $i est incr�ment� en sortie pour permettre des appels successifs
     * � la fonction.
     */
    private static function writeSortableVInt($value, & $buffer='', &$i=0)
    {
        settype($value, 'integer');
        $result='';
        while ($value != 0)
        {
            $part = $value & 0xff;
            $value = $value >> 8;
            $result=chr($part).$result;
        }
        $length=strlen($result);
        $result=chr($length).$result;
        $buffer=substr_replace($buffer, $result, $i, $length+1);
        $i+=$length+1;
        return $result;
    }
    
    /**
     * D�code une chaine contenant un entier encod� avec la fonction {@link writeSortableVInt}.
     * 
     * @param string $buffer la chaine � d�coder
     * @param int $i optionnel : la position dans $string � partir de laquelle le d�codage doit
     * d�marrer. $i est incr�ment� en sortie pour permettre des appels successifs � la fonction.
     */
    private static function readSortableVInt($buffer, & $i=0)
    {
        $next = $i + ord($buffer[$i++]);
        for($result = 0; $i<$next; $i++)
            $result = ($result << 8) + ord($buffer[$i]);
        return $result;
    }
    
    /**
     * Ecrit une chaine repr�sentant un entier encod� sous forme d'un nombre
     * variable d'octets.
     * 
     * @param int $value l'entier � encoder
     * @param string $string optionnel : la chaine dans laquelle le r�sultat
     * doit �tre �crit.
     * @param int $i optionnel : la position dans $string � laquelle le r�sultat
     * sera �crit. $i est incr�ment� en sortie pour permettre des appels successifs
     * � la fonction.
     */
    private static function writeVInt($value, & $buffer='', & $i=0)
    {
        settype($value, 'integer');
        $result='';
        while ($value > 0x7F) 
        {
            $result.=chr( ($value & 0x7F)|0x80 );
            $value >>= 7;
        }
        $result.=chr($value);
        $length=strlen($result);
        $buffer=substr_replace($buffer, $result, $i, $length);
        $i+=$length;
        return $result;
    }
    
    /**
     * D�code une chaine contenant un entier encod� avec la fonction {@link writeVInt}.
     * 
     * @param string $string la chaine � d�coder
     * @param int $i optionnel : la position dans $string � partir de laquelle le d�codage doit
     * d�marrer. $i est incr�ment� en sortie pour permettre des appels successifs � la fonction.
     */
    private static function readVInt($buffer, & $i=0)
    {
        $nextByte = ord($buffer[$i++]);
        $val = $nextByte & 0x7F;
        
        for ($shift=7; ($nextByte & 0x80) != 0; $shift += 7)
        {
            $nextByte = ord($buffer{$i++});
            $val |= ($nextByte & 0x7F) << $shift;
        }
        return $val;
    }
    
    private static function writeString($string, & $buffer='', & $i=0)
    {
        $j=$i;
        $length=strlen($string);
        self::writeVInt($length, $buffer, $i);
        $buffer=substr_replace($buffer, $string, $i, $length);
        $i+=$length;
        return substr($buffer, $j, $i-$j);
    }
    
    private static function readString($buffer, & $i=0)
    {
        if (0 == $length=self::readVInt($buffer, $i)) return '';
        $result=substr($buffer, $i, $length);
//        echo 'readString. i=', $i, ', length=', $length, 'value=[', $result, ']<br />';
        $i+=$length;
        return $result;
    }
    
    private static function structure(& $structure)
    {
        // V�rifie que c'est bien une structure de base de donn�es
        if (! is_array($structure) or 0 == count($structure))
            throw new Exception('Structure incorrecte, tableau non vide attendu');
            
        if (! isset($structure['fields']) or ! is_array($structure['fields']) or 0==count($structure['fields']))
            throw new Exception('Structure incorrecte, aucun champ d�fini');
        
        // Initialise les attributs non d�finis � leur valeur par d�faut
        if (! isset($structure['sep'])) $structure['sep']=';';
        if (! isset($structure['stopwords'])) $structure['stopwords']='';
        
        // Num�rote les champs et les index
        $fields=$structure['fields'];
        $structure['fields']=array();
        $structure['fieldbyname']=array();
        $structure['indexbyname']=array();
        $fieldNumber=1;
        $indexNumber=1;
        foreach ($fields as $field)
        {
            if (! isset($field['name']))
                throw new Exception('Le champ num�ro '.$fieldNumber.' sans nom');

            $field['number']=$fieldNumber;
             
            if (! isset($field['type'])) $field['type']='text';
            if (! isset($field['multiple'])) $field['multiple']=false;
            if (! isset($field['sep'])) $field['sep']=& $structure['sep'];
            if (! isset($field['label'])) $field['label']=$field['name'];
            if (! isset($field['stopwords'])) $field['stopwords']=& $structure['stopwords'];


            $indexed=
                (isset($field['indexwords']) and $field['indexwords']==true)
             or (isset($field['indexvalues']) and $field['indexvalues']==true)
             or (isset($field['indexvaluescount']) and $field['indexvaluescount']==true);
            $field['indexed']=$indexed;
            
            if ($indexed)
            {
                if (! isset($field['indexname'])) $field['indexname']=$field['name'];
                $indexname=$field['indexname'];
                if (isset($structure['indexbyname'][$indexname]))
                    $field['indexprefix']=$structure['indexbyname'][$indexname];
                else
                    $field['indexprefix']=$structure['indexbyname'][$indexname]=($indexNumber++).':';
            }
            
            $structure['fields'][$fieldNumber]=$field;
            if (isset($structure['fieldbyname'][$field['name']]))
                throw new Exception('Plusieurs champs avec le m�me nom : ' . $field['name']);
                
            $structure['fieldbyname'][$field['name']]= & $structure['fields'][$fieldNumber];
            $fieldNumber++;
        }
        
/*

    ,
    'alias'=>array                      // Noms d'index suppl�mentaires (synonymes, index de regroupements)
    (
        'org'=>'AUTCOLL',                   // simple synonyme une recherche "org=xxx" fait la m�me chose que "autcoll=xxx"
        'au'=>array('AUT','AUTS')           // index de regroupement : "mcl=xxx" fait la m�me chose que "motscles=xxx OR nouvdesc=xxx"
    )
    ,
    'views'=>array                      // � �tudier, juste pour garder l'id�e
    (
        'article'=>array
        (
        ),
        'ouvrage'=>array
        (
        )
    ),
    
    // � partir de la structure de base ci-dessus, un certains nombre de choses sont calcul�es pour optimiser les traitements
    'fieldsbyname'=>array           // � partir du nom, donne le num�ro. peut aussi servir � indiquer l'ordre des champs
    (
        'AUT'=>0,          // ie r�f�rence sur database['fields'][0]
        'AUTCOLL'=>1
    )
    ,
    'indexprefixes'=>array          // liste de tous les index disponibles et pr�fixe(s) des tokens de chaque
    (
        'AUT'=>'X0:',
        'AUTCOLL'=>'X1:',
        // ...
        'org'=>'X1:',
        'au'=>array('X0:','X1:')
    )
  
 */        
    }
    
    protected function doCreate($path, $structure, $options=null)
    {
        self::structure($structure);
//        echo '<pre>';
//        var_export($structure);
        //die();
        // Cr�e la base xapian
        putenv('XAPIAN_PREFER_FLINT=1'); // uniquement pour xapian < 1.0
        echo "cr�ation de la base...<br />";
        $this->xapianDatabase=new XapianWritableDatabase($path, Xapian::DB_CREATE_OR_OVERWRITE); // TODO: remettre DB_CREATE
        echo "base xapian cr��e !<br />";
        
        // Enregistre la structure de la base
        echo "enregistrement de la structure...<br />";
        $h=rtrim($path, '/').'/structure.php';
        file_put_contents($h, "<?php\n return " . var_export($structure, true) . "\n?>");
        echo "structure enregistr�e !<br />";
        
        // Enregistre la structure de la base dans l'enreg #1
        $doc=new XapianDocument();
        $doc->set_data(serialize($structure));
        $this->structure=$structure;
        $doc->add_term('@structure');
//        $this->xapianDatabase->add_document($doc);
// 
        $this->xapianDatabase->replace_document(self::ConfigDocId, $doc);
        $this->xapianDatabase->flush();
        //unset($this->xapianDatabase);
        $this->record=new XapianDatabaseRecord($this, $this->fields);
        
        // TODO : reset de toutes les propri�t�s
        
    }
        
    protected function doOpen($path, $readOnly=true)
    {
        // Charge la structure de la base
//        $h=rtrim($path, '/').'/structure.php';
//        $structure=file_get_contents($h);
        echo 'doOpen<br />';
        // Ouvre la base xapian
        if ($readOnly)
        {
            echo "Ouverture de $path en readonly<br />";
            $this->xapianDatabase=new XapianDatabase($path);
        }
        else
        {  
            echo "Ouverture de $path en read/write<br />";
            $this->xapianDatabase=new XapianWritableDatabase($path, Xapian::DB_OPEN);
        }

        // Charge la structure de la base
        echo "Chargement de la structure<br />";
        $this->structure=unserialize($this->xapianDatabase->get_document(self::ConfigDocId)->get_data());
        
        foreach ($this->structure['fieldbyname'] as $name=>$field)
            $this->fields[$name]=null;
        $this->record=new XapianDatabaseRecord($this, $this->fields);
            
        // TODO : reset de toutes les propri�t�s
            
    }
    
    private function setupSearch()
    {
        // Initialise l'environnement de recherche
        $this->enquire=new XapianEnquire($this->xapianDatabase);
        
        // Initialise le QueryParser
        $this->parser=new XapianQueryParser();
        
        // Initialise la liste des noms d'index reconnus dans les �quations et associe le pr�fixe correspondant
        foreach($this->structure['indexbyname'] as $name=>$prefix)
        {
            //$this->parser->add_boolean_prefix($name, $prefix);
            $this->parser->add_prefix($name, $prefix);
        }

        // Initialise le stopper (suppression des mots-vides)
        $stopper=new XapianSimpleStopper();
        foreach (explode(' ', $this->structure['stopwords']) as $stopWord)
            $stopper->add($stopWord);
        
        echo 'stopper : ', $stopper->get_description(), ' (non appliqu�s pour le moment, bug xapian/apache)<br />';
        
//        $this->parser->set_stopper($stopper); // TODO : segfault
    
        $this->parser->set_database($this->xapianDatabase); // indispensable pour FLAG_WILDCARD
    }

    
    public function search($equation=null, $options=null)
    {
        // a priori, pas de r�ponses
        $this->eof=true;

        // Analyse les options indiqu�es (start et sort) 
        if (is_array($options))
        {
            $sort=isset($options['_sort']) ? $options['_sort'] : null;
            $start=isset($options['_start']) ? ((int)$options['_start'])-1 : 0;
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
        
        
        // Met en place l'environnement de recherche lors de la premi�re recherche
        if (is_null($this->enquire)) $this->setupSearch();

        // Construit la requ�te
        $equation=strtr($equation,'=',':');
        echo "Equation donn�e � xapian : ", $equation, "<br />";
        
        $query=$this->parser->parse_Query
        (
            $equation, 
            XapianQueryParser::FLAG_BOOLEAN |
            XapianQueryParser::FLAG_PHRASE | 
            XapianQueryParser::FLAG_LOVEHATE |
            XapianQueryParser::FLAG_BOOLEAN_ANY_CASE |
            XapianQueryParser::FLAG_WILDCARD
        );
//     echo "here"; return;    
        
        $this->equation=$query->get_description();
        echo "Equation comprise par xapian : ", $this->equation, "<br />"; 
        // g�n�rer la liste des mots ignor�s dans la requ�te

        // D�finit l'ordre de tri des r�ponses
        $this->setSortOrder($sort);
        
        // Ex�cute la requ�te
        $this->enquire->set_query($query);

        $this->mset=$this->enquire->get_MSet($start, $max);
        $this->count=$this->mset->get_matches_estimated();
        echo 'Nb de r�ponses : ' , $this->count, '<br />';
        echo 'start : ', $start, ', max=', $max, '<br />';
//        $this->moveFirst();
//        if (is_null($this->mset)) return;
        //echo "mset.size=", $this->mset->size(), "\n";
        $this->iterator=$this->mset->begin();
        if ($this->eof=$this->iterator->equals($this->mset->end())) { echo 'eof atteint d�s le d�but<br />'; return false;} 
        $this->loadDocument();
        echo 'first doc loaded<br />';
        $this->eof=false;              
        // Retourne le r�sultat
        return true;
    }
    
    private function setSortOrder($sort=null)
    {
        // D�finit l'ordre de tri
//     *     - '%' : trier les notices par score (la meilleure en t�te)
//     *     - '+' : trier par ordre croissant de num�ro de document
//     *     - '-' : trier par ordre d�croissant de num�ro de document
//     *     - 'xxx+' : trier sur le champ xxx, par ordre croissant
//     *     - 'xxx-' : trier sur le champ xxx, par ordre d�croissant
//     *     - 'xxx+%' : trier sur le champ xxx par ordre croissant, puis par
//     *       pertinence.
//     *     - 'xxx-%' : trier sur le champ xxx par ordre d�croissant, puis par
//     *       pertinence.
        switch ($sort)
        {
            case '%':
                echo 'Tri : par pertinence<br />';
                $this->enquire->set_Sort_By_Relevance();
                break;
                
            case '+':
                echo 'Tri : par docid croissants<br />';
                $this->enquire->set_weighting_scheme(new XapianBoolWeight());
                $this->enquire->set_DocId_Order(XapianEnquire::ASCENDING);
                break;

            case '-':
            case null:
                echo 'Tri : par docid d�croissants<br />';
                $this->enquire->set_weighting_scheme(new XapianBoolWeight());
                $this->enquire->set_DocId_Order(XapianEnquire::DESCENDING);
                break;
                
            default:
                if 
                (
                    0==preg_match('~(%?)([+-]?)([a-z]+)([+-]?)(%?)~i', $sort, $matches) 
                    or 
                    ($matches[1]<>'' and $matches[5]<>'') // le % figure � la fois au d�but et � la fin
                    or 
                    ($matches[2]<>'' and $matches[4]<>'') // le +/- figure � la fois avant et apr�s le nom du champ
                )
                    throw new Exception('Ordre de tri incorrect, syntaxe non reconnue : ' . $sort);
                $sortField=$matches[3];
                if (! isset($this->structure['fieldbyname'][$sortField])
                    or ! isset($this->structure['fieldbyname'][$sortField]['sortable'])
                    or $this->structure['fieldbyname'][$sortField]['sortable']===false)
                    throw new Exception('Impossible de trier sur le champ indiqu� : ' . $sortField);
                $fieldNumber=$this->structure['fieldbyname'][$sortField]['number'];
                $order = ((($matches[2]==='-') || ($matches[4]) === '-')) ? XapianEnquire::DESCENDING : XapianEnquire::ASCENDING;
                if ($matches[1])        // trier par pertinence puis par champ
                {
                    echo 'Tri : par pertinence puis par ', $sortField, ($order ? ' croissants': ' d�croissants'),'<br />';
                    $this->enquire->set_sort_by_relevance_then_value($fieldNumber, $order);
                }
                elseif ($matches[5])    // trier par champ puis par pertinence
                { 
                    echo 'Tri : par ', $sortField, ($order ? ' croissants': ' d�croissants'),' puis par pertinence.<br />';
                    $this->enquire->set_sort_by_value_then_relevance($fieldNumber, $order);
                }
                else                    // trier par champ uniquement
                {                        
                    echo 'Tri : par ', $sortField, ($order ? ' croissants': ' d�croissants'),'<br />';
                    $this->enquire->set_sort_by_value($fieldNumber, $order);
                }
        }
    }

    private function loadDocument()
    {
        if (is_null($this->iterator))
            throw new Exception('Pas de document courant');

        if ($this->iterator->equals($this->mset->end()))
        {
            $this->doc=null;
        }
        else
        {
            $this->doc=$this->iterator->get_document();
            $this->unserializeFields($this->doc->get_data());
        }
    } 

    /**
     * Retourne une chaine contenant la version serialis�e de $this->fields, telle
     * qu'elle est stock�e dans les documents (set_data)
     * 
     * @return string la chaine obtenue
     */
    private function serializeFields()
    {
//        static $buffer=''; // static : la taille va augmenter dynamiquement, �vite les r�allocations
        $buffer='';
        $i=0;
        foreach($this->fields as $name=>& $data)
        {
            if (! is_null($data) && ($data !== '') && ($data !== false) && ($data !== 0) && ($data !== 0.0))
            {
                // Ecrit le num�ro du champ
                self::writeVInt((int) $this->structure['fieldbyname'][$name]['number'], $buffer, $i);
                
                // Ecrit le contenu du champ
                if (is_array($data))
                {
                    // Ecrit le nombre de valeurs
                    self::writeVInt(count($data), $buffer, $i);
                    
                    // Ecrit chacune des valeurs
                	foreach($data as $item)
                        self::writeString($item, $buffer, $i);
                }
                else
                {
                	// Une seule valeur
                    self::writeVInt(1, $buffer, $i);
                    
                    // Ecrit le contenu du champ
                    self::writeString((string)$data, $buffer, $i);
                } 
            }
        }
        return $buffer;
    }

    /**
     * D�s�rialise une chaine cr��e par {@link serializeFields} et initialise les champs
     * de l'enregistrement en cours ($this->fields)
     * 
     * @param string la $buffer la chaine retourn�e par $doc->get_data() 
     */
    private function unserializeFields($buffer)
    {
        $this->fields=array();
        $length=strlen($buffer);
        $i=0;
        while ($i<$length) 
        {
            // Lit le num�ro du champ
            $fieldNumber=self::readVInt($buffer, $i);
            
            // Lit le nombre de valeurs
            $count=self::readVInt($buffer, $i);
            
            // Lit le contenu du champ
            if ($count==1)
            {
                $data=self::readString($buffer, $i);
            }
            else
            {
                $data=array();
                while ($count--)
                {
                	$data[]=self::readString($buffer, $i);
                }
            }
            
            // Stocke le champ 
            if (isset($this->structure['fields'][$fieldNumber]) and isset($this->structure['fields'][$fieldNumber]['name']))
                $this->fields[$this->structure['fields'][$fieldNumber]['name']]=$data;
            // else : le champ n'a plus de nom = champ supprim�, on l'ignore. Sera supprim� lors du prochain save.
        }
        foreach ($this->structure['fieldbyname'] as $name=>$field)
        {
        	if (! isset($this->fields[$name])) $this->fields[$name]=null;
        }
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
            case 'max': return $this->max;
            default: return null;
        }
    }
    
    public function moveNext()
    {
        if (is_null($this->mset)) return;
        $this->iterator->next();
        $this->loadDocument();
        $this->eof=$this->iterator->equals($this->mset->end());
    }

    public function addRecord()
    {
        if (! $this->xapianDatabase instanceof XapianWritableDatabase)
            throw new Exception('La base est ouverte en lecture uniquement');
            
        $this->unserializeFields('');
        $this->doc=new XapianDocument();
        $this->editMode=1;
    }

    public function editRecord()
    {
        if (! $this->xapianDatabase instanceof XapianWritableDatabase)
            throw new Exception('La base est ouverte en lecture uniquement');

        if (is_null($this->doc))
            throw new Exception('Aucun enregistrement en cours');
                        
        $this->editMode=2;
    }

    public function saveRecord()
    {
        if ($this->editMode == 0)
            throw new Exception('L\'enregistrement n\est pas en cours de modification');

        echo 'saveRecord. editMode=', $this->editMode, '<br />';

        if (! isset($this->fields['REF']) or ($this->fields['REF']==''))
            $this->fields['REF']=$this->xapianDatabase->get_lastdocid()+1;
            
        echo '<H1>FIELDS</H1><pre>';
        var_dump($this->fields);
        echo '</pre>';

        // indexe chaque champ un par un
        $this->createTokens();

        // cr�e les cl�s de tri
        
        // stocke les donn�es de l'enregistrement
        $this->doc->set_data($this->SerializeFields());
        
        if ($this->editMode==1)
        {
            echo "Cr�ation d'un nouvel enreg dans la base<br />";
            $docId=$this->xapianDatabase->add_document($this->doc);
            echo 'DocId=', $docId, '<br />';
        }
        else
        {
            $docId=$this->iterator->get_docid();
            echo "Remplacement de l'enreg docId=", $docId,'<br />';
            $this->xapianDatabase->replace_document($docId, $this->doc);
        }
    }

    public function cancelUpdate()
    {
        if ($this->editMode == 0)
            throw new Exception('pas en mode �dition');
        $this->loadDocument();
    }

    public function deleteRecord()
    {
        $this->selection->delete();
    }
    
    private function createTokens()
    {
        static $charFroms=
            "\x00\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0a\x0b\x0c\x0d\x0e\x0f\x10\x11\x12\x13\x14\x15\x16\x17\x18\x19\x1a\x1b\x1c\x1d\x1e\x1f\x20\x21\x22\x23\x24\x25\x26\x27\x28\x29\x2a\x2b\x2c\x2d\x2e\x2f\x30\x31\x32\x33\x34\x35\x36\x37\x38\x39\x3a\x3b\x3c\x3d\x3e\x3f\x40\x41\x42\x43\x44\x45\x46\x47\x48\x49\x4a\x4b\x4c\x4d\x4e\x4f\x50\x51\x52\x53\x54\x55\x56\x57\x58\x59\x5a\x5b\x5c\x5d\x5e\x5f\x60\x61\x62\x63\x64\x65\x66\x67\x68\x69\x6a\x6b\x6c\x6d\x6e\x6f\x70\x71\x72\x73\x74\x75\x76\x77\x78\x79\x7a\x7b\x7c\x7d\x7e\x7f\x80\x81\x82\x83\x84\x85\x86\x87\x88\x89\x8a\x8b\x8c\x8d\x8e\x8f\x90\x91\x92\x93\x94\x95\x96\x97\x98\x99\x9a\x9b\x9c\x9d\x9e\x9f\xa0\xa1\xa2\xa3\xa4\xa5\xa6\xa7\xa8\xa9\xaa\xab\xac\xad\xae\xaf\xb0\xb1\xb2\xb3\xb4\xb5\xb6\xb7\xb8\xb9\xba\xbb\xbc\xbd\xbe\xbf\xc0\xc1\xc2\xc3\xc4\xc5\xc6\xc7\xc8\xc9\xca\xcb\xcc\xcd\xce\xcf\xd0\xd1\xd2\xd3\xd4\xd5\xd6\xd7\xd8\xd9\xda\xdb\xdc\xdd\xde\xdf\xe0\xe1\xe2\xe3\xe4\xe5\xe6\xe7\xe8\xe9\xea\xeb\xec\xed\xee\xef\xf0\xf1\xf2\xf3\xf4\xf5\xf6\xf7\xf8\xf9\xfa\xfb\xfc\xfd\xfe\xff";
        
        static $charTo=
            '                                                0123456789      @abcdefghijklmnopqrstuvwxyz      abcdefghijklmnopqrstuvwxyz                                                                     aaaaaaaceeeeiiiidnooooo 0uuuuy saaaaaaaceeeeiiiidnooooo  uuuuyby';

        // la position du token en cours
        $position=0;
        
        // index tous les champs
        echo 'Indexation de l\'enreg :<br />';
        foreach($this->structure['fields'] as $field)
        {
            if ( (!isset($field['indexed'])) or ($field['indexed']!==true)) continue;

            if (isset($this->fields[$field['name']])) 
                $data=$this->fields[$field['name']];
            else
                $data='';
                
            $indexWords     = isset($field['indexwords'     ]) && ($field['indexwords'     ]===true);
            $indexValues    = isset($field['indexvalues'    ]) && ($field['indexvalues'    ]===true);
            $indexPositions = isset($field['indexpositions' ]) && ($field['indexpositions' ]===true);
            $indexValuesCount= isset($field['indexvaluescount' ]) && ($field['indexvaluescount' ]===true);
            $prefix=$field['indexprefix'];
            $indextoall=true;

            echo '- ', $field['name'], '=[', $data, ']. Index=words:',$indexWords, ',values:', $indexValues,',positions:',$indexPositions,',valuesCount=',$indexValuesCount,'<br />';

            
//            $data=(array)$data;
            // champ vide : ajoute uniquement empty/notempty si l'option est activ�e
            if ($data==='' or $data===false or (is_array($data) and count($data)===0))
            {
            	if (! $indexValuesCount) continue;
                $this->doc->add_term($prefix.'isempty');
                echo 'term("', $prefix.'isempty"', ')<br />';
                if ($indextoall)
                {
                    $this->doc->add_term('isempty');
                    echo 'term("', 'isempty', '")<br />';
                }
                continue;
            }

            $data=(array)$data;

            if ($indexValuesCount)
            {
                $this->doc->add_term($prefix.'has'.count($data));
                echo 'term("', $prefix.'has'.count($data).'"', ')<br />';
                if ($indextoall)
                {
                    $this->doc->add_term('has'.count($data));
                    echo 'term("', 'has'.count($data).'"', ')<br />';
                }
            }                

            foreach ($data as $value)
            {
            	if ($indexWords)
                {
                    $text=strtr($value, $charFroms, $charTo);
                    $token=strtok($text, ' ');
                    while ($token !== false)
                    {
                        $len=strlen($token);
                        
                        // passe les termes vides et les termes vraiment trop longs
                        if ($len==0 or $len>64) continue;
                        
                        if ($indexPositions)
                        {
                            ++$position;
                            $this->doc->add_posting($prefix.$token, $position);
                            echo 'posting("', $prefix.$token, '",', $position, ')<br />';
                            if ($indextoall)
                            {
                                $this->doc->add_posting($token, $position);
                                echo 'posting("', $token, '",', $position, ')<br />';
                            }
                        }
                        else
                        {
                            $this->doc->add_term($prefix.$token);
                            echo 'term("', $prefix.$token, '")<br />';
                            if ($indextoall)
                            {
                                $this->doc->add_term($token);
                                echo 'term("', $token, '")<br />';
                            }
                        }
        
                        $token=strtok(' ');
                    }
                    if ($indexPositions) $position+=10;
                }
            }            
        }	
    }
    
    private function index($field, &$data, $document, &$position)
    {
        $MAX_TOKEN_LENGTH=64;
        
        $index=$field['index'];
        echo "$field[name] : $data", "\n";

        $type=$index['typenumber'];
        
        // empty / not empty
        if ($type & 4)
        {
            $token=isset($data) ? '@notempty@': '@empty@';
            $document->add_term($token);
            echo "- [$token]\n";
        }
        if (! isset($data)) return;
        
        // word / phrase / article
        if ($type & 2) // article
        {
            $text='@soa@ ' . str_replace(trim($field['separator']), ' @eoa@ @soa@ ', $data) . ' @eoa@';
            $text=strtr($text, self::$charFroms, self::$charTo);
        }
        else
            $text=strtr($data, self::$charFroms, self::$charTo);
    
        $addposition = ($type & (2 | 8)); // phrase ou article
        
        $anyfield = ($type | 16); 
        //echo "type=$type, addposition=$addposition\n";
        if ($type & (1 | 2 | 8)) // mot, phrase ou article 
        {  
            $prefix='X' . strtoupper($index['name']);
            $token=strtok($text, ' ');
            while ($token !== false)
            {
                $len=strlen($token);
                
                // passe les termes vides et les termes vraiment trop longs
                if ($len==0 or $len>$MAX_TOKEN_LENGTH) continue;
                
                if ($addposition)
                {
                    ++$position;
                    $document->add_posting("$prefix$token", $position);
                    echo "- $prefix:[$token], $position\n";
                    if ($anyfield)
                    {
                        $document->add_posting($token, $position);  
                        echo "- [$token], $position\n";
                    }
                }
                else
                {
                    $document->add_term("$prefix$token");
                    echo "- $prefix:[$token]\n";
                    if ($anyfield)
                    {
                        $document->add_term($token);
                        echo "- [$token]\n";
                    }
                }

                $token=strtok(' ');
            }
            if ($addposition) $position+=10;
        }
    }
    
    public function dumpTerms($start='', $max=0)
    {
        $begin=$this->xapianDatabase->allterms_begin();
        $end=$this->xapianDatabase->allterms_end();
        $begin->skip_to($start);
        $count=0;
        if ($max<=0) $max=PHP_INT_MAX;
        while (!$begin->equals($end))
        {
            if ($count >= $max) break;
            $term=$begin->get_term();
            if (substr($term, 0, strlen($start))!=$start) break;
        	echo '<li>[', $term, '], freq=', $begin->get_termfreq(), ', wdf=', $begin->get_wdf(), '</li>', "\n";
            $count++;            
            $begin->next();
        }
        echo '<strong>', $count, ' termes</strong>';
    }
}

/**
 * Repr�sente un enregistrement dans une base {@link BisDatabase}
 * 
 * @package     fab
 * @subpackage  database
 */
class XapianDatabaseRecord extends DatabaseRecord
{
    /**
     * @var COM.Bis.Fields Liste des champs de cet enregistrement
     */
    private $fields=null;
    
    /**
     * @var boolean Vrai si l'it�rateur est valide
     */
    private $valid=false;
    
    /**
     * {@inheritdoc}
     * 
     * @param COM.Bis.Fields $fields l'objet BIS.Fields contenant la liste
     * des champs de la base
     */
    public function __construct(Database $parent, & $fields)
    {
//        parent::__construct($parent);
        $this->fields= & $fields;   
    }

    /* <ArrayAccess> */

    public function offsetSet($offset, $value)
    {
        $this->fields[$offset]=$value;
    }

    public function offsetGet($offset)
    {
        if (isset($this->fields[$offset]))          // TODO: � �tudier
            return $this->fields[$offset];
        return '';
        //return (string) $this->fields[$offset]->value;
        //return $this->fields->item($offset)->value;
    }

    public function offsetUnset($offset)
    {
        unset($this->fields[$offset]);
    }

    public function offsetExists($offset)
    {
        return isset($this->fields[$offset]);
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
        $this->valid=(reset($this->fields) !== false);
//        echo "Rewind. This.current=", $this->current, "<br />";
    }

    public function current()
    {
//        echo "Appel de current(). This.current=",$this->current," (",$this->fields[$this->current]->name,")","<br />";
        return current($this->fields);
    }

    public function key()
    {
//        echo "Appel de key(). This.current=",$this->current," (",$this->fields[$this->current]->name,")","<br />";
        return key($this->fields);
    }

    public function next()
    {
//        echo "Appel de next. This.current passe � ", ($this->current+1),"<br />";
        $this->valid=(next($this->fields) !== false);
    }

    public function valid()
    {
        return $this->valid;
    }
    
    /* </Iterator> */
    
}

?>