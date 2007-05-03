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
    
	public function preExecute()
    {
		// Crée ou charge le panier
		$this->getCart();
	}
     
	/**
	 * actionAdd : ajoute un item dans le panier, associé à la clé key
	 * Si une catégorie est précisée, l'item sera ajouté à cette catégorie
	 * avec la clé key.
	 */
	public function actionAdd()
	{		
		// Récupère la catégorie
		$category=Utils::get($_REQUEST['category']);
		
		// Récupère la clé
		$key=Utils::get($_REQUEST['key']);
		
		// Récupère l'item à ajouter
		$item=Utils::get($_REQUEST['item']);
		
		// Ajoute l'item au panier
		$this->add($item, $key, $category);
		
		echo'<pre>';
		print_r($this->cart);
		echo'</pre>';
		
		// Détermine le callback à utiliser
		$callback=Config::get('callback');
		
		// Exécute le template, s'il a été indiqué
		if ($template=Config::get('template'))
			Template::run
			(
				$template,
				array($this, $callback),
				$this->cart,
				array('cart', $this->cart)
			);
	}
	
	/**
	 * actionRemove : supprime un item du panier. Si une catégorie a été précisée,
	 * supprime, de cette catégorie, l'item associé à la clé key. Sinon, supprime
	 * l'item associé à la clé key.
	 */
	public function actionRemove()
	{
		// Récupère la catégorie
		$category=Utils::get($_REQUEST['category']);
		
		// Récupère la clé
		$key=Utils::get($_REQUEST['key']);
		
		// Récupère l'item à ajouter
		$item=Utils::get($_REQUEST['item']);
		
		// Si item non présent
		
		// Supprime l'item du panier
		// TODO : Si plus rien dans la catégorie, supprimer la catégorie ?
		$this->remove($key, $category);
		
		// Détermine le callback à utiliser
		$callback=Config::get('callback');
		
		// Exécute le template, s'il a été indiqué
		if ($template=Config::get('template'))
			Template::run
			(
				$template,
				array($this, $callback),
				$this->cart,
				array('cart', $this->cart)
			);
	}
	
	/**
	 * actionClear : vide le panier
	 */
	public function actionClear()
	{
		$this->clear();		
	}
	
	/**
	 * actionShow : affiche le panier
	 */
	public function actionShow()
	{
		// Détermine le template à utiliser
		if (! $template=Config::get('template'))
			throw new Exception('Le template à utiliser n\'a pas été indiqué');
			
		// Détermine le callback à utiliser
		$callback=Config::get('callback');
		
		// Exécute le template
		Template::run
		(
			$template,
			array('cart', $this->cart),
			array($this, $callback),
			$this->cart
		);
	}
	
	/**
	 * Crée, ou charge s'il existe déjà, le panier indiqué dans la configuration
     * Le panier sera automatiquement enregistré à la fin de la requête en cours.
	 */
	private function getCart()
	{
		// Récupère le nom du panier
		$name=Config::get('name', 'cart');
		
        // Crée le panier s'il n'existe pas déjà dans la session
        if (!isset($_SESSION[$name])) 
            $_SESSION[$name]=array();
        
        // Crée une référence entre notre tableau cart et le tableau stocké 
        // en session pour que le tableau de la session soit automatiquement modifié
        // et enregistré 
        $this->cart =& $_SESSION[$name];		
	}
	
    /**
     * Ajoute un item dans le panier. Si un item ayant la même clé figurait déjà
     * dans le panier, il est écrasé.
     * 
     * @param mixed $item les données à associer à la clé $key
     * @param mixed $key la clé de l'item à ajouter
     * @param mixed $category optionnel la catégorie dans laquelle on veut ajouter
     * l'item 
     */
    private function add($item, $key, $category=null)
    {
		if (is_int($key) || ctype_digit($key)) $key=(int)$key;
		
		// Ajoute l'item dans le panier
		(! is_null($category)) ? $this->cart[$category][$key]=$item : $this->cart[$key]=$item;			
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
    public function remove($key, $category=null)
    {
        if (is_int($key) || ctype_digit($key)) $key=(int)$key;
        
        // Supprime l'élément du panier
        if (is_null($category))
        	unset($this->cart[$key]);
        else
        {
        	unset($this->cart[$category][$key]);

	        // Si plus d'élément dans la catégorie, supprime la catégorie
			if (count($this->cart[$category]) == 0)
				unset($this->cart[$category]);        	
        }
    }

    /**
     * Vide le panier
     */
    private function clear()
    {
    	// Vide le panier
    	$this->cart=null;    			
    }
	
} 

?>
