# $Id: zperfmon-server.spec 6458 2010-08-30 20:00:02Z prashun $
# Authority: Shankara, Prashun
%define php_apiver  %((echo 0; php -i 2>/dev/null | sed -n 's/^PHP API => //p') | tail -1)
%define php_version  %((echo 0; php -i 2>/dev/null | sed -n 's/^PHP Version => //p') | tail -1 | tr '-' '_')

%define php_extdir %(php-config --extension-dir 2>/dev/null || echo %{_libdir}/php4)
%define _unpackaged_files_terminate_build 0 

Summary: zPerfmon server side components deployment, MySQL db creation and aggregation of collected data
Name: zperfmon-server
Version: 1.0.9.5
Release: %{php_version}
License: BSD
Group: Development/Languages
URL: http://www.zynga.com/
Packager: Prashun Purkayastha <ppurkayastha@zynga.com>,Debasish Bose <dbose@zynga.com>
Vendor: Zynga Repository, https://svn.zynga.com/svn/zperfmon/trunk/server/ 

Source: /zperfmon-server.tgz
BuildRoot: %{_tmppath}/%{name}-%{version}-%{release}-root
Requires: php >= %{php_version}, graphviz, gearmand, php-splunk-api

%description
ZPERFMON server is a server-side components which periodically harvest and aggregate the collected by ZPERFMON client machine data and insert/update the analytics into a managed MySQL db

%prep
%setup -q -c -n zperfmon-server
# Docs are +x (as of 2.0.0), so fix here
%{__chmod} -x CREDITS README

%clean
%{__rm} -rf %{buildroot}

%install
%{__rm} -rf %{buildroot}
%{__mkdir_p} %{buildroot}
%{__make} install INSTALL_ROOT=%{buildroot}

%post

%files
%defattr(-,root,root,-)
#%doc CREDITS README
%doc README
%attr(0777, -, -) /var/log/zperfmon/
%attr(0777, -, -) /var/www/html/zperfmon/blobs/
%attr(0644, root, root) /etc/cron.d/zperfmon-server
%attr(0755, root, root) /usr/local/zperfmon/bin/update_game_map.php
/etc/php.d/zperfmon-server.ini
%config(noreplace)/etc/zperfmon/server.cfg
%config(noreplace)/etc/zperfmon/eu.ini
%config(noreplace)/etc/zperfmon/rs.ini
%config(noreplace)/etc/zperfmon/common_config.yml
%config(noreplace)/etc/zperfmon/common_config_zcloud.yml
%config(noreplace)/etc/zperfmon/conf.d/game.cfg
%config(noreplace)/etc/zperfmon/report.ini
/etc/httpd/conf.d/
/etc/cron.d/zperfmon-server/
/usr/local/zperfmon/etc/schemas/
/usr/local/zperfmon/bin/
/usr/local/zperfmon/include/
/usr/local/bin/
/var/www/html/zperfmon/
/usr/share/php/zperfmon/

#%ghost /usr/local/zperfmon/*.pyc
%changelog
* Mon Apr 23 2013 kopaul@zynga.com
- Version 1.0.9.5
- zPerfmon automatic slack reduction feature
- Updated the retention policy in zPerfmon
- Modified and enhanced the zPErfmon ADD / REMOVE game API
- API to fetch the instantaneous data from zmon
- Admin tab in zperfmon to tweak thresholds for slack computation
* Mon Aug 13 2012 rbakshi@zynga.com
- Version 1.0.8
- DB partitioning to enable auto deletion of older data in a 
-   relatively faster and simpler manner
- zperfmon-upgrade: install mysql5.5 on DB server, run script 
-   add.p_tag.xhprof_blob_30min.php to add p_tag and create partitions,
-   upgrade zperfmon server 
* Tue Jul 10 2012 rbakshi@zynga.com
- Version 1.0.7
- added eu.common_eu.instance_count field
- sql procedure to initially populate eu.common_eu.instance_count
- fix for weighted average of EU gauges on overview-dashboard
* Mon Jul 02 2012 rbakshi@zynga.com
- Version 1.0.6
- changes for zPerfmon over zPerfmon.
- this will let zPerfmon profile zPerfmon itself.
* Fri May 18 2012 gkumar@zynga.com
- Version 1.0.5
- Bug to prevent addition of zmon data if only web nodes are there ZPERF-95
- Changed for staging and dev games to appear in right place even though it contains multiple underscores ZPERF-96
- Bugs in ipview ( using generic folder name for ipview ) SEG-5942
- Bug which prevented game name to appear in xhprof view 	ZPERF-97
- Removed unused reference to links in profile view 	ZPERF-98
- bug to add array game from API SEG-6937
- DAU fix in report ZPERF-99
- Zruntime variable update for new game added by API
* Tue Dec 20 2011 uprakash@zynga.com
- Version 0.13.3
- fixed compression problem for array game. Now passing timeslot parameter is mandatory, else compression will fail
- fixed error log bug in cpmpress.php
* Thu Dec 15 2011 gkumar@zynga.com
- Version 0.13.2
- fixed zperfmon-add-game
* Fri Dec 09 2011 gkumar@zynga.com
- Version 0.13.1
-  Bug fixes in UI
- mgattani@zynga.com
-  Compression isn’t working at all via cron
-  fetchRS says tolerance crossed for every negative change in number of instances
* Thu Dec 08 2011 gkumar@zynga.com
- Version 0.13.0
- Changes in xhprof library to read compressed file
-	  Whenever a file is compressed it will be read by the xhprof library , if the file is not compressed then it would be read normally.
- fixed a bud which was displaying NaN instead of 0 in fn summary of xhprof
-	  Associated jira ticket http://jira.xxxx.xxx/browse/IWISH
- now the zperfmon Url can be accessed by using gameid=xyz in the URL 
-	  Note you may need to add any specific games in the config db which you want to access .
-	  And also run the config.sql to create db
- In  Eu dashboard which made dynamic mc tabs(sub menu).
- Fixed a bug in Eu dashboard which the mb tab(sub menu ) to appear in all the tabs.
- changed the report csv file column name and filename.
- mgattani@zynga.com
-  FetchRS redesigned
-  DAU code resign and refactoring
-  Xhprof memory profiling issue fixed.
- uprakash@zynga.com
- After every half hour processing every unzipped profiles (inside IP
-	Directory) will be compressed and kept there with same file name. Also the uploaded tar files will get deleted.
- Clean up script has changed as follows:-
- 	i) Every thing inside /db/zperfmon/<game>/timeslots/ which are older than 14 days will be deleted.
-       ii) All other cleaning logic will remain same.


* Wed Dec 07 2011 uprakash@zynga.com
- Version 0.12.8
- Fix the bug which was causing same data being inserted into all array_games as of parent games for EU
- Added a script to compress the unzipped profiles and delete uploaded tar files 
- Now clean up script will delete every thing older than 14 days in /db/zperfmon/<game>/timeslots/ 

* Tue Nov 15 2011 gkumar@zynga.com
- version 0.12.7
- Changed pricing of ec2 and zCloud server 
- version 0.12.6
- Fixed a issue with report which prevented hyperlinks in mail to work
- Mahesh Gattani: Fixed path of clean.sh in clean.php script
* Fri Nov 11 2011 mgattani@zynga.com
- version 0.12.5
- Fixed clean.sh to clean for array of games also
* Wed Nov 07 2011 uprakash@zynga.com
- version 0.12.4
- Changed the query for web_eu_chart range
- Changed report-collector constructor to read report.ini from server.cfg 
- forcing the trunk to be in sync with dev branch
* Wed Nov 02 2011 uprakash@zynga.com
- version 0.12.3
- added scripts process_uploads.php and its cron process_upload.sh to process the uploaded  profiles more frequently.
- currently the cron runs at every 5 minutes. Changed following scripts accordingly:-
- rightscale.php, get_game_metrics.php, array_wise_split.php, massage_profiles.py, massage_profiles_unzip.py
----- gkumar@zynga.com
- changes in report
-- changed the cost per user in report to have a common factor for all the games across all the clouds
-- implemented the trending in reports
- changed piecharts in overview dashboard to show pages according to specific page

* Wed Oct 19 2011 gkumar@zynga.com
- 0.12.2
- checked in the code from production server (xxxx.xxxx.xxx) and merged in dev branch of zperfmon
