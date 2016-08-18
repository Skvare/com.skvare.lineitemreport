<?php

/**
 * @file 
 * Provides fields and preprocessing for participant line item selections
 * \CRM\Lineitemreport\Report\Form\LineItemParticipant
 */
class CRM_Lineitemreport_Report_Form_LineItemParticipant extends CRM_Lineitemreport_Report_Form_LineItem {

  
  protected $_entity = 'participant';

  /**
   * ennumerate which custom field groups will be exposed to the report query
   *
   * @var        array
   */
  protected $_customGroupExtends = array(
    'Participant',
    'Contact',
    'Event',
  );

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

    if (is_null(CRM_Utils_Request::retrieve('event_id_value','String')))
    {
      $message = 'You must choose one or more events from the filters tab before running this report';
      $title = 'Choose one or more events';
      // $this->checkJoinCount($message,$title);
      CRM_Core_Session::setStatus($message,$title,$type='error', $options=array('expires'=>0));
    }

    $this->_autoIncludeIndexedFieldsAsOrderBys = 1;

    // Check if CiviCampaign is a) enabled and b) has active campaigns
    $config = CRM_Core_Config::singleton();
    $campaignEnabled = in_array("CiviCampaign", $config->enableComponents);
    if ($campaignEnabled) {
      $getCampaigns = CRM_Campaign_BAO_Campaign::getPermissionedCampaigns(NULL, NULL, TRUE, FALSE, TRUE);
      $this->activeCampaigns = $getCampaigns['campaigns'];
      asort($this->activeCampaigns);
    }

    $this->_columns = array(
      'civicrm_contact' => array(
        'dao' => 'CRM_Contact_DAO_Contact',
        'fields' => array_merge(array(
          // CRM-17115 - to avoid changing report output at this stage re-instate
          // old field name for sort name
          'sort_name_linked' => array(
            'title' => ts('Sort Name'),
            'required' => TRUE,
            'no_repeat' => TRUE,
            'dbAlias' => 'contact_civireport.sort_name',
          )),
          $this->getBasicContactFields(),
          array(
            'age_at_event' => array(
              'title' => ts('Age at Event'),
              'dbAlias' => 'TIMESTAMPDIFF(YEAR, contact_civireport.birth_date, event_civireport.start_date)',
            ),
          )
        ),
        'grouping' => 'contact-fields',
        'order_bys' => array(
          'sort_name' => array(
            'title' => ts('Last Name, First Name'),
            'default' => '1',
            'default_weight' => '0',
            'default_order' => 'ASC',
          ),
          'first_name' => array(
            'name' => 'first_name',
            'title' => ts('First Name'),
          ),
          'gender_id' => array(
            'name' => 'gender_id',
            'title' => ts('Gender'),
          ),
          'birth_date' => array(
            'name' => 'birth_date',
            'title' => ts('Birth Date'),
          ),
          'age_at_event' => array(
            'name' => 'age_at_event',
            'title' => ts('Age at Event'),
          ),
          'contact_type' => array(
            'title' => ts('Contact Type'),
          ),
          'contact_sub_type' => array(
            'title' => ts('Contact Subtype'),
          ),
        ),
        'filters' => array(
          'sort_name' => array(
            'title' => ts('Participant Name'),
            'operator' => 'like',
          ),
          'gender_id' => array(
            'title' => ts('Gender'),
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options' => CRM_Core_PseudoConstant::get('CRM_Contact_DAO_Contact', 'gender_id'),
          ),
          'birth_date' => array(
            'title' => ts('Birth Date'),
            'operatorType' => CRM_Report_Form::OP_DATE,
          ),
          'contact_type' => array(
            'title' => ts('Contact Type'),
          ),
          'contact_sub_type' => array(
            'title' => ts('Contact Subtype'),
          ),
        ),
      ),
      'civicrm_email' => array(
        'dao' => 'CRM_Core_DAO_Email',
        'fields' => array(
          'email' => array(
            'title' => ts('Email'),
            'no_repeat' => TRUE,
          ),
        ),
        'grouping' => 'contact-fields',
        'filters' => array(
          'email' => array(
            'title' => ts('Participant E-mail'),
            'operator' => 'like',
          ),
        ),
      ),
      'civicrm_address' => array(
        'dao' => 'CRM_Core_DAO_Address',
        'fields' => array(
          'street_address' => NULL,
          'city' => NULL,
          'postal_code' => NULL,
          'state_province_id' => array(
            'title' => ts('State/Province'),
          ),
          'country_id' => array(
            'title' => ts('Country'),
          ),
        ),
        'grouping' => 'contact-fields',
      ),
      'civicrm_participant' => array(
        'dao' => 'CRM_Event_DAO_Participant',
        'fields' => array(
          'participant_id' => array('title' => 'Participant ID'),
          'participant_record' => array(
            'name' => 'id',
            'no_display' => TRUE,
            'required' => TRUE,
          ),
          'event_id' => array(
            'default' => TRUE,
            'type' => CRM_Utils_Type::T_STRING,
          ),
          'status_id' => array(
            'title' => ts('Status'),
            'default' => TRUE,
          ),
          'role_id' => array(
            'title' => ts('Role'),
            'default' => TRUE,
          ),
          'fee_currency' => array(
            'required' => TRUE,
            'no_display' => TRUE,
          ),
          'participant_fee_level' => NULL,
          'participant_fee_amount' => NULL,
          'participant_register_date' => array('title' => ts('Registration Date')),
          'total_paid' => array(
            'title' => ts('Total Paid'),
            'dbAlias' => 'SUM(ft.total_amount)',
            'type' => 1024,
          ),
          'balance' => array(
            'title' => ts('Balance'),
            'dbAlias' => 'participant_civireport.fee_amount - SUM(ft.total_amount)',
            'type' => 1024,
          ),
        ),
        'grouping' => 'event-fields',
        'filters' => array(
          'event_id' => array(
            'name' => 'event_id',
            'title' => ts('Event'),
            'operatorType' => CRM_Report_Form::OP_ENTITYREF,
            'type' => CRM_Utils_Type::T_INT,
            'required' => TRUE,
            'attributes' => array(
              'entity' => 'event',
              'select' => array('minimumInputLength' => 0),
            ),
          ),
          'sid' => array(
            'name' => 'status_id',
            'title' => ts('Participant Status'),
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options' => CRM_Event_PseudoConstant::participantStatus(NULL, NULL, 'label'),
          ),
          'rid' => array(
            'name' => 'role_id',
            'title' => ts('Participant Role'),
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options' => CRM_Event_PseudoConstant::participantRole(),
          ),
          'participant_register_date' => array(
            'title' => 'Registration Date',
            'operatorType' => CRM_Report_Form::OP_DATE,
          ),
          'fee_currency' => array(
            'title' => ts('Fee Currency'),
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options' => CRM_Core_OptionGroup::values('currencies_enabled'),
            'default' => NULL,
            'type' => CRM_Utils_Type::T_STRING,
          ),

        ),
        'order_bys' => array(
          'participant_register_date' => array(
            'title' => ts('Registration Date'),
            'default_weight' => '1',
            'default_order' => 'ASC',
          ),
          'event_id' => array(
            'title' => ts('Event'),
            'default_weight' => '1',
            'default_order' => 'ASC',
          ),
        ),
      ),
      'civicrm_phone' => array(
        'dao' => 'CRM_Core_DAO_Phone',
        'fields' => array(
          'phone' => array(
            'title' => ts('Phone'),
            'default' => TRUE,
            'no_repeat' => TRUE,
          ),
        ),
        'grouping' => 'contact-fields',
      ),
      'civicrm_event' => array(
        'dao' => 'CRM_Event_DAO_Event',
        'fields' => array(
          'event_type_id' => array('title' => ts('Event Type')),
          'event_start_date' => array('title' => ts('Event Start Date')),
        ),
        'grouping' => 'event-fields',
        'filters' => array(
          'eid' => array(
            'name' => 'event_type_id',
            'title' => ts('Event Type'),
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options' => CRM_Core_OptionGroup::values('event_type'),
          ),
          'event_start_date' => array(
            'title' => ts('Event Start Date'),
            'operatorType' => CRM_Report_Form::OP_DATE,
          ),
        ),
        'order_bys' => array(
          'event_type_id' => array(
            'title' => ts('Event Type'),
            'default_weight' => '2',
            'default_order' => 'ASC',
          ),
        ),
      ),
      'civicrm_contribution' => array(
        'dao' => 'CRM_Contribute_DAO_Contribution',
        'fields' => array(
          'contribution_id' => array(
            'name' => 'id',
            'no_display' => TRUE,
            'required' => TRUE,
            'csv_display' => TRUE,
            'title' => ts('Contribution ID'),
          ),
          'financial_type_id' => array('title' => ts('Financial Type')),
          'receive_date' => array('title' => ts('Payment Date')),
          'contribution_status_id' => array('title' => ts('Contribution Status')),
          'payment_instrument_id' => array('title' => ts('Payment Type')),
          'contribution_source' => array(
            'name' => 'source',
            'title' => ts('Contribution Source'),
          ),
          'currency' => array(
            'required' => TRUE,
            'no_display' => TRUE,
          ),
          'trxn_id' => NULL,
          'fee_amount' => array('title' => ts('Transaction Fee')),
          'net_amount' => NULL,
        ),
        'grouping' => 'contrib-fields',
        'filters' => array(
          'receive_date' => array(
            'title' => 'Payment Date',
            'operatorType' => CRM_Report_Form::OP_DATE,
          ),
          'financial_type_id' => array(
            'title' => ts('Financial Type'),
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options' => CRM_Contribute_PseudoConstant::financialType(),
          ),
          'currency' => array(
            'title' => ts('Contribution Currency'),
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options' => CRM_Core_OptionGroup::values('currencies_enabled'),
            'default' => NULL,
            'type' => CRM_Utils_Type::T_STRING,
          ),
          'payment_instrument_id' => array(
            'title' => ts('Payment Type'),
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options' => CRM_Contribute_PseudoConstant::paymentInstrument(),
          ),
          'contribution_status_id' => array(
            'title' => ts('Contribution Status'),
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options' => CRM_Contribute_PseudoConstant::contributionStatus(),
            'default' => NULL,
          ),
        ),
      ),
      'civicrm_line_item' => array(
        'dao' => 'CRM_Price_DAO_LineItem',
        'grouping' => 'priceset-fields',
        'filters' => array(
          'price_field_value_id' => array(
            'name' => 'price_field_value_id',
            'title' => ts('Fee Level'),
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options' => $this->getPriceLevels(),
          ),
        ),
      ),
    );


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

    // If we have active campaigns add those elements to both the fields and filters
    if ($campaignEnabled && !empty($this->activeCampaigns)) {
      $this->_columns['civicrm_participant']['fields']['campaign_id'] = array(
        'title' => ts('Campaign'),
        'default' => 'false',
      );
      $this->_columns['civicrm_participant']['filters']['campaign_id'] = array(
        'title' => ts('Campaign'),
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'options' => $this->activeCampaigns,
      );
      $this->_columns['civicrm_participant']['order_bys']['campaign_id'] = array(
        'title' => ts('Campaign'),
      );
    }

    $this->organizeColumns();
        

    $this->_currencyColumn = 'civicrm_participant_fee_currency';
    parent::__construct();
  }

  /*public function getPriceSets($ids) {
    $fields = array();
    $pricesets = civicrm_api3('PriceSet', 'get', array(
      'sequential' => 1,
      'id' => array('IN'=>$ids),
      'is_active' => 1,
      'options' => array('limit'=>1000),
    ));

    return $pricesets['values'];
  }*/

  /**
   * get the appropriate price field data based on the price sets and entity id. Return the data as needed for the select field list, filters, or other usage
   *
   * @param      string  $psId      price set id
   * @param      string  $format    return format for price set data
   * 
   * @return     array price field ids
   */
  public function getPriceFields($psId, $format=null) {
    // if (is_array($entityId)) $entityId = implode(',', $entityId);
    switch($format) {
      case 'fieldlist':
        $select = "SELECT DISTINCT li.price_field_id FROM civicrm_line_item li
        JOIN civicrm_price_field pf ON li.price_field_id = pf.id
        -- JOIN civicrm_price_set_entity pse ON pf.price_set_id = pse.price_set_id";
        $where = "WHERE pf.price_set_id = $psId";
        // if (!empty($entityId)) $where .= " AND pse.entity_id IN ($entityId)";

        $order = "ORDER BY li.price_field_id;";
        $query = sprintf("%s\n%s\n%s",$select,$where,$order);

        $dao = CRM_Core_DAO::executeQuery($query);
        $fields = array();
        while ($dao->fetch()) {
          $fields[] = $dao->price_field_id;
        }

        return $fields;

      break;

      case 'filters':
        $select = "SELECT DISTINCT li.price_field_id, li.price_field_value_id, pf.name, pf.label, pf.is_enter_qty, pf.html_type, pf.price_set_id FROM civicrm_line_item li
        JOIN civicrm_price_field pf ON li.price_field_id = pf.id
        -- JOIN civicrm_price_set_entity pse ON pf.price_set_id = pse.price_set_id";
        $where = "WHERE pf.price_set_id = $psId";
        // if (isset($entityId)) $where .= " AND pse.entity_id IN ($entityId)";

        $order = "ORDER BY li.price_field_id;";
        $query = sprintf("%s\n%s\n%s",$select,$where,$order);

        // var_dump($query);
        $dao = CRM_Core_DAO::executeQuery($query);
        $filters = array();
        while ($dao->fetch()) {
          $fieldname = sprintf('ps%d_%s',$dao->price_set_id,$dao->name);
          $filters[$fieldname] = array(
            'title' => $psId.'_'.$dao->label,
            'alias' => 'pf'.$dao->price_field_id,
            'type' => CRM_Utils_Type::T_INT,
          );
          if ($dao->is_enter_qty == 1) $filters[$fieldname]['name'] = 'qty';
          if ($dao->html_type != 'Text') {
            $filters[$fieldname]['operatorType'] = CRM_Report_Form::OP_MULTISELECT;
            $result = civicrm_api3('PriceFieldValue', 'get', array(
                'sequential' => 1,
                'return' => "name,label",
                'price_field_id' => $dao->price_field_id,
              ));
            $options = array();
            foreach($result['values'] AS $fieldOption) {
              $options[$fieldOption['id']] = $fieldOption['label'];
            }
            $filters[$fieldname]['options'] = $options;
          } else 
            $filters[$fieldname]['operatorType'] = CRM_Report_Form::OP_INT;
          $filters[$fieldname]['name'] = 'price_field_value_id';
        }

        return $filters;
      break;

      default:
        $select = "SELECT DISTINCT li.price_field_id, pf.name, pf.label, pf.is_enter_qty, pf.html_type FROM civicrm_line_item li
        JOIN civicrm_price_field pf ON li.price_field_id = pf.id
        -- JOIN civicrm_price_set_entity pse ON pf.price_set_id = pse.price_set_id";
        $where = "WHERE pf.price_set_id = $psId";
        // if (!empty($entityId)) $where .= " AND pse.entity_id IN ($entityId)";

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
          if ($dao->is_enter_qty == 1) {
            $fields[$dao->name]['name'] = 'qty';
            $fields[$dao->name]['type'] = CRM_Utils_Type::T_INT;
          }
          else if ($dao->html_type != 'Text')
            $fields[$dao->name]['name'] = 'label';
          else
            $fields[$dao->name]['name'] = 'line_total';

        }
        return $fields;
    }
    
  }

  /**
   * Determine relevant price sets for given events
   *
   * @param      array  $events  user selected event filter
   *
   * @return     array
   */
  public function getPriceSetsByEvent($events=null)
  {
    
    $query = "SELECT DISTINCT pf.price_set_id id FROM civicrm_line_item li
            JOIN civicrm_participant p ON li.entity_id = p.id
            JOIN civicrm_price_field pf ON li.price_field_id = pf.id
            WHERE li.entity_table = 'civicrm_participant'";

    if (count($events) > 0) {
      $events = implode(',',$events);
      $query .= sprintf("AND p.event_id IN (%s)", $events);
    }

    $dao = CRM_Core_DAO::executeQuery($query);
    
    $priceSets = array();
    while ($dao->fetch())
    {
      $priceSets[] = $dao->id;
    }

    if (count($priceSets) > 0) {
      return $priceSets;
    }

    return false;
  }

  /**
   * Organize the columns and filters into groups by price set for display as accordions. 
   * Limit fields in the select clause based on the relevant price sets
   */
  public function organizeColumns() {
      // Create a column grouping for each price set

      $this->_extendedEntities = array('participant'=>'CiviEvent','contribution'=>'CiviContribute','membership'=>'CiviMember');
      $this->_entities = array('contribution','membership','participant');

      switch ($this->_entity) {
        case 'participant':
          foreach (array_diff($this->_entities, array($this->_entity)) AS $other) {
            unset($this->_columns['civicrm_'.$other]);
            unset($this->_extendedEntities[$other]);
          }
          $entityId = CRM_Utils_Request::retrieve('event_id_value','String');
          if (!is_array('entityId')) $entityId = (array) $entityId;
        break;

      }

      $this->_relevantPriceSets = $this->getPriceSetsByEvent($entityId);
      foreach ($this->getPriceSets($this->_relevantPriceSets) AS $ps) {
        $this->_columns['civicrm_price_set_'.$ps['id']] = array(
          'alias' => 'ps'.$ps['id'],
          'dao' => 'CRM_Price_DAO_LineItem',
          'grouping' => 'priceset-fields-'.$ps['name'],
          'group_title' => 'Price Fields - '.$ps['title'],
        );

          $this->_columns['civicrm_price_set_'.$ps['id']]['fields'] = $this->getPriceFields($ps['id']);
          $this->_columns['civicrm_price_set_'.$ps['id']]['filters'] = $this->getPriceFields($ps['id'], 'filters');
          // var_dump($this->_columns['civicrm_price_set_93']['filters']);
      }
  }

  public function preProcess()
  {
    parent::preProcess();
  }



}
