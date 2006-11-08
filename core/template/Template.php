<?php
/**
 * @package     fab
 * @subpackage  template
 * @author 		Daniel Ménard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id: Template.php 105 2006-09-21 16:30:25Z dmenard $
 */

/**
 * Gestionnaire de templates
 * 
 * Un template est un fichier texte qui contient des balises. Assez souvent,
 * il s'agit d'un fichier html (soit complet, soit juste un fragment).
 * 
 * Il existe deux sortes de balises : les balises de champs ([xxx]) et les
 * balises de contrôles qui permettent de faire des boucles, des conditions,
 * etc.
 * 
 * [DONE] - Commentaires. Vous pouvez inclure des commentaires dans votre
 * template. Syntaxe : /* commentaire *\/
 * Les commentaires sont automatiquement supprimés dans la version compilée
 * du template.
 * 
 * Remarque : si votre template contient du code html , vous pouvez
 * également utiliser les commentaires html standards : <!-- commentaire -->.
 * Ils seront également supprimés.
 * 
 * 
 * [DONE]- balises de champs : il s'agit d'un identifiant quelconque que vous
 * indiquez entre crochets. Exemple : [titre]. Lors de l'exécution, la balise de
 * champ sera remplacée dans le template par la valeur retournée par la fonction
 * de callback.
 * 
 * [DONE] - il est possible, au sein d'une balise de champ, d'indiquer plusieurs
 * champs et éventuellement une valeur littérale par défaut, en les séparant par
 * une virgule.
 * 
 * Exemple : [TitreFRE,TitreEng,'Document sans titre'] 
 * 
 * Dans ce cas, la fonction callback sera appellée pour chacun des champs
 * jusqu'à ce qu'une valeur non vide soit retournée. Si aucune valeur n'est
 * obtenue, la valeur par défaut sera utilisée.
 * La valeur littérale peut être indiquée entre guillemets simples ou doubles.
 * Pour include un guillemet dans la chaine, le faire précéder par un antislash
 * (même syntaxe que les chaines littérales en PHP).
 *  
 * Exemple : "l'astuce du \"jour\"", 'l\'astuce du "jour"'
 * 
 * 
 * [DONE] - Blocs optionnels. Vous pouvez encadrer une partie de votre template
 * par des balises <opt> et </opt> pour indiquer qu'il s'agit d'un bloc
 * optionnel. La signification d'un tel bloc est la suivante : si le bloc
 * contient des balises de champ et qu'au moins une de ces balises de champs
 * retourne une valeur non vide lors de l'exécution du template, alors ce bloc
 * sera affiché, sinon l'intégralité du bloc sera supprimée.
 * Nb : peut importe ou se trouve les balises (balise simple, condition d'un
 * if, etc.) : elles sont toutes prises en compte.
 * Nb2 : le coté optionnel d'un bloc <opt>...</opt> ne concerne que l'affichage.
 * Si vous faites des traitements dans votre callback, ceux-ci seront toujours
 * exécutés, que le bloc soit au final affiché ou non.
 * 
 * 
 * [DONE] - Un template peut également contenir du code php, bien que ceci ne
 * soit pas conseillé. Encadrer le code php avec les balises habituelles (<?php
 * et ?>). Le code sera exécuté lors de l'exécution du template, et non pas lors
 * de sa compilation. [TODO] : faut-il un moyen d'exécuter du code au moment de
 * la compilation ? (exemple : boucle sur les prix éditions ensp)
 * 
 * 
 * [DONE ]- Conditions. Vous pouvez utiliser dans votre template des blocs 
 * <if test="">...</if> et <else>...</else> pour afficher certaines parties
 * en fonction du résultat de l'évaluation d'une condition.
 * Dans l'attribut test, vous devez indiquer la condition qui sera évaluée. Il
 * doit s'agir d'une expression PHP valide.
 * 
 * Exemple : 
 * <if test="hasAccess('admin')">Accès</if><else>Accès refusé</else>
 * 
 * [DONE] - Vous pouvez également utiliser des balises de champs [xxx] pour
 * exprimer la condition.
 * 
 * Nb pas insérer de balises de champs dans des chaines de caractères, cela ne
 * fonctionnera pas.
 * 
 * Exemples : 
 * <if test='true'   "[user]=='admin'">Accès</if><else>Accès refusé</else>
 * <if test="isAdmin([user])">Accès</if><else>Accès refusé</else>
 * 
 * Provoquera  une erreur :
 * <if test="'[user]'=='admin'">Accès</if><else>Accès refusé</else>
 *
 * [DONE] - Boucles <loop>...</loop> syntaxe : <loop on="xxx" max="yyy" order="
 * zzz">code</loop> xxx peut-être : - bis($s) où $s est une variable globale
 * contenant une sélection bis - array($t) où $t est une variable globale
 * contenant un tableau php - table(xxx) où xxx est le nom d'une table (fichier
 * texte tabulé). La table est recherché dans le même répertoire que le template
 * et dans le répertoire $root/tables [à mettre dans la config]
 * 
 * 
 * [INUTILE] - Walk
 * 
 * [DONE] - Fill
 * 
 * [DONE] - Templates générés automatiquement à partir d'un fichier Yaml
 * 
 * [TODO] - Sous-templates
 * 
 * [TODO] - TableOfContents
 * 
 * @package     fab
 * @subpackage  template
 */
class Template
{
public static $csource='';
    /**
     * @var boolean Indique s'il faut ou non forcer une recompilation complète
     * des templates, que ceux-ci aient été modifiés ou non.
     * @access public 
     */
    public static $forceCompile=false;

    /**
     * @var boolean Indique si le gestionnaire de template doit vérifier ou
     * non si les templates ont été modifiés depuis leur dernière compilation.
     * @access public
     */
    public static $checkTime=true;
    
    /**
     * @var boolean Indique si le cache est utilisé ou non. Si vous n'utilisez
     * pas le cache, les templates seront compilés à chaque fois et aucun
     * fichier compilé ne sera créé. 
     * @access public
     */
    public static $useCache=true;


    /**
     * @var array Pile utilisée pour enregistrer l'état du gestionnaire de
     * templates et permettre à {@link run()} d'être réentrante. Voir {@link
     * saveState()} et {@link restoreState()}
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
     * @var string Liste des callbacks à appeller pour déterminer la valeur d'un
     * champ. Il s'agit d'un copie de l'argument $callback  indiqué par
     * l'utilisateur lors de l'appel à {@link run()}.
     * 
     * @access private
     */
    private static $dataCallbacks;
    private static $dataObjects;
    private static $dataVars;
    

    /**
     * @var string Flag utilisé par certains callback pour savoir s'il faut ou
     * non insérer les tags de début et de fin de php. Cf par exemple {@link
     * handleFieldsCallback()}.
     * @access private
     */
    private static $inPhp=false;
    
    /**
     * @var array Tableau utilisé par {@link hashPhpBlocks} pour protéger les
     * blocs php générés par les balises.
     * @access private
     */
    private static $phpBlocks=array();
    
    /**
     * @var string Path complet du template en cours d'exécution.
     * Utilisé pour résoudre les chemins relatifs (tables, sous-templates...)
     * @access private
     */
    private static $template='';
    
    /**
     * @var string Lors de la génération d'un template (cf {@link generate()}),
     * nom du jeu de générateurs à utiliser. Correspond à un sous-réperoire du
     * répertoire "template_generators"
     * @access private
     */
    private static $genSet;
    
    /**
     * @var string Lors de la génération d'un template (cf {@link generate()}),
     * item en cours de génération
     * @access private
     */
    private static $genItem;
    
    
    /**
     * @var array Utilisé par {@link generateId()} pour générer des identifiants
     * unique. Le tableau contient, pour chaque nom utilisé lors d'un appel à
     * {@link generateId()} le dernier numéro utilisé.
     */
    private static $usedId;
    public static $data=null;
    
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
    
    public static function setup()
    {
        self::$useCache=Config::get('cache.enabled', false);
        self::$forceCompile=Config::get('templates.forcecompile', false);
        self::$checkTime=Config::get('templates.checktime', false);
        
//        echo '<pre>';
//        var_dump(self::$useCache, self::$forceCompile, self::$checkTime, Config::getAll());
//        echo '</pre>';
    }
    
    public static function getLevel()
    {
    	return count(self::$stateStack);
    }
    
    /**
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
    public static function run($template /* $dataSource1, $dataSource2, ..., $dataSourceN */ )
    {
        debug && Debug::log("Template::Run('%s')", $template);
        debug && Debug::tplLog("Template::Run", $template." (TODO: lister les datasources)");
        if (count(self::$stateStack)==0)
        {
            // On est au niveau zéro, c'est un tout nouveau template, réinitialise les id utilisés.
            self::$usedId=array();
        }

        $parentDir=dirname(self::$template);

        // Sauvegarde l'état
        self::saveState();

        // Détermine le path du répertoire du script qui nous appelle
        $caller=dirname(Utils::callerScript()).DIRECTORY_SEPARATOR;
        
        // Recherche le template
        $sav=$template;
        if (strncasecmp($caller, Runtime::$fabRoot, strlen(Runtime::$fabRoot)) ==0)
        {
            // module du framework : le répertoire templates de l'application est prioritaire
            $template=Utils::searchFile
            (
                $template,                          // On recherche le template :
                Runtime::$root . 'templates',       // 1. dans le répertoire 'templates' de l'application
                $caller,                            // 2. dans le répertoire du script appellant
                Runtime::$fabRoot . 'templates',     // 3. dans le répertoire 'templates' du framework
                $parentDir
            );
        }
        else
        {        
            // module de l'application : le répertoire du script appellant est prioritaire
            $template=Utils::searchFile
            (
                $template,                          // On recherche le template :
                $caller,                            // 1. dans le répertoire du script appellant
                Runtime::$root . 'templates',       // 2. dans le répertoire 'templates' de l'application
                Runtime::$fabRoot . 'templates',     // 3. dans le répertoire 'templates' du framework
                $parentDir
            );
        }
        if (! $template) 
            throw new Exception("Impossible de trouver le template $sav");

        debug && Debug::log("Path du template : '%s'", $template);
        self::$template=$template; // enregistre le path du template en cours (cf walkTable)
        
        self::$data=func_get_args();    // new
        array_shift(self::$data);
        debug && Debug::log('Données du formulaire %s : %s', $sav, self::$data);

        self::$dataCallbacks=self::$dataObjects=self::$dataVars=array();    // old
        $nb=func_num_args();
        for ($i=1; $i<$nb; $i++)
        {
            $value=func_get_arg($i);
            
            if (is_object($value))              // Un objet, exemple : $this
                self::$dataObjects[]=$value;
            elseif (is_string($value))          // Une fonction globale, exemple : 'mycallback' 
            {                                   // Ou une méthode statique, exemple : 'template::callback'
                $value=rtrim($value, '()');
                if (strpos($value, '::') !== false)
                    $value=explode('::', $value);
                self::$dataCallbacks[]=$value;
            }
            elseif (is_array($value))           // Un tableau de valeur ou un tableau callback
//            elseif (is_array($value) or($value instanceof Iterator and $value instanceOf ArrayAccess))
            {

                if (is_callable($value))        // C'est un callback
                    self::$dataCallbacks[]=$value;                    	
                else
                {
                    // TODO: à faire seulement en mode debug ou en environnement test
                    foreach ($value as $key=>$item)
                        if (! is_string($key)) 
                            throw new Exception("Les clés d'un tableau de valeur doivent être des chaines de caractères. Callback passé : " . Debug::dump($key) . ', ' . Debug::dump($item));
                    self::$dataVars=array_merge(self::$dataVars, $value);
                }
            }
            else
                echo "Je ne sais pas quoi faire de l'argument numéro ", ($i+1), " passé à template::run <br />", print_r($value), "<br />";
        }
            
        // Compile le template s'il y a besoin
        if (TRUE or self::needsCompilation($template)) // TODO : à virer
        {
            //
            debug && Debug::notice("'%s' doit être compilé", $template);
            
            // Charge le contenu du template
            if ( ! $source=file_get_contents($template, 1) )
                throw new Exception("Le template '$template' est introuvable.");
            
            // Teste si c'est un template généré
            if (Utils::getExtension($template)=='.yaml') // TODO: à revoir
            {
                // Autorise l'emploi des tabulations (une tab=4 espaces) dans le source yaml
                $source=str_replace("\t", '    ', $source);
                
                //echo "<h1>Description du template :</h1>";
                //highlight_string($source);
                debug && Debug::notice("Génération du template html à partir du fichier yaml");
                self::generate($source);
                
                // Nettoyage
                //$source=str_replace(self::PHP_END_TAG."\r", self::PHP_END_TAG."\n", $source);
//                echo "<h1>Template généré: </h1>";
//                highlight_string($source);
//                echo "<hr />";
            }
            
            //echo "<h1>Source à compiler :</h1>";
            //highlight_string($source);
                        
            // Compile le code
            debug && Debug::log('Compilation du source');
            require_once dirname(__FILE__) . '/TemplateCompiler.php';
            $source=TemplateCompiler::compile($source);
self::$csource=$source;// TODO: à virer
            // Nettoyage
            // si la balise de fin de php est \r, elle est mangée (cf http://fr2.php.net/manual/fr/language.basic-syntax.instruction-separation.php)
            $source=str_replace(self::PHP_END_TAG."\r", self::PHP_END_TAG." \r", $source);
//            $source=preg_replace('~[\r\n][ \t]*([\r\n])~', '$1', $source);
            $source=preg_replace("~([\r\n])\s+$~m", '$1', $source);
            
            //echo "<h1>Version compilée du source :</h1>";
            //highlight_string($source);
                        
            // Stocke le template dans le cache
            if (self::$useCache)
            {
                debug && Debug::log("Mise en cache de '%s'", $template);
                Cache::set($template, $source);
            }
            
            // Exécute le source compilé
            if (self::$useCache)
            {
                debug && Debug::log("Exécution à partir du cache");
                require(Cache::getPath($template));
            }
            else
            {
                debug && Debug::log("Cache désactivé, eval()");
                eval(self::PHP_END_TAG . $source);
            }            
        }
        
        // Sinon, exécute le template à partir du cache
        else
        {
            debug && Debug::log("Exécution à partir du cache");
            require(Cache::getPath($template));
        }

        // restaure l'état du gestionnaire
        self::restoreState();
    }        

//    /**
//     * Compile le source de template passé en paramètre.
//     * 
//     * @param string $source En entrée, le source du template à compiler, en
//     * sortie la version compilée
//     */
//    private static function compile(&$source)
//    {
//        // Exécute les blocs PHP éventuellement présents dans le template
//        self::execPHP($source);
//    
//        // supprime les commentaires !!! à faire en premier
//        self::removeComments($source);
//
//        // Convertit les liens en fonction des routes
//        self::handleLinks($source);
//        
//        // Blocs <if>...</if><else>...</else>
//        self::handleIfElse($source);
//        
//        // Blocs <loop>...</loop>
//        self::handleLoop($source);
//        
//        // Blocs <fill>...</fill>
//        self::handleFill($source);        
//        
//        // Blocs <tableofcontents>...</tableofcontents>
//        self::handleTableOfContents($source);
//        
//        // Blocs <opt>...</opt>
//        self::handleOpt($source);
//
//        self::handleInclude($source);
//
//        // Champs [xxx]  !!! à faire en dernier
//        self::handleFields($source);
//        
//        // php "mange" systématiquement le \r qui suit la balise de fermeture 
//        // du coup on lui en donne un à manger pour qu'il ne touche pas à l'original
//        //$source=str_replace(self::PHP_END_TAG."\r", self::PHP_END_TAG."\r\r", $source);
//    }

    /**
     * Génère un template à partir de la description qui en est faite dans un
     * fichier YAML
     * 
     * @param string $source En entrée, la descrition yaml du template à
     * générer, en sortie le template généré
     */
    private static function generate(&$source)
    {
        // Analyse le source Yaml
        $t=Utils::loadYaml($source);
        
        // Détermine le jeu de générateurs à utiliser
        self::$genSet=isset($t['generator']) ? $t['generator'] : 'default';

        // Lance la génération
        self::$genItem=&$t;
        $source=self::generateCallback('contents');
    }

    private static function generateCallback($name)
    {
        static $used=array(1=>array('0'=>'0', '1'=>'1', 'type'=>'type', 'name'=>'name', 'id'=>'id', 'auto_id'=>'auto_id'));
        
        switch($name)
        {
            case 'contents': 
                $used[count($used)][$name]=$name;
                // IDEA: voir si on peut se passer de 'contents:'
/* 
                // Contenu de l'item 1 : syntaxe '- [xxx]', 'contents' : syntaxe 'contents: xxx'
                if (isset(self::$genItem[1])) 
                {
                    $content=self::$genItem;
                if (! is_array($content)) return $content;
                    unset($content[0]);
                    var_dump($content);
                } 
                else
                {
                    $content=self::$genItem['contents'];
                if (! is_array($content)) return $content;
                    var_dump($content);
                } 
*/
                $content=@self::$genItem['contents'];
                if ($content=='')
                {
                	echo 'ITEM SANS CONTENTS: <pre>'.print_r(self::$genItem,true).'</pre>';
                }
                if (! is_array($content)) return $content;

                ob_start();
                self::saveState(); // HACK: à virer. lorsqu'on fait le save dans run, genItem à déjà été modifié 
                foreach ($content as self::$genItem)
                {
                    // Type de l'item 0 : syntaxe '- [xxx]', 'type' : syntaxe '- type: xxx'
                    $type=isset(self::$genItem[0]) ? self::$genItem[0] : self::$genItem['type'];
//                    echo "<h1>type=$type</h1><pre>";var_dump($content, self::$genItem);echo"</pre>";
                    // Détermine le path du template à utiliser 
                    // Par exemple : ./template_generators/default/radio.htm
                    $template=
                        'template_generators' . DIRECTORY_SEPARATOR
                        . self::$genSet . DIRECTORY_SEPARATOR 
                        . $type
                        . '.htm';
                    
                    // TODO : si le template indiqué n'existe pas dans le jeu de générateurs sélectionné,
                    // le rechercher dans le jeu de templates 'default'.
                    // S'il n'existe toujours pas, erreur 'type de composant' non géré.
                     
                    // Initialise l'id de l'objet
//                    if (isset(self::$genItem['name']))
//                        $name=self::$genItem['name'];
//                    else
//                        $name=$type;
//                        
//                    if (isset(self::$usedId[$name])) // si id déjà utilisé, ajoute un numéro (2, 3...)
//                    {
//                        debug && Debug::notice('Génère ID pour %s. Dernier utilisé : %s', $name, self::$usedId[$name]);
//                        $name=$name . '_' . (++self::$usedId[$name]+1);
//                        debug && Debug::notice('ID généré: %s', $name);
//                    }
//                    else
//                    {
//                        debug && Debug::notice('Génère ID pour %s. Jamais utilisé. ID généré : %s', $name, $name);
//                        self::$usedId[$name]=0;
//                    }
//                    
//                    self::$genItem['auto_id']=$name; // auto_id existe toujours
//                    
                    // auto_id existe toujours
                    if (isset(self::$genItem['id']))
                        self::$genItem['auto_id']=self::$genItem['id']; 
                    else
                    {
                        self::$genItem['auto_id']=self::generateId(isset(self::$genItem['name']) ? self::$genItem['name'] : $type); 
                        if (isset(self::$genItem['name'])) // id n'existe que si name a été indiqué
                            self::$genItem['id']=self::$genItem['name'];
                    }
                    
                    // Construit le code de l'objet
                    array_push($used, array('0'=>'0', '1'=>'1', 'type'=>'type', 'name'=>'name', 'id'=>'id', 'auto_id'=>'auto_id'));
                    ob_start();
                    Template::run($template, array('Template', 'generateCallback'));
                    $source=ob_get_clean();
//                    $h="Elément courant : " . print_r(self::$genItem, true) . ', used : ' . print_r($used[count($used)], true) . ', diff: ' . print_r(array_diff_key(self::$genItem, $used[count($used)]), true);
                    if (strpos($source, '[attributes]')!==false)
                    {    
                        $h='';
                        foreach (array_diff_key(self::$genItem, $used[count($used)]) as $key=>$value)
                            $h.= ($h ? ' ': '') . $key. '="' .  htmlspecialchars(is_array($value)?'dmarray':$value) . '"';
                        $source=str_replace('[attributes]', $h, $source);
                    }
                    array_pop($used);
                    echo $source;
                }
                self::restoreState();// HACK: à virer. lorsqu'on fait le save dans run, genItem à déjà été modifié
                return ob_get_clean();
            
            case 'value_or_name':
                if (isset(self::$genItem['value'])) 
                {
                    $used[count($used)]['value']='value';
                    return self::$genItem['value']; 
                }
                if (isset(self::$genItem['name']))
                { 
                    $used[count($used)]['name']='name';
                    return '['.self::$genItem['name'].']'; 
                } 
                return '';
                
            case 'attributes': return '[attributes]';
//                return "Elément courant : " . print_r(self::$genItem, true) . ', used : ' . print_r($used[count($used)], true) . ', diff: ' . print_r(array_diff_key(self::$genItem, $used[count($used)]), true);
//                $h='';
//                foreach (array_diff_key(self::$genItem, $used[count($used)]) as $key=>$value)
//                	$h.= ($h ? ' ': '') . $key. '="' .  htmlspecialchars($value) . '"';
//                return $h;
//                //return implode(',', $used[count($used)]);
                
            case 'sep': // HACK: à virer
                return '[sep]';
            
//            case 'auto_id':
//                return self::generateId( isset(self::$genItem['name']) ? self::$genItem['name'] : 'dmdm'); 
                
            default://echo "DUMP " ;var_dump($name);
                if ($name{0}=='[') return 'iii'.$name; 
                $used[count($used)][$name]=$name; 
                if (isset(self::$genItem[$name]))
                    return self::$genItem[$name];
                else
                    return '';
        }	
    }
    
    /**
     * Génère un identifiant unique (au sein de la page) pour l'identifiant
     * passé en paramètre
     * @param string id le nom pour lequel il faut générer un identifiant ('id'
     * si absent)
     * @return string un identifiant unique de la forme 'name', 'name2',
     * 'name3...'
     */
    private static function generateId($name='id')
    {
        if (isset(self::$usedId[$name]))
            return $name . '-' . (++self::$usedId[$name] + 1);
        else
        {
            self::$usedId[$name]=0;
        	return $name;
        }        
    }
    
    /**
     * Teste si un template a besoin d'être recompilé en comparant la version
     * en cache avec la version source.
     * 
     * La fonction prend également en compte les options {@link $forceCompile}
     * et {link $checkTime}
     * 
     * @param string $template path du template à vérifier
     * @param string autres si le template dépend d'autres fichiers, vous pouvez
     * les indiquer.
     * 
     * @return boolean vrai si le template doit être recompilé, false sinon.
     */
    private static function needsCompilation($template /* ... */)
    {
        // Si forceCompile est à true, on recompile systématiquement
        //echo "<li>forcecompile : " . var_export(self::$forceCompile,true) . "</li>";
        if (self::$forceCompile) return true;
        
        // Si le cache est désactivé, on recompile systématiquement
        //echo "<li>usecache : " . self::$useCache . "</li>";
        if (! self::$useCache) return true;
        
        // si le fichier n'est pas encore dans le cache, il faut le générer
        $mtime=Cache::lastModified($template);
        //echo "<li>mtime : $mtime</li>";
        if ( $mtime==0 ) return true; 
        
        // Si $checkTime est à false, terminé
        if (! self::$checkTime) return false; 

        // Compare la date du fichier en cache avec celle de chaque dépendance
        $argc=func_num_args();
        for($i=0; $i<$argc; $i++)
        {
            if ($mtime<=filemtime(func_get_arg($i)) ) 
            {
//                echo "<li>$template dépend de " . func_get_arg($i) . " qui est plus récent</li>";      
                return true;
            } 
        }
        
        // Aucune des dépendances n'est plus récente que le fichier indiqué
        return false;
    }
    
    /**
     * Exécute les blocs PHP présents dans le template
     * 
     * @param string $source le source à exécuter
     */
    private static function execPHP(&$source)
    {
        // php "mange" systématiquement le \r qui suit la balise de fermeture 
        // du coup on lui en donne un à manger pour qu'il ne touche pas à l'original
//        echo"<h1>eval. Template en cours : " . self::$template . "</h1>";
//        echo "<p>Source : </p>\n";
//        print(str_replace('<', '&lt;', $source));
        
        $source=str_replace(self::PHP_END_TAG."\r", self::PHP_END_TAG."\r\r", $source);
        ob_start();
        $result=eval('extract($GLOBALS);' . self::PHP_END_TAG . $source);
        $source=ob_get_clean() . $result;
//        echo "<p>Résultat : </p>\n";
//        print(str_replace('<', '&lt;', $source));
    }
    
    /**
     * Supprime les commentaires présents dans le template
     * 
     * @param string $source le source à modifier
     */
    private static function removeComments(&$source)
    {
        $source=preg_replace('~/\*.*?\*/~ms', null, $source);
        // $source=preg_replace('~<!--.*-->~ms', null, $source);
    }

    /**
     * Analyse les attributs d'une balise et retourne un tableau associatif
     * contenant les attributs rencontrés.
     * @param string $h les attributs à analyser
     * @return array un tableau contenant les attributs trouvés (clé=nom de
     * l'attribut, valeur=valeur de l'attribut)
     */
    public static function splitAttributes($h)
    {
        $matches=null;
        $nb=preg_match_all
        (
            '~
                ([\w]+)                 # $1 : nom attribut
                \s*=\s*                 # espaces égal espaces
                ([\'"])                 # $2 : un guillemet ou une apostrophe
                (.*?)                   # $3 : valeur attribut
                \2                      # guillemet ou apostrophe stocké en $2
            ~msx',
            $h,
            $matches
        );
    
        if ($nb)
        {   // l'attribut contient peut-être des balises (walk source="[table].[ext]" 
            $matches[3]=preg_replace // TODO : voir s'il faut garder
                (
                    '~
                    \[                  # crochet ouvrant
                    (                   # $1=nom du tag
                        [^\[\]]*           # tout caractère sauf crochet ouvrant
                    )
                    \]                  # crochet fermant
                    ~e',
                    "tplfield('$1')",                                           
                    $matches[3]
                );
    
            return array_combine($matches[1],$matches[3]);
        }
        return array();
    }
    
    /**
     * Gère les blocs <if>...</if><else>...</else> présents dans le template
     * 
     * @param string $source le source à modifier
     */
    private static function handleIfElse(&$source)
    {
        // Gère le if et sa condition
        self::$inPhp=true;
        $source=preg_replace_callback
        (
            '~<if\s+(?:condition|test)\s*=\s*"(.*?)"\s*>~ms',
            array('Template','handleIfElseCallback'),
            $source   
        );
        self::$inPhp=false;
        
        // Gère le reste : </if>, <else>, </else>
        $source=preg_replace
        (
            array
            (
//                '~<if\s+(?:condition|test)\s*=\s*"(.*?)"\s*>~ms', // 1. <if>   
                '~</if>\s*<else>~ms',                           // 2. </if> suivi de <else>
                '~</if>~',                                      // 3. </if> tout seul
                '~</else>~'                                     // 4. </else>
            ),
            array
            (
//                self::PHP_START_TAG . 'if($1){' . self::PHP_END_TAG,  // 1.
                self::PHP_START_TAG . '}else{' . self::PHP_END_TAG,   // 2.
                self::PHP_START_TAG . '}' . self::PHP_END_TAG,        // 3.
                self::PHP_START_TAG . '}' . self::PHP_END_TAG         // 4.
            ),
            $source
        );
    }
    
    /**
     * Callback utilisé par {@link handleIfElse()}
     */
    private static function handleIfElseCallback($matches)
    {
        self::handleFields($matches[1]);     
        return self::PHP_START_TAG . 'if(' . $matches[1] . '){' . self::PHP_END_TAG;
    }    
    
    /** 
     * Gère les boucles <loop>...</loop> présentes dans le template
     * 
     * @param string $source le source à modifier
     */
    private static function handleLoop(&$source)
    {
        $source=preg_replace_callback
        (
            '~<loop([^>]*)>(.*?)</loop>~msx',
            array('Template','handleLoopCallback'),
            $source
        );
    }    
    
    /**
     * Callback utilisé par {@link handleLoop()}
     */
    private static function handleLoopCallback($matches)
    {
        // 1=les attributs du loop, 2=le code entre <loop> et </loop> 
        $t=self::splitAttributes($matches[1]);

        $id=isset($t['id'])         ? $t['id']      : 'loop';
        $on=isset($t['on'])         ? $t['on']      : null;
        $max=isset($t['max'])       ? $t['max']     : null;
        $order=isset($t['order'])   ? $t['order']   : 1;
        
        $code=$matches[2];

        if (! $on)
            throw new Exception("Boucle incorrect, l'attribut 'on' n'a pas été indiqué.");
            
        // Récupère la valeur pour "$on"
/*
        if (strpos($on, '[') === false)
        {
            
        }
        else
        {
            self::$inPhp=true;
            self::handleFields($on);
            self::$inPhp=false;
        }
*/
        $type=strtolower(trim(strtok($on, '(')));
        $object=strtolower(trim(strtok(')')));

        // Récupère la valeur pour "$object"
        if (strpos($object, '[') === false)
        {
        	
        }
        else
        {
            self::$inPhp=true;
            self::handleFields($object);
            self::$inPhp=false;
        }

        // Récupère la valeur pour "order"        
        if (strpos($order, '[') === false)
        {
            $knownOrder=true;
            switch(strtolower(trim($order)))
            {
                case '1': case'asc': case 'true': $order=1; break;
                case '0': case '-1': case'desc': case 'false': $order=-1; break;
                default: throw new Exception('Order inconnu');
            }
        }
        else
        {
            $knownOrder=false;
            self::$inPhp=true;
            self::handleFields($order);
            self::$inPhp=false;
        }
        
        // Récupère la valeur pour "max"
        if (strpos($max, '[') === false)
        {
            $knownMax=true;
        }
        else
        {
            $knownMax=false;
            self::$inPhp=true;
            self::handleFields($max);
            self::$inPhp=false;
        }

        switch($type)
        {
            case 'db':
                if ($knownMax)
                {
                    if ($max)
                    {
                        $maxInit="\$nb=0;\n";
                        $maxCond="    if (\$nb++>=$max) break;\n";
                    }
                    else
                    {
                        $maxInit=$maxCond="";
                    }
                }
                else
                {
                    $maxInit="\$max=$max;\n"
                            ."\$nb=0;\n";
                    $maxCond="    if (\$nb++>=\$max) break;\n";
                }

                // dans le code, remplace directement les champs de la forme [loop.xxx] ou [$id.xxx]
                // par un appel à $table['xxx']
                $code=preg_replace // remplace [array.key], [loop.key]...
                (
                    "/\[(?:loop|array".($id?"|$id":"").")\.(rank|fields|name)\]/", 
                    self::PHP_START_TAG. "echo \$$1" . self::PHP_END_TAG, 
                    $code
                );


                $source=self::PHP_START_TAG . "\n"
                    .   $maxInit
                    .   "foreach(Template::field('$object',null) as \$rank=>\$fields)\n"
                    .   "{"
                    .   $maxCond
                    .   self::PHP_END_TAG
                    .   $code
                    .   self::PHP_START_TAG . "\n"
                    .   "}"
                    .   self::PHP_END_TAG 
                    ;
                break;

        	case 'bis':
                if ($knownOrder)
                {
                    if ($order==1)
                    {
                        $init = "${object}->moveFirst();";
                        $cond = "! ${object}->eof";
                        $next = "${object}->moveNext();";
                    }
                    else
                    {
                        $init = "${object}->moveLast();";
                        $cond = "! ${object}->bof";
                        $next = "${object}->movePrevious();";
                    }
                }
                else
                {
                    $init = "\$order=$order;\n"
                        .   "if (\$order==1) ${object}->moveFirst(); else ${object}->moveLast();";
                        
                    $cond = "! (\$order==1 ? ${object}->eof : ${object}->bof)";
                    
                    $next = "if (\$order==1) ${object}->moveNext(); else ${object}->movePrevious();";
                }    

                if ($knownMax)
                {
                    if ($max)
                    {
                        $maxInit="\$nb=0;\n";
                        $maxCond=" && \$nb<$max";
                        $maxNext="   \$nb++;\n";
                    }
                    else
                    {
                        $maxInit=$maxCond=$maxNext="";
                    }
                }
                else
                {
                    $maxInit="\$max=$max;\n"
                            ."\$nb=0;\n";
                    $maxCond=" && \$nb<\$max";
                    $maxNext="   \$nb++;\n";
                }

                $source=self::PHP_START_TAG . "\n"
                    .   "global $object;\n"
                    .   "$init\n"
                    .   $maxInit
                    .   "while($cond$maxCond)\n"
                    .   "{"
                    .   self::PHP_END_TAG
                    .   $code
                    .   self::PHP_START_TAG . "\n"
                    .   "   $next\n"
                    .   $maxNext
                    .   "}"
                    .   self::PHP_END_TAG 
                    ;
                break;

            case 'array':
                if ($knownOrder)
                {
                    if ($order==1)
                    {
                        $init = "";
                        $cond = $object;
                    }
                    else
                    {
                        $init = "";
                        $cond = "array_reverse($object,true)";
                    }
                }
                else
                {
                    $init = "if ($order==1) \$temp=&$object; else \$temp=array_reverse($object,true);\n";
                    $cond = "\$temp";
                }    

                if ($knownMax)
                {
                    if ($max)
                    {
                        $maxInit="\$nb=0;\n";
                        $maxCond="   if(++\$nb>=$max) break;\n";
                    }
                    else
                    {
                        $maxInit=$maxCond=$maxNext="";
                    }
                }
                else
                {
                    $maxInit="\$max=$max;\n"
                            ."\$nb=0;\n";
                    $maxCond="   if(++\$nb>=\$max) break;\n";
                }

                // dans le code, remplace directement les champs de la forme [loop.xxx] ou [$id.xxx]
                // par un appel à $table['xxx']
                $code=preg_replace // remplace [array.key], [loop.key]...
                (
                    "/\[(?:loop|array".($id?"|$id":"").")\.(key|value)\]/", 
                    self::PHP_START_TAG. "echo \$$1" . self::PHP_END_TAG, 
                    $code
                );

                $code=preg_replace
                (
                    "/\[(?:loop|array".($id?"|$id":"").")\.([A-Za-z0-9_-]+)\]/", 
                    self::PHP_START_TAG. "echo \$value['$1']" . self::PHP_END_TAG, 
                    $code
                );

                $source=self::PHP_START_TAG . "\n"
                    .   "global $object;\n"
                    .   $init
                    .   $maxInit
                    .   "foreach($cond as \$key=>\$value)\n"
                    .   "{"
                    .   self::PHP_END_TAG
                    .   $code
                    .   self::PHP_START_TAG . "\n"
                    .   $maxCond
                    .   "}"
                    .   self::PHP_END_TAG 
                    ;
                break;
                
            case 'table':
                /*
                if ($knownOrder)  PAS GERE POUR LES TABLES
                {
                    if ($order==1)
                    {
                        $init = "${object}->moveFirst();";
                        $cond = "! ${object}->eof";
                        $next = "${object}->moveNext();";
                    }
                    else
                    {
                        $init = "${object}->moveLast();";
                        $cond = "! ${object}->bof";
                        $next = "${object}->movePrevious();";
                    }
                }
                else
                {
                    $init = "\$order=$order;\n"
                        .   "if (\$order==1) ${object}->moveFirst(); else ${object}->moveLast();";
                        
                    $cond = "! (\$order==1 ? ${object}->eof : ${object}->bof)";
                    
                    $next = "if (\$order==1) ${object}->moveNext(); else ${object}->movePrevious();";
                }    
				*/
                
                if ($knownMax)
                {
                    if ($max)
                    {
                        $maxInit="\$nb=0;\n";
                        $maxCond="\$nb<$max && ";
                        $maxNext="   \$nb++;\n";
                    }
                    else
                    {
                        $maxInit=$maxCond=$maxNext="";
                    }
                }
                else
                {
                    $maxInit="\$max=$max;\n"
                            ."\$nb=0;\n";
                    $maxCond="\$nb<\$max && ";
                    $maxNext="   \$nb++;\n";
                }

                // dans le code, remplace directement les champs de la forme [loop.xxx] ou [$id.xxx]
                // par un appel à $table['xxx']
                $code=preg_replace
                (
                    "/\[(?:loop|table".($id?"|$id":"").")\.([A-Za-z0-9-]+)\]/", 
                    self::PHP_START_TAG. 'echo \$table[\'$1\']' . self::PHP_END_TAG, 
                    $code
                );
        
                $source=self::PHP_START_TAG . "\n"
                    .   "Template::walkTable(0,$object);\n"
                    .   $maxInit
                    .   "while($maxCond\$table=Template::walkTable())\n"
                    .   "{"
                    .   self::PHP_END_TAG
                    .   $code
                    .   self::PHP_START_TAG . "\n"
                    .   $maxNext
                    .   "}\n"
                    .   "Template::walkTable(2);\n"
                    .   self::PHP_END_TAG 
                    ;
                break;
                
            default:
                throw new Exception("Type de boucle inconnu : '$type'");
        }
        
        return $source;
//        return "boucle loop, object=$object, type=$type. id=$id, on=$on, max=$max, order=$order, \nsource=\n$source\n\n";
//        self::handleFields($matches[1]);     
//        return self::PHP_START_TAG . 'if(' . $matches[1] . '){' . self::PHP_END_TAG;
    }    

    private static function walkTable($action=1, $source='')
    {
        static $file, $fields, $line;
        
        switch($action)
        {
            case 0: // Initialisation
                // Ajoute l'extension par défaut s'il y a lieu
                Utils::defaultExtension($source, '.txt');
                                
                // Détermine le path exact de la table
                $h=Utils::searchFile
                (
                    $source,                                    // On recherche la table :
                    dirname(self::$stateStack[1]['template']),  // dans le répertoire du script appellant
                    Runtime::$root . 'tables',                  // dans le répertoire 'tables' de l'application
                    Runtime::$fabRoot . 'tables'                // dans le répertoire 'tables du framework
                );
                if (! $h)
                    throw new Exception("Table non trouvée : '$source'");
                
                $file=@fopen($h, 'r');
                    
                $fields=fgetcsv($file, 4096, "\t", '"');
                $line=0;
                break;
                
            case 1: // Lecture d'une ligne de la table
                if ( feof($file) )
                    return false;
            
                $t=fgetcsv($file, 4096, "\t", '"');
                if ($t===false) return false;
            
                $t=array_combine($fields,$t);
                    $t['line']=++$line;
                
                return $t;
                
            case 2: // Fermeture de la table
                fclose($file);
                $file=0;
                return;
                
            default: 
                throw new Exception('Appel incorrect de walkTable');
        }    
    }
    
    /**
     * Gère les blocs <fill>...</fill> présents dans le template
     * 
     * @param string $source le source à modifier
     */
    private static function handleFill(&$source)
    {
        $source=preg_replace_callback
        (
            '~<fill([^>]*)>(.*?)</fill>~msx',
            array('Template', 'handleFillCallback'),
            $source
        );
    }  

    /**
     * Callback utilisé par {@link handleFill()}
     */
    private static function handleFillCallback($matches)
    {
        // 1=les attributs de fill, 2=le code entre <fill> et </fill> 
        $t=self::splitAttributes($matches[1]);
    
        $values=$t['values'];

        if (strpos($values, '[[') !== false)
            return $matches[0];
            
        if (strpos($values, '[') === false)
        {
            $values="'" . addslashes($values) . "'";
        }
        else
        {
            self::$inPhp=true;
            self::handleFields($values);
            self::$inPhp=false;
        }
        
        // TODO: faire pareil pour sep (peut venir d'un champ)
                
        $code=@$t['code'];
        $sep=@$t['separator'];
        $data=$matches[2];
    
        $result=self::PHP_START_TAG . 'ob_start();' . self::PHP_END_TAG;
        
        $result .= $data;
        
        if(!$sep)$sep=',';
        $sep=addslashes($sep);
        $result.=self::PHP_START_TAG . 'Template::fill('.$values.', \''.$code.'\', \''.$sep.'\');' . self::PHP_END_TAG;
        return $result;
    }

    /**
     * Fonction appellée pour "remplir" les listes
     * @internal cette fonction ne doit être appellée que depuis un template.
     * @access public
     */
    public static function fill($values,$code,$sep)
    {
    //    echo "<pre>fillist. values=[$values], code=[$code], sep=[$sep]</pre>";
        $data=ob_get_clean();
        if (is_array($values)) $values=implode($sep,$values);
        $values=preg_quote($values,'~');
        $sep=preg_quote($sep,'~');
        $values=preg_replace('~\s*'.$sep.'\s*~', '|', $values);
        //echo "values=$values\n";
    //    $values=str_replace(',', '|',$values);
        $re='~(value=([\'"])(?:' . $values . ')\2)~';
        
        //echo "sep=$sep re=$re\n";
        $data=preg_replace(
            $re,
            '$1 '.$code,
            $data);
            
        echo $data;
    }
    
    /**
     * Gère les blocs <tableofcontents>...</tableofcontents> présents dans le template
     * 
     * @param string $source le source à modifier
     */
    private static function handleTableOfContents(&$source)
    {
        $source=preg_replace
        (
            array
            (
                '~<tableofcontents\s*>~ms',              // 1. début de toc   
                '~</tableofcontents>~ms',                // 2. fin de toc
            ),
            array
            (
                self::PHP_START_TAG . 'start_toc()' . self::PHP_END_TAG,        // 1.
                self::PHP_START_TAG . 'end_toc()' . self::PHP_END_TAG,         // 2.
            ),
            $source
        );
    }
    
    /**
     * Gère les blocs <opt>...</opt>
     * 
     * @param string $source le source à modifier
     */
    private static function handleOpt(&$source)
    {
        // URGENT: voir si le IF a le même problème de récursivité
        // TODO: voir si d'autres fonctions peuvent être optimisées pareil (suppression des callbacks de preg_replace)
        
        // Découpe la chaine au premier <opt> trouvé.
        // Ne recherche pas <option> présent dans les select
        //$t=preg_split('~<opt([^>]*)>~msx', $source, 2, PREG_SPLIT_DELIM_CAPTURE);
        $t=preg_split('~<opt([^o>]*)>~msx', $source, 2, PREG_SPLIT_DELIM_CAPTURE);
        // en 0 : ce qui précède, en 1 : les attributs, en 2 : ce qui suit le <opt>

        // Si le source ne contient aucun <opt>, terminé
        if (count($t)<2) return;
        
        // Récursive : gère les sous-blocs <opt></opt> inclus
        self::handleOpt($t[2]);
    
        // Récupère la valeur de min si indiquée
        $attributes=self::splitAttributes($t[1]);
        if (isset($attributes['min']))
            $min=$attributes['min'];
        else
            $min='';
    
        // Recherche le </opt>
        $pt = strpos($t[2], '</opt>');
        if ($pt === false) die(htmlentities('</opt> non trouvé !'));
        
        // Construit le résultat
        $source = $t[0]; // ce qui précède
        $source.= self::PHP_START_TAG . 'Template::optBegin();' .self::PHP_END_TAG;
        $source.= substr_replace
        (
            $t[2], 
            self::PHP_START_TAG . "Template::optEnd($min);" .self::PHP_END_TAG, 
            $pt, 
            strlen('</opt>')
        );
    }
    
//    private static function handleOpt(&$source)
//    {
//        /*
//        $source=preg_replace
//            (
//                array
//                (
//                    '~<opt([^>]*)>~ms',                    //  2
//                    '~</opt>~ms',                   //  3
//                ),
//                array
//                (
//                    self::PHP_START_TAG . "Template::optBegin();" .self::PHP_END_TAG, //  2
//                    self::PHP_START_TAG . "Template::optEnd();" .self::PHP_END_TAG,   //  3
//                ),                        
//                $source
//            );
//        */
//        echo "<h1>Source avant</h1>";
//        highlight_string($source);
//        $source=preg_replace_callback
//        (
//            '~<opt([^>]*)>(.*?)</opt>~msx',
//            array('Template', 'handleOptCallback'),
//            $source
//        );
//        echo "<h1>Source après</h1>";
//        highlight_string($source);
//    //die();
//    }

//    /**
//     * Callback utilisé par {@link handleFill()}
//     */
//    private static function handleOptCallback($matches)
//    {
//        // 1=les attributs de opt, 2=le code entre <opt> et </opt> 
//        $t=self::splitAttributes($matches[1]);
//        
//        if (isset($t['min']))
//            $min=$t['min'];
//        else
//            $min='';
//
//        return
//            self::PHP_START_TAG . 'Template::optBegin();' .self::PHP_END_TAG
//            .
//            $matches[2]
//            .
//            self::PHP_START_TAG . "Template::optEnd($min);" .self::PHP_END_TAG;
//                    	
//    }
    
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
    
    private static function hashPhpBlocks($matches)
    {
        $text = $matches[1];
        $text=self::PHP_START_TAG . $text . self::PHP_END_TAG;
        $key = md5($text);
        self::$phpBlocks[$key] = $text;
        return $key; 
    }
    
    /**
     * Gère les balises de champs [xxx]
     * 
     * @param string $source le source à modifier
     */
    private static function handleFields(&$source)
    {
        // Protège les blocs PHP générés par les autres balises
        $source = preg_replace_callback
            (
                '~<\?php\s(.*?)\s\?>~ms',
                array('Template','hashPhpBlocks'),
                $source
            );

        // Convertit toutes les balises de champs
        $source=preg_replace_callback
            (
                '~
                \[                  # crochet ouvrant
                (                   # $1=nom du tag
                    [^\[\]]*           # tout caractère sauf crochet ouvrant
                )
                \]                  # crochet fermant
                ~xm',
                array('Template', 'handleFieldsCallback'),
                $source
            );
        // Restaure les blocs PHP
        $source=str_replace(array_keys(self::$phpBlocks),array_values(self::$phpBlocks), $source);
        //unset(self::$phpBlocks);
            
    }
    
    /**
     * Callback utilisé par {@link handleFields()}
     */
    private static function handleFieldsCallback($matches)
    {
        // $matches[1] contient ce qu'il y a entre crochets. exemple : titeng,titfre,'aucun titre'
        $string=$matches[1];

        // Sépare la valeur par défaut du reste
        $default='null';
        if ($nb=preg_match_all('~(["\']).*?(?<!\\\\)\1~', $string, $t,PREG_OFFSET_CAPTURE))
        {
            if ($nb>1)            
                throw new Exception(sprintf("Une balise de champ ne peut contenir " .
                        "qu'une seule valeur par défaut : %s", $string));
            
            $default=$t[0][0][0];
            $string=substr($string, 0, $t[0][0][1]);
        }

        $h='';
        
        foreach(preg_split('~\s*[,:]\s*~', $string) as $i=>$field)
        {
            if ($field) 
            {
                if (strlen($h)) $h.=' or ';
                $source=self::fieldSource($field);
                $h.= '$x=' . ($source ? $source :"'[Champ inconnu : $field]'");	
            }
        }
        $h='(' . $h . ') ? Template::filled($x) : ' . $default;

        if (!self::$inPhp) 
            $h = self::PHP_START_TAG . 'echo ' . $h . self::PHP_END_TAG;
        else
            $h = '(' . $h . ')';
        
        return $h;                                           
    }
    public static function fieldSource($name) // TODO: repasser private
    {
        debug && Debug::log('%s', $name);
        
        // Parcours toutes les sources de données
        foreach (self::$data as $i=>$data)
        {
            // Objet
            if (is_object($data))
            {
                // Propriété d'un objet
                if (property_exists($data, $name))
                {
                    debug && Debug::log('C\'est une propriété de l\'objet %s', get_class($data));
                    return 'Template::$data['.$i.']->'.$name;
                }
                
                // Clé d'un objet ArrayAccess
                if ($data instanceof ArrayAccess)
                {
                    try
                    {
                        debug && Debug::log('Tentative d\'accès à %s[\'%s\']', get_class($data), $name);
                        $value=$data[$name];
                        return 'Template::$data['.$i.'][\''.$name.'\']';
                    }
                    catch(Exception $e)
                    {
                        debug && Debug::log('Génère une erreur %s', $e->getMessage());
                    }
                }
                else
                    debug && Debug::log('Ce n\'est pas une clé de l\'objet %s', get_class($data));
            }

            // Clé d'un tableau de données
            if (is_array($data) && array_key_exists($name, $data)) 
            {
                debug && Debug::log('C\'est une clé du tableau de données');
                $value=$data[$name];  
                return 'Template::$data['.$i.'][\''.$name.'\']';
            }

            // Fonction de callback
            if (is_callable($data))
            {
                $value=@call_user_func($data, $name);

                // Si la fonction retourne autre chose que "null", terminé
                if ( ! is_null($value) )
                    return 'call_user_func(Template::$data['.$i.'], \''.$name.'\')';
            }
            
            //echo('Datasource incorrecte : <pre>'.print_r($data, true). '</pre>');
        }
        //echo('Aucune source ne connait <pre>'. $name.'</pre>');
        return '';
    }
    private static function & filled(&$x)
    {
        //if ($x != '') 
        self::$optFilled[self::$optLevel]++;
    	return $x;
    }
    
    private static function handleFieldsCallbackOld($matches)
    {
        // $matches[1] contient ce qu'il y a entre crochets. exemple : titeng,titfre,'aucun titre'
        $string=$matches[1];

        // Sépare la valeur par défaut du reste
        $default='null';
        if ($nb=preg_match_all('~(["\']).*?(?<!\\\\)\1~', $string, $t,PREG_OFFSET_CAPTURE))
        {
            if ($nb>1)            
                throw new Exception(sprintf("Une balise de champ ne peut contenir " .
                        "qu'une seule valeur par défaut : %s", $string));
            
            $default=$t[0][0][0];
            $string=substr($string, 0, $t[0][0][1]);
        }

        if (self::$inPhp)
            $h="Template::field(";
        else
            $h=self::PHP_START_TAG . "echo Template::field(";
            
        foreach(preg_split('~\s*[,:]\s*~', $string) as $i=>$field)
        {
            if ($field) $h.= "'$field',";
        }
        $h.= "$default)";
        if (!self::$inPhp) $h .= self::PHP_END_TAG;
        return $h;                                           
    }
    
    public static function field()
    {
        // Pour le moment, on ne connaît pas ce champ
        $value=null;
        
        // Parcours tous les champs indiqués en paramètre. Le dernier est la valeur par défaut
        $nb=func_num_args();
        if (debug)
        {
            $args=func_get_args();
            if (is_null(end($args))) array_pop($args);  
            debug && Debug::log('['.implode(':', $args).']');
        }
        for ($i=0; $i<$nb-1; $i++)
        {
/*
on appelle template::run avec :
- 0: un objet qui retourne qq chose pour titObjProp (via une propriété)
- 1: un objet qui retourne qq chose pour titObjKey (l'objet implémente ArrayAccess et a la clé)
- 2: un callback qui retourne qq chose pour 'titCbk'
- 3: un tableau de valeur qui contient qq chose pour la clé 'titArr'

dans le template, on a :
[titObjProp:titObjKey:titCbk:titArr:'pas de titre']

actuellement, on compile en :
echo Template::field('titObjProp','titObjKey','titCbk','titArr', 'pas de titre')
ce qui génère : une boucle sur chacun des arguments X une boucle sur chaque datasource

early binding. La liaison est faite lors de la compilation, on génère :
echo Template::$data[0]->titObjProp
    Template::$data[1]['titObjKey']
    Template::$data[2]('titCbk')
    Template::$data[3]['titArr']


echo $var=x ? $var 
*/     

//echo Template::$data[1]('titre');

            // Récupère le nom du champ recherché
            $name=func_get_arg($i);
            debug && Debug::log('%s', $name);
            
            // Parcours toutes les sources de données
            foreach (self::$data as $data)
            {
                // Objet
                if (is_object($data))
                {
                    // Propriété d'un objet
                    if (property_exists($data, $name))
                    {
                        debug && Debug::log('C\'est une propriété de l\'objet %s', get_class($data));
                        $value=$data->$name;
                        if ( $value !== '') 
                        {   
                            debug && Debug::tplLog
                            (
                                '['.implode(':', $args).']',
                                $value,
                                "%s->%s",
                                get_class($data),
                                $name
                            );
                            
                            self::$optFilled[self::$optLevel]++;
                            debug && Debug::log('Valeur non vide obtenue, terminé');
                            return $value;
                        }
                        debug && Debug::log('A retourné une chaine vide');
                        break;
                    }
                    else
                        debug && Debug::log('Ce n\'est pas une propriété de l\'objet %s', get_class($data));
                    
                    // Clé d'un objet ArrayAccess
                    if ($data instanceof ArrayAccess)
                    {
                        try
                        {
                            debug && Debug::log('Tentative d\'accès à %s[\'%s\']', get_class($data), $name);
                            $value=$data[$name];
                            if ( $value !== '') 
                            {   
                                debug && Debug::tplLog
                                (
                                    '['.implode(':', $args).']',
                                    $value,
                                    "%s['%s']",
                                    get_class($data),
                                    $name
                                );
                                self::$optFilled[self::$optLevel]++;
                                debug && Debug::log('Valeur non vide obtenue, terminé');
                                return $value;
                            }
                            debug && Debug::log('Pas d\'erreur mais a retourné une chaine vide');
                        }
                        catch(Exception $e)
                        {
                            debug && Debug::log('Génère une erreur %s', $e->getMessage());
                            
                        }
                    }
                    else
                        debug && Debug::log('Ce n\'est pas une clé de l\'objet %s', get_class($data));
                }
//                echo 'name=', $name, ', is_array(data)', is_array($data), ', dump data=', print_r($data,true) ,'<br />';
                // Clé d'un tableau de données
                if (is_array($data) && array_key_exists($name, $data)) 
                {
                    debug && Debug::log('C\'est une clé du tableau de données');
                    $value=$data[$name];  
                    if ( $value !== '') 
                    {   
                        debug && Debug::tplLog
                        (
                            '['.implode(':', $args).']',
                            $value,
                            "\$data['%s']",
                            $name
                        );
    
                        self::$optFilled[self::$optLevel]++;
                        debug && Debug::log('Valeur non vide obtenue, terminé');
                        return $value;
                    }
                    debug && Debug::log('Valeur vide obtenue, terminé');
                    break;
                }

                // Fonction de callback
                if (is_callable($data))
                {
                    $value=@call_user_func($data, $name);
    
                    // Si la fonction retourne autre chose que "null", terminé
                    if ( ! is_null($value) )
                    {
                        if ( $value !== '') 
                        {   
                            debug && Debug::tplLog
                            (
                                '['.implode(':', $args).']',
                                $value,
                                "%s::%s('%s')",
                                is_string($data[0]) ? $data[0] : get_class($data[0]),
                                $data[1],
                                $name
                            );
                            self::$optFilled[self::$optLevel]++;
                            return $value;
                        }
                        break;
                    }
                }
            }   // source de données
        }   // paramètres
        
        // Retourne la valeur par défaut si on en a une
        $default=func_get_arg($nb-1);
        if (! is_null($default))
        {
            debug && Debug::tplLog
            (
                '['.implode(':', $args).']',
                $value,
                "valeur par défaut indiquée dans le template"
            );
            if ($default) self::$optFilled[self::$optLevel]++;
            return $default;
        }
        
        // Retourne la balise littérale sinon
        if (! is_null($value)) 
        {
            debug && Debug::tplLog
            (
                '['.implode(':', $args).']',
                $value,
                "A ETUDIER"
            );
            return $value;  
        }

        self::$optFilled[self::$optLevel]++;
        $t=func_get_args();
        array_pop($t);
        
        $value='[' . join($t,':') . ']';

        debug && Debug::tplLog
        (
            '['.implode(':', $args).']',
            $value,
            "balise littérale, aucune source ne connaît"
        );
        
        return $value ; //"[$name]";

        
    }
        
    /**
     * Fonction appellée pour déterminer la valeur d'un champ de template
     * @internal cette fonction ne doit être appellée que depuis un template.
     * @access public
     */
    public static function oldfield()
    {
        $value=null;
        $nb=func_num_args();
        if (debug)
        {
            $args=func_get_args();
            if (is_null(end($args))) array_pop($args);	
        }
        for ($i=0; $i<$nb-1; $i++)
        {
     
            $name=func_get_arg($i);
       
            // Teste s'il s'agit d'une variable
            if (array_key_exists($name, self::$dataVars)) 
            {
                $value=self::$dataVars[$name];	
                if ( $value !== '') 
                {   
                    debug && Debug::tplLog
                    (
                        '['.implode(':', $args).']',
                        $value,
                        "\$data['%s']",
                        $name
                    );

                    self::$optFilled[self::$optLevel]++;
                    return $value;
                }
                break;
            }
            
            // Teste s'il s'agit d'une propriété d'objet
            foreach (self::$dataObjects as $j=>$object)
            {
                if ($object instanceof ArrayAccess)
                {
                    try
                    {
                        $value=$object[$name];
                        if ( $value !== '') 
                        {   
                            debug && Debug::tplLog
                            (
                                '['.implode(':', $args).']',
                                $value,
                                "%s['%s']",
                                get_class($object),
                                $name
                            );
                            self::$optFilled[self::$optLevel]++;
                            return $value;
                        }
                    }
                    catch(Exception $e)
                    {
                    	
                    }
                }
                
                if (property_exists($object, $name))
                {
                	$value=$object->$name;
                    if ( $value !== '') 
                    {   
                        debug && Debug::tplLog
                        (
                            '['.implode(':', $args).']',
                            $value,
                            "%s->%s",
                            get_class($object),
                            $name
                        );
                        
                        self::$optFilled[self::$optLevel]++;
                        return $value;
                    }
                    break;
                }	
            }
            
            // Exécute tous les callbacks un par un
            foreach(self::$dataCallbacks as $callback)
            {
                $value=call_user_func($callback, $name);

                // Si la fonction retourne autre chose que "null", terminé
                if ( ! is_null($value) )
                {
            	   /*
                    if ( is_array($value) )
                    {
                        $value=array_filter($value);
                        $value=implode(SEPARATOR, $value);               
                    }
    				*/
                    if ( $value !== '') 
                    {   
                        debug && Debug::tplLog
                        (
                            '['.implode(':', $args).']',
                            $value,
                            "%s::%s('%s')",
                            is_string($callback[0]) ? $callback[0] : get_class($callback[0]),
                            $callback[1],
                            $name
                        );
                        self::$optFilled[self::$optLevel]++;
                        return $value;
                    }
                    break;
                }
            }
        }
        
        // Retourne la valeur par défaut si on en a une
        $default=func_get_arg($nb-1);
        if (! is_null($default))
        {
            debug && Debug::tplLog
            (
                '['.implode(':', $args).']',
                $value,
                "valeur par défaut indiquée dans le template"
            );
            if ($default) self::$optFilled[self::$optLevel]++;
        	return $default;
        }
        
        // Retourne la balise littérale sinon
        if (! is_null($value)) 
        {
            debug && Debug::tplLog
            (
                '['.implode(':', $args).']',
                $value,
                "A ETUDIER"
            );
            return $value;	
        }

        self::$optFilled[self::$optLevel]++;
        $t=func_get_args();
        array_pop($t);
        
        $value='[' . join($t,':') . ']';

        debug && Debug::tplLog
        (
            '['.implode(':', $args).']',
            $value,
            "balise littérale, aucune source ne connaît"
        );
        
        return $value ; //"[$name]";
    }    
    
    /**
     * Convertit les liens présents dans les templates en fonction des routes
     * 
     * @param string $source le source à modifier
     */
    private static function handleLinks(&$source)
    {
        $source=preg_replace_callback
            (
                '~
                (                           # $1 : tout ce qui précède la quote ouvrante
                    \<(?:a|frame|iframe|img|form)    # un tag html pouvant contenir un attribut href ou src
                    .+?                     # quelque chose, au moins un blanc
                    (?:href|src|action)            # nom de l\'attribut
                    \s*=\s*                 # =
                )                           #
                ("|\')                      # $2 : quote ouvrante
                (/.*?)                       # $3 : le lien (uniquement ceux qui commencent par slash)
                \2                          # quote fermante
                ~xm',
                array('Template', 'handleLinksCallback'),
                $source
            );
    }
    
    /**
     * Callback utilisé par {@link handleLinks()}
     */
    private static function handleLinksCallback($matches)
    {
//echo "<li>HLC $matches[3]</li>";
        // $1 : tout ce qui précède la quote ouvrante, $2 : la quote, $3 : le lien
        $link=$matches[3];

//        if (strpos($link, '[') !== false)
//        {
//            self::$inPhp=true;
//            self::handleFields($link);
//            self::$inPhp=false;
//        }

        self::$inPhp=true;
        $t=preg_split('~\[|\]~', $link);
        foreach($t as $i=>$h)
        {
            if ($i % 2==0) // pair : c'est du texte
            {
                //if ($t[$i]) // TODO : on a des . '' en trop 
                $t[$i]="'" . addslashes($t[$i]) . "'";
            }
            else
            {
                $t[$i]='['.$t[$i].']';
                self::handleFields($t[$i]);
            }
        }
        self::$inPhp=false;
        $link=implode('.',$t);
//        $link=print_r($t,true);
//        $link=self::PHP_START_TAG . 'echo Routing::linkFor("' . addslashes($link) . '")' . self::PHP_END_TAG;
        $link=self::PHP_START_TAG . 'echo Routing::linkFor(' . $link . ')' . self::PHP_END_TAG;
        
        return $matches[1] . $matches[2] . $link . $matches[2];                                           
    }
        
    private static function handleInclude(&$source)
    {
        // Gère la balise <include file="xxx" />
        $source=preg_replace_callback
        (
            '~<include\s+file\s*=\s*"(.*?)"\s*/>~ms',
            array('Template','handleIncludeCallback'),
            $source   
        );
    }
    
    /**
     * Callback utilisé par {@link handleInclude()}
     */
    private static function handleIncludeCallback($matches)
    {
        $h=$matches[1];
//        if (strpos($matches[1], '[') === false)
//        {
//            $matches[1]='\'' . addslashes($matches[1]) . '\'';            
//        }
//        else
//        {
//            self::$inPhp=true;
//            self::handleFields($matches[1]);
//            self::$inPhp=false;
//        }
        $h=addslashes($h);
        $h=str_replace('[', '\'.[', $h);
        $h=str_replace(']', '].\'', $h);
        self::$inPhp=true;
        self::handleFields($h);
        self::$inPhp=false;
        $h="'$h'";
        
        //self::handleFields($matches[1]);     
        return self::PHP_START_TAG . 'Template::run(' . $h . ')' . self::PHP_END_TAG;
    }    

    /**
     * Enregistre l'état du gestionnaire de template. 
     * Utilisé pour permettre à {@link run()} d'être réentrant
     */
    private static function saveState()
    {
//        array_push(self::$stateStack, array(self::$template,self::$callbacks,self::$genItem));
        array_push
        (
            self::$stateStack, 
            array
            (
                'template'      => self::$template,
                'dataVars'      => self::$dataVars,
                'dataObjects'   => self::$dataObjects,
                'dataCallbacks' => self::$dataCallbacks,
                'genItem'       => self::$genItem,
                'data'          => self::$data
            )
        );
    }

    /**
     * Restaure l'état du gestionnaire de template.
     * Utilisé pour permettre à {@link run()} d'être réentrant
     */
    private static function restoreState()
    {
//        list(self::$template, self::$callbacks,self::$genItem)=array_pop(self::$stateStack);
        $t=array_pop(self::$stateStack);
        self::$template         =$t['template'];
        self::$dataVars         =$t['dataVars'];
        self::$dataObjects      =$t['dataObjects'];
        self::$dataCallbacks    =$t['dataCallbacks'];
        self::$genItem          =$t['genItem'];
        self::$data             =$t['data'];
    }
    
    /** 
     * selectionCallback
     * 
     * Callback standard pour les templates. Recherche la variable demandée
     * dans les champs de la sélection en cours, si elle existe, et s'il y a un
     * enregistrement courant.
     */
    public static function selectionCallback($name)
    {
        global $selection;
        
        if (isset($selection) && $selection->count)
        {   try
            {
                $value=$selection->field($name);
                if (is_null($value)) return '';
                return $value;
            }
            catch (Exception $e)
            {
                return null;
            }
        }
        return null; // aucune notice en cours
    }

    /** 
     * varCallback
     * 
     * Callback standard pour les templates. Recherche la variable demandée
     * dans les variables globales existantes.
     */
    public static function varCallback($name)
    {
        if ( isset($GLOBALS[$name]) )
            return $GLOBALS[$name];
    }
    
    /** 
     * postCallback
     * 
     * Callback standard pour les templates. Recherche la variable demandée
     * dans $_POST (données de formulaire transmises en méthode post)
     */
    public static function postCallback($name)
    {
        if ( isset($_POST[$name]) ) return $_POST[$name];
    }
    
    /**
     * getCallback
     * 
     * Callback standard pour les templates. Recherche la variable demandée
     * dans $_GET (query_string)
     */
    public static function getCallback($name)
    {
        if ( isset($_GET[$name]) ) return $value=$_GET[$name];
    }
    
    /** 
     * requestCallback
     * 
     * Callback standard pour les templates. Recherche la variable demandée
     * dans $_REQUEST (données provenant des cookies, de la query_string et
     * des données de formulaires en méthode post).
     */
    public static function requestCallback($name)
    {
        if ( isset($_REQUEST[$name]) ) return $_REQUEST[$name];
    }
    
    /** 
     * emptyCallback
     * 
     * Callback standard pour les templates. Retourne une chaine
     * vide quelle que soit la variable demandée
     */
    public static function emptyCallback($name)
    {
        return '';
    }

    public static function configCallback($name)
    {
        require_once('Config.php');
    	return Config::get($name);
    }
}
?>
<style>
body
{
    margin: 0;
    padding: 0;
}
.scrollbox
{
    height: 33.3%; 
    width: 100%; 
    font-family: monospace; 
    overflow: scroll; 
/*    border: 1px solid red;*/
    white-space: nowrap;
/*    float: left;*/
    background-color: rgb(250,250,250);
}
pre
{
    clear: both;
    border: 1px solid red;
}
</style>
<script type='text/javascript'>
    function sync(id1,id2)
    {
        var div1=document.getElementById(id1);
        var div2=document.getElementById(id2);
        
        if(div2.scrollTop!=div1.scrollTop) 
            div2.scrollTop=div1.scrollTop;
        if(div2.scrollLeft!=div1.scrollLeft) 
            div2.scrollLeft=div1.scrollLeft;
    }
</script>
<?php

class highlight_html
{
 private $color_base = '#000000'; //Couleur du texte hors-balises
 private $color_coms = '#008000'; //Couleur des commentaires et scripts
 private $color_tags = '#000099'; //Couleur des tages (a, div, span, img, table...)
 private $color_dels = '#000099'; //Couleur des delimiteurs < et >
 private $color_atts = '#FF9900'; //Couleur des attributs (href, src, class, style...)
 private $color_vals = '#0000FF'; //Couleur des valeurs d'attributs
 
 private $option_egal = false; //Défini si le = prend la couleur de l'attribut, et les " la couleur de la valeur
 private $option_nl2br = TRUE; //Défini si les retours de lignes doivent être remplacés par des <br />
 
  function color_tags($cod)
  {
   $mask = "#([a-zA-Z0-1\-_:]+)=(('|\")|)(.*?)(?(3)(\\3)|( |>))#si";
if (!$this->option_egal) $repl = "<span style='color: {$this->color_atts}'>\\1</span>=\\3<span style='color: {$this->color_vals}'>\\4</span>\\3\\6";
if ($this->option_egal) $repl = "<span style='color: {$this->color_atts}'>\\1=</span><span style='color: {$this->color_vals}'>\\3\\4\\3</span>\\6";
return preg_replace($mask,$repl,$cod);
}
 
function int_html($match)
{
$bals = array('script','style');
if (empty($match[2])) $match[2] = '';
$ends = "<span style='color: {$this->color_base}'>";
return "</span><span style='color: {$this->color_dels}; font-weight: bold'>&lt;</span><span style='color: {$this->color_tags}; font-weight: bold'>$match[1]</span>".substr($this->color_tags($match[2]),0,-1)."<span style='color: {$this->color_dels}; font-weight: bold'>&gt;</span>".$ends;
}
 
function color_html($code)
{$code = str_replace('<','&lt;',$code);
$code = str_replace('&lt;!--',"</span><span style='color: {$this->color_coms}'>&lt;!--",$code);
$code = "<span style='color: {$this->color_base}'>".preg_replace_callback("#&lt;([/a-zA-Z0-9!\?:_-]+)(( [^>]*)?>)#si",array(&$this,'int_html'),$code)."</span>";
   if ($this->option_nl2br) $code = nl2br($code);
   return $code;
  }
}

$h="d:/webapache/fab/xml/test.html";
$high = new highlight_html; //Nouvelle instance de la class
echo '<div id="div1" class="scrollbox" onscroll="sync(\'div1\',\'div2\')">' . $high->color_html(file_get_contents($h)) . '</div>';
ob_start();
$h=Template::run
(
    $h,
    array
    (
        'titoriga'=>'Titre de niveau analytique',
        'titorigm'=>'Titre monographique',
        'title'=>'',//'Tests avec les templates',
        'prix1'=>12.05,
        'prix2'=>23.40,
        'prix3'=>40.12,
        'class'=>'menu',
        'link'=>'http://www.bdsp.tm.fr/',
        'time'=>'20h40',
        'content'=>'content pas encore géré',
        'key'=>'ceci est ma clé',
        'value'=>'ceci est ma valeur',
        't'=>array('un','deux','trois')
    )
);
$h=ob_get_clean();
echo '<div id="div2" class="scrollbox" onscroll="sync(\'div2\',\'div1\')">' . $high->color_html(Template::$csource) . '</div>';
echo '<div id="div2" class="scrollbox" onscroll="sync(\'div2\',\'div1\')">' . $high->color_html($h) . '</div>';
die();
      
//$xml_parser = new xml();
//$h='<html><head></head><body><h1 opt="true" style="color: red;">Titre<br />sous-titre</h1></body></html>';
//$h='<a href="toto">fd<!-- commentaire--></a><b>gras</b>';
//$h='<b style="class:toto">gras<i>grasita</i></b>';
//$h=file_get_contents("d:/webapache/asco/themes/asco/demodm.htm");
//$h=file_get_contents("d:/webapache/fab/xml/test1.xml");
//$h=file_get_contents("d:/webapache/fab/xml/test2.xml");
//$h=file_get_contents("d:/webapache/fab/xml/test3.xml");
//$h=file_get_contents("d:/webapache/fab/xml/test4.xml");
$h=file_get_contents("d:/webapache/fab/xml/test.html");
//$h=file_get_contents("d:/webapache/asco/modules/BaseTest/templates/export/commun.txt");
//$h=file_get_contents("d:/webapache/fab/NU/fab/runtime1.htm");
//$h=file_get_contents("d:/webapache/fab/templates/NotFound.htm");


//$h='date : [date] $date, titre : [titoriga:titorigm:\'pas de titre\']{$titoriga:$titorigm:\'pas de titre\'}, typdoc : {upper($typdoc):DEF_TITLE}retour
//<div class="$class" /> <include file="/templates/$file.htm" />
//';
//$h='{$title:$autre}';
//$h=TemplateCompiler::compileField($h, false);
//echo '<hr />', htmlentities($h);
//die();
//

$high = new highlight_html; //Nouvelle instance de la class

echo '<div id="div1" class="scrollbox" onscroll="sync(\'div1\',\'div2\')">' . $high->color_html($h) . '</div>';
ob_start();
$h=TemplateCompiler::compile($h);
ob_end_clean();
echo "here";
echo '<div id="div2" class="scrollbox" onscroll="sync(\'div2\',\'div1\')">' . $high->color_html($h) . '</div>';
/*echo '<pre>';
eval('?>' . $h . '<?php ');
echo '</pre>';
*/
die();


function dumpNodes($nodes)
{
  $output = '';
  
  foreach ($nodes as $node)
    $output .= dumpNode($node) . '<hr />';
  return $output;
}
function dumpNode($node)
{
    return htmlentities($node->ownerDocument->saveXML($node));
}


?>