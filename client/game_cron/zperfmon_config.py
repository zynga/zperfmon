#!/usr/bin/python

"""
  Configuration Manager for zperfmon game clients.
  Currently used by perfaggregate.py and count_page_load_time.py
"""

import re
import os

#
# Do nothing class for trapping clean exit paths, were we needn't log
# errors or stack of the resulting exception
#
class CleanExit(Exception):
    pass

class GameSetup:
    
    def __init__(self, preset_paths):

        # Load base configs
        self.set_base_params(preset_paths)

        # Last conf-file read time
        self.last_modification = -1

        # Load params delivered via zrt
        self.read_zrt_config()

    # Pickup configuration from built-ins
    def set_base_params(self, preset_paths):
        for key, value in preset_paths.items():
             setattr(self, key, value)

    # Read ZRT config and set corresponding parameters
    def read_zrt_config(self):

        try:
            zrt_config = open(self.ZPERFMON_CONFIG_PATH).read()
        except:
            #
            # If zperfmon.inc.php did not dump the config pulled from
            # zRT, we are most likely not profiling on this machine. We
            # will do a clean exit.
            #
            raise CleanExit()

        # The file is in this format:
        #       key1:=value1,key2:=value2,key3:=......
        #
        values = [re.split(":=", x) for x in zrt_config.split("\n") if x]

        # set the attribute in the class corresponding to key
        # The consumers will use it directly as 'conf.key => value'

        for key, value in values:
            setattr(self, key, value)

        self.last_modification = os.path.getmtime(self.ZPERFMON_CONFIG_PATH)

    #
    # Check and re-read the configuration dumped by zperfmon.inc.php
    def refresh(self):
        if (os.path.exists(self.ZPERFMON_CONFIG_PATH) and
            self.last_modification != os.path.getmtime(self.ZPERFMON_CONFIG_PATH)):
            #
            # Re-read the configuration, it was dumped again
            #
            self.read_zrt_config()
