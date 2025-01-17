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

namespace JvH\JvHPuzzelDbBackendBundle\Callback;

use Contao\DataContainer;
use Contao\Environment;
use Contao\Input;
use Contao\System;
use Symfony\Component\HttpFoundation\Session\Session;

class CollectionMail {

  public function selectButtonsCallback($arrButtons, DataContainer $dc) {
    $arrButtons['sendEmail'] = '<button type="submit" name="sendEmail" id="sendEmail" class="tl_submit">' . $GLOBALS['TL_LANG']['tl_jvh_db_collections']['send_email'][0] . '</button>';
    return $arrButtons;
  }

  public function onLoadCallback(DataContainer $dc) {
    if (Input::post('FORM_SUBMIT') == 'tl_select') {
      if (isset($_POST['sendEmail'])) {
        $container = System::getContainer();
        $objSession = $container->get('session');
        $ids = Input::post('IDS');
        if (!empty($ids) && \is_array($ids))
        {
          $session = $objSession->all();
          $session['CURRENT']['IDS'] = $ids;
          $objSession->replace($session);
        }
        $dc->redirect(str_replace('act=select', 'key=send_email', Environment::get('request')));
      }
    }
  }

}