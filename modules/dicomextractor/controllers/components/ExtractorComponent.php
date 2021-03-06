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

include_once BASE_PATH.'/library/KWUtils.php';

/** Extract dicom metadata */
class Dicomextractor_ExtractorComponent extends AppComponent
{
    public $moduleName = 'dicomextractor';

    /**
     * Check whether a given application is configured properly.
     *
     * @param command the command to test with
     * @param appName the human-readable application name
     * @param appendVersion whether we need the --version flag
     * @return an array indicating whether the app is valid or not
     */
    public function getApplicationStatus($preparedCommand, $appName, $appendVersion = true)
    {
        // Our config files replace double quotes with single quotes, so we need to fix that
        $preparedCommand = str_replace("'", '"', $preparedCommand);

        if ($appendVersion) {
            $preparedCommand .= ' --version';
        }
        $this->_prependDataDict($preparedCommand);
        exec($preparedCommand, $output, $return_var);
        if (empty($output)) {
            return array(false, $appName.' was not found or is not configured properly.');
        }
        $parsedOutput = explode(' ', $output[0], 4);
        $appVersion = $parsedOutput[2];
        if ($return_var !== 0) {
            return array(false, $appName.' was not found or is not configured properly.');
        } else {
            return array(true, $appName.' '.$appVersion.' is present.');
        }
    }

    /**
     * Remove any params to the command, returning only the executable argument.
     */
    private function _getExecutableArg($commandWithParams)
    {
        $commandWithParams = trim($commandWithParams);
        // First test if the executable argument has quotes
        $isQuoted = preg_match('/^["\'][^"]*["\']/', $commandWithParams, $matches);
        if ($isQuoted) {
            $executable = $matches[0];
        } else {
            $commandWithParamsParts = explode(' ', $commandWithParams);
            $executable = $commandWithParamsParts[0];
        }

        return $executable;
    }

    /**
     * Prepend data dictionary environment variable if necessary.
     */
    private function _prependDataDict(&$command)
    {
        /** @var SettingModel $settingModel */
        $settingModel = MidasLoader::loadModel('Setting');
        $dictPath = $settingModel->getValueByName(DICOMEXTRACTOR_DCMDICTPATH_KEY, $this->moduleName);

        if ($dictPath != '') {
            $command = 'DCMDICTPATH="'.$dictPath.'" '.$command;
        }
    }

    /**
     * Verify that DCMTK is setup properly.
     */
    public function isDCMTKWorking()
    {
        /** @var SettingModel $settingModel */
        $settingModel = MidasLoader::loadModel('Setting');
        $dcm2xmlCommand = $settingModel->getValueByName(DICOMEXTRACTOR_DCM2XML_COMMAND_KEY, $this->moduleName);
        $dcmftestCommand = $settingModel->getValueByName(DICOMEXTRACTOR_DCMFTEST_COMMAND_KEY, $this->moduleName);

        // dcmj2pnmCommand may have some params that will cause it to throw
        // an error when no input is given, hence for existence and configuration
        // testing just get the command itself, without params
        $dcmj2pnmCommand = $this->_getExecutableArg($settingModel->getValueByName(DICOMEXTRACTOR_DCMJ2PNM_COMMAND_KEY, $this->moduleName));

        $ret = array();
        $ret['dcm2xml'] = $this->getApplicationStatus($dcm2xmlCommand, 'dcm2xml');
        $ret['dcmftest'] = $this->getApplicationStatus($dcmftestCommand, 'dcmftest', false);
        $ret['dcmj2pnm'] = $this->getApplicationStatus($dcmj2pnmCommand, 'dcmj2pnm');

        $dataDictVar = $settingModel->getValueByName(DICOMEXTRACTOR_DCMDICTPATH_KEY, $this->moduleName);

        if ($dataDictVar == '') {
            if (is_readable('/usr/local/share/dcmtk/dicom.dic') ||  // default on OS X
                is_readable('/usr/share/dcmtk/dicom.dic')
            ) { // default on Ubuntu
                $ret['dcmdatadict'] = array(true, 'DICOM Data Dictionary found at '.'default location.');
            } else {
                $ret['dcmdatadict'] = array(
                    false,
                    'No DICOM Data dictionary set or '.'found. Please set the DCMDATADICT config variable.',
                );
            }
        } else {
            $dictPaths = explode(':', $dataDictVar);
            $errorInDictVar = false;
            foreach ($dictPaths as $path) {
                if (!is_readable($path)) {
                    $ret['dcmdatadict'] = array(
                        false,
                        'Unable to find DICOM Data '.'dictionary specified through the environment variable',
                    );
                    $errorInDictVar = true;
                    break;
                }
            }

            if (!$errorInDictVar) {
                $ret['dcmdatadict'] = array(
                    true,
                    'DICOM Data Dictionary found as '.'specified in the DCMDATADICT variable.',
                );
            }
        }

        return $ret;
    }

    /**
     * Create a thumbnail from the series.
     */
    public function thumbnail($item)
    {
        /** @var ItemModel $itemModel */
        $itemModel = MidasLoader::loadModel('Item');
        $revision = $itemModel->getLastRevision($item);
        if ($revision === false) {
            return;
        }

        $bitstreams = $revision->getBitstreams();
        $numBitstreams = count($bitstreams);
        if ($numBitstreams < 1) {
            return;
        }

        /** @var Thumbnailcreator_ImagemagickComponent $thumbnailComponent */
        $thumbnailComponent = MidasLoader::loadComponent('Imagemagick', 'thumbnailcreator');

        /** @var UtilityComponent $utilityComponent */
        $utilityComponent = MidasLoader::loadComponent('Utility');
        $bitstream = $bitstreams[(int) ($numBitstreams / 2)];

        // Turn the DICOM into a JPEG
        $tempDirectory = $utilityComponent->getTempDirectory();
        $tmpSlice = $tempDirectory.'/'.$bitstream->getName().'.jpg';

        /** @var SettingModel $settingModel */
        $settingModel = MidasLoader::loadModel('Setting');
        $command = $settingModel->getValueByName(DICOMEXTRACTOR_DCMJ2PNM_COMMAND_KEY, $this->moduleName);
        $preparedCommand = str_replace("'", '"', $command);
        $preparedCommand .= ' "'.$bitstream->getFullPath().'" "'.$tmpSlice.'"';
        $this->_prependDataDict($preparedCommand);
        exec($preparedCommand, $output);

        // We have to spoof an item array for the thumbnail component. This
        // should certainly be fixed one day. It's a hack, but not my hack.
        $spoofedItem = array();
        $spoofedItem['item_id'] = $item->getKey();
        $thumbnailComponent->createThumbnail($spoofedItem, $tmpSlice);
        unlink($tmpSlice);
    }

    /**
     * HACK TODO FIXME Right now we only extract the metadata from the 0th
     * bitstream of the item. We should really do some sort of validation on
     * the n bitstreams to make sure their tags match.
     */
    public function extract($revision)
    {
        $bitstreams = $revision->getBitstreams();
        if (count($bitstreams) < 1) {
            return;
        }
        $bitstream = $bitstreams[0];

        /** @var SettingModel $settingModel */
        $settingModel = MidasLoader::loadModel('Setting');
        $command = $settingModel->getValueByName(DICOMEXTRACTOR_DCM2XML_COMMAND_KEY, $this->moduleName);
        $preparedCommand = str_replace("'", '"', $command);
        $preparedCommand .= ' "'.$bitstream->getFullPath().'"';
        $this->_prependDataDict($preparedCommand);
        exec($preparedCommand, $output);
        $xml = new XMLReader();
        $xml->xml(implode($output)); // implode our output
        $tagArray = array();
        $tagField = array();
        while ($xml->read()) {
            switch ($xml->nodeType) {
                case XMLReader::END_ELEMENT:
                    $tagField = array();
                    break;
                case XMLReader::ELEMENT:
                    if ($xml->hasAttributes) {
                        while ($xml->moveToNextAttribute()) {
                            if ($xml->name == 'tag') {
                                $tagField['tag'] = $xml->value;
                            } elseif ($xml->name == 'name') {
                                $tagField['name'] = $xml->value;
                            }
                        }
                    }
                    break;
                case XMLReader::TEXT:
                    $tagField['value'] = $xml->value;
                    $tagArray[] = $tagField;
                    break;
                default:
                    break;
            }
        }

        /** @var MetadataModel $MetadataModel */
        $MetadataModel = MidasLoader::loadModel('Metadata');
        foreach ($tagArray as $row) {
            try {
                $metadataDao = $MetadataModel->getMetadata(MIDAS_METADATA_TEXT, 'DICOM', $row['name']);
                if (!$metadataDao) {
                    $metadataDao = $MetadataModel->addMetadata(
                        MIDAS_METADATA_TEXT,
                        'DICOM',
                        $row['name'],
                        $row['name']
                    );
                }
                $metadataDao->setItemrevisionId($revision->getKey());
                $metadataDao->setValue($row['value']);
                if (!$MetadataModel->getMetadataValueExists($metadataDao)) {
                    $MetadataModel->addMetadataValue(
                        $revision,
                        MIDAS_METADATA_TEXT,
                        'DICOM',
                        $row['name'],
                        $row['value']
                    );
                }
            } catch (Zend_Exception $exc) {
                echo $exc->getMessage();
            }
        }
    }
}
