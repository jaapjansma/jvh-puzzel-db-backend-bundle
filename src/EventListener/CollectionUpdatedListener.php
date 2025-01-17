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

namespace JvH\JvHPuzzelDbBackendBundle\EventListener;

use Contao\MemberModel;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use JvH\JvHPuzzelDbBundle\Event\CollectionUpdatedEvent;
use JvH\JvHPuzzelDbBundle\Model\CollectionModel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CollectionUpdatedListener implements EventSubscriberInterface {

  /**
   * @var Connection
   */
  private $connection;

  public function __construct(Connection $connection) {
    $this->connection = $connection;
  }

  /**
   * Returns an array of event names this subscriber wants to listen to.
   *
   * The array keys are event names and the value can be:
   *
   *  * The method name to call (priority defaults to 0)
   *  * An array composed of the method name to call and the priority
   *  * An array of arrays composed of the method names to call and respective
   *    priorities, or 0 if unset
   *
   * For instance:
   *
   *  * ['eventName' => 'methodName']
   *  * ['eventName' => ['methodName', $priority]]
   *  * ['eventName' => [['methodName1', $priority], ['methodName2']]]
   *
   * The code must not depend on runtime state as it will only be called at compile time.
   * All logic depending on runtime state must be put into the individual methods handling the events.
   *
   * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
   */
  public static function getSubscribedEvents()
  {
    return [
      CollectionUpdatedEvent::EVENT => 'onCollectionUpdated',
    ];
  }

  public function onCollectionUpdated(CollectionUpdatedEvent $event) {
    $member = MemberModel::findByPk($event->collection->member);
    if (!$member->has_collection) {
      $member->has_collection = true;
      $member->collection_create_date = time();
    }
    $member->collection_update_date = time();

    $strQuery = "SELECT COUNT(*) FROM `tl_jvh_db_collection` WHERE `member` = ? AND collection = ? GROUP BY member";
    try {
      $member->collection_count = $this->connection->executeQuery($strQuery, [$member->id, CollectionModel::COLLECTION])->fetchOne() ?? 0;
    } catch (Exception $e) {

    }
    try {
      $member->wishlist_count = $this->connection->executeQuery($strQuery, [$member->id, CollectionModel::WISHLIST])->fetchOne() ?? 0;
    } catch (Exception $e) {

    }
    if (!$member->collection_count) {
      $member->collection_count = 0;
    }
    if (!$member->wishlist_count) {
      $member->wishlist_count = 0;
    }
    $member->save();
  }


}