<?php

/**
 * @package     fab
 * @subpackage  Admin
 * @author      Daniel M�nard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id$
 */

/**
 * Module d'administration des sch�mas.
 *
 * @package     fab
 * @subpackage  Admin
 */
/**
 * Module d'administration permettant de g�rer les {@link DatabaseSchema sch�mas
 * de bases de donn�es de l'application}.
 *
 * Les sch�mas de bases de donn�es sont des fichiers
 * {@link http://fr.wikipedia.org/wiki/Xml xml} stock�s dans le r�pertoire
 * <code>/data/schemas</code> de l'application et qui d�finissent la structure
 * d'une base de donn�es (liste des champs, des index, param�tres, etc.)
 *
 * Ce module permet de lister tous les sch�mas de l'application ainsi que les
 * {@link /AdminSchemas mod�les de sch�mas propos�s par fab}.
 *
 * Il h�rite du module {@link AdminFiles} et dispose donc des actions
 * de base permettant de {@link AdminFiles::actionCopy() copier},
 * {@link AdminFiles::actionRename() renommer},
 * {@link AdminFiles::actionDelete() supprimer} et
 * {@link AdminFiles::actionDownload() t�l�charger} un fichier de sch�ma ainsi
 * que de la possibilit� {@link actionEdit() d'�diter le code xml} d'un sch�ma
 * ou de {@link AdminFiles::copyFrom() cr�er un nouveau sch�ma � partir d'un
 * mod�le propos� par fab}.
 *
 * <code>AdminSchemas</code> introduit un �diteur sp�cifique qui permet de
 * {@link actionNew() cr�er}, {@link actionEditSchema() modifier} et
 * {@link actionSaveSchema() sauvegarder} un sch�ma de fa�on plus intuitive
 * qu'en intervenant sur le code xml sous-jacent et dispose d'une fonction de
 * validation int�gr�e qui emp�che la cr�ation d'un
 * {@link DatabaseSchema::validate() sch�ma invalide}.
 *
 * Pour plus d'informations, vous pouvez consulter :
 * - La documentation de l'action {@link AdminDatabases::actionSetSchema SetSchema}
 *   du module d'administration {@link /AdminDatabases AdminDatabases} qui permet
 *   d'appliquer un sch�ma � une base de donn�es.
 * - {@link DatabaseSchema l'API de la classe DatabaseSchema}.
 *
 * @package     fab
 * @subpackage  Admin
 */
class AdminSchemas extends AdminFiles
{
    /**
     * Page d'accueil du module d'administration des sch�mas de bases de donn�es.
     *
     * Affiche la liste des sch�mas disponibles.
     */
    public function actionIndex()
    {
        Template::run
        (
            Config::get('template')
        );
    }


    /**
     * Retourne la liste des sch�mas connus du syst�me.
     *
     * Par d�faut, la fonction retourne la liste des sch�mas de l'application.
     * Si $fab est � true, c'est la liste des sch�mas de fab qui sera retourn�e.
     *
     * @param bool $fab true pour r�cup�rer les sch�mas d�finis dans fab,
     * false (valeur par d�faut) pour retourner les sch�mas de l'application.
     *
     * @return array un tableau contenant le path de tous les sch�mas
     * disponibles (la cl� associ� contient le nom du sch�ma, c'est-�-dire
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

        // Trie par ordre alphab�tique du nom
        uksort($schemas, 'strcoll');

        return $schemas;
    }


    /**
     * Retourne le sch�ma dont le nom est pass� en param�tre.
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
     * Edite un sch�ma de l'application.
     *
     * @param string $file le nom du fichier xml � �diter
     */
    public function actionEditSchema($file)
    {
        $dir='data/schemas/';

        // V�rifie que le fichier indiqu� existe
        $path=Runtime::$root.$dir.$file;
        if (! file_exists($path))
            throw new Exception("Le sch�ma $file n'existe pas.");

        // Charge le sch�ma
        $schema=new DatabaseSchema(file_get_contents($path));

        // Valide et redresse le sch�ma, ignore les �ventuelles erreurs
        $schema->validate();

        // Charge le sch�ma dans l'�diteur
        Template::run
        (
            Config::get('template'),
            array
            (
                'schema'=>$schema->toJson(), // hum.... envoie de l'utf-8 dans une page html d�clar�e en iso-8859-1...
                'saveUrl'=>'SaveSchema',
                'saveParams'=>"{file:'$file'}",
                'title'=>'Modification de '.$file
            )
        );
    }


    /**
     * V�rifie et sauvegarde un sch�ma.
     *
     * Cette action permet d'enregistrer un sch�ma modifi� avec l'�diteur de
     * structure.
     *
     * Elle commence par valider le sch�ma pass� en param�tre. Si des
     * erreurs sont d�tect�es, une r�ponse au format JSON est g�n�r�e. Cette
     * r�ponse contient un tableau contenant la liste des erreurs rencontr�es.
     * La r�ponse sera interpr�t�e par l'�diteur de sch�ma qui affiche la
     * liste des erreurs � l'utilisateur.
     *
     * Si aucune erreur n'a �t� d�tect�e, le sch�ma est enregistr�.
     * Dans ce cas, une chaine de caract�res au format JSON est retourn�e
     * � l'�diteur. Elle indique l'url vers laquelle l'utilisateur va �tre
     * redirig�.
     *
     * @param string $file le nom du fichier xml dans lequel enregistrer le
     * sch�ma.
     *
     * @param string $schema une chaine de caract�res au format JSON contenant le
     * sch�ma � valider et � enregistrer.
     *
     * @throws Exception si le fichier indiqu� n'existe pas.
     */
    public function actionSaveSchema($file, $schema)
    {
        $dir='data/schemas/';

        // V�rifie que le fichier indiqu� existe
        $path=Runtime::$root.$dir.$file;
        if (! file_exists($path))
            throw new Exception("Le sch�ma $file n'existe pas.");

        // Charge le sch�ma
        $schema=new DatabaseSchema($schema);

        // Valide le sch�ma et d�tecte les erreurs �ventuelles
        $result=$schema->validate();

        // S'il y a des erreurs, retourne un tableau JSON contenant la liste
        if ($result !== true)
            return Response::create('JSON')->setContent($result);

        // Compile le sch�ma (attribution des ID, etc.)
        $schema->compile();

        // Met � jour la date de derni�re modification (et de cr�ation �ventuellement)
        $schema->setLastUpdate();

        // Aucune erreur : sauvegarde le sch�ma
        file_put_contents($path, $schema->toXml());

        // Retourne l'url vers laquelle on redirige l'utilisateur
        return Response::create('JSON')->setContent
        (
            Routing::linkFor($this->request->clear()->setAction('index'), true)
        );
    }


    /**
     * Cr�e un nouveau sch�ma (vide).
     *
     * V�rifie que le fichier indiqu� existe, demande le nouveau nom
     * du fichier, v�rifie que ce nom n'est pas d�j� pris, renomme le
     * fichier, redirige vers la page d'accueil.
     */
    public function actionNew($file='')
    {
        $dir='data/schemas/';

        $error='';
        if ($file !== '')
        {
            // Ajoute l'extension '.xml' si n�cessaire
            $file=Utils::defaultExtension($file, '.xml');
            if (Utils::getExtension($file) !== '.xml')
                $file.='.xml';

            // V�rifie que le fichier indiqu� n'existe pas d�j�
            $path=Runtime::$root.$dir.$file;
            if ($file !== '' && file_exists($path))
                $error='Il existe d�j� un fichier portant ce nom.';
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

        // Cr�e un nouveau sch�ma
        $schema=new DatabaseSchema();

        // Enregistre le sch�ma dans le fichier indiqu�
        file_put_contents($path, $schema->toXml());

        Runtime::redirect('/'.$this->module);
    }

    /**
     * Slot permettant � un autre module de choisir un sch�ma.
     *
     * @param string $link le lien � appliquer � chaque sch�ma.
     * @param bool $fab true pou afficher les sch�mas de l'application,
     * false pour afficher ceux de fab.
     */
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