zPerfmon - ***Grok Production Profiles***
========


zPerfmon is an app performance analysis suite. It collects production
profiles, systems metrics and other data on a periodic basis. It has
data visualization and data correlation capabilities which lets you
answer questions about performance, health and behavior trends as
well.

![App state Overview Page][overview_image]

### There are two distinct pieces in it:
 
[Server][server-code] - which takes care of ETL-ing, storing and presenting
  	collected data. Data streams can consist of profiles, page hits,
	system metrics, unqiue user counts or even arbitrary events
        with timestamps.

[Client][client-code] - is like an agent. It goes into the application being monitored and at
	a minimum triggers profile collection. As long as the client generates
	PHP serialized or igbinary serialized xhprof formatted data, server
	can pick it up. There is a schema mandated for the profile name.  

Server and Client are connected by a [configuration file][client-config]. The PHP client uses
xhprof extension for profile collection. For another language or platform to
deliver profiles into zPerfmon, you will need a profile generation solution
in that language which can dump xhprof formatted profiles.

The most intersting view for a developer from a performance or behavior
perspective is the [profile browser][profile-view] tab. It shows an average profile for each
distinct page hit at half hour granularity. 
![profile_page_image]

If however, you want to browse individual profiles, maybe to see the one
errant hit which took 10 times the average, or look at the hit which consumed
the most CPU, there is the [un-aggregated view][profile-view-unagg]

![unaggregated_image]

Data mined from profiles are surfaced in different ways.
[Top-5 functions][top-5-functions] is one of them. It provides visual clues
when top function distribution according to wall time change.

![top_5_image]

High altitude overviews which answer questions like "Did my web instance count
go up without user count increasing?" is answered by the [business tab][bd-dash]

![counts_tab_image]

[overview_image]: props/overview_small.jpg "Overview Dashboard"
[profile_page_image]: props/profile_page_small.jpg "Profile Tab"
[top_5_image]: props/top_5_small.jpg "Top5 functions Tab"
[unaggregated_image]: props/unaggregated_small.jpg "Unaggregated Profiles Page"
[counts_tab_image]: props/counts_tab_small.jpg "Instance and user count Tab"
[server-code]: server
[client-code]: client
[client-config]: client/zperfmon.ini.sample
[profile-view]: server/web_ui/profile-view
[profile-view-unagg]: server/web_ui/profile-view/ipview.php
[top-5-functions]: server/web_ui/top5-view/top5-view.php
[bd-dash]: server/web_ui/bd-view/bd-view.html
