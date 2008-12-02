<?php
/**
 * @package     Bdsp
 * @subpackage  core
 * @author      Daniel Ménard <Daniel.Menard@ehesp.fr>
 * @version     SVN: $Id$
 */

/**
 * Interface définissant un singleton.
 * 
 * Cette interface sert essentiellement de "signature" aux modules qui 
 * fonctionnent comme des singletons. Pour tester si un module est un 
 * singleton, il suffit alors d'utiliser :
 * <code>if ($module instanceOf 'Singleton') ...</code>.
 * 
 * Pour qu'une classe fonctionne réellement comme un Singleton, celle-ci doit
 * normallement implémenter plusieurs méthodes telles que 
 * <code>getInstance()</code>, <code>hasInstance()</code>, 
 * <code>releaseInstance()</code>. Elle doit également empêcher que de 
 * nouvelles instances de la classes ne soient créées, ce qui peut être fait en 
 * générant une exception si les méthodes <code>__construct()</code>, 
 * <code>__clone()</code> ou <code>__wakeup()</code> sont appellées ou en 
 * réduisant la visibilité de ces méthodes (<code>private</code> ou 
 * <code>protected</code>).
 * 
 * Pour éviter d'être trop "rigide", cette interface ne définit que la méthode 
 * <code>getInstance()<code> qui est la seule méthode réellement indispensable;
 * 
 * @package     Bdsp
 * @subpackage  core 
 */
interface Singleton
{
   /**
    * Retourne l'instance unique de la classe.
    * 
    * @return StdClass L'instance du module
    */
    public static function getInstance();
}