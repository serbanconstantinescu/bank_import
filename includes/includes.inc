<?php

function get_transaction($tid) {
	$sql = "
	    SELECT t.*, s.account our_account FROM ".TB_PREF."bi_transactions t
    	    LEFT JOIN ".TB_PREF."bi_statements as s ON t.smt_id = s.id
	    WHERE t.id=".db_escape($tid);
	$result = db_query($sql, "could not get transaction with id $tid");	
	return db_fetch($result);
}

function update_transactions($tid, $cids, $status, $trans_no, $trans_type) {
    $cids[] = $tid;
    $cids = implode(',', $cids);

    $sql = "
	UPDATE ".TB_PREF."bi_transactions
	SET status=".db_escape($status).",
	    fa_trans_no=".db_escape($trans_no).",
	    fa_trans_type=".db_escape($trans_type)."
	    WHERE id in ($cids)";
//    display_notification($sql);
    db_query($sql, 'Could not update trans');
}

function get_bank_account_by_number($account_number) {
	$sql = "SELECT a.*
		FROM ".TB_PREF."bank_accounts a 
		WHERE a.bank_account_number = ".db_escape($account_number);
	$result = db_query($sql,"could not get bank account");
	return db_fetch($result);
}

function my_write_customer_payment($trans_no, $customer_id, $branch_id, $bank_account,
	$date_, $ref, $amount, $discount, $memo_, $rate=0, $charge=0, $bank_amount=0)
{
	global $Refs;

	begin_transaction();
	$args = func_get_args(); while (count($args) < 12) $args[] = 0;
	$args = (object)array_combine(array('trans_no', 'customer_id', 'branch_id', 'bank_account', 
		'date_', 'ref', 'amount', 'discount', 'memo_','rate','charge', 'bank_amount'), $args);
	//hook_db_prewrite($args, ST_CUSTPAYMENT);
	hook_db_prewrite($args, ST_BANKDEPOSIT);

	$company_record = get_company_prefs();

	if ($trans_no != 0) {
	  //delete_comments(ST_CUSTPAYMENT, $trans_no);
	  //void_bank_trans(ST_CUSTPAYMENT, $trans_no, true);
	  //void_gl_trans(ST_CUSTPAYMENT, $trans_no, true);
	  //void_cust_allocations(ST_CUSTPAYMENT, $trans_no, $date_);

	  delete_comments(ST_BANKDEPOSIT, $trans_no);
	  void_bank_trans(ST_BANKDEPOSIT, $trans_no, true);
	  void_gl_trans(ST_BANKDEPOSIT, $trans_no, true);
	  void_cust_allocations(ST_BANKDEPOSIT, $trans_no, $date_);
	}

	$bank = get_bank_account($bank_account);

	if (!$bank_amount) 	// backward compatibility workaround
	{
		if(!$rate)
			$rate = get_exchange_rate_from_to(get_customer_currency($customer_id),
				$bank['bank_curr_code'], $date_ );

		$bank_amount = $amount/$rate;
	}

	// do not use $rate here: global rate stored in exrate table is always used
	//$payment_no = write_customer_trans(ST_CUSTPAYMENT, $trans_no, $customer_id, $branch_id, 
	//	$date_, $ref, $amount, $discount);

	$payment_no = write_customer_trans(ST_BANKDEPOSIT, $trans_no, $customer_id, $branch_id, 
		$date_, $ref, $amount, $discount);

	$bank_gl_account = get_bank_gl_account($bank_account);

	$total = 0;

	/* Bank account entry first */
	//$total += add_gl_trans(ST_CUSTPAYMENT, $payment_no, $date_,
	//	$bank_gl_account, 0, 0, '', ($bank_amount - $charge),  $bank['bank_curr_code'], PT_CUSTOMER, $customer_id);

	$total += add_gl_trans(ST_BANKDEPOSIT, $payment_no, $date_,
		$bank_gl_account, 0, 0, '', ($bank_amount - $charge),  $bank['bank_curr_code'], PT_CUSTOMER, $customer_id);

	if ($branch_id != ANY_NUMERIC) {

		$branch_data = get_branch_accounts($branch_id);

		$debtors_account = $branch_data["receivables_account"];
		$discount_account = $branch_data["payment_discount_account"];

	} else {
		$debtors_account = $company_record["debtors_act"];
		$discount_account = $company_record["default_prompt_payment_act"];
	}

	if (($discount + $amount) != 0)	{
	/* Now Credit Debtors account with receipts + discounts */
	//$total += add_gl_trans_customer(ST_CUSTPAYMENT, $payment_no, $date_,
	//	$debtors_account, 0, 0, -($discount + $amount), $customer_id,
	//	"Cannot insert a GL transaction for the debtors account credit");
	$total += add_gl_trans_customer(ST_BANKDEPOSIT, $payment_no, $date_,
		$debtors_account, 0, 0, -($discount + $amount), $customer_id,
		"Cannot insert a GL transaction for the debtors account credit");
	}

	if ($discount != 0)	{
		/* Now Debit discount account with discounts allowed*/
		//$total += add_gl_trans_customer(ST_CUSTPAYMENT, $payment_no, $date_,
		//	$discount_account, 0, 0, $discount, $customer_id,
		//	"Cannot insert a GL transaction for the payment discount debit");

		$total += add_gl_trans_customer(ST_BANKDEPOSIT, $payment_no, $date_,
			$discount_account, 0, 0, $discount, $customer_id,
			"Cannot insert a GL transaction for the payment discount debit");
	}

	if ($charge != 0)	{
		/* Now Debit bank charge account with charges */
		$charge_act = get_company_pref('bank_charge_act');
		//$total += add_gl_trans(ST_CUSTPAYMENT, $payment_no, $date_,	$charge_act, 0, 0, '', 
		//	$charge, $bank['bank_curr_code'], PT_CUSTOMER,  $customer_id);
		$total += add_gl_trans(ST_BANKDEPOSIT, $payment_no, $date_,	$charge_act, 0, 0, '', 
			$charge, $bank['bank_curr_code'], PT_CUSTOMER,  $customer_id);
	}


	/*Post a balance post if $total != 0 due to variance in AR and bank posted values*/
	if ($total != 0)
	{
		$variance_act = get_company_pref('exchange_diff_act');
		//add_gl_trans(ST_CUSTPAYMENT, $payment_no, $date_,	$variance_act, 0, 0, '',
		//	-$total, null, PT_CUSTOMER,  $customer_id);
		add_gl_trans(ST_BANKDEPOSIT, $payment_no, $date_,	$variance_act, 0, 0, '',
			-$total, null, PT_CUSTOMER,  $customer_id);
	}

	/*now enter the bank_trans entry */
	//add_bank_trans(ST_CUSTPAYMENT, $payment_no, $bank_account, $ref,
	//	$date_, $bank_amount - $charge, PT_CUSTOMER, $customer_id);

	add_bank_trans(ST_BANKDEPOSIT, $payment_no, $bank_account, $ref,
		$date_, $bank_amount - $charge, PT_CUSTOMER, $customer_id);

	//add_comments(ST_CUSTPAYMENT, $payment_no, $date_, $memo_);
	add_comments(ST_BANKDEPOSIT, $payment_no, $date_, $memo_);

	//SC: that would be the change!!!
	//$Refs->save(ST_CUSTPAYMENT, $payment_no, $ref);
	$Refs->save(ST_BANKDEPOSIT, $payment_no, $ref);

	$args->trans_no = $payment_no;
	//hook_db_postwrite($args, ST_CUSTPAYMENT);
	hook_db_postwrite($args, ST_BANKDEPOSIT);
	commit_transaction();

	return $payment_no;
}

//qe_to_cart
function qe_to_cart(&$cart, $id, $base, $type, $descr='') {
    $bank_amount = 0;

    if (!isset($id) || $id == null || $id == "") {
	display_error("No Quick Entries are defined.");
    } else {
	if ($type == QE_DEPOSIT)
	    $base = -$base;
	if ($type != QE_SUPPINV)	// only one quick entry on journal/bank transaction
	    $cart->clear_items();
	$qe = get_quick_entry($id);

	if ($qe['bal_type'] == 1) {
	    if ($qe['base_amount'] == 1.0) // monthly
		$begin = begin_month($cart->tran_date);
	    else {
		if (is_account_balancesheet($qe['base_desc'])) // total
		    $begin = "";
		else
		    $begin = begin_fiscalyear(); // from fiscalyear begin
	    }
	    $base = get_gl_trans_from_to($begin, $cart->tran_date, $qe['base_desc']);
	}

	if ($descr != '') $qe['description'] .= ': '.$descr;
	$result = get_quick_entry_lines($id);
	if (db_num_rows($result) == 0) {
	    display_error( _("No Quick Entry lines are defined."));
	    return 0;
	}

	$totrate = 0;
	while ($row = db_fetch($result)) {
	    $qe_lines[] = $row;

	    switch (strtolower($row['action'])) {
		case "t": // post taxes calculated on base amount
		case "t+": // ditto & increase base amount
		case "t-": // ditto & reduce base amount
		    if (substr($row['action'],0,1) != 'T') 
			$totrate += get_tax_type_default_rate($row['dest_id']);
	    }
	}

	$first = true;
	$taxbase = 0;
	foreach($qe_lines as $qe_line) {
	    switch (strtolower($qe_line['action'])) {
		case "=": // post current base amount to GL account
		    $part = $base;
		    break;
		case "a": // post amount to GL account and reduce base
		    $part = $qe_line['amount'];
		    break;
		case "a+": // post amount to GL account and increase base
		    $part = $qe_line['amount']; $base += $part;
		    break;
		case "a-": // post amount to GL account and reduce base
		    $part = $qe_line['amount']; $base -= $part;
		    break;
		case "%":	// store acc*amount% to GL account
		    $part = round2($base * $qe_line['amount'] / 100, user_price_dec());
		    break;
		case "%+":	// ditto & increase base amount
		    $part = round2($base * $qe_line['amount'] / 100, user_price_dec());
		    $base += $part;
		    break;
		case "%-":	// ditto & reduce base amount
		    $part = round2($base * $qe_line['amount'] / 100, user_price_dec());
		    $base -= $part;
		    break;
		case "t": // post taxes calculated on base amount
		case "t+": // ditto & increase base amount
		case "t-": // ditto & reduce base amount
		    if ($first) {
			$taxbase = $base/($totrate+100);
			$first = false;
		    }

		    if (substr($qe_line['action'],0,1) != 'T') 
			$part = $taxbase;
		    else
			$part = $base/100;
		    $item_tax = get_tax_type($qe_line['dest_id']);
		    //if ($type == QE_SUPPINV && substr($qe_line['action'],0,1) != 'T')
		    if ($type == QE_SUPPINV) {
			$taxgroup = $cart->tax_group_id;
			$rates = 0;
			$res = get_tax_group_rates($cart->tax_group_id);
			while ($row = db_fetch($res))
			    $rates += $row['rate'];
			if ($rates == 0)
			    continue 2;
		    }
		    $tax = round2($part * $item_tax['rate'],  user_price_dec());
		    if ($tax==0)
			continue 2;
		    $gl_code = ($type == QE_DEPOSIT || ($type == QE_JOURNAL && $base < 0)) 
			    ? $item_tax['sales_gl_code'] : $item_tax['purchasing_gl_code'];
		    if (!is_tax_gl_unique($gl_code)) {
	   		display_error("Cannot post to GL account used by more than one tax type.");
			break 2;
		    }
		    if ($type != QE_SUPPINV)
			$cart->add_gl_item($gl_code, 
				$qe_line['dimension_id'], $qe_line['dimension2_id'], 
				$tax, $qe['description']);
		    else {
			$acc_name = get_gl_account_name($gl_code);
			$cart->add_gl_codes_to_trans($gl_code, 
				$acc_name, $qe_line['dimension_id'], 
				$qe_line['dimension2_id'], $tax, $qe['description']);
		    }
		    if (strpos($qe_line['action'], '+'))
			$base += $tax;
		    elseif (strpos($qe_line['action'], '-'))
			$base -= $tax;
		    continue 2;
	    }
	    if ($type != QE_SUPPINV)
		$cart->add_gl_item($qe_line['dest_id'], $qe_line['dimension_id'],
		    $qe_line['dimension2_id'], $part, $qe['description']);
	    else {
		$acc_name = get_gl_account_name($qe_line['dest_id']);
		$cart->add_gl_codes_to_trans($qe_line['dest_id'], 
		    $acc_name, $qe_line['dimension_id'], 
		    $qe_line['dimension2_id'], $part, $qe['description']);
	    }
	}
    }
    return $bank_amount;
}

