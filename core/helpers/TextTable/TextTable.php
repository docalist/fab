<?php

/*
 * TextTable objet d'accès à une table au format csv
 *
 * A partir d'un fichier format CSV , créer un objet qu'on peut parcourir comme un tableau :
 * implémente les interfaces Iterator
 *
 * @param $filePath passé au constructeur est le chemin d'accès au fichier CVS à lire
 */
 class TextTable implements Iterator
 {

    private $file=false;        // Descripteur du fichier CVS passé utilisé comme source de données
    private $eof=true;          // True si eof atteint
    private $delimiter=null;    // délimiteur du fichier csv
    private $enclosure=null;    // TODO: faire doc
    private $ignoreEmpty;       // true si on ignore les enregistrements "vides" (contenant seulement $delimiter, espaces ou $enclosure)
    private $header=null;       // La ligne d'entête
    private $record=null;       // L'enreg en cours
    private $line=0;            // Numéro de la ligne en cours

    /*
     * @param $filePath string chemin d'accès au fichier
     * @param $delimiter char délimiteur d'enregistrements (tabulation par défaut)
     * @param $ignoreEmptyRecord bool indiquant si les enregistrements vides sont ignorés (false par défaut)
     */
    public function __construct($filePath, $ignoreEmptyRecord=true, $delimiter="\t", $enclosure='"')
    {
        $this->delimiter=$delimiter;
        $this->enclosure=$enclosure;
        $this->ignoreEmpty = $ignoreEmptyRecord;

        // todo : traiter les chemins relatifs

        if(! is_file($filePath))
            throw new Exception('Fichier non trouvée : ' . $filePath);

        $this->file = @fopen($filePath, 'r'); // @ = pas de warning si file not found
        if ($this->file===false)
            throw new Exception('Table non trouvée : '.$filePath);

        // Charge la ligne d'entête contenant les noms de champs
        $this->header=$this->getLine();
    }

    public function __destruct()
    {
        if($this->file)
        {
            fclose($this->file);
            $this->file=false;
            $this->line=0;
            $this->delimiter="\t";
            $this->enclosure='"';
            $this->ignoreEmpty=true;
        }
    }

    // Interface Iterator
    public function rewind()
    {
        $this->next();
    }

    public function current()
    {
        return $this->record;
    }

    public function key()
    {
        // return le numéro de la ligne en cours
        return $this->line;
    }

    public function next()
    {
        // Charge la ligne suivante
        if (false === $this->record=$this->getLine()) return;
        $this->record=array_combine($this->header, $this->record);
        ++$this->line;
        if (!isset($this->header['line']))
            $this->record['line']=$this->line;
    }

    public function valid()
    {
        return !$this->eof;
    }

    /*
     * Lit une ligne dans le fichier en passant éventuellement les lignes vides
     *
     * @return mixed false si eof ou fichier non ouvert, un tableau contenant la
     * ligne lue sinon
     */
    private function getLine()
    {
        $this->eof=false;
        for(;;)
        {
            if ((! $this->file) or (feof($this->file))) break;
            $t=fgetcsv($this->file, 1024, $this->delimiter, $this->enclosure);
            if ($t===false) break;
            if (! $this->ignoreEmpty) return $t;
            if (trim(implode('', $t), ' ')!=='') return $t;
        }
        $this->eof=true;
        return false;
    }


    /**
     * Charge le fichier texte indiqué en paramêtre et retourne un tableau représentant le contenu
     * de la table.
     *
     * Les tables sont compilées à la volée et stockées dans le cache de l'application. Lors du
     * tout premier appel, le fichier texte est chargé puis est stocké sous la forme d'un script
     * php dans le cache. Lors des appels suivants, le fichier php est simplement inclus.
     *
     * Si le fichier d'origine est modifié, la table est recompilée automatiquement pour mettre à
     * jour la version stockée en cache.
     *
     * Un cache de second niveau est également utilisé pour stocker les tables ouvertes. Lors du
     * premier appel, le fichier php en cache est chargé. Lors des appels suivants, les données
     * déjà chargées sont simplement retournées à l'appellant.
     *
     * @param array $table le nom de la table à charger. Il peut s'agir du path exact de la table
     * relatif à la racine de l'application (Runtime::$root) ou d'un nom symbolique définit dans la
     * section <code><alias></code> de la configuration.
     *
     * @return array un tableau contenant les entrées de la table.
     *
     * Les clés du tableau correspondent à la première colonne de la table. Les valeurs contiennent
     * les colonnes restantes. Si la table ne contient que deux colonnes, la valeur est une chaine
     * de caractères contenant la deuxième colonne. Sinon, la valeur est un tableau qui contient
     * toutes les colonnes sauf la première.
     *
     * La clé spéciale 'Table_Headers' est un tableau contenant les noms des colonnes de la table.
     */
    private static function getLookupTable($table)
    {
        // Liste des tables déjà ouvertes
        static $tables = array();

        // Cas d'une table déjà ouverte
        if (isset($tables[$table]))
            return $tables[$table];

        // Si la table indiquée est un alias, on le convertit
        $key = $table;
        $alias = Config::get('alias');
        if (isset($alias[$table])) $table = $alias[$table];
        $table = Utils::makePath(Runtime::$root, $table);

        // Vérifie que la table demandée existe
        if (! file_exists($table))
            throw new Exception("Impossible de trouver la table '$table'");

        // Retourne la table depuis le cache si elle est à jour
        if ( Cache::has($table, filemtime($table)) )
            return $tables[$key] = require(Cache::getPath($table));

        // Ouvre le fichier
        $file = fopen($table, 'r');

        // Charge les entêtes de colonne
        $data = fgetcsv($file, 1024, "\t");
        $result = array('Table_Headers' => $data);

        // Lit les données
        while (false !== $data = fgetcsv($file, 1024, "\t"))
        {
            $code = array_shift($data);
            if (count($data)===1) $data = array_shift($data);
            $result[$code] = $data;
        }

        // Ferme le fichier
        fclose($file);

        // Stocke la table en cache (dans les deux)
        Cache::set
        (
            $table,
            sprintf
            (
                "<?php\n".
                "// Fichier généré automatiquement à partir de '%s'\n".
                "// Ne pas modifier.\n".
                "//\n".
                "// Date : %s\n\n".
                "return %s;\n",
                $table,
                @date('d/m/Y H:i:s'),
                var_export($result, true)
            )
        );
        $tables[$key] = $result;

        // retourne la table
        return $result;
    }


    /**
     * Recherche une entrée dans un fichier texte représentant une table de Lookup.
     *
     * @param string $table Nom de la table à utiliser.
     * Il peut s'agir du path exact de la table (relatif à Runtime::$root, la racine de
     * l'application), par exemple "/tables/pays.txt" ou d'un nom symbolique définit dans la
     * section <code><alias></code> de la configuration (exemple : "Pays").
     *
     * @param string $key La valeur recherchée (exemple : "FRA").
     *
     * Remarque :
     * La valeur est toujours recherchée dans la première colonne de la table
     * (nommée "code", en général) et la recherche est effectuée en tenant compte de la casse des
     * caractères.
     *
     * @param string $return Indique ce que la méthode doit retourner si
     * elle trouve la valeur recherchée.
     *
     * Par défaut, lorsque $return vaut null, la méthode retourne le contenu de la deuxième
     * colonne de la table (nommé "label", en général).
     *
     * Pour obtenir une autre colonne, indiquez son nom dans $return. Une erreur sera générée si
     * la colonne indiquée n'existe pas dans la table (le nom est sensible à la casse).
     *
     * Vous pouvez également indiquer dans $return la valeur spéciale "*". Dans ce cas, la totalité
     * de l'entrée sera retournée sous la forme d'un tableau dont les clés correspondent aux entêtes
     * de la table et dont les valeurs correspondent à l'entrée trouvée.
     *
     * @return string|array Si la valeur recherchée a été trouvée la méthode retourne le contenu
     * du champ indiqué dans $return. Sinon, elle retourne la valeur recherchée ($key).
     */
    public static function lookup($table, $key, $return = null)
    {
        // Charge la table indiquée
        $table = self::getLookupTable($table);

        // Si la valeur recherche n'existe pas dans la table, retourne la valeur inchangée
        if (! isset($table[$key])) return $key;
        $data = $table[$key];

        // Retourne le contenu de la seconde colonne si $return est null
        if (is_null($return))
        {
            if (is_scalar($data)) return $data;
            return $data[0];
        }

        // Reconstruit l'entrée en utilisant les entêtes de colonnes comme clés
        if (is_scalar($data))
            $data = array($key, $data);
        else
            array_unshift($data, $key);

        $data = array_combine($table['Table_Headers'], $data);

        // Retourne tous les champs si $return vaut '*'
        if ($return === '*')
                return $data;

        // Génère une erreur si la colonne demandée n'existe pas
        if (! isset($data[$return]))
            throw new Exception("La colonne $return n'existe pas dans la table");

        // Retourne la colonne demandée
        return $data[$return];
    }
}