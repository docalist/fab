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
pre
{
    background-color: #eef;
    margin: 0;
}
fieldset
{
    padding: 0.0em;
    margin-top: 2em;
    border: 1px solid red;
}
legend
{
	background-color: yellow;
    margin-top: -1em;
}
</style>

<?php


define('debug',false);
require('core/template/Template.php');
require('core/template/TemplateCompiler.php');
require('core/config/Config.php');
require('core/utils/Utils.php');

function normalizeSpaces($string)
{
    return preg_replace(array('~\s*\n\s*~','~\s+~'), ' ', $string);
}

function test($label, $template, $expected)
{
    $xml=new domDocument();
    $xml->loadXML($template);
    TemplateCompiler::compileMatches($xml);

    $result=$xml->saveXml();
    if ((substr($expected,0,5) !== '<?xml') && (substr($result,0,5)==='<?xml'))
    {
    	$result=substr($result, strpos($result, '?>')+3);
    }
    $result=rtrim($result, "\n\r");
    $result=preg_replace('~<template[^>]*/>~', '', $result);
    $result=preg_replace('~<template[^>]*>(.*?)</template>~', '\1', $result);
    $result=normalizeSpaces($result);
    $expected=normalizeSpaces($expected);
    if ($result !== $expected)
    {
        echo '<p style="color: red; font-weight: bold;">FAILS : ', $label, '</p>';
        echo 'Template : <br />', "\n";
        echo '<pre>', htmlentities($template), '</pre>', "\n";
        echo 'Expected : <br />', "\n";
        echo '<pre>', htmlentities($expected), '</pre>', "\n";
        echo 'Result : <br />', "\n";
        echo '<pre>', htmlentities($result), '</pre>', "\n";
    }    
    else
        echo '<p style="color: green; font-weight: bold;">OK : ', $label, '</p>';
}

test
(
    "S'il n'y a pas de templates match, le source n'est pas modifié",
    $x='<html><body><h1 class="main">Titre</h1><!--fin--><?php echo "here"?></body></html>',
    $x    
);


test
(
"Un template vide supprime les éléments qu'il sélectionne",
'<html>
    <template match="test"></template>
    <test />
    <test a="a" b="b">bla bla</test>
</html>',
'<html>
</html>'
);



test
(
"Les attributs des templates deviennent des variables",
'<html>
    <template match="test" param1="essai">$param1, {$param1}</template>
    <test/>
    <test a="a" b="b">bla bla</test>
</html>',
'<html>
    essai, essai
    essai, essai
</html>'
);


test
(
"La valeur par défaut d'un attribut de template est remplacée par la valeur spécifiée par l'appellant",
'<html>
    <template match="test" param1="essai">$param1, {$param1}</template>
    <test param1="truc"/>
    <test a="a" b="b">bla bla</test>
</html>',
'<html>
    truc, truc
    essai, essai
</html>'
);


test
(
"Les attributs de l'appellant ne sont pas accessibles sous forme de variables si le template ne contient pas d'attribut du même nom",
'<html>
    <template match="test">$attr, {trim($attr)}</template>
    <test attr="truc"/>
</html>',
'<html>
    $param1, {$param1} <- bug : les acolades ont disparues!!!
</html>'
);


test
(
"select() : string(attribut) inséré dans corps du template",
'<html>
    <template match="test">{select("string(@attr)")}</template>
    <test attr="truc"/>
</html>',
'<html>
    truc
</html>'
);


test
(
"select() : string(attribut) inséré dans attribut",
'<html>
    <template match="test"><div class="red {select(\'string(@attr)\')}" /></template>
    <test attr="truc"/>
</html>',
'<html>
    <div class="red truc"/>
</html>'
);

test
(
"select() : AttrNode inséré dans attribut",
'<html>
    <template match="test"><div class="red {select(\'@attr\')}" /></template>
    <test attr="truc"/>
</html>',
'<html>
    <div class="red truc"/>
</html>'
);

test
(
"select() : AttrNode inséré dans corps du template (ajout de l'attribut au parent)",
'<html>
    <template match="test"><div>{select(\'@attr\')}</div></template>
    <test attr="truc"/>
</html>',
'<html>
    <div attr="truc"></div>
</html>'
);

test
(
"select() : @* inséré dans corps du template (ajout de tous les attributs au parent)",
'<html>
    <template match="test"><div>{select(\'@*\')}</div></template>
    <test attr1="un" attr2="deux" attr3="trois"/>
</html>',
'<html>
    <div attr1="un" attr2="deux" attr3="trois"></div>
</html>'
);


test
(
"select() : idem, mais les paramètres du template sont ignorés",
'<html>
    <template match="test" attr1="default" attr3="default"><div>{select(\'@*\')}</div></template>
    <test attr1="un" attr2="deux" attr3="trois"/>
</html>',
'<html>
    <div attr2="deux"></div>
</html>'
);

test
(
"select() : idem, mais les paramètres qui existent déjà dans le parent ne sont pas écrasés",
'<html>
    <template match="test" attr3="default"><div attr2="hi">{select(\'@*\')}</div></template>
    <test attr1="un" attr2="deux" attr3="trois"/>
</html>',
'<html>
    <div attr2="hi" attr1="un"></div>
</html>'
);

die();
//echo '<textarea style="width: 100%; height: 100%;">';
echo Template::runSource
(
'
<template match="//test" param1="defaultparam1">
    <result aa="{select(\'@a\')}">
        Insertion dans du texte :
            span=[{select("span")}]
            text=[{select("text()")}]
            comment=[{select("comment()")}]
            pi=[{select("processing-instruction()")}]
            attrs=[{select("@*")}]

        Insertion dans un commentaire :
        <!--
            span=[{select("span")}]
            text=[{select("text()")}]
            comment=[{select("comment()")}]
            pi=[{select("processing-instruction()")}]
            attrs=[{select("@*")}]
        -->

        Insertion dans une PI :
        <?xxx
            span=[{select("span")}]
            text=[{select("text()")}]
            comment=[{select("comment()")}]
            pi=[{select("processing-instruction()")}]
            attrs=[{select("@*")}]
        ?>
/*
        Insertion dans une cdata :
        <![CDATA[
            span=[{select("span")}]
            text=[{select("text()")}]
            comment=[{select("comment()")}]
            pi=[{select("processing-instruction()")}]
            attrs=[{select("@*")}]
        ]]>*/
    </result>
</template>
<test a="A" b="B" c="{$titre}" param1="newparam1">
    <span class="tit">$titre</span>
    <!-- comment -->
    <![CDATA[<<<!!>>>]]>
    <?php pi() ?>
</test>
',

    array
    (
        'titre'=>'test',
        'nom'=>'Daniel',
    )
);
die();


echo Template::runSource
(
'
<template match="//a">
    href=$href
    <aa href="$href" target="$target" title="{$title:\'lien externe\'}" oo="aa{select(\'@other\')}bb">
        cc{select("node()|@*")}dd
    </aa>
</template>

<html>
    <head>
        <title test="trim($titre) !== \'\'">$titre</title>
    </head>
    <body>
<!-- comment -->
<![CDATA[
        <<<!!>>>
]]>
<?php pi() ?>
        Bonjour $nom
        <a href="http://bdsp" target="_parent" title="bdsp" class="external" other="{$titre}"><span class="tit">$titre</span></a>
    </body>
</html>
',

    array
    (
        'titre'=>'test',
        'nom'=>'Daniel',
    )
);
echo '</textarea>';
die();

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
//        TemplateCompiler::parseExpression($expression, 'handleVariable');
        TemplateCompiler::parseCode('début{'.$expression.'}fin', 'handleVariable');
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