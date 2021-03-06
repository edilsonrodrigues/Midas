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

/** Upgrade the dicomextractor module to version 1.1.0. */
class Dicomextractor_Upgrade_1_1_0 extends MIDASUpgrade
{
    /** @var string */
    public $moduleName = 'dicomextractor';

    /** Post database upgrade. */
    public function postUpgrade()
    {
        /** @var SettingModel $settingModel */
        $settingModel = MidasLoader::loadModel('Setting');
        $configPath = LOCAL_CONFIGS_PATH.DIRECTORY_SEPARATOR.$this->moduleName.'.local.ini';

        if (file_exists($configPath)) {
            $config = new Zend_Config_Ini($configPath, 'global');
            $settingModel->setConfig(DICOMEXTRACTOR_DCM2XML_COMMAND_KEY, $config->get('dcm2xml', DICOMEXTRACTOR_DCM2XML_COMMAND_DEFAULT_VALUE), $this->moduleName);
            $settingModel->setConfig(DICOMEXTRACTOR_DCMJ2PNM_COMMAND_KEY, $config->get('dcmj2pnm', DICOMEXTRACTOR_DCMJ2PNM_COMMAND_DEFAULT_VALUE), $this->moduleName);
            $settingModel->setConfig(DICOMEXTRACTOR_DCMFTEST_COMMAND_KEY, $config->get('dcmftest', DICOMEXTRACTOR_DCMFTEST_COMMAND_DEFAULT_VALUE), $this->moduleName);
            $settingModel->setConfig(DICOMEXTRACTOR_DCMDICTPATH_KEY, $config->get('dcmdictpath', DICOMEXTRACTOR_DCMDICTPATH_DEFAULT_VALUE), $this->moduleName);

            $config = new Zend_Config_Ini($configPath, null, true);
            unset($config->global->dcm2xml);
            unset($config->global->dcmj2pnm);
            unset($config->global->dcmftest);
            unset($config->global->dcmdictpath);

            $writer = new Zend_Config_Writer_Ini();
            $writer->setConfig($config);
            $writer->setFilename($configPath);
            $writer->write();
        } else {
            $settingModel->setConfig(DICOMEXTRACTOR_DCM2XML_COMMAND_KEY, DICOMEXTRACTOR_DCM2XML_COMMAND_DEFAULT_VALUE, $this->moduleName);
            $settingModel->setConfig(DICOMEXTRACTOR_DCMJ2PNM_COMMAND_KEY, DICOMEXTRACTOR_DCMJ2PNM_COMMAND_DEFAULT_VALUE, $this->moduleName);
            $settingModel->setConfig(DICOMEXTRACTOR_DCMFTEST_COMMAND_KEY, DICOMEXTRACTOR_DCMFTEST_COMMAND_DEFAULT_VALUE, $this->moduleName);
            $settingModel->setConfig(DICOMEXTRACTOR_DCMDICTPATH_KEY, DICOMEXTRACTOR_DCMDICTPATH_DEFAULT_VALUE, $this->moduleName);
        }
    }
}
