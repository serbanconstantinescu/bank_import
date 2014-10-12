<?php

class hooks_bank_import extends hooks {
    var $module_name = 'bank_import'; 

    /*
    * Install additonal menu options provided by module
    */

    function install_options($app) {
	global $path_to_root;


	switch($app->id) {
	    case 'GL':
		$app->add_lapp_function(0, _("Process Bank Statements"),
			$path_to_root."/modules/".$this->module_name."/process_statements.php", 'SA_BANKACCOUNT', MENU_TRANSACTION);
		$app->add_lapp_function(2, _("Manage Partners Bank Accounts"),
			$path_to_root."/modules/".$this->module_name."/manage_partners_data.php", 'SA_CUSTOMER', MENU_MAINTENANCE);
		$app->add_lapp_function(2, _("Import Bank Statements"),
			$path_to_root."/modules/".$this->module_name."/import_statements.php", 'SA_BANKACCOUNT', MENU_MAINTENANCE);
		$app->add_lapp_function(1, _("Bank Statements Inquiry"),
			$path_to_root."/modules/".$this->module_name."/view_statements.php", 'SA_BANKACCOUNT', MENU_INQUIRY);

		break;
	}
    }


    function activate_extension($company, $check_only=true) {
	$updates = array( 'update.sql' => array($this->module_name) );
	return $this->update_databases($company, $updates, $check_only);
	return true;
    }

    //this is required to cancel bank transactions when a voiding operation occurs
    function db_prevoid($trans_type, $trans_no) {
	$sql = "
	    UPDATE ".TB_PREF."bi_transactions
	    SET status=0
	    WHERE
		fa_trans_no=".db_escape($trans_no)." AND
		fa_trans_type=".db_escape($trans_type)." AND
		status = 1";
	//display_notification($sql);
	db_query($sql, 'Could not void transaction');

    }


}
?>