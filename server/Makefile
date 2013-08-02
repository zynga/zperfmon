FILES=Makefile \
      db_cron/ \
      configs/ \
      web_ui/ \
      schemas/ \
      scripts/ \
      CREDITS \
      README \
      zperfmon-server.conf \
      zperfmon-server.spec 

DIRS=/var/www/html/zperfmon/blobs \
     /etc/zperfmon/conf.d \
     /usr/local/zperfmon/bin \
     /usr/local/zperfmon/include \
     /usr/local/bin \
     /usr/local/zperfmon/etc/schemas \
     /usr/local/zperfmon/etc/configs \
     /usr/share/php/zperfmon \
     /etc/cron.d \
     /var/run/zperfmon \
     /var/log/zperfmon \
     /etc/php.d \
     /etc/httpd/conf.d 
	 
BUILDROOT=.
#svn info | grep "Revision" | sed "s/Revision: /r/"

all: 
	@@true # do nothing


tarball: 
	tar -czv --exclude=.svn --dereference -f $(BUILDROOT)/zperfmon-server.tgz $(FILES)

rpm: tarball
	rpmbuild -ta $(BUILDROOT)/zperfmon-server.tgz

install: $(INSTALL_ROOT) crons configs schemas webui symlinks scripts 

makedirs: $(INSTALL_ROOT)
	test -d $(INSTALL_ROOT) || echo "Invalid build root specified - $(INSTALL_ROOT)"
	for dir in $(DIRS); do mkdir -p $(INSTALL_ROOT)/$$dir; done

crons: makedirs
	cp -v db_cron/*.{php,py,sh} $(INSTALL_ROOT)/usr/local/zperfmon/bin/
	cp -rv db_cron/include/ $(INSTALL_ROOT)/usr/local/zperfmon/
	chmod a+x $(INSTALL_ROOT)/usr/local/zperfmon/bin/*
	cp -v db_cron/zperfmon-server.cron $(INSTALL_ROOT)/etc/cron.d/zperfmon-server
	
configs: makedirs
	cp -rvf db_cron/etc/* $(INSTALL_ROOT)/etc/zperfmon/
	cp -v web_ui/zperfmon-server.ini $(INSTALL_ROOT)/etc/php.d/
	cp -v configs/* $(INSTALL_ROOT)/usr/local/zperfmon/etc/configs/
	cp -v zperfmon-server.conf  $(INSTALL_ROOT)/etc/httpd/conf.d/

schemas: makedirs
	cp -rvf schemas/* $(INSTALL_ROOT)/usr/local/zperfmon/etc/schemas

webui: makedirs
	cp -rvf web_ui/* $(INSTALL_ROOT)/var/www/html/zperfmon/
	rm $(INSTALL_ROOT)/var/www/html/zperfmon/README.txt 
	rm $(INSTALL_ROOT)/var/www/html/zperfmon/zperfmon-server.ini 

symlinks: makedirs
	for each in aggregate_runs.php extract_functions.php combine_files.php; do \
		ln -svf /usr/local/zperfmon/bin/aggregate.php $(INSTALL_ROOT)/usr/local/zperfmon/bin/$$each;\
	done

scripts: makedirs
	cp -avf scripts/* $(INSTALL_ROOT)/usr/local/bin/
	chmod a+x $(INSTALL_ROOT)/usr/local/bin/*
