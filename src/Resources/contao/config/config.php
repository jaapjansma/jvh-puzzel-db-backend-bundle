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

\Contao\ArrayUtil::arrayInsert($GLOBALS['BE_MOD']['jvh_puzzel_db'], 0, array
(
  'tl_jvh_db_collections' => array
  (
    'tables'            => array('tl_jvh_db_collections'),
    'send_email' => [\JvH\JvHPuzzelDbBackendBundle\Controller\CollectionMailController::class, 'prepareEmail'],
  ),
));

$GLOBALS['BE_MOD']['jvh_puzzel_db']['tl_jvh_db_puzzel_product']['tables'][] = 'tl_jvh_db_member_puzzel_product';
$GLOBALS['BE_MOD']['jvh_puzzel_db']['tl_jvh_db_puzzel_product']['send_email'] = [\JvH\JvHPuzzelDbBackendBundle\Controller\ProductMailController::class, 'prepareEmail'];

$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['tl_jvh_db_collections']['collection_mail']['recipients'] = array('recipient_email');
$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['tl_jvh_db_collections']['collection_mail']['email_text'] = array(
  'member_*', // All Member fields
  'subject',
  'message'
);
$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['tl_jvh_db_collections']['collection_mail']['email_subject'] = &$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['tl_jvh_db_collections']['collection_mail']['email_text'];
$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['tl_jvh_db_collections']['collection_mail']['email_html'] = &$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['tl_jvh_db_collections']['collection_mail']['email_text'];
$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['tl_jvh_db_collections']['collection_mail']['email_replyTo'] = &$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['tl_jvh_db_collections']['collection_mail']['recipients'];
$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['tl_jvh_db_collections']['collection_mail']['email_recipient_cc'] = &$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['tl_jvh_db_collections']['collection_mail']['recipients'];
$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['tl_jvh_db_collections']['collection_mail']['email_recipient_bcc'] = &$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['tl_jvh_db_collections']['collection_mail']['recipients'];

$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['tl_jvh_db_collections']['product_collection_mail']['recipients'] = array('recipient_email');
$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['tl_jvh_db_collections']['product_collection_mail']['email_text'] = array(
  'member_*', // All Member fields
  'puzzel_product_*', // All Puzzel Product fields
  'subject',
  'message'
);
$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['tl_jvh_db_collections']['product_collection_mail']['email_subject'] = &$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['tl_jvh_db_collections']['product_collection_mail']['email_text'];
$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['tl_jvh_db_collections']['product_collection_mail']['email_html'] = &$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['tl_jvh_db_collections']['product_collection_mail']['email_text'];
$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['tl_jvh_db_collections']['product_collection_mail']['email_replyTo'] = &$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['tl_jvh_db_collections']['product_collection_mail']['recipients'];
$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['tl_jvh_db_collections']['product_collection_mail']['email_recipient_cc'] = &$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['tl_jvh_db_collections']['product_collection_mail']['recipients'];
$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['tl_jvh_db_collections']['product_collection_mail']['email_recipient_bcc'] = &$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['tl_jvh_db_collections']['product_collection_mail']['recipients'];