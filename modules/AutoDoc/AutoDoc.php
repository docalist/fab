<?php
class AutoDoc extends Module
{
    /**
     * Affiche la documentation interne d'une classe ou d'un module
     * 
     * Les flags passés en paramètre permettent de 
     *
     * @param bool $class le nom de la classe pour laquelle il faut afficher
     * la documentation
     * 
     * @param bool $inherited <code>true</code> pour afficher les propriétés 
     * et les méthodes héritées, <code>false</code> sinon
     * 
     * @param bool $private  <code>true</code> pour afficher les propriétés et 
     * les méthodes privées, <code>false</code> sinon
     * 
     * @param bool $protected <code>true</code> pour afficher les propriétés et 
     * les méthodes protégées, <code>false</code> sinon
     * 
     * @param bool $public <code>true</code> pour afficher les propriétés et 
     * les méthodes publiques, <code>false</code> sinon.
     * 
     * Remarque : la documentation des actions est affichée même si 
     * <code>$public=false</code>, bien qu'il s'agisse de méthodes publiques.
     * 
     * Cela permet, en mettant tous les flags à false, de n'afficher que la 
     * documentation des actions. 
     */
    public function actionIndex($class, $inherited=true, $private=true, $protected=true, $public=true)
    {
        // Vérifie que la classe demandée existe
        if (!class_exists($class, true))
        {
            // Essaie de charger la classe comme module
            Module::loadModule($class);        
            if (!class_exists($class, true))
                throw new Exception('Impossible de trouver la classe '.$class);
        }
        
        // Stocke dans la config les flags de visibilité passés en paramètre
        foreach(array('inherited', 'private', 'protected', 'public') as $flag)
            Config::set('show.'.$flag, $this->request->bool($flag)->defaults(true)->ok());

        $class=new ClassDoc(new ReflectionClass($class));

        Template::Run
        (
            'classdoc.html', 
            array('class'=>$class)
        );
    }

    
    private function printConfig($config)
    {
        if (is_null($config)) return;
        if (is_scalar($config))
            echo $config;
        else
        {
            echo '<ul>';
            foreach($config as $key=>$config)
            {
                 echo '<li><strong>', $key, '</strong> : ';
                 $this->printConfig($config);
                 echo '</li>';
            }
            echo '</ul>';
        }
    }

    private function formatDoc($doc)
    {
        echo '<pre>',htmlentities($doc),'<br />';
        
        // Eclate la documentation en lignes
        $lines=explode("\n", $doc);
        
        // Ignore la première ('/**') et la dernière ('*/') ligne
        $lines=array_slice($lines, 1, count($lines)-2);
        
        // Ajoute une ligne vide à la fin
        $lines[]='';
        
        // Supprime les espaces et les '*' de début et de fin, reconstruit les paragraphes
        $lines=$this->getParas($lines);        
        
        
        print_r($lines);
        
        $doc=new DocBlock();

        $i=0;
        
        // Description courte et description longue
        $first=true;
        for( ; $i<count($lines); $i++)
        {
            $line=$lines[$i];
            if ($line[0]==='@') break;
            if ($first)
            {
                $doc->shortDescription=$line;
                $first=false;
            }
            else
            {
                $doc->longDescription.="<p>$line</p>\n";
            }
        }
        
        // Annotations
        while($i<count($lines))
        {
            $line=$lines[$i];
            $i++;
            if ($line[0] !== '@') die('pb');
            $line=substr($line,1);

            $tag=strtok($line, " \t");
            echo 'TAG=', $tag, ', line=', $line, '<br />';
            $tagDoc=new DocBlock();
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
            
            $tagDoc->shortDescription=strtok('¤'); // tout ce qui reste
            
            for( ; $i<count($lines); $i++)
            {
                $line=$lines[$i];
                if ($line[0]==='@') break;
                $tagDoc->longDescription.="<p>$line</p>\n";
            }
            
            if (! isset($doc->annotations[$tag]))
                $doc->annotations[$tag]=$tagDoc;
            elseif(is_array($doc->annotations[$tag]))
                $doc->annotations[$tag][]=$tagDoc;
            else
                $doc->annotations[$tag]=array($doc->annotations[$tag],$tagDoc);
        }
    echo htmlspecialchars(print_r($doc,true), ENT_NOQUOTES);        
    }
    
    private function getRoutes($module, $action)
    {
        $t=array();
        foreach(Config::get('routes') as $route)
        {
            if (! isset($route['args'])) continue;
            
            if (isset($route['args']['module']) && strcasecmp($route['args']['module'],$module)!==0) continue;
            if (isset($route['args']['action']) && strcasecmp($route['args']['action'],$action)!==0) continue;
            $t[]=$route['url'];
        }
        return $t;
    }
    
    
}

class ElementDoc
{
    public $name;
    public $summary;
    public $description;
    
    public function _construct($name, DocItem $doc=null)
    {
        $this->name=$name;
        $this->summary=is_null($doc) ? '' : $doc->shortDescription;
        $this->description=is_null($doc) ? '' : $doc->longDescription;   
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
}

class ClassDoc extends ElementDoc
{
    public $ancestors;
    public $constants;
    public $properties;
    public $methods;
    private $isModule=false;
    
    public function __construct(ReflectionClass $class)
    {
        $doc=new DocBlock($class->getDocComment());
        parent::_construct($class->getName(), $doc);
        
        // Ancêtres
        $parent=$class->getParentClass();
        while ($parent)
        {
            $name=$parent->getName();
            if ($name==='Module') $this->isModule=true;
            $this->ancestors[]=$name;
            $parent=$parent->getParentClass();
        }
        
        // Constantes
//        foreach($class->getConstants() as $constant)
//            $this->constants[$constant->getName()]=new ConstantDoc($method);
        
        // Propriétés
        foreach($class->getProperties() as $property)
        {
            $group=$this->getGroup($property, $class);
            if (! is_null($group))
                $this->properties[$group][$property->getName()]=new propertyDoc($property);
        }
        if($this->properties)
        {
            ksort($this->properties);
            foreach($this->properties as & $group)
                ksort($group);
            unset($group);
        }
                
        // Méthodes
        foreach($class->getMethods() as $method)
        {
            $group=$this->getGroup($method, $class);
            if (! is_null($group))
                $this->methods[$group][$method->getName()]=new MethodDoc($method);
        }
        if ($this->methods)
        {
            ksort($this->methods);
            foreach($this->methods as & $group)
                ksort($group);
            unset($group);
        }
                    
//        echo '<pre>';
//        var_export($this->methods);
//        die();
        // +signature
    }

    public function isModule()
    {
        return $this->isModule;
    }
}
class PropertyDoc extends ElementDoc
{
    public function __construct(ReflectionProperty $property)
    {
        $doc=new DocBlock($property->getDocComment());
        parent::_construct($property->getName(), $doc);
    }
    
}

class MethodDoc extends ElementDoc
{
    public $parameters;
    public $return;
    
    public function __construct(ReflectionMethod $method)
    {
        $doc=new DocBlock($method->getDocComment());
        parent::_construct($method->getName(), $doc);

//echo '<pre>';
//var_export($doc);
//echo '</pre>';

        // Paramètres
        foreach($method->getParameters() as $parameter)
        {
//            echo 'Création du param ', $parameter->getName() ;
            if (isset($doc->annotations['param']) && isset($doc->annotations['param']['$'.$parameter->getName()]))
                $paramDoc=$doc->annotations['param']['$'.$parameter->getName()];
            else
                $paramDoc=null;
            $this->parameters[$parameter->getName()]=new ParameterDoc($parameter, $paramDoc);
        }
        
        // Valeur de retour
        if (isset($doc->annotations['return']))
            $this->return=new ReturnDoc($doc->annotations['return']['']);
            
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
        
        $h.=' <span class="operator">(</span> ';
        $first=true;
        foreach($method->getParameters() as $i=>$parameter)
        {
            if (!$first) $h.='<span class="operator">,</span> ';
            $h.='<span class="type">'.$this->parameters[$parameter->getName()]->type .'</span> ';
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
        
        $h.=' <span class="operator">)</span>';
        if ($this->return)
        {
            $h.=' <span class="operator">:</span> ' 
            .'<span class="type">'
            . $this->return->type
            . '</span>';
        }    
        //$this->signature=Utils::highlight($h);
        $this->signature=$h;
    }
    
}

class ParameterDoc extends ElementDoc
{
    public $type;
    public $default;

    public function __construct(ReflectionParameter $parameter, DocItem $doc=null)
    {
        parent::_construct('$'.$parameter->getName(), $doc);
        
        // Type
        if (!is_null($doc))
        {
            $this->type=$doc->type;
        }
        else
        {
            if ($parameter->isArray())
            {
                $this->type='array';
            }
            elseif($parameter->getClass())
            {
                $class=$parameter->getClass();
                if ($class->isUserDefined())
                    $this->type='<a href="?class='.$class->getName().'">'.$class->getName().'</a>';
                else
                    $this->type=$class->getName();
            }
            else
            {
                $this->type='mixed';
            }
        }
        
        // Valeur par défaut
        if ($parameter->isDefaultValueAvailable())
            $this->default=$parameter->getDefaultValue();
        else
            $this->default=null;
    }
}

class ReturnDoc extends ElementDoc
{
    public $type;

    public function __construct(DocItem $doc)
    {
        parent::_construct('', $doc);
        
        // Type
        $this->type=$doc->type;
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
        // Eclate la documentation en lignes
        $lines=explode("\n", $doc);
        
        // Ignore la première ('/**') et la dernière ('*/') ligne
        $lines=array_slice($lines, 1, count($lines)-2);
        
        // Ajoute une ligne vide à la fin
        $lines[]='';
        
        // Supprime les espaces et les '*' de début et de fin, reconstruit les paragraphes
        $lines=$this->getParas($lines);        
        
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
                $this->longDescription.="<p>$line</p>\n";
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
            
            $tagDoc->shortDescription=strtok('¤'); // tout ce qui reste
            
            for( ; $i<count($lines); $i++)
            {
                $line=$lines[$i];
                if ($line[0]==='@') break;
                $tagDoc->longDescription.="<p>$line</p>\n";
            }
            $this->inlineTags($tagDoc->shortDescription);
            $this->inlineTags($tagDoc->longDescription);
            
            $this->annotations[$tag][$tagDoc->name]=$tagDoc;
//            if (! isset($this->annotations[$tag]))
//                $this->annotations[$tag]=$tagDoc;
//            elseif(is_array($this->annotations[$tag]))
//                $this->annotations[$tag][]=$tagDoc;
//            else
//                $this->annotations[$tag]=array($this->annotations[$tag],$tagDoc);
        }
        
    }
    public function inlineTags(& $doc)
    {
        $doc=preg_replace_callback('~\{@([a-z]+)\s(.*?)}~', array($this, 'parseInlineTag'), $doc);
        //$doc=preg_replace('~\$[a-z_0-9]+~i', '<span class="var">$0</span>', $doc);
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
                
                // Sépare le nom de l'item du texte optionnel du lien
                $link=strtok($text, " \t\n");
                $text=strtok('¤'); // tout le reste

                // Si on n'a pas de texte, prend le lien
                if ($text===false || trim($text)==='') $text=$link;
                
                // Supprime les parenthèses finales si c'est un nom de fonction
                if (substr($link,-2)==='()') 
                    $link=substr($link,0, -2);
                    
                // Supprime le dollar initial si c'est un nom de variable
                $link=ltrim($link,'$');

                // 
                if (strpos($link,'/')===false)
                    $link='#'.$link;
                    
                return '<a href="'.$link.'">'.$text.'</a>';
                break;
            default:
                echo 'INLINE TAG inconnu : ', $tag;
                var_export($match);
                echo '<hr />';    
                
        }
    }
    
    private function getParas($lines)
    {
        $result=array();
        $h='';
        $lastLen=80;
        foreach($lines as $i=>$line)
        {
            // Supprime les espaces de début jusqu'à la première étoile et tous les espaces de fin
            $line=trim($line," \t\r\n");
            
            // Supprime l'étoile de début
            $line=ltrim($line, '*');
            
            // Supprime l'espace qui suit
            if ($line !=='' && $line[0] === ' ') $line=substr($line,1);
            
            //$line=htmlentities($line);
            
            if ($line === '')
            {
                if ($h !== '')
                {
                    $result[]=$h;
                    $h='';
                }
            }
            elseif($line[0]==='@')
            {
                if ($h !== '')
                {
                    $result[]=$h;
                }
                $h=$line;
            }
            elseif(strlen($line)<$lastLen*0.9) // Ligne courte
            {
                if ($h === '')
                    $h=$line;
                else
                    $h.=' '.$line;
                $result[]=$h;
                $h='';
            }
            else
            {
                if ($h === '')
                    $h=$line;
                else
                    $h.=' '.$line;
            }
            $lastLen=strlen($line);
        }
        return $result;
    }
}
?>