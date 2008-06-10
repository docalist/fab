<?php

/**
 * @package     fab
 * @subpackage  Admin
 * @author      Daniel Ménard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id$
 */

/**
 * Module d'administration abstrait permettant de gérer les fichiers
 * et les dossiers présents au sein d'un répertoire donné.
 * 
 * Le module offre des fonctions permettant de créer/renommer/modifier/supprimer
 * les fichiers et les dossiers.
 * 
 * Ce module peut être utilisé directement ou servir de classe ancêtre à un
 * module ayant besoin d'offrir des actions de manipulation de fichiers et 
 * de répertoires.
 * 
 * @package     fab
 * @subpackage  Admin
 */
class AdminFiles extends Admin
{
    /**
     * Retourne le path du répertoire sur lequel va travailler le module.
     * 
     * Le path retourné est construit à partir des élements suivants :
     * - le répertoire racine de l'application ({@link Runtime::$root})
     * - le répertoire indiqué dans la clé <code>directory</code> de la config
     * - le répertoire éventuel indiqué dans le paramètre <code>directory</code>
     *   indiqué en query string
     * 
     * Une exception est générée si le répertoire obtenu n'existe pas ou ne
     * désigne pas un répertoire.
     * 
     * Le chemin retourné contient toujours un slash final.
     *
     * @throws Exception si le path n'existe pas.
     * @return string le path obtenu.
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
        
        // Vérifie que c'est un répertoire et que celui-ci existe
        if (!is_dir($path))
            throw new Exception("Le répertoire indiqué n'existe pas.");
            
        // Retourne le résultat
        return $path;
    }

    
    /**
     * Retourne le répertoire parent du répertoire de travail tel que
     * retourné par {@link getDirectory()}.
     * 
     * @return false|string la fonction retourne le nom du répertoire parent,
     * une chaine vide (pour indiquer le répertoire de travail) ou le
     * booléen false sin on est déjà à la racine.
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
     * répertoire.
     * 
     * Le tableau obtenu liste en premier les fichiers (triés par ordre 
     * alphabétique) puis les dossiers (également triés).
     * 
     * Les clés des entrées du tableau contiennent le path complet 
     * du fichier ou du dossier, la valeur associée contient le nom (basename).
     * 
     * @return array
     */
    public function getFiles()
    {
        $files=array();
        $dirs=array();
        
        $baseDir=$this->request->get('directory', '');
        if ($baseDir !== '') $baseDir.='/';
        foreach(glob($this->getDirectory() . '*') as $path)
        {
            if (is_file($path) || is_link($path))
                $files[$path]=basename($path);
            else
                $dirs[$path]=basename($path);
        }
        ksort($files, SORT_LOCALE_STRING);
        ksort($dirs, SORT_LOCALE_STRING);
        return $files + $dirs;
    }
    
    /**
     * Retourne le path d'une icone pour le fichier ou le dossier passé en
     * paramètre.
     *
     * @param string $path
     * @return string
     */
    public function getFileIcon($path)
    {
        $icon='/FabWeb/modules/AdminFiles/images/filetypes/';
        if (is_dir($path))
            return $icon . 'folder.png';

        switch(strtolower(Utils::getExtension($path)))
        {
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
        return $icon;
    }
    
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
     * éventuellement une ancre sur laquelle positionner la page.
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
     * éventuellement une ancre sur laquelle positionner la page.
     * 
     * @param string $file le nom d'un fichier à utiliser comme ancre. 
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
     * Page d'accueil : liste tous les fichiers et tous les dossiers
     * présents dans le répertoire de travail.
     */
    public function actionIndex()
    {
        Template::run
        (
            Config::get('template'),
            array('files'=>self::getFiles())
        );
    }


    /**
     * Crée un nouveau fichier.
     * 
     * Demande le nom du fichier à créer, vérifie qu'il n'existe pas, crée le 
     * fichier et redirige l'utilisateur vers la page d'accueil.
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
     * Demande le nom du dossier à créer, vérifie qu'il n'existe pas, crée le 
     * dossier et redirige l'utilisateur vers la page d'accueil.
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
     * Renomme un fichier ou un dossier.
     * 
     * Vérifie que le fichier ou le dossier indiqué existe, demande le nouveau 
     * nom, vérifie que ce nom n'est pas déjà pris, renomme le fichier ou le
     * dossier, redirige l'utilisateur vers la page d'accueil.
     * 
     * @param string $file
     * @param string $newName
     */
    public function actionRename($file, $newName='')
    {
        $path=$this->getDirectory().$file;
        
        if (! file_exists($path))
            throw new Exception("Le fichier $file n'existe pas.");

        $error='';
        if ($newName!=='' && $file !==$newName && file_exists($this->getDirectory().$newName))
            $error='Il existe déjà un fichier ou un dossier portant ce nom.';
                    
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
     * Copie un fichier ou un dossier.
     * 
     * Vérifie que le fichier ou le dossier indiqué existe, demande le nouveau 
     * nom, vérifie que ce nom n'est pas déjà pris, copie le fichier ou le 
     * dossier, redirige l'utilisateur vers la page d'accueil.
     * 
     * @param string $file
     * @param string $newName
     */
    public function actionCopy($file, $newName='')
    {
        $path = $this->getDirectory().$file;
        
        if (! file_exists($path))
            throw new Exception("Le fichier $file n'existe pas.");

        $error='';
        if ($newName!=='' && $file !==$newName && file_exists($this->getDirectory().$newName))
            $error='Il existe déjà un fichier portant ce nom.';

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
     * @author      Aidan Lister <aidan@php.net>
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
     * Vérifie que le fichier indiqué existe, demande le nouveau nom
     * du fichier, vérifie que ce nom n'est pas déjà pris, copie le
     * fichier, redirige vers la page d'accueil.
     * 
     * @param string $file
     * @param string $newName
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
     * Supprime un fichier ou un répertoire.
     * 
     * Vérifie que le fichier ou le dossier indiqué existe, demande confirmation,
     * supprime le fichier ou le dossier, redirige l'utilisateur vers la page 
     * d'accueil.
     * 
     * @param string $file
     * @param bool $confirm
     */
    public function actionDelete($file, $confirm=false)
    {
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
     * @author      Aidan Lister <aidan@php.net>
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
     * @param string $file 
     */
    public function actionDownload($file)
    {
        if (! file_exists($this->getDirectory().$file))
            throw new Exception("Le fichier $file n'existe pas.");
        header('content-type: '.Utils::mimeType($file));
        header('content-disposition: attachment; filename="'.$file.'"');
        readfile($this->getDirectory().$file);
    }

    
    /**
     * Charge le fichier indiqué dans l'éditeur de code source.
     *
     * @param string $file
     */
    public function actionEdit($file)
    {
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
     * Sauvegarde le fichier indiqué
     *
     * @param string $file
     * @param string $content
     */
    public function actionSave($file, $content)
    {
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