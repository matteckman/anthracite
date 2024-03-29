<?php

function __autoload($className){
	$fileName = MVC_CORE_PATH . 'class'.$className.'.php';
	if(file_exists($fileName))
		include_once $fileName;
}

function getRegistryClass(){
	$reg = registry::singleton();
	return $reg;
}

/**
 * -- DATABASE FUNCTIONS --
 */

function currentUser(){
	global $firephp;
	if(USE_FIREPHP){$firephp->log('CURR utilities.php, currentUser(), line '.__LINE__);}

	if(!isset($_SESSION['userId']) || $_SESSION['userId'] == '')
		return FALSE;
	
	$userId = $_SESSION['userId'];
	if(USE_FIREPHP){$firephp->log($userId,'--$userId at line '.__LINE__);}

	// accomodate different roles being predominant in various apps
	$role = USER_ROLE;
	
	$sql = 'SELECT *,people.id AS person_id,'.$role.'.id AS id FROM '.$role.' JOIN people ON ('.$role.'.person_id=people.id) WHERE '.$role.'.id='.$userId;

	$user = _getFromDatabase($sql);

/* ---------- ADD DECRYPTION HERE ---------- */

	return $user;
}

function currentCareTeam(){
	// initialize
	global $firephp;
	if(USE_FIREPHP){$firephp->log('CURR utilities.php, currentCareTeam(), line '.__LINE__);}
	
	if(!isset($_SESSION['userId']))
		return FALSE;

	$userRecord = array();
	$careTeamId = '';

	$reg = registry::singleton();
	$dbConn = $reg->get('databaseConnectionSingleton');
	
	// get values from session
	$userId = $_SESSION['userId'];
	if(USE_FIREPHP){$firephp->log($userId,'--$userId at line '.__LINE__);}
	$role = $_SESSION['userRole'];
	
	// 1. get all care team members
	// 1a. get the user's care_team_id value
	if(USER_ROLE == 'patients'){
		$sql = 'SELECT * FROM care_teams WHERE patient_id='.$userId;
		$result = mysqli_query($dbConn,$sql);
		if(is_object($result)){
			$numRows = mysqli_num_rows($result);
			if($numRows > 0){
				$temp = mysqli_fetch_assoc($result);
				$careTeamId = $temp['id'];
			}else{
				return FALSE;
			}
		}else{
			return FALSE;
		}
	}else{
		$sql = 'SELECT * FROM care_team_members WHERE role_id='.$userId.' AND role=\''.USER_ROLE.'\'';
		$result = mysqli_query($dbConn,$sql);
		if(is_object($result)){
			$numRows = mysqli_num_rows($result);
			if($numRows > 0){
				if($numRows == 1){
					$temp = mysqli_fetch_assoc($result);
					$careTeamId = $temp['care_team_id'];
				}elseif($numRows > 1){
					/* @TODO add option to allow user to choose which patient they are currently supporting (i.e. which care_team to use) */
					$temp = mysqli_fetch_assoc($result);
					$careTeamId = $temp['care_team_id'];
				}
			}else{
				return FALSE;
			}
		}else{
			return FALSE;
		}
	}

	// 1b. use the care_team_members records to get role & people info
	$sql = 'SELECT * FROM care_team_members WHERE care_team_id='.$careTeamId;
	$result = mysqli_query($dbConn,$sql);
	if(is_object($result)){
		$numRows = mysqli_num_rows($result);
		if($numRows > 0){
			while($row = mysqli_fetch_assoc($result)){
				$return[] = $row;
			}
			foreach($return as $key => $teamMember){
				$sql  = 'SELECT * FROM '.$teamMember['role'].' JOIN people ON (people.id='.$teamMember['role'].'.person_id) WHERE '.$teamMember['role'].'.id='.$teamMember['role_id'];
				$result2 = mysqli_query($dbConn,$sql);
				if(is_object($result2)){
					$numRows2 = mysqli_num_rows($result2);

					if($numRows2 > 0){
						$temp = mysqli_fetch_assoc($result2);

/* ---------- ADD DECRYPTION HERE ---------- */

					}
				}else{
					echo $sql.'<br/>';
				}
				
				// expand the $careTeam array to include information from "people" table
				foreach($temp as $key2=>$tempValue)
					$return[$key][$key2] = $tempValue;
			}
			unset($teamMember);
		}else{
			$return = FALSE;
		}
	}else{
		if(USE_FIREPHP){$firephp->log('--mysqli call did not work, at line '.__LINE__);}
		$return = FALSE;
	}
	
	return $return;
}

function currentCareteamPatient($role=''){
	// id has to equal patient_id
	global $firephp;
	if(USE_FIREPHP){$firephp->log('CURR utilities.php, currentUser(), line '.__LINE__);}	
	if(!isset($_SESSION['userId']) || $_SESSION['userId'] == '')
		return FALSE;

	// get values from session
	$userRoleId = $_SESSION['userId'];
	if(USE_FIREPHP){$firephp->log($userRoleId,'--$userId at line '.__LINE__);}

	// find $userId in careteam table
	$sql = 'SELECT * FROM care_team WHERE role_id='.$userRoleId.' LIMIT 1';
	$careTeam = _getFromDatabase($sql);
	
	// use that careteam record to determine the patient id that careteam supports
	$patientId = $careTeam['patient_id'];
	
	// get patient data
	$sql = 'SELECT * FROM patients JOIN people ON (patients.person_id=people.id) WHERE patients.id='.$patientId;
	$patientFields = _getFromDatabase($sql);
	
/* ---------- ADD DECRYPTION HERE ---------- */
	
	return $patientFields;
}

function getAllPatientsCareTeams(){
	global $firephp;
	$reg = registry::singleton();
	$dbConn = $reg->get('databaseConnectionSingleton');

	$userId = $_SESSION['userId'];
	$role = $_SESSION['userRole'];
echo $userId.'<br/>';
echo $role.'<br/>';exit;

	// 1. get all care team members
	// 1a. get the user's record
	$sql = 'SELECT * FROM care_team WHERE role_id='.$userId.' AND accepted=1';
	$result = mysqli_query($dbConn,$sql);
	if(is_object($result)){
		$numRows = mysqli_num_rows($result);
		if($numRows > 0){
			while($row = mysqli_fetch_assoc($result)){
				$userRecords[] = $row;
			}
		}
	}
echo '<pre>';print_r($userRecords);echo '</pre>';

	// 1b. use the user's record to get all members of the careteam
	$return = array();
	foreach($userRecords as $userRecord){
		$tempReturn = array();
		$sql = 'SELECT * FROM care_team WHERE patient_id='.$userRecord['patient_id'].' AND accepted=1';
		if(USE_FIREPHP){$firephp->log(array($dbConn,$sql),'--$dbConn and $sql at line '.__LINE__);}
		
		$result = mysqli_query($dbConn,$sql);
		if(is_object($result)){
			$numRows = mysqli_num_rows($result);
			if($numRows > 0){
				while($row = mysqli_fetch_assoc($result)){
					$tempReturn[] = $row;
				}
echo '<pre>';print_r($tempReturn);echo '</pre>';
				foreach($tempReturn as $key => $teamMember){
					$sql  = 'SELECT * FROM '.$teamMember['role'].' JOIN people ON (people.id=';
					$sql .= $teamMember['role'].'.person_id) WHERE '.$teamMember['role'].'.id='.$teamMember['role_id'];
					$result2 = mysqli_query($dbConn,$sql);
					$numRows2 = mysqli_num_rows($result2);
					
/* ---------- ADD DECRYPTION HERE ---------- */
					
					if($numRows2 > 0){
						$temp = mysqli_fetch_assoc($result2);
					}
					// expand the $careTeam array to include information from "people" table
					foreach($temp as $key2=>$tempValue)
						$tempReturn[$key][$key2] = $tempValue;
				}
				unset($teamMember);
			}else{
				$return = FALSE;
			}
		}else{
			if(USE_FIREPHP){$firephp->log('--mysqli call did not work, at line '.__LINE__);}
			$return = FALSE;
		}
		
		$return[] = $tempReturn;
	}
exit;	
	return $return;
}

function getAllPatientsForDoctor(){
	global $firephp;
	$reg = registry::singleton();
	$dbConn = $reg->get('databaseConnectionSingleton');

	$userId = $_SESSION['userId'];
	$role = $_SESSION['userRole'];

	// 1. get all care team members
	// 1a. get the user's record
	$sql = 'SELECT * FROM care_team_members JOIN care_teams ON (care_team_members.care_team_id=care_teams.id) WHERE role="doctors" AND care_team_members.role_id='.$userId.' AND care_team_members.accepted=1';
	$result = mysqli_query($dbConn,$sql);
	if(is_object($result)){
		$numRows = mysqli_num_rows($result);
		if($numRows > 0){
			while($row = mysqli_fetch_assoc($result)){
				$userRecords[] = $row;
			}
		}else{
			return FALSE;
		}
	}else{
		return FALSE;
	}

	// 1b. use the user's record to get all members of the careteam
	$return = array();
	foreach($userRecords as $count => $userRecord){
		$tempReturn = array();
		$sql = 'SELECT * FROM patients JOIN people ON (patients.person_id=people.id) WHERE patients.id='.$userRecord['patient_id'];
		if(USE_FIREPHP){$firephp->log(array($dbConn,$sql),'--$dbConn and $sql at line '.__LINE__);}
		
		$result = mysqli_query($dbConn,$sql);
		if(is_object($result)){
			$numRows = mysqli_num_rows($result);
			if($numRows > 0){
				$return[] = mysqli_fetch_assoc($result);
			}
		}
		
/* ---------- ADD DECRYPTION HERE ---------- */
		
	}
	
	return $return;
}

function _getFromDatabase($sql){
	global $firephp;
	if(USE_FIREPHP){$firephp->log('CURR utilities.php, _getFromDatabase(), line '.__LINE__);}
	
	$dbConn  = getDbConnection();
	
	if(USE_FIREPHP){$firephp->log(array($dbConn,$sql),'--$dbConn and $sql at line '.__LINE__);}

	$result = mysqli_query($dbConn,$sql);
	
	if(is_object($result)){
		$numRows = mysqli_num_rows($result);
		if($numRows == 1){
			$return = mysqli_fetch_assoc($result);
			if(USE_FIREPHP){$firephp->log($return,'$return at line '.__LINE__);}
		}elseif($numRows > 1){
			while($row = mysqli_fetch_assoc($result)){
				$return[] = $row;
			}
			if(USE_FIREPHP){$firephp->log($return,'$return at line '.__LINE__);}
		}else{
			$return = FALSE;
			if(USE_FIREPHP){$firephp->log($return,'$return at line '.__LINE__);}
		}
	}else{
		if(USE_FIREPHP){$firephp->log('--mysqli call did not work, at line '.__LINE__);}
		$return = FALSE;
	}
	if(USE_FIREPHP){$firephp->log($return,'--$return at line '.__LINE__);}
	return $return;
}

function getDbConnection(){
	$reg = registry::singleton();
	$dbConn = $reg->get('databaseConnectionSingleton');
	return $dbConn;
}

/**
 * Return the following:
 * 0 if a treatment does not fall on the date given ($onDate)
 * 1 if the treatment does fall, and the patient did NOT do it
 * 2 if the treatment does fall, and the patient DID do it
 */
function treatmentScheduled($patientId,$onDate,$treatmentId,$treatmentType){
	$todayWeekDay = date('w',strtotime($onDate)); // gets numerical day of week (0-6)
	$todayMonthDay = date('j',strtotime($onDate)); // gets numerical day of month (1-31) without leading zeros
	
	// 1. Does treatment fall on that day?
	switch($treatmentType){
		case 'exercises':
			$sql = 'SELECT * FROM exercises_patients JOIN exercises ON (exercises.id=exercises_patients.exercise_id) WHERE exercises_patients.exercise_id='.$treatmentId;
			$lastDateField = 'last_date_exercised';
			break;
		case 'medications':
			$sql = 'SELECT * FROM medications_patients JOIN medications ON (medications.id=medications_patients.medication_id) WHERE medications_patients.medications_id='.$treatmentId;
			$lastDateField = 'last_date_taken';
			break;
		case 'othertreatments':
			$sql = 'SELECT * FROM othertreatments_patients JOIN othertreatments ON (othertreatments.id=othertreatments_patients.othertreatments_id) WHERE othertreatments_patients.othertreatments_id='.$treatmentId;
			$lastDateField = 'last_date_done';
			break;
	}
	
	$row = _getFromDatabase($sql);

	$temp = '';	
	
	if(!isset($row['frequency'])){$row['frequency'] = '';}
	if(!isset($row[$lastDateField])){$row[$lastDateField] = '';}
// FIX LINE ABOVE!!! FIX LINE ABOVE!!!
	$dateLastTaken = strtotime($row[$lastDateField].' 00:00:00');
	if($row['frequency'] == '1'){ // if 'frequency' == 1 == 'Once A Day', then automatically add to reminder list
		$temp = $row;
	}
	if($row['frequency'] == 2){ // if 'frequency' == 2 == 'Once A Week', then compare to $todayWeekDay
		$weekDay = date('w',$dateLastTaken);
		if($weekDay == $todayWeekDay)
			$temp = $row;
	}
	if($row['frequency'] == 3){ // if 'frequency' == 3 == 'Once A Month', then compare to $todayMonthDay
		if(date('j',$dateLastTaken) == $todayMonthDay)
			$temp = $row;
	}

	// 2. was the treatment done?
	if($temp != ''){
		switch($treatmentType){
			case 'exercises':
				$sql = 'SELECT * FROM exercises_done WHERE exercise_id='.$temp['exercise_id'].' AND date_exercised LIKE \'%'.date('Y-m-d',strtotime($onDate)).'%\'';
				break;
			case 'medications':
				$sql = 'SELECT * FROM medications_taken WHERE medication_id='.$temp['medication_id'].' AND date_taken LIKE \'%'.date('Y-m-d',strtotime($onDate)).'%\'';
				break;
			case 'othertreatments':
				$sql = 'SELECT * FROM othertreatments_taken WHERE othertreatments_id='.$temp['othertreatments_id'].' AND date_othertreatments_taken LIKE \'%'.date('Y-m-d',strtotime($onDate)).'%\'';
				break;
		}
		$taken = _getFromDatabase($sql);
		if(!$taken) // means there was a treatment for today, but it wasn't done
			$return = 1;
		else
			$return = 2;
	}else{
		$return = 0;// means there was no treatment scheduled for that day
	}
	
	return $return;
}

/**
 * MISC FUNCTIONS
 */

function redirect($url){
	// @TODO need logic here to decide whether to flush the buffer or just clean it in the next line of code
	// ob_end_flush();

	// erase the output buffer, since the current page is going away, so we don't need any output
	ob_get_clean();
		
	// make sure the cookie made it to the browser, in case this redirect is for the sign-in process
	$params = session_get_cookie_params();
	setcookie(session_name(), session_id(), 0,$params["path"], $params["domain"],$params["secure"]);
	
	// redirect the url
	header('Location: '.$url);
}

function send_email($text,$to=array(),$from=array(),$subject='',$htmlText){
	global $firephp;
	require_once PATH_TO_MVC_LIBRARIES.'/swiftMailer/Swift-4.3.1/lib/swift_required.php';
	
	/**
	 * Create the message
	 * @tutorial http://swiftmailer.org/docs/messages.html
	 */
	 
	if($subject == '')
		$subject = 'A Message From A Member Of Kurbi - MS Comunication Platform';
	$message = Swift_Message::newInstance()
	->setSubject($subject)
	->setFrom($from) // array('john@doe.com' => 'John Doe')
	->setTo($to) // array('receiver@domain.org', 'other@domain.org' => 'A name')
	->setBody($text)
	->addPart($htmlText, 'text/html') // And optionally an alternative body
	// ->attach(Swift_Attachment::fromPath('my-document.pdf')) // Optionally add any attachments
	;
	
	/**
	 * Create the Transport
	 * @tutorial http://swiftmailer.org/docs/sending.html
	 */

	// For when we have SMTP available 
	$transport = Swift_SmtpTransport::newInstance('smtp.mandrillapp.com', 587) // port:25 or 587
	->setUsername('matteckman@gmail.com')
	->setPassword('xp5Yuhfe1AhoM3XbYR7qiQ')
	;

	// If SMTP doesn't work
	// $transport = Swift_MailTransport::newInstance();
	
	// Create the Mailer using your created Transport
	$mailer = Swift_Mailer::newInstance($transport);
	
	// Send the message
	$result = $mailer->send($message);
	$firephp->log($result,'$result from sending email in utilities.php, line '.__LINE__);

	return $result;
}

function cleanMysqlDate($date){
	$tempDate = explode('-',$date);
	if(isset($tempDate[1]) && isset($tempDate[2]) && isset($tempDate[0]))
		$cleanDate = $tempDate[1].'/'.$tempDate[2].'/'.$tempDate[0];
	else
		return FALSE;
	return $cleanDate;
}

/**
 * format should come in as 05/29/1973, and should get converted to 1973-05-29
 */
function convertToMysqlDate($date){
	// check to make sure it's not already in mysqli format, i.e. has "-" instead of "/"
	if(substr_count($date,'/') > 0){
		$tempDate = explode('/',$date);
		$cleanDate = $tempDate[2].'-'.$tempDate[0].'-'.$tempDate[1];
		return $cleanDate;
	}elseif(substr_count($date,'-') > 0){
		return $date;
	}else{
		return FALSE;
	}
}

/**
 * resize an image
 */
function resize_and_move_image($filename='',$filepath='',$targetheight=60,$targetwidth=53,$targetPath='',$targetName=''){
	global $firephp;
	if($targetName == ''){$targetName = $filename;}
	
	/**
	 * Figure out the extension
	 */
	$i = strrpos($filename,".");
    if (!$i) { return ""; } 
    $l = strlen($filename) - $i;
    $extension = substr($filename,$i+1,$l);
	$extension = strtolower($extension);
	
	$firephp->log($extension,'$extension, in utilities.php, at line '.__LINE__);
	
	/**
	 * Create $src from uploaded file (temp folder) dependent on image type (jpg,png,gif)
	 */
	if($extension=="jpg" || $extension=="jpeg" ){
		$uploadedfile = $filepath.$filename;
		$src = imagecreatefromjpeg($uploadedfile);
	}else if($extension=="png"){
		$uploadedfile = $filepath.$filename;
		$src = imagecreatefrompng($uploadedfile);
	}else{
		$src = imagecreatefromgif($uploadedfile);
	}
 
 	if($src == FALSE){
 		return FALSE;
 	}
 
 	/**
	 * Manipulate Width and Height
	 * keep ratio of height to width in resizing
	 */
	// is width wider then height?
	list($width,$height) = getimagesize($uploadedfile);
	$ratio = $height / $width;
	// $newwidth = $width * $ratio;
	// $newheight = $ratio * $targetheight;
// TO DO: make this work on multiple sizes
	$newwidth = $targetwidth;
	$newheight = $targetheight;
	
	/**
	 * Make temporary version of the new, resized image
	 */
	$tmp = imagecreatetruecolor($newwidth,$newheight);
	imagecopyresampled($tmp,$src,0,0,0,0,$newwidth,$newheight,$width,$height);

	/**
	 * Create the image in final directory
	 */
	$filename = $targetPath.$targetName;	
	imagejpeg($tmp,$filename,100);
	
	/**
	 * 
	 */
	imagedestroy($src);
	imagedestroy($tmp);
}

function slope($x_1,$y_1,$x_2,$y_2){
	return ($y_2-$y_1)/($x_2-$x_1);
}

function sendMessage($personId,$textonlyMessage,$formattedMessage){
	if(empty($personId) || empty($textonlyMessage) || empty($formattedMessage))
		return FALSE;
	
	// get person's preferred way of receiving messages
	// NOTE: if there is no access to the message_preferences table or if there is no data about preference,
	// default to sending an email. If there is no email address for person, return FALSE or exception
	
	
}

/**
 * There's only a "set" for FlashMessage cause flash messages are a one way proposition. A flash message is set in a model, and retrieved by the coreView class
 */
function setFlashMessage($type='',$title='',$payload=''){
	$reg = registry::singleton();
	$session = $reg->get('sessionSingleton');
	$session->setFlashMessage($type,$title,$payload);
}

/**
 *
 */

function getRolesForPerson($personId){
	// check to see if the array of roles already exists in $_SESSION

	// using $personId, check each role table (doctors, friendsfamily, patients) for a record with that person_id

	// set up an array in SESSION var with roles and role_ids

}

function rolesHtmlDropdown(){
	/*
		<ul>
		<li><a href="<?php echo FRIENDSFAMILY_APP; ?>/sign_in/switching_roles">Supporter Role</a></li>
		<li><a href="<?php echo PATIENT_APP; ?>/sign_in/switching_roles">Patient Role</a></li>
		<li><a href="<?php echo DOCTORS_APP; ?>/sign_in/switching_roles">Dr/Nurse Role</a></li>
	</ul>	
	*/
}