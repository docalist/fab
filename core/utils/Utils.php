<?php
/**
 * @package     fab
 * @subpackage  util
 * @author 		Daniel M�nard <Daniel.Menard@bdsp.tm.fr>
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
     * 
     * @access private
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
     * 
	 * @param string $ext l'extension � ajouter
     * 
	 * @return string le nouveau path
	 */
	public static function defaultExtension($path, $ext)
	{
		if (self :: getExtension($path) === '')
			$path .= ($ext{0} == '.' ? $ext : ".$ext");
		return $path;
	}


	/**
	 * Remplace ou supprime l'extension de fichier d'un path. 
	 * Ne touche pas aux extensions pr�sentes dans les noms de r�pertoires
	 * (c:\toto.tmp\aa.jpg).
	 * G�re � la fois les slashs et les anti-slashs
	 * 
	 * @param string $path le path � modifier
     * 
	 * @param string $ext l'extension � appliquer � $path, ou vide pour supprimer
	 * l'extension existante. $ext peut �tre indiqu� avec ou sans point de d�but
	 */
	public static function setExtension($path, $ext = '')
	{
		if ($ext && $ext {0} != '.')
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
     * @return bool true si le r�pertoire a �t� cr��, false sinon 
     * (droits insuffisants, par exemple) 
	 */
	public static function makeDirectory($path)
	{
        if (is_dir($path)) return true;
		umask(0);
		return @mkdir($path, 0777, true);
	}


	/**
	 * Indique si le path pass� en param�tre est un chemin relatif
	 * 
	 * Remarque : aucun test d'existence du path indiqu� n'est fait.
	 * 
	 * @param string $path le path � tester
     * 
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
     * 
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
	 * makePath('a','b','c'); // 'a/b/c'
	 * makePath('/temp/','/dm/','test.txt'); // '/temp/dm/test.txt'
	 * </code>
	 * 
	 * Le path obtenu n'est pas normalis� : si les arguments pass�s contiennent
	 * des '.' ou des '..' le r�sultat les contiendra aussi.
     * 
     * Le s�parateur de r�pertoire, par contre, est normalis� (slashs et
     * anti-slash sont remplac�s par le s�parateur du syst�me h�te.)
	 * 
	 * @param string paramname un nombre variable d'arguments � concat�ner 
     * 
	 * @return string le path obtenu
	 */
	public static function makePath()
	{
		$path = '';
        $t=func_get_args();
		foreach ($t as $arg)
		{
            $arg = strtr($arg, '/\\', DIRECTORY_SEPARATOR.DIRECTORY_SEPARATOR);
			if ($path)
			{
				if ($arg && ($arg[0] === DIRECTORY_SEPARATOR))
					$path = rtrim($path, DIRECTORY_SEPARATOR).$arg;
				else
					$path = rtrim($path, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$arg;
			}
			else
			{
				$path = $arg;
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
	 * r�f�rences '..' ont �t� r�solues, utiliser {@link isCleanPath()} apr�s.
	 * 
	 * @param string $path le path � normaliser
     * 
	 * @return string le path normalis�
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
     * 
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
     * 
     * @param mixed $directory... les autres param�tres indiquent les
     * r�pertoires dans lesquels le fichier sera recherch�.
     * 
     * @return mixed false si le fichier n'a pas �t� trouv�, le
     * chemin exact du fichier dans le cas contraire.
     */
    public static function searchFile($file /* , $directory1, $directory2... $directoryn */)
    {
        $nb=func_num_args();
        for ($i=1; $i<$nb; $i++)
        {
            $dir=func_get_arg($i);
            debug && Debug::log("Recherche du fichier [%s]. result=[%s]", $file, realpath(rtrim($dir,'/\/').DIRECTORY_SEPARATOR.$file));
            if ($path=realpath(rtrim($dir,'/\/').DIRECTORY_SEPARATOR.$file)) 
                return $path;
        }
        return false;
    }

    
    /**
     * Recherche un fichier dans une liste de r�pertoires, sans tenir compte de la
     * casse du fichier recherch�.
     * 
     * Les r�pertoires sont examin�s dans l'ordre o� ils sont fournis.
     * 
     * @param string $file le fichier � chercher. Vous pouvez soit indiquer un
     * simple nom de fichier (par exemple 'test.php') ou bien un 'bout' de
     * chemin ('/modules/test.php')
     * 
     * @param mixed $directory... les autres param�tres indiquent les
     * r�pertoires dans lesquels le fichier sera recherch�.
     * 
     * @return mixed false si le fichier n'a pas �t� trouv�, le
     * chemin exact du fichier dans le cas contraire.
     */
    public static function searchFileNoCase($file /* , $directory1, $directory2... $directoryn */)
    {
        $nb=func_num_args();
        for ($i=1; $i<$nb; $i++)
        {
            $dir=rtrim(func_get_arg($i), '/\\');
            if (($handle=opendir($dir))!==false) // si le r�pertoire n'existe pas, on ignore
            {   
                while (($thisFile=readdir($handle)) !==false)
                {
                	if (strcasecmp($file, $thisFile)==0)
                    {
                        // pas de test && is_dir($thisFile)
                        // faut �tre tordu pour mettre dans le m�me r�pertoire
                        // un fichier et un sous-r�pertoire portant le m�me nom
                        
                        closedir($handle);
                        return realpath($dir . DIRECTORY_SEPARATOR . $thisFile);
                    }
                }
                closedir($handle);
            }
        }
        return false;
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

                'upper'=>
                    /*           0123456789ABCDEF*/
                    /* 00 */    "\x00\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0a\x0b\x0c\x0d\x0e\x0f".
                    /* 10 */    "\x10\x11\x12\x13\x14\x15\x16\x17\x18\x19\x1a\x1b\x1c\x1d\x1e\x1f".
                    /* 20 */    "\x20\x21\x22\x23\x24\x25\x26\x27\x28\x29\x2a\x2b\x2c\x2d\x2e\x2f".
                    /* 30 */    "0123456789\x3a\x3b\x3c\x3d\x3e\x3f".
                    /* 40 */    '@ABCDEFGHIJKLMNO'.
                    /* 50 */    "PQRSTUVWXYZ\x5b\x5c\x5d\x5e\x5f".
                    /* 60 */    "\x60ABCDEFGHIJKLMNO".
                    /* 70 */    "PQRSTUVWXYZ\x7b\x7c\x7d\x7e\x7f".
                    /* 80 */    "\x80\x81\x82\x83\x84\x85\x86\x87\x88\x89\x8a\x8b\x8c\x8d\x8e\x8f".
                    /* 90 */    "\x90\x91\x92\x93\x94\x95\x96\x97\x98\x99\x9a\x9b\x9c\x9d\x9e\x9f".
                    /* A0 */    "\xa0\xa1\xa2\xa3\xa4\xa5\xa6\xa7\xa8\xa9\xaa\xab\xac\xad\xae\xaf".
                    /* B0 */    "\xb0\xb1\xb2\xb3\xb4\xb5\xb6\xb7\xb8\xb9\xba\xbb\xbc\xbd\xbe\xbf".
                    /* C0 */    'AAAAAAACEEEEIIII'.
                    /* D0 */    'DNOOOOO 0UUUUY S'.
                    /* E0 */    'AAAAAAACEEEEIIII'.
                    /* F0 */    'DNOOOOO  UUUUYBY',

                'CP1252 to CP850' => // Table de conversion CP1252 vers CP850 (ANSI to DOS)
                    /*          00  01  02  03  04  05  06  07  08  09  0a  0b  0c  0d  0e  0f */
                    /* 00 */ "\x00\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0a\x0b\x0c\x0d\x0e\x0f" .
                    /* 10 */ "\x10\x11\x12\x13\x14\x15\x16\x17\x18\x19\x1a\x1b\x1c\x1d\x1e\x1f" .
                    /* 20 */ "\x20\x21\x22\x23\x24\x25\x26\x27\x28\x29\x2a\x2b\x2c\x2d\x2e\x2f" .
                    /* 30 */ "\x30\x31\x32\x33\x34\x35\x36\x37\x38\x39\x3a\x3b\x3c\x3d\x3e\x3f" .
                    /* 40 */ "\x40\x41\x42\x43\x44\x45\x46\x47\x48\x49\x4a\x4b\x4c\x4d\x4e\x4f" .
                    /* 50 */ "\x50\x51\x52\x53\x54\x55\x56\x57\x58\x59\x5a\x5b\x5c\x5d\x5e\x5f" .
                    /* 60 */ "\x60\x61\x62\x63\x64\x65\x66\x67\x68\x69\x6a\x6b\x6c\x6d\x6e\x6f" .
                    /* 70 */ "\x70\x71\x72\x73\x74\x75\x76\x77\x78\x79\x7a\x7b\x7c\x7d\x7e\x7f" .
                    /* 80 */ "\x45\x81\x60\x9f\x22\x2e\x2b\x87\x5e\x6f\x53\x3c\x4f\x8d\x5a\x8f" .
                    /* 90 */ "\x90\x60\xef\x22\x22\x6f\x2d\x2d\x7e\x54\x73\x3e\x6f\x9d\x7a\x22" .
                    /* a0 */ "\xff\xad\xbd\x9c\xcf\xbe\xdd\xf5\xf9\xb8\xa6\xae\xaa\xf0\xa9\xee" .
                    /* b0 */ "\xf8\xf1\xfd\xfc\xef\xe6\xf4\xfa\xf7\xfb\xa7\xaf\xac\xab\xf3\xa8" .
                    /* c0 */ "\xb7\xb5\xb6\xc7\x8e\x8f\x92\x80\xd4\x90\xd2\xd3\xde\xd6\xd7\xd8" .
                    /* d0 */ "\xd1\xa5\xe3\xe0\xe2\xe5\x99\x9e\x9d\xeb\xe9\xea\x9a\xed\xe8\xe1" .
                    /* e0 */ "\x85\xa0\x83\xc6\x84\x86\x91\x87\x8a\x82\x88\x89\x8d\xa1\x8c\x8b" .
                    /* f0 */ "\xd0\xa4\x95\xa2\x93\xe4\x94\xf6\x9b\x97\xa3\x96\x81\xec\xe7\x98",
                    
                'CP850 to CP1252' => // Table de conversion CP850 vers CP1252 (DOS TO ANSI)
                    /*          00  01  02  03  04  05  06  07  08  09  0a  0b  0c  0d  0e  0f */
                    /* 00 */ "\x00\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0a\x0b\x0c\x0d\x0e\x0f" .
                    /* 10 */ "\x10\x11\x12\x13\x14\x15\x16\x17\x18\x19\x1a\x1b\x1c\x1d\x1e\x1f" .
                    /* 20 */ "\x20\x21\x22\x23\x24\x25\x26\x27\x28\x29\x2a\x2b\x2c\x2d\x2e\x2f" .
                    /* 30 */ "\x30\x31\x32\x33\x34\x35\x36\x37\x38\x39\x3a\x3b\x3c\x3d\x3e\x3f" .
                    /* 40 */ "\x40\x41\x42\x43\x44\x45\x46\x47\x48\x49\x4a\x4b\x4c\x4d\x4e\x4f" .
                    /* 50 */ "\x50\x51\x52\x53\x54\x55\x56\x57\x58\x59\x5a\x5b\x5c\x5d\x5e\x5f" .
                    /* 60 */ "\x60\x61\x62\x63\x64\x65\x66\x67\x68\x69\x6a\x6b\x6c\x6d\x6e\x6f" .
                    /* 70 */ "\x70\x71\x72\x73\x74\x75\x76\x77\x78\x79\x7a\x7b\x7c\x7d\x7e\x7f" .
                    /* 80 */ "\xc7\xfc\xe9\xe2\xe4\xe0\xe5\xe7\xea\xeb\xe8\xef\xee\xec\xc4\xc5" .
                    /* 90 */ "\xc9\xe6\xc6\xf4\xf6\xf2\xfb\xf9\xff\xd6\xdc\xf8\xa3\xd8\xd7\x83" .
                    /* a0 */ "\xe1\xed\xf3\xfa\xf1\xd1\xaa\xba\xbf\xae\xac\xbd\xbc\xa1\xab\xbb" .
                    /* b0 */ "\xb0\xb1\xb2\x7c\x2b\xc1\xc2\xc0\xa9\xb9\xba\xbb\xbc\xa2\xa5\x2b" .
                    /* c0 */ "\x2b\x2b\x2b\x2b\x2d\x2b\xe3\xc3\xc8\xc9\xca\xcb\xcc\xcd\xce\xa4" .
                    /* d0 */ "\xf0\xd0\xca\xcb\xc8\x69\xcd\xce\xcf\x2b\x2b\xdb\xdc\xa6\xcc\xdf" .
                    /* e0 */ "\xd3\xdf\xd4\xd2\xf5\xd5\xb5\xfe\xde\xda\xdb\xd9\xfd\xdd\xaf\xb4" .
                    /* f0 */ "\xad\xb1\xf2\xbe\xb6\xa7\xf7\xb8\xb0\xa8\xb7\xb9\xb3\xb2\xfe\xa0",
                    
            );
        }
        if (! isset($tables[$table]))
            throw new Exception("La table de conversion de caract�res '$table' n'existe pas");
            
    	return strtr($string, $charFroms, $tables[$table]);
    }
    
    /**
     * Cr��e une table de conversion utilisable dans la fonction convertString ci-dessus.
     * 
     * La fonction utilise iconv (qui doit �tre disponible) pour g�n�rer le source php d'une
     * table permettant la conversion de caract�res SBCS (un octet=un caract�re).
     * 
     * Utilisation : faire un echo du r�sultat obtenu et int�grer ce source dans la fonction
     * convertString.
     * 
     * Exemple : pour cr�er une table conversion dos to ansi (plus exactement CP850 vers CP1252)
     * il suffit de faire echo createConversionTable('CP850','CP1252');
     * 
     * Remarques :
     * 
     * <li>l'option //TRANSLIT est ajout�e au charset de destination pour essayer de traduire
     * approximativement les caract�res qui n'ont pas de correspondance exacte.
     * Par exemple, le caract�re '�' sera traduit par 'f' pour en CP850.
     * 
     * <li>les caract�res sans correspondance sont conserv�s tels quels dans la table.
     * 
     * @param string $fromCharset le jeu de caract�re source
     * @param string $toCharset le jeu de caract�re destination
     * @return string l'extrait de code PHP permettant de d�finir la table 
     */
    public static function createConversionTable($fromCharset, $toCharset='CP1252')
    {
        // Ajoute l'option translit au charset de destination
        $toOri=$toCharset;
        $toCharset.='//TRANSLIT';

        // V�rifie que la fonction iconv est disponible
        if (! function_exists('iconv'))
            throw new Exception("La fonction iconv n'est pas disponible");
            
        // V�rifie que les charset indiqu�s sont valides    
        if (false===@iconv($fromCharset,$toCharset, 'a'))
            throw new Exception("L'un des charset indiqu�s n'est pas valide : '$fromCharset', '$toCharset'");
            
        // G�n�re l'ent�te de la table
        $table = '$table = // Table de conversion ' . $fromCharset . ' vers ' . $toOri . "\n";
        $table.= '/*          00  01  02  03  04  05  06  07  08  09  0a  0b  0c  0d  0e  0f */' . "\n";
        
        // G�n�re chacune des lignes 
        for ($i=0; $i<16; $i++)
        {
            // G�n�re l'ent�te de la ligne
            $table .= '/* '. dechex($i). '0 */ "';
            
            // G�n�re les 16 valeurs de la ligne
            for ($j=0; $j<16; $j++)
            {
                // Essaie de convertir le caract�re
                $code=$i*16 + $j;
                $char=@iconv($fromCharset, $toCharset, chr($code));
                
                // iconv retourne '' si elle n'arraive pas � convertir le caract�re
                if ($char!=='') $code=ord($char);
        
                $table .= '\x'. str_pad(dechex($code), 2, '0', STR_PAD_LEFT);
            }
            
            // Fin de la lgne
            $table .= '"'. ($i<15?' .':';'). "\n";
        }
    
        // G�n�re un exemple
        $table.= '// Exemple :' . "\n";
        $h='Le c�ur d��u mais l\'�me plut�t na�ve, Lou�s r�va de crapa�ter en cano� au del� des �les, pr�s du m�lstr�m o� br�lent les nov� (http://en.wikipedia.org/wiki/Pangram)';
        $len=max(strlen($fromCharset),strlen($toOri));
        $table .= '// ' . str_pad($fromCharset,$len) . " : $h\n";
        for($i=0; $i<strlen($h);$i++)
        	if ('' !== $char=@iconv($fromCharset, $toCharset, $h[$i])) $h[$i]=$char;
        $table .= '// ' . str_pad($toOri,$len) . " : $h\n";
        
        for($i=128; $i<256; $i++)
            $h.=chr($i);
        // Retourne le r�sultat
        return $table;
    }
    
    
    /**
     * Met en minuscule la premi�re lettre de la chaine pass�e en param�tre et
     * retourne le r�sultat obtenu.
     * 
     * @param string $str la chaine � convertir
     * 
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
     * 
     * @param mixed $defaultValue la valeur par d�faut � retourner si $anyVar
     * 
     * n'est pas d�finie (optionnel, valeur par d�faut null)
     * 
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
     * @param int $level le nombre de parents � ignorer
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
    
    
    public static function callerObject($level=1)
    {
        $stack=debug_backtrace();
        // En 0, on a la trace pour la fonction qui nous a appell�
        // En 1, on a la trace de la fonction qui a appell� celle qui nous a appell�.
        // en g�n�ral, c'est �a qu'on veut, donc $level=1 par d�faut
        
        return isset($stack[$level]['object']) ? $stack[$level]['object'] : null;
    }
    
    public static function callerClass($level=1)
    {
        $stack=debug_backtrace();
        // En 0, on a la trace pour la fonction qui nous a appell�
        // En 1, on a la trace de la fonction qui a appell� celle qui nous a appell�.
        // en g�n�ral, c'est �a qu'on veut, donc $level=1 par d�faut
        
        return @$stack[$level]['class'].@$stack[$level]['type'].@$stack[$level]['function']; // TODO: pourquoi le @ ?
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
            $raw = '&'. Runtime::$queryString=$_SERVER['QUERY_STRING'];
            $t= & $_GET;
        }
        
        // En m�thodes POST et PUT, on travaille avec l'entr�e standard et le tableau $_POST
        else
        {
            $raw = '&'. Runtime::$queryString=file_get_contents('php://input');
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
     * 
     * @return bool
     */
    public static function isGet()
    {
        return (strpos('GET HEAD', $_SERVER['REQUEST_METHOD']) !== false);
    }
    

    /**
     * Retourne vrai si on a �t� appell� en m�thode 'POST' ou 'PUT'
     * 
     * @return bool
     */
    public static function isPost()
    {
        return (strpos('POST PUT', $_SERVER['REQUEST_METHOD']) !== false);
    }
    
    
    /**
     * Charge un fichier de configuration au format YAML
     * 
     * Par d�faut, la fonction utilise la classe {@link spyc} mais si 
     * l'extension syck, qui est beaucoup plus rapide, est install�e, c'est
     * cette extension qui sera utilis�e.
     * 
     * @param string $path le path du fichier � charger
     * 
     * @return array un tableau associatif contenant la configuration lue
     */
    public static function loadYaml($path)
    {
        // utilise l'extension syck.dll si elle est disponible
        if (function_exists('syck_load'))
            return syck_load($path);

        // utilise la classe spyc sinon
        require_once (Runtime::$fabRoot.'lib/spyc/spyc.php');
        $spyc = new Spyc();
        return $spyc->load($path);
    }
    
    /**
     * Retourne le path du r�pertoire 'temp' du syst�me.
     * Le path obtenu n'a jamais de slash final.
     * 
     * @return string le path obtenu
     */
    public static function getTempDirectory()
    {
        static $dir=null;
        
        // Si on a d�j� d�termin� le r�pertoire temp, termin�
        if (!is_null($dir)) return $dir;
        
        // Si la fonction sys_get_temp_dir est dispo (php 6 ?), on l'utilise
        if ( function_exists('sys_get_temp_dir') )
            return $dir=rtrim(sys_get_temp_dir(), '/\\');
        
        // Regarde si on a l'une des variables d'environnement connues
        foreach(array('TMPDIR','TMP','TEMP') as $var)
            if ($h=Utils::get($_ENV[$var])) 
                return $dir=rtrim($h, '/\\');
        
        // Cr�e un fichier temporaire, r�cup�re son path, puis le d�truit
//        if (false !== $file = tempnam( md5(uniqid(rand(), TRUE)), '' ))
//        {
//            $dir = rtrim(realpath( dirname($file) ), '/\\');
//            unlink( $file );
//            return $dir;
//        }
        
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN')         
            return $dir='c:\\temp';
        else
            return $dir='/tmp';
    }

    /**
     * Affiche ou retourne la repr�sentation sous forme de code php
     * du contenu de la variable pass�e en param�tre.
     * 
     * Cette fonction fait la m�me chose que la fonction standard
     * var_export() de php, mais elle g�n�re un code plus compact,
     * pour les tableaux (pour les autres variables, la sortie 
     * g�n�r�e est la m�me qu'avec var_expor())
     * 
     * - pas de retours chariots ni d'espaces inutiles dans le code g�n�r�
     * - ne g�n�re les index de tableau que s'ils sont diff�rents de
     * l'index qui serait automatiquement attribu� s'il n'avait pas �t�
     * sp�cifi�  
     * 
     * Exemple : avec le tableau
     * <code>$t=array('a', 10=>'b', 'c', 'key'=>'d', 'e');</code>
     * 
     * On g�n�re le code :
     * <code>array('a',10=>'b','c','key'=>'d','e')</code>
     * 
     * Alors que la fonction var_export de php g�n�re :
     * <code>array (
     *   0 => 'a',
     *   10 => 'b',
     *   11 => 'c',
     *   'key' => 'd',
     *   12 => 'e',
     * )</code>
     * 
     * @param mixed $var la variable � afficher
     * @param boolean $return false : la fonction affiche le r�sultat, 
     * true : la fonction retourne le r�sultat
     */
    public static function varExport($var, $return = false)
    {
        if (! is_array($var)) return var_export($var, $return);
        
        $t = array();
        $index=0;
        foreach ($var as $key => $value)
        {
            if ($key<>$index)
            {
                $t[] = var_export($key, true).'=>'.self::varExport($value, true);
                if (is_int($key)) $index=$key+1;
            }
            else
            {
                $t[] = self::varExport($value, true);
                $index++;
            }
        }
        $code = 'array('.implode(',', $t).')';
        if ($return) return $code;
        echo $code;
    }    

    /**
     * Retourne une version coloris�e du code php pass� en param�tre
     * 
     * Il s'agit d'un wrapper autour de la fonction php highlight_string()
     * qui se charge d'ajouter (puis d'enlever) les tags de d�but et de fin 
     * de code php
     * 
     * @param string $php le code php � coloriser
     * @return string
     */
    public static function highlight($php)
    {
        return str_replace(array('&lt;?php&nbsp;', '?&gt;'), '', highlight_string('<?php '.$php.'?>', true));
    
    }
    
}
?>