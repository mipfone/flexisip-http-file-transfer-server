HTTP File Transfer server
=========================

RPM Packaging
-------------

To build a rpm package on centos7:

`make rpm`

To build a rpm package with docker:

`docker run -v $PWD:/home/bc -it gitlab.linphone.org:4567/bc/public/linphone-sdk/bc-dev-centos:7  make`

The flexisip-http-file-transfer-server rpm package can be found in rpmbuild/RPMS/x86_64/bc-flexisip-http-file-transfer-server*.rpm
Installation requires package centos-release-scl-rh to be installed for php7.1 or above

Debian/Ubuntu Packaging
-----------------------

To build a dev package on debian10, with alien installed run as root:

`make deb`

To build a deb package with docker:

`docker run -v $PWD:/home/bc -it gitlab.linphone.org:4567/bc/public/flexisip/bc-dev-debian:10 make deb`
or to build it for ubuntu 18
`docker run -v $PWD:/home/bc -it gitlab.linphone.org:4567/bc/public/flexisip/bc-dev-ubuntu:18.04 make deb`


The flexisip-http-file-transfer-server deb package can be found in rpmbuild/DEBS/x86_64/bc-flexisip-http-file-transfer-server*.deb
