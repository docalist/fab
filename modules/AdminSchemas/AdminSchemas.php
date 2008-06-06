<?php

/**
 * @package     fab
 * @subpackage  Admin
 * @author      Daniel Ménard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id$
 */

/**
 * Module d'administration des schémas.
 * 
 * @package     fab
 * @subpackage  Admin
 */
class AdminSchemas extends AdminFiles
{
    /**
     * Page d'accueil du module d'administration des schémas de bases de données.
     * 
     * Affiche la liste des schémas disponibles.
     */
    public function actionIndex()
    {
        Template::run
        (
            Config::get('template')
        );
    }
    
        
    /**
     * Retourne la liste des schémas connus du système.
     *
     * Par défaut, la fonction retourne la liste des schémas de l'application.
     * Si $fab est à true, c'est la liste des schémas de fab qui sera retournée.
     * 
     * @param bool $fab true pour récupérer les schémas définis dans fab,
     * false (valeur par défaut) pour retourner les schémas de l'application.
     * 
     * @return array un tableau contenant le path de tous les schémas 
     * disponibles (la clé associé contient le nom du schéma, c'est-à-dire
     * <code>basename($path)</code>).
     */
    public static function getSchemas($fab=false)
    {
        $path=($fab ? Runtime::$fabRoot : Runtime::$root) . 'data/schemas/';
        
        // Construit la liste
        $files=glob($path.'*.xml');
        if ($files===false) return array();

        $schemas=array();
        foreach($files as $file)
        {
            $schemas[$file]=basename($file);
        }
        
        // Trie par ordre alphabétique du nom
        uksort($schemas, 'strcoll');

        return $schemas;
    }
    
    
    /**
     * Retourne le schéma dont le nom est passé en paramètre.
     *  
     * @return DatabaseSchema|false
     */
    public static function getSchema($schema)
    {
        $path='data/schemas/';
        $path=Utils::searchFile($schema, Runtime::$root . $path, Runtime::$fabRoot . $path);
        if ($path === false) return false;
        return new DatabaseSchema(file_get_contents($path));
    }

    
    /**
     * Edite un schéma de l'application.
     * 
     * @param string $file le nom du fichier xml à éditer
     */
    public function actionEditSchema($file)
    {
        $dir='data/schemas/';

        // Vérifie que le fichier indiqué existe
        $path=Runtime::$root.$dir.$file;
        if (! file_exists($path))
            throw new Exception("Le schéma $file n'existe pas.");
            
        // Charge le schéma
        $schema=new DatabaseSchema(file_get_contents($path));
                
        // Valide et redresse le schéma, ignore les éventuelles erreurs
        $schema->validate();

        // Charge le schéma dans l'éditeur
        Template::run
        (
            Config::get('template'),
            array
            (
                'schema'=>$schema->toJson(), // hum.... envoie de l'utf-8 dans une page html déclarée en iso-8859-1...
                'saveUrl'=>'SaveSchema',
                'saveParams'=>"{file:'$file'}",
                'title'=>'Modification de '.$file
            )
        );
    }
    
    
    /**
     * Vérifie et sauvegarde un schéma.
     * 
     * Cette action permet d'enregistrer un schéma modifié avec l'éditeur de 
     * structure.
     * 
     * Elle commence par valider le schéma passé en paramètre. Si des 
     * erreurs sont détectées, une réponse au format JSON est générée. Cette
     * réponse contient un tableau contenant la liste des erreurs rencontrées.
     * La réponse sera interprétée par l'éditeur de schéma qui affiche la
     * liste des erreurs à l'utilisateur.
     * 
     * Si aucune erreur n'a été détectée, le schéma est enregistré.
     * Dans ce cas, une chaine de caractères au format JSON est retournée 
     * à l'éditeur. Elle indique l'url vers laquelle l'utilisateur va être 
     * redirigé. 
     *
     * @param string $file le nom du fichier xml dans lequel enregistrer le
     * schéma.
     *   
     * @param string $schema une chaine de caractères au format JSON contenant le
     * schéma à valider et à enregistrer.
     * 
     * @throws Exception si le fichier indiqué n'existe pas.
     */
    public function actionSaveSchema($file, $schema)
    {
        $dir='data/schemas/';

        // Vérifie que le fichier indiqué existe
        $path=Runtime::$root.$dir.$file;
        if (! file_exists($path))
            throw new Exception("Le schéma $file n'existe pas.");
        
        // Charge le schéma
        $schema=new DatabaseSchema($schema);
        
        // Valide le schéma et détecte les erreurs éventuelles
        $result=$schema->validate();
        
        // S'il y a des erreurs, retourne un tableau JSON contenant la liste
        if ($result !== true)
        {
            header('Content-type: application/json; charset=iso-8859-1');
            echo json_encode(Utils::utf8Encode($result));
            return;
        }
        
        // Compile le schéma (attribution des ID, etc.)
        $schema->compile();
        
        // Met à jour la date de dernière modification (et de création éventuellement)
        $schema->setLastUpdate();
        
        // Aucune erreur : sauvegarde le schéma
        file_put_contents($path, $schema->toXml());
        
        // Retourne l'url vers laquelle on redirige l'utilisateur
        header('Content-type: application/json; charset=iso-8859-1');
        echo json_encode(Routing::linkFor($this->request->clear()->setAction('index'), true));
    }
    
    
    /**
     * Crée un nouveau schéma (vide).
     * 
     * Vérifie que le fichier indiqué existe, demande le nouveau nom
     * du fichier, vérifie que ce nom n'est pas déjà pris, renomme le
     * fichier, redirige vers la page d'accueil.
     */
    public function actionNew($file='')
    {
        $dir='data/schemas/';

        $error='';
        if ($file !== '')
        {
            // Ajoute l'extension '.xml' si nécessaire
            $file=Utils::defaultExtension($file, '.xml');
            if (Utils::getExtension($file) !== '.xml')
                $file.='.xml';
        
            // Vérifie que le fichier indiqué n'existe pas déjà
            $path=Runtime::$root.$dir.$file;
            if ($file !== '' && file_exists($path))
                $error='Il existe déjà un fichier portant ce nom.';
        }
                        
        if ($file==='' || $error !='')
        {
            Template::run
            (
                Config::get('template'),
                array('file'=>$file, 'error'=>$error)
            );
            return;
        }
        
        // Crée un nouveau schéma
        $schema=new DatabaseSchema();
        
        // Enregistre le schéma dans le fichier indiqué
        file_put_contents($path, $schema->toXml());
                
        Runtime::redirect('/'.$this->module);
    }
    
    public function actionChoose($link='Edit?file=%s', $fab=false)
    {
        Template::run
        (
            Config::get('template'),
            array('link'=>$link, 'fab'=>$fab)
        );
    }
}
?>