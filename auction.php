<?php

include ('config.php');

// The form allows user to upload and delete bid data to database
// It also provide links to delete cron job list and restore if deleted.

// get_bid_form_data() gets any auction data previously provided by the user. 
// it puts data into an array.

function get_bid_form_data() {

	db_connect();

	$sql = "SELECT * FROM bid_form";
	$result = mysql_query($sql);
	
	$bid_form = [];
	while ($row = mysql_fetch_array($result)) {
		$auction_row = $row['auction_row'];
		$bid_form[$auction_row] = array(
			'auction_url' => $row['auction_url'],
			'max_live_bids' => $row['max_live_bids'],
			'stop_rate' => $row['stop_rate']
			);
	}
	
	ksort($bid_form); // this sorts the data in order of rows.
	return $bid_form;
	
}

$bid_form = get_bid_form_data();

?>





<head>

	<style>

		.fc {

			padding-left: 2%;
			padding-right: 2%;
			padding-top: 0.2cm;
			font-family: verdana,helvetica,arial,sans-serif;
		}

	form {
		display: inline-block; 
	}		
		
	</style>

</head>

<body>

	<div class="fc">
		
		<h3 style="text-align:center">Funding Circle Auto Bid</h3>
		<h5 style="text-align:center">See bottom on page for instructions.</h3>		

		
		<!-- These two buttons are links for deleting cron job list and restoring.  -->
		
		<div style="text-align:center">
			<form action='clear_cron.php' >
				<input type="submit" style="width:250 display:inline-block" value="Stop rebooting every 2 mins">
			</form>				
			
			<form action='reload_cron.php'>
				<input type="submit" style="width:250 display:inline-block" value="Restart autobidding every 2 mins">
			</form>		
		</div>

		
		<table>
			<tr>
				<td></td>
				<td>Auction url</td>
				<td>Maximum Live Bids</td>
				<td>Stop Rate</td>
				<td></td>
				<td></td>
			</tr>
					
<?php for ($i = 1; $i <= 20; $i++) { ?>
			<tr>
				<form action='save.php' method='post'><input type='hidden' name='auction_row' value='<?php echo $i; ?>'>
					<td><?php echo $i;?>.</td>
					<td><input type="text" name="auction_url" size='60' value='<?php
						if (array_key_exists($i, $bid_form)) echo $bid_form[$i]["auction_url"]; ?>'></td>
					<td><input type="text" name="max_live_bids" size='20' value='<?php
						if (array_key_exists($i, $bid_form)) {
							if ($bid_form[$i]["max_live_bids"] == false) {
							} else echo $bid_form[$i]["max_live_bids"]; 							
						};  
							?>'></td>						
					<td><input type="text" name="stop_rate" size='20' value='<?php
						if (array_key_exists($i, $bid_form)) {
							if ($bid_form[$i]["stop_rate"] == false) {
							} else echo $bid_form[$i]["stop_rate"]; 							
						};  
							?>'></td>	
					<td><input type='submit' value='Save'></td>						
				</form>
				<form action='delete_row.php' method='post'><input type='hidden' name='auction_row' value='<?php echo $i; ?>'>
					<td><input type="submit" value="Clear"</td>
				</form>
					<td><a href="logfile<?php echo $i;?>">View Log</a></td>
			</tr>

<?php } ?> 
			

			
		</table>
		
		<h4>Auction URL:</h4>
		<p>The fundingcircle.com page that contains information for a specific loan request.</p>
		<h4>Maximum Live Bids: </h4>
		<p>Bidding continues in 20 pound amounts until Maximum Live Bids is reached. The interest rate used is the highest rate available.	Once Maximum Live Bids is reached the loan page will be repeatedly refreshed to see if the user has been outbid. If the user has been outbid the live bid amount will fall below the Maximum Live Bids. Once this happens bidding will continue again.</p>
		<h4>Stop Rate:</h4>
		<p>Bidding and checking of live bids continues until stop interest rate is reached and autobidding stops.</p>
		<p>Clearing data will stop autobidding instantly. Once data is saved it will be stored in a database. Data is collected from database every two minutes.</p>

	</div>

	

