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
// make sure we do not display any error or it may mess the returned message
ini_set('display_errors', 'Off');

/*** simple logs ***/
// emulate simple enumeration
abstract class LogLevel {
	const DISABLED = 0;
	const ERROR = 1;
	const WARNING = 2;
	const MESSAGE = 3;
	const DEBUG = 4;
};

function stringErrorLevel($level) {
	switch($level) {
		case LogLevel::DISABLED: return "DISABLED";
		case LogLevel::ERROR: return "ERROR";
		case LogLevel::WARNING: return "WARNING";
		case LogLevel::MESSAGE: return "MESSAGE";
		case LogLevel::DEBUG: return "DEBUG";
		default: return "UNKNOWN";
	}
}

function fhft_log($level, $message) {
	if (fhft_logLevel>=$level) {
		if ($level === LogLevel::ERROR) { // in ERROR case, add a backtrace
			file_put_contents(fhft_logFile, date("c")." -".fhft_logDomain."- ".stringErrorLevel($level)." : $message\n".print_r(debug_backtrace(),true), FILE_APPEND);
		} else {
			file_put_contents(fhft_logFile, date("c")." -".fhft_logDomain."- ".stringErrorLevel($level)." : $message\n", FILE_APPEND);
		}
	}
}

// Include the configuration file AFTER the definition of logLevel
// get configuration file path, default is /etc/flexisip-http-file-transfer-server/flexisip-http-file-transfer-server.conf
// but give a chance to the webserver to configure it
$config_file= "/etc/flexisip-http-file-transfer-server/flexisip-http-file-transfer-server.conf";
if (isset($_SERVER["flexisip_http_file_transfer_config_path"])) {
	$config_file=$_SERVER["flexisip_http_file_transfer_config_path"];
}
include $config_file;

fhft_log(LogLevel::DEBUG, "Configuration file path: ".$config_file);

// avoid date() warnings
// time zone shall be set in php.ini or in flexisip-http-file-transfer configuration file if needed
date_default_timezone_set(@date_default_timezone_get());


/*** User authentication ***/
function auth_get_db_conn() {
	if (USE_PERSISTENT_CONNECTIONS) {
		$conn = mysqli_connect('p:' . AUTH_DB_HOST, AUTH_DB_USER, AUTH_DB_PASSWORD, AUTH_DB_NAME);
	} else {
		$conn = mysqli_connect(AUTH_DB_HOST, AUTH_DB_USER, AUTH_DB_PASSWORD, AUTH_DB_NAME);
	}
	if (!$conn) {
		fhft_log(LogLevel::ERROR, "Unable to connect to MySQL base ".AUTH_DB_NAME.".\nDebugging errno: " . mysqli_connect_errno() . "\nDebugging error: " . mysqli_connect_error() . "\n");
	}
	return $conn;
}

// Nonce are one-time usage, in order to avoid storing them in a table
// The nonce is built using:
// - timestamp : nonce is valid for MIN_NONCE_VALIDITY_PERIOD seconds at minimum and twice it at maximum (our goal is one time usage anyway, typical value shall be 10 )
// - secret key : avoid an attacker to be able to generate a valid nonce
function auth_get_valid_nonces() {
	$time = time();
	$time -= $time%MIN_NONCE_VALIDITY_PERIOD; // our nonce will be valid at leat MIN_NONCE_VALIDITY_PERIOD seconds and max twice it, so floor the timestamp
	return array(
		hash_hmac("sha256", $time, AUTH_NONCE_KEY),
		hash_hmac("sha256", $time-MIN_NONCE_VALIDITY_PERIOD, AUTH_NONCE_KEY));
}

function request_authentication($realm = "sip.example.org", $username=null) {
	$has_md5 = false;
	$has_sha256 = false;

	if ($username != null) {
		// Get the password/hash from database to include only available password hash in the authenticate header
		$db = auth_get_db_conn();
		$stmt = $db->prepare(AUTH_QUERY);
		if (!$stmt) {
			fhft_log (LogLevel::ERROR, "Unable to execute ".AUTH_QUERY.".\nDebugging errno: " . mysqli_connect_errno() . "\nDebugging error: " . mysqli_connect_error() . "\n");
			$has_md5 = true;
			$has_sha256 = true;
		} else {
			$stmt->bind_param('ss', $username, $realm);
			$stmt->execute();

			if ($query_result = $stmt->get_result()) {
				while ($row = $query_result->fetch_assoc()) {
					$algorithm = $row['algorithm'];
					if ($algorithm == 'CLRTXT') {
						fhft_log(LogLevel::DEBUG, "User  " . $username. " has clear text password in db \n");
						$has_md5 = true;
						$has_sha256 = true;
						break; // with clear text password, we can reconstruct MD5 or SHA256 hash, don't parse anything else from base
					} elseif ($algorithm == 'MD5' ) {
						fhft_log(LogLevel::DEBUG, "User  " . $username. " has md5 password in db \n");
						$has_md5 = true;
					} elseif ($algorithm == 'SHA-256') {
						fhft_log(LogLevel::DEBUG, "User  " . $username. " has sha256 password in db \n");
						$has_sha256 = true;
					} else {
						fhft_log(LogLevel::WARNING, "User  " . $username. " uses unrecognised hash algorithm ".$algorithm." to store password in db \n");
					}
				}
			}

			$stmt->close();
		}
		$db->close();
	} else { // we don't have the username authorize both MD5 and SHA256
		$has_md5 = true;
		$has_sha256 = true;
	}

	if (($has_md5 || $has_sha256) == false) {
		fhft_log(LogLevel::WARNING, "User  " . $username. " not found in db upon request_authentification\n");
		// reply anyway with both hash authorized
		$has_md5 = true;
		$has_sha256 = true;
	}

	header('HTTP/1.1 401 Unauthorized');
	if ($has_md5 == true) {
		header('WWW-Authenticate: Digest realm="' . $realm.
			'",qop="auth",algorithm=MD5,nonce="' . auth_get_valid_nonces()[0] . '",opaque="' . md5($realm) . '"');
	}

	if ($has_sha256 == true) {
		header('WWW-Authenticate: Digest realm="' . $realm.
			'",qop="auth",algorithm=SHA-256,nonce="' . auth_get_valid_nonces()[0] . '",opaque="' . md5($realm) . '"',false);
	}

	exit();
}

function authenticate($auth_digest, $realm = "sip.example.org") {
	// Parse the client authentication data
	preg_match_all('@(realm|username|nonce|uri|nc|cnonce|qop|response|opaque|algorithm)=[\'"]?([^\'",]+)@', $auth_digest, $a);
	$data = array_combine($a[1], $a[2]);
	$username = $data['username'];

	// Is the nonce valid?
	$valid_nonces = auth_get_valid_nonces();
	if (!hash_equals($valid_nonces[0], $data['nonce']) && !hash_equals($valid_nonces[1], $data['nonce'])) {
		fhft_log(LogLevel::DEBUG, "User  " . $username. " tried to log using invalid nonce");
		return;
	}

	// check that the authenticated URI and server URI match
	if($data['uri'] != $_SERVER['REQUEST_URI']) {
		fhft_log(LogLevel::DEBUG, "User  " . $username. " tried to log using unmatching URI in auth and server request");
		return;
	}

	// Check opaque is correct(even if we use a fixed value: md5($realm))
	if(!hash_equals(md5($realm), $data['opaque'])) {
		fhft_log(LogLevel::DEBUG, "User  " . $username. " tried to log using invalid auth opaque value");
		return;
	}

	// Get the password/hash from database
	$db = auth_get_db_conn();
	$stmt = $db->prepare(AUTH_QUERY);
	if (!$stmt) {
		fhft_log(LogLevel::ERROR, "Unable to execute ".AUTH_QUERY.".\nDebugging errno: " . mysqli_connect_errno() . "\nDebugging error: " . mysqli_connect_error() . "\n");
		return;
	}
	$stmt->bind_param('ss', $username, $realm);
	$stmt->execute();

	// Default requested to MD5 if not specified in header
	if (array_key_exists('algorithm', $data)) {
		$requested_algorithm = $data['algorithm'];
	} else {
		$requested_algorithm = 'MD5';
	}

	$password = null;
	if ($query_result = $stmt->get_result()) {
		while ($row = $query_result->fetch_assoc()) {
			$password = $row['password'];
			$algorithm = $row['algorithm'];
			if ($algorithm == 'CLRTXT') {
				fhft_log(LogLevel::DEBUG, "User  " . $username. " using clear text password from db \n");
				break; // with clear text password, we can reconstruct MD5 or SHA256 hash, don't parse anything else from base
			} elseif ($algorithm == $requested_algorithm ) {
				fhft_log(LogLevel::DEBUG, "User  " . $username. " using ". $row['algorithm'] ." password from db \n");
				break; // we found the requested hash in base, don't parse anything else
			} else { // algo used to store the password in base won't allow to reconstruct the one given in the header, keep parsing query result
				$password = null;
				$algorithm = null;
			}
		}
	}

	$stmt->close();
	$db->close();

	if (is_null($password)) {
		fhft_log (LogLevel::ERROR, "Unable to find password for User: " . $username . " in format " . $requested_algorithm );
		return;
	}


	//select right hash
	switch ($requested_algorithm) {
		case 'MD5':
			$hash_algo = 'md5';
			break;
		case 'SHA-256':
			$hash_algo = 'sha256';
			break;
		default:
			fhft_log(LogLevel::ERROR, "Unsupported algo " . $requested_algorithm . " for User:" . $username ."\n");
			break;
	}

	if ($algorithm == 'CLRTXT') {
		$A1 = hash($hash_algo, $username.':'.$data['realm'].':'.$password);
	} else {
		$A1 = $password;
	}

	$A2 = hash($hash_algo, getenv('REQUEST_METHOD').':'.$data['uri']);
	$valid_response = hash($hash_algo, $A1.':'.$data['nonce'].':'.$data['nc'].':'.$data['cnonce'].':'.$data['qop'].':'.$A2);

	// Compare with the client response
	if(hash_equals($valid_response, $data['response'])) {
		return $username;
	} else {
		return;
	}
}

function upload_error_message($error) {
	$message = 'Error uploading file';
	switch($error) {
	    case UPLOAD_ERR_OK:
		$message = false;;
		break;
	    case UPLOAD_ERR_INI_SIZE:
	    case UPLOAD_ERR_FORM_SIZE:
		$message .= ' - file too large (limit of '. ini_get('upload_max_filesize') .' bytes).';
		break;
	    case UPLOAD_ERR_PARTIAL:
		$message .= ' - file upload was not completed.';
		break;
	    case UPLOAD_ERR_NO_FILE:
		$message .= ' - zero-length file uploaded.';
		break;
	    default:
		$message .= ' - internal error #'. $error;
		break;
	}
	return $message;
}

function bad_request() {
	http_response_code(400);
	exit();
}

function check_server_settings() {
	// log Level, if not set or incorrect, disable log
	switch(fhft_logLevel) {
		// Authorized values
		case LogLevel::DISABLED :
		case LogLevel::ERROR :
		case LogLevel::WARNING :
		case LogLevel::MESSAGE :
		case LogLevel::DEBUG :
			break;
		// any other default to DISABLED
		default:
			error_log("fhft log level setting is invalid ".fhft_logLevel.". Disable flexisip http file transfer logs");
			define ("fhft_logLevel", LogLevel::DISABLED);
	}
}

// First check if the user connected with a client certificate
//  - yes: auth Ok, return true
//  - no: is digest auth enable?
//  	- yes: perform digest auth
//  	- no: no auth requested, return true
function check_user_authentication() {
	if ($_SERVER['SSL_CLIENT_VERIFY'] == 'SUCCESS') {
		fhft_log(LogLevel::DEBUG, "Authentication successful using TLS client certificate from ".$_SERVER['HTTP_FROM']);
		return true;
	}

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
				return true;
			} else {
				fhft_log(LogLevel::DEBUG, "Authentication failed for " . $headers['From'] . " requesting authorization for user ".$username);
				request_authentication($auth_realm, $username);
			}
		} else {
			fhft_log(LogLevel::DEBUG, "There is no authentication digest header for " . $headers['From'] . " requesting authorization for user ".$username);
			request_authentication($auth_realm,$username);
		}
	} else {
		// no auth requested
		return true;
	}
}
?>
