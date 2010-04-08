<?php
/**
 * @package     fab
 * @subpackage  response
 * @author      Daniel Ménard <Daniel.Menard@ehesp.fr>
 * @version     SVN: $Id$
 */

/**
 * Représente une réponse dont le contenu provient d'un fichier existant.
 *
 * @package     fab
 * @subpackage  response
 */
class FileResponse extends Response
{
    /**
     * @inheritdoc
     */
    protected $contentType = 'application/octet-stream';


    protected $name = null;
    protected $download = false;

    /**
     * Génère une exception si vous essayez de définir un contenu pour une requête de ce type.
     *
     * @param mixed $content ignoré
     *
     * @throw Exception systématiquement.
     */
    public function setContent($content = null/* , $name=null, $download=false */)
    {
//        $this->name=$name;
//        $this->download = $download;
        //$this->setHeader(sprintf('Content-Disposition: %s; filename="%s"', $download ? 'attachment' : 'inline', $name));

        return parent::setContent($content);
        // Un path
//        if (is_string($content))
//        elseif(is_resource(content))
//        elseif($content instanceof Response)
    }

    /**
     * Envoie le contenu du fichier.
     *
     * @return Response $this
     */
    public function outputContent()
    {
        // Content contient le path d'un fichier
        if (is_string($this->content))
        {
            readfile($this->content);
        }

        // Content contient un handle de fichier déjà ouvert (ou un stream)
        elseif(is_resource($this->content))
        {
            fpassthru($this->content);
        }

        // Content contient une réponse
        elseif($content instanceof Response)
        {
            $content->outputContent();
        }

        // Done
        return $this;
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
        $this->illegal(__FUNCTION__);
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