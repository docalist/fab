<?php

/**
 * @package     fab
 * @subpackage  Admin
 * @author      Daniel Ménard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id$
 */

/**
 * Module d'administration permettant de gérer les fichiers et les dossiers 
 * présents au sein d'un répertoire.
 * 
 * Le module offre des fonctions permettant de {@link actionNewFile() créer}, de 
 * {@link actionRename() renommer}, de {@link actionEdit() modifier}, 
 * de {@link actionCopy() copier}, de {@link actionDelete() supprimer} et de 
 * {@link actionDownload() télécharger} les fichiers.
 * 
 * Il permet également de {@link getBackUrl() naviguer} dans l'arborescence des 
 * répertoires et offre des fonctions permettant de {@link actionCopy() copier}, 
 * de {@link actionRename() renommer} et de {@link actionDelete() supprimer} des
 * répertoires.
 * 
 * Le module travaille par rapport à un répertoire de travail qui est indiqué 
 * par la méthode {@link getDirectory()}. Il n'est pas possible de "remonter"
 * plus haut que ce répertoire, ni d'intervenir sur des fichiers situé en dehors
 * de l'arborescence définie par ce répertoire.
 * 
 * Néanmoins, il est possible de copier un fichier "extérieur" dans 
 * l'arborescence du répertoire de travail en utilisant la méthode 
 * {@link actionCopyFrom()}.
 * 
 * <code>AdminFiles</code> peut être utilisé directement ou servir de classe 
 * ancêtre à un module d'administration ayant besoin d'offrir des actions de 
 * manipulation de fichiers et de répertoires.
 * 
 * C'est le cas par exemple des modules d'administration {@link AdminCache}, 
 * {@link AdminConfig}, {@link AdminModules} et {@link AdminSchemas}.
 * 
 * @package     fab
 * @subpackage  Admin
 */
class AdminFiles extends Admin
{

    /**
     * Retourne le path complet du répertoire sur lequel va travailler le module.
     * 
     * Le path retourné est construit à partir des élements suivants :
     * - le répertoire racine de l'application ({@link Runtime::$root})
     * - le répertoire indiqué dans la clé <code><directory></code> de la config
     * - le répertoire éventuel indiqué dans le paramètre <code>directory</code>
     *   indiqué en query string
     * 
     * Le chemin retourné contient toujours un slash final (un antislash sous
     * windows).
     * 
     * Cette méthode peut être surchargée par les modules d'administration
     * descendants si une implémentation différente est souhaitée.
     *
     * @throws Exception est générée si :
     * - le répertoire obtenu contient des séquences de la forme 
     *   <code>/../</code> (ces séquences sont interdites pour empécher 
     *   de remonter plus haut que le répertoire racine de l'application ou que
     *   le répertoire de travail indiqué dans le fichier de configuration).
     * - le répertoire obtenu n'existe pas ou désigne autre chose qu'un 
     *   répertoire.
     * 
     * @return string le path exact (absolu) du répertoire de travail obtenu.
     */
    public function getDirectory()
    {
        // Construit le path
        $path=Utils::makePath
        (
            Runtime::$root,                     // On ne travaille que dans l'application 
            Config::get('directory'),           // Répertoire indiqué dans la config
            $this->request->get('directory'),   // Répertoire éventuel passé en paramètre
            DIRECTORY_SEPARATOR                 // Garantit qu'on a un slash final
        );
        
        // Vérifie qu'on n'a pas de '..'
        $this->checkPath($path, 'Répertoire de travail');
        
        // Vérifie que c'est un répertoire et que celui-ci existe
        if (!is_dir($path))
            throw new Exception("Le répertoire indiqué n'existe pas.");
            
        // Retourne le résultat
        return $path;
    }

    
    /**
     * Vérifie que le path indiqué en paramètre ne contient pas de séquences
     * de la forme <code>../</code>.
     * 
     * Ces séquences sont interdites pour empécher de remonter plus haut
     * que le répertoire racine de l'application ou que le répertoire de travail 
     * indiqué dans le fichier de configuration.
     *
     * @param string $path le path ou le nom de fichier à tester.
     * @param string $what un message optionnel qui sera affiché dans la
     * description de l'exception générée.
     * 
     * @throws Exception si le path contient des séquences interdites. 
     */
    private function checkPath($path, $what)
    {
         // Vérifie qu'on n'a pas de séquences /../ dans le path
        if (strpos($path, '..'.DIRECTORY_SEPARATOR) !== false)
            throw new Exception($what . " : les séquences de la forme '..' ne sont pas autorisées.");
    }
    
    
    /**
     * Retourne le répertoire parent du répertoire de travail indiqué par 
     * {@link getDirectory()}.
     * 
     * La méthode si un sous-répertoire a été indiqué dans le paramètre
     * <code>directory</code> de la {@link Request requête en cours}.
     * 
     * Si c'est le cas, elle retourne le répertoire parent de ce répertoire.
     * 
     * @return false|string la fonction retourne :
     * - <code>false</code> si aucun sous-répertoire n'a été indiqué (i.e. on
     *   est déjà "à la racine") ;
     * - le path exact du répertoire parent si un sous-répertoire (non vide) a 
     *   été indiqué dans la requête,
     * - une chaine vide si le répertoire parent obtenu correspond à la racine,
     *   c'est-à-dire au répertoire retourné par {@link getDirectory()}.
     */
    public function getParentDirectory()
    {
        $path=$this->request->get('directory', '');
        if ($path==='') return false;
        $path=strtr($path, '\\', '/');
        $path=rtrim($path, '/');
        $pt=strrpos($path, '/');
        return substr($path, 0, $pt);
    }
    
    
    /**
     * Retourne la liste des fichiers et des dossiers présents dans le 
     * répertoire de travail.
     * 
     * Le tableau obtenu liste en premier les fichiers (triés par ordre 
     * alphabétique) puis les dossiers (également triés).
     * 
     * Les clés des entrées du tableau contiennent le path complet 
     * du fichier ou du dossier, la valeur associée contient uniquement
     * le nom de base.
     * 
     * Seuls les fichiers et les dossiers correspondant au masque <code>*</code>
     * sont retournés. Cela signifie (entre autres) que les fichiers dont le nom
     * commence par un point (<code>.project</code>, <code>.htaccess</code>, ...) ne
     * seront pas retournés. Idem pour les dossiers (<code>.</code>, 
     * <code>..</code>, <code>.svn</code>, ...)
     * 
     * @return array le tableau obtenu ou un tableau vide si le répertoire
     * de travail est vide.
     */
    public function getFiles()
    {
        $files=array();
        $dirs=array();
        
        // Dresse la liste des fichiers et des dossiers
        foreach(glob($this->getDirectory() . '*') as $path)
        {
            if (is_file($path) || is_link($path))
                $files[$path]=basename($path);
            else
                $dirs[$path]=basename($path);
        }
        
        // Trie les fichiers par ordre alphabétique
        ksort($files, SORT_LOCALE_STRING);
        
        // Trie les dossiers par ordre alphabétique
        ksort($dirs, SORT_LOCALE_STRING);
        
        // Concatène les fichiers et les dossiers et retourne le tout
        return $files + $dirs;
    }
    
    /**
     * Détermine l'icone à afficher pour représenter le type du fichier ou du
     * dossier passé en paramètre.
     *  
     * Si le path indiqué désigne un répertoire, une icone générique 
     * représentant un dossier est retournée.
     * 
     * Dans le cas contraire, elle examine l'extension présente dans le nom du 
     * fichier (<code>.php</code>, <code>.xml</code>, etc) pour déterminer le
     * type de ce fichier et retourne une icone en conséquence.
     * 
     * Si le fichier ne comporte pas d'extension ou si cette extension n'est
     * pas reconnue, une icone générique représentant un fichier est retournée.
     *
     * @param string $path
     * @return string une url de la forme 
     * <code>/FawWeb/modules/AdminFiles/images/filetypes/icon.png</code>
     */
    public function getFileIcon($path)
    {
        // Path du répertoire contenant les icones
        $icon='/FabWeb/modules/AdminFiles/images/filetypes/';
        
        // Cas d'un dossier
        if (is_dir($path))
            return $icon . 'folder.png';

        // Fichier : regarde l'extension
        switch(strtolower(Utils::getExtension($path)))
        {
            // fixme: mettre tout ça dans la config (clé filetypes avec une clé par type)
            case '.config': 
                $icon.='config.png'; 
                break;
            case '.css': 
                $icon.='css.png'; 
                break;
            case '.gif': 
                $icon.='gif.png'; 
                break;
            case '.htm':
            case '.html':
                $icon.='html.png'; 
                break;
            case '.jpg': 
                $icon.='jpg.png'; 
                break;
            case '.pdf': 
                $icon.='pdf.png'; 
                break;
            case '.php': 
                $icon.='php.png'; 
                break;
            case '.png': 
                $icon.='png.png'; 
                break;
            case '.xml': 
                $icon.='xml.png'; 
                break;
            case '.zip': 
                $icon.='zip.png'; 
                break;
            case '.config': 
                $icon.='config.png'; 
                break;
            default: 
                $icon.='default.png';
        }
        
        // Retourne le résultat
        return $icon;
    }

    
    /**
     * Détermine le type de coloration syntaxique à appliquer au fichier dont le
     * nom est passé en paramètre.
     * 
     * Cette méthode permet de paramétrer l'éditeur de code (actuellement, il 
     * s'agit de {@link http://www.cdolivet.net/editarea/ EditArea}) utilisé
     * par l'action {@link actionEdit() Edit}.
     * 
     * Elle retourne le nom d'une syntaxe à utiliser pour afficher correctement
     * le fichier dans l'éditeur, en se basant sur l'extension du fichier passé 
     * en paramètre pour déterminer le type du fichier.
     *
     * @param string $file le nom ou le path d'un fichier.
     * 
     * Remarque :
     * Aucun test d'existence n'est fait sur le path indiqué.
     * 
     * @return string le code de l'un des fichiers de syntaxe supportés par 
     * {@link http://www.cdolivet.net/editarea/ EditArea}
     */
    public function getEditorSyntax($file)
    {
        switch (strtolower(Utils::getExtension($file)))
        {
            case '.htm':
            case '.html':
                 return 'html';
            case '.css': 
                return 'css';
            case '.js':
                return 'js';
            case '.php':
                return 'php';
            case '.xml': 
                return 'xml';
            case '.config':
                return 'xml';
            default: return 'brainfuck';
        }
    }

    
    /**
     * Redirige l'utilisateur vers la page d'où il vient. 
     *
     * L'utilisateur est redirigé vers l'action index du module, en indiquant
     * éventuellement une ancre sur laquelle positionner l'utilisateur.
     * 
     * @param string $file le nom d'un fichier à utiliser comme ancre. 
     */
    private function goBack($file='')
    {
        Runtime::redirect($this->getBackUrl());
    }

    
    /**
     * Retourne une url permettant de rediriger l'utilisateur vers la page 
     * d'où il vient. 
     *
     * L'utilisateur est redirigé vers l'action index du module, en indiquant
     * éventuellement une ancre sur laquelle positionner l'utilisateur.
     * 
     * @param string $file le nom d'un fichier à utiliser comme ancre.
     * @return string 
     */
    public function getBackUrl($file='')
    {
        $url=$this->request->setAction('Index')->keepOnly('directory');
        if ($url->get('directory')==='') $url->clear('directory');
        $url=$url->getUrl();
        
        if ($file) $url.='#'.$file;
        return $url;
    }


    /**
     * Page d'accueil.
     * 
     * <code>actionIndex</code> représente la page d'accueil du module 
     * d'administration des fichiers.
     * 
     * Elle liste tous les fichiers et les dossiers présents dans le 
     * {@link getDirectory() répertoire de travail}.
     * 
     * Le template utilisé pour l'affichage est indiqué dans la clé
     * <code><template></code> du fichier de configuration.
     */
    public function actionIndex()
    {
        Template::run(Config::get('template'));
    }


    /**
     * Crée un nouveau fichier.
     * 
     * - demande le nom du fichier à créer,
     * - vérifie que ce nom de fichier est correct,
     * - vérifie qu'il n'existe pas déjà un fichier ou un répertoire portant
     *   ce nom,
     * - crée le fichier,
     * - redirige l'utilisateur vers la page d'accueil.
     * 
     * Si aucun nom de fichier n'a été passé en paramètre, un nom par défaut est 
     * proposé à l'utilisateur en utilisant la valeur indiquée dans la clé 
     * <code><newfilename></code> du fichier de configuration et la méthode 
     * appelle ensuite le template indiqué dans la clé <code><template></code> 
     * du fichier de configuration en lui passant en paramètre le nom du fichier 
     * (<code>file</code>).
     * 
     * Si le nom choisi n'est pas valide ou est déjà utilisé, le même template
     * est réaffiché, en indiquant en plus le message d'erreur obtenu 
     * (<code>error</code>).
     * 
     * Sinon, la méthode crée le fichier (vide). Si une erreur survient à ce 
     * stade (droits insuffisants, partition pleine...), celle-ci est simplement 
     * affichée. Dans le cas contraire, l'utilisateur est redirigé vers la page 
     * d'accueil du site et est positionné sur le nouveau fichier créé. 
     * 
     * @param string $file
     */
    public function actionNewFile($file='')
    {
        $path=$this->getDirectory().$file;
        
        $error='';
        
        // Vérifie que le fichier indiqué n'existe pas déjà
        if ($file !== '')
        {
            // Vérifie qu'on n'a pas de '..'
            $this->checkPath($file, 'Nom du nouveau fichier');
        
            if (file_exists($path))
            {
                if (is_dir($path))
                    $error="Il existe déjà un dossier nommé $file.";
                else
                    $error="Il existe déjà un fichier nommé $file.";
            }
        }
        
        // Demande le nom du fichier à créer
        if ($file==='' || $error !='')
        {
            if ($file==='') $file=Config::get('newfilename');
            Template::run
            (
                Config::get('template'),
                array('file'=>$file, 'error'=>$error)
            );
            return;
        }
        
        // Crée le fichier
        if (false === @file_put_contents($path, ''))
        {
            echo 'La création du fichier ', $file, ' a échoué.';
            return;
        }
                
        // Redirige vers la page d'accueil
        $this->goBack($file);
    }
    

    /**
     * Crée un nouveau dossier.
     * 
     * - demande le nom du dossier à créer,
     * - vérifie que ce nom de dossier est correct,
     * - vérifie qu'il n'existe pas déjà un fichier ou un répertoire portant
     *   ce nom,
     * - crée le dossier,
     * - redirige l'utilisateur vers la page d'accueil.
     * 
     * Si aucun nom n'a été passé en paramètre, un nom par défaut est 
     * proposé à l'utilisateur en utilisant la valeur indiquée dans la clé 
     * <code><newfoldername></code> du fichier de configuration et la méthode 
     * appelle ensuite le template indiqué dans la clé <code><template></code> 
     * du fichier de configuration en lui passant en paramètre le nom du dossier 
     * (<code>file</code>).
     * 
     * Si le nom choisi n'est pas valide ou est déjà utilisé, le même template
     * est réaffiché, en indiquant en plus le message d'erreur obtenu 
     * (<code>error</code>).
     * 
     * Sinon, la méthode crée le dossier. Si une erreur survient à ce 
     * stade (droits insuffisants, partition pleine...), celle-ci est simplement 
     * affichée. Dans le cas contraire, l'utilisateur est redirigé vers la page 
     * d'accueil du site et est positionné sur le nouveau dossier créé. 
     * 
     * @param string $file
     */
    public function actionNewFolder($file='')
    {
        $path=$this->getDirectory().$file;
        
        $error='';
        
        // Vérifie que le dossier indiqué n'existe pas déjà
        if ($file !== '')
        {
            // Vérifie qu'on n'a pas de '..'
            $this->checkPath($file, 'Nom du nouveau dossier');
        
            if (file_exists($path))
            {
                if (is_dir($path))
                    $error="Il existe déjà un dossier nommé $file.";
                else
                    $error="Il existe déjà un fichier nommé $file.";
            }
        }
        
        // Demande le nom du fichier à créer
        if ($file==='' || $error !='')
        {
            if ($file==='') $file=Config::get('newfoldername');
            Template::run
            (
                Config::get('template'),
                array('file'=>$file, 'error'=>$error)
            );
            return;
        }
        
        // Crée le fichier ou le dossier
        if (false=== @mkdir($path))
        {
            echo 'La création du répertoire ', $file, ' a échoué.';
            return;
        }
                
        // Redirige vers la page d'accueil
        $this->goBack($file);
    }
    
    
    /**
     * Renomme un fichier ou un dossier existant.
     * 
     * - demande le nouveau nom du fichier ou du dossier à renommer,
     * - vérifie que ce nom est correct,
     * - vérifie qu'il n'existe pas déjà un fichier ou un répertoire portant
     *   ce nom,
     * - renomme le fichier ou le dossier,
     * - redirige l'utilisateur vers la page d'accueil.
     * 
     * Si le nouveau nom n'a pas été passé en paramètre, la méthode 
     * appelle le template indiqué dans la clé <code><template></code> 
     * du fichier de configuration en lui passant en paramètre le nom du fichier 
     * ou du dossier à renommer (<code>file</code>).
     * 
     * Si le nouveau nom choisi par l'utilisateur n'est pas valide ou est déjà 
     * utilisé, le même template est réaffiché, en indiquant en plus le nouveau
     * nom (<code>newName</code>) et le message d'erreur obtenu 
     * (<code>error</code>).
     * 
     * Sinon, la méthode renomme le fichier ou le dossier. Si une erreur 
     * survient à ce stade (droits insuffisants par exemple), celle-ci est 
     * simplement affichée. Dans le cas contraire, l'utilisateur est redirigé 
     * vers la page d'accueil du site et est positionné sur le fichier ou le
     * dossier renommé. 
     * 
     * @param string $file
     * @param string $newName
     * 
     * @throws Exception si le fichier indiqué n'existe pas.
     * 
     */
    public function actionRename($file, $newName='')
    {
        $this->checkPath($file, 'Fichier à renommer');
    
        $path=$this->getDirectory().$file;
        
        if (! file_exists($path))
            throw new Exception("Le fichier $file n'existe pas.");

        $error='';
        if ($newName !== '')
        {
            // Vérifie qu'on n'a pas de '..'
            $this->checkPath($newName, 'Nouveau nom');
        
            if ($file !== $newName && file_exists($this->getDirectory().$newName))
                $error='Il existe déjà un fichier ou un dossier portant ce nom.';
        }
                    
        if ($newName==='' || $error !='')
        {
            Template::run
            (
                Config::get('template'),
                array('file'=>$file, 'newName'=>$newName, 'error'=>$error)
            );
            return;
        }
        
        if ($file !==$newName)
        {
            if (!rename($path, $this->getDirectory().$newName))
            {
                echo 'Le renommage a échoué';
                return;
            }
        }
                
        // Redirige vers la page d'accueil
        $this->goBack($newName);
    }

    
    /**
     * Fait une copie sous un nouveau nom d'un fichier ou d'un dossier existant.
     * 
     * - demande le nom du nouveau fichier ou du nouveau dossier,
     * - vérifie que ce nom est correct,
     * - vérifie qu'il n'existe pas déjà un fichier ou un répertoire portant
     *   ce nom,
     * - fait une copie,
     * - redirige l'utilisateur vers la page d'accueil.
     * 
     * Si le nom du fichier ou du dossier à créer n'a pas été passé en paramètre, 
     * la méthode appelle le template indiqué dans la clé <code><template></code> 
     * du fichier de configuration en lui passant en paramètre le nom du fichier 
     * ou du dossier à renommer (<code>file</code>) et une suggestion de nom
     * (<code>newName</code>) qui par défaut sera "copie de "+le nom du fichier
     * ou du dossier existant.
     * 
     * Si le nouveau nom choisi par l'utilisateur n'est pas valide ou est déjà 
     * utilisé, le même template est réaffiché, en indiquant en plus le message 
     * d'erreur obtenu (<code>error</code>).
     * 
     * Sinon, la méthode fait une copie du fichier ou du dossier. Si une erreur 
     * survient à ce stade (droits ou espace disque insuffisants par exemple), 
     * celle-ci est simplement affichée. Dans le cas contraire, l'utilisateur 
     * est redirigé vers la page d'accueil du site et est positionné sur le 
     * fichier ou le dossier créé. 
     * 
     * @param string $file
     * @param string $newName
     * 
     * @throws Exception si le fichier indiqué n'existe pas.
     */
    public function actionCopy($file, $newName='')
    {
        $this->checkPath($file, 'Fichier à copier');
        
        $path = $this->getDirectory().$file;
        
        if (! file_exists($path))
            throw new Exception("Le fichier $file n'existe pas.");

        $error='';
        if ($newName!=='')
        { 
            // Vérifie qu'on n'a pas de '..'
            $this->checkPath($newName, 'Nouveau nom');
        
            if ($file !==$newName && file_exists($this->getDirectory().$newName))
                $error='Il existe déjà un fichier portant ce nom.';
        }
        
        if ($newName==='' || $error !='')
        {
            if ($newName==='') $newName='copie de '.$file;
            Template::run
            (
                Config::get('template'),
                array('file'=>$file, 'newName'=>$newName, 'error'=>$error)
            );
            return;
        }
        
        if ($file !==$newName)
        {
            if (!$this->copyr($path, $this->getDirectory().$newName))
            {
                echo 'La copie a échoué';
                return;
            }
        }
                
        // Redirige vers la page d'accueil
        $this->goBack($newName);
    }

    
    /**
     * Copy a file, or recursively copy a folder and its contents
     *
     * @author      Aidan Lister {@link aidan@php.net}
     * @version     1.0.1
     * @link        http://aidanlister.com/repos/v/function.copyr.php
     * @param       string   $source    Source path
     * @param       string   $dest      Destination path
     * @return      bool     Returns TRUE on success, FALSE on failure
     */
    private function copyr($source, $dest)
    {
        // Simple copy for a file
        if (is_file($source)) {
            return copy($source, $dest);
        }
     
        // Make destination directory
        if (!is_dir($dest)) {
            mkdir($dest);
        }
     
        // Loop through the folder
        $dir = dir($source);
        while (false !== $entry = $dir->read()) {
            // Skip pointers
            if ($entry == '.' || $entry == '..') {
                continue;
            }
     
            // Deep copy directories
            if ($dest !== "$source/$entry") {
                $this->copyr("$source/$entry", "$dest/$entry");
            }
        }
     
        // Clean up
        $dir->close();
        return true;
    }    

    
    /**
     * Copie un fichier à partir d'un autre répertoire.
     * 
     * Cette méthode fonctionne comme {@link actionCopy() l'action Copy} si 
     * ce n'est que le fichier à copier doit être fournit sous la forme d'un
     * path complet.
     * 
     * @param string $file
     * @param string $newName
     * 
     * @todo L'action {@link actionCopy Copy} devrait savoir à la fois copier un 
     * fichier local et un fichier extérieur à l'arborescence du répertoire de 
     * travail.
     * 
     * A l'avenir cette méthode sera supprimée, il ne restera que l'action Copy.
     * 
     * @throws Exception si le fichier indiqué n'existe pas.
     */
    public function actionCopyFrom($file, $newName='')
    {
        if (! file_exists($file))
            throw new Exception("Le fichier $file n'existe pas.");

        $error='';
        if ($newName!=='' && file_exists($this->getDirectory().$newName))
            $error='Il existe déjà un fichier portant ce nom.';
                    
        if ($newName==='' || $error !='')
        {
            Template::run
            (
                Config::get('template'),
                array('file'=>$file, 'newName'=>$newName, 'error'=>$error)
            );
            return;
        }
        
        if ($file !==$newName)
        {
            if (!copy($file, $this->getDirectory().$newName))
            {
                echo 'La copie a échoué';
                return;
            }
        }
                
        // Redirige vers la page d'accueil
        $this->goBack($newName);
    }
    
    
    /**
     * Supprime définitivement un fichier ou un dossier existant.
     * 
     * - vérifie que le fichier ou le dossier demandé existe
     * - demande confirmation à l'utilisateur
     * - fait le fichier ou le dossier,
     * - redirige l'utilisateur vers la page d'accueil.
     * 
     * La demande de confirmation consiste à exécuter le template indiqué dans 
     * la clé <code><template></code> du fichier de configuration en lui passant 
     * en paramètre le nom du fichier ou du dossier à supprimer 
     * (<code>file</code>).
     * 
     * Ce template doit ré-appeller l'action Delete avec 
     * <code>confirm</code> à <code>true</code>.
     * 
     * Sinon, la méthode procède alors à la destruction du fichier ou du dossier.
     * 
     * Si une erreur survient à ce stade celle-ci est simplement affichée.
     * 
     * Dans le cas contraire, l'utilisateur est redirigé vers la page d'accueil 
     * du site. 
     * 
     * @param string $file
     * @param bool $confirm
     * 
     * @throws Exception si le fichier indiqué n'existe pas.
     */
    public function actionDelete($file, $confirm=false)
    {
        $this->checkPath($file, 'Fichier à supprimer');
        
        if (! file_exists($this->getDirectory().$file))
            throw new Exception("Le fichier $file n'existe pas.");

        if (! $confirm)
        {
            Template::run
            (
                Config::get('template'),
                array('file'=>$file)
            );
            return;
        }
        
        if (!$this->delete($this->getDirectory().$file))
        {
            echo 'La suppression a échoué';
            return;
        }
                
        // Redirige vers la page d'accueil
        $this->goBack();
    }

    
    /**
     * Delete a file, or a folder and its contents
     *
     * @author      Aidan Lister {@link aidan@php.net}
     * @version     1.0.3
     * @link        http://aidanlister.com/repos/v/function.rmdirr.php
     * @param       string   $dirname    Directory to delete
     * @return      bool     Returns TRUE on success, FALSE on failure
     */
    private function delete($dirname)
    {
        // Sanity check
        if (!file_exists($dirname)) {
            return false;
        }
     
        // Simple delete for a file
        if (is_file($dirname) || is_link($dirname)) {
            return unlink($dirname);
        }
     
        // Loop through the folder
        $dir = dir($dirname);
        while (false !== $entry = $dir->read()) {
            // Skip pointers
            if ($entry == '.' || $entry == '..') {
                continue;
            }
     
            // Recurse
            $this->delete($dirname . DIRECTORY_SEPARATOR . $entry);
        }
     
        // Clean up
        $dir->close();
        return rmdir($dirname);
    }    
    
    
    /**
     * Télécharge un fichier.
     * 
     * La méthode vérifie que le nom de fichier indiqué existe et est valide
     * puis transmet le contenu du fichier en définissant les entêtes http
     * ({@link http://www.ietf.org/rfc/rfc1049.txt content-type} et 
     * {@link http://www.ietf.org/rfc/rfc1806.txt content-disposition}) de 
     * manière à ce que le navigateur propose à l'utilisateur d'enregistrer 
     * ou d'ouvrir le fichier.
     * 
     * Le type mime du fichier retourné dans les entêtes http est déterminé
     * en utilisant la fonction {Utils::mimeType()}.
     * 
     * @param string $file
     * 
     * @throws Exception si le fichier indiqué n'existe pas.
     */
    public function actionDownload($file)
    {
        $this->checkPath($file, 'Fichier à télécharger');
        if (! file_exists($this->getDirectory().$file))
            throw new Exception("Le fichier $file n'existe pas.");
        header('content-type: '.Utils::mimeType($file));
        header('content-disposition: attachment; filename="'.$file.'"');
        readfile($this->getDirectory().$file);
    }

    
    /**
     * Charge le fichier indiqué dans l'éditeur de code source.
     *
     * La méthode vérifie que le fichier est correct puis exécute le template
     * indiqué dans la clé <code><template></code> du fichier de configuration
     * en lui passant le nom du fichier édité (<code>file</code>) et son 
     * contenu (<code>content</code>).
     * 
     * Actuellement, l'éditeur de code ne sait gérer que des fichiers encodés
     * en ISO-8859-1. Si la clé <code><utf8></code> du fichier de configuration 
     * est à <code>true</code> (ce qui signifie "les fichiers du répertoire de
     * travail sont encodés en utf-8"), le contenu du fichier sera ré-encodé en 
     * ISO-8859-1 avant d'être fournit au template (la fonction 
     * {@link http://php.net/utf8_decode utf8_decode()} de php est utilisée.
     * 
     * @param string $file
     * 
     * @throws Exception si le fichier indiqué n'existe pas.
     */
    public function actionEdit($file)
    {
        $this->checkPath($file, 'Fichier à modifier');
        
        // Vérifie que le fichier indiqué existe
        $path=$this->getDirectory().$file;
        if (! file_exists($path))
            throw new Exception("Le fichier $file n'existe pas.");
        
        // Charge le fichier
        $content=file_get_contents($path);

        // Décode l'utf8 si demandé dans la config
        if (Config::get('utf8'))
            $content=utf8_decode($content);
            
        // Charge le fichier dans l'éditeur
        Template::run
        (
            Config::get('template'),
            array
            (
                'file'=>$file,
                'content'=>$content,
            )
        );
    }

    /**
     * Sauvegarde le contenu d'un fichier modifié dans 
     * {@link actionEdit() l'éditeur de code}.
     *
     * La méthode vérifie que le fichier indiqué existe puis sauvegarde le 
     * contenu passé en paramètre dans ce fichier.
     * 
     * Si la clé <code><utf8></code> du fichier de configuration est à 
     * <code>true</code> (ce qui signifie "les fichiers du répertoire de
     * travail sont encodés en utf-8"), le contenu du fichier sera encodé en 
     * utf-8 avant d'être sauvegardé (la fonction 
     * {@link http://php.net/utf8_encode utf8_encode()} de php est utilisée.
     * 
     * @param string $file
     * @param string $content
     * 
     * @throws Exception si le fichier indiqué n'existe pas.
     */
    public function actionSave($file, $content)
    {
        $this->checkPath($file, 'Fichier à modifier');
    
        // Vérifie que le fichier indiqué existe
        $path=$this->getDirectory().$file;
        if (! file_exists($path))
            throw new Exception("Le fichier $file n'existe pas.");
        
        // Encode l'utf8 si demandé dans la config
        if (Config::get('utf8'))
            $content=utf8_encode($content);
        
        // Sauvegarde le fichier
        file_put_contents($path, $content);
        
        // Redirige vers la page d'accueil
        $this->goBack($file);
    }
}
?>