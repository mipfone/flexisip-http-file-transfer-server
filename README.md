HTTP File Transfer server
=========================

A PHP script managing file transfer according to Rich Communications Service recommendation : RCC.07 v6.0 section 3.5.4.8 File Transfer via HTTP.

Requirements
------------
The server requires a functional web server serving php script located (by default) in */opt/belledonne-communications/share/flexisip-http-file-transfer-server/hft.php*

The server requires writing access to a directory accessible directly through http, located by default in */var/opt/belledonne-communications/flexisip-http-file-transfer-tmp/* It is strongly advised to disable any script execution in this directory.

Logs are written in /var/opt/belledonne-communications/log/flexisip-http-file-transfer.log


Configuration
-------------

The configuration file is in */etc/flexisip-http-file-transfer-server/flexisip-http-file-transfer-server.conf*
If you modify there the path to the upload directory, you might have to also modify the apache configuration file(httpd/conf.d/flexisip-http-file-transfer-server.conf)

Maximum uploaded file size can be limited. The apache configuration file set the limit to 512 MB, this limit can be decreased in the file server configuration.

User Authentication
-------------------
Access to the file transfer server can be limited using user authentication.
* Authentication via TLS certificate can be achieve by modifying the apache server configuration and is out of the scope of this server configuration.
* Digest authentication can be enabled in the configuration file, see there for details. Digest auth can be enabled on upload only or on both upload and download. If do not want to enable the digest auth on upload, check the apache configuration file (httpd/conf.d/flexisip-http-file-transfer-server.conf) and enable the alias to allow direct download

Optional
--------

The package install a logrotate and a crontab entry to rotate the log and delete uploaded file at the end of their validity period (default 1 week)

Package
--------

To build a rpm package on centos7:

`make rpm`

To build a rpm package with docker:

`docker run -v $PWD:/home/bc -it gitlab.linphone.org:4567/bc/public/linphone-sdk/bc-dev-centos:7  make`

The flexisip-http-file-transfer-server rpm package can be found in rpmbuild/RPMS/x86_64/bc-flexisip-http-file-transfer-server*.rpm
Installation requires package centos-release-scl-rh to be installed for php7.1 or above
