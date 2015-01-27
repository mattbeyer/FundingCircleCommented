<?php

function logged_in($data) {
	
	if (strpos($data, 'Available funds') !== false) {
		return true;	
	} else return false;

}

function question_num($data) {

	$question_num = 0;

	if (strpos($data, 'Where did you grow up?') !== false) {
		$question_num = '1';
		}

	if (strpos($data, 'What school did you attend when you were 10 years old?') !== false) {
		$question_num = '2';
		}

	if (strpos($data, 'What was the name of your best friend at school?') !== false) {
		$question_num = '3';
		}	

	return $question_num;


}

function curl_head() {

	$ch = curl_init();	
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);
	curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1)");
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
	curl_setopt($ch, CURLOPT_COOKIEJAR, COOKIEFILE);
	curl_setopt($ch, CURLOPT_COOKIEFILE, COOKIEFILE);
	curl_setopt($ch, CURLOPT_HEADER, 0);  
	curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 120);
	curl_setopt($ch, CURLOPT_TIMEOUT, 120);
	curl_setopt($ch, CURLOPT_POST, 1); 

	return $ch;
	
}

function log_curl_error($ch) {

	$curl_error = curl_error($ch);
	fc_log($curl_error . "<br>");
	
}

function get_login_fields() {

	$fields = array(
						'client_id:' => urlencode(''),
						'redirect_uri' => urlencode(''),
						'state' => urlencode(''),
						'response_type' => urlencode(''),
						'signin[_csrf_token]' => urlencode('2fbe0fc73dfa9ddf927bc0c52e7a3013'),
						'signin[username]' => EMAIL,
						'signin[password]' => PASSWORD

					);
					
	return $fields;				

}



function get_post_string($fields) {

	$fields_string = "";
	foreach ($fields as $key => $value) {
		$fields_string .= $key.'='.$value.'&';
	}
	$post_string = rtrim($fields_string, '&');	
	return $post_string;

}

function fc_login() {

	if (file_exists(COOKIEFILE)) unlink (COOKIEFILE);		

	$ch = curl_head();
	$fields = get_login_fields();
	$post_string = get_post_string($fields);
	
	curl_setopt($ch, CURLOPT_URL, 'https://www.fundingcircle.com/login');
	curl_setopt($ch, CURLOPT_POSTFIELDS, $post_string);  
  
	$data = curl_exec($ch);
	if ($data == false) log_curl_error($ch);
 
	return $data;

}

function get_answer($question_num) {

	if ($question_num == 1) $answer = 'hallgreen'; 
	if ($question_num == 2) $answer = 'hallgreenjuniors';
	if ($question_num == 3) $answer = 'greeny';
	return $answer;

}

function get_security_fields($question_num, $answer) {

	$fields = array(
						'client_id:' => urlencode(''),
						'redirect_uri' => urlencode(''),
						'state' => urlencode(''),
						'response_type' => urlencode(''),
						'question' => ($question_num),
						'answer' => ($answer)

					);
					
	return $fields;				

}

function fc_security($question_num) {

	$ch = curl_head();
	$answer = get_answer($question_num);
	$fields = get_security_fields($question_num, $answer);
	$post_string = get_post_string($fields);
	
	curl_setopt($ch, CURLOPT_POSTFIELDS, $post_string);  	
	curl_setopt($ch, CURLOPT_URL, 'https://www.fundingcircle.com/security-questions/');		
	
	$data = curl_exec($ch);
	$err = curl_errno($ch);

	if ($data == false) log_curl_error($ch);
	
	return $data;
	
}

function goto_security_q() {

	$question_num = false;
	$login_attempt = 1;
	
	while ($question_num == false) {
		fc_log("Attempt to get question.<br>");
		
		$data = fc_login();
		$question_num = question_num($data);
		
		$login_attempt++;
	
	}
	
	fc_log("Got security Q" . $question_num . "<br>");
	return $question_num;
}

function save_answered_q_html($data) {

	$fp = fopen('answered_q_html.html' , "w+");
	fwrite($fp, $data);
	fclose($fp);

}

function answer_q($question_num) {
	$answer_attempt = 1;
	while ($question_num == true) {
		fc_log($answer_attempt . ". Attempt to answer Q.<br>");
		$data = fc_security($question_num);
		$answer_attempt++;
		$question_num = question_num($data);

	}
	
	save_answered_q_html($data);
	
	return $data;	
	
}

function login() {

	$data = false;
	
	while (logged_in($data) == false) {
		$question_num = goto_security_q();
		$data = answer_q($question_num);
	}	
	
	return $data;
}

?>