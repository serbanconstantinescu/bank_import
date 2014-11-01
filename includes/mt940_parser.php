<?php

class mt940_parser extends parser {

    //fields definitions
    // 20: account number
    var $f_20 = '/:20:(\d+)/';
    // 25: IBAN account number
    var $f_25 = '/:25:(\S+)*/';
    // 28: statement number
    var $f_28 = '/:28:(\d{1,5})\/(\d{1,3})/';
    // 60F,62F: C or D, YYMMDD timestamp, ABC currency code, amount
    var $f_60F = '/:60F:([C|D])(\d{6})([A-Z]{3})([\d,\.]+)*/';
    //61 YYMMDD timestamp, MMDD booking time, C or D, amount, N mark, trans type, trans reference, \n, desc
    var $f_61 = '/:61:(\d{6})(\d{4})([CD])([\d]+,\d{2})N([A-Z]{3})([A-Z,\d,\/]+)\n(.*)\n(?=^:86)/sm';
    // 60F,62F: C or D, YYMMDD timestamp, ABC currency code, amount
    var $f_62F = '/:62F:([C|D])(\d{6})([A-Z]{3})([\d,\.]+)*/';


    //subfields definition
    var $sf_20 = '/\+20(.*)/';
    var $sf_21 = '/\+21(.*)/';
    var $sf_22 = '/\+22(.*)/';
    var $sf_23 = '/\+23(.*)/';
    var $sf_24 = '/\+24(.*)/';
    var $sf_25 = '/\+25(.*)/';
    var $sf_26 = '/\+26(.*)/';

    var $sf_31 = '/\+31(.+)/';
    var $sf_32 = '/\+32(.+)/';
    var $sf_33 = '/\+33(.+)/';



    //split strings
    var $statementSplit = '/^(:20:.*?)(?=^:20:|\Z)/sm';
    var $transactionSplit = '/^:61:(.*?)(?=^:61:|\Z)/sm';


    // field mapping
    var $statementMap = array();
    var $transactionMap = array();


    function __construct() {
	// initialize statement map
	$this->statementMap['account']		= array('regexp'=>'f_25', 'index'=>1);
	$this->statementMap['startBalance']	= array('regexp'=>'f_60F','index'=>4);
	$this->statementMap['endBalance']	= array('regexp'=>'f_62F','index'=>4);
	$this->statementMap['currency']		= array('regexp'=>'f_62F','index'=>3);
	$this->statementMap['number']		= array('regexp'=>'f_28', 'index'=>1);
	$this->statementMap['sequence']		= array('regexp'=>'f_28', 'index'=>2);

	$this->statementMap['timestamp']	= array('regexp'=>'f_60F','index'=>2);
	//initialize transaction map
	$this->transactionMap['transactionAmount']	= array('regexp'=>'f_61','index'=>4);
	$this->transactionMap['valueTimestamp']		= array('regexp'=>'f_61','index'=>1);
	$this->transactionMap['entryTimestamp']		= array('regexp'=>'f_61','index'=>2);
	$this->transactionMap['transactionType']	= array('regexp'=>'f_61','index'=>5);
	$this->transactionMap['transactionCode']	= array('regexp'=>'f_61','index'=>6);
	$this->transactionMap['transactionDC']		= array('regexp'=>'f_61','index'=>3);
	$this->transactionMap['transactionCodeDesc']	= array('regexp'=>'f_61','index'=>7);
	$this->transactionMap['account']		= array('regexp'=>'sf_31','index'=>1);
	$this->transactionMap['accountName1']		= array('regexp'=>'sf_32','index'=>1);
	$this->transactionMap['accountName2']		= array('regexp'=>'sf_33','index'=>1);

	$this->transactionMap['transactionTitle1']	= array('regexp'=>'sf_20','index'=>1);
	$this->transactionMap['transactionTitle2']	= array('regexp'=>'sf_21','index'=>1);
	$this->transactionMap['transactionTitle3']	= array('regexp'=>'sf_22','index'=>1);
	$this->transactionMap['transactionTitle4']	= array('regexp'=>'sf_23','index'=>1);
	$this->transactionMap['transactionTitle5']	= array('regexp'=>'sf_24','index'=>1);
	$this->transactionMap['transactionTitle6']	= array('regexp'=>'sf_25','index'=>1);
	$this->transactionMap['transactionTitle7']	= array('regexp'=>'sf_26','index'=>1);
    }

    /**
     * actual parsing of the data
     * @return array
     */
    function parse($string, $static_data = array(), $debug=false) {
	// trim spaces
	$string = trim($string);
	//remove any MSDOS newlines
	$string = str_replace("\r", "", $string);

	$results = array();

	//split content into statements
	foreach ($this->parseData($string, $this->statementSplit) as $statementString) {
	    // fill in statement data
	    $statement = new statement();
	    $this->processMap($this->statementMap, $statementString, $statement);

	    //now search transactions in statement data
	    $statementString = trim($statementString);
	    foreach ($this->parseData($statementString, $this->transactionSplit) as $transactionString) {
		$transaction = new transaction();
		$this->processMap($this->transactionMap, $transactionString, $transaction);
		
		//give childs a chance to post-process transaction
		$this->postProcessTransaction($transaction);
		$statement->addTransaction($transaction);
	    }
	    //give childs a chance to post-process statement
	    $this->postProcessStatement($statement);
	    
	    //add to results
	    $results[] = $statement;

	    
	    //for debug purposes
	    if ($debug) { $statement->dump(); }
	}
	return $results;
    }

    function postProcessStatement(&$statement) {
    }


    function postProcessTransaction(&$transaction) {
    }

    /**
     * split the rawdata up into data chunks
     * @return array
     */
    function parseData($string, $split) {
	$results = array();
	preg_match_all($split,$string, $results);
	return ((!empty($results[0]))? $results[0] : array());
    }


    function processMap($map, $string, $class) {
	$res = array();
	foreach($map as $var=>$pars) {
	    if (!empty($pars['value'])) {
		$class->$var = $pars['value'];
	    } else {
		$str = $pars['regexp'];
		$idx = $pars['index'];
		$res = array();
		preg_match($this->$str, $string, $res);
		if (!empty($res[$idx])) {
		    $class->$var = $res[$idx];
		}
	    }
	}
    }
}