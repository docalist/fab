<?php
header('content-type: text/html; charset=ISO-8859-1');
?>
<style>
pre
{
    background-color: #eef;
    margin: 0;
}
a
{
	text-decoration: none;
}
</style>
<script>
function toggle($n)
{
    if ( elt = document.getElementById('test'+$n) )
        elt.style.display = elt.style.display=='none' ? 'block' : 'none';
}
</script>
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
function highlight($h)
{
    echo '<textarea name="code" class="xml:nocontrols" rows="15" cols="100">', htmlentities($h), '</textarea>';
	
}
function test($label, $template, $expected)
{
    static $n=0;
    $n++;
    $xml=new domDocument();

    $template=TemplateCompiler::addCodePosition($template);
    $xml->loadXML($template);
    TemplateCompiler::compileMatches($xml);

    TemplateCompiler::removeCodePosition($xml);
    TemplateCompiler::removeCodePosition($template);
    $result=$xml->saveXml();
    if ((substr($expected,0,5) !== '<?xml') && (substr($result,0,5)==='<?xml'))
    {
    	$result=substr($result, strpos($result, '?>')+3);
    }
    $result=rtrim($result, "\n\r");
    $result=preg_replace('~<template[^>]*/>~', '', $result);
    $result=preg_replace('~<template[^>]*>(.*?)</template>~s', '\1', $result);
    $resultori=$result;
    $result=normalizeSpaces($result);
    $expected=normalizeSpaces($expected);
    if ($result !== $expected)
    {
        echo '<li><a style="color: red; font-weight: bold;" href="javascript:toggle('.$n.')">', $label, ' : ECHEC</a></li>';
        echo "<blockquote id='test$n' style='display: block'>";
        echo 'Template : <br />', "\n";
        highlight($template);
        echo 'Expected : <br />', "\n";
        highlight($expected);
        echo 'Result : <br />', "\n";
        highlight($result);
        echo '</blockquote>';
    }    
    else
    {
        echo '<li><a style="color: green; font-weight: bold;" href="javascript:toggle('.$n.')">', $label, ' : OK</a></li>';
        echo "<blockquote id='test$n' style='display: none'>";
        echo 'Template : <br />', "\n";
        highlight($template);
        echo 'Result : <br />', "\n";
        highlight($resultori);
        echo '</blockquote>';
    }
}
echo '<pre>';
test
(
"Dans un template, les attributs sont accessibles sous forme de variables",
'<html>
    <template match="test" param1="essai">$param1, {$param1}, {rtrim(ltrim(str_repeat($param1,2)))}</template>
    <test/>
    <test a="a" b="b">bla bla</test>
    $x
$y
{trim("a")}
</html>',
'<html>
    essai, essai, essaiessai
    essai, essai, essaiessai
    $x
$y
</html>'
);

/*----*/
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
"Dans un template, les attributs sont accessibles sous forme de variables",
'<html>
    <template match="test" param1="essai">$param1, {$param1}, {rtrim(ltrim(str_repeat($param1,2)))}</template>
    <test/>
    <test a="a" b="b">bla bla</test>
</html>',
'<html>
    essai, essai, essaiessai
    essai, essai, essaiessai
</html>'
);


test
(
"La valeur par défaut d'un attribut de template est remplacée par la valeur éventuelle spécifiée par l'appellant",
'<html>
    <template match="test" param1="essai">$param1, {$param1}, {rtrim(ltrim(str_repeat($param1,2)))}</template>
    <test param1="truc"/>
    <test a="a" b="b">bla bla</test>
</html>',
'<html>
    truc, truc, tructruc
    essai, essai, essaiessai
</html>'
);


test
(
"Les attributs de l'appellant ne sont accessibles sous forme de variables que si le template contient un attribut du même nom",
'<html>
    <template match="test">$attr, {trim($attr)}</template>
    <test attr="truc"/>
</html>',
'<html>
    $attr, {trim($attr)}
</html>'
);


test
(
"select('.') récupère la totalité du noeud appellant sans rien changer",
'<html>
    <template match="test">{select(".")}</template>
    <test attr="essai" class="red" id="t1">
        simple text
        <div class="yellow">
            <span>hello</span>
            <!--comment-->
            <![CDATA[section <&> &amp; cdata]]>
            <?php echo time();?>
        </div>
    </test>
</html>',
'<html>
    <test attr="essai" class="red" id="t1">
        simple text
        <div class="yellow">
            <span>hello</span>
            <!--comment-->
            <![CDATA[section <&> &amp; cdata]]>
            <?php echo time();?>
        </div>
    </test>
</html>'
);


test
(
"select('text()') récupère les noeuds de type texte et cdata qui sont des fils directs de l'appellant",
'<html>
    <template match="test">{select("text()")}</template>
    <test attr="essai" class="red" id="t1">
        simple text
        <div class="yellow">
            <span>hello</span>
            another text
            <!--comment-->
            <![CDATA[une <&> &amp; cdata]]>
            <?php echo time();?>
        </div>
        <![CDATA[une autre <&> &amp; cdata]]>
        last text
    </test>
</html>',
'<html>
        simple text
        <![CDATA[une autre <&> &amp; cdata]]>
        last text
</html>'
);

test
(
"select('//text()') récupère en ordre hiérarchique tous les noeuds texte ou cdata de l'appellant",
'<html>
    <template match="test">{select("//text()")}</template>
    <test attr="essai" class="red" id="t1">
        simple text
        <div class="yellow">
            <span>hello</span>
            another text
            <!--comment-->
            <![CDATA[une <&> &amp; cdata]]>
            <?php echo time();?>
        </div>
        <![CDATA[une autre <&> &amp; cdata]]>
        last text
    </test>
</html>',
'<html>
        simple text
        <![CDATA[une autre <&> &amp; cdata]]>
        last text
            another text
            <![CDATA[une <&> &amp; cdata]]>
                hello
</html>'
);

test
(
"select('string()') récupère le texte du noeud dans l'ordre attendu et en convertissant éventuellement les caractères spéciaux de la cdata",
'<html>
    <template match="test">{select("string()")}</template>
    <test attr="essai" class="red" id="t1">
        simple text
        <div class="yellow">
            <span>hello</span>
            another text
            <!--comment-->
            <![CDATA[une <&> &amp; cdata]]>
            <?php echo time();?>
        </div>
        <![CDATA[une autre <&> &amp; cdata]]>
        last text
    </test>
</html>',
'<html>
        simple text 
                hello 
            another text 
            une &lt;&amp;&gt; &amp;amp; cdata
            une autre &lt;&amp;&gt; &amp;amp; cdata
        last text
</html>'
);

test
(
"select('//comment()') récupère les commentaires en ordre hiérarchique",
'<html>
    <template match="test">{select("//comment()")}</template>
    <test attr="essai" class="red" id="t1">
        <!--comment 1-->
        <div class="yellow">
            <span>hello</span>
            another text
            <!--comment 2-->
            <?php echo time();?>
        </div>
        <!--comment 3-->
        last text
    </test>
</html>',
'<html>
    <!--comment 1--><!--comment 3--><!--comment 2-->
</html>'
);

test
(
"select('//processing-instruction()') récupère les PI en ordre hiérarchique",
'<html>
    <template match="test">{select("//processing-instruction()")}</template>
    <test attr="essai" class="red" id="t1">
        <?php un ?>
        <div class="yellow">
            <span>hello</span>
            <?xsl deux ?>
        </div>
        <?php trois ?>
    </test>
</html>',
'<html>
    <?php un ?><?php trois ?><?xsl deux ?>
</html>'
);

test
(
"select('//processing-instruction(\"xsl\")') ne récupère que les PI du type indiqué",
'<html>
    <template match="test">{select("//processing-instruction(\'xsl\')")}</template>
    <test attr="essai" class="red" id="t1">
        <?php un ?>
        <div class="yellow">
            <span>hello</span>
            <?xsl deux ?>
        </div>
        <?php trois ?>
    </test>
</html>',
'<html>
    <?xsl deux ?>
</html>'
);



test
(
"select(string('@attr')) dans le corps du template récupère la valeur de l'attribut",
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
"select('@attr') dans le corps du template ajoute l'attribut au noeud contenant l'appel à select",
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
"select('@*') dans le corps du template ajoute tous les attributs de l'appellant au noeud contenant l'appel à select",
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
"select('@attr') ne fait rien si le noeud contenant l'appel à select a déjà un attribut portant ce nom",
'<html>
    <template match="test"><div attr1="wontchange">{select(\'@attr1\')}</div></template>
    <test/>
</html>',
'<html>
    <div attr1="wontchange"></div>
</html>'
);

test
(
"select('@*') ne récupère que les attributs qui n'existent pas déjà dans le noeud contenant l'appel à select",
'<html>
    <template match="test"><div attr1="wontchange">{select(\'@*\')}</div></template>
    <test attr1="new" attr2="new" attr3="new"/>
</html>',
'<html>
    <div attr1="wontchange" attr2="new" attr3="new"></div>
</html>'
);

test
(
"select('@attr') ne fait rien si le template a un paramètre portant ce nom",
'<html>
    <template match="test" attr1="default"><div>{select(\'@attr1\')}</div></template>
    <test/>
</html>',
'<html>
    <div></div>
</html>'
);

test
(
"select('@*') ne récupère pas les attributs qui sont des paramètres du template",
'<html>
    <template match="test" attr1="default" attr2="default"><div>{select(\'@*\')}</div></template>
    <test attr1="new" attr2="new" attr3="new"/>
</html>',
'<html>
    <div attr3="new"></div>
</html>'
);

test
(
"select('@*') (combinaison des précédents) ne récupère que les attributs qui n'existent pas déjà et qui ne sont pas des paramètres du template",
'<html>
    <template match="test" attr1="default" attr2="default"><div attr3="old">{select(\'@*\')}</div></template>
    <test attr1="new" attr2="new" attr3="new" attr4="new" />
</html>',
'<html>
    <div attr3="old" attr4="new"></div>
</html>'
);

test
(
"select('@*') fonctionne correctement même s'il est appellé plusieurs fois au sein du même noeud",
'<html>
    <template match="test" attr1="default" attr2="default">
        <div attr3="old">{select(\'@*\')}{select(\'@*\')}</div>
        <div attr3="old">{select(\'@*\'):select(\'@*\')}</div>
        <span attr3="old">{select(\'@*\')}</span>
        <span attr3="old">{select(\'@*\')}</span>
    </template>
    <test attr1="new" attr2="new" attr3="new" attr4="new" />
</html>',
'<html>
        <div attr3="old" attr4="new"></div>
        <div attr3="old" attr4="new"></div>
        <span attr3="old" attr4="new"></span>
        <span attr3="old" attr4="new"></span>
</html>'
);


test
(
"dans un attribut, select('string(@attr)') insère la valeur de l'attribut",
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
"dans un attribut, select('@attr') fait la même chose que select('string(@attr)') : insère la valeur de l'attribut",
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
"dans un attribut, select('node()') insère la valeur textuelle du noeud",
'<html>
    <template match="test"><div attr="{select(\'node()\')}"/></template>
    <test>
        simple text
        <div class="yellow">
            <span>hello</span>
            another text
            <!--comment-->
            <![CDATA[une <&> &amp; cdata]]>
            <?php echo time();?>
        </div>
        <![CDATA[une autre <&> &amp; cdata]]>
        last text
    </test>
</html>',
'<html>
<div attr="&#10;
        simple text&#10;
        &#10;
                hello&#10;
            another text&#10;
            &#10;
            une &lt;&amp;&gt; &amp;amp; cdata&#10;
            &#10;
            &#10;
        une autre &lt;&amp;&gt; &amp;amp; cdata&#10;
        last text&#10; "/>
</html>'
);

test
(
"dans un attribut, select('//text()') insère aussi la valeur textuelle du noeud, mais en ordre hiérachique",
'<html>
    <template match="test"><div attr="{select(\'//text()\')}"/></template>
    <test>
        simple text
        <div class="yellow">
            <span>hello</span>
            another text
            <!--comment-->
            <![CDATA[une <&> &amp; cdata]]>
            <?php echo time();?>
        </div>
        <![CDATA[une autre <&> &amp; cdata]]>
        last text
    </test>
</html>',
'<html>
    <div attr="&#10;
        &#10;
        &#10;&#10;
        simple text&#10;
        &#10;
        une autre &lt;&amp;&gt; &amp;amp; cdata&#10;
        last text&#10;
        &#10;
        &#10;
        another text&#10;
            &#10;
            une &lt;&amp;&gt; &amp;amp; cdata&#10;
                &#10;
                hello"/>
</html>'
);

test
(
"dans un attribut, select('//comment()') récupère les commentaires en les concaténant",
'<html>
    <template match="test"><div attr="{select(\'//comment()\')}"/></template>
    <test>
        <!--comment 1-->
        <div class="yellow">
            <!--comment 2-->
        </div>
        <!--comment 3-->
    </test>
</html>',
'<html>
<div attr="comment 1comment 3comment 2"/>
</html>'
);

test
(
"dans un attribut, select('//processing-instruction()') récupère les PI en les concaténant",
'<html>
    <template match="test"><div attr="{select(\'//processing-instruction()\')}"/></template>
    
    <test>
        <?php echo un;?>
        <div class="yellow">
            <?php echo deux;?>
        </div>
        <?php echo trois;?>
    </test>
</html>',
'<html>
<div attr="echo un;echo trois;echo deux;"/>
</html>'
);

test
(
"select() peut accéder à n'importe quel noeud du document, pas uniquement le noeud appellant",
'<html>
    <template match="test"><h1>{select(\'/html/head/title/text()\')}</h1></template>
    <head>
        <title>Titre de la page</title>
    </head>
    <test />
</html>',
'<html>
    <head>
        <title>Titre de la page</title>
    </head>
    <h1>Titre de la page</h1>
</html>'
);

test
(
"select() peut ajouter de l'information à un noeud",
'<html>
    <template match="/html/head">
        <head>
            {select(\'*\')}
            <link rel="copyright" href="copyright.html"/>
            <link rel="glossary" href="glossary.html"/>
            <link rel="Start" href="home.html"/>
            
            <link rel="author" href="mailto:contact@example.org"/>
            <link rel="help" href="help.html"/>
        </head>
    </template>
    <head>
        <title>Titre de la page</title>
    </head>
</html>',
'<html>
    <head>
        <title>Titre de la page</title>
        <link rel="copyright" href="copyright.html"/>
        <link rel="glossary" href="glossary.html"/>
        <link rel="Start" href="home.html"/>
        
        <link rel="author" href="mailto:contact@example.org"/>
        <link rel="help" href="help.html"/>
    </head>
</html>'
);
?>

<link type="text/css" rel="stylesheet" href="web/dp.SyntaxHighlighter/Styles/SyntaxHighlighter.css"></link>  
  
<!-- the following code should be place at the bottom of the page -->  
<script language="javascript" src="web/dp.SyntaxHighlighter/Scripts/shCore.js"></script>  
<script language="javascript" src="web/dp.SyntaxHighlighter/Scripts/shBrushXml.js"></script>  
<script language="javascript">  
    dp.SyntaxHighlighter.HighlightAll('code');  
</script>