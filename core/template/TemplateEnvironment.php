<?php
/**
 * @package     fab
 * @subpackage  template
 * @author      Daniel Ménard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id$
 */

/**
 * Gère l'environnement d'exécution d'un template, c'est à dire l'ensemble
 * des sources de données (tableaux, objets, callback...) passées en paramètre
 * lors de l'exécution d'un template ainsi que les variables temporaires
 * introduites par le code du template.
 * 
 * Cette classe n'est utilisée que pendant la compilation d'un template 
 * (cf {@link TemplateCompiler}).
 * 
 * @package     fab
 * @subpackage  template
 */
class TemplateEnvironment
{
    /**
     * @var array L'environnement d'exécution du template, c'est à dire l'ensemble
     * des données, tableaux, objets et callbacks passés en paramêtres pour instancier le
     * template.
     * 
     * Initialement, il s'agit d'une copie exacte de l'environnement passé à Template::run.
     * 
     * Si le template crée des données locales (par exemple les variables créées par 
     * l'attribut as d'une balise loop), lors de l'entrée dans le bloc, elles seront 
     * ajoutées au début du tableau afin qu'elles soient prioritaires (cf {@link push()}).
     * 
     * Lorsqu'on sort du bloc qui a créé les variables, on dépile simplement le premier 
     * élément du tableau pour retrouver l'environnement initial (cf {@link pop()}).
     * 
     * @access private
     */
    private $env=array();
    
    /**
     * @var integer indique le nombre d'éléments de {@link $env} qui sont des variables
     * locales. Pour chaque élément i de $env, si i&lt;localCount, alors i est une variable locale,
     * sinon, c'est une données passée en paramètre à Template::Run
     * 
     * @access private 
     */
    private $localCount=0;
    

    /**
     * @var array Liaisons entre chacune des variables rencontrées dans le template
     * et la source de données correspondante.
     * Ce tableau est construit au cours de la compilation. Les bindings sont ensuite
     * générés au début du template généré.
     * 
     * @access private
     */
    private $bindings=array();
    
    
    /**
     * Initialise un nouvel environnement contenant les sources de données présentes dans le 
     * tableau passé en paramètre
     * 
     * @param array|null $env un tableau contenant les sources de données du template
     * @return void 
     */
    public function __construct($env=null)
    {
        $this->env=$env;
    }


    /**
     * Empile de nouvelles variables locales dans l'environnement d'exécution du template.
     *
     * @param array $vars un tableau contenant les variables à ajouter à l'environnement.
     * Pour chaque élément, la clé indique le nom de la variable (sans le dollar initial)
     * sous sa forme visible parl'utilisateur, et la valeur indique le code php qui sera 
     * utilisé chaque fois que cette variable sera utilisée dans le template (en général
     * il s'agit du nom de la variable réelle).
     */
    public function push($vars)
    {
        array_unshift($this->env, $vars); // empile au début
        ++$this->localCount;
    }


    /**
     * Restaure l'environnement d'exécution tel qu'il était avant le dernier appel
     * à {@link push()} en supprimant de l'environnement le dernier tableau de 
     * variables ajouté.
     * 
     * @return array l'élément dépilé
     */
    public function pop()
    {
        --$this->localCount;
        return array_shift($this->env);    // dépile au début
    }


    /**
     * Recherche une variable dans l'environnement et retourne le code php correspondant.
     * 
     * Si la variable est trouvée et qu'il ne s'agit pas d'une variable locale, une liaison
     * est établie entre la variable telle qu'indiquée dans le template et la source de données
     * correspondante.
     * 
     * @param string $var la variable recherchée
     * @return string|boolean le code php correspondant à la variable ou false si aucune source
     * de données ne connaît la variable recherchée.
     */
    public function get($var)
    {
        debug && Debug::log('%s', $var);

        // Parcours toutes les sources de données
        foreach ($this->env as $i=>$data)
        {
            $j=$i-$this->localCount;
            // Clé d'un tableau de données
            if (is_array($data) && array_key_exists($var, $data)) 
            {
                debug && Debug::log('C\'est une clé du tableau de données');
                
                // Si c'est une variable locale introduite par un bloc, pas de binding
                if ($i<$this->localCount) return $data[$var];

                // Sinon, c'est une variable utilisateur, crée une liaison
                return $this->addBinding($var, 'Template::$data['.$j.'][\''.$var.'\']');
            }

            // Objet
            if (is_object($data))
            {
                // Propriété d'un objet
                
                /*
                    property_exists teste s'il s'agit d'une propriété réelle de l'objet, mais ne fonctionne pas si c'est une propriété magique
                    isset teste bien les deux, mais retourera false pour une propriété réelle existante mais dont la valeur est "null"
                    du coup il faudrait faire les deux...
                    mais isset, pour les méthodes magiques, ne fonctionnera que si l'utilisateur a écrit une méthode __get dans son objet
                    au final, on fait simplement un appel à __get (si la méthode existe), et on teste si on récupère "null" ou pas
                 */
 
                if (property_exists($data,$var) || (is_callable(array($data,'__get'))&& !(is_null(call_user_func(array($data,'__get'),$var)))))
                {
                    debug && Debug::log('C\'est une propriété de l\'objet %s', get_class($data));
                    return $this->addBinding($var, 'Template::$data['.$j.']->'.$var);
                }
                
                // Clé d'un objet ArrayAccess
                if ($data instanceof ArrayAccess)
                {
                    try // tester avec isset
                    {
                        debug && Debug::log('Tentative d\'accès à %s[\'%s\']', get_class($data), $var);
                        $value=$data[$var]; // essaie d'accéder, pas d'erreur ?

                        $code=$this->addBinding(get_class($data), 'Template::$data['.$j.']');
                        return $code.'[\''.$var.'\']';
                        // pas de référence : see http://bugs.php.net/bug.php?id=34783
                        // It is impossible to have ArrayAccess deal with references
                    }
                    catch(Exception $e)
                    {
                        debug && Debug::log('Génère une erreur %s', $e->getMessage());
                    }
                }
                else
                    debug && Debug::log('Ce n\'est pas une clé de l\'objet %s', get_class($data));
            }

            // Fonction de callback
            if (is_callable($data))
            {
                Template::$isCompiling++;
                ob_start();
                $value=call_user_func($data, $var);
                ob_end_clean();
                Template::$isCompiling--;
                
                // Si la fonction retourne autre chose que "null", terminé
                if ( ! is_null($value) )
                {
                    // Simple fonction 
                    if (is_string($data))
                    {
                        $code=$this->addBinding($data, 'Template::$data['.$j.']');
                        return $code.'(\''.$var.'\')';
                    }

                    // Méthode statique ou dynamique d'un objet
                    else // is_array
                    {
                        $code=$this->addBinding($data[1], 'Template::$data['.$j.']');
                        return 'call_user_func(' . $code.', \''.$var.'\')';
                    } 
                }
            }
            
            //echo('Datasource incorrecte : <pre>'.print_r($data, true). '</pre>');
        }
        //echo('Aucune source ne connait <pre>'. $name.'</pre>');
        return false;
    }
    
    /**
     * nom de la variable dans le template => (nom de la variable compilée, code de la liaison)
     */
    private function addBinding($var, $code)
    {
        $h=$var.' --- '.$code;
        if (isset($this->bindings[$h]))
        {
//            if ($this->bindings[$var][1] !== $code)
//                throw new Exception("Appels multiples à addBinding('$var') avec des liaisons différentes"
//                . ' Valeur actuelle : ' . $this->bindings[$var][1] .', '
//                . ' Nouvelle valeur : ' . $code
//                
//                );
            return $this->bindings[$h][0];
        }
        $temp=$this->getTemp($var);
        $this->bindings[$h]=array($temp,$code);
        return $temp;
    }
    
    public function getBindings()
    {
        $h="\n    //Liste des variables de ce template\n" ;
        foreach ($this->bindings as $name=>$binding)
            $h.='    ' . $binding[0] . '=' . $binding[1] . "; // variable $name\n";
            
    	return $h;
    }
    
    private $tempVars=array();
    
    /**
     * Alloue une nouvelle variable temporaire
     * 
     * La variable temporaire reste allouée temps que {link freeTemp()} n'est pas 
     * appellée pour cette variable. Si d'autres appels à getTemp sont faits avec 
     * le même nom, les variables retournées seront numérotées (tmp, tmp2, etc.) 
     * 
     * @param string $name le nom souhaité pour la variable temporaire à allouer
     * @return string
     */
    public function getTemp($name='tmp')
    {
        if ($name[0]==='$') $name=substr($name,1);
        if ($name[0]!=='_') $name='_'.$name;

        // On travaille en minu, sinon, si on est appellé avec le même préfixe mais avec des casses différentes,
        // on génère des variables distingues vis à vis de php, mais assez difficiles à distinguer (par exemple getField1 et getfield1)
        $lcname=strtolower($name);
        $h=$lcname;
        
        for($i=2; $i<=100; $i++)
        {
            if (!isset($this->tempVars[$h]))
            {
                $this->tempVars[$h]=true;
                return '$'.$name.($i===2?'':$i-1);
            }
        	$h=$lcname.$i;
            
        }
        throw new Exception("Erreur interne : plus de 100 variables temporaires pour $name");
    }
    
    /**
     * Libère une variable temporaire allouée par {link getTemp()}
     * 
     * @param string $name le nom de la variable temporaire à libérer
     */
    public function freeTemp($name)
    {
        if ($name[0]==='$') $name=substr($name,1);
        if ($name[0]!=='_') $name='_'.$name;
        $name=strtolower($name);
        
        if (!isset($this->tempVars[$name]))
            throw new Exception("La variable temporaire $name n'existe pas");
        unset($this->tempVars[$name]);
    }
    
}
?>