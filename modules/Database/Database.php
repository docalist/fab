<?php
class Database extends Module
{
    /**
     * @var string Equation de recherche
     * @access protected
     */
    protected $equation='';
       
//    public function preExecute()
//    {
//        return parent::preExecute();
//    }

    public function actionIndex()
    {
    	echo 'Liste des actions de ce module, avec droits requis/accord�s<pre>';
        
        $class=new ReflectionClass(get_class($this));
        foreach($class->getMethods() as $method)
        {
            $name=$method->getName();
            if (strncmp($name, 'action', 6)==0)
            { 
                $name=Utils::lcfirst(substr($name, 6));
                echo    Debug::dump($name), 
                        ' : ', 
                        Debug::dump(Config::get("$name.access",'')), 
                        ' : ' , 
                        Debug::dump(User::hasAccess(Config::get("$name.access",''))), 
                        '<br />';
                
            }
        }
        //Reflection::export($class);
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
        Template::run
        (
            $template,  
            array($this, $callback),            // Priorit� � la fonction utilisateur
            $_REQUEST,                          // Champ pass� en param�tre
            'Template::emptyCallback'           // Effacer tous les autres
        );       
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
            throw new Exception('Le template d\'erreur � utiliser n\'a pas �t� indiqu�.');

        // D�termine le callback � utiliser
        $callback=$this->getCallback();

        // Ex�cute le template
        Template::run
        (
            $template,  
            array($this, $callback),
            array('error'=>$error)             // Message d'erreur
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
        global $selection;
        
        // Construit l'�quation de recherche
        $this->equation=$this->makeBisEquation();
        
        // Si aucun param�tre de recherche n'a �t� pass�, il faut afficher le formulaire
        // de recherche
        if (is_null($this->equation))
        {
            Runtime::redirect('searchform');
        }
        
        // Des param�tres ont �t� pass�s, mais tous sont vides et l'�quation obtenue est vide
        if ($this->equation==='')
            return $this->showError('Vous n\'avez indiqu� aucun crit�re de recherche.');
        
        // Ouvre la s�lection
        $selection=self::openDatabase($this->equation);
        
        // Si on n'a aucune r�ponse, erreur
        if ($selection->count == 0)
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
            'Template::selectionCallback'
        );                
    }
    
    /**
     * Affiche une ou plusieurs notices en "format long"
     * Le(s) num�ro(s) de la (des) notice(s) � afficher doit �tre indiqu�
     * dans 'ref'. 
     * 
     * G�n�re une erreur si ref n'est pas renseign� ou ne correspond pas � une
     * notice existante de la base.
     */
    public function actionShow()
    {
        global $selection;

        // D�termine la r�f�rence � afficher         
        if (! $ref=Utils::get($_REQUEST['ref']))
            throw new Exception('Le num�ro de la r�f�rence � afficher n\'a pas �t� indiqu�');
        
        // Ouvre la base sur la r�f�rence demand�e
        $selection=self::openDatabase("ref=$ref"); // TODO : ne g�re pas plusieurs notices
        
        // V�rifie qu'elle existe
        if ($selection->count==0)
            throw new Exception('La r�f�rence demand�e n\'existe pas');
        
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
            'Template::selectionCallback'
        );                
    }
    
    /**
     * Affiche le formulaire indiqu� dans la cl� 'template' de la configuration.
     * Si un num�ro de notice a �t� pass� dans le param�tre 'ref', la notice
     * correspondante est charg�e dans le formulaire (�dition de la notice),
     * sinon, un formulaire vierge est affich� (cr�ation d'une nouvelle notice).
     */
    public function actionLoad()
    {
        global $selection;
        
        // D�termine le template � utiliser
        if (! $template=$this->getTemplate())
            throw new Exception('Le template � utiliser n\'a pas �t� indiqu�');
        
        // D�termine le callback � utiliser
        $callback=$this->getCallback();
        
        // Si un num�ro de r�f�rence a �t� indiqu�, on charge cette notice         
        if ($ref=Utils::get($_REQUEST['ref']))
        {
            // Ouvre la base sur la r�f�rence demand�e
            $selection=self::openDatabase("ref=$ref");
            
            // V�rifie qu'elle existe
            if ($selection->count==0)
                throw new Exception('La r�f�rence demand�e n\'existe pas');
            
            // Ex�cute le template
            Template::run
            (
                $template,  
                array($this, $callback),
                'Template::selectionCallback'
            );                
        }

        // Sinon, on affiche un formulaire vierge
        else
        {
            // Ex�cute le template
            Template::run
            (
                $template,  
                array($this, $callback),
                'Template::requestCallback',                
                'Template::emptyCallback'
            );                
        }
    	
    }
    
    /**
     * Sauvegarde la notice d�sign�e par 'ref' avec les champs pass�s en
     * param�tre.
     * Redirige ensuite l'utilisateur vers l'action 'show'
     */
    public function actionSave()
    {
        // Si un num�ro de r�f�rence a �t� indiqu�, on charge cette notice         
        if ($ref=Utils::get($_REQUEST['ref']))
        {
            // Ouvre la s�lection
            debug && Debug::log('Chargement de la notice num�ro %i', $ref);
            $selection=self::openDatabase("ref=$ref", false); 

            // V�rifie qu'elle existe
            if ($selection->count==0)
                throw new Exception('La r�f�rence demand�e n\'existe pas');
                
            // Edite la notice
            $selection->edit();
        }
        
        // Sinon, on en cr��e une nouvelle
        else
        {
            // Ouvre la s�lection
            debug && Debug::log('Cr�ation d\'une nouvelle notice');
            $selection=self::openDatabase('', false); 

            // Cr�e une nouvelle notice
            $selection->addnew();
    
            // R�cup�re le num�ro de la notice cr��e
            $ref=$selection->field(1);
            debug && Debug::log('Num�ro de la notice cr��e : %s', $ref);
        }            
       
        // Initialise chacun des champs de la notice
        for ($i=2; $i <= $selection->fieldscount; $i++)
        {
            // D�termine le nom du champ
            $name=$selection->fieldname($i);
            
            // R�cup�re la ou les valeur(s) pass�e(s) en param�tre(s)
            $value=Utils::get($_REQUEST[$name], '');
            
            // Permet � l'application d'interdire la modification du champ ou de modifier la valeur
            if ($this->setField($name, $value) !== false)
            {
            	// Si la valeur est un tableau, convertit en articles s�par�s par le s�parateur
                if (is_array($value))
                    $value=implode(' / ', array_filter($value)); // TODO : comment acc�der au s�parateur ???

                // Met � jour le champ
                debug && Debug::log('Champ %s=%s', $name, $value);
                $selection->setfield($i, $value); // TODO : gestion d'erreurs
            }
            else
                debug && Debug::notice('Champ %s:modification interdite par setField', $name);
        }
        
        // Enregistre la notice
        debug && Debug::log('Sauvegarde de la notice');
        $selection->update();   // TODO: gestion d'erreurs
    	
        // Redirige l'utilisateur vers l'action show
        debug && Debug::log('Redirection pour afficher la notice enregistr�e');
        Runtime::redirect('show?ref='.$ref);
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
        if (! $ref=Utils::get($_REQUEST['ref']))
            throw new Exception('Le num�ro de la r�f�rence � supprimer n\'a pas �t� indiqu�');
        
        // Ouvre la base sur la r�f�rence demand�e
        $selection=self::openDatabase("ref=$ref", false); // TODO : ne g�re pas plusieurs notices
        
        // V�rifie qu'elle existe
        if ($selection->count==0)
            throw new Exception('La r�f�rence demand�e n\'existe pas');
            
        // Supprime la notice
        $selection->delete();
        
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
    
    // ****************** fonctions surchargeables ***************
    /**
     * Retourne le template � utiliser pour l'action en cours ({@link $this-
     * >action})
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
     * Ouvre la base de donn�es
     * 
     * @param boolean $readOnly
     */
    protected function openDatabase($equation, $readOnly=true, $database=null, $dataset=null)
    {
        // D�termine la base � utiliser
        if (is_null($database))
        {
        	$database=Config::get('database');
            if (is_null($database))
                throw new Exception('La base de donn�es � utiliser n\'a pas �t� indiqu�e');
        }            
        
        // Ajoute l'extension .bed si n�cessaire
        Utils::defaultExtension($database, '.bed');
        
        // Si c'est un chemin relatif, recherche dans /data/db
        if (Utils::isRelativePath($database))
        {
            $path=Utils::searchFile($database, Runtime::$root . 'data/db');
            if ($path=='')
                throw new Exception("Impossible de trouver la base '$database'");
        }
        
        // D�termine le dataset � utiliser
        if (is_null($dataset))
        {
            $dataset=Config::get('dataset');
            if (is_null($dataset))
            {
                $dataset=basename($database);
                Utils::setExtension($dataset);
            }
        }            

        // Ajoute le filtre �ventuel � l'�quation de recherche
        if ($equation != '' && $filter=$this->getFilter()) 
            $equation='(' . $equation . ') and (' . $filter . ')';        
        
        // Cr�e une instance de Bis
        $Bis=new COM("Bis.PHPEngine");

        // Ouvre la base en lecture seule
        if ($readOnly)
        {
            try
            {
                $Selection=$Bis->OpenSelection($path, $dataset, $equation);
            }
            catch (Exception $e)
            {
                throw new Exception("Erreur lors de l'ouverture de la base '$database' : " . $e->getMessage());
            }
        }
        
        // Ouvre la base en lecture/�criture
        else
        {
            try
            {
                $Selection=$Bis->OpenWriteSelection($path, $dataset, $equation);
            }
            catch (Exception $e)
            {
                throw new Exception("Erreur lors de l'ouverture de la base '$database' : " . $e->getMessage());
            }
        }
        
        // Termin�
        unset($Bis);
        return $Selection;    	
    }
    
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
     * @param string $ignore optionnel, param�tres suppl�mentaires � ignorer.
     * (remarque : insensible � la casse).
     * 
     * @return mixed soit une chaine contenant l'�quation obtenue, soit une
     * chaine vide si tous les param�tres pass�s �taient vides (l'utilisateur a
     * valid� le formulaire de recherche sans rien remplir), soit null si aucun
     * param�tre n'a �t� pass� dans la requ�te (l'utilisateur a simplement
     * demand� l'affichage du formulaire de recherche)
     */
    public function makeBisEquation($ignore='')
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
                            array(" ou $name=", " ou $name=", " et $name=", " sauf $name=", " sauf $name=", " sauf $name=", " et $name="),
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
            if ($h) if ($equation) $equation .= ' et ' . $h; else $equation=$h;
        }
        //echo "equation : [$equation]";
        if ($hasFields) return $equation; else return null;
    }
    
}
?>
