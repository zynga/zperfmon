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


include_once 'xhprof_lib.php';
include_once 'xhprof_runs.php';

$display_calls = 'true';

function get_summary($file_path, $probe=null)
{
        $xhprof_data = XHProfRuns_Default::load_profile($file_path);
        $xhprof_data = xhprof_compute_flat_info($xhprof_data, $totals);

        $probe_count = 0;
        if ($probe != null && isset($xhprof_data[$probe])) {
                $probe_count = $xhprof_data[$probe]['ct'];
        }

        return array('pmu' => isset($totals['pmu']) ? $totals['pmu'] : '',
                     'mu' => isset($totals['mu']) ? $totals['mu'] : '',
                     'wt' => $totals['wt'],
                     'cpu' => $totals['cpu'],
                     'nbr' => $probe_count);
}


