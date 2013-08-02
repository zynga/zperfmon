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

import os
import time


class TimeTail:
    # File descriptor for file last open
    fd = -1

    # Inode for fd
    inode = None

    # File we are reading from
    name = ""

    # offset till where we read
    last_read = 0

    # How long to sleep in seconds between reads
    sleep_time = 60

    # Function to call after each wakeup
    data_sink = None

    def default_action(self, data):
        for line in data.split("\n"):
            print line
    

    def __init__(self, name, data_sink="default", sleep_time=60):
        self.name = name

        if data_sink == 'default':
            self.data_sink = self.default_action
        else:
            self.data_sink = data_sink

        # get fd for 'name'
        if self.open_file() >= 0:
            # We get to the end of file at start, old data is stale data
            self.last_read = self.fd.seek(0, 2)
            self.sleep_time = sleep_time


    def open_file(self):
        try:
            self.fd = open(self.name)
            self.inode = os.fstat(self.fd.fileno()).st_ino
        except:
            self.fd = None
            self.inode = -1

        return self.inode

    #
    # 1. sleep 'x' seconds
    # 2. on wakeup read till end of file from stored fd, from
    #    last_read offset
    # 
    # 3. compare inodes of fd and file fd was got from
    #    if they are different read till end of new file, save new fd
    # 4. save read offset in either case
    #
    # 5. if call-back defined call it with read data else yield it
    #
    def tail(self):

        while True:
            # 1.
            time.sleep(self.sleep_time)

            # 2.
            # Ideally we should be seeking to self.last_read, but why?
            # Only we use fd. Asking fd to track itself is perfectly fine.
            data = ""
            if self.fd > 0:
                data = self.fd.read()

            # 3.
            if (self.fd < 0 or
                not os.path.exists(self.name) or
                self.inode != os.stat(self.name).st_ino):
                if self.open_file() >= 0:
                    data += self.fd.read()
                else:
                    continue
                
            # 4.
            self.last_read = self.fd.tell()
                
            # 5.
            if self.data_sink:
                self.data_sink(data)
            else:
                yield data


if __name__ == "__main__":
    test = TimeTail("testdata", sleep_time=5)
    test.tail()
