<?php
/*

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
 * Syntaxe des templates :
 * 
 * - commentaires de templates : slash-étoile commentaire étoile-slash. 
 * Systématiquement enlevés.
 * 
 * - commentaire html : <!-- commentaire -->
 * Enlevés si l'option templates.removehtmlcomments est activée dans le
 * fichier de configuration
 * 
 * - Données : $title, {$title:'pas de titre'}, {Config::get('env')}
 * Examine les sources de données indiquées à Template::Run et affiche
 * le premier des champs qui retourne autre chose que null ou une chaine
 * vide. Si tous les champ sont vides, affiche la valeur par défaut.
 * Si tous les champ sont vides et qu'aucune valeur par défaut n'a été 
 * indiquée, rien ne sera affiché (tout ce qu'il y a entre les crochets
 * disparaît).
 * Pour des raisons de compatibilité ascendante, l'ancienne syntaxe 
 * utilisée dans les templates est également supportée ([title], 
 * [titoriga:titorigm])
 * 
 * - Expression dans la valeur d'un attribut : <tag attribut="$data" />
 * ou <tag attribut="{$data:'default'}" />
 * La syntaxe est la même que pour un champ de données. La valeur de 
 * l'attribut sera remplacée par la valeur du champ.
 * Dans ce mode, l'ensemble de l'attribut est compilé de manière à 
 * retourner une expression. Cela permet d'écrire, par exemple
 * <include file="/templates/$file.htm" /> -> '/templates/'.$file.'.htm'
 * TODO: à tester
 * 
 * - Champ dans le nom d'un attribut : <p [attr]="xxx" />
 * Interdit : le template doit rester un fichier xml correctement formé et
 * le dollar n'est pas un caractère valide pour un nom d'attribut.
 * et avec un dollar ?
 * 
 * - Bloc optionnel : <opt>contenu</opt>
 * Permet de n'afficher un bloc de texte que si au moins un des champs
 * présents dans le contenu ont retournés une valeur.
 * TODO : Si contenu ne contient aucun champ, il sera toujours affiché.
 * Les blocs <opt> peuvent être imbriqués les uns dans les autres.
 * Le tag opt accepte un attribut optionnel "min" qui permet d'indiquer
 * le nombre minimum de champs qui doivent être renseignés au sein du bloc
 * pour que le bloc soit affiché (par défaut, min="1").
 * 
 * - Bloc optionnel : <tag test="condition">contenu</tag>
 * L'attribut "test" peut être ajouté à n'importe quel tag html. Il représente
 * une condition qui peut éventuellement contenir des champs. Lors de l'exécution
 * la condition est évaluée et l'ensemble du bloc, tag compris, sera supprimé si
 * la condition n'est pas remplie.
 * Exemple : <div test="IsAdmin([user])" id="adminBar" >...</div>
 * 
 * - Tag optionnel : <tag if="condition">contenu</tag>
 * L'attribut "if" peut être ajouté à n'importe quel tag html. Il représente
 * une condition qui peut éventuellement contenir des champs. Lors de l'exécution
 * la condition est évaluée et le tag ne sera affiché que si la condition est 
 * remplie. Les éléments contenus dans le bloc, eux, sont toujours affichés, que 
 * la condition soit remplie ou non.
 * Exemple : <a href="[link]" if="[link]">[titre]</a>
 * Si on a un lien, générera <a href="http://xxx">Titre du doc</a>
 * Sinon, générera : Titre du doc
 * Remarque : on peut combiner, dans un même tag, les attributs test et if.
 * 
 */
 
/**
 * @package     fab
 * @subpackage  template
 * @author      Daniel Ménard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id$
 */
class TemplateCompiler
{
    const PHP_START_TAG='<?php ';
    const PHP_END_TAG="?>";

    private static $opt=0;
    private static $datasources=array();
    private static $bindings=array();
    
    /**
     * Compile un template 
     * 
     * Génère une exception si le tempalte est mal formé ou contient des erreurs.
     * 
     * @param string $source le code source du template à compiler
     * @return string le code php du template compilé
     */
    public static function compile($source)
    {
        // Supprime les commentaires de templates : /* xxx */
        //$source=preg_replace('~/\*.*?\*/~ms', null, $source);
        
        // Englobe le template dans des balises <template>...</template>
        if (substr($source, 0, 6)==='<?xml ')
        {
            // le fichier a une déclaration xml
            // le premier tag trouvé est la racine, on l'entoure avec <template></template>
            $source=preg_replace('~(\<[^!?])~', '<template>$1', $source, 1);
            $source.='</template>';
            
            $xmlDeclaration=strtok($source, '>').'>';
        }
        else
        {
            // Pas de déclaration xml, donc pas d'encoding : traduit en utf8
            $source=self::translate_entities($source);
    
            // si le fichier à un DTD <!DOCTYPE..., il faut prendre des précautions
            $source=preg_replace('~(\<!DOCTYPE [^>]+\>)~im', '$1<template>', $source, 1, $nb);
            if ($nb==0) $source='<template ignore="true">'.$source;
            $source.='</template>';
            
            // on ajoute la déclaration xml
            $source='<?xml version="1.0" encoding="ISO-8859-1" ?>' . $source;
            
            $xmlDeclaration='';  
        }
        
        // Charge le template comme s'il s'agissait d'un document xml
        $xml=new domDocument('1.0', 'iso-8859-1');
        if (false or Config::get('templates.removeblanks'))
            $xml->preserveWhiteSpace=false; // à true par défaut

        // gestion des erreurs : voir comment 1 à http://fr.php.net/manual/en/function.dom-domdocument-loadxml.php
        //libxml_clear_errors(); // >PHP5.1
        //libxml_use_internal_errors(true);// >PHP5.1
        if (! $xml->loadXML($source)) // options : >PHP5.1
//        if (! $xml->loadXML($source, LIBXML_COMPACT)) // options : >PHP5.1
        {
            // >PHP5.1
            echo 'Erreur lors du chargement du template';
            //echo '<pre>'; print_r(libxml_get_errors()); echo '</pre>';
            return;
        }
        unset($source);
                    
        // Instancie tous les templates présents dans le document
        self::compileMatches($xml);        

        // Lance la compilation
        ob_start();
        self::$opt=0;
        self::$datasources=array();
        self::$bindings=array();
        if ($xmlDeclaration) echo $xmlDeclaration, "\n";
        self::compileChildren($xml); //->documentElement
        $result=ob_get_clean();
        if (count(self::$bindings))
        {
            $h=self::PHP_START_TAG . "\n//Liste des variables de ce template\n" ;
            foreach (self::$bindings as $var=>$binding)
                $h.='    $' . $var . '=' . $binding . ";\n";
            $h.=self::PHP_END_TAG;
            $result = $h.$result;
        }
        return $result;
    }
    
    /**
     * Compile les templates présents dans le document
     * 
     * La fonction récupère tous les templates présents dans le document
     * (c'est à dire les noeuds ayant un attribut match="xxx") et instancie tous
     * les noeuds du document qui correspondent
     * 
     * @param DOMDocument $xml le document xml à traiter
     */
    private static function compileMatches(DOMDocument $xml)
    {
        // Crée la liste des templates = les noeuds avec un attribut match="xxx""
        $xpath=new DOMXPath($xml);
        $templates=$xpath->query('//*[@match]');
         
        // Traite chaque template dans l'ordre d'apparation dans le document
        foreach($templates as $template)
        {
            // Supprime le template du document, pour qu'il n'apparaisse pas dans la sortie
            $template->parentNode->removeChild($template);
            
            // Crée la liste de tous les noeuds sélectionnés par ce template
            $matches=$xpath->query($template->getAttribute('match'));
            
            // Traite chaque noeud trouvé dans l'ordre
            foreach($matches as $match)
            {
                // Génère un tableau contenant les attributs du noeud d'origine
                $replace=array();
                foreach ($match->attributes as $attribute)
                    $replace['['.$attribute->nodeName.']']=$attribute->value;

                // Clone le template pour créer un nouveau noeud 
                $node=$template->cloneNode(true);
                $node->removeAttribute('match');

                // Applique au nouveau noeud les attributs de l'ancien noeud
                self::instantiateMatch($node, $replace);
                
                // Remplace l'ancien noeud (l'appel de template) par le nouveau (le template instancié)
                $match->parentNode->replaceChild($node, $match);
            }
        }
    }
    
    /**
     * Instancie récursivement un template avec une liste d'attributs
     * 
     * @param DOMNode $node le template à instancier
     * @param array $replace un tableau contenant les attributs à appliquer
     * au template
     */
    private static function instantiateMatch(DOMNode $node, $replace)
    {
        switch ($node->nodeType)
        {
            case XML_TEXT_NODE:
                if ($node->isWhitespaceInElementContent()) return;
                $node->nodeValue=strtr($node->nodeValue, $replace);
                return;
            case XML_ELEMENT_NODE:
                if ($node->hasAttributes())
                    foreach ($node->attributes as $key=>$attribute)
                        $attribute->value=strtr($attribute->value, $replace);
                                
                if (! $node->hasChildNodes()) return;
                foreach ($node->childNodes as $child)
                    self::instantiateMatch($child, $replace);
                return;
        }
    }
    
    /**
     * Convertit en caractères les entités html présentes dans le template  
     * 
     * @param string $source le code source du template à convertir
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
     * @param DOMNode $node le noeud à compiler
     */
    private static function compileNode(DOMNode $node)
    {
        static $tags= array
        (
            //'template'=>'compileTemplate',
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
            case XML_TEXT_NODE:
                echo self::compileField(utf8_decode($node->nodeValue)); // ou textContent ? ou wholeText ? ou data ?
                return;
//                if ($node->isWhitespaceInElementContent())
//                    echo '{VIDE', utf8_decode($node->nodeValue), '}'; // ou textContent ? ou wholeText ? ou data ?
//                else
//                    echo '{', utf8_decode($node->nodeValue), '}'; // ou textContent ? ou wholeText ? ou data ?
//                return;
            case XML_COMMENT_NODE:
                if (false or Config::get('templates.removehtmlcomments')) return;
                echo utf8_decode($node->ownerDocument->saveXML($node));
                // Serait plus efficace : $node->ownerDocument->save('php://output');
                return;
            case XML_PI_NODE:
                echo utf8_decode($node->ownerDocument->saveXML($node));
                // Serait plus efficace : $node->ownerDocument->save('php://output');
                return;
            case XML_ELEMENT_NODE:
                $name=$node->tagName;
                
                if (isset($tags[$name]))
                    return call_user_func(array('TemplateCompiler', $tags[$name]), $node);

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
                    echo self::PHP_START_TAG, 'if ($tmp=(', $if, ')):',self::PHP_END_TAG;
                    $node->removeAttribute('if');
                }

                if ($node->hasAttributes())
                    foreach ($node->attributes as $key=>$attribute)
                        $attribute->value=self::compileField($attribute->value, false);

                if (!$ignore)
                {        
                    echo '<', $name;    // si le terme a un préfixe, il figure déjà dans name (e.g. <test:h1>)
                    if ($node->namespaceURI !== $node->parentNode->namespaceURI)
                        echo ' xmlns="', $node->namespaceURI, '"'; 
                }
                
                // Accès aux attributs xmlns : cf http://bugs.php.net/bug.php?id=38949
                // apparemment, fixé dans php > 5.1.6, à vérifier
                
                if (! $ignore and $node->hasAttributes())
                {
                    foreach ($node->attributes as $key=>$attribute)
                    {
                        $value=utf8_decode($attribute->value);
                        $quot=(strpos($value,'"')===false) ? '"' : "'";
                        echo ' ', $attribute->nodeName, '=', $quot, $value, $quot;
                    }
                }
                if ($node->hasChildNodes())
                {
                    if (! $ignore) echo '>';
                    if ($if !== '')                     
                        echo self::PHP_START_TAG, 'endif',self::PHP_END_TAG;
                    self::compileChildren($node);
                    if ($if !== '')                     
                        echo self::PHP_START_TAG, 'if ($tmp):',self::PHP_END_TAG;
                    if (! $ignore) echo '</', $node->tagName, '>';
                    if ($if !== '')                     
                        echo self::PHP_START_TAG, 'endif',self::PHP_END_TAG;
                }
                else
                {
                    if (! $ignore) echo ' />';
                    if ($if !== '')                     
                        echo self::PHP_START_TAG, 'endif',self::PHP_END_TAG;
                }

                if ($test !== '')                     
                    echo self::PHP_START_TAG, 'endif',self::PHP_END_TAG;
                
                return;
            case XML_DOCUMENT_NODE:
                self::compileChildren($node);
                return;
            case XML_DOCUMENT_TYPE_NODE:
                echo "\n";
                echo utf8_decode($node->ownerDocument->saveXML($node));
                return;
            default:
                echo '***Type de noeud non géré : ', $node->nodeType, "\n";
                return;
        }
    }


    /**
     * Compile les fils d'un noeud et tous leurs descendants   
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
     * Compile les balises de champ présents dans un texte   
     * 
     * @param string $source le texte à examiner
     * @param boolean $phpTags indique q'il faut ou non ajouter les tags 
     * d'ouverture et de fermeture de php dans le code généré
     * @return string la version compilée du source
     */
    public static function compileField($source, $asExpression=false) // TODO: repasser private
    {
// compilé=
//echo ($x=f(0) or $x=f(1) or $x=f(2) or $x=f(3) or $x=f(4)) ? $x : "au bout";

        // Expression régulière pour un nom de variable php valide
        // Source : http://fr2.php.net/variables (dans l'intro 'essentiel')
        $var='\$([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)';

        // Expression régulière pour une expression valide dans un template
        $exp='\{[^}]*\}';
        
        // Expression régulière combinant les deux
        $re="~$var|$exp~";
        
        $h='012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789'."\n";
//        echo 'Source initial : <pre>', $h, htmlentities($source), '</pre><br />';

        // Convertit l'ancienne syntaxe des champs dans la nouvelle syntaxe 
        $source=preg_replace('~\[([a-zA-Z]+)\]~', '$$1', $source);  // [titre]
        $source=preg_replace                                        // [titoriga:titorigm]
        (
            '~\[([^\]]+)\]~e', 
            "'{\$' . preg_replace('~:(?=[a-zA-Z])~',':\$','$1') . '}'", 
            $source
        );
//        echo 'Source compatible : <pre>', htmlentities($source), '</pre><br />';

        // Boucle tant qu'on trouve des choses dans le source passé en paramètre
        $start=0;
        $result='';
        
        for($i=1;;$i++)
        {
            // Recherche la prochaine expression
            if (preg_match($re, $source, $match, PREG_OFFSET_CAPTURE, $start)==0) break;
            $expression=$match[0][0];
            $len=strlen($expression);
            $offset=$match[0][1];
            
            // Envoie le texte qui précède l'expression trouvée
            if ('' != $text=substr($source, $start, $offset-$start))
            {
                if ($asExpression)
                    $result.=($result?' . ':'') . '\'' . addslashes($text) . '\'';
                else
                    $result.=$text;
            }
                        
            // Enlève les accolades qui entourent l'expression
            if ($expression[0]==='{') $expression=substr($expression, 1, -1);
            
            // Compile l'expression
            $tokens = token_get_all(self::PHP_START_TAG . $expression . self::PHP_END_TAG);
            
            // Enlève les tokens php de début et de fin
            array_shift($tokens);
            array_pop($tokens);

            $hasOr=false;
            $h='';
//            print_r($tokens);
            foreach ($tokens as $token) 
            {
                if (is_string($token))
                {
//                    echo 'string ', htmlentities($token), "\n";
                    if ($token===':')
                    {
                        $h.=' or ';
                        $hasOr=true;
                    }
                    else
                        $h.=$token;
                }
                else
                {
                    list($id, $data) = $token;
//                    echo token_name($id), ' : ' , htmlentities($data), "\n";
                    switch ($id)
                    {
                        case T_VARIABLE:
//                            $h.= "Template::field('" . substr($data,1) . "')";

                            $var=substr($data,1);
                            $field=null;
                            foreach (self::$datasources as $datasource)
                            {
                                if (isset($datasource[$var]))
                                {
                                    $field=$datasource[$var];
                                    break;
                                }    
                            }
                            if (is_null($field)) 
                            {
                                if (isset(self::$bindings[$var]))
                                {
                                    $field='$' .$var;
                                }
                                else
                                {
                                    $field=Template::fieldSource(substr($data,1));
                                    self::$bindings[$var]='&' . $field;
                                    $field='$'.$var;
                                }
                            }
                            if ($field=='') $field="'Champ inconnu : " . $data . " '";//TODO
                            $h.= '$x=' . $field;
                            break;
                        default: 
                            $h.='$x=' . $data;
                    }
                }
            }
            
            if ($asExpression)
            {
                if ($result) $result .= ' . ';
                $result.= "($h)?\$x:''";
            }
            else
            {
                $result.=self::PHP_START_TAG . 'echo (' . $h . ') ? Template::Filled($x) : \'\''. self::PHP_END_TAG;
            }
            
            // Passe au suivant
            $start=$offset + $len;
        }

        // Envoie le texte qui suit le dernier match 
        if ('' != $text=substr($source, $start))
        {
            if ($asExpression)
                $result.=($result?' . ':'') . '\'' . addslashes($text) . '\'';
            else
                $result.=$text;
        }
        //if ($i>0) $h="($h)";        
//        echo 'Expression compilée : <pre>' , htmlentities($result), '</pre><br />';
        return $result;
    }

    /**
     * Compile le tag 'template' représentant la racine de l'arbre xml
     * 
     * Le tag template est ajouté pour mettre au format xml un template qui
     * ne l'est pas. On se contente de générer le contenu du tag, en ignorant
     * le tag lui-même.
     *
     * @param DOMNode $node le noeud à compiler
     */
    private static function compileTemplate(DOMNode $node)
    {
        self::compileChildren($node);
        // TODO : serait inutile si on pouvait écrire <template collapse="true">
    }
/*
    private static function insertPhp(DOMNode $node, $data, $before=true)
    {
        $piNode=$node->ownerDocument->createProcessingInstruction('php', $data);
        if ($before)
            $node->parentNode->insertBefore($piNode, $node);
        else
            $node->parentNode->insertBefore($piNode, $node->nextSibling);
    }
*/
    /**
     * Compile un bloc &lt;opt&gt;&lt;/opt&gt;   
     *
     * @param DOMNode $node le noeud à compiler
     */
    private static function compileOpt(DOMNode $node)
    {
        // Récupère la condition
        $min=$node->getAttribute('min') or '';
        
        // Génère le code
        echo self::PHP_START_TAG . 'Template::optBegin()' .self::PHP_END_TAG;
        ++self::$opt;
        self::compileChildren($node);
        --self::$opt;
        echo self::PHP_START_TAG . "Template::optEnd($min)" .self::PHP_END_TAG; 
    }

   
    /**
     * Compile un bloc if/elseif/else
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

        $next=$node;
        for(;;)
        {
            // Génère le tag
            switch($tag=$next->tagName)
            {
                case 'else':
                    echo self::PHP_START_TAG, $tag, ':', self::PHP_END_TAG;
                    break;
                case 'if':
                case 'elseif':
                    // Récupère la condition
                    if (($test=$next->getAttribute('test')) === '')
                        throw new Exception("Tag $tag incorrect : attribut test manquant");
                    $test=self::compileField($test, true);
                    
                    // Génère le tag et sa condition
                    echo self::PHP_START_TAG, $tag, ' (', $test, '):', self::PHP_END_TAG;
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
            if ($next->nodeType!==XML_ELEMENT_NODE or
               ($next->tagName!=='else' and $next->tagName!=='elseif')) break;

        }
                
        // Ferme le dernier tag ouvert
        echo self::PHP_START_TAG, 'endif', self::PHP_END_TAG;
        
        // Supprime tous les noeuds qu'on a traité
        while(!$node->nextSibling->isSameNode($next))
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
        if (($test=$node->getAttribute('test')) === '')
            $test='true';
                
        // Génère le tag et sa condition
//        echo self::PHP_START_TAG, 'switch (', $test, '):', self::PHP_END_TAG, "\n";
        echo self::PHP_START_TAG, 'switch (', $test, '):', "\n";
                        
        // Génère les fils (les blocs case et default)
        self::compileSwitchCases($node);

        // Ferme le switch
        echo self::PHP_START_TAG, 'endswitch', self::PHP_END_TAG;
//        echo 'endswitch', self::PHP_END_TAG;
    }

    private static function compileSwitchCases($node)
    {
        $first=true;
        
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
                            if (($test=$node->getAttribute('test')) === '')
                                throw new Exception("Tag case incorrect : attribut test manquant");
                            echo ($first?'':self::PHP_START_TAG.'break;'), 'case ', $test, ':', self::PHP_END_TAG;
                            self::compileChildren($node);
                            break;
                        case 'default':
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
//        throw new Exception('Le tag else doit suivre immédiatement un tag if, seuls des blancs sont autorisés entre les deux.');
        echo 'Tag ', $node->tagName, ' isolé.';
    }
    

    private static function compileLoop($node)
    {
        // Récupère l'objet sur lequel il faut itérer
        if (($on=$node->getAttribute('on')) === '')
            throw new Exception("Tag loop incorrect : attribut 'on' manquant");
        $on=self::compileField($on, true);
            
        // Récupère et traite l'attribut as
        $key='key';
        $value='value';
        if (($as=$node->getAttribute('as')) !== '')
        {
            $var='\$?([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)';
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
        $key='$'.$key;
        $value='$'.$value;    
        echo self::PHP_START_TAG, "foreach($on as $key=>$value):", self::PHP_END_TAG;
        if ($node->hasChildNodes())
        {
            array_unshift(self::$datasources, array('key'=>$key, 'value'=>$value)); // empile au début
            self::compileChildren($node);
            array_shift(self::$datasources);    // dépile au début
        }
        echo self::PHP_START_TAG, 'endforeach;', self::PHP_END_TAG;
    }
}
?>