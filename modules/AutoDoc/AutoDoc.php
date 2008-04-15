<?php
class AutoDoc extends Module
{
    static $errors=array();
    
    public static $flags=array('inherited'=>false, 'private'=>false, 'protected'=>false, 'public'=>true, 'errors'=>false, 'sort'=>false);
    

    /**
     * Affiche la documentation interne d'une classe ou d'un module
     * 
     * Les flags pass�s en param�tre permettent de choisir ce qui sera affich�.
     * 
     * @param string $class le nom de la classe pour laquelle il faut afficher
     * la documentation
     * 
     * @param string $filename le nom du fichier docBook � afficher
     * 
     * @param bool $inherited <code>true</code> pour afficher les propri�t�s 
     * et les m�thodes h�rit�es, <code>false</code> sinon
     * 
     * @param bool $private  <code>true</code> pour afficher les propri�t�s et 
     * les m�thodes priv�es, <code>false</code> sinon
     * 
     * @param bool $protected <code>true</code> pour afficher les propri�t�s et 
     * les m�thodes prot�g�es, <code>false</code> sinon
     * 
     * @param bool $public <code>true</code> pour afficher les propri�t�s et 
     * les m�thodes publiques, <code>false</code> sinon.
     * 
     * Remarques :
     * 
     * - La documentation des actions est affich�e m�me si 
     *   <code>$public=false</code>, bien qu'il s'agisse de m�thodes publiques.
     *   Cela permet, en mettant tous les flags � false, de n'afficher que la 
     *   documentation des actions.
     * - Normallement, uniquement l'un des deux param�tres <code>$class</code>
     *   ou <code>filename</code> doit �tre indiqu�. Si vous indiquez les deux, 
     *   <code>$class</code> est prioritaire.  
     */
    public function actionIndex($class='', $filename='', $inherited=true, $private=true, $protected=true, $public=true)
    {
        // Stocke dans la config les flags de visibilit� pass�s en param�tre
        foreach(self::$flags as $flag=>$default)
            Config::set('show.'.$flag, $this->request->bool($flag)->defaults($default)->ok());

        if ($class) return $this->phpDoc($class);
        if ($filename) return $this->docBook($filename);
            
        // exp�rimental, essaie de dresser la liste de toutes les classes existantes
        $this->includeClasses(Runtime::$fabRoot.'core');
        $this->includeClasses(Runtime::$fabRoot.'modules');
        $this->includeClasses(Runtime::$root.'modules');
        //die();
        $classes=get_declared_classes();
        $lib=Runtime::$fabRoot.'lib';
        foreach($classes as $class)
        {
            $reflex=new ReflectionClass($class);
            if ($reflex->isUserDefined())
            {
                $path=$reflex->getFileName();
                
                if (strncmp($path, $lib, strlen($lib))===0) continue;
                echo '<a href="?class='.$class.'">'.$class.' ('.$path.')'.'</a><br />';
            }
        }
        echo'<pre>';
        print_r($classes);
        echo '</pre>';
        die();
    }

    private function includeClasses($path)
    {
        $dir=new DirectoryIterator($path);
        foreach($dir as $item)
        {
            $name=$item->getFileName();
            //echo $item->getPathName(), '   (', $name,'), type=', $item->getType(), '<br />';
            
            // ignore '.', '..', '.svn/', '.settings', etc.
            if (substr($name,0,1)==='.') continue;
            
            // ignore les sous-r�pertoires 'tests/'
            if ($name=='tests') continue;
            
            if($item->isDir())
            {
                $this->includeClasses($item->getPathName());
            }
            elseif(Utils::getExtension($name)==='.php')
            {
                echo 'Fichier � inclure : ', $item->getPathName(), '<br />';
                @require_once($item->getPathName());
            }
        }
    }
    
    
    /**
     * Affiche un fichier docbook en format xml
     * 
     * @param string $filename le nom du fichier � afficher
     */
    private function docbook($filename)
    {
        $path=Runtime::$root . "doc/$filename.xml";
        if (! file_exists($path))
        {
            $path=Runtime::$fabRoot . "doc/$filename.xml";
            if (! file_exists($path))
                die('impossible de trouver le fichier '.$path);
        }
        
        $source=file_get_contents($path);
        
        $source=utf8_decode($source);
        
        if (false === $start=strpos($source, '<sect1'))
            die('Impossible de trouver &lt;sect1 dans le fichier docbook');
        
        $source=substr($source, $start);
        $source=str_replace('$', '\$', $source);
        
        Template::runSource($path, $source);
    }
    
    /**
     * Affiche la documentation interne d'une classe ou d'un module
     * 
     * @param string $class le nom de la classe pour laquelle il faut afficher
     * la documentation
     * 
     * Remarque :
     * 
     * La documentation des actions est affich�e m�me si 
     * <code>$public=false</code>, bien qu'il s'agisse de m�thodes publiques.
     * Cela permet, en mettant tous les flags � false, de n'afficher que la 
     * documentation des actions. 
     */
    private function phpDoc($class)
    {
        // V�rifie que la classe demand�e existe
        if (!class_exists($class, true))
        {
            // Essaie de charger la classe comme module
            Module::loadModule($class);        
            if (!class_exists($class, true))
                throw new Exception('Impossible de trouver la classe '.$class);
        }
        
        $class=new ClassDoc(new ReflectionClass($class));

        Template::Run
        (
            'classdoc.html', 
            array
            (
                'class'=>$class,
                'errors'=>self::$errors 
            )
        );
        return;
    }
    public function getFlags()
    {
        return self::$flags;
    }
    /**
     * Cr�e un lien vers une autre classe en propageant les options de 
     * visibilit� en cours
     *
     * @param string $class la classe dont on veut afficher la doc
     * @param string $anchor une ancre optionnelle (nom de m�thode ou de propri�t�)
     * @return string
     */
    public static function link($class, $anchor='', $label='', $cssClass='')
    {
        if($label==='') $label=$class;
        
        $link='<a href="?class='.$class;
        
        foreach(self::$flags as $flag=>$default)
        {
            $value=Config::get("show.$flag",$default);
            if ($value!==$default)
                $link.="&$flag=".var_export($value,true);
        }
        
        if ($anchor) $link.='#'.$anchor;
        $link.='"';
        
        if ($cssClass)
            $link.=' class="'.$cssClass.'"';
            
        $link.='>'.$label.'</a>';
        return $link;    
    }
    
    public static function docError($message)
    {
        self::$errors[]=$message;
    }
    
}

class ElementDoc
{
    public $name;
    public $summary;
    public $description;
    public $annotations;
    
    public function _construct($name, DocItem $doc=null)
    {
        $this->name=$name;
        $this->summary=is_null($doc) ? '' : $doc->shortDescription;
        $this->description=is_null($doc) ? '' : $doc->longDescription;
        $this->annotations=isset($doc->annotations) ? $doc->annotations : array() ;
    }

    protected function getGroup($element, ReflectionClass $class)
    {
        $group=null;
        
        if ($element->getDeclaringClass() != $class && !Config::get('show.inherited'))
            return null; 
    
        if ($element instanceof ReflectionMethod && strncmp('action', $element->getName(), 6)===0)
            return 'action';
            
        if ($element->isPrivate() && Config::get('show.private'))
            return 'private';
                            
        if ($element->isProtected() && Config::get('show.protected'))
            return 'protected';
            
        if ($element->isPublic() && Config::get('show.public'))
            return 'public';
            
        return null;
    }

    protected function docError($message, $method)
    {
        AutoDoc::docError(sprintf('<a href="#%s">%1$s</a> : %s', $method, $message));
    }
    
    protected function checkType($type, $method, $arg)
    {
        $types=Config::get('types');
        
        
        $t=explode('|', $type);
        foreach($t as & $type)
        {
            for(;;) // on boucle uniquement si on tombe sur un alias
            {
                if (!array_key_exists($type, $types))
                {
                    if (in_array($type, array(0, 1, -1, 'true', 'false'), true))
                    {
                        // ok pas vraiment un type, mais c'est pratique
                        // d'autoriser ces valeurs pour dire, par exemple
                        // qu'une fonction retourne array|false ou 0|timestamp
                    }
                    else
                    {
                        try
                        {
                            $class=new ReflectionClass($type);
                        }
                        catch(Exception $e)
                        {
                            $class=null;                    
                        }
                        if (is_null($class))
                        {
                            $this->docError(sprintf('type inconnu "%s" pour %s', $type, $arg), $method);
                        }
                        else
                        {
                            if ($class->isUserDefined())
                                $type=AutoDoc::link($class->getName());
                            else
                                $type=$class->getName();
                        }
                    }                    
                    break;
                }
                
                $typeinfo=$types[$type];
                if(isset($typeinfo['use'])) // alias. exemple : bool/boolean
                {
                    $this->docError(sprintf('utiliser "%s" plut�t que "%s" pour %s', $typeinfo['use'], $type, $arg), $method);
                    $type=$typeinfo['use'];
                }
                else
                {
                    if(isset($typeinfo['label'])) // label � utiliser pour ce type
                    {
                        $type=$typeinfo['label'];
                    }
                    
                    if(isset($typeinfo['link'])) // lien pour ce type
                    {
                        if(isset($typeinfo['title'])) // titre du lien
                            $type='<a href="'.$typeinfo['link'].'" title="'.$typeinfo['title'].'">'.$type.'</a>';
                        else
                            $type='<a href="'.$typeinfo['link'].'">'.$type.'</a>';
                    }
                    
                    break;
                }
            }        
        }
        return implode(' ou ', $t);
    }
}

class ClassDoc extends ElementDoc
{
    public $ancestors;
    public $constants;
    public $properties;
    public $methods;
    private $isModule=false;
    public $lastModified;
    
    public function __construct(ReflectionClass $class)
    {
        $doc=$class->getDocComment();
        if ($doc===false)
            $this->docError('aucune documentation pour la classe', $class->getName());

        $doc=new DocBlock($doc);
        parent::_construct($class->getName(), $doc);
        
        // Anc�tres
        $parent=$class->getParentClass();
        while ($parent)
        {
            $name=$parent->getName();
            if ($name==='Module') $this->isModule=true;
            $this->ancestors[]=$name;
            $parent=$parent->getParentClass();
        }
        
        // Constantes
        foreach($class->getConstants() as $name=>$value) // pas de r�flection pour les constantes de classes
            $this->constants[$name]=new ConstantDoc($name, $value);
        
        // Propri�t�s
        foreach($class->getProperties() as $property)
        {
            $group=$this->getGroup($property, $class);
            if (! is_null($group))
                $this->properties[$group][$property->getName()]=new propertyDoc($property);
        }
        
        if($this->properties)
        {
            if (Config::get('show.sort'))
            {
                ksort($this->properties);
                foreach($this->properties as & $group)
                    ksort($group);
                unset($group);
            }
        }
                
        // M�thodes
        foreach($class->getMethods() as $method)
        {
            $group=$this->getGroup($method, $class);
            if (! is_null($group))
                $this->methods[$group][$method->getName()]=new MethodDoc($class->getName(), $method);
        }
        if ($this->methods)
        {
            if (Config::get('show.sort'))
            {
                ksort($this->methods);
                foreach($this->methods as & $group)
                    ksort($group);
                unset($group);
            }
        }
        
        
        $this->lastModified=filemtime($class->getFileName());
    }

    public function isModule()
    {
        return $this->isModule;
    }
}

class ConstantDoc extends ElementDoc
{
    public $type=null;
    public $value=null;
    
    public function __construct($name, $value)
    {
//        $doc=$property->getDocComment();
//        if ($doc===false)
//            $this->docError('aucune documentation pour la propri�t�', $property->getName());

        $this->type=$this->checkType($this->getType($value), 'constante', $name);
        $this->value=Utils::varExport($value,true);

        $doc=new DocBlock('');
        
        parent::_construct($name, $doc);
    }
    
    private function getType($var)
    {
        $type=strtolower(gettype($var));
        switch($type)
        {
            case 'integer': return 'int';
            case 'double' : return 'float';
        }
        return $type;
    }
}
/**
 * Enter description here...
 *
 */
class PropertyDoc extends ElementDoc
{
    public function __construct(ReflectionProperty $property)
    {
        $doc=$property->getDocComment();
        if ($doc===false)
            $this->docError('aucune documentation pour la propri�t�', $property->getName());

        $doc=new DocBlock($property->getDocComment());
        parent::_construct($property->getName(), $doc);
    }
    
}

class MethodDoc extends ElementDoc
{
    public $parameters;
    public $return;
    public $inheritedFrom='';
    public $overwrites='';
    public function __construct($class, ReflectionMethod $method)
    {
        $doc=$method->getDocComment();
        if ($doc===false)
            $this->docError('aucune documentation pour la m�thode', $method->getName());

        $doc=new DocBlock($method->getDocComment());
        parent::_construct($method->getName(), $doc);

        // Supprime les annotations qu'on g�re, laisse celles qu'on ne conna�t pas
        unset($this->annotations['param']);
        unset($this->annotations['return']);
        
        // Param�tres
        if (isset($doc->annotations['param']))
            $t=$doc->annotations['param'];
        else
            $t=array();
            
        foreach($method->getParameters() as $parameter)
        {
            $name='$'.$parameter->getName();
            if (isset($doc->annotations['param']) && isset($t[$name]))
            {
                $paramDoc=$t[$name];
                unset($t[$name]);
            }
            else
            {
                $paramDoc=null;
                $this->docError(sprintf('@param manquant pour %s', $name), $this->name); 
            }
            $this->parameters[$parameter->getName()]=new ParameterDoc($method->getName(), $parameter, $paramDoc);
        }
        foreach($t as $parameter)
        {
            $this->docError(sprintf('@param pour un param�tre inexistant : %s', $parameter->name), $this->name); 
        }
        
        // Valeur de retour
        if (isset($doc->annotations['return']))
        {
            $this->return=new ReturnDoc($method->getName(), $doc->annotations['return']['']);
        }
        else
        {
            // on n'a pas de @return dans la doc. G�n�re une erreur si on aurait d� en avoir un
            $source=implode
            (
                '', 
                array_slice
                (
                    file($method->getFileName()), 
                    $method->getStartLine()-1,
                    $method->getEndline()-$method->getStartLine()+1
                )
            );
            if (preg_match('~return\b\s*[^;]+;~', $source))
                $this->docError('@return manquant', $this->name); 
        }
            
        // Construit la signature
        $h='<span class="keyword">';
        
        if ($method->isAbstract())
            $h.='abstract ';
            
        if ($method->isPublic())
            $h.='public ';
        elseif($method->isPrivate())
            $h.='private ';
        elseif($method->isProtected())
            $h.='protected ';
            
        if ($method->isFinal())
            $h.='final ';
            
        if ($method->isStatic())
            $h.='static ';
            
        $h.='function ';
        $h.="</span>";
        
        $h.= '<span class="element">'.$method->getName().'</span>';
        
        $h.='<span class="operator">(</span>';
        $first=true;
        foreach($method->getParameters() as $i=>$parameter)
        {
            if (!$first) $h.='<span class="operator">,</span> ';
            $h.='<span class="type">'.$this->parameters[$parameter->getName()]->type .'</span> ';
            
            if ($parameter->isPassedByReference()) $h.='<span class="operator">&</span> ';
            
            $h.='<span class="var">$' . $parameter->getName() .'</span>';
            if ($parameter->isDefaultValueAvailable())
            {
                $h.='<span class="operator">=</span>'
                    .'<span class="value">'
                    .Utils::varExport($parameter->getDefaultValue(),true)
                    . '</span>';
            }
            $first=false;
        }
        
        $h.='<span class="operator">)</span>';
        if ($this->return)
        {
            $h.=' <span class="operator">:</span> ' 
            .'<span class="type">'
            . $this->return->type
            . '</span>';
        }    
        //$this->signature=Utils::highlight($h);
        $this->signature=$h;
        
        // M�thode h�rit�e ou non
        $c=$method->getDeclaringClass();
        $h=$c->getName();
        if ($h != $class)
        {
            $this->inheritedFrom=$h;
        }
        else
        {
            // c'est soit une m�thode d�clar�e dans cette classe
            // soit une m�thode h�rit�e d'une classe anc�tre qu'on surcharge
            //$this->inheritedFrom='';
            $ancestor=$c->getParentClass();
            if (false !==$ancestor && $ancestor->hasMethod($method->getName()))
            {
                $this->overwrites=$ancestor->getMethod($method->getName())->getDeclaringClass()->getName();
            }
        }
    }
}

class ParameterDoc extends ElementDoc
{
    public $type;
    public $default;

    public function __construct($method, ReflectionParameter $parameter, DocItem $doc=null)
    {
        parent::_construct('$'.$parameter->getName(), $doc);
        
        // Type du param�tre
        
        // Si on a de la doc pour ce param�tre, on prend le type indiqu� par la doc
        if (!is_null($doc))
        {
            $this->type=$doc->type;
        }
        
        // Sinon, on utilise le type r�el du param�tre
        else
        {
            if ($parameter->isArray())
            {
                $this->type='array';
            }
            elseif($class=$parameter->getClass())
            {
                $this->type=$class->getName();
            }
            else
            {
                $this->type='mixed';
            }
        }
        $this->type=$this->checkType($this->type, $method, $this->name);
        
        // Valeur par d�faut
        if ($parameter->isDefaultValueAvailable())
            $this->default=$parameter->getDefaultValue();
        else
            $this->default=null;
    }
}

class ReturnDoc extends ElementDoc
{
    public $type;

    public function __construct($method, DocItem $doc)
    {
        parent::_construct('', $doc);
        
        // Type
        $this->type=$doc->type;
        
        $this->type=$this->checkType($this->type, $method, 'return value');
        
    }
}


class DocItem
{
    public $shortDescription='';
    public $longDescription='';
}

class DocBlock extends DocItem
{
    public $name='';
    public $signature='';
    public $annotations=array();

    public function __construct($doc)
    {
        $lines=$this->getParas($doc);        
        
        $i=0;
        
        // Description courte et description longue
        $first=true;
        for( ; $i<count($lines); $i++)
        {
            $line=$lines[$i];
            if ($line[0]==='@') break;
            if ($first)
            {
                $this->shortDescription=$line;
                $first=false;
            }
            else
            {
                $this->longDescription.=$line;
            }
        }
        $this->inlineTags($this->shortDescription);
        $this->inlineTags($this->longDescription);
        
        // Annotations
        while($i<count($lines))
        {
            $line=$lines[$i];
            $i++;
            if ($line[0] !== '@') die('pb');
            $line=substr($line,1);

            $tag=strtok($line, " \t");
            
            $tagDoc=new DocItem();
            $tagDoc->name='';
            switch($tag)
            {
                case 'package':
                case 'subpackage':
                    $tagDoc->name=strtok(' ');
                    break;
                case 'param':
                    $tagDoc->type=strtok(' ');
                    $tagDoc->name=strtok(' ');
                    break;
                case 'return':
                    $tagDoc->type=strtok(' ');
                    break;
                default:
                    break;
            }
            
            $tagDoc->shortDescription=strtok('�'); // tout ce qui reste
            
            for( ; $i<count($lines); $i++)
            {
                $line=$lines[$i];
                if ($line[0]==='@') break;
                $tagDoc->longDescription.=$line;
            }
            $this->inlineTags($tagDoc->shortDescription);
            $this->inlineTags($tagDoc->longDescription);
            
            $this->annotations[$tag][$tagDoc->name]=$tagDoc;
        }
//        echo '<pre>', print_r($this,true), '</pre>';
    }
    
    private function getParas($doc)
    {
        // Eclate la documentation en lignes
        $lines=explode("\n", $doc);
        
        // Ignore la premi�re ('/**') et la derni�re ('*/') ligne
        $lines=array_slice($lines, 1, count($lines)-2);
        
        // Ajoute une ligne vide � la fin
        $lines[]='';
        
//        echo'<pre>', htmlentities(print_r($lines,true)), '</pre>';
        $result=array();
        $h='';

        foreach($lines as & $line)
        {
            // Supprime les espaces de d�but jusqu'� la premi�re �toile et tous les espaces de fin
            $line=trim($line," \t\r\n");
            
            // Supprime l'�toile de d�but
            $line=ltrim($line, '*');
            
            // Supprime l'espace qui suit
            if ($line !=='' && $line[0] === ' ') $line=substr($line, 1);
        }
        
        $doc=implode("\n", $lines);
        
        // Fait les remplacements de code et autres
        $this->replacement=array();
        $doc=$this->code($doc);
//        echo '<pre>', var_export($doc, true), '</pre>';
//        echo'<pre>', htmlentities(print_r($doc,true)), '</pre>';
        
        
        $lines=explode("\n", $doc);
        $inUl=$inLi=$inP=false;
        foreach($lines as $i=>$line)
        {
            if ($line === '' || $line[0]==='@')
            {
                if ($h !== '')
                {
                    if ($inLi)
                    {
                        $h.="</p>\n    </li>\n";
                        $inLi=false;    
                    }
                    if($inP)
                    {
                        $h.="\n</p>\n";
                        $inP=false;    
                    }
                    
                    if ($inUl)
                    {
                        $h.="</ul>\n";
                        $inUl=false;
                    }
                    
                    $result[]=$h;
                    $h='';
                }
                $h=$line;
            }
            else
            {
                if (preg_match('~^\s*[*+-]~', $line))
                {
                    $line=ltrim(substr(ltrim($line),1));
                    $line="\n    <li><p>\n        " . $line;    
                    if (!$inUl)
                    {
                        if($inP)
                        {
                            $h.="\n</p>\n";
                            $inP=false;    
                        }
                        $line="\n<ul>" . $line;    
                        $inUl=true;
                    }
                    $inLi=true;
                }
                else
                {
                    if ($h==='') 
                    {
                        if ($inUl)
                        {
                            $h.="</ul>\n";
                            $inUl=false;
                        }
                        $h.="\n<p>\n    ";
                        $inP=true;
                    }
                }
                
                $h.=' '.$line;
            }
        }

//        echo'<pre>', htmlentities(print_r($result,true)), '</pre>';
//        die();
        
        // Restaure les blocs qui ont �t� prot�g�s
        $result=str_replace(array_keys($this->replacement),array_values($this->replacement), $result);
        
        return $result;
    }
    private $admonitionType='';
    private function admonitions($doc)
    {
        // premier cas : 
        // <p>Remarque : suivi du texte d'un paragraphe</p>
        
        // Second cas : 
        //<p>Remarque :</p>
        //Suivi d'un tag <ul><li><p>...
        
        foreach(Config::get('admonitions') as $admonition)
        {
            $h=$admonition['match'];
            $this->admonitionType=$admonition['type'];
            $doc=preg_replace_callback('~<p>\s*('.$h.')(?:\s*:\s*)</p>\s*(<([a-z]+)>.*?</\3>)~ism', array($this,'admonitionCallback1'), $doc);
            $doc=preg_replace_callback('~<p>\s*('.$h.')(?:\s*:\s*)(.*?)</p>~ism', array($this,'admonitionCallback2'), $doc);
        }
        return $doc;
    }
    
    private function admonitionCallback1($match) // titre suivi d'un tag ul ou pre
    {
        // match[1] : titre
        // match[2] : texte du paragraphe
        $h='<div class="'.$this->admonitionType.'">'."\n";
        $h.='<div class="title">'.$match[1].'</div>'."\n";
        $h.=$match[2]."\n";
        $h.='</div>'."\n";
//        echo 'admonition : ', '<pre>', htmlentities(var_export($match,true)), '</pre>';
//        echo 'result : <pre>', htmlentities($h), '</pre>';
        return $h;
    }
    
    private function admonitionCallback2($match) // <p>titre : corps</p>
    {
        // match[1] : titre
        // match[2] : texte du paragraphe
        $h='<div class="'.$this->admonitionType.'">'."\n";
        $h.='<div class="title">'.$match[1].'</div>'."\n";
        $h.='<p>'.ucfirst($match[2]).'</p>'."\n";
        $h.='</div>'."\n";
//        echo 'admonition : ', '<pre>', htmlentities(var_export($match,true)), '</pre>';
//        echo 'result : <pre>', htmlentities($h), '</pre>';
        return $h;
    }
    
    
    private function code($doc)
    {
        $doc=preg_replace_callback('~<code>\s?\n(.*?)\n\s?</code>~s', array($this,'codeBlockCallback'), $doc);
        $doc=preg_replace_callback('~<code>(.*?)</code>~s', array($this,'codeInlineCallback'), $doc);
        return $doc;
    }
    
    private function codeInlineCallback($matches)
    {
        $code=$matches[1];
        //$code=Utils::highlight($code);
        $result='<code>' . $code . '</code>';
        $md5=md5($result);
        $this->replacement[$md5]=$result;
        return $md5;    
    }
    
    private function codeBlockCallback($matches)
    {
        $code=$matches[1];

        // Suppime les lignes vides et les blancs de fin
        $code=rtrim($code);
        
        // R�indente les lignes en supprimant de chaque ligne l'indentation de la premi�re
        $lines=explode("\n", $code);
        $len=strspn($lines[0], " \t");
        $indent=substr($lines[0], 0, $len);
        
        $sameindent=true;
        foreach($lines as &$line)
        {
            if (trim($line)!='' && substr($line, 0, $len)!==$indent)
            {
                $sameindent=false;
                break; 
            }
            $line=substr($line, $len);
        }

        if($sameindent)
            $code=implode("\n", $lines);
        // else l'indentation n'est pas homog�ne, on garde le code existant tel quel
            
        //$code=Utils::highlight($code);
            
        $result="\n<pre class=\"programlisting\">" . $code . "</pre>\n";
        
        $md5=md5($result);
        $this->replacement[$md5]=$result;
        return $md5;    
    }
    
    public function inlineTags(& $doc)
    {
        $doc=preg_replace_callback('~\{@([a-z]+)\s(.*?)}~', array($this, 'parseInlineTag'), $doc);
        //$doc=preg_replace('~\$[a-z_0-9]+~i', '<span class="var">$0</span>', $doc);
        $doc=$this->admonitions($doc);        
        
//        $this->replacement=array();
//        $doc=preg_replace_callback('~(\$[a-z0-9_]+)~is', array($this,'codeInlineCallback'), $doc);
//        $doc=str_replace(array_keys($this->replacement),array_values($this->replacement), $doc);
    }
    
    private function link($link, $text)
    {
        // Url absolue (http:, ftp:, etc.)
        if (preg_match('~^[a-z]{3,10}:~', $link))  // commence par un nom de protocole, une query string ou un hash (#)
            return '<a href="'.$link.'" class="external">'.$text.'</a>';
        
        // Pour un lien de la forme class::xxx, s�pare le nom de la classe du reste 
        $class='';
        if (strpos($link,'::')!==false)
            list($class,$link)=explode('::',$link,2);
            
        $link=trim($link);
            
        
        // Nom de variable (commence par un dollar)
        if (strpos($link,'$')===0)
        {
            $link=substr($link,1);
            
            if ($class)
                return AutoDoc::link($class, $link, $text, 'externalproperty');
                
            return '<a class="property" href="#'.$link.'">'.$text.'</a>';
        }
        
        // Nom de fonction
        if (substr($link,-2)==='()')
        { 
            $link=substr($link,0, -2);
            if ($class)
                return AutoDoc::link($class, $link, $text, 'externalmethod');
                
            return '<a class="method" href="#'.$link.'">'.$text.'</a>';
        }
            
        // Truc de la forme Class::xxx : une constante 
        if ($class)
        {
            return AutoDoc::link($class, $link, $text, 'externalconst');
        }
        
        // Pas de nom classe, juste un true de la forme xxx
        // �a peut �tre une autre classe ou une constante de la classe en cours... ou une erreur (pas $ devant var, pas de () apr�s fonction...)
        // On opte pour une autre classe
        return AutoDoc::link($link, '', $text, 'otherclass');
        
        // todo: pour tous les liens cr��s, v�rifier que c'est un lien valide
        // ie tester que c'est bien une propri�t�/m�thode/constante de la classe
        // en cours ou indiqu�e.
    }
    
    private function parseInlineTag($match)
    {
        // 1 le nom du tag ({@link xxx} -> 'link'
        // 2 le reste -> 'xxx'
        $tag=$match[1];
        $text=$match[2];
        
        switch($tag)
        {
            case 'link':
                $ori=$text;
                
                // S�pare le nom de l'item du texte optionnel du lien
                $link=strtok($text, " \t\n");
                $text=strtok('�'); // tout le reste

                // Si on n'a pas de texte, prend le lien
                if ($text===false || trim($text)==='') $text=$link;
                
                // Transforme l'url et cr�e le lien
                return $this->link($link,$text);
                
            default:
                echo 'tag inconnu : ', $match[0], '<br />';
                return $match[0];    
                
        }
    }
    
}
?>