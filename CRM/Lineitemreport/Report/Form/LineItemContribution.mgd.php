<?php
/**
 * @file
 * Provides entity definition for registering the contribution line item report template
 */

return [
  0 => [
    'name' => 'Contribution Line Items',
    'entity' => 'ReportTemplate',
    'params' => [
      'version' => 3,
      'label' => 'Contribution Line Items',
      'description' => 'Contribution report user-selectable columns and filters for line items',
      'class_name' => 'CRM_Lineitemreport_Report_Form_LineItemContribution',
      'report_url' => 'lineitem/contribution',
      'component' => 'CiviContribute',
    ],
  ],
];
