<?php
/**
 * Copyright (C) 2022  Jaap Jansma (jaap.jansma@civicoop.org)
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

/**
 * Table tl_isotope_packaging_slip_product_collection
 */
$GLOBALS['TL_DCA']['tl_jvh_db_mail_message'] = [

  // Config
  'config' => [
    'ptable' => 'tl_member',
    'sql' => [
      'keys' => [
        'id' => 'primary',
        'pid' => 'index',
      ],
    ],
  ],

  'fields' => [
    'id' => [
      'sql' => "int(10) unsigned NOT NULL auto_increment",
    ],
    'pid' => [
      'foreignKey' => 'tl_member.id',
      'sql' => "int(10) unsigned NOT NULL default '0'",
      'relation' => ['type' => 'belongsTo', 'load' => 'lazy'],
    ],
    'tstamp' => [
      'sql' => "int(10) unsigned NOT NULL default '0'",
    ],
    'subject' => [
      'sql' => "varchar(255) NOT NULL default ''",
    ],
    'msg' => [
      'sql' => "text NOT NULL default ''",
    ],
    'product_id' => [
      'foreignKey' => 'tl_jvh_puzzel_product.id',
      'sql' => "int(10) unsigned NULL default '0'",
      'relation' => ['type' => 'belongsTo', 'load' => 'lazy'],
    ],
  ],
];
