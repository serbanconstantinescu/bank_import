<?php

//we need to interpret the file and generate a new statement for each day of transactions

class ro_bcr_csv_parser extends parser {

    function parse($content, $static_data = array(), $debug = true) {
	//keep statements in an array, hashed by statement-id
	//statement id is the statement date: yyyy-mm-dd-<number>-<seq>
	// as each line is processed, adjust statement data and add tranzactions
	$smts = array();


	//split content by \n
	$lines = explode("\n", $content);
	//first line is header so ignore it
	array_shift($lines);

	//parse lines
	foreach($lines as $line) {
	    if (strlen($line) == 0)
		continue;

	    $f = str_getcsv($line);

	    if(empty($f[0]))
		continue;

	    //cleanup numeric fields
	    foreach(array(9,14,15,16,17,18,19,20,21) as $i) {
		$f[$i] = str_replace(',', '', $f[$i]);
	    }

	    //get field #10 and generate statement id in the form 
	    $y = substr($f[10], 6, 4);
	    $m = substr($f[10], 3, 2);
	    $d = substr($f[10], 0, 2);
	    $f[10] = "{$y}-{$m}-{$d}";


	    $sid = $f[10];
	    
	    //if smtid exists in results, add to this statement else create new statement
	    if (empty($smts[$sid])) {
		$smts[$sid] = new statement;
		$smts[$sid]->bank = 'BCR';
		$smts[$sid]->account = trim($f[6]);
		$smts[$sid]->currency = trim($f[4]);
		$smts[$sid]->timestamp = $f[10];
		$smts[$sid]->startBalance = '';
		$smts[$sid]->endBalance = '';
		$smts[$sid]->number = '00000';
		$smts[$sid]->sequence = '0';
		$smts[$sid]->statementId = "{$sid}-{$smts[$sid]->number}-{$smts[$sid]->sequence}";
/*
		echo "-----------------------------------------------------------------------------------------\n";
		printf("%-10s %10s  %10s  %10s  %10s  %10s  %10s  %s\n",
		    "smtid", "sold-in", "debit", "credit", "t-debit", "t-credit", "sold-fin", "trz-code");
*/
	    }
	    
	    //now use the statement
	    //update start balance
	    if ($f[9] != '0.00') {
		$smts[$sid]->startBalance = $f[9];
	    }
	    //update endBalance 
	    if ($f[18] != '0.00') {
		$smts[$sid]->endBalance = $f[18];
	    }
	    //add transaction data
	    $trz = new transaction;

	    //parse transactionCode
	    if (preg_match('/(\d+) (.*)/', $f[13], $matches) && isset($matches[1])) {
		$trz->transactionCode = $matches[1];
		$trz->transactionCodeDesc = $matches[2]; //fields[13];
	    }

	    //add timestamp
	    $trz->valueTimestamp = $trz->entryTimestamp = $sid;


	    //parse transaction data
	    switch(true) {
		case (preg_match('/(Referinta [0-9]{6}S[0-9]{9})/', $f[12], $matches) && isset($matches[1])):
		    $trz->transactionType = 'TRF';

		    $d_acct = $d_acct_name = $c_acct = $c_acct_name = '';
		    //now parse both accounts
		    if (preg_match('/-Platitor\:(.+);(.+);/U', $f[12], $m) && isset($m[1]) && isset($m[2])) {
			$d_acct = trim($m[2]);
			$d_acct_name = trim($m[1]);
		    }

		    if (preg_match('/-Beneficiar\:(.+);(.+)[;-]/U', $f[12], $m) && isset($m[1]) && isset($m[2])) {
			$c_acct = trim($m[2]);
			$c_acct_name = trim($m[1]);
		    }

		    if (empty($c_acct) || empty($d_acct)) {
			$trz->transactionType = 'XXX';
			break;
		    }

		    // if i am the debtor then it is a debit operation
		    if ($d_acct == $smts[$sid]->account) {
			$trz->transactionDC = 'D';
			$trz->account = $c_acct;
			$trz->accountName1 = $c_acct_name;
			$trz->transactionAmount = $f[14];
		    } else {
			$trz->transactionDC = 'C';
			$trz->account = $d_acct;
			$trz->accountName1 = $d_acct_name;
			$trz->transactionAmount = $f[15];
		    }
		    break;
		case (preg_match('/(Tranzactie comerciant)/', $f[12], $matches) && isset($matches[1])):
		    $trz->transactionType = 'TRF';
		    $trz->transactionAmount = $f[14];
		    $trz->transactionDC = 'D';
		    break;
		case (preg_match('/(Retragere numerar)/', $f[12], $matches) && isset($matches[1])):
		    $trz->transactionType = 'TRF';
		    $trz->transactionAmount = $f[14];
		    $trz->transactionDC = 'D';
		    break;
		case (preg_match('/( - com [CD]R)/', $f[12], $matches) && isset($matches[1])):
		case (preg_match('/(Comision utilizare ATM)/', $f[12], $matches) && isset($matches[1])):
		case (preg_match('/(Comision de administrare)/', $f[12], $matches) && isset($matches[1])):
		    $trz->transactionType = 'COM';
		    $trz->transactionAmount = $f[14];
		    $trz->transactionDC = 'D';
		    break;

		case (preg_match('/(Returnare facilitati)/', $f[12], $matches) && isset($matches[1])):
		    $trz->transactionType = 'TRF';
		    $trz->transactionDC = 'C';
		    $trz->transactionAmount = $f[14];
		    if ($trz->transactionAmount < 0)
			$trz->transactionAmount = -$trz->transactionAmount;
		    break;
		default:
		    $trz->transactionType = 'XXX';
		    break;
	    }
	    $trz->transactionTitle1 = $f[12];

/*
	    printf("%-10s %10s  %10s  %10s  %10s  %10s  %10s  %s\n",
		$sid,
		$f[9], $f[14],
		$f[15], $f[16],
		$f[17], $f[18],
		$trz->transactionType . '//' . $trz->transactionCode
	    );
	    if ($trz->transactionType == 'XXX')
		echo $f[12]."\n";
*/

	    //add transaction to statement
	    $smts[$sid]->addTransaction($trz);

	    //for debug purposes
	    if ($debug) { $smts[$sid]->dump(); }

	}
	//time to return
	return $smts;
    }

}
