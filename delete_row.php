<?php

error_reporting(E_ALL); 

include ('config.php');
db_connect();

$auction_row = $_POST['auction_row'];

$sql = "DELETE FROM bid_form WHERE auction_row ='" . $auction_row . "'"; 


/*
$sql = 	'UPDATE bid_form ' .
		'SET auction_url =NULL, ' .
		'max_live_bids =NULL, ' .	
		'stop_rate =NULL '.			
		'WHERE auction_row =' . $auction_row;
*/

$result = mysql_query($sql);

header("Location: http://www.beyerbeyer.co.uk/fc/auction.php");


?>
