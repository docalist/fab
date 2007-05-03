<?php
/**
 * @package     fab
 * @subpackage  module
 * @author 		Daniel M�nard <Daniel.Menard@bdsp.tm.fr>
 */

/**
 * Ce module permet de g�rer un panier
 * 
 * @package     fab
 * @subpackage  modules
 */
class CartModule extends Module
{
	public $cart=array();
    
	public function preExecute()
    {
		// Cr�e ou charge le panier
		$this->getCart();
	}
     
	/**
	 * actionAdd : ajoute un item dans le panier, associ� � la cl� key
	 * Si une cat�gorie est pr�cis�e, l'item sera ajout� � cette cat�gorie
	 * avec la cl� key.
	 */
	public function actionAdd()
	{		
		// R�cup�re la cat�gorie
		$category=Utils::get($_REQUEST['category']);
		
		// R�cup�re la cl�
		$key=Utils::get($_REQUEST['key']);
		
		// R�cup�re l'item � ajouter
		$item=Utils::get($_REQUEST['item']);
		
		// Ajoute l'item au panier
		$this->add($item, $key, $category);
		
		echo'<pre>';
		print_r($this->cart);
		echo'</pre>';
		
		// D�termine le callback � utiliser
		$callback=Config::get('callback');
		
		// Ex�cute le template, s'il a �t� indiqu�
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
	 * actionRemove : supprime un item du panier. Si une cat�gorie a �t� pr�cis�e,
	 * supprime, de cette cat�gorie, l'item associ� � la cl� key. Sinon, supprime
	 * l'item associ� � la cl� key.
	 */
	public function actionRemove()
	{
		// R�cup�re la cat�gorie
		$category=Utils::get($_REQUEST['category']);
		
		// R�cup�re la cl�
		$key=Utils::get($_REQUEST['key']);
		
		// R�cup�re l'item � ajouter
		$item=Utils::get($_REQUEST['item']);
		
		// Si item non pr�sent
		
		// Supprime l'item du panier
		// TODO : Si plus rien dans la cat�gorie, supprimer la cat�gorie ?
		$this->remove($key, $category);
		
		// D�termine le callback � utiliser
		$callback=Config::get('callback');
		
		// Ex�cute le template, s'il a �t� indiqu�
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
		// D�termine le template � utiliser
		if (! $template=Config::get('template'))
			throw new Exception('Le template � utiliser n\'a pas �t� indiqu�');
			
		// D�termine le callback � utiliser
		$callback=Config::get('callback');
		
		// Ex�cute le template
		Template::run
		(
			$template,
			array('cart', $this->cart),
			array($this, $callback),
			$this->cart
		);
	}
	
	/**
	 * Cr�e, ou charge s'il existe d�j�, le panier indiqu� dans la configuration
     * Le panier sera automatiquement enregistr� � la fin de la requ�te en cours.
	 */
	private function getCart()
	{
		// R�cup�re le nom du panier
		$name=Config::get('name', 'cart');
		
        // Cr�e le panier s'il n'existe pas d�j� dans la session
        if (!isset($_SESSION[$name])) 
            $_SESSION[$name]=array();
        
        // Cr�e une r�f�rence entre notre tableau cart et le tableau stock� 
        // en session pour que le tableau de la session soit automatiquement modifi�
        // et enregistr� 
        $this->cart =& $_SESSION[$name];		
	}
	
    /**
     * Ajoute un item dans le panier. Si un item ayant la m�me cl� figurait d�j�
     * dans le panier, il est �cras�.
     * 
     * @param mixed $item les donn�es � associer � la cl� $key
     * @param mixed $key la cl� de l'item � ajouter
     * @param mixed $category optionnel la cat�gorie dans laquelle on veut ajouter
     * l'item 
     */
    private function add($item, $key, $category=null)
    {
		if (is_int($key) || ctype_digit($key)) $key=(int)$key;
		
		// Ajoute l'item dans le panier
		(! is_null($category)) ? $this->cart[$category][$key]=$item : $this->cart[$key]=$item;			
    } 
     
    /**
     * Supprime un �l�ment du panier 
     * Si une cat�gorie est pr�cis�e en param�tre, supprime, de la cat�gorie 'category'
     * l'�l�ment associ� � la cl� key pass�e en param�tre.
     * Sinon, supprime l'�l�ment associ� � la cl� key pass�e en param�tre.
     * Supprime la cat�gorie, si elle ne contient plus d'�l�ment.
     * remarque : Aucune erreur n'est g�n�r�e si l'item ne figure pas dans le
     * panier
	 *
     * @param mixed $key la cl� de l'item � supprimer
     * @param mixed $category optionnel la cat�gorie dans laquelle on veut supprimer
     * l'item 
     */
    public function remove($key, $category=null)
    {
        if (is_int($key) || ctype_digit($key)) $key=(int)$key;
        
        // Supprime l'�l�ment du panier
        if (is_null($category))
        	unset($this->cart[$key]);
        else
        {
        	unset($this->cart[$category][$key]);

	        // Si plus d'�l�ment dans la cat�gorie, supprime la cat�gorie
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
