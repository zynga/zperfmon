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

#
# For each bzip file in given directory unbzip-untar into a newly created dir
#
# For every xhprof file thus extracted:
# create and sort into subdirs based on profile source filename
#
# called as: massage_profiles.py /var/zperfmon/fish/xhprof/6529 777777777777
#

import os
import re
import commands
import sys
import syslog
import traceback
import shutil
from glob import glob
import json #for now, we'll depend on a pure python impl
import time

server_config_file = "/etc/zperfmon/server.cfg"

# holder class for putting in config parameters
class CFG:

    def __init__(self):
        setattr(self, "run_id", int(time.time()))
	setattr(self, "aggregate_all", 1)

    def set_option(self, option, value):
        setattr(self, option, value)
    pass

def debug_print(*args):
    #print(args)
    return

#
# Read the server config file which is php code that creates a map.
#
def get_server_config(config_file):

    config_content = open(config_file).read()

    cfg = CFG()
    for m in re.finditer("^[\t ]*\"([^\"]+)\"\s*=>\s*\"([^\"]+)\"",
                         config_content, re.MULTILINE):
        cfg.set_option(m.group(1), m.group(2))

    return cfg

#
# Find all files in the given directory which look like and uploaded
# tarbzip appended with the IP of the uploading machine
#
# Create a directory with the IP and extract the contents into that directory
#
# upload_path: directory to scan for tbz's
#
# Returns the list of directories such created
#
# dir_list = un_tarbzip("/var/zperfmon/fish/xhprof/712716")
# print dir_list
#
def list_dirs(server_cfg):
    tbz_re = "^([0-9]+\.[0-9]+\.[0-9]+\.[0-9]+)$"
    tbz_re = re.compile(tbz_re);

    dir_list = []
    for file_name in os.listdir(server_cfg.upload_path):
        match = tbz_re.match(file_name)
        if not match:
            continue

        dir_name = match.group(1)
        dir_list.append(dir_name)

    # return dir_list
    # return uniq ip list (duplicate ips may be there due to twice upload in one slot)
    return list(set(dir_list))


#
# Given a list of directories, classify all profile outputs according
# to source, create directories for each source and group the
# collected profiles under the corresponding directory
#
# upload_path: path where directories with extracted profiles are
# dir_list: list of directories to scan for profiles
#
# returns dictionary with php page as the key and list of profiles as
# the value
#
# segregate_profiles("/var/zperfmon/fish/xhprof/712716",
#              ['184.71.12.151', '174.119.92.175', '184.73.12.158', '174.129.92.195'])
#
# for i in coll.keys():
#     print i, coll[i]
#     print
def segregate_profiles(server_cfg, dir_list):

    profile_files = {}

    # profile_re = "H?[0-9]{10,10}\.(.*)\.agrgt.xhprof"
    # profile_re = re.compile(profile_re)

    # For each client that delivered a bzip file ...
    for dir_ip in dir_list:
        
        profile_ip_dir = os.path.join(server_cfg.upload_path, dir_ip)

        # Process each xhprof dump in that directory ...
        for file_name in os.listdir(profile_ip_dir):

            #
            # The file name format is:
            #           "<number>.:<timestamp>:<ip>:<php file>:xhprof"
            #
            name_components = file_name.split(":")
            if name_components[-1] != "xhprof":
                continue

            # This is the file that generated the profile
            php_source = name_components[-2]
	    if name_components[0][0] =='H':
 		php_source= "^"+php_source
            # Complete path to the profile
            source_file = os.path.join(profile_ip_dir, file_name)

            target_file = os.path.join(server_cfg.upload_path, php_source, file_name)

            # print source_file, target_file

            # Check if the directory has to be created
            if not profile_files.has_key(php_source):
                profile_path = os.path.join(server_cfg.upload_path, php_source)
                if(not os.path.exists(profile_path)):
                    os.mkdir(profile_path)
                profile_files[php_source] = []

            # Sym-link the profile and add to dictionary
	    # Checking for already existing links(for backfill purpose)
	    if(not os.path.isfile(target_file)):
                os.symlink(source_file, target_file)
	
	    # We have to take the absolute path, otherwise there are some // instead of /
	    # Slow pages moveing returns a list of profiles with their absolute path, 
	    # so comparison is possible now
	    target_file = os.path.abspath(target_file)
            profile_files[php_source].append(target_file)

    return  profile_files

#
#Clean up the *php folder of the slow pages. and update the profile_list accordingly
#
def slow_pages_cleanup(server_cfg, game_name, time_slot, profile_list):
    file_path = server_cfg.move_slowpages_command
    cmd = "%s -g %s -t %s" % (file_path, game_name, time_slot)
    # print cmd
    result = commands.getstatusoutput(cmd)
    # print result
    if (result[0] != 0):
        print "could not move slow pages"
	return
    for profile in eval(result[1]):
	profile = profile.replace("\\", "");
	name_components = profile.split(":")
	php_source = name_components[len(name_components) - 2]
	if( profile_list[php_source].count(profile)  > 0):
		profile_list[php_source].remove(profile)
		
	# This is required because otherwise if all the profiles for a page are slow,
	# the slot will appear as partially profiled instead of completely profiled.
	if( len(profile_list[php_source]) == 0):
                profile_list.pop(php_source)
    return profile_list


#
# Call xhprof aggregation script with each profile collection dir as argument
def aggregate_all(server_cfg, page_list):

    # If all the profiles for all the pages for a timeslot are slow,
    # then we dont have anything to aggregate, so skipping.
    if (len(page_list) == 0):
	return True
	
    # Create the 'blob_dir'
    blobdir = os.path.join(server_cfg.upload_path, server_cfg.blob_dir) 
    if(not os.path.exists(blobdir)): os.mkdir(blobdir);

    cmds = []

    manifest = {}

    for page in page_list:
        name = "%s.%s" % (server_cfg.run_id, page)
        cmds.append("%s %s %s %s %s/%s/*:xhprof" % (server_cfg.profile_aggregation_command, 
                            server_cfg.game_name, name, blobdir, server_cfg.upload_path, page))

        cmds.append("%s %s %s %s %s/%s/*:xhprof" % (server_cfg.profile_extraction_command, 
                            server_cfg.game_name, name, blobdir, server_cfg.upload_path, page))

    name = "%s.all" % (server_cfg.run_id)
    xhprof_aggregate = os.path.join(blobdir, "%s.%s" % (name, "xhprof"))

    if(os.path.exists(xhprof_aggregate)):
        os.rename(xhprof_aggregate, xhprof_aggregate+"~")

    manifest["all"] = ["%s.xhprof" % (name),
                            len(glob("%s/*.php/*:xhprof" % (server_cfg.upload_path)))] # keep in sync with aggregate.php

    cmds.append("%s %s %s %s %s/*.xhprof" % (server_cfg.profile_aggregation_command, 
                            server_cfg.game_name, name, blobdir, blobdir))
    cmds.append("%s %s %s %s %s/*.xhprof" % (server_cfg.profile_extraction_command, 
                            server_cfg.game_name, name, blobdir, blobdir))
    cmds.append("%s %s '%s' %s %s/*.extract" % (server_cfg.profile_combine_command, 
                            server_cfg.game_name,
                            server_cfg.profile_pattern, 
                            os.path.join(blobdir, server_cfg.profile_blob_filename), 
                            blobdir))
    # run all commands (maybe parallely in the future)
    results = map(lambda cmd: commands.getstatusoutput(cmd), cmds)
    failures = filter(lambda result: result[0], results)
    # set page otions like memory profilling enabled or not. can be used in future to add more options
    create_manifest(server_cfg, page_list)   
    # ignore failures, recovery and sanity is not worth the returns
    if len(failures):
        raise failures[0][1]

    return True


#
# setting the profilling information to be filled in the manifest file
# This is done by going through final aggregated profiles of each make and see if there is memory profilind data
#
def create_manifest(server_cfg, page_list):

    blobdir = os.path.join(server_cfg.upload_path, server_cfg.blob_dir)	
    manifest = {}
    # creating the manifest file to be read in by the UI 
    memory_profilied_pages = 0
    for page in page_list:
        name = "%s.%s" % (server_cfg.run_id, page)
        try:
            f = open("%s/%s.extract" % (blobdir,name), "r")
            data = f.read(1024)
            if ("\"mu\"" in data):
                manifest[page] = ["%s.xhprof" % (name),
                                  len(glob("%s/%s/*:xhprof" % (server_cfg.upload_path, page))), "MemEnabled"] # keep in sync with aggregate.php
                memory_profilied_pages = memory_profilied_pages + 1
            else :
                manifest[page] = ["%s.xhprof" % (name),
                                  len(glob("%s/%s/*:xhprof" % (server_cfg.upload_path, page))), "MemDisabled"] # keep in sync with aggregate.php
        except:
            syslog.syslog("Processing for " + name + " failed")
            pass

    # going through all the profiles and figuring out the all.xhprof is memory profiled or not
    if ( memory_profilied_pages == len(page_list) ):
        manifest['all'] = ["%s.xhprof" % (name),
                                    len(glob("%s/*.php/*:xhprof" % (server_cfg.upload_path))), "MemEnabled"] # keep in sync with aggregate.php
    elif ( memory_profilied_pages != 0 ):
        manifest['all'] = ["%s.xhprof" % (name),
                                    len(glob("%s/*.php/*:xhprof" % (server_cfg.upload_path))), "MemPartial"] # keep in sync with aggregate.php
    else:
        manifest['all'] = ["%s.xhprof" % (name),
                                    len(glob("%s/*.php/*:xhprof" % (server_cfg.upload_path))), "MemDisabled"] # keep in sync with aggregate.php

    index = open(os.path.join(blobdir, "manifest.json"),"w")
    index.write(json.write(manifest, True))
    index.close()


#
# Delete temporary profile directories, create a single bzip file of
# all aggregated profiles and return that file name.
#
# Working directory must be set to where the split profiles are
# stored.
def cleanup_and_bzip(exec_dir, dir_list):
    #for dir_name in dir_list:
    #    try: 
    #        shutil.rmtree(os.path.join(exec_dir, dir_name))
    #    except:
    #        pass
    
    # create one tbz for uploading
    cmd = "tar jcf %s %s/" % (server_cfg.xhprof_tbz_name,
                                  server_cfg.blob_dir)
    result = commands.getstatusoutput(cmd)
    debug_print(cmd)
    
    # ignore failures, recovery and sanity is not worth the returns
    if result[0]:
        debug_print(result[1])
        return None

    tbz_file = os.path.join(os.getcwd(), server_cfg.xhprof_tbz_name)
    if tbz_file and os.path.exists(tbz_file):
        print tbz_file
        return tbz_file

    return None

#
# prases the supplied argument, validate for required argument, 
# and stores the passed argument in the config(server_cfg)
#
def parse_and_store_arguments(cfg):
    import getopt
    try:
        (args, leftover) = getopt.getopt(sys.argv[1:], "g:t:d:", ["no-aggregate", "ip-list="])
    except:
        print traceback.format_exc()
        print "error while parsing param"
        pass
    for o,v in args:
        if o == "-g":
	   cfg.set_option("game_name", v)
	elif o == "-t":
	   cfg.set_option("run_id", v)
	elif o == "--no-aggregate":
	   cfg.set_option("aggregate_all", 0)
	elif o == "-d":
	   cfg.set_option("upload_path", v)
	elif o == "--ip_list":
	   cfg.set_option("ip_list", v.split(","));
        else:
           pass

    if not hasattr(cfg, "game_name"):
        print "game name is not passed"
	return None
    return cfg

def set_default_upload_path(server_cfg):
    res = commands.getstatusoutput("php /usr/local/zperfmon/bin/get_conf_vars.php root_upload_directory")
    if res[0]:
        print "problem in getting root upload directory. No upload directory. Exiting"
        sys.exit()
    root_upload_dir = res[1]
    game_upload_dir = root_upload_dir % (server_cfg.game_name)
    upload_path = game_upload_dir + "/" +str(server_cfg.run_id / 1800) + "/"
    server_cfg = server_cfg.set_option("upload_path", upload_path)
    return server_cfg

#
# Calls different massaging methods based on the passed argument.
#
def main(server_cfg):
    if not server_cfg:
        print "usage: %s <game name> <dir with uploaded tbz's> <timestamp>" % (
        sys.argv[0])
    # if upload_path is not passed. take it as current timeslot upload directory
    if not hasattr(server_cfg, "upload_path"):
        server_cfg = set_default_upload_path(server_cfg)

    os.chdir(server_cfg.upload_path)

    client_ips = []
    return_value = 0
        
    # check if ip_list has been passed as an argument. If it is passed then take this to segregate
    if hasattr(server_cfg, "ip_list"):
        client_ips = server_cfg.ip_list

    if not client_ips: 
        # List of all client machines that uploaded data
        client_ips = list_dirs(server_cfg)
        # print "===> client list ", client_ips

    # Sort and organize all collected profiles
    profile_list = segregate_profiles(server_cfg, client_ips)
    # print "====> sorted profiles", profile_list
    time_slot = (int)(server_cfg.run_id)/1800

    # clean up slow pages before aggregating all profiles organized above 
    # and push all aggregated profile into one directory - BLOB_DIR
    if server_cfg.aggregate_all:
        profile_list = slow_pages_cleanup(server_cfg, server_cfg.game_name, time_slot, profile_list) 
        # print "====> profile_list",profile_list    
        res = aggregate_all(server_cfg, profile_list.keys())
        # print "=====> aggregate call", res
        # Cleanup temporary dirs, create tbz
        if cleanup_and_bzip(server_cfg, profile_list.keys()):
            return 0

    return return_value 

def usage():
    print "Usage: %s <game name> <path clients upload tbzs to> <timestamp>" % (sys.argv[0])
    return

if __name__ == "__main__":
    status = 37
    try:
        server_cfg = get_server_config(server_config_file)
	server_cfg = parse_and_store_arguments(server_cfg)
        status = main(server_cfg)
    except:
        info = sys.exc_info()
        syslog.syslog(str(info[0]))
        syslog.syslog(traceback.format_exc())
        #print(str(info[0]))
        #print(traceback.format_exc())

        status = 38

    # print sys.argv[0], "exiting with status: ", status
    sys.exit(status)
