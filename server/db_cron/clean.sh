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

# 
# This script is meant to clean up the zPerfmon data which is either too old or can be reproduced with the remaining data.
# Collects the ROOT Directory and other paths from server.cfg file.
# Clears the obsolete links present in /db/zperfmon/<game>/xhprof.slow (path is hard coded, its same for every system)
# Clears the Blobs which are no more required (older than 3 days) /var/www/html/zperfmon/blobs/<game> (path is hard coded, its same for every system)
# Clears the game logs /var/log/zperfmon/ older than 3 days (path is hard coded, its same for every system)
# Clears the reports /var/opt/zperfmon/%s/reports/ older than 28 days (exact path is read from the server.cfg file)
# Clears the old profile data from the ROOT Directory (everything older than 28 days and everything except tar.bz profiles files older than 3 days)  


# Make sure only root can run our script

if [[ $EUID -ne 0 ]]; then
   echo "This script must be run as root" 1>&2
   exit 1
fi

MODIFIED_TIME_1=2
MODIFIED_TIME_2=7

ALL="false" 
LINKS="true"
BLOBS="true"
LOGS="true"
REPORTS="true"
OLD_DATA="true"
GAMES=

function process_options ()
{
    until [ -z "$1" ]
    do
      case $1 in
          --all|-a)
              ALL="true"
              shift 1
              ;;
          --games|-g)
              GAMES=`echo $2 | tr "," " "`
              shift 2
              ;;
          --li)
	      LINKS="false"
	      shift 1
	      ;;
          --blobs|-b)
              BLOBS="false"      
              shift 1
              ;;
          --lo)
              LOGS="false"      
              shift 1
              ;;
          --reports|-r)
              REPORTS="false"      
              shift 1
              ;;
          --data|-d)
              OLD_DATA="false"      
              shift 1
              ;;
          --m1)
              MODIFIED_TIME_1=$2 
              shift 2
              ;;
          --m2)
              MODIFIED_TIME_2=$2      
              shift 2
              ;;
	  	
	 *)
              echo "Usage: $0 [--all|-a] [--games|-g <comma sep list of games>] [--li|-b|--lo|-r|-d] | [--m1 <modified_time_1>] | [--m2 <modified_time_2>]"
              exit 1
              ;;
      esac
    done
}


# Process parameters
process_options $*


# get list of games to cleanup for
if [[ -z ${GAMES} ]]; then
	GAMES=$(/usr/local/zperfmon/bin/get_conf_vars.php -s game_list)
fi

echo -e "Cleaning up following games " $GAMES"\\n"

ROOT_UPLOAD_DIRECTORY=$(/usr/local/zperfmon/bin/get_conf_vars.php root_upload_directory)
DAILY_UPLOAD_DIRECTORY=$(/usr/local/zperfmon/bin/get_conf_vars.php daily_upload_directory)
SLOW_PAGE=$(/usr/local/zperfmon/bin/get_conf_vars.php slow_page_dir)
DAILY_REPORT_FILE=$(/usr/local/zperfmon/bin/get_conf_vars.php daily_report_file)
WEEKLY_REPORT_FILE=$(/usr/local/zperfmon/bin/get_conf_vars.php weekly_report_file)

# delete hanging links in /db/zperfmon/<game>/xhprof.slow
function remove_links(){
	
	echo -e "Cleaning Links...\\n"
	symlinks -d  `echo "$SLOW_PAGE" | sed "s/%s/$1/"`	

}

# delete blobs older than MODIFIED_TIME_1 = 2 days (modified date) in /var/www/html/zperfmon/blobs/<game>
function remove_blobs(){

	echo "Cleaning Blobs..."
	if [ -d "/var/www/html/zperfmon/blobs/"$1"/" ]; then
		find `echo "/var/www/html/zperfmon/blobs/"$1"/"` -mindepth 1 -maxdepth 1 -mtime +${MODIFIED_TIME_1} -exec rm -rf {} \;
	fi
}

# remove logs in /var/log/zperfmon/ older than MODIFIED_TIME_1 = 2 days (modified)
function remove_logs(){

	echo "Cleaning Logs..."
	if [ -f "/var/log/zperfmon/"$1".log" ]; then 
		find `echo "/var/log/zperfmon/"$1".log"` -exec rm -rf {} \;
	fi
}

#remove old reports from /var/opt/zperfmon/%s/reports/ (older than MODIFIED_TIME_2 = 14)
function remove_reports(){

	echo "Cleaning Reports..."
	dailypath=$(echo `dirname "$DAILY_REPORT_FILE" | sed "s/%s/$1/"`)
	weeklypath=$(echo `dirname "$WEEKLY_REPORT_FILE" | sed "s/%s/$1/"`)
	if [ -d $dailypath ]; then
		find $dailypath -mindepth 1 -maxdepth 1 -mtime +$MODIFIED_TIME_2 -exec rm -rf {} \;
	fi
	if [ -d $weeklypath ]; then
		find $weeklypath -mindepth 1 -maxdepth 1 -mtime +$MODIFIED_TIME_2 -exec rm -rf {} \;
	fi
}

#remove old profiles and processed data
function remove_old_data(){

	echo "Cleaning old profile data..."

	#remove all older than 14 days    
	find `echo "$ROOT_UPLOAD_DIRECTORY" | sed "s/%s/$1/"` -mindepth 1 -maxdepth 1 -mtime +$MODIFIED_TIME_2 -exec rm -rf {} \;
	find `echo "$DAILY_UPLOAD_DIRECTORY" | sed "s/%s/$1/"` -mindepth 1 -maxdepth 1 -mtime +$MODIFIED_TIME_2 -exec rm -rf {} \;
	find `echo "$ROOT_UPLOAD_DIRECTORY" | sed "s/%s/$1/"` -mindepth 1 -maxdepth 1 -type d -empty -exec rmdir {} \;
	       
	#uploaded tar files deletion will be a part of game processing
        #find `echo "$ROOT_UPLOAD_DIRECTORY" | sed "s/%s/$1/"` -mindepth 3 \(  -iname *.tar.bz__* \) -mmin +30 -exec rm -f {} \;

	#remove the symlinks for sudo game ( games for each array )
	symlinks -d  `echo "$ROOT_UPLOAD_DIRECTORY../../" | sed "s/%s/$1/"`
	
}

#loop for each game
function clean_game(){

for GAME in ${GAMES}
do
	echo -e "Starting to clean "$GAME"\\n"
	if [ $BLOBS = true ]; then 
		remove_blobs $GAME
	fi
	if [ $LOGS = true ]; then 
		remove_logs $GAME
	fi
	if [ $REPORTS = true ]; then 
		remove_reports $GAME
	fi
	if [ $OLD_DATA = true ]; then 
		remove_old_data $GAME
	fi
        if [ $LINKS = true ]; then
                remove_links $GAME
        fi

done

}

clean_game

