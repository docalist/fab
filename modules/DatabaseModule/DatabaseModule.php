<?php

/**
 * @package     fab
 * @subpackage  modules
 * @author      Daniel M�nard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id: Database.php 105 2006-09-21 16:30:25Z dmenard $
 */

class Optional implements ArrayAccess
{
    private $data=null;
    
	public function __construct($data)
    {
    	if (! is_array($data))
            throw new Exception('Vous devez passer un tableau pour cr�er un objet '.get_class());
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
 * Ce module permet de publier une base de donn�es sur le web
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
     * @var Database La s�lection en cours
     * @access protected
     */
    public $selection=null;

    /**
     * Ouvre la base de donn�es du module
     * 
     * Si une �quation est indiqu�e, une recherche est lanc�e.
     * 
     * @param string $equation optionnel, l'�quation de recherche � lancer
     * 
     * @param readOnly indique si la base doit �tre ouverte en lecture seule
     * (valeur par d�faut) ou en lecture/�criture
     * 
     * @return boolean true si une recherche a �t� lanc�e et qu'on a au moins 
     * une r�ponse, false sinon
     */
    protected function openDatabase($readOnly=true)
    {
        // Le fichier de config du module indique la base � utiliser
        $database=Config::get('database');

        if (is_null($database))
            throw new Exception('La base de donn�es � utiliser n\'a pas �t� indiqu�e dans le fichier de configuration du module');
        
        debug && Debug::log("Ouverture de la base '%s' en mode '%s'", $database, $readOnly ? 'lecture seule' : 'lecture/�criture');
        $this->selection=Database::open($database, $readOnly);
    }

    protected function select($equation, $options=null)
    {
		// Valeurs par d�faut des options
	    $defaultOptions=array
	    (
	        '_sort'  => Utils::get($_REQUEST['_sort'], Config::get('sort','+')),
	        '_start' => Utils::get($_REQUEST['_start'], 1),
	        '_max'   => Utils::get($_REQUEST['_max'], Config::get('max',10)),
	    );

        if (is_array($options))
        {
        	// On fusionne le tableau d'options pass� en param�tre et les options par d�faut
        	// On prend par d�faut $defaultOptions. Si $options red�finit la valeur d'une option,
        	// alors c'est cette nouvelle valeur qui sera prise en compte.
        	return $this->selection->search($equation,array_merge($defaultOptions, $options));
        }
        else
        {
        	return $this->selection->search($equation, $defaultOptions);
        }
    }
   
    /**
     * Affiche le formulaire de recherche indiqu� dans la cl� 'template' de la
     * configuration, en utilisant le callback indiqu� dans la cl� 'callback' de
     * la configuration. Si le callback indiqu� est 'none' alors aucun callback 
     * n'est appliqu�.
     * 
     */
    public function actionSearchForm()
    {        
        // D�termine le template � utiliser
        if (! $template=$this->getTemplate())
            throw new Exception('Le template � utiliser n\'a pas �t� indiqu�');
        
        // D�termine le callback � utiliser
        $callback=$this->getCallback();
                
        // Ex�cute le template
        Template::run
        (
            $template,  
            array($this, $callback)           // Priorit� � la fonction utilisateur
        );
    }

    /**
     * Affiche un message si une erreur s'est produite lors de la recherche.
     * Le template � utiliser est indiqu� dans la cl� 'errortemplate' de la 
     * configuration de l'action 'search'.
     * 
     * @param $error string le message d'erreur a afficher (pass� � Template::run) via
     * la source de donn�e 'error'
     */
    public function showError($error='')
    {
        // D�termine le template � utiliser
        if (! $template=$this->getTemplate('errortemplate'))
        {
        	echo $error ? $error : 'Une erreur est survenue pendant le traitement de la requ�te';
            return;
        }

        // D�termine le callback � utiliser
        $callback=$this->getCallback();

        // Ex�cute le template
        Template::run
        (
            $template,  
            array($this, $callback),
            array('error'=>$error)
        );
    }
    
    
    
    /**
     * Affiche un message si aucune r�ponse n'est associ�e la recherche.
     * Le template � utiliser est indiqu� dans la cl� 'noanswertemplate' de la 
     * configuration de l'action 'search'.
     * 
     * @param $message string le message a afficher (pass� � Template::run) via
     * la source de donn�e 'message'
     * 
     */
    public function showNoAnswer($message='')
    {
        // D�termine le template � utiliser
        if (! $template=$this->getTemplate('noanswertemplate'))
        {
            echo $message ? $message : 'La requ�te n\'a retourn� aucune r�ponse';
            return;
        }

        // D�termine le callback � utiliser
        $callback=$this->getCallback();
        
        // Ex�cute le template
        Template::run
        (
            $template,  
            array($this, $callback),
            array('message'=>$message)
        );
    }
    
    /**
     * Lance une recherche si une �quation peut �tre construite � partir des 
     * param�tres pass�s et affiche les notices obtenues en utilisant le template 
     * indiqu� dans la cl� 'template' de la configuration.
     * Si aucun param�tre n'a �t� pass�, redirige vers le formulaire de recherche.
     * Si erreur lors de la recherche, affiche l'erreur en utilisant le template
     * indiqu� dans la cl� 'errortemplate' de la configuration. 
     * 
     * Le template peut ensuite boucler sur {$this->selection} pour afficher les r�sultats
     */
    public function actionSearch()
    {
        // Ouvre la base de donn�es (le nouveau makeEquation en a besoin)
        $this->openDatabase();

        // D�termine la recherche � ex�cuter        
        $this->equation=$this->selection->makeEquation(Utils::isGet() ? $_GET : $_POST);

        // Si aucun param�tre de recherche n'a �t� pass�, il faut afficher le formulaire
        // de recherche
        if (is_null($this->equation))
            Runtime::redirect('searchform');
        
        // Des param�tres ont �t� pass�s, mais tous sont vides et l'�quation obtenue est vide
        if ($this->equation==='')
            return $this->showError('Vous n\'avez indiqu� aucun crit�re de recherche.');
        
        // Aucune r�ponse
        if (! $this->select($this->equation))
            return $this->showNoAnswer("La requ�te $this->equation n'a donn� aucune r�ponse.");
        
        // D�termine le template � utiliser
        if (! $template=$this->getTemplate('template'))
            throw new Exception('Le template � utiliser n\'a pas �t� indiqu�');
        
        // D�termine le callback � utiliser
        $callback=$this->getCallback();

        // Ex�cute le template
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
     * A partir d'une s�lection ouverte et de la queryString, retourne la barre de navigation entre
     * les diff�rentes pages de r�sultats (au format XHTML).
     * Peut-�tre appel�e directement depuis un template
     * 
     * @param $actionName string l'action qui donne les r�sultats pour lesquels on cr�� une barre de navigation
     * @param $maxLinks integer le nombre de liens maximum � afficher dans la barre de navigation
     * @param $prevLabel string le libell� du lien vers la page pr�c�dente
     * @param $nextLabel string le libelle du lien vers la page suivante
     * @param $firstLabel string le libelle du lien vers la premi�re page de r�sultats pour la s�lection (cha�ne vide si aucun)
     * @param $lastLabel string le libelle du lien vers la derni�re page de r�sultats pour la s�lection (cha�ne vide si aucun)
     * 
     * @return cha�ne XHTML correspond � la barre de navigation ou une cha�ne vide s'il n'y a qu'une seule page � afficher
     */
    public function getSimpleNav($prevLabel = '&lt;&lt; Pr�c�dent', $nextLabel = 'Suivant &gt;&gt;')
    {
        // la base de la query string pour la requ�te
        $query=$_GET;
        unset($query['_start']);
        unset($query['module']);
        unset($query['action']);
        $query=self::buildQuery($query);
        
        $actionName = $this->action;    // on adapte l'URL en fonction de l'action en cours (search, show, ...)

        $start=$this->selection->searchInfo('start');
        $max= $this->selection->searchInfo('max');
        $count=$this->selection->count();
        
        $this->module=strtolower($this->module); // BUG : this->module devrait �tre tel que recherch� par les routes
        $url='/'.$this->module.'/'.$this->action.'?'.$query;

        $h='R�sultats ' . $start.' � '.min($start+$max-1,$count) . ' sur environ '.$count. ' ';
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
        // la base de la query string pour la requ�te
        $queryStr=$_GET;
        unset($queryStr['_start']);
        unset($queryStr['module']);
        unset($queryStr['action']);
        $baseQueryString=self::buildQuery($queryStr);
        
        $actionName = $this->action;    // on adapte l'URL en fonction de l'action en cours (search, show, ...)

        $currentStart = $this->selection->searchInfo('start');  // num dans la s�lection du premi�re enreg de la page en cours 
        $maxRes = $this->selection->searchInfo('max');          // le nombre de r�ponses max par page
        
        $startParam = 1;                // le param start pour les URL des liens g�n�r�s dans la barre de navigation
        $pageNum = 1;                   // le premier num�ro de page � g�n�r� comme lien dans la barre de navigation
        $navBar = '<span class="navbar">';                   // la barre de navigation au format XHTML
        
        // num�ro de la page dont les r�sultats sont affich�s
        $currentPage = intval(($currentStart - 1) / $maxRes) + 1;
        
        // num�ro de la derni�re page de r�sultats pour la s�lection en cours (ind�pendamment de $maxLinks)
        $lastSelPageNum = ($this->selection->count() % $maxRes) == 0 ? (intval($this->selection->count()/$maxRes)) : (intval($this->selection->count()/$maxRes)+1);
        // num�ro de page du dernier lien qu'on affiche (<= $lastSelPageNum suivant la val de $maxLinks)
        $lastDispPageNum = $lastSelPageNum;    
        
        // Ajustement de la valeur des variable pour la gestion de la "fen�tre" de liens : nombre de liens � afficher...
        if ($maxLinks < $lastSelPageNum)
        {
            // nombre de liens � afficher avant le num�ro de la page courante dans la barre
            // de navigation en supposant que le num�ro de la page courante sera centr� sur celle-ci
            $numLinksBefore = intval(($maxLinks - 1) / 2);
            
            // ici, le premier num�ro de page � afficher dans la barre de navigation ($pageNum) vaut 1
            // le recalcule si n�cessaire
            if ( ($currentPage - $numLinksBefore) >= 1 )
            {
                if (($currentPage + ($maxLinks - $numLinksBefore - 1)) > $lastSelPageNum)
                {
                    // on va afficher le lien vers la derni�re page de r�sultats pour la s�lection
                    // le num de la page courante ne sera pas centr� sur la barre (sinon on afficherait 
                    // un ou des liens vers des pages de r�sultats inexistantes � droite)
                    $pageNum = $currentPage - ($maxLinks - ($lastSelPageNum - $currentPage + 1));
                }
                else
                {
                    $pageNum = ($currentPage - $numLinksBefore);
                }
            }
            
            $lastDispPageNum = $pageNum + ($maxLinks - 1);  // ajuste le num�ro de la derni�re page � afficher dans la barre
            $startParam =  ($pageNum - 1) * $maxRes + 1;    // ajuste $startParam pour le premier lien correspondant � un num de page
        }
        
        if ($pageNum < $lastDispPageNum)    // plusieurs pages de r�sultats : g�n�re les liens 
        {            
            // lien "page pr�c�dente" et �ventuel lien vers la premi�re page
            if ($currentPage > 1)
            {
                if ( ($firstLabel != '') && ($pageNum > 1) )    // afficher lien vers la premi�re page ?
                    $navBar = $navBar . '<span class="firstPage"><a href="' . $actionName . '?' . $baseQueryString . "&_start=1" . '">' . $firstLabel . '</a></span> ';
                    
                // TODO: ligne suivante n�cessaire ?
                $prevStart = $currentStart-$maxRes >=1 ? $currentStart-$maxRes : 1; // param start pour le lien vers la page pr�c�dente
                $navBar = $navBar . '<span class="prevPage"><a href="' . $actionName . '?' . $baseQueryString . "&_start=$prevStart" . '">' . $prevLabel . '</a></span> ';
            }
            
            // g�n�re les liens vers chaque num�ro de page de r�sultats
            for($pageNum; $pageNum <= $lastDispPageNum; ++$pageNum)
            {
                if($startParam == $currentStart)    // s'il s'agit du num�ro de la page qu'on va afficher, pas de lien
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
            
            // lien "page suivante" et �ventuellement, lien vers la derni�re page de la s�lection
            if (($currentPage < $lastSelPageNum))
            {
                // TODO : ligne comment�e suivante n�cessaire ?
//                $nextStart = $currentStart+$maxRes <= $this->selection->count() ? $currentStart+$maxRes : ;
                $nextStart = $currentStart + $maxRes;   // param start pour le lien vers la page suivante
                $navBar = $navBar . '<span class="nextPage"><a href="' . $actionName . '?' . $baseQueryString . "&_start=$nextStart" . '">' . $nextLabel . '</a></span> ';
                
                if ( ($lastLabel != '') && ($lastDispPageNum < $lastSelPageNum) )   // afficher lien vers la derni�re page ?
                {
                    $startParam = ($this->selection->count() % $maxRes) == 0 ? $this->selection->count() - $maxRes + 1 : intval($this->selection->count() / $maxRes) * $maxRes + 1;
                    $navBar = $navBar . '<span class="lastPage"><a href="' . $actionName . '?' . $baseQueryString . "&_start=$startParam" . '">' . $lastLabel . '</a></span>';
                }
            }
            
            return $navBar . "</span>";
        }
        else    // une seule page � afficher : on ne renvoie pas de liens (cha�ne vide)
        {
            return '';   
        }
    }
    
    /**
     * Affiche une ou plusieurs notices en "format long"
     * Le(s) notice(s) � afficher sont donn�s par une equation de recherche
     * G�n�re une erreur si aucune �quation n'est accessible ou si elle ne retourne aucune notice
     * 
     * Le template instanci� peut ensuite boucler sur {$this->selection} pour afficher les r�sultats
     */
    public function actionShow()
    {
        // Ouvre la base de donn�es (le nouveau makeEquation en a besoin)
        $this->openDatabase();

        // D�termine la recherche � ex�cuter        
        $this->equation=$this->selection->makeEquation(Utils::isGet() ? $_GET : $_POST);

        // Si aucun param�tre de recherche n'a �t� pass�, erreur
        if (is_null($this->equation))
            return $this->showError('Le num�ro de la r�f�rence � afficher n\'a pas �t� indiqu�.');

        // Des param�tres ont �t� pass�s, mais tous sont vides et l'�quation obtenue est vide
        if ($this->equation==='')
            return $this->showError('Vous n\'avez indiqu� aucun crit�re permettant de s�lectionner la notice � afficher.');

        // Aucune r�ponse
        if (! $this->select($this->equation))
            return $this->showNoAnswer("La requ�te $this->equation n'a donn� aucune r�ponse.");

        // D�termine le template � utiliser
        if (! $template=$this->getTemplate())
            throw new Exception('Le template � utiliser n\'a pas �t� indiqu�');
        
        // D�termine le callback � utiliser
        $callback=$this->getCallback();

        // Ex�cute le template         
        Template::run
        (
            $template,  
            array($this, $callback),
            $this->selection->record
        );  
    }
    
    
    /**
     * Cr�ation d'une notice
     * Affiche le formulaire indiqu� dans la cl� 'template' de la configuration.
     * 
     * Lui passe la source de donn�e 'REF' = 0 pour indiquer � l'action save qu'on cr�� une nouvelle notice
     *
     */
    public function actionNew()
    {    
        // D�termine le template � utiliser
        if (! $template=$this->getTemplate())
            throw new Exception('Le template � utiliser n\'a pas �t� indiqu�');
            
        $callback = $this->getCallback();
        
        // On ex�cute le template correspondant
        Template::run
        (
            $template,
            array('REF'=>'0'),        // indique qu'on veut cr�er une nouvelle notice 
            array($this, $callback)
        );      
    } 
    
    /**
     * Edition d'une notice
     * Affiche le formulaire indiqu� dans la cl� 'template' de la configuration.
     * 
     * La notice correspondant � l'�quation donn�e est charg�e dans le formulaire : l'�quation ne doit
     * retourner qu'un seul enregistrement sinon erreur.
     */
    public function actionLoad()
    {
        // Ouvre la base de donn�es (le nouveau makeEquation en a besoin)
        $this->openDatabase();

        // D�termine la recherche � ex�cuter        
        $this->equation=$this->selection->makeEquation(Utils::isGet() ? $_GET : $_POST);

        // Erreur, si aucun param�tre de recherche n'a �t� pass�
        // Erreur, si des param�tres ont �t� pass�s, mais tous sont vides et l'�quation obtenue est vide
        if (is_null($this->equation) || $this->equation==='')
        	return $this->showError('Le num�ro de la r�f�rence � modifier n\'a pas �t� indiqu�.');

        // Si un num�ro de r�f�rence a �t� indiqu�, on charge cette notice         
        // V�rifie qu'elle existe
        if (! $this->select($this->equation))
        	return $this->showError('La r�f�rence demand�e n\'existe pas.');

        // Si s�lection contient plusieurs enreg, erreur
        if ($this->selection->count() > 1)
            return $this->showError('Vous ne pouvez pas �diter plusieurs enregistrements � la fois.');     

        // D�termine le template � utiliser
        if (! $template=$this->getTemplate())
            throw new Exception('Le template � utiliser n\'a pas �t� indiqu�');
        
        // D�termine le callback � utiliser
        $callback=$this->getCallback();
        
        Template::run
        (
            $template,
            array($this, $callback),
            $this->selection->record  
        );             
    }
    
    /**
     * Sauvegarde la notice d�sign�e par 'REF' avec les champs pass�s en
     * param�tre.
     * Redirige ensuite l'utilisateur vers l'action 'show'
     * 
     * REF doit toujours �tre indiqu�. Si REF==0, une nouvelle notice sera
     * cr��e. Si REF>0, la notice correspondante sera �cras�e. Si REF est absent
     * ou invalide, une exception est lev�e.
     */
    public function actionSave()
    {
        // CODE DE DEBUGGAGE : save ne sauvegarde pas la notice si Runtime::redirect ne se termine
        // pas par exit(0) (voir plus bas)
//        ftrace(str_repeat('-', 80));
//        ftrace('Entr�e dans actionSave');
		
		// TODO: dans la config, on devrait avoir, par d�faut, access: admin (ie base modifiable uniquement par les admin)
		
        // D�termine le callback � utiliser
        $callback=$this->getCallback();
        
        // Par d�faut, le callback du save est � 'none'. Le module descendant DOIT d�finit un callback pour pouvoir modifier la base 
        if ($callback === 'none')
            throw new Exception("Cette base n'est pas modifiable (aucun callback d�finit pour le save"); 
                
        // Si REF n'a pas �t� transmis ou contient autre chose qu'un entier >= 0, erreur
        if (is_null($ref=Utils::get($_REQUEST['REF'])) || (! ctype_digit($ref)))
            throw new Exception('Appel incorrect de save : REF non transmis ou invalide');
        
        $ref=(int) $ref;  // TODO: dangereux, si ref n'est pas un entier, g�n�rer une exception
        
        // Ouvre la base
        $this->openDatabase(false);
        
        // Si un num�ro de r�f�rence a �t� indiqu�, on charge cette notice         
        if ($ref>0)
        {
            // Ouvre la s�lection
            debug && Debug::log('Chargement de la notice num�ro %s', $ref);
            
            if (! $this->select("REF=$ref"))
                throw new Exception('La r�f�rence demand�e n\'existe pas');
                
            $this->selection->editRecord();     // mode �dition enregistrement
        } 
        // Sinon (REF == 0), on en cr��e une nouvelle
        else
        {
            debug && Debug::log('Cr�ation d\'une nouvelle notice');
            $this->selection->addRecord();            
        }            
        
        // Mise � jour de chacun des champs
        foreach($this->selection->record as $fieldName => $fieldValue)
        {         
            if ($fieldName==='REF') continue;   // Pour l'instant, REF non modifiable cod� en dur
                
            $fieldValue=Utils::get($_REQUEST[$fieldName], null); // TODO: ne devrait pas �tre l�, � charge pour le callback de savoir d'o� viennent les donn�es

//            // Si la valeur est un tableau, convertit en articles s�par�s par le s�parateur 
//            if (is_array($fieldValue))
//                $fieldValue=implode('/', array_filter($fieldValue)); // TODO : comment acc�der au s�parateur ???
                
            // Appelle le callback qui peut :
            // - indiquer � l'application d'interdire la modification du champ
            // - ou modifier sa valeur avant l'enregistrement (validation donn�es utilisateur)
            if ($this->$callback($fieldName, $fieldValue) === true)
            {
                // Met � jour le champ
                $this->selection[$fieldName]=$fieldValue;
            }
        }
        
        // Enregistre la notice
        $ref=$this->selection->saveRecord();   // TODO: gestion d'erreurs

        // R�cup�re le num�ro de la notice cr��e
        //$ref=$this->selection['REF'];
        debug && Debug::log('Sauvegarde de la notice %s', $ref);

        // redirige vers le template s'il y en a un, vers l'action show sinon
        if (! $template=$this->getTemplate())
        {
            // Redirige l'utilisateur vers l'action show
            debug && Debug::log('Redirection pour afficher la notice enregistr�e %s', $ref);
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
     * Par d�faut, le callback de actionSave est � 'none'. Cette fonction est une facilit� offerte
     * � l'utilisateur pour lui �viter d'avoir � �crire un callback � chaque fois : 
     * il suffit de cr�er un pseudo module et dans la cl� save.callback de la config de ce
     * module de metre la valeur 'allowSave' 
     */
    public function allowSave($name, &$value)
    {
        return true;
    }
    
    /**
     * Supprime la ou les notice(s) indiqu�e(s) par l'�quation puis affiche le template
     * indiqu� dans la cl� 'template' de la configuration.
     * 
     * Si aucun template n'est indiqu�, affiche un message 'notice supprim�e'.
     */
    public function actionDelete()
    {
        // Ouvre la base de donn�es (le nouveau makeEquation en a besoin)
        $this->openDatabase();

        // R�cup�re l'�quation de recherche qui donne les enregistrements � supprimer
        $this->equation=$this->selection->makeEquation(Utils::isGet() ? $_GET : $_POST);

        // Param�tre equation manquant
        if (is_null($this->equation) || (! $this->equation))
            return $this->showError('Le ou les num�ros des notices � supprimer n\'ont pas �t� indiqu�s.');

        // Aucune r�ponse
        if (! $this->select($this->equation, array('_max'=>-1)) )
            return $this->showError("Aucune r�ponse. Equation : $this->equation");

        // TODO: d�l�guer au TaskManager

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

        // Supprime toutes les notices de la s�lection
        while ($this->selection->count())
            $this->selection->deleteRecord();
        
        // D�termine le template � utiliser
        if (! $template=$this->getTemplate())
            echo '<p>Notice supprim�e.</p>';

        // D�termine le callback � utiliser
        $callback=$this->getCallback();

        // Ex�cute le template
        Template::run
        (
            $template,  
            array($this, $callback),
            $this->selection->record  
        );
    }
    
    /**
     * Affiche le formulaire de type Chercher/Remplacer indiqu� dans la cl�
     * 'template' de la configuration
     */
     public function actionReplaceForm()
     {        
        // Ouvre la base de donn�es (le nouveau makeEquation en a besoin)
        $this->openDatabase();

        // R�cup�re l'�quation de recherche qui donne les enregistrements sur lesquels travailler
        $this->equation=$this->selection->makeEquation(Utils::isGet() ? $_GET : $_POST);

        // Param�tre equation manquant
        if (is_null($this->equation) || (! $this->equation))
            return $this->showError('Vous n\'avez indiqu� aucun crit�re de recherche sur les enregistrements de la base de donn�es.');
            
        // Aucune r�ponse
        if (! $this->select($this->equation, array('_max'=>-1)) )
            return $this->showError("Aucune r�ponse. Equation : $this->equation");

        // Construit le tableau des champs modifiables des enregistrements retourn�s par la recherche.
        // Par compatibilit� avec les g�n�rateurs de contr�les utilisateurs (fichier generators.xml)
        // il faut un tableau de tableaux contenant chacun une cl� 'code' et une cl� 'label'
        // On suppose que la s�lection peut contenir des enregistrements provenants de diff�rentes tables (pas la m�me structure)
        $fieldList = array();   // le tableau global qui contient les tableaux de champs
        
        // Si on est certain de n'avoir que des enregistrements de m�me nature (m�me noms de champs),
        // on peut vouloir boucler sur un seul enregistrement (au lieu de tous � l'heure actuelle)
        // Cependant, dans le cas de nombreuses BDD relationnelles, une selection peut �tre compos� d'enreg
        // de nature diff�rente (diff�rentes tables)
        // TODO: optimisation possible si on a un fichier structure BDD de bas niveau
        
        $ignore = array('REF');  // liste des champs � ignorer : REF plus ceux d�j� ajout�s � $fieldList
        
        foreach($this->selection as $record)
        {
            $newField = array();    // un tableau par champ trouv�
            
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
        
        // D�termine le template � utiliser
        if (! $template=$this->getTemplate())
            throw new Exception('Le template � utiliser n\'a pas �t� indiqu�');
        
        // D�termine le callback � utiliser
        $callback=$this->getCallback();
               
        // Ex�cute le template
        Template::run
        (
            $template,  
            array($this, $callback),           // Priorit� � la fonction utilisateur
            array('fieldList'=>$fieldList)
        );
     }
    
    /**
     * Effectue le chercher/remplacer et appelle le template indiqu� dans la cl�
     * template de la configuration ensuite : feedback
     * 
     * La source de donn�e $count est pass� � Template::run et permet au template d'afficher
     * s'il y a eu une erreur ($count === false) ou le nombre de remplacements effectu�s s'il n'y a pas d'erreur
     * ($count contient alors le nombre d'occurences remplac�es)
     *  
     */
     public function actionReplace()
     {       
        // Ouvre la base de donn�es (le nouveau makeEquation en a besoin)
        $this->openDatabase(false);

        // R�cup�re l'�quation de recherche qui donne les enregistrements sur lesquels travailler
        $this->equation=$this->selection->makeEquation(Utils::isGet() ? $_GET : $_POST);
        
        // TODO : Pb avec les �quations qui ont des guillemets + le OpenSelection donne les 10 premi�res ?
        
        $search=Utils::get($_REQUEST['search'], '');
        $replace=Utils::get($_REQUEST['replaceStr'], '');
        $fields = (array) Utils::get($_REQUEST['fields']);
        
        $wholeWord=is_null(Utils::get($_REQUEST['wholeWord'])) ? false : true;
        $caseInsensitive=is_null(Utils::get($_REQUEST['caseInsensitive'])) ? false : true;
        $regExp=is_null(Utils::get($_REQUEST['regExp'])) ? false : true;
        
        // V�rifie que les donn�es sont renseign�es
        if ($this->equation==='')
            return $this->showError('Vous n\'avez indiqu� aucun crit�re de recherche sur les enregistrements de la base de donn�es.');

        // S'il n'y a rien � faire,on redirige vers replaceform
        if (count($fields)==0 || ($search==='' && $replace===''))
            Runtime::redirect('replaceform?_equation=' . urlencode($this->equation));
            
        // Lance la requ�te qui d�termine les enregistrements sur lesquels on va op�rer le chercher/remplacer 
        if (! $this->select($this->equation, array('_max'=>-1)) )
            return $this->showError("Aucune r�ponse. Equation : $this->equation");

        // Eventuelle callback de validation des donn�es pass�e au format array(object, nom m�thode) 
//        if (($callback = $this->getCallback()) !== 'none')
//            $callback = array($this, $callback);
//        else
//            $callback = null;

        $count = 0;         // nombre de remplacements effectu�s par enregistrement
        $totalCount = 0;    // nombre total de remplacements effectu�s sur le sous-ensemble de notices
        
        // TODO: d�l�guer le boulot au TaskManager (ex�cution peut �tre longue)

        // Search est vide : on injecte la valeur indiqu�e par replace dans les champs vides
        if ($search==='')
        {
            foreach($this->selection as $record)
            {          
                $this->selection->editRecord(); // on passe en mode �dition de l'enregistrement
                $this->selection->replaceEmpty($fields, $replace, $count);             
                $this->selection->saveRecord();
                $totalCount += $count;
            }
        }
        
        // chercher/remplacer sur exp reg ou cha�ne
        else        
        {
            if ($regExp || $wholeWord)
            {
                // expr reg ou alors cha�ne avec 'Mot entier' s�lectionn�
                // dans ces deux-cas, on appellera pregReplace pour simplier

                // �chappe le '~' �ventuellement entr� par l'utilisateur car on l'utilise comme d�limiteur
                $search = str_replace('~', '\~', $search);
                
                if ($wholeWord)
                    $search = $search = '~\b' . $search . '\b~';
                else
                    $search = '~' . $search . '~';  // d�limiteurs de l'expression r�guli�re
                    
                if ($caseInsensitive)
                    $search = $search . 'i';

                foreach($this->selection as $record)
                {          
                    $this->selection->editRecord(); // on passe en mode �dition de l'enregistrement
                    
                    if (! $this->selection->pregReplace($fields, $search, $replace, $count))    // cf. Database.php
                    {
                        $totalCount = false;
                        break;   
                    }    
                    
                    $this->selection->saveRecord();
                    $totalCount += $count;
                }
            }
            
            // chercher/remplacer sur une cha�ne
            else
            {
                foreach($this->selection as $record)
                {
                    $this->selection->editRecord(); // on passe en mode �dition de l'enregistrement
//                    $this->selection->strReplace($fields, $search, $replace, $caseInsensitive, $count, $callback);     // cf. Database.php
                    $this->selection->strReplace($fields, $search, $replace, $caseInsensitive, $count);
                    $this->selection->saveRecord();
                    $totalCount += $count;
                }
            }
        }
        
        // D�termine le template � utiliser
        if (! $template=$this->getTemplate('template'))
            throw new Exception('Le template � utiliser n\'a pas �t� indiqu�');
        
        // D�termine le callback � utiliser
        $callback=$this->getCallback();

        // Ex�cute le template
        Template::run
        (
            $template,  
            array($this, $callback),
            array('count'=>$totalCount)
        ); 
     }
     
     
     
    // ****************** fonctions surchargeables ***************
    /**
     * Retourne le template � utiliser pour l'action en cours ({@link action})
     * 
     * Par d�faut, la fonction retourne le nom du template indiqu� dans la cl�
     * 'template' du fichier de config, mais les descendants de BisDatabase
     * peuvent surcharger cette fonction pour retourner autre chose (utile par
     * exemple si le template � utiliser d�pends de certaines conditions telles
     * que les droits qu'a l'utilisateur en cours).
     * 
     * Dans le fichier de configuration, vous pouvez indiquer soit le nom d'un 
     * template qui sera utilis� dans tous les cas, soit un tableau qui va
     * permettre d'indiquer le template � utiliser en fonction des droits de
     * l'utilisateur en cours.
     * 
     * Dans ce cas, les cl�s du tableau indiquent le droit � avoir et la valeur
     * le template � utiliser. Le tableau est examin� dans l'ordre indiqu�. A
     * vous d'organiser les cl�s pour que les droits les plus restrictifs
     * apparaissent en premier.
     * 
     * Remarque : Vous pouvez utiliser le pseudo droit 'default' pour indiquer
     * le template � utiliser lorsque l'utilisateur ne dispose d'aucun des
     * droits indiqu�s.
     * 
     * @return string le nom du template � utiliser ou null
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
            
        if (file_exists($h=$this->path . $template)) // pb : template relatif � BisDatabase, pas au module h�rit� (si seulement config)
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
    
    // ****************** fonctions priv�es ***************
    
/**
     * Cr�e une �quation � partir des param�tres de la requ�te.
     * 
     * Les param�tres qui ont le m�me nom sont combin�s en 'OU', les param�tres
     * dont le nom diff�re sont combin�s en 'ET'.
     * 
     * Un m�me param�tre peut �tre combin� plusieurs fois : les diff�rentes
     * valeurs seront alors combin�s en 'OU'.
     * 
     * Un param�tre peut contenir une simple valeur, une expression entre
     * guillemets doubles ou une �quation de recherche combinant des valeurs ou
     * des expressions avec des op�rateurs. Les op�rateurs seront remplac�s
     * lorsqu'ils ne figurent pas dans une expression. (avec tit="a ou b"&tit=c
     * on obtient tit="a ou b" et tit=c et non pas tit="a ou tit=b" et tit=c)
     * 
     * Lors de la cr�ation de l'�quation, les param�tres dont le nom commence
     * par 'bq', les noms 'module' et 'action' ainsi que le nom utilis�
     * comme identifiant de session (sessin_name) sont ignor�s. Vous pouvez
     * indiquer des noms suppl�mentaires � ignorer en passant dans le param�tre
     * ignore une chaine contenant les noms � ignorer (s�par�s par une virgule).
     * Par exemple si votre formulaire a des param�tres nomm�s '_max' et '_order',
     * ceux-ci seront par d�faut pris en compte pour construire l'�quation (vous
     * obtiendrez quelque chose du style "tit=xxx et max=100 et order=1", ce qui
     * en g�n�ral n'est pas le r�sultat souhait�). Pour obtenir le r�sultat
     * correct, indiquez la chaine "_max, _order" en param�tre.
     * 
     * @param string $ignore optionnel, param�tres suppl�mentaires � ignorer, 
     * s�par�s par une virgule (remarque : insensible � la casse).
     * 
     * @return mixed soit une chaine contenant l'�quation obtenue, soit une
     * chaine vide si tous les param�tres pass�s �taient vides (l'utilisateur a
     * valid� le formulaire de recherche sans rien remplir), soit null si aucun
     * param�tre n'a �t� pass� dans la requ�te (l'utilisateur a simplement
     * demand� l'affichage du formulaire de recherche)
     */
    public function makeEquationOld($ignore='')
    {
        // si '_equation' a �t� transmis, on prend tel quel
        if (! is_null($equation = Utils::get($_REQUEST['_equation']))) return $equation;

        // Sinon, construit l'�quation de recherche � partir des param de la requ�te
        if ($ignore) $ignore='|' . str_replace(',', '|', preg_quote($ignore, '~'));
        $namesToIgnore="~module|action|bq.+|".preg_quote(session_name())."$ignore~i";
        
        $equation='';
        $hasFields=false;
        
        // Boucle sur les attributs pass�s
        foreach((Utils::isGet() ? $_GET : $_POST) as $name=>$value)
        {
            
            if (preg_match($namesToIgnore, $name)) continue;    
            
            $hasFields=true; // il y a au moins un nom de champ non ignor� pass� en param�tre

            if (! is_array($value))
                if ($value=='') continue; else $value=array($value);    // $value sous forme de tableau
                
            $h='';
            
            $parent=false;
            
            // Boucle sur chaque valeur d'un attribut donn� (exemple: s'il y a plusieurs champs Dates dans
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
    
    // lookup dans une table des entr�es (xapian only)
    function actionLookup()
    {
//        header('Content-type: text/plain; charset=iso-8859-1');
        header('Content-type: text/html; charset=iso-8859-1');
//        var_export($_POST,true);
        
        // Ouvre la base
        $this->openDatabase();
        
        // R�cup�re le nom de la table dans laquelle il faut rechercher
        if ('' === $table=Utils::get($_REQUEST['table'],''))
            die('aucune table indiqu�e');
        
        // R�cup�re le terme recherch�
        $search=Utils::get($_REQUEST['value'],'');
        $max=Utils::get($_REQUEST['max'],10);
        
        // Lance la recherche
        $terms=$this->selection->lookup($table, $search, $max, 0, true);

        // D�termine le template � utiliser
        if (! $template=$this->getTemplate())
            throw new Exception('Le template � utiliser n\'a pas �t� indiqu�');
        
        // D�termine le callback � utiliser
        $callback=$this->getCallback();

        // Ex�cute le template         
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