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


/*** simple logs ***/
// emulate simple enumeration
abstract class LogLevel {
	const DISABLED = 0;
	const ERROR = 1;
	const WARNING = 2;
	const MESSAGE = 3;
	const DEBUG = 4;
};

// Include the configuration file AFTER the definition of logLevel
include "/etc/flexisip-http-file-transfer-server/flexisip-http-file-transfer-server.conf";

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

// avoid date() warnings
// time zone shall be set in php.ini or in flexisip-http-file-transfer configuration file if needed
date_default_timezone_set(@date_default_timezone_get());

// make sure we do not display any error or it may mess the returned message
ini_set('display_errors', 'Off');


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
	if (!function_exists('http_response_code')) {
                $protocol = (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0');
                header($protocol . ' 400 Bad Request');
                $GLOBALS['http_response_code'] = 400;
        } else {
                http_response_code(400);
        }
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

// first check server settings
check_server_settings();

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

	if ($_FILES['File']['error'] === UPLOAD_ERR_INI_SIZE) {
		//File too large
                fhft_log(logLevel::ERROR, upload_error_message($_FILES['File']['error']));
		bad_request();
	} else {
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
			echo '<?xml version="1.0" encoding="UTF-8"?><file xmlns="urn:gsma:params:xml:ns:rcs:rcs:fthttp">
<file-info type="file">
<file-size>'.$_FILES['File']['size'].'</file-size>
<file-name>'.$_FILES['File']['name'].'</file-name>
<content-type>'.$_FILES['File']['type'].'</content-type>
<data url = "'.$http_url.'" until = "'.$until.'"/>
</file-info>
</file>';
exit();
		}
	}
}

if (isset($_SERVER['CONTENT_LENGTH']) && (int) $_SERVER['CONTENT_LENGTH'] > (1024*1024*(int) ini_get('post_max_size'))) {
	// File too large
	bad_request();
} else if ((count($_POST) == 0) && (count($_FILES) == 0)) {
	if (!function_exists('http_response_code')) {
		$protocol = (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0');
                header($protocol . ' 204 No Content');
		$GLOBALS['http_response_code'] = 204;
	} else {
		http_response_code(204);
	}
}
?>
