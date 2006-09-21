<?php
/**
 * @package     fab
 * @subpackage  database
 * @author      dmenard
 * @version     SVN: $Id$
 */

// TODO: impl�menter append()
// r�cup�rer une notice $selection[$ref]
// ajout d'une notice : $selection[]=array('ref'=>12, 'tit'=>'xxx'...)
// suppression d'une notice unset($selection[$ref])
// modification d'une notice : $selection[$ref]=$t

/**
 * Interface d'acc�s aux bases de donn�es.
 * 
 * Database est � la fois une classe statique offrant des m�thodes simples pour
 * cr�er ({@link create()}) ou ouvrir ({@link open()})une base de donn�es et une
 * interface pour toutes les classes descendantes.
 * 
 * @package     fab
 * @subpackage  config
 */
abstract class Database implements ArrayAccess, Iterator
{
    /**
     * @var string le type de base de donn�es en cours ('bis' ou
     * 'xapian').
     * @access protected
     */
    protected $type=null;

    /**
     * @var boolean vrai si on a atteint la fin de la s�lection
     * 
     * Les drivers qui h�ritent de cette classe doivent tenir � jour cette
     * propri�t�. Notamment, les fonctions {@link search()} et {@link
     * moveNext()} doivent initialiser eof � true ou false selon qu'il y a ou
     * non une notice en cours.
     * 
     * @access protected
     */
    protected $eof=true;
    
    /**
     * Le constructeur est priv� car ni cette classe, ni aucun des drivers qui
     * h�ritent de cette classe ne sont instanciables.
     * 
     * L'utilisateur ne manipule cette classe que via les m�thodes statiques
     * propos�es ({@link create}, {@link open}, ...) et via les m�thodes
     * non statiques impl�ment�es par les drivers.
     */
    protected function __construct()
    {
    }


    /**
     * Cr�e une base de donn�es.
     * 
     * Une erreur est g�n�r�e si la base de donn�es � cr�er existe d�j�.
     * 
     * La fonction ne peut pas �tre surcharg�e dans les drivers (final).
     * 
     * @param string $database alias ou path de la base de donn�es � cr�er.
     * 
     * @param array $def tableau contenant la d�finition de la structure de la
     * base
     * 
     * @param string $type type de la base de donn�es � cr�er. Ignor� si
     * $database d�signe un alias (dans ce cas, c'est le type indiqu� dans la
     * config de l'alias qui est prioritaire).
     * 
     * @param array $options tableau contenant des options suppl�mentaires. Les
     * options disponibles d�pendent du backend utilis�e. Chaque backend ignore
     * silencieusement les options qu'il ne reconnait pas ou ne sait pas g�rer.
     */
    final public static function create($database, $def, $type=null, $options=null)
    {
        /*
            DatabaseInterface
            	|-> BisDatabase
                |-> XapianDatabase
                
            DatabaseModule
            	|-> Base
 
			$selection=Database::Create('ascodoc', $def);
            
         */

        // Utilise /config/db.yaml pour convertir l'alias en chemin et d�terminer le type de base
//        $type=Config::get("db.$database.type", $type);
//        $database=Config::get("db.$database.path", $database);
        
        // TODO: g�rer les chemins relatifs
        
        // Cr�e une instance de la bonne classe en fonction du type, cr�e la base et retourne l'objet obtenu
//        debug && Debug::log("Cr�ation de la base '%s' de type '%s'", $database, $type);
        switch($type=strtolower($type))
        {
            case 'toto':
//              require_once 'BisDatabase.php';
                $db=new toto();
                $db->doCreate($database, $def, $options);
                break;

            case 'bis':
//              require_once 'BisDatabase.php';
                $db=new BisDatabase();
                $db->doCreate($database, $def, $options);
                break;
                
            case 'xapian':
//              require_once 'XapianDatabase.php';
                $db=new XapianDatabase();
                $db->doCreate($database, $def, $options);
                break;
                
            default:
                throw new Exception("Impossible de cr�er la base '$database' : le type de base '$type' n'est pas support�.");
        }
        $db->type=$type;
        return $db;
    }


    /**
     * M�thode impl�ment�e dans les drivers : cr�e la base
     * 
     * @param string $database alias ou path de la base de donn�es � cr�er.
     * 
     * @param array $def tableau contenant la d�finition de la structure de la
     * base
     * 
     * @param array $options tableau contenant des options suppl�mentaires. Les
     * options disponibles d�pendent du backend utilis�e. Chaque backend ignore
     * silencieusement les options qu'il ne reconnait pas ou ne sait pas g�rer.
     */
    abstract protected function doCreate($database, $def, $options=null);
    
    public function compareDef($def)
    {
        
    }
    
    /**
     * Modifie la structure d'une base de donn�es en lui appliquant une nouvelle
     * d�finition.
     * 
     * @param array $newDef le tableau contenant la d�finition de la nouvelle
     * structure de la base.
     * 
     * @param $mappings ????
     */
    public function changeDef($newDef, $mappings=null)
    {
        
    }

    
    /**
     * Retourne le type de la base
     */
    public function getType()
    {
    	return $this->type;
    }


    /**
     * Ouvre une base de donn�es.
     * 
     * Une erreur est g�n�r�e si la base de donn�es � cr�er n'existe pas.
     * 
     * La fonction ne peut pas �tre surcharg�e dans les drivers (final).
     * 
     * @param string $database alias ou path de la base de donn�es � ouvrir.
     * 
     * @param boolean $readOnly true pour ouvrir la base en lecture seule, false
     * pour l'ouvrir en lecture/�criture.
     * 
     * @param string $type type de la base de donn�es � ouvrir. Ignor� si
     * $database d�signe un alias (dans ce cas, c'est le type indiqu� dans la
     * config de l'alias qui est prioritaire).
     */
    final public static function open($database, $readOnly=true, $type=null)
    {
        // Utilise /config/db.yaml pour convertir l'alias en chemin et d�terminer le type de base
//        $type=Config::get("db.$database.type", $type);
//        $database=Config::get("db.$database.path", $database);
        
        // TODO: g�rer les chemins relatifs
        
        // Cr�e une instance de la bonne classe en fonction du type, cr�e la base et retourne l'objet obtenu
//        debug && Debug::log("Cr�ation de la base '%s' de type '%s'", $database, $type);
        switch($type=strtolower($type))
        {
            case 'toto':
//              require_once 'BisDatabase.php';
                $db=new toto();
                $db->doOpen($database, $readOnly);
                break;

            case 'bis':
//              require_once 'BisDatabase.php';
                $db=new BisDatabase();
                $db->doOpen($database, $readOnly);
                break;
                
            case 'xapian':
//              require_once 'XapianDatabase.php';
                $db=new XapianDatabase();
                $db->doOpen($database, $readOnly);
                break;
                
            default:
                throw new Exception("Impossible d'ouvrir la base '$database' : le type de base '$type' n'est pas support�.");
        }
        $db->type=$type;
        return $db;
    }


    /**
     * M�thode impl�ment�e dans les drivers : ouvre la base
     * 
     * @param string $database alias ou path de la base de donn�es � cr�er.
     * 
     * @param boolean $readOnly true pour ouvrir la base en lecture seule, false
     * pour l'ouvrir en lecture/�criture.
     */
    abstract protected function doOpen($database, $readOnly=true);
    

    /**
     * Lance une recherche et s�lectionne les notices correspondantes.
     * 
     * Si la recherche aboutit, la s�lection est positionn�e sur la premi�re
     * r�ponse obtenue (ou sur la r�ponse indiqu�e par 'start' dans les
     * options).
     * 
     * @param string $equation l'�quation de recherche � ex�cuter
     * @param array $options tableau d'options support�es par le backend qui
     * indiquent la mani�re dont la recherche doit �tre faite.
     * 
     * Options disponibles :
     * 
     * - 'sort' : une chaine indiquant la mani�re dont les r�ponses doivent �tre
     * tri�es : 
     * 
     * <li>'%' : trier les notices par score (la meilleure en t�te)
     * <li>'+' : trier par ordre croissant de num�ro de document
     * <li>'-' : trier par ordre d�croissant de num�ro de document
     * <li>'xxx+' : trier sur le champ xxx, par ordre croissant
     * <li>'xxx-' : trier sur le champ xxx, par ordre d�croissant
     * <li>'xxx+%' : trier sur le champ xxx par ordre croissant, puis par
     * pertinence.
     * <li>'xxx-%' : trier sur le champ xxx par ordre d�croissant, puis par
     * pertinence.
     * 
     * - 'start': entier (>0) indiquant la notice sur laquelle se positionner
     * une fois la recherche effectu�e.
     * 
     * - 'nb' : entier (>0) donnant une indication sur le nombre de notices que
     * l'on compte parcourir. Permet au backend d'optimiser sa recherche qu'en
     * ne recherchant que les nb meilleurs r�ponses.
     * 
     * - 'min_weight' : entier (>=0), score minimum qu'une notice doit obtenir
     * pour �tre s�lectionn�e (0=pas de minimum)
     * 
     * - 'min_percent' : entier (de 0 � 100), pourcentage minimum qu'une notice
     * doit obtenir pour �tre s�lectionn�e (0=pas de minimum)
     * 
     * - 'time_bias' : reserv� pour le futur, api non fig�e dans xapian.
     * 
     * - 'collapse' : nom d'un champ utilis� pour regrouper les r�ponses
     * obtenues. Par exemple, avec un regroupement sur le champ TypDoc, on
     * obtiendra uniquement la meilleure r�ponse obtenue parmis les articles,
     * puis la meilleures obtenues parmi les ouvrages, et ainsi de suite.
     * 
     * - 'weighting_scheme' : nom et �ventuellement param�tres de l'algorithme
     * de pertinence utilis�. Les valeurs possibles sont : 'bool', 'trad',
     * 'trad(k), 'bm25' ou 'bm25(k1,k2,k3,b,m)'
     * 
     * @return boolean true si au moins une notice a �t� trouv�e, false s'il n'y
     * a aucune r�ponse.
     * 
     */
    abstract public function search($equation=null, $options=null);
    

    /**
     * Retourne des meta-informations sur la derni�re recherche ex�cut�e ou sur
     * la notice courante de la s�lection.
     * 
     * @param string $what le nom de l'information � r�cup�rer.
     * 
     * Les valeurs possibles d�pendent du backend utilis�.
     * 
     * tous:
     * <li>'equation' : retourne l'�quation de recherche telle qu'elle a �t�
     * interpr�t�e par le backend
     * 
     * xapian :
     * 
     * Meta-donn�es portant sur la s�lectionc en cours 
     * <li>'max_weight' : retourne le poids obtenu par la meilleure notice
     * s�lectionn�e
     * <li>'stop_words' : retourne une chaine contenant les termes pr�sents dans
     * l'�quation qui ont �t� ignor�s lors de la recherche (mots vides).
     * <li>'query_terms' : retourne une chaine contenant les termes de la
     * requ�te qui ont �t� utilis�s pour la recherche. Pour une recherche
     * simple, cela retournera les termes de la requ�te moins les mots-vides ;
     * pour une recherche avec troncature, �a retournera �galement tous les
     * termes qui commence par le pr�fixe indiqu�.
     * 
     * 
     * Meta-donn�es portant sur la notice en cours au sein de la s�lection :
     * <li>'rank' : retourne le num�ro de la r�ponse en cours (i.e. c'est la
     * i�me r�ponse)
     * <li>'weight' : retourne le poids obtenu par la notice courante
     * <li>'percent' : identique � weight, mais retourne le poids sous forme
     * d'un pourcentage
     * <li>'collapse' : retourne le nombre de documents similaires qui sont
     * "cach�s" derri�re la notice courante
     * <li>'matching_terms' : retourne une cha�ne contenant les termes de
     * l'�quation de recherche sur lesquels ce document a �t� s�lectionn�.
     */
    abstract public function searchInfo($what);
    

    /**
     * Retourne une estimation du nombre de notices actuellement s�lectionn�es.
     * 
     * @param int $countType indique l'estimation qu'on souhaite obtenir. Les
     * valeurs possibles sont :
     * 
     * - 0 : estimation la plus fiable du nombre de notices. Le backend fait de
     * son mieux pour estimer le nombre de notices s�lectionn�es, mais rien ne
     * garantit qu'il n'y en ait pas en r�alit� plus ou moins que le nombre
     * indiqu�.
     * 
     * - 1 : le nombre minimum de notices s�lectionn�es. Le backend garantit
     * qu'il y a au moins ce nombre de notices dans la s�lection.
     * 
     * - 2 : le nombre maximum de notices s�lectionn�es. Le backend garantit
     * qu'il n'y a pas plus de notices dnas la s�lection que le nombre retourn�.
     */
    abstract public function count($countType=0);
    
    
    /**
     * Passe � la notice suivante, si elle existe.
     * 
     * @return boolean true si on a toujours une notice en cours, false si on a
     * pass� la fin de la s�lection (eof).
     */
    abstract public function moveNext();

    
    /**
     * Retourne la notice en cours
     * 
     * @return DatabaseRecord un objet repr�sentant la notice en cours. Cette
     * objet peut �tre manipul� comme un tableau (utilisation dans une
     * boucle foreach, lecture/modification de la valeur d'un champ en
     * utilisant les crochets, utilisation de count pour conna�tre le nombre de
     * champs dans la base...)
     */
    abstract public function fields();


    /**
     * Retourne la valeur d'un champ
     * 
     * @param mixed $which index ou nom du champ dont la valeur sera retourn�e.
     * @return mixed la valeur du champ ou null si ce champ ne figure pas dans
     * l'enregistrement courant.
     * 
     * @access protected Cette fonction n'est pas destin� � �tre appell�e par
     * l'utilisatateur, mais par les m�thodes qui impl�mentent l'interface
     * ArrayAccess.
     * 
     * TODO : comment connait-on le nombre de champs ?
     * xapian : les num�ros peuvent avoir des trous... (champs supprim�)
     * non cas� : fieldname(i), fieldType(i), fieldCount...
     */
    abstract protected function getField($offset);
 

    /**
     * Modifie la valeur d'un champ
     * 
     * @param mixed $which index ou nom du champ dont la valeur sera modifi�e.
     * @return mixed la nouvelle valeur du champ ou null pour supprimer ce
     * champ de la notice en cours.
     * 
     * @access protected Cette fonction n'est pas destin� � �tre appell�e par
     * l'utilisatateur, mais par les m�thodes qui impl�mentent l'interface
     * ArrayAccess.
     */
    abstract protected function setField($offset, $value);

    
    /**
     * Initialise la cr�ation d'un nouvel enregistrement
     * 
     * L'enregistrement ne sera effectivement cr�� que lorsque {@link update}
     * sera appell�.
     */
    abstract public function add();
    

    /**
     * Passe la notice en cours en mode �dition.
     * 
     * L'enregistrement  ne sera effectivement cr�� que lorsque {@link update}
     * sera appell�.
     *  
     */
    abstract public function edit();

    
    /**
     * Enregistre les modifications apport�es � une notice apr�s un appel �
     * {@link add} ou � {@link edit}
     */
    abstract public function save();
    

    /**
     * Annule l'op�ration d'ajout ou de modification de notice en cours
     */
    abstract public function cancel();


    /**
     * Supprime la notice en cours
     */
    abstract public function delete();


    /* D�but de l'interface ArrayAccess */

    /**
     * Modifie la valeur d'un champ
     * 
     * Il s'agit d'une des m�thodes de l'interface ArrayAccess qui permet de
     * manipuler les champs de la notice en cours comme s'il s'agissait d'un
     * tableau.
     * 
     * Exemple : $selection['titre']='nouveau titre';
     * 
     * @param mixed $offset nom ou num�ro du champ � modifier
     * @param mixed $value nouvelle valeur du champ
     */
    public function offsetSet($offset, $value)
    {
        $this->setField($offset, $value);
    }


    /**
     * Retourne la valeur d'un champ
     * 
     * Il s'agit d'une des m�thodes de l'interface ArrayAccess qui permet de
     * manipuler les champs de la notice en cours comme s'il s'agissait d'un
     * tableau.
     * 
     * Exemple : echo $selection['titre'];
     * 
     * @param mixed $offset nom ou num�ro du champ � retourner
     * @return mixed la valeur du champ ou null si le champ n'existe pas dans la
     * notice en cours ou a la valeur 'null'.
     */
    public function offsetGet($offset)
    {
        return $this->getField($offset);
    }


    /**
     * Supprime un champ de la notice en cours
     * 
     * Il s'agit d'une des m�thodes de l'interface ArrayAccess qui permet de
     * manipuler les champs de la notice en cours comme s'il s'agissait d'un
     * tableau.
     * 
     * Supprimer un champ de la notice en cours revient � lui affecter la
     * valeur 'null'.
     * 
     * Exemple : unset ($selection ['titre']);
     * (�quivalent � $selection['titre']=null;)
     * 
     * @param mixed $offset nom ou num�ro du champ � supprimer
     */
    public function offsetUnset($offset)
    {
        $this->setField($offset, null);
    }


    /**
     * Teste si un champ existe dans la notice en cours
     * 
     * Il s'agit d'une des m�thodes de l'interface ArrayAccess qui permet de
     * manipuler les champs de la notice en cours comme s'il s'agissait d'un
     * tableau.
     * 
     * Exemple : if (isset($selection['titre']) echo 'existe';
     * 
     * @param mixed $offset nom ou num�ro du champ � tester
     * 
     * @return boolean true si le champ existe dans la notice en cours et � une
     * valeur non-nulle, faux sinon.
     */
    public function offsetExists($offset)
    {
        return ! is_null($this->getField($offset));
    }
    /* Fin de l'interface ArrayAccess */
    

    /* D�but de l'interface Iterator */
    /**
     * Ne fait rien.
     * 
     * Il s'agit d'une des m�thodes de l'interface Iterator qui permet de
     * manipuler la s�lection comme un tableau. 
     * 
     * En th�orie, rewind replace l'it�rarateur sur le premier �l�ment, mais
     * dans notre cas, une s�lection ne peut �tre parcourue qu'une fois du d�but
     * � la fin, donc rewind ne fait rien.
     */
    public function rewind()
    {
    }


    /**
     * Retourne la notice en cours dans la s�lection.
     * 
     * Il s'agit d'une des m�thodes de l'interface Iterator qui permet de
     * manipuler la s�lection comme un tableau. 
     * 
     * Plus exactement, fields() retourne un it�rateur sur les champs de la
     * notice en cours.
     * 
     * @return Iterator
     */
    public function current()
    {
        return $this->fields();
    }


    /**
     * Retourne le rang de la notice en cours, c'est � dire le num�ro d'ordre
     * de la notice en cours au sein des r�ponses obtenues
     * 
     * Il s'agit d'une des m�thodes de l'interface Iterator qui permet de
     * manipuler la s�lection comme un tableau. 
     * 
     * @return int
     */
    public function key()
    {
        return $this->searchInfo('rank');
    }


    /**
     * Passe � la notice suivante
     * 
     * Il s'agit d'une des m�thodes de l'interface Iterator qui permet de
     * manipuler la s�lection comme un tableau. 
     */
    public function next()
    {
        $this->moveNext();
    }


    /**
     * D�termine si la fin de la s�lection a �t� atteinte.
     * 
     * Il s'agit d'une des m�thodes de l'interface Iterator qui permet de
     * manipuler la s�lection comme un tableau. 
     *
     * @return boolean faux si la fin de la s�lection a �t� atteinte
     */
    public function valid()
    {
        return ! $this->eof;
    }
    
    /* Fin de l'interface Iterator */
    


}
/**
 * Repr�sente un enregistrement de la base
 * 
 * @package     fab
 * @subpackage  config
 */
abstract class DatabaseRecord implements Iterator, ArrayAccess, Countable
{

    protected $parent=null;
    
    public function __construct(Database $parent)
    {
        $this->parent= & $parent;   
    }

    /* D�but de l'interface ArrayAccess */
    /*
     * on impl�mente l'interface arrayaccess pour permettre d'acc�der �
     * $selection->fields comme un tableau.
     * Ainsi, on peut faire echo $selection['tit'], mais on peut aussi faire
     * foreach($selection as $fields)
     *      echo $fields['tit'];
     * L'impl�mentation ci-dessous de ArrayAccess se contente d'appeller les
     * m�thodes correspondantes de la s�lection.
     */

    public function offsetSet($offset, $value)
    {
        $this->parent->offsetSet($offset, $value);
    }

    public function offsetGet($offset)
    {
        return $this->parent->offsetGet($offset);
    }

    public function offsetUnset($offset)
    {
        $this->parent->offsetUnset($offset);
    }

    public function offsetExists($offset)
    {
        return $this->parent->offsetExists($offset);
    }
    /* Fin de l'interface ArrayAccess */
	
}

/**
 * Repr�sente un enregistrement dans une base {@link BisDatabase}
 * 
 * @package     fab
 * @subpackage  config
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
    
    /* D�but de l'interface Countable */
    public function count()
    {
        return $this->fields->count;	
    }
    
    /* Fin de l'interface Countable */
    
    /* D�but de l'interface Iterator */
    /**
     * Ne fait rien.
     * 
     * Il s'agit d'une des m�thodes de l'interface Iterator qui permet de
     * manipuler la s�lection comme un tableau. 
     * 
     * En th�orie, rewind replace l'it�rarateur sur le premier �l�ment, mais
     * dans notre cas, une s�lection ne peut �tre parcourue qu'une fois du d�but
     * � la fin, donc rewind ne fait rien.
     */
    public function rewind()
    {
        //echo 'rewind', ', current=', $this->current, "\n";
        $this->current=1;
    }


    /**
     * Retourne la notice en cours dans la s�lection.
     * 
     * Il s'agit d'une des m�thodes de l'interface Iterator qui permet de
     * manipuler la s�lection comme un tableau. 
     * 
     * Plus exactement, fields() retourne un it�rateur sur les champs de la
     * notice en cours.
     * 
     * @return Iterator
     */
    public function current()
    {
        //echo 'current', ', current=', $this->current, "\n";
        return $this->fields[$this->current]->value;
    }


    /**
     * Retourne le rang de la notice en cours, c'est � dire le num�ro d'ordre
     * de la notice en cours au sein des r�ponses obtenues
     * 
     * Il s'agit d'une des m�thodes de l'interface Iterator qui permet de
     * manipuler la s�lection comme un tableau. 
     * 
     * @return int
     */
    public function key()
    {
        //echo 'key ', ', current=', $this->current, "\n";
        return $this->fields[$this->current]->name;
    }


    /**
     * Passe � la notice suivante
     * 
     * Il s'agit d'une des m�thodes de l'interface Iterator qui permet de
     * manipuler la s�lection comme un tableau. 
     */
    public function next()
    {
        ++$this->current;
        //echo 'next', ', current=', $this->current, "\n";
    }


    /**
     * D�termine si la fin de la s�lection a �t� atteinte.
     * 
     * Il s'agit d'une des m�thodes de l'interface Iterator qui permet de
     * manipuler la s�lection comme un tableau. 
     *
     * @return boolean faux si la fin de la s�lection a �t� atteinte
     */
    public function valid()
    {
        //echo 'valid ?', ', current=', $this->current, "\n";
        return $this->current<=$this->fields->count;
    }
    
    /* Fin de l'interface Iterator */
	
}

/**
 * Repr�sente une base de donn�es Bis
 * 
 * @package     fab
 * @subpackage  config
 */
class BisDatabase extends Database
{
    private $reverse=false; // true : afficher en ordre inverse
    private $count=0;   // le nombre de notices pour l'utilisateur (=count-start)
    private $rank=0; // le "rang" de la notice en cours
    private $fields=null; // un raccourci vers $this->selection->fields
    private $fieldsIterator=null;
    
    protected function doCreate($database, $def, $options=null)
    {
        throw new Exception('non impl�ment�');
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
        // a priori, pas de r�ponses
        $this->eof=true;

        // Analyse les options indiqu�es (start et sort) 
        if (is_array($options))
        {
            $sort=isset($options['sort']) ? $options['sort'] : null;
            $start=isset($options['start']) ? ((int)$options['start'])-1 : 0;
            if ($start<0) $start=0;
        }
        else
        {
            $sort=null;
            $start=0;
        }
        //echo 'equation=', $equation, ', options=', print_r($options,true), ', sort=', $sort, ', start=', $start, "\n";
        
        // Lance la recherche
        $this->rank=0;
        $this->selection->equation=$equation;
        
        // Pas de r�ponse ? return false
        $this->count=$this->selection->count();
        if ($this->count==0) return false;
        
        // Si start est sup�rieur � count, return false
        if ($this->count<0 or $this->count<=$start)
        {
            $this->selection->moveLast();
            $this->selection->moveNext();
            return false;	
        }
        
        $this->rank=$start+1;
        
        // G�re l'ordre de tri et va sur la start-i�me r�ponse
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
        
        // Retourne le r�sultat
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
echo '<pre>';

echo "Ouverture de la base\n";
$selection=Database::open('ascodocpsy', false, 'bis');
echo "\n", 'Base ouverte. Type=', $selection->getType(), "\n";
echo "Lancement d'une recherche 'article'\n";
$nb=0;
if (! $selection->search('article', array('sort'=>'%', 'start'=>1)))
    echo "Aucune r�ponse\n";
else
{
    echo $selection->count(), " r�ponses\n";
    $time=microtime(true);	
//    do
//    {
//        echo 
//            '<li>Nouvelle m�thode :', 
//            ' r�ponse n� ', $selection->searchInfo('rank') ,
//            ', ref=', $selection['ref'], 
//            ', typdoc=', $selection['type'],
//            ', titre=', $selection['tit'],
//
//            "</li>\n"; 	
//    
//        //$selection['tit']='essai';
//    } while ($selection->next() && (++$nb<1000));
//    

echo "La base contient ", count($selection->fields()), " champs, ", $selection->fields()->count(), "\n";
echo "Premier parcours\n";
    foreach($selection as $rank=>$fields)
    {
        echo $rank, '. ';
        echo 'acc�s direct au titre : ', $fields['tit'], "\n";
        foreach($fields as $name=>$value)
            echo $name, ' : ', $value, "\n";
        
        echo "//\n";
        if (++$nb>10) break;            
    }
    
    echo 'time : ', microtime(true)-$time;

echo "Second parcours\n";
    foreach($selection as $rank=>$fields)
        echo $rank, print_r($fields, true), "\n";
    
    echo 'time : ', microtime(true)-$time;
}


echo "count=", $selection->count();

die();
// code pour balayer une notice dont on ne sait rien
edit();
foreach ($selection->fields() as $name=>$value) // balaye tous les champs
{
    if ($value) echo $name, ' : ', $value, "\n";
    $selection[$name]='new value';
    echo $selection->fieldInfo($name, 'controls');
}
save();


// on n'acc�de plus jamais � un champ avec un num�ro. uniquement par son nom
// si possible, rendre les noms de champ insensibles � la casse
// impl�menter un it�rateur fields() sur les champs
// impl�menter un it�rateur sur la s�lection ??
// plus de fonction fieldsCount()
// fonction fieldInfo($fieldName, $infoName)->mixed

// locker une base .???

// copy($destination)
// sort($key)
// compact()
// ftpTo(serveur, port, path, username, password)

// date:8+,titperio:20-,titre:20+, 

// Acc�s aux termes de l'index
// cr�ation d'un expand set (eset)
// cr�ation d'un result set (rset)


?>
