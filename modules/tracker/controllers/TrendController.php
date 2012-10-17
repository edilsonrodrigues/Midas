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
/** Trend controller*/
class Tracker_TrendController extends Tracker_AppController
{
  public $_models = array('Community');
  public $_moduleModels = array('Producer', 'Trend');

  /**
   * View a given trend
   * @param trendId The id of the trend to view
   * @param startDate The start date to retrieve scalars
   * @param endDate The end date to retrieve scalars
   */
  public function viewAction()
    {
    $trendId = $this->_getParam('trendId');
    $startDate = $this->_getParam('startDate');
    $endDate = $this->_getParam('endDate');
    if(!isset($trendId))
      {
      throw new Zend_Exception('Must pass trendId parameter');
      }
    $trend = $this->Tracker_Trend->load($trendId);
    $comm = $trend->getProducer()->getCommunity();
    if(!$this->Community->policyCheck($comm, $this->userSession->Dao, MIDAS_POLICY_READ))
      {
      throw new Zend_Exception('Read permission required on the community', 403);
      }
    $this->view->trend = $trend;
    $this->view->isAdmin = $this->Community->policyCheck($comm, $this->userSession->Dao, MIDAS_POLICY_ADMIN);
    $header = '<ul class="pathBrowser">';
    $header .= '<li class="pathFolder"><img alt="" src="'.$this->view->coreWebroot.'/public/images/icons/community.png" /><span><a href="'.$this->view->webroot.'/community/'.$comm->getKey().'#Trackers">'.$comm->getName().'</a></span></li>';
    $header .= '<li class="pathFolder"><img alt="" src="'.$this->view->coreWebroot.'/public/images/icons/cog_go.png" /><span><a href="'.$this->view->webroot.'/tracker/producer/view?producerId='.$trend->getProducer()->getKey().'">'.$trend->getProducer()->getDisplayName().'</a></span></li>';
    $header .= '<li class="pathFolder"><img alt="" src="'.$this->view->moduleWebroot.'/public/images/chart_line.png" /><span>'.$trend->getDisplayName().'</span></li>';
    $header .= '</ul>';
    $this->view->header = $header;

    // Provide sensible default date range
    if(!isset($startDate))
      {
      $startDate = strtotime('-1 month');
      }
    else
      {
      $startDate = strtotime($startDate);
      }
    if(!isset($endDate))
      {
      $endDate = time();
      }
    else
      {
      $endDate = strtotime($endDate);
      }

    $startDate = date('Y-m-d H:i:s', $startDate);
    $endDate = date('Y-m-d H:i:s', $endDate);
    $this->view->json['tracker']['scalars'] = $this->Tracker_Trend->getScalars($trend, $startDate, $endDate);
    $this->view->json['tracker']['trend'] = $trend;
    $this->view->json['tracker']['initialStartDate'] = date('n/j/Y', strtotime($startDate));
    $this->view->json['tracker']['initialEndDate'] = date('n/j/Y', strtotime($endDate));
    }

  /**
   * Ajax action for getting a new list of scalars for a specified date range
   * @param trendId The id of the trend to view
   * @param startDate The start date to retrieve scalars
   * @param endDate The end date to retrieve scalars
   */
  public function scalarsAction()
    {
    $this->disableView();
    $this->disableLayout();
    $trendId = $this->_getParam('trendId');
    $startDate = $this->_getParam('startDate');
    $endDate = $this->_getParam('endDate');
    if(!isset($trendId))
      {
      throw new Zend_Exception('Must pass trendId parameter');
      }
    $trend = $this->Tracker_Trend->load($trendId);
    $comm = $trend->getProducer()->getCommunity();
    if(!$this->Community->policyCheck($comm, $this->userSession->Dao, MIDAS_POLICY_READ))
      {
      throw new Zend_Exception('Read permission required on the community', 403);
      }
    $startDate = date('Y-m-d H:i:s', strtotime($startDate));
    $endDate = date('Y-m-d H:i:s', strtotime($endDate));

    echo JsonComponent::encode(array(
      'status' => 'ok',
      'scalars' => $this->Tracker_Trend->getScalars($trend, $startDate, $endDate)));
    }

  /**
   * Delete a trend, deleting all scalar records within it (requires community admin)
   */
  public function deleteAction()
    {
    // TODO (include progress reporting)
    }

  /**
   * Show the view for editing the trend information
   */
  public function editAction()
    {
    $trendId = $this->_getParam('trendId');

    if(!isset($trendId))
      {
      throw new Zend_Exception('Must pass trendId parameter');
      }
    $trend = $this->Tracker_Trend->load($trendId);
    $comm = $trend->getProducer()->getCommunity();
    if(!$this->Community->policyCheck($comm, $this->userSession->Dao, MIDAS_POLICY_ADMIN))
      {
      throw new Zend_Exception('Admin permission required on the community', 403);
      }
    $this->view->trend = $trend;

    $header = '<ul class="pathBrowser">';
    $header .= '<li class="pathFolder"><img alt="" src="'.$this->view->coreWebroot.'/public/images/icons/community.png" /><span><a href="'.$this->view->webroot.'/community/'.$comm->getKey().'#Trackers">'.$comm->getName().'</a></span></li>';
    $header .= '<li class="pathFolder"><img alt="" src="'.$this->view->coreWebroot.'/public/images/icons/cog_go.png" /><span><a href="'.$this->view->webroot.'/tracker/producer/view?producerId='.$trend->getProducer()->getKey().'">'.$trend->getProducer()->getDisplayName().'</a></span></li>';
    $header .= '<li class="pathFolder"><img alt="" src="'.$this->view->moduleWebroot.'/public/images/chart_line.png" /><span><a href="'.$this->view->webroot.'/tracker/trend/view?trendId='.$trend->getKey().'">'.$trend->getDisplayName().'</a></span></li>';
    $header .= '</ul>';
    $this->view->header = $header;
    }

  /**
   * Handle edit form submission
   * @param trendId The id of the trend to edit
   */
  public function editsubmitAction()
    {
    $this->disableLayout();
    $this->disableView();
    $trendId = $this->_getParam('trendId');

    if(!isset($trendId))
      {
      throw new Zend_Exception('Must pass trendId parameter');
      }
    $trend = $this->Tracker_Trend->load($trendId);
    if(!$this->Community->policyCheck($trend->getProducer()->getCommunity(), $this->userSession->Dao, MIDAS_POLICY_ADMIN))
      {
      throw new Zend_Exception('Admin permission required on the community', 403);
      }
    $metricName = $this->_getParam('metricName');
    $displayName = $this->_getParam('displayName');
    $unit = $this->_getParam('unit');
    $configItemId = $this->_getParam('configItemId');
    $testItemId = $this->_getParam('testItemId');
    $truthItemId = $this->_getParam('truthItemId');

    if(isset($metricName))
      {
      $trend->setMetricName($metricName);
      }
    if(isset($displayName))
      {
      $trend->setDisplayName($displayName);
      }
    if(isset($unit))
      {
      $trend->setUnit($unit);
      }
    if(isset($configItemId))
      {
      $trend->setConfigItemId($configItemId);
      }
    if(isset($testItemId))
      {
      $trend->setTestDatasetId($testItemId);
      }
    if(isset($truthItemId))
      {
      $trend->setTruthDatasetId($truthItemId);
      }
    $this->Tracker_Trend->save($trend);
    echo JsonComponent::encode(array('status' => 'ok', 'message' => 'Changes saved'));
    }
}//end class