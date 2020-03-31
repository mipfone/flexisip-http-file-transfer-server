HTTP File Transfer server
=========================

A PHP script managing file transfer according to Rich Communications Service recommendation : RCC.07 v6.0 section 3.5.4.8 File Transfer via HTTP.

Disclaimer
-----------

The current version of this server does not authenticate the user accessing it. Anyone can use this file-transfer server or download the uploaded files.

Requirements
------------
The server requires a functional web server serving php script located (by default) in */opt/belledonne-communications/share/flexisip-http-file-transfer-server/hft.php*

The server requires writing access to a directory accessible directly through http, located by default in */opt/belledonne-communications/share/flexisip-http-file-transfer-server/tmp/* It is strongly advised to disable any script execution in this directory.

Package
--------

To build a rpm package on centos7:

`make rpm`

To build a rpm package with docker:

`docker run -v $PWD:/home/bc -it gitlab.linphone.org:4567/bc/public/linphone-sdk/bc-dev-centos:7  make`

The flexisip-http-file-transfer-server rpm package can be found in rpmbuild/RPMS/x86_64/bc-flexisip-http-file-transfer-server*.rpm
Installation requires package centos-release-scl-rh to be installed for php7.1
