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
        
        $files[$path . 'general.yaml']='Paramètres généraux';
        $files[$path . 'routes.yaml']='Règles de routage';
        $files[$path . 'config.php']='Configuration du cache';
        $files[$path . 'test.yaml']='Test/divers';
        
        Template::run('list.html', array('error'=>$error, 'files'=>$files));
    }
    
    public function actionEdit()
    {
        if (! $file=Utils::get($_REQUEST['file']))
        {
            $this->index("Vous n'avez pas choisi le fichier à modifier");
            return; 
        }
        $path=Runtime::$root . $file;
        if (file_exists($path))
            $data=file_get_contents($path);
        else switch(Utils::getExtension($file))
        {
        	case '.yaml':
                $data ="# Fichier de configuration $file\n";
                $data.="# Ce fichier est au format YAML, pensez à utiliser correctement les espaces pour l'indentation\n\n";
                $data.="\n";
                $data.="groupe1:\n";
                $data.="  clé1: 'valeur 1'\n";
                $data.="  clé2: 'valeur 2'\n";
                $data.="\n";
                $data.="groupe2:\n";
                $data.="  clé3: 'valeur 3'\n";
                $data.="  clé4: 'valeur 4'\n";
                $data.="\n";
                $data.="\n";
                break;
            case '.php':
                $data ="<?php\n";
                $data.="Config::addArray\n";
                $data.="(\n";
                $data.="    array\n";
                $data.="    (\n";
                $data.="        // Paramètres du cache\n";
                $data.="        'cache'=>array\n";
                $data.="        (\n";
                $data.="            // Indique si on autorise ou non le cache (true/false)\n";
                $data.="            'enabled'   => true,\n";
                $data.="            \n";
                $data.="            // Path du répertoire dans lequel seront stockés les fichiers mis\n";
                $data.="            // en cache. Il peut s'agir d'un chemin absolu (c:/temp/cache/) ou\n";
                $data.="            // d'un chemin relatif à la racine de l'application (\$root)\n";
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
    
    // TODO: pas secure : à vérifier (paramètres attendus présents, pas d'autres file que ceux autorisés...)
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
        echo "<p>Le fichier '$file' a été enregistré (une sauvegarde du fichier existant a été faite dans '$path.bak').</p>";
    }
}
?>
