<?php
/**
 * Classe utilitaire pour charger en mémoire un thésaurus exporté depuis Cindoc.
 * 
 * Principe
 * 
 * A cause des termes relais ([RE]), aucun des fichiers d'export généré par cindoc 
 * n'est exploitable tel quel. Le plus complet est la liste alphabétique structurée
 * mais cindoc fait apparaître les RE à la fois comme TG et comme TS. Du coup il est
 * impossible de distinguer les deux. 
 * 
 * Exemple :
 * PSYCHOTHERAPIE FAMILIALE
 *   [RE] TYPE DE PSYCHOTHERAPIE            -> TG
 *   [RE] OUTILS ET CONCEPTS                -> TS
 * 
 * Pour contourner le bug, on charge l'intégralité du thésaurus en mémoire en faisant une
 * double lecture : d'abord la liste hiérarchique (pour avoir correctement les MT, TG et TS)
 * ensuite la liste alpha pour compléter les termes avec les EP, EM, TA et NA
 * 
 * Utilisation : 
 * créer un objet ThesaurusCindoc en passant au constructeur les path du fichier
 * alpha et du fichier hiérarchique (les deux fichiers exportés par cindoc)
 * 
 * Appeller ensuite getTheso() pour obtenir un tableau contenant les termes dans le format 
 * Bdsp habituel (Fre,MT,TG,TS,EM,EP,TA,NA)
 * 
 * Le tableau obtenu est trié par ordre alphabétique (no case) des termes
 * 
 * Remarques: quelques vérifications sont faites pour vérifier la cohérence entre le fichier
 * alpha et le fichier hiérarchique. Les erreurs rencontrées sont directement affichées sur
 * la sortie standard sous la forme 'WARNING : xxx'.
 */
class ThesaurusCindoc
{
    private $file=null;
    private $line=null;
    private $theso=array();
    
    public function getTerms()
    {
        return $this->theso;
    }
    
    // ouvre le fichier thesaurus
    public function __construct($alphaPath, $hierPath)
    {
        // Charge la liste hiérarchique
        $this->loadHiera($hierPath);
        
        // Charge la liste alphabétique
        $this->loadAlpha($alphaPath);

        // Trie le thésaurus par ordre alphabétique en ignorant la casse, les lettres accentuées et les mentions '[RE]' de début
        setlocale(LC_COLLATE, 'fra');
        uksort
        (
            $this->theso, 
            create_function
            (
                '$a,$b', 
                'if(substr($a,0,5)===\'[RE] \') $a=substr($a,5);        
                 if(substr($b,0,5)===\'[RE] \') $b=substr($b,5);
                 return strcoll($a,$b);'
             )
        );
    }

    // Charge la hiérarchie
    private function loadHiera($path)
    {
        // Ouvre le fichier 
        if (false === $this->file=@fopen($path, 'rt'))
            throw new Exception("Impossible d'ouvrir le fichier de thesaurus $path");
        $this->skipHeader();
        
        // lit jusque(à la fin)
        while (false !== $this->line)
            $this->readHiera();
            
        // Ferme le fichier
        fclose($this->file);
    }
        
    // Charge la hiérarchie
    private function loadAlpha($path)
    {
        // Ouvre le fichier 
        if (false === $this->file=@fopen($path, 'rt'))
            throw new Exception("Impossible d'ouvrir le fichier de thesaurus $path");
        $this->skipHeader();
        
        // lit jusque(à la fin)
        while (false !== $this->line)
            $this->readAlpha();
            
        // Ferme le fichier
        fclose($this->file);
    }
        
    private function readHiera($MT=null, $TG=null)
    {
        // La ligne en cours nous donne le libellé du terme à construire, détermine son indentation pour savoir quand quiter la boucle
        $indent=strspn($this->line, ' ');

        $fre=trim($this->line);
        
        $term=array();        
        $term['Fre']=$fre;
        if (! is_null($MT)) $term['MT']=$MT;
        if (! is_null($TG)) $term['TG']=$TG; else $MT=$fre;
        $this->get();

        $this->theso[$term['Fre']]=& $term;       
        
        // Tant que la ligne qui suit a une indentation > à la notre, c'est un TS
        while($indent < strspn($this->line, ' '))
        {
            // Charge le TS
            $TS=$this->readHiera($MT, $fre);
            
            // Ajoute le terme lu comme TS du terme en cours de construction
            $this->add($term, 'TS', $TS['Fre'], true);
        }
        
        return $term;
    }

    // lit le prochain terme, retourne un tableau si ok, false si c'est la fin, exception en cas d'erreur
    public function readAlpha()
    {
        if (substr($this->line, 0, 1)===' ')
            throw new exception("Le pointeur de fichier n'est pas au début d'un terme");
        $fre=$this->line;
        
        if (! isset($this->theso[$fre]))
        {
//            throw new Exception("Le terme $fre figure dans la liste hiérarchique mais pas dans la liste alphabétique structurée");
            $this->theso[$fre]=array('Fre'=>$fre);
            $nondes=true;
        }
        else
            $nondes=false;
            
        $term=& $this->theso[$fre];
        
        while(substr($this->get(),0,1)===' ')
        {
            $this->line=ltrim($this->line, " \t");
            if (substr($this->line, 0,1)!=='[' || substr($this->line,3,1)!==']')
                throw new Exception("[xx]attendu : $this->line");

            $rel=substr($this->line, 1,2);
            $this->line=substr($this->line, 5);
            switch(strtoupper($rel))
            {
                case 'RE': // Les putains de termes relais, on ignore, on les a récupérés via la liste hiérarchique
                    continue 2;
                    
                case 'MV': // Mot vedette = le MT chez nous, déjà lu dans la liste hiérarchique
                    $rel='MT';
                case 'TG':  
                case 'TS':
                    if (!isset($term[$rel]))
                        throw new Exception("Le terme $fre a un $rel dans la liste alpha ($this->line) mais n'en a pas dans la liste hiérarchique");
                    if (!in_array($this->line, (array)$term[$rel]))
                    {
                        echo "<p>WARNING : ", ("Le $rel $this->line indiqué dans la liste alpha pour le terme $fre est différent de ce qui est indiqué dans la liste hiérarchique (".implode((array)$term[$rel]).')');
                        echo "</p>";
                    }
                    continue 2;
                    
                case 'EM':  // Les valeurs supplémentaires qu'on récupère dans la liste alpha          
                    $multi=false;
                    break;
                case 'VA': // Voir aussi = TA chez nous
                    $rel='TA';
                case 'EP':
                    $multi=true;
                    break;
                case 'NE'; // Note explicative, NA chez nous
                    $rel='NA';
                    $multi=false;
                    break;
                default:
                    throw new Exception("Type de relation [$rel] non géré pour le terme $fre");
            }
            if ($nondes===true && $rel!='EM')
                throw new Exception("Impossible d'ajouter une relation $rel pour le non-descripteur $fre (ne peut avoit que des EM)");
                    
            $this->add($term, $rel, $this->line, $multi);
        }
    }
    
    //
    private function add(& $term, $rel, $value, $multi=true)
    {
        if (isset($term[$rel]))
        {
            if (!$multi)
                throw new Exception("Erreur dans le fichier thesaurus, plusieurs $rel pour le terme $term[Fre]");
            if (is_array($term[$rel]))
                $term[$rel][]=$value;
            else
                $term[$rel]=array($term[$rel], $value);
        }
        else
        {
            $term[$rel]=$value;
        }
    }   
      
    // Passe les lignes d'entête
    private function skipHeader()
    {
        // jusqu'à ce qu'on lise une ligne contenant 25 étoiles
        for($i=1; $i<=30 && false!==$this->get(); $i++)
        {
            if (substr($this->line, 0,10)==='**********') 
            {
                $this->get();
                return;
            }
        }
        
        // erreur
        throw new Exception("Ligne de séparateur d'entête contenant les étoiles non trouvée dans les 30 premières lignes");
    }
    
    // lit la prochaine ligne en passant les lignes vides ou ne contenant que des espaces ou des tabs
    private function get()
    {
        while(false !== $this->line=fgets($this->file))
        {
            $this->line=rtrim($this->line, " \n\r");
            if ('' !== ltrim($this->line, " \t")) return $this->line;
        }
        return $this->line;
    } 
    
}


?>
