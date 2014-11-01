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

function import_statements() {
    start_table(TABLESTYLE);
    start_row();
    echo "<td width=100%><pre>\n";


    echo '<pre>';
    $statements = unserialize($_SESSION['statements']);
    foreach($statements as $id => $smt) {
	echo "importing statement {$smt->statementId} ...";
	echo importStatement($smt);
	echo "\n";
    }
    echo '</pre>';



    echo "</pre></td>";

    end_row();
    start_row();
    echo '<td>';
	submit_center_first('goback', 'Go back');
    echo '</td>';
    end_row();
    
    end_table(1);
    hidden('parser', $_POST['parser']);
}


function importStatement($smt) {
	$message = '';
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
	    $message .= "new, imported";
	} else {
	    //update
	    $smt_id = $myrow['smtId'];
	    $sql = "UPDATE ".TB_PREF."bi_statements SET startBalance=".db_escape($smt->startBalance).", endBalance=".db_escape($smt->endBalance)." WHERE statementId=".db_escape($smt_id);
	    $res = db_query($sql, "could not insert statement");
	    $message .= "existing, updated";
	}

	foreach($smt->transactions as $id => $t) {
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
	    
	    //$t_id = db_insert_id();
	    //$message .= "    processed transaction #$id, $t_id\n";
	}
	$message .= ' ' . count($smt->transactions) . ' transactions';
	return $message;
}


function do_upload_form() {
    $parsers = array();
    $_parsers = getParsers();
    foreach($_parsers as $pid => $pdata) {
	$parsers[$pid] = $pdata['name'];
    }


    div_start('doc_tbl');
    start_table(TABLESTYLE);
    $th = array(_("Select File(s) and type"), '');
    table_header($th);

    label_row(_("Format:"), array_selector('parser', null, $parsers, array('select_submit' => true)));
    foreach($_parsers[$_POST['parser']]['select'] as $param => $label) {
	switch($param) {
	    case 'bank_account':
		bank_accounts_list_row($label, 'bank_account', $selected_id=null, $submit_on_change=false);
	    break;

	}
    }
    label_row(_("Files"), "<input type='file' name='files[]' multiple />");


    start_row();
    label_cell('Upload', "class='label'");
    submit_cells('upload', _("Upload"));
    end_row();

    end_table(1);
    div_end();
}


function parse_uploaded_files() {
    start_table(TABLESTYLE);
    start_row();
    

    echo "<td width=100%><pre>\n";

    // initialize parser class
    $parserClass = $_POST['parser'] . '_parser';
    $parser = new $parserClass;

    //prepare static data for parser
    $static_data = array();
    $_parsers = getParsers();
    foreach($_parsers[$_POST['parser']]['select'] as $param => $label) {
	switch($param) {
	    case 'bank_account':
		//get bank account data
		$bank_account = get_bank_account($_POST['bank_account']);
		$static_data['account'] = $bank_account['bank_account_number'];
		$static_data['currency'] = $bank_account['bank_curr_code'];
	    break;
	}
    }

    $smt_ok = 0;
    $trz_ok = 0;
    $smt_err = 0;
    $trz_err = 0;

    foreach($_FILES['files']['name'] as $id=>$fname) {
    	echo "Processing file `$fname` with format `{$_parsers[$_POST['parser']]['name']}`...\n";

    	$content = file_get_contents($_FILES['files']['tmp_name'][$id]);
    	$statements = $parser->parse($content, $static_data, $debug=false); // false for no debug, true for debug

	foreach ($statements as $smt) {
	    echo "statement: {$smt->statementId}:";
	    if ($smt->validate($debug = false)) {
		    $smt_ok ++;
		    $trz_cnt = count($smt->transactions);
		    $trz_ok += $trz_cnt;
		    echo " is valid, $trz_cnt transactions\n";
	    } else {
		    echo " is invalid!!!!!!!!!\n";
		    $smt->validate($debug=true);
		    $smt_err ++;
	    }
	}

    	echo "======================================\n";
    	echo "Valid statements   : $smt_ok\n";
    	echo "Invalid statements : $smt_err\n";
    	echo "Total transactions : $trz_ok\n";
    }
    echo "</pre></td>";

    end_row();
    start_row();
    echo '<td>';
	submit_center_first('goback', 'Go back');
	if ($smt_err == 0)
	    submit_center_last('import', 'Import');

    echo '</td>';
    end_row();
    
    end_table(1);
    hidden('parser', $_POST['parser']);
    if ($smt_err == 0) {
	$_SESSION['statements'] = serialize($statements);
    }
}







// select changed
if (get_post('_parser_update')) {
	$Ajax->activate('doc_tbl');
}

start_form(true);

if (empty($_POST['upload']) && empty($_POST['import'])) {
    do_upload_form();
}


//if upload is hit, parse the files and store result in session
if (@$_POST['upload'] && ($_FILES['files']['error'][0] == 0)) {
    parse_uploaded_files();
}

//if import is hit, perform the import
if (@$_POST['import']) {
    import_statements();
}


end_form(2);

end_page();
?>