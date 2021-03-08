<?php
/*
    Flexisip HTTP File Transfer Server
    Copyright (C) 2020  Belledonne Communications SARL.

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU Affero General Public License as
    published by the Free Software Foundation, either version 3 of the
    License, or (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU Affero General Public License for more details.

    You should have received a copy of the GNU Affero General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
include("common.php");

function process_request() {
	if (count($_FILES) != 0) {
		$rcvname=$_FILES['File']['name'];
		$ext= strtolower(pathinfo($rcvname, PATHINFO_EXTENSION));

		// if file extension is black listed, append the fallback extension to it
		if (in_array(strtolower($ext), fhft_extension_black_list)) $ext.=".".fhft_extension_fallback;

		$tmpfile=$_FILES['File']['tmp_name'];
		if (strlen($tmpfile) <= 0) {
			fhft_log(logLevel::ERROR, upload_error_message($_FILES['File']['error']));
			bad_request();
		}

		// Check system upload size - can we actually reach this point if the upload size is too big the php script won't run
		if ($_FILES['File']['error'] === UPLOAD_ERR_INI_SIZE) {
			//File too large - system limit
	                fhft_log(logLevel::ERROR, upload_error_message($_FILES['File']['error']));
			bad_request();
		}

		// Check the configured upload size
		if (defined("fhft_maximum_file_size_in_MB") && fhft_maximum_file_size_in_MB>0) {
			if ($_FILES['File']['size']/1024/1024 > fhft_maximum_file_size_in_MB) {
				//File too large - config limit
				fhft_log(logLevel::ERROR, "Error uploading file ".$rcvname." size ".($_FILES['File']['size']/1024/1024)." MB larger than configured limit ".fhft_maximum_file_size_in_MB." MB");
				bad_request();
			}
		}

		fhft_log(logLevel::DEBUG, 'Uploaded '.$rcvname.' to '.$tmpfile);
		$uploadfile = fhft_tmp_path.uniqid()."_".bin2hex(openssl_random_pseudo_bytes(10)).".$ext";

		if (move_uploaded_file($tmpfile, $uploadfile)) {
			fhft_log(logLevel::DEBUG, 'Moved to '.$uploadfile);
			if (isset($_SERVER['SERVER_NAME'])) {
				$ipport = $_SERVER['SERVER_NAME'];
			} else {
				// This is not recommended because in case of HA
				// this will return the meta named use to reach the server
				// and not the real domain of the server hosting the file
				$ipport = $_SERVER['HTTP_HOST'];
			}
		        $prefix= (isset($_SERVER["HTTPS"]) && strtolower($_SERVER["HTTPS"])=="on")?"https":"http";
			$start= $prefix."://".$ipport.':'.$_SERVER['SERVER_PORT'].dirname($_SERVER['REQUEST_URI']);
			$http_url = $start."/tmp/".basename($uploadfile); // file will be served in the ./tmp/ directory on the server path

			// valid until now + validity period
			$until = date("Y-m-d\TH:i:s\Z",time()+fhft_validity_period);
			echo '<?xml version="1.0" encoding="UTF-8"?>';
				echo '<file xmlns="urn:gsma:params:xml:ns:rcs:rcs:fthttp">';
					echo '<file-info type="file">';
						echo '<file-size>'.$_FILES['File']['size'].'</file-size>';
						echo '<file-name>'.$_FILES['File']['name'].'</file-name>';
						echo '<content-type>'.$_FILES['File']['type'].'</content-type>';
						echo '<data url = "'.$http_url.'" until = "'.$until.'"/>';
					echo '</file-info>';
				echo '</file>';
			exit();
		} else {
			fhft_log(logLevel::ERROR, "Unable to move uploaded file ".$tmp_file."(".$rcvname.") to ".$uploadfile);
			http_response_code(500);
			exit();
		}
	} else {
		fhft_log(logLevel::ERROR, "HTTP File Upload variables not populated");
		bad_request();
	}
}

// first check server settings
check_server_settings();
if (isset($_SERVER['CONTENT_LENGTH']) && (int) $_SERVER['CONTENT_LENGTH'] > (1024*1024*(int) ini_get('post_max_size'))) {
	// File too large
        fhft_log(logLevel::ERROR, "File too big - max post size is ".(ini_get('post_max_size'))." MB");
	bad_request();
}

// If digest auth is enabled
if (defined("DIGEST_AUTH") && DIGEST_AUTH === true) {
	// Check the configuration
	if (!defined("AUTH_NONCE_KEY") || strlen(AUTH_NONCE_KEY) < 12) {
		fhft_log(LogLevel::ERROR, "Your file transfer server is badly configured, please set a random string in AUTH_NONCE_KEY at least 12 characters long");
		http_response_code(500);
		exit();
	}

	$headers = getallheaders();
        // From is the GRUU('sip(s):username@auth_realm;gr=*;) or just a sip:uri(sip(s):username@auth_realm), we need to extract the username from it:
        // from position of : until the first occurence of @
        // pass it through rawurldecode has GRUU may contain escaped characters
        $username_start_pos = strpos($headers['From'], ':') +1;
        $username_end_pos = strpos($headers['From'], '@');
        $username = rawurldecode(substr($headers['From'], $username_start_pos, $username_end_pos - $username_start_pos));
        $username_end_pos++; // point to the begining of the realm
        if (!defined("AUTH_REALM")) {
                if (strpos($headers['From'], ';') === FALSE ) { // From holds a sip:uri
                        $auth_realm = rawurldecode(substr($headers['From'], $username_end_pos));
                } else { // From holds a GRUU
                        $auth_realm = rawurldecode(substr($headers['From'], $username_end_pos, strpos($headers['From'], ';') - $username_end_pos ));
                }
        } else {
                $auth_realm = AUTH_REALM;
        }

	// Get authentication header if there is one
	if (!empty($headers['Auth-Digest'])) {
		fhft_log(LogLevel::DEBUG, "Auth-Digest = " . $headers['Auth-Digest']);
		$authorization = $headers['Auth-Digest'];
	} elseif (!empty($headers['Authorization'])) {
		fhft_log(LogLevel::DEBUG, "Authorization = " . $headers['Authorization']);
		$authorization = $headers['Authorization'];
	}

	// Authentication
	if (!empty($authorization)) {
		fhft_log(LogLevel::DEBUG, "There is a digest authentication header for " . $headers['From'] ." (username ".$username." requesting Auth on realm ".$auth_realm." )");
		$authenticated_username = authenticate($authorization, $auth_realm);

		if ($authenticated_username != '') {
			fhft_log(LogLevel::DEBUG, "Authentication successful for " . $headers['From'] ."with username ".$username);
			process_request();
		} else {
			fhft_log(LogLevel::DEBUG, "Authentication failed for " . $headers['From'] . " requesting authorization for user ".$username);
			request_authentication($auth_realm, $username);
		}
	} else {
		fhft_log(LogLevel::DEBUG, "There is no authentication digest header for " . $headers['From'] . " requesting authorization for user ".$username);
		request_authentication($auth_realm,$username);
	}
} else { // No digest auth
	if (count($_FILES) != 0) {
		process_request();
	}
	// Send back a 204 - see RCS doc section section 3.5.4.8
	if ((count($_POST) == 0) && (count($_FILES) == 0)) {
		http_response_code(204);
	}
}

?>
