<?php
/**
 * @package     fab
 * @subpackage  response
 * @author      Daniel Ménard <Daniel.Menard@ehesp.fr>
 * @version     SVN: $Id$
 */

/**
 * Représente une réponse de type JSon.
 *
 * @package     fab
 * @subpackage  response
 */
class JsonResponse extends TextResponse
{
    /**
     * @inheritdoc
     */
    protected $contentType = 'application/json';


    /**
     * @inheritdoc
     */
    protected $charset = 'UTF-8';


    /**
     * Constructeur.
     *
     * Construit la réponse JSON et ajoute automatiquement les entêtes http requis pour
     * empêcher que les résultats de la requête ne soient mis en cache par IE.
     */
    public function __construct()
    {
       parent::__construct();

       $this->setHeader('Pragma', 'No-cache');
       $this->setHeader('Cache-Control', 'No-cache, Must-revalidate');
       $this->setHeader('Expires', gmdate('D, d M Y H:i:s').' GMT');
    }


    /**
     * Définit la valeur qui sera générée dans la réponse Json.
     *
     * @param mixed $value la valeur à retourner dans la réponse
     *
     * @return Response $this
     */
    public function setContent($value = null)
    {
        $this->content = json_encode(Utils::utf8Encode($value));

        return $this;
    }


    /**
     * Génère une exception si vous essayez de modifier le content JSon de la réponse.
     *
     * @param mixed $content ignoré
     *
     * @throw Exception systématiquement.
     */
    public function prependContent($content)
    {
        $this->illegal();
    }


    /**
     * Génère une exception si vous essayez de modifier le content JSon de la réponse.
     *
     * @param mixed $content ignoré
     *
     * @throw Exception systématiquement.
     */
    public function appendContent($content)
    {
        $this->illegal();
    }
}
