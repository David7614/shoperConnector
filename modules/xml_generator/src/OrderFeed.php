<?php

namespace app\modules\xml_generator\src;

use app\models\IntegrationData;
use app\models\Queue;
use Cassandra\Date;
use Codeception\Configuration;
use yii;
use app\models\Orders;
use SoapClient;

class OrderFeed extends XmlFeed
{
    /**
     * @param null $what
     * @return bool
     *
     */
    const API_RESULT_COUNT=100; 
    const XML_PAGE_SIZE=50000; 

    public function generate($what = null): int
    {
        $temp = $this->getFile(true, true);
        $file = $this->getFile(true, false);

        if($what == 'objects') {
            echo "creating objects".PHP_EOL;
            return $this->createOrderObjects();

        }
        


        if (!$this->isFinished()) {
            $created = $this->createOrAddTempOrderXml($temp);
        } else {
            $created = $this->createOrderXml($file, $temp);
        }

        return $created;
    }

    /**
     * @param bool $get_file_path
     * @param bool $temp
     *
     * @return string
     */
    public function getFile(bool $get_file_path = false, bool $temp = false): string
    {
        return parent::getFile($get_file_path, $temp);
    }

    public function checkOrders(){
        //creating SOAP client with Authorization header
        $gate = "https://www.vedion.pl/api/?gate=orders/get/129/soap/wsdl&lang=pol";
        $apiClient = new SoapClient(
            $gate,
            [
                'stream_context' => stream_context_create([
                    'http' => [
                        'header' => 'Authorization: Bearer ' . $this->_token
                    ],
                ]),
                'cache_wsdl' => WSDL_CACHE_NONE
            ]
        );

        try {
            //building request
            $request = [
                'authenticate' => [
                    //leaving empty - authenticating using OAuth access token
                    'userLogin' => '',
                    'authenticateKey' => ''
                ],
                'get' => [
                    'params' => [
                        'returnProducts' => 'active',
                    ]
                ]
            ];

            $file_path = '/home/sambam2/public_html/modules/xml_generator/src/feeds/order/' . $this->_user->uuid . '/controll';

            
            $page = file_get_contents($file_path);
            $end = $page + 200;
            
            for($i = $page; $i < $end; $i++) {
                echo " ******** PAGE ".$i." start ".PHP_EOL;
                $allItems=Array();
                $request['params']['resultsPage'] = $i;
                $response = $apiClient->get($request);
                if (!isset($response->Results)){
                  die (" no results, stop ");
                }
                foreach ($response->Results as $order) {
                    if ($order->orderDetails->productsResults == null) continue;

                    $status = isset($order_statuses_map[$order->orderDetails->orderStatus]) ? $order_statuses_map[$order->orderDetails->orderStatus] : 'created';

                    $order_item = [];
                    $order_item['order_id'] = $order->orderId;
                    $order_item['customer_id'] = $order->clientResult->clientAccount->clientId;
                    $order_item['created_on'] = $order->orderDetails->orderAddDate;
                    $order_item['finished_on'] = $status == 'finished' ? $order->orderDetails->orderDispatchDate : null;
                    $order_item['status'] = $status;
                    $order_item['email'] = htmlentities(html_entity_decode($order->clientResult->clientAccount->clientEmail, ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8');
                    $order_item['phone'] = $order->clientResult->clientAccount->clientPhone1;
                    $order_item['zip_code'] = $order->clientResult->clientBillingAddress->clientZipCode;
                    $order_item['country_code'] = $order->clientResult->clientBillingAddress->clientCountryId;
                    $order_item['user_id'] = $this->_user->id;
                    $order_item['page'] = $i;

                    $positions=Array();
                    foreach ($order->orderDetails->productsResults as $product) {
                        $positions[]=[
                            'product_id' => $product->productId,
                            'amount' => $product->productQuantity,
                            'price' => $product->productOrderPrice
                        ];
                    }
                    $order_item['order_positions']=serialize($positions);
                    $allItems[]=$order_item;
                    
                    

                }
                Yii::$app->db->createCommand()->batchInsert('orders', ['order_id', 'customer_id', 'created_on', 'finished_on', 'status', 'email', 'phone', 'zip_code', 'country_code', 'user_id', 'page', 'order_positions'], $allItems)->execute();
                echo " ******** PAGE ".$i." end ".PHP_EOL;
            }

            // echo count($allItems);
            // die ("STOP");

            

            echo "gut majonez";
            file_put_contents($file_path, $i);

            return true;
        } catch (\Exception $e) {
            echo $e;
            return false;
        }
    }

    private function checkQueueConstraints(){ // todo
        
        if ($this->_queue->max_page==$this->_queue->page && $this->_queue->max_page!=0){
            IntegrationData::setData('last_orders_integration_date', date('Y-m-d'), $this->_user->id);
        }

        if ($this->_queue->max_page>0){
            return false; // no need every time    
        }
        $request=$this->_request;
        $request->addParam('resultsLimit', 1);   
        $response = $this->_client->get($request->getRequest());
        // var_dump($response);
        if ($response->errors->faultCode==2){
            echo "api fault code 2".PHP_EOL;
            return 10;
        }
        // die();
        if (!$response->resultsNumberAll){
            echo "no results".PHP_EOL;
            return false;    
        }
        
        $maxPage=ceil($response->resultsNumberAll/self::API_RESULT_COUNT);
        if ($this->_queue->max_page<$maxPage){
            $this->_queue->max_page=$maxPage;
            $this->_queue->save();
        }
        
        return true; 
    }

    private function createOrderObjects()
    {
        echo "creating (createOrderObjects)";
        //creating SOAP client with Authorization header
        $gate = "https://{$this->_user->username}/api/?gate=orders/get/129/soap/wsdl&lang=pol";


        $this->_client=new IdioselClient($gate, $this->_token);    
        $this->_request=new SoapRequest();      
        $this->_request->addParam('returnProducts', 'active'); 
        if (IntegrationData::getData('INITIAL_ORDERS_DONE', $this->_user->id)){
            $begin = new \DateTime('now');

            // if(Orders::find()->where(['user_id' => $this->_user->id])->orderBy(['created_on' => SORT_DESC])->one() !== null) {
            //     $begin = $begin->modify('-1 week');
            // } else {
            //     $begin = $begin->modify('-4 year');
            // }
            // $begin = ;
            $begin = date('Y-m-d H:i:s', strtotime(IntegrationData::getDataValue('last_orders_integration_date', $this->_user->id)));

            echo "BEGIN DATE: ".$begin.PHP_EOL;
                if ($begin){
                    $this->_request->addParam('ordersRange', [
                        'ordersDateRange' => [
                            'ordersDateType' => 'add',
                            'ordersDateBegin' => $begin
                        ]
                    ]);
                }
                // $request['params']['ordersRange'] = [];
                // $request['params']['ordersRange']['ordersDateRange'] = [];
                // $request['params']['ordersRange']['ordersDateRange']['ordersDateType'] = 'add';
                // $request['params']['ordersRange']['ordersDateRange']['ordersDateBegin'] = $begin->format('Y-m') . '-01 00:00:00';
            }
        $this->checkQueueConstraints();
        $this->_request->addParam('resultsLimit', self::API_RESULT_COUNT); 
        $request=$this->_request;   

        echo "request start"; 
        
        try {

            

            


            // $request['params']['ordersBy'] = array();
            // $request['params']['ordersBy'][0] = array();
            // $request['params']['ordersBy'][0]['elementName'] = "order_time";
            // $request['params']['ordersBy'][0]['sortDirection'] = "ASC";
            $request->addParam('ordersBy', [                
                [
                    'elementName'=>'order_time',
                    'sortDirection' => 'ASC'
                ]                
            ]);

                        
            $allItems = [];

            $request->addParam('resultsPage', $this->_queue->page);   
            var_dump($request->getRequest());
            $response = $this->_client->get($request->getRequest());
            var_dump($response);
            // die();

            try {
                // $this->_queue->setMaxPages($response->resultsNumberPage);
                // print_r($response->Results); die;

                if ($this->_queue->page >= $this->_queue->max_page) {
                    IntegrationData::setIsNew('ORDER', false, $this->_user->id);
                    IntegrationData::setData('INITIAL_ORDERS_DONE', 1, $this->_user->id);
                    // IntegrationData::setData('last_orders_integration_date', date('Y-m-d'), $this->_user->id);
                    return 10;
                }

                if(!isset($response->Results)) {
                    var_dump($response); 
                    echo "no res";
                    $this->_queue->increasePage();
                    return true; 
                }

                if(!isset($response)) {
                    var_dump($response); 
                    echo "no res";
                    // $this->_queue->increasePage();
                    return true; 
                }

                
            } catch (\yii\base\ErrorException $e) {
                echo "exception";
                echo $e->getMessage();
                return 0;
            }catch (\Exception $e) {
                echo "exception";
                echo $e->getMessage();
                return 0;
            }

            if ($response->errors && !empty($response->errors->faultString)) {
                echo "respo nse errors";
                return false;
            } 

            // Mapping order statuses from
            $order_statuses_map = [
                'finished_ext' => 'finished',
                'finished' => 'finished',

                'new' => 'created',
                'payment_waiting' => 'created',
                'delivery_waiting' => 'created',
                'on_order' => 'created',
                'packed' => 'created',
                'packed_fulfillment' => 'created',
                'packet_ready' => 'created',
                'ready' => 'created',
                'wait_for_dispatch' => 'created',
                'joined' => 'created',
                'packed_ready' => 'created',

                'suspended' => 'canceled',
                'returned' => 'canceled',
                'missing' => 'canceled',
                'lost' => 'canceled',
                'false' => 'canceled',
                'canceled' => 'canceled',
                'complainted' => 'canceled',
            ];

            foreach ($response->Results as $order) {
                echo "check ".$order->orderId.PHP_EOL;
                if ($order->orderDetails->productsResults == null){
                    echo "empty order".PHP_EOL;
                    continue;
                }
                echo "process products ".PHP_EOL;

                $status = isset($order_statuses_map[$order->orderDetails->orderStatus]) ? $order_statuses_map[$order->orderDetails->orderStatus] : 'created';
                echo $status.PHP_EOL;
                echo $order->orderDetails->orderDispatchDate.PHP_EOL;

                $order_item = [];
                $order_item['order_id'] = $order->orderId;
                $order_item['customer_id'] = $order->clientResult->clientAccount->clientId;
                $order_item['created_on'] = $order->orderDetails->orderAddDate;
                $order_item['finished_on'] = $status == 'finished' ? $order->orderDetails->orderDispatchDate : null;
                if ($status == 'finished' && !$order_item['finished_on']){
                    $order_item['finished_on']=date('Y-m-d');
                }
                $order_item['status'] = $status;
                $order_item['email'] = $order->clientResult->clientAccount->clientEmail;
                $order_item['phone'] = str_replace(' ', '', $order->clientResult->clientAccount->clientPhone1);
                $order_item['zip_code'] = $order->clientResult->clientBillingAddress->clientZipCode;
                $order_item['country_code'] = $order->clientResult->clientBillingAddress->clientCountryId;


                $positions = [];
                foreach ($order->orderDetails->productsResults as $product) {
                    $positions[] = [
                        'product_id' => $product->productId,
                        'amount' => $product->productQuantity,
                        'price' => $product->productOrderPrice
                    ];
                }

                $order_item['order_positions'] = serialize($positions);
                $order_object = Orders::addOrder($order_item, $this->_user->id, $this->_queue->page);
                echo "new id ".$order_object->id.PHP_EOL;
            }
            // die ("!!!!!");
            $this->_queue->increasePage();
            

            return true;
        } catch (\Exception $e) {
            echo $e;
            return false;
        }
    }

    private function createOrAddTempOrderXml($temp): int
    {
        echo "creating file";
        $orders = new \SimpleXmlElement('<ORDERS/>');

        // $year = (int) date('Y');
        // $since_year = $year - 4;

        $integrationDataCurrentPage = $this->_queue->page;
        $integrationDataMaxPage = $this->_queue->max_page;
        $page_size = self::XML_PAGE_SIZE;

        $ordersQuery=Orders::find()->where(['user_id' => $this->_queue->getCurrentUser()->id]);

        $page = $integrationDataCurrentPage;

        if( $integrationDataMaxPage == 0 ) {
            $ordersQueryAll = $ordersQuery->count();
            $pages = ceil($ordersQueryAll / $page_size);
            $this->_queue->max_page=$pages;
            $integrationDataMaxPage=$pages;
            $this->_queue->page=$page;
            $this->_queue->save();
        }

        echo " PAGE ".$page." of ".$integrationDataMaxPage.PHP_EOL;
        // $customers_db = $customers_query->limit($page_size)->offset(($page - 1) * $page_size)->all();
        echo "offset ".($page) * $page_size;
        echo PHP_EOL;
        $orders_db = $ordersQuery->limit($page_size)->offset(($page) * $page_size)->all();

        foreach($orders_db as $order) {
            echo ".";
            // if($order->customer == null){
            //     echo "null customer";
            //     continue;
            // }
            // if($order->customer->email == null){
            //     echo "null customer email";
            //     continue;
            // }
            // echo "one process";

            if (Queue::isDisallowedEmail($order->email)) { // omit allegro etc
                continue;
            }

            $ordChild = $orders->addChild('ORDER');
            $ordChild->addChild('ORDER_ID', $order->order_id);
            $ordChild->addChild('CUSTOMER_ID', $order->customer_id);
            $ordChild->addChild('CREATED_ON', $this->getCorrectSambaDate($order->created_on));

            if ($order->status == 'finished') {
                $ordChild->addChild('FINISHED_ON', $this->getCorrectSambaDate($order->finished_on));
            }

            $ordChild->addChild('STATUS', $order->status);
            $ordChild->addChild('EMAIL', $order->email);
            $ordChild->addChild('PHONE', str_replace(' ', '', $order->phone));
            $ordChild->addChild('ZIP_CODE', $order->zip_code);
            $ordChild->addChild('COUNTRY_CODE', $order->country_code);
            echo $order->id.PHP_EOL;
            $ordItems = $ordChild->addChild('ITEMS');
            foreach ($order->getPositions() as $product) {
                $prodItem = $ordItems->addChild('ITEM');
                $prodItem->addChild('PRODUCT_ID', $product['product_id']);
                $prodItem->addChild('AMOUNT', $product['amount']);
                $prodItem->addChild('PRICE', $product['amount']*$product['price']);
            }
            
            $file_handle = fopen($temp, 'a+');            
            fwrite($file_handle, $ordChild->asXml());
            fclose($file_handle);
            // echo "one processed".PHP_EOL;
        }
        echo "----";
        $page++;
        $this->_queue->page=$page;
        $this->_queue->save();

        if($page > (int) $integrationDataMaxPage) {
            // echo $page.PHP_EOL;
            // echo $integrationDataMaxPage.PHP_EOL;
                // die ("JUZ !!!!!");
            echo "FINISHED ";
            return $this->createOrderXml($file, $temp);

            return 10;
        }
        return true;
    }

    private function createOrderXml(string $file, string $temp)
    {
        // $orders = new \SimpleXMLElement('<ORDERS/>');
        // $orders->addChild('ORDER');
        file_put_contents($file, '');
        $fileContent=file_get_contents($temp);
        $file_handle = fopen($file, 'a+'); 
        fwrite($file_handle, '<?xml version="1.0"?> <ORDERS>');
        fwrite($file_handle, $fileContent);
        fwrite($file_handle, "</ORDERS>");
        fclose($file_handle);
        file_put_contents($temp, '');
        return is_file($file)?10:0;
    }
}
