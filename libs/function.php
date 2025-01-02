<?php
include("conn/conn.php");

function check_triggers_opdallergy($host,$mysqluser,$mypass,$myDb){

        $mysqli = new mysqli($host,$mysqluser,$mypass,$myDb);

		if ($result = $mysqli -> query("SHOW TRIGGERS LIKE 'opd_allergy'")) {
			// echo "<br> Returned rows are: " . $result -> num_rows;
			if( ($result -> num_rows)>0){
				 echo "<br> Returned rows are: " . $result -> num_rows;
			}else{
				 	echo "<br> Returned rows are < 0 : " . $result -> num_rows;

						$sql  = "    DROP TRIGGER IF EXISTS `innsert_data`; " ;
						$sql.$sql = "  DELIMITER ;; ";
						$sql.$sql = "  CREATE TRIGGER `innsert_data` AFTER INSERT ON `opd_allergy` FOR EACH ROW begin                       
										INSERT INTO opd_allergy_event_logs (hn,agent ,patient_cid,trigges_event,date_event,flag)  SELECT hn,agent ,patient_cid,'inserted' , now(),0  from opd_allergy where hn = new.hn  and agent = new.agent ;
									end	;; " ;
						$mysqli->multi_query($sql);

						//------  add triggers update_data

							$sql = " DROP TRIGGER IF EXISTS `update_data`; ";
							$sql.$sql = " DELIMITER ;; " ;
							$sql.$sql = " CREATE TRIGGER `update_data` AFTER UPDATE ON `opd_allergy` FOR EACH ROW begin     
											INSERT INTO opd_allergy_event_logs (hn,agent ,patient_cid,trigges_event,date_event,flag)  SELECT hn,agent ,patient_cid,'updated' , now(),0  from opd_allergy where hn = old.hn  and agent = old.agent ;
										end	;; ";
							$mysqli->multi_query($sql);



								$sql = " DROP TRIGGER IF EXISTS `delete_data`; ";
								$sql.$sql = " DELIMITER ;; " ;
								$sql.$sql = " CREATE TRIGGER `delete_data` BEFORE DELETE ON `opd_allergy` FOR EACH ROW begin                      
												INSERT INTO opd_allergy_event_logs (hn,agent ,patient_cid,trigges_event,date_event,flag)  SELECT hn,agent ,patient_cid,'deleted' , now(),0  from opd_allergy where hn = old.hn  and agent = old.agent ;
											end ;; ";
								$mysqli->multi_query($sql);
 

		  

			}
			$mysqli -> close();
		}
	}


function chkopd_allergy(){	
               include("conn/conn.php");
                $sql = " select * from opd_allergy_event_logs where  flag = 0";
				//return $sql;
		     	$query = mysqli_query($Conn,$sql);
				$num_row = mysqli_num_rows($query);
				//$hn ="";
				//return $num_row;

				$msg_send_line = "";
				while ($result = mysqli_fetch_array($query)) {
					$hn = $result['hn'];	
					$agent = $result['agent'];	
					$patient_cid = $result['patient_cid'];
					$date_event = $result['date_event'];
					$trigges_event =  $result['trigges_event'];					
					$msgforsendline = get_msgforsendline($hn,$agent,$trigges_event);

						$sql_pcu = "select * from pcu_child where hcode <> (select hospitalcode from opdconfig)";
						$query_pcu = mysqli_query($Conn,$sql_pcu);						
						
						$msg_send_line_n = $msgforsendline.' | HospCode[11039]';
						while ($result_pcu = mysqli_fetch_array($query_pcu)) { //วนลูปเพื่อยืนยันแต่ละ รพ.สต.
							  	$hospcode = $result_pcu['hcode'];								
							//$get_row_pcu = count_pcuchild();
							//echo check_patient_hospcu($patient_cid,$hospcode);

							      if(set_hospcu($trigges_event,$sql,$ccid,$agent ,$hospcode,"check_patient_hospcu" )	 > 0) {		
															  
									  	
									    $msg_send_line_n =  $msg_send_line_n."|[".$hospcode."]";
										$hospcode  =  $hospcode.$hospcode;

								  }else{
										
									  	// echo "ไม่มี Patient : $patient_cid, นี้ในระบบ $hospcode <br>";
									   	$msg_send_line_n =   $msg_send_line_n;
										$hospcode = '';
								}

								
							//  echo "---==>>>>>>". $trigges_event.':'.$patient_cid.':'.$agent.':'.$hn.':'.$hospcode ;
						}						

								//echo $msg_send_line.$msg_send_line_n;
							set_update_flag_finish($trigges_event,$patient_cid,$agent,$hospcode);
							sendLineNotif_opd_allergy( $msg_send_line_n);
				 }


				// return $date_event.'|'.$patient_cid.'|'.$trigges_event.'|'.$agent;
}


function get_msgforsendline($hn,$agent,$trigges_event){
	include("conn/conn.php");
	$sql = " select *,PtName(hn,hn) as pt_name from opd_allergy_event_logs where hn ='$hn' and agent ='$agent' ";
	$query = mysqli_query($Conn,$sql);
	while ($result = mysqli_fetch_array($query)) {
		$agent = var_export( $result['agent'], true);
		$patient_cid = var_export($result['patient_cid'], true);		   	       
		$pt_name = 	 $result['pt_name'];
		$opd_allergy_alert_type_id = 1;

		if($opd_allergy_alert_type_id==1){
			$opd_allergy_alert_type = "แพ้ยา";
		}else{
			$opd_allergy_alert_type = "เฝ้าระวังการใช้ยา";
		}
	}
		$msg_send_line  = $trigges_event."! ".$pt_name ." | ".$opd_allergy_alert_type.": ".$agent;

	return $msg_send_line ; 

}




function set_db_for_patient($trigges_event,$patient_cid,$agent,$hn, $hospcode){
    //global  $hospcode;	
	include("conn/conn.php");
	if($trigges_event=='deleted'){
		   $sql = " select *,PtName(hn,hn) as pt_name from opd_allergy_event_logs where hn ='$hn' and agent ='$agent' ";
		//  echo $sql;
		   $query = mysqli_query($Conn,$sql);
		   while ($result = mysqli_fetch_array($query)) {
		  	 $hn = $result['hn'];
			 $agent = var_export( $result['agent'], true);
		   	 $patient_cid = var_export($result['patient_cid'], true);		   	       
			 $pt_name =  $result['pt_name'];
			 $opd_allergy_alert_type_id = 1;			 
			 $sql_to_set_db_pcu = " delete opd_allergy  where patient_cid = $patient_cid and agent= $agent";

		   }

		   

		//#########################################################################################
	   //#########################  รันฟังชัน ######################################################
	        set_hospcu($trigges_event,$sql_to_set_db_pcu,$patient_cid,$agent,$hospcode,"");
			/*set_pcu_04770($trigges_event,$sql_to_set_db_pcu,$patient_cid,$agent);
			set_pcu_04771($trigges_event,$sql_to_set_db_pcu,$patient_cid,$agent);
			set_pcu_04772($trigges_event,$sql_to_set_db_pcu,$patient_cid,$agent);
			set_pcu_04773($trigges_event,$sql_to_set_db_pcu,$patient_cid,$agent);
			set_pcu_04774($trigges_event,$sql_to_set_db_pcu,$patient_cid,$agent);
			set_pcu_04775($trigges_event,$sql_to_set_db_pcu,$patient_cid,$agent);	*/		
	   //#########################################################################################
	   //#########################  รันฟังชัน ######################################################
	
	        set_update_flag_finish($trigges_event,$patient_cid,$agent,$hospcode);
			//echo $GLOBALS['hospcode'];     
			


	}else{
	
			$sql = " select *,PtName(hn,hn) as pt_name from opd_allergy where hn ='$hn' and agent ='$agent' ";	
			$query = mysqli_query($Conn,$sql);
			while ($result = mysqli_fetch_array($query)) {
				$hn = $result['hn'];
				$report_date = var_export( $result['report_date'], true);
				$agent = var_export( $result['agent'], true);
				$symptom = var_export( $result['symptom'], true);
				$reporter =	var_export( $result['reporter'], true);
				$relation_level = var_export($result['relation_level'], true);
				$note = var_export($result['note'], true); 
				$allergy_type = var_export($result['allergy_type'], true);
				$display_order = var_export($result['display_order'], true);
				$begin_date = var_export( $result['begin_date'], true);
				$allergy_group_id = var_export( $result['allergy_group_id'], true);
				$seriousness_id = var_export( $result['seriousness_id'], true);
				$allergy_result_id = var_export( $result['allergy_result_id'], true);
				$allergy_relation_id = var_export( $result['allergy_relation_id'], true);
				$ward = var_export($result['ward'], true);
				$department = var_export($result['department'], true);
				$spclty = var_export($result['spclty'], true);
				$entry_datetime = var_export($result['entry_datetime'], true);
				$update_datetime = var_export($result['update_datetime'], true);
				$depcode = var_export($result['depcode'], true);
				$no_alert = var_export($result['no_alert'], true);
				$naranjo_result_id = var_export($result['naranjo_result_id'], true);
				$force_no_order = var_export($result['force_no_order'], true);
				$opd_allergy_alert_type_id = var_export($result['opd_allergy_alert_type_id'], true);
				$hos_guid = var_export($result['hos_guid'], true);
				$adr_preventable_score = var_export($result['adr_preventable_score'], true);
				$preventable = var_export($result['preventable'], true);
				$patient_cid = var_export($result['patient_cid'], true);
				$adr_consult_dialog_id = var_export($result['adr_consult_dialog_id'], true);
				$opd_allergy_report_type_id = var_export($result['opd_allergy_report_type_id'], true);
				$hos_guid_ext = var_export($result['hos_guid_ext'], true);
				$agent_code24 = var_export($result['agent_code24'], true);
				$officer_confirm = var_export($result['officer_confirm'], true);
				$icode = var_export($result['icode'], true);
				$opd_allergy_symtom_type_id = var_export($result['opd_allergy_symtom_type_id'], true);
				$opd_allergy_id = var_export($result['opd_allergy_id'], true);
				$cross_group_check = var_export($result['cross_group_check'], true);
				$opd_allergy_source_id = var_export($result['opd_allergy_source_id'], true);
				$opd_allergy_type_id = var_export($result['opd_allergy_type_id'], true);
				$doctor_code = var_export($result['doctor_code'], true);
				$dosage_text = var_export($result['dosage_text'], true);
				$usage_text = var_export($result['usage_text'], true);
				$lab_text = var_export($result['lab_text'], true);
				$sct_disorder_id = var_export($result['sct_disorder_id'], true);
				$sct_substance_id = var_export($result['sct_substance_id'], true);	
				$pt_name = 	 $result['pt_name'];

					switch ($trigges_event) {
						case 'inserted':
							$sql_to_set_db_pcu = "insert into opd_allergy(report_date,agent,symptom,reporter,relation_level,note,allergy_type,display_order,begin_date,allergy_group_id,	seriousness_id,	allergy_result_id,	allergy_relation_id,	ward,	department,	spclty,	entry_datetime,	update_datetime,	depcode,	no_alert,	naranjo_result_id,	force_no_order,	opd_allergy_alert_type_id,	hos_guid,	adr_preventable_score,	preventable,	patient_cid,	adr_consult_dialog_id,	opd_allergy_report_type_id,	hos_guid_ext,	agent_code24,	officer_confirm,	icode,	opd_allergy_symtom_type_id,	opd_allergy_id,	cross_group_check,	opd_allergy_source_id,opd_allergy_type_id,doctor_code,dosage_text,usage_text,	lab_text,sct_disorder_id,sct_substance_id)
									values($report_date,	$agent,	$symptom,	$reporter,	$relation_level,	$note,	$allergy_type,	$display_order,	$begin_date,	$allergy_group_id,	$seriousness_id,	$allergy_result_id,	$allergy_relation_id,	$ward,	$department,	$spclty,	$entry_datetime,	$update_datetime,	$depcode,	$no_alert,	$naranjo_result_id,	$force_no_order,	$opd_allergy_alert_type_id,	$hos_guid,	$adr_preventable_score,	$preventable,	$patient_cid,	$adr_consult_dialog_id,	$opd_allergy_report_type_id,	$hos_guid_ext,	$agent_code24,	$officer_confirm,	$icode,	$opd_allergy_symtom_type_id,	$opd_allergy_id,	$cross_group_check,	$opd_allergy_source_id,	$opd_allergy_type_id,	$doctor_code,	$dosage_text,	$usage_text,	$lab_text,	$sct_disorder_id,	$sct_substance_id)";
							
						case 'updated':
							$sql_to_set_db_pcu = "update opd_allergy set report_date = $report_date,													
															symptom = $symptom,
															reporter = $reporter,
															relation_level = $relation_level ,
															note = $note,
															allergy_type = $allergy_type,
															display_order = $display_order,
															begin_date = $begin_date,
															allergy_group_id = $allergy_group_id,
															seriousness_id = $seriousness_id,
															allergy_result_id = $allergy_result_id,
															allergy_relation_id = $allergy_relation_id,
															ward = $ward,
															department = $department,
															spclty = $spclty,
															entry_datetime = $entry_datetime,
															update_datetime = $update_datetime,
															depcode = $depcode,
															no_alert = $no_alert,
															naranjo_result_id = $naranjo_result_id,
															force_no_order = $force_no_order,
															opd_allergy_alert_type_id = $opd_allergy_alert_type_id,
															hos_guid = $hos_guid,
															adr_preventable_score = $adr_preventable_score,
															preventable = $preventable,													
															adr_consult_dialog_id = $adr_consult_dialog_id,
															opd_allergy_report_type_id = $opd_allergy_report_type_id,
															hos_guid_ext = $hos_guid_ext,
															agent_code24 = $agent_code24,
															officer_confirm = $officer_confirm,
															icode = $icode,
															opd_allergy_symtom_type_id = $opd_allergy_symtom_type_id,
															opd_allergy_id = $opd_allergy_id,
															cross_group_check = $cross_group_check,
															opd_allergy_source_id = $opd_allergy_source_id,
															opd_allergy_type_id = $opd_allergy_type_id,
															doctor_code = $doctor_code,
															dosage_text = $dosage_text,
															usage_text = $usage_text,
															lab_text = $lab_text,
															sct_disorder_id = $sct_disorder_id,
															sct_substance_id = $sct_substance_id					
															where patient_cid = $patient_cid and agent= $agent";

															//echo $sql_to_set_db_pcu;
							
								
							

						default:
							# code...
							break;
				    
						}

	

       //#########################################################################################
	   //#########################  รันฟังชัน ######################################################
	 //  echo $sql_to_set_db_pcu ." for ".$hospcode ;
	        set_hospcu($trigges_event,$sql_to_set_db_pcu,$patient_cid,$agent,$hospcode,"");
			/*set_pcu_04770($trigges_event,$sql_to_set_db_pcu,$patient_cid,$agent);
			set_pcu_04771($trigges_event,$sql_to_set_db_pcu,$patient_cid,$agent);
			set_pcu_04772($trigges_event,$sql_to_set_db_pcu,$patient_cid,$agent);
			set_pcu_04773($trigges_event,$sql_to_set_db_pcu,$patient_cid,$agent);
			set_pcu_04774($trigges_event,$sql_to_set_db_pcu,$patient_cid,$agent);
			set_pcu_04775($trigges_event,$sql_to_set_db_pcu,$patient_cid,$agent);	*/		
	   //#########################################################################################
	   //#########################  รันฟังชัน ######################################################
	
			set_update_flag_finish($trigges_event,$patient_cid,$agent,$hospcode);
				//echo $GLOBALS['hospcode'];
				
				//$msg_send_line  = $trigges_event."! ".$pt_name ." | ".$opd_allergy_alert_type.": ".$agent." | HospCode[". $hospcode."]";
				//echo $msg_send_line;
				//sendLineNotif_opd_allergy( $msg_send_line);

		}

	}

	return $msg_send_line ;
    
}




function set_update_flag_finish($trigges_event,$patient_cid,$agent,$hospcode){
	$ini = parse_ini_file('conn/app.ini');
	$mysqli_ = new mysqli($ini['db_host_master'],$ini['db_user_master'],$ini['db_password_master'],$ini['db_name_master']);
	$mysqli_ -> set_charset("utf8");

	$sql = " update opd_allergy_event_logs set flag = 1,hospcode = '$hospcode' where patient_cid = '$patient_cid' and agent = '$agent' and flag=0;" ;
     echo "<br>".$sql;
	// Check connection
	if ($mysqli_ -> connect_errno) {
			echo "Failed to connect to MySQL: " . $mysqli_ -> connect_error;
	  exit();
	  }
	  //perfromQuery
	$result = $mysqli_ -> query($sql);

	//$result -> free_result();
	$mysqli_ -> close();
	     
}







function sendLineNotif_opd_allergy($message)
{

	$ini = parse_ini_file('conn/app.ini');
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://notify-api.line.me/api/notify");
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "message=" . $message);
    $headers = array('Content-type: application/x-www-form-urlencoded', 'Authorization: Bearer ' . $ini['token'] . '',);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $result = curl_exec($ch);

    if (curl_error($ch)) {
        echo 'error:' . curl_error($ch);
    } else {
        $res = json_decode($result, true);
        echo "status : " . $res['status'];
        echo "message : " . $res['message'];
        echo "<br>";
    }
    curl_close($ch);
}


//--------------------ฟังชั่นบน hosxp_pcu--------------------------------
function set_hospcu($trigges_event,$sql,$ccid,$agent ,$hospcode,$optionselect ){
    include("conn/conn.php"); 
	$ini = parse_ini_file('conn/app.ini');
	$sql_check_pid = "select cid from patient where cid = $ccid";	
    
	switch ($hospcode) {
		case '04770':
			$mysqli = new mysqli($ini['db_host_04770'],$ini['db_user_04770'],$ini['db_password_04770'],$ini['db_name_04770']);
			$mysqli -> set_charset("utf8");

		case '04771':
			$mysqli = new mysqli($ini['db_host_04771'],$ini['db_user_04771'],$ini['db_password_04771'],$ini['db_name_04771']);
			$mysqli -> set_charset("utf8");

		case '04772':
			$mysqli = new mysqli($ini['db_host_04772'],$ini['db_user_04772'],$ini['db_password_04772'],$ini['db_name_04772']);
			$mysqli -> set_charset("utf8");

		case '04773':
			$mysqli = new mysqli($ini['db_host_04773'],$ini['db_user_04773'],$ini['db_password_04773'],$ini['db_name_04773']);
			$mysqli -> set_charset("utf8");
			
		case '04774':
			$mysqli = new mysqli($ini['db_host_04774'],$ini['db_user_04774'],$ini['db_password_04774'],$ini['db_name_04774']);
			$mysqli -> set_charset("utf8");

		case '04775':
			$mysqli = new mysqli($ini['db_host_04775'],$ini['db_user_04775'],$ini['db_password_04775'],$ini['db_name_04775']);
			$mysqli -> set_charset("utf8");

		default:

			break;

	}


	
	// Check connection
		if ($mysqli -> connect_errno) {
		      echo "Failed to connect to MySQL: " . $mysqli -> connect_error;
		exit();
		}


    // Check Option For Get data 

			if($optionselect=="check_patient_hospcu"){ 
						// Perform query
					// echo $sql_check_pid;
					$result = mysqli_query($mysqli,$sql_check_pid);	
					$rowcount=mysqli_num_rows($result);
					return $rowcount;

			}else{

				
		

			switch ($trigges_event) {
				case 'inserted':					   
					//echo $sql."<br>";
					$result = $mysqli -> query($sql);
				//	$result -> free_result();

					$sql_update_hn = " update opd_allergy set hn = (select hn from patient where cid = $ccid limit 1 ) where patient_cid = $ccid  and agent=$agent";
					$result = $mysqli -> query($sql_update_hn);
				//	$result -> free_result();				
				
					$sql_update_patient = " update patient set patient.drugallergy = (SELECT group_concat(agent) FROM opd_allergy where hn = patient.hn  and  patient.cid = $ccid )  where patient.cid = $ccid ";
					$result = $mysqli -> query($sql_update_patient);
				//	$result -> free_result();
				break;

				case 'updated':
					//	echo $sql."<br>";
					   $result = $mysqli -> query($sql);
						//$result -> free_result();   
						$sql_update_patient = " update patient set patient.drugallergy = (SELECT group_concat(agent) FROM opd_allergy where hn = patient.hn  and  patient.cid = $ccid )  where patient.cid = $ccid ";
						$result = $mysqli -> query($sql_update_patient);
						
				   break;
   
				   case 'deleted':
					   //echo $sql."<br>";
					   $result = $mysqli -> query($sql);
						//$result -> free_result();
   
						$sql_delete_patient = " delete FROM opd_allergy where patient_cid = $ccid and agent=$agent";
						$result = $mysqli -> query($sql_delete_patient);

						$sql_update_patient = " update patient set patient.drugallergy = (SELECT group_concat(agent) FROM opd_allergy where hn = patient.hn  and  patient.cid = $ccid )  where patient.cid = $ccid ";
						$result = $mysqli -> query($sql_update_patient);

				   break;

				default:			
					
				break;	
			}
			echo "จัดการ Patient : $ccid นี้ในระบบ $hospcode เรียบร้อยแล้ว<br>";
			

		}


	$mysqli -> close();
}





?>