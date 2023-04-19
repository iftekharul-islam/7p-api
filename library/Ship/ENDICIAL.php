<?php


namespace Monogram\Ship;


use Illuminate\Support\Facades\Log;

class ENDICIAL
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

    protected $getServiceMethodCode = array(
        'USFC' => 'First',
        'USPM' => 'Priority',
        'USCG' => 'RetailGround'
    );
    /**
     * Constructor
     * @param string $RequesterID
     * @param string $AccountID
     * @param string $PassPhrase
     */
    function __construct()
    {
        $this->RequesterID = "lpst";
        $this->AccountID = "Natico_kohls2";
        $this->PassPhrase = "N.atico_kohls2";
    }


    public function getLabel($store, $order, $unique_order_id, $method = null, $packages = [0])
    {
//        dd($store, $order, $unique_order_id, $method, $packages );
        if (!isset($this->getServiceMethodCode[$method])){
            $method = "First";
        }else{
            $method = $this->getServiceMethodCode[$method];
        }

        if(($packages[0]*16) < 0){
            dd("Weight must be minimum 1 ounce", ($packages[0]*16));
        }

        $shipZip = explode('-', $order->customer->ship_zip);
        $data = array(
            'MailClass' => $method,
            'WeightOz' => (double)($packages[0]*16),
            'PartnerCustomerID' => $order->order_id,
            'PartnerTransactionID' => $unique_order_id,
            'ToName' => $order->customer->ship_full_name,
            'ToAddress1' => $order->customer->ship_address_1,
            'ToCity' => $order->customer->ship_city ,
            'ToState' => $order->customer->ship_state,
            'ToPostalCode' => $shipZip[0],
        );
//        dd($data);
        $resData = $this->request_shipping_label($data);
        $resPondData = $this->curlPost("https://labelserver.endicia.com/LabelService/EwsLabelService.asmx/GetPostageLabelXML", $resData);
        $result = $this->parse_response($resPondData);

//        ######### Debug Codes #########
//        echo "<pre>";
//        #echo $result['Base64LabelImage'];
//        print_r($result);
//        echo "</pre>";
//        dd($resData, $resPondData, $result);
//        ######### Debug Codes #########
        if($result['Status'] != 'error' && !empty($result['TrackingNumber'])){
            $trackingInfo['image'] = (string) $result['Base64LabelImage'];//zpl_label;
            $trackingInfo['tracking_number'] = (string) $result['TrackingNumber'];
            $trackingInfo['shipping_id'] = (string) $result['TrackingNumber'];
            $trackingInfo['unique_order_id'] = (string) $unique_order_id;
            $trackingInfo['mail_class'] = (string) $method;
            $trackingInfo['type'] = 'ENDICIA';
//            dd("YYYYYYYYYYy", $result, $trackingInfo);

        }else {
                dd("Error Message", $result['message']);
        }
//        dd( "TTTTTTTTTTTTTTTTT",$result, $trackingInfo);
        return $trackingInfo;
    }

    /**
     * Sends A requst for shipping label and info to endicia
     * @param  array $data Array of data to be formated as XML see Endicia documentation
     * @return array       The Response data from Endicia as an array , endica actually retuns XML
     */
    function request_shipping_label($data) {
        // Note you may want to associate this in some other way, like with a database ID.
//        $this->PartnerTransactionID = substr(uniqid(rand(), true), 0, 10);

        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<LabelRequest Test="NO" LabelType="Default" LabelSize="4X6" ImageFormat="ZPLII"><RequesterID>'.$this->RequesterID.'</RequesterID><AccountID>'.$this->AccountID.'</AccountID><PassPhrase>'.$this->PassPhrase.'</PassPhrase><PartnerTransactionID>'.$data['PartnerTransactionID'].'</PartnerTransactionID>';
//        $xml .= '<LabelRequest Test="YES" LabelType="Default" LabelSize="4X6" ImageFormat="ZPLII"><RequesterID>'.$this->RequesterID.'</RequesterID><AccountID>'.$this->AccountID.'</AccountID><PassPhrase>'.$this->PassPhrase.'</PassPhrase><PartnerTransactionID>'.$data['PartnerTransactionID'].'</PartnerTransactionID>';
        if(!empty($data)) {
            foreach($data as $node_key => $node_value) {
                $xml .= '<'.$node_key.'>'.$node_value.'</'.$node_key.'>';
            }
        }
        $xml .= '<FromCompany>KOHLS.COM</FromCompany><FromName>866-887-8884</FromName><ReturnAddress1>575 UNDERHILL BLVD STE 325</ReturnAddress1><FromCity>SYOSSET</FromCity><FromState>NY</FromState><FromPostalCode>11791</FromPostalCode><POZipCode>11791</POZipCode><ReferenceID2>STI</ReferenceID2><MailpieceDimensions><Length>9</Length><Width>9</Width><Height>5</Height></MailpieceDimensions>';
        $xml .=	'<ResponseOptions PostagePrice="TRUE"/></LabelRequest>';
//        $xml .=	'<ResponseOptions PostagePrice="FALSE"/></LabelRequest>';
        $data = array("labelRequestXML" => $xml);
        return $data;
    }

    /**
     * Parses the response from the request using guzzle client
     * @param  object $response the response object from guzzle
     * @return array  returns data from response
     */
    function parse_response($response) {
        $sxe =  @simplexml_load_string($response);
        // Convert into json
        $con = json_encode($sxe);

        // Convert into associative array
        $sxe = json_decode($con, true);
//        dd($sxe);
        if($sxe['Status'] == 0) {
            $return_data = array();
//            $return_data['Status'] = (string) $sxe->Status;
//            $return_data['Base64LabelImage'] = (string) base64_decode($sxe->Base64LabelImage);
//            $return_data['TrackingNumber'] = (string) $sxe->TrackingNumber;
//            $return_data['FinalPostage'] = (string) $sxe->FinalPostage;
//            $return_data['TransactionID'] = (string) $sxe->TransactionID;
//            $return_data['PostmarkDate'] = (string) $sxe->PostmarkDate;
//            $return_data['DeliveryTimeDays'] = (string) $sxe->PostagePrice->DeliveryTimeDays;
            $return_data['Status'] =  $sxe['Status'];
            $return_data['Base64LabelImage'] = base64_decode($sxe['Base64LabelImage']);
            $return_data['TrackingNumber'] =  $sxe['TrackingNumber'];
            $return_data['FinalPostage'] =  $sxe['FinalPostage'];
            $return_data['TransactionID'] =  $sxe['TransactionID'];
            $return_data['PostmarkDate'] =  $sxe['PostmarkDate'];
//            $return_data['DeliveryTimeDays'] =  $sxe->PostagePrice->DeliveryTimeDays;
            return $return_data;
        } else {
            return array('Status' => 'error', 'message' => $sxe['ErrorMessage']);
        }
    }

    public function curlPost($target_url, $purchaseData)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $target_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($purchaseData));
        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }
}