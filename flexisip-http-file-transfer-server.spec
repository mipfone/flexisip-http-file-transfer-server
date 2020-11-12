# -*- rpm-spec -*-

#%define _prefix    @CMAKE_INSTALL_PREFIX@
#%define pkg_prefix @BC_PACKAGE_NAME_PREFIX@

# re-define some directories for older RPMBuild versions which don't. This messes up the doc/ dir
# taken from https://fedoraproject.org/wiki/Packaging:RPMMacros?rd=Packaging/RPMMacros
#%define _datarootdir       %{_prefix}/share
#%define _datadir           %{_datarootdir}
#%define _docdir            %{_datadir}/doc

%define build_number 4
#%if %{build_number}
#%define build_number_ext -%{build_number}
#%endif


Name:           bc-flexisip-http-file-transfer-server
Version:        1.0
Release:        %{build_number}%{?dist}
Summary:        Flexisip HTTP File Transfer server.

Group:          Applications/Communications
License:        GPL
URL:            http://www.linphone.org
#Source0:        %{name}-%{version}%{?build_number_ext}.tar.gz
Source0:	flexisip-http-file-transfer-server.tar.gz
#BuildRoot:      %{_tmppath}/%{name}-%{version}-%{release}-buildroot
#BuildRoot:	/home/jenkins/rpmbuild/%{name}-%{version}-%{release}

# dependencies
# this server need php to run there is no generic name and we do not need to enforce the use of apache, so no dependencies

%description
A PHP script managing file transfer according to Rich Communications Service recommendation : RCC.07 v6.0 section 3.5.4.8 File Transfer via HTTP.

# This is for debian builds where debug_package has to be manually specified, whereas in centos it does not
#%define custom_debug_package %{!?_enable_debug_packages:%debug_package}%{?_enable_debug_package:%{nil}}
#%custom_debug_package

%prep
#%setup -q
#%setup -n %{name}-%{version}%{?build_number}
%setup -n flexisip-http-file-transfer-server

%install
rm -rf "$RPM_BUILD_ROOT"
mkdir -p "$RPM_BUILD_ROOT/var/opt/belledonne-communications/flexisip-http-file-transfer-tmp"
mkdir -p "$RPM_BUILD_ROOT/opt/belledonne-communications/share/flexisip-http-file-transfer-server"
cp -R *.php "$RPM_BUILD_ROOT/opt/belledonne-communications/share/flexisip-http-file-transfer-server"
cp -R README* "$RPM_BUILD_ROOT/opt/belledonne-communications/share/flexisip-http-file-transfer-server"
cp -R LICENSE.txt "$RPM_BUILD_ROOT/opt/belledonne-communications/share/flexisip-http-file-transfer-server"
mkdir -p "$RPM_BUILD_ROOT/etc/flexisip-http-file-transfer-server"
cp -R flexisip-http-file-transfer-server.conf "$RPM_BUILD_ROOT/etc/flexisip-http-file-transfer-server"
mkdir -p $RPM_BUILD_ROOT/opt/rh/httpd24/root/etc/httpd/conf.d
cp httpd/flexisip-http-file-transfer-server.conf "$RPM_BUILD_ROOT/opt/rh/httpd24/root/etc/httpd/conf.d"
mkdir -p $RPM_BUILD_ROOT/etc/logrotate.d
cp logrotate/flexisip-http-file-transfer-server.conf "$RPM_BUILD_ROOT/etc/logrotate.d"
mkdir -p $RPM_BUILD_ROOT/etc/cron.d
cp cron.d/flexisip-http-file-transfer-server "$RPM_BUILD_ROOT/etc/cron.d"

%post
if [ $1 -eq 1 ] ; then
mkdir -p /var/opt/belledonne-communications/log
touch /var/opt/belledonne-communications/log/flexisip-http-file-transfer-server.log
chown apache:apache /var/opt/belledonne-communications/log/flexisip-http-file-transfer-server.log
chcon -t httpd_sys_rw_content_t /var/opt/belledonne-communications/log/flexisip-http-file-transfer-server.log
# it seems crontab daemon parses only fresh files, to be sure, touch this one when the install is done
touch /etc/cron.d/flexisip-http-file-transfer-server
fi

%files
/opt/belledonne-communications/share/flexisip-http-file-transfer-server/*.php
/opt/belledonne-communications/share/flexisip-http-file-transfer-server/README*
/opt/belledonne-communications/share/flexisip-http-file-transfer-server/LICENSE.txt
%dir
%attr(744,apache,apache) /var/opt/belledonne-communications/flexisip-http-file-transfer-tmp

%config(noreplace) /etc/flexisip-http-file-transfer-server/flexisip-http-file-transfer-server.conf
%config(noreplace) /opt/rh/httpd24/root/etc/httpd/conf.d/flexisip-http-file-transfer-server.conf
%config(noreplace) /etc/logrotate.d/flexisip-http-file-transfer-server.conf
%config(noreplace) /etc/cron.d/flexisip-http-file-transfer-server

%clean
rm -rf $RPM_BUILD_ROOT

%changelog
* Thu Nov 12 2020 Johan Pascal <johan.pascal@belledonne-communications.com>
- Add digest authentification
* Tue Apr 7 2020 Johan Pascal <johan.pascal@belledonne-communications.com>
- Add crontab configuration file
* Wed Apr 1 2020 Johan Pascal <johan.pascal@belledonne-communications.com>
- Add configuration file
* Mon Feb 24 2020 Johan Pascal <johan.pascal@belledonne-communications.com>
- Initial RPM release.
