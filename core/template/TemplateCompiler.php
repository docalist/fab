<?php

/*
TODO: le if et le collapse sont redondants. Virer le if, gérer correctement le collapse
A faire :
- améliorer le système de bindings pour les cas où il s'agit d'un callback, 
d'une propriété d'objet, etc.
- ne pas générer Template::Filled($x) si on n'est pas dans un bloc opt
- ne pas génèrer ($x=$key) ? Template::Filled($x) : '' si on n'a qu'une
seule variable
- une variable, pas de opt : echo $titre
- une variable, dans un opt : echo Filled($x);  (filled retourne ce qu'on lui passe)
- deux vars, pas de opt : echo (($x=var1) or ($x=var2) ? $x : void)
- sortie de boucle : les variables datasource ont été écrasées (bindings)
- tester le tag ignore, autoriser autre chose que 'true'
- à étudier : générer des variables v1, v2... plutôt que le vrai nom (pas d'écrasement)
- autoriser des variables de variables (l'équivalent de [prix[i]] = ?)
*/

/**
 * @package     fab
 * @subpackage  template
 * @author      Daniel Ménard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id$
 */


require_once(dirname(__FILE__).'/TemplateCode.php');
require_once(dirname(__FILE__).'/TemplateEnvironment.php');

/**
 * Compilateur de templates
 * 
 * Le compilateur est basé sur un parser xml. Si le remplate n'est pas un fichier xml
 * on ajoute une déclaration xml et un tag racine pour qu'il le devienne. 
 * Quelques transformations sont ensuite opérées sur le source xml obtenu (pour le 
 * moment uniquement transformation des templates match). 
 * Le source obtenu est ensuite chargé dans le parser. La compilation consiste alors 
 * simplement à faire un parcourt de l'arbre obtenu en générant à chaque fois le code 
 * nécessaire (cf {@link compileNode()}). Pour chacun des tags de notre langage (if, 
 * loop, switch...) compileNode() appelle la fonction correspondante (cf {@link compileIf()}, 
 * {@link CompileLoop()}, {@link CompileSwitch()}, ...).
 * Le code est généré par de simples echos. L'ensemble de la sortie est bufferisé pour être
 * retourné à l'appellant. 
 */
class TemplateCompiler
{
    const PHP_START_TAG='<?php ';
    const PHP_END_TAG="?>";

    /**
     * @var int Niveau d'imbrication des blocs <opt>...</opt> rencontrés durant
     * la compilation. Utilisé pour optimiser la façon dont les variables sont
     * compilées (pas de Template::filled($x) si on n'est pas dans un bloc opt)
     * 
     * @access private
     */
    private static $opt=0;

    /**
     * @var int Niveau d'imbrication des blocs <loop>...</loop> rencontrés durant
     * la compilation. Utilisé pour attribuer des variables de boucles différentes
     * à chaque niveau.
     * 
     * @access private
     */
    private static $loop=0;
    
    private static $stack=array();
    
    private static $functions=array
    (
        'autoId'=>'getAutoId',
        'lastId'=>'getLastId',
        'select'=>'executeSelect'
    );
    
    private static $currentNode=null;
    private static $lastId='';
    private static $usedId=array();


    /**
     * @staticvar string Expression régulière utilisée pour trouver les variables et les expressions présentes dans le source du template
     * @access private
     */
    public static $reCode=
        '~
                                            # SOIT une variable

                (?<!\\\\)                   # si on a un antislash devant le dollar, on ignore 
                \$                          # le signe dollar
                [a-zA-Z][a-zA-Z0-9_]*       # un identifiant : lettres+chiffres+underscore

            |                               # SOIT une expression entre accolades

                (?<!\\\\)                   # si on a un antislash devant le signe "{" , on ignore 
                \{                          # une accolade ouvrante
                .*?                         # toute suite de caractères 
                (?<!\\\\)                   # si le "}" est précédé de antislash, on ignore
                \}                          # le "}" fermant
        ~x';


    /**
     * @staticvar DOMNodeList Lorsqu'un template match contient un appel à la pseudo fonction select(), les noeuds
     * sélectionnés sont stockés dans $selectNodes
     */
    private static $selectNodes=null;

    private static $env;    // TemplateEnvironment 
    
    public static function autoId()
    {
        $node=self::$currentNode;
        for(;;)
        {
            if (is_null($node) ) break; //or !($node instanceof DOMElement)
            if ($node instanceof DOMElement)    // un DOMText, par exemple, n'a pas d'attributs
                if ($h=$node->getAttribute('id') or $h=$node->getAttribute('name')) break;
        	$node=$node->parentNode;
        }
        if (!$h)
            $h=self::$currentNode->tagName;
        if (isset(self::$usedId[$h]))
            $h=$h.(++self::$usedId[$h]);
        else
            self::$usedId[$h]=1;
        
        return self::$lastId=$h;
    }
    
    public static function lastId()
    {
        return self::$lastId;   
    }
    
    /**
     * Compile un template 
     * 
     * Génère une exception si le template est mal formé ou contient des erreurs.
     * 
     * @param string $source le code source du template à compiler
     * @param array l'environnement d'exécution du template
     * 
     * @return string le code php du template compilé
     */
    public static function compile($source, $env=null)
    {
        //self::addCodePosition($source);
        
        // Supprime les commentaires de templates : /* xxx */
        $source=preg_replace('~/\*.*?\*/~ms', null, $source);
        
        // Ajoute si nécessaire une déclaration xml au template
        if (substr($source, 0, 6)==='<?xml ')
        {
            $xmlDeclaration=strtok($source, '>').'>';
        }
        else
        {
            $xmlDeclaration='';  
            $source='<?xml version="1.0" encoding="ISO-8859-1" ?>' . $source;
        }

        // Expression régulière utilisée pour déterminer la fin du prologue du fichier xml
        // on sélectionne tout ce qui commence par <! ou <? suivis d'espaces
        $re='~^(?:\<[?!][^>]*>\s*)*~';

        // Ajoute une racine <root>...</root> au template
        $source=preg_replace($re, '$0<root collapse="true">', $source, 1);
        $source.='</root>';
        
        // Ajoute les fichiers auto-include dans le source
        $files=Config::get('templates.autoinclude');

        // TODO: tester que le fichier existe et est lisible et générer une exception sinon 
        $h='';
        foreach((array)$files as $file)
        	$h.=file_get_contents(Runtime::$fabRoot.'core/template/autoincludes/'.$file);

        if ($h) $source=str_replace('</root>', '<div ignore="true">'.$h.'</div></root>', $source);
//        if (Template::getLevel()==0) file_put_contents(dirname(__FILE__).'/dm.xml', $source);

        // Crée un document XML
        $xml=new domDocument();

        if (Config::get('templates.removeblanks'))
            $xml->preserveWhiteSpace=false; // à true par défaut
//$xml->strictErrorChecking=true;
        if (Config::get('templates.resolveexternals'))
        {
            $xml->resolveExternals=true;
            $catalog='XML_CATALOG_FILES=' . dirname(__FILE__) . '/xmlcatalog/catalog.xml';
            putenv($catalog);
        }
                    
        // gestion des erreurs : voir comment 1 à http://fr.php.net/manual/en/function.dom-domdocument-loadxml.php
        libxml_clear_errors(); // >PHP5.1
        libxml_use_internal_errors(true);// >PHP5.1

//echo 'Source xml à compiler :<pre>';
//echo htmlentities($source);
//echo '</pre>';
        
        if (! $xml->loadXML($source)) // options : >PHP5.1
        {
            $h="Impossible de compiler le template, ce n'est pas un fichier xml valide :<br />\n"; 
            foreach (libxml_get_errors() as $error)
            	$h.= "- ligne $error->line, colonne $error->column : $error->message<br />\n";

            throw new Exception($h);
        }
        unset($source);
//        self::walkDOM($xml);
//        die();
//self::collapseNodes($xml);
//        self::dumpNodes($xml, 'Source original');

        // Instancie tous les templates présents dans le document
       self::compileMatches($xml);        
//echo 'Après instanciation des templates match :<pre>';
//echo htmlentities($xml->saveXml());
//echo '</pre>';

//        if (Template::getLevel()==2) 
//        {
//            $ret=$xml->save(dirname(__FILE__).'/aftermatch.xml');
//            file_put_contents(__FILE__ . '.trace2', print_r(debug_backtrace(),true));
//            if ($ret===false)
//                echo "IMPOSSIBLE DE SAUVER LE XML";
//            else
//                echo "SAVED";	
//        }

//die();
        // Normalize le document
//        $xml->normalize();        
        self::removeEmptyTextNodes($xml->documentElement);

        // Lance la compilation
        self::$stack[]=array(self::$loop, self::$opt, self::$env);
        self::$loop=self::$opt=0;
        self::$env=new TemplateEnvironment($env);
        ob_start();
        if ($xmlDeclaration) echo $xmlDeclaration, "\n";
        self::compileChildren($xml); //->documentElement
        $result=ob_get_clean();
        
        // Fusionne les blocs php adjacents en un seul bloc php  
        self::mergePhpBlocks($result);
        
//        $result=str_replace(self::PHP_END_TAG."\n", self::PHP_END_TAG."\n\n", $result);

     // Nettoyage
     // si la balise de fin de php est \r, elle est mangée (cf http://fr2.php.net/manual/fr/language.basic-syntax.instruction-separation.php)
        $result=str_replace(self::PHP_END_TAG."\r", self::PHP_END_TAG."\r\r", $result);
        $result=str_replace(self::PHP_END_TAG."\n", self::PHP_END_TAG."\n\r", $result);
        
        $h=self::PHP_START_TAG ."\n\n";
        $name=uniqid('tpl_');
        $h.="$name();\n\nfunction $name()\n{\n\n";                
        
        $h.=self::$env->getBindings();
        
        $h.="\n".self::PHP_END_TAG;
        $result = $h.$result;
        $result.=self::PHP_START_TAG . '}' . self::PHP_END_TAG;                

        list(self::$loop, self::$opt, self::$env)=array_pop(self::$stack);
        return $result;
    }

    /**
     * Fusionne les blocs php adjacents en un seul bloc php
     */  
    public static function mergePhpBlocks(& $source)
    {
    	return; // désactivé pour le moment, à étudier de plus près
        $endStart=preg_quote(self::PHP_END_TAG.self::PHP_START_TAG, '~');
        $search=array
        (
            // un bloc echo suivi d'un bloc echo
            '~(echo [^;]+?);?'.$endStart.'echo ~',

            // un bloc php se terminant par un point-virgule et suivi d'un autre bloc php
            '~;'.$endStart.'~',

            // cas générique : un bloc php quivi d'un autre
            '~'.$endStart.'~'
        );

        $replace=array
        (
            '$1,',
            ';',
            ';'
        );

        $source=preg_replace
        (
            $search,
            $replace,
            $source
        );
    }

    /**
     * Ajoute devant chaque variable ($xxx) et expression ({xxx}) du source de template 
     * passé en paramètre un appel à la pseudo fonction setCurrentPosition permettant, lors
     * du traitement des expressions de connaître la position (ligne,colonne) de cette expression
     * au sein du fichier source.
     * 
     * @param string $template le source du template à modifier
     * @return string le template dotté des informations de position 
     */
    public static function addCodePosition(& $template)
    {
        $lines=explode("\n",$template);
        foreach($lines as $line=> & $text)
            if (preg_match_all(TemplateCompiler::$reCode, $text, $matches, PREG_PATTERN_ORDER|PREG_OFFSET_CAPTURE)>0)
                foreach(array_reverse($matches[0]) as $match)
                    $text=substr_replace($text, '{setCurrentPosition('.($line+1).','.($match[1]+1).')}',$match[1],0);   

        $template=implode("\n", $lines);
    }
    
    /**
     * Supprime d'un arbre xml les informations de positions ajoutées au source
     * par la fonction {@link addCodePosition()}
     * 
     * @param striong|DOMNode $template le template à modifier. Il peut s'agir soit d'une chaine de 
     * caractères contenant le source du template, soit de l'arbre XML du template (DOMNode)
     * 
     * @return void
     */
    public static function removeCodePosition(& $template)
    {
        if (! $template instanceof DOMNode)
        {
            $template=preg_replace('~{setCurrentPosition\(\d+,\d+\)}~','',$template);
            return;
        }
        
        if ($template instanceof DOMCharacterData || $template instanceof DOMProcessingInstruction) // #text, #comment...
            $template->data=preg_replace('~{setCurrentPosition\(\d+,\d+\)}~','',$template->data);
        
        if ($template->hasAttributes())
            foreach ($template->attributes as $name=>$attribute)
                self::removeCodePosition($attribute);

        if ($template->hasChildNodes())
            foreach ($template->childNodes as $child)
                self::removeCodePosition($child);
    }
    
    private static function WalkDOM(DOMNode $node)
    {
        echo self::nodeType($node), '(', $node->nodeType, ') ';
//        echo ' nodeValue=', htmlentities($node->nodeValue);
        echo htmlentities($node->nodeName);
        
        if ($node instanceof DOMCharacterData || $node instanceof DOMProcessingInstruction) // #text, #comment...
        {
            echo ' VALUE: [', htmlentities($node->data), ']<br />';
            echo ' parent : ', self::nodeType($node->parentNode), '<br />';
        }
        
        if ($node->hasAttributes())
        {
            foreach ($node->attributes as $name=>$attribute)
            {
                echo '<blockquote>attrs';
                self::walkDOM($attribute);
                echo '</blockquote>';
            }
//            	echo ' ', $name, '="', $attribute->value, '"';
        }	
        echo "<br />";

        if ($node->hasChildNodes())
            foreach ($node->childNodes as $child)
            {
                echo '<blockquote>childs';
                self::walkDOM($child);
                echo '</blockquote>';
            }
    }
    
    private static function dumpNodes($node, $title='')
    {
        echo "<fieldset>\n";
        if ($title) echo "<legend>$title</legend>\n";
        echo '<pre>';
        if (is_scalar($node))
        {
            echo htmlentities($node);
        }
        elseif ($node instanceof DOMNodeList)
        {
            foreach($node as $index=>$n)
                echo "nodeList($index) : ",self::nodeType($n)," <code>", htmlentities($n->ownerDocument->saveXml($n)), "</code>\n";
        }
        else
        {
            echo self::nodeType($node)," : ";
            if ($node->nodeType===XML_DOCUMENT_NODE)
                echo htmlentities($node->saveXml());
            else 
                echo htmlentities($node->ownerDocument->saveXml($node));
        } 
        echo '</pre>';
        echo "</fieldset>\n";
    }

    private static function removeEmptyTextNodes(DOMElement $node)
    {
        if (!$node->hasChildNodes()) return;
        $child=$node->firstChild;
        while (! is_null($child))
        {
            $nextChild=$child->nextSibling;
            switch ($child->nodeType)
            {
                case XML_TEXT_NODE:
//                    if ($child->isWhitespaceInElementContent())
                    if ($child->wholeText==='')
                        $node->removeChild($child);
                    break;
                    
                case XML_ELEMENT_NODE:
                    self::removeEmptyTextNodes($child);
                    break;
                case XML_COMMENT_NODE:
                case XML_PI_NODE:
                case XML_CDATA_SECTION_NODE:
                    break;
                default:
                    //echo __METHOD__, "type de noeud non géré : ", $node->nodeType, '(', self::nodeType($node),')';
            }
            $child=$nextChild;
        }
    }
    
private static $matchNode=null;
private static $matchTemplate=null;

    /**
     * Compile les templates match présents dans le document
     * 
     * La fonction récupère tous les templates présents dans le document
     * (c'est à dire les noeuds ayant un attribut match="xxx") et instancie tous
     * les noeuds du document qui correspondent
     * 
     * @param DOMDocument $xml le document xml à traiter
     * @access private
     */
    public static function compileMatches(DOMDocument $xml) // public : uniquement pour les tests unitaires
    {
        // Crée la liste des templates match (tous les noeuds qui ont un attribut match="xxx")
        $xpath=new DOMXPath($xml);
        $templates=$xpath->query('//template'); // , $xml->documentElement
        if ($templates->length ==0) return;

        // On enlève tous les templates du document xml
        // - pour qu'ils n'apparaissent pas dans la sortie générée
        // - pour que le code d'un template ne soit pas modifié par un autre template        
        foreach($templates as $template)
            $template->parentNode->removeChild($template);

        // Exécute chaque template dans l'ordre
        foreach($templates as $template)
        {
            // Récupère l'expression xpath du template
            $expression=$template->getAttribute('match');
            if ($expression==='')
                throw new Exception
                (
                    "L'attribut match d'un tag template est obligatoire" .
                    htmlentities($template->ownerDocument->saveXml($template))
                );
                    
            // Exécute la requête xpath pour obtenir la liste des noeuds sélectionnés par ce template
            if (false === $nodes=$xpath->query($expression))
                throw new Exception("Erreur dans l'expression xpath [$expression]");
                
            // Aucun résultat : rien à faire
            if ($nodes->length==0) 
                continue;	   

            // Remplace chacun des noeuds sélectionnés par la version instanciée du template
            foreach($nodes as $node)
            {
                // Clone le template pour créer le noeud résultat 
                $result=$template->cloneNode(true);

                // Stocke le template et le noeud en cours d'instanciation (utilisé par select())
                self::$matchNode=$node;
                self::$matchTemplate=$template;
                
                // Instancie le noeud
                self::instantiateMatch($result);
                
                // Ajoute un attribut collapse pour que seul le corps du template apparaisse dans le source généré
                $result->setAttribute('collapse','true');
                
                // Remplace le noeud instancié par le noeud obtenu
                $node->parentNode->replaceChild($result, $node); // remplace match par node
            }
        }
    }
    
    public static function handleMatchVar(& $var)
    {
        // Enlève le signe $ de début
        $attr=substr($var,1);
        
        // Regarde si le template match a un attribut portant ce nom
        if (self::$matchTemplate->hasAttribute($attr))
        {
            // Si l'appellant a spécifié une valeur, on la prends
            if (self::$matchNode->hasAttribute($attr))
                $var=self::$matchNode->getAttribute($attr);
                
            // Sinon on prends la valeur par défaut du template
            else
                $var=self::$matchTemplate->getAttribute($attr);
            
                
            return false;
        }

        // IDEA : si on voulait, on pourrait rendre accessibles tous les attributs de l'appellant
        // sous forme de variables , que ceux-ci soient ou non des attributs du template. 
        // Peut-être que cela simplifierait l'écriture des templates match et éviterait d'avoir 
        // à déclarer toutes les variables utilisés.
        // Par contre, est-ce souhaitable en terme de lisibilité du code ?
        
        // Variable non trouvée, retourne inchangée
        return true;
    }
private static $line=0, $column=0;
    
    /**
     * Mémorise la ligne et la colonne à laquelle commence une expression.
     * 
     * Lorqu'un template doit être compilé, es appels à cette fonction sont insérés devant chacune
     * des variables et expression présentes dans le source. Lors de la compilation, la fonction
     * sera appellée et lors de l'évaluation d'une expression, on peut alors indiquer la position
     * en cours si une erreur survient.
     * 
     * @param integer $line le numéro de la ligne en cours
     * @param integer column le numéro de la colonne en cours
     */
    public static function setCurrentPosition($line, $column)
    {
        self::$line=$line;
        self::$column=$column;
    }
    
    /**
     * Instancie récurcivement un noeud sélectionné par un template match.
     * 
     * L'instanciation consiste à :
     * 
     * <li>pour chacun des attributs indiqués dans le tag template, remplacer les variables 
     * utilisées dont le nom correspond au nom de l'attribut par la valeur de cet attribut ou par la 
     * valeur spécifiée par le noeud instancié si celui-ci a également spécifié l'attribut.
     * 
     * <li>exécuter les appels à la fonction select()
     * 
     * @param DOMNode $node le noeud à instancier
     * @return void
     */
    public static function instantiateMatch(DOMNode $node)
    {
        // Traite tous les attributs du noeud
        if ($node->hasAttributes())
            foreach ($node->attributes as $attribute)
                self::instantiateMatch($attribute);

        // Exécute le code présent dans les données du noeud
        if ($node instanceof DOMCharacterData) // #text, #comment... pour les PI :  || $node instanceof DOMProcessingInstruction
        {
            if (preg_match_all(self::$reCode, $node->data, $matches, PREG_PATTERN_ORDER|PREG_OFFSET_CAPTURE)>0)
            { 
                // Evalue toutes les expressions dans l'ordre où elles apparaissent
                foreach($matches[0] as & $match)
                {
                	// Initialement, $match contient : 
                    //    $match[0] = l'expression trouvée
                    //    $match[1] = l'offset de l'expression dans data
                    // on va y ajouter
                    //    $match[3] = le résultat de l'évaluation de l'expression
                    //    $match[4] = les noeuds éventuels à insérer devant expression si elle contient un appel à select()
                    
                    // Récupère l'expression à exécuter
                    $code=$match[0];
                    
                    // Evalue l'expression
                    self::$selectNodes=null; // si select() est utilisée, on aura en sortie les noeuds sélectionnés
                    $canEval=TemplateCode::parseExpression
                    (
                        $code, 
                        'handleMatchVar', 
                        array
                        (
                            'select'=>array(__CLASS__,'select'),
                            'setcurrentposition'=>array(__CLASS__,'setCurrentPosition'),
                            'autoid'=>null,
                            'lastid'=>null,
                        )
                    );
                    
                    if ($canEval) $code=TemplateCode::evalExpression($code);
                    
                    // Stocke le résultat
                    $match[2]=$code;
                    $match[3]=self::$selectNodes; // les noeuds éventuels retournés par select et qu'il faut insérer
                }
                
                // Remplace l'expression par sa valeur et insère les noeuds sélectionnés par select()
                
                // On travaille en ordre inverse pour deux raisons :
                // - l'offset de l'expression reste valide jusqu'à la fin
                // - après un splitText, le noeud en cours ne change pas
                foreach(array_reverse($matches[0]) as $match)
                {
                	// Remplace l'expression par sa valeur
                    $node->replaceData($match[1], strlen($match[0]), $match[2]);
                    
                    // Si select a été appellée et a retourné des noeuds, on les insère devant l'expression
                    if (! is_null($match[3]))
                    {
                        // Cas 1 : c'est un noeud de type texte (mais ce n'est pas la valeur d'un attribut)
                        if ($node instanceof DOMText && (!$node->parentNode instanceof DOMAttr))
                        {
                            // Utiliser splittext sur le noeud en cours et insère tous les noeuds à insérer devant le noeud créé
                            $newNode=$node->splitText($match[1]);
                            foreach($match[3] as $nodeToInsert)
                            {
                                // Si le noeud à insérer est un attribut, on l'ajoute au parent du noeud en cours
                                if ($nodeToInsert instanceof DOMAttr)
                                {
                                    // sauf si le parent a déjà cet attribut ou s'il s'agit d'un paramètre du template    
                                    if (! $node->parentNode->hasAttribute($nodeToInsert->name) &&
                                        ! self::$matchTemplate->hasAttribute($nodeToInsert->name))
                                        $node->parentNode->setAttributeNode($nodeToInsert->cloneNode(true));
                                }
                                
                                // Sinon on clone le noeud sélectionné et on l'insère devant l'expression
                                else
                                {
                                    $newNode->parentNode->insertBefore($nodeToInsert->cloneNode(true), $newNode);
                                }
                            }
                        }

                        // Cas 2 :concatène la valeur de tous les noeuds et insère le résultat devant l'expression
                        else
                        {
                            $h='';
                            foreach ($match[3] as $nodeToInsert)
                                $h.=$nodeToInsert->nodeValue;
                            if ($h!=='') $node->insertData($match[1], $h);
                        }
                    }
                }
            }
        }
        
        // Traite les descendants
        if ($node->hasChildNodes())
            foreach ($node->childNodes as $child)
                self::instantiateMatch($child);
    }

    /**
     * Exécute les appels à 'select()' présents dans un template match.
     * 
     * La fonction évalue l'expression xpath indiquée par rapport au noeud en cours
     * (cf {@link $matchNode}). Si le résultat est un scalaire, il est retourné ; s'il 
     * s'agit d'un noeud ou d'un ensemble de noeuds, ils sont stockés dans 
     * {@link $selectNodes}
     * 
     * @param string $xpath l'expression xpath à exécuter
     * @return mixed le scalaire retourné par l'expression xpath ou null si le résultat
     * n'est pas un scalaire
     */
    public static function select($xpath=null)
    {
        // Vérifie que le nombre d'arguments passés en paramètre est correct
    	if (func_num_args()!==1)
            throw new Exception('la fonction select() prends un et un seul argument');

        // Exécute l'expression xpath
        $xpather=new DOMXPath(self::$matchNode->ownerDocument);
        if (false === $nodeSet=$xpather->evaluate($xpath, self::$matchNode))
            throw new Exception("Erreur dans l'expression xpath [$xpath]");

        // $selectNodes va contenir les noeuds retournés 
        self::$selectNodes=null;

        // Si le résultat est un scalaire (un entier, une chaine...), on le retourne tel quel
        if (is_scalar($nodeSet)) return $nodeSet;

        // Si le résultat est un ensemble vide, rien à faire
        if ($nodeSet->length==0) return;
            
        // Stocke la liste des noeuds à insérer
        self::$selectNodes=$nodeSet;

        return null;
    }

    private static function nodeType($node)
    {
        switch ($node->nodeType)
        {
            case XML_ATTRIBUTE_NODE:        return 'XML_ATTRIBUTE_NODE';
            case XML_TEXT_NODE:             return 'XML_TEXT_NODE';
            case XML_COMMENT_NODE:          return 'XML_COMMENT_NODE';
            case XML_PI_NODE:               return 'XML_PI_NODE';
            case XML_ELEMENT_NODE:          return 'XML_ELEMENT_NODE';
            case XML_DOCUMENT_NODE:         return 'XML_DOCUMENT_NODE';
            case XML_DOCUMENT_TYPE_NODE:    return 'XML_DOCUMENT_TYPE_NODE';
            case XML_CDATA_SECTION_NODE:    return 'XML_CDATA_SECTION_NODE';
            default:
                return "type de noeud non géré ($node->nodeType)";
        }
    } 

    /**
     * Compile un noeud (un tag) et tous ses fils   
     * 
     * @param DOMNode $node le noeud à compiler
     */
    private static function compileNode(DOMNode $node)
    {
        // Liste des tags reconnus par le gestionnaire de template.
        // Pour chaque tag, on a le nom de la méthode à appeller lorsqu'un
        // noeud de ce type est rencontré dans l'arbre du document
        static $tags= array
        (
//            'root'=>'compileTemplate',
//            'template'=>'compileTemplate',
            'loop'=>'compileLoop',
            'if'=>'compileIf',
            'else'=>'elseError',
            'elseif'=>'elseError',
            'switch'=>'compileSwitch',
            'case'=>'caseError',
            'default'=>'caseError',
            'opt'=>'compileOpt',
            'slot'=>'compileSlot'
        );
        static $empty=null;
        
        self::$currentNode=$node;
        switch ($node->nodeType)
        {
            case XML_TEXT_NODE:     // du texte
                echo self::parseCode($node->nodeValue); // ou textContent ? ou wholeText ? ou data ?
                return;

            case XML_COMMENT_NODE:  // un commentaire
                if (Config::get('templates.removehtmlcomments')) return;
                echo $node->ownerDocument->saveXML($node);
                // Serait plus efficace : $node->ownerDocument->save('php://output');
                return;
                
            case XML_PI_NODE:       // une directive (exemple : <?xxx ... ? >)
                echo $node->ownerDocument->saveXML($node);
                // Serait plus efficace : $node->ownerDocument->save('php://output');
                return;
                
            case XML_ELEMENT_NODE:  // un tag
                // Récupère le nom du tag
                $name=$node->tagName;
                
                // S'il s'agit de l'un de nos tags, appelle la méthode correspondante
                if (isset($tags[$name]))
                    return call_user_func(array('TemplateCompiler', $tags[$name]), $node);

                // Récupère les attributs qu'on gère
                $collapse=($node->getAttribute('collapse')=='true');
                $ignore=$node->getAttribute('ignore')=='true';

//                if (($test=$node->getAttribute('dm')) !== '')
//                {
//                    file_put_contents(__FILE__.'.txt', $node->ownerDocument->saveXml($node));
//                }
                // Génère le noeud
                if (! $ignore)
                {
                    if (($test=$node->getAttribute('test')) !== '')
                    {
                        TemplateCode::parseExpression($test,
                                    'handleVariable',
                                    array
                                    (
                                        'setcurrentposition'=>array(__CLASS__,'setCurrentPosition'),
                                        'autoid'=>array(__CLASS__,'autoid'),
                                        'lastid'=>array(__CLASS__,'lastid')
                                    )
                        );
                        
                        echo self::PHP_START_TAG, 'if (', $test, '):',self::PHP_END_TAG;
                        $node->removeAttribute('test');
                    }
                    if (($if=$node->getAttribute('if')) !== '')
                    {
                        $canEval=TemplateCode::parseExpression($if,
                                    'handleVariable',
                                    array
                                    (
                                        'setcurrentposition'=>array(__CLASS__,'setCurrentPosition'),
                                        'autoid'=>array(__CLASS__,'autoid'),
                                        'lastid'=>array(__CLASS__,'lastid')
                                    )
                        );

                        // Si l'expression est évaluable, pas besoin de code pour le if : on évalue tout de suite
                        if ($canEval)
                        {
                        	if (! TemplateCode::evalExpression($if)) return; // le if est à false, on ignore le noeud
                            $if=''; // le if est à true, on génère tout le noeud sans condition
                        }
                        // non évaluable, entour les tags de début et de fin de noeud par un if
                        else
                        {
                            $ifVar=self::$env->getTemp('if');
                            echo self::PHP_START_TAG, 'if ('.$ifVar.'=(', $if, ')):', self::PHP_END_TAG;
                        }
                        $node->removeAttribute('if');
                    }
    
                    // Génère le tag ouvrant
                    if (!$collapse)
                    {        
                        echo '<', $name;    // si le tag a un préfixe, il figure déjà dans name (e.g. <test:h1>)
                        if ($node->namespaceURI !== $node->parentNode->namespaceURI)
                            echo ' xmlns="', $node->namespaceURI, '"'; 
                    
                        // Accès aux attributs xmlns : cf http://bugs.php.net/bug.php?id=38949
                        // apparemment, fixé dans php > 5.1.6, à vérifier
                    
                        if ($node->hasAttributes())
                        {
                            $flags=0;
                            foreach ($node->attributes as $key=>$attribute)
                            {
                                ++self::$opt;
                                $value=self::parseCode($attribute->value);
                                --self::$opt;

                                $quot=(strpos($value,'"')===false) ? '"' : "'";
                                
                                // Si l'attribut ne contient que des variables (pas de texte), il devient optionnel
                                if ($flags===2)
                                {
                                    echo self::PHP_START_TAG, 'Template::optBegin()', self::PHP_END_TAG;
                                    echo ' ', $attribute->nodeName, '=', $quot, $value, $quot;
                                    echo self::PHP_START_TAG, 'Template::optEnd()', self::PHP_END_TAG; 
                                }
                                else
                                    echo ' ', $attribute->nodeName, '=', $quot, $value, $quot;
                            }
                        }
                    }
      
                    // Détermine s'il faut générer le tag complet (<div>...</div>) ou un tag vide (<br />)
                    $emptyTag=false;
                    if (! $node->hasChildNodes())
                    {
                        // Liste des éléments dont le "content model" est déclaré comme "EMPTY" dans les DTD
                        if (is_null($empty))
                        {
                            //XHTML 1.0 strict et XHTML 1.1
                            $empty['base']=true;
                            $empty['meta']=true;
                            $empty['link']=true;
                            $empty['hr']=true;
                            $empty['br']=true;
                            $empty['param']=true;
                            $empty['img']=true;
                            $empty['area']=true;
                            $empty['input']=true;
                            $empty['col']=true;
                            
                            //XHTML 1.0 TRANSITIONAL : idem plus
                            $empty['basefont']=true;
                            $empty['isindex']=true;
                            
                            //XHTML 1.0 FRAMESET : idem plus
                            $empty['frame']=true;
                        }
                        if (isset($empty[$node->tagName])) $emptyTag=true;
                    }
                    
                    // Tag vide
                    if ($emptyTag)
                    {
                        if (! $collapse) echo ' />';
                        if ($if !== '')                     
                            echo self::PHP_START_TAG, 'endif;',self::PHP_END_TAG;
                    }
                    
                    // Génère tous les fils et la fin du tag
                    else
                    {
                        if (! $collapse) echo '>';
                        if ($if !== '')                     
                            echo self::PHP_START_TAG, 'endif;',self::PHP_END_TAG;
                        self::compileChildren($node);
                        if ($if !== '')
                        {
                            echo self::PHP_START_TAG, 'if ('.$ifVar.'):',self::PHP_END_TAG;
                            self::$env->freeTemp($ifVar);
                        }
                        if (! $collapse) echo '</', $node->tagName, '>';
                        if ($if !== '')                     
                            echo self::PHP_START_TAG, 'endif;',self::PHP_END_TAG;
                    }
                    
                    if ($test !== '')                     
                        echo self::PHP_START_TAG, 'endif;',self::PHP_END_TAG;
                }
                
                return;
                
            case XML_DOCUMENT_NODE:     // L'ensemble du document xml
                self::compileChildren($node);
                return;
                
            case XML_DOCUMENT_TYPE_NODE:    // Le DTD du document
                echo $node->ownerDocument->saveXML($node), "\n";
                return;
            
            case XML_CDATA_SECTION_NODE:    // Une section CDATA
                echo $node->ownerDocument->saveXML($node);
                return;

            case XML_ENTITY_REF_NODE:
            case XML_ENTITY_NODE:
                echo $node->ownerDocument->saveXML($node);
                return;
                            
            default:
                throw new Exception("Impossible de compiler le template : l'arbre obtenu contient un type de noeud non géré ($node->nodeType)");
        }
    }


    /**
     * Compile récursivement les fils d'un noeud et tous leurs descendants   
     * 
     * @param DOMNode $node le noeud à compiler
     */
    private static function compileChildren(DOMNode $node)
    {
        if ($node->hasChildNodes())
            foreach ($node->childNodes as $child)
                self::compileNode($child);
    }
    
    /**
     * Supprime les antislashes devant les dollar et les accolades
     */
    private static function unescape($source)
    {
        return strtr($source, array('\\$'=>'$', '\\{'=>'{', '\\}'=>'}'));	
    }

    /**
     * Compile un bloc &lt;opt&gt;&lt;/opt&gt;   
     *
     * @param DOMNode $node le noeud à compiler
     */
    private static function compileOpt(DOMNode $node)
    {
        // Opt accepte un attribut optionnel min qui indique le nombre minimum de variables
        $t=self::getAttributes($node, null,array('min'=>''));
        
        // Génère le code
        echo self::PHP_START_TAG, 'Template::optBegin()', self::PHP_END_TAG;
        ++self::$opt;
        self::compileChildren($node);
        --self::$opt;
        echo self::PHP_START_TAG, 'Template::optEnd('.$t['min'].')', self::PHP_END_TAG; 
    }

    /**
     * Récupère et vérifie les attributs obligatoires et optionnels d'un tag.
     * 
     * La fonction prend en paramètres le noeud à examiner et deux tableaux :
     * - un tableau dont les valeurs sont les attributs obligatoires
     * - un tableau dont les clés sont les attributs optionnels et dont les
     * valeurs sont les valeurs par défaut de ces attributs.
     * 
     * La fonction examine tous les attributs du noeud passé en paramètre.
     * Elle génère une exception si :
     * - un attribut obligatoire est absent
     * - le noeud contient d'autres attributs que ceux autorisés.
     * 
     * Elle retourne un tableau listant tous les attributs avec comme valeur
     * la valeur présente dans l'attribut si celui-ci figure dans le noeud ou
     * la valeur par défaut s'il s'agit d'un attribut optionnel absent du noeud.
     */
    private static function getAttributes(DOMNode $node, array $required=null, array $optional=null)
    {
    	$result=array();
        $bad=array();
        
        if (is_null($required))
            $required=array();
        else
            $required=array_flip($required);

        if (is_null($optional))
            $optional=array();
        
        // Examine les attributs présents dans le noeud
        if ($node->hasAttributes())
        {
            foreach ($node->attributes as $name=>$attribute)
            {
                // C'est un attribut obligatoire, il est présent
                if (isset($required[$name]))
                {
                    $result[$name]=$attribute->value;
                    unset($required[$name]);
                }
                    
                // C'est un attribut optionnel, il est présent
                elseif (isset($optional[$name]))
                {
                    $result[$name]=$attribute->value;
                    unset($optional[$name]);
                }
                
                // C'est un mauvais attribut
                else
                {
                	$bad[]=$name;
                }
            }
        }

        // Génère une exception s'il manque des attributs obligatoires ou si on a des attributs en trop 
        if (count($required) or count($bad))
        {
            $h=$h2='';
            if (count($required))
                $h=sprintf
                (
                    count($required)==1 ? 'l\'attribut %s est obligatoire' : 'les attributs %s sont obligatoires',
                    implode(', ', array_keys($required))    
                );
            if (count($bad))            
                $h2=sprintf
                (
                    count($bad)==1 ? 'l\'attribut %s est interdit' : 'les attributs %s sont interdits',
                    implode(', ', $bad)    
                );
            if ($h2) $h.= ($h ? ' et ' : '').$h2;
            $h.= ' dans un tag '.$node->tagName;

            throw new Exception($h);
        }
        
        // Complète le tableau résultat avec les attributs optionnels non présents
        return $result+$optional;
    }   
    
    /**
     * Compile des blocs if/elseif/else consécutifs
     * 
     * @param DOMNode $node le noeud à compiler
     */
    private static function compileIf(DOMNode $node)
    {
        /* fonctionnement : on considère qu'on a une suite de tags suivis
         * éventuellements de blancs (i.e. commentaire ou bloc de texte 
         * ne contenant que des espaces).
         * 
         * Pour la compilation, on boucle en générant à chaque fois le tag 
         * en cours (if puis elseif* puis else?) et en passant les blancs.
         * 
         * On sort de la boucle quand on trouve autre chose qu'un blanc ou 
         * autre chose qu'un tag elseif ou else.
         * 
         * A l'issue de la boucle, on supprime tous les noeuds qu'on a 
         * traité, sauf le noeud node passé en paramètre dans la mesure ou
         * la fonction compileNode qui nous a appellée fait elle-même un next.
         */
        $elseAllowed=true;  // Un else ou un elseif sont-ils encore autorisés au stade où on est ?
        $next=$node;
        for(;;)
        {
            // Génère le tag
            switch($tag=$next->tagName)
            {
                case 'else':
                    self::getAttributes($next); // aucun attribut n'est autorisé
                    echo self::PHP_START_TAG, $tag, ':', self::PHP_END_TAG;
                    $elseAllowed=false;
                    break;

                case 'elseif':
                    
                case 'if':
                    $t=self::getAttributes($next, array('test'));
                    
                    TemplateCode::parseExpression($t['test'],
                                    'handleVariable',
                                    array
                                    (
                                        'setcurrentposition'=>array(__CLASS__,'setCurrentPosition'),
                                        'autoid'=>array(__CLASS__,'autoid'),
                                        'lastid'=>array(__CLASS__,'lastid')
                                    )
                    );
                    
                    // Génère le tag et sa condition
                    echo self::PHP_START_TAG, $tag, ' (', $t['test'], '):', self::PHP_END_TAG;
                    break;
            }
                        
            // Génère le bloc (les fils)
            self::compileChildren($next);

            // Ignore tous les noeuds "vides" qui suivent
            for(;;)
            {
                // S'il n'y a plus rien après le noeud, terminé 
                if (is_null($next=$next->nextSibling)) break 2;

                // S'il ne s'agit pas d'un commentaire ou de texte vide, terminé
                if (!(($next->nodeType===XML_TEXT_NODE and $next->isWhitespaceInElementContent())
                    or ($next->nodeType===XML_COMMENT_NODE))) break;
            }

            // Vérifie que le noeud obtenu est un elseif ou un else
            if ($next->nodeType!==XML_ELEMENT_NODE) break;
            if ($elseAllowed and $next->tagName=='else') continue;
            if ($elseAllowed and $next->tagName=='elseif') continue;
            break;

        }
                
        // Ferme le dernier tag ouvert
        echo self::PHP_START_TAG, 'endif;', self::PHP_END_TAG;
        
        // Supprime tous les noeuds qu'on a traité
        if ($next)
            while(!$node->nextSibling->isSameNode($next))
                $node->parentNode->removeChild($node->nextSibling);
        else
            while($node->nextSibling)
                $node->parentNode->removeChild($node->nextSibling);
    }

    /**
     * Compile un bloc switch/case/default
     * 
     * @param DOMNode $node le noeud à compiler
     */
    private static function compileSwitch(DOMNode $node)
    {
        // Récupère la condition du switch
//        if (($test=$node->getAttribute('test')) === '')
//            $test='true';
//
        $t=self::getAttributes($node, null, array('test'=>true));
            
        TemplateCode::parseExpression($t['test'],
                                    'handleVariable',
                                    array
                                    (
                                        'setcurrentposition'=>array(__CLASS__,'setCurrentPosition'),
                                        'autoid'=>array(__CLASS__,'autoid'),
                                        'lastid'=>array(__CLASS__,'lastid')
                                    )
        );
                
        // Génère le tag et sa condition
        echo self::PHP_START_TAG, 'switch (', $t['test'], '):', "\n";
                        
        // Génère les fils (les blocs case et default)
        self::compileSwitchCases($node);

        // Ferme le switch
        echo self::PHP_START_TAG, 'endswitch;', self::PHP_END_TAG;
    }

    private static function compileSwitchCases($node)
    {
        $first=true;
        $seen=array(); // Les conditions déjà rencontrées dans le switch
        
        // Génère tous les fils du switch
        foreach ($node->childNodes as $node)
        {
            switch ($node->nodeType)
            {
                case XML_COMMENT_NODE:  // Commentaire : autorisé 
                    break;
                    
                case XML_TEXT_NODE:     // Texte : autorisé si vide
                    if (! $node->isWhitespaceInElementContent())
                        throw new Exception('Vous ne pouvez pas inclure de texte entre les différents cas d\'un switch');
                    break;
                case XML_ELEMENT_NODE:  // Noeud : seuls <case> et <default> sont autorisés 
                    switch($node->tagName)
                    {
                        case 'case':
                            if (isset($seen['']))
                                throw new Exception('Switch : bloc case rencontré après un bloc default');
                            $t=self::getAttributes($node, array('test'));
//                            if (($test=$node->getAttribute('test')) === '')
//                                throw new Exception("Tag case incorrect : attribut test manquant");
                            if (isset($seen[$t['test']]))
                                throw new Exception('Switch : plusieurs blocs case avec la même condition');
                            $seen[$t['test']]=true;
                            TemplateCode::parseExpression($t['test'],
                                    'handleVariable',
                                    array
                                    (
                                        'setcurrentposition'=>array(__CLASS__,'setCurrentPosition'),
                                        'autoid'=>array(__CLASS__,'autoid'),
                                        'lastid'=>array(__CLASS__,'lastid')
                                    )
                            );
                            echo ($first?'':self::PHP_START_TAG.'break;'), 'case ', $t['test'], ':', self::PHP_END_TAG;
                            self::compileChildren($node);
                            break;
                        case 'default':
                            $t=self::getAttributes($node); // aucun attribut autorisé
                            if (isset($seen['']))
                                throw new Exception('Switch : blocs default multiples');
                            $seen['']=true;
                            echo ($first?'':self::PHP_START_TAG.'break;'), 'default:', self::PHP_END_TAG;
                            self::compileChildren($node);
                            break;
                        default: 
                            throw new Exception('Un switch ne peut pas contenir des '. $node->tagName);
                    }
                    $first=false;
                    break;
                default:
                    throw new Exception('Un switch ne peut contenir que des blocs case et default');
            }
        } 
        
        // Si first est toujours à true, c'est qu'on a aucun fils ou que des vides
        if ($first)
            throw new Exception('Switch vide');
    }
    
    
    /**
     * Génère une erreur quand un bloc else ou un bloc elseif Compile un bloc &lt;else&gt;&lt;/else&gt;   
     *
     * @param DOMNode $node le noeud à compiler
     */
    private static function elseError(DOMNode $node)
    {
        throw new Exception('Tag '.$node->tagName.' isolé. Ce tag doit suivre immédiatement un tag if ou elseif, seuls des blancs sont autorisés entre les deux.');
    }

    private static function caseError(DOMNode $node)
    {
        throw new Exception('Tag '.$node->tagName.' isolé. Ce tag ne peut apparaître que dans un bloc switch, seuls des blancs sont autorisés entre les deux.');
    }
    
    private static function nodeGetIndent($node)
    {
        $node=$node->previousSibling;
        if (is_null($node)) return 0;
        if ($node->nodeType != XML_TEXT_NODE) return 0;
    	$h=$node->nodeValue;
        //echo "text=[",nl2br($h),"], len(h)=", strlen($h), ", len(trim(h)))=",strlen(rtrim($h, ' ')), ", h=", bin2hex($h), "\n";
        return strlen($h)-strlen(rtrim($h, ' '));
    } 
    
    private static function collapseNodes($xml)
    {
//return;
echo "Source initial :\n",  $xml->saveXml($xml), "\n------------------------------------------------------------\n";
$xpath=new DOMXPath($xml);
$nodes=$xpath->query('//div');
for($i=1;$i<10;$i++)
{
        foreach($nodes as $node)
            self::indent($node, '    ');
}
//            self::collapse($node);
echo "Source indente :\n",  $xml->saveXml($xml), "\n------------------------------------------------------------\n";
for($i=1;$i<10;$i++)
{
        foreach($nodes as $node)
            self::unindent($node, '    ');
}
echo "Source desindente :\n",  $xml->saveXml($xml), "\n------------------------------------------------------------\n";
    }

    private static function collapse(DOMElement $node)
    {
        echo "Collapse du noeud ", $node->tagName, "\n\n";
        echo "Source initial :\n",  $node->ownerDocument->saveXml($node), "\n\n";
        
        // Détermine l'indentation qui précède le tag d'ouverture du noeud
        $indent='';
        if ($previous=$node->previousSibling and $previous->nodeType==XML_TEXT_NODE)
        {
            $h=$previous->data;
            if ($pt=strrpos($h, 10) !== false) $indent=substr($h, $pt);
            if (rtrim($indent, " \t")!='') $indent='';
        }
//        echo "myindent=[$indent], "; var_dump($indent); echo "\n";
        
        // Si le tag d'ouverture est tout seul sur sa ligne (avec éventuellement des espaces avant et après),
        // on supprime la dernière ligne du noeud texte qui précède
        if ($previous)
        {
            $h=$previous->data;
            if ($pt=strrpos($h, 10) !== false)
            {
                $line=substr($h, $pt);
                if (rtrim($line, " \t")==='')
                    $previous->data=substr($previous->data,0,$pt-1);
            } 
        }
        
        // Réindente tous les noeuds fils de type texte contenant des retours à la ligne
        foreach($node->childNodes as $child)
        {
            $nb=0;
            if ($child->nodeType===XML_TEXT_NODE)
                $child->data=$h=str_replace("\n".$indent, "\n", $child->data, $nb);
        }
        echo "Source obtenu :\n",  $node->ownerDocument->saveXml($node), "\n\n";
        echo "-----------------------------------------------------\n\n";
    }
    
    // ajoute la chaine indent à l'indentation du noeud et de tous ses descendants
    private static function indent(DOMNode $node, $indent, $isChild=false)
    {
        // ajoute indent au noeud texte qui précède le tag d'ouverture (node->previousSibling)
        // pas de previous = node est le premier noeud de l'arbre
        // previous != TEXT_NODE = <elem><node> : ne pas indenter
        if (! $isChild)
        {
            $previous=$node->previousSibling;
            if (is_null($previous))
            {
                $node->parentNode->insertBefore($node->ownerDocument->createTextNode($indent), $node);
            }
            else
            {
                if ($previous->nodeType===XML_TEXT_NODE)
                {
                    if (rtrim(strrchr($previous->data, 10), "\n\t- ")==='')
                        $previous->data .= $indent;
                }
            }
        }
                
        // Indente tous les fils
        
        if ($node->hasChildNodes()) foreach($node->childNodes as $child)
        {
            if ($child->nodeType===XML_TEXT_NODE)
                $child->data=str_replace("\n", "\n".$indent, $child->data);
            
            if ($child->hasChildNodes()) self::indent($child, $indent, true);
        }
    }   
     
    // supprime la chaine indent à l'indentation du noeud et de tous ses descendants
    private static function unindent(DOMNode$node, $indent, $isChild=false)
    {
        // ajoute indent au noeud texte qui précède le tag d'ouverture (node->previousSibling)
        // pas de previous = node est le premier noeud de l'arbre
        // previous != TEXT_NODE = <elem><node> : ne pas indenter
        if (! $isChild)
        {
            $previous=$node->previousSibling;
            if (is_null($previous))
            {
                $node->parentNode->insertBefore($node->ownerDocument->createTextNode($indent), $node);
            }
            else
            {
                if ($previous->nodeType===XML_TEXT_NODE)
                {
                    $previous->data=str_replace("\n".$indent, "\n", $previous->data);
//                    if (rtrim(strrchr($previous->data, 10), "\n\t- ")==='')
//                        $previous->data .= $indent;
                }
            }
        }
                
        // Indente tous les fils
        
        if ($node->hasChildNodes()) foreach($node->childNodes as $child)
        {
            if ($child->nodeType===XML_TEXT_NODE)
                $child->data=str_replace("\n".$indent, "\n", $child->data);
            
            if ($child->hasChildNodes()) self::unindent($child, $indent, true);
        }
        
    }   
     
    private static function compileLoop($node)
    {
//echo "Je suis indenté de ", self::nodeGetIndent($node), " espaces\n";
//echo "AVANT: \n\n", show($node->ownerDocument->saveXml($node->parentNode)), "\n\n"; 
//$indent=self::nodeGetIndent($node);
//if (($node->previousSibling) && ($node->previousSibling->nodeType===XML_TEXT_NODE))
//{
//    $h=$node->previousSibling->nodeValue;
//    $node->previousSibling->nodeValue=substr($h,0,strlen($h)-4);
//}
//foreach($node->childNodes as $child)
//{
//	if ($child->nodeType!==XML_TEXT_NODE) continue;
//    {
//    	$h=$child->nodeValue;
//        if(substr($h, -$indent)===str_repeat(' ', $indent))
//        {
//            //$h{strlen($h)-$indent}='|';//substr($h,0,-$indent)
//            $child->nodeValue=substr($h,0,strlen($h)-$indent);
////            $child->nodeValue=rtrim($h, ' ');
//        }else
//            echo "child non réindenté\n";
//    }
//}
//echo "APRES : \n\n", show($node->ownerDocument->saveXml($node->parentNode)), "\n\n"; 

        // Récupère l'objet sur lequel il faut itérer
//        if (($on=$node->getAttribute('on')) === '')
//            throw new Exception("Tag loop incorrect : attribut 'on' manquant");
            
        $t=self::getAttributes($node, array('on'), array('as'=>'$key,$value', 'max'=>''));
            
        TemplateCode::parseExpression($t['on'],
                                    'handleVariable',
                                    array
                                    (
                                        'setcurrentposition'=>array(__CLASS__,'setCurrentPosition'),
                                        'autoid'=>array(__CLASS__,'autoid'),
                                        'lastid'=>array(__CLASS__,'lastid')
                                    )
        );
            
        // Récupère et traite l'attribut as
        $var='\$([a-zA-Z][a-zA-Z0-9_]*)'; // synchro avec le $var de parseCode 
        $re="~^\s*$var\s*(?:,\s*$var\s*)?\$~"; // as="value", as="key,value", as=" $key, $value "
        if (preg_match($re, $t['as'], $matches) == 0)
            throw new Exception("Tag loop : syntaxe incorrecte pour l'attribut 'as'");
        if (isset($matches[2]))
        {
            $key=$matches[1];
            $value=$matches[2];
        }
        else
        {
            $key='key';
            $value=$matches[1];
        }            
        
        $keyReal=self::$env->getTemp($key);
        $valueReal=self::$env->getTemp($value);

        $max='';
        if ($t['max']!='')
        {
            TemplateCode::parseExpression($t['max'],
                                        'handleVariable',
                                        array
                                        (
                                            'setcurrentposition'=>array(__CLASS__,'setCurrentPosition'),
                                            'autoid'=>array(__CLASS__,'autoid'),
                                            'lastid'=>array(__CLASS__,'lastid')
                                        )
            );
            if ($t['max']!=='0')
                $max=self::$env->getTemp('nb');
        }
        
        echo self::PHP_START_TAG, ($max?"$max=0;\n":''), "foreach($t[on] as $keyReal=>$valueReal):", self::PHP_END_TAG;
        if ($node->hasChildNodes())
        {
            self::$env->push(array($key=>$keyReal, $value=>$valueReal));
            ++self::$loop;
            self::compileChildren($node);
            --self::$loop;
            self::$env->pop();
        }
        echo self::PHP_START_TAG, ($max?"if (++$max>=$t[max]) break;\n":''),'endforeach;', self::PHP_END_TAG;
        
        self::$env->freeTemp($keyReal);
        self::$env->freeTemp($valueReal);
    }

    private static function compileSlot($node)
    {
        // Récupère le nom du slot
        if (($name=$node->getAttribute('name')) === '')
            throw new Exception("Tag slot incorrect : attribut 'name' manquant");

        // Récupère l'action par défaut (optionnel)
        if (($default=$node->getAttribute('default')) !== '')
            TemplateCode::parseExpression($default,
                                    'handleVariable',
                                    array
                                    (
                                        'setcurrentposition'=>array(__CLASS__,'setCurrentPosition'),
                                        'autoid'=>array(__CLASS__,'autoid'),
                                        'lastid'=>array(__CLASS__,'lastid')
                                    )
            );

        echo self::PHP_START_TAG, "Template::runSlot('",addslashes($name), "'";
        if ($default !== '') echo ",'",addslashes($default),"'";
        echo ");", self::PHP_END_TAG;

    }
    
    /* ======================== EXPRESSION PARSER ============================= */

    public static function parseCode($source)
    {
        // Boucle tant qu'on trouve des choses dans le source passé en paramètre
        $start=0;
        $result='';
        
        for($i=1;;$i++)
        {
            // Recherche la prochaine expression
            if (preg_match(self::$reCode, $source, $match, PREG_OFFSET_CAPTURE, $start)==0) break;
            $expression=$match[0][0];
            $len=strlen($expression);
            $offset=$match[0][1];
            
            // Envoie le texte qui précède l'expression trouvée
            if ('' != $text=substr($source, $start, $offset-$start))
                $result.=self::unescape($text);
                        
            // Enlève les accolades qui entourent l'expression
            if ($expression[0]==='{') $expression=substr($expression, 1, -1);
            if (trim($expression) != '')
            {
                // Compile l'expression
                $canEval=TemplateCode::parseExpression
                (
                    $expression, 
                    'handleVariable', 
                    array
                    (
                        'setcurrentposition'=>array(__CLASS__,'setCurrentPosition'),
                        'autoid'=>array(__CLASS__,'autoid'),
                        'lastid'=>array(__CLASS__,'lastid'),
                    )
                );
                
                if ($canEval)
                    $result.=TemplateCode::evalExpression($expression);
                else
                {
                    if ($expression !== 'NULL') // le résultat retourné par var_export(null)
                    {
                        // Si on est dans un bloc <opt>...</opt>, génère un appel à filled(x)
                        if (self::$opt)
                            $result.=self::PHP_START_TAG . 'echo Template::filled(' . $expression . ')'.self::PHP_END_TAG;
                            
                        // sinon, retourne la variable telle quelle
                        else
                            $result.=self::PHP_START_TAG . 'echo ' . $expression . self::PHP_END_TAG;
                    }
                }
            }
                        
            // Passe au suivant
            $start=$offset + $len;
        }

        // Envoie le texte qui suit le dernier match 
        if ('' != $text=substr($source, $start))
            $result.=self::unescape($text);

        return $result;
    }
    // return true si c'est du code, false sinon
    public static function handleVariable(& $var)
    {
        // Enlève le signe $ de début
        $name=substr($var,1);

        // Teste si c'est une source de données
        $var=self::$env->get($name);
        if ($var === false)
            throw new Exception("Impossible de compiler le template : la source de données <code>$name</code> n'est pas définie."
            . 'ligne '.self::$line . ', colonne '.self::$column
            );
            
        return true;
    }
    

}

?>