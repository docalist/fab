<?php
/**
 * @package     fab
 * @subpackage  module
 * @author 		Daniel Ménard <Daniel.Menard@bdsp.tm.fr>
 */

/**
 * Module de gestion d'un panier.
 * 
 * @package     fab
 * @subpackage  modules
 */
class CartModule extends Module
{
	// TODO : Besoin de quantité ou non à mettre dans config
	// TODO : Panier à catégorie ou non : à mettre dans config
	
	public $cart=array();
    
    /**
     * @var boolean Indique si le panier accepte ou non les catégories
     */
    public $hasCategory=null;
    
    /**
     * @var boolean Indique si le panier a besoin ou non des quantités
     */
    public $hasQuantity=null;
    
    /**
     * Crée, ou charge s'il existe déjà, le panier indiqué dans la configuration.
     * Si aucun nom, le panier s'appelle cart.
     * Le panier sera automatiquement enregistré à la fin de la requête en cours.
     */
	public function preExecute()
    {
        //parent::preExecute();
        // Récupère le nom du panier
        $name=Config::get('name');
        
        // Fab ouvre la session juste avant d'appeller l'action et donc à ce
        // stade (preExecute), la session n'a pas encore été chargée. Comme
        // On utilise des alias pour gérer le panier, il faut absolument qu'elle
        // soit chargée, donc on le fait maintenant. 
        Runtime::startSession();
        
        // Crée le panier s'il n'existe pas déjà dans la session
        if (!isset($_SESSION[$name])) 
        {
            $_SESSION[$name]=array();
            $_SESSION[$name.'hascategory']=null;
            $_SESSION[$name.'hasquantity']=Config::get('quantity',false);
        }
        
        // Crée une référence entre notre tableau cart et le tableau stocké 
        // en session pour que le tableau de la session soit automatiquement modifié
        // et enregistré 
        $this->cart =& $_SESSION[$name];        
        $this->hasCategory=& $_SESSION[$name.'hascategory'];
        $this->hasQuantity=& $_SESSION[$name.'hasquantity'];

//        if (Utils::isAjax())
//        {
//            $this->setLayout('none');
//            Config::set('debug', false);
//            Config::set('showdebug', false);
//            header('Content-Type: text/html; charset=ISO-8859-1'); // TODO : avoir une rubrique php dans general.config permettant de "forcer" les options de php.ini
//        }
         
	}

    /**
     * Ajoute un ou plusieurs éléments dans le panier, en précisant la quantité.
     * Si une catégorie est précisée, l'élément sera ajouté à cette catégorie.
     * 
     * L'action affiche ensuite le template indiqué dans la clé <code><template></code>
     * du fichier de configuration, en utilisant le callback indiqué dans la clé
     * <code><callback></code> du fichier de configuration.
     * 
     * Pour l'ajout de plusieurs éléments :
     * - On suppose que les navigateurs respectent l'ordre dans lequel les paramètres 
     * sont passés. On obtient ainsi 3 tableaux (item, category, quantity).
     * item[X] est à ajouter à la catégorie category[X], en quantité quantity[X].
     * - Si une seule catégorie et/ou une seule quantité, alors la catégorie et/ou la 
     * quantité s'appliquent à chaque élément à ajouter.
     *
     * @param array $item l'(les) élément(s) à ajouter
     * @param int|array $quantity la quantité de chaque élément à ajouter
     * @param string|array $category la catégorie de chaque élément à ajouter
     */
	public function actionAdd(array $item, $quantity=1, $category=null)
    {
        /*
         * Notes DM 13/12/07
         * 
         * Avec le nouveau ModuleLoader, les arguments de la requête sont
         * passés directement en paramètre de l'action.
         * 
         * Du coup, on n'a plus besoin de les récupérer manuellement 
         * (le code Utils::get($_REQUEST['xxx']) qui existait avant).
         * 
         * Par ailleurs, le type des paramètres peut être forcé. Par exemple,
         * ici, item a été déclaré comme étant un tableau. Le ModuleLoader se
         * charge de veiller à ce que les paramètres sient du bon type et en 
         * l'occurence il nous passera toujours un tableau, même si l'utilisateur
         * n'a indiqué qu'un seul item.
         * 
         * Cela simplifie le code parce que du coup on n'a plus à tester les deux
         * cas (un item seul / un tableau d'items) : on a toujours un tableau,
         * contenant éventuellement un seul élément.
         * 
         * Le nouvel objet Request permet également de vérifier facilement le
         * type des aguments. Par exemple, avant, on ne vérifiait pas que le(s)
         * quantity(s) passé(s) en paramêtre étai(en)t un(des) entier(s).
         * Que se passait-il si on appellait add?item=&quantity=abcd ?
         * Maintenant, item est obligatoire (parce qu'il est déclaré sans valeur
         * par défaut dans les paramètres de l'action) et quantity est testé 
         * (0 < entier < 100)
         *  
         * Pour le moment, j'ai uniquement mis le code obsolète en commentaires, 
         * le temps de valider tout ça. 
         * 
         * SF : 
         * - faire le ménage une fois qu'on sera sur que tout fonctionne bien.
         * - faire la même chose pour les autres actions de CartModule
         * 
         * Remarque : les templates teste explicitement 'if(is_array(item))'. 
         * Ils doivent être adaptés pour tester à la place 'if(count(item)>1)'
         */
	    
        // Fait des vérifications sur la quantité
        $this->request->int('quantity')->min(1)->max(100)->ok();
        
		// Plusieurs éléments à ajouter
		$nb=0;
//		if (is_array($item))
//		{
			if (isset($category) && is_array($category) && (count($category)!= count($item)))
				throw new Exception('Erreur : il doit y avoir autant de catégories que d\'éléments à ajouter.');
			
			if (isset($quantity) && is_array($quantity) && (count($quantity)!= count($item)))
				throw new Exception('Une quantité doit être précisée pour chaque élément à ajouter.');
		
			foreach($item as $key=>$value)
			{
				// Si on a une catégorie pour chaque élément
				(is_array($category)) ? $cat=$category[$key] : $cat=$category;
				
				// Si une quantité pour chaque élément
				(is_array($quantity)) ? $quant=$quantity[$key] : $quant=$quantity;
				
				// Ajoute l'élément au panier
				$this->add($value, $quant, $cat);
				++$nb;
			}
//		}
		
		// Un seul élément à ajouter
//		else
//		{
//			// Ajoute l'item au panier
//			$this->add($item, $quantity, $category);
//			$nb=1;
//		}
		
		if (Utils::isAjax())
        {
        	if ($nb===1)
                echo $nb . ' notice ajoutée au panier';
            else
                echo $nb . ' notices ajoutées au panier';
            
            return;
        }
        
		// Détermine le callback à utiliser
		// TODO : Vérifier que le callback existe
		$callback=Config::get('callback');
		
		// Exécute le template, s'il a été indiqué
		if ($template=Config::get('template'))
			Template::run
			(
				$template,
				array($this, $callback),
                array('category'=>$category, 'item'=>$item, 'quantity'=>$quantity)
			);
	}
	
	/**
	 * Supprime un ou plusieurs éléments du panier, en précisant la quantité.
	 * Si une catégorie a été précisée, supprime l'élément, de cette catégorie.
	 * 
     * L'action affiche ensuite le template indiqué dans la clé <code><template></code>
     * du fichier de configuration, en utilisant le callback indiqué dans la clé
     * <code><callback></code> du fichier de configuration.
     * 
	 * Pour la suppression de plusieurs éléments :
	 * - On suppose que les navigateurs respectent l'ordre dans lequel les paramètres 
	 * sont passés. On obtient ainsi 3 tableaux (item, category, quantity).
	 * item[X] est à supprimer de la catégorie category[X], en quantité quantity[X].
	 * - Si une seule catégorie et/ou une seule quantité, alors la catégorie et/ou la 
	 * quantité s'appliquent à chaque élément à supprimer.
     *
     * @param array $item l'(les) élément(s) à supprimer
     * @param int|array $quantity la quantité de chaque élément à supprimer
     * @param string|array $category la catégorie de chaque élément à supprimer
	 */
	public function actionRemove(array $item, $quantity=1, $category=null)
	{
	    // Fait des vérifications sur la quantité
	    $this->request->int('quantity')->min(1)->max(100)->ok();
	    
		// Plusieurs éléments à supprimer
//		if (is_array($item))
//		{
			if (isset($category) && is_array($category) && (count($category)!= count($item)))
				throw new Exception('Erreur : il doit y avoir autant de catégories que d\'éléments à supprimer.');
			
			if (isset($quantity) && is_array($quantity) && (count($quantity)!= count($item)))
				throw new Exception('Une quantité doit être précisée pour chaque élément à supprimer.');
		
			foreach($item as $key=>$value)
			{
				// Si on a une catégorie pour chaque élément
				(is_array($category)) ? $cat=$category[$key] : $cat=$category;
				
				// Si une quantité pour chaque élément
				(is_array($quantity)) ? $quant=$quantity[$key] : $quant=$quantity;
				
				// Supprime l'élément du panier
				$this->remove($value, $quant, $cat);
			}
//		}
//		
//		// Un seul élément à supprimer
//		else
//		{
//			$this->remove($item, $quantity, $category);
//		}

		// Détermine le callback à utiliser
		// TODO : Vérifier que le callback existe
		$callback=Config::get('callback');
		
		// Exécute le template, s'il a été indiqué
		if ($template=Config::get('template'))
			Template::run
			(
				$template,
				array($this, $callback),
                array('category'=>$category, 'item'=>$item, 'quantity'=>$quantity)
			);
	}
	
	/**
	 * Vide la totalité du panier ou supprime une catégorie d'éléments du panier.
	 * 
     * Après la suppression des éléments du panier, l'action affiche le template 
     * indiqué dans la clé <code><template></code> du fichier de configuration, 
     * en utilisant le callback indiqué dans la clé <code><callback></code> 
     * du fichier de configuration.
     * 
	 * @param string|null $category la catégorie d'éléments à supprimer ou null
	 * pour vider la totalité du panier.
	 */
	public function actionClear($category=null)
	{
		// Vide la catégorie ou vide le panier si pas de catégorie
		$this->clear($category);
        
		// Détermine le callback à utiliser
		// TODO : Vérifier que le callback existe
		$callback=Config::get('callback');

		// Exécute le template, s'il a été indiqué
		if ($template=Config::get('template'))
			Template::run
			(
				$template,
				array($this, $callback),
                array('category'=>$category)
			);
	}
	
	/**
	 * Affiche le panier.
	 *
	 * Affiche le panier en utilisant le template indiqué dans la clé 
	 * <code><template></code> du fichier de configuration et le callback
     * indiqué dans la clé <code><callback></code> du fichier de configuration.
	 * 
	 * @param string|null $category la catégorie des éléments à afficher ou null
	 * pour afficher tout le panier.
	 */
	public function actionShow($category=null)
	{
	    // Vérifie que la catégorie existe
        if ($category)
        {
        	if ($this->hasCategory)
        	{
	        	if (! isset($this->cart[$category]))
	        		throw new Exception('La catégorie demandée n\'existe pas.');
        	}
        }
        
		// Détermine le template à utiliser
		if (! $template=Config::get('template'))
			throw new Exception('Le template à utiliser n\'a pas été indiqué');
			
		// Détermine le callback à utiliser
		$callback=Config::get('callback');
		
		// Exécute le template
		Template::run
		(
			$template,
			array($this, $callback),
            array('category'=>$category)
		);
	}
	
    
    /**
     * Ajoute un élément dans le panier.
     * 
     * @param mixed $item l'élément à ajouter
     * @param int $quantity la quantité d'élément $item
     * @param mixed $category optionnel la catégorie dans laquelle on veut ajouter
     * l'item
     */
    private function add($item, $quantity=1, $category=null)
    {
        if ($quantity<0) return $this->remove($item, $quantity, $category);
        
        // Le 1er ajout d'un item définit si le panier a des catégories ou pas
        if (is_null($this->hasCategory))
            $this->hasCategory=(!is_null($category));
        else
        {
            if ($this->hasCategory)
            {
                if (is_null($category)) throw new Exception('Vous devez spécifier une catégorie');
            }
            else
            {
                if (!is_null($category)) throw new Exception('Catégorie non autorisée');
            }
        }
        
		if (is_int($item) || ctype_digit($item)) $item=(int)$item;     	

		// Ajoute l'item dans le panier
		if (is_null($category))
        {
            if ($this->hasQuantity)
            {
                if (isset($this->cart[$item])) 
                    $this->cart[$item]+=$quantity; 
                else      
                    $this->cart[$item]=$quantity;
            }
            else
            {
                // On met la quantité à 1 quand le panier n'a pas besoin de quantité
                $this->cart[$item]=1;
            }
        }
        else
        {        
            if ($this->hasQuantity)
            {
                if (! isset($this->cart[$category]) || ! isset($this->cart[$category][$item]))
                    $this->cart[$category][$item]=$quantity;
                else
                    $this->cart[$category][$item]+=$quantity;
            }
            else
            {
                // On met la quantité à 1 quand le panier n'a pas besoin de quantité
                $this->cart[$category][$item]=1;
            }
        }
    }
    
     
    /**
     * Supprime un élément du panier 
     * Si une catégorie est précisée en paramètre, supprime l'item, de la catégorie.
     * Supprime la catégorie, si elle ne contient plus d'élément.
     * remarque : Aucune erreur n'est générée si l'item ne figure pas dans le
     * panier
	 *
	 * @param mixed $item l'item à supprimer
     * @param int $quantity la quantité d'item à supprimer
     * @param mixed $category optionnel la catégorie dans laquelle on veut supprimer
     * l'item
     */
    private function remove($item, $quantity=1, $category=null)
    {
        if (is_int($item) || ctype_digit($item)) $item=(int)$item;

        if (! is_null($this->hasCategory))
        {
            if ($this->hasCategory)
            {
                if (is_null($category)) throw new Exception('Vous devez spécifier une catégorie');
            }
            else
            {
                if (!is_null($category)) throw new Exception('Catégorie non autorisée');
            }
        }
                
        // Supprime l'élément du panier
        if (is_null($category))
        {
        	if (! isset($this->cart[$item])) return;
            $this->cart[$item]-=$quantity;
            if ($this->cart[$item]<=0) unset($this->cart[$item]);
        }
        else
        {
            if (! isset($this->cart[$category])) return;
            if (! isset($this->cart[$category][$item])) return;

            $this->cart[$category][$item]-=$quantity;
            if ($this->cart[$category][$item]<=0) unset($this->cart[$category][$item]);
            
	        // Si plus d'élément dans la catégorie, supprime la catégorie
			if (count($this->cart[$category]) == 0)
				unset($this->cart[$category]);        	
        }

        // Si le panier est vide, réinitialise le flag hasCategory
        if (count($this->cart) == 0) $this->hasCategory=null;
    }
    

    /**
     * Vide la totalité du panier ou supprime une catégorie d'items du panier 
     * 
     * @param string|null $category la catégorie à supprimer
     */
    private function clear($category=null)
    {
        if (! $this->hasCategory)
        {
            if (!is_null($category)) throw new Exception('Catégorie non autorisée');
        }

        // Si on n'a aucune catégorie, vide tout le panier
        if (is_null($category))
        	$this->cart=array();
            
        // Sinon, vide uniquement la catégorie indiquée
        else
        	unset($this->cart[$category]);

        // Si le panier est complètement vide, réinitialise le flag hasCategory
        if (count($this->cart) == 0) $this->hasCategory=null;
    }
	
    public function contains($item)
    {
        return isset($this->cart[$item]);
    }
} 

?>
