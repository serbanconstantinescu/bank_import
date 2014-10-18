<?php

//we need to interpret the file and generate a new statement for each day of transactions

class ro_ing_csv_parser extends parser {

    function parse($content, $static_data = array(), $debug = true) {
	//keep statements in an array, hashed by statement-id
	//statement id is the statement date: yyyy-mm-dd-<number>-<seq>
	// as each line is processed, adjust statement data and add tranzactions
	$smts = array();

	$months = array(
	    'ianuarie' => '01', 'februarie' => '02', 'martie' => '03', 'aprilie' => '04', 'mai' => '05', 'iunie' => '06',
	    'iulie' => '07', 'august' => '08', 'septembrie' => '09', 'octombrie' => '10', 'noiembrie' => '11', 'decembrie' => '12',
	);


	//split content by \n
	$lines = explode("\n", $content);

	//first line is header so ignore it
	array_shift($lines);


	//current transaction
	$trz = null;
	$trz_line = '';
	//last TRF transaction
	$last_trz = null;
	
	//parse lines
	foreach($lines as $line) {
	    if (strlen($line) == 0)
		continue;

	    //echo "----------------------------------------------------\n";
    	    //echo "debug: line: $line\n";

	    $f = str_getcsv($line);

	    if (!empty($f[0]) && (strpos($f[0],'Sold initial') === 0)) {
		$si = $f[1];
		//echo "debug: sold initial. continue\n";
		continue;
	    }

	    if (!empty($f[0]) && (strpos($f[0],'Sold final') === 0)) {
		$sf = $f[1];
		//echo "debug: sold final. continue\n";
		continue;
	    }

	    //if $f[0] exists and has some date format => it is a $sid
	    if(!empty($f[0])) {
		//need to parse timestamp
		if (preg_match('/(\d+) ([a-z]+) (\d+)/', $f[0], $matches) && isset($matches[1])) {
		    $y = trim($matches[3]);
		    $m = $months[trim($matches[2])];
		    $d = trim($matches[1]);
		    $sid = "{$y}-{$m}-{$d}";
		} else {
		    $sid = "1970-01-01";
		}
		//echo "debug: found a timestamp: $sid\n";
	    }

	    //if smtid exists in results, add to this statement else create new statement
	    if (empty($smts[$sid])) {
		$smts[$sid] = new statement;
		$smts[$sid]->bank = 'ING';
		//get additional info from static_data
		$smts[$sid]->account = $static_data['account'];
		$smts[$sid]->currency = $static_data['currency'];
		$smts[$sid]->timestamp = $sid;
		

		$smts[$sid]->startBalance = '0';
		$smts[$sid]->endBalance = '0';
		$smts[$sid]->number = '00000';
		$smts[$sid]->sequence = '0';
		$smts[$sid]->statementId = "{$sid}-{$smts[$sid]->number}-{$smts[$sid]->sequence}";
		//echo "debug: adding a statement with sid=$sid\n";
	    } else {
		//echo "debug: statement exists for sid=$sid\n";
	    }


	    //cleanup numeric fields
	    foreach(array(2,3) as $i) {
		$f[$i] = str_replace('.', '', @$f[$i]);
		$f[$i] = str_replace(',', '.', @$f[$i]);
	    }


	    //state machine
	    // in transaction && new transaction indicator => close transaction
	    if ($trz && !empty($f[0])) {
		if ($debug) {
		    echo "debug: closing transaction {$trz->valueTimestamp}\n";
		    echo "debug: trz_line=$trz_line\n";
		    $trz->dump();
		}
		$smts[$trz->valueTimestamp]->addTransaction($trz);

		if ($trz->transactionType != 'COM')
		    $last_trz = $trz;
		if ($trz->valueTimestamp != $sid)
		    $last_trz = null;

		$trz = null;
		$trz_line = '';
	    }
	    
	    // not in transaction && new transaction indicator => open transaction and parse line 1
	    if (!$trz && !empty($f[0])) {
		if ($debug) echo "debug: adding new transaction....\n";
		$trz = new transaction;

		//transactionDC & amount
		if (!empty($f[2])) {
		    $trz->transactionDC = 'D';
		    $trz->transactionAmount = $f[2];
		} elseif (!empty($f[3])) {
		    $trz->transactionDC = 'C';
		    $trz->transactionAmount = $f[3];
		}

		//add timestamp
		$trz->valueTimestamp = $trz->entryTimestamp = $sid;

		//transaction type
		switch(trim(strtolower($f[1]))) {
		    case 'incasare':
		    case "transfer home'bank":
		    case 'retragere numerar':
			$trz->transactionType = 'TRF';
			break;
		    case 'comision pe operatiune':
			$trz->transactionType = 'COM';
			$trz->transactionTitle1 = $f[1];
			if ($last_trz)
			    $trz->transactionCode = $last_trz->transactionCode;
			break;
		    case 'acoperire sold negativ neautorizat':
			$trz->transactionType = 'FEX';
			$trz->transactionTitle1 = $f[1];
			$trz->transactionCode = $trz->valueTimestamp . '-' . $trz->transactionAmount;
		    break;
		    default:
			$trz->transactionType = 'XXX';
			break;
		}


		
		//debug
		$trz_line = $line;
		//end of loop
		continue;
	    }

	    // in transaction && detail indicator => parse details
	    if ($trz && empty($f[0])) {
		//echo "debug: add details....\n";
		//parse aditional line data
		switch(true) {
		    case (preg_match('/contul\:(.+)/i', $f[1], $matches) && isset($matches[1])):
			$trz->account = $matches[1];
		    break;
		    case (preg_match('/beneficiar\:(.+)/i', $f[1], $matches) && isset($matches[1])):
			$trz->accountName1 = $matches[1];
		    break;
		    case (preg_match('/ordonator\:(.+)/i', $f[1], $matches) && isset($matches[1])):
			$trz->accountName1 = $matches[1];
		    break;
		    case (preg_match('/referinta\:(.+)/i', $f[1], $matches) && isset($matches[1])):
			$trz->transactionCode = $matches[1];
		    break;
		    case (preg_match('/detalii\:(.+)/i', $f[1], $matches) && isset($matches[1])):
			$trz->transactionTitle1 .= '//'.$matches[1];
		    break;
		    default:
			$trz->transactionTitle1 .= '//'.$f[1];
		    break;
		}

		//debug
		$trz_line .= '//'.$line;
	    }
	}
	//parsing ended, cleanup
	if ($trz)
	    $smts[$sid]->addTransaction($trz);

	//time to return
	return $smts;
    }

}



