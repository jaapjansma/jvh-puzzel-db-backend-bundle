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

use JvH\JvHPuzzelDbBackendBundle\Callback\CollectionMail;
use JvH\JvHPuzzelDbBackendBundle\Driver\DC_Member;

\Contao\System::loadLanguageFile('tl_jvh_db_collections');

$GLOBALS['TL_DCA']['tl_jvh_db_collections'] = array
(
  // Config
  'config' => array
  (
    'dataContainer'               => DC_Member::class,
    'sql' => array
    (
      'keys' => array
      (
        'id' => 'primary'
      )
    ),
    'notEditable' => true,
    'notDeletable' => true,
    'notCopyable' => true,
    'onload_callback' => [
      [CollectionMail::class, 'onLoadCallback']
    ],
  ),
  'select' => array(
    'buttons_callback' => [[CollectionMail::class, 'selectButtonsCallback']],
  ),

  // List
  'list' => array
  (
    'sorting' => array
    (
      'mode'                    => 2,
      'fields'                  => array('country', 'collection_count', 'wishlist_count', 'collection_create_date', 'collection_update_date'),
      'flag'                    => 11,
      'panelLayout'             => 'filter;sort,limit',
      'filter'                  => array("has_collection = '1'")
    ),
    'filtering' => array (
      'fields' => array('country'),
    ),
    'label' => array
    (
      'showColumns'             => true,
      'fields'                  => array('firstname', 'lastname', 'country', 'email', 'collection_count', 'wishlist_count', 'collection_create_date', 'collection_update_date'),
    ),
    'global_operations' => array
    (
      'all' => array
      (
        'href'                => 'act=select',
        'class'               => 'header_edit_all',
        'attributes'          => 'onclick="Backend.getScrollOffset()" accesskey="e"'
      )
    ),
    'operations' => array
    (
      'delete' => array
      (
        'href'                => 'act=delete',
        'icon'                => 'delete.svg',
        'attributes'          => 'onclick="if(!confirm(\'' . $GLOBALS['TL_LANG']['tl_jvh_db_collections']['deleteConfirm'] . '\'))return false;Backend.getScrollOffset()"',
      ),
    )
  ),

  // Subpalettes
  'subpalettes' => array
  (
  ),

  // Fields
  'fields' => array
  (
  )
);