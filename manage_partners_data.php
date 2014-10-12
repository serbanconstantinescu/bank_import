<?php
/**********************************************************************
    Copyright (C) FrontAccounting, LLC.
	Released under the terms of the GNU General Public License, GPL, 
	as published by the Free Software Foundation, either version 3 
	of the License, or (at your option) any later version.
    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  
    See the License here <http://www.gnu.org/licenses/gpl-3.0.html>.
***********************************************************************/
$path_to_root = "../..";
include_once($path_to_root . "/includes/session.inc");
$page_security = 'SA_BANKACCOUNT';

include_once($path_to_root . "/gl/includes/gl_ui.inc");
include_once($path_to_root . "/modules/bank_import/includes/pdata.inc");


$js = '';
if ($use_popup_windows)
	$js .= get_js_open_window(800, 500);
if ($use_date_picker)
	$js .= get_js_date_picker();

page(_($help_context = "Manage Partners Bank Accounts"), false, false, '', $js);


$types = array (
    PT_CUSTOMER => _("Customer"),
    PT_SUPPLIER => _("Supplier"),
);


//----------------------------------------------------------------------------------------
if (list_updated('partner_detail_id')) {
    $br = get_branch(get_post('partner_detail_id'));
    $_POST['partner_id'] = $br['debtor_no'];
    $Ajax->activate('partner_id');
}

if (isset($_POST['_partner_type_update'])) {
    $_POST['partner_id'] = '';
    $_POST['data'] = '';
    $Ajax->activate('partner');
    set_focus('data');

}

//-----------------------------------------------------------------------------------------------
if (isset($_POST['process'])) {
    set_partner_data($_POST['partner_id'], $_POST['partner_type'], $_POST['partner_detail_id'], $_POST['data']);
    display_notification("Partner data updated!");
}


//-----------------------------------------------------------------------------------------------

start_form();

div_start('partner');
start_outer_table(TABLESTYLE2, "width=90%"); // outer table
table_section(1, "33%");

if (!isset($_POST['partner_id'])) {
    $_POST['partner_id'] = "";
}

if (!isset($_POST['partner_type'])) {
    $_POST['partner_type'] = PT_CUSTOMER;
    set_focus('data');
}

echo "<tr><td class='label'>" . _("Choose: ") . "</td><td>";
echo array_selector('partner_type', $_POST['partner_type'], $types, array( 'select_submit'=> true ) );
echo "</td></tr>\n";


switch ($_POST['partner_type']) {
    case PT_SUPPLIER :
    	supplier_list_row(_("Supplier:"), 'partner_id', null, false, true, false, true);
	$_POST['partner_detail_id'] = ANY_NUMERIC;
    	hidden('partner_detail_id');
    break;
    case PT_CUSTOMER :
    	customer_list_row(_("Customer:"), 'partner_id', null, false, true, false, true);

        if (db_customer_has_branches($_POST['partner_id'])) {
    	    customer_branches_list_row(_("Branch:"), $_POST['partner_id'], 
					'partner_detail_id', null, false, true, true, true);
        } else {
	    $_POST['partner_detail_id'] = ANY_NUMERIC;
    	    hidden('partner_detail_id');
        }
    break;
    default:
	echo "something else";
    break;
}


//get existing data
$data = get_partner_data($_POST['partner_id'], $_POST['partner_type'], $_POST['partner_detail_id']);
if (!empty($data))
    $_POST['data'] = $data['data'];
    

/*
$val = "p_id = ".$_POST['partner_id'] . "pd_id=".$_POST['partner_detail_id'] . " type=";
if ($_POST['partner_type'] == PT_CUSTOMER)
    $val .= "customer";
else
    $val .= "supplier";
*/

table_section(2, "66%");
textarea_row(_("IBAN(S):"), 'data', @$_POST['data'], 50, 3);

end_outer_table(1); // outer table
div_end();



submit_center_first('process', _("Update"), '', 'default');

end_form();

//------------------------------------------------------------------------------------------------

end_page();

?>
