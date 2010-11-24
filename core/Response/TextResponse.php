<?php
/**
 * @package     fab
 * @subpackage  response
 * @author      Daniel Ménard <Daniel.Menard@ehesp.fr>
 * @version     SVN: $Id$
 */

/**
 * Représente une réponse de type 'text/plain'.
 *
 * @package     fab
 * @subpackage  response
 */
class TextResponse extends Response
{
    /**
     * @inheritdoc
     */
    protected $contentType = 'text/plain';

    /**
     * @inheritdoc
     */
    protected $charset = 'ISO-8859-1';

    /**
     * Constructeur.
     *
     * Construit la réponse et ajoute un entête spécifique à IE8 pour le forcer à respecter
     * le Content-Type indiqué ({@link
     * http://blogs.msdn.com/ie/archive/2008/07/02/ie8-security-part-v-comprehensive-protection.aspx
     * X-Content-Type-Options: nosniff}).
     */
    public function __construct()
    {
       parent::__construct();

       $this->setHeader('X-Content-Type-Options', 'nosniff'); // ne fonctionne qu'avec IE8+
       // Source : http://blogs.msdn.com/ie/archive/2008/07/02/ie8-security-part-v-comprehensive-protection.aspx
    }

}
