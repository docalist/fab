<?php
define('debugexp',false);

/*
TODO: le if et le collapse sont redondants. Virer le if, g�rer correctement le collapse
A faire :
- am�liorer le syst�me de bindings pour les cas o� il s'agit d'un callback, 
d'une propri�t� d'objet, etc.
- ne pas g�n�rer Template::Filled($x) si on n'est pas dans un bloc opt
- ne pas g�n�rer ($x=$key) ? Template::Filled($x) : '' si on n'a qu'une
seule variable
- une variable, pas de opt : echo $titre
- une variable, dans un opt : echo Filled($x);  (filled retourne ce qu'on lui passe)
- deux vars, pas de opt : echo (($x=var1) or ($x=var2) ? $x : void)
- sortie de boucle : les variables datasource ont �t� �cras�es (bindings)
- tester le tag ignore, autoriser autre chose que 'true'
- � �tudier : g�n�rer des variables v1, v2... plut�t que le vrai nom (pas d'�crasement)
- autoriser des variables de variables (l'�quivalent de [prix[i]] = ?)
*/

/**
 * @package     fab
 * @subpackage  template
 * @author      Daniel M�nard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id$
 */

/**
 * Compilateur de templates
 * 
 * Le compilateur est bas� sur un parser xml. Si le remplate n'est pas un fichier xml
 * on ajoute une d�claration xml et un tag racine pour qu'il le devienne. 
 * Quelques transformations sont ensuite op�r�es sur le source xml obtenu (pour le 
 * moment uniquement transformation des templates match). 
 * Le source obtenu est ensuite charg� dans le parser. La compilation consiste alors 
 * simplement � faire un parcourt de l'arbre obtenu en g�n�rant � chaque fois le code 
 * n�cessaire (cf {@link compileNode()}). Pour chacun des tags de notre langage (if, 
 * loop, switch...) compileNode() appelle la fonction correspondante (cf {@link compileIf()}, 
 * {@link CompileLoop()}, {@link CompileSwitch()}, ...).
 * Le code est g�n�r� par de simples echos. L'ensemble de la sortie est bufferis� pour �tre
 * retourn� � l'appellant. 
 */
class TemplateCompiler
{
    const PHP_START_TAG='<?php ';
    const PHP_END_TAG="?>";

    /**
     * @var int Niveau d'imbrication des blocs <opt>...</opt> rencontr�s durant
     * la compilation. Utilis� pour optimiser la fa�on dont les variables sont
     * compil�es (pas de Template::filled($x) si on n'est pas dans un bloc opt)
     * 
     * @access private
     */
    private static $opt=0;

    /**
     * @var int Niveau d'imbrication des blocs <loop>...</loop> rencontr�s durant
     * la compilation. Utilis� pour attribuer des variables de boucles diff�rentes
     * � chaque niveau.
     * 
     * @access private
     */
    private static $loop=0;
    
    /**
     * @var array Liaisons entre chacune des variables rencontr�es dans le template
     * et la source de donn�es correspondante.
     * Ce tableau est construit au cours de la compilation. Les bindings sont ensuite
     * g�n�r�s au d�but du template g�n�r�.
     * 
     * @access private
     */
    private static $bindings=array();
    
    
    private static $datasources=array();
    
    private static $stack=array();
    
    private static $functions=array
    (
        'autoId'=>'getAutoId',
        'lastId'=>'getLastId',
        'select'=>'executeSelect'
    );
    
    private static function executeSelect($xpath)
    {
        echo "<pre>";
        echo "Appel de executeSelect\n";
        $t=func_get_args();
        print_r($t);
        
        echo htmlentities(self::$currentNode->ownerDocument->saveXml(self::$currentNode));
                   
        echo "</pre>";           
    } 
    
    private static $currentNode=null;
    private static $lastId='';
    private static $usedId=array();


    /**
     * @staticvar string Expression r�guli�re utilis�e pour trouver les variables et les expressions pr�sentes dans le source du template
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
                .*?                         # toute suite de caract�res 
                (?<!\\\\)                   # si le "}" est pr�c�d� de antislash, on ignore
                \}                          # le "}" fermant
        ~x';


    /**
     * @staticvar DOMNodeList Lorsqu'un template match contient un appel � la pseudo fonction select(), les noeuds
     * s�lectionn�s sont stock�s dans $selectNodes
     */
    private static $selectNodes=null;

    
    private static function getAutoId()
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
    
    private static function getLastId()
    {
        return self::$lastId;   
    }
    
    /**
     * Compile un template 
     * 
     * G�n�re une exception si le template est mal form� ou contient des erreurs.
     * 
     * @param string $source le code source du template � compiler
     * @return string le code php du template compil�
     */
    public static function compile($source, $datasources=null)
    {
        // Supprime les commentaires de templates : /* xxx */
        $source=preg_replace('~/\*.*?\*/~ms', null, $source);
        
        // Ajoute si n�cessaire une d�claration xml au template
        if (substr($source, 0, 6)==='<?xml ')
        {
            $xmlDeclaration=strtok($source, '>').'>';
        }
        else
        {
            $xmlDeclaration='';  
            $source='<?xml version="1.0" encoding="ISO-8859-1" ?>' . $source;
        }

        // Expression r�guli�re utilis�e pour d�terminer la fin du prologue du fichier xml
        // on s�lectionne tout ce qui commence par <! ou <? suivis d'espaces
        $re='~^(?:\<[?!][^>]*>\s*)*~';

        // Ajoute une racine <root>...</root> au template
        $source=preg_replace($re, '$0<root collapse="true">', $source, 1);
        $source.='</root>';
        
        // Ajoute les fichiers auto-include dans le source
        $files=Config::get('templates.autoinclude');

        // TODO: tester que le fichier existe et est lisible et g�n�rer une exception sinon 
        $h='';
        foreach((array)$files as $file)
        	$h.=file_get_contents(Runtime::$fabRoot.'core/template/autoincludes/'.$file);

        if ($h) $source=str_replace('</root>', '<div ignore="true">'.$h.'</div></root>', $source);
//        if (Template::getLevel()==0) file_put_contents(dirname(__FILE__).'/dm.xml', $source);

        // Cr�e un document XML
        $xml=new domDocument();

        if (Config::get('templates.removeblanks'))
            $xml->preserveWhiteSpace=false; // � true par d�faut
//$xml->strictErrorChecking=true;
        if (Config::get('templates.resolveexternals'))
        {
            $xml->resolveExternals=true;
            $catalog='XML_CATALOG_FILES=' . dirname(__FILE__) . '/xmlcatalog/catalog.xml';
            putenv($catalog);
        }
                    
        // gestion des erreurs : voir comment 1 � http://fr.php.net/manual/en/function.dom-domdocument-loadxml.php
        libxml_clear_errors(); // >PHP5.1
        libxml_use_internal_errors(true);// >PHP5.1
        
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
        // Instancie tous les templates pr�sents dans le document
       // self::compileMatches($xml);        

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
        self::$stack[]=array(self::$loop, self::$opt, self::$datasources, self::$bindings);
        self::$loop=self::$opt=0;
        self::$datasources=$datasources;
        self::$bindings=array();
        ob_start();
        if ($xmlDeclaration) echo $xmlDeclaration, "\n";
        self::compileChildren($xml); //->documentElement
        $result=ob_get_clean();
        
//        $result=str_replace(self::PHP_END_TAG."\n", self::PHP_END_TAG."\n\n", $result);

     // Nettoyage
     // si la balise de fin de php est \r, elle est mang�e (cf http://fr2.php.net/manual/fr/language.basic-syntax.instruction-separation.php)
        $result=str_replace(self::PHP_END_TAG."\r", self::PHP_END_TAG."\r\r", $result);
        $result=str_replace(self::PHP_END_TAG."\n", self::PHP_END_TAG."\n\r", $result);
        
        if (count(self::$bindings))
        {
            $h=self::PHP_START_TAG ."\n\n";
            $name='tpl_'.md5($result);
            $h.="$name();\n\nfunction $name()\n{\n\n";                
            
            $h.="\n    //Liste des variables de ce template\n" ;
            foreach (self::$bindings as $var=>$binding)
                $h.='    ' . $var . '=' . $binding . ";\n";
                
            $h.="\n".self::PHP_END_TAG;
            $result = $h.$result;
$result.=self::PHP_START_TAG . '}' . self::PHP_END_TAG;                
        }
        list(self::$loop, self::$opt, self::$datasources, self::$bindings)=array_pop(self::$stack);
        return $result;
    }

    /**
     * Ajoute devant chaque variable ($xxx) et expression ({xxx}) du source de template 
     * pass� en param�tre un appel � la pseudo fonction setCurrentPosition permettant, lors
     * du traitement des expressions de conna�tre la position (ligne,colonne) de cette expression
     * au sein du fichier source.
     * 
     * @param string $template le source du template � modifier
     * @return string le template dott� des informations de position 
     */
    public static function addCodePosition($template)
    {
        $lines=explode("\n",$template);
        foreach($lines as $line=> & $text)
            if (preg_match_all(TemplateCompiler::$reCode, $text, $matches, PREG_PATTERN_ORDER|PREG_OFFSET_CAPTURE)>0)
                foreach(array_reverse($matches[0]) as $match)
                    $text=substr_replace($text, '{setCurrentPosition('.($line+1).','.($match[1]+1).')}',$match[1],0);   

        $template=implode("\n", $lines);
        return $template;
    }
    
    /**
     * Supprime d'un arbre xml les informations de positions ajout�es au source
     * par la fonction {@link addCodePosition()}
     * 
     * @param striong|DOMNode $template le template � modifier. Il peut s'agir soit d'une chaine de 
     * caract�res contenant le source du template, soit de l'arbre XML du template (DOMNode)
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
                    //echo __METHOD__, "type de noeud non g�r� : ", $node->nodeType, '(', self::nodeType($node),')';
            }
            $child=$nextChild;
        }
    }
    
private static $matchNode=null;
private static $matchTemplate=null;

    /**
     * Compile les templates match pr�sents dans le document
     * 
     * La fonction r�cup�re tous les templates pr�sents dans le document
     * (c'est � dire les noeuds ayant un attribut match="xxx") et instancie tous
     * les noeuds du document qui correspondent
     * 
     * @param DOMDocument $xml le document xml � traiter
     * @access private
     */
    public static function compileMatches(DOMDocument $xml) // public : uniquement pour les tests unitaires
    {
        // Cr�e la liste des templates match (tous les noeuds qui ont un attribut match="xxx")
        $xpath=new DOMXPath($xml);
        $templates=$xpath->query('//template'); // , $xml->documentElement
        if ($templates->length ==0) return;

        // On enl�ve tous les templates du document xml
        // - pour qu'ils n'apparaissent pas dans la sortie g�n�r�e
        // - pour que le code d'un template ne soit pas modifi� par un autre template        
        foreach($templates as $template)
            $template->parentNode->removeChild($template);

        // Ex�cute chaque template dans l'ordre
        foreach($templates as $template)
        {
            // R�cup�re l'expression xpath du template
            $expression=$template->getAttribute('match');
            if ($expression==='')
                throw new Exception
                (
                    "L'attribut match d'un tag template est obligatoire" .
                    htmlentities($template->ownerDocument->saveXml($template))
                );
                    
            // Ex�cute la requ�te xpath pour obtenir la liste des noeuds s�lectionn�s par ce template
            if (false === $nodes=$xpath->query($expression))
                throw new Exception("Erreur dans l'expression xpath [$expression]");
                
            // Aucun r�sultat : rien � faire
            if ($nodes->length==0) 
                continue;	   

            // Remplace chacun des noeuds s�lectionn�s par la version instanci�e du template
            foreach($nodes as $node)
            {
//    self::dumpNodes($node, 'INSTANCIATION DU NOEUD');
                // Clone le template pour cr�er le noeud r�sultat 
                $result=$template->cloneNode(true);

                // Supprime l'attribut match
//                $result->removeAttribute('match');
                
                // Recopie dans le noeud r�sultat les attributs qui existent dans le noeud d'origine mais pas dans le template
                // Remplace la valeur par d�faut des attributs pr�sents dans le template par la valeur indiqu�e dans le noeud appellant
//    self::dumpNodes($result, 'RESULT AVANT');

//                foreach ($result->attributes as $attribute)
//                    if ($node->hasAttribute($attribute->name))
//                        $attribute->value=$node->getAttribute($attribute->name);
                
                self::$matchNode=$node;
//    self::dumpNodes($result, 'RESULT APRES');
                self::$matchTemplate=$template;
                
                // Instancie le noeud
                self::instantiateMatch($result);
                
                // Ajoute un attribut collapse
                $result->setAttribute('collapse','true');
                
                // ancien code : remplace l'ancien noeud par le noeud template
                $node->parentNode->replaceChild($result, $node); // remplace match par node
             
            }
        }
        
        
        //self::dumpNodes($xml, 'Apr�s compileMatch');
    }
    
    public static function handleMatchVar(& $var)
    {
        // Enl�ve le signe $ de d�but
        $attr=substr($var,1);
        
        // Regarde si le template match a un attribut portant ce nom
        if (self::$matchTemplate->hasAttribute($attr))
        {
            // Si l'appellant a sp�cifi� une valeur, on la prends
            if (self::$matchNode->hasAttribute($attr))
                $var=self::$matchNode->getAttribute($attr);
                
            // Sinon on prends la valeur par d�faut du template
            else
                $var=self::$matchTemplate->getAttribute($attr);
            
                
            return false;
        }

        // IDEA : si on voulait, on pourrait rendre accessibles tous les attributs de l'appellant
        // sous forme de variables , que ceux-ci soient ou non des attributs du template. 
        // Peut-�tre que cela simplifierait l'�criture des templates match et �viterait d'avoir 
        // � d�clarer toutes les variables utilis�s.
        // Par contre, est-ce souhaitable en terme de lisibilit� du code ?
        
        // Variable non trouv�e, retourne inchang�e
        return true;
    }
private static $line=0, $column=0;
    
    private static function setCurrentPosition($line, $column)
    {
    	self::$line=0;
        self::$column=0;
    }
    
    public static function instantiateMatch(DOMNode $node)
    {
        // Traite les attributs du noeud
        if ($node->hasAttributes())
            foreach ($node->attributes as $attribute)
                self::instantiateMatch($attribute);

        // Ex�cute le code pr�sent dans les donn�es du noeud
        if ($node instanceof DOMCharacterData) // #text, #comment... pour les PI :  || $node instanceof DOMProcessingInstruction
        {
            if (preg_match_all(self::$reCode, $node->data, $matches, PREG_PATTERN_ORDER|PREG_OFFSET_CAPTURE)>0)
            { 
                // Le tableau obtenu a un seul element, simplifions l'acc�s
                $matches=$matches[0];
                
                // Evalue toutes les expressions dans l'ordre o� elles apparaissent
                foreach($matches as & $match)
                {
                	// Initialement, $match contient : 
                    //    $match[0] = l'expression trouv�e
                    //    $match[1] = l'offset de l'expression dans data
                    // on va y ajouter
                    //    $match[3] = le r�sultat de l'�valuation de l'expression
                    //    $match[4] = les noeuds �ventuels � ins�rer devant expression si elle contient un appel � select()
                    
                    // R�cup�re l'expression � ex�cuter
                    $code=$match[0];
//                    if ($code[0]==='{') $code=substr($code, 1, -1); // TODO : � mettre dans parseExpression
                    
                    // Evalue l'expression
                    self::$selectNodes=null; // si select() est utilis�e, on aura en sortie les noeuds s�lectionn�s
                    $canEval=self::parseExpression
                    (
                        $code, 
                        'handleMatchVar', 
                        array
                        (
                            'select'=>array(__CLASS__,'select'),
                            'setcurrentposition'=>array(__CLASS__,'setCurrentPosition')
                        )
                    );
                    
                    if ($canEval) $code=self::evalExpression($code);
                    
                    // Stocke le r�sultat
                    $match[2]=$code;
                    $match[3]=self::$selectNodes; // les noeuds �ventuels retourn�s par select et qu'il faut ins�rer
                }
                
                // Remplace l'expression par sa valeur et ins�re les noeuds s�lectionn�s par select()
                
                // On travaille en ordre inverse pour deux raisons :
                // - l'offset de l'expression reste valide jusqu'� la fin
                // - apr�s un splitText, le noeud en cours ne change pas
                foreach(array_reverse($matches) as $match)
                {
                	// Remplace l'expression par sa valeur
                    $node->replaceData($match[1], strlen($match[0]), $match[2]);
                    
                    // Si select a �t� appell�e et a retourn� des noeuds, on les ins�re devant l'expression
                    if (! is_null($match[3]))
                    {
                        // Cas 1 : c'est un noeud de type texte (mais ce n'est pas la valeur d'un attribut)
                        if ($node instanceof DOMText && (!$node->parentNode instanceof DOMAttr))
                        {
                            // Utiliser splittext sur le noeud en cours et ins�re tous les noeuds � ins�rer devant le noeud cr��
                            $newNode=$node->splitText($match[1]);
                            foreach($match[3] as $nodeToInsert)
                            {
                                // Si le noeud � ins�rer est un attribut, on l'ajoute au parent du noeud en cours
                                if ($nodeToInsert instanceof DOMAttr)
                                {
                                    // sauf si le parent a d�j� cet attribut ou s'il s'agit d'un param�tre du template    
                                    if (! $node->parentNode->hasAttribute($nodeToInsert->name) &&
                                        ! self::$matchTemplate->hasAttribute($nodeToInsert->name))
                                        $node->parentNode->setAttributeNode($nodeToInsert->cloneNode(true));
                                }
                                
                                // Sinon on clone le noeud s�lectionn� et on l'ins�re devant l'expression
                                else
                                {
                                    $newNode->parentNode->insertBefore($nodeToInsert->cloneNode(true), $newNode);
                                }
                            }
                        }

                        // Cas 2 :concat�ne la valeur de tous les noeuds et ins�re le r�sultat devant l'expression
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
        
        // Traite les enfants du noeud
        if ($node->hasChildNodes())
            foreach ($node->childNodes as $child)
                self::instantiateMatch($child);
    }

    /**
     * Ins�re un noeud dans un noeud existant � l'offset indiqu�
     * 
     * @param DOMNode $node le noeud existant
     * @param int $offset la position � laquelle le nouveau noeud doit �tre ins�r�
     * @param DOMNode $newNode le noeud � ins�rer
     */
    private static function insertNode(DOMNode $node, $offset, DOMNode $newNode)
    {
/*                                                      Type du noeud � ins�rer
                |---------------|-----------+-------+-----------+-------+-----------+-----------+-----------+---------------+
                |               | ATTRIBUTE | TEXT  | COMMENT   | PI    | ELEMENT   | DOCUMENT  | DOCUMENT  | CDATA_SECTION |
                |---------------|-----------+-------+-----------+-------+-----------+-----------+-----------+---------------+
                | ATTRIBUTE     | la valeur textuelle du noeud � ins�rer est ins�r�e dans la valeur de l'attribut           |
                |---------------|-----------+-------+-----------+-------+-----------+-----------+-----------+---------------+
                | TEXT          |add in par |       |           |       |           |           |           |               |
                |---------------|-----------+-------+-----------+-------+-----------+-----------+-----------+---------------+
                | COMMENT       |           |       |           |       |           |           |           |               |
         Noeud  |---------------|-----------+-------+-----------+-------+-----------+-----------+-----------+---------------+
          en    | PI            |           |       |           |       |           |           |           |               |
         Cours  |---------------|-----------+-------+-----------+-------+-----------+-----------+-----------+---------------+
                | ELEMENT       |           |       |           |       |           |           |           |               |
                |---------------|-----------+-------+-----------+-------+-----------+-----------+-----------+---------------+
                | DOCUMENT      |           |       |           |       |           |           |           |               |
                |---------------|-----------+-------+-----------+-------+-----------+-----------+-----------+---------------+
                | DOCUMENT      |           |       |           |       |           |           |           |               |
                |---------------|-----------+-------+-----------+-------+-----------+-----------+-----------+---------------+
                | CDATA_SECTION |           |       |           |       |           |           |           |               |
                |---------------|-----------+-------+-----------+-------+-----------+-----------+-----------+---------------+
*/

    	
    }
    
    private static function select($xpath=null)
    {
        // V�rifie que le nombre d'arguments pass�s en param�tre est correct
    	if (func_num_args()!==1)
            throw new Exception('la fonction select() prends un et un seul argument');

        // Ex�cute l'expression xpath
        $xpather=new DOMXPath(self::$matchNode->ownerDocument);
        if (false === $nodeSet=$xpather->evaluate($xpath, self::$matchNode))
            throw new Exception("Erreur dans l'expression xpath [$xpath]");

        self::$selectNodes=null; // il n'y a aucun noeud � ins�rer

        // Si le r�sultat est un scalaire (un entier, une chaine...), on le retourne tel quel
        if (is_scalar($nodeSet)) return $nodeSet;

        // Si le r�sultat est un ensemble vide, rien � faire
        if ($nodeSet->length==0) return;
            
        // Stocke la liste des noeuds � ins�rer
        self::$selectNodes=$nodeSet;

        return null;
    }
    private static function compileSelectsInTextNode(DOMElement $template, DOMNode $node, DOMElement $matchNode)
    {
        // Expression r�guli�re pour rep�rer les expressions {select('xxx')}
        $re=
            '~
                (?<!\\\\)\{             # Une accolade ouvrante non pr�c�d�e de antislash
                \s*select\s*            # Le nom de la fonction : select
                \(\s*                   # D�but des param�tres
                (?:
                    (?:"([^"]*)")       # Expression xpath entre guillemets doubles
                    |                   # soit
                    (?:\'([^\']*)\')    # Expression xpath entre guillemets simples
                )
                \s*\)\s*                # Fin des param�tres
                (?<!\\\\)\}             # Une accolade fermante non pr�c�d�e de antislash
            ~sx';

        // Boucle tant qu'on trouve des choses dans le source pass� en param�tre
        for(;;)
        {
            // Recherche la prochaine expression {select('xpath')}
            if (preg_match($re, $node->nodeValue, $match, PREG_OFFSET_CAPTURE)==0) break;
            $offset=$match[0][1];
            $len=strlen($match[0][0]);
            $expression=$match[1][0];
            if ($expression=='') $expression=$match[2][0];

            // Coupe le noeud texte en cours en deux, au d�but de l'expression trouv�e
            $node=$node->splitText($offset);
            
            // Le noeud de droite retourn� par splitText() devient le nouveau noeud en cours
            
            // Supprime l'expression xpath trouv�e, elle figure au d�but
            $node->nodeValue=substr($node->nodeValue, $len);

            // Ex�cute l'expression xpath trouv�e
            $xpath=new DOMXPath($matchNode->ownerDocument);
            //$nodeSet=$xpath->query($expression, $matchNode);
            $nodeSet=$xpath->evaluate($expression, $matchNode);
            
            // Expression xpath erron�e ?
            if ($nodeSet===false)
                throw new Exception("Erreur dans l'expression xpath [$expression]");
                
            // Ins�re entre les deux noeuds texte les noeuds s�lectionn�s par l'expression xpath
            // Il est important de cloner chaque noeud car il sera peut-�tre r�utilis� par un autre select
            if (is_scalar($nodeSet))
            {
                $node->parentNode->insertBefore($node->ownerDocument->createTextNode($nodeSet), $node);
            }            
            else 
            {
                // Aucun r�sultat : rien � faire
                if ($nodeSet->length==0) continue;
                
            	
                foreach($nodeSet as $newNode)
                {
                    switch ($newNode->nodeType)
                    {
                        // S'il s'agit d'un attribut, on l'ajoute au parent, sans �craser
                        case XML_ATTRIBUTE_NODE:
                            // sauf si le parent a d�j� d�finit cet attribut    
                            if ($node->parentNode->hasAttribute($newNode->name)) break;
                            
                            // Ou s'il s'agit d'un des param�tres du template 
                            if ($template->hasAttribute($newNode->name)) break;
                            
                            // OK.
                            $node->parentNode->setAttributeNode($newNode->cloneNode(true)); 
                            break;
                            
                        // Les autres types de noeud sont simplement ins�r�s
                        case XML_TEXT_NODE:
                        case XML_COMMENT_NODE:
                        case XML_PI_NODE:
                        case XML_ELEMENT_NODE:
                        case XML_DOCUMENT_TYPE_NODE:
                        case XML_CDATA_SECTION_NODE:
                            $node->parentNode->insertBefore($newNode->cloneNode(true), $node);
                            break;
    
                        // Types de noeuds ill�gaux : exception
                        case XML_DOCUMENT_NODE:
                            throw new Exception("Expression xpath incorrecte : $expression. Les noeuds retourn�s ne peuvent pas �tre ins�r�s � la postion actuelle");
                            
                        default:
                            throw new Exception(__METHOD__ . " : type de noeud non g�r� ($node->nodeType)");
                    }
                }
            }
        }
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
                return "type de noeud non g�r� ($node->nodeType)";
        }
    } 

    /**
     * Compile un noeud (un tag) et tous ses fils   
     * 
     * @param DOMNode $node le noeud � compiler
     */
    private static function compileNode(DOMNode $node)
    {
        // Liste des tags reconnus par le gestionnaire de template.
        // Pour chaque tag, on a le nom de la m�thode � appeller lorsqu'un
        // noeud de ce type est rencontr� dans l'arbre du document
        static $tags= array
        (
//            'root'=>'compileTemplate',
//            'template'=>'compileTemplate',
            'loop'=>'compileLoop',
            'if'=>'compileIf',
            'else'=>'elseError',
            'elseif'=>'elseError',
            'switch'=>'compileSwitch',
            'case'=>'elseError',
            'default'=>'elseError',
            'opt'=>'compileOpt',
            'slot'=>'compileSlot'
        );
        static $empty=null;
        
        self::$currentNode=$node;
        switch ($node->nodeType)
        {
            case XML_TEXT_NODE:     // du texte
                $h=$node->nodeValue;
                echo self::parseCode($h,'handleVariable',
                        array
                        (
                            'setcurrentposition'=>array(__CLASS__,'setCurrentPosition')
                        )
                ,
                false); // ou textContent ? ou wholeText ? ou data ?

                $node->nodeValue=$h;
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
                // R�cup�re le nom du tag
                $name=$node->tagName;
                
                // S'il s'agit de l'un de nos tags, appelle la m�thode correspondante
                if (isset($tags[$name]))
                    return call_user_func(array('TemplateCompiler', $tags[$name]), $node);

                // R�cup�re les attributs qu'on g�re
                $collapse=($node->getAttribute('collapse')=='true');
                $ignore=$node->getAttribute('ignore')=='true';

//                if (($test=$node->getAttribute('dm')) !== '')
//                {
//                    file_put_contents(__FILE__.'.txt', $node->ownerDocument->saveXml($node));
//                }
                // G�n�re le noeud
                if (! $ignore)
                {
                    if (($test=$node->getAttribute('test')) !== '')
                    {
                        self::parseExpression($test);
                        echo self::PHP_START_TAG, 'if (', $test, '):',self::PHP_END_TAG;
                        $node->removeAttribute('test');
                    }
                    if (($if=$node->getAttribute('if')) !== '')
                    {
                        self::parseExpression($if);
                        echo self::PHP_START_TAG, 'if ($tmp=(', $if, ')):', self::PHP_END_TAG;
                        $node->removeAttribute('if');
                    }
    
                    // G�n�re le tag ouvrant
                    if (!$collapse)
                    {        
                        echo '<', $name;    // si le tag a un pr�fixe, il figure d�j� dans name (e.g. <test:h1>)
                        if ($node->namespaceURI !== $node->parentNode->namespaceURI)
                            echo ' xmlns="', $node->namespaceURI, '"'; 
                    
                        // Acc�s aux attributs xmlns : cf http://bugs.php.net/bug.php?id=38949
                        // apparemment, fix� dans php > 5.1.6, � v�rifier
                    
                        if ($node->hasAttributes())
                        {
                            $flags=0;
                            foreach ($node->attributes as $key=>$attribute)
                            {
                                ++self::$opt;
                                $value=$attribute->value;
    //public static function parseCode(& $source, $varCallback=null, $pseudoFunctions=null, $doEval=false)
                                self::parseCode($value);
                                --self::$opt;
                                $value=$value;
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
      
                    // D�termine s'il faut g�n�rer le tag complet (<div>...</div>) ou un tag vide (<br />)
                    $emptyTag=false;
                    if (! $node->hasChildNodes())
                    {
                        // Liste des �l�ments dont le "content model" est d�clar� comme "EMPTY" dans les DTD
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
                    
                    // G�n�re tous les fils et la fin du tag
                    else
                    {
                        if (! $collapse) echo '>';
                        if ($if !== '')                     
                            echo self::PHP_START_TAG, 'endif;',self::PHP_END_TAG;
                        self::compileChildren($node);
                        if ($if !== '')                     
                            echo self::PHP_START_TAG, 'if ($tmp):',self::PHP_END_TAG;
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
            
            case XML_CDATA_SECTION_NODE:    // Un bloc CDATA : <![CDATA[ on met <ce> <qu'on> $veut ]]>
                echo $node->ownerDocument->saveXML($node);
                return;

            case XML_ENTITY_REF_NODE:
            case XML_ENTITY_NODE:
                echo $node->ownerDocument->saveXML($node);
                return;
                            
            default:
                throw new Exception("Impossible de compiler le template : l'arbre obtenu contient un type de noeud non g�r� ($node->nodeType)");
        }
    }


    /**
     * Compile r�cursivement les fils d'un noeud et tous leurs descendants   
     * 
     * @param DOMNode $node le noeud � compiler
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

    public static function getDataSource($name, & $bindingName, & $bindingValue, & $code)
    {
        debug && Debug::log('%s', $name);

        // Parcours toutes les sources de donn�es
        foreach (self::$datasources as $i=>$data)
        {
            // Objet
            if (is_object($data))
            {
                // Propri�t� d'un objet
                if (property_exists($data, $name))
                {
                    debug && Debug::log('C\'est une propri�t� de l\'objet %s', get_class($data));
                    $code=$bindingName='$b_'.$name;
                    $bindingValue='& Template::$data['.$i.']->'.$name;
                    return true;
                }
                
                // Cl� d'un objet ArrayAccess
                if ($data instanceof ArrayAccess)
                {
                    try
                    {
                        debug && Debug::log('Tentative d\'acc�s � %s[\'%s\']', get_class($data), $name);
                        $value=$data[$name]; // essaie d'acc�der, pas d'erreur ?

                        $bindingName='$b_'.$name;
                        $code=$bindingName.'[\''.$name.'\']';
                        $bindingValue='& Template::$data['.$i.']';
// TODO: ne pas g�n�rer plusieurs fois le m�me binding                        
//                        $bindingValue='& Template::$data['.$i.'][\''.$name.'\']';
                        // pas de r�f�rence : see http://bugs.php.net/bug.php?id=34783
                        // It is impossible to have ArrayAccess deal with references
                        return true;
                    }
                    catch(Exception $e)
                    {
                        debug && Debug::log('G�n�re une erreur %s', $e->getMessage());
                    }
                }
                else
                    debug && Debug::log('Ce n\'est pas une cl� de l\'objet %s', get_class($data));
            }

            // Cl� d'un tableau de donn�es
            if (is_array($data) && array_key_exists($name, $data)) 
            {
                debug && Debug::log('C\'est une cl� du tableau de donn�es');
                $code=$bindingName='$b_'.$name;
                $bindingValue='& Template::$data['.$i.'][\''.$name.'\']';
                return true;
            }

            // Fonction de callback
            if (is_callable($data))
            {
                Template::$isCompiling++;
                ob_start();
                $value=call_user_func($data, $name);
                ob_end_clean();
                Template::$isCompiling--;
                
                // Si la fonction retourne autre chose que "null", termin�
                if ( ! is_null($value) )
                {
                    $bindingName='$callback';
                    if ($i) $bindingName .= $i;
                    $bindingValue='& Template::$data['.$i.']';
//                    $bindingValue.= 'print_r('.$bindingName.')';
                    $code=$bindingName.'(\''.$name.'\')';
                    $code='call_user_func(' . $bindingName.', \''.$name.'\')';
                    return true;
                    //return 'call_user_func(Template::$data['.$i.'], \''.$name.'\')';
                }
            }
            
            //echo('Datasource incorrecte : <pre>'.print_r($data, true). '</pre>');
        }
        //echo('Aucune source ne connait <pre>'. $name.'</pre>');
        return false;
    }

    
    /**
     * Compile le tag 'template' repr�sentant la racine de l'arbre xml
     * 
     * Le tag template est ajout� pour mettre au format xml un template qui
     * ne l'est pas. On se contente de g�n�rer le contenu du tag, en ignorant
     * le tag lui-m�me.
     *
     * @param DOMNode $node le noeud � compiler
     */
    private static function compileTemplate(DOMNode $node)
    {
        self::compileChildren($node);
        // TODO : serait inutile si on pouvait �crire <template collapse="true">
    }

    /**
     * Compile un bloc &lt;opt&gt;&lt;/opt&gt;   
     *
     * @param DOMNode $node le noeud � compiler
     */
    private static function compileOpt(DOMNode $node)
    {
        // Opt accepte un attribut optionnel min qui indique le nombre minimum de variables
        $min=$node->getAttribute('min') or '';
        
        // G�n�re le code
        echo self::PHP_START_TAG, 'Template::optBegin()', self::PHP_END_TAG;
        ++self::$opt;
        self::compileChildren($node);
        --self::$opt;
        echo self::PHP_START_TAG, "Template::optEnd($min)", self::PHP_END_TAG; 
    }

   
    /**
     * Compile un bloc if/elseif/else
     * 
     * @param DOMNode $node le noeud � compiler
     */
    private static function compileIf(DOMNode $node)
    {
        /* fonctionnement : on consid�re qu'on a une suite de tags suivis
         * �ventuellements de blancs (i.e. commentaire ou bloc de texte 
         * ne contenant que des espaces).
         * 
         * Pour la compilation, on boucle en g�n�rant � chaque fois le tag 
         * en cours (if puis elseif* puis else?) et en passant les blancs.
         * 
         * On sort de la boucle quand on trouve autre chose qu'un blanc ou 
         * autre chose qu'un tag elseif ou else.
         * 
         * A l'issue de la boucle, on supprime tous les noeuds qu'on a 
         * trait�, sauf le noeud node pass� en param�tre dans la mesure ou
         * la fonction compileNode qui nous a appell�e fait elle-m�me un next.
         */
        $elseAllowed=true;  // Un else ou un elseif sont-ils encore autoris�s au stade o� on est ?
        $next=$node;
        for(;;)
        {
            // G�n�re le tag
            switch($tag=$next->tagName)
            {
                case 'else':
                    echo self::PHP_START_TAG, $tag, ':', self::PHP_END_TAG;
                    $elseAllowed=false;
                    break;

                case 'elseif':
                    
                case 'if':
                    // R�cup�re la condition
                    if (($test=$next->getAttribute('test')) === '')
                        throw new Exception("Tag $tag incorrect : attribut test manquant");
                    self::parseExpression($test);
                    
                    // G�n�re le tag et sa condition
                    echo self::PHP_START_TAG, $tag, ' (', $test, '):', self::PHP_END_TAG;
                    break;
            }
                        
            // G�n�re le bloc (les fils)
            self::compileChildren($next);

            // Ignore tous les noeuds "vides" qui suivent
            for(;;)
            {
                // S'il n'y a plus rien apr�s le noeud, termin� 
                if (is_null($next=$next->nextSibling)) break 2;

                // S'il ne s'agit pas d'un commentaire ou de texte vide, termin�
                if (!(($next->nodeType===XML_TEXT_NODE and $next->isWhitespaceInElementContent())
                    or ($next->nodeType===XML_COMMENT_NODE))) break;
            }

            // V�rifie que le noeud obtenu est un elseif ou un else
            if ($next->nodeType!==XML_ELEMENT_NODE) break;
            if ($elseAllowed and $next->tagName=='else') continue;
            if ($elseAllowed and $next->tagName=='elseif') continue;
            break;

        }
                
        // Ferme le dernier tag ouvert
        echo self::PHP_START_TAG, 'endif;', self::PHP_END_TAG;
        
        // Supprime tous les noeuds qu'on a trait�
        if ($next)
            while(!$node->nextSibling->isSameNode($next))
            {
//                echo'<pre>Suppression du tag ', $node->nextSibling, '</pre>';
                $node->parentNode->removeChild($node->nextSibling);
            }
        else
            while($node->nextSibling)
            {
//                echo'<pre>Suppression du tag ', $node->nextSibling, '</pre>';
                $node->parentNode->removeChild($node->nextSibling);
            }
    }

    /**
     * Compile un bloc switch/case/default
     * 
     * @param DOMNode $node le noeud � compiler
     */
    private static function compileSwitch(DOMNode $node)
    {
        // R�cup�re la condition du switch
        if (($test=$node->getAttribute('test')) === '')
            $test='true';
        self::parseExpression($test);
                
        // G�n�re le tag et sa condition
//        echo self::PHP_START_TAG, 'switch (', $test, '):', self::PHP_END_TAG, "\n";
        echo self::PHP_START_TAG, 'switch (', $test, '):', "\n";
                        
        // G�n�re les fils (les blocs case et default)
        self::compileSwitchCases($node);

        // Ferme le switch
        echo self::PHP_START_TAG, 'endswitch;', self::PHP_END_TAG;
//        echo 'endswitch', self::PHP_END_TAG;
    }

    private static function compileSwitchCases($node)
    {
        $first=true;
        $seen=array(); // Les conditions d�j� rencontr�es dans le switch
        
        // G�n�re tous les fils du switch
        foreach ($node->childNodes as $node)
        {
            switch ($node->nodeType)
            {
                case XML_COMMENT_NODE:  // Commentaire : autoris� 
                    break;
                    
                case XML_TEXT_NODE:     // Texte : autoris� si vide
                    if (! $node->isWhitespaceInElementContent())
                        throw new Exception('Vous ne pouvez pas inclure de texte entre les diff�rents cas d\'un switch');
                    break;
                case XML_ELEMENT_NODE:  // Noeud : seuls <case> et <default> sont autoris�s 
                    switch($node->tagName)
                    {
                        case 'case':
                            if (isset($seen['']))
                                throw new Exception('Switch : bloc case rencontr� apr�s un bloc default');
                            if (($test=$node->getAttribute('test')) === '')
                                throw new Exception("Tag case incorrect : attribut test manquant");
                            if (isset($seen[$test]))
                                throw new Exception('Switch : plusieurs blocs case avec la m�me condition');
                            $seen[$test]=true;
                            self::parseExpression($test);
                            echo ($first?'':self::PHP_START_TAG.'break;'), 'case ', $test, ':', self::PHP_END_TAG;
                            self::compileChildren($node);
                            break;
                        case 'default':
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
        
        // Si first est toujours � true, c'est qu'on a aucun fils ou que des vides
        if ($first)
            throw new Exception('Switch vide');
    }
    
    
    /**
     * G�n�re une erreur quand un bloc else ou un bloc elseif Compile un bloc &lt;else&gt;&lt;/else&gt;   
     *
     * @param DOMNode $node le noeud � compiler
     */
    private static function elseError(DOMNode $node)
    {
        throw new Exception('Tag '.$node->tagName.' isol�. Ce tag doit suivre imm�diatement un tag if ou elseif, seuls des blancs sont autoris�s entre les deux.');
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
        
        // D�termine l'indentation qui pr�c�de le tag d'ouverture du noeud
        $indent='';
        if ($previous=$node->previousSibling and $previous->nodeType==XML_TEXT_NODE)
        {
            $h=$previous->data;
            if ($pt=strrpos($h, 10) !== false) $indent=substr($h, $pt);
            if (rtrim($indent, " \t")!='') $indent='';
        }
//        echo "myindent=[$indent], "; var_dump($indent); echo "\n";
        
        // Si le tag d'ouverture est tout seul sur sa ligne (avec �ventuellement des espaces avant et apr�s),
        // on supprime la derni�re ligne du noeud texte qui pr�c�de
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
        
        // R�indente tous les noeuds fils de type texte contenant des retours � la ligne
        foreach($node->childNodes as $child)
        {
            $nb=0;
            if ($child->nodeType===XML_TEXT_NODE)
                $child->data=$h=str_replace("\n".$indent, "\n", $child->data, $nb);
        }
        echo "Source obtenu :\n",  $node->ownerDocument->saveXml($node), "\n\n";
        echo "-----------------------------------------------------\n\n";
    }
    
    // ajoute la chaine indent � l'indentation du noeud et de tous ses descendants
    private static function indent(DOMNode $node, $indent, $isChild=false)
    {
        // ajoute indent au noeud texte qui pr�c�de le tag d'ouverture (node->previousSibling)
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
     
    // supprime la chaine indent � l'indentation du noeud et de tous ses descendants
    private static function unindent(DOMNode$node, $indent, $isChild=false)
    {
        // ajoute indent au noeud texte qui pr�c�de le tag d'ouverture (node->previousSibling)
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
//echo "Je suis indent� de ", self::nodeGetIndent($node), " espaces\n";
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
//            echo "child non r�indent�\n";
//    }
//}
//echo "APRES : \n\n", show($node->ownerDocument->saveXml($node->parentNode)), "\n\n"; 

        // R�cup�re l'objet sur lequel il faut it�rer
        if (($on=$node->getAttribute('on')) === '')
            throw new Exception("Tag loop incorrect : attribut 'on' manquant");
        self::parseExpression($on);
            
        // R�cup�re et traite l'attribut as
        $key='key';
        $value='value';
        if (($as=$node->getAttribute('as')) !== '')
        {
            $var='\$([a-zA-Z][a-zA-Z0-9_]*)'; // synchro avec le $var de parseCode 
            $re="~^\s*$var\s*(?:,\s*$var\s*)?\$~"; // as="value", as="key,value", as=" $key, $value "
            if (preg_match($re, $as, $matches) == 0)
                throw new Exception("Tag loop : syntaxe incorrecte pour l'attribut 'as'");
            if (isset($matches[2]))
            {
                $key=$matches[1];
                $value=$matches[2];
            }
            else
            {
                $value=$matches[1];
            }            
        }
        
        $keyReal='$_key';
        $valueReal='$_val';
//        if (self::$loop)
//        {
            $keyReal.=self::$loop;
            $valueReal.=self::$loop;
//        }
        echo self::PHP_START_TAG, "foreach($on as $keyReal=>$valueReal):", self::PHP_END_TAG;
        if ($node->hasChildNodes())
        {
//            echo '<pre>ajout ds datasources de ', $key, ' et de ', $value, '</pre>';
            array_unshift(self::$datasources, array($key=>$keyReal, $value=>$valueReal)); // empile au d�but
            ++self::$loop;
            self::compileChildren($node);
            --self::$loop;
            array_shift(self::$datasources);    // d�pile au d�but
        }
        echo self::PHP_START_TAG, 'endforeach;', self::PHP_END_TAG;
    }

    private static function compileSlot($node)
    {
        // R�cup�re le nom du slot
        if (($name=$node->getAttribute('name')) === '')
            throw new Exception("Tag slot incorrect : attribut 'name' manquant");

        // R�cup�re l'action par d�faut (optionnel)
        if (($default=$node->getAttribute('default')) !== '')
            self::parseExpression($default);

        echo self::PHP_START_TAG, "Template::runSlot('",addslashes($name), "'";
        if ($default !== '') echo ",'",addslashes($default),"'";
        echo ");", self::PHP_END_TAG;

    }
    
    /* ======================== EXPRESSION PARSER ============================= */

    /**
     * Pour l'analyse des expressions, on utilise token_get_all, mais on ajoute
     * deux tokens qui n'existent pas en standard : T_CHAR pour les caract�res et
     * T_END qui marque la fin de l'expression. Cela nous simplifie l'analyse.
     */
    const T_CHAR=20000; // les tokens actuels de php vont de 258 � 375, peu de risque de conflit...
    const T_END=20001;
    
    /**
     * Analyse une chaine contenant une expression php et retourne un tableau contenant
     * les tokens correspondants
     * 
     * @param string $expression l'expression � analyser
     * @return array les tokens obtenus
     */
    public static function tokenize($expression)
    {
        // Utilise l'analyseur syntaxique de php pour d�composer l'expression en tokens
        $tokens = token_get_all(self::PHP_START_TAG . $expression . self::PHP_END_TAG);
        
        // Enl�ve le premier et le dernier token (PHP_START_TAG et PHP_END_TAG)
        array_shift($tokens);
        array_pop($tokens);
        
        // Supprime les espaces du source et cr�e des tokens T_CHAR pour les caract�res
        foreach ($tokens as $index=>$token)
        {
            // Transforme en T_CHAR ce que token_get_all nous retourne sous forme de chaines
            if (is_string($token)) 
                $tokens[$index]=array(self::T_CHAR, $token);
                
            // Supprime les espaces
            elseif ($token[0]==T_WHITESPACE)
            {
                // Si les blancs sont entre deux (chiffres+lettres), il faut garder au moins un blanc
                if (    isset($tokens[$index-1]) && isset($tokens[$index+1]) 
                     && (ctype_alnum(substr($tokens[$index-1][1],-1)))
                     && (ctype_alnum(substr(is_string($tokens[$index+1]) ? $tokens[$index+1] : $tokens[$index+1][1],0,1)))
                   )  
                    $tokens[$index][1]=' ';
                    
                // Sinon on peut supprimer compl�tement l'espace
                else                     
                    unset($tokens[$index]);
            }
            
            // Supprimer les commentaires
            elseif ($token[0]==T_COMMENT || $token[0]==T_DOC_COMMENT)
                unset($tokens[$index]);
        }
        
        // Comme on a peut-�tre supprim� des tokens, force une renum�rotation des index
        $tokens=array_values($tokens);
        
        // Ajoute la marque de fin (T_END)
        $tokens[]=array(self::T_END,null);
        
        // Retourne le tableau de tokens obtenu
        return $tokens;
    }

    /**
     * G�n�re l'expression PHP correspondant au tableau de tokens pass�s en param�tre
     * 
     * Remarque : les tokens doivent avoir �t� g�n�r�s par {@link tokenize()}, cela
     * ne fonctionnera pas avec le r�sultat standard de token_get_all().
     * 
     * @param string $tokens le tableau de tokens
     * @return string l'expression php correspondante
     */
    public static function unTokenize($tokens)
    {
        $result='';
        foreach ($tokens as $token)
            $result.=$token[1];
        return $result;
    }


    /**
     * Affiche les tokens pass�s en param�tre (debug)
     */
    private static function dumpTokens($tokens)
    {
        echo '<pre>';
        foreach($tokens as $index=>$token)
        {
            echo gettype($token), ' => ', $index, '. '; 
            switch($token[0])
            {
                case self::T_CHAR:
                    echo 'T_CHAR'; 
                    break;
                    
                case self::T_END:
                    echo 'T_END'; 
                    break;
                    
                default:
                    echo token_name($token[0]);	
            }
            echo ' : [', $token[1], ']', "<br />";
        }
        var_export($tokens);
        echo '</pre>';
    }
    
    /**
     * Evalue l'expression PHP pass�e en param�tre et retourne sa valeur.
     * 
     * @param string $expression l'expression PHP � �valuer
     * @return mixed la valeur obtenue
     * @throws Exception en cas d'erreur.
     */
    private static function evalExpression($expression)
    {
        // Installe un gestionnaire d'exception sp�cifique
        set_error_handler(array(__CLASS__,'evalError'));
        
        // Ex�cute l'expression
        if ($expression[0]==='{') $expression=substr($expression, 1, -1);
        ob_start();
        $result=eval('return '.$expression.';');
        $h=ob_get_clean();
        
        // L'�valuation n'a pas g�n�r� d'exception, mais si une sortie a �t� g�n�r�e (un warning, par exemple), c'est une erreur
        if ($h !=='')
            throw new Exception('Erreur dans l\'expression PHP [ ' . $expression . ' ] : ' . $h);
            
        // Restaure le gestionnaire d'exceptions pr�c�dent
        restore_error_handler();
        return $result;     
    }
    
    /** 
     * Gestionnaire d'erreurs appell� par {@link evalExpression} en cas d'erreur 
     * dans l'expression �valu�e 
     */
    private static function evalError($errno, $errstr, $errfile, $errline, $errcontext)
    {
        // errContext contient les variables qui existaient au moment de l'erreur
        // Celle qui nous int�resse, c'est l'expression pass�e � eval
        // on supprimer le 'return ' de d�but et le ';' de fin (cf evalExpression)
        $h=substr(substr($errcontext['expression'], 7), 0, -1);
        
        // G�n�re une exception
        throw new Exception('Erreur dans l\'expression PHP [ ' . $h . ' ] : ' . $errstr);
    } 

    // return true si c'est du code, false sinon
    public static function handleVariable(& $var)
    {
        // Enl�ve le signe $ de d�but
        $var=substr($var,1);

        // Teste si c'est une variable cr��e par le template (loop, etc.)
//        foreach (self::$datasources as $datasource)
//        {
//            if (isset($datasource[$var]))
//            {
//                // trouv� !
//                $h.= $datasource[$var];
//
//                // Passe au token suivant
//                continue 2;
//            }    
//        }

        // Teste si c'est une source de donn�es
        $name=$value=$code=null;
        if (!self::getDataSource($var, $name, $value, $code))
            throw new Exception("Impossible de compiler le template : la source de donn�es <code>$var</code> n'est pas d�finie.");

        self::$bindings[$name]=$value;
        $var=$code;
        return true;
    }
    
    /**
     * Analyse et optimise une expression PHP
     * 
     * les affectations de variables sont interdites (=, .=, &=, |=...)
     * 
     * @param mixed $expression l'expression � analyser. Cette expression peut �tre 
     * fournie sous la forme d'une chaine de caract�re ou sous la forme d'un tableau
     * de tokens tel que retourn� par {@link tokenize()}.
     * 
     * @param mixed $varCallback la m�thode � appeller lorsqu'une variable est rencontr�e
     * dans l'expression analys�e. Si vous indiquez 'null', aucun traitement ne sera effectu� 
     * sur les variables. Sinon, $varCallback doit �tre le nom d'une m�thode de la classe Template TODO: � revoir
     * 
     * @param array|null $pseudoFunctions un tableau de pseudo fonctions ("nom pour l'utilisateur"=>callback)
     */
    public static function parseExpression(&$expression, $varCallback='handleVariable', $pseudoFunctions=null)
    {
        // Si $expression est un tableau de tokens, on ajoute juste T_END � la fin
        if(debugexp) echo '<blockquote>';

        $addCurly=false;

        if (is_array($expression))
        {
            $tokens=$expression;
            $tokens[]=array(self::T_END,null);
            if(debugexp) echo '<code>', Utils::highlight(self::unTokenize($expression)), '</code>';
        }
        
        // Sinon, on tokenise l'expression
        else
        {
            if(debugexp) echo '<code>', Utils::highlight($expression), '</code>';
            if ($expression[0]==='{')
            {
                $expression=substr($expression, 1, -1);
                $addCurly=true;	
            }
            $tokens=self::tokenize($expression);
        }
        
        // Indique si on est � l'int�rieur d'une chaine ou non
        $inString=false;
        
        $curly=0;
        
        // Compteur pour l'op�rateur ternaire (xx?yy:zz). Incr�ment� lors du '?', d�cr�ment� lors du ':'. 
        // Lorsqu'on rencontre le signe ':' et que $ternary est � z�ro, c'est que c'est un collier d'expressions
        $ternary=0;
        
        // Indique si l'expression est un collier d'expressions ($x:$y:$z)
        // Passe � true quand on rencontre un ':' et que ternary est � z�ro.
        // Utilis� en fin d'analyse pour "enrober" l'expression avec le code n�cessaire
        $colon=false;
        
        // True si l'expression en cours est �valuable
        // True par d�faut, passe � faux quand on rencontre une variable, l'op�rateur '=>' dans un array() ou
        // quand on tombe sur quelque chose qu'on ne sait pas �valuer (propri�t� d'un objet, par exemple)
        $canEval=true;
        
        // Examine tous les tokens les uns apr�s les autres
        for ($index=0; $index<count($tokens); $index++)
        {
            $token=$tokens[$index];
            switch ($token[0])
            {          
                case self::T_CHAR:
                    switch ($token[1])
                    {
                        // D�but/fin de chaine de caract�re contenant des variables
                        case '"':
                            $inString=! $inString;
                            break;
                            
                        case '}':
                            if ($curly) $tokens[$index]=null;
                            --$curly;
                            break;
                            
                        // Remplace '!' par '->' sauf s'il s'agit de l'op�rateur 'not' 
                        case '!': 
                            if ($index>0) 
                            {
                                switch($tokens[$index-1][0])
                                {
                                    // Le token qui pr�c�de le '!' est un op�rateur, ne pas remplacer
                                    case T_BOOLEAN_AND:
                                    case T_BOOLEAN_OR:
                                    case T_LOGICAL_AND:
                                    case T_LOGICAL_OR:
                                    case T_LOGICAL_XOR:
                                    case T_VARIABLE:
                                        break;
                                    case self::T_CHAR:
                                        switch($tokens[$index-1][1])
                                        {
                                            case '!':
                                            case '(':
                                            case '[':
                                                break 2;
                                        }

                                    // OK, on peut remplacer
                                    default:
                                        $tokens[$index][1]='->';
                                }   
                            }
                            break;

                        // L'op�rateur '=' (affectation) n'est pas autoris�
                        case '=': 
                            throw new Exception('affectation interdite dans une expression, utilisez "=="'.$expression);
                            
                        // Symbole '?' : m�morise qu'on est dans un op�rateur ternaire (xx?yy:zz)
                        case '?':
                            $ternary++;
                            break;
                            
                        // Symbole ':' : op�rateur ternaire ou collier d'expression ($x:$y:$z)
                        case ':':
                            if ($ternary>0)     // On a un '?' en cours
                            {
                                $ternary--;
                            }
                            else                // Aucun '?' en cours : c'est le d�but ou la suite d'un collier d'expressions
                            {
                                $tokens[$index][1]=') OR $tmp=(';
                                $colon=true;
                            }
                    }
                    break;  
                    
                // Gestion/instanciation des variables : si on a un callback, on l'appelle                  
                case T_VARIABLE:    
                    if (is_null($varCallback))
                        $canEval=false;
                    else
                    {
                        $var=$token[1];
                        $isCode=self::$varCallback($var, $inString, $canEval);

                        if ($inString) 
                        {
                            if ($isCode) // il faut stopper la chaine, concat�ner l'expression puis la suite de la chaine
                                $t=array(array(self::T_CHAR,'"'), array(self::T_CHAR,'.'), array(self::T_CHAR,$var), array(self::T_CHAR,'.'), array(self::T_CHAR,'"'));
                            else // il faut ins�rer la valeur telle quelle dans la chaine
                                $t=array(array(self::T_CHAR,addslashes($var)));
                        }
                        else
                        {
                            if ($isCode)
                                $t=array(array(self::T_CHAR,$var));
                            else
                                $t=array(array(T_CONSTANT_ENCAPSED_STRING, '\''.addslashes($var). '\'')); 
                        }
                
                        array_splice($tokens, $index, 1, $t);

                        if ($isCode) $canEval=false; 
                    }
                    break;
                    
                case T_CURLY_OPEN: // une { dans une chaine . exemple : "nom: {$h}"
                    $tokens[$index][1]=null;
                    $curly++;
                    break;
                    
                // un identifiant : appel de fonction, constante, propri�t�    
                case T_STRING:  // un identifiant de fonction, de constante, de propri�t�, etc.
                case T_ARRAY:   // array(xxx), g�r� comme un appel de fonction
                case T_EMPTY:   // empty(xxx), g�r� comme un appel de fonction
                case T_ISSET:   // isset(xxx), g�r� comme un appel de fonction
                
                    // Appel de fonction
                    if ($tokens[$index+1][1]==='(')
                    {
                        $canEval &= self::parseFunctionCall($tokens, $index, $varCallback, $pseudoFunctions);
                        break;
                    }

                    // Si c'est une constante d�finie, on peut �valuer
                    if (defined($tokens[$index][1])) break;

                    // C'est autre chose (une propri�t�, etc.), on ne peut pas �valuer
                    $canEval=false;
                    break;
                
                case T_DOUBLE_ARROW: // seulement si on est dans un array()
                    $canEval=false;
                    break;

                // R��criture des chaines � guillemets doubles en chaines simples si elle ne contiennent plus de variables
                case T_CONSTANT_ENCAPSED_STRING:
                    $tokens[$index][1]=var_export(substr($token[1], 1, -1),true);
                    break;
                    
                // Autres tokens autoris�s, mais sur lesquels on ne fait rien
                case T_NUM_STRING:
                case self::T_END:
                case T_WHITESPACE:      
                                
                case T_BOOLEAN_AND:
                case T_BOOLEAN_OR:
                case T_LOGICAL_AND:
                case T_LOGICAL_OR:
                case T_LOGICAL_XOR:
                
                case T_LNUMBER:
                case T_DNUMBER:
                
                case T_SL:
                case T_SR:

                case T_IS_EQUAL:
                case T_IS_GREATER_OR_EQUAL:   
                case T_IS_IDENTICAL:  
                case T_IS_NOT_EQUAL:
                case T_IS_NOT_IDENTICAL:
                case T_IS_SMALLER_OR_EQUAL:
                
                case T_ARRAY_CAST:
                case T_BOOL_CAST:
                case T_INT_CAST:
                case T_DOUBLE_CAST:
                case T_INT_CAST:
                case T_OBJECT_CAST:
                case T_STRING_CAST:
                case T_UNSET_CAST:
                
                case T_DOUBLE_COLON:
                case T_OBJECT_OPERATOR: 
                
                case T_ENCAPSED_AND_WHITESPACE:

                case T_INSTANCEOF:    
                    break;
                    
                // Liste des tokens interdits dans une expression de template
                case T_AND_EQUAL:       // tous les op�rateurs d'assignation (.=, &=, ...)
                case T_CONCAT_EQUAL:
                case T_DIV_EQUAL:
                case T_MINUS_EQUAL:
                case T_MOD_EQUAL:
                case T_MUL_EQUAL:
                case T_OR_EQUAL:
                case T_PLUS_EQUAL:
                case T_SL_EQUAL:
                case T_SR_EQUAL:
                case T_XOR_EQUAL:

                case T_INC:
                case T_DEC:
                
                case T_INCLUDE:         // include, require...   
                case T_INCLUDE_ONCE:  
                case T_REQUIRE:
                case T_REQUIRE_ONCE:

                case T_IF:              // if, elsif...
                case T_ELSE:
                case T_ELSEIF:
                case T_ENDIF:

                case T_SWITCH:          // switch, case...
                case T_CASE:
                case T_BREAK:
                case T_DEFAULT:
                case T_ENDSWITCH:

                case T_FOR:             // for, foreach...
                case T_ENDFOR:
                case T_FOREACH:
                case T_AS:
                case T_ENDFOREACH:
                case T_CONTINUE:

                case T_DO:              // do, while...
                case T_WHILE:
                case T_ENDWHILE:

                case T_TRY:             // try, catch
                case T_CATCH:
                case T_THROW: 

                case T_CLASS:           // classes, fonctions...
                case T_INTERFACE:
                case T_FINAL:
                case T_EXTENDS:
                case T_IMPLEMENTS:
                case T_VAR :
                case T_PRIVATE:
                case T_PUBLIC:
                case T_PROTECTED:
                case T_STATIC:
                case T_FUNCTION:
                case T_RETURN:

                case T_ECHO:            // fonctions interdites
                case T_PRINT:
                case T_EVAL:
                case T_EXIT:
                case T_DECLARE:
                case T_ENDDECLARE:
                case T_UNSET:
                case T_LIST:
                case T_EXIT:
                case T_HALT_COMPILER:

                case T_OPEN_TAG:        // d�but et fin de blocs php
                case T_OPEN_TAG_WITH_ECHO:
                case T_CLOSE_TAG:

                case T_START_HEREDOC:   // syntaxe heredoc >>> 
                case T_END_HEREDOC:

                case T_CHARACTER:
                case T_BAD_CHARACTER:

                case T_CLONE:
                case T_NEW:
                case T_CONST:
                case T_DOLLAR_OPEN_CURLY_BRACES:

                case T_FILE:        // � g�rer : __FILE__
                case T_LINE:        // __LINE__    
                case T_FUNC_C:
                case T_CLASS_C:

                case T_GLOBAL:
                case T_INLINE_HTML:        

                case T_STRING_VARNAME:         
                case T_USE :

             // case T_COMMENT: // inutile : enlev� durant la tokenisation
             // case T_DOC_COMMENT: // idem
             // case T_ML_COMMENT: php 4 only 
             // case T_OLD_FUNCTION: php 4 only
             // case T_PAAMAYIM_NEKUDOTAYIM: 
                
//                default:
                    throw new Exception('Interdit dans une expression : "'. $token[1]. '"');
                    
                // Tokens inconnus ou non g�r�s
                default:
                     //echo $token[0], '-', token_name($token[0]),'[',$token[1],']', "<br />";
                     self::dumpTokens($tokens);
                
            }
        }

        if ($colon)
        {
            array_unshift($tokens, array(self::T_CHAR, '($tmp=('));
            $tokens[]=array(self::T_CHAR, '))?$tmp:null');
        }

        $expression=self::unTokenize($tokens);

        if(debugexp) echo ' &rArr; <code>', Utils::highlight($expression), '</code>';

        if ($canEval)
        {
            $expression=Utils::varExport(self::evalExpression($expression),true);
            if(debugexp) echo ' &rArr; <code>', Utils::highlight($expression), '</code>';
        }
        else
        {
            if(debugexp) echo ' &rArr; &otimes;';
            if ($addCurly) $expression='{'.$expression.'}';
        }
        if(debugexp) echo '</blockquote>';
        return $canEval;
    }

    private static function parseFunctionCall(& $tokens, $index, $varCallback, $pseudoFunctions)
    {
        $function=$tokens[$index][1];
        
        // Fonctions qui peuvent �tre appell�es lors de la compilation
        static $compileTimeFunctions=null;

        // Fonctions autoris�es mais qui ne doivent �tre appell�es que lors de l'ex�cution du template
        static $runtimeFunctions=null;

        if (is_null($compileTimeFunctions))
        {
        	$compileTimeFunctions=array_flip(array
            (
                'empty', 'isset',
                'trim', 'rtrim', 'ltrim',
                'substr','str_replace','str_repeat',
                'implode','explode',
                'dmtrim'
            ));
            $runtimeFunctions=array_flip(array
            (
                'array', 'range',
                'implode','explode',
            ));
        }

        // D�termine si cette fonction est autoris�e et ce � quoi on a affaire
        $handler=strtolower($function);    // autorise les noms de fonction aussi bien en maju qu'en minu
        $canEval=true;
        if (isset($pseudoFunctions[$handler]))
        {
            $handler=$pseudoFunctions[$handler];
            $functype=0;
        }
        elseif (isset($compileTimeFunctions[$handler]))
            $functype=1;
        elseif (isset($runtimeFunctions[$handler]))
        {
            $functype=2;
            $canEval=false;
        }
        else
            throw new Exception($function.' : fonction inconnue ou non authoris�e');
        
        // Extrait chacun des arguments de l'appel de fonction
        $level=1;
        $args=array();
        $start=$index+2;
        for ($i=$start; $i<count($tokens); $i++)
        {
            switch ($tokens[$i][1])
            {
                case '(': 
                    $level++;
                    break;
                case ')':
                    --$level; 
                    if ($level===0) 
                    {
                        if ($i>$start)
                        {
                            $arg=array_slice($tokens, $start, $i-$start);
                            $canEval &= self::parseExpression($arg, $varCallback, $pseudoFunctions); // pas de shortcircuit avec un &=
                            $args[]=$arg;
                        }
                        break 2;
                    }
                    break ;
                case ',': 
                    if ($level===1)
                    {
                        $arg=array_slice($tokens, $start, $i-$start);
                        $canEval &= self::parseExpression($arg, $varCallback, $pseudoFunctions); // pas de shortcircuit avec un &=
                        $args[]=$arg;
                        $start=$i+1;
                    }
            }
        }
        if ($i>=count($tokens)) throw new Exception(') attendue');

        if ($canEval)
        {
            // Evalue chacun des arguments
            foreach ($args as & $arg)
                $arg=self::evalExpression($arg);
            
            // Appelle la fonction
            $result=call_user_func_array($handler, $args); // TODO : gestion d'erreur
            
            // G�n�re le code PHP du r�sultat obtenu
            $result=Utils::varExport($result, true);
            
            // Remplace les tokens codant l'appel de fonction par un token unique contenant le r�sultat
            array_splice($tokens, $index, $i-$index+1, array(array(self::T_CHAR,$result)));
        }
        else
        {
            if ($functype===0)
                throw new Exception('Les arguments de la pseudo-fonction '.$function.' doivent �tre �valuables lors de la compilation');

            $t=array();
            foreach ($args as $no=>$arg)
            {
                if ($no>0) $t[]=array(self::T_CHAR, ',');
                $t[]=array(self::T_CHAR, $arg);
            }
            array_splice($tokens, $index+1+1, $i-$index+1-1-1-1, $t);
        }
    	return $canEval;
    }

    public static function parseCode(& $source, $varCallback=null, $pseudoFunctions=null, $doEval=false)
    {
        // Expression r�guli�re pour un nom de variable php valide
        // Source : http://fr2.php.net/variables (dans l'intro 'essentiel')
        // Modif du 14/11/06 : interdiction d'avoir une variable qui commence
        // par un underscore (r�serv� aux variables internes du template, le fait
        // de l'interdire assure que les variables internes n'�crasent pas les
        // sources de donn�es)
        // Modif du 20/03/07 : les accents sont d�sormais interdits
        $var='(?<!\\\\)\$([a-zA-Z][a-zA-Z0-9_]*)';   // trouve $ident, ignore \$ident
        
        // Expression r�guli�re pour une expression valide dans un template
        $exp='(?<!\\\\)\{[^}]*(?<!\\\\)\}';         // trouve { xxx } mais pas \{ xxx }, { xxx \}, \{ xxx \}
        
        // Expression r�guli�re combinant les deux
        $re="~$var|$exp~";
        
        // Boucle tant qu'on trouve des choses dans le source pass� en param�tre
        $start=0;
        $result='';
        
        for($i=1;;$i++)
        {
            // Recherche la prochaine expression
            if (preg_match($re, $source, $match, PREG_OFFSET_CAPTURE, $start)==0) break;
            $expression=$match[0][0];
            $len=strlen($expression);
            $offset=$match[0][1];
            
            // Envoie le texte qui pr�c�de l'expression trouv�e
            if ('' != $text=substr($source, $start, $offset-$start))
                $result.=self::unescape($text);
                        
            // Enl�ve les accolades qui entourent l'expression
            if ($expression[0]==='{') $expression=substr($expression, 1, -1);
            if (trim($expression) != '')
            {
                // Compile l'expression
                $canEval=self::parseExpression($expression, $varCallback, $pseudoFunctions);
                if ($canEval && $doEval)
                    $result.=self::evalExpression($expression);
                else
                    $result.=self::PHP_START_TAG . 'echo ' . $expression . self::PHP_END_TAG;
            }
                        
            // Passe au suivant
            $start=$offset + $len;
        }

        // Envoie le texte qui suit le dernier match 
        if ('' != $text=substr($source, $start))
            $result.=self::unescape($text);

        $source= $result;
    }


}

function dmtrim($h)
{
    $result=trim($h);
	//echo "appel de dmtrim([$h]) = [$result]<br />";
    return $result;
}


?>