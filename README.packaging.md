HTTP File Transfer server
=========================

Packaging
---------

To build a rpm package on centos7:

`make rpm`

To build a rpm package with docker:

`docker run -v $PWD:/home/bc -it gitlab.linphone.org:4567/bc/public/linphone-sdk/bc-dev-centos:7  make`

The flexisip-http-file-transfer-server rpm package can be found in rpmbuild/RPMS/x86_64/bc-flexisip-http-file-transfer-server*.rpm
Installation requires package centos-release-scl-rh to be installed for php7.1
