<?php
/**
 * Copyright (C) 2025  Jaap Jansma (jaap.jansma@civicoop.org)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace JvH\JvHPuzzelDbBackendBundle\Migration;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Contao\MemberModel;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use JvH\JvHPuzzelDbBundle\Model\CollectionModel;

class MigrateMemberData extends AbstractMigration {

  public function __construct(private readonly Connection $connection)
  {
  }

  public function shouldRun(): bool
  {
    $collectionCount = $this->connection->executeQuery("SELECT COUNT(*) FROM `tl_jvh_db_collection`")->fetchOne();
    $memberCount = $this->connection->executeQuery("SELECT COUNT(*) FROM `tl_member` WHERE `has_collection` != '0'")->fetchOne();
    if ($collectionCount && !$memberCount) {
      return true;
    }
    return false;
  }

  public function run(): MigrationResult
  {
    $members = $this->connection->executeQuery("SELECT `member` FROM `tl_jvh_db_collection` GROUP BY `member`");
    $count = 0;
    foreach($members->fetchAllAssociative() as $member) {
      $this->updateMember($member['member']);
      $count++;
    }
    return $this->createResult(
      true,
      'Updated '. $count.' members with collection info.'
    );
  }

  private function updateMember(int $id) {
    $collection_count = 0;
    $wishlist_count = 0;
    $collection_create_date = 0;
    $collection_update_date = 0;


    $strQuery = "SELECT COUNT(*) FROM `tl_jvh_db_collection` WHERE `member` = ? AND collection = ? GROUP BY member";
    try {
      $collection_count = $this->connection->executeQuery($strQuery, [$member->id, CollectionModel::COLLECTION])->fetchOne() ?? 0;
    } catch (Exception $e) {

    }
    try {
      $wishlist_count = $this->connection->executeQuery($strQuery, [$member->id, CollectionModel::WISHLIST])->fetchOne() ?? 0;
    } catch (Exception $e) {

    }

    try {
    $collection_create_date = $this->connection->executeQuery("SELECT MIN(`tstamp`) FROM `tl_jvh_db_collection` WHERE `member` = ?", [$member->id])->fetchOne();
    } catch (Exception $e) {

    }
    if (empty($collection_create_date)) {
      $collection_create_date = time();
    }

    try {
      $collection_update_date = $this->connection->executeQuery("SELECT MAX(`tstamp`) FROM `tl_jvh_db_collection` WHERE `member` = ?", [$member->id])->fetchOne();
    } catch (Exception $e) {

    }
    if (empty($collection_update_date)) {
      $collection_update_date = time();
    }

    if (!$collection_count) {
      $collection_count = 0;
    }
    if (!$wishlist_count) {
      $wishlist_count = 0;
    }

    $this->connection->executeQuery("UPDATE `tl_member` SET `has_collection` = 1, `collection_count` = ?, `wishlist_count` = ?, `collection_create_date` = ?, `collection_update_date` = ? WHERE `id` = ?", [$collection_count, $wishlist_count, $collection_create_date, $collection_update_date, $id]);
  }


}