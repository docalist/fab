<?php
/*
 * Created on 7 sept. 06
 *
 * To change the template for this generated file go to
 * Window - Preferences - PHPeclipse - PHP - Code Templates
 */
require_once (Runtime::$fabRoot.'lib/xapian/xapian.php');



    
/**
 * Charge les fichiers de configuration de base de données (db.yaml, db.
 * debug.yaml...) dans la configuration en cours.
 * 
 * L'ordre de chargement est le suivant :
 * 
 * - fichier db.yaml de fab (si existant)
 * 
 * - fichier db.$env.yaml de fab (si existant)
 * 
 * - fichier db.yaml de l'application (si existant)
 * 
 * - fichier db.$env.yaml de l'application (si existant)
 */
function loadDbConfig()
{
    Debug::log("Chargement de la configuration des bases de données");
    if (file_exists($path=Runtime::$fabRoot.'config' . DIRECTORY_SEPARATOR . 'db.yaml'))
        Config::load($path, 'db');
    if (file_exists($path=Runtime::$root.'config' . DIRECTORY_SEPARATOR . 'db.yaml'))
        Config::load($path, 'db');

    if (!empty(Runtime::$env))   // charge la config spécifique à l'environnement
    {
        if (file_exists($path=Runtime::$fabRoot.'config'.DIRECTORY_SEPARATOR.'db.' . Runtime::$env . '.yaml'))
            Config::load($path, 'db');
        if (file_exists($path=Runtime::$root.'config'.DIRECTORY_SEPARATOR.'db.' . Runtime::$env . '.yaml'))
            Config::load($path, 'db');
    }
    
}

class xap
{
    /**
     * Table de conversion utilisée pour déterminer la liste des tokens 
     * d'indexation d'une notice.
     * 
     * Conserve uniquement les lettres, les chiffres et le caractère '@'.
     * Les lettres majuscules et accentuées sont ramenées à la forme 
     * minuscule non accentuée.
     * 
     * Tous les autres caractères sont remplacés par des espaces.
     */
    private static $charFroms=
        "\x00\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0a\x0b\x0c\x0d\x0e\x0f\x10\x11\x12\x13\x14\x15\x16\x17\x18\x19\x1a\x1b\x1c\x1d\x1e\x1f\x20\x21\x22\x23\x24\x25\x26\x27\x28\x29\x2a\x2b\x2c\x2d\x2e\x2f\x30\x31\x32\x33\x34\x35\x36\x37\x38\x39\x3a\x3b\x3c\x3d\x3e\x3f\x40\x41\x42\x43\x44\x45\x46\x47\x48\x49\x4a\x4b\x4c\x4d\x4e\x4f\x50\x51\x52\x53\x54\x55\x56\x57\x58\x59\x5a\x5b\x5c\x5d\x5e\x5f\x60\x61\x62\x63\x64\x65\x66\x67\x68\x69\x6a\x6b\x6c\x6d\x6e\x6f\x70\x71\x72\x73\x74\x75\x76\x77\x78\x79\x7a\x7b\x7c\x7d\x7e\x7f\x80\x81\x82\x83\x84\x85\x86\x87\x88\x89\x8a\x8b\x8c\x8d\x8e\x8f\x90\x91\x92\x93\x94\x95\x96\x97\x98\x99\x9a\x9b\x9c\x9d\x9e\x9f\xa0\xa1\xa2\xa3\xa4\xa5\xa6\xa7\xa8\xa9\xaa\xab\xac\xad\xae\xaf\xb0\xb1\xb2\xb3\xb4\xb5\xb6\xb7\xb8\xb9\xba\xbb\xbc\xbd\xbe\xbf\xc0\xc1\xc2\xc3\xc4\xc5\xc6\xc7\xc8\xc9\xca\xcb\xcc\xcd\xce\xcf\xd0\xd1\xd2\xd3\xd4\xd5\xd6\xd7\xd8\xd9\xda\xdb\xdc\xdd\xde\xdf\xe0\xe1\xe2\xe3\xe4\xe5\xe6\xe7\xe8\xe9\xea\xeb\xec\xed\xee\xef\xf0\xf1\xf2\xf3\xf4\xf5\xf6\xf7\xf8\xf9\xfa\xfb\xfc\xfd\xfe\xff";
    
    private static $charTo=
        '                                                0123456789      @abcdefghijklmnopqrstuvwxyz      abcdefghijklmnopqrstuvwxyz                                                                     aaaaaaaceeeeiiiidnooooo 0uuuuy saaaaaaaceeeeiiiidnooooo  uuuuyby';

    public $equation='';
    public $count=0;
    public $fieldsCount=0;
    
    private $database=''; // le nom ou le path de la base, tel que indiqué lors du open
    private $db=null; // l'objet XapianDatabase
    private $def=null; // le tableau représentant le .def de la base
    
    private $enquire=null; // l'environnement de recherche
    private $queryParser=null; // l'analyseur d'équations
    private $mset=null; // la sélection
    private $iterator=null; // le msetiterator
    private $doc=null; // le document en cours
    public $data=null; // les champs
    private $editMode=0; // 0: rien, 1:edit, 2:addnew
    
    private static function check(& $array, $key, $subkeys, $strict=true)
    {
        if (! isset($array[$key]))
            $array[$key]=array();
            
        $t=& $array[$key];
        
        foreach($subkeys as $name=>$subkey)
        {
        	list($type, $default)=$subkey;
            if (! isset($t[$name]))
                $t[$name]=$default;
            elseif (gettype($t[$name]) != $type)
                throw new Exception("$key.$subkey : valeur incorrecte ($t[$name]), valeur de type '$type' attendue");
            $t=& $array[$key];
        }
        
        if ($strict) foreach($t as $name=>$value)
        {
        	if (! isset($subkeys[$name]))
                throw new Exception("La clé '$name' n'est pas autorisé dans la section '$key'\n");
        }	
    } 
    private static function checkFieldType($name, &$field)
    {
    	$valid=array
        (
            'text'=>1,
            'memo'=>2,
            'int'=>3,
            'boolean'=>4,
            'datetime'=>5,
            'date'=>6
        );
        
        if (! isset($valid[$field['type']]))
            throw new Exception("Type incorrect pour le champ '$name' : '$field[type]'");
        $field['typenumber']=$valid[$field['type']];
    }
    
    private static function checkIndexType($name, & $field)
    {
    	if ($field['index']===false) return;

        $valid=array
        (
            'word'=>1,
            'article'=>2,
            'filled'=>4,
            'phrase'=>8,
            'anyfield'=>16
        );

        $index=& $field['index'];
        if (! isset($index['name']))
            throw new Exception("Le nom de l'index doit être indiqué pour le champ '$name'");
        if (! isset($index['type']))
            throw new Exception("Le type d'index doit être indiqué pour le champ '$name'");
        $type=explode(',', $index['type']);
        $mask=0;
        foreach($type as $type)
        {
        	$type=trim($type);
            if (!isset($valid[$type]))
                throw new Exception("Type d'index invalide pour le champ '$name' : '$type'");
            $i=$valid[$type];
            if (($mask & $i) != 0)
                throw new Exception("Type d'index invalide pour le champ '$name' : '$type' est répété plusieurs fois");
            $mask = $mask | $i;
        }
        $index['typenumber']=$mask;
        
    }
    
    private function convertDefinition($def)
    {
    	// section 'options'
        $t=array
        (
            'stoplist'          => array('string'  , ''    ),
            'indexstopwords'    => array('boolean' , false ),
            'separator'         => array('string'  , ', '  ),
            'stemmer'           => array('string'  , 'none')
        );
        
        self::check($def, 'options', $t);
        
        // section 'fields'
        self::check($def, 'fields', array(), false);
        $t=array
        (
            'type'              => array('string'  , 'memo' ),
            'title'             => array('string'  , ''     ),
            'separator'         => array('string'  , & $def['options']['separator']   ),
            'index'             => array('array'   , false )
        );
        
        $fieldNumber=0;
        foreach ($def['fields'] as $name=>& $field)
        {
        	self::check($def['fields'], $name, $t);
            $field=& $def['fields'][$name];
            $field['name']=$name;
            self::checkFieldType($name, $field);
            //echo Debug::dump($field);
            self::checkIndexType($name, $field);
            $field['fieldnumber']=++$fieldNumber;
            $def['fields'][$fieldNumber]= & $field;
        }

        return $def;
    }
    
    public function __construct()
    {
    	
    }
    public function __destruct()
    {
    	
    }
    public function open($database, $readOnly=true)
    {
        // Détermine le path de la base de données passée en paramètre
        // Si $database représente déjà un path (ou si on ne trouve pas cet alias)
        // on travaille directement avec le nom donné.
        $path=Config::get("db.$database.path", $database);
        
        // Ouvre la base xapian
        if ($readOnly)
            $this->db=new XapianDatabase($path);
        else  
            $this->db=new XapianWritableDatabase($path, Xapian::DB_OPEN);
        
        // Charge la structure de la base
        $this->def=unserialize($this->db->get_document(1)->get_data());
        $this->database=$database;
        
        // Ouvrir une sélection ?               
    }
    
    public function create($database, $definition, $overwrite=false)
    {
        // Détermine le path de la base de données passée en paramètre
        // Si $database représente déjà un path (ou si on ne trouve pas cet alias)
        // on travaille directement avec le nom donné.
        $path=Config::get("db.$database.path", $database);
        
        // Valide et convertit le .def
        $this->def=$this->convertDefinition(Utils::loadYaml($definition));
//        echo Debug::dump($this->def, false);
//        echo Debug::dump(serialize($this->def), false);
        
        // Crée la base xapian
        $this->db=new XapianWritableDatabase($path, $overwrite ? DB_CREATE_OR_OVERWRITE : DB_CREATE);
        
        // Enregistre la structure de la base dans l'enreg #1
        $doc=new XapianDocument();
        $doc->set_data(serialize($this->def));
        $this->db->add_document($doc);
        
        $this->database=$database;
        
        // Ouvrir une sélection ?               
    	
    }
    
    public function select($equation, $sort=false)
    {
        // Met en place l'environnement de recherche lors de la première recherche
        if (is_null($this->enquire))
        {
            $this->enquire=$enquire=new XapianEnquire($this->db);

            $this->parser=$parser=new XapianQueryParser();
            
//            foreach ($this->def['map'] as $name=>$prefix)
//            {
//                $parser->add_boolean_prefix($name, $prefix);
//                $parser->add_prefix($name, $prefix);
//            }
            
            $stopper=new XapianSimpleStopper();
            foreach (explode(' ', $this->def['options']['stoplist']) as $stopWord)
                $stopper->add($stopWord);
            
            $parser->set_stopper($stopper);
        
            $parser->set_database($this->db); // indispensable pour FLAG_WILDCARD
            
            $this->parseOptions=
                XapianQueryParser::FLAG_BOOLEAN |
                XapianQueryParser::FLAG_PHRASE | 
                XapianQueryParser::FLAG_LOVEHATE |
                XapianQueryParser::FLAG_BOOLEAN_ANY_CASE |
                XapianQueryParser::FLAG_WILDCARD;
        }
    
        // Construit la requête
        $query=$parser->parse_Query($equation, $this->parseOptions);
        $this->equation=$equation;
        
        // générer la liste des mots ignorés dans la requête

        // Définit l'ordre de tri
        //$enquire->set_sort_by_value(21,XapianEnquire::DESCENDING); // 0 ou 1 marchent, true ou false ne marchent pas
        $enquire->set_Sort_By_Relevance();
        //$enquire->setSortByValueThenRelevance(21,false);
    
        //$enquire->set_weighting_scheme(new XapianBoolWeight());
        //$enquire->set_DocId_Order(XapianEnquire::DESCENDING);
        
        $enquire->set_query($query);

        $this->mset=$enquire->get_MSet(0, 10000000, 10000000);
        $this->count=$this->mset->get_matches_estimated();
        $this->moveFirst();
    }
    
    private function loadDocument()
    {
        if (is_null($this->iterator))
            throw new Exception('Pas de document courant');
        echo "Etat de l'itérateur : ", var_dump($this->iterator), "\n";
        if ($this->iterator->equals($this->mset->end()))
        {
            $this->doc=null;
            $this->data=null;
        }
        else
        {
            $this->doc=$this->iterator->get_document();
            $this->data=unserialize($this->doc->get_data());
        }
    } 
    public function moveFirst()
    {
        if (is_null($this->mset)) return;
        echo "mset.size=", $this->mset->size(), "\n";
        $this->iterator=$this->mset->begin();
        $this->loadDocument();
    }       
    public function moveNext()
    {
        if (is_null($this->mset)) return;
        $this->iterator->next();
        $this->loadDocument();
    }
    // movePrevious
    //moveLast       
    public function eof()
    {
        if (is_null($this->mset) or is_null($this->iterator)) return true;
        return $this->iterator->equals($this->mset->end());
    }
    // bof
    public function field($key)
    {
        if (is_string($key)) $key=$this->def['fields'][$key]['fieldnumber'];
        if (isset($this->data[$key])) return $this->data[$key];
        return null;
    }
    public function fieldName($i)
    {
        return $this->def['fields'][$i]['name'];
    }       

    public function addNew()
    {
        $this->editMode=2;
        $this->data=array();
    }       
    public function edit()
    {
        $this->editMode=1;
    }       
    public function editMode()
    {
        return $this->editMode;
    }       
    
    public function setField($key, $value)
    {
        if ($this->editMode==0)
            throw new Exception("La notice n'est pas en cours d'édition");
        if (! isset($this->def['fields'][$key]))
            throw new Exception("Champ inconnu : '$key'");
            
        $i=$this->def['fields'][$key]['fieldnumber'];
        if (is_null($value))
            unset($this->data[$i]);
        else
            $this->data[$i]=$value;
    }

    public function update()
    {
        // vérifier qu'on ets en mode ajout ou edit
        // coder les données, faire l'indexation, stocker le doc dans la base
        // add ou replace selon editmode
        if ($this->editMode==0)
            throw new Exception("La notice n'est pas en cours d'édition");
        echo
            "\n<h1>Update()</h1>\n",
            'Edit mode : ', Debug::dump($this->editMode), "\n",
            'Data : ', Debug::dump($this->data), "\n";
        $doc=new XapianDocument();
        $doc->set_data(serialize($this->data));
        
        $position=0;
        foreach($this->def['fields'] as $name=>$field)
        {
            if (is_int($name)) break;
            if (($field['index']) === false) continue;
        	
            $this->index($field, $this->data[$field['fieldnumber']], $doc, $position);
        }
        
        if ($this->editMode==2)
            $this->db->add_Document($doc);
        else
            $this->db->replace_Document($this->iterator->get_docid(), $doc);
        $this->editMode=0;
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
    
    public function cancelUpdate()
    {
        $this->editMode=0;
        $this->loadDocument();
    }       
    public function delete()
    {
        $this->db->delete_document($this->iterator->get_docid());
    }
    // position ?
    // add
    // remove
    // contains
    // clear
    
    
    
    
}

loadDbConfig();
//$def=new XapianDefinition();
//$def->load(dirname(__FILE__) . '/db.yaml');
echo '<pre>';
//var_dump($def);
//echo '</pre>';
$selection=new Xap();
$dbPath=Runtime::$fabRoot . 'data/db/test1';
$selection->create($dbPath, dirname(__FILE__) . '/db.yaml', true);

$selection->addNew();
$selection->setfield('REF', 209);
$selection->setfield('Type', 'Livre');
$selection->setfield('Aut', 'LORIN C');
$selection->setfield('Tit', 'Traité de psychodrame d\'enfants. Suivi de : Samia, "l\'enfant de chien".');
$selection->setfield('Date', '1989');
$selection->setfield('Page', '237 p.');
$selection->setfield('Edit', 'Toulouse/Privat');
$selection->setfield('Resu', 'Les principales formes de psychodrames pratiquées en France avec des enfants et des adolescents sont exposées, ainsi que les règles techniques et l\'organisation des jeux de rôles et de mise en scène du "théâtre privé". Le point de vue est celui des performatifs. Un cas clinique est analysé.');
$selection->setfield('MotCle', 'MANUEL/PSYCHODRAME/PEDOPSYCHIATRIE/PSYCHANALYSE D\'ENFANT');
$selection->setfield('FinSaisie', true);
$selection->setfield('Valide', true);
$selection->setfield('Creation', '20060905');
$selection->setfield('LastUpdate', '20060910');
$selection->update();

$selection->select('lorin c');
echo "Equation: $selection->equation, réponses : $selection->count\n";
while (! $selection->eof())
{
	echo '1. ', $selection->field(1), ', ', $selection->field('Tit'), "\n";
    echo var_dump($selection->data), "\n\n";
    $selection->moveNext();
}
    

?>
