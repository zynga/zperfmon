#
# Adapted from https://github.com/petewarden/pyparallelcurl/blob/master/pyparallelcurl.py
#

# This class is designed to make it easy to run multiple curl requests in parallel, rather than
# waiting for each one to finish before starting the next. Under the hood it uses curl_multi_exec
# but since I find that interface painfully confusing, I wanted one that corresponded to the tasks
# that I wanted to run.
#
# To use it, first create the ParallelCurl object:
#
# parallel_curl = ParallelCurl(10);
#
# The first argument to the constructor is the maximum number of outstanding fetches to allow
# before blocking to wait for one to finish. You can change this later using setMaxRequests()
# The second optional argument is an array of curl options in the format used by curl_setopt_array()
#
# Next, start a URL fetch:
#
# parallel_curl.startrequest('http://example.com', onrequestdone, {'somekey': 'somevalue'})
#
# The first argument is the address that should be fetched
# The second is the callback function that will be run once the request is done
# The third is a 'cookie', that can contain arbitrary data to be passed to the callback
#
# This startrequest call will return immediately, as long as less than the maximum number of
# requests are outstanding. Once the request is done, the callback function will be called, eg:
#
# onrequestdone(content, 'http://example.com', ch, {'somekey': 'somevalue'})
#
# The callback should take four arguments. The first is a string containing the content found at
# the URL. The second is the original URL requested, the third is the curl handle of the request that
# can be queried to get the results, and the fourth is the arbitrary 'cookie' value that you
# associated with this object. This cookie contains user-defined data.
#
# By Pete Warden <pete@petewarden.com>, freely reusable, see http://petewarden.typepad.com for more

import sys
import pycurl
import cStringIO
import time

# Pete- Not quite sure what this is all about, but seems important, so copied from
# retriever-multi.py :)
#
# We should ignore SIGPIPE when using pycurl.NOSIGNAL - see
# the libcurl tutorial for more info.
try:
    import signal
    from signal import SIGPIPE, SIG_IGN
    signal.signal(signal.SIGPIPE, signal.SIG_IGN)
except ImportError:
    pass

class ParallelCurl:
    
    max_requests = 10
    options = {}
    
    outstanding_requests = {}
    multi_handle = None
    
    def __init__(self, in_max_requests = 10, in_options = {}):
        self.max_requests = in_max_requests
        self.options = in_options
        
        self.outstanding_requests = {}
        self.multi_handle = pycurl.CurlMulti()
    
    # Ensure all the requests finish nicely
    def __del__(self):
        print 'self.max_requests='+str(self.max_requests)
        self.finishallrequests()
    
    # Sets how many requests can be outstanding at once before we block and wait for one to
    # finish before starting the next one
    def setmaxrequests(self, in_max_requests):
        self.max_requests = in_max_requests
    
    # Sets the options to pass to curl, using the format of curl_setopt_array()
    def setoptions(self, in_options):
        self.options = in_options
    
    # Start a fetch from the 'url' address, calling the 'callback' function passing the optional
    # 'user_data' value. The callback should accept 3 arguments, the url, curl handle and user
    # data, eg on_request_done(url, ch, user_data). force_get is to make the custom request
    # GET instead of POST
    def startrequest(self, url, callback, user_data = {}, post_fields=None, force_get=False):
        
        if self.max_requests > 0:
            self.waitforoutstandingrequeststodropbelow(self.max_requests)
    
        ch = pycurl.Curl()
        for option, value in self.options.items():
            ch.setopt(option, value)

        ch.setopt(pycurl.URL, url)
        result_buffer = cStringIO.StringIO()
        ch.setopt(pycurl.WRITEFUNCTION, result_buffer.write)
    
        if post_fields is not None:
            if not force_get:
                ch.setopt(pycurl.POST, True)
            else:
                ch.setopt(pycurl.CUSTOMREQUEST, 'GET')
            ch.setopt(pycurl.POSTFIELDS, post_fields)
        
        self.multi_handle.add_handle(ch)
        
        self.outstanding_requests[ch] = {
            'handle': ch,
            'result_buffer': result_buffer,
            'url': url,
            'callback': callback,
            'user_data':user_data
        }
        
        self.checkforcompletedrequests()
    
    # You *MUST* call this function at the end of your script. It waits for any running requests
    # to complete, and calls their callback functions
    def finishallrequests(self):
        self.waitforoutstandingrequeststodropbelow(1)
    
    # Checks to see if any of the outstanding requests have finished
    def checkforcompletedrequests(self):
        
        # Call select to see if anything is waiting for us
        if self.multi_handle.select(1.0) == -1:
            return;
        
        # Since something's waiting, give curl a chance to process it
        while True:
            ret, num_handles = self.multi_handle.perform()
            if ret != pycurl.E_CALL_MULTI_PERFORM:
                break
        
        # Now grab the information about the completed requests
        while True:
            num_q, ok_list, err_list = self.multi_handle.info_read()
            for ch in ok_list:
                if ch not in self.outstanding_requests:
                    raise RuntimeError("Error - handle wasn't found in requests: '"+str(ch)+"' in "
                        +str(self.outstanding_requests))
                    
                request = self.outstanding_requests[ch]
                
                url = request['url']
                content = request['result_buffer'].getvalue()
                callback = request['callback']
                user_data = request['user_data']
                
                callback(content, url, ch, user_data)
                
                self.multi_handle.remove_handle(ch)
                
                del self.outstanding_requests[ch]

            for ch, errno, errmsg in err_list:
                
                if ch not in self.outstanding_requests:
                    raise RuntimeError("Error - handle wasn't found in requests: '"+str(ch)+"' in "
                        +str(self.outstanding_requests))
                    
                request = self.outstanding_requests[ch]
                
                url = request['url']
                content = None
                callback = request['callback']
                user_data = request['user_data']
                
                callback(content, url, ch, user_data)
                
                self.multi_handle.remove_handle(ch)
                
                del self.outstanding_requests[ch]
            
            if num_q < 1:
                break
    
    # Blocks until there's less than the specified number of requests outstanding
    def waitforoutstandingrequeststodropbelow(self, max):
        while True:
            self.checkforcompletedrequests()
            if len(self.outstanding_requests) < max:
             break
            
            time.sleep(0.01)
