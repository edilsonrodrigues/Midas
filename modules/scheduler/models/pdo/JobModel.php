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

require_once BASE_PATH.'/modules/scheduler/models/base/JobModelBase.php';

/** job model */
class Scheduler_JobModel extends Scheduler_JobModelBase
{
    /** get by tasks */
    public function getJobsByTask($task)
    {
        if (!is_string($task)) {
            throw new Zend_Exception('Error Params');
        }
        $sql = $this->database->select()->setIntegrityCheck(false)->where('task = ?', $task)->where(
            'status = ?',
            SCHEDULER_JOB_STATUS_TORUN
        );

        $rowset = $this->database->fetchAll($sql);
        $return = array();
        foreach ($rowset as $row) {
            $tmpDao = $this->initDao('Job', $row, 'scheduler');
            $return[] = $tmpDao;
            unset($tmpDao);
        }

        return $return;
    }

    /** get by tasks */
    public function getJobsByTaskAndCreator($task, $userDao)
    {
        if (!is_string($task) && !$userDao instanceof UserDao) {
            throw new Zend_Exception('Error Params');
        }
        $sql = $this->database->select()->setIntegrityCheck(false)->where('task = ?', $task)->where(
            'creator_id = ?',
            $userDao->getKey()
        )->where(
            'status = ?',
            SCHEDULER_JOB_STATUS_TORUN
        )->order('fire_time DESC');

        $rowset = $this->database->fetchAll($sql);
        $return = array();
        foreach ($rowset as $row) {
            $tmpDao = $this->initDao('Job', $row, 'scheduler');
            $return[] = $tmpDao;
            unset($tmpDao);
        }

        return $return;
    }

    /** get the jobs that should be run on the current run invocation */
    public function getJobsToRun($limit = 1000)
    {
        $load = $this->getServerLoad();
        $minPriority = MIDAS_EVENT_PRIORITY_LOW;
        if (!empty($load)) {
            if ($load[0] > 80 || $load[1] > 80) { // don't run anything
                return array();
            }
            $minPriority = MIDAS_EVENT_PRIORITY_HIGH;
            if ($load[0] < 40 || $load[1] < 40) {
                $minPriority = MIDAS_EVENT_PRIORITY_NORMAL;
            }
            if ($load[0] < 20 && $load[1] < 20) {
                $minPriority = MIDAS_EVENT_PRIORITY_LOW;
            }
        }

        $sql = $this->database->select()->setIntegrityCheck(false)->where('priority >= ?', $minPriority)->where(
            'status = ?',
            SCHEDULER_JOB_STATUS_TORUN
        )->where('fire_time <= ?', date('Y-m-d H:i:s'))->limit($limit)->order(array('priority DESC', 'fire_time ASC'));
        $rowset = $this->database->fetchAll($sql);
        $return = array();
        foreach ($rowset as $row) {
            $tmpDao = $this->initDao('Job', $row, 'scheduler');
            $return[] = $tmpDao;
            unset($tmpDao);
        }

        return $return;
    }

    /** get all jobs scheduled to run in the future */
    public function getFutureScheduledJobs()
    {
        $sql = $this->database->select()->setIntegrityCheck(false)->where(
            'status = ?',
            SCHEDULER_JOB_STATUS_TORUN
        )->where(
            'fire_time >= ?',
            date('Y-m-d H:i:s')
        )->order(array('fire_time ASC'));
        $rowset = $this->database->fetchAll($sql);
        $return = array();
        foreach ($rowset as $row) {
            $tmpDao = $this->initDao('Job', $row, 'scheduler');
            $return[] = $tmpDao;
            unset($tmpDao);
        }

        return $return;
    }

    /** Get the last 'limit' jobs that have failed. The default value for limit is 10. */
    public function getLastErrors($limit = 10)
    {
        $this->getServerLoad();
        $sql = $this->database->select()->setIntegrityCheck(false)->where(
            'status = ?',
            SCHEDULER_JOB_STATUS_FAILED
        )->order(array('fire_time DESC'))->limit($limit);
        $rowset = $this->database->fetchAll($sql);
        $return = array();
        foreach ($rowset as $row) {
            $tmpDao = $this->initDao('Job', $row, 'scheduler');
            $return[] = $tmpDao;
            unset($tmpDao);
        }

        return $return;
    }

    /**
     * Removes all references to the given userId in the job table by replacing
     * creator_id with NULL.
     */
    public function removeUserReferences($userId)
    {
        $this->database->update(array('creator_id' => null), array('creator_id = ?' => $userId));
    }
}
