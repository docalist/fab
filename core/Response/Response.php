<?php
/**
 * @package     fab
 * @subpackage  response
 * @author      Daniel Ménard <Daniel.Menard@ehesp.fr>
 * @version     SVN: $Id$
 */

/**
 * Représente la réponse générée par une action.
 *
 * Le résultat d'une action est toujours un objet <code>Response</code> : Fab appelle l'action
 * demandée en lui passant un objet {@link Request}, l'action s'exécute et collecte les données
 * requises puis prépare et retourne un objet de type <code>Response</code>.
 *
 * Fab se charge alors d'exécuter la réponse obtenue en appellant sa méthode {@link output()}.
 *
 * Il existe plusieurs type de réponses, chaque type étant représenté par un objet spécifique
 * descendant de la classe générique <code>Response</code> ({@link HtmlResponse},
 * {@link JsonResponse}, {@link RedirectResponse}, etc.)
 *
 * La classe <code>Response</code> permet de paramètrer complètement la sortie envoyée au client
 * (le navigateur en général) en définissant le {@link setStatus() code http}, les
 * {@link setHeader() entêtes http}, les {@link setCookie() cookies} et le contenu retourné.
 *
 * Le contenu de la réponse peut être {@link setContent() statique} ou
 * {@link setTemplate() dynamique} (c'est-à-dire définit en utilisant un {@link Template template}).
 *
 * Remarque :
 * <code>Response</code> dispose d'une interface fluide : les méthodes qui ne retournent pas de
 * valeur retournent <code>$this</code> pour permettre de chainer les appels. Pour simplifier
 * la création d'une requête, <code>Response</code> dispose d'une {@link create() méthode
 * statique de fabrication} permettant de créer une réponse d'un type donné.
 *
 * Exemple :
 * <code>
 * Response::create('html')->setStatus(200)->setContent('Hello World !')->output()
 * </code>
 *
 * @package     fab
 * @subpackage  response
 */
class Response
{
    /**
     * Version du protocole http utilisée pour la réponse (<code>'1.0'</code> ou <code>'1.1'</code>).
     *
     * @var string
     */
    protected $version = null;


    /**
     * Code http de la réponse tel que définit par {@link setStatus()}.
     *
     * Par défaut, la propriété est à <code>null</code> ce qui signifie qu'on n'envoie rien
     * nous-même et qu'on laisse l'action exécutée, php et/ou apache fournir (ou non) une valeur
     * par défaut.
     *
     * @var int
     */
    protected $status = null;


    /**
     * Entêtes http générés par la réponse tels que définits par {@link setHeader()}.
     *
     * @var array()
     */
    protected $headers = array();


    /**
     * {@link http://fr.wikipedia.org/wiki/Type_MIME Type mime} de la requête. Cette propriété est
     * destinée à être surchargée par les classes descendantes. Le constructeur ajoute
     * automatiquement un entête http 'Content-Type' contenant le type mime indiqué.
     *
     * Par défaut, la propriété est à <code>null</code> ce qui signifie qu'on n'envoie rien
     * nous-même et qu'on laisse l'action exécutée, php et/ou apache fournir (ou non) une valeur
     * par défaut.
     *
     * @var string
     */
    protected $contentType = null;


    /**
     * Charset utilisé pour définir l'entête Content-Type.
     *
     * Cette propriété est destinée à être surchargée par les classes descendantes. Son contenu
     * est automatiquement ajouté au contenu de la propriété {@link $contentType} pour former
     * l'entête http Content-Type.
     *
     * Par défaut, la propriété est à <code>null</code> ce qui signifie qu'on n'envoie rien
     * nous-même et qu'on laisse l'action exécutée, php et/ou apache fournir (ou non) une valeur
     * par défaut.
     *
     * @var string
     */
    protected $charset = null;


    /**
     * Cookies générés par la réponse (cf {@link cookie()}).
     *
     * Chaque élément est un tableau contenant les arguments passés en paramètres lors de l'appel à
     * la méthode {@link setCookie()}.
     *
     * @var array
     */
    protected $cookies = array();


    /**
     * Contenu statique de la réponse tel que définit par {@link setContent()}.
     *
     * @var mixed
     */
    protected $content = null;


    /**
     * Path du template à utiliser pour la réponse tel que définit par {@link setTemplate()}.
     *
     * @var string
     */
    protected $template = null;


    /**
     * Données du template à utiliser pour la réponse telles que définies par {@link setTemplate()}.
     *
     * @var string
     */
    protected $templateData = null;


    /**
     * Liste des codes de statut http valides et texte associé.
     *
     * Source : {@link http://fr.wikipedia.org/wiki/Liste_des_codes_HTTP}.
     *
     * @var array(string)
     */
    static protected $httpStatus = array
    (
        // 1xx : Information
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',          // WebDav

        // 2xx : Succès
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-Status',        // WebDav
        210 => 'Content Different',   // WebDav

        // 3xx : Redirection
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Moved Temporarily',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        307 => 'Temporary Redirect',

        // 4xx : Erreur du client
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Requested Range Unsatisfiable',
        417 => 'Expectation Failed',
     // 418 => "I'm a teapot"             // Blague de 1er avril : "Coffee Pot Transfer Protocol"
        422 => 'Unprocessable entity',    // WebDav
        423 => 'Locked',                  // WebDav
        424 => 'Method failure',          // WebDav
        425 => 'Unordered Collection',    // WebDav
     // 426 => 'Upgrade required',        // Spécifique : TLS
     // 449 => 'Retry with',              // Spécifique Microsoft
     // 450 => 'Blocked by Windows',      // spécifique Windows : contrôle parental

        // 5xx : Erreurs du serveur
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway or Proxy Error',
        503 => 'Service Unavailable',
        504 => 'Gateway Time-out',
        505 => 'HTTP Version not supported',
        507 => 'Insufficient storage', // WebDav
        509 => 'Bandwidth Limit Exceeded', // Non officiel : dépassement de quota
    );


    /**
     * Crée un objet Response.
     *
     * Si un type mime {@link $contentType a été défini}, ajoute une entête http Content-Type à la
     * réponse.
     */
    public function __construct()
    {
        if ($this->contentType)
        {
            if ($this->charset)
                $content = $this->contentType . ';charset=' . $this->charset;
            else
                $content = $this->contentType;

            $this->setHeader('Content-Type', $content);
        }
    }


    /**
     * Retourne le type de la réponse (Html, Text, Json, Redirect, etc.)
     *
     * Le type de la réponse est déterminé à partir du nom de la classe : par convention,
     * c'est tout ce qui précède le mot 'Response' dans le nom de la classe.
     *
     * Exemple :
     * Pour un objet de type {@link JsonResponse}, <code>getType()</code>retourne
     * <code>'Json'</code>.
     *
     * @return string
     */
    public function getType()
    {
        return strtok(get_class($this), __CLASS__);
    }


    /**
     * Factory : crée un objet Response du type indiqué.
     *
     * @param string $type le type de réponse à créer (Html, Text, Json, Redirect, etc.)
     * Si <code>$type</code> n'est pas indiqué, une réponse de type générique est retournée.
     *
     * @param mixed $args ... les arguments à passer au constructeur de la réponse.
     *
     * @return Response $this
     */
    public static function create($type='', $args=null)
    {
        $args = func_get_args();
        array_shift($args);

        $class = new ReflectionClass(ucfirst(strtolower($type)) . 'Response');
        return $class->newInstanceArgs($args);
    }


    /**
     * Définit le template et les données à utiliser pour générer le contenu de la réponse.
     *
     * Si vous définissez à la fois un contenu statique (avec {@link setContent()}) et un contenu
     * dynamique (avec <code>setTemplate()</code>) la réponse générée contiendra le contenu statique
     * puis le contenu du template.
     *
     * @param object $context le contexte dans lequel le template sera exécuté : lors de l'exécution
     * du template, l'objet passé en paramètre sera accessible en utilisant <code>$this</code>.
     *
     * @param string $path le chemin (absolu ou relatif) du template à utiliser.
     *
     * @param mixed $data ... une liste variable d'arguments représentant les données du template
     *
     * @return Response $this
     */
    public function setTemplate($context, $path, $data=null)
    {
        // Vérifie $context
        if (! is_object($context))
            throw new InvalidArgumentException('Contexte invalide, objet attendu');

        // Stocke le chemin absolu du template
        if (Utils::isRelativePath($path) &&  false === $path=Utils::searchFile($sav=$path))
            throw new Exception("Impossible de trouver le template $sav.");
        $this->template = $path;

        // Stocke les données du template
        $this->templateData = func_get_args();
        array_splice($this->templateData, 0, 2, array(array('this'=>$context)));

        return $this;
    }


    /**
     * Définit le contenu statique à utiliser pour générer la réponse.
     *
     * Si vous définissez à la fois un contenu statique (avec <code>setContent()</code>) et un
     * contenu dynamique (avec {@link template()}) la réponse générée contiendra le contenu
     * statique puis le contenu du template.
     *
     * @param mixed $content
     *
     * @return Response $this
     */
    public function setContent($content = null)
    {
        $this->content = $content;
        return $this;
    }


    /**
     * Ajoute un contenu avant le contenu existant.
     *
     * @param mixed $content
     *
     * @return Response $this
     */
    public function prependContent($content)
    {
        $this->content = $content . $this->content;
        return $this;
    }


    /**
     * Ajoute un contenu après le contenu existant.
     *
     * @param mixed $content
     *
     * @return Response $this
     */
    public function appendContent($content)
    {
        $this->content .= $content;
        return $this;
    }


    /**
     * Retourne une description de l'objet en cours.
     *
     * @return string
     */
    public function __toString()
    {
        ob_start();

        // Class type
        printf("<pre>%s :\n{\n", get_class($this));

        // Statut http
        if ($this->status)
        {
            if (is_null($this->version)) $this->setVersion();
            printf("    HTTP/%s %s %s\n", $this->version, $this->status, self::$httpStatus[$this->status]);
        }

        // Entêtes http
        foreach ($this->headers as $name => $value)
            foreach((array)$value as $item)
                printf("    %s: %s\n", $name, $item);

        // Cookies
        foreach ($this->getCookies() as $cookie)
        {
            $name = isset($cookie['name']) ? $cookie['name'] : 'null';
            unset($cookie['name']);
            $value = isset($cookie['value']) ? $cookie['value'] : 'null';
            unset($cookie['value']);
            $properties = '';
            if ($cookie)
            {
                foreach($cookie as $k=>$v)
                    $properties[] = $k . '=' . $v;
                $properties = '(' . implode(',', $properties) . ')';
            }

            printf("    Set-Cookie: %s=%s%s\n", $name, $value, $properties);
        }

        print("\n");
        echo htmlspecialchars($this->render());

        printf("\n}</pre>");

        return ob_get_clean();
    }


    /**
     * Définit le code http de la réponse.
     *
     * @param int $status un {@link http://fr.wikipedia.org/wiki/Liste_des_codes_HTTP code http valide}.
     *
     * @return Response $this
     *
     * @throw InvalidArgumentException si le code indiqué n'est pas valide.
     */
    public function setStatus($status)
    {
        $status = (int) $status;
        if (! isset(self::$httpStatus))
            throw new InvalidArgumentException('Statut http invalide : ' . $status);
        $this->status = $status;
        return $this;
    }


    /**
     * Retourne le code http de la réponse.
     *
     * @return int le {@link http://fr.wikipedia.org/wiki/Liste_des_codes_HTTP code http}
     * actuellement définit pour la réponse.
     */
    public function getStatus()
    {
        return $this->status;
    }


    /**
     * Définit la version du protocole http utilisée pour la réponse.
     *
     * @param null|string $version la version du protocole http à utiliser. Si vous indiquez une
     * chaine de caractères, seules les valeurs '1.0' et '1.1' sont autorisées comme argument.
     *
     * Si vous n'indiquez aucun argument ou si $version vaut null, c'est la version actuellement
     * définie dans $_SERVER['SERVER_PROTOCOL'] qui est utilisée.
     *
     * @return Response $this
     *
     * @throw InvalidArgumentException si la version indiquée n'est pas valide.
     */
    public function setVersion($version = null)
    {
        if (is_null($version))
        {
            strtok($_SERVER['SERVER_PROTOCOL'],'/');
            $version = strtok('/');
        }

        if ($version !== '1.0' && $version !== '1.1')
            throw new InvalidArgumentException('Version http invalide : ' . $version);

        $this->version = $version;

        return $this;
    }


    /**
     * Retourne la version du protocole http qui sera utilisée pour générer la réponse.
     *
     * @return string la version du protocole http utilisée ('1.0' ou '1.1').
     */
    public function getVersion()
    {
        return $this->status;
    }


    /**
     * Ajoute ou supprime un entête http de la réponse.
     *
     * La méthode fonctionne comme la fonction {@link http://php.net/header header()} de php.
     *
     * @param string $name le nom de l'entête à ajouter.
     *
     * @param string|null $value la valeur de l'entête. Si $value vaut null, l'entête est supprimé
     * de la réponse.
     *
     * @param bool $replace indique ce qu'il faut faire si l'entête à ajouter existe déjà dans la
     * réponse. Si $replace vaut true, l'entête existant est ecrasé, sinon, la valeur indiquée est
     * ajoutée à l'entête existant.
     *
     * @return Response $this
     */
    public function setHeader($name, $value = null, $replace = true)
    {
        // Normalise le nom de l'entête
        $name = $this->headerName($name);

        // Supprime l'entête si $value est null
        if (is_null($value))
        {
            unset($this->headers[$name]);
            return $this;
        }

        // Stocke l'entête si replace est à true ou si l'entête n'existe pas déjà
        if ($replace || ! isset($this->headers[$name]))
        {
            $this->headers[$name] = $value;
            return $this;
        }

        // Ajout d'une nouvelle valeur à un entête déjà défini
        if (is_array($this->headers[$name]))
            $this->headers[$name][] = $value;
        else
            $this->headers[$name] = array($this->headers[$name], $value);

        return $this;
    }


    /**
     * Retourne la valeur actuelle de l'entête http indiqué ou <code>false</code> si l'entête n'a
     * pas été défini.
     *
     * @param string $name
     * @return string|false
     */
    public function getHeader($name)
    {
        $name = $this->headerName($name);
        if (isset($this->headers[$name]))
            return $this->headers[$name];
        return false;
    }


    /**
     * Indique si la réponse contient l'entête http indiqué.
     *
     * @param string $name nom de l'entête http à tester.
     * @return bool <code>true</code> si l'entête est définit dans la réponse avec une valeur
     * non nulle, <code>false</code> sinon.
     */
    public function hasHeader($name)
    {
        return isset($this->headers[$this->headerName($name)]);
    }


    /**
     * Retourne tous les entêtes http actuellement définits pour la réponse.
     *
     * @return array un tableau contenant tous les entêtes. Les clés du tableaux correspondent aux
     * noms des entêtes ajoutés. Les valeurs correspondent aux valeurs passées en paramètre lors
     * de l'appel à la méthode {@link header()}. Si un même entête a été définit plusieurs fois,
     * dans ce cas la valeur correspondante dans le tableau sera un tableau contenant les
     * différentes valeurs.
     */
    public function getHeaders()
    {
        return $this->headers;
    }


    /**
     * Efface tous les entêtes http actuellement définits pour la requête.
     *
     * @return Response $this
     */
    public function clearHeaders()
    {
        $this->headers = array();
        return $this;
    }


    /**
     * Valide et redresse le nom d'un entête http (exemple : 'content type' -> 'Content-Type').
     *
     * @param string $name
     * @return string
     */
    protected function headerName($name)
    {
        return strtr(ucwords(strtolower(strtr(trim($name), '-_', '  '))), ' ', '-');
    }


    /**
     * Définit un cookie.
     *
     * La méthode fonctionne comme la fonction {@link http://php.net/setrawcookie()} de php.
     *
     * @param string $name Le nom du cookie.
     * @param string $value La valeur du cookie
     * @param timestamp $expire La durée de vie du cookie (0 = à la fin de la session du navigateur)
     * @param string $path Le chemin sur le serveur sur lequel le cookie sera disponible.
     * @param string $domain Le domaine où le cookie est disponible
     * @param bool $secure Lorsque $secure vaut <code>true</code>, le cookie n'est définit que
     * pour les connexions https.
     * @param bool $httpOnly Lorsque ce paramètre vaut <code>true</code>, le cookie ne sera
     * accessible que par le protocole HTTP
     *
     * @return Response $this
     */
    public function setCookie($name, $value = null, $expire = 0, $path = null, $domain = null, $secure = false, $httpOnly = false)
    {
        $this->cookies[$name] = func_get_args();
        return $this;
    }


    /**
     * Retourne le cookie dont le nom est passé en paramètre ou <code>false</code> si le cookie
     * indiqué n'existe pas.
     *
     * @param string $name le nom du cookie à retourner.
     *
     * @return array|false un tableau contenant les paramètres du cookie ou <code>false</code> si
     * le cookie demandé n'existe pas
     *
     * Le tableau retourné peut avoir les clés suivantes :
     * - <code>name</code> : le nom du cookie.
     * - <code>value</code> : la valeur du cookie.
     * - <code>expire</code> : la durée de vie du cookie.
     * - <code>path</code> : le chemin du cookie.
     * - <code>domain</code> : le domaine du cookie.
     * - <code>secure</code> : flag indiquant un cookie https.
     * - <code>httpOnly</code> : flag indiquant un cookie accessible uniquement par HTTP.
     *
     * Important :
     * Seuls les arguments effectivements passés en paramètre lors de l'appel à {@link setCookie()}
     * figurent dans le tableau retourné.
     */
    public function getCookie($name)
    {
        static $keys = array('name', 'value', 'expire', 'path', 'domain', 'secure', 'httpOnly');

        if (! isset($this->cookies[$name]))
            return false;

        return array_combine
        (
            array_slice($keys, 0, count($this->cookies[$name])),
            $this->cookies[$name]
        );
    }


    /**
     * Retourne tous les cookies actuellement définits dans la requête.
     *
     * @return array retourne un tableau dont les clés correspondent au nom des cookies et dont
     * la valeur est un tableau tel que retourné par {@link getCookie()}.
     *
     * Remarque :
     * Si aucun cookie n'est définit dans la requête, la méthdoe retourne un tableau
     * vide.
     */
    public function getCookies()
    {
        $result = array();
        foreach($this->cookies as $name=>$cookie)
            $result[$name] = $this->getCookie($name);
        return $result;
    }


    /**
     * Efface tous les cookies actuellement définits pour la requête.
     *
     * @return Response $this
     */
    public function clearCookies()
    {
        $this->cookies = array();
        return $this;
    }



    /**
     * Génère les entêtes http de la réponse.
     *
     * Si les entêtes ont déjà été envoyés ({@link http://php.net/headers_sent() headers_sent()}
     * retourne true), la méthode ne fait rien et aucun warning n'est émis.
     *
     * @return Response $this
     */
    protected function outputHeaders()
    {
        // Si les entêtes ont déjà été envoyés, on ne peut pas envoyer les notres
        if (headers_sent()) return $this;

        // Statut http
        if ($this->status)
        {
            if (is_null($this->version)) $this->setVersion();
            header(sprintf('HTTP/%s %s %s', $this->version, $this->status, self::$httpStatus[$this->status]));
        }

        // Entêtes http
        foreach ($this->headers as $name => $value)
            foreach((array)$value as $item)
                header("$name: $item");

        // Cookies
        foreach ($this->cookies as $cookie)
            call_user_func_array('setrawcookie', $cookie);

        return $this;
    }


    /**
     * Génère le contenu de la réponse.
     *
     * @return Response $this
     */
    public function outputContent()
    {
        // Contenu statique
        echo $this->content;

        // Template
        if ($this->template)
            Template::runInternal($this->template, $this->templateData);

        // Done
        return $this;
    }


    /**
     * Génère la répoonse complète ({@link outputHeaders() entêtes} et
     * {@link outputContent() contenu}).
     *
     * @return Response $this
     */
    public function output()
    {
        $this->outputHeaders();
        $this->outputContent();
        return $this;
    }


    /**
     * Génère le contenu de la réponse et le retourne sous forme de chaine de caractères.
     *
     * Remarque : <code>render()</code> ne génère que la réponse. Les entêtes http, les cookies et
     * le layout éventuel ne sont ni retournés, ni envoyés au navigateur.
     *
     * @return string
     */
    public function render()
    {
        ob_start();
        $this->outputContent();
        return ob_get_clean();
    }


    /**
     * Indique si la réponse peut avoir un layout.
     *
     * @return bool retourne <code>true</code> si la réponse en cours est une instance de
     * {@link LayoutResponse} ou de ses descendants, <code>false</code> sinon.
     */
    public function hasLayout()
    {
        return $this instanceof LayoutResponse;
    }


    /**
     * Méthode utilitaire permettant de générer une exception de type BadMethodCallException si
     * l'appel d'une méthode n'est pas autorisé.
     *
     * @param string|null $message texte additionnel à ajouter au mesage de l'exception.
     */
    protected function illegal($message=null)
    {
        if ($message) $message = ' : ' . $message;
        throw new BadMethodCallException('Opération illégale pour une réponse de type '. get_class($this) . $message);
    }
}