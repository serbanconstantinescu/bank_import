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
$page_security = 'SA_SALESTRANSVIEW';
$path_to_root = "../..";
include_once($path_to_root . "/includes/date_functions.inc");
include_once($path_to_root . "/includes/session.inc");

include_once($path_to_root . "/includes/ui/ui_input.inc");
include_once($path_to_root . "/includes/ui/ui_lists.inc");
include_once($path_to_root . "/includes/ui/ui_globals.inc");

//include_once($path_to_root . "/modules/bank_import/includes/includes.php");
include_once($path_to_root . "/includes/data_checks.inc");


$js = "";
if ($use_popup_windows)
    $js .= get_js_open_window(900, 500);
if ($use_date_picker)
    $js .= get_js_date_picker();
page(_($help_context = "View Bank Statements"), @$_GET['popup'], false, "", $js);

//--------------------------------------------------------------------

// search button pressed
if(get_post('RefreshInquiry')) {
	$Ajax->activate('doc_tbl');
}


start_form();

//------------------------------------------------------------------------------------------------
// this is filter table
start_table(TABLESTYLE_NOBORDER);
start_row();
if (!isset($_POST['TransAfterDate']))
	$_POST['TransAfterDate'] = begin_month(Today());

if (!isset($_POST['TransToDate']))
	$_POST['TransToDate'] = end_month(Today());

date_cells(_("From:"), 'TransAfterDate', '', null, -30);
date_cells(_("To:"), 'TransToDate', '', null, 1);

submit_cells('RefreshInquiry', _("Search"),'',_('Refresh Inquiry'), 'default');
end_row();
end_table();


//------------------------------------------------------------------------------------------------
// this is data display table
$sql = " SELECT bank, account, currency, startBalance, endBalance, smtDate, number, seq, statementId
	FROM
	".TB_PREF."bi_statements WHERE smtDate >= ".db_escape(date2sql($_POST['TransAfterDate']))." AND smtDate <= ".
	db_escape(date2sql($_POST['TransToDate']))." ORDER BY smtDate ASC";

$res=db_query($sql, 'unable to get transactions data');

div_start('doc_tbl');
start_table(TABLESTYLE, "width='100%'");
table_header(array("Bank", "Statement#", "Date", "Account(Currency)", "Start Balance", "End Balance", "Delta"));
while($myrow = db_fetch($res)) {
    start_row();
    echo "<td>". $myrow['bank'] . "</td>";
    echo "<td>" . $myrow['statementId']."</td>";
    echo "<td>" . $myrow['smtDate'] . "</td>";
    echo "<td>" . $myrow['account']. '(' . $myrow['currency'] . ')' . "</td>";
    amount_cell($myrow['startBalance']);
    amount_cell($myrow['endBalance']);
    amount_cell($myrow['endBalance'] - $myrow['startBalance']);
    
    end_row();
}
end_table();
div_end();

end_form();

end_page(@$_GET['popup'], false, false);
?>
