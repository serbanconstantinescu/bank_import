#!/usr/bin/php -f
<?php

//test section - comment in production
require 'banking.php';
require 'parser.php';
require 'mt940_parser.php';

require 'ro_ing_csv_parser.php';
require 'ro_bcr_csv_parser.php';
require 'ro_brd_mt940_parser.php';



//$parser = new ro_ing_csv_parser;
//$content = file_get_contents('statement_ro_ing_csv.csv');
//$static_data = array('account' => 'sadfadadasdasdas', 'currency' => 'RON');

$parser = new ro_bcr_csv_parser;
$content = file_get_contents('statement_ro_bcr_csv.csv');
$static_data = array();

//$parser = new ro_brd_mt940_parser;
//$content = file_get_contents('statement_ro_brd_mt940.sta');
//$static_data = array();




$statements = $parser->parse($content, $static_data, $debug = true);


echo "======================================\n";
$smt_ok = $trz_ok = 0;
foreach ($statements as $smt) {
    if ($smt->validate($debug = true)) {
	$smt_ok ++;
	$trz_cnt = count($smt->transactions);
	$trz_ok += $trz_cnt;
	echo "  valid statement found, $trz_cnt transactions\n";
    } else {
	echo "  invalid statement, ignored\n";
	echo "  --------------------------------------------\n";
	$smt->dump();
	echo "  --------------------------------------------\n";
    }
}

echo "======================================\n";
echo "Total statements  : $smt_ok\n";
echo "Total transactions: $trz_ok\n";


