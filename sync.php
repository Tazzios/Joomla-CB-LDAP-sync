<?php

/*///////////
Dit script is nodig om er voor te zorgen dat: 
-medewerkers direct in de portrettengids zichtbaar zijn.
- AD gegevens worden ingevuld in de Community Builder velden.

//////////////*/


// mydap version 4
// https://samjlevy.com/mydap-v4/

function mydap_start($username,$password,$host,$port=636) {
  global $mydap;
  if(isset($mydap)) die('Error, LDAP connection already established');
 
  // Connect to AD
	$mydap = ldap_connect($host,$port) or die('Error connecting to LDAP');
	
	ldap_set_option($mydap,LDAP_OPT_PROTOCOL_VERSION,3);
	@ldap_bind($mydap,$username,$password) or die('Error binding to LDAP: '.ldap_error($mydap));
 
	return true;
}
 
function mydap_end() {
	global $mydap;
	if(!isset($mydap)) die('Error, no LDAP connection established');
 
	// Close existing LDAP connection
	ldap_unbind($mydap);
}
 
function mydap_attributes($user_dn,$keep=true) {
	global $mydap;
	if(!isset($mydap)) die('Error, no LDAP connection established');
	if(empty($user_dn)) die('Error, no LDAP user specified');
 
	// Disable pagination setting, not needed for individual attribute queries
	ldap_control_paged_result($mydap,1);
 
	// Query user attributes
	$results = (($keep) ? ldap_search($mydap,$user_dn,'cn=*',$keep) : ldap_search($mydap,$user_dn,'cn=*'))
	or die('Error searching LDAP: '.ldap_error($mydap));
 
	$attributes = ldap_get_entries($mydap,$results);
 
	// Return attributes list
	if(isset($attributes[0])) return $attributes[0];
	else return array();
}
 
function mydap_members($object_dn,$object_class='g') {
	global $mydap;
	if(!isset($mydap)) die('Error, no LDAP connection established');
	if(empty($object_dn)) die('Error, no LDAP object specified');
 
	// Determine class of object we are dealing with
 
	// Groups, use range to overcome LDAP attribute limit
	if($object_class == 'g') {
		$output = array();
		$range_size = 1500;
		$range_start = 0;
		$range_end = $range_size - 1;
		$range_stop = false;
 
		do {
			// Query Group members
			$results = ldap_search($mydap,$object_dn,'cn=*',array("member;range=$range_start-$range_end")) or die('Error searching LDAP: '.ldap_error($mydap));
			$members = ldap_get_entries($mydap,$results);
 
			$member_base = false;
 
			// Determine array key of the member results
 
			// If array key matches the format of range=$range_start-* we are at the end of the results
			if(isset($members[0]["member;range=$range_start-*"])) {
				// Set flag to break the do loop
				$range_stop = true;
				// Establish the key of this last segment
				$member_base = $members[0]["member;range=$range_start-*"];
 
			// Otherwise establish the key of this next segment
			} elseif(isset($members[0]["member;range=$range_start-$range_end"]))
				$member_base = $members[0]["member;range=$range_start-$range_end"];
 
			if($member_base && isset($member_base['count']) && $member_base['count'] != 0) {
				// Remove 'count' element from array
				array_shift($member_base);
 
				// Append this segment of members to output
				$output = array_merge($output,$member_base);
			} else $range_stop = true;
 
			if(!$range_stop) {
				// Advance range
				$range_start = $range_end + 1;
				$range_end = $range_end + $range_size;
			}
		} while($range_stop == false);
 
	// Containers and Organizational Units, use pagination to overcome SizeLimit
	} elseif($object_class == 'c' || $object_class == "o") {
 
		$pagesize = 1000;
		$counter = "";
		do {
			ldap_control_paged_result($mydap,$pagesize,true,$counter);
			
			// Query Container or Organizational Unit members
			$results = ldap_search($mydap,$object_dn,'objectClass=user',array('sn')) or die('Error searching LDAP: '.ldap_error($mydap));
			$members = ldap_get_entries($mydap, $results);
 
			// Remove 'count' element from array
			array_shift($members);
 
			// Pull the 'dn' from each result, append to output
			foreach($members as $e) $output[] = $e['dn'];
 
			ldap_control_paged_result_response($mydap,$results,$counter);
		} while($counter !== null && $counter != "");
	
	// Invalid object_class specified
	} else die("Invalid mydap_member object_class, must be c, g, or o");
 
	// Return alphabetized member list
	sort($output);
	return $output;
}

// ==================================================================================
// Example Usage
// ==================================================================================
   
// Establish connection
mydap_start(
	'SERVICEACCOUNT', // Active Directory search user
	'PASSWORD', // Active Directory search user password
	'ldaps://DOMAINSERVER/', // Active Directory server
	636 // Port (optional)
);
 
// Query users using mydap_members(object_dn,object_class)
// The object_dn parameter should be the distinguishedName of the object
// The object_class parameter should be 'c' for Container, 'g' for Group, or 'o' for Organizational Unit
// If left blank object_class will assume Group
// Ex: the default 'Users' object in AD is a Container
// The function returns an array of member distinguishedName's
$members = mydap_members('OU=OUEXAMPLE,OU=OUEXAMPLE,DC=DCEXAMPLE,DC=DCEXAMPLE,DC=DCEXAMPLE','o');
if(!$members) die('No members found, make sure you are specifying the correct object_class');
 
// Now collect attributes for each member pulled
// Specify user attributes we want to collect, to be used as the keep parameter of mydap_attributes
$keep = array('samaccountname','mail','name','physicaldeliveryofficename','telephonenumber','department','title','useraccountcontrol','personaltitle','givenname','sn','middlename','Custom-data' );



// DB verbinding gegevens
$servername = "127.0.0.1";
$username = "Joomla";
$password = "PASSWORD";
$dbname = "joomla";
$Joomla_prefix="PREFIX";
// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);


// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}


 
// Iterate each member to get attributes
$i = 1; // For counting our output
foreach($members as $m) {
	
	
	// Query a user's attributes using mydap_attributes(member_dn,keep)
	// The member_dn is the step $m of this foreach
	$attr = mydap_attributes($m,$keep);
 
	// Each attribute is returned as an array, the first key is [count], [0]+ will contain the actual value(s)
	// You will want to make sure the key exists to account for situations in which the attribute is not returned (has no value)
	
	//deze 2 woren gebruikt bij de checks
	$Actief = isset($attr['useraccountcontrol'][0]) ? $attr['useraccountcontrol'][0] : "000";
	$samaccountname = isset($attr['samaccountname'][0]) ? $attr['samaccountname'][0] : "[no account name]";
	
	// extra attributen
  // complete naam
	$name = isset($attr['name'][0]) ? $attr['name'][0] : "[no employee ID]";
	$name= utf8_decode($name); //utf voor speciale tekens in de naam
  // voornaam
  $firstname = isset($attr['givenname'][0]) ? $attr['givenname'][0] : "[no givenName]";
	$firstname= utf8_decode($firstname); //utf voor speciale tekens in de naam
  //tussenvoegsel
  $middlename = isset($attr['middlename'][0]) ? $attr['middlename'][0] : "";
  $middlename= utf8_decode($middlename); //utf voor speciale tekens in de naam
  
  //achternaam
  $lastname = isset($attr['sn'][0]) ? $attr['sn'][0] : "[no lastName]";
  $lastname= utf8_decode($lastname); //utf voor speciale tekens in de naam
  
	$mail = isset($attr['mail'][0]) ? $attr['mail'][0] : "[no email]";
	$physicaldeliveryofficename = isset($attr['physicaldeliveryofficename'][0]) ? $attr['physicaldeliveryofficename'][0] : "[no Kantoor]";
	$telephonenumber = isset($attr['telephonenumber'][0]) ? $attr['telephonenumber'][0] : "000";
		
	$department = isset($attr['department'][0]) ? $attr['department'][0] : "[no department]";
	$department= utf8_decode($department); //utf voor speciale tekens in de naam
	
	//functiebenaming
	$title = isset($attr['title'][0]) ? str_replace('medewerker ','Mdw. ',$attr['title'])[0] : "[no title]";
	$title = utf8_decode(str_replace(' medewerker','',$title));
	
	// mr in g etc
	$Persoonlijketitel = isset($attr['personaltitle'][0]) ? $attr['personaltitle'][0] : ""; 
  
	
	//Attribuut Custom-data has different syntax because the '-' sign in the name.
	${'Custom-data'} = isset($attr['Custom-data'][0]) ? $attr['Custom-data'][0] : "[no data]";
		
	
	//Controleren of het AD account actief is.
	if ($Actief==514 or $Actief==546 or $Actief==66050 or $Actief==66082 or $Actief==252658 or $Actief==26290 or $Actief==328194 or $Actief==328226): 
	{
		//account niet actief account blokkeren
		$sql = "UPDATE IGNORE ".$Joomla_prefix ."_comprofiler Ldap INNER JOIN ".$Joomla_prefix ."_users joomla ON Ldap.user_id=Joomla.id SET 
		joomla.block = 1
		WHERE joomla.USERNAME ='$samaccountname'";
		

		if ($conn->query($sql) === TRUE) {
			echo "$i. $samaccountname account uitgeschakeld<br>";
		} else {
			echo "$i. $samaccountname Error account blokkeren: " . $sql . "<br>" . $conn->error;
		}
		
	}
	else:
	{
		
		//Controleren of de gebruiker al bestaat in de database
		$sql = "SELECT * FROM ".$Joomla_prefix ."_users WHERE USERNAME='$samaccountname'";
		if(mysqli_num_rows($conn->query($sql)) > 0)
		{
  		echo "$i. $samaccountname Bestaat al <br>";
      //account  activeren voor als iemand even uitdienst is geweest 
      // emailadres bijwerken wil wel eens wijzigen indien mensen trouwen bv
  		$sql = "UPDATE IGNORE ".$Joomla_prefix ."_comprofiler Ldap INNER JOIN ".$Joomla_prefix ."_users joomla ON Ldap.user_id=Joomla.id SET 
  		joomla.block = 0,
      joomla.email= '$mail',
      joomla.name= '$name'
  		WHERE joomla.USERNAME ='$samaccountname'";
      
      if ($conn->query($sql) === TRUE) {
  			echo "$i. $samaccountname account geactiveerd<br>";
  		} else {
  			echo "$i. $samaccountname Error account activeren: " . $sql . "<br>" . $conn->error;
  		}
    
		}
		else
		{
		 
			// gebruiker toevoegen aan joomla tabel
			$sql = "INSERT IGNORE INTO ".$Joomla_prefix ."_users (name,username,email,params,registerDate) VALUES ('$name','$samaccountname','$mail','{\"auth_type\":\"LDAP\",\"auth_domain\":\"Hoge Raad\"}',now())";
			if ($conn->query($sql) === TRUE) {
				echo "$i. $samaccountname account toegevoegd <br>";
			} else {
				echo "$i. $samaccountname Error account toevoegen: " . $sql . "<br>" . $conn->error;
			}
			
			// basis rechten geven (2 = Registered).
			$sql = "INSERT IGNORE INTO ".$Joomla_prefix ."_user_usergroup_map (user_id ,group_id ) SELECT id,2 FROM ". $Joomla_prefix ."_users joomla where joomla.USERNAME='$samaccountname'";
			if ($conn->query($sql) === TRUE) {
				echo " $samaccountname account rechten toegevoegd <br>";
			} else {
				echo "$i. $samaccountname Error account rechten toevoegen: " . $sql . "<br>" . $conn->error;
			}
		
		}
	}
	endif;

	// Zorgen dat de joomla en communitybuilder tabel gelijk zijn
	if ($conn->query("INSERT IGNORE INTO ". $Joomla_prefix ."_comprofiler(id,user_id) SELECT id,id FROM ". $Joomla_prefix ."_users joomla where joomla.USERNAME='$samaccountname'") === TRUE) {
		echo " $samaccountname Cb bijgewerkt <br>" ;
	} else {
		echo "$i. $samaccountname Error tabellen syncen: <br>" . $conn->error;
	}

 
$now = new DateTime();
$now= $now->format('Y-m-d H:i:s');
	
	
	// Resultaat weergeven 
	/*echo "$i. $samaccountname - $mail - $name - ${'Custom-data'} - $physicaldeliveryofficename - " . substr($telephonenumber, -3) . $now;*/
		
	// communitybuilder tabel bijwerken

  // controleren of avatar aanwezig is.
  $avatar = '';
  $avatarapproved= 0;
	$filename = 'D:\\inetpub\\intranet\\images\\comprofiler\\'. $samaccountname. '.png';

	if (file_exists($filename)) {
		$avatar = $samaccountname. '.png';
		$avatarapproved = 1;
	} else {
	//$avatar = 'nophoto.png';
  $avatar = NULL;	
  $avatarapproved = 0;
	}


	$sql = "update ignore ". $Joomla_prefix ."_comprofiler Ldap inner join ". $Joomla_prefix ."_users joomla on Ldap.user_id=Joomla.id SET 
	Ldap.cb_kantoor= '$physicaldeliveryofficename', 
	Ldap.cb_telefoonnummer='$telephonenumber',
	Ldap.cb_afdeling= '$department',
	Ldap.cb_Data= '${'Custom-data'}',
	ldap.cb_functiebenaming= '$title',
	ldap.cb_persoonlijketitel= '$Persoonlijketitel',
  ldap.firstname= '$firstname',
  ldap.middlename= '$middlename',
  ldap.cb_achternaam= '$lastname',
	ldap.cb_telnrkort = '" . substr($telephonenumber, -3) . "',
	Ldap.avatar= '$avatar',
  Ldap.avatarapproved= $avatarapproved,
	joomla.name= '$name'
	WHERE joomla.USERNAME ='$samaccountname'";
  

	

	if ($conn->query($sql) === TRUE) {
	  echo "<br>";
	} else {
		echo "$i. $samaccountname Error ldap gegevens bijwerken: " . $sql . "<br>" . $conn->error;
	}
	

 
	$i++;
	}
 
// Here you could run another mydap_members() if needed, merge with previous results, etc.
 

 
// Here you can open a new connection with mydap_connect() if needed, such as to a different AD server

// User in the OU Accounts Disabled should be disabled.
$members = mydap_members('OU=Accounts Disabled,DC=DCEXAMPLE,DC=DCEXAMPLE','o');
if(!$members) die('No members found, make sure you are specifying the correct object_class');


echo "Accounts uitschakelen<br>";
// Iterate each member to get attributes
$i = 1; // For counting our output
foreach($members as $m) {
  
		
	// Query a user's attributes using mydap_attributes(member_dn,keep)
	// The member_dn is the step $m of this foreach
	$attr = mydap_attributes($m,$keep);
 
	// Each attribute is returned as an array, the first key is [count], [0]+ will contain the actual value(s)
	// You will want to make sure the key exists to account for situations in which the attribute is not returned (has no value)
	
	//deze 2 woren gebruikt bij de checks
	$Actief = isset($attr['useraccountcontrol'][0]) ? $attr['useraccountcontrol'][0] : "000";
	$samaccountname = isset($attr['samaccountname'][0]) ? $attr['samaccountname'][0] : "[no account name]";
	

	// Resultaat weergeven 
	echo "$i. $samaccountname " . $Actief . "<br>";
	
	
	
	//account niet actief account blokkeren indien dat nog niet is gedaan.
	$sql = "UPDATE IGNORE ".$Joomla_prefix ."_comprofiler Ldap INNER JOIN ".$Joomla_prefix ."_users joomla ON Ldap.user_id=Joomla.id SET 
	joomla.block = 1,
	Ldap.lastupdatedate = now()
	WHERE joomla.USERNAME ='$samaccountname' AND joomla.block = 0";
	

	if ($conn->query($sql) === TRUE) {
		echo "$i. $samaccountname account uitgeschakeld indien dat nog niet was gedaan<br>";
	} else {
		echo "$i. Error account uitgeschakeld: " . $sql . "<br>" . $conn->error;
	}
	
	
	
	$i++;
	
}

// Close connection
mydap_end();

//sluit DB verBinding
$conn->close();

?> 