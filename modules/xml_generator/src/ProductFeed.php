<?php

namespace app\modules\xml_generator\src;

use app\models\Product;
use SoapClient;
use app\models\IntegrationData;

class ProductFeed extends XmlFeed
{
    /**
     * @param null $what
     *
     * @return bool
     *
     */


    const API_RESULT_COUNT=40; // default 100
    const XML_PAGE_SIZE=100; // default 100

    public function generate($what = null): int
    {
        $temp = $this->getFile(true, true);
        $file = $this->getFile(true, false);
            echo "*** ";
            var_dump($this->isFinished());
            echo "*** ";


        if($what == 'objects') {
            return $this->createOrAddTempProductXml($temp);
        }    

        if (!$this->isFinished()) {
            $created = $this->prepareProductXml($temp);
        } else {
            $created = $this->createProductsXml($file, $temp);
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

    private function checkQueueConstraints(){ // todo

        if ($this->_queue->max_page==$this->_queue->page && $this->_queue->max_page!=0){
            IntegrationData::setData('last_products_integration_date', date('Y-m-d'), $this->_user->id);
        }
        
        if ($this->_queue->max_page>0){
            return false; // no need every time    
        }
        $request=$this->_request;
        $request->addParam('resultsLimit', 1);   
        $response = $this->_client->get($request->getRequest());
        // var_dump($request->getRequest());
        // var_dump($response);
        // die();
        if (!$response->resultsNumberAll){
            // echo "Res no: ";
            var_dump($response);
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

    private function createOrAddTempProductXml($temp): int
    {
        echo "FUNC createOrAddTempProductXml".PHP_EOL;



        $gate = "https://{$this->_user->username}/api/?gate=products/get/164/soap/wsdl&lang=eng";

        $this->_client=new IdioselClient($gate, $this->_token);    
        $this->_request=new SoapRequest();      
        $this->_request->addParam('returnProducts', 'active');
        if ($selectedShopId=$this->_user->config->get('customer_set_shop_id')){
            $productShops=array();
            // $productShops[]=$selectedShopId;
            $shop=new \stdClass();
            $shop->shopId=$selectedShopId;

            $productShops[]=$shop;
            $this->_request->addParam('productShops', $productShops);
            // die ("!!");
        }
        $this->_request->addParam('resultsLimit', self::API_RESULT_COUNT); 
        $this->_request->addParam('showPromotionsPrices', 'y');

        if (IntegrationData::getData('INITIAL_PRODUCTS_DONE', $this->_user->id)){
            $this->_request->addParam('productDate', [
                    'productDateMode' => 'modified',
                    'productDateBegin'=> IntegrationData::getDataValue('last_products_integration_date', $this->_user->id),
                    'productDateEnd' => date("Y-m-d", strtotime('tomorrow'))
                ]);  
        }

        $this->checkQueueConstraints();

        // $apiClient = $this->_client;
        echo "start ".PHP_EOL;

        /*
        if ($this->_queue->id==147923){
            $request = new SoapRequest();
            $request->addParam('returnProducts', 'active');
            $request->addParam('resultsPage', 1671);
            $request->addParam('resultsLimit', 10);
            $response = $this->_client->get($request->getRequest());
            foreach ($response->results as $p){
                    echo $p->productId.PHP_EOL;
                if ($p->productId == 18379){
                    $idiosellProduct=new \app\models\IdiosellProduct($p, $this->_user);
                    // var_dump($p->productStocksData->productStocksQuantities);
                    $idiosellProduct->prepareFromApi();
                    die ("MAKAO");
                }
            }
            // print_r($response);
            die ("waitwait");
        }*/

        try {
            //building request
            $request = $this->_request;
            $request->addParam('returnProducts', 'active');    
            $request->addParam('resultsPage', $this->_queue->page);    
            $request->addParam('resultsLimit', self::API_RESULT_COUNT);
            $request->addParam('showPromotionsPrices', 'y');

            var_dump($request->getRequest());
            try {
                $response = $this->_client->get($request->getRequest());

                if(isset($response->errors) && isset($response->errors->faultCode) && $response->errors->faultCode == 2) {
                    $this->_queue->max_page = $this->_queue->page;
                    $this->_queue->save();
                    IntegrationData::setData('INITIAL_PRODUCTS_DONE', 1, $this->_user->id);
                    echo "finished";
                    return 10;
                }

            } 
            catch (\Throwable $e) {
                echo "throwable! ";
                echo $e->getMessage();
                var_dump($request->getRequest());
                return true;
            }



            // $this->_queue->setMaxPages($response->resultsNumberPage);
            $products = new \SimpleXMLElement('<PRODUCTS/>');
            echo $this->_queue->page.PHP_EOL;
            echo $this->_queue->max_page.PHP_EOL;
            if ($this->_queue->page >= $this->_queue->max_page) {
                // IntegrationData::setData('last_products_integration_date', (date('Y-m-d'), $this->_user->id));
                // IntegrationData::setIsNew('PRODUCTS', 0, $this->_user->id);
                IntegrationData::setData('INITIAL_PRODUCTS_DONE', 1, $this->_user->id);
                echo "finished";
                return 10;
            }
            // print_r($response->results); die;
            if ($response->errors && !empty($response->errors->faultString)) {
                echo "fault ".$response->errors->faultString;
                return false;
            }            

            $selectedLanguage=$this->_user->config->get('selected_language');
            $aggregate_groups_as_variants=$this->_user->config->get('aggregate_groups_as_variants');
            if (!$selectedLanguage){
                $selectedLanguage='pol';
            }
            echo "**** LANG ".$selectedLanguage." ******".PHP_EOL;
            foreach ($response->results as $product) {
                var_dump($product->productId);

                // if ($selectedShopId=$this->_user->config->get('customer_set_shop_id')){
                    // die ("TESTY");
                // }
                $product->from_api_page=$this->_queue->page;
                $idiosellProduct=new \app\models\IdiosellProduct($product, $this->_user);
                if (!$idiosellProduct->prepareFromApi()){
                    $this->_queue->setErrorStatus('Błąd zapisu produktu');
                    return 0;
                }
            }
            $this->_queue->increasePage();
            return true;
        } catch (\Exception $e) {
            echo "main error";
            echo $e->getMessage();
            return false;
        }
    }

    /**
     * @param $file
     * @param $temp
     *
     * @return bool
     */

    private function prepareProductXml($temp){
        echo "FUNCTION prepareProductXml".PHP_EOL;
        $aggregate_groups_as_variants=$this->_user->config->get('aggregate_groups_as_variants');
        $products = new \SimpleXMLElement('<PRODUCTS/>');
        $integrationDataCurrentPage = $this->_queue->page;
        $integrationDataMaxPage = $this->_queue->max_page;
        $page_size = self::XML_PAGE_SIZE;

        $query = Product::find()
            ->where(['user_id' => $this->_queue->getCurrentUser()->id]);

        $page = $integrationDataCurrentPage;
        if( $integrationDataMaxPage == 0 ) {
            $customers_all = $query->count();
            $pages = ceil($customers_all / $page_size);
            // $pages+=1; // to fit everything else
            $this->_queue->max_page=$pages;
            $integrationDataMaxPage=$pages;
            $this->_queue->page=$page;
            $this->_queue->save();
        }
        echo " PAGE ".$page." of ".$integrationDataMaxPage.PHP_EOL;
        $res = $query->limit($page_size)->offset(($page) * $page_size)->all();
        $products_str = "";
        foreach ($res as $product) {
            if ($product->response=='-'){
                $par['aggregate_groups_as_variants']=$aggregate_groups_as_variants;
                $products_str .= $product->getXmlEntity($par);
            }else{
                $products_str .= unserialize($product->response);
            }
        }
        $file_handle = fopen($temp, 'a+');            
        fwrite($file_handle, $products_str);
        fclose($file_handle);
        $page++;
        $this->_queue->page=$page;
        $this->_queue->save();
        if($page > (int) $integrationDataMaxPage) {
            // echo $page.PHP_EOL;
            // echo $integrationDataMaxPage.PHP_EOL;
                // die ("JUZ !!!!!");
            echo "FINISHED ";
            return $this->createProductsXml($file, $temp);

            return 10;
        }
        return 1;
    }

    private function createProductsXml($file, $temp): int
    {
        $products = new \SimpleXMLElement('<PRODUCTS/>');
        $products->addChild('PRODUCT');
        $products_str = "";
        file_put_contents($file, str_replace('<PRODUCT/>', file_get_contents($temp), $products->asXML()));
        file_put_contents($temp, '');
        return is_file($file)?10:0;

    }
}
