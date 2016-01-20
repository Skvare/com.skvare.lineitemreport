<?php

/**
 * @file
 * Base class for the line item report
 */
class CRM_Lineitemreport_Report_Form_LineItem extends CRM_Report_Form {


  /**
   * a flag to denote whether the civicrm_contribution table needs to be included in the SQL query
   *
   * @var        boolean
   */
  protected $_contribField = FALSE;
  
  
  /**
   * a flag to denote whether the civicrm_line_item table needs to be included in the SQL query
   *
   * @var        boolean
   */
  protected $_lineitemField = FALSE;
  

  /**
   * a flag to denote whether the civicrm_price_field tables need to be included in the SQL query
   *
   * @var        boolean
   */
  protected $_priceField = FALSE;


  /**
   * a flag to denote whether the civicrm_member* tables need to be included in the SQL query
   *
   * @var        boolean
   */
  protected $_memberField = FALSE;
  

  /**
   * a flag to denote whether the civicrm_group table needs to be included in the SQL query
   *
   * @var        boolean
   */
  protected $_groupFilter = TRUE;
  

  /**
   * a flag to denote whether the civicrm_entity table needs to be included in the SQL query
   *
   * @var        boolean
   */
  protected $_tagFilter = TRUE;
  

  /**
   * a flag to denote whether the civicrm_contribution table needs to be included in the SQL query
   *
   * @var        boolean
   */
  protected $_balance = FALSE;
  

  /**
   * a flag to denote whether the civicrm_contribution table needs to be included in the SQL query
   *
   * @var        array 
   */
  protected $activeCampaigns;


  /**
   * Provides link to drilldown report
   *
   * @var        array
   */
  public $_drilldownReport = array('event/income' => 'Link to Detail Report');

  
  /**
   * Column and option setup for the report
   */
  public function __construct() {

    $this->_autoIncludeIndexedFieldsAsOrderBys = 1;

    // Check if CiviCampaign is a) enabled and b) has active campaigns
    $config = CRM_Core_Config::singleton();
    $campaignEnabled = in_array("CiviCampaign", $config->enableComponents);
    if ($campaignEnabled) {
      $getCampaigns = CRM_Campaign_BAO_Campaign::getPermissionedCampaigns(NULL, NULL, TRUE, FALSE, TRUE);
      $this->activeCampaigns = $getCampaigns['campaigns'];
      asort($this->activeCampaigns);
    }

    

    $this->_options = array(
      'blank_column_begin' => array(
        'title' => ts('Blank column at the Begining'),
        'type' => 'checkbox',
      ),
      'blank_column_end' => array(
        'title' => ts('Blank column at the End'),
        'type' => 'select',
        'options' => array(
          '' => '-select-',
          1 => ts('One'),
          2 => ts('Two'),
          3 => ts('Three'),
        ),
      ),
    );

    // CRM-17115 avoid duplication of sort_name - would be better to standardise name
    // & behaviour across reports but trying for no change at this point.
    $this->_columns['civicrm_contact']['fields']['sort_name']['no_display'] = TRUE;


    $this->organizeColumns();

    $this->_currencyColumn = 'civicrm_participant_fee_currency';
    parent::__construct();
  }

  /**
   * Searches database for priceset values.
   *
   * @return array
   */
  public function getPriceLevels() {
    $query = "
SELECT CONCAT(cv.label, ' (', ps.title, ')') label, cv.id
FROM civicrm_price_field_value cv
LEFT JOIN civicrm_price_field cf
  ON cv.price_field_id = cf.id
LEFT JOIN civicrm_price_set_entity ce
  ON ce.price_set_id = cf.price_set_id
LEFT JOIN civicrm_price_set ps
  ON ce.price_set_id = ps.id
ORDER BY  cv.label
";
    $dao = CRM_Core_DAO::executeQuery($query);
    $elements = array();
    while ($dao->fetch()) {
      $elements[$dao->id] = "$dao->label\n";
    }

    return $elements;
  }

  public function getPriceSets($extends) {
    $fields = array();
    $pricesets = civicrm_api3('PriceSet', 'get', array(
      'sequential' => 1,
      'extends' => array('IN'=>$extends),
      'is_active' => 1,
      'options' => array('limit'=>1000),
    ));

    return $pricesets['values'];
  }

  /**
   * Get price set fields for a given price set and entity
   *
   * @param      int  $psId      (description)
   * @param      int  $entityId  (description)
   * @param      string  $format    (description)
   * 
   * return     returns array of fields
   */
  public function getPriceFields($psId, $entityId, $format=null) {
    switch($format) {
      case 'fieldlist':
        $select = "SELECT DISTINCT li.price_field_id FROM civicrm_line_item li
        JOIN civicrm_price_field pf ON li.price_field_id = pf.id
        JOIN civicrm_price_set_entity pse ON pf.price_set_id = pse.price_set_id";
        $where = "WHERE pse.price_set_id = $psId";
        if (!empty($entityId)) $where .= " AND pse.entity_id IN ($entityId)";

        $order = "ORDER BY li.price_field_id;";
        $query = sprintf("%s\n%s\n%s",$select,$where,$order);

        // var_dump($query);
        $dao = CRM_Core_DAO::executeQuery($query);
        $fields = array();
        while ($dao->fetch()) {
          $fields[] = $dao->price_field_id;
        }

        return $fields;

      break;

      case 'filters':
        $select = "SELECT DISTINCT li.price_field_id, li.price_field_value_id, pf.name, pf.label, pf.is_enter_qty FROM civicrm_line_item li
        JOIN civicrm_price_field pf ON li.price_field_id = pf.id
        JOIN civicrm_price_set_entity pse ON pf.price_set_id = pse.price_set_id";
        $where = "WHERE pse.price_set_id = $psId";
        if (isset($entityId)) $where .= " AND pse.entity_id IN ($entityId)";

        $order = "ORDER BY li.price_field_id;";
        $query = sprintf("%s\n%s\n%s",$select,$where,$order);

        // var_dump($query);
        $dao = CRM_Core_DAO::executeQuery($query);
        $filters = array();
        while ($dao->fetch()) {
          $filters[$dao->name] = array(
            'title' => $psId.'_'.$dao->label,
            'alias' => 'pf'.$dao->price_field_id,
            'type' => CRM_Utils_Type::T_INT,
          );
          if ($dao->is_enter_qty == 1) $filters[$dao->name]['name'] = 'qty';
          if ($dao->html_type != 'text') {
            $filters[$dao->name]['operatorType'] = CRM_Report_Form::OP_MULTISELECT;
            $result = civicrm_api3('PriceFieldValue', 'get', array(
                'sequential' => 1,
                'return' => "name,label",
                'price_field_id' => $dao->price_field_id,
              ));
            $options = array();
            foreach($result['values'] AS $fieldOption) {
              $options[$fieldOption['id']] = $fieldOption['label'];
            }
            $filters[$dao->name]['options'] = $options;
          }
          $filters[$dao->name]['name'] = 'price_field_value_id';
        }
        return $filters;
      break;

      default:
        $select = "SELECT DISTINCT li.price_field_id, pf.name, pf.label, pf.is_enter_qty FROM civicrm_line_item li
        JOIN civicrm_price_field pf ON li.price_field_id = pf.id
        JOIN civicrm_price_set_entity pse ON pf.price_set_id = pse.price_set_id";
        $where = "WHERE pse.price_set_id = $psId";
        if (!empty($entityId)) $where .= " AND pse.entity_id IN ($entityId)";

        $order = "ORDER BY li.price_field_id;";
        $query = sprintf("%s\n%s\n%s",$select,$where,$order);

        // var_dump($query);
        $dao = CRM_Core_DAO::executeQuery($query);
        $fields = array();
        while ($dao->fetch()) {
          $fields[$dao->name] = array(
            'title' => $psId.'_'.$dao->label,
            'alias' => 'pf'.$dao->price_field_id,
          );
          if ($dao->is_enter_qty == 1) $fields[$dao->name]['name'] = 'qty';
          else $fields[$dao->name]['name'] = 'label';
        }
        return $fields;
    }
    
  }

  public function organizeColumns() {
      // Create a column grouping for each price set

      $this->_extendedEntities = array('participant'=>'CiviEvent','contribution'=>'CiviContribute','membership'=>'CiviMember');
      $this->_uri = parse_url($_SERVER['REQUEST_URI']);
      $this->_entity = array_pop(explode('/',$this->_uri['path']));
      $this->_entities = array('member','participant');

      switch ($this->_entity) {
        case 'membership':
          foreach (array_diff($this->_entities, array($this->_entity)) AS $other) {
            unset($this->_columns['civicrm_'.$other]);
            unset($this->_extendedEntities[$other]);
          }
          unset($this->_columns['civicrm_event']);
          $entityId = $this->_submitValues['membership_type_id_value'];
        break;

        case 'contribution':
          foreach (array_diff($this->_entities, array($this->_entity)) AS $other) {
            unset($this->_columns['civicrm_'.$other]);
            unset($this->_extendedEntities[$other]);
          }
          unset($this->_columns['civicrm_event']);
          $entityId = $this->_submitValues['contribution_page_id_value'];
        break;

        case 'participant':
          foreach (array_diff($this->_entities, array($this->_entity)) AS $other) {
            unset($this->_columns['civicrm_'.$other]);
            unset($this->_extendedEntities[$other]);
          }
          $entityId = $this->_submitValues['event_id_value'];
        break;

      }

      $this->_relevantPriceSets = $this->getPriceSets(array_values($this->_extendedEntities));
      foreach ($this->_relevantPriceSets AS $ps) {
        $this->_columns['civicrm_price_set_'.$ps['id']] = array(
          'alias' => 'ps'.$ps['id'],
          'dao' => 'CRM_Price_DAO_LineItem',
          'grouping' => 'priceset-fields-'.$ps['name'],
          'group_title' => 'Price Fields - '.$ps['title'],
        );

          $this->_columns['civicrm_price_set_'.$ps['id']]['fields'] = $this->getPriceFields($ps['id'], $entityId);
          $this->_columns['civicrm_price_set_'.$ps['id']]['filters'] = $this->getPriceFields($ps['id'], $entityId, 'filters');
      }
  }

  /**
   * Checks to see if price set has related entities. Allows hiding of unused price sets from report options
   *
   * @param      int   $psId      Price Set Id
   * @param      int   $entityId  Related event/contribution page/membership
   *
   * @return     integer
   */
  public function checkPriceSetEntity($psId, $entityId) {
    if (!isset($entityId)) return 0;
    $query = "SELECT id FROM civicrm_price_set_entity WHERE price_set_id = $psId";
    if (isset($entityId)) $query .= " AND entity_id IN ($entityId)";
    $dao = CRM_Core_DAO::executeQuery($query);
    $entityCt = 0;
    while ($dao->fetch()) {
      $entityCt = $dao->id;
    }

    return (int) $entityCt;
  }

  /**
   * Executes pre-query processing directives
   */
  public function preProcess() {
    parent::preProcess();
  }

  /**
   * Defines select clause of report SQL query
   */
  public function select() {
    $select = array();
    $this->_columnHeaders = array();

    //add blank column at the Start
    if (array_key_exists('options', $this->_params) &&
      !empty($this->_params['options']['blank_column_begin'])
    ) {
      $select[] = " '' as blankColumnBegin";
      $this->_columnHeaders['blankColumnBegin']['title'] = '_ _ _ _';
    }
    foreach ($this->_columns as $tableName => $table) {
     
      if ($tableName == 'civicrm_participant') {
        $this->_eventField = TRUE;
      }

      if ($tableName == 'civicrm_membership') {
        $this->_memberField = TRUE;
      }

      if ($tableName == 'civicrm_contribution') {
        $this->_contribField = TRUE;
      }
      if ($fieldName == 'total_paid' || $fieldName == 'balance') {
        $this->_balance = TRUE;
      }
      if ($tableName == 'civicrm_line_item') {
        $this->_lineitemField = TRUE;
      }

      if (strpos($tableName, 'civicrm_price_set') !== false) {
        $this->_priceField = TRUE;
        $pricesetId = array_pop(explode('_',$tableName));
        
        // Check to see if price set is assigned to user selected event(s). 
        // If not, remove the table from the select clause and continue

        switch($this->_entity) {
          case 'contribution':
          case 'membership':
            $entityId = $this->_submitValues['contribution_page_id_value'];
            break;

          case 'participant':
            $entityId = $this->_submitValues['event_id_value'];
            break;
        }

        $pseId = $this->checkPriceSetEntity($pricesetId, $entityId);
        if ($pseId == 0) {
          unset($this->_columns[$tableName]);
          continue;
        }

        // Add price sets requiring joins
        $this->_reqPriceSets[$tableName] = $tableName;
          
      }

      if (array_key_exists('fields', $table)) {
        foreach ($table['fields'] as $fieldName => $field) {
          if (!empty($field['required']) ||
            !empty($this->_params['fields'][$fieldName])
          ) {
            $alias = "{$tableName}_{$fieldName}";
            $select[] = "{$field['dbAlias']} as $alias";
            $this->_columnHeaders["{$tableName}_{$fieldName}"]['type'] = CRM_Utils_Array::value('type', $field);
            $this->_columnHeaders["{$tableName}_{$fieldName}"]['no_display'] = CRM_Utils_Array::value('no_display', $field);
            $this->_columnHeaders["{$tableName}_{$fieldName}"]['title'] = CRM_Utils_Array::value('title', $field);
            $this->_selectAliases[] = $alias;
          }
        }
      }


    }
    //add blank column at the end
    $blankcols = CRM_Utils_Array::value('blank_column_end', $this->_params);
    if ($blankcols) {
      for ($i = 1; $i <= $blankcols; $i++) {
        $select[] = " '' as blankColumnEnd_{$i}";
        $this->_columnHeaders["blank_{$i}"]['title'] = "_ _ _ _";
      }
    }

    $this->_select = "SELECT " . implode(', ', $select) . " ";
  }

  /**
   * @param $fields
   * @param $files
   * @param $self
   *
   * @return array
   */
  public static function formRule($fields, $files, $self) {
    $errors = $grouping = array();
    return $errors;
  }

  
  /**
   * Defines from clause of the report SQL query
   */
  public function from() {
    $this->_from = "
        FROM civicrm_{$this->_entity} {$this->_aliases['civicrm_'.$this->_entity]}
             LEFT JOIN civicrm_contact {$this->_aliases['civicrm_contact']}
                    ON ({$this->_aliases['civicrm_'.$this->_entity]}.contact_id  = {$this->_aliases['civicrm_contact']}.id  )
             {$this->_aclFrom}
             LEFT JOIN civicrm_address {$this->_aliases['civicrm_address']}
                    ON {$this->_aliases['civicrm_contact']}.id = {$this->_aliases['civicrm_address']}.contact_id AND
                       {$this->_aliases['civicrm_address']}.is_primary = 1
             LEFT JOIN  civicrm_email {$this->_aliases['civicrm_email']}
                    ON ({$this->_aliases['civicrm_contact']}.id = {$this->_aliases['civicrm_email']}.contact_id AND
                       {$this->_aliases['civicrm_email']}.is_primary = 1)
             LEFT  JOIN civicrm_phone  {$this->_aliases['civicrm_phone']}
                     ON {$this->_aliases['civicrm_contact']}.id = {$this->_aliases['civicrm_phone']}.contact_id AND
                         {$this->_aliases['civicrm_phone']}.is_primary = 1
      ";

    if ($this->_memberField) {
      $this->_from .= "LEFT  JOIN civicrm_membership_status {$this->_aliases['civicrm_membership_status']}
                          ON {$this->_aliases['civicrm_membership_status']}.id =
                             {$this->_aliases['civicrm_membership']}.status_id
      ";
    }  

    if ($this->_eventField) {
      $this->_from .= "LEFT JOIN civicrm_event {$this->_aliases['civicrm_event']}
                    ON ({$this->_aliases['civicrm_event']}.id = {$this->_aliases['civicrm_participant']}.event_id ) AND
                        {$this->_aliases['civicrm_event']}.is_template = 0
      ";
    }

    if ($this->_contribField && $this->_eventField) {
      $this->_from .= "
             LEFT JOIN civicrm_participant_payment pp
                    ON ({$this->_aliases['civicrm_participant']}.id  = pp.participant_id)
             LEFT JOIN civicrm_contribution {$this->_aliases['civicrm_contribution']}
                    ON (pp.contribution_id  = {$this->_aliases['civicrm_contribution']}.id)
      ";
    }

    if ($this->_contribField && $this->_memberField) {
      $this->_from .= "
             LEFT JOIN civicrm_membership_payment mp
                    ON ({$this->_aliases['civicrm_membership']}.id = mp.membership_id)
             LEFT JOIN civicrm_contribution {$this->_aliases['civicrm_contribution']}
                    ON (mp.contribution_id  = {$this->_aliases['civicrm_contribution']}.id)
      ";
    }

    if ($this->_lineitemField) {
      $this->_from .= "
            LEFT JOIN civicrm_line_item line_item_civireport
                  ON line_item_civireport.entity_table = 'civicrm_$this->_entity' AND
                     line_item_civireport.entity_id = {$this->_aliases['civicrm_'.$this->_entity]}.id AND
                     line_item_civireport.qty > 0
      ";

    }

    if ($this->_priceField) {
      switch($this->_entity) {
        case 'participant':
          $entityId = $this->_submitValues['event_id_value'];
          $entityTable = 'civicrm_participant';
          break;

        case 'membership':
          $entityId = $this->_submitValues['tid_value'];
          $entityTable = 'civicrm_membership';
          break;

        case 'contribution':
          $entityId = $this->_submitValues['contribution_page_id_value'];  
          $entityTable = 'civicrm_contribution_page';
          break;
      }
      

      foreach($this->_reqPriceSets AS $priceset) {
        $pricesetId = array_pop(explode('_',$priceset));
        if ($this->checkPriceSetEntity($pricesetId, $entityId) == 0) continue;
        $fieldlist = $this->getPriceFields($pricesetId, $entityId, 'fieldlist');          
          
        foreach($fieldlist AS $pf) {

          $this->_from .=  sprintf('
              LEFT JOIN civicrm_line_item pf%1$s
                    ON pf%1$s.entity_table = \'%3$s\' AND
                       pf%1$s.entity_id = %2$s.id AND
                       pf%1$s.qty > 0 AND
                       pf%1$s.price_field_id = %1$s
        ', $pf, $this->_aliases['civicrm_'.$this->_entity], $entityTable);
        }
      }
    }

  
    if ($this->_balance) {
      $this->_from .= "
            LEFT JOIN civicrm_entity_financial_trxn eft
                  ON (eft.entity_id = {$this->_aliases['civicrm_contribution']}.id)
            LEFT JOIN civicrm_financial_account fa
                  ON (fa.account_type_code = 'AR')
            LEFT JOIN civicrm_financial_trxn ft
                  ON (ft.id = eft.financial_trxn_id AND eft.entity_table = 'civicrm_contribution') AND
                     (ft.to_financial_account_id != fa.id)
      ";
    }
  }

  /**
   * Defines where clause of the report SQL query
   */
  public function where() {
    $clauses = array();
    foreach ($this->_columns as $tableName => $table) {
      if (strpos($tableName, 'civicrm_price_set') !== false) {
        $pricesetId = array_pop(explode('_',$tableName));
        
        // Check to see if price set is assigned to user selected event(s). 
        // If not, remove the table from the clause and continue
        $pseId = $this->checkPriceSetEntity($pricesetId, $this->_params['event_id_value']);
        if ($pseId == 0) {
          unset($this->_columns[$tableName]);
          continue;
        }

        // Add price sets requiring joins
        // $this->_reqPriceSets[$tableName] = $tableName;
          
      }

      if (array_key_exists('filters', $table)) {
        foreach ($table['filters'] as $fieldName => $field) {
          $clause = NULL;

          if (CRM_Utils_Array::value('type', $field) & CRM_Utils_Type::T_DATE) {
            $relative = CRM_Utils_Array::value("{$fieldName}_relative", $this->_params);
            $from = CRM_Utils_Array::value("{$fieldName}_from", $this->_params);
            $to = CRM_Utils_Array::value("{$fieldName}_to", $this->_params);

            if ($relative || $from || $to) {
              $clause = $this->dateClause($field['name'], $relative, $from, $to, $field['type']);
            }
          }
          else {
            $op = CRM_Utils_Array::value("{$fieldName}_op", $this->_params);

            if ($fieldName == 'rid') {
              $value = CRM_Utils_Array::value("{$fieldName}_value", $this->_params);
              if (!empty($value)) {
                $clause = "( {$field['dbAlias']} REGEXP '[[:<:]]" .
                  implode('[[:>:]]|[[:<:]]', $value) . "[[:>:]]' )";
              }
              $op = NULL;
            }

            if ($op) {
              $clause = $this->whereClause($field,
                $op,
                CRM_Utils_Array::value("{$fieldName}_value", $this->_params),
                CRM_Utils_Array::value("{$fieldName}_min", $this->_params),
                CRM_Utils_Array::value("{$fieldName}_max", $this->_params)
              );
            }
          }

          if (!empty($clause)) {
            $clauses[] = $clause;
          }
        }
      }
    }
    if (empty($clauses)) {
      $this->_where = "WHERE {$this->_aliases['civicrm_'.$this->_entity]}.is_test = 0 ";
    }
    else {
      $this->_where = "WHERE {$this->_aliases['civicrm_'.$this->_entity]}.is_test = 0 AND " .
        implode(' AND ', $clauses);
    }
    if ($this->_aclWhere) {
      $this->_where .= " AND {$this->_aclWhere} ";
    }
  }

  /**
   * Defines grouping clause of the report SQL query
   */
  public function groupBy() {
    $this->_groupBy = "GROUP BY {$this->_aliases['civicrm_'.$this->_entity]}.id";
  }

  /**
   * Checks to see if the total number of tables in the price set entity table will require more than the max number of joins in MySQL (61)
   *
   * @param      string   $message      The message to be displayed in the user notification
   * @param      string   $title        Title for the notification box
   * @param      string   $type         alert, success, info, or error
   * @param      array   $options      unique (boolean - default true), expires (int - default 10s)
   * @param      string   $entityTable  entity table as listed in civicrm_price_set_entity
   *
   * @return     boolean
   */
  public function checkJoinCount($message, $title, $type='error', $options=array('expires'=>0), $entityTable=null) 
  {
    $psCountQuery = "select count(id) as recordCt from civicrm_price_set_entity";
    $dao = CRM_Core_DAO::singleValueQuery($psCountQuery);
    $joinCt = (int) $dao;
  
    if ($joinCt > 60) {
      CRM_Core_Session::setStatus($message,$title,$type,$options);
      return false;
    }
  }

  /**
   * Builds query and runs post-query processing directives
   */
  public function postProcess() {
    if (empty($this->_submitValues['event_id_value']) && $this->_entity == 'participant') 
    {
      $message = 'You must choose one or more events from the filters tab before running this report';
      $title = 'Choose one or more events';
      $this->checkJoinCount($message,$title);
    }

    if (empty($this->_submitValues['tid_value']) && $this->_entity == 'membership')
    {
      $message = 'You must choose one or more membership types from the filters tab before running this report';
      $title = 'Choose one or more membership types';
      $this->checkJoinCount($message,$title);
    }
    
    if (empty($this->_submitValues['contribution_page_id_value']) && $this->_entity == 'contribution')
    {
      $message = 'You must choose one or more contribution pages from the filters tab before running this report';
      $title = 'Choose one or more contribution types';
      $this->checkJoinCount($message,$title);
    }

    


    // get ready with post process params
    $this->beginPostProcess();

    // get the acl clauses built before we assemble the query
    $this->buildACLClause($this->_aliases['civicrm_contact']);
    // build query
    $sql = $this->buildQuery(TRUE);
    // var_dump($sql);

    // build array of result based on column headers. This method also allows
    // modifying column headers before using it to build result set i.e $rows.
    $rows = array();
    $this->buildRows($sql, $rows);

    // format result set.
    $this->formatDisplay($rows);

    // assign variables to templates
    $this->doTemplateAssignment($rows);

    // do print / pdf / instance stuff if needed
    $this->endPostProcess($rows);
  }

  /**
   * @param $rows
   * @param $entryFound
   * @param $row
   * @param int $rowId
   * @param $rowNum
   * @param $types
   *
   * @return bool
   */
  private function _initBasicRow(&$rows, &$entryFound, $row, $rowId, $rowNum, $types) {
    if (!array_key_exists($rowId, $row)) {
      return FALSE;
    }

    $value = $row[$rowId];
    if ($value) {
      $rows[$rowNum][$rowId] = $types[$value];
    }
    $entryFound = TRUE;
  }

  /**
   * Alter display of rows.
   *
   * Iterate through the rows retrieved via SQL and make changes for display purposes,
   * such as rendering contacts as links.
   *
   * @param array $rows
   *   Rows generated by SQL, with an array for each row.
   */
  public function alterDisplay(&$rows) {
    $entryFound = FALSE;
    $eventType = CRM_Core_OptionGroup::values('event_type');

    $financialTypes = CRM_Contribute_PseudoConstant::financialType();
    $contributionStatus = CRM_Contribute_PseudoConstant::contributionStatus();
    $paymentInstruments = CRM_Contribute_PseudoConstant::paymentInstrument();

    foreach ($rows as $rowNum => $row) {
      // make count columns point to detail report
      // convert display name to links
      if (array_key_exists('civicrm_participant_event_id', $row)) {
        $eventId = $row['civicrm_participant_event_id'];
        if ($eventId) {
          $rows[$rowNum]['civicrm_participant_event_id'] = CRM_Event_PseudoConstant::event($eventId, FALSE);

          $url = CRM_Report_Utils_Report::getNextUrl('event/income',
            'reset=1&force=1&id_op=in&id_value=' . $eventId,
            $this->_absoluteUrl, $this->_id, $this->_drilldownReport
          );
          $rows[$rowNum]['civicrm_participant_event_id_link'] = $url;
          $rows[$rowNum]['civicrm_participant_event_id_hover'] = ts("View Event Income Details for this Event");
        }
        $entryFound = TRUE;
      }

      // handle event type id
      $this->_initBasicRow($rows, $entryFound, $row, 'civicrm_event_event_type_id', $rowNum, $eventType);

      // handle participant status id
      if (array_key_exists('civicrm_participant_status_id', $row)) {
        $statusId = $row['civicrm_participant_status_id'];
        if ($statusId) {
          $rows[$rowNum]['civicrm_participant_status_id'] = CRM_Event_PseudoConstant::participantStatus($statusId, FALSE, 'label');
        }
        $entryFound = TRUE;
      }

      // handle participant role id
      if (array_key_exists('civicrm_participant_role_id', $row)) {
        $roleId = $row['civicrm_participant_role_id'];
        if ($roleId) {
          $roles = explode(CRM_Core_DAO::VALUE_SEPARATOR, $roleId);
          $roleId = array();
          foreach ($roles as $role) {
            $roleId[$role] = CRM_Event_PseudoConstant::participantRole($role, FALSE);
          }
          $rows[$rowNum]['civicrm_participant_role_id'] = implode(', ', $roleId);
        }
        $entryFound = TRUE;
      }

      // Handel value seperator in Fee Level
      if (array_key_exists('civicrm_participant_participant_fee_level', $row)) {
        $feeLevel = $row['civicrm_participant_participant_fee_level'];
        if ($feeLevel) {
          CRM_Event_BAO_Participant::fixEventLevel($feeLevel);
          $rows[$rowNum]['civicrm_participant_participant_fee_level'] = $feeLevel;
        }
        $entryFound = TRUE;
      }

      // Convert display name to link
      $displayName = CRM_Utils_Array::value('civicrm_contact_sort_name_linked', $row);
      $cid = CRM_Utils_Array::value('civicrm_contact_id', $row);
      $id = CRM_Utils_Array::value('civicrm_participant_participant_record', $row);

      if ($displayName && $cid && $id) {
        $url = CRM_Report_Utils_Report::getNextUrl('contact/detail',
          "reset=1&force=1&id_op=eq&id_value=$cid",
          $this->_absoluteUrl, $this->_id, $this->_drilldownReport
        );

        $viewUrl = CRM_Utils_System::url("civicrm/contact/view/participant",
          "reset=1&id=$id&cid=$cid&action=view&context=participant"
        );

        $contactTitle = ts('View Contact Details');
        $participantTitle = ts('View Participant Record');

        $rows[$rowNum]['civicrm_contact_sort_name_linked'] = "<a title='$contactTitle' href=$url>$displayName</a>";
        if ($this->_outputMode !== 'csv') {
          $rows[$rowNum]['civicrm_contact_sort_name_linked'] .=
            "<span style='float: right;'><a title='$participantTitle' href=$viewUrl>" .
            ts('View') . "</a></span>";
        }
        $entryFound = TRUE;
      }

      // Handle country id
      if (array_key_exists('civicrm_address_country_id', $row)) {
        $countryId = $row['civicrm_address_country_id'];
        if ($countryId) {
          $rows[$rowNum]['civicrm_address_country_id'] = CRM_Core_PseudoConstant::country($countryId, TRUE);
        }
        $entryFound = TRUE;
      }

      // Handle state/province id
      if (array_key_exists('civicrm_address_state_province_id', $row)) {
        $provinceId = $row['civicrm_address_state_province_id'];
        if ($provinceId) {
          $rows[$rowNum]['civicrm_address_state_province_id'] = CRM_Core_PseudoConstant::stateProvince($provinceId, TRUE);
        }
        $entryFound = TRUE;
      }

      // Handle employer id
      if (array_key_exists('civicrm_contact_employer_id', $row)) {
        $employerId = $row['civicrm_contact_employer_id'];
        if ($employerId) {
          $rows[$rowNum]['civicrm_contact_employer_id'] = CRM_Contact_BAO_Contact::displayName($employerId);
          $url = CRM_Utils_System::url('civicrm/contact/view',
            'reset=1&cid=' . $employerId, $this->_absoluteUrl
          );
          $rows[$rowNum]['civicrm_contact_employer_id_link'] = $url;
          $rows[$rowNum]['civicrm_contact_employer_id_hover'] = ts('View Contact Summary for this Contact.');
        }
      }

      // Convert campaign_id to campaign title
      $this->_initBasicRow($rows, $entryFound, $row, 'civicrm_participant_campaign_id', $rowNum, $this->activeCampaigns);

      // handle contribution status
      $this->_initBasicRow($rows, $entryFound, $row, 'civicrm_contribution_contribution_status_id', $rowNum, $contributionStatus);

      // handle payment instrument
      $this->_initBasicRow($rows, $entryFound, $row, 'civicrm_contribution_payment_instrument_id', $rowNum, $paymentInstruments);

      // handle financial type
      $this->_initBasicRow($rows, $entryFound, $row, 'civicrm_contribution_financial_type_id', $rowNum, $financialTypes);

      $entryFound = $this->alterDisplayContactFields($row, $rows, $rowNum, 'event/participantListing', 'View Event Income Details') ? TRUE : $entryFound;

      // display birthday in the configured custom format
      if (array_key_exists('civicrm_contact_birth_date', $row)) {
        $birthDate = $row['civicrm_contact_birth_date'];
        if ($birthDate) {
          $rows[$rowNum]['civicrm_contact_birth_date'] = CRM_Utils_Date::customFormat($birthDate, '%Y%m%d');
        }
        $entryFound = TRUE;
      }

      // skip looking further in rows, if first row itself doesn't
      // have the column we need
      if (!$entryFound) {
        break;
      }
    }
  }

    /**
   * Filter statistics.
   *
   * @param array $statistics
   */
  public function filterStat(&$statistics) {
    foreach ($this->_columns as $tableName => $table) {
      if (strpos($tableName, 'civicrm_price_set') !== false) {
        $pricesetId = array_pop(explode('_',$tableName));
        
        // Check to see if price set is assigned to user selected event(s). 
        // If not, remove the table from the select clause and continue
        if ($this->checkPriceSetEntity($pricesetId, $this->_params['event_id_value']) == 0) {
          unset($this->_columns['tableName']);
          continue;
        }

        // Add price sets requiring joins
        // $this->_reqPriceSets[$tableName] = $tableName;
          
      }
      if (array_key_exists('filters', $table)) {
        foreach ($table['filters'] as $fieldName => $field) {
          if (CRM_Utils_Array::value('type', $field) & CRM_Utils_Type::T_DATE &&
            CRM_Utils_Array::value('operatorType', $field) !=
            CRM_Report_Form::OP_MONTH
          ) {
            list($from, $to)
              = $this->getFromTo(
                CRM_Utils_Array::value("{$fieldName}_relative", $this->_params),
                CRM_Utils_Array::value("{$fieldName}_from", $this->_params),
                CRM_Utils_Array::value("{$fieldName}_to", $this->_params),
                CRM_Utils_Array::value("{$fieldName}_from_time", $this->_params),
                CRM_Utils_Array::value("{$fieldName}_to_time", $this->_params)
              );
            $from_time_format = !empty($this->_params["{$fieldName}_from_time"]) ? 'h' : 'd';
            $from = CRM_Utils_Date::customFormat($from, NULL, array($from_time_format));

            $to_time_format = !empty($this->_params["{$fieldName}_to_time"]) ? 'h' : 'd';
            $to = CRM_Utils_Date::customFormat($to, NULL, array($to_time_format));

            if ($from || $to) {
              $statistics['filters'][] = array(
                'title' => $field['title'],
                'value' => ts("Between %1 and %2", array(1 => $from, 2 => $to)),
              );
            }
            elseif (in_array($rel = CRM_Utils_Array::value("{$fieldName}_relative", $this->_params),
              array_keys($this->getOperationPair(CRM_Report_Form::OP_DATE))
            )) {
              $pair = $this->getOperationPair(CRM_Report_Form::OP_DATE);
              $statistics['filters'][] = array(
                'title' => $field['title'],
                'value' => $pair[$rel],
              );
            }
          }
          else {
            $op = CRM_Utils_Array::value("{$fieldName}_op", $this->_params);
            $value = NULL;
            if ($op) {
              $pair = $this->getOperationPair(
                CRM_Utils_Array::value('operatorType', $field),
                $fieldName
              );
              $min = CRM_Utils_Array::value("{$fieldName}_min", $this->_params);
              $max = CRM_Utils_Array::value("{$fieldName}_max", $this->_params);
              $val = CRM_Utils_Array::value("{$fieldName}_value", $this->_params);
              if (in_array($op, array('bw', 'nbw')) && ($min || $max)) {
                $value = "{$pair[$op]} $min " . ts('and') . " $max";
              }
              elseif ($val && CRM_Utils_Array::value('operatorType', $field) & self::OP_ENTITYREF) {
                $this->setEntityRefDefaults($field, $tableName);
                $result = civicrm_api3($field['attributes']['entity'], 'getlist',
                  array('id' => $val) +
                  CRM_Utils_Array::value('api', $field['attributes'], array()));
                $values = array();
                foreach ($result['values'] as $v) {
                  $values[] = $v['label'];
                }
                $value = "{$pair[$op]} " . implode(', ', $values);
              }
              elseif ($op == 'nll' || $op == 'nnll') {
                $value = $pair[$op];
              }
              elseif (is_array($val) && (!empty($val))) {
                $options = CRM_Utils_Array::value('options', $field, array());
                foreach ($val as $key => $valIds) {
                  if (isset($options[$valIds])) {
                    $val[$key] = $options[$valIds];
                  }
                }
                $pair[$op] = (count($val) == 1) ? (($op == 'notin' || $op ==
                  'mnot') ? ts('Is Not') : ts('Is')) : CRM_Utils_Array::value($op, $pair);
                $val = implode(', ', $val);
                $value = "{$pair[$op]} " . $val;
              }
              elseif (!is_array($val) && (!empty($val) || $val == '0') &&
                isset($field['options']) &&
                is_array($field['options']) && !empty($field['options'])
              ) {
                $value = CRM_Utils_Array::value($op, $pair) . " " .
                  CRM_Utils_Array::value($val, $field['options'], $val);
              }
              elseif ($val) {
                $value = CRM_Utils_Array::value($op, $pair) . " " . $val;
              }
            }
            if ($value) {
              $statistics['filters'][] = array(
                'title' => CRM_Utils_Array::value('title', $field),
                'value' => $value,
              );
            }
          }
        }
      }
    }
  }

    /**
   * Apply common settings to entityRef fields.
   *
   * @param array $field
   * @param string $table
   */
  private function setEntityRefDefaults(&$field, $table) {
    $field['attributes'] = $field['attributes'] ? $field['attributes'] : array();
    $field['attributes'] += array(
      'entity' => CRM_Core_DAO_AllCoreTables::getBriefName(CRM_Core_DAO_AllCoreTables::getClassForTable($table)),
      'multiple' => TRUE,
      'placeholder' => ts('- select -'),
    );
  }

}
