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
require_once BASE_PATH.'/modules/communityagreement/models/base/AgreementModelBase.php';

/** agreement pdo model */

class Communityagreement_AgreementModel extends Communityagreement_AgreementModelBase
{
  /** Get all */
 function getAll()
    {
    $sql = $this->database->select();
    $rowset = $this->database->fetchAll($sql);
    $rowsetAnalysed = array();
    foreach($rowset as $keyRow => $row)
      {
      $tmpDao = $this->initDao('Agreement', $row, 'communityagreement');
      $rowsetAnalysed[] = $tmpDao;
      }
    return $rowsetAnalysed;
    }
    
  /** Get an agreement by communityid */
  function getByCommunityId($community_id)
    {
    $row = $this->database->fetchRow($this->database->select()->where('community_id=?', $community_id));
    $dao = $this->initDao('Agreement', $row, 'communityagreement');
    return $dao;
    } 
}  // end class
