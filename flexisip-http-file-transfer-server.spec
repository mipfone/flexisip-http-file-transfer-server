%define build_number 8

%if "%{?dist}" == ".deb"
%{echo:Packaging for Debian, apache user is www-data}
%define apache_user www-data
%define apache_conf_path /etc/apache2/conf-available
%else
%{echo:Packaging for %{?dist}, expect apache user to be apache}
%define apache_user apache
%define apache_conf_path /opt/rh/httpd24/root/etc/httpd/conf.d
%endif

Name:           bc-flexisip-http-file-transfer-server
Version:        1.0
Release:        %{build_number}%{?dist}
Summary:        Flexisip HTTP File Transfer server.

Group:          Applications/Communications
License:        GPL
URL:            http://www.linphone.org
Source0:	flexisip-http-file-transfer-server.tar.gz

# dependencies
# this server need php to run there is no generic name and we do not need to enforce the use of apache, so no dependencies

%description
A PHP script managing file transfer according to Rich Communications Service recommendation : RCC.07 v6.0 section 3.5.4.8 File Transfer via HTTP.

%prep
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
mkdir -p $RPM_BUILD_ROOT%{apache_conf_path}
cp httpd/flexisip-http-file-transfer-server.conf "$RPM_BUILD_ROOT%{apache_conf_path}"
mkdir -p $RPM_BUILD_ROOT/etc/logrotate.d
cp logrotate/flexisip-http-file-transfer-server.conf "$RPM_BUILD_ROOT/etc/logrotate.d"
mkdir -p $RPM_BUILD_ROOT/etc/cron.d
cp cron.d/flexisip-http-file-transfer-server "$RPM_BUILD_ROOT/etc/cron.d"
sed -i 's/apache/%{apache_user}/' "$RPM_BUILD_ROOT/etc/cron.d/flexisip-http-file-transfer-server"

#log files, must be declared as ghost in the file section so they are removed when the package is uninstalled
mkdir -p $RPM_BUILD_ROOT/var/opt/belledonne-communications/log
touch $RPM_BUILD_ROOT/var/opt/belledonne-communications/log/flexisip-http-file-transfer-server.log

%post
touch /var/opt/belledonne-communications/log/flexisip-http-file-transfer-server.log
chown %{apache_user}:%{apache_user} /var/opt/belledonne-communications/log/flexisip-http-file-transfer-server.log
# it seems crontab daemon parses only fresh files, to be sure, touch this one when the install is done
touch /etc/cron.d/flexisip-http-file-transfer-server
# if selinux is installed on the system (even if not enabled)
which setsebool
if [ $? -eq 0 ] ; then
setsebool -P httpd_can_network_connect_db on
chcon -t httpd_sys_rw_content_t /var/opt/belledonne-communications/log/flexisip-http-file-transfer-server.log
fi

%files
/opt/belledonne-communications/share/flexisip-http-file-transfer-server/*.php
/opt/belledonne-communications/share/flexisip-http-file-transfer-server/README*
/opt/belledonne-communications/share/flexisip-http-file-transfer-server/LICENSE.txt
%dir %attr(744,%{apache_user},%{apache_user}) /var/opt/belledonne-communications/flexisip-http-file-transfer-tmp
%dir /var/opt/belledonne-communications/log
%ghost %attr(644, %{apache_user}, %{apache_user}) /var/opt/belledonne-communications/log/flexisip-http-file-transfer-server.log

%config(noreplace) /etc/flexisip-http-file-transfer-server/flexisip-http-file-transfer-server.conf
%config(noreplace) %{apache_conf_path}/flexisip-http-file-transfer-server.conf
%config(noreplace) /etc/logrotate.d/flexisip-http-file-transfer-server.conf
%config(noreplace) /etc/cron.d/flexisip-http-file-transfer-server

%clean
rm -rf $RPM_BUILD_ROOT

%changelog
* Mon Apr 19 2021 Johan Pascal <johan.pascal@belledonne-communications.com>
- 1.0-8 Debian/Ubuntu packaging
* Mon Mar 22 2021 Johan Pascal <johan.pascal@belledonne-communications.com>
- 1.0-7 Add proxy for strict multidomains configuration
* Tue Mar 02 2021 Johan Pascal <johan.pascal@belledonne-communications.com>
- 1.0-6 File size limit can be configured in the configuration file
* Mon Nov 16 2020 Johan Pascal <johan.pascal@belledonne-communications.com>
- 1.0-5 Fix default apache configuration file for legacy alias
* Thu Nov 12 2020 Johan Pascal <johan.pascal@belledonne-communications.com>
- 1.0-4 Add digest authentification
* Tue Apr 7 2020 Johan Pascal <johan.pascal@belledonne-communications.com>
- Add crontab configuration file
* Wed Apr 1 2020 Johan Pascal <johan.pascal@belledonne-communications.com>
- Add configuration file
* Mon Feb 24 2020 Johan Pascal <johan.pascal@belledonne-communications.com>
- Initial RPM release.
