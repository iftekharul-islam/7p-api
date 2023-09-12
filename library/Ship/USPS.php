<?php 

namespace Ship;

use Illuminate\Support\Facades\Log;

class USPS 
{ 
	protected $username = '388DEALT4238';
	
	public function validate($street, $apt = null, $zip, $city, $state, $count = 0) {
		
			$xml = new \SimpleXMLElement('<AddressValidateRequest></AddressValidateRequest>'); 
	     
	    $xml->addAttribute('USERID', $this->username); 
			
			$xml->addChild('Revision', 1);
			$address = $xml->addChild('Address');
	    // $address->addAttribute('ID', 0); 
			$address->addChild('Address1', $apt);
			$address->addChild('Address2', $street);
			$address->addChild('City', $city);
			$address->addChild('State', $state);
			$address->addChild('Zip5', substr($zip, 0, 5));
			$address->addChild('Zip4');
			
	    $postdata = $xml->asXML(); 

      // Perform the request and return result
			try {
      	$response = $this->connect('API=Verify&XML=', $postdata);
      } catch (\Exception $e) {
				Log::error('USPS validate: ' . $e->getMessage());
				return 'Error: ' . $e->getMessage();
			}
			
      $response = new \SimpleXMLElement($response);
			
			if (isset($response->Address->Zip4) && strlen($response->Address->Zip4) == 4) {
				return $response->Address;
			} else if (isset($response->Number)) {
				return FALSE;
        Log::info('USPS validate: ' . $response->Description . ' ' . $response->Number);
			} else {
				if ($count == 0) {
					return $this->tryAgain($street, $apt, $zip, $city, $state);
				} else {
					return FALSE;
				}
			}
  }
	
	private function tryAgain ($street, $apt, $zip, $city, $state) {
		
		$changed = false;
		
		//replace dots with spaces
		$street = str_replace(['.','-'], ' ', $street);
		
		if ($apt == '') {
			
			//move unit to apt
			$units = ['unit', 'apt', '#', 'apartment', 'ste', 'suite', 'lot'];
			
			foreach ($units as $unit) {
				if (strpos(strtolower($street), $unit)) {
					$apt = substr($street, strpos(strtolower($street), $unit));
					$street = substr($street, 0, strpos(strtolower($street), $unit));
					$changed = true;
					break;
				}
			}
			
		} else if (is_numeric(trim($street))) {

			$street = $street . ' ' . $apt;
			$apt = '';
			$changed = true;
			
		}
		
		if (!$changed) {
			//split numbers and letters
			$arr = preg_split('/(?<=[0-9])(?=[a-z]+)/i',$street);
			$street = implode(' ', $arr);
		}
		
		return $this->validate($street, $apt, $zip, $city, $state, 1);
	}
	
	public function connect($api, $xml) {
		
		$ch = curl_init('http://production.shippingapis.com/ShippingAPI.dll');
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $api . $xml);
		curl_setopt($ch, CURLOPT_TIMEOUT, 60);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
 
		$result = curl_exec($ch);
		$error = curl_error($ch);
 
		if (empty($error)) {
			return $result;
		} else {
			return false;
		}
	}
}