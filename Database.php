<?php
 
abstract class Database
{
    /**
     * @var string le type de base de donn�es en cours ('bis' ou
     * 'xapian').
     * @access protected
     */
    protected $type=null;

    
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
     * Exemple pour xapian :
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
     * son mieux pour estimer ce nmbre, mais rien ne garantit qu'il n'y en ait
     * pas en r�alit� plus ou moins que le nombre retourn�e.
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
    abstract public function next();
    

    /**
     * Retourne la valeur d'un champ
     * 
     * @param mixed $which index ou nom du champ dont la valeur sera retourn�e.
     * @return mixed la valeur du champ ou null si ce champ ne figure pas dans
     * l'enregistrement courant.
     * 
     * TODO : comment connait-on le nombre de champs ?
     * xapian : les num�ros peuvent avoir des trous... (champs supprim�)
     * non cas� : fieldname(i), fieldType(i), fieldCount...
     * 
     */
    abstract public function getField($which);
 
    
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
     * Supprime la notice en cours
     */
    abstract public function delete();
    

}

class BisDatabase extends Database
{
    protected function doCreate($database, $def, $options=null)
    {
        throw new Exception('non impl�ment�');
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
//echo "\n", 'Base cr��e. Type=', $db->getType(), "\n";
$db=Database::open('dbtest', false, 'bis');
//$db->doCreate(1,2,3);
echo "\n", 'Base ouverte. Type=', $db->getType(), "\n";
echo "count=", $db->count();
?>
