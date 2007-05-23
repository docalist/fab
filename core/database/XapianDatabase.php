<?php
/**
 * @package     fab
 * @subpackage  database
 * @author      Daniel Ménard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id$
 */

require_once Runtime::$fabRoot . 'lib/xapian/xapian.php';

/**
 * Représente une base de données Xapian
 * 
 * @package     fab
 * @subpackage  database
 */
class XapianDatabaseDriver extends Database
{
    // Numéro de l'enreg qui contient une copie de la structure de la base
    const ConfigDocId=1; // = 4294967295 = (2^32)-1
    
    // l'objet XapianDatabase
    private $xapianDatabase=null;
    
    private $structure=null;
    
    private $fields=array();
    private $fieldName=array();
    
    /**
     * @var XapianQueryParser l'analyseur d'équations de xapian
     * @access private
     */
    private $queryParser=null; // l'analyseur d'équations

    /**
     * @var XapianEnquire l'environnement de recherche. Vaut null tant que search n'a pas été appellée.
     * @access private
     */
    private $enquire=null; // l'environnement de recherche

    /**
     * @var XapianMSet les résultats de la recherche. Vaut null tant que search n'a pas été appellée.
     * @access private
     */
    private $mset=null; // la sélection

    /**
     * @var XapianIterator L'iterateur sur les résultats de la recherche.
     * Vaut null tant que search n'a pas été appellée
     * @access private
     */
    private $iterator=null; // le msetiterator
    
    /**
     * @var XapianDocument le document en cours parmi les résultats de la recherche
     */
    private $doc=null;
    
    private $parseOptions=0; // options données à parse_Query
    
    
    /**
     * Génère une chaine représentant un entier encodé avec un nombre variable d'octets.
     * Les chaines obtenues respecte l'ordre de tri des entiers.
     * 
     * La chaine générée contient 1 octet de longueur indiquant le nombre de caractères 
     * utilisés pour représenter l'entier puis autant de les octets (MSB en premier) 
     * représentant l'entier
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
     * @param int $value l'entier à encoder
     * @param string $buffer optionnel : la chaine dans laquelle le résultat
     * doit être écrit.
     * @param int $i optionnel : la position dans $string à laquelle le résultat
     * sera écrit. $i est incrémenté en sortie pour permettre des appels successifs
     * à la fonction.
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
        $buffer=substr_replace($buffer, $result, $i/*, $length+1*/);
        $i+=$length+1;
        return $result;
    }
    
    /**
     * Décode une chaine contenant un entier encodé avec la fonction {@link writeSortableVInt}.
     * 
     * @param string $buffer la chaine à décoder
     * @param int $i optionnel : la position dans $string à partir de laquelle le décodage doit
     * démarrer. $i est incrémenté en sortie pour permettre des appels successifs à la fonction.
     */
    private static function readSortableVInt($buffer, & $i=0)
    {
        $next = $i + ord($buffer[$i++]);
        for($result = 0; $i<$next; $i++)
            $result = ($result << 8) + ord($buffer[$i]);
        return $result;
    }
    
    /**
     * Ecrit une chaine représentant un entier encodé sous forme d'un nombre
     * variable d'octets.
     * 
     * @param int $value l'entier à encoder
     * @param string $string optionnel : la chaine dans laquelle le résultat
     * doit être écrit.
     * @param int $i optionnel : la position dans $string à laquelle le résultat
     * sera écrit. $i est incrémenté en sortie pour permettre des appels successifs
     * à la fonction.
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
        $buffer=$ret=substr_replace($buffer, $result, $i/*, $length*/);
        $i+=$length;
        return $result;
    }
    
    /**
     * Décode une chaine contenant un entier encodé avec la fonction {@link writeVInt}.
     * 
     * @param string $string la chaine à décoder
     * @param int $i optionnel : la position dans $string à partir de laquelle le décodage doit
     * démarrer. $i est incrémenté en sortie pour permettre des appels successifs à la fonction.
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
        $buffer=substr_replace($buffer, $string, $i/*, $length*/);
        $i+=$length;
        return substr($buffer, $j, $i-$j);
    }
    
    private static function readString($buffer, & $i=0)
    {
        if (0 == $length=self::readVInt($buffer, $i)) return '';
        $result=substr($buffer, $i, $length);
        $i+=$length;
        return $result;
    }
    

//************************************
    private static function xmlToDef($xmlSource)
    {
        // Le tableau généré
        $def=array();
        
        // Crée un document XML
        $xml=new domDocument();
        $xml->preserveWhiteSpace=false;
    
        // gestion des erreurs : voir comment 1 à http://fr.php.net/manual/en/function.dom-domdocument-loadxml.php
        libxml_clear_errors(); // >PHP5.1
        libxml_use_internal_errors(true);// >PHP5.1
    
        // Charge le document
        if (! $xml->loadXML($xmlSource))
        {
            $h="Structure de base incorrecte, ce n'est pas un fichier xml valide :<br />\n"; 
            foreach (libxml_get_errors() as $error)
                $h.= "- ligne $error->line, colonne $error->column : $error->message<br />\n";
    
            throw new Exception($h);
        }
    
        // L'élément racine doit être '<database>'
        $database=$xml->documentElement;
        if ($database->tagName !=='database')
            throw new Exception('Structure de base incorrecte : doit commencer par un tag "database"');
            
        // Convertit la structure xml en tableau php
        $dtd=array
        (
            'label',
            'version',
            'description',
            'sep',
            'stopwords',                // Liste par défaut des mots-vides à ignorer lors de l'indexation
            'field'=>array
            (
                'name',                 // Nom du champ, d'autres noms peuvent être définis via des alias
                'type',                 // Type du champ (juste à titre d'information, non utilisé pour l'instant)
                'label',                // Libellé du champ
                'description',          // Description
                
                'stopwords',            // Liste spécifique de mots-vides à appliquer à ce champ
                
                'index'=>array          // Liste des index à créer pour ce champ  
                (
                    'name',             // Nom de l'index
                    'type',             // Type
                ),
                
                'entries'=>array        // Liste des tables des valeurs à constituer
                (
                    'name',             // Nom de la table
                    'start',            // Position de début ou chaine délimitant le début de la valeur à ajouter à la table
                    'end'               // Longueur ou chaine délimitant la fin de la valeur à ajouter à la table
                ),
                
                'sortable'=>array       // Permet de trier sur ce champ
                (
                    'start',            // Position de début ou chaine délimitant le début de la clé de tri à créer
                    'end'               // Longueur ou chaine délimitant la fin de la clé de tri à créer
                )
            )
        );
//                <entries name="auteurs" start="0" end="/" />
//                <entries name="noms" start="0" end="(" />
//                <entries name="prénoms" start="(" end=")" />
//                <entries name="roles" start="/" end=":" />
//                <entries name="affiliations" start=":" end="" />

//                <sortable start="(" end=")" />
//                <sortable start="0" end=")" />
        
        $def=self::xmlToArray($database,$dtd);
    
        // Vérifie que c'est bien une structure de base de données
        if (! is_array($def) or 0 == count($def))
            throw new Exception('Structure de base incorrecte');
            
        // Tri et nettoyage des mots-vides
        if (isset($def['stopwords']))
        { 
            $t=preg_split('~\s~', $def['stopwords'], -1, PREG_SPLIT_NO_EMPTY);
            sort($t);
            $def['stopwords']=array_flip($t);    
        }
        else
            $def['stopwords']=array();
        
        // Vérifie qu'on a au moins un champ
        if (! isset($def['field']) or ! is_array($def['field']) or 0==count($def['field']))
            throw new Exception('Structure de base incorrecte, aucun champ défini');
    
        $fields=array();
        
        $fieldNumber=1;
        $indexNumber=1;
        
        $def['index']=array();      // Les prefixes des index
        $def['entries']=array();    // Les préfixes des tables des entrées
        
        foreach($def['field'] as $fieldNumber=>$field)
        {
            // Numérote le champ
            $field['number']=$fieldNumber++;
             
            // Tri et nettoie les mots-vides
            if (isset($field['stopwords']))
            { 
                $t=preg_split('~\s~', $def['stopwords'], -1, PREG_SPLIT_NO_EMPTY);
                sort($t);
                $field['stopwords']=array_flip($t);
            }    
    
            // Les index
            if (isset($field['index']))
            {
                foreach($field['index'] as & $index)
                {
                    // Détermine le nom de l'index (=le nom du champ si non précisé)
                    if (isset($index['name'])) $name=$index['name']; else $name=$field['name'];
                    
                    // Détermine le préfixe de cet index
                    if (!isset($def['index'][$name])) $def['index'][$name]=count($def['index']).':';
                }
            }
    
            // Tables des entrées
            if (isset($field['entries']))
            {
                foreach($field['entries'] as & $entry)
                {
                    // Détermine le nom de la table (=le nom du champ si non précisé)
                    if (isset($entry['name'])) $name=$entry['name']; else $name=$field['name'];
                    
                    // Détermine le préfixe de cette table
                    if (!isset($def['entries'][$name])) $def['entries'][$name]='T'.count($def['entries']).':';
                }
            }
    
            // Vérifie que le champ a un nom
            if (! isset($field['name'])) 
                throw new Exception("Le champ numéro $fieldNumber n'a pas de nom");
    
            // Vérifie que le nom du champ est unique
            $name=$field['name'];
            if (isset($fields[$name]))
                throw new Exception("Champ $name définit plusieurs fois");
            unset($field['name']);
                        
            $fields[$name]=$field;
        }
        $def['field']=$fields;
    
        return $def;
    }
    
    private static function xmlToArray(DOMNode $node, array $dtd)
    {
        $result=array();
        
        // Les attributs du tag sont des propriétés du champ (peuvent aussi figurer sous forme de noeud fils)
        if ($node->hasAttributes())
        {
            foreach ($node->attributes as $attribute)
            {
                if (! in_array($attribute->nodeName, $dtd, true))
                    throw new Exception('Attribut "'.$attribute->nodeName.'" incorect pour un élément <'.$node->tagName.'>');
                    
                $result[$attribute->nodeName]=utf8_decode($attribute->nodeValue);
            }
        }
        
        // Parcourt tous les fils
        foreach ($node->childNodes as $child)
        {
            switch ($child->nodeType)
            {
                case XML_ELEMENT_NODE:
                    $name=$child->tagName;
                    
                    // Propriété définie sous forme de noeud enfant
                    if (in_array($name, $dtd, true))
                    {
                        // Vérifie que la propriété n'est pas déjà définie
                        if (isset($result[$name]))
                            throw new Exception('La propriété '.$name.' est définie à la fois comme attribut et comme noeud enfant');
    
                        // Récupère le contenu du noeud
                        $h='';
                        foreach($child->childNodes as $n)
                            $h.=$child->ownerDocument->saveXml($n);
                        $result[$name]=utf8_decode($h);
                        break;
                    }
                    if(isset($dtd[$name]))
                    {
                        $t=self::xmlToArray($child, $dtd[$name]);
                        if (! isset($result[$name]))
                            $result[$name][1]=$t;
                        else
                            $result[$name][]=$t;
                        break;
                    }
                    throw new Exception('Un tag <'.$node->tagName.'> ne peut pas contenir d\'éléments <'.$name.'>');    
                
                // Types de noeud autorisés mais ignorés
                case XML_COMMENT_NODE:
                    break;
                    
                // Types de noeud interdits
                default:
                    throw new Exception('Type de noeud interdit dans un tag <'.$node->tagName.'>');
            }
        }
        
        return $result;
    }

//************************************
    
    protected function doCreate($path, $xml, $options=null)
    {
        // Convertit la structure xml en tableau php
        $def=self::xmlToDef($xml);
        
        // Crée la base xapian
        putenv('XAPIAN_PREFER_FLINT=1'); // uniquement pour xapian < 1.0
        echo "création de la base...<br />";
        $this->xapianDatabase=new XapianWritableDatabase($path, Xapian::DB_CREATE_OR_OVERWRITE); // TODO: remettre DB_CREATE
        echo "base xapian créée !<br />";
        
        // Enregistre la structure de la base
        echo "enregistrement de la structure...<br />";
        $h=rtrim($path, '/').'/structure.php';
        file_put_contents($h, "<?php\n return " . var_export($def, true) . "\n?>");
        echo "structure enregistrée !<br />";
        
        // Enregistre la structure de la base dans l'enreg #1
        $doc=new XapianDocument();
        $doc->set_data(serialize($def));
        $this->structure=$def;
        $doc->add_term('@structure');
//        $this->xapianDatabase->add_document($doc);
// 
        $this->xapianDatabase->replace_document(self::ConfigDocId, $doc);
        $this->xapianDatabase->flush();
        //unset($this->xapianDatabase);
        $this->record=new XapianDatabaseRecord($this, $this->fields);
        
        // TODO : reset de toutes les propriétés
        
    }
        
    protected function doOpen($path, $readOnly=true)
    {
        // Ouvre la base xapian
        if ($readOnly)
            $this->xapianDatabase=new XapianDatabase($path);
        else
            $this->xapianDatabase=new XapianWritableDatabase($path, Xapian::DB_OPEN);

        // Charge la structure de la base
        $this->structure=unserialize($this->xapianDatabase->get_document(self::ConfigDocId)->get_data());
        
        foreach ($this->structure['field'] as $name=>$field)
        {
            $this->fields[$name]=null;
            if ($i=$field['number']) $this->fieldName[$i]=$name; 
        }
        $this->record=new XapianDatabaseRecord($this, $this->fields);
            
        // TODO : reset de toutes les propriétés
            
    }
    
    private function setupSearch()
    {
        // Initialise l'environnement de recherche
        $this->enquire=new XapianEnquire($this->xapianDatabase);
        
        // Initialise le QueryParser
        $this->parser=new XapianQueryParser();
        
        // Initialise la liste des noms d'index reconnus dans les équations et associe le préfixe correspondant
        foreach($this->structure['index'] as $name=>$prefix)
        {
            //$this->parser->add_boolean_prefix($name, $prefix);
            $this->parser->add_prefix($name, $prefix);
            $this->parser->add_prefix(strtolower($name), $prefix);  // TODO : les préfixes sont sensibles à la casse, ajouter wishlist à xapian
            $this->parser->add_prefix(strtoupper($name), $prefix);
        }

        // Initialise le stopper (suppression des mots-vides)
        $stopper=new XapianSimpleStopper();
        foreach ($this->structure['stopwords'] as $stopWord=>$i)
            $stopper->add($stopWord.'');
        
        echo 'stopper : ', $stopper->get_description(), ' (non appliqués pour le moment, bug xapian/apache)<br />';
        
        $this->parser->set_stopper($stopper); // TODO : segfault
        flush();
        
        
// IDEM si on bypass xapian.php
//        $stopper=new_SimpleStopper();
//        foreach ($this->structure['stopwords'] as $stopWord=>$i)
//            SimpleStopper_add($stopper,$stopWord);
//        echo 'stopper : ', SimpleStopper_get_description($stopper),'<br />';
//        QueryParser_set_stopper($this->parser->_cPtr,$stopper);                
    
        $this->parser->set_database($this->xapianDatabase); // indispensable pour FLAG_WILDCARD
    }

    
    public function search($equation=null, $options=null)
    {
        // a priori, pas de réponses
        $this->eof=true;

        // Analyse les options indiquées (start et sort) 
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
        
        
        // Met en place l'environnement de recherche lors de la première recherche
        if (is_null($this->enquire)) $this->setupSearch();

        // Construit la requête
        $equation=strtr($equation,'=',':');
        echo "Equation donnée à xapian : ", $equation, "<br />";
        
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
        // générer la liste des mots ignorés dans la requête

        // Définit l'ordre de tri des réponses
        $this->setSortOrder($sort);
        
        // Exécute la requête
        $this->enquire->set_query($query);

        $this->mset=$this->enquire->get_MSet($start, $max);
        $this->count=$this->mset->get_matches_estimated();
        echo 'Nb de réponses : ' , $this->count, '<br />';
        echo 'start : ', $start, ', max=', $max, '<br />';
//        $this->moveFirst();
//        if (is_null($this->mset)) return;
        //echo "mset.size=", $this->mset->size(), "\n";
        $this->iterator=$this->mset->begin();
        if ($this->eof=$this->iterator->equals($this->mset->end())) { echo 'eof atteint dès le début<br />'; return false;} 
        $this->loadDocument();
        $this->eof=false;              
        // Retourne le résultat
        return true;
    }
    
    private function setSortOrder($sort=null)
    {
        // Définit l'ordre de tri
//     *     - '%' : trier les notices par score (la meilleure en tête)
//     *     - '+' : trier par ordre croissant de numéro de document
//     *     - '-' : trier par ordre décroissant de numéro de document
//     *     - 'xxx+' : trier sur le champ xxx, par ordre croissant
//     *     - 'xxx-' : trier sur le champ xxx, par ordre décroissant
//     *     - 'xxx+%' : trier sur le champ xxx par ordre croissant, puis par
//     *       pertinence.
//     *     - 'xxx-%' : trier sur le champ xxx par ordre décroissant, puis par
//     *       pertinence.
//     *     - '%xxx+'
//     *     - '%xxx-'

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
                echo 'Tri : par docid décroissants<br />';
                $this->enquire->set_weighting_scheme(new XapianBoolWeight());
                $this->enquire->set_DocId_Order(XapianEnquire::DESCENDING);
                break;
                
            default:
                if 
                (
                    0==preg_match('~(%?)([+-]?)([a-z]+)([+-]?)(%?)~i', $sort, $matches) 
                    or 
                    ($matches[1]<>'' and $matches[5]<>'') // le % figure à la fois au début et à la fin
                    or 
                    ($matches[2]<>'' and $matches[4]<>'') // le +/- figure à la fois avant et après le nom du champ
                )
                    throw new Exception('Ordre de tri incorrect, syntaxe non reconnue : ' . $sort);
                $sortField=$matches[3];
                if (! isset($this->structure['field'][$sortField])
                    or ! isset($this->structure['field'][$sortField]['sortable'])
                    or $this->structure['field'][$sortField]['sortable']===false)
                    throw new Exception('Impossible de trier sur le champ indiqué : ' . $sortField);
                $fieldNumber=$this->structure['field'][$sortField]['number'];
                $order = ((($matches[2]==='-') || ($matches[4]) === '-')) ? XapianEnquire::DESCENDING : XapianEnquire::ASCENDING;
                if ($matches[1])        // trier par pertinence puis par champ
                {
                    echo 'Tri : par pertinence puis par ', $sortField, ($order ? ' croissants': ' décroissants'),'<br />';
                    $this->enquire->set_sort_by_relevance_then_value($fieldNumber, $order);
                }
                elseif ($matches[5])    // trier par champ puis par pertinence
                { 
                    echo 'Tri : par ', $sortField, ($order ? ' croissants': ' décroissants'),' puis par pertinence.<br />';
                    $this->enquire->set_sort_by_value_then_relevance($fieldNumber, $order);
                }
                else                    // trier par champ uniquement
                {                        
                    echo 'Tri : par ', $sortField, ($order ? ' croissants': ' décroissants'),'<br />';
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

    public function getTerms()
    {
        if (is_null($this->doc))
            throw new Exception('Pas de document courant');
          
        $indexName=array_flip($this->structure['index']);
        $entryName=array_flip($this->structure['entries']);
          
        $result=array();
        
        $begin=$this->doc->termlist_begin();
        $end=$this->doc->termlist_end();
        while (!$begin->equals($end))
        {
            $term=$begin->get_term();
            if (false === $pt=strpos($term,':'))
            {
            	$kind='index';
                $index='*';
            }
            else
            {
            	$prefix=substr($term,0,$pt+1);
                if($prefix[0]==='T')
                {
                	$kind='entries';
                    $index=$entryName[$prefix];
                }
                else
                {
                    $kind='index';
                    $index=$indexName[$prefix];
                }
                $term=substr($term,$pt+1);
            }
            
            $posBegin=$begin->positionlist_begin();
            $posEnd=$begin->positionlist_end();
            $pos=array();
            while(! $posBegin->equals($posEnd))
            {
            	$pos[]=$posBegin->get_termpos();
                $posBegin->next();
            }
            
            $result[$kind][$index][$term]=array
            (
                'freq'=>$begin->get_termfreq(),
                'wdf'=>$begin->get_wdf(),
//                'positions'=>$pos
            );
            if ($pos)
                $result[$kind][$index][$term]['positions']=$pos;
            
            //'freq='.$begin->get_termfreq(). ', wdf='. $begin->get_wdf();                
            $begin->next();
        }
        return $result;
    }
    

    /**
     * Retourne une chaine contenant la version serialisée de $this->fields, telle
     * qu'elle est stockée dans les documents (set_data)
     * 
     * @return string la chaine obtenue
     */
    private function serializeFields()
    {
//        static $buffer=''; // static : la taille va augmenter dynamiquement, évite les réallocations
        $buffer='';
        $i=0;
        foreach($this->fields as $name=>& $data)
        {
            if (! is_null($data) && ($data !== '') && ($data !== false) && ($data !== 0) && ($data !== 0.0))
            {
                // Ecrit le numéro du champ
                self::writeVInt((int) $this->structure['field'][$name]['number'], $buffer, $i);
                
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
     * Désérialise une chaine créée par {@link serializeFields} et initialise les champs
     * de l'enregistrement en cours ($this->fields)
     * 
     * @param string la $buffer la chaine retournée par $doc->get_data() 
     */
    private function unserializeFields($buffer)
    {
        $this->fields=array();
        $length=strlen($buffer);
        $i=0;
        while ($i<$length) 
        {
            // Lit le numéro du champ
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
            if (isset($this->fieldName[$fieldNumber]))
            {
                $this->fields[$this->fieldName[$fieldNumber]]=$data;
            }
            // else : le champ n'a plus de nom = champ supprimé, on l'ignore. Sera supprimé lors du prochain save.
        }
        foreach ($this->structure['field'] as $name=>$field)
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
            
//        echo '<H1>FIELDS</H1><pre>';
//        var_dump($this->fields);
//        echo '</pre>';
//
        // indexe chaque champ un par un
        $this->createTokens();

        // crée les clés de tri
        
        // stocke les données de l'enregistrement
        $this->doc->set_data($this->SerializeFields());
        
        if ($this->editMode==1)
        {
            $docId=$this->xapianDatabase->add_document($this->doc);
            echo 'Nouvel enref, DocId=', $docId, '<br />';
//            $this->xapianDatabase->flush();
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
            throw new Exception('pas en mode édition');
        $this->loadDocument();
    }

    public function deleteRecord()
    {
        $this->selection->delete();
    }
    const 
        WORDS=1, 
        VALUES=2,
        COUNT=4,
        POSITIONS=8
        ;
        
    const
        MAX_KEY=240,            // Longueur maximale d'un terme, tout compris (doit être inférieur à BTREE_MAX_KEY_LEN de xapian)
        MAX_PREFIX=4,           // longueur maxi d'un préfixe (par exemple 'T99:')
        MAX_TERM=236,           // =MAX_KEY-MAX_PREFIX, longueur maximale d'un terme
        MAX_ENTRY_SLOT=20,      // longueur maximale d'un mot de base dans une table des entrées
        MAX_ENTRY=219           // =MAX_KEY-MAX_ENTRY_SLOT-1, longueur maximale d'une valeur dans une table des entrées (e.g. masson:Editions Masson)
        ;
        
    private function createTokens()
    {
        static $charFroms=
            "\x00\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0a\x0b\x0c\x0d\x0e\x0f\x10\x11\x12\x13\x14\x15\x16\x17\x18\x19\x1a\x1b\x1c\x1d\x1e\x1f\x20\x21\x22\x23\x24\x25\x26\x27\x28\x29\x2a\x2b\x2c\x2d\x2e\x2f\x30\x31\x32\x33\x34\x35\x36\x37\x38\x39\x3a\x3b\x3c\x3d\x3e\x3f\x40\x41\x42\x43\x44\x45\x46\x47\x48\x49\x4a\x4b\x4c\x4d\x4e\x4f\x50\x51\x52\x53\x54\x55\x56\x57\x58\x59\x5a\x5b\x5c\x5d\x5e\x5f\x60\x61\x62\x63\x64\x65\x66\x67\x68\x69\x6a\x6b\x6c\x6d\x6e\x6f\x70\x71\x72\x73\x74\x75\x76\x77\x78\x79\x7a\x7b\x7c\x7d\x7e\x7f\x80\x81\x82\x83\x84\x85\x86\x87\x88\x89\x8a\x8b\x8c\x8d\x8e\x8f\x90\x91\x92\x93\x94\x95\x96\x97\x98\x99\x9a\x9b\x9c\x9d\x9e\x9f\xa0\xa1\xa2\xa3\xa4\xa5\xa6\xa7\xa8\xa9\xaa\xab\xac\xad\xae\xaf\xb0\xb1\xb2\xb3\xb4\xb5\xb6\xb7\xb8\xb9\xba\xbb\xbc\xbd\xbe\xbf\xc0\xc1\xc2\xc3\xc4\xc5\xc6\xc7\xc8\xc9\xca\xcb\xcc\xcd\xce\xcf\xd0\xd1\xd2\xd3\xd4\xd5\xd6\xd7\xd8\xd9\xda\xdb\xdc\xdd\xde\xdf\xe0\xe1\xe2\xe3\xe4\xe5\xe6\xe7\xe8\xe9\xea\xeb\xec\xed\xee\xef\xf0\xf1\xf2\xf3\xf4\xf5\xf6\xf7\xf8\xf9\xfa\xfb\xfc\xfd\xfe\xff";
        
        static $charTo=
            '                                                0123456789      @abcdefghijklmnopqrstuvwxyz      abcdefghijklmnopqrstuvwxyz                                                                     aaaaaaaceeeeiiiidnooooo 0uuuuy saaaaaaaceeeeiiiidnooooo  uuuuyby';

        // la position du token en cours
        $position=0;
        
        // indexe tous les champs
        echo 'Indexation de l\'enreg :<br />';
        
        foreach($this->structure['field'] as $name=>$field)
        {
            // Récupère le contenu du champ sous forme de tableau
            $data=(array) $this->fields[$name];
            
            // Les index de ce champ
            if ( isset($field['index']) ) foreach($field['index'] as $index)
            {
                // Récupère le type de l'index
                $type=$index['type'];
    
                $indextoall=false; // true;
                
                // Détermine le nom (le préfixe) de l'index
                $prefix=$this->structure['index'][isset($index['name']) ? $index['name'] : $name];

                // Indexation au mot
                if ($type & self::WORDS && !is_null($data)) foreach ($data as $value)
                {
                    // convertit le texte
                    $text=strtr($value, $charFroms, $charTo);
                    
                    // Extrait chaque mot et l'ajoute dans l'index
                    $token=strtok($text, ' ');
                    while ($token !== false)
                    {
                        // Passe les termes vides et les termes trop longs
                        $len=strlen($token);
                        if ($len==0 or $len>self::MAX_TERM) continue;
                        
                        if ($type & self::POSITIONS)
                        {
                            $this->doc->add_posting($prefix.$token, ++$position);
                            if ($indextoall)
                                $this->doc->add_posting($token, $position);
                        }
                        else
                        {
                            $this->doc->add_term($prefix.$token);
                            if ($indextoall)
                                $this->doc->add_term($token);
                        }
        
                        $token=strtok(' ');
                    }
                    if ($type & self::POSITIONS) $position+=10;
                }

                // Indexation empty/not empty
                if ($type & self::COUNT)
                {
                    if (count($data)===0)
                        $this->doc->add_term($prefix.'isempty');
                    else        
                        $this->doc->add_term($prefix.'has'.count($data));
                }    
                
            }
            
            // Table des entrées
            if ( isset($field['entries']) ) foreach($field['entries'] as $entry)
            {
                // Détermine le nom (le préfixe) de la table
                $prefix=$this->structure['entries'][isset($entry['name']) ? $entry['name'] : $name];

                // Détermine la table des mots-vides à utiliser
                if (isset($field['stopwords']))
                    $stopWords=$field['stopwords'];
                elseif(isset($this->structure['stopwords']))
                    $stopWords=$this->structure['stopwords'];
                else
                    $stopWords=array();
                    
                // Ajoute les entrées
                foreach ($data as $value)
                {
                    $value=trim($value);
                    
                    // convertit le texte
                    $text=strtr($value, $charFroms, $charTo);
                    
                    // Extrait chaque mot et l'ajoute dans l'index sous la forme "Txx:token=Entrée de la table""
                    $token=strtok($text, ' ');
                    while ($token !== false)
                    {
                        if (strlen($token)>1 && !isset($stopWords[$token]))
                        {
                            $this->doc->add_term(substr($prefix.$token.'='.$value,0,self::MAX_KEY));
                            echo $prefix.$token.'='.$value, '<br />';
                        }
                        $token=strtok(' ');
                    }
                }
            }
        }	
    }

    /**
     * Recherche dans une table des entrées les valeurs qui commence par le terme indiqué.
     * 
     * @param string $table le nom de la table des entrées à utiliser.
     * 
     * @param string $term le terme recherché
     * 
     * @param int $max le nombre maximum de valeurs à retourner
     * 
     * @param int $sort l'ordre de tri souhaité pour les réponses :
     *   - 0 : trie les réponses par nombre décroissant d'occurences dans la base (valeur par défaut)
     *   - 1 : trie les réponses par ordre alphabétique croissant
     * 
     * @param bool $splitTerms définit le format du tableau obtenu. Par défaut 
     * (splitTerms à faux), la fonction retourne un tableau simple associatif de la forme
     * array
     * (
     *     'droit du malade' => 10,
     *     'information du malade' => 3
     * )
     * Quand splitTerms est à true, chaque élément du tableau va être un tableau contenant
     * le nombre d'occurences, la partie à gauche du terme recherché, le mot contenant le terme 
     * recherché et la partie à droite du terme recherché :
     * array
     * (
     *     'droit du malade'=>array(10, 'droit ', 'du', ' malade'),
     *     'information du malade'=>array(3, 'information ', 'du', ' malade')
     * )
     * 
     * @return array
     */
    public function lookup($table, $term, $max=100000, $sort=0, $splitTerms=false)
    {
        if ('' === $prefix=Utils::get($this->structure['entries'][$table],''))
            throw new Exception("La table des entrées '$table' n'existe pas");
        
        if ('' === $token=trim(Utils::convertString($term,'bis')))
            return array();
        
        $start=$prefix.$token;
        
        $begin=$this->xapianDatabase->allterms_begin();
        $end=$this->xapianDatabase->allterms_end();

        $begin->skip_to($start);
        
        $count=0;
        if ($max<=0) $max=PHP_INT_MAX;
        $result=array();
        while (!$begin->equals($end))
        {
            $entry=$begin->get_term();

            if ($start !== substr($entry, 0, strlen($start))) 
                break;
                         
            $entry=substr($entry, strpos($entry, '=')+1);

            if ($splitTerms)
            {
                $h=Utils::convertString($entry,'bis');
                if (false === $pt=strpos(' '.$h, ' '.$token)) $pt=0;       
                if (false === $pt2=strpos($h, ' ', $pt+1))$pt2=strlen($entry);
                $left=substr($entry, 0, $pt);
                $middle=substr($entry, $pt, $pt2-$pt);
                $right=substr($entry, $pt2);                

                if (!isset($result[$entry]))
                    $result[$entry]=array($begin->get_termfreq(), $left, $middle, $right);
                else
                    $result[$entry][0]+=$begin->get_termfreq();
            }
            else
            {
                
                if (!isset($result[$entry]))
                    $result[$entry]=$begin->get_termfreq();
                else
                    $result[$entry]+=$begin->get_termfreq();
            }
            if (count($result) >= $max) break;

            $begin->next();
        }
        
        // Trie des réponses
        switch ($sort)
        {
        	case 0:     // Tri par occurences
                arsort($result, SORT_NUMERIC);
                break;  
            default:    // Tri alpha
                ksort($result, SORT_LOCALE_STRING);
                break;
        }
        
        return $result;	
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
        	echo '<li>[', $term, '], freq=', $begin->get_termfreq(), '</li>', "\n";
            $count++;            
            $begin->next();
        }
        echo '<strong>', $count, ' termes</strong>';
    }
}

/**
 * Représente un enregistrement dans une base {@link BisDatabase}
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
     * @var boolean Vrai si l'itérateur est valide
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
        if (isset($this->fields[$offset]))          // TODO: à étudier
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
//        echo "Appel de next. This.current passe à ", ($this->current+1),"<br />";
        $this->valid=(next($this->fields) !== false);
    }

    public function valid()
    {
        return $this->valid;
    }
    
    /* </Iterator> */
    
}

?>
