<?php

/**
 * @package     fab
 * @subpackage  modules
 * @author      Daniel M�nard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id: Database.php 105 2006-09-21 16:30:25Z dmenard $
 */

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
    
    public function preExecute()
    {
        // HACK: permet � l'action export d'avoir un layout s'il faut afficher le formulaire de recherche
        // et de ne pas en avoir si tous les param�tres requis pour lancer l'export sont OK.
        // TODO: il faudrait que fab offre plus de flexibilit� sur le choix du layout
        // Actuellement, on peut d�finir le layout dans la config ou dans preExecute. Lorsque l'action
        // commence � s'ex�cuter, le d�but du layout a d�j� �t� envoy�. Il faudrait avoir un syst�me qui 
        // permette � l'action, jusqu'au dernier moment, de choisir le layout.
        // Pistes �tudi�es :
        // 1. action r�elle export avec layout (afficher le formulaire) et pseudo action doexport avec
        // layout none pour faire l'export � proprement parler.
        // 2. hack dans preExecute : si tous les param�tres sont bon, faire un setLayout() (ce qu'on fait ci-dessous)
        // 3. hack avec preExecute : appeller l'action d�s le preExecute, si l'action retourne true, arr�ter fab
        // sinon, continuer l'ex�cution normale (qui va appeller � nouveau l'action)
        // 4. (non test�) avoir une fonction runLayout() qu'on pourrait appeller dans l'action et qui ensuite appellerait
        // � nouveau l'action (tordu !)
        // 5. lazy layout magique : avoir un syst�me qui d�tecte le premier echo effectu� (un ob_handler) et qui, si un 
        // layout a �t� d�fini, commence � l'envoyer (suppose de savoir couper les layout en deux)
        // 6. lazy layout manuel : le layout n'est jamais envoy� automatiquement. L'action doit appeller startLayout() 
        // quand elle commmence � envoyer des donn�es. Dans Template::Run, un test if (!layoutSent) startLayout(), ce qui fait
        // que ce serait transparent pour toutes les actions qui se contente d'afficher un template.
        if ($this->method==='actionExport')
        {
            $defaultLayout=Config::get('layout');
            $this->setLayout('none');               // essaie de faire l'export, pour �a on met layout � none
            if ($this->actionExport(true)===true)   // l'export a �t� fait, termin�, demande � fab de s'arr�ter
                return true;
            $this->setLayout($defaultLayout);       // export non fait, il faut afficher le formulaire, remet le layout initial
        }
    }

    
    // *************************************************************************
    //                            ACTIONS DU MODULE
    // *************************************************************************
    
    /**
     * Affiche le formulaire de recherche permettant d'interroger la base
     * 
     * L'action searchForm affiche le template retourn� par la fonction
     * {@link getTemplate()} en utilisant le callback retourn� par la fonction
     * {@link getCallback()}.
     */
    public function actionSearchForm()
    {        
        // Ouvre la base de donn�es : permet au formulaire de recherche de consulter la structure
        $this->openDatabase();
        
        // D�termine le template � utiliser
        if (! $template=$this->getTemplate())
            throw new Exception('Le template � utiliser n\'a pas �t� indiqu�');
        
        // D�termine le callback � utiliser
        $callback=$this->getCallback();
                
        // Ex�cute le template
        Template::run
        (
            $template,  
            array($this, $callback)
        );
    }
    public function actionReindex()
    {
        //die('ne fonctionne pas, ne pas utiliser tant que le bug n\'aura pas �t� fix�');
        set_time_limit(0);
        $this->openDatabase(false);
        $this->selection->reindex();
        echo 'done';
    }
    
    /**
     * Lance une recherche dans la base et affiche les r�ponses obtenues
     * 
     * L'action search construit une �quation de recherche en appellant la
     * fonction {@link getEquation()}. L'�quation obtenue est ensuite combin�e
     * avec les filtres �ventuels retourn�s par la fonction {@link getFilter()}.
     * 
     * Si aucun crit�re de recherche n'a �t� indiqu�, un message d'erreur est
     * affich�.
     * 
     * Dans le cas contraire, la recherche est lanc�e et les r�sultats sont 
     * affich�s en utilisant le template retourn� par la fonction 
     * {@link getTemplate()} et la callback indiqu� par la fonction 
     * {@link getCallback()}.
     */
    public function actionSearch()
    {
        // Ouvre la base de donn�es
        $this->openDatabase();

        // D�termine la recherche � ex�cuter        
        $this->equation=$this->getEquation();

        // Affiche le formulaire de recherche si on n'a aucun param�tre
        if (is_null($this->equation))
        {
            if (! $this->request->hasParameters())
                Runtime::redirect('searchform'); // c'est la seule diff�rence avec ActionShow. Si on avait dans la config 'redirectToSearchForm yes/no', show pourrait �tre une pseudo action
            $this->showError('Vous n\'avez indiqu� aucun crit�re de recherche.');
            return;
        }

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

        // Ajoute la requ�te dans l'historique des �quations de recherche
        $history=$this->configUserGet('history', false);

        if ($history===true) $history=10;
        elseif ($history===false) $history=0;

        if (!is_int($history)) $history=0;
        if ($history>0 && ! Utils::isAjax())
            $this->updateSearchHistory($history);      
    }   
    
    private function & loadSearchHistory()
    {
        // Nom de la cl� dans la session qui stocke l'historique des recherches pour cette base
        $historyKey='search_history_'.Config::get('database');
        
        // R�cup�re l'historique actuel
        if (!isset($_SESSION[$historyKey])) $_SESSION[$historyKey]=array();
        return $_SESSION[$historyKey];
    }
    
    private function updateSearchHistory($maxHistory=10)
    {
        // Charge les sessions si ce n'est pas le cas (pas mis en config, comme �a la session n'est charg�e que si on en a besoin)
        Runtime::startSession();
        
        // Charge l'historique
        $hist=& $this->loadSearchHistory();
        
        // R�cup�re l'�quation � ajouter � l'historique
        $equation=$this->equation;
        $xapianEquation=$this->selection->searchInfo('internalfinalquery');
        
        // Cr�e une cl� unique pour l'�quation de recherche
        $key=crc32($xapianEquation); // ou md5 si n�cessaire
        
        // Si cette �quation figure d�j� dans l'historique, on la remet � la fin
        $number=null;
        if (isset($hist[$key]))
        {
            $number=$hist[$key]['number'];
            unset($hist[$key]);
        }
        
        while (count($hist)>$maxHistory-1)
        {
            reset($hist);
            unset($hist[key($hist)]);
        }

        // Attribue un num�ro � cette recherche
        if (is_null($number))
        {
            for($number=1; $number <=$maxHistory; $number++)
            {
                foreach($hist as $t)
                {
                    if ($t['number']==$number) continue 2;
                }
                break;
            }
        }
        
        // Ajoute l'�quation (� la fin)        
        $hist[$key]= array
        (
            'user' =>$equation,
            'xapian'=>$xapianEquation,
            'count'=>$this->selection->count('environ '),
            'time'=>time(),
            'number'=>$number
        );
            
//        echo 'Historique de recherche mis � jour : <br/>';
//        echo '<pre>', print_r($hist,true), '</pre>';
        
    }
    
    public function actionClearSearchHistory()
    {
        // Charge les sessions si ce n'est pas le cas (pas mis en config, comme �a la session n'est charg�e que si on en a besoin)
        Runtime::startSession();
        
        // Nom de la cl� dans la session qui stocke l'historique des recherches pour cette base
        $historyKey='search_history_'.Config::get('database'); // no dry / loadSearchHistory
        
        // R�cup�re l'historique actuel
        if (isset($_SESSION[$historyKey])) unset($_SESSION[$historyKey]);
        
        echo "Historique effac�.";
    }
    
    public function getSearchHistory()
    {
        return $this->loadSearchHistory();
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
        // Ouvre la base de donn�es
        $this->openDatabase();

        // D�termine la recherche � ex�cuter        
        $this->equation=$this->getEquation();

        // Si aucun param�tre de recherche n'a �t� pass�, erreur
        if (is_null($this->equation))
            return $this->showError('Vous n\'avez indiqu� aucun crit�re permettant de s�lectionner les notices � afficher.');

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
        // Ouvre la base de donn�es
        $this->openDatabase();

        // D�termine la recherche � ex�cuter        
        $this->equation=$this->getEquation();

        // Erreur, si aucun param�tre de recherche n'a �t� pass�
        // Erreur, si des param�tres ont �t� pass�s, mais tous sont vides et l'�quation obtenue est vide
        if (is_null($this->equation))
            return $this->showError('Vous n\'avez indiqu� aucun crit�re permettant de s�lectionner la notice � modifier.');

        // Si un num�ro de r�f�rence a �t� indiqu�, on charge cette notice         
        // V�rifie qu'elle existe
        if (! $this->select($this->equation))
            return $this->showNoAnswer("La requ�te $this->equation n'a donn� aucune r�ponse.");
        
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
     * Saisie par duplication d'une notice existante
     * 
     * Identique � l'action load, si ce n'est que la configuration contient une
     * section "fields" qui indique quels champs doivent �tre copi�s ou non.
     */
    public function actionDuplicate()
    {
        // Ouvre la base de donn�es
        $this->openDatabase();

        // D�termine la recherche � ex�cuter        
        $this->equation=$this->getEquation();

        // Erreur, si aucun param�tre de recherche n'a �t� pass�
        // Erreur, si des param�tres ont �t� pass�s, mais tous sont vides et l'�quation obtenue est vide
        if (is_null($this->equation))
            return $this->showError('Vous n\'avez indiqu� aucun crit�re permettant de s�lectionner la notice � modifier.');

        // Si un num�ro de r�f�rence a �t� indiqu�, on charge cette notice         
        // V�rifie qu'elle existe
        if (! $this->select($this->equation))
            return $this->showNoAnswer("La requ�te $this->equation n'a donn� aucune r�ponse.");
        
        // Si s�lection contient plusieurs enreg, erreur
        if ($this->selection->count() > 1)
            return $this->showError('Vous ne pouvez pas �diter plusieurs enregistrements � la fois.');     

        // D�termine le template � utiliser
        if (! $template=$this->getTemplate())
            throw new Exception('Le template � utiliser n\'a pas �t� indiqu�');
        
        // D�termine le callback � utiliser
        $callback=$this->getCallback();
        
        // R�cup�re dans la config la section <fields> qui indique les champs � dupliquer
        $fields=Config::get('fields');
        
        // Par d�faut doit-on tout copier ou pas ?
        $default=(bool) Utils::get($fields['default'], true);
        unset($fields['default']);
        
        // Convertit les noms des champs en minu pour qu'on soit insensible � la casse
        $fields=array_combine(array_map('strtolower', array_keys($fields)), array_values($fields));

        // Recopie les champs
        $values=array();
        foreach($this->selection->record as $name=>$value)
        {
            $values[$name]= ((bool)Utils::get($fields[strtolower($name)], $default)) ? $value : null;
        }

        // Affiche le formulaire de saisie/modification
        Template::run
        (
            $template,
            array
            (
                'REF'=>0,
            ),
            array($this, $callback),
            $values  
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
        
        // TODO: dans la config, on devrait avoir, par d�faut, access: admin (ie base modifiable uniquement par les admin)
        
        // D�termine le callback � utiliser
        $callback=$this->getCallback();
        
        // Par d�faut, le callback du save est � 'none'. Le module descendant DOIT d�finit un callback pour pouvoir modifier la base 
        if ($callback === 'none')
            throw new Exception("Cette base n'est pas modifiable (aucun callback d�finit pour le save"); 
                
        // Si REF n'a pas �t� transmis ou est invalide, erreur
        $ref=$this->request->required('REF')->unique()->int()->min(0)->ok();
        
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
                
            $fieldValue=$this->request->get($fieldName);

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
            Runtime::redirect('show?REF='.$ref);
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
     * Supprime la ou les notice(s) indiqu�e(s) par l'�quation puis affiche le template
     * indiqu� dans la cl� 'template' de la configuration.
     * 
     * Avant de supprimer les notices, redirige vers la pseudo action confirmDelete
     * pour demander une confirmation.
     *  
     * @param int $confirm
     * @return unknown
     */
    public function actionDelete($confirm=0)
    {
        // Ouvre la base de donn�es
        $this->openDatabase(false);

        // R�cup�re l'�quation de recherche qui donne les enregistrements � supprimer
        $this->equation=$this->getEquation();

        // Param�tre equation manquant
        if (is_null($this->equation))
            return $this->showError('Le ou les num�ros des notices � supprimer n\'ont pas �t� indiqu�s.');

        // Aucune r�ponse
        if (! $this->select($this->equation, -1) )
            return $this->showError("Aucune r�ponse. Equation : $this->equation");

        // Demande confirmation si ce n'est pas d�j� fait
        $confirm=$this->request->int('confirm')->ok();
        $confirm=time()-$confirm;
        if($confirm<0 || $confirm>Config::get('timetoconfirm',30))  // laisse timetoconfirm secondes � l'utilisateur pour confirmer
            Runtime::redirect($this->request->setAction('confirmDelete'));
            
        // R�cup�re le nombre exact de notices � supprimer
        $count=$this->selection->count();
        
        // Cr�e une t�che dans le TaskManager si on a plus de maxrecord notices et qu'on n'est pas d�j� dans une t�che
        if ( $count > Config::get('maxrecord',1))
        {
            // afficher formulaire, choisir date et heure, revenir ici
            $id=Task::create()
                ->setRequest($this->request->setAction('BatchDelete'))
                ->setTime(0)
                ->setLabel("Suppression de $count notices dans la base ".Config::get('database'))
                ->setStatus(Task::Waiting)
                ->save()
                ->getId();
                
            Runtime::redirect('/TaskManager/TaskStatus?id='.$id);
            return;
        }

        // Supprime toutes les notices de la s�lection
        foreach($this->selection as $record)
            $this->selection->deleteRecord();
        
        // Ex�cute le template
        Template::run
        (
            $this->getTemplate(),  
            array($this, $this->getCallback())
        );

        // Ferme la base maintenant
        unset($this->selection); // optionnel, voir si on garde
    }
    
    public function actionBatchDelete()
    {
        // D�termine si on est une t�che du TaskManager ou si on est "en ligne"
        if (!User::hasAccess('cli')) die(); // todo: mettre access:cli en config
        
        // Ouvre la base de donn�es
        $this->openDatabase(false);

        // R�cup�re l'�quation de recherche qui donne les enregistrements � supprimer
        $this->equation=$this->getEquation();

        // Param�tre equation manquant
        if (is_null($this->equation))
            return $this->showError('Le ou les num�ros des notices � supprimer n\'ont pas �t� indiqu�s.');

        // Aucune r�ponse
        if (! $this->select($this->equation, -1) )
            return $this->showError("Aucune r�ponse. Equation : $this->equation");

        // R�cup�re le nombre exact de notices � supprimer
        $count=$this->selection->count();
        
        // idea: avoir un template unique contenant un switch et qu'on appelle avec phase="before", "after", etc. � creuser.

        // Ex�cute le template
        Template::run
        (
            $this->getTemplate(),  
            array($this, $this->getCallback())
        );
        
        // Supprime toutes les notices de la s�lection
        $nb=0;
        foreach($this->selection as $record)
        {
            $this->selection->deleteRecord();
            TaskManager::progress(++$nb, $count);
        }
        echo '<p>Fermeture de la base...</p>';
        TaskManager::progress(50,50);
        
        // Ferme la base maintenant
        unset($this->selection);
        
        // Done.
        echo '<p>Suppression des ', $count, ' enregistrements termin�e</p>';
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
     public function actionReplace($_equation, $search='', $replace='', array $fields=array(), $word=false, $ignoreCase=true, $regexp=false)
     {       
        // V�rifie les param�tres
        $this->equation=$this->request->required('_equation')->ok();
        $search=$this->request->unique('search')->ok();
        $replace=$this->request->unique('replace')->ok();
        $fields=$this->request->asArray('fields')->required()->ok();
        $word=$this->request->bool('word')->ok();
        $ignoreCase=$this->request->bool('ignoreCase')->ok();
        $regexp=$this->request->bool('regexp')->ok();
        
        // V�rifie qu'on a des notices � modifier 
        $this->openDatabase(false);
        if (! $this->select($this->equation, -1) )
            return $this->showError("Aucune r�ponse. Equation : $this->equation");
        
        $count=$this->selection->count();

        // Si on est "en ligne" (ie pas en ligne de commande), cr�e une t�che dans le TaskManager
        if (!User::hasAccess('cli'))
        {
            $options=array();
            if ($word) $options[]='mot entier';
            if ($ignoreCase) $options[]='ignorer la casse';
            if ($regexp) $options[]='expression r�guli�re';
            if (count($options))
                $options=' (' . implode(', ', $options) . ')';
            else
                $options='';
                
            $label=sprintf
            (
                'Remplacer %s par %s dans %d notices de la base %s%s',
                var_export($search,true),
                var_export($replace,true),
                $count,
                Config::get('database'),
                $options
            );
            
            $id=Task::create()
                ->setRequest($this->request)
                ->setTime(0)
                ->setLabel($label)
                ->setStatus(Task::Waiting)
                ->save()
                ->getId();
                
            Runtime::redirect('/TaskManager/TaskStatus?id='.$id);
            return;
        }
        
        // Sinon, au boulot !

        echo '<h1>Modification en s�rie</h1>', "\n";
        echo '<ul>', "\n";
        echo '<li>Equation de recherche : <code>', $this->equation, '</code></li>', "\n";
        echo '<li>Nombre de notices � modifier : <code>', $count, '</code></li>', "\n";
        echo '<li>Rechercher : <code>', var_export($search,true), '</code></li>', "\n";
        echo '<li>Remplacer par : <code>', var_export($replace,true), '</code></li>', "\n";
        echo '<li>Dans le(s) champ(s) : <code>', implode(', ', $fields), '</code></li>', "\n";
        echo '<li>Mots entiers uniquement : <code>', ($word ? 'oui' : 'non'), '</code></li>', "\n";
        echo '<li>Ignorer la casse des caract�res : <code>', ($ignoreCase ? 'oui' : 'non'), '</code></li>', "\n";
        echo '<li>Expression r�guli�re : <code>', ($regexp ? 'oui' : 'non'), '</code></li>', "\n";
        
        
        $count = 0;         // nombre de remplacements effectu�s par enregistrement
        $totalCount = 0;    // nombre total de remplacements effectu�s sur le sous-ensemble de notices
        
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
            if ($regexp || $word)
            {
                // expr reg ou alors cha�ne avec 'Mot entier' s�lectionn�
                // dans ces deux-cas, on appellera pregReplace pour simplier

                // �chappe le '~' �ventuellement entr� par l'utilisateur car on l'utilise comme d�limiteur
                $search = str_replace('~', '\~', $search);
                
                if ($word)
                    $search = $search = '~\b' . $search . '\b~';
                else
                    $search = '~' . $search . '~';  // d�limiteurs de l'expression r�guli�re
                    
                if ($ignoreCase)
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
//                    $this->selection->strReplace($fields, $search, $replace, $ignoreCase, $count, $callback);     // cf. Database.php
                    $this->selection->strReplace($fields, $search, $replace, $ignoreCase, $count);
                    $this->selection->saveRecord();
                    $totalCount += $count;
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
            array($this, $callback),
            array('count'=>$totalCount)
        ); 
     }
     
    // lookup dans une table des entr�es (xapian only)
    function actionLookup($table, $value='', $max=10)
    {
        header('Content-type: text/html; charset=iso-8859-1');

        $max=$this->request->defaults('max', 25)->int()->min(0)->ok();
        
        // Ouvre la base
        $this->openDatabase();
        
        // Lance la recherche
        $terms=$this->selection->lookup($table, $value, $max, 0, true);

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
            array('search'=>$value, 'table'=>$table, 'terms'=>$terms)
        );  
    }
    
     
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
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

    protected function select($equation, $max=null, $start=null, $sort=null)
    {
        /*
         * Valeurs par d�faut des options de recherche
         * 
         * Pour chaque param�tre, on prend dans l'ordre :
         * - le param�tre transmis si non null
         * - sinon, ce qu'il y a dans request, si non null, en v�rifiant le type
         * - la valeur indiqu�e dans la config sinon 
         */
        $options=array
        (
            '_start'=> isset($start) ? $start :
                $this->request->defaults('_start', Config::get('start',1))
                    ->unique()
                    ->int()
                    ->min(1)
                    ->ok(),
                    
            '_max'=> isset($max) ? $max :
                $this->request->defaults('_max', Config::get('max', 10))
                    ->unique()
                    ->int()
                    ->min(-1)
                    ->ok(),
                    
            '_sort'=> isset($sort) ? $sort : 
                $this->request->defaults('_sort', Config::get('sort','-'))
                    ->ok(),
                    
            '_filter'=>$this->getFilter(),
                    
            '_defaultop'=>Config::get('defaultop'),
        );
        return $this->selection->search($equation, $options);
    }
   
    protected function selectOLD($equation, $options=null)
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
            array('message'=>$message),
            $this->selection->record
            
            // On passe en param�tre la s�lection en cours pour permettre
            // d'utiliser le m�me template dans un search (par exemple) et 
            // dans l'erreur 'aucune r�ponse' (on a le cas pour ImportModule)
            
        );
    }
    
    /**
     * Construit l'�quation qui sera utilis�e pour lancer la recherche dans 
     * la base.
     * 
     * getEquation() construit une �quation de recherche qui sera ensuite 
     * combin�e avec les filtres retourn�s par {@link getFilter()} pour 
     * lancer la recherche.
     * 
     * Par d�faut, getEquation() combine en 'ET' les �l�ments suivants :
     * 
     * - la ou le(s) �quation(s) de recherche qui figure dans l'argument 
     *   '_equation' de la {@link $request requ�te en cours}
     * 
     * - tous les param�tres de la requ�te dont le nom est un index ou un 
     *   alias de la base.
     * 
     * Si aucun des �l�ments ci-dessus ne retourne une �quation de recherche,
     * getEquation() utilise la ou les �quation(s) de recherche indiqu�e(s) 
     * dans la cl� 'DefaultEquation' de la configuration (des �quations par
     * d�faut diff�rentes peuvent �tre indiqu�es selon les droits de 
     * l'utilisateur, voir {@link configUserGet()}).
     * 
     * Si aucune �quation par d�faut n'a �t� indiqu�e, la fonction retourne
     * null.
     *
     * Les modules qui h�rite de DatabaseModule peuvent surcharger cett�  
     * m�thode pour changer le comportement par d�faut.
     * 
     * @return null|string l'�quation de recherche obtenue ou null si aucune
     * �quation ne peut �tre construite.
     */
    protected function getEquation()
    {
        $equation='';     

        // Combine en ET tous les '_equation=' transmis en param�tre
        if (isset($this->request->_equation))
        {
            foreach((array)$this->request->_equation as $eq)
            {
                if ('' !== $eq=trim($eq)) 
                {
                    if ($equation) $equation .= ' AND ';
                    $equation.= $eq;    
                }
            }
        }
        if ($equation !=='') $equation='(' . $equation . ')';
        
        // Combine en OU tous les param�tres qui sont des noms d'index/alias et combine en ET avec l'�quation pr�c�dente
        $structure=$this->selection->getStructure();
        foreach($this->request->getParameters() as $name=>$value)
        {
            if (is_null($value) || $value==='') continue;
            if (isset($structure->indices[strtolower($name)]) || isset($structure->aliases[strtolower($name)]))
            {
                $h='';
                $addBrackets=false;
                foreach((array)$value as $value)
                {
                    if ('' !== trim($value))
                    {
                        if ($h) 
                        {
                           $h.=' OR ';
                           $addBrackets=true;
                        }
                        $h.=$value;
                    }
                }
                if ($h)
                {
                    if ($equation) $equation .= ' AND ';
                    if (true or $addBrackets) // todo: � revoir, g�n�re des parenth�ses inutiles
                        $equation.= $name.':('.$h.')';
                    else
                        $equation.= $name.':'.$h;
                }
            }
        }
        
        // Retourne l'�quation obtenue si on en a une
        if ($equation !== '') return $equation;
        
        // L'�quation par d�faut indiqu�e dans la config sinon
        return $this->configUserGet('equation', null);
    }
    
    /**
     * D�termine le ou les filtres � appliquer � la recherche qui sera ex�cut�e
     * 
     * Les filtres seront combin�s � l'�quation de recherche retourn�e par
     * {@link getEquation()} de telle sorte que seuls les enregistrements qui 
     * passent les filtres soient pris en compte.
     * 
     * La mani�re dont les filtres sont combin�s � l'�quation d�pend du driver
     * de base de donn�es utilis� (BisDatabase combine en 'SAUF', XapianDatabase
     * utilise l'op�rateur xapian 'FILTER'). 
     * 
     * Par d�faut, getFilter() prend en compte :
     * 
     * - les filtres �ventuels indiqu�s dans la cl� <code>filter</code> de la
     *   configuration en cours (des filtres diff�rents peuvent �tre indiqu�s
     *   selon les droits de l'utilisateur, voir {@link configUserGet()}).
     * 
     * - les filtres �ventuels pass�s dans la cl� <code>_filter</code> de la
     * {@link request requ�te en cours}
     * 
     * Les modules descendants peuvent surcharger cette m�thode pour modifier
     * ce comportement par d�faut.
     */
    protected function getFilter()
    {
        // Charge les filtres indiqu�s dans la cl� 'filter' de la configuration
        $filters1=(array) $this->configUserGet('filter');
                
        // Charge les filtres indiqu�s dans les param�tres '_filter' de la requ�te
        $filters2=(array) $this->request->_filter;
        
        // Fusionne les deux
        return array_merge($filters1, $filters2);
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
     * G�n�re une barre de navigation affichant le nombre de r�ponses obtenues
     * et les liens suivant/pr�c�dent
     *
     * @param string $prevLabel libell� � utiliser pour le lien "Pr�c�dent"
     * @param string $nextLabel libell� � utiliser pour le lien "Suivant"
     * @return string
     */
    public function getSimpleNav($prevLabel = '&lt;&lt; Pr�c�dent', $nextLabel = 'Suivant &gt;&gt;')
    {
        // Regarde ce qu'a donn� la requ�te en cours
        $start=$this->selection->searchInfo('start');
        $max= $this->selection->searchInfo('max');
        $count=$this->selection->count();
        
        // Clone de la requ�te qui sera utilis� pour g�n�rer les liens
        $request=$this->request->copy();
        
        // D�termine le libell� � afficher
        if ($start==min($start+$max-1,$count))
            $h='R�sultat ' . $start . ' sur '.$this->selection->count('environ %d'). ' ';
        else
            $h='R�sultats ' . $start.' � '.min($start+$max-1,$count) . ' sur '.$this->selection->count('environ %d'). ' ';
        
        // G�n�re le lien "pr�c�dent"
        if ($start > 1)
        {
            $newStart=max(1,$start-$max);
            
            $prevUrl=Routing::linkFor($request->set('_start', $newStart));
            $h.='<a href="'.$prevUrl.'">'.$prevLabel.'</a>';
        }
        
        // G�n�re le lien "suivant"
        if ( ($newStart=$start+$max) <= $count)
        {
            $nextUrl=Routing::linkFor($request->set('_start', $newStart));
            if ($start > 1 && $h) $h.='&nbsp;|&nbsp;';
            $h.='<a href="'.$nextUrl.'">'.$nextLabel.'</a>';
        }

        // Retourne le r�sultat
        return '<span class="navbar">'.$h.'</span>';
    }
    
    /**
     * Enter description here...
     *
     * Voir {@link http://www.smashingmagazine.com/2007/11/16/pagination-gallery-examples-and-good-practices/}
     * 
     * @param unknown_type $links
     * @param unknown_type $previousLabel
     * @param unknown_type $nextLabel
     * @param unknown_type $firstLabel
     * @param unknown_type $lastLabel
     */
    public function getNavigation($links = 9, $previousLabel = '�', $nextLabel = '�', $firstLabel = '�', $lastLabel = '�')
    {
        /*
                                $max r�ponses par page
                            $links liens g�n�r�s au maximum
                      +----------------------------------------+  
            1   2   3 | 4   5   6   7  (8)  9   10  11  12  13 | 14  15 16            
                      +-^---------------^-------------------^--+        ^
                        |               |                   |           |
                        $first          $current            $last       $maxlast
        */
        
        // Regarde ce qu'a donn� la requ�te en cours
        $start=$this->selection->searchInfo('start');
        $max= $this->selection->searchInfo('max');
        $count=$this->selection->count();
        
        // Num�ro de la page en cours
        $current = intval(($start - 1) / $max) + 1;
        
        // Num�ro du plus grand lien qu'il est possible de g�n�rer
        $maxlast = intval(($count - 1) / $max) + 1;
        
        // "demi-fen�tre"
        $half=intval($links / 2);
        
        // Num�ro du premier lien � g�n�rer
        $first=max(1, $current-$half);
        
        // Num�ro du dernier lien � g�n�rer
        $last=$first+$links-1;
        
        // Ajustement des limites
        if ($last > $maxlast)
        {
            $last=$maxlast;
            $first=max(1,$last-$links+1);
        }

        // Requ�te utilis�e pour g�n�rer les liens
        $request=Routing::linkFor($this->request->copy()->clearNull()->clear('_start'));
        $request.=(strpos($request,'?')===false ? '?' : '&') . '_start=';
        $request=htmlspecialchars($request);
        
//        echo '<div class="pager">';
        
        echo '<span class="label">';
        if ($start==min($start+$max-1,$count))
            echo 'R�ponse ', $start, ' sur ', $this->selection->count('environ %d'), ' ';
        else
            echo 'R�ponses ', $start, ' � ', min($start+$max-1, $count), ' sur ', $this->selection->count('environ %d'), ' ';
        echo '</span>';
        
        // Lien vers la premi�re page
        if ($firstLabel)
        {
            if ($current > 1)
                echo '<a class="first" href="',$request, 1,'" title="premi�re page">', $firstLabel, '</a>';
            else
                echo '<span class="first">', $firstLabel, '</span>';
        }    
        
        // Lien vers la page pr�c�dente
        if ($previousLabel)
        {
            if ($current > 1)
                echo '<a class="previous" href="', $request, 1+($current-2)*$max,'" title="page pr�c�dente">', $previousLabel, '</a>';
            else
                echo '<span class="previous">', $previousLabel, '</span>';
            
        }    
        
        // Lien vers les pages de la fen�tre
        for($i=$first; $i <= $last; $i++)
        {
            if ($i===$current)
            {
                echo '<span class="current">', $i, '</span>';
            }
            else
            {
                $title='R�ponses '.(1+($i-1)*$max) . ' � ' . min($count, $i*$max);
                echo '<a href="', $request, 1+($i-1)*$max,'" title="', $title, '">', $i, '</a>';
            }
        }

        // Lien vers la page suivante
        if ($nextLabel)
        {
            if ($current < $maxlast)
                echo '<a class="next" href="', $request, 1+($current)*$max,'" title="page suivante">', $nextLabel, '</a>';
            else
                echo '<span class="next">', $nextLabel, '</span>';
            
        }    
        
        // Lien vers la derni�re page
        if ($lastLabel)
        {
            if ($current < $maxlast)
                echo '<a class="last" href="', $request, 1+($maxlast-1)*$max,'" title="derni�re page">', $lastLabel, '</a>';
            else
                echo '<span class="last">', $lastLabel, '</span>';
        }    
        
//        echo '</div>';
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
    public function configUserGet($key, $default=null)
    {
        $value=Config::get($key);
        if (is_null($value))
            return $default;

        if (is_array($value))
        {
            foreach($value as $right=>$value)
            {
                if (User::hasAccess($right))
                    return $value;
            }
            return $default;
        }
        
        return $value;
    }
     
    protected function getTemplate($key='template')
    {
        debug && Debug::log('%s : %s', $key, $this->ConfigUserGet($key));
        if (! $template=$this->ConfigUserGet($key)) 
            return null;
            
        if (file_exists($h=$this->path . $template)) // fixme : template relatif � BisDatabase, pas au module h�rit� (si seulement config). Utiliser le searchPath du module en cours
        {
            return $h;
        }    
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
    
    /**
     * Charge la liste des formats d'export disponibles dans la config en cours
     * 
     * Seuls les formats auxquels l'utilisateur a acc�s sont charg�s (param�tre access de chaque format).
     * Les formats charg�s sont ajout�s dans la configuration en cours dans la cl� 'formats'.
     * Config::get('formats') retourne la liste de tous les formats
     * Config::get('formats.csv') retourne les param�tres d'un format particulier.
     * 
     * @return int le nombre de formats charg�s
     */
    private function loadExportFormats()
    {
        // Balaye la liste des formats d'export disponibles 
        foreach(Config::get('formats') as $name=>$format)
        {
            // Ne garde que les formats auquel l'utilisateur a acc�s
            if (isset($format['access']) && ! User::hasAccess($format['access']))
        	{
                Config::clear("formats.$name");
        	}
        	
        	// Initialise label et max
        	else
            {
                if (!isset($format['label']))
                    Config::set("formats.$name.label", $name);
                Config::set("formats.$name.max", $this->configUserGet("formats.$name.max",300));
            }
        }

        // Retourne le nombre de formats charg�s
        return count(Config::get('formats'));
    }
    
    /** 
     * Export � partir d'une �quation de recherche pass�e en param�tre
     * 
     * Les formats d'export disponibles sont list�s dans un fichier dont le
     * nom est indiqu� dans la cl� 'list' de l'action 'export'.
     * 
     * Premi�re �tape : affichage de la liste des formats disponibles et
     * s�lection par l'utilisateur du type d'export � faire (envoi par email ou
     * affichage d�chargement de fichier)
     * 
     * Seconde �tape : ex�cution du template correspondant au format d'export 
     * choisi en indiquant le type mime correct.
     */
    public function actionExport($calledFromPreExecute=false)
    {
        $showForm=false;
        
        // Charge la liste des formats d'export disponibles
        if ($calledFromPreExecute)
        {
            if (!$this->loadExportFormats())
                throw new Exception("Aucun format d'export n'est disponible");
        }

        // Choix du format d'export
        if(count(Config::get('formats'))===1)                       // Un seul format dispo : on prend celui-l�
        {
            foreach(Config::get('formats') as $format=>$fmt);       // point-virgule � la fin, voulu !
                // boucle vide, ex�cut�e une seule fois, juste pour initialiser $format et $fmt
        }
        else                                                        // L'utilisateur a le choix du format
        {
            $format=Utils::get($_REQUEST['_format']);               // Regarde dans la query string
            if ($format)
            {
                $fmt=Config::get("formats.$format");                // et v�rifie que le format existe et est autoris�
                if(is_null($fmt))
                    throw new Exception("Format d'export incorrect");
            }
            else
            {
                $showForm=true; // on ne sait pas quel format utiliser
            }
        }
        if (isset($fmt['description'])) 
            $fmt['description']=trim($fmt['description']);
        else
            $fmt['description']='';
            
        // Option "envoi par mail"
        if ($mail=(bool)Config::get('allowmail'))                   // seulement si l'option mail est autoris�e dans la config
        {
            if(! $mail=(bool)Config::get('forcemail'))              // option forcemail � true, on envoie toujours un mail
            {
                if (isset($_REQUEST['_mail']))                      // sinon, on regarde si on a l'option dans la query string
                {
                    $mail=(bool)Utils::get($_REQUEST['_mail']);         
                }
                else
                {
                    $showForm=true;
                }
            }        
            $to=$subject=$message=null;
            if($mail)                                               // s'il faut envoyer un mail, v�rifie qu'on a un destinataire
            {
                if (!$to=Utils::get($_REQUEST['_to']))              // pas de destinataire : demande � l'utilisateur
                {
                    $showForm=true;
                }
                else                                                // r�cup�re l'objet et le message, valeurs par d�faut si absents
                {
                    $subject=Utils::get($_REQUEST['_subject'], 'Export de notices');
                    $message=Utils::get($_REQUEST['_message'], '');
                }
            }
        }
        
        // Equation(s) de recherche � ex�cuter
        if (!$equations=Config::get('equation') )                    // �quation(s) fix�e(s) en dur dans la config, on prend
            if (!$equations=Utils::get($_REQUEST['_equation']))      // sinon, doi(ven)t �tre transmis(es) en query string          
                throw new Exception('Aucune �quation de recherche indiqu�e');
        $equations=(array)$equations;

        // Option "archive au format ZIP"
        if ($zip=(bool)Config::get('allowzip') )                    // seulement si l'option zip est autoris�e dans la config
        {
            
            if(! $zip=(bool)Config::get('forcezip'))                // option forcezip � true, on cr�e toujours un zip
            {
                if (isset($_REQUEST['_zip']))
                {
                    $zip=(bool)Utils::get($_REQUEST['_zip']);       // sinon, on regarde si on a l'option dans la query string
                }
                else
                {
                    $showForm=true;
                }
            }
            if ($zip && ! class_exists('ZipArchive'))                   // zip demand� mais l'extension php_zip n'est pas charg�e dans php.ini
                throw new Exception("La cr�ation de fichiers ZIP n'est pas possible sur ce serveur");
        }

        // TODO: a revoir, que faire si on a plusieurs fichiers et que php_zip.dll n'est pas charg�e ?
        // Si plusieurs �quations et ni zip ni mail, probl�me (on ne peut pas envoyer plusieurs fichiers au navigateur !)
        if (count($equations)>1 && !$mail && !$showForm)
        {
            $zip=true;
            if (! class_exists('ZipArchive'))                   // zip demand� mais l'extension php_zip n'est pas charg�e dans php.ini
                throw new Exception("La cr�ation de fichiers ZIP n'est pas possible sur ce serveur");
        }
    
        if ($showForm)
        {
            if($calledFromPreExecute) return false;
            
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
                array
                (
                    'equations'=>$equations,
                )
            );
            return true;
        }
        
        // Tous les param�tres ont �t� v�rifi�s, on est pr�t � faire l'export
        
        // TODO : basculer vers le TaskManager si n�cessaire
        /* 
            si mail -> TaskManager
            si plusieurs fichiers -> TaskManager

            la difficult� :
                - on ex�cute addTask()
                - message "Le fichier est en cours de g�n�ration"
                - (on attend)
                - (la t�che s'ex�cute)
                - message "cliquez ici pour d�charger le fichier g�n�r�"
                
                ou
                - redirection vers TaskManager/TaskStatus?id=xxx
                (l'utilisateur a acc�s au TaskManager ??? !!!)
            difficult� suppl�mentaire :
                - �a fait quoi quand on clique sur le lien ?            
        */
        // Extrait le nom de fichier indiqu� dans le format dans la cl� content-disposition
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

        // G�n�re un nom de fichier unique pour chaque fichier en utilisant l'�ventuel filename pass� en query string
        $filename=Utils::get($_REQUEST['filename'],'');
        $filenames=array();
        foreach($equations as $i=>$equation)
        {
            $h=is_array($filename) ? Utils::get($filename[$i],'') : $filename;
            //if ($h) $h='-' . $h;
            $j=1;
            $result=sprintf($basename, $h);
            while(in_array($result, $filenames))
                $result=sprintf($basename, $h.''.(++$j));

            $filenames[$i]=$result;
        }

        // D�termine le nombre maximum de notices que l'utilisateur a le droit d'exporter
        $max=$this->ConfigUserGet("formats.$format.max",10);

        // Ouvre la base de donn�es
        $this->openDatabase();

        // Ex�cute toutes les recherches dans l'ordre
        $files=array();
        $counts=array();
        $filesizes=array();
        foreach($equations as $i=>$equation)
        {        
            // Lance la recherche, si aucune r�ponse, erreur
            if (! $this->select($equation, $max, 0))
            {
            	echo "Aucune r�ponse pour l'�quation $equation<br />";
                continue;
            }
            
            //echo 'G�n�ration fichier ',$filenames[$i],' pour �quation ', $equation, '<br />';
            $counts[$i]=$max===-1 ? $this->selection->count() : (min($max,$this->selection->count()));
            
            // Si l'utilisateur a demand� un envoi par mail, d�marre la capture
            if ($mail or $zip)
            {
            	Utils::startCapture();
            }
            else
            {
                // Sinon, d�finit les ent�tes http du fichier g�n�r�
                if (isset($fmt['content-type']))
                    header('content-type: ' . $fmt['content-type']);
        
                if (isset($fmt['content-disposition']))
                    header('content-disposition: ' . sprintf($fmt['content-disposition'],Utils::setExtension($filenames[$i])));
            }
        
            // Si un g�n�rateur sp�cifique a �t� d�finit pour ce format, on l'ex�cute
            if (isset($fmt['generator']))
            {
                $generator=trim($fmt['generator']);
                if (strtolower($generator)!=='')
                    $this->$generator($fmt);
            }
    
            // Sinon, on utilise le g�n�rateur par d�faut
            else
            {
                // D�termine le template � utiliser
                if (! $template=$this->ConfigUserGet("formats.$format.template"))
                {
                    if ($mail or $zip) Utils::endCapture(); // sinon on ne "verra" pas l'erreur
                    throw new Exception("Le template � utiliser pour l'export en format $format n'a pas �t� indiqu�");
                }

                // D�termine le callback � utiliser
                $callback=$this->ConfigUserGet("formats.$format.callback"); 
        
                // Ex�cute le template
                try
                {
                    Template::run
                    (
                        $template,
                        array($this, $callback),
                        array('format'=>$format),
                        array('fmt'=>$fmt),
                        $this->selection->record
                    );
                }
                catch(Exception $e)
                {
                    if ($mail or $zip) Utils::endCapture(); // sinon on ne "verra" pas l'erreur
                    throw $e; // re g�n�re l'exception
                }
            }
        
            // Pour un export classique, on a fini
            if (!($mail or $zip)) continue;
        
            // Termine la capture du fichier d'export g�n�r� et stocke le nom du fichier temporaire
            $files[$i]=Utils::endCapture();
            $filesizes[$i]=filesize($files[$i]);
        }

        // Si l'option zip est active, cr�e le fichier zip
        if ($zip)
        {
        	$zipFile=new ZipArchive();
            $f=Utils::getTempFile(0, 'export.zip');
            $zipPath=Utils::getFileUri($f);
            fclose($f);
            if (!$zipFile->open($zipPath, ZipArchive::OVERWRITE))
            	throw new Exception('Impossible de cr�er le fichier zip');
//            if (!$zipFile->setArchiveComment('Fichier export� depuis le site ascodocpsy')) // non affich� par 7-zip            
//                throw new Exception('Impossible de cr�er le fichier zip - 1');
            foreach($files as $i=>$path)
            {
                if (!$zipFile->addFile($files[$i], Utils::convertString($filenames[$i],'CP1252 to CP437')))
                    throw new Exception('Impossible de cr�er le fichier zip - 2');
                if (!$zipFile->setCommentIndex($i, Utils::convertString($equations[$i],'CP1252 to CP437')))            
                    throw new Exception('Impossible de cr�er le fichier zip - 3');
                    
                // Historiquement, le format ZIP utilise CP437 
                // (source : annexe D de http://www.pkware.com/documents/casestudies/APPNOTE.TXT) 
                 
            }
            if (!$zipFile->close())            
                throw new Exception('Impossible de cr�er le fichier zip - 4');
                
            // Si l'option mail n'est pas demand�e, envoie le zip
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
		require_once Runtime::$fabRoot . 'lib/SwiftMailer/Swift.php';
		require_once Runtime::$fabRoot . 'lib/SwiftMailer/Swift/Connection/SMTP.php';

 		// Cr�e une nouvelle connexion Swift
        $swift = new Swift(new Swift_Connection_SMTP(ini_get('SMTP'))); // TODO: mettre dans la config de fab pour ne pas �tre oblig� de changer php.ini

        $log = Swift_LogContainer::getLog();
        $log->setLogLevel(4); // 4 = tout est logg�, 0 = pas de log
        
        // Force swift � utiliser un cache disque pour minimiser la m�moire utilis�e
        Swift_CacheFactory::setClassName("Swift_Cache_Disk");
        Swift_Cache_Disk::setSavePath(Utils::getTempDirectory());        
 		
 		// Cr�e le message
		$email = new Swift_Message($subject);
        
        // Cr�e le corps du message
        $template=$this->configUserGet('mailtemplate');
        if (is_null($template))
        {
            $body=$message;
            $mimeType='text/plain';
        }
        else
        {
            ob_start();
            Template::run
            (
                $template, 
                array
                (
                    'to'=>$to,
                    'subject'=>htmlentities($subject),  // Le message tap� par l'utilisateur dans le formulaire
                    'message'=>htmlentities($message),  // Le message tap� par l'utilisateur dans le formulaire
                    'filenames'=>$filenames,            // Les noms des fichiers joints
                    'equations'=>$equations,            // Les �quations de recherche
                    'format'=>$fmt['label'],            // Le nom du format d'export
                    'description'=>$fmt['description'], // Description du format d'export
                    'counts'=>$counts,                  // Le nombre de notices de chacun des fichiers
                    'filesizes'=>$filesizes,            // La taille non compress�e de chacun des fichiers
                    'zip'=>$zip                         // true si option zip
                    
                )
            );
            $body=ob_get_clean();
            $mimeType='text/html'; // fixme: on ne devrait pas fixer en dur le type mime. Cl� de config ?
        }

        $email->attach(new Swift_Message_Part($body, $mimeType));

		// Met les pi�ces attach�es
        $swiftFiles=array(); // Grrr... Swift ne ferme pas les fichiers avant l'appel � destruct. Garde un handle dessus pour pouvoir appeller nous m�me $file->close();
        if ($zip)
        {
    		$swiftFiles[0]=new Swift_File($zipPath);
            $email->attach(new Swift_Message_Attachment($swiftFiles[0], 'export.zip', 'application/zip'));
        }
        else
        {
            foreach($files as $i=>$path)
            {
                $swiftFiles[$i]=new Swift_File($path);
                $mimeType=strtok($fmt['content-type'],';');
                $email->attach(new Swift_Message_Attachment($swiftFiles[$i], $filenames[$i], $mimeType));
//                $piece->setDescription($equations[$i]);
            }
        }
        	
		// Envoie le mail
        $from=new Swift_Address(Config::get('admin.email'), Config::get('admin.name'));
        $error='';
        try
        {
    		$sent=$swift->send($email, $to, $from);
        }
        catch (Exception $e)
        {
            $sent=false;
            $error=$e->getMessage();
        }

        // HACK: ferme "� la main" les pi�ces jointes de swift, sinon le capture handler ne peut pas supprimer les fichiers temporaires
        foreach($swiftFiles as $file)
            $file->close();

        if ($sent)
        {
            $template=$this->getTemplate('mailsenttemplate');
            if ($template)
            {
                Template::run
                (
                    $template,
                    array
                    (
                        'to'=>$to,
                        'subject'=>htmlentities($subject),      // Le message tap� par l'utilisateur dans le formulaire
                        'message'=>htmlentities($message),      // Le message tap� par l'utilisateur dans le formulaire
                        'filenames'=>$filenames,                // Les noms des fichiers joints
                        'equations'=>$equations,                // Les �quations de recherche
                        'format'=>$fmt['label'],                // Le nom du format d'export
                        'description'=>$fmt['description'],     // Description du format d'export
                        'counts'=>$counts,                      // Le nombre de notices de chacun des fichiers
                        'filesizes'=>$filesizes,                // La taille non compress�e de chacun des fichiers
                        'zip'=>$zip                             // true si option zip
                        
                    )
                );
            }
            else
                echo '<p>Vos notices ont �t� envoy�es par courriel.';
        }
        else
        {
            echo '<h1>Erreur</h1>';
            echo "<fieldset>Impossible d'envoyer l'e-mail � l'adresse <strong><code>$to</code></strong></fieldset>";
            if ($error)
                echo "<p>Erreur retourn�e par le serveur : <strong><code>$error</code></strong></p>";
            echo "<fieldset><legend>Log de la transaction</legend> <pre>";
            echo $log->dump(true);
            echo "</pre></fieldset>";
        }
        return true;
    }

    // exemple de g�n�rateur en format xml simple pour les formats d'export
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
    
    // Recherche des doublons potentiels pour la notice en cours dans this->selection
    public function findDuplicates()
    {
        //$duplicates=clone $this->selection;
        $sav=$this->selection;
        $this->openDatabase();
        $duplicates=$this->selection;
        $this->selection=$sav;
        
        $equation=sprintf
        (
            'Tit:(%s) AND NOT REF:%d',
            $this->selection['Tit'],
            $this->selection['REF']
        );
//echo '<pre>';
$terms=$this->selection->getTerms();
$terms=$terms['index'];
$fields=array('Type', 'Aut', 'Tit', 'CongrTit', 'Date', 'DateText', 'Rev', 'Edit', 'IsbnIssn');

$terms=array_intersect_key($terms, array_flip($fields));
        
$equation='(';        
foreach($terms as $name=>$tokens)
{
    $tokens=implode(' ', array_keys($tokens));
    $equation.=$name . ':(' . $tokens . ') ';        
}
$equation .=') AND NOT REF:'.$this->selection['REF'];
//echo 'equation : ', $equation, '<br />';
//        print_r($terms);

        $duplicates->search
        (
            $equation
            ,
            array
            (
                '_sort'=>'%',
                '_max'=>10,
                '_minscore'=>75,
                '_rset'=>array($this->selection->searchInfo('docid'))
            )
        );
        return $duplicates;
    }
    
    public function actionTest()
    {
        // Ouvre la base de donn�es
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
Le r�seau BDSP est un groupement d�organismes dont la gestion est assur�e 
par l'Ecole nationale de la sant� publique.
Cr�� � l�initiative de la Direction g�n�rale de la sant�, il d�veloppe depuis 
1993 des services d'information en ligne destin�s aux professionnels des 
secteurs sanitaire et social. Notre site Web a pour objectif de donner acc�s, 
gr�ce � des outils de navigation raisonn�e, � une tr�s large palette de 
sources d�informations.
La coordination du r�seau, sous la direction d�un Comit� de pilotage national, 
est assur�e, � l'ENSP (Rennes � France), par l'Atelier d'�tudes et 
d�veloppement de la BDSP.        
        ";
        
        echo "<h1>Texte � indexer</h1><pre>$text</pre>";
        $text=utf8_encode($text);
        
        $indexer->index_text_without_positions($text,2);

        echo "<h1>Liste des tokens cr��s par TermGenerator::index_text()</h1>";
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
        foreach(array('sante','renne', 'r�zau', 'ravigation', 'rational', 'BDSP') as $word)
            echo '<li>', $word, ' -&gt; ', utf8_decode($db->get_spelling_suggestion($word)), '</li>';

        echo "<h1>stemming</h1>";
        echo "Languages disponibles : ", XapianStem::get_available_languages(), "<br />";
        $stemmer=new XapianStem('french');
        foreach(array('hopitaux','documentation', 'r�seau', 'armements', 'tests', 'BDSP') as $word)
            echo '<li>', $word, ' -&gt; ', $stemmer->apply($word), '</li>';
                    
        unset($db);        
        
        echo "<h1>S�rialisation entiers/doubles</h1>";
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