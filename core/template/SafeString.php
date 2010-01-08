<?php

/**
 * Ce fichier contient le code utilisé pour greffer un système de protection automatique
 * contre les failles XSS au gestionnaire de templates.
 *
 * @author dmenard
 *
 */

/**
 * Classe utilisée pour marquer une chaine comme étant "sûre".
 *
 */
class SafeString
{
    private $string = null;

    public function __construct($string)
    {
        $this->string = (string) $string;
    }

    public function __toString()
    {
        return $this->string;
    }

    public static function create($string)
    {
        return new self($string);
    }
}

/**
 * Facilité pour créer une SafeString
 * @param $string
 */
function SafeString($string)
{
    return new self($string);
}

/**
 * Fonction utilisée pour filtrer des données
 */
/* public static */ function filter($data)
{
    return $data; // mettre en commentaire pour activer tous les filtres
    if (is_bool($data) || is_int($data) || is_float($data)) return $data;
    if ($data instanceof SafeString) return $data;
    return htmlspecialchars($data);
}

/**
 * Fonction trouvée sur le web, à tester : filtre les données
 * en fonction du contexte indiqué en paramètre.
 *
 * Source : http://www.codebelay.com/killxss.phps
 */
function h($string, $esc_type = 'htmlall')
{
    switch ($esc_type)
    {
        case 'css':
            $string = str_replace(array('<', '>', '\\'), array('&lt;', '&gt;', '&#47;'), $string);

            // get rid of various versions of javascript
            $string = preg_replace(
                    '/j\s*[\\\]*\s*a\s*[\\\]*\s*v\s*[\\\]*\s*a\s*[\\\]*\s*s\s*[\\\]*\s*c\s*[\\\]*\s*r\s*[\\\]*\s*i\s*[\\\]*\s*p\s*[\\\]*\s*t\s*[\\\]*\s*:/i',
                    'blocked', $string);
            $string = preg_replace(
                    '/@\s*[\\\]*\s*i\s*[\\\]*\s*m\s*[\\\]*\s*p\s*[\\\]*\s*o\s*[\\\]*\s*r\s*[\\\]*\s*t/i',
                    'blocked', $string);
            $string = preg_replace(
                    '/e\s*[\\\]*\s*x\s*[\\\]*\s*p\s*[\\\]*\s*r\s*[\\\]*\s*e\s*[\\\]*\s*s\s*[\\\]*\s*s\s*[\\\]*\s*i\s*[\\\]*\s*o\s*[\\\]*\s*n\s*[\\\]*\s*/i',
                    'blocked', $string);
            $string = preg_replace('/b\s*[\\\]*\s*i\s*[\\\]*\s*n\s*[\\\]*\s*d\s*[\\\]*\s*i\s*[\\\]*\s*n\s*[\\\]*\s*g:/i', 'blocked', $string);

            return $string;

        case 'html':
            //return htmlspecialchars($string, ENT_NOQUOTES);
            return str_replace(array('<', '>'), array('&lt;' , '&gt;'), $string);

        case 'htmlall':
            return htmlentities($string, ENT_QUOTES);

        case 'url':
            return rawurlencode($string);

        case 'query':
            return urlencode($string);

        case 'quotes':
            // escape unescaped single quotes
            return preg_replace("%(?<!\\\\)'%", "\\'", $string);

        case 'hex':
            // escape every character into hex
            $s_return = '';
            for ($x=0; $x < strlen($string); $x++)
            {
                $s_return .= '%' . bin2hex($string[$x]);
            }
            return $s_return;

        case 'hexentity':
            $s_return = '';
            for ($x=0; $x < strlen($string); $x++)
            {
                $s_return .= '&#x' . bin2hex($string[$x]) . ';';
            }
            return $s_return;

        case 'decentity':
            $s_return = '';
            for ($x=0; $x < strlen($string); $x++)
            {
                $s_return .= '&#' . ord($string[$x]) . ';';
            }
            return $s_return;

        case 'javascript':
            // escape quotes and backslashes, newlines, etc.
            return strtr($string, array('\\'=>'\\\\',"'"=>"\\'",'"'=>'\\"',"\r"=>'\\r',"\n"=>'\\n','</'=>'<\/'));

        case 'mail':
            // safe way to display e-mail address on a web page
            return str_replace(array('@', '.'),array(' [AT] ', ' [DOT] '), $string);

        case 'nonstd':
            // escape non-standard chars, such as ms document quotes
            $_res = '';
            for($_i = 0, $_len = strlen($string); $_i < $_len; $_i++)
            {
                $_ord = ord($string{$_i});
                // non-standard char, escape it
                if($_ord >= 126)
                {
                    $_res .= '&#' . $_ord . ';';
                }
                else
                {
                    $_res .= $string{$_i};
                }
            }
           return $_res;

        default:
            return $string;
    }
}

