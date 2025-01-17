<?php
/**
 * Copyright (C) 2024  Jaap Jansma (jaap.jansma@civicoop.org)
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

namespace JvH\JvHPuzzelDbBackendBundle\Controller;

use Contao\Backend;
use Contao\BackendTemplate;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Database;
use Contao\Input;
use Contao\MemberModel;
use Contao\StringUtil;
use Contao\System;
use Doctrine\DBAL\Connection;
use JvH\JvHPuzzelDbBundle\Model\PuzzelProductModel;

class ProductMailController {

  /**
   * @var ContaoFramework
   */
  private $framework;

  public function __construct(ContaoFramework $framework = null) {
    $this->framework = $framework;
  }

    public function sendEmails(int $limit = 25) {
      System::loadLanguageFile('tl_member');
      $delimiter = ",";
        $objNotificationCollection = \NotificationCenter\Model\Notification::findByType('product_collection_mail');
        if (null === $objNotificationCollection) {
            return;
        }
        $processedIds = [];
        $db = Database::getInstance();
        $rows = $db->prepare("SELECT * FROM `tl_jvh_db_mail_message` WHERE (`product_id` IS NOT NULL AND `product_id` > 0) ORDER BY `id` ASC")->limit($limit)->execute();
        while($row = $rows->fetchAssoc()) {
          $objMember = MemberModel::findByPk($row['pid']);
          $arrTokens = [];
          foreach ($objMember->row() as $k => $v) {
            $arrTokens = $this->flatten($v, 'member_' . $k, $arrTokens, $delimiter);
          }
          $objProduct = PuzzelProductModel::findByPk($row['product_id']);
          foreach ($objProduct->row() as $k => $v) {
            $arrTokens = $this->flatten($v, 'puzzel_product_' . $k, $arrTokens, $delimiter);
          }
          $arrTokens['recipient_email'] = $objMember->email;
          $arrTokens['subject'] = $row['subject'];
          $arrTokens['message'] = $row['msg'];
          $objNotificationCollection->reset();
          while ($objNotificationCollection->next()) {
              $objNotification = $objNotificationCollection->current();
              $objNotification->send($arrTokens, $GLOBALS['TL_LANGUAGE']);
          }
          $processedIds[] = $row['id'];
        }
        if (count($processedIds)) {
            $db->prepare("DELETE FROM `tl_jvh_db_mail_message` WHERE `id` IN (" . implode(", ", $processedIds) . ")")->execute();
        }
    }

    public function prepareEmail(\DataContainer $dc) {
        \System::loadLanguageFile('tl_jvh_db_collections');
        \System::loadLanguageFile('tl_nc_language');
        \System::loadLanguageFile('default');
        \System::loadLanguageFile('tokens');
        \DataContainer::loadDataContainer('tl_jvh_db_collections', false);

        $strBuffer = '';
        $values = array();
        $doNotSubmit = false;
        $objSession = System::getContainer()->get('session');
        $session = $objSession->all();
        $ids = array();
        if ($dc->id) {
            $ids[] = $dc->id;
        } elseif (isset($session['CURRENT']['IDS'])) {
            $ids = $session['CURRENT']['IDS'];
        }
        $productId = Input::get('product_id');

        $arrSubject = [
            'label'     => $GLOBALS['TL_LANG']['tl_jvh_db_collections']['send_email_subject'],
            'inputType' => 'text',
            'eval'      => array('mandatory'=>true, 'required' => true, 'rte'=>'tinyMCE'),
        ];
        $arrSubjectWidget = \Contao\TextField::getAttributesFromDca($arrSubject, 'subject');
        $objSubjectWidget = new \Contao\TextField($arrSubjectWidget);

        $arrMessageField = [
            'label'     => $GLOBALS['TL_LANG']['tl_jvh_db_collections']['send_email_message'],
            'inputType' => 'textarea',
            'eval'      => array('mandatory'=>true, 'required' => true, 'rte'=>'tinyMCE'),
        ];
        $arrMessageWidget = \Contao\TextArea::getAttributesFromDca($arrMessageField, 'message');
        $objMessageWidget = new \Contao\TextArea($arrMessageWidget);

        if (\Input::post('FORM_SUBMIT') === 'tl_jvh_db_collections_send_email') {
            $objSubjectWidget->validate();
            $objMessageWidget->validate();

            if ($objSubjectWidget->hasErrors() || $objMessageWidget->hasErrors()) {
                $doNotSubmit = true;
            } else {
                $values['subject'] = $objSubjectWidget->value;
                $values['message'] = $objMessageWidget->value;
            }
        }

        $strBuffer .= '<div class="clr widget">'.$objSubjectWidget->parse().'</div>';
        $strBuffer .= '<div class="clr widget">'.$objMessageWidget->parse().$this->addFileBrowser('ctrl_message').'</div>';

        if (\Input::post('FORM_SUBMIT') === 'tl_jvh_db_collections_send_email' && !$doNotSubmit) {
            /** @var Connection $connection */
            $connection = System::getContainer()->get('database_connection');
            $db = Database::getInstance();
            $sql = "INSERT INTO `tl_jvh_db_mail_message` (`pid`, `subject`, `msg`, `tstamp`, `product_id`) VALUES ";
            $data = [];
            foreach($ids as $id) {
                $data[] = "(" . $connection->quote($id) . ', ' . $connection->quote($values['subject'])  . ', ' . $connection->quote($values['message'])  . ', '. time() . ', ' . $productId . ')';
            }
            if (count($data)) {
                $sql .= implode(", ", $data);
                $db->execute($sql);
            }

            $url = str_replace('&key=send_email', '', \Environment::get('request'));
            if (\Input::get('id') && Input::get('pid')) {
                $url = str_replace('&id='.\Input::get('id'), '&id='.\Input::get('pid'), $url);
                $url = str_replace('&pid='.\Input::get('pid'), '', $url);
            } elseif (Input::get('pid')) {
                $url = str_replace('&pid='.\Input::get('pid'), '&id='.\Input::get('pid'), $url);
            }
            \Controller::redirect($url);
        }

        return $this->output($strBuffer, count($ids));
    }

    private function output(string $strBuffer, int $count): string {
        return '
            <div id="tl_buttons">
              <a href="' . ampersand(str_replace('&key=send_email', '', \Environment::get('request'))) . '" class="header_back" title="' . specialchars($GLOBALS['TL_LANG']['MSC']['backBT']) . '">' . $GLOBALS['TL_LANG']['MSC']['backBT'] . '</a>
            </div>
            <h2 class="sub_headline">' . sprintf($GLOBALS['TL_LANG']['tl_jvh_db_collections']['send_mail_headline'], $count) . '</h2>' . \Message::generate() . '
            <form action="' . ampersand(\Environment::get('request'), true) . '" id="tl_jvh_db_collections_send_email" class="tl_form" method="post">
                <div class="tl_formbody_edit">
                    <input type="hidden" name="FORM_SUBMIT" value="tl_jvh_db_collections_send_email">
                    <input type="hidden" name="REQUEST_TOKEN" value="' . REQUEST_TOKEN . '">
                    <fieldset class="tl_tbox block">
                    ' . $strBuffer . '
                    </fieldset>
                </div>
                <div class="tl_formbody_submit">
                    <div class="tl_submit_container">
                        <input type="submit" name="save" id="save" class="tl_submit" accesskey="s" value="' . specialchars($GLOBALS['TL_LANG']['tl_jvh_db_collections']['send_email'][1]) . '">
                    </div>
                </div>
            </form>';
    }

    private function addFileBrowser(string $selector) {
        $fileBrowserTypes = array();
        $pickerBuilder = System::getContainer()->get('contao.picker.builder');

        foreach (array('file' => 'image', 'link' => 'file') as $context => $fileBrowserType)
        {
            if ($pickerBuilder->supportsContext($context))
            {
                $fileBrowserTypes[] = $fileBrowserType;
            }
        }

        $objRteTemplate = new BackendTemplate('be_tinyMCE');
        $objRteTemplate->selector = $selector;
        $objRteTemplate->fileBrowserTypes = $fileBrowserTypes;
        // Deprecated since Contao 4.0, to be removed in Contao 5.0
        $objRteTemplate->language = Backend::getTinyMceLanguage();
        return $objRteTemplate->parse();
    }

  /**
   * Flatten input data, Simple Tokens can't handle arrays.
   *
   * @param mixed  $varValue
   * @param string $strKey
   * @param string $strPattern
   */
  private function flatten($varValue, $strKey, array $arrData, $strPattern = ', ')
  {
    /** @var StringUtil $stringUtilAdapter */
    $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);

    if (!empty($varValue) && !\is_array($varValue) && \is_string($varValue) && \strlen($varValue) > 3 && \is_array($stringUtilAdapter->deserialize($varValue))) {
      $varValue = $stringUtilAdapter->deserialize($varValue);
    }

    if (\is_object($varValue)) {
      return $arrData;
    }

    if (!\is_array($varValue)) {
      $arrData[$strKey] = $varValue;

      return $arrData;
    }

    $blnAssoc = array_is_assoc($varValue);

    $arrValues = [];

    foreach ($varValue as $k => $v) {
      if ($blnAssoc || \is_array($v)) {
        $arrData = $this->flatten($v, $strKey.'_'.$k, $arrData);
      } else {
        $arrData[$strKey.'_'.$v] = '1';
        $arrValues[] = $v;
      }
    }

    $arrData[$strKey] = implode($strPattern, $arrValues);

    return $arrData;
  }

}