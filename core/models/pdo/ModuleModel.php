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

require_once BASE_PATH.'/core/models/base/ModuleModelBase.php';

/** Module model. */
class ModuleModel extends ModuleModelBase
{
    /**
     * Return a module given its name.
     *
     * @param string $name name
     * @return false|ModuleDao or false on failure
     * @throws Zend_Exception
     */
    public function getByName($name)
    {
        $row = $this->database->fetchRow(
            $this->database->select()->where('name = ?', $name)
        );

        return $this->initDao('Module', $row);
    }

    /**
     * Return a module given its UUID.
     *
     * @param string $uuid UUID
     * @return false|ModuleDao module DAO or false on failure
     * @throws Zend_Exception
     */
    public function getByUuid($uuid)
    {
        $row = $this->database->fetchRow(
            $this->database->select()->where('uuid = ?', $uuid)
        );

        return $this->initDao('Module', $row);
    }

    /**
     * Return the modules that are enabled.
     *
     * @param bool $enabled true if a module is enabled
     * @return array module DAOs
     * @throws Zend_Exception
     */
    public function getEnabled($enabled = true)
    {
        $rows = $this->database->fetchAll(
            $this->database->select()->where('enabled = ?', (int) $enabled)
        );
        $moduleDaos = array();
        foreach ($rows as $row) {
            $moduleDaos[] = $this->initDao('Module', $row);
        }

        return $moduleDaos;
    }
}
