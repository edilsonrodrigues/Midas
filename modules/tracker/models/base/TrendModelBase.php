<?php
/*=========================================================================
MIDAS Server
Copyright (c) Kitware SAS. 20 rue de la Villette. All rights reserved.
69328 Lyon, FRANCE.

See Copyright.txt for details.
This software is distributed WITHOUT ANY WARRANTY; without even
the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
PURPOSE.  See the above copyright notices for more information.
=========================================================================*/

/**
 * Trend Model Base
 */
abstract class Tracker_TrendModelBase extends Tracker_AppModel
{
  /** constructor*/
  public function __construct()
    {
    parent::__construct();
    $this->_name = 'tracker_trend';
    $this->_key = 'trend_id';
    $this->_mainData = array(
        'trend_id' => array('type' => MIDAS_DATA),
        'producer_id' => array('type' => MIDAS_DATA),
        'metric_name' => array('type' => MIDAS_DATA),
        'display_name' => array('type' => MIDAS_DATA),
        'unit' => array('type' => MIDAS_DATA),
        'config_item_id' => array('type' => MIDAS_DATA),
        'test_dataset_id' => array('type' => MIDAS_DATA),
        'truth_dataset_id' => array('type' => MIDAS_DATA),
        'producer' => array('type' => MIDAS_MANY_TO_ONE,
                            'model' => 'Producer',
                            'module' => $this->moduleName,
                            'parent_column' => 'producer_id',
                            'child_column' => 'producer_id'),
        'config_item' => array('type' => MIDAS_MANY_TO_ONE,
                                  'model' => 'Item',
                                  'parent_column' => 'config_item_id',
                                  'child_column' => 'item_id'),
        'test_dataset_item' => array('type' => MIDAS_MANY_TO_ONE,
                                      'model' => 'Item',
                                      'parent_column' => 'test_dataset_id',
                                      'child_column' => 'item_id'),
        'truth_dataset_item' => array('type' => MIDAS_MANY_TO_ONE,
                                      'model' => 'Item',
                                      'parent_column' => 'truth_dataset_id',
                                      'child_column' => 'item_id'),
        'scalars' => array('type' => MIDAS_ONE_TO_MANY,
                           'model' => 'Scalar',
                           'module' => $this->moduleName,
                           'parent_column' => 'trend_id',
                           'child_column' => 'trend_id')
      );
    $this->initialize();
    }

  public abstract function getMatch($producerId, $metricName, $configItemId, $testDatasetId, $truthDatasetId);
  public abstract function getAllByParams($params);
  public abstract function getScalars($trend, $startDate = null, $endDate = null, $userId = null);
  public abstract function getTrendsGroupByDatasets($producerDao);

  /** 
   * Override the default save to make sure that we explicitly set null values in the database
   */
  public function save($trendDao)
    {
    $trendDao->setExplicitNullFields = true;
    parent::save($trendDao);
    }

  /**
   * If the producer with the matching parameters exists, return it.
   * If not, it will create it and return it.
   */
  public function createIfNeeded($producerId, $metricName, $configItemId, $testDatasetId, $truthDatasetId)
    {
    $trend = $this->getMatch($producerId, $metricName, $configItemId, $testDatasetId, $truthDatasetId);
    if(!$trend)
      {
      $trend = MidasLoader::newDao('TrendDao', $this->moduleName);
      $trend->setProducerId($producerId);
      $trend->setMetricName($metricName);
      $trend->setDisplayName($metricName);
      $trend->setUnit('');
      if($configItemId != null)
        {
        $trend->setConfigItemId($configItemId);
        }
      if($testDatasetId != null)
        {
        $trend->setTestDatasetId($testDatasetId);
        }
      if($truthDatasetId != null)
        {
        $trend->setTruthDatasetId($truthDatasetId);
        }
      $this->save($trend);
      }
    return $trend;
    }

  /**
   * Delete the trend (deletes all child scalars as well)
   */
  public function delete($trend, $progressDao = null)
    {
    $scalarModel = MidasLoader::loadModel('Scalar', $this->moduleName);
    $notificationModel = MidasLoader::loadModel('ThresholdNotification', $this->moduleName);
    if($progressDao)
      {
      $progressModel = MidasLoader::loadModel('Progress');
      $progressDao->setMessage('Counting scalar points...');
      $progressModel->save($progressDao);
      }
    $scalars = $trend->getScalars();
    if($progressDao)
      {
      $progressDao->setMaximum(count($scalars));
      $progressModel->save($progressDao);
      $i = 0;
      }

    foreach($scalars as $scalar)
      {
      if($progressDao)
        {
        $i++;
        $message = 'Deleting scalars: '.$i.' of '.$progressDao->getMaximum();
        $progressModel->updateProgress($progressDao, $i, $message);
        }
      $scalarModel->delete($scalar);
      }
    $notificationModel->deleteByTrend($trend);
    parent::delete($trend);
    }
}