<?php
/**Author : Xavier TRABET Tous droits reserves 2012**/
header("Content-type: application/xml");
set_time_limit(0);
ignore_user_abort(1);

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
//equivalent de file_gets_content avec un curl + rapide & plus propre
function RecupUrl($url) {
$ch = curl_init();
curl_setopt($ch, CURLOPT_HEADER, 0);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
curl_setopt($ch, CURLOPT_URL, $url);
$data = curl_exec($ch);
curl_close($ch);

return $data;
      }


function exception_handler($exception) {
    // Ouvrir le fichier de log en mode append
    $logfile = fopen('exceptions.log', 'a+');
    // Ecrire l'exception dans le fichier de log
    fwrite($logfile, date('Y-m-d H:i:s') . " - " . $exception->__toString() . "\n");
    // Fermer le fichier de log
    fclose($logfile);
}

// Définir la fonction personnalisée comme gestionnaire d'exceptions
set_exception_handler('exception_handler');

// Set flag that this is a parent file.
define('_JEXEC', 1);
define('DS', DIRECTORY_SEPARATOR);

if (file_exists(dirname(__FILE__) . '/defines.php')) {
	include_once dirname(__FILE__) . '/defines.php';
}

if (!defined('_JDEFINES')) {
	define('JPATH_BASE', dirname(__FILE__));
	require_once JPATH_BASE.'/includes/defines.php';
}

require_once JPATH_BASE.'/includes/framework.php';

require_once "./Spstools.php";
require_once "./Exceltools.php";

// Mark afterLoad in the profiler.
JDEBUG ? $_PROFILER->mark('afterLoad') : null;

// Instantiate the application.
$app = JFactory::getApplication('site');

// Initialise the application.
$app->initialise();

// Mark afterIntialise in the profiler.
JDEBUG ? $_PROFILER->mark('afterInitialise') : null;
sleep(3);
$time = date(DATE_ATOM); //Horodatage

$filename = 'semsync.txt';
if (file_exists($filename)) {
    $datesem = date('Y-m-d H:i:s', (filemtime($filename)));
    $datesem2 = new DateTime($datesem);

    $nowscript = new DateTime() ;
    $datesem2->add(new DateInterval('PT01H30M'));
    //echo 'datesem='.$datesem2->format('Y-m-d H:i:sP').' et nowscript='.$nowscript->format('Y-m-d H:i:sP');
    if ($nowscript >$datesem2)
    {
        unlink($filename);
    }
}

//Open a new or get an existing semaphore
if(!file_exists('semsync.txt')) {
    $SEM = fopen('semsync.txt', 'w');
} else {
    $xml = new DOMDocument ("1.0", "UTF-8");
    $receive = $xml->createElement("SPS");
    $newreceive = $xml->appendChild($receive);
    $statusel = $xml->createElement("status", "ok");
    $newreceive->appendChild($statusel);
    $message = $xml->createElement("response","synchro deja en cours");
    $newreceive->appendChild($message);
    echo $xml->saveXML();
    exit;
}

//$arrivingfiledir = /*"/home/xavier/test";//*/"/home/drne16/uploads/uat";
$arrivingfiledir=Spstools::loadMetaValueFromKey('IncomingPlanningFileDirectory');

//start by processing tagged files on previous round
$files1 = scandir(JPATH_BASE."/synchro",1);
$first=true;
foreach($files1 as $fileToImport) {
    if(substr($fileToImport,0,1)!='.' && substr($fileToImport,0,3)!='old' && substr($fileToImport,0,13)!='verifsync.txt') {
        $content = file_get_contents($arrivingfiledir.'/'.$fileToImport);
        //replace 0d0a by ' '
        $content2 = str_replace(''.chr(0x0d).chr(0x0a),'',$content);
        $filedest=JPATH_BASE."/synchro/".$fileToImport;
        file_put_contents($filedest, $content2);
       // try{
            unlink($arrivingfiledir.'/'.$fileToImport);
            if($first){Exceltools::processSyncCSVFormat1File($filedest);$first=false;}
       // } catch (Exception $e){
       //     var_dump($e);
       // }
        rename($filedest, JPATH_BASE."/synchro/old/".$fileToImport);
    }
}
//at the end, detect files for next call
$files1 = scandir($arrivingfiledir);

foreach($files1 as $fileToImport) {
    if(substr($fileToImport,0,1)!='.') {
        file_put_contents(JPATH_BASE."/synchro/".$fileToImport, 'next');
    }
}
fclose($SEM);
unlink('semsync.txt');
 ?>




