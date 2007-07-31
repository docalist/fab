<?php

/**
 * @package     fab
 * @subpackage  modules
 * @author      Daniel Ménard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id: Database.php 105 2006-09-21 16:30:25Z dmenard $
 */

class Optional implements ArrayAccess
{
    private $data=null;
    
	public function __construct($data)
    {
    	if (! is_array($data))
            throw new Exception('Vous devez passer un tableau pour créer un objet '.get_class());
        $this->data=$data;
    }
    
    public function offsetExists($offset)
    {
    	return true;
    }
    public function offsetGet($offset)
    {
    	if (isset($this->data[$offset])) return $this->data[$offset];else return '';
    }   
    public function offsetSet($offset, $value)
    {
        throw new exception('Un objet ' . get_class() . ' n\'est pas modifiable');
    }   
    public function offsetUnset($offset)
    {
        throw new exception('Un objet ' . get_class() . ' n\'est pas modifiable');
    }   
}


/**
 * Ce module permet de publier une base de données sur le web
 * 
 * @package     fab
 * @subpackage  modules
 */
class DatabaseModule extends Module
{
    /**
     * @var string Equation de recherche
     * @access protected
     */
    public $equation='';
    
    /**
     * @var Database La sélection en cours
     * @access protected
     */
    public $selection=null;
    
    public function preExecute()
    {
        // HACK: permet à l'action export d'avoir un layout s'il faut afficher le formulaire de recherche
        // et de ne pas en avoir si tous les paramètres requis pour lancer l'export sont OK.
        // TODO: il faudrait que fab offre plus de flexibilité sur le choix du layout
        // Actuellement, on peut définir le layout dans la config ou dans preExecute. Lorsque l'action
        // commence à s'exécuter, le début du layout a déjà été envoyé. Il faudrait avoir un système qui 
        // permette à l'action, jusqu'au dernier moment, de choisir le layout.
        // Pistes étudiées :
        // 1. action réelle export avec layout (afficher le formulaire) et pseudo action doexport avec
        // layout none pour faire l'export à proprement parler.
        // 2. hack dans preExecute : si tous les paramètres sont bon, faire un setLayout() (ce qu'on fait ci-dessous)
        // 3. hack avec preExecute : appeller l'action dès le preExecute, si l'action retourne true, arrêter fab
        // sinon, continuer l'exécution normale (qui va appeller à nouveau l'action)
        // 4. (non testé) avoir une fonction runLayout() qu'on pourrait appeller dans l'action et qui ensuite appellerait
        // à nouveau l'action (tordu !)
        // 5. lazy layout magique : avoir un système qui détecte le premier echo effectué (un ob_handler) et qui, si un 
        // layout a été défini, commence à l'envoyer (suppose de savoir couper les layout en deux)
        // 6. lazy layout manuel : le layout n'est jamais envoyé automatiquement. L'action doit appeller startLayout() 
        // quand elle commmence à envoyer des données. Dans Template::Run, un test if (!layoutSent) startLayout(), ce qui fait
        // que ce serait transparent pour toutes les actions qui se contente d'afficher un template.
        if ($this->realAction==='export')
        {
            $defaultLayout=Config::get('layout');
            $this->setLayout('none');               // essaie de faire l'export, pour ça on met layout à none
            if ($this->actionExport(true)===true)   // l'export a été fait, terminé, demande à fab de s'arrêter
                return true;
            $this->setLayout($defaultLayout);       // export non fait, il faut afficher le formulaire, remet le layout initial
        }
    }

    /**
     * Ouvre la base de données du module
     * 
     * Si une équation est indiquée, une recherche est lancée.
     * 
     * @param string $equation optionnel, l'équation de recherche à lancer
     * 
     * @param readOnly indique si la base doit être ouverte en lecture seule
     * (valeur par défaut) ou en lecture/écriture
     * 
     * @return boolean true si une recherche a été lancée et qu'on a au moins 
     * une réponse, false sinon
     */
    protected function openDatabase($readOnly=true)
    {
        // Le fichier de config du module indique la base à utiliser
        $database=Config::get('database');

        if (is_null($database))
            throw new Exception('La base de données à utiliser n\'a pas été indiquée dans le fichier de configuration du module');
        
        debug && Debug::log("Ouverture de la base '%s' en mode '%s'", $database, $readOnly ? 'lecture seule' : 'lecture/écriture');
        $this->selection=Database::open($database, $readOnly);
    }

    protected function select($equation, $options=null)
    {
		// Valeurs par défaut des options
	    $defaultOptions=array
	    (
	        '_sort'  => Utils::get($_REQUEST['_sort'], Config::get('sort','+')),
	        '_start' => Utils::get($_REQUEST['_start'], 1),
	        '_max'   => Utils::get($_REQUEST['_max'], Config::get('max',10)),
	    );

        if (is_array($options))
        {
        	// On fusionne le tableau d'options passé en paramètre et les options par défaut
        	// On prend par défaut $defaultOptions. Si $options redéfinit la valeur d'une option,
        	// alors c'est cette nouvelle valeur qui sera prise en compte.
        	return $this->selection->search($equation,array_merge($defaultOptions, $options));
        }
        else
        {
        	return $this->selection->search($equation, $defaultOptions);
        }
    }
   
    /**
     * Affiche le formulaire de recherche indiqué dans la clé 'template' de la
     * configuration, en utilisant le callback indiqué dans la clé 'callback' de
     * la configuration. Si le callback indiqué est 'none' alors aucun callback 
     * n'est appliqué.
     * 
     */
    public function actionSearchForm()
    {        
        // Détermine le template à utiliser
        if (! $template=$this->getTemplate())
            throw new Exception('Le template à utiliser n\'a pas été indiqué');
        
        // Détermine le callback à utiliser
        $callback=$this->getCallback();
                
        // Exécute le template
        Template::run
        (
            $template,  
            array($this, $callback)           // Priorité à la fonction utilisateur
        );
    }

    /**
     * Affiche un message si une erreur s'est produite lors de la recherche.
     * Le template à utiliser est indiqué dans la clé 'errortemplate' de la 
     * configuration de l'action 'search'.
     * 
     * @param $error string le message d'erreur a afficher (passé à Template::run) via
     * la source de donnée 'error'
     */
    public function showError($error='')
    {
        // Détermine le template à utiliser
        if (! $template=$this->getTemplate('errortemplate'))
        {
        	echo $error ? $error : 'Une erreur est survenue pendant le traitement de la requête';
            return;
        }

        // Détermine le callback à utiliser
        $callback=$this->getCallback();

        // Exécute le template
        Template::run
        (
            $template,  
            array($this, $callback),
            array('error'=>$error)
        );
    }
    
    
    
    /**
     * Affiche un message si aucune réponse n'est associée la recherche.
     * Le template à utiliser est indiqué dans la clé 'noanswertemplate' de la 
     * configuration de l'action 'search'.
     * 
     * @param $message string le message a afficher (passé à Template::run) via
     * la source de donnée 'message'
     * 
     */
    public function showNoAnswer($message='')
    {
        // Détermine le template à utiliser
        if (! $template=$this->getTemplate('noanswertemplate'))
        {
            echo $message ? $message : 'La requête n\'a retourné aucune réponse';
            return;
        }

        // Détermine le callback à utiliser
        $callback=$this->getCallback();
        
        // Exécute le template
        Template::run
        (
            $template,  
            array($this, $callback),
            array('message'=>$message)
        );
    }
    
    /**
     * Lance une recherche si une équation peut être construite à partir des 
     * paramètres passés et affiche les notices obtenues en utilisant le template 
     * indiqué dans la clé 'template' de la configuration.
     * Si aucun paramètre n'a été passé, redirige vers le formulaire de recherche.
     * Si erreur lors de la recherche, affiche l'erreur en utilisant le template
     * indiqué dans la clé 'errortemplate' de la configuration. 
     * 
     * Le template peut ensuite boucler sur {$this->selection} pour afficher les résultats
     */
    public function actionSearch()
    {
        // Ouvre la base de données (le nouveau makeEquation en a besoin)
        $this->openDatabase();

        // Détermine la recherche à exécuter        
        $this->equation=$this->selection->makeEquation(Utils::isGet() ? $_GET : $_POST);

        // Si aucun paramètre de recherche n'a été passé, il faut afficher le formulaire
        // de recherche
        if (is_null($this->equation))
                Runtime::redirect('searchform');
        
        // Des paramètres ont été passés, mais tous sont vides et l'équation obtenue est vide
        if ($this->equation==='')
        {
            // Ajout DM 18/07/07 : on peut avoir dans la config une equation par défaut
            $this->equation=Config::get('equation');
            if (is_null($this->equation))
                return $this->showError('Vous n\'avez indiqué aucun critère de recherche.');
        }
        
        // Aucune réponse
        if (! $this->select($this->equation))
            return $this->showNoAnswer("La requête $this->equation n'a donné aucune réponse.");
        
        // Détermine le template à utiliser
        if (! $template=$this->getTemplate('template'))
            throw new Exception('Le template à utiliser n\'a pas été indiqué');
        
        // Détermine le callback à utiliser
        $callback=$this->getCallback();

        // Exécute le template
        Template::run
        (
            $template,
            array($this, $callback),
            $this->selection->record  
        );                
    }   
    
    
    /**
     * Reconstitue et retourne la query string
     */
    private static function buildQuery($t)
    {
        $query='';
        foreach ($t as $key=>$value)
        {
            $key=urlencode($key);
            if (is_array($value))
            {
                foreach ($value as $item)
                {
                    $query.='&'.$key.'='.urlencode($item);
                }
            }
            else
            {
                $query.='&'.$key.'='.urlencode($value);
            }
        }
        return substr($query,1);
    }     
    
    /**
     * A partir d'une sélection ouverte et de la queryString, retourne la barre de navigation entre
     * les différentes pages de résultats (au format XHTML).
     * Peut-être appelée directement depuis un template
     * 
     * @param $actionName string l'action qui donne les résultats pour lesquels on créé une barre de navigation
     * @param $maxLinks integer le nombre de liens maximum à afficher dans la barre de navigation
     * @param $prevLabel string le libellé du lien vers la page précédente
     * @param $nextLabel string le libelle du lien vers la page suivante
     * @param $firstLabel string le libelle du lien vers la première page de résultats pour la sélection (chaîne vide si aucun)
     * @param $lastLabel string le libelle du lien vers la dernière page de résultats pour la sélection (chaîne vide si aucun)
     * 
     * @return chaîne XHTML correspond à la barre de navigation ou une chaîne vide s'il n'y a qu'une seule page à afficher
     */
    public function getSimpleNav($prevLabel = '&lt;&lt; Précédent', $nextLabel = 'Suivant &gt;&gt;')
    {
        // la base de la query string pour la requête
        $query=$_GET;
        unset($query['_start']);
        unset($query['module']);
        unset($query['action']);
        $query=self::buildQuery($query);
        
        $actionName = $this->action;    // on adapte l'URL en fonction de l'action en cours (search, show, ...)

        $start=$this->selection->searchInfo('start');
        $max= $this->selection->searchInfo('max');
        $count=$this->selection->count();
        
        $this->module=strtolower($this->module); // BUG : this->module devrait être tel que recherché par les routes
        $url='/'.$this->module.'/'.$this->action.'?'.$query;

        $h='Résultats ' . $start.' à '.min($start+$max-1,$count) . ' sur environ '.$count. ' ';
        if ($start > 1)
        {
            $newStart=max(1,$start-$max);
            $prevUrl=Routing::linkFor($url.'&_start='.$newStart);
            $h.='<a href="'.$prevUrl.'">'.$prevLabel.'</a>';
        }
        
        
        if ( ($newStart=$start+$max) < $count)
        {
            $nextUrl=Routing::linkFor($url.'&_start='.$newStart);
            if ($h) $h.=' ';
            $h.='<a href="'.$nextUrl.'">'.$nextLabel.'</a>';
        }

        return '<span class="navbar">'.$h.'</span>';
    }
    
    public function getResNavigation($maxLinks = 10, $prevLabel = '<', $nextLabel = '>', $firstLabel = '', $lastLabel = '')
    {        
        // la base de la query string pour la requête
        $queryStr=$_GET;
        unset($queryStr['_start']);
        unset($queryStr['module']);
        unset($queryStr['action']);
        $baseQueryString=self::buildQuery($queryStr);
        
        $actionName = $this->action;    // on adapte l'URL en fonction de l'action en cours (search, show, ...)

        $currentStart = $this->selection->searchInfo('start');  // num dans la sélection du première enreg de la page en cours 
        $maxRes = $this->selection->searchInfo('max');          // le nombre de réponses max par page
        
        $startParam = 1;                // le param start pour les URL des liens générés dans la barre de navigation
        $pageNum = 1;                   // le premier numéro de page à généré comme lien dans la barre de navigation
        $navBar = '<span class="navbar">';                   // la barre de navigation au format XHTML
        
        // numéro de la page dont les résultats sont affichés
        $currentPage = intval(($currentStart - 1) / $maxRes) + 1;
        
        // numéro de la dernière page de résultats pour la sélection en cours (indépendamment de $maxLinks)
        $lastSelPageNum = ($this->selection->count() % $maxRes) == 0 ? (intval($this->selection->count()/$maxRes)) : (intval($this->selection->count()/$maxRes)+1);
        // numéro de page du dernier lien qu'on affiche (<= $lastSelPageNum suivant la val de $maxLinks)
        $lastDispPageNum = $lastSelPageNum;    
        
        // Ajustement de la valeur des variable pour la gestion de la "fenêtre" de liens : nombre de liens à afficher...
        if ($maxLinks < $lastSelPageNum)
        {
            // nombre de liens à afficher avant le numéro de la page courante dans la barre
            // de navigation en supposant que le numéro de la page courante sera centré sur celle-ci
            $numLinksBefore = intval(($maxLinks - 1) / 2);
            
            // ici, le premier numéro de page à afficher dans la barre de navigation ($pageNum) vaut 1
            // le recalcule si nécessaire
            if ( ($currentPage - $numLinksBefore) >= 1 )
            {
                if (($currentPage + ($maxLinks - $numLinksBefore - 1)) > $lastSelPageNum)
                {
                    // on va afficher le lien vers la dernière page de résultats pour la sélection
                    // le num de la page courante ne sera pas centré sur la barre (sinon on afficherait 
                    // un ou des liens vers des pages de résultats inexistantes à droite)
                    $pageNum = $currentPage - ($maxLinks - ($lastSelPageNum - $currentPage + 1));
                }
                else
                {
                    $pageNum = ($currentPage - $numLinksBefore);
                }
            }
            
            $lastDispPageNum = $pageNum + ($maxLinks - 1);  // ajuste le numéro de la dernière page à afficher dans la barre
            $startParam =  ($pageNum - 1) * $maxRes + 1;    // ajuste $startParam pour le premier lien correspondant à un num de page
        }
        
        if ($pageNum < $lastDispPageNum)    // plusieurs pages de résultats : génère les liens 
        {            
            // lien "page précédente" et éventuel lien vers la première page
            if ($currentPage > 1)
            {
                if ( ($firstLabel != '') && ($pageNum > 1) )    // afficher lien vers la première page ?
                    $navBar = $navBar . '<span class="firstPage"><a href="' . $actionName . '?' . $baseQueryString . "&_start=1" . '">' . $firstLabel . '</a></span> ';
                    
                // TODO: ligne suivante nécessaire ?
                $prevStart = $currentStart-$maxRes >=1 ? $currentStart-$maxRes : 1; // param start pour le lien vers la page précédente
                $navBar = $navBar . '<span class="prevPage"><a href="' . $actionName . '?' . $baseQueryString . "&_start=$prevStart" . '">' . $prevLabel . '</a></span> ';
            }
            
            // génère les liens vers chaque numéro de page de résultats
            for($pageNum; $pageNum <= $lastDispPageNum; ++$pageNum)
            {
                if($startParam == $currentStart)    // s'il s'agit du numéro de la page qu'on va afficher, pas de lien
                    $navBar = $navBar . $pageNum . ' ';
                else
                {
                    $navBar = $navBar . '<span class="pageNum"><a href="' . $actionName . '?' . $baseQueryString . "&_start=$startParam" . '">'. $pageNum . '</a></span> ';
//                    $link=$actionName . '?' . $baseQueryString . "&_start=$startParam";
                    //echo Routing::linkFor(Utils::convertString($link, 'lower'));
//                    echo Routing::linkFor($link);
//                    echo $link;
//                    $navBar = $navBar . '<span class="pageNum"><a href="' . Routing::linkFor($link) . '">'. $pageNum . '</a></span> ';
                }    
                $startParam += $maxRes;
            }
            
            // lien "page suivante" et éventuellement, lien vers la dernière page de la sélection
            if (($currentPage < $lastSelPageNum))
            {
                // TODO : ligne commentée suivante nécessaire ?
//                $nextStart = $currentStart+$maxRes <= $this->selection->count() ? $currentStart+$maxRes : ;
                $nextStart = $currentStart + $maxRes;   // param start pour le lien vers la page suivante
                $navBar = $navBar . '<span class="nextPage"><a href="' . $actionName . '?' . $baseQueryString . "&_start=$nextStart" . '">' . $nextLabel . '</a></span> ';
                
                if ( ($lastLabel != '') && ($lastDispPageNum < $lastSelPageNum) )   // afficher lien vers la dernière page ?
                {
                    $startParam = ($this->selection->count() % $maxRes) == 0 ? $this->selection->count() - $maxRes + 1 : intval($this->selection->count() / $maxRes) * $maxRes + 1;
                    $navBar = $navBar . '<span class="lastPage"><a href="' . $actionName . '?' . $baseQueryString . "&_start=$startParam" . '">' . $lastLabel . '</a></span>';
                }
            }
            
            return $navBar . "</span>";
        }
        else    // une seule page à afficher : on ne renvoie pas de liens (chaîne vide)
        {
            return '';   
        }
    }
    
    /**
     * Affiche une ou plusieurs notices en "format long"
     * Le(s) notice(s) à afficher sont donnés par une equation de recherche
     * Génère une erreur si aucune équation n'est accessible ou si elle ne retourne aucune notice
     * 
     * Le template instancié peut ensuite boucler sur {$this->selection} pour afficher les résultats
     */
    public function actionShow()
    {
        // Ouvre la base de données (le nouveau makeEquation en a besoin)
        $this->openDatabase();

        // Détermine la recherche à exécuter        
        $this->equation=$this->selection->makeEquation(Utils::isGet() ? $_GET : $_POST);

        // Si aucun paramètre de recherche n'a été passé, erreur
        if (is_null($this->equation))
            return $this->showError('Le numéro de la référence à afficher n\'a pas été indiqué.');

        // Des paramètres ont été passés, mais tous sont vides et l'équation obtenue est vide
        if ($this->equation==='')
            return $this->showError('Vous n\'avez indiqué aucun critère permettant de sélectionner la notice à afficher.');

        // Aucune réponse
        if (! $this->select($this->equation))
            return $this->showNoAnswer("La requête $this->equation n'a donné aucune réponse.");

        // Détermine le template à utiliser
        if (! $template=$this->getTemplate())
            throw new Exception('Le template à utiliser n\'a pas été indiqué');
        
        // Détermine le callback à utiliser
        $callback=$this->getCallback();

        // Exécute le template         
        Template::run
        (
            $template,  
            array($this, $callback),
            $this->selection->record
        );  
    }
    
    
    /**
     * Création d'une notice
     * Affiche le formulaire indiqué dans la clé 'template' de la configuration.
     * 
     * Lui passe la source de donnée 'REF' = 0 pour indiquer à l'action save qu'on créé une nouvelle notice
     *
     */
    public function actionNew()
    {    
        // Détermine le template à utiliser
        if (! $template=$this->getTemplate())
            throw new Exception('Le template à utiliser n\'a pas été indiqué');
            
        $callback = $this->getCallback();
        
        // On exécute le template correspondant
        Template::run
        (
            $template,
            array('REF'=>'0'),        // indique qu'on veut créer une nouvelle notice 
            array($this, $callback)
        );      
    } 
    
    /**
     * Edition d'une notice
     * Affiche le formulaire indiqué dans la clé 'template' de la configuration.
     * 
     * La notice correspondant à l'équation donnée est chargée dans le formulaire : l'équation ne doit
     * retourner qu'un seul enregistrement sinon erreur.
     */
    public function actionLoad()
    {
        // Ouvre la base de données (le nouveau makeEquation en a besoin)
        $this->openDatabase();

        // Détermine la recherche à exécuter        
        $this->equation=$this->selection->makeEquation(Utils::isGet() ? $_GET : $_POST);

        // Erreur, si aucun paramètre de recherche n'a été passé
        // Erreur, si des paramètres ont été passés, mais tous sont vides et l'équation obtenue est vide
        if (is_null($this->equation) || $this->equation==='')
        	return $this->showError('Le numéro de la référence à modifier n\'a pas été indiqué.');

        // Si un numéro de référence a été indiqué, on charge cette notice         
        // Vérifie qu'elle existe
        if (! $this->select($this->equation))
        	return $this->showError('La référence demandée n\'existe pas.');

        // Si sélection contient plusieurs enreg, erreur
        if ($this->selection->count() > 1)
            return $this->showError('Vous ne pouvez pas éditer plusieurs enregistrements à la fois.');     

        // Détermine le template à utiliser
        if (! $template=$this->getTemplate())
            throw new Exception('Le template à utiliser n\'a pas été indiqué');
        
        // Détermine le callback à utiliser
        $callback=$this->getCallback();
        
        Template::run
        (
            $template,
            array($this, $callback),
            $this->selection->record  
        );             
    }
    
    /**
     * Sauvegarde la notice désignée par 'REF' avec les champs passés en
     * paramètre.
     * Redirige ensuite l'utilisateur vers l'action 'show'
     * 
     * REF doit toujours être indiqué. Si REF==0, une nouvelle notice sera
     * créée. Si REF>0, la notice correspondante sera écrasée. Si REF est absent
     * ou invalide, une exception est levée.
     */
    public function actionSave()
    {
        // CODE DE DEBUGGAGE : save ne sauvegarde pas la notice si Runtime::redirect ne se termine
        // pas par exit(0) (voir plus bas)
		
		// TODO: dans la config, on devrait avoir, par défaut, access: admin (ie base modifiable uniquement par les admin)
		
        // Détermine le callback à utiliser
        $callback=$this->getCallback();
        
        // Par défaut, le callback du save est à 'none'. Le module descendant DOIT définit un callback pour pouvoir modifier la base 
        if ($callback === 'none')
            throw new Exception("Cette base n'est pas modifiable (aucun callback définit pour le save"); 
                
        // Si REF n'a pas été transmis ou contient autre chose qu'un entier >= 0, erreur
        if (is_null($ref=Utils::get($_REQUEST['REF'])) || (! ctype_digit($ref)))
            throw new Exception('Appel incorrect de save : REF non transmis ou invalide');
        
        $ref=(int) $ref;  // TODO: dangereux, si ref n'est pas un entier, générer une exception
        
        // Ouvre la base
        $this->openDatabase(false);
        
        // Si un numéro de référence a été indiqué, on charge cette notice         
        if ($ref>0)
        {
            // Ouvre la sélection
            debug && Debug::log('Chargement de la notice numéro %s', $ref);
            
            if (! $this->select("REF=$ref"))
                throw new Exception('La référence demandée n\'existe pas');
                
            $this->selection->editRecord();     // mode édition enregistrement
        } 
        // Sinon (REF == 0), on en créée une nouvelle
        else
        {
            debug && Debug::log('Création d\'une nouvelle notice');
            $this->selection->addRecord();            
        }            
        
        // Mise à jour de chacun des champs
        foreach($this->selection->record as $fieldName => $fieldValue)
        {         
            if ($fieldName==='REF') continue;   // Pour l'instant, REF non modifiable codé en dur
                
            $fieldValue=Utils::get($_REQUEST[$fieldName], null); // TODO: ne devrait pas être là, à charge pour le callback de savoir d'où viennent les données

//            // Si la valeur est un tableau, convertit en articles séparés par le séparateur 
//            if (is_array($fieldValue))
//                $fieldValue=implode('/', array_filter($fieldValue)); // TODO : comment accéder au séparateur ???
                
            // Appelle le callback qui peut :
            // - indiquer à l'application d'interdire la modification du champ
            // - ou modifier sa valeur avant l'enregistrement (validation données utilisateur)
            if ($this->$callback($fieldName, $fieldValue) === true)
            {
                // Met à jour le champ
                $this->selection[$fieldName]=$fieldValue;
            }
        }
        
        // Enregistre la notice
        $ref=$this->selection->saveRecord();   // TODO: gestion d'erreurs

        // Récupère le numéro de la notice créée
        //$ref=$this->selection['REF'];
        debug && Debug::log('Sauvegarde de la notice %s', $ref);

        // redirige vers le template s'il y en a un, vers l'action show sinon
        if (! $template=$this->getTemplate())
        {
            // Redirige l'utilisateur vers l'action show
            debug && Debug::log('Redirection pour afficher la notice enregistrée %s', $ref);
            Runtime::redirect('/base/show?REF='.$ref);
        }
        else
        {
            Template::run
            (
                $template,
                array('equationAnswers'=>'NA'),
                $this->selection->record,
                array('selection',$this->selection)  
            );
        }
    }
    
    /**
     * callback pour l'action save autorisant la modification de tous les champs.
     * Par défaut, le callback de actionSave est à 'none'. Cette fonction est une facilité offerte
     * à l'utilisateur pour lui éviter d'avoir à écrire un callback à chaque fois : 
     * il suffit de créer un pseudo module et dans la clé save.callback de la config de ce
     * module de metre la valeur 'allowSave' 
     */
    public function allowSave($name, &$value)
    {
        return true;
    }
    
    /**
     * Supprime la ou les notice(s) indiquée(s) par l'équation puis affiche le template
     * indiqué dans la clé 'template' de la configuration.
     * 
     * Si aucun template n'est indiqué, affiche un message 'notice supprimée'.
     */
    public function actionDelete()
    {
        // Ouvre la base de données (le nouveau makeEquation en a besoin)
        $this->openDatabase(false);

        // Récupère l'équation de recherche qui donne les enregistrements à supprimer
        $this->equation=$this->selection->makeEquation(Utils::isGet() ? $_GET : $_POST);

        // Paramètre equation manquant
        if (is_null($this->equation) || (! $this->equation))
            return $this->showError('Le ou les numéros des notices à supprimer n\'ont pas été indiqués.');

        // Aucune réponse
        if (! $this->select($this->equation, array('_max'=>-1)) )
            return $this->showError("Aucune réponse. Equation : $this->equation");

        // TODO: déléguer au TaskManager

//        echo 'Delete - Nb notices : ', $this->selection->count(),"\n";
////        echo '<pre>';
////        echo print_r($_GET);
////        echo '</pre>';
////        echo 'fin';
//        echo 'Equation Delete :', $this->equation,"<br />";
////        $nb=0;
//        foreach($this->selection as $record)
//        {
//        	echo $record['REF'],';';
//        }
//        die();

        // Supprime toutes les notices de la sélection
//        while ($this->selection->count())
//            $this->selection->deleteRecord();
        foreach($this->selection as $record)
            $this->selection->deleteRecord();

        // Détermine le template à utiliser
        if (! $template=$this->getTemplate())
            echo '<p>Notice supprimée.</p>';

        // Détermine le callback à utiliser
        $callback=$this->getCallback();

        // Exécute le template
        Template::run
        (
            $template,  
            array($this, $callback),
            $this->selection->record  
        );
    }
    
    /**
     * Affiche le formulaire de type Chercher/Remplacer indiqué dans la clé
     * 'template' de la configuration
     */
     public function actionReplaceForm()
     {        
        // Ouvre la base de données (le nouveau makeEquation en a besoin)
        $this->openDatabase();

        // Récupère l'équation de recherche qui donne les enregistrements sur lesquels travailler
        $this->equation=$this->selection->makeEquation(Utils::isGet() ? $_GET : $_POST);

        // Paramètre equation manquant
        if (is_null($this->equation) || (! $this->equation))
            return $this->showError('Vous n\'avez indiqué aucun critère de recherche sur les enregistrements de la base de données.');
            
        // Aucune réponse
        if (! $this->select($this->equation, array('_max'=>-1)) )
            return $this->showError("Aucune réponse. Equation : $this->equation");

        // Construit le tableau des champs modifiables des enregistrements retournés par la recherche.
        // Par compatibilité avec les générateurs de contrôles utilisateurs (fichier generators.xml)
        // il faut un tableau de tableaux contenant chacun une clé 'code' et une clé 'label'
        // On suppose que la sélection peut contenir des enregistrements provenants de différentes tables (pas la même structure)
        $fieldList = array();   // le tableau global qui contient les tableaux de champs
        
        // Si on est certain de n'avoir que des enregistrements de même nature (même noms de champs),
        // on peut vouloir boucler sur un seul enregistrement (au lieu de tous à l'heure actuelle)
        // Cependant, dans le cas de nombreuses BDD relationnelles, une selection peut être composé d'enreg
        // de nature différente (différentes tables)
        // TODO: optimisation possible si on a un fichier structure BDD de bas niveau
        
        $ignore = array('REF');  // liste des champs à ignorer : REF plus ceux déjà ajoutés à $fieldList
        
        foreach($this->selection as $record)
        {
            $newField = array();    // un tableau par champ trouvé
            
            foreach($record as $fieldName => $fieldValue)
            {
                if (! in_array($fieldName, $ignore))   // REF n'est pas modifiable
                {
                    $newField['code'] = $fieldName;
                    $newField['label'] = $fieldName;    // on affichera directement le code du champ tel que dans la BDD
                    $fieldList[] = $newField;           // ajoute au tableau global
                    $ignore[] = $fieldName;             // pour ne pas le rajouter la prochaine fois qu'on le trouve
                }
            }
        }
        
        // Tri le tableau des champs modifiables
        sort($fieldList);

        // Détermine le template à utiliser
        if (! $template=$this->getTemplate())
            throw new Exception('Le template à utiliser n\'a pas été indiqué');
        
        // Détermine le callback à utiliser
        $callback=$this->getCallback();
               
        // Exécute le template
        Template::run
        (
            $template,  
            array($this, $callback),           // Priorité à la fonction utilisateur
            array('fieldList'=>$fieldList)
        );
     }
    
    /**
     * Effectue le chercher/remplacer et appelle le template indiqué dans la clé
     * template de la configuration ensuite : feedback
     * 
     * La source de donnée $count est passé à Template::run et permet au template d'afficher
     * s'il y a eu une erreur ($count === false) ou le nombre de remplacements effectués s'il n'y a pas d'erreur
     * ($count contient alors le nombre d'occurences remplacées)
     *  
     */
     public function actionReplace()
     {       
        // Ouvre la base de données (le nouveau makeEquation en a besoin)
        $this->openDatabase(false);

        // Récupère l'équation de recherche qui donne les enregistrements sur lesquels travailler
        $this->equation=$this->selection->makeEquation(Utils::isGet() ? $_GET : $_POST);
        
        // TODO : Pb avec les équations qui ont des guillemets
        
        $search=Utils::get($_REQUEST['search'], '');
        $replace=Utils::get($_REQUEST['replaceStr'], '');
        $fields = (array) Utils::get($_REQUEST['fields']);
        
        $wholeWord=is_null(Utils::get($_REQUEST['wholeWord'])) ? false : true;
        $caseInsensitive=is_null(Utils::get($_REQUEST['caseInsensitive'])) ? false : true;
        $regExp=is_null(Utils::get($_REQUEST['regExp'])) ? false : true;
        
        // Vérifie que les données sont renseignées
        if ($this->equation==='')
            return $this->showError('Vous n\'avez indiqué aucun critère de recherche sur les enregistrements de la base de données.');

        // S'il n'y a rien à faire,on redirige vers replaceform
        if (count($fields)==0 || ($search==='' && $replace===''))
            Runtime::redirect('replaceform?_equation=' . urlencode($this->equation));
            
        // Lance la requête qui détermine les enregistrements sur lesquels on va opérer le chercher/remplacer 
        if (! $this->select($this->equation, array('_max'=>-1)) )
            return $this->showError("Aucune réponse. Equation : $this->equation");

        // Eventuelle callback de validation des données passée au format array(object, nom méthode) 
//        if (($callback = $this->getCallback()) !== 'none')
//            $callback = array($this, $callback);
//        else
//            $callback = null;

        $count = 0;         // nombre de remplacements effectués par enregistrement
        $totalCount = 0;    // nombre total de remplacements effectués sur le sous-ensemble de notices
        
        // TODO: déléguer le boulot au TaskManager (exécution peut être longue)

        // Search est vide : on injecte la valeur indiquée par replace dans les champs vides
        if ($search==='')
        {
            foreach($this->selection as $record)
            {          
                $this->selection->editRecord(); // on passe en mode édition de l'enregistrement
                $this->selection->replaceEmpty($fields, $replace, $count);             
                $this->selection->saveRecord();
                $totalCount += $count;
            }
        }
        
        // chercher/remplacer sur exp reg ou chaîne
        else        
        {
            if ($regExp || $wholeWord)
            {
                // expr reg ou alors chaîne avec 'Mot entier' sélectionné
                // dans ces deux-cas, on appellera pregReplace pour simplier

                // échappe le '~' éventuellement entré par l'utilisateur car on l'utilise comme délimiteur
                $search = str_replace('~', '\~', $search);
                
                if ($wholeWord)
                    $search = $search = '~\b' . $search . '\b~';
                else
                    $search = '~' . $search . '~';  // délimiteurs de l'expression régulière
                    
                if ($caseInsensitive)
                    $search = $search . 'i';

                foreach($this->selection as $record)
                {          
                    $this->selection->editRecord(); // on passe en mode édition de l'enregistrement
                    
                    if (! $this->selection->pregReplace($fields, $search, $replace, $count))    // cf. Database.php
                    {
                        $totalCount = false;
                        break;   
                    }    
                    
                    $this->selection->saveRecord();
                    $totalCount += $count;
                }
            }
            
            // chercher/remplacer sur une chaîne
            else
            {
                foreach($this->selection as $record)
                {
                    $this->selection->editRecord(); // on passe en mode édition de l'enregistrement
//                    $this->selection->strReplace($fields, $search, $replace, $caseInsensitive, $count, $callback);     // cf. Database.php
                    $this->selection->strReplace($fields, $search, $replace, $caseInsensitive, $count);
                    $this->selection->saveRecord();
                    $totalCount += $count;
                }
            }
        }
        
        // Détermine le template à utiliser
        if (! $template=$this->getTemplate('template'))
            throw new Exception('Le template à utiliser n\'a pas été indiqué');
        
        // Détermine le callback à utiliser
        $callback=$this->getCallback();

        // Exécute le template
        Template::run
        (
            $template,  
            array($this, $callback),
            array('count'=>$totalCount)
        ); 
     }
     
     
     
    // ****************** fonctions surchargeables ***************
    /**
     * Retourne le template à utiliser pour l'action en cours ({@link action})
     * 
     * Par défaut, la fonction retourne le nom du template indiqué dans la clé
     * 'template' du fichier de config, mais les descendants de BisDatabase
     * peuvent surcharger cette fonction pour retourner autre chose (utile par
     * exemple si le template à utiliser dépends de certaines conditions telles
     * que les droits qu'a l'utilisateur en cours).
     * 
     * Dans le fichier de configuration, vous pouvez indiquer soit le nom d'un 
     * template qui sera utilisé dans tous les cas, soit un tableau qui va
     * permettre d'indiquer le template à utiliser en fonction des droits de
     * l'utilisateur en cours.
     * 
     * Dans ce cas, les clés du tableau indiquent le droit à avoir et la valeur
     * le template à utiliser. Le tableau est examiné dans l'ordre indiqué. A
     * vous d'organiser les clés pour que les droits les plus restrictifs
     * apparaissent en premier.
     * 
     * Remarque : Vous pouvez utiliser le pseudo droit 'default' pour indiquer
     * le template à utiliser lorsque l'utilisateur ne dispose d'aucun des
     * droits indiqués.
     * 
     * @return string le nom du template à utiliser ou null
     */
    private function configUserGet($key, $default=null)
    {
        $value=Config::get($key);
        if (is_null($value))
            return $default;

        if (is_array($value))
        {
            foreach($value as $right=>$value)
                if (User::hasAccess($right)) 
                    return $value;
            return $default;
        }
        
        return $value;
    }
     
    protected function getTemplate($key='template')
    {
        debug && Debug::log('%s : %s', $key, $this->ConfigUserGet($key));
        if (! $template=$this->ConfigUserGet($key)) 
            return null;
            
        if (file_exists($h=$this->path . $template)) // pb : template relatif à BisDatabase, pas au module hérité (si seulement config)
            return $h;
            
        return $template;
    }

    public function none($name)
    {
    }
    
    protected function getCallback()
    {
        debug && Debug::log('callback : %s', $this->ConfigUserGet('callback'));
        return $this->ConfigUserGet('callback'); 
    }
    
    public function getFilter()
    {
        debug && Debug::log('Filtre de recherche : %s', $this->ConfigUserGet('filter'));
        return $this->ConfigUserGet('filter');  
    }
    
    public function getField($name)
    {
        
    }

    public function setField($name, &$value)
    {
        global $selection;
        
        switch ($name)
        {
            case 'Creation':
                if (! $selection->field($name))
                    $value=date('Ymd');
                else
                    $value=$selection->field($name);
                break;
//                if (empty($value)) $value=date('Ymd');
//                break;
                    
            case 'LastUpdate':
                $value=date('Ymd');
                break;
        }
    }
    
    // ****************** fonctions privées ***************
    
/**
     * Crée une équation à partir des paramètres de la requête.
     * 
     * Les paramètres qui ont le même nom sont combinés en 'OU', les paramètres
     * dont le nom diffère sont combinés en 'ET'.
     * 
     * Un même paramètre peut être combiné plusieurs fois : les différentes
     * valeurs seront alors combinés en 'OU'.
     * 
     * Un paramètre peut contenir une simple valeur, une expression entre
     * guillemets doubles ou une équation de recherche combinant des valeurs ou
     * des expressions avec des opérateurs. Les opérateurs seront remplacés
     * lorsqu'ils ne figurent pas dans une expression. (avec tit="a ou b"&tit=c
     * on obtient tit="a ou b" et tit=c et non pas tit="a ou tit=b" et tit=c)
     * 
     * Lors de la création de l'équation, les paramètres dont le nom commence
     * par 'bq', les noms 'module' et 'action' ainsi que le nom utilisé
     * comme identifiant de session (sessin_name) sont ignorés. Vous pouvez
     * indiquer des noms supplémentaires à ignorer en passant dans le paramètre
     * ignore une chaine contenant les noms à ignorer (séparés par une virgule).
     * Par exemple si votre formulaire a des paramètres nommés '_max' et '_order',
     * ceux-ci seront par défaut pris en compte pour construire l'équation (vous
     * obtiendrez quelque chose du style "tit=xxx et max=100 et order=1", ce qui
     * en général n'est pas le résultat souhaité). Pour obtenir le résultat
     * correct, indiquez la chaine "_max, _order" en paramètre.
     * 
     * @param string $ignore optionnel, paramètres supplémentaires à ignorer, 
     * séparés par une virgule (remarque : insensible à la casse).
     * 
     * @return mixed soit une chaine contenant l'équation obtenue, soit une
     * chaine vide si tous les paramètres passés étaient vides (l'utilisateur a
     * validé le formulaire de recherche sans rien remplir), soit null si aucun
     * paramètre n'a été passé dans la requête (l'utilisateur a simplement
     * demandé l'affichage du formulaire de recherche)
     */
    public function makeEquationOld($ignore='')
    {
        // si '_equation' a été transmis, on prend tel quel
        if (! is_null($equation = Utils::get($_REQUEST['_equation']))) return $equation;

        // Sinon, construit l'équation de recherche à partir des param de la requête
        if ($ignore) $ignore='|' . str_replace(',', '|', preg_quote($ignore, '~'));
        $namesToIgnore="~module|action|bq.+|".preg_quote(session_name())."$ignore~i";
        
        $equation='';
        $hasFields=false;
        
        // Boucle sur les attributs passés
        foreach((Utils::isGet() ? $_GET : $_POST) as $name=>$value)
        {
            
            if (preg_match($namesToIgnore, $name)) continue;    
            
            $hasFields=true; // il y a au moins un nom de champ non ignoré passé en paramètre

            if (! is_array($value))
                if ($value=='') continue; else $value=array($value);    // $value sous forme de tableau
                
            $h='';
            
            $parent=false;
            
            // Boucle sur chaque valeur d'un attribut donné (exemple: s'il y a plusieurs champs Dates dans
            // un formulaire, l'attribut Dates aura plusieurs valeurs)
            
//            echo "value = ", print_r($value), "<br />";
            
            foreach ($value as $item)
            {
//                echo "item = $item<br />";

                if ($item == '') continue;
                
                // remplace 'et/ou/sauf' par 'op $name=', mais pas dans les trucs entre guillemets
                $t=explode('"',$item);
                
//                echo "t = ", print_r($t), "<br />";
                $parent=false;
                for ($i=0; $i<count($t); $i+=2)
                {
                    $nb=0;
                    if ($t[$i])
                    {
                        $t[$i]=str_ireplace
                        (
                            array(' ou ', ' or ', ' et ', ' and ', ' sauf ', ' and not ', ' but '),
                            array(" OU $name=", " OR $name=", " ET $name=", " AND $name=", " sauf $name=", " sauf $name=", " sauf $name="),
                            $t[$i],
                            $nb
                        );
                        if ($nb) $parent=true;
                    }
                }
                $item="$name=" . implode('"',$t);
                $item=preg_replace('~'.$name.'\s*=((?:\s*\()+)~', '\1' . $name . '=', $item);
                $item=preg_replace('~\s+~', ' ', $item);
                $item=preg_replace('~=\s+~', '=', $item);
//                $item=implode('"',$t);
//                $item=preg_replace('~\(([^\(])~', '(/'.$name.'/=\1', $item);
                if ($parent) $item='(' . $item . ')';
                
                if ($h) 
                {
                    $h.=" ou $item"; 
                    $parent=true;
                }
                else
                {
                    $h=$item;
                    $parent=false;
                }
            }
                                    
            if ($parent) $h='(' . $h . ')';
            if ($h) if ($equation) $equation .= ' AND ' . $h; else $equation=$h;
        }

//        echo "makeEquation: equation = $equation<br />";
        if ($hasFields) return $equation; else return null;
    }
    
    // lookup dans une table des entrées (xapian only)
    function actionLookup()
    {
//        header('Content-type: text/plain; charset=iso-8859-1');
        header('Content-type: text/html; charset=iso-8859-1');
//        var_export($_POST,true);
        
        // Ouvre la base
        $this->openDatabase();
        
        // Récupère le nom de la table dans laquelle il faut rechercher
        if ('' === $table=Utils::get($_REQUEST['table'],''))
            die('aucune table indiquée');
        
        // Récupère le terme recherché
        $search=Utils::get($_REQUEST['value'],'');
        $max=Utils::get($_REQUEST['max'],10);
        
        // Lance la recherche
        $terms=$this->selection->lookup($table, $search, $max, 0, true);

        // Détermine le template à utiliser
        if (! $template=$this->getTemplate())
            throw new Exception('Le template à utiliser n\'a pas été indiqué');
        
        // Détermine le callback à utiliser
        $callback=$this->getCallback();

        // Exécute le template         
        Template::run
        (
            $template,  
            array($this, $callback),
            array('search'=>$search, 'table'=>$table, 'terms'=>$terms)
        );  
    }
    
    /**
     * Charge la liste des formats d'export disponibles dans la config en cours
     * 
     * Seuls les formats auxquels l'utilisateur a accès sont chargés (paramètre access de chaque format).
     * Les formats chargés sont ajoutés dans la configuration en cours dans la clé 'formats'.
     * Config::get('formats') retourne la liste de tous les formats
     * Config::get('formats.csv') retourne les paramètres d'un format particulier.
     * 
     * @return int le nombre de formats chargés
     */
    private function loadExportFormats()
    {
        // Détermine le nom du fichier contenant la liste des formats d'export disponibles
        $name=Config::get('list', 'export.yaml');
        
        // Recherche le fichier indiqué, dans le répertoire du module, puis dans celui de son ancêtre, etc.
        if (false === $path=Utils::searchFile($name))
            throw new Exception("Impossible de trouver le fichier $name");

        // Charge la liste des formats d'export disponibles
        $formats=Config::loadFile($path);
        
        // Ne garde que les formats auquel l'utilisateur a accès
        foreach($formats as $name=>& $format)
        {
        	if (isset($format['access']) && ! User::hasAccess($format['access']))
                unset($formats[$name]);
            if (!isset($format['label'])) $format['label']=$name;
        }
        
        // Ajoute les formats dans la config
        Config::addArray($formats, 'formats');

        // Initialise format['max'] pour permettre un accès simple depuis le template
        // idéalement, devrait être fait dans la boucle au dessus, mais configUserGet()
        // ne sait travailler que sur la config, pas sur un tableau donc on charge le tableau
        // on l'ajoute à la config, puis on modifie la config... 
        foreach($formats as $name=>& $format)
            Config::set("formats.$name.max", $this->configUserGet("formats.$name.max",300));

        // Retourne le nombre de formats chargés
        return count($formats);
    }
    
    /** 
     * Export à partir d'une équation de recherche passée en paramètre
     * 
     * Les formats d'export disponibles sont listés dans un fichier dont le
     * nom est indiqué dans la clé 'list' de l'action 'export'.
     * 
     * Première étape : affichage de la liste des formats disponibles et
     * sélection par l'utilisateur du type d'export à faire (envoi par email ou
     * affichage déchargement de fichier)
     * 
     * Seconde étape : exécution du template correspondant au format d'export 
     * choisi en indiquant le type mime correct.
     */
    public function actionExport($calledFromPreExecute=false)
    {
        $showForm=false;
        $why=array();
        
        // Charge la liste des formats d'export disponibles
        if (!$this->loadExportFormats())
            throw new Exception("Aucun format d'export n'est disponible");
        
        // Choix du format d'export
        if(count(Config::get('formats'))===1)                       // Un seul format dispo : on prend celui-là
        {
            foreach(Config::get('formats') as $format=>$fmt);       // point-virgule à la fin, voulu !
                // boucle vide, exécutée une seule fois, juste pour initialiser $format et $fmt
        }
        else                                                        // L'utilisateur a le choix du format
        {
            $format=Utils::get($_REQUEST['_format']);               // Regarde dans la query string
            if ($format)
            {
                $fmt=Config::get("formats.$format");                // et vérifie que le format existe et est autorisé
                if(is_null($fmt))
                    throw new Exception("Format d'export incorrect");
            }
            else
            {
                $showForm=true; // on ne sait pas quel format utiliser
                $why[]='quel format faut-il utiliser ?';
            }
        }
                
        // Option "envoi par mail"
        if ($mail=(bool)Config::get('allowmail'))                   // seulement si l'option mail est autorisée dans la config
        {
            if(! $mail=(bool)Config::get('forcemail'))              // option forcemail à true, on envoie toujours un mail
            {
                if (isset($_REQUEST['_mail']))                      // sinon, on regarde si on a l'option dans la query string
                {
                    $mail=(bool)Utils::get($_REQUEST['_mail']);         
                }
                else
                {
                    $showForm=true;
                    $why[]='faut-il envoyer un mail ou non ?';
                }
            }        
            $to=$subject=$body=null;
            if($mail)                                               // s'il faut envoyer un mail, vérifie qu'on a un destinataire
            {
                if (!$to=Utils::get($_REQUEST['_to']))              // pas de destinataire : demande à l'utilisateur
                {
                    $showForm=true;
                    $why[]='à qui faut-il envoyer le mail ?';
                }
                else                                                // récupère l'objet et le message, valeurs par défaut si absents
                {
                    $subject=Utils::get($_REQUEST['_subject'], 'Export de notices');
                    $body=Utils::get($_REQUEST['_body'], 'Le fichier ci-joint contient les notices sélectionnées.');
                }
            }
        }
        
        // Equation(s) de recherche à exécuter
        if (!$equations=Config::get('equation') )                    // équation(s) fixée(s) en dur dans la config, on prend
            if (!$equations=Utils::get($_REQUEST['_equation']))      // sinon, doi(ven)t être transmis(es) en query string          
                throw new Exception('Aucune équation de recherche indiquée');
        $equations=(array)$equations;

        // Option "archive au format ZIP"
        if ($zip=(bool)Config::get('allowzip') )                    // seulement si l'option zip est autorisée dans la config
        {
            
            if(! $zip=(bool)Config::get('forcezip'))                // option forcezip à true, on crée toujours un zip
            {
                if (isset($_REQUEST['_zip']))
                {
                    $zip=(bool)Utils::get($_REQUEST['_zip']);       // sinon, on regarde si on a l'option dans la query string
                }
                else
                {
                    $showForm=true;
                    $why[]='faut-il créer une archive au format zip ?';
                }
            }
            if ($zip && ! class_exists('ZipArchive'))                   // zip demandé mais l'extension php_zip n'est pas chargée dans php.ini
                throw new Exception("La création de fichiers ZIP n'est pas possible sur ce serveur");
        }

        // TODO: a revoir, que faire si on a plusieurs fichiers et que php_zip.dll n'est pas chargée ?
        // Si plusieurs équations et ni zip ni mail, problème (on ne peut pas envoyer plusieurs fichiers au navigateur !)
        if (count($equations)>1 && !$mail && !$showForm)
        {
            $zip=true;
            if (! class_exists('ZipArchive'))                   // zip demandé mais l'extension php_zip n'est pas chargée dans php.ini
                throw new Exception("dmdmLa création de fichiers ZIP n'est pas possible sur ce serveur");
        }
    
        if ($showForm)
        {
            if($calledFromPreExecute) return false;
            
            // Détermine le template à utiliser
            if (! $template=$this->getTemplate('template'))
                throw new Exception('Le template à utiliser n\'a pas été indiqué');
            
            // Détermine le callback à utiliser
            $callback=$this->getCallback();
            
            // Exécute le template
            Template::run
            (
                $template,
                array($this, $callback),
                array
                (
                    'equations'=>$equations,
                    'why'=>$why,
                )
            );
            return true;
        }
        
        // Tous les paramètres ont été vérifiés, on est prêt à faire l'export
        
        // TODO : basculer vers le TaskManager si nécessaire
        /* 
            si mail -> TaskManager
            si plusieurs fichiers -> TaskManager

            la difficulté :
                - on exécute addTask()
                - message "Le fichier est en cours de génération"
                - (on attend)
                - (la tâche s'exécute)
                - message "cliquez ici pour décharger le fichier généré"
                
                ou
                - redirection vers TaskManager/TaskStatus?id=xxx
                (l'utilisateur a accès au TaskManager ??? !!!)
            difficulté supplémentaire :
                - ça fait quoi quand on clique sur le lien ?            
        */
        // Extrait le nom de fichier indiqué dans le format dans la clé content-disposition
        $basename='export%s';
        if ($contentDisposition=Utils::get($fmt['content-disposition']))
        {
            if (preg_match('~;\s?filename\s?=\s?(?:"([^"]+)"|([^ ;]+))~i', $contentDisposition, $match))
            {
                $basename=Utils::get($match[2], $match[1]);
                if (stripos($basename, '%s')===false)
                {
                	$basename=Utils::setExtension($basename,'').'%s'.Utils::getExtension($basename);
                }
            }
        }

        // Génère un nom de fichier unique pour chaque fichier en utilisant l'éventuel filename passé en query string
        $filename=Utils::get($_REQUEST['filename'],'');
        $filenames=array();
        foreach($equations as $i=>$equation)
        {
            $h=is_array($filename) ? Utils::get($filename[$i],'') : $filename;
            if ($h) $h='-' . $h;
            $j=1;
            $result=sprintf($basename, $h);
            while(in_array($result, $filenames))
                $result=sprintf($basename, $h.''.(++$j));

            $filenames[$i]=$result;
        }

        // Détermine le nombre maximum de notices que l'utilisateur a le droit d'exporter
        $max=$this->ConfigUserGet("formats.$format.max",10);

        // Ouvre la base de données (le nouveau makeEquation en a besoin)
        $this->openDatabase();

        // Exécute toutes les recherches dans l'ordre
        $files=array();
        foreach($equations as $i=>$equation)
        {        
            // Lance la recherche, si aucune réponse, erreur
            if (! $this->select($equation, array('_sort'=>'+', '_start'=>0, '_max'=>$max)))
            {
            	echo "Aucune réponse pour l'équation $equation<br />";
                continue;
            }
            
            //echo 'Génération fichier ',$filenames[$i],' pour équation ', $equation, '<br />';
            
            // Si l'utilisateur a demandé un envoi par mail, démarre la capture
            if ($mail or $zip)
            {
            	Utils::startCapture();
            }
            else
            {
                // Sinon, définit les entêtes http du fichier généré
                if (isset($fmt['content-type']))
                    header('content-type: ' . $fmt['content-type']);
        
                if (isset($fmt['content-disposition']))
                    header('content-disposition: ' . $fmt['content-disposition']);
            }
        
            // Si un générateur spécifique a été définit pour ce format, on l'exécute
            if (isset($fmt['generator']))
            {
                $generator=trim($fmt['generator']);
                if (strtolower($generator)!=='')
                    $this->$generator($fmt);
            }
    
            // Sinon, on utilise le générateur par défaut
            else
            {
                // Détermine le template à utiliser
                if (! $template=$this->ConfigUserGet("formats.$format.template"))
                {
                    if ($mail or $zip) Utils::endCapture(); // sinon on ne "verra" pas l'erreur
                    throw new Exception("Le template à utiliser pour l'export en format $format n'a pas été indiqué");
                }

                // Détermine le callback à utiliser
                $callback=$this->ConfigUserGet("formats.$format.callback"); 
        
                // Exécute le template
                Template::run
                (
                    $template,
                    array($this, $callback),
                    array('format'=>$format),
                    $this->selection->record
                );
            }
        
            // Pour un export classique, on a fini
            if (!($mail or $zip)) continue;
        
            // Termine la capture du fichier d'export généré et stocke le nom du fichier temporaire
            $files[$i]=Utils::endCapture();
            
        }

        // Si l'option zip est active, crée le fichier zip
        if ($zip)
        {
        	$zipFile=new ZipArchive();
            $f=Utils::getTempFile(0, 'export.zip');
            $zipPath=Utils::getFileUri($f);
            fclose($f);
            if (!$zipFile->open($zipPath, ZipArchive::OVERWRITE))
            	throw new Exception('Impossible de créer le fichier zip');
//            if (!$zipFile->setArchiveComment('Fichier exporté depuis le site ascodocpsy')) // non affiché par 7-zip            
//                throw new Exception('Impossible de créer le fichier zip - 1');
            foreach($files as $i=>$path)
            {
                if (!$zipFile->addFile($files[$i], $filenames[$i]))
                    throw new Exception('Impossible de créer le fichier zip - 2');
                if (!$zipFile->setCommentIndex($i, Utils::convertString($equations[$i],'CP1252 to CP850')))            
                    throw new Exception('Impossible de créer le fichier zip - 3');
            }
            if (!$zipFile->close())            
                throw new Exception('Impossible de créer le fichier zip - 4');
                
            // Si l'option mail n'est pas demandée, envoie le zip
            if (!$mail)
            {
                header('content-type: application/zip');// type mime 'officiel', source : http://en.wikipedia.org/wiki/ZIP_(file_format)
                header('content-disposition: attachment; filename="export.zip"');
                readfile($zipPath);
                return true;
            }
        }

        if (!$mail)
            return true;
            
        // Charge les fichiers Swift
		require_once Runtime::$fabRoot . 'lib/Swift/Swift.php';
		require_once Runtime::$fabRoot . 'lib/Swift/Swift/Connection/SMTP.php';

 		// Crée une nouvelle connexion Swift
        $swift = new Swift(new Swift_Connection_SMTP(ini_get('SMTP'))); // TODO: mettre dans la config de fab pour ne pas être obligé de changer php.ini
        $swift->log->enable();

        // Force swift à utiliser un cache disque pour minimiser la mémoire utilisée
        Swift_CacheFactory::setClassName("Swift_Cache_Disk");
        Swift_Cache_Disk::setSavePath(Utils::getTempDirectory());        
 		
 		// Crée le message
		$message = new Swift_Message($subject);
        
        // Crée le corps du message
        // TODO: regarder dans la config de l'action export si on a une clé 'mailTemplate' remplie
        // $this->configUserGet()
        /*
            Template::run
            (
                $emailTemplate, 
                array
                (
                    'message'=>$body,            // le message tapé par l'utilisateur dans le formulaire
                    'filenames'=>$filenames,
                    'equations'=>$equations,
                    'counts'=>$counts
                    filesizes
                    
                )
            )
           
           (message de l'utilisateur)
           ci-joint n fichiers au format 'CSV public tout champs':
             - fichier $filenames[i], xxx réponses, équation: fdsfsdfsd
         */
		$message->attach(new Swift_Message_Part($body, 'text/plain'));

		// Met les pièces attachées
        $swiftFiles=array(); // Grrr... Swift ne ferme pas les fichiers avant l'appel à destruct. Garde un handle dessus pour pouvoir appeller nous même $file->close();
        if ($zip)
        {
    		$swiftFiles[0]=new Swift_File($zipPath);
            $message->attach(new Swift_Message_Attachment($swiftFiles[0], 'export.zip', 'application/zip'));
        }
        else
        {
            foreach($files as $i=>$path)
            {
                $swiftFiles[$i]=new Swift_File($path);
                $mimeType=strtok($fmt['content-type'],';');
                $message->attach(new Swift_Message_Attachment($swiftFiles[$i], $filenames[$i], $mimeType));
//                $piece->setDescription($equations[$i]);
            }
        }
        	
		// Envoie le mail
        $from=new Swift_Address(Config::get('admin.email'), Config::get('admin.name'));
        $error='';
        try
        {
    		$sent=$swift->send($message, $to, $from);
        }
        catch (Exception $e)
        {
            $sent=false;
            $error=$e->getMessage();
        }

        // HACK: ferme "à la main" les pièces jointes de swift, sinon le capture handler ne peut pas supprimer les fichiers temporaires
        foreach($swiftFiles as $file)
            $file->close();

        if ($sent)
            echo 'Mail envoyé'; // TODO: avoir un template (mais on n'a pas de layout) ou redir vers actionMailSent (c'est bof)
        else
        {
            echo '<h1>Erreur</h1>';
            echo "<fieldset>Impossible d'envoyer l'e-mail à l'adresse <strong><code>$to</code></strong></fieldset>";
            if ($error)
                echo "<p>Erreur retournée par le serveur : <strong><code>$error</code></strong></p>";
            echo "<fieldset><legend>Log de la transaction</legend> <pre>";
            $swift->log->dump();
            echo "</pre></fieldset>";
        }
        return true;
    }

    // exemple de générateur en format xml simple pour les formats d'export
    public function exportXml($format)
    {
        echo '<','?xml version="1.0" encoding="iso-8889-1"?','>', "\n";
        echo '<database>', "\n"; 
    	foreach($this->selection as $record)
        {
            echo '  <record>', "\n"; 
        	foreach($record as $field=>$value)
            {
            	if ($value)
                {
                    if (is_array($value))
                    {
                        if (count($value)===1)
                        {
                            echo '    <', $field, '>', htmlspecialchars($value[0],ENT_NOQUOTES), '</', $field, '>', "\n";
                        }
                        else
                        {
                            echo '    <', $field, '>', "\n";
                            foreach($value as $item)
                                echo '      <item>', htmlspecialchars($item,ENT_NOQUOTES), '</item>', "\n";
                            echo '    </', $field, '>', "\n";
                        }
                    }
                    else
                    {
                        echo '    <', $field, '>', htmlspecialchars($value,ENT_NOQUOTES), '</', $field, '>', "\n";
                    }
                } 
            }
            echo '  </record>', "\n"; 
        }
        echo '</database>', "\n"; 
    }
    
    public function actionTest()
    {
        // Ouvre la base de données (le nouveau makeEquation en a besoin)
        //$this->openDatabase();
        echo "test des nouvelles fonctions le version 1.0.2 de xapian";
        require_once Runtime::$fabRoot . 'lib/xapian/xapian.php';
        
        $db=new XapianWritableDatabase(dirname(__FILE__).'/testspell.db', Xapian::DB_CREATE_OR_OVERWRITE); // TODO: remettre DB_CREATE
//        $db=Xapian::inmemory_open();
        
        $indexer=new XapianTermGenerator();
        
        $doc=new XapianDocument();
        $indexer->set_document($doc);
        $indexer->set_database($db);
        $indexer->set_flags(XapianTermGenerator::FLAG_SPELLING);
        
        $text=
        "
Le réseau BDSP est un groupement d’organismes dont la gestion est assurée 
par l'Ecole nationale de la santé publique.
Créé à l’initiative de la Direction générale de la santé, il développe depuis 
1993 des services d'information en ligne destinés aux professionnels des 
secteurs sanitaire et social. Notre site Web a pour objectif de donner accès, 
grâce à des outils de navigation raisonnée, à une très large palette de 
sources d’informations.
La coordination du réseau, sous la direction d’un Comité de pilotage national, 
est assurée, à l'ENSP (Rennes – France), par l'Atelier d'études et 
développement de la BDSP.        
        ";
        
        echo "<h1>Texte à indexer</h1><pre>$text</pre>";
        $text=utf8_encode($text);
        
        $indexer->index_text_without_positions($text,2);

        echo "<h1>Liste des tokens créés par TermGenerator::index_text()</h1>";
        $begin=$doc->termlist_begin();
        $end=$doc->termlist_end();
        while(!$begin->equals($end))
        {
        	echo '<li><code>', utf8_decode($begin->get_term()), "</code></li>";
            $begin->next();
        }
        

        $docId=$db->add_document($doc);
        echo "docid: $docId <br />";

        echo "<h1>Spellings</h1>";
        $begin=$db->spellings_begin();
        $end=$db->spellings_end();
        while(!$begin->equals($end))
        {
            echo '<li><code>', utf8_decode($begin->get_term()), "</code></li>";
            $begin->next();
        }
//        $db->add_spelling('hello');
        
        echo "<h1>Essai de correction orthographique</h1>";
        foreach(array('sante','renne', 'rézau', 'ravigation', 'rational', 'BDSP') as $word)
            echo '<li>', $word, ' -&gt; ', utf8_decode($db->get_spelling_suggestion($word)), '</li>';

        echo "<h1>stemming</h1>";
        echo "Languages disponibles : ", XapianStem::get_available_languages(), "<br />";
        $stemmer=new XapianStem('french');
        foreach(array('hopitaux','documentation', 'réseau', 'armements', 'tests', 'BDSP') as $word)
            echo '<li>', $word, ' -&gt; ', $stemmer->apply($word), '</li>';
                    
        unset($db);        
        
        echo "<h1>Sérialisation entiers/doubles</h1>";
        for($i=-10; $i<1000000; $i+=128)
        {
            $h=Xapian::sortable_serialise($i);

            echo "<li>$i -&gt;", "len=", strlen($h), ', =';
            var_dump(Xapian::sortable_unserialise($h));
            echo  '</li>';
        }
        
        // todo : test avec les MatchSpy
    }
}


?>