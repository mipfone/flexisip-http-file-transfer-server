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

// This function is available from PHP8
if (!function_exists('str_ends_with')) {
	function str_ends_with(string $haystack, string $needle)
	{
		$rpos = strrpos($haystack, $needle);
		if ($rpos === false) {
			return false;
		}
		return ((strlen($haystack) - strlen($needle)) == $rpos);
	}
}

function process_request()
{
	// Are we a file proxy? : configured for(PROXY_SELF_DOMAIN defined) and the url requested passed in target arg?
	if (defined("PROXY_SELF_DOMAIN") && (strlen("PROXY_SELF_DOMAIN") > 0) && array_key_exists("target", $_GET)) {
		$target_url = $_GET["target"];
		// Check the requested url
		$url = parse_url($target_url);
		if ($url === false || !array_key_exists("host", $url) || (strlen($url["host"]) == 0)) { // malformed URL
			fhft_log(logLevel::ERROR, "Proxy request invalid URL " . $target_url);
			http_response_code(404);
			exit();
		}
		$target_host = $url["host"];

		// Is the file requested on our domain?
		if (str_ends_with($target_host, PROXY_SELF_DOMAIN) !== false) {
			fhft_log(logLevel::DEBUG, "Download script acting as proxy on domain " . PROXY_SELF_DOMAIN . " file " . $target_url . " served locally");
			$file = fhft_tmp_path . basename($target_url);
		} else {
			fhft_log(logLevel::DEBUG, "Download script acting as proxy on domain " . PROXY_SELF_DOMAIN . " requests file " . $target_url . " Request server " . $target_host . " for the file");
			// Do we have this foreign domain
			unset($local_cert_path);
			if (defined("FOREIGN_DOMAINS") && is_array(FOREIGN_DOMAINS)) {
				foreach (FOREIGN_DOMAINS as $domain => $client_cert) {
					if (str_ends_with($target_host, $domain) === true) { // We have a client cert for this external domain
						$local_cert_path = $client_cert;
					}
				}
			}
			if (!isset($local_cert_path)) {
				fhft_log(logLevel::ERROR, "Download script acting as proxy on domain " . PROXY_SELF_DOMAIN . " queries server " . $target_host . " for a file. But we do not have credentials to log on this server");
				http_response_code(404);
				exit();
			}
			// Create the context to connect the other server
			$cafile = (defined("FOREIGN_DOMAINS_CAFILE"))
				? FOREIGN_DOMAINS_CAFILE
				: '';

			$options = [
				'http' => [
					'header'  => "From:" . PROXY_SELF_DOMAIN . "\r\n",
					'method'  => 'GET'
				],
				'ssl' => [
					'cafile' => $cafile,
					'local_cert' => $local_cert_path
				]
			];
			$context  = stream_context_create($options);

			// open the file on the remote server
			$fp = fopen($target_url, 'rb', false, $context);
			if ($fp === false) {
				fhft_log(logLevel::ERROR, "Download script acting as proxy on domain " . PROXY_SELF_DOMAIN . " queries server " . $target_host . " for a file. Fail to open the requested URL : " . $target_url);
				http_response_code(404);
				exit();
			}

			// Get headers from the response to fopen
			if (isset($http_response_header) && is_array($http_response_header)) {
				foreach ($http_response_header as $header) {
					// forward only Content- related header
					if (stripos($header, "Content") === 0) {
						header($header);
					}
				}
			}

			// and the file content
			fpassthru($fp);
			fclose($fp);
			exit();
		}
	} else { // URL was the direct request, we are not a proxy, just serve it if we have it
		$file = fhft_tmp_path . basename($_SERVER["REQUEST_URI"]);
	}

	if (file_exists($file)) {
		fhft_log(logLevel::DEBUG, "Download script serve file " . $file);
		header('Content-Description: File Transfer');
		header('Content-Type: application/octet-stream');
		header('Content-Disposition: attachment; filename="' . basename($file) . '"');
		header('Content-Length: ' . filesize($file));
		readfile($file);
	} else {
		fhft_log(logLevel::ERROR, "Download script requested to serve " . $file . " but it cannot be found");
		http_response_code(404);
	}
	exit();
}

// first check server settings
check_server_settings();

// Check user authentication and process the request
if (check_user_authentication() === true) {
	process_request();
}
