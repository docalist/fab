<?php

/**
 * @package     fab
 * @subpackage  modules
 * @author      Daniel Ménard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id: Database.php 105 2006-09-21 16:30:25Z dmenard $
 */

class Optional implements ArrayAccess
{
    private $data=null;
    
	public function __construct($data)
    {
    	if (! is_array($data))
            throw new Exception('Vous devez passer un tableau pour créer un objet '.get_class());
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
 * Ce module permet de publier une base de données sur le web
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
     * @var Database La sélection en cours
     * @access protected
     */
    public $selection=null;
    
    /**
     * Ouvre la base de données du module
     * 
     * Si une équation est indiquée, une recherche est lancée.
     * 
     * @param string $equation optionnel, l'équation de recherche à lancer
     * 
     * @param readOnly indique si la base doit être ouverte en lecture seule
     * (valeur par défaut) ou en lecture/écriture
     * 
     * @return boolean true si une recherche a été lancée et qu'on a au moins 
     * une réponse, false sinon
     */
    private function OpenSelection($equation=null, $readOnly=false)
    {
        // Le fichier de config du module indique la base à utiliser
        $database=Config::get('database');
        if (is_null($database))
            throw new Exception('La base de données à utiliser n\'a pas été indiquée dans le fichier de configuration du module');

        debug && Debug::log("Ouverture de la base '%s' en mode '%s'", $database, $readOnly ? 'lecture seule' : 'lecture/écriture');
        $this->selection=Database::open($database, $readOnly);
        
        if ($equation)
        {
            $result=$this->selection->search($equation, $_REQUEST);
            debug && Debug::log("Requête : %s, %s réponse(s).", $equation, $this->selection->count());
            return $result;
        }
            
        return false;	
    }
    
    /**
     * Affiche le formulaire de recherche indiqué dans la clé 'template' de la
     * configuration, en utilisant le callback indiqué dans la clé 'callback' de
     * la configuration. Si le callback indiqué est 'none' alors aucun callback 
     * n'est appliqué. Si la clé 'callback' n'est pas défini, c'est le callback
     * par défaut 'getField' qui est utilisé.
     */
    public function actionSearchForm()
    {
        // Détermine le template à utiliser
        if (! $template=$this->getTemplate())
            throw new Exception('Le template à utiliser n\'a pas été indiqué');
        
        // Détermine le callback à utiliser
        $callback=$this->getCallback();
               
        // Exécute le template
//        echo $template;
        Template::run
        (
            $template,  
            array($this, $callback),           // Priorité à la fonction utilisateur
            new Optional($_REQUEST)
//            $_REQUEST,                          // Champs passés en paramètre
//            array('Template', 'EmptyCallback')  // Effacer tous les autres
        );
//        echo "here";
// TODO: remettre en place le rappel des éléments de formulaire
//        Template::run
//        (
//            $template,  
//            array($this, $callback),            // Priorité à la fonction utilisateur
//            $_REQUEST,                          // Champs passés en paramètre
////            'Template::emptyCallback'           // Effacer tous les autres
//            array('Template', 'EmptyCallback')
//        );
    }

    /**
     * Affiche un message si une erreur s'est produite lors de la recherche.
     * Le template à utiliser est indiqué dans la clé 'errortemplate' de la 
     * configuration de l'action 'search'.
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
     * Lance une recherche si une équation peut être construite à partir des 
     * paramètres passés et affiche les notices obtenues en utilisant le template 
     * indiqué dans la clé 'template' de la configuration.
     * Si aucun paramètre n'a été passé, redirige vers le formulaire de recherche.
     * Si erreur lors de la recherche, affiche l'erreur en utilisant le template
     * indiqué dans la clé 'errortemplate' de la configuration. 
     */
    public function actionSearch()
    {
        // Construit l'équation de recherche
        $this->equation=$this->makeEquation('start,max,sort');
        
        
        // Si aucun paramètre de recherche n'a été passé, il faut afficher le formulaire
        // de recherche
        if (is_null($this->equation))
            Runtime::redirect('searchform');
        
        // Des paramètres ont été passés, mais tous sont vides et l'équation obtenue est vide
        if ($this->equation==='')
            return $this->showError('Vous n\'avez indiqué aucun critère de recherche.');
        
        // Lance la récherche
        if (! $this->openSelection($this->equation))
            return $this->showError("Aucune réponse. Equation : $this->equation");

        // Détermine le template à utiliser
        if (! $template=$this->getTemplate('template'))
            throw new Exception('Le template à utiliser n\'a pas été indiqué');
        
        // Détermine le callback à utiliser
        $callback=$this->getCallback();

        // Exécute le template
        Template::run
        (
            $template,  
            array($this, $callback),
            array('selection'=>$this->selection),
            $this->selection->record
        );                
    }
    
    /**
     * Affiche une ou plusieurs notices en "format long"
     * Le(s) numéro(s) de la (des) notice(s) à afficher doit être indiqué
     * dans 'REF'. 
     * 
     * Génère une erreur si REF n'est pas renseigné ou ne correspond pas à une
     * notice existante de la base.
     */
    public function actionShow()
    {
        // Construit l'équation de recherche
        $this->equation=$this->makeEquation('start,nb');
        
        // Si aucun paramètre de recherche n'a été passé, erreur
        if (is_null($this->equation))
            return $this->showError('Le numéro de la référence à afficher n\'a pas été indiqué');
        
        // Des paramètres ont été passés, mais tous sont vides et l'équation obtenue est vide
        if ($this->equation==='')
            return $this->showError('Vous n\'avez indiqué aucun critère permettant de sélectionner la notice à afficher');
        
        // Ouvre la sélection
        if (! $this->openSelection($this->equation))
            return $this->showError('La référence demandée n\'existe pas');

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
            array('selection'=>$this->selection, 'record'=>$this->selection->record),
            $this->selection->record
        );  
    }
    
    
    /**
     * Création d'une notice
     * Affiche le formulaire indiqué dans la clé 'template' de la configuration.
     */
    public function actionNew()
    {        
        $template = $this->getTemplate();
        $callback = $this->getCallback();
        
        // On exécute le template correspondant
        // Exécute le template
        Template::run
        (
            $template,  
            array($this, $callback)
        );      
    } 
    
    
    /**
     * Edition d'une notice
     * Affiche le formulaire indiqué dans la clé 'template' de la configuration.
     * La notice correspondant au paramètre 'REF' est chargée dans le formulaire
     */
    public function actionLoad()
    {        
        // Construit l'équation de recherche
        $this->equation=$this->makeEquation('start,nb');
        
        // Si aucun paramètre de recherche n'a été passé, erreur
        if (is_null($this->equation))
            throw new Exception('Le numéro de la référence à afficher n\'a pas été indiqué');
        
        // Des paramètres ont été passés, mais tous sont vides et l'équation obtenue est vide
        if ($this->equation==='')
            echo 'EQUATION VIDE';
            //TODO
        
        // Détermine le template à utiliser
        if (! $template=$this->getTemplate())
            throw new Exception('Le template à utiliser n\'a pas été indiqué');
        
        // Détermine le callback à utiliser
        $callback=$this->getCallback();
        
        // Si un numéro de référence a été indiqué, on charge cette notice         
        // Vérifie qu'elle existe
        if (! $this->openSelection($this->equation))
            throw new Exception('La référence demandée n\'existe pas');      

        Template::run
        (
            $template,  
            array($this, $callback),
            array('selection'=>$this->selection, 'record'=>$this->selection->record),
            $this->selection->record
        );             
    }
    
    /**
     * Sauvegarde la notice désignée par 'REF' avec les champs passés en
     * paramètre.
     * Redirige ensuite l'utilisateur vers l'action 'show'
     */
    public function actionSave()
    {
        global $selection;
        
        // Si un numéro de référence a été indiqué, on charge cette notice         
        if ($ref=Utils::get($_REQUEST['REF']))
        {
            // Ouvre la sélection
            debug && Debug::log('Chargement de la notice numéro %i', $ref);
            if (! $this->openSelection("REF=$ref", false))
                throw new Exception('La référence demandée n\'existe pas');
                
            // Edite la notice
            $this->selection->editRecord();
        }
        
        // Sinon, on en créée une nouvelle
        else
        {
            // Ouvre la sélection
            debug && Debug::log('Création d\'une nouvelle notice');
            $this->openSelection('', false); 

            // Crée une nouvelle notice
            $this->selection->addRecord();
            // Récupère le numéro de la notice créée
            $ref=$this->selection['REF'];
            debug && Debug::log('Numéro de la notice créée : %s', $ref);
        }            
       
       // Mise à jour de chacun des champs
        $record=& $this->selection->record;
        foreach($this->selection->record as $fieldName => $fieldValue)
        {
            //echo "fieldName=$fieldName, fieldValue=$fieldValue<br />";
            if ($fieldName==='REF') continue;
            
            $newValue=Utils::get($_REQUEST[$fieldName], '');
            $record[$fieldName]=$newValue;
        }

        // Enregistre la notice
        debug && Debug::log('Sauvegarde de la notice');
        $this->selection->saveRecord();   // TODO: gestion d'erreurs
        
        // redirige vers le template s'il y en a un, vers l'action show sinon
        if (! $template=$this->getTemplate())
        {
            // Redirige l'utilisateur vers l'action show
            debug && Debug::log('Redirection pour afficher la notice enregistrée');
            Runtime::redirect('show?REF='.$ref);
        }
        else
        {
            Template::run
            (
                $template,
//                array($this, $callback),  TODO
                array('selection'=>$this->selection, 'record'=>$this->selection->record),
                $this->selection->record
            );
        }
    }
    
    /**
     * Supprime la notice indiquée puis affiche le template indiqué dans la clé
     * 'template' de la configuration.
     * 
     * Si aucun template n'est indiqué, affiche un message 'notice supprimée'.
     */
    public function actionDelete()
    {
        // Détermine la référence à supprimer         
        if (! $ref=Utils::get($_REQUEST['REF']))
            throw new Exception('Le numéro de la référence à supprimer n\'a pas été indiqué');
        
        // Ouvre la base sur la référence demandée
        if( ! $this->openSelection("REF=$ref", false) )
            throw new Exception('La référence demandée n\'existe pas');
        
        // Vérifie qu'elle existe
        if ($this->selection->count()==0)
            throw new Exception('La référence demandée n\'existe pas');
            
        // Supprime la notice
        $this->selection->deleteRecord();
        
        // Détermine le template à utiliser
        if (! $template=$this->getTemplate())
            echo '<p>Notice supprimée.</p>';

        // Exécute le template
        else
        {
            // Détermine le callback à utiliser
            $callback=$this->getCallback();

            Template::run
            (
                $template,  
                array($this, $callback)
            );
        }
    }
    
    /**
     * Affiche le formulaire de type Chercher/Remplacer indiqué dans la clé
     * template de la configuration
     */
     public function actionReplaceForm()
     {
        //TODO: Faut-il gérer un éventuel callback ici ?

        // Détermine le template à utiliser
        if (! $template=$this->getTemplate())
            throw new Exception('Le template à utiliser n\'a pas été indiqué');
        
        // Détermine le callback à utiliser
        $callback=$this->getCallback();
               
        // Exécute le template
        Template::run
        (
            $template,  
            array($this, $callback),           // Priorité à la fonction utilisateur
            new Optional($_REQUEST)
        );
     }
    
    /**
     * Effectue le chercher/remplacer et appelle le tempalte indiqué dans la clé
     * template de la configuration ensuite : feedback
     */
     
     // NEW VERSION
     public function actionReplace()
     {
        //TODO : gestion d'erreurs
        //TODO : nettoyage
                
        // Récupérer l'équation de recherche
        $this->equation=Utils::get($_REQUEST['equation']);
        $search=Utils::get($_REQUEST['searchStr']);
        $replace=Utils::get($_REQUEST['replaceStr']);
        $wholeWord=Utils::get($_REQUEST['wholeWord']);

        // Si aucun paramètre de recherche n'a été passé ou si rien n'est à remplacer, il faut rediriger vers le formulaire replaceform
        if (is_null($this->equation) || is_null($search))
            Runtime::redirect('replaceform');
        
        if ($wholeWord)     // expression régulière pour le chercher/remplacer
        {
            $pattern = '~\b' . preg_quote($search) . '\b~';   
        }
        else
        {
            $pattern = '~' . preg_quote($search) . '~';
        }
        
        echo "PATTERN = $pattern<br />";
        
        // Des paramètres ont été passés, mais tous sont vides et l'équation obtenue est vide
        if ($this->equation==='')
            return $this->showError('Vous n\'avez indiqué aucun critère de recherche.');
        
        // Lance la récherche
        if (! $this->openSelection($this->equation))
            // TODO : il n'y a rien a remplacer
            return $this->showError("Aucune réponse. Equation : $this->equation");
        
        echo "search = $search<br />replace = $replace<br />";
        
        // TODO: déléguer le boulot au TaskManager (exécution peut être longue)
        foreach($this->selection as & $record)
        {            
            $this->selection->editRecord();

            if( ! $this->selection->pregReplace($pattern, $replace))
                echo 'RIEN DE REMPLACE<br />';
                
            $this->selection->saveRecord();
        }
        
        die();
        
        
        // Détermine le template à utiliser
        if (! $template=$this->getTemplate('template'))
            throw new Exception('Le template à utiliser n\'a pas été indiqué');
        
        // Détermine le callback à utiliser
        $callback=$this->getCallback();

        // Exécute le template
        Template::run
        (
            $template,  
            array($this, $callback),
            array('selection'=>$this->selection),
            $this->selection->record
        ); 
     }

     
    // ****************** fonctions surchargeables ***************
    /**
     * Retourne le template à utiliser pour l'action en cours ({@link action})
     * 
     * Par défaut, la fonction retourne le nom du template indiqué dans la clé
     * 'template' du fichier de config, mais les descendants de BisDatabase
     * peuvent surcharger cette fonction pour retourner autre chose (utile par
     * exemple si le template à utiliser dépends de certaines conditions telles
     * que les droits qu'a l'utilisateur en cours).
     * 
     * Dans le fichier de configuration, vous pouvez indiquer soit le nom d'un 
     * template qui sera utilisé dans tous les cas, soit un tableau qui va
     * permettre d'indiquer le template à utiliser en fonction des droits de
     * l'utilisateur en cours.
     * 
     * Dans ce cas, les clés du tableau indiquent le droit à avoir et la valeur
     * le template à utiliser. Le tableau est examiné dans l'ordre indiqué. A
     * vous d'organiser les clés pour que les droits les plus restrictifs
     * apparaissent en premier.
     * 
     * Remarque : Vous pouvez utiliser le pseudo droit 'default' pour indiquer
     * le template à utiliser lorsque l'utilisateur ne dispose d'aucun des
     * droits indiqués.
     * 
     * @return string le nom du template à utiliser ou null
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
            
        if (file_exists($h=$this->path . $template)) // pb : template relatif à BisDatabase, pas au module hérité (si seulement config)
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
    
    // ****************** fonctions privées ***************
    
/**
     * Crée une équation à partir des paramètres de la requête.
     * 
     * Les paramètres qui ont le même nom sont combinés en 'OU', les paramètres
     * dont le nom diffère sont combinés en 'ET'.
     * 
     * Un même paramètre peut être combiné plusieurs fois : les différentes
     * valeurs seront alors combinés en 'OU'.
     * 
     * Un paramètre peut contenir une simple valeur, une expression entre
     * guillemets doubles ou une équation de recherche combinant des valeurs ou
     * des expressions avec des opérateurs. Les opérateurs seront remplacés
     * lorsqu'ils ne figurent pas dans une expression. (avec tit="a ou b"&tit=c
     * on obtient tit="a ou b" et tit=c et non pas tit="a ou tit=b" et tit=c)
     * 
     * Lors de la création de l'équation, les paramètres dont le nom commence
     * par 'bq', les noms 'module' et 'action' ainsi que le nom utilisé
     * comme identifiant de session (sessin_name) sont ignorés. Vous pouvez
     * indiquer des noms supplémentaires à ignorer en passant dans le paramètre
     * ignore une chaine contenant les noms à ignorer (séparés par une virgule).
     * Par exemple si votre formulaire a des paramètres nommés 'max' et 'order',
     * ceux-ci seront par défaut pris en compte pour construire l'équation (vous
     * obtiendrez quelque chose du style "tit=xxx et max=100 et order=1", ce qui
     * en général n'est pas le résultat souhaité). Pour obtenir le résultat
     * correct, indiquez la chaine "max, order" en paramètre.
     * 
     * @param string $ignore optionnel, paramètres supplémentaires à ignorer, 
     * séparés par une virgule (remarque : insensible à la casse).
     * 
     * @return mixed soit une chaine contenant l'équation obtenue, soit une
     * chaine vide si tous les paramètres passés étaient vides (l'utilisateur a
     * validé le formulaire de recherche sans rien remplir), soit null si aucun
     * paramètre n'a été passé dans la requête (l'utilisateur a simplement
     * demandé l'affichage du formulaire de recherche)
     */
    public function makeEquation($ignore='')
    {
        if ($ignore) $ignore='|' . str_replace(',', '|', preg_quote($ignore, '~'));
        $namesToIgnore="~module|action|bq.+|".preg_quote(session_name())."$ignore~i";
        
        $equation='';
        $hasFields=false;
        foreach((Utils::isGet() ? $_GET : $_POST) as $name=>$value)
        {
            
            if (preg_match($namesToIgnore, $name)) continue;    
            
            $hasFields=true; // il y a au moins un nom de champ non ignoré passé en paramètre

            if (! is_array($value))
                if ($value=='') continue; else $value=array($value);
                
            $h='';
            
            $parent=false;
            
            foreach ($value as $item)
            {
                if ($item == '') continue;
                
                // remplace 'et/ou/sauf' par 'op $name=', mais pas dans les trucs entre guillemets
                $t=explode('"',$item);
                $parent=false;
                for ($i=0; $i<count($t); $i+=2)
                {
                    $nb=0;
                    if ($t[$i])
                    {
                        $t[$i]=str_ireplace
                        (
                            array(' ou ', ' or ', ' et ', ' sauf ', ' and not ', ' but ', ' and '),
                            array(" OR $name=", " OR $name=", " AND $name=", " sauf $name=", " sauf $name=", " sauf $name=", " AND $name="),
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
        //echo "equation : [$equation]";
        if ($hasFields) return $equation; else return null;
    }
    
}
?>
