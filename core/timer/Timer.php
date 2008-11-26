<?php
/**
 * @package     fab
 * @subpackage  timer
 * @author      Daniel M�nard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id$
 */

/**
 * Chonom�trage du temps d'ex�cution du code.
 * 
 * Timer est une classe statique permettant de mesurer le temps d'ex�cution de
 * certaines sections de code.
 * 
 * Une section de code poss�de un nom (pas forc�ment unique) et est encadr�e par 
 * des appels aux m�thodes {@link enter() Timer::enter()} et 
 * {@link leave() Timer::leave()}.
 *  
 * Elles peuvent �tre imbriqu�es les unes dans les autres � l'infini. 
 * 
 * Si vous n'indiquez pas de nom pour une section, Timer attribuera 
 * automatiquement le nom de la fonction ou de la m�thode dans laquelle vous 
 * �tes.
 * 
 * Exemple :
 * <code>
 * Timer::enter('Ex�cution de la requ�te');
 *     Timer::enter('Ouverture de la base');
 *         ...
 *     Timer::leave();
 *     ...
 *     Timer::enter('Ex�cution');
 *         ...
 *     Timer::leave();
 *
 *     Timer::enter('Fermeture de la base');
 *         ...
 *     Timer::leave();
 * Timer::leave();
 * Timer::enter('Ecriture des logs');
 *     ...
 * Timer::leave();
 * </code>
 * 
 * Lorsque l'application est termin�e, il suffit d'appeller 
 * {@link printOut() Timer::printOut()} pour afficher le temps d'ex�cution de 
 * toutes les sections qui ont �t� chronom�tr�es.
 * 
 * L'affichage obtenu a la forme suivante :
 * <code>
 * - Total : 180 ms (100%)
 *     - Ex�cution de la requ�te : 100 ms (55%)
 *         - Ouverture de la base : 15 ms (8 %)
 *         - Ex�cution : 80 ms (44%)
 *         - Ouverture de la base : 2 ms (1 %)
 *     - Ecriture des logs : 40 ms (22%)
 * </code>
 * 
 * Pour chaque section, {@link printOut() Timer::printOut()} affiche :
 * - le nom de la section,
 * - le dur�e d'ex�cution de la section,
 * - un pourcentage repr�sentant le rapport entre le temps d'ex�cution de cette
 *   section et le temps total d'ex�cution indiqu� en premi�re ligne.
 * 
 * Remarque :
 * Si vous additionnez les temps d'ex�cution ou les pourcentages, vous 
 * n'obtiendrez pas le total. C'est normal, car les sections ne mesurent que le 
 * temps �coul� entre les appels � {@link enter() Timer::enter()} et � 
 * {@link leave() Timer::leave()}, pas ce qui se passe ailleurs.
 * 
 * @package     fab
 * @subpackage  timer
 */
final class Timer
{
    private static $current=null;
    
    /**
     * R�-initialize la classe Timer.
     * 
     * Cette m�thode supprime toutes les sections en cours et r�initialise la
     * classe Timer comme elle �tait au d�marrage de l'application.
     */
    public static function reset()
    {
        if (is_null(self::$current))
            self::$current=new TimerSection('Total');
    }

    /**
     * Commence une nouvelle section
     *
     * @param string $name le nom de la section qui d�marre. Si vous n'indiquez
     * pas de nom de section, le nom de la m�thode appellante est utilis�.
     */
    public static function enter($name=null)
    {
        self::$current=self::$current->enter($name);
    }

    /**
     * Termine la section en cours.
     */
    public static function leave()
    {
        self::$current=self::$current->leave();
    }
    
    /**
     * Affiche le temps d'ex�cution des sections enregistr�es.
     */
    public static function printOut()
    {
        self::$current->printOut();
    }
}

// Initialise la classe Timer
Timer::reset();

/**
 * Classe mesurant le temps d'ex�cution d'une section de code.
 *
 * Cette classe est priv�e et ne devrait pas �tre visible de l'utilisateur.  
 * @package     fab
 * @subpackage  timer
 */
final class TimerSection
{
    public $parent=null;
    public $name=null;
    public $startTime=null;
    public $endTime=null;
    public $children=array();

    public function __construct($name, $parent=null)
    {
        $this->parent=& $parent;
        $this->name=$name;
        $this->startTime=microtime(true);
    }
    
    public function & enter($name=null)
    {
        if (is_null($name))
        {
            $stack=debug_backtrace();
            $name=$stack[2]['class']. '.' .$stack[2]['function']; 
        }
        $timer=new TimerSection($name, $this);
        $this->children[]=& $timer;
        return $timer;
    }
    
    public function & leave()
    {
        $this->endTime=microtime(true);
        return $this->parent;
    }

    public function printOut($total=null)
    {
        if (is_null($this->endTime))
            $elapsed=microtime(true)-$this->startTime;
        else
            $elapsed=$this->endTime-$this->startTime;

        if (is_null($total)) $total=$elapsed;

        echo '<li style="position:relative">';
        $percent=round(($elapsed/$total)*100);
        //echo '<span style="z-index: 0; background-color: #eee; position: absolute; height: 1em; width: '.$percent.'%"></span>';
        printf('<p>%s : %s (%d %%)</p>', $this->name, Utils::friendlyElapsedTime($elapsed), $percent);
        
        if ($this->children)
        {
            echo '<ul>';
            foreach($this->children as $timer)
            {
                $timer->printOut($total);
            }
            echo '</ul>';
        }
        echo '</li>';
    }
}
?>