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
        return Response::create('Html')->setTemplate
        (
            $this,
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
        timer && Timer::enter();

        // Ouvre la base de données
        $this->openDatabase();

        // Lance la requête
        if (! $this->select())
            return $this->showNoAnswer("La requête $this->equation n'a donné aucune réponse.");

        // Détermine le template à utiliser
        if (! $template=$this->getTemplate())
            throw new Exception('Le template à utiliser n\'a pas été indiqué');

        // Détermine le callback à utiliser
        $callback=$this->getCallback();

        // Génère la réponse
        $response = Response::create('Html')->setTemplate
        (
            $this,
            $template,
            array($this, $callback),
            $this->selection->record
        );

        timer && Timer::leave();
        return $response;
    }

    /**
     * Méthode appellée par fab après une fois que l'action demandée a été exécutée et que la
     * réponse a été envoyée au client.
     *
     * Traitement effectué :
     * 1. Mise à jour de l'historique des recherches : appelle updateSearchHistory si l'action
     *    demandée était actionSearch ou une pseudo-action descendante.
     */
    public function postExecute()
    {
        if ($this->method==='actionSearch')
            $this->updateSearchHistory();

        parent::postExecute();
    }

    /**
     * Méthode utilitaire utilisée par le template par défaut de l'action search
     * (format.autolist.html) pour déterminer quels sont les champs à afficher.
     *
     * Retourne un tableau contenant les champs indexés dont le nom ou le
     * libellé contiennent la chaine 'tit'.
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
     * Retourne le nombre maximum de recherches qui peuvent être stockées dans
     * l'historique des équations de recherche, ou zéro si l'historique est
     * désactivé.
     *
     * @return int la valeur retournée dépend de ce que contient la clé
     * <code><history></code> de la config :
     * - null : 0
     * - false : 0
     * - true : 10
     * - entier positif : cet entier
     * - entier négatif : 0
     *
     * Remarque :
     * La méthode retourne toujours zéro si la requête en cours est une requête
     * ajax.
     */
    public function getSearchHistoryLimit()
    {
        if (Utils::isAjax()) return 0;
        if (! $history=Config::userGet('history', false)) return 0;

        if ($history===true) return 10;

        if (!is_int($history)) return 0;
        if ($history > 0) return $history;
        return 0;
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
     */
    private function updateSearchHistory()
    {
        if (! $maxHistory=$this->getSearchHistoryLimit()) return;

        timer && Timer::enter('Mise à jour de l\'historique des recherches');

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

        timer && Timer::leave();
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

        return Response::create('Redirect');
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

        // Lance la recherche
        if (! $this->select())
            return $this->showNoAnswer("La requête $this->equation n'a donné aucune réponse.");

        // Détermine le template à utiliser
        if (! $template=$this->getTemplate())
            throw new Exception('Le template à utiliser n\'a pas été indiqué');

        // Détermine le callback à utiliser
        $callback=$this->getCallback();

        // Exécute le template
        return Response::create('Html')->setTemplate
        (
            $this,
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

        // Ouvre la base de données : permet au formulaire de consulter le schéma
        $this->openDatabase();

        // On exécute le template correspondant
        return Response::create('Html')->setTemplate
        (
            $this,
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

        // Lance la recherche
        if (! $this->select())
            return $this->showNoAnswer("La requête $this->equation n'a donné aucune réponse.");

        // Si sélection contient plusieurs enreg, erreur
        if ($this->selection->count() > 1)
            return $this->showError('Vous ne pouvez pas éditer plusieurs enregistrements à la fois.');

        // Détermine le template à utiliser
        if (! $template=$this->getTemplate())
            throw new Exception('Le template à utiliser n\'a pas été indiqué');

        // Détermine le callback à utiliser
        $callback=$this->getCallback();

        return Response::create('Html')->setTemplate
        (
            $this,
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

        // Lance la recherche
        if (! $this->select())
            return $this->showNoAnswer("La requête $this->equation n'a donné aucune réponse.");

        // Si la sélection contient plusieurs enreg, erreur
        if ($this->selection->count() > 1)
            return $this->showError('Vous ne pouvez pas éditer plusieurs enregistrements à la fois.');

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
        return Response::create('Html')->setTemplate
        (
            $this,
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
            return Response::create('Redirect', 'Show?REF='.$REF);

        return Response::create('Html')->setTemplate
        (
            $this,
            $template,
            array('equationAnswers'=>'NA'),
            $this->selection->record,
            array('selection',$this->selection)
        );
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

        // Lance la recherche
        if (! $this->select(null, -1) )
            return $this->showError("Aucune réponse. Equation : $this->equation");

        // Demande confirmation si ce n'est pas déjà fait
        $confirm=$this->request->int('confirm')->ok();
        $confirm=time()-$confirm;
        if($confirm<0 || $confirm>Config::get('timetoconfirm',30))  // laisse timetoconfirm secondes à l'utilisateur pour confirmer
            return Response::create('Redirect', $this->request->setAction('confirmDelete'));

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

            return Response::create('Redirect', '/TaskManager/TaskStatus?id='.$id);
        }

        // Supprime toutes les notices de la sélection
        foreach($this->selection as $record)
            $this->selection->deleteRecord();

        // Exécute le template
        return Response::create('Html')->setTemplate
        (
            $this,
            $this->getTemplate(),
            array($this, $this->getCallback())
        );
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

        // Lance la recherche
        if (! $this->select(null, -1) )
            return $this->showError("Aucune réponse. Equation : $this->equation");

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
            return $this->showError("Aucune réponse. Equation : $this->equation");

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

            return Response::create('Redirect', '/TaskManager/TaskStatus?id='.$id);
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
     * @return bool retourne true pour indiquer que l'export a été fait, false pour
     * afficher le formulaire d'export.
     */
    public function actionExport()
    {
        $error=null;

        // Détermine la ou les équations de recherche à exécuter
        $equations=Config::get('equation', $this->request->defaults('_equation','')->asArray()->ok());
        if (! $equations) throw new Exception('Aucune équation de recherche indiquée.');
        $equations=(array)$equations;

        // Détermine l'ordre de tri des réponses
        $sort=$this->request->get('_sort', Config::get('sort', $this->request->get('_sort')));

        // Détermine l'ordre de tri des réponses
        $start=$this->request->get('_start',1);

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

        $confirm = $this->request->get('confirm');
        if ($mail && !$to && $confirm) $error[]='Veuillez indiquer l\'adresse du destinataire de l\'e-mail.';

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
            {
                if(is_null($fmt=Config::get("formats.$format")))
                    throw new Exception("Format d'export incorrect");
            }
            elseif($confirm)
            {
                $error[]='Veuillez choisir le format à utiliser.';
            }
        }

        // Détermine s'il faut afficher le formulaire
        $showForm =     $error          // s'il y a une erreur
                    ||  ! $format       // ou qu'on n'a pas de format
                                        // ou que l'utilisateur n'a pas encore choisi parmi les options disponibles
                    ||  ($format && ($allowmail || ($allowzip && !$forcezip)) && !$confirm);

        if ($showForm)
        {
            // Détermine le template à utiliser
            if (! $template=$this->getTemplate())
                throw new Exception('Le template à utiliser n\'a pas été indiqué');

            // Détermine le callback à utiliser
            $callback=$this->getCallback();

            // Détermine quel est le format par défaut pour cet utilisateur
            $defaultFormat = Config::userGet('defaultFormat');

            // Exécute le template
            return Response::create('html')->setTemplate
            (
                $this,
                $template,
                array($this, $callback),
                array
                (
                    'error'=>$error,
                    'equations'=>$equations,
                    // 'sort'=>$sort,
                    // 'filename'=>$filename,
                    'format'=>$format ? $format : $defaultFormat,
                    'zip'=>$zip,
                    'mail'=>$mail,
                    'to'=>$to,
                    'subject'=>$subject,
                    'message'=>$message,
                )
            );
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
        $max=$this->request
                ->defaults('_max', $max)
                ->unique()
                ->int()
                ->min(1)
                ->max($max)
                ->ok();

        // Ouvre la base de données
        $this->openDatabase();

        // Exécute toutes les recherches dans l'ordre
        $files=array();
        $counts=array();
        $filesizes=array();
        $nothing = true;
        foreach($equations as $i=>$equation)
        {
            $response = new Response();

            // Lance la recherche, si aucune réponse, erreur
            if (! $this->select($equation, $max, $start, $sort))
            {
                $response->appendContent("<p>Aucune réponse pour l'équation $equation</p>");
                $counts[$i] = 0;
                $filesizes[$i] = 0;
                continue;
            }

            $nothing = false;
            $counts[$i]=$max===-1 ? $this->selection->count() : (min($max,$this->selection->count()));

            // Définit les entêtes http du fichier généré
            if (isset($fmt['content-type']))
                $response->setHeader('Content-Type', $fmt['content-type']);

            if (isset($fmt['content-disposition']))
                $response->setHeader('Content-Disposition', sprintf($fmt['content-disposition'],Utils::setExtension($filenames[$i])));

            // Si le format utilise un générateur, on utilise un template spécifique qui se
            // contente d'appeller la méthode indiquée comme générateur.
            if (isset($fmt['generator']))
                $template = 'exportGenerator.txt';

            // Sinon, on utilise le template indiqué pour le format
            elseif (! $template=Config::userGet("formats.$format.template"))
                throw new Exception("Le template à utiliser pour l'export en format $format n'a pas été indiqué");

            // Détermine le callback à utiliser
//            $callback=Config::userGet("formats.$format.callback"); plus de callbacks. ça pose pb ?

            // Exécute le template
            $response->setTemplate
            (
                $this,
                $template,
//                array($this, $callback),
                array('format'=>$format),
                array('fmt'=>$fmt),
                $this->selection->record
            );

            // Pour un export classique, on a fini
            if (!$mail && !$zip) return $response;

            // Termine la capture du fichier d'export généré et stocke le nom du fichier temporaire
            $files[$i] = $response->render(); // Utils::endCapture()
            $filesizes[$i] = strlen($files[$i]); // FIXME. filesize($files[$i]);

            // remarque : on est obligé de faire le rendu dans la boucle des équations sinon,
            // lorsqu'on a plusieurs équations, la sélection aura changé au moment ou on essaiera
            // de le faire
            // todo: faire le rendu en mémoire ou dans un fichier temporaire ?
        }

        if ($nothing)
            throw new Exception("Aucune notice sélectionnée, il n'y a rien à exporter.");

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
            foreach($files as $i=>$content)
            {
                if (!$zipFile->addFromString(Utils::convertString($filenames[$i],'CP1252 to CP437'), $content))
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
                return Response::create('File')
                    ->setHeader('Content-Type', 'application/zip') // type mime 'officiel', source : http://en.wikipedia.org/wiki/ZIP_(file_format)
                    ->setHeader('Content-Disposition', 'attachment; filename="export.zip"')
                    ->setContent($zipPath);
        }

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

        // Transforme les éventuels liens relatifs présents dans l'e-mail en liens absolus
        $body=preg_replace('~( (?:action|href|src)\s*=\s*")/(.*?)(")~', '$1' . Utils::getHost() . Runtime::$realHome . '$2$3', $body);

        // Ajoute le corps du message dans le mail
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
            foreach($files as $i=>$content)
            {
                //$swiftFiles[$i]=new Swift_File($path);
                $mimeType=strtok($fmt['content-type'],';');
                $email->attach(new Swift_Message_Attachment($content/* $swiftFiles[$i] */, $filenames[$i], $mimeType));
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
                return Response::create('Html')->setTemplate
                (
                    $this,
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
                return Response::create('Html')
                    ->setContent('<p>Vos notices ont été envoyées par courriel.');
        }
        else
        {
            return Response::create('Html')->setContent
            (
                sprintf
                (
                    '<h1>Erreur</h1>' .
                    '<fieldset>Impossible d\'envoyer l\'e-mail à l\'adresse <strong><code>%s</code></strong></fieldset>'.
                    '<p>Erreur retournée par le serveur : <strong><code>%s</code></strong></p>' .
                    '<fieldset><legend>Log de la transaction</legend> <pre>%s</pre></fieldset>',
                    $to,
                    $error,
                    $log->dump(true)
                )
            );
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
    function actionLookup($table, $value='', $max=10, $sort=false)
    {
        $max=$this->request->defaults('max', 10)->int()->min(0)->ok();

        // Ouvre la base
        $this->openDatabase();

        // Lance la recherche
        $terms = $this->selection->lookup($table, $value, $max, $sort);

        // Détermine le template à utiliser
        if (! $template = $this->getTemplate())
            throw new Exception('Le template à utiliser n\'a pas été indiqué');

        // Détermine le callback à utiliser
        $callback = $this->getCallback();

        // Exécute le template
        return Response::create('Html')->setTemplate
        (
            $this,
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
        return Response::create('Redirect', '/DatabaseAdmin/Reindex?database='.Config::get('database'));
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

        timer && Timer::enter('Ouverture de la base '.$database);
        debug && Debug::log("Ouverture de la base '%s' en mode '%s'", $database, $readOnly ? 'lecture seule' : 'lecture/écriture');
        $this->selection=Database::open($database, $readOnly);
        timer && Timer::leave();
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
    public function select($equation=null, $max=null, $start=null, $sort=null)
    {
        timer && Timer::enter('Exécution de la requête '.$equation);

        // Equation de recherche à exécuter
        if (is_null($equation))
            $equation=$this->getEquation();
        elseif ($equation==='')
            $equation=null;

        // Valeur de l'option "filter" qui sera transmise à search()
        $filter=$this->getFilter();

        /*
         * Détermine les options de recherche en testant dans l'ordre :
         * - s'il s'agit d'un paramètre ou d'une variable locale existante de notre fonction
         * - ce qu'il y a dans l'objet request,
         * - ce qu'il y a dans la config.
         */
        $options=$this->selection->getDefaultOptions();
        foreach ($options as $name => &$value)
        {
            $value = isset($$name) ? $$name
                : $value=$this->request->get('_' . $name, Config::userGet($name, $value));
        }

        // L'option 'auto' est gérée à part : on passe l'objet request
        $options['auto']=$this->request->getParameters(); // ->copy() ?

        // L'option boost est gérée à part : on récupère le nom du boost mais
        // ce qu'on doit passer à search(), c'est l'équation de boost.
        if ($options['boost']=$this->request->get('_boost', Config::get('boost.default', null)))
            $options['boost']=Config::get('boost.'.$options['boost']);

        $result=$this->selection->search($equation, $options);

        timer && Timer::leave();
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
            return Response::create('Html')->setContent
            (
                $error ? $error : 'Une erreur est survenue pendant le traitement de la requête'
            );

        // Détermine le callback à utiliser
        $callback=$this->getCallback();

        // Exécute le template
        return Response::create('Html')->setTemplate
        (
            $this,
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
            return Response::create('Html')->setContent
            (
                $message ? $message : 'La requête n\'a retourné aucune réponse'
            );

        // Détermine le callback à utiliser
        $callback=$this->getCallback();

        // Exécute le template
        return Response::create('Html')->setTemplate
        (
            $this,
            $template,
            array('message'=>$message),
            array($this, $callback),
            $this->selection->record

            // On passe en paramètre la sélection en cours pour permettre
            // d'utiliser le même template dans un search (par exemple) et
            // dans l'erreur 'aucune réponse' (on a le cas pour ImportModule)
        );
    }

    /**
     * Retourne l'équation qui sera utilisée pour lancer la recherche dans
     * la base.
     *
     * getEquation() se contente de retourner la ou les équations de recherche
     * qui figurent dans le paramêtre "_equation" de la requête en cours.
     *
     * Les modules qui héritent de DatabaseModule peuvent surcharger cette
     * méthode pour changer le comportement par défaut.
     *
     * @return null|string|array la ou les équations de recherche obtenues ou
     * null si la requête en cours ne contient pas de paramètre "_equation".
     */
    protected function getEquation()
    {
        return $this->request->_equation;
    }

    /**
     * Construit l'équation qui sera utilisée comme filtre pour lancer la
     * recherche dans la base.
     *
     * getFilter() se contente de retourner la ou les équations de recherche
     * qui figurent dans le paramêtre "_filter" de la requête en cours.
     *
     * Les modules qui héritent de DatabaseModule peuvent surcharger cette
     * méthode pour changer le comportement par défaut.
     *
     * @return null|string|array la ou les équations de recherche obtenues ou
     * null si la requête en cours ne contient pas de paramètre "_filter".
     */
    protected function getFilter()
    {
        return $this->request->_filter;
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
    public function getNavigation($links = 9, $previousLabel = '‹', $nextLabel = '›', $firstLabel = '«', $lastLabel = '»', $addLabel=true)
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

        if ($addLabel)
        {
            echo '<span class="label">';
            if ($start==min($start+$max-1,$count))
                echo 'Réponse ', $start, ' sur ', $this->selection->count('environ %d'), ' ';
            else
                echo 'Réponses ', $start, ' à ', min($start+$max-1, $count), ' sur ', $this->selection->count('environ %d'), ' ';
            echo '</span>';
        }

        // Lien vers la première page
        if ($firstLabel)
        {
            if ($current > 1)
                echo ' <a class="first" href="',$request, 1,'" title="Première page">', $firstLabel, '</a> ';
            else
                echo ' <span class="first">', $firstLabel, '</span> ';
        }

        // Lien vers la page précédente
        if ($previousLabel)
        {
            if ($current > 1)
                echo ' <a class="previous" href="', $request, 1+($current-2)*$max,'" title="Page précédente">', $previousLabel, '</a> ';
            else
                echo ' <span class="previous">', $previousLabel, '</span> ';

        }

        if ($links) echo '<span class="number">';

        // Lien vers les pages de la fenêtre
        for($i=$first; $i <= $last; $i++)
        {
            if ($i===$current)
            {
                echo ' <span class="current">', $i, '</span> ';
            }
            else
            {
                $title='Réponses '.(1+($i-1)*$max) . ' à ' . min($count, $i*$max);
                echo ' <a href="', $request, 1+($i-1)*$max,'" title="', $title, '">', $i, '</a> ';
            }
        }
        if ($links) echo '</span>';

        // Lien vers la page suivante
        if ($nextLabel)
        {
            if ($current < $maxlast)
                echo ' <a class="next" href="', $request, 1+($current)*$max,'" title="Page suivante">', $nextLabel, '</a> ';
            else
                echo ' <span class="next">', $nextLabel, '</span> ';

        }

        // Lien vers la dernière page
        if ($lastLabel)
        {
            if ($current < $maxlast)
                echo ' <a class="last" href="', $request, 1+($maxlast-1)*$max,'" title="Dernière page">', $lastLabel, '</a> ';
            else
                echo ' <span class="last">', $lastLabel, '</span> ';
        }
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
        echo '<','?xml version="1.0" encoding="iso-8859-1"?','>', "\n";
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
            case 'equation': return $this->selection->searchInfo('equation');
            case 'count':    return is_null($this->selection) ? '' : $this->selection->count();
            case 'fmt':      return $this->request->get('_fmt');
            case 'sort':     return $this->selection->searchInfo('sortorder');
            case 'max':      return $this->selection->searchInfo('max');
            case 'start':    return $this->selection->searchInfo('start');

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
            if ($name==='default')
            {
                Config::set('defaultFormat', $format);
                Config::clear('formats.default');
                break;
            }

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

                if (! isset($format['description']))
                    Config::set("formats.$name.description", '');

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