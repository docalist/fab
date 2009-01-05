<?php
/**
 * Module de consultation de thesaurus (monolingue, monohiérarchique)
 * 
 * @package     fab
 * @subpackage  modules
 */
class ThesaurusModule extends DatabaseModule
{
    public function preExecute()
    {
//        if (Utils::isAjax())
//            $this->setLayout('none');	
    }
        
    /**
     * Affiche la hiérarchie d'un champ sémantique.
     * 
     * Principe : 
     * - L'utilisateur passe en paramètre le libellé (?Fre=<terme>) d'un champ 
     *   sémantique (c'est-à-dire un terme pour lequel TG est vide).
     * - On recherche tous les enregistrements pour lesquels MT=<terme>
     * - On parcourt toutes les réponses obtenues pour constituer un tableau 
     *   qui pour chaque TG trouvé contient un tableau contenant les TS.
     * - On utilise la méthode {@link makeHierarchy()} qui reconstitue la 
     *   hiérarchie complète du champ sémantique demandée à partir de ce tableau.
     * - On appelle le template indiqué par la méthode {@link getTemplate()} 
     *   pour afficher le résultat.
     */
    public function actionHierarchy()
    {
        // Ouvre la base de données
        $this->openDatabase();

        // Détermine la recherche à exécuter        
        $this->equation=$this->getEquation();

        // Récupère le libellé exact du terme demandé
        if (! $this->select($this->equation, 1, 1, '+'))
        {
            $this->showNoAnswer("La requête $this->equation n'a donné aucune réponse.");
            return;
        }
        $term=$this->selection['Fre'];
        
        // Lance une requête MT:<terme>, gère le cas aucune réponse
        $this->equation='MT:' . $this->termToQuery($term);
        if (! $this->select($this->equation, -1, 1, '+'))
        {
            $this->showNoAnswer("Le terme $term ne désigne pas un champ sémantique du thésaurus.");
            return;
        }
        
        // Constitue le tableau initial TG -> liste des TS
        $hierarchy=array();
        foreach($this->selection as $record)
        {
            //echo $record['Fre'], ' TG : ', $record['TG'], '<br />';
            Utils::arrayAppendKey($hierarchy, $record['TG'], $record['Fre']);
        }    

        // Reconstitue la hiérarchie du terme
        $this->makeHierarchy($hierarchy[$term], $hierarchy);

        // Détermine le template à utiliser
        if (! $template=$this->getTemplate())
            throw new Exception('Le template à utiliser n\'a pas été indiqué');
        
        // Exécute le template
        Template::run
        (
            $template,
            array('hierarchy'=>$hierarchy)
        );  
    }
    
    /**
     * Reconstitue la hiérarchie complète d'un champ sémantique.
     * 
     * @param array $term un tableau désignant le terme dont on veut reconstituer
     * la hiérarchie. 
     * @param array $allTerms un tableau contenant tous les termes.
     * En sortie, ne contiendra plus que le terme recherché.
     * 
     * Important :
     * - $term doit obligatoirement être l'un des éléments du tableau 
     *   <code>$hierarchy</code>. Exemple :
     *   <code>makeHierarchy($t['famille'], $t)</code>
     * - les deux paramètrs son "by ref".
     * 
     * @see actionHierarchy()
     */
    private function makeHierarchy(& $term, & $allTerms)
    {
        $t=array();
        foreach((array)$term as $value)
        {
            if (isset($allTerms[$value]))
            {
                $this->makeHierarchy($allTerms[$value], $allTerms);
                $t[$value]=$allTerms[$value];
                unset($allTerms[$value]);
            }
            else
            {
                $t[$value]=$value;
            }
        }
        $term=$t;
    }
    
    /**
     * Nettoie le terme passé en paramètre pour qu'il puisse être utilisé dans 
     * une requête pour faire une recherche à l'article.
     * 
     * Le traitement consiste à 
     * - remplacer par un esapce les caractères reconnus comme opérateurs
     *   (crochets, parenthèses, &, +, - 
     * (neutralise les crochets présents dans le terme et l'encadre de crochets)
     * 
     * @param string $term le terme à nettoyer
     * @param string le bout de requête obtenu
     */ 
    public function termToQuery($term, $asValue=true, $encode=true)
    {
        $result = str_replace('  ', ' ', trim(strtr($term, '[]()&+-', '       ')));
        if ($encode) $result=urlencode($result);
        if ($asValue) $result = '[' . $result . ']';
        return $result;
    }
}
?>
