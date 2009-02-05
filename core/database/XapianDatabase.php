<?php
/**
 * @package     fab
 * @subpackage  database
 * @author      Daniel Ménard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id$
 */

/**
 * Un driver de base de données pour fab utilisant une base Xapian pour
 * l'indexation et le stockage des données
 *
 * @package     fab
 * @subpackage  database
 */
class XapianDatabaseDriver extends Database
{
    /**
     * Le schéma de la base de données (cf {@link getSchema()}).
     *
     * @var DatabaseSchema
     */
    private $schema=null;


    /**
     * Tableau interne indiquant, pour chaque champ de type 'AutoNumber' le nom
     * de la clé metadata utilisée pour stocker le dernier numéro utilisé.
     *
     * Les clés du tableau sont les noms (minu sans accents) des champs de type
     * AutoNumber. La valeur est une chaine de la forme 'fab_autonumber_ID' ou
     * 'ID' est l'identifiant du champ.
     *
     * Ce tableau est initialisé dans InitDatabase() et n'est utilisé que par
     * saveRecord()
     *
     * @var {array(string)}
     */
    private $autoNumberFields=array();


    /**
     * Permet un accès à la valeur d'un champ dont on connaît l'id
     *
     * Pour chaque champ (name,id), fieldById[id] contient une référence
     * vers fields[name] (ie modifier fields[i] ou fieldsById[i] changent la
     * même variable)
     *
     * @var Array
     */
    private $fieldById=array();


    /**
     * L'objet XapianDatabase retourné par xapian après ouverture ou création
     * de la base.
     *
     * @var XapianDatabase
     */
    private $xapianDatabase=null;


    /**
     * L'objet XapianDocument contenant les données de l'enregistrement en
     * cours ou null s'il n'y a pas d'enregistrement courant.
     *
     * @var XapianDocument
     */
    private $xapianDocument=null;


    /**
     * Un flag indiquant si on est en train de modifier l'enregistrement en
     * cours ou non  :
     *
     * - 0 : l'enregistrement courant n'est pas en cours d'édition
     *
     * - 1 : un nouvel enregistrement est en cours de création
     * ({@link addRecord()} a été appellée)
     *
     * - 2 : l'enregistrement courant est en cours de modification
     * ({@link editRecord()} a été appellée)
     *
     * @var int
     */
    private $editMode=0;


    /**
     * Un tableau contenant la valeur de chacun des champs de l'enregistrement
     * en cours.
     *
     * Ce tableau est passé à l'objet {@link XapianDatabaseRecord} que l'on crée
     * lors de l'ouverture de la base.
     *
     * @var Array
     */
    private $fields=array();


    /**
     * L'objet XapianEnquire représentant l'environnement de recherche.
     *
     * Vaut null tant que {@link search()} n'a pas été appellée.
     *
     * @var XapianEnquire
     */
    private $xapianEnquire=null;


    /**
     * L'objet XapianQueryParser utilisé pour analyser les équations de recherche.
     *
     * Initialisé par {@link setupSearch()} et utilisé uniquement dans
     * {@link search()}
     *
     * @var XapianQueryParser
     */
    private $xapianQueryParser=null;


    /**
     * L'objet XapianMultiValueSorter utilisé pour réaliser les tris multivalués.
     *
     * Initialisé par {@link setSortOrder()}.
     *
     * @var XapianMultiValueSorter
     */
    private $xapianSorter=null;

    /**
     * L'objet XapianMSet contenant les résultats de la recherche.
     *
     * Vaut null tant que {@link search()} n'a pas été appellée.
     *
     * @var XapianMSet
     */
    private $xapianMSet=null;

    /**
     * L'objet XapianMSetIterator permettant de parcourir les réponses obtenues
     *
     * Vaut null tant que {@link search()} n'a pas été appellée.
     *
     * @var XapianMSetIterator
     */
    private $xapianMSetIterator=null;

    /**
     * L'équation de recherche en cours.
     *
     * @var string
     */
    private $equation='';

    /**
     * Le filtre en cours.
     * @var string
     */
    private $filter='';

    /**
     * L'objet XapianQuery contenant l'équation de recherche indiquée par
     * l'utilisateur (sans les filtres éventuels appliqués).
     *
     * Vaut null tant que {@link search()} n'a pas été appellée.
     *
     * Utilisé par {@link getQueryTerms()} pour retourner la liste des termes
     * composant la requête
     *
     * @var XapianQuery
     */
    private $xapianQuery=null;

    /**
     * L'objet XapianFilter contient la requête correspondant aux filtres
     * appliqués à la recherche
     *
     * Vaut null tant que {@link search()} n'a pas été appellée.
     * Vaut null si aucun filtre n'a été spécifié.
     *
     * @var XapianQuery
     */
    private $xapianFilter=null;

    /**
     * Libellé de l'ordre de tri utilisé lors de la recherche.
     *
     * Si plusieurs critères de tri ont été indiqués lors de la requête,
     * le libellé obtenu est une chaine listant toutes les clés (séparées
     * par des espaces).
     *
     * Exemples :
     * - 'type', 'date-', '%', '+', '-' pour une clé de tri unique
     * - 'type date-', 'date- %' pour une clé de tri composite
     *
     * @var string
     */
    private $sortOrder='';

    /**
     * Tableau contenant les numéros des slots qui contiennent les valeurs
     * composant l'ordre de tri en cours.
     *
     * @var null|array
     */
    private $sortKey=array();


    /**
     * Opérateur par défaut utilisé par le queryparser
     *
     * @var null|int
     */
    private $defaultOp=null;

    /**
     * Indique si les opérateurs booléens (and, or, et, ou...) sont reconnus
     * comme tels quelle que soit leur casse ou s'il ne doivent être reconnus
     * que lorsqu'il sont en majuscules.
     *
     * @var bool
     */
    private $opAnyCase=true;

    /**
     * Indique le nom de l'index "global" par défaut, c'est-à-dire le nom de
     * l'index ou de l'alias qui sera utilisé si aucun nom de champ n'est
     * indiqué dans la requête de l'utilisateur.
     *
     * @var null|string
     */
    private $defaultIndex=null;

    /**
     * Indique le numéro d'ordre de la première réponse retournée par la
     * recherche en cours.
     *
     * Initialisé par {@link search()} et utilisé par {@link searchInfo()}.
     *
     * @var int
     */
    private $start=0;


    /**
     * Indique le nombre maximum de réponses demandées pour la recherche en
     * cours.
     *
     * Initialisé par {@link search()} et utilisé par {@link searchInfo()}.
     *
     * @var int
     */
    private $max=-1;

    /**
     * Une estimation du nombre de réponses obtenues pour la recherche en cours.
     *
     * @var int
     */
    private $count=0;

    /**
     * La version corrigée par le correcteur orthographique de xapian de la
     * requête en cours.
     *
     * @var string
     */
    private $correctedEquation='';

    /**
     * MatchingSpy employé pour créer les facettes de la recherche
     *
     * Expérimental (branche MatchSpy de Xapian), cf search().
     *
     * @var XapianMatchDecider
     */
    private $spy=null;

    /**
     * Retourne le schéma de la base de données
     *
     * @return DatabaseSchema
     */
    public function getSchema()
    {
        return $this->schema;
    }

    // *************************************************************************
    // ***************** Création et ouverture de la base **********************
    // *************************************************************************

    /**
     * Crée une nouvelle base xapian
     *
     * @param string $path le path de la base à créer
     * @param DatabaseSchema $schema le schéma de la base à créer
     * @param array $options options éventuelle, non utilisé
     */
    protected function doCreate($path, /* DS DatabaseSchema */ $schema, $options=null)
    {
        /* DS A ENLEVER */
        // Vérifie que le schéma de la base de données est correcte
        if (true !== $t=$schema->validate())
            throw new Exception('Le schéma passé en paramètre contient des erreurs : ' . implode('<br />', $t));

        // Compile le schéma
        $schema->compile();

        // Crée la base xapian
        $this->xapianDatabase=new XapianWritableDatabase($path, Xapian::DB_CREATE_OR_OVERWRITE); // todo: remettre à DB_CREATE
//        $this->xapianDatabase=Xapian::chert_open($path,Xapian::DB_CREATE_OR_OVERWRITE,8192);

        // Enregistre le schema dans la base
        $this->xapianDatabase->set_metadata('schema', $schema->toXml());
        $this->xapianDatabase->set_metadata('schema_object', serialize($schema));

        // Initialise les propriétés de l'objet
        $this->schema=$schema;
        $this->initDatabase(true);
    }

    /**
     * Modifie la structure d'une base de données en lui appliquant le
     * schéma passé en paramêtre.
     *
     * La fonction se contente d'enregistrer le nouveau schéma dans
     * la base : selon les modifications apportées, il peut être nécessaire
     * ensuite de lancer une réindexation complète (par exemple pour créer les
     * nouveaux index ou pour purger les champs qui ont été supprimés).
     *
     * @param DatabaseSchema $newSchema le nouveau schéma de la base.
     */
    public function setSchema(DatabaseSchema $schema)
    {
        if (! $this->xapianDatabase instanceOf XapianWritableDatabase)
            throw new LogicException('Impossible de modifier le schéma d\'une base ouverte en lecture seule.');

        // Vérifie que le schéma de la base de données est correct
        if (true !== $t=$schema->validate())
            throw new Exception('Le schéma passé en paramètre contient des erreurs : ' . implode('<br />', $t));

        // Compile le schéma
        $schema->compile();

        // Enregistre le schéma dans la base
        $this->xapianDatabase->set_metadata('schema', $schema->toXml());
        $this->xapianDatabase->set_metadata('schema_object', serialize($schema));

        // Initialise les propriétés de l'objet
        $this->schema=$schema;
        $this->initDatabase(true);
    }


    /**
     * Ouvre une base Xapian
     *
     * @param string $path le path de la base à ouvrir.
     * @param bool $readOnly true pour ouvrir la base en lecture seule, false
     * pour l'ouvrir en mode lexture/écriture.
     */
    protected function doOpen($path, $readOnly=true)
    {
        // Ouverture de la base xapian en lecture
        if ($readOnly)
        {
            $this->xapianDatabase=new XapianDatabase($path);
        }

        // Ouverture de la base xapian en écriture
        else
        {
            $starttime=microtime(true);
            $maxtries=100;
            for($i=1; ; $i++)
            {
                try
                {
//                    echo "tentative d'ouverture de la base...<br />\n";
                    $this->xapianDatabase=new XapianWritableDatabase($path, Xapian::DB_OPEN);
                }
                catch (Exception $e)
                {
                    // comme l'exception DatabaseLockError de xapian n'est pas mappée en php
                    // on teste le début du message d'erreur pour déterminer le type de l'exception
                    if (strpos($e->getMessage(), 'DatabaseLockError:')===0)
                    {
//                        echo 'la base est verrouillée, essais effectués : ', $i, "<br />\n";

                        // Si on a fait plus de maxtries essais, on abandonne
                        if ($i>$maxtries) throw $e;

                        // Sinon, on attend un peu et on refait un essai
                        $wait=rand(1,9) * 10000;
//                        echo 'attente de ', $wait/10000, ' secondes<br />', "\n";
                        usleep($wait); // attend de 0.01 à 0.09 secondes
                        continue;
                    }

                    // Ce n'est pas une exception de type DatabaseLockError, on la propage
                    throw $e;
                }

                // on a réussi à ouvrir la base
                break;
            }
//            echo 'Base ouverte en écriture au bout de ', $i, ' essai(s). Temps total : ', (microtime(true)-$starttime), ' sec.<br />', "\n";
        }

        // Charge le schéma de la base
        $this->schema=unserialize($this->xapianDatabase->get_metadata('schema_object'));
        if (! $this->schema instanceof DatabaseSchema)
            throw new Exception("Impossible d'ouvrir la base, schéma non géré");

        // Initialise les propriétés de l'objet
        $this->initDatabase($readOnly);
    }


    /**
     * Initialise les propriétés de la base
     *
     * @param bool $readOnly
     */
    private function initDatabase($readOnly=true)
    {
        // Crée le tableau qui contiendra la valeur des champs
        $this->fields=array_fill_keys(array_keys($this->schema->fields), null);

        // Crée l'objet DatabaseRecord
        $this->record=new XapianDatabaseRecord($this->fields, $this->schema);

        foreach($this->schema->fields as $name=>$field)
            $this->fieldById[$field->_id]=& $this->fields[$name];

        foreach($this->schema->indices as $name=>&$index) // fixme:
            $this->indexById[$index->_id]=& $index;

        foreach($this->schema->lookuptables as $name=>&$lookuptable) // fixme:
            $this->lookuptableById[$lookuptable->_id]=& $lookuptable;

        // Les propriétés qui suivent ne sont initialisées que pour une base en lecture/écriture
//        if ($readOnly) return;

        // Mots vides de la base
        $this->schema->_stopwords=array_flip(Utils::tokenize($this->schema->stopwords));

        // Crée la liste des champs de type AutoNumber + mots-vides des champs
        foreach($this->schema->fields as $name=>$field)
        {
            // Champs autonumber
            if ($field->_type === DatabaseSchema::FIELD_AUTONUMBER)
                $this->autoNumberFields[$name]='fab_autonumber_'.$field->_id;

            // Mots vides du champ
            if ($field->defaultstopwords)
            {
                if ($field->stopwords==='')
                    $field->_stopwords=$this->schema->_stopwords;
                else
                    $field->_stopwords=array_flip(Utils::tokenize($field->stopwords.' '.$this->schema->stopwords));
            }
            else
            {
                if ($field->stopwords==='')
                    $field->_stopwords=array();
                else
                    $field->_stopwords=array_flip(Utils::tokenize($field->stopwords));
            }
        }
    }


    // *************************************************************************
    // ********* Ajout/modification/suppression d'enregistrements **************
    // *************************************************************************


    /**
     * Initialise la création d'un nouvel enregistrement
     *
     * L'enregistrement ne sera effectivement créé que lorsque {@link update()}
     * sera appellé.
     *
     * @throws DatabaseReadOnlyException si la base est ouverte en lecture seule
     */
    public function addRecord()
    {
        // Vérifie que la base n'est pas en lecture seule
        if (! $this->xapianDatabase instanceof XapianWritableDatabase)
            throw new DatabaseReadOnlyException();

        // Réinitialise tous les champs à leur valeur par défaut
        foreach($this->fields as $name=>&$value)
            $value=null;

        // Réinitialise le document xapian en cours
        $this->xapianDocument=new XapianDocument();

        // Mémorise qu'on a une édition en cours
        $this->editMode=1;
    }


    /**
     * Initialise la modification d'un enregistrement existant.
     *
     * L'enregistrement  ne sera effectivement modifié que lorsque {@link update}
     * sera appellé.
     *
     * @throws DatabaseReadOnlyException si la base est ouverte en lecture seule
     * @throws DatabaseNoRecordException s'il n'y a pas d'enregistrement courant
     */
    public function editRecord()
    {
        // Vérifie que la base n'est pas en lecture seule
        if (! $this->xapianDatabase instanceof XapianWritableDatabase)
            throw new DatabaseReadOnlyException();

        // Vérifie qu'on a un enregistrement courant
        if (is_null($this->xapianDocument))
            throw new DatabaseNoRecordException();

        // Mémorise qu'on a une édition en cours
        $this->editMode=2;
    }


    /**
     * Sauvegarde l'enregistrement en cours.
     *
     * @throws DatabaseNotEditingException si l'enregistrement courant n'est pas
     * en cours de modification, c'est-à-dire si on appelle saveRecord() sans
     * avoir appellé {@link addRecord()} ou {@link editRecord()} auparavant.
     *
     * @return int l'identifiant (docid) de l'enregistrement créé ou modifié
     */
    public function saveRecord()
    {
        // Vérifie qu'on a une édition en cours
        if ($this->editMode === 0)
            throw new DatabaseNotEditingException();

        // Affecte une valeur aux champs AutoNumber qui n'en n'ont pas
        foreach($this->autoNumberFields as $name=>$key)
        {
            // Si le champ autonumber n'a pas de valeur, on lui en donne une
            if (! $this->fields[$name]) // null ou 0 ou '' ou false
            {
                // get_metadata retourne '' si la clé n'existe pas. Valeur initiale=1+(int)''=1
                $value=1+(int)$this->xapianDatabase->get_metadata($key);
                $this->fields[$name]=$value;
                $this->xapianDatabase->set_metadata($key, $value);
            }

            // Sinon, si la valeur indiquée est supérieure au compteur, on met à jour le compteur
            else
            {
                $value=(int)$this->fields[$name];
                if ($value>(int)$this->xapianDatabase->get_metadata($key))
                    $this->xapianDatabase->set_metadata($key, $value);
            }
        }

        // Indexe l'enregistrement
        $this->initializeDocument();

        // Ajoute un nouveau document si on est en train de créer un enreg
        if ($this->editMode==1)
        {
            $docId=$this->xapianDatabase->add_document($this->xapianDocument);
        }

        // Remplace le document existant sinon
        else
        {
            $docId=$this->xapianMSetIterator->get_docid();
            $this->xapianDatabase->replace_document($docId, $this->xapianDocument);
        }

        // Edition terminée
        $this->editMode=0;
//        pre($this->schema);
//        die('here');

        // Retourne le docid du document créé ou modifié
        return $docId;
    }


    /**
     * Annule l'édition de l'enregistrement en cours.
     *
     * @throws DatabaseNotEditingException si l'enregistrement courant n'est pas
     * en cours de modification, c'est-à-dire si on appelle saveRecord() sans
     * avoir appellé {@link addRecord()} ou {@link editRecord()} auparavant.
     */
    public function cancelUpdate()
    {
        // Vérifie qu'on a une édition en cours
        if ($this->editMode == 0)
            throw new DatabaseNotEditingException();

        // Recharge le document original pour annuler les éventuelles modifications apportées
        $this->loadDocument();

        // Edition terminée
        $this->editMode=0;
    }


    /**
     * Supprime l'enregistrement en cours
     *
     */
    public function deleteRecord()
    {
        // Vérifie que la base n'est pas en lecture seule
        if (! $this->xapianDatabase instanceof XapianWritableDatabase)
            throw new ReadOnlyDatabaseException();

        // Interdiction d'appeller deleteRecord() juste après addRecord()
        if ($this->editMode == 1)
            throw new LogicException("Appel de deleteRecord() après un appel à addRecord()");

        // Supprime l'enregistrement
        $docId=$this->xapianMSetIterator->get_docid();
        $this->xapianDatabase->delete_document($docId);
    }


    // *************************************************************************
    // *************************** Indexation **********************************
    // *************************************************************************


    /**
     * Retourne un extrait de chaine délimités par des positions ou des chaines
     * de début et de fin.
     *
     * Start et End représente les positions de début et de fin de la chaine à
     * obtenir. Chacun des deux peut être soit un entier soit une chaine.
     * Entier positif = position à partir du début
     * Entier négatif = position depuis la fin
     *
     * @todo complèter la doc
     *
     * @param string $value
     * @param int|string $start
     * @param int|string $end
     * @return string
     */
    private function startEnd($value, $start, $end=null)
    {
        if (is_int($start) && is_int($end) && (($start>0 && $end>0) || ($start<0 && $end<0)) && ($start > $end))
            throw new InvalidArgumentException('Si start et end sont des entiers de même signe, start doit être inférieur à end');

        // On ignore les espaces de début : si on a "    AAAAMMJJ", (0,3) doit retourner AAAA, pas les espaces
        $value=ltrim($value);

        if (is_int($start))
        {
            if ($start) // 0 = prendre tout
            {
                // start > 0 : on veut à partir du ième caractère, -1 pour php
                if ($start > 0)
                {
                    if (is_int($end) && $end>0 ) $end -= $start-1;
                    if (false === $value=substr($value, $start-1)) return '';
                }

                // start < 0 : on veut les i derniers caractères
                elseif (strlen($value)>-$start)
                    $value=substr($value, $start);
            }
        }
        elseif($start !=='')
        {
            $pt=stripos($value, $start); // insensible à la casse mais pas aux accents
            if ($pt !== false)
                $value=substr($value, $pt+strlen($start));
        }

        if (is_int($end))
        {
            if ($end) // 0 = prendre tout
            {
                if ($end>0)
                    $value=substr($value, 0, $end);
                else
                    $value=substr($value, 0, $end);
            }
        }
        elseif($end !=='')
        {
            $pt=stripos($value, $end);
            if ($pt !== false)
                $value=substr($value, 0, $pt);
        }

        return trim($value);
    }


    /**
     * Ajoute un terme dans l'index
     *
     * @param string $term le terme à ajouté
     * @param string $prefix le préfixe à ajouter au terme
     * @param int $weight le poids du terme
     * @param null|int $position null : le terme est ajouté sans position,
     * int : le terme est ajouté avec la position indiquée
     */
    private function addTerm($term, $prefix, $weight=1, $position=null)
    {
        if (is_null($position))
        {
            $this->xapianDocument->add_term($prefix.$term, $weight);
        }
        else
        {
            $this->xapianDocument->add_posting($prefix.$term, $position, $weight);
        }
    }


    /**
     * Initialise le document xapian en cours lors de la création ou de la
     * modification d'un enregistrement.
     *
     */
    private function initializeDocument()
    {
        // On ne stocke dans doc.data que les champs non null
        $data=array_filter($this->fieldById, 'count'); // Supprime les valeurs null et array()

        // Stocke les données de l'enregistrement
        $this->xapianDocument->set_data(serialize($data));

        // Supprime tous les tokens existants
        $this->xapianDocument->clear_terms();

        // Met à jour chacun des index
        $position=0;
        foreach ($this->schema->indices as $index)
        {
            // Détermine le préfixe à utiliser pour cet index
            $prefix=$index->_id.':';

            // Pour chaque champ de l'index, on ajoute les tokens du champ dans l'index
            foreach ($index->fields as $name=>$field)
            {
                // Traite tous les champs comme des champs articles
                $data=(array) $this->fields[$name];

                // Initialise la liste des mots-vides à utiliser
                $stopwords=$this->schema->fields[$name]->_stopwords;

                // Index chaque article
                $count=0;
                foreach($data as $value)    // fixme: seulement si indexation au mot !!!
                {
                    // start et end
                    if ($value==='') continue;
                    if ($field->start || $field->end)
                        if ('' === $value=$this->startEnd($value, $field->start, $field->end)) continue;

                    // Compte le nombre de valeurs non nulles
                    $count++;

                    // Si c'est juste un index de type "count", on n'a rien d'autre à faire
                    if (! $field->words && ! $field->values) continue;

                    // Indexation au mot et à la phrase
                    $tokens=Utils::tokenize($value);
                    foreach($tokens as $term)
                    {
                        // Vérifie que la longueur du terme est dans les limites autorisées
                        if (strlen($term)<self::MIN_TERM or strlen($term)>self::MAX_TERM) continue;

                        // Vérifie que ce n'est pas un mot-vide
                        if (! self::INDEX_STOP_WORDS && isset($stopwords[$term])) continue;

                        // Ajoute le terme dans le document
                        $this->addTerm($term, $prefix, $field->weight, $field->phrases?$position:null);

                        // Correcteur orthographique
                        if (isset($index->spelling) && $index->spelling)
                            $this->xapianDatabase->add_spelling($term); // todo: à étudier, stocker forme riche pour réutilisation dans les lookup terms ?

                        // Incrémente la position du terme en cours
                        $position++;
                    }

                    // Indexation à l'article
                    if ($field->values)
                    {
                        $term=implode('_', $tokens);
                        if (strlen($term)>self::MAX_TERM-2)
                            $term=substr($term, 0, self::MAX_TERM-2);
                        $term = '_' . $term . '_';
                        $this->addTerm($term, $prefix, $field->weight, null);
                    }

                    // Fait de la "place" entre chaque article
                    $position+=100;
                    $position-=$position % 100;
                }

                // Indexation empty/notempty
                if ($field->count)
                    $this->addTerm($count ? '__has'.$count : '__empty', $prefix);
            }
        }

        // Tables de lookup
        foreach ($this->schema->lookuptables as $lookupTable)
        {
            // Détermine le préfixe à utiliser pour cette table
            $prefix='T'.$lookupTable->_id.':';

            // Parcourt tous les champs qui alimentent cette table
            foreach($lookupTable->fields as $name=>$field)
            {
                // Traite tous les champs comme des champs articles
                $data=(array) $this->fields[$name];
                $data=array_slice($data, $field->startvalue-1, $field->endvalue===0 ? null : ($field->endvalue));

                // Initialise la liste des mots-vides à utiliser
                $stopwords=$this->schema->fields[$name]->_stopwords;

                // Index chaque article
                $count=0;
                foreach($data as $value)
                {
                    // start et end
                    if ($value==='') continue;

                    if ($field->start || $field->end)
                        if ('' === $value=$this->startEnd($value, $field->start, $field->end)) continue;

                    // Si la valeur est trop longue, on l'ignore
                    if (strlen($value)>self::MAX_ENTRY) continue;

                    // Table de lookup de type simple
                    if ($lookupTable->_type === DatabaseSchema::LOOKUP_SIMPLE)
                    {
                        $this->addTerm($value, $prefix);
                    }

                    // Table de lookup de type inversée
                    else
                    {
                        // Tokenise et ajoute une entrée dans la table pour chaque terme obtenu
                        foreach(Utils::tokenize($value) as $term)
                        {
                            // Vérifie que la longueur du terme est dans les limites autorisées
                            if (strlen($term)<self::MIN_ENTRY_SLOT || strlen($term)>self::MAX_ENTRY_SLOT) continue;

                            // Vérifie que ce n'est pas un mot-vide
                            if (isset($stopwords[$term])) continue;

                            // Ajoute le terme dans le document
                            $this->addTerm($term.'='.$value, $prefix);
                        }
                    }
                }
            }
        }

        // Clés de tri
        // FIXME : faire un clear_value avant. Attention : peut vire autre chose que des clés de tri. à voir
        foreach($this->schema->sortkeys as $sortkeyname=>$sortkey)
        {
            foreach($sortkey->fields as $name=>$field)
            {
                // Récupère les données du champ, le premier article si c'est un champ multivalué
                $value=$this->fields[$name];
                if (is_array($value)) $value=reset($value);

                // start et end
                if ($field->start || $field->end)
                    $value=$this->startEnd($value, $field->start, $field->end);

                $value=implode(' ', Utils::tokenize($value));

                // Ne prend que les length premiers caractères
                if ($field->length)
                {
                    if (strlen($value) > $field->length)
                        $value=substr($value, 0, $field->length);
                }

                // Si on a une valeur, terminé, sinon examine les champs suivants
                if ($value!==null && $value !== '') break;
            }

            if (!isset($sortkey->type)) $sortkey->type='string'; // FIXME: juste en attendant que les bases asco soient recréées
            switch($sortkey->type)
            {
                case 'string':
                    if (is_null($value) || $value === '') $value=chr(255);
                    break;
                case 'number':
                    if (! is_numeric($value)) $value=INF;
                    $value=Xapian::sortable_serialise($value);
                    break;
                default:
                    throw new LogicException("Type de clé incorrecte pour la clé de tri $sortkeyname");

            }
            $this->xapianDocument->add_value($sortkey->_id, $value);
        }

    }


    // *************************************************************************
    // **************************** Recherche **********************************
    // *************************************************************************

    /**
     * Met en place l'environnement de recherche
     *
     * La fonction crée tous les objets xapian dont on a besoin pour faire
     * analyser une équation et lancer une recherche
     */
    private function setupSearch()
    {
        // Initialise l'environnement de recherche
        $this->xapianEnquire=new XapianEnquire($this->xapianDatabase);

        // Initialise le QueryParser
        $this->xapianQueryParser=new XapianQueryParser();

        // Paramètre l'index par défaut (l'index global)
        if (! is_null($this->defaultIndex))
        {
            $default= Utils::convertString($this->defaultIndex, 'alphanum');
            if (isset($this->schema->indices[$default]))
            {
                $this->xapianQueryParser->add_prefix('', $this->schema->indices[$default]->_id.':');
            }
            elseif (isset($this->schema->aliases[$default]))
            {
                foreach($this->schema->aliases[$default]->indices as $index)
                    $this->xapianQueryParser->add_prefix('', $index->_id.':');
            }
            else
            {
                throw new Exception("Impossible d'utiliser '$default' comme index global : ce n'est ni un index, ni un alias.");
            }
        }

        // Indique au QueryParser la liste des index de base
        foreach($this->schema->indices as $name=>$index)
            $this->xapianQueryParser->add_prefix($name, $index->_id.':');
/*
        foreach($this->schema->indices as $name=>$index)
        {
            if (!isset($index->_type)) $index->_type=DatabaseSchema::INDEX_PROBABILISTIC; // cas d'un schéma compilé avant que _type ne soit implémenté
            switch($index->_type)
            {
                case DatabaseSchema::INDEX_PROBABILISTIC:
                    $this->xapianQueryParser->add_prefix($name, $index->_id.':');
                    break;

                case DatabaseSchema::INDEX_BOOLEAN:
                    $this->xapianQueryParser->add_boolean_prefix($name, $index->_id.':');
                    break;

                default:
                    throw new Exception('index ' . $name . ' : type incorrect : ' . $index->_type);
            }
        }
*/
        // Indique au QueryParser la liste des alias
        foreach($this->schema->aliases as $aliasName=>$alias)
        {
            foreach($alias->indices as $index)
                $this->xapianQueryParser->add_prefix($aliasName, $index->_id.':');
        }
/*
        foreach($this->schema->aliases as $aliasName=>$alias)
        {
            if (!isset($alias->_type)) $alias->_type=DatabaseSchema::INDEX_PROBABILISTIC; // cas d'un schéma compilé avant que _type ne soit implémenté
            switch($alias->_type)
            {
                case DatabaseSchema::INDEX_PROBABILISTIC:
                    foreach($alias->indices as $name=>$index)
                        $this->xapianQueryParser->add_prefix($aliasName, $index->_id.':');
                    break;

                case DatabaseSchema::INDEX_BOOLEAN:
                    foreach($alias->indices as $name=>$index)
                        $this->xapianQueryParser->add_boolean_prefix($aliasName, $index->_id.':');
                    break;

                default:
                    throw new Exception('index ' . $name . ' : type incorrect : ' . $index->_type);
            }
        }
*/

        // Initialise le stopper (suppression des mots-vides)
        $this->stopper=new XapianSimpleStopper();
        foreach ($this->schema->_stopwords as $stopword=>$i)
            $this->stopper->add($stopword);
        $this->xapianQueryParser->set_stopper($this->stopper); // fixme : stopper ne doit pas être une variable locale, sinon segfault

        $this->xapianQueryParser->set_default_op($this->defaultOp);
        $this->xapianQueryParser->set_database($this->xapianDatabase); // indispensable pour FLAG_WILDCARD

        // Expérimental : autorise un value range sur le champ REF s'il existe une clé de tri nommée REF
        foreach($this->schema->sortkeys as $name=>$sortkey)
        {
            if (!isset($sortkey->type)) $sortkey->type='string'; // FIXME: juste en attendant que les bases asco soient recréées
            if ($sortkey->type==='string')
            {
                // todo: xapian ne supporte pas de préfixe pour les stringValueRangeProcessor
                // $this->vrp=new XapianStringValueRangeProcessor($this->schema->sortkeys['ref']->_id);
            }
            else
            {
                $this->vrp=new XapianNumberValueRangeProcessor($sortkey->_id, $name.':', true);
                $this->xapianQueryParser->add_valuerangeprocessor($this->vrp);
            }
            // todo: date
        }
    }

    /**
     * Fonction callback utilisée par {@link parseQuery()} pour convertir
     * la syntaxe [xxx] utilisée dans une équation de recherche en recherche
     * à l'article
     *
     * @param array $matches le tableau généré par preg_replace_callback
     * @return string
     */
    private function searchByValueCallback($matches)
    {
        // récupère le terme à convertir
        $term=trim($matches[1]);

        // Regarde si le terme se termine par une troncature
        $wildcard=substr($term, -1)==='*';

        // Concatène tous les tokens du terme avec un underscore
        $term=implode('_', Utils::tokenize($term));

        // Tronque l'article s'il dépasse la limite autorisée
        if (strlen($term)>self::MAX_TERM-2)
            $term=substr($term, 0, self::MAX_TERM-2);

        // Encadre le terme avec des underscores et ajoute éventuellement la troncature
        $term = '_' . $term ; // fixme: pb si ce qui précède est un caractère aa[bb]cc -> aa_bb_cc. Faut gérer ?
        if ($wildcard) $term.='*'; else $term.='_';

        // Terminé
        return $term;
    }

    /**
     * Traduit les opérateurs booléens français (et, ou, sauf) en opérateurs
     * reconnus par xapian.
     *
     * @param string $equation
     * @return string
     */
    private function protectOperators($equation)
    {
        $t=explode('"', $equation);
        foreach($t as $i=>&$h)
        {
            if ($i%2==1) continue;
            $h=preg_replace
            (
                array('~\b(ET|AND)\b~','~\b(OU|OR)\b~','~\b(SAUF|BUT|NOT)\b~'),
                array(':AND:', ':OR:', ':NOT:'),
                $h
            );

            if ($this->opAnyCase)
            {
                $h=preg_replace
                (
                    array('~\b(et|and)\b~','~\b(ou|or)\b~','~\b(sauf|but|not)\b~'),
                    array('~and~', '~or~', '~not~'),
                    $h
                );
            }
        }
        return implode('"', $t);
    }

    private function restoreOperators($equation)
    {
        return str_replace
        (
            array('~and~', '~or~', '~not~', ':and:', ':or:', ':not:'),
            array('and', 'or', 'not', 'AND', 'OR', 'NOT'),
            $equation
        );
    }

    /**
     * Construit une requête xapian à partir d'une équation de recherche saisie
     * par l'utilisateur.
     *
     * Si la requête à analyser est null ou une chaine vide, un objet XapianQuery
     * special permettant de rechercher tous les documents présents dans la base
     * est retourné.
     *
     * @param string $equation
     * @return XapianQuery
     */
    private function parseQuery($equation)
    {
        // Equation=null ou chaine vide : sélectionne toute la base
        if (is_null($equation) || $equation==='' || $equation==='*')
            return new XapianQuery('');

        // Pré-traitement de la requête pour que xapian l'interprête comme on souhaite
        $equation=preg_replace_callback('~(?:[a-z0-9]\.){2,9}~i', array('Utils', 'acronymToTerm'), $equation); // sigles à traiter, xapian ne le fait pas s'ils sont en minu (a.e.d.)
        $equation=preg_replace_callback('~\[(.*?)\]~', array($this,'searchByValueCallback'), $equation);
        $equation=$this->protectOperators($equation);
        $equation=Utils::convertString($equation, 'queryparser'); // FIXME: utiliser la même table que tokenize()
        $equation=$this->restoreOperators($equation);

        $flags=
            XapianQueryParser::FLAG_BOOLEAN |
            XapianQueryParser::FLAG_PHRASE |
            XapianQueryParser::FLAG_LOVEHATE |
            XapianQueryParser::FLAG_WILDCARD |
            XapianQueryParser::FLAG_SPELLING_CORRECTION |
            XapianQueryParser::FLAG_PURE_NOT;

        if ($this->opAnyCase)
            $flags |= XapianQueryParser::FLAG_BOOLEAN_ANY_CASE;

        // Construit la requête
        $query=$this->xapianQueryParser->parse_Query
        (
            utf8_encode($equation),         // à partir de la version 1.0.0, les composants "texte" de xapian attendent de l'utf8
            $flags
        );

        // Correcteur orthographique
        $this->correctedEquation=$this->xapianQueryParser->get_corrected_query_string();

        return $query;
    }

    /**
     * Fonction expérimentale utilisée par {@link parseQuery()} pour convertir
     * les numéros de préfixe présents dans l'équation retournée par xapian en
     * noms d'index tels que définis par l'utilisateur.
     *
     * @param array $matches le tableau généré par preg_replace_callback
     * @return string
     */
    private function idToName($matches)
    {
        $id=(int)$matches[1];
        foreach($this->schema->indices as $index)
            if ($index->_id===$id) return $index->name.'=';
        return $matches[1];
    }

/*

  AMELIORATIONS A APPORTER AU SYSTEME DE RECHERCHE, REFLEXION 21/12/2007

- Dans le schéma de la base (DatabaseSchema, DbEdit) ajouter pour
  chaque index (que ce soit un vrai index ou un alias) une propriété
  "type d'index" qui peut prendre les valeurs "index probablistique" ou
  "filtre".

- Dans le setupSearch(), lorsqu'on ajoute la liste des index/préfixes, utiliser
  cette propriété pour indiquer à xapian le type d'index :

  * "index probabalistique" : utiliser add_prefix()
  * "filtre" : utiliser add_boolean_prefix()).

- Lors d'une recherche, ne pas chercher à combiner nous-même les différents
  champs et les différents bouts d'équations : laisser xapian le faire.
  Si on nous a transmis "_equation=xxx & date=yyy & type=zzz" en query string,
  se contenter de concaténer le tout et laisser xapian utiliser le defaultOp().
  Xapian se chargera tout seul de passer en 'filter' tous les index définis
  avec add_boolean_prefix().

- Il faut quand même tout parenthéser au cas ou les bouts contiennent plusieurs
  ce qui nous donne : (xxx) date:(yyy) type:(zzz)

- voir comment on peut implémenter ça en gardant la compatibilité avec BIS

*/

    public function getFacet($table, $sortByCount=false)
    {
        if (! $this->spy) return array();

        $key=Utils::ConvertString($table, 'alphanum');
        if (!isset($this->schema->lookuptables[$key]))
            throw new Exception("La table de lookup '$table' n'existe pas");
        $prefix='T' . $this->schema->lookuptables[$key]->_id . ':';
        $facet=$this->spy->get_terms_as_array($prefix);

        // workaround bug dans TermSpy : si la lettre qui suit le prefix est une maju, l'entrée est ignorée
//        $t=array();
//        foreach($facet as $key=>&$value)
//            $t[substr($key,1)]=$value;
//        $facet=$t;
        // fin workaround

        if ($sortByCount)
            arsort($facet, SORT_NUMERIC);
        else
            ksort($facet, SORT_LOCALE_STRING);
        return $facet;
    }

    /**
     * @inheritdoc
     */
    public function search($equation=null, $options=null)
    {
        // a priori, pas de réponses
        $this->eof=true;

        $rset=null;
        // Analyse les options indiquées (start et sort)
        $this->defaultOp=XapianQuery::OP_OR;
        if (is_array($options))
        {
            $sort=isset($options['_sort']) ? $options['_sort'] : null;
            $start=isset($options['_start']) ? ((int)$options['_start'])-1 : 0;
            if ($start<0) $start=0;
            $max=isset($options['_max']) ? $options['_max'] : 10;
            if (is_array($max))
                foreach ($max as $i)
                    if ($i) { $max=$i; break;}
            if (is_numeric($max))
            {
                $max=(int)$max;
                if ($max<-1) $max=10;
            }
            else
                $max=10;

            if (isset($options['_filter']))
                $filter=(array)$options['_filter'];
            else
                $filter=null;

            if (isset($options['_minscore']))
                $minscore=(int)$options['_minscore'];
            else
                $minscore=0;

            if (isset($options['_rset']) && is_array($id=$options['_rset']))
            {
                $rset=new XapianRset();
                foreach($id as $id)
                    $rset->add_document($id);
            }

            if (isset($options['_defaultop']))
            {
                switch( strtolower(trim($options['_defaultop'])))
                {
                    case 'et':
                    case 'and':
                        $this->defaultOp=XapianQuery::OP_AND;
                        break;
                    case 'ou':
                    case 'or':
                        $this->defaultOp=XapianQuery::OP_OR;
                        break;
                    default:
                        throw new Exception('Opérateur par défaut incorrect : '.$options['_defaultop']);
                }
            }

            if (isset($options['_opanycase']))
                $this->opAnyCase=(bool)$options['_opanycase'];
            else
                $this->opAnyCase=true;

            if (isset($options['_defaultindex']))
                $this->defaultIndex=$options['_defaultindex'];

            if (isset($options['_facets']))
                $facets=(array)$options['_facets'];
            else
                $facets=array();
        }
        else
        {
            $sort=null;
            $start=0;
            $max=-1;
            $filter=null;
            $minscore=0;
            $facets=array();
        }

        // Ajuste start pour que ce soit un multiple de max
        if ($max>0) $start=$start-($start % $max);

        /*
         * explication : si on est sur la 2nde page avec max=10, on affiche la
         * 11ème réponse en premier. Si on demande alors à passer à 50 notices par
         * page, on va alors afficher les notices 11 à 50, mais on n'aura pas
         * de lien "page précédente".
         * Le code ci-dessus, dans ce cas, ramène "start" à 1 pour que toutes
         * les notices soient affichées.
         */

        // Stocke les valeurs finales
        $this->start=$start+1;
        $this->max=$max;


        if ($minscore<0) $minscore=0; elseif($minscore>100) $minscore=100;

        // Met en place l'environnement de recherche lors de la première recherche
        if (is_null($this->xapianEnquire)) $this->setupSearch();

        // Analyse les filtres éventuels à appliquer à la recherche
        if ($filter)
        {
            $this->xapianFilter=null;
            $this->filter=implode(' AND ', $filter);
            foreach($filter as $filter)
            {
                $filter=$this->parseQuery($filter);
                if (is_null($this->xapianFilter))
                    $this->xapianFilter=$filter;
                else
                    $this->xapianFilter=new XapianQuery(XapianQuery::OP_FILTER, $this->xapianFilter, $filter);
            }
        }

        // Analyse l'équation de recherche de l'utilisateur
        $this->equation=$equation;
        $query=$this->xapianQuery=$this->parseQuery($equation);

        // Problème xapian : si on fait une recherche '*' avec un tri par pertinence,
        // xapian ne rends pas la main. Du coup on force içi un tri par docid décroissant.
        if (trim($equation)==='*') $sort='-';

        // Combine l'équation et le filtre pour constituer la requête finale
        if ($filter)
            $query=new XapianQuery(XapianQuery::OP_FILTER, $query, $this->xapianFilter);

        // Exécute la requête
        $this->xapianEnquire->set_query($query);

        // Définit l'ordre de tri des réponses
        $this->setSortOrder($sort);

        // Définit le score minimal souhaité
        if ($minscore) $this->xapianEnquire->set_cutoff($minscore);

        // Lance la recherche

        // Expérimental : support des facettes de la recherche via un TermCountMatchSpy.
        // Requiert la version "MatchSpy" de Xapian (en attendant que la branche
        // MatchSpy ait été intégrée dans le trunk.
        if ($facets && function_exists('new_TermCountMatchSpy'))
        {
            // Fonctionnement : on définit dans la config une clé facets qui
            // indique les tables de lookup qu'on souhaite utiliser comme facettes.
            // DatabaseModule::select() nous passe cette liste dans le paramètre
            // '_facet' du tableau options.
            // On crée un Spy de type XapianTermCountMatchSpy auquel on
            // demande de compter tous les termes provenant de ces tables
            // de lookup.
            // L'utilisateur peut ensuite récupérer le résultat en utilisant
            // la méthode getFacet() et en appellant searchInfo() avec les
            // nouveaus paramètres spy* introduits.
            $this->spy=new XapianTermCountMatchSpy();
            foreach($facets as $table)
            {
                $key=Utils::ConvertString($table, 'alphanum');
                if (!isset($this->schema->lookuptables[$key]))
                    throw new Exception("La table de lookup '$table' n'existe pas");
                $prefix='T' . $this->schema->lookuptables[$key]->_id . ':';
                $this->spy->add_prefix($prefix);
            }
            $this->xapianMSet=$this->xapianEnquire->get_MSet($start, $max, 1000, $rset, null, $this->spy);
        }

        // Recherche standad sans facettes
        else
        {
            $this->spy=null;
            $this->xapianMSet=$this->xapianEnquire->get_MSet($start, $max, $max+1, $rset);
        }

        // Teste si la requête a retourné des réponses
        if ($this->xapianMSet->is_empty())
        {
            $this->xapianMSetIterator=null;
            $this->count=0;
            return false;
        }

        $this->xapianMSetIterator=$this->xapianMSet->begin();
        $this->count=$this->xapianMSet->get_matches_estimated();
        $this->loadDocument();
        $this->eof=false;

        // Retourne true pour indiquer qu'on a au moins une réponse
        return true;
    }


    /**
     * Paramètre le MSet pour qu'il retourne les documents selon l'ordre de tri
     * indiqué en paramètre.
     *
     * @param string|array|null $sort un tableau ou une chaine indiquant les
     * différents critères composant l'ordre de tri souhaité.
     *
     * Les critères de tri possible sont :
     * - <code>%</code> : trier les notices par pertinence (la meilleure en tête)
     * - <code>+</code> : trier par ordre croissant des numéros de document
     * - <code>-</code> : trier par ordre décroissant des numéros de document
     * - <code>xxx+</code> : trier sur le champ xxx, par ordre croissant
     *   (le signe plus est optionnel, c'est l'ordre par défaut)
     * - <code>xxx-</code> : trier sur le champ xxx, par ordre décroissant
     *
     * Plusieurs critères de tri peuvent être combinés entres eux. Dans ce cas,
     * le premier critère sera d'abord utilisé, puis, en cas d'égalité, le
     * second et ainsi de suite.
     *
     * La combinaison des critères peut se faire soit en passant en paramètre
     * une chaine listant dans l'ordre les différents critères, soit en passant
     * en paramètre un tableau contenant autant d'éléments que de critères ;
     * soit en combinant les deux.
     *
     * Exemple de critères composites :
     * - chaine : <code>'type'</code>, <code>'type+ date- %'</code>
     * - tableau : <code>array('type', 'date+')</code>,
     *   <code>array('type', 'date+ revue+ titre %'</code>
     *
     * Remarque : n'importe quel caractère de ponctuation peut être utilisé
     * pour séparer les différents critères au sein d'une même chaine (espace,
     * virgule, point-virgule...)
     *
     * @throws Exception si l'ordre de tri demandé n'est pas possible ou si
     * la clé de tri indiquée n'existe pas dans la base.
     */
    private function setSortOrder($sort=null)
    {
        // Si $sort est un tableau, on concatène tous les éléments ensembles
        if (is_array($sort))
            $sort=implode(',', $sort);

        // On a une chaine unique avec tous les critères, on l'explose
        $t=preg_split('~[^a-zA-Z_%+-]+~m', $sort, -1, PREG_SPLIT_NO_EMPTY);

        // Ordre de tri par défaut : par docid décroissants
        if (empty($t))
            $t=array('-');

        // Cas d'un tri simple (un seul critère indiqué)
        $this->sortKey=array();
        if (count($t)===1)
        {
            $this->sortOrder = $key = $t[0];
            switch ($key)
            {
                // Par pertinence
                case '%':
                    $this->xapianEnquire->set_Sort_By_Relevance();
                    break;

                // Par docid croissants
                case '+':
                    $this->xapianEnquire->set_weighting_scheme(new XapianBoolWeight());
                    $this->xapianEnquire->set_DocId_Order(XapianEnquire::ASCENDING);
                    break;

                // Par docid décroissants
                case '-':
                    $this->xapianEnquire->set_weighting_scheme(new XapianBoolWeight());
                    $this->xapianEnquire->set_DocId_Order(XapianEnquire::DESCENDING);
                    break;

                // Sur une clé de tri existante
                default:
                    // Détermine l'ordre (croissant/décroissant)
                    $lastChar=substr($key, -1);
                    $forward=true;
                    if ($lastChar==='+' || $lastChar==='-')
                    {
                        $key=substr($key, 0, -1);
                        $forward=($lastChar==='+');
                    }

                    // Vérifie que la clé de tri existe dans la base
                    $key=strtolower($key);
                    if (! isset($this->schema->sortkeys[$key]))
                        throw new Exception('Impossible de trier par : ' . $key);

                    // Récupère l'id de la clé de tri (= le value slot number à utiliser)
                    $id=$this->schema->sortkeys[$key]->_id;

                    // Trie sur cette valeur
                    $this->xapianEnquire->set_sort_by_value($id, !$forward);

                    // Mémorise l'ordre de tri en cours (pour searchInfo)
                    $this->sortOrder=$key . ($forward ? '+' : '-');
                    $this->sortKey[$key]=$id;
            }
        }

        // Cas d'un tri composite (plusieurs critères de tri)
        else
        {
            // On va utiliser un sorter xapian pour créer la clé
            $this->xapianSorter=new XapianMultiValueSorter();

            // Réinitialise l'ordre de tri en cours
            $this->sortOrder='';

            // On va utiliser la méthode set_sort_by_key sauf s'il faut combiner avec la pertinence
            $function='set_sort_by_key';

            // Ajoute chaque critère de tri au sorter
            foreach($t as $i=>$key)
            {
                switch ($key)
                {
                    // Par pertinence : change la méthode à utiliser
                    case '%':
                        if ($i===0)
                            $method='set_sort_by_relevance_then_key';
                        elseif($i===count($t)-1)
                            $method='set_sort_by_key_then_relevance';
                        else
                            throw new Exception('Ordre de tri incorrect "'.$sort.'" : "%" peut être au début ou à la fin mais pas au milieu');

                        $this->sortOrder.=$key . ' ';
                        break;

                    // Par docid : impossible, on ne peut pas combiner avec autre chose
                    case '+':
                    case '-':
                        throw new Exception('Ordre de tri incorrect "'.$sort.'" : "'.$key.'" ne peut pas être utilisé avec d\'autres critères');
                        break;

                    // Sur une clé de tri existante
                    default:
                        // Détermine l'ordre (croissant/décroissant)
                        $lastChar=substr($key, -1);
                        $forward=true;
                        if ($lastChar==='+' || $lastChar==='-')
                        {
                            $key=substr($key, 0, -1);
                            $forward=($lastChar==='+');
                        }

                        // Vérifie que la clé de tri existe dans la base
                        $key=strtolower($key);
                        if (! isset($this->schema->sortkeys[$key]))
                            throw new Exception('Impossible de trier par : ' . $key);

                        // Récupère l'id de la clé de tri (= le value slot number à utiliser)
                        $id=$this->schema->sortkeys[$key]->_id;

                        // Ajoute cette clé au sorter
                        $this->xapianSorter->add($id, $forward);

                        // Mémorise l'ordre de tri en cours (pour searchInfo)
                        $this->sortOrder.=$key . ($forward ? '+ ' : '- ');
                        $this->sortKey[$key]=$id;
                }
            }

            // Demande à xapian de trier en utilisant la méthode et le sorter obtenu
            $this->xapianEnquire->$function($this->xapianSorter, false);

            // Supprime l'espace final de l'ordre en cours
            $this->sortOrder=trim($this->sortOrder);
        }
    }


    /**
     * Suggère des termes provenant de la table indiquée
     *
     * @param string $table
     * @return array
     */
    public function suggestTerms($table)
    {
        // Vérifie qu'on a un enregistrement en cours
        if (is_null($this->xapianMSetIterator))
            throw new Exception('Pas de document courant');

        // Détermine le préfixe de la table dans laquelle on veut chercher
        if ($table)
        {
            $key=Utils::ConvertString($table, 'alphanum');
            if (!isset($this->schema->lookuptables[$key]))
                throw new Exception("La table de lookup '$table' n'existe pas");
            $prefix='T' . $this->schema->lookuptables[$key]->_id . ':';
        }

        $rset=new XapianRset();

        $it=$this->xapianMSet->begin();
//        $nb=0;
        while (!$it->equals($this->xapianMSet->end()))
        {
            $rset->add_document($it->get_docid());
//            $nb++;
//            if ($nb>5) break;
            $it->next();
        }

        $eset=$this->xapianEnquire->get_eset(100, $rset);
        $terms=array();
        $it=$eset->begin();
        while (!$it->equals($eset->end()))
        {
            $term=$it->get_term();
            if (substr($term,0,strlen($prefix))===$prefix)
                $terms[substr($term, strpos($term, '=')+1)]=true;// . '('.$it->get_weight().')';
            $it->next();
        }

        return array_keys($terms);
    }

    private function loadDocument()
    {
        // Réinitialise tous les champs à leur valeur par défaut
        // Corrige à la fois :
        // bug de actionReindex() qui fusionne les notices
        // bug trouvé par SF : search(texte officiel) -> on répête les infos
        // Voir si on peut corriger le bug autrement qu'en bouclant.
        foreach($this->fields as $name=>&$value)
            $value=null;

        if (is_null($this->xapianMSetIterator))
            throw new Exception('Pas de document courant');

        if ($this->xapianMSetIterator->equals($this->xapianMSet->end()))
        {
            $this->xapianDocument=null;
        }
        else
        {
            $this->xapianDocument=$this->xapianMSetIterator->get_document();
            $data=unserialize($this->xapianDocument->get_data());
            foreach($data as $id=>$data)
            {
                if (array_key_exists($id, $this->fieldById))
                    $this->fieldById[$id]=$data;
                // else : il s'agit d'un champ qui n'existe plus
            }
        }
    }

    /**
     * Retourne la liste des termes du document en cours
     *
     * @return array
     */
    public function getTerms()
    {
        if (is_null($this->xapianDocument))
            throw new Exception('Pas de document courant');

//        $indexName=array_flip($this->schema['index']);
//        $entryName=array_flip($this->schema['entries']);

        $result=array();

        $begin=$this->xapianDocument->termlist_begin();
        $end=$this->xapianDocument->termlist_end();
        while (!$begin->equals($end))
        {
            $term=$begin->get_term();
            if (false === $pt=strpos($term,':'))
            {
                $kind='index';
                $index='*';
            }
            else
            {
                //print_r($this->lookuptableById);
                //die();
                $prefix=substr($term,0,$pt);
                if($prefix[0]==='T')
                {
                    $kind='lookup';
                    $prefix=substr($prefix, 1);
                    $index=$this->lookuptableById[$prefix]->name; //$entryName[$prefix];
                }
                else
                {
                    $kind='index';
//                    $index=$prefix; //$indexName[$prefix];
                    $index=$this->indexById[$prefix]->name;
                }
                $term=substr($term,$pt+1);
            }

            $posBegin=$begin->positionlist_begin();
            $posEnd=$begin->positionlist_end();
            $pos=array();
            while(! $posBegin->equals($posEnd))
            {
                $pos[]=$posBegin->get_termpos();
                $posBegin->next();
            }
//            echo "kind=$kind, index=$index, term=$term<br />";
            $result[$kind][$index][$term]=array
            (
                'freq'=>$begin->get_termfreq(),
                'wdf'=>$begin->get_wdf(),
//                'positions'=>$pos
            );
            if ($pos)
                $result[$kind][$index][$term]['positions']=$pos;

            //'freq='.$begin->get_termfreq(). ', wdf='. $begin->get_wdf();
            $begin->next();
        }
        foreach($result as &$t)
            ksort($t);
        return $result;
    }

    /**
     * Retourne une estimation du nombre de réponses obtenues lors de la
     * dernière recherche exécutée.
     *
     * @param int|string $countType le type d'estimation à fournir ou le
     * libellé à utiliser

     * @return int|string
     */
    public function count($countType=0)
    {
        // Si l'argument est une chaine, on considère que l'utilisateur veut
        // une évaluation (arrondie) du nombre de réponses et cette chaine
        // est le libellé à utiliser (par exemple : 'environ %d ')
        if (is_string($countType))
        {
            if (is_null($this->xapianMSet)) return 0;
            $count=$this->xapianMSet->get_matches_estimated();
            if ($count===0) return 0;

            $min=$this->xapianMSet->get_matches_lower_bound();
            $max=$this->xapianMSet->get_matches_upper_bound();

//            echo
//                'Etapes du calcul : <br />',
//                'min : ', $min, '<br />',
//                'max : ', $max, '<br />',
//                'count : ', $count, '<br />';

            // Si min==max, c'est qu'on a le nombre exact de réponses, pas d'évaluation
            if ($min === $max) return $min;

            $unit = pow(10, floor(log10($max-$min))-1);
            $round=max(1,round($count / $unit)) * $unit;

//            echo
//                'diff=', $max-$min, '<br />',
//                'log10(diff)=', log10($max-$min), '<br />',
//                'floor(log10(diff))=', floor(log10($max-$min)), '<br />',
//                'unit -1 =pow(10, floor(log10($max-$min))-1)', $unit, '<br />',
//                'unit=pow(10,floor(log10(diff)))=', pow(10,floor(log10($max-$min))), '<br />',
//                'count/puissance=', $count/$unit, '<br />',
//                'round(count/puissance)=', round($count/$unit), '<br />',
//                'round(count/puissance)* puissance=', $round, '<br />',
//                '<strong>Result : ', $round, '</strong><br />',
//                '<br />'
//                ;

            if ($unit===0.1)
                return '~&#160;' . $round; //  ou '±&#160;'

            return
                (strpos($countType, '%')===false)
                ?
                $countType . $round
                :
                sprintf($countType, $round);
        }
        return $this->count;
    }

    // *************************************************************************
    // *************** INFORMATIONS SUR LA RECHERCHE EN COURS ******************
    // *************************************************************************

    public function searchInfo($what)
    {
        switch (strtolower($what))
        {
            case 'docid': return $this->xapianMSetIterator->get_docid();

            case 'equation': return $this->equation;
            case 'filter': return $this->filter;
            case 'rank': return $this->xapianMSetIterator->get_rank()+1;
            case 'start': return $this->start;
            case 'max': return $this->max;

            case 'correctedequation': return $this->correctedEquation;

            // Liste des mots-vides ignorés dans l'équation de recherche
            case 'stopwords': return $this->getRequestStopwords(false);
            case 'internalstopwords': return $this->getRequestStopwords(true);

            // Liste des termes présents dans l'équation + termes correspondants au troncatures
            case 'queryterms': return $this->getQueryTerms(false);
            case 'internalqueryterms': return $this->getQueryTerms(true);

            // Liste des termes du document en cours qui collent à la requête
            case 'matchingterms': return $this->getMatchingTerms(false);
            case 'internalmatchingterms': return $this->getMatchingTerms(true);

            // Score obtenu par le document en cours
            case 'score': return $this->xapianMSetIterator->get_percent();
            case 'internalscore': return $this->xapianMSetIterator->get_weight();

            // Tests
            case 'maxpossibleweight': return $this->xapianMSet->get_max_possible();
            case 'maxattainedweight': return $this->xapianMSet->get_max_attained();

            case 'internalquery': return $this->xapianQuery->get_description();
            case 'internalfilter': return is_null($this->xapianFilter) ? null : $this->xapianFilter->get_description();
            case 'internalfinalquery': return $this->xapianEnquire->get_query()->get_description();

            // Le libellé de la clé de tri en cours
            case 'sortorder':
                return  $this->sortOrder;

            // La valeur de la clé de tri pour l'enreg en cours
            case 'sortkey':
                //return $this->sortKey;
                if (empty($this->sortKey)) return array($this->xapianMSetIterator->get_weight());

                $result=array();
                foreach($this->sortKey as $key=>$id)
                    $result[$key]=$this->xapianDocument->get_value($id);
                return $result;

            case 'spydocumentsseen':
                return $this->spy ? $this->spy->get_documents_seen() : 0;

            case 'spytermsseen':
                return $this->spy ? $this->spy->get_terms_seen() : 0;

            default: return null;
        }
    }

    /**
     * Retourne la liste des termes de recherche générés par la requête.
     *
     * getQueryTerms construit la liste des termes d'index qui ont été générés
     * par la dernière requête analysée.
     *
     * La liste comprend tous les termes présents dans la requête (mais pas les
     * mots vides) et tous les termes générés par les troncatures.
     *
     * Par exemple, la requête <code>éduc* pour la santé</code> pourrait
     * retourner <code>array('educateur', 'education', 'sante')</code>.
     *
     * Par défaut, les termes retournés sont filtrés de manière à pouvoir être
     * présentés à l'utilisateur (dédoublonnage des termes, suppression des
     * préfixes internes utilisés dans les index de xapian), mais vous pouvez
     * passer <code>false</code> en paramètre pour obtenir la liste brute.
     *
     * @param bool $internal flag indiquant s'il faut filtrer ou non la liste
     * des termes.
     *
     * @return array un tableau contenant la liste des termes obtenus.
     */
    private function getQueryTerms($internal=false)
    {
        $terms=array();
        $begin=$this->xapianQuery->get_terms_begin();
        $end=$this->xapianQuery->get_terms_end();
        while (!$begin->equals($end))
        {
            $term=$begin->get_term();
            if ($internal)
            {
                $terms[]=$term;
            }
            else
            {
                // Supprime le préfixe éventuel
                if (false !== $pt=strpos($term, ':')) $term=substr($term,$pt+1);

                // Pour les articles, supprime les underscores
                $term=strtr(trim($term, '_'), '_', ' ');

                $terms[$term]=true;
            }

            $begin->next();
        }
        return $internal ? $terms : array_keys($terms);
    }

    /**
     * Retourne la liste des mots-vides présents dans la la requête.
     *
     * getRequestStopWords construit la liste des termes qui figuraient dans
     * la dernière requête analysée mais qui ont été ignorés parcequ'ils
     * figuraient dans la liste des mots-vides déinis dans la base.
     *
     * Par exemple, la requête <code>outil pour le web, pour internet</code>
     * pourrait retourner <code>array('pour', 'le')</code>.
     *
     * Par défaut, les termes retournés sont dédoublonnés, mais vous pouvez
     * passer <code>false</code> en paramètre pour obtenir la liste brute (dans
     * l'exemple ci-dessus, on obtiendrait <code>array('pour', 'le', 'pour')</code>
     *
     * @param bool $internal flag indiquant s'il faut dédoublonner ou non la
     * liste des mots-vides.
     *
     * @return array un tableau contenant la liste des termes obtenus.
     */
    private function getRequestStopWords($internal=false)
    {
        // Liste des mots vides ignorés
        if (is_null($this->xapianQueryParser)) return array();

        $stopwords=array();
        $iterator=$this->xapianQueryParser->stoplist_begin();
        while(! $iterator->equals($this->xapianQueryParser->stoplist_end()))
        {
            if ($internal)
                $stopwords[]=$iterator->get_term(); // pas de dédoublonnage
            else
                $stopwords[$iterator->get_term()]=true; // dédoublonne en même temps
            $iterator->next();
        }
        return $internal ? $stopwords : array_keys($stopwords);
    }

    /**
     * Retourne la liste des termes du document en cours qui correspondent aux
     * terms de recherche générés par la requête.
     *
     * getMatchingTerms construit l'intersection entre la liste des termes
     * du document en cours et la liste des termes générés par la requête.
     *
     * Cela permet, entre autres, de comprendre pourquoi un document apparaît
     * dans la liste des réponses.
     *
     * Par défaut, les termes retournés sont filtrés de manière à pouvoir être
     * présentés à l'utilisateur (dédoublonnage des termes, suppression des
     * préfixes internes utilisés dans les index de xapian), mais vous pouvez
     * passer <code>false</code> en paramètre pour obtenir la liste brute.
     *
     * @param bool $internal flag indiquant s'il faut filtrer ou non la liste
     * des termes.
     *
     * @return array un tableau contenant la liste des termes obtenus.
     */
    private function getMatchingTerms($internal=false)
    {
        $terms=array();
        $begin=$this->xapianEnquire->get_matching_terms_begin($this->xapianMSetIterator);
        $end=$this->xapianEnquire->get_matching_terms_end($this->xapianMSetIterator);
        while(!$begin->equals($end))
        {
            $term=$begin->get_term();
            if ($internal)
            {
                $terms[]=$term;
            }
            else
            {
                // Supprime le préfixe éventuel
                if (false !== $pt=strpos($term, ':')) $term=substr($term,$pt+1);

                // Pour les articles, supprime les underscores
                $term=strtr(trim($term, '_'), '_', ' ');

                $terms[$term]=true;
            }

            $begin->next();
        }
        return $internal ? $terms : array_keys($terms);
    }

    public function moveNext()
    {
        if (is_null($this->xapianMSet)) return;
        $this->xapianMSetIterator->next();
        $this->loadDocument();
        $this->eof=$this->xapianMSetIterator->equals($this->xapianMSet->end());
    }

    const
        MAX_KEY=240,            // Longueur maximale d'un terme, tout compris (doit être inférieur à BTREE_MAX_KEY_LEN de xapian)
        MAX_PREFIX=4,           // longueur maxi d'un préfixe (par exemple 'T99:')
        MIN_TERM=1,             // Longueur minimale d'un terme
        MAX_TERM=236,           // =MAX_KEY-MAX_PREFIX, longueur maximale d'un terme
        MIN_ENTRY_SLOT=2,       // longueur minimale d'un mot de base dans une table de lookup
        MAX_ENTRY_SLOT=20,      // longueur maximale d'un mot de base dans une table de lookup
        MAX_ENTRY=219           // =MAX_KEY-MAX_ENTRY_SLOT-1, longueur maximale d'une valeur dans une table des entrées (e.g. masson:Editions Masson)
        ;
    const
        INDEX_STOP_WORDS=false; // false : les mots-vides sont ignorés lors de l'indexation, true : ils sont ajoutés à l'index (mais ignoré pendant la recherche)

    /**
     * Suggère à l'utilisateur des entrées ou des termes existant dans l'index
     * de xapian.
     *
     * Lookup prend en paramètre un mot, un début de mot ou une expression
     * constituée de plusieurs mots ou début de mots et va rechercher dans les
     * index de xapian des termes, des articles ou des entrées issues des tables
     * de lookup susceptibles de correspondre à ce que rechercher l'utilisateur.
     *
     * Lookup teste dans l'ordre que la "table" indiquée en paramètre correspond
     * au nom d'une table de lookup, d'un alias ou d'un index existant (une
     * exception sera générée si ce n'est pas le cas).
     *
     * Selon la source utilisée, la nature des suggestions retournées sera
     * différente :
     * - S'il s'agit d'une table de lookup inversée, lookup retournera des
     *   entrées en format riche (majuscules et minuscules, accents) contenant
     *   tous les mots indiqués.
     * - S'il s'agit d'une table de lookup simple, lookup retournera également
     *   des entrées en format riche, mais uniquement celles qui commencent par
     *   l'un des mots indiqués (et qui contiennent tous les autres).
     * - S'il s'agit d'un index de type "article", lookup retournera des chaines
     *   "pauvres" (en minuscules non accentuées) qui commencent par l'un des
     *   mots indiqués et contiennent tous les autres.
     * - S'il s'agit d'un index de type "mot", seul le dernier mot indiqué dans
     *   l'expression de recherche sera pris en compte et les suggestions
     *   retournées sous la forme de "mots" en format pauvre.
     * - S'il s'agit d'un alias, les suggestions retournées correspondront au
     *   type des indices composant cet alias (i.e. soit des articles, soit des
     *   termes).
     *
     * @param string $table le nom de la table de lookup, de l'alias ou de
     * l'index à utiliser pour générer des suggestions.
     *
     * @param string $term le mot, le début de mot ou l'expression à rechercher.
     *
     * @param int $max le nombre maximum de suggestions à retourner
     * (0=pas de limite)
     *
     * @param bool $sort indique s'il faut trier les réponses par ordre
     * alphabétique ou par nombre décroissant d'occurences dans la base.
     *
     * Ce paramètre accepte les valeurs suivantes :
     * - false ou '-' : trier par ordre alphabétique ;
     * - true ou '%' : trier par nombre d'occurences.
     *
     * Par défaut (false ou '-'), les suggestions sont triées en ordre
     * alphabétique. La recherche s'arrête dès que $max suggestions ont été
     * trouvées.
     *
     * Si $sort est à true (ou '%'), lookup va générer la liste complète de
     * toutes les suggestions possibles puis va trier le résultat obtenu par
     * occurences décroissantes et va ensuite conserver les $max meilleures.
     *
     * Un lookup avec le tri par défaut (ordre alphabétique) est donc bien plus
     * efficace.
     *
     * @param string $format définit le format à utiliser pour la mise en
     * surbrillance des termes de recherche de l'utilisateur au sein de chacun
     * des suggestions trouvées.
     *
     * Il s'agit d'une chaine qui sera appliquée à chacune des mots en utilisant
     * la fonction sprintf() de php (exemple de format : <strong>%s</strong>).
     *
     * Si $format est null ou s'il s'agit d'une chaine vide, aucune surbrillance
     * ne sera appliquée.
     *
     * @return array un tableau contenant les suggestions obtenues. Chaque clé
     * du tableau contient une suggestion et la valeur associée contient le
     * nombre d'occurences de cette entrée dans la base.
     *
     * Exemple :
     * <code>
     * array
     * (
     *     'droit du malade' => 10,
     *     'information du malade' => 3
     * )
     * </code>
     */
    public function lookup($table, $term, $max=0, $sort=false, $format='<strong>%s</strong>')
    {
        require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR. 'XapianLookupHelpers.php');

        /**
         * @var LookupHelper
         */
        $helper=null;

        // Ajuste $sort
        if ($sort === false || $sort==='-')
            $sort = false;
        elseif($sort===true || $sort==='%')
            $sort=true;
        else
            throw new BadMethodCallException('Valeur incorrecte pour $sort : '.var_export($sort,true));

        // Construit la version "minuscules non accentuées" de la table indiquée
        $key=Utils::ConvertString($table, 'alphanum');

        // Teste s'il s'agit d'une table de lookup
        if (isset($this->schema->lookuptables[$key]))
        {
            switch(Utils::get($this->schema->lookuptables[$key]->_type, DatabaseSchema::LOOKUP_INVERTED))
            {
                case DatabaseSchema::LOOKUP_SIMPLE:
                    $helper=new SimpleTableLookup();
                    break;

                case DatabaseSchema::LOOKUP_INVERTED:
                    $helper=new InvertedTableLookup();
                    break;

                default:
                    throw new Exception("Impossible de faire un lookup sur la table de lookup '$table' : type de table non géré");
            }
            $prefix='T' . $this->schema->lookuptables[$key]->_id . ':';
        }

        // Teste s'il s'agit d'un alias
        elseif (isset($this->schema->aliases[$key]))
        {
            $helper=new AliasLookup();
            $prefix='';
            foreach($this->schema->aliases[$key]->indices as $name=>$index)
            {
                $index=$this->schema->indices[$name];
                if (reset($index->fields)->values)
                    $item=new ValueLookup();
                else
                    $item=new TermLookup();
                $prefix=$index->_id . ':';
                $item->setIterators($this->xapianDatabase->allterms_begin(), $this->xapianDatabase->allterms_end());
                $item->setMax($max);
                $item->setSortByFrequency($sort);
                $item->setPrefix($prefix);
                $item->setFormat($format);

                $helper->add($item);
            }

            $prefix=array();
            foreach($this->schema->aliases[$key]->indices as $index)
                $prefix[]=$index->_id . ':';
            // quel préfixe ?
        }

        // Teste s'il s'agit d'un index
        elseif (isset($this->schema->indices[$key]))
        {
            // Teste s'il s'agit d'un index "à l'article"

            // Remarque : on ne peut pas tester directement l'index, car chacun des
            // champs peut être indexé à l'article ou au mot. Du coup, on teste
            // uniquement le type d'indexation du premier champ et on suppose que
            // les autres champs de l'index sont indexés pareil.

            if (reset($this->schema->indices[$key]->fields)->values)
                $helper=new ValueLookup();
            else
                $helper=new TermLookup();
            $prefix=$this->schema->indices[$key]->_id . ':';
        }

        // Impossible de faire un lookup
        else
        {
            throw new Exception("Impossible de faire un lookup sur '$table' : ce n'est ni une table de lookup, ni un alias, ni un index");
        }

        // Paramétre le helper
        $helper->setIterators($this->xapianDatabase->allterms_begin(), $this->xapianDatabase->allterms_end());
        $helper->setMax($max);
        $helper->setSortByFrequency($sort);
        $helper->setPrefix($prefix);
        $helper->setFormat($format);
echo 'Helper de type ', get_class($helper), '<br />';
        // Fait le lookup et retourne les résultats
        return $helper->lookup($term);
    }


    /**
     * Recherche les tokens de la base qui commencent par le terme indiqué.
     *
     * Cette méthode est similaire à lookup, mais recherche parmi les termes
     * d'indexation et non pas parmi les tables de lookup.
     *
     * @param string $term le terme recherché
     *
     * @param int $max le nombre maximum de valeurs à retourner (0=pas de limite)
     *
     * @param int $sort l'ordre de tri souhaité pour les réponses :
     *   - 0 : trie les réponses par nombre décroissant d'occurences dans la base (valeur par défaut)
     *   - 1 : trie les réponses par ordre alphabétique croissant
     *
     * @return array
     */
    public function lookupTerm($term, $max=0, $sort=0)
    {
        static $charFroms=
            "\x00\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0a\x0b\x0c\x0d\x0e\x0f\x10\x11\x12\x13\x14\x15\x16\x17\x18\x19\x1a\x1b\x1c\x1d\x1e\x1f\x20\x21\x22\x23\x24\x25\x26\x27\x28\x29\x2a\x2b\x2c\x2d\x2e\x2f\x30\x31\x32\x33\x34\x35\x36\x37\x38\x39\x3a\x3b\x3c\x3d\x3e\x3f\x40\x41\x42\x43\x44\x45\x46\x47\x48\x49\x4a\x4b\x4c\x4d\x4e\x4f\x50\x51\x52\x53\x54\x55\x56\x57\x58\x59\x5a\x5b\x5c\x5d\x5e\x5f\x60\x61\x62\x63\x64\x65\x66\x67\x68\x69\x6a\x6b\x6c\x6d\x6e\x6f\x70\x71\x72\x73\x74\x75\x76\x77\x78\x79\x7a\x7b\x7c\x7d\x7e\x7f\x80\x81\x82\x83\x84\x85\x86\x87\x88\x89\x8a\x8b\x8c\x8d\x8e\x8f\x90\x91\x92\x93\x94\x95\x96\x97\x98\x99\x9a\x9b\x9c\x9d\x9e\x9f\xa0\xa1\xa2\xa3\xa4\xa5\xa6\xa7\xa8\xa9\xaa\xab\xac\xad\xae\xaf\xb0\xb1\xb2\xb3\xb4\xb5\xb6\xb7\xb8\xb9\xba\xbb\xbc\xbd\xbe\xbf\xc0\xc1\xc2\xc3\xc4\xc5\xc6\xc7\xc8\xc9\xca\xcb\xcc\xcd\xce\xcf\xd0\xd1\xd2\xd3\xd4\xd5\xd6\xd7\xd8\xd9\xda\xdb\xdc\xdd\xde\xdf\xe0\xe1\xe2\xe3\xe4\xe5\xe6\xe7\xe8\xe9\xea\xeb\xec\xed\xee\xef\xf0\xf1\xf2\xf3\xf4\xf5\xf6\xf7\xf8\xf9\xfa\xfb\xfc\xfd\xfe\xff";

        static $charTo=
            '                                                0123456789      @abcdefghijklmnopqrstuvwxyz      abcdefghijklmnopqrstuvwxyz                                                                     aaaaaaaceeeeiiiidnooooo 0uuuuy saaaaaaaceeeeiiiidnooooo  uuuuyby';

        $begin=$this->xapianDatabase->allterms_begin();
        $end=$this->xapianDatabase->allterms_end();

        $count=0;
        if ($max<=0) $max=PHP_INT_MAX;

        $result=array();

        $term=strtr(trim($term), $charFroms, $charTo);
        if (false === $token=strtok($term, ' '))
            $token=''; // terme vide : retourne les n premiers de la liste

        $nb=0;
        while($token !== false)
        {
            $start=$token;
            $begin->skip_to($start);

            while (!$begin->equals($end))
            {
                $entry=$begin->get_term();

                if ($start !== substr($entry, 0, strlen($start)))
                    break;

                if (!isset($result[$entry]))
                {
                    $result[$entry]=$begin->get_termfreq();
                    ++$nb;
                    if ( ($sort && $nb >= $max) or (! $sort && $nb>=1000))
                        break;
                }

                $begin->next();
            }
            $token=strtok(' ');
        }

        // Trie des réponses
        if ($sort===0)
        {
            arsort($result, SORT_NUMERIC);
            $result=array_slice($result,0,$max);
        }

        return $result;
    }

    public function dumpTerms($start='', $max=0)
        {
        $begin=$this->xapianDatabase->allterms_begin();
        $end=$this->xapianDatabase->allterms_end();
        $begin->skip_to($start);
        $count=0;
        if ($max<=0) $max=PHP_INT_MAX;
        while (!$begin->equals($end))
        {
            if ($count >= $max) break;
            $term=$begin->get_term();
            if (substr($term, 0, strlen($start))!=$start) break;
//            if(strlen($term)=='' || $term[0]!=='T' && trim(strtr($term, 'abcdefghijklmnopqrstuvwxyz0123456789_:', '                                      '))!=='')
//            {
                echo '<li>[', $term, '], len=', strlen($term), ', freq=', $begin->get_termfreq(), '</li>', "\n";
                $count++;
//            }
            $begin->next();
        }
        echo '<strong>', $count, ' termes</strong>';
    }

    public function totalCount()
    {
        return $this->xapianDatabase->get_doccount();
    }
    public function lastDocId()
    {
        return $this->xapianDatabase->get_lastdocid();
    }
    public function averageLength()
    {
        return $this->xapianDatabase->get_avlength();
    }

    public function deleteAllRecords()
    {
        $enquire=new XapianEnquire($this->xapianDatabase);
        $enquire->set_query(new XapianQuery(''));
        $enquire->set_docid_Order(XapianEnquire::ASCENDING);

        $mset=$enquire->get_MSet(0, -1);

        $i=0;
        $iterator=$mset->begin();
        while (! $iterator->equals($mset->end()))
        {
            $id=$iterator->get_docid();
            $this->xapianDatabase->delete_document($iterator->get_docid());
            if (debug) echo ++$i, ': doc ', $id, ' supprimé<br />';
            $iterator->next();
        }
    }

    public function reindex()
    {
        $startTime=microtime(true);

        // Vérifie que la base est ouverte en écriture
        if (! $this->xapianDatabase instanceof XapianWritableDatabase)
            throw new Exception('Impossible de réindexer une base de données ouverte en lecture seule.');

        // Mémorise le path actuel de la base
        $path=$this->getPath();

        echo '<h1>Réindexation complète de la base ', basename($path), '</h1>';

        // Sélectionne toutes les notices
        $this->search(null, array('_sort'=>'+', '_max'=>-1));
        $count=$this->count();
        if ($count==0)
        {
            echo '<p>La base ne contient aucun document, il est inutile de lancer la réindexation.</p>';
            return;
        }
        echo '<ol>';

        echo '<li>La base contient ', $count, ' notices.</li>';

        // Si une base 'tmp' existe déjà, on le signale et on s'arrête
        echo '<li>Création de la base de données temporaire...</li>';
        $pathTmp=$path.DIRECTORY_SEPARATOR.'tmp';
        if (file_exists($pathTmp) && count(glob($pathTmp . DIRECTORY_SEPARATOR . '*'))!==0)
            throw new Exception("Le répertoire $pathTmp contient déjà des données (réindexation précédente interrompue ?). Examinez et videz ce répertoire puis relancez la réindexation.");

        // Crée la nouvelle base dans './tmp'
        $tmp=Database::create($pathTmp, $this->getSchema(), 'xapian');

        // Crée le répertoire 'old' s'il n'existe pas déjà
        $pathOld=$path.DIRECTORY_SEPARATOR.'old';
        if (! is_dir($pathOld))
        {
            if (! @mkdir($pathOld))
                throw new Exception('Impossible de créer le répertoire ' . $pathOld);
        }

        // Données collectées pour le graphique
        $width=560;
        $data=array();
        $step=ceil($this->count() / ($width*1/4)); // on prendra une mesure toute les step notices

        // Recopie les notices
        echo '<li>Réindexation des notices...</li>';
        $last=$start=microtime(true);
        $i=0;
        foreach ($this as $record)
        {
            $tmp->addRecord();
            foreach($record as $field=>$value)
            {
                $tmp[$field]=$value;
            }
            $tmp->saveRecord();

            $id=$this->xapianMSetIterator->get_docid();
            $time=microtime(true);
            if (($time-$start)>1)
            {
                TaskManager::progress($i, $count);
                $start=$time;
            }

            if (0 === $i % $step)
            {
                if ($i>2)
                {
                    $data[$i]=round($step/($time-$last),0);
                }
                $last=$time;
            }

            $i++;
        }
        TaskManager::progress($i, $count);

        // + copier les spellings, les synonyms, les meta keys

        // Ferme la base temporaire
        echo '<li>Flush et fermeture de la base temporaire...</li>';
        $tmp=null;

        if (($i % $step)>0) $data[$i]=round(($i%$step)/(microtime(true)-$last),0);

        // Ferme la base actuelle en mettant à 'null' toutes les propriétés de $this
        echo '<li>Fermeture de la base actuelle...</li>';

        $me=new ReflectionObject($this);
        foreach((array)$me->getProperties() as $prop)
        {
            $prop=$prop->name;
            $this->$prop=null;
        }

        /*
            On va maintenant remplacer les fichiers de la base existante par
            les fichiers de la base temporaire.

            Potentiellement, il se peut que quelqu'un essaie d'ouvrir la base
            entre le moment où on commence le transfert et le moment où tout
            est transféré.

            Pour éviter ça, on procède en deux étapes :
            1. on déplace vers le répertoire ./old tous les fichiers de la base
               existante, en commençant par le fichier de version (iamflint), ce
               qui fait que plus personne ne peut ouvrir la base dès que
               celui-ci a été renommé ;
            2. on transfère tous les fichier de ./tmp en ordre inverse,
               c'est-à-dire en terminant par le fichier de version, ce qui fait
               que personne ne peut ouvrir la base tant qu'on n'a pas fini.
        */

        // Liste des fichiers pouvant être créés pour une base flint
        $files=array
        (
            // les fichiers de version doivent être en premier
            'iamflint',
            'iamchert',
            'uuid', // replication stuff

            // autres fichiers
            'flintlock',

            'position.baseA',
            'position.baseB',
            'position.DB',

            'postlist.baseA',
            'postlist.baseB',
            'postlist.DB',

            'record.baseA',
            'record.baseB',
            'record.DB',

            'spelling.baseA',
            'spelling.baseB',
            'spelling.DB',

            'synonym.baseA',
            'synonym.baseB',
            'synonym.DB',

            'termlist.baseA',
            'termlist.baseB',
            'termlist.DB',

            'value.baseA',
            'value.baseB',
            'value.DB',
        );


        // Transfère tous les fichiers existants vers le répertoire ./old
        clearstatcache();
        echo '<li>Transfert de la base actuelle dans le répertoire "old"...</li>';
        foreach($files as $file)
        {
            $old=$pathOld . DIRECTORY_SEPARATOR . $file;
            if (file_exists($old))
            {
                unlink($old);
            }

            $h=$path . DIRECTORY_SEPARATOR . $file;
            if (file_exists($h))
            {
                rename($h, $old);
            }
        }

        // Transfère les fichiers du répertoire tmp dans le répertoire de la base
        echo '<li>Installation de la base temporaire comme base actuelle...</li>';
        foreach(array_reverse($files) as $file)
        {
            $h=$path . DIRECTORY_SEPARATOR . $file;

            $tmp=$pathTmp . DIRECTORY_SEPARATOR . $file;
            if (file_exists($tmp))
            {
                //echo "Déplacement de $tmp vers $h<br />";
                rename($tmp, $h);
            }
        }

        // Essaie de supprimer le répertoire tmp (désormais vide)
        $files=glob($pathTmp . DIRECTORY_SEPARATOR . '*');
        if (count($files)!==0)
            echo '<li><strong>Warning : il reste des fichiers dans le répertoire tmp</strong></li>';

        // todo: en fait on n'arrive jamais à supprimer tmp. xapian garde un handle dessus ? à voir, pas indispensable de supprimer tmp
        /*
            if (!@unlink($pathTmp))
                echo '<p>Warning : impossible de supprimer ', $pathTmp, '</p>';
        */

        // Réouvre la base
        echo '<li>Ré-ouverture de la base...</li>';
        $this->doOpen($path, false);
        $this->search(null, array('_sort'=>'+', '_max'=>-1));

        echo '<li>La réindexation est terminée.</li>';
        echo '<li>Statistiques :';

        // Génère un graphique
        $type='lc';        // type de graphe
        $size=$width.'x300';    // Taille du graphe (largeur x hauteur)
        $title=utf8_encode('Nombre de notices réindexées par seconde');
        $grid='5,5,1,5';  // largeur, hauteur, taille trait, taille blanc
        $xrange=min(array_keys($data)) . ',' . max(array_keys($data));

        $min=min($data);
        $max=max($data);
        $average=array_sum($data)/count($data);
        $yrange=$min . ',' . $max;

        $ratio=($max-$min)/100;
        foreach($data as &$val)
            $val=round(($val-$min)/$ratio, 0);

        $data='t:' . implode(',',$data);

        $avg01=($average-$min)/($max-$min);
        $src=sprintf
        (
            'http://chart.apis.google.com/chart?cht=%s&chs=%s&chd=%s&chtt=%s&chg=%s&chxt=x,y,x&chxr=0,%s|1,%s&chxl=2:||taille de la base&chm=r,220000,0,%.3F,%.3F',
            $type,
            $size,
            $data,
            $title,
            $grid,
            $xrange,
            $yrange,
            $avg01,
            $avg01-0.001
        );

        echo '<p><img style="border: 4px solid black; background-color: #fff; padding: 1em; margin: auto;" src="'.$src.'" /></p>';
        echo sprintf('<p>Minimum : %d notices/seconde, Maximum : %d notices/seconde, Moyenne : %.3F notices/seconde', $min, $max, $average);
        echo '<p>Durée totale de la réindexation : ', Utils::friendlyElapsedTime(microtime(true)-$startTime), '.</p>';

        echo '</li></ol>';
    }

/*
    public function reindex()
    {
        while(ob_get_level()) ob_end_flush();
        $this->search(null, array('_sort'=>'+', '_max'=>40000));
        echo $this->count(), ' notices à réindexer. <br />';

        $start=microtime(true);
        $i=0;
        foreach ($this as $record)
        {
            $id=$this->xapianMSetIterator->get_docid();
            if (0 === $i % 1000)
            {
                echo sprintf('%.2f', microtime(true)-$start), ', i=', $i, ', id=', $id, '<br />';
                flush();
            }

            $this->xapianDocument->clear_terms();
            $this->xapianDocument->clear_values();

            // Remplace le document existant
            $this->xapianDatabase->replace_document($id, $this->xapianDocument);

            $i++;
        }

        //
        echo sprintf('%.2f', microtime(true)-$start), ', i=', $i, ', id=', $id, ', flush de la base...<br />';
        flush();

        $this->xapianDatabase->flush();
        echo sprintf('%.2f', microtime(true)-$start), ', terminé !<br />';
        flush();
    }

    public function reindexOld()
    {
        while(ob_get_level()) ob_end_flush();
        $this->search(null, array('_sort'=>'+', '_max'=>20000));
        echo $this->count(), ' notices à réindexer. <br />';

        $start=microtime(true);
//        $this->xapianDatabase->begin_transaction(false);
        $i=0;
        foreach ($this as $record)
        {
            $id=$this->xapianMSetIterator->get_docid();
            if (0 === $i % 1000)
            {
                echo sprintf('%.2f', microtime(true)-$start), ', i=', $i, ', id=', $id, '<br />';
                flush();
            }

            // Ré-indexe l'enregistrement
            $this->initializeDocument();

            // Remplace le document existant
            $this->xapianDatabase->replace_document($id, $this->xapianDocument);

            $i++;
        }
//        $this->xapianDatabase->commit_transaction();

        //
        echo sprintf('%.2f', microtime(true)-$start), ', i=', $i, ', id=', $id, ', flush de la base...<br />';
        flush();

        $this->xapianDatabase->flush();
        echo sprintf('%.2f', microtime(true)-$start), ', terminé !<br />';
        flush();
    }
*/

    public function warmUp()
    {
        $begin=$this->xapianDatabase->allterms_begin();
        $end=$this->xapianDatabase->allterms_end();
//        echo 'Premier terme : ', $term=$begin->get_term(), '<br />';
//        die($term);
//        $begin->skip_to('zzzzzz');
//        echo $begin->get_description();
//        //echo 'Premier terme : ', $begin->get_term(), '<br />';
//        //echo 'Dernier terme : ', $end->get_term(), '<br />';
//        die('here');
//         return;
        while (!$begin->equals($end))
        {
            $term=$begin->get_term();
            echo $term, '<br />';
            $this->search($term);
            echo $this->count(), '<br />';
            $term[0]=chr(1+ord($term[0]));
            $begin->skip_to($term);
        }
    }
}

/**
 * Représente un enregistrement dans une base {@link XapianDatabase}
 *
 * @package     fab
 * @subpackage  database
 */
class XapianDatabaseRecord extends DatabaseRecord
{
    /**
     * @var Array Liste des champs de cet enregistrement
     */
    private $fields=null;

    private $schema=null;

    /**
     * Lors d'un parcours séquentiel, numéro de l'élément de tableau
     * en cours. Utilisé par {@link next()} pour savoir si on a atteint
     * la fin du tableau.
     *
     * @var unknown_type
     */
    private $current=0;

    /**
     * {@inheritdoc}
     *
     * @param Array $fields la liste des champs de la base
     */
    public function __construct(& $fields, DatabaseSchema $schema)
    {
        $this->fields= & $fields;
        $this->schema= $schema;
    }

    /* <ArrayAccess> */

    public function offsetSet($offset, $value)
    {

        // Version minu non accentuée du nom du champ
        $key=Utils::ConvertString($offset, 'alphanum');
        $this->fields[$key]=$value;
        return;
        // Vérifie que le champ existe
        if (! array_key_exists($key, $this->fields))
            throw new DatabaseFieldNotFoundException($offset);

        // Vérifie que la valeur concorde avec le type du champ
        switch ($this->schema->fields[$key]->_type)
        {
            case DatabaseSchema::FIELD_AUTONUMBER:
            case DatabaseSchema::FIELD_INT:
                /*
                 * Valeurs stockées telles quelles :
                 *      null -> null
                 *      12 -> 12
                 * Valeurs converties : (par commodité, exemple, import fichier texte)
                 *      '' -> null
                 *      '12' -> 12
                 * Erreurs :
                 *      '12abc' -> exception            pas de tolérance si on caste une chaine
                 *      true, false -> exception        un booleéen n'est pas un entier
                 *      autres -> exception
                 */
                if (is_null($value) || is_int($value)) break;
                if (is_string($value) && ctype_digit($value))
                {
                    $value=(int)$value;
                    break;
                }
                if ($value==='')
                {
                    $value=null;
                    break;
                }
                throw new DatabaseFieldTypeMismatch($offset, $this->schema->fields[$key]->type, $value);

            case DatabaseSchema::FIELD_BOOL:
                /*
                 * Valeurs stockées telles quelles :
                 *      null -> null
                 *      true -> true
                 *      false -> false
                 * Valeurs converties : (par commodité, exemple, import fichier texte)
                 *      0 -> false
                 *      1 -> true
                 *      '1','true','vrai','on' -> true
                 *      '','0','false','faux','off' -> false
                 * Erreurs :
                 *      'xxx' -> exception       toute autre chaine (y compris vide ou espace) est une erreur
                 *      3, -1-> exception        tout autre entier est une erreur
                 *      autres -> exception
                 */
                if (is_null($value) || is_bool($value)) break;
                if (is_int($value))
                {
                    if ($value===0 | $value===1)
                    {
                        $value=(bool) $value;
                        break;
                    }
                    throw new DatabaseFieldTypeMismatch($offset, $this->schema->fields[$key]->type, $value);
                }
                if (is_string($value))
                {
                    switch(strtolower(trim($value)))
                    {
                        case 'true':
                        case 'vrai':
                        case 'on':
                        case '1':
                            $value=true;
                            break 2;

                        case 'false':
                        case 'faux':
                        case 'off':
                        case '0':
                            $value=false;
                            break 2;
                        case '':
                            $value=null;
                            break 2;
                    }
                }
                throw new DatabaseFieldTypeMismatch($offset, $this->schema->fields[$key]->type, $value);

            case DatabaseSchema::FIELD_TEXT:
                if (is_null($value) || is_string($value)) break;
                if (is_scalar($value))
                {
                    $value=(string)$value;
                    if ($value==='')
                    {
                        $value=null;
                        break;
                    }
                }
                throw new DatabaseFieldTypeMismatch($offset, $this->schema->fields[$key]->type, $value);
                break;
        }

        // Stocke la valeur du champ
        $this->fields[$key]=$value;
    }

    public function offsetGet($offset)
    {
        return $this->fields[Utils::ConvertString($offset, 'alphanum')];
    }

    public function offsetUnset($offset)
    {
        unset($this->fields[Utils::ConvertString($offset, 'alphanum')]);
    }

    public function offsetExists($offset)
    {
        return isset($this->fields[Utils::ConvertString($offset, 'alphanum')]);
    }

    /* </ArrayAccess> */


    /* <Countable> */

    public function count()
    {
        return $this->fields->count;
    }

    /* </Countable> */


    /* <Iterator> */

    public function rewind()
    {
        reset($this->fields);
        $this->current=1;
    }

    public function current()
    {
        return current($this->fields);
    }

    public function key()
    {
        return $this->schema->fields[key($this->fields)]->name;
        /*
         * On ne retourne pas directement key(fields) car sinon on récupère
         * un nom en minu sans accents qui sera ensuite utilisé dans les boucles,
         * les callbacks, etc.
         * Si un callback a le moindre test du style if($name='Aut'), cela ne marchera
         * plus.
         * On fait donc une indirection pour retourner comme clé le nom exact du
         * champ tel que saisi par l'utilisateur dans le schéma.
         */
    }

    public function next()
    {
        next($this->fields);
        $this->current++;
    }

    public function valid()
    {
        return $this->current<=count($this->fields);
    }

    /* </Iterator> */

}

/**
 * Exception générée lorsqu'on essaie de modifier une base de données
 * ouverte en lecture seule
 *
 * @package     fab
 * @subpackage  database
 */
class DatabaseReadOnlyException extends LogicException
{
    public function __construct()
    {
        parent::__construct('La base est en ouverte en lecture seule.');
    }
}

/**
 * Exception générée lorsqu'on essaie d'accèder à un enregistrement alors
 * qu'il n'y a aucun enregistrement en cours.
 *
 * @package     fab
 * @subpackage  database
 */
class DatabaseNoRecordException extends LogicException
{
    public function __construct()
    {
        parent::__construct('Pas d\'enregistrement courant.');
    }
}

/**
 * Exception générée lorsqu'on essaie d'accéder à un champ qui n'existe pas
 * dans la base
 *
 * @package     fab
 * @subpackage  database
 */
class DatabaseFieldNotFoundException extends InvalidArgumentException
{
    public function __construct($field)
    {
        parent::__construct(sprintf('Le champ %s n\'existe pas.', $field));
    }
}

/**
 * Exception générée par {@link saveRecord()} et {@link cancelUpdate()} si elles
 * sont appellées alors que l'enregistrement actuel n'est pas en cours de
 * modification.
 *
 * @package     fab
 * @subpackage  database
 */
class DatabaseNotEditingException extends LogicException
{
    public function __construct()
    {
        parent::__construct('L\'enregistrement n\'est pas en cours de modification');
    }
}

/**
 * Exception générée lorsqu'on essaie de stocker dans un champ une valeur qui
 * n'est pas compatible avec le type du champ.
 *
 * @package     fab
 * @subpackage  database
 */
class DatabaseFieldTypeMismatch extends RuntimeException
{
    public function __construct($field, $type, $value)
    {
        parent::__construct(sprintf('Valeur incorrecte pour le champ %s (%s) : %s', $field, $type, var_export($value, true)));
    }
}
?>