<?php
/**
 * Created by PhpStorm.
 * User: jitendra
 * Date: 1/15/18
 * Time: 9:42 AM
 */

namespace func;

trait QB_invoice
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
		$this->CI->load->config('quickbooks');
	}

	public function _updateInvoiceRequest($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $version, $locale)
	{


		$query = $this->CI->db->query("Select * from tbl_custom_invoice where insertInvID='" . $ID . "'  ");
		$data = $query->row_array();
		//$query1 = $this->CI->db->last_query();
		//	 $query1 = $this->CI->db->query("Select * from  tbl_subscription_invoice_item where invoiceDataID='".$ID."'  ");
		//	$data_items   = 	$query1->result_array();

		$xml = '<?xml version="1.0" encoding="utf-8"?>
		<?qbxml version="' . $version . '"?>
		<QBXML>
		  <QBXMLMsgsRq onError="stopOnError">
			  <InvoiceModRq requestID="' . $requestID . '">
			  <InvoiceMod >
			    <TxnID>' . $data['TxnID'] . '</TxnID>
				<EditSequence>' . $data['EditSequence'] . '</EditSequence>
				<CustomerRef>
			    	<ListID>' . $data['Customer_ListID'] . '</ListID>
				  <FullName>' . $data['Customer_FullName'] . '</FullName>
				</CustomerRef>
				<TxnDate>' . date('Y-m-d', strtotime($data['TimeModified'])) . '</TxnDate>
				<RefNumber>' . $data['RefNumber'] . '</RefNumber>
			
			
				<BillAddress>
				  <Addr1>' . $data['ShipAddress_Addr1'] . '</Addr1>
				  <City>' . $data['ShipAddress_City'] . '</City>
				  <State>' . $data['ShipAddress_State'] . '</State>
				  <PostalCode>' . $data['ShipAddress_PostalCode'] . '</PostalCode>
				  <Country>' . $data['ShipAddress_Country'] . '</Country>
				</BillAddress>
			  <DueDate>' . date('Y-m-d', strtotime($data['DueDate'])) . '</DueDate>
			   <ShipDate>' . date('Y-m-d', strtotime($data['DueDate'])) . '</ShipDate>
			      <Memo>This is a update</Memo>';

		$xml .= '</InvoiceMod>
			</InvoiceModRq>
		
		  </QBXMLMsgsRq>
		</QBXML>';


		QuickBooks_Utilities::log(QB_QUICKBOOKS_DSN, 'Invoice Update REQUEST  Datadgjdgjdgjdgdjgd #: ' . print_r($xml, true));

		return $xml;
	}

	public function _updateInvoiceResponse($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $xml, $idents)
	{
		// Do something here to record that the data was added to QuickBooks successfully
		$errnum = 0;
		$errmsg = '';
		$Parser = new QuickBooks_XML_Parser($xml);
		if ($Doc = $Parser->parse($errnum, $errmsg)) {
			$Root = $Doc->getRoot();
			$List = $Root->getChildAt('QBXML/QBXMLMsgsRs/InvoiceModRs');

			foreach ($List->children() as $Invoice) {
				$arr = array(
					'TxnID' => $Invoice->getChildDataAt('InvoiceRet TxnID'),
					'TimeCreated' => $Invoice->getChildDataAt('InvoiceRet TimeCreated'),
					'TimeModified' => $Invoice->getChildDataAt('InvoiceRet TimeModified'),
					'RefNumber' => $Invoice->getChildDataAt('InvoiceRet RefNumber'),
					'Customer_ListID' => $Invoice->getChildDataAt('InvoiceRet CustomerRef ListID'),
					'Customer_FullName' => $Invoice->getChildDataAt('InvoiceRet CustomerRef FullName'),
					'ShipAddress_Addr1' => $Invoice->getChildDataAt('InvoiceRet BillAddress Addr1'),
					'ShipAddress_Addr2' => $Invoice->getChildDataAt('InvoiceRet BillAddress Addr2'),
					'ShipAddress_City' => $Invoice->getChildDataAt('InvoiceRet BillAddress City'),
					'ShipAddress_State' => $Invoice->getChildDataAt('InvoiceRet BillAddress State'),
					'ShipAddress_Country' => $Invoice->getChildDataAt('InvoiceRet BillAddress Country'),
					'ShipAddress_PostalCode' => $Invoice->getChildDataAt('InvoiceRet BillAddress PostalCode'),
					'BalanceRemaining' => $Invoice->getChildDataAt('InvoiceRet BalanceRemaining'),
					'DueDate' => $Invoice->getChildDataAt('InvoiceRet DueDate'),
					'IsPaid' => $Invoice->getChildDataAt('InvoiceRet IsPaid'),
					'EditSequence' => $Invoice->getChildDataAt('InvoiceRet EditSequence'),
					'AppliedAmount' => $Invoice->getChildDataAt('InvoiceRet AppliedAmount'),

				);

				$q = $this->CI->db->query("Select * from qb_test_invoice where TxnID='" . $arr['TxnID'] . "' ");
				if ($q->num_rows() == 0) {
					$arr['userStatus'] = 'Active';
				} else {
					$arr['userStatus'] = $q->row_array()['userStatus'];
				}

				//	QuickBooks_Utilities::log(QB_QUICKBOOKS_DSN, 'Importing invoice #' . $arr['RefNumber'] . ': ' . print_r($Invoice, true));

				foreach ($arr as $key => $value) {
					$arr[$key] = $this->CI->db->escape_str($value);
				}

				// Store the invoices in MySQL
				$this->CI->db->query("
				REPLACE INTO
					qb_test_invoice
				(
					" . implode(", ", array_keys($arr)) . "
				) VALUES (
					'" . implode("', '", array_values($arr)) . "'
				)");

				// Remove any old line items
				$this->CI->db->query("DELETE FROM qb_test_invoice_lineitem WHERE TxnID = '" . $this->CI->db->escape_str($arr['TxnID']) . "' ");

				// Process the line items
				foreach ($Invoice->children() as $Child) {
					if ($Child->name() == 'InvoiceLineRet') {
						$InvoiceLine = $Child;

						$lineitem = array(
							'TxnID' => $arr['TxnID'],
							'TxnLineID' => $InvoiceLine->getChildDataAt('InvoiceLineRet TxnLineID'),
							'Item_ListID' => $InvoiceLine->getChildDataAt('InvoiceLineRet ItemRef ListID'),
							'Item_FullName' => $InvoiceLine->getChildDataAt('InvoiceLineRet ItemRef FullName'),
							'Descrip' => $InvoiceLine->getChildDataAt('InvoiceLineRet Desc'),
							'Quantity' => $InvoiceLine->getChildDataAt('InvoiceLineRet Quantity'),
							'Rate' => $InvoiceLine->getChildDataAt('InvoiceLineRet Rate'),
						);

						foreach ($lineitem as $key => $value) {
							$lineitem[$key] = $this->CI->db->escape_str($value);
						}

						// Store the lineitems in MySQL
						$this->CI->db->query("
						INSERT INTO
							qb_test_invoice_lineitem
						(
							" . implode(", ", array_keys($lineitem)) . "
						) VALUES (
							'" . implode("', '", array_values($lineitem)) . "'
						) ");
					}
				}

				$this->CI->db->where(array('insertInvID' => $ID));
				//  $this->CI->db->delete('tbl_custom_invoice',array('qb_status'=>5));
				$this->CI->db->update('tbl_custom_invoice', array('qb_status' => 5, 'TimeModified' => date('Y-m-d H:i:s')));
				//$this->CI->db->where(array('invoiceDataID'=>$ID));
				// $this->CI->db->delete('tbl_subscription_invoice_item');

			}
		}
		return true;
	}

	public function _deleteInvoiceRequest($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $version, $locale)
	{


		$data = $this->CI->db->query("Select * from tbl_del_transactions where delID='" . $ID . "' ")->row_array();


		$xml = '<?xml version="1.0" encoding="utf-8"?>
		<?qbxml version="' . $version . '"?>
		<QBXML>
		 <QBXMLMsgsRq onError="stopOnError">
		<TxnDelRq requestID="' . $requestID . '">
			<TxnDelType>' . $data['txnType'] . '</TxnDelType>
			<TxnID>' . $data['delTxnID'] . '</TxnID>
		</TxnDelRq>
      </QBXMLMsgsRq>
      </QBXML>';


// QuickBooks_Utilities::log(QB_QUICKBOOKS_DSN, 'Datadgjdgjdgjdgdjgd #: ' . print_r( $xml, true));

		return $xml;
	}

	public function _deleteInvoiceResponse($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $xml, $idents)
	{
		// Do something here to record that the data was added to QuickBooks successfully
		$errnum = 0;
		$errmsg = '';
		$Parser = new QuickBooks_XML_Parser($xml);
//	QuickBooks_Utilities::log(QB_QUICKBOOKS_DSN, 'Importing customer Referererere>>>>>>>>>>>>>>>' .  ': ' . print_r($Parser, true));
		if ($Doc = $Parser->parse($errnum, $errmsg)) {
			$this->CI->db->where(array('delID' => $ID));
			$this->CI->db->update('tbl_del_transactions', array('qb_status' => 5, 'TimeModified' => date('Y-m-d H:i:s')));

			/*  	$query= $this->CI->db->query("Delete  from	customer_transaction where qbListTxnID='$ID' and  (transactionCode IN('100','1','200','111')
                or transactionType='Offline Payment'
                 or ((transactionType='AUTH ECheck'or transactionType='NMI ECheck')and transactionCode IN('100','1')))");  */

		}
		return true;
	}

	public function _addInvoiceRequest($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $version, $locale)
	{


		$query = $this->CI->db->query("Select * from tbl_custom_invoice where insertInvID='" . $ID . "'  ");
		$data = $query->row_array();
		//$query1 = $this->CI->db->last_query();
		$query1 = $this->CI->db->query("Select * from  tbl_subscription_invoice_item where invoiceDataID='" . $ID . "'  ");
		$data_items = $query1->result_array();

		$xml = '<?xml version="1.0" encoding="utf-8"?>
		<?qbxml version="' . $version . '"?>
		<QBXML>
		  <QBXMLMsgsRq onError="stopOnError">
			  <InvoiceAddRq requestID="' . $requestID . '">
			  <InvoiceAdd >
				<CustomerRef>
			    	<ListID>' . $data['Customer_ListID'] . '</ListID>
				  <FullName>' . $data['Customer_FullName'] . '</FullName>
				</CustomerRef>
				<TxnDate>' . date('Y-m-d', strtotime($data['TimeModified'])) . '</TxnDate>
				<RefNumber>' . $data['RefNumber'] . '</RefNumber>
			
			
				<BillAddress>
				  <Addr1>' . $data['ShipAddress_Addr1'] . '</Addr1>
				  <City>' . $data['ShipAddress_City'] . '</City>
				  <State>' . $data['ShipAddress_State'] . '</State>
				  <PostalCode>' . $data['ShipAddress_PostalCode'] . '</PostalCode>
				  <Country>' . $data['ShipAddress_Country'] . '</Country>
				</BillAddress>
			  <DueDate>' . date('Y-m-d', strtotime($data['DueDate'])) . '</DueDate>
			   <ShipDate>' . date('Y-m-d', strtotime($data['DueDate'])) . '</ShipDate>
			      <Memo>This is a custom</Memo>
			      
        
                 <LinkToTxnID></LinkToTxnID>';
		foreach ($data_items as $item) {
			if ($data['freeTrial'] == '1') {
				$amount = '0.00';
			} else {
				$amount = sprintf('%0.2f', $item['itemRate'] * $item['itemQuantity']);
				//  $amount= number_format($item['itemRate']*$item['itemQuantity'],2);
			}

			$xml .= '<InvoiceLineAdd>
				  <ItemRef>
				   <ListID>' . $item['itemListID'] . '</ListID>
					<FullName>' . $item['itemFullName'] . '</FullName>
				  </ItemRef>
				  <Desc>' . $item['itemDescription'] . '</Desc>
				  <Quantity>' . $item['itemQuantity'] . '</Quantity>
				  <Rate>' . $item['itemRate'] . '</Rate>
				   <Amount>' . $amount . '</Amount>
				</InvoiceLineAdd>';
		}
		$xml .= '</InvoiceAdd>
			</InvoiceAddRq>
		
		  </QBXMLMsgsRq>
		</QBXML>';


		QuickBooks_Utilities::log(QB_QUICKBOOKS_DSN, 'Invoice REQUEST  Datadgjdgjdgjdgdjgd #: ' . print_r($xml, true));

		return $xml;
	}

	public function _addInvoiceResponse($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $xml, $idents)
	{
		// Do something here to record that the data was added to QuickBooks successfully
		$errnum = 0;
		$errmsg = '';
		$Parser = new QuickBooks_XML_Parser($xml);
		if ($Doc = $Parser->parse($errnum, $errmsg)) {
			$Root = $Doc->getRoot();
			$List = $Root->getChildAt('QBXML/QBXMLMsgsRs/InvoiceAddRs');

			foreach ($List->children() as $Invoice) {
				$arr = array(
					'TxnID' => $Invoice->getChildDataAt('InvoiceRet TxnID'),
					'TimeCreated' => $Invoice->getChildDataAt('InvoiceRet TimeCreated'),
					'TimeModified' => $Invoice->getChildDataAt('InvoiceRet TimeModified'),
					'RefNumber' => $Invoice->getChildDataAt('InvoiceRet RefNumber'),
					'Customer_ListID' => $Invoice->getChildDataAt('InvoiceRet CustomerRef ListID'),
					'Customer_FullName' => $Invoice->getChildDataAt('InvoiceRet CustomerRef FullName'),
					'ShipAddress_Addr1' => $Invoice->getChildDataAt('InvoiceRet BillAddress Addr1'),
					'ShipAddress_Addr2' => $Invoice->getChildDataAt('InvoiceRet BillAddress Addr2'),
					'ShipAddress_City' => $Invoice->getChildDataAt('InvoiceRet BillAddress City'),
					'ShipAddress_State' => $Invoice->getChildDataAt('InvoiceRet BillAddress State'),
					'ShipAddress_Country' => $Invoice->getChildDataAt('InvoiceRet BillAddress Country'),
					'ShipAddress_PostalCode' => $Invoice->getChildDataAt('InvoiceRet BillAddress PostalCode'),
					'BalanceRemaining' => $Invoice->getChildDataAt('InvoiceRet BalanceRemaining'),
					'DueDate' => $Invoice->getChildDataAt('InvoiceRet DueDate'),
					'IsPaid' => $Invoice->getChildDataAt('InvoiceRet IsPaid'),
					'EditSequence' => $Invoice->getChildDataAt('InvoiceRet EditSequence'),
					'AppliedAmount' => $Invoice->getChildDataAt('InvoiceRet AppliedAmount'),

				);

				$q = $this->CI->db->query("Select * from qb_test_invoice where TxnID='" . $arr['TxnID'] . "' ");
				if ($q->num_rows() == 0) {
					$arr['userStatus'] = 'Active';
				} else {
					$arr['userStatus'] = $q->row_array()['userStatus'];
				}

				//	QuickBooks_Utilities::log(QB_QUICKBOOKS_DSN, 'Importing invoice #' . $arr['RefNumber'] . ': ' . print_r($Invoice, true));

				foreach ($arr as $key => $value) {
					$arr[$key] = $this->CI->db->escape_str($value);
				}

				// Store the invoices in MySQL
				$this->CI->db->query("
				REPLACE INTO
					qb_test_invoice
				(
					" . implode(", ", array_keys($arr)) . "
				) VALUES (
					'" . implode("', '", array_values($arr)) . "'
				)");

				// Remove any old line items
				$this->CI->db->query("DELETE FROM qb_test_invoice_lineitem WHERE TxnID = '" . $this->CI->db->escape_str($arr['TxnID']) . "' ");

				// Process the line items
				foreach ($Invoice->children() as $Child) {
					if ($Child->name() == 'InvoiceLineRet') {
						$InvoiceLine = $Child;

						$lineitem = array(
							'TxnID' => $arr['TxnID'],
							'TxnLineID' => $InvoiceLine->getChildDataAt('InvoiceLineRet TxnLineID'),
							'Item_ListID' => $InvoiceLine->getChildDataAt('InvoiceLineRet ItemRef ListID'),
							'Item_FullName' => $InvoiceLine->getChildDataAt('InvoiceLineRet ItemRef FullName'),
							'Descrip' => $InvoiceLine->getChildDataAt('InvoiceLineRet Desc'),
							'Quantity' => $InvoiceLine->getChildDataAt('InvoiceLineRet Quantity'),
							'Rate' => $InvoiceLine->getChildDataAt('InvoiceLineRet Rate'),
						);

						foreach ($lineitem as $key => $value) {
							$lineitem[$key] = $this->CI->db->escape_str($value);
						}

						// Store the lineitems in MySQL
						$this->CI->db->query("
						INSERT INTO
							qb_test_invoice_lineitem
						(
							" . implode(", ", array_keys($lineitem)) . "
						) VALUES (
							'" . implode("', '", array_values($lineitem)) . "'
						) ");
					}
				}

				$this->CI->db->where(array('insertInvID' => $ID));
				$this->CI->db->update('tbl_custom_invoice', array('qb_status' => 5, 'TimeModified' => date('Y-m-d H:i:s')));
				$this->CI->db->where(array('invoiceDataID' => $ID));
				$this->CI->db->delete('tbl_subscription_invoice_item');

			}
		}
		// QuickBooks_Utilities::log(QB_QUICKBOOKS_DSN, 'Invoice Response  Datadgjdgjdgjdgdjgd #: ' . print_r($Parser, true));
		return true;
	}
	
	/**
	 * Build a request to import invoices already in QuickBooks into our application
	 */
	public function _quickbooks_invoice_import_request($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $version, $locale)
	{
		// Iterator support (break the result set into small chunks)
		$attr_iteratorID = '';
		$attr_iterator = ' iterator="Start" ';
		if (empty($extra['iteratorID'])) {
			// This is the first request in a new batch
			$last = $this->_quickbooks_get_last_run($user, $action);
			$this->_quickbooks_set_last_run($user, $action);            // Update the last run time to NOW()

			// Set the current run to $last
			$this->_quickbooks_set_current_run($user, $action, $last);
		} else {
			// This is a continuation of a batch
			$attr_iteratorID = ' iteratorID="' . $extra['iteratorID'] . '" ';
			$attr_iterator = ' iterator="Continue" ';

			$last = $this->_quickbooks_get_current_run($user, $action);
		}


		// Build the request
		$xml = '<?xml version="1.0" encoding="utf-8"?>
		<?qbxml version="' . $version . '"?>
		<QBXML>
			<QBXMLMsgsRq onError="stopOnError">
				<InvoiceQueryRq ' . $attr_iterator . ' ' . $attr_iteratorID . ' requestID="' . $requestID . '">
					<MaxReturned>' . QB_QUICKBOOKS_MAX_RETURNED . '</MaxReturned>
					<ModifiedDateRangeFilter>
						<FromModifiedDate>' . $last . '</FromModifiedDate>
					</ModifiedDateRangeFilter>
					<IncludeLineItems>true</IncludeLineItems>
					<OwnerID>0</OwnerID>
				</InvoiceQueryRq>	
			</QBXMLMsgsRq>
		</QBXML>';

		return $xml;
	}

	/**
	 * Handle a response from QuickBooks
	 */
	public function _quickbooks_invoice_import_response($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $xml, $idents)
	{
		if (!empty($idents['iteratorRemainingCount'])) {
			// Queue up another request

			$Queue = QuickBooks_WebConnector_Queue_Singleton::getInstance();
			$Queue->enqueue(QUICKBOOKS_IMPORT_INVOICE, null, QB_PRIORITY_INVOICE, array('iteratorID' => $idents['iteratorID']), $user);
		}

		// This piece of the response from QuickBooks is now stored in $xml. You
		//	can process the qbXML response in $xml in any way you like. Save it to
		//	a file, stuff it in a database, parse it and stuff the records in a
		//	database, etc. etc. etc.
		//
		// The following example shows how to use the built-in XML parser to parse
		//	the response and stuff it into a database.

		// Import all of the records
		$errnum = 0;
		$errmsg = '';
		$Parser = new QuickBooks_XML_Parser($xml);
//	QuickBooks_Utilities::log(QB_QUICKBOOKS_DSN, 'Importing INVOICE Referererere>>>>>>>>>>>>>>>' .  ': ' . print_r($Parser, true));
		if ($Doc = $Parser->parse($errnum, $errmsg)) {
			$Root = $Doc->getRoot();
			$List = $Root->getChildAt('QBXML/QBXMLMsgsRs/InvoiceQueryRs');

			foreach ($List->children() as $Invoice) {
				$arr = array(
					'TxnID' => $Invoice->getChildDataAt('InvoiceRet TxnID'),
					'TimeCreated' => $Invoice->getChildDataAt('InvoiceRet TimeCreated'),
					'TimeModified' => $Invoice->getChildDataAt('InvoiceRet TimeModified'),
					'RefNumber' => $Invoice->getChildDataAt('InvoiceRet RefNumber'),
					'Customer_ListID' => $Invoice->getChildDataAt('InvoiceRet CustomerRef ListID'),
					'Customer_FullName' => $Invoice->getChildDataAt('InvoiceRet CustomerRef FullName'),
					'ShipAddress_Addr1' => $Invoice->getChildDataAt('InvoiceRet BillAddress Addr1'),
					'ShipAddress_Addr2' => $Invoice->getChildDataAt('InvoiceRet BillAddress Addr2'),
					'ShipAddress_City' => $Invoice->getChildDataAt('InvoiceRet BillAddress City'),
					'ShipAddress_State' => $Invoice->getChildDataAt('InvoiceRet BillAddress State'),
					'ShipAddress_Country' => $Invoice->getChildDataAt('InvoiceRet BillAddress Country'),
					'ShipAddress_PostalCode' => $Invoice->getChildDataAt('InvoiceRet BillAddress PostalCode'),
					'BalanceRemaining' => $Invoice->getChildDataAt('InvoiceRet BalanceRemaining'),
					'DueDate' => $Invoice->getChildDataAt('InvoiceRet DueDate'),
					'IsPaid' => $Invoice->getChildDataAt('InvoiceRet IsPaid'),
					'EditSequence' => $Invoice->getChildDataAt('InvoiceRet EditSequence'),
					'AppliedAmount' => $Invoice->getChildDataAt('InvoiceRet AppliedAmount'),

				);

				$q = $this->CI->db->query("Select * from qb_test_invoice where TxnID='" . $arr['TxnID'] . "' ");
				if ($q->num_rows() == 0) {
					$arr['userStatus'] = 'Active';
				} else {
					$arr['userStatus'] = $q->row_array()['userStatus'];
				}

				//	QuickBooks_Utilities::log(QB_QUICKBOOKS_DSN, 'Importing invoice #' . $arr['RefNumber'] . ': ' . print_r($Invoice, true));

				foreach ($arr as $key => $value) {
					$arr[$key] = $this->CI->db->escape_str($value);
				}

				// Store the invoices in MySQL
				$this->CI->db->query("
				REPLACE INTO
					qb_test_invoice
				(
					" . implode(", ", array_keys($arr)) . "
				) VALUES (
					'" . implode("', '", array_values($arr)) . "'
				)");

				// Remove any old line items
				$this->CI->db->query("DELETE FROM qb_test_invoice_lineitem WHERE TxnID = '" . $this->CI->db->escape_str($arr['TxnID']) . "' ");

				// Process the line items
				foreach ($Invoice->children() as $Child) {
					if ($Child->name() == 'InvoiceLineRet') {
						$InvoiceLine = $Child;

						$lineitem = array(
							'TxnID' => $arr['TxnID'],
							'TxnLineID' => $InvoiceLine->getChildDataAt('InvoiceLineRet TxnLineID'),
							'Item_ListID' => $InvoiceLine->getChildDataAt('InvoiceLineRet ItemRef ListID'),
							'Item_FullName' => $InvoiceLine->getChildDataAt('InvoiceLineRet ItemRef FullName'),
							'Descrip' => $InvoiceLine->getChildDataAt('InvoiceLineRet Desc'),
							'Quantity' => $InvoiceLine->getChildDataAt('InvoiceLineRet Quantity'),
							'Rate' => $InvoiceLine->getChildDataAt('InvoiceLineRet Rate'),
						);

						foreach ($lineitem as $key => $value) {
							$lineitem[$key] = $this->CI->db->escape_str($value);
						}

						// Store the lineitems in MySQL
						$this->CI->db->query("
						INSERT INTO
							qb_test_invoice_lineitem
						(
							" . implode(", ", array_keys($lineitem)) . "
						) VALUES (
							'" . implode("', '", array_values($lineitem)) . "'
						) ");
					}
				}
			}
		}

		return true;
	}
}

