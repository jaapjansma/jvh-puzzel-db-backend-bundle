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

$GLOBALS['TL_DCA']['tl_member']['fields']['collection_count'] = [
  'inputType'               => 'text',
  'eval'                    => array('rgxp'=>'natural', 'doNotCopy'=>true),
  'sql'                     => "int unsigned NULL"
];
$GLOBALS['TL_DCA']['tl_member']['fields']['wishlist_count'] = [
  'inputType'               => 'text',
  'eval'                    => array('rgxp'=>'natural', 'doNotCopy'=>true),
  'sql'                     => "int unsigned NULL"
];

$GLOBALS['TL_DCA']['tl_member']['fields']['collection_create_date'] = [
  'default'                 => time(),
  'flag'                    => \Contao\DataContainer::SORT_DAY_DESC,
  'eval'                    => array('rgxp'=>'datim', 'doNotCopy'=>true),
  'sql'                     => "int(10) unsigned NOT NULL default 0"
];

$GLOBALS['TL_DCA']['tl_member']['fields']['collection_update_date'] = [
  'default'                 => time(),
  'flag'                    => \Contao\DataContainer::SORT_DAY_DESC,
  'eval'                    => array('rgxp'=>'datim', 'doNotCopy'=>true),
  'sql'                     => "int(10) unsigned NOT NULL default 0"
];

$GLOBALS['TL_DCA']['tl_member']['fields']['has_collection'] = [
  'inputType'               => 'checkbox',
  'sql'                     => "char(1) NOT NULL default ''"
];