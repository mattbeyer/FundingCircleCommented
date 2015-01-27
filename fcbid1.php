<?php
/*

First there is a check made to see if there is data in the database that can be used to autobid. 
got_db_bid_data() is first function used. It get bid data from database to start auto bidding.
fcbid*.php calls fcbid($auction_url, $max_live_bids, $stop_rate).
It uses data retrieved from the database to autobid. 

Typical chronological list of keys functions used, for logged out in user:

got_db_bid_data()
row_exists(AUCTION_ROW)
db_connect()
fcbid($auction_url, $max_live_bids, $stop_rate)
logged_in($logged_in_html)

A logged out user will need to login:

login()
goto_security_q()	
fc_login() to submit email and password to get security questions.
get_login_fields() to create array contact form submitit data
get_post_string($fields) to convert form data array into post string for cURL
question_num($data) gets a security question number from return HTML
answer_q($question_num) to answer security question.
fc_security($question_num) submit form data answering security question

Once logged in:
logged_in_bid_init($auction_url, $max_live_bids, $stop_rate) to begin auto bidding
get_auction_html($auction_url) to get auction html
get_bid_data($auction_html) to get bid data needed to make a bid
auto_bid($bid_data) to get bid data from array to start autobid
bid($auth_token, $top_rate, $bid_url) to make a bid
get_bid_data($bid_return) to update bid data once bid is made.

logged_in_bid_init will keep checking so it if:
more bids can be made, or
page needs to be refreshed, or
script can be exited

Issues:
cron PHP version v browser
file_put_contents with cron/remotely
autobidding broke after 10 mins plus... no bid data  - cURL returned unrendered html
unable to use dynamic path for cookiefile on remote host
PID listed on comment line different to getmypid().

*/  



error_reporting(E_ALL); 
ini_set('display_errors', 1); 
ini_set('max_execution_time', 0); // Change to unlimited to allow let script refresh auction page many times during testing.

include ('config.php');
include ('fc_login_func.php'); 	// this file contains all functions needed to login.
include ('db_functions.php');	// this file provides all database functions. 


define ('AUCTION_ROW', 1); 
define ('LOGFILE', 'logfile' . AUCTION_ROW . '.html'); 
if (file_exists(LOGFILE)) unlink(LOGFILE); // deletes previous logfile.


// fc_log($log_line) writes lines to logfile.
// file_put_contents was used locally. This didn't worked remotely. 
// So fopen and fwrite were used instead.

function fc_log($log_line) {


	$fp = fopen(LOGFILE , "a+"); 
	fwrite($fp, $log_line);
	fclose($fp);


}

function log_no_bid_data() {

	fc_log(put_time() . AUCTION_URL . '. There was no bid data. <br>');	

}


function curl_opts($url)	{

	$ch = curl_init();

	curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1)");
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
	curl_setopt($ch, CURLOPT_COOKIEJAR, COOKIEFILE);
	curl_setopt($ch, CURLOPT_COOKIEFILE, COOKIEFILE);
	curl_setopt($ch, CURLOPT_HEADER, 0);  
	curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
	curl_setopt($ch, CURLOPT_TIMEOUT, 0);
	curl_setopt($ch, CURLOPT_URL, $url);
	
	// At times this option seemed to stop SSL errors. There seemed to be much less SSL errors working remotely.	
	//curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1); 		

	
	sleep(1); 

	return $ch;

}

// get_auction_html($auction_url) makes cURL request to return auction page containing bid data needed.
function get_auction_html($auction_url) {
	
	$ch = curl_opts($auction_url);
 	$auction_html = curl_exec($ch); 
	curl_close($ch);
	return $auction_html;

}

function get_bid_data($auction_html) {

	$doc = new DOMDocument(); // allows retrieval of DOM data using DOMDocument class. 
	
	if ($auction_html == true) $doc->loadHTML($auction_html); // loads auction html
	$rates_list = $doc->getElementById('bid_annualised_rate')->childNodes; // attempts to find list of available bid interest rates.

	// If no interest rates found function returns false to logged_in_bid_init()	and log is made in log file. 
	// This may have happened for several reasons. 
	if ($rates_list == null) {
		log_no_bid_data();
		return false;		
	}
	$rates_arr = array();

	
	// Puts the interest rate value of each node into an array. 
	foreach($rates_list as $rate) {
		$rates_arr[] = $rate->firstChild->nodeValue;
	}

	// Gets the top rate available. The first index, [0] is the word 'Choose'.
	$top_rate = $rates_arr[1];

	// This gets the url of teh submitted bid data. 
	$bid_form = $doc->getElementById('new_bid');
	$action = $bid_form->getAttribute('action');

	// This find the current 'live bids' the user has made on the auction.
	$live_bids = $doc->getElementById('live_bids')->nodeValue;
	$live_bids = ltrim($live_bids, "Â£"); // this gets rid of the Â£ that appears on the nodeValue.
	$live_bids = intval($live_bids); // turn the string numbers into integer value.
	
	
	// Creates url to send form data to to make bid.
	$bid_url = 'https://www.fundingcircle.com' . $action;

	// Gets the authentication token needed to make bid.
	$auth_token = $bid_form->firstChild->firstChild->nextSibling->getAttribute('value');

	// Creates array of all the bid data needed. 
	$bid_data = array(
	
		'auth_token' => $auth_token,
		'top_rate' => $top_rate,
		'bid_url' => $bid_url,		
		'live_bids' => $live_bids
	
	);
	
	return $bid_data;

	
}


function bid($auth_token, $top_rate, $bid_url) {

	// Setup up curl handle.
	$ch = curl_opts($bid_url);
	sleep(1);		

	
	// These are the fields that are submitted by www.fundingcircle.com to make a bid.
	
	$fields = array(
						'utf8' => urlencode('✓'),
						'authenticity_token' => urlencode($auth_token),
						'is_bid' => urlencode('1'),
						'bid[amount]' => urlencode('20'),
						'bid[annualised_rate]' => urlencode($top_rate),
						'make_bid' => ''

					);
					
	$fields_string = "";
	
	// Use fields array to create post string for cURL request.
	foreach ($fields as $key => $value) {
					$fields_string .= $key.'='.$value.'&';
		}
		
	$post_string = rtrim($fields_string, '&'); // removes last & from end of post fields string.

	curl_setopt($ch, CURLOPT_POST, 1);  
	curl_setopt($ch, CURLOPT_POSTFIELDS, $post_string);  

	sleep(1);
	$bid_return = curl_exec($ch); // make curl request

	if ($bid_return == false) log_curl_error($ch); // make log if cURL request returned error.
	if ($bid_return == true) {
	
		fc_log(put_time() . "PID = " . PID . " " . AUCTION_URL . '. Bid attempt true.<br>');		
	
	}	
	
	curl_close($ch);
	
	return $bid_return; // return returned html of successful bid 


// Gets bid data from array ready for bid($auth_token, $top_rate, $bid_url).
function auto_bid($bid_data) {

	$auth_token = $bid_data['auth_token'];
	$top_rate = $bid_data['top_rate'];
	$bid_url = $bid_data['bid_url'];

	// Call bid($auth_token, $top_rate, $bid_url) to make the bid.
	$bid_return = bid($auth_token, $top_rate, $bid_url);
	return $bid_return;
		
}

function save_auction_html($auction_html) {

	$fp = fopen('auction_html.html' , "w+");
	fwrite($fp, $auction_html);
	fclose($fp);

}


function logged_in_bid_init($auction_url, $max_live_bids, $stop_rate) {

	// get_auction_html($auction_url) makes cURL request to return auction page containing bid data needed.
	
	$auction_html = get_auction_html($auction_url);
	sleep(5); 
	
	// get_bid_data($auction_html) gets the bid data needed to start autobid.
	$bid_data = get_bid_data($auction_html);		

	
	if ($bid_data == true) {
	
		save_auction_html($auction_html); // Auction page html saved for debugging
		fc_log(put_time() . 'Auction data true.<br>' . $bid_data['live_bids']); // Log made of getting good data.
		
	}	

	if ($bid_data == false) {
	
		save_auction_html($auction_html); // Auction page html saved for debugging
		fc_log(put_time() . 'Auction data false.<br>');
		exit; // Exit script. Script will be executed again by Cron every two minutes.
		
	}
	
	$live_bids = $bid_data['live_bids'];
	$top_rate = $bid_data['top_rate'];
	$top_rate = (float) rtrim($top_rate, "%"); // remove % sign.

	$while_entry_time = time();
	
	// This is main part of autobidding.
	
	// This loop will continued whilst the user defined stop rate is less than the top rate on auction page.
	while ($top_rate > $stop_rate) {

	
		$top_of_while_time = time(); // Get the time of start autobidding.
		$time_elapsed = $top_of_while_time - $while_entry_time; // Get the time elapsed during autobidding loop.
	
		// Exit if 120 seconds of autobidding has elapsed. Cron will restart every two minutes #.
		if ($time_elapsed > 100) {
		
			fc_log(put_time() . 'Run time elapsed.<br>');
			exit;
		
		}

		
		// Log current bid data for user. 
		fc_log(put_time() . "PID = " . PID . " " . AUCTION_URL . '. Live bids are ' . $live_bids . '. Max live bids are ' . $max_live_bids . '. ');
		fc_log(' Top rate is ' . $top_rate . '. Stop rate is ' . $stop_rate . '. ');	
	
	
		// Check to see if live bids is less than user defined maximum live bids.
				
		if ($live_bids < $max_live_bids) {
		
			// if the maximum live bids is less and current live bids AND stop rate is less than the top rate
			// then a bid can be made.
		
			fc_log(put_time() . 'Bid attempted.<br>');

			// Begin placing a bid.
			$bid_return = auto_bid($bid_data);
			sleep(5);
			
			// Check returned html of successful bid for new bid data.
			$bid_data = get_bid_data($bid_return);

			// If new bid data is not found return to fcbid($auction_url, $max_live_bids, $stop_rate)
			if ($bid_data == false) return false; 
			fc_log(put_time() . "PID = " . PID . " " . AUCTION_URL . '. Bid successful.<br>');	
			
			// Update bid data variables with refreshed data.
			$live_bids = $bid_data['live_bids'];
			$top_rate = $bid_data['top_rate'];
			$top_rate = (float) rtrim($top_rate, "%");
		}	

		
		// If stop rate is less than top... but the Maximum live bids has reached, or exceeded,
		// the current live bids, then..
		// the auction page need to be repeatedly refreshed to see if user has been outbid by another user.
		 
		if ($live_bids >= $max_live_bids) {

			fc_log('Refreshing auction page to check for outbids.<br>');

			// get_auction_html($auction_url) refreshes the auction page.
			$auction_html = get_auction_html($auction_url);
			sleep(5);
			
			// get_bid_data($auction_html) is used to get refreshed bid data.
			$bid_data = get_bid_data($auction_html);	
			if ($bid_data == false) return false; 
			
			// Updated bid data is set into variables.
			$live_bids = $bid_data['live_bids'];
			$top_rate = $bid_data['top_rate'];
			$top_rate = (float) rtrim($top_rate, "%");
			
		}
		
		// Check is made to see if user has deleted bid data from the database.
		// If the bid data has been deleted the script is exited.
		$db_bid_data = row_exists(AUCTION_ROW);
		if ($db_bid_data == false) {		
			fc_log(put_time() . "PID = " . PID . " " . AUCTION_URL . '. Bid data cleared.<br>');	
			exit('Bid data cleared.');	
		}
		
	}  

	// If stop rate is the same, or greater than, the top rate, the script is exited. 
	if ($top_rate <= $stop_rate) {
	
		fc_log(put_time() . "PID = " . PID . " " . AUCTION_URL . ' Top rate is ' . $top_rate . '. Stop rate is ' . $stop_rate . '. Auto bid stopped.<br>');	
		exit;
	}
		

		
		
		
}	

// 	get_my_lending_html() makes cURL request and returns html that will show if use is logged in.

function get_my_lending_html() {

	$url = 'https://www.fundingcircle.com/my-account/my-lending/';
	$ch = curl_opts($url);
 	$logged_in_html = curl_exec($ch); 
	curl_close($ch);
	
	return $logged_in_html;
}

function fcbid($auction_url, $max_live_bids, $stop_rate) {

	// First check is made to see if user is logged in. 
	// 	get_my_lending_html() makes cURL request and returns html that will show if use is logged in.
	$logged_in_html = get_my_lending_html();

	// If user is logged into logged_in_bid_init() is called
	if (logged_in($logged_in_html) == true) {
		$log_line = put_time() . "PID = " . PID . " " . AUCTION_URL . '. Already logged in. Cookiefile kept.<br>';
		fc_log($log_line);		
		$logged_in_html = logged_in_bid_init($auction_url, $max_live_bids, $stop_rate);
	}	
	
	
	while (logged_in($logged_in_html) == false) {
	$log_line = put_time() . "PID = " . PID . " " . AUCTION_URL . '. Need to login.<br>';
		fc_log($log_line);		
		$logged_in_html = login();
		if ($logged_in_html == true) {
			$log_line = put_time() . "PID = " . PID . " " . AUCTION_URL . '. You are now logged in.<br>';
			fc_log($log_line);
			
		}
		if ($logged_in_html == false) exit; 
		while (logged_in($logged_in_html)) {
			$logged_in_html = logged_in_bid_init($auction_url, $max_live_bids, $stop_rate);
		}
		
	}

}

function got_db_bid_data() {

	$db_bid_data = row_exists(AUCTION_ROW); // checks to see if row exists and return bid data if available. 
	
	$auction_url = $db_bid_data['auction_url'];
	$max_live_bids = $db_bid_data['max_live_bids'];
	$stop_rate = $db_bid_data['stop_rate'];

	// Error messages returned if data is missing, then exit. 
	
	if ($auction_url == false) {
		fc_log(put_time() . 'Missing or unusable bid information.<br>');
		exit('Missing bid information...');
	}

	if ($max_live_bids == false) {
		fc_log(put_time() . 'Missing or unusable bid information.<br>');
		exit('Missing bid information...');
	}

	if ($stop_rate == false) {
		fc_log(put_time() . 'Missing or unusable bid information.<br>');
		exit('Missing bid information...');
	}
	
	return $db_bid_data;
}

$db_bid_data = got_db_bid_data(); // Check that there is data available in the database to use. Gets bid data array.

// Bid data is placed into variables for fcbid($auction_url, $max_live_bids, $stop_rate) to begin. 

$auction_url = $db_bid_data['auction_url'];
$max_live_bids = $db_bid_data['max_live_bids'];
$stop_rate = $db_bid_data['stop_rate'];

define ('AUCTION_URL', $auction_url);

fcbid($auction_url, $max_live_bids, $stop_rate);	








					


