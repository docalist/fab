<?php

/**
 * Cette classe implémente un modèle de (non-)sécurité trivial.
 *
 * L'utilisateur :
 *
 * - est toujours anonyme
 *
 * - est toujours connecté (isConnected retourne toujours true)
 *
 * - a tous les droits (hasRight retourne toujours true)
 */
class NoSecurity extends BaseSecurity
{
    /**
     * Teste si l'utilisateur dispose du droit unique indiqué.
     *
     * @return true Retourne toujours true.
     */
    public function hasRight($right) // TODO: est-ce que ce code (ou une partie) ne devrait pas être plutôt dans User?
    {
        return true;
    }
}
?>
