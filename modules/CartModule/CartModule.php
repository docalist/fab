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
    
    /**
     * @var boolean Indique si le panier accepte ou non les cat�gories
     */
    public $hasCategory=null;
    
    /**
     * Cr�e, ou charge s'il existe d�j�, le panier indiqu� dans la configuration.
     * Si aucun nom, le panier s'appelle cart.
     * Le panier sera automatiquement enregistr� � la fin de la requ�te en cours.
     */
	public function preExecute()
    {
        // R�cup�re le nom du panier
        $name=Config::get('name', 'cart');
        
        // Cr�e le panier s'il n'existe pas d�j� dans la session
        if (!isset($_SESSION[$name])) 
        {
            $_SESSION[$name]=array();
            $_SESSION[$name.'hascategory']=null;
        }
        
        // Cr�e une r�f�rence entre notre tableau cart et le tableau stock� 
        // en session pour que le tableau de la session soit automatiquement modifi�
        // et enregistr� 
        $this->cart =& $_SESSION[$name];        
        $this->hasCategory=& $_SESSION[$name.'hascategory'];
	}
     

	/**
	 * Ajoute un item dans le panier, en pr�cisant sa quantit�.
	 * Si une cat�gorie est pr�cis�e, l'item sera ajout� � cette cat�gorie.
	 */
	public function actionAdd()
	{		
		// R�cup�re la cat�gorie
		$category=Utils::get($_REQUEST['category']);
		
		// R�cup�re l'item � ajouter
		$item=Utils::get($_REQUEST['item']);
		
		// R�cup�re la quantit�
		$quantity=Utils::get($_REQUEST['quantity'],1);
		
		// Ajoute l'item au panier
		$this->add($item, $quantity, $category);
		
		// D�termine le callback � utiliser
		// TODO : V�rifier que le callback existe
		$callback=Config::get('callback');
		
		// Ex�cute le template, s'il a �t� indiqu�
		if ($template=Config::get('template'))
			Template::run
			(
				$template,
				array($this, $callback),
                array('category'=>$category, 'item'=>$item, 'quantity'=>$quantity)
			);
	}
	
	/**
	 * Supprime un item du panier. Si une cat�gorie a �t� pr�cis�e,
	 * supprime, de cette cat�gorie, l'item associ� � la cl� key. Sinon, supprime
	 * l'item associ� � la cl� key.
	 */
	public function actionRemove()
	{
		// R�cup�re la cat�gorie
		$category=Utils::get($_REQUEST['category']);
		
		// R�cup�re l'item � supprimer
		$item=Utils::get($_REQUEST['item']);
		
		// R�cup�re la quantit�
		$quantity=Utils::get($_REQUEST['quantity'],1);
		
		// Supprime l'item du panier
		$this->remove($item, $quantity, $category);
		
		// D�termine le callback � utiliser
		// TODO : V�rifier que le callback existe
		$callback=Config::get('callback');
		
		// Ex�cute le template, s'il a �t� indiqu�
		if ($template=Config::get('template'))
			Template::run
			(
				$template,
				array($this, $callback),
                array('category'=>$category, 'item'=>$item, 'quantity'=>$quantity)
			);
	}
	
	/**
	 * Vide le panier ou une cat�gorie du panier
	 */
	public function actionClear()
	{
        // R�cup�re la cat�gorie
        $category=Utils::get($_REQUEST['category']);

		// Vide la cat�gorie
		$this->clear($category);	
        
		// D�termine le callback � utiliser
		// TODO : V�rifier que le callback existe
		$callback=Config::get('callback');

		// Ex�cute le template, s'il a �t� indiqu�
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
		// V�rifie que la cat�gorie existe
        if ($category=Utils::get($_REQUEST['category']))
        {
        	if ($this->hasCategory)
        	{
	        	if (! isset($this->cart[$category]))
	        		throw new Exception('La cat�gorie demand�e n\'existe pas.');
        	}
        }
        
		// D�termine le template � utiliser
		if (! $template=Config::get('template'))
			throw new Exception('Le template � utiliser n\'a pas �t� indiqu�');
			
		// D�termine le callback � utiliser
		$callback=Config::get('callback');
		
		// Ex�cute le template
		Template::run
		(
			$template,
			array($this, $callback),
            array('category'=>$category)
		);
	}
	
    
    /**
     * Ajoute un item dans le panier. Si un item ayant la m�me cl� figurait d�j�
     * dans le panier, il est �cras�.
     * 
     * @param mixed $item l'�l�ment � ajouter
     * @param mixed $quantity la quantit� d'�l�ment $item
     * @param mixed $category optionnel la cat�gorie dans laquelle on veut ajouter
     * l'item
     */
    private function add($item, $quantity=1, $category=null)
    {
        if ($quantity<0) return $this->remove($item, $quantity, $category);
        
        // Le 1er ajout d'un item d�finit si le panier a des cat�gories ou pas
        if (is_null($this->hasCategory))
            $this->hasCategory=(!is_null($category));
        else
        {
            if ($this->hasCategory)
            {
                if (is_null($category)) throw new Exception('Vous devez sp�cifier une cat�gorie');
            }
            else
            {
                if (!is_null($category)) throw new Exception('Cat�gorie non autoris�e');
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
    private function remove($item, $quantity=1, $category=null)
    {
        if (is_int($item) || ctype_digit($item)) $item=(int)$item;

        if (! is_null($this->hasCategory))
        {
            if ($this->hasCategory)
            {
                if (is_null($category)) throw new Exception('Vous devez sp�cifier une cat�gorie');
            }
            else
            {
                if (!is_null($category)) throw new Exception('Cat�gorie non autoris�e');
            }
        }
                
        // Supprime l'�l�ment du panier
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
            
	        // Si plus d'�l�ment dans la cat�gorie, supprime la cat�gorie
			if (count($this->cart[$category]) == 0)
				unset($this->cart[$category]);        	
        }

        // Si le panier est vide, r�initialise le flag hasCategory
        if (count($this->cart) == 0) $this->hasCategory=null;
    }
    

    /**
     * Vide la totalit� du panier ou supprime une cat�gorie d'items du panier 
     * 
     * @param string|null $category la cat�gorie � supprimer
     */
    private function clear($category=null)
    {
        if ($this->hasCategory)
        {
            if (is_null($category)) throw new Exception('Vous devez sp�cifier une cat�gorie');
        }
        else
        {
            if (!is_null($category)) throw new Exception('Cat�gorie non autoris�e');
        }

        // Si on n'a aucune cat�gorie, vide tout le panier
        if (is_null($category))
        	$this->cart=array();
            
        // Sinon, vide uniquement la cat�gorie indiqu�e
        else
        	unset($this->cart[$category]);

        // Si le panier est compl�tement vide, r�initialise le flag hasCategory
        if (count($this->cart) == 0) $this->hasCategory=null;
    }
	
} 

?>
