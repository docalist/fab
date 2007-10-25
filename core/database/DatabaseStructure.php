<?php
/**
 * @package     fab
 * @subpackage  database
 * @author      Daniel M�nard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id
 */

/**
 * Classe permettant de manipuler la structure d'une base de donn�es.
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
            'sep'=>'',                  // NUPLM, � virer
            'stopwords'=>'',            // Liste par d�faut des mots-vides � ignorer lors de l'indexation
    
            'fields'=>array             // FIELDS : LISTE DES CHAMPS DE LA BASE
            (
                'field'=>array          
                (
                    'name'=>'',             // Nom du champ, d'autres noms peuvent �tre d�finis via des alias
                    'type'=>'text',         // Type du champ (juste � titre d'information, non utilis� pour l'instant)
                    'label'=>'',            // Libell� du champ
                    'description'=>'',      // Description
                    'stopwords'=>'',        // Liste sp�cifique de mots-vides � appliquer � ce champ
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
                            'count'=>false,     // Ajouter un token sp�cial repr�sentant le nombre de valeurs (has0, has1...)
                            'global'=>false,    // Prendre en compte cet index dans l'index 'tous champs'
                            'start'=>'',        // Position ou chaine indiquant le d�but du texte � indexer
                            'end'=>'',          // Position ou chain indquant la fin du texte � indexer
                            'weight'=>1         // Poids des tokens ajout�s � cet index
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
                            'start'=>'',        // Position de d�but ou chaine d�limitant le d�but de la valeur � ajouter � la table
                            'end'=>''           // Longueur ou chaine d�limitant la fin de la valeur � ajouter � la table
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
                    'name'=>'',             // Nom de la cl� de tri
                    'fields'=>array         // La liste des champs qui composent cette cl� de tri
                    (
                        'field'=>array
                        (
                            'name'=>'',         // Nom du champ
                            'start'=>'',        // Position de d�but ou chaine d�limitant le d�but de la valeur � ajouter � la cl�
                            'end'=>''           // Longueur ou chaine d�limitant la fin de la valeur � ajouter � la cl�
                        )
                    )
                )
            )
        )
    );
    

    /**
     * Objet repr�sentant la structure de la base
     *
     * @var StdClass
     * @access private
     */
    private $def=null;

    
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
     * @throws Exception si le type de l'argument pass� en param�tre ne peut pas
     * �tre d�termin� ou si la d�finition a des erreurs fatales (par exemple un
     * fichier xml mal form�)
     */
    public function __construct($def=null)
    {
        // faut-il ajouter les propri�t�s par d�faut (oui pour tous sauf xml qui le fait d�j�)
        $defaults=true;
        
        // Une structure vide
        if (is_null($def))
        {
            $this->def=new StdClass();
            $this->def->label='Nouveau mod�le de structure de base de donn�es cr�� le '.date('d/m/y � H:i:s');
        }
        
        // Un objet repr�sentant la structure de la base
        elseif (is_object($def))
            $this->def=$def;
        
        // Un tableau dont les �l�ments repr�sentent la structure de la base
        elseif (is_array($def))
            $this->def=(object)$def;
            
        // Une chaine de caract�res contenant du xml ou du JSON
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
                    throw new Exception('Impossible de d�terminer le type de la structure pass�e en param�tre');
            }
        }
        
        // Ajoute toutes les propri�t�s qui ne sont pas d�finies avec leur valeur par d�faut
        if ($defaults) $this->def=self::defaults($this->def, self::$dtd['database']);
/*        
 BENCHMARK SERIALIZE/VAR_EXPORT/JSON : 
        set_time_limit(0);
        $max=1;
        echo 'R�p�tition ', $max, ' fois de chaque op�ration<br />';
        
        $start=microtime(true);
        for ($i=0; $i<$max; $i++)
            json_encode(Utils::utf8Encode($this->def));
        echo 'Encodage en json : ', microtime(true)-$start, '<br />';
        
        $json=json_encode(Utils::utf8Encode($this->def));
        
        $start=microtime(true);
        for ($i=0; $i<$max; $i++)
            Utils::utf8Decode(json_decode($json, false));
        echo 'D�codage json : ', microtime(true)-$start, '<br />';
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
        
        die('Termin�');
*/
    }


    /**
     * Retourne la structure de la base de donn�es sous la forme d'un objet
     * contenant toutes les propri�t�s de la base, des champs, des index, etc.
     *
     * @return StdClass
     */
    public function getStructure()
    {
        return $this->def;    
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
    
            throw new Exception($h);
        }

        // Convertit la structure xml en tableau php
        return self::xmlToObject($xml->documentElement, self::$dtd);
    }

    
    /**
     * Fonction utilitaire utilis�e par {@link fromXml()} pour convertir un 
     * source xml en objet.
     *
     * @param DOMNode $node le noeud xml � convertir
     * @param array $dtd un tableau indiquant les noeuds et attributs autoris�s
     * @return StdClass
     * @throws Exception si le source xml contient des attributs ou des tags
     * non autoris�s
     */
    private static function xmlToObject(DOMNode $node, array $dtd)
    {
        // V�rifie que le nom du noeud correspond au tag attendu tel qu'indiqu� par le dtd
        if (count($dtd)>1)
            throw new Exception('DTD invalide : le tableau doit contenir un seul �l�ment');
        reset($dtd);
        $tag=key($dtd);
        if ($node->tagName !== $tag)
            throw new Exception("El�ment '$tag' attendu (trouv� : '$node->tagName')");
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
                    throw new Exception("La propri�t� $node->tagName.$name n'existe pas");
                    
                // Si la propri�t� est un objet, elle ne peut pas �tre d�finie sous forme d'attribut
                if (is_array($dtd[$name]))
                    throw new Exception("La propri�t� $node->tagName.$name doit �tre indiqu�e comme �l�ment, pas comme attribut");
                    
                // D�finit la propri�t�
                $result->$name=utf8_decode(trim($attribute->nodeValue));
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
                        throw new Exception("La propri�t� $node->tagName.$name n'existe pas");
                        
                    // V�rifie qu'on n'a pas � la fois un attribut et un �l�ment de m�me nom (<database label="xxx"><label>yyy...)
                    if ($node->hasAttribute($name))
                        throw new Exception("La propri�t� $node->tagName.$name est d�finie � la fois comme attribut et comme tag");

                    // Cas d'une propri�t� simple (scalaire)
                    if (! is_array($dtd[$name]))
                        $result->$name=utf8_decode($child->nodeValue); // si plusieurs fois le m�me tag, c'est le dernier qui gagne
                    else
                    {
                        foreach($child->childNodes as $child)
                            array_push($result->$name, self::xmlToObject($child, $dtd[$name]));
                    }
                    break;

                // Types de noeud autoris�s mais ignor�s
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
     * Retourne la version xml de la structure de base de donn�es en cours.
     * 
     * @return string
     */    
    public function toXml()
    {
        ob_start();
        echo 
            '<?xml version="1.0" encoding="iso-8859-1"?>' .
            "\n" .
            '<!-- Derni�re modification : ' . date('d/m/y H:i:s') . ' -->' .
            "\n" ;
        self::nodeToXml(self::$dtd, $this->def);
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
            throw new Exception('DTD invalide : le tableau doit contenir un seul �l�ment');
        reset($dtd);
        $tag=key($dtd);
        $dtd=array_pop($dtd);
        
        $attr=array();
        $simpleNodes=array();
        $complexNodes=array();
        
        // Parcourt toutes les propri�t�s pour les classer
        foreach($object as $prop=>$value)
        {
            // La propri�t� a la valeur par d�faut indiqu�e dans le DTD : on l'ignore
            if(array_key_exists($prop,$dtd) && $value === $dtd[$prop])
                continue;
            
            // Valeurs scalaires (entiers, chaines, bool�ens...)
            if (is_scalar($value))
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
                // Ignore les tableaux vides
                if (count($value)) $complexNodes[]=$prop;
            }
        }
        
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
     * Cr�e la structure de la base de donn�es � partir d'un source JSON.
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
        return Utils::utf8Decode(json_decode($json, false));
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
        return json_encode(Utils::utf8Encode($this->def));
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
        
        // Evite de r�p�ter $this->def � chaque fois
        $def= & $this->def;
        
        // Tri et nettoyage des mots-vides
        self::stopwords($def->stopwords);
        
        // V�rifie qu'on a au moins un champ
        if (count($def->fields)===0)
            $errors[]="Structure de base incorrecte, aucun champ n'a �t� d�fini";
    
        // Tableau utilis� pour dresser la liste des champs/index/alias utilis�s
        $fields=array();
        $indices=array();
        $lookuptables=array();
        $aliases=array();
        $sortkeys=array();
        
        // V�rifie la liste des champs
        foreach($def->fields as $i=>&$field)
        {
            // V�rifie que le champ a un nom correct, sans caract�res interdits
            $name=trim(Utils::ConvertString($field->name, 'alphanum'));
            if ($name==='' || strpos($name, ' ')!==false)
                $errors[]="Nom incorrect pour le champ #$i : '$field->name'";
            
            // V�rifie que le nom du champ est unique
            if (isset($fields[$name]))
                $errors[]="Les champs #$i et #$fields[$name] ont le m�me nom";
            $fields[$name]=$i;
            
            // Tri et nettoie les mots-vides
            self::stopwords($field->stopwords);
        }
        unset($field);
        

        // V�rifie la liste des index
        foreach($def->indices as $i=>&$index)
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
                    
                // V�rifie le type de l'index
                $field->type=strtolower(trim($field->type));
                if (! in_array($field->type, array('none', 'words', 'phrases', 'values')))
                    $errors[]="Type d'indexation incorrect pour le champ #$j de l'index #$i";
                
                // V�rifie les propri�t�s count et global
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
                            $errors[]="Propri�t� $prop incorrecte pour le champ #$j de l'index #$i (bool�en attendu)";
                    }
                }
                
                // Pour un index de type 'none', count doit �tre � true
                if ($field->type==='none' && !$field->count)
                    $errors[]="Le champ #$j ne sert � rien dans l'index #$i : type='none' et count='false'";
                    
                // Poids du champ
                $field->weight=trim($field->weight);
                if ($field->weight==='') $field->weight=1;
                if ((! is_int($field->weight) && !ctype_digit($field->weight)) || (1>$field->weight=(int)$field->weight))
                    $errors[]="Propri�t� weight incorrecte pour le champ #$j de l'index #$i (entier sup�rieur � z�ro attendu)";
            }
            unset($field);
        }
        unset($index);


        // V�rifie la liste des tables des entr�es
        foreach($def->lookuptables as $i=>&$lookuptable)
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
            }
            unset($field);
        }
        unset($lookuptable);    

        
        // V�rifie la liste des alias
        foreach($def->aliases as $i=>& $alias)
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
        foreach($def->sortkeys as $i=>& $sortkey)
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
                $name=trim(Utils::ConvertString($field->name, 'alphanum'));
                if (!isset($fields[$name]))
                    $errors[]="Nom de champ inconnu dans la cl� de tri #$i : '$name'";
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
    private function stopwords(& $stopwords)
    {
        // todo: convertir en caract�res minuscules non accentu�s
        $t=preg_split('~\s~', $stopwords, -1, PREG_SPLIT_NO_EMPTY);
        sort($t);
        $stopwords=implode(' ', $t);    
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
    // TODO : not used here, � transf�rer dans Utils ?
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


    // TODO: doc � faire
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
     * Compl�te la structure en cours pour
     * 
     * Pour chaque champ :
     * - attribue un identifiant unique (field->id) pour les champs qui n'en 
     * ont pas encore. Actuellement, cet identifiant est un simple num�ro, mais
     * c'est susceptible de changer. Remarque : les num�ros ne sont pas 
     * obligatoirement cons�cutifs (par exemple si on a supprim� un champ, son 
     * ID ne sera pas r�utilis�). 
     * 
     * Pour chaque index :
     * - attribue un identifiant unique (index->id) si l'index n'en a pas encore.
     * Cet identifiant correspond au pr�fixe qui sera ajout� aux tokens lors de 
     * l'indexation du champ. Actuellement, il s'agit d'un num�ro entier suivi
     * du caract�re ':', mais c'est susceptible de changer. 
     * - traduit les propri�t�s 'type' et 'count' de l'index (string) en entier
     * (typeAsInt)
     *  
     * Pour chaque table des entr�es :
     * - attribue un identifiant unique (entry->id) si la table n'en a pas encore.
     * Cet identifiant correspond au pr�fixe qui sera ajout� aux entr�es 
     * composant la table lors de l'indexation du champ. Actuellement, il s'agit
     * de la lettre 'T' suivie d'un num�ro entier puis du caract�re ':'.
     * 
     * La fonction cr�e �galement les propri�t�s suivantes :
     * 
     * - fieldById : un tableau qui pour un champ dont on conna�t l'identifiant
     * donne le nom du champ tel que d�fini par l'utilisateur. Ce tableau garde
     * la trace de tous les identifiants qui ont un jour �t� utilis�s. Si on
     * supprime un champ de la base, le champ est supprim� mais son identifiant 
     * reste dans le tableau. Si on cr�e ensuite un nouveau champ on utilisera 
     * le tableau pour attribuer un identifiant dont on est certain qu'il n'a 
     * jamais �t� utilis�. Dans le tableau, la valeur associ�e � un identifiant
     * qui n'est plus utilis� est une chaine vide.
     * 
     * fieldByName ?
     * 
     * - indexByName : un tableau qui pour un index ou un alias dont on conna�t
     * le nom tel que d�fini par l'utilisateur indique l'identifiant du ou des
     * index correspondant. Pour chaque �l�ment du tableau, s'il s'agit d'un 
     * index simple, la valeur contient directement l'identifiant, dans le cas 
     * contraire (un alias), la valeur est un tableau listant les identifiants 
     * de chacun des index de cet alias.
     * Ce tableau est notamment utilis� lors d'une recherche pour traduire les 
     * noms d'index indiqu�s par l'utilisateur en pr�fixes (aut=m�nard -> 8:menard) 
     * 
     * indexById ?
     * 
     * - entryByName : un tableau qui pour une table des entr�es dont on conna�t
     * le nom tel que d�finit par l'utilisateur indique l'identifiant 
     * correspondant.
     * 
     * entryById ?
     */
    public function compile()
    {

/*
champs � ajouter dans la structure :
db.lastFieldId  
db.lastIndexId

field.id
*/        
        // Raccourci pour �viter de taper $this->def � chaque fois 
        $def=& $this->def;

        // R�initialise les valeurs de fieldById (mais garde les cl�s) pour �liminer les champ supprim�s
        $def->fieldById=array_fill_keys(array_keys($def->fieldById), null);
        
        foreach($def->field as & $field)
        {
            // Si le champ n'a pas encore d'identifiant, on lui en attribue un
            if (! $field->id)    
                $field->id=$def->lastFieldId++;
            
            // S'il s'agit d'un nouveau champ ou si son nom a chang�, met � jour fieldById
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