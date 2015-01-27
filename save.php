<?php

include ('config.php');
include ('db_functions.php');

function bid_form_to_db() {

	$auction_row = $_POST['auction_row'];
	$row_exists = row_exists($auction_row);

	if ($row_exists == false) insert_row();
	if ($row_exists == true) update_row();	
	

	
}

function inputs_filled() {
	if (($_POST['auction_url'] == true) && ($_POST['max_live_bids'] == true) && ($_POST['stop_rate'] == true)) {
		return true;
	} 
	return false;

}

function save() {

	if (inputs_filled() == false) {
		echo "Incomplete form";
		exit;
	}

	if (inputs_filled() == true) {
		bid_form_to_db();
		header('Location: auction.php');
		exit;
	}
	
}


save();

?>