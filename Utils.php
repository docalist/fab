<?php
/**
 * @package     fab
 * @subpackage  util
 * @author      dmenard
 * @version     SVN: $Id$
 */

/**
 * Fonctions utilitaires
 * 
 * @package     fab
 * @subpackage  util
 */
final class Utils
{
	/**
	 * constructeur
	 * 
	 * Le constructeur est priv� : il n'est pas possible d'instancier la
	 * classe. Utilisez directement les m�thodes statiques propos�es.
	 */
	private function __construct()
	{
	}

	/**
	 * Retourne la partie extension d'un path
	 * 
	 * @param string $path
	 * @return string l'extension du path ou une chaine vide  
	 */
	public static function getExtension($path)
	{
		$pt = strrpos($path, '.');

		if ($pt === false)
			return '';
		$ext = substr($path, $pt);
		if (strpos($ext, '/') === FALSE && strpos($ext, '\\') === FALSE)
			return $ext;
		return '';
	}

	/**
	 * Ajoute une extension au path indiqu� si celui-ci n'en a pas
	 * 
	 * @param string $path le path � modifier
	 * @param string $ext l'extension � ajouter
	 * @return string le nouveau path
	 * 
	 * Remarque : la fonction retourne le path obtenu mais le param�tre path,
	 * pass� par r�f�rence, est �galement modifi�. Cela permet d'�crire :
	 * <code>
	 * defaultExtension($h,'txt');
	 * $h=defaultExtension('test', 'txt');
	 * </code>
	 */
	public static function defaultExtension(& $path, $ext)
	{
		if (self :: getExtension($path) == '')
			$path .= ($ext {
			0 }
		== '.' ? $ext : ".$ext");
		return $path;
	}

	/**
	 * Remplace ou supprime l'extension de fichier d'un path. 
	 * Ne touche pas aux extensions pr�sentes dans les noms de r�pertoires
	 * (c:\toto.tmp\aa.jpg).
	 * G�re � la fois les slashs et les anti-slashs
	 * 
	 * @param string $path le path � modifier
	 * @param string $ext l'extension � appliquer � $path, ou vide pour supprimer
	 * l'extension existante. $ext peut �tre indiqu� avec ou sans point de d�but
	 */
	public static function setExtension(& $path, $ext = '')
	{
		if ($ext && $ext {
			0 }
		!= '.')
		$ext = ".$ext";

		$pt = strrpos($path, '.');

		if ($pt !== false)
		{
			$oldext = substr($path, $pt);
			if (strpos($oldext, '/') === FALSE && strpos($oldext, '\\') === FALSE)
			{
				$path = substr($path, 0, $pt) . $ext;
				return $path;
			}
		}
		$path = $path . $ext;
		return $path;
	}

	/**
	 * Cr�e le r�pertoire indiqu�
	 * 
	 * La fonction cr�e en une seule fois tous les r�pertoires n�cessaires du
	 * niveau le plus haut au plus bas.
	 * 
	 * Le r�pertoire cr�� a tous les droits (777).
	 * 
	 * @param string $path le chemin complet du r�pertoire � cr�er 
	 */
	public static function makeDirectory($path)
	{
        if (! is_dir($path))
        {
    		$current_umask = umask(0000);
    		mkdir($path, 0777, true);
    		umask($current_umask);
        }
	}

	/**
	 * Indique si le path pass� en param�tre est un chemin relatif
	 * 
	 * Remarque : aucun test d'existence du path indiqu� n'est fait.
	 * 
	 * @param string $path le path � tester
	 * @return bool true si path est un chemin relatif, false sinon
	 */
	public static function isRelativePath($path)
	{
		if (!$len = strlen($path)) return true;
		if (strpos('/\\', $path{0}) !== false) return false;
		if ($len > 2 && $path{1} == ':') return false;
		return true;
	}

	/**
	 * Indique si le path pass� en param�tre est un chemin absolu
	 * 
	 * Remarque : aucun test d'existence du path indiqu� n'est fait.
	 * 
	 * @param string $path le path � tester
	 * @return bool true si path est un chemin absolu, false sinon
	 */
	public static function isAbsolutePath($path)
	{
		return !self :: isRelativePath($path);
	}

	/**
	 * Construit un chemin complet � partir des bouts pass�s en param�tre.
	 * 
	 * La fonction concat�ne ses arguments en prenant soin d'ajouter
	 * correctement le s�parateur s'il est n�cessaire.
	 * 
	 * Exemple :
	 * <code>
	 * makePath('a','b','c') -> 'a/b/c'
	 * makePath('/temp/','/dm/','test.txt') -> '/temp/dm/test.txt'
	 * </code>
	 * 
	 * Le path obtenu n'est pas normalis� : si les arguments pass�s contiennent
	 * des '.' ou des '..' le r�sultat les contiendra aussi.
	 * 
	 * @param string paramname un nombre variable d'arguments � concat�ner 
	 * @return string le path obtenu
	 */
	public static function makePath()
	{
		$path = '';
		$nb = func_num_args();
		for ($i = 0; $i < $nb; $i++)
		{
			$h = func_get_arg($i);
			$h = strtr($h, '/\\', DIRECTORY_SEPARATOR);
			if ($path)
			{
				if ($h && ($h{0}== DIRECTORY_SEPARATOR))
				{
					//                    $path = rtrim($path, DIRECTORY_SEPARATOR).$h;
					$path = $path . substr($h, 1);
				}
				else
				{
					//                  $path = rtrim($path, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$h;
					$path .= $h;
				}
			}
			else
			{
				$path = $h;
			}
		}
		return $path;
	}

	/**
	 * Nettoie un path, en supprimant les parties '.' et '..' inutiles.
	 * 
	 * Exemple :
	 * <code>
	 * cleanPath('/a/b/../c/') -> '/a/c/'
	 * </code>
	 * 
	 * La fonction ne supprime que les parties '..' qui sont r�solvables, ce qui
	 * peut �viter certains attaques (acc�der � un r�pertoire au dessus du 
	 * r�pertoire 'root', par exemple). 
	 * 
	 * Exemple :
	 * <code>
	 * cleanPath('/a/../../') -> '/../'
	 * </code>
	 * 
	 * Pour savoir si le path obtenu est propre, c'est-�-dire si toutes les
	 * r�f�rences '..' ont �t� r�solues, utiliser {@link isCleanPath) apr�s.
	 * 
	 * @param string $path le path � normaliser
	 * @return string le path normalis�
	 * 
	 */
	public static function cleanPath($path)
	{
        if (strlen($path) > 2 && $path{1} == ':')
		{
			$prefix = substr($path, 0, 2);
			$path = substr($path, 2);
		}
		else
			$prefix = '';

		$path = preg_replace('~[/\\\\]+~', DIRECTORY_SEPARATOR, $path);
		$parts = explode(DIRECTORY_SEPARATOR, $path);
		$t = array ();

		foreach ($parts as $dir)
		{
			if ($dir == '.')
				continue;

			$last = end($t);
			if ($dir == '..' && $last != '' && $last != '..')
				array_pop($t);
			else
				$t[] = $dir;
		}
		$path = $prefix . implode(DIRECTORY_SEPARATOR, $t);
		return $path;

	}

	/**
	 * Indique si le path fourni contient des �l�ments '.' ou '..'
	 * 
	 * @param string $path le path � tester
	 * @return bool vrai si le path est propre, faux sinon.
	 */
	public static function isCleanPath($path)
	{
		return preg_match('~[/\\\\]\.\.?[/\\\\]|^\.\.|\.\.$~', $path) ? false : true;
	}
    
    /**
     * Recherche un fichier dans une liste de r�pertoires. Les r�pertoires sont
     * examin�s dans l'ordre o� ils sont fournis.
     * 
     * @param string $file le fichier � chercher. Vous pouvez soit indiquer un
     * simple nom de fichier (par exemple 'test.php') ou bien un 'bout' de
     * chemin ('/modules/test.php')
     * @param mixed $directory... les autres param�tres indiquent les
     * r�pertoires dans lesquels le fichier sera recherch�.
     * @return string une chaine vide si le fichier n'a pas �t� trouv�, le
     * chemin exact du fichier dans le cas contraire.
     */
    public static function searchFile($file /* , $directory1, $directory2... $directoryn */)
    {
        if (self::isAbsolutePath($file)) return realpath($file);
        $nb=func_num_args();
        
        for ($i=1; $i<$nb; $i++)
        {
            $dir=func_get_arg($i);
            debug && Debug::log("Recherche du template [%s]. result=[%s]", rtrim($dir,'/\/').DIRECTORY_SEPARATOR.$file, realpath(rtrim($dir,'/\/').DIRECTORY_SEPARATOR.$file));
            if ($path=realpath(rtrim($dir,'/\/').DIRECTORY_SEPARATOR.$file)) return $path;
            
        }
        return '';
    }
    
    public static function searchFileNoCase($file /* , $directory1, $directory2... $directoryn */)
    {
        $nb=func_num_args();
        for ($i=1; $i<$nb; $i++)
        {
            $dir=rtrim(func_get_arg($i), '/\\');
            if (($handle=opendir($dir))===false)
                throw new Exception("Le r�pertoire '$dir' pass� � ".__CLASS__.'::'.__METHOD__ . "() n'existe pas");
                
            while (($thisFile=readdir($handle)) !==false)
            {
            	if (strcasecmp($file, $thisFile)==0)
                {
                    // pas de test && is_dir($thisFile)
                    // faut �tre tordu pour mettre dans le m�me r�pertoire
                    // un fichier et un sous-r�pertoire portant le m�me nom
                    
                    closedir($handle);
                    return $dir . DIRECTORY_SEPARATOR . $thisFile;
                }
            }
            closedir($handle);
        }
        return '';
    }
    
    // TODO: doc � �crire
    public static function convertString($string, $table='bis')
    {
        static $charFroms=null, $tables=null;
        
        if (is_null($charFroms))
        { 
            $charFroms=
                    /*           0123456789ABCDEF*/
                    /* 00 */    "\x00\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0a\x0b\x0c\x0d\x0e\x0f".
                    /* 10 */    "\x10\x11\x12\x13\x14\x15\x16\x17\x18\x19\x1a\x1b\x1c\x1d\x1e\x1f".
                    /* 20 */    "\x20\x21\x22\x23\x24\x25\x26\x27\x28\x29\x2a\x2b\x2c\x2d\x2e\x2f".
                    /* 30 */    "\x30\x31\x32\x33\x34\x35\x36\x37\x38\x39\x3a\x3b\x3c\x3d\x3e\x3f".
                    /* 40 */    "\x40\x41\x42\x43\x44\x45\x46\x47\x48\x49\x4a\x4b\x4c\x4d\x4e\x4f".
                    /* 50 */    "\x50\x51\x52\x53\x54\x55\x56\x57\x58\x59\x5a\x5b\x5c\x5d\x5e\x5f".
                    /* 60 */    "\x60\x61\x62\x63\x64\x65\x66\x67\x68\x69\x6a\x6b\x6c\x6d\x6e\x6f".
                    /* 70 */    "\x70\x71\x72\x73\x74\x75\x76\x77\x78\x79\x7a\x7b\x7c\x7d\x7e\x7f".
                    /* 80 */    "\x80\x81\x82\x83\x84\x85\x86\x87\x88\x89\x8a\x8b\x8c\x8d\x8e\x8f".
                    /* 90 */    "\x90\x91\x92\x93\x94\x95\x96\x97\x98\x99\x9a\x9b\x9c\x9d\x9e\x9f".
                    /* A0 */    "\xa0\xa1\xa2\xa3\xa4\xa5\xa6\xa7\xa8\xa9\xaa\xab\xac\xad\xae\xaf".
                    /* B0 */    "\xb0\xb1\xb2\xb3\xb4\xb5\xb6\xb7\xb8\xb9\xba\xbb\xbc\xbd\xbe\xbf".
                    /* C0 */    "\xc0\xc1\xc2\xc3\xc4\xc5\xc6\xc7\xc8\xc9\xca\xcb\xcc\xcd\xce\xcf".
                    /* D0 */    "\xd0\xd1\xd2\xd3\xd4\xd5\xd6\xd7\xd8\xd9\xda\xdb\xdc\xdd\xde\xdf".
                    /* E0 */    "\xe0\xe1\xe2\xe3\xe4\xe5\xe6\xe7\xe8\xe9\xea\xeb\xec\xed\xee\xef".
                    /* F0 */    "\xf0\xf1\xf2\xf3\xf4\xf5\xf6\xf7\xf8\xf9\xfa\xfb\xfc\xfd\xfe\xff";
                
            $tables=array
            (
                'bis'=>
                    /*           0123456789ABCDEF*/
                    /* 00 */    '                '.
                    /* 10 */    '                '.
                    /* 20 */    '                '.
                    /* 30 */    '0123456789      '.
                    /* 40 */    '@abcdefghijklmno'.
                    /* 50 */    'pqrstuvwxyz     '.
                    /* 60 */    ' abcdefghijklmno'.
                    /* 70 */    'pqrstuvwxyz     '.
                    /* 80 */    '                '.
                    /* 90 */    '                '.
                    /* A0 */    '                '.
                    /* B0 */    '                '.
                    /* C0 */    'aaaaaaaceeeeiiii'.
                    /* D0 */    'dnooooo 0uuuuy s'.
                    /* E0 */    'aaaaaaaceeeeiiii'.
                    /* F0 */    'dnooooo  uuuuyby',
                    
                'lower'=>
                    /*           0123456789ABCDEF*/
                    /* 00 */    "\x00\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0a\x0b\x0c\x0d\x0e\x0f".
                    /* 10 */    "\x10\x11\x12\x13\x14\x15\x16\x17\x18\x19\x1a\x1b\x1c\x1d\x1e\x1f".
                    /* 20 */    "\x20\x21\x22\x23\x24\x25\x26\x27\x28\x29\x2a\x2b\x2c\x2d\x2e\x2f".
                    /* 30 */    "0123456789\x3a\x3b\x3c\x3d\x3e\x3f".
                    /* 40 */    '@abcdefghijklmno'.
                    /* 50 */    "pqrstuvwxyz\x5b\x5c\x5d\x5e\x5f".
                    /* 60 */    "\x60abcdefghijklmno".
                    /* 70 */    "pqrstuvwxyz\x7b\x7c\x7d\x7e\x7f".
                    /* 80 */    "\x80\x81\x82\x83\x84\x85\x86\x87\x88\x89\x8a\x8b\x8c\x8d\x8e\x8f".
                    /* 90 */    "\x90\x91\x92\x93\x94\x95\x96\x97\x98\x99\x9a\x9b\x9c\x9d\x9e\x9f".
                    /* A0 */    "\xa0\xa1\xa2\xa3\xa4\xa5\xa6\xa7\xa8\xa9\xaa\xab\xac\xad\xae\xaf".
                    /* B0 */    "\xb0\xb1\xb2\xb3\xb4\xb5\xb6\xb7\xb8\xb9\xba\xbb\xbc\xbd\xbe\xbf".
                    /* C0 */    'aaaaaaaceeeeiiii'.
                    /* D0 */    'dnooooo 0uuuuy s'.
                    /* E0 */    'aaaaaaaceeeeiiii'.
                    /* F0 */    'dnooooo  uuuuyby',
            );
        }
        if (! isset($tables[$table]))
            throw new Exception("La table de conversion de caract�res '$table' n'existe pas");
            
    	return strtr($string, $charFroms, $tables[$table]);
    }
    
    /**
     * Met en minuscule la premi�re lettre de la chaine pass�e en param�tre et
     * retourne le r�sultat obtenu.
     * 
     * @param string $str la chaine � convertir
     * @return string la chaine obtenue
     */
    public static function lcfirst($str)
    {
        return strtolower(substr($str, 0, 1)) . substr($str, 1);
    }
    
    /**
     * Retourne la valeur de la variable pass�e en param�tre si celle-ci est
     * d�finie et contient autre chose qu'une chaine vide ou la valeur par
     * d�faut sinon.
     * 
     * Remarque : la fonction repose sur le fait que la variable � examiner est
     * pass�e par r�f�rence, bien que la fonction ne modifie aucune variable. Ca
     * �vite que php g�n�re un warning indiquant que la variable n'existe pas.
     * 
     * On peut appeller la fonction avec une variable simple, un tableau, un
     * �l�ment de tableau, etc.
     * 
     * Remarque 2 : anyVar doit �tre une variable. �a ne marchera pas si c'est
     * un appel de fonction, une propri�t� inexistante d'un objet, une
     * constante, etc.
     * 
     * Remarque 3 : �quivalent � 'empty', mais ne retourne pas vrai pour une
     * chaine contenant la valeur '0' ou pour un entier 0 ou pour un bool�en
     * false.
     * 
     * @param mixed $anyVar	la variable � examiner
     * @param mixed $defaultValue la valeur par d�faut � retourner si $anyVar
     * n'est pas d�finie (optionnel, valeur par d�faut null)
     * @return mixed
     */
    public static function get(&$anyVar, $defaultValue=null)
    {
        if (! isset($anyVar)) return $defaultValue;
        if (is_string($anyVar) && strlen(trim($anyVar))==0) return $defaultValue;
        if (is_bool($anyVar) or is_int($anyVar)) return $anyVar;
        if (is_float($anyVar)) return is_nan($anyVar) ? $defaultValue : $anyVar;
        if (is_array($anyVar) && count($anyVar)==0) return $defaultValue;
        return $anyVar;
    }

// idem mais nb 'illimit�' de variables pass�es par r�f�rence. pb : oblige � passer defaultvalue
// en premier ce qui est contre-intuitif. 
//    public static function getAny($defaultValue, &$var1, &$var2=null, &$var3=null, &$var4=null, &$var5=null)
//    {
//        $nb=func_num_args();
//        for ($i=1; $i<$nb; $i++)
//        {
//            $arg=func_get_arg($i);
//            if 
//            (
//                    isset($arg)
//                &&  (is_string($arg) && $arg != '')
//            ) return $arg;
//        }
//        return $defaultValue;
//    }

    /**
     * Retourne le path du script qui a appell� la fonction qui appelle
     * callerScript.
     * 
     * Exemple : un script 'un.php' appelle une fonction test() qui se trouve
     * ailleurs. La fonction test() veut savoir qui l'a appell�. Elle appelle
     * callerScript() qui va retourner le path complet de 'un.php'
     * 
     * @return string
     */
    public static function callerScript($level=1)
    {
        $stack=debug_backtrace();
        // En 0, on a la trace pour la fonction qui nous a appell�
        // En 1, on a la trace de la fonction qui a appell� celle qui nous a appell�.
        // en g�n�ral, c'est �a qu'on veut, donc $level=1 par d�faut
        
        return $stack[$level]['file'];
    }
    
    public static function callerClass($level=1)
    {
        $stack=debug_backtrace();
        // En 0, on a la trace pour la fonction qui nous a appell�
        // En 1, on a la trace de la fonction qui a appell� celle qui nous a appell�.
        // en g�n�ral, c'est �a qu'on veut, donc $level=1 par d�faut
        
        return @$stack[$level]['class']; // TODO: pourquoi le @ ?
    }
    
    public static function callLevel()
    {
        return count(debug_backtrace())-1;
    }
    
    /**
     * Retourne l'adresse du serveur 
     * (exemple : http://www.bdsp.tm.fr)
     */
    public static function getHost()
    {
        $host= Utils::get($_SERVER['HTTP_HOST']);
        if (! $host) $host=Utils::get($_SERVER['SERVER_NAME']);
        if (! $host) $host=Utils::get($_SERVER['SERVER_ADDR']);
            
        if (Utils::get($_SERVER['HTTPS'])=='on')
            $host='https://' . $host;
        else 
            $host='http://' . $host;
        
        $port=$_SERVER['SERVER_PORT'];
        if ($port != '80') $host .= ':' . $port;
        
    	return $host;
    }

    // r�pare $_GET, $_REQUEST et $_POST
    // remarque : php://input n'est pas disponible avec enctype="multipart/form-data".
    public static function repairGetPostRequest()
    {
        // Cr�e une fonction anonyme : urldecode avec l'argument modifi� par r�f�rence
        $urldecode=create_function('&$h','$h=urldecode($h);');
        
        // Si on est en m�thode 'GET', on travaille avec la query_string et le tableau $_GET
        if (self::isGet())
        {
            $raw = '&'.$_SERVER['QUERY_STRING'];
            $t= & $_GET;
        }
        
        // En m�thodes POST et PUT, on travaille avec l'entr�e standard et le tableau $_POST
        else
        {
            $raw = '&'.file_get_contents('php://input');
            $t = & $_POST;          
        }

        // Parcourt tous les arguments et modifient ceux qui sont multivalu�s
        foreach($t as $key=>$value)
        {
            if (preg_match_all('/&'.$key.'=([^&]*)/',$raw, $matches, PREG_PATTERN_ORDER) > 1)
            {
                array_walk($matches[1], $urldecode);
                $_REQUEST[$key]=$t[$key]=$matches[1];
            }
        }
    }    

    /**
     * Retourne vrai si on a �t� appell� en m�thode 'GET' ou 'HEAD'
     */
    public static function isGet()
    {
        return (strpos('GET HEAD', $_SERVER['REQUEST_METHOD']) !== false);
    }
    
    /**
     * Retourne vrai si on a �t� appell� en m�thode 'POST' ou 'PUT'
     */
    public static function isPost()
    {
        return (strpos('POST PUT', $_SERVER['REQUEST_METHOD']) !== false);
    }
    
    /**
     * Charge un fichier de configuration au format YAML
     * 
     * @param string $path le path du fichier � charger
     * @return array un tableau associatif contenant la configuration lue
     */
    public static function loadYaml($path)
    {
        // utilise l'extension syck.dll si elle est disponible
        if (function_exists('syck_load'))
            return syck_load($path);

        // utilise la classe spyc sinon
        require_once ('lib/spyc/spyc.php');
        $spyc = new Spyc();
        return $spyc->load($path);
    }

    


}
?>