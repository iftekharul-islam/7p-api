<?php

namespace Ship;

use App\Http\Requests\Request;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\MessageBag;
use Mockery\CountValidator\Exception;
use GuzzleHttp\Psr7\Request as GRequest;

class ApiClient
{

	const API_SERVER = 'https://mwf2.monogramonline.com';

	private $httpClient;
	private $orderIds = [], $store, $neededApi, $id_catalogs = [];
	private $options = [];
	private $store_contact_token = [
		// store online id
		#'yhst-132060549835833' => '1.0_j5CYYOls_wGIuB5b9mbRpyCRCzKOnau6a8Ec4YthB7wf77afyyOxYa7irnQFLy0Hgy6a9yNPqWwGVpz4egruypuO4nvyyPprCtHde1vXdl.7YKaGwOBrmUuKpwrS3pqXiEU.5o3InQ--',
		'yhst-132060549835833' => '1.0_3Hjk_Ols_wEXH_D_WSDH8aGnyvfCBdIcPCNXcLgkfyvuqdX9HOPah6efV.XAg6suIoJhPEX2dMyXNXbJW79EndUYGB3PeW4B0O.FMN__HgsKVjliHNeHSvM87MIKA37yIztKLxlU4Q--',
		// monogram online id
		#'yhst-128796189915726' => '1.0_.YQdYOls_wEHLw9D_0A7OcXXf38FJPBVSap2q6duSebDcQqYVeWVI.L2Yf1Kg8ofzdzP_dYs520oj_bvO1PQtsLmTEhwwHw2zVX5hWZD1jChETkYdQeAHFcd08dEgVBRnDw2aPcONQ--',
		'yhst-128796189915726' => '1.0_70gQrOls_wFecU3_HiSB.NnalMmqCjYVY3ltbXuUirRO0fGOVVWLykWZv.VpiDDDLLmOTRGQVDGdVcls6GS1YwaNeif5ED.NwlTKncuiOOfu7cQ0qEhtxS0fyG5uEgoNIHvZ1lPXiA--',
	];
	private $user_agents = [
		'Mozilla/4.0 (compatible; MSIE 7.0; AOL 8.0; Windows NT 5.1; GTB5; .NET CLR 1.1.4322; .NET CLR 2.0.50727)',
		'Mozilla/5.0 (compatible; U; ABrowse 0.6; Syllable) AppleWebKit/420+ (KHTML, like Gecko)',
		'Mozilla/5.0 (compatible; MSIE 9.0; AOL 9.1; AOLBuild 4334.5012; Windows NT 6.0; WOW64; Trident/5.0)',
		'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2227.0 Safari/537.36',
		'Mozilla/5.0 (Windows; U; Windows NT 5.0; en-US; rv:1.5) Gecko/20031016 K-Meleon/0.8.2',
		'Mozilla/5.0 (Windows NT 6.1; WOW64; Trident/7.0; AS; rv:11.0) like Gecko',
		'Mozilla/5.0 (compatible, MSIE 11, Windows NT 6.3; Trident/7.0; rv:11.0) like Gecko',
		'Mozilla/5.0 (Windows NT 6.2; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/48.0.2564.10 Safari/537.36',
	];
	private $task = null;

	private function getOrderIds()
	{
		return $this->orderIds;
	}

	private function setOrderIds($orderIds)
	{
		if (!empty($orderIds)) {
			if (is_array($orderIds)) {
				$this->orderIds = array_merge($this->orderIds, $orderIds);
			} else {
				$this->orderIds = [$orderIds];
			}

			return;
		}

		throw new \Exception("Order id cannot be null");
	}

	private function getIdCatalogs()
	{
		return $this->id_catalogs;
	}

	private function setIdCatalogs($id_catalogs)
	{
		if (!empty($id_catalogs)) {
			if (is_array($id_catalogs)) {
				$this->id_catalogs = array_merge($this->id_catalogs, $id_catalogs);
			} else {
				$this->orderIds = [$id_catalogs];
			}

			return;
		}

		throw new \Exception("Id catalogs cannot be null");
	}

	private function getNeededApi()
	{
		return $this->neededApi;
	}

	private function setNeededApi($neededApi)
	{
		if (!empty($neededApi)) {
			$this->neededApi = $neededApi;

			return;
		}

		throw new \Exception("API to use cannot be null");
	}

	private function getStore()
	{
		return $this->store;
	}

	private function setStore($store)
	{
		if (!empty($this->store = $store)) {
			$this->store = $store;

			return;
		}

		throw new \Exception("Store id cannot be null");
	}

	public function __construct($items, $store, $needed_api, $task = null)
	{
		if ($task == 'sync') {
			$this->task = 'sync';
			$this->setStore($store);
			$this->setNeededApi($needed_api);
			$this->setIdCatalogs($items);
			$this->httpClient = new Client();
		} else {
			if ($task == "none") {
			} else {
				$this->task = 'add-order';
				$this->setStore($store);
				$this->setNeededApi($needed_api);
				$this->setOrderIds($items);
				$this->httpClient = new Client();
			}
		}
	}

	public function fetch_data()
	{
		if ($this->getNeededApi() == 'yahoo') {
			if ($this->task == 'sync') {
				return $this->fetch_sync_order_from_yahoo();
			} elseif ($this->task == 'add-order') {
				return $this->fetch_data_from_yahoo();
			}
		}
	}

	private function setOptions($options)
	{
		foreach ($options as $key => $value) {
			$this->options[$key] = $value;
		}
	}

	private function build_options()
	{
		$headers = array();
		$headers['User-Agent'] = $this->randomizeAgent();
		$this->setOptions([
			'headers'         => $headers,
			'allow_redirects' => true,
		]);
	}

	private function randomizeAgent()
	{
		$random = rand(0, count($this->user_agents) - 1);

		return $this->user_agents[$random];
	}

	private function fetch_data_from_yahoo()
	{
		$placeHolderData = "<?xml version='1.0' encoding='utf-8'?><ystorewsRequest><StoreID>{$this->getStore()}</StoreID><SecurityHeader><PartnerStoreContractToken>{$this->store_contact_token[$this->getStore()]}</PartnerStoreContractToken></SecurityHeader><Version>1.0</Version><Verb>get</Verb><ResourceList><OrderListQuery><Filter><Include>all</Include></Filter><QueryParams><OrderID>PLACEHOLDERORDERID</OrderID></QueryParams></OrderListQuery></ResourceList></ystorewsRequest>";
		$url = "https://{$this->getStore()}.order.store.yahooapis.com/V1/order";
		$errors = [];
		$responses = [];
		foreach ($this->getOrderIds() as $orderId) {
			$data = str_replace("PLACEHOLDERORDERID", $orderId, $placeHolderData);
			$body = [
				'body' => $data,
			];
			$response = null;
			try {
				$response = $this->httpClient->request('POST', $url, $body);
				if ($response->getStatusCode() === 200) {
					$responses[] = [
						$orderId,
						$response->getBody()
							->getContents(),
					];
				} else {
					$errors[] = "Error for order id: $orderId";
				}
			} catch (RequestException $requestException) {
				$errors[] = "Error for order id: $orderId";
			} catch (Exception $exception) {
				$errors[] = "Error for order id: $orderId";
			}
		}

		return [
			$responses,
			new MessageBag($errors),
		];
	}

	private function fetch_sync_order_from_yahoo()
	{
		$data = "<?xml version='1.0' encoding='utf-8'?>";
		$data .= "<ystorewsRequest>";
		$data .= "<StoreID>{$this->getStore()}</StoreID>";     //insert your store id
		$data .= "<SecurityHeader>";
		$data .= "<PartnerStoreContractToken>{$this->store_contact_token[$this->getStore()]}</PartnerStoreContractToken>";  //insert your token`
		$data .= "</SecurityHeader>";
		$data .= "<Version>1.0</Version>";
		$data .= "<Verb>get</Verb>";
		$data .= "<ResourceList>";
		$data .= "<CatalogQuery>";
		$data .= "<ItemQueryList>";
		$data .= "<ItemIDList>";
		$data .= "<ID>PLACEHOLDERIDCATALOG</ID>";
		$data .= "</ItemIDList>";
		$data .= "<AttributesType>all</AttributesType>";
		$data .= "</ItemQueryList>";
		$data .= "</CatalogQuery>";
		$data .= "</ResourceList>";
		$data .= "</ystorewsRequest>";
		#$url = "https://{$this->getStore()}.order.store.yahooapis.com/V1/order";
		$url = "https://{$this->getStore()}.catalog.store.yahooapis.com/V1/CatalogQuery";
		$errors = [];
		$responses = [];
		foreach ($this->getIdCatalogs() as $idCatalog) {
			$ndata = str_replace("PLACEHOLDERIDCATALOG", $idCatalog, $data);
			$body = [
				'body' => $ndata,
			];
			$response = null;
			try {
				$response = $this->httpClient->request('POST', $url, $body);
				if ($response->getStatusCode() === 200) {
					$responses[] = [
						$idCatalog,
						$response->getBody()
							->getContents(),
					];
				} else {
					$errors[] = "Error for Catalog id: $idCatalog";
				}
			} catch (RequestException $requestException) {
				$errors[] = "Error for Catalog id: $idCatalog";
			} catch (Exception $exception) {
				$errors[] = "Error for Catalog id: $idCatalog";
			}
		}

		return [
			$responses,
			new MessageBag($errors),
		];
	}

	public function getAuthenticationToken()
	{
		$apiParams = [
			'API_LOGIN_URI' => self::API_SERVER . '/security/api/login',
			'API_USERNAME' => 'api@example.com',
			'API_PASSWORD' => 'api@2021'
		];

		$httpClient = new Client([
			'verify' => false
		]);


		$response = $httpClient->post(
			$apiParams['API_LOGIN_URI'],
			[
				'json' => ['username' => $apiParams['API_USERNAME'], 'password' => $apiParams['API_PASSWORD']]
			]
		);

		return json_decode($response->getBody()->getContents(), true)['token'];
	}

	public function postPayload($uri, $token, $payLoad = [])
	{
		$httpClient = new Client([
			'verify' => false
		]);
		return $httpClient->post(
			self::API_SERVER . $uri,
			[
				'headers' => [
					'Authorization' => 'Bearer ' . $token,
					'Content-Type' => 'application/ld+json'
				],
				'json' => $payLoad,
			]
		);
	}

	public function getPayload($uri, $token, $payLoad = [])
	{
		$httpClient = new Client([
			'verify' => false
		]);
		return $httpClient->get(
			self::API_SERVER . $uri,
			[
				'headers' => [
					'Authorization' => 'Bearer ' . $token,
					'Content-Type' => 'application/json'
				],
				'json' => $payLoad,
			]
		);
	}
}
