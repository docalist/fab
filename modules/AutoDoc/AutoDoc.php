<?php
class AutoDoc extends Module
{
    static $errors=array();
    static $class='';
    static $reflectionClass=null;
    static $reflectionMethod=null;

    public static $flags=array('inherited'=>false, 'private'=>false, 'protected'=>false, 'public'=>true, 'errors'=>false, 'sort'=>false);


    /**
     * Retourne le path exact du fichier docbook dont le nom ets passé en
     * paramètre.
     *
     * La méthode teste s'il existe un fichier docbook portant le nom
     * indiqué dans le répertoire <code>/doc</code> de l'application ou de fab
     * (elle ajoute l'extension .xml si nécessaire).
     *
     * Si c'est le cas, elle retourne le path exact du fichier docbook trouvé,
     * sinon, elle génère une exception.
     *
     * @param string $name le nom à tester.
     *
     * @returns path le path exact du fichier docbook trouvé.
     *
     * @throws Exception si le fichier indiqué n'existe pas.
     */
    public function getDocbookPath($name)
    {
        if (strcasecmp(substr($name, -4), '.xml') !== 0)
            $name.='.xml';

        $path=Utils::makePath(Runtime::$root, 'doc', $name);
        if (file_exists($path)) return $path;

        $path=Utils::makePath(Runtime::$fabRoot, 'doc', $name);
        if (file_exists($path)) return $path;

        throw new Exception("Impossible de trouver le fichier docbook $name.");
    }


    /**
     * Retourne le titre du fichier docbook dont le nom est passé en paramètre.
     *
     * La méthode retourne le contenu de la première balise
     * <code>title</code> trouvée dans les 1024 premiers caractères du
     * fichier.
     *
     * @param string $name le nom du fichier docbook.
     * @return string le titre du fichier docbook ou une chaine vide.
     */
    public function getDocbookSummary($name)
    {
        $path=$this->getDocbookPath($name);

        $data=@file_get_contents($path, false, null, 0, 1024);
        if ($data===false) return '';

        if (0===$result=preg_match( "~<title>(.*?)</title>~s", $data, $match )) return '';
        return trim(strip_tags(utf8_decode($match[1])));
    }


    /**
     * Retourne la description courte de la classe dont le nom est passé
     * en paramètre.
     *
     * @param string $name le nom de la classe recherchée.
     * @return string le description courte de la classe.
     */
    public function getClassSummary($name)
    {
        $class=$this->getReflectionClass($name);

        $doc=$class->getDocComment();
        if ($doc===false) return '';

        $doc=new DocBlock($doc);

        return trim(strip_tags($doc->shortDescription));
    }

    /**
     * Retourne un objet ReflectionClass pour la classe dont le nom est passé
     * en paramètre.
     *
     * Si la classe indiquée est un module, crée une instance de ce module et
     * le retourne dans le paramètre optionnel <code>$module</code>.
     *
     * @param string $name le nom de la classe recherchée.
     * @param Module $module le module chargé ou null si $class ne désigne pas
     * un module.
     *
     * @return ReflectionClass le description courte de la classe.
     *
     * @throws Exception si la classe demandée ne peut pas être chargée.
     */
    public function getReflectionClass($name, & $module=null)
    {
        $module=null;

        // Vérifie que la classe demandée existe
        if (!class_exists($name, true))
        {
            // Essaie de charger la classe comme module
            $module=Module::loadModule($name);

            if (!class_exists($name, true))
                throw new Exception('Impossible de trouver la classe '.$name);
        }
        elseif (is_subclass_of($name, 'Module'))
        {
            $module=Module::loadModule($name);
        }

        return new ReflectionClass($name);
    }

    public function getClassDoc($class)
    {
        $module=null;
        AutoDoc::$reflectionClass=$reflClass=$this->getReflectionClass($class, $module);
        $class=AutoDoc::$class=$reflClass->getName();

        $pseudoMethods=array();

        if ($module)
        {
            // Charge tous les modules parents de ce module pour pouvoir examiner leur config
            $parents=array($class=>$module);
            foreach(array_values(class_parents($class, true)) as $parent)
            {
                if ($parent !== 'Module')
                    $parents[$parent]=Module::loadModule($parent);
            }

            // Détermine la liste des pseudo actions de ce module
            foreach($module->config as $pseudo=>$config)
            {

                // Ne garde que le pseudo actions
                if (strpos($pseudo, 'action') !== 0) continue;
                if (! isset($config['action'])) continue;
                $method=$action=$config['action'];

                //echo '<hr />', $pseudo, ' -&gt; ', $action, '<br />';
                $inheritedFrom='';
                $overwrites='';
                $doc=null;
                foreach($parents as $name=>$parent)
                {
                    if ($doc!==null && $overwrites!=='' && $inheritedFrom !=='') break;
                    //echo 'Recherche dans ', $name, '<br />';
                    if ($inheritedFrom==='' && $name !== $class && isset($parent->config[$pseudo]))
                    {
                        //echo 'héritée de ', $name, '<br />';
                        if (Config::get('show.inherited'))
                            $inheritedFrom=$name;
                        else
                        {
                            //echo 'héritées non affichées, pseudo suivante<br />';
                            continue 2;
                        }
                    }

                    $r=new ReflectionClass($parent);
                    if ($r->hasMethod($method))
                    {
                        $r=$r->getMethod($method)->getDeclaringClass();
                        $name=$r->getName();
                        //echo 'la classe ', $name, ' contient la méthode ', $method, ', création de $doc<br />';
                        $doc=new MethodDoc($pseudo, $r->getMethod($method));
                        if ($overwrites==='')
                        {
                            //echo '1. surcharge la méthode ', $name, '::', $method, '()<br />';
                            $overwrites=$name;
                        }
                    }
                    elseif (isset($parent->config[$method]['action']))
                    {
                        if ($overwrites==='')
                        {
                            //echo 'surcharge la pseudo action ', $name, '::', $method, '()<br />';
                            $overwrites=$name;
                        }
                        $method=$parent->config[$method]['action'];
                        //echo 'nouvelle méthode recherchée : ', $method, '<br />';
                        if ($r->hasMethod($method))
                        {
                            if ($overwrites==='')
                            {
                                //echo '2. surcharge la méthode ', $name, '::', $method, '()<br />';
                                $overwrites=$name;
                            }
                            //echo 'la classe ', $name, ' contient la méthode ', $method, ', création de $doc<br />';
                            $doc=new MethodDoc($pseudo, $r->getMethod($method)->getDeclaringClass()->getMethod($method));
                        }
                    }
                }
                //$doc->name=$pseudo;
                $doc->signature=str_replace($method, $pseudo, $doc->signature);
                $doc->summary="<p>Pseudo action basée sur l'action ".self::link($overwrites, $method, $method);
                if ($overwrites !== $class) $doc->summary.= " du module $overwrites";
                $doc->summary.= '.</p>';

                //$doc->description='';

                $doc->overwrites=$overwrites;
                $doc->inheritedFrom=$inheritedFrom;
                $pseudoMethods[$pseudo]=$doc;

                //echo 'inheritedFrom=', $inheritedFrom, ', overwrites=', $overwrites, ', methode=', $doc->signature, '<br />';

            }
        }

        $doc=new ClassDoc($reflClass, $pseudoMethods);
        return $doc;
    }

    /**
     * Affiche la documentation interne d'une classe ou d'un module
     *
     * Les flags passés en paramètre permettent de choisir ce qui sera affiché.
     *
     * @param string $class le nom de la classe pour laquelle il faut afficher
     * la documentation
     *
     * @param string $filename le nom du fichier docBook à afficher
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
     * Remarques :
     *
     * - La documentation des actions est affichée même si
     *   <code>$public=false</code>, bien qu'il s'agisse de méthodes publiques.
     *   Cela permet, en mettant tous les flags à false, de n'afficher que la
     *   documentation des actions.
     * - Normallement, uniquement l'un des deux paramètres <code>$class</code>
     *   ou <code>filename</code> doit être indiqué. Si vous indiquez les deux,
     *   <code>$class</code> est prioritaire.
     *
     * @see Template
     *
     * @package fab
     * @subpackage doc
     *
     * @tutorial format.documentation
     *
     */
    public function actionIndex($class='', $filename='', $inherited=true, $private=true, $protected=true, $public=true)
    {
        // Stocke dans la config les flags de visibilité passés en paramètre
        foreach(self::$flags as $flag=>$default)
            Config::set('show.'.$flag, $this->request->bool($flag)->defaults($default)->ok());

        if ($class) return $this->phpDoc($class);
        if ($filename) return $this->docBook($filename);
        $this->docBook('index'); // ni filename ni classe affiche le fichier index.xml (soit de l'application, soit de fab)
        return;

        // expérimental, essaie de dresser la liste de toutes les classes existantes
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

    public function actionApi()
    {
        // Détermine le template à utiliser
        $template=Config::get('template');

        // En mode debug, ré-exécute systématiquement le template
        if (debug)
        {
            Template::run($template);
            return;
        }

        // En mode normal, stocke dans le cache la sortie générée par le template
        $path=dirname(__FILE__) . DIRECTORY_SEPARATOR . 'result of ' . $template;

        // Si on a une copie fraiche en cache, on l'envoie direct
        if (Cache::has($path, time()-3600))
        {
            readfile(Cache::getPath($path));
            return;
        }

        // Sinon on exécute le template et on stocke la sortie générée
        ob_start();
        Template::run($template);
        Cache::set($path, ob_get_flush());
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

            // ignore les sous-répertoires 'tests/'
            if ($name=='tests') continue;

            if($item->isDir())
            {
                $this->includeClasses($item->getPathName());
            }
            elseif(Utils::getExtension($name)==='.php')
            {
                echo 'Fichier à inclure : ', $item->getPathName(), '<br />';
                @require_once($item->getPathName());
            }
        }
    }


    /**
     * Affiche un fichier docbook en format xml
     *
     * @param string $filename le nom du fichier à afficher
     */
    private function docbook($filename)
    {
        if (strcasecmp(substr($filename, -4), '.xml') !== 0)
            $filename.='.xml';

        $path=Utils::makePath(Runtime::$root, 'doc', $filename);
        if (! file_exists($path))
        {
            $path=Utils::makePath(Runtime::$fabRoot, 'doc', $filename);
            if (! file_exists($path))
            {
                if ($filename==='index.xml')
                    $path=Utils::makePath(Runtime::$fabRoot, 'doc', 'fab.index.xml');
                else
                    die('impossible de trouver le fichier '.$path);
            }
        }

        $source=file_get_contents($path);

        $source=utf8_decode($source);

        if (false === $start=strpos($source, '<sect1'))
            die('Impossible de trouver &lt;sect1 dans le fichier docbook');

        $source=substr($source, $start);
        //$source=str_replace('$', '\$', $source);

        // on définit içi les templates match utilisés pour convertir le docbook
        // plutôt que dans la config car on ne veut pas les avoir pour les autres
        // templates susceptibles d'être exécutés par l'action index (classdoc.html, etc.)
        Config::set('templates.autoinclude.docbook_to_html', 'templates.html');
        Config::set('templates.autoinclude.docbook_toc', 'toc.html');
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
     * La documentation des actions est affichée même si
     * <code>$public=false</code>, bien qu'il s'agisse de méthodes publiques.
     * Cela permet, en mettant tous les flags à false, de n'afficher que la
     * documentation des actions.
     */
    private function phpDoc($class)
    {
        Template::Run
        (
            'classdoc.html',
            array
            (
                'class'=>$this->getClassDoc($class),
                'errors'=>self::$errors
            )
        );
    }

    public function getFlags()
    {
        return self::$flags;
    }
    /**
     * Crée un lien vers une autre classe en propageant les options de
     * visibilité en cours
     *
     * @param string $class la classe dont on veut afficher la doc
     * @param string $anchor une ancre optionnelle (nom de méthode ou de propriété)
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
                $link.="&amp;$flag=".var_export($value,true);
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
                if ($type=='$this') $type='this';
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
                    $this->docError(sprintf('utiliser "%s" plutôt que "%s" pour %s', $typeinfo['use'], $type, $arg), $method);
                    $type=$typeinfo['use'];
                }
                else
                {
                    if(isset($typeinfo['label'])) // label à utiliser pour ce type
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

    public function __construct(ReflectionClass $class, array $pseudoMethods=null)
    {
        $doc=$class->getDocComment();
        if ($doc===false)
            $this->docError('aucune documentation pour la classe', $class->getName());

        $doc=new DocBlock($doc);
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
        foreach($class->getConstants() as $name=>$value) // pas de réflection pour les constantes de classes
            $this->constants[$name]=new ConstantDoc($name, $value);

        // Propriétés
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

        // Méthodes
        foreach($class->getMethods() as $method)
        {
            $group=$this->getGroup($method, $class);
            if (! is_null($group))
                $this->methods[$group][$method->getName()]=new MethodDoc($class->getName(), $method);
        }

        // Pseudo méthodes
        if ($pseudoMethods)
        {
            foreach ($pseudoMethods as $name=>$doc)
            {
                $this->methods['action'][$name]=$doc;
            }
        }

        if ($this->methods)
        {
            ksort($this->methods);
            if (Config::get('show.sort'))
            {
                foreach($this->methods as & $group)
                    ksort($group);
                unset($group);
            }
        }

        // si la classe est créée via eval (cf Module::loadModule), le nom du fichier n'est pas valide
        if (file_exists($class->getFileName()))
            $this->lastModified=filemtime($class->getFileName());
        else
            $this->lastModified=time();
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
//            $this->docError('aucune documentation pour la propriété', $property->getName());

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
            $this->docError('aucune documentation pour la propriété', $property->getName());

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
        AutoDoc::$reflectionMethod=$method;
        $doc=$method->getDocComment();
        if ($doc===false)
            $this->docError('aucune documentation pour la méthode', $method->getName());

        $doc=new DocBlock($method->getDocComment());
        parent::_construct($method->getName(), $doc);

        // Supprime les annotations qu'on gère, laisse celles qu'on ne connaît pas
        unset($this->annotations['param']);
        unset($this->annotations['return']);

        // Paramètres
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
            $this->docError(sprintf('@param pour un paramètre inexistant : %s', $parameter->name), $this->name);
        }

        // Valeur de retour
        if (isset($doc->annotations['return']))
        {
            $this->return=new ReturnDoc($method->getName(), $doc->annotations['return']['']);
        }
        else
        {
            // on n'a pas de @return dans la doc. Génère une erreur si on aurait dû en avoir un
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
                    . htmlentities(Utils::varExport($parameter->getDefaultValue(),true))
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

        // Méthode héritée ou non
        $c=$method->getDeclaringClass();
        $h=$c->getName();
        if ($h != $class)
        {
            $this->inheritedFrom=$h;
        }
        else
        {
            // c'est soit une méthode déclarée dans cette classe
            // soit une méthode héritée d'une classe ancêtre qu'on surcharge
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

        // Type du paramètre

        // Si on a de la doc pour ce paramètre, on prend le type indiqué par la doc
        if (!is_null($doc))
        {
            $this->type=$doc->type;
        }

        // Sinon, on utilise le type réel du paramètre
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
                    $tagDoc->name=strtok(' ');
                    break;
            }

            $tagDoc->shortDescription=strtok('¤'); // tout ce qui reste

            for( ; $i<count($lines); $i++)
            {
                $line=$lines[$i];
                if ($line[0]==='@') break;
                $tagDoc->longDescription.=$line;
            }
            $this->inlineTags($tagDoc->shortDescription);
            $this->inlineTags($tagDoc->longDescription);
            //echo $tagDoc->name, '=', print_r($tagDoc,true), '<br />';
            $this->annotations[$tag][$tagDoc->name]=$tagDoc;
        }
//        echo '<pre>', print_r($this,true), '</pre>';
    }

    private function getParas($doc)
    {
        // Eclate la documentation en lignes
        $lines=explode("\n", $doc);

        // Ignore la première ('/**') et la dernière ('*/') ligne
        $lines=array_slice($lines, 1, count($lines)-2);

        // Ajoute une ligne vide à la fin
        $lines[]='';

//        echo'<pre>', htmlentities(print_r($lines,true)), '</pre>';
        $result=array();
        $h='';

        foreach($lines as & $line)
        {
            // Supprime les espaces de début jusqu'à la première étoile et tous les espaces de fin
            $line=trim($line," \t\r\n");

            // Supprime l'étoile de début
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
                        if($inP)
                        {
                            $h.="</p>";
                            $inP=false;
                        }

                        $h.="</li>\n";
                        $inLi=false;
                    }

                    if($inP)
                    {
                        $h.="</p>\n";
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
                    $line="\n    <li><p>" . $line;
                    if (!$inUl)
                    {
                        if($inP)
                        {
                            $h.="</p>\n";
                            $inP=false;
                        }
                        $line="<ul>" . $line;
                        $inUl=true;
                    }
                    else
                    {
                        if ($inLi)
                        {
                            if($inP)
                            {
                                $h.="</p>";
                                $inP=false;
                            }

                            $h.="</li>";
                            $inLi=false;
                        }

                        if($inP)
                        {
                            $h.="</p>\n";
                            $inP=false;
                        }
                    }
                    $inLi=true;
                    $inP=true;
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
                        $h.="<p>";
                        $inP=true;
                    }
                }

                $h.=' '.$line;
            }
        }

//        echo'<pre>', htmlentities(print_r($result,true)), '</pre>';
//        die();

        // Restaure les blocs qui ont été protégés
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
        $code=trim($matches[1]);
        if (substr($code, 0, 1) === '<' && substr($code, -1) === '>')
        {
            $code=htmlspecialchars(substr($code, 1, -1));
            $result='<code class="configkey"><span class="operator">&lt;</span>' . $code . '<span class="operator">&gt;</span></code>';
        }
        elseif (substr($code, 0, 1) === '$' && strpos($code, ' ') === false)
        {
            $code=htmlspecialchars($code);
            $result='<code class="var">' . $code . '</code>';
        }
        else
        {
            $code=htmlspecialchars($code);
            $result='<code>' . $code . '</code>';
        }

        $md5=md5($result);
        $this->replacement[$md5]=$result;
        return $md5;
    }

    private function codeBlockCallback($matches)
    {
        $code=$matches[1];
        $code=htmlspecialchars($code);

        // Supprime les lignes vides et les blancs de fin
        $code=rtrim($code);

        // Réindente les lignes en supprimant de chaque ligne l'indentation de la première
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
        // else l'indentation n'est pas homogène, on garde le code existant tel quel

        //$code=Utils::highlight($code);

        $result="\n<pre class=\"programlisting\">" . $code . "</pre>\n";

        $md5=md5($result);
        $this->replacement[$md5]=$result;
        return $md5;
    }

    public function inlineTags(& $doc)
    {
        $doc=preg_replace_callback('~\{@([a-z]+)(?:\s(.*?))?}~', array($this, 'parseInlineTag'), $doc);

        // le replace ci-dessus peut générer des <p><p>...</p></p> pour le tag
        // @inheritoc. On les vire ici.
        $doc=preg_replace('~<p>\s<p>~i', '<p>', $doc);
        $doc=preg_replace('~</p>\s</p>~i', '<p>', $doc);


        $doc=$this->admonitions($doc);

//        $this->replacement=array();
//        $doc=preg_replace_callback('~(\$[a-z0-9_]+)~is', array($this,'codeInlineCallback'), $doc);
//        $doc=str_replace(array_keys($this->replacement),array_values($this->replacement), $doc);
    }

    // fixme : fusionner ce link là avec AutoDoc::link
    private function link($link, $text)
    {
        // Url absolue (http:, ftp:, etc.)
        if (preg_match('~^[a-z]{3,10}:~', $link))  // commence par un nom de protocole, une query string ou un hash (#)
            return '<a href="'.$link.'" class="external">'.$text.'</a>';

        // un lien vers un module de l'application
        if ($link[0]==='/')
            return '<a href="'.Routing::linkFor($link).'">'.$text.'</a>';

        // Un lien vers un fichier xml
        if (preg_match('~[a-z0-9_]+(?:\.[a-z0-9_]+)*\.xml~i', $link))
            return '<a href="?filename='.$link.'">'.$text.'</a>';

        // Pour un lien de la forme class::xxx, sépare le nom de la classe du reste
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
        // ça peut être une autre classe ou une constante de la classe en cours... ou une erreur (pas $ devant var, pas de () après fonction...)
        // On opte pour une autre classe
        return AutoDoc::link($link, '', $text, 'otherclass');

        // todo: pour tous les liens créés, vérifier que c'est un lien valide
        // ie tester que c'est bien une propriété/méthode/constante de la classe
        // en cours ou indiquée.
    }

    private function parseInlineTag($match)
    {
        // 1 le nom du tag ({@link xxx} -> 'link'
        // 2 le reste -> 'xxx'
        $tag=$match[1];
        $text=isset($match[2]) ? $match[2] : '';

        switch($tag)
        {
            case 'link':
                // Sépare le nom de l'item du texte optionnel du lien
                $link=strtok($text, " \t\n");
                $text=strtok('¤'); // tout le reste

                // Si on n'a pas de texte, prend le lien
                if ($text===false || trim($text)==='') $text=$link;

                // Transforme l'url et crée le lien
                return $this->link($link,$text);

            case 'tutorial':
                // Sépare le nom de l'item du texte optionnel du lien
                $link=strtok($text, " \t\n");
                $text=strtok('¤'); // tout le reste

                // Si on n'a pas de texte, prend le lien
                if ($text===false || trim($text)==='') $text=$link;

                // Transforme l'url et crée le lien
                return $this->link($link,$text);

            case 'inheritdoc':
                $parent=AutoDoc::$reflectionClass->getParentClass();
                if (is_null($parent)) return '';

                $name=AutoDoc::$reflectionMethod->getName();
                if (! $parent->hasMethod($name)) return '';

                $doc=$parent->getMethod($name)->getDocComment();
                if ($doc===false) return '';

                $doc=new DocBlock($doc);

                $doc=$doc->longDescription;

                $doc=str_replace('href="#', 'href="?class='.$parent->getName().'#', $doc);

                return $doc;

            default:
                echo 'tag inconnu : ', $match[0], '<br />';
                return $match[0];

        }
    }
}
?>