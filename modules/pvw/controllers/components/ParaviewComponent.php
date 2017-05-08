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

/**
 * This component is used to create and manages
 * paraview (pvpython) instances.
 */
class Pvw_ParaviewComponent extends AppComponent
{
    /** @var string */
    public $moduleName = 'pvw';

    /**
     * Creates a new pvpython instance and a corresponding database record for it.
     *
     * @param ItemDao $item item DAO to visualize
     * @param array $meshItems
     * @param string $appname
     * @param null|ProgressDao $progressDao
     * @return Pvw_InstanceDao
     * @throws Zend_Exception
     */
    public function createAndStartInstance($item, $meshItems, $appname, $progressDao = null)
    {
        /** @var ProgressModel $progressModel */
        $progressModel = MidasLoader::loadModel('Progress');
        if ($progressDao) {
            $step = 1;
            $progressDao->setMaximum(5);
            $progressModel->save($progressDao);
            $progressModel->updateProgress($progressDao, $step, 'Checking available ports...');
        }

        /** @var SettingModel $settingModel */
        $settingModel = MidasLoader::loadModel('Setting');
        $pvpython = $settingModel->getValueByName(MIDAS_PVW_PVPYTHON_KEY, $this->moduleName);
        $application = BASE_PATH.'/modules/pvw/apps/'.$appname.'.py';
        if (!is_file($application)) {
            throw new Zend_Exception('No such application: '.$appname, 400);
        }
        if ($progressDao) {
            ++$step;
            $progressModel->updateProgress($progressDao, $step, 'Checking available ports...');
        }

        // TODO critical section of code between getting the open port and listening on it
        // practical solution: add db setting for 'nextPortToTry'.
        $port = $this->_getNextOpenPort();
        if ($port === false) {
            throw new Zend_Exception('Maximum number of running instances reached, try again soon', 503);
        }
        if ($progressDao) {
            ++$step;
            $progressModel->updateProgress($progressDao, $step, 'Starting ParaView instance...');
        }

        /** @var Pvw_InstanceDao $instance */
        $instance = MidasLoader::newDao('InstanceDao', 'pvw');
        $instance->setItemId($item->getKey());
        $instance->setPort($port);
        $instance->setSid(''); // todo?
        $instance->setPid(0);
        $instance->setCreationDate(date('Y-m-d H:i:s'));

        /** @var RandomComponent $randomComponent */
        $randomComponent = MidasLoader::loadComponent('Random');
        $instance->setSecret($randomComponent->generateString(32, '0123456789abcdef'));

        /** @var Pvw_InstanceModel $instanceModel */
        $instanceModel = MidasLoader::loadModel('Instance', 'pvw');
        $instanceModel->save($instance);

        $dataPath = $this->_createDataDir($item, $meshItems, $instance);

        $cmdArray = array(
            $pvpython,
            $application,
            '--port',
            $port,
            '--data',
            $dataPath,
            '-a',
            $instance->getSecret(),
            '--timeout',
            '900',
        ); // TODO --inactivity-timeout

        // Now start the instance
        $displayEnv = $settingModel->getValueByName(MIDAS_PVW_DISPLAY_ENV_KEY, $this->moduleName);
        if (!empty($displayEnv)) {
            putenv('DISPLAY='.$displayEnv);
        }
        $cmd = implode(' ', $cmdArray);
        exec(sprintf('%s > %s 2>&1 & echo $!', $cmd, $dataPath.'/pvw.log'), $output);
        $pid = trim(implode('', $output));
        if (!is_numeric($pid) || $pid == 0) {
            throw new Zend_Exception('Expected pid output, got: '.$pid, 500);
        }
        $instance->setPid($pid);
        $instanceModel->save($instance);

        if ($progressDao) {
            ++$step;
            $progressModel->updateProgress($progressDao, $step, 'Waiting for binding on port '.$port.'...');
        }
        // After we start the process, wait some number of seconds for the port to open up.
        // If it doesn't, something went wrong.
        $portOpen = false;
        for ($i = 0; $i < 4 * MIDAS_PVW_STARTUP_TIMEOUT; ++$i) {
            usleep(250000); // sleep for 1/4 sec
            if (UtilityComponent::isPortListening($port)) {
                $portOpen = true;
                break;
            }
        }
        if (!$portOpen) {
            $log = file_get_contents($dataPath.'/pvw.log');
            $errMsg = 'Instance did not bind to port within '.MIDAS_PVW_STARTUP_TIMEOUT.' seconds.';
            $errMsg .= "\n\n<b>Log content:</b> ".$log;
            $this->killInstance($instance);
            throw new Zend_Exception($errMsg, 500);
        }

        $instance->setPid($pid);
        $instanceModel->save($instance);

        return $instance;
    }

    /**
     * Kills the pvpython process and deletes the instance record from the database.
     *
     * @param Pvw_InstanceDao $instance instance DAO to kill
     * @throws Zend_Exception
     */
    public function killInstance($instance)
    {
        if ($instance->getPid()) {
            exec('kill -9 '.$instance->getPid());
        }

        UtilityComponent::rrmdir(UtilityComponent::getTempDirectory('pvw-data').'/'.$instance->getKey());

        /** @var Pvw_InstanceModel $instanceModel */
        $instanceModel = MidasLoader::loadModel('Instance', 'pvw');
        $instanceModel->delete($instance);
    }

    /**
     * Return whether or not the given instance is still running.
     *
     * @param $instance instance DAO
     * @return bool
     */
    public function isRunning($instance)
    {
        exec('ps '.$instance->getPid(), $output);

        return count($output) >= 2;
    }

    /**
     * Uses the admin-configured port settings and allocates the next
     * available port that isn't currently in use. If none are available, returns false.
     *
     * @return false|int
     * @throws Zend_Exception
     */
    private function _getNextOpenPort()
    {
        /** @var SettingModel $settingModel */
        $settingModel = MidasLoader::loadModel('Setting');
        $ports = $settingModel->getValueByName(MIDAS_PVW_PORTS_KEY, $this->moduleName);
        if (!$ports) {
            $ports = '9000,9001'; // some reasonable default
        }
        $ports = explode(',', $ports);
        foreach ($ports as $portEntry) {
            $portEntry = trim($portEntry);
            if (strpos($portEntry, '-') !== false) { // port range check
                list($start, $end) = explode('-', $portEntry);
                $start = (int) trim($start);
                $end = (int) trim($end);
                if ($start <= 0 || $end <= 0 || $start >= $end) {
                    throw new Zend_Exception('Port range invalid: '.$portEntry, 500);
                }
                for ($port = $start; $port <= $end; ++$port) {
                    if (!UtilityComponent::isPortListening($port)) {
                        return $port;
                    }
                }
            } elseif (!UtilityComponent::isPortListening($portEntry)
            ) { // single port check
                return $portEntry;
            }
        }

        return false;
    }

    /**
     * Symlink the item into a directory.
     *
     * @param ItemDao $itemDao
     * @param array $meshItems
     * @param Pvw_InstanceDao $instanceDao
     * @return string
     * @throws Zend_Exception
     */
    private function _createDataDir($itemDao, $meshItems, $instanceDao)
    {
        if (!is_dir(UtilityComponent::getTempDirectory('pvw-data'))) {
            mkdir(UtilityComponent::getTempDirectory('pvw-data'));
        }
        $path = UtilityComponent::getTempDirectory('pvw-data').'/'.$instanceDao->getKey();
        mkdir($path);
        mkdir($path.'/main');
        mkdir($path.'/surfaces');

        // Symlink main item into the main subdir
        /** @var ItemModel $itemModel */
        $itemModel = MidasLoader::loadModel('Item');

        /** @var ItemRevisionModel $revisionModel */
        $revisionModel = MidasLoader::loadModel('ItemRevision');
        $rev = $itemModel->getLastRevision($itemDao);
        if ($rev === false) {
            throw new Zend_Exception('The item has no revisions', MIDAS_INVALID_POLICY);
        }
        $bitstreams = $rev->getBitstreams();
        if (count($bitstreams) === 0) {
            throw new Zend_Exception('The item has no bitstreams', MIDAS_INVALID_POLICY);
        }
        $src = $bitstreams[0]->getFullpath();
        symlink($src, $path.'/main/'.$bitstreams[0]->getName());

        // Symlink all the surfaces into the surfaces subdir
        foreach ($meshItems as $meshItem) {
            $rev = $itemModel->getLastRevision($meshItem);
            if ($rev === false) {
                continue;
            }
            $bitstreams = $rev->getBitstreams();
            if (count($bitstreams) === 0) {
                continue;
            }
            $src = $bitstreams[0]->getFullpath();
            $linkPath = $path.'/surfaces/'.$meshItem->getKey().'_'.$bitstreams[0]->getName();
            symlink($src, $linkPath);

            // Write out any metadata properties in the ParaView namespace
            $fh = fopen($linkPath.'.properties', 'w');
            $metadata = $revisionModel->getMetadata($rev);
            foreach ($metadata as $field) {
                if (strtolower($field->getElement()) == 'paraview') {
                    fwrite($fh, $field->getQualifier().' '.$field->getValue()."\n");
                }
            }
            fclose($fh);
        }

        return $path;
    }
}
