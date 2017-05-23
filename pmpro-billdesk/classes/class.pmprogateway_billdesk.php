<?php	
	
	//load classes init method
	add_action('init', array('PMProGateway_billdesk', 'init'));
		
	class PMProGateway_billdesk extends PMProGateway
	{
		function __construct($gateway = NULL)
		{
			$this->gateway = $gateway;
			return $this->gateway;
		}										
		
		/**
		 * Run on WP init
		 *		 
		 * @since 1.8
		 */
		static function init()
		{			
			//make sure PayPal Express is a gateway option
			add_filter('pmpro_gateways', array('PMProGateway_billdesk', 'pmpro_gateways'));
			
			//add fields to payment settings
			add_filter('pmpro_payment_options', array('PMProGateway_billdesk', 'pmpro_payment_options'));	
			global $pmpro_payment_option_fields_for_billdesk;
			if(empty($pmpro_payment_option_fields_for_billdesk))			{				
				
			add_filter('pmpro_payment_option_fields', array('PMProGateway_billdesk', 'pmpro_payment_option_fields'), 10, 2);
				$pmpro_payment_option_fields_for_billdesk = true;
			}
			

			//code to add at checkout
			$gateway = pmpro_getGateway();

			if($gateway == "billdesk")
			{
				// add_action('pmpro_checkout_preheader', array('PMProGateway_billdesk', 'pmpro_checkout_preheader'));		
				add_filter('pmpro_include_billing_address_fields', array('PMProGateway_billdesk', 'pmpro_include_billing_address_fields'));
				add_filter('pmpro_required_billing_fields', array('PMProGateway_billdesk', 'pmpro_required_billing_fields'));
				add_filter('pmpro_checkout_before_processing', array('PMProGateway_billdesk', 'pmpro_checkout_before_processing'));
				add_filter('pmpro_include_payment_information_fields', array('PMProGateway_billdesk', 'pmpro_include_payment_information_fields'));
				add_filter('pmpro_checkout_before_change_membership_level', array('PMProGateway_billdesk', 'pmpro_checkout_before_change_membership_level'), 10, 2);
			}

		}
		
		
		/**
		 * Make sure this gateway is in the gateways list
		 *		 
		 * @since 1.8
		 */
		static function pmpro_gateways($gateways)
		{
			if(empty($gateways['billdesk']))
				$gateways['billdesk'] = __('billdesk', 'pmpro');
		
			return $gateways;
		}
		
		/**
		 * Get a list of payment options that the this gateway needs/supports.
		 *		 
		 * @since 1.8
		 */
		static function getGatewayOptions()
		{			
			$options = array(
				'sslseal',
				'nuclear_HTTPS',
				'gateway_environment',
				'billerid',
				'checksumkey',
				'currency',
				'use_ssl',
				'tax_state',
				'tax_rate',
				
			);
			
			return $options;
		}
	
		/**
		 * Set payment options for payment settings page.
		 *		 
		 * @since 1.8
		 */
		static function pmpro_payment_options($options)
		{			
			//get stripe options
			$billdesk_options = PMProGateway_billdesk::getGatewayOptions();
			
			//merge with others.
			$options = array_merge($billdesk_options, $options);
			
			return $options;
		}
		
		/**
		 * Display fields for this gateway's options.
		 *		 
		 * @since 1.8
		 */
		static function pmpro_payment_option_fields($values, $gateway)
		{
		?>
			<tr class="pmpro_settings_divider gateway gateway_billdesk" <?php if($gateway != "billdesk") { ?>style="display: none;"<?php } ?>>
				<td colspan="2">
					<?php _e('BillDesk Settings', 'pmpro'); ?>
				</td>
			</tr>
			<tr class="gateway gateway_billdesk" <?php if($gateway != "billdesk") { ?>style="display: none;"<?php } ?>>
				<?php // billdesk custom pamyment settings here ?>
			</tr>
			<tr class="gateway gateway_billdesk" <?php if($gateway != "billdesk") { ?>style="display: none;"<?php } ?>>
				<th scope="row" valign="top">
					<label for="billerid"><?php _e('Biller ID', 'pmpro');?>:</label>
				</th>
				<td>
					<input type="text" id="billerid" name="billerid" size="60" value="<?php echo esc_attr($values['billerid'])?>" />
				</td>
			</tr>
			<tr class="gateway gateway_billdesk" <?php if($gateway != "billdesk") { ?>style="display: none;"<?php } ?>>
				<th scope="row" valign="top">
					<label for="checksumkey"><?php _e('Checksum Key', 'pmpro');?>:</label>
				</th>
				<td>
					<input type="text" id="checksumkey" name="checksumkey" size="60" value="<?php echo esc_attr($values['checksumkey'])?>" />
				</td>
			</tr>
		
		<?php
		}
		
		// define the pmpro_checkout_preheader callback 
		function pmpro_checkout_preheader() { 
			// make action magic happen here... 
			//echo 'Hello World!';
			// var_dump( $array );
			// die();
		}
         
		// add the action 
		// add_action( 'pmpro_checkout_preheader', 'wv_action_pmpro_checkout_preheader', 10, 1 ); 
		
		
		static function pmpro_include_billing_address_fields($include)
		{
			//check settings RE showing billing address
			// if(!pmpro_getOption("example_billingaddress"))
				// $include = false;

			return $include;
		}
		
		/**
		 * Remove required billing fields
		 *
		 * @since 1.8
		 */
		static function pmpro_required_billing_fields($fields)
		{	
			unset($fields['CardType']);
			unset($fields['AccountNumber']);
			unset($fields['ExpirationMonth']);
			unset($fields['ExpirationYear']);
			unset($fields['CVV']);

			return $fields;
		}
		
		/**
		 * Save session vars before processing
		 *
		 * @since 1.8
		 */
		static function pmpro_checkout_before_processing()
		{
			global $current_user, $gateway;

			//save user fields for BillDesk
			if(!$current_user->ID)
			{
				//get values from post
				if(isset($_REQUEST['username']))
					$username = trim($_REQUEST['username']);
				else
					$username = "";
				if(isset($_REQUEST['password']))
					$password = $_REQUEST['password'];
				else
					$password = "";
				if(isset($_REQUEST['bemail']))
					$bemail = $_REQUEST['bemail'];
				else
					$bemail = "";

				//save to session
				$_SESSION['pmpro_signup_username'] = $username;
				$_SESSION['pmpro_signup_password'] = $password;
				$_SESSION['pmpro_signup_email'] = $bemail;
			}

			//can use this hook to save some other variables to the session
			do_action("pmpro_paypalexpress_session_vars");
			
			// update custom fields before going offsite to billdesk
			do_action('pmpro_before_send_to_paypal_standard', $current_user->ID);
		}

		/*hide the card field*/
		static function pmpro_include_payment_information_fields($include)
		{
			//global vars
			global $pmpro_requirebilling, $pmpro_show_discount_code, $discount_code, $CardType, $AccountNumber, $ExpirationMonth, $ExpirationYear;
			
			//get accepted credit cards
			$pmpro_accepted_credit_cards = pmpro_getOption("accepted_credit_cards");
			$pmpro_accepted_credit_cards = explode(",", $pmpro_accepted_credit_cards);
			$pmpro_accepted_credit_cards_string = pmpro_implodeToEnglish($pmpro_accepted_credit_cards);

			//include ours
			?>
			<table id="pmpro_payment_information_fields" class="pmpro_checkout top1em" width="100%" cellpadding="0" cellspacing="0" border="0" 
			<?php //if(!$pmpro_requirebilling || apply_filters("pmpro_hide_payment_information_fields", false) ) { ?>
			style="display: none;"
			<?php //} ?>>
			<thead>
				<tr>
					<th><span class="pmpro_thead-msg"><?php printf(__('We Accept %s', 'pmpro'), $pmpro_accepted_credit_cards_string);?></span><?php _e('Payment Information', 'pmpro');?></th>
				</tr>
			</thead>
			<tbody>
				<tr valign="top">
					<td>
						<?php
							$sslseal = pmpro_getOption("sslseal");
							if($sslseal)
							{
							?>
								<div class="pmpro_sslseal"><?php echo stripslashes($sslseal)?></div>
							<?php
							}
						?>
						<?php 
						/*this section is for if any discount code exists*/
						//if($pmpro_show_discount_code) { 
						?>
						
						<!-- <div class="pmpro_payment-discount-code">
							<label for="discount_code"><?php //_e('Discount Code', 'pmpro');?></label>
							<input class="input <?php //echo pmpro_getClassForField("discount_code");?>" id="discount_code" name="discount_code" type="text" size="20" value="<?php //echo esc_attr($discount_code)?>" />
							<input type="button" id="discount_code_button" name="discount_code_button" value="<?php //_e('Apply', 'pmpro');?>" />
							<p id="discount_code_message" class="pmpro_message" style="display: none;"></p>
						</div> -->
						<?php //} ?>

					</td>
				</tr>
			</tbody>
			</table>

			<?php
			if ($_GET['pmpro_error']) echo '<div id="pmpro_message" class="pmpro_message pmpro_error">'.$_GET['pmpro_error'].'</div>';            

			//don't include the default
			return false;
		}
		
		/**
		 * Swap in our submit buttons.
		 *
		 * @since 1.8
		 */
		static function pmpro_checkout_default_submit_button($show)
		{
			global $gateway, $pmpro_requirebilling, $pmpro_error;
			
			//show our submit buttons
			?>			
			<span id="pmpro_submit_span">
				<input type="hidden" name="submit-checkout" value="1" />		
				<input type="submit" class="pmpro_btn pmpro_btn-submit-checkout" value="<?php if($pmpro_requirebilling) { _e('checkout', 'pmpro'); } else { _e('Proceed', 'pmpro');}?> &raquo;" />		
			</span>
			<?php
		
			//don't show the default
			return false;
		}
		
		/**
		 * Instead of change membership levels, send users to BillDesk to pay.
		 *
		 * @since 1.8
		 */
		static function pmpro_checkout_before_change_membership_level($user_id, $morder)
		{
		
			global $wpdb, $discount_code_id;
			
			//if no order, no need to pay
			if(empty($morder))
				return;
			
			$morder->user_id = $user_id;				
			$morder->saveOrder();
			
			//save discount code use
			if(!empty($discount_code_id))
				$wpdb->query("INSERT INTO $wpdb->pmpro_discount_codes_uses (code_id, user_id, order_id, timestamp) VALUES('" . $discount_code_id . "', '" . $user_id . "', '" . $morder->id . "', now())");	
			
			do_action("pmpro_before_send_to_billdesk", $user_id, $morder);
			
			$morder->Gateway->sendToBillDesk($morder);
		}
		
		/**
		 * Process checkout.
		 *		
		 */
		function process(&$order)
		{	
								
			if(empty($order->code))
				$order->code = $order->getRandomCode();			
			
			//clean up a couple values
			$order->payment_type = "billdesk";
			$order->CardType = "";
			$order->cardtype = "";
			

			$order->status = "cancelled";	
			$order->payment_transaction_id = $order->code;
			$order->subscription_transaction_id=$order->code.'_'.date("Y-m-d");
			$order->membership_level = apply_filters("pmpro_checkout_level", $order->membership_level);
			$order->saveOrder();
			//sendToBillDesk($order);
			//if($this->sendToBillDesk($order))
			return true;//else return false;	
		}
		
		
		function getTaxForPriceIN($price, $order)
		{
			//get options
			$tax_state = pmpro_getOption("tax_state");
			$tax_rate = pmpro_getOption("tax_rate");

			//default
			$tax = 0;

			//calculate tax
			if($tax_state && $tax_rate)
			{
				//we have values, is this order in the tax state?
					//return value, pass through filter
					$tax = round((float)$price * (float)$tax_rate, 2);
			}

			//set values array for filter
			$values = array("price" => $price, "tax_state" => $tax_state, "tax_rate" => $tax_rate);
			if(!empty($order->billing->state))
				$values['billing_state'] = $order->billing->state;
			if(!empty($order->billing->city))
				$values['billing_city'] = $order->billing->city;
			if(!empty($order->billing->zip))
				$values['billing_zip'] = $order->billing->zip;
			if(!empty($order->billing->country))
				$values['billing_country'] = $order->billing->country;

			//filter
			$order->tax = $tax;
			$order->total  = $price+$tax;			
			$order->saveOrder();
			$tax = apply_filters("pmpro_tax", $tax, $values, $order);
			return $tax;
		}
				
		public	function pmpro_insSaveOrder1( $txnref, $last_order ) {
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
			
			
			//inslog( "New order (" . $morder->code . ") created." );
			return true;
		}
		else {
			//inslog( "Duplicate Transaction ID: " . $txnref );
			
			return false;
		}
	}
	public	function pmpro_insChangeMembershipLevel1($txnref, &$morder)
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
			//inslog($pmpro_error);				
		}
		if( pmpro_changeMembershipLevel($custom_level, $morder->user_id) !== false ) {

			//update order status and transaction ids					
			$morder->status = "cancelled";
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
		
			//inslog("CHANGEMEMBERSHIPLEVEL: ORDER: " . var_export($morder, true) . "\n---\n");
		
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
	
		function sendToBillDesk(&$order)
		{				
		error_reporting(0);		
			global $pmpro_currency;		
			$initial_payment = $order->InitialPayment;
			$details='';


		global $current_user;
				$user_id = $current_user->ID;
		
			global $pmprorh_registration_fields;
		
	//any fields?niraj
	if(!empty($pmprorh_registration_fields))
	{		
		foreach($pmprorh_registration_fields as $where => $fields)
		{						
			//cycle through fields
			foreach($fields as $field)
			{
				if(!pmprorh_checkFieldForLevel($field))
					continue;
				
				if(!empty($field->profile) && ($field->profile === "only" || $field->profile === "only_admin"))
					continue;	//wasn't shown at checkout
				
				//assume no value
				$value = NULL;
				
				//where are we getting the value from?
				if(isset($_REQUEST[$field->name]))
				{
					//request
					$value = $_REQUEST[$field->name];
				}
				elseif(isset($_SESSION[$field->name]))
				{					
					//file or value?
					if(is_array($_SESSION[$field->name]) && isset($_SESSION[$field->name]['name']))
					{
						//add to files global
						$_FILES[$field->name] = $_SESSION[$field->name];
						
						//set value to name
						$value = $_SESSION[$field->name]['name'];
					}
					else					
					{
						//session
						$value = $_SESSION[$field->name];
					}
					
					//unset
					unset($_SESSION[$field->name]);
				}
				elseif(isset($_FILES[$field->name]))
				{
					//file		
					
								
					//$value = $_FILES[$field->name]['name'];
				
					$user = get_userdata($user_id);
					$upload_dir = wp_upload_dir();
			$pmprorh_dir = $upload_dir['basedir'] . "/pmpro-register-helper/" . $user->user_login . "/";
			$pmprorh_dir_hellper = $upload_dir['basedir'] . "/pmpro-register-helper/";
			//create the dir and subdir if needed
			if(!is_dir($pmprorh_dir))
			{
				wp_mkdir_p($pmprorh_dir);
			}
			
			$old_file = get_user_meta($user->ID, $meta_key, true);			
			if(!empty($old_file) && !empty($old_file['fullpath']) && file_exists($old_file['fullpath']))
			{				
				unlink($old_file['fullpath']);				
			}	
			$filename = $_FILES[$field->name]['name'];
			//echo "filename".$filename."->".$field->name."<br>";
			//	move_uploaded_file($_FILES[$field->name]['tmp_name'], $pmprorh_dir . $filename);	
				if(file_exists($pmprorh_dir_hellper.$filename)){
				
				    rename($pmprorh_dir_hellper.$filename , $pmprorh_dir.$filename);
					unlink($pmprorh_dir_hellper.$filename);
				
				
				update_user_meta($user_id, $field->meta_key, array("original_filename"=>$filename, "filename"=>$filename, "fullpath"=> $pmprorh_dir . $filename, "fullurl"=>content_url("/uploads/pmpro-register-helper/" . $user->user_login . "/" . $filename), "size"=> $_FILES[$field->name]['size']));
				}
			 }else{
					//move_uploaded_file($_FILES[$field->name]['tmp_name'], $pmprorh_dir . $filename);
					
					//update_user_meta($user_id, $field->meta_key, array("original_filename"=>$filename, "filename"=>$filename, "fullpath"=> $pmprorh_dir . $filename, "fullurl"=>content_url("/uploads/pmpro-register-helper/" . $user->user_login . "/" . $filename), "size"=> $_FILES[$field->name]['size']));				 
			 } 
			 
			

				//update user meta
				if(isset($value))	
				{			
						
					//callback?
					if($field->meta_key=="dob"){
					if(strlen($value['m'])==1){
						$month = "0".$value['m'];
					}else{
						$month = $value['m'];
					}
					$dob = $value['y']."-".$month."-".$value['d'];
					
					update_user_meta($user_id, $field->meta_key, $dob);
					}else{
						update_user_meta($user_id, $field->meta_key, $value);
						}
						//unset($_SESSION[$field->meta_key]);
				
				}
			}			
		}
		if($_REQUEST['emergency_phone']!=""){
			update_user_meta($user_id, 'emergency_phone', $_REQUEST['emergency_phone']);
		}if($_REQUEST['alternate_phone']!=""){
			update_user_meta($user_id, 'alternate_phone', $_REQUEST['alternate_phone']);
		}
	}
	
	
			$amount = $order->PaymentAmount;

			// Recurring membership			
			if( pmpro_isLevelRecurring( $order->membership_level ) ) {
				$amount = number_format($initial_payment - $amount, 2, ".", "");		//negative amount for lower initial payments
				$recurring_payment = number_format($order->membership_level->billing_amount, 2, ".", "");
				$amount = number_format($recurring_payment, 2, ".", "");

				$details= ( $order->BillingFrequency == 1 ) ? $order->BillingFrequency . ' ' . $order->BillingPeriod : $order->BillingFrequency . ' ' . $order->BillingPeriod . 's';

				if( property_exists( $order, 'TotalBillingCycles' ) )
					$details = ($order->BillingFrequency * $order->TotalBillingCycles ) . ' ' . $order->BillingPeriod;
				else
					$details = 'Forever';
			}
			// Non-recurring membership
			else {
				$amount = number_format($initial_payment, 2, ".", "");
			}
			if(!empty($order->TrialBillingPeriod)) {
				$trial_amount = $order->TrialAmount;
				$amount = $trial_amount; // Negative trial amount
			}
			global $pmpro_level;

			//create a code for the order
			if(empty($order->code))
				$order->code = $order->getRandomCode();

			//what amount to charge?
			$amount = $order->InitialPayment;

			//tax
			$order->subtotal = $amount;
			$tax = $this->getTaxForPriceIN($amount, $order);
			$amount = round((float)$order->subtotal + (float)$tax, 2);
				
			$order_id = $order->code;

				
			
		$morder = new MemberOrder( $order_id );
		$morder->getMembershipLevel();
		$morder->getUser();

	
		/*standard code generation*/
		/*-----------------------------my new test code--------------------------------------*/
		//if( ! empty ( $morder ) && ! empty ( $morder->status ) && $morder->status === 'success' ) {
			//inslog( "Checkout was already processed (" . $morder->code . "). Ignoring this request." );
		//}
		//elseif (PMProGateway_billdesk::pmpro_insChangeMembershipLevel1( $order_id, $morder ) ) {
				
			//inslog( "Checkout processed (" . $morder->code . ") success!" );
		//}
		//elseif( $last_subscr_order->getLastMemberOrderBySubscriptionTransactionID( $order_id ) == false) {
			//first payment, get order	
		//	$morder->subscription_transaction_id = $txnref; 
		//	$morder->InitialPayment = $amount;  
		//	$morder->PaymentAmount = $amount;	
		//	$morder->getMembershipLevel();
		//	$morder->getUser();

			//update membership
			//if( PMProGateway_billdesk::pmpro_insChangeMembershipLevel1( $order_id,$morder ) ) {									
				//inslog( "Checkout processed (" . $morder->code . ") success!" );			
			//}
			//else {
				//inslog( "ERROR: Couldn't change level for order (" . $morder->code . ")." );	
			//}
		//}
		//else {
			//PMProGateway_billdesk::pmpro_insSaveOrder1( $order_id, $last_subscr_order );
			
		//}		
				
				

		    $this->service_host = "https://pgi.billdesk.com/pgidsk/PGIMerchantPayment ";
			if  (pmpro_getOption("gateway_environment") == "sandbox") $this->service_host = "https://pgi.billdesk.com/pgidsk/PGIMerchantPayment ";
//			$returnUrl = site_url()."/wp-content/plugins/pmpro-billdesk/classes/paymenthandler.php?level=".$order->membership_level->id;
			$returnUrl = site_url()."/wp-content/plugins/pmpro-billdesk/classes/paymenthandler.php";

			$productinfo = "Subscription Fees";		
			
			$orderId = $order_info['order_id'];


			$MerchantId = pmpro_getOption("billerid");
			$checksum_pass = pmpro_getOption("checksumkey");
			$customer_name	=$order->billing->name;								
        	$phone    = $order->billing->phone;		
			if($phone==""){
				$phone = "123";
			}
			$email  	= $order->Email;		
			$SecurityId = strtolower($MerchantId);
			$memlevel = $order->membership_level->id;

			if ( $customer_name	== "" ) {
				$f_name = get_user_meta( $user_id, 'first_name', true);
				$l_name = get_user_meta( $user_id, 'last_name', true);
				$customer_name = $f_name . ' ' . $l_name;
			}
			
			$webv_member_id  = get_user_meta( $user_id, 'webv_member_id',true); 
			$webv_member_data = "NA";
			if($webv_member_id!=""){
				$webv_member_data = $webv_member_id; 
			}
			
			$checksum_srt ="$MerchantId|$order_id|$webv_member_data|$amount|NA|NA|NA|INR|NA|R|$SecurityId|NA|NA|F|$phone|$email|$customer_name|$order_id|NA|NA|$memlevel|$returnUrl";
		
//			echo $checksum_srt;
//			die();
			
			$checksum = hash_hmac('sha256',$checksum_srt,$checksum_pass, false); 
			
			$billdesk_msg = $checksum_srt .'|'. strtoupper($checksum);
			
		
			$data = array(
				'msg' => $billdesk_msg,

			);			
			
			

			foreach($data as $key => $value){
			$billdesk_fm_array[] = "<input type='hidden' name='$key' value='$value'/>";
			}		   
			$redirectHtml = '<html>
<head><title>Processing..</title>
<script language="javascript">
function onLoadSubmit() {
	document.merchantForm.submit();
}
</script>
</head>
<body onLoad="onLoadSubmit();">

	<br />&nbsp;<br /b>
	<center><font size="5" color="#3b4455">Transaction is being processed,<br/>Please wait ... </font></center><form name="merchantForm" method="post" action="'. $this->service_host.'"/>'.implode('', $billdesk_fm_array) . '<noscript>
		<br />&nbsp;<br />
		<center>
		<font size="3" color="#3b4455">
		JavaScript is currently disabled or is not supported by your browser.<br />
		Please click Submit to continue the processing of your transaction.<br />&nbsp;<br />
		<input type="submit" />
		</font>
		</center>
	</noscript>
	</form>
</body>

</html>';		   

			echo $redirectHtml;
			exit();
				
			
		}

		function cancel(&$order)
		{

			if(empty($order->subscription_transaction_id))
			return false;
			
			$order->updateStatus("cancelled");					
			return true;
		}	
	}