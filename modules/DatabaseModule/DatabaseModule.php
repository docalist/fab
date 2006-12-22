<?php

/**
 * @package     fab
 * @subpackage  modules
 * @author      Daniel M�nard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id: Database.php 105 2006-09-21 16:30:25Z dmenard $
 */

class Optional implements ArrayAccess
{
    private $data=null;
    
	public function __construct($data)
    {
    	if (! is_array($data))
            throw new Exception('Vous devez passer un tableau pour cr�er un objet '.get_class());
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
    private function OpenSelection($equation=null, $readOnly=false)
    {
        // Le fichier de config du module indique la base � utiliser
        $database=Config::get('database');
        if (is_null($database))
            throw new Exception('La base de donn�es � utiliser n\'a pas �t� indiqu�e dans le fichier de configuration du module');

        debug && Debug::log("Ouverture de la base '%s' en mode '%s'", $database, $readOnly ? 'lecture seule' : 'lecture/�criture');
        $this->selection=Database::open($database, $readOnly);
        
        if ($equation)
        {
            $result=$this->selection->search($equation, $_REQUEST);
            debug && Debug::log("Requ�te : %s, %s r�ponse(s).", $equation, $this->selection->count());
            return $result;
        }
            
        return false;	
    }
    
    /**
     * Affiche le formulaire de recherche indiqu� dans la cl� 'template' de la
     * configuration, en utilisant le callback indiqu� dans la cl� 'callback' de
     * la configuration. Si le callback indiqu� est 'none' alors aucun callback 
     * n'est appliqu�. Si la cl� 'callback' n'est pas d�fini, c'est le callback
     * par d�faut 'getField' qui est utilis�.
     */
    public function actionSearchForm()
    {
        // D�termine le template � utiliser
        if (! $template=$this->getTemplate())
            throw new Exception('Le template � utiliser n\'a pas �t� indiqu�');
        
        // D�termine le callback � utiliser
        $callback=$this->getCallback();
               
        // Ex�cute le template
//        echo $template;
        Template::run
        (
            $template,  
            array($this, $callback),           // Priorit� � la fonction utilisateur
            new Optional($_REQUEST)
//            $_REQUEST,                          // Champs pass�s en param�tre
//            array('Template', 'EmptyCallback')  // Effacer tous les autres
        );
//        echo "here";
// TODO: remettre en place le rappel des �l�ments de formulaire
//        Template::run
//        (
//            $template,  
//            array($this, $callback),            // Priorit� � la fonction utilisateur
//            $_REQUEST,                          // Champs pass�s en param�tre
////            'Template::emptyCallback'           // Effacer tous les autres
//            array('Template', 'EmptyCallback')
//        );
    }

    /**
     * Affiche un message si une erreur s'est produite lors de la recherche.
     * Le template � utiliser est indiqu� dans la cl� 'errortemplate' de la 
     * configuration de l'action 'search'.
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
     * Lance une recherche si une �quation peut �tre construite � partir des 
     * param�tres pass�s et affiche les notices obtenues en utilisant le template 
     * indiqu� dans la cl� 'template' de la configuration.
     * Si aucun param�tre n'a �t� pass�, redirige vers le formulaire de recherche.
     * Si erreur lors de la recherche, affiche l'erreur en utilisant le template
     * indiqu� dans la cl� 'errortemplate' de la configuration. 
     */
    public function actionSearch()
    {
        // Construit l'�quation de recherche
        $this->equation=$this->makeEquation('start,max,sort');
        
        
        // Si aucun param�tre de recherche n'a �t� pass�, il faut afficher le formulaire
        // de recherche
        if (is_null($this->equation))
            Runtime::redirect('searchform');
        
        // Des param�tres ont �t� pass�s, mais tous sont vides et l'�quation obtenue est vide
        if ($this->equation==='')
            return $this->showError('Vous n\'avez indiqu� aucun crit�re de recherche.');
        
        // Lance la r�cherche
        if (! $this->openSelection($this->equation))
            return $this->showError("Aucune r�ponse. Equation : $this->equation");

        // D�termine le template � utiliser
        if (! $template=$this->getTemplate('template'))
            throw new Exception('Le template � utiliser n\'a pas �t� indiqu�');
        
        // D�termine le callback � utiliser
        $callback=$this->getCallback();

        // Ex�cute le template
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
     * Le(s) num�ro(s) de la (des) notice(s) � afficher doit �tre indiqu�
     * dans 'REF'. 
     * 
     * G�n�re une erreur si REF n'est pas renseign� ou ne correspond pas � une
     * notice existante de la base.
     */
    public function actionShow()
    {
        // Construit l'�quation de recherche
        $this->equation=$this->makeEquation('start,nb');
        
        // Si aucun param�tre de recherche n'a �t� pass�, erreur
        if (is_null($this->equation))
            return $this->showError('Le num�ro de la r�f�rence � afficher n\'a pas �t� indiqu�');
        
        // Des param�tres ont �t� pass�s, mais tous sont vides et l'�quation obtenue est vide
        if ($this->equation==='')
            return $this->showError('Vous n\'avez indiqu� aucun crit�re permettant de s�lectionner la notice � afficher');
        
        // Ouvre la s�lection
        if (! $this->openSelection($this->equation))
            return $this->showError('La r�f�rence demand�e n\'existe pas');

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
            array('selection'=>$this->selection, 'record'=>$this->selection->record),
            $this->selection->record
        );  
    }
    
    
    /**
     * Cr�ation d'une notice
     * Affiche le formulaire indiqu� dans la cl� 'template' de la configuration.
     */
    public function actionNew()
    {        
        $template = $this->getTemplate();
        $callback = $this->getCallback();
        
        // On ex�cute le template correspondant
        // Ex�cute le template
        Template::run
        (
            $template,  
            array($this, $callback)
        );      
    } 
    
    
    /**
     * Edition d'une notice
     * Affiche le formulaire indiqu� dans la cl� 'template' de la configuration.
     * La notice correspondant au param�tre 'REF' est charg�e dans le formulaire
     */
    public function actionLoad()
    {        
        // Construit l'�quation de recherche
        $this->equation=$this->makeEquation('start,nb');
        
        // Si aucun param�tre de recherche n'a �t� pass�, erreur
        if (is_null($this->equation))
            throw new Exception('Le num�ro de la r�f�rence � afficher n\'a pas �t� indiqu�');
        
        // Des param�tres ont �t� pass�s, mais tous sont vides et l'�quation obtenue est vide
        if ($this->equation==='')
            echo 'EQUATION VIDE';
            //TODO
        
        // D�termine le template � utiliser
        if (! $template=$this->getTemplate())
            throw new Exception('Le template � utiliser n\'a pas �t� indiqu�');
        
        // D�termine le callback � utiliser
        $callback=$this->getCallback();
        
        // Si un num�ro de r�f�rence a �t� indiqu�, on charge cette notice         
        // V�rifie qu'elle existe
        if (! $this->openSelection($this->equation))
            throw new Exception('La r�f�rence demand�e n\'existe pas');      

        Template::run
        (
            $template,  
            array($this, $callback),
            array('selection'=>$this->selection, 'record'=>$this->selection->record),
            $this->selection->record
        );             
    }
    
    /**
     * Sauvegarde la notice d�sign�e par 'REF' avec les champs pass�s en
     * param�tre.
     * Redirige ensuite l'utilisateur vers l'action 'show'
     */
    public function actionSave()
    {
        global $selection;
        
        // Si un num�ro de r�f�rence a �t� indiqu�, on charge cette notice         
        if ($ref=Utils::get($_REQUEST['REF']))
        {
            // Ouvre la s�lection
            debug && Debug::log('Chargement de la notice num�ro %i', $ref);
            if (! $this->openSelection("REF=$ref", false))
                throw new Exception('La r�f�rence demand�e n\'existe pas');
                
            // Edite la notice
            $this->selection->editRecord();
        }
        
        // Sinon, on en cr��e une nouvelle
        else
        {
            // Ouvre la s�lection
            debug && Debug::log('Cr�ation d\'une nouvelle notice');
            $this->openSelection('', false); 

            // Cr�e une nouvelle notice
            $this->selection->addRecord();
            // R�cup�re le num�ro de la notice cr��e
            $ref=$this->selection['REF'];
            debug && Debug::log('Num�ro de la notice cr��e : %s', $ref);
        }            
       
       // Mise � jour de chacun des champs
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
            debug && Debug::log('Redirection pour afficher la notice enregistr�e');
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
     * Supprime la notice indiqu�e puis affiche le template indiqu� dans la cl�
     * 'template' de la configuration.
     * 
     * Si aucun template n'est indiqu�, affiche un message 'notice supprim�e'.
     */
    public function actionDelete()
    {
        // D�termine la r�f�rence � supprimer         
        if (! $ref=Utils::get($_REQUEST['REF']))
            throw new Exception('Le num�ro de la r�f�rence � supprimer n\'a pas �t� indiqu�');
        
        // Ouvre la base sur la r�f�rence demand�e
        if( ! $this->openSelection("REF=$ref", false) )
            throw new Exception('La r�f�rence demand�e n\'existe pas');
        
        // V�rifie qu'elle existe
        if ($this->selection->count()==0)
            throw new Exception('La r�f�rence demand�e n\'existe pas');
            
        // Supprime la notice
        $this->selection->deleteRecord();
        
        // D�termine le template � utiliser
        if (! $template=$this->getTemplate())
            echo '<p>Notice supprim�e.</p>';

        // Ex�cute le template
        else
        {
            // D�termine le callback � utiliser
            $callback=$this->getCallback();

            Template::run
            (
                $template,  
                array($this, $callback)
            );
        }
    }
    
    /**
     * Affiche le formulaire de type Chercher/Remplacer indiqu� dans la cl�
     * template de la configuration
     */
     public function actionReplaceForm()
     {
        //TODO: Faut-il g�rer un �ventuel callback ici ?

        // D�termine le template � utiliser
        if (! $template=$this->getTemplate())
            throw new Exception('Le template � utiliser n\'a pas �t� indiqu�');
        
        // D�termine le callback � utiliser
        $callback=$this->getCallback();
               
        // Ex�cute le template
        Template::run
        (
            $template,  
            array($this, $callback),           // Priorit� � la fonction utilisateur
            new Optional($_REQUEST)
        );
     }
    
    /**
     * Effectue le chercher/remplacer et appelle le tempalte indiqu� dans la cl�
     * template de la configuration ensuite : feedback
     */
     
     // NEW VERSION
     public function actionReplace()
     {
        //TODO : gestion d'erreurs
        //TODO : nettoyage
                
        // R�cup�rer l'�quation de recherche
        $this->equation=Utils::get($_REQUEST['equation']);
        $search=Utils::get($_REQUEST['searchStr']);
        $replace=Utils::get($_REQUEST['replaceStr']);
        $wholeWord=Utils::get($_REQUEST['wholeWord']);

        // Si aucun param�tre de recherche n'a �t� pass� ou si rien n'est � remplacer, il faut rediriger vers le formulaire replaceform
        if (is_null($this->equation) || is_null($search))
            Runtime::redirect('replaceform');
        
        if ($wholeWord)     // expression r�guli�re pour le chercher/remplacer
        {
            $pattern = '~\b' . preg_quote($search) . '\b~';   
        }
        else
        {
            $pattern = '~' . preg_quote($search) . '~';
        }
        
        echo "PATTERN = $pattern<br />";
        
        // Des param�tres ont �t� pass�s, mais tous sont vides et l'�quation obtenue est vide
        if ($this->equation==='')
            return $this->showError('Vous n\'avez indiqu� aucun crit�re de recherche.');
        
        // Lance la r�cherche
        if (! $this->openSelection($this->equation))
            // TODO : il n'y a rien a remplacer
            return $this->showError("Aucune r�ponse. Equation : $this->equation");
        
        echo "search = $search<br />replace = $replace<br />";
        
        // TODO: d�l�guer le boulot au TaskManager (ex�cution peut �tre longue)
        foreach($this->selection as & $record)
        {            
            $this->selection->editRecord();

            if( ! $this->selection->pregReplace($pattern, $replace))
                echo 'RIEN DE REMPLACE<br />';
                
            $this->selection->saveRecord();
        }
        
        die();
        
        
        // D�termine le template � utiliser
        if (! $template=$this->getTemplate('template'))
            throw new Exception('Le template � utiliser n\'a pas �t� indiqu�');
        
        // D�termine le callback � utiliser
        $callback=$this->getCallback();

        // Ex�cute le template
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
            
        if (file_exists($h=$this->path . $template)) // pb : template relatif � BisDatabase, pas au module h�rit� (si seulement config)
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
     * Par exemple si votre formulaire a des param�tres nomm�s 'max' et 'order',
     * ceux-ci seront par d�faut pris en compte pour construire l'�quation (vous
     * obtiendrez quelque chose du style "tit=xxx et max=100 et order=1", ce qui
     * en g�n�ral n'est pas le r�sultat souhait�). Pour obtenir le r�sultat
     * correct, indiquez la chaine "max, order" en param�tre.
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
    public function makeEquation($ignore='')
    {
        if ($ignore) $ignore='|' . str_replace(',', '|', preg_quote($ignore, '~'));
        $namesToIgnore="~module|action|bq.+|".preg_quote(session_name())."$ignore~i";
        
        $equation='';
        $hasFields=false;
        foreach((Utils::isGet() ? $_GET : $_POST) as $name=>$value)
        {
            
            if (preg_match($namesToIgnore, $name)) continue;    
            
            $hasFields=true; // il y a au moins un nom de champ non ignor� pass� en param�tre

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
