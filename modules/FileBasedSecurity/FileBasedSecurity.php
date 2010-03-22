<?php
/**
 * @package     fab
 * @subpackage  security
 * @author      Daniel Ménard <Daniel.Menard@ehesp.fr>
 * @version     SVN: $Id$
 */


/**
 * Module de sécurité basé sur un fichier texte contenant la liste des
 * utilisateurs du site.
 *
 * Ce module utilise le fichier <code>FileBasedSecurity.config</code> pour toutes ses options de
 * configuration : le fichier de Fab fournit des valeurs par défaut et celui qui est définit dans
 * l'application permet de changer ces valeurs par défaut.
 *
 * Exemple de fichier FileBasedSecurity.config :
 * <code>
 *     <file>
 *         <!--
 *             Path du fichier contenant la liste des utilisateurs.
 *
 *             Il peut s'agit d'un chemin "absolu" (commençant par un slash). Dans ce cas,
 *             le fichier est recherché par rapport à la racine de l'application. Si c'est un
 *             chemin relatif, il est recherché dans le répertoire /data de l'application
 *             (exemple : users/list.txt -> /data/users/list.txt).
 *
 *             L'application doit explicitement créer un fichier FileBasedSecurity.config
 *             dans son répertoire /config et indiquer le path du fichier utilisé.
 *          -->
 *         <path>/data/users.txt</path>
 *
 *         <!--
 *             Paramètres du fichier CSV : séparateur de champs (virgule par défault),
 *             caractère de délimitation (guillemet par défaut).
 *             Consulter la fonction {@link http://php.net/fgetcsv} pour plus d'infos.
 *         -->
 *         <delimiter>,</delimiter>
 *         <enclosure>"</enclosure>
 *     </file>
 *
 *     <!--
 *         Paramètres du cookie.
 *
 *         Consulter {@link http://php.net/setcookie} pour plus d'informations sur les différentes options.
 *     -->
 *     <cookie>
 *          <!-- nom du cookie généré ('user' par défaut) -->
 *         <name>user</name>
 *
 *          <!--
 *             Durée de vie, en secondes du cookie généré (10 jours par défaut).
 *             Si l'utilisateur coche la case "se souvenir de moi", le cookie généré sera valide
 *             pour la durée indiquée ci-dessous, sinon, lifetime est ignoré et le cookie est
 *             effacé dès que l'utilisateur ferme son navigateur (et au maximum 24h).
 *         -->
 *         <lifetime>864000</lifetime>
 *
 *          <!-- path du cookie ('/' par défaut) -->
 *         <path>/</path>
 *
 *          <!-- domaine du cookie (null par défaut) -->
 *         <domain />
 *
 *          <!-- http-only (true par défaut) -->
 *         <http-only>true</http-only>
 *     </cookie>
 * </code>
 *
 * La liste des utilisateurs du site est gérée dans un fichier texte au format
 * {@link http://fr.wikipedia.org/wiki/Comma-separated_values CSV} avec une ligne d'entête
 * indiquant le nom des différentes colonnes.
 *
 * Le chemin du fichier (<code><file.path></code>) et le format utilisé sont indiqués dans le
 * fichier de configuration (séparateur de colonnes, caractères d'échappement, etc.)
 *
 * Le fichier CSV DOIT contenir les colonnes "login", "password" et "rights" et il PEUT contenir
 * des colonnes supplémentaires (nom, prénom, e-mail, etc.) qui seront chargées automatiquement
 * et deviendront des propriétés publiques de l'utilisateur en cours
 * (exemple d'utilisation : <code>User::get('prénom')</code>).
 *
 * Remarques :
 * - L'ordre des colonnes dans le fichier n'a pas d'importance.
 * - Les espaces de début et de fin de colonne sont ignorés.
 * - Les lignes vides sont ignorées.
 *
 * La colonne "rights" doit indiquer les permissions dont dispose l'utilisateur sous la forme
 * d'une liste de droits séparés par une virgule (utiliser des guillemets si la virgule est
 * utilisée comme séparateur de colonnes) : par exemple <code>"AdminBase, EditContacts"</code>.
 *
 * Exemple :
 * <code>
 * login, password, rights, firstname, surname, mail
 * dmenard, abcd123, AdminFab, Daniel, Ménard, daniel.menard@ehesp.fr
 * ferron, efgh456, "AdminBase,AdminEmploi", Séverine, Ferron, severine.ferron@ehesp.fr
 * test, "ijkl 789",,"compte de test, aucun droits",,
 * </code>
 *
 * Pour se connecter, l'utilisateur appelle l'action {@link actionLogin() Login} et saisit son
 * identifiant (login) et son mot de passe. Il peut aussi activer l'option "se souvenir de moi"
 * pour éviter d'avoir à se reconnecter à chacune de ses visites.
 *
 * Lorsqu'il valide ses informations, le login et le mot de passe sont vérifiés : ils doivent
 * correspondre à un utilisateur existant dans le fichier et la comparaison tient compte de la
 * casse des caractères. Si les codes sont invalides, le formulaire est réaffiché avec un message
 * d'erreur. Sinon, un cookie est généré et l'utilisateur est connecté au site.
 *
 * Les paramètres du cookie généré sont définis dans le fichier de configuration (nom du cookie,
 * path, domaine, etc.)
 *
 * La valeur du cookie est cryptée avec un double mécanisme d'encodage utilisant les fonctions
 * {@link http://php.net/hash_hmac hash_hmac()} et {@link http://php.net/md5 md5()} et utiliser
 * une clé (salt) qui dépend à la fois du login et du mot de passe de l'utilisateur.
 *
 * Par défaut, le cookie généré n'est valable que pour la durée de la visite de l'utilisateur
 * (le cookie est effacé dès que le navigateur est fermé) avec une durée de vie maximale de 24
 * heures (si le navigateur reste ouvert plus de 24 heures, l'utilisateur devra se reconnecter).
 *
 * Si l'utilisateur a coché l'option "Se souvenir de moi", le cookie généré est valable tant que
 * la durée indiquée dans la clé <code><cookie.lifetime></code> n'est pas écoulée.
 *
 * Enfin, le module dispose d'une action {@link actionLogout() Logout} qui permet à l'utilisateur
 * de se déconnecter et d'effacer de son navigateur le cookie généré lors de la connexion.
 *
 * Utilisation du module :
 *
 * Pour utiliser le module, commencez par créer dans le répertoire <code>/config</code> de
 * l'application un fichier <code>FileBasedSecurity.config</code> et un fichier CSV contenant
 * la liste des utilisateurs du site (cf modèles ci-dessus).
 *
 * Il suffit ensuite d'indiquer à Fab que c'est le module FileBasedScurity qui est utilisé pour
 * gérer la sécurité du site en ajoutant le code suivant dans le fichier
 * <code>general.config</code> de l'application :
 *
 * <code>
 * <security>
 *     <handler>FileBasedSecurity</handler>
 * </security>
 * </code>
 *
 * @package     fab
 * @subpackage  security
 * @author      Daniel Ménard <Daniel.Menard@ehesp.fr>
 */
class FileBasedSecurity extends BaseSecurity
{
    /**
     * Valide le login et le mot de passe saisis par l'utilisateur dans le formulaire généré
     * par {@link actionLogin()}, connecte l'utilisateur si ceux-ci sont corrects puis le redirige
     * vers une autre page.
     *
     * On fait ce travail dans preExecute() plutôt que dans {@link actionLogin()} car pour
     * faire la redirection, on ne veut avoir ni thème, ni layout (on pourrait sinon obtenir
     * une erreur "headers already sent").
     *
     * @return bool <code>true</code> si l'utilisateur a été connecté, <code>false</code> sinon.
     */
    public function preExecute()
    {
        if ($this->method === 'actionLogin')
        {
            // Connecte l'utilisateur si les données du formulaire sont correctes
            if
            (
                Utils::isPost()                             // c'est une requête POST
                && isset($_COOKIE['test'])                  // le navigateur supporte les cookies
                && isset($_POST['login'])                   // on a un login
                && isset($_POST['password'])                // on a un mot de passe
                && $user = $this->getUser($_POST['login'], $_POST['password']) // ils sont corrects
            )
            {
                // Efface le cookie de test (cf plus bas)
                setcookie('test', 1);

                // Stocke le cookie de connexion
                $this->setCookie($user);

                // Détermine l'url de la page vers laquelle on va rediriger l'utilisateur
                if (isset($_POST['redirect']))
                    $url = Routing::linkFor($_POST['redirect'], true);
                else
                    $url = Routing::linkFor('/', true);

                // Redirige l'utilisateur
                Runtime::redirect($url);

                // Indique à fab qu'on a fait le boulot et qu'il ne faut pas appeller actionLogin()
                return true;
            }

            // Sinon, génère un cookie de test pour vérifier que le navigateur supporte les cookies
            setcookie('test', 1);

            // Laisse actionLogin() afficher le formulaire et les erreurs éventuelles
        }
    }


    /**
     * Affiche un formulaire permettant à l'utilisateur de saisir ses codes d'accès (identifiant
     * et mot de passe).
     *
     * La méthode utilise le template <code>login.html</code>.
     *
     * @param string $login identifiant de l'utilisateur.
     * @param string $password mot de passe de l'utilisateur.
     * @param bool $remember 1 si l'utilisateur a coché l'option "se souvenir de moi", 0 sinon.
     * @param string $redirect url de la page vers laquelle sera redirigé l'utilisateur s'il
     * parvient à se connecter.
     */
    public function actionLogin($login='', $password='', $remember=0, $redirect='/')
    {
        // Si on nous a transmis des paramètres, on vérifie le login et le mot de passe.
        // Le travail a déjà été fait en partie dans {@link preExecute()}, on refait une partie
        // du boulot ici juste pour pouvoir afficher un message d'erreur correct à l'utilisateur.
        $error = false;
        if (Utils::isPost())
        {
            // Le navigateur ne support pas les cookies
            if (! isset($_COOKIE['test']))
                $error = 'Votre navigateur ne supporte pas les cookies. Vous ne pourrez pas vous connecter au site.';

            // Pas de login
            elseif ($login == '')
                $error = "Vous n'avez pas indiqué l'identifiant.";

            // Pas de password
            elseif($password == '')
                $error = "Vous n'avez pas indiqué le mot de passe.";

            // On a les deux
            else
                $error = 'Identifiant ou mot de passe invalides.';
        }

        // Affiche le formulaire de connexion au site
        Template::run
        (
            'login.html',
            array
            (
                'login'    => $login,
                'password' => $password,
                'remember' => $remember,
                'redirect' => $redirect,
                'error'    => $error,
            )
        );
    }


    /**
     * Déconnecte l'utilisateur en cours et le redirige vers l'url passée en paramètre.
     *
     * @param string $redirect url de la page vers laquelle sera redirigé l'utilisateur après avoir
     * été déconnecté.
     */
    public function actionLogout($redirect='/')
    {
        $this->clearCookie();
        Runtime::redirect($redirect);
    }


    /**
     * Charge la liste des utilisateurs du site à partir du fichier.
     *
     * La méthode charge le fichier indiqué dans la clé <code><security.file></code> du fichier
     * de configuration et construit un tableau indexé par login d'utilisateur.
     *
     * Le path du fichier indiqué est soit un chemin "absolu" (commençant par un slash) par rapport
     * à la racine de l'application (par exemple <code>/private/users.txt</code>), soit un chemin
     * relatif au répertoire <code>/data</code> de l'application
     * (exemple : <code>users/list.txt -> /data/users/list.txt</code>).
     *
     * @return array un tableau contenant tous les utilisateurs définits dans le fichier.
     */
    private function loadUsers()
    {
        // Vérifie qu'un fichier de login/mot de passe a été fourni
        if (is_null($path=$this->config['file']['path']))
            throw new Exception(__CLASS__ . ' : aucun fichier d\'utilisateurs n\'a été indiqué dans la clé security.file du fichier de configuration.');

        // Détermine le path exact du fichier
        if (Utils::isRelativePath($path))
            $path=Utils::makePath(Runtime::$root, 'data', $path);
        else
            $path=Utils::makePath(Runtime::$root, $path);

        // Vérifie que le fichier indiqué existe
        if (! file_exists($path))
            throw new Exception(__CLASS__ . ' : impossible de trouver le fichier d\'utilisateurs indiqué dans la clé security.file du fichier de configuration.');

        // Récupère les paramètres du fichier
        $delimiter = $this->config['file']['delimiter'];
        $enclosure = $this->config['file']['enclosure'];

        // Ouvre le fichier
        if (!is_resource($file = fopen($path, 'r')))
            throw new Exception(__CLASS__ . ' : impossible de charger le fichier d\'utilisateurs indiqué dans la clé security.file du fichier de configuration.');

        // Charge la ligne d'entête, ignore les espaces et vérifie qu'elle est correcte
        $header = fgetcsv($file, 1000, $delimiter, $enclosure);
        $header = array_map('trim', $header);
        if (! in_array('login', $header) || ! in_array('password', $header))
            throw new Exception(__CLASS__ . ' : le fichier utilisateurs est incorrect, il doit contenir les colonnes "login", "password" et "rights".');

        // Charge tous les utilisateurs
        $users = array();
        $line=1;
        while (false !== $user = fgetcsv($file, 1000, $delimiter, $enclosure))
        {
            // Passe les lignes vides
            ++$line;
            if (is_null($user[0])) continue;

            //
            if (count($user) != count($header))
                throw new Exception(__CLASS__ . ' : erreur ligne ' . $line . ' du fichier d\'utilisateurs, nombre de colonnes incorrect.');

            // Supprime les espaces de début et de fin de colonne
            $user = array_map('trim', $user);

            // Crée un tableau indexé par nom de champ
            $user = array_combine($header, $user);
            $users[$user['login']] = (object) $user;

        }

        // Ferme le fichier
        fclose($file);

        // Ok, terminé
        return $users;
    }


    /**
     * Vérifie que l'identifiant et le mot de passe passés en paramètre sont valides
     * et retourne l'objet User correspondant.
     *
     * @param string $login l'identifiant de l'utilisateur (login)
     * @param string $password le mot de passe de l'utilisateur.
     * @return object un objet contenant les propriétés de l'utilisateur.
     */
    private function getUser($login, $password)
    {
        // Charge la liste des utilisateurs
        $users = $this->loadUsers();

        // Vérifie que le login indiqué existe
        if (! isset($users[$login]))
            return false;

        // Charge l'utilisateur
        $user = $users[$login];

        // Vérifie que le mot de passe est correct
        if ($user->password !== $password)
            return false;

        // Retourne l'utilisateur
        return $user;
    }


    /**
     * Définit le cookie utilisateur en cryptant sa valeur.
     *
     * @param object $user un objet utilisateur tel que retourné par {@link getUser()}.
     * @param bool $remember <code>true</code> pour générer un cookie persistant, <code>false</code>
     * pour générer un cookie de session.
     */
    private function setCookie($user, $remember=false)
    {
        $cookie = $this->config['cookie'];

        // Calcule la date d'expiration du cookie
        if ($remember)
        {
            // expire après la durée indiquée dans la config
            $validUntil = $expire = time() + $cookie['lifetime'];
        }
        else
        {
            // cookie valide jusqu'à ce que le navigateur doit fermé
            $expire = 0;

            // mais pas si le navigateur reste ouvert plus de 24h
            $validUntil = time() + 24*60*60;
        }

        // Calcule et crypte la valeur du cookie
        $key = md5($user->login . $user->password . '|' . $validUntil);
        $hash = hash_hmac('md5', $user->login . '|' . $validUntil, $key);
        $value = sprintf('%s|%d|%s', $user->login, $validUntil, $hash);

        // Le hash présent dans le cookie résulte d'une double encodage.
        // D'abord, on calcule une clé de cryptage basée sur le login de l'utilisateur
        // et la date d'expiration du cookie, ensuite on encode à nouveau le login
        // et la date d'expiration en utilisant cette clé.

        // Crée le cookie
        setcookie
        (
            $cookie['name'],
            $value,
            $expire,
            $cookie['path'],
            $cookie['domain'],
            false,
            $cookie['http-only']
        );
        $_COOKIE[$cookie['name']]=$value;
    }


    /**
     * Efface le cookie utilisateur généré par un appel précédent à
     * {@link setCookie()}.
     */
    private function clearCookie()
    {
        $cookie = $this->config['cookie'];

        // Efface le cookie
        setcookie
        (
            $cookie['name'],
            false,
            0,
            $cookie['path'],
            $cookie['domain'],
            false,
            $cookie['http-only']
        );
        unset($_COOKIE[$cookie['name']]);
    }


    /**
     * Teste si l'utilisateur dispose d'un cookie de connexion valide et, si c'est le cas,
     * le connecte au site.
     *
     * @return bool <code>true</code> si l'utilisateur a un cookie valide et qu'il a été connecté,
     * <false> sinon.
     */
    private function checkCookie()
    {
        // Récupère le nom du cookie
        $name = $this->config['cookie']['name'];
        if (isset($_COOKIE[$name]))
        {
            $cookie = $_COOKIE[$name];

            // Récupère le contenu du cookie : login|date d'expiration|hash
            $parts = explode('|', $_COOKIE[$name]);
            if ( count($parts) !== 3 ) return;
            list($login, $validUntil, $hash) = $parts;

            // Si le cookie a expiré, l'utilisateur n'est plus connecté
            if ( $validUntil < time() ) return;

            // Charge la liste des utilisateurs si ce n'est pas déjà fait
            $users = $this->loadUsers();

            // Vérifie que l'utilisateur indiqué existe
            if (! isset($users[$login])) return;
            $user = $users[$login];

            // Vérifie que le cookie n'a pas été trafiqué.
            // Le hash présent dans le cookie résulte d'une double encodage.
            // D'abord, on calcule une clé de cryptage basée sur le login de l'utilisateur
            // et la date d'expiration du cookie, ensuite on encode à nouveau le login
            // et la date d'expiration en utilisant cette clé.
            $key = md5($user->login . $user->password . '|' . $validUntil);
            $correctHash = hash_hmac('md5', $user->login . '|' . $validUntil, $key);

            // Si le hash obtenu est différent du hash présent dans le cookie, abandon.
            if ($hash != $correctHash ) return;

            foreach ($user as $property => $value)
                $this->$property = $value;

            return true;
        }
    }

    /**
     * Indique si l'utilisateur en cours est connecté (authentifié)
     *
     * @return boolean true si l'utilisateur est connecté, false s'il
     * s'agit d'un visiteur anonyme
     */
    public function isConnected()
    {
        return isset($this->login);
    }


    /**
     * Essaie de connecter l'utilisateur au site si celui-ci a envoyé
     * un cookie de connexion valide.
     *
     * La méthode <code>initialize()</code> est appellée automatiquement par la méthode
     * {@link Module::loadModule()} une fois que la configuration du module a été chargée.
     */
    public function initialize()
    {
        parent::initialize();
        $this->checkCookie();
    }
}