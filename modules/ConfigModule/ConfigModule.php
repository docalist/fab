<?php
/*
 * Created on 1 juin 06
 *
 * To change the template for this generated file go to
 * Window - Preferences - PHPeclipse - PHP - Code Templates
 */
class ConfigModule extends Module
{
    public function preExecute()
    {
        throw new Exception('Ne peut pas fonctionner avec la nouvelle config xml');	
        User::checkAccess('Admin');
    }
    
    // liste les fichiers de config disponibles
	public function actionIndex($error='')
    {
        global $files;
        
        $files=array();
        
        $path='config' . DIRECTORY_SEPARATOR;
        
        $files[$path . 'general.yaml']='Param�tres g�n�raux';
        $files[$path . 'routes.yaml']='R�gles de routage';
        $files[$path . 'config.php']='Configuration du cache';
        $files[$path . 'test.yaml']='Test/divers';
        
        Template::run('list.html', array('error'=>$error, 'files'=>$files));
    }
    
    public function actionEdit()
    {
        if (! $file=Utils::get($_REQUEST['file']))
        {
            $this->index("Vous n'avez pas choisi le fichier � modifier");
            return; 
        }
        $path=Runtime::$root . $file;
        if (file_exists($path))
            $data=file_get_contents($path);
        else switch(Utils::getExtension($file))
        {
        	case '.yaml':
                $data ="# Fichier de configuration $file\n";
                $data.="# Ce fichier est au format YAML, pensez � utiliser correctement les espaces pour l'indentation\n\n";
                $data.="\n";
                $data.="groupe1:\n";
                $data.="  cl�1: 'valeur 1'\n";
                $data.="  cl�2: 'valeur 2'\n";
                $data.="\n";
                $data.="groupe2:\n";
                $data.="  cl�3: 'valeur 3'\n";
                $data.="  cl�4: 'valeur 4'\n";
                $data.="\n";
                $data.="\n";
                break;
            case '.php':
                $data ="<?php\n";
                $data.="Config::addArray\n";
                $data.="(\n";
                $data.="    array\n";
                $data.="    (\n";
                $data.="        // Param�tres du cache\n";
                $data.="        'cache'=>array\n";
                $data.="        (\n";
                $data.="            // Indique si on autorise ou non le cache (true/false)\n";
                $data.="            'enabled'   => true,\n";
                $data.="            \n";
                $data.="            // Path du r�pertoire dans lequel seront stock�s les fichiers mis\n";
                $data.="            // en cache. Il peut s'agir d'un chemin absolu (c:/temp/cache/) ou\n";
                $data.="            // d'un chemin relatif � la racine de l'application (\$root)\n";
                $data.="            'path'      => 'cache'\n";
                $data.="        )\n";
                $data.="    )\n";
                $data.=");\n";
                $data.="?>\n";
                break;
            default:echo '???' . $path . Utils::getExtension($file);
        }
        
        Template::run('edit.html', array('file'=>$file, 'data'=>$data));
    }
    
    // TODO: pas secure : � v�rifier (param�tres attendus pr�sents, pas d'autres file que ceux autoris�s...)
    public function actionSave()
    {
    	$data=$_REQUEST['data'];
        $file=$_REQUEST['file'];

        $path=Runtime::$root . $file;
        file_put_contents("$path.tmp", $data);
        if (file_exists($path))
        {
            @unlink("$path.bak");
            rename($path, "$path.bak");
        }
        rename("$path.tmp",$path);
        echo "<p>Le fichier '$file' a �t� enregistr� (une sauvegarde du fichier existant a �t� faite dans '$path.bak').</p>";
    }
}
?>
