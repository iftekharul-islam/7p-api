<?php


namespace Monogram\Ship;


use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use SimpleXMLElement;


class ENDICIA
{
    /**
     * Unique Transaction ID
     * @var string
     */
    public $PartnerTransactionID;
    /**
     * Client Object from Guzzle
     * @var [type]
     */
    private $client;
    /**
     * Requester ID from Endicia
     * @var [type]
     */
    private $RequesterID;
    /**
     * Account ID from Endicia
     * @var [type]
     */
    private $AccountID;
    /**
     * Passphrase from Endicia
     * @var string
     */
    private $PassPhrase;

    /**
     * Constructor
     * @param string $RequesterID
     * @param string $AccountID
     * @param string $PassPhrase
     */
    function __construct()
    {
        $this->client = $client = new Client();
        $this->client = new Client(['https://www.envmgr.com']);
        $this->RequesterID = "lxxx";
        $this->AccountID = "Natico_kohls2";
        $this->PassPhrase = "N.atico_kohls2";
    }


    public function getLabel($store, $order, $unique_order_id, $method = null, $packages = [0])
//    public function getLabelXw()
    {
//        dd($store, $order, $unique_order_id, $method, $packages );
//        $data['MailClass'] = "Priority";
//        $data['WeightOz'] = 15;
//        $data['PartnerCustomerID'] = "123467";
//        $data['PartnerTransactionID'] = "5b3da6879ced7";
//        $data['ToName'] = "John Doe";
//        $data['ToAddress1'] = "323 N Mathilda Ave";
//        $data['ToCity'] = "Sunnyvale";
//        $data['ToState'] = "CA";
//        $data['ToPostalCode'] = "94085";
        $data = array(
            'MailClass' => 'Priority',
            'WeightOz' => 15,
            'MailpieceShape' => 'Parcel',
            'Description' => 'Electronics',
            'FromName' => 'Bilbo Bagins',
            'ReturnAddress1' => '777 E Hobbit Lane',
            'FromCity' => 'Middle Earth',
            'FromState' => 'AZ',
            'FromPostalCode' => '85296',
            'FromZIP4' => '0004',
            'ToName' => 'Gandalf Grey',
            'ToCompany' => 'The White Wizard',
            'ToAddress1' => 'White Tower',
            'ToCity' => 'Morder',
            'ToState' => 'Az',
            'ToPostalCode' => '85296',
            'ToZIP4' => '0004',
            'ToDeliveryPoint' => '00',
            'ToPhone' => '2125551234'
        );
        $res = $this->request_shipping_label($data);
dd($res);
#################CCCCCCCCCCCCCCCCCCC#####################

//
////        dd($store, $order, $unique_order_id, $method, $packages);
//        $customer = $order->customer;
//        $customerName = substr(Helper::removeSpecial($customer['ship_full_name']), 0, 35);
//        $customerAddress1 = trim($customer['ship_address_1']);
//
//        $country_code = Shipper::getcountrycode($customer['ship_country']);
//        if (isset($customer['ship_address_2'])) {
//            $customerAddress2 = trim($customer['ship_address_2']);
//        } else {
//            $customerAddress2 = "";
//        }
//        if ($customer['ship_company_name'] != '') {
//            $customer_company_name = substr($customer['ship_company_name'], 0, 35);
//        } else {
//            $customer_company_name = "";
//        }
//        if (strlen(str_replace(['(', '-', ')', ' '], '', $customer['ship_phone'])) != 10) {
//            $customer_phone = '8563203200';
//        } else {
//            $customer_phone = $customer['ship_phone'];
//        }
//        #-------------------------------------WEIGHT and Dimensions--------------------------------------------------------#
//        $weightMultiplier = 1;
//
//
//        if (max($packages) < 1) {
//            $weightUnit = 'OZ';
//            $weightMultiplier = 16;
//        } else {
//            $weightUnit = 'LB';
//        }
//
//        if (max($packages) == 0 || $packages[0] == null || max($packages) < 1) {
//            $weightUnit = 'OZ';
//            $weightMultiplier = 16;
//        } else {
//            $weightUnit = 'LB';
//        }
//
//        if (array_sum($packages) == 0) {
//            $weight = .99;
//        } else {
//            $weight = array_sum($packages) * $weightMultiplier;
//        }
//
#################CCCCCCCCCCCCCCCCCCC#####################
        ################XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX############################
//        // Note you may want to associate this in some other way, like with a database ID.
//        $this->PartnerTransactionID = substr(uniqid(rand(), true), 0, 10);
//
//        $xml = '<LabelRequest Test="YES" LabelType="Default" LabelSize="4X6" ImageFormat="ZPLII">
//<RequesterID>' . $this->RequesterID . '</RequesterID>
//<AccountID>' . $this->AccountID . '</AccountID>
//<PassPhrase>' . $this->PassPhrase . '</PassPhrase>
//<PartnerTransactionID>' . $this->PartnerTransactionID . '</PartnerTransactionID>';
//
//        $xml .= "<FromCompany>KOHLS.COM</FromCompany>
//                <FromName>866-887-8884 <.FromName>
//                <ReturnAddress1>575 UNDERHILL BLVD STE 325</ReturnAddress1>
//                <FromCity>SYOSSET</FromCity>
//                <FromState>NY</FromState>
//                <FromPostalCode>11791</FromPostalCode>
//                <POZipCode>11791</POZipCode>
//                <ReferenceID2>STI</ReferenceID2>
//                <MailpieceDimensions>
//                                <Length>9</Length>
//                                <Width>9</Width>
//                                <Height>5</Height>
//                </MailpieceDimensions>";
//
//        if (!empty($data)) {
//            foreach ($data as $node_key => $node_value) {
//                $xml .= '<' . $node_key . '>' . $node_value . '</' . $node_key . '>';
//            }
//        }
//
//        $xml .= '<ResponseOptions PostagePrice="TRUE"/>
//</LabelRequest>';
//dd($xml);
//        $data = array("labelRequestXML" => $xml);
//
//
//        $returmResult = $this->client->post('https://labelserver.endicia.com/LabelService/EwsLabelService.asmx/GetPostageLabelXML',$data);
////        return $this->send_request($request);
////        $returmResult = $this->postGuzzleRequest($data);
//        dd("returmResult = ",$returmResult);
        ################XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX############################
    }
##############################################


    /**
     * Sends A requst for shipping label and info to endicia
     * @param  array $data Array of data to be formated as XML see Endicia documentation
     * @return array       The Response data from Endicia as an array , endica actually retuns XML
     */
    function request_shipping_label($data) {
        // Note you may want to associate this in some other way, like with a database ID.
        $this->PartnerTransactionID = substr(uniqid(rand(), true), 0, 10);

        $xml = '<LabelRequest Test="YES" LabelType="Default" LabelSize="4X6" ImageFormat="GIF">
					<RequesterID>'.$this->RequesterID.'</RequesterID>
					<AccountID>'.$this->AccountID.'</AccountID> 
					<PassPhrase>'.$this->PassPhrase.'</PassPhrase>
					<PartnerTransactionID>'.$this->PartnerTransactionID.'</PartnerTransactionID>';

        if(!empty($data)) {
            foreach($data as $node_key => $node_value) {
                $xml .= '<'.$node_key.'>'.$node_value.'</'.$node_key.'>';
            }
        }

        $xml .=	'<ResponseOptions PostagePrice="TRUE"/> 
				</LabelRequest>';

        $data = array("labelRequestXML" => $xml);
        $request = $this->client->post('/LabelService/EwsLabelService.asmx/GetPostageLabelXML', array(), $data);
        return $this->send_request($request);
    }


    /**
     * Send HTTP Request using Guzzle
     * @param  object $request THe Request Object to send using guzzle
     */
    function send_request($request) {
        $response = $request->send();
        return $this->parse_response($response);
    }

    /**
     * Parses the response from the request using guzzle client
     * @todo
     * @param  object $response the response object from guzzle
     * @return array  returns data from response
     */
    function parse_response($response) {
        $data = $response->getBody();

        // Note we could not use Guzzle XML method becuase Endicia does not return valid XML it seems
        $sxe = new SimpleXMLElement($data);

        if($sxe->status == 0) {
            $return_data = array();
            $return_data['Status'] = (string) $sxe->Status;
            $return_data['Base64LabelImage'] = (string) $sxe->Base64LabelImage;
            $return_data['TrackingNumber'] = (string) $sxe->TrackingNumber;
            $return_data['FinalPostage'] = (string) $sxe->FinalPostage;
            $return_data['TransactionID'] = (string) $sxe->TransactionID;
            $return_data['PostmarkDate'] = (string) $sxe->PostmarkDate;
            $return_data['DeliveryTimeDays'] = (string) $sxe->PostagePrice->DeliveryTimeDays;
            return $return_data;
        } else {
            return array('status' => 'error', 'message' => $sxe->ErrorMessage);
        }
    }



##############################################


}