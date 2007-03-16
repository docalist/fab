<?php
//{{{
//$x=65;
//}}}
//echo $x;
//function f($x)
//{
//	echo $x, ' - ';
//    return $x;
//}
//$tmp=false or $tmp=0.0 or $tmp='' or $tmp=f(false) or $tmp='last';
//
//var_dump($tmp);
//echo '<br />';
//$tmp=false || $tmp=0 || $tmp='' || $tmp='last';
//var_dump($tmp);
//
//echo '<br />';
////echo f(1) ? (f(2) ? f(3) : f(4)) : f(5);
////echo f(1) ? f(2) ? f(3) : f(4) : f(5);
//echo f(0) ?             f(2) ? f(3) : f(4)      :       f(5);
//echo $tmp=false ? $tmp : null;
//
//echo true?'true':false?'t':'f';
// 
//die();
//
?>
<style>
body
{
	background-color: #099;
    font-size: 20px;
}
code
{
	color: #A00;
    font-weight: bold;
    background-color: #eee;
}
blockquote
{
    border: 1px solid #008;
    padding: 0.2em;
    background-color: rgb(250,250,250);
    margin: 1px 2em;
    margin-bottom: 1em;
    font-size: 90%;
}
blockquote blockquote
{
    background-color: rgb(240,240,240);  
    margin-bottom: 0;
}
blockquote blockquote blockquote
{
    background-color: rgb(230,230,230);  
}
blockquote blockquote blockquote blockquote 
{
    background-color: rgb(220,220,220);  
}
blockquote blockquote blockquote blockquote blockquote 
{
    background-color: rgb(210,210,210);  
}
blockquote blockquote blockquote blockquote blockquote blockquote 
{
    background-color: rgb(200,200,200);  
}
p.exception
{
	background-color: #800;
    color: yellow;
    font-weight: bold;
    margin: 0;
    padding: 0.1em;
}
</style>

<?php

//header('content-type: text/plain');
//var_export(get_loaded_extensions());
//die();


//$ext = new ReflectionExtension('standard');
//ob_start();
//foreach ($ext->getFunctions() as $function)
//{
////    if (! $function->isInternal()) continue;
//    
//    if (false !== $doc=$function->getDocComment()) echo "\n", $doc,"\n" ;
//    echo '   function ';
//    if ($function->returnsReference()) echo ' & ';
//    echo $function->getName(), '(';
//    foreach($function->getParameters() as $index=>$parm)
//    {
//    	if ($index>0) echo ', ';
//        if ($parm->isPassedByReference()) echo '& ';
//        if ($parm->isArray()) echo 'IS_ARRAY ';
//        if ($parm->isOptional()) echo '[ ';
//        if ($class=$parm->getClass()) echo $class->getName(), ' ';
//        echo '$';
//        echo is_null($name=$parm->getName()) ? 'parm'.$index : $name; 
//        if ($parm->isDefaultValueAvailable()) echo '= ', varExport($parm->getDefaultValue(),true);
//        if ($parm->isOptional()) echo ' ]';
//    }
//    echo ');';
//    echo "\n";
//}
//$h=ob_get_clean();
//highlight_string("<?php\n" . $h . "?".">\n");
//die();





//header('content-type: text/plain');
//var_export(get_defined_functions());
//die();
// parcourir l'expression
// remplacer les variables qu'on connaît
// exécuter les pseudo fonctions
// déterminer si l'expression est "constante" ou non

define('T_CHAR', PHP_INT_MAX); // les tokens actuels de php vont de 258 à 375, peu de risque de conflit...
define('T_END', T_CHAR-1); // les tokens actuels de php vont de 258 à 375, peu de risque de conflit...
function select($xpath)
{
//	echo 'In Select, xpath=', var_export($xpath, true), "\n";
    echo '&rArr;<code>(exécution du select)</code>';
    return null;//'(sel '.$xpath.')';
//    return array(array(T_CHAR,'(sel '.$xpath.')'));
}


function varExport($var, $return = false)
{
    if (! is_array($var)) return var_export($var, $return);
    
    $t = array();
    $index=0;
    foreach ($var as $key => $value)
    {
        if ($key<>$index)
        {
            $t[] = var_export($key, true).'=>'.varExport($value, true);
            if (is_int($key)){echo 'réinit start à '.$key."\n"; $index=$key+1;}
        }
        else
        {
            $t[] = varExport($value, true);
            $index++;
        }
    }
    $code = 'array('.implode(',', $t).')';
    if ($return) return $code;
    echo $code;
}    
function highlight($h)
{
    return str_replace(array('&lt;?php&nbsp;', '?&gt;'), '', highlight_string('<?php '.$h.'?>', true));

}
//echo highlight('PHP_OS');
//die();
class ExpressionParser
{
    const PHP_START_TAG='<?php ';
    const PHP_END_TAG="?>";
    
    private $inString=false;
    private $curly=0;
    
    public function handleVariable($token, &$canEval=null)
    {
        $name=$token[1];
//        echo '-> ', $name, ', inString=', ($this->inString ? 'true' : 'false'), "\n";
        
        // on a un nom de variable. On l'instancie. Au final, soit on a sa valeur (simple chaine)
        // soit une expression php plus complexe (parexemple) $this->bindings['var']
        if (false)
        {
            $var='(contenu de la variable)';
            $exp=false;
        }
        else
        {
            $var='$this->bindings[\''.$name.'\']';
            $exp=true;
            $canEval=false;
        }
                
        if ($this->inString) 
        {
            if ($exp) // il faut stopper la chaine, concaténer l'expression puis la suite de la chaine
                return array(array(T_CHAR,'"'), array(T_CHAR,'.'), array(T_CHAR,$var), array(T_CHAR,'.'), array(T_CHAR,'"'));
            else // il faut insérer la valeur telle quelle dans la chaine
                return array(array(T_CHAR,addslashes($var)));
        }
        else
        {
        	if ($exp)
                return array(array(T_CHAR,$var));
            else
                return array(array(T_CONSTANT_ENCAPSED_STRING, '\''.addslashes($var). '\'')); 
        }
    }

    /**
     * Analyse une chaine contenant une expression php et retourne un tableau contenant
     * les tokens correspondants
     * 
     * @param string $expression l'expression à analyser
     * @return array les tokens obtenus
     */
    public function tokenize($expression)
    {
        // Utilise l'analyseur syntaxique de php pour décomposer l'expression en tokens
        $tokens = token_get_all(self::PHP_START_TAG . $expression . self::PHP_END_TAG);
        
        // Enlève le premier et le dernier token (PHP_START_TAG et PHP_END_TAG)
        array_shift($tokens);
        array_pop($tokens);
        
        // Supprime les espaces du source et crée des tokens T_CHAR pour les caractères
        foreach ($tokens as $index=>$token)
        {
            if (is_string($token)) 
                $tokens[$index]=array(T_CHAR, $token);
            elseif ($token[0]==T_WHITESPACE)
            {
                if (    isset($tokens[$index-1]) && isset($tokens[$index+1]) 
                     && (ctype_alnum(substr($tokens[$index-1][1],-1)))
                     && (ctype_alnum(substr(is_string($tokens[$index+1]) ? $tokens[$index+1] : $tokens[$index+1][1],0,1)))
                   )  
                    $tokens[$index][1]=' ';
                else                     
                    unset($tokens[$index]);
            }
            elseif ($token[0]==T_COMMENT || $token[0]==T_DOC_COMMENT)
                unset($tokens[$index]);
        }
        $tokens=array_values($tokens);
        $tokens[]=array(T_END,null);
        //$this->dumpTokens($tokens);
        
        // Retourne le tableau de tokens obtenu
        return $tokens;
    }
    
    /**
     * Affiche les tokens passés en paramètre
     */
    private function dumpTokens($tokens)
    {
    	echo '<pre>';
        foreach($tokens as $index=>$token)
        {
        	   echo gettype($token), ' => ', $index, '. ', ($token[0]==T_CHAR ? 'T_CHAR' : $token[0].'-'.token_name($token[0])), ' : [', $token[1], ']', "<br />";
        }
        var_export($tokens);
        echo '</pre>';
    }
    
    /**
     * Génère l'expression PHP correspondant au tableau de tokens passés en paramètre
     * 
     * @param string $tokens le tableau de tokens
     * @return string l'expression php correspondante
     */
    public function unTokenize($tokens)
    {
        $result='';
        foreach ($tokens as $index=>$token)
        {
            if (is_string($token))
                $result.= $token;
            else
            {
                list($type, $data) = $token;
                $result.= $data;
            }
        }        
        return $result;
    }
    
    // analyse l'expression php passée en paramètre
    // vérifie que les fonctions appellées sont autorisées
    // essaie d'optimiser l'expression
    // essaie de l'exécuter
    //
    // En sortie, expression est modifiée et contient l'expression optimisée
    // Retourne true si l'expression est évaluable (dans ce cas expression contient un varExport du résultat)
    public function parseExpression(&$expression)
    {
        echo '<blockquote>';
        if (is_array($expression))
        {
            $tokens=$expression;
            $tokens[]=array(T_END,null);
            echo '<code>', highlight($this->unTokenize($expression)), '</code>';
        }
        else
        {
            echo '<code>', highlight($expression), '</code>';
            $tokens=$this->tokenize($expression);
        }
        $this->inString=false;
        $this->curly=0;
$colon=false;
$ternary=0;
        $canEval=true;
        for ($index=0; $index<count($tokens); $index++)
        {
            $token=$tokens[$index];
            switch ($token[0])
            {          
                case T_CHAR:
                    switch ($token[1])
                    {
                        case '"':
                            $this->inString=! $this->inString;
                            break;
                        case '}':
                            if ($this->curly) $tokens[$index]=null;
                            --$this->curly;
                            break;
                        case '!': 
                            if ($index>0) 
                            {
                                switch($tokens[$index-1][0])
                                {
                                    case T_BOOLEAN_AND:
                                    case T_BOOLEAN_OR:
                                    case T_LOGICAL_AND:
                                    case T_LOGICAL_OR:
                                    case T_LOGICAL_XOR:
                                	case T_VARIABLE:
                                        break;
                                    case T_CHAR:
                                        switch($tokens[$index-1][1])
                                        {
                                            case '!':
                                            case '(':
                                            case '[':
                                                break 2;
                                        }
                                    default:
                                        $tokens[$index][1]='->';
                                }	
                            }
                            break;
                        case '=': 
                            throw new Exception('affectation interdite dans une expression, utilisez "=="');
                        case '?':
                            $ternary++;
                            break;
                        case ':':
                            if ($ternary>0)
                                $ternary--;
                            else
                            {
                                $tokens[$index][1]=') OR $tmp=(';
                                $colon=true;
                            }
                    }
                    break;                    
                case T_VARIABLE:    
                    array_splice($tokens, $index, 1, $this->handleVariable($token, $canEval));
                    break;
                case T_CURLY_OPEN: // une { dans une chaine . exemple : "nom: {$h}"
                    $tokens[$index][1]=null;
                    $this->curly++;
                    break;
                case T_ARRAY:
                case T_STRING:
                case T_NUM_STRING:
                case T_EXIT:
                case T_HALT_COMPILER:
                case T_EMPTY:
                case T_ISSET:
                    // constante
                    if (defined($tokens[$index][1])) break;

                    // appel de fonction
                    if ($tokens[$index+1][1]==='(')
                    {
                        // Liste des fonctions qu'on peut appeller sans générer d'effets de bord
                        $constFuncs=array
                        (
                            'array', 'range',
                            'empty', 'isset',
                            'trim', 'rtrim', 'ltrim',
                            'substr','str_replace','str_repeat',
                            'implode','explode',
                            'select'
                        );
                        // Fonction autorisée ?
                        if(! in_array($token[1], $constFuncs))
                        {
                            $canEval=false;
                             
                            // les méthodes statiques (::) ou d'objet (->) sont autorisées
                            if (!isset($tokens[$index-1]) || (($tokens[$index-1][0]!==T_DOUBLE_COLON) && ($tokens[$index-1][0]!==T_OBJECT_OPERATOR)))
                                throw new Exception($token[1].' : fonction inconnue ou non authorisée');
                        }
                        
                        // Extrait chacun des arguments de l'appel de fonction
                        $level=1;
                        $args=array();
                        $start=$index+2;
                        $parser=clone $this;
                        $argsCanEval=true;
                        for ($i=$start; $i<count($tokens); $i++)
                        {
                            switch ($tokens[$i][1])
                            {
                                case '(': $level++; break;
                                case ')':
                                    --$level; 
                                    if ($level===0) 
                                    {
                                        if ($i>$start)
                                        {
                                            $arg=array_slice($tokens, $start, $i-$start);
                                            $argsCanEval &= $parser->parseExpression($arg); // pas de shortcircuit avec un &=
                                            $args[]=$arg;
                                        }
                                        break 2;
                                    }
                                    break ;
                                case ',': 
                                    if ($level===1)
                                    {
                                        $arg=array_slice($tokens, $start, $i-$start);
                                        $argsCanEval &= $parser->parseExpression($arg); // pas de shortcircuit avec un &=
                                        $args[]=$arg;
                                        $start=$i+1;
                                    }
                            }
                        }
                        if ($i>=count($tokens)) throw new Exception(') attendue');

                        if (in_array($token[1], array('select', 'autoid','lastid')))
                        {
                            if ( ! $argsCanEval )
                                throw new Exception('Les arguments de la pseudo-fonction '.$token[1].' doivent être évaluables lors de la compilation');
                            array_splice($tokens, $index, $i-$index+1, array(T_CHAR,call_user_func_array('select', $args)));
                        }
                        else
                        {
                            $canEval&=$argsCanEval;
                            $t=array();
                            foreach ($args as $no=>$arg)
                            {
                                if ($no>0) $t[]=array(T_CHAR, ',');
                                $t[]=array(T_CHAR, $arg);
                            }
//                            $this->dumpTokens($tokens);
                            array_splice($tokens, $index+1+1, $i-$index+1-1-1-1, $t);
//                            $this->dumpTokens($tokens);
                        }
                        break;
                    }

                    // autre chose
                    $canEval=false;
                
                // Autres tokens autorisés
                case T_END:
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
                
                case T_DOUBLE_ARROW: // seulement si on est dans un array()
                
                case T_DOUBLE_COLON:
                case T_OBJECT_OPERATOR: 
                
                case T_ENCAPSED_AND_WHITESPACE:

                case T_INSTANCEOF:    
                    break;
                    
                case T_CONSTANT_ENCAPSED_STRING:
                    $tokens[$index][1]=var_export(substr($token[1], 1, -1),true);
                    break;
                    
                // Liste des tokens interdits dans une expression de template
                case T_AND_EQUAL:       // tous les opérateurs d'assignation (.=, &=, ...) sont interdits
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

                case T_OPEN_TAG:        // début et fin de blocs php
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
                    throw new Exception('Interdit dans une expression : "'. $token[1]. '"');
                    
                // Tokens inconnus ou non gérés
                default:
                     echo $token[0], '-', token_name($token[0]),'[',$token[1],']', "<br />";
                     $this->dumpTokens($tokens);
                
            }
        }
if ($colon)
{
	array_unshift($tokens, array(T_CHAR, '($tmp=('));
    $tokens[]=array(T_CHAR, '))?$tmp:null');
}
        $expression=$this->unTokenize($tokens);

        echo ' &rArr; <code>', highlight($expression), '</code>';

        if ($canEval)
        {
            $expression=varExport($this->evalExpression($expression),true);
            echo ' &rArr; <code>', highlight($expression), '</code>';
        }
        else
            echo ' &rArr; &otimes;';
        echo '</blockquote>';
        return $canEval;
    }

    /**
     * Evalue l'expression PHP passée en paramètre et retourne sa valeur.
     * 
     * @param string $expression l'expression PHP à évaluer
     * @return mixed la valeur obtenue
     * @throws Exception en cas d'erreur.
     */
    public function evalExpression($expression)
    {
        set_error_handler(array($this,'evalError'));
        ob_start();
        $result=eval('return '.$expression.';');
        if ('' !== $h=ob_get_clean())
            throw new Exception('Erreur dans l\'expression PHP [ ' . $expression . ' ] : ' . $h);
        restore_error_handler();
        return $result;    	
    }
    
    /** 
     * Gestionnaire d'erreurs appellé par {@link evalExpression} en cas d'erreur 
     * dans l'expression évaluée 
     */
    private function evalError($errno, $errstr, $errfile, $errline, $errcontext)
    {
        $h=substr(substr($errcontext['expression'], 7), 0, -1);
        throw new Exception('Erreur dans l\'expression PHP [ ' . $h . ' ] : ' . $errstr);
    } 
}


// PROGRAMME DE TEST
echo "<blockquote><code>Expression analysée</code> &rArr; <code>Expression optimisée</code> &rArr; <code>Résultat de l'évaluation</code> ou <code>&otimes;</code></blockquote>";

$p=new ExpressionParser();
$tests=file(dirname(__FILE__).'/expressions-to-test.php');
array_shift($tests); // vire la première ligne

foreach($tests as $expression)
{
    $expression=rtrim($expression, "\n\r");
    try
    {
        $p->parseExpression($expression);
    }
    catch (Exception $e)
    {
        echo '<p class="exception">Exception ! ', $e->getMessage(), '</p>', str_repeat('</blockquote', 10);
    }
//    die();
    try
    {
        $p->parseExpression($expression);
    }
    catch (Exception $e)
    {
        echo '<p class="exception">Exception ! ', $e->getMessage(), '</p>', str_repeat('</blockquote', 10);
    }
echo '<hr />';
}
?>

<h1>Choses non gérées pour le moment :</h1>
<li>colliers d'expressions : <code>$titoriga:$titorigm:'pas de titre'</code></li>
<li>opérateur ternaire : <code>$x ? 'true' : 'false'</code></li>
<li>accès tableau en dehors des accolades : <code>titre : $record['titoriga']</code></li>
<li>accès propriété en dehors des accolades : <code>$selection->count() réponses</code></li>
<li>liste complète des fonctions autorisées (uniquement celles qui n'ont pas d'effet de bord, ne prennent pas d'arguments byref + autres conditions)</li>
<li>accolades vides : <code>{ }</code> (peut être utile pour gérer les select)</li>
<li>autoId(), lastId()</li>
<li>DONE : point d'exclamation comme synonyme de l'opérateur d'objet : <code>$this!path</code> idem <code>$this->path</code> (xml)</li>
<li>virer le code de débogage</li>
<li>virer les fonctions tokenize/unTokenize (appellées une seule fois)</li>
<li>voir si on garde un objet (une simple fonction devrait suffire)