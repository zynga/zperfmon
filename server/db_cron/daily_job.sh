#!/bin/bash

#
# Copyright 2013 Zynga Inc.
# 
# Licensed under the Apache License, Version 2.0 (the "License");
#    you may not use this file except in compliance with the License.
#    You may obtain a copy of the License at
# 
#    http://www.apache.org/licenses/LICENSE-2.0
# 
#    Unless required by applicable law or agreed to in writing, software
#      distributed under the License is distributed on an "AS IS" BASIS,
#      WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
#    See the License for the specific language governing permissions and
#    limitations under the License.
# 

# run this script to update the report db to have any new classes
taskset -c 13-15 /usr/bin/php /usr/local/zperfmon/bin/update_report_hostgroup.php

# generate instance email reports
taskset -c 13-15 /usr/bin/php /usr/local/zperfmon/bin/daily_instance_report_job.php -j report

# generate aggregate and email reports
/usr/local/zperfmon/bin/get_conf_vars.php -s game_list | xargs -n1 -P8 taskset -c 13-15 /usr/bin/php /usr/local/zperfmon/bin/daily_job.php -g

# cleanup zperfmon old data
/usr/local/zperfmon/bin/get_conf_vars.php -s game_list | xargs -n1 -P8 taskset -c 13-15 /usr/bin/php /usr/local/zperfmon/bin/clean.php -g

# rebuild the deployment to game map file
/usr/local/zperfmon/bin/update_game_map.php

# update the vertica_stats_30min table
php -r "include 'server.cfg'; echo implode(' ',\$server_cfg['game_list']);" | xargs -n1 -P8 taskset -c 1-8 /usr/bin/php  /usr/local/zperfmon/bin/vertica_table_add_column.php -g
