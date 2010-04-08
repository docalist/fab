<?php
/**
 * @package     fab
 * @subpackage  response
 * @author      Daniel Ménard <Daniel.Menard@ehesp.fr>
 * @version     SVN: $Id$
 */

/**
 * Représente une redirection temporaire ou permanente.
 *
 * @package     fab
 * @subpackage  response
 */
class RedirectResponse extends Response
{
    /**
     * @inheritdoc
     */
    protected $contentType = 'text/html';


    /**
     * @inheritdoc
     */
    protected $charset = 'ISO-8859-1';


    /**
     * @inheritdoc
     */
    protected $status = 302; // 'Moved Temporarily' en HTTP 1.0


    /**
     * Url vers laquelle cette réponse redirgera l'utilisateur.
     *
     * @var string
     */
    protected $location = null;


    /**
     * Construit la redirection.
     *
     * @param string|Request|null $location l'adresse vers laquelle l'utilisateur sera redirigée.
     * Il peut s'agir d'une url relative ou absolue, d'une fab-url ou d'un objet Request.
     * Si vous n'indiquez pas d'adresse, le contenu de la vairable d'environnement HTTP_REFERER
     * est utilisée mais si celle-ci est vide, une exception est générée.
     *
     * @param bool $permanent par défaut la requête génére une redirection temporaire (302). Si vous
     * indiquez true pour ce paramètre, une redirection permanente (301) sera générée à la place.
     *
     * @throw Exception si aucune url n'a été indiquée et que HTTP_REFERER est vide.
     */
    public function __construct($location = null, $permanent = false)
    {
        // Laisse les ancêtres faire leur boulot
        parent::__construct();

        // Si aucune url n'a été indiquée, utilise le referrer
        if (! $location)
        {
            if (isset($_SERVER['HTTP_REFERER']))
                $location = $_SERVER['HTTP_REFERER'];
            else
                throw new Exception('Impossible de déterminer l\'url de redirection');
        }

        // Route l'adresse en url absolue
        else
            $location = Routing::linkFor($location, true);

        // Change le statut par défaut si c'est une redirection permanente
        if ($permanent)
            $this->status = 301;

        // Ajoute l'entête de redirection
        $this->setHeader('Location', $location);

        // Définit le template qui sera utilisé pour générer la réponse
        parent::setTemplate
        (
            $this,
            dirname(__FILE__) . '/RedirectResponse.html',
            array
            (
                'location'=>$location,
                'status' => $this->status,
                'reason' => self::$httpStatus[$this->status]
            )
        );
    }


    /**
     * Génère une exception si vous essayez de définir un contenu pour une requête de ce type.
     *
     * @param mixed $content ignoré
     *
     * @throw Exception systématiquement.
     */
    public function setContent($content = null)
    {
        $this->illegal();
    }


    /**
     * Génère une exception si vous essayez de définir un contenu pour une requête de ce type.
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
     * Génère une exception si vous essayez de définir un contenu pour une requête de ce type.
     *
     * @param mixed $content ignoré
     *
     * @throw Exception systématiquement.
     */
    public function appendContent($content)
    {
        $this->illegal();
    }

    /**
     * Génère une exception si vous essayez de définir un contenu pour une requête de ce type
     *
     * @param object $context ignoré
     * @param string $path ignoré
     * @param mixed $data ... ignoré
     *
     * @throw Exception systématiquement.
     */
    public function setTemplate($context, $path, $data=null)
    {
        $this->illegal();
    }
}