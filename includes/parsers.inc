<?php

include_once($path_to_root . "/modules/bank_import/includes/parser.php");
include_once($path_to_root . "/modules/bank_import/includes/mt940_parser.php");
include_once($path_to_root . "/modules/bank_import/includes/ro_brd_mt940_parser.php");
include_once($path_to_root . "/modules/bank_import/includes/ro_bcr_csv_parser.php");


function getParsers() {
    return array(
	'ro_brd_mt940' => 'BRD-RO, MT940 format',
	'ro_bcr_csv' => 'BCR-RO, CSV format',
	);
}

?>