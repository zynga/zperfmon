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
# Reads one or more bucketed PDT time files and prints it out in a
# format readable by most humans
#
# bphilip@zynga.com
#

import sys

def print_page(page, buckets, detail=False):
    if not detail:
        c = buckets['0']['count']
        if not c: c = 0.0000001
        t = buckets['0']['time']
        print "%s,%i,%i,%i" % (page,c,t,t/c)
        return

    head = "-" * 10 + page + "-" * 10
    print head
#    print "-" * len(head)

    bkts = [int(x) for x in buckets.keys()]
    bkts.sort()
    for b in [str(x) for x in bkts][:]:
        entry = buckets[b]
        c = int(entry["count"])
        if not c: c = 0.000001
        t = int(entry["time"])
        print "%8s %8i %12i %8i" % (b, c, t, round(t/c, 3))
    print


def print_pages(buckets, page_list, detail=False):
    if not page_list:
        page_list = buckets.keys()

    if type(page_list) != list:
        page_list = [page_list]
    for page in page_list:
        print_page(page, buckets[page], detail)


def load_apache_stats(file_list):
    buckets = {}
    stat_count = 0

    for fname in file_list:
        stat_count += 1
        # print "Opening", fname
        buf = open(fname).read()
        if buf[0] != "{":
            buf = buf[8:]
        stats = eval(buf)

        for page in stats.keys():
            merge(buckets, stats, page)

    print "opened %d stat files" % stat_count
    return buckets


def merge(buckets, page_data, page_name):

    page = page_data[page_name]
    if not buckets.has_key(page_name):
        buckets[page_name] = page
        return
    
    for x in buckets[page_name].keys():
        buckets[page_name][x]['time'] += page[x]['time']
        buckets[page_name][x]['count'] += page[x]['count']

    return

import getopt

def main():

    opts, file_list = getopt.getopt(sys.argv[1:], "p:d")

    pages = None
    detail = False
    
    for i,j in opts:
        if i == "-p":
            pages = j
        if i == "-d":
            detail = True
        
    buckets = load_apache_stats(file_list)
    print_pages(buckets, pages, detail)


if __name__ == '__main__':
    main()
