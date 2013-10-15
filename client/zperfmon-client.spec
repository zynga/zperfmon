# $Id: zperfmon-client.spec 6458 2010-08-30 20:00:02Z prashun $
# Authority: Shankara, Prashun
%define php_apiver  %((echo 0; php -i 2>/dev/null | sed -n 's/^PHP API => //p') | tail -1)
%define php_zendabiver %((echo 0; php -i 2>/dev/null | sed -n 's/^PHP Extension => //p') | tail -1)
%define php_version  %((echo 0; php -i 2>/dev/null | sed -n 's/^PHP Version => //p') | tail -1 | tr '-' '_')
%define php_max_version %((echo 0; php -r "echo implode('.',array_slice(explode('.',phpversion()),0,2)).'.999';") | tail -1)

%define php_extdir %(php-config --extension-dir 2>/dev/null || echo %{_libdir}/php4)
%define _unpackaged_files_terminate_build 0 

Summary: ZPerfmon client package for profiling and collecting system parameters
Name: zperfmon-client
Version: 1.1.3.3
Release: %{php_version}
License: BSD
Group: Development/Languages
URL: http://www.zynga.com/
Packager: Prashun Purkayastha <ppurkayastha@zynga.com>,Shankara K Seethappa <shankara@zynga.com>
Vendor: Zynga Repository, https://svn.zynga.com/svn/zperfmon/trunk/client/ 

Source: /zperfmon-client.tgz
BuildRoot: %{_tmppath}/%{name}-%{version}-%{release}-root
Requires: php >= %{php_version}, php < %{php_max_version}
%if "%{rhel}" == "5"
Requires: php-zend-abi = %{php_zendabiver}
%endif
BuildRequires: php, php-devel, zlib-devel
# Required by phpize
BuildRequires: autoconf, automake, libtool

%description
ZPERFMON client is combination of xhprof for profiling php as well as cron jobs to collect various sys params which will be uploaded to a monitoring server periodically.

%prep
%setup -q -c -n zperfmon-client
# Docs are +x (as of 2.0.0), so fix here
%{__chmod} -x CREDITS README


%build
# Workaround for broken old phpize on 64 bits
cd zxhprof/extension
%{__cat} %{_bindir}/phpize | sed 's|/lib/|/%{_lib}/|g' > phpize && sh phpize
%configure
%{__make} %{?_smp_mflags}

%post
SPLIT=$(($RANDOM % 5 * 5));
MINUTE=$(($RANDOM % 30));
cat <<HERE > /etc/cron.d/zperfmon-client
${SPLIT},25,$((30+SPLIT)),55 * * * * root /usr/local/zperfmon/bin/perfaggregate.py  &> /dev/null
59 0 * * * root /usr/bin/find /var/opt/zperfmon/xhprof_tbz/ -mtime +0 -print0 | xargs --null rm -f &> /dev/null
59 12 * * * root /usr/bin/find /var/log/zperfmon/ -mtime +0 -exec rm -f {} \; &> /dev/null
1,31 * * * * root /usr/local/zperfmon/bin/movenbzip.sh &> /dev/null
*/4 * * * * root /usr/bin/pgrep count_page_load; if [ "\$?_" == "1_" ]; then /usr/local/zperfmon/bin/count_page_load_time.py; fi &> /dev/null
${MINUTE} 2 * * * root /usr/local/zperfmon/bin/pullconfig.py &> /dev/null
HERE
chown root /etc/cron.d/zperfmon-client
chmod 0644 /etc/cron.d/zperfmon-client
pkill -9 count_page_load; true;
rm /var/run/apache_mon_pid; true;
/usr/local/zperfmon/bin/pullconfig.py &> /dev/null; true

%install
%{__rm} -rf %{buildroot}
%{__mkdir_p} %{buildroot}
%{__make} install INSTALL_ROOT=%{buildroot}

%clean
%{__rm} -rf %{buildroot}


%files
%defattr(-,root,root,-)
#%doc CREDITS README
%doc README
%config(noreplace) %{_sysconfdir}/php.d/xhprof.ini
%{php_extdir}/xhprof.so

%attr(0777, -, -) /mnt/logs/httpd/xhprof
%attr(0777, -, -) /var/run/zperfmon

/var/log/zperfmon/
/var/opt/zperfmon/
/var/opt/zperfmon/xhprof_tbz
/usr/local/zperfmon/bin/
/usr/local/zperfmon/etc/zperfmon.htpasswd
/usr/share/php/zperfmon.inc.php
/etc/httpd/conf.d/zperfmon-client.conf
/etc/zperfmon/zperfmon.ini

#%ghost /usr/local/zperfmon/bin/perfaggregate.pyc
#%ghost /usr/local/zperfmon/bin/awk.pyc
#%ghost /usr/local/zperfmon/bin/count_page_load_time.pyc
#%ghost /usr/local/zperfmon/bin/perfaggregate.pyo
#%ghost /usr/local/zperfmon/bin/awk.pyo
#%ghost /usr/local/zperfmon/bin/count_page_load_time.pyo
#%ghost /usr/local/zperfmon/bin/timetail.pyc
#%ghost /usr/local/zperfmon/bin/timetail.pyo
%changelog
* Sun Apr 21 2013 kopaul@zynga.com
- Added capability to client to pull configuration from server(made change to delete ini before pull)

* Mon Jul 16 2012 bphilip@zynga.com
- Added capability to client to pull configuration from server

* Fri May 18 2012 mgattani@zynga.com
- Adding ZPERFMON_HASH_DATA constant, if defined to false, we wont collect hash data.

* Mon Feb 13 2012 bphilip@zynga.com
- Don't call apache_note in the cli case

* Mon Jan 16 2012 bphilip@zynga.com
- Expanded zid, page, mem and auth-hash stats collection
- Key slow page profiles with zid if available

* Thu Dec 8 2011 bphilip@zynga.com
- Enabled collection of zid-hit count and zid-time on server

* Mon Dec 5 2011 gkumar@zynga.com
- Increased version to 13.0 after two major updates by mgattani@zynga.com and uprakash@zynga.com
- Added ZPERFMON_PAGE_PARAMS to configuration and introduced page params to  be appended to profile name
- Introduced Customizable memory profiling, time slack.

* Thu Dec 1 2011 uprakash@zynga.com
- 0.12.3
-  Added ZPERFMON_PAGE_PARAMS to configuration and introduced page params to  be appended to profile name.

* Tue Nov 29 2011 mgattani@zynga.com
- 0.12.2
- Introduced Customizable memory profiling, time slack.

