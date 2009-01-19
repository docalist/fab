<?php
/**
 * @package     fab
 * @subpackage  template
 * @author 		Daniel Ménard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id$
 */


/*
 * TODO :
 * - FAIT, Youenn avoir l'équivalent des walktables
 * - blocs <fill>/</fill>
 * - TableOfContents
 * - HandleLinks (conversion des liens en fonction des routes)
 * - Includes (un autre template)
 *
 */
/**
 * Gestionnaire de templates
 *
 * @package     fab
 * @subpackage  template
 */
class Template
{
    /**
     * options de configuration utilisées :
     *
     * templates.forcecompile : indique s'il faut ou non forcer une recompilation
     * complète des templates, que ceux-ci aient été modifiés ou non.
     *
     * templates.checktime : indique si le gestionnaire de template doit vérifier
     * ou non si les templates ont été modifiés depuis leur dernière compilation.
     *
     * cache.enabled: indique si le cache est utilisé ou non. Si vous n'utilisez
     * pas le cache, les templates seront compilés à chaque fois et aucun fichier
     * compilé ne sera créé.
     */


    /**
     * @var array Pile utilisée pour enregistrer l'état du gestionnaire de
     * templates et permettre à {@link run()} d'être réentrante.
     */
    private static $stateStack=array();

    /**
     * @var array Tableau utilisé pour gérer les blocs <opt> imbriqués (voir
     * {@link optBegin()} et {@link optEnd()})
     * @access private
     */
    private static $optFilled=array(0);

    /**
     * @var integer Indique le niveau d'imbrication des blocs <opt> (voir {@link
     * optBegin()} et {@link optEnd()})
     * @access private
     */
    private static $optLevel=0;

    /**
     * @var array Sources de données passées en paramètre
     *
     * @access private
     */
    public static $data=null;

    /**
     * @var string Path complet du template en cours d'exécution.
     * Utilisé pour résoudre les chemins relatifs (tables, sous-templates...)
     * @access private
     */
    private static $template='';

    public static $isCompiling=0;

    /**
     * constructeur
     *
     * Le constructeur est privé : il n'est pas possible d'instancier la
     * classe. Utilisez directement les méthodes statiques proposées.
     */
    private function __construct()
    {
    }

    const PHP_START_TAG='<?php ';
    const PHP_END_TAG=" ?>";

    public static function getLevel()
    {
    	return count(self::$stateStack);
    }

    /**
     * TODO: doc obsolète, à revoir
     * Exécute un template, en le recompilant au préalable si nécessaire.
     *
     * La fonction run est réentrante : on peut appeller run sur un template qui
     * lui même va appeller run pour un sous-template et ainsi de suite.
     *
     * @param string $template le nom ou le chemin, relatif ou absolu, du
     * template à exécuter. Si vous indiquez un chemin relatif, le template est
     * recherché dans le répertoire du script appellant puis dans le répertoire
     * 'templates' de l'application et enfin dans le répertoire 'templates' du
     * framework.
     *
     * @param mixed $dataSources indique les sources de données à utiliser
     * pour déterminer la valeur des balises de champs présentes dans le
     * template.
     *
     * Les gestionnaire de templates reconnait trois sources de données :
     *
     * 1. Des fonctions de callback.  Une fonction de callback est une fonction
     * ou une méthode qui prend en argument le nom du champ recherché et
     * retourne une valeur. Si votre template contient une balise de champ
     * '[toto]', les fonctions de callback que vous indiquez seront appellé les
     * unes après les autres jusqu'à ce que l'une d'entre elles retourne une
     * valeur.
     *
     * Lorsque vous indiquez une fonction callback, il peut s'agir :
     *
     * - d'une fonction globale : indiquez dans le tableau $dataSources une
     * chaine de caractères contenant le nom de la fonction à appeller (exemple
     * : 'mycallback')
     *
     * - d'une méthode statique de classe : indiquez dans le tableau
     * $dataSources soit une chaine de caractères contenant le nom de la classe
     * suivi de '::' puis du nom de la méthode statique à appeller (exemple :
     * 'Template:: postCallback') soit un tableau à deux éléments contenant à
     * l'index zéro le nom de la classe et à l'index 1 le nom de la méthode
     * statique à appeller (exemple : array ('Template', 'postCallback'))
     *
     * - d'une méthode d'objet : indiquez dans le tableau $dataSources un
     * tableau à deux éléments contenant à l'index zéro l'objet et à l'index 1
     * le nom de la méthode à appeller (exemple : array ($this, 'postCallback'))
     *
     * 2. Des propriétés d'objets. Indiquez dans le tableau $dataSources l'objet
     * à utiliser. Si le gestionnaire de template rencontre une balise de champ
     * '[toto]' dans le template, il regardera si votre objet contient une
     * propriété nommée 'toto' et si c'est le cas utilisera la valeur obtenue.
     *
     * n.b. vous pouvez, dans votre objet, utiliser la méthode magique '__get'
     * pour créer de pseudo propriétés.
     *
     * 3. Des valeurs : tous les éléments du tableau $dataSources dont la clé
     * est alphanumérique (i.e. ce n'est pas un simple index) sont considérées
     * comme des valeurs.
     *
     * Si votre template contient une balise de champ '[toto]' et que vous avez
     * passé comme élément dans le tableau : 'toto'=>'xxx', c'est cette valeur
     * qui sera utilisée.
     *
     * @param string $callbacks les nom des fonctions callback à utiliser pour
     * instancier le template. Vous pouvez indiquer une fonction unique ou
     * plusieurs en séparant leurs noms par une virgule. Ce paramètre est
     * optionnel : si vous n'indiquez pas de fonctions callbacks, la fonction
     * essaiera d'utiliser une fonction dont le nom correspond au nom du
     * script appellant suffixé avec "_callback".
     */


    /**
     * Exécute un template
     *
     * @param string $path le nom du template (il peut s'agir du path du template s'il s'agit
     * d'un fichier ou d'un nom symbolique s'il s'agit d'un source généré)
     *
     * @param array|boolean les données du template
     * @param string $source le source du template. (uniquement pour les templates générés à la volée)
     * quand source est non null, on n'essaie pas de charger le fichier $path, on utilise dirtectement
     * le source
     */
    private static function runInternal($path, array $data, $source=null)
    {
//echo '<div style="background:#888;padding: 1em;border: 1px solid red;"><div>','TEMPLATE ', $path,'</div>';
        debug && Debug::log('Exécution du template %s', $path);

        // Sauvegarde l'état
        array_push
        (
            self::$stateStack,
            array
            (
                'template'      => self::$template,
                'data'          => self::$data
            )
        );

        // Stocke le path du template
        self::$template=$path;

        // Stocke les sources de données passées en paramètre
        self::$data=$data;

        // Calcule la signature des sources de données
        $signature='';
        foreach(self::$data as $data)
        {
            if (is_object($data))
                $signature.='o';
            elseif (is_string($data))
            {
                $signature.='f';
                if (! is_callable($data))
                    throw new Exception("fonction $data non trouvée");
            }
            elseif (is_array($data))
            {
                if (is_callable($data))
                    $signature.='m';
                else
                    $signature.='a';
                    // TODO : les clés du tableau doivent être des chaines
            }
            else
                throw new Exception('Type de source de données incorrecte : objet, tableau ou callback attendu');
        }

        // Détermine le path dans le cache du fichier
        $cachePath=Utils::setExtension($path, $signature . Utils::getExtension($path));

        // Compile le template s'il y a besoin
        if (self::needsCompilation($path, $cachePath))
        {
            Timer::enter('Template.Compile ' . $path);
            // Charge le contenu du template
            if (is_null($source))
                if ( false === $source=file_get_contents($path) )
                    throw new Exception("Le template '$template' est introuvable.");

            // Compile le code
//            require_once dirname(__FILE__) . '/TemplateCompiler.php';
            $source=TemplateCompiler::compile($source, self::$data);

//          if (php_version < 6) ou  if (! PHP_IS_UTF8)
            $source=utf8_decode($source);

            // Stocke le template dans le cache et l'exécute
            if (config::get('cache.enabled'))
            {
                debug && Debug::log("Mise en cache de '%s'", $path);
                Cache::set($cachePath, $source);
                debug && Debug::log("Exécution à partir du cache");
                Timer::leave();
                require(Cache::getPath($cachePath));
            }
            else
            {
                Timer::leave();
                debug && Debug::log("Cache désactivé, evaluation du template compilé");
                eval(self::PHP_END_TAG . $source);
            }

        }

        // Sinon, exécute le template à partir du cache
        else
        {
            debug && Debug::log("Exécution à partir du cache");
            require(Cache::getPath($cachePath));
        }

        // restaure l'état du gestionnaire
        $t=array_pop(self::$stateStack);
        self::$template         =$t['template'];
        self::$data             =$t['data'];
//echo '</div>';
    }

    public static function run($path /* $dataSource1, $dataSource2, ..., $dataSourceN */ )
    {
        Timer::enter('Template.run '.$path);
        // Résout le path s'il est relatif
        if (Utils::isRelativePath($path))
        {
            $sav=$path;
            if (false === $path=Utils::searchFile($path))
                throw new Exception("Impossible de trouver le template $sav. searchPath=".print_r(Utils::$searchPath, true));
        }

        // Crée un tableau à partir des sources de données passées en paramètre
        $data=func_get_args();
        array_shift($data);

        // Ajoute une source '$this' correspondant au module appellant
        array_unshift($data,array('this'=>Utils::callerObject(2)));

        // Exécute le template
        self::runInternal($path,$data);
        Timer::leave();
    }

    public static function runSource($path, $source /* $dataSource1, $dataSource2, ..., $dataSourceN */ )
    {
        // Détermine le path du répertoire du script qui nous appelle
        //$path=dirname(Utils::callerScript()).DIRECTORY_SEPARATOR;
        // TODO: +numéro de ligne ou nom de la fonction ?

        // Crée un tableau à partir des sources de données passées en paramètre
        $data=func_get_args();
        array_shift($data); // enlève $path de la liste
        array_shift($data); // enlève $source de la liste

        // Ajoute une source '$this' correspondant au module appellant
        array_unshift($data,array('this'=>Utils::callerObject(2)));

        // Exécute le template
        self::runInternal($path,$data,$source);
    }

    /**
     * Teste si un template a besoin d'être recompilé en comparant la version
     * en cache avec la version source.
     *
     * La fonction prend également en compte les options templates.forcecompile
     * et templates.checkTime
     *
     * @param string $template path du template à vérifier
     * @param string autres si le template dépend d'autres fichiers, vous pouvez
     * les indiquer.
     *
     * @return boolean vrai si le template doit être recompilé, false sinon.
     */
    private static function needsCompilation($path, $cachePath)
    {
        // Si templates.forceCompile est à true, on recompile systématiquement
        if (Config::get('templates.forcecompile')) return true;

        // Si le cache est désactivé, on recompile systématiquement
        if (! Config::get('cache.enabled')) return true;

        // si le fichier n'est pas encore dans le cache, il faut le générer
        $mtime=Cache::lastModified($cachePath);
        if ( $mtime==0 ) return true;

        // Si templates.checktime est à false, terminé
        if (! Config::get('templates.checktime')) return false;

        // Compare la date du fichier en cache avec celle du fichier original
        if ($mtime<=@filemtime($path) ) return true;

        // explication du '@' ci-dessus :
        // si le fichier n'existe pas (utilisation de runSource, par exemple)
        // évite de générer un warning et retourne false (ie pas besoin de
        // recompiler)

        // Le fichier est dans le cache et il est à jour
        return false;
    }

    /**
     * Fonction appellée au début d'un bloc <opt>
     * @internal cette fonction ne doit être appellée que depuis un template.
     * @access public
     */
    public static function optBegin()
    {
        self::$optFilled[++self::$optLevel]=0;
        ob_start();
    }

    /**
     * Fonction appellée à la fin d'un bloc <opt>
     * @internal cette fonction ne doit être appellée que depuis un template.
     * @access public
     */
    public static function optEnd($minimum=1)
    {
        // Si on a rencontré au moins un champ non vide
        if (self::$optFilled[self::$optLevel--]>=$minimum)
        {
            // Indique à l'éventuel bloc opt parent qu'on est renseigné
            self::$optFilled[self::$optLevel]++ ;

            // Envoit le contenu du bloc
            ob_end_flush();
        }

        // Sinon, vide le contenu du bloc
        else
        {
            ob_end_clean();
        }
    }

    /**
     * Examine la valeur passée en paramètre et marque le bloc opt en cours comme
     * "rempli" si la valeur est renseignée.
     *
     * La valeur est considérée comme vide si :
     * - c'est la valeur 'null'
     * - c'est une chaine de caractères vide
     * - c'est un tableau ne contenant aucun élément
     *
     * Elle est comme renseignée si :
     * - c'est une chaine non vide
     * - c'est un entier ou un réel (même si c'est la valeur zéro)
     * - c'est un booléen (même si c'est la valeur false)
     * - c'est un tableau non vide
     * - un objet, une ressource, etc.
     *
     * @param mixed $x la valeur à examiner
     * @return mixed la valeur passée en paramètre
     */
    public static function filled($x)
    {
        // Cas où $x est considéré comme non rempli
        if (is_null($x)) return $x;
        if ($x==='') return $x;
        if (is_array($x) && count($x)===0) return $x;

        // Dans tous les autres cas, considéré comme renseigné
        // int ou float (y compris 0), array non vide, bool (y compris false)
        self::$optFilled[self::$optLevel]++;
    	return $x;
    }

    /**
     * Construit la liste des valeurs qui est utilisée par un bloc <fill>...</fill>
     *
     * @param string|array $values les valeurs provenant du champ
     * @param boolean $strict true pour que le fill tiennent compte des accents
     * et de la casse des caractères, false (valeur par défaut) sinon.
     * @return array un tableau dont les clés contiennent les articles trouvés
     * dans $values et dont la valeur est true.
     */
    public static function getFillValues($values, $strict=false)
    {
        if (! is_array($values))
            $values=preg_split('~\s*[,;/·¨|]\s*~', trim($values), -1, PREG_SPLIT_NO_EMPTY);
        // autres candidats possibles comme séparateurs utilisés dans le preg_split ci-dessus : cr, lf, tilde

        $save=$values;

        if (! $strict)
        {
            foreach($values as & $value)
                $value=implode(' ', Utils::tokenize($value));
        }

        if (count($values))
            $values=array_combine($values, $save);

        return $values;
    }

/*
runSlot examine la config en cours pour savoir s'il faut examiner le noeud ou pas.
si enabled=false : return false (ne pas exécuter le slot, ne pas afficher le contenu par défaut)
si file="" et action="" return true (afficher le contenu par défaut du slot)
si file : Template::Run(file, currentdatasources)
sinonsi action : Routing::dispatch(action, currentdatasource)
runSlot retourne true s'il faut afficher le contenu par défaut du noeud
return false (ne pas afficher le contenu par défaut)
*/
    public static function runSlot($name, $defaultAction='', array $args=null)
    {
//        echo "Début d'exécution du slot $name, action par défaut : $defaultAction, args=", var_export($args,true),"<br />";
        $action=Config::get("slots.$name", $defaultAction);
//        echo "Action à exécuter : $action<br />";

        if ($action==='')
        {
//            echo "contenu par défaut\n";
            debug && Debug::log('slot %s : affichage du contenu par défaut', $name);
            return true;
        }
        if ($action==='none')
        {
//            echo 'désactivé<br />';
            debug && Debug::log('slot %s désactivé', $name);
        	return false;
        }
        debug && Debug::log('slot %s : %s', $name, $action);
//echo '<div style="background:#888;padding: 1em;border: 1px solid red;"><div>','SLOT ', $name,'=',($action?$action:'(contenu par défaut)'),'</div>';
        // S'il s'agit d'une action, on l'exécute
        if ($action[0]==='/')
        {
            $action=ltrim($action, '/');
            $module=strtok($action, '/');
            $action=strtok('/');
            if ($action==='') $action='Index';

            // On repart de la requête d'origine, et on ajoute les nouveaux paramètres
            $request=Runtime::$request->copy();
            if (! is_null($args)) $request->addParameters($args);

            $sav=Runtime::$request->getModule($module);
            Runtime::$request->setModule($module);
            $savConfig=Config::getAll();
            Config::clear();
            Config::addArray(Runtime::$baseConfig);
            Module::runAs($request, $module, $action);
            Config::clear();
            Config::addArray($savConfig);
            Runtime::$request->setModule($sav);
        }

        // C'est un template : on l'exécute
        else
        {
            $path=$action;

            // Résout le path s'il est relatif
            if (Utils::isRelativePath($action))
            {
                if (false === $path=Utils::searchFile($path, Runtime::$root))   // root : permet que le template soit relatif à la racine du site (exemple : wordpress)
                    throw new Exception("Impossible de trouver le template $action. searchPath=".print_r(Utils::$searchPath, true));
            }

            // Ajoute les arguments passés en paramètre aux sources de données en cours
            $data=self::$data;
            if (!is_null($args))
            {
                // Le premier élément de self::$data correspond à $this
                // On l'enlève de data (array_shift)
                // on l'ajoute comme premier élément de args (+)
                // et on ajoute le tout au début de data (array_unshift)
                array_unshift($data,array_shift($data)+$args);

                // for the record : j'ai cherché longtemps comment utiliser array_shift pour
                // ajouter un élément en conservant la clé. En fait il suffit suffit d'utiliser
                // l'opérateur de tableaux '+'...

                // echo '<h1>data final</h1>', Debug::dump($data,false);
            }

            // Exécute le template
            self::runInternal($path,$data);
        }
//echo '</div>';
        return false;
    }
}

?>
