<?php

/**
 * Cette classe définit l'interface de base pour les modules de sécurité.
 *
 * Tous les modules de sécurité doivent hériter de cette classe de base.
 *
 * Remarque : un module de sécurité est aussi un module classique qui peut
 * avoir des actions. Cela permet de gérer la sécurité (hasAccess, etc.) au
 * même endroit que les actions (afficher le formulaire de connexion, access
 * denied, etc.)
 *
 * Cette classe de base implémente également un modèle de sécurité trivial.
 *
 * L'utilisateur :
 *
 * - est toujours anonyme
 *
 * - est toujours connecté (isConnected retourne toujours true)
 *
 * - a tous les droits (hasRight retourne toujours true)
 */
class BaseSecurity extends Module
{
    /**
     * @var string Les droits de l'utilisateur.
     * exemple : "AdminBdsp, EditWebs"
     *
     * (on peut utiliser n'importe quelle suite de caractères comme séparateur,
     * sauf les lettres, les chiffres, le souligné et le tiret)
     */
    public $rights;

    /**
     * Teste si l'utilisateur est connecté (authentifié)
     *
     * @return boolean true si l'utilisateur est connecté, false s'il
     * s'agit d'un visiteur anonyme
     */
    public function isConnected()
    {
        return true;
    }

    /**
     * Vérifie que l'utilisateur est connecté et l'envoie sur la page de
     * connexion si ce n'est pas le cas.
     */
    public function checkConnected()
    {
        if (! $this->isConnected())
            $this->logon();
    }

    /**
     * Force la connexion d'un utilisateur en le redirigeant vers
     * le formulaire de saisie du login/mot de passe
     */
    public function actionLogon()
    {
// TODO: à étudier
// redirection vers l'url de connexion ?
// affichage direct du formulaire indiqué dans la config puis die() ?
// appelle de l'action showLoginForm d'un module ?
//        $template=Config::get('user.loginform');  // plutôt l'url vers laquelle il faut aller ?
//        Routing::redirect()
    }

    /**
     * Teste si l'utilisateur dispose du droit unique indiqué.
     * Contrairement à {@link hasAccess()}, hasRight() ne permet de tester qu'un
     * droit unique et non pas une combinaison de droits séparés par des
     * virgules et des plus.
     *
     * @param string $right le droit à tester
     * @return boolean true si l'utilisateur dispose du droit demandé, false
     * sinon.
     */
    public function hasRight($right) // TODO: est-ce que ce code (ou une partie) ne devrait pas être plutôt dans User?
    {
        // Tout le monde dispose du droit 'default'
        if (strcasecmp($right,'default')==0) return true;

        // Le droit 'cli' n'est accordé que si php tourne en ligne de commande
        if (strcasecmp($right,'cli')==0) return php_sapi_name()=='cli';

        // Extrait le rôle et l'objet du droit (on coupe à la seconde majuscule)
        $i=strcspn($right,'ABCDEFGHIJKLMNOPQRSTUVWXYZ',1);
        $role=substr($right, 0, $i+1);
        $object=substr($right, $i+1);

        // Construit l'expression régulière utilisée
        $re="~\\b(?:(?:$right)";
        if ($role && $role!=$right) $re.="|(?:$role)";
        if ($object) $re.="|(?:$object)";
        $re.=")\\b~";

        // Retourne vrai si on trouve soit le droit, soit le rôle, soit l'objet dans les droits
        return preg_match($re, $this->rights) != 0;
    }

    /**
     * Teste si l'utilisateur dispose des droits indiqués.
     *
     * @param string $level le ou les droit(s) à tester
     * @return boolean true si l'utilisateur dispose du droit requis,
     * false sinon
     */
    public function hasAccess($rights) // TODO: est-ce que ce code ne devrait pas être plutôt dans User?
    {
        if (trim($rights)=='') return true;
        foreach(explode(',', $rights) as $right) // ensemble séparés par des ','
        {
        	foreach(explode('+',trim($right)) as $right) // ensemble séparé par des '+'
            	if (! $this->hasRight(trim($right)))
                    continue 2;
            return true;
        }
        return false;
    }

    /**
     * Vérifie que l'utilisateur dispose des droits indiqués et génère une
     * erreur 'access denied' sinon.
     *
     * @param string $level le droit à tester
     */
    public function checkAccess($rights)
    {
        if (! $this->hasAccess($rights))
            $this->accessDenied();
    }


    /**
     * Génère une erreur 'access denied'
     */
    public function accessDenied()
    {
    	throw new Exception('Accès refusé');
    }

    /**
     * Accorde des droits supplémentaire à l'utilisateur.
     *
     * @param string $rights les droits à accorder
     */
    public function grantAccess($rights)
    {
        if ($this->rights)
            $this->rights .= ',' . $rights;
        else
            $this->rights .= $rights;
    }

}
?>
