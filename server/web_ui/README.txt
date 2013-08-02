Following changes should be made to httpd.conf file to configure user authentication to access the page

1) Write following sections in httpd.conf file
	<AuthnProviderAlias file zperfmon_user>
	AuthUserFile /var/www/.zperfmon_user
	</AuthnProviderAlias>

	<AuthnProviderAlias file zperfmon_admin>
	AuthUserFile /var/www/.zperfmon_admin
	</AuthnProviderAlias>

expln:- Here we have created aliases for two different password files namely zperfmon_user and zperfmon_admin. 
	Here wwe have already created two password files "/var/www/.zperfmon_admin" and "/var/www/.zperfmon_user"

2) Below this create a Directory  section in httpd.conf file as shown below :-

<Directory  [directory-for-authentication > [ex. /var/www/html/zperfmon]
     AllowOverride None
     AuthName "Require user ceredentials"
     AuthType Basic
     Require valid-user
     AuthBasicProvider zperfmon_user zperfmon_admin
</Directory>

expln:- Here we write the exact authentication script. 

3) Restart the server 
	/sbin/service httpd restart
4) Now try to access the page which are in the given directory(/var/www/html/zperfmon), it will ask for user authentication
