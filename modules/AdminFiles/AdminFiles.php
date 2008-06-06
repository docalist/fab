<?php

/**
 * @package     fab
 * @subpackage  Admin
 * @author      Daniel M�nard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id$
 */

/**
 * Module d'administration abstrait permettant de g�rer des fichiers
 * au sein d'un r�pertoire (renommer, copier, supprimer...)
 * 
 * Ce module est une classe abstraite : il n'est pas destin� � �tre 
 * appell� directement par l'utilisateur. Il permet � un module 
 * d'administration, simplement en d�rivant de <code>AdminFiles</code> au 
 * lieu de d�river de <code>Admin</code>, de disposer des actions n�cessaires 
 * � la gestion d'une liste de fichiers.
 * 
 * @package     fab
 * @subpackage  Admin
 */
class AdminFiles extends Admin
{
    /**
     * Retourne le path du r�pertoire sur lequel va travailler le module.
     * 
     * Par d�faut, retourne le path indiqu� dans la cl� <code>directory</code>
     * de la configuration.
     *
     * @throws Exception si le path n'existe pas
     * @return path
     */
    public function getDirectory()
    {
        $path=Config::get('directory');
        $path=str_replace
        (
            array('$fab', '$app'),
            array(Runtime::$fabRoot, Runtime::$root),
            $path
        );
        if (Utils::isRelativePath($path))
            $path=Utils::searchFile($path);
            
        if ($path==='' || $path===false || false===$path=realpath($path))
            throw new Exception('Impossible de trouver le r�pertoire indiqu� dans la config.');
                
        if (!is_dir($path))
            throw new Exception('Le path indiqu� dans la config ne d�signe pas un r�pertoire.');
        
        $path.= DIRECTORY_SEPARATOR;
        
        return $path;
    }
    
    /**
     * Retourne la liste des fichiers pr�sents dans le r�pertoire.
     * 
     * @return array
     */
    public function getFiles()
    {
        $files=array();

        foreach(glob($this->getDirectory() . '*') as $path)
            if (is_file($path)) $files[$path]=basename($path);
//        foreach(glob($this->getDirectory() . '*', GLOB_ONLYDIR) as $path)
//            $files[$path]=basename($path);
        return $files;
    }
    
    public function getFileIcon($file)
    {
        $icon='/FabWeb/modules/AdminFiles/images/filetypes/';
        
        switch(strtolower(Utils::getExtension($file)))
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
    /**
     * Page d'accueil : liste tous les fichiers pr�sents dans le
     * r�pertoire sp�cifi� dans la config.
     * 
     * Affiche la liste des fichiers disponibles.
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
     * Cr�e un nouveau fichier.
     * 
     * Demande le nom du fichier � cr�er, v�rifie qu'il n'existe pas, cr�e le 
     * fichier et redirige vers la page d'accueil.
     */
    public function actionNew($file='')
    {
        $error='';
        // V�rifie que le fichier indiqu� n'existe pas d�j�
        if ($file !== '')
        {
            if (file_exists($this->getDirectory().$file))
                $error="Le fichier $file existe d�j�.";
        }
        
        // Demande le nom du fichier � cr�er
        if ($file==='' || $error !='')
        {
            Template::run
            (
                Config::get('template'),
                array('file'=>$file, 'error'=>$error)
            );
            return;
        }
        
        // Cr�e le fichier
        if (! $f=fopen($this->getDirectory().$file,'w'))
        {
            echo 'La cr�ation du fichier a �chou�.';
            return;
        }
        fclose($f);
        
        // Redirige vers la page d'accueil
        Runtime::redirect('/'.$this->module);
    }
    

    
    /**
     * Renomme un fichier.
     * 
     * V�rifie que le fichier indiqu� existe, demande le nouveau nom
     * du fichier, v�rifie que ce nom n'est pas d�j� pris, renomme le
     * fichier, redirige vers la page d'accueil.
     */
    public function actionRename($file, $newName='')
    {
        if (! file_exists($this->getDirectory().$file))
            throw new Exception("Le fichier $file n'existe pas.");

        $error='';
        if ($newName!=='' && $file !==$newName && file_exists($this->getDirectory().$newName))
            $error='Il existe d�j� un fichier portant ce nom.';
                    
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
            if (!rename($this->getDirectory().$file, $this->getDirectory().$newName))
            {
                echo 'Le renommage a �chou�';
                return;
            }
        }
                
        Runtime::redirect('/'.$this->module);
    }
    
    /**
     * Copie un fichier.
     * 
     * V�rifie que le fichier indiqu� existe, demande le nouveau nom
     * du fichier, v�rifie que ce nom n'est pas d�j� pris, copie le
     * fichier, redirige vers la page d'accueil.
     */
    public function actionCopy($file, $newName='')
    {
        if (! file_exists($this->getDirectory().$file))
            throw new Exception("Le fichier $file n'existe pas.");

        $error='';
        if ($newName!=='' && $file !==$newName && file_exists($this->getDirectory().$newName))
            $error='Il existe d�j� un fichier portant ce nom.';

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
            if (!copy($this->getDirectory().$file, $this->getDirectory().$newName))
            {
                echo 'La copie a �chou�';
                return;
            }
        }
                
        Runtime::redirect('/'.$this->module);
    }

    /**
     * Copie un fichier � partir d'un autre r�pertoire.
     * 
     * V�rifie que le fichier indiqu� existe, demande le nouveau nom
     * du fichier, v�rifie que ce nom n'est pas d�j� pris, copie le
     * fichier, redirige vers la page d'accueil.
     */
    public function actionCopyFrom($file, $newName='')
    {
        if (! file_exists($file))
            throw new Exception("Le fichier $file n'existe pas.");

        $error='';
        if ($newName!=='' && file_exists($this->getDirectory().$newName))
            $error='Il existe d�j� un fichier portant ce nom.';
                    
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
                echo 'La copie a �chou�';
                return;
            }
        }
                
        Runtime::redirect('/'.$this->module);
    }
    
    /**
     * Supprime un fichier.
     * 
     * V�rifie que le fichier indiqu� existe, demande confirmation,
     * supprime le fichier, redirige vers la page d'accueil.
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
        
        if (!unlink($this->getDirectory().$file))
        {
            echo 'La suppression a �chou�';
            return;
        }
                
        Runtime::redirect('/'.$this->module);
    }
    
    /**
     * T�l�charge un fichier. 
     */
    public function actionDownload($file)
    {
        if (! file_exists($this->getDirectory().$file))
            throw new Exception("Le fichier $file n'existe pas.");
        header('content-type: '.Utils::mimeType($file));
        header('content-disposition: attachment; filename="'.$file.'"');
        readfile($this->getDirectory().$file);
    }

    public function actionEdit($file)
    {
        // V�rifie que le fichier indiqu� existe
        $path=$this->getDirectory().$file;
        if (! file_exists($path))
            throw new Exception("Le fichier $file n'existe pas.");
        
        // Charge le fichier
        $content=file_get_contents($path);

        // D�code l'utf8 si demand� dans la config
        if (Config::get('utf8'))
            $content=utf8_decode($content);
            
        // Charge le fichier dans l'�diteur
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

    public function actionSave($file, $content)
    {
        // V�rifie que le fichier indiqu� existe
        $path=$this->getDirectory().$file;
        if (! file_exists($path))
            throw new Exception("Le fichier $file n'existe pas.");
        
        // Encode l'utf8 si demand� dans la config
        if (Config::get('utf8'))
            $content=utf8_encode($content);
        
        // Sauvegarde le fichier
        file_put_contents($path, $content);
        
        Runtime::redirect('/'.$this->module);
    }
}
?>