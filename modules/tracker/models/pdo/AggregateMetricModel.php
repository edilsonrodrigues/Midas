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

require_once BASE_PATH.'/modules/tracker/models/base/AggregateMetricModelBase.php';

/** AggregateMetric model for the tracker module. */
class Tracker_AggregateMetricModel extends Tracker_AggregateMetricModelBase
{
    /**
     * Compute a percentile value out of the passed in array.
     *
     * @param array values the set of values to compute percentile over
     * @param array params an array with the first element containing the desired percentile value
     * @return false | float the desired percentile value extracted from values
     */
    protected function percentile($values, $params)
    {
        if (!$params) {
            return false;
        }
        $percentile = $params[0];
        $percentile = $percentile > 100.0 ? 100.0 : $percentile;
        $percentile = $percentile < 0.0 ? 0.0 : $percentile;

        asort($values);
        $ind = round(($percentile / 100.0) * count($values)) - 1;
        // Ind may be below 0, in this case the closest value is the 0th index.
        $ind = $ind < 0 ? 0 : $ind;

        return $values[$ind];
    }

    /**
     * Return a sorted array of input scalars that would be used by an aggregate metric for the submission based on the spec.
     *
     * @param Tracker_AggregateMetricSpecDao $aggregateMetricSpecDao spec DAO
     * @param Tracker_SubmissionDao $submissionDao submission DAO
     * @return false | array array of scalar values that would be input to the aggregate metric
     */
    public function getAggregateMetricInputValuesForSubmission($aggregateMetricSpecDao, $submissionDao)
    {
        if (is_null($aggregateMetricSpecDao) || $aggregateMetricSpecDao === false) {
            return false;
        }
        if (is_null($submissionDao) || $submissionDao === false) {
            return false;
        }

        $spec = $this->parseSpec($aggregateMetricSpecDao->getSpec());
        $metricName = $spec['metric_name'];
        // Get the list of relevant trend_ids.
        $sql = $this->database->select()->setIntegrityCheck(false)
            ->from('tracker_trend', array('trend_id'))
            ->join('tracker_trendgroup', 'tracker_trendgroup.trendgroup_id=tracker_trend.trendgroup_id')
            ->where('key_metric = ?', 1)
            ->where('producer_id = ?', $aggregateMetricSpecDao->getProducerId())
            ->where('metric_name = ?', $metricName);
        $rows = $this->database->fetchAll($sql);
        if (count($rows) === 0) {
            return false;
        }
        $trendIds = array();
        /** @var Zend_Db_Table_Row_Abstract $row */
        foreach ($rows as $row) {
            $trendIds[] = $row['trend_id'];
        }

        // Get all the scalar values from these trends in the submission.
        $sql = $this->database->select()->setIntegrityCheck(false)
            ->from('tracker_scalar')
            ->join(
                'tracker_submission',
                'tracker_scalar.submission_id = tracker_submission.submission_id',
                array()
            )
            ->where('tracker_submission.submission_id = ?', $submissionDao->getKey())
            ->where('tracker_scalar.trend_id IN (?)', $trendIds);
        $rows = $this->database->fetchAll($sql);
        if (count($rows) === 0) {
            return false;
        }
        $values = array();
        /** @var Zend_Db_Table_Row_Abstract $row */
        foreach ($rows as $row) {
            $values[] = floatval($row['value']);
        }
        sort($values);

        return $values;
    }

    /**
     * Compute on the fly the AggregateMetricDao for the submission and the
     * aggregate metric spec, without saving any results.
     *
     * @param Tracker_AggregateMetricSpecDao $aggregateMetricSpecDao spec DAO
     * @param Tracker_SubmissionDao $submissionDao submission DAO
     * @return false | Tracker_AggregateMetricDao metric DAO computed on the submission from the spec
     */
    public function computeAggregateMetricForSubmission($aggregateMetricSpecDao, $submissionDao)
    {
        $values = $this->getAggregateMetricInputValuesForSubmission($aggregateMetricSpecDao, $submissionDao);
        if ($values === false) {
            return false;
        }
        $spec = $this->parseSpec($aggregateMetricSpecDao->getSpec());
        $aggregationMethod = $spec['aggregation_method'];
        $aggregationParams = $spec['params'];
        $computedValue = $this->$aggregationMethod($values, $aggregationParams);
        if ($computedValue === false) {
            return false;
        } else {
            /** @var Tracker_AggregateMetricDao $aggregateMetricDao */
            $aggregateMetricDao = MidasLoader::newDao('AggregateMetricDao', 'tracker');
            $aggregateMetricDao->setAggregateMetricSpecId($aggregateMetricSpecDao->getAggregateMetricSpecId());
            $aggregateMetricDao->setSubmissionId($submissionDao->getSubmissionId());
            $aggregateMetricDao->setValue($computedValue);

            return $aggregateMetricDao;
        }
    }

    /**
     * Delete any existing AggregateMetric for the submission and spec, then compute and save
     * an AggregateMetric for the submission and spec, returning the AggregateMetric.
     *
     * @param Tracker_AggregateMetricSpecDao $aggregateMetricSpecDao spec DAO
     * @param Tracker_SubmissionDao $submissionDao submission DAO
     * @return false | Tracker_AggregateMetricDao metric DAO computed on the submission from the spec
     */
    public function updateAggregateMetricForSubmission($aggregateMetricSpecDao, $submissionDao)
    {
        if (is_null($aggregateMetricSpecDao) || $aggregateMetricSpecDao === false) {
            return false;
        }
        if (is_null($submissionDao) || $submissionDao === false) {
            return false;
        }
        Zend_Registry::get('dbAdapter')->delete($this->_name, array(
            'aggregate_metric_spec_id = '.$aggregateMetricSpecDao->getAggregateMetricSpecId(),
            'submission_id = '.$submissionDao->getSubmissionId(),
        ));
        $aggregateMetricDao = $this->computeAggregateMetricForSubmission($aggregateMetricSpecDao, $submissionDao);
        if ($aggregateMetricDao === false) {
            return false;
        }
        $this->save($aggregateMetricDao);

        return $aggregateMetricDao;
    }

    /**
     * Compute on the fly all AggregateMetricDaos for the submission, without
     * saving any results.
     *
     * @param Tracker_SubmissionDao $submissionDao submission DAO
     * @return false | array AggregateMetric DAOs all AggregateMetricDaos for the
     * SubmissionDao
     */
    public function computeAggregateMetricsForSubmission($submissionDao)
    {
        if (is_null($submissionDao) || $submissionDao === false) {
            return false;
        }
        /** @var AggregateMetricSpecModel $aggregateMetricSpecModel */
        $aggregateMetricSpecModel = MidasLoader::loadModel('AggregateMetricSpec', 'tracker');
        $specDaos = $aggregateMetricSpecModel->getAggregateMetricSpecsForSubmission($submissionDao);
        if ($specDaos === false) {
            return false;
        }
        $aggregateMetrics = array();
        /** @var Tracker_AggregateMetricSpecDao $specDao */
        foreach ($specDaos as $specDao) {
            $aggregateMetricDao = $this->computeAggregateMetricForSubmission($specDao, $submissionDao);
            if ($aggregateMetricDao !== false) {
                $aggregateMetrics[] = $aggregateMetricDao;
            }
        }

        return $aggregateMetrics;
    }

    /**
     * Delete all existing AggregateMetrics for the submission, then compute and save all
     * AggregateMetrics for the submission, returning the AggregateMetrics.
     *
     * @param Tracker_SubmissionDao $submissionDao submission DAO
     * @return false | array AggregateMetric DAOs all AggregateMetricDaos for the
     * SubmissionDao
     */
    public function updateAggregateMetricsForSubmission($submissionDao)
    {
        if (is_null($submissionDao) || $submissionDao === false) {
            return false;
        }
        Zend_Registry::get('dbAdapter')->delete($this->_name, 'submission_id = '.$submissionDao->getSubmissionId());
        /** @var array $computedMetrics */
        $computedMetrics = $this->computeAggregateMetricsForSubmission($submissionDao);
        if ($computedMetrics === false) {
            return false;
        }
        $updatedMetrics = array();
        /* @var Tracker_AggregateMetricDao $computedMetricDao */
        foreach ($computedMetrics as $computedMetricDao) {
            if ($computedMetricDao != false) {
                $this->save($computedMetricDao);
                $updatedMetrics[] = $computedMetricDao;
            }
        }

        return $updatedMetrics;
    }

    /**
     * Return one existing AggregateMetric tied to the submission and spec.
     *
     * @param Tracker_AggregateMetricSpecDao $aggregateMetricSpecDao spec DAO
     * @param Tracker_SubmissionDao $submissionDao submission DAO
     * @return false | Tracker_AggregateMetricDao the AggregateMetricDao linked to the
     * SubmissionDao and AggregateMetricSpecDao
     */
    public function getAggregateMetricForSubmission($aggregateMetricSpecDao, $submissionDao)
    {
        if (is_null($aggregateMetricSpecDao) || $aggregateMetricSpecDao === false) {
            return false;
        }
        if (is_null($submissionDao) || $submissionDao === false) {
            return false;
        }
        $sql = $this->database->select()->setIntegrityCheck(false)
            ->from('tracker_aggregate_metric')
            ->where('aggregate_metric_spec_id = ?', $aggregateMetricSpecDao->getAggregateMetricSpecId())
            ->where('submission_id = ?', $submissionDao->getSubmissionId());

        /** @var Zend_Db_Table_Row_Abstract $row */
        $row = $this->database->fetchRow($sql);

        return $this->initDao('AggregateMetric', $row, $this->moduleName);
    }

    /**
     * Return a list of submission ids mapped to an existing aggregate metric for
     * that submission and the single aggregate metric spec, sorted in ascending order
     * of submission submit_time.
     *
     * @param Tracker_AggregateMetricSpecDao $aggregateMetricSpecDao spec DAO
     * @param Tracker_SubmissionDao $submissionDao submission DAO
     * @return false | array keys are submission_id and values are Tracker_AggregateMetricDao
     * for that SubmissionDao and AggregateMetricSpecDao, sorted in ascending order of
     * SubmissionDao submit_time
     */
    public function getAggregateMetricsForSubmissions($aggregateMetricSpecDao, $submissionDaos)
    {
        if (is_null($aggregateMetricSpecDao) || $aggregateMetricSpecDao === false) {
            return false;
        }
        if (is_null($submissionDaos) || count($submissionDaos) === 0) {
            return false;
        }

        $submissionsBySubmissionId = array();
        /** @var Tracker_SubmissionDao $submissionDao */
        foreach ($submissionDaos as $submissionDao) {
            $submissionsBySubmissionId[$submissionDao->getSubmissionId()] = $submissionDao;
        }
        $sql = $this->database->select()->setIntegrityCheck(false)
            ->from('tracker_aggregate_metric')
            ->where('aggregate_metric_spec_id = ?', $aggregateMetricSpecDao->getAggregateMetricSpecId())
            ->where('submission_id IN (?)', array_keys($submissionsBySubmissionId));
        $rows = $this->database->fetchAll($sql);
        if (count($rows) === 0) {
            return false;
        }
        $aggregateMetricDaosBySubmissionId = array();
        /** @var Zend_Db_Table_Row_Abstract $row */
        foreach ($rows as $row) {
            /** @var Tracker_AggregateMetricDao aggregateMetricDao */
            $aggregateMetricDao = $this->initDao('AggregateMetric', $row, $this->moduleName);
            $aggregateMetricDaosBySubmissionId[$aggregateMetricDao->getSubmissionId()] = $aggregateMetricDao;
        }

        // Sort by ascending submission submit_time order.
        $cmpBySubmitTime = function ($a, $b) use ($submissionsBySubmissionId) {
            $submitTimeA = strtotime($submissionsBySubmissionId[$a]->getSubmitTime());
            $submitTimeB = strtotime($submissionsBySubmissionId[$b]->getSubmitTime());

            return $submitTimeA - $submitTimeB;
        };
        uksort($aggregateMetricDaosBySubmissionId, $cmpBySubmitTime);

        return $aggregateMetricDaosBySubmissionId;
    }

    /**
     * Return an associative array with Aggregate Metric Names as keys and
     * a list of calculated Aggregate Metric Values as values for the
     * $producerDao and $branch, within the days interval up to $lastDate,
     * where the values in the lists are sorted by ascending submission time,
     * but there is no guarantee that the values in separate lists match up
     * in time or frequency.
     *
     * @param Tracker_ProducerDao $producerDao producer DAO
     * @param false|date $lastDate the end of the datetime interval, if false
     * (the default) is passed, $lastDate will be set to midnight today
     * @param int $daysInterval the number of days in the total datetime
     * interval that ends with $lastDate, defaults to 7, which is a week
     * @param string $branch the branch tied to submissions for calculated
     * metrics, defaults to 'master'
     * @return array keys are AggregateMetricSpecDao Name, values are lists of
     * AggregateMetric values calculated that match the input param filters and
     * are sorted in their individual lists in ascending submission time order
     */
    public function getAggregateMetricsSeries($producerDao, $lastDate = false, $daysInterval = 7, $branch = 'master')
    {
        if (is_null($producerDao) || $producerDao === false) {
            return array();
        }
        if ($lastDate === false) {
            $lastDate = date('Y-m-d').' 23:59:59';
        }
        $firstDate = new DateTime($lastDate);
        $firstDate->modify('-'.$daysInterval.' day');
        $sql = $this->database->select()->setIntegrityCheck(false)
            ->from(array('am' => 'tracker_aggregate_metric'),
                   array('ams.name', 'am.value'))
            ->join(array('u' => 'tracker_submission'),
                   'am.submission_id = u.submission_id',
                   array())
            ->join(array('ams' => 'tracker_aggregate_metric_spec'),
                   'ams.aggregate_metric_spec_id = am.aggregate_metric_spec_id',
                   array())
            ->where('u.branch = ?', $branch)
            ->where('u.producer_id = ?', $producerDao->getProducerId())
            ->where('u.submit_time > ?', $firstDate->format('Y-m-d H:i:s'))
            ->where('u.submit_time <= ?', $lastDate)
            ->order('ams.name')
            ->order('u.submit_time');

        /** @var array $rows */
        $rows = $this->database->fetchAll($sql);
        if (count($rows) === 0) {
            return array();
        }

        $metricsSeries = array();
        /** @var Zend_Db_Table_Row_Abstract $row */
        foreach ($rows as $row) {
            $seriesName = $row['name'];
            if (!array_key_exists($seriesName, $metricsSeries)) {
                $metricsSeries[$seriesName] = array();
            }
            $metricsSeries[$seriesName][] = floatval($row['value']);
        }

        return $metricsSeries;
    }
}
