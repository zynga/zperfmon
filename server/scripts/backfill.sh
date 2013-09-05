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
# Backfill the missing data for given timeslots and given games
# parameters passed
# g : game name 
# games : name of games
# t :  comma sep start and end timeslots
# slots : comma separated timeslots
#


GAMES=
SLOTS=

process_options()
{
    until [ -z "$1" ]
    do
      case $1 in
	 --games|-g)
	      GAMES=$(echo $2 | tr "," " ")
	      echo $GAMES
	      shift 2
	      ;;
	   --slots|-t)
	      SLOTS=$(echo $2 | tr "," "\n" | awk 'BEGIN{ FS = "-"} /-/ {for(i = $1; i <= $2; i++) print i; next;} !/-/')
	      echo $SLOTS
	      shift 2
	      ;;
	  *)
	      echo "Usage: $0 [--games|-g <comma sep list of games>] [--timslots|-t <comma sep list of timeslots>]"
	      exit 1
	      ;;
      esac
    done
}


# Process parameters
process_options $*


# Outer loop for all games 
for GAME in ${GAMES}
  do

  # 
  # Loop through time slot directories in reverse order and 
  # creating marker files to enable backfill:
  #
  #
  for SLOT in ${SLOTS}
    do
    XHPROF_DIR=/db/zperfmon/${GAME}/timeslots/${SLOT}/xhprof
    if [ -d ${XHPROF_DIR} ]; then

	echo touch $XHPROF_DIR/.profiles $XHPROF_DIR/.slowpages $XHPROF_DIR/.apache_stats
	touch $XHPROF_DIR/.profiles $XHPROF_DIR/.slowpages $XHPROF_DIR/.apache_stats

    else
        echo ${XHPROF_DIR}" does not exist"
    fi

  done # per timeslot loop

  SLOTS_COMMA_SEP=$(echo $SLOTS | tr " " ",")
  echo php /usr/local/zperfmon/bin/get_game_metrics.php -g ${GAME} -t "{${SLOTS_COMMA_SEP}}"
  php /usr/local/zperfmon/bin/get_game_metrics.php -g ${GAME} -t "{${SLOTS_COMMA_SEP}}" > /var/tmp/bacfill.${GAME}.log & 

done # per game loop
wait

