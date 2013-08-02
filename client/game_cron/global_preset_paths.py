"""
Global configuration file
"""

global_preset_paths = {
    "ZPERFMON_CONFIG_PATH"            : "/var/run/zperfmon/zperfmon-conf",
    "ZPERFMON_TBZ_TARGET_PATH"        : "/var/opt/zperfmon/xhprof_tbz",
    "ZPERFMON_XHPROF_CONFIG_FILE"     : "/etc/php.d/xhprof.ini",
    "ZPERFMON_APACHE_STAT_FILE"       : "/var/log/zperfmon/apache-page.stats",
    "ZPERFMON_APACHE_MON_PID"         : "/var/run/apache_mon_pid",
    "ZPERFMON_ZID_COUNT_FILE"         : "/var/log/zperfmon/zidstuff.csv",
    "ZPERFMON_AUTH_HASH_FILE"         : "/var/log/zperfmon/auth_hash.csv",
    "ZPERFMON_PDT_FILE"               : "/var/log/zperfmon/pdt.json",
    "ZPERFMON_MEM_FILE"               : "/var/log/zperfmon/zidmem.csv",

    #
    # URL parameter extraction
    #
    "ZPERFMON_URL_PARAMS"             : "",
    "ZRUNTIME_LIVE_REV"               : "/var/tmp/prod-liveRev",
    "ZPERFMON_INI_FILE"               : "/etc/zperfmon/zperfmon.ini",
    "ZPERFMON_CONF_URL"                : "http:/xxxxx/zperfmon/get_ini.php",
    "ZPERFMON_CONF_URL_EC2"           : "http://xxxxx/zperfmon/get_ini.php"
    }
