<?php

namespace app\modules\shoper\models;

use Yii;
use app\modules\shoper\library\App;
use DreamCommerce\ShopAppstoreLib\Resource\Category;
use DreamCommerce\ShopAppstoreLib\Resource\Product as ShoperProduct;
use DreamCommerce\ShopAppstoreLib\Resource\CategoriesTree;
use DreamCommerce\ShopAppstoreLib\Resource\Attribute;
use DreamCommerce\ShopAppstoreLib\Resource\Producer;
use DreamCommerce\ShopAppstoreLib\Resource\User as ShoperUser;
use DreamCommerce\ShopAppstoreLib\Resource\UserAddress;
use DreamCommerce\ShopAppstoreLib\Resource\Subscriber;
use DreamCommerce\ShopAppstoreLib\Resource\UserTag;
use DreamCommerce\ShopAppstoreLib\Resource\Order;
use DreamCommerce\ShopAppstoreLib\Resource\OrderProduct;
use DreamCommerce\ShopAppstoreLib\Resource\Status;
use DreamCommerce\ShopAppstoreLib\Resource\Metafield;
use DreamCommerce\ShopAppstoreLib\Resource\MetafieldValue;
use \app\models\Product;
use \app\models\Customers;
use \app\models\Orders;
use \app\models\Queue;
use app\models\IntegrationData;
use app\services\FeedStorageService;

class Integrator extends ShoperShops{

    const XML_PAGE_SIZE=10000; // 50000

    public static function shoperLog ($message, $queueId = 0) {
        if ($queueId == '31976') {
            Yii::info($message, 'shoper');
        }
    }

    public function prepareConnection(){
        chdir(__DIR__);
        setlocale(LC_ALL, basename(isset($_GET['locale'])?$_GET['locale']:'pl_PL'));
        $config = require '../library/Config.php';
        $app = new App($config);
        // *** zamiast bootstrap
        $app->shopData=$this;
        if (strtotime($this->expires) - time() < 86400) {
            $app->shopData = $app->refreshToken($app->shopData);
        }
        $app->setClient ($app->instantiateClient($app->shopData));
        // ***/ zamiast bootstrap
        return $app;
    }

    public function generateCategoriesTree($client){
        $categoriesResource = new CategoriesTree($client);
        $this->parseCategoriesTree($categoriesResource->get());

    }

    public function parseCategoriesTree($categories){
        foreach ($categories as $res){
            // print_r($res);
            // continue;
            $parentId=$res->id;
            if (empty($res->children)){
                continue;
            }
            foreach ($res->children as $child){
                $cat=ShoperCategories::findOne(['shoper_shops_id'=>$this->id, 'category_id'=>$child->id]);
                if ($cat->parent_id!=$parentId){
                    $cat->parent_id=$parentId;
                    $cat->save();
                }

            }
            $this->parseCategoriesTree($res->children);
        }
    }

    public function generateCategory($queue){
        $app=$this->prepareConnection();

        $client = $app->getClient();
        $categoriesResource = new Category($client);
        if ($queue->page){
            $categoriesResource->page($queue->page+1);
            // filter page
        }


        $categoriesResponse=$categoriesResource->get();
        if ($queue->max_page<$categoriesResponse->pages){
            $queue->max_page=$categoriesResponse->pages;
        }
        $queue->page=$categoriesResponse->page;
        $queue->save();



        foreach ($categoriesResponse as $res){
            $category=ShoperCategories::findOne(['shoper_shops_id'=>$this->id, 'category_id'=>$res->category_id]);
            if (!$category){
                $category = new ShoperCategories(['shoper_shops_id'=>$this->id, 'category_id'=>$res->category_id]);
            }
            $category->order=$res->order;
            $category->root=$res->root;
            $category->in_loyalty=$res->in_loyalty;
            if (!$category->save()){
                print_r($category->getErrors());
            }
            foreach ($res->translations as $lang=>$trans){
                $langCat=ShoperCategoriesLanguage::findOne(['shoper_categories_id'=>$category->id]);
                if (!$langCat){
                    $langCat=new ShoperCategoriesLanguage(['shoper_categories_id'=>$category->id]);
                }
                $langCat->translation=$lang;
                $langCat->name=$trans->name;
                $langCat->description=$trans->description;
                $langCat->description_bottom=$trans->description_bottom;
                $langCat->active=$trans->active;
                $langCat->isdefault=$trans->isdefault;
                $langCat->seo_title=$trans->seo_title;
                $langCat->seo_description=mb_substr($trans->seo_description, 0,250);
                $langCat->seo_keywords=mb_substr($trans->seo_keywords, 0,250);
                $langCat->permalink=$trans->permalink;
                if (!$langCat->save()){
                    print_r($langCat->getErrors());
                }
            }
        }

        if ($queue->max_page <= $queue->page){
            $this->generateCategoriesTree($client);
            echo "[category] building parent relations" . PHP_EOL;
        }
        return true;

        // $queue->save();

        // var_dump($categoriesResponse->count);
        // var_dump($categoriesResponse->pages);
        // var_dump($categoriesResponse->page);
    }

    public function generateAttributes($queue){
        

        $parameters=$queue->additionalParameters;
        if (!isset($parameters['attributes'])){
            $parameters['attributes']=[];
            $parameters['attributes']['page']=0;
            $parameters['attributes']['max_page']=0;
        }
        if (isset($parameters['attributes']['done']) && $parameters['attributes']['done'] ==1){
            echo "[product] attributes skip done" . PHP_EOL;
            return true;

        }
        $app=$this->prepareConnection();
        $resource = new Attribute($app->getClient());


        if (isset($parameters['attributes_prev']['page']) && $parameters['attributes']['page']<$parameters['attributes_prev']['page']){
            $parameters['attributes']['page']=$parameters['attributes_prev']['page']-1;
        }

        if ($parameters['attributes']['page']){
            $resource->page($parameters['attributes']['page']+1);
            // filter page
        }
        $response=$resource->get();


        if ($parameters['attributes']['max_page']<$response->pages){
            $parameters['attributes']['max_page']=$response->pages;
        }
        $parameters['attributes']['page']=$response->page;
        echo "[product] attributes start ".$parameters['attributes']['page']." of ".$parameters['attributes']['max_page']. PHP_EOL;

        $queue->additionalParameters=$parameters;
        $queue->save();
        foreach ($response as $res){
            $Attribute=ShoperAttributes::findOne(['shoper_shops_id'=>$this->id, 'attribute_id'=>$res->attribute_id]);
            if (!$Attribute){
                $Attribute= new ShoperAttributes(['shoper_shops_id'=>$this->id, 'attribute_id'=>$res->attribute_id]);
            }
            $Attribute->attribute_id=$res->attribute_id;
            $Attribute->name=$res->name;
            $Attribute->description=$res->description?$res->description:'no desc';
            if (!$Attribute->save()){
                print_r($Attribute->getErrors());
            }
            foreach ($res->options as $opt){
                $option=ShoperAttributesOptions::findOne(['option_id'=>$opt->option_id, 'shoper_attributes_id'=>$Attribute->id]);
                if (!$option){
                    $option= new ShoperAttributesOptions(['option_id'=>$opt->option_id, 'shoper_attributes_id'=>$Attribute->id]);
                }
                $option->value=$opt->value;
                $option->save();
            }
        }

        if ($parameters['attributes']['max_page']<=$parameters['attributes']['page']){
            echo "[product] attributes done" . PHP_EOL;
            $parameters['attributes']['done']=1;
            $queue->additionalParameters=$parameters;
            $queue->save();
            return true;
        }
        return false;
    }

    public function generateSubscriberData($queue){
        $parameters=$queue->additionalParameters;
        if (!isset($parameters['subscriber'])){
            $parameters['subscriber']=[];
            $parameters['subscriber']['page']=0;
            $parameters['subscriber']['max_page']=0;
        }
        $app=$this->prepareConnection();
        $resource = new Subscriber($app->getClient());
        // if (isset($parameters['subscriber_prev']['page']) && $parameters['subscriber']['page']<$parameters['subscriber_prev']['page']){
        //     $parameters['subscriber']['page']=$parameters['subscriber_prev']['page']-1;
        // }
        if ($parameters['subscriber']['page']){
            $resource->page($parameters['subscriber']['page']+1);
            // filter page
        }

        if (IntegrationData::getDataValue('INITIAL_CUSTOMERS_DONE', $queue->getCurrentUser()->id) && IntegrationData::getLastCustomerIntegrationDate($queue->getCurrentUser()->id)){
            $resource->filters([
                // 'origin' => [0,1,2],
                'updated_at'=>[
                    '>='=>IntegrationData::getLastCustomerIntegrationDate($queue->getCurrentUser()->id)
                ] 
            ]);
        }

        $response=$resource->get();



        echo "[customer] subscriber page " . $parameters['subscriber']['page'] . " of " . $parameters['subscriber']['max_page'] . PHP_EOL;

        if ($parameters['subscriber']['max_page']<$response->pages){
            $parameters['subscriber']['max_page']=$response->pages;
        }
        $parameters['subscriber']['page']=$response->page;

        $queue->additionalParameters=$parameters;
        $queue->save();

        foreach ($response as $res){
            $Subscriber=ShoperSubscribers::findOne(['shoper_shops_id'=>$this->id, 'subscriber_id'=>$res->subscriber_id]);
            if (!$Subscriber){
                $Subscriber= new ShoperSubscribers(['shoper_shops_id'=>$this->id, 'subscriber_id'=>$res->subscriber_id]);
            }
            $Subscriber->email=$res->email;
            $Subscriber->active=$res->active;
            $Subscriber->dateadd=$res->dateadd;
            $Subscriber->ipaddress=$res->ipaddress;
            $Subscriber->lang_id=$res->lang_id;
            $Subscriber->groups=serialize($res->groups);
            if (!$Subscriber->save()){
                print_r($Subscriber->getErrors());
            }
        }

        if ($parameters['subscriber']['max_page']<=$parameters['subscriber']['page']){
            echo "[customer] subscribers done" . PHP_EOL;
            return true;
        }
        return false;
    }

    public function generateTags($queue){
        $parameters=$queue->additionalParameters;
        if (!isset($parameters['tagslist'])){
            $parameters['tagslist']=[];
            $parameters['tagslist']['page']=0;
            $parameters['tagslist']['max_page']=0;
        }

        $app=$this->prepareConnection();
        $resource = new UserTag($app->getClient());
        if (isset($parameters['tagslist_prev']['page']) && $parameters['tagslist']['page']<$parameters['tagslist_prev']['page']){
            $parameters['tagslist']['page']=$parameters['tagslist_prev']['page']-1;
        }
        if ($parameters['tagslist']['page']){
            $resource->page($parameters['tagslist']['page']+1);
            // filter page
        }
        $response=$resource->get();



        if ($parameters['tagslist']['max_page']<$response->pages){
            $parameters['tagslist']['max_page']=$response->pages;
        }
        $parameters['tagslist']['page']=$response->page;

        $queue->additionalParameters=$parameters;
        $queue->save();

        foreach ($response as $res){
            $Tag=ShoperUserTag::findOne(['shoper_shops_id'=>$this->id, 'tag_id'=>$res->tag_id]);
            if (!$Tag){
                $Tag= new ShoperUserTag(['shoper_shops_id'=>$this->id, 'tag_id'=>$res->tag_id]);
            }
            $Tag->name=$res->name;
            $Tag->lang_id=$res->lang_id;
            if (!$Tag->save()){
                print_r($Tag->getErrors());
            }
        }

        if ($parameters['tagslist']['max_page']<=$parameters['tagslist']['page']){
            echo "[customer] tags done" . PHP_EOL;
            return true;
        }
        return false;
    }
    public function generateAddressData($queue) {
        $parameters=$queue->additionalParameters;
        if (!isset($parameters['address'])){
            $parameters['address']=[];
            $parameters['address']['page']=0;
            $parameters['address']['max_page']=0;
        }

        $app=$this->prepareConnection();
        $resource = new UserAddress($app->getClient());

        

        // if (isset($parameters['address_prev']['page']) &&  $parameters['address']['page']<$parameters['address_prev']['page']){
        //     echo "page is ".$parameters['address']['page'].PHP_EOL;
        //     echo "set page to address prev! ".PHP_EOL;
        //     $parameters['address']['page']=$parameters['address_prev']['page']-1;
        // }

        if ($parameters['address']['page']){
            $resource->page($parameters['address']['page']+1);
            // filter page
        }

        if (IntegrationData::getDataValue('INITIAL_CUSTOMERS_DONE', $queue->getCurrentUser()->id) && IntegrationData::getLastCustomerIntegrationDate($queue->getCurrentUser()->id)){
            $resource->filters([
                // 'origin' => [0,1,2],
                'updated_at'=>[
                    '>='=>IntegrationData::getLastCustomerIntegrationDate($queue->getCurrentUser()->id)
                ] 
            ]);
        }

        $response=$resource->get();

        if ($parameters['address']['max_page']<$response->pages){
            $parameters['address']['max_page']=$response->pages;
        }
        $parameters['address']['page']=$response->page;

        echo "[customer] address page " . $parameters['address']['page'] . " of " . $parameters['address']['max_page'] . ", pages in response ".$response->pages.PHP_EOL;

        $queue->additionalParameters=$parameters;
        $queue->save();

        foreach ($response as $res) {
            $Address=ShoperUserAddress::findOne(['shoper_shops_id'=>$this->id, 'address_book_id'=>$res->address_book_id]);
            if (!$Address) {
                $Address= new ShoperUserAddress(['shoper_shops_id'=>$this->id, 'address_book_id'=>$res->address_book_id]);
            }

            $Address->user_id=$res->user_id;
            $Address->address_name=$res->address_name;
            $Address->company_name=$res->company_name;
            $Address->pesel=$res->pesel;
            $Address->firstname=$res->firstname;
            $Address->lastname=$res->lastname;
            $Address->street_1=$res->street_1;
            $Address->street_2=$res->street_2;
            $Address->city=$res->city;
            $Address->zip_code=$res->zip_code;
            $Address->state=mb_substr($res->state, 0,15);
            $Address->country=$res->country;
            $Address->default=$res->default;
            $Address->shipping_default=$res->shipping_default;
            $Address->phone=$res->phone;
            $Address->sortkey=$res->sortkey;
            $Address->country_code=$res->country_code;
            $Address->tax_identification_number=$res->tax_identification_number;

            if (!$Address->save()) {
                print_r($Address->getErrors());
            }
        }

        if ($parameters['address']['max_page']<=$parameters['address']['page']) {
            echo "[customer] addresses done" . PHP_EOL;
            return true;
        }

        return false;
    }

    
    public function generateCustomer($queue){
        $parameters=$queue->additionalParameters;
        if (!isset($parameters['tagslist']['max_page']) || $parameters['tagslist']['max_page']>$parameters['tagslist']['page']){
            if (!$this->generateTags($queue)) {
                return false;
            }
        }

        if (!isset($parameters['address']['max_page']) || $parameters['address']['max_page']>$parameters['address']['page']){
            if (!$this->generateAddressData($queue)) {
                return false;
            }
        }

        if (!isset($parameters['subscriber']['max_page']) || $parameters['subscriber']['max_page']>$parameters['subscriber']['page']){
            if (!$this->generateSubscriberData($queue)){
                return false;
            }
        }
        $app=$this->prepareConnection();

        $client = $app->getClient();
        $resource = new ShoperUser($client);
        if ($queue->page){
            $resource->page($queue->page+1);
        }

        echo "[customer] get from api" . PHP_EOL;

        if ($queue->getCurrentUser()->getIncrementalFeedFlag()) {
            if ($queue->page == 0) {
                Customers::deleteAll(['user_id' => $queue->getCurrentUser()->id]); // delete all obsolete entries
            }
            $date2weeksago = date('Y-m-d', strtotime('-2 weeks'));
            IntegrationData::setLastCustomerIntegrationDate($date2weeksago, $queue->getCurrentUser()->id);
        }

        if (IntegrationData::getLastCustomerIntegrationDate($queue->getCurrentUser()->id)){
            $resource->filters([
                // 'origin' => [0,1,2],
                'updated_at'=>[
                    '>='=>IntegrationData::getLastCustomerIntegrationDate($queue->getCurrentUser()->id)
                ] 
            ]);
        }

        $response=$resource->get();
        if ($queue->max_page<$response->pages){
            $queue->max_page=$response->pages;
        }
        // var_dump($response);
        $queue->page=$response->page;
        $queue->save();

        foreach ($response as $res) {
            $Customer = Customers::findOne(['customer_id'=>$res->user_id, 'user_id' => $queue->getCurrentUser()->id]);
            if (!$Customer) {
                $Customer = new Customers(['customer_id'=>$res->user_id, 'user_id' => $queue->getCurrentUser()->id]);
            }

            $Customer->email=$res->email;
            $Customer->registration=$res->date_add;
            $Customer->first_name=$res->firstname;
            $Customer->lastname=$res->lastname;
            $Customer->page=$queue->page;
            $AddressData=ShoperUserAddress::findOne(['shoper_shops_id'=>$this->id, 'default'=>1, 'user_id'=>$Customer->customer_id]);

            if ($AddressData){
                $Customer->zip_code=$AddressData->zip_code;
                $Customer->phone=$AddressData->phone;
            }

            $Customer->newsletter_frequency='never';
            $Customer->sms_frequency='never';
            $Customer->nlf_time=$res->date_add;

            if ($res->newsletter){
                $Subscriber=ShoperSubscribers::findOne(['shoper_shops_id'=>$this->id, 'email'=>$Customer->email]);
                if ($Subscriber){
                    $Customer->newsletter_frequency=$Subscriber->active?'every day':'never';
                    $Customer->nlf_time=$Subscriber->dateadd;
                }
            }

            $Customer->data_permission='full'; // pewnie z adresów trzeba;
            $Customer->tags=serialize($res->tags);

            if (!$Customer->save()) {
                print_r($Customer->getErrors());
                die("!!");
            }
        }

        if ($queue->max_page <= $queue->page){
            IntegrationData::setLastCustomerIntegrationDate(date('Y-m-d'), $queue->getCurrentUser()->id);
            IntegrationData::setData('INITIAL_CUSTOMERS_DONE', 1, $queue->getCurrentUser()->id);
        }

        return true;
    }

    public function generateProducers($queue){
        $parameters=$queue->additionalParameters;
        if (!isset($parameters['producers'])){
            $parameters['producers']=[];
            $parameters['producers']['page']=0;
            $parameters['producers']['max_page']=0;
        }
        if (isset($parameters['producers']['done']) && $parameters['producers']['done'] ==1){
            echo "[product] producers skip done" . PHP_EOL;
            return true;

        }
        // print_r($parameters);
        $app=$this->prepareConnection();
        $resource = new Producer($app->getClient());

        if (isset($parameters['producers_prev']['page']) && $parameters['producers']['page']<$parameters['producers_prev']['page']){
            $parameters['producers']['page']=$parameters['producers_prev']['page']-1;
        }

        if ($parameters['producers']['page']){
            $resource->page($parameters['producers']['page']+1);
            // filter page
        }
        $response=$resource->get();

        if ($parameters['producers']['max_page']<$response->pages){
            $parameters['producers']['max_page']=$response->pages;
        }
        $parameters['producers']['page']=$response->page;

        echo "[product] producer start ".$parameters['attributes']['page']." of ".$parameters['attributes']['max_page']. PHP_EOL;

        $queue->additionalParameters=$parameters;
        $queue->save();
        foreach ($response as $res){
            $ShoperProducer=ShoperProducer::findOne(['shoper_shops_id'=>$this->id, 'producer_id'=>$res->producer_id]);
            if (!$ShoperProducer){
                $ShoperProducer= new ShoperProducer(['shoper_shops_id'=>$this->id, 'producer_id'=>$res->producer_id]);
            }
            $ShoperProducer->name=$res->name;
            if (!$ShoperProducer->save()){
                print_r($ShoperProducer->getErrors());
            }
        }
        print_r($parameters);

        if ($parameters['producers']['max_page']<=$parameters['producers']['page']){
            echo "[product] producers done" . PHP_EOL;
            $parameters['producers']['done']=1;
            $queue->additionalParameters=$parameters;
            $queue->save();
            return true;
        }
        return false;
    }

    public function generateProduct($queue){

        if ($queue->page==0){
            if (!$this->generateAttributes($queue)){
                return false;
            }
            if (!$this->generateProducers($queue)){
                return false;
            }
        }

        $user = $queue->getCurrentUser();
        $app  = $this->prepareConnection();

        $client = $app->getClient();
        $resource = new ShoperProduct($client);
        if ($queue->page){
            $resource->page($queue->page+1);
        }

        $initialDone = IntegrationData::getDataValue('INITIAL_PRODUCTS_DONE', $user->id);
        $lastDone    = IntegrationData::getDataValue('LAST_PRODUCTS_DONE', $user->id);

        if ($initialDone && $lastDone) {
            echo "[product] incremental from " . $lastDone . PHP_EOL;
            $resource->filters(['updated_at' => ['>=' => $lastDone]]);
        }

        echo "[product] fetching from API" . PHP_EOL;
        $response=$resource->get();
        echo "[product] got ".$response->pages." from API" . PHP_EOL;
        if ($queue->max_page<$response->pages){
            $queue->max_page=$response->pages;
        }
        $queue->page=$response->page;
        $queue->save();

        $producerMap = [];
        foreach (ShoperProducer::find()->where(['shoper_shops_id' => $this->id])->all() as $p) {
            $producerMap[$p->producer_id] = $p->name;
        }

        $categoryMap = [];
        foreach (ShoperCategories::find()->where(['shoper_shops_id' => $this->id])->all() as $c) {
            $categoryMap[$c->category_id] = $c;
        }

        $attributeMap = [];
        foreach (ShoperAttributes::find()->where(['shoper_shops_id' => $this->id])->all() as $a) {
            $attributeMap[$a->attribute_id] = $a;
        }

        $userUrl = $user->getUrl();

        $productsProcessed=0;

        foreach ($response as $res){
            echo "[product] processing product " . $res->product_id . PHP_EOL;        

            foreach ($res->translations as $lang=>$trans){
                $Product = Product::findOne(['user_id' => $user->id, 'PRODUCT_ID' => $res->product_id, 'translation' => $lang])
                    ?? new Product(['user_id' => $user->id, 'PRODUCT_ID' => $res->product_id, 'translation' => $lang]);

                $Product->from_api_page = $queue->page;
                $Product->URL   = $trans->permalink;
                $Product->TITLE = $trans->name;
                $Product->PRICE = str_replace(',', '.', $res->stock->comp_promo_price);
                $Product->BRAND = $producerMap[$res->producer_id] ?? 'brak';
                $Product->DESCRIPTION           = $trans->description;
                $Product->PRICE_BEFORE_DISCOUNT = str_replace(',', '.', $res->stock->price);
                $Product->PRICE_BUY             = str_replace(',', '.', $res->stock->price_buying);

                if ($res->main_image) {
                    $imgName = (isset($res->main_image->unic_name) && $res->main_image->unic_name !== '')
                        ? $res->main_image->unic_name
                        : $res->main_image->gfx_id;
                    $Product->IMAGE = $userUrl . '/userdata/public/gfx/' . $imgName . '/pic.' . $res->main_image->extension;
                }

                $Product->PRODUCT_LINE = 'brak';

                if (isset($categoryMap[$res->category_id])) {
                    $Product->CATEGORYTEXT = $categoryMap[$res->category_id]->getFullPath($lang);
                }else{
                    $Product->CATEGORYTEXT = 'brak';
                }
                $Product->SHOW = $trans->active;

                $parametersArray = [];
                if ($res->attributes) {
                    foreach ($res->attributes as $attributeOptions) {
                        foreach ($attributeOptions as $k => $v) {
                            if (isset($attributeMap[$k])) {
                                $parametersArray[] = ['NAME' => $attributeMap[$k]->name, 'VALUE' => $v];
                            }
                        }
                    }
                }
                $Product->PARAMETERS = serialize($parametersArray);

                $variantArray = [];
                if ($res->options) {
                    foreach ($res->options as $optionId) {
                        $variantArray[] = ['PRODUCT_ID' => $optionId];
                    }
                }
                $Product->VARIANT     = serialize($variantArray);
                $Product->STOCK       = $res->stock->stock;
                $Product->response    = serialize($res);
                $Product->params_hash = md5(serialize($res));

                if (!$Product->save()){
                    print_r($Product->getErrors());
                }else{
                    $productsProcessed++;
                }
            }
        }

        echo "[product] processed $productsProcessed products on page " . $queue->page . PHP_EOL;


        if ($queue->max_page <= $queue->page){
            IntegrationData::setData('LAST_PRODUCTS_DONE', date('Y-m-d'), $user->id);
            IntegrationData::setData('INITIAL_PRODUCTS_DONE', 1, $user->id);
        }

        return true;
    }

    public function generateStatuses($queue){
        $parameters=$queue->additionalParameters;
        if (!isset($parameters['statuses'])){
            $parameters['statuses']=[];
            $parameters['statuses']['page']=0;
            $parameters['statuses']['max_page']=0;
        }
        // print_r($parameters);
        $app=$this->prepareConnection();
        $resource = new Status($app->getClient());

        if (isset($parameters['statuses_prev']['page']) && $parameters['statuses']['page']<$parameters['statuses_prev']['page']){
            $parameters['statuses']['page']=$parameters['statuses_prev']['page']-1;
        }

        if ($parameters['statuses']['page']){
            $resource->page($parameters['statuses']['page']+1);
            // filter page
        }
        $response=$resource->get();

        if ($parameters['statuses']['max_page']<$response->pages){
            $parameters['statuses']['max_page']=$response->pages;
        }
        $parameters['statuses']['page']=$response->page;
        // print_r($parameters);

        $queue->additionalParameters=$parameters;
        $queue->save();
        foreach ($response as $res){
            foreach ($res->translations as $lang=>$trans){
                $ShoperStatus=ShoperStatus::findOne(['shoper_shops_id'=>$this->id, 'status_id'=>$res->status_id, 'translation'=>$lang]);
                if (!$ShoperStatus){
                    $ShoperStatus= new ShoperStatus(['shoper_shops_id'=>$this->id, 'status_id'=>$res->status_id, 'translation'=>$lang]);
                }
                $ShoperStatus->active=$res->active;
                $ShoperStatus->default=$res->default;
                $ShoperStatus->type=$res->type;
                $ShoperStatus->order=$res->order;
                $ShoperStatus->name=$trans->name;
                $ShoperStatus->message=$trans->message?$trans->message:'not set';
                if (!$ShoperStatus->save()){
                    print_r($ShoperStatus->getErrors());
                }
            }

        }

        if ($parameters['statuses']['max_page']<=$parameters['statuses']['page']){
            echo "[order] statuses done" . PHP_EOL;
            return true;
        }
        return false;
    }

    
    public function generateOrder($queue) {
        if ($queue->page == 0) {
            if (!$this->generateStatuses($queue)){
                return false;
            }
        }

        $user = $queue->getCurrentUser();

        $app=$this->prepareConnection();

        $client = $app->getClient();
        $resource = new Order($client);
        if ($queue->page) {
            $resource->page($queue->page + 1);
        }

        $lastOrdersDone = IntegrationData::getDataValue('LAST_ORDERS_DONE', $user->id);

        if ($user->getIncrementalFeedFlag()) {
            if ($queue->page == 0) {
                Orders::deleteAll(['user_id' => $user->id]);
            }
            $lastOrdersDone = date('Y-m-d', strtotime('-2 weeks'));
            IntegrationData::setData('LAST_ORDERS_DONE', $lastOrdersDone, $user->id);
        }

        if ($lastOrdersDone) {
            $resource->filters([
                'updated_at' => ['>=' => $lastOrdersDone]
            ]);
        }

        $response = $resource->get();
        if ($queue->max_page < $response->pages) {
            $queue->max_page = $response->pages;
        }

        echo "[order] page " . $response->page . " of " . $response->pages . PHP_EOL;

        $queue->page = $response->page;
        $queue->save();

        $statusMap = [];
        foreach (ShoperStatus::find()->where(['shoper_shops_id' => $this->id])->all() as $s) {
            $statusMap[$s->status_id] = $s->sambaStatus;
        }

        foreach ($response as $res) {
            $Order = Orders::find()->where(['order_id' => $res->order_id, 'user_id' => $user->id])->one()
                ?? new Orders(['user_id' => $user->id, 'order_id' => $res->order_id]);

            $Order->customer_id = $res->user_id;
            $Order->created_on = $res->date;
            $Order->finished_on = $res->date;
            $Order->status = $statusMap[$res->status_id] ?? null;
            $Order->email = $res->email;
            $Order->phone = $res->delivery_address->phone;
            $Order->zip_code = $res->delivery_address->postcode;
            $Order->country_code = $res->delivery_address->country_code;
            $Order->page = $queue->page + 1;

            $orderProduct = new OrderProduct($client);
            $orderProduct->filters(['order_id' => $res->order_id]);
            $items = [];
            foreach ($orderProduct->get() as $orderItem) {
                $items[] = [
                    'product_id' => $orderItem->product_id,
                    'amount'     => $orderItem->quantity,
                    'price'      => $orderItem->quantity * $orderItem->price,
                ];
            }
            $Order->order_positions = serialize($items);

            if (!$Order->save()) {
                print_r($Order->getErrors());
            }
        }

        if ($queue->max_page <= $queue->page) {
            IntegrationData::setData('LAST_ORDERS_DONE', date('Y-m-d'), $user->id);
            IntegrationData::setData('INITIAL_ORDERS_DONE', 1, $user->id);
        }

        return true;
    }

    public function prepareFile($queue){
        echo "[" . $queue->integration_type . "] preparing file" . PHP_EOL;
        switch ($queue->integration_type){
            case 'product':
                return $this->prepareProductsFile($queue);
            break;
            case 'category':
                return $this->prepareCategoriesFile($queue);
            break;
            case 'customer':
                return $this->prepareCustomersFile($queue);
            break;
            case 'order':
                return $this->prepareOrdersFile($queue);
            break;
            case 'tags':
                return true; // no tags file
            break;
        }
        return false;
    }

    public function prepareDiversedFile($queue){
        echo "[" . $queue->integration_type . "] preparing file" . PHP_EOL;
        switch ($queue->integration_type){
            case 'product':
                return $this->prepareProductsFile($queue);
            break;
            case 'category':
                return $this->prepareCategoriesFile($queue);
            break;
            case 'customer':
                if (!$this->isFinished($queue)){
                    return $this->prepareCustomersDiversedFile($queue);
                }else{
                    return $this->createCustomerXml();
                }
            break;
            case 'order':
                return $this->prepareOrdersFile($queue);
            break;
            case 'tags':
                return true; // no tags file
            break;
        }
        return false;
    }

    public function isFinished($queue)
    {
        if($queue->max_page == 0 && $queue->page == 0) return false;

        return $queue->page >= $queue->max_page;
    }

    public function prepareProductsFile($queue): bool
    {
        $query   = Product::find()->where(['user_id' => $queue->getCurrentUser()->id]);
        $storage = $this->getStorage();

        if ($storage) {
            return $this->prepareProductsFileChunked($query, $storage);
        }
        return $this->prepareProductsFileLocal($query);
    }

    private function prepareProductsFileChunked($query, FeedStorageService $storage): bool
    {
        $batchSize    = 3000;
        $finalKey     = $this->getMinioProductsKey();
        $chunkBaseKey = $finalKey . '.s' . $batchSize;

        $storage->deleteChunks($chunkBaseKey);

        $chunkIndex = 0;
        foreach ($query->batch($batchSize) as $batch) {
            $xml = new \XMLWriter();
            $xml->openMemory();
            foreach ($batch as $product) {
                $this->writeProductXml($xml, $product);
            }
            $storage->putChunk($chunkBaseKey, $chunkIndex, $xml->outputMemory(true));
            echo "[product] chunk {$chunkIndex} saved (" . count($batch) . " products)" . PHP_EOL;
            $chunkIndex++;
        }

        $missing = [];
        for ($i = 0; $i < $chunkIndex; $i++) {
            if (!$storage->chunkExists($chunkBaseKey, $i)) {
                $missing[] = $i;
            }
        }
        if ($missing) {
            echo "[product] ERROR: missing chunks: " . implode(', ', $missing) . PHP_EOL;
            $storage->deleteChunks($chunkBaseKey);
            return false;
        }

        $tmpFile = sys_get_temp_dir() . '/' . $this->shop . '-products-' . getmypid() . '.xml';
        if (!$storage->collectChunksToFile($chunkBaseKey, $tmpFile, $chunkIndex, '<?xml version="1.0" encoding="UTF-8"?><PRODUCTS>', '</PRODUCTS>')) {
            return false;
        }
        $storage->putFromFile($finalKey, $tmpFile, 'application/xml');
        @unlink($tmpFile);
        return true;
    }

    private function prepareProductsFileLocal($query): bool
    {
        $tmpFile = sys_get_temp_dir() . '/' . $this->shop . '-products-' . getmypid() . '.xml';

        $xml = new \XMLWriter();
        if (!$xml->openUri($tmpFile)) {
            return false;
        }
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('PRODUCTS');

        foreach ($query->each(3000) as $product) {
            $this->writeProductXml($xml, $product);
        }

        $xml->endElement();
        $xml->endDocument();
        $xml->flush();

        $dest = $this->getProductsFile();
        $ok   = rename($tmpFile, $dest);
        if (!$ok) {
            @unlink($tmpFile);
        }
        return $ok;
    }

    private function writeProductXml(\XMLWriter $xml, $product): void
    {
        $xml->startElement('PRODUCT');
        $xml->writeElement('SHOW', $product->SHOW ? 'TRUE' : 'FALSE');
        $xml->writeElement('PRODUCT_ID', $product->PRODUCT_ID);
        $xml->writeElement('URL', $product->URL);
        $xml->writeElement('TITLE', $product->TITLE);
        $xml->writeElement('PRICE', str_replace(',', '.', $product->PRICE));
        $xml->writeElement('BRAND', $product->BRAND);
        $xml->writeElement('DESCRIPTION', $product->DESCRIPTION);
        $xml->writeElement('PRICE_BEFORE_DISCOUNT', $product->PRICE_BEFORE_DISCOUNT);
        $xml->writeElement('PRICE_BUY', str_replace(',', '.', $product->PRICE_BUY));
        $xml->writeElement('IMAGE', $product->IMAGE);
        $xml->writeElement('PRODUCT_LINE', $product->PRODUCT_LINE);
        $xml->writeElement('CATEGORYTEXT', $product->CATEGORYTEXT);

        $xml->startElement('PARAMETERS');
        foreach (unserialize($product->PARAMETERS) as $param) {
            $xml->startElement('PARAMETER');
            $xml->writeElement('NAME', $param['NAME']);
            $xml->writeElement('VALUE', $param['VALUE']);
            $xml->endElement();
        }
        $xml->endElement(); // PARAMETERS

        foreach (unserialize($product->VARIANT) as $variant) {
            $xml->startElement('VARIANT');
            foreach ($variant as $k => $v) {
                $xml->writeElement($k, $v);
            }
            $xml->endElement(); // VARIANT
        }

        $xml->writeElement('STOCK', $product->STOCK);
        $xml->endElement(); // PRODUCT
    }
    public function prepareCategoriesFile($queue){
        echo "[category] building XML file" . PHP_EOL;
        $categories = new \SimpleXMLElement('<CATEGORY/>');
        foreach (ShoperCategories::find()->where(['shoper_shops_id' => $this->id, 'parent_id'=>0])->all() as $category) {
            $item = $categories->addChild('ITEM');
            $item->addChild('TITLE', htmlspecialchars($category->getTranslated()->name));
            $item->addChild('URL', $category->getTranslated()->permalink);
            $category->getChildren($item);
        }
        $storage = $this->getStorage();
        if ($storage) {
            $storage->put($this->getMinioCategoriesKey(), $categories->asXML(), 'application/xml');
            return true;
        }
        if (file_put_contents($this->getCategoriesFile(), $categories->asXML())) {
            return true;
        }


        return false;
    }

    public function prepareCustomersFile($queue): bool
    {
        $query   = Customers::find()->where(['user_id' => $queue->getCurrentUser()->id]);
        $storage = $this->getStorage();
        if ($storage) {
            return $this->prepareCustomersFileChunked($query, $storage);
        }
        return $this->prepareCustomersFileLocal($query);
    }

    private function prepareCustomersFileChunked($query, FeedStorageService $storage): bool
    {
        $batchSize    = 3000;
        $finalKey     = $this->getMinioCustomersKey();
        $chunkBaseKey = $finalKey . '.s' . $batchSize;
        $storage->deleteChunks($chunkBaseKey);

        $chunkIndex = 0;
        foreach ($query->batch($batchSize) as $batch) {
            $xml = new \XMLWriter();
            $xml->openMemory();
            foreach ($batch as $customer) {
                $this->writeCustomerXml($xml, $customer);
            }
            $storage->putChunk($chunkBaseKey, $chunkIndex, $xml->outputMemory(true));
            echo "[customer] chunk {$chunkIndex} saved (" . count($batch) . " customers)" . PHP_EOL;
            $chunkIndex++;
        }

        $missing = [];
        for ($i = 0; $i < $chunkIndex; $i++) {
            if (!$storage->chunkExists($chunkBaseKey, $i)) {
                $missing[] = $i;
            }
        }
        if ($missing) {
            echo "[customer] ERROR: missing chunks: " . implode(', ', $missing) . PHP_EOL;
            $storage->deleteChunks($chunkBaseKey);
            return false;
        }

        $tmpFile = sys_get_temp_dir() . '/' . $this->shop . '-customers-' . getmypid() . '.xml';
        if (!$storage->collectChunksToFile($chunkBaseKey, $tmpFile, $chunkIndex, '<?xml version="1.0" encoding="UTF-8"?><CUSTOMERS>', '</CUSTOMERS>')) {
            return false;
        }
        $storage->putFromFile($finalKey, $tmpFile, 'application/xml');
        @unlink($tmpFile);
        return true;
    }

    private function prepareCustomersFileLocal($query): bool
    {
        $tmpFile = sys_get_temp_dir() . '/' . $this->shop . '-customers-' . getmypid() . '.xml';
        $xml = new \XMLWriter();
        if (!$xml->openUri($tmpFile)) {
            return false;
        }
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('CUSTOMERS');
        foreach ($query->each(3000) as $customer) {
            $this->writeCustomerXml($xml, $customer);
        }
        $xml->endElement();
        $xml->endDocument();
        $xml->flush();
        $dest = $this->getCustomersFile();
        $ok   = rename($tmpFile, $dest);
        if (!$ok) {
            @unlink($tmpFile);
        }
        return $ok;
    }

    private function writeCustomerXml(\XMLWriter $xml, $customer): void
    {
        $xml->startElement('CUSTOMER');
        $xml->writeElement('CUSTOMER_ID', $customer->customer_id);
        $xml->writeElement('EMAIL', $customer->email);
        $xml->writeElement('REGISTRATION', $this->getCorrectSambaDate($customer->registration));
        $xml->writeElement('FIRST_NAME', $customer->first_name);
        $xml->writeElement('LAST_NAME', $customer->lastname);
        $xml->writeElement('NEWSLETTER_FREQUENCY', $customer->newsletter_frequency);
        if ($customer->zip_code) {
            $xml->writeElement('ZIP_CODE', $customer->zip_code);
        }
        if ($customer->phone) {
            $xml->writeElement('PHONE', preg_replace('/[^0-9]/', '', $customer->phone));
        }
        $xml->writeElement('SMS_FREQUENCY', $customer->sms_frequency);
        $xml->writeElement('DATA_PERMISSION', $customer->data_permission);
        $xml->writeElement('NLF_TIME', $this->getCorrectSambaDate($customer->nlf_time));

        $xml->startElement('PARAMETERS');
        $xml->startElement('PARAMETER');
        $xml->writeElement('NAME', 'LAST_NAME');
        $xml->writeElement('VALUE', $customer->lastname);
        $xml->endElement();
        $xml->startElement('PARAMETER');
        $xml->writeElement('NAME', 'FIRST_NAME');
        $xml->writeElement('VALUE', $customer->first_name);
        $xml->endElement();
        $tags = unserialize($customer->tags);
        if ($tags) {
            foreach ($tags as $tag) {
                $Tag = ShoperUserTag::findOne(['shoper_shops_id' => $this->id, 'tag_id' => $tag]);
                $xml->startElement('PARAMETER');
                $xml->writeElement('NAME', $Tag->name);
                $xml->writeElement('VALUE', '1');
                $xml->endElement();
            }
        }
        $xml->endElement(); // PARAMETERS
        $xml->endElement(); // CUSTOMER
    }

    private function createCustomerXml()
    {
        $storage = $this->getStorage();
        if ($storage) {
            $combined = $storage->collectAndDeleteChunks($this->getMinioCustomersTempKey());
            $customer = new \SimpleXMLElement('<CUSTOMERS/>');
            $customer->addChild('CUSTOMER');
            $xml = str_replace('<CUSTOMER/>', $combined, $customer->asXML());
            $storage->put($this->getMinioCustomersKey(), $xml, 'application/xml');
            return 10;
        }

        $file=$this->getCustomersFile();
        $temp=$this->getCustomersTempFile();

        $customer = new \SimpleXMLElement('<CUSTOMERS/>');
        $customer->addChild('CUSTOMER');
        file_put_contents($file, str_replace('<CUSTOMER/>', file_get_contents($temp), $customer->asXML()));
        file_put_contents($temp, '');
        return is_file($file)?10:0;
    }

    public function prepareCustomersDiversedFile($queue){

        $temp=$this->getCustomersTempFile();

        $integrationDataCurrentPage = $queue->page;
        $integrationDataMaxPage = $queue->max_page;
        $storage = $this->getStorage();
        $chunkBuffer = '';
        $chunkIndex = $integrationDataCurrentPage;
        $page_size = self::XML_PAGE_SIZE;

        $customers_query = Customers::find()->where(['user_id' => $queue->getCurrentUser()->id]);
        $subscribers_query = ShoperSubscribers::find()->where(['shoper_shops_id'=>$this->id, 'active' => 1]);

        $page = $integrationDataCurrentPage;
        $customerPages=ceil($customers_query->count() / $page_size);
        if( $integrationDataMaxPage == 0 ) {
            $pages = $customerPages;
            $pages += ceil($subscribers_query->count() / $page_size);
            // $pages+=1; // to fit everything else
            $queue->max_page=$pages;
            $integrationDataMaxPage=$pages;
            $queue->page=$page;
            $queue->save();
        }

        echo "[customer] page " . $page . " of " . $integrationDataMaxPage . PHP_EOL;

        $usedEmails=[];
        $customers = new \SimpleXMLElement('<CUSTOMERS/>');
        if ($page<=$customerPages){
            $customers_db = $customers_query->limit($page_size)->offset(($page) * $page_size)->all();
            foreach ($customers_db as $customer) {
                if (Queue::isDisallowedEmail($customer->email)) { // ommit allegro etc
                    continue;
                }
                $usedEmails[]=$customer->email;
                // var_dump($customer);
                $item = $customers->addChild('CUSTOMER');
                $item->addChild('CUSTOMER_ID', $customer->customer_id);
                // echo htmlspecialchars($customer->email);
                $item->addChild('EMAIL', htmlspecialchars($customer->email));
                $item->addChild('REGISTRATION', $this->getCorrectSambaDate($customer->registration));
                $item->addChild('FIRST_NAME', htmlspecialchars($customer->first_name));
                $item->addChild('LAST_NAME', htmlspecialchars($customer->lastname));
                $item->addChild('NEWSLETTER_FREQUENCY', $customer->newsletter_frequency);
                if ($customer->zip_code){
                    $item->addChild('ZIP_CODE', $customer->zip_code);
                } 
                if ($customer->phone){
                    $number = str_replace(['+', '-'], '', filter_var($customer->phone, FILTER_SANITIZE_NUMBER_INT));
                    $number=preg_replace("/[^0-9]/", "", $number);
                    $item->addChild('PHONE', $number);
                }
                $item->addChild('SMS_FREQUENCY', $customer->sms_frequency);
                $item->addChild('DATA_PERMISSION', $customer->data_permission);
                $item->addChild('NLF_TIME', $this->getCorrectSambaDate($customer->nlf_time));


                $paramsChild = $item->addChild('PARAMETERS');
                $lastName = $paramsChild->addChild('PARAMETER');
                $lastName->addChild('NAME', 'LAST_NAME');
                $lastName->addChild('VALUE', htmlspecialchars($customer->lastname));

                $firstName = $paramsChild->addChild('PARAMETER');
                $firstName->addChild('NAME', 'FIRST_NAME');
                $firstName->addChild('VALUE', htmlspecialchars($customer->first_name));

                $tags=unserialize($customer->tags);
                if ($tags){
                    foreach ($tags as $tag){
                        $Tag=ShoperUserTag::findOne(['shoper_shops_id'=>$this->id, 'tag_id'=>$tag]);
                        $paramChild = $paramsChild->addChild('PARAMETER');
                        $paramChild->addChild('NAME', $Tag->name);
                        $paramChild->addChild('VALUE', '1');
                    }
                }

                if ($storage) {
                    $chunkBuffer .= $item->asXml();
                } else {
                    $file_handle = fopen($temp, 'a+');
                    fwrite($file_handle, $item->asXml());
                    fclose($file_handle);
                }

            }
        }else{
            $subscribers_db = $subscribers_query->limit($page_size)->offset(($page-$customerPages) * $page_size)->all();

            foreach ($subscribers_db as $subscriber) {
                if (in_array($subscriber, $usedEmails)){
                    continue;
                }
                $item = $customers->addChild('CUSTOMER');
                $item->addChild('CUSTOMER_ID', 'popup-'.htmlspecialchars($subscriber->email));
                $item->addChild('EMAIL', htmlspecialchars($subscriber->email));
                $item->addChild('REGISTRATION', $this->getCorrectSambaDate($subscriber->dateadd));
                $item->addChild('NEWSLETTER_FREQUENCY', 'every day');
                $item->addChild('NLF_TIME', $this->getCorrectSambaDate($subscriber->dateadd));


                if ($storage) {
                    $chunkBuffer .= $item->asXml();
                } else {
                    $file_handle = fopen($temp, 'a+');
                    fwrite($file_handle, $item->asXml());
                    fclose($file_handle);
                }

            }
        }

        if ($storage && $chunkBuffer !== '') {
            $storage->putChunk($this->getMinioCustomersTempKey(), $chunkIndex, $chunkBuffer);
        }

        $page++;

        $queue->page=$page;
        $queue->save();

        if($page > (int) $integrationDataMaxPage) {
            // echo $page.PHP_EOL;
            // echo $integrationDataMaxPage.PHP_EOL;
                // die ("JUZ !!!!!");
            echo "[customer] XML chunk prepared" . PHP_EOL;

            return 1;
        }
        // echo "put tp ".$this->getCustomersFile().PHP_EOL;
        // if (file_put_contents($this->getCustomersFile(), $customers->asXML())){
        //     return true;
        // }


        return false;
    }

    public function prepareOrdersFile($queue): bool
    {
        $query   = Orders::find()->where(['user_id' => $queue->getCurrentUser()->id]);
        $storage = $this->getStorage();
        if ($storage) {
            return $this->prepareOrdersFileChunked($query, $storage);
        }
        return $this->prepareOrdersFileLocal($query);
    }

    private function prepareOrdersFileChunked($query, FeedStorageService $storage): bool
    {
        $batchSize    = 3000;
        $finalKey     = $this->getMinioOrdersKey();
        $chunkBaseKey = $finalKey . '.s' . $batchSize;
        $storage->deleteChunks($chunkBaseKey);

        $chunkIndex = 0;
        foreach ($query->batch($batchSize) as $batch) {
            $xml = new \XMLWriter();
            $xml->openMemory();
            foreach ($batch as $order) {
                $this->writeOrderXml($xml, $order);
            }
            $storage->putChunk($chunkBaseKey, $chunkIndex, $xml->outputMemory(true));
            echo "[order] chunk {$chunkIndex} saved (" . count($batch) . " orders)" . PHP_EOL;
            $chunkIndex++;
        }

        $missing = [];
        for ($i = 0; $i < $chunkIndex; $i++) {
            if (!$storage->chunkExists($chunkBaseKey, $i)) {
                $missing[] = $i;
            }
        }
        if ($missing) {
            echo "[order] ERROR: missing chunks: " . implode(', ', $missing) . PHP_EOL;
            $storage->deleteChunks($chunkBaseKey);
            return false;
        }

        $tmpFile = sys_get_temp_dir() . '/' . $this->shop . '-orders-' . getmypid() . '.xml';
        if (!$storage->collectChunksToFile($chunkBaseKey, $tmpFile, $chunkIndex, '<?xml version="1.0" encoding="UTF-8"?><ORDERS>', '</ORDERS>')) {
            return false;
        }
        $storage->putFromFile($finalKey, $tmpFile, 'application/xml');
        @unlink($tmpFile);
        return true;
    }

    private function prepareOrdersFileLocal($query): bool
    {
        $tmpFile = sys_get_temp_dir() . '/' . $this->shop . '-orders-' . getmypid() . '.xml';
        $xml = new \XMLWriter();
        if (!$xml->openUri($tmpFile)) {
            return false;
        }
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('ORDERS');
        foreach ($query->each(3000) as $order) {
            $this->writeOrderXml($xml, $order);
        }
        $xml->endElement();
        $xml->endDocument();
        $xml->flush();
        $dest = $this->getOrdersFile();
        $ok   = rename($tmpFile, $dest);
        if (!$ok) {
            @unlink($tmpFile);
        }
        return $ok;
    }

    private function writeOrderXml(\XMLWriter $xml, $order): void
    {
        $xml->startElement('ORDER');
        $xml->writeElement('ORDER_ID', $order->order_id);
        $xml->writeElement('CUSTOMER_ID', $order->customer_id);
        $xml->writeElement('CREATED_ON', $this->getCorrectSambaDate($order->created_on));
        if ($order->status == 'finished') {
            $xml->writeElement('FINISHED_ON', $this->getCorrectSambaDate($order->finished_on));
        }
        $xml->writeElement('STATUS', $order->status);
        $xml->writeElement('EMAIL', html_entity_decode($order->email, ENT_QUOTES, 'UTF-8'));
        $xml->writeElement('PHONE', str_replace(' ', '', html_entity_decode($order->phone, ENT_QUOTES, 'UTF-8')));
        $xml->writeElement('ZIP_CODE', $order->zip_code);
        $xml->writeElement('COUNTRY_CODE', $order->country_code);
        $xml->startElement('ITEMS');
        foreach ($order->getPositions() as $product) {
            $xml->startElement('ITEM');
            $xml->writeElement('PRODUCT_ID', $product['product_id']);
            $xml->writeElement('AMOUNT', $product['amount']);
            $xml->writeElement('PRICE', $product['price']);
            $xml->endElement();
        }
        $xml->endElement(); // ITEMS
        $xml->endElement(); // ORDER
    }

    public function getCustomersFile(){
        return Yii::$app->basePath.'/modules/shoper/files/'.$this->shop.'.customers.xml';
    }
    public function getCustomersTempFile(){
        return Yii::$app->basePath.'/modules/shoper/files/temp_'.$this->shop.'.customers.xml';
    }
    public function getProductsFile(){
        return Yii::$app->basePath.'/modules/shoper/files/'.$this->shop.'.products.xml';
    }
    public function getCategoriesFile(){
        return Yii::$app->basePath.'/modules/shoper/files/'.$this->shop.'.categories.xml';
    }
    public function getOrdersFile(){
        return Yii::$app->basePath.'/modules/shoper/files/'.$this->shop.'.orders.xml';
    }

    private function getStorage(): ?FeedStorageService
    {
        return FeedStorageService::isConfigured() ? FeedStorageService::create() : null;
    }

    public function getMinioCustomersKey(): string     { return 'customer/' . $this->shop . '.customers.xml'; }
    public function getMinioCustomersTempKey(): string  { return 'customer/' . $this->shop . '.customers.temp'; }
    public function getMinioProductsKey(): string      { return 'product/'  . $this->shop . '.products.xml'; }
    public function getMinioCategoriesKey(): string    { return 'category/' . $this->shop . '.categories.xml'; }
    public function getMinioOrdersKey(): string        { return 'order/'    . $this->shop . '.orders.xml'; }


    public function getCorrectSambaDate($date): string
    {
        $datetime = new \DateTime($date);
//        return $datetime->format('Y-m-d H:i:s.')
        return $datetime->format(DATE_RFC3339_EXTENDED);
    }

    public function checkMetaField($name, $type){
        $meta=ShoperMetafields::findOne(['shoper_shops_id'=>$this->id, 'key'=>$name]);
        if (!$meta){
            $data = array(
                'namespace' => ShoperMetafields::NAMESPACE,
                'key' => $name,
                'description' => 'samba integration field',
                'type' => $type
            );
            $app=$this->prepareConnection();
            $client = $app->getClient();
            $resource = new Metafield($client);
            $listParams=$resource->get();
            if ($listParams){
                foreach ($listParams as $par){
                    if ($par->key==$name){
                        $resource->delete(ShoperMetafields::OBJECT, $par->metafield_id);
                    }
                }
            }

            $result = $resource->post(ShoperMetafields::OBJECT, $data);
            if ($result){
                $meta=new ShoperMetafields(['shoper_shops_id'=>$this->id, 'key'=>$name]);
                $meta->object=ShoperMetafields::OBJECT;
                $meta->namespace=ShoperMetafields::NAMESPACE;
                $meta->description='samba integration field';
                $meta->type=$type;
                $meta->metafield_id=$result;
                if (!$meta->save()){
                    print_r($meta->getErrors());
                    die("!!");
                }
            }
        }
        return $meta;
    }


    public function setMetafield($name, $value, $type){
        $meta=$this->checkMetaField($name, $type);

        if(!$meta){
            die ("error meta");
        }
        $app=$this->prepareConnection();
        $client = $app->getClient();
        $resource = new MetafieldValue($client);
        $data = array(
            'metafield_id' => $meta->metafield_id,
            'object_id' => $meta->id,
            'value' => $value
        );
        $resource->filters(['metafield_id' => $meta->metafield_id]);
        $result = $resource->get();
        if (count($result)){
            $res= $resource->put($result[0]->value_id, ['value'=>$value]);
            return $res;

        }
        $res=$resource->post($data);
        return $res;

    }

}
