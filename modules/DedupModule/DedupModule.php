<?php
/**
 * @package     fab
 * @subpackage  modules
 * @author      Daniel Ménard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id$
 */

require_once(dirname(__FILE__).'/DedupMethod.php');
require_once(dirname(__FILE__).'/DedupTokens.php');
require_once(dirname(__FILE__).'/DedupValues.php');
require_once(dirname(__FILE__).'/DedupFirstValue.php');
require_once(dirname(__FILE__).'/DedupYears.php');

/**
 * Ce module permet de rechercher et de traiter des doublons potentiels au sein 
 * d'une base de données.
 * 
 * Il fonctionne à partir d'un lot de notices indiqués par une équation de 
 * recherche. Pour chacune des notices sélectionnées, des tests (définis dans 
 * le fichier de configuration) vont être exécutés.
 * 
 * Une fois ces tests exécutés, on obtient une liste de notices qui sont des 
 * doublons potentiels de la notice étudiée.
 * 
 * Le dédoublonnage a proprement parler sera ensuite réalisé par un humain qui
 * décidera si les doublons potentiels détectés sont effectivement des doublons
 * ou non et supprimera ou fusionnera certaines des notices.
 * 
 * @package     fab
 * @subpackage  modules
 */
class DedupModule extends Module
{
    /**
     * La sélection contenant les notices pour lesquelles on va rechercher
     * des doublons potentiels.
     * 
     * @var Database
     */
    public $selection=null;
    
    /**
     * Permet de lancer une recherche de doublons potentiels pour les notices 
     * sélectionnées par l'équation passée en paramètre.
     * 
     * L'action commence par afficher un template à l'utilisateur lui indiquant
     * le nombre de notices sélectionnées et lui demandant de confirmer le 
     * lancement du dédoublonnage.
     * 
     * Elle crée ensuite une tâche au sein du {@link TaskManager} qui exécutera
     * l'action {@link actionDedup() Dedup}.
     *
     * @param string $_equation l'équation de recherche qui définit les notices
     * pour lesquelles on souhaite rechercher des doublons potentiels.
     * 
     * @param bool $confirm un booléen indiquant si l'action a été confirmée.
     * Lorsque <code>$confirm</code> est à <code>false</code>, une demande de 
     * confirmation est demandée à l'utilisateur. Lorsque <code>confirm </code>
     * est à <code>true</code>, la tâche est créée.
     * 
     * @throws Exception si aucune équation de recherche n'a été indiquée ou si
     * aucune notice ne correspond à cette équation.
     */
    public function actionIndex($_equation, $confirm=false)
    {
        // Lance la recherche dans la base
        $this->selection=$this->search($_equation, array('_max'=>-1));
        
        // Demande confirmation à l'utilisateur
        if (!$confirm)
        {
            Template::run
            (
                config::get('template'),
                array('equation'=>$_equation)
            );
            return;
        }

        // Crée une tâche au sein du gestionnaire de tâches
        $id=Task::create()
            ->setRequest($this->request->setAction('Dedup')->keepOnly('_equation'))
            ->setTime(0)
            ->setLabel('Recherche de doublons pour ' . $this->selection->count() . ' notices de la base '.Config::get('database') . ' (equation : ' . $_equation . ')')
            ->setStatus(Task::Waiting)
            ->save()
            ->getId();
            
        Runtime::redirect('/TaskManager/TaskStatus?id='.$id);
    }
    
    /**
     * Exécute le dédoublonnage des notices 
     *
     * @param string $_equation
     */
    public function actionDedup($_equation)
    {
        // Lance la recherche dans la base
        $this->selection=$this->search($_equation, array('_max'=>-1));
        
        echo '<h1>Recherche de doublons pour l\'équation ', $_equation, ' : ', $this->selection->count(), ' notices à étudier</h1>';
        
        $tests=Config::get('tests');
        
        $this->dumpTests($tests);
        
        echo '<hr />';
        
        // Lance les tests
        $this->runTests($tests, Config::get('format1'), Config::get('format2'));
    }
    private function prepareTests($tests)
    {
        
    }
    
    /**
     * Affiche une description "en clair" des tests qui seront appliqués
     * aux notices
     */
    private function dumpTests(array $tests)
    {
        echo "Pour chacune des notices sélectionnées, le programme va rechercher dans la base :<br />";

        echo "<ul>";
        foreach($tests as $num=>$test)
        {
            echo '<li>';
            
            $cond=array(); // conditions
            $t=array(); // tests à faire
            foreach ($test as $field=>$options)
            {
                if (is_scalar($options))
                {
                    $cond[$field]=$options;
                }
                else
                {
                    $t[$field]=$options;
                    $cond[$field]=null;
                }
            }
            
            if ($t)
            {
                $first=true;
                echo 'des notices avec ';
                foreach($t as $field=>$options)
                {
                    if (!$first) echo ' et ';
                    if (is_null($options))
                    {
                        echo 'un champ ', $field, ' identique    ';
                    }
                    else
                    {
                        $min=(int)Utils::get($options['min'], '100%');
                        if ($min===100)
                            echo 'un champ ', $field, ' identique';
                        else
                            echo 'un champ ', $field, ' similaire à ', $min, '%';
                    }
                    $first=false;
                        
                }
            }
            if ($cond)
            {
                $first=true;
                echo ' (si ';
                foreach($cond as $field=>$options)
                {
                    if (!$first) echo ' et ';
                    if (is_null($options))
                        echo $field, ' est renseigné';
                    else
                        echo $field, '=', $options;
                    $first=false;
                }
                //echo '';
            }
            echo ') ;';
        }
        echo "</ul>";
    }

    
    /**
     * Exécute tous les tests passés en paramètre sur les notices présentes
     * dans la sélection en cours
     *
     * @param Array $tests les tests à exécuter
     * @param String $format le format d'affichage des notices (le source
     * d'un template)
     */
    private function runTests(array $tests, $format1, $format2)
    {
        $path1=__FILE__.'/'.md5($format1).'.html';
        $path2=__FILE__.'/'.md5($format2).'.html';
        
        $duplicates=$this->search(null);
        echo "\n", '<ol>', "\n";
        foreach($this->selection as $rank=>$record)
        {        
            // Dump la notice en cours
            $equation=$this->createEquation($tests);
                                    
            if ($equation==='')
            {
                echo '<li>';
                Template::runSource($path1, $format1, $record);
                echo '<p style="color: red">WARNING : impossible de dédoublonner cette notice, aucun test ne s\'applique<p>';
                echo '</li>';
                continue;
            }

            if (debug) echo 'Equation générée : <a href="',Routing::linkFor('/Base/search?_defaultop=or&_equation='.urlencode($equation)), '">', $equation, '</a><br />';

            if ($duplicates->search($equation, array('_sort'=>'%', '_max'=>50)))
            {
                if (debug) echo $duplicates->searchInfo('internalquery'), '<br />';
                if (debug) echo $duplicates->count(), ' réponses<br />';

                $first=true;
                $hasDuplicates=false;
                foreach($duplicates as $duplicate)
                {
                    if (debug) echo '----------------------<br />';
                    $score=0;
                    if ($this->isDuplicate($tests, $duplicate, $score))
                    {
                        if ($first) // premier doublon trouvé : affiche la notice étudiée
                        {
                            echo '<li>';
                            Template::runSource($path1, $format1, $record);
                            echo "\n", '    <ul>', "\n";
                            $hasDuplicates=true;
                            $first=false;
                        }
                        echo '        <li>';
                        echo $duplicates->searchInfo('score'), '% - ';
                        Template::runSource($path2, $format2, array('REFMAIN'=>$record['REF']), $duplicate);
                        echo '</li>', "\n";
                    }
                }
                if ($hasDuplicates)
                {
                    echo '    </ul>', "\n";
                    echo '</li>', "\n";
                }
            }
            TaskManager::progress($rank, $this->selection->count());
        }
        
        echo '</ol>', "\n";
    }

    
    /**
     * Crée une équation de recherche permettant de rechercher les doublons 
     * potentiels pour la notice en cours à partir des tests passés en 
     * paramètre.
     * 
     * La fonction examine tous les tests indiqués et va créer une équation de 
     * recherche pour ceux qui peuvent être appliqués à la notice en cours.
     * 
     * Les équations provenant de chacun des tests retenus sont combinées en
     * OU pour former l'équation finale à exécuter.
     * 
     * @return string|null l'équation finale obtenue ou null si aucun des tests
     * indiqués ne s'applique à la notice en cours.
     */
    private function createEquation($tests)
    {
        $compare=array();
        if (debug) echo '<br /><br />';
        $equations=array();
        foreach($tests as $numTest=>$test)
        {
            $equation=array();
            foreach ($test as $field=>$options)
            {
                // Le champ indique une valeur (par exemple <type>ouvrage</type>)
                if (is_scalar($options))
                {
                    // Le test ne s'applique que si la notice en cours a la valeur indiquée
                    if (! $this->fieldContains($field, $options))
                    { 
                        if (debug) echo 'Test ', $numTest+1, ' ignoré (', $field, ' ne contient pas ', $options, ')<br />';
                        continue 2;
                    }

                    // tester dans notice étudiée si field=options
                    $method='DedupTokens';
                    $method=new $method($this->selection);
                
                    
                    $equation[] = $field.'='.$method->getEquation($options);//dmdm;

                }
                
                // Le champ indique des critères (type, min...)
                else
                {
                    // Récupère la valeur du champ
                    $value=$this->selection[$field];
                        
                    if (is_null($value) || $value==='')
                    { 
                        if (debug) echo 'Test ', $numTest+1, ' ignoré (', $field, ' non renseigné)<br />';
                        continue 2;
                    }
                    
                    $method='Dedup' . Utils::get($options['compare'], 'tokens');
                    $method=new $method($this->selection);
                
                    $value=$method->getEquation($value);

                    $equation[]=$field . ':' . $value;
                }
            }
            
            if (debug) echo 'Test ', $numTest+1, ' applicable<br />';
            $equations[]=implode(' AND ', $equation);
        }
        
        if (debug) echo '<br />';
        
        // Retourne le résultat
        return '(' . implode(' OR ', $equations) . ') -REF:' . $this->selection['REF'];
    }

    /**
     * Teste si les deux notices passées en paramètre sont effectivement des
     * doublons en utilisant les tests indiqués
     *
     * @param array $tests
     * @param DatabaseRecord $selection
     * @param DatabaseRecord $duplicate
     * @return bool
     */
    private function isDuplicate(array $tests, DatabaseRecord $duplicate)
    {
        foreach ($tests as $numTest=>$test)
        {
            if (debug) echo '<br />test ', $numTest+1, ' :<br />';
            foreach($test as $field=>$options)
            {
                // cas d'une valeur
                if (is_scalar($options))
                {
                    if (! $this->fieldContains($field, $options)) 
                    {
                        if (debug) echo 'On n\'a pas ', $field, '=', var_export($options,true), ' dans le doublon, passage au test suivant<br />';
                        continue 2;
                    }
                    if (debug) echo 'On a bien ', $field, '=', var_export($options,true), ' dans le doublon<br />';
                    continue;   
                }
                
                if (is_null($options))
                {
                    $value=$this->selection[$field];
                    if (is_null($value) || $value==='') 
                    {
                        if (debug) echo $field, '=', var_export($options,true), ' dans le doublon, non rempli, passage au test suivant<br />';
                        continue 2;
                    }
                }

                $min=(int)Utils::get($options['min'], '100%');

                $method='Dedup' . ucfirst(Utils::get($options['compare'], 'tokens'));
                if (debug) echo 'comparaison des tokens de ', $field, ' avec la méthode ', $method, '<br />';
                $method=new $method($this->selection);
                
                $score=$method->compare($this->selection[$field], $duplicate[$field]);
                if ($score < $min)
                {
                    if (debug) echo 'score inférieur à min=', $min, ', passage au test suivant<br />';
                    continue 2;
                }
                if (debug) echo 'score &gt;= à min=', $min, ', on continue<br />';
            }
            if (debug) echo 'tous les tests ont réussis<br />';
            return true;
        }
        return false;
    }
    
    /**
     * Teste si le champ indiqué contient la valeur recherchée.
     * 
     * Si le champ est multivalué (champ articles), le test réussi si l'un
     * des articles correspond à la valeur recherchée, sinon, le test réussi
     * si le champ contient la valeur recherchée.
     * 
     * La comparaison ne tient pas compte de la casse des caractères ni des
     * accents.
     * 
     * @param string $field le nom du champ dans lequel il faut rechercher
     * @param string|null $value la valeur recherchée
     * @return bool true si le champ contient la valeur recherchée.
     */
    private function fieldContains($field, $value)
    {
        // Récupère la valeur du champ
        $field=$this->selection[$field];
        
        // Convertit la valeur recherchée en minuscules et supprime les accents
        $value=trim(Utils::convertString($value, 'lower'));
        
        // Si le champ est vide, value doit être vide aussi 
        if (is_null($field))
        {
            return is_null($value) || $value==='';
        }
        
        // Si le champ est monovalué, on compare directement les valeurs
        if (is_scalar($field))
        {
            return trim(Utils::convertString($field, 'lower')) === $value;
        }
        
        // Si le champ est un champ articles, teste si l'un des articles correspond à la valeur recherchée
        if (is_array($field))
        {
            foreach($field as $v)
            {
                if (trim(Utils::convertString($v, 'lower')) === $value) return true;
            }
            return false;
        }

        // Autre chose ?
        throw new Exception('non géré '.var_export(string,true));
        
    }
    
    /**
     * Supprime de la valeur passée ne paramètre value tout ce qui peut être 
     * génant dans une équation de recherche (opérateurs booléens, + et -, 
     * guillemets, crochets, parenthèses...)
     *
     * @param unknown_type $value
     * @return unknown
     */
    private function cleanupValue($value)
    {
        if (is_scalar($value))
        {
            $value=preg_replace('~\b(?:et|ou|sauf|and|or|not|but)\b~i', '', $value);
            //$value=strtr($value, '"[]()+-:.', '         ');
            $value=implode(' ', Utils::tokenize($value));
            return $value;
        }
        
        foreach($value as &$v)
        {
            $v=$this->cleanupValue($v);
        }
        return $value;
    }
    
    protected function search($equation=null, $options=null)
    {
        // Le fichier de config du module indique la base à utiliser
        $database=Config::get('database');

        if (is_null($database))
            throw new Exception('La base de données à utiliser n\'a pas été indiquée dans le fichier de configuration du module');

        $selection=Database::open($database);
        if (! is_null($equation))
        {
            if (! $selection->search($equation, $options))
                throw new Exception("Aucune réponse pour l'équation $equation");
        }
        return $selection;
    }
    
    public function actionEdit(array $REF)
    {
        if (count($REF) !== 2)
            throw new InvalidArgumentException('Vous devez indiquer deux numéros de notices valides.');
            
        Template::run
        (
            'edit.html', 
            array('REF'=>$REF)
        );
    }
}


?>