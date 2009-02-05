<?php
/**
 * @package     fab
 * @subpackage  database
 * @author      Daniel M�nard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id$
 */

/**
 * Un driver de base de donn�es pour fab utilisant une base Xapian pour
 * l'indexation et le stockage des donn�es
 *
 * @package     fab
 * @subpackage  database
 */
class XapianDatabaseDriver extends Database
{
    /**
     * Le sch�ma de la base de donn�es (cf {@link getSchema()}).
     *
     * @var DatabaseSchema
     */
    private $schema=null;


    /**
     * Tableau interne indiquant, pour chaque champ de type 'AutoNumber' le nom
     * de la cl� metadata utilis�e pour stocker le dernier num�ro utilis�.
     *
     * Les cl�s du tableau sont les noms (minu sans accents) des champs de type
     * AutoNumber. La valeur est une chaine de la forme 'fab_autonumber_ID' ou
     * 'ID' est l'identifiant du champ.
     *
     * Ce tableau est initialis� dans InitDatabase() et n'est utilis� que par
     * saveRecord()
     *
     * @var {array(string)}
     */
    private $autoNumberFields=array();


    /**
     * Permet un acc�s � la valeur d'un champ dont on conna�t l'id
     *
     * Pour chaque champ (name,id), fieldById[id] contient une r�f�rence
     * vers fields[name] (ie modifier fields[i] ou fieldsById[i] changent la
     * m�me variable)
     *
     * @var Array
     */
    private $fieldById=array();


    /**
     * L'objet XapianDatabase retourn� par xapian apr�s ouverture ou cr�ation
     * de la base.
     *
     * @var XapianDatabase
     */
    private $xapianDatabase=null;


    /**
     * L'objet XapianDocument contenant les donn�es de l'enregistrement en
     * cours ou null s'il n'y a pas d'enregistrement courant.
     *
     * @var XapianDocument
     */
    private $xapianDocument=null;


    /**
     * Un flag indiquant si on est en train de modifier l'enregistrement en
     * cours ou non  :
     *
     * - 0 : l'enregistrement courant n'est pas en cours d'�dition
     *
     * - 1 : un nouvel enregistrement est en cours de cr�ation
     * ({@link addRecord()} a �t� appell�e)
     *
     * - 2 : l'enregistrement courant est en cours de modification
     * ({@link editRecord()} a �t� appell�e)
     *
     * @var int
     */
    private $editMode=0;


    /**
     * Un tableau contenant la valeur de chacun des champs de l'enregistrement
     * en cours.
     *
     * Ce tableau est pass� � l'objet {@link XapianDatabaseRecord} que l'on cr�e
     * lors de l'ouverture de la base.
     *
     * @var Array
     */
    private $fields=array();


    /**
     * L'objet XapianEnquire repr�sentant l'environnement de recherche.
     *
     * Vaut null tant que {@link search()} n'a pas �t� appell�e.
     *
     * @var XapianEnquire
     */
    private $xapianEnquire=null;


    /**
     * L'objet XapianQueryParser utilis� pour analyser les �quations de recherche.
     *
     * Initialis� par {@link setupSearch()} et utilis� uniquement dans
     * {@link search()}
     *
     * @var XapianQueryParser
     */
    private $xapianQueryParser=null;


    /**
     * L'objet XapianMultiValueSorter utilis� pour r�aliser les tris multivalu�s.
     *
     * Initialis� par {@link setSortOrder()}.
     *
     * @var XapianMultiValueSorter
     */
    private $xapianSorter=null;

    /**
     * L'objet XapianMSet contenant les r�sultats de la recherche.
     *
     * Vaut null tant que {@link search()} n'a pas �t� appell�e.
     *
     * @var XapianMSet
     */
    private $xapianMSet=null;

    /**
     * L'objet XapianMSetIterator permettant de parcourir les r�ponses obtenues
     *
     * Vaut null tant que {@link search()} n'a pas �t� appell�e.
     *
     * @var XapianMSetIterator
     */
    private $xapianMSetIterator=null;

    /**
     * L'�quation de recherche en cours.
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
     * L'objet XapianQuery contenant l'�quation de recherche indiqu�e par
     * l'utilisateur (sans les filtres �ventuels appliqu�s).
     *
     * Vaut null tant que {@link search()} n'a pas �t� appell�e.
     *
     * Utilis� par {@link getQueryTerms()} pour retourner la liste des termes
     * composant la requ�te
     *
     * @var XapianQuery
     */
    private $xapianQuery=null;

    /**
     * L'objet XapianFilter contient la requ�te correspondant aux filtres
     * appliqu�s � la recherche
     *
     * Vaut null tant que {@link search()} n'a pas �t� appell�e.
     * Vaut null si aucun filtre n'a �t� sp�cifi�.
     *
     * @var XapianQuery
     */
    private $xapianFilter=null;

    /**
     * Libell� de l'ordre de tri utilis� lors de la recherche.
     *
     * Si plusieurs crit�res de tri ont �t� indiqu�s lors de la requ�te,
     * le libell� obtenu est une chaine listant toutes les cl�s (s�par�es
     * par des espaces).
     *
     * Exemples :
     * - 'type', 'date-', '%', '+', '-' pour une cl� de tri unique
     * - 'type date-', 'date- %' pour une cl� de tri composite
     *
     * @var string
     */
    private $sortOrder='';

    /**
     * Tableau contenant les num�ros des slots qui contiennent les valeurs
     * composant l'ordre de tri en cours.
     *
     * @var null|array
     */
    private $sortKey=array();


    /**
     * Op�rateur par d�faut utilis� par le queryparser
     *
     * @var null|int
     */
    private $defaultOp=null;

    /**
     * Indique si les op�rateurs bool�ens (and, or, et, ou...) sont reconnus
     * comme tels quelle que soit leur casse ou s'il ne doivent �tre reconnus
     * que lorsqu'il sont en majuscules.
     *
     * @var bool
     */
    private $opAnyCase=true;

    /**
     * Indique le nom de l'index "global" par d�faut, c'est-�-dire le nom de
     * l'index ou de l'alias qui sera utilis� si aucun nom de champ n'est
     * indiqu� dans la requ�te de l'utilisateur.
     *
     * @var null|string
     */
    private $defaultIndex=null;

    /**
     * Indique le num�ro d'ordre de la premi�re r�ponse retourn�e par la
     * recherche en cours.
     *
     * Initialis� par {@link search()} et utilis� par {@link searchInfo()}.
     *
     * @var int
     */
    private $start=0;


    /**
     * Indique le nombre maximum de r�ponses demand�es pour la recherche en
     * cours.
     *
     * Initialis� par {@link search()} et utilis� par {@link searchInfo()}.
     *
     * @var int
     */
    private $max=-1;

    /**
     * Une estimation du nombre de r�ponses obtenues pour la recherche en cours.
     *
     * @var int
     */
    private $count=0;

    /**
     * La version corrig�e par le correcteur orthographique de xapian de la
     * requ�te en cours.
     *
     * @var string
     */
    private $correctedEquation='';

    /**
     * MatchingSpy employ� pour cr�er les facettes de la recherche
     *
     * Exp�rimental (branche MatchSpy de Xapian), cf search().
     *
     * @var XapianMatchDecider
     */
    private $spy=null;

    /**
     * Retourne le sch�ma de la base de donn�es
     *
     * @return DatabaseSchema
     */
    public function getSchema()
    {
        return $this->schema;
    }

    // *************************************************************************
    // ***************** Cr�ation et ouverture de la base **********************
    // *************************************************************************

    /**
     * Cr�e une nouvelle base xapian
     *
     * @param string $path le path de la base � cr�er
     * @param DatabaseSchema $schema le sch�ma de la base � cr�er
     * @param array $options options �ventuelle, non utilis�
     */
    protected function doCreate($path, /* DS DatabaseSchema */ $schema, $options=null)
    {
        /* DS A ENLEVER */
        // V�rifie que le sch�ma de la base de donn�es est correcte
        if (true !== $t=$schema->validate())
            throw new Exception('Le sch�ma pass� en param�tre contient des erreurs : ' . implode('<br />', $t));

        // Compile le sch�ma
        $schema->compile();

        // Cr�e la base xapian
        $this->xapianDatabase=new XapianWritableDatabase($path, Xapian::DB_CREATE_OR_OVERWRITE); // todo: remettre � DB_CREATE
//        $this->xapianDatabase=Xapian::chert_open($path,Xapian::DB_CREATE_OR_OVERWRITE,8192);

        // Enregistre le schema dans la base
        $this->xapianDatabase->set_metadata('schema', $schema->toXml());
        $this->xapianDatabase->set_metadata('schema_object', serialize($schema));

        // Initialise les propri�t�s de l'objet
        $this->schema=$schema;
        $this->initDatabase(true);
    }

    /**
     * Modifie la structure d'une base de donn�es en lui appliquant le
     * sch�ma pass� en param�tre.
     *
     * La fonction se contente d'enregistrer le nouveau sch�ma dans
     * la base : selon les modifications apport�es, il peut �tre n�cessaire
     * ensuite de lancer une r�indexation compl�te (par exemple pour cr�er les
     * nouveaux index ou pour purger les champs qui ont �t� supprim�s).
     *
     * @param DatabaseSchema $newSchema le nouveau sch�ma de la base.
     */
    public function setSchema(DatabaseSchema $schema)
    {
        if (! $this->xapianDatabase instanceOf XapianWritableDatabase)
            throw new LogicException('Impossible de modifier le sch�ma d\'une base ouverte en lecture seule.');

        // V�rifie que le sch�ma de la base de donn�es est correct
        if (true !== $t=$schema->validate())
            throw new Exception('Le sch�ma pass� en param�tre contient des erreurs : ' . implode('<br />', $t));

        // Compile le sch�ma
        $schema->compile();

        // Enregistre le sch�ma dans la base
        $this->xapianDatabase->set_metadata('schema', $schema->toXml());
        $this->xapianDatabase->set_metadata('schema_object', serialize($schema));

        // Initialise les propri�t�s de l'objet
        $this->schema=$schema;
        $this->initDatabase(true);
    }


    /**
     * Ouvre une base Xapian
     *
     * @param string $path le path de la base � ouvrir.
     * @param bool $readOnly true pour ouvrir la base en lecture seule, false
     * pour l'ouvrir en mode lexture/�criture.
     */
    protected function doOpen($path, $readOnly=true)
    {
        // Ouverture de la base xapian en lecture
        if ($readOnly)
        {
            $this->xapianDatabase=new XapianDatabase($path);
        }

        // Ouverture de la base xapian en �criture
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
                    // comme l'exception DatabaseLockError de xapian n'est pas mapp�e en php
                    // on teste le d�but du message d'erreur pour d�terminer le type de l'exception
                    if (strpos($e->getMessage(), 'DatabaseLockError:')===0)
                    {
//                        echo 'la base est verrouill�e, essais effectu�s : ', $i, "<br />\n";

                        // Si on a fait plus de maxtries essais, on abandonne
                        if ($i>$maxtries) throw $e;

                        // Sinon, on attend un peu et on refait un essai
                        $wait=rand(1,9) * 10000;
//                        echo 'attente de ', $wait/10000, ' secondes<br />', "\n";
                        usleep($wait); // attend de 0.01 � 0.09 secondes
                        continue;
                    }

                    // Ce n'est pas une exception de type DatabaseLockError, on la propage
                    throw $e;
                }

                // on a r�ussi � ouvrir la base
                break;
            }
//            echo 'Base ouverte en �criture au bout de ', $i, ' essai(s). Temps total : ', (microtime(true)-$starttime), ' sec.<br />', "\n";
        }

        // Charge le sch�ma de la base
        $this->schema=unserialize($this->xapianDatabase->get_metadata('schema_object'));
        if (! $this->schema instanceof DatabaseSchema)
            throw new Exception("Impossible d'ouvrir la base, sch�ma non g�r�");

        // Initialise les propri�t�s de l'objet
        $this->initDatabase($readOnly);
    }


    /**
     * Initialise les propri�t�s de la base
     *
     * @param bool $readOnly
     */
    private function initDatabase($readOnly=true)
    {
        // Cr�e le tableau qui contiendra la valeur des champs
        $this->fields=array_fill_keys(array_keys($this->schema->fields), null);

        // Cr�e l'objet DatabaseRecord
        $this->record=new XapianDatabaseRecord($this->fields, $this->schema);

        foreach($this->schema->fields as $name=>$field)
            $this->fieldById[$field->_id]=& $this->fields[$name];

        foreach($this->schema->indices as $name=>&$index) // fixme:
            $this->indexById[$index->_id]=& $index;

        foreach($this->schema->lookuptables as $name=>&$lookuptable) // fixme:
            $this->lookuptableById[$lookuptable->_id]=& $lookuptable;

        // Les propri�t�s qui suivent ne sont initialis�es que pour une base en lecture/�criture
//        if ($readOnly) return;

        // Mots vides de la base
        $this->schema->_stopwords=array_flip(Utils::tokenize($this->schema->stopwords));

        // Cr�e la liste des champs de type AutoNumber + mots-vides des champs
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
     * Initialise la cr�ation d'un nouvel enregistrement
     *
     * L'enregistrement ne sera effectivement cr�� que lorsque {@link update()}
     * sera appell�.
     *
     * @throws DatabaseReadOnlyException si la base est ouverte en lecture seule
     */
    public function addRecord()
    {
        // V�rifie que la base n'est pas en lecture seule
        if (! $this->xapianDatabase instanceof XapianWritableDatabase)
            throw new DatabaseReadOnlyException();

        // R�initialise tous les champs � leur valeur par d�faut
        foreach($this->fields as $name=>&$value)
            $value=null;

        // R�initialise le document xapian en cours
        $this->xapianDocument=new XapianDocument();

        // M�morise qu'on a une �dition en cours
        $this->editMode=1;
    }


    /**
     * Initialise la modification d'un enregistrement existant.
     *
     * L'enregistrement  ne sera effectivement modifi� que lorsque {@link update}
     * sera appell�.
     *
     * @throws DatabaseReadOnlyException si la base est ouverte en lecture seule
     * @throws DatabaseNoRecordException s'il n'y a pas d'enregistrement courant
     */
    public function editRecord()
    {
        // V�rifie que la base n'est pas en lecture seule
        if (! $this->xapianDatabase instanceof XapianWritableDatabase)
            throw new DatabaseReadOnlyException();

        // V�rifie qu'on a un enregistrement courant
        if (is_null($this->xapianDocument))
            throw new DatabaseNoRecordException();

        // M�morise qu'on a une �dition en cours
        $this->editMode=2;
    }


    /**
     * Sauvegarde l'enregistrement en cours.
     *
     * @throws DatabaseNotEditingException si l'enregistrement courant n'est pas
     * en cours de modification, c'est-�-dire si on appelle saveRecord() sans
     * avoir appell� {@link addRecord()} ou {@link editRecord()} auparavant.
     *
     * @return int l'identifiant (docid) de l'enregistrement cr�� ou modifi�
     */
    public function saveRecord()
    {
        // V�rifie qu'on a une �dition en cours
        if ($this->editMode === 0)
            throw new DatabaseNotEditingException();

        // Affecte une valeur aux champs AutoNumber qui n'en n'ont pas
        foreach($this->autoNumberFields as $name=>$key)
        {
            // Si le champ autonumber n'a pas de valeur, on lui en donne une
            if (! $this->fields[$name]) // null ou 0 ou '' ou false
            {
                // get_metadata retourne '' si la cl� n'existe pas. Valeur initiale=1+(int)''=1
                $value=1+(int)$this->xapianDatabase->get_metadata($key);
                $this->fields[$name]=$value;
                $this->xapianDatabase->set_metadata($key, $value);
            }

            // Sinon, si la valeur indiqu�e est sup�rieure au compteur, on met � jour le compteur
            else
            {
                $value=(int)$this->fields[$name];
                if ($value>(int)$this->xapianDatabase->get_metadata($key))
                    $this->xapianDatabase->set_metadata($key, $value);
            }
        }

        // Indexe l'enregistrement
        $this->initializeDocument();

        // Ajoute un nouveau document si on est en train de cr�er un enreg
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

        // Edition termin�e
        $this->editMode=0;
//        pre($this->schema);
//        die('here');

        // Retourne le docid du document cr�� ou modifi�
        return $docId;
    }


    /**
     * Annule l'�dition de l'enregistrement en cours.
     *
     * @throws DatabaseNotEditingException si l'enregistrement courant n'est pas
     * en cours de modification, c'est-�-dire si on appelle saveRecord() sans
     * avoir appell� {@link addRecord()} ou {@link editRecord()} auparavant.
     */
    public function cancelUpdate()
    {
        // V�rifie qu'on a une �dition en cours
        if ($this->editMode == 0)
            throw new DatabaseNotEditingException();

        // Recharge le document original pour annuler les �ventuelles modifications apport�es
        $this->loadDocument();

        // Edition termin�e
        $this->editMode=0;
    }


    /**
     * Supprime l'enregistrement en cours
     *
     */
    public function deleteRecord()
    {
        // V�rifie que la base n'est pas en lecture seule
        if (! $this->xapianDatabase instanceof XapianWritableDatabase)
            throw new ReadOnlyDatabaseException();

        // Interdiction d'appeller deleteRecord() juste apr�s addRecord()
        if ($this->editMode == 1)
            throw new LogicException("Appel de deleteRecord() apr�s un appel � addRecord()");

        // Supprime l'enregistrement
        $docId=$this->xapianMSetIterator->get_docid();
        $this->xapianDatabase->delete_document($docId);
    }


    // *************************************************************************
    // *************************** Indexation **********************************
    // *************************************************************************


    /**
     * Retourne un extrait de chaine d�limit�s par des positions ou des chaines
     * de d�but et de fin.
     *
     * Start et End repr�sente les positions de d�but et de fin de la chaine �
     * obtenir. Chacun des deux peut �tre soit un entier soit une chaine.
     * Entier positif = position � partir du d�but
     * Entier n�gatif = position depuis la fin
     *
     * @todo compl�ter la doc
     *
     * @param string $value
     * @param int|string $start
     * @param int|string $end
     * @return string
     */
    private function startEnd($value, $start, $end=null)
    {
        if (is_int($start) && is_int($end) && (($start>0 && $end>0) || ($start<0 && $end<0)) && ($start > $end))
            throw new InvalidArgumentException('Si start et end sont des entiers de m�me signe, start doit �tre inf�rieur � end');

        // On ignore les espaces de d�but : si on a "    AAAAMMJJ", (0,3) doit retourner AAAA, pas les espaces
        $value=ltrim($value);

        if (is_int($start))
        {
            if ($start) // 0 = prendre tout
            {
                // start > 0 : on veut � partir du i�me caract�re, -1 pour php
                if ($start > 0)
                {
                    if (is_int($end) && $end>0 ) $end -= $start-1;
                    if (false === $value=substr($value, $start-1)) return '';
                }

                // start < 0 : on veut les i derniers caract�res
                elseif (strlen($value)>-$start)
                    $value=substr($value, $start);
            }
        }
        elseif($start !=='')
        {
            $pt=stripos($value, $start); // insensible � la casse mais pas aux accents
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
     * @param string $term le terme � ajout�
     * @param string $prefix le pr�fixe � ajouter au terme
     * @param int $weight le poids du terme
     * @param null|int $position null : le terme est ajout� sans position,
     * int : le terme est ajout� avec la position indiqu�e
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
     * Initialise le document xapian en cours lors de la cr�ation ou de la
     * modification d'un enregistrement.
     *
     */
    private function initializeDocument()
    {
        // On ne stocke dans doc.data que les champs non null
        $data=array_filter($this->fieldById, 'count'); // Supprime les valeurs null et array()

        // Stocke les donn�es de l'enregistrement
        $this->xapianDocument->set_data(serialize($data));

        // Supprime tous les tokens existants
        $this->xapianDocument->clear_terms();

        // Met � jour chacun des index
        $position=0;
        foreach ($this->schema->indices as $index)
        {
            // D�termine le pr�fixe � utiliser pour cet index
            $prefix=$index->_id.':';

            // Pour chaque champ de l'index, on ajoute les tokens du champ dans l'index
            foreach ($index->fields as $name=>$field)
            {
                // Traite tous les champs comme des champs articles
                $data=(array) $this->fields[$name];

                // Initialise la liste des mots-vides � utiliser
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

                    // Si c'est juste un index de type "count", on n'a rien d'autre � faire
                    if (! $field->words && ! $field->values) continue;

                    // Indexation au mot et � la phrase
                    $tokens=Utils::tokenize($value);
                    foreach($tokens as $term)
                    {
                        // V�rifie que la longueur du terme est dans les limites autoris�es
                        if (strlen($term)<self::MIN_TERM or strlen($term)>self::MAX_TERM) continue;

                        // V�rifie que ce n'est pas un mot-vide
                        if (! self::INDEX_STOP_WORDS && isset($stopwords[$term])) continue;

                        // Ajoute le terme dans le document
                        $this->addTerm($term, $prefix, $field->weight, $field->phrases?$position:null);

                        // Correcteur orthographique
                        if (isset($index->spelling) && $index->spelling)
                            $this->xapianDatabase->add_spelling($term); // todo: � �tudier, stocker forme riche pour r�utilisation dans les lookup terms ?

                        // Incr�mente la position du terme en cours
                        $position++;
                    }

                    // Indexation � l'article
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
            // D�termine le pr�fixe � utiliser pour cette table
            $prefix='T'.$lookupTable->_id.':';

            // Parcourt tous les champs qui alimentent cette table
            foreach($lookupTable->fields as $name=>$field)
            {
                // Traite tous les champs comme des champs articles
                $data=(array) $this->fields[$name];
                $data=array_slice($data, $field->startvalue-1, $field->endvalue===0 ? null : ($field->endvalue));

                // Initialise la liste des mots-vides � utiliser
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

                    // Table de lookup de type invers�e
                    else
                    {
                        // Tokenise et ajoute une entr�e dans la table pour chaque terme obtenu
                        foreach(Utils::tokenize($value) as $term)
                        {
                            // V�rifie que la longueur du terme est dans les limites autoris�es
                            if (strlen($term)<self::MIN_ENTRY_SLOT || strlen($term)>self::MAX_ENTRY_SLOT) continue;

                            // V�rifie que ce n'est pas un mot-vide
                            if (isset($stopwords[$term])) continue;

                            // Ajoute le terme dans le document
                            $this->addTerm($term.'='.$value, $prefix);
                        }
                    }
                }
            }
        }

        // Cl�s de tri
        // FIXME : faire un clear_value avant. Attention : peut vire autre chose que des cl�s de tri. � voir
        foreach($this->schema->sortkeys as $sortkeyname=>$sortkey)
        {
            foreach($sortkey->fields as $name=>$field)
            {
                // R�cup�re les donn�es du champ, le premier article si c'est un champ multivalu�
                $value=$this->fields[$name];
                if (is_array($value)) $value=reset($value);

                // start et end
                if ($field->start || $field->end)
                    $value=$this->startEnd($value, $field->start, $field->end);

                $value=implode(' ', Utils::tokenize($value));

                // Ne prend que les length premiers caract�res
                if ($field->length)
                {
                    if (strlen($value) > $field->length)
                        $value=substr($value, 0, $field->length);
                }

                // Si on a une valeur, termin�, sinon examine les champs suivants
                if ($value!==null && $value !== '') break;
            }

            if (!isset($sortkey->type)) $sortkey->type='string'; // FIXME: juste en attendant que les bases asco soient recr��es
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
                    throw new LogicException("Type de cl� incorrecte pour la cl� de tri $sortkeyname");

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
     * La fonction cr�e tous les objets xapian dont on a besoin pour faire
     * analyser une �quation et lancer une recherche
     */
    private function setupSearch()
    {
        // Initialise l'environnement de recherche
        $this->xapianEnquire=new XapianEnquire($this->xapianDatabase);

        // Initialise le QueryParser
        $this->xapianQueryParser=new XapianQueryParser();

        // Param�tre l'index par d�faut (l'index global)
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
            if (!isset($index->_type)) $index->_type=DatabaseSchema::INDEX_PROBABILISTIC; // cas d'un sch�ma compil� avant que _type ne soit impl�ment�
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
            if (!isset($alias->_type)) $alias->_type=DatabaseSchema::INDEX_PROBABILISTIC; // cas d'un sch�ma compil� avant que _type ne soit impl�ment�
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
        $this->xapianQueryParser->set_stopper($this->stopper); // fixme : stopper ne doit pas �tre une variable locale, sinon segfault

        $this->xapianQueryParser->set_default_op($this->defaultOp);
        $this->xapianQueryParser->set_database($this->xapianDatabase); // indispensable pour FLAG_WILDCARD

        // Exp�rimental : autorise un value range sur le champ REF s'il existe une cl� de tri nomm�e REF
        foreach($this->schema->sortkeys as $name=>$sortkey)
        {
            if (!isset($sortkey->type)) $sortkey->type='string'; // FIXME: juste en attendant que les bases asco soient recr��es
            if ($sortkey->type==='string')
            {
                // todo: xapian ne supporte pas de pr�fixe pour les stringValueRangeProcessor
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
     * Fonction callback utilis�e par {@link parseQuery()} pour convertir
     * la syntaxe [xxx] utilis�e dans une �quation de recherche en recherche
     * � l'article
     *
     * @param array $matches le tableau g�n�r� par preg_replace_callback
     * @return string
     */
    private function searchByValueCallback($matches)
    {
        // r�cup�re le terme � convertir
        $term=trim($matches[1]);

        // Regarde si le terme se termine par une troncature
        $wildcard=substr($term, -1)==='*';

        // Concat�ne tous les tokens du terme avec un underscore
        $term=implode('_', Utils::tokenize($term));

        // Tronque l'article s'il d�passe la limite autoris�e
        if (strlen($term)>self::MAX_TERM-2)
            $term=substr($term, 0, self::MAX_TERM-2);

        // Encadre le terme avec des underscores et ajoute �ventuellement la troncature
        $term = '_' . $term ; // fixme: pb si ce qui pr�c�de est un caract�re aa[bb]cc -> aa_bb_cc. Faut g�rer ?
        if ($wildcard) $term.='*'; else $term.='_';

        // Termin�
        return $term;
    }

    /**
     * Traduit les op�rateurs bool�ens fran�ais (et, ou, sauf) en op�rateurs
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
     * Construit une requ�te xapian � partir d'une �quation de recherche saisie
     * par l'utilisateur.
     *
     * Si la requ�te � analyser est null ou une chaine vide, un objet XapianQuery
     * special permettant de rechercher tous les documents pr�sents dans la base
     * est retourn�.
     *
     * @param string $equation
     * @return XapianQuery
     */
    private function parseQuery($equation)
    {
        // Equation=null ou chaine vide : s�lectionne toute la base
        if (is_null($equation) || $equation==='' || $equation==='*')
            return new XapianQuery('');

        // Pr�-traitement de la requ�te pour que xapian l'interpr�te comme on souhaite
        $equation=preg_replace_callback('~(?:[a-z0-9]\.){2,9}~i', array('Utils', 'acronymToTerm'), $equation); // sigles � traiter, xapian ne le fait pas s'ils sont en minu (a.e.d.)
        $equation=preg_replace_callback('~\[(.*?)\]~', array($this,'searchByValueCallback'), $equation);
        $equation=$this->protectOperators($equation);
        $equation=Utils::convertString($equation, 'queryparser'); // FIXME: utiliser la m�me table que tokenize()
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

        // Construit la requ�te
        $query=$this->xapianQueryParser->parse_Query
        (
            utf8_encode($equation),         // � partir de la version 1.0.0, les composants "texte" de xapian attendent de l'utf8
            $flags
        );

        // Correcteur orthographique
        $this->correctedEquation=$this->xapianQueryParser->get_corrected_query_string();

        return $query;
    }

    /**
     * Fonction exp�rimentale utilis�e par {@link parseQuery()} pour convertir
     * les num�ros de pr�fixe pr�sents dans l'�quation retourn�e par xapian en
     * noms d'index tels que d�finis par l'utilisateur.
     *
     * @param array $matches le tableau g�n�r� par preg_replace_callback
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

- Dans le sch�ma de la base (DatabaseSchema, DbEdit) ajouter pour
  chaque index (que ce soit un vrai index ou un alias) une propri�t�
  "type d'index" qui peut prendre les valeurs "index probablistique" ou
  "filtre".

- Dans le setupSearch(), lorsqu'on ajoute la liste des index/pr�fixes, utiliser
  cette propri�t� pour indiquer � xapian le type d'index :

  * "index probabalistique" : utiliser add_prefix()
  * "filtre" : utiliser add_boolean_prefix()).

- Lors d'une recherche, ne pas chercher � combiner nous-m�me les diff�rents
  champs et les diff�rents bouts d'�quations : laisser xapian le faire.
  Si on nous a transmis "_equation=xxx & date=yyy & type=zzz" en query string,
  se contenter de concat�ner le tout et laisser xapian utiliser le defaultOp().
  Xapian se chargera tout seul de passer en 'filter' tous les index d�finis
  avec add_boolean_prefix().

- Il faut quand m�me tout parenth�ser au cas ou les bouts contiennent plusieurs
  ce qui nous donne : (xxx) date:(yyy) type:(zzz)

- voir comment on peut impl�menter �a en gardant la compatibilit� avec BIS

*/

    public function getFacet($table, $sortByCount=false)
    {
        if (! $this->spy) return array();

        $key=Utils::ConvertString($table, 'alphanum');
        if (!isset($this->schema->lookuptables[$key]))
            throw new Exception("La table de lookup '$table' n'existe pas");
        $prefix='T' . $this->schema->lookuptables[$key]->_id . ':';
        $facet=$this->spy->get_terms_as_array($prefix);

        // workaround bug dans TermSpy : si la lettre qui suit le prefix est une maju, l'entr�e est ignor�e
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
        // a priori, pas de r�ponses
        $this->eof=true;

        $rset=null;
        // Analyse les options indiqu�es (start et sort)
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
                        throw new Exception('Op�rateur par d�faut incorrect : '.$options['_defaultop']);
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
         * 11�me r�ponse en premier. Si on demande alors � passer � 50 notices par
         * page, on va alors afficher les notices 11 � 50, mais on n'aura pas
         * de lien "page pr�c�dente".
         * Le code ci-dessus, dans ce cas, ram�ne "start" � 1 pour que toutes
         * les notices soient affich�es.
         */

        // Stocke les valeurs finales
        $this->start=$start+1;
        $this->max=$max;


        if ($minscore<0) $minscore=0; elseif($minscore>100) $minscore=100;

        // Met en place l'environnement de recherche lors de la premi�re recherche
        if (is_null($this->xapianEnquire)) $this->setupSearch();

        // Analyse les filtres �ventuels � appliquer � la recherche
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

        // Analyse l'�quation de recherche de l'utilisateur
        $this->equation=$equation;
        $query=$this->xapianQuery=$this->parseQuery($equation);

        // Probl�me xapian : si on fait une recherche '*' avec un tri par pertinence,
        // xapian ne rends pas la main. Du coup on force i�i un tri par docid d�croissant.
        if (trim($equation)==='*') $sort='-';

        // Combine l'�quation et le filtre pour constituer la requ�te finale
        if ($filter)
            $query=new XapianQuery(XapianQuery::OP_FILTER, $query, $this->xapianFilter);

        // Ex�cute la requ�te
        $this->xapianEnquire->set_query($query);

        // D�finit l'ordre de tri des r�ponses
        $this->setSortOrder($sort);

        // D�finit le score minimal souhait�
        if ($minscore) $this->xapianEnquire->set_cutoff($minscore);

        // Lance la recherche

        // Exp�rimental : support des facettes de la recherche via un TermCountMatchSpy.
        // Requiert la version "MatchSpy" de Xapian (en attendant que la branche
        // MatchSpy ait �t� int�gr�e dans le trunk.
        if ($facets && function_exists('new_TermCountMatchSpy'))
        {
            // Fonctionnement : on d�finit dans la config une cl� facets qui
            // indique les tables de lookup qu'on souhaite utiliser comme facettes.
            // DatabaseModule::select() nous passe cette liste dans le param�tre
            // '_facet' du tableau options.
            // On cr�e un Spy de type XapianTermCountMatchSpy auquel on
            // demande de compter tous les termes provenant de ces tables
            // de lookup.
            // L'utilisateur peut ensuite r�cup�rer le r�sultat en utilisant
            // la m�thode getFacet() et en appellant searchInfo() avec les
            // nouveaus param�tres spy* introduits.
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

        // Teste si la requ�te a retourn� des r�ponses
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

        // Retourne true pour indiquer qu'on a au moins une r�ponse
        return true;
    }


    /**
     * Param�tre le MSet pour qu'il retourne les documents selon l'ordre de tri
     * indiqu� en param�tre.
     *
     * @param string|array|null $sort un tableau ou une chaine indiquant les
     * diff�rents crit�res composant l'ordre de tri souhait�.
     *
     * Les crit�res de tri possible sont :
     * - <code>%</code> : trier les notices par pertinence (la meilleure en t�te)
     * - <code>+</code> : trier par ordre croissant des num�ros de document
     * - <code>-</code> : trier par ordre d�croissant des num�ros de document
     * - <code>xxx+</code> : trier sur le champ xxx, par ordre croissant
     *   (le signe plus est optionnel, c'est l'ordre par d�faut)
     * - <code>xxx-</code> : trier sur le champ xxx, par ordre d�croissant
     *
     * Plusieurs crit�res de tri peuvent �tre combin�s entres eux. Dans ce cas,
     * le premier crit�re sera d'abord utilis�, puis, en cas d'�galit�, le
     * second et ainsi de suite.
     *
     * La combinaison des crit�res peut se faire soit en passant en param�tre
     * une chaine listant dans l'ordre les diff�rents crit�res, soit en passant
     * en param�tre un tableau contenant autant d'�l�ments que de crit�res ;
     * soit en combinant les deux.
     *
     * Exemple de crit�res composites :
     * - chaine : <code>'type'</code>, <code>'type+ date- %'</code>
     * - tableau : <code>array('type', 'date+')</code>,
     *   <code>array('type', 'date+ revue+ titre %'</code>
     *
     * Remarque : n'importe quel caract�re de ponctuation peut �tre utilis�
     * pour s�parer les diff�rents crit�res au sein d'une m�me chaine (espace,
     * virgule, point-virgule...)
     *
     * @throws Exception si l'ordre de tri demand� n'est pas possible ou si
     * la cl� de tri indiqu�e n'existe pas dans la base.
     */
    private function setSortOrder($sort=null)
    {
        // Si $sort est un tableau, on concat�ne tous les �l�ments ensembles
        if (is_array($sort))
            $sort=implode(',', $sort);

        // On a une chaine unique avec tous les crit�res, on l'explose
        $t=preg_split('~[^a-zA-Z_%+-]+~m', $sort, -1, PREG_SPLIT_NO_EMPTY);

        // Ordre de tri par d�faut : par docid d�croissants
        if (empty($t))
            $t=array('-');

        // Cas d'un tri simple (un seul crit�re indiqu�)
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

                // Par docid d�croissants
                case '-':
                    $this->xapianEnquire->set_weighting_scheme(new XapianBoolWeight());
                    $this->xapianEnquire->set_DocId_Order(XapianEnquire::DESCENDING);
                    break;

                // Sur une cl� de tri existante
                default:
                    // D�termine l'ordre (croissant/d�croissant)
                    $lastChar=substr($key, -1);
                    $forward=true;
                    if ($lastChar==='+' || $lastChar==='-')
                    {
                        $key=substr($key, 0, -1);
                        $forward=($lastChar==='+');
                    }

                    // V�rifie que la cl� de tri existe dans la base
                    $key=strtolower($key);
                    if (! isset($this->schema->sortkeys[$key]))
                        throw new Exception('Impossible de trier par : ' . $key);

                    // R�cup�re l'id de la cl� de tri (= le value slot number � utiliser)
                    $id=$this->schema->sortkeys[$key]->_id;

                    // Trie sur cette valeur
                    $this->xapianEnquire->set_sort_by_value($id, !$forward);

                    // M�morise l'ordre de tri en cours (pour searchInfo)
                    $this->sortOrder=$key . ($forward ? '+' : '-');
                    $this->sortKey[$key]=$id;
            }
        }

        // Cas d'un tri composite (plusieurs crit�res de tri)
        else
        {
            // On va utiliser un sorter xapian pour cr�er la cl�
            $this->xapianSorter=new XapianMultiValueSorter();

            // R�initialise l'ordre de tri en cours
            $this->sortOrder='';

            // On va utiliser la m�thode set_sort_by_key sauf s'il faut combiner avec la pertinence
            $function='set_sort_by_key';

            // Ajoute chaque crit�re de tri au sorter
            foreach($t as $i=>$key)
            {
                switch ($key)
                {
                    // Par pertinence : change la m�thode � utiliser
                    case '%':
                        if ($i===0)
                            $method='set_sort_by_relevance_then_key';
                        elseif($i===count($t)-1)
                            $method='set_sort_by_key_then_relevance';
                        else
                            throw new Exception('Ordre de tri incorrect "'.$sort.'" : "%" peut �tre au d�but ou � la fin mais pas au milieu');

                        $this->sortOrder.=$key . ' ';
                        break;

                    // Par docid : impossible, on ne peut pas combiner avec autre chose
                    case '+':
                    case '-':
                        throw new Exception('Ordre de tri incorrect "'.$sort.'" : "'.$key.'" ne peut pas �tre utilis� avec d\'autres crit�res');
                        break;

                    // Sur une cl� de tri existante
                    default:
                        // D�termine l'ordre (croissant/d�croissant)
                        $lastChar=substr($key, -1);
                        $forward=true;
                        if ($lastChar==='+' || $lastChar==='-')
                        {
                            $key=substr($key, 0, -1);
                            $forward=($lastChar==='+');
                        }

                        // V�rifie que la cl� de tri existe dans la base
                        $key=strtolower($key);
                        if (! isset($this->schema->sortkeys[$key]))
                            throw new Exception('Impossible de trier par : ' . $key);

                        // R�cup�re l'id de la cl� de tri (= le value slot number � utiliser)
                        $id=$this->schema->sortkeys[$key]->_id;

                        // Ajoute cette cl� au sorter
                        $this->xapianSorter->add($id, $forward);

                        // M�morise l'ordre de tri en cours (pour searchInfo)
                        $this->sortOrder.=$key . ($forward ? '+ ' : '- ');
                        $this->sortKey[$key]=$id;
                }
            }

            // Demande � xapian de trier en utilisant la m�thode et le sorter obtenu
            $this->xapianEnquire->$function($this->xapianSorter, false);

            // Supprime l'espace final de l'ordre en cours
            $this->sortOrder=trim($this->sortOrder);
        }
    }


    /**
     * Sugg�re des termes provenant de la table indiqu�e
     *
     * @param string $table
     * @return array
     */
    public function suggestTerms($table)
    {
        // V�rifie qu'on a un enregistrement en cours
        if (is_null($this->xapianMSetIterator))
            throw new Exception('Pas de document courant');

        // D�termine le pr�fixe de la table dans laquelle on veut chercher
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
        // R�initialise tous les champs � leur valeur par d�faut
        // Corrige � la fois :
        // bug de actionReindex() qui fusionne les notices
        // bug trouv� par SF : search(texte officiel) -> on r�p�te les infos
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
     * Retourne une estimation du nombre de r�ponses obtenues lors de la
     * derni�re recherche ex�cut�e.
     *
     * @param int|string $countType le type d'estimation � fournir ou le
     * libell� � utiliser

     * @return int|string
     */
    public function count($countType=0)
    {
        // Si l'argument est une chaine, on consid�re que l'utilisateur veut
        // une �valuation (arrondie) du nombre de r�ponses et cette chaine
        // est le libell� � utiliser (par exemple : 'environ %d ')
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

            // Si min==max, c'est qu'on a le nombre exact de r�ponses, pas d'�valuation
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
                return '~&#160;' . $round; //  ou '�&#160;'

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

            // Liste des mots-vides ignor�s dans l'�quation de recherche
            case 'stopwords': return $this->getRequestStopwords(false);
            case 'internalstopwords': return $this->getRequestStopwords(true);

            // Liste des termes pr�sents dans l'�quation + termes correspondants au troncatures
            case 'queryterms': return $this->getQueryTerms(false);
            case 'internalqueryterms': return $this->getQueryTerms(true);

            // Liste des termes du document en cours qui collent � la requ�te
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

            // Le libell� de la cl� de tri en cours
            case 'sortorder':
                return  $this->sortOrder;

            // La valeur de la cl� de tri pour l'enreg en cours
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
     * Retourne la liste des termes de recherche g�n�r�s par la requ�te.
     *
     * getQueryTerms construit la liste des termes d'index qui ont �t� g�n�r�s
     * par la derni�re requ�te analys�e.
     *
     * La liste comprend tous les termes pr�sents dans la requ�te (mais pas les
     * mots vides) et tous les termes g�n�r�s par les troncatures.
     *
     * Par exemple, la requ�te <code>�duc* pour la sant�</code> pourrait
     * retourner <code>array('educateur', 'education', 'sante')</code>.
     *
     * Par d�faut, les termes retourn�s sont filtr�s de mani�re � pouvoir �tre
     * pr�sent�s � l'utilisateur (d�doublonnage des termes, suppression des
     * pr�fixes internes utilis�s dans les index de xapian), mais vous pouvez
     * passer <code>false</code> en param�tre pour obtenir la liste brute.
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
                // Supprime le pr�fixe �ventuel
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
     * Retourne la liste des mots-vides pr�sents dans la la requ�te.
     *
     * getRequestStopWords construit la liste des termes qui figuraient dans
     * la derni�re requ�te analys�e mais qui ont �t� ignor�s parcequ'ils
     * figuraient dans la liste des mots-vides d�inis dans la base.
     *
     * Par exemple, la requ�te <code>outil pour le web, pour internet</code>
     * pourrait retourner <code>array('pour', 'le')</code>.
     *
     * Par d�faut, les termes retourn�s sont d�doublonn�s, mais vous pouvez
     * passer <code>false</code> en param�tre pour obtenir la liste brute (dans
     * l'exemple ci-dessus, on obtiendrait <code>array('pour', 'le', 'pour')</code>
     *
     * @param bool $internal flag indiquant s'il faut d�doublonner ou non la
     * liste des mots-vides.
     *
     * @return array un tableau contenant la liste des termes obtenus.
     */
    private function getRequestStopWords($internal=false)
    {
        // Liste des mots vides ignor�s
        if (is_null($this->xapianQueryParser)) return array();

        $stopwords=array();
        $iterator=$this->xapianQueryParser->stoplist_begin();
        while(! $iterator->equals($this->xapianQueryParser->stoplist_end()))
        {
            if ($internal)
                $stopwords[]=$iterator->get_term(); // pas de d�doublonnage
            else
                $stopwords[$iterator->get_term()]=true; // d�doublonne en m�me temps
            $iterator->next();
        }
        return $internal ? $stopwords : array_keys($stopwords);
    }

    /**
     * Retourne la liste des termes du document en cours qui correspondent aux
     * terms de recherche g�n�r�s par la requ�te.
     *
     * getMatchingTerms construit l'intersection entre la liste des termes
     * du document en cours et la liste des termes g�n�r�s par la requ�te.
     *
     * Cela permet, entre autres, de comprendre pourquoi un document appara�t
     * dans la liste des r�ponses.
     *
     * Par d�faut, les termes retourn�s sont filtr�s de mani�re � pouvoir �tre
     * pr�sent�s � l'utilisateur (d�doublonnage des termes, suppression des
     * pr�fixes internes utilis�s dans les index de xapian), mais vous pouvez
     * passer <code>false</code> en param�tre pour obtenir la liste brute.
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
                // Supprime le pr�fixe �ventuel
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
        MAX_KEY=240,            // Longueur maximale d'un terme, tout compris (doit �tre inf�rieur � BTREE_MAX_KEY_LEN de xapian)
        MAX_PREFIX=4,           // longueur maxi d'un pr�fixe (par exemple 'T99:')
        MIN_TERM=1,             // Longueur minimale d'un terme
        MAX_TERM=236,           // =MAX_KEY-MAX_PREFIX, longueur maximale d'un terme
        MIN_ENTRY_SLOT=2,       // longueur minimale d'un mot de base dans une table de lookup
        MAX_ENTRY_SLOT=20,      // longueur maximale d'un mot de base dans une table de lookup
        MAX_ENTRY=219           // =MAX_KEY-MAX_ENTRY_SLOT-1, longueur maximale d'une valeur dans une table des entr�es (e.g. masson:Editions Masson)
        ;
    const
        INDEX_STOP_WORDS=false; // false : les mots-vides sont ignor�s lors de l'indexation, true : ils sont ajout�s � l'index (mais ignor� pendant la recherche)

    /**
     * Sugg�re � l'utilisateur des entr�es ou des termes existant dans l'index
     * de xapian.
     *
     * Lookup prend en param�tre un mot, un d�but de mot ou une expression
     * constitu�e de plusieurs mots ou d�but de mots et va rechercher dans les
     * index de xapian des termes, des articles ou des entr�es issues des tables
     * de lookup susceptibles de correspondre � ce que rechercher l'utilisateur.
     *
     * Lookup teste dans l'ordre que la "table" indiqu�e en param�tre correspond
     * au nom d'une table de lookup, d'un alias ou d'un index existant (une
     * exception sera g�n�r�e si ce n'est pas le cas).
     *
     * Selon la source utilis�e, la nature des suggestions retourn�es sera
     * diff�rente :
     * - S'il s'agit d'une table de lookup invers�e, lookup retournera des
     *   entr�es en format riche (majuscules et minuscules, accents) contenant
     *   tous les mots indiqu�s.
     * - S'il s'agit d'une table de lookup simple, lookup retournera �galement
     *   des entr�es en format riche, mais uniquement celles qui commencent par
     *   l'un des mots indiqu�s (et qui contiennent tous les autres).
     * - S'il s'agit d'un index de type "article", lookup retournera des chaines
     *   "pauvres" (en minuscules non accentu�es) qui commencent par l'un des
     *   mots indiqu�s et contiennent tous les autres.
     * - S'il s'agit d'un index de type "mot", seul le dernier mot indiqu� dans
     *   l'expression de recherche sera pris en compte et les suggestions
     *   retourn�es sous la forme de "mots" en format pauvre.
     * - S'il s'agit d'un alias, les suggestions retourn�es correspondront au
     *   type des indices composant cet alias (i.e. soit des articles, soit des
     *   termes).
     *
     * @param string $table le nom de la table de lookup, de l'alias ou de
     * l'index � utiliser pour g�n�rer des suggestions.
     *
     * @param string $term le mot, le d�but de mot ou l'expression � rechercher.
     *
     * @param int $max le nombre maximum de suggestions � retourner
     * (0=pas de limite)
     *
     * @param bool $sort indique s'il faut trier les r�ponses par ordre
     * alphab�tique ou par nombre d�croissant d'occurences dans la base.
     *
     * Ce param�tre accepte les valeurs suivantes :
     * - false ou '-' : trier par ordre alphab�tique ;
     * - true ou '%' : trier par nombre d'occurences.
     *
     * Par d�faut (false ou '-'), les suggestions sont tri�es en ordre
     * alphab�tique. La recherche s'arr�te d�s que $max suggestions ont �t�
     * trouv�es.
     *
     * Si $sort est � true (ou '%'), lookup va g�n�rer la liste compl�te de
     * toutes les suggestions possibles puis va trier le r�sultat obtenu par
     * occurences d�croissantes et va ensuite conserver les $max meilleures.
     *
     * Un lookup avec le tri par d�faut (ordre alphab�tique) est donc bien plus
     * efficace.
     *
     * @param string $format d�finit le format � utiliser pour la mise en
     * surbrillance des termes de recherche de l'utilisateur au sein de chacun
     * des suggestions trouv�es.
     *
     * Il s'agit d'une chaine qui sera appliqu�e � chacune des mots en utilisant
     * la fonction sprintf() de php (exemple de format : <strong>%s</strong>).
     *
     * Si $format est null ou s'il s'agit d'une chaine vide, aucune surbrillance
     * ne sera appliqu�e.
     *
     * @return array un tableau contenant les suggestions obtenues. Chaque cl�
     * du tableau contient une suggestion et la valeur associ�e contient le
     * nombre d'occurences de cette entr�e dans la base.
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

        // Construit la version "minuscules non accentu�es" de la table indiqu�e
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
                    throw new Exception("Impossible de faire un lookup sur la table de lookup '$table' : type de table non g�r�");
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
            // quel pr�fixe ?
        }

        // Teste s'il s'agit d'un index
        elseif (isset($this->schema->indices[$key]))
        {
            // Teste s'il s'agit d'un index "� l'article"

            // Remarque : on ne peut pas tester directement l'index, car chacun des
            // champs peut �tre index� � l'article ou au mot. Du coup, on teste
            // uniquement le type d'indexation du premier champ et on suppose que
            // les autres champs de l'index sont index�s pareil.

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

        // Param�tre le helper
        $helper->setIterators($this->xapianDatabase->allterms_begin(), $this->xapianDatabase->allterms_end());
        $helper->setMax($max);
        $helper->setSortByFrequency($sort);
        $helper->setPrefix($prefix);
        $helper->setFormat($format);
echo 'Helper de type ', get_class($helper), '<br />';
        // Fait le lookup et retourne les r�sultats
        return $helper->lookup($term);
    }


    /**
     * Recherche les tokens de la base qui commencent par le terme indiqu�.
     *
     * Cette m�thode est similaire � lookup, mais recherche parmi les termes
     * d'indexation et non pas parmi les tables de lookup.
     *
     * @param string $term le terme recherch�
     *
     * @param int $max le nombre maximum de valeurs � retourner (0=pas de limite)
     *
     * @param int $sort l'ordre de tri souhait� pour les r�ponses :
     *   - 0 : trie les r�ponses par nombre d�croissant d'occurences dans la base (valeur par d�faut)
     *   - 1 : trie les r�ponses par ordre alphab�tique croissant
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

        // Trie des r�ponses
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
            if (debug) echo ++$i, ': doc ', $id, ' supprim�<br />';
            $iterator->next();
        }
    }

    public function reindex()
    {
        $startTime=microtime(true);

        // V�rifie que la base est ouverte en �criture
        if (! $this->xapianDatabase instanceof XapianWritableDatabase)
            throw new Exception('Impossible de r�indexer une base de donn�es ouverte en lecture seule.');

        // M�morise le path actuel de la base
        $path=$this->getPath();

        echo '<h1>R�indexation compl�te de la base ', basename($path), '</h1>';

        // S�lectionne toutes les notices
        $this->search(null, array('_sort'=>'+', '_max'=>-1));
        $count=$this->count();
        if ($count==0)
        {
            echo '<p>La base ne contient aucun document, il est inutile de lancer la r�indexation.</p>';
            return;
        }
        echo '<ol>';

        echo '<li>La base contient ', $count, ' notices.</li>';

        // Si une base 'tmp' existe d�j�, on le signale et on s'arr�te
        echo '<li>Cr�ation de la base de donn�es temporaire...</li>';
        $pathTmp=$path.DIRECTORY_SEPARATOR.'tmp';
        if (file_exists($pathTmp) && count(glob($pathTmp . DIRECTORY_SEPARATOR . '*'))!==0)
            throw new Exception("Le r�pertoire $pathTmp contient d�j� des donn�es (r�indexation pr�c�dente interrompue ?). Examinez et videz ce r�pertoire puis relancez la r�indexation.");

        // Cr�e la nouvelle base dans './tmp'
        $tmp=Database::create($pathTmp, $this->getSchema(), 'xapian');

        // Cr�e le r�pertoire 'old' s'il n'existe pas d�j�
        $pathOld=$path.DIRECTORY_SEPARATOR.'old';
        if (! is_dir($pathOld))
        {
            if (! @mkdir($pathOld))
                throw new Exception('Impossible de cr�er le r�pertoire ' . $pathOld);
        }

        // Donn�es collect�es pour le graphique
        $width=560;
        $data=array();
        $step=ceil($this->count() / ($width*1/4)); // on prendra une mesure toute les step notices

        // Recopie les notices
        echo '<li>R�indexation des notices...</li>';
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

        // Ferme la base actuelle en mettant � 'null' toutes les propri�t�s de $this
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
            entre le moment o� on commence le transfert et le moment o� tout
            est transf�r�.

            Pour �viter �a, on proc�de en deux �tapes :
            1. on d�place vers le r�pertoire ./old tous les fichiers de la base
               existante, en commen�ant par le fichier de version (iamflint), ce
               qui fait que plus personne ne peut ouvrir la base d�s que
               celui-ci a �t� renomm� ;
            2. on transf�re tous les fichier de ./tmp en ordre inverse,
               c'est-�-dire en terminant par le fichier de version, ce qui fait
               que personne ne peut ouvrir la base tant qu'on n'a pas fini.
        */

        // Liste des fichiers pouvant �tre cr��s pour une base flint
        $files=array
        (
            // les fichiers de version doivent �tre en premier
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


        // Transf�re tous les fichiers existants vers le r�pertoire ./old
        clearstatcache();
        echo '<li>Transfert de la base actuelle dans le r�pertoire "old"...</li>';
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

        // Transf�re les fichiers du r�pertoire tmp dans le r�pertoire de la base
        echo '<li>Installation de la base temporaire comme base actuelle...</li>';
        foreach(array_reverse($files) as $file)
        {
            $h=$path . DIRECTORY_SEPARATOR . $file;

            $tmp=$pathTmp . DIRECTORY_SEPARATOR . $file;
            if (file_exists($tmp))
            {
                //echo "D�placement de $tmp vers $h<br />";
                rename($tmp, $h);
            }
        }

        // Essaie de supprimer le r�pertoire tmp (d�sormais vide)
        $files=glob($pathTmp . DIRECTORY_SEPARATOR . '*');
        if (count($files)!==0)
            echo '<li><strong>Warning : il reste des fichiers dans le r�pertoire tmp</strong></li>';

        // todo: en fait on n'arrive jamais � supprimer tmp. xapian garde un handle dessus ? � voir, pas indispensable de supprimer tmp
        /*
            if (!@unlink($pathTmp))
                echo '<p>Warning : impossible de supprimer ', $pathTmp, '</p>';
        */

        // R�ouvre la base
        echo '<li>R�-ouverture de la base...</li>';
        $this->doOpen($path, false);
        $this->search(null, array('_sort'=>'+', '_max'=>-1));

        echo '<li>La r�indexation est termin�e.</li>';
        echo '<li>Statistiques :';

        // G�n�re un graphique
        $type='lc';        // type de graphe
        $size=$width.'x300';    // Taille du graphe (largeur x hauteur)
        $title=utf8_encode('Nombre de notices r�index�es par seconde');
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
        echo '<p>Dur�e totale de la r�indexation : ', Utils::friendlyElapsedTime(microtime(true)-$startTime), '.</p>';

        echo '</li></ol>';
    }

/*
    public function reindex()
    {
        while(ob_get_level()) ob_end_flush();
        $this->search(null, array('_sort'=>'+', '_max'=>40000));
        echo $this->count(), ' notices � r�indexer. <br />';

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
        echo sprintf('%.2f', microtime(true)-$start), ', termin� !<br />';
        flush();
    }

    public function reindexOld()
    {
        while(ob_get_level()) ob_end_flush();
        $this->search(null, array('_sort'=>'+', '_max'=>20000));
        echo $this->count(), ' notices � r�indexer. <br />';

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

            // R�-indexe l'enregistrement
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
        echo sprintf('%.2f', microtime(true)-$start), ', termin� !<br />';
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
 * Repr�sente un enregistrement dans une base {@link XapianDatabase}
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
     * Lors d'un parcours s�quentiel, num�ro de l'�l�ment de tableau
     * en cours. Utilis� par {@link next()} pour savoir si on a atteint
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

        // Version minu non accentu�e du nom du champ
        $key=Utils::ConvertString($offset, 'alphanum');
        $this->fields[$key]=$value;
        return;
        // V�rifie que le champ existe
        if (! array_key_exists($key, $this->fields))
            throw new DatabaseFieldNotFoundException($offset);

        // V�rifie que la valeur concorde avec le type du champ
        switch ($this->schema->fields[$key]->_type)
        {
            case DatabaseSchema::FIELD_AUTONUMBER:
            case DatabaseSchema::FIELD_INT:
                /*
                 * Valeurs stock�es telles quelles :
                 *      null -> null
                 *      12 -> 12
                 * Valeurs converties : (par commodit�, exemple, import fichier texte)
                 *      '' -> null
                 *      '12' -> 12
                 * Erreurs :
                 *      '12abc' -> exception            pas de tol�rance si on caste une chaine
                 *      true, false -> exception        un boole�en n'est pas un entier
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
                 * Valeurs stock�es telles quelles :
                 *      null -> null
                 *      true -> true
                 *      false -> false
                 * Valeurs converties : (par commodit�, exemple, import fichier texte)
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
         * On ne retourne pas directement key(fields) car sinon on r�cup�re
         * un nom en minu sans accents qui sera ensuite utilis� dans les boucles,
         * les callbacks, etc.
         * Si un callback a le moindre test du style if($name='Aut'), cela ne marchera
         * plus.
         * On fait donc une indirection pour retourner comme cl� le nom exact du
         * champ tel que saisi par l'utilisateur dans le sch�ma.
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
 * Exception g�n�r�e lorsqu'on essaie de modifier une base de donn�es
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
 * Exception g�n�r�e lorsqu'on essaie d'acc�der � un enregistrement alors
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
 * Exception g�n�r�e lorsqu'on essaie d'acc�der � un champ qui n'existe pas
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
 * Exception g�n�r�e par {@link saveRecord()} et {@link cancelUpdate()} si elles
 * sont appell�es alors que l'enregistrement actuel n'est pas en cours de
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
 * Exception g�n�r�e lorsqu'on essaie de stocker dans un champ une valeur qui
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