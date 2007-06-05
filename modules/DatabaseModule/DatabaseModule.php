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
            return $this->showError('Vous n\'avez indiqué aucun critère de recherche.');
        
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
//        ftrace(str_repeat('-', 80));
//        ftrace('Entrée dans actionSave');
		
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
                array('equationAnswers'=>'NA', 'ShowModifyBtn'=>false),
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
        $this->openDatabase();

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
        while ($this->selection->count())
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
                    $newField['label'] = $fieldName;    // on affichera directement le code du champs tel que dans la BDD
                    $fieldList[] = $newField;           // ajoute au tableau global
                    $ignore[] = $fieldName;             // pour ne pas le rajouter la prochaine fois qu'on le trouve
                }
            }
        }
        
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
        
        // TODO : Pb avec les équations qui ont des guillemets + le OpenSelection donne les 10 premières ?
        
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
    private function ConfigUserGet($key)
    {
        $value=Config::get($key);
        if (empty($value))
            return null;

        if (is_array($value))
        {
            foreach($value as $right=>$value)
                if (User::hasAccess($right)) 
                    return $value;
            return null;
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
}
function ftrace($h)
{
    $f=fopen(__FILE__ . '.txt', 'a');
    fwrite($f, $h . "\n");
    fclose($f);
}    
?>