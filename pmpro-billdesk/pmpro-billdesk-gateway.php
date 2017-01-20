<?php
/*
Plugin Name: BillDesk Gateway for PMPro
Description: BillDesk Gateway for Paid Memberships Pro
Version: 1.1
Author: Web Vectors Design CoLast Updated: 26 August 2016
*/

define("PMPRO_BILLDESKGATEWAY_DIR", dirname(__FILE__));
function add_query_vars_filter_billdesk( $vars ){
		$vars[] = "status";
		return $vars;
	}
add_filter( 'query_vars', 'add_query_vars_filter_billdesk' );

//load payment gateway class
require_once(PMPRO_BILLDESKGATEWAY_DIR . "/classes/class.pmprogateway_billdesk.php");