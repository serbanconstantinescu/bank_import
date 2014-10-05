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
$page_security = 'SA_BANKACCOUNT';
$path_to_root = "../..";
include($path_to_root . "/includes/session.inc");

include_once($path_to_root . "/includes/date_functions.inc");
include_once($path_to_root . "/includes/ui.inc");

include_once($path_to_root . "/modules/bank_import/includes/banking.php");
include_once($path_to_root . "/modules/bank_import/includes/parsers.inc");

page(_($help_context = "Import Bank Statement"));


function importStatement($smt) {
	//search for smtId
	$sql = "SELECT id FROM ".TB_PREF."bi_statements WHERE bank=".db_escape($smt->bank)." AND statementId=".db_escape($smt->statementId);
	$res = db_query($sql, "unable to search for statement");
	$myrow = db_fetch($res);
	if (empty($myrow)) {
	    //insert
	    $sql = "INSERT INTO ".TB_PREF."bi_statements (bank, account, currency, startBalance, endBalance, smtDate, number, seq, statementId)" .
		" VALUES(".
		db_escape($smt->bank).", ".
		db_escape($smt->account).", ".
		db_escape($smt->currency).", ".
		db_escape($smt->startBalance).", ".
		db_escape($smt->endBalance).", ".
		db_escape($smt->timestamp).", ".
		db_escape($smt->number).", ".
		db_escape($smt->sequence).", ".
		db_escape($smt->statementId).")";

	    $res = db_query($sql, "could not insert statement");

	    $smt_id = db_insert_id();
	} else {
	    //update
	    $smt_id = $myrow['smtId'];
	    $sql = "UPDATE ".TB_PREF."bi_statements SET startBalance=".db_escape($smt->startBalance).", endBalance=".db_escape($smt->endBalance)." WHERE statementId=".db_escape($smt_id);
	    $res = db_query($sql, "could not insert statement");
	}

	foreach($smt->transactions as $t) {
	    $sql = "INSERT IGNORE INTO ".TB_PREF."bi_transactions(smt_id, valueTimestamp, entryTimestamp, account, accountName, transactionType, ".
	    "transactionCode, transactionCodeDesc, transactionDC, transactionAmount, transactionTitle) VALUES(" .
	    db_escape($smt_id).", ".
	    db_escape($t->valueTimestamp).", ".
	    db_escape($t->entryTimestamp).", ".
	    db_escape($t->account).",".
	    db_escape($t->accountName).", ".
	    db_escape($t->transactionType).", ".
	    db_escape($t->transactionCode).", ".
	    db_escape($t->transactionCodeDesc).", ".
	    db_escape($t->transactionDC).", ".
	    db_escape($t->transactionAmount).", ".
	    db_escape($t->transactionTitle).")";

	    $res = db_query($sql, "could not insert transaction");
	}
}


function doImport() {
    $parsers = getParsers();

    start_form(true);
    start_outer_table(TABLESTYLE2, "width=80%");

    table_section(1, "100%");
    table_section_title(_("Select file(s) and type"));

    label_row(_("Files"), "<input type='file' name='files[]' multiple />");
    label_row(_("Format:"), array_selector('parser', null, $parsers));

    submit_cells('upload', _("Upload"));
    table_section(2, "80%");
    table_section_title(_("Statement import status"));
    start_row();
    echo "<td width=100%><pre>\n";

    if (@$_POST['upload'] && ($_FILES['files']['error'][0] == 0)) {
	$smt_ok = 0;
	$trz_ok = 0;

	// initialize parser class
	$parserClass = $_POST['parser'] . '_parser';
	$parser = new $parserClass;

	echo "Processing using format `{$parsers[$_POST['parser']]}`\n";

    	foreach($_FILES['files']['name'] as $id=>$fname) {
    	    echo "Processing $fname...\n";

    	    $content = file_get_contents($_FILES['files']['tmp_name'][$id]);

    	    $statements = $parser->parse($content, false); // false for no debug, true for debug

	    foreach ($statements as $smt) {
		if ($smt->validate($debug = true)) {
		    $smt_ok ++;
		    $trz_cnt = count($smt->transactions);
		    $trz_ok += $trz_cnt;
		    echo "  valid statement found, $trz_cnt transactions\n";
		    importStatement($smt);
		    echo "  statement imported\n";
		} else {
		    echo "  invalid statement, ignored\n";
		}
	    }

    	    echo "======================================\n";
    	    echo "Total statements  : $smt_ok\n";
    	    echo "Total transactions: $trz_ok\n";
	}
    }

    echo "</pre></td>";
    end_row();
    end_outer_table();
    end_form(2);
}


doImport();

end_page();
?>