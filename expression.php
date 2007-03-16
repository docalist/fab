<?php
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

//define('T_CHAR', PHP_INT_MAX); // les tokens actuels de php vont de 258 à 375, peu de risque de conflit...
//define('T_END', T_CHAR-1); // les tokens actuels de php vont de 258 à 375, peu de risque de conflit...
function select($xpath)
{
//	echo 'In Select, xpath=', var_export($xpath, true), "\n";
    echo '&rArr;<code>(exécution du select)</code>';
    return null;//'(sel '.$xpath.')';
//    return array(array(T_CHAR,'(sel '.$xpath.')'));
}


//echo highlight('PHP_OS');
//die();
class ExpressionParser
{
    const PHP_START_TAG='<?php ';
    const PHP_END_TAG="?>";
    
}

require('core/template/TemplateCompiler.php');
require('core/utils/Utils.php');

// PROGRAMME DE TEST
echo "<blockquote><code>Expression analysée</code> &rArr; <code>Expression optimisée</code> &rArr; <code>Résultat de l'évaluation</code> ou <code>&otimes;</code></blockquote>";

$tests=file(dirname(__FILE__).'/expressions-to-test.php');
array_shift($tests); // vire la première ligne

foreach($tests as $expression)
{
    $expression=rtrim($expression, "\n\r");
    if ($expression==='stop') break;
    try
    {
        TemplateCompiler::parseExpression($expression, 'handleVariable');
    }
    catch (Exception $e)
    {
        echo '<p class="exception">Exception ! ', $e->getMessage(), '</p>', str_repeat('</blockquote', 10);
    }
//    die();
//    try
//    {
//        $p->parseExpression($expression);
//    }
//    catch (Exception $e)
//    {
//        echo '<p class="exception">Exception ! ', $e->getMessage(), '</p>', str_repeat('</blockquote', 10);
//    }
echo '<hr />';
//break;
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