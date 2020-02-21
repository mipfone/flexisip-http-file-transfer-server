# -*- rpm-spec -*-

#%define _prefix    @CMAKE_INSTALL_PREFIX@
#%define pkg_prefix @BC_PACKAGE_NAME_PREFIX@

# re-define some directories for older RPMBuild versions which don't. This messes up the doc/ dir
# taken from https://fedoraproject.org/wiki/Packaging:RPMMacros?rd=Packaging/RPMMacros
#%define _datarootdir       %{_prefix}/share
#%define _datadir           %{_datarootdir}
#%define _docdir            %{_datadir}/doc

%define build_number 1
#%if %{build_number}
#%define build_number_ext -%{build_number}
#%endif


Name:           bc-http-file-transfer-server
Version:        1.0
Release:        %{build_number}%{?dist}
Summary:        HTTP File Transfer server.

Group:          Applications/Communications
License:        GPL
URL:            http://www.linphone.org
#Source0:        %{name}-%{version}%{?build_number_ext}.tar.gz
Source0:	http-file-transfer-server.tar.gz
#BuildRoot:      %{_tmppath}/%{name}-%{version}-%{release}-buildroot
#BuildRoot:	/home/jenkins/rpmbuild/%{name}-%{version}-%{release}

# dependencies
Requires:	rh-php71-php rh-php71-php-mysqlnd

%description
A PHP script managing file transfer according to Rich Communications Service recommendation : RCC.07 v6.0 section 3.5.4.8 File Transfer via HTTP.

# This is for debian builds where debug_package has to be manually specified, whereas in centos it does not
#%define custom_debug_package %{!?_enable_debug_packages:%debug_package}%{?_enable_debug_package:%{nil}}
#%custom_debug_package

%prep
#%setup -q
#%setup -n %{name}-%{version}%{?build_number}
%setup -n http-file-transfer-server

%install
rm -rf "$RPM_BUILD_ROOT"
mkdir -p "$RPM_BUILD_ROOT/opt/belledonne-communications/share/http-file-transfer-server"
mkdir -p "$RPM_BUILD_ROOT/opt/belledonne-communications/share/http-file-transfer-server/tmp"
cp -R *.php "$RPM_BUILD_ROOT/opt/belledonne-communications/share/http-file-transfer-server"
cp -R README* "$RPM_BUILD_ROOT/opt/belledonne-communications/share/http-file-transfer-server"
mkdir -p $RPM_BUILD_ROOT/opt/rh/httpd24/root/etc/httpd/conf.d
cp  httpd/http-file-transfer-server.conf "$RPM_BUILD_ROOT/opt/rh/httpd24/root/etc/httpd/conf.d"

%post
if [ $1 -eq 1 ] ; then
mkdir -p /var/opt/belledonne-communications/log
touch /var/opt/belledonne-communications/log/http-file-transfer-server.log
chown apache:apache /var/opt/belledonne-communications/log/http-file-transfer-server.log
chcon -t httpd_sys_rw_content_t /var/opt/belledonne-communications/log/http-file-transfer-server.log
chown apache:apache /opt/belledonne-communications/share/http-file-transfer-server/tmp
chcon -t httpd_sys_rw_content_t /opt/belledonne-communications/share/http-file-transfer-server/tmp
fi

%files
/opt/belledonne-communications/share/http-file-transfer-server/*.php
/opt/belledonne-communications/share/http-file-transfer-server/README*
%dir
/opt/belledonne-communications/share/http-file-transfer-server/tmp/

%config(noreplace) /opt/rh/httpd24/root/etc/httpd/conf.d/http-file-transfer-server.conf

%clean
rm -rf $RPM_BUILD_ROOT

%changelog
* Wed Feb 16 2020 Johan Pascal <johan.pascal@belledonne-communications.com>
- Initial RPM release.
