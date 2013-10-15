FILES=Makefile \
      game_cron/ \
      zxhprof/ \
      ../CREDITS \
      README \
      zperfmon-client-apache.conf \
      zperfmon-client.spec \
      zperfmon.ini

DIRS=/var/opt/zperfmon/xhprof_tbz \
     /usr/share/php/zperfmon \
     /usr/local/zperfmon/bin \
     /etc/cron.d \
     /var/run/zperfmon \
     /etc/php.d \
     /mnt/logs/httpd/xhprof \
     /etc/httpd/conf.d \
     /etc/zperfmon/ \
     /var/log/zperfmon \
     /usr/local/zperfmon/etc

BUILDROOT=.
#svn info | grep "Revision" | sed "s/Revision: /r/"

all: 
	@@true # do nothing


tarball: 
	tar -czv --exclude=.svn --dereference -f $(BUILDROOT)/zperfmon-client.tgz $(FILES)

rpm: tarball
	rpmbuild -ta $(BUILDROOT)/zperfmon-client.tgz

install: $(INSTALL_ROOT) xhprof crons profiler apache_conf zperfmon_ini htpasswd


makedirs: $(INSTALL_ROOT)
	test -d $(INSTALL_ROOT) || echo "Invalid build root specified - $(INSTALL_ROOT)"
	for dir in $(DIRS); do mkdir -p $(INSTALL_ROOT)/$$dir; done

xhprof: makedirs
	cd zxhprof/extension && make install INSTALL_ROOT=$(INSTALL_ROOT)
	cp zxhprof/xhprof.ini $(INSTALL_ROOT)/etc/php.d/

crons: makedirs
	cp -v game_cron/*.py $(INSTALL_ROOT)/usr/local/zperfmon/bin/
	cp -v game_cron/*.sh $(INSTALL_ROOT)/usr/local/zperfmon/bin/
	chmod a+x $(INSTALL_ROOT)/usr/local/zperfmon/bin/*

profiler: makedirs
	cp -v zxhprof/zperfmon.inc.php $(INSTALL_ROOT)/usr/share/php/ 

apache_conf: makedirs
	cp -v zperfmon-client-apache.conf ${INSTALL_ROOT}/etc/httpd/conf.d/zperfmon-client.conf

zperfmon_ini: makedirs
	cp -v zperfmon.ini ${INSTALL_ROOT}/etc/zperfmon/zperfmon.ini

htpasswd: makedirs
	/usr/bin/htpasswd -bc ${INSTALL_ROOT}/usr/local/zperfmon/etc/zperfmon.htpasswd  "3.141592653" "2.718281823"
