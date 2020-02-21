<?php
/*
    HTTP File Transfer Server
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

function custom_log($message) {
	$now = getdate();
	$month = sprintf("%02d", $now["mon"]);
	$day = sprintf("%02d", $now["mday"]);
	$hours = sprintf("%02d", $now["hours"]);
	$minutes = sprintf("%02d", $now["minutes"]);
	$seconds = sprintf("%02d", $now["seconds"]);

	error_log("[" . $day . "/" .  $month . "/" . $now["year"] . " " . $hours . ":" . $minutes . ":" . $seconds . "] " . $message . "\r\n", 3, "/var/log/file_sharing/trace_file_sharing.log");
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

function file_too_large() {
	if (!function_exists('http_response_code')) {
                $protocol = (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0');
                header($protocol . ' 400 Bad Request');
                $GLOBALS['http_response_code'] = 400;
        } else {
                http_response_code(400);
        }
}

date_default_timezone_set("UTC");

// make sure we do not display any error or it may mess the returned message
ini_set('display_errors', 'Off');


if (count($_FILES) != 0) {
	$uploaddir = dirname(__FILE__).'/tmp/';
	$rcvname=$_FILES['File']['name'];
	$ext= strtolower(pathinfo($rcvname, PATHINFO_EXTENSION));
	//$allowed_ext = array("jpg", "txt", "zip", "zlib", "gz");
	//if (!in_array($ext, $allowed_ext)) $ext="jpg";
	$tmpfile=$_FILES['File']['tmp_name'];
	if (strlen($tmpfile) <= 0) {
		custom_log(upload_error_message($_FILES['File']['error']));
	}

	if ($_FILES['File']['error'] === UPLOAD_ERR_INI_SIZE) {
		//File too large
                custom_log(upload_error_message($_FILES['File']['error']));
		file_too_large();
	} else {
		custom_log('Uploaded '.$rcvname.' to '.$tmpfile);
		//$uploadfile = $uploaddir.time().md5_file($tmpfile).".".$ext;
		$uploadfile = $uploaddir.uniqid()."_".bin2hex(openssl_random_pseudo_bytes(10)).".$ext";

		if (move_uploaded_file($tmpfile, $uploadfile)) {
			custom_log('Moved to '.$uploadfile);
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
			$http_url = $start."/tmp/".basename($uploadfile);

			// validity time is one week ahead from now
			$until = date("Y-m-d\TH:i:s\Z",time()+7*24*60*60);
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
	file_too_large();
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
