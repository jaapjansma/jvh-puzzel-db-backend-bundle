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

use Contao\Backend;
use JvH\JvHPuzzelDbBundle\Driver\DC_Withexport;
use JvH\JvHPuzzelDbBundle\Event\LabelCallback;
use JvH\JvHPuzzelDbBundle\Model\CollectionModel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class LabelCallbackListener implements EventSubscriberInterface {
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
      LabelCallback::EVENT_NAME => 'onLabelCallback',
    ];
  }

  public function onLabelCallback(LabelCallback $event) {
    $fields = $GLOBALS['TL_DCA'][$event->dc->table]['list']['label']['fields'];
    $collectCountKey = array_search('collection_count', $fields, true);
    $collectWishlistKey = array_search('wishlist_count', $fields, true);
    $id = $event->row['id'];
    $exporting = false;
    if ($event->dc instanceof DC_Withexport && $event->dc->isExporting()) {
      $exporting = true;
    }
    if (!$exporting && isset($event->labels[$collectCountKey]) && $event->labels[$collectCountKey] > 0) {
      $href = Backend::addToUrl("table=tl_jvh_db_member_puzzel_product&product_id=".$id."&collection=".CollectionModel::COLLECTION);
      $event->labels[$collectCountKey] = '<a href="' . $href . '">' . $event->labels[$collectCountKey] . '</a>';
    }
    if (!$exporting && isset($event->labels[$collectWishlistKey]) && $event->labels[$collectWishlistKey] > 0) {
      $href = Backend::addToUrl("table=tl_jvh_db_member_puzzel_product&product_id=".$id."&collection=".CollectionModel::WISHLIST);
      $event->labels[$collectWishlistKey] = '<a href="' . $href . '">' . $event->labels[$collectWishlistKey] . '</a>';
    }
  }


}