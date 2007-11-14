<?php
/**
 * @package     fab
 * @subpackage  database
 * @author      Daniel M�nard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id
 */

/**
 * La structure d'une base de donn�es fab.
 * 
 * Cette classe offre des fonctions permettant de charger, de valider et de 
 * sauvegarder la structure d'une base de donn�es en format XML et JSON.
 * 
 * @package     fab
 * @subpackage  database
 */
class DatabaseStructure
{
    /**
     * Les types de champ autoris�s
     *
     */
    const
        FIELD_INT    = 1,
        FIELD_AUTONUMBER=2,
        FIELD_TEXT=3,
        FIELD_BOOL=4;

    /**
     * Ce tableau d�crit les propri�t�s d'une structure de base de donn�es.
     * 
     * @var array
     */
    private static $dtd=array       // NUPLM = non utilis� pour le moment 
    (
        'database'=>array
        (
                                        // PROPRIETES GENERALES DE LA BASE
                                        
            'label'=>'',                // Un libell� court d�crivant la base
            'version'=>'1.0',           // NUPLM Version de fab qui a cr�� la base
            'description'=>'',          // Description, notes, historique des modifs...
            'stopwords'=>'',            // Liste par d�faut des mots-vides � ignorer lors de l'indexation
            'indexstopwords'=>false,    // faut-il indexer les mots vides ?
            'creation'=>'',           // Date de cr�ation de la structure
            'lastupdate'=>'',         // Date de derni�re modification de la structure
            '_lastid'=>array
            (
                'field'=>0,
                'index'=>0,
                'alias'=>0,
                'lookuptable'=>0,
                'sortkey'=>0
            ),
            
            'fields'=>array             // FIELDS : LISTE DES CHAMPS DE LA BASE
            (
                'field'=>array          
                (
                    '_id'=>0,            // Identifiant (num�ro unique) du champ
                    'name'=>'',             // Nom du champ, d'autres noms peuvent �tre d�finis via des alias
                    'type'=>'text',         // Type du champ (juste � titre d'information, non utilis� pour l'instant)
                    '_type'=>0,          // Traduction de la propri�t� type en entier
                    'label'=>'',            // Libell� du champ
                    'description'=>'',      // Description
                    'defaultstopwords'=>true, // Utiliser les mots-vides de la base
                    'stopwords'=>'',        // Liste sp�cifique de mots-vides � appliquer � ce champ
                )
            ),
            
            /*
                Combinatoire stopwords/defaultstopwords : 
                - lorsque defaultstopwords est � true, les mots indiqu�s dans 
                  stopwords viennent en plus de ceux indiqu�s dans db.stopwords.
                - lorsque defaultstopwords est � false, les mots indiqu�s
                  dans stopwords remplacent ceux de la base

                Liste finale des mots vides pour un champ =
                    - stopwords=""
                        - defaultstopwords=false    => ""
                        - defaultstopwords=true     => db.stopwords 
                    - stopwords="x y z"
                        - defaultstopwords=false    => "x y z"
                        - defaultstopwords=true     => db.stopwords . "x y z"
             */
            
            'indices'=>array            // INDICES : LISTE DES INDEX  
            (
                'index'=>array
                (
                    '_id'=>0,            // Identifiant (num�ro unique) de l'index
                    'name'=>'',             // Nom de l'index
                    'label'=>'',            // Libell� de l'index
                    'description'=>'',      // Description de l'index
                    'fields'=>array         // La liste des champs qui alimentent cet index
                    (
                        'field'=> array
                        (               
                            'name'=>'',         // Nom du champ
                            'words'=>false,     // Indexer les mots
                            'phrases'=>false,   // Indexer les phrases
                            'values'=>false,    // Indexer les valeurs
                            'count'=>false,     // Compter le nombre de valeurs (empty, has1, has2...)
                            'global'=>false,    // Prendre en compte cet index dans l'index 'tous champs'
                            'start'=>'',      // Position ou chaine indiquant le d�but du texte � indexer
                            'end'=>'',        // Position ou chain indquant la fin du texte � indexer
                            'weight'=>1         // Poids des tokens ajout�s � cet index
                        )
                    )
                )
            ),
    
            'lookuptables'=>array            // LOOKUpTABLES : LISTE DES TABLES DE LOOKUP
            (
                'lookuptable'=>array
                (
                    '_id'=>0,            // Identifiant (num�ro unique) de la table
                    'name'=>'',             // Nom de la table
                    'label'=>'',            // Libell� de l'index
                    'description'=>'',      // Description de l'index
                    'fields'=>array         // La liste des champs qui alimentent cette table
                    (
                        'field'=>array
                        (
                            'name'=>'',         // Nom du champ
                            'start'=>'',      // Position de d�but ou chaine d�limitant le d�but de la valeur � ajouter � la table
                            'end'=>''         // Longueur ou chaine d�limitant la fin de la valeur � ajouter � la table
                        )
                    )
                )
            ),
    
            'aliases'=>array            // ALIASES : LISTE DES ALIAS
            (
                'alias'=>array
                (
                    '_id'=>0,            // Identifiant (num�ro unique) de l'alias (non utilis�)
                    'name'=>'',             // Nom de l'alias
                    'label'=>'',            // Libell� de l'index
                    'description'=>'',      // Description de l'index
                    'indices'=>array        // La liste des index qui composent cet alias
                    (
                        'index'=>array
                        (
                            'name'=>'',         // Nom de l'index
                        )
                    )
                )
            ),
            
            'sortkeys'=>array           // SORTKEYS : LISTE DES CLES DE TRI
            (
                'sortkey'=>array
                (
                    '_id'=>0,            // Identifiant (num�ro unique) de la cl� de tri
                    'name'=>'',             // Nom de la cl� de tri
                    'label'=>'',            // Libell� de l'index
                    'description'=>'',      // Description de l'index
                    'fields'=>array         // La liste des champs qui composent cette cl� de tri
                    (
                        'field'=>array
                        (
                            'name'=>'',         // Nom du champ
                            'start'=>'',      // Position de d�but ou chaine d�limitant le d�but de la valeur � ajouter � la cl�
                            'end'=>'',        // Longueur ou chaine d�limitant la fin de la valeur � ajouter � la cl�
                            'length'=>0      // Longueur totale de la partie de cl� (tronqu�e ou padd�e � cette taille)
                        )
                    )
                )
            )
        )
    );
    

    /**
     * Constructeur. Cr�e une nouvelle structure de base de donn�es � partir 
     * de l'argument pass� en param�tre.
     * 
     * L'argument est optionnel. Si vous n'indiquez rien ou si vous passez 
     * 'null', une nouvelle structure de base de donn�es (vide) sera cr��e.
     * 
     * Sinon, la structure de la base de donn�es va �tre charg�e � partir de
     * l'argument pass� en param�tre. Il peut s'agir :
     * <li>d'un tableau ou d'un objet php d�crivant la base</li>
     * <li>d'une chaine de caract�res contenant le source xml d�crivant la base</li>
     * <li>d'une chaine de caract�res contenant le source JSON d�crivant la base</li>
     *
     * @param mixed $def 
     * @throws DatabaseStructureException si le type de l'argument pass� en 
     * param�tre ne peut pas �tre d�termin� ou si la d�finition a des erreurs 
     * fatales (par exemple un fichier xml mal form�)
     */
    public function __construct($def=null)
    {
        // Faut-il ajouter les propri�t�s par d�faut ? (oui pour tous sauf xml qui le fait d�j�)
        $addDefaultsProps=true;
        
        // Une structure vide
        if (is_null($def))
        {
            $this->label='Nouvelle structure de base de donn�es';
            $this->creation=date('Y/m/d H:i:s');
        }
        
        // Une chaine de caract�res contenant du xml ou du JSON
        elseif (is_string($def))
        {
            switch (substr(ltrim($def), 0, 1))
            {
                case '<': // du xml
                    $this->fromXml($def);
                    $addDefaultsProps=false;
                    break;
                    
                case '{': // du json
                    $this->fromJson($def);
                    break;
                    
                default:
                    throw new DatabaseStructureException('Impossible de d�terminer le type de la structure de base de donn�es pass�e en param�tre');
            }
        }

        // Ajoute toutes les propri�t�s qui ne sont pas d�finies avec leur valeur par d�faut
        if ($addDefaultsProps) 
        {
            $this->addDefaultProperties();
        }
    }


    /**
     * Ajoute les propri�t�s par d�faut � tous les objets de la hi�rarchie
     *
     */
    public function addDefaultProperties()
    {
        self::defaults($this, self::$dtd['database']);
    }
    
    
    /**
     * Met � jour la date de derni�re modification (lastupdate) de la structure
     *
     * @param unknown_type $timestamp
     */
    public function setLastUpdate($timestamp=null)
    {
        if (is_null($timestamp))
            $this->lastupdate=date('Y/m/d H:i:s');
        else
            $this->lastupdate=date('Y/m/d H:i:s', $timestamp);
    }


    /**
     * Cr�e la structure de la base de donn�es � partir d'un source xml
     *
     * @param string $xmlSource
     * @return StdClass
     */
    private function fromXml($xmlSource)
    {
        // Cr�e un document XML
        $xml=new domDocument();
        $xml->preserveWhiteSpace=false;
    
        // gestion des erreurs : voir comment 1 � http://fr.php.net/manual/en/function.dom-domdocument-loadxml.php
        libxml_clear_errors(); // >PHP5.1
        libxml_use_internal_errors(true);// >PHP5.1
    
        // Charge le document
        if (! $xml->loadXML($xmlSource))
        {
            $h="Structure de base incorrecte, ce n'est pas un fichier xml valide :<br />\n"; 
            foreach (libxml_get_errors() as $error)
                $h.= "- ligne $error->line : $error->message<br />\n";
            libxml_clear_errors(); // lib�re la m�moire utilis�e par les erreurs
            throw new DatabaseStructureXmlException($h);
        }

        // Convertit la structure xml en objet
        $o=self::xmlToObject($xml->documentElement, self::$dtd);
        
        // Initialise nos propri�t�s � partir de l'objet obtenu
        foreach(get_object_vars($o) as $prop=>$value)
        {
            $this->$prop=$value;
        }
    }

    
    /**
     * Fonction utilitaire utilis�e par {@link fromXml()} pour convertir un 
     * source xml en objet.
     *
     * @param DOMNode $node le noeud xml � convertir
     * @param array $dtd un tableau indiquant les noeuds et attributs autoris�s
     * @return StdClass
     * @throws DatabaseStructureXmlNodeException si le source xml contient des 
     * attributs ou des tags non autoris�s
     */
    private static function xmlToObject(DOMNode $node, array $dtd)
    {
        // V�rifie que le nom du noeud correspond au tag attendu tel qu'indiqu� par le dtd
        if (count($dtd)>1)
            throw new LogicException('DTD invalide : le tableau doit contenir un seul �l�ment');
        reset($dtd);
        $tag=key($dtd);
        if ($node->tagName !== $tag)
            throw new DatabaseStructureXmlNodeException($node, "�l�ment non autoris�, '$tag' attendu");
        $dtd=array_pop($dtd);
                    
        // Cr�e un nouvel objet contenant les propri�t�s par d�faut indiqu�es dans le dtd
        $result=self::defaults(new StdClass, $dtd);

        // Les attributs du tag sont des propri�t�s de l'objet
        if ($node->hasAttributes())
        {
            foreach ($node->attributes as $attribute)
            {
                // Le nom de l'attribut va devenir le nom de la propri�t�
                $name=$attribute->nodeName;
                
                // V�rifie que c'est un �l�ment autoris�
                if (! array_key_exists($name, $dtd))
                    throw new DatabaseStructureXmlNodeException($node, "l'attribut '$name' n'est pas autoris�");
                    
                // Si la propri�t� est un objet, elle ne peut pas �tre d�finie sous forme d'attribut
                if (is_array($dtd[$name]))
                    throw new DatabaseStructureXmlNodeException($node, "'$name' n'est pas autoris� comme attribut, seulement comme �l�ment fils");
                    
                // D�finit la propri�t�
                $result->$name=self::xmlToValue(utf8_decode($attribute->nodeValue), $attribute, $dtd[$name]);
            }
        }
        
        // Les noeuds fils du tag sont �galement des propri�ts de l'objet
        foreach ($node->childNodes as $child)
        {
            switch ($child->nodeType)
            {
                case XML_ELEMENT_NODE:
                    // Le nom de l'�l�ment va devenir le nom de la propri�t�
                    $name=$child->tagName;
                    
                    // V�rifie que c'est un �l�ment autoris�
                    if (! array_key_exists($name, $dtd))
                        throw new DatabaseStructureXmlNodeException($node, "l'�l�ment '$name' n'est pas autoris�");
                        
                    // V�rifie qu'on n'a pas � la fois un attribut et un �l�ment de m�me nom (<database label="xxx"><label>yyy...)
                    if ($node->hasAttribute($name))
                        throw new DatabaseStructureXmlNodeException($node, "'$name' appara�t � la fois comme attribut et comme �l�ment");

                    // Cas d'une propri�t� simple (scalaire)
                    if (! is_array($dtd[$name]))
                    {
                        $result->$name=self::xmlToValue(utf8_decode($child->nodeValue), $child, $dtd[$name]); // si plusieurs fois le m�me tag, c'est le dernier qui gagne
                    }
                    
                    // Cas d'un tableau
                    else
                    {
                        // Une collection : le tableau a un seul �l�ment qui est lui m�me un tableau d�crivant les noeuds
                        if (count($dtd[$name])===1 && is_array(reset($dtd[$name])))
                        {
                            foreach($child->childNodes as $child)
                                array_push($result->$name, self::xmlToObject($child, $dtd[$name]));
                        }
                        
                        // Un objet (exemple : _lastid) : plusieurs propri�t�s ou une seule mais pas un tableau 
                        else
                        {
                            $result->$name=self::xmlToObject($child, array($name=>$dtd[$name]));
                        }
                    }
                    break;

                // Types de noeud autoris�s mais ignor�s
                case XML_COMMENT_NODE:
                    break;
                    
                // Types de noeud interdits
                default:
                    throw new DatabaseStructureXmlNodeException($node, "les noeuds de type '".$child->nodeName . "' ne sont pas autoris�s");
            }
        }
        return $result;
    }


    /**
     * Fonction utilitaire utilis�e par {@link xmlToObject()} pour convertir la
     * valeur d'un attribut ou le contenu d'un tag.
     * 
     * Pour les bool�ens, la fonction reconnait les valeurs 'true' ou 'false'.
     * Pour les autres types scalaires, la fonction encode les caract�res '<', 
     * '>', '&' et '"' par l'entit� xml correspondante.
     *
     * @param scalar $value
     * @return string
     */
    private static function xmlToValue($xml, DOMNode $node, $dtdValue)
    {
        if (is_bool($dtdValue)) 
        {
            if($xml==='true') return true;
            if($xml==='false') return false;
            throw new DatabaseStructureXmlNodeException($node, 'bool�en attendu');
        }
        
        if (is_int($dtdValue))
        {
            if (! ctype_digit($xml))
                throw new DatabaseStructureXmlNodeException($node, 'entier attendu');
            return (int) $xml;
        }
        return $xml;
    }
    

    /**
     * Retourne la version xml de la structure de base de donn�es en cours.
     * 
     * @return string
     */    
    public function toXml()
    {
        ob_start();
        echo '<?xml version="1.0" encoding="iso-8859-1"?>', "\n";
        self::nodeToXml(self::$dtd, $this);
        return ob_get_clean();
    }

    
    /**
     * Fonction utilitaire utilis�e par {@link toXml()} pour g�n�rer la version
     * Xml de la structure de la base.
     *
     * @param array $dtd le dtd d�crivant la structure de la base 
     * @param string $tag le nom du tag xml � g�n�rer
     * @param StdClass $object l'objet � g�n�rer
     * @param string $indent l'indentation en cours
     * @return string le source xml g�n�r�
     */
    private static function nodeToXml($dtd, $object, $indent='')
    {
        // Extrait du DTD le nom du tag � g�n�rer
        if (count($dtd)>1)
            throw new LogicException('DTD invalide : le tableau doit contenir un seul �l�ment');
        reset($dtd);
        $tag=key($dtd);
        $dtd=array_pop($dtd);
        
        $attr=array();
        $simpleNodes=array();
        $complexNodes=array();
        $objects=array();
        
        // Parcourt toutes les propri�t�s pour les classer
        foreach($object as $prop=>$value)
        {
            // La propri�t� a la valeur par d�faut indiqu�e dans le DTD : on l'ignore
            if(array_key_exists($prop,$dtd) && $value === $dtd[$prop])
                continue;
            
            // Valeurs scalaires (entiers, chaines, bool�ens...)
            if (is_scalar($value) || is_null($value))
            {
                $value=(string)$value;
                
                // Si la valeur est courte, ce sera un attribut
                if (strlen($value)<80) 
                    $attr[]=$prop;
                     
                // sinon, ce sera un �l�ment
                else 
                    $simpleNodes[]=$prop;
            }
            
            // Tableau
            else
            {
                if (count($dtd[$prop])===1 && is_array(reset($dtd[$prop])))
                {
                    if (count($value))  // Ignore les tableaux vides
                        $complexNodes[]=$prop;
                }
                else
                {
                    $objects[]=$prop;
                }
            }
        }
        
        if (count($attr)===0 && count($simpleNodes)===0 && count($complexNodes)===0 & count($objects)===0)
            return;
            
        // Ecrit le d�but du tag et ses attributs
        echo $indent, '<', $tag;
        foreach($attr as $prop)
            echo ' ', $prop, '="', self::valueToXml($object->$prop), '"';
            
        // Si le tag ne contient pas de noeuds fils, termin�
        if (count($simpleNodes)===0 && count($complexNodes)===0)
        {
            echo " />\n";
            return;
        }
        
        // Ferme le tag d'ouverture 
        echo ">\n";
        
        // Ecrit en premier les noeuds simples qui n'ont pas de fils
        foreach($simpleNodes as $prop)
            echo $indent, '    <', $prop, '>', self::valueToXml($object->$prop), '</', $prop, '>', "\n";

        // Puis toutes les propri�t�s 'objet'
        foreach($objects as $prop)
        {
            self::nodeToXml(array($prop=>$dtd[$prop]), $object->$prop, $indent.'    ');
        } 
            
        // Puis tous les nouds qui ont des fils
        foreach($complexNodes as $prop)
        {
            echo $indent, '    <', $prop, ">\n";
            foreach($object->$prop as $i=>$item)
            {
                self::nodeToXml($dtd[$prop], $item, $indent.'        ');
            }
            echo $indent, '    </', $prop, ">\n";
        } 
        
        // Ecrit le tag de fermeture
        echo $indent, '</', $tag, ">\n";
    }

    
    /**
     * Fonction utilitaire utilis�e par {@link nodeToXml()} pour �crire la
     * valeur d'un attribut ou le contenu d'un tag.
     * 
     * Pour les bool�ens, la fonction g�n�re les valeurs 'true' ou 'false'.
     * Pour les autres types scalaires, la fonction encode les caract�res '<', 
     * '>', '&' et '"' par l'entit� xml correspondante.
     *
     * @param scalar $value
     * @return string
     */
    private static function valueToXml($value)
    {

        if (is_bool($value)) 
            return $value ? 'true' : 'false';
        return htmlspecialchars($value, ENT_COMPAT);
    }


    /**
     * Initialise la structure de la base de donn�es � partir d'un source JSON.
     * 
     * La chaine pass�e en param�tre doit �tre encod�e en UTF8. Elle est 
     * d�cod�e de mani�re � ce que la structure de base de donn�es obtenue
     * soit encod�e en ISO-8859-1.
     *
     * @param string $xmlSource
     * @return StdClass
     */
    private function fromJson($json)
    {
        // Cr�e un objet � partir de la chaine JSON
        $o=Utils::utf8Decode(json_decode($json, false));
        
        // Initialise nos propri�t�s � partir de l'objet obtenu
        foreach(get_object_vars($o) as $prop=>$value)
        {
            $this->$prop=$value;
        }
    }
    

    /**
     * Retourne la version JSON de la structure de base de donn�es en cours.
     * 
     * Remarque : la chaine obtenu est encod�e en UTF-8.
     * 
     * @return string
     */    
    public function toJson()
    {
        // Si notre structure est compil�e, les cl�s de tous les tableaux
        // sont des chaines et non plus des entiers. Or, la fonction json_encode
        // de php traite ce cas en g�n�rant alors un objet et non plus un 
        // tableau (je pense que c'est conforme � la sp�cification JSON dans la
        // mesure o� on ne peut pas, en json, sp�cifier les cl�s du tableau).
        // Le probl�me, c'est que l'�diteur de structure ne sait pas g�rer �a :
        // il veut absolument un tableau.
        // Pour contourner le probl�me, on utilise notre propre version de 
        // json_encode qui ignore les cl�s des tableaux (ie fait l'inverse de
        // compileArrays)
        
        ob_start();
        self::jsonEncode($this);
        return ob_get_clean();
        
        // version json originale
        // return json_encode(Utils::utf8Encode($this));
    }
    
    /**
     * Fonction utilitaire utilis�e par {@link toJson()}
     *
     * @param mixed $o
     */
    private static function jsonEncode($o)
    {
        if (is_null($o) || is_scalar($o)) 
        {
            echo json_encode(is_string($o) ? utf8_encode($o) : $o);
            return;
        }
        if (is_object($o))
        {
            echo '{';
            $comma=null;
            foreach($o as $prop=>$value)
            {
                echo $comma, json_encode(utf8_encode($prop)), ':';
                self::jsonEncode($value);
                $comma=',';
            }    
            echo '}';
            return;
        }
        if (is_array($o))
        {
            echo '[';
            $comma=null;
            foreach($o as $value) // ignore les cl�s
            {
                echo $comma;
                self::jsonEncode($value);
                $comma=',';
            }    
            echo ']';
            return;
        }
        throw new LogicException(__CLASS__ . '::'.__METHOD__.' : type non g�r� : '.var_export($o,true));
    }

    private static function boolean($x)
    {
        if (is_string($x))
        { 
            switch(strtolower(trim($x)))
            {
                case 'true':
                    return true; 
                default:
                    return false;
            }
        }
        return (bool) $x;
    }
    
    /**
     * Redresse et valide la structure de la base de donn�es, d�tecte les 
     * �ventuelles erreurs.
     * 
     * @return (true|array) retourne 'true' si aucune erreur n'a �t� d�tect�e
     * dans la structure de la base de donn�es. Retourne un tableau contenant
     * un message pour chacune des erreurs rencontr�es sinon.
     */
    public function validate()
    {
        $errors=array();
        
        // Tri et nettoyage des mots-vides
        self::stopwords($this->stopwords);
        $this->indexstopwords=self::boolean($this->indexstopwords);
        
        // V�rifie qu'on a au moins un champ
//        if (count($this->fields)===0)
//            $errors[]="Structure de base incorrecte, aucun champ n'a �t� d�fini";
    
        // Tableau utilis� pour dresser la liste des champs/index/alias utilis�s
        $fields=array();
        $indices=array();
        $lookuptables=array();
        $aliases=array();
        $sortkeys=array();
        
        // V�rifie la liste des champs
        foreach($this->fields as $i=>&$field)
        {
            // V�rifie que le champ a un nom correct, sans caract�res interdits
            $name=trim(Utils::ConvertString($field->name, 'alphanum'));
            if ($name==='' || strpos($name, ' ')!==false)
                $errors[]="Nom incorrect pour le champ #$i : '$field->name'";
            
            // V�rifie le type du champ
            switch($field->type=strtolower(trim($field->type)))
            {
                case 'autonumber':    
                case 'bool': 
                case 'int':   
                case 'text':  
                    break;
                default:
                    $errors[]="Type incorrect pour le champ #$i";
            }
            
            // V�rifie que le nom du champ est unique
            if (isset($fields[$name]))
                $errors[]="Les champs #$i et #$fields[$name] ont le m�me nom";
            $fields[$name]=$i;
            
            // Tri et nettoie les mots-vides
            self::stopwords($field->stopwords);
            
            // V�rifie la propri�t� defaultstopwords
            $field->defaultstopwords=self::boolean($field->defaultstopwords);
            
        }
        unset($field);
        

        // V�rifie la liste des index
        foreach($this->indices as $i=>&$index)
        {
            // V�rifie que l'index a un nom
            $name=trim(Utils::ConvertString($index->name, 'alphanum'));
            if ($name==='' || strpos($name, ' ')!==false)
                $errors[]="Nom incorrect pour l'index #~$i : '$index->name'";
                
            // V�rifie que le nom de l'index est unique
            if (isset($indices[$name]))
                $errors[]="Les index #$i et #$indices[$name] ont le m�me nom";
            $indices[$name]=$i;
            
            // V�rifie que l'index a au moins un champ
            if (count($index->fields)===0)
                $errors[]="Aucun champ n'a �t� indiqu� pour l'index #$i ($index->name)";
            else foreach ($index->fields as $j=>&$field)
            {
                // V�rifie que le champ indiqu� existe
                $name=trim(Utils::ConvertString($field->name, 'alphanum'));
                if (!isset($fields[$name]))
                    $errors[]="Champ inconnu dans l'index #$i : '$name'";
                    
                // V�rifie les propri�t�s bool�enne words/phrases/values/count et global
                $field->words=self::boolean($field->words);
                $field->phrases=self::boolean($field->phrases);
                if ($field->phrases) $field->words=true;
                $field->values=self::boolean($field->values);
                $field->count=self::boolean($field->count);
                $field->global=self::boolean($field->global);
                    
                // V�rifie qu'au moins un des types d'indexation est s�lectionn�
                if (! ($field->words || $field->phrases || $field->values || $field->count))
                    $errors[]="Le champ #$j ne sert � rien dans l'index #$i : aucun type d'indexation indiqu�";
                    
                // Poids du champ
                $field->weight=trim($field->weight);
                if ($field->weight==='') $field->weight=1;
                if ((! is_int($field->weight) && !ctype_digit($field->weight)) || (1>$field->weight=(int)$field->weight))
                    $errors[]="Propri�t� weight incorrecte pour le champ #$j de l'index #$i (entier sup�rieur � z�ro attendu)";
                    
                // Ajuste start et end
                $this->startEnd($field, $errors, "Champ #$j de l'index #$i : ");
            }
            unset($field);
        }
        unset($index);


        // V�rifie la liste des tables des entr�es
        foreach($this->lookuptables as $i=>&$lookuptable)
        {
            // V�rifie que la table a un nom
            $name=trim(Utils::ConvertString($lookuptable->name, 'alphanum'));
            if ($name==='' || strpos($name, ' ')!==false)
                $errors[]="Nom incorrect pour la table des entr�es #~$i : '$index->name'";
                
            // V�rifie que le nom de la table est unique
            if (isset($lookuptables[$name]))
                $errors[]="Les tables d'entr�es #$i et #$lookuptables[$name] ont le m�me nom";
            $lookuptables[$name]=$i;
                
            // V�rifie que la table a au moins un champ
            if (count($lookuptable->fields)===0)
                $errors[]="Aucun champ n'a �t� indiqu� pour la table des entr�es #$i ($lookuptable->name)";
            else foreach ($lookuptable->fields as $j=>&$field)
            {
                // V�rifie que le champ indiqu� existe
                $name=trim(Utils::ConvertString($field->name, 'alphanum'));
                if (!isset($fields[$name]))
                    $errors[]="Champ inconnu dans la table des entr�es #$i : '$name'";

                // Ajuste start et end
                $this->startEnd($field, $errors, "Champ #$j de la table des entr�es #$i : ");
            }
            unset($field);
        }
        unset($lookuptable);    

        
        // V�rifie la liste des alias
        foreach($this->aliases as $i=>& $alias)
        {
            // V�rifie que l'alias a un nom
            $name=trim(Utils::ConvertString($alias->name, 'alphanum'));
            if ($name==='' || strpos($name, ' ')!==false)
                $errors[]="Nom incorrect pour l'alias #$i";

            // V�rifie que ce nom est unique
            if (isset($indices[$name]))
                $errors[]="Impossible de d�finir l'alias '$name' : ce nom est d�j� utilis� pour d�signer un index de base";
            if (isset($aliases[$name]))
                $errors[]="Les alias #$i et #$aliases[$name] ont le m�me nom";
            $aliases[$name]=$i;
            
            // V�rifie que l'alias a au moins un index
            if (count($alias->indices)===0)
                $errors[]="Aucun index n'a �t� indiqu� pour l'alias #$i ($alias->name)";
            else foreach ($alias->indices as $j=>&$index)
            {
                // V�rifie que l'index indiqu� existe
                $name=trim(Utils::ConvertString($index->name, 'alphanum'));
                if (!isset($indices[$name]))
                    $errors[]="Index '$name' inconnu dans l'alias #$i ($alias->name)";
            }
            unset($index);
        }
        unset($alias);

        
        // V�rifie la liste des cl�s de tri
        foreach($this->sortkeys as $i=>& $sortkey)
        {
            // V�rifie que la cl� a un nom
            $name=trim(Utils::ConvertString($sortkey->name, 'alphanum'));
            if ($name==='' || strpos($name, ' ')!==false)
                $errors[]="Nom incorrect pour la cl� de tri #$i";

            // V�rifie que ce nom est unique
            if (isset($sortkeys[$name]))
                $errors[]="Les cl�s de tri #$i et #$sortkeys[$name] ont le m�me nom";
            $sortkeys[$name]=$i;
            
            // V�rifie que la cl� a au moins un index
            if (count($sortkey->fields)===0)
                $errors[]="Aucun champ n'a �t� indiqu� pour la cl� de tri #$i ($sortkey->name)";
            else foreach ($sortkey->fields as $j=>&$field)
            {
                // V�rifie que le champ indiqu� existe
                $fieldnames=str_word_count(Utils::ConvertString($field->name, 'alphanum'), 1, '0123456789');
                if (count($fieldnames)===0)
                    $errors[]="Aucun champ indiqu� dans la cl� de tri #$i : '$name'";
                else
                {
                    foreach($fieldnames as $fieldname)
                    {
                        if (!isset($fields[$fieldname]))
                            $errors[]="Nom de champ inconnu dans la cl� de tri #$i : '$fieldname'";
                        
                    }
                }
                
                // Ajuste start et end
                $this->startEnd($field, $errors, "Champ #$j de la cl� de tri #$i : ");
                $field->length=(int)$field->length;
                if (count($sortkey->fields)>1 && $j<count($sortkey->fields)-1 && empty($field->length))
                    $errors[]="Vous devez indiquer une longueur pour le champ #$i : '$fieldname' de la cl� de tri '$name'";
            }
            unset($field);
        }
        unset($sortkey);

        // Retourne le r�sultat
        return count($errors) ? $errors : true;
    }


    /**
     * Fonction utilitaire utilis�e par {@link validate()} pour nettoyer une
     * liste de mots vides.
     * 
     * Les mots indiqu�s sont minusculis�s, d�doublonn�s et tri�s.
     *
     * @param string & $stopwords
     * @return void
     */
    private static function stopwords(& $stopwords)
    {
        $t=preg_split('~\s~', $stopwords, -1, PREG_SPLIT_NO_EMPTY);
        sort($t);
        $stopwords=implode(' ', $t);    
    }
    
    /**
     * Fonction utilitaire utilis�e par {@link validate()} pour ajuster les 
     * propri�t�s start et end d'un objet
     *
     * @param StdClass $object
     */
    private function startEnd($object, & $errors, $label)
    {
        // Convertit en entier les chaines qui repr�sentent des entiers, en null les chaines vides
        foreach (array('start','end') as $prop)
        {
            if (is_string($object->$prop))
            {
                if (ctype_digit(trim($object->$prop))) // entier sous forme de chaine
                    $object->$prop=(int)$object->$prop;
            }
            elseif(is_null($object->$prop))
                $object->$prop='';
        }
        
        // Si start et end sont des indices, v�rifie que end > start
        if (
            is_int($object->start) && 
            is_int($object->end) && 
            (($object->start>0 && $object->end>0) || ($object->start<0 && $object->end<0)) &&  
            ($object->start > $object->end))
            $errors[]=$label . 'end doit �tre strictement sup�rieur � start';
            
        // Si start vaut 0, met null
        if (is_int($object->start))
            $object->start=null;
            
        // End ne peut pas �tre � z�ro 
        if ($object->end===0)
            $errors[]=$label . 'end ne peut pas �tre � z�ro';
                    
    }
    
    /**
     * Fusionne des objets ou des tableaux ensembles.
     * 
     * Ajoute dans $a tous les �l�ments de $b qui n'existe pas d�j�.
     * 
     * L'algorithme de fusion est le suivant : 
     * Pour chaque �l�ment (key,value) de $b :
     * - si key est un entier : $a[]=valeur
     * - si key n'existe pas encore dans a : $a[cl�]=valeur
     * - si key existe et si a[key] est un objet ou un tableau, fusion r�cursive
     * de a[key] avec value.
     * 
     * Le m�me traitement est r�p�t� pour chacun des arguments suppl�mentaires
     * pass�s en param�tre.
     * 
     * Le type initial du premier argument d�termine le type de la valeur 
     * retourn�e : si c'est un objet, la fonction retourne un objet StdClass 
     * contenant l'ensemble des propri�t�s obtenues. Dans tous les autres cas,
     * elle retourne un tableau.
     * 
     * Exemple (en pseudo code) :
     * o1 = {city:'rennes', country:'fra'} // un objet 
     * o2 = {postcode: 35043} // un objet
     * t1 = array('city'=>'rennes', 'country'=>'fra') // un tableau 
     * t2 = array('postcode'=>35043) // un tableau
     *
     * merge(o1,o2) et merge(o1,t2) vont tous les deux retourner un objet :
     * {city:'rennes', country:'fra', postcode: 35043} // un objet
     * 
     * merge(t1,t2) et merge(t1,o2) vont tous les deux retourner un tableau :
     * array('city'=>'rennes', 'country'=>'fra', 'postcode'=>35043)
     * 
     * Si les arguments pass�s en param�tre sont des types simples, ils seront
     * cast�s en tableau puis seront fusionn�s comme indiqu�.
     * Exemple : 
     * merge('hello', 'word') = array(0=>'hello', 1=>'word')
     *
     * @param mixed $a
     * @param mixed $b
     * @return mixed
     */
    /*
    public static function merge($a, $b) // $c, $d, etc.
    {
        $asObject=is_object($a);
        
        $a=(array)$a;
        
        $nb = func_num_args();
        for ($i = 1; $i < $nb; $i++)
        {
            $b = func_get_arg($i);        
            foreach((array)$b as $prop=>$value)
            {
                if (is_int($prop))
                    $a[]=$value;
                elseif (!array_key_exists($prop, $a)) 
                    $a[$prop]=$value;
                elseif(is_object($value) || is_array($value))
                    $a[$prop]=self::merge($a[$prop], $value);
            }
        }
        return $asObject ? (object)$a : $a;
    }
    */
    
    /**
     * Cr�e toutes les propri�t�s qui existent dans le dtd mais qui n'existe
     * pas encore dans l'obet. 
     *
     * @param objetc $object
     * @param array $dtd
     * @return $object
     */
    public static function defaults($object, $dtd)
    {
        foreach ($dtd as $prop=>$value)
        {
            if (! property_exists($object, $prop))
            { 
                if (is_array($value))
                {
                    if (count($value)===1 && is_array(reset($value)))
                        $object->$prop= array();
                    else
                    {
                        $object->$prop= self::defaults(new StdClass(), $dtd[$prop]);
                    }
                }
                else
                {
                    $object->$prop=$value;
                }
            }
            elseif (is_array($object->$prop))
            {
                foreach ($object->$prop as $i=>&$item)
                    $item=self::defaults($item, $value[key($value)]);
                unset($item);
            }
        }
        return $object;
    }

    /**
     * Compile la structure de base de donn�es en cours.
     * 
     * - Indexation des objets de la base par nom :
     * Dans une structure non compil�e, les cl�s de tous les tableaux 
     * (db.fields, db.indices, db.indices[x].fields, etc.) sont de simples
     * num�ros. Dans une structure compil�e, les cl�s sont la version 
     * minusculis�e et sans accents du nom de l'item (la propri�t� name de 
     * l'objet)
     * 
     * - Attribution d'un ID unique � chacun des objets de la base :
     * Pour chaque objet (champ, index, alias, table de lookup, cl� de tri), 
     * attribution d'un num�ro unique qui n'ait jamais �t� utilis� auparavant
     * (utilisation de db.lastId, coir ci-dessous).
     * Remarque : actuellement, un ID est un simple num�ro commencant � 1, quel 
     * que soit le type d'objet. Les num�ros sont attribu�s de mani�re cons�cutive,
     * mais rien ne garantit que la structure finale a des num�ros cons�cutifs
     * (par exemple, si on a supprim� un champ, il y aura un "trou" dans les
     * id des champs, et l'ID du champ supprim� ne sera jamais r�utilis�).   
     * 
     * - Cr�ation/mise � jour de db._lastid
     * Cr�ation si elle n'existe pas encore ou mise � jour dans le cas 
     * contraire de la propri�t� db.lastId. Il s'agit d'un objet ajout� comme
     * propri�t� de la base elle m�me. Chacune des propri�t�s de cet objet 
     * est un entier qui indique le dernier ID attribu� pour un type d'objet 
     * particulier. Actuellement, les propri�t�s de cet objet sont : lastId.field, 
     * lastId.index, lastId.alias, lastId.lookuptable et lastId.sortkey'.
     * 
     * - Cr�ation d'une propri�t� de type entier pour les propri�t�s ayant une
     * valeur exprim�e sous forme de chaine de caract�res :
     * field.type ('text', 'int'...) -> field._type (1, 2...)
     * index.type ('none', 'word'...) -> index._type
     * 
     * - Conversion en entier si possible des propri�t�s 'start' et 'end'
     * objets concern�s : index.field, lookuptable.field, sortkey.field
     * Si la chaine de caract�res repr�sente un entier, conversion sous forme
     * d'entier, sinon on conserve sous forme de chaine (permet des tests 
     * rapides du style is_int() ou is_string())
     * 
     * - Indexation pour acc�s rapide des mots-vides
     * db.stopwords, field.stopwords : _stopwords[] = tableau index� par mot
     * (permet de faire isset(stopwords[mot]))
     */
    public function compile()
    {
        // Indexe tous les tableaux par nom
        self::compileArrays($this);
     
        // Attribue un ID � tous les �l�ments des tableaux de premier niveau
        foreach($this as $prop=>$value)
        {
            if (is_array($value) && count($value))
            {
                foreach($value as $item)
                {
                    if (empty($item->_id))
                    {
                        $type=key(self::$dtd['database'][$prop]);
                        $item->_id = ++$this->_lastid->$type;
                    }
                }
            }
        }
        
        // Types des champs
        foreach($this->fields as $field)
        {
            switch(strtolower(trim($field->type)))
            {
                case 'autonumber': $field->_type=self::FIELD_AUTONUMBER;    break;
                case 'bool':       $field->_type=self::FIELD_BOOL;          break;
                case 'int':        $field->_type=self::FIELD_INT;           break;
                case 'text':       $field->_type=self::FIELD_TEXT;          break;
                default:
                    throw new LogicException('Type de champ incorrect, aurait d� �tre d�tect� avant : ' . $field->type);
            }
        }
    }


    /**
     * Fonction utilitaire utilis�e par {@link compile()}.
     * 
     * Compile les propri�t�s de type tableaux pr�sentes dans l'objet pass� en 
     * param�tre (remplace les cl�s du tableau par la version minu du nom de 
     * l'�l�ment)
     *
     * @param StdClass $object
     */
    private static function compileArrays($object)
    {
        foreach($object as $prop=>& $value)
        {
            if (is_array($value) && count($value))
            {
                $result=array();
                foreach($value as $item)
                {
                    $name=trim(Utils::ConvertString($item->name, 'alphanum'));
                    self::compileArrays($item);
                    $result[$name]=$item;
                }
                $value=$result;
            }
        }
    }
}

/**
 * Exception g�n�rique g�n�r�e par {@link DatabaseStructure}
 * 
 * @package     fab
 * @subpackage  database
 */
class DatabaseStructureException extends RuntimeException { };

/**
 * Exception g�n�r�e lorsqu'un fichier xml repr�sentant une structure de base
 * de donn�es contient des erreurs
 * 
 * @package     fab
 * @subpackage  database
 */
class DatabaseStructureXmlException extends DatabaseStructureException { };

/**
 * Exception g�n�r�e lorsqu'un fichier xml repr�sentant une structure de base
 * de donn�es contient un noeud incorrect
 * 
 * @package     fab
 * @subpackage  database
 */
class DatabaseStructureXmlNodeException extends DatabaseStructureXmlException
{
    public function __construct(DOMNode $node, $message)
    {
        $path='';
            
        while ($node)
        {
            if ($node instanceof DOMDocument) break;
            if ($node->hasAttributes() && $node->hasAttribute('name')) 
                $name='("'.$node->getAttribute('name').'")';
            else
                $name='';
            $path='/' . $node->nodeName . $name . $path;
            $node = $node->parentNode;
        }
        parent::__construct(sprintf('Erreur dans le fichier xml pour ' . $path . ' : %s', $message));
    }
}

//$dbs=new DatabaseStructure();
//$dbs->indexstopwords='fhdsjkhfdsk';
//$dbs->fields[]=new stdClass();
//
//var_export($dbs->validate());
//die();


?>