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


include_once 'XhProfModel.php';

/**
 * Base view class for XhProf. All views derive from it.
 * When views are constructed, a model is used to bind the view with
 * the model. When Model "feels good", it renders itself through the view
 *
 * @author user
 */
abstract class XhProfView_Interface
{
    /*
     * Commands the view to render itself. Commands usually
     * come from Model.
     */
    abstract protected function Render($array);
    abstract protected function RenderCombination($chart, $ccols, $table = null, $tcols = null);
    
}
?>
