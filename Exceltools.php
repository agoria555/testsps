<?php
/**
 * Created by
 * User: xavier TRABET
 * Date: 23/05/13
 * Time: 18:40
 *
 */

include_once './sps_parametrage.php';
include_once('./Spstools.php');
include_once './smsbox_src/apixtrabet.php';
// Import PHPExcel library
jimport('phpexcel.library.PHPExcel');
/** Include path **/
set_include_path(get_include_path() . PATH_SEPARATOR . 'libraries/phpexcel/library/PHPExcel/');

/** PHPExcel_IOFactory */
include 'libraries/phpexcel/library/PHPExcel/IOFactory.php';

class MyReadFilter implements PHPExcel_Reader_IReadFilter
{
    private $_startRow = 0;

    private $_endRow = 0;

    private $_columns = array();

    public function __construct($startRow, $endRow, $columns) {
        $this->_startRow	= $startRow;
        $this->_endRow		= $endRow;
        $this->_columns		= $columns;
    }

    public function readCell($column, $row, $worksheetName = '') {
        if ($row >= $this->_startRow && $row <= $this->_endRow) {
            if (in_array($column,$this->_columns)) {
                return true;
            }
        }
        return false;
    }
}

class Exceltools
{


    public static $notification_actif;




    public
    static function processSMSFile($form)
    {

        $db=& JFactory::getDBO();
        $filterSubset = new MyReadFilter(1,1000,range('A','B'));
        $inputFileName=$form->files['file']['path'];


        //echo 'Loading file '.pathinfo($inputFileName,PATHINFO_BASENAME).'<br />';
        $objReader = PHPExcel_IOFactory::createReaderForFile($inputFileName);
        //echo 'Loading Sheet using filter<br />';
        $objReader->setReadFilter($filterSubset);
        $objPHPExcel = $objReader->load($inputFileName);




        //$sheetData = $objPHPExcel->getActiveSheet()->toArray(null,true,true,true);
        $clientid=Spstools::getUserClientId($db);
        if ($clientid=='') $clientid=null;

        foreach ($objPHPExcel->getActiveSheet()->getRowIterator() as $row) {

            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(true);
            $sms=array();
            $i=0;
            foreach ($cellIterator as $cell) {
                $sms[$i]=addslashes($cell->getValue());
                $i++;
            }

            //send sms if phone ok
            //check number
            preg_match(Spstools::checkphoneNumber_regexp(), $sms[0], $matches);
//limit also message min and max size from 1 to 1600 characters
                if (strlen($matches[0]) > 0 && strlen($sms[1])>0 && strlen($sms[1]<160*10)) {

//notify
            $sessionNotif_id=self::smsBoxNotify($sms,$form->data['date'],substr($form->data['heure'],0,5));

                    //insert  sentcalls
            $query = "insert into #__sentcalls (numDestinataire,dateheure,typeappel,client_id,sessionNotif_id) values ('".$sms[0]."',now(),'envoi tableur','".$clientid."','".$sessionNotif_id."')";
            $db->setQuery($query);
            $query = $db->execute();
            } else {
                    //incorrect number...
                }
        }
    }

    /**import appelé depuis l'écran chronoforms utilisateurs*/
    public
    static function importUtilisateur($form)
{
/**A=NOM
 * B=Prenom
 * C=mail
 * D=Profil
 * E=Depots
 */
    $db=& JFactory::getDBO();
    $filterSubset = new MyReadFilter(2,1000,range('A','E'));
    $inputFileName=$form->files['file']['path'];


    //echo 'Loading file '.pathinfo($inputFileName,PATHINFO_BASENAME).'<br />';
    $objReader = PHPExcel_IOFactory::createReaderForFile($inputFileName);
    //echo 'Loading Sheet using filter<br />';
    $objReader->setReadFilter($filterSubset);
    $objPHPExcel = $objReader->load($inputFileName);




    //$sheetData = $objPHPExcel->getActiveSheet()->toArray(null,true,true,true);
    $clientid=Spstools::getUserClientId($db);
    if ($clientid=='') $clientid=null;
    $indice=0;
    $utilisateur=new JObject('data');
    
    foreach ($objPHPExcel->getActiveSheet()->getRowIterator() as $row) {
        $cellIterator = $row->getCellIterator();
        $cellIterator->setIterateOnlyExistingCells(true);
        $planning=array();
        $i=0;
        foreach ($cellIterator as $cell) {
            if($i==0){  
                $utilisateur->data['nom'] = addslashes($cell->getValue());
            }
            if($i==1){  
                $utilisateur->data['prenom'] = addslashes($cell->getValue());
            }
            if($i==2){  
                $utilisateur->data['email'] = addslashes($cell->getValue());
            }
            if($i==3){  
                $utilisateur->data['profil'] = addslashes($cell->getValue());
            }
            if($i==4){  
                $utilisateur->data['depot'] = addslashes($cell->getValue());
            }
            $i++;
            
        }

        if(strlen($utilisateur->data['nom'])>0){
            //creation utilisateur

            Spstools::CreateUser($utilisateur);
            $indice++;
        }

    }
    return $indice;



}

        public
        static function processPlanningFile($form)
    {

        $db=& JFactory::getDBO();
        $filterSubset = new MyReadFilter(2,1000,range('A','G'));
        $inputFileName=$form->files['file']['path'];


        //echo 'Loading file '.pathinfo($inputFileName,PATHINFO_BASENAME).'<br />';
        $objReader = PHPExcel_IOFactory::createReaderForFile($inputFileName);
        //echo 'Loading Sheet using filter<br />';
        $objReader->setReadFilter($filterSubset);
        $objPHPExcel = $objReader->load($inputFileName);




        //$sheetData = $objPHPExcel->getActiveSheet()->toArray(null,true,true,true);
        $clientid=Spstools::getUserClientId($db);
        if ($clientid=='') $clientid=null;
        $indice=0;

        foreach ($objPHPExcel->getActiveSheet()->getRowIterator() as $row) {

            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(true);
            $planning=array();
            $i=0;
            foreach ($cellIterator as $cell) {
                if ($i==2) $planning[$i] = PHPExcel_Style_NumberFormat::toFormattedString($cell->getCalculatedValue(), 'hh:mm:ss');
                elseif ($i==5){
                    $planning = self::checknExtractPhones($cell, $planning, $i);

                }else $planning[$i]=addslashes($cell->getValue());
                $i++;
            }

            if(strlen($planning[0])>0){
                self::insertOrReplacePlanningWithRecur($planning,$form);
                $indice++;
            }

        }
        return $indice;



    }


    public
    static function processSyncCSVFormat1File($file)
    {
        /** file format (description: example)
 A0   Filliale: KSLTB
 B1   Centre(Dépôts): 12
 C2   Date service: 2015-10-08 00:00:00.000
 D3   Heure départ (en s): 23400
 E4   arretdepart-Description: CHA.dpt
 F5   * designation
 G6   * service
 H7   Matricule: 20326
 I8   Nom: DUXXXX
 J9   Prénom: Axxxxx
 K10   NA (ignoré): NULL <<< changement TELEPHONE PRO : c'est ce numéro à contacter
 L11   Numéro(s) de téléphone: 03 83 24 34 xx / 06 20 85 29 xx
 M12   Commentaire: Circule en période scolaire de Reims
        */

        $db=& JFactory::getDBO();
        $filterSubset = new MyReadFilter(1,1000,range('A','M'));
        $inputFileName=$file;
        $inputFileType = 'CSV';
        $aujourdhui = new DateTime();

        Spstools::loadParametres($db,false);
        if (filesize($file)>10) {


        //echo 'Loading file '.pathinfo($inputFileName,PATHINFO_BASENAME).'<br />';
        $objReader = PHPExcel_IOFactory::createReader($inputFileType);
        /** @var $objReader PHPExcel_Reader_CSV */
        //echo 'Loading Sheet using filter<br />';
        $objReader->setDelimiter(';');
        $objReader->setInputEncoding('UTF-8');
        $objReader->setReadFilter($filterSubset);
        $objPHPExcel = $objReader->load($inputFileName);




        //$sheetData = $objPHPExcel->getActiveSheet()->toArray(null,true,true,true);
        $clientid=Spstools::getUserClientId($db);
        if (is_null($clientid) || $clientid=='') $clientid=Spstools::$client_id;

        $indice=0;
        $aujourdhui = new DateTime();
            //add 10 minutes
$aujourdhui->add(new DateInterval(('PT00H30M')));
        //delete futur services from now()
        //pour le planning

            try {
                $timelog = date(DATE_ATOM); //Horodatage
                $console = '';
                $console .= $timelog . ": Debut timing delete\n";
                $filename = './consolesyncksl.txt';
                $fp = fopen($filename, 'a+');
                fwrite($fp, $console);
                fclose($fp);
            } catch (Exception $e) {
            }

            $query = "select p.idplanning FROM jos_planning p
inner join jos_service s on s.idservice=p.service_idservice
inner join jos_etape e on e.service_idservice = s.idservice
where  s.recurrence is null and ((e.heure>time(DATE_ADD(now(), INTERVAL 30 MINUTE))
        and date='".$aujourdhui->format('Y-m-d')."')
        or date>'".$aujourdhui->format('Y-m-d')."')";
            $db->setQuery($query);
            $db->query();
            $num_rowsp=$db->getNumRows();
            $rResultp = $db->loadRowList();
            if ($num_rowsp > 0)
            {
                foreach ($rResultp as $aRowp)
                {
                    $query = "DELETE ep FROM jos_etape_planning ep where ep.planning_idplanning=".$aRowp[0];
                    $db->setQuery($query);
                    $db->query();
                    $query = "DELETE p FROM jos_planning p where p.idplanning=".$aRowp[0];
                    $db->setQuery($query);
                    $db->query();
                }
            }

        $query="select idservice from jos_service left join jos_planning on service_idservice = idservice where idplanning is null limit 10000";
            $db->setQuery($query);
            $db->query();
            $num_rows=$db->getNumRows();
            $rResult = $db->loadRowList();
            if ($num_rows > 0)
            {
                foreach ($rResult as $aRow)
                {
                    $query = "DELETE FROM `jos_etape` where service_idservice = ".$aRow[0];
                    $db->setQuery($query);
                    $db->query();
                    $query = "DELETE FROM `jos_service` where idservice = ".$aRow[0];
                    $db->setQuery($query);
                    $db->query();
                }
            }
            try {
                $timelog = date(DATE_ATOM); //Horodatage
                $console = '';
                $console .= $timelog . ": Fin timing delete -- debut timing Insert\n";
                $filename = './consolesyncksl.txt';
                $fp = fopen($filename, 'a+');
                fwrite($fp, $console);
                fclose($fp);
            } catch (Exception $e) {
            }

            //prepare planning filter options
            $filterActive=Spstools::loadMetaValueFromKey('FilterPlanning') === 'T'?true:false;
            if ($filterActive){
                $filterKeyword=Spstools::loadMetaValueFromKey('FilterKeyword');
            }

            $importIfFound=Spstools::loadMetaValueFromKey('FilterKeywordInsertIfFound');
        foreach ($objPHPExcel->getActiveSheet()->getRowIterator() as $row) {
            /** @var $row PHPExcel_Worksheet_Row */

            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);

            $planning=array();
            $i=0;

            foreach ($cellIterator as $cell) {
                /* @var $cell PHPExcel_Cell  */
                if ($i==2) {
                    $planning[$i] = PHPExcel_Style_NumberFormat::toFormattedString($cell->getValue(), 'hh:mm:ss');
                    $InvDate= substr($cell->getValue(),0,10);
                    if(Spstools::isValidDate($InvDate)) {
                        $planning[$i] = $InvDate;
                    }
                } elseif($i==10/*$cell->getColumn()=='L'*/) {
                    $planning = self::checknExtractPhones($cell, $planning, 10);

                    //modification cahier des charges : Mail du 12/05/2016 2eme appel sur meme numéro
                    if (isset($planning[10][0])) $planning[10][1]=$planning[10][0];


                }
                else $planning[$i]=trim(addslashes($cell->getValue()));
                $i++;
            }
            $date=new DateTime(addslashes($planning[2]));
            /**negative numbers for night services (leaving day before)*/
            if (intval($planning[3],0)<0) {
                $date->sub(new DateInterval('PT'.abs(intval($planning[3],0)).'S'));
                $planning[2]=$date->format('Y-m-d');
                $planning[3]=intval($date->format('H'))*3600+intval($date->format('i'))*60;
            }
            else $date->add(new DateInterval('PT'.abs(intval($planning[3],0)).'S'));

            if(strlen($planning[1])>0 && $date > $aujourdhui){

                // ajout filtre sur mot clé dans commentaire si filtre activé

                preg_match("/^.*" . $filterKeyword . "($|[\' \']{1,}.*){1}$/", $planning[12], $matches);

                /*attention condition inversee ou pas selon parametrage : mot cle PAS trouve on insere*/

                if(!$filterActive || (1==$importIfFound?($filterActive && strlen($matches[0]) > 0):!($filterActive && strlen($matches[0]) > 0))){
                    self::insertOrReplacePlanning($planning);
                    $indice++;
                }
            }



        }
            try {
                //last update was now (update)
                //to keep in mind that sync is ok
                $query = "UPDATE jos_parametres SET dateDerniereSynchronisationExterne=NOW()";
                $db->setQuery($query);
                //$db->query();

                $timelog = date(DATE_ATOM); //Horodatage
                $console = '';
                $console .= $timelog . ": Fin timing Insert\n";
                $filename = './consolesyncksl.txt';
                $fp = fopen($filename, 'a+');
                fwrite($fp, $console);
                fclose($fp);

            } catch (Exception $e) {
                //print_r($e);
            }
        }
    }


    public static function insertOrReplacePlanningWithRecur($tableurservices,$form)
    {
        $date_debut=$form->data['date_debut'];
        $date_fin=$form->data['date_fin'];
        $liste_idcalendrier_defaut=$form->data['liste_idcalendrier'];

        //service planning
        $db =& JFactory::getDBO();
        Spstools::loadParametres($db, false);

        $service=$tableurservices;

            //pour le service
            $libelle = trim(addslashes($service[0]).addslashes($service[1]));
            $semaineid = null;//addslashes($service["SEMAINE_ID"]);

            //on empile les semaineid pour supprimer ceux qui ne sont plus transmis
            //$tab_semaineid[$indice++]=$semaineid;
            $secteurgeo = '';//addslashes($service["SECTEURGEO"]);
            $hdep = addslashes($service[2]);
            $lieudep = '';//addslashes($service["LIEUDEP"]);
            $lieuret = '';//addslashes($service["LIEURET"]);
            $groupe = '';//addslashes($service["GROUPE"]);
            $nomgroupe = '';//addslashes($service["NOMGROUPE"]);
            $chauf = '';//addslashes($service["CHAUF"]);
            $words = '';//preg_split('/([\s\-_,:;?!\/\(\)\[\]{}<>\r\n"]|(?<!\d)\.(?!\d))/',$chauf, null, PREG_SPLIT_NO_EMPTY);
            $nom = '';//substr($words[0],0,4);
            $prenom = '';//$words[1];//substr($words[1],0,2);
            $chauf = '';//$nom.'. '.$prenom;
            $comment = '';//addslashes($service["COMMENT"]);
            $observation = '';//addslashes($service["OBSERVATION"]);
            $societe = isset($service[4]) && strlen($service[4])>0?addslashes($service[4]):'KATL';//en dur     //addslashes($service["SOCIETE"]);
            $numeroA='';$numeroB='';
            if(isset($service[5])){
                if(isset($service[5][0])) $numeroA=$service[5][0];
                if(isset($service[5][1])) $numeroB=$service[5][1];
            }
            $car = '';//addslashes($service["CAR"]);
            $recurrence='Jour';


            //process validity (LMmJVSDF) day of weeks and days off (F)
            $aujourdhui = new DateTime();
            if ($date_debut == '') $date_debut = $aujourdhui->format('Y-m-d');
            if ($date_fin == '') $date_fin = $aujourdhui->format('Y-m-d');

            $jourscoches = '';
            $lundi=''; $mardi=''; $mercredi=''; $jeudi=''; $vendredi=''; $samedi='';$dimanche=''; $ferie='';

            if ((($service[3][0]))=='L') $lundi='1';
            if ((($service[3][1]))=='M') $mardi='1';
            if ((($service[3][2]))=='M') $mercredi='1';
            if ((($service[3][3]))=='J') $jeudi='1';
            if ((($service[3][4]))=='V') $vendredi='1';
            if ((($service[3][5]))=='S') $samedi='1';
            if ((($service[3][6]))=='D') $dimanche='1';
            if ((($service[3][7]))=='F') $ferie='1';
        $liste_idcalendrier=$liste_idcalendrier_defaut;
        $date_sans_recurr='';

            //modification pour prise en compte de la colonne 4 en format date
            if ( $lundi=='' && $mardi=='' && $mercredi=='' && $jeudi=='' && $vendredi=='' && $samedi=='' && $dimanche=='' && $ferie==''){
                $date_debut = '';$date_fin='';
                $date_sans_recurr=$service[3];
                $liste_idcalendrier='';
                $recurrence='';


            }



            if ($ferie=='1') {
                $lundi='1'; $mardi='1'; $mercredi='1'; $jeudi='1'; $vendredi='1'; $samedi='1';$dimanche='1';$liste_idcalendrier=4;
            }
           /* if ($lundi == '1') $jourscoches = '1, ';
            if ($mardi == '1') $jourscoches .= '2, ';
            if ($mercredi == '1') $jourscoches .= '3, ';
            if ($jeudi == '1') $jourscoches .= '4, ';
            if ($vendredi == '1') $jourscoches .= '5, ';
            if ($samedi == '1') $jourscoches .= '6, ';
            if ($dimanche == '1') $jourscoches .= '0';


            if ($jourscoches == '') {
                $jourscoches = $dateDeb -> format('w');
            }
*/



            $query = "SELECT idservice FROM `#__service`
    WHERE libelle= '".$libelle."'
    and client_id='".Spstools::$client_id."'";
            $db->setQuery($query);
            $query = $db->query();
            $num_rows = $db->getNumRows();
            $rResult = $db->loadRowList();
            if($num_rows == 0) {
                // does not exist in database, post it....
                $query = "INSERT INTO `#__service` (libelle,secteurgeo,hdep,lieudep,lieuret,groupe,nomgroupe,chauf,comment,observation,semaineid,societe,client_id,abc_idcar,date_debut,date_fin,lundi,mardi,mercredi,jeudi,vendredi,samedi,dimanche,recurrence,liste_idcalendrier) VALUES('"
                    .$libelle."', '".$secteurgeo."','".$hdep."','".$lieudep."','".$lieuret."','".$groupe."','".$nomgroupe."','".$chauf."','".$comment."','".$observation."','".$semaineid."','".$societe."','".Spstools::$client_id."','".$car."',".($date_debut==''?"NULL":"'".$date_debut."'").",".($date_fin==''?"NULL":"'".$date_fin."'").",".($lundi==''?"NULL":"'".$lundi."'").",".($mardi==''?"NULL":"'".$mardi."'").",".($mercredi==''?"NULL":"'".$mercredi."'").",".($jeudi==''?"NULL":"'".$jeudi."'").",".($vendredi==''?"NULL":"'".$vendredi."'").",".($samedi==''?"NULL":"'".$samedi."'").",".($dimanche==''?"NULL":"'".$dimanche."'").",".($recurrence==''?"NULL":"'".$recurrence."'").",".($liste_idcalendrier==''?"NULL":"'".$liste_idcalendrier."'").")";
//echo "titi:".$query;
                $db->setQuery($query);
                $db->query();
                $idService = $db->insertid();

                // add a step (etape)
                $query = "INSERT INTO `jos_etape` (libelle,heure,jplus1,service_idservice,ordre,type) VALUES(
        'Prise de service', '".$hdep."',0,".$idService.",1,'')";
                $db->setQuery($query);
                $db->query();

            } else {
                //exist so we can update
                $query = "UPDATE `#__service`  SET libelle = '".$libelle."', secteurgeo = '".$secteurgeo."', hdep = '".$hdep."', lieudep = '".$lieudep."', lieuret = '".$lieuret."', groupe = '".$groupe."', nomgroupe = '".$nomgroupe."', chauf = '".$chauf."', comment = '".$comment."', observation = '".$observation."', societe = '".$societe."', abc_idcar = '".$car."',
                          date_debut= ".($date_debut==''?"NULL":"'".$date_debut."'").",date_fin= ".($date_fin==''?"NULL":"'".$date_fin."'").",lundi= ".($lundi==''?"NULL":"'".$lundi."'").",mardi= ".($mardi==''?"NULL":"'".$mardi."'").",mercredi= ".($mercredi==''?"NULL":"'".$mercredi."'").",jeudi= ".($jeudi==''?"NULL":"'".$jeudi."'").",vendredi= ".($vendredi==''?"NULL":"'".$vendredi."'").",samedi= ".($samedi==''?"NULL":"'".$samedi."'").",dimanche= ".($dimanche==''?"NULL":"'".$dimanche."'").",recurrence= ".($recurrence==''?"NULL":"'".$recurrence."'").",liste_idcalendrier = ".($liste_idcalendrier==''?"NULL":"'".$liste_idcalendrier."'")."
                          WHERE libelle= '".$libelle."'";
//        echo "titiupd:".$query;
                $db->setQuery($query);
                $db->query();

                //update step
                //exist so we can update
                $query = "UPDATE `jos_etape` e,`jos_service` s SET e.heure = '".$hdep."'
        WHERE e.service_idservice = s.idservice
        and s.libelle= '".$libelle."'
        and s.client_id = '".Spstools::$client_id."'";
                $db->setQuery($query);
                $db->query();
            }

            //get idservice
            $query = "SELECT idservice FROM `#__service` WHERE libelle= '".$libelle."'";
            $db->setQuery($query);
            $query = $db->query();
            $rResult = $db->loadRowList();

            $idservice = $rResult[0][0];

/***********/

            //pour le planning
            $datedep = addslashes($date_debut);
            $codesms=addslashes($service[6]);

/*            $query = "SELECT idplanning FROM `#__planning` p
    INNER JOIN  `#__service` s
    ON p.service_idservice=s.idservice
    WHERE s.libelle= '".$libelle."'
    and s.client_id = '".Spstools::$client_id."'
    and p.date > DATE_ADD(CURDATE(), INTERVAL -1 DAY)";

            $db->setQuery($query);
            $query = $db->query();
            $num_rowsp = $db->getNumRows();
            $rResult = $db->loadRowList();
            $idPlanning='';
            if ($codesms!='') {
                //insert only if there is a code sms!
                if($num_rowsp == 0) {
                    // does not exist in database, post it....
                    $query = "INSERT INTO `#__planning` (date,etat,service_idservice,codesms,client_id) VALUES(DATE(STR_TO_DATE('".$datedep."', '%Y-%m-%d')), 'N','".$idservice."','".$codesms."','".Spstools::$client_id."')";
                    $db->setQuery($query);
//            echo "titi:".$query;
                    $db->query();

                    $idPlanning = $db->insertid();

                    // add a step (etape)
                    $query = "INSERT INTO `jos_etape_planning` (planning_idplanning,etat,etape_idetape,type)
           SELECT ".$idPlanning.", null, idetape, type FROM `#__etape` e1 WHERE e1.service_idservice=".$idservice;
                    $db->setQuery($query);
                    $db->query();
                } else {
                    //exist so we can update
                    $idPlanning=$rResult[0][0];
                    $query = "UPDATE `#__planning`  SET codesms = '".$codesms."'', date = DATE(STR_TO_DATE('".$datedep."', '%Y-%m-%d'))  WHERE idplanning = '".$idPlanning."'";
                    $db->setQuery($query);
//            echo "titiupd:".$query;
                    $db->query();

                }
            }

*/


            $form->data['idplanning']='';//$idPlanning;
        if ($date_sans_recurr!=''){
            $form->data['date']=$date_sans_recurr;
            //"delete from jos_planning";
            //pour le planning
            $query = "DELETE FROM `#__planning`
        WHERE service_idservice= ".$idservice."
        and date='".$date_sans_recurr."'";
            $db->setQuery($query);
            $db->query();
        } else
            $form->data['date']=$date_debut;
            $form->data['etat']='N';
            $form->data['codesms']=$codesms;
            $form->data['service_idservice']=$idservice;
            $form->data['client_id']=Spstools::$client_id;
        $form->data['numTelephone']=$numeroA;
        $form->data['numTelephoneBis']=$numeroB;

            Spstools::savePlanningService($form);




    }


    /**insert or replace in planning
     * file format (description: example)
    0-Filliale: KSLTB
    1-Centre(Dépôts): 12
    2-Date service: 2015-10-08 00:00:00.000
    3-Heure départ (en s): 23400
    4-departDescription: CHA.dpt
    5 * designation
    6 * service
    7-Matricule: 20326
    8-Nom: DUXXXX
    9-Prénom: Axxxxx
    10-NA (ignoré): NULL --> euhhh numero de telephone PRO !!!! à prendre en consideration donc
    11-Numéro(s) de téléphone: 03 83 24 34 xx / 06 20 85 29 xx
    12-Commentaire: Circule en période scolaire de Reims
     * @param $tableurservices
     */
    public static function insertOrReplacePlanning($tableurservices)
    {
        $service=$tableurservices;
        $date=new DateTime(addslashes($service[2]));
        $date_fin='';
        $liste_idcalendrier_defaut='';

        //service planning
        $db =& JFactory::getDBO();
        Spstools::loadParametres($db, false);



        //pour le service
        $libelle = trim(addslashes($service[6])).' '.trim(addslashes($service[5]));//modif suite mail 2016-05-09 .' '.$date->format('Y-m-d');
        $semaineid = null;//addslashes($service["SEMAINE_ID"]);

        //on empile les semaineid pour supprimer ceux qui ne sont plus transmis
        //$tab_semaineid[$indice++]=$semaineid;
        $secteurgeo = '';//addslashes($service["SECTEURGEO"]);
        $hdep_sec = addslashes($service[3]);
        $hdepHeure = floor($hdep_sec/3600);
        $hdepMin = ($hdep_sec/60)%60;
        $hdep = ($hdepHeure<10?'0'.$hdepHeure:$hdepHeure).':'.($hdepMin<10?'0'.$hdepMin:$hdepMin).':00';
        $lieudep = '';//addslashes($service["LIEUDEP"]);
        $lieuret = '';//addslashes($service["LIEURET"]);
        $groupe = '';//addslashes($service["GROUPE"]);
        $nomgroupe = '';//addslashes($service["NOMGROUPE"]);
        $chauf = '';//addslashes($service["CHAUF"]);
        $words = '';//preg_split('/([\s\-_,:;?!\/\(\)\[\]{}<>\r\n"]|(?<!\d)\.(?!\d))/',$chauf, null, PREG_SPLIT_NO_EMPTY);
        $nom = '';//substr($words[0],0,4);
        $prenom = '';//$words[1];//substr($words[1],0,2);
        /**TODO:rajouter variable meta CNIL a vrai ou faux*/
        if(Spstools::$client_id=='LLPO11') $chauf = addslashes($service[7]).' '.addslashes($service[8]).' '.addslashes($service[9]);//addslashes($service[6]).'. '.addslashes($service[7]);
        else $chauf = addslashes($service[7]).' '.addslashes($service[8]).' '.addslashes($service[9]);//addslashes($service[6]).'. '.addslashes($service[7]);
        $comment = addslashes($service[12]);//addslashes($service["COMMENT"]);
        $observation = '';//addslashes($service["OBSERVATION"]);
        $societe = isset($service[1]) && strlen($service[1])>0?trim(addslashes($service[1])):'';    //addslashes($service["SOCIETE"]);
        $car = '';//addslashes($service["CAR"]);
        $numeroA=addslashes($service[10][0]);
        $numeroB=addslashes($service[10][1]);
        $recurrence='';


        //process validity (LMmJVSDF) day of weeks and days off (F)
$aujourdhui = new DateTime();
        if ($date == '') $date = $aujourdhui->format('Y-m-d');
        if ($date_fin == '') $date_fin =$date;// $aujourdhui->format('Y-m-d');

        $idservice=null;







        /*      $query = "SELECT idservice FROM `#__service`
          WHERE libelle= '".$libelle."'
          and societe = '".$societe."'
          and client_id='".Spstools::$client_id."'";
              $db->setQuery($query);
              $query = $db->query();
              $num_rows = $db->getNumRows();
        */
        $num_rows=0;

        if($num_rows == 0) {
            // does not exist in database, post it....
            $query = "INSERT INTO `#__service` (libelle,secteurgeo,hdep,lieudep,lieuret,groupe,nomgroupe,chauf,comment,observation,semaineid,societe,client_id,abc_idcar) VALUES('"
                .$libelle."', '".$secteurgeo."','".$hdep."','".$lieudep."','".$lieuret."','".$groupe."','".$nomgroupe."','".$chauf."','".$comment."','".$observation."','".$semaineid."','".$societe."','".Spstools::$client_id."','".$car."')";
//echo "titi:".$query;
            $db->setQuery($query);
            $db->query();
            $idservice = $db->insertid();

            // add a step (etape)
            $query = "INSERT INTO `jos_etape` (libelle,heure,jplus1,service_idservice,ordre,type) VALUES(
        'Prise de service', '".$hdep."',0,".$idservice.",1,'')";
            $db->setQuery($query);
            $db->query();

        } else {
            //exist so we can update
            $query = "UPDATE `#__service`  SET libelle = '".$libelle."', secteurgeo = '".$secteurgeo."', hdep = '".$hdep."', lieudep = '".$lieudep."', lieuret = '".$lieuret."', groupe = '".$groupe."', nomgroupe = '".$nomgroupe."', chauf = '".$chauf."', comment = '".$comment."', observation = '".$observation."', societe = '".$societe."', abc_idcar = '".$car."'
                          WHERE libelle= '".$libelle."'
                          and societe = '".$societe."'
                          and client_id='".Spstools::$client_id."'";
//        echo "titiupd:".$query;
            $db->setQuery($query);
            $db->query();

            //update step
            //exist so we can update
            $query = "UPDATE `jos_etape` e,`jos_service` s SET e.heure = '".$hdep."'
        WHERE e.service_idservice = s.idservice
        and s.libelle= '".$libelle."'
        and s.client_id = '".Spstools::$client_id."'";
            $db->setQuery($query);
            $db->query();
        }

        //get idservice
        $query = "SELECT idservice FROM `#__service`
        WHERE libelle= '".$libelle."'
        and societe = '".$societe."'
        and client_id='".Spstools::$client_id."'";
        $db->setQuery($query);
        $db->query();
        $rResult = $db->loadRowList();

        if(is_null($idservice)) $idservice = $rResult[0][0];


        //pour le planning
        $query = "DELETE FROM `#__planning`
        WHERE service_idservice= ".$idservice."
        and date='".$date->format('Y-m-d')."'";
        $db->setQuery($query);
        $db->query();


        $datedep = $date;

        //3 premiere lettre filiale + matricule conducteur
        //$codesms=substr(trim(addslashes($service[0])),0,3).trim(addslashes($service[7]));
        $codesms=trim(addslashes($service[6]));

 //NORMANDIE SEINE specific ATTENTION a METTRE lors d une maintenance ABSZ97 partout dans la base au lieu de LLPO11
        if(Spstools::$client_id=='LLPO11') $codesms=trim(addslashes($service[7]));

        //KATL specific
        if(Spstools::$client_id=='FXDK40' && strlen($codesms)>=5 && (substr($codesms,-2)=='PS' || substr($codesms,-2)=='VS'))    $codesms=substr($codesms,0,-2);

        $form=new JObject('data');
        $form->data['idplanning']='';
        $form->data['date']=$date->format('Y-m-d');
        $form->data['etat']='N';
        $form->data['codesms']=$codesms;
        $form->data['service_idservice']=$idservice;
        $form->data['client_id']=Spstools::$client_id;

        $form->data['numTelephone']=$numeroA;
        $form->data['numTelephoneBis']=$numeroB;


        $form->data['recurrencekey']='';
        $form->data['date_debut']='';
        $form->data['date_fin']='';
        $form->data['liste_idcalendrier']='';


        Spstools::savePlanningService($form);




    }

    /**
     * notify with SMSBOX api
     * @param $sms
     * @param JDatabase $db
     * @param client_id
     * @return int|string
     */
    public static function smsBoxNotify($sms,$dateEnvoi=null,$heureEnvoi=null)
    {
    //protect field
$annoncevocale = $sms[1];
    $annoncevocale2 = $annoncevocale;



$erreur_envoi = false;
$sessioncall_id = "-1";

        //convert date and time to correct format for smsbox
$dateFormatted=null;
        if($heureEnvoi!=null){
            if($dateEnvoi==null){$dateEnvoi=date('d/m/Y');}
            $heureEnvoi=substr($heureEnvoi,0,5);
        }
        if($dateEnvoi!=null){
            $dateFormatted=substr($dateEnvoi,8,2).'/'.substr($dateEnvoi,5,2).'/'.substr($dateEnvoi,0,4);
        }



        //Appel Astreinte numero1
        // set the scripts timezone to use the date function
        date_default_timezone_set('UTC');

        $smsbox = new api_smsbox();

        // number of the device to receive the message
        $recipient = $sms[0];

        // create the DispatchMessage object to send, note that the type of message
        // specified can be either SMS or Voice, the constants are in the AbstractMessage class

        $retour = $smsbox->sendSMS($recipient, utf8_decode($annoncevocale), null/*Parametrage::$numeroVirtuel*/, 'Expert', true, true, false, $dateFormatted, $heureEnvoi);


        try {
            $description = array('OK' => 'Message envoyé avec succès', 'ERROR 01' => 'Paramètres manquants', 'ERROR 02' => 'Identifiant ou mot de passe incorrect', 'ERROR 03' => 'Crédit insuffisant', 'ERROR 04' => 'Numéro invalide', 'ERROR 05' => 'Erreur d\'éxécution SMSBOX');
            if (array_key_exists($retour, $description)) {

                if (strpos($retour, 'ERROR 04') === true) {
                    //mail($mailAstreinte, 'Alerte SPS', 'Bonjour, la notification d\'un conducteur a échoué car le numéro (' . $recipient . ') est erroné: ' . $description{$retour} . '\nMessage d\'envoi : ' . $annoncevocale, Parametrage::$headers);
                }

            } elseif (substr($retour, 0, 2) == 'OK') {
                $sessioncall_id = substr($retour, 2);

            } else $erreur_envoi = true;


        } catch (Exception $e) {
            //rien a faire à part un log
            $filename = './consoleSendSMSxls.txt';
            $fp = fopen($filename, 'a+');
            fwrite($fp, date(DATE_ATOM) . ':exception envoi smsbox');
            fclose($fp);
            $erreur_envoi = true;

        }

        if ($erreur_envoi) return -1;

/*
Fin
*/
return $sessioncall_id;
}

    /**
     * @param $cell
     * @param $planning
     * @param $i
     * @return mixed
     */
    protected static function checknExtractPhones($cell, $planning, $i)
    {
        $phone=array();
//phone numbers -- for france 06 07 will be set in first position
        $phonestring = addslashes($cell->getValue());
        $phonestring = str_replace(' ', '', $phonestring);
        if (substr_count($phonestring, '/') == 1) $phones = explode('/', $phonestring);
        elseif (substr_count($phonestring, '-') == 1) $phones = explode('-', $phonestring);
        elseif (substr_count($phonestring, ';') == 1) $phones = explode(';', $phonestring);
        else $phones[0] = $phonestring;

        $phone0isMobile = false;
        $phone1isMobile = false;

        if (substr($phones[0], 0, 2) == '06' || substr($phones[0], 0, 2) == '07' || substr($phones[0], 0, 3) == '336' || substr($phones[0], 0, 3) == '337' || substr($phones[0], 0, 4) == '+336' || substr($phones[0], 0, 4) == '+337')
            $phone0isMobile = true;
        if (isset($phones[1]) && (substr($phones[1], 0, 2) == '06' || substr($phones[1], 0, 2) == '07' || substr($phones[1], 0, 3) == '336' || substr($phones[0], 0, 3) == '337' || substr($phones[0], 0, 4) == '+336' || substr($phones[0], 0, 4) == '+337'))
            $phone1isMobile = true;

        if (!$phone0isMobile && $phone1isMobile) {
            //swap phones first is mobile
            $buff = $phones[0];
            $phones[0] = $phones[1];
            $phones[1] = $buff;

        }


        //check number
        preg_match(Spstools::checkphoneNumber_regexp(), $phones[0], $matches);
        if (strlen($matches[0]) > 0) $planning[$i][0] = $phones[0];
        if (isset($phones[1])) {
            preg_match(Spstools::checkphoneNumber_regexp(), $phones[1], $matches);
            if (strlen($matches[0]) > 0) $planning[$i][1] = $phones[1];

        }
        return $planning;
    }


    public function export($form){
$lignes = Spstools::createExport($form->data['datedebut'],$form->data['datefin']);

if (ereg('Opera(/| )([0-9].[0-9]{1,2})', $_SERVER['HTTP_USER_AGENT'])) {
$UserBrowser = "Opera";
}
elseif (ereg('MSIE ([0-9].[0-9]{1,2})', $_SERVER['HTTP_USER_AGENT'])) {
    $UserBrowser = "IE";
} else {
    $UserBrowser = '';
}
$mime_type = ($UserBrowser == 'IE' || $UserBrowser == 'Opera') ? 'application/octetstream' : 'application/octet-stream';
@ob_end_clean();
ob_start();

header('Content-Type: ' . $mime_type);
header('Expires: ' . gmdate('D, d M Y H:i:s') . ' GMT');

if ($UserBrowser == 'IE') {
    header('Content-Disposition: inline; filename="' . "exportSPS - ".date("j_n_Y").'.csv"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
}
else {
    header('Content-Disposition: attachment; filename="' . "exportSPS - ".date("j_n_Y").'.csv"');
    header('Pragma: no-cache');
}

$outputBuffer = fopen("php://output", 'w');
foreach($lignes as $val) {
    fputcsv($outputBuffer, $val);
}
fclose($outputBuffer);
exit();
}




    /**
     *
     */
    static function loadHistory(){
        self::init_includes();

        ?>

        <script type="text/javascript" charset="utf-8">
            (function($) {

                var oTable;

                $(document).ready(function() {

                    oTable = $('#tabledefault').dataTable( {
                        "sPaginationType": "full_numbers",
                        "bSortClasses": false,
                        "bJQueryUI": true,
                        "bProcessing": true,
                        "bAutoWidth": false,
                        "aaSorting": [[ 0, "desc" ]],
                        "aoData": [
                            { "sType": "string"},
                            { "sType": "string"},
                            { "sType": "string"}
                        ],
                        "oLanguage": {
                            "sUrl": " <?php echo JURI::Base();?>components/com_alertsms/assets/locale/fr_FR.json"
                        }

                    } );
                    oTable.fnAdjustColumnSizing();
                    oTable.fnRender();

                } );
            })(jQuery);
        </script>

        <div id="tablecell">
            <table cellpadding="0" cellspacing="0" border="0" class="display" id="tabledefault" style="width:100%">
                <thead>
                <tr>
                    <th>
                        Date
                    </th>

                    <th>
                        Total
                    </th>
                    <th>
                        Envoy&eacute;
                    </th>

                </tr>
                </thead>
                <tbody>
                <?php

                    $query = "SELECT date,total,envoye FROM jos_bilan_envoi_tableur";

                    $db =& JFactory::getDBO();
                    $db->setQuery($query);
                    $query = $db->execute();
                    $num_rows = $db->getNumRows();
                    if ($num_rows > 0) {
                        $data = $db->loadObjectList();
                        foreach ( $data as $row ):

                            ?>
                            <tr class="<?php echo "gradeA";?>">
                                <td>
                                    <?php echo $row->date; ?>
                                </td>
                                <td>
                                    <?php echo $row->total; ?>
                                </td>
                                <td>
                                    <?php echo $row->envoye; ?>
                                </td>

                            </tr>
                        <?php endforeach; }?>
                </tbody>
            </table>

        </div>
        <br/>


    <?php
    }

    /**
     *
     */
    protected static function init_includes()
    {


        JHTML::stylesheet('alertsms.css', 'administrator/components/com_alertsms/assets/');
        JHTML::stylesheet('','components/com_alertsms/assets/css/alertsms_page.css');
        //JHTML::stylesheet('','components/com_alertsms/assets/css/alertsms_table.css');
        JHTML::stylesheet('','components/com_alertsms/assets/css/alertsms_table_jui.css');
        JHTML::stylesheet('','components/com_alertsms/assets/css/alertsms_themeroller.css');
        JHTML::stylesheet('','components/com_chronoforms/css/datepicker/datepicker_dashboard.css');



        $doc = JFactory::getDocument();
        //$doc->addScript( JURI::Base().'components/com_alertsms/assets/js/jquery.js' );
        $doc->addScript( JURI::Base().'components/com_alertsms/assets/js/jquery.dataTables.js' );

        //$doc->addScript( JURI::Base().'media/system/js/mootools-core-uncompressed.js' );
       // $doc->addScript( JURI::Base().'media/system/js/core-uncompressed.js' );
        //$doc->addScript( JURI::Base().'media/system/js/mootools-more-uncompressed.js' );

    }


}




?>
