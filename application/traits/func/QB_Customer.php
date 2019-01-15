<?php
/**
 * Created by PhpStorm.
 * User: jitendra
 * Date: 1/15/18
 * Time: 10:01 AM
 */

namespace func;

trait QB_Customer
{
	protected $CI = null;
	/**
	 * Simplified method to obtain a reference to CI controller.
	 */
	public function __construct ()
	{
		# CI INSTANCE
		$this->CI =& get_instance();
		# QuickBooks config
		$this->CI->load->config('config');
	}

	public function abc(){
	    die('I am defined in application/traits/func/QB_Customer.php and using into QB Controller');
    }

	public function _updateCustomerRequest($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $version, $locale)
	{
		// Do something here to load data using your model
		$ss='';
		$query = $this->CI->db->query("Select c.*,comp.qbwc_username as user  from tbl_custom_customer c inner join tbl_company comp on c.companyID=comp.id    where 	customerID =$ID ");

		$data  =   $query->row_array();

		if($data['country'] > 0){
			$data['country_name'] =$this->CI->db->query('Select country_name from country where country_id = "'.$data['country'].'" ')->row_array()['country_name'];
			$ss.= $this->CI->db->last_query();
		}
		//QuickBooks_Utilities::log(QB_QUICKBOOKS_DSN, 'Request RefererereOOOOOOO>>>>>>>>>>>>>>'.  $ID.': '.print_r($ss , true));

		// Build the qbXML request from data

		if($data['isActive']=='false'){

			$xml = '<?xml version="1.0" encoding="utf-8"?>
		<?qbxml version="'.$version.'"?>
		<QBXML>
			<QBXMLMsgsRq onError="stopOnError">
				<CustomerModRq requestID="' . $requestID . '">
				  <CustomerMod> <!-- required -->
					<ListID >'.$data['ListID'].'</ListID> <!-- required -->
					<EditSequence >'.$data['EditSequence'].'</EditSequence> <!-- required -->
					<Name>'.$data['fullName'].'</Name>
					<IsActive >'.$data['isActive'].'</IsActive> 

					</CustomerMod>
				</CustomerModRq>
			</QBXMLMsgsRq>
		</QBXML>';
		}else{

			$xml ='<?xml version="1.0" encoding="utf-8"?>
		<?qbxml version="'.$version.'"?>
		<QBXML>
			<QBXMLMsgsRq onError="stopOnError">
				<CustomerModRq requestID="' . $requestID . '">
				  <CustomerMod> <!-- required -->
					<ListID >'.$data['ListID'].'</ListID> <!-- required -->
					<EditSequence >'.$data['EditSequence'].'</EditSequence> <!-- required -->
					<Name>'.$data['fullName'].'</Name>
					<IsActive >'.$data['isActive'].'</IsActive> 
					<CompanyName>'.$data['companyName'].'</CompanyName> 
					<FirstName>'.$data['firstName'].'</FirstName>
				    <LastName>'.$data['lastName'].'</LastName>';
			if($data['address1']!='' && $data['zipcode']!='' && $data['city']!='' && $data['state']!=''  && $data['country']>0 ){

				$xml.='<BillAddress> 
    					<Addr1>'.$data['address1'].'</Addr1>
    					<Addr2 >'.$data['address2'].'</Addr2> 				
    					<City>'.$data['city'].'</City>
    					<State>'.$data['state'].'</State>
    					<PostalCode>'.$data['zipcode'].'</PostalCode>
    					<Country>'.$data['country_name'].'</Country>
					</BillAddress>';
			}
			$xml.='<Phone>'.$data['phoneNumber'].'</Phone>
					<Email>'.$data['userEmail'].'</Email>
					<Contact>'.$data['userEmail'].'</Contact>
					</CustomerMod>
				</CustomerModRq>
			</QBXMLMsgsRq>
		</QBXML>';
		}

//	QuickBooks_Utilities::log(QB_QUICKBOOKS_DSN, 'Request Customer Referererere>>>>>>>>>>>>>>>'.  $ID.': '.print_r($xml , true));
		return $xml;


	}
	/**
	 * Handle a response from QuickBooks indicating a new customer has been added
	 */
	public function _updateCustomerResponse($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $xml, $idents)
	{
		$errnum = 0;
		$errmsg = '';
		$Parser = new QuickBooks_XML_Parser($xml);



		if ($Doc = $Parser->parse($errnum, $errmsg))
		{
			$Root = $Doc->getRoot();
			$List = $Root->getChildAt('QBXML/QBXMLMsgsRs/CustomerModRs');
			//   QuickBooks_Utilities::log(QB_QUICKBOOKS_DSN, 'DDDDDDDDDDD ************************Referererere<<<<<<<<<<>>>>>>>>>>>>>>>>>>>' .  ': ' . print_r($List, true));
			foreach ($List->children() as $Customer)
			{
				//   QuickBooks_Utilities::log(QB_QUICKBOOKS_DSN, 'DDDDDDDDDDD ************************Referererere<<<<<<<<<<>>>>>>>>>>>>>>>>>>>' .  ': ' . print_r($Customer, true));
				if($Customer->getChildDataAt('CustomerRet IsActive')=='true'){ $st='1'; }else{ $st='0'; }
				$arr = array(
					'ListID'                 => $Customer->getChildDataAt('CustomerRet ListID'),
					'EditSequence'           => $Customer->getChildDataAt('CustomerRet EditSequence'),
					'TimeCreated'  			 => $Customer->getChildDataAt('CustomerRet TimeCreated'),
					'TimeModified' 			 => $Customer->getChildDataAt('CustomerRet TimeModified'),
					'companyName' 			 => $Customer->getChildDataAt('CustomerRet CompanyName'),
					'FullName'				 => $Customer->getChildDataAt('CustomerRet FullName'),
					'FirstName'   			 => $Customer->getChildDataAt('CustomerRet FirstName'),
					'MiddleName' 			 => $Customer->getChildDataAt('CustomerRet MiddleName'),
					'LastName' 				 => $Customer->getChildDataAt('CustomerRet LastName'),
					'Contact' 				 => $Customer->getChildDataAt('CustomerRet Email'),
					'Phone'					 => $Customer->getChildDataAt('CustomerRet Phone'),
					'ShipAddress_Addr1'      => $Customer->getChildDataAt('CustomerRet BillAddress Addr1'),
					'ShipAddress_Addr2' 	 => $Customer->getChildDataAt('CustomerRet BillAddress Addr2'),
					'ShipAddress_City' 	     => $Customer->getChildDataAt('CustomerRet BillAddress City'),
					'ShipAddress_State'		 => $Customer->getChildDataAt('CustomerRet BillAddress State'),
					'ShipAddress_PostalCode' => $Customer->getChildDataAt('CustomerRet BillAddress PostalCode'),
					'IsActive'               =>$Customer->getChildDataAt('CustomerRet IsActive'),
					'customerStatus'         => $st,
				);

				$lsID     =    	$arr['ListID'];


				$this->CI->db->where(array('ListID'=>$lsID));
				$this->CI->db->update('qb_test_customer', $arr);
				$this->CI->db->where(array('customerID'=>$ID));
				$this->CI->db->update('tbl_custom_customer', array('qb_status'=>5, 'updatedAt'=>date('Y-m-d H:i:s')));



			}

		}



		// Do something here to record that the data was added to QuickBooks successfully
		//	echo "Customer is added Successfully";
		return true;

	}


	/**
	 * Issue a request to QuickBooks to add a customer
	 */
	public function _addCustomerRequest($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $version, $locale)
	{
		// Do something here to load data using your model

		$query = $this->CI->db->query("Select  c.*,cr.country_name as country_name, cr1.country_name as scountry_name,   comp.qbwc_username as user  from tbl_custom_customer c inner join tbl_company comp on c.companyID=comp.id  left join country cr on c.country= cr.country_id  left join country cr1 on c.ship_country= cr1.country_id  where 	customerID =$ID ");

		$data  =   $query->row_array();

		//	QuickBooks_Utilities::log(QB_QUICKBOOKS_DSN, 'Request Customer Referererere>>>>>>>>>>>>>>>'.  $ID.': '.print_r($data , true));

		// Build the qbXML request from data

		$xml = '<?xml version="1.0" encoding="utf-8"?>
		<?qbxml version="' . $version . '"?>
		<QBXML>
			<QBXMLMsgsRq onError="stopOnError">
				<CustomerAddRq requestID="' . $requestID . '">
					<CustomerAdd>
					<Name>'.$data['fullName'].'</Name>
    				<CompanyName>'.$data['companyName'].'</CompanyName>
    				<FirstName>'.$data['firstName'].'</FirstName>
    				<LastName>'.$data['lastName'].'</LastName>
    				<BillAddress>
    				        	<Addr1>'.$data['address1'].'</Addr1>
    							<Addr2>'.$data['address2'].'</Addr2>
    							<City>'.$data['city'].'</City>
    							<State>'.$data['state'].'</State>
    							<PostalCode>'.$data['zipcode'].'</PostalCode>
    							<Country>'.$data['country_name'].'</Country>
    				</BillAddress>
                    <ShipAddress>
    				        	<Addr1>'.$data['ship_address1'].'</Addr1>
    							<Addr2>'.$data['ship_address2'].'</Addr2>
    							<City>'.$data['ship_city'].'</City>
    							<State>'.$data['ship_state'].'</State>
    							<PostalCode>'.$data['ship_zipcode'].'</PostalCode>
    							<Country>'.$data['scountry_name'].'</Country>
    				</ShipAddress>
    				<Phone>'.$data['phoneNumber'].'</Phone>
    				<Email>'.$data['userEmail'].'</Email>
    		     	<Contact>'.$data['userEmail'].'</Contact>
					</CustomerAdd>
				</CustomerAddRq>
			</QBXMLMsgsRq>
		</QBXML>';


		return $xml;


	}

	/**
	 * Handle a response from QuickBooks indicating a new customer has been added
	 */
	public function _addCustomerResponse($requestID, $user, $action, $ID, $extr, &$err, $last_action_time, $last_actionident_time, $xml, $idents)
	{
		$errnum = 0;
		$errmsg = '';
		$Parser = new QuickBooks_XML_Parser($xml);
		//$this->general_model->insert_row('dummy_table_do_not_use_it', $xml);
//	QuickBooks_Utilities::log(QB_QUICKBOOKS_DSN, 'Importing customer Referererere>>>>>>>>>>>>>>>' .  ': ' . print_r($Parser, true));
//    QuickBooks_Utilities::log(QB_QUICKBOOKS_DSN, 'Importing customer Referererere>>>>>>>>>>>>>>>' .  ': ' . print_r( $Parser->parse($errnum, $errmsg), true));
		if ($Doc = $Parser->parse($errnum, $errmsg))
		{
			$Root = $Doc->getRoot();
			$List = $Root->getChildAt('QBXML/QBXMLMsgsRs/CustomerAddRs');

			$con = array('qbwc_username'=>$user);
			$query = $this->CI->db->select('*')->from('tbl_company')->where($con)->get();
			$lastquery = $this->CI->db->last_query();

			if($query->num_rows()>0 ) {

				$user_data    = $query->row_array();
				$username     = $user_data['qbwc_username'];
				$companyID    = $user_data['id'] ;
			}

			foreach ($List->children() as $Customer)
			{
				$this->CI->db->where(array('customerID'=>$ID));

				$this->CI->db->update('tbl_custom_customer', array('qb_status'=>5, 'updatedAt'=>date('Y-m-d H:i:s')));

				if($Customer->getChildDataAt('CustomerRet IsActive')=='true'){ $st='1'; }else{ $st='0'; }
				$arr = array(
					'ListID' => $Customer->getChildDataAt('CustomerRet ListID'),
					'TimeCreated'  			 => $Customer->getChildDataAt('CustomerRet TimeCreated'),
					'TimeModified' 			 => $Customer->getChildDataAt('CustomerRet TimeModified'),
					'CompanyName' 			 => $Customer->getChildDataAt('CustomerRet CompanyName'),
					'FullName'				 => $Customer->getChildDataAt('CustomerRet FullName'),
					'FirstName'   			 => $Customer->getChildDataAt('CustomerRet FirstName'),
					'MiddleName' 			 => $Customer->getChildDataAt('CustomerRet MiddleName'),
					'LastName' 				 => $Customer->getChildDataAt('CustomerRet LastName'),
					'Contact' 				 => $Customer->getChildDataAt('CustomerRet Email'),
					'Phone'					 => $Customer->getChildDataAt('CustomerRet Phone'),
					'BillingAddress_Addr1'      => $Customer->getChildDataAt('CustomerRet BillAddress Addr1'),
					'BillingAddress_Addr2' 	 => $Customer->getChildDataAt('CustomerRet BillAddress Addr2'),
					'BillingAddress_City' 	     => $Customer->getChildDataAt('CustomerRet BillAddress City'),
					'BillingAddress_State'		 => $Customer->getChildDataAt('CustomerRet BillAddress State'),
					'BillingAddress_Country'	 => $Customer->getChildDataAt('CustomerRet BillAddress Country'),
					'BillingAddress_PostalCode' => $Customer->getChildDataAt('CustomerRet BillAddress PostalCode'),
					'ShipAddress_Addr1'      => $Customer->getChildDataAt('CustomerRet ShipAddress Addr1'),
					'ShipAddress_Addr2' 	 => $Customer->getChildDataAt('CustomerRet ShipAddress Addr2'),
					'ShipAddress_City' 	     => $Customer->getChildDataAt('CustomerRet ShipAddress City'),
					'ShipAddress_State'		 => $Customer->getChildDataAt('CustomerRet ShipAddress State'),
					'ShipAddress_Country'	 => $Customer->getChildDataAt('CustomerRet ShipAddress Country'),
					'ShipAddress_PostalCode' => $Customer->getChildDataAt('CustomerRet ShipAddress PostalCode'),
					'EditSequence'            => $Customer->getChildDataAt('CustomerRet EditSequence'),
					'IsActive'                =>$Customer->getChildDataAt('CustomerRet IsActive'),
					'customerStatus'          => $st,
					'company_qb_username'     => $username,
					'companyID' 			  => $companyID,
				);

				//QuickBooks_Utilities::log(QB_QUICKBOOKS_DSN, 'Importing customer >>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>' . $arr['FullName'] . ': ' . print_r($arr, true));

				foreach ($arr as $key => $value)
				{
					$arr[$key] = $this->CI->db->escape_str($value);
				}

				$this->CI->db->query("
				REPLACE INTO
					qb_test_customer
				(
					" . implode(", ", array_keys($arr)) . "
				) VALUES (
					'" . implode("', '", array_values($arr)) . "'
				)");
			}


		}



		return true;
	}


	/**
	 * Build a request to import customers already in QuickBooks into our application
	 */
	public function _quickbooks_customer_import_request($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $version, $locale)
	{
		// Iterator support (break the result set into small chunks)
		$attr_iteratorID = '';
		$attr_iterator = ' iterator="Start" ';
		if (empty($extra['iteratorID']))
		{
			// This is the first request in a new batch
			$last = $this->_quickbooks_get_last_run($user, $action);

			$this->_quickbooks_set_last_run($user, $action);			// Update the last run time to NOW()

			// Set the current run to $last
			$this->_quickbooks_set_current_run($user, $action, $last);
		}
		else
		{
			// This is a continuation of a batch
			$attr_iteratorID = ' iteratorID="' . $extra['iteratorID'] . '" ';
			$attr_iterator   = ' iterator="Continue" ';

			$last = $this->_quickbooks_get_current_run($user, $action);
		}

		// Build the request
		$xml = '<?xml version="1.0" encoding="utf-8"?>
		<?qbxml version="' . $version . '"?>
		<QBXML>
			<QBXMLMsgsRq onError="stopOnError">
				<CustomerQueryRq ' . $attr_iterator . ' ' . $attr_iteratorID . ' requestID="' . $requestID . '">
					<MaxReturned>' . QB_QUICKBOOKS_MAX_RETURNED . '</MaxReturned>
					<FromModifiedDate>' . $last . '</FromModifiedDate>
					<OwnerID>0</OwnerID>
				</CustomerQueryRq>	
			</QBXMLMsgsRq>
		</QBXML>';

		return $xml;
	}
	/**
	 * Handle a response from QuickBooks
	 */
	public function _quickbooks_customer_import_response($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $xml, $idents)
	{
		if (!empty($idents['iteratorRemainingCount']))
		{
			// Queue up another request

			$Queue = QuickBooks_WebConnector_Queue_Singleton::getInstance();
			$Queue->enqueue(QUICKBOOKS_IMPORT_CUSTOMER, null, QB_PRIORITY_CUSTOMER, array( 'iteratorID' => $idents['iteratorID'] ),$user);
		}


		// The following example shows how to use the built-in XML parser to parse
		//	the response and stuff it into a database.

		// Import all of the records
		$errnum = 0;
		$errmsg = '';
		$Parser = new QuickBooks_XML_Parser($xml);
		//$this->general_model->insert_row('dummy_table_do_not_use_it', $xml);
//	QuickBooks_Utilities::log(QB_QUICKBOOKS_DSN, 'Importing customer Referererere>>>>>>>>>>>>>>>' .  ': ' . print_r($Parser, true));
		if ($Doc = $Parser->parse($errnum, $errmsg))
		{
			$Root = $Doc->getRoot();
			$List = $Root->getChildAt('QBXML/QBXMLMsgsRs/CustomerQueryRs');

			$con = array('qbwc_username'=>$user);
			$query = $this->CI->db->select('*')->from('tbl_company')->where($con)->get();
			$lastquery = $this->CI->db->last_query();

			if($query->num_rows()>0 ) {

				$user_data    = $query->row_array();
				$username     = $user_data['qbwc_username'];
				$companyID    = $user_data['id'] ;
				$merchantID    = $user_data['merchantID'] ;
			}

			foreach ($List->children() as $Customer)
			{
				if($Customer->getChildDataAt('CustomerRet IsActive')=='true'){ $st='1'; }else{ $st='0'; }
				$arr = array(
					'ListID' => $Customer->getChildDataAt('CustomerRet ListID'),
					'TimeCreated'  			 => $Customer->getChildDataAt('CustomerRet TimeCreated'),
					'TimeModified' 			 => $Customer->getChildDataAt('CustomerRet TimeModified'),
					'CompanyName' 			 => $Customer->getChildDataAt('CustomerRet CompanyName'),
					'FullName'				 => $Customer->getChildDataAt('CustomerRet FullName'),
					'FirstName'   			 => $Customer->getChildDataAt('CustomerRet FirstName'),
					'MiddleName' 			 => $Customer->getChildDataAt('CustomerRet MiddleName'),
					'LastName' 				 => $Customer->getChildDataAt('CustomerRet LastName'),
					'Contact' 				 => $Customer->getChildDataAt('CustomerRet Email'),
					'Phone'					 => $Customer->getChildDataAt('CustomerRet Phone'),
					'BillingAddress_Addr1'      => $Customer->getChildDataAt('CustomerRet BillAddress Addr1'),
					'BillingAddress_Addr2' 	 => $Customer->getChildDataAt('CustomerRet BillAddress Addr2'),
					'BillingAddress_City' 	     => $Customer->getChildDataAt('CustomerRet BillAddress City'),
					'BillingAddress_State'		 => $Customer->getChildDataAt('CustomerRet BillAddress State'),
					'BillingAddress_Country'	 => $Customer->getChildDataAt('CustomerRet BillAddress Country'),
					'BillingAddress_PostalCode' => $Customer->getChildDataAt('CustomerRet BillAddress PostalCode'),
					'ShipAddress_Addr1'      => $Customer->getChildDataAt('CustomerRet ShipAddress Addr1'),
					'ShipAddress_Addr2' 	 => $Customer->getChildDataAt('CustomerRet ShipAddress Addr2'),
					'ShipAddress_City' 	     => $Customer->getChildDataAt('CustomerRet ShipAddress City'),
					'ShipAddress_State'		 => $Customer->getChildDataAt('CustomerRet ShipAddress State'),
					'ShipAddress_Country'	 => $Customer->getChildDataAt('CustomerRet ShipAddress Country'),
					'ShipAddress_PostalCode' => $Customer->getChildDataAt('CustomerRet ShipAddress PostalCode'),
					'EditSequence'      => $Customer->getChildDataAt('CustomerRet EditSequence'),
					'IsActive'              =>$Customer->getChildDataAt('CustomerRet IsActive'),
					'customerStatus'          => $st,
					'company_qb_username'    => $username,
					'companyID' 			 => $companyID,
					'qbmerchantID'           => $merchantID,
				);

				//	QuickBooks_Utilities::log(QB_QUICKBOOKS_DSN, 'Importing customer >>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>' . $arr['FullName'] . ': ' . print_r($arr, true));

				foreach ($arr as $key => $value)
				{
					$arr[$key] = $this->CI->db->escape_str($value);
				}

				// Store the invoices in MySQL



				$this->CI->db->query("
				REPLACE INTO
					qb_test_customer
				(
					" . implode(", ", array_keys($arr)) . "
				) VALUES (
					'" . implode("', '", array_values($arr)) . "'
				)");
			}
		}

		return true;
	}

}


