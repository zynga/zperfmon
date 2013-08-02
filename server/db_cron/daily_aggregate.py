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

import sys,os,re,syslog,traceback,time
from os import path
from glob import glob
import commands
from itertools import groupby
import shutil
import json

server_config_file = "/etc/zperfmon/server.cfg"
daily_raw_dir = "_raw"
# holder class for putting in config parameters
class CFG:

    def set_option(self, option, value):
        setattr(self, option, value)

    pass

def debug_print(*args):
    return
    #print(args)

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


page_re = re.compile('.*_raw/[0-9]+/(?P<runid>[0-9]*)\.(?P<filename>.*)\.xhprof')

def pagename(k):
    m = page_re.match(k)
    if(m):
        return m.group('filename')

def collect_profiles(cfg, rawpath):
    xhprof_files = glob("%s/*/*.xhprof" % rawpath) 
    groups = {}
    for f in xhprof_files:
        k = pagename(f)
        groups.setdefault(k,[])
        groups[k].append(f)
    return groups    



#
# Find all manifest.json files one level under 'source' and combine them. Dump
# result as json 'target'/manifest.json. Manifests are loaded with an eval()
# since the pure python json[encode|decode] (for 2.4) is very slow.
#
def aggregate_manifests(source, target):

    aggregate = {}
    for manifest in glob(path.join(source, "*", "manifest.json")):
        try:
            m = json.read(open(manifest).read())

            #
            # Very simplistic, we could use collections and sets and all
            # that. Not enough gain here to justify the trouble.
            #
            for page, count in [[k, v[1]] for k,v in m.items()]:
                if not aggregate.has_key(page):
                    aggregate[page] = [page, 0]

                aggregate[page][1] += count
        except Exception, e:
            info = sys.exc_info()
            syslog.syslog(str(info[0]))
            syslog.syslog(traceback.format_exc())

    agg_file = path.join(target, "manifest.json")
    open(agg_file, "w").write(json.write(aggregate))
    return agg_file


# look backwards from timestamp's half hour to num elements back
# num is 48 by default, because it's in 1/2 hour slots
# root_upload_dir=/db/zperfmon/<game_name>/timeslots/
#
def extract_profiles(cfg, root_upload_dir, timestamp, num=48):
    
    end = int(timestamp / 1800)
    start = end - 48
    slots = range(start, end)

    files = map(lambda x: path.join(root_upload_dir,str(x),"xhprof",cfg.xhprof_tbz_name), slots);

    aggregate_dir = path.normpath(path.join(root_upload_dir,'..','xhprof.daily', str(end), cfg.blob_dir))

    rawpath = path.normpath(path.join(root_upload_dir,'..','xhprof.daily', str(end), daily_raw_dir))

    if(not path.exists(rawpath)):
        os.makedirs(rawpath)

    if(not path.exists(aggregate_dir)):
        os.makedirs(aggregate_dir)

    count = 0
    for f in files:
    	os.makedirs("%s/%d" % (rawpath, count))
       	cmd = "tar --strip-components 1 -xjf %s -C %s/%d" % (f, rawpath, count)
        result = commands.getstatusoutput(cmd)
        if(result[0]):
            print "Command failed: %s" % cmd
            print "Ignoring error and continuing"
	count += 1
    
    aggregate_manifests(rawpath, aggregate_dir)
    return (aggregate_dir, end, collect_profiles(cfg, rawpath))

def aggregate_runs(cfg, name, aggregate_dir, xhprofs):
    cmd = "%s %s %s %s %s" % (cfg.profile_aggregation_command, cfg.game_name, name, aggregate_dir, " ".join(xhprofs))
    result = commands.getstatusoutput(cmd)
    if(result[0]):
        print "Command failed: %s" % cmd

def extract_functions(cfg, name, aggregate_dir, xhprofs):
    cmd = "%s %s %s %s %s" % (cfg.profile_extraction_command, cfg.game_name, name, aggregate_dir, " ".join(xhprofs))
    result = commands.getstatusoutput(cmd)
    if(result[0]):
        print "Command failed: %s" % cmd


def cleanup_and_bzip(server_cfg, exec_dir):
    # create one tbz for inserting

    cwd = os.getcwd()
    os.chdir(exec_dir)
    
    #
    # Remove the raw directory
    #
    shutil.rmtree(daily_raw_dir)

    #
    # bzip to insert 
    #
    cmd = "tar jcf %s %s/" % (server_cfg.xhprof_tbz_name, server_cfg.blob_dir)
    print cmd
    result = commands.getstatusoutput(cmd)
    debug_print(cmd)
    
    os.chdir(cwd)
    # ignore failures, recovery and sanity is not worth the returns
    if result[0]:
        return None
    

def usage():
    print "error !"
    
def main(cfg):
    args = sys.argv[1:]
    if(len(args) < 2 or len(args) > 3):
        usage()
        return
    game_name = args[0]
    # xhprof_dir = args[1]
    root_upload_dir = args[1]
    if(len(args) == 3):
        timestamp = int(args[2])
    else:
        timestamp = int(time.time())
    
    cfg.set_option("game_name", game_name)

    # (aggregate_dir, day, profile_slots) = extract_profiles(cfg, xhprof_dir, timestamp)
    (aggregate_dir, end, profile_slots) = extract_profiles(cfg, root_upload_dir, timestamp)
    for name in profile_slots.keys():
        aggregate_runs(cfg, "%s.%s" % (end,name), aggregate_dir, profile_slots[name])
        # TODO: optimize this to generate off the aggregate file 
        extract_functions(cfg, "%s.%s" % (end, name), aggregate_dir, profile_slots[name])

    cleanup_and_bzip(cfg, path.normpath(path.join(aggregate_dir, "..")))

if __name__ == "__main__":
    status = 37
    try:
        server_cfg = get_server_config(server_config_file)
        status = main(server_cfg)
    except:
        info = sys.exc_info()
        syslog.syslog(str(info[0]))
        syslog.syslog(traceback.format_exc())
        status = 38
        print traceback.format_exc()
    sys.exit(status)
