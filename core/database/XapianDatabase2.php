<?php
/**
 * @package     fab
 * @subpackage  database
 * @author      Daniel Ménard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id: XapianDatabase.php 484 2007-10-25 09:33:07Z daniel.menard.bdsp $
 */

require_once Runtime::$fabRoot . 'lib/xapian/xapian.php';

/**
 * Un driver de base de données pour fab utilisant une base Xapian pour
 * l'indexation et le stockage des données
 * 
 * @package     fab
 * @subpackage  database
 */
class XapianDatabaseDriver2 extends Database
{

    /**
     * La structure de la base de données, telle que retournée par 
     * {@link DatabaseStructure->getStructure()}
     *
     * @var Object 
     */
    private $structure=null;
    
    
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
     * 0 : l'enregistrement courant n'est pas en cours d'édition
     *  
     * 1 : un nouvel enregistrement est en cours de création 
     * ({@link addRecord()} a été appellée)
     * 
     * 2 : l'enregistrement courant est en cours de modification 
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
     * {@link serach()}
     * 
     * @var XapianQueryParser
     */
    private $xapianQueryParser=null;

    
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
     * Libellé de l'ordre de tri utilisé lors de la recherche
     *
     * @var string
     */
    private $sortOrder='';
    
    /**
     * Numéro du slot dans les 'values' du document qui contient la valeur
     * de la clé de tri pour l'ordre de tri en cours
     *
     * @var null|int
     */
    private $sortKey=null;
    
    public function getStructure()
    {
        return $this->structure;
    }
    
    // *************************************************************************
    // ***************** Création et ouverture de la base **********************
    // *************************************************************************
    
    /**
     * Crée une nouvelle base xapian
     *
     * @param string $path le path de la base à créer
     * @param DatabaseStructure $structure la structure de la base à créer
     * @param Array $options options éventuelle, non utilisé
     * @return void
     */
    protected function doCreate($path, /* DS DatabaseStructure */ $structure, $options=null)
    {
        /* DS A ENLEVER */ 
        // Vérifie que la structure de la base de données est correcte
        if (true !== $t=$structure->validate())
            throw new Exception('La structure de base passée en paramètre contient des erreurs : ' . implode('<br />', $t));

        // Compile la structure
        $structure->compile();
        
        // Crée la base xapian
        $this->xapianDatabase=new XapianWritableDatabase($path, Xapian::DB_CREATE_OR_OVERWRITE); // todo: remettre à DB_CREATE
        
        // Enregistre la structure de la base
        $this->xapianDatabase->set_metadata('fab_structure', $structure->toXml());
        $this->xapianDatabase->set_metadata('fab_structure_php', serialize($structure));

        // Initialise les propriétés de l'objet
        $this->structure=$structure;
        $this->initDatabase(true);
    }

    
    /**
     * Ouvre une base Xapian
     *
     * @param string $path le path de la base à ouvrir.
     * @param bool $readOnly true pour ouvrir la base en lecture seule, false
     * pour l'ouvrir en mode lexture/écriture.
     * @return void
     */
    protected function doOpen($path, $readOnly=true)
    {
        // Ouvre la base xapian
        if ($readOnly)
            $this->xapianDatabase=new XapianDatabase($path);
        else
            $this->xapianDatabase=new XapianWritableDatabase($path, Xapian::DB_OPEN);
            
        // Charge la structure de la base
        $this->structure=unserialize($this->xapianDatabase->get_metadata('fab_structure_php'));
        if (! $this->structure instanceof DatabaseStructure)
            throw new Exception("Impossible d'ouvrir la base, structure non gérée'");
        
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
        $this->fields=array_fill_keys(array_keys($this->structure->fields), null);

        // Crée l'objet DatabaseRecord
        $this->record=new XapianDatabaseRecord2($this->fields, $this->structure);
        
        foreach($this->structure->fields as $name=>$field)
            $this->fieldById[$field->_id]=& $this->fields[$name];

        foreach($this->structure->indices as $name=>&$index) // fixme:
            $this->indexById[$index->_id]=& $index;

        foreach($this->structure->lookuptables as $name=>&$lookuptable) // fixme:
            $this->lookuptableById[$lookuptable->_id]=& $lookuptable;
            
        // Les propriétés qui suivent ne sont initialisées que pour une base en lecture/écriture
//        if ($readOnly) return;
        
        // Mots vides de la base
        $this->structure->_stopwords=array_flip($this->tokenize($this->structure->stopwords));

        // Crée la liste des champs de type AutoNumber + mots-vides des champs
        foreach($this->structure->fields as $name=>$field)
        {
            // Champs autonumber
            if ($field->_type === DatabaseStructure::FIELD_AUTONUMBER)
                $this->autoNumberFields[$name]='fab_autonumber_'.$field->_id;

            // Mots vides du champ
            if ($field->defaultstopwords)
            {
                if ($field->stopwords==='')
                    $field->_stopwords=$this->structure->_stopwords;
                else
                    $field->_stopwords=array_flip($this->tokenize($field->stopwords.' '.$this->structure->stopwords));
            }
            else
            {
                if ($field->stopwords==='')
                    $field->_stopwords=array();
                else
                    $field->_stopwords=array_flip($this->tokenize($field->stopwords));
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
//        pre($this->structure);
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
     * @return unknown
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
            if ($pt === false) return '';
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
     * @param bool $global si true, le terme est ajouté avec et sans préfixe 
     * @param int $weight le poids du terme
     * @param null|int $position null : le terme est ajouté sans position, 
     * int : le terme est ajouté avec la position indiquée
     */
    private function addTerm($term, $prefix, $global=false, $weight=1, $position=null)
    {
        if (is_null($position))
        {
            $this->xapianDocument->add_term($prefix.$term, $weight);
            if ($global)
                $this->xapianDocument->add_term($term, $weight);
        }
        else
        {
            $this->xapianDocument->add_posting($prefix.$term, $position, $weight);
            if ($global)
                $this->xapianDocument->add_posting($term, $position, $weight);
        }
    }
    
    
    /**
     * Construit la liste des tokens pour un texte donné.
     * 
     * @param string text
     * @return array
     */
    private function tokenize($text)
    {
        static $charFroms = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZÀÁÂÃÄÅŒÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝßàáâãäåœæçèéêëìíîïðñòóôõöùúûüýþÿ-Þ\'';        
        static $charTo    = '0123456789abcdefghijklmnopqrstuvwxyzaaaaaaœæceeeeiiiidnooooo0uuuuysaaaaaaœæceeeeiiiidnooooouuuuyby e ';
         
        // Convertit les sigles en mots
        $text=preg_replace_callback('~(?:[a-z0-9]\.){2,9}~i', array($this, 'AcronymToTerm'), $text);
        
        // Convertit les caractères 
        $text=strtr($text, $charFroms, $charTo);
        
        // Gère les lettres doubles
        $text=strtr($text, array('æ'=>'ae', 'œ'=>'oe'));

        // Retourne un tableau contenant tous les mots présents
        return str_word_count($text, 1, '0123456789@');
    }
    

    /**
     * Fonction utilitaire utilisée par {@link tokenize()} pour convertir
     * les acronymes en mots
     *
     * @param Array $matches
     * @return string
     */
    private function AcronymToTerm($matches)
    {
        return str_replace('.','', $matches[0]);
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
        foreach ($this->structure->indices as $index)
        {
            // Détermine le préfixe à utiliser pour cet index
            $prefix=$index->_id.':';
            
            // Pour chaque champ de l'index, on ajoute les tokens du champ dans l'index
            foreach ($index->fields as $name=>$field)
            {
                // Traite tous les champs comme des champs articles
                $data=(array) $this->fields[$name];

                // Initialise la liste des mots-vides à utiliser
                $stopwords=$this->structure->fields[$name]->_stopwords;

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
                    $tokens=$this->tokenize($value);
                    foreach($tokens as $term)
                    { 
                        // Vérifie que la longueur du terme est dans les limites autorisées
                        if (strlen($term)<self::MIN_TERM or strlen($term)>self::MAX_TERM) continue;
                        
                        // Vérifie que ce n'est pas un mot-vide
                        if (! self::INDEX_STOP_WORDS && isset($stopwords[$term])) continue;
                                                
                        // Ajoute le terme dans le document
                        $this->addTerm($term, $prefix, $field->global, $field->weight, $field->phrases?$position:null);
                        
                        // Correcteur orthographique
                        if (false && $field->spelling) // fixme: ajouter à la structure
                            $this->xapianDatabase->add_spelling($term);

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
                        $this->addTerm($term, $prefix, $field->global, $field->weight, null);
                    }
                    
                    // Fait de la "place" entre chaque article
                    $position+=100;
                    $position-=$position % 100;
                }
                
                // Indexation empty/notempty
                if ($field->count)
                    $this->addTerm($count ? '__has'.$count : '__empty', $prefix, false);
            }                
        }

        // Tables de lookup
        foreach ($this->structure->lookuptables as $lookupTable)
        {
            // Détermine le préfixe à utiliser pour cette table
            $prefix='T'.$lookupTable->_id.':';
            
            // Parcourt tous les champs qui alimentent cette table 
            foreach($lookupTable->fields as $name=>$field)
            {
                // Traite tous les champs comme des champs articles
                $data=(array) $this->fields[$name];
                
                // Initialise la liste des mots-vides à utiliser
                $stopwords=$this->structure->fields[$name]->_stopwords;

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
                    
                    // Tokenise et ajoute une entrée dans la table pour chaque terme obtenu 
                    foreach($this->tokenize($value) as $term)
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
            
        // Clés de tri
        // FIXME : faire un clear_value avant. Attention : peut vire autre chose que des clés de tri. à voir
        foreach($this->structure->sortkeys as $sortkeyname=>$sortkey)
        {
//            echo 'Sortkey : ', $sortkeyname, '<br />';
            $key='';
            foreach($sortkey->fields as $name=>$field)
            {
                
                foreach($this->tokenize($field->name) as $fieldname) // FIXME: un peu lourd d'utiliser tokenize juste pour ça
                {
                    // Récupère les données du champ, le premier article si c'est un champ multivalué
                    $value=$this->fields[$fieldname];
//                    echo '...champ ', $fieldname, ', value=', var_export($value,true), '<br />';
                    if (is_array($value)) $value=reset($value);
//                    echo '...champ ', $fieldname, ', value=', var_export($value,true), '<br />';
                    if ($value!==null && $value !== '') break;
                }

                // start et end
                if ($field->start || $field->end)
                {
                    $value=$this->startEnd($value, $field->start, $field->end);
//                    echo '...après startend value=', var_export($value,true), ', start=', var_export($field->start,true), ', end=', var_export($field->end, true), '<br />';
                }
                $value=implode(' ', $this->tokenize($value));
                
                // Ne prend que les length premiers caractères
                if ($field->length)
                {
                    if (strlen($value) > $field->length)
                        $value=substr($value, 0, $field->length);
                        
                    // Complète avec des espaces si la clé fait moins que length caractères
                    if (strlen($value) < $field->length) // FIXME: on ne devrait pas être obligé de padder, xapian devrait supporter le multisort
                        $value=str_pad($value, $field->length, ' ', STR_PAD_RIGHT);
                }
                                    
                // Stocke la partie de clé
//                echo '...valeur finale=', var_export($value,true), '<br />';
                $key.=$value;
            }
            $key=rtrim($key);
            if ($key==='') $key=chr(255);
//            if ($key !== '')
//            {
//                echo '...clé finale pour ', $sortkeyname , ' : [<tt>', $key, '</tt>], len=', strlen($key), '<br />';
                $this->xapianDocument->add_value($sortkey->_id, $key);
//            }
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
        
        // Indique au QueryParser la liste des index de base
        foreach($this->structure->indices as $name=>$index)
        {
//            if($index->boolean)
//                $this->xapianQueryParser->add_boolean_prefix($name, $index->_id.':');
//            else
                $this->xapianQueryParser->add_prefix($name, $index->_id.':');
        }
        // Indique au QueryParser la liste des alias
        foreach($this->structure->aliases as $aliasName=>$alias)
        {
            foreach($alias->indices as $name=>$index)
            {
//                if(false)//($name==='date' || $name==='type')
//                    $this->xapianQueryParser->add_boolean_prefix($aliasName, $this->structure->indices[$name]->_id.':');
//                else
                    $this->xapianQueryParser->add_prefix($aliasName, $this->structure->indices[$name]->_id.':');
            }
        }
        // Initialise le stopper (suppression des mots-vides)
        $this->stopper=new XapianSimpleStopper();
        foreach ($this->structure->_stopwords as $stopword=>$i)
            $this->stopper->add($stopword);
        $this->xapianQueryParser->set_stopper($this->stopper); // fixme : stopper ne doit pas être une variable locale, sinon segfault
    
        $this->xapianQueryParser->set_default_op(XapianQuery::OP_OR); // FIXME : quel doit-être l'opérateur par défaut ?
        
        $this->xapianQueryParser->set_database($this->xapianDatabase); // indispensable pour FLAG_WILDCARD
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
        $term=implode('_', $this->tokenize($term));

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
    private static function frenchOperators($equation)
    {
        $t=explode('"', $equation);
        foreach($t as $i=>&$h)
        {
            if ($i%2==1) continue;
            $h=preg_replace(array('~\bet\b~','~\bou\b~','~\bsauf\b~','~\bbut\b~'), array('AND', 'OR', 'NOT', 'NOT'), $h);
        }
        return implode('"', $t);
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
        if (is_null($equation) || $equation==='')
            return new XapianQuery('');
            
        // Pré-traitement de la requête pour que xapian l'interprête comme on souhaite
//        if (debug) echo 'Equation originale : ', var_export($equation,true), '<br />';
        $equation=preg_replace_callback('~(?:[a-z0-9]\.){2,9}~i', array($this, 'AcronymToTerm'), $equation); // sigles à traiter, xapian ne le fait pas s'ils sont en minu (a.e.d.)
        $equation=Utils::convertString($equation, 'queryparser'); // FIXME: utiliser la même table que tokenize()
        $equation=preg_replace_callback('~\[(.*?)\]~', array($this,'searchByValueCallback'), $equation);
        $equation=self::frenchOperators($equation);
        //$equation=str_replace(array('[', ']'), array('"_','_"'), $equation);
        
//        if (debug) echo 'Equation passée à xapian : ', var_export($equation,true), '<br />';
    
        // Construit la requête
        $query=$this->xapianQueryParser->parse_Query
        (
            utf8_encode($equation),         // à partir de la version 1.0.0, les composants "texte" de xapian attendent de l'utf8 
            XapianQueryParser::FLAG_BOOLEAN |
            XapianQueryParser::FLAG_PHRASE | 
            XapianQueryParser::FLAG_LOVEHATE |
            XapianQueryParser::FLAG_BOOLEAN_ANY_CASE |
            XapianQueryParser::FLAG_WILDCARD |
            XapianQueryParser::FLAG_SPELLING_CORRECTION |
            XapianQueryParser::FLAG_PURE_NOT
        );

//        $h=utf8_decode($query->get_description());
//        $h=substr($h, 14, -1);
//        $h=preg_replace('~:\(pos=\d+?\)~', '', $h);
////        if (debug) echo "Equation comprise par xapian... : ", $h, "<br />"; 
//        $h=preg_replace_callback('~(\d+):~',array($this,'idToName'),$h);
////        if (debug) echo "Equation xapian après idtoname... : ", $h, "<br />"; 

        // Correcteur orhtographique
        if ($correctedEquation=$this->xapianQueryParser->get_corrected_query_string())
            echo '<strong>Essayez avec l\'orthographe suivante : </strong><a href="?_equation='.urlencode($correctedEquation).'">', $correctedEquation, '</a><br />';
        
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
        foreach($this->structure->indices as $index)
            if ($index->_id===$id) return $index->name.'=';
        return $matches[1];
    }
    
/*

  AMELIORATIONS A APPORTER AU SYSTEME DE RECHERCHE, REFLEXION 21/12/2007

- Dans la structure de la base (DatabaseStructure, DbEdit) ajouter pour
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
    
    /**
     * @inheritdoc
     */
    public function search($equation=null, $options=null)
    {
        // a priori, pas de réponses
        $this->eof=true;

        // Analyse les options indiquées (start et sort) 
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
        }
        else
        {
            $sort=null;
            $start=0;
            $max=-1;
            $filter=null;
        }
        $this->start=$start+1;
        $this->max=$max;
        $this->rank=0;
        
        // Met en place l'environnement de recherche lors de la première recherche
        if (is_null($this->xapianEnquire)) $this->setupSearch();

        // Analyse les filtres éventuels à appliquer à la recherche
        if ($filter)
        {
            $this->xapianFilter=null;
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
        $query=$this->xapianQuery=$this->parseQuery($equation);

        // Combine l'équation et le filtre pour constituer la requête finale
        if ($filter)
            $query=new XapianQuery(XapianQuery::OP_FILTER, $query, $filter);
            
        // Exécute la requête
        $this->xapianEnquire->set_query($query);
        
        // Définit l'ordre de tri des réponses
        $this->setSortOrder($sort);
        
        // Lance la recherche
        $this->xapianMSet=$this->xapianEnquire->get_MSet($start, $max, $max+1);
        $this->count=$this->xapianMSet->get_matches_estimated();

        // Teste si la requête a retourné des réponses
        $this->xapianMSetIterator=$this->xapianMSet->begin();
        if ($this->eof=$this->xapianMSetIterator->equals($this->xapianMSet->end())) 
        {
            return false;
        } 
        $this->loadDocument();
        $this->eof=false;
        
        // Retourne true pour indiquer qu'on a au moins une réponse
        return true;
    }

    
    /**
     * Paramètre le MSet pour qu'il retourne les documents selon l'ordre de tri
     * indiqué par la chaine $sort passée en paramètre.
     * 
     * Les options possibles pour sort sont :
     *     - '%' : trier les notices par score (la meilleure en tête)
     *     - '+' : trier par ordre croissant de numéro de document
     *     - '-' : trier par ordre décroissant de numéro de document
     *     - 'xxx+' : trier sur le champ xxx, par ordre croissant
     *     - 'xxx-' : trier sur le champ xxx, par ordre décroissant
     *     - 'xxx+%' : trier sur le champ xxx par ordre croissant, puis par
     *       pertinence.
     *     - 'xxx-%' : trier sur le champ xxx par ordre décroissant, puis par
     *       pertinence.
     *     - '%xxx+'
     *     - '%xxx-'
     * 
     * @param string|null $sort
     */
    private function setSortOrder($sort=null)
    {   
        $this->sortOrder='';
        $this->sortKey=null;
        
        // Définit l'ordre de tri
        switch ($sort)
        {
            case '%':
                $this->sortOrder='par pertinence';
                $this->xapianEnquire->set_Sort_By_Relevance();
                break;
                
            case '+':
                $this->sortOrder='par docid croissants';
                $this->xapianEnquire->set_weighting_scheme(new XapianBoolWeight());
                $this->xapianEnquire->set_DocId_Order(XapianEnquire::ASCENDING);
                break;

            case '-':
            case null:
                $this->sortOrder='par docid décroissants';
                $this->xapianEnquire->set_weighting_scheme(new XapianBoolWeight());
                $this->xapianEnquire->set_DocId_Order(XapianEnquire::DESCENDING);
                break;
                
            default:
                // Vérifie la syntaxe
                if 
                (
                    0==preg_match('~(%?)([+-]?)([a-z]+)([+-]?)(%?)~i', $sort, $matches) 
                    or 
                    ($matches[1]<>'' and $matches[5]<>'') // le % figure à la fois au début et à la fin
                    or 
                    ($matches[2]<>'' and $matches[4]<>'') // le +/- figure à la fois avant et après le nom du champ
                )
                    throw new Exception('Ordre de tri incorrect, syntaxe non reconnue : ' . $sort);
                    
                // Récupère le nom de la clé de tri à utiliser
                $sortkey=$matches[3];
                
                // Vérifie que cette clé de tri existe
                $sortkey=strtolower($sortkey);
                if (! isset($this->structure->sortkeys[$sortkey]))
                    throw new Exception('Impossible de trier par : ' . $sortkey);
                
                // Récupère l'id de la clé de trie (= le value slot number à utiliser)
                $id=$this->structure->sortkeys[$sortkey]->_id;

                $label=$this->structure->sortkeys[$sortkey]->label;
                if ($label)
                    $label= '"'.$label . '('.$sortkey. ')"';
                else 
                    $label='"' . $sortkey . '"';
                                 
                $this->sortKey=$id;
                
                // Détermine l'ordre
                $order = ((($matches[2]==='-') || ($matches[4]) === '-')) ? 0:1; //XapianEnquire::DESCENDING : XapianEnquire::ASCENDING;
                if ($matches[1])        // trier par pertinence puis par champ
                {
                    $this->sortOrder='par pertinence puis par '. $label . ($order ? ' croissants': ' décroissants');
                    $this->xapianEnquire->set_sort_by_relevance_then_value($id, !$order);
                }
                elseif ($matches[5])    // trier par champ puis par pertinence
                { 
                    $this->sortOrder='par ' . $label . ($order ? ' croissants': ' décroissants') . ' puis par pertinence';
                    $this->xapianEnquire->set_sort_by_value_then_relevance($id, !$order);
                }
                else                    // trier par champ uniquement
                {                        
                    $this->sortOrder='par ' . $label . ($order ? ' croissants': ' décroissants');
                    $this->xapianEnquire->set_sort_by_value($id, !$order);
                }
        }
    }

    public function suggestTerms($table)
    {
        // Vérifie qu'on a un enregistrement en cours
        if (is_null($this->xapianMSetIterator))
            throw new Exception('Pas de document courant');

        // Détermine le préfixe de la table dans laquelle on veut chercher
        if ($table)
        {
            $key=Utils::ConvertString($table, 'alphanum');
            if (!isset($this->structure->lookuptables[$key]))
                throw new Exception("La table de lookup '$table' n'existe pas");
            $prefix='T' . $this->structure->lookuptables[$key]->_id . ':';
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

    public function getTerms()
    {
        if (is_null($this->xapianDocument))
            throw new Exception('Pas de document courant');
          
//        $indexName=array_flip($this->structure['index']);
//        $entryName=array_flip($this->structure['entries']);
          
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

    public function count($countType=0)
    {
        // Si l'argument est une chaine, on considère que l'utilisateur veut
        // une évaluation (arrondie) du nombre de réponses et cette chaine
        // est le libellé à utiliser (par exemple : 'environ %d ')
        if (is_string($countType))
        {
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
            
            case 'equation': return $this->selection->equation;
            case 'rank': return $this->rank;
            case 'start': return $this->start;
            case 'max': return $this->max;
            
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

            case 'sortorder': return  $this->sortOrder;
            case 'sortkey': return  isset($this->sortKey) ? $this->xapianDocument->get_value($this->sortKey) : '';
            
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
     * @return array() un tableau contenant la liste des termes obtenus.
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
     * @return array() un tableau contenant la liste des termes obtenus.
     */
    private function getRequestStopWords($internal=false)
    {
        // Liste des mots vides ignorés
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
     * @return array() un tableau contenant la liste des termes obtenus.
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
        if (debug)
        {
            if (true && isset($this->sort))
                echo 'Valeur de la clé pour le tri en cours : <strong><tt style="background-color: #FFFFBB; border: 1px solid yellow;">', 
                    var_export($this->xapianDocument->get_value($this->sort), true), 
                    '</tt></strong>, docid=', $this->xapianMSetIterator->get_docid(), 
                    ', Score : ',$this->xapianMSetIterator->get_percent(), ' %',
                    '<br/>Match : ', implode(', ', $this->getMatchingTerms()),
                    '<hr />';
        }
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
     * Recherche dans une table des entrées les valeurs qui commence par le terme indiqué.
     * 
     * @param string $table le nom de la table des entrées à utiliser.
     * 
     * @param string $term le terme recherché
     * 
     * @param int $max le nombre maximum de valeurs à retourner (0=pas de limite)
     * 
     * @param int $sort l'ordre de tri souhaité pour les réponses :
     *   - 0 : trie les réponses par nombre décroissant d'occurences dans la base (valeur par défaut)
     *   - 1 : trie les réponses par ordre alphabétique croissant
     * 
     * @param bool $splitTerms définit le format du tableau obtenu. Par défaut 
     * (splitTerms à faux), la fonction retourne un tableau simple associatif de la forme
     * array
     * (
     *     'droit du malade' => 10,
     *     'information du malade' => 3
     * )
     * Quand splitTerms est à true, chaque élément du tableau va être un tableau contenant
     * le nombre d'occurences, la partie à gauche du terme recherché, le mot contenant le terme 
     * recherché et la partie à droite du terme recherché :
     * array
     * (
     *     'droit du malade'=>array(10, 'droit ', 'du', ' malade'),
     *     'information du malade'=>array(3, 'information ', 'du', ' malade')
     * )
     * 
     * @return array
     */
    public function lookup($table, $term, $max=0, $sort=0, $splitTerms=false)
    {
        static $charFroms=
            "\x00\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0a\x0b\x0c\x0d\x0e\x0f\x10\x11\x12\x13\x14\x15\x16\x17\x18\x19\x1a\x1b\x1c\x1d\x1e\x1f\x20\x21\x22\x23\x24\x25\x26\x27\x28\x29\x2a\x2b\x2c\x2d\x2e\x2f\x30\x31\x32\x33\x34\x35\x36\x37\x38\x39\x3a\x3b\x3c\x3d\x3e\x3f\x40\x41\x42\x43\x44\x45\x46\x47\x48\x49\x4a\x4b\x4c\x4d\x4e\x4f\x50\x51\x52\x53\x54\x55\x56\x57\x58\x59\x5a\x5b\x5c\x5d\x5e\x5f\x60\x61\x62\x63\x64\x65\x66\x67\x68\x69\x6a\x6b\x6c\x6d\x6e\x6f\x70\x71\x72\x73\x74\x75\x76\x77\x78\x79\x7a\x7b\x7c\x7d\x7e\x7f\x80\x81\x82\x83\x84\x85\x86\x87\x88\x89\x8a\x8b\x8c\x8d\x8e\x8f\x90\x91\x92\x93\x94\x95\x96\x97\x98\x99\x9a\x9b\x9c\x9d\x9e\x9f\xa0\xa1\xa2\xa3\xa4\xa5\xa6\xa7\xa8\xa9\xaa\xab\xac\xad\xae\xaf\xb0\xb1\xb2\xb3\xb4\xb5\xb6\xb7\xb8\xb9\xba\xbb\xbc\xbd\xbe\xbf\xc0\xc1\xc2\xc3\xc4\xc5\xc6\xc7\xc8\xc9\xca\xcb\xcc\xcd\xce\xcf\xd0\xd1\xd2\xd3\xd4\xd5\xd6\xd7\xd8\xd9\xda\xdb\xdc\xdd\xde\xdf\xe0\xe1\xe2\xe3\xe4\xe5\xe6\xe7\xe8\xe9\xea\xeb\xec\xed\xee\xef\xf0\xf1\xf2\xf3\xf4\xf5\xf6\xf7\xf8\xf9\xfa\xfb\xfc\xfd\xfe\xff";
        
        static $charTo=
            '                                                0123456789      @abcdefghijklmnopqrstuvwxyz      abcdefghijklmnopqrstuvwxyz                                                                     aaaaaaaceeeeiiiidnooooo 0uuuuy saaaaaaaceeeeiiiidnooooo  uuuuyby';

        $key=Utils::ConvertString($table, 'alphanum');
        if (!isset($this->structure->lookuptables[$key]))
            throw new Exception("La table de lookup '$table' n'existe pas");
        $prefix='T' . $this->structure->lookuptables[$key]->_id . ':';
        
        $begin=$this->xapianDatabase->allterms_begin();
        $end=$this->xapianDatabase->allterms_end();

        $count=0;
        if ($max<=0) $max=PHP_INT_MAX;
        $result=array();

        $term=trim($term);
        $term=strtr($term, $charFroms, $charTo);
        if (false === $token=strtok($term, ' ')) 
            $token=''; // term=vide : sort les n premiers de la liste

        $length=strlen($term);
        // fixme: )à revoir, on parcourt toujours tous les termes. si sort=tri alpha, on pourrait s'arrêter avant
        while($token !== false)
        {
            $start=$prefix.$token;
            $begin->skip_to($start);
            
            while (!$begin->equals($end))
            {
                $entry=$begin->get_term();
    
                if ($start !== substr($entry, 0, strlen($start))) 
                    break;
                             
                $entry=substr($entry, strpos($entry, '=')+1);
                $h=strtr($entry, $charFroms, $charTo);
                if (false !== $pt=strpos(' '.$h, ' '.$term))
                {
                    if ($splitTerms)
                    {
                        $left=substr($entry, 0, $pt);
                        $middle=substr($entry, $pt, $length);
                        $right=substr($entry, $pt+$length);                
        
                        if (!isset($result[$entry]))
                            $result[$entry]=array($begin->get_termfreq(), $left, $middle, $right);
                    }
                    else
                    {
                        if (!isset($result[$entry]))
                            $result[$entry]=$begin->get_termfreq();
                    }
                }    
                $begin->next();
            }
            $token=strtok(' ');            
        }

        // Trie des réponses
        switch ($sort)
        {
            case 0:     // Tri par occurences
                if ($splitTerms)
                    uasort($result, create_function('$a,$b','return $b[0]-$a[0];')); // nb décroissants
                else
                    arsort($result, SORT_NUMERIC);
                break;  
            default:    // Tri alpha
                ksort($result, SORT_LOCALE_STRING);
                break;
        }
//echo count($result);
        return array_slice($result,0,$max);
//        return $result; 
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
        while(ob_get_level()) ob_end_flush();
        $this->search(null, array('_sort'=>'+', '_max'=>-1));
        echo $this->count(), ' notices à réindexer. <br />';
        $start=microtime(true);
//        $this->xapianDatabase->begin_transaction(true);
        $i=0;
        foreach ($this as $record)
        {
            $id=$this->xapianMSetIterator->get_docid();
            if (0 === $i % 100)
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
//        $this->xapianDatabase->commit_transaction(true);

        //
        echo sprintf('%.2f', microtime(true)-$start), ', i=', $i, ', id=', $id, ', flush de la base...<br />';
        flush();
        
        $this->xapianDatabase->flush();
        echo sprintf('%.2f', microtime(true)-$start), ', terminé !<br />';
        flush();
    }
    
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
class XapianDatabaseRecord2 extends DatabaseRecord
{
    /**
     * @var Array Liste des champs de cet enregistrement
     */
    private $fields=null;
    
    private $structure=null;
    
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
    public function __construct(& $fields, DatabaseStructure $structure)
    {
        $this->fields= & $fields;
        $this->structure= $structure;
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
        switch ($this->structure->fields[$key]->_type)
        {
            case DatabaseStructure::FIELD_AUTONUMBER:
            case DatabaseStructure::FIELD_INT:
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
                throw new DatabaseFieldTypeMismatch($offset, $this->structure->fields[$key]->type, $value);
                
            case DatabaseStructure::FIELD_BOOL:
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
                    throw new DatabaseFieldTypeMismatch($offset, $this->structure->fields[$key]->type, $value);
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
                throw new DatabaseFieldTypeMismatch($offset, $this->structure->fields[$key]->type, $value);
                
            case DatabaseStructure::FIELD_TEXT:
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
                throw new DatabaseFieldTypeMismatch($offset, $this->structure->fields[$key]->type, $value);
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
        return $this->structure->fields[key($this->fields)]->name;
        /*
         * On ne retourne pas directement key(fields) car sinon on récupère
         * un nom en minu sans accents qui sera ensuite utilisé dans les boucles,
         * les callbacks, etc.
         * Si un callback a le moindre test du style if($name='Aut'), cela ne marchera
         * plus. 
         * On fait donc une indirection pour retourner comme clé le nom exact du
         * champ tel que saisi par l'utilisateur dans la structure de la base.
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