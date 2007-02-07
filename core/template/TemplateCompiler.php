<?php
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
    public static function compile($source)
    {
        // TODO : voir si on peut r�tablir les commentaires de templates /* ... */
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
        foreach($files as $file)
        	$h.=file_get_contents(Runtime::$fabRoot.'core/template/autoincludes/'.$file);

        if ($h) $source=str_replace('</root>', '<div ignore="true">'.$h.'</div></root>', $source);
//        if (Template::getLevel()==0) file_put_contents(dirname(__FILE__).'/dm.xml', $source);

        // Cr�e un document XML
        $xml=new domDocument();

        if (Config::get('templates.removeblanks'))
            $xml->preserveWhiteSpace=false; // � true par d�faut

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
//self::collapseNodes($xml);

        // Instancie tous les templates pr�sents dans le document
        self::compileMatches($xml);        

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
        self::$datasources=array();
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
            $h=self::PHP_START_TAG ;
            $name='tpl_'.md5($result);
            $h.="$name();function $name(){";                
            
            $h. "\n//Liste des variables de ce template\n" ;
            foreach (self::$bindings as $var=>$binding)
                $h.='    ' . $var . '=' . $binding . ";\n";
                
            $h.=self::PHP_END_TAG;
            $result = $h.$result;
$result.=self::PHP_START_TAG . '}' . self::PHP_END_TAG;                
        }
        list(self::$loop, self::$opt, self::$datasources, self::$bindings)=array_pop(self::$stack);
        return $result;
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
    

    /**
     * Compile les templates match pr�sents dans le document
     * 
     * La fonction r�cup�re tous les templates pr�sents dans le document
     * (c'est � dire les noeuds ayant un attribut match="xxx") et instancie tous
     * les noeuds du document qui correspondent
     * 
     * @param DOMDocument $xml le document xml � traiter
     */
    private static function compileMatches(DOMDocument $xml)
    {
        // Cr�e la liste des templates = tous les noeuds qui ont un attribut match="xxx""
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
            $expression=$template->getAttribute('match');
//            echo 'template match=', $expression, '<br />';
            if ($expression==='')
                throw new Exception
                (
                    "L'attribut match d'un tag template est obligatoire" .
                    htmlentities($template->ownerDocument->saveXml($template))
                );
                    
            // Cr�e la liste de tous les noeuds s�lectionn�s par ce template
            $matches=$xpath->query($expression);

            // Expression xpath erron�e ?
            if ($matches===false)
                throw new Exception("Erreur dans l'expression xpath [$expression]");
                
            // Aucun r�sultat : rien � faire
            if ($matches->length==0) 
            {
//                echo 'aucun match<br />';
                continue;	   
            }

            // Traite chaque noeud trouv� dans l'ordre
            foreach($matches as $match)
            {
                // Clone le template pour cr�er un nouveau noeud 
                $node=$template->cloneNode(true);

                // Supprime l'attribut match
                $node->removeAttribute('match');
                
                // Recopie tous les arguments (les attributs du match qui existent dans le template)
                $replace=array();
                foreach ($node->attributes as $attribute)
                {
                    if ($match->hasAttribute($attribute->name))
                        $attribute->value=$match->getAttribute($attribute->name);
                    $replace['~\$'.$attribute->name.'($|[^a-zA-Z0-9_\x7f-\xff])~']=$attribute->value.'\1';
                }

                // Applique au nouveau noeud les attributs de l'ancien noeud
                self::instantiateMatch($template, $node, $replace, $match);
//file_put_contents(__FILE__.'.clone', $node->ownerDocument->saveXml($node));
//die();                
                
                // Remplace l'ancien noeud (l'appel de template) 
                // par les fils (pour ne pas avoir le tag <template>) du nouveau (le template instanci�)
//                while ($node->hasChildNodes())
//                    $match->parentNode->insertBefore($node->childNodes->item(0), $match);
//                $match->parentNode->removeChild($match);    
                // ci-dessus, on ne peut pas utiliser une boucle foreach :
                // comme on modifie la liste des fils, le foreach perd la boule                    

                // Ajoute un attribut collapse
                $node->setAttribute('collapse','true');
                
                // ancien code : remplace l'ancien noeud par le noeud template
                $match->parentNode->replaceChild($node, $match); // remplace match par node
             
            }
        }
    }
    
    

    /**
     * Instancie r�cursivement un template match avec une liste d'attributs
     * 
     * @param DOMNode $node le template � instancier
     * @param array $replace un tableau contenant les attributs � appliquer
     * au template
     */
    private static function instantiateMatch(DOMElement $template, DOMNode $node, $replace, DOMNode $match)
    {
        switch ($node->nodeType)
        {
            case XML_TEXT_NODE:
                if ($node->isWhitespaceInElementContent()) return;
                $node->nodeValue=preg_replace(array_keys($replace),array_values($replace), $node->nodeValue);
                self::compileSelectsInTextNode($template, $node, $match);
                return;
                
            case XML_ELEMENT_NODE:
                if ($node->hasAttributes())
                {
                    $emptyAttributes=array();
                    foreach ($node->attributes as $name=>$attribute)
                    {
                        $attribute->value=self::compileSelectsInAttribute($template, $attribute->value, $match);
                        $attribute->value=preg_replace(array_keys($replace),array_values($replace), $attribute->value);
//                        if ($attribute->value==='') 
//                            $emptyAttributes[]=$name; 
                    }
// TODO: revoir la gestion des attributs dans les templates match
// si dans un template on g�n�re le code name="$name" alors
// - si l'attribut name a �t� sp�cifi� dans l'appellant, et ce, m�me s'il est vide, alors il faut garder l'attribut
// - sinon (non sp�cifi� par l'appellant), il faut le supprimer (l'enlever du code g�n�r�)
// Pour le moment, aucun traitement n'est fait sur les attributs vides : ils sont conserv�s
                    
                    // Supprime tous les attributs vides � l'issue du match
//                    foreach ($emptyAttributes as $name)
//                        $node->removeAttribute($name);    
                }                
                if ($node->hasChildNodes())
                    foreach ($node->childNodes as $child)
                        self::instantiateMatch($template, $child, $replace, $match);

                return;
            case XML_COMMENT_NODE:
            case XML_PI_NODE:
            case XML_CDATA_SECTION_NODE:
                $node->nodeValue=preg_replace(array_keys($replace),array_values($replace), $node->nodeValue);
                self::compileSelectsInTextNode($template, $node, $match);
                return;
//            case XML_DOCUMENT_TYPE_NODE:
//            case XML_DOCUMENT_NODE:
            default:
                echo "\ninstantiateMatch non gere : ", $node->nodeType, '(', self::nodeType($node),')';
        }
    }
     
    private static function compileSelectsInAttribute(DOMElement $template, $value, DOMElement $matchNode)
    {
//echo "\n", 'VALUE initial : [', $value, ']', "\n";
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
        
        $result='';
        
        // Boucle tant qu'on trouve des choses dans le source pass� en param�tre
        for(;;)
        {
            // Recherche la prochaine expression {select('xpath')}
            if (preg_match($re, $value, $match, PREG_OFFSET_CAPTURE)==0) break;
            $offset=$match[0][1];
            $len=strlen($match[0][0]);
            $expression=$match[1][0];
            if ($expression=='') $expression=$match[2][0];

            // Coupe le texte en en deux, au d�but de l'expression trouv�e
            if ($offset > 0) 
                $result .= '' . trim(substr($value, 0, $offset));
            $value=substr($value, $offset+$len);	   
//            echo "match trouv�. result actuel=", $result, ", select=", $expression, ", suite=", $value, "\n";
            
            // Ex�cute l'expression xpath trouv�e
            $xpath=new DOMXPath($matchNode->ownerDocument);
            //$nodeSet=$xpath->query($expression, $matchNode);
            $nodeSet=$xpath->evaluate($expression, $matchNode);

            // Expression xpath erron�e ?
            if ($nodeSet===false)
                throw new Exception("Erreur dans l'expression xpath [$expression]");
                
            if (is_scalar($nodeSet))
            {
                if ($nodeSet !== '') $result.='' . trim($nodeSet);
                
            }
            else
            {            
                // Aucun r�sultat : rien � faire
                if ($nodeSet->length==0) continue;
                
                // Ins�re entre les deux noeuds texte les noeuds s�lectionn�s par l'expression xpath
                // Il est important de cloner chaque noeud car il sera peut-�tre r�utilis� par un autre select
                $allInserts='';
                $insert='';
                foreach($nodeSet as $newNode)
                {
                    switch ($newNode->nodeType)
                    {
                        // S'il s'agit d'un attribut, on ins�re sa valeur
                        case XML_ATTRIBUTE_NODE:
                            $insert=$newNode->value;
                            break;
                            
                        // Idem si c'est un noeud texte
                        case XML_TEXT_NODE:
                            $insert=$newNode->nodeValue;
                            break;
                            
                        // Types de noeuds ill�gaux : exception
                        case XML_COMMENT_NODE:
                        case XML_PI_NODE:
                        case XML_ELEMENT_NODE:
                        case XML_DOCUMENT_TYPE_NODE:
                        case XML_CDATA_SECTION_NODE:
                        case XML_DOCUMENT_NODE:
                            throw new Exception("Expression xpath incorrecte : $expression. Les noeuds retourn�s ne peuvent pas �tre ins�r�s � la postion actuelle");
                            
                        default:
                            return __METHOD__ . " : type de noeud non g�r� ($node->nodeType)";
                    }
                    if ($insert!=='') 
                    {
                    	if ($allInserts) $allInserts .=' ';
                        $allInserts.=trim($insert);
                    }
                }
                if ($allInserts!=='') 
                {
//                    if ($result) $result .=' ';
                    $result.=trim($allInserts);
                }
            }
        }
        
        if ($value !=='') 
        {
            //if ($result) $result .=' ';
            $result .= trim($value);
        }
//echo "\n", 'result : [', $result, ']', "\n";
        return $result;
//        return trim($result);
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
                echo self::compileField($node->nodeValue); // ou textContent ? ou wholeText ? ou data ?
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
                        $test=self::compileField($test, true);
                        echo self::PHP_START_TAG, 'if (', $test, '):',self::PHP_END_TAG;
                        $node->removeAttribute('test');
                    }
                    if (($if=$node->getAttribute('if')) !== '')
                    {
                        $if=self::compileField($if, true);
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
                                $value=self::compileField($attribute->value, false, $flags);
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

    /**
     * Compile les balises de champ pr�sents dans un texte   
     * 
     * Remarque : la fonction applique automatiquement utf8_decode � l'expression
     * pass�e en param�re : ne pas le faire ni avant ni apr�s.
     * 
     * @param string $source le texte � examiner
     * 
     * @param boolean $phpTags indique q'il faut ou non ajouter les tags 
     * d'ouverture et de fermeture de php dans le code g�n�r�
     * 
     * @param int $flags (optionnel) en sortie, un entier indiquant le
     * statut de la compilation (0=le source pass� en param�tre ne contenait
     * pas de champs, 1=le source pass� en param�tre contenait des champs et
     * du texte, 2=le source pass� en param�tre ne contenait que des champs)
     * 
     * @return string la version compil�e du source
     */
    private static function compileField($source, $asExpression=false, &$flags=null)
    {
        // Expression r�guli�re pour un nom de variable php valide
        // Source : http://fr2.php.net/variables (dans l'intro 'essentiel')
        // Modif du 14/11/06 : interdiction d'avoir une variable qui commence
        // par un underscore (r�serv� aux variables internes du template, le fait
        // de l'interdire assure que les variables internes n'�crasent pas les
        // sources de donn�es)
        $var='(?<!\\\\)\$([a-zA-Z\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)';
        // TODO : voir si un \w fait la m�me chose
        
        // Expression r�guli�re pour une expression valide dans un template
        $exp='(?<!\\\\)\{[^}]*(?<!\\\\)\}';
        
        // Expression r�guli�re combinant les deux
        $re="~$var|$exp~";
        
        // Convertit l'ancienne syntaxe des champs dans la nouvelle syntaxe 
//        $source=preg_replace('~\[([a-zA-Z]+)\]~', '$$1', $source);  // [titre]
//        $source=preg_replace                                        // [titoriga:titorigm]
//        (
//            '~\[([^\]]+)\]~e', 
//            "'{\$' . preg_replace('~:(?=[a-zA-Z])~',':\$','$1') . '}'", 
//            $source
//        );

        
        // Boucle tant qu'on trouve des choses dans le source pass� en param�tre
        $start=0;
        $result='';
        
        $hasFields=false;
        $hasText=false;
        for($i=1;;$i++)
        {
            // Recherche la prochaine expression
            if (preg_match($re, $source, $match, PREG_OFFSET_CAPTURE, $start)==0) break;
            $expression=$match[0][0];
            $len=strlen($expression);
            $offset=$match[0][1];
            $hasFields=true; // pour indiquer dans flags qu'on a trouv� au moins un champ
            
            // Envoie le texte qui pr�c�de l'expression trouv�e
            if ('' != $text=substr($source, $start, $offset-$start))
            {
                $result.=self::unescape($text);
                $hasText=true;
            }
                        
            // Enl�ve les accolades qui entourent l'expression
            if ($expression[0]==='{') $expression=substr($expression, 1, -1);
            if (trim($expression) != '')
            {
                // Compile l'expression
                $t=self::compileExpression($expression);
                //echo '<pre>$expression=', print_r($t,true), '</pre>';
                // Il s'agit d'une simple expression, pas d'un collier
                if (count($t)==1)
                {
                    $h=$t[0];
                    if (self::$opt) $h="Template::filled($h)";	
                }
                
                // Il y a plusieurs alternatives dans l'expression
                else
                {
                    $h='($tmp=' . join($t, ' or $tmp=') . ') ? ';	
                    if (self::$opt) 
                        $h.='Template::filled($tmp)';
                    else
                        $h.='$tmp';
                    $h.=' : null';  
                }
                
                if ($asExpression)
                    $result.= $h;
                else
                    $result.=self::PHP_START_TAG . 'echo ' . $h . self::PHP_END_TAG;
            }
                        
            // Passe au suivant
            $start=$offset + $len;
        }

        // Envoie le texte qui suit le dernier match 
        if ('' != $text=substr($source, $start))
        {
            $result.=self::unescape($text);
            $hasText=true;
        }

        // Positionne les flags en fonction de ce q'on a trouv�
        if (! $hasFields) 
            $flags=0;           // aucun champ, que du texte
        elseif ($hasText)       
            $flags=1;           // champ et texte m�lang�s
        else
            $flags=2;           // que des champs
            
        return $result;
    }


    /**
     * Compile un "collier" d'expression pr�sent dans une zone de donn�es et
     * retourne un tableau contenant les diff�rentes alternatives.
     * 
     * Par exemple, avec l'expression "$titoriga:$titorigm", la fonction 
     * retournera un tableau contenant deux �l�ments : un pour la version compil�e
     * de l'expression $titoriga, un second pour la version compil�e de l'expression
     * titorigm
     * 
     * @param string $expression l'expression � compiler
     * @return array un tableau contenant les diff�rentes alternatives de l'expression
     */
    public static function compileExpression($expression)
    {
        // Utilise l'analyseur syntaxique de php pour d�composer l'expression en tokens
        $tokens = token_get_all(self::PHP_START_TAG . $expression . self::PHP_END_TAG);
        
        // Enl�ve le premier et le dernier token (PHP_START_TAG et PHP_END_TAG)
        array_shift($tokens);
        array_pop($tokens);
        
        $result=array();    // le tableau r�sultat
        $h='';              // l'expression en cours dans le collier

        // Supprime les espaces du source
        foreach ($tokens as $index=>$token)
        {
            if (is_array($token) and $token[0]==T_WHITESPACE) unset($tokens[$index]);
        }
        $tokens=array_values($tokens);
        
        reset($tokens);
        while(list($index,$token)=each($tokens))
        {
            // Le token est un bloc de texte
            if (is_string($token))
            {
                // Si c'est le signe ':', on ajoute l'expression en cours au tableau r�sultat
                if ($token===':')
                {
                    if ($h) 
                    {
                        $result[]=$h;
                        $h='';  
                    }
                }

                // Sinon, on ajoute le texte � l'expression en cours
                else
                    $h.=$token;

                // Passe au token suivant
                continue;
            }
            
            // Il s'agit d'un vrai token, extrait le type et la valeur du token 
            list($type, $data) = $token;
            
            switch ($type)
            {          
                case T_VARIABLE:
                    // C'est une variable, on la compile
                        
                    // Enl�ve le signe $ de d�but
                    $var=substr($data,1);
        
                    // Convertit le nom de la variable en iso8859-1 au cas o� elle contienne des accents
                    // if (php_version <6) ou qq chose comme (if PHP_SUPPORTS_UNICODE)
                    $var=utf8_decode($var); // UTILE ?
                    
                    // Teste si c'est une variable cr��e par le template (loop, etc.)
                    foreach (self::$datasources as $datasource)
                    {
                        if (isset($datasource[$var]))
                        {
                            // trouv� !
                            $h.= $datasource[$var];
        
                            // Passe au token suivant
                            continue 2;
                        }    
                    }
        
                    // C'est une source de donn�es
                    $name=$value=$code=null;
                    if (!Template::getDataSource($var, $name, $value, $code))
                        throw new Exception("Impossible de compiler le template : la source de donn�es <code>$var</code> n'est pas d�finie.");
        
                    self::$bindings[$name]=$value;
                    $h.= $code;
                    break;
                
                case T_STRING:
                    if (isset($tokens[$index+1]) && is_string($tokens[$index+1]) && ($tokens[$index+1]=='(') && isset(self::$functions[$data]))
                    {
                        // todo: check fonction � nous
                        ++$index;
                        unset($tokens[$index]); // supprime la parenth�ses ouvrante
                        ++$index;
                        $args='';
                        $level=0;
                        for(;;)
                        {
                            if (! isset($tokens[$index])) throw new Exception("parenth�se fermante attendue");
                            $token=$tokens[$index];
                            unset($tokens[$index]);
                            $index++;
                            if (is_string($token))
                            {
                                if ($token=='(')
                                    $level++;
                                elseif ($token==')')
                                {
                                     if ($level==0) break;
                                     $level--;
                                }
                                $args.=$token;
                            }
                            else
                            {
                                $args.=$token[1];
                            }
                        }
                        $h.=var_export(eval('return self::' . self::$functions[$data].'('.$args.');'), true);
                    }
                    else
                        $h.=$data;
                    break;
                
                default:
                    // Si c'est autre chose qu'une variable, on se contente d'ajouter � l'expression en cours 
                    // concat�ne. Il faut que ce soit du php valide
                    $h.=$data;
                    // TODO: � impl�menter et � tester : <a href="/base/show?ref=$REF" ... /> doit �tre rout� correctement, et le $REF soit �tre instanci�.
//                    $h.=self::compileField($data,true);  
                    break;
            }
        }
        
        // Ajoute l'expression en cours au tableau et retourne le r�sultat
        if ($h) $result[]=$h;
        return $result;
    }
    private static function OLDcompileExpression($expression)
    {
        // Utilise l'analyseur syntaxique de php pour d�composer l'expression en tokens
        $tokens = token_get_all(self::PHP_START_TAG . $expression . self::PHP_END_TAG);
        
        // Enl�ve le premier et le dernier token (PHP_START_TAG et PHP_END_TAG)
        array_shift($tokens);
        array_pop($tokens);

        $result=array();    // le tableau r�sultat
        $h='';              // l'expression en cours dans le collier

        foreach ($tokens as $token) 
        {
            // Le token est un bloc de texte
            if (is_string($token))
            {
                // Si c'est le signe ':', on ajoute l'expression en cours au tableau r�sultat
                if ($token===':')
                {
                    if ($h) 
                    {
                        $result[]=$h;
                        $h='';	
                    }
                }
                
                // Sinon, on ajoute le texte � l'expression en cours
                else
                    $h.=$token;

                // Passe au token suivant
                continue;
            }
            
            // Il s'agit d'un vrai token, extrait le type et la valeur du token 
            list($type, $data) = $token;
            
            // Si c'est autre chose qu'une variable, on se contente d'ajouter � l'expression en cours 
            if ($type !== T_VARIABLE)
            {
                // concat�ne. Il faut que ce soit du php valide
            	$h.=$data;

                // Passe au token suivant
                continue;
            }

            // C'est une variable, on la compile
                
            // Enl�ve le signe $ de d�but
            $var=substr($data,1);
            
            // Teste si c'est une variable cr��e par le template (loop, etc.)
            foreach (self::$datasources as $datasource)
            {
                if (isset($datasource[$var]))
                {
                    // trouv� !
                    $h.= $datasource[$var];

                    // Passe au token suivant
                    continue 2;
                }    
            }

            // C'est une source de donn�es
            $name=$value=$code=null;
            if (!Template::getDataSource($var, $name, $value, $code))
                throw new Exception("Impossible de compiler le template : la source de donn�es <code>$var</code> n'est pas d�finie.");

            self::$bindings[$name]=$value;
            $h.= $code;
        }
        
        // Ajoute l'expression en cours au tableau et retourne le r�sultat
        if ($h) $result[]=$h;
        return $result;
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
                    $test=self::compileField($test, true);
                    
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
        $test=self::compileField($test, true);
                
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
                            $test=self::compileField($test, true);
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
        $on=self::compileField($on, true);
            
        // R�cup�re et traite l'attribut as
        $key='key';
        $value='value';
        if (($as=$node->getAttribute('as')) !== '')
        {
            $var='\$([a-zA-Z\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)'; // synchro avec le $var de compileField 
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
            $default=self::compileField($default, true);

        echo self::PHP_START_TAG, "Template::runSlot('",addslashes($name), "'";
        if ($default !== '') echo ",'",addslashes($default),"'";
        echo ");", self::PHP_END_TAG;

    }
}
?>