<?php


session_start();

include ('config.php');


include ('fc_login_func.php');

if (file_exists(LOGFILE)) unlink (LOGFILE);	

$data = login();

if (logged_in($data) == true) {

	echo "You are logged in to funding circle as:<br>" . EMAIL ;
	fc_log('You are logged in');

}

//header('Location: auction.php');

?> 