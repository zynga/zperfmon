#!/usr/bin/python

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

"""
This script will be called periodically from cron - 30 minutes
is the default frequency.

Each run will have a unique "run_id". At present it is the unix time
(in seconds) when the script is launched.

There are multiple parts to the script:

1. Moves all xhprof profiles collected till now to a target directory

2. Grab processed dump of apache page access times

- All of the above files are rolled up into a single tbz and is
uploaded via http to a pre-configured machine.
"""

import os, sys
import syslog
import re
import commands
import traceback
import os.path as path
import glob
import time
import shutil

from global_preset_paths import global_preset_paths
import zperfmon_config
from socket import gethostbyname, gethostname

#
# Scan default xhprof dump path for all profile files collected and
# move them to the dir where the tbz will be constructed.
#
def collect_profile_dumps(game_config, run_id):
    
    xhprof_config = None
    # Extract XHProf profile target directory
    try:
        xhprof_config = open(game_config.ZPERFMON_XHPROF_CONFIG_FILE).read()
    except:
        # xhprof setup not correct, we will exit with an exception
        pass

    match = None
    if xhprof_config:
        match = re.search("xhprof.output_dir=\"(.*)\"", xhprof_config)

    if not match:
        raise "XHProf conf '%s' does not exist or does not have dump path" % (
            game_config.ZPERFMON_XHPROF_CONFIG_FILE)

    profile_dir = match.group(1)
    # Check if there is atleast one profile to be processed. If
    # not, do nothing more.
    if not glob.glob(os.path.join(profile_dir, "*")):
	print os.path.join(profile_dir, "*")
        return ""
   
    # Create new profile directory for bzipping and deleting
    profile_dir_path = os.path.normpath(profile_dir)
    deldir = "%s%s" % (profile_dir_path, run_id)
    try:
        shutil.move(profile_dir_path, deldir)
	os.mkdir(profile_dir,0777)
	os.chmod(profile_dir,0777)
    except:
        return ""

    return deldir


#
# ask the local server for statistics
def collect_apache_stats(game_config, run_id, upload_dir):

    xhprof_config = None
    # Extract XHProf profile target directory
    try:
        xhprof_config = open(game_config.ZPERFMON_XHPROF_CONFIG_FILE).read()
    except:
        # xhprof setup not correct, we will exit with an exception
        pass

    match = None
    if xhprof_config:
        match = re.search("xhprof.output_dir=\"(.*)\"", xhprof_config)

    if not match:
        raise "XHProf conf '%s' does not exist or does not have dump path" % (
            game_config.ZPERFMON_XHPROF_CONFIG_FILE)

    try:
        tgt = os.path.join(upload_dir, "%s.apache-page.stats" % run_id)

        cmd = "mv %s %s" %(game_config.ZPERFMON_APACHE_STAT_FILE, tgt)
        result = commands.getstatusoutput(cmd)
        if result[0]: # failed
            syslog.syslog(cmd)
            syslog.syslog(result[1])
    except:
        pass

#
# bzip everything in given directory 
def bzip_it(run_id, game_config, upload_dir):

    xhprof_config = None
    # Extract XHProf profile target directory
    try:
        xhprof_config = open(game_config.ZPERFMON_XHPROF_CONFIG_FILE).read()
    except:
        # xhprof setup not correct, we will exit with an exception
        pass

    match = None
    if xhprof_config:
        match = re.search("xhprof.output_dir=\"(.*)\"", xhprof_config)

    if not match:
        raise "XHProf conf '%s' does not exist or does not have dump path" % (
            game_config.ZPERFMON_XHPROF_CONFIG_FILE)

    # This is the file we will upload to the x-mon machine
    tbz_file_name = "%s/%s.tar.bz" % (game_config.ZPERFMON_TBZ_TARGET_PATH,
                                      run_id)

    cmd = "tar jcf %s %s/" % (tbz_file_name, upload_dir)
    result = commands.getstatusoutput(cmd)

    shutil.rmtree(upload_dir)

    if result[0]: # failed
        syslog.syslog("cmd was: %s, error was: %s" % (cmd, result[1]))
        raise "tar bzip failed: %s" % (cmd)


def upload_tbz(game_config):
    "uploads all tbz files in the ZPERFMON_TBZ_TARGET_PATH"

    tarballs = glob.glob("%s/*.tar.bz" % (game_config.ZPERFMON_TBZ_TARGET_PATH))
    for tbz_file_name in tarballs:

        try:
            client_ip_header = """-H "CLIENT_IP: %s" """ % (gethostbyname(gethostname()))
        except:
            client_ip_header = ""
        
        # Allow curl to run for a max of 30 seconds only
        cmd = """curl -m 30 -F "uploadedfile=@%s" -F "cmd=XHPROF" -F "game=%s" %s %s""" % (
            			tbz_file_name,
            			game_config.ZPERFMON_GAME_NAME,
                                client_ip_header,
            			game_config.ZPERFMON_UPLOAD_URL)
        result = commands.getstatusoutput(cmd)
        if result[0]: # failed
            syslog.syslog("cmd was: %s, error was: %s" % (cmd, result[1]))
            raise "curl POST failed: %s" % (cmd)
        else:
            os.rename(tbz_file_name, tbz_file_name.replace('.tar.bz','.old'))

def usage():
    print "Usage:", sys.argv[0]
    return


# delete all files inside the give path
def cleanup(game_config, run_id, create=False):
    run_dir = "%s/%s" % (game_config.ZPERFMON_TBZ_TARGET_PATH, run_id)
    if os.path.exists(run_dir):
        shutil.rmtree(run_dir)
    if create:
        os.makedirs(run_dir)


def invoke_collectors(run_id, game_config):
    # let us be paranoid and cleanup before us
    cleanup(game_config, run_id, True)

    #
    # Move collected xhprof output to upload directory. If no profiles
    # are collected this machine has not been selected for profiling.
    #
    upload_dir = collect_profile_dumps(game_config, run_id)
    if not upload_dir:
        os.unlink(game_config.ZPERFMON_APACHE_STAT_FILE)
        return

    # Apache page delivery times
    collect_apache_stats(game_config, run_id, upload_dir)

    # We have everything we need, send it on it's way to the zperfmon server
    bzip_it(run_id, game_config, upload_dir)


def main():
    # This is the time stamp/tag for all data collected in this run
    run_id = str(int(time.time())) # Treat this as UTC

    try:
        # zperfmon is the log id
        syslog.openlog("zperfmon")
    except:
        # The worst that could happen is we will log under a different
        # name - most probably python
        pass


    # All error tracking is through this try-except block, so we are
    # not worried about return values
    try:
        try:
            # Load static and parameters from zRuntime or pre-set file
            game_config = zperfmon_config.GameSetup(global_preset_paths)
        #
        # other exceptions will be caught by the outer try
        except:
            return 0

        cleanup(game_config, run_id, True)

        try:
            invoke_collectors(run_id, game_config)
            upload_tbz(game_config)
        finally:
            cleanup(game_config, run_id)
            
    except:
        info = sys.exc_info()
        syslog.syslog(str(info[0]))
        syslog.syslog(traceback.format_exc())
        
    syslog.closelog()


if __name__ == "__main__":
    sys.exit(main())
