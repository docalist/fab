<?php
/**
 * @package     fab
 * @subpackage  modules
 * @author      Daniel M�nard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id$
 */

/**
 * Ce module permet de publier une base de donn�es sur le web.
 *
 * @package     fab
 * @subpackage  modules
 */
class DatabaseModule extends Module
{
    /**
     * Equation de recherche
     *
     * @var string
     */
    public $equation='';

    /**
     * La s�lection en cours
     *
     * @var Database
     */
    public $selection=null;

    /**
     * Permet � l'action {@link actionExport() Export} d'avoir un layout s'il faut
     * afficher le formulaire d'export, et de ne pas en avoir un si on dispose
     * de tous les param�tres requis pour lancer l'export.
     *
     * @return bool retourne true pour interrompre l'ex�cution de l'action, l'export
     * a �t� fait.
     */
    public function preExecute()
    {
        // HACK: permet � l'action export d'avoir un layout s'il faut afficher le formulaire d'export
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
     * Affiche le formulaire de recherche permettant d'interroger la base.
     *
     * L'action searchForm affiche le template retourn� par la m�thode
     * {@link getTemplate()} en utilisant le callback retourn� par la m�thode
     * {@link getCallback()}.
     */
    public function actionSearchForm()
    {
        // Ouvre la base de donn�es : permet au formulaire de recherche de consulter le sch�ma
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

    /**
     * Lance une recherche dans la base et affiche les r�ponses obtenues.
     *
     * L'action Search construit une �quation de recherche en appellant la
     * m�thode {@link getEquation()}. L'�quation obtenue est ensuite combin�e
     * avec les filtres �ventuels retourn�s par la m�thode {@link getFilter()}.
     *
     * Si aucun crit�re de recherche n'a �t� indiqu�, un message d'erreur est
     * affich�, en utilisant le template sp�cifi� dans la cl� <code><errortemplate></code>
     * du fichier de configuration.
     *
     * Si l'�quation de recherche ne fournit aucune r�ponse, un message est affich�
     * en utilisant le template d�fini dans la cl� <code><noanswertemplate></code>
     * du fichier de configuration.
     *
     * Dans le cas contraire, la recherche est lanc�e et les r�sultats sont
     * affich�s en utilisant le template retourn� par la m�thode
     * {@link getTemplate()} et le callback indiqu� par la m�thode
     * {@link getCallback()}.
     *
     * Si la cl� <code><history></code> de la configuration est � <code>true</code>,
     * la requ�te est ajout�e � l'historique des �quations de recherche.
     * L'historique peut contenir, au maximum, 10 �quations de recherche.
     */
    public function actionSearch()
    {
        Timer::enter();

        // Ouvre la base de donn�es
        $this->openDatabase();

        // D�termine la recherche � ex�cuter
        $this->equation=$this->getEquation();

        // Affiche le formulaire de recherche si on n'a aucun param�tre
        if (is_null($this->equation))
        {
            $this->showError('Vous n\'avez indiqu� aucun crit�re de recherche.');
            return;
        }

        // Aucune r�ponse
        if (! $this->select($this->equation))
        {
            $this->showNoAnswer("La requ�te $this->equation n'a donn� aucune r�ponse.");
            return;
        }

        // D�termine le template � utiliser
        if (! $template=$this->getTemplate())
            throw new Exception('Le template � utiliser n\'a pas �t� indiqu�');

        // D�termine le callback � utiliser
        $callback=$this->getCallback();

        // Ex�cute le template
        Timer::enter('Ex�cution du template d\'affichage des r�ponses');

        Template::run
        (
            $template,
            array($this, $callback),
            $this->selection->record
        );
        Timer::leave();

        // Ajoute la requ�te dans l'historique des �quations de recherche
        $history=Config::userGet('history', false);

        if ($history===true) $history=10;
        elseif ($history===false) $history=0;

        if (!is_int($history)) $history=0;
        if ($history>0 && ! Utils::isAjax())
            $this->updateSearchHistory($history);
        Timer::leave();
    }

    /**
     * M�thode utilitaire utilis�e par le template par d�faut de l'action search
     * (format.autolist.html) pour d�terminer quels sont les champs � afficher.
     *
     * Retourne un tableau contenant les champs index�s dont le nom ou le
     * libell� contiennent la chaine 'tit'.
     *
     * @return array
     */
    public function guessFieldsForAutoList()
    {
        $indices=$this->selection->getSchema()->indices;
        $fields=array();
        foreach($indices as $name=>$index)
        {
            $h=$name . ' ' . $index->label . ' ' . $index->description;
            if (false !== stripos($h, 'tit'))
            {
                foreach ($index->fields as $field)
                    $fields[$field->name]=true;
            }
        }
        return array_keys($fields);
    }

    /**
     * Charge l'historique des �quations de recherche.
     *
     * @return array tableau des �quations de recherche stock� dans la session.
     */
    private function & loadSearchHistory()
    {
        // Nom de la cl� dans la session qui stocke l'historique des recherches pour cette base
        $historyKey='search_history_'.Config::get('database');

        // R�cup�re l'historique actuel
        if (!isset($_SESSION[$historyKey])) $_SESSION[$historyKey]=array();
        return $_SESSION[$historyKey];
    }

    /**
     * Met � jour l'historique des �quations de recherche.
     *
     * @param int $maxHistory le nombre maximum d'�quations de recherche que
     * l'historique peut contenir.
     */
    private function updateSearchHistory($maxHistory=10)
    {
        Timer::enter('Mise � jour de l\'historique des recherches');

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
            'user' =>preg_replace('~[\n\r\f]+~', ' ', $equation), // normalise les retours � la ligne sinon le clearHistory ne fonctionne pas
            'xapian'=>$xapianEquation,
            'count'=>$this->selection->count('environ '),
            'time'=>time(),
            'number'=>$number
        );

//        echo 'Historique de recherche mis � jour : <br/>';
//        echo '<pre>', print_r($hist,true), '</pre>';
        Timer::leave();
    }

    /**
     * Efface l'historique des �quations de recherche.
     *
     * Apr�s avoir effac� l'historique, redirige l'utilisateur vers la page sur
     * laquelle il se trouvait.
     */
    public function actionClearSearchHistory($_equation=null)
    {
        // Charge les sessions si ce n'est pas le cas (pas mis en config, comme �a la session n'est charg�e que si on en a besoin)
        Runtime::startSession();

        // Nom de la cl� dans la session qui stocke l'historique des recherches pour cette base
        $historyKey='search_history_'.Config::get('database'); // no dry / loadSearchHistory

        // R�cup�re l'historique actuel
        if (isset($_SESSION[$historyKey]))
        {
            if (is_null($_equation))
            {
                unset($_SESSION[$historyKey]);
            }
            else
            {
                $_equation=array_flip((array)$_equation);
                foreach($_SESSION[$historyKey] as $key=>$history)
                {
                    if (isset($_equation[$history['user']]))
                        unset($_SESSION[$historyKey][$key]);
                }
            }
        }

        Runtime::redirect($_SERVER['HTTP_REFERER']);
    }

    /**
     * Retourne l'historique des �quations de recherche.
     *
     * @return array tableau des �quations de recherche stock� dans la session.
     */
    public function getSearchHistory()
    {
        return $this->loadSearchHistory();
    }

    /**
     * Affiche une ou plusieurs notices en "format long".
     *
     * Les notices � afficher sont donn�es par une equation de recherche.
     *
     * G�n�re une erreur si aucune �quation n'est accessible ou si elle ne
     * retourne aucune notice.
     *
     * Le template instanci� peut ensuite boucler sur <code>{$this->selection}</code>
     * pour afficher les r�sultats.
     */
    public function actionShow()
    {
        // Ouvre la base de donn�es
        $this->openDatabase();

        // D�termine la recherche � ex�cuter
        $this->equation=$this->getEquation();

        // Si aucun param�tre de recherche n'a �t� pass�, erreur
//        if (is_null($this->equation))
//        {
//            $this->showError('Vous n\'avez indiqu� aucun crit�re permettant de s�lectionner les notices � afficher.');
//            return;
//        }

        // Aucune r�ponse
        if (! $this->select($this->equation))
        {
            $this->showNoAnswer("La requ�te $this->equation n'a donn� aucune r�ponse.");
            return;
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
            $this->selection->record
        );
    }

    /**
     * Cr�e une nouvelle notice.
     *
     * Affiche le formulaire indiqu� dans la cl� <code><template></code> de la configuration.
     *
     * La source de donn�e 'REF' = 0 est pass�e au template pour indiquer �
     * l'action {@link actionSave() Save} qu'on cr�e une nouvelle notice.
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
     * Edite une notice.
     *
     * Affiche le formulaire indiqu� dans la cl� <code><template></code> de la
     * configuration, en appliquant le callback indiqu� dans la cl� <code><callback></code>.
     *
     * La notice correspondant � l'�quation donn�e est charg�e dans le formulaire.
     * L'�quation ne doit retourner qu'un seul enregistrement sinon une erreur est
     * affich�e en utilisant le template d�fini dans la cl� <code><errortemplate></code>.
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
        {
            $this->showError('Vous n\'avez indiqu� aucun crit�re permettant de s�lectionner la notice � modifier.');
            return;
        }

        // Si un num�ro de r�f�rence a �t� indiqu�, on charge cette notice
        // V�rifie qu'elle existe
        if (! $this->select($this->equation))
        {
            $this->showNoAnswer("La requ�te $this->equation n'a donn� aucune r�ponse.");
            return;
        }

        // Si s�lection contient plusieurs enreg, erreur
        if ($this->selection->count() > 1)
        {
            $this->showError('Vous ne pouvez pas �diter plusieurs enregistrements � la fois.');
            return;
        }

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
     * Saisie par duplication d'une notice existante.
     *
     * Identique � l'action {@link actionLoad() Load}, si ce n'est que la configuration
     * contient une section <code><fields></code> qui indique quels champs doivent
     * �tre copi�s ou non.
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
        {
            $this->showError('Vous n\'avez indiqu� aucun crit�re permettant de s�lectionner la notice � modifier.');
            return;
        }

        // Si un num�ro de r�f�rence a �t� indiqu�, on charge cette notice
        // V�rifie qu'elle existe
        if (! $this->select($this->equation))
        {
            $this->showNoAnswer("La requ�te $this->equation n'a donn� aucune r�ponse.");
            return;
        }

        // Si s�lection contient plusieurs enreg, erreur
        if ($this->selection->count() > 1)
        {
            $this->showError('Vous ne pouvez pas �diter plusieurs enregistrements � la fois.');
            return;
        }

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
     *
     * REF doit toujours �tre indiqu�. Si REF==0, une nouvelle notice sera
     * cr��e. Si REF>0, la notice correspondante sera �cras�e. Si REF est absent
     * ou invalide, une exception est lev�e.
     *
     * Lors de l'enregistrement de la notice, appelle le callback retourn� par la
     * m�thode {@link getCallback()} (cl� <code><callback></code> du fichier de
     * configuration). Ce callback permet d'indiquer � l'application s'il faut
     * interdire la modification des champs ou modifier leurs valeurs avant
     * l'enregistrement.
     *
     * Affiche ensuite le template retourn� par la m�thode {@link getTemplate()},
     * si la cl� <code><template></code> est d�finie dans le fichier de configuration,
     * ou redirige l'utilisateur vers l'action {@link actionShow() Show} sinon.
     *
     * @param int $REF num�ro de r�f�rence de la notice.
     */
    public function actionSave($REF)
    {
        // CODE DE DEBUGGAGE : save ne sauvegarde pas la notice si Runtime::redirect ne se termine
        // pas par exit(0) (voir plus bas)

        // TODO: dans la config, on devrait avoir, par d�faut, access: admin (ie base modifiable uniquement par les admin)

        // D�termine le callback � utiliser
        $callback=$this->getCallback();

        // Par d�faut, le callback du save est � 'none'. Le module descendant DOIT d�finir un callback pour pouvoir modifier la base
        if ($callback === 'none')
            throw new Exception("Cette base n'est pas modifiable (aucun callback d�finit pour le save");

        // Si REF n'a pas �t� transmis ou est invalide, erreur
        $REF=$this->request->required('REF')->unique()->int()->min(0)->ok();

        // Ouvre la base
        $this->openDatabase(false);

        // Si un num�ro de r�f�rence a �t� indiqu�, on charge cette notice
        if ($REF>0)
        {
            // Ouvre la s�lection
            debug && Debug::log('Chargement de la notice num�ro %s', $REF);

            if (! $this->select("REF=$REF"))
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
        $this->selection->saveRecord();   // TODO: gestion d'erreurs

        // R�cup�re le num�ro de la notice cr��e
        $REF=$this->selection['REF'];
        debug && Debug::log('Sauvegarde de la notice %s', $REF);

        // Redirige vers le template s'il y en a un, vers l'action Show sinon
        if (! $template=$this->getTemplate())
        {
            // Redirige l'utilisateur vers l'action show
            debug && Debug::log('Redirection pour afficher la notice enregistr�e %s', $REF);
            Runtime::redirect('Show?REF='.$REF);
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
     * Supprime une notice.
     *
     * Si l'�quation de recherche donne une seule notice, supprime la notice puis
     * affiche le template indiqu� dans la cl� <code><template></code> de la configuration.
     *
     * Si aucun crit�re de recherche n'a �t� indiqu� ou si l'�quation de recherche
     * ne fournit aucune r�ponse, un message d'erreur est affich�, en utilisant
     * le template sp�cifi� dans la cl� <code><errortemplate></code> du fichier
     * de configuration.
     *
     * Avant de faire la suppression, redirige vers la pseudo action
     * {@link actionConfirmDelete() ConfirmDelete} pour demander une confirmation.
     *
     * Pour confirmer, l'utilisateur dispose du nombre de secondes d�fini dans
     * la cl� <code><timetoconfirm></code> de la configuration. Si la cl� n'est pas
     * d�finie, il dispose de 30 secondes.
     *
     * Si l'�quation de recherche donne un nombre de notices sup�rieur au nombre
     * sp�cifi� dans la cl� <code><maxrecord></code> de la configuration (par d�faut,
     * maxrecord = 1), cr�e une t�che dans le {@link TaskManager gestionnaire de t�ches},
     * qui ex�cutera l'action {@link actionBatchDelete() BatchDelete}.
     *
     * @param timestamp $confirm l'heure courante, mesur�e en secondes depuis le
     * 01/01/1970 00h00 (temps Unix). Permet de redemander confirmation si
     * l'utilisateur n'a pas confirm� dans le d�lai qui lui �tait imparti.
     */
    public function actionDelete($confirm=0)
    {
        // Ouvre la base de donn�es
        $this->openDatabase(false);

        // R�cup�re l'�quation de recherche qui donne les enregistrements � supprimer
        $this->equation=$this->getEquation();

        // Param�tre equation manquant
        if (is_null($this->equation))
        {
            $this->showError('Le ou les num�ros des notices � supprimer n\'ont pas �t� indiqu�s.');
            return;
        }

        // Aucune r�ponse
        if (! $this->select($this->equation, -1) )
        {
            $this->showError("Aucune r�ponse. Equation : $this->equation");
            return;
        }

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
        // FIXME: optionnel, voir si on garde
        unset($this->selection);
    }

    /**
     * Supprime plusieurs notices, � partir d'une t�che du {@link TaskManager TaskManager}.
     *
     * Dans le cas de la suppression d'une seule notice, c'est l'action
     * {@link actionDelete() Delete} qui est utilis�e.
     */
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
        {
            $this->showError('Le ou les num�ros des notices � supprimer n\'ont pas �t� indiqu�s.');
            return;
        }

        // Aucune r�ponse
        if (! $this->select($this->equation, -1) )
        {
            $this->showError("Aucune r�ponse. Equation : $this->equation");
            return;
        }

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
     * Effectue un chercher/remplacer.
     *
     * Effectue le chercher/remplacer et appelle le template indiqu� dans la cl�
     * <code><template></code> de la configuration.
     *
     * La source de donn�e <code>count</code> est pass�e � <code>Template::run</code>
     * et permet au template d'afficher s'il y a eu une erreur (<code>$count === false</code>)
     * ou le nombre de remplacements effectu�s s'il n'y a pas d'erreur
     * (<code>$count</code> contient alors le nombre d'occurences remplac�es).
     *
     * @param string $_equation l'�quation de recherche permettant d'obtenir les
     * notices sur lesquelles le remplacement est r�alis�.
     * @param string $search la cha�ne � rechercher.
     * @param string $replace la cha�ne qui viendra remplacer la cha�ne � rechercher.
     * @param array $fields le ou les champs dans lesquels se fait le remplacement.
     * @param bool $word indique si la cha�ne � rechercher est � consid�rer ou
     * non comme un mot entier.
     * @param bool $ignoreCase indique si la casse des caract�res doit �tre ignor�e
     * ou non.
     * @param bool $regexp indique si le remplacement se fait � partir d'une
     * expression r�guli�re.
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
        {
            $this->showError("Aucune r�ponse. Equation : $this->equation");
            return;
        }

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
        echo '</ul>', "\n";

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

    /**
     * Exporte des notices � partir d'une �quation de recherche pass�e en param�tre.
     *
     * Les formats d'export disponibles sont list�s dans la cl� <code><formats></code>
     * de la configuration.
     *
     * Premi�re �tape : affichage de la liste des formats disponibles et
     * s�lection par l'utilisateur du type d'export � faire (envoi par email ou
     * affichage ou d�chargement de fichier).
     *
     * Seconde �tape : ex�cution du template correspondant au format d'export
     * choisi en indiquant le type mime correct.
     *
     * @param bool $calledFromPreExecute indique si l'action a �t� appel�e
     * (<code>true</code>) ou non depuis la m�thode {@link preExecute}.
     *
     * @return bool retourne true pour indiquer que l'export a �t� fait, false pour
     * afficher le formulaire d'export.
     */
    public function actionExport($calledFromPreExecute=false)
    {
        $error=null;

        // D�termine la ou les �quations de recherche � ex�cuter
        $equations=Config::get('equation', $this->request->defaults('_equation','')->asArray()->ok());
        if (! $equations) throw new Exception('Aucune �quation de recherche indiqu�e.');
        $equations=(array)$equations;

        // D�termine l'ordre de tri des r�ponses
        $sort=Config::get('sort', $this->request->get('_sort'));

        // D�termine le nom des fichiers � g�n�rer
        $filename=$this->request->get('filename');

        // D�termine s'il faut envoyer un e-mail
        if ($mail=$allowmail=(bool)Config::get('allowmail'))
            if(! $mail=(bool)Config::get('forcemail'))
                $mail=$this->request->bool('_mail')->unique()->defaults(false)->ok();

        // S'il faut envoyer un e-mail, d�termine le destinataire, le sujet et le message de l'e-mail
        $to=$this->request->defaults('_to','')->unique()->ok();
        $subject=$this->request->defaults('_subject', (string)Config::get('mailsubject'))->unique()->ok();
        $message=$this->request->defaults('_message', (string)Config::get('mailbody'))->unique()->ok();

        if ($mail && !$to) $error[]='Veuillez indiquer l\'adresse du destinataire de l\'e-mail.';

        // D�termine s'il faut g�n�rer une archive au format zip
        if ($zip=$allowzip=(bool)Config::get('allowzip'))
            if(! $zip=$forcezip=(bool)Config::get('forcezip'))
                $zip=$this->request->bool('_zip')->unique()->defaults(false)->ok();

        // Si on a plusieurs fichiers et qu'on n'envoie pas un mail, force l'option zip
        if (count($equations)>1 && !$mail)
            $zip=true;

        // Si l'option zip est activ�e, v�rifie qu'on a l'extension php requise
        if ($zip && ! class_exists('ZipArchive'))
            throw new Exception("La cr�ation de fichiers ZIP n'est pas possible sur ce serveur : l'extension PHP requise n'est pas disponible.");

        // Charge la liste des formats d'export disponibles
        if ($calledFromPreExecute)
            if (!$this->loadExportFormats())
                throw new Exception("Aucun format d'export n'est disponible");

        // Choix du format d'export
        $formats=Config::get('formats');
        if (count($formats)===1) // Un seul format est propos�, inutile de demander � l'utilisateur
        {
            $fmt=reset($formats);
            $format=key($formats);
        }
        else
        {
            if ($format=$this->request->unique('_format')->ok())
                if(is_null($fmt=Config::get("formats.$format")))
                    throw new Exception("Format d'export incorrect");
        }

        // D�termine s'il faut afficher le formulaire
        $showForm =     $error          // s'il y a une erreur
                    ||  ! $format       // ou qu'on n'a pas de format
                                        // ou que l'utilisateur n'a pas encore choisi parmi les options disponibles
                    ||  ($format && ($allowmail || ($allowzip && !$forcezip)) && !$this->request->get('confirm'));

        if ($showForm)
        {
            if ($calledFromPreExecute) return false;

            // D�termine le template � utiliser
            if (! $template=$this->getTemplate())
                throw new Exception('Le template � utiliser n\'a pas �t� indiqu�');

            // D�termine le callback � utiliser
            $callback=$this->getCallback();

            // D�termine quel est le format par d�faut pour cet utilisateur
            foreach(Config::get('formats') as $name=>$fmt)
            {
                if (Config::userGet('formats.'.$name.'.default'))
                {
                    $defaultFormat=$name;
                    break;
                }
            }

            // Ex�cute le template
            Template::run
            (
                $template,
                array($this, $callback),
                array
                (
                    'error'=>$error,
                    'equations'=>$equations,
                    'sort'=>$sort,
                    'filename'=>$filename,
                    'format'=>$format ? $format : $defaultFormat,
                    'zip'=>$zip,
                    'mail'=>$mail,
                    'to'=>$to,
                    'subject'=>$subject,
                    'message'=>$message,
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
        if (preg_match('~;\s?filename\s?=\s?(?:"([^"]+)"|([^ ;]+))~i', Utils::get($fmt['content-disposition']), $match))
            $basename=Utils::get($match[2], $match[1]);
        elseif ($template=Utils::get($fmt['template']))
            $basename=$template;

        if (stripos($basename, '%s')===false)
            $basename=Utils::setExtension($basename,'').'%s'.Utils::getExtension($basename);

        // G�n�re un nom de fichier unique pour chaque fichier en utilisant l'�ventuel filename pass� en query string
        $filenames=array();
        foreach($equations as $i=>$equation)
        {
            $h=is_array($filename) ? Utils::get($filename[$i],'') : $filename;
            // if (! $h) $h='export';
            $j=1;
            $result=sprintf($basename, $h);
            while(in_array($result, $filenames))
                $result=sprintf($basename, $h.''.(++$j));

            $filenames[$i]=$result;
        }

        // D�termine le nombre maximum de notices que l'utilisateur a le droit d'exporter
        $max=Config::userGet("formats.$format.max",10);

        // Ouvre la base de donn�es
        $this->openDatabase();

        // Ex�cute toutes les recherches dans l'ordre
        $files=array();
        $counts=array();
        $filesizes=array();
        foreach($equations as $i=>$equation)
        {
            // Lance la recherche, si aucune r�ponse, erreur
            if (! $this->select($equation, $max, 1, $sort))
            {
                echo "Aucune r�ponse pour l'�quation $equation<br />";
                continue;
            }

            $counts[$i]=$max===-1 ? $this->selection->count() : (min($max,$this->selection->count()));

            // Si l'utilisateur a demand� un envoi par mail ou un zip, d�marre la capture
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
                if (! $template=Config::userGet("formats.$format.template"))
                {
                    if ($mail or $zip) Utils::endCapture(); // sinon on ne "verra" pas l'erreur
                    throw new Exception("Le template � utiliser pour l'export en format $format n'a pas �t� indiqu�");
                }

                // D�termine le callback � utiliser
                $callback=Config::userGet("formats.$format.callback");

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
        $template=Config::userGet('mailtemplate');
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

    /**
     * Affiche le r�sultat d'une recherche dans une table des entr�es (lookup) (pour Xapian uniquement).
     *
     * Recherche dans la table des entr�es <code>$table</code> les valeurs qui
     * commencent par le terme <code>$value</code> indiqu� (voir m�thode
     * {@link XapianDatabase2#lookup lookup} de {@link XapianDatabase2}, et affiche
     * le r�sultat en utilisant le template retourn� par la m�thode {@link getTemplate()}
     * et le callback retourn� par la m�thode {@link getCallback()}.
     *
     * @param string $table le nom de la table des entr�es.
     * @param string $value le terme recherch�.
     * @param int $max le nombre maximum de valeurs � retourner (0=pas de limite).
     */
    function actionLookup($table, $value='', $max=10, $sort=false)
    {
        if (!headers_sent())
            header('Content-type: text/html; charset=iso-8859-1');

        $max=$this->request->defaults('max', 10)->int()->min(0)->ok();

        // Ouvre la base
        $this->openDatabase();

        // Lance la recherche
        $terms=$this->selection->lookup($table, $value, $max, $sort);

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
     * Lance une r�indexation compl�te de la base de donn�es.
     *
     * Cette action se contente de rediriger l'utilisateur vers l'action
     * {@link DatabaseAdmin#actionReindex Reindex} de {@link DatabaseAdmin}.
     */
    public function actionReindex()
    {
        Runtime::redirect('/DatabaseAdmin/Reindex?database='.Config::get('database'));
    }
















    /**
     * Ouvre la base de donn�es du module.
     *
     * La base � ouvrir est indiqu�e dans la cl� <code><database></code> du fichier
     * de configuration du module.
     *
     * @param bool $readOnly indique si la base doit �tre ouverte en lecture seule
     * (valeur par d�faut) ou en lecture/�criture.
     *
     * @return bool true si une recherche a �t� lanc�e et qu'on a au moins
     * une r�ponse, false sinon.
     */
    protected function openDatabase($readOnly=true)
    {
        $database=Config::get('database');
        // Le fichier de config du module indique la base � utiliser

        if (is_null($database))
            throw new Exception('La base de donn�es � utiliser n\'a pas �t� indiqu�e dans le fichier de configuration du module');

        Timer::enter('Ouverture de la base '.$database);
        debug && Debug::log("Ouverture de la base '%s' en mode '%s'", $database, $readOnly ? 'lecture seule' : 'lecture/�criture');
        $this->selection=Database::open($database, $readOnly);
        Timer::leave();
    }

    /**
     * Lance une recherche en d�finissant les options de recherche et s�lectionne
     * les notices correspondantes.
     *
     * Les valeurs des options de recherche sont d�finies en examinant dans l'ordre :
     * - le param�tre transmis � la m�thode s'il est non null,
     * - le param�tre transmis � la {@link $request requ�te} s'il est non null, en
     * v�rifiant le type,
     * - la valeur indiqu�e dans le fichier de configuration.
     *
     * @param string $equation l'�quation de recherche.
     *
     * @param int|null $max le nombre maximum de notices � retourner, ou null
     * si on veut r�cup�rer le nombre � partir de la {@link $request requ�te} ou
     * � partir de la cl� <code><max></code> du fichier de configuration.
     *
     * @param int|null $start le num�ro d'ordre de la notice sur laquelle se positionner
     * une fois la recherche effectu�e, ou null si on veut r�cup�rer le num�ro �
     * partir de la {@link $request requ�te} ou � partir de la cl� <code><start></code>
     * du fichier de configuration.
     *
     * @param string|null $sort l'ordre de tri des r�sultats ou null si on veut
     * r�cup�rer l'ordre de tri � partir de la {@link $request requ�te} ou � partir
     * de la cl� <code><sort></code> du fichier de configuration.
     *
     * @return bool true si au moins une notice a �t� trouv�e, false s'il n'y
     * a aucune r�ponse.
     */
    protected function select($equation, $max=null, $start=null, $sort=null)
    {
        Timer::enter('Ex�cution de la requ�te '.$equation);
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

            '_defaultop'=>
                $this->request->defaults('_defaultop', Config::get('defaultop'))
                    ->ok(),

            '_opanycase'=>
                $this->request->defaults('_opanycase', Config::get('opanycase'))
                    ->ok(),

            '_facets' => Config::get('facets'),
        );
        $result=$this->selection->search($equation, $options);
        Timer::leave();
        return $result;
    }

    /**
     * Affiche un message si une erreur s'est produite lors de la recherche.
     *
     * Le template � utiliser est indiqu� dans la cl� <code><errortemplate></code>
     * de la configuration de l'action {@link actionSearch() Search}.
     *
     * @param string $error le message d'erreur � afficher (pass� �
     * <code>Template::run</code>) via la source de donn�e <code>error</code>.
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
     * Affiche un message si aucune r�ponse n'est associ�e � la recherche.
     *
     * Le template � utiliser est indiqu� dans la cl� <code><noanswertemplate></code>
     * de la configuration de l'action {@link actionSearch() Search}.
     *
     * @param string $message le message � afficher (pass� � <code>Template::run</code>)
     * via la source de donn�e <code>message</code>.
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
     *   <code>_equation</code> de la {@link $request requ�te en cours},
     *
     * - tous les param�tres de la requ�te dont le nom correspond � un nom
     *   d'index ou d'alias de type probabiliste (ceux sui sont de type
     *   bool�ens sont retourn�s par getFilter).
     *
     * Si aucun des �l�ments ci-dessus ne retourne une �quation de recherche,
     * getEquation() utilise la ou les �quation(s) de recherche indiqu�e(s)
     * dans la cl� <code><equation></code> de la configuration (des �quations
     * par d�faut diff�rentes peuvent �tre indiqu�es selon les droits de
     * l'utilisateur, voir {@link Config::userGet()}).
     *
     * Les modules qui h�ritent de DatabaseModule peuvent surcharger cette
     * m�thode pour changer le comportement par d�faut.
     *
     * @return null|string l'�quation de recherche obtenue ou null si aucune
     * �quation ne peut �tre construite.
     */
    protected function getEquation()
    {
        return $this->getEquationPart('_equation', DatabaseSchema::INDEX_PROBABILISTIC, true);
    }

    /**
     * Construit l'�quation qui sera utilis�e comme filtre pour lancer la
     * recherche dans la base.
     *
     * Les filtres seront combin�s � l'�quation de recherche retourn�e par
     * {@link getEquation()} de telle sorte que seuls les enregistrements qui
     * passent les filtres soient pris en compte.
     *
     * La mani�re dont les filtres sont combin�s � l'�quation d�pend du driver
     * de base de donn�es utilis� (BisDatabase combine en 'SAUF', XapianDatabase
     * utilise l'op�rateur xapian 'FILTER').
     *
     * Par d�faut, getFilter() combine en 'ET' les �l�ments suivants :
     *
     * - la ou le(s) �quation(s) de recherche qui figure dans l'argument
     *   <code>_filter</code> de la {@link $request requ�te en cours},
     *
     * - tous les param�tres de la requ�te dont le nom correspond � un nom
     *   d'index ou d'alias de type boolean (ceux sui sont de type
     *   probabiliste sont retourn�s par getEquation).
     *
     * Si aucun des �l�ments ci-dessus ne retourne une �quation de recherche,
     * getFilter() utilise la ou les �quation(s) filtres indiqu�e(s)
     * dans la cl� <code><filter></code> de la configuration (des filtres
     * diff�rents peuvent �tre indiqu�es selon les droits de l'utilisateur,
     * voir {@link Config::userGet()}).
     *
     * Les modules descendants peuvent surcharger cette m�thode pour modifier
     * ce comportement par d�faut.
     *
     * @return null|string l'�quation de recherche obtenue ou null aucun filtre
     * n'a �t� sp�cifi�.
     */
    protected function getFilter()
    {
        return $this->getEquationPart('_filter', DatabaseSchema::INDEX_BOOLEAN, false);
    }


    /**
     * Construit la partie probabiliste ou la partie filtre de l'�quation de
     * recherche � ex�cuter en fonction de la requ�te et des arguments pass�s
     * en param�tres.
     *
     * getEquationPart() et la fonction interne utilis�e par
     * {@link getEquation()} et {@link getFilter()} pour construire l'�quation
     * de recherche � ex�cuter.
     *
     * La m�thode combine en 'ET' les �l�ments suivants :
     *
     * - la ou le(s) �quation(s) de recherche qui figure dans l'argument
     *   <code>$parameterName</code> de la {@link $request requ�te en cours},
     *
     * - tous les param�tres de la requ�te dont le nom correspond � un nom
     *   d'index ou d'alias ayant le type <code>$indexType</code> pass� en
     *   param�tre.
     *
     * Si aucun des �l�ments ci-dessus ne retourne une �quation de recherche,
     * getEquationPart() utilise la ou les �quation(s) de recherche indiqu�e(s)
     * dans la cl� <code>$parameterName</code> de la configuration (en
     * supprimant le underscore initial �ventuel : '_equation' :
     * config::get('equation').
     *
     * Si aucune �quation par d�faut n'a �t� indiqu�e, la m�thode retourne
     * null.
     *
     * Les modules qui h�ritent de DatabaseModule peuvent surcharger cette
     * m�thode pour changer le comportement par d�faut.
     *
     *
     * @param $parameterName le nom du param�tre � r�cup�rer dans la query
     * string (soit '_equation', soit '_filter').
     *
     * @param $indexType le type d'index � prendre en compte
     * (DatabaseSchema::INDEX_PROBABILISTIC ouDatabaseSchema::INDEX_BOOLEAN).
     * Seuls les arguments correspondant � un index ou un alias de ce type seront
     * ajout�s � l'�quation finale.
     *
     * @param boolean $configIsDefault indique si l'�quation indiqu�e dans la
     * config est une valeur par d�faut (elle n'est retourn�e que si aucune
     * �quation n'a pu �tre construite) ou non (elle est syst�matiquement
     * ajout�e � l'�quation de recherche).
     *
     * @return null|string l'�quation de recherche obtenue ou null si aucune
     * �quation ne peut �tre construite.
     */
    protected function getEquationPart($parameterName, $indexType, $configIsDefault)
    {
        // Supprime de la requ�te tous les param�tres qui sont vides
        $request=$this->request->copy()->clearNull();

        // R�cup�re les param�tres "_equation"
        $equations=$request->asArray($parameterName)->ok();

        // Ajoute tous les param�tres qui sont des index de type probabiliste
        $schema=$this->selection->getSchema();
        foreach($request->getParameters() as $name=>$value)
        {
            // D�termine s'il s'agit d'un index ou d'un alias
            $name=strtolower($name);
            if (isset($schema->indices[$name]))
                $index=$schema->indices[$name];
            elseif(isset($schema->aliases[$name]))
                $index=$schema->aliases[$name];
            else
                continue;

            // On ne prend en compte que les index ayant le type demand�
            if (isset($index->_type) && $index->_type !== $indexType) continue;

            // Combine en OU Les param�tres de m�me nom (e.g. plusieurs dates)
            if (is_array($value)) $value=implode(' OR ', (array)$value);
            $this->addBrackets($value);
            $equations[]=$name.'='.$value;
        }

        // D�termine l'�quation par d�faut qui figure dans la configuration
        $default=Config::userGet(ltrim($parameterName,'_'), null);

        // Si $configIsDefault est � false, on l'ajoute � l'�quation
        if (! $configIsDefault && !is_null($default))
            $equations[]=$default;

        // Retourne le r�sultat
        switch (count($equations))
        {
            // Aucune �quation : retourne l'�quation par d�faut
            case 0:
                return $default;

            // Une seule �quation : retourne tel quel
            case 1:
                return array_pop($equations);

            // Plusieurs �quations : combine en ET
            default:
                // Ajoute des parenth�ses si n�cessaire
                foreach($equations as & $equation)
                    $this->addBrackets($equation);

                // Combine en et
                return implode(' AND ', $equations);
        }
    }

    /**
     * Ajoute des parenth�ses autour de l'�quation pass�e au param�tre si c'est
     * n�cessaire.
     *
     * La m�thode consid�re que l'�quation pass�e en param�tre est destin�e �
     * �tre combin�e en "ET" avec d'autres �quations.
     *
     * Dans sa version actuelle, la m�thode supprime de l'�quation les blocs
     * parenth�s�s, les phrases et les articles et ajoute des parenth�ses si
     * ce qui reste contient l'op�rateur ou.
     *
     * Id�alement, il faudrait faire un traitement beaucoup plus compliqu�, mais
     * �a revient quasiment � r�-�crire un query parser.
     *
     * Le traitement actuel est plus simple mais semble fonctionner.
     *
     * @param string $equation l'�quation � tester.
     */
    private function addBrackets(& $equation)
    {
        $h=$equation;
        do
        {
            $h=preg_replace('~(?:\[.*\])|(?:".*")|(?:\([^()]*\))~', '', $h, -1, $count);
        }
        while ($count);

        if (false !== stripos($h, ' OR ') || false !== stripos($h, ' OU '))
            $equation='('.$equation.')';
    }

    /**
     * G�n�re une barre de navigation affichant le nombre de r�ponses obtenues
     * et les liens suivant/pr�c�dent.
     *
     * @param string $prevLabel libell� � utiliser pour le lien "Pr�c�dent".
     * @param string $nextLabel libell� � utiliser pour le lien "Suivant".
     * @return string le code html de la barre de navigation.
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
     * G�n�re une barre de navigation affichant le nombre de r�ponses obtenues
     * et permettant d'acc�der directement aux pages de r�sultat.
     *
     * La barre de navigation pr�sente les liens vers :
     * - la premi�re page,
     * - la derni�re page,
     * - la page pr�c�dente,
     * - la page suivante,
     * - <code>$links</code> pages de r�sultat.
     *
     * Voir {@link http://www.smashingmagazine.com/2007/11/16/pagination-gallery-examples-and-good-practices/}
     *
     * @param int $links le nombre maximum de liens g�n�r�s sur la barre de navigation.
     * Par d�faut, 9 liens sont affich�s.
     * @param string $previousLabel libell� du lien "Pr�c�dent", "<" par d�faut.
     * @param string $nextLabel libell� du lien "Suivant", ">" par d�faut.
     * @param string $firstLabel libell� du lien "Premi�re page", "�" par d�faut.
     * @param string $lastLabel libell� du lien "Derni�re page", "�" par d�faut.
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
        $request=Routing::linkFor(Runtime::$request->copy()->clearNull()->clear('_start'));
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
     * Callback pour l'action {@link actionSave() Save} autorisant la modification
     * de tous les champs d'un enregistrement.
     *
     * Par d�faut, le callback de l'action {@link actionSave() Save} est � <code>none</code>.
     * Cette fonction est une facilit� offerte � l'utilisateur pour lui �viter
     * d'avoir � �crire un callback � chaque fois : il suffit de cr�er un pseudo
     * module et, dans la cl� <code><save.callback></code> de la configuration de ce
     * module, de mettre la valeur <code>allowSave</code>.
     *
     * @param string $name nom du champ de la base.
     * @param mixed $value contenu du champ $name.
     * @return bool true pour autoriser la modification de tous les champs d'un
     * enregistrement.
     */
    public function allowSave($name, &$value)
    {
        return true;
    }

    /**
     * Retourne le template � utiliser pour l'action en cours ({@link $action}).
     *
     * La m�thode retourne le nom du template indiqu� dans la cl�
     * <code><template></code> du fichier de configuration.
     *
     * Dans cette cl�, vous pouvez indiquer soit le nom d'un template qui sera
     * utilis� dans tous les cas, soit un tableau qui va permettre d'indiquer
     * le template � utiliser en fonction des droits de l'utilisateur en cours.
     *
     * Dans ce cas, les cl�s du tableau indiquent le droit � avoir et le template
     * � utiliser. Le tableau est examin� dans l'ordre indiqu�. A vous
     * d'organiser les cl�s pour que les droits les plus restrictifs
     * apparaissent en premier.
     *
     * Vous pouvez utiliser le pseudo droit <code><default></code> pour
     * indiquer le template � utiliser lorsque l'utilisateur ne dispose d'aucun des
     * droits indiqu�s.
     *
     * Exemple :
     * <code>
     *     <!-- Exemple 1 : Le template form.html sera toujours utilis� -->
     *     <template>form.html</template>
     *
     *     <!--
     *         Exemple 2 : on utilisera le template admin.html pour les
     *         utilisateurs disposant du droit 'Admin', le template 'edit.html'
     *         pour ceux ayant le droit 'Edit' et 'form.html' pour tous les
     *         autres
     *     -->
     *     <template>
     *         <Admin>admin.html</Admin>
     *         <Edit>edit.html</Edit>
     *         <default>form.html</default>
     *     </template>
     * </code>
     *
     * Remarque :
     * Les modules descendants de <code>DatabaseModule</code> peuvent surcharger
     * cette m�thode si un autre comportement est souhait�.
     *
     * @param string $key nom de la cl� du fichier de configuration qui contient
     * le nom du template. La cl� est par d�faut <code><template></code>.
     * @return string|null le nom du template � utiliser ou null.
     */
    protected function getTemplate($key='template')
    {
        debug && Debug::log('%s : %s', $key, Config::userGet($key));
        if (! $template=Config::userGet($key))
            return null;

        if (file_exists($h=$this->path . $template)) // fixme : template relatif � BisDatabase, pas au module h�rit� (si seulement config). Utiliser le searchPath du module en cours
        {
            return $h;
        }
        return $template;
    }

    /**
     * Retourne le callback � utiliser pour l'action en cours ({@link $action}).
     *
     * getCallback() appelle la m�thode {@link Config::userGet()} en passant la cl�
     * <code>callback</code> en param�tre. Retourne null si l'option de configuration
     * <code><callback></code> n'est pas d�finie.
     *
     * @return string|null la valeur de l'option de configuration <code><callback></code>
     * si elle existe ou null sinon.
     */
    protected function getCallback()
    {
        debug && Debug::log('callback : %s', Config::userGet('callback'));
        return Config::userGet('callback');
    }

    /**
     * Exemple de g�n�rateur en format xml simple pour les formats d'export.
     *
     * @param array $format les caract�ristiques du format d'export.
     */
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

    /**
     * Retourne la valeur d'un �l�ment � �crire dans le fichier de log.
     *
     * Nom d'items reconnus par cette m�thode :
     * - tous les items d�finis dans la m�thode {@link Module::getLogItem()}
     * - database : le nom de la base de donn�es
     * - equation : l'�quation de recherche
     * - count : le nombre de r�ponses de la recherche en cours
     * - fmt : le format d'affichage des r�ponses
     * - sort : le tri utilis� pour afficher les r�ponses
     * - max : le nombre maximum de r�ponses affich�es sur une page
     * - start : le num�ro d'ordre de la notice sur laquelle
     *
     * @param string $name le nom de l'item.
     *
     * @return string la valeur � �crire dans le fichier de log pour cet item.
     */
    protected function getLogItem($name)
    {
        switch($name)
        {
            // Base de donn�es
            case 'database': return Config::get('database');

            // Items sur la recherche en cours
            case 'equation': return $this->equation;
            case 'count':    return is_null($this->equation) ? '' : $this->selection->count();
            case 'fmt':      return $this->request->get('_fmt');
            case 'sort':     return $this->request->get('_sort');
            case 'max':      return $this->request->get('_max');
            case 'start':    return $this->request->get('_start');

            // Items sur l'affichage d'un enregistrement
            case 'ref':      return $this->request->get('REF');
        }

        return parent::getLogItem($name);
    }

    // ****************** m�thodes priv�es ***************

    /**
     * Charge la liste des formats d'export disponibles dans la configuration en cours.
     *
     * Seuls les formats auxquels l'utilisateur a acc�s sont charg�s (param�tre
     * <code><access></code> de chaque format).
     *
     * Les formats charg�s sont ajout�s dans la configuration en cours dans la cl�
     * <code><formats></code>.
     *
     * <code>Config::get('formats')</code> retourne la liste de tous les formats.
     *
     * <code>Config::get('formats.csv')</code> retourne les param�tres d'un format particulier.
     *
     * @return int le nombre de formats charg�s.
     */
    private function loadExportFormats()
    {
        // Balaye la liste des formats d'export disponibles
        foreach((array) Config::get('formats') as $name=>$format)
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
                Config::set("formats.$name.max", Config::userGet("formats.$name.max",300));
            }
        }

        // Retourne le nombre de formats charg�s
        return count(Config::get('formats'));
    }

    /**
     * Fonction utilitaire utilis�e par les template rss : retourne le premier
     * champ renseign� ou la valeur par d�faut sinon.
     *
     * @param mixed $fields un nom de champ ou un tableau contenant les noms
     * des champs � �tudier.
     * @param string|null la valeur par d�faut � retourner si tous les champs
     * indiqu�s sont vides.
     * @return mixed le premier champ rempli.
     */
    public function firstFilled($fields, $default=null)
    {
        foreach((array)$fields as $field)
        {
            $value=$this->selection[$field];

            if (is_null($value)) continue;
            if ($value==='') continue;
            if (is_array($value) && count($value)===0) continue;
            if (is_array($value)) $value=reset($value);
            return $value;
        }
        return $default;
    }
}
?>