<?php

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
debug && Debug::log("Chargement de la configuration des bases de données");
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

// TODO : revoir API pour editRecord, saveRecord, addNew

/**
 * @package     fab
 * @subpackage  database
 * @author 		Daniel Ménard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id$
 */

/**
 * Interface d'accès aux bases de données.
 * 
 * Database est à la fois une classe statique offrant des méthodes simples pour
 * créer ({@link create()}) ou ouvrir ({@link open()})une base de données et une
 * interface pour toutes les classes descendantes.
 * 
 * Database implémente l'interface Iterator. Cela permet de parcourir les
 * enregistrements très simplement en utilisant une boucle foreach :
 * 
 * <code>
 * foreach ($selection as $rank=>$record) 
 *     echo 'Réponse numéro ', $rank, ' : ref ', $record['ref'], "\n";
 * </code>
 * 
 * La même chose peut être faite dans un template en utilisant le tag <loop> :
 * 
 * <code>
 * <loop on="$selection" as="$rank,$record">
 *     Réponse numéro $rank : ref {$record['ref']}
 * </loop>
 * </code>
 * 
 * Database implémente également l'interface ArrayAccess. Cela permet de
 * manipuler les champs de la notice en cours comme s'il s'agissait d'un
 * tableau :
 * 
 * <code>
 * echo 'Titre original : ', $selection['titre'], "\n";
 * $selection->edit();
 * $selection['titre']='autre chose';
 * $selection->save();
 * echo 'Nouveau titre : ', $selection['titre'], "\n";
 * </code>
 * 
 * /NON
 * 
 * L'ajout, la modification et la suppression d'un enregistrement se font
 * en utilisant les méthodes {@link add()}, {@link edit()} et {@link save()}
 * 
 * Ajout d'un enregistrement :
 * 
 * <code>
 * $selection->add();
 * $selection['titre']='titre du rapport';
 * $selection['type']='rapport';
 * $selection->save();
 * </code>
 * 
 * Modification d'un enregistrement :
 * 
 * <code>
 * $selection->edit();
 * $selection['type'] ='rapport officiel';
 * $selection->save();
 * </code>
 * 
 * Suppression d'un enregistrement :
 * 
 * <code>
 * $selection->delete();
 * </code>
 * 
 * @package      fab
 * @subpackage  database
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
    
    public $record=null;
    
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
            case 'bis':
                require_once dirname(__FILE__).'/BisDatabase.php';
                $db=new BisDatabase();
                $db->doCreate($database, $def, $options);
                break;
                
            case 'xapian':
                require_once dirname(__FILE__).'/XapianDatabase.php';
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
        $type=Config::get("db.$database.type", $type);
        $database=Config::get("db.$database.path", $database);
        
        // Si c'est un chemin relatif, recherche dans /data/db
        if (Utils::isRelativePath($database))
        {
            $path=Utils::searchFile($database, Runtime::$root . 'data/db');
            if ($path=='')
                throw new Exception("Impossible de trouver la base '$database'");
        }

        // Crée une instance de la bonne classe en fonction du type, crée la base et retourne l'objet obtenu
        debug && Debug::log("Ouverture de la base '%s' de type '%s' (%s)", $database, $type, $path);
        switch($type=strtolower($type))
        {
            case 'bis':
                require_once dirname(__FILE__).'/BisDatabase.php';
                $db=new BisDatabase();
                $db->doOpen($path, $readOnly);
                break;
                
            case 'xapian':
                require_once dirname(__FILE__).'/XapianDatabase.php';
                $db=new XapianDatabase();
                $db->doOpen($path, $readOnly);
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
     * Exemple d'utilisation :
     * 
     * <code>
     * if ($selection->search('type:article', array('sort'=>'-', 'start'=>10)))
     * ... afficher les réponses obtenues
     * else
     * ... aucune réponse
     * </code>
     * 
     * 
     * @param string $equation l'équation de recherche à exécuter
     * @param array $options tableau d'options supportées par le backend qui
     * indiquent la manière dont la recherche doit être faite.
     * 
     * Options disponibles :
     * 
     * <b>'sort'</b> : une chaine indiquant la manière dont les réponses doivent être
     * triées : 
     *
     *     - '%' : trier les notices par score (la meilleure en tête)
     *     - '+' : trier par ordre croissant de numéro de document
     *     - '-' : trier par ordre décroissant de numéro de document
     *     - 'xxx+' : trier sur le champ xxx, par ordre croissant
     *     - 'xxx-' : trier sur le champ xxx, par ordre décroissant
     *     - 'xxx+%' : trier sur le champ xxx par ordre croissant, puis par
     *       pertinence.
     *     - 'xxx-%' : trier sur le champ xxx par ordre décroissant, puis par
     *       pertinence.
     *
     * <b>'start'</b> : entier (>0) indiquant la notice sur laquelle se positionner
     * une fois la recherche effectuée.
     * 
     * <b>'max'</b> : entier indiquant le nombre maximum de notices à retourner.
     * Permet au backend d'optimiser sa recherche en ne recherchant que les max
     * meilleures réponses. Indiquez -1 pour obtenir toutes les réponses.
     * Indiquez 0 si vous voulez seulement savoir combien il y a de réponses.
     * 
     * <b>'min_weight'</b> : entier (>=0), score minimum qu'une notice doit obtenir
     * pour être sélectionnée (0=pas de minimum)
     * 
     * <b>'min_percent'</b> : entier (de 0 à 100), pourcentage minimum qu'une notice
     * doit obtenir pour être sélectionnée (0=pas de minimum)
     * 
     * <b>'time_bias'</b> : reservé pour le futur, api non figée dans xapian.
     * 
     * <b>'collapse'</b> : nom d'un champ utilisé pour regrouper les réponses
     * obtenues. Par exemple, avec un regroupement sur le champ TypDoc, on
     * obtiendra uniquement la meilleure réponse obtenue parmis les articles,
     * puis la meilleures obtenues parmi les ouvrages, et ainsi de suite.
     * 
     * <b>'weighting_scheme'</b> : nom et éventuellement paramètres de l'algorithme
     * de pertinence utilisé. Les valeurs possibles sont : 
     *     - 'bool'
     *     - 'trad'
     *     - 'trad(k)
     *     - 'bm25' 
     *     - 'bm25(k1,k2,k3,b,m)'
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
     *     - 'rank' : retourne le numéro de la réponse en cours (i.e. c'est
     *       la ième réponse)
     *     - 'weight' : retourne le poids obtenu par la notice courante
     *     - 'percent' : identique à weight, mais retourne le poids sous forme
     *       d'un pourcentage
     *     - 'collapse' : retourne le nombre de documents similaires qui sont
     *       "cachés" derrière la notice courante
     *     - 'matching_terms' : retourne une chaîne contenant les termes de
     *       l'équation de recherche sur lesquels ce document a été sélectionné.
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
     * 
     * @access protected
     */
    abstract protected function moveNext();

    
//    /**
//     * Retourne la notice en cours
//     * 
//     * @return DatabaseRecord un objet représentant la notice en cours. Cette
//     * objet peut être manipulé comme un tableau (utilisation dans une
//     * boucle foreach, lecture/modification de la valeur d'un champ en
//     * utilisant les crochets, utilisation de count pour connaître le nombre de
//     * champs dans la base...)
//     */
//    abstract public function fields();


//    /**
//     * Retourne la valeur d'un champ
//     * 
//     * Cette fonction n'est pas destiné à être appellée par l'utilisatateur, 
//     * mais par les méthodes qui implémentent l'interface ArrayAccess.
//     * 
//     * @access protected
//     * 
//     * @param mixed $which index ou nom du champ dont la valeur sera retournée.
//     * 
//     * @return mixed la valeur du champ ou null si ce champ ne figure pas dans
//     * l'enregistrement courant.
//     */
//    abstract protected function getField($offset);
// 
//
//    /**
//     * Modifie la valeur d'un champ
//     * 
//     * Cette fonction n'est pas destiné à être appellée par l'utilisatateur, 
//     * mais par les méthodes qui implémentent l'interface ArrayAccess.
//     * 
//     * @access protected
//     * 
//     * @param mixed $which index ou nom du champ dont la valeur sera modifiée.
//     * 
//     * @return mixed la nouvelle valeur du champ ou null pour supprimer ce
//     * champ de la notice en cours.
//     */
//    abstract protected function setField($offset, $value);

    
    /**
     * Initialise la création d'un nouvel enregistrement
     * 
     * L'enregistrement ne sera effectivement créé que lorsque {@link update()}
     * sera appellé.
     */
    abstract public function addRecord();
    

    /**
     * Passe la notice en cours en mode édition.
     * 
     * L'enregistrement  ne sera effectivement créé que lorsque {@link update}
     * sera appellé.
     *  
     */
    abstract public function editRecord();

    
    /**
     * Enregistre les modifications apportées à une notice après un appel à
     * {@link add()} ou à {@link edit()}
     */
    abstract public function saveRecord();
    

    /**
     * Annule l'opération d'ajout ou de modification de notice en cours
     */
    abstract public function cancelUpdate();


    /**
     * Supprime la notice en cours
     */
    abstract public function deleteRecord();


    /* Début de l'interface ArrayAccess */

    /* En fait, on implémente pas réellement ArrayAccess, on se contente
     * de tout déléguer à record ( de type DatabaseRecord) qui lui implémente
     * réellement l'interface.
     * Comme il n'existe pas, dans SPL, d'interface ArrayAccessDelegate, on
     * est obligé de le faire nous-même 
     */
     
    /**
     * Modifie la valeur d'un champ
     * 
     * Il s'agit d'une des méthodes de l'interface ArrayAccess qui permet de
     * manipuler les champs de la notice en cours comme s'il s'agissait d'un
     * tableau.
     * 
     * Exemple : 
     * <code>
     * $selection['titre']='nouveau titre';
     * </code>
     * 
     * @param mixed $offset nom du champ à modifier
     * 
     * @param mixed $value nouvelle valeur du champ
     */
    public function offsetSet($offset, $value)
    {
//        $this->setField($offset, $value);
        $this->record->offsetSet($offset, $value);
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
     * @param mixed $offset nom du champ à retourner
     * 
     * @return mixed la valeur du champ ou null si le champ n'existe pas dans la
     * notice en cours ou a la valeur 'null'.
     */
    public function offsetGet($offset)
    {
        return $this->record->offsetGet($offset);
//        return $this->getField($offset);
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
     * Exemple : 
     * <code>
     * unset($selection ['titre']);
     * </code>
     * (équivalent à $selection['titre']=null;)
     * 
     * @param mixed $offset nom ou numéro du champ à supprimer
     */
    public function offsetUnset($offset)
    {
        $this->record->offsetUnset($offset);
//        $this->setField($offset, null);
    }


    /**
     * Teste si un champ existe dans la notice en cours
     * 
     * Il s'agit d'une des méthodes de l'interface ArrayAccess qui permet de
     * manipuler les champs de la notice en cours comme s'il s'agissait d'un
     * tableau.
     * 
     * Exemple :
     * <code>
     * if (isset($selection['titre']) echo 'existe';
     * </code>
     * 
     * @param mixed $offset nom ou numéro du champ à tester
     * 
     * @return boolean true si le champ existe dans la notice en cours et à une
     * valeur non-nulle, faux sinon.
     */
    public function offsetExists($offset)
    {
        return $this->record->offsetExists($offset);
//        return ! is_null($this->getField($offset));
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
//        echo "Appel de Database::rewind()<br />";
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
//        echo "Appel de Database::current()<br />";
        return $this->record;
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
//        echo "Appel de Database::key()<br />";
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
//        echo "Appel de Database::next()<br />";
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
//        echo "Appel de Database::valid() result=",((! $this->eof)?"true":"false"),"<br />";
        return ! $this->eof;
    }
    
    /* Fin de l'interface Iterator */
    
    
    /**
     * Chercher/Remplacer à partir d'une exp rég sur l'enregistrement en cours d'une base de données ouverte 
     * (peut être appelé dans une boucle sur une sélection par exemple)
     * 
     * @param array fields la liste des champs sur lesquels on effectue le chercher/remplacer
     * @param string $pattern le pattern à utiliser pour l'expression régulière de recherche
     * @param string $replace la chaîne de remplacement pour les occurences trouvées
     * @param bool $caseSensitive indique si la recherche est sensible à la casse
     * 
     * @return false s'il y a une erreur (pattern de recherche mal formé) et true sinon
     */
    public function pregReplace($fields, $pattern, $replace, $caseSensitive = true)
    {
        // (une partie de cette vérification est faite plus bas et peut-être recopiée)
        
        if ($pattern === null || trim($pattern) == '')
            return false;
        else
            $pattern = trim($pattern);
        
        // vérifie que $pattern contient bien les deux délimiteurs
        $delimiter = $pattern[0];  
        $end = strpos($pattern, $delimiter, 1); // position du délimiteur de fin de pattern de recherche
        if ($end === false)     // pas de délimiteur de fin ou alors, problème avec le délimiteur de début
            return false;
        
        if (! $caseSensitive)
        {            
            if ($end == strlen($pattern)-1 || strpos($pattern, 'i', $end) === false)
                $pattern = $pattern . 'i';  // spécifie une recherche insensible à la casse
        }
        
        // boucle sur les champs pour effectuer le remplacement éventuel
        foreach($this->record as $field => $value)
        {
            if (in_array($field, $fields))
                $this->record[$field] = preg_replace($pattern, $replace, $value);    
        } 
        
        return true;
            
    }
    
    
    /**
     * Chercher/Remplacer à partir d'une chaîne de caractères sur l'enregistrement en cours d'une base de données ouverte 
     * (peut être appelé dans une boucle sur une sélection par exemple)
     * 
     * @param array fields la liste des champs sur lesquels on effectue le chercher/remplacer
     * @param string $search la chaîne de caractère de recherche
     * @param string $replace la chaîne de remplacement pour les occurences trouvées
     * @param bool $caseSensitive indique si la recherche est (true) ou non (false) sensible à la case
     * @param bool $wholeWord indique si on recherche uniquement le(s) mot(s) entier(s) correspondant(s)
     * à search (true) dans l'enregistrement en cours
     * 
     * @return false si une erreur est survenue et true sinon
     */
    public function strReplace($fields, $search, $replace, $caseSensitive = true, $wholeWord = false)
    {        
        if ( ($search == null) || trim($search) == '')
            return false;
        else
            $search = trim($search);
            
        if(! $caseSensitive)
            $search = strtolower($search);   // pour optimiser un peu la boucle principale
        
        if ($wholeWord)
        {
            $search = ' ' . $search . ' ';   // ajoute des espaces pour simplifier la suite
            $replace = ' ' . $replace . ' ';
        }
        
        // boucle sur les champs et effecue le chercher/remplacer
        foreach($this->record as $field => $value)
        {
            if (in_array($field, $fields))
            {
                // TODO : ne fonctionne pas avec des tabulations, "'", etc.
                // Exemple : dans "l'ensemble", "ensemble" est bien un mot mais il n'est pas remplacé
                // si on sélectionne "Mots entiers"
                // => Il faudra sûrement utiliser des expressions régulières si $wholeWord vaut true
                
                if ($wholeWord)
                    $value = ' ' . $value . ' ';    // pré-traitement : ajoute des espaces pour simplifier la suite
                
                if ($caseSensitive)
                    $this->record[$field] = str_replace($search, $replace, $value);
                else
                    $this->record[$field] = str_replace($search, $replace, strtolower($value));
                
                if($wholeWord)      // post-traitement : supprime les 2 espaces ajoutés en pré-traitement       
                    $this->record[$field] = substr($this->record[$field], 1, strlen($this->record[$field])-2);
            }    
        } 
        
        return true;
    }
    
    /**
     * Chercher/Remplacer les champs vides de l'enregistrement en cours d'une base de données ouverte 
     * (peut être appelé dans une boucle sur une sélection par exemple)
     * 
     * @param array fields la liste des champs sur lesquels on effectue le chercher/remplacer
     * @param string $replace la chaîne de remplacement pour les champs vides trouvés
     * 
     */
     public function replaceEmpty($fields, $replace)
     {
        foreach($this->record as $field => $value)
        {
            if (in_array($field, $fields))
            {
                $this->record[$field] = $replace;
            }
        }
     }
}

/**
 * Représente un enregistrement de la base
 * 
 * @package     fab
 * @subpackage  database
 */
abstract class DatabaseRecord implements Iterator, ArrayAccess, Countable
{
    /**
     * @var Database L'objet Database auquel appartient cet enregistrement 
     * @access protected
     */
    protected $database=null;
    
    public function __construct(BisDatabase $database)
    {
        $this->database= & $database;   
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
        $this->database->offsetSet($offset, $value);
    }

    public function offsetGet($offset)
    {
        // normallement, il faudrait convertir le variant en zval php
        // en fonction du type du variant
        // Dans la pratique, on utilise quasiment jamais BIS pour autre
        // chose que des chaines (si ce n'est REF). Ca me semble sans
        // risque de caster systématiquement vers une chaine
        $value=$this->database->offsetGet($offset);
        return is_null($value) ? null : (string)$value;
//        return $this->variantToZVal($this->parent->offsetGet($offset));
    }

    public function offsetUnset($offset)
    {
        $this->database->offsetUnset($offset);
    }

    public function offsetExists($offset)
    {
        return $this->database->offsetExists($offset);
    }
    /* Fin de l'interface ArrayAccess */
    

}


//echo '<pre>';

//echo "Ouverture de la base\n";
//$selection=Database::open('ascodocpsy', false, 'bis');
//echo "\n", 'Base ouverte. Type=', $selection->getType(), "\n";
//echo "Lancement d'une recherche 'article'\n";
//$nb=0;
//if (! $selection->search('article', array('sort'=>'%', 'start'=>1)))
//    echo "Aucune réponse\n";
//else
//{
//    echo $selection->count(), " réponses\n";
//    $time=microtime(true);	
////    do
////    {
////        echo 
////            '<li>Nouvelle méthode :', 
////            ' réponse n° ', $selection->searchInfo('rank') ,
////            ', ref=', $selection['ref'], 
////            ', typdoc=', $selection['type'],
////            ', titre=', $selection['tit'],
////
////            "</li>\n"; 	
////    
////        //$selection['tit']='essai';
////    } while ($selection->next() && (++$nb<1000));
////    
//}
//
//echo "La base contient ", count($selection->fields()), " champs, ", $selection->fields()->count(), "\n";
//echo "Premier parcours\n";
//    foreach($selection as $rank=>$fields)
//    {
//        echo $rank, '. ';
//        echo 'accès direct au titre : ', $fields['tit'], "\n";
//        foreach($fields as $name=>$value)
//            echo $name, ' : ', $value, "\n";
//        
//        echo "//\n";
//        if (++$nb>10) break;            
//    }
//    
//    echo 'time : ', microtime(true)-$time;
//
//echo "Second parcours\n";
//    foreach($selection as $rank=>$fields)
//        echo $rank, print_r($fields, true), "\n";
//    
//    echo 'time : ', microtime(true)-$time;
//}
//
//
//echo "count=", $selection->count();
//
//die();
//// code pour balayer une notice dont on ne sait rien
//edit();
//foreach ($selection->fields() as $name=>$value) // balaye tous les champs
//{
//    if ($value) echo $name, ' : ', $value, "\n";
//    $selection[$name]='new value';
//    echo $selection->fieldInfo($name, 'controls');
//}
//save();
//
//
//// on n'accède plus jamais à un champ avec un numéro. uniquement par son nom
//// si possible, rendre les noms de champ insensibles à la casse
//// DONE implémenter un itérateur fields() sur les champs
//// DONE implémenter un itérateur sur la sélection ??
//// DONE plus de fonction fieldsCount()
//// fonction fieldInfo($fieldName, $infoName)->mixed
//
//// locker une base .???
//
//// copy($destination)
//// sort($key)
//// compact()
//// ftpTo(serveur, port, path, username, password)
//
//// date:8+,titperio:20-,titre:20+, 
//
//// Accès aux termes de l'index
//// création d'un expand set (eset)
//// création d'un result set (rset)
//
//
?>
