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

namespace JvH\JvHPuzzelDbBackendBundle\Driver;

use Contao\ArrayUtil;
use Contao\Config;
use Contao\CoreBundle\Exception\InternalServerErrorException;
use Contao\Database;
use Contao\DataContainer;
use Contao\Date;
use Contao\DC_Table;
use Contao\Encryption;
use Contao\Image;
use Contao\Input;
use Contao\Message;
use Contao\StringUtil;
use Contao\System;
use Contao\Widget;
use JvH\JvHPuzzelDbBundle\Model\CollectionModel;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBagInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\String\UnicodeString;

class DC_PuzzelProduct extends DC_Table {

  protected string $strAliasTable;

  protected string $strRealTable = 'tl_jvh_db_puzzel_product';

  protected int $intCollection = 0;

  protected int $intProductId = 0;

  /**
   * Initialize the object
   *
   * @param string $strTable
   * @param array $arrModule
   */
  public function __construct($strTable, $arrModule = array())
  {
    $this->intCollection = Input::get('collection');
    $this->intProductId = Input::get('product_id');
    $this->strAliasTable = $strTable;
    System::loadLanguageFile($this->strRealTable);
    DataContainer::loadDataContainer($this->strRealTable);
    System::loadLanguageFile('tl_member');
    DataContainer::loadDataContainer('tl_member');

    if (\is_array($GLOBALS['TL_DCA'][$this->strAliasTable]['config']['onload_callback'] ?? null))
    {
      foreach ($GLOBALS['TL_DCA'][$this->strAliasTable]['config']['onload_callback'] as $callback)
      {
        if (\is_array($callback))
        {
          $this->import($callback[0]);
          $this->{$callback[0]}->{$callback[1]}($this);
        }
        elseif (\is_callable($callback))
        {
          $callback($this);
        }
      }
    }
    $GLOBALS['TL_DCA'][$strTable]['fields'] = $GLOBALS['TL_DCA']['tl_member']['fields'];
    foreach($GLOBALS['TL_DCA'][$strTable]['fields'] as $k => $v) {
      unset($GLOBALS['TL_DCA'][$strTable]['fields'][$k]['search']);
      unset($GLOBALS['TL_DCA'][$strTable]['fields'][$k]['sorting']);
    }
    $GLOBALS['TL_DCA'][$strTable]['fields']['count']['label'] = $GLOBALS['TL_LANG']['tl_jvh_db_member_puzzel_product']['count'];
    $GLOBALS['TL_DCA'][$this->strRealTable]['list']['global_operations'] = $GLOBALS['TL_DCA'][$strTable]['list']['global_operations'];

    parent::__construct($this->strRealTable, $arrModule);
  }

  protected function reviseTable() {
    return;
  }

  /**
   * List all records of a particular table
   *
   * @return string
   */
  public function showAll()
  {
    $originalTable = $this->strTable;
    $this->strTable = $this->strAliasTable;

    $this->procedure[] = '`'. $this->strRealTable . '`.`id`=?';
    $this->values[] = $this->intProductId;
    if ($this->intCollection) {
      $this->procedure[] = '`tl_jvh_db_collection`.`collection`=?';
      $this->values[] = $this->intCollection;
    }

    $return = parent::showAll();
    $this->strTable = $originalTable;
    return $return;
  }

  /**
   * Compile global buttons from the table configuration array and return them as HTML
   *
   * @return string
   */
  protected function generateGlobalButtons()
  {
    $originalTable = $this->strTable;
    $this->strTable = $this->strAliasTable;
    $return = parent::generateGlobalButtons();
    $this->strTable = $originalTable;
    return $return;
  }


  /**
   * Return a select menu to limit results
   *
   * @param boolean $blnOptional
   *
   * @return string
   */
  protected function limitMenu($blnOptional = false)
  {

    $objSessionBag = System::getContainer()->get('session')->getBag('contao_backend');

    $session = $objSessionBag->all();
    $filter = ($GLOBALS['TL_DCA'][$this->strRealTable]['list']['sorting']['mode'] ?? null) == self::MODE_PARENT ? $this->strRealTable . '_' . CURRENT_ID : $this->strRealTable;
    $fields = '';

    // Set limit from user input
    if (\in_array(Input::post('FORM_SUBMIT'), array('tl_filters', 'tl_filters_limit')))
    {
      $strLimit = Input::post('tl_limit');

      if ($strLimit == 'tl_limit')
      {
        unset($session['filter'][$filter]['limit']);
      }
      // Validate the user input (thanks to aulmn) (see #4971)
      elseif ($strLimit == 'all' || preg_match('/^[0-9]+,[0-9]+$/', $strLimit))
      {
        $session['filter'][$filter]['limit'] = $strLimit;
      }

      $objSessionBag->replace($session);

      if (Input::post('FORM_SUBMIT') == 'tl_filters_limit')
      {
        $this->reload();
      }
    }

    // Set limit from table configuration
    else
    {
      $this->limit = isset($session['filter'][$filter]['limit']) ? (($session['filter'][$filter]['limit'] == 'all') ? null : $session['filter'][$filter]['limit']) : '0,' . Config::get('resultsPerPage');

      $arrProcedure = $this->procedure;
      $arrValues = $this->values;
      $query = "SELECT COUNT(*) AS count FROM " . $this->strRealTable;
      $query .= " INNER JOIN `tl_jvh_db_collection` ON `".$this->strRealTable."`.`id` = `tl_jvh_db_collection`.`puzzel_product`";
      $query .= " INNER JOIN `tl_member` ON `tl_jvh_db_collection`.`member` = `tl_member`.`id`";

      if (!empty($this->root) && \is_array($this->root))
      {
        $arrProcedure[] = 'id IN(' . implode(',', $this->root) . ')';
      }

      // Support empty ptable fields
      if ($GLOBALS['TL_DCA'][$this->strRealTable]['config']['dynamicPtable'] ?? null)
      {
        $arrProcedure[] = ($this->ptable == 'tl_article') ? "(ptable=? OR ptable='')" : "ptable=?";
        $arrValues[] = $this->ptable;
      }

      if (!empty($arrProcedure))
      {
        $query .= " WHERE " . implode(' AND ', $arrProcedure);
      }

      $objTotal = $this->Database->prepare($query)->execute($arrValues);
      $this->total = $objTotal->count;
      $options_total = 0;
      $maxResultsPerPage = Config::get('maxResultsPerPage');
      $blnIsMaxResultsPerPage = false;

      // Overall limit
      if ($maxResultsPerPage > 0 && $this->total > $maxResultsPerPage && ($this->limit === null || preg_replace('/^.*,/', '', $this->limit) == $maxResultsPerPage))
      {
        if ($this->limit === null)
        {
          $this->limit = '0,' . Config::get('maxResultsPerPage');
        }

        $blnIsMaxResultsPerPage = true;
        Config::set('resultsPerPage', Config::get('maxResultsPerPage'));
        $session['filter'][$filter]['limit'] = Config::get('maxResultsPerPage');
      }

      $options = '';

      // Build options
      if ($this->total > 0)
      {
        $options = '';
        $options_total = ceil($this->total / Config::get('resultsPerPage'));

        // Reset limit if other parameters have decreased the number of results
        if ($this->limit !== null && (!$this->limit || preg_replace('/,.*$/', '', $this->limit) > $this->total))
        {
          $this->limit = '0,' . Config::get('resultsPerPage');
        }

        // Build options
        for ($i=0; $i<$options_total; $i++)
        {
          $this_limit = ($i*Config::get('resultsPerPage')) . ',' . Config::get('resultsPerPage');
          $upper_limit = ($i*Config::get('resultsPerPage')+Config::get('resultsPerPage'));

          if ($upper_limit > $this->total)
          {
            $upper_limit = $this->total;
          }

          $options .= '
  <option value="' . $this_limit . '"' . Widget::optionSelected($this->limit, $this_limit) . '>' . ($i*Config::get('resultsPerPage')+1) . ' - ' . $upper_limit . '</option>';
        }

        if (!$blnIsMaxResultsPerPage)
        {
          $options .= '
  <option value="all"' . Widget::optionSelected($this->limit, null) . '>' . $GLOBALS['TL_LANG']['MSC']['filterAll'] . '</option>';
        }
      }

      // Return if there is only one page
      if ($blnOptional && ($this->total < 1 || $options_total < 2))
      {
        return '';
      }

      $fields = '
<select name="tl_limit" class="tl_select tl_chosen' . (($session['filter'][$filter]['limit'] ?? null) != 'all' && $this->total > Config::get('resultsPerPage') ? ' active' : '') . '" onchange="this.form.submit()">
  <option value="tl_limit">' . $GLOBALS['TL_LANG']['MSC']['filterRecords'] . '</option>' . $options . '
</select> ';
    }

    return '
<div class="tl_limit tl_subpanel">
<strong>' . $GLOBALS['TL_LANG']['MSC']['showOnly'] . ':</strong> ' . $fields . '
</div>';
  }

  /**
   * Return a search form that allows to search results using regular expressions
   *
   * @return string
   */
  protected function searchMenu()
  {
    $originalTable = $this->strTable;
    $this->strTable = $this->strAliasTable;
    $return = parent::searchMenu();
    $this->strTable = $originalTable;
    return $return;
  }

  /**
   * Return a select menu that allows to sort results by a particular field
   *
   * @return string
   */
  protected function sortMenu()
  {
    $originalTable = $this->strTable;
    $this->strTable = $this->strAliasTable;
    $return = parent::sortMenu();
    $this->strTable = $originalTable;
    return $return;
  }


  /**
   * Generate the filter panel and return it as HTML string
   *
   * @param integer $intFilterPanel
   *
   * @return string
   */
  protected function filterMenu($intFilterPanel)
  {
    /** @var AttributeBagInterface $objSessionBag */
    $objSessionBag = System::getContainer()->get('session')->getBag('contao_backend');

    $fields = '';
    $sortingFields = $GLOBALS['TL_DCA'][$this->strAliasTable]['list']['filtering']['fields'] ?? [];
    $session = $objSessionBag->all();
    $filter = ($GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['mode'] ?? null) == self::MODE_PARENT ? $this->strTable . '_' . CURRENT_ID : $this->strTable;

    // Return if there are no sorting fields
    if (empty($sortingFields))
    {
      return '';
    }

    // Set filter from user input
    if (Input::post('FORM_SUBMIT') == 'tl_filters')
    {
      foreach ($sortingFields as $field)
      {
        if (Input::post($field, true) != 'tl_' . $field)
        {
          $session['filter'][$filter][$field] = Input::post($field, true);
        }
        else
        {
          unset($session['filter'][$filter][$field]);
        }
      }

      $objSessionBag->replace($session);
    }

    // Set filter from table configuration
    else
    {
      foreach ($sortingFields as $field)
      {
        $what = Database::quoteIdentifier($field);

        if (isset($session['filter'][$filter][$field]))
        {
          // Sort by day
          if (\in_array($GLOBALS['TL_DCA'][$this->strTable]['fields'][$field]['flag'] ?? null, array(self::SORT_DAY_ASC, self::SORT_DAY_DESC)))
          {
            if (!$session['filter'][$filter][$field])
            {
              $this->procedure[] = $what . "=''";
            }
            else
            {
              $objDate = new Date($session['filter'][$filter][$field]);
              $this->procedure[] = $what . ' BETWEEN ? AND ?';
              $this->values[] = $objDate->dayBegin;
              $this->values[] = $objDate->dayEnd;
            }
          }

          // Sort by month
          elseif (\in_array($GLOBALS['TL_DCA'][$this->strTable]['fields'][$field]['flag'] ?? null, array(self::SORT_MONTH_ASC, self::SORT_MONTH_DESC)))
          {
            if (!$session['filter'][$filter][$field])
            {
              $this->procedure[] = $what . "=''";
            }
            else
            {
              $objDate = new Date($session['filter'][$filter][$field]);
              $this->procedure[] = $what . ' BETWEEN ? AND ?';
              $this->values[] = $objDate->monthBegin;
              $this->values[] = $objDate->monthEnd;
            }
          }

          // Sort by year
          elseif (\in_array($GLOBALS['TL_DCA'][$this->strTable]['fields'][$field]['flag'] ?? null, array(self::SORT_YEAR_ASC, self::SORT_YEAR_DESC)))
          {
            if (!$session['filter'][$filter][$field])
            {
              $this->procedure[] = $what . "=''";
            }
            else
            {
              $objDate = new Date($session['filter'][$filter][$field]);
              $this->procedure[] = $what . ' BETWEEN ? AND ?';
              $this->values[] = $objDate->yearBegin;
              $this->values[] = $objDate->yearEnd;
            }
          }

          // Manual filter
          elseif ($GLOBALS['TL_DCA'][$this->strTable]['fields'][$field]['eval']['multiple'] ?? null)
          {
            // CSV lists (see #2890)
            if (isset($GLOBALS['TL_DCA'][$this->strTable]['fields'][$field]['eval']['csv']))
            {
              $this->procedure[] = $this->Database->findInSet('?', $field, true);
              $this->values[] = $session['filter'][$filter][$field] ?? null;
            }
            else
            {
              $this->procedure[] = $what . ' LIKE ?';
              $this->values[] = '%"' . $session['filter'][$filter][$field] . '"%';
            }
          }

          // Other sort algorithm
          else
          {
            $this->procedure[] = $what . '=?';
            $this->values[] = $session['filter'][$filter][$field] ?? null;
          }
        }
      }
    }

    // Add sorting options
    foreach ($sortingFields as $cnt=>$field)
    {
      $arrValues = array();
      $arrProcedure = array();

      if (($GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['mode'] ?? null) == self::MODE_PARENT)
      {
        $arrProcedure[] = 'pid=?';
        $arrValues[] = CURRENT_ID;
      }

      if (!$this->treeView && !empty($this->root) && \is_array($this->root))
      {
        $arrProcedure[] = "id IN(" . implode(',', array_map('\intval', $this->root)) . ")";
      }

      // Check for a static filter (see #4719)
      if (\is_array($GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['filter'] ?? null))
      {
        foreach ($GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['filter'] as $fltr)
        {
          if (\is_string($fltr))
          {
            $arrProcedure[] = $fltr;
          }
          else
          {
            $arrProcedure[] = $fltr[0];
            $arrValues[] = $fltr[1];
          }
        }
      }

      // Support empty ptable fields
      if ($GLOBALS['TL_DCA'][$this->strTable]['config']['dynamicPtable'] ?? null)
      {
        $arrProcedure[] = ($this->ptable == 'tl_article') ? "(ptable=? OR ptable='')" : "ptable=?";
        $arrValues[] = $this->ptable;
      }

      $what = Database::quoteIdentifier($field);

      // Optimize the SQL query (see #8485)
      if (isset($GLOBALS['TL_DCA'][$this->strTable]['fields'][$field]['flag']))
      {
        // Sort by day
        if (\in_array($GLOBALS['TL_DCA'][$this->strTable]['fields'][$field]['flag'], array(self::SORT_DAY_ASC, self::SORT_DAY_DESC)))
        {
          $what = "IF($what!='', FLOOR(UNIX_TIMESTAMP(FROM_UNIXTIME($what , '%Y-%m-%d'))), '') AS $what";
        }

        // Sort by month
        elseif (\in_array($GLOBALS['TL_DCA'][$this->strTable]['fields'][$field]['flag'], array(self::SORT_MONTH_ASC, self::SORT_MONTH_DESC)))
        {
          $what = "IF($what!='', FLOOR(UNIX_TIMESTAMP(FROM_UNIXTIME($what , '%Y-%m-01'))), '') AS $what";
        }

        // Sort by year
        elseif (\in_array($GLOBALS['TL_DCA'][$this->strTable]['fields'][$field]['flag'], array(self::SORT_YEAR_ASC, self::SORT_YEAR_DESC)))
        {
          $what = "IF($what!='', FLOOR(UNIX_TIMESTAMP(FROM_UNIXTIME($what , '%Y-01-01'))), '') AS $what";
        }
      }

      $table = ($GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['mode'] ?? null) == self::MODE_TREE_EXTENDED ? $this->ptable : $this->strTable;

      // Limit the options if there are root records
      if ($this->root)
      {
        $rootIds = $this->root;

        // Also add the child records of the table (see #1811)
        if (($GLOBALS['TL_DCA'][$table]['list']['sorting']['mode'] ?? null) == self::MODE_TREE)
        {
          $rootIds = array_merge($rootIds, $this->Database->getChildRecords($rootIds, $table));
        }

        if (($GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['mode'] ?? null) == self::MODE_TREE_EXTENDED)
        {
          $arrProcedure[] = "pid IN(" . implode(',', $rootIds) . ")";
        }
        else
        {
          $arrProcedure[] = "id IN(" . implode(',', $rootIds) . ")";
        }
      }

      $objFields = $this->Database->prepare("SELECT DISTINCT " . $what . " FROM " . $this->strRealTable . ((\is_array($arrProcedure) && isset($arrProcedure[0])) ? ' WHERE ' . implode(' AND ', $arrProcedure) : ''))
        ->execute($arrValues);

      // Begin select menu
      $fields .= '
<select name="' . $field . '" id="' . $field . '" class="tl_select tl_chosen' . (isset($session['filter'][$filter][$field]) ? ' active' : '') . '">
  <option value="tl_' . $field . '">' . (\is_array($GLOBALS['TL_DCA'][$this->strTable]['fields'][$field]['label'] ?? null) ? $GLOBALS['TL_DCA'][$this->strTable]['fields'][$field]['label'][0] : ($GLOBALS['TL_DCA'][$this->strTable]['fields'][$field]['label'] ?? null)) . '</option>
  <option value="tl_' . $field . '">---</option>';

      if ($objFields->numRows)
      {
        $options = $objFields->fetchEach($field);

        // Sort by day
        if (\in_array($GLOBALS['TL_DCA'][$this->strTable]['fields'][$field]['flag'] ?? null, array(self::SORT_DAY_ASC, self::SORT_DAY_DESC)))
        {
          ($GLOBALS['TL_DCA'][$this->strTable]['fields'][$field]['flag'] ?? null) == self::SORT_DAY_DESC ? rsort($options) : sort($options);

          foreach ($options as $k=>$v)
          {
            if ($v === '')
            {
              $options[$v] = '-';
            }
            else
            {
              $options[$v] = Date::parse(Config::get('dateFormat'), $v);
            }

            unset($options[$k]);
          }
        }

        // Sort by month
        elseif (\in_array($GLOBALS['TL_DCA'][$this->strTable]['fields'][$field]['flag'] ?? null, array(self::SORT_MONTH_ASC, self::SORT_MONTH_DESC)))
        {
          ($GLOBALS['TL_DCA'][$this->strTable]['fields'][$field]['flag'] ?? null) == self::SORT_MONTH_DESC ? rsort($options) : sort($options);

          foreach ($options as $k=>$v)
          {
            if ($v === '')
            {
              $options[$v] = '-';
            }
            else
            {
              $options[$v] = date('Y-m', $v);
              $intMonth = (date('m', $v) - 1);

              if (isset($GLOBALS['TL_LANG']['MONTHS'][$intMonth]))
              {
                $options[$v] = $GLOBALS['TL_LANG']['MONTHS'][$intMonth] . ' ' . date('Y', $v);
              }
            }

            unset($options[$k]);
          }
        }

        // Sort by year
        elseif (\in_array($GLOBALS['TL_DCA'][$this->strTable]['fields'][$field]['flag'] ?? null, array(self::SORT_YEAR_ASC, self::SORT_YEAR_DESC)))
        {
          ($GLOBALS['TL_DCA'][$this->strTable]['fields'][$field]['flag'] ?? null) == self::SORT_YEAR_DESC ? rsort($options) : sort($options);

          foreach ($options as $k=>$v)
          {
            if ($v === '')
            {
              $options[$v] = '-';
            }
            else
            {
              $options[$v] = date('Y', $v);
            }

            unset($options[$k]);
          }
        }

        // Manual filter
        if ($GLOBALS['TL_DCA'][$this->strTable]['fields'][$field]['eval']['multiple'] ?? null)
        {
          $moptions = array();

          // TODO: find a more effective solution
          foreach ($options as $option)
          {
            // CSV lists (see #2890)
            if (isset($GLOBALS['TL_DCA'][$this->strTable]['fields'][$field]['eval']['csv']))
            {
              $doptions = StringUtil::trimsplit($GLOBALS['TL_DCA'][$this->strTable]['fields'][$field]['eval']['csv'], $option);
            }
            else
            {
              $doptions = StringUtil::deserialize($option);
            }

            if (\is_array($doptions))
            {
              $moptions = array_merge($moptions, $doptions);
            }
          }

          $options = $moptions;
        }

        $options = array_unique($options);
        $options_callback = array();

        // Call the options_callback
        if (!($GLOBALS['TL_DCA'][$this->strTable]['fields'][$field]['reference'] ?? null) && (\is_array($GLOBALS['TL_DCA'][$this->strTable]['fields'][$field]['options_callback'] ?? null) || \is_callable($GLOBALS['TL_DCA'][$this->strTable]['fields'][$field]['options_callback'] ?? null)))
        {
          if (\is_array($GLOBALS['TL_DCA'][$this->strTable]['fields'][$field]['options_callback'] ?? null))
          {
            $strClass = $GLOBALS['TL_DCA'][$this->strTable]['fields'][$field]['options_callback'][0];
            $strMethod = $GLOBALS['TL_DCA'][$this->strTable]['fields'][$field]['options_callback'][1];

            $this->import($strClass);
            $options_callback = $this->$strClass->$strMethod($this);
          }
          elseif (\is_callable($GLOBALS['TL_DCA'][$this->strTable]['fields'][$field]['options_callback'] ?? null))
          {
            $options_callback = $GLOBALS['TL_DCA'][$this->strTable]['fields'][$field]['options_callback']($this);
          }
        }

        $options_sorter = array();
        $blnDate = \in_array($GLOBALS['TL_DCA'][$this->strTable]['fields'][$field]['flag'] ?? null, array(self::SORT_DAY_ASC, self::SORT_DAY_DESC, self::SORT_MONTH_ASC, self::SORT_MONTH_DESC, self::SORT_YEAR_ASC, self::SORT_YEAR_DESC));

        // Options
        foreach ($options as $kk=>$vv)
        {
          $value = $blnDate ? $kk : $vv;

          // Options callback
          if (!empty($options_callback) && \is_array($options_callback) && isset($options_callback[$vv]))
          {
            $vv = $options_callback[$vv];
          }

          // Replace the ID with the foreign key
          elseif (isset($GLOBALS['TL_DCA'][$this->strTable]['fields'][$field]['foreignKey']))
          {
            $key = explode('.', $GLOBALS['TL_DCA'][$this->strTable]['fields'][$field]['foreignKey'], 2);

            $objParent = $this->Database->prepare("SELECT " . Database::quoteIdentifier($key[1]) . " AS value FROM " . $key[0] . " WHERE id=?")
              ->limit(1)
              ->execute($vv);

            if ($objParent->numRows)
            {
              $vv = $objParent->value;
            }
          }

          // Replace boolean checkbox value with "yes" and "no"
          elseif (($GLOBALS['TL_DCA'][$this->strTable]['fields'][$field]['eval']['isBoolean'] ?? null) || (($GLOBALS['TL_DCA'][$this->strTable]['fields'][$field]['inputType'] ?? null) == 'checkbox' && !($GLOBALS['TL_DCA'][$this->strTable]['fields'][$field]['eval']['multiple'] ?? null)))
          {
            $vv = $vv ? $GLOBALS['TL_LANG']['MSC']['yes'] : $GLOBALS['TL_LANG']['MSC']['no'];
          }

          // Get the name of the parent record (see #2703)
          elseif ($field == 'pid')
          {
            $this->loadDataContainer($this->ptable);
            $showFields = $GLOBALS['TL_DCA'][$this->ptable]['list']['label']['fields'] ?? array();

            if (!($showFields[0] ?? null))
            {
              $showFields[0] = 'id';
            }

            $objShowFields = $this->Database->prepare("SELECT " . Database::quoteIdentifier($showFields[0]) . " FROM " . $this->ptable . " WHERE id=?")
              ->limit(1)
              ->execute($vv);

            if ($objShowFields->numRows)
            {
              $vv = $objShowFields->{$showFields[0]};
            }
          }

          $option_label = '';

          // Use reference array
          if (isset($GLOBALS['TL_DCA'][$this->strTable]['fields'][$field]['reference']))
          {
            $option_label = \is_array($GLOBALS['TL_DCA'][$this->strTable]['fields'][$field]['reference'][$vv] ?? null) ? $GLOBALS['TL_DCA'][$this->strTable]['fields'][$field]['reference'][$vv][0] : ($GLOBALS['TL_DCA'][$this->strTable]['fields'][$field]['reference'][$vv] ?? null);
          }

          // Associative array
          elseif (($GLOBALS['TL_DCA'][$this->strTable]['fields'][$field]['eval']['isAssociative'] ?? null) || ArrayUtil::isAssoc($GLOBALS['TL_DCA'][$this->strTable]['fields'][$field]['options'] ?? null))
          {
            $option_label = $GLOBALS['TL_DCA'][$this->strTable]['fields'][$field]['options'][$vv] ?? null;
          }

          // No empty options allowed
          if (!$option_label)
          {
            if (isset($GLOBALS['TL_DCA'][$this->strTable]['fields'][$field]['foreignKey']))
            {
              $option_label = $vv ?: '-';
            }
            else
            {
              $option_label = (string) $vv !== '' ? $vv : '-';
            }
          }

          $options_sorter[$option_label . '_' . $field . '_' . $kk] = '  <option value="' . StringUtil::specialchars($value) . '"' . ((isset($session['filter'][$filter][$field]) && $value == $session['filter'][$filter][$field]) ? ' selected="selected"' : '') . '>' . StringUtil::specialchars($option_label) . '</option>';
        }

        // Sort by option values
        if (!$blnDate)
        {
          uksort($options_sorter, static function ($a, $b)
          {
            $a = (new UnicodeString($a))->folded();
            $b = (new UnicodeString($b))->folded();

            if ($a->toString() === $b->toString())
            {
              return 0;
            }

            return strnatcmp($a->ascii()->toString(), $b->ascii()->toString());
          });

          if (\in_array($GLOBALS['TL_DCA'][$this->strTable]['fields'][$field]['flag'] ?? null, array(self::SORT_INITIAL_LETTER_DESC, self::SORT_INITIAL_LETTERS_DESC, self::SORT_DESC)))
          {
            $options_sorter = array_reverse($options_sorter, true);
          }
        }

        $fields .= "\n" . implode("\n", array_values($options_sorter));
      }

      // End select menu
      $fields .= '
</select> ';

      // Force a line-break after six elements (see #3777)
      if ((($cnt + 1) % 6) == 0)
      {
        $fields .= '<br>';
      }
    }

    return '
<div class="tl_filter tl_subpanel">
<strong>' . $GLOBALS['TL_LANG']['MSC']['filter'] . ':</strong> ' . $fields . '
</div>';
  }


  protected function listView()
  {
    $_originalTable = $this->strTable;
    $this->strTable = $this->strRealTable;
    $table = ($GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['mode'] ?? null) == self::MODE_TREE_EXTENDED ? $this->ptable : $this->strTable;
    $orderBy = $GLOBALS['TL_DCA'][$this->strAliasTable]['list']['sorting']['fields'] ?? array('id');
    $firstOrderBy = preg_replace('/\s+.*$/', '', $orderBy[0]);

    if (\is_array($this->orderBy) && !empty($this->orderBy[0]))
    {
      $orderBy = $this->orderBy;
      $firstOrderBy = $this->firstOrderBy;
    }

    // Check the default labels (see #509)
    $labelNew = $GLOBALS['TL_LANG'][$this->strTable]['new'] ?? $GLOBALS['TL_LANG']['DCA']['new'];

    //$this->strTable = $this->strAliasTable;
    $query = "SELECT `tl_member`.*, `tl_jvh_db_collection`.`puzzel_product`, `tl_jvh_db_collection`.`collection` FROM " . $this->strTable;
    $query .= " INNER JOIN `tl_jvh_db_collection` ON `".$this->strTable."`.`id` = `tl_jvh_db_collection`.`puzzel_product`";
    $query .= " INNER JOIN `tl_member` ON `tl_jvh_db_collection`.`member` = `tl_member`.`id`";

    if (!empty($this->procedure))
    {
      $query .= " WHERE " . implode(' AND ', $this->procedure);
    }

    if (!empty($this->root) && \is_array($this->root))
    {
      $query .= (!empty($this->procedure) ? " AND " : " WHERE ") . "id IN(" . implode(',', array_map('\intval', $this->root)) . ")";
    }

    $query .= " GROUP BY `tl_member`.`id`";

    if (\is_array($orderBy) && $orderBy[0])
    {
      foreach ($orderBy as $k=>$v)
      {
        list($key, $direction) = explode(' ', $v, 2) + array(null, null);

        $orderBy[$k] = $key;

        // If there is no direction, check the global flag in sorting mode 1 or the field flag in all other sorting modes
        if (!$direction)
        {
          if (($GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['mode'] ?? null) == self::MODE_SORTED && isset($GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['flag']) && ($GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['flag'] % 2) == 0)
          {
            $direction = 'DESC';
          }
          elseif (isset($GLOBALS['TL_DCA'][$this->strTable]['fields'][$key]['flag']) && ($GLOBALS['TL_DCA'][$this->strTable]['fields'][$key]['flag'] % 2) == 0)
          {
            $direction = 'DESC';
          }
        }

        if (isset($GLOBALS['TL_DCA'][$this->strTable]['fields'][$key]['foreignKey']))
        {
          $chunks = explode('.', $GLOBALS['TL_DCA'][$this->strTable]['fields'][$key]['foreignKey'], 2);
          $orderBy[$k] = "(SELECT " . Database::quoteIdentifier($chunks[1]) . " FROM " . $chunks[0] . " WHERE " . $chunks[0] . ".id=" . $this->strTable . "." . $key . ")";
        }

        if (\in_array($GLOBALS['TL_DCA'][$this->strTable]['fields'][$key]['flag'] ?? null, array(self::SORT_DAY_ASC, self::SORT_DAY_DESC, self::SORT_MONTH_ASC, self::SORT_MONTH_DESC, self::SORT_YEAR_ASC, self::SORT_YEAR_DESC)))
        {
          $orderBy[$k] = "CAST(" . $orderBy[$k] . " AS SIGNED)"; // see #5503
        }

        if ($direction)
        {
          $orderBy[$k] .= ' ' . $direction;
        }

        if ($GLOBALS['TL_DCA'][$this->strTable]['fields'][$key]['eval']['findInSet'] ?? null)
        {
          if (\is_array($GLOBALS['TL_DCA'][$this->strTable]['fields'][$key]['options_callback'] ?? null))
          {
            $strClass = $GLOBALS['TL_DCA'][$this->strTable]['fields'][$key]['options_callback'][0];
            $strMethod = $GLOBALS['TL_DCA'][$this->strTable]['fields'][$key]['options_callback'][1];

            $this->import($strClass);
            $keys = $this->$strClass->$strMethod($this);
          }
          elseif (\is_callable($GLOBALS['TL_DCA'][$this->strTable]['fields'][$key]['options_callback'] ?? null))
          {
            $keys = $GLOBALS['TL_DCA'][$this->strTable]['fields'][$key]['options_callback']($this);
          }
          else
          {
            $keys = $GLOBALS['TL_DCA'][$this->strTable]['fields'][$key]['options'] ?? array();
          }

          if (($GLOBALS['TL_DCA'][$this->strTable]['fields'][$key]['eval']['isAssociative'] ?? null) || ArrayUtil::isAssoc($keys))
          {
            $keys = array_keys($keys);
          }

          $orderBy[$k] = $this->Database->findInSet($v, $keys);
        }
      }

      if (($GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['mode'] ?? null) == self::MODE_SORTED_PARENT)
      {
        $firstOrderBy = 'pid';
        $showFields = $GLOBALS['TL_DCA'][$table]['list']['label']['fields'];

        $query .= " ORDER BY (SELECT " . Database::quoteIdentifier($showFields[0]) . " FROM " . $this->ptable . " WHERE " . $this->ptable . ".id=" . $this->strTable . ".pid), " . implode(', ', $orderBy) . ', id';

        // Set the foreignKey so that the label is translated
        if (!($GLOBALS['TL_DCA'][$table]['fields']['pid']['foreignKey'] ?? null))
        {
          $GLOBALS['TL_DCA'][$table]['fields']['pid']['foreignKey'] = $this->ptable . '.' . $showFields[0];
        }

        // Remove the parent field from label fields
        array_shift($showFields);
        $GLOBALS['TL_DCA'][$table]['list']['label']['fields'] = $showFields;
      }
      else
      {
        $query .= " ORDER BY " . implode(', ', $orderBy) . ', id';
      }
    }

    $objRowStmt = $this->Database->prepare($query);

    if ($this->limit)
    {
      $arrLimit = explode(',', $this->limit) + array(null, null);
      $objRowStmt->limit($arrLimit[1], $arrLimit[0]);
    }

    $objRow = $objRowStmt->execute($this->values);
    // Display buttos
    $return = Message::generate() . '
<div id="tl_buttons">
<a href="' . $this->getReferer(true, $this->strRealTable) . '" class="header_back" title="' . StringUtil::specialchars($GLOBALS['TL_LANG']['MSC']['backBTTitle']) . '" accesskey="b" onclick="Backend.getScrollOffset()">' . $GLOBALS['TL_LANG']['MSC']['backBT'] . '</a> ' . ((Input::get('act') != 'select' && !($GLOBALS['TL_DCA'][$this->strAliasTable]['config']['closed'] ?? null) && !($GLOBALS['TL_DCA'][$this->strAliasTable]['config']['notCreatable'] ?? null)) ? '
 ' : '') . $this->generateGlobalButtons() . '
</div>';

    // Return "no records found" message
    if ($objRow->numRows < 1)
    {
      $return .= '
<p class="tl_empty">' . $GLOBALS['TL_LANG']['MSC']['noResult'] . '</p>';
    }

    // List records
    else
    {
      $result = $objRow->fetchAllAssoc();

      $return .= ((Input::get('act') == 'select') ? '
<form id="tl_select" class="tl_form' . ((Input::get('act') == 'select') ? ' unselectable' : '') . '" method="post" novalidate>
<div class="tl_formbody_edit">
<input type="hidden" name="FORM_SUBMIT" value="tl_select">
<input type="hidden" name="REQUEST_TOKEN" value="' . REQUEST_TOKEN . '">' : '') . '
<div class="tl_listing_container list_view" id="tl_listing"' . $this->getPickerValueAttribute() . '>' . ((Input::get('act') == 'select' || $this->strPickerFieldType == 'checkbox') ? '
<div class="tl_select_trigger">
<label for="tl_select_trigger" class="tl_select_label">' . $GLOBALS['TL_LANG']['MSC']['selectAll'] . '</label> <input type="checkbox" id="tl_select_trigger" onclick="Backend.toggleCheckboxes(this)" class="tl_tree_checkbox">
</div>' : '') . '
<table class="tl_listing' . (($GLOBALS['TL_DCA'][$this->strAliasTable]['list']['label']['showColumns'] ?? null) ? ' showColumns' : '') . ($this->strPickerFieldType ? ' picker unselectable' : '') . '">';

      // Automatically add the "order by" field as last column if we do not have group headers
      if (($GLOBALS['TL_DCA'][$this->strAliasTable]['list']['label']['showColumns'] ?? null) && false !== ($GLOBALS['TL_DCA'][$this->strAliasTable]['list']['label']['showFirstOrderBy'] ?? null))
      {
        $blnFound = false;

        // Extract the real key and compare it to $firstOrderBy
        foreach ($GLOBALS['TL_DCA'][$this->strAliasTable]['list']['label']['fields'] as $f)
        {
          if (strpos($f, ':') !== false)
          {
            list($f) = explode(':', $f, 2);
          }

          if ($firstOrderBy == $f)
          {
            $blnFound = true;
            break;
          }
        }

        if (!$blnFound)
        {
          $GLOBALS['TL_DCA'][$this->strAliasTable]['list']['label']['fields'][] = $firstOrderBy;
        }
      }

      // Generate the table header if the "show columns" option is active
      if ($GLOBALS['TL_DCA'][$this->strAliasTable]['list']['label']['showColumns'] ?? null)
      {
        $return .= '
  <tr>';

        foreach ($GLOBALS['TL_DCA'][$this->strAliasTable]['list']['label']['fields'] as $f)
        {
          if (strpos($f, ':') !== false)
          {
            list($f) = explode(':', $f, 2);
          }

          $return .= '
    <th class="tl_folder_tlist col_' . $f . (($f == $firstOrderBy) ? ' ordered_by' : '') . '">' . (\is_array($GLOBALS['TL_DCA'][$this->strAliasTable]['fields'][$f]['label'] ?? null) ? $GLOBALS['TL_DCA'][$this->strAliasTable]['fields'][$f]['label'][0] : ($GLOBALS['TL_DCA'][$this->strAliasTable]['fields'][$f]['label'] ?? $f)) . '</th>';
        }

        $return .= '
    <th class="tl_folder_tlist tl_right_nowrap"></th>
  </tr>';
      }

      // Process result and add label and buttons
      $remoteCur = false;
      $groupclass = 'tl_folder_tlist';
      $eoCount = -1;

      foreach ($result as $row)
      {
        $this->current[] = $row['id'];
        $label = $this->generateRecordLabel($row, $this->strTable);

        // Build the sorting groups
        if (($GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['mode'] ?? null) > 0)
        {
          $current = $row[$firstOrderBy];
          $orderBy = $GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['fields'] ?? array('id');
          $sortingMode = (\count($orderBy) == 1 && $firstOrderBy == $orderBy[0] && ($GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['flag'] ?? null) && !($GLOBALS['TL_DCA'][$this->strTable]['fields'][$firstOrderBy]['flag'] ?? null)) ? $GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['flag'] : ($GLOBALS['TL_DCA'][$this->strTable]['fields'][$firstOrderBy]['flag'] ?? null);
          $remoteNew = $this->formatCurrentValue($firstOrderBy, $current, $sortingMode);

          // Add the group header
          if (($remoteNew != $remoteCur || $remoteCur === false) && !($GLOBALS['TL_DCA'][$this->strTable]['list']['label']['showColumns'] ?? null) && !($GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['disableGrouping'] ?? null))
          {
            $eoCount = -1;
            $group = $this->formatGroupHeader($firstOrderBy, $remoteNew, $sortingMode, $row);
            $remoteCur = $remoteNew;

            $return .= '
  <tr>
    <td colspan="2" class="' . $groupclass . '">' . $group . '</td>
  </tr>';
            $groupclass = 'tl_folder_list';
          }
        }

        $return .= '
  <tr class="' . ((++$eoCount % 2 == 0) ? 'even' : 'odd') . ((string) ($row['tstamp'] ?? null) === '0' ? ' draft' : '') . ' click2edit toggle_select hover-row">
    ';

        $colspan = 1;

        // Handle strings and arrays
        if (!($GLOBALS['TL_DCA'][$this->strAliasTable]['list']['label']['showColumns'] ?? null))
        {
          $label = \is_array($label) ? implode(' ', $label) : $label;
        }
        elseif (!\is_array($label))
        {
          $label = array($label);
          $colspan = \count($GLOBALS['TL_DCA'][$this->strAliasTable]['list']['label']['fields'] ?? array());
        }

        // Show columns
        if ($GLOBALS['TL_DCA'][$this->strAliasTable]['list']['label']['showColumns'] ?? null)
        {
          foreach ($label as $j=>$arg)
          {
            $field = $GLOBALS['TL_DCA'][$this->strAliasTable]['list']['label']['fields'][$j] ?? null;

            if (isset($GLOBALS['TL_DCA'][$this->strTable]['fields'][$field]['foreignKey']))
            {
              if ($arg)
              {
                $key = explode('.', $GLOBALS['TL_DCA'][$this->strTable]['fields'][$field]['foreignKey'], 2);

                $reference = $this->Database
                  ->prepare("SELECT " . Database::quoteIdentifier($key[1]) . " AS value FROM " . $key[0] . " WHERE id=?")
                  ->limit(1)
                  ->execute($arg);

                if ($reference->numRows)
                {
                  $arg = $reference->value;
                }
              }

              $value = $arg ?: '-';
            }
            else
            {
              $value = (string) $arg !== '' ? $arg : '-';
            }

            $return .= '<td colspan="' . $colspan . '" class="tl_file_list col_' . explode(':', $field, 2)[0] . ($field == $firstOrderBy ? ' ordered_by' : '') . '">' . $value . '</td>';
          }
        }
        else
        {
          $return .= '<td class="tl_file_list">' . $label . '</td>';
        }

        // Buttons ($row, $table, $root, $blnCircularReference, $childs, $previous, $next)
        $return .= ((Input::get('act') == 'select') ? '
    <td class="tl_file_list tl_right_nowrap"><input type="checkbox" name="IDS[]" id="ids_' . $row['id'] . '" class="tl_tree_checkbox" value="' . $row['id'] . '"></td>' : '
    <td class="tl_file_list tl_right_nowrap">' . $this->generateButtons($row, $this->strAliasTable, $this->root) . ($this->strPickerFieldType ? $this->getPickerInputField($row['id']) : '') . '</td>') . '
  </tr>';
      }

      // Close the table
      $return .= '
</table>' . ($this->strPickerFieldType == 'radio' ? '
<div class="tl_radio_reset">
<label for="tl_radio_reset" class="tl_radio_label">' . $GLOBALS['TL_LANG']['MSC']['resetSelected'] . '</label> <input type="radio" name="picker" id="tl_radio_reset" value="" class="tl_tree_radio">
</div>' : '') . '
</div>';

      // Add another panel at the end of the page
      if (strpos($GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['panelLayout'] ?? '', 'limit') !== false)
      {
        $return .= $this->paginationMenu();
      }

      // Close the form
      if (Input::get('act') == 'select')
      {
        // Submit buttons
        $arrButtons = array();

        if (!($GLOBALS['TL_DCA'][$this->strAliasTable]['config']['notEditable'] ?? null))
        {
          $arrButtons['edit'] = '<button type="submit" name="edit" id="edit" class="tl_submit" accesskey="s">' . $GLOBALS['TL_LANG']['MSC']['editSelected'] . '</button>';
        }

        if (!($GLOBALS['TL_DCA'][$this->strAliasTable]['config']['notDeletable'] ?? null))
        {
          $arrButtons['delete'] = '<button type="submit" name="delete" id="delete" class="tl_submit" accesskey="d" onclick="return confirm(\'' . $GLOBALS['TL_LANG']['MSC']['delAllConfirm'] . '\')">' . $GLOBALS['TL_LANG']['MSC']['deleteSelected'] . '</button>';
        }

        if (!($GLOBALS['TL_DCA'][$this->strAliasTable]['config']['notCopyable'] ?? null))
        {
          $arrButtons['copy'] = '<button type="submit" name="copy" id="copy" class="tl_submit" accesskey="c">' . $GLOBALS['TL_LANG']['MSC']['copySelected'] . '</button>';
        }

        if (!($GLOBALS['TL_DCA'][$this->strAliasTable]['config']['notEditable'] ?? null))
        {
          $arrButtons['override'] = '<button type="submit" name="override" id="override" class="tl_submit" accesskey="v">' . $GLOBALS['TL_LANG']['MSC']['overrideSelected'] . '</button>';
        }

        // Call the buttons_callback (see #4691)
        if (\is_array($GLOBALS['TL_DCA'][$this->strAliasTable]['select']['buttons_callback'] ?? null))
        {
          foreach ($GLOBALS['TL_DCA'][$this->strAliasTable]['select']['buttons_callback'] as $callback)
          {
            if (\is_array($callback))
            {
              $this->import($callback[0]);
              $arrButtons = $this->{$callback[0]}->{$callback[1]}($arrButtons, $this);
            }
            elseif (\is_callable($callback))
            {
              $arrButtons = $callback($arrButtons, $this);
            }
          }
        }

        if (\count($arrButtons) < 3)
        {
          $strButtons = implode(' ', $arrButtons);
        }
        else
        {
          $strButtons = array_shift($arrButtons) . ' ';
          $strButtons .= '<div class="split-button">';
          $strButtons .= array_shift($arrButtons) . '<button type="button" id="sbtog">' . Image::getHtml('navcol.svg') . '</button> <ul class="invisible">';

          foreach ($arrButtons as $strButton)
          {
            $strButtons .= '<li>' . $strButton . '</li>';
          }

          $strButtons .= '</ul></div>';
        }

        $return .= '
</div>
<div class="tl_formbody_submit" style="text-align:right">
<div class="tl_submit_container">
  ' . $strButtons . '
</div>
</div>
</form>';
      }
    }

    $this->strTable = $_originalTable;
    return $return;
  }

  /**
   * Generates the label for a given data record according to the DCA configuration.
   * Returns an array of strings if 'showColumns' is enabled in the DCA configuration.
   *
   * @param array  $row   The data record
   * @param string $table The name of the data container
   *
   * @return string|array<string>
   */
  public function generateRecordLabel(array $row, string $table = null, bool $protected = false, bool $isVisibleRootTrailPage = false)
  {
    $table = $table ?? $this->strTable;
    $labelConfig = &$GLOBALS['TL_DCA'][$this->strAliasTable]['list']['label'];
    $args = array();

    foreach ($labelConfig['fields'] as $k=>$v)
    {
      // Decrypt the value
      if ($GLOBALS['TL_DCA'][$table]['fields'][$v]['eval']['encrypt'] ?? null)
      {
        $row[$v] = Encryption::decrypt(StringUtil::deserialize($row[$v]));
      }

      if (strpos($v, ':') !== false)
      {
        list($strKey, $strTable) = explode(':', $v, 2);
        list($strTable, $strField) = explode('.', $strTable, 2);

        $objRef = Database::getInstance()
          ->prepare("SELECT " . Database::quoteIdentifier($strField) . " FROM " . $strTable . " WHERE id=?")
          ->limit(1)
          ->execute($row[$strKey]);

        $args[$k] = $objRef->numRows ? $objRef->$strField : '';
      }
      elseif (\in_array($GLOBALS['TL_DCA'][$table]['fields'][$v]['flag'] ?? null, array(self::SORT_DAY_ASC, self::SORT_DAY_DESC, self::SORT_MONTH_ASC, self::SORT_MONTH_DESC, self::SORT_YEAR_ASC, self::SORT_YEAR_DESC)))
      {
        if (($GLOBALS['TL_DCA'][$table]['fields'][$v]['eval']['rgxp'] ?? null) == 'date')
        {
          $args[$k] = $row[$v] ? Date::parse(Config::get('dateFormat'), $row[$v]) : '-';
        }
        elseif (($GLOBALS['TL_DCA'][$table]['fields'][$v]['eval']['rgxp'] ?? null) == 'time')
        {
          $args[$k] = $row[$v] ? Date::parse(Config::get('timeFormat'), $row[$v]) : '-';
        }
        else
        {
          $args[$k] = $row[$v] ? Date::parse(Config::get('datimFormat'), $row[$v]) : '-';
        }
      }
      elseif (($GLOBALS['TL_DCA'][$table]['fields'][$v]['eval']['isBoolean'] ?? null) || (($GLOBALS['TL_DCA'][$table]['fields'][$v]['inputType'] ?? null) == 'checkbox' && !($GLOBALS['TL_DCA'][$table]['fields'][$v]['eval']['multiple'] ?? null)))
      {
        $args[$k] = $row[$v] ? $GLOBALS['TL_LANG']['MSC']['yes'] : $GLOBALS['TL_LANG']['MSC']['no'];
      }
      elseif (isset($row[$v]))
      {
        $row_v = StringUtil::deserialize($row[$v]);

        if (\is_array($row_v))
        {
          $args_k = array();

          foreach ($row_v as $option)
          {
            $args_k[] = $GLOBALS['TL_DCA'][$table]['fields'][$v]['reference'][$option] ?? $option;
          }

          $args[$k] = implode(', ', iterator_to_array(new \RecursiveIteratorIterator(new \RecursiveArrayIterator($args_k)), false));
        }
        elseif (isset($GLOBALS['TL_DCA'][$table]['fields'][$v]['reference'][$row[$v]]))
        {
          $args[$k] = \is_array($GLOBALS['TL_DCA'][$table]['fields'][$v]['reference'][$row[$v]]) ? $GLOBALS['TL_DCA'][$table]['fields'][$v]['reference'][$row[$v]][0] : $GLOBALS['TL_DCA'][$table]['fields'][$v]['reference'][$row[$v]];
        }
        elseif ((($GLOBALS['TL_DCA'][$table]['fields'][$v]['eval']['isAssociative'] ?? null) || ArrayUtil::isAssoc($GLOBALS['TL_DCA'][$table]['fields'][$v]['options'] ?? null)) && isset($GLOBALS['TL_DCA'][$table]['fields'][$v]['options'][$row[$v]]))
        {
          $args[$k] = $GLOBALS['TL_DCA'][$table]['fields'][$v]['options'][$row[$v]] ?? null;
        }
        else
        {
          $args[$k] = $row[$v];
        }
      }
      else
      {
        $args[$k] = null;
      }
    }

    // Render the label
    $label = vsprintf($labelConfig['format'] ?? '%s', $args);

    // Shorten the label it if it is too long
    if (($labelConfig['maxCharacters'] ?? null) > 0 && $labelConfig['maxCharacters'] < \strlen(strip_tags($label)))
    {
      $label = trim(StringUtil::substrHtml($label, $labelConfig['maxCharacters'])) . ' …';
    }

    // Remove empty brackets (), [], {}, <> and empty tags from the label
    $label = preg_replace('/\( *\) ?|\[ *] ?|{ *} ?|< *> ?/', '', $label);
    $label = preg_replace('/<[^\/!][^>]+>\s*<\/[^>]+>/', '', $label);

    $mode = $GLOBALS['TL_DCA'][$table]['list']['sorting']['mode'] ?? self::MODE_SORTED;

    $fields = $GLOBALS['TL_DCA'][$this->strAliasTable]['list']['label']['fields'];
    $countKey = array_search('count', $fields, true);
    if (!empty($row['id'])) {
      $args[$countKey] = $this->getCollectionCountForMember($row['id']);
    }

    // Execute label_callback
    if (\is_array($labelConfig['label_callback'] ?? null) || \is_callable($labelConfig['label_callback'] ?? null))
    {
      if (\in_array($mode, array(self::MODE_TREE, self::MODE_TREE_EXTENDED)))
      {
        if (\is_array($labelConfig['label_callback'] ?? null))
        {
          $label = System::importStatic($labelConfig['label_callback'][0])->{$labelConfig['label_callback'][1]}($row, $label, $this, '', false, $protected, $isVisibleRootTrailPage);
        }
        else
        {
          $label = $labelConfig['label_callback']($row, $label, $this, '', false, $protected, $isVisibleRootTrailPage);
        }
      }
      elseif ($mode === self::MODE_PARENT)
      {
        if (\is_array($labelConfig['label_callback'] ?? null))
        {
          $label = System::importStatic($labelConfig['label_callback'][0])->{$labelConfig['label_callback'][1]}($row, $label, $this);
        }
        else
        {
          $label = $labelConfig['label_callback']($row, $label, $this);
        }
      }
      else
      {
        if (\is_array($labelConfig['label_callback'] ?? null))
        {
          $label = System::importStatic($labelConfig['label_callback'][0])->{$labelConfig['label_callback'][1]}($row, $label, $this, $args);
        }
        else
        {
          $label = $labelConfig['label_callback']($row, $label, $this, $args);
        }
      }
    }
    elseif (\in_array($mode, array(self::MODE_TREE, self::MODE_TREE_EXTENDED)))
    {
      $label = Image::getHtml('iconPLAIN.svg') . ' ' . $label;
    }

    if (($labelConfig['showColumns'] ?? null) && !\in_array($mode, array(self::MODE_PARENT, self::MODE_TREE, self::MODE_TREE_EXTENDED)))
    {
      return \is_array($label) ? $label : $args;
    }

    return $label;
  }

  /**
   * Delete a record of the current table and save it to tl_undo
   *
   * @param boolean $blnDoNotRedirect
   *
   * @throws InternalServerErrorException
   */
  public function delete($blnDoNotRedirect=false)
  {
    if ($GLOBALS['TL_DCA'][$this->strTable]['config']['notDeletable'] ?? null)
    {
      throw new InternalServerErrorException('Table "' . $this->strTable . '" is not deletable.');
    }

    if (!$this->intId)
    {
      $this->redirect($this->getReferer());
    }

    $this->redirect($this->getReferer());
  }

  private $collectionCount = array();
  protected function getCollectionCountForMember(int $memberId): int {
    if (empty($this->collectionCount)) {
      $sql = "SELECT COUNT(*) as `count`, `member` FROM `tl_jvh_db_collection` WHERE `puzzel_product`=? AND `collection`=? GROUP BY `member`";
      $results = $this->Database->prepare($sql)->execute([$this->intProductId, $this->intCollection]);
      foreach ($results->fetchAllAssoc() as $row) {
        $this->collectionCount[$row['member']] = $row['count'];
      }
    }
    if (isset($this->collectionCount[$memberId])) {
      return $this->collectionCount[$memberId];
    }
    return 0;
  }

  protected function export()
  {
    $this->procedure[] = '`'. $this->strRealTable . '`.`id`=?';
    $this->values[] = $this->intProductId;
    if ($this->intCollection) {
      $this->procedure[] = '`tl_jvh_db_collection`.`collection`=?';
      $this->values[] = $this->intCollection;
    }

    // Custom filter
    if (!empty($GLOBALS['TL_DCA'][$this->strAliasTable]['list']['sorting']['filter']) && \is_array($GLOBALS['TL_DCA'][$this->strAliasTable]['list']['sorting']['filter']))
    {
      foreach ($GLOBALS['TL_DCA'][$this->strAliasTable]['list']['sorting']['filter'] as $filter)
      {
        if (\is_string($filter))
        {
          $this->procedure[] = $filter;
        }
        else
        {
          $this->procedure[] = $filter[0];
          $this->values[] = $filter[1];
        }
      }
    }
    $_originalTable = $this->strTable;
    $this->strTable = $this->strRealTable;
    $table = ($GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['mode'] ?? null) == self::MODE_TREE_EXTENDED ? $this->ptable : $this->strTable;
    $orderBy = $GLOBALS['TL_DCA'][$this->strAliasTable]['list']['sorting']['fields'] ?? array('id');
    $firstOrderBy = preg_replace('/\s+.*$/', '', $orderBy[0]);

    if (\is_array($this->orderBy) && !empty($this->orderBy[0]))
    {
      $orderBy = $this->orderBy;
      $firstOrderBy = $this->firstOrderBy;
    }

    // Check the default labels (see #509)
    $labelNew = $GLOBALS['TL_LANG'][$this->strTable]['new'] ?? $GLOBALS['TL_LANG']['DCA']['new'];

    //$this->strTable = $this->strAliasTable;
    $query = "SELECT `tl_member`.*, `tl_jvh_db_collection`.`puzzel_product`, `tl_jvh_db_collection`.`collection` FROM " . $this->strTable;
    $query .= " INNER JOIN `tl_jvh_db_collection` ON `".$this->strTable."`.`id` = `tl_jvh_db_collection`.`puzzel_product`";
    $query .= " INNER JOIN `tl_member` ON `tl_jvh_db_collection`.`member` = `tl_member`.`id`";

    if (!empty($this->procedure))
    {
      $query .= " WHERE " . implode(' AND ', $this->procedure);
    }

    if (!empty($this->root) && \is_array($this->root))
    {
      $query .= (!empty($this->procedure) ? " AND " : " WHERE ") . "id IN(" . implode(',', array_map('\intval', $this->root)) . ")";
    }

    $query .= " GROUP BY `tl_member`.`id`";

    if (\is_array($orderBy) && $orderBy[0])
    {
      foreach ($orderBy as $k=>$v)
      {
        list($key, $direction) = explode(' ', $v, 2) + array(null, null);

        $orderBy[$k] = $key;

        // If there is no direction, check the global flag in sorting mode 1 or the field flag in all other sorting modes
        if (!$direction)
        {
          if (($GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['mode'] ?? null) == self::MODE_SORTED && isset($GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['flag']) && ($GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['flag'] % 2) == 0)
          {
            $direction = 'DESC';
          }
          elseif (isset($GLOBALS['TL_DCA'][$this->strTable]['fields'][$key]['flag']) && ($GLOBALS['TL_DCA'][$this->strTable]['fields'][$key]['flag'] % 2) == 0)
          {
            $direction = 'DESC';
          }
        }

        if (isset($GLOBALS['TL_DCA'][$this->strTable]['fields'][$key]['foreignKey']))
        {
          $chunks = explode('.', $GLOBALS['TL_DCA'][$this->strTable]['fields'][$key]['foreignKey'], 2);
          $orderBy[$k] = "(SELECT " . Database::quoteIdentifier($chunks[1]) . " FROM " . $chunks[0] . " WHERE " . $chunks[0] . ".id=" . $this->strTable . "." . $key . ")";
        }

        if (\in_array($GLOBALS['TL_DCA'][$this->strTable]['fields'][$key]['flag'] ?? null, array(self::SORT_DAY_ASC, self::SORT_DAY_DESC, self::SORT_MONTH_ASC, self::SORT_MONTH_DESC, self::SORT_YEAR_ASC, self::SORT_YEAR_DESC)))
        {
          $orderBy[$k] = "CAST(" . $orderBy[$k] . " AS SIGNED)"; // see #5503
        }

        if ($direction)
        {
          $orderBy[$k] .= ' ' . $direction;
        }

        if ($GLOBALS['TL_DCA'][$this->strTable]['fields'][$key]['eval']['findInSet'] ?? null)
        {
          if (\is_array($GLOBALS['TL_DCA'][$this->strTable]['fields'][$key]['options_callback'] ?? null))
          {
            $strClass = $GLOBALS['TL_DCA'][$this->strTable]['fields'][$key]['options_callback'][0];
            $strMethod = $GLOBALS['TL_DCA'][$this->strTable]['fields'][$key]['options_callback'][1];

            $this->import($strClass);
            $keys = $this->$strClass->$strMethod($this);
          }
          elseif (\is_callable($GLOBALS['TL_DCA'][$this->strTable]['fields'][$key]['options_callback'] ?? null))
          {
            $keys = $GLOBALS['TL_DCA'][$this->strTable]['fields'][$key]['options_callback']($this);
          }
          else
          {
            $keys = $GLOBALS['TL_DCA'][$this->strTable]['fields'][$key]['options'] ?? array();
          }

          if (($GLOBALS['TL_DCA'][$this->strTable]['fields'][$key]['eval']['isAssociative'] ?? null) || ArrayUtil::isAssoc($keys))
          {
            $keys = array_keys($keys);
          }

          $orderBy[$k] = $this->Database->findInSet($v, $keys);
        }
      }

      if (($GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['mode'] ?? null) == self::MODE_SORTED_PARENT)
      {
        $firstOrderBy = 'pid';
        $showFields = $GLOBALS['TL_DCA'][$table]['list']['label']['fields'];

        $query .= " ORDER BY (SELECT " . Database::quoteIdentifier($showFields[0]) . " FROM " . $this->ptable . " WHERE " . $this->ptable . ".id=" . $this->strTable . ".pid), " . implode(', ', $orderBy) . ', id';

        // Set the foreignKey so that the label is translated
        if (!($GLOBALS['TL_DCA'][$table]['fields']['pid']['foreignKey'] ?? null))
        {
          $GLOBALS['TL_DCA'][$table]['fields']['pid']['foreignKey'] = $this->ptable . '.' . $showFields[0];
        }

        // Remove the parent field from label fields
        array_shift($showFields);
        $GLOBALS['TL_DCA'][$table]['list']['label']['fields'] = $showFields;
      }
      else
      {
        $query .= " ORDER BY " . implode(', ', $orderBy) . ', id';
      }
    }

    $objRowStmt = $this->Database->prepare($query);

    if ($this->limit)
    {
      $arrLimit = explode(',', $this->limit) + array(null, null);
      $objRowStmt->limit($arrLimit[1], $arrLimit[0]);
    }

    $objRow = $objRowStmt->execute($this->values);
    $result = $objRow->fetchAllAssoc();
    // Automatically add the "order by" field as last column if we do not have group headers
    if (($GLOBALS['TL_DCA'][$this->strAliasTable]['list']['label']['showColumns'] ?? null) && false !== ($GLOBALS['TL_DCA'][$this->strAliasTable]['list']['label']['showFirstOrderBy'] ?? null))
    {
      $blnFound = false;

      // Extract the real key and compare it to $firstOrderBy
      foreach ($GLOBALS['TL_DCA'][$this->strAliasTable]['list']['label']['fields'] as $f)
      {
        if (strpos($f, ':') !== false)
        {
          list($f) = explode(':', $f, 2);
        }

        if ($firstOrderBy == $f)
        {
          $blnFound = true;
          break;
        }
      }

      if (!$blnFound)
      {
        $GLOBALS['TL_DCA'][$this->strAliasTable]['list']['label']['fields'][] = $firstOrderBy;
      }
    }

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $colNr = 1;
    $rowNr = 1;

    // Generate the table header if the "show columns" option is active
    if ($GLOBALS['TL_DCA'][$this->strAliasTable]['list']['label']['showColumns'] ?? null)
    {
      foreach ($GLOBALS['TL_DCA'][$this->strAliasTable]['list']['label']['fields'] as $f)
      {
        if (strpos($f, ':') !== false)
        {
          list($f) = explode(':', $f, 2);
        }
        $sheet->setCellValue([$colNr, $rowNr], (\is_array($GLOBALS['TL_DCA'][$this->strAliasTable]['fields'][$f]['label'] ?? null) ? $GLOBALS['TL_DCA'][$this->strAliasTable]['fields'][$f]['label'][0] : ($GLOBALS['TL_DCA'][$this->strAliasTable]['fields'][$f]['label'] ?? $f)));
        $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($rowNr))->setAutoSize(TRUE);
        $sheet->getStyle([$colNr, $rowNr])->getFont()->setBold(TRUE);
        $sheet->getStyle([$colNr, $rowNr])->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        $colNr++;
      }
    }

    foreach ($result as $row)
    {
      $rowNr ++;
      $colNr = 1;
      $this->current[] = $row['id'];
      $label = $this->generateRecordLabel($row, $this->strTable);

      if (!\is_array($label))
      {
        $label = array($label);
      }

      // Show columns
      if ($GLOBALS['TL_DCA'][$this->strAliasTable]['list']['label']['showColumns'] ?? null)
      {
        foreach ($label as $j=>$arg)
        {
          $field = $GLOBALS['TL_DCA'][$this->strAliasTable]['list']['label']['fields'][$j] ?? null;

          if (isset($GLOBALS['TL_DCA'][$this->strTable]['fields'][$field]['foreignKey']))
          {
            if ($arg)
            {
              $key = explode('.', $GLOBALS['TL_DCA'][$this->strTable]['fields'][$field]['foreignKey'], 2);

              $reference = $this->Database
                ->prepare("SELECT " . Database::quoteIdentifier($key[1]) . " AS value FROM " . $key[0] . " WHERE id=?")
                ->limit(1)
                ->execute($arg);

              if ($reference->numRows)
              {
                $arg = $reference->value;
              }
            }

            $value = $arg ?: '-';
          }
          else
          {
            $value = (string) $arg !== '' ? $arg : '-';
          }
          $sheet->setCellValue([$colNr, $rowNr], $value);
          $colNr++;
        }
      }
    }
    $this->strTable = $_originalTable;

    $writer = new Xlsx($spreadsheet);
    $response =  new StreamedResponse(
      function () use ($writer) {
        $writer->save('php://output');
      }
    );
    $response->headers->set('Content-Type', 'application/vnd.ms-excel');
    $response->headers->set('Content-Disposition', 'attachment;filename="'.$this->strAliasTable.'_'.date('ymd').'.xlsx"');
    $response->headers->set('Cache-Control','max-age=0');
    $response->send();
    exit();
  }

}