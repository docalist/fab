<?php
/**
 * @package     fab
 * @subpackage  module
 * @author 		Daniel Ménard <Daniel.Menard@bdsp.tm.fr>
 */

/**
 * Ce module permet de gérer un panier
 * 
 * @package     fab
 * @subpackage  modules
 */
class CartModule extends Module
{
	public $cart=array();
    
    /**
     * @var boolean Indique si le panier accepte ou non les catégories
     */
    public $hasCategory=null;
    
    /**
     * Crée, ou charge s'il existe déjà, le panier indiqué dans la configuration.
     * Si aucun nom, le panier s'appelle cart.
     * Le panier sera automatiquement enregistré à la fin de la requête en cours.
     */
	public function preExecute()
    {
        // Récupère le nom du panier
        $name=Config::get('name', 'cart');
        
        // Crée le panier s'il n'existe pas déjà dans la session
        if (!isset($_SESSION[$name])) 
        {
            $_SESSION[$name]=array();
            $_SESSION[$name.'hascategory']=null;
        }
        
        // Crée une référence entre notre tableau cart et le tableau stocké 
        // en session pour que le tableau de la session soit automatiquement modifié
        // et enregistré 
        $this->cart =& $_SESSION[$name];        
        $this->hasCategory=& $_SESSION[$name.'hascategory'];
	}
     

	/**
	 * Ajoute un item dans le panier, en précisant sa quantité.
	 * Si une catégorie est précisée, l'item sera ajouté à cette catégorie.
	 */
	public function actionAdd()
	{		
		// Récupère la catégorie
		$category=Utils::get($_REQUEST['category']);
		
		// Récupère l'item à ajouter
		$item=Utils::get($_REQUEST['item']);
		
		// Récupère la quantité
		$quantity=Utils::get($_REQUEST['quantity'],1);
		
		// Ajoute l'item au panier
		$this->add($item, $quantity, $category);
		
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
	 * Supprime un item du panier. Si une catégorie a été précisée,
	 * supprime, de cette catégorie, l'item associé à la clé key. Sinon, supprime
	 * l'item associé à la clé key.
	 */
	public function actionRemove()
	{
		// Récupère la catégorie
		$category=Utils::get($_REQUEST['category']);
		
		// Récupère l'item à supprimer
		$item=Utils::get($_REQUEST['item']);
		
		// Récupère la quantité
		$quantity=Utils::get($_REQUEST['quantity'],1);
		
		// Supprime l'item du panier
		$this->remove($item, $quantity, $category);
		
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
	 * Vide le panier ou une catégorie du panier
	 */
	public function actionClear()
	{
        // Récupère la catégorie
        $category=Utils::get($_REQUEST['category']);

		// Vide la catégorie
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
	 * Affiche le panier
	 */
	public function actionShow()
	{
		// Vérifie que la catégorie existe
        if ($category=Utils::get($_REQUEST['category']))
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
     * Ajoute un item dans le panier. Si un item ayant la même clé figurait déjà
     * dans le panier, il est écrasé.
     * 
     * @param mixed $item l'élément à ajouter
     * @param mixed $quantity la quantité d'élément $item
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
            if (isset($this->cart[$item])) 
                $this->cart[$item]+=$quantity; 
            else      
                $this->cart[$item]=$quantity; 
        }
        else
        {        
            if (! isset($this->cart[$category]) || ! isset($this->cart[$category][$item]))
                $this->cart[$category][$item]=$quantity;
            else
                $this->cart[$category][$item]+=$quantity;
        } 
    }
    
     
    /**
     * Supprime un élément du panier 
     * Si une catégorie est précisée en paramètre, supprime, de la catégorie 'category'
     * l'élément associé à la clé key passée en paramètre.
     * Sinon, supprime l'élément associé à la clé key passée en paramètre.
     * Supprime la catégorie, si elle ne contient plus d'élément.
     * remarque : Aucune erreur n'est générée si l'item ne figure pas dans le
     * panier
	 *
     * @param mixed $key la clé de l'item à supprimer
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
        if ($this->hasCategory)
        {
            if (is_null($category)) throw new Exception('Vous devez spécifier une catégorie');
        }
        else
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
	
} 

?>
