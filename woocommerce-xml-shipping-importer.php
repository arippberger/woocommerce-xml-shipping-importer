<?php
/**
 * Plugin Name: WooCommerce XML Shipping Importer
 * Plugin URI: http://URI_Of_Page_Describing_Plugin_and_Updates
 * Description: Pulls XML from distribution center's server and updates shipping info
 * Version: 1.0
 * Author: Alec Rippberger
 * Author URI: http://alecrippberger.com
 * License: GPL2
 */

if ( !class_exists( 'WCXMLShippingInput' ) ) {
	class WCXMLShippingInput 
	{
		public function __construct() 
		{

			//add everymin cron schedule
			add_filter( 'cron_schedules', array( $this, 'xml_shipping_add_schedule' ) );			

			//schedule cron
			add_action( 'init', array( $this, 'add_scheduled_import' ) );

			// add main shipping import method to cron action
			add_action( 'wcxmlshippingimportaction', array( $this, 'wc_xml_shipping_import' ) );

			//get the import interval
			$this->options = get_option( 'xml_shipping_import_option' );
			$this->import_interval = $this->options["ftp_interval"]; 

			//register activation hook - here we'll run the main function
			register_activation_hook( __FILE__, array( $this,'wc_xml_shipping_import_activate' ) );

			//initialize admin
			if ( is_admin() ) {
				$this->admin_includes();
			}
			
		}

		//method for adding the cron job
		public function add_scheduled_import() 
		{
			//update import interval 
			$this->options = get_option( 'xml_shipping_import_option' );
			$this->import_interval = $this->options["ftp_interval"]; 

			if ( ! $this->import_interval ) {
				$this->import_interval == 'hourly';
			}

			$current_interval = wp_get_schedule( 'wcxmlshippingimportaction' );

			//if not currently scheduled
			if ( ! wp_next_scheduled( 'wcxmlshippingimportaction' ) ) {

				// Schedule import 
				wp_schedule_event( time(), $this->import_interval, 'wcxmlshippingimportaction' );

			} elseif ( $current_interval != $this->import_interval ) { //if current interval set in options doesn't match scheduled cron

				//unschedule old cron
				wp_clear_scheduled_hook( 'wcxmlshippingimportaction' );

				// Schedule import 
				wp_schedule_event( time(), $this->import_interval, 'wcxmlshippingimportaction' );

			}
		}		

		public function admin_includes() 
		{

			// loads the admin settings page and adds functionality to the order admin
			require_once( 'class-xml-shipping-importer-options.php' );
			$this->admin = new XML_Shipping_Importer_Options(__FILE__);

		}		

		public function wc_xml_shipping_import_activate() 
		{	

			$this->wc_xml_shipping_import(); //also run on plugin activation

		}

		public function wc_xml_shipping_import() 
		{
			global $woocommerce;

			//get options
			$this->options = get_option( 'xml_shipping_import_option' );

			//define FTP server
			$ftp_server = $this->options["ftp_server"]; 
			$ftp_user_name = $this->options["ftp_user_name"]; 
			$ftp_user_pass = $this->options["ftp_user_pass"]; 
			$ftp_directory = $this->options["ftp_directory"]; 
			$xml_filename = $this->options["xml_filename"]; 

			//get the import interval
			$this->import_interval = $this->options["ftp_interval"]; // 'hourly'

			//change any date references (tags) to today's date via php date() function
			$xml_filename = $this->change_date_tag($xml_filename);

			//append .xml to files
			$xml_filename = $xml_filename . '.xml';

			//create local file to store XML for manipulation
			$local_file = plugin_dir_path( __FILE__ ) . $xml_filename;

			// set up basic connection
			$conn_id = ftp_connect($ftp_server); 

			// login with username and password
			$login_result = ftp_login($conn_id, $ftp_user_name, $ftp_user_pass); 

			// check connection
			if ((!$conn_id) || (!$login_result)) {
			    error_log("FTP connection has failed !");
			}

			// turn passive mode on
			ftp_pasv($conn_id, true);

			// try to change the directory to somedir
			if (ftp_chdir($conn_id, $ftp_directory)) {
				// all good
			} else { 
			    error_log("Couldn't change directory<br />");
			}

			//check to see if XML file exists
			$contents_on_server = ftp_nlist($conn_id, $path);

			if (in_array($xml_filename, $contents_on_server)) {

				// try to download $xml_filename and save to $local_file
				if (ftp_get($conn_id, $local_file, $xml_filename, FTP_BINARY)) {

					$shipments = simplexml_load_file($local_file);

					foreach ($shipments as $shipment) {

						//get order & tracking numbers and carrier - clean up
						$ordernumber = intval($shipment->ordernumber);
						$trackingnumber = intval($shipment->trackingnumber);
						$carrier = trim(strval($shipment->carrier));

						if (!empty($ordernumber)) {

							//create new order object
							$order = new WC_Order(strval($ordernumber));

							//if order status isn't complet
							if ($order->status != 'completed') {

								//add tracking number and carrier info as meta data
								update_post_meta( $ordernumber, 'tracking_number', $trackingnumber ); 
								update_post_meta( $ordernumber, 'carrier', $carrier );

								//turn XML shipment shortcodes into detailed strings
								switch ($carrier) {
									case 'FH': 
										$carrier = 'via FedEx Ground Home Delivery';
									break;
									case 'FG':
										$carrier = 'via FedEx Ground';
									break;
									case 'F1':
										$carrier = 'via FedEx Standard Overnight';
									break;
									case 'F2':
										$carrier = 'via FedEx 2Day';
									break;
								}

								//formulate shipping note
								$order_shipped_note = sprintf( __( "Order has been shipped %s and the tracking number is %s.", 'woocommerce' ), $carrier, $tracking_number );

								//update order status
								$order->update_status( 'completed', 'Order Shipped'  );

								//add order note
								$order->add_order_note($order_shipped_note, 0  );

							}	
						}					
					}
				} else {
					error_log( "There was a problem<br />");
				}

				//delete the local file
				if ( ! unlink( $local_file ) ) {
	  					error_log( "unable to delete local file" );
				}
				
			} else {
				//xml file does not exist
				error_log('XML file does not exist');
			}
			// close the connection
			ftp_close($conn_id);

		}

		//add cron schedule for every minute
		public function xml_shipping_add_schedule($schedules) {
		    // interval in seconds
		    $schedules['everymin'] = array('interval' => 60, 'display' => 'Every Minute');
		    $schedules['every5min'] = array('interval' => 5*60, 'display' => 'Every Five Minutes');
		    return $schedules;
		}	

		//change php date() variables to date based on timestamp
		private function change_date_tag($input) 
		{
			$start_tag = '%-';
			$end_tag = '-%';
			//get date in filename
			$date_string = $this->getBetween($input, $start_tag, $end_tag); 
			//var_dump($date_string);
			//if there is a date in the filename, turn that string into a numerical date based on current timestamp
			if (!empty($date_string)) {
			    $return_date = strval(date($date_string));
			}
			//remove item (including tags) from input string
			$input = str_replace($start_tag . $date_string . $end_tag, '', $input);
			//append date string to the rest of the input
			$input = $input . $return_date;		

			return $input;
		}

		//get content between 2 points - used for $this->change_date_tage() method
        private function getBetween($content,$start,$end) 
        {
            $r = explode($start, $content);
            if (isset($r[1])){
                $r = explode($end, $r[1]);
                return $r[0];
            }
            return '';
        }     		
	}
}

global $WCXMLShippingInput;
$WCXMLShippingInput = new WCXMLShippingInput();


