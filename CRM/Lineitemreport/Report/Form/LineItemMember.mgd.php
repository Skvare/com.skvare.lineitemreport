<?php
/**
 * @file
 * Provides entity definition for registering the membership line item report template
 */

return array(
  0 => array(
    'name' => 'Membership Line Items',
    'entity' => 'ReportTemplate',
    'params' => array(
      'version' => 3,
      'label' => 'Membership Line Items',
      'description' => 'Membership report user-selectable columns and filters for line items',
      'class_name' => 'CRM_Lineitemreport_Report_Form_LineItemMember',
      'report_url' => 'lineitem/membership',
      'component' => 'CiviMember',
    ),
  ),
);