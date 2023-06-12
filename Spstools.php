<?php
/**
 * Created by
 * User: xavier TRABET
 * Date: 23/05/13
 * Time: 18:40
 *
 */
include_once('./SmsTools.php');
include_once('./SpsDepot.php');

class Spstools
{


    public static $notification_actif;
    public static $attente_sms;
    public static $alerte_astreinte_reveil;
    public static $alerte_astreinte_sms;
    public static $h1ast_off;
    public static $h2ast_on;
    public static $h3ast_off;
    public static $h4ast_on;
    public static $numdefAstreinte1;
    public static $numdefAstreinte2;
    public static $margeMinuteSMS; //delay before alarm in minutes for the 1st step.
    public static $maildefAstreinte;
    public static $margeMinuteSMS2; //delay before alarm in minutes for the intermediate steps.
    public static $margeMinuteSMSArrivee; //delay before alarm in minutes for the finish (last step).
    public static $modeComptage; //counting mode : inactif(SPS), exclusif(Comptage), combined(SPS+Comptage)
    public static $comptageSmsStarter; //counting mode,  sms must start with this string
    public static $comptageNotifErreurSms; //counting mode, if a bad sms syntax is received and if checked we notiy the sender with the good sms format expected
    public static $comptageMsgErreurSMS; //counting mode, content of the notification
    public static $timeMinFuel; //sps+
    public static $timeMinWash; //sps+
    public static $timeMinBetweenReads; //sps+
    public static $client_id; //useful for insert by default
    public static $horaireNumAstreinte;
    public static $debutHoraireN1;
    public static $finHoraireN1;

public static function CreateUser($form){

        // Instantiate the application.
            $app = JFactory::getApplication('site');
            $db =& JFactory::getDBO();
        $falsemail=false;
        if ($form->data['email']=='') {
            $form->data['email']='noaddress@noaddress.xtr';
            $falsemail=true;
        }
        
        
            $strrnd='P'.mt_rand(100000,9999999);
	echo $form->data['nom'].' '.$strrnd.'<br>';
        $data = array(
            "name"=>$form->data['nom'].' '.$form->data['prenom'],
            "username"=>strtolower($form->data['prenom'][0].$form->data['nom']),
            "password"=>$strrnd,
            "password2"=>$strrnd,
            "email"=>strtolower(str_replace(' ','',$form->data['email'])),
            "block"=>0,
            "groups"=>array('12')
        );
        
        self::loadParametres($db,false);
        $clientid=self::$client_id;
        $userid = self::createJoomlaUser($app,$data);
        if ($userid>0) {
        
            $query = "insert into jos_usersps (user_id,liste_iddepot,client_id)
             values (".$userid.",12,'".$clientid."')";
        
            $db->setQuery($query);
            $db->execute();
        
        
            if ($falsemail) {
                $query = "update jos_users set email='' where id=".$userid;
        
                $db->setQuery($query);
                $db->execute();
            }
        }
        
        }
        
            /**
             * @param $mainframe
             * @param $data
             * @return int|null
             * @throws Exception
             */
            public static function createJoomlaUser($mainframe, $data)
            {
                $user =  JUser::getInstance();
                $pathway = & $mainframe->getPathway();
                $config = & JFactory::getConfig();
                $authorize = & JFactory::getACL();
                $document = & JFactory::getDocument();
        
                $usersConfig = & JComponentHelper::getParams('com_users');
                /*if ($usersConfig->get('allowUserRegistration') == '0') {
                    JError::raiseError(403, JText::_('Access Forbidden'));
                    return;
                }*/
                jimport('joomla.user.user');
                jimport('joomla.application.component.helper');
        
                $newUsertype = $usersConfig->get('new_usertype');
                if (!$newUsertype) {
                    $newUsertype = 'Registered';
                }
        
                if (!$user->bind($data)) {
                    throw new Exception("Could not bind data. Error: " . $user->getError());
                }
        
                //$salt     = JUserHelper::genRandomPassword(32);
               // $password_clear = $password;
        
                //$crypted  = JUserHelper::getCryptedPassword($password_clear, $salt);
                //$password = $crypted.':'.$salt;
                $user->set('id', 0);
                $user->set('usertype', 'deprecated');
             //   $user->set('name'           , $name);
             //   $user->set('username'       , $username);
             //   $user->set('password' , $password);
             //   $user->set('password_clear' , $password_clear);
             //   $user->set('email'          , $email);
             //   $user->set('groups'     , array($defaultUserGroup));
                // Here is possible set user profile details
             //   $user->set('profile'    , array('gender' =>  $gender));
        
        
                $date =& JFactory::getDate();
                $user->set('registerDate', $date->toSql());
        
                $useractivation = $usersConfig->get('useractivation');
                if ($useractivation == '1') {
                    jimport('joomla.user.helper');
                    $user->set('activation',  JApplication::getHash(JUserHelper::genRandomPassword()));
                    $user->set('block', '0');
                    $user->set('sendEmail', '0');
                }
        
                if (!$user->save()) {
                    throw new Exception("Impossible de creer cet utilisateur. Erreur: " . $user->getError());
                } else {
                    return $user->id;
                }
        
            }
        



    /**
     * @param $idplanning
     * @param $db
     * @return string
     */
    public
    static function getNextStatus($idplanning, JDatabase $db)
    {

        $query = 'SELECT
            ep.etat
			FROM #__etape_planning ep
            where ep.planning_idplanning = ' . $idplanning .'
			and ep.dateEtat is null
			and (ep.etat is null or ep.etat = \'L\')';
        $db->setQuery($query);
        $db->query();
        $num_rows = $db->getNumRows();

        if ($num_rows > 1) {
            return "N";
        } else {
            return "R";
        }
    }

    /**
     * @param $idplanning
     * @param JDatabase $db
     * @return string
     */
    public static function getNextEtape($idplanning, JDatabase $db)
    {
        $query = 'SELECT
            ep.idetape_planning
			FROM #__etape_planning ep
			INNER JOIN #__etape e
			ON e.idetape = ep.etape_idetape
            where ep.planning_idplanning = ' . $idplanning .'
			and (ep.etat is null or (ep.etat = \'L\' and user_id is null))
			order by e.ordre asc';
        $db->setQuery($query);
        $db->query();
        $num_rows = $db->getNumRows();
        $rResult = $db->loadRowList();
        if ($num_rows > 0) {
            return $rResult[0][0];
        } else {
            return "";
        }
    }

    /**
     * @param $dateheureRecept
     * @param $codeSms
     * @param $idrsms
     * @param $db
     * @return string
     */
    public
    static function affectReceivedSMS($dateheureRecept, $codeSms, $idrsms, JDatabase $db,$testalphanum,$qrcode=false)
    {
        $margeAnteMinAccept = -180; //by default before knowing which depot is concerned
        $smsAffecte = "";
        $strEtat = "";
        $ordreaffecte = null;

        $dateencours = null;
        $datetrouvee = null;


        //recherche du trajet concerné
        if ($testalphanum===true) {
            $codeSmsNoSpace = str_replace(" ", "", $codeSms);
            $query = 'SELECT p.idplanning,p.etat,s.hdep,p.date,DATE("' . $dateheureRecept . '"),s.societe
			FROM #__service s, #__planning p
            where (p.date = DATE("' . $dateheureRecept . '")
             OR DATE_ADD(p.date,INTERVAL 1 DAY) = DATE("' . $dateheureRecept . '"))
            AND (
            lower("'. $codeSmsNoSpace .'") REGEXP CONCAT("^(.*([[:<:]]",trim(LOWER(p.codesms)),"([[:>:]]|$){1})[^$]*)$")
            OR lower("'. $codeSms .'") REGEXP CONCAT("^(.*([[:<:]]",trim(LOWER(p.codesms)),"([[:>:]]|$){1})[^$]*)$")
            OR lower("'. $codeSms .'") REGEXP CONCAT("^(.*(([[:alpha:]]|[[:space:]]|^){1}",trim(LOWER(p.codesms)),"([[:alpha:]]|[[:space:]]|$){1})[^$]*)$")
            )
            AND p.service_idservice = s.idservice
            ';}
            else
        /*old version with numbers only
        $query = 'SELECT p.idplanning,p.etat,s.hdep,p.date,DATE("' . $dateheureRecept . '")
			FROM #__service s, #__planning p
            where (p.date = DATE("' . $dateheureRecept . '")
             OR DATE_ADD(p.date,INTERVAL 1 DAY) = DATE("' . $dateheureRecept . '"))
            AND p.codesms like "' . $codeSms . '"
            AND p.service_idservice = s.idservice
            ';*/
                $query = 'SELECT p.idplanning,p.etat,s.hdep,p.date,DATE("' . $dateheureRecept . '"),s.societe
			FROM #__service s, #__planning p
            where (p.date = DATE("' . $dateheureRecept . '")
             OR DATE_ADD(p.date,INTERVAL 1 DAY) = DATE("' . $dateheureRecept . '"))
            AND lower("'. $codeSms .'") REGEXP CONCAT("^(.*(([[:alpha:]]|[[:space:]]|^){1}",LOWER(p.codesms),"([[:alpha:]]|[[:space:]]|$){1})[^$]*)$")
            AND p.service_idservice = s.idservice
            ';
        $db->setQuery($query);
        $db->query();
        $num_rows = $db->getNumRows();
        if ($num_rows > 0) {
            $rResult = $db->loadRowList();

            //get depot timing
            $depot = new SpsDepot();
            $depot->loadBySociete($rResult[0][5]);
            $margeAnteMinAccept= -(!is_null($depot->margeMinute1)?$depot->margeMinute1:$margeAnteMinAccept);
            $margeMinuteSMS= !is_null($depot->margeMinuteAlerte)?$depot->margeMinuteAlerte:Spstools::$margeMinuteSMS;

            foreach ($rResult as $aRow) {
                /*
                 * tester si pour chaque heure renseignée l'heure du sms recu est dans la fourchette de temps
                 * selon la regle d'affectation
                 * sinon retour null
                 */
                $nb_etape = 0;
                $smsAffecte = "";
                $strEtat = "";


                $query = 'select max(ordre)
                FROM #__etape e,#__service s, #__planning p
                WHERE p.idplanning = ' . $aRow[0] . '
                AND p.service_idservice = s.idservice
                AND e.service_idservice = s.idservice';
                $db->setQuery($query);
                $db->query();
                $num_rows3 = $db->getNumRows();
                $rResult3 = $db->loadRowList();
                if ($num_rows3 > 0) {
                    $nb_etape = $rResult3[0][0];

                } else {
                    $nb_etape = 0;
                }

                //si date planning meme jour
                $datetrouvee = $aRow[3];
                $dateencours = $aRow[4];
                if ($datetrouvee == $dateencours) {

                    //la prise de service est evaluee
                    $query = "SELECT p.etat like 'N' AND TIMESTAMPDIFF(MINUTE,'" . $dateheureRecept . "',DATE_ADD(p.date,interval TIME_TO_SEC(s.hdep) second))<=" . $margeAnteMinAccept . " AND
                        TIMESTAMPDIFF(SECOND,'" . $dateheureRecept . "',DATE_ADD(p.date,interval TIME_TO_SEC(s.hdep) second))>=-(" . $margeMinuteSMS*60 . ") as indep
                    FROM #__planning p, #__service s
                    where p.idplanning = " . $aRow[0] . "
                    and p.dateappel is null
                    and s.idservice = p.service_idservice";

                    $db->setQuery($query);
                    $db->query();
                    $num_rows2 = $db->getNumRows();
                    if ($num_rows2 > 0) {
                        $rResult2 = $db->loadRowList();
                        foreach ($rResult2 as $aRowB) {
                            if ($aRowB[0]) {
                                if ($nb_etape == 0) $smsAffecte = 'R'; else
                                    $smsAffecte = 'P';
                                $strEtat = 'etatPriseDeService';
                                $ordreaffecte = 0;
                            }
                        }
                    }

                }


                for ($cpt = 1; $cpt <= $nb_etape; $cpt++) {
                    //evalue les etapes (s'il y en a)
                    //la premiere etape
                    if ($cpt == 1) {
                        $query = "SELECT TIMESTAMPDIFF(MINUTE,'" . $dateheureRecept . "',DATE_ADD(DATE_ADD(p.date, INTERVAL e.jplus1 DAY),interval TIME_TO_SEC(e.heure) second))<=" . $margeAnteMinAccept . "
                    AND ep.etat IS NULL AND
                    TIMESTAMPDIFF(SECOND,'" . $dateheureRecept . "',DATE_ADD(DATE_ADD(p.date, INTERVAL e.jplus1 DAY),interval TIME_TO_SEC(e.heure) second))>=-(" . $margeMinuteSMS*60 . ") as inEtape,
                    e.type
                    FROM #__etape_planning ep, #__etape e, #__planning p, #__service s
                    where ep.planning_idplanning = " . $aRow[0] . "
                    and ep.etape_idetape = e.idetape
                    and e.ordre=" . $cpt . "
                    and p.idplanning = ep.planning_idplanning
                    and s.idservice = p.service_idservice";

                        $db->setQuery($query);
                        $query = $db->query();
                        $num_rows2 = $db->getNumRows();
                        if ($num_rows2 > 0) {
                            $rResult2 = $db->loadRowList();
                            foreach ($rResult2 as $aRowB) {
                                if ($aRowB[0] && ((!$qrcode && ($aRowB[1]=='' || $aRowB[1]==null)) || ($qrcode && $aRowB[1]=='Qrcode'))) {
                                    if ($cpt < $nb_etape) $smsAffecte = 'P1'; else
                                        $smsAffecte = 'R';
                                    $strEtat = 'etat';
                                    $ordreaffecte = 1;
                                }
                            }
                        }
                    } else {
                        //etapes
                        $query = "SELECT TIMESTAMPDIFF(MINUTE,'" . $dateheureRecept . "',DATE_ADD(DATE_ADD(p.date, INTERVAL e.jplus1 DAY),interval TIME_TO_SEC(e.heure) second))<LEAST(60, ABS((TIME_TO_SEC(e.heure) - TIME_TO_SEC(e2.heure))/60 +(" . $margeMinuteSMS . ")))
                    AND ep.etat IS NULL AND
                    TIMESTAMPDIFF(SECOND,'" . $dateheureRecept . "',DATE_ADD(DATE_ADD(p.date, INTERVAL e.jplus1 DAY),interval TIME_TO_SEC(e.heure) second))>=-(" . $margeMinuteSMS*60 . ") as inEtape,
                    e.type
                    FROM #__etape_planning ep, #__etape e, #__planning p, #__service s, #__etape e2
                    where ep.planning_idplanning = " . $aRow[0] . "
                    and ep.etape_idetape = e.idetape
                    and e.ordre=" . $cpt . "
                    and p.idplanning = ep.planning_idplanning
                    and s.idservice = p.service_idservice
                    and e2.service_idservice=s.idservice
                    and e2.ordre = " . ($cpt - 1);

                        $db->setQuery($query);
                        $db->query();
                        $num_rows2 = $db->getNumRows();
                        if ($num_rows2 > 0) {
                            $rResult2 = $db->loadRowList();
                            foreach ($rResult2 as $aRowB) {
                                if ($aRowB[0] && ((!$qrcode && ($aRowB[1]=='' || $aRowB[1]==null)) || ($qrcode && $aRowB[1]=='Qrcode'))) {
                                    if ($cpt < $nb_etape) $smsAffecte = 'P' . $cpt; else
                                        $smsAffecte = 'R';

                                    $strEtat = 'etat';
                                    $ordreaffecte = $cpt;
                                }
                            }
                        }

                    }

                }


                if ($smsAffecte != "") {


                    //affecte le sms au trajet et mise à jour du trajet (etape + etat etape)
                    $query = 'UPDATE #__receivedsms SET planning_idplanning = ' . $aRow[0] . '
                            where idreceivedsms = ' . $idrsms . '
                            ';
                    $db->setQuery($query);
                    $db->query();


                    if ($ordreaffecte == 0) {
                        $query = 'UPDATE #__planning SET etat = "' . $smsAffecte . '",' . $strEtat . '="R"
                            where idplanning = ' . $aRow[0] . '
                            ';

                    } else {
                        $query = 'UPDATE #__planning SET etat = "' . $smsAffecte . '"
                            where idplanning = ' . $aRow[0] . '
                            ';
                        $db->setQuery($query);
                        $query = $db->query();

                        $idetapeValidee = NULL;
                        $query = 'SELECT ep.idetape_planning
                    FROM #__etape_planning ep,#__etape e
                            where ep.planning_idplanning = ' . $aRow[0] . '
                            and ep.etape_idetape = e.idetape
							and e.ordre=' . $ordreaffecte;
                        $db->setQuery($query);
                        $query = $db->query();
                        $num_rows3 = $db->getNumRows();
                        $rResult3 = $db->loadRowList();
                        if ($num_rows3 > 0) {
                            $idetapeValidee = $rResult3[0][0];
                        }

                        $query = 'UPDATE #__receivedsms SET etape_idetape_planning = ' . $idetapeValidee . '
                            where idreceivedsms = ' . $idrsms . '
                            ';
                        $db->setQuery($query);
                        $query = $db->query();


                        $query = 'UPDATE #__etape_planning ep,jos_etape e SET ep.etat = "R", ep.dateEtat="'.$dateheureRecept.'"
                            where ep.planning_idplanning = ' . $aRow[0] . '
                            and ep.etape_idetape = e.idetape
							and e.ordre=' . $ordreaffecte;
                    }
                    $db->setQuery($query);
                    $query = $db->query();

                    //update les etats des etapes si certains n'ont jamais ete validé (NULL) il sont soldés (S)
                    $query = 'UPDATE #__planning SET
                        etatPriseDeService = CASE WHEN etatPriseDeService IS NOT NULL THEN etatPriseDeService 
                        ELSE CASE WHEN etat>="P" THEN "S" ELSE NULL END END
                            where idplanning = ' . $aRow[0];
                    $db->setQuery($query);
                    $query = $db->query();

                    $query = 'UPDATE #__etape_planning ep,#__etape e SET ep.etat = "S", ep.dateEtat="'.$dateheureRecept.'"
                            where planning_idplanning = ' . $aRow[0] . '
                            and etape_idetape = e.idetape
							and e.ordre<' . $ordreaffecte . '
							and etat IS NULL';

                    $db->setQuery($query);
                    $query = $db->query();

                }


            }


            if ($smsAffecte == "" && $num_rows == 1 && $datetrouvee != $dateencours) {
                //update du motif sur le sms
                $query = 'UPDATE #__receivedsms SET motifRejet = "Code trajet inconnu"
                where idreceivedsms = ' . $idrsms . '
                ';
                $db->setQuery($query);
                $db->query();
                return "Code trajet inconnu";
            } else if ($smsAffecte == "" && is_null($ordreaffecte)) {
                //sms rejeté car en dehors des clous
                //update du motif sur le sms
                $query = 'UPDATE #__receivedsms SET motifRejet = "Hors période"
                            where idreceivedsms = ' . $idrsms . '
                            ';
                $db->setQuery($query);
                $db->query();
                return "Hors période";

            }


            return $strEtat;
        } else {
            //update du motif sur le sms
            $query = 'UPDATE #__receivedsms SET motifRejet = "Code trajet inconnu"
            where idreceivedsms = ' . $idrsms . '
            ';
            $db->setQuery($query);
            $db->query();
            return "Code trajet inconnu";
        }
    }

    /**
     * Check if received sms if its syntax is correct (for counting mode)
     * @param $phoneSender
     * @param $smsContent
     * @param $idrsms
     * @param JDatabase $db
     * @return string
     */
    public
    static function checkCountingForReceivedSMS($phoneSender, $smsContent, $idrsms, JDatabase $db, $client_id)
    {
        //check sms syntax
        preg_match(Spstools::getCountingMode_regexp(), $smsContent, $matches);

        //nok?
        if (!strlen($matches[0]) > 0) {

            //notify checked?
            //temporary test Exclusive mode before further dev
            if (Spstools::$modeComptage == 'E') {

            if (Spstools::$comptageNotifErreurSms) {
                //notify driver
                Spstools::notifyDriver($phoneSender, $db, $client_id);
            }

            }//endif temporary test


            //update sms reject label if exclusive mode
            if (Spstools::$modeComptage == 'E') {
                $query = 'UPDATE #__receivedsms SET motifRejet = "Erreur de syntaxe"
                where idreceivedsms = ' . $idrsms . '
                ';
                $db->setQuery($query);
                $db->query();
                return "Erreur de syntaxe";
            }
        }
    }

    /**
     * give the regexp for sms Counting mode
     * @return string
     */
    public static function getCountingMode_regexp()
    {
        return "/^(" . Spstools::$comptageSmsStarter . "[0-9][0-9][0-9])[\' \']{1,}([0-9]{1,2})($|[\' \']{1,}.*){1}$/";
    }

    /**
     * give the - history compatible - regexp for sms Counting mode
     * @return string
     */
    public static function getCountingMode_Hist_regexp()
    {
        return "/^(48[0-9][0-9][0-9]|" . Spstools::$comptageSmsStarter . "[0-9][0-9][0-9])[\' \']{1,}([0-9]{1,2})($|[\' \']{1,}.*){1}$/";
    }

    /**
     * notify the sender sms syntax is incorrect
     * @param $phoneSender
     * @param JDatabase $db
     * @param $client_id
     */
    public static function notifyDriver($phoneSender, JDatabase $db, $client_id)
    {

        if (smsTools::smsBoxNotify(Spstools::$comptageMsgErreurSMS, 0, $phoneSender, 'sms', Spstools::$maildefAstreinte, $db, $client_id) == -1) {
            //notify failed, insert a record in notify queue in order to retry later.
            $query = "INSERT INTO jos_sentcallsqueue (`numDestinataire`, `dateheure`, `typeappel`, `tryNumber`, `active`, `client_id`) VALUES ('" . $phoneSender . "',NOW(),'sms',0,'T','".$client_id."')";
            $db->setQuery($query);
            $db->query();

        }

    }

    //renvoi vrai si user sur le groupe readOnly (standard SPS)
    /**
     * @param $db
     * @param $user_id
     * @return bool
     */
    public static function checkReadOnlyUser(JDatabase $db, $user_id)
    {

        $public_SPS = 14;

        //recherche du groupe concerné
        $query = 'SELECT group_id
			FROM #__user_usergroup_map
            where user_id = ' . $user_id;
        $db->setQuery($query);
        $query = $db->query();
        $num_rows = $db->getNumRows();


        if ($num_rows > 0) {
            $rResult = $db->loadRowList();
            foreach ($rResult as $aRow) {
                return ($aRow[0] == $public_SPS) ? true : false;
            }
        } else {
            return true;
        }
    }



//renvoi vrai si user sur le groupe standard SPS)

    /**
     * @param $db
     * @param $user_id
     * @return bool
     */
    public static function checkStandardUser(JDatabase $db, $user_id)
    {

        $standard_SPS = 3;

        //recherche du groupe concerné
        $query = 'SELECT group_id
			FROM #__user_usergroup_map
            where user_id = ' . $user_id;
        $db->setQuery($query);
        $query = $db->query();
        $num_rows = $db->getNumRows();


        if ($num_rows > 0) {
            $rResult = $db->loadRowList();
            foreach ($rResult as $aRow) {
                return ($aRow[0] == $standard_SPS) ? true : false;
            }
        } else {
            return false;
        }
    }

    //renvoi vrai si user sur le groupe standard Plus SPS)

    /**
     * @param $db
     * @param $user_id
     * @return bool
     */
    public static function checkStandardPlusUser(JDatabase $db, $user_id)
    {

        $standard_Plus_SPS = 10;

        //recherche du groupe concerné
        $query = 'SELECT group_id
			FROM #__user_usergroup_map
            where user_id = ' . $user_id;
        $db->setQuery($query);
        $query = $db->query();
        $num_rows = $db->getNumRows();


        if ($num_rows > 0) {
            $rResult = $db->loadRowList();
            foreach ($rResult as $aRow) {
                return ($aRow[0] == $standard_Plus_SPS) ? true : false;
            }
        } else {
            return false;
        }
    }



    /**
     * @param $db
     * @param $forceReload
     */
    public static function loadParametres(JDatabase $db, $forceReload)
    {

        if (!isset(Spstools::$margeMinuteSMS) || $forceReload) {

            $query = "SELECT idparametres, notification_actif, attente_sms, '', alerte_astreinte_sms, h1ast_off, h2ast_on, h3ast_off, h4ast_on, numAstreinte1, numAstreinte2, margeMinuteSMS,
            mailAstreinte, margeMinuteSMS, margeMinuteSMS, modeComptage, comptageSmsStarter, comptageNotifErreurSms, comptageMsgErreurSMS, timeMinFuel, timeMinWash, timeMinBetweenReads, client_id,
            horaireNumAstreinte, debutHoraireN1, finHoraireN1
            FROM jos_parametres LIMIT 1";

            $db->setQuery($query);
            $db->query();
            //$num_rows = $db->getNumRows();
            $rResult = $db->loadRowList();
            Spstools::$notification_actif = $rResult[0][1];
            Spstools::$attente_sms = $rResult[0][2];
            Spstools::$alerte_astreinte_reveil = $rResult[0][3];
            Spstools::$alerte_astreinte_sms = $rResult[0][4];
            Spstools::$h1ast_off = $rResult[0][5];
            Spstools::$h2ast_on = $rResult[0][6];
            Spstools::$h3ast_off = $rResult[0][7];
            Spstools::$h4ast_on = $rResult[0][8];
            Spstools::$numdefAstreinte1 = $rResult[0][9];
            Spstools::$numdefAstreinte2 = $rResult[0][10];
            Spstools::$margeMinuteSMS = $rResult[0][11];
            Spstools::$maildefAstreinte = $rResult[0][12];
            Spstools::$margeMinuteSMS2 = $rResult[0][13];
            Spstools::$margeMinuteSMSArrivee = $rResult[0][14];
            Spstools::$modeComptage = $rResult[0][15];
            Spstools::$comptageSmsStarter = $rResult[0][16];
            Spstools::$comptageNotifErreurSms = $rResult[0][17];
            Spstools::$comptageMsgErreurSMS = $rResult[0][18];
            Spstools::$timeMinFuel = $rResult[0][19];
            Spstools::$timeMinWash = $rResult[0][20];
            Spstools::$timeMinBetweenReads = $rResult[0][21];
            Spstools::$client_id = $rResult[0][22];
            Spstools::$horaireNumAstreinte = $rResult[0][23];
            Spstools::$debutHoraireN1 = $rResult[0][24];
            Spstools::$finHoraireN1 = $rResult[0][25];
        }
    }


    /**
     * @param $datedebut
     * @param $datefinale
     * @return array
     */
    public static function createExport($datedebut, $datefinale)
    {
        $user =& JFactory::getUser();

        $total = '';
        $dateDeb = new DateTime($datedebut);
        $dateFin = new DateTime($datefinale);

        $lignes = array();

        for ($dateencours = $dateDeb->format('Y-m-d'); $dateDeb->format('Y-m-d') <= $dateFin->format('Y-m-d'); $dateencours = $dateDeb->modify('+1 day')->format('Y-m-d')) {

            $ch = curl_init();

            $param = http_build_query(array(
                'date_cible' => $dateencours,
                'expuid' => $user->id
            ));
            $localuri=substr($_SERVER['REQUEST_URI'],0,strrpos(substr($_SERVER['REQUEST_URI'],0,strrpos($_SERVER['REQUEST_URI'],'/')),'/'));
            curl_setopt($ch, CURLOPT_URL, /*"http://127.0.0.1/depot-git/spseffia"*/ sprintf(
                    "%s://%s%s",
                    isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ? 'https' : 'http',
                    $_SERVER['SERVER_NAME'],
                    //$_SERVER['REQUEST_URI']
                //);
                $localuri."/server_processing_plus.php"));
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $param);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
//--- Start buffering
            ob_start();
            $result = curl_exec($ch);
//--- End buffering and clean output
            ob_end_clean();

            curl_close($ch);
            $total .= $result;
            // $json_str = "{'aintlist':[4,3,2,1], 'astringlist':['str1','str2']}";

            $json_obj = json_decode($result);


            foreach ($json_obj->aaData as $curraaData) {
                $fields = Spstools::objectToArray($curraaData);
                if (!is_null($fields)) {
                    unset($fields['DT_RowClass']);
                    unset($fields['details']);
                    unset($fields['action']);

                    //on range.....


                    //array_values($fields);
                    $currligne = array();
                    //$currligne[] = $fields['DT_RowId'];
                    $currligne[] = $fields['etat'];
                    $currligne[] = $fields['date'];
                    $currligne[] = $fields['libelle'];
                    $currligne[] = $fields['societe'];
                    $currligne[] = $fields['codesms'];
                    $currligne[] = stristr($fields['numchauf'], '<br/>', true);
                    $currligne[] = substr(stristr($fields['numchauf'], '<br/>'), 5);
                    if (substr(substr($fields['hdep'], 0, 8), 0, 1) == '<') $currligne[] = ''; else $currligne[] = substr($fields['hdep'], 0, 8); //remove <img
                    $currligne[] = Spstools::returnEtat($fields['etatPriseDeService']);
                    $currligne[] = Spstools::returnEcart($fields['hdep']);
                    if (substr(substr($fields['hPriseClient'], 0, 8), 0, 1) == '<') $currligne[] = ''; else $currligne[] = substr($fields['hPriseClient'], 0, 8); //remove <img
                    $currligne[] = Spstools::returnEtat($fields['etatPriseClient']);
                    $currligne[] = Spstools::returnEcart($fields['hPriseClient']);


                    for ($ind = 1; $ind <= 10; $ind++) {
                        if (strlen($fields['libelleLieuInter' . $ind]) > 0) {
                            $currligne[] = $fields['libelleLieuInter' . $ind];
                            if (substr(substr($fields['hLieuInter' . $ind], 0, 8), 0, 1) == '<') $currligne[] = ''; else $currligne[] = substr($fields['hLieuInter' . $ind], 0, 8); //remove <img
                            $currligne[] = Spstools::returnEtat($fields['etatLI' . $ind]);
                            $currligne[] = Spstools::returnEcart($fields['hLieuInter' . $ind]);
                        }
                    }

                    if (substr(substr($fields['hFindeService'], 0, 8), 0, 1) == '<') $currligne[] = ''; else $currligne[] = substr($fields['hFindeService'], 0, 8);
                    $currligne[] = Spstools::returnEtat($fields['etatFinDeService']);
                    $currligne[] = Spstools::returnEcart($fields['hFindeService']);

                    $lignes[] = $currligne;
                    //fputcsv($fp, $result);

                }
            }


        }

        $lignes = Spstools::array_unique_tree($lignes);
        return $lignes;
    }

    public static function createFromEcranSuiviExport($datedebut, $datefinale)
    {
        $user =& JFactory::getUser();

        $total = '';
        $dateDeb = new DateTime($datedebut);
        $dateFin = new DateTime($datefinale);

        $lignes = array();

        for ($dateencours = $dateDeb->format('Y-m-d'); $dateDeb->format('Y-m-d') <= $dateFin->format('Y-m-d'); $dateencours = $dateDeb->modify('+1 day')->format('Y-m-d')) {

            $ch = curl_init();

            $param = http_build_query(array(
                'date_cible' => $dateencours,
                'expuid' => $user->id
            ));


            $localuri=substr($_SERVER['REQUEST_URI'],0,strrpos(substr($_SERVER['REQUEST_URI'],0,strrpos($_SERVER['REQUEST_URI'],'/')),'/'));

            curl_setopt($ch, CURLOPT_URL, /*"http://127.0.0.1/depot-git/spseffia"*/ sprintf(
                "%s://%s%s",
                isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ? 'https' : 'http',
                $_SERVER['SERVER_NAME'],

                //);
                $localuri."/server_processing_plus.php"));
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $param);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
//--- Start buffering
            ob_start();
            $result = curl_exec($ch);

//--- End buffering and clean output
            ob_end_clean();

            curl_close($ch);
            $total .= $result;
            // $json_str = "{'aintlist':[4,3,2,1], 'astringlist':['str1','str2']}";

            $json_obj = json_decode($result,true);

$onedayarray = Spstools::createSuiviExport($json_obj);
           $lignes=array_merge($lignes,$onedayarray);





        }

        $lignes = Spstools::array_unique_tree($lignes);
        return $lignes;
    }



    /**
     * @param $output array
     * @return array
     */
    public static function createComptageExport($output)
    {

        $currligne = array();

        //CSV Header
        //$currligne[] = 'Id';
        $currligne[] = 'Log';
        $currligne[] = 'NumCar';
        $currligne[] = 'Voyageurs';
        $currligne[] = 'Expediteur';
        $currligne[] = 'Observations';
        $lignes[] = $currligne;

            foreach ($output[aaData] as $curraaData) {
                $fields = Spstools::objectToArray($curraaData);
                if (!is_null($fields)) {
                    unset($fields['DT_RowClass']);

                    $currligne = array();
                    //$currligne[] = $fields['DT_RowId'];
                    $datec1 = $fields['dateheure'];
                    $currligne[] = substr($datec1,8,2).'/'.substr($datec1,5,2).'/'.substr($datec1,0,4).' '.substr($datec1,11);
                    //$currligne[] = $datec1;
                    $currligne[] = $fields['numcirculation'];
                    $currligne[] = $fields['voyageurs'];
                    $currligne[] = $fields['numExpediteur'];
                    $currligne[] = $fields['observation'];

                    $lignes[] = $currligne;
                }
            }




        $lignes = Spstools::array_unique_tree($lignes);
        return $lignes;
    }

    /**
     * @param $output array
     * @return array
     */
    public static function createSuiviExport($output)
    {

        $currligne = array();

        //CSV Header
        $currligne[] = 'Id';
        $currligne[] = 'Date';
        $currligne[] = 'Etat';
        $currligne[] = 'Libelle';
        $currligne[] = 'Lieu Depart';
        $currligne[] = 'Vehicule';
        $currligne[] = 'Conducteur';
        $currligne[] = 'Code attendu';
        $currligne[] = 'Libelle Etape';
        $currligne[] = 'Heure Etape';
        $currligne[] = 'Ecart';
        $currligne[] = 'Heure valid. Etape';
        $currligne[] = 'Utilisateur valid. Etape';
        $currligne[] = 'Etat Etape';
        $currligne[] = 'Moyen valid. Etape';
        $lignes[] = $currligne;

        $currligne=array();
        //$currligne[]='BIENTOT DISPONIBLE';
        //$lignes[] = $currligne;


        foreach ($output[aaData] as $curraaData) {
            $fields = Spstools::objectToArray($curraaData);
            if (!is_null($fields)) {
                unset($fields['DT_RowClass']);
                $matches=null;




                for($ind=1;$ind<=count($fields['etatEtapes']);$ind++){
                    $currligne = array();
                $currligne[] = $fields['DT_RowId'];
                $currligne[] = $fields['date'];//TODO : add j+1 to date $fields['jplus1Etapes'][$ind];
                $currligne[] = $fields['etat'];
                $currligne[] = $fields['libelle'];
                $currligne[] = $fields['lieudep'];

                //check vehicule syntax
                preg_match("/^<a.*>(.*)<\/a>.*$/", $fields['vehicule'], $matches);
                $currligne[] = $matches[1];
                $currligne[] = $fields['numchauf'];
                $currligne[] = $fields['codesms'];
                $currligne[] = $fields['libelleEtapes'][$ind];
                preg_match("/^.*([0-2][0-9]\:[0-5][0-9]\:[0-9][0-9]){1}(.*<br\/>(.*)m){0,1}[^$]*$/", $fields['heurePrevueEtapes'][$ind], $matches);
                $currligne[] = $matches[1];
                if(isset($matches[3])) $currligne[] = $matches[3]; else $currligne[]="";

                    $currligne[] = $fields['dateEtapes'][$ind];
                    $currligne[] = $fields['user_idEtapes'][$ind];
                    if(strpos($fields['etatEtapes'][$ind],'bullet_grey')!==false) $currligne[] = 'S';
                    elseif(strpos($fields['etatEtapes'][$ind],'alarm-bell')!==false || strpos($fields['etatEtapes'][$ind],'cross')!==false) $currligne[] = 'L';
                    elseif(strpos($fields['etatEtapes'][$ind],'tick_button')!==false) $currligne[] = 'R';
                    else $currligne[] = 'N';


                    //$currligne[] = $fields['etatEtapes'][$ind];
                    $currligne[] = $fields['moyenEtapes'][$ind];
/* 'Heure valid. Etape';

        $currligne[] = 'Utilisateur valid. Etape';
        $currligne[] = 'Etat Etape';
        $currligne[] = 'Moyen valid. Etape';*/


                $lignes[] = $currligne;
                }
            }
        }





        $lignes = Spstools::array_unique_tree($lignes);
        return $lignes;
    }

    /**
     * @param $d
     * @return array
     */
    public static function objectToArray($d)
    {
        if (is_object($d)) {
            // Gets the properties of the given object
            // with get_object_vars function
            $d = get_object_vars($d);
        }

        if (is_array($d)) {
            /*
            * Return array converted to object
            * Using __FUNCTION__ (Magic constant)
            * for recursive call
            */
            return array_map(null, $d);
        } else {
            // Return array
            return $d;
        }
    }

    /**
     * @param $etat
     * @return string
     */
    private static function returnEtat($etat)
    {
        if ($etat == '') return '';
        if (strpos($etat, 'images/alarm-bell.png') !== false) return "L";
        if (strpos($etat, 'images/tick_button.png') !== false) return "R";
        if (strpos($etat, 'images/bullet_grey.png') !== false) return "S";
        return "";

    }



    /**
     * @param $heure
     * @return int
     */
    private static function returnEcart($heure)
    {
        $posp = strpos($heure, '+');
        $posm = strpos($heure, '-');
        if (!$posp && !$posm) return 0;
        if ($posp) $ecart = substr($heure, $posp, strpos($heure, 'm') - $posp);
        else $ecart = substr($heure, $posm, strpos($heure, 'm') - $posm);
        if (strpos($ecart, 'm')) $ecart = strpos($ecart, 'm', true);
        return intval($ecart);

    }

    /**
     * Return unique values from a tree of values
     *
     * @param array $array_tree
     * @return array
     * @author memandeemail at gmail dot com
     */
    public static function array_unique_tree($array_tree)
    {
        $will_return = array();
        $vtemp = array();
        foreach ($array_tree as $tkey => $tvalue) $vtemp[$tkey] = Spstools::implode_with_key('&', $tvalue, '=');
        foreach (array_keys(array_unique($vtemp)) as $tvalue) $will_return[$tvalue] = $array_tree[$tvalue];
        return $will_return;
    }

    /** same as implode but with keys
    * @param string $glue Oque colocar entre as chave => valor
    * @param array $pieces Valores
    * @param string $hifen Separar chave da array do valor
    * @return string
    * @author memandeemail at gmail dot com
    */
    public static function implode_with_key($glue = null, $pieces, $hifen = ',')
    {
        $return = null;
        foreach ($pieces as $tk => $tv) $return .= $glue . $tk . $hifen . $tv;
        return substr($return, 1);
    }

    /**
     * @param $db
     * @param $datedebut
     * @param $datefinale
     * @return array
     */
    public static function createStat(JDatabase $db, $datedebut, $datefinale)
    {
        $user =& JFactory::getUser();


        $dateDeb = new DateTime($datedebut);
        $dateFin = new DateTime($datefinale);


        for ($dateencours = $dateDeb->format('Y-m-d'); $dateDeb->format('Y-m-d') <= $dateFin->format('Y-m-d'); $dateencours = $dateDeb->modify('+1 day')->format('Y-m-d')) {
            //par jour
            //nb de service
            $query = "select s.societe,count(idplanning) from #__planning p,#__service s where p.date = DATE('" . $dateencours . "') and p.service_idservice=s.idservice group by s.societe";
            $db->setQuery($query);
            $query = $db->query();
            $num_rows = $db->getNumRows();
            if ($num_rows > 0) {
                $rResult = $db->loadRowList();
                foreach ($rResult as $aRow) {
                    $depot = $aRow[0];

                    if (is_null($depot)) $depot = "defaut";
                    $nbService[$dateencours][$depot] = $aRow[1];
                }
            }
        }

        $aFaire = array();
        while (($nbdated = current($nbService)) !== false) {
            if ($nbdated > 0) {
                $aFaire[] = key($nbService);
            }
            next($nbService);
        }

        $nbLieuInter = array();
        $nbAlerte = array();
        $nbsmsHPrecu = array();
        $moysmsHPrecutroptot=array();
        $nbsmsHPrecutroptot=array();
        $nbsmsOKrecu = array();

        while (($dateencours = current($aFaire)) !== false) {
            //nb d'etapes a valider
            $query = 'SELECT s.societe,count(idetape_planning)
			FROM #__service s, #__planning p, #__etape_planning ep
			INNER JOIN #__usersps usps
            ON usps.user_id = ' . $user->id . '
            LEFT JOIN #__depot depot
            ON FIND_IN_SET(depot.iddepot,usps.liste_iddepot)
            where p.date = DATE(\'' . $dateencours . '\')			and p.service_idservice = s.idservice';
            $query .= ' and (s.societe = depot.societe or ((s.societe is null or s.societe = \'\') and depot.iddepot =  usps.liste_iddepot))';
            $query .= ' and ep.planning_idplanning = p.idplanning group by s.societe';
            $db->setQuery($query);
            $query = $db->query();
            $num_rows = $db->getNumRows();

            if ($num_rows > 0) {
                $rResult = $db->loadRowList();
                foreach ($rResult as $aRow) {
                    $depot = $aRow[0];

                    if (is_null($depot)) $depot = "defaut";
                        $nbLieuInter[$dateencours][$depot] = $aRow[1];
                }
            } else {
                $nbLieuInter[$dateencours] = 0;
            }

            //nombre d'alerte

            $query = 'SELECT s.societe,count(*)
            FROM #__service s, #__planning p, #__etape_planning ep
            INNER JOIN #__usersps usps
            ON usps.user_id = ' . $user->id . '
            LEFT JOIN #__depot depot
            ON FIND_IN_SET(depot.iddepot,usps.liste_iddepot)
            where p.date = DATE(\'' . $dateencours . '\')			and p.service_idservice = s.idservice';
            $query .= ' and (s.societe = depot.societe or ((s.societe is null or s.societe = \'\') and depot.iddepot =  usps.liste_iddepot))';
            $query .= ' and ep.planning_idplanning = p.idplanning';
            $query .= ' and ep.etat = \'L\' group by s.societe';
            $db->setQuery($query);
            $query = $db->query();
            $num_rows = $db->getNumRows();
            $rResult = $db->loadRowList();

            if ($num_rows > 0) {
                foreach ($rResult as $aRow) {
                    $depot = $aRow[0];

                    if (is_null($depot)) $depot = "defaut";
                    $nbAlerte[$dateencours][$depot] = $aRow[1];
                }

            } else {
                $nbAlerte[$dateencours]['defaut'] = 0;
            }

            //sms hors periode
            $query = 'select s.societe,count(r.idreceivedsms) from jos_receivedsms r,jos_planning p,jos_etape_planning ep,jos_service s
where dateheure>DATE(\'' . $dateencours . '\') and dateheure<DATE_ADD(DATE(\'' . $dateencours . '\'),INTERVAL 24 hour) and p.date=\'' . $dateencours . '\'
and p.service_idservice = s.idservice
and ep.planning_idplanning=p.idplanning
and r.content like CONCAT(p.codesms,\'%\') and motifRejet like \'Hors%\'
group by s.societe';
            $db->setQuery($query);
            $query = $db->query();
            $num_rows = $db->getNumRows();
            $rResult = $db->loadRowList();

            if ($num_rows > 0) {
                foreach ($rResult as $aRow) {
                    $depot = $aRow[0];

                    if (is_null($depot)) $depot = "defaut";

                    $nbsmsHPrecu[$dateencours][$depot] = $aRow[1];
                }

            } else {
                $nbsmsHPrecu[$dateencours]['defaut'] = 0;
            }

            //moy des temps sms arrives trop tot  /////rajouter un gros AVG()

            $query='select s.societe,count(r.idreceivedsms),ROUND(avg(TIME_TO_SEC(TIMEDIFF(TIME(e.heure),TIME(r.dateheure))))/60) avance_moyenne_minute
from jos_receivedsms r,jos_planning p,jos_etape_planning ep,jos_service s,jos_etape e
where dateheure>DATE(\'' . $dateencours . '\') and dateheure<DATE_ADD(DATE(\'' . $dateencours . '\'),INTERVAL 24 hour) and p.date=\'' . $dateencours . '\'
            and p.service_idservice = s.idservice
            and ep.planning_idplanning=p.idplanning
            and e.idetape=ep.etape_idetape
            and r.content like CONCAT(p.codesms,\'%\') and motifRejet like \'Hors%\'
            and r.dateheure<DATE_ADD(DATE(\'' . $dateencours . '\'),INTERVAL TIME_TO_SEC(e.heure) second)
            group by s.societe';
            $db->setQuery($query);
            $query = $db->query();
            $num_rows = $db->getNumRows();
            $rResult = $db->loadRowList();

            if ($num_rows > 0) {
                foreach ($rResult as $aRow) {
                    $depot = $aRow[0];

                    if (is_null($depot)) $depot = "defaut";

                    $nbsmsHPrecutroptot[$dateencours][$depot] = $aRow[1];
                    $moysmsHPrecutroptot[$dateencours][$depot] = $aRow[2];
                }

            } else {
                $nbsmsHPrecu[$dateencours]['defaut'] = 0;
                $moysmsHPrecutroptot[$dateencours]['defaut'] = 0;
            }

            //sms ok
            $query = "SELECT s.societe,count(*)
            FROM #__receivedsms r,#__planning p,#__service s
            where p.idplanning=r.planning_idplanning
            and p.service_idservice = s.idservice
             and DATE(dateheure) = DATE('" . $dateencours . "')
                and (motifRejet like '' or motifRejet is null)
            group by s.societe";
            $db->setQuery($query);
            $query = $db->query();
            $num_rows = $db->getNumRows();
            $rResult = $db->loadRowList();

            if ($num_rows > 0) {
                foreach ($rResult as $aRow) {
                    $depot = $aRow[0];

                    if (is_null($depot)) $depot = "defaut";
                    $nbsmsOKrecu[$dateencours][$depot] = $aRow[1];
                }

            } else {
                $nbsmsOKrecu[$dateencours]['defaut'] = 0;
            }

            next($aFaire);
        }


        reset($aFaire);
        $cpt = 0;
        $ligne = array();

        while (($dateencours = current($aFaire)) !== false) {
            foreach($nbService[$dateencours] as $depot=>$value):

            $ligne[$cpt][] = $dateencours;
            $ligne[$cpt][] = $depot;
            $ligne[$cpt][] = isset($nbService[$dateencours][$depot])?$nbService[$dateencours][$depot]:0;
            $ligne[$cpt][] = isset($nbLieuInter[$dateencours][$depot])?$nbLieuInter[$dateencours][$depot]:0;
            $ligne[$cpt][] = isset($nbAlerte[$dateencours][$depot])?$nbAlerte[$dateencours][$depot]:0;
            $ligne[$cpt][] = isset($nbsmsOKrecu[$dateencours][$depot])?$nbsmsOKrecu[$dateencours][$depot]:0;
            $ligne[$cpt][] = isset($nbsmsHPrecu[$dateencours][$depot])?$nbsmsHPrecu[$dateencours][$depot]:0;
            $ligne[$cpt][] = isset($nbsmsHPrecutroptot[$dateencours][$depot])?$nbsmsHPrecutroptot[$dateencours][$depot]:0;
            $ligne[$cpt][] = isset($moysmsHPrecutroptot[$dateencours][$depot])?$moysmsHPrecutroptot[$dateencours][$depot]:0;
            $cpt++;
            endforeach;
            next($aFaire);
        }
        return $ligne;
    }

    /**
     * purge les tables sentcalls, receivedsms, service et planning dont les enregistrements sont plus vieux que x mois
     * @param $db
     * @param $nbmois
     * @return string
     */
    public static function purge(JDatabase $db, $nbmois)
    {
        if ($nbmois < 2) $nbmois = 2;

        //sentcalls
        $query = "delete from jos_sentcalls where DATE_ADD(dateheure,INTERVAL " . $nbmois . " MONTH)<NOW()";
        $db->setQuery($query);
        $query = $db->query();


        //receivedsms
        $query = "delete from jos_receivedsms where DATE_ADD(dateheure,INTERVAL " . $nbmois . " MONTH)<NOW()";
        $db->setQuery($query);
        $query = $db->query();


        //service
        $query = "delete from jos_service where idservice in (select p.service_idservice from jos_planning p where DATE_ADD(date,INTERVAL " . $nbmois . " MONTH)<NOW())";
        $db->setQuery($query);
        $query = $db->query();

        //planning
        $query = "delete from jos_planning where DATE_ADD(date,INTERVAL " . $nbmois . " MONTH)<NOW()";
        $db->setQuery($query);
        $query = $db->query();

        return "";

    }

    /**
     * @param $date_src
     * @param $date_dest
     */
    public static function copyPlanningDay($date_src,$date_dest){
    $db =& JFactory::getDBO();

       //select plannings of date_src
        //foreach insert planning in date_dest
        //get insertId
        //insert etapes_plannings with insertId

        $query = "SELECT idplanning FROM `#__planning` p1 WHERE p1.date like date('".$date_src."')";

        $db->setQuery($query);
        $db->query();
        $num_rows = $db->getNumRows();
        if ($num_rows > 0) {
            $rResult = $db->loadRowList();
            foreach ($rResult as $aRow) {
                $query = "INSERT INTO `#__planning` (date,etat,service_idservice,codesms,client_id)
                SELECT date('".$date_dest."'),'N',service_idservice,codesms,client_id FROM `#__planning` p1 WHERE
                p1.idplanning = ".$aRow[0];

                $db->setQuery($query);
                $db->query();

                $newIdPlanning = $db->insertid();


                $query = "INSERT INTO `#__etape_planning` (planning_idplanning,user_id,etape_idetape,type)
    SELECT ".$newIdPlanning.", null, etape_idetape, type FROM `#__etape_planning` pe1 WHERE
pe1.planning_idplanning = ".$aRow[0];

                $db->setQuery($query);
                $db->query();
            }
        }




}

    public static function forceEtape(JDatabase $db, $idplanning) {
        $newetat = Spstools::getNextStatus($idplanning, $db);
        $idetape = Spstools::getNextEtape($idplanning, $db);
        $user =& JFactory::getUser();



        if ($newetat == 'R') $newetat = 'S';
        if ($idetape != "")
        {
            $query = "Update `#__etape_planning` set etat=IFNULL(etat,'S'), dateEtat=NOW(), user_id=".$user->id." where idetape_planning=".$idetape;
            $db->setQuery($query);
            $db->query();
            $query = "Update `#__planning` set etat ='".$newetat."', aAlerterSMS = 'F' WHERE idplanning = ".$idplanning;
            $db->setQuery($query);
            $db->query();

        }
        else
        {
            if ($newetat != "N") {
                $query = "Update `#__planning` set etat ='".$newetat."', aAlerterSMS = 'F' WHERE idplanning = ".$idplanning;
                $db->setQuery($query);
                $db->query();

            } else {
                echo "<h3>Le trajet n'a pas besoin d'etre forc&eacute;</h3>";
            }
        }

    }

    public static function ValidEcranEtape(JDatabase $db=null, $idplanning,$display=true,$user=null,$chauffeurScreenNomPrenom=null) {
        if(is_null($db)) $db = & JFactory::getDBO();
        $newetat = Spstools::getNextStatus($idplanning, $db);
        $idetape = Spstools::getNextEtape($idplanning, $db);
        if(is_null($user)) $user =& JFactory::getUser();



        if ($newetat == 'R' && is_null($chauffeurScreenNomPrenom)) $newetat = 'S';
        if ($idetape != "")
        {
	    if (!is_integer($user->id) ){
		$user->id="'".$user->id."'";
	    }
            $query = "Update `#__etape_planning` set etat=IFNULL(etat,'R'), dateEtat=NOW(), user_id=".$user->id." where idetape_planning=".$idetape;
            $db->setQuery($query);
            $db->query();
            $query = "Update `#__planning` set etat ='".$newetat."', aAlerterSMS = 'F' WHERE idplanning = ".$idplanning;
            $db->setQuery($query);
            $db->query();

        }
        else
        {
            if ($newetat != "N") {
                $query = "Update `#__planning` set etat ='".$newetat."', aAlerterSMS = 'F' WHERE idplanning = ".$idplanning;
                $db->setQuery($query);
                $db->query();

            } else {
                if ($display) echo "<h3>Le trajet n'a pas besoin d'etre forc&eacute;</h3>";
            }
        }

    }

    public static function savePlanningService($form){
/**Author : Xavier TRABET Tous droits reserves 2012**/
//generer la recurrence du service planifié
//1 charge les donnees du service en question
//2 si recurrence alors generation recurrence sinon rien

$db =& JFactory::getDBO();

if ($form->data['idplanning']<>'') { //planning exists in db
    $query = "SELECT service_idservice FROM `#__planning` WHERE idplanning = ".$form->data['idplanning'];
    $db->setQuery($query);
    $query = $db->query();
    $num_rows = $db->getNumRows();
    $rResult = $db->loadRowList();
    if($num_rows != '0') {
        // service exists in database....
        $service_before_update = $rResult[0][0];

        //suppression des planning recurrents à venir
        $query = "DELETE FROM `#__planning`  WHERE recurrencekey is not null and recurrencekey like '".$service_before_update."%"."' and
                NOW() < STR_TO_DATE(CONCAT(date,' ','23:59:59'),'%Y-%m-%d %H:%i:%s')";
        $db->setQuery($query);
        $db->query();

        //update planning
        $query = "update `#__planning` set 
        date='".$form->data['date']."', /*etat='*/".''/*$form->data['etat']*/."/*',*/ service_idservice='".$service_before_update."', codesms='".$form->data['codesms']."', recurrencekey='".$form->data['recurrencekey']."', 
        numTelephone='".$form->data['numTelephone']."', numTelephoneBis='".$form->data['numTelephoneBis']."', client_id='".$form->data['client_id']."', date_last_modif=now()
        WHERE idplanning = ".$form->data['idplanning'];
        $db->setQuery($query);
        $db->query();
    }
}

 $query = "SELECT idservice,recurrence,date_debut,date_fin,lundi,mardi,mercredi,jeudi,vendredi,samedi,dimanche,liste_idcalendrier,hdep 
 FROM `#__service` WHERE idservice = ".intval($form->data['service_idservice']);
    $db->setQuery($query);
    $db->query();
    $num_rows = $db->getNumRows();
    $rResult = $db->loadRowList();
    if($num_rows != '0') {
        // exist in database....
        $idservice = $rResult[0][0];
        $recurrence = $rResult[0][1];
        $date_debut = $rResult[0][2];
        $date_fin = $rResult[0][3];
        $lundi = $rResult[0][4];
        $mardi = $rResult[0][5];
        $mercredi = $rResult[0][6];
        $jeudi = $rResult[0][7];
        $vendredi = $rResult[0][8];
        $samedi = $rResult[0][9];
        $dimanche = $rResult[0][10];
        $liste_idcalendrier = $rResult[0][11];
        $hdep = $rResult[0][12];

        $recurrencekey = $form->data['recurrencekey'];
        $date = $form->data['date'];
        $etat = $form->data['etat'];
        $codesms = $form->data['codesms'];

        if (is_null($liste_idcalendrier) || $liste_idcalendrier == ''){
            if ($form->data['idplanning']=='') { //planning does not exist in db
            //sauvegarde normale
                $query = "INSERT INTO `#__planning` (date,etat,service_idservice,codesms,recurrencekey,numTelephone,numTelephoneBis,client_id,date_last_modif) 
                VALUES(DATE(STR_TO_DATE('".$date."', '%Y-%m-%d')), '".$etat."','".$idservice."','".$codesms."','','".$form->data['numTelephone']."','".$form->data['numTelephoneBis']."','".$form->data['client_id']."',now())";
            $db->setQuery($query);
            $db->query();

            //insert etape_planning
            $newIdPlanning = $db->insertid();

            Spstools::insertEtapeServiceIfNotExist($db,$idservice,$hdep);


            $query = "INSERT INTO `#__etape_planning` (planning_idplanning,user_id,etape_idetape,type)
                    SELECT ".$newIdPlanning.", null, idetape, type FROM `#__etape` e1 WHERE e1.service_idservice=".$idservice;

            $db->setQuery($query);
            $db->query();
            }else {
                $newIdPlanning = $form->data['idplanning'];
            }
            return $newIdPlanning;
        }
        else {

            //si service recurrent alors on supprime tous ceux dont planning "date" >= aujourdhui() et hdep > heurenow() avec idservice
            if (!($recurrence == "Aucune" || $recurrence == '')) {
                //suppression des planning recurrents à venir
                $query = "DELETE FROM `#__planning`  WHERE service_idservice = '".$idservice."' and
                NOW() < STR_TO_DATE(CONCAT(date,' ','".$hdep."'),'%Y-%m-%d %H:%i:%s')";
                $db->setQuery($query);
                $db->query();

            }




            $query = "SELECT idcalendrier,liste_jours FROM `#__calendrier` WHERE idcalendrier in (".$liste_idcalendrier.")";
            $db->setQuery($query);
            $query = $db->query();
            $num_rows = $db->getNumRows();
            $rResult = $db->loadRowList();
            $liste_jours='';

            if ($num_rows > 0) {
                foreach ($rResult as $aRow) {
                    //$idcalendrier = $aRow[0];
                    //union de tous les jours des calendriers (si plusieurs)
                    $liste_jours = $liste_jours.", ".$aRow[1];
                }

                $recurrencekey = $idservice.$recurrence.$date_debut;

                /*recurrence possible :
                Aucune=Aucune
                Jour=Jour
                Hebdomadaire=Hebdomadaire
                Quinzaine=Quinzaine
                Mensuelle=Mensuelle*/


                ////****recurrence : si aucune
                if ($recurrence == "Aucune" || $recurrence == '') {
                    //sauvegarde normale
                    $query = "INSERT INTO `#__planning` (date,etat,service_idservice,codesms,recurrencekey,numTelephone,numTelephoneBis,client_id) VALUES(DATE(STR_TO_DATE('".$date."', '%Y-%m-%d')), '".$etat."','".$idservice."','".$codesms."','".$recurrencekey."','".$form->data['numTelephone']."','".$form->data['numTelephoneBis']."','".$form->data['client_id']."')";
                    $db->setQuery($query);
                    $db->query();


                    //insert etape_planning
                    $newIdPlanning = $db->insertid();

                    Spstools::insertEtapeServiceIfNotExist($db,$idservice,$hdep);


                    $query = "INSERT INTO `#__etape_planning` (planning_idplanning,user_id,etape_idetape,type)
                    SELECT ".$newIdPlanning.", null, idetape, type FROM `#__etape` e1 WHERE e1.service_idservice=".$idservice;

                    $db->setQuery($query);
                    $db->query();

                }

                $aujourdhui = new DateTime();
                if ($date_debut == '') $date_debut = $aujourdhui->format('Y-m-d');
                if ($date_fin == '') $date_fin = $aujourdhui->format('Y-m-d');
                $dateDeb = DateTime::createFromFormat('Y-m-d', $date_debut);
                $dateFin = DateTime::createFromFormat('Y-m-d', $date_fin);
                $dateDebPlanning = DateTime::createFromFormat('Y-m-d', $date);

                $jourscoches = '';
                if ($lundi == '1') $jourscoches = '1, ';
                if ($mardi == '1') $jourscoches .= '2, ';
                if ($mercredi == '1') $jourscoches .= '3, ';
                if ($jeudi == '1') $jourscoches .= '4, ';
                if ($vendredi == '1') $jourscoches .= '5, ';
                if ($samedi == '1') $jourscoches .= '6, ';
                if ($dimanche == '1') $jourscoches .= '0';

                if ($jourscoches == '') {
                    $jourscoches = $dateDeb -> format('w');
                }


                if ($dateDebPlanning->format('Ymd') > $dateDeb->format('Ymd')) {
                    $dateDeb = $dateDebPlanning;
                }
                if ($aujourdhui->format('Ymd') > $dateDeb->format('Ymd')) {
                    $dateDeb = $aujourdhui;
                }

                ////****recurrence : si jour
                if ($recurrence == "Jour") {

                    while ($dateDeb -> format('Ymd') <= $dateFin -> format('Ymd'))
                    {
                        $pos = strpos($liste_jours, ' '.$dateDeb -> format('j/n/Y'));
                        if ($pos !== false) {

                            //si dayofweek(j) in jourcoche alors insert planning
                            $pos2 = strpos($jourscoches, $dateDeb -> format('w'));
                            if ($pos2 !== false) {
                                $query = "INSERT INTO `#__planning` (date,etat,service_idservice,codesms,recurrencekey,numTelephone,numTelephoneBis,client_id) VALUES(DATE(STR_TO_DATE('".$dateDeb -> format('Y-m-d')."', '%Y-%m-%d')), '".$etat."','".$idservice."','".$codesms."','".$recurrencekey."','".$form->data['numTelephone']."','".$form->data['numTelephoneBis']."','".$form->data['client_id']."')";
                                $db->setQuery($query);
                                $db->query();

                                //insert etape_planning
                                $newIdPlanning = $db->insertid();

                                Spstools::insertEtapeServiceIfNotExist($db,$idservice,$hdep);


                    $query = "INSERT INTO `#__etape_planning` (planning_idplanning,user_id,etape_idetape,type)
                    SELECT ".$newIdPlanning.", null, idetape, type FROM `#__etape` e1 WHERE e1.service_idservice=".$idservice;

                    $db->setQuery($query);
                    $db->query();
                            }
                        }
                        $dateDeb -> modify('+1 day');
                    }
                    //for j=date debut, j<=date de fin, j = j + 1
                    //si j in liste_jours du calendrier
                    //si aucun jour coche alors jourcoche=dayofweek(date_debut)
                    //si dayofweek(j) in jourcoche alors insert planning
                    //end for

                }

                ////****recurrence : si hebdo
                if ($recurrence == "Hebdomadaire") {
                    //si pas jourdecoche alors
                    //  increment = 7
                    //  jourcoche=dayofweek(date_debut)
                    //sinon increment = 1;


                    while ($dateDeb -> format('Ymd') <= $dateFin -> format('Ymd'))
                    {
                        $pos = strpos($liste_jours, ' '.$dateDeb -> format('j/n/Y'));
                        if ($pos !== false) {

                            //si dayofweek(j) in jourcoche alors insert planning
                            $pos2 = strpos($jourscoches, $dateDeb -> format('w'));
                            if ($pos2 !== false) {
                                $query = "INSERT INTO `#__planning` (date,etat,service_idservice,codesms,recurrencekey,numTelephone,numTelephoneBis,client_id) VALUES(DATE(STR_TO_DATE('".$dateDeb -> format('Y-m-d')."', '%Y-%m-%d')), '".$etat."','".$idservice."','".$codesms."','".$recurrencekey."','".$form->data['numTelephone']."','".$form->data['numTelephoneBis']."','".$form->data['client_id']."')";
                                $db->setQuery($query);
                                $db->query();
                                
                                //insert etape_planning
                    $newIdPlanning = $db->insertid();

                                Spstools::insertEtapeServiceIfNotExist($db,$idservice,$hdep);


                    $query = "INSERT INTO `#__etape_planning` (planning_idplanning,user_id,etape_idetape,type)
                    SELECT ".$newIdPlanning.", null, idetape, type FROM `#__etape` e1 WHERE e1.service_idservice=".$idservice;

                    $db->setQuery($query);
                    $db->query();
                            }
                        }
                        $dateDeb -> modify('+1 day');
                    }

                    //for j=date debut, j<=date de fin, j = j + increment
                    //si j in liste_jours du calendrier
                    //et si dayofweek(j) in jourcoche alors insert planning
                    //end for
                }

                ////*****recurrence : si quinzaine
                if ($recurrence == "Quinzaine") {
                    //si pas jourdecoche alors
                    //  increment = 14
                    //  jourcoche=dayofweek(date_debut)
                    //sinon increment = 1;

                    $dateorig = DateTime::createFromFormat('Y-m-d', $date_debut);
                    $intervaldate = $dateorig->diff($aujourdhui);
                    $jourcpt=floor($intervaldate->format('%a'));
                    $numero_semaine = floor($jourcpt/7);



                    while ($dateDeb -> format('Ymd') <= $dateFin -> format('Ymd'))
                    {
                        $pos = strpos($liste_jours, ' '.$dateDeb -> format('j/n/Y'));
                        if ($pos !== false && !($numero_semaine % 2)) {

                            //si dayofweek(j) in jourcoche alors insert planning
                            $pos2 = strpos($jourscoches, $dateDeb -> format('w'));
                            if ($pos2 !== false) {
                                $query = "INSERT INTO `#__planning` (date,etat,service_idservice,codesms,recurrencekey,numTelephone,numTelephoneBis,client_id) VALUES(DATE(STR_TO_DATE('".$dateDeb -> format('Y-m-d')."', '%Y-%m-%d')), '".$etat."','".$idservice."','".$codesms."','".$recurrencekey."','".$form->data['numTelephone']."','".$form->data['numTelephoneBis']."','".$form->data['client_id']."')";
                                $db->setQuery($query);
                                $db->query();
                                
                                //insert etape_planning
                    $newIdPlanning = $db->insertid();

                                Spstools::insertEtapeServiceIfNotExist($db,$idservice,$hdep);


                    $query = "INSERT INTO `#__etape_planning` (planning_idplanning,user_id,etape_idetape,type)
                    SELECT ".$newIdPlanning.", null, idetape, type FROM `#__etape` e1 WHERE e1.service_idservice=".$idservice;

                    $db->setQuery($query);
                    $db->query();
                            }
                        }
                        $jourcpt++;
                        $numero_semaine = floor($jourcpt/7);
                        $dateDeb -> modify('+1 day');
                    }

                    //for j=date debut, j<=date de fin, j = j + increment

                    //si j in liste_jours du calendrier
                    //et si dayofweek(j) in jourcoche ET numero semaine mod 2
                    //alors insert planning
                    //numero_semaine= jour mod 7;
                    //jour++;
                    //end for

                }


                ////****recurrence : si mensuel
                if ($recurrence == "Mensuelle") {
                    //si pas jourdecoche alors
                    //  jourcoche=dayofweek(date_debut)
                    //sinon jour=0
                    $jourcpt=0;
                    $dateDeb2 = $dateDeb;


                    while ($dateDeb -> format('Ymd') <= $dateFin -> format('Ymd')){
                        while ($dateDeb -> format('Ymd') <= $dateFin -> format('Ymd') && $jourcpt<7)
                        {
                            $pos = strpos($liste_jours, ' '.$dateDeb -> format('j/n/Y'));
                            if ($pos !== false) {

                                //si dayofweek(j) in jourcoche alors insert planning
                                $pos2 = strpos($jourscoches, $dateDeb -> format('w'));
                                if ($pos2 !== false) {
                                    $query = "INSERT INTO `#__planning` (date,etat,service_idservice,codesms,recurrencekey,numTelephone,numTelephoneBis,client_id) VALUES(DATE(STR_TO_DATE('".$dateDeb -> format('Y-m-d')."', '%Y-%m-%d')), '".$etat."','".$idservice."','".$codesms."','".$recurrencekey."','".$form->data['numTelephone']."','".$form->data['numTelephoneBis']."','".$form->data['client_id']."')";
                                    $db->setQuery($query);
                                    $db->query();
                                    
                                    //insert etape_planning
                    $newIdPlanning = $db->insertid();

                                    Spstools::insertEtapeServiceIfNotExist($db,$idservice,$hdep);


                    $query = "INSERT INTO `#__etape_planning` (planning_idplanning,user_id,etape_idetape,type)
                    SELECT ".$newIdPlanning.", null, idetape, type FROM `#__etape` e1 WHERE e1.service_idservice=".$idservice;

                    $db->setQuery($query);
                    $db->query();
                                }
                            }
                            $jourcpt++;
                            $dateDeb -> modify('+1 day');
                        }
                        $jourcpt=0;
                        $dateDeb = $dateDeb2 -> modify('+1 month');
                    }

                    //faire
                    //for j=date debut, j<=date de fin || jour == 7, j = j + 1

                    //si j in liste_jours du calendrier
                    //et si dayofweek(j) in jourcoche et semaine = 0
                    //alors insert planning
                    //numero_semaine= jour mod 7;
                    //jour++;
                    //end for

                }
//tant que (date_debut.addMois(incmois) <date de fin)
            }//finsi select calendrier
        }//finsi idcalendrier non vide
    }//finsi select service
    }

private static function insertEtapeServiceIfNotExist(JDatabase $db,$idservice,$hdep,$type=null){
    $query = "SELECT service_idservice FROM `#__etape` e1 WHERE e1.service_idservice=".$idservice;
    $db->setQuery($query);
    $query = $db->query();
    $num_rows = $db->getNumRows();
    $rResult = $db->loadRowList();
    if($num_rows == '0') {
        // not exist in database..
        $query = "INSERT INTO `#__etape` (heure,libelle,jplus1,ordre,service_idservice,type)
                    VALUES('".$hdep."', '','0','1',".$idservice.",".is_null($type)?'null':'\'.$type.\'.'.")";
        $db->setQuery($query);
        $db->query();

    }
}

    public static function getUserClientId($db){
        $clientid=null;
        $user=& JFactory::getUser();
        $query ='SELECT client_id FROM #__usersps usps
            WHERE usps.user_id = ' . $user->id;
        $db->setQuery($query);
        $query = $db->execute();
        $num_rows=$db->getNumRows();
        $rResult = $db->loadRowList();

        if ($num_rows > 0)
        {
            foreach ($rResult as $aRow)
            {
                $clientid = $aRow[0];
            }
        }
        return $clientid;

    }

    /**
     * give the regexp for sms Counting mode
     * @return string
     */
    public static function checkphoneNumber_regexp()
    {
        return "/^.*(0[0-9]{9})|(\+|00){0,1}([0-9]{11}).*$/";

    }

    public static function isValidDate($date)
    {
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') == $date;
    }

    /**
     * @param $idmeta
     * @return null
     */
    public static  function loadMetaKey($idmeta){

        $db =& JFactory::getDBO();
        if($idmeta>0){
            $query = 'SELECT akey from jos_type_meta m where m.idtype_meta = '.$idmeta;
            $db->setQuery($query);
            $db->execute();
            $num_rows = $db->getNumRows();
            if ($num_rows > 0) {
                $data = $db->loadObject();

                return $data->akey;
            }

        } else return null;
    }

    /**
     * @param $idmeta
     * @return null
     */
    public static  function loadMetaValue($idmeta){

        $db =& JFactory::getDBO();
        if($idmeta>0){
            $query = 'SELECT value from jos_type_meta m where m.idtype_meta = '.$idmeta;
            $db->setQuery($query);
            $db->execute();
            $num_rows = $db->getNumRows();
            if ($num_rows > 0) {
                $data = $db->loadObject();

                return $data->value;
            }

        } else return null;
    }

    /**
     * @param $name
     * @param null $akey
     * @return null
     */
    public static  function loadMetaValueFromName($name,$akey=null){

        $db =& JFactory::getDBO();
        if(strlen($name)>0){
            $query = "SELECT value from jos_type_meta m where UPPER(m.name) like UPPER('".$name."')";
            $db->setQuery($query);
            $db->execute();
            $num_rows = $db->getNumRows();
            if ($num_rows == 1) {
                $data = $db->loadObject();

                return $data->value;
            } else if($num_rows > 1 and !is_null($akey)) {
                $query = "SELECT value from jos_type_meta m where UPPER(m.name) like UPPER('".$name."') and m.akey='".$akey."'";
                $db->setQuery($query);
                $db->execute();
                $num_rows = $db->getNumRows();
                if ($num_rows>0) {
                    $data = $db->loadObject();

                    return $data->value;
                }
            }

        } else return null;
    }

    /**
     * @param $akey
     * @return $value
     */
    public static  function loadMetaValueFromKey($akey){

        $db =& JFactory::getDBO();
        if(strlen($akey)>0){
            $query = "SELECT value from jos_type_meta m where UPPER(m.akey) like UPPER('".$akey."')";
            $db->setQuery($query);
            $db->execute();
            $num_rows = $db->getNumRows();
            if ($num_rows == 1) {
                $data = $db->loadObject();

                return $data->value;
            } else return null;

        } else return null;
    }

    /**
     * @param $name
     * @param null $akey
     * @return null
     */
    public static  function loadMetaIdFromName($name,$akey=null){

        $db =& JFactory::getDBO();
        if(strlen($name)>0){
            $query = "SELECT idtype_meta from jos_type_meta m where UPPER(m.name) like UPPER('".$name."')";
            $db->setQuery($query);
            $db->execute();
            $num_rows = $db->getNumRows();
            if ($num_rows == 1) {
                $data = $db->loadObject();

                return $data->idmeta;
            } else if($num_rows > 1 and !is_null($akey)) {
                $query = "SELECT idtype_meta from jos_type_meta m where UPPER(m.name) like UPPER('".$name."') and m.akey='".$akey."'";
                $db->setQuery($query);
                $db->execute();
                $num_rows = $db->getNumRows();
                if ($num_rows>0) {
                    $data = $db->loadObject();

                    return $data->idmeta;
                }
            }

        } else return null;
    }

    /**
     * TODO:select des alertes et autres anomalies (badges...)
     * @param JDatabase $db
     * @param $userid
     * @return int
     */
    public static function getAlertNumber(JDatabase $db, $userid){
        return 0;
    }
    /**
     * @param $db
     * @param $datedebut
     * @param $datefinale
     * @return array
     */

/*$datedebut= new DateTime ('2017-09-29 00:00:00')
$datedebut->format('Y-m-d H:i:sP');
    $datefinale= new DateTime=('2017-09-30 00:00:00');
$datefinale->format('Y-m-d H:i:sP');*/
    public static function createStatJour(JDatabase $db, $datedebut, $datefinale){
            //if(is_null($db)) $db = & JFactory::getDBO();

        echo "coucou l1950 spstools";
        //$user =& JFactory::getUser();
        $user="42";
        $nbService=array();

        $dateDeb = new DateTime($datedebut);
        $dateFin = new DateTime($datefinale);

        for ($dateencours = $dateDeb->format('Y-m-d'); $dateDeb->format('Y-m-d') <= $dateFin->format('Y-m-d'); $dateencours = $dateDeb->modify('+1 day')->format('Y-m-d')) {
            //par jour
            //nb de service
            $query = "select s.societe,count(idplanning) from #__planning p,#__service s where p.date = DATE('" . $dateencours . "') and p.service_idservice=s.idservice group by s.societe";
            $db->setQuery($query);
            $query = $db->query();
            $num_rows = $db->getNumRows();
            if ($num_rows > 0) {
                $rResult = $db->loadRowList();
                foreach ($rResult as $aRow) {
                    $depot = $aRow[0];

                    if (is_null($depot)) $depot = "defaut";
                    $nbService[$dateencours][$depot] = $aRow[1];
                }
            }
        }

        $aFaire = array();
        while (($nbdated = current($nbService)) !== false) {
            if ($nbdated > 0) {
                $aFaire[] = key($nbService);
            }
            next($nbService);
        }

        $nbLieuInter = array();
        $nbAlerte = array();
        $nbsmsHPrecu = array();
        $moysmsHPrecutroptot=array();
        $nbsmsHPrecutroptot=array();
        $nbsmsOKrecu = array();

        while (($dateencours = current($aFaire)) !== false) {
            //nb d'etapes a valider
            $query = 'SELECT s.societe,count(idetape_planning)
			FROM #__service s, #__planning p, #__etape_planning ep
			INNER JOIN #__usersps usps
            ON usps.user_id = ' . $user . '
            LEFT JOIN #__depot depot
            ON FIND_IN_SET(depot.iddepot,usps.liste_iddepot)
            where p.date = DATE(\'' . $dateencours . '\')			and p.service_idservice = s.idservice';
            $query .= ' and (s.societe = depot.societe or ((s.societe is null or s.societe = \'\') and depot.iddepot =  usps.liste_iddepot))';
            $query .= ' and ep.planning_idplanning = p.idplanning group by s.societe';
            $db->setQuery($query);
            $query = $db->query();
            $num_rows = $db->getNumRows();

            if ($num_rows > 0) {
                $rResult = $db->loadRowList();
                foreach ($rResult as $aRow) {
                    $depot = $aRow[0];

                    if (is_null($depot)) $depot = "defaut";
                    $nbLieuInter[$dateencours][$depot] = $aRow[1];
                }
            } else {
                $nbLieuInter[$dateencours] = 0;
            }

            //nombre d'alerte

            $query = 'SELECT s.societe,count(*)
            FROM #__service s, #__planning p, #__etape_planning ep
            INNER JOIN #__usersps usps
            ON usps.user_id = ' . $user . '
            LEFT JOIN #__depot depot
            ON FIND_IN_SET(depot.iddepot,usps.liste_iddepot)
            where p.date = DATE(\'' . $dateencours . '\')			and p.service_idservice = s.idservice';
            $query .= ' and (s.societe = depot.societe or ((s.societe is null or s.societe = \'\') and depot.iddepot =  usps.liste_iddepot))';
            $query .= ' and ep.planning_idplanning = p.idplanning';
            $query .= ' and ep.etat = \'L\' group by s.societe';
            $db->setQuery($query);
            $query = $db->query();
            $num_rows = $db->getNumRows();
            $rResult = $db->loadRowList();

            if ($num_rows > 0) {
                foreach ($rResult as $aRow) {
                    $depot = $aRow[0];

                    if (is_null($depot)) $depot = "defaut";
                    $nbAlerte[$dateencours][$depot] = $aRow[1];
                }

            } else {
                $nbAlerte[$dateencours]['defaut'] = 0;
            }

            //sms hors periode
            $query = 'select s.societe,count(r.idreceivedsms) from jos_receivedsms r,jos_planning p,jos_etape_planning ep,jos_service s
where dateheure>DATE(\'' . $dateencours . '\') and dateheure<DATE_ADD(DATE(\'' . $dateencours . '\'),INTERVAL 24 hour) and p.date=\'' . $dateencours . '\'
and p.service_idservice = s.idservice
and ep.planning_idplanning=p.idplanning
and r.content like CONCAT(p.codesms,\'%\') and motifRejet like \'Hors%\'
group by s.societe';
            $db->setQuery($query);
            $query = $db->query();
            $num_rows = $db->getNumRows();
            $rResult = $db->loadRowList();

            if ($num_rows > 0) {
                foreach ($rResult as $aRow) {
                    $depot = $aRow[0];

                    if (is_null($depot)) $depot = "defaut";

                    $nbsmsHPrecu[$dateencours][$depot] = $aRow[1];
                }

            } else {
                $nbsmsHPrecu[$dateencours]['defaut'] = 0;
            }

            //moy des temps sms arrives trop tot  /////rajouter un gros AVG()

            $query='select s.societe,count(r.idreceivedsms),ROUND(avg(TIME_TO_SEC(TIMEDIFF(TIME(e.heure),TIME(r.dateheure))))/60) avance_moyenne_minute
from jos_receivedsms r,jos_planning p,jos_etape_planning ep,jos_service s,jos_etape e
where dateheure>DATE(\'' . $dateencours . '\') and dateheure<DATE_ADD(DATE(\'' . $dateencours . '\'),INTERVAL 24 hour) and p.date=\'' . $dateencours . '\'
            and p.service_idservice = s.idservice
            and ep.planning_idplanning=p.idplanning
            and e.idetape=ep.etape_idetape
            and r.content like CONCAT(p.codesms,\'%\') and motifRejet like \'Hors%\'
            and r.dateheure<DATE_ADD(DATE(\'' . $dateencours . '\'),INTERVAL TIME_TO_SEC(e.heure) second)
            group by s.societe';
            $db->setQuery($query);
            $query = $db->query();
            $num_rows = $db->getNumRows();
            $rResult = $db->loadRowList();

            if ($num_rows > 0) {
                foreach ($rResult as $aRow) {
                    $depot = $aRow[0];

                    if (is_null($depot)) $depot = "defaut";

                    $nbsmsHPrecutroptot[$dateencours][$depot] = $aRow[1];
                    $moysmsHPrecutroptot[$dateencours][$depot] = $aRow[2];
                }

            } else {
                $nbsmsHPrecu[$dateencours]['defaut'] = 0;
                $moysmsHPrecutroptot[$dateencours]['defaut'] = 0;
            }

            //sms ok
            $query = "SELECT s.societe,count(*)
            FROM #__receivedsms r,#__planning p,#__service s
            where p.idplanning=r.planning_idplanning
            and p.service_idservice = s.idservice
             and DATE(dateheure) = DATE('" . $dateencours . "')
                and (motifRejet like '' or motifRejet is null)
            group by s.societe";
            $db->setQuery($query);
            $query = $db->query();
            $num_rows = $db->getNumRows();
            $rResult = $db->loadRowList();

            if ($num_rows > 0) {
                foreach ($rResult as $aRow) {
                    $depot = $aRow[0];

                    if (is_null($depot)) $depot = "defaut";
                    $nbsmsOKrecu[$dateencours][$depot] = $aRow[1];
                }

            } else {
                $nbsmsOKrecu[$dateencours]['defaut'] = 0;
            }

            next($aFaire);
        }


        reset($aFaire);
        $cpt = 0;
        $ligne = array();

        while (($dateencours = current($aFaire)) !== false) {
            foreach($nbService[$dateencours] as $depot=>$value):

                $ligne[$cpt][] = $dateencours;
                $ligne[$cpt][] = $depot;
                $ligne[$cpt][] = isset($nbService[$dateencours][$depot])?$nbService[$dateencours][$depot]:0;
                $ligne[$cpt][] = isset($nbLieuInter[$dateencours][$depot])?$nbLieuInter[$dateencours][$depot]:0;
                $ligne[$cpt][] = isset($nbAlerte[$dateencours][$depot])?$nbAlerte[$dateencours][$depot]:0;
                $ligne[$cpt][] = isset($nbsmsOKrecu[$dateencours][$depot])?$nbsmsOKrecu[$dateencours][$depot]:0;
                $ligne[$cpt][] = isset($nbsmsHPrecu[$dateencours][$depot])?$nbsmsHPrecu[$dateencours][$depot]:0;
                $ligne[$cpt][] = isset($nbsmsHPrecutroptot[$dateencours][$depot])?$nbsmsHPrecutroptot[$dateencours][$depot]:0;
                $ligne[$cpt][] = isset($moysmsHPrecutroptot[$dateencours][$depot])?$moysmsHPrecutroptot[$dateencours][$depot]:0;
                $cpt++;
            endforeach;
            next($aFaire);
        }
        return $ligne;
    }

    public static function createMail($filename, $path, $mailto, $from_mail, $from_name, $replyto, $subject, $pmessage, $applicationtype='application/octet-stream')
    {
            $file = $path.$filename;//fichier=dossier nom du fichier
            $file_size = filesize($file);//taille du fichier
            $handle = fopen($file, "r");//$handle=(pointeur de fichier).fopen=ouvre un fichier (r=en lecture seul)
            $content = fread($handle, $file_size);//le contenu=lecture d'un fichier en mode binaire(lit jusqu'au poids indiquer dans le fichier referencer dans handle)
            fclose($handle);//ferme le fichier
        $content = chunk_split(base64_encode($content));//le contenu =scinde le contenu pour convertir en un autre format
            $uid = md5(uniqid(time()));//identifiant utilisateur=(uniqid= generation d'un identifiant unique)(md5=calcule du hachage de chaine)
            
            $eol = PHP_EOL;

			// Basic headers
			$header = "From: ".$from_name." <".$from_mail.">".$eol;
       if(strlen($replyto)>0) $header .= "Reply-To: " . $replyto . $eol;
			$header .= "MIME-Version: 1.0\r\n";
			$header .= "Content-Type: multipart/mixed; boundary=\"".$uid."\"";

			// Put everything else in $message
			$message = "--".$uid.$eol;
        $message .= "Content-Type: text/html; charset=utf-8" . $eol;
			$message .= "Content-Transfer-Encoding: 8bit".$eol.$eol;
        $message .= $pmessage . $eol.$eol;
			$message .= "--".$uid.$eol;
        $message .= "Content-Type: ".$applicationtype."; name=\"" . $filename . "\"" . $eol;
			$message .= "Content-Transfer-Encoding: base64".$eol;
        $message .= "Content-Disposition: attachment; filename=\"" . $filename . "\"" . $eol . $eol;
        $message .= $content . $eol.$eol;
			$message .= "--".$uid."--";

        if ($ret=mail($mailto, $subject, $message, $header)) {
            var_dump('succes='.$ret);
				return "mail_success";
        } else {
            var_dump('echec='.$ret);
				return "mail_error";
			}
			  
        }

}
