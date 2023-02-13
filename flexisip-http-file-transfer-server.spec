%define build_number MAKE_FILE_BUILD_NUMBER_SEARCH
%define var_dir /var/opt/belledonne-communications
%define opt_dir /opt/belledonne-communications/share/flexisip-http-file-transfer-server

%if "%{?dist}" == ".deb"
    %define web_user www-data
    %define apache_conf_path /etc/apache2/conf-available
%else
    %define web_user apache
    %if "%{?dist}" == ".el7"
        %define apache_conf_path /opt/rh/httpd24/root/etc/httpd/conf.d
    %else
        %define apache_conf_path /etc/httpd/conf.d
    %endif
%endif

Name:           bc-flexisip-http-file-transfer-server
Version:        MAKE_FILE_VERSION_SEARCH
Release:        %{build_number}%{?dist}
Summary:        Flexisip HTTP File Transfer server.

Group:          Applications/Communications
License:        GPL
URL:            http://www.linphone.org
Source0:        flexisip-http-file-transfer-server.tar.gz

Requires:       php, php-mysqlnd, php-xml

%description
A PHP script managing file transfer according to Rich Communications Service recommendation : RCC.07 v6.0 section 3.5.4.8 File Transfer via HTTP.

%prep
%setup -n flexisip-http-file-transfer-server

%install
rm -rf "$RPM_BUILD_ROOT"
mkdir -p "$RPM_BUILD_ROOT%{var_dir}/flexisip-http-file-transfer-tmp"

mkdir -p "$RPM_BUILD_ROOT%{opt_dir}"
cp -R *.php "$RPM_BUILD_ROOT%{opt_dir}/"
cp -R README* "$RPM_BUILD_ROOT%{opt_dir}/"
cp -R LICENSE.txt "$RPM_BUILD_ROOT%{opt_dir}/"

mkdir -p "$RPM_BUILD_ROOT/etc/flexisip-http-file-transfer-server"
cp -R flexisip-http-file-transfer-server.conf "$RPM_BUILD_ROOT/etc/flexisip-http-file-transfer-server"

mkdir -p $RPM_BUILD_ROOT%{apache_conf_path}
cp httpd/flexisip-http-file-transfer-server.conf "$RPM_BUILD_ROOT%{apache_conf_path}"

mkdir -p $RPM_BUILD_ROOT/etc/logrotate.d
cp logrotate/flexisip-http-file-transfer-server.conf "$RPM_BUILD_ROOT/etc/logrotate.d"

mkdir -p $RPM_BUILD_ROOT/etc/cron.d
cp cron.d/flexisip-http-file-transfer-server "$RPM_BUILD_ROOT/etc/cron.d"
sed -i 's/apache/%{web_user}/' "$RPM_BUILD_ROOT/etc/cron.d/flexisip-http-file-transfer-server"

#log files, must be declared as ghost in the file section so they are removed when the package is uninstalled
mkdir -p $RPM_BUILD_ROOT%{var_dir}/log
touch $RPM_BUILD_ROOT%{var_dir}/log/flexisip-http-file-transfer-server.log

%post
touch %{var_dir}/log/flexisip-http-file-transfer-server.log
chown %{web_user}:%{web_user} %{var_dir}/log/flexisip-http-file-transfer-server.log

# it seems crontab daemon parses only fresh files, to be sure, touch this one when the install is done
touch /etc/cron.d/flexisip-http-file-transfer-server

# if selinux is installed on the system (even if not enabled)
which setsebool
if [ $? -eq 0 ] ; then
setsebool -P httpd_can_network_connect_db on
chcon -t httpd_sys_rw_content_t %{var_dir}/log/flexisip-http-file-transfer-server.log
fi

%files
%{opt_dir}/*.php
%{opt_dir}/README*
%{opt_dir}/LICENSE.txt
%dir %attr(744,%{web_user},%{web_user}) %{var_dir}/flexisip-http-file-transfer-tmp
%dir %{var_dir}/log
%ghost %attr(644, %{web_user}, %{web_user}) %{var_dir}/log/flexisip-http-file-transfer-server.log

%config(noreplace) /etc/flexisip-http-file-transfer-server/flexisip-http-file-transfer-server.conf
%config(noreplace) %{apache_conf_path}/flexisip-http-file-transfer-server.conf
%config(noreplace) /etc/logrotate.d/flexisip-http-file-transfer-server.conf
%config(noreplace) /etc/cron.d/flexisip-http-file-transfer-server

%clean
rm -rf $RPM_BUILD_ROOT
