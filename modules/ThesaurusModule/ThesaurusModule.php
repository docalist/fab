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
    
    public function actionImportAsco()
    {
        require_once dirname(__FILE__).'/ThesaurusCindoc.php';
        
        // Path du fichier thesaurus à importer
        $alphaPath=dirname(__FILE__) . '/alpha-26-05-08.txt';
        $hierPath=dirname(__FILE__) . '/hier-26-05-08.txt';
        
        // Charge le fichier thesaurus
        $theso=new ThesaurusCindoc($alphaPath, $hierPath);

        // Ouvre la base
        $this->openDatabase(false);
        
        // Charge tous les termes dans la base
        $nb=0;
        foreach($theso->getTerms() as $fre=>$term)
        {
            // Crée une nouvelle notice
            $this->selection->addRecord();
            
            // Initialise tous les champs
            foreach($term as $rel=>$value)
                $this->selection[$rel]=$value;
            
            // Ajoute la notice dans la base
            $this->selection->saveRecord();

            // 
            $nb++;
            echo $nb, ' ', $fre, '<br />';
        }
        
        // Ferme le fichier thesaurus
        unset($theso);

        // Ferme la base
        unset($this->selection);
    }
}
?>
