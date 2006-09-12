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
    	echo 'Liste des actions de ce module, avec droits requis/accordés<pre>';
        
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
        Template::run
        (
            $template,  
            array($this, $callback),            // Priorité à la fonction utilisateur
            $_REQUEST,                          // Champ passé en paramètre
            'Template::emptyCallback'           // Effacer tous les autres
        );       
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
            throw new Exception('Le template d\'erreur à utiliser n\'a pas été indiqué.');

        // Détermine le callback à utiliser
        $callback=$this->getCallback();

        // Exécute le template
        Template::run
        (
            $template,  
            array($this, $callback),
            array('error'=>$error)             // Message d'erreur
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
        global $selection;
        
        // Construit l'équation de recherche
        $this->equation=$this->makeBisEquation();
        
        // Si aucun paramètre de recherche n'a été passé, il faut afficher le formulaire
        // de recherche
        if (is_null($this->equation))
        {
            Runtime::redirect('searchform');
        }
        
        // Des paramètres ont été passés, mais tous sont vides et l'équation obtenue est vide
        if ($this->equation==='')
            return $this->showError('Vous n\'avez indiqué aucun critère de recherche.');
        
        // Ouvre la sélection
        $selection=self::openDatabase($this->equation);
        
        // Si on n'a aucune réponse, erreur
        if ($selection->count == 0)
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
            'Template::selectionCallback'
        );                
    }
    
    /**
     * Affiche une ou plusieurs notices en "format long"
     * Le(s) numéro(s) de la (des) notice(s) à afficher doit être indiqué
     * dans 'ref'. 
     * 
     * Génère une erreur si ref n'est pas renseigné ou ne correspond pas à une
     * notice existante de la base.
     */
    public function actionShow()
    {
        global $selection;

        // Détermine la référence à afficher         
        if (! $ref=Utils::get($_REQUEST['ref']))
            throw new Exception('Le numéro de la référence à afficher n\'a pas été indiqué');
        
        // Ouvre la base sur la référence demandée
        $selection=self::openDatabase("ref=$ref"); // TODO : ne gère pas plusieurs notices
        
        // Vérifie qu'elle existe
        if ($selection->count==0)
            throw new Exception('La référence demandée n\'existe pas');
        
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
            'Template::selectionCallback'
        );                
    }
    
    /**
     * Affiche le formulaire indiqué dans la clé 'template' de la configuration.
     * Si un numéro de notice a été passé dans le paramètre 'ref', la notice
     * correspondante est chargée dans le formulaire (édition de la notice),
     * sinon, un formulaire vierge est affiché (création d'une nouvelle notice).
     */
    public function actionLoad()
    {
        global $selection;
        
        // Détermine le template à utiliser
        if (! $template=$this->getTemplate())
            throw new Exception('Le template à utiliser n\'a pas été indiqué');
        
        // Détermine le callback à utiliser
        $callback=$this->getCallback();
        
        // Si un numéro de référence a été indiqué, on charge cette notice         
        if ($ref=Utils::get($_REQUEST['ref']))
        {
            // Ouvre la base sur la référence demandée
            $selection=self::openDatabase("ref=$ref");
            
            // Vérifie qu'elle existe
            if ($selection->count==0)
                throw new Exception('La référence demandée n\'existe pas');
            
            // Exécute le template
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
            // Exécute le template
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
     * Sauvegarde la notice désignée par 'ref' avec les champs passés en
     * paramètre.
     * Redirige ensuite l'utilisateur vers l'action 'show'
     */
    public function actionSave()
    {
        // Si un numéro de référence a été indiqué, on charge cette notice         
        if ($ref=Utils::get($_REQUEST['ref']))
        {
            // Ouvre la sélection
            debug && Debug::log('Chargement de la notice numéro %i', $ref);
            $selection=self::openDatabase("ref=$ref", false); 

            // Vérifie qu'elle existe
            if ($selection->count==0)
                throw new Exception('La référence demandée n\'existe pas');
                
            // Edite la notice
            $selection->edit();
        }
        
        // Sinon, on en créée une nouvelle
        else
        {
            // Ouvre la sélection
            debug && Debug::log('Création d\'une nouvelle notice');
            $selection=self::openDatabase('', false); 

            // Crée une nouvelle notice
            $selection->addnew();
    
            // Récupère le numéro de la notice créée
            $ref=$selection->field(1);
            debug && Debug::log('Numéro de la notice créée : %s', $ref);
        }            
       
        // Initialise chacun des champs de la notice
        for ($i=2; $i <= $selection->fieldscount; $i++)
        {
            // Détermine le nom du champ
            $name=$selection->fieldname($i);
            
            // Récupère la ou les valeur(s) passée(s) en paramètre(s)
            $value=Utils::get($_REQUEST[$name], '');
            
            // Permet à l'application d'interdire la modification du champ ou de modifier la valeur
            if ($this->setField($name, $value) !== false)
            {
            	// Si la valeur est un tableau, convertit en articles séparés par le séparateur
                if (is_array($value))
                    $value=implode(' / ', array_filter($value)); // TODO : comment accéder au séparateur ???

                // Met à jour le champ
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
        debug && Debug::log('Redirection pour afficher la notice enregistrée');
        Runtime::redirect('show?ref='.$ref);
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
        if (! $ref=Utils::get($_REQUEST['ref']))
            throw new Exception('Le numéro de la référence à supprimer n\'a pas été indiqué');
        
        // Ouvre la base sur la référence demandée
        $selection=self::openDatabase("ref=$ref", false); // TODO : ne gère pas plusieurs notices
        
        // Vérifie qu'elle existe
        if ($selection->count==0)
            throw new Exception('La référence demandée n\'existe pas');
            
        // Supprime la notice
        $selection->delete();
        
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
    
    // ****************** fonctions surchargeables ***************
    /**
     * Retourne le template à utiliser pour l'action en cours ({@link $this-
     * >action})
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
     * Ouvre la base de données
     * 
     * @param boolean $readOnly
     */
    protected function openDatabase($equation, $readOnly=true, $database=null, $dataset=null)
    {
        // Détermine la base à utiliser
        if (is_null($database))
        {
        	$database=Config::get('database');
            if (is_null($database))
                throw new Exception('La base de données à utiliser n\'a pas été indiquée');
        }            
        
        // Ajoute l'extension .bed si nécessaire
        Utils::defaultExtension($database, '.bed');
        
        // Si c'est un chemin relatif, recherche dans /data/db
        if (Utils::isRelativePath($database))
        {
            $path=Utils::searchFile($database, Runtime::$root . 'data/db');
            if ($path=='')
                throw new Exception("Impossible de trouver la base '$database'");
        }
        
        // Détermine le dataset à utiliser
        if (is_null($dataset))
        {
            $dataset=Config::get('dataset');
            if (is_null($dataset))
            {
                $dataset=basename($database);
                Utils::setExtension($dataset);
            }
        }            

        // Ajoute le filtre éventuel à l'équation de recherche
        if ($equation != '' && $filter=$this->getFilter()) 
            $equation='(' . $equation . ') and (' . $filter . ')';        
        
        // Crée une instance de Bis
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
        
        // Ouvre la base en lecture/écriture
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
        
        // Terminé
        unset($Bis);
        return $Selection;    	
    }
    
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
     * @param string $ignore optionnel, paramètres supplémentaires à ignorer.
     * (remarque : insensible à la casse).
     * 
     * @return mixed soit une chaine contenant l'équation obtenue, soit une
     * chaine vide si tous les paramètres passés étaient vides (l'utilisateur a
     * validé le formulaire de recherche sans rien remplir), soit null si aucun
     * paramètre n'a été passé dans la requête (l'utilisateur a simplement
     * demandé l'affichage du formulaire de recherche)
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
            
            $hasFields=true; // il y a au moins un nom de champ non ignoré passé en paramètre

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
