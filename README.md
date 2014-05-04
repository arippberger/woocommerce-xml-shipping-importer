woocommerce-xml-shipping-importer
=================================

Intro
-----

This WordPress plugin was designed to fulfill a specific purpose, but may be useful to others. Major code modifications may be required to fit the plugin to your needs.

Original Purpose
----------------

The original purpose for this plugin was to interface a WooCommerce installation with a distribution center's order processing.
  
  * Distribution center had an FTP server set up 
  * Distribution center requested an XML file of orders be uploaded to their FTP server (WooCommerce Customer/Order XML Export Suite was used for this process - http://www.woothemes.com/products/customerorder-xml-export-suite/ )
  * Distribution center would upload an XML file of completed orders to their FTP server. This file included 1) WooCommerce Order Number 2) Tracking number 3) Shipping Carrier Name
  * This plugin was designed to process to completed orders:
  
  1. Add tracking number and carrier information to order as meta data
  2. Change order status to completed
  3. Capture card information (credit card info authorized at time of order, captured when order has shipped)
  
What You'll Need To Customize
-----------------------------
  
  * FTP information- the plugin has a settings page for these fields - could use improved security
  * Depending on the format of the XML file you'd like to import, you may need to customize the code that handles the import. The plugin currently uses the SimpleXML class to import the XML file to an object.
  * The current credit card capture will need to be customized depending on your needs and your payment gateway (currently set up to work with Intuit's payment gateway)
  
Setup
-----

  1. Install the plugin
  2. Activate the plugin
  3. Enter FTP credentials under the settings page
  4. As XML files are added to your FTP server, the plugin will update orders accordingly
  
Examples
--------

  * Example XML file included in repo as example.xml
