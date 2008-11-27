<?php
/**
 * @package     fab
 * @subpackage  timer
 * @author      Daniel Ménard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id$
 */

/**
 * Chonométrage du temps d'exécution du code.
 * 
 * Timer est une classe statique permettant de mesurer le temps d'exécution de
 * certaines sections de code.
 * 
 * Les sections possèdent un nom et sont définies par des appels aux méthodes 
 * {@link enter() Timer::enter()} et {@link leave() Timer::leave()}.
 *  
 * Remarque : 
 * Si vous n'indiquez pas de nom pour une section, Timer attribuera 
 * automatiquement le nom de la fonction ou de la méthode dans laquelle vous 
 * êtes.
 * 
 * Les sections peuvent être imbriquées les unes dans les autres à l'infini. 
 * Cela permet d'obtenir plus de détails sur la manière dont une section de code
 * s'exécute.
 * 
 * Important :
 * Les appels à {@link enter() Timer::enter()} et {@link leave() Timer::leave()}
 * doivent toujours fonctionner par paire : si vous ouvrez une section mais 
 * que vous oubliez de la fermer (ou que vous fites l'inverse), les résultats 
 * obtenus n'auront aucun sens. 
 * 
 * Exemple d'utilisation :
 * <code>
 * function databaseRequest()
 * {
 *     Timer::enter();
 *         Timer::enter('Ouverture de la base');
 *             ...
 *         Timer::leave();
 *         ...
 *         Timer::enter('Exécution');
 *             ...
 *         Timer::leave();
 *
 *         Timer::enter('Fermeture de la base');
 *             ...
 *         Timer::leave();
 *     Timer::leave();
 *     Timer::enter('Ecriture des logs');
 *         ...
 *     Timer::leave();
 * }
 * </code>
 * 
 * Lorsque l'application est terminée, il suffit d'appeller 
 * {@link printOut() Timer::printOut()} pour afficher le temps d'exécution de 
 * toutes les sections qui ont été chronométrées.
 * 
 * L'affichage obtenu a la forme suivante :
 * <code>
 * - Total : 180 ms (100%)
 *     - databaseRequest() : 100 ms (55%)
 *         - Ouverture de la base : 15 ms (8 %)
 *         - Exécution : 80 ms (44%)
 *         - Ouverture de la base : 2 ms (1 %)
 *     - Ecriture des logs : 40 ms (22%)
 * </code>
 * 
 * Pour chaque section, {@link printOut() Timer::printOut()} affiche :
 * - le nom de la section,
 * - le durée d'exécution de la section,
 * - un pourcentage représentant le rapport entre le temps d'exécution de cette
 *   section et le temps total d'exécution indiqué en première ligne.
 * 
 * Remarque :
 * Si vous additionnez les temps d'exécution ou les pourcentages, vous 
 * n'obtiendrez pas le total. C'est normal, car les sections ne mesurent que le 
 * temps écoulé entre les appels à {@link enter() Timer::enter()} et à 
 * {@link leave() Timer::leave()}, pas ce qui se passe ailleurs.
 * 
 * @package     fab
 * @subpackage  timer
 */
abstract class Timer
{
    /**
     * La section en cours.
     *
     * @var TimerSection
     */
    private static $current=null;
    
    /**
     * Réinitialise la classe Timer.
     * 
     * Cette méthode supprime toutes les sections en cours et réinitialise la
     * classe Timer comme elle était au démarrage de l'application.
     * 
     * Il est peu probable que vous ayez à utiliser cette méthode dans votre
     * application.
     * 
     * @param timestamp $time par défaut, l'heure de début du timer est
     * l'heure en cours. Time permet de faire démarrer la section à une heure 
     * antérieure.
     */
    public static function reset($time=null)
    {
        self::$current=new TimerSection('Total', null, $time);
    }

    /**
     * Commence une nouvelle section.
     *
     * @param string $name le nom de la section qui démarre. Si vous n'indiquez
     * pas de nom de section, le nom de la méthode appellante est utilisé.
     * @param timestamp $time par défaut, l'heure de début de la section est
     * l'heure en cours. Time permet de faire démarrer la section à une heure 
     * antérieure.
     */
    public static function enter($name=null, $time=null)
    {
        self::$current=self::$current->start($name, $time);
    }

    /**
     * Termine la section en cours.
     * 
     * @param string $name (optionnel) nom éventuel de la section. Si vous 
     * indiquez un nom, il doit s'agir exactement du même nom que celui utilisé 
     * lors de l'appel à start (utile pour résoudre une erreur de séquençage).
     */
    public static function leave($name=null)
    {
        self::$current=self::$current->stop($name);
    }
    
    
    /**
     * Retourne la section en cours.
     * 
     * Lorsque toutes les sections ont été fermées (typiquement, à la fin du
     * programme), la méthode get() retourne le timer global utilisé pour 
     * chronométrer l'application.
     *
     * @return TimerSection la section en cours.
     */
    public static function get()
    {
        $timer=self::$current;
        while (!is_null($parent=$timer->getParent())) $timer=$parent;
        return $timer;
    }
    
    /**
     * Affiche le temps d'exécution de toutes les sections enregistrées.
     * 
     * @param bool $html true pour générer une sortie html, false pour une
     * sortie texte brute.
     */
    public static function printOut($html=true)
    {
        if ($html) 
            echo '<div style="background-color: #fff; color: #000; border: 1px solid #000; padding: 5px; font-size: 12px;">';
        else 
            echo "\n";
            
        self::get()->printOutSection(null, $html);
        
        if ($html) 
            echo '</div>';
        else 
            echo "\n";
    }
}

// Initialise la classe Timer
Timer::reset();

/**
 * Mesure le temps d'exécution d'une section de code.
 * 
 * TimerSection est la classe utilisée par {@link Timer} pour mesurer le temps
 * d'exécution.
 *
 * Chaque instance de cette classe représente le temps d'exécution d'une section
 * unique et possède un nom ({@link getName()}), une durée d'exécution 
 * ({@link getElapsedTime()}) et éventuellement des sous-sections 
 * ({@link getSections()}).
 *   
 * @package     fab
 * @subpackage  timer
 */
final class TimerSection extends Timer
{
    /**
     * La section parente de cette section
     *
     * @var TimerSection
     */
    private $parent=null;
    
    /**
     * Le nom de la section
     *
     * @var string
     */
    private $name=null;
    
    /**
     * L'heure de début d'exécution
     *
     * @var timestamp
     */
    private $startTime=null;
    
    /**
     * L'heure de fin d'exécution
     *
     * @var timestamp
     */
    private $endTime=null;
    
    /**
     * Les sous-sections éventuelles
     *
     * @var array
     */
    private $children=array();

    /**
     * Constructeur
     *
     * @param string $name le nom de la section.
     * @param TimerSection $parent la section parente.
     * @param timestamp $time le timestamp de début de la section (optionnel, 
     * microtime() sera utilisé si le paramètre n'est pas fourni).
     */
    protected function __construct($name, $parent=null, $time=null)
    {
        $this->parent=& $parent;
        $this->name=$name;
        $this->startTime=is_null($time) ? microtime(true) : $time;
    }
    
    /**
     * Retourne le nom de la section.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }
    
    /**
     * Retourne la durée d'exécution de la section.
     * 
     * Par défaut, la méthode retourne le temps écoulé entre l'appel à 
     * {@link Timer::start()} et l'appel à {@link Timer::leave()}.
     * 
     * Si la section n'est pas terminée (i.e. {@link Timer::leave()} n'a 
     * pas encore été appellée), la méthode retourne le temps écoulé entre 
     * l'appel à {@link Timer::start()} et maintenant. 
     *
     * @return timestamp le temps écoulé (sous la forme d'un réel contenant
     * les secondes dans la partie entière et les en microsecondes dans sa 
     * partie décimale).
     */
    public function getElapsedTime()
    {
        return (is_null($this->endTime) ? microtime(true) : $this->endTime) - $this->startTime;        
    }
    
    /**
     * Retourne les sous-sections qui composent la section.
     * 
     * @return array un tableau d'objets TimerSection ou un tableau vide si
     * la section ne comporte pas de sous-sections.
     */
    public function getSections()
    {
        return $this->children;
    }
    
    /**
     * Retourne la section parente de la section.
     *
     * @return TimerSection|null la section parente ou null si la section n'a pas
     * de parent.
     */
    public function getParent()
    {
        return $this->parent;
    }
    
    /**
     * Démarre une nouvelle sous-section.
     *
     * @param string $name le nom de la sous-section
     * @param timestamp $time par défaut, l'heure de début de la section est
     * l'heure en cours. Time permet de faire démarrer la section à une heure 
     * antérieure.
     * @return TimerSection la sous-section créée.
     */
    protected function & start($name=null, $time=null)
    {
        if (is_null($name))
        {
            $stack=debug_backtrace();
            $name=$stack[2]['class']. '.' .$stack[2]['function']; 
        }
        $timer=new TimerSection($name, $this, $time);
        $this->children[]=& $timer;
        return $timer;
    }
    
    /**
     * Termine la section.
     *
     * @param string $name (optionnel) nom éventuel de la section. Si vous 
     * indiquez un nom, il doit s'agir exactement du même nom que celui utilisé 
     * lors de l'appel à start (utile pour résoudre une erreur de séquençage).
     * 
     * @return TimerSection|null la section parente de cette section ou null si
     * la section n'a pas de parent. 
     */
    protected function & stop($name=null)
    {
        if (! is_null($name))
        {
            if ($name !== $this->name)
                die('erreur de séquençage. Nom attendu : '.$this->name.', nom indiqué : '.$name);
        }
        $this->endTime=microtime(true);
        return $this->parent;
    }

    /**
     * Affiche le temps écoulé pour la section et pour chacune de ses 
     * sous-sections. 
     *
     * @param timestamp|null $total le temps total à utiliser pour le calcul
     * des pourcentages.
     */
    protected function printOutSection($total=null, $html=true, $indent=0)
    {
        if (is_null($this->endTime))
            $elapsed=microtime(true)-$this->startTime;
        else
            $elapsed=$this->endTime-$this->startTime;

        if (is_null($total)) $total=$elapsed;
        
        $nl= $html ? "<br />\n" : "\n";
        $nbsp= $html ? '&#160;' : ' ';
        $strong=$html ? '<strong>%s</strong>' : '%s';
        $bar=$html ? '<code>[%s%s]</code>' : '[%s%s]';
        
        $percent=($elapsed/$total)*100;
        $bars=round(10*$percent/100);
        if ($bars<0) echo 'PPPPPP';
        $percent=round($percent,1);
        printf($bar, str_repeat('=', $bars), str_repeat($nbsp, 10-$bars));
        echo str_repeat($nbsp, $indent), ' ', $this->name, ' : ';
        printf($strong, Utils::friendlyElapsedTime($elapsed));
        echo ' (', $percent, ' %)', $nl;
        
        if ($this->children)
        {
            //echo '<ul>';
            foreach($this->children as $timer)
            {
                $timer->printOutSection($total, $html, $indent+4);
            }
            //echo '</ul>';
        }
        //echo '</li>';
    }
}
?>