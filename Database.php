<?php
/**
 * @package     fab
 * @subpackage  database
 * @author      dmenard
 * @version     SVN: $Id$
 */

// TODO: implémenter append()
// récupérer une notice $selection[$ref]
// ajout d'une notice : $selection[]=array('ref'=>12, 'tit'=>'xxx'...)
// suppression d'une notice unset($selection[$ref])
// modification d'une notice : $selection[$ref]=$t

/**
 * Interface d'accès aux bases de données.
 * 
 * Database est à la fois une classe statique offrant des méthodes simples pour
 * créer ({@link create()}) ou ouvrir ({@link open()})une base de données et une
 * interface pour toutes les classes descendantes.
 * 
 * @package     fab
 * @subpackage  config
 */
abstract class Database implements ArrayAccess, Iterator
{
    /**
     * @var string le type de base de données en cours ('bis' ou
     * 'xapian').
     * @access protected
     */
    protected $type=null;

    /**
     * @var boolean vrai si on a atteint la fin de la sélection
     * 
     * Les drivers qui héritent de cette classe doivent tenir à jour cette
     * propriété. Notamment, les fonctions {@link search()} et {@link
     * moveNext()} doivent initialiser eof à true ou false selon qu'il y a ou
     * non une notice en cours.
     * 
     * @access protected
     */
    protected $eof=true;
    
    /**
     * Le constructeur est privé car ni cette classe, ni aucun des drivers qui
     * héritent de cette classe ne sont instanciables.
     * 
     * L'utilisateur ne manipule cette classe que via les méthodes statiques
     * proposées ({@link create}, {@link open}, ...) et via les méthodes
     * non statiques implémentées par les drivers.
     */
    protected function __construct()
    {
    }


    /**
     * Crée une base de données.
     * 
     * Une erreur est générée si la base de données à créer existe déjà.
     * 
     * La fonction ne peut pas être surchargée dans les drivers (final).
     * 
     * @param string $database alias ou path de la base de données à créer.
     * 
     * @param array $def tableau contenant la définition de la structure de la
     * base
     * 
     * @param string $type type de la base de données à créer. Ignoré si
     * $database désigne un alias (dans ce cas, c'est le type indiqué dans la
     * config de l'alias qui est prioritaire).
     * 
     * @param array $options tableau contenant des options supplémentaires. Les
     * options disponibles dépendent du backend utilisée. Chaque backend ignore
     * silencieusement les options qu'il ne reconnait pas ou ne sait pas gérer.
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

        // Utilise /config/db.yaml pour convertir l'alias en chemin et déterminer le type de base
//        $type=Config::get("db.$database.type", $type);
//        $database=Config::get("db.$database.path", $database);
        
        // TODO: gérer les chemins relatifs
        
        // Crée une instance de la bonne classe en fonction du type, crée la base et retourne l'objet obtenu
//        debug && Debug::log("Création de la base '%s' de type '%s'", $database, $type);
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
                throw new Exception("Impossible de créer la base '$database' : le type de base '$type' n'est pas supporté.");
        }
        $db->type=$type;
        return $db;
    }


    /**
     * Méthode implémentée dans les drivers : crée la base
     * 
     * @param string $database alias ou path de la base de données à créer.
     * 
     * @param array $def tableau contenant la définition de la structure de la
     * base
     * 
     * @param array $options tableau contenant des options supplémentaires. Les
     * options disponibles dépendent du backend utilisée. Chaque backend ignore
     * silencieusement les options qu'il ne reconnait pas ou ne sait pas gérer.
     */
    abstract protected function doCreate($database, $def, $options=null);
    
    public function compareDef($def)
    {
        
    }
    
    /**
     * Modifie la structure d'une base de données en lui appliquant une nouvelle
     * définition.
     * 
     * @param array $newDef le tableau contenant la définition de la nouvelle
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
     * Ouvre une base de données.
     * 
     * Une erreur est générée si la base de données à créer n'existe pas.
     * 
     * La fonction ne peut pas être surchargée dans les drivers (final).
     * 
     * @param string $database alias ou path de la base de données à ouvrir.
     * 
     * @param boolean $readOnly true pour ouvrir la base en lecture seule, false
     * pour l'ouvrir en lecture/écriture.
     * 
     * @param string $type type de la base de données à ouvrir. Ignoré si
     * $database désigne un alias (dans ce cas, c'est le type indiqué dans la
     * config de l'alias qui est prioritaire).
     */
    final public static function open($database, $readOnly=true, $type=null)
    {
        // Utilise /config/db.yaml pour convertir l'alias en chemin et déterminer le type de base
//        $type=Config::get("db.$database.type", $type);
//        $database=Config::get("db.$database.path", $database);
        
        // TODO: gérer les chemins relatifs
        
        // Crée une instance de la bonne classe en fonction du type, crée la base et retourne l'objet obtenu
//        debug && Debug::log("Création de la base '%s' de type '%s'", $database, $type);
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
                throw new Exception("Impossible d'ouvrir la base '$database' : le type de base '$type' n'est pas supporté.");
        }
        $db->type=$type;
        return $db;
    }


    /**
     * Méthode implémentée dans les drivers : ouvre la base
     * 
     * @param string $database alias ou path de la base de données à créer.
     * 
     * @param boolean $readOnly true pour ouvrir la base en lecture seule, false
     * pour l'ouvrir en lecture/écriture.
     */
    abstract protected function doOpen($database, $readOnly=true);
    

    /**
     * Lance une recherche et sélectionne les notices correspondantes.
     * 
     * Si la recherche aboutit, la sélection est positionnée sur la première
     * réponse obtenue (ou sur la réponse indiquée par 'start' dans les
     * options).
     * 
     * @param string $equation l'équation de recherche à exécuter
     * @param array $options tableau d'options supportées par le backend qui
     * indiquent la manière dont la recherche doit être faite.
     * 
     * Options disponibles :
     * 
     * - 'sort' : une chaine indiquant la manière dont les réponses doivent être
     * triées : 
     * 
     * <li>'%' : trier les notices par score (la meilleure en tête)
     * <li>'+' : trier par ordre croissant de numéro de document
     * <li>'-' : trier par ordre décroissant de numéro de document
     * <li>'xxx+' : trier sur le champ xxx, par ordre croissant
     * <li>'xxx-' : trier sur le champ xxx, par ordre décroissant
     * <li>'xxx+%' : trier sur le champ xxx par ordre croissant, puis par
     * pertinence.
     * <li>'xxx-%' : trier sur le champ xxx par ordre décroissant, puis par
     * pertinence.
     * 
     * - 'start': entier (>0) indiquant la notice sur laquelle se positionner
     * une fois la recherche effectuée.
     * 
     * - 'nb' : entier (>0) donnant une indication sur le nombre de notices que
     * l'on compte parcourir. Permet au backend d'optimiser sa recherche qu'en
     * ne recherchant que les nb meilleurs réponses.
     * 
     * - 'min_weight' : entier (>=0), score minimum qu'une notice doit obtenir
     * pour être sélectionnée (0=pas de minimum)
     * 
     * - 'min_percent' : entier (de 0 à 100), pourcentage minimum qu'une notice
     * doit obtenir pour être sélectionnée (0=pas de minimum)
     * 
     * - 'time_bias' : reservé pour le futur, api non figée dans xapian.
     * 
     * - 'collapse' : nom d'un champ utilisé pour regrouper les réponses
     * obtenues. Par exemple, avec un regroupement sur le champ TypDoc, on
     * obtiendra uniquement la meilleure réponse obtenue parmis les articles,
     * puis la meilleures obtenues parmi les ouvrages, et ainsi de suite.
     * 
     * - 'weighting_scheme' : nom et éventuellement paramètres de l'algorithme
     * de pertinence utilisé. Les valeurs possibles sont : 'bool', 'trad',
     * 'trad(k), 'bm25' ou 'bm25(k1,k2,k3,b,m)'
     * 
     * @return boolean true si au moins une notice a été trouvée, false s'il n'y
     * a aucune réponse.
     * 
     */
    abstract public function search($equation=null, $options=null);
    

    /**
     * Retourne des meta-informations sur la dernière recherche exécutée ou sur
     * la notice courante de la sélection.
     * 
     * @param string $what le nom de l'information à récupérer.
     * 
     * Les valeurs possibles dépendent du backend utilisé.
     * 
     * tous:
     * <li>'equation' : retourne l'équation de recherche telle qu'elle a été
     * interprétée par le backend
     * 
     * xapian :
     * 
     * Meta-données portant sur la sélectionc en cours 
     * <li>'max_weight' : retourne le poids obtenu par la meilleure notice
     * sélectionnée
     * <li>'stop_words' : retourne une chaine contenant les termes présents dans
     * l'équation qui ont été ignorés lors de la recherche (mots vides).
     * <li>'query_terms' : retourne une chaine contenant les termes de la
     * requête qui ont été utilisés pour la recherche. Pour une recherche
     * simple, cela retournera les termes de la requête moins les mots-vides ;
     * pour une recherche avec troncature, ça retournera également tous les
     * termes qui commence par le préfixe indiqué.
     * 
     * 
     * Meta-données portant sur la notice en cours au sein de la sélection :
     * <li>'rank' : retourne le numéro de la réponse en cours (i.e. c'est la
     * ième réponse)
     * <li>'weight' : retourne le poids obtenu par la notice courante
     * <li>'percent' : identique à weight, mais retourne le poids sous forme
     * d'un pourcentage
     * <li>'collapse' : retourne le nombre de documents similaires qui sont
     * "cachés" derrière la notice courante
     * <li>'matching_terms' : retourne une chaîne contenant les termes de
     * l'équation de recherche sur lesquels ce document a été sélectionné.
     */
    abstract public function searchInfo($what);
    

    /**
     * Retourne une estimation du nombre de notices actuellement sélectionnées.
     * 
     * @param int $countType indique l'estimation qu'on souhaite obtenir. Les
     * valeurs possibles sont :
     * 
     * - 0 : estimation la plus fiable du nombre de notices. Le backend fait de
     * son mieux pour estimer le nombre de notices sélectionnées, mais rien ne
     * garantit qu'il n'y en ait pas en réalité plus ou moins que le nombre
     * indiqué.
     * 
     * - 1 : le nombre minimum de notices sélectionnées. Le backend garantit
     * qu'il y a au moins ce nombre de notices dans la sélection.
     * 
     * - 2 : le nombre maximum de notices sélectionnées. Le backend garantit
     * qu'il n'y a pas plus de notices dnas la sélection que le nombre retourné.
     */
    abstract public function count($countType=0);
    
    
    /**
     * Passe à la notice suivante, si elle existe.
     * 
     * @return boolean true si on a toujours une notice en cours, false si on a
     * passé la fin de la sélection (eof).
     */
    abstract public function moveNext();

    
    /**
     * Retourne la notice en cours
     * 
     * @return DatabaseRecord un objet représentant la notice en cours. Cette
     * objet peut être manipulé comme un tableau (utilisation dans une
     * boucle foreach, lecture/modification de la valeur d'un champ en
     * utilisant les crochets, utilisation de count pour connaître le nombre de
     * champs dans la base...)
     */
    abstract public function fields();


    /**
     * Retourne la valeur d'un champ
     * 
     * @param mixed $which index ou nom du champ dont la valeur sera retournée.
     * @return mixed la valeur du champ ou null si ce champ ne figure pas dans
     * l'enregistrement courant.
     * 
     * @access protected Cette fonction n'est pas destiné à être appellée par
     * l'utilisatateur, mais par les méthodes qui implémentent l'interface
     * ArrayAccess.
     * 
     * TODO : comment connait-on le nombre de champs ?
     * xapian : les numéros peuvent avoir des trous... (champs supprimé)
     * non casé : fieldname(i), fieldType(i), fieldCount...
     */
    abstract protected function getField($offset);
 

    /**
     * Modifie la valeur d'un champ
     * 
     * @param mixed $which index ou nom du champ dont la valeur sera modifiée.
     * @return mixed la nouvelle valeur du champ ou null pour supprimer ce
     * champ de la notice en cours.
     * 
     * @access protected Cette fonction n'est pas destiné à être appellée par
     * l'utilisatateur, mais par les méthodes qui implémentent l'interface
     * ArrayAccess.
     */
    abstract protected function setField($offset, $value);

    
    /**
     * Initialise la création d'un nouvel enregistrement
     * 
     * L'enregistrement ne sera effectivement créé que lorsque {@link update}
     * sera appellé.
     */
    abstract public function add();
    

    /**
     * Passe la notice en cours en mode édition.
     * 
     * L'enregistrement  ne sera effectivement créé que lorsque {@link update}
     * sera appellé.
     *  
     */
    abstract public function edit();

    
    /**
     * Enregistre les modifications apportées à une notice après un appel à
     * {@link add} ou à {@link edit}
     */
    abstract public function save();
    

    /**
     * Annule l'opération d'ajout ou de modification de notice en cours
     */
    abstract public function cancel();


    /**
     * Supprime la notice en cours
     */
    abstract public function delete();


    /* Début de l'interface ArrayAccess */

    /**
     * Modifie la valeur d'un champ
     * 
     * Il s'agit d'une des méthodes de l'interface ArrayAccess qui permet de
     * manipuler les champs de la notice en cours comme s'il s'agissait d'un
     * tableau.
     * 
     * Exemple : $selection['titre']='nouveau titre';
     * 
     * @param mixed $offset nom ou numéro du champ à modifier
     * @param mixed $value nouvelle valeur du champ
     */
    public function offsetSet($offset, $value)
    {
        $this->setField($offset, $value);
    }


    /**
     * Retourne la valeur d'un champ
     * 
     * Il s'agit d'une des méthodes de l'interface ArrayAccess qui permet de
     * manipuler les champs de la notice en cours comme s'il s'agissait d'un
     * tableau.
     * 
     * Exemple : echo $selection['titre'];
     * 
     * @param mixed $offset nom ou numéro du champ à retourner
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
     * Il s'agit d'une des méthodes de l'interface ArrayAccess qui permet de
     * manipuler les champs de la notice en cours comme s'il s'agissait d'un
     * tableau.
     * 
     * Supprimer un champ de la notice en cours revient à lui affecter la
     * valeur 'null'.
     * 
     * Exemple : unset ($selection ['titre']);
     * (équivalent à $selection['titre']=null;)
     * 
     * @param mixed $offset nom ou numéro du champ à supprimer
     */
    public function offsetUnset($offset)
    {
        $this->setField($offset, null);
    }


    /**
     * Teste si un champ existe dans la notice en cours
     * 
     * Il s'agit d'une des méthodes de l'interface ArrayAccess qui permet de
     * manipuler les champs de la notice en cours comme s'il s'agissait d'un
     * tableau.
     * 
     * Exemple : if (isset($selection['titre']) echo 'existe';
     * 
     * @param mixed $offset nom ou numéro du champ à tester
     * 
     * @return boolean true si le champ existe dans la notice en cours et à une
     * valeur non-nulle, faux sinon.
     */
    public function offsetExists($offset)
    {
        return ! is_null($this->getField($offset));
    }
    /* Fin de l'interface ArrayAccess */
    

    /* Début de l'interface Iterator */
    /**
     * Ne fait rien.
     * 
     * Il s'agit d'une des méthodes de l'interface Iterator qui permet de
     * manipuler la sélection comme un tableau. 
     * 
     * En théorie, rewind replace l'itérarateur sur le premier élément, mais
     * dans notre cas, une sélection ne peut être parcourue qu'une fois du début
     * à la fin, donc rewind ne fait rien.
     */
    public function rewind()
    {
    }


    /**
     * Retourne la notice en cours dans la sélection.
     * 
     * Il s'agit d'une des méthodes de l'interface Iterator qui permet de
     * manipuler la sélection comme un tableau. 
     * 
     * Plus exactement, fields() retourne un itérateur sur les champs de la
     * notice en cours.
     * 
     * @return Iterator
     */
    public function current()
    {
        return $this->fields();
    }


    /**
     * Retourne le rang de la notice en cours, c'est à dire le numéro d'ordre
     * de la notice en cours au sein des réponses obtenues
     * 
     * Il s'agit d'une des méthodes de l'interface Iterator qui permet de
     * manipuler la sélection comme un tableau. 
     * 
     * @return int
     */
    public function key()
    {
        return $this->searchInfo('rank');
    }


    /**
     * Passe à la notice suivante
     * 
     * Il s'agit d'une des méthodes de l'interface Iterator qui permet de
     * manipuler la sélection comme un tableau. 
     */
    public function next()
    {
        $this->moveNext();
    }


    /**
     * Détermine si la fin de la sélection a été atteinte.
     * 
     * Il s'agit d'une des méthodes de l'interface Iterator qui permet de
     * manipuler la sélection comme un tableau. 
     *
     * @return boolean faux si la fin de la sélection a été atteinte
     */
    public function valid()
    {
        return ! $this->eof;
    }
    
    /* Fin de l'interface Iterator */
    


}
/**
 * Représente un enregistrement de la base
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

    /* Début de l'interface ArrayAccess */
    /*
     * on implémente l'interface arrayaccess pour permettre d'accêder à
     * $selection->fields comme un tableau.
     * Ainsi, on peut faire echo $selection['tit'], mais on peut aussi faire
     * foreach($selection as $fields)
     *      echo $fields['tit'];
     * L'implémentation ci-dessous de ArrayAccess se contente d'appeller les
     * méthodes correspondantes de la sélection.
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
 * Représente un enregistrement dans une base {@link BisDatabase}
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
    
    /* Début de l'interface Countable */
    public function count()
    {
        return $this->fields->count;	
    }
    
    /* Fin de l'interface Countable */
    
    /* Début de l'interface Iterator */
    /**
     * Ne fait rien.
     * 
     * Il s'agit d'une des méthodes de l'interface Iterator qui permet de
     * manipuler la sélection comme un tableau. 
     * 
     * En théorie, rewind replace l'itérarateur sur le premier élément, mais
     * dans notre cas, une sélection ne peut être parcourue qu'une fois du début
     * à la fin, donc rewind ne fait rien.
     */
    public function rewind()
    {
        //echo 'rewind', ', current=', $this->current, "\n";
        $this->current=1;
    }


    /**
     * Retourne la notice en cours dans la sélection.
     * 
     * Il s'agit d'une des méthodes de l'interface Iterator qui permet de
     * manipuler la sélection comme un tableau. 
     * 
     * Plus exactement, fields() retourne un itérateur sur les champs de la
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
     * Retourne le rang de la notice en cours, c'est à dire le numéro d'ordre
     * de la notice en cours au sein des réponses obtenues
     * 
     * Il s'agit d'une des méthodes de l'interface Iterator qui permet de
     * manipuler la sélection comme un tableau. 
     * 
     * @return int
     */
    public function key()
    {
        //echo 'key ', ', current=', $this->current, "\n";
        return $this->fields[$this->current]->name;
    }


    /**
     * Passe à la notice suivante
     * 
     * Il s'agit d'une des méthodes de l'interface Iterator qui permet de
     * manipuler la sélection comme un tableau. 
     */
    public function next()
    {
        ++$this->current;
        //echo 'next', ', current=', $this->current, "\n";
    }


    /**
     * Détermine si la fin de la sélection a été atteinte.
     * 
     * Il s'agit d'une des méthodes de l'interface Iterator qui permet de
     * manipuler la sélection comme un tableau. 
     *
     * @return boolean faux si la fin de la sélection a été atteinte
     */
    public function valid()
    {
        //echo 'valid ?', ', current=', $this->current, "\n";
        return $this->current<=$this->fields->count;
    }
    
    /* Fin de l'interface Iterator */
	
}

/**
 * Représente une base de données Bis
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
    echo "Aucune réponse\n";
else
{
    echo $selection->count(), " réponses\n";
    $time=microtime(true);	
//    do
//    {
//        echo 
//            '<li>Nouvelle méthode :', 
//            ' réponse n° ', $selection->searchInfo('rank') ,
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
        echo 'accès direct au titre : ', $fields['tit'], "\n";
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


// on n'accède plus jamais à un champ avec un numéro. uniquement par son nom
// si possible, rendre les noms de champ insensibles à la casse
// implémenter un itérateur fields() sur les champs
// implémenter un itérateur sur la sélection ??
// plus de fonction fieldsCount()
// fonction fieldInfo($fieldName, $infoName)->mixed

// locker une base .???

// copy($destination)
// sort($key)
// compact()
// ftpTo(serveur, port, path, username, password)

// date:8+,titperio:20-,titre:20+, 

// Accès aux termes de l'index
// création d'un expand set (eset)
// création d'un result set (rset)


?>
