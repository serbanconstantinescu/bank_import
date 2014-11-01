<?php
class banking_base {
	//getter
	public function __get($property) {
	    if(array_key_exists($property,get_class_vars(__CLASS__))) {
		return $this->$property;
	    } elseif (method_exists($this, 'get'.$property)) {
		return call_user_func(array($this, 'get'.$property));
	    } else {
		return null;
	    }
	}

	//setter
	public function __set($property, $value) {
	    if (array_key_exists($property, get_class_vars(__CLASS__))) {
		$this->$property = $value;
	    }
	}
}

class transaction extends banking_base {
	var $valueTimestamp = '';
	var $entryTimestamp = '';

	var $account = '';
	var $accountName1 = '';
	var $accountName2 = '';
//	var $transactionDesc = '';
	var $transactionType = '';
	var $transactionCode = '';
	var $transactionCodeDesc = '';
	var $transactionDC = '';
	var $transactionAmount = 0;
	var $transactionTitle1 = '';
	var $transactionTitle2 = '';
	var $transactionTitle3 = '';
	var $transactionTitle4 = '';
	var $transactionTitle5 = '';
	var $transactionTitle6 = '';
	var $transactionTitle7 = '';

	//custom function to get transaction title
	function getTransactionTitle() {
	    $title = '';
	    for ($i=1; $i<=7; $i++) {
		$var = "transactionTitle$i";
	        $title .= $this->$var;
	    }
	    return $title;
	}
	function getAccountName() {
	    return $this->accountName1 . $this->accountName2;
	}

	function dump() {
	    echo "    -------------------------------------------------------------------\n";
	    echo "    account            ={$this->account}\n";
	    echo "    accountName        ={$this->accountName}\n";
	    echo "    amount             ={$this->transactionAmount}\n";
	    echo "    transactionType    ={$this->transactionType}\n";
	    echo "    valuetimestamp     ={$this->valueTimestamp}\n";
	    echo "    entrytimestamp     ={$this->entryTimestamp}\n";
	    echo "    transactionCode    ={$this->transactionCode}\n";
	    echo "    transactionCodeDesc={$this->transactionCodeDesc}\n";
	    echo "    transactionDC      ={$this->transactionDC}\n";
	    echo "    transactionTitle   ={$this->transactionTitle}\n";
	}

	function validate($debug = false) {
	    $vars = array('transactionAmount', 'transactionType', 'transactionCode', 'transactionDC');
	    foreach($vars as $var) {
		if ($this->$var == "") {
	    	    if ($debug)
	    		echo "$var is empty\n";
		    return false;
		}
	    }
	    //aditional
	    if ($this->transactionType == 'XXX') {
	    	if ($debug)
	    	    echo "unknown transaction type: `XXX`\n";
		return false;
	    }
	    if ($debug)
		echo "ok\n";
		
	    return true;
	}
}

class statement extends banking_base {
	var $bank = '';
	var $account = '';
	var $transactions = array();
	var $currency = '';
	var $startBalance = 0;
	var $endBalance = 0;
	var $timestamp = 0;
	var $number = '';
	var $sequence = '';
	var $statementId = '';

	function addTransaction($transaction) {
	    $this->transactions[] = $transaction;
	}

	function dump() {
	    echo "-------------------------------------------------------------------\n";
	    echo "bank        ={$this->bank}\n";
	    echo "account     ={$this->account}\n";
	    echo "startBalance={$this->startBalance}\n";
	    echo "endBalance  ={$this->endBalance}\n";
	    echo "currency    ={$this->currency}\n";
	    echo "timestamp   ={$this->timestamp}\n";
	    echo "number      ={$this->number}\n";
	    echo "sequence    ={$this->sequence}\n";
	    echo "id          ={$this->statementId}\n";
	    foreach($this->transactions as $trz) {
		$trz->dump();
	    }
	}

	function validate($debug = false) {
	    $vars = array('bank', 'account', 'startBalance', 'endBalance', 'currency', 'timestamp', 'number', 'sequence', 'statementId');
	    foreach($vars as $var) {
		if ($this->$var == "") {
	    	    if ($debug) echo "statement: validate: $var is empty\n";
		    return false;
		}
	    }
	    foreach($this->transactions as $id => $trz) {
		if ($debug)
		    echo "  transaction #$id:";
		if (!$trz->validate($debug))
		    return false;
	    }
	    return true;
	}

}
