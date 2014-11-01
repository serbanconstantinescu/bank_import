<?php

include_once($path_to_root . "/modules/bank_import/includes/parser.php");
include_once($path_to_root . "/modules/bank_import/includes/mt940_parser.php");
include_once($path_to_root . "/modules/bank_import/includes/ro_brd_mt940_parser.php");
include_once($path_to_root . "/modules/bank_import/includes/ro_bcr_csv_parser.php");
include_once($path_to_root . "/modules/bank_import/includes/ro_ing_csv_parser.php");


function getParsers() {
    return array(
	'ro_brd_mt940' => array('name' => 'BRD-RO, MT940 format'),
	'ro_bcr_csv' => array('name' => 'BCR-RO, CSV format'),
	'ro_ing_csv' => array('name' => 'ING-RO, CSV format', 'select' => array('bank_account' => 'Select bank account')),
	);
}

?>