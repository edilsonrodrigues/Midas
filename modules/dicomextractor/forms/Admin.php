<?php
/*=========================================================================
 Midas Server
 Copyright Kitware SAS, 26 rue Louis Guérin, 69100 Villeurbanne, France.
 All rights reserved.
 For more information visit http://www.kitware.com/.

 Licensed under the Apache License, Version 2.0 (the "License");
 you may not use this file except in compliance with the License.
 You may obtain a copy of the License at

         http://www.apache.org/licenses/LICENSE-2.0.txt

 Unless required by applicable law or agreed to in writing, software
 distributed under the License is distributed on an "AS IS" BASIS,
 WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 See the License for the specific language governing permissions and
 limitations under the License.
=========================================================================*/

/** Admin form for the dicomextractor module. */
class Dicomextractor_Form_Admin extends Zend_Form
{
    /** Initialize this form. */
    public function init()
    {
        $this->setName('dicomextractor_admin');
        $this->setMethod('POST');

        $csrf = new Midas_Form_Element_Hash('csrf');
        $csrf->setSalt('49XPMaMZKPVq6uGt3vgMbp7Y');
        $csrf->setDecorators(array('ViewHelper'));

        $dcm2xmlCommand = new Zend_Form_Element_Text(DICOMEXTRACTOR_DCM2XML_COMMAND_KEY);
        $dcm2xmlCommand->setLabel('dcm2xml Command');
        $dcm2xmlCommand->setRequired(true);
        $dcm2xmlCommand->addValidator('NotEmpty', true);

        $dcmj2pnmCommand = new Zend_Form_Element_Text(DICOMEXTRACTOR_DCMJ2PNM_COMMAND_KEY);
        $dcmj2pnmCommand->setLabel('dcmj2pnm Command');
        $dcmj2pnmCommand->setRequired(true);
        $dcmj2pnmCommand->addValidator('NotEmpty', true);

        $dcmftestCommand = new Zend_Form_Element_Text(DICOMEXTRACTOR_DCMFTEST_COMMAND_KEY);
        $dcmftestCommand->setLabel('dcmftest Command');
        $dcmftestCommand->setRequired(true);
        $dcmftestCommand->addValidator('NotEmpty', true);

        $dcmdictpath = new Zend_Form_Element_Text(DICOMEXTRACTOR_DCMDICTPATH_KEY);
        $dcmdictpath->setLabel('DCMDICTPATH Environment Variable');
        $dcmdictpath->setRequired(true);
        $dcmdictpath->addValidator('NotEmpty', true);

        $this->addDisplayGroup(array($dcm2xmlCommand, $dcmj2pnmCommand, $dcmftestCommand, $dcmdictpath), 'global');

        $submit = new Zend_Form_Element_Submit('submit');
        $submit->setLabel('Save');

        $this->addElements(array($csrf, $dcm2xmlCommand, $dcmj2pnmCommand, $dcmftestCommand, $dcmdictpath, $submit));
    }
}
