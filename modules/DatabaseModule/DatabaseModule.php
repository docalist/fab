<?php
/**
 * @package     fab
 * @subpackage  modules
 * @author      Daniel Ménard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id$
 */

/**
 * Ce module permet de publier une base de données sur le web.
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
     * La sélection en cours
     *
     * @var Database
     */
    public $selection=null;

    /**
     * Permet à l'action {@link actionExport() Export} d'avoir un layout s'il faut
     * afficher le formulaire d'export, et de ne pas en avoir un si on dispose
     * de tous les paramètres requis pour lancer l'export.
     *
     * @return bool retourne true pour interrompre l'exécution de l'action, l'export
     * a été fait.
     */
    public function preExecute()
    {
        // HACK: permet à l'action export d'avoir un layout s'il faut afficher le formulaire d'export
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
        if ($this->method==='actionExport')
        {
            $defaultLayout=Config::get('layout');
            $this->setLayout('none');               // essaie de faire l'export, pour ça on met layout à none
            if ($this->actionExport(true)===true)   // l'export a été fait, terminé, demande à fab de s'arrêter
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
     * L'action searchForm affiche le template retourné par la méthode
     * {@link getTemplate()} en utilisant le callback retourné par la méthode
     * {@link getCallback()}.
     */
    public function actionSearchForm()
    {
        // Ouvre la base de données : permet au formulaire de recherche de consulter le schéma
        $this->openDatabase();

        // Détermine le template à utiliser
        if (! $template=$this->getTemplate())
            throw new Exception('Le template à utiliser n\'a pas été indiqué');

        // Détermine le callback à utiliser
        $callback=$this->getCallback();

        // Exécute le template
        Template::run
        (
            $template,
            array($this, $callback)
        );
    }

    /**
     * Lance une recherche dans la base et affiche les réponses obtenues.
     *
     * L'action Search construit une équation de recherche en appellant la
     * méthode {@link getEquation()}. L'équation obtenue est ensuite combinée
     * avec les filtres éventuels retournés par la méthode {@link getFilter()}.
     *
     * Si aucun critère de recherche n'a été indiqué, un message d'erreur est
     * affiché, en utilisant le template spécifié dans la clé <code><errortemplate></code>
     * du fichier de configuration.
     *
     * Si l'équation de recherche ne fournit aucune réponse, un message est affiché
     * en utilisant le template défini dans la clé <code><noanswertemplate></code>
     * du fichier de configuration.
     *
     * Dans le cas contraire, la recherche est lancée et les résultats sont
     * affichés en utilisant le template retourné par la méthode
     * {@link getTemplate()} et le callback indiqué par la méthode
     * {@link getCallback()}.
     *
     * Si la clé <code><history></code> de la configuration est à <code>true</code>,
     * la requête est ajoutée à l'historique des équations de recherche.
     * L'historique peut contenir, au maximum, 10 équations de recherche.
     */
    public function actionSearch()
    {
        Timer::enter();

        // Ouvre la base de données
        $this->openDatabase();

        // Détermine la recherche à exécuter
        $this->equation=$this->getEquation();

        // Affiche le formulaire de recherche si on n'a aucun paramètre
        if (is_null($this->equation))
        {
            if (! $this->request->hasParameters())
                Runtime::redirect('searchform'); // c'est la seule différence avec ActionShow. Si on avait dans la config 'redirectToSearchForm yes/no', show pourrait être une pseudo action
            $this->showError('Vous n\'avez indiqué aucun critère de recherche.');
            return;
        }

        // Aucune réponse
        if (! $this->select($this->equation))
        {
            $this->showNoAnswer("La requête $this->equation n'a donné aucune réponse.");
            return;
        }

        // Détermine le template à utiliser
        if (! $template=$this->getTemplate())
            throw new Exception('Le template à utiliser n\'a pas été indiqué');

        // Détermine le callback à utiliser
        $callback=$this->getCallback();

        // Exécute le template
        Timer::enter('Exécution du template d\'affichage des réponses');

        Template::run
        (
            $template,
            array($this, $callback),
            $this->selection->record
        );
        Timer::leave();

        // Ajoute la requête dans l'historique des équations de recherche
        $history=Config::userGet('history', false);

        if ($history===true) $history=10;
        elseif ($history===false) $history=0;

        if (!is_int($history)) $history=0;
        if ($history>0 && ! Utils::isAjax())
            $this->updateSearchHistory($history);
        Timer::leave();
    }

    /**
     * Charge l'historique des équations de recherche.
     *
     * @return array tableau des équations de recherche stocké dans la session.
     */
    private function & loadSearchHistory()
    {
        // Nom de la clé dans la session qui stocke l'historique des recherches pour cette base
        $historyKey='search_history_'.Config::get('database');

        // Récupère l'historique actuel
        if (!isset($_SESSION[$historyKey])) $_SESSION[$historyKey]=array();
        return $_SESSION[$historyKey];
    }

    /**
     * Met à jour l'historique des équations de recherche.
     *
     * @param int $maxHistory le nombre maximum d'équations de recherche que
     * l'historique peut contenir.
     */
    private function updateSearchHistory($maxHistory=10)
    {
        Timer::enter('Mise à jour de l\'historique des recherches');

        // Charge les sessions si ce n'est pas le cas (pas mis en config, comme ça la session n'est chargée que si on en a besoin)
        Runtime::startSession();

        // Charge l'historique
        $hist=& $this->loadSearchHistory();

        // Récupère l'équation à ajouter à l'historique
        $equation=$this->equation;
        $xapianEquation=$this->selection->searchInfo('internalfinalquery');

        // Crée une clé unique pour l'équation de recherche
        $key=crc32($xapianEquation); // ou md5 si nécessaire

        // Si cette équation figure déjà dans l'historique, on la remet à la fin
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

        // Attribue un numéro à cette recherche
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

        // Ajoute l'équation (à la fin)
        $hist[$key]= array
        (
            'user' =>preg_replace('~[\n\r\f]+~', ' ', $equation), // normalise les retours à la ligne sinon le clearHistory ne fonctionne pas
            'xapian'=>$xapianEquation,
            'count'=>$this->selection->count('environ '),
            'time'=>time(),
            'number'=>$number
        );

//        echo 'Historique de recherche mis à jour : <br/>';
//        echo '<pre>', print_r($hist,true), '</pre>';
        Timer::leave();
    }

    /**
     * Efface l'historique des équations de recherche.
     *
     * Après avoir effacé l'historique, redirige l'utilisateur vers la page sur
     * laquelle il se trouvait.
     */
    public function actionClearSearchHistory($_equation=null)
    {
        // Charge les sessions si ce n'est pas le cas (pas mis en config, comme ça la session n'est chargée que si on en a besoin)
        Runtime::startSession();

        // Nom de la clé dans la session qui stocke l'historique des recherches pour cette base
        $historyKey='search_history_'.Config::get('database'); // no dry / loadSearchHistory

        // Récupère l'historique actuel
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
     * Retourne l'historique des équations de recherche.
     *
     * @return array tableau des équations de recherche stocké dans la session.
     */
    public function getSearchHistory()
    {
        return $this->loadSearchHistory();
    }

    /**
     * Affiche une ou plusieurs notices en "format long".
     *
     * Les notices à afficher sont données par une equation de recherche.
     *
     * Génère une erreur si aucune équation n'est accessible ou si elle ne
     * retourne aucune notice.
     *
     * Le template instancié peut ensuite boucler sur <code>{$this->selection}</code>
     * pour afficher les résultats.
     */
    public function actionShow()
    {
        // Ouvre la base de données
        $this->openDatabase();

        // Détermine la recherche à exécuter
        $this->equation=$this->getEquation();

        // Si aucun paramètre de recherche n'a été passé, erreur
        if (is_null($this->equation))
        {
            $this->showError('Vous n\'avez indiqué aucun critère permettant de sélectionner les notices à afficher.');
            return;
        }

        // Aucune réponse
        if (! $this->select($this->equation))
        {
            $this->showNoAnswer("La requête $this->equation n'a donné aucune réponse.");
            return;
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
            array($this, $callback),
            $this->selection->record
        );
    }

    /**
     * Crée une nouvelle notice.
     *
     * Affiche le formulaire indiqué dans la clé <code><template></code> de la configuration.
     *
     * La source de donnée 'REF' = 0 est passée au template pour indiquer à
     * l'action {@link actionSave() Save} qu'on crée une nouvelle notice.
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
     * Edite une notice.
     *
     * Affiche le formulaire indiqué dans la clé <code><template></code> de la
     * configuration, en appliquant le callback indiqué dans la clé <code><callback></code>.
     *
     * La notice correspondant à l'équation donnée est chargée dans le formulaire.
     * L'équation ne doit retourner qu'un seul enregistrement sinon une erreur est
     * affichée en utilisant le template défini dans la clé <code><errortemplate></code>.
     */
    public function actionLoad()
    {
        // Ouvre la base de données
        $this->openDatabase();

        // Détermine la recherche à exécuter
        $this->equation=$this->getEquation();

        // Erreur, si aucun paramètre de recherche n'a été passé
        // Erreur, si des paramètres ont été passés, mais tous sont vides et l'équation obtenue est vide
        if (is_null($this->equation))
        {
            $this->showError('Vous n\'avez indiqué aucun critère permettant de sélectionner la notice à modifier.');
            return;
        }

        // Si un numéro de référence a été indiqué, on charge cette notice
        // Vérifie qu'elle existe
        if (! $this->select($this->equation))
        {
            $this->showNoAnswer("La requête $this->equation n'a donné aucune réponse.");
            return;
        }

        // Si sélection contient plusieurs enreg, erreur
        if ($this->selection->count() > 1)
        {
            $this->showError('Vous ne pouvez pas éditer plusieurs enregistrements à la fois.');
            return;
        }

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
     * Saisie par duplication d'une notice existante.
     *
     * Identique à l'action {@link actionLoad() Load}, si ce n'est que la configuration
     * contient une section <code><fields></code> qui indique quels champs doivent
     * être copiés ou non.
     */
    public function actionDuplicate()
    {
        // Ouvre la base de données
        $this->openDatabase();

        // Détermine la recherche à exécuter
        $this->equation=$this->getEquation();

        // Erreur, si aucun paramètre de recherche n'a été passé
        // Erreur, si des paramètres ont été passés, mais tous sont vides et l'équation obtenue est vide
        if (is_null($this->equation))
        {
            $this->showError('Vous n\'avez indiqué aucun critère permettant de sélectionner la notice à modifier.');
            return;
        }

        // Si un numéro de référence a été indiqué, on charge cette notice
        // Vérifie qu'elle existe
        if (! $this->select($this->equation))
        {
            $this->showNoAnswer("La requête $this->equation n'a donné aucune réponse.");
            return;
        }

        // Si sélection contient plusieurs enreg, erreur
        if ($this->selection->count() > 1)
        {
            $this->showError('Vous ne pouvez pas éditer plusieurs enregistrements à la fois.');
            return;
        }

        // Détermine le template à utiliser
        if (! $template=$this->getTemplate())
            throw new Exception('Le template à utiliser n\'a pas été indiqué');

        // Détermine le callback à utiliser
        $callback=$this->getCallback();

        // Récupère dans la config la section <fields> qui indique les champs à dupliquer
        $fields=Config::get('fields');

        // Par défaut doit-on tout copier ou pas ?
        $default=(bool) Utils::get($fields['default'], true);
        unset($fields['default']);

        // Convertit les noms des champs en minu pour qu'on soit insensible à la casse
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
     * Sauvegarde la notice désignée par 'REF' avec les champs passés en
     * paramètre.
     *
     * REF doit toujours être indiqué. Si REF==0, une nouvelle notice sera
     * créée. Si REF>0, la notice correspondante sera écrasée. Si REF est absent
     * ou invalide, une exception est levée.
     *
     * Lors de l'enregistrement de la notice, appelle le callback retourné par la
     * méthode {@link getCallback()} (clé <code><callback></code> du fichier de
     * configuration). Ce callback permet d'indiquer à l'application s'il faut
     * interdire la modification des champs ou modifier leurs valeurs avant
     * l'enregistrement.
     *
     * Affiche ensuite le template retourné par la méthode {@link getTemplate()},
     * si la clé <code><template></code> est définie dans le fichier de configuration,
     * ou redirige l'utilisateur vers l'action {@link actionShow() Show} sinon.
     *
     * @param int $REF numéro de référence de la notice.
     */
    public function actionSave($REF)
    {
        // CODE DE DEBUGGAGE : save ne sauvegarde pas la notice si Runtime::redirect ne se termine
        // pas par exit(0) (voir plus bas)

        // TODO: dans la config, on devrait avoir, par défaut, access: admin (ie base modifiable uniquement par les admin)

        // Détermine le callback à utiliser
        $callback=$this->getCallback();

        // Par défaut, le callback du save est à 'none'. Le module descendant DOIT définir un callback pour pouvoir modifier la base
        if ($callback === 'none')
            throw new Exception("Cette base n'est pas modifiable (aucun callback définit pour le save");

        // Si REF n'a pas été transmis ou est invalide, erreur
        $REF=$this->request->required('REF')->unique()->int()->min(0)->ok();

        // Ouvre la base
        $this->openDatabase(false);

        // Si un numéro de référence a été indiqué, on charge cette notice
        if ($REF>0)
        {
            // Ouvre la sélection
            debug && Debug::log('Chargement de la notice numéro %s', $REF);

            if (! $this->select("REF=$REF"))
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

            $fieldValue=$this->request->get($fieldName);

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
        $this->selection->saveRecord();   // TODO: gestion d'erreurs

        // Récupère le numéro de la notice créée
        $REF=$this->selection['REF'];
        debug && Debug::log('Sauvegarde de la notice %s', $REF);

        // Redirige vers le template s'il y en a un, vers l'action Show sinon
        if (! $template=$this->getTemplate())
        {
            // Redirige l'utilisateur vers l'action show
            debug && Debug::log('Redirection pour afficher la notice enregistrée %s', $REF);
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
     * Si l'équation de recherche donne une seule notice, supprime la notice puis
     * affiche le template indiqué dans la clé <code><template></code> de la configuration.
     *
     * Si aucun critère de recherche n'a été indiqué ou si l'équation de recherche
     * ne fournit aucune réponse, un message d'erreur est affiché, en utilisant
     * le template spécifié dans la clé <code><errortemplate></code> du fichier
     * de configuration.
     *
     * Avant de faire la suppression, redirige vers la pseudo action
     * {@link actionConfirmDelete() ConfirmDelete} pour demander une confirmation.
     *
     * Pour confirmer, l'utilisateur dispose du nombre de secondes défini dans
     * la clé <code><timetoconfirm></code> de la configuration. Si la clé n'est pas
     * définie, il dispose de 30 secondes.
     *
     * Si l'équation de recherche donne un nombre de notices supérieur au nombre
     * spécifié dans la clé <code><maxrecord></code> de la configuration (par défaut,
     * maxrecord = 1), crée une tâche dans le {@link TaskManager gestionnaire de tâches},
     * qui exécutera l'action {@link actionBatchDelete() BatchDelete}.
     *
     * @param timestamp $confirm l'heure courante, mesurée en secondes depuis le
     * 01/01/1970 00h00 (temps Unix). Permet de redemander confirmation si
     * l'utilisateur n'a pas confirmé dans le délai qui lui était imparti.
     */
    public function actionDelete($confirm=0)
    {
        // Ouvre la base de données
        $this->openDatabase(false);

        // Récupère l'équation de recherche qui donne les enregistrements à supprimer
        $this->equation=$this->getEquation();

        // Paramètre equation manquant
        if (is_null($this->equation))
        {
            $this->showError('Le ou les numéros des notices à supprimer n\'ont pas été indiqués.');
            return;
        }

        // Aucune réponse
        if (! $this->select($this->equation, -1) )
        {
            $this->showError("Aucune réponse. Equation : $this->equation");
            return;
        }

        // Demande confirmation si ce n'est pas déjà fait
        $confirm=$this->request->int('confirm')->ok();
        $confirm=time()-$confirm;
        if($confirm<0 || $confirm>Config::get('timetoconfirm',30))  // laisse timetoconfirm secondes à l'utilisateur pour confirmer
            Runtime::redirect($this->request->setAction('confirmDelete'));

        // Récupère le nombre exact de notices à supprimer
        $count=$this->selection->count();

        // Crée une tâche dans le TaskManager si on a plus de maxrecord notices et qu'on n'est pas déjà dans une tâche
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

        // Supprime toutes les notices de la sélection
        foreach($this->selection as $record)
            $this->selection->deleteRecord();

        // Exécute le template
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
     * Supprime plusieurs notices, à partir d'une tâche du {@link TaskManager TaskManager}.
     *
     * Dans le cas de la suppression d'une seule notice, c'est l'action
     * {@link actionDelete() Delete} qui est utilisée.
     */
    public function actionBatchDelete()
    {
        // Détermine si on est une tâche du TaskManager ou si on est "en ligne"
        if (!User::hasAccess('cli')) die(); // todo: mettre access:cli en config

        // Ouvre la base de données
        $this->openDatabase(false);

        // Récupère l'équation de recherche qui donne les enregistrements à supprimer
        $this->equation=$this->getEquation();

        // Paramètre equation manquant
        if (is_null($this->equation))
        {
            $this->showError('Le ou les numéros des notices à supprimer n\'ont pas été indiqués.');
            return;
        }

        // Aucune réponse
        if (! $this->select($this->equation, -1) )
        {
            $this->showError("Aucune réponse. Equation : $this->equation");
            return;
        }

        // Récupère le nombre exact de notices à supprimer
        $count=$this->selection->count();

        // idea: avoir un template unique contenant un switch et qu'on appelle avec phase="before", "after", etc. à creuser.

        // Exécute le template
        Template::run
        (
            $this->getTemplate(),
            array($this, $this->getCallback())
        );

        // Supprime toutes les notices de la sélection
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
        echo '<p>Suppression des ', $count, ' enregistrements terminée</p>';
    }

    /**
     * Effectue un chercher/remplacer.
     *
     * Effectue le chercher/remplacer et appelle le template indiqué dans la clé
     * <code><template></code> de la configuration.
     *
     * La source de donnée <code>count</code> est passée à <code>Template::run</code>
     * et permet au template d'afficher s'il y a eu une erreur (<code>$count === false</code>)
     * ou le nombre de remplacements effectués s'il n'y a pas d'erreur
     * (<code>$count</code> contient alors le nombre d'occurences remplacées).
     *
     * @param string $_equation l'équation de recherche permettant d'obtenir les
     * notices sur lesquelles le remplacement est réalisé.
     * @param string $search la chaîne à rechercher.
     * @param string $replace la chaîne qui viendra remplacer la chaîne à rechercher.
     * @param array $fields le ou les champs dans lesquels se fait le remplacement.
     * @param bool $word indique si la chaîne à rechercher est à considérer ou
     * non comme un mot entier.
     * @param bool $ignoreCase indique si la casse des caractères doit être ignorée
     * ou non.
     * @param bool $regexp indique si le remplacement se fait à partir d'une
     * expression régulière.
     */
     public function actionReplace($_equation, $search='', $replace='', array $fields=array(), $word=false, $ignoreCase=true, $regexp=false)
     {
        // Vérifie les paramètres
        $this->equation=$this->request->required('_equation')->ok();
        $search=$this->request->unique('search')->ok();
        $replace=$this->request->unique('replace')->ok();
        $fields=$this->request->asArray('fields')->required()->ok();
        $word=$this->request->bool('word')->ok();
        $ignoreCase=$this->request->bool('ignoreCase')->ok();
        $regexp=$this->request->bool('regexp')->ok();

        // Vérifie qu'on a des notices à modifier
        $this->openDatabase(false);
        if (! $this->select($this->equation, -1) )
        {
            $this->showError("Aucune réponse. Equation : $this->equation");
            return;
        }

        $count=$this->selection->count();

        // Si on est "en ligne" (ie pas en ligne de commande), crée une tâche dans le TaskManager
        if (!User::hasAccess('cli'))
        {
            $options=array();
            if ($word) $options[]='mot entier';
            if ($ignoreCase) $options[]='ignorer la casse';
            if ($regexp) $options[]='expression régulière';
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

        echo '<h1>Modification en série</h1>', "\n";
        echo '<ul>', "\n";
        echo '<li>Equation de recherche : <code>', $this->equation, '</code></li>', "\n";
        echo '<li>Nombre de notices à modifier : <code>', $count, '</code></li>', "\n";
        echo '<li>Rechercher : <code>', var_export($search,true), '</code></li>', "\n";
        echo '<li>Remplacer par : <code>', var_export($replace,true), '</code></li>', "\n";
        echo '<li>Dans le(s) champ(s) : <code>', implode(', ', $fields), '</code></li>', "\n";
        echo '<li>Mots entiers uniquement : <code>', ($word ? 'oui' : 'non'), '</code></li>', "\n";
        echo '<li>Ignorer la casse des caractères : <code>', ($ignoreCase ? 'oui' : 'non'), '</code></li>', "\n";
        echo '<li>Expression régulière : <code>', ($regexp ? 'oui' : 'non'), '</code></li>', "\n";
        echo '</ul>', "\n";

        $count = 0;         // nombre de remplacements effectués par enregistrement
        $totalCount = 0;    // nombre total de remplacements effectués sur le sous-ensemble de notices

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
            if ($regexp || $word)
            {
                // expr reg ou alors chaîne avec 'Mot entier' sélectionné
                // dans ces deux-cas, on appellera pregReplace pour simplier

                // échappe le '~' éventuellement entré par l'utilisateur car on l'utilise comme délimiteur
                $search = str_replace('~', '\~', $search);

                if ($word)
                    $search = $search = '~\b' . $search . '\b~';
                else
                    $search = '~' . $search . '~';  // délimiteurs de l'expression régulière

                if ($ignoreCase)
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
//                    $this->selection->strReplace($fields, $search, $replace, $ignoreCase, $count, $callback);     // cf. Database.php
                    $this->selection->strReplace($fields, $search, $replace, $ignoreCase, $count);
                    $this->selection->saveRecord();
                    $totalCount += $count;
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
            array($this, $callback),
            array('count'=>$totalCount)
        );
     }

    /**
     * Exporte des notices à partir d'une équation de recherche passée en paramètre.
     *
     * Les formats d'export disponibles sont listés dans la clé <code><formats></code>
     * de la configuration.
     *
     * Première étape : affichage de la liste des formats disponibles et
     * sélection par l'utilisateur du type d'export à faire (envoi par email ou
     * affichage ou déchargement de fichier).
     *
     * Seconde étape : exécution du template correspondant au format d'export
     * choisi en indiquant le type mime correct.
     *
     * @param bool $calledFromPreExecute indique si l'action a été appelée
     * (<code>true</code>) ou non depuis la méthode {@link preExecute}.
     *
     * @return bool retourne true pour indiquer que l'export a été fait, false pour
     * afficher le formulaire d'export.
     */
    public function actionExport($calledFromPreExecute=false)
    {
        $error=null;

        // Détermine la ou les équations de recherche à exécuter
        $equations=Config::get('equation', $this->request->defaults('_equation','')->asArray()->ok());
        if (! $equations) throw new Exception('Aucune équation de recherche indiquée.');
        $equations=(array)$equations;

        // Détermine l'ordre de tri des réponses
        $sort=Config::get('sort', $this->request->get('_sort'));

        // Détermine le nom des fichiers à générer
        $filename=$this->request->get('filename');

        // Détermine s'il faut envoyer un e-mail
        if ($mail=$allowmail=(bool)Config::get('allowmail'))
            if(! $mail=(bool)Config::get('forcemail'))
                $mail=$this->request->bool('_mail')->unique()->defaults(false)->ok();

        // S'il faut envoyer un e-mail, détermine le destinataire, le sujet et le message de l'e-mail
        $to=$this->request->defaults('_to','')->unique()->ok();
        $subject=$this->request->defaults('_subject', (string)Config::get('mailsubject'))->unique()->ok();
        $message=$this->request->defaults('_message', (string)Config::get('mailbody'))->unique()->ok();

        if ($mail && !$to) $error[]='Veuillez indiquer l\'adresse du destinataire de l\'e-mail.';

        // Détermine s'il faut générer une archive au format zip
        if ($zip=$allowzip=(bool)Config::get('allowzip'))
            if(! $zip=$forcezip=(bool)Config::get('forcezip'))
                $zip=$this->request->bool('_zip')->unique()->defaults(false)->ok();

        // Si on a plusieurs fichiers et qu'on n'envoie pas un mail, force l'option zip
        if (count($equations)>1 && !$mail)
            $zip=true;

        // Si l'option zip est activée, vérifie qu'on a l'extension php requise
        if ($zip && ! class_exists('ZipArchive'))
            throw new Exception("La création de fichiers ZIP n'est pas possible sur ce serveur : l'extension PHP requise n'est pas disponible.");

        // Charge la liste des formats d'export disponibles
        if ($calledFromPreExecute)
            if (!$this->loadExportFormats())
                throw new Exception("Aucun format d'export n'est disponible");

        // Choix du format d'export
        $formats=Config::get('formats');
        if (count($formats)===1) // Un seul format est proposé, inutile de demander à l'utilisateur
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

        // Détermine s'il faut afficher le formulaire
        $showForm =     $error          // s'il y a une erreur
                    ||  ! $format       // ou qu'on n'a pas de format
                                        // ou que l'utilisateur n'a pas encore choisi parmi les options disponibles
                    ||  ($format && ($allowmail || ($allowzip && !$forcezip)) && !$this->request->get('confirm'));

        if ($showForm)
        {
            if ($calledFromPreExecute) return false;

            // Détermine le template à utiliser
            if (! $template=$this->getTemplate())
                throw new Exception('Le template à utiliser n\'a pas été indiqué');

            // Détermine le callback à utiliser
            $callback=$this->getCallback();

            // Détermine quel est le format par défaut pour cet utilisateur
            foreach(Config::get('formats') as $name=>$fmt)
            {
                if (Config::userGet('formats.'.$name.'.default'))
                {
                    $defaultFormat=$name;
                    break;
                }
            }

            // Exécute le template
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
        if (preg_match('~;\s?filename\s?=\s?(?:"([^"]+)"|([^ ;]+))~i', Utils::get($fmt['content-disposition']), $match))
            $basename=Utils::get($match[2], $match[1]);
        elseif ($template=Utils::get($fmt['template']))
            $basename=$template;

        if (stripos($basename, '%s')===false)
            $basename=Utils::setExtension($basename,'').'%s'.Utils::getExtension($basename);

        // Génère un nom de fichier unique pour chaque fichier en utilisant l'éventuel filename passé en query string
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

        // Détermine le nombre maximum de notices que l'utilisateur a le droit d'exporter
        $max=Config::userGet("formats.$format.max",10);

        // Ouvre la base de données
        $this->openDatabase();

        // Exécute toutes les recherches dans l'ordre
        $files=array();
        $counts=array();
        $filesizes=array();
        foreach($equations as $i=>$equation)
        {
            // Lance la recherche, si aucune réponse, erreur
            if (! $this->select($equation, $max, 1, $sort))
            {
                echo "Aucune réponse pour l'équation $equation<br />";
                continue;
            }

            $counts[$i]=$max===-1 ? $this->selection->count() : (min($max,$this->selection->count()));

            // Si l'utilisateur a demandé un envoi par mail ou un zip, démarre la capture
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
                    header('content-disposition: ' . sprintf($fmt['content-disposition'],Utils::setExtension($filenames[$i])));
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
                if (! $template=Config::userGet("formats.$format.template"))
                {
                    if ($mail or $zip) Utils::endCapture(); // sinon on ne "verra" pas l'erreur
                    throw new Exception("Le template à utiliser pour l'export en format $format n'a pas été indiqué");
                }

                // Détermine le callback à utiliser
                $callback=Config::userGet("formats.$format.callback");

                // Exécute le template
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
                    throw $e; // re génère l'exception
                }
            }

            // Pour un export classique, on a fini
            if (!($mail or $zip)) continue;

            // Termine la capture du fichier d'export généré et stocke le nom du fichier temporaire
            $files[$i]=Utils::endCapture();
            $filesizes[$i]=filesize($files[$i]);
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
                if (!$zipFile->addFile($files[$i], Utils::convertString($filenames[$i],'CP1252 to CP437')))
                    throw new Exception('Impossible de créer le fichier zip - 2');
                if (!$zipFile->setCommentIndex($i, Utils::convertString($equations[$i],'CP1252 to CP437')))
                    throw new Exception('Impossible de créer le fichier zip - 3');

                // Historiquement, le format ZIP utilise CP437
                // (source : annexe D de http://www.pkware.com/documents/casestudies/APPNOTE.TXT)

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
        require_once Runtime::$fabRoot . 'lib/SwiftMailer/Swift.php';
        require_once Runtime::$fabRoot . 'lib/SwiftMailer/Swift/Connection/SMTP.php';

        // Crée une nouvelle connexion Swift
        $swift = new Swift(new Swift_Connection_SMTP(ini_get('SMTP'))); // TODO: mettre dans la config de fab pour ne pas être obligé de changer php.ini

        $log = Swift_LogContainer::getLog();
        $log->setLogLevel(4); // 4 = tout est loggé, 0 = pas de log

        // Force swift à utiliser un cache disque pour minimiser la mémoire utilisée
        Swift_CacheFactory::setClassName("Swift_Cache_Disk");
        Swift_Cache_Disk::setSavePath(Utils::getTempDirectory());

        // Crée le message
        $email = new Swift_Message($subject);

        // Crée le corps du message
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
                    'subject'=>htmlentities($subject),  // Le message tapé par l'utilisateur dans le formulaire
                    'message'=>htmlentities($message),  // Le message tapé par l'utilisateur dans le formulaire
                    'filenames'=>$filenames,            // Les noms des fichiers joints
                    'equations'=>$equations,            // Les équations de recherche
                    'format'=>$fmt['label'],            // Le nom du format d'export
                    'description'=>$fmt['description'], // Description du format d'export
                    'counts'=>$counts,                  // Le nombre de notices de chacun des fichiers
                    'filesizes'=>$filesizes,            // La taille non compressée de chacun des fichiers
                    'zip'=>$zip                         // true si option zip

                )
            );
            $body=ob_get_clean();
            $mimeType='text/html'; // fixme: on ne devrait pas fixer en dur le type mime. Clé de config ?
        }

        $email->attach(new Swift_Message_Part($body, $mimeType));

        // Met les pièces attachées
        $swiftFiles=array(); // Grrr... Swift ne ferme pas les fichiers avant l'appel à destruct. Garde un handle dessus pour pouvoir appeller nous même $file->close();
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

        // HACK: ferme "à la main" les pièces jointes de swift, sinon le capture handler ne peut pas supprimer les fichiers temporaires
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
                        'subject'=>htmlentities($subject),      // Le message tapé par l'utilisateur dans le formulaire
                        'message'=>htmlentities($message),      // Le message tapé par l'utilisateur dans le formulaire
                        'filenames'=>$filenames,                // Les noms des fichiers joints
                        'equations'=>$equations,                // Les équations de recherche
                        'format'=>$fmt['label'],                // Le nom du format d'export
                        'description'=>$fmt['description'],     // Description du format d'export
                        'counts'=>$counts,                      // Le nombre de notices de chacun des fichiers
                        'filesizes'=>$filesizes,                // La taille non compressée de chacun des fichiers
                        'zip'=>$zip                             // true si option zip

                    )
                );
            }
            else
                echo '<p>Vos notices ont été envoyées par courriel.';
        }
        else
        {
            echo '<h1>Erreur</h1>';
            echo "<fieldset>Impossible d'envoyer l'e-mail à l'adresse <strong><code>$to</code></strong></fieldset>";
            if ($error)
                echo "<p>Erreur retournée par le serveur : <strong><code>$error</code></strong></p>";
            echo "<fieldset><legend>Log de la transaction</legend> <pre>";
            echo $log->dump(true);
            echo "</pre></fieldset>";
        }
        return true;
    }

    /**
     * Affiche le résultat d'une recherche dans une table des entrées (lookup) (pour Xapian uniquement).
     *
     * Recherche dans la table des entrées <code>$table</code> les valeurs qui
     * commencent par le terme <code>$value</code> indiqué (voir méthode
     * {@link XapianDatabase2#lookup lookup} de {@link XapianDatabase2}, et affiche
     * le résultat en utilisant le template retourné par la méthode {@link getTemplate()}
     * et le callback retourné par la méthode {@link getCallback()}.
     *
     * @param string $table le nom de la table des entrées.
     * @param string $value le terme recherché.
     * @param int $max le nombre maximum de valeurs à retourner (0=pas de limite).
     */
    function actionLookup($table, $value='', $max=10)
    {
        header('Content-type: text/html; charset=iso-8859-1');

        $max=$this->request->defaults('max', 25)->int()->min(0)->ok();

        // Ouvre la base
        $this->openDatabase();

        // Lance la recherche
        $terms=$this->selection->lookup($table, $value, $max, 0, true);

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
            array('search'=>$value, 'table'=>$table, 'terms'=>$terms)
        );
    }

    /**
     * Lance une réindexation complète de la base de données.
     *
     * Cette action se contente de rediriger l'utilisateur vers l'action
     * {@link DatabaseAdmin#actionReindex Reindex} de {@link DatabaseAdmin}.
     */
    public function actionReindex()
    {
        Runtime::redirect('/DatabaseAdmin/Reindex?database='.Config::get('database'));
    }
















    /**
     * Ouvre la base de données du module.
     *
     * La base à ouvrir est indiquée dans la clé <code><database></code> du fichier
     * de configuration du module.
     *
     * @param bool $readOnly indique si la base doit être ouverte en lecture seule
     * (valeur par défaut) ou en lecture/écriture.
     *
     * @return bool true si une recherche a été lancée et qu'on a au moins
     * une réponse, false sinon.
     */
    protected function openDatabase($readOnly=true)
    {
        $database=Config::get('database');
        // Le fichier de config du module indique la base à utiliser

        if (is_null($database))
            throw new Exception('La base de données à utiliser n\'a pas été indiquée dans le fichier de configuration du module');

        Timer::enter('Ouverture de la base '.$database);
        debug && Debug::log("Ouverture de la base '%s' en mode '%s'", $database, $readOnly ? 'lecture seule' : 'lecture/écriture');
        $this->selection=Database::open($database, $readOnly);
        Timer::leave();
    }

    /**
     * Lance une recherche en définissant les options de recherche et sélectionne
     * les notices correspondantes.
     *
     * Les valeurs des options de recherche sont définies en examinant dans l'ordre :
     * - le paramètre transmis à la méthode s'il est non null,
     * - le paramètre transmis à la {@link $request requête} s'il est non null, en
     * vérifiant le type,
     * - la valeur indiquée dans le fichier de configuration.
     *
     * @param string $equation l'équation de recherche.
     *
     * @param int|null $max le nombre maximum de notices à retourner, ou null
     * si on veut récupérer le nombre à partir de la {@link $request requête} ou
     * à partir de la clé <code><max></code> du fichier de configuration.
     *
     * @param int|null $start le numéro d'ordre de la notice sur laquelle se positionner
     * une fois la recherche effectuée, ou null si on veut récupérer le numéro à
     * partir de la {@link $request requête} ou à partir de la clé <code><start></code>
     * du fichier de configuration.
     *
     * @param string|null $sort l'ordre de tri des résultats ou null si on veut
     * récupérer l'ordre de tri à partir de la {@link $request requête} ou à partir
     * de la clé <code><sort></code> du fichier de configuration.
     *
     * @return bool true si au moins une notice a été trouvée, false s'il n'y
     * a aucune réponse.
     */
    protected function select($equation, $max=null, $start=null, $sort=null)
    {
        Timer::enter('Exécution de la requête '.$equation);
        /*
         * Valeurs par défaut des options de recherche
         *
         * Pour chaque paramètre, on prend dans l'ordre :
         * - le paramètre transmis si non null
         * - sinon, ce qu'il y a dans request, si non null, en vérifiant le type
         * - la valeur indiquée dans la config sinon
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
        );
        $result=$this->selection->search($equation, $options);
        Timer::leave();
        return $result;
    }

    /**
     * Affiche un message si une erreur s'est produite lors de la recherche.
     *
     * Le template à utiliser est indiqué dans la clé <code><errortemplate></code>
     * de la configuration de l'action {@link actionSearch() Search}.
     *
     * @param string $error le message d'erreur à afficher (passé à
     * <code>Template::run</code>) via la source de donnée <code>error</code>.
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
     * Affiche un message si aucune réponse n'est associée à la recherche.
     *
     * Le template à utiliser est indiqué dans la clé <code><noanswertemplate></code>
     * de la configuration de l'action {@link actionSearch() Search}.
     *
     * @param string $message le message à afficher (passé à <code>Template::run</code>)
     * via la source de donnée <code>message</code>.
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
            array('message'=>$message),
            $this->selection->record

            // On passe en paramètre la sélection en cours pour permettre
            // d'utiliser le même template dans un search (par exemple) et
            // dans l'erreur 'aucune réponse' (on a le cas pour ImportModule)
        );
    }

    /**
     * Construit l'équation qui sera utilisée pour lancer la recherche dans
     * la base.
     *
     * getEquation() construit une équation de recherche qui sera ensuite
     * combinée avec les filtres retournés par {@link getFilter()} pour
     * lancer la recherche.
     *
     * Par défaut, getEquation() combine en 'ET' les éléments suivants :
     *
     * - la ou le(s) équation(s) de recherche qui figure dans l'argument
     *   <code>_equation</code> de la {@link $request requête en cours},
     *
     * - tous les paramètres de la requête dont le nom correspond à un nom
     *   d'index ou d'alias de type probabiliste (ceux sui sont de type
     *   booléens sont retournés par getFilter).
     *
     * Si aucun des éléments ci-dessus ne retourne une équation de recherche,
     * getEquation() utilise la ou les équation(s) de recherche indiquée(s)
     * dans la clé <code><equation></code> de la configuration (des équations
     * par défaut différentes peuvent être indiquées selon les droits de
     * l'utilisateur, voir {@link Config::userGet()}).
     *
     * Les modules qui héritent de DatabaseModule peuvent surcharger cette
     * méthode pour changer le comportement par défaut.
     *
     * @return null|string l'équation de recherche obtenue ou null si aucune
     * équation ne peut être construite.
     */
    protected function getEquation()
    {
        return $this->getEquationPart('_equation', DatabaseSchema::INDEX_PROBABILISTIC, true);
    }

    /**
     * Construit l'équation qui sera utilisée comme filtre pour lancer la
     * recherche dans la base.
     *
     * Les filtres seront combinés à l'équation de recherche retournée par
     * {@link getEquation()} de telle sorte que seuls les enregistrements qui
     * passent les filtres soient pris en compte.
     *
     * La manière dont les filtres sont combinés à l'équation dépend du driver
     * de base de données utilisé (BisDatabase combine en 'SAUF', XapianDatabase
     * utilise l'opérateur xapian 'FILTER').
     *
     * Par défaut, getFilter() combine en 'ET' les éléments suivants :
     *
     * - la ou le(s) équation(s) de recherche qui figure dans l'argument
     *   <code>_filter</code> de la {@link $request requête en cours},
     *
     * - tous les paramètres de la requête dont le nom correspond à un nom
     *   d'index ou d'alias de type boolean (ceux sui sont de type
     *   probabiliste sont retournés par getEquation).
     *
     * Si aucun des éléments ci-dessus ne retourne une équation de recherche,
     * getFilter() utilise la ou les équation(s) filtres indiquée(s)
     * dans la clé <code><filter></code> de la configuration (des filtres
     * différents peuvent être indiquées selon les droits de l'utilisateur,
     * voir {@link Config::userGet()}).
     *
     * Les modules descendants peuvent surcharger cette méthode pour modifier
     * ce comportement par défaut.
     *
     * @return null|string l'équation de recherche obtenue ou null aucun filtre
     * n'a été spécifié.
     */
    protected function getFilter()
    {
        return $this->getEquationPart('_filter', DatabaseSchema::INDEX_BOOLEAN, false);
    }


    /**
     * Construit la partie probabiliste ou la partie filtre de l'équation de
     * recherche à exécuter en fonction de la requête et des arguments passés
     * en paramètres.
     *
     * getEquationPart() et la fonction interne utilisée par
     * {@link getEquation()} et {@link getFilter()} pour construire l'équation
     * de recherche à exécuter.
     *
     * La méthode combine en 'ET' les éléments suivants :
     *
     * - la ou le(s) équation(s) de recherche qui figure dans l'argument
     *   <code>$parameterName</code> de la {@link $request requête en cours},
     *
     * - tous les paramètres de la requête dont le nom correspond à un nom
     *   d'index ou d'alias ayant le type <code>$indexType</code> passé en
     *   paramètre.
     *
     * Si aucun des éléments ci-dessus ne retourne une équation de recherche,
     * getEquationPart() utilise la ou les équation(s) de recherche indiquée(s)
     * dans la clé <code>$parameterName</code> de la configuration (en
     * supprimant le underscore initial éventuel : '_equation' :
     * config::get('equation').
     *
     * Si aucune équation par défaut n'a été indiquée, la méthode retourne
     * null.
     *
     * Les modules qui héritent de DatabaseModule peuvent surcharger cette
     * méthode pour changer le comportement par défaut.
     *
     *
     * @param $parameterName le nom du paramètre à récupérer dans la query
     * string (soit '_equation', soit '_filter').
     *
     * @param $indexType le type d'index à prendre en compte
     * (DatabaseSchema::INDEX_PROBABILISTIC ouDatabaseSchema::INDEX_BOOLEAN).
     * Seuls les arguments correspondant à un index ou un alias de ce type seront
     * ajoutés à l'équation finale.
     *
     * @param boolean $configIsDefault indique si l'équation indiquée dans la
     * config est une valeur par défaut (elle n'est retournée que si aucune
     * équation n'a pu être construite) ou non (elle est systématiquement
     * ajoutée à l'équation de recherche).
     *
     * @return null|string l'équation de recherche obtenue ou null si aucune
     * équation ne peut être construite.
     */
    protected function getEquationPart($parameterName, $indexType, $configIsDefault)
    {
        // Supprime de la requête tous les paramètres qui sont vides
        $request=$this->request->copy()->clearNull();

        // Récupère les paramètres "_equation"
        $equations=$request->asArray($parameterName)->ok();

        // Ajoute tous les paramètres qui sont des index de type probabiliste
        $schema=$this->selection->getSchema();
        foreach($request->getParameters() as $name=>$value)
        {
            // Détermine s'il s'agit d'un index ou d'un alias
            $name=strtolower($name);
            if (isset($schema->indices[$name]))
                $index=$schema->indices[$name];
            elseif(isset($schema->aliases[$name]))
                $index=$schema->aliases[$name];
            else
                continue;

            // On ne prend en compte que les index de type probabiliste
            if ($index->_type !== $indexType) continue;

            // Combine en OU Les paramètres de même nom (e.g. plusieurs dates)
            if (is_array($value)) $value=implode(' OR ', (array)$value);
            $this->addBrackets($value);
            $equations[]=$name.'='.$value;
        }

        // Détermine l'équation par défaut qui figure dans la configuration
        $default=Config::userGet(ltrim($parameterName,'_'), null);

        // Si $configIsDefault est à false, on l'ajoute à l'équation
        if (! $configIsDefault && !is_null($default))
            $equations[]=$default;

        // Retourne le résultat
        switch (count($equations))
        {
            // Aucune équation : retourne l'équation par défaut
            case 0:
                return $default;

            // Une seule équation : retourne tel quel
            case 1:
                return array_pop($equations);

            // Plusieurs équations : combine en ET
            default:
                // Ajoute des parenthèses si nécessaire
                foreach($equations as & $equation)
                    $this->addBrackets($equation);

                // Combine en et
                return implode(' AND ', $equations);
        }
    }

    /**
     * Ajoute des parenthèses autour de l'équation passée au paramètre si c'est
     * nécessaire.
     *
     * La méthode considère que l'équation passée en paramètre est destinée à
     * être combinée en "ET" avec d'autres équations.
     *
     * Dans sa version actuelle, la méthode supprime de l'équation les blocs
     * parenthésés, les phrases et les articles et ajoute des parenthèses si
     * ce qui reste contient l'opérateur ou.
     *
     * Idéalement, il faudrait faire un traitement beaucoup plus compliqué, mais
     * ça revient quasiment à ré-écrire un query parser.
     *
     * Le traitement actuel est plus simple mais semble fonctionner.
     *
     * @param string $equation l'équation à tester.
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
     * Génère une barre de navigation affichant le nombre de réponses obtenues
     * et les liens suivant/précédent.
     *
     * @param string $prevLabel libellé à utiliser pour le lien "Précédent".
     * @param string $nextLabel libellé à utiliser pour le lien "Suivant".
     * @return string le code html de la barre de navigation.
     */
    public function getSimpleNav($prevLabel = '&lt;&lt; Précédent', $nextLabel = 'Suivant &gt;&gt;')
    {
        // Regarde ce qu'a donné la requête en cours
        $start=$this->selection->searchInfo('start');
        $max= $this->selection->searchInfo('max');
        $count=$this->selection->count();

        // Clone de la requête qui sera utilisé pour générer les liens
        $request=$this->request->copy();

        // Détermine le libellé à afficher
        if ($start==min($start+$max-1,$count))
            $h='Résultat ' . $start . ' sur '.$this->selection->count('environ %d'). ' ';
        else
            $h='Résultats ' . $start.' à '.min($start+$max-1,$count) . ' sur '.$this->selection->count('environ %d'). ' ';

        // Génère le lien "précédent"
        if ($start > 1)
        {
            $newStart=max(1,$start-$max);

            $prevUrl=Routing::linkFor($request->set('_start', $newStart));
            $h.='<a href="'.$prevUrl.'">'.$prevLabel.'</a>';
        }

        // Génère le lien "suivant"
        if ( ($newStart=$start+$max) <= $count)
        {
            $nextUrl=Routing::linkFor($request->set('_start', $newStart));
            if ($start > 1 && $h) $h.='&nbsp;|&nbsp;';
            $h.='<a href="'.$nextUrl.'">'.$nextLabel.'</a>';
        }

        // Retourne le résultat
        return '<span class="navbar">'.$h.'</span>';
    }

    /**
     * Génère une barre de navigation affichant le nombre de réponses obtenues
     * et permettant d'accéder directement aux pages de résultat.
     *
     * La barre de navigation présente les liens vers :
     * - la première page,
     * - la dernière page,
     * - la page précédente,
     * - la page suivante,
     * - <code>$links</code> pages de résultat.
     *
     * Voir {@link http://www.smashingmagazine.com/2007/11/16/pagination-gallery-examples-and-good-practices/}
     *
     * @param int $links le nombre maximum de liens générés sur la barre de navigation.
     * Par défaut, 9 liens sont affichés.
     * @param string $previousLabel libellé du lien "Précédent", "<" par défaut.
     * @param string $nextLabel libellé du lien "Suivant", ">" par défaut.
     * @param string $firstLabel libellé du lien "Première page", "«" par défaut.
     * @param string $lastLabel libellé du lien "Dernière page", "»" par défaut.
     */
    public function getNavigation($links = 9, $previousLabel = '‹', $nextLabel = '›', $firstLabel = '«', $lastLabel = '»')
    {
        /*
                                $max réponses par page
                            $links liens générés au maximum
                      +----------------------------------------+
            1   2   3 | 4   5   6   7  (8)  9   10  11  12  13 | 14  15 16
                      +-^---------------^-------------------^--+        ^
                        |               |                   |           |
                        $first          $current            $last       $maxlast
        */

        // Regarde ce qu'a donné la requête en cours
        $start=$this->selection->searchInfo('start');
        $max= $this->selection->searchInfo('max');
        $count=$this->selection->count();

        // Numéro de la page en cours
        $current = intval(($start - 1) / $max) + 1;

        // Numéro du plus grand lien qu'il est possible de générer
        $maxlast = intval(($count - 1) / $max) + 1;

        // "demi-fenêtre"
        $half=intval($links / 2);

        // Numéro du premier lien à générer
        $first=max(1, $current-$half);

        // Numéro du dernier lien à générer
        $last=$first+$links-1;

        // Ajustement des limites
        if ($last > $maxlast)
        {
            $last=$maxlast;
            $first=max(1,$last-$links+1);
        }

        // Requête utilisée pour générer les liens
        $request=Routing::linkFor(Runtime::$request->copy()->clearNull()->clear('_start'));
        $request.=(strpos($request,'?')===false ? '?' : '&') . '_start=';
        $request=htmlspecialchars($request);

//        echo '<div class="pager">';

        echo '<span class="label">';
        if ($start==min($start+$max-1,$count))
            echo 'Réponse ', $start, ' sur ', $this->selection->count('environ %d'), ' ';
        else
            echo 'Réponses ', $start, ' à ', min($start+$max-1, $count), ' sur ', $this->selection->count('environ %d'), ' ';
        echo '</span>';

        // Lien vers la première page
        if ($firstLabel)
        {
            if ($current > 1)
                echo '<a class="first" href="',$request, 1,'" title="première page">', $firstLabel, '</a>';
            else
                echo '<span class="first">', $firstLabel, '</span>';
        }

        // Lien vers la page précédente
        if ($previousLabel)
        {
            if ($current > 1)
                echo '<a class="previous" href="', $request, 1+($current-2)*$max,'" title="page précédente">', $previousLabel, '</a>';
            else
                echo '<span class="previous">', $previousLabel, '</span>';

        }

        // Lien vers les pages de la fenêtre
        for($i=$first; $i <= $last; $i++)
        {
            if ($i===$current)
            {
                echo '<span class="current">', $i, '</span>';
            }
            else
            {
                $title='Réponses '.(1+($i-1)*$max) . ' à ' . min($count, $i*$max);
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

        // Lien vers la dernière page
        if ($lastLabel)
        {
            if ($current < $maxlast)
                echo '<a class="last" href="', $request, 1+($maxlast-1)*$max,'" title="dernière page">', $lastLabel, '</a>';
            else
                echo '<span class="last">', $lastLabel, '</span>';
        }

//        echo '</div>';
    }

    /**
     * Callback pour l'action {@link actionSave() Save} autorisant la modification
     * de tous les champs d'un enregistrement.
     *
     * Par défaut, le callback de l'action {@link actionSave() Save} est à <code>none</code>.
     * Cette fonction est une facilité offerte à l'utilisateur pour lui éviter
     * d'avoir à écrire un callback à chaque fois : il suffit de créer un pseudo
     * module et, dans la clé <code><save.callback></code> de la configuration de ce
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
     * Retourne le template à utiliser pour l'action en cours ({@link $action}).
     *
     * La méthode retourne le nom du template indiqué dans la clé
     * <code><template></code> du fichier de configuration.
     *
     * Dans cette clé, vous pouvez indiquer soit le nom d'un template qui sera
     * utilisé dans tous les cas, soit un tableau qui va permettre d'indiquer
     * le template à utiliser en fonction des droits de l'utilisateur en cours.
     *
     * Dans ce cas, les clés du tableau indiquent le droit à avoir et le template
     * à utiliser. Le tableau est examiné dans l'ordre indiqué. A vous
     * d'organiser les clés pour que les droits les plus restrictifs
     * apparaissent en premier.
     *
     * Vous pouvez utiliser le pseudo droit <code><default></code> pour
     * indiquer le template à utiliser lorsque l'utilisateur ne dispose d'aucun des
     * droits indiqués.
     *
     * Exemple :
     * <code>
     *     <!-- Exemple 1 : Le template form.html sera toujours utilisé -->
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
     * cette méthode si un autre comportement est souhaité.
     *
     * @param string $key nom de la clé du fichier de configuration qui contient
     * le nom du template. La clé est par défaut <code><template></code>.
     * @return string|null le nom du template à utiliser ou null.
     */
    protected function getTemplate($key='template')
    {
        debug && Debug::log('%s : %s', $key, Config::userGet($key));
        if (! $template=Config::userGet($key))
            return null;

        if (file_exists($h=$this->path . $template)) // fixme : template relatif à BisDatabase, pas au module hérité (si seulement config). Utiliser le searchPath du module en cours
        {
            return $h;
        }
        return $template;
    }

    /**
     * Retourne le callback à utiliser pour l'action en cours ({@link $action}).
     *
     * getCallback() appelle la méthode {@link Config::userGet()} en passant la clé
     * <code>callback</code> en paramètre. Retourne null si l'option de configuration
     * <code><callback></code> n'est pas définie.
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
     * Exemple de générateur en format xml simple pour les formats d'export.
     *
     * @param array $format les caractéristiques du format d'export.
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
     * Retourne la valeur d'un élément à écrire dans le fichier de log.
     *
     * Nom d'items reconnus par cette méthode :
     * - tous les items définis dans la méthode {@link Module::getLogItem()}
     * - database : le nom de la base de données
     * - equation : l'équation de recherche
     * - count : le nombre de réponses de la recherche en cours
     * - fmt : le format d'affichage des réponses
     * - sort : le tri utilisé pour afficher les réponses
     * - max : le nombre maximum de réponses affichées sur une page
     * - start : le numéro d'ordre de la notice sur laquelle
     *
     * @param string $name le nom de l'item.
     *
     * @return string la valeur à écrire dans le fichier de log pour cet item.
     */
    protected function getLogItem($name)
    {
        switch($name)
        {
            // Base de données
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

    // ****************** méthodes privées ***************

    /**
     * Charge la liste des formats d'export disponibles dans la configuration en cours.
     *
     * Seuls les formats auxquels l'utilisateur a accès sont chargés (paramètre
     * <code><access></code> de chaque format).
     *
     * Les formats chargés sont ajoutés dans la configuration en cours dans la clé
     * <code><formats></code>.
     *
     * <code>Config::get('formats')</code> retourne la liste de tous les formats.
     *
     * <code>Config::get('formats.csv')</code> retourne les paramètres d'un format particulier.
     *
     * @return int le nombre de formats chargés.
     */
    private function loadExportFormats()
    {
        // Balaye la liste des formats d'export disponibles
        foreach((array) Config::get('formats') as $name=>$format)
        {
            // Ne garde que les formats auquel l'utilisateur a accès
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

        // Retourne le nombre de formats chargés
        return count(Config::get('formats'));
    }

    /**
     * Fonction utilitaire utilisée par les template rss : retourne le premier
     * champ renseigné ou la valeur par défaut sinon.
     *
     * @param mixed $fields un nom de champ ou un tableau contenant les noms
     * des champs à étudier.
     * @param string|null la valeur par défaut à retourner si tous les champs
     * indiqués sont vides.
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