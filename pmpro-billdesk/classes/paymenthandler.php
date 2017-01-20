<?php	
	//set this in your wp-config.php for debugging
	//define('PMPRO_INS_DEBUG', true);
	//in case the file is loaded directly	
	if(!defined("WP_USE_THEMES"))
	{
		global $isapage;
		$isapage = true;
		
		define('WP_USE_THEMES', false);
		require_once(dirname(__FILE__) . '/../../../../wp-load.php');
	}

	//some globals
	global $wpdb, $gateway_environment, $logstr,$pmpro_currency,$pmpro_level, $current_user,  $pmpro_error;

	$logstr = "";	//will put debug info here and write to inslog.txt
	$authorised = false;
	
	$checksum_pass = pmpro_getOption("checksumkey");
	
	$resmsg = $_REQUEST['msg'];
	$requestdata = explode('|', $resmsg);		 


	
	$txnref = $requestdata[2];
	$order_id = $requestdata[1];
	$txn_responce=$requestdata[24];		
	$paymentSt = trim($requestdata[14]);			 	
	$msg['class']   = 'error';
	$msg['message'] = "Thank you for shopping with us. However, the transaction has been declined.";
//	$level_id=$_REQUEST['level'];
	$level_id=$requestdata[22];
	$amount=$requestdata[4];	

	//validate?
	if( !pmpro_billdeskValidate($checksum_pass) ) {		
		$pmpro_error = __($txn_responce, "pmpro");		
		pmpro_billdeskExit(pmpro_url("checkout", "?level=" .$level_id."&pmpro_error=".urlencode($txn_responce)));

		
		
	 }
	 //if validation success
	if( pmpro_billdeskValidate($checksum_pass)) {
		//initial payment, get the order
		$last_subscr_order = new MemberOrder($order_id);
		global $current_user;
		$user_id = $current_user->ID;

		$morder = new MemberOrder( $order_id );
		$morder->getMembershipLevel();
		$morder->getUser();

		/*standard code generation*/
		/*-----------------------------my new test code--------------------------------------*/
		if( ! empty ( $morder ) && ! empty ( $morder->status ) && $morder->status === 'success' ) {
			inslog( "Checkout was already processed (" . $morder->code . "). Ignoring this request." );
		}
		elseif (pmpro_insChangeMembershipLevel( $order_id, $morder ) ) {
				
			inslog( "Checkout processed (" . $morder->code . ") success!" );
		}
		elseif( $last_subscr_order->getLastMemberOrderBySubscriptionTransactionID( $order_id ) == false) {
			//first payment, get order	
			$morder->subscription_transaction_id = $txnref; 
			$morder->InitialPayment = $amount;  
			$morder->PaymentAmount = $amount;	
			$morder->getMembershipLevel();
			$morder->getUser();

			//update membership
			if( pmpro_insChangeMembershipLevel( $order_id,$morder ) ) {									
				inslog( "Checkout processed (" . $morder->code . ") success!" );			
			}
			else {
				inslog( "ERROR: Couldn't change level for order (" . $morder->code . ")." );	
			}
		}
		else {
			pmpro_insSaveOrder( $order_id, $last_subscr_order );
			
		}	
		
	
		
		
		inslog("NO MESSAGE: ORDER: " . var_export($morder, true) . "\n---\n");		
		// $service_host=get_home_url().'/membership-account/membership-confirmation/?level='.$order->membership_level->id;
		// header("Location:".$service_host);
		pmpro_billdeskExit(pmpro_url("confirmation", "?level=" . $morder->membership_level->id));
	 }
	else{
		pmpro_billdeskExit();
	 }
	 inslog("The PMPro INS handler does not process this type of message. message_type = " . $message_type);
	 pmpro_billdeskExit();	
	/* my billing functions*/

	

	function null2unknown($data) {
			if ($data == "") {
				return "No Value Returned";
			} else {
				return $data;
			}
		}
	

	/*
		Add message to inslog string
	*/
	function inslog( $s )
	{		
		global $logstr;		
		$logstr .= "\t" . $s . "\n";
	}
	
	/*
		Output inslog and exit;
	*/
	function pmpro_billdeskExit($redirect = false)
	{	
		
		if(!empty($redirect))
			wp_redirect($redirect);
//			header("Location:".$redirect);
		
		exit;
	}

	/*
		Validate the $_POST with TwoCheckout
	*/
	function pmpro_billdeskValidate($checksum_pass) {
		
		$authorised = false;
	
		$msg = $_REQUEST['msg'];
		$requestdata = explode('|', $msg);	
		$paymentSt = trim($requestdata[14]);			 
//		$paymentSt = '0300';
		/* Checking hash / true or false */
		if(check_hash_after_transaction($checksum_pass, $msg)) {
			if($paymentSt=='0300'){
				$authorised = true;
			}
			else {
				$authorised = false;
			} 
		} else {			
			$authorised = false;			
		}

		return $authorised;
	}
	
function check_hash_after_transaction($checksum_pass, $msg) {
	
		$requestdata = explode('|', $msg);
		$paymentSt = $requestdata[14];
		$order_id = $requestdata[1];
		$bdchecksum = substr(strrchr($msg, "|"), 1);
		$codestr = '|'.$bdchecksum;
		$string_new=str_replace($codestr,'',$msg);
		$checksum = strtoupper(hash_hmac('sha256',$string_new ,$checksum_pass,  false));		
		
		
		
		/* The hash is valid */
		if($checksum == $bdchecksum) {
			return true;
		} else {
			return false;
		}
	} 
	
	
	/*
		Change the membership level. We also update the membership order to include filtered valus.
	*/
	function pmpro_insChangeMembershipLevel($txnref, &$morder)
	{
		//$recurring = pmpro_getParam( 'recurring', 'POST' );
		
		//filter for level
		$morder->membership_level = apply_filters("pmpro_inshandler_level", $morder->membership_level, $morder->user_id);
					
		//fix expiration date		
		if(!empty($morder->membership_level->expiration_number))
		{
			$enddate = "'" . date("Y-m-d", strtotime("+ " . $morder->membership_level->expiration_number . " " . $morder->membership_level->expiration_period, current_time("timestamp"))) . "'";
		}
		else
		{
			$enddate = "NULL";
		}
		
		//get discount code
		$morder->getDiscountCode();
		if(!empty($morder->discount_code))
		{		
			//update membership level
			$morder->getMembershipLevel(true);
			$discount_code_id = $morder->discount_code->id;
		}
		else
			$discount_code_id = "";
		
		//set the start date to current_time('mysql') but allow filters
		$startdate = apply_filters("pmpro_checkout_start_date", "'" . current_time('mysql') . "'", $morder->user_id, $morder->membership_level);
		
		//custom level to change user to
		$custom_level = array(
			'user_id' => $morder->user_id,
			'membership_id' => $morder->membership_level->id,
			'code_id' => $discount_code_id,
			'initial_payment' => $morder->membership_level->initial_payment,
			'billing_amount' => $morder->membership_level->billing_amount,
			'cycle_number' => $morder->membership_level->cycle_number,
			'cycle_period' => $morder->membership_level->cycle_period,
			'billing_limit' => $morder->membership_level->billing_limit,
			'trial_amount' => $morder->membership_level->trial_amount,
			'trial_limit' => $morder->membership_level->trial_limit,
			'startdate' => $startdate,
			'enddate' => $enddate);

		global $pmpro_error;
		if(!empty($pmpro_error))
		{
			echo $pmpro_error;
			inslog($pmpro_error);				
		}
		if( pmpro_changeMembershipLevel($custom_level, $morder->user_id) !== false ) {

			//update order status and transaction ids					
			$morder->status = "success";
			$morder->payment_transaction_id = $txnref;
			//if( $recurring )
				$morder->subscription_transaction_id = $txnref;
			//else
				//$morder->subscription_transaction_id = '';*/
			$morder->saveOrder();
			
			//add discount code use
			if(!empty($discount_code) && !empty($use_discount_code))
			{
				$wpdb->query("INSERT INTO $wpdb->pmpro_discount_codes_uses (code_id, user_id, order_id, timestamp) VALUES('" . $discount_code_id . "', '" . $morder->user_id . "', '" . $morder->id . "', '" . current_time('mysql') . "')");
			}									
		
			//save first and last name fields
			if(!empty($_POST['first_name']))
			{
				$old_firstname = get_user_meta($morder->user_id, "first_name", true);
				if(!empty($old_firstname))
					update_user_meta($morder->user_id, "first_name", $_POST['first_name']);
			}
			if(!empty($_POST['last_name']))
			{
				$old_lastname = get_user_meta($morder->user_id, "last_name", true);
				if(!empty($old_lastname))
					update_user_meta($morder->user_id, "last_name", $_POST['last_name']);
			}
												
			//hook
			do_action("pmpro_after_checkout", $morder->user_id);						
		
			//setup some values for the emails
			if(!empty($morder))
				$invoice = new MemberOrder($morder->id);						
			else
				$invoice = NULL;
		
			inslog("CHANGEMEMBERSHIPLEVEL: ORDER: " . var_export($morder, true) . "\n---\n");
		
			$user = get_userdata($morder->user_id);					
			
			if(empty($user))
				return false;
				
			$user->membership_level = $morder->membership_level;		//make sure they have the right level info
			
		
			//send email to member
			$pmproemail = new PMProEmail();				
			$pmproemail->sendCheckoutEmail($user, $invoice);
										
			//send email to admin
			$pmproemail = new PMProEmail();
			$pmproemail->sendCheckoutAdminEmail($user, $invoice);
			
			
			return true;
		}
		else
			return false;
	}
	/*failed payment trigger*/
	function pmpro_insFailedPayment( $last_order ) {		
		//hook to do other stuff when payments fail		
		do_action("pmpro_subscription_payment_failed", $last_order);							
	
		//create a blank order for the email			
		$morder = new MemberOrder();
		$morder->user_id = $last_order->user_id;
				
		// Email the user and ask them to update their credit card information			
		$pmproemail = new PMProEmail();				
		$pmproemail->sendBillingFailureEmail($user, $morder);
	
		// Email admin so they are aware of the failure
		$pmproemail = new PMProEmail();				
		$pmproemail->sendBillingFailureAdminEmail(get_bloginfo("admin_email"), $morder);	
	
		inslog("Payment failed. Emails sent to " . $user->user_email . " and " . get_bloginfo("admin_email") . ".");	
		
		return true;
	}
	/*save order function*/
	function pmpro_insSaveOrder( $txnref, $last_order ) {
		global $wpdb;
		//check that txn_id has not been previously processed
		$old_txn = $wpdb->get_var("SELECT payment_transaction_id FROM $wpdb->pmpro_membership_orders WHERE payment_transaction_id = '" . $txnref . "' LIMIT 1");
		
		if( empty( $old_txn ) ) {	
			//hook for successful subscription payments
			do_action("pmpro_subscription_payment_completed");
			//save order
			$morder = new MemberOrder();
			$morder->user_id = $last_order->user_id;
			$morder->membership_id = $last_order->membership_id;			
			$morder->payment_transaction_id = $txnref;
			$morder->subscription_transaction_id = $last_order->subscription_transaction_id;
			$morder->InitialPayment = $last_order->InitialPayment;//$_POST['item_list_amount_1'];	//not the initial payment, but the class is expecting that
			$morder->PaymentAmount = $last_order->PaymentAmount;//$_POST['item_list_amount_1'];
			
			
			$morder->gateway = $last_order->gateway;
			$morder->gateway_environment = $last_order->gateway_environment;
			
			//save
			$morder->saveOrder();
			
			$pmproemail = new PMProEmail();
			$pmproemail->sendInvoiceEmail($user_id, $morder);
			
			$user = get_userdata($morder->user_id);
			$user->membership_level = $morder->membership_level;		//make sure they have the right level info

			//send email to member
			$pmproemail = new PMProEmail();
			$pmproemail->sendCheckoutEmail($user_id, $morder);

			//send email to admin
			$pmproemail = new PMProEmail();
			$pmproemail->sendCheckoutAdminEmail($user_id, $morder);
			$morder->getMemberOrderByID( $morder->id );
			
			
				
			// //email the user their invoice				
			$pmproemail = new PMProEmail();				
			$pmproemail->sendInvoiceEmail( get_userdata( $last_order->user_id ), $morder );	
			if(strpos(PMPRO_INS_DEBUG, "@"))
				$log_email = PMPRO_INS_DEBUG;	//constant defines a specific email address
			else
				$log_email = get_option("admin_email");	
			
			
			inslog( "New order (" . $morder->code . ") created." );
			return true;
		}
		else {
			inslog( "Duplicate Transaction ID: " . $txnref );
			
			return false;
		}
	}
