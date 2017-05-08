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

/** ItemModelBase */
abstract class ItemModelBase extends AppModel
{
    /** Constructor */
    public function __construct()
    {
        parent::__construct();
        $this->_name = 'item';
        $this->_key = 'item_id';

        $this->_mainData = array(
            'item_id' => array('type' => MIDAS_DATA),
            'name' => array('type' => MIDAS_DATA),
            'description' => array('type' => MIDAS_DATA),
            'type' => array('type' => MIDAS_DATA),
            'sizebytes' => array('type' => MIDAS_DATA),
            'date_creation' => array('type' => MIDAS_DATA),
            'date_update' => array('type' => MIDAS_DATA),
            'thumbnail_id' => array('type' => MIDAS_DATA),
            'view' => array('type' => MIDAS_DATA),
            'download' => array('type' => MIDAS_DATA),
            'privacy_status' => array('type' => MIDAS_DATA),
            'uuid' => array('type' => MIDAS_DATA),
            'folders' => array(
                'type' => MIDAS_MANY_TO_MANY,
                'model' => 'Folder',
                'table' => 'item2folder',
                'parent_column' => 'item_id',
                'child_column' => 'folder_id',
            ),
            'revisions' => array(
                'type' => MIDAS_ONE_TO_MANY,
                'model' => 'ItemRevision',
                'parent_column' => 'item_id',
                'child_column' => 'item_id',
            ),
            'itempolicygroup' => array(
                'type' => MIDAS_ONE_TO_MANY,
                'model' => 'Itempolicygroup',
                'parent_column' => 'item_id',
                'child_column' => 'item_id',
            ),
            'itempolicyuser' => array(
                'type' => MIDAS_ONE_TO_MANY,
                'model' => 'Itempolicyuser',
                'parent_column' => 'item_id',
                'child_column' => 'item_id',
            ),
        );
        $this->initialize(); // required
    }

    /** Get owned by user */
    abstract public function getOwnedByUser($userDao, $limit = 20);

    /** Get shared to user */
    abstract public function getSharedToUser($userDao, $limit = 20);

    /** Check policy */
    abstract public function policyCheck($itemdao, $userDao = null, $policy = 0);

    /** Get last revision */
    abstract public function getLastRevision($itemdao);

    /** Get most popular items */
    abstract public function getMostPopulars($userDao, $limit = 20);

    /** Get random thumbnails */
    abstract public function getRandomThumbnails($userDao = null, $policy = 0, $limit = 10, $thumbnailFilter = false);

    /** Get by UUID */
    abstract public function getByUuid($uuid);

    /** Get all */
    abstract public function getAll();

    /** Get items from search */
    abstract public function getItemsFromSearch($searchterm, $userDao, $limit = 14, $group = true, $order = 'view');

    /** Get by name */
    abstract public function getByName($name);

    /** Get by name and folder id */
    abstract public function getByNameAndFolderId($name, $folderId);

    /** Get by name and folder name */
    abstract public function getByNameAndFolderName($name, $folderName);

    /** Iterate with callback */
    abstract public function iterateWithCallback($callback, $paramName = 'item', $otherParams = array());

    /** Get total count */
    abstract public function getTotalCount();

    /** Get max policy */
    abstract public function getMaxPolicy($itemId, $user);

    /** Exists in folder */
    abstract public function existsInFolder($name, $folder);

    /** Update item name */
    abstract public function updateItemName($name, $parent);

    /** copy another item's policies */
    public function copyItemPolicies($itemdao, $referenceItemdao, $feeddao = null)
    {
        if (!$itemdao instanceof ItemDao || !$referenceItemdao instanceof ItemDao) {
            throw new Zend_Exception('Error in param itemdao or referenceItemdao when copying parent policies.');
        }
        $groupPolicies = $referenceItemdao->getItempolicygroup();
        $userPolicies = $referenceItemdao->getItempolicyuser();

        /** @var ItempolicygroupModel $ItempolicygroupModel */
        $ItempolicygroupModel = MidasLoader::loadModel('Itempolicygroup');
        foreach ($groupPolicies as $key => $policy) {
            $ItempolicygroupModel->createPolicy($policy->getGroup(), $itemdao, $policy->getPolicy());
        }

        /** @var ItempolicyuserModel $ItempolicyuserModel */
        $ItempolicyuserModel = MidasLoader::loadModel('Itempolicyuser');
        foreach ($userPolicies as $key => $policy) {
            $ItempolicyuserModel->createPolicy($policy->getUser(), $itemdao, $policy->getPolicy());
        }

        if ($feeddao != null && $feeddao instanceof FeedDao) {
            /** @var FeedpolicygroupModel $FeedpolicygroupModel */
            $FeedpolicygroupModel = MidasLoader::loadModel('Feedpolicygroup');
            foreach ($groupPolicies as $key => $policy) {
                $FeedpolicygroupModel->createPolicy($policy->getGroup(), $feeddao, $policy->getPolicy());
            }

            /** @var FeedpolicyuserModel $FeedpolicyuserModel */
            $FeedpolicyuserModel = MidasLoader::loadModel('Feedpolicyuser');
            foreach ($userPolicies as $key => $policy) {
                $FeedpolicyuserModel->createPolicy($policy->getUser(), $feeddao, $policy->getPolicy());
            }
        }
    }

    /** delete an item */
    public function delete($dao)
    {
        if (!$dao instanceof ItemDao) {
            throw new Zend_Exception('You must pass an item dao to ItemModel::delete');
        }
        Zend_Registry::get('notifier')->callback('CALLBACK_CORE_ITEM_DELETED', array('item' => $dao));
        parent::delete($dao);
    }

    /**
     * Default save override.
     *
     * @param dao The item dao to save
     * @param metadataChanged (bool, default = true) This parameter is passed to the
     *                        CALLBACK_CORE_ITEM_SAVED and should only be set to true on the
     *                        final save of the item in the controller's execution
     */
    public function save($dao, $metadataChanged = true)
    {
        if (!isset($dao->uuid) || empty($dao->uuid)) {
            /** @var UuidComponent $uuidComponent */
            $uuidComponent = MidasLoader::loadComponent('Uuid');
            $dao->setUuid($uuidComponent->generate());
        }
        if (!isset($dao->date_creation) || empty($dao->date_creation)) {
            $dao->setDateCreation(date('Y-m-d H:i:s'));
        }
        if (!isset($dao->type) || empty($dao->type)) {
            $dao->setType(0);
        }
        $dao->setDateUpdate(date('Y-m-d H:i:s'));
        $dao->setDescription(UtilityComponent::filterHtmlTags($dao->getDescription()));
        parent::save($dao);

        Zend_Registry::get('notifier')->callback(
            'CALLBACK_CORE_ITEM_SAVED',
            array('item' => $dao, 'metadataChanged' => $metadataChanged)
        );
    }

    /** copy parent folder policies */
    public function copyParentPolicies($itemdao, $folderdao, $feeddao = null)
    {
        if (!$itemdao instanceof ItemDao || !$folderdao instanceof FolderDao) {
            throw new Zend_Exception('Error in param itemdao or folderdao when copying parent policies.');
        }
        $groupPolicies = $folderdao->getFolderpolicygroup();
        $userPolicies = $folderdao->getFolderpolicyuser();

        /** @var ItempolicygroupModel $ItempolicygroupModel */
        $ItempolicygroupModel = MidasLoader::loadModel('Itempolicygroup');
        foreach ($groupPolicies as $policy) {
            $ItempolicygroupModel->createPolicy($policy->getGroup(), $itemdao, $policy->getPolicy());
        }

        /** @var ItempolicyuserModel $ItempolicyuserModel */
        $ItempolicyuserModel = MidasLoader::loadModel('Itempolicyuser');
        foreach ($userPolicies as $policy) {
            $ItempolicyuserModel->createPolicy($policy->getUser(), $itemdao, $policy->getPolicy());
        }

        if ($feeddao != null && $feeddao instanceof FeedDao) {
            /** @var FeedpolicygroupModel $FeedpolicygroupModel */
            $FeedpolicygroupModel = MidasLoader::loadModel('Feedpolicygroup');
            foreach ($groupPolicies as $policy) {
                $FeedpolicygroupModel->createPolicy($policy->getGroup(), $feeddao, $policy->getPolicy());
            }

            /** @var FeedpolicyuserModel $FeedpolicyuserModel */
            $FeedpolicyuserModel = MidasLoader::loadModel('Feedpolicyuser');
            foreach ($userPolicies as $policy) {
                $FeedpolicyuserModel->createPolicy($policy->getUser(), $feeddao, $policy->getPolicy());
            }
        }
    }

    /** Grant read only permission for an item in the target folder
     * share an item to destination folder (grant read-only permission to users/groups who can
     * access the destination folder ).
     *
     * @param  ItemDao $itemDao the item to be shared
     * @param  FolderDao $folderDao destination folder
     * @throws Zend_Exception on invalid input parameters (itemDao and folderDao)
     */
    public function addReadonlyPolicy($itemdao, $folderdao)
    {
        if (!$itemdao instanceof ItemDao || !$folderdao instanceof FolderDao) {
            throw new Zend_Exception('Error in itemdao or folderdao when adding read only policy.');
        }
        $groupPolicies = $folderdao->getFolderpolicygroup();
        $existingGroupPolicies = $itemdao->getItempolicygroup();
        $existingGroups = array();
        foreach ($existingGroupPolicies as $policy) {
            $group = $policy->getGroup();
            if (in_array($group, $existingGroups)) {
                array_push($existingGroups, $group);
            }
        }

        /** @var ItempolicygroupModel $ItempolicygroupModel */
        $ItempolicygroupModel = MidasLoader::loadModel('Itempolicygroup');
        foreach ($groupPolicies as $policy) {
            $newGroup = $policy->getGroup();
            if (!in_array($newGroup, $existingGroups)) {
                $ItempolicygroupModel->createPolicy($newGroup, $itemdao, MIDAS_POLICY_READ);
            }
        }
    }

    /**
     * Duplicate an item in destination folder/community.
     *
     * Create a new item (same as old one) in destination folder/community. The new item
     * have the same metadata and revisions with the old one, but its owner is set as the
     * input userDao parameter (who run this operation) and access policy is based on
     * the input folderDao parameter (destination folder)
     *
     * @param ItemDao $itemDao the item to be duplicated
     * @param UserDao $userDao the user who run this operation
     * @param FolderDao $folderDao destination folder
     * @return ItemDao
     * @throws Zend_Exception on invalid input parameters (itemDao, userDao and folderDao)
     */
    public function duplicateItem($itemDao, $userDao, $folderDao)
    {
        if (!$itemDao instanceof ItemDao || !$folderDao instanceof FolderDao) {
            throw new Zend_Exception('Error in ItemDao or FolderDao when duplicating item');
        }
        if (!$userDao instanceof UserDao) {
            throw new Zend_Exception('Should be an user.');
        }

        /** @var BitstreamModel $BitstreamModel */
        $BitstreamModel = MidasLoader::loadModel('Bitstream');

        $name = $itemDao->getName();
        $description = $itemDao->getDescription();
        $newItem = $this->createItem($name, $description, $folderDao);
        $newItem->setType($itemDao->getType());
        $newItem->setSizebytes($itemDao->getSizebytes());
        $newItem->setDateCreation(date('Y-m-d H:i:s'));
        $newItem->setDateUpdate(date('Y-m-d H:i:s'));

        $thumbnailId = $itemDao->getThumbnailId();
        if ($thumbnailId !== null) {
            $oldThumb = $BitstreamModel->load($thumbnailId);
            $newThumb = new BitstreamDao();
            $newThumb->setItemrevisionId(-1);
            $newThumb->setName($oldThumb->getName());
            $newThumb->setMimetype($oldThumb->getMimetype());
            $newThumb->setSizebytes($oldThumb->getSizebytes());
            $newThumb->setChecksum($oldThumb->getChecksum());
            $newThumb->setPath($oldThumb->getPath());
            $newThumb->setAssetstoreId($oldThumb->getAssetstoreId());
            $newThumb->setDate($oldThumb->getDate());
            $BitstreamModel->save($newThumb);
            $newItem->setThumbnailId($newThumb->getKey());
        }

        /** @var ItemRevisionModel $ItemRevisionModel */
        $ItemRevisionModel = MidasLoader::loadModel('ItemRevision');

        /** @var BitstreamModel $BitstreamModel */
        $BitstreamModel = MidasLoader::loadModel('Bitstream');

        /** @var MetadataModel $MetadataModel */
        $MetadataModel = MidasLoader::loadModel('Metadata');

        /** @var ItempolicygroupModel $ItemPolicyGroupModel */
        $ItemPolicyGroupModel = MidasLoader::loadModel('Itempolicygroup');
        $ItemPolicyGroupModel->computePolicyStatus($newItem);

        foreach ($itemDao->getRevisions() as $revision) {
            $dupItemRevision = new ItemRevisionDao();
            $dupItemRevision->setItemId($newItem->getItemId());
            $dupItemRevision->setRevision($revision->getRevision());
            $dupItemRevision->setDate($revision->getDate());
            $dupItemRevision->setChanges($revision->getChanges());
            $dupItemRevision->setUserId($userDao->getUserId());
            $dupItemRevision->setLicenseId($revision->getLicenseId());
            $ItemRevisionModel->save($dupItemRevision);
            // duplicate metadata value
            $metadatavalues = $ItemRevisionModel->getMetadata($revision);
            foreach ($metadatavalues as $metadata) {
                $MetadataModel->addMetadataValue(
                    $dupItemRevision,
                    $metadata->getMetadatatype(),
                    $metadata->getElement(),
                    $metadata->getQualifier(),
                    $metadata->getValue(),
                    false
                );
            }
            // duplicate bitstream
            foreach ($revision->getBitstreams() as $bitstream) {
                $dupBitstream = new BitstreamDao();
                $dupBitstream->setItemrevisionId($dupItemRevision->getItemrevisionId());
                $dupBitstream->setName($bitstream->getName());
                $dupBitstream->setMimetype($bitstream->getMimetype());
                $dupBitstream->setSizebytes($bitstream->getSizebytes());
                $dupBitstream->setChecksum($bitstream->getChecksum());
                $dupBitstream->setPath($bitstream->getPath());
                $dupBitstream->setAssetstoreId($bitstream->getAssetstoreId());
                $dupBitstream->setDate($bitstream->getDate());
                $BitstreamModel->save($dupBitstream);
            }
        }
        $this->save($newItem, true); // call save with metadata changed flag

        return $newItem;
    }

    /** plus one view */
    public function incrementViewCount($itemdao)
    {
        if (!$itemdao instanceof ItemDao) {
            throw new Zend_Exception('Error in param itemdao when incrementing view count.');
        }
        $user = Zend_Registry::get('userSession');
        if (isset($user)) {
            if (isset($user->viewedItems[$itemdao->getKey()])) {
                return;
            } else {
                $user->viewedItems[$itemdao->getKey()] = true;
            }
        }
        ++$itemdao->view;
        parent::save($itemdao);
    }

    /** plus one download */
    public function incrementDownloadCount($itemdao)
    {
        if (!$itemdao instanceof ItemDao) {
            throw new Zend_Exception('Error in param itemdao when incrementing download count.');
        }
        ++$itemdao->download;
        parent::save($itemdao);
        Zend_Registry::get('notifier')->callback('CALLBACK_CORE_PLUS_ONE_DOWNLOAD', array('item' => $itemdao));
    }

    /**
     * Add a revision to an item.
     *
     * @param ItemDao $itemdao
     * @param ItemRevisionDao $revisiondao
     * @throws Zend_Exception
     */
    public function addRevision($itemdao, $revisiondao)
    {
        if (!$itemdao instanceof ItemDao) {
            throw new Zend_Exception('First argument should be an item');
        }
        if (!$revisiondao instanceof ItemRevisionDao) {
            throw new Zend_Exception('Second argument should be an item revision');
        }

        /** @var ItemRevisionModel $ItemRevisionModel */
        $ItemRevisionModel = MidasLoader::loadModel('ItemRevision');

        // Should check the latest revision for this item
        $latestrevision = $ItemRevisionModel->getLatestRevision($itemdao);
        if (!$latestrevision) { // no revision yet we assigne the value 1
            $revisiondao->setRevision(1);
        } else {
            $revisiondao->setRevision($latestrevision->getRevision() + 1);
        }
        $revisiondao->setItemId($itemdao->getItemId());
        $ItemRevisionModel->save($revisiondao);
        $this->save($itemdao); // update date
    }

    /**
     * Delete an itemrevision from an item, will reduce all other
     * itemrevision revision numbers appropriately.
     *
     * @param ItemDao $itemdao
     * @param ItemRevisionDao $revisiondao
     * @throws Zend_Exception
     */
    public function removeRevision($itemdao, $revisiondao)
    {
        if (!$itemdao instanceof ItemDao) {
            throw new Zend_Exception('First argument should be an item');
        }
        if (!$revisiondao instanceof ItemRevisionDao) {
            throw new Zend_Exception('Second argument should be an item revision');
        }
        if ($revisiondao->getItemId() != $itemdao->getItemId()) {
            throw new Zend_Exception('Revision needs to be associated with Item');
        }

        /** @var ItemRevisionModel $itemRevisionModel */
        $itemRevisionModel = MidasLoader::loadModel('ItemRevision');

        $lastRevisionDao = $this->getLastRevision($itemdao);
        $numRevisions = $lastRevisionDao->getRevision();
        $deletedRevisionNum = $revisiondao->getRevision();
        $itemRevisionModel->delete($revisiondao);

        // compact the revision numbers if necessary
        if ($deletedRevisionNum < $numRevisions) {
            // reach past the deleted revision, reduce each revision number by 1
            $revisions = range($deletedRevisionNum + 1, $numRevisions);
            foreach ($revisions as $revision) {
                $itemRevisionDao = $this->getRevision($itemdao, $revision);
                $itemRevisionDao->setRevision($itemRevisionDao->getRevision() - 1);
                $itemRevisionModel->save($itemRevisionDao);
            }
        }

        // reload the item and last revision now that we have updated revisions
        $itemId = $itemdao->getItemId();
        $itemdao = $this->load($itemId);
        $lastRevisionDao = $this->getLastRevision($itemdao);

        // now that we have deleted a revision, recalculate size of item
        if (empty($lastRevisionDao)) {
            $itemdao->setSizebytes(0);
        } else {
            $itemdao->setSizebytes($itemRevisionModel->getSize($lastRevisionDao));
        }
        $this->save($itemdao, true);
    }

    /** Create a new empty item */
    public function createItem($name, $description, $parent, $uuid = '')
    {
        if (!$parent instanceof FolderDao && !is_numeric($parent)) {
            throw new Zend_Exception('Parent should be a folder.');
        }

        if (!is_string($name)) {
            throw new Zend_Exception('Name should be a string.');
        }

        if (empty($name) && $name !== '0') {
            throw new Zend_Exception('Name cannot be empty.');
        }

        if (!$parent instanceof FolderDao) {
            $parentId = $parent;
            $parent = $this->load($parentId);
        }

        /** @var ItemDao $item */
        $item = MidasLoader::newDao('ItemDao');
        $item->setName($name);
        $item->setDescription($description);
        $item->setType(0);
        $item->setUuid($uuid);
        $item->setPrivacyStatus(MIDAS_PRIVACY_PRIVATE); // Must set this flag private initially
        $this->save($item, true);

        /** @var FolderModel $folderModel */
        $folderModel = MidasLoader::loadModel('Folder');
        $folderModel->addItem($parent, $item);
        $this->copyParentPolicies($item, $parent);

        return $item;
    }

    /**
     * Count the bitstreams under this item's head revision.
     * Returns array('size'=>size_in_bytes, 'count'=>total_number_of_bitstreams).
     */
    public function countBitstreams($itemDao)
    {
        $totalSize = 0;
        $totalCount = 0;
        $rev = $this->getLastRevision($itemDao);

        if ($rev) {
            $bitstreams = $rev->getBitstreams();
            foreach ($bitstreams as $bitstream) {
                ++$totalCount;
                $totalSize += $bitstream->getSizebytes();
            }
        }

        return array('size' => $totalSize, 'count' => $totalCount);
    }

    /**
     * Merge the items in the specified array into one item.
     */
    public function mergeItems($itemIds, $name, $userSessionDao, $progress = null)
    {
        if ($progress) {
            $current = 0;

            /** @var ProgressModel $progressModel */
            $progressModel = MidasLoader::loadModel('Progress');
        }

        $items = array();
        foreach ($itemIds as $item) {
            $itemDao = $this->load($item);
            if ($item != false && $this->policyCheck($itemDao, $userSessionDao, MIDAS_POLICY_ADMIN)
            ) {
                if ($itemDao) {
                    $items[] = $itemDao;
                } else {
                    $this->getLogger()->info(
                        __METHOD__.' User unable to merge item '.$itemDao->getKey(
                        ).' due to insufficient '.'permissions.'
                    );
                    if ($progress) {
                        ++$current;
                        $message = 'Merging items: '.$current.' of '.$progress->getMaximum();
                        $progressModel->updateProgress($progress, $current, $message);
                    }
                }
            } else {
                $this->getLogger()->info(
                    __METHOD__.' User unable to merge item '.$item.' because it does not exist.'
                );
                if ($progress) {
                    ++$current;
                    $message = 'Merging items: '.$current.' of '.$progress->getMaximum();
                    $progressModel->updateProgress($progress, $current, $message);
                }
            }
        }

        if (empty($items)) {
            throw new Zend_Exception('Insufficient permissions to merge these items.');
        }

        $mainItem = $items[0];
        $mainItemLastResision = $this->getLastRevision($mainItem);

        /** @var BitstreamModel $bitstreamModel */
        $bitstreamModel = MidasLoader::loadModel('Bitstream');

        /** @var ItemRevisionModel $revisionModel */
        $revisionModel = MidasLoader::loadModel('ItemRevision');
        foreach ($items as $key => $item) {
            if ($key != 0) {
                $revision = $this->getLastRevision($item);
                $bitstreams = $revision->getBitstreams();
                foreach ($bitstreams as $b) {
                    $b->setItemrevisionId($mainItemLastResision->getKey());
                    $bitstreamModel->save($b);
                }
                $this->delete($item);
            }
            if ($progress) {
                ++$current;
                $message = 'Merging items: '.$current.' of '.$progress->getMaximum();
                $progressModel->updateProgress($progress, $current, $message);
            }
        }

        $mainItem->setSizebytes($revisionModel->getSize($mainItemLastResision));
        $mainItem->setName($name);
        $this->save($mainItem);
        Zend_Registry::get('notifier')->callback(
            'CALLBACK_CORE_ITEM_MERGED',
            array('item' => $mainItem, 'itemIds' => $itemIds)
        );

        return $mainItem;
    }

    /**
     * Delete the existing thumbnail on an item if there is one, and replace
     * it with the one at the provided temp path.  The temp file will be
     * moved into the assetstore.
     */
    public function replaceThumbnail($item, $tempThumbnailFile)
    {
        /** @var AssetstoreModel $assetstoreModel */
        $assetstoreModel = MidasLoader::loadModel('Assetstore');

        /** @var BitstreamModel $bitstreamModel */
        $bitstreamModel = MidasLoader::loadModel('Bitstream');

        // Remove the existing thumbnail bitstream
        if ($item->getThumbnailId() !== null) {
            $oldThumb = $bitstreamModel->load($item->getThumbnailId());
            $bitstreamModel->delete($oldThumb);
        }

        $thumb = $bitstreamModel->createThumbnail($assetstoreModel->getDefault(), $tempThumbnailFile);
        $item->setThumbnailId($thumb->getKey());
        $this->save($item);
    }

    /**
     * Return the latest bitstream from the latest item revision of the given item.
     *
     * @param ItemDao $itemDao item DAO
     * @return BitstreamDao bitstream DAO
     * @throws Zend_Exception
     */
    public function getLatestBitstream($itemDao)
    {
        /** @var ItemRevisionModel $itemRevisionModel */
        $itemRevisionModel = MidasLoader::loadModel('ItemRevision');

        try {
            $itemRevisionDao = $this->getLastRevision($itemDao);
        } catch (Zend_Exception $exception) {
            throw new Zend_Exception('Item does not contain any item revisions');
        }

        return $itemRevisionModel->getLatestBitstream($itemRevisionDao);
    }
}
