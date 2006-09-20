<?php
 
abstract class Database
{
    /**
     * @var string le type de base de données en cours ('bis' ou
     * 'xapian').
     * @access protected
     */
    protected $type=null;

    
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
     * Exemple pour xapian :
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
     * son mieux pour estimer ce nmbre, mais rien ne garantit qu'il n'y en ait
     * pas en réalité plus ou moins que le nombre retournée.
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
    abstract public function next();
    

    /**
     * Retourne la valeur d'un champ
     * 
     * @param mixed $which index ou nom du champ dont la valeur sera retournée.
     * @return mixed la valeur du champ ou null si ce champ ne figure pas dans
     * l'enregistrement courant.
     * 
     * TODO : comment connait-on le nombre de champs ?
     * xapian : les numéros peuvent avoir des trous... (champs supprimé)
     * non casé : fieldname(i), fieldType(i), fieldCount...
     * 
     */
    abstract public function getField($which);
 
    
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
     * Supprime la notice en cours
     */
    abstract public function delete();
    

}

class BisDatabase extends Database
{
    protected function doCreate($database, $def, $options=null)
    {
        throw new Exception('non implémenté');
    }
        
    protected function doOpen($database, $readOnly=true)
    {
        $bis=new COM("Bis.Engine");
        $dataset='dataset';
        if ($readOnly)
            $this->selection=$bis->openSelection($database, $dataset);
        else
            $this->selection=$bis->OpenDatabase($database, false, false)->openSelection($dataset);
        unset($bis);
        
    }
        
    public function search($equation=null, $options=null)
    {
        $selection->equation=$equation;
        // TODO: examiner les options, se positionner sur 'start'
        return $this->selection->count() != 0;
    }

    public function count($countType=0)
    {
    	return $this->selection->count;
    }

    public function searchInfo($what)
    {
    	
    }
    public function next()
    {
        $this->selection->moveNext();
    }
    public function getField($which)
    {
        return $this->selection[$which];
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
    public function delete()
    {
        $this->selection->delete();
    }
}
echo '<pre>';

//$db=Database::create('dbtest',array(),'toto');
////$db->doCreate(1,2,3);
//echo "\n", 'Base créée. Type=', $db->getType(), "\n";
$db=Database::open('dbtest', false, 'bis');
//$db->doCreate(1,2,3);
echo "\n", 'Base ouverte. Type=', $db->getType(), "\n";
echo "count=", $db->count();
?>
