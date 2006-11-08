<?php
/**
 * @package     fab
 * @subpackage  template
 * @author 		Daniel M�nard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id: Template.php 105 2006-09-21 16:30:25Z dmenard $
 */

/**
 * Gestionnaire de templates
 * 
 * Un template est un fichier texte qui contient des balises. Assez souvent,
 * il s'agit d'un fichier html (soit complet, soit juste un fragment).
 * 
 * Il existe deux sortes de balises : les balises de champs ([xxx]) et les
 * balises de contr�les qui permettent de faire des boucles, des conditions,
 * etc.
 * 
 * [DONE] - Commentaires. Vous pouvez inclure des commentaires dans votre
 * template. Syntaxe : /* commentaire *\/
 * Les commentaires sont automatiquement supprim�s dans la version compil�e
 * du template.
 * 
 * Remarque : si votre template contient du code html , vous pouvez
 * �galement utiliser les commentaires html standards : <!-- commentaire -->.
 * Ils seront �galement supprim�s.
 * 
 * 
 * [DONE]- balises de champs : il s'agit d'un identifiant quelconque que vous
 * indiquez entre crochets. Exemple : [titre]. Lors de l'ex�cution, la balise de
 * champ sera remplac�e dans le template par la valeur retourn�e par la fonction
 * de callback.
 * 
 * [DONE] - il est possible, au sein d'une balise de champ, d'indiquer plusieurs
 * champs et �ventuellement une valeur litt�rale par d�faut, en les s�parant par
 * une virgule.
 * 
 * Exemple : [TitreFRE,TitreEng,'Document sans titre'] 
 * 
 * Dans ce cas, la fonction callback sera appell�e pour chacun des champs
 * jusqu'� ce qu'une valeur non vide soit retourn�e. Si aucune valeur n'est
 * obtenue, la valeur par d�faut sera utilis�e.
 * La valeur litt�rale peut �tre indiqu�e entre guillemets simples ou doubles.
 * Pour include un guillemet dans la chaine, le faire pr�c�der par un antislash
 * (m�me syntaxe que les chaines litt�rales en PHP).
 *  
 * Exemple : "l'astuce du \"jour\"", 'l\'astuce du "jour"'
 * 
 * 
 * [DONE] - Blocs optionnels. Vous pouvez encadrer une partie de votre template
 * par des balises <opt> et </opt> pour indiquer qu'il s'agit d'un bloc
 * optionnel. La signification d'un tel bloc est la suivante : si le bloc
 * contient des balises de champ et qu'au moins une de ces balises de champs
 * retourne une valeur non vide lors de l'ex�cution du template, alors ce bloc
 * sera affich�, sinon l'int�gralit� du bloc sera supprim�e.
 * Nb : peut importe ou se trouve les balises (balise simple, condition d'un
 * if, etc.) : elles sont toutes prises en compte.
 * Nb2 : le cot� optionnel d'un bloc <opt>...</opt> ne concerne que l'affichage.
 * Si vous faites des traitements dans votre callback, ceux-ci seront toujours
 * ex�cut�s, que le bloc soit au final affich� ou non.
 * 
 * 
 * [DONE] - Un template peut �galement contenir du code php, bien que ceci ne
 * soit pas conseill�. Encadrer le code php avec les balises habituelles (<?php
 * et ?>). Le code sera ex�cut� lors de l'ex�cution du template, et non pas lors
 * de sa compilation. [TODO] : faut-il un moyen d'ex�cuter du code au moment de
 * la compilation ? (exemple : boucle sur les prix �ditions ensp)
 * 
 * 
 * [DONE ]- Conditions. Vous pouvez utiliser dans votre template des blocs 
 * <if test="">...</if> et <else>...</else> pour afficher certaines parties
 * en fonction du r�sultat de l'�valuation d'une condition.
 * Dans l'attribut test, vous devez indiquer la condition qui sera �valu�e. Il
 * doit s'agir d'une expression PHP valide.
 * 
 * Exemple : 
 * <if test="hasAccess('admin')">Acc�s</if><else>Acc�s refus�</else>
 * 
 * [DONE] - Vous pouvez �galement utiliser des balises de champs [xxx] pour
 * exprimer la condition.
 * 
 * Nb pas ins�rer de balises de champs dans des chaines de caract�res, cela ne
 * fonctionnera pas.
 * 
 * Exemples : 
 * <if test='true'   "[user]=='admin'">Acc�s</if><else>Acc�s refus�</else>
 * <if test="isAdmin([user])">Acc�s</if><else>Acc�s refus�</else>
 * 
 * Provoquera  une erreur :
 * <if test="'[user]'=='admin'">Acc�s</if><else>Acc�s refus�</else>
 *
 * [DONE] - Boucles <loop>...</loop> syntaxe : <loop on="xxx" max="yyy" order="
 * zzz">code</loop> xxx peut-�tre : - bis($s) o� $s est une variable globale
 * contenant une s�lection bis - array($t) o� $t est une variable globale
 * contenant un tableau php - table(xxx) o� xxx est le nom d'une table (fichier
 * texte tabul�). La table est recherch� dans le m�me r�pertoire que le template
 * et dans le r�pertoire $root/tables [� mettre dans la config]
 * 
 * 
 * [INUTILE] - Walk
 * 
 * [DONE] - Fill
 * 
 * [DONE] - Templates g�n�r�s automatiquement � partir d'un fichier Yaml
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
     * @var boolean Indique s'il faut ou non forcer une recompilation compl�te
     * des templates, que ceux-ci aient �t� modifi�s ou non.
     * @access public 
     */
    public static $forceCompile=false;

    /**
     * @var boolean Indique si le gestionnaire de template doit v�rifier ou
     * non si les templates ont �t� modifi�s depuis leur derni�re compilation.
     * @access public
     */
    public static $checkTime=true;
    
    /**
     * @var boolean Indique si le cache est utilis� ou non. Si vous n'utilisez
     * pas le cache, les templates seront compil�s � chaque fois et aucun
     * fichier compil� ne sera cr��. 
     * @access public
     */
    public static $useCache=true;


    /**
     * @var array Pile utilis�e pour enregistrer l'�tat du gestionnaire de
     * templates et permettre � {@link run()} d'�tre r�entrante. Voir {@link
     * saveState()} et {@link restoreState()}
     */
    private static $stateStack=array();
    
    /**
     * @var array Tableau utilis� pour g�rer les blocs <opt> imbriqu�s (voir
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
     * @var string Liste des callbacks � appeller pour d�terminer la valeur d'un
     * champ. Il s'agit d'un copie de l'argument $callback  indiqu� par
     * l'utilisateur lors de l'appel � {@link run()}.
     * 
     * @access private
     */
    private static $dataCallbacks;
    private static $dataObjects;
    private static $dataVars;
    

    /**
     * @var string Flag utilis� par certains callback pour savoir s'il faut ou
     * non ins�rer les tags de d�but et de fin de php. Cf par exemple {@link
     * handleFieldsCallback()}.
     * @access private
     */
    private static $inPhp=false;
    
    /**
     * @var array Tableau utilis� par {@link hashPhpBlocks} pour prot�ger les
     * blocs php g�n�r�s par les balises.
     * @access private
     */
    private static $phpBlocks=array();
    
    /**
     * @var string Path complet du template en cours d'ex�cution.
     * Utilis� pour r�soudre les chemins relatifs (tables, sous-templates...)
     * @access private
     */
    private static $template='';
    
    /**
     * @var string Lors de la g�n�ration d'un template (cf {@link generate()}),
     * nom du jeu de g�n�rateurs � utiliser. Correspond � un sous-r�peroire du
     * r�pertoire "template_generators"
     * @access private
     */
    private static $genSet;
    
    /**
     * @var string Lors de la g�n�ration d'un template (cf {@link generate()}),
     * item en cours de g�n�ration
     * @access private
     */
    private static $genItem;
    
    
    /**
     * @var array Utilis� par {@link generateId()} pour g�n�rer des identifiants
     * unique. Le tableau contient, pour chaque nom utilis� lors d'un appel �
     * {@link generateId()} le dernier num�ro utilis�.
     */
    private static $usedId;
    public static $data=null;
    
    /**
     * constructeur
     * 
     * Le constructeur est priv� : il n'est pas possible d'instancier la
     * classe. Utilisez directement les m�thodes statiques propos�es.
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
     * Ex�cute un template, en le recompilant au pr�alable si n�cessaire.
     * 
     * La fonction run est r�entrante : on peut appeller run sur un template qui
     * lui m�me va appeller run pour un sous-template et ainsi de suite.
     * 
     * @param string $template le nom ou le chemin, relatif ou absolu, du
     * template � ex�cuter. Si vous indiquez un chemin relatif, le template est
     * recherch� dans le r�pertoire du script appellant puis dans le r�pertoire
     * 'templates' de l'application et enfin dans le r�pertoire 'templates' du
     * framework.
     * 
     * @param mixed $dataSources indique les sources de donn�es � utiliser
     * pour d�terminer la valeur des balises de champs pr�sentes dans le
     * template.
     * 
     * Les gestionnaire de templates reconnait trois sources de donn�es :
     * 
     * 1. Des fonctions de callback.  Une fonction de callback est une fonction
     * ou une m�thode qui prend en argument le nom du champ recherch� et
     * retourne une valeur. Si votre template contient une balise de champ
     * '[toto]', les fonctions de callback que vous indiquez seront appell� les
     * unes apr�s les autres jusqu'� ce que l'une d'entre elles retourne une
     * valeur.
     * 
     * Lorsque vous indiquez une fonction callback, il peut s'agir :
     * 
     * - d'une fonction globale : indiquez dans le tableau $dataSources une
     * chaine de caract�res contenant le nom de la fonction � appeller (exemple
     * : 'mycallback')
     * 
     * - d'une m�thode statique de classe : indiquez dans le tableau
     * $dataSources soit une chaine de caract�res contenant le nom de la classe
     * suivi de '::' puis du nom de la m�thode statique � appeller (exemple :
     * 'Template:: postCallback') soit un tableau � deux �l�ments contenant �
     * l'index z�ro le nom de la classe et � l'index 1 le nom de la m�thode
     * statique � appeller (exemple : array ('Template', 'postCallback'))
     * 
     * - d'une m�thode d'objet : indiquez dans le tableau $dataSources un
     * tableau � deux �l�ments contenant � l'index z�ro l'objet et � l'index 1
     * le nom de la m�thode � appeller (exemple : array ($this, 'postCallback'))
     * 
     * 2. Des propri�t�s d'objets. Indiquez dans le tableau $dataSources l'objet
     * � utiliser. Si le gestionnaire de template rencontre une balise de champ
     * '[toto]' dans le template, il regardera si votre objet contient une
     * propri�t� nomm�e 'toto' et si c'est le cas utilisera la valeur obtenue.
     * 
     * n.b. vous pouvez, dans votre objet, utiliser la m�thode magique '__get'
     * pour cr�er de pseudo propri�t�s.
     * 
     * 3. Des valeurs : tous les �l�ments du tableau $dataSources dont la cl�
     * est alphanum�rique (i.e. ce n'est pas un simple index) sont consid�r�es
     * comme des valeurs.
     * 
     * Si votre template contient une balise de champ '[toto]' et que vous avez
     * pass� comme �l�ment dans le tableau : 'toto'=>'xxx', c'est cette valeur
     * qui sera utilis�e.
     * 
     * @param string $callbacks les nom des fonctions callback � utiliser pour
     * instancier le template. Vous pouvez indiquer une fonction unique ou
     * plusieurs en s�parant leurs noms par une virgule. Ce param�tre est
     * optionnel : si vous n'indiquez pas de fonctions callbacks, la fonction
     * essaiera d'utiliser une fonction dont le nom correspond au nom du
     * script appellant suffix� avec "_callback".
     */
    public static function run($template /* $dataSource1, $dataSource2, ..., $dataSourceN */ )
    {
        debug && Debug::log("Template::Run('%s')", $template);
        debug && Debug::tplLog("Template::Run", $template." (TODO: lister les datasources)");
        if (count(self::$stateStack)==0)
        {
            // On est au niveau z�ro, c'est un tout nouveau template, r�initialise les id utilis�s.
            self::$usedId=array();
        }

        $parentDir=dirname(self::$template);

        // Sauvegarde l'�tat
        self::saveState();

        // D�termine le path du r�pertoire du script qui nous appelle
        $caller=dirname(Utils::callerScript()).DIRECTORY_SEPARATOR;
        
        // Recherche le template
        $sav=$template;
        if (strncasecmp($caller, Runtime::$fabRoot, strlen(Runtime::$fabRoot)) ==0)
        {
            // module du framework : le r�pertoire templates de l'application est prioritaire
            $template=Utils::searchFile
            (
                $template,                          // On recherche le template :
                Runtime::$root . 'templates',       // 1. dans le r�pertoire 'templates' de l'application
                $caller,                            // 2. dans le r�pertoire du script appellant
                Runtime::$fabRoot . 'templates',     // 3. dans le r�pertoire 'templates' du framework
                $parentDir
            );
        }
        else
        {        
            // module de l'application : le r�pertoire du script appellant est prioritaire
            $template=Utils::searchFile
            (
                $template,                          // On recherche le template :
                $caller,                            // 1. dans le r�pertoire du script appellant
                Runtime::$root . 'templates',       // 2. dans le r�pertoire 'templates' de l'application
                Runtime::$fabRoot . 'templates',     // 3. dans le r�pertoire 'templates' du framework
                $parentDir
            );
        }
        if (! $template) 
            throw new Exception("Impossible de trouver le template $sav");

        debug && Debug::log("Path du template : '%s'", $template);
        self::$template=$template; // enregistre le path du template en cours (cf walkTable)
        
        self::$data=func_get_args();    // new
        array_shift(self::$data);
        debug && Debug::log('Donn�es du formulaire %s : %s', $sav, self::$data);

        self::$dataCallbacks=self::$dataObjects=self::$dataVars=array();    // old
        $nb=func_num_args();
        for ($i=1; $i<$nb; $i++)
        {
            $value=func_get_arg($i);
            
            if (is_object($value))              // Un objet, exemple : $this
                self::$dataObjects[]=$value;
            elseif (is_string($value))          // Une fonction globale, exemple : 'mycallback' 
            {                                   // Ou une m�thode statique, exemple : 'template::callback'
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
                    // TODO: � faire seulement en mode debug ou en environnement test
                    foreach ($value as $key=>$item)
                        if (! is_string($key)) 
                            throw new Exception("Les cl�s d'un tableau de valeur doivent �tre des chaines de caract�res. Callback pass� : " . Debug::dump($key) . ', ' . Debug::dump($item));
                    self::$dataVars=array_merge(self::$dataVars, $value);
                }
            }
            else
                echo "Je ne sais pas quoi faire de l'argument num�ro ", ($i+1), " pass� � template::run <br />", print_r($value), "<br />";
        }
            
        // Compile le template s'il y a besoin
        if (TRUE or self::needsCompilation($template)) // TODO : � virer
        {
            //
            debug && Debug::notice("'%s' doit �tre compil�", $template);
            
            // Charge le contenu du template
            if ( ! $source=file_get_contents($template, 1) )
                throw new Exception("Le template '$template' est introuvable.");
            
            // Teste si c'est un template g�n�r�
            if (Utils::getExtension($template)=='.yaml') // TODO: � revoir
            {
                // Autorise l'emploi des tabulations (une tab=4 espaces) dans le source yaml
                $source=str_replace("\t", '    ', $source);
                
                //echo "<h1>Description du template :</h1>";
                //highlight_string($source);
                debug && Debug::notice("G�n�ration du template html � partir du fichier yaml");
                self::generate($source);
                
                // Nettoyage
                //$source=str_replace(self::PHP_END_TAG."\r", self::PHP_END_TAG."\n", $source);
//                echo "<h1>Template g�n�r�: </h1>";
//                highlight_string($source);
//                echo "<hr />";
            }
            
            //echo "<h1>Source � compiler :</h1>";
            //highlight_string($source);
                        
            // Compile le code
            debug && Debug::log('Compilation du source');
            require_once dirname(__FILE__) . '/TemplateCompiler.php';
            $source=TemplateCompiler::compile($source);
self::$csource=$source;// TODO: � virer
            // Nettoyage
            // si la balise de fin de php est \r, elle est mang�e (cf http://fr2.php.net/manual/fr/language.basic-syntax.instruction-separation.php)
            $source=str_replace(self::PHP_END_TAG."\r", self::PHP_END_TAG." \r", $source);
//            $source=preg_replace('~[\r\n][ \t]*([\r\n])~', '$1', $source);
            $source=preg_replace("~([\r\n])\s+$~m", '$1', $source);
            
            //echo "<h1>Version compil�e du source :</h1>";
            //highlight_string($source);
                        
            // Stocke le template dans le cache
            if (self::$useCache)
            {
                debug && Debug::log("Mise en cache de '%s'", $template);
                Cache::set($template, $source);
            }
            
            // Ex�cute le source compil�
            if (self::$useCache)
            {
                debug && Debug::log("Ex�cution � partir du cache");
                require(Cache::getPath($template));
            }
            else
            {
                debug && Debug::log("Cache d�sactiv�, eval()");
                eval(self::PHP_END_TAG . $source);
            }            
        }
        
        // Sinon, ex�cute le template � partir du cache
        else
        {
            debug && Debug::log("Ex�cution � partir du cache");
            require(Cache::getPath($template));
        }

        // restaure l'�tat du gestionnaire
        self::restoreState();
    }        

//    /**
//     * Compile le source de template pass� en param�tre.
//     * 
//     * @param string $source En entr�e, le source du template � compiler, en
//     * sortie la version compil�e
//     */
//    private static function compile(&$source)
//    {
//        // Ex�cute les blocs PHP �ventuellement pr�sents dans le template
//        self::execPHP($source);
//    
//        // supprime les commentaires !!! � faire en premier
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
//        // Champs [xxx]  !!! � faire en dernier
//        self::handleFields($source);
//        
//        // php "mange" syst�matiquement le \r qui suit la balise de fermeture 
//        // du coup on lui en donne un � manger pour qu'il ne touche pas � l'original
//        //$source=str_replace(self::PHP_END_TAG."\r", self::PHP_END_TAG."\r\r", $source);
//    }

    /**
     * G�n�re un template � partir de la description qui en est faite dans un
     * fichier YAML
     * 
     * @param string $source En entr�e, la descrition yaml du template �
     * g�n�rer, en sortie le template g�n�r�
     */
    private static function generate(&$source)
    {
        // Analyse le source Yaml
        $t=Utils::loadYaml($source);
        
        // D�termine le jeu de g�n�rateurs � utiliser
        self::$genSet=isset($t['generator']) ? $t['generator'] : 'default';

        // Lance la g�n�ration
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
                self::saveState(); // HACK: � virer. lorsqu'on fait le save dans run, genItem � d�j� �t� modifi� 
                foreach ($content as self::$genItem)
                {
                    // Type de l'item 0 : syntaxe '- [xxx]', 'type' : syntaxe '- type: xxx'
                    $type=isset(self::$genItem[0]) ? self::$genItem[0] : self::$genItem['type'];
//                    echo "<h1>type=$type</h1><pre>";var_dump($content, self::$genItem);echo"</pre>";
                    // D�termine le path du template � utiliser 
                    // Par exemple : ./template_generators/default/radio.htm
                    $template=
                        'template_generators' . DIRECTORY_SEPARATOR
                        . self::$genSet . DIRECTORY_SEPARATOR 
                        . $type
                        . '.htm';
                    
                    // TODO : si le template indiqu� n'existe pas dans le jeu de g�n�rateurs s�lectionn�,
                    // le rechercher dans le jeu de templates 'default'.
                    // S'il n'existe toujours pas, erreur 'type de composant' non g�r�.
                     
                    // Initialise l'id de l'objet
//                    if (isset(self::$genItem['name']))
//                        $name=self::$genItem['name'];
//                    else
//                        $name=$type;
//                        
//                    if (isset(self::$usedId[$name])) // si id d�j� utilis�, ajoute un num�ro (2, 3...)
//                    {
//                        debug && Debug::notice('G�n�re ID pour %s. Dernier utilis� : %s', $name, self::$usedId[$name]);
//                        $name=$name . '_' . (++self::$usedId[$name]+1);
//                        debug && Debug::notice('ID g�n�r�: %s', $name);
//                    }
//                    else
//                    {
//                        debug && Debug::notice('G�n�re ID pour %s. Jamais utilis�. ID g�n�r� : %s', $name, $name);
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
                        if (isset(self::$genItem['name'])) // id n'existe que si name a �t� indiqu�
                            self::$genItem['id']=self::$genItem['name'];
                    }
                    
                    // Construit le code de l'objet
                    array_push($used, array('0'=>'0', '1'=>'1', 'type'=>'type', 'name'=>'name', 'id'=>'id', 'auto_id'=>'auto_id'));
                    ob_start();
                    Template::run($template, array('Template', 'generateCallback'));
                    $source=ob_get_clean();
//                    $h="El�ment courant : " . print_r(self::$genItem, true) . ', used : ' . print_r($used[count($used)], true) . ', diff: ' . print_r(array_diff_key(self::$genItem, $used[count($used)]), true);
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
                self::restoreState();// HACK: � virer. lorsqu'on fait le save dans run, genItem � d�j� �t� modifi�
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
//                return "El�ment courant : " . print_r(self::$genItem, true) . ', used : ' . print_r($used[count($used)], true) . ', diff: ' . print_r(array_diff_key(self::$genItem, $used[count($used)]), true);
//                $h='';
//                foreach (array_diff_key(self::$genItem, $used[count($used)]) as $key=>$value)
//                	$h.= ($h ? ' ': '') . $key. '="' .  htmlspecialchars($value) . '"';
//                return $h;
//                //return implode(',', $used[count($used)]);
                
            case 'sep': // HACK: � virer
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
     * G�n�re un identifiant unique (au sein de la page) pour l'identifiant
     * pass� en param�tre
     * @param string id le nom pour lequel il faut g�n�rer un identifiant ('id'
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
     * Teste si un template a besoin d'�tre recompil� en comparant la version
     * en cache avec la version source.
     * 
     * La fonction prend �galement en compte les options {@link $forceCompile}
     * et {link $checkTime}
     * 
     * @param string $template path du template � v�rifier
     * @param string autres si le template d�pend d'autres fichiers, vous pouvez
     * les indiquer.
     * 
     * @return boolean vrai si le template doit �tre recompil�, false sinon.
     */
    private static function needsCompilation($template /* ... */)
    {
        // Si forceCompile est � true, on recompile syst�matiquement
        //echo "<li>forcecompile : " . var_export(self::$forceCompile,true) . "</li>";
        if (self::$forceCompile) return true;
        
        // Si le cache est d�sactiv�, on recompile syst�matiquement
        //echo "<li>usecache : " . self::$useCache . "</li>";
        if (! self::$useCache) return true;
        
        // si le fichier n'est pas encore dans le cache, il faut le g�n�rer
        $mtime=Cache::lastModified($template);
        //echo "<li>mtime : $mtime</li>";
        if ( $mtime==0 ) return true; 
        
        // Si $checkTime est � false, termin�
        if (! self::$checkTime) return false; 

        // Compare la date du fichier en cache avec celle de chaque d�pendance
        $argc=func_num_args();
        for($i=0; $i<$argc; $i++)
        {
            if ($mtime<=filemtime(func_get_arg($i)) ) 
            {
//                echo "<li>$template d�pend de " . func_get_arg($i) . " qui est plus r�cent</li>";      
                return true;
            } 
        }
        
        // Aucune des d�pendances n'est plus r�cente que le fichier indiqu�
        return false;
    }
    
    /**
     * Ex�cute les blocs PHP pr�sents dans le template
     * 
     * @param string $source le source � ex�cuter
     */
    private static function execPHP(&$source)
    {
        // php "mange" syst�matiquement le \r qui suit la balise de fermeture 
        // du coup on lui en donne un � manger pour qu'il ne touche pas � l'original
//        echo"<h1>eval. Template en cours : " . self::$template . "</h1>";
//        echo "<p>Source : </p>\n";
//        print(str_replace('<', '&lt;', $source));
        
        $source=str_replace(self::PHP_END_TAG."\r", self::PHP_END_TAG."\r\r", $source);
        ob_start();
        $result=eval('extract($GLOBALS);' . self::PHP_END_TAG . $source);
        $source=ob_get_clean() . $result;
//        echo "<p>R�sultat : </p>\n";
//        print(str_replace('<', '&lt;', $source));
    }
    
    /**
     * Supprime les commentaires pr�sents dans le template
     * 
     * @param string $source le source � modifier
     */
    private static function removeComments(&$source)
    {
        $source=preg_replace('~/\*.*?\*/~ms', null, $source);
        // $source=preg_replace('~<!--.*-->~ms', null, $source);
    }

    /**
     * Analyse les attributs d'une balise et retourne un tableau associatif
     * contenant les attributs rencontr�s.
     * @param string $h les attributs � analyser
     * @return array un tableau contenant les attributs trouv�s (cl�=nom de
     * l'attribut, valeur=valeur de l'attribut)
     */
    public static function splitAttributes($h)
    {
        $matches=null;
        $nb=preg_match_all
        (
            '~
                ([\w]+)                 # $1 : nom attribut
                \s*=\s*                 # espaces �gal espaces
                ([\'"])                 # $2 : un guillemet ou une apostrophe
                (.*?)                   # $3 : valeur attribut
                \2                      # guillemet ou apostrophe stock� en $2
            ~msx',
            $h,
            $matches
        );
    
        if ($nb)
        {   // l'attribut contient peut-�tre des balises (walk source="[table].[ext]" 
            $matches[3]=preg_replace // TODO : voir s'il faut garder
                (
                    '~
                    \[                  # crochet ouvrant
                    (                   # $1=nom du tag
                        [^\[\]]*           # tout caract�re sauf crochet ouvrant
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
     * G�re les blocs <if>...</if><else>...</else> pr�sents dans le template
     * 
     * @param string $source le source � modifier
     */
    private static function handleIfElse(&$source)
    {
        // G�re le if et sa condition
        self::$inPhp=true;
        $source=preg_replace_callback
        (
            '~<if\s+(?:condition|test)\s*=\s*"(.*?)"\s*>~ms',
            array('Template','handleIfElseCallback'),
            $source   
        );
        self::$inPhp=false;
        
        // G�re le reste : </if>, <else>, </else>
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
     * Callback utilis� par {@link handleIfElse()}
     */
    private static function handleIfElseCallback($matches)
    {
        self::handleFields($matches[1]);     
        return self::PHP_START_TAG . 'if(' . $matches[1] . '){' . self::PHP_END_TAG;
    }    
    
    /** 
     * G�re les boucles <loop>...</loop> pr�sentes dans le template
     * 
     * @param string $source le source � modifier
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
     * Callback utilis� par {@link handleLoop()}
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
            throw new Exception("Boucle incorrect, l'attribut 'on' n'a pas �t� indiqu�.");
            
        // R�cup�re la valeur pour "$on"
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

        // R�cup�re la valeur pour "$object"
        if (strpos($object, '[') === false)
        {
        	
        }
        else
        {
            self::$inPhp=true;
            self::handleFields($object);
            self::$inPhp=false;
        }

        // R�cup�re la valeur pour "order"        
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
        
        // R�cup�re la valeur pour "max"
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
                // par un appel � $table['xxx']
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
                // par un appel � $table['xxx']
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
                // par un appel � $table['xxx']
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
                // Ajoute l'extension par d�faut s'il y a lieu
                Utils::defaultExtension($source, '.txt');
                                
                // D�termine le path exact de la table
                $h=Utils::searchFile
                (
                    $source,                                    // On recherche la table :
                    dirname(self::$stateStack[1]['template']),  // dans le r�pertoire du script appellant
                    Runtime::$root . 'tables',                  // dans le r�pertoire 'tables' de l'application
                    Runtime::$fabRoot . 'tables'                // dans le r�pertoire 'tables du framework
                );
                if (! $h)
                    throw new Exception("Table non trouv�e : '$source'");
                
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
     * G�re les blocs <fill>...</fill> pr�sents dans le template
     * 
     * @param string $source le source � modifier
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
     * Callback utilis� par {@link handleFill()}
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
     * Fonction appell�e pour "remplir" les listes
     * @internal cette fonction ne doit �tre appell�e que depuis un template.
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
     * G�re les blocs <tableofcontents>...</tableofcontents> pr�sents dans le template
     * 
     * @param string $source le source � modifier
     */
    private static function handleTableOfContents(&$source)
    {
        $source=preg_replace
        (
            array
            (
                '~<tableofcontents\s*>~ms',              // 1. d�but de toc   
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
     * G�re les blocs <opt>...</opt>
     * 
     * @param string $source le source � modifier
     */
    private static function handleOpt(&$source)
    {
        // URGENT: voir si le IF a le m�me probl�me de r�cursivit�
        // TODO: voir si d'autres fonctions peuvent �tre optimis�es pareil (suppression des callbacks de preg_replace)
        
        // D�coupe la chaine au premier <opt> trouv�.
        // Ne recherche pas <option> pr�sent dans les select
        //$t=preg_split('~<opt([^>]*)>~msx', $source, 2, PREG_SPLIT_DELIM_CAPTURE);
        $t=preg_split('~<opt([^o>]*)>~msx', $source, 2, PREG_SPLIT_DELIM_CAPTURE);
        // en 0 : ce qui pr�c�de, en 1 : les attributs, en 2 : ce qui suit le <opt>

        // Si le source ne contient aucun <opt>, termin�
        if (count($t)<2) return;
        
        // R�cursive : g�re les sous-blocs <opt></opt> inclus
        self::handleOpt($t[2]);
    
        // R�cup�re la valeur de min si indiqu�e
        $attributes=self::splitAttributes($t[1]);
        if (isset($attributes['min']))
            $min=$attributes['min'];
        else
            $min='';
    
        // Recherche le </opt>
        $pt = strpos($t[2], '</opt>');
        if ($pt === false) die(htmlentities('</opt> non trouv� !'));
        
        // Construit le r�sultat
        $source = $t[0]; // ce qui pr�c�de
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
//        echo "<h1>Source apr�s</h1>";
//        highlight_string($source);
//    //die();
//    }

//    /**
//     * Callback utilis� par {@link handleFill()}
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
     * Fonction appell�e au d�but d'un bloc <opt>
     * @internal cette fonction ne doit �tre appell�e que depuis un template.
     * @access public
     */
    public static function optBegin()
    {
        self::$optFilled[++self::$optLevel]=0;
        ob_start(); 
    }

    /**
     * Fonction appell�e � la fin d'un bloc <opt>
     * @internal cette fonction ne doit �tre appell�e que depuis un template.
     * @access public
     */
    public static function optEnd($minimum=1)
    {
        // Si on a rencontr� au moins un champ non vide 
        if (self::$optFilled[self::$optLevel--]>=$minimum)
        {
            // Indique � l'�ventuel bloc opt parent qu'on est renseign� 
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
     * G�re les balises de champs [xxx]
     * 
     * @param string $source le source � modifier
     */
    private static function handleFields(&$source)
    {
        // Prot�ge les blocs PHP g�n�r�s par les autres balises
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
                    [^\[\]]*           # tout caract�re sauf crochet ouvrant
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
     * Callback utilis� par {@link handleFields()}
     */
    private static function handleFieldsCallback($matches)
    {
        // $matches[1] contient ce qu'il y a entre crochets. exemple : titeng,titfre,'aucun titre'
        $string=$matches[1];

        // S�pare la valeur par d�faut du reste
        $default='null';
        if ($nb=preg_match_all('~(["\']).*?(?<!\\\\)\1~', $string, $t,PREG_OFFSET_CAPTURE))
        {
            if ($nb>1)            
                throw new Exception(sprintf("Une balise de champ ne peut contenir " .
                        "qu'une seule valeur par d�faut : %s", $string));
            
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
        
        // Parcours toutes les sources de donn�es
        foreach (self::$data as $i=>$data)
        {
            // Objet
            if (is_object($data))
            {
                // Propri�t� d'un objet
                if (property_exists($data, $name))
                {
                    debug && Debug::log('C\'est une propri�t� de l\'objet %s', get_class($data));
                    return 'Template::$data['.$i.']->'.$name;
                }
                
                // Cl� d'un objet ArrayAccess
                if ($data instanceof ArrayAccess)
                {
                    try
                    {
                        debug && Debug::log('Tentative d\'acc�s � %s[\'%s\']', get_class($data), $name);
                        $value=$data[$name];
                        return 'Template::$data['.$i.'][\''.$name.'\']';
                    }
                    catch(Exception $e)
                    {
                        debug && Debug::log('G�n�re une erreur %s', $e->getMessage());
                    }
                }
                else
                    debug && Debug::log('Ce n\'est pas une cl� de l\'objet %s', get_class($data));
            }

            // Cl� d'un tableau de donn�es
            if (is_array($data) && array_key_exists($name, $data)) 
            {
                debug && Debug::log('C\'est une cl� du tableau de donn�es');
                $value=$data[$name];  
                return 'Template::$data['.$i.'][\''.$name.'\']';
            }

            // Fonction de callback
            if (is_callable($data))
            {
                $value=@call_user_func($data, $name);

                // Si la fonction retourne autre chose que "null", termin�
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

        // S�pare la valeur par d�faut du reste
        $default='null';
        if ($nb=preg_match_all('~(["\']).*?(?<!\\\\)\1~', $string, $t,PREG_OFFSET_CAPTURE))
        {
            if ($nb>1)            
                throw new Exception(sprintf("Une balise de champ ne peut contenir " .
                        "qu'une seule valeur par d�faut : %s", $string));
            
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
        // Pour le moment, on ne conna�t pas ce champ
        $value=null;
        
        // Parcours tous les champs indiqu�s en param�tre. Le dernier est la valeur par d�faut
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
- 0: un objet qui retourne qq chose pour titObjProp (via une propri�t�)
- 1: un objet qui retourne qq chose pour titObjKey (l'objet impl�mente ArrayAccess et a la cl�)
- 2: un callback qui retourne qq chose pour 'titCbk'
- 3: un tableau de valeur qui contient qq chose pour la cl� 'titArr'

dans le template, on a :
[titObjProp:titObjKey:titCbk:titArr:'pas de titre']

actuellement, on compile en :
echo Template::field('titObjProp','titObjKey','titCbk','titArr', 'pas de titre')
ce qui g�n�re : une boucle sur chacun des arguments X une boucle sur chaque datasource

early binding. La liaison est faite lors de la compilation, on g�n�re :
echo Template::$data[0]->titObjProp
    Template::$data[1]['titObjKey']
    Template::$data[2]('titCbk')
    Template::$data[3]['titArr']


echo $var=x ? $var 
*/     

//echo Template::$data[1]('titre');

            // R�cup�re le nom du champ recherch�
            $name=func_get_arg($i);
            debug && Debug::log('%s', $name);
            
            // Parcours toutes les sources de donn�es
            foreach (self::$data as $data)
            {
                // Objet
                if (is_object($data))
                {
                    // Propri�t� d'un objet
                    if (property_exists($data, $name))
                    {
                        debug && Debug::log('C\'est une propri�t� de l\'objet %s', get_class($data));
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
                            debug && Debug::log('Valeur non vide obtenue, termin�');
                            return $value;
                        }
                        debug && Debug::log('A retourn� une chaine vide');
                        break;
                    }
                    else
                        debug && Debug::log('Ce n\'est pas une propri�t� de l\'objet %s', get_class($data));
                    
                    // Cl� d'un objet ArrayAccess
                    if ($data instanceof ArrayAccess)
                    {
                        try
                        {
                            debug && Debug::log('Tentative d\'acc�s � %s[\'%s\']', get_class($data), $name);
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
                                debug && Debug::log('Valeur non vide obtenue, termin�');
                                return $value;
                            }
                            debug && Debug::log('Pas d\'erreur mais a retourn� une chaine vide');
                        }
                        catch(Exception $e)
                        {
                            debug && Debug::log('G�n�re une erreur %s', $e->getMessage());
                            
                        }
                    }
                    else
                        debug && Debug::log('Ce n\'est pas une cl� de l\'objet %s', get_class($data));
                }
//                echo 'name=', $name, ', is_array(data)', is_array($data), ', dump data=', print_r($data,true) ,'<br />';
                // Cl� d'un tableau de donn�es
                if (is_array($data) && array_key_exists($name, $data)) 
                {
                    debug && Debug::log('C\'est une cl� du tableau de donn�es');
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
                        debug && Debug::log('Valeur non vide obtenue, termin�');
                        return $value;
                    }
                    debug && Debug::log('Valeur vide obtenue, termin�');
                    break;
                }

                // Fonction de callback
                if (is_callable($data))
                {
                    $value=@call_user_func($data, $name);
    
                    // Si la fonction retourne autre chose que "null", termin�
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
            }   // source de donn�es
        }   // param�tres
        
        // Retourne la valeur par d�faut si on en a une
        $default=func_get_arg($nb-1);
        if (! is_null($default))
        {
            debug && Debug::tplLog
            (
                '['.implode(':', $args).']',
                $value,
                "valeur par d�faut indiqu�e dans le template"
            );
            if ($default) self::$optFilled[self::$optLevel]++;
            return $default;
        }
        
        // Retourne la balise litt�rale sinon
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
            "balise litt�rale, aucune source ne conna�t"
        );
        
        return $value ; //"[$name]";

        
    }
        
    /**
     * Fonction appell�e pour d�terminer la valeur d'un champ de template
     * @internal cette fonction ne doit �tre appell�e que depuis un template.
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
            
            // Teste s'il s'agit d'une propri�t� d'objet
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
            
            // Ex�cute tous les callbacks un par un
            foreach(self::$dataCallbacks as $callback)
            {
                $value=call_user_func($callback, $name);

                // Si la fonction retourne autre chose que "null", termin�
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
        
        // Retourne la valeur par d�faut si on en a une
        $default=func_get_arg($nb-1);
        if (! is_null($default))
        {
            debug && Debug::tplLog
            (
                '['.implode(':', $args).']',
                $value,
                "valeur par d�faut indiqu�e dans le template"
            );
            if ($default) self::$optFilled[self::$optLevel]++;
        	return $default;
        }
        
        // Retourne la balise litt�rale sinon
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
            "balise litt�rale, aucune source ne conna�t"
        );
        
        return $value ; //"[$name]";
    }    
    
    /**
     * Convertit les liens pr�sents dans les templates en fonction des routes
     * 
     * @param string $source le source � modifier
     */
    private static function handleLinks(&$source)
    {
        $source=preg_replace_callback
            (
                '~
                (                           # $1 : tout ce qui pr�c�de la quote ouvrante
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
     * Callback utilis� par {@link handleLinks()}
     */
    private static function handleLinksCallback($matches)
    {
//echo "<li>HLC $matches[3]</li>";
        // $1 : tout ce qui pr�c�de la quote ouvrante, $2 : la quote, $3 : le lien
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
        // G�re la balise <include file="xxx" />
        $source=preg_replace_callback
        (
            '~<include\s+file\s*=\s*"(.*?)"\s*/>~ms',
            array('Template','handleIncludeCallback'),
            $source   
        );
    }
    
    /**
     * Callback utilis� par {@link handleInclude()}
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
     * Enregistre l'�tat du gestionnaire de template. 
     * Utilis� pour permettre � {@link run()} d'�tre r�entrant
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
     * Restaure l'�tat du gestionnaire de template.
     * Utilis� pour permettre � {@link run()} d'�tre r�entrant
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
     * Callback standard pour les templates. Recherche la variable demand�e
     * dans les champs de la s�lection en cours, si elle existe, et s'il y a un
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
     * Callback standard pour les templates. Recherche la variable demand�e
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
     * Callback standard pour les templates. Recherche la variable demand�e
     * dans $_POST (donn�es de formulaire transmises en m�thode post)
     */
    public static function postCallback($name)
    {
        if ( isset($_POST[$name]) ) return $_POST[$name];
    }
    
    /**
     * getCallback
     * 
     * Callback standard pour les templates. Recherche la variable demand�e
     * dans $_GET (query_string)
     */
    public static function getCallback($name)
    {
        if ( isset($_GET[$name]) ) return $value=$_GET[$name];
    }
    
    /** 
     * requestCallback
     * 
     * Callback standard pour les templates. Recherche la variable demand�e
     * dans $_REQUEST (donn�es provenant des cookies, de la query_string et
     * des donn�es de formulaires en m�thode post).
     */
    public static function requestCallback($name)
    {
        if ( isset($_REQUEST[$name]) ) return $_REQUEST[$name];
    }
    
    /** 
     * emptyCallback
     * 
     * Callback standard pour les templates. Retourne une chaine
     * vide quelle que soit la variable demand�e
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
 
 private $option_egal = false; //D�fini si le = prend la couleur de l'attribut, et les " la couleur de la valeur
 private $option_nl2br = TRUE; //D�fini si les retours de lignes doivent �tre remplac�s par des <br />
 
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
        'content'=>'content pas encore g�r�',
        'key'=>'ceci est ma cl�',
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