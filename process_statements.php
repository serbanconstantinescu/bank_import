<?php

$page_security = 'SA_SALESTRANSVIEW';
$path_to_root = "../..";
include_once($path_to_root . "/includes/date_functions.inc");
include_once($path_to_root . "/includes/session.inc");

include_once($path_to_root . "/includes/ui/ui_input.inc");
include_once($path_to_root . "/includes/ui/ui_lists.inc");
include_once($path_to_root . "/includes/ui/ui_globals.inc");
include_once($path_to_root . "/includes/ui/ui_controls.inc");
include_once($path_to_root . "/includes/ui/items_cart.inc");
include_once($path_to_root . "/includes/data_checks.inc");


include_once($path_to_root . "/modules/bank_import/includes/includes.inc");
include_once($path_to_root . "/modules/bank_import/includes/pdata.inc");


$js = "";
if ($use_popup_windows)
    $js .= get_js_open_window(900, 500);
if ($use_date_picker)
    $js .= get_js_date_picker();

page(_($help_context = "Bank Transactions"), @$_GET['popup'], false, "", $js);

$optypes = array(
    'SP' => 'Supplier',
    'CU' => 'Customer',
    'QE' => 'Quick Entry',
    'MA' => 'Manual settlement',
);


//---------------------------------------------------------------------------------
// actions
unset($k, $v);
if (isset($_POST['ProcessTransaction'])) {
    list($k, $v) = each($_POST['ProcessTransaction']);
    if (isset($k) && isset($v) && isset($_POST['partnerType'][$k])) {
	//check params
	$error = 0;
	if (!isset($_POST["partnerId_$k"])) {
	    $Ajax->activate('doc_tbl');
	    display_error('missing partnerId');
	    $error = 1;
	}

	if (!$error) {
	    $tid = $k;
	    //time to gather data about transaction
	    //load $tid
	    $trz = get_transaction($tid);
	    //display_notification('<pre>'.print_r($trz,true).'</pre>');

	    //check bank account
	    $our_account = get_bank_account_by_number($trz['our_account']);
	    if (empty($our_account)) {
		$Ajax->activate('doc_tbl');
		display_error('the bank account <b>'.$trz['our_account'].'</b> is not defined in Bank Accounts');
		$error = 1;
	    }
	    //display_notification('<pre>'.print_r($ba,true).'</pre>');
	}
	if (!$error) {
	    //get charges
	    $chgs = array();
	    $_cids = array_filter(explode(',', $_POST['cids'][$tid]));
	    foreach($_cids as $cid) {
		$chgs[] = get_transaction($cid);
	    }
	    //display_notification("tid=$tid, cids=`".$_POST['cids'][$tid]."`");
	    //display_notification("cids_array=".print_r($_cids,true));

	    //now sum up
	    //now group data from tranzaction
	    $amount = $trz['transactionAmount'];
	    $charge = 0;
	    foreach($chgs as $t) {
		$charge += $t['transactionAmount'];
	    }

	    //display_notification("amount=$amount, charge=$charge");
	    //display_notification("partnerType=".$_POST['partnerType'][$k]);


	    switch(true) {
		case ($_POST['partnerType'][$k] == 'SP' && $trz['transactionDC'] == 'D'):
		    //supplier payment
		    //make sure we have a unique reference
		    do {
			$reference = $Refs->get_next(ST_SUPPAYMENT);
		    } while(!is_new_reference($reference, ST_SUPPAYMENT));

		    $payment_id = add_supp_payment(
		    	    $_POST["partnerId_$k"], sql2date($trz['valueTimestamp']),
		    	    $our_account['id'], user_numeric($trz['transactionAmount']), 0, $reference, $trz['transactionTitle'], 0, user_numeric($charge));
		    //display_notification("payment_id = $payment_id");
		    //update trans with payment_id details
		    if ($payment_id) {
			update_transactions($tid, $_cids, $status=1, $payment_id, ST_SUPPAYMENT);
			update_partner_data($partner_id = $_POST["partnerId_$k"], $partner_type = PT_SUPPLIER, $partner_detail_id = ANY_NUMERIC, $account = $trz['account']);
			display_notification('Supplier payment processed');
		    }
		break;
		case ($_POST['partnerType'][$k] == 'CU' && $trz['transactionDC'] == 'C'):
		    //function my_write_customer_payment($trans_no, $customer_id, $branch_id, $bank_account,
		    //	$date_, $ref, $amount, $discount, $memo_, $rate=0, $charge=0, $bank_amount=0)
		    //insert customer payment into database
		    do {
    			$reference = $Refs->get_next(ST_BANKDEPOSIT);
		    } while(!is_new_reference($reference, ST_BANKDEPOSIT));
		    $deposit_id = my_write_customer_payment(
			$trans_no = 0, $customer_id=$_POST["partnerId_$k"], $branch_id=$_POST["partnerDetailId_$k"], $bank_account=$our_account['id'],
			$date_ = sql2date($trz['valueTimestamp']), $reference, user_numeric($trz['transactionAmount']),
			$discount=0, $memo_ = $trz['transactionTitle'], $rate=0, user_numeric($charge), $bank_amount=0);
		    //display_notification("payment_id = $payment_id");
		    //update trans with payment_id details
		    if ($deposit_id) {
			update_transactions($tid, $_cids, $status=1, $deposit_id, ST_BANKDEPOSIT);
			update_partner_data($partner_id = $_POST["partnerId_$k"], $partner_type = PT_CUSTOMER, $partner_detail_id = $_POST["partnerDetailId_$k"], $account = $trz['account']);
			display_notification('Customer deposit processed');
		    }
		break;
		case ($_POST['partnerType'][$k] == 'QE'):
		    $cart_type = ($trz['transactionDC'] == 'D')?ST_BANKPAYMENT:ST_BANKDEPOSIT;
		    $cart = new items_cart($cart_type);
		    $cart->order_id = 0;
		    $cart->original_amount = $trz['transactionAmount'] + $charge;

		    do {
			$cart->reference = $Refs->get_next($cart->trans_type);
		    } while(!is_new_reference($cart->reference, $cart->trans_type));

		    $cart->tran_date = sql2date($trz['valueTimestamp']);
		    if (!is_date_in_fiscalyear($cart->tran_date)) {
			$cart->tran_date = end_fiscalyear();
		    }
		    //this loads the QE into cart!!!
		    //$rval = display_quick_entries($cart, $_POST['qeId'][$k], $_POST['amount'][$k], ($_POST['transDC'][$k]=='C') ? QE_DEPOSIT : QE_PAYMENT);
		    $rval = qe_to_cart($cart, $_POST["partnerId_$k"], $trz['transactionAmount'], ($trz['transactionDC']=='C') ? QE_DEPOSIT : QE_PAYMENT);
		    $total = $cart->gl_items_total();
		    if ($total != 0) {
			//need to add the charge to the cart
			$cart->add_gl_item(get_company_pref('bank_charge_act'), 0, 0, $charge, 'Charge/'.$trz['transactionTitle']);
			//process the transaction

			begin_transaction();

			$trans = write_bank_transaction(
			    $cart->trans_type, $cart->order_id, $our_account['id'],
			    $cart, sql2date($trz['valueTimestamp']),
			    PT_QUICKENTRY, $_POST["partnerId_$k"], 0,
			    $cart->reference, $trz['transactionTitle'], true, null);

			update_transactions($tid, $_cids, $status=1, $trans[1], $cart_type);
			commit_transaction();
			display_notification("Transaction processed via Quick Entry");

		    } else {
			display_notification("QE not loaded: rval=$rval, k=$k, total=$total");
			//display_notification("debug: <pre>".print_r($_POST, true).'</pre>');
		    }
		break;
		case ($_POST['partnerType'][$k] == 'MA'):
		    display_notification("tid=$tid, cids=`".$_POST['cids'][$tid]."`");
		    display_notification("cids_array=".print_r($_cids,true));
		    update_transactions($tid, $_cids, $status=1, 0, 0);
		    display_notification("Transaction was manually settled");
		break;
	    } // end of switch
	    $Ajax->activate('doc_tbl');
	} //end of if !error

    } // end of is set....
} //end of is isset(post[processTranzaction])


/*
// check whether a transaction is ignored
unset($k, $v);
list($k, $v) = each($_POST['IgnoreTrans']);
if (isset($k) && isset($v)) {
    updateTrans($_POST['trans_id'][$k], $_POST['charge_id'][$k], TR_MAN_SETTLED);
    $Ajax->activate('doc_tbl');
    display_notification('Manually processed');
}
*/

// search button pressed
if (get_post('RefreshInquiry')) {
	$Ajax->activate('doc_tbl');
}

//SC: check whether a customer has been changed, so that we can update branch as well
// as there a user can click on one submit button only, there is no need for multiple check
unset($k, $v);
if (isset($_POST['partnerId'])) {
    list($k, $v) = each($_POST['partnerId']);
    if (isset($k) && isset($v)) {
	$Ajax->activate('doc_tbl');
    }
}

//SC: 05.10.2012: whether post['partnerType'] exists, refresh
if (isset($_POST['partnerType'])) {
    $Ajax->activate('doc_tbl');
}


start_form();

div_start('doc_tbl');

if (1) {
    //------------------------------------------------------------------------------------------------
    // this is filter table
    start_table(TABLESTYLE_NOBORDER);
    start_row();
    if (!isset($_POST['statusFilter']))
	$_POST['statusFilter'] = 0;
    if (!isset($_POST['TransAfterDate']))
	$_POST['TransAfterDate'] = begin_month(Today());
    $_POST['TransAfterDate'] = '01/01/2012';

    if (!isset($_POST['TransToDate']))
	$_POST['TransToDate'] = end_month(Today());

    date_cells(_("From:"), 'TransAfterDate', '', null, -30);
    date_cells(_("To:"), 'TransToDate', '', null, 1);
    label_cells(_("Status:"), array_selector('statusFilter', $_POST['statusFilter'], array(0 => 'Unsettled', 1 => 'Settled', 255 => 'All')));

    submit_cells('RefreshInquiry', _("Search"),'',_('Refresh Inquiry'), 'default');
    end_row();
    end_table();

    //if (!@$_GET['popup'])
    //	end_form();


    //------------------------------------------------------------------------------------------------
    // this is data display table

    $sql = "
	SELECT t.*, s.account our_account, s.currency from ".TB_PREF."bi_transactions t
    	    LEFT JOIN ".TB_PREF."bi_statements as s ON t.smt_id = s.id
	WHERE
	    t.valueTimestamp >= ".db_escape(date2sql($_POST['TransAfterDate']))." AND
	    t.valueTimestamp <  ".db_escape(date2sql($_POST['TransToDate']));

    if ($_POST['statusFilter'] != 255) {
	$sql .= " AND t.status = ".db_escape($_POST['statusFilter']);
    }

    $sql.= " ORDER BY t.valueTimestamp ASC";
    $res=db_query($sql, 'unable to get transactions data');


    //load data
    $trzs = array();
    while($myrow = db_fetch($res)) {
	$trz_code = $myrow['transactionCode'];
	if (!isset($trzs[$trz_code])) {
	    $trzs[$trz_code] = array();
	}
	$trzs[$trz_code][] = $myrow;
    }

    start_table(TABLESTYLE, "width='100%'");
    table_header(array("Transaction Details", "Operation/Status"));
    
    foreach($trzs as $trz_code => $trz_data) {
	//try to match something, interpreting saved info if status=TR_SETTLED
	//$minfo = doMatching($myrow, $coyBankAccounts, $custBankAccounts, $suppBankAccounts);

	//now group data from tranzaction
	$tid = 0;
	$cids = array();

	//bring trans details
	$has_trz = 0;
	$amount = 0;
	$charge = 0;
	foreach($trz_data as $idx => $trz) {
	    if ($trz['transactionType'] != 'COM') {
		    $transactionDC = $trz['transactionDC'];
		    $our_account = $trz['our_account'];
		    $valueTimestamp = $trz['valueTimestamp'];
		    $bankAccount = $trz['account'];
		    $bankAccountName = $trz['accountName'];
		    $transactionTitle = $trz['transactionTitle'];
		    $currency = $trz['currency'];
		    $status = $trz['status'];
		    $tid = $trz['id'];
		    $has_trz = 1;
		    $amount = $trz['transactionAmount'];
		    $fa_trz_type = $trz['fa_trans_type'];
		    $fa_trz_no = $trz['fa_trans_no'];
	    }
	}
	//if does not have trz aka just charge, take info from charge
	if (!$has_trz) {
	    foreach($trz_data as $idx => $trz) {
		if ($trz['transactionType'] == 'COM') {
		    $transactionDC = $trz['transactionDC'];
		    $our_account = $trz['our_account'];
		    $valueTimestamp = $trz['valueTimestamp'];
		    $bankAccount = $trz['account'];
		    $bankAccountName = $trz['accountName'];
		    $transactionTitle = $trz['transactionTitle'];
		    $currency = $trz['currency'];
		    $status = $trz['status'];
		    $tid = $trz['id']; // tid is from charge
		    $amount += $trz['transactionAmount'];
		    $fa_trz_type = $trz['fa_trans_type'];
		    $fa_trz_no = $trz['fa_trans_no'];
		}
	    }
	} else {
	    //transaction plus charge => add charge ids into place and sum amounts
	    foreach($trz_data as $idx => $trz) {
		if ($trz['transactionType'] == 'COM') {
		    $cids[] = $trz['id'];
		    $charge += $trz['transactionAmount'];
		}
	    }
	}
	$cids = implode(',', $cids);

	//echo '<pre>'.print_r($trz_data, true).'</pre>';

	start_row();
	echo '<td width="50%">';
	
	start_table(TABLESTYLE2, "width='100%'");
	label_row("Trans Date:", $valueTimestamp, "width='25%' class='label'");
	label_row("Trans Type:", ($transactionDC =='C') ? "Credit" : "Debit");
	label_row("Our account:", $our_account . ' - '); // . $minfo['coyBankAccountName']);
	label_row("Other account:", $bankAccount . ' / '. $bankAccountName);
	label_row("Amount/Charge(s):", $amount.' / '.$charge." (".$currency.")");
	label_row("Trans Title:", $transactionTitle);
	end_table();

	echo "</td><td width='50%' valign='top'>";

	start_table(TABLESTYLE2, "width='100%'");

	//now display stuff: forms and information

	if ($status == 1) {
    	    // the transaction is settled, we can display full details
	    label_row("Status:", "<b>Transaction is settled!</b>", "width='25%' class='label'");
    	    switch ($trz['fa_trans_type']) {
		case ST_SUPPAYMENT:
			label_row("Operation:", "Payment");
			// get supplier info
			
			//label_row("Supplier:", $minfo['supplierName']);
			//label_row("From bank account:", $minfo['coyBankAccountName']);
		break;
		case ST_BANKDEPOSIT:
			label_row("Operation:", "Deposit");
			//get customer info from transaction details
			$fa_trans = get_customer_trans($fa_trz_no, $fa_trz_type);
			label_row("Customer/Branch:", get_customer_name($fa_trans['debtor_no']) . " / " . get_branch_name($fa_trans['branch_code']));
		break;
		case 0:
			label_row("Operation:", "Manual settlement");
		break;
		default:
			label_row("Status:", "other transaction type; no info yet");
		break;
	    }
	} else {	
	    //transaction not settled
	    // this is a new transaction, but not matched by routine so just display some forms

	    if (!isset($_POST['partnerType'][$tid])) {
		if ($transactionDC == 'C')
		    $_POST['partnerType'][$tid] = 'CU';
		else
		    $_POST['partnerType'][$tid] = 'SP';
	    }
	    label_row("Operation:", (($transactionDC=='C') ? "Deposit" : "Payment"), "width='25%' class='label'");
	    label_row("Partner:", array_selector("partnerType[$tid]", $_POST['partnerType'][$tid], $optypes, array('select_submit'=> true)));

	    if (!isset($_POST["partnerId_$tid"])) {
		$_POST["partnerId_$tid"] = '';
	    }

	    switch($_POST['partnerType'][$tid]) {
		//supplier payment
		case 'SP':
		    //propose supplier
		    if (empty($_POST["partnerId_$tid"])) {
			$match = search_partner_by_bank_account(PT_SUPPLIER, $bankAccount);
			if (!empty($match)) {
			    $_POST["partnerId_$tid"] = $match['partner_id'];
			}
		    }
		    label_row(_("Payment To:"), supplier_list("partnerId_$tid", null, false, false));
		    break;
		//customer deposit
		case 'CU':
			//propose customer
			if (empty($_POST["partnerId_$tid"])) {
			    $match = search_partner_by_bank_account(PT_CUSTOMER, $bankAccount);
			    if (!empty($match)) {
				$_POST["partnerId_$tid"] = $match['partner_id'];
				$_POST["partnerDetailId_$tid"] = $match['partner_detail_id'];
			    }
			}
			$cust_text = customer_list("partnerId_$tid", null, false, true);
			if (db_customer_has_branches($_POST["partnerId_$tid"])) {
			    $cust_text .= customer_branches_list($_POST["partnerId_$tid"], "partnerDetailId_$tid", null, false, true, true);
			} else {
			    hidden("partnerDetailId_$tid", ANY_NUMERIC);
			    $_POST["partnerDetailId_$tid"] = ANY_NUMERIC;
			}
			label_row(_("From Customer/Branch:"),  $cust_text);
			//label_row("debug", "customerid_tid=".$_POST["partnerId_$tid"]." branchid[tid]=".$_POST["partnerDetailId_$tid"]);

		break;
		// quick entry
		case 'QE':
			//label_row("Option:", "<b>Process via quick entry</b>");
			$qe_text = quick_entries_list("partnerId_$tid", null, (($transactionDC=='C') ? QE_DEPOSIT : QE_PAYMENT), true);
			$qe = get_quick_entry(get_post("partnerId_$tid"));
			$qe_text .= " " . $qe['base_desc'];
	    
			label_row("Quick Entry:", $qe_text);
		break;
		case 'MA':
		    hidden("partnerId_$tid", 'manual');
		break;
	    }
	    label_row("", submit("ProcessTransaction[$tid]",_("Process"),false, '', 'default'));

	    //other common info
	    hidden("cids[$tid]",$cids);
	}
	end_table();
	echo "</td>";
	end_row();
    }
    end_table();
}


div_end();
end_form();

end_page(@$_GET['popup'], false, false);
?>
