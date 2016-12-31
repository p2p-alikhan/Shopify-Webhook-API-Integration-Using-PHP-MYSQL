<?php
/**
 *  PDO wrapper for Mysql 
 *
 * @author		Author: Ali khan (pk.linkedin.com/in/p2palikhan) 
 *
 */
 
header('Content-Type: application/json');

/* For Debuggin Only
ini_set('display_errors', 1);
error_reporting(E_ALL);
phpinfo();
print_r(PDO::getAvailableDrivers());
*/

require_once('DB.php');
class Shopify {

	// Initializing pdo instance & shopify api credentials 
	private $db;
	private $api_key = "e14e68216ed66ff77b9e2c280cb23198";
	private $password = "fc1819612467619615352d8ef8c45ae6";
	private $store = "kpstore2016.myshopify.com";
	
	function __construct(){		
		$this->db = new DB('DB-CUSTOMERS');			
	}
	
	// Constructing store url
	private function storeUrl(){		
		// format: https://e14e68216ed66ff77b9e2c280cb23198:fc1819612467619615352d8ef8c45ae6@kpstore2016.myshopify.com/admin/customers/5383416582.json
		return "https://".$this->api_key.":".$this->password."@".$this->store;		
	}
	
	// Fetching customers data from mysql database
	private function fetchCustomers(){
	
		$query = "select * from customers order by id desc limit 0,10";					
		$rows = $this->db->query($query);
		return $rows;
		
	}
	
	// Responsible for creating customers through shopify api
	private function createUserShopify($data) {

		$customer = array("customer"=>array(
			"first_name"=>$data['name'],
			"email"=>$data['email'],
			"password"=>$data['password'],
			"password_confirmation"=>$data['password'],
			"verified_email"=>false,
			"send_email_welcome"=>false,
		    "send_email_invite"=> false,
			"tags"=> "premium_access"		    		
		));		
		
		$url = $this->storeUrl()."/admin/customers.json";
		
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_VERBOSE, 0);
		//curl_setopt($curl, CURLOPT_HEADER, 1);
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($customer));
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		$response['data'] = curl_exec ($curl);
		if(curl_errno($curl))
		{
			$response['error'] = 'Curl error: ' . curl_error($curl);
		}
		curl_close ($curl);		
		return $response;
	
	}
	
	// Responsible for generating strong password
	private function generate_strong_password( $length = 10 ) {
	
		$chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789@#$%&?";
		$password = substr( str_shuffle( $chars ), 0, $length );
		return $password;
		
	}
	
	// Responsible for fetching customers from db then exporting to shopify
	public function processCustomersMigration(){
		
		$results = array();
		$results['start-time'] = date('h:i:s'); 
		$customers = $this->fetchCustomers();	
		
		foreach($customers as $customer){
		
			$data = array();
			$data['first_name'] = $customer['first_name'];
			$data['last_name'] = $customer['last_name'];
			$data['email'] = $customer['email'];
			$data['password'] = $this->generate_strong_password(12);
			$data['shopify-response'] = $this->createUserShopify($data);			
			$results[] = $data;
			// wait for half second to compensate 2 calls per second shopify bucket limit (40)
			usleep(500000); 
		
		}
		
		$results['end-time'] = date('h:i:s');
		return json_encode($results);		
			
	}

	// Responsible for sending push notification to admin whenever someone places an order on shopify store
	public function processOrderWebHook(){

		$webhook_content = '';
		$webhook = fopen('php://input' , 'rb');
		while(!feof($webhook)){ //loop through the input stream while the end of file is not reached
			$webhook_content .= fread($webhook, 4096); //append the content on the current iteration
		}
		fclose($webhook); //close the resource
		$data = json_decode($webhook_content, true); //convert the json to array
		$headers = "From: KPStore <shop.support@kpstore.com> \r\n";
		$headers .= "MIME-Version: 1.0\r\n";
		$headers .= "Content-Type: text/html; charset=ISO-8859-1\r\n";
		echo mail("p2p.alikhan@gmail.com","Shopify Store: New Order Placed From Customer",$data,$headers);echo "\n";
		echo file_put_contents("test.txt",$data);
		
	}	
	
}

// Launching migration process now
$Shopify = new Shopify();
$results = $Shopify->processCustomersMigration();
echo $results;
//echo "<pre>"; print_r($results);

?>