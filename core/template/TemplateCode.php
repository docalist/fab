<?php
/**
 * @package     fab
 * @subpackage  template
 * @author      Daniel Ménard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id$
 */

/**
 * Analyseur de code du gestionnaire de templates
 * 
 * @package     fab
 * @subpackage  template
 */
class TemplateCode
{
    const PHP_START_TAG='<?php ';
    const PHP_END_TAG="?>";

    /**
     * Pour l'analyse des expressions, on utilise token_get_all, mais on ajoute
     * deux tokens qui n'existent pas en standard : T_CHAR pour les caractères et
     * T_END qui marque la fin de l'expression. Cela nous simplifie l'analyse.
     */
    const T_CHAR=20000; // les tokens actuels de php vont de 258 à 375, peu de risque de conflit...
    const T_END=20001;


    /**
     * Analyse une chaine contenant une expression php et retourne un tableau contenant
     * les tokens correspondants
     * 
     * @param string $expression l'expression à analyser
     * @return array les tokens obtenus
     */
    public static function tokenize($expression)
    {
        // Utilise l'analyseur syntaxique de php pour décomposer l'expression en tokens
        ob_start();
        $tokens = token_get_all(self::PHP_START_TAG . $expression . self::PHP_END_TAG);
        $warning=ob_get_clean();
        if ($warning !== '')
            throw new Exception("Une erreur s'est produite durant l'analyse de l'expression :<br />".$expression.'<br />'.$warning);
        
        // Enlève le premier et le dernier token (PHP_START_TAG et PHP_END_TAG)
        array_shift($tokens);
        array_pop($tokens);
        
        // Supprime les espaces du source et crée des tokens T_CHAR pour les caractères
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
                    
                // Sinon on peut supprimer complètement l'espace
                else                     
                    unset($tokens[$index]);
            }
            
            // Supprimer les commentaires
            elseif ($token[0]==T_COMMENT || $token[0]==T_DOC_COMMENT)
                unset($tokens[$index]);
        }
        
        // Comme on a peut-être supprimé des tokens, force une renumérotation des index
        $tokens=array_values($tokens);
        
        // Ajoute la marque de fin (T_END)
        $tokens[]=array(self::T_END,null);

        // Retourne le tableau de tokens obtenu
        return $tokens;
    }


    /**
     * Génère l'expression PHP correspondant au tableau de tokens passés en paramètre
     * 
     * Remarque : les tokens doivent avoir été générés par {@link tokenize()}, cela
     * ne fonctionnera pas avec le résultat standard de token_get_all().
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
     * Affiche les tokens passés en paramètre (debug)
     * 
     * @param array $tokens un tableau de tokens tel que retourné par {@link tokenize()}
     * @return void
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
//        var_export($tokens);
        echo '</pre>';
    }

    private static $currentExpression=null;
    
    /**
     * Evalue l'expression PHP passée en paramètre et retourne sa valeur.
     * 
     * @param string $expression l'expression PHP à évaluer
     * @return mixed la valeur obtenue
     * @throws Exception en cas d'erreur.
     */
    public static function evalExpression($expression)
    {
        if (trim($expression)==='') return null;
        
        // Supprime les éventuelles accolades autour de l'expression
        if ($expression[0]==='{') $expression=substr($expression, 1, -1);

        // Capture la sortie éventuellement générée lors de l'évaluation
        ob_start();

        // Installe un gestionnaire d'exception spécifique
        self::$currentExpression=$expression;
        set_error_handler(array(__CLASS__,'evalError'));

        // Exécute l'expression
        $result=eval('return '.$expression.';');

        // Restaure le gestionnaire d'exceptions précédent
        restore_error_handler();
        self::$currentExpression=null;

        // Récupère la sortie éventuelle
        $h=ob_get_clean();
        
        // L'évaluation n'a pas généré d'exception, mais si une sortie a été générée (un warning, par exemple), c'est une erreur
        if ($h !=='')
            throw new Exception('Erreur dans l\'expression PHP [ ' . $expression . ' ] : ' . $h);
//        echo 'eval(', $expression, ') = '; var_dump($result); echo '<br />';
        // Retourne le résultat
        return $result;     
    }
    
    /** 
     * Gestionnaire d'erreurs appellé par {@link evalExpression} en cas d'erreur 
     * dans l'expression évaluée 
     */
    private static function evalError($errno, $errstr, $errfile, $errline, $errcontext)
    {
        // errContext contient les variables qui existaient au moment de l'erreur
        // Celle qui nous intéresse, c'est l'expression passée à eval
        // on supprimer le 'return ' de début et le ';' de fin (cf evalExpression)
        //$h=substr(substr($errcontext['expression'], 7), 0, -1);
        
        // Génère une exception
        throw new Exception('Erreur dans l\'expression PHP [ ' . self::$currentExpression . ' ] : ' . $errstr . ' ligne '.$errline);
    } 

    /**
     * Analyse et optimise une expression PHP
     * 
     * - appelle une fonction utilisateur lorsqu'une variable est rencontrée
     * - permet d'avoir dans le code de pseudo-fonctions qui vont être appellées si
     *   elles sont rencontrées
     * - les affectations de variables sont interdites (=, .=, &=, |=...)
     * - gère une liste de fonctions autorisées, génère une exception si une fonction
     *   non autorisée est appellée
     * - optimise les expressions en évaluant tout ce qu'il est possible d'évaluer
     * 
     * TODO: compléter la doc 
     * 
     * @param mixed $expression l'expression à analyser. Cette expression peut être 
     * fournie sous la forme d'une chaine de caractère ou sous la forme d'un tableau
     * de tokens tel que retourné par {@link tokenize()}.
     * 
     * @param mixed $varCallback la méthode à appeller lorsqu'une variable est rencontrée
     * dans l'expression analysée. Si vous indiquez 'null', aucun traitement ne sera effectué 
     * sur les variables. Sinon, $varCallback doit être le nom d'une méthode de la classe Template 
     * TODO: à revoir
     * 
     * @param array|null $pseudoFunctions un tableau de pseudo fonctions ("nom pour l'utilisateur"=>callback)
     */
    public static function parseExpression(&$expression, $varCallback=null, $pseudoFunctions=null)
    {
        // Indique si l'expression en cours est évaluable
        // True par défaut, passe à faux quand on rencontre une variable, l'opérateur '=>' dans un array() ou
        // quand on tombe sur quelque chose qu'on ne sait pas évaluer (propriété d'un objet, par exemple)
        $canEval=true;
        
        // Indique si l'expression passée en paramètre était encadrée par des accolades
        $addCurly=false;

        // Indique si on est à l'intérieur d'une chaine de caractères ou non
        $inString=false;
        
        // Compteur utilisé pour gérer l'opérateur ternaire (xx?yy:zz).
        // Incrémenté lorsqu'on rencontre un signe '?', décrémenté lorsqu'on rencontre un signe ':'. 
        // Lorsqu'on rencontre le signe ':' et que $ternary est à zéro, c'est que c'est un collier d'expressions
        $ternary=0;
        
        // Indique si l'expression est un collier d'expressions ($x:$y:$z)
        // Passe à true quand on rencontre un ':' et que ternary est à zéro.
        // Utilisé en fin d'analyse pour "enrober" l'expression finale avec le code nécessaire
        $colon=false;
        
        $curly=0; // TODO : utile ? à conserver ?
        

        // Si $expression est un tableau de tokens, on ajoute juste T_END à la fin
        if (is_array($expression))
        {
            $tokens=$expression;
            $tokens[]=array(self::T_END,null);
        }
        
        // Sinon, on tokenise l'expression
        else
        {
            if (strlen($expression) && $expression[0]==='{')
            {
                $expression=substr($expression, 1, -1);
                $addCurly=true; 
            }
            $tokens=self::tokenize($expression);
        }
        //self::dumpTokens($tokens);
        // Examine tous les tokens les uns après les autres
        for ($index=0; $index<count($tokens); $index++)
        {
            $token=$tokens[$index];
            switch ($token[0])
            {          
                case self::T_CHAR:
                    switch ($token[1])
                    {
                        // Début/fin de chaine de caractère contenant des variables
                        case '"':
                            $inString=! $inString;
                            break;
                            
                        case '}':
                            //if ($curly) $tokens[$index]=null;
                            --$curly;
                            break;
                            
                        // Remplace '!' par '->' sauf s'il s'agit de l'opérateur 'not' 
                        case '!': 
                            if ($index>0) 
                            {
//                                echo '<h1>Signe ! entre</h1>';
//                                self::dumpTokens(array($tokens[$index-1]));
//                                self::dumpTokens(array($tokens[$index+1]));
//                                echo '<hr />';
//
                                // Si ce qui suit est un T_STRING
                                if ($tokens[$index+1][0] == T_STRING)
                                {
                                    // et que ce qui précède est autre chose que :
                                    switch($tokens[$index-1][0])
                                    {
                                        case T_BOOLEAN_AND:
                                        case T_BOOLEAN_OR:
                                        case T_LOGICAL_AND:
                                        case T_LOGICAL_OR:
                                        case T_LOGICAL_XOR:
                                        //case T_VARIABLE:
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
                                            $tokens[$index][0]=T_OBJECT_OPERATOR;
                                            $tokens[$index][1]='->';
                                    }
                                }   
                            }
                            break;

                        // L'opérateur '=' (affectation) n'est pas autorisé
                        case '=': 
                            throw new Exception('affectation interdite dans une expression, utilisez "=="'.$expression);
                            
                        // Symbole '?' : mémorise qu'on est dans un opérateur ternaire (xx?yy:zz)
                        case '?':
                            $ternary++;
                            break;
                            
                        // Symbole ':' : opérateur ternaire ou collier d'expression ($x:$y:$z)
                        case ':':
                            if ($ternary>0)     // On a un '?' en cours
                            {
                                $ternary--;
                            }
                            else                // Aucun '?' en cours : c'est le début ou la suite d'un collier d'expressions
                            {
                                $tokens[$index][1]=') OR $tmp=(';
                                $colon=true;
                            }
                            break;
                            
                        case '`':
                            throw new Exception("L'opérateur d'exécution est interdit dans une expression");
                        case '@':
                            throw new Exception("L'opérateur de suppression des messages d'erreur est interdit dans une expression");
                    }
                    break;  
                    
                // Gestion/instanciation des variables : si on a un callback, on l'appelle                  
                case T_VARIABLE:    
                    if (is_null($varCallback))
                        $canEval=false;
                    else
                    {
                        $var=$token[1];
                        $isCode=TemplateCompiler::$varCallback($var, $inString, $canEval); // TODO : ne pas coder TemplateCompiler en dur!!!

                        if ($inString) 
                        {
                            if ($isCode) // il faut stopper la chaine, concaténer l'expression puis la suite de la chaine
                                $t=array(array(self::T_CHAR,'"'), array(self::T_CHAR,'.'), array(self::T_CHAR,$var), array(self::T_CHAR,'.'), array(self::T_CHAR,'"'));
                            else // il faut insérer la valeur telle quelle dans la chaine
                                $t=array(array(self::T_CHAR,addslashes($var)));
                        }
                        else
                        {
                            if ($isCode)
                                $t=array(array(self::T_CHAR,$var));
                            else
                                $t=array(array(T_CONSTANT_ENCAPSED_STRING, '\''.addslashes($var). '\'')); 
//                                $t=array(array(self::T_CHAR,$var));
                        }
                
                        array_splice($tokens, $index, 1, $t);

                        if ($isCode) $canEval=false; 
                    }
                    break;
                    
                case T_CURLY_OPEN: // une { dans une chaine . exemple : "nom: {$h}"
                    //$tokens[$index][1]=null;
                    $curly++;
                    break;
                    
                // un identifiant : appel de fonction, constante, propriété    
                case T_STRING:  // un identifiant de fonction, de constante, de propriété, etc.
                case T_ARRAY:   // array(xxx), géré comme un appel de fonction
                case T_EMPTY:   // empty(xxx), géré comme un appel de fonction
                case T_ISSET:   // isset(xxx), géré comme un appel de fonction
                
                    // Appel de fonction
                    if ($tokens[$index+1][1]==='(')
                    {
                        $canEval &= self::parseFunctionCall($tokens, $index, $varCallback, $pseudoFunctions);
                        break;
                    }

                    // Si c'est une constante définie, on peut évaluer
                    if (defined($tokens[$index][1])) break;

                    // C'est autre chose (une propriété, etc.), on ne peut pas évaluer
                    $canEval=false;
                    break;
                
                case T_DOUBLE_ARROW: // seulement si on est dans un array()
                case T_NEW: // autorisé, pour permettre des new TextTable() et autres... mais c'est dommage
                    $canEval=false;
                    break;

                // Réécriture des chaines à guillemets doubles en chaines simples si elle ne contiennent plus de variables
                case T_CONSTANT_ENCAPSED_STRING:
//                    $tokens[$index][1]=var_export(substr($token[1], 1, -1),true);
                    $tokens[$index][1]=var_export(self::evalExpression($token[1]), true);
                    break;
                    
                // Autres tokens autorisés, mais sur lesquels on ne fait rien
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
                case T_AND_EQUAL:       // tous les opérateurs d'assignation (.=, &=, ...)
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

                case T_OPEN_TAG:        // début et fin de blocs php
                case T_OPEN_TAG_WITH_ECHO:
                case T_CLOSE_TAG:

                case T_START_HEREDOC:   // syntaxe heredoc >>> 
                case T_END_HEREDOC:

                case T_CHARACTER:
                case T_BAD_CHARACTER:

                case T_CLONE:
                case T_CONST:
                case T_DOLLAR_OPEN_CURLY_BRACES:

                case T_FILE:        // à gérer : __FILE__
                case T_LINE:        // __LINE__    
                case T_FUNC_C:
                case T_CLASS_C:

                case T_GLOBAL:
                case T_INLINE_HTML:        

                case T_STRING_VARNAME:         
                case T_USE :

             // case T_COMMENT: // inutile : enlevé durant la tokenisation
             // case T_DOC_COMMENT: // idem
             // case T_ML_COMMENT: php 4 only 
             // case T_OLD_FUNCTION: php 4 only
             // case T_PAAMAYIM_NEKUDOTAYIM: 
                
//                default:
                    throw new Exception('Interdit dans une expression : "'. $token[1]. '" ('.token_name($token[0]));
                    
                // Tokens inconnus ou non gérés
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

        if ($canEval)
            $expression=Utils::varExport(self::evalExpression($expression),true);
        elseif ($addCurly)
            $expression='{'.$expression.'}';
            
        return $canEval;
    }

    /**
     * Ananlyse et exécute un appel de fonction présent dans l'expression
     */
    private static function parseFunctionCall(& $tokens, $index, $varCallback, $pseudoFunctions)
    {
        $function=$tokens[$index][1];
        
        // Fonctions qui peuvent être appellées lors de la compilation
        static $compileTimeFunctions=null;

        // Fonctions autorisées mais qui ne doivent être appellées que lors de l'exécution du template
        static $runtimeFunctions=null;

        if (is_null($compileTimeFunctions))
        {
            $compileTimeFunctions=array_flip(array
            (
                'empty', 'isset',
                'trim', 'rtrim', 'ltrim',
                'substr','str_replace','str_repeat','strtr',
                'strtoupper','strtolower',
                'implode','explode',
                'strpos', 'strlen', 'gettype',
                'urlencode',
                'addslashes',
                'var_export', 'print_r', 'var_dump',
                'file_get_contents',
                'count',
                'is_null',
                'is_array',
                'array_sum',
                'in_array',
                'date',
                'sprintf',
                'strpbrk',
                'wordwrap',
                'number_format',
                'ucfirst',
                'htmlspecialchars',
                'htmlentities',
                'nl2br',
                'array_flip',
                'array_intersect_key',
                'array_diff_key',
                'array_map',
                'iterator_to_array',
                'chr',
                'crc32',
                'md5',
                'dechex',
                'utf8_encode',
                'get_class',
                'max',
                'filemtime',
                'filesize',
                'filectime',
                'basename',
                'is_dir',
                'is_file',
                'is_link',
                'dirname',
                'array_reverse',
                'strip_tags'
            ));
            $runtimeFunctions=array_flip(array
            (
                'array',
                'range',
                'extension_loaded',
                'array_keys',
                'class_exists',
                'time',
                'strftime'
            ));
        }

        // Détermine si cette fonction est autorisée et ce à quoi on a affaire
        $handler=strtolower($function);    // autorise les noms de fonction aussi bien en maju qu'en minu
        $canEval=true;
//        if (isset($pseudoFunctions[$handler]))

//if ($tokens[$index][1]==='TextTable')
//{
//	self::dumpTokens(array_slice($tokens, $index-2));
//} 

        // les méthodes statiques (::) ou d'objet (->) sont toutes autorisées
        if (isset($tokens[$index-1]) && ($tokens[$index-1][0]===T_DOUBLE_COLON || $tokens[$index-1][0]===T_OBJECT_OPERATOR))
        {
                $functype=2;
                $canEval=false;
        	
        }
        // les new XXX() ressemble à des fonctions, sont autorisés
        elseif ( 
                    (isset($tokens[$index-1]) && $tokens[$index-1][0]===T_NEW) 
                ||  
                    (isset($tokens[$index-2]) && $tokens[$index-1][0]===T_WHITESPACE && $tokens[$index-2][0]===T_NEW)
                )
        {
                $functype=2;
                $canEval=false;
        	
        }
        elseif ($pseudoFunctions && array_key_exists($handler, $pseudoFunctions))
        {
            if (is_null($pseudoFunctions[$handler])) // pseudo fonction autorisée mais handler==null
            {
                $functype=2;
                $canEval=false;
            }
            else
            {
                $handler=$pseudoFunctions[$handler];
                $functype=0;
            }
        }
        elseif (isset($compileTimeFunctions[$handler]))
        {
            $functype=1;
        }
        elseif (isset($runtimeFunctions[$handler]))
        {
            $functype=2;
            $canEval=false;
        }
        else
            throw new Exception($function.' : fonction inconnue ou non autorisée');
        
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
            
            // Génère le code PHP du résultat obtenu
            $result=Utils::varExport($result, true);
            
            // Remplace les tokens codant l'appel de fonction par un token unique contenant le résultat
            array_splice($tokens, $index, $i-$index+1, array(array(self::T_CHAR,$result)));
        }
        else
        {
            if ($functype===0)
                throw new Exception('Les arguments de la pseudo-fonction '.$function.' doivent être évaluables lors de la compilation');

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

	
}
?>
