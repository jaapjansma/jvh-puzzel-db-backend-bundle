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

use JvH\JvHPuzzelDbBackendBundle\Callback\ProductMail;
use JvH\JvHPuzzelDbBackendBundle\Driver\DC_PuzzelProduct;

\Contao\System::loadLanguageFile('tl_jvh_db_member_puzzel_product');

$GLOBALS['TL_DCA']['tl_jvh_db_member_puzzel_product'] = array
(
  // Config
  'config' => array
  (
    'dataContainer'               => DC_PuzzelProduct::class,
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
    'doNotCopyRecords' => true,
    'doNotDeleteRecords' => true,
    'onload_callback' => [
      [ProductMail::class, 'onLoadCallback']
    ],
  ),
  'select' => array(
    'buttons_callback' => [[ProductMail::class, 'selectButtonsCallback']],
  ),

  // List
  'list' => array
  (
    'sorting' => array
    (
      'mode'                    => 1,
      'fields'                  => array('country'),
      'flag'                    => 11,
      'panelLayout'             => 'limit', //'filter;sort,limit',
    ),
    'filtering' => array (
      'fields' => array('country'),
    ),
    'label' => array
    (
      'showColumns'             => true,
      'fields'                  => array('firstname', 'lastname', 'country', 'email', 'count'),
    ),
    'global_operations' => array
    (
      'export' => array
      (
        'label'               =>  &$GLOBALS['TL_LANG']['tl_jvh_db_member_puzzel_product']['export'],
        'href'                => 'act=export',
        'class'               => 'header_export',
        'attributes'          => 'onclick="Backend.getScrollOffset()"',
        'icon'                => 'tablewizard.svg',
      ),
      'all' => array
      (
        'href'                => 'act=select',
        'class'               => 'header_edit_all',
        'attributes'          => 'onclick="Backend.getScrollOffset()" accesskey="e"'
      )
    ),
    'operations' => array
    (
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