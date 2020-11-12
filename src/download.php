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
	$file = fhft_tmp_path.basename($_SERVER["REQUEST_URI"]);
        if (file_exists($file)) {
                fhft_log(logLevel::DEBUG,"Download script serve file ".$file);
                header('Content-Description: File Transfer');
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="'.basename($file).'"');
                header('Content-Length: ' . filesize($file));
                readfile($file);
        } else {
                fhft_log(logLevel::DEBUG,"Download script requested to serve ".$file." but it cannot be found");
                http_response_code(404);
        }
        exit();
}

// first check server settings
check_server_settings();

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
	process_request();
}

?>
