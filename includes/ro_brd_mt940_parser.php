<?php

class ro_brd_mt940_parser extends mt940_parser {
    // brd uses :OS: to asend some info
    var $statementSplit = '/^(:20:.*?)(?=^:20:|:OS:|\Z)/sm';

    //brd puts sf 20, 31, 32 inline somewhere
    var $sf_20 = '/\+20([^+\n]+)/';
    var $sf_31 = '/\+31([^+\n]+)/';
    var $sf_32 = '/\+32([^+\n]+)/';

    //brd adds at the end of account a /
    var $sf_33 = '/\+33(.+)\//';

    function __construct() {
	parent::__construct();
	$this->statementMap['bank'] = array('value'=>'BRD');
    }


    function postProcessTransaction(&$trz) {
	//replace , with .
	$trz->transactionAmount = str_replace(',', '.', $trz->transactionAmount);

	$y = substr($trz->valueTimestamp, 0, 2);
	$m = substr($trz->valueTimestamp, 2, 2);
	$d = substr($trz->valueTimestamp, 4, 2);
	$y += 2000;
	$trz->valueTimestamp = $y . '-' . $m . '-' . $d;

	$m = substr($trz->entryTimestamp, 0, 2);
	$d = substr($trz->entryTimestamp, 2, 2);
	$trz->entryTimestamp = $y . '-' . $m . '-' . $d;
    }

    function postProcessStatement(&$smt) {
	//replace , with .
	$smt->startBalance = str_replace(',', '.', $smt->startBalance);
	$smt->endBalance = str_replace(',', '.', $smt->endBalance);

	//change statement date
	$y = substr($smt->timestamp, 0, 2);
	$m = substr($smt->timestamp, 2, 2);
	$d = substr($smt->timestamp, 4, 2);
	$y += 2000;
	$smt->timestamp = $y . '-' . $m . '-' . $d;

	//add statement id field as $y-$number-$sequence
	$smt->statementId = "{$smt->timestamp}-{$smt->number}-{$smt->sequence}";

	//fucking amazing: same transaction code in the same statement happening at BRD-RO
	//search back in transactions, if same type and code, add some character here
	$i=0;
	foreach($smt->transactions as $t) {
	    do {
		//Search back in transactions to check for same code
		//echo "searching back for code=".$t->transactionCode." type=".$t->transactionType."\n";
		$j=0; $exists = false;
		foreach($smt->transactions as $tback) {
		    if ($i == $j) {
			//echo "    not found\n";
			break;
		    }
		    //echo "  checking against code=".$tback->transactionCode." type=".$tback->transactionType."\n";
		    if (($tback->transactionType == $t->transactionType) && ($tback->transactionCode == $t->transactionCode)) {
			//echo "    exists! add x and go again\n";
			$exists = true;
			$t->transactionCode .='X';
			break;
		    }
		    $j++;
		}
	    } while($exists == true);
	    $i++;
	}
    }
    
}