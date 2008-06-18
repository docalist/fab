<?php
/*
$h='simple texte';
TemplateCompiler::findCode($h, $matches);
var_export($matches);
echo "\n";
preg_match_all(TemplateCompiler::$reCode, $h, $matches, PREG_OFFSET_CAPTURE);
var_export($matches[0]);
echo "\n";

$h='a $x b $y c $z';
TemplateCompiler::findCode($h, $matches);
var_export($matches);
echo "\n";
preg_match_all(TemplateCompiler::$reCode, $h, $matches, PREG_OFFSET_CAPTURE);
var_export($matches[0]);
echo "\n";

$h='$x b $y c $z';
TemplateCompiler::findCode($h, $matches);
var_export($matches);
echo "\n";
preg_match_all(TemplateCompiler::$reCode, $h, $matches, PREG_OFFSET_CAPTURE);
var_export($matches[0]);
echo "\n";

$h='a \$x b \$x c';
TemplateCompiler::findCode($h, $matches);
var_export($matches);
echo "\n";
preg_match_all(TemplateCompiler::$reCode, $h, $matches, PREG_OFFSET_CAPTURE);
var_export($matches[0]);
echo "\n";

$h='a $4 $_$� b';
TemplateCompiler::findCode($h, $matches);
var_export($matches);
echo "\n";
preg_match_all(TemplateCompiler::$reCode, $h, $matches, PREG_OFFSET_CAPTURE);
var_export($matches[0]);
echo "\n";

$h='a {autoId()} b {autoId()} c {autoId()} d';
TemplateCompiler::findCode($h, $matches);
var_export($matches);
echo "\n";
preg_match_all(TemplateCompiler::$reCode, $h, $matches, PREG_OFFSET_CAPTURE);
var_export($matches[0]);
echo "\n";

$h='a $x b {autoId()} c';
TemplateCompiler::findCode($h, $matches);
var_export($matches);
echo "\n";
preg_match_all(TemplateCompiler::$reCode, $h, $matches, PREG_OFFSET_CAPTURE);
var_export($matches[0]);
echo "\n";

$h="a {'{essai}'} b";
TemplateCompiler::findCode($h, $matches);
var_export($matches);
echo "\n";
preg_match_all(TemplateCompiler::$reCode, $h, $matches, PREG_OFFSET_CAPTURE);
var_export($matches[0]);

$h='a $x b {autoId()';
TemplateCompiler::findCode($h, $matches);
var_export($matches);
echo "\n";
preg_match_all(TemplateCompiler::$reCode, $h, $matches, PREG_OFFSET_CAPTURE);
var_export($matches[0]);
echo "\n";


$h="a {'{test}'} b {\"'{test}\"} c {'{te\'st}'} ";
TemplateCompiler::findCode($h, $matches);
var_export($matches);
preg_match_all(TemplateCompiler::$reCode, $h, $matches, PREG_OFFSET_CAPTURE);
var_export($matches);

die();
*/
/**
 * @package     fab
 * @subpackage  template
 * @author      Daniel M�nard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id$
 */

//echo "en ISO-8859-1 : R�sum�<br />";
//echo "en UTF-8 : ", utf8_encode('R�sum�'), "<br />";
//echo "double UTF-8 : ", utf8_encode(utf8_encode('R�sum�')), "<br />";
//echo "d�cod� : ", utf8_decode('R�sum�'), "<br />";
//die();
//
//require_once(dirname(__FILE__).'/TemplateCode.php');
//require_once(dirname(__FILE__).'/TemplateEnvironment.php');


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

    private static $nbVar=0;// nombre de variables rencontr�es dans un bloc opt /opt (cf compileOpt)

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

    private static $env;    // TemplateEnvironment 
    
    public static function autoId($name=null)
    {
        // Aucun "nom sugg�r�" : recherche le nom du parent, du grand-parent, etc.
        if (is_null($name) || $name==='')
        {
            $node=self::$currentNode;
            for(;;)
            {
                if (is_null($node) ) break;
                if ($node instanceof DOMElement)    // un DOMText, par exemple, n'a pas d'attributs
                {
                    if ($name=$node->getAttribute('id')) break;
                    if ($name=$node->getAttribute('name')) break;
                }
            	$node=$node->parentNode;
            }
            if (!$name)
                $name=self::$currentNode->tagName;
        }
        else
        {
            // si le nom sugg�r� contient des expressions, il faut les �valuer
            self::parse($name);

            $node=self::$currentNode->parentNode;
        }

        if (isset(self::$usedId[$name]))
            $name=$name.(++self::$usedId[$name]);
        else
            self::$usedId[$name]=1;

        return self::$lastId=$name;
    }
    
    public static function lastId()
    {
        return self::$lastId;   
    }
    
    /**
     * Compile un template 
     * 
     * G�n�re une exception si le template est mal form� ou contient des erreurs.
     * 
     * @param string $source le code source du template � compiler
     * @param array l'environnement d'ex�cution du template
     * 
     * @return string le code php du template compil�
     */
    public static function compile($source, $env=null)
    {
        self::$env=new TemplateEnvironment($env);

//$t=array
//(
//'a',
//'{true}',
//'$varA',
//
//'a $varAut b {trim("c")} {trim("c2")} d {trim($varA)}$varA e {trim("e2")} {trim("e3")}nll{null}f',
//);
//
//foreach($t as $hsav)
//{
//    echo 'Source : <code>', $hsav, '</code><br />';
//    
//    $h=$hsav;
//    $r=self::parse($h,true);
//    echo 'asExpression returns ', var_export($r,true), ' : <code>', htmlentities($h), '</code><br />';
//    
//    $h=$hsav;
//    self::parse($h,false);
//    echo 'asCode returns ', var_export($r,true), ' : <code>', htmlentities($h), '<code><br />';
//    echo '<hr />';
//}
//die();
        
        // Fait un reset sur les ID utilis�s
        self::$usedId=array(); // HACK: ne fonctionnera pas avec des fonctions include
        // il ne faudrait faire le reset que si c'est un template de premier niveau (pas un include)
//echo 'Source avant at<pre>*',htmlentities($source),'*</pre>';

//        self::addCodePosition($source);
//echo 'Source apr�s at<pre>',htmlentities($source),'</pre>';
//        echo '<pre>';
//echo (htmlspecialchars($source));        
//echo '</pre>';
//echo $source;        
        // Supprime les commentaires de templates : /* xxx */
        $source=preg_replace('~/\*[ \t\n\r\f].*?[ \t\n\r\f]\*/~ms', null, $source);
        
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
        $source=preg_replace($re, '$0<root strip="{true}">', $source, 1);
        $source.='</root>';
        
        // Ajoute les fichiers auto-include dans le source
        $files=Config::get('templates.autoinclude');

        // TODO: tester que le fichier existe et est lisible et g�n�rer une exception sinon 
        $h='';
        foreach((array)$files as $file)
        {
            if (false === $path=Utils::searchFile($file))
                throw new Exception("Impossible de trouver le fichier include $file sp�cifi� dans la config, searchPath=".var_export(Utils::$searchPath,true));
//            debug && Debug::log('Concat�nation du fichier include %s au source du template.file=%s, searchpath=%s', $path, $file, print_r(Utils::$searchPath, true));
            $include=file_get_contents($path);
            // Supprime les commentaires en syntaxe C pr�sents dans le include
            $include=preg_replace('~/\*\s.*?\s\*/~ms', null, $include);
            
        	$h.=$include;
        }
        if ($h) $source=str_replace('</root>', '<div test="{false}">'.$h.'</div></root>', $source);
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

//echo 'Source xml � compiler :<pre>';
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

        // Instancie tous les templates pr�sents dans le document
       self::compileMatches($xml);        
//echo 'Apr�s instanciation des templates match :<pre>';
//echo htmlentities($xml->saveXml());
////echo $xml->saveXml();
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
        ob_start();
        if ($xmlDeclaration) echo $xmlDeclaration, "\n";
        try
        {
            self::compileChildren($xml); //->documentElement
        }
        catch(Exception $e)
        {
            ob_end_clean();
            throw new Exception('Erreur dans le template : ' . $e->getMessage() . ', ligne '.self::$line . ', colonne '.self::$column);
        }
        $result=ob_get_clean();
        
        // Fusionne les blocs php adjacents en un seul bloc php  
        self::mergePhpBlocks($result);
        
//        $result=str_replace(self::PHP_END_TAG."\n", self::PHP_END_TAG."\n\n", $result);

     // Nettoyage
     // si la balise de fin de php est \r, elle est mang�e (cf http://fr2.php.net/manual/fr/language.basic-syntax.instruction-separation.php)
        $result=str_replace(self::PHP_END_TAG."\r", self::PHP_END_TAG."\r\r", $result);
        $result=str_replace(self::PHP_END_TAG."\n", self::PHP_END_TAG."\n\r", $result);
        
        $h=self::PHP_START_TAG ."\n\n";
        $name=uniqid('tpl_');
        $h.="if (! function_exists('$name'))\n";
        $h.="{\n";
            $h.="function $name()\n";
            $h.="{\n";                
            $h.=self::$env->getBindings();
            $h.="\n".self::PHP_END_TAG;
            $h.=$result;
            $h.=self::PHP_START_TAG;
            $h.="}\n";
        $h.="}\n";                
        $h.="$name();";
        $h.=self::PHP_END_TAG;                

        list(self::$loop, self::$opt, self::$env)=array_pop(self::$stack);
        return $h;
    }

    /**
     * Fusionne les blocs php adjacents en un seul bloc php
     */  
    public static function mergePhpBlocks(& $source)
    {
    	return; // d�sactiv� pour le moment, � �tudier de plus pr�s
        $endStart=preg_quote(self::PHP_END_TAG.self::PHP_START_TAG, '~');
        $search=array
        (
            // un bloc echo suivi d'un bloc echo
            '~(echo [^;]+?);?'.$endStart.'echo ~',

            // un bloc php se terminant par un point-virgule et suivi d'un autre bloc php
            '~;'.$endStart.'~',

            // cas g�n�rique : un bloc php quivi d'un autre
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
     * pass� en param�tre un appel � la pseudo fonction setCurrentPosition permettant, lors
     * du traitement des expressions de conna�tre la position (ligne,colonne) de cette expression
     * au sein du fichier source.
     * 
     * @param string $template le source du template � modifier
     * @return string le template dott� des informations de position 
     */
    public static function addCodePosition(& $template)
    {
        $lines=explode("\n",$template);
        foreach($lines as $line=> & $text)
        {
            if (preg_match_all(TemplateCompiler::$reCode, $text, $matches, PREG_PATTERN_ORDER|PREG_OFFSET_CAPTURE)>0)
                foreach(array_reverse($matches[0]) as $match)
                    $text=substr_replace($text, '{setCurrentPosition('.($line+1).','.($match[1]+1).')}',$match[1],0);   

//            if (preg_match_all('~<([A-Za-z][A-Za-z0-9]*)[^>]*/{0,1}>~', $text, $matches, PREG_PATTERN_ORDER|PREG_OFFSET_CAPTURE)>0)
//                foreach(array_reverse($matches[0]) as $match)
//                    $text=substr_replace($text, '{setCurrentPosition('.($line+1).','.($match[1]+1).')}',$match[1],0);   
        }
        $template=implode("\n", $lines);
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
        // - pour qu'ils n'apparaissent pas dans la sortie g�n�r�e (non valable comme raison : on a test="false")
        // - pour que le code d'un template ne soit pas modifi� par un autre template (� �tudier : toujours valide
        foreach($templates as $template) // todo: � garder ?
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
                // Clone le template pour cr�er le noeud r�sultat 
                $result=$template->cloneNode(true);

                // Stocke le template et le noeud en cours d'instanciation (utilis� par select())
                self::$matchNode=$node;
                self::$matchTemplate=$template;
                
                // Instancie le noeud
                self::instantiateMatch($result);
                
                // result est maintenant un tag <template> instanci�
                // on va remplacer node (le noeud match�) par le contenu de result
                
                // on ne peut pas travailler directement sur childNodes car
                // d�s qu'on fait un ajout de fils, la liste est modifi�e.
                // On commence donc par faire la liste de tous les noeuds
                // � ins�rer.
                $childs=array();
                foreach($result->childNodes as $child)
                    $childs[]=$child;
                
                foreach($childs as $child)
                    $node->parentNode->insertBefore($child, $node);
                    
                // supprime le noeud <template> d�sormais vide qui reste
                $node->parentNode->removeChild($node);
            }
        }
    }
    
    // return true si c'est du code, false si c'est une valeur
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
            
            // la fonction DOIT retourner de l'ascii, pas de l'utf-8 (cf commentaires dans instantiateMatch)
            $var=utf8_decode($var);
//            echo 'handleMatchVar : ', $attr, '=', $var, '<br />';
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
    
    /**
     * M�morise la ligne et la colonne � laquelle commence une expression.
     * 
     * Lorqu'un template doit �tre compil�, es appels � cette fonction sont ins�r�s devant chacune
     * des variables et expression pr�sentes dans le source. Lors de la compilation, la fonction
     * sera appell�e et lors de l'�valuation d'une expression, on peut alors indiquer la position
     * en cours si une erreur survient.
     * 
     * @param integer $line le num�ro de la ligne en cours
     * @param integer column le num�ro de la colonne en cours
     */
    public static function setCurrentPosition($line, $column)
    {
        self::$line=$line;
        self::$column=$column;
    }
    
    /**
     * Instancie r�curcivement un noeud s�lectionn� par un template match.
     * 
     * L'instanciation consiste � :
     * 
     * <li>pour chacun des attributs indiqu�s dans le tag template, remplacer les variables 
     * utilis�es dont le nom correspond au nom de l'attribut par la valeur de cet attribut ou par la 
     * valeur sp�cifi�e par le noeud instanci� si celui-ci a �galement sp�cifi� l'attribut.
     * 
     * <li>ex�cuter les appels � la fonction select()
     * 
     * @param DOMNode $node le noeud � instancier
     * @return void
     */
    public static function instantiateMatch(DOMNode $node)
    {
        if ($node instanceOf DOMCdataSection)
        {
            //self::dumpNodes($node, 'Section CDATA');
            return;
        }
        
        // Traite tous les attributs du noeud
        if ($node->hasAttributes())
            foreach ($node->attributes as $attribute)
                self::instantiateMatch($attribute);

        /*
        
         probl�mes d'encodage...
         en gros, on fait un preg_match sur le contenu du noeud en demandant � r�cup�rer les offset et ensuite, on fera un
         replaceData � l'offset obtenu et sur la longueur du match
         Le probl�me, c'est que DOM travaille en utf-8. Donc $node->data est une chaine en utf-8. preg_match ne g�re
         pas �a bien : les offset retourn�s seront des offset d'octets et non pas des offset de caract�res.
         replaceData, elle, travaille en utf-8. Donc elle attend des offset de caract�res et non pas des offset d'octets.
         Si on ne fait rien, on aura un "d�calage", �gal au nombre de caract�res cod�s sur plus de un octet pr�c�dant la
         chaine � remplacer.
         La solution trouv�e consiste � passer � preg_match une chaine ansi et non pas une chaine utf8.
         Du coup, les offset retourn�s sont toujours des offset octets, mais sont strictement identiques aux ofssets caract�res
         qui auraient �t� retourn�s si preg_match g�rait correctement l'utf-8.
         Du coup, le replaceData fonctionne correctement...
         
          DM+YL, 06/04/07
          
         Pr�cisions (23/04/06, DM+YL+SF)
         En fait le correctif n'est pas suffisant.
         - on a le DOM qui est en UTF-8
         - on d�code, pour que le preg_match fonctionne
         - chaque $x ou {} est �valu�.
         - Le r�sultat vient de php, donc c'est de l'ansi, donc il faut l'encoder, sinon on va ins�rer de l'ascii dans de l'utf
            -> donc on encode syst�matiquement
         - Probl�me : tous les r�sultats ne viennent pas de php :
            - s'il s'agit d'un attribut, on retourne la valeur de cet attribut, donc c'est d�j� de l'utf8. 
            Comme on r�encode syst�matiquement, on a un double encodage
            - si l'expression est un select qui retourne du texte exemple : {select('string(@label)')}, idem
            -> donc les fonctions handleMatchVar() et select() doivent d�coder le r�sultat, sachant que celui-ci
            sera ensuite r� encod� avant d'�tre ins�r� dans la chaine utf8
         c'est compl�tement batard comme code... mais on n'a pas mieux pour le moment
         
         source est en utf8
         (offset,code)=pregmatch(source, '$xx et {}')
         ->offset sont en octets, pas en utf8
         result=eval(code)
         replace(source, code, result)
           
         source est en utf8
         (offset,code)=pregmatch(decode(source), '$xx et {}')
         result=eval(code)
         replace(source, code, result)
         -> result est en ascii, on ins�re de l'ascii dans de l'utf

         source est en utf8
         (offset,code)=pregmatch(decode(source), '$xx et {}')
         result=eval(code)
         replace(source, code, encode(result))
         -> si result pas en ascii (cas d'un select ou d'un matchVar), double encodage

         source est en utf8
         (offset,code)=pregmatch(decode(source), '$xx et {}')
         result=eval(code) // avec hacks dans handleMatchVar et select : retourne decode(result)
         replace(source, code, encode(result))

--------
        -> si on inverse la logique (tout convertir en ascii, faire le traitement puis reconvertir tout en utf)
         convertir source en ascii
         (offset,code)=pregmatch(source, '$xx et {}')
         result=eval(code) // avec hacks dans handleMatchVar et select : retourne decode(result) (doivent retourne de l'ascii, pas de l'utf)
         replace(source, code, result)
         reconvertir source en utf8
        -> pas mieux                                                                                                                
                                                                                                                                    

         */
        // Ex�cute le code pr�sent dans les donn�es du noeud
        if ($node instanceof DOMCharacterData) // #text, #comment... pour les PI :  || $node instanceof DOMProcessingInstruction
        {
            $matches=null;
//            if (preg_match_all(self::$reCode, utf8_decode($node->data), $matches, PREG_PATTERN_ORDER|PREG_OFFSET_CAPTURE)>0)
            if (self::findCode(utf8_decode($node->data), $matches))
            { 
                // Evalue toutes les expressions dans l'ordre o� elles apparaissent
//                foreach($matches[0] as & $match)
                foreach($matches as & $match)
                {
                	// Initialement, $match contient : 
                    //    $match[0] = l'expression trouv�e
                    //    $match[1] = l'offset de l'expression dans data
                    // on va y ajouter
                    //    $match[2] = le r�sultat de l'�valuation de l'expression
                    //    $match[3] = les noeuds �ventuels � ins�rer devant expression si elle contient un appel � select()
                    
                    // R�cup�re l'expression � ex�cuter
                    $code=$match[0];

                    // Evalue l'expression
                    self::$selectNodes=null; // si select() est utilis�e, on aura en sortie les noeuds s�lectionn�s
//                    echo 'Code avant parseExpression: ', $code, '<br />';
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
//                    echo 'Code apr�s parseExpression: ', $code, '<br />';
                    
                    if ($canEval) $code=TemplateCode::evalExpression($code);
//                    echo 'Code apr�s evalExpression: ', $code, '<br />';
                    
                    // Stocke le r�sultat
                    $match[2]=$code;
                    $match[3]=self::$selectNodes; // les noeuds �ventuels retourn�s par select et qu'il faut ins�rer
                }

                // Remplace l'expression par sa valeur et ins�re les noeuds s�lectionn�s par select()
                
                // On travaille en ordre inverse pour deux raisons :
                // - l'offset de l'expression reste valide jusqu'� la fin
                // - apr�s un splitText, le noeud en cours ne change pas
//                foreach(array_reverse($matches[0]) as $match)
                foreach(array_reverse($matches) as $match)
                {
                	// Remplace l'expression par sa valeur
//                    echo 'Node before <pre>',$node->nodeValue,'</pre>';
//                    echo 'replaceData, offset=', $match[1], ', len=', strlen($match[0]), ', replacewith=', $match[2], "\n";
                    $node->replaceData($match[1], strlen($match[0]), utf8_encode($match[2]));
//                    echo 'Node after <pre>',$node->nodeValue,'</pre>';
                    
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
        
        // Traite les descendants
        if ($node->hasChildNodes())
            foreach ($node->childNodes as $child)
                self::instantiateMatch($child);
    }

    /**
     * Ex�cute les appels � 'select()' pr�sents dans un template match.
     * 
     * La fonction �value l'expression xpath indiqu�e par rapport au noeud en cours
     * (cf {@link $matchNode}). Si le r�sultat est un scalaire, il est retourn� ; s'il 
     * s'agit d'un noeud ou d'un ensemble de noeuds, ils sont stock�s dans 
     * {@link $selectNodes}
     * 
     * @param string $xpath l'expression xpath � ex�cuter
     * @return mixed le scalaire retourn� par l'expression xpath ou null si le r�sultat
     * n'est pas un scalaire
     */
    public static function select($xpath=null)
    {
        // V�rifie que le nombre d'arguments pass�s en param�tre est correct
    	if (func_num_args()!==1)
            throw new Exception('la fonction select() prends un et un seul argument');

        // Ex�cute l'expression xpath
        $xpather=new DOMXPath(self::$matchNode->ownerDocument);
        if (false === $nodeSet=$xpather->evaluate($xpath, self::$matchNode))
            throw new Exception("Erreur dans l'expression xpath [$xpath]");

        // $selectNodes va contenir les noeuds retourn�s 
        self::$selectNodes=null;

        // Si le r�sultat est un scalaire (un entier, une chaine...), on le retourne tel quel
        // la fonction DOIT retourner de l'ascii, pas de l'utf-8 (cf commentaires dans instantiateMatch)
        if (is_scalar($nodeSet)) return utf8_decode($nodeSet);

        // Si le r�sultat est un ensemble vide, rien � faire
        if ($nodeSet->length==0) return;
            
        // Stocke la liste des noeuds � ins�rer
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
            'case'=>'caseError',
            'default'=>'caseError',
            'opt'=>'compileOpt',
            'fill'=>'compileFill',
            'input'=>'compileFillControls',
            'option'=>'compileFillControls',
            'tag'=>'compileTag',
            'slot'=>'compileSlot',
            'def'=>'compileDef'
        );
        
        self::$currentNode=$node;
        switch ($node->nodeType)
        {
            case XML_TEXT_NODE:     // du texte
                $h=$node->nodeValue;
                self::parse($h);
                echo $h;
                return;

            case XML_COMMENT_NODE:  // un commentaire
                if (Config::get('templates.removehtmlcomments')) return;
                echo $node->ownerDocument->saveXML($node);
                // Serait plus efficace : $node->ownerDocument->save('php://output');
                return;
                
            case XML_PI_NODE:       // une directive (exemple : <?xxx ... ? >)
                throw new Exception('Les directives "'.$node->target.'" sont interdites dans un template ' . $node->data);
                echo $node->ownerDocument->saveXML($node);
                // Serait plus efficace : $node->ownerDocument->save('php://output');
                return;
                
            case XML_ELEMENT_NODE:  // un �l�ment
            
                // R�cup�re le nom du tag
                $name=$node->tagName;
                
                // S'il s'agit de l'un de nos tags, appelle la m�thode correspondante
                if (isset($tags[$name]))
                    if (true !== call_user_func(array('TemplateCompiler', $tags[$name]), $node)) return;

                self::compileElement($node);
                return;
                
            case XML_DOCUMENT_NODE:     // L'ensemble du document xml
                self::compileChildren($node);
                return;
                
            case XML_DOCUMENT_TYPE_NODE:    // Le DTD du document
                echo $node->ownerDocument->saveXML($node), "\n";
                return;
            
            case XML_CDATA_SECTION_NODE:    // Une section CDATA
                echo htmlspecialchars($node->nodeValue);
                //echo $node->ownerDocument->saveXML($node);
                return;

            case XML_ENTITY_REF_NODE:
            case XML_ENTITY_NODE:
                echo $node->ownerDocument->saveXML($node);
                return;
                            
            default:
                throw new Exception("Impossible de compiler le template : l'arbre obtenu contient un type de noeud non g�r� ($node->nodeType)");
        }
    }

    private static function route($value)
    {
        $canEval=self::parse($value,true);

        // Si l'expression est �valuable, on fait le routage � la compilation (requiert de recompiler les templates si on change les routes)
        if ($canEval)
            return Routing::linkFor(TemplateCode::evalExpression($value));
        
        // Sinon, le routage sera d�termin� � l'ex�cution
        else
            return self::PHP_START_TAG .'echo Routing::linkFor('.$value.')' . self::PHP_END_TAG;
    }
    
    /**
     * Compile un �l�ment <tag name="">
     * 
     * G�n�re le tag dont le nom est pass� en param�tre dans l'attribut name.
     * Name doit �tre un nom d'�l�ment valide (que des lettres)
     * Si name est absent ou est vide, fait la m�me chose qu'un strip (seul le contenu du
     * tag est g�n�r�)
     * Si name est une expression, celle-ci doit pouvoir �tre �valu�e � la compilation.
     */
    private static function compileTag(DOMElement $node)
    {
        if (! $node->hasAttribute('name'))
            return self::compileChildren($node);
        
        $name=$node->getAttribute('name');
        if ($name==='')
        	return self::compileChildren($node);

        if (! self::parse($name, true))
            throw new Exception("L'attribut name d'un �l�ment <tag> doit pouvoir �tre �valu� � la compilation");
        $name=TemplateCode::evalExpression($name);                
        $node->removeAttribute('name');
        try
        {
        $newNode=$node->ownerDocument->createElement($name);
        }
        catch (Exception $e)
        {
        	throw new Exception("Le nom $name indiqu� dans l'attribut name de l'�l�ment <tag> n'est pas valide");
        }
        if ($node->hasAttributes())
            foreach ($node->attributes as $key=>$attribute)
                $newNode->setAttribute($attribute->nodeName, $attribute->nodeValue);
        
        if ($node->hasChildNodes())
            foreach ($node->childNodes as $child)
                $newNode->appendChild($child->cloneNode(true));
        
        self::compileElement($newNode);
    }
    
    private static function compileElement(DOMElement $node, $attrPhpCode=null)
    {
        // liste des attributs pour lesquels il faut appliquer le routage (Routing::linkFor)
        // Pour chaque tag, on a un tableau contenant la liste des attributs � router
        static $attrToRoute=array
        (
            'a'         => array('href'=>true),
            'img'       => array('src'=>true),
            'form'      => array('action'=>true),
            'frame'     => array('src'=>true),
            'iframe'    => array('src'=>true),
            'link'      => array('href'=>true),
            'script'    => array('src'=>true),
            'link'      => array('href'=>true),
        );
        
        // G�re l'attribut "test" : supprime tout le noeud si l'expression retourne false
        $test='';
        if ($node->hasAttribute('test'))
        {
            $test=$node->getAttribute('test');
            $canEval=self::parse($test,true);

            // Si le test est �valuable, on teste maintenant
            if ($canEval)
            {
                // Si le test s'�value � 'false', termin� (on ignore le noeud)
                if (false == TemplateCode::evalExpression($test)) return;
                
                // Sinon, on g�n�re tout le noeud sans condition
                $test='';
            }
            
            // Si le test n'est pas �valuable, on encadre le noeud par un bloc php "if($test)"
            else
            {
                echo self::PHP_START_TAG, "if($test):", self::PHP_END_TAG;
            }
            
            // Supprime l'attribut "test" du noeud en cours
            $node->removeAttribute('test');
        }

        // G�re l'attribut "strip" : ne garde que le contenu du noeud si l'expression retourne true
        $strip='';
        if ($node->hasAttribute('strip'))
        {
            $strip=$node->getAttribute('strip');
            $canEval=self::parse($strip,true);

            // Si le strip est �valuable, on teste maintenant
            if ($canEval)
            {
                // Si strip s'�value � 'false', on g�n�re toujours les tags ouvrant et fermants
                if (false == TemplateCode::evalExpression($strip))
                    $strip='';
                    
                // Strip s'�value � 'true', on ne g�n�re que le contenu du noeud
                else
                    return self::compileChildren($node);
            }
            
            // Si le strip n'est pas �valuable, ajoute un test php "if($strip)" autour du tag ouvrant et du tag fermant
            else
            {
                $keepTag=self::$env->getTemp('keeptag');
                echo self::PHP_START_TAG, "if($keepTag=!($strip)):", self::PHP_END_TAG;
            }

            // Supprime l'attribut "strip" du noeud en cours
            $node->removeAttribute('strip');
        }

        $name=$node->tagName;

        // G�n�re le d�but du tag ouvrant
        echo '<', $name;    // si le tag a un pr�fixe, il figure d�j� dans name (e.g. <test:h1>)

        // cas particulier de l'attribut xmlns
        if ($node->namespaceURI !== $node->parentNode->namespaceURI)
            echo ' xmlns="', $node->namespaceURI, '"'; 
            
        // Acc�s aux attributs xmlns : cf http://bugs.php.net/bug.php?id=38949
        // apparemment, fix� dans php > 5.1.6, � v�rifier
            
        // G�n�re tous les attributs
        if ($node->hasAttributes())
        {
            $flags=0;
            foreach ($node->attributes as $key=>$attribute)
            {
                $value=$attribute->value;
                //if ($value ==='') continue;
                $attr=$attribute->nodeName;
                
                // Teste si ce tag contient des attributs qu'il faut router
                if (isset($attrToRoute[$name]) && isset($attrToRoute[$name][$attr]))
//                if (isset($attrToRoute[$name]) && isset($attrToRoute[$name][$attr]) && (substr($value, 0, 1) === '/'))
                {
                    $value=self::route($value);
                    echo ' ', $attr, '="', $value, '"';
                }
                else
                {
                    ++self::$opt;
                    self::parse($value,false,$flags);
                    --self::$opt;
    
                    if ($value==='') continue;
                    $quot=(strpos($value,'"')===false) ? '"' : "'";
                    
                    // Si l'attribut ne contient que des variables (pas de texte), il devient optionnel
                    if ($flags===2)
                    {
                        echo self::PHP_START_TAG, 'Template::optBegin()', self::PHP_END_TAG;
                        echo ' ', $attr, '=', $quot, $value, $quot;
                        echo self::PHP_START_TAG, 'Template::optEnd()', self::PHP_END_TAG; 
                    }
                    else
                        echo ' ', $attribute->nodeName, '=', $quot, $value, $quot;
                }
            }
        }
        if (!is_null($attrPhpCode))
            echo self::PHP_START_TAG, $attrPhpCode, self::PHP_END_TAG;
            
        // Tag vide
        if (self::isEmptyTag($node))
        {
            echo ' />';
            if ($strip !== '')                     
                echo self::PHP_START_TAG, 'endif;',self::PHP_END_TAG;
        }
            
        // G�n�re tous les fils et la fin du tag
        else
        {
            echo '>';
            if ($strip !== '')                     
                echo self::PHP_START_TAG, 'endif;',self::PHP_END_TAG;
            self::compileChildren($node);
            if ($strip !== '')                     
            {
                echo self::PHP_START_TAG, "if ($keepTag):",self::PHP_END_TAG;
                self::$env->freeTemp($keepTag);
            }
            echo '</', $name, '>';
            if ($strip !== '')                     
                echo self::PHP_START_TAG, 'endif;',self::PHP_END_TAG;
        }
            
        if ($test !== '')                     
            echo self::PHP_START_TAG, 'endif;',self::PHP_END_TAG;
        
    	
    }
    
    /**
     * Teste si le noeud pass� en param�tre est vide et peut �tre �crit sous forme courte (ie sans tag de fin).
     * 
     * @param DOMNode $node le noeud � examiner
     * @return boolean true si le noeud pass� en param�tre ne contient aucun fils et s'il est d�clar� comme
     * ayant un content-model �gal � "empty" dans les DTD de xhtml.  
     */
    private static function isEmptyTag(DOMNode $node)
    {
        static $empty=null;

        if ($node->hasChildNodes()) return false;
        
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

        return (isset($empty[$node->tagName]));
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

    /**
     * Compile un bloc &lt;opt&gt;&lt;/opt&gt;   
     *
     * @param DOMNode $node le noeud � compiler
     */
    private static function compileOpt(DOMNode $node)
    {
        // Opt accepte un attribut optionnel min qui indique le nombre minimum de variables
        $t=self::getAttributes($node, null,array('min'=>''));
        
        // Reset du nombre de var contenu dans le bloc opt
        $save=self::$nbVar;
        self::$nbVar=0;

        // compile le contenu
        ++self::$opt;
        ob_start();
        self::compileChildren($node);
        $content=ob_get_clean();
        --self::$opt;

        // G�n�re le code
        if (self::$nbVar===0)
        {
            echo $content; // aucune variable dans le bloc, ce n'est pas un bloc optionnel
            // Restaure le nombre de var (au xa o� on il est des blocs opt ascendants)
            self::$nbVar=$save;
        }
        else
        {
            echo self::PHP_START_TAG, 'Template::optBegin()', self::PHP_END_TAG,
                 $content,
                 self::PHP_START_TAG, 'Template::optEnd('.$t['min'].')', self::PHP_END_TAG;
 
            // Restaure le nombre de var (au xa o� on il est des blocs opt ascendants)
            self::$nbVar=$save+1;
        }

    }

    /**
     * R�cup�re et v�rifie les attributs obligatoires et optionnels d'un tag.
     * 
     * La fonction prend en param�tres le noeud � examiner et deux tableaux :
     * - un tableau dont les valeurs sont les attributs obligatoires
     * - un tableau dont les cl�s sont les attributs optionnels et dont les
     * valeurs sont les valeurs par d�faut de ces attributs.
     * 
     * La fonction examine tous les attributs du noeud pass� en param�tre.
     * Elle g�n�re une exception si :
     * - un attribut obligatoire est absent
     * - le noeud contient d'autres attributs que ceux autoris�s.
     * 
     * Elle retourne un tableau listant tous les attributs avec comme valeur
     * la valeur pr�sente dans l'attribut si celui-ci figure dans le noeud ou
     * la valeur par d�faut s'il s'agit d'un attribut optionnel absent du noeud.
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
        
        // Examine les attributs pr�sents dans le noeud
        if ($node->hasAttributes())
        {
            foreach ($node->attributes as $name=>$attribute)
            {
                // C'est un attribut obligatoire, il est pr�sent
                if (isset($required[$name]))
                {
                    $result[$name]=$attribute->value;
                    unset($required[$name]);
                }
                    
                // C'est un attribut optionnel, il est pr�sent
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

        // G�n�re une exception s'il manque des attributs obligatoires ou si on a des attributs en trop 
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
        
        // Compl�te le tableau r�sultat avec les attributs optionnels non pr�sents
        return $result+$optional;
    }   
    
    /**
     * Compile des blocs if/elseif/else cons�cutifs
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
        $close=false;
        $done=false;
        $lastWasFalse=false;
        $first=true;
        for(;;)
        {
            // G�n�re le tag
            if (!$done) switch($tag=$next->tagName)
            {
                case 'else':
                    self::getAttributes($next); // aucun attribut n'est autoris�
                    if (! $lastWasFalse)
                    {
                        echo self::PHP_START_TAG, $tag, ':', self::PHP_END_TAG;
                        $close=true;
                    }
                    $elseAllowed=false;
                    self::compileChildren($next);
                    
                    break;

                case 'elseif':
                    
                case 'if':
                    $t=self::getAttributes($next, array('test'));
                    $canEval=self::parse($t['test'],true);
                    if ($t['test']=='') $t['test']='false';
                    $lastWasFalse=false;
                    
                    // Si le test est �valuable, on teste maintenant
                    if ($canEval)
                    {
                        // on a un if(true)  ou un elseif(true)
                        if (true==TemplateCode::evalExpression($t['test']))
                        {
                            // ne pas g�n�rer de condition (si c'est un if, pas de condition, si c'est un elseif, devient un else)

                            if ($close)
                                echo self::PHP_START_TAG, 'else', ':', self::PHP_END_TAG;
                                
                            // G�n�re le bloc (les fils)
                            self::compileChildren($next);
                            $done=true;
                        }
                        // on a un if(false)  ou un elseif(false)
                        else
                        {
                        	// ignorer le noeud
                            // si prochain tag=elseif, g�n�rer un if
                            $lastWasFalse=true;
                        }
                        
                        // Sinon, on g�n�re tout le noeud sans condition
                        $test='';
                    }

                    // Sinon, g�n�re le tag et sa condition
                    else
                    {
                        if ($first) $tag='if';
                        echo self::PHP_START_TAG, $tag, '(', $t['test'], '):', self::PHP_END_TAG;
                        $first=false;
                        $close=true;
                        // G�n�re le bloc (les fils)
                        self::compileChildren($next);
                    }

                    break;
            }
                        
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
        if ($close) echo self::PHP_START_TAG, 'endif;', self::PHP_END_TAG;
        
        // Supprime tous les noeuds qu'on a trait�
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
     * @param DOMNode $node le noeud � compiler
     */
    private static function compileSwitch(DOMNode $node)
    {
        // R�cup�re la condition du switch
        $t=self::getAttributes($node, null, array('test'=>true));
        $canEval=self::parse($t['test'],true);
                
        // G�n�re le tag et sa condition
        echo self::PHP_START_TAG, 'switch (', $t['test'], '):', "\n";
                        
        // G�n�re les fils (les blocs case et default)
        self::compileSwitchCases($node);

        // Ferme le switch
        echo self::PHP_START_TAG, 'endswitch;', self::PHP_END_TAG;
    }

    private static function compileSwitchCases($node)
    {
        $first=true;
        $seen=array(); // Les conditions d�j� rencontr�es dans les diff�rents case du switch
        
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
                            $t=self::getAttributes($node, array('test'));
                            if (isset($seen[$t['test']]))
                                throw new Exception('Switch : plusieurs blocs case avec la m�me condition');
                            $seen[$t['test']]=true;
                            $canEval=self::parse($t['test'],true);
                            echo ($first?'':self::PHP_START_TAG.'break;'), 'case ', $t['test'], ':', self::PHP_END_TAG;
                            self::compileChildren($node);
                            break;
                        case 'default':
                            $t=self::getAttributes($node); // aucun attribut autoris�
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

    private static function caseError(DOMNode $node)
    {
        throw new Exception('Tag '.$node->tagName.' isol�. Ce tag ne peut appara�tre que dans un bloc switch, seuls des blancs sont autoris�s entre les deux.');
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
//        if (($on=$node->getAttribute('on')) === '')
//            throw new Exception("Tag loop incorrect : attribut 'on' manquant");
            
        $t=self::getAttributes($node, array('on'), array('as'=>'$key,$value', 'max'=>''));

        // Enl�ve les accolades qui entourent l'expression
        // HACK : ne devrait pas �tre l�, int�grer dans un wrapper autour de parseExpression
        if ($t['on'][0]==='{') $t['on']=substr($t['on'], 1, -1);
            
        TemplateCode::parseExpression($t['on'],
                                    'handleVariable',
                                    array
                                    (
                                        'setcurrentposition'=>array(__CLASS__,'setCurrentPosition'),
                                        'autoid'=>array(__CLASS__,'autoid'),
                                        'lastid'=>array(__CLASS__,'lastid')
                                    )
        );
            
        // R�cup�re et traite l'attribut as
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
        
        $on=self::$env->getTemp('on');
        
        echo self::PHP_START_TAG,
            "$on=$t[on];", 
            "if (! is_array($on) && ! $on instanceOf Traversable && !is_object($on)) throw new Exception('loop sur objet non iterable');",
            ($max?"$max=0;\n":''), 
            "foreach($on as $keyReal=>$valueReal):", self::PHP_END_TAG;
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

/*
  
On peut avoir :

- Un slot vide :
    <slot name="toto" />

- Un slot avec un contenu initial fix� en dur :
    <slot name="toto">
        bla bla
    </slot>

- Un slot avec un contenu initial provenant d'un autre template :
    <slot name="toto" file="menu.html" />

- Un slot avec un contenu provenant d'un module action :
    <slot name="toto" action="/base/search" max="10" sort="-" cart="{$this->getCart()}">
        bla bla
    </slot>
    
Un slot peut avoir les attributs standard "test" et "strip". Ils sont d�finis et g�r�s de fa�on
"absolue", c'est � dire que m�me si le contenu du slot change, les conditions restent (en fait, elles
sont �valu�es avant m�me qu'on commence � essayer d'ex�cuter le slot)

Code de compilation :
- r�cup�rer name, exception si absent ou vide
- parser sous forme d'expression php
- g�n�rer :
    - si le noeud a des fils non vide :
        - php if (runSlot($name)) /php
        - contenu du noeud (compileChildrend())
        - php endif /php
    - sinon
        - php runSlot($name) /php

Fonctionnement de runSlot :
Dans la config, on a les d�finitions des slots :
    - slots:
        - footer:
            enabled: true
            file: sidebar.tml
        - sidebar:
            enabled: true
            action: /blog/recent
            
runSlot examine la config en cours pour savoir s'il faut examiner le noeud ou pas.
si enabled=false : return false (ne pas ex�cuter le slot, ne pas afficher le contenu par d�faut)
si file="" et action="" return true (afficher le contenu par d�faut du slot)
si file : Template::Run(file, currentdatasources)
sinonsi action : Routing::dispatch(action, currentdatasource) 
runSlot retourne true s'il faut afficher le contenu par d�faut du noeud
return false (ne pas afficher le contenu par d�faut)

*/    
    private static function compileSlot($node)
    {
        // R�cup�re le nom du slot
        if (($name=$node->getAttribute('name')) === '')
            throw new Exception("Tag slot incorrect : attribut 'name' manquant");
        self::parse($name, true);
        $node->removeAttribute('name');
        
        // V�rifie que le slot ne sp�cifie pas � la fois une action et un contenu par d�faut
        if ($node->hasAttribute('action') && $node->hasChildNodes())
            throw new Exception('Un tag slot peut sp�cifier soit une action soit un contenu par d�faut mais pas les deux');

        // R�cup�re l'action par d�faut
        $action=$node->getAttribute('action');
        if ($action==='') $action="''"; else self::parse($action, true);
        $node->removeAttribute('action');

        if ($node->hasAttributes())
        {
            $args='array(';
            foreach($node->attributes as $attribute)
            {
                $value=$attribute->value;
                self::parse($value,true);
                $args.='\'' . $attribute->nodeName . '\'=>' . $value . ',';
            }
            $args=rtrim($args,',');
            $args.=')';
        }
        else
            $args='null';
        
        // G�n�re le code 
        if ($node->hasChildNodes())
        {
            echo self::PHP_START_TAG, 'if(Template::runSlot(',$name, ',', $action, ',', $args, ')){', self::PHP_END_TAG;
            self::compileChildren($node);
            echo self::PHP_START_TAG, '}', self::PHP_END_TAG;
        }
        else
        {
            echo self::PHP_START_TAG, 'Template::runSlot(',$name, ',', $action, ',', $args, ')', self::PHP_END_TAG;
        }
    }
    
    private static function compileDef($node)
    {
        $t=self::getAttributes($node, array('name','value'));
        self::parse($t['name'],false);
        self::parse($t['value'],true);
        
        // Cr�e un nom unique pour la variable
        $def=self::$env->getTemp($t['name']); // pour �tre sur de ne pas �craser une var existante
        self::$env->freeTemp($t['name']);       // lib�r�e aussit�t : si le def est red�fini, la m�me va rsera utilis�e
        
        self::$env->push(array($t['name']=>$def));
        
        echo self::PHP_START_TAG, $def,'=', $t['value'], self::PHP_END_TAG;
        
    }
    /* ======================== EXPRESSION PARSER ============================= */
    
    /**
     * Analyse une chaine de caract�res contenant � la fois du texte et du code
     * (variables ou expressions entre accolades).
     * 
     * Si asExpression vaut true, la chaine est retourn�e sous la forme d'une 
     * expression php dans laquelle le texte statique est convertit en chaine
     * et concat�n� aux expressions figurant dans le code.
     * 
     * Si asExpression vaut false, la chaine est retourn�e sous la forme d'un
     * code source dans lequel le texte statique est inchang� et les expressions
     * figurant dans le code sont converties en blocs php contenant un appel � echo.
     * 
     * Exemple :
     * Source analys�     : a $x b {trim('c')} d {trim($x)} e
     * asExpression=false : a <?php echo $x?> b c d <?php echo trim($x)?> e   
     * asExpression=true  : 'a '.$x.' b c d '.trim($x).' e'
     * 
     * Remarque : si le code contient une expression qui est �valuable (par
     * exemple trim('c') dans l'exemple ci-dessus), l'expression est remplac�e par
     * le r�sultat de son �valuation et est ensuite trait�e comme s'il s'agissait
     * de texte statique.
     * 
     * @param string & $source la chaine de caract�res � analyser
     * 
     * @param boolean $asExpression true si le source doit �tre retourn� sous 
     * la forme d'une expression php, false (valeur par d�faut) si l'expression
     * doit �tre retourn�e sous forme de code
     * 
     * @return boolean true si l'expression �tait �valuable, false sinon.
     * 
     * La valeur de retour est int�ressante lorsque asExpression=true car elle permet � 
     * l'appelant de savoir qu'il peut faire un eval() sur l'expression obtenue (la 
     * valeur true retourn�e signifie que l'expression retourn�e est une constante :
     * elle ne contient ni variables ni appels de fonctions).
     * 
     * Quand asExpression=false et que la fonction retourne true, cela signifie que le 
     * code retourn� ne contient aucun bloc php, il ne contient que du texte.
     */
    public static function findCode($source, & $matches, $start=0)
    {
//        echo '<hr />Source : <code style="background-color: yellow"><br>',$source,'<br>012345678901234567890123456789</code><br />';
        
        $matches=array();
        $end=$start;
        for($iii=0;$iii<10;$iii++)
        {
            // Recherche la position du prochain '$' ou '{' dans la chaine
            $start+=$len=strcspn($source, '${', $end);

            // Non trouv�, termin�
            if ($start >= strlen($source)) break;
            
            // Si le caract�re est pr�c�d� d'un antislah, on l'ignore
            if ($start>0 && $source[$start-1]==='\\') 
            {
                $end=++$start;
                continue;	
            } 
            
            // Recherche la fin du nom de variable
            if ($source[$start]==='$')
            {
                while ($start+1<strlen($source) && $source[$start+1]==='$') $start++;
                
                for($end=$start+1; $end<strlen($source); $end++)
                	if (! ctype_alnum($source[$end]) && $source[$end]!='_') break;
                $code=substr($source, $start, $end-$start);
                if (! preg_match('~\$[A-Za-z][A-Za-z0-9_]*~', $code))
                    throw new Exception('Nom de variable incorrect : ' . $code . ' ('.$source.')');
                $matches[]=array($code, $start);
            }

            // Recherche la fin de l'expression
            else
            {
                $curly=1;
                $quot=false;
                $apos=false;

                for($end=$start+1; $end<strlen($source); $end++)
                {
                    switch ($source[$end])
                    {
                    	case '{':
                            if ($quot or $apos) break;
                            $curly++;
                            break;
                        case '}':
                            if ($quot or $apos) break;
                            if ($source[$end-1]==='\\') break;   
                            $curly--;
                            if ($curly===0) break 2;
                            break;
                        case '"':
                            if ($apos) break; // un " dans une chaine encadr�es de guillemets simples
                            if ($quot && $source[$end-1]==='\\') break; // \" dans une chaine encadr�e de guillemets doubles
                            $quot=!$quot;
                            break;
                        case '\'':
                            if ($quot) break; // un ' dans une chaine encadr�es de guillemets doubles
                            if ($apos && $source[$end-1]==='\\') break; // \' dans une chaine encadr�e de guillemets simples
                            $apos=!$apos;
                            break;
                    }
                }
                if ($curly) 
                    echo 'Erreur : accolade fermante attendue dans l\'expression '.$source, ', curly=', $curly, '<br />';
                    
                $code=substr($source, $start, $end-$start+1);
                $matches[]=array($code, $start);
            }
            
            $start=$end; // += la longueur de l'expression
        }
        return count($matches);
    }
    
    // retourne flags : 0=que du texte, 1=texte+code, 2=que du code
    public static function parse( & $source, $asExpression=false, & $flags=null)
    {
        // Boucle tant qu'on trouve des choses dans le source pass� en param�tre
        $start=0;
        $result='';
        $canEval=true;
        
        $pieces=array(); // chaque �l�ment est un tableau. 0: flag, true=texte statique, false=code, 1: le bout d'expression
        $static=false;  // true si le dernier �l�ment ajout� � $pieces �tait du texte statique
        $nb=-1;
        $match=null;

        $hasCode=false;
        $hasText=false;
        
        for($i=1;;$i++)
        {
            // Recherche la prochaine expression
//            if (preg_match(self::$reCode, $source, $match, PREG_OFFSET_CAPTURE, $start)==0) break;
            if (self::findCode($source, $match, $start)==0) break;
            $expression=$match[0][0];
//            echo 'Expression : <code style="background-color: yellow">',$expression,'</code><br />';
            $len=strlen($expression);
            $offset=$match[0][1];
            
            // Envoie le texte qui pr�c�de l'expression trouv�e
            if ($offset>$start)
            {
                $hasText=true;
                if ($static)
                    $pieces[$nb][1].=self::unescape(substr($source, $start, $offset-$start));
                else
                    $pieces[++$nb]=array($static=true,self::unescape(substr($source, $start, $offset-$start)));
            }
            
            // Enl�ve les accolades qui entourent l'expression
            if ($expression[0]==='{') $expression=substr($expression, 1, -1);
            if (trim($expression) != '')
            {
                // Compile l'expression
                if
                (
                        TemplateCode::parseExpression
                        (
                            $expression, 
                            'handleVariable', 
                            array
                            (
                                'setcurrentposition'=>array(__CLASS__,'setCurrentPosition'),
                                'autoid'=>array(__CLASS__,'autoid'),
                                'lastid'=>array(__CLASS__,'lastid'),
                            )
                        )
                )
                {
                    $expression=TemplateCode::evalExpression($expression);
                    if (! is_null($expression) )
                    {
                        $hasText=true;
                        if ($static)
                            $pieces[$nb][1].=$expression;
                        else
                            $pieces[++$nb]=array($static=true,$expression);
                    }
                }
                else
                {
                    if ($expression !== 'NULL') // le r�sultat retourn� par var_export(null)
                    {
                        $hasCode=true;
                        $pieces[++$nb]=array($static=false,$expression);
                        $canEval=false;
                    }
                }
            }
                        
            // Passe au suivant
            $start=$offset + $len;
        }

        // Envoie le texte qui suit le dernier match 
        if ($start < strlen($source))
        {
            $hasText=true;
            if ($static)
                $pieces[$nb][1].=self::unescape(substr($source, $start));
            else
                $pieces[++$nb]=array($static=true,self::unescape(substr($source, $start)));
        }
            
        // G�n�re le r�sultat
        $source='';
        
        // Sous forme d'expression
        if ($asExpression)
        {
            foreach($pieces as $i=>$piece)
            {
                if($i) $source.='.';
                if ($piece[0]) 
                    $source.=var_export($piece[1],true); 
                else 
                    $source.=$piece[1];            	
            }
        }
        
        // Sous forme de code php
        else
        {
            $piece=reset($pieces);
            while($piece!==false)
            {
                if ($piece[0])
                { 
                    $source.=$piece[1];
                    $piece=next($pieces); 
                }
                else 
                {
                    $source.=self::PHP_START_TAG.'echo ';
                    $source.='is_array($_ee=';
                    $source.=(self::$opt ? 'Template::filled(' . $piece[1] . ')' : $piece[1]);
                    $source.=')?implode(\' � \',$_ee):$_ee';
                    while((false !== $piece=next($pieces)) && ($piece[0]===false))
                    {
                        $source.=',';
                        $source.='is_array($_ee=';
                        $source.=(self::$opt ? 'Template::filled(' . $piece[1] . ')' : $piece[1]);
                        $source.=')?implode(\' � \',$_ee):$_ee';
                    }
                    $source.=self::PHP_END_TAG;
                }
            }
        }
        
        // Positionne les flags en fonction de ce qu'on a trouv�
        // flags : 0=que du texte, 1=texte+code, 2=que du code
        $flags=($hasCode ? ($hasText ? 1 : 2): 0);

        return $canEval;
    }
    
    /**
     * @return boolean canEval
     */

    // return true si c'est du code, false sinon
    public static function handleVariable(& $var)
    {
        // Enl�ve le signe $ de d�but
        $name=substr($var,1);

        // Teste si c'est une source de donn�es
        $var=self::$env->get($name);
        if ($var === false)
            throw new Exception("\$$name : variable non d�finie");
            
        ++self::$nbVar;
        return true;
    }
    
    
    private static function boolean($x)
    {
        if (is_string($x))
        { 
            switch(strtolower(trim($x)))
            {
                case 'true':
                case 'on':
                case '1':
                case '-1':
                    return true; 
                default:
                    return false;
            }
        }
        return (bool) $x;
    }
    
    private static $fillLevel=0;
    private static $fillVar=array();
    private static $fillStrict=array();
    
    private static function compileFill(DOMNode $node)
    {
        $t=self::getAttributes($node, array('values'), array('strict'=>'false'));
        $values=$t['values'];
        $canEval=self::parse($values,true);

        ++self::$fillLevel;
        
        self::$fillStrict[self::$fillLevel]=self::boolean($t['strict']);
        
        $fill=self::$fillVar[self::$fillLevel]=self::$env->getTemp('fill');
        
        // Pr�pare la liste des valeurs pour le fill
        echo 
            self::PHP_START_TAG,
            $fill, '=Template::getFillValues(', $values, ',', var_export(self::$fillStrict[self::$fillLevel],true), ');',
            self::PHP_END_TAG;
        
        // Cr�e une nouvelle variable, $fill, utilisable uniquement au sein du bloc <fill>..</fill>
        // et qui contient la liste des valeurs qui n'ont pas encore �t� utilis�es.
        self::$env->push(array('fill'=>"array_filter($fill)"));
        
        // Compile tous les noeuds fils du bloc <fill>...</fill>
        self::compileChildren($node);

        // Supprime la variable temporaire $fill
        self::$env->pop();
        
        self::$env->freeTemp($fill);
        echo self::PHP_START_TAG, 'unset(',$fill,')', self::PHP_END_TAG;
        --self::$fillLevel;
    }
    
    private static function compileFillControls(DOMNode $node)
    {
        if (self::$fillLevel===0) return true; // on n'est pas dans un fill, g�n�re un noeud normal
    
        switch($node->tagName)
        {
            case 'input': 
                switch ($node->getAttribute('type'))
                {
                    case 'radio':
                    case 'checkbox':
                        if ('' === $value=$node->getAttribute('value')) return true; // pas de value, g�n�re un noeud normal
                        $code='checked="checked"';
                        break;
                    default:
                        return true;
                }
                break;
            case 'option': 
                if ('' === $value=$node->getAttribute('value')) // trim ??
                    if ('' === $value = $node->textContent) return true; // pas de value, g�n�re un noeud normal
                $code='selected="selected"';
                break;
            default:
                throw new exception(__METHOD__.' appell�e pour un tag ' . $node.tagName);
        }
        $canEval=self::parse($value,true);
        
        if (self::$fillStrict[self::$fillLevel]) 
            $item=self::$fillVar[self::$fillLevel].'[trim('.$value.')]';
        else
            $item=self::$fillVar[self::$fillLevel]."[implode(' ', Utils::tokenize($value))]";
            
        self::compileElement($node, "if (isset($item)){echo ' $code';$item=false;}");
    }

}

?>