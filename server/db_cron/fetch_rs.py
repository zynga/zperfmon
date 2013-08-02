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
# This is used to populate the instances table in rightscale database.
# We collect data from both EC2 and zCloud.
# The items filled are (cloud_id, deploy_id, deploy_name, array_id, array_name, name, hostgroup, type, state, price, ip, pr_ip, awsid, birth)
#
# EC2 Instances
# We fetch the depolyments, fetch the arrays, then iterate through the instances of deployments and arrays and fill the instances table
#
# zCloud Instances
# We fetch the deployments, fetch the arrays, fetch the templates, fetch the instances. 
# We use deployment, array and template fetches to create a correct mapping for fields (ArrayID, DeployID, Type) to be filled in instances table
#

import parallelcurl
import commands
import syslog
import sys
import getopt
import re

import fileinput
import os.path

import traceback
    
from pprint import pprint

import ConfigParser

import time

# To get pycurl and MySQL-python package is:
# yum install MySQL-python.x86_64
import MySQLdb

# yum install pycurl
import pycurl

import math

# To eval() json strings
null = None
true = True
false = False

debug = True

"""
Fetches right scale instance data in json format and insert it into an
un-normalized manner into a mysql table.

Each row is of the form below:

[timestamp, cloud-id, deploy_id, deploy_name, array_id, array_name,
 hostname, type, status, pricing, public_ip, private_ip, aws_id, birthtime ]

"""
#
# Used to manage DB config
#
class Config:

    def __init__(self):
        #
        conf = ConfigParser.ConfigParser()

        # We want case insensitive options
        conf.optionxform = str

        res = commands.getstatusoutput("/usr/local/zperfmon/bin/get_conf_vars.php rs_conf_file")
        if res[0]:
            raise "Failed to read server configuration"

        conf.read(res[1])
        for k,v in conf.items("DB"):
            setattr(self, k, v)

    def __str__(self):
        t = ""
        for k in [x for x in dir(self) if not x.startswith("__")]:
             t += str(k) + ":" + str(getattr(self, k)) + "\n"
        return t

#
# Used to connect to DB. 
#
class DB:
    def __init__(self, cfg=None):
        if not cfg:
            self.cfg = cfg
        else:
            self.cfg =Config()

        self.db = MySQLdb.connect(host=cfg.host,
                                  user=cfg.user,
                                  passwd=cfg.password,
                                  db=cfg.database)

        self.cursor = self.db.cursor()

    def querymulti(self, query, rows):
        r = self.cursor.executemany(query, rows)
        self.db.commit()
        return r

    def query(self, query):
        r = self.cursor.execute(query)
        self.db.commit()
        return self.cursor.fetchall()

    def create_tmp_table(self, table):
        tmp_table = table + str(time.time()).replace(".", "_")

        q = "CREATE TABLE `%s` LIKE `%s`" % (tmp_table, table)

        print "Create query:", q
        self.query(q)

        return tmp_table
   
    def copy_rows(self, table_new, table_old):
        copy = "INSERT INTO %(new)s SELECT * FROM %(old)s" % \
                {"new" : table_new , "old" : table_old}
	self.query(copy)	

    def create_clone(self, table):
	tmp_table = table + str(time.time()).replace(".", "_")
	q = "CREATE TEMPORARY TABLE %s LIKE %s" % (tmp_table, table)

	print "Creating a temporary table", q
	self.query(q)

        swap = "INSERT INTO %(new)s SELECT * FROM instances" % \
                {"new" : tmp_table}

        print "Tranferring instances to the tmp table: ", swap
        self.query(swap)

	return tmp_table

    def drop_rows(self, table, deploy_id, array_id):
        if array_id == -1 :
                q = "DELETE FROM %s WHERE deploy_id = %s" % (table, str(deploy_id))	
	elif deploy_id == 0 :
		q = "DELETE FROM %s WHERE array_id = %s" % (table, str(array_id))
	else:
		q = "DELETE FROM %s WHERE deploy_id = %s AND array_id = %s" % (table, str(deploy_id), str(array_id))
	#print "Dropping all the deployments of deploy_id ", deploy_id
	self.query(q)

    #
    # returns the number of instances for the given deploy_id and arrya_id
    # if deploy_id = 0, we are interested in instance count for a particular array
    #
    def get_size(self, table, deploy_id, array_id):
	if deploy_id == 0 :
		q = "SELECT COUNT(*) FROM %s WHERE array_id = %s" % (table, str(array_id))
	elif array_id == -1 :
		q = "SELECT COUNT(*) FROM %s WHERE deploy_id = %s" % (table, str(deploy_id))
	else :
		q = "SELECT COUNT(*) FROM %s WHERE deploy_id = %s AND array_id = %s" % (table, str(deploy_id), str(array_id))
	return self.query(q)

    def drop_table(self, table):
        drop = "DROP TABLE IF EXISTS %s" % table

        print "Drop query:", drop
        self.query(drop)

    def swap_drop_table(self, table, tmp_table):
        t = "__" + str(time.time()).replace(".", "_") + "__"
        swap = "RENAME TABLE %(cur)s TO %(tmp)s, %(new)s to %(cur)s" % \
               { "cur" : table, "new" : tmp_table, "tmp" : t}
        print "Swap query:", swap
        self.query(swap)

        self.drop_table(t)

#
# Used to collect data from zcloud. It uses rightscale api 1.5
#
class Instance_RS15:

    # Common zCloud API urls 
    DEPLOY_URL = "https://xxxx.rightscale.com/api/deployments/"
    ARRAY_URL = "https://xxxx.rightscale.com/api/server_arrays/"
    SESSION_URL = "https://xxxx.rightscale.com/api/session"
    INSTANCES_EXTENDED_URL = "https://xxxx.rightscale.com/api/clouds/858/instances?view=extended"
    CLOUD_IMAGES = "https://xxxx.rightscale.com/api/multi_cloud_images/"
    CLOUD_URL = "https://xxxx.rightscale.com/api/clouds"
    COOKIE = '/var/tmp/login.cookie'

    # Prefix for array, deploy and template fetch
    ARRAY_PREFIX = 19
    DEPLOY_PREFIX = 53
    TEMPLATE_PREFIX = 62

    def tolerance(self, new, old):
        if ( math.fabs((new-old)/float(old))*100 > 85 ) and math.fabs(new - old) > 5 :
                return false
        return true


    def deploy_tolerance(self, new, old, deploy_id):
        if not self.tolerance(new, old) :
                if str(deploy_id) not in (open("/var/tmp/tax/failed_tolerance_deployments_zCloud.txt", "r").read()):
                        return false
        return true

    # a single template image (image_json) is saved    
    def on_one_template_fetch(self, image_json, image_href, ch, server_template):
        try:
            if debug and ch: open("/var/tmp/tax/image_" +server_template+ ".json", "w").write(image_json)
            content = eval(image_json)
        except:
            print "Skipping template", image_href
            return
        self.rs["images"][server_template] = content['name']

        print "Got image ", len(image_json), "bytes long"

    # a single array (array_json) is saved
    def on_one_array_fetch(self, array_json, array_href, ch, array_id):
        try:
            if debug and ch: open("/var/tmp/tax/array_" + str(array_id) + ".json", "w").write(array_json)
            content = eval(array_json)
        except:
            print "Skipping array", array_href
            return
        self.rs["arrays"][array_id] = content['name']

        print "Got array ", array_id, len(array_json), "bytes long", array_href

    # a single deployment (deployment_json) is saved
    def on_one_deployment_fetch(self, deployment_json, deployment_href, ch, deployment_id):
        try:
            if debug and ch: open("/var/tmp/tax/deploy_" + str(deployment_id) + ".json", "w").write(deployment_json)
            content = eval(deployment_json)
        except:
            print "Skipping deployment", deployment_href
            return
        self.rs["deployments"][deployment_id] = content['name']

        print "Got deployment_json ", deployment_id, len(deployment_json), "bytes long", deployment_href

    # a list of template images (images_json) is saved 
    def on_all_images_fetch(self, images_json, images_href, ch, data):
        try:
            if debug and ch: open("/var/tmp/tax/all_images" + ".json", "w").write(images_json)
            content = eval(images_json)
        except:
            print "Skipping images", array_href
            return
        for image in content:
            image_id = image['links'][0]['href'][24:]
            self.rs["images"][image_id] = image['name']
        print "Got images ", len(images_json), "bytes long"

    # a list of arrays (array_json) is saved
    def on_all_array_fetch(self, array_json, array_href, ch, data):
        try:
            if debug and ch: open("/var/tmp/tax/all_arrays" + ".json", "w").write(array_json)
            content = eval(array_json)
        except:
            print "Skipping arrays", array_href
            return

        for arr in content:
                array_id = arr['links'][0]['href'][19:]
                self.rs["arrays"][array_id] = arr['name']

        print "Got array ", len(array_json), "bytes long", array_href

    # a list of deployments (deployment_json) is saved          
    def on_all_deployment_fetch(self, deployment_json, deployment_href, ch, deployment_id):
        try:
            if debug and ch: open("/var/tmp/tax/all_deployments" + ".json", "w").write(deployment_json)
            content = eval(deployment_json)
        except:
            print "Skipping deployment", deployment_href
            return

        for dep in content:
                deployment_id = dep['links'][0]['href'][17:]
                self.rs["deployments"][deployment_id] = dep['name']

        print "Got deployment_json ", deployment_id, len(deployment_json), "bytes long", deployment_href

    def on_cloud_fetch(self, cloud_json, cloud_href, ch, data):
        try:
            if debug and ch: open("/var/tmp/tax/clouds.json", "w").write(cloud_json)
            content = eval(cloud_json)
        except:
            print "Skipping cloud fetch", cloud_href
	    return
	
	self.cloud_id = content[0]['links'][0]['href'][12:]
	
	# /api/clouds/%s/instances
	cloud_url = content[0]['links'][4]['href']
        self.INSTANCES_EXTENDED_URL = 'https://xxxx.rightscale.com' + content[0]['links'][4]['href'] + "?view=extended"
        print "Got cloud_json ", len(cloud_json), "bytes long", cloud_href

    def on_deploy_fetch(self, deployment_json, deployment_href, ch, data):
	deployment_id = 0
	try:
	    deployment_id = data.split("/")[-1]
            if debug and ch: open("/var/tmp/tax/deploy_zcloud_" + str(deployment_id) + ".json", "w").write(deployment_json)	
	    content = eval(deployment_json)
            deploy_size = self.db.get_size(self.table, deployment_id, -1)
            deploy_size = deploy_size[0][0]
            new_deploy_size = len(content) - deployment_json.count('inactive')
            if new_deploy_size == 0 and deploy_size == 0 :
                print "zCloud: Deployment " + str(deployment_id) + " has no instances or all inactive instances"
		open("/var/tmp/fetch_rs_log", "a").write( \
			self.time + " zCloud: Deployment " + str(deployment_id) + " has no instances or all inactive instances\n")
            elif deploy_size == 0 :
                print "zCLoud: New Deployment came up with deployment_id " + str(deployment_id) + " with " + str(new_deploy_size) + " instances"
		open("/var/tmp/fetch_rs_log", "a").write( \
			self.time + " zCLoud: New Deployment came up with deployment_id " + str(deployment_id) + " with " + str(new_deploy_size) + " instances\n")
            elif len(content) == 0 :
                print "zCloud: Deployment " + str(deployment_id) + " has 0 instances now"
		open("/var/tmp/fetch_rs_log", "a").write( \
			self.time + " zCloud: Deployment " + str(deployment_id) + " has 0 instances now\n")
            elif( not self.deploy_tolerance(new_deploy_size, deploy_size, deployment_id) ):
                print "zCloud: Deployment " + str(deployment_id) + "'s instance count has changed drastically from " + str(deploy_size) + " to " + str(new_deploy_size)
		open("/var/tmp/fetch_rs_log", "a").write( \
			self.time + " zCloud: Deployment " + str(deployment_id) + "'s instance count has changed drastically from " + str(deploy_size) + " to " + str(new_deploy_size) + "\n")
                self.failed_deployments = self.failed_deployments + str(deployment_id) + "\n"
                return

	except:
	    print "Skipping deployment fetch for ", deployment_href
	    print deployment_json
	    return

	self.rs["instances"][deployment_id] = content
        print "Got deployment_json ", deployment_id, len(deployment_json), "bytes long", deployment_href

        self.db.drop_rows(self.table, deployment_id, -1)		
	

    def __init__(self):
        
	cmd = '/usr/local/zperfmon/bin/get_conf_vars.php -s clouds'
        result = commands.getstatusoutput(cmd)
        
	self.accounts = result[1].split(' ')
	# Assoc array of all objects corresponding to right-scale data

        self.failed_deployments = ""

        #self.accounts = [22328, 30287]
        self.types = {}
        self.status = {}

        self.rejects = open("/var/tmp/tax/instances.rejects", "w")
        self.cfg = Config() # To get the configured deployment ids if db fetch for deploiy_id is failed

    def run(self, time, db, table):

        self.time = time
        self.db = db
        self.table = table
	self.cloud_id = 0

        for account in self.accounts:
            commands.getstatusoutput("rm "+self.COOKIE)
            self.rs = { "arrays": {},
                        "deployments" : {},
                        "images" : {},
                        "instances" : {}}

            handle = pycurl.Curl()
            handle.setopt(handle.URL, self.SESSION_URL)
            handle.setopt(handle.COOKIEFILE, self.COOKIE)
            handle.setopt(handle.COOKIEJAR, self.COOKIE)
            handle.setopt(handle.CUSTOMREQUEST, 'POST')
            handle.setopt(handle.CONNECTTIMEOUT, 0)
            handle.setopt(handle.TIMEOUT, 0)
            handle.setopt(handle.FAILONERROR, 1)
            handle.setopt(handle.HTTPHEADER, ['X-API-VERSION:1.5'])
            handle.setopt(handle.POSTFIELDS, 'account_href=/api/accounts/'+str(account)+'&email=xxx@xxxx.xxx&password=veryclear')
            handle.setopt(handle.HEADER, 1)
            handle.perform()
            handle.close()

            opts = {
                 pycurl.HTTPHEADER : ["X-API-VERSION: 1.5"],
                 pycurl.COOKIEFILE : self.COOKIE,
                 pycurl.CONNECTTIMEOUT : 120,
                 pycurl.TIMEOUT : 1000
                }

	    self.ch = parallelcurl.ParallelCurl( 20, opts)

	    # Instance extraction and population is after all fetches are through.
	    self.ch.startrequest(self.CLOUD_URL, self.on_cloud_fetch, None)
	    self.ch.startrequest(self.DEPLOY_URL, self.on_all_deployment_fetch, None)
	    self.ch.startrequest(self.ARRAY_URL, self.on_all_array_fetch, None)
	    self.ch.startrequest(self.CLOUD_IMAGES, self.on_all_images_fetch, None)
	    # Wait till everything is fetched
	    self.ch.finishallrequests()

	    if len(self.rs["deployments"]) == 0 or len(self.rs["arrays"]) == 0 or len(self.rs["images"]) == 0 :
		return
	    # Fetch instances
	    self.fetch_all_deployments()

	    # Make sure instances fetch is complete
	    self.ch.finishallrequests()

	    self.clear_dumped_deployments()

            commands.getstatusoutput("rm " + self.COOKIE)

	    self.dump_all_instances()
	
	open("/var/tmp/tax/failed_tolerance_deployments_zCloud.txt","w").write(self.failed_deployments)

    def clear_dumped_deployments(self):
        query = "SELECT DISTINCT deploy_id FROM instances WHERE cloud_id = %s" % (self.cloud_id)
        deployments_old = self.db.query(query)
        deployments_new = self.rs["deployments"]

	deployments_new = []
	for d in self.rs["deployments"]:
		deployments_new.append(d)

        for dep in deployments_old:
                if str(dep[0]) not in deployments_new:
                        self.db.drop_rows(self.table, dep[0], -1)
			print "Deployment %s has been removed" % (dep[0])  
			open("/var/tmp/fetch_rs_log", "a").write(self.time + " zCloud: Deployment %s has been removed" % (dep[0]) + "\n")


    def fetch_all_deployments(self):
	import urllib
	for dep_url in self.rs['deployments']:
		self.ch.startrequest(self.INSTANCES_EXTENDED_URL, self.on_deploy_fetch, dep_url,
					urllib.urlencode({"filter[]": "deployment_href==/api/deployments/%s" % str(dep_url)}), force_get = True)

	#to get a host group from a host name
    def get_hostgroup(self, name):
        hash_index = name.find("#")
        if hash_index != -1:
            return name[:hash_index].strip()

        parts = name.split("-")

        if parts[-1].isdigit():
            parts.pop()

        return "-".join(parts)

    #insert one row into db                   
    def insert_into_db(self, rows):
        query = """INSERT IGNORE INTO """ + self.table + """ (timestamp, cloud_id, \
                   deploy_id, deploy_name, array_id, array_name, \
                   hostname, hostgroup, type, status, pricing, \
                   public_ip, private_ip, \
                   aws_id, sketchy_id, birthtime) \
                   VALUES (""" + "%s," * 15 + "%s)"
        r = self.db.querymulti(query, rows)
        # Hope and pray it succeeded
        

    # get the deploy id, deploy name, array id, array name, type for a machine
    def fill_type_deploy_array(self, machine, deploy_id, deploy_name, array_id, array_name, cloud_id, type):

	hostname = machine["name"]
	type = 'UNKNOWN'

	for link in machine['links']:
		# setting the deployment id and deployment name
		if link['rel'] == 'deployment':
			deploy_id, deploy_name = self.get_deployment_from_link(link, deploy_id, deploy_name)
		elif link['rel'] == 'parent' and 'server_arrays' in link['href']:
			array_id, array_name = self.get_array_from_link(link, array_id, array_name)
		elif (link['rel']=="multi_cloud_image"):
			type = self.get_type_from_link(link, type)
		elif link['rel'] == 'cloud':
			cloud_id = link['href'][12:]

	return deploy_id, deploy_name, array_id, array_name, cloud_id, type

    def get_deployment_from_link(self, link, deploy_id, deploy_name):
	    deploy_id = link['href'][17:]
		
	    if (self.rs['deployments'].has_key(deploy_id)):
		    deploy_name = self.rs['deployments'][deploy_id]
	    else:
		    self.ch.startrequest(self.DEPLOY_URL+deploy_id, self.on_one_deployment_fetch, deploy_id)
		    self.ch.finishallrequests()
		    try:
                            deploy_name = self.rs['deployments'][deploy_id]
		    except:
                            return 'UNKNOWN', 'UNKNOWN'
	    return deploy_id, deploy_name

    def get_array_from_link(self, link, array_id, array_name):

	    array_id = link['href'][19:]

	    if(self.rs['arrays'].has_key(array_id)):
		    array_name = self.rs['arrays'][array_id]
	    else:
		    self.ch.startrequest(self.ARRAY_URL+array_id, self.on_one_array_fetch, array_id)
		    self.ch.finishallrequests()
		    try:
			    array_name = self.rs['arrays'][array_id]
		    except:
			    array_name = 'UNKNOWN'
		    
	    return array_id, array_name


    def get_type_from_link(self, link, type):
	    server_template = link['href'][24:]
            if (server_template not in self.rs['images']):
                    self.ch.startrequest(self.CLOUD_IMAGES+server_template, self.on_one_template_fetch, server_template)
                    self.ch.finishallrequests()
            try:                
                image = self.rs['images'][server_template]
            except:
                return "UNKNOWN"

            from pprint import pprint
            
            mach_type_re = re.compile("(C1.MEDIUM|C1.XLARGE|CC1.4XLARGE|CG1.4XLARGE|M1.LARGE|M1.SMALL|M1.XLARGE|M2.2XLARGE|M2.4XLARGE|M2.XLARGE)", re.IGNORECASE)
            try:
                    # 'RightImage CentOS_5.2_x64_v4.2.4 [m1.xlarge]' should give  "M1.XLARGE"
                    #
                    type_s = mach_type_re.search(image)
                    if not type_s:
                        type = "UNKNOWN"
                    else:
                        mach_type = type_s.group().upper()
            except:
                    self.rejects.write(traceback.format_exc())
            
            if 'cloud.com_CentOS_5.4_x64_v5.6.31_700d_70m' in image:
                    type = 'C1.M72.DR800'
            elif 'cloud.com_CentOS_5.4_x64_v5.6.31_100d_20m' in image:
                    type = 'C1.M24.DR140'
            elif 'cloud.com_CentOS_5.4_x64_v5.6.31_100d_70m' in image:
                    type = 'C1.M72.DR140'
            
	    if 'UNKNOWN' in image:
                    print image, " does not have a machine type"
		    open("/var/tmp/fetch_rs_log", "a").write(self.time + " zCloud: " + image + "does not have a matching machine type" + "\n")
	    return type


    #looping though all the instances to be put in db
    def dump_all_instances(self):
   	instances = []

	for deployment in self.rs["instances"]:
	    for machine in self.rs["instances"][deployment]:
		    hostname = machine["name"]
		    hostgroup =  self.get_hostgroup(hostname)

	            state = machine["state"].upper().replace(" ", "_")

		    ip = "UNKNOWN"
	            if (machine.has_key("public_ip_addresses") and machine["public_ip_addresses"]):
        	            ip = machine["public_ip_addresses"][0]

		    pr_ip = "UNKNOWN"        
		    if (machine.has_key("private_ip_addresses") and machine["private_ip_addresses"]):
                	    pr_ip = machine["private_ip_addresses"][0]

	            if machine.has_key("resource_uid"):
        	    	awsid = machine["resource_uid"]
	            else:
        	    	awsid = "UNKNOWN"

	            if machine.has_key("monitoring_id"):
        	        sketchyid = machine["monitoring_id"]
	            else:
        	        sketchyid = "UNKNOWN"	
	    
		    pricing = "UNKNOWN"
		    cloud_id = 'UNKNOWN'
	            try: 
			birth = machine["created_at"][:-6]
	            except: 
			birth = time.strftime("%y/%m/%d %T", time.gmtime(0))

		    deploy_id, deploy_name, array_id, array_name, type = 0, 'UNKNOWN', 0, 'UNKNOWN', 'UNKNOWN'
		    if machine.has_key("links"):
			deploy_id, deploy_name, array_id, array_name, cloud_id, type = self.fill_type_deploy_array(machine, deploy_id, deploy_name, array_id, array_name, cloud_id, type)
		    if('INACTIVE' not in state):	    
			try:
				instances.append((self.time, cloud_id, deploy_id, deploy_name, array_id, array_name, hostname, hostgroup, type, state, pricing, ip, pr_ip, awsid, sketchyid, birth))			
			except:
				self.rejects.write(traceback.format_exc())

	self.insert_into_db(instances)

#
# Used to collect data from EC2. It uses rightscale api 1.5
#               
class Instance_RS10:

    DEPLOY_URL = "https://xxxx.rightscale.com/api/acct/12345/deployments/"
    ARRAY_URL = "https://xxxx.rightscale.com/api/acct/12345/server_arrays?format=js&server_settings=true"

    ARRAY_PREFIX = 54
    DEPLOY_PREFIX = 52
    TEMPLATE_PREFIX = 61

    def tolerance(self, new, old):
	if ( math.fabs((new-old)/float(old))*100 > 85 ) and math.fabs(new - old) > 5 :
		return false
        return true


    def deploy_tolerance(self, new, old, deploy_id):
	if not self.tolerance(new, old) : 
		if str(deploy_id) not in (open("/var/tmp/tax/failed_tolerance_deployments_ec2.txt", "r").read()):
			return false		
	return true

    def array_tolerance(self, new, old, array_id):
        if not self.tolerance(new, old) :
                if str(array_id) not in (open("/var/tmp/tax/failed_tolerance_arrays_ec2.txt", "r").read()):
                        return false
        return true

    def on_array_list_fetch(self, arrays_json, arrays_href, ch, data):
        try:
            if debug and ch: open("/var/tmp/tax/array_list.json", "w").write(arrays_json)
            content = eval(arrays_json)
        except:
            print "Skipping array list", arrays_href
            return

        self.rs["array_list"] = content
        print "Got array list:", len(arrays_json), "bytes long", arrays_href

    def on_one_deployment_fetch(self, deployment_json, deployment_href, ch, deployment_id):
        try:
            if debug and ch: open("/var/tmp/tax/deploy_" + str(deployment_id) + ".json", "w").write(deployment_json)
            content = eval(deployment_json)
	    deploy_size = self.db.get_size(self.table, deployment_id, 0)
	    deploy_size = deploy_size[0][0]
	    new_deploy_size = len(content["servers"]) - deployment_json.count('stopped')
	    if new_deploy_size == 0 and deploy_size == 0 :
		print "Deployment " + str(deployment_id) + " has no instances or all inactive instances"
		open("/var/tmp/fetch_rs_log", "a").write( \
			self.time + " eC2: Deployment " + str(deployment_id) + " has no instances or all inactive instances\n")
	    elif deploy_size == 0 :
		print "New Deployment came up with deployment_id " + str(deployment_id) + " with " + str(new_deploy_size) + " instances"
		open("/var/tmp/fetch_rs_log", "a").write( \
			self.time + " eC2: New Deployment came up with deployment_id " + str(deployment_id) + " with " + str(new_deploy_size) + " instances\n")
	    elif len(content["servers"]) == 0 :
		print "Deployment " + str(deployment_id) + " has 0 instances now"
		open("/var/tmp/fetch_rs_log", "a").write( \
			self.time + " eC2: Deployment " + str(deployment_id) + " has 0 instances now\n")
	    elif( not self.deploy_tolerance(new_deploy_size, deploy_size, deployment_id) ):
		print "Deployment " + str(deployment_id) + "'s instance count has changed drastically from " + str(deploy_size) + " to " + str(new_deploy_size)
		open("/var/tmp/fetch_rs_log", "a").write( \
			self.time + " eC2: Deployment " + str(deployment_id) + "'s instance count has changed drastically from " + str(deploy_size) + " to " + str(new_deploy_size) + "\n")
		self.failed_deployments = self.failed_deployments + str(deployment_id) + "\n"
		return
        except:
	    #print deployment_json
            print "Skipping deployment", deployment_href
            return

        self.rs["deployments"][deployment_id] = content
        print "Got deployment_json ", deployment_id, len(deployment_json), "bytes long", deployment_href	

	self.db.drop_rows(self.table, deployment_id, 0)
	#print "Dropping rows from the clone table for deployment", deployment_id

    def on_one_array_fetch(self, array_json, array_href, ch, data):
      	try:
	    array_id = data["href"][self.ARRAY_PREFIX:]
            if debug and ch: open("/var/tmp/tax/array_" + str(array_id) + ".json", "w").write(array_json)
            content = eval(array_json)
	    array_size = self.db.get_size(self.table, 0, array_id)
	    array_size = array_size[0][0]
	    new_array_size = len(content) - array_json.count('stopped')
            if new_array_size == 0 and array_size == 0 :
                print "Array " + str(array_id) + " has no instances"
		open("/var/tmp/fetch_rs_log", "a").write( \
			self.time + " eC2: Array " + str(array_id) + " has no instances\n")
            elif array_size == 0 :
                print "New Array came up with array_id " + str(array_id) + " with " + str(new_array_size) + " instances"
		open("/var/tmp/fetch_rs_log", "a").write( \
			self.time + " eC2: New Array came up with array_id " + str(array_id) + " with " + str(new_array_size) + " instances\n")
            elif len(content) == 0 :
                print "Array " + str(array_id) + " has 0 instances now"
		open("/var/tmp/fetch_rs_log", "a").write( \
			self.time + " eC2: Array " + str(array_id) + " has 0 instances now\n")
	    elif( not self.array_tolerance(new_array_size, array_size, array_id) ):
		print "Array " + str(array_id) + "'s instance count has changed drastically from " + str(array_size) + " to " + str(new_array_size)
		open("/var/tmp/fetch_rs_log", "a").write( \
			self.time + " eC2: Array " + str(array_id) + "'s instance count has changed drastically from " + str(array_size) + " to " + str(new_array_size) + "\n")
                self.failed_arrays = self.failed_arrays + str(array_id) + "\n"
                return
        except:
            print "Skipping array", array_href
            return

        self.rs["arrays"][array_id] = {'array' : data,
                                       'instances' : content}
        print "Got array ", array_id, len(array_json), "bytes long", array_href

        self.db.drop_rows(self.table, 0, array_id)
        #print "Dropping rows from the clone table for array ", array_id


    def __init__(self):

        # Assoc array of all objects corresponding to right-scale data
        self.rs = { "arrays": {},
                    "array_list" : [],
                    "deployments" : {} }

	self.failed_deployments = ""
	self.failed_arrays = ""

        self.types = {}
        self.status = {}
        self.cfg = Config()

        opts = { pycurl.HTTPHEADER : ["X-API-VERSION: 1.0"],
                 pycurl.USERPWD : 'xxxx@xxxxx.xxx:veryclear',
                 pycurl.CONNECTTIMEOUT : 120,
                 pycurl.TIMEOUT : 1000}

	self.ch = parallelcurl.ParallelCurl( 20, opts)

        self.rejects = open("/var/tmp/tax/instances.rejects", "w")	
        self.cfg = Config() # To get the configured deployment ids if db fetch for deploiy_id is failed

    def run(self, time, db, table):

        self.time = time
	self.db = db
        self.table = table

        # Get deployments from last known list in DB
        self.deploy_list = self.fetch_deployment_list()
        self.template_map = self.fetch_template_list()
        # Fetch array-list
        self.ch.startrequest(self.ARRAY_URL, self.on_array_list_fetch, None)
	
        # Make sure array_list fetch is complete
        self.ch.finishallrequests()

        if(len(self.rs["array_list"]) == 0):
		self.rs["array_list"] = eval(open("/var/tmp/tax/array_list.json").read())

        # Instance extraction and population is after all fetches are through.
        self.fetch_all_deployments()
        self.fetch_all_arrays()

        # Wait till everything is fetched
        self.ch.finishallrequests()

        # Insert details of machines in deployments directly. This
        # also populates deployment-id to deployment-name mapping
        self.dump_deployments()

        # Now insert array instances
        self.dump_arrays()

        self.clear_dumped_deployments()
        self.clear_dumped_arrays()

	open("/var/tmp/tax/failed_tolerance_deployments_ec2.txt","w").write(self.failed_deployments)
	open("/var/tmp/tax/failed_tolerance_arrays_ec2.txt","w").write(self.failed_arrays)
        
	print "TYPES:", self.types
        print "STATUS:", self.status

    # Clearing removed deployments
    def clear_dumped_deployments(self):
	query = "SELECT DISTINCT deploy_id FROM instances WHERE cloud_id = 1"
	deployments_old = self.db.query(query)
	deployments_new = self.deploy_list
	for dep in deployments_old:
		if dep[0] not in deployments_new:
			self.db.drop_rows(self.table, dep[0], -1)
			print "Deployment %s has been removed" % (dep[0])
			open("/var/tmp/fetch_rs_log", "a").write(self.time + " eC2: Deployment %s has been removed" % (dep[0]) + "\n")
			
    # Clearing removed arrays
    def clear_dumped_arrays(self):
        query = "SELECT DISTINCT array_id FROM instances WHERE cloud_id = 1"
        arrays_old = self.db.query(query)
	arrays_new = ['0']
	for a in self.rs["array_list"]:
		arrays_new.append(a['href'][54:])
        for arr in arrays_old:
                if str(arr[0]) not in arrays_new:
                        self.db.drop_rows(self.table, 0, arr[0])
			print "Array %s has been removed" % (arr[0])
			open("/var/tmp/fetch_rs_log", "a").write(self.time + " eC2: Array %s has been removed" % (arr[0]) + "\n")

    # Make api calls for each deployment
    def fetch_all_deployments(self):
        for dep in self.deploy_list:
            self.ch.startrequest(self.DEPLOY_URL + str(dep) + "/?format=js&server_settings=true",
                                 self.on_one_deployment_fetch, dep)

    # Make api calls to fetch all the arrays
    def fetch_all_arrays(self):
        for a in self.rs["array_list"]:
            self.ch.startrequest(a['href'] + "/instances?format=js&server_settings=true",
                                 self.on_one_array_fetch, a)

    # Return the cached list of deployments from DB
    def fetch_deployment_list(self):
        query = """SELECT id FROM deployments WHERE
                        timestamp = (SELECT MAX(timestamp) FROM deployments);"""
        d_list = self.db.query(query)

        dep_list = [int(x[0]) for x in d_list]

        # if deployIDs are not there in DB we take it from config file
        if not len(dep_list):
                d_list = self.cfg.deployments.split(",")
                dep_list = [int(x) for x in d_list]

        return dep_list

    def fetch_template_list(self):
        query = """SELECT id,type FROM templates WHERE
                        timestamp = (SELECT MAX(timestamp) FROM templates);"""
        t_list = self.db.query(query)

        template_map = dict(t_list)
        return template_map

    def get_hostgroup(self, name):
        hash_index = name.find("#")
        if hash_index != -1:
            return name[:hash_index].strip()

        parts = name.split("-")

        if parts[-1].isdigit():
            parts.pop()

        #for count in range(len(parts)):
        #    if parts[count].isdigit() and count :
        #        break

        return "-".join(parts)


    def extract_server_detail(self, machine, instance_type, hostgroup):

        name = machine["nickname"]

        if not hostgroup:
            hostgroup = self.get_hostgroup(name)
        
        if instance_type:
            typ = instance_type
        else:
            typ = machine["settings"]["ec2-instance-type"].upper()

        settings = machine
        if machine.has_key("settings"):
            settings = machine["settings"]

        self.types[typ] = 1
    
        state = machine["state"].upper().replace(" ", "_")
        self.status[state] = 1

        if settings.has_key("pricing"):
            price = settings["pricing"].upper()
        else:
            price = "UNKNOWN"

        if settings.has_key("ip-address"):
            ip = settings["ip-address"]
        elif settings.has_key("ip_address"):
            ip = settings["ip_address"]
        else:
            ip = "UNKNOWN"

        if settings.has_key("private-ip-address"):
            pr_ip = settings["private-ip-address"]
        elif settings.has_key("private_ip_address"):
            pr_ip = settings["private_ip_address"]
        else:
            pr_ip = "UNKNOWN"
    
        if settings.has_key("aws-id"):
            awsid = settings["aws-id"]
        elif settings.has_key("aws_id"):
            awsid = settings["aws_id"]
        elif settings.has_key("resource_uid"):
            awsid = settings["resource_uid"]
        elif settings.has_key("resource-uid"):
            awsid = settings["resource-uid"]
        else:
            awsid = machine["href"].split("/")[-1]
       
	sketchyid = awsid
 
        try: birth = machine["created_at"][:-6]
        except: birth = time.strftime("%y/%m/%d %T", time.gmtime(0))

        #insts.write(",".join([str(x) for x in (name, hostgroup, typ, state, price, ip, pr_ip, awsid, sketchyid, birth)]) + "\n")
        return (name, hostgroup, typ, state, price, ip, pr_ip, awsid, sketchyid, birth)

    def insert_into_db(self, rows):

        query = """INSERT INTO """ + self.table + """ (timestamp, cloud_id, \
                   deploy_id, deploy_name, array_id, array_name, \
                   hostname, hostgroup, type, status, pricing, \
                   public_ip, private_ip, \
                   aws_id, sketchy_id, birthtime) \
                   VALUES (""" + "%s," * 15 + "%s)"

        r = self.db.querymulti(query, rows)
        # Hope and pray it succeeded


    def dump_instances(self, server_list, cloud_id, deploy_id, deploy_name, array_id, array_name,
                       instance_type=None):

        instances = []
        for node in server_list:
            try:
		name, hostgroup, typ, state, price, ip, pr_ip, awsid, sketchyid, birth = self.extract_server_detail(node, instance_type, array_name)
		if state != 'STOPPED' :
	        	instances.append((self.time, cloud_id, deploy_id, deploy_name, array_id, array_name,
        			name, hostgroup, typ, state, price, ip, pr_ip, awsid, sketchyid, birth))
            except:
                self.rejects.write(traceback.format_exc())
                self.rejects.write(str(node) + "\n")
        self.insert_into_db(instances)

    #
    # For every instance in each deployment, add a row to the
    # instances table.
    #
    def dump_deployments(self):
    
        # We have to translate deployment id to name for array
        # processing. This map will provide a direct mapping.
        self.deployment_map = {}

        cloud_id = 1
        for deploy_id, dep in self.rs['deployments'].items():
            deploy_name = dep["nickname"]
            array_id = 0
            array_name = ""

            self.dump_instances(dep['servers'], cloud_id,
                                deploy_id, deploy_name, array_id, array_name)

            self.deployment_map[str(deploy_id)] = deploy_name


    def dump_arrays(self):

        cloud_id = 1

        for id, array_detail in self.rs['arrays'].items():
            array_instances = array_detail['instances']
            array = array_detail["array"]

            if array["active_instances_count"] < 1:
                continue
            deploy_id = array["deployment_href"][self.DEPLOY_PREFIX:]
            if str(deploy_id) in self.deployment_map:
                deploy_name = self.deployment_map[str(deploy_id)]
            else:
                deploy_name = str(deploy_id)

            array_id = id
            array_name = array["nickname"]
            template_id = int(array["server_template_href"][self.TEMPLATE_PREFIX:])

            try:
                    instance_type = self.template_map[template_id]
            except:
                    instance_type = 'UNKNOWN'

            self.dump_instances(array_instances, cloud_id,
                                deploy_id, deploy_name, array_id, array_name, instance_type)


class Instance:

    def tolerance(self, new, old):
        if ( math.fabs((new-old)/float(old))*100 > 85 ) and math.fabs(new - old) > 5 :
                return false
        return true

    def __init__(self):

        self.cfg = Config()
        self.db = DB(self.cfg)
        self.table = self.db.create_clone("instances")
        self.time = time.strftime("%y/%m/%d %T", time.gmtime())

    def run(self):

        RS10_fetcher = Instance_RS10()
        RS10_fetcher.run(self.time, self.db, self.table)
        RS15_fetcher = Instance_RS15()
        RS15_fetcher.run(self.time, self.db, self.table)
        
	# Checking if any hostgroup size changed massively(more than 85% or 5 whichever is max) 
        self.hostgroups_tolerance_check()

        # Checking if any hostname or private_ip or sketchy_id occurs twice
        self.check_uniqueness(["hostname", "private_ip", "sketchy_id"])

	self.table_to_swap = self.db.create_tmp_table("instances")
	self.db.copy_rows(self.table_to_swap, self.table)
        self.db.swap_drop_table("instances", self.table_to_swap)
	
	# To insert static data given in a file specified in server.cfg static_ip_config
	self.insert_static_data(RS15_fetcher)

    def insert_static_data(self,RS15_fetcher):
	RS15_fetcher.table = "instances"
	print "Trying to fetch static host_ip_config\n"
	open("/var/tmp/fetch_rs_log", "a").write( \
                                self.time + " Trying to fetch static host_ip_config\n")
        static_ip_config_list = commands.getstatusoutput("/usr/local/zperfmon/bin/get_conf_vars.php -s static_ip_config")
        static_ip_config_list = static_ip_config_list[1]
        static_ip_config_list = static_ip_config_list.split(' ')
	instance = []
        for static_ip_config in static_ip_config_list:
		if os.path.exists(static_ip_config):
			for line in fileinput.input([static_ip_config]):
				line = line.strip()
				cloud_id, deploy_id, deploy_name, array_id, array_name, hostname, hostgroup, type, state, pricing, ip, pr_ip, awsid, birth, sketchyid = line.split(',')
				instance.append((self.time, cloud_id, deploy_id, deploy_name, array_id, array_name, hostname, hostgroup, type, state, pricing, ip, pr_ip, awsid, sketchyid, birth))
			print "Inserting contents of " + static_ip_config + "to temp instance table\n"
		        open("/var/tmp/fetch_rs_log", "a").write( \
                                self.time + " Inserting contents of " + static_ip_config + "to temp instance table\n")	
			RS15_fetcher.insert_into_db(instance)
		else:
			print "Invalid static file path " + static_ip_config
			open("/var/tmp/fetch_rs_log", "a").write( \
				self.time + " Invalid static file path" + static_ip_config + "\n")
		
    def hostgroups_tolerance_check(self):
        query = "Select hostgroup, count(*) from " + self.table +" group by hostgroup"
        retval_tmp = self.db.query(query)
        query = "Select hostgroup, count(*) from " + "instances" +" group by hostgroup"
        retval_primary = self.db.query(query)

        primary_mapping = {}
        tmp_mapping = {}
        for row in retval_primary:
                primary_mapping[row[0].strip()] = row[1]
        for row in retval_tmp:
                tmp_mapping[row[0].strip()] = row[1]

        for hostgroup in primary_mapping:
                if primary_mapping[hostgroup] == 0 :
                        print "New hostgroup " + hostgroup + "has come up with " + str(primary_mapping[hostgroup]) + " instances"
			open("/var/tmp/fetch_rs_log", "a").write( \
				self.time + " New hostgroup " + hostgroup + "has come up with " + str(primary_mapping[hostgroup]) + " instances\n")
                elif hostgroup not in tmp_mapping :
                        print "Hostgroup " + hostgroup + "has been removed. Had " + str(primary_mapping[hostgroup]) + " instances earlier"
			open("/var/tmp/fetch_rs_log", "a").write( \
				self.time + " Hostgroup " + hostgroup + "has been removed. Had " + str(primary_mapping[hostgroup]) + " instances earlier\n")
                elif not self.tolerance(primary_mapping[hostgroup] , tmp_mapping[hostgroup]):
                        print "Hostgroup " + hostgroup + "'s instances count changed drastically"
			open("/var/tmp/fetch_rs_log", "a").write( \
				self.time + " Hostgroup " + hostgroup + "'s instances count changed drastically\n")


    def check_uniqueness(self, matrics):
        for matric in matrics:
                query = "Select " + matric + ", cloud_id , count(*) from " + self.table +" \
				 where status like 'OPERATIONAL' and " + matric + " not like 'UNKNOWN' group by " + matric + ", cloud_id  having count(" + matric + ") > 1"
                retval = self.db.query(query)
                if (len(retval) != 0) :
                        open("/var/tmp/tax/" + matric + ".json","w").write(str(retval))
                        print "Uniqueness property voilated for matric: " + matric + ". Please check /var/tmp/tax/" + matric + ".json"
			open("/var/tmp/fetch_rs_log", "a").write( \
				self.time + " Uniqueness property violated for matric: " + matric + ". Please check /var/tmp/tax/" + matric + ".json\n")

class Template:

    TEMPLATE_URL = "https://xxxx.rightscale.com/api/acct/1234/ec2_server_templates/?format=js"

    TEMPLATE_PREFIX = 61
    
    def __init__(self):
        
        self.cfg = Config()
        self.db = DB(self.cfg)

        self.time = time.strftime("%y/%m/%d %T", time.gmtime())
        
        opts = { pycurl.HTTPHEADER : ["X-API-VERSION: 1.0"],
                 pycurl.USERPWD : 'xxxx@xxxx.xxx:veryclear',
                 pycurl.CONNECTTIMEOUT : 120,
                 pycurl.TIMEOUT : 1000 }

	self.ch = parallelcurl.ParallelCurl( 20, opts)

        rejects = open("/var/tmp/tax/templates.rejects", "w")


    def run(self):
        
        self.table = self.db.create_tmp_table("templates")
        
        self.template = {}

        self.ch.startrequest(self.TEMPLATE_URL, self.on_template_list_fetch, None)

        # Wait for the fetch and populate
        self.ch.finishallrequests()

        self.queue_template_fetches()
        self.ch.finishallrequests()

        self.dump_templates()

        self.db.swap_drop_table("templates", self.table)


    def dump_templates(self):

        rows = []
        from pprint import pprint

        mach_type_re = re.compile("(C1.MEDIUM|C1.XLARGE|CC1.4XLARGE|CG1.4XLARGE|M1.LARGE|M1.SMALL|M1.XLARGE|M2.2XLARGE|M2.4XLARGE|M2.XLARGE)", re.IGNORECASE)
        for tmplt_id, tmplt in self.template.items():
            
            try:
                # 'RightImage CentOS_5.2_x64_v4.2.4 [m1.xlarge]' should give  "M1.XLARGE"
                #
            
                image_name = tmplt['default_multi_cloud_image']['name']
                type_s = mach_type_re.search(image_name)
                if not type_s:
                    mach_type = "UNKNOWN"
                    print tmplt_id, image_name, "does not have a machine type"
                else:
                    mach_type = type_s.group().upper()
            
                rows.append([self.time, tmplt_id, mach_type, tmplt["nickname"]])
            except:
                self.rejects.write(traceback.format_exc())
                self.rejects.write(str(node) + "\n")
            
        self.db.querymulti("""INSERT INTO """ + self.table + """ (timestamp, id, type, name)
                                values (%s, %s, %s, %s)""", rows)
    
    def on_template_list_fetch(self, template_list_json, template_list_href, ch, data):

        if debug and ch: open("/var/tmp/tax/template_list.json", "w").write(template_list_json)

        try:
            content = eval(template_list_json)
        except:
            print "Skipping template list", template_list_href
            return

        self.template_list = content
        print "Got template list data: ", len(template_list_json), "bytes long"

    def on_template_fetch(self, template_json, template_href, ch, data):

        tmpl_id = template_href.replace("?", "/").split("/")[7]
        if debug and ch: open("/var/tmp/tax/template_" + str(tmpl_id) + ".json", "w").write(template_json)

        try:
            content = eval(template_json)
        except:
            print "Skipping template", template_href
            return

        self.template[tmpl_id] = content
        print "Got template data for ", tmpl_id, len(template_json), "bytes long"

    def queue_template_fetches(self):

        for tmpl in self.template_list:
            self.ch.startrequest(tmpl["href"] + "?include_mcis=true&format=js", self.on_template_fetch, None)

    def insert_into_db(self, rows):
        r = self.db.querymulti("""INSERT INTO """ + self.table + """
                (timestamp, cloud_id, id, name, href)
                VALUES (%s, %s, %s, %s, %s)""",
                rows)
        return r

    #
    # For every deployment, add a row to the deployment table.
    #
    def dump_deployment(self):
        cloud_id = 1
        deps = [[self.time, cloud_id,
                 dep["href"][self.DEPLOY_PREFIX:],  dep["nickname"], dep["href"]]
                for dep in self.deployments]

        self.insert_into_db(deps)

class Deployment:

    DEPLOY_URL = "https://xxxx.rightscale.com/api/acct/1234/deployments?format=js"

    DEPLOY_PREFIX = 52

    def __init__(self):

        self.cfg = Config()
        self.db = DB(self.cfg)

        self.time = time.strftime("%y/%m/%d %T", time.gmtime())

        opts = { pycurl.HTTPHEADER : ["X-API-VERSION: 1.0"],
                 pycurl.USERPWD : 'xxx@xxxx.xxx:veryclear',
                 pycurl.CONNECTTIMEOUT : 120,
                 pycurl.TIMEOUT : 1000 }

	self.ch = parallelcurl.ParallelCurl( 20, opts)
	print "Fetching from rightscale"

        self.rejects = open("/var/tmp/tax/deployment.rejects", "w")

        self.deployments = []

    def run(self):

        self.table = self.db.create_tmp_table("deployments")

        self.ch.startrequest(self.DEPLOY_URL, self.on_deploy_fetch, None)

        # Wait for the fetch and populate
        self.ch.finishallrequests()

        if self.dump_deployment():
            self.db.swap_drop_table("deployments", self.table)
        else:
            self.db.drop_table(self.table)

    def on_deploy_fetch(self, deploy_json, deploy_href, ch, data):

        if debug and ch: open("/var/tmp/tax/deploy.json", "w").write(deploy_json)

        try:
            content = eval(deploy_json)
        except:
            print "Skipping deployment", deploy_href
            return

        self.deployments = content
        print "Got deploy data: ", len(deploy_json), "bytes long"


    def insert_into_db(self, rows):
        r = self.db.querymulti("""INSERT INTO """ + self.table + """
                (timestamp, cloud_id, id, name, href)
                VALUES (%s, %s, %s, %s, %s)""",
                rows)
        return r

    #
    # For every deployment, add a row to the deployment table.
    #
    def dump_deployment(self):

        if not self.deployments:
            print "Deployment list empty, probably fetch failed"
            return False

        cloud_id = 1
        deps = []
        print len(self.deployments)
        for dep in self.deployments:
            try:
                deps.append([self.time, cloud_id,
                            dep["href"][self.DEPLOY_PREFIX:],  dep["nickname"], dep["href"]])
            except:
                self.rejects.write(traceback.format_exc())
                self.rejects.write(str(dep) + "\n")

        self.insert_into_db(deps)
        return True


def usage(valid):
    print "Use it as\nfetch_rs.py [-y", str(valid), "]"

def main():
    syslog.openlog("zperfmon")

    valid = ['instance', 'deployment', 'clean', 'template']
    why = 'instance'

    (args, leftover) = getopt.getopt(sys.argv[1:], "y:c:")
    for o, v in args:
        if o == "-y":
            if v in valid:
                why = v
            else:
                usage(valid)
                sys.exit()

    print "Running", why
    if why == 'instance':
        fetcher = Instance()
    elif why == 'deployment':
        fetcher = Deployment()
    elif why == 'template':
        fetcher = Template()
    else:
        usage()
        sys.exit()

    fetcher.run()

if __name__ == "__main__":
    main()

