<?php
/*

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
        // $source=preg_replace('~/\*.*?\*/~ms', null, $source);
        
        // Englobe le template dans des balises <template>...</template>
        if (substr($source, 0, 6)==='<?xml ')
        {
            // le fichier a une d�claration xml
            // le premier tag trouv� est la racine, on l'entoure avec <template></template>
            $source=preg_replace('~(\<[^!?])~', '<template>$1', $source, 1);
            $source.='</template>';
            
            $xmlDeclaration=strtok($source, '>').'>';
        }
        else
        {
            // Pas de d�claration xml, donc pas d'encoding : traduit en utf8
            $source=self::translate_entities($source);
    
            // si le fichier � un DTD <!DOCTYPE..., il faut prendre des pr�cautions
            $source=preg_replace('~(\<!DOCTYPE [^>]+\>)~im', '$1<template>', $source, 1, $nb);
            if ($nb==0) $source='<template ignore="true">'.$source;
            $source.='</template>';
            
            // on ajoute la d�claration xml
            $source='<?xml version="1.0" encoding="ISO-8859-1" ?>' . $source;
            
            $xmlDeclaration='';  
        }
        
        // Charge le template comme s'il s'agissait d'un document xml
        $xml=new domDocument('1.0', 'iso-8859-1');
        if (false or Config::get('templates.removeblanks'))
            $xml->preserveWhiteSpace=false; // � true par d�faut

        // gestion des erreurs : voir comment 1 � http://fr.php.net/manual/en/function.dom-domdocument-loadxml.php
        libxml_clear_errors(); // >PHP5.1
        libxml_use_internal_errors(true);// >PHP5.1
//        if (! $xml->loadXML($source, LIBXML_COMPACT)) // options : >PHP5.1, fait planter php
        if (! $xml->loadXML($source)) // options : >PHP5.1
        {
            $h="Impossible de compiler le template, ce n'est pas un fichier xml valide :<br />\n"; 
            foreach (libxml_get_errors() as $error)
            	$h.= "- ligne $error->line, colonne $error->column : $error->message<br />\n";

            throw new Exception($h);
        }
        unset($source);

        // Instancie tous les templates pr�sents dans le document
        self::compileMatches($xml);        

        // Lance la compilation
        ob_start();
        self::$loop=self::$opt=0;
        self::$datasources=array();
        self::$bindings=array();
        if ($xmlDeclaration) echo $xmlDeclaration, "\n";
        self::compileChildren($xml); //->documentElement
        $result=ob_get_clean();
        if (count(self::$bindings))
        {
            $h=self::PHP_START_TAG . "\n//Liste des variables de ce template\n" ;
            foreach (self::$bindings as $var=>$binding)
                $h.='    ' . $var . '=' . $binding . ";\n";
            $h.=self::PHP_END_TAG;
            $result = $h.$result;
        }
        return $result;
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
        $templates=$xpath->query('//*[@match]');
        if ($templates->length ==0) return;

        // Traite chaque template dans l'ordre d'apparation dans le document
        header('content-type: text/plain');
        foreach($templates as $template)
        {
            echo '--------------------------------------------', "\n";
            echo "template :\n", $template->ownerDocument->saveXML($template), "\n";
            
            // Supprime le template du document, pour qu'il n'apparaisse pas dans la sortie
            $template->parentNode->removeChild($template);
            
            // Cr�e la liste de tous les noeuds s�lectionn�s par ce template
            $matches=$xpath->query($template->getAttribute('match'));

            // Traite chaque noeud trouv� dans l'ordre
            foreach($matches as $match)
            {
                echo "\nmatch original:\n", $match->ownerDocument->saveXML($match), "\n";
                // G�n�re un tableau contenant les attributs du noeud d'origine

                // Clone le template pour cr�er un nouveau noeud 
                $node=$template->cloneNode(true);
                
                // Supprime l'attribut match
                $node->removeAttribute('match');
                
                // Recopie tous les arguments (les attributs du match qui existent dans le template)
                echo "Remplacement des attributs :\n";
                foreach ($node->attributes as $attribute)
                {
                    echo "- ", $attribute->name, "='", $attribute->value, "' : ";
                    if ($match->hasAttribute($attribute->name))
                    {
                        echo "pr�sent dans le match, nouvelle valeur=", $match->getAttribute($attribute->name), "\n";
//                        $node->setAttribute($attribute->name, $match->getAttribute($attribute->name));
                        $attribute->value=$match->getAttribute($attribute->name);
                    }
                    else
                        echo "absent du match, valeur inchang�e\n";
                }

                // Applique au nouveau noeud les attributs de l'ancien noeud
                self::instantiateMatch($template, $node, array(), $match);
                echo "\nmatch apr�s replace:\n", $node->ownerDocument->saveXML($node), "\n";
                
                // Remplace l'ancien noeud (l'appel de template) par le nouveau (le template instanci�)
                $match->parentNode->replaceChild($node, $match);
            }
        }
        echo '========================= TERMINE =========================', "\n";
        
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
                $node->nodeValue=strtr($node->nodeValue, $replace);
                self::compileSelects($template, $node, $match);
                return;
                
            case XML_ELEMENT_NODE:
                if ($node->hasAttributes())
                    foreach ($node->attributes as $key=>$attribute)
                        $attribute->value=strtr($attribute->value, $replace);
                                
                if ($node->hasChildNodes())
                    foreach ($node->childNodes as $child)
                        self::instantiateMatch($template, $child, $replace, $match);

                return;
            default:
                echo "\ninstantiateMatch non gere : ", $node->nodeType, '(', self::nodeType($node),')';
        }
    }
    
    private static function compileSelects(DOMElement $template, DOMText $node, DOMElement $matchNode)
    {
        /*
         * Algorithme :
         * 
         * On a un noeud en cours de type DOMText qui contient des expressions select
         * [avant{select('xpath')}apr�s...]
         * 
         * On rep�re l'expression, et on coupe le noeud texte en deux (splitText)
         * [avant][apr�s...]
         * 
         * On ex�cute l'expression xpath qui retourne une liste de noeuds
         * -> <N1 /><N2 />...<Nn />
         * 
         * On clone ces noeuds et on les ins�re entre les deux noeuds texte
         * [avant]<N1 /><N2 />...<Nn />[apr�s...]
         * 
         * Le second noeud texte ([apr�s...]) devient le noeud en cours et on recommence
         * jusqu'� ce qu'on ne trouve plus de select()
         * 
         * Cas particulier : l'expression xpath retourne des noeuds de type DOMAttr.
         * Dans ce cas, chaque attribut est ajout� au noeud parent du noeud texte en 
         * cours, sauf s'il s'agit d'un attribut qui existe d�j� dans le parent ou s'il 
         * s'agit d'un des param�tres du template. 
         * 
         * Cas d'erreurs, g�n�rent une exception : expression xpath vide, expression 
         * xpath erron�e, expression xpath qui retourne des types de noeuds incorrects
         * (par exemple tout le document)
         */

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
            $node->splitText($offset);
            
            // Le noeud de droite devient le nouveau noeud en cours
            $node=$node->nextSibling;
            
            // Supprime l'expression xpath trouv�e, elle figure au d�but
            $node->nodeValue=substr($node->nodeValue, $len);

            // Ex�cute l'expression xpath trouv�e
            $xpath=new DOMXPath($matchNode->ownerDocument);
            $nodeSet=$xpath->query($expression, $matchNode);
            
            // Expresion xpath erron�e ?
            if ($nodeSet===false)
                throw new Exception("Erreur dans l'expression xpath [$expression]");
                
            // Aucun r�sultat : rien � faire
            if ($nodeSet->length==0) continue;
            
            // Ins�re entre les deux noeuds texte les noeuds s�lectionn�s par l'expression xpath
            // Il est important de cloner chaque noeud car il sera peut-�tre r�utilis� par un autre select
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
                        return;

                    // Types de noeuds ill�gaux : exception
                    case XML_DOCUMENT_NODE:
                        throw new Exception("Expression xpath incorrecte : $expression. Les noeuds retourn�s ne peuvent pas �tre ins�r�s � la postion actuelle");
                        
                    default:
                        return __METHOD__ . " : type de noeud non g�r� ($node->nodeType)";
                }
            }
        }
    }

    private static function nodeType($node)
    {
        switch ($node->nodeType)
        {
            case XML_ATTRIBUTE_NODE: return 'XML_ATTRIBUTE_NODE';
            case XML_TEXT_NODE: return 'XML_TEXT_NODE';
            case XML_COMMENT_NODE: return 'XML_COMMENT_NODE';
            case XML_PI_NODE: return 'XML_PI_NODE';
            case XML_ELEMENT_NODE: return 'XML_ELEMENT_NODE';
            case XML_DOCUMENT_NODE: return 'XML_DOCUMENT_NODE';
            case XML_DOCUMENT_TYPE_NODE: return 'XML_DOCUMENT_TYPE_NODE';
            case XML_CDATA_SECTION_NODE: return 'XML_CDATA_SECTION_NODE';
            default:
                return "type de noeud non g�r� ($node->nodeType)";
        }
    	
    } 
    /**
     * Convertit en caract�res les entit�s html pr�sentes dans le template  
     * 
     * @param string $source le code source du template � convertir
     * @return string le source convertit
     */
    private static function translate_entities($source, $reverse=true)
    {
        static $literal2NumericEntity=null;
            
        if (is_null($literal2NumericEntity))
        {
            $transTbl = get_html_translation_table(HTML_ENTITIES);
    
            foreach ($transTbl as $char => $entity)
            {
                if (strpos('&#038;"<>', $char) !== false) continue;
                $literal2NumericEntity[$entity] = '&#'.ord($char).';';
            }
        }
    
        if ($reverse)
            return strtr($source, array_flip($literal2NumericEntity));
        else
            return strtr($source, $literal2NumericEntity);
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
            'template'=>'compileTemplate',
            'loop'=>'compileLoop',
            'if'=>'compileIf',
            'else'=>'elseError',
            'elseif'=>'elseError',
            'switch'=>'compileSwitch',
            'case'=>'elseError',
            'default'=>'elseError',
            'opt'=>'compileOpt'
        );
        
        switch ($node->nodeType)
        {
            case XML_TEXT_NODE:     // du texte
                echo self::compileField($node->nodeValue); // ou textContent ? ou wholeText ? ou data ?
                return;

            case XML_COMMENT_NODE:  // un commentaire
                if (false or Config::get('templates.removehtmlcomments')) return;
                echo utf8_decode($node->ownerDocument->saveXML($node));
                // Serait plus efficace : $node->ownerDocument->save('php://output');
                return;
                
            case XML_PI_NODE:       // une directive (exemple : <?xxx ... ? >)
                echo utf8_decode($node->ownerDocument->saveXML($node));
                // Serait plus efficace : $node->ownerDocument->save('php://output');
                return;
                
            case XML_ELEMENT_NODE:  // un tag
                // R�cup�re le nom du tag
                $name=$node->tagName;
                
                // S'il s'agit de l'un de nos tags, appelle la m�thode correspondante
                if (isset($tags[$name]))
                    return call_user_func(array('TemplateCompiler', $tags[$name]), $node);

                // R�cup�re les attributs qu'on g�re
                $ignore=$node->getAttribute('ignore')=='true';

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

                // G�n�re le tag
                if (!$ignore)
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
                            $value=utf8_decode($value);
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
                
                // G�n�re tous les fils et la fin du tag
                if ($node->hasChildNodes())
                {
                    if (! $ignore) echo '>';
                    if ($if !== '')                     
                        echo self::PHP_START_TAG, 'endif;',self::PHP_END_TAG;
                    self::compileChildren($node);
                    if ($if !== '')                     
                        echo self::PHP_START_TAG, 'if ($tmp):',self::PHP_END_TAG;
                    if (! $ignore) echo '</', $node->tagName, '>';
                    if ($if !== '')                     
                        echo self::PHP_START_TAG, 'endif;',self::PHP_END_TAG;
                }
                
                // Cas d'un noeud sans fils
                else
                {
                    if (! $ignore) echo ' />';
                    if ($if !== '')                     
                        echo self::PHP_START_TAG, 'endif;',self::PHP_END_TAG;
                }

                if ($test !== '')                     
                    echo self::PHP_START_TAG, 'endif;',self::PHP_END_TAG;
                
                return;
                
            case XML_DOCUMENT_NODE:     // L'ensemble du document xml
                self::compileChildren($node);
                return;
                
            case XML_DOCUMENT_TYPE_NODE:    // Le DTD du document
                echo utf8_decode($node->ownerDocument->saveXML($node)), "\n";
                return;
            
            case XML_CDATA_SECTION_NODE:    // Un bloc CDATA : <![CDATA[ on met <ce> <qu'on> $veut ]]>
                echo utf8_decode($node->ownerDocument->saveXML($node));
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

        // COnvertit le texte ytf8->iso8859-1
        $source=utf8_decode($source);
        
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
    private static function compileExpression($expression)
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
    

    private static function compileLoop($node)
    {
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
}
?>