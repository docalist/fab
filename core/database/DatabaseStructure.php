<?php
/**
 * @package     fab
 * @subpackage  database
 * @author      Daniel Ménard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id
 */

/**
 * Classe permettant de manipuler la structure d'une base de données.
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
            'sep'=>'',                  // NUPLM, à virer
            'stopwords'=>'',            // Liste par défaut des mots-vides à ignorer lors de l'indexation
    
            'fields'=>array             // FIELDS : LISTE DES CHAMPS DE LA BASE
            (
                'field'=>array          
                (
                    'name'=>'',             // Nom du champ, d'autres noms peuvent être définis via des alias
                    'type'=>'text',         // Type du champ (juste à titre d'information, non utilisé pour l'instant)
                    'label'=>'',            // Libellé du champ
                    'description'=>'',      // Description
                    'stopwords'=>'',        // Liste spécifique de mots-vides à appliquer à ce champ
                )
            ),
            
            'indices'=>array            // INDICES : LISTE DES INDEX  
            (
                'index'=>array
                (
                    'name'=>'',             // Nom de l'index
                    'fields'=>array         // La liste des champs qui alimentent cet index
                    (
                        'field'=> array
                        (               
                            'name'=>'',         // Nom du champ
                            'type'=>'none',     // Type d'indexation (none, words, phrases, values)
                            'count'=>false,     // Ajouter un token spécial représentant le nombre de valeurs (has0, has1...)
                            'global'=>false,    // Prendre en compte cet index dans l'index 'tous champs'
                            'start'=>'',        // Position ou chaine indiquant le début du texte à indexer
                            'end'=>'',          // Position ou chain indquant la fin du texte à indexer
                            'weight'=>1         // Poids des tokens ajoutés à cet index
                        )
                    )
                )
            ),
    
            'lookuptables'=>array            // LOOKUpTABLES : LISTE DES TABLES DE LOOKUP
            (
                'lookuptable'=>array
                (
                    'name'=>'',             // Nom de la table
                    'fields'=>array         // La liste des champs qui alimentent cette table
                    (
                        'field'=>array
                        (
                            'name'=>'',         // Nom du champ
                            'start'=>'',        // Position de début ou chaine délimitant le début de la valeur à ajouter à la table
                            'end'=>''           // Longueur ou chaine délimitant la fin de la valeur à ajouter à la table
                        )
                    )
                )
            ),
    
            'aliases'=>array            // ALIASES : LISTE DES ALIAS
            (
                'alias'=>array
                (
                    'name'=>'',             // Nom de l'alias
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
                    'name'=>'',             // Nom de la clé de tri
                    'fields'=>array         // La liste des champs qui composent cette clé de tri
                    (
                        'field'=>array
                        (
                            'name'=>'',         // Nom du champ
                            'start'=>'',        // Position de début ou chaine délimitant le début de la valeur à ajouter à la clé
                            'end'=>''           // Longueur ou chaine délimitant la fin de la valeur à ajouter à la clé
                        )
                    )
                )
            )
        )
    );
    

    /**
     * Objet représentant la structure de la base
     *
     * @var StdClass
     * @access private
     */
    private $def=null;

    
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
     * @throws Exception si le type de l'argument passé en paramètre ne peut pas
     * être déterminé ou si la définition a des erreurs fatales (par exemple un
     * fichier xml mal formé)
     */
    public function __construct($def=null)
    {
        // faut-il ajouter les propriétés par défaut (oui pour tous sauf xml qui le fait déjà)
        $defaults=true;
        
        // Une structure vide
        if (is_null($def))
        {
            $this->def=new StdClass();
            $this->def->label='Nouveau modèle de structure de base de données créé le '.date('d/m/y à H:i:s');
        }
        
        // Un objet représentant la structure de la base
        elseif (is_object($def))
            $this->def=$def;
        
        // Un tableau dont les éléments représentent la structure de la base
        elseif (is_array($def))
            $this->def=(object)$def;
            
        // Une chaine de caractères contenant du xml ou du JSON
        elseif (is_string($def))
        {
            switch (substr(ltrim($def), 0, 1))
            {
                case '<': // du xml
                    $this->def=$this->fromXml($def);
                    $defaults=false;
                    break;
                    
                case '{': // du json
                    $this->def=$this->fromJson($def);
                    break;
                    
                default:
                    throw new Exception('Impossible de déterminer le type de la structure passée en paramètre');
            }
        }
        
        // Ajoute toutes les propriétés qui ne sont pas définies avec leur valeur par défaut
        if ($defaults) $this->def=self::defaults($this->def, self::$dtd['database']);
/*        
 BENCHMARK SERIALIZE/VAR_EXPORT/JSON : 
        set_time_limit(0);
        $max=1;
        echo 'Répétition ', $max, ' fois de chaque opération<br />';
        
        $start=microtime(true);
        for ($i=0; $i<$max; $i++)
            json_encode(Utils::utf8Encode($this->def));
        echo 'Encodage en json : ', microtime(true)-$start, '<br />';
        
        $json=json_encode(Utils::utf8Encode($this->def));
        
        $start=microtime(true);
        for ($i=0; $i<$max; $i++)
            Utils::utf8Decode(json_decode($json, false));
        echo 'Décodage json : ', microtime(true)-$start, '<br />';
        if (Utils::utf8Decode(json_decode($json, false)) !== $this->def)
            echo 'json_decode(def) != def <br />';
        
        
        $start=microtime(true);
        for ($i=0; $i<$max; $i++)
            serialize($this->def);
        echo 'serialize : ', microtime(true)-$start, '<br />';

        $serial=serialize($this->def);
        echo '<pre>', $serial, '</pre>';
        
        $start=microtime(true);
        for ($i=0; $i<$max; $i++)
            unserialize($serial);
        echo 'unserialize : ', microtime(true)-$start, '<br />';
        if (unserialize($serial) !== $this->def)
        {
            echo '<pre>';
            echo 'unserailize(def) != def <br />';
            echo 'Def : ';
            file_put_contents('c:/def.txt', var_export($this->def, true));
            echo '<hr />';
            echo 'unserialize:';
            file_put_contents('c:/def2.txt', var_export(unserialize($serial), true));
        }
        
        $start=microtime(true);
        for ($i=0; $i<$max; $i++)
            var_export($this->def, true);
        echo 'var_export : ', microtime(true)-$start, '<br />';
//
//        $php='$t='. var_export($this->def, true).';';
//        echo $php;
//        
//        $start=microtime(true);
//        for ($i=0; $i<$max; $i++)
//            eval($php);
//        echo 'var_export : ', microtime(true)-$start, '<br />';
        
        die('Terminé');
*/
    }


    /**
     * Retourne la structure de la base de données sous la forme d'un objet
     * contenant toutes les propriétés de la base, des champs, des index, etc.
     *
     * @return StdClass
     */
    public function getStructure()
    {
        return $this->def;    
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
    
            throw new Exception($h);
        }

        // Convertit la structure xml en tableau php
        return self::xmlToObject($xml->documentElement, self::$dtd);
    }

    
    /**
     * Fonction utilitaire utilisée par {@link fromXml()} pour convertir un 
     * source xml en objet.
     *
     * @param DOMNode $node le noeud xml à convertir
     * @param array $dtd un tableau indiquant les noeuds et attributs autorisés
     * @return StdClass
     * @throws Exception si le source xml contient des attributs ou des tags
     * non autorisés
     */
    private static function xmlToObject(DOMNode $node, array $dtd)
    {
        // Vérifie que le nom du noeud correspond au tag attendu tel qu'indiqué par le dtd
        if (count($dtd)>1)
            throw new Exception('DTD invalide : le tableau doit contenir un seul élément');
        reset($dtd);
        $tag=key($dtd);
        if ($node->tagName !== $tag)
            throw new Exception("Elément '$tag' attendu (trouvé : '$node->tagName')");
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
                    throw new Exception("La propriété $node->tagName.$name n'existe pas");
                    
                // Si la propriété est un objet, elle ne peut pas être définie sous forme d'attribut
                if (is_array($dtd[$name]))
                    throw new Exception("La propriété $node->tagName.$name doit être indiquée comme élément, pas comme attribut");
                    
                // Définit la propriété
                $result->$name=utf8_decode(trim($attribute->nodeValue));
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
                        throw new Exception("La propriété $node->tagName.$name n'existe pas");
                        
                    // Vérifie qu'on n'a pas à la fois un attribut et un élément de même nom (<database label="xxx"><label>yyy...)
                    if ($node->hasAttribute($name))
                        throw new Exception("La propriété $node->tagName.$name est définie à la fois comme attribut et comme tag");

                    // Cas d'une propriété simple (scalaire)
                    if (! is_array($dtd[$name]))
                        $result->$name=utf8_decode($child->nodeValue); // si plusieurs fois le même tag, c'est le dernier qui gagne
                    else
                    {
                        foreach($child->childNodes as $child)
                            array_push($result->$name, self::xmlToObject($child, $dtd[$name]));
                    }
                    break;

                // Types de noeud autorisés mais ignorés
                case XML_COMMENT_NODE:
                    break;
                    
                // Types de noeud interdits
                default:
                    throw new Exception('Type de noeud interdit dans un tag '.$node->tagName);
            }
        }
        return $result;
    }


    /**
     * Retourne la version xml de la structure de base de données en cours.
     * 
     * @return string
     */    
    public function toXml()
    {
        ob_start();
        echo 
            '<?xml version="1.0" encoding="iso-8859-1"?>' .
            "\n" .
            '<!-- Dernière modification : ' . date('d/m/y H:i:s') . ' -->' .
            "\n" ;
        self::nodeToXml(self::$dtd, $this->def);
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
            throw new Exception('DTD invalide : le tableau doit contenir un seul élément');
        reset($dtd);
        $tag=key($dtd);
        $dtd=array_pop($dtd);
        
        $attr=array();
        $simpleNodes=array();
        $complexNodes=array();
        
        // Parcourt toutes les propriétés pour les classer
        foreach($object as $prop=>$value)
        {
            // La propriété a la valeur par défaut indiquée dans le DTD : on l'ignore
            if(array_key_exists($prop,$dtd) && $value === $dtd[$prop])
                continue;
            
            // Valeurs scalaires (entiers, chaines, booléens...)
            if (is_scalar($value))
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
                // Ignore les tableaux vides
                if (count($value)) $complexNodes[]=$prop;
            }
        }
        
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
     * Crée la structure de la base de données à partir d'un source JSON.
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
        return Utils::utf8Decode(json_decode($json, false));
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
        return json_encode(Utils::utf8Encode($this->def));
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
        
        // Evite de répêter $this->def à chaque fois
        $def= & $this->def;
        
        // Tri et nettoyage des mots-vides
        self::stopwords($def->stopwords);
        
        // Vérifie qu'on a au moins un champ
        if (count($def->fields)===0)
            $errors[]="Structure de base incorrecte, aucun champ n'a été défini";
    
        // Tableau utilisé pour dresser la liste des champs/index/alias utilisés
        $fields=array();
        $indices=array();
        $lookuptables=array();
        $aliases=array();
        $sortkeys=array();
        
        // Vérifie la liste des champs
        foreach($def->fields as $i=>&$field)
        {
            // Vérifie que le champ a un nom correct, sans caractères interdits
            $name=trim(Utils::ConvertString($field->name, 'alphanum'));
            if ($name==='' || strpos($name, ' ')!==false)
                $errors[]="Nom incorrect pour le champ #$i : '$field->name'";
            
            // Vérifie que le nom du champ est unique
            if (isset($fields[$name]))
                $errors[]="Les champs #$i et #$fields[$name] ont le même nom";
            $fields[$name]=$i;
            
            // Tri et nettoie les mots-vides
            self::stopwords($field->stopwords);
        }
        unset($field);
        

        // Vérifie la liste des index
        foreach($def->indices as $i=>&$index)
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
                    
                // Vérifie le type de l'index
                $field->type=strtolower(trim($field->type));
                if (! in_array($field->type, array('none', 'words', 'phrases', 'values')))
                    $errors[]="Type d'indexation incorrect pour le champ #$j de l'index #$i";
                
                // Vérifie les propriétés count et global
                foreach(array('count','global') as $prop)
                {
                    if (is_string($field->$prop)) 
                        $field->$prop=strtolower(trim($field->$prop));
    
                    switch($field->$prop)
                    {
                        case true:
                        case 'true': 
                            $field->$prop=true;
                            break;
                        case false:
                        case 'false': 
                            $field->$prop=false;
                            break;
                        default:
                            $errors[]="Propriété $prop incorrecte pour le champ #$j de l'index #$i (booléen attendu)";
                    }
                }
                
                // Pour un index de type 'none', count doit être à true
                if ($field->type==='none' && !$field->count)
                    $errors[]="Le champ #$j ne sert à rien dans l'index #$i : type='none' et count='false'";
                    
                // Poids du champ
                $field->weight=trim($field->weight);
                if ($field->weight==='') $field->weight=1;
                if ((! is_int($field->weight) && !ctype_digit($field->weight)) || (1>$field->weight=(int)$field->weight))
                    $errors[]="Propriété weight incorrecte pour le champ #$j de l'index #$i (entier supérieur à zéro attendu)";
            }
            unset($field);
        }
        unset($index);


        // Vérifie la liste des tables des entrées
        foreach($def->lookuptables as $i=>&$lookuptable)
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
            }
            unset($field);
        }
        unset($lookuptable);    

        
        // Vérifie la liste des alias
        foreach($def->aliases as $i=>& $alias)
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
        foreach($def->sortkeys as $i=>& $sortkey)
        {
            // Vérifie que la clé a un nom
            $name=trim(Utils::ConvertString($sortkey->name, 'alphanum'));
            if ($name==='' || strpos($name, ' ')!==false)
                $errors[]="Nom incorrect pour la clé de tri #$i";

            // Vérifie que ce nom est unique
            if (isset($sortkeys[$name]))
                $errors[]="Les clés de tri #$i et #$sortkeys[$name] ont le même nom";
            $sortkeys[$name]=$i;
            
            // Vérifie que la clé a au moins un index
            if (count($sortkey->fields)===0)
                $errors[]="Aucun champ n'a été indiqué pour la clé de tri #$i ($sortkey->name)";
            else foreach ($sortkey->fields as $j=>&$field)
            {
                // Vérifie que le champ indiqué existe
                $name=trim(Utils::ConvertString($field->name, 'alphanum'));
                if (!isset($fields[$name]))
                    $errors[]="Nom de champ inconnu dans la clé de tri #$i : '$name'";
            }
            unset($field);
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
    private function stopwords(& $stopwords)
    {
        // todo: convertir en caractères minuscules non accentués
        $t=preg_split('~\s~', $stopwords, -1, PREG_SPLIT_NO_EMPTY);
        sort($t);
        $stopwords=implode(' ', $t);    
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
    // TODO : not used here, à transférer dans Utils ?
    public static function merge($a, $b /* ... */)
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


    // TODO: doc à faire
    public static function defaults($object, $dtd)
    {
        foreach ($dtd as $prop=>$value)
        {
            if (! property_exists($object, $prop)) 
                $object->$prop=(is_array($value) ? array() : $value);
            elseif (is_array($object->$prop))
            {
                foreach ($object->$prop as $i=>&$item)
                    $item=self::defaults($item, $value);
                unset($item);
            }
        }
        return $object;
    }
    
    /**
     * Complète la structure en cours pour
     * 
     * Pour chaque champ :
     * - attribue un identifiant unique (field->id) pour les champs qui n'en 
     * ont pas encore. Actuellement, cet identifiant est un simple numéro, mais
     * c'est susceptible de changer. Remarque : les numéros ne sont pas 
     * obligatoirement consécutifs (par exemple si on a supprimé un champ, son 
     * ID ne sera pas réutilisé). 
     * 
     * Pour chaque index :
     * - attribue un identifiant unique (index->id) si l'index n'en a pas encore.
     * Cet identifiant correspond au préfixe qui sera ajouté aux tokens lors de 
     * l'indexation du champ. Actuellement, il s'agit d'un numéro entier suivi
     * du caractère ':', mais c'est susceptible de changer. 
     * - traduit les propriétés 'type' et 'count' de l'index (string) en entier
     * (typeAsInt)
     *  
     * Pour chaque table des entrées :
     * - attribue un identifiant unique (entry->id) si la table n'en a pas encore.
     * Cet identifiant correspond au préfixe qui sera ajouté aux entrées 
     * composant la table lors de l'indexation du champ. Actuellement, il s'agit
     * de la lettre 'T' suivie d'un numéro entier puis du caractère ':'.
     * 
     * La fonction crée également les propriétés suivantes :
     * 
     * - fieldById : un tableau qui pour un champ dont on connaît l'identifiant
     * donne le nom du champ tel que défini par l'utilisateur. Ce tableau garde
     * la trace de tous les identifiants qui ont un jour été utilisés. Si on
     * supprime un champ de la base, le champ est supprimé mais son identifiant 
     * reste dans le tableau. Si on crée ensuite un nouveau champ on utilisera 
     * le tableau pour attribuer un identifiant dont on est certain qu'il n'a 
     * jamais été utilisé. Dans le tableau, la valeur associée à un identifiant
     * qui n'est plus utilisé est une chaine vide.
     * 
     * fieldByName ?
     * 
     * - indexByName : un tableau qui pour un index ou un alias dont on connaît
     * le nom tel que défini par l'utilisateur indique l'identifiant du ou des
     * index correspondant. Pour chaque élément du tableau, s'il s'agit d'un 
     * index simple, la valeur contient directement l'identifiant, dans le cas 
     * contraire (un alias), la valeur est un tableau listant les identifiants 
     * de chacun des index de cet alias.
     * Ce tableau est notamment utilisé lors d'une recherche pour traduire les 
     * noms d'index indiqués par l'utilisateur en préfixes (aut=ménard -> 8:menard) 
     * 
     * indexById ?
     * 
     * - entryByName : un tableau qui pour une table des entrées dont on connaît
     * le nom tel que définit par l'utilisateur indique l'identifiant 
     * correspondant.
     * 
     * entryById ?
     */
    public function compile()
    {

/*
champs à ajouter dans la structure :
db.lastFieldId  
db.lastIndexId

field.id
*/        
        // Raccourci pour éviter de taper $this->def à chaque fois 
        $def=& $this->def;

        // Réinitialise les valeurs de fieldById (mais garde les clés) pour éliminer les champ supprimés
        $def->fieldById=array_fill_keys(array_keys($def->fieldById), null);
        
        foreach($def->field as & $field)
        {
            // Si le champ n'a pas encore d'identifiant, on lui en attribue un
            if (! $field->id)    
                $field->id=$def->lastFieldId++;
            
            // S'il s'agit d'un nouveau champ ou si son nom a changé, met à jour fieldById
            $def->fieldById[$id]=$field->name;
            
            foreach($field->index as $index)
            {
                // Si l'index n'a pas encore d'identifiant, on lui en attribue un
                if (! $index->id)
                    $index->id=$def->lastIndexId++;
                    
            }
        }
    }
}
?>