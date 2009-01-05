<?php
/**
 * Tests pour le module de routing
 * 
 * On teste les fonctions suivantes :
 *  
 * - transform() : compile les routes stockées sous forme xml en tableaux 
 *   permettant à linkFor() et routeFor() de travailler rapidement.
 * - urlParts() : découpe une url en morceaux
 * - routeFor() : convertit l'url de la page appellée en module/action
 * - linkFor()  : convertit un module/action en url
 * 
 * Le tests n'utilisent aucune des routes prédéfinies dans fab ou dans 
 * l'application (on commence toujours par Config::clear('routes')).
 * 
 * On utilise deux fichiers contenant des routes spécifiques à nos tests :
 * - routing.config : contient les routes par défaut de fab et des routes
 *   écrites spécifiquement pour les tests
 * - routing.blog.config : des routes pour un blog imaginaire ayant des urls 
 *   très personnalisées (urls incluant le titre des articles ou encore urls
 *   de la forme /articles/tag/php+mvc+framework pour lancer une recherche en 
 *   ET sur les mots-clés indiqués)   
 *
 */
class RoutingTest extends AutoTestCase
{
    /**
     * Test de Routing::transform()
     * 
     * Fichiers utilisés : transform.testfile
     *
     */
    public function testfileTransform()
    {
        $this->runTestFile(dirname(__FILE__).'/transform.testfile',array($this,'transformCallback'));
    }
    
    public function transformCallback($routes)
    {
        return var_export(Routing::transform(Config::loadXml($routes)),true);
    }

    
    /**
     * Test de Routing::urlParts()
     *
     */
    public function testfileUrlParts()
    {
        $this->runTestFile(dirname(__FILE__).'/urlParts.testfile',array($this,'urlPartsCallback'));
    }

    public function urlPartsCallback($url)
    {
        return var_export(Routing::urlParts($url),true);
    }

    
    /**
     * Test de Routing::routeFor()
     *
     */
    public function testfileRouteFor1()
    {
        $this->runTestFile(dirname(__FILE__).'/routeFor.testfile',array($this,'routeForCallback1'));
    }
    
    public function routeForCallback1($url)
    {
        Config::clear('routing');
        Config::load(dirname(__FILE__).DIRECTORY_SEPARATOR.'routing.config', 'routing', 'Routing::transform');
        return var_export(Routing::routeFor($url),true);
    }

    public function testfileRouteFor() // blog
    {
        $this->runTestFile(dirname(__FILE__).'/routeFor.blog.testfile',array($this,'routeForCallback2'));
    }
    
    public function routeForCallback2($url)
    {
        Config::clear('routing');
        Config::load(dirname(__FILE__).DIRECTORY_SEPARATOR.'routes.blog.config', 'routing', 'Routing::transform');
        return var_export(Routing::routeFor($url),true);
    }
    
    
    /**
     * Test de Routing::linkFor()
     *
     */
    public function testfileLinkFor1()
    {
        $this->runTestFile(dirname(__FILE__).'/linkFor.testfile',array($this,'linkForCallback1'));
    }

    public function linkForCallback1($url)
    {
        Config::clear('routing');
        Config::load(dirname(__FILE__).DIRECTORY_SEPARATOR.'routing.config', 'routing', 'Routing::transform');
        $h=Routing::linkFor($url);
        if (strncmp($h, Runtime::$home, strlen(Runtime::$home))===0)
            $h='(home)' . substr($h, strlen(Runtime::$home)-1);
        if (strncmp($h, Runtime::$home, strlen(Runtime::$realHome))===0)
            $h='(realhome)' . substr($h, strlen(Runtime::$realHome)-1);
        return $h;
    }

    public function testfileLinkFor2() // blog
    {
        $this->runTestFile(dirname(__FILE__).'/linkFor.blog.testfile',array($this,'linkForCallback2'));
    }

    public function linkForCallback2($url)
    {
        Config::clear('routing');
        Config::load(dirname(__FILE__).DIRECTORY_SEPARATOR.'routing.blog.config', 'routing', 'Routing::transform');
        $h=Routing::linkFor($url);
        if (strncmp($h, Runtime::$home, strlen(Runtime::$home))===0)
            $h='(home)' . substr($h, strlen(Runtime::$home)-1);
        if (strncmp($h, Runtime::$home, strlen(Runtime::$realHome))===0)
            $h='(realhome)' . substr($h, strlen(Runtime::$realHome)-1);
        return $h;
    }
}
?>
