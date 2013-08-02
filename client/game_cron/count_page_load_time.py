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

import sys;
import os;
import syslog;
import traceback;
import time;
import csv

#
# Get config manager
import zperfmon_config
from global_preset_paths import global_preset_paths

import timetail

# To look at query parameters
import cgi

APACHE_MON_PID = "/var/run/apache_mon_pid"
ZPERF_APACHE_ACCESS_LOG = "/var/log/httpd/zperfmon.log"

"""
The Pages class implements bucketed collection of page delivery times. Each
page is tracked via the BucketedPDT class. Buckets are 100 - 1000 milliseconds
at 100ms interval and 1000 - 10000 at 1000ms interval. Each bucket stores
total time and count.

If zid information is available in the apache-log, dump per-zid hit counts and
wall times to one file, and a list of zid/auth-hash mapping to another.
"""

import pyjson as json

class BucketedPDT:

    def __init__(self):
        self.init_buckets();

    #
    # Find the correct bucket for given PDT and account for int
    # cumulative counts too
    #
    def add(self, pdt):
        # First update the 'all' bucket
        self.buckets["0"]["count"] += 1
        self.buckets["0"]["time"] += pdt

        if pdt < 1000:
            bucket = int(pdt / 100) * 100
        elif pdt < 10000:
            bucket = int(pdt / 1000) * 1000
        else:
            bucket = 10001

        bucket = str(bucket)

        self.buckets[bucket]["count"] += 1
        self.buckets[bucket]["time"] = round(
                                self.buckets[bucket]["time"] + pdt, 2)

    #
    # The json implementation we are using do not understand non-objects.
    # Rather than contorting the code and design trying to do JSON-ification
    # of this object, we pass the internal hash so the container class can
    # spit out the JSON representation.
    #
    def get(self):
        return self.buckets

    def init_buckets(self):
        self.buckets = {}

        # This hash will be JSON-ified, hence key needs to be strings.
        for key in [str(x) for x in [0] +
                    range(100,1000,100) +
                    range(1000, 10000, 1000) +
                    [10001]]:
            self.buckets[key] = { "count" : 0, "time" : 0}        

    #
    # Cleanup after a value dump, we could cleanup individual elements
    # as well, but there isn't much to gain there -  hence re-create.
    #
    def flush(self):
        self.init_buckets()

#
# Container for BucketedPDT objects keyed by page name
#
class Pages:

    pages = {}

    def __init__(self):
        self.pages = {}
    
    #
    # Maintain a dict for all pages hit, each element knows how to do
    # bucketing for itself.
    #
    def add(self, page, pdt):
        if not self.pages.has_key(page):
            self.pages[page] = BucketedPDT()

        self.pages[page].add(pdt)


    def flush(self):
        self.pages = {}


    #
    # Returns the page-hash containing the bucket-hash of each page. This is
    # so the caller can do the JSON dump of this hash.
    #
    def get(self):
        mirror = {}

        for name, obj in self.pages.items():
            mirror[name] = obj.get()

        return mirror


'''
    Handler class for calculating bucketed page load time for each page
'''
class LogHarvester:

    def __init__(self, log_path = ZPERF_APACHE_ACCESS_LOG, page_type = ".php",
                 collection_interval = 60, dump_interval = 2 * 60):

        self.game_config = zperfmon_config.GameSetup(global_preset_paths)
            
        self.page_type = page_type
        self.collection_interval = collection_interval
        self.dump_interval = dump_interval

        self.pdt_bkt_target = self.game_config.ZPERFMON_APACHE_STAT_FILE
        self.zid_count_target = self.game_config.ZPERFMON_ZID_COUNT_FILE
        self.auth_hash_target = self.game_config.ZPERFMON_AUTH_HASH_FILE
        self.pdt_target = self.game_config.ZPERFMON_PDT_FILE
        self.mem_target = self.game_config.ZPERFMON_MEM_FILE

        # This PDT collection has buckets and needs backward compatibility
        self.m_pdt_bkt = Pages()
        self.staging_pdt_bkt = Pages()

        # zid counts and times
        self.m_zid_dict = {}
        self.staging_zid_dict = {}
        
        # auth hashes
        self.m_auth_hash_dict = {}
        self.staging_auth_hash_dict = {}

        # page response time dictionary
        self.m_pdt = Pages()
        self.staging_pdt = Pages()
        
        # Per-zid mem use and peak
        self.m_mem_dict = {}
        self.staging_mem_dict = {}

        self.log_reader = timetail.TimeTail(log_path, None, self.collection_interval)


    # Read the log periodically, extract page times, zid information
    # and dump into an in-memory cmulative hash. The hash is written
    # to a known file periodically from where a cron job picks it up.
    # The hashes are reset whenever the file is picked up.
    #
    def run(self):

        # This timestamp tells us when dump_interval seconds has expired.
        slot_start = time.time()

        # Get the generator for reading the log
        log_reader = self.log_reader.tail() 

        # and ask it to give us a chunk on every call.
        while True:
            #
            # re-read the configuration in-case URL params or some of
            # the more dynamic parameters has changed
            #
            try:
                self.game_config.refresh()
                self.params_configured = self.game_config.ZPERFMON_URL_PARAMS.split(",")
            except:
                self.params_configured = None

            #
            # Now get tail from the apache custom log, the generator
            # never terminates
            #
            block = log_reader.next()

            if block:
                for line in block.split("\n"):
                    self.process_line(line)

            if (time.time() - slot_start) > (self.dump_interval):
                try:
                    self.dump_pdt_bkt()
                    self.dump_zid_count()
                    self.dump_auth_hash()
                    self.dump_pdt()
                    self.dump_mem()
                except:
                    info = sys.exc_info()
                    syslog.syslog(str(info[0]))
                    syslog.syslog(traceback.format_exc())

                slot_start = time.time()

    #
    # - If PDT dump file exists overwrite it with latest PDT data.
    #           - flush staging-PDT dict
    # - If PDT file does not exist
    #           - throw away current aggregate,
    #           - promote staging-PDT data as running aggregate
    #           - dump PDT data to file.
    #
    # The magic string, "BUCKETED", is prepended to PDT data. This allows
    # server to handle unbucketed old-style PDT data and bucketed PDTs.
    #
    def dump_pdt_bkt(self):
        if not os.path.exists(self.pdt_bkt_target):
            self.m_pdt_bkt = self.staging_pdt_bkt
            self.staging_pdt_bkt = Pages()
        else:
            self.staging_pdt_bkt.flush()

        pdt_bkt_file = open(self.pdt_bkt_target, "w")

        # Magic string to tell server we are delivering bucketed PDT
        pdt_bkt_file.write("BUCKETED");

        x = self.m_pdt_bkt.get()
        pdt_bkt_file.write(json.write(x))
        
        pdt_bkt_file.close()


    def dump_zid_count(self):
        if not os.path.exists(self.zid_count_target):
            self.m_zid_dict = self.staging_zid_dict

        self.staging_zid_dict = {}

        old_mask = os.umask(0)
        zid_file = open(self.zid_count_target, "w", 0777)
        os.umask(old_mask)
    
        zid_file_handle = csv.writer(zid_file)

        for zid in self.m_zid_dict.iterkeys():
            zid_file_handle.writerow([zid] + self.m_zid_dict[zid])
        zid_file.close()


    def dump_auth_hash(self):
        if not os.path.exists(self.auth_hash_target):
            self.m_auth_hash_dict = self.staging_auth_hash_dict

        self.staging_auth_hash_dict = {}

        old_mask = os.umask(0)
        auth_hash_file = open(self.auth_hash_target, "w", 0777)
        os.umask(old_mask)
    
        auth_file_handle = csv.writer(auth_hash_file)

        for zid in self.m_auth_hash_dict.iterkeys():
            auth_file_handle.writerow([zid] + list(self.m_auth_hash_dict[zid]))
        auth_hash_file.close()


    def dump_pdt(self):
        if not os.path.exists(self.pdt_target):
            self.m_pdt = self.staging_pdt
            self.staging_pdt = Pages()
        else:
            self.staging_pdt.flush()

        pdt_file = open(self.pdt_target, "w")
        pdt_file.write(json.write(self.m_pdt.get()))
        
        pdt_file.close()


    def dump_mem(self):
        if not os.path.exists(self.mem_target):
            self.m_mem_dict = self.staging_mem_dict
        self.m_mem_dict = self.staging_mem_dict

        self.staging_mem_dict = {}

        old_mask = os.umask(0)
        mem_file = open(self.mem_target, "w", 0777)
        os.umask(old_mask)
    
        mem_file_handle = csv.writer(mem_file)

        for zid in self.m_mem_dict.iterkeys():
            mem_file_handle.writerow([zid] + self.m_mem_dict[zid])
        mem_file.close()

    #
    # Extract the URL parameters configured from query string and update the page
    #
    def prefix_page_name(self, page_name, query_string):

        # Query string can be empty. {%q} = The query string (prepended with
        # a '?' if a query string exists, otherwise an empty string)
        if not query_string or len(query_string) <= 1 or not self.params_configured:
            return page_name

        # Strip out the pre-pended ?. cgi.parse_qs doesn't like this
        query_string = query_string[1:]

        try:
            # Parse and get the query params dictionary
            params_dict = cgi.parse_qs(query_string)

        except Exception:
            # Some problem in parsing the query string. Shouldn't happen. In
            # the unfortunate case, we have no other option but returning
            # original page name
            return page_name

        # Generate unique page name from all those parameters
        for name in self.params_configured:
            if params_dict.has_key(name) and params_dict[name]:
                page_name = "".join(params_dict[name]) + "^" + page_name

        return page_name


    def process_line(self, s):
        try:
            # REQ_typeurlpage_deliver_timetimestampstatus_codeoriginal_urlquery_paramspparamzidhshmpkmuse
            #
            parts = s.split("");

            if len(parts) != 12:
                return

            req_type,serve_url,pdt,ts,status,hiturl,query,pparam,zid,hsh,mpk,muse = parts

            if (not req_type in ['GET', 'POST'] or not int(pdt) > 0 or
                not serve_url or status != "200"):
                return

            #
            # serve_url is the complete path from docroot. We take
            # only the filename. This can lead to duplication of files
            # from different directories. Final fix should be to take
            # the complete path stripped of docroot.
            #
            page_name = os.path.basename(serve_url)
            if (not page_name or not page_name.endswith(self.page_type) or
                page_name.find("zynga_checkme") != -1):
                return

            #
            # Tag the page name with configured URL parameters
            # appearing in this request's query parameters or page-parameter
            #
            if query and self.params_configured:
                page_name = self.prefix_page_name(page_name, query)

            if pparam and pparam != "-":
                page_name = pparam + "^" + page_name

            page_time = round(float(pdt)/1000, 3) # us to ms

            self.m_pdt_bkt.add(page_name, page_time)
            self.staging_pdt_bkt.add(page_name, page_time)

            # '7' is a special zid for when request didn't log a zid 
            if not zid or zid == "-":
                zid = "7"

            if self.m_zid_dict.has_key(zid):
                self.m_zid_dict[zid][0] += 1
                self.m_zid_dict[zid][1] += page_time
            else:
                self.m_zid_dict[zid] = [1, page_time]

            if self.staging_zid_dict.has_key(zid):
                self.staging_zid_dict[zid][0] += 1
                self.staging_zid_dict[zid][1] += page_time
            else:
                self.staging_zid_dict[zid] = [1, page_time]

            # auth hashes for a zid are kept in a set.
            if hsh and zid and not hsh == "-" and not zid == "_":
                if self.m_auth_hash_dict.has_key(zid):
                    self.m_auth_hash_dict[zid].add(hsh)
                else:
                    self.m_auth_hash_dict[zid] = set([hsh])

                if self.staging_auth_hash_dict.has_key(zid):
                    self.staging_auth_hash_dict[zid].add(hsh)
                else:
                    self.staging_auth_hash_dict[zid] = set([hsh])

            # Page time tracking
            self.m_pdt.add(page_name, page_time)
            self.staging_pdt.add(page_name, page_time)

            if mpk.isdigit(): mpk = int(mpk)
            else: mpk = 0

            if muse.isdigit(): muse = int(muse)
            else: muse = 0
            
            # Per zid memory tracking
            if self.m_mem_dict.has_key(zid):
                self.m_mem_dict[zid][0] += 1
                self.m_mem_dict[zid][1] += mpk
                self.m_mem_dict[zid][2] += muse
            else:
                self.m_mem_dict[zid] = [1, mpk, muse]

            if self.staging_mem_dict.has_key(zid):
                self.staging_mem_dict[zid][0] += 1
                self.staging_mem_dict[zid][1] += mpk
                self.staging_mem_dict[zid][2] += muse
            else:
                self.staging_mem_dict[zid] =  [1, mpk, muse]
        except:
            info = sys.exc_info()
            syslog.syslog(str(info[0]))
            syslog.syslog(traceback.format_exc())


    def description(self):
        return "Display the pages with their load time"


def test():
    b = Pages()

    import random

    pnames = [ "one", "two", "three", "four", "five"]
    p = 0
    for i in range(100):
        pdt = random.randrange(10,11000)
        print pdt, pnames[p]
        b.add(pnames[p], pdt)
        p = i % len(pnames)

    # print str(b)
    print repr(b)


def try_daemonize():
    # If lock file is present and if process which created it is alive
    # don't bother becoming a daemon, exit.
    if os.path.exists(APACHE_MON_PID):
        try:
            pid = int(open(APACHE_MON_PID).read())
            if os.kill(pid, 0) == 0:
                return 1
        except:
            pass
            
    if os.fork() > 0:
        return 2

    os.chdir("/")
    os.umask(0)
    os.setsid()

    try:
        open(APACHE_MON_PID, "w").write(str(os.getpid()))
    except:
        syslog.syslog("count_page_load_time::Error dumping the pid") 
        return 1
    return 0


def main():

    if try_daemonize() > 0:
        return 3

    # Collect every minute and dump every 2 minutes
    c = 1 * 60
    d = 2 * 60

    if len(sys.argv) > 1:
        c = int(sys.argv[1])

    if len(sys.argv) > 2:
        d = int(sys.argv[2])

    log_tail = LogHarvester(collection_interval = c, dump_interval = d)
    log_tail.run()


if __name__ == "__main__":
    # test()
    try:
        main()
    except:
        info = sys.exc_info()
        syslog.syslog(str(info[0]))
        syslog.syslog(traceback.format_exc())
