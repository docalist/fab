<?php
/**
 * @package     fab
 * @subpackage  template
 * @author 		Daniel M�nard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id$
 */


/*
 * TODO :
 * - FAIT, Youenn avoir l'�quivalent des walktables
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
     * options de configuration utilis�es :
     * 
     * templates.forcecompile : indique s'il faut ou non forcer une recompilation 
     * compl�te des templates, que ceux-ci aient �t� modifi�s ou non.
     * 
     * templates.checktime : indique si le gestionnaire de template doit v�rifier 
     * ou non si les templates ont �t� modifi�s depuis leur derni�re compilation.
     * 
     * cache.enabled: indique si le cache est utilis� ou non. Si vous n'utilisez
     * pas le cache, les templates seront compil�s � chaque fois et aucun fichier 
     * compil� ne sera cr��. 
     */


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
     * @var array Sources de donn�es pass�es en param�tre 
     * 
     * @access private
     */
    public static $data=null;
    

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

    public static $isCompiling=0;
    
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
    public static function runSource($source /* $dataSource1, $dataSource2, ..., $dataSourceN */ )
    {
echo 'Template :<pre>';
echo htmlentities($source);
echo '</pre>';
        debug && Debug::log('Ex�cution du source %s', $source);

        // Sauvegarde l'�tat
        self::saveState();

        // D�termine le path du r�pertoire du script qui nous appelle
        $template=dirname(Utils::callerScript()).DIRECTORY_SEPARATOR;
        
        // Stocke le path du template
        debug && Debug::log("Path du template initialis� au path de l'appellant : '%s'", $template);
        self::$template=$template; // enregistre le path du template en cours (cf walkTable)
        
        // Stocke les sources de donn�es pass�es en param�tre
        self::$data=func_get_args();
        array_shift(self::$data);
        array_unshift(self::$data,array('this'=>Utils::callerObject(2)));
        
        // Compile le template s'il y a besoin
        if (true)
        {
            debug && Debug::notice("'%s' doit �tre compil�", $template);
            
            // Compile le code
            debug && Debug::log('Compilation du source');
            require_once dirname(__FILE__) . '/TemplateCompiler.php';
            $source=TemplateCompiler::compile($source, self::$data);
            $source=utf8_decode($source);
            
echo 'Version compil�e :<pre>';
echo htmlentities($source);
echo '</pre>';
return;
                
            // Stocke le template dans le cache et l'ex�cute
            if (config::get('cache.enabled'))
            {
                debug && Debug::log("Mise en cache de '%s'", $template);
                Cache::set($template, $source);
                debug && Debug::log("Ex�cution � partir du cache");
                require(Cache::getPath($template));
            }
            else
            {
                debug && Debug::log("Cache d�sactiv�, evaluation du template compil�");
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
    
    public static function run($template /* $dataSource1, $dataSource2, ..., $dataSourceN */ )
    {
        debug && Debug::log('Ex�cution du template %s', $template);

        $parentDir=dirname(self::$template);

        // Sauvegarde l'�tat
        self::saveState();

        // D�termine le path du r�pertoire du script qui nous appelle
        $caller=dirname(Utils::callerScript()).DIRECTORY_SEPARATOR;
        
        // Recherche le template
        if (! file_exists($template))
        {
            $sav=$template;
            $template=Utils::searchFile
            (
                $template,                          // On recherche le template :
                $caller,                            // 2. dans le r�pertoire du script appellant
                $parentDir
            );
            if (! $template) 
                throw new Exception("Impossible de trouver le template $sav");
        }

        // Stocke le path du template
        debug && Debug::log("Path du template : '%s'", $template);
        self::$template=$template; // enregistre le path du template en cours (cf walkTable)
        
        // Stocke les sources de donn�es pass�es en param�tre
        self::$data=func_get_args();
        array_shift(self::$data);
        array_unshift(self::$data,array('this'=>Utils::callerObject(2)));
        
        // Compile le template s'il y a besoin
        if (self::needsCompilation($template))
        {
            debug && Debug::notice("'%s' doit �tre compil�", $template);
            
            // Charge le contenu du template
            if ( false === $source=file_get_contents($template) )
                throw new Exception("Le template '$template' est introuvable.");
            
            // Compile le code
            debug && Debug::log('Compilation du source');
            require_once dirname(__FILE__) . '/TemplateCompiler.php';
            $source=TemplateCompiler::compile($source, self::$data);
//echo 'Version compil�e :<pre>';
//echo htmlentities($source);
//echo '</pre>';
//          if (php_version < 6) ou  if (! PHP_IS_UTF8)
            $source=utf8_decode($source);
            // Stocke le template dans le cache et l'ex�cute
            if (config::get('cache.enabled'))
            {
                debug && Debug::log("Mise en cache de '%s'", $template);
                Cache::set($template, $source);
                debug && Debug::log("Ex�cution � partir du cache");

                require(Cache::getPath($template));
            }
            else
            {
                debug && Debug::log("Cache d�sactiv�, evaluation du template compil�");
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

    /**
     * Teste si un template a besoin d'�tre recompil� en comparant la version
     * en cache avec la version source.
     * 
     * La fonction prend �galement en compte les options templates.forcecompile
     * et templates.checkTime
     * 
     * @param string $template path du template � v�rifier
     * @param string autres si le template d�pend d'autres fichiers, vous pouvez
     * les indiquer.
     * 
     * @return boolean vrai si le template doit �tre recompil�, false sinon.
     */
    private static function needsCompilation($template /* ... */)
    {
        // Si templates.forceCompile est � true, on recompile syst�matiquement
        if (Config::get('templates.forcecompile')) return true;

        // Si le cache est d�sactiv�, on recompile syst�matiquement
        if (! Config::get('cache.enabled')) return true;
        
        
        // si le fichier n'est pas encore dans le cache, il faut le g�n�rer
        $mtime=Cache::lastModified($template);
        if ( $mtime==0 ) return true;
        
        // Si templates.checktime est � false, termin�
        if (! Config::get('templates.checktime')) return false;

        // Compare la date du fichier en cache avec celle de chaque d�pendance
        $argc=func_num_args();
        for($i=0; $i<$argc; $i++)
            if ($mtime<=filemtime(func_get_arg($i)) ) return true;
             
        // Aucune des d�pendances n'est plus r�cente que le fichier indiqu�
        return false;
    }
    
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
    
    public static function filled($x)
    {
        if ($x != '') 
            self::$optFilled[self::$optLevel]++;
    	return $x;
    }
    
    /**
     * Enregistre l'�tat du gestionnaire de template. 
     * Utilis� pour permettre � {@link run()} d'�tre r�entrant
     */
    private static function saveState()
    {
        array_push
        (
            self::$stateStack, 
            array
            (
                'template'      => self::$template,
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
        $t=array_pop(self::$stateStack);
        self::$template         =$t['template'];
        self::$data             =$t['data'];
    }

    public static function runSlot($slotName, $defaultAction='')
    {
        debug && Debug::log('Ex�cution du slot %s', $slotName);
        if ('' === $action=Config::get('slot.'.$slotName, $defaultAction)) 
        {
            debug && Debug::log('Ex�cution du slot %s : aucune action d�finie', $slotName);
            return;	
        }
        
        debug && Debug::log('Ex�cution du slot %s : %s', $slotName, $action);
        Routing::dispatch($action);
    }    
}

?>
