<?php

/**
 * Omni Merchant controller for QuickBooks Web Connector integrations
 *
 *
 */

#use func\QB_Customer AS QB_Customer, func\QB_Invoice AS QB_invoice;

class QB extends CI_Controller
{

    use func\QB_Customer;
    use func\QB_Invoice;
    #protected $QB_Customer = 'jay';
	public function __construct()
	{
		parent::__construct();

		echo "<pre>";
		print_r($this->abc());
		# abc() method is defined into application/traits/func/QB_customer.php file
		echo "</pre>";die;
		// QuickBooks config
		$this->load->config('quickbooks');
		#$this->load->model('general_model');
		$dsn ='mysqli://' . $this->db->username . ':' . $this->db->password . '@'.$this->db->hostname.  '/' . $this->db->database;
		define('QB_QUICKBOOKS_DSN', $dsn);
		define('QB_QUICKBOOKS_MAILTO', 'support.omni@test.com');


	}

	function index(){
		// echo QB_QUICKBOOKS_DSN;die;
		redirect(base_url('home/company'));

	}



	public function config()
	{

		$descrip = 'PayPortal';		// A description of your server 

		$appurl = 'https://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/qbwc';		// This *must* be httpS:// (path to your QuickBooks SOAP server)
		$appsupport = $appurl; 		// This *must* be httpS:// and the domain name must match the domain name above



		$username  = $this->input->post('qbusername'); 	// This is the username you stored in the 'quickbooks_user' table by using QuickBooks_Utilities::createUser()
		$name      = $this->input->post('qbcompany_name'); 	// A name for your server (make it whatever you want)
		$password  = $this->input->post('qbpassword');

		$fileid = QuickBooks_WebConnector_QWC::fileID();		// Just make this up, but make sure it keeps that format
		$ownerid = QuickBooks_WebConnector_QWC::ownerID();		// Just make this up, but make sure it keeps that format
		$merchId = $this->session->userdata('logged_in')['merchID'];

		$this->db->query("insert into tbl_company set fileID = '".$fileid."', qbwc_username='".$username."', companyName='".$name."', qbwc_password='".$password."', merchantID='".$merchId."', date_added='".date("Y-m-d H:i:s")."' ");


		$qb_data['appIntegration']    = "2";
		$qb_data['merchantID']    = $merchId;
		$chk_condition = array('merchantID'=>$merchId);
		$app_integration = $this->general_model->get_row_data('app_integration_setting',$chk_condition);
		if(!empty($app_integration)){

			$this->general_model->update_row_data('app_integration_setting',$chk_condition, $qb_data);
			$user = $this->session->userdata('logged_in');
			$user['active_app'] = '2';
			$this->session->set_userdata('logged_in',$user);
		}
		else
		{
			$this->general_model->insert_row('app_integration_setting', $qb_data);
			$user = $this->session->userdata('logged_in');
			$user['active_app'] = '2';
			$this->session->set_userdata('logged_in',$user);

		}



		$qbtype = QUICKBOOKS_TYPE_QBFS;

		$readonly = false;
		// Run every 600 seconds (10 minutes)
		$run_every_n_seconds = 600;

		// Generate the XML file
		$QWC = new QuickBooks_WebConnector_QWC($name, $descrip, $appurl, $appsupport, $username, $fileid, $ownerid, $qbtype, $readonly, $run_every_n_seconds);
		$xml = $QWC->generate();

		// Send as a file download
		$this->load->helper('file');

		$file = "my-quickbooks-wc-file$username.qwc";

		if ( ! write_file(FCPATH."uploads/$file", $xml,'w+'))
		{
			echo 'Error ';  die;
		}
		else
		{
			header('Content-type: text/xml');
			header('Content-Disposition: attachment; filename="my-quickbooks-wc-file.qwc"');
		}

		print($xml);
		exit;
	}





	/**
	 * SOAP endpoint for the Web Connector to connect to
	 */
	public function qbwc()
	{



		$today = date('Y-m-d');
		$this->db->query("delete from quickbooks_user where qb_username ='' ");
		$this->db->query("delete from quickbooks_log where DATE_FORMAT(log_datetime, '%Y-%m-%d') < $today ");
		// Memory limit
		ini_set('memory_limit', $this->config->item('quickbooks_memorylimit'));

		// We need to make sure the correct timezone is set, or some PHP installations will complain
		if (function_exists('date_default_timezone_set'))
		{
			// * MAKE SURE YOU SET THIS TO THE CORRECT TIMEZONE! *
			// List of valid timezones is here: http://us3.php.net/manual/en/timezones.php
			date_default_timezone_set($this->config->item('quickbooks_tz'));
		}

		// Map QuickBooks actions to handler functions
		$map = array(


			QUICKBOOKS_MOD_INVOICE=>array(array( $this, '_updateInvoiceRequest' ), array( $this, '_updateInvoiceResponse' )),
			QUICKBOOKS_ADD_INVOICE=>array(array( $this, '_addInvoiceRequest' ), array( $this, '_addInvoiceResponse' )),
			QUICKBOOKS_DELETE_TXN => array( array($this, '_deleteInvoiceRequest'), array($this, '_deleteInvoiceResponse') ) ,
			QUICKBOOKS_MOD_CUSTOMER => array( array( $this, '_updateCustomerRequest' ), array( $this, '_updateCustomerResponse' ) ),
			QUICKBOOKS_ADD_CUSTOMER => array( array( $this, '_addCustomerRequest' ), array( $this, '_addCustomerResponse' ) ),

			QUICKBOOKS_ADD_RECEIVEPAYMENT => array( array( $this, '_addPaymentRequest' ), array( $this, '_addPaymentResponse' ) ),

			QUICKBOOKS_IMPORT_CUSTOMER => array( array($this,'_quickbooks_customer_import_request'), array($this, '_quickbooks_customer_import_response') ),

			QUICKBOOKS_IMPORT_INVOICE => array(  array($this,'_quickbooks_invoice_import_request'),  array($this, '_quickbooks_invoice_import_response' )),
			QUICKBOOKS_IMPORT_ITEM => array( array($this, '_quickbooks_item_import_request'), array($this, '_quickbooks_item_import_response') ) ,
			QUICKBOOKS_IMPORT_ACCOUNT => array( array($this, '_quickbooks_account_import_request'), array($this, '_quickbooks_account_import_response') ) ,
			QUICKBOOKS_IMPORT_CREDITMEMO => array( array( $this, '_quickbooks_credit_import_request' ), array( $this, '_quickbooks_credit_import_response' ) ),
			QUICKBOOKS_ADD_CREDITMEMO => array( array( $this, '_addCustomerCredit_request' ), array( $this, '_addCustomerCredit_response' ) ),
			QUICKBOOKS_ADD_NONINVENTORYITEM=>array(array( $this, '_add_noninventory_item_request' ), array( $this, '_add_noninventory_item_response' )),
			QUICKBOOKS_MOD_NONINVENTORYITEM=>array(array( $this, '_update_noninventory_item_request' ), array( $this, '_update_noninventory_item_response' )),
			QUICKBOOKS_ADD_SERVICEITEM=>array(array( $this, '_add_service_item_request' ), array( $this, '_add_service_item_response' )),
			QUICKBOOKS_MOD_SERVICEITEM=>array(array( $this, '_update_service_item_request' ), array( $this, '_update_service_item_response' )),
			QUICKBOOKS_ADD_DISCOUNTITEM=>array(array( $this, '_add_item_request_discount' ), array( $this, '_add_item_response_discount' )),
			QUICKBOOKS_MOD_DISCOUNTITEM=>array(array( $this, '_update_item_request_discount' ), array( $this, '_update_item_response_discount' )),
			QUICKBOOKS_ADD_OTHERCHARGEITEM=>array(array( $this, '_add_item_othercharge_request' ), array( $this, '_add_item_othercharge_response' )),
			QUICKBOOKS_MOD_OTHERCHARGEITEM=>array(array( $this, '_update_item_othercharge_request' ), array( $this, '_update_item_othercharge_response' )),
			QUICKBOOKS_ADD_SUBTOTALITEM=>array(array( $this, '_add_item_subtotal_request' ), array( $this, '_add_item_subtotal_response' )),
			QUICKBOOKS_MOD_SUBTOTALITEM=>array(array( $this, '_update_item_subtotal_request' ), array( $this, '_update_item_subtotal_response' )),
			QUICKBOOKS_ADD_PAYMENTITEM=>array(array( $this, '_add_item_payment_request' ), array( $this, '_add_item_payment_response' )),
			QUICKBOOKS_MOD_PAYMENTITEM=>array(array( $this, '_update_item_payment_request' ), array( $this, '_update_item_payment_response' )),
			QUICKBOOKS_ADD_GROUPITEM=>array(array( $this, '_add_item_group_request' ), array( $this, '_add_item_group_response' )),
			QUICKBOOKS_MOD_GROUPITEM=>array(array( $this, '_update_item_group_request' ), array( $this, '_update_item_group_response' )),




		);





		// Catch all errors that QuickBooks throws with this function 
		$errmap = array(
			500 => array($this,'_quickbooks_error_e500_notfound'), 			// Catch errors caused by searching for things not present in QuickBooks
			1 => array($this, '_quickbooks_error_e500_notfound'),
			'*' => array($this,'_quickbooks_error_catchall'), 				// Catch any other errors that might occur
		);

		// Call this method whenever the Web Connector connects
		$hooks = array(
			QuickBooks_WebConnector_Handlers::HOOK_LOGINSUCCESS => array( array( $this, '_loginSuccess' ) ) , 	// call this whenever a successful login occurs

		);

		// An array of callback options
		$callback_options = array();

		// Logging level
		$log_level = $this->config->item('quickbooks_loglevel');

		// What SOAP server you're using 
		//$soapserver = QUICKBOOKS_SOAPSERVER_PHP;		
		$soapserver = QUICKBOOKS_SOAPSERVER_BUILTIN;		// A pure-PHP SOAP server (no PHP ext/soap extension required, also makes debugging easier)

		$soap_options = array(
		);

		$handler_options = array(
			'authenticate' => array($this,'_quickbooks_custom_auth'),
			'deny_concurrent_logins' => false,
			'deny_reallyfast_logins' => false,
		);


		// See the comments in the QuickBooks/Server/Handlers.php file

		$driver_options = array(		// See the comments in the QuickBooks/Driver/<YOUR DRIVER HERE>.php file ( i.e. 'Mysql.php', etc. )
			'max_log_history' => 32000,	// Limit the number of quickbooks_log entries to 1024
			'max_queue_history' => 1024, 	// Limit the number of *successfully processed* quickbooks_queue entries to 64
		);

		// Build the database connection string


		$dsn = QB_QUICKBOOKS_DSN;

		// Check to make sure our database is set up 
		if (!QuickBooks_Utilities::initialized($dsn))
		{
			// Initialize creates the neccessary database schema for queueing up requests and logging
			QuickBooks_Utilities::initialize($dsn);

			// This creates a username and password which is used by the Web Connector to authenticate

			QuickBooks_Utilities::createUser($dsn, $user, $pass);
		}

		// Set up our queue singleton
		QuickBooks_WebConnector_Queue_Singleton::initialize($dsn);

		// Create a new server and tell it to handle the requests
		// __construct($dsn_or_conn, $map, $errmap = array(), $hooks = array(), $log_level = QUICKBOOKS_LOG_NORMAL, $soap = QUICKBOOKS_SOAPSERVER_PHP, $wsdl = QUICKBOOKS_WSDL, $soap_options = array(), $handler_options = array(), $driver_options = array(), $callback_options = array()
		//echo QUICKBOOKS_WSDL ;  die;
		$Server = new QuickBooks_WebConnector_Server($dsn, $map, $errmap, $hooks, $log_level, $soapserver, QUICKBOOKS_WSDL, $soap_options, $handler_options, $driver_options, $callback_options);


		$response = $Server->handle(true, true);
		//print_r($response);




	}



	function _quickbooks_custom_auth($username, $password, &$qb_company_file)
	{

		$con = array('qbwc_username'=>$username);

		$query = $this->db->select('*')->from('tbl_company')->where($con)->get();
		$lastquery = $this->db->last_query();

		if($query->num_rows()>0 ) {
			$user = $query->row_array();
			$user1 = $user['qbwc_username'];
			$company = $user['companyName'];
			$qb_company_file_name = $company.'.qbw';
		} else {
			return false;
		}

		QuickBooks_Utilities::log(QB_QUICKBOOKS_DSN, 'Validate Company data>>>>>>>>>>>>>>>>>>>>>>>>>>>: ' .$user1.  print_r($qb_company_file, true));




		if ($username == $user1 and $password ==  $user['qbwc_password'])
		{
			// Use this company file and auth successfully

			//  $qb_company_file = 'C:\Users\Public\Documents\Intuit\QuickBooks\Company Files\\'.$qb_company_file_name;
			return true;
		}

		// Login failure
		return false;
	}


	/**
	 * Catch and handle errors from QuickBooks
	 */
	public function _catchallErrors($requestID, $user, $action, $ID, $extra, &$err, $xml, $errnum, $errmsg)
	{
		return false;
	}

	/**
	 * Whenever the Web Connector connects, do something (e.g. queue some stuff up if you want to)
	 */
	public function _loginSuccess($requestID, $user, $hook, &$err, $hook_data, $callback_config)
	{
		//	return true;

		$Queue = QuickBooks_WebConnector_Queue_Singleton::getInstance();
		$date = '1983-06-15 12:01:01';

		// Do the same for customers



		// $this->db->query("delete  from quickbooks_config");

		if (! ($this->_quickbooks_get_last_run($user, QUICKBOOKS_IMPORT_CUSTOMER)))
		{
			$this->_quickbooks_set_last_run($user, QUICKBOOKS_IMPORT_CUSTOMER, $date);
		}


		if (! ($this->_quickbooks_get_last_run($user, QUICKBOOKS_IMPORT_ITEM)))
		{
			$this->_quickbooks_set_last_run($user, QUICKBOOKS_IMPORT_ITEM, $date);
		}
		// Comment

		// Set up the invoice imports

		if (!	($this->_quickbooks_get_last_run($user, QUICKBOOKS_IMPORT_INVOICE)))
		{

			$this->_quickbooks_set_last_run($user, QUICKBOOKS_IMPORT_INVOICE, $date);
		}


		if (! ($this->_quickbooks_get_last_run($user, QUICKBOOKS_IMPORT_ACCOUNT)))
		{
			$this->_quickbooks_set_last_run($user, QUICKBOOKS_IMPORT_ACCOUNT, $date);
		}


		if (! ($this->_quickbooks_get_last_run($user, QUICKBOOKS_IMPORT_CREDITMEMO)))
		{
			$this->_quickbooks_set_last_run($user, QUICKBOOKS_IMPORT_CREDITMEMO, $date);
		}


		/*
                if (!	($this->_quickbooks_get_last_run($user, QUICKBOOKS_IMPORT_RECEIVEPAYMENT)))
            {

                    $this->_quickbooks_set_last_run($user, QUICKBOOKS_IMPORT_RECEIVEPAYMENT, $date);
            }
            */
		//	_quickbooks_set_last_run($user, QUICKBOOKS_IMPORT_CUSTOMER, $date);

		// Make sure the requests get queued up
		//$Queue->enqueue(QUICKBOOKS_IMPORT_SALESORDER, 1, QB_PRIORITY_SALESORDER);
		$Queue->enqueue(QUICKBOOKS_IMPORT_INVOICE, 1, QB_PRIORITY_INVOICE,'', $user);


		$Queue->enqueue(QUICKBOOKS_IMPORT_CUSTOMER, 1, QB_PRIORITY_CUSTOMER,'', $user);
		/*$Queue->enqueue(QUICKBOOKS_IMPORT_PURCHASEORDER, 1, QB_PRIORITY_PURCHASEORDER); */
		$Queue->enqueue(QUICKBOOKS_IMPORT_ITEM, 1, QB_PRIORITY_ITEM,'', $user);
		$Queue->enqueue(QUICKBOOKS_IMPORT_ACCOUNT, 1, 10,'', $user);
		$Queue->enqueue(QUICKBOOKS_IMPORT_CREDITMEMO, 1, 10,'', $user);
		// $Queue->enqueue(QUICKBOOKS_IMPORT_RECEIVEPAYMENT, 1, 10,'', $user);

	}




	/**
	 * Get the last date/time the QuickBooks sync ran
	 *
	 * @param string $user		The web connector username
	 * @return string			A date/time in this format: "yyyy-mm-dd hh:ii:ss"
	 */
	public function _quickbooks_get_last_run($user, $action)
	{
		$type = null;
		$opts = null;
		return QuickBooks_Utilities::configRead(QB_QUICKBOOKS_DSN, $user, md5(__FILE__), QB_QUICKBOOKS_CONFIG_LAST . '-' . $action, $type, $opts);
	}

	/**
	 * Set the last date/time the QuickBooks sync ran to NOW
	 *
	 * @param string $user
	 * @return boolean
	 */

	public	 function _quickbooks_set_last_run($user, $action, $force = null)
	{
		$value = date('Y-m-d') . 'T' . date('H:i:s');
		$value = date('Y-m-d') . 'T' . date('00:00:00');
		if ($force)
		{
			$value = date('Y-m-d', strtotime($force)) . 'T' . date('H:i:s', strtotime($force));
		}



		return QuickBooks_Utilities::configWrite(QB_QUICKBOOKS_DSN, $user, md5(__FILE__), QB_QUICKBOOKS_CONFIG_LAST . '-' . $action, $value);
	}


	/**
	 *
	 *
	 */
	public function _quickbooks_get_current_run($user, $action)
	{
		$type = null;
		$opts = null;
		return QuickBooks_Utilities::configRead(QB_QUICKBOOKS_DSN, $user, md5(__FILE__), QB_QUICKBOOKS_CONFIG_CURR . '-' . $action, $type, $opts);
	}

	/**
	 *
	 *
	 */
	public function _quickbooks_set_current_run($user, $action, $force = null)
	{
		$value = date('Y-m-d') . 'T' . date('H:i:s');

		if ($force)
		{
			$value = date('Y-m-d', strtotime($force)) . 'T' . date('H:i:s', strtotime($force));
		}

		return QuickBooks_Utilities::configWrite(QB_QUICKBOOKS_DSN, $user, md5(__FILE__), QB_QUICKBOOKS_CONFIG_CURR . '-' . $action, $value);
	}





	/**
	 * Handle a 500 not found error from QuickBooks
	 *
	 * Instead of returning empty result sets for queries that don't find any
	 * records, QuickBooks returns an error message. This handles those error
	 * messages, and acts on them by adding the missing item to QuickBooks.
	 */
	public	function _quickbooks_error_e500_notfound($requestID, $user, $action, $ID, $extra, &$err, $xml, $errnum, $errmsg)
	{
		$Queue = QuickBooks_WebConnector_Queue_Singleton::getInstance();

		if ($action == QUICKBOOKS_IMPORT_INVOICE)
		{
			return true;
		}
		else if ($action == QUICKBOOKS_IMPORT_CUSTOMER)
		{
			return true;
		}
		else if ($action == QUICKBOOKS_IMPORT_SALESORDER)
		{
			return true;
		}
		else if ($action == QUICKBOOKS_IMPORT_ITEM)
		{
			return true;
		}
		else if ($action == QUICKBOOKS_IMPORT_ACCOUNT)
		{
			return true;
		}
		else if ($action == QUICKBOOKS_IMPORT_PURCHASEORDER)
		{
			return true;
		}
		else if ($action == QUICKBOOKS_IMPORT_CREDITMEMO)
		{

			return true;
		}
		else if ($action == QUICKBOOKS_IMPORT_RECEIVEPAYMENT)
		{
			return true;
		}

		return false;
	}
	/**
	 * Catch any errors that occur
	 *
	 * @param string $requestID
	 * @param string $action
	 * @param mixed $ID
	 * @param mixed $extra
	 * @param string $err
	 * @param string $xml
	 * @param mixed $errnum
	 * @param string $errmsg
	 * @return void
	 */
	public	function _quickbooks_error_catchall($requestID, $user, $action, $ID, $extra, &$err, $xml, $errnum, $errmsg)
	{
		$message = '';
		$message .= 'Request ID: ' . $requestID . "\r\n";
		$message .= 'User: ' . $user . "\r\n";
		$message .= 'Action: ' . $action . "\r\n";
		$message .= 'ID: ' . $ID . "\r\n";
		$message .= 'Extra: ' . print_r($extra, true) . "\r\n";
		//$message .= 'Error: ' . $err . "\r\n";
		$message .= 'Error number: ' . $errnum . "\r\n";
		$message .= 'Error message: ' . $errmsg . "\r\n";


		if($action=='CustomerAdd' ||  $action=='CustomerMod')
		{
			$this->db->where(array('customerID'=>$ID));
			$this->db->update('tbl_custom_customer',array('qb_status'=>3));

		}
		if($action=='ItemGroupMod' ||  $action=='ItemGroupAdd')
		{
			$this->db->where(array('productID'=>$ID));
			$this->db->update('tbl_custom_product',array('qb_status'=>3));

		}
		if($action=='ItemPaymentAdd' ||  $action=='ItemPaymentMod')
		{
			$this->db->where(array('productID'=>$ID));
			$this->db->update('tbl_custom_product',array('qb_status'=>3));

		}
		if($action=='ItemDiscountAdd' ||  $action=='ItemDiscountMod')
		{
			$this->db->where(array('productID'=>$ID));
			$this->db->update('tbl_custom_product',array('qb_status'=>3));

		}
		if($action=='ItemNonInventoryMod' ||  $action=='ItemNonInventoryAdd')
		{
			$this->db->where(array('productID'=>$ID));
			$this->db->update('tbl_custom_product',array('qb_status'=>3));

		}
		if($action=='ItemOtherChargeMod' ||  $action=='ItemOtherChargeAdd')
		{
			$this->db->where(array('productID'=>$ID));
			$this->db->update('tbl_custom_product',array('qb_status'=>3));

		}
		if($action=='ItemSubtotalMod' ||  $action=='ItemSubtotalAdd')
		{
			$this->db->where(array('productID'=>$ID));
			$this->db->update('tbl_custom_product',array('qb_status'=>3));

		}

		if($action=='ItemServiceMod' ||  $action=='ItemServiceAdd')
		{
			$this->db->where(array('productID'=>$ID));
			$this->db->update('tbl_custom_product',array('qb_status'=>3));

		}


		if($action=='InvoiceMod' ||  $action=='InvoiceAdd')
		{
			$this->db->where(array('insertInvID'=>$ID));
			$this->db->update('tbl_custom_invoice',array('qb_status'=>3));

		}
		if($action=='TxnDel')
		{
			$this->db->where(array('delID'=>$ID));
			$this->db->update('tbl_del_transactions',array('qb_status'=>3));

		}
		$from_email = "your@example.com";
		$to_email = QB_QUICKBOOKS_MAILTO;

		//Load email library
		$this->load->library('email');

		$this->email->from($from_email, 'Quickbook Demo');
		$this->email->to($to_email);
		$this->email->subject('QuickBooks error occured!');
		$this->email->message($message);

		//Send mail
		if($this->email->send());



	}













}
