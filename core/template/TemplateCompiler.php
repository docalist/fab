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

$h='a $4 $_$é b';
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
 * @author      Daniel Ménard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id$
 */

//echo "en ISO-8859-1 : Résumé<br />";
//echo "en UTF-8 : ", utf8_encode('Résumé'), "<br />";
//echo "double UTF-8 : ", utf8_encode(utf8_encode('Résumé')), "<br />";
//echo "décodé : ", utf8_decode('Résumé'), "<br />";
//die();
//
//require_once(dirname(__FILE__).'/TemplateCode.php');
//require_once(dirname(__FILE__).'/TemplateEnvironment.php');


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

    private static $nbVar=0;// nombre de variables rencontrées dans un bloc opt /opt (cf compileOpt)

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
    
    public static function autoId($name=null)
    {
        // Aucun "nom suggéré" : recherche le nom du parent, du grand-parent, etc.
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
            // si le nom suggéré contient des expressions, il faut les évaluer
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
     * Génère une exception si le template est mal formé ou contient des erreurs.
     * 
     * @param string $source le code source du template à compiler
     * @param array l'environnement d'exécution du template
     * 
     * @return string le code php du template compilé
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
        
        // Fait un reset sur les ID utilisés
        self::$usedId=array(); // HACK: ne fonctionnera pas avec des fonctions include
        // il ne faudrait faire le reset que si c'est un template de premier niveau (pas un include)
//echo 'Source avant at<pre>*',htmlentities($source),'*</pre>';

//        self::addCodePosition($source);
//echo 'Source après at<pre>',htmlentities($source),'</pre>';
//        echo '<pre>';
//echo (htmlspecialchars($source));        
//echo '</pre>';
//echo $source;        
        // Supprime les commentaires de templates : /* xxx */
        $source=preg_replace('~/\*[ \t\n\r\f].*?[ \t\n\r\f]\*/~ms', null, $source);
        
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
        $source=preg_replace($re, '$0<root strip="{true}">', $source, 1);
        $source.='</root>';
        
        // Ajoute les fichiers auto-include dans le source
        $files=Config::get('templates.autoinclude');

        // TODO: tester que le fichier existe et est lisible et générer une exception sinon 
        $h='';
        foreach((array)$files as $file)
        {
            if (false === $path=Utils::searchFile($file))
                throw new Exception("Impossible de trouver le fichier include $file spécifié dans la config, searchPath=".var_export(Utils::$searchPath,true));
//            debug && Debug::log('Concaténation du fichier include %s au source du template.file=%s, searchpath=%s', $path, $file, print_r(Utils::$searchPath, true));
            $include=file_get_contents($path);
            // Supprime les commentaires en syntaxe C présents dans le include
            $include=preg_replace('~/\*\s.*?\s\*/~ms', null, $include);
            
        	$h.=$include;
        }
        if ($h) $source=str_replace('</root>', '<div test="{false}">'.$h.'</div></root>', $source);
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
     // si la balise de fin de php est \r, elle est mangée (cf http://fr2.php.net/manual/fr/language.basic-syntax.instruction-separation.php)
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
        // - pour qu'ils n'apparaissent pas dans la sortie générée (non valable comme raison : on a test="false")
        // - pour que le code d'un template ne soit pas modifié par un autre template (à étudier : toujours valide
        foreach($templates as $template) // todo: à garder ?
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
                
                // result est maintenant un tag <template> instancié
                // on va remplacer node (le noeud matché) par le contenu de result
                
                // on ne peut pas travailler directement sur childNodes car
                // dès qu'on fait un ajout de fils, la liste est modifiée.
                // On commence donc par faire la liste de tous les noeuds
                // à insérer.
                $childs=array();
                foreach($result->childNodes as $child)
                    $childs[]=$child;
                
                foreach($childs as $child)
                    $node->parentNode->insertBefore($child, $node);
                    
                // supprime le noeud <template> désormais vide qui reste
                $node->parentNode->removeChild($node);
            }
        }
    }
    
    // return true si c'est du code, false si c'est une valeur
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
            
            // la fonction DOIT retourner de l'ascii, pas de l'utf-8 (cf commentaires dans instantiateMatch)
            $var=utf8_decode($var);
//            echo 'handleMatchVar : ', $attr, '=', $var, '<br />';
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
        
         problèmes d'encodage...
         en gros, on fait un preg_match sur le contenu du noeud en demandant à récupérer les offset et ensuite, on fera un
         replaceData à l'offset obtenu et sur la longueur du match
         Le problème, c'est que DOM travaille en utf-8. Donc $node->data est une chaine en utf-8. preg_match ne gère
         pas ça bien : les offset retournés seront des offset d'octets et non pas des offset de caractères.
         replaceData, elle, travaille en utf-8. Donc elle attend des offset de caractères et non pas des offset d'octets.
         Si on ne fait rien, on aura un "décalage", égal au nombre de caractères codés sur plus de un octet précédant la
         chaine à remplacer.
         La solution trouvée consiste à passer à preg_match une chaine ansi et non pas une chaine utf8.
         Du coup, les offset retournés sont toujours des offset octets, mais sont strictement identiques aux ofssets caractères
         qui auraient été retournés si preg_match gérait correctement l'utf-8.
         Du coup, le replaceData fonctionne correctement...
         
          DM+YL, 06/04/07
          
         Précisions (23/04/06, DM+YL+SF)
         En fait le correctif n'est pas suffisant.
         - on a le DOM qui est en UTF-8
         - on décode, pour que le preg_match fonctionne
         - chaque $x ou {} est évalué.
         - Le résultat vient de php, donc c'est de l'ansi, donc il faut l'encoder, sinon on va insérer de l'ascii dans de l'utf
            -> donc on encode systématiquement
         - Problème : tous les résultats ne viennent pas de php :
            - s'il s'agit d'un attribut, on retourne la valeur de cet attribut, donc c'est déjà de l'utf8. 
            Comme on réencode systématiquement, on a un double encodage
            - si l'expression est un select qui retourne du texte exemple : {select('string(@label)')}, idem
            -> donc les fonctions handleMatchVar() et select() doivent décoder le résultat, sachant que celui-ci
            sera ensuite ré encodé avant d'être inséré dans la chaine utf8
         c'est complètement batard comme code... mais on n'a pas mieux pour le moment
         
         source est en utf8
         (offset,code)=pregmatch(source, '$xx et {}')
         ->offset sont en octets, pas en utf8
         result=eval(code)
         replace(source, code, result)
           
         source est en utf8
         (offset,code)=pregmatch(decode(source), '$xx et {}')
         result=eval(code)
         replace(source, code, result)
         -> result est en ascii, on insère de l'ascii dans de l'utf

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
        // Exécute le code présent dans les données du noeud
        if ($node instanceof DOMCharacterData) // #text, #comment... pour les PI :  || $node instanceof DOMProcessingInstruction
        {
            $matches=null;
//            if (preg_match_all(self::$reCode, utf8_decode($node->data), $matches, PREG_PATTERN_ORDER|PREG_OFFSET_CAPTURE)>0)
            if (self::findCode(utf8_decode($node->data), $matches))
            { 
                // Evalue toutes les expressions dans l'ordre où elles apparaissent
//                foreach($matches[0] as & $match)
                foreach($matches as & $match)
                {
                	// Initialement, $match contient : 
                    //    $match[0] = l'expression trouvée
                    //    $match[1] = l'offset de l'expression dans data
                    // on va y ajouter
                    //    $match[2] = le résultat de l'évaluation de l'expression
                    //    $match[3] = les noeuds éventuels à insérer devant expression si elle contient un appel à select()
                    
                    // Récupère l'expression à exécuter
                    $code=$match[0];

                    // Evalue l'expression
                    self::$selectNodes=null; // si select() est utilisée, on aura en sortie les noeuds sélectionnés
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
//                    echo 'Code après parseExpression: ', $code, '<br />';
                    
                    if ($canEval) $code=TemplateCode::evalExpression($code);
//                    echo 'Code après evalExpression: ', $code, '<br />';
                    
                    // Stocke le résultat
                    $match[2]=$code;
                    $match[3]=self::$selectNodes; // les noeuds éventuels retournés par select et qu'il faut insérer
                }

                // Remplace l'expression par sa valeur et insère les noeuds sélectionnés par select()
                
                // On travaille en ordre inverse pour deux raisons :
                // - l'offset de l'expression reste valide jusqu'à la fin
                // - après un splitText, le noeud en cours ne change pas
//                foreach(array_reverse($matches[0]) as $match)
                foreach(array_reverse($matches) as $match)
                {
                	// Remplace l'expression par sa valeur
//                    echo 'Node before <pre>',$node->nodeValue,'</pre>';
//                    echo 'replaceData, offset=', $match[1], ', len=', strlen($match[0]), ', replacewith=', $match[2], "\n";
                    $node->replaceData($match[1], strlen($match[0]), utf8_encode($match[2]));
//                    echo 'Node after <pre>',$node->nodeValue,'</pre>';
                    
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
        // la fonction DOIT retourner de l'ascii, pas de l'utf-8 (cf commentaires dans instantiateMatch)
        if (is_scalar($nodeSet)) return utf8_decode($nodeSet);

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
                
            case XML_ELEMENT_NODE:  // un élément
            
                // Récupère le nom du tag
                $name=$node->tagName;
                
                // S'il s'agit de l'un de nos tags, appelle la méthode correspondante
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
                throw new Exception("Impossible de compiler le template : l'arbre obtenu contient un type de noeud non géré ($node->nodeType)");
        }
    }

    private static function route($value)
    {
        $canEval=self::parse($value,true);

        // Si l'expression est évaluable, on fait le routage à la compilation (requiert de recompiler les templates si on change les routes)
        if ($canEval)
            return Routing::linkFor(TemplateCode::evalExpression($value));
        
        // Sinon, le routage sera déterminé à l'exécution
        else
            return self::PHP_START_TAG .'echo Routing::linkFor('.$value.')' . self::PHP_END_TAG;
    }
    
    /**
     * Compile un élément <tag name="">
     * 
     * Génère le tag dont le nom est passé en paramètre dans l'attribut name.
     * Name doit être un nom d'élément valide (que des lettres)
     * Si name est absent ou est vide, fait la même chose qu'un strip (seul le contenu du
     * tag est généré)
     * Si name est une expression, celle-ci doit pouvoir être évaluée à la compilation.
     */
    private static function compileTag(DOMElement $node)
    {
        if (! $node->hasAttribute('name'))
            return self::compileChildren($node);
        
        $name=$node->getAttribute('name');
        if ($name==='')
        	return self::compileChildren($node);

        if (! self::parse($name, true))
            throw new Exception("L'attribut name d'un élément <tag> doit pouvoir être évalué à la compilation");
        $name=TemplateCode::evalExpression($name);                
        $node->removeAttribute('name');
        try
        {
        $newNode=$node->ownerDocument->createElement($name);
        }
        catch (Exception $e)
        {
        	throw new Exception("Le nom $name indiqué dans l'attribut name de l'élément <tag> n'est pas valide");
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
        // Pour chaque tag, on a un tableau contenant la liste des attributs à router
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
        
        // Gère l'attribut "test" : supprime tout le noeud si l'expression retourne false
        $test='';
        if ($node->hasAttribute('test'))
        {
            $test=$node->getAttribute('test');
            $canEval=self::parse($test,true);

            // Si le test est évaluable, on teste maintenant
            if ($canEval)
            {
                // Si le test s'évalue à 'false', terminé (on ignore le noeud)
                if (false == TemplateCode::evalExpression($test)) return;
                
                // Sinon, on génère tout le noeud sans condition
                $test='';
            }
            
            // Si le test n'est pas évaluable, on encadre le noeud par un bloc php "if($test)"
            else
            {
                echo self::PHP_START_TAG, "if($test):", self::PHP_END_TAG;
            }
            
            // Supprime l'attribut "test" du noeud en cours
            $node->removeAttribute('test');
        }

        // Gère l'attribut "strip" : ne garde que le contenu du noeud si l'expression retourne true
        $strip='';
        if ($node->hasAttribute('strip'))
        {
            $strip=$node->getAttribute('strip');
            $canEval=self::parse($strip,true);

            // Si le strip est évaluable, on teste maintenant
            if ($canEval)
            {
                // Si strip s'évalue à 'false', on génère toujours les tags ouvrant et fermants
                if (false == TemplateCode::evalExpression($strip))
                    $strip='';
                    
                // Strip s'évalue à 'true', on ne génère que le contenu du noeud
                else
                    return self::compileChildren($node);
            }
            
            // Si le strip n'est pas évaluable, ajoute un test php "if($strip)" autour du tag ouvrant et du tag fermant
            else
            {
                $keepTag=self::$env->getTemp('keeptag');
                echo self::PHP_START_TAG, "if($keepTag=!($strip)):", self::PHP_END_TAG;
            }

            // Supprime l'attribut "strip" du noeud en cours
            $node->removeAttribute('strip');
        }

        $name=$node->tagName;

        // Génère le début du tag ouvrant
        echo '<', $name;    // si le tag a un préfixe, il figure déjà dans name (e.g. <test:h1>)

        // cas particulier de l'attribut xmlns
        if ($node->namespaceURI !== $node->parentNode->namespaceURI)
            echo ' xmlns="', $node->namespaceURI, '"'; 
            
        // Accès aux attributs xmlns : cf http://bugs.php.net/bug.php?id=38949
        // apparemment, fixé dans php > 5.1.6, à vérifier
            
        // Génère tous les attributs
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
            
        // Génère tous les fils et la fin du tag
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
     * Teste si le noeud passé en paramètre est vide et peut être écrit sous forme courte (ie sans tag de fin).
     * 
     * @param DOMNode $node le noeud à examiner
     * @return boolean true si le noeud passé en paramètre ne contient aucun fils et s'il est déclaré comme
     * ayant un content-model égal à "empty" dans les DTD de xhtml.  
     */
    private static function isEmptyTag(DOMNode $node)
    {
        static $empty=null;

        if ($node->hasChildNodes()) return false;
        
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

        return (isset($empty[$node->tagName]));
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
        
        // Reset du nombre de var contenu dans le bloc opt
        $save=self::$nbVar;
        self::$nbVar=0;

        // compile le contenu
        ++self::$opt;
        ob_start();
        self::compileChildren($node);
        $content=ob_get_clean();
        --self::$opt;

        // Génère le code
        if (self::$nbVar===0)
        {
            echo $content; // aucune variable dans le bloc, ce n'est pas un bloc optionnel
            // Restaure le nombre de var (au xa où on il est des blocs opt ascendants)
            self::$nbVar=$save;
        }
        else
        {
            echo self::PHP_START_TAG, 'Template::optBegin()', self::PHP_END_TAG,
                 $content,
                 self::PHP_START_TAG, 'Template::optEnd('.$t['min'].')', self::PHP_END_TAG;
 
            // Restaure le nombre de var (au xa où on il est des blocs opt ascendants)
            self::$nbVar=$save+1;
        }

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
        $close=false;
        $done=false;
        $lastWasFalse=false;
        $first=true;
        for(;;)
        {
            // Génère le tag
            if (!$done) switch($tag=$next->tagName)
            {
                case 'else':
                    self::getAttributes($next); // aucun attribut n'est autorisé
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
                    
                    // Si le test est évaluable, on teste maintenant
                    if ($canEval)
                    {
                        // on a un if(true)  ou un elseif(true)
                        if (true==TemplateCode::evalExpression($t['test']))
                        {
                            // ne pas générer de condition (si c'est un if, pas de condition, si c'est un elseif, devient un else)

                            if ($close)
                                echo self::PHP_START_TAG, 'else', ':', self::PHP_END_TAG;
                                
                            // Génère le bloc (les fils)
                            self::compileChildren($next);
                            $done=true;
                        }
                        // on a un if(false)  ou un elseif(false)
                        else
                        {
                        	// ignorer le noeud
                            // si prochain tag=elseif, générer un if
                            $lastWasFalse=true;
                        }
                        
                        // Sinon, on génère tout le noeud sans condition
                        $test='';
                    }

                    // Sinon, génère le tag et sa condition
                    else
                    {
                        if ($first) $tag='if';
                        echo self::PHP_START_TAG, $tag, '(', $t['test'], '):', self::PHP_END_TAG;
                        $first=false;
                        $close=true;
                        // Génère le bloc (les fils)
                        self::compileChildren($next);
                    }

                    break;
            }
                        
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
        if ($close) echo self::PHP_START_TAG, 'endif;', self::PHP_END_TAG;
        
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
        $t=self::getAttributes($node, null, array('test'=>true));
        $canEval=self::parse($t['test'],true);
                
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
        $seen=array(); // Les conditions déjà rencontrées dans les différents case du switch
        
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
                            if (isset($seen[$t['test']]))
                                throw new Exception('Switch : plusieurs blocs case avec la même condition');
                            $seen[$t['test']]=true;
                            $canEval=self::parse($t['test'],true);
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

        // Enlève les accolades qui entourent l'expression
        // HACK : ne devrait pas être là, intégrer dans un wrapper autour de parseExpression
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

- Un slot avec un contenu initial fixé en dur :
    <slot name="toto">
        bla bla
    </slot>

- Un slot avec un contenu initial provenant d'un autre template :
    <slot name="toto" file="menu.html" />

- Un slot avec un contenu provenant d'un module action :
    <slot name="toto" action="/base/search" max="10" sort="-" cart="{$this->getCart()}">
        bla bla
    </slot>
    
Un slot peut avoir les attributs standard "test" et "strip". Ils sont définis et gérés de façon
"absolue", c'est à dire que même si le contenu du slot change, les conditions restent (en fait, elles
sont évaluées avant même qu'on commence à essayer d'exécuter le slot)

Code de compilation :
- récupérer name, exception si absent ou vide
- parser sous forme d'expression php
- générer :
    - si le noeud a des fils non vide :
        - php if (runSlot($name)) /php
        - contenu du noeud (compileChildrend())
        - php endif /php
    - sinon
        - php runSlot($name) /php

Fonctionnement de runSlot :
Dans la config, on a les définitions des slots :
    - slots:
        - footer:
            enabled: true
            file: sidebar.tml
        - sidebar:
            enabled: true
            action: /blog/recent
            
runSlot examine la config en cours pour savoir s'il faut examiner le noeud ou pas.
si enabled=false : return false (ne pas exécuter le slot, ne pas afficher le contenu par défaut)
si file="" et action="" return true (afficher le contenu par défaut du slot)
si file : Template::Run(file, currentdatasources)
sinonsi action : Routing::dispatch(action, currentdatasource) 
runSlot retourne true s'il faut afficher le contenu par défaut du noeud
return false (ne pas afficher le contenu par défaut)

*/    
    private static function compileSlot($node)
    {
        // Récupère le nom du slot
        if (($name=$node->getAttribute('name')) === '')
            throw new Exception("Tag slot incorrect : attribut 'name' manquant");
        self::parse($name, true);
        $node->removeAttribute('name');
        
        // Vérifie que le slot ne spécifie pas à la fois une action et un contenu par défaut
        if ($node->hasAttribute('action') && $node->hasChildNodes())
            throw new Exception('Un tag slot peut spécifier soit une action soit un contenu par défaut mais pas les deux');

        // Récupère l'action par défaut
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
        
        // Génère le code 
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
        
        // Crée un nom unique pour la variable
        $def=self::$env->getTemp($t['name']); // pour être sur de ne pas écraser une var existante
        self::$env->freeTemp($t['name']);       // libérée aussitôt : si le def est redéfini, la même va rsera utilisée
        
        self::$env->push(array($t['name']=>$def));
        
        echo self::PHP_START_TAG, $def,'=', $t['value'], self::PHP_END_TAG;
        
    }
    /* ======================== EXPRESSION PARSER ============================= */
    
    /**
     * Analyse une chaine de caractères contenant à la fois du texte et du code
     * (variables ou expressions entre accolades).
     * 
     * Si asExpression vaut true, la chaine est retournée sous la forme d'une 
     * expression php dans laquelle le texte statique est convertit en chaine
     * et concaténé aux expressions figurant dans le code.
     * 
     * Si asExpression vaut false, la chaine est retournée sous la forme d'un
     * code source dans lequel le texte statique est inchangé et les expressions
     * figurant dans le code sont converties en blocs php contenant un appel à echo.
     * 
     * Exemple :
     * Source analysé     : a $x b {trim('c')} d {trim($x)} e
     * asExpression=false : a <?php echo $x?> b c d <?php echo trim($x)?> e   
     * asExpression=true  : 'a '.$x.' b c d '.trim($x).' e'
     * 
     * Remarque : si le code contient une expression qui est évaluable (par
     * exemple trim('c') dans l'exemple ci-dessus), l'expression est remplacée par
     * le résultat de son évaluation et est ensuite traitée comme s'il s'agissait
     * de texte statique.
     * 
     * @param string & $source la chaine de caractères à analyser
     * 
     * @param boolean $asExpression true si le source doit être retourné sous 
     * la forme d'une expression php, false (valeur par défaut) si l'expression
     * doit être retournée sous forme de code
     * 
     * @return boolean true si l'expression était évaluable, false sinon.
     * 
     * La valeur de retour est intéressante lorsque asExpression=true car elle permet à 
     * l'appelant de savoir qu'il peut faire un eval() sur l'expression obtenue (la 
     * valeur true retournée signifie que l'expression retournée est une constante :
     * elle ne contient ni variables ni appels de fonctions).
     * 
     * Quand asExpression=false et que la fonction retourne true, cela signifie que le 
     * code retourné ne contient aucun bloc php, il ne contient que du texte.
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

            // Non trouvé, terminé
            if ($start >= strlen($source)) break;
            
            // Si le caractère est précédé d'un antislah, on l'ignore
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
                            if ($apos) break; // un " dans une chaine encadrées de guillemets simples
                            if ($quot && $source[$end-1]==='\\') break; // \" dans une chaine encadrée de guillemets doubles
                            $quot=!$quot;
                            break;
                        case '\'':
                            if ($quot) break; // un ' dans une chaine encadrées de guillemets doubles
                            if ($apos && $source[$end-1]==='\\') break; // \' dans une chaine encadrée de guillemets simples
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
        // Boucle tant qu'on trouve des choses dans le source passé en paramètre
        $start=0;
        $result='';
        $canEval=true;
        
        $pieces=array(); // chaque élément est un tableau. 0: flag, true=texte statique, false=code, 1: le bout d'expression
        $static=false;  // true si le dernier élément ajouté à $pieces était du texte statique
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
            
            // Envoie le texte qui précède l'expression trouvée
            if ($offset>$start)
            {
                $hasText=true;
                if ($static)
                    $pieces[$nb][1].=self::unescape(substr($source, $start, $offset-$start));
                else
                    $pieces[++$nb]=array($static=true,self::unescape(substr($source, $start, $offset-$start)));
            }
            
            // Enlève les accolades qui entourent l'expression
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
                    if ($expression !== 'NULL') // le résultat retourné par var_export(null)
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
            
        // Génère le résultat
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
                    $source.=')?implode(\' ¤ \',$_ee):$_ee';
                    while((false !== $piece=next($pieces)) && ($piece[0]===false))
                    {
                        $source.=',';
                        $source.='is_array($_ee=';
                        $source.=(self::$opt ? 'Template::filled(' . $piece[1] . ')' : $piece[1]);
                        $source.=')?implode(\' ¤ \',$_ee):$_ee';
                    }
                    $source.=self::PHP_END_TAG;
                }
            }
        }
        
        // Positionne les flags en fonction de ce qu'on a trouvé
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
        // Enlève le signe $ de début
        $name=substr($var,1);

        // Teste si c'est une source de données
        $var=self::$env->get($name);
        if ($var === false)
            throw new Exception("\$$name : variable non définie");
            
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
        
        // Prépare la liste des valeurs pour le fill
        echo 
            self::PHP_START_TAG,
            $fill, '=Template::getFillValues(', $values, ',', var_export(self::$fillStrict[self::$fillLevel],true), ');',
            self::PHP_END_TAG;
        
        // Crée une nouvelle variable, $fill, utilisable uniquement au sein du bloc <fill>..</fill>
        // et qui contient la liste des valeurs qui n'ont pas encore été utilisées.
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
        if (self::$fillLevel===0) return true; // on n'est pas dans un fill, génère un noeud normal
    
        switch($node->tagName)
        {
            case 'input': 
                switch ($node->getAttribute('type'))
                {
                    case 'radio':
                    case 'checkbox':
                        if ('' === $value=$node->getAttribute('value')) return true; // pas de value, génère un noeud normal
                        $code='checked="checked"';
                        break;
                    default:
                        return true;
                }
                break;
            case 'option': 
                if ('' === $value=$node->getAttribute('value')) // trim ??
                    if ('' === $value = $node->textContent) return true; // pas de value, génère un noeud normal
                $code='selected="selected"';
                break;
            default:
                throw new exception(__METHOD__.' appellée pour un tag ' . $node.tagName);
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