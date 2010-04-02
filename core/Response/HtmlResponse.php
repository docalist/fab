<?php
/**
 * @package     fab
 * @subpackage  response
 * @author      Daniel Ménard <Daniel.Menard@ehesp.fr>
 * @version     SVN: $Id$
 */

/**
 * Représente une réponse Html.
 *
 * @package     fab
 * @subpackage  response
 */
class HtmlResponse extends LayoutResponse
{
    /**
     * @inheritdoc
     */
    protected $contentType = 'text/html';

    /**
     * @inheritdoc
     */
    protected $charset = 'ISO-8859-1';

}