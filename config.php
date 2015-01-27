<?php


// Attempted to use dynamic $_SERVER['document_root']. 
// and tried to use __DIR__.
// These didn't work on remote server/from command line.

define ('COOKIEFILE', '/kunden/homepages/16/d528087927/htdocs/beyerbeyer/fc/cookie.txt'); 



define ('EMAIL', 'philipxyz@btopenworld.com');
define ('PASSWORD', 'Funding2014%');
define ('PID', getmygid()); // this is not used. it does not find PID on host.



date_default_timezone_set('Europe/Lisbon');

function put_time() {

	$time = date("d/m/y g:i:s A");
	$time = $time . ". ";
	return $time;
	
}




function db_connect() {

	$hostname="db560401255.db.1and1.com";
	$username="dbo560401255";
	$password="Mothership99";
	$database="db560401255";

	mysql_connect($hostname, $username, $password);
	mysql_select_db($database);

}

?>