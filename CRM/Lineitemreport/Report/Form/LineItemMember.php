<?php

/**
 * @file
 */
class CRM_Lineitemreport_Report_Form_LineItemMember extends CRM_Lineitemreport_Report_Form_LineItem {


  /**
   * a flag to denote whether the civicrm_member* tables need to be included in the SQL query
   *
   * @var        boolean
   */
  protected $_memberField = FALSE;
  

  protected $_customGroupExtends = array(
    'Contact',
    'Individual',
    'Contribution',
    'Membership',
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

    if (empty($this->_submitValues)) {
      $message = 'You must choose one or more membership types from the filters tab before running this report';
      $title = 'Choose one or more membership types';
      $this->checkJoinCount($message,$title);
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
          'total_amount' => array(
            'title' => ts('Payment Amount (most recent)'),
            'statistics' => array('sum' => ts('Amount')),
          ),
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
          'contribution_page_id' => array(
            'title' => ts('Contribution Page'),
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options' => CRM_Contribute_PseudoConstant::contributionPage(),
            'type' => CRM_Utils_Type::T_INT,
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
      'civicrm_membership' => array(
        'dao' => 'CRM_Member_DAO_Membership',
        'fields' => array(
          'membership_type_id' => array(
            'title' => 'Membership Type',
            'required' => TRUE,
            'no_repeat' => TRUE,
          ),
          'membership_start_date' => array(
            'title' => ts('Start Date'),
            'default' => TRUE,
          ),
          'membership_end_date' => array(
            'title' => ts('End Date'),
            'default' => TRUE,
          ),
          'join_date' => array(
            'title' => ts('Join Date'),
            'default' => TRUE,
          ),
          'source' => array('title' => 'Source'),
        ),
        'filters' => array(
          'join_date' => array('operatorType' => CRM_Report_Form::OP_DATE),
          'membership_start_date' => array('operatorType' => CRM_Report_Form::OP_DATE),
          'membership_end_date' => array('operatorType' => CRM_Report_Form::OP_DATE),
          'owner_membership_id' => array(
            'title' => ts('Membership Owner ID'),
            'operatorType' => CRM_Report_Form::OP_INT,
          ),
          'tid' => array(
            'name' => 'membership_type_id',
            'title' => ts('Membership Types'),
            'type' => CRM_Utils_Type::T_INT,
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options' => CRM_Member_PseudoConstant::membershipType(),
          ),
        ),
        'order_bys' => array(
          'membership_type_id' => array(
            'title' => ts('Membership Type'),
            'default' => '0',
            'default_weight' => '1',
            'default_order' => 'ASC',
          ),
        ),
        'grouping' => 'member-fields',
        'group_bys' => array(
          'id' => array(
            'title' => ts('Membership'),
            'default' => TRUE,
          ),
        ),
      ),
      'civicrm_membership_status' => array(
        'dao' => 'CRM_Member_DAO_MembershipStatus',
        'alias' => 'mem_status',
        'fields' => array(
          'name' => array(
            'title' => ts('Status'),
            'default' => TRUE,
          ),
        ),
        'filters' => array(
          'sid' => array(
            'name' => 'id',
            'title' => ts('Status'),
            'type' => CRM_Utils_Type::T_INT,
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options' => CRM_Member_PseudoConstant::membershipStatus(NULL, NULL, 'label'),
          ),
        ),
        'grouping' => 'member-fields',
      ),
      /*'civicrm_line_item' => array(
        'alias' => 'pf',
        'dao' => 'CRM_Price_DAO_LineItem',
        'fields' => $this->getPriceFields(),
        'grouping' => 'priceset-fields',
        'group_title' => 'Price Fields',
      ),*/
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

   $this->organizeColumns();
        

    $this->_currencyColumn = 'civicrm_participant_fee_currency';
    parent::__construct();
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
    $checkList = array();

    $contributionTypes = CRM_Contribute_PseudoConstant::financialType();
    $contributionStatus = CRM_Contribute_PseudoConstant::contributionStatus();
    $paymentInstruments = CRM_Contribute_PseudoConstant::paymentInstrument();

    $repeatFound = FALSE;
    foreach ($rows as $rowNum => $row) {
      if ($repeatFound == FALSE ||
        $repeatFound < $rowNum - 1
      ) {
        unset($checkList);
        $checkList = array();
      }
      if (!empty($this->_noRepeats) && $this->_outputMode != 'csv') {
        // not repeat contact display names if it matches with the one
        // in previous row
        foreach ($row as $colName => $colVal) {
          if (in_array($colName, $this->_noRepeats) &&
            $rowNum > 0
          ) {
            if ($rows[$rowNum][$colName] == $rows[$rowNum - 1][$colName] ||
              (!empty($checkList[$colName]) &&
              in_array($colVal, $checkList[$colName]))
              ) {
              $rows[$rowNum][$colName] = "";
              // CRM-15917: Don't blank the name if it's a different contact
              if ($colName == 'civicrm_contact_exposed_id') {
                $rows[$rowNum]['civicrm_contact_sort_name'] = "";
              }
              $repeatFound = $rowNum;
            }
          }
          if (in_array($colName, $this->_noRepeats)) {
            $checkList[$colName][] = $colVal;
          }
        }
      }

      if (array_key_exists('civicrm_membership_membership_type_id', $row)) {
        if ($value = $row['civicrm_membership_membership_type_id']) {
          $rows[$rowNum]['civicrm_membership_membership_type_id'] = CRM_Member_PseudoConstant::membershipType($value, FALSE);
        }
        $entryFound = TRUE;
      }

      if (array_key_exists('civicrm_address_state_province_id', $row)) {
        if ($value = $row['civicrm_address_state_province_id']) {
          $rows[$rowNum]['civicrm_address_state_province_id'] = CRM_Core_PseudoConstant::stateProvince($value, FALSE);
        }
        $entryFound = TRUE;
      }

      if (array_key_exists('civicrm_address_country_id', $row)) {
        if ($value = $row['civicrm_address_country_id']) {
          $rows[$rowNum]['civicrm_address_country_id'] = CRM_Core_PseudoConstant::country($value, FALSE);
        }
        $entryFound = TRUE;
      }

      if (array_key_exists('civicrm_contact_sort_name', $row) &&
        $rows[$rowNum]['civicrm_contact_sort_name'] &&
        array_key_exists('civicrm_contact_id', $row)
      ) {
        $url = CRM_Utils_System::url("civicrm/contact/view",
          'reset=1&cid=' . $row['civicrm_contact_id'],
          $this->_absoluteUrl
        );
        $rows[$rowNum]['civicrm_contact_sort_name_link'] = $url;
        $rows[$rowNum]['civicrm_contact_sort_name_hover'] = ts("View Contact Summary for this Contact.");
        $entryFound = TRUE;
      }

      if ($value = CRM_Utils_Array::value('civicrm_contribution_financial_type_id', $row)) {
        $rows[$rowNum]['civicrm_contribution_financial_type_id'] = $contributionTypes[$value];
        $entryFound = TRUE;
      }
      if ($value = CRM_Utils_Array::value('civicrm_contribution_contribution_status_id', $row)) {
        $rows[$rowNum]['civicrm_contribution_contribution_status_id'] = $contributionStatus[$value];
        $entryFound = TRUE;
      }
      if ($value = CRM_Utils_Array::value('civicrm_contribution_payment_instrument_id', $row)) {
        $rows[$rowNum]['civicrm_contribution_payment_instrument_id'] = $paymentInstruments[$value];
        $entryFound = TRUE;
      }

      // Convert campaign_id to campaign title
      if (array_key_exists('civicrm_membership_campaign_id', $row)) {
        if ($value = $row['civicrm_membership_campaign_id']) {
          $rows[$rowNum]['civicrm_membership_campaign_id'] = $this->activeCampaigns[$value];
          $entryFound = TRUE;
        }
      }
      $entryFound = $this->alterDisplayAddressFields($row, $rows, $rowNum, 'member/detail', 'List all memberships(s) for this ') ? TRUE : $entryFound;
      $entryFound = $this->alterDisplayContactFields($row, $rows, $rowNum, 'member/detail', 'List all memberships(s) for this ') ? TRUE : $entryFound;

      if (!$entryFound) {
        break;
      }
    }
  }


}
