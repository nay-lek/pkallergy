<?php 
    $ini = parse_ini_file('app.ini');
	if (!defined('HOSTNAME')) define('HOSTNAME', $ini['db_host']);
	if (!defined('USDBNAME')) define('USDBNAME', $ini['db_user']);
	if (!defined('PSDBNAME')) define('PSDBNAME', $ini['db_password']);
	if (!defined('_db_')) define('_db_', $ini['db_name']);
	global $Conn;

	$Conn = mysqli_connect(HOSTNAME, USDBNAME, PSDBNAME);
	        mysqli_select_db($Conn, _db_);
			mysqli_set_charset($Conn,"utf8");
			if (!$Conn) {
				die("Connection failed: " . mysqli_connect_error());
			}
	
				




?>