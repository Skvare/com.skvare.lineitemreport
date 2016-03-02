# com.skvare.lineitemreport
### CiviCRM Line Item Reporting Extension
The extension installs a line item report for the participant, membership, and contribution entities. The report will allow you to select standard columns for these entities, along with custom fields and any price set field that is applicable for the entity. Filters are also also available for each of the price set fields.

#### Installation
Clone the git repository or download and unzip/untar the extension respository to your civicrm extensions folder. Go to civicrm/admin/extensions on your website, and choose the install option next to the extension. If the Line Item Report extension doesn't appear in the list, hit the Refresh button to refresh the list.

#### Usage
Choose the "Create Reports from Template" option in the Reports menu, and choose the Contribution Line Items, Membership Line Items, or Participant Line Item report to create a report instance. Select the columns and filters to be displayed in the report. Before running the report, choose the event(s), membership type(s), or contribution type(s) to filter on. This is required to prevent an error from MySQL about the number of joins that can be made in a query. A notice appears as a reminder to make this selection before running the report. 




