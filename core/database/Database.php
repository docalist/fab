<?php

/**
 * Charge les fichiers de configuration de base de donn�es (db.yaml, db.
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
debug && Debug::log("Chargement de la configuration des bases de donn�es");
if (file_exists($path=Runtime::$fabRoot.'config' . DIRECTORY_SEPARATOR . 'db.yaml'))
    Config::load($path, 'db');
if (file_exists($path=Runtime::$root.'config' . DIRECTORY_SEPARATOR . 'db.yaml'))
    Config::load($path, 'db');

if (!empty(Runtime::$env))   // charge la config sp�cifique � l'environnement
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
 * @author 		Daniel M�nard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id$
 */

/**
 * Interface d'acc�s aux bases de donn�es.
 * 
 * Database est � la fois une classe statique offrant des m�thodes simples pour
 * cr�er ({@link create()}) ou ouvrir ({@link open()})une base de donn�es et une
 * interface pour toutes les classes descendantes.
 * 
 * Database impl�mente l'interface Iterator. Cela permet de parcourir les
 * enregistrements tr�s simplement en utilisant une boucle foreach :
 * 
 * <code>
 * foreach ($selection as $rank=>$record) 
 *     echo 'R�ponse num�ro ', $rank, ' : ref ', $record['ref'], "\n";
 * </code>
 * 
 * La m�me chose peut �tre faite dans un template en utilisant le tag <loop> :
 * 
 * <code>
 * <loop on="$selection" as="$rank,$record">
 *     R�ponse num�ro $rank : ref {$record['ref']}
 * </loop>
 * </code>
 * 
 * Database impl�mente �galement l'interface ArrayAccess. Cela permet de
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
 * en utilisant les m�thodes {@link add()}, {@link edit()} et {@link save()}
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
    
    public $record=null;
    
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
        $type=Config::get("db.$database.type", $type);
        $database=Config::get("db.$database.path", $database);
        
        // Si c'est un chemin relatif, recherche dans /data/db
        if (Utils::isRelativePath($database))
        {
            $path=Utils::searchFile($database, Runtime::$root . 'data/db');
            if ($path=='')
                throw new Exception("Impossible de trouver la base '$database'");
        }

        // Cr�e une instance de la bonne classe en fonction du type, cr�e la base et retourne l'objet obtenu
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
     * Exemple d'utilisation :
     * 
     * <code>
     * if ($selection->search('type:article', array('sort'=>'-', 'start'=>10)))
     * ... afficher les r�ponses obtenues
     * else
     * ... aucune r�ponse
     * </code>
     * 
     * 
     * @param string $equation l'�quation de recherche � ex�cuter
     * @param array $options tableau d'options support�es par le backend qui
     * indiquent la mani�re dont la recherche doit �tre faite.
     * 
     * Options disponibles :
     * 
     * <b>'sort'</b> : une chaine indiquant la mani�re dont les r�ponses doivent �tre
     * tri�es : 
     *
     *     - '%' : trier les notices par score (la meilleure en t�te)
     *     - '+' : trier par ordre croissant de num�ro de document
     *     - '-' : trier par ordre d�croissant de num�ro de document
     *     - 'xxx+' : trier sur le champ xxx, par ordre croissant
     *     - 'xxx-' : trier sur le champ xxx, par ordre d�croissant
     *     - 'xxx+%' : trier sur le champ xxx par ordre croissant, puis par
     *       pertinence.
     *     - 'xxx-%' : trier sur le champ xxx par ordre d�croissant, puis par
     *       pertinence.
     *
     * <b>'start'</b> : entier (>0) indiquant la notice sur laquelle se positionner
     * une fois la recherche effectu�e.
     * 
     * <b>'max'</b> : entier indiquant le nombre maximum de notices � retourner.
     * Permet au backend d'optimiser sa recherche en ne recherchant que les max
     * meilleures r�ponses. Indiquez -1 pour obtenir toutes les r�ponses.
     * Indiquez 0 si vous voulez seulement savoir combien il y a de r�ponses.
     * 
     * <b>'min_weight'</b> : entier (>=0), score minimum qu'une notice doit obtenir
     * pour �tre s�lectionn�e (0=pas de minimum)
     * 
     * <b>'min_percent'</b> : entier (de 0 � 100), pourcentage minimum qu'une notice
     * doit obtenir pour �tre s�lectionn�e (0=pas de minimum)
     * 
     * <b>'time_bias'</b> : reserv� pour le futur, api non fig�e dans xapian.
     * 
     * <b>'collapse'</b> : nom d'un champ utilis� pour regrouper les r�ponses
     * obtenues. Par exemple, avec un regroupement sur le champ TypDoc, on
     * obtiendra uniquement la meilleure r�ponse obtenue parmis les articles,
     * puis la meilleures obtenues parmi les ouvrages, et ainsi de suite.
     * 
     * <b>'weighting_scheme'</b> : nom et �ventuellement param�tres de l'algorithme
     * de pertinence utilis�. Les valeurs possibles sont : 
     *     - 'bool'
     *     - 'trad'
     *     - 'trad(k)
     *     - 'bm25' 
     *     - 'bm25(k1,k2,k3,b,m)'
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
     *     - 'rank' : retourne le num�ro de la r�ponse en cours (i.e. c'est
     *       la i�me r�ponse)
     *     - 'weight' : retourne le poids obtenu par la notice courante
     *     - 'percent' : identique � weight, mais retourne le poids sous forme
     *       d'un pourcentage
     *     - 'collapse' : retourne le nombre de documents similaires qui sont
     *       "cach�s" derri�re la notice courante
     *     - 'matching_terms' : retourne une cha�ne contenant les termes de
     *       l'�quation de recherche sur lesquels ce document a �t� s�lectionn�.
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
     * 
     * @access protected
     */
    abstract protected function moveNext();

    
//    /**
//     * Retourne la notice en cours
//     * 
//     * @return DatabaseRecord un objet repr�sentant la notice en cours. Cette
//     * objet peut �tre manipul� comme un tableau (utilisation dans une
//     * boucle foreach, lecture/modification de la valeur d'un champ en
//     * utilisant les crochets, utilisation de count pour conna�tre le nombre de
//     * champs dans la base...)
//     */
//    abstract public function fields();


//    /**
//     * Retourne la valeur d'un champ
//     * 
//     * Cette fonction n'est pas destin� � �tre appell�e par l'utilisatateur, 
//     * mais par les m�thodes qui impl�mentent l'interface ArrayAccess.
//     * 
//     * @access protected
//     * 
//     * @param mixed $which index ou nom du champ dont la valeur sera retourn�e.
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
//     * Cette fonction n'est pas destin� � �tre appell�e par l'utilisatateur, 
//     * mais par les m�thodes qui impl�mentent l'interface ArrayAccess.
//     * 
//     * @access protected
//     * 
//     * @param mixed $which index ou nom du champ dont la valeur sera modifi�e.
//     * 
//     * @return mixed la nouvelle valeur du champ ou null pour supprimer ce
//     * champ de la notice en cours.
//     */
//    abstract protected function setField($offset, $value);

    
    /**
     * Initialise la cr�ation d'un nouvel enregistrement
     * 
     * L'enregistrement ne sera effectivement cr�� que lorsque {@link update()}
     * sera appell�.
     */
    abstract public function addRecord();
    

    /**
     * Passe la notice en cours en mode �dition.
     * 
     * L'enregistrement  ne sera effectivement cr�� que lorsque {@link update}
     * sera appell�.
     *  
     */
    abstract public function editRecord();

    
    /**
     * Enregistre les modifications apport�es � une notice apr�s un appel �
     * {@link add()} ou � {@link edit()}
     */
    abstract public function saveRecord();
    

    /**
     * Annule l'op�ration d'ajout ou de modification de notice en cours
     */
    abstract public function cancelUpdate();


    /**
     * Supprime la notice en cours
     */
    abstract public function deleteRecord();


    /* D�but de l'interface ArrayAccess */

    /* En fait, on impl�mente pas r�ellement ArrayAccess, on se contente
     * de tout d�l�guer � record ( de type DatabaseRecord) qui lui impl�mente
     * r�ellement l'interface.
     * Comme il n'existe pas, dans SPL, d'interface ArrayAccessDelegate, on
     * est oblig� de le faire nous-m�me 
     */
     
    /**
     * Modifie la valeur d'un champ
     * 
     * Il s'agit d'une des m�thodes de l'interface ArrayAccess qui permet de
     * manipuler les champs de la notice en cours comme s'il s'agissait d'un
     * tableau.
     * 
     * Exemple : 
     * <code>
     * $selection['titre']='nouveau titre';
     * </code>
     * 
     * @param mixed $offset nom du champ � modifier
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
     * Il s'agit d'une des m�thodes de l'interface ArrayAccess qui permet de
     * manipuler les champs de la notice en cours comme s'il s'agissait d'un
     * tableau.
     * 
     * Exemple : echo $selection['titre'];
     * 
     * @param mixed $offset nom du champ � retourner
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
     * Il s'agit d'une des m�thodes de l'interface ArrayAccess qui permet de
     * manipuler les champs de la notice en cours comme s'il s'agissait d'un
     * tableau.
     * 
     * Supprimer un champ de la notice en cours revient � lui affecter la
     * valeur 'null'.
     * 
     * Exemple : 
     * <code>
     * unset($selection ['titre']);
     * </code>
     * (�quivalent � $selection['titre']=null;)
     * 
     * @param mixed $offset nom ou num�ro du champ � supprimer
     */
    public function offsetUnset($offset)
    {
        $this->record->offsetUnset($offset);
//        $this->setField($offset, null);
    }


    /**
     * Teste si un champ existe dans la notice en cours
     * 
     * Il s'agit d'une des m�thodes de l'interface ArrayAccess qui permet de
     * manipuler les champs de la notice en cours comme s'il s'agissait d'un
     * tableau.
     * 
     * Exemple :
     * <code>
     * if (isset($selection['titre']) echo 'existe';
     * </code>
     * 
     * @param mixed $offset nom ou num�ro du champ � tester
     * 
     * @return boolean true si le champ existe dans la notice en cours et � une
     * valeur non-nulle, faux sinon.
     */
    public function offsetExists($offset)
    {
        return $this->record->offsetExists($offset);
//        return ! is_null($this->getField($offset));
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
//        echo "Appel de Database::rewind()<br />";
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
//        echo "Appel de Database::current()<br />";
        return $this->record;
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
//        echo "Appel de Database::key()<br />";
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
//        echo "Appel de Database::next()<br />";
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
//        echo "Appel de Database::valid() result=",((! $this->eof)?"true":"false"),"<br />";
        return ! $this->eof;
    }
    
    /* Fin de l'interface Iterator */
    
    
    /**
     * Chercher/Remplacer � partir d'une exp r�g sur l'enregistrement en cours d'une base de donn�es ouverte 
     * (peut �tre appel� dans une boucle sur une s�lection par exemple)
     * 
     * @param array fields la liste des champs sur lesquels on effectue le chercher/remplacer
     * @param string $pattern le pattern � utiliser pour l'expression r�guli�re de recherche
     * @param string $replace la cha�ne de remplacement pour les occurences trouv�es
     * @param bool $caseSensitive indique si la recherche est sensible � la casse
     * 
     * @return false s'il y a une erreur (pattern de recherche mal form�) et true sinon
     */
    public function pregReplace($fields, $pattern, $replace, $caseSensitive = true)
    {
        // (une partie de cette v�rification est faite plus bas et peut-�tre recopi�e)
        
        if ($pattern === null || trim($pattern) == '')
            return false;
        else
            $pattern = trim($pattern);
        
        // v�rifie que $pattern contient bien les deux d�limiteurs
        $delimiter = $pattern[0];  
        $end = strpos($pattern, $delimiter, 1); // position du d�limiteur de fin de pattern de recherche
        if ($end === false)     // pas de d�limiteur de fin ou alors, probl�me avec le d�limiteur de d�but
            return false;
        
        if (! $caseSensitive)
        {            
            if ($end == strlen($pattern)-1 || strpos($pattern, 'i', $end) === false)
                $pattern = $pattern . 'i';  // sp�cifie une recherche insensible � la casse
        }
        
        // boucle sur les champs pour effectuer le remplacement �ventuel
        foreach($this->record as $field => $value)
        {
            if (in_array($field, $fields))
                $this->record[$field] = preg_replace($pattern, $replace, $value);    
        } 
        
        return true;
            
    }
    
    
    /**
     * Chercher/Remplacer � partir d'une cha�ne de caract�res sur l'enregistrement en cours d'une base de donn�es ouverte 
     * (peut �tre appel� dans une boucle sur une s�lection par exemple)
     * 
     * @param array fields la liste des champs sur lesquels on effectue le chercher/remplacer
     * @param string $search la cha�ne de caract�re de recherche
     * @param string $replace la cha�ne de remplacement pour les occurences trouv�es
     * @param bool $caseSensitive indique si la recherche est (true) ou non (false) sensible � la case
     * @param bool $wholeWord indique si on recherche uniquement le(s) mot(s) entier(s) correspondant(s)
     * � search (true) dans l'enregistrement en cours
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
                // Exemple : dans "l'ensemble", "ensemble" est bien un mot mais il n'est pas remplac�
                // si on s�lectionne "Mots entiers"
                // => Il faudra s�rement utiliser des expressions r�guli�res si $wholeWord vaut true
                
                if ($wholeWord)
                    $value = ' ' . $value . ' ';    // pr�-traitement : ajoute des espaces pour simplifier la suite
                
                if ($caseSensitive)
                    $this->record[$field] = str_replace($search, $replace, $value);
                else
                    $this->record[$field] = str_replace($search, $replace, strtolower($value));
                
                if($wholeWord)      // post-traitement : supprime les 2 espaces ajout�s en pr�-traitement       
                    $this->record[$field] = substr($this->record[$field], 1, strlen($this->record[$field])-2);
            }    
        } 
        
        return true;
    }
    
    /**
     * Chercher/Remplacer les champs vides de l'enregistrement en cours d'une base de donn�es ouverte 
     * (peut �tre appel� dans une boucle sur une s�lection par exemple)
     * 
     * @param array fields la liste des champs sur lesquels on effectue le chercher/remplacer
     * @param string $replace la cha�ne de remplacement pour les champs vides trouv�s
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
 * Repr�sente un enregistrement de la base
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
        $this->database->offsetSet($offset, $value);
    }

    public function offsetGet($offset)
    {
        // normallement, il faudrait convertir le variant en zval php
        // en fonction du type du variant
        // Dans la pratique, on utilise quasiment jamais BIS pour autre
        // chose que des chaines (si ce n'est REF). Ca me semble sans
        // risque de caster syst�matiquement vers une chaine
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
//    echo "Aucune r�ponse\n";
//else
//{
//    echo $selection->count(), " r�ponses\n";
//    $time=microtime(true);	
////    do
////    {
////        echo 
////            '<li>Nouvelle m�thode :', 
////            ' r�ponse n� ', $selection->searchInfo('rank') ,
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
//        echo 'acc�s direct au titre : ', $fields['tit'], "\n";
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
//// on n'acc�de plus jamais � un champ avec un num�ro. uniquement par son nom
//// si possible, rendre les noms de champ insensibles � la casse
//// DONE impl�menter un it�rateur fields() sur les champs
//// DONE impl�menter un it�rateur sur la s�lection ??
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
//// Acc�s aux termes de l'index
//// cr�ation d'un expand set (eset)
//// cr�ation d'un result set (rset)
//
//
?>
