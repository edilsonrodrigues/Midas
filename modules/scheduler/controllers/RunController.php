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

/** Run controller for the scheduler module */
class Scheduler_RunController extends Scheduler_AppController
{
    public $_models = array('Setting');
    public $_moduleModels = array('Job', 'JobLog');
    public $_components = array('Json');

    /** Index action */
    public function indexAction()
    {
        $startTime = time();
        $this->disableLayout();
        $this->disableView();

        $lastStart = $this->Setting->getValueByName('lastrun', $this->moduleName);
        if ($lastStart !== null && $startTime < (int) $lastStart + 270) {
            throw new Zend_Exception(
                'The scheduler is already running. Please wait for it to complete before invoking again.'
            );
        }
        ignore_user_abort(true);

        $this->Setting->setConfig('lastrun', $startTime, $this->moduleName);

        $id = $this->getParam('id');

        $validator = new Zend_Validate_Digits();
        if (!$validator->isValid($id)) {
            throw new Zend_Exception('Must specify an id parameter');
        }

        if (isset($id)) {
            $jobs = $this->Scheduler_Job->load($id);
            if ($jobs == false) {
                throw new Zend_Exception('Unable to load job');
            }
            $jobs = array($jobs);
        } else {
            $jobs = $this->Scheduler_Job->getJobsToRun(1000);
        }
        $modules = Zend_Registry::get('notifier')->modules;
        $tasks = Zend_Registry::get('notifier')->tasks;
        foreach ($jobs as $job) {
            if (time() - $startTime > 270
            ) { // After 4.5 minutes, do not start any new tasks
                break;
            }
            $job->setStatus(SCHEDULER_JOB_STATUS_STARTED);
            if ($job->getRunOnlyOnce() == 0) {
                $interval = $job->getTimeInterval();
                $currTime = time();
                $firetime = strtotime($job->getFireTime()) + $interval;
                while ($firetime < $currTime && $interval > 0) {
                    $firetime += $interval; // only schedule jobs for the future
                }
                $job->setFireTime(date('Y-m-d H:i:s', $firetime));
            }
            $job->setTimeLastFired(date('Y-m-d H:i:s'));
            $this->Scheduler_Job->save($job);
            try {
                if (!isset($tasks[$job->getTask()])) {
                    throw new Zend_Exception('Unable to find task '.$job->getTask());
                }
                $log = call_user_func(
                    array($modules[$tasks[$job->getTask()]['module']], $tasks[$job->getTask()]['method']),
                    JsonComponent::decode($job->getParams())
                );
                if ($log && is_string($log) && $log != '') {
                    $this->Scheduler_JobLog->saveLog($job, $log);
                }
            } catch (Zend_Exception $exc) {
                $job->setStatus(SCHEDULER_JOB_STATUS_FAILED);
                $this->Scheduler_Job->save($job);
                $this->Scheduler_JobLog->saveLog($job, $exc->getMessage().' - '.$exc->getTraceAsString());
                continue;
            }
            if ($job->getRunOnlyOnce() == 0) {
                $job->setStatus(SCHEDULER_JOB_STATUS_TORUN);
            } else {
                $job->setStatus(SCHEDULER_JOB_STATUS_DONE);
            }
            $this->Scheduler_Job->save($job);
        }
        $lastRunSetting = $this->Setting->getDaoByName('lastrun', $this->moduleName);
        if ($lastRunSetting) {
            $this->Setting->delete($lastRunSetting);
        }
    }
}
