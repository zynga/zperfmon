<?php

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

# Create our worker object.
$worker= new GearmanWorker();

# Add default server (localhost).
$worker->addServer('localhost', 4730);

# Register function "reverse" with the server.
$worker->addFunction("shell_execute", "shell_execute");
$options = getopt("P:");
$pid = getmypid();
file_put_contents($options['P'], $pid);
while (1)
{
#  print "Waiting for job...\n";

  $ret= $worker->work();
  if ($worker->returnCode() != GEARMAN_SUCCESS)
    break;
}

# A much simple reverse function
function shell_execute($job)
{
  $workload= $job->workload();
#  echo "Automated game addition to zPerfmon: " . $job->handle() . "\n";
#  echo "Workload: $workload\n";
  $result= exec($workload, $retval);
#  echo "Result: $retval\n";
#print_r($retval);
  return $result;
}

?>
