<?php
/**
 * @package     fab
 * @subpackage  database
 * @author      Daniel Ménard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id
 */

/**
 * La structure d'une base de données fab.
 * 
 * Cette classe offre des fonctions permettant de charger, de valider et de 
 * sauvegarder la structure d'une base de données en format XML et JSON.
 * 
 * @package     fab
 * @subpackage  database
 */
class DatabaseStructure
{
    /**
     * Les types de champ autorisés
     *
     */
    const
        FIELD_INT    = 1,
        FIELD_AUTONUMBER=2,
        FIELD_TEXT=3,
        FIELD_BOOL=4;

    /**
     * Ce tableau décrit les propriétés d'une structure de base de données.
     * 
     * @var array
     */
    private static $dtd=array       // NUPLM = non utilisé pour le moment 
    (
        'database'=>array
        (
                                        // PROPRIETES GENERALES DE LA BASE
                                        
            'label'=>'',                // Un libellé court décrivant la base
            'version'=>'1.0',           // NUPLM Version de fab qui a créé la base
            'description'=>'',          // Description, notes, historique des modifs...
            'stopwords'=>'',            // Liste par défaut des mots-vides à ignorer lors de l'indexation
            'indexstopwords'=>false,    // faut-il indexer les mots vides ?
            'creation'=>'',           // Date de création de la structure
            'lastupdate'=>'',         // Date de dernière modification de la structure
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
                    '_id'=>0,            // Identifiant (numéro unique) du champ
                    'name'=>'',             // Nom du champ, d'autres noms peuvent être définis via des alias
                    'type'=>'text',         // Type du champ (juste à titre d'information, non utilisé pour l'instant)
                    '_type'=>0,          // Traduction de la propriété type en entier
                    'label'=>'',            // Libellé du champ
                    'description'=>'',      // Description
                    'defaultstopwords'=>true, // Utiliser les mots-vides de la base
                    'stopwords'=>'',        // Liste spécifique de mots-vides à appliquer à ce champ
                )
            ),
            
            /*
                Combinatoire stopwords/defaultstopwords : 
                - lorsque defaultstopwords est à true, les mots indiqués dans 
                  stopwords viennent en plus de ceux indiqués dans db.stopwords.
                - lorsque defaultstopwords est à false, les mots indiqués
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
                    '_id'=>0,            // Identifiant (numéro unique) de l'index
                    'name'=>'',             // Nom de l'index
                    'label'=>'',            // Libellé de l'index
                    'description'=>'',      // Description de l'index
                    'fields'=>array         // La liste des champs qui alimentent cet index
                    (
                        'field'=> array
                        (               
                            '_id'=>0,            // Identifiant du champ
                            'name'=>'',         // Nom du champ
                            'words'=>false,     // Indexer les mots
                            'phrases'=>false,   // Indexer les phrases
                            'values'=>false,    // Indexer les valeurs
                            'count'=>false,     // Compter le nombre de valeurs (empty, has1, has2...)
                            'global'=>false,    // Prendre en compte cet index dans l'index 'tous champs'
                            'start'=>'',      // Position ou chaine indiquant le début du texte à indexer
                            'end'=>'',        // Position ou chain indquant la fin du texte à indexer
                            'weight'=>1         // Poids des tokens ajoutés à cet index
                        )
                    )
                )
            ),
    
            'lookuptables'=>array            // LOOKUpTABLES : LISTE DES TABLES DE LOOKUP
            (
                'lookuptable'=>array
                (
                    '_id'=>0,            // Identifiant (numéro unique) de la table
                    'name'=>'',             // Nom de la table
                    'label'=>'',            // Libellé de l'index
                    'description'=>'',      // Description de l'index
                    'fields'=>array         // La liste des champs qui alimentent cette table
                    (
                        'field'=>array
                        (
                            '_id'=>0,            // Identifiant (numéro unique) du champ
                            'name'=>'',         // Nom du champ
                            'start'=>'',      // Position de début ou chaine délimitant le début de la valeur à ajouter à la table
                            'end'=>''         // Longueur ou chaine délimitant la fin de la valeur à ajouter à la table
                        )
                    )
                )
            ),
    
            'aliases'=>array            // ALIASES : LISTE DES ALIAS
            (
                'alias'=>array
                (
                    '_id'=>0,            // Identifiant (numéro unique) de l'alias (non utilisé)
                    'name'=>'',             // Nom de l'alias
                    'label'=>'',            // Libellé de l'index
                    'description'=>'',      // Description de l'index
                    'indices'=>array        // La liste des index qui composent cet alias
                    (
                        'index'=>array
                        (
                            '_id'=>0,            // Identifiant (numéro unique) du champ
                            'name'=>'',         // Nom de l'index
                        )
                    )
                )
            ),
            
            'sortkeys'=>array           // SORTKEYS : LISTE DES CLES DE TRI
            (
                'sortkey'=>array
                (
                    '_id'=>0,            // Identifiant (numéro unique) de la clé de tri
                    'name'=>'',             // Nom de la clé de tri
                    'label'=>'',            // Libellé de l'index
                    'description'=>'',      // Description de l'index
                    'type'=>'string',       // Type de la clé à créer ('string' ou 'number')
                    'fields'=>array         // La liste des champs qui composent cette clé de tri
                    (
                        'field'=>array
                        (
                            '_id'=>0,            // Identifiant (numéro unique) du champ
                            'name'=>'',         // Nom du champ
                            'start'=>'',      // Position de début ou chaine délimitant le début de la valeur à ajouter à la clé
                            'end'=>'',        // Longueur ou chaine délimitant la fin de la valeur à ajouter à la clé
                            'length'=>0      // Longueur totale de la partie de clé (tronquée ou paddée à cette taille)
                        )
                    )
                )
            )
        )
    );
    

    /**
     * Constructeur. Crée une nouvelle structure de base de données à partir 
     * de l'argument passé en paramètre.
     * 
     * L'argument est optionnel. Si vous n'indiquez rien ou si vous passez 
     * 'null', une nouvelle structure de base de données (vide) sera créée.
     * 
     * Sinon, la structure de la base de données va être chargée à partir de
     * l'argument passé en paramètre. Il peut s'agir :
     * <li>d'un tableau ou d'un objet php décrivant la base</li>
     * <li>d'une chaine de caractères contenant le source xml décrivant la base</li>
     * <li>d'une chaine de caractères contenant le source JSON décrivant la base</li>
     *
     * @param mixed $def 
     * @throws DatabaseStructureException si le type de l'argument passé en 
     * paramètre ne peut pas être déterminé ou si la définition a des erreurs 
     * fatales (par exemple un fichier xml mal formé)
     */
    public function __construct($def=null)
    {
        // Faut-il ajouter les propriétés par défaut ? (oui pour tous sauf xml qui le fait déjà)
        $addDefaultsProps=true;
        
        // Une structure vide
        if (is_null($def))
        {
            $this->label='Nouvelle structure de base de données';
            $this->creation=date('Y/m/d H:i:s');
        }
        
        // Une chaine de caractères contenant du xml ou du JSON
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
                    throw new DatabaseStructureException('Impossible de déterminer le type de la structure de base de données passée à '.__CLASS__);
            }
        }

        // Ajoute toutes les propriétés qui ne sont pas définies avec leur valeur par défaut
        if ($addDefaultsProps) 
        {
            $this->addDefaultProperties();
        }
    }


    /**
     * Ajoute les propriétés par défaut à tous les objets de la hiérarchie
     *
     */
    public function addDefaultProperties()
    {
        self::defaults($this, self::$dtd['database']);
    }
    
    
    /**
     * Met à jour la date de dernière modification (lastupdate) de la structure
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
     * Crée la structure de la base de données à partir d'un source xml
     *
     * @param string $xmlSource
     * @return StdClass
     */
    private function fromXml($xmlSource)
    {
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
                $h.= "- ligne $error->line : $error->message<br />\n";
            libxml_clear_errors(); // libère la mémoire utilisée par les erreurs
            throw new DatabaseStructureXmlException($h);
        }

        // Convertit la structure xml en objet
        $o=self::xmlToObject($xml->documentElement, self::$dtd);
        
        // Initialise nos propriétés à partir de l'objet obtenu
        foreach(get_object_vars($o) as $prop=>$value)
        {
            $this->$prop=$value;
        }
    }

    
    /**
     * Fonction utilitaire utilisée par {@link fromXml()} pour convertir un 
     * source xml en objet.
     *
     * @param DOMNode $node le noeud xml à convertir
     * @param array $dtd un tableau indiquant les noeuds et attributs autorisés
     * @return StdClass
     * @throws DatabaseStructureXmlNodeException si le source xml contient des 
     * attributs ou des tags non autorisés
     */
    private static function xmlToObject(DOMNode $node, array $dtd)
    {
        // Vérifie que le nom du noeud correspond au tag attendu tel qu'indiqué par le dtd
        if (count($dtd)>1)
            throw new LogicException('DTD invalide : le tableau doit contenir un seul élément');
        reset($dtd);
        $tag=key($dtd);
        if ($node->tagName !== $tag)
            throw new DatabaseStructureXmlNodeException($node, "élément non autorisé, '$tag' attendu");
        $dtd=array_pop($dtd);
                    
        // Crée un nouvel objet contenant les propriétés par défaut indiquées dans le dtd
        $result=self::defaults(new StdClass, $dtd);

        // Les attributs du tag sont des propriétés de l'objet
        if ($node->hasAttributes())
        {
            foreach ($node->attributes as $attribute)
            {
                // Le nom de l'attribut va devenir le nom de la propriété
                $name=$attribute->nodeName;
                
                // Vérifie que c'est un élément autorisé
                if (! array_key_exists($name, $dtd))
                    throw new DatabaseStructureXmlNodeException($node, "l'attribut '$name' n'est pas autorisé");
                    
                // Si la propriété est un objet, elle ne peut pas être définie sous forme d'attribut
                if (is_array($dtd[$name]))
                    throw new DatabaseStructureXmlNodeException($node, "'$name' n'est pas autorisé comme attribut, seulement comme élément fils");
                    
                // Définit la propriété
                $result->$name=self::xmlToValue(utf8_decode($attribute->nodeValue), $attribute, $dtd[$name]);
            }
        }
        
        // Les noeuds fils du tag sont également des propriéts de l'objet
        foreach ($node->childNodes as $child)
        {
            switch ($child->nodeType)
            {
                case XML_ELEMENT_NODE:
                    // Le nom de l'élément va devenir le nom de la propriété
                    $name=$child->tagName;
                    
                    // Vérifie que c'est un élément autorisé
                    if (! array_key_exists($name, $dtd))
                        throw new DatabaseStructureXmlNodeException($node, "l'élément '$name' n'est pas autorisé");
                        
                    // Vérifie qu'on n'a pas à la fois un attribut et un élément de même nom (<database label="xxx"><label>yyy...)
                    if ($node->hasAttribute($name))
                        throw new DatabaseStructureXmlNodeException($node, "'$name' apparaît à la fois comme attribut et comme élément");

                    // Cas d'une propriété simple (scalaire)
                    if (! is_array($dtd[$name]))
                    {
                        $result->$name=self::xmlToValue(utf8_decode($child->nodeValue), $child, $dtd[$name]); // si plusieurs fois le même tag, c'est le dernier qui gagne
                    }
                    
                    // Cas d'un tableau
                    else
                    {
                        // Une collection : le tableau a un seul élément qui est lui même un tableau décrivant les noeuds
                        if (count($dtd[$name])===1 && is_array(reset($dtd[$name])))
                        {
                            foreach($child->childNodes as $child)
                                array_push($result->$name, self::xmlToObject($child, $dtd[$name]));
                        }
                        
                        // Un objet (exemple : _lastid) : plusieurs propriétés ou une seule mais pas un tableau 
                        else
                        {
                            $result->$name=self::xmlToObject($child, array($name=>$dtd[$name]));
                        }
                    }
                    break;

                // Types de noeud autorisés mais ignorés
                case XML_COMMENT_NODE:
                    break;
                    
                // Types de noeud interdits
                default:
                    throw new DatabaseStructureXmlNodeException($node, "les noeuds de type '".$child->nodeName . "' ne sont pas autorisés");
            }
        }
        return $result;
    }


    /**
     * Fonction utilitaire utilisée par {@link xmlToObject()} pour convertir la
     * valeur d'un attribut ou le contenu d'un tag.
     * 
     * Pour les booléens, la fonction reconnait les valeurs 'true' ou 'false'.
     * Pour les autres types scalaires, la fonction encode les caractères '<', 
     * '>', '&' et '"' par l'entité xml correspondante.
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
            throw new DatabaseStructureXmlNodeException($node, 'booléen attendu');
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
     * Retourne la version xml de la structure de base de données en cours.
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
     * Fonction utilitaire utilisée par {@link toXml()} pour générer la version
     * Xml de la structure de la base.
     *
     * @param array $dtd le dtd décrivant la structure de la base 
     * @param string $tag le nom du tag xml à générer
     * @param StdClass $object l'objet à générer
     * @param string $indent l'indentation en cours
     * @return string le source xml généré
     */
    private static function nodeToXml($dtd, $object, $indent='')
    {
        // Extrait du DTD le nom du tag à générer
        if (count($dtd)>1)
            throw new LogicException('DTD invalide : le tableau doit contenir un seul élément');
        reset($dtd);
        $tag=key($dtd);
        $dtd=array_pop($dtd);
        
        $attr=array();
        $simpleNodes=array();
        $complexNodes=array();
        $objects=array();
        
        // Parcourt toutes les propriétés pour les classer
        foreach($object as $prop=>$value)
        {
            // La propriété a la valeur par défaut indiquée dans le DTD : on l'ignore
            if(array_key_exists($prop,$dtd) && $value === $dtd[$prop])
                continue;
            
            // Valeurs scalaires (entiers, chaines, booléens...)
            if (is_scalar($value) || is_null($value))
            {
                $value=(string)$value;
                
                // Si la valeur est courte, ce sera un attribut
                if (strlen($value)<80) 
                    $attr[]=$prop;
                     
                // sinon, ce sera un élément
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
            
        // Ecrit le début du tag et ses attributs
        echo $indent, '<', $tag;
        foreach($attr as $prop)
            echo ' ', $prop, '="', self::valueToXml($object->$prop), '"';
            
        // Si le tag ne contient pas de noeuds fils, terminé
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

        // Puis toutes les propriétés 'objet'
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
     * Fonction utilitaire utilisée par {@link nodeToXml()} pour écrire la
     * valeur d'un attribut ou le contenu d'un tag.
     * 
     * Pour les booléens, la fonction génère les valeurs 'true' ou 'false'.
     * Pour les autres types scalaires, la fonction encode les caractères '<', 
     * '>', '&' et '"' par l'entité xml correspondante.
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
     * Initialise la structure de la base de données à partir d'un source JSON.
     * 
     * La chaine passée en paramètre doit être encodée en UTF8. Elle est 
     * décodée de manière à ce que la structure de base de données obtenue
     * soit encodée en ISO-8859-1.
     *
     * @param string $xmlSource
     * @return StdClass
     */
    private function fromJson($json)
    {
        // Crée un objet à partir de la chaine JSON
        $o=Utils::utf8Decode(json_decode($json, false));
        
        // Initialise nos propriétés à partir de l'objet obtenu
        foreach(get_object_vars($o) as $prop=>$value)
        {
            $this->$prop=$value;
        }
    }
    

    /**
     * Retourne la version JSON de la structure de base de données en cours.
     * 
     * Remarque : la chaine obtenu est encodée en UTF-8.
     * 
     * @return string
     */    
    public function toJson()
    {
        // Si notre structure est compilée, les clés de tous les tableaux
        // sont des chaines et non plus des entiers. Or, la fonction json_encode
        // de php traite ce cas en générant alors un objet et non plus un 
        // tableau (je pense que c'est conforme à la spécification JSON dans la
        // mesure où on ne peut pas, en json, spécifier les clés du tableau).
        // Le problème, c'est que l'éditeur de structure ne sait pas gérer ça :
        // il veut absolument un tableau.
        // Pour contourner le problème, on utilise notre propre version de 
        // json_encode qui ignore les clés des tableaux (ie fait l'inverse de
        // compileArrays)
        
        ob_start();
        self::jsonEncode($this);
        return ob_get_clean();
        
        // version json originale
        // return json_encode(Utils::utf8Encode($this));
    }
    
    /**
     * Fonction utilitaire utilisée par {@link toJson()}
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
            foreach($o as $value) // ignore les clés
            {
                echo $comma;
                self::jsonEncode($value);
                $comma=',';
            }    
            echo ']';
            return;
        }
        throw new LogicException(__CLASS__ . '::'.__METHOD__.' : type non géré : '.var_export($o,true));
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
     * Redresse et valide la structure de la base de données, détecte les 
     * éventuelles erreurs.
     * 
     * @return (true|array) retourne 'true' si aucune erreur n'a été détectée
     * dans la structure de la base de données. Retourne un tableau contenant
     * un message pour chacune des erreurs rencontrées sinon.
     */
    public function validate()
    {
        $errors=array();
        
        // Tri et nettoyage des mots-vides
        self::stopwords($this->stopwords);
        $this->indexstopwords=self::boolean($this->indexstopwords);
        
        // Vérifie qu'on a au moins un champ
//        if (count($this->fields)===0)
//            $errors[]="Structure de base incorrecte, aucun champ n'a été défini";
    
        // Tableau utilisé pour dresser la liste des champs/index/alias utilisés
        $fields=array();
        $indices=array();
        $lookuptables=array();
        $aliases=array();
        $sortkeys=array();
        
        // Vérifie la liste des champs
        foreach($this->fields as $i=>&$field)
        {
            // Vérifie que le champ a un nom correct, sans caractères interdits
            $name=trim(Utils::ConvertString($field->name, 'alphanum'));
            if ($name==='' || strpos($name, ' ')!==false)
                $errors[]="Nom incorrect pour le champ #$i : '$field->name'";
            
            // Vérifie le type du champ
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
            
            // Vérifie que le nom du champ est unique
            if (isset($fields[$name]))
                $errors[]="Les champs #$i et #$fields[$name] ont le même nom";
            $fields[$name]=$i;
            
            // Tri et nettoie les mots-vides
            self::stopwords($field->stopwords);
            
            // Vérifie la propriété defaultstopwords
            $field->defaultstopwords=self::boolean($field->defaultstopwords);
            
        }
        unset($field);
        

        // Vérifie la liste des index
        foreach($this->indices as $i=>&$index)
        {
            // Vérifie que l'index a un nom
            $name=trim(Utils::ConvertString($index->name, 'alphanum'));
            if ($name==='' || strpos($name, ' ')!==false)
                $errors[]="Nom incorrect pour l'index #~$i : '$index->name'";
                
            // Vérifie que le nom de l'index est unique
            if (isset($indices[$name]))
                $errors[]="Les index #$i et #$indices[$name] ont le même nom";
            $indices[$name]=$i;
            
            // Vérifie que l'index a au moins un champ
            if (count($index->fields)===0)
                $errors[]="Aucun champ n'a été indiqué pour l'index #$i ($index->name)";
            else foreach ($index->fields as $j=>&$field)
            {
                // Vérifie que le champ indiqué existe
                $name=trim(Utils::ConvertString($field->name, 'alphanum'));
                if (!isset($fields[$name]))
                    $errors[]="Champ inconnu dans l'index #$i : '$name'";
                    
                // Vérifie les propriétés booléenne words/phrases/values/count et global
                $field->words=self::boolean($field->words);
                $field->phrases=self::boolean($field->phrases);
                if ($field->phrases) $field->words=true;
                $field->values=self::boolean($field->values);
                $field->count=self::boolean($field->count);
                $field->global=self::boolean($field->global);
                    
                // Vérifie qu'au moins un des types d'indexation est sélectionné
                if (! ($field->words || $field->phrases || $field->values || $field->count))
                    $errors[]="Le champ #$j ne sert à rien dans l'index #$i : aucun type d'indexation indiqué";
                    
                // Poids du champ
                $field->weight=trim($field->weight);
                if ($field->weight==='') $field->weight=1;
                if ((! is_int($field->weight) && !ctype_digit($field->weight)) || (1>$field->weight=(int)$field->weight))
                    $errors[]="Propriété weight incorrecte pour le champ #$j de l'index #$i (entier supérieur à zéro attendu)";
                    
                // Ajuste start et end
                $this->startEnd($field, $errors, "Champ #$j de l'index #$i : ");
            }
            unset($field);
        }
        unset($index);


        // Vérifie la liste des tables des entrées
        foreach($this->lookuptables as $i=>&$lookuptable)
        {
            // Vérifie que la table a un nom
            $name=trim(Utils::ConvertString($lookuptable->name, 'alphanum'));
            if ($name==='' || strpos($name, ' ')!==false)
                $errors[]="Nom incorrect pour la table des entrées #~$i : '$index->name'";
                
            // Vérifie que le nom de la table est unique
            if (isset($lookuptables[$name]))
                $errors[]="Les tables d'entrées #$i et #$lookuptables[$name] ont le même nom";
            $lookuptables[$name]=$i;
                
            // Vérifie que la table a au moins un champ
            if (count($lookuptable->fields)===0)
                $errors[]="Aucun champ n'a été indiqué pour la table des entrées #$i ($lookuptable->name)";
            else foreach ($lookuptable->fields as $j=>&$field)
            {
                // Vérifie que le champ indiqué existe
                $name=trim(Utils::ConvertString($field->name, 'alphanum'));
                if (!isset($fields[$name]))
                    $errors[]="Champ inconnu dans la table des entrées #$i : '$name'";

                // Ajuste start et end
                $this->startEnd($field, $errors, "Champ #$j de la table des entrées #$i : ");
            }
            unset($field);
        }
        unset($lookuptable);    

        
        // Vérifie la liste des alias
        foreach($this->aliases as $i=>& $alias)
        {
            // Vérifie que l'alias a un nom
            $name=trim(Utils::ConvertString($alias->name, 'alphanum'));
            if ($name==='' || strpos($name, ' ')!==false)
                $errors[]="Nom incorrect pour l'alias #$i";

            // Vérifie que ce nom est unique
            if (isset($indices[$name]))
                $errors[]="Impossible de définir l'alias '$name' : ce nom est déjà utilisé pour désigner un index de base";
            if (isset($aliases[$name]))
                $errors[]="Les alias #$i et #$aliases[$name] ont le même nom";
            $aliases[$name]=$i;
            
            // Vérifie que l'alias a au moins un index
            if (count($alias->indices)===0)
                $errors[]="Aucun index n'a été indiqué pour l'alias #$i ($alias->name)";
            else foreach ($alias->indices as $j=>&$index)
            {
                // Vérifie que l'index indiqué existe
                $name=trim(Utils::ConvertString($index->name, 'alphanum'));
                if (!isset($indices[$name]))
                    $errors[]="Index '$name' inconnu dans l'alias #$i ($alias->name)";
            }
            unset($index);
        }
        unset($alias);

        
        // Vérifie la liste des clés de tri
        foreach($this->sortkeys as $i=>& $sortkey)
        {
            // Vérifie que la clé a un nom
            $name=trim(Utils::ConvertString($sortkey->name, 'alphanum'));
            if ($name==='' || strpos($name, ' ')!==false)
                $errors[]="Nom incorrect pour la clé de tri #$i";

            // Vérifie que ce nom est unique
            if (isset($sortkeys[$name]))
                $errors[]="Les clés de tri #$i et #$sortkeys[$name] ont le même nom";
            $sortkeys[$name]=$i;
            
            // Vérifie le type de clé
            $sortkey->type=strtolower(trim($sortkey->type));
            switch($sortkey->type)
            {
                case 'number':
                case 'string':
                    break; // ok
                case '':
                case null:
                    $sortkey->type='string';
                default:
                    $errors[]="Type incorrect pour la clé de tri #$i : '$name'";
            }
            
            // Vérifie que la clé a au moins un champ
            if (count($sortkey->fields)===0)
                $errors[]="Aucun champ n'a été indiqué pour la clé de tri #$i ($sortkey->name)";
            else 
            {
                foreach ($sortkey->fields as $j=>&$field)
                {
                    // Vérifie que le champ indiqué existe
                    $name=trim(Utils::ConvertString($field->name, 'alphanum'));
                    if (!isset($fields[$name]))
                        $errors[]="Nom de champ inconnu dans la clé de tri #$i : '$name'";
                    
                    // Ajuste start et end
                    $this->startEnd($field, $errors, "Champ #$j de la clé de tri #$i : ");
                    $field->length=(int)$field->length;
                }
                unset($field);
            }
        }
        unset($sortkey);

        // Retourne le résultat
        return count($errors) ? $errors : true;
    }


    /**
     * Fonction utilitaire utilisée par {@link validate()} pour nettoyer une
     * liste de mots vides.
     * 
     * Les mots indiqués sont minusculisés, dédoublonnés et triés.
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
     * Fonction utilitaire utilisée par {@link validate()} pour ajuster les 
     * propriétés start et end d'un objet
     *
     * @param StdClass $object
     */
    private function startEnd($object, & $errors, $label)
    {
        // Convertit en entier les chaines qui représentent des entiers, en null les chaines vides
        foreach (array('start','end') as $prop)
        {
            if (is_string($object->$prop))
            {
                if (ctype_digit(trim($object->$prop))) // entier sous forme de chaine
                {
                    $object->$prop=(int)$object->$prop;
                }
            }
            elseif(is_null($object->$prop))
            {
                $object->$prop='';
            }
        }
        
        // Si start et end sont des indices, vérifie que end > start
        if (
            is_int($object->start) && 
            is_int($object->end) && 
            (($object->start>0 && $object->end>0) || ($object->start<0 && $object->end<0)) &&  
            ($object->start > $object->end))
            $errors[]=$label . 'end doit être strictement supérieur à start';
            
        // Si start vaut 0, met null
        if ($object->start===0) $object->start=null;
            
        // End ne peut pas être à zéro 
        if ($object->end===0) $errors[]=$label . 'end ne peut pas être à zéro';
                    
    }
    
    /**
     * Fusionne des objets ou des tableaux ensembles.
     * 
     * Ajoute dans $a tous les éléments de $b qui n'existe pas déjà.
     * 
     * L'algorithme de fusion est le suivant : 
     * Pour chaque élément (key,value) de $b :
     * - si key est un entier : $a[]=valeur
     * - si key n'existe pas encore dans a : $a[clé]=valeur
     * - si key existe et si a[key] est un objet ou un tableau, fusion récursive
     * de a[key] avec value.
     * 
     * Le même traitement est répêté pour chacun des arguments supplémentaires
     * passés en paramètre.
     * 
     * Le type initial du premier argument détermine le type de la valeur 
     * retournée : si c'est un objet, la fonction retourne un objet StdClass 
     * contenant l'ensemble des propriétés obtenues. Dans tous les autres cas,
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
     * Si les arguments passés en paramètre sont des types simples, ils seront
     * castés en tableau puis seront fusionnés comme indiqué.
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
     * Crée toutes les propriétés qui existent dans le dtd mais qui n'existe
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
     * Compile la structure de base de données en cours.
     * 
     * - Indexation des objets de la base par nom :
     * Dans une structure non compilée, les clés de tous les tableaux 
     * (db.fields, db.indices, db.indices[x].fields, etc.) sont de simples
     * numéros. Dans une structure compilée, les clés sont la version 
     * minusculisée et sans accents du nom de l'item (la propriété name de 
     * l'objet)
     * 
     * - Attribution d'un ID unique à chacun des objets de la base :
     * Pour chaque objet (champ, index, alias, table de lookup, clé de tri), 
     * attribution d'un numéro unique qui n'ait jamais été utilisé auparavant
     * (utilisation de db.lastId, coir ci-dessous).
     * Remarque : actuellement, un ID est un simple numéro commencant à 1, quel 
     * que soit le type d'objet. Les numéros sont attribués de manière consécutive,
     * mais rien ne garantit que la structure finale a des numéros consécutifs
     * (par exemple, si on a supprimé un champ, il y aura un "trou" dans les
     * id des champs, et l'ID du champ supprimé ne sera jamais réutilisé).   
     * 
     * - Création/mise à jour de db._lastid
     * Création si elle n'existe pas encore ou mise à jour dans le cas 
     * contraire de la propriété db.lastId. Il s'agit d'un objet ajouté comme
     * propriété de la base elle même. Chacune des propriétés de cet objet 
     * est un entier qui indique le dernier ID attribué pour un type d'objet 
     * particulier. Actuellement, les propriétés de cet objet sont : lastId.field, 
     * lastId.index, lastId.alias, lastId.lookuptable et lastId.sortkey'.
     * 
     * - Création d'une propriété de type entier pour les propriétés ayant une
     * valeur exprimée sous forme de chaine de caractères :
     * field.type ('text', 'int'...) -> field._type (1, 2...)
     * index.type ('none', 'word'...) -> index._type
     * 
     * - Conversion en entier si possible des propriétés 'start' et 'end'
     * objets concernés : index.field, lookuptable.field, sortkey.field
     * Si la chaine de caractères représente un entier, conversion sous forme
     * d'entier, sinon on conserve sous forme de chaine (permet des tests 
     * rapides du style is_int() ou is_string())
     * 
     * - Indexation pour accès rapide des mots-vides
     * db.stopwords, field.stopwords : _stopwords[] = tableau indexé par mot
     * (permet de faire isset(stopwords[mot]))
     */
    public function compile()
    {
        // Indexe tous les tableaux par nom
        self::compileArrays($this);
     
        // Attribue un ID à tous les éléments des tableaux de premier niveau
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
                    throw new LogicException('Type de champ incorrect, aurait dû être détecté avant : ' . $field->type);
            }
        }

        // Stocke l'ID de chacun des champs des index
        foreach($this->indices as $index)
        {
            foreach ($index->fields as &$field)
                $field->_id=$this->fields[trim(Utils::ConvertString($field->name, 'alphanum'))]->_id;
            unset($field);
        }


        // Stocke l'ID de chacun des champs des tables des entrées
        foreach($this->lookuptables as $lookuptable)
        {
            foreach ($lookuptable->fields as &$field)
                $field->_id=$this->fields[trim(Utils::ConvertString($field->name, 'alphanum'))]->_id;
            unset($field);
        }

        
        // Stocke l'ID de chacun des index des tables des alias
        foreach($this->aliases as $alias)
        {
            foreach ($alias->indices as &$index)
                $index->_id=$this->indices[trim(Utils::ConvertString($index->name, 'alphanum'))]->_id;
            unset($index);
        }

        
        // Stocke l'ID de chacun des champs des clés de tri
        foreach($this->sortkeys as $sortkey)
        {
            foreach ($sortkey->fields as &$field)
                $field->_id=$this->fields[trim(Utils::ConvertString($field->name, 'alphanum'))]->_id;
            unset($field);
        }
    }


    /**
     * Fonction utilitaire utilisée par {@link compile()}.
     * 
     * Compile les propriétés de type tableaux présentes dans l'objet passé en 
     * paramètre (remplace les clés du tableau par la version minu du nom de 
     * l'élément)
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
    
    /**
     * Etablit la liste des modifications apportées entre la structure passée 
     * en paramètre et la structure actuelle.
     *
     * Remarque :
     * Pour faire la comparaison, la structure actuelle et la structure 
     * passée en paramètre doivent être compilées. La fonction appellera
     * automatiquement la méthode {@link compile()} pour chacune des structures  
     * si celles-ci ne sont pas déjà compilées.
     *   
     * @param DatabaseStructure $old la structure à comparer (typiquement : une
     * version plus ancienne de la structure actuelle).
     * 
     * @return array un tableau listant les modifications apportées entre la
     * structure passée en paramètre et la structure actuelle.
     * 
     * Chaque clé du tableau est un message décrivant la modification effectuée 
     * et la valeur associée à cette clé indique le "niveau de gravité" de la
     * modification apportée.
     * 
     * Exemple de tableau retourné :
     * <code>
     *      array
     *      (
     *          "Création du champ url" => 0
     *          "Suppression de la table de lookup lieux" => 1
     *          "Création de l'index liens" => 2 
     *      )
     * </code>
     * 
     * Le niveau de gravité est un chiffre dont la signification est la 
     * suivante :
     * 
     * - 0 : la modification peut être appliquée immédiatement à une base, 
     *   aucune réindexation n'est nécessaire (exemple : changement du nom d'un
     *   champ)
     * - 1 : la modification peut être appliquée immédiatement, mais il est 
     *   souhaitable de réindexer la base pour purger les données qui ne sont
     *   plus nécessaires (exemple : suppression d'un champ ou d'un index).
     * - 2 : la base devra obligatoirement être réindexée pour que la 
     *   modification apportée puisse être prise en compte (exemple : création
     *   d'un nouvel index).
     * 
     * Remarque : pour savoir s'il faut ou non réindexer la base, il suffit
     * d'utiliser la fonction {@link http://php.net/max max()} de php au 
     * tableau obtenu.
     * 
     * Exemple : 
     * <code>
     *      if (max($dbs->compare($oldDbs)) > 1)
     *          echo 'Il faut réindexer la base';
     * </code>
     * 
     * La fonction retourne un tableau vide si les deux structures sont 
     * identiques.
     * 
     */
    public function compare(DatabaseStructure $old)
    {
        // Compile les deux structures si nécessaire
        $old->compile();
        $new=$this;
        $new->compile();
        
        // Le tableau résultat
        $changes=array();
        
        // Propriétés générales de la base
        // -------------------------------
        if ($old->label !== $new->label)
            $changes['Modification du libellé de la base']=0;    
        if ($old->description !== $new->description)
            $changes['Modification de la description de la base']=0;
        if ($old->stopwords !== $new->stopwords)
            $changes['Modification des mots-vides de la base']=2;
        if ($old->indexstopwords !== $new->indexstopwords)
            $changes['Modification de la propriété "indexer les mots-vides" de la base']=2;
            
        // Liste des champs
        // ----------------
        $t1=$this->index($old->fields);
        $t2=$this->index($new->fields);
        
        // Champs supprimés
        foreach($deleted=array_diff_key($t1, $t2) as $i=>$item)
            $changes['Suppression du champ ' . $item->name]=1;
        
        // Champs créés
        foreach($added=array_diff_key($t2, $t1) as $i=>$item)
            $changes['Création du champ ' . $item->name]=0;
        
        // Ordre des champs
        if (array_keys(array_diff_key($t1,$deleted)) !== array_keys(array_diff_key($t2, $added)))
            $changes['Modification de l\'ordre des champs de la base']=0;
        
        // Champs modifiés
        foreach($t2 as $id=>$newField)
        {
            if (! isset($t1[$id])) continue;
            $oldField=$t1[$id];

            if ($oldField->name !== $newField->name)
                $changes['Renommage du champ ' . $oldField->name . ' en ' . $newField->name]=0;
            
            if ($oldField->type !== $newField->type)
                $changes['Changement du type du champ ' . $newField->name . ' (' . $oldField->type . ' -> ' . $newField->type . ')']=2;

            if ($oldField->label !== $newField->label)
                $changes['Changement du libellé du champ ' . $newField->name]=0;

            if ($oldField->description !== $newField->description)
                $changes['Changement de la description du champ ' . $newField->name]=0;

            if ($oldField->defaultstopwords !== $newField->defaultstopwords )
                $changes['Changement de la propriété "defaultstopwords" du champ ' . $newField->name]=2;
                
            if ($oldField->stopwords !== $newField->stopwords )
                $changes['Changement des mots-vides pour le champ ' . $newField->name]=2;
        }
        

        // Liste des index
        // ---------------
        $t1=$this->index($old->indices);
        $t2=$this->index($new->indices);
        
        // Index supprimés
        foreach($deleted=array_diff_key($t1, $t2) as $i=>$item)
            $changes['Suppression de l\'index ' . $item->name]=1;
            // todo : si l'index est vide, rien à faire, level 0
        
        // Index créés
        foreach($added=array_diff_key($t2, $t1) as $i=>$item)
            $changes['Création de l\'index ' . $item->name]=2;
            // todo: si nouvel index sur nouveau champ et count=false, rien à faire, level 0
        
        // Ordre des index
        if (array_keys(array_diff_key($t1,$deleted)) !== array_keys(array_diff_key($t2, $added)))
            $changes['Modification de l\'ordre des index']=0;
        
        // Index modifiés
        foreach($t2 as $id=>$newIndex)
        {
            if (! isset($t1[$id])) continue;
            $oldIndex=$t1[$id];

            if ($oldIndex->name !== $newIndex->name)
                $changes['Renommage de l\'index ' . $oldIndex->name . ' en ' . $newIndex->name]=0;
            
            if ($oldIndex->label !== $newIndex->label)
                $changes['Changement du libellé de l\'index ' . $newIndex->name]=0;

            if ($oldIndex->description !== $newIndex->description)
                $changes['Changement de la description de l\'index ' . $newIndex->name]=0;

            // Liste des champs de cet index
            $f1=$this->index($oldIndex->fields);
            $f2=$this->index($newIndex->fields);
            
            // Champs enlevés
            foreach($deleted=array_diff_key($f1, $f2) as $i=>$item)
                $changes['Suppression du champ ' . $item->name . ' de l\'index ' . $newIndex->name]=2;
                // todo : si l'index est vide, rien à faire, level 0
            
            // Champ ajoutés
            foreach($added=array_diff_key($f2, $f1) as $i=>$item)
                $changes['Ajout du champ ' . $item->name . ' dans l\'index ' . $newIndex->name]=2;
                // todo: si nouvel index sur nouveau champ et count=false, rien à faire, level 0
            
            // Ordre des champs de l'index
            if (array_keys(array_diff_key($f1,$deleted)) !== array_keys(array_diff_key($f2, $added)))
                $changes['Modification de l\'ordre des champs dans l\'index ' . $newIndex->name]=0;
    
            // Champs d'index modifiés
            foreach($f2 as $id=>$newField)
            {
                if (! isset($f1[$id])) continue;
                $oldField=$f1[$id];
                
                if ($oldField != $newField)
                    $changes['Index ' . $newIndex->name . ' : Modification des paramètres d\'indexation du champ ' . $newField->name]=2;
            }
        }
        

        // Liste des alias
        // ---------------
        $t1=$this->index($old->aliases);
        $t2=$this->index($new->aliases);
        
        // Alias supprimés
        foreach($deleted=array_diff_key($t1, $t2) as $i=>$item)
            $changes['Suppression de l\'alias ' . $item->name]=0;
        
        // Alias créés
        foreach($added=array_diff_key($t2, $t1) as $i=>$item)
            $changes['Création de l\'alias ' . $item->name]=0;
        
        // Ordre des alias
        if (array_keys(array_diff_key($t1,$deleted)) !== array_keys(array_diff_key($t2, $added)))
            $changes['Modification de l\'ordre des alias']=0;
        
        // Alias modifiés
        foreach($t2 as $id=>$newAlias)
        {
            if (! isset($t1[$id])) continue;
            $oldAlias=$t1[$id];

            if ($oldAlias->name !== $newAlias->name)
                $changes['Renommage de l\'alias ' . $oldAlias->name . ' en ' . $newAlias->name]=0;
            
            if ($oldAlias->label !== $newAlias->label)
                $changes['Changement du libellé de l\'alias ' . $newAlias->name]=0;

            if ($oldAlias->description !== $newAlias->description)
                $changes['Changement de la description de l\'alias ' . $newAlias->name]=0;

            // Liste des index de cet alias
            $f1=$this->index($oldAlias->indices);
            $f2=$this->index($newAlias->indices);
            
            // Index enlevés
            foreach($deleted=array_diff_key($f1, $f2) as $i=>$item)
                $changes['Suppression de l\'index ' . $item->name . ' de l\'alias ' . $newAlias->name]=0;
            
            // Index ajoutés
            foreach($added=array_diff_key($f2, $f1) as $i=>$item)
                $changes['Ajout de l\'index ' . $item->name . ' dans l\'alias ' . $newAlias->name]=0;
            
            // Ordre des index de l'alias
            if (array_keys(array_diff_key($f1,$deleted)) !== array_keys(array_diff_key($f2, $added)))
                $changes['Modification de l\'ordre des index dans l\'alias ' . $newAlias->name]=0;
    
            // Index d'alias modifiés
            /* Inutile : les index indiqués pour un alias n'ont pas d'autres propriétés que name
            foreach($f2 as $id=>$newIndex)
            {
                if (! isset($f1[$id])) continue;
                $oldIndex=$f1[$id];
                
                if ($oldIndex != $newIndex)
                    $changes['Alias ' . $newAlias->name . ' : Modification des paramètres d\'alias de l\'index ' . $newIndex->name]=0;
            }
            */
        }
        
        
        // Liste des tables de lookup
        // --------------------------
        $t1=$this->index($old->lookuptables);
        $t2=$this->index($new->lookuptables);
        
        // Tables de lookup supprimées
        foreach($deleted=array_diff_key($t1, $t2) as $i=>$item)
            $changes['Suppression de la table de lookup ' . $item->name]=1;
        
        // Tables de lookup créées
        foreach($added=array_diff_key($t2, $t1) as $i=>$item)
            $changes['Création de la table de lookup ' . $item->name]=2;
        
        // Ordre des tables de lookup
        if (array_keys(array_diff_key($t1,$deleted)) !== array_keys(array_diff_key($t2, $added)))
            $changes['Modification de l\'ordre des tables de lookup']=0;
        
        // Tables de lookup modifiées
        foreach($t2 as $id=>$newTable)
        {
            if (! isset($t1[$id])) continue;
            $oldTable=$t1[$id];

            if ($oldTable->name !== $newTable->name)
                $changes['Renommage de la table de lookup ' . $oldTable->name . ' en ' . $newTable->name]=0;
            
            if ($oldTable->label !== $newTable->label)
                $changes['Changement du libellé de la table de lookup ' . $newTable->name]=0;

            if ($oldTable->description !== $newTable->description)
                $changes['Changement de la description de la table de lookup ' . $newTable->name]=0;

            // Liste des champs de cette table de lookup
            $f1=$this->index($oldTable->fields);
            $f2=$this->index($newTable->fields);
            
            // Champs enlevés
            foreach($deleted=array_diff_key($f1, $f2) as $i=>$item)
                $changes['Suppression du champ ' . $item->name . ' de la table de lookup ' . $newTable->name]=2;
                // todo : si l'index est vide, rien à faire, level 0
            
            // Champ ajoutés
            foreach($added=array_diff_key($f2, $f1) as $i=>$item)
                $changes['Ajout du champ ' . $item->name . ' dans la table de lookup ' . $newTable->name]=2;
                // todo: si nouvel index sur nouveau champ et count=false, rien à faire, level 0
            
            // Ordre des champs de l'index
            if (array_keys(array_diff_key($f1,$deleted)) !== array_keys(array_diff_key($f2, $added)))
                $changes['Modification de l\'ordre des champs dans la table de lookup ' . $newTable->name]=0;
    
            // Champs dde tables de lookup modifiés
            foreach($f2 as $id=>$newField)
            {
                if (! isset($f1[$id])) continue;
                $oldField=$f1[$id];
                
                if ($oldField != $newField)
                    $changes['Table de lookup ' . $newTable->name . ' : Modification des paramètres pour le champ ' . $newField->name]=2;
            }
        }
        
        
        // Liste des tables de lookup
        // --------------------------
        $t1=$this->index($old->lookuptables);
        $t2=$this->index($new->lookuptables);
        
        // Tables de lookup supprimées
        foreach($deleted=array_diff_key($t1, $t2) as $i=>$item)
            $changes['Suppression de la table de lookup ' . $item->name]=1;
        
        // Tables de lookup créées
        foreach($added=array_diff_key($t2, $t1) as $i=>$item)
            $changes['Création de la table de lookup ' . $item->name]=2;
        
        // Ordre des tables de lookup
        if (array_keys(array_diff_key($t1,$deleted)) !== array_keys(array_diff_key($t2, $added)))
            $changes['Modification de l\'ordre des tables de lookup']=0;
        
        // Tables de lookup modifiées
        foreach($t2 as $id=>$newTable)
        {
            if (! isset($t1[$id])) continue;
            $oldTable=$t1[$id];

            if ($oldTable->name !== $newTable->name)
                $changes['Renommage de la table de lookup ' . $oldTable->name . ' en ' . $newTable->name]=0;
            
            if ($oldTable->label !== $newTable->label)
                $changes['Changement du libellé de la table de lookup ' . $newTable->name]=0;

            if ($oldTable->description !== $newTable->description)
                $changes['Changement de la description de la table de lookup ' . $newTable->name]=0;

            // Liste des champs de cette table de lookup
            $f1=$this->index($oldTable->fields);
            $f2=$this->index($newTable->fields);
            
            // Champs enlevés
            foreach($deleted=array_diff_key($f1, $f2) as $i=>$item)
                $changes['Suppression du champ ' . $item->name . ' de la table de lookup ' . $newTable->name]=2;
                // todo : si l'index est vide, rien à faire, level 0
            
            // Champ ajoutés
            foreach($added=array_diff_key($f2, $f1) as $i=>$item)
                $changes['Ajout du champ ' . $item->name . ' dans la table de lookup ' . $newTable->name]=2;
                // todo: si nouvel index sur nouveau champ et count=false, rien à faire, level 0
            
            // Ordre des champs de l'index
            if (array_keys(array_diff_key($f1,$deleted)) !== array_keys(array_diff_key($f2, $added)))
                $changes['Modification de l\'ordre des champs dans la table de lookup ' . $newTable->name]=0;
    
            // Champs dde tables de lookup modifiés
            foreach($f2 as $id=>$newField)
            {
                if (! isset($f1[$id])) continue;
                $oldField=$f1[$id];
                
                if ($oldField != $newField)
                    $changes['Table de lookup ' . $newTable->name . ' : Modification des paramètres pour le champ ' . $newField->name]=2;
            }
        }

        
        // Liste des clés de tri
        // ---------------------
        $t1=$this->index($old->sortkeys);
        $t2=$this->index($new->sortkeys);
        
        // Clés de tri supprimées
        foreach($deleted=array_diff_key($t1, $t2) as $i=>$item)
            $changes['Suppression de la clé de tri ' . $item->name]=1;
        
        // Clés de tri créées
        foreach($added=array_diff_key($t2, $t1) as $i=>$item)
            $changes['Création de la clé de tri ' . $item->name]=2;
        
        // Ordre des clés de tri
        if (array_keys(array_diff_key($t1,$deleted)) !== array_keys(array_diff_key($t2, $added)))
            $changes['Modification de l\'ordre des clés de tri']=0;
        
        // Clés de tri modifiées
        foreach($t2 as $id=>$newSortKey)
        {
            if (! isset($t1[$id])) continue;
            $oldSortKey=$t1[$id];

            if ($oldSortKey->name !== $newSortKey->name)
                $changes['Renommage de la clé de tri ' . $oldSortKey->name . ' en ' . $newSortKey->name]=0;
            
            if ($oldSortKey->label !== $newSortKey->label)
                $changes['Changement du libellé de la clé de tri ' . $newSortKey->name]=0;

            if ($oldSortKey->description !== $newSortKey->description)
                $changes['Changement de la description de la clé de tri ' . $newSortKey->name]=0;

            if ($oldSortKey->type !== $newSortKey->type)
                $changes['Changement du type de la clé de tri ' . $newSortKey->name]=2;

            // Liste des champs de cette clé de tri
            $f1=$this->index($oldSortKey->fields);
            $f2=$this->index($newSortKey->fields);
            
            // Champs enlevés
            foreach($deleted=array_diff_key($f1, $f2) as $i=>$item)
                $changes['Suppression du champ ' . $item->name . ' de la clé de tri ' . $newSortKey->name]=2;
            
            // Champ ajoutés
            foreach($added=array_diff_key($f2, $f1) as $i=>$item)
                $changes['Ajout du champ ' . $item->name . ' dans la clé de tri ' . $newSortKey->name]=2;
            
            // Ordre des champs de la clé de tri
            if (array_keys(array_diff_key($f1,$deleted)) !== array_keys(array_diff_key($f2, $added)))
                $changes['Modification de l\'ordre des champs dans la clé de tri ' . $newSortKey->name]=2;
    
            // Champs de clés de tri modifiés
            foreach($f2 as $id=>$newField)
            {
                if (! isset($f1[$id])) continue;
                $oldField=$f1[$id];
                
                if ($oldField != $newField)
                    $changes['Clé de tri ' . $newSortKey->name . ' : Modification des paramètres pour le champ ' . $newField->name]=2;
            }
        }
        
        // Retourne le résultat
        return $changes;
    }

    /**
     * Fonction utilitaire utilisée par {@link compare()}.
     * 
     * Index la collection d'objet en paramétre par id.
     *
     * @param array $collection un tableau contenant des objets ayant une
     * propriété '_id'
     * 
     * @return array le même tableau mais dans lequel les clés des objets
     * correspondent à la valeur de la propriété _id.
     */
    private function index($collection)
    {
        $t=array();
        foreach($collection as $item)
            $t[$item->_id]=$item;
        return $t;
    }
}


/**
 * Exception générique générée par {@link DatabaseStructure}
 * 
 * @package     fab
 * @subpackage  database
 */
class DatabaseStructureException extends RuntimeException { };

/**
 * Exception générée lorsqu'un fichier xml représentant une structure de base
 * de données contient des erreurs
 * 
 * @package     fab
 * @subpackage  database
 */
class DatabaseStructureXmlException extends DatabaseStructureException { };

/**
 * Exception générée lorsqu'un fichier xml représentant une structure de base
 * de données contient un noeud incorrect
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