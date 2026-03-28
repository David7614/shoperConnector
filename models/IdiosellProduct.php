<?php

namespace app\models;

use Yii;

class IdiosellProduct 
{
	private $productData;
	private $userId;
	private $productName=null;
	private $productDescription=null;

	public function __construct($productData, $user){
		$this->productData=$productData;
		$this->_user=$user;
		$this->userId=$this->_user->id;

	}

	private function getPrice(){
		return $this->productData->productRetailPrice;
	}
	private function getPrices(){
		$product=$this->productData;

		$prices=[];
		$price=[];
		$price['NAME']='retail';
		$price['PRICE']=$product->productRetailPrice;
        if ($this->_user->config->get('product_price_before_discount')) {
            $price_before_discount = $product->productRetailPrice;
            $price['PRICE_BEFORE_DISCOUNT']=$this->getPriceBeforeDiscount();
        }
        $prices[]=$price;

        $price=[];
		$price['NAME']='Wholesaler';
		$price['PRICE']=$product->productWholesalePrice;
		$prices[]=$price;
		
        return $prices;    
	}
	private function getPriceBeforeDiscount(){
		$product=$this->productData;

        if ($this->_user->config->get('product_price_before_discount')) {
        	// $price_before_discount = isset($product->productStrikethroughRetailPrice)?$product->productStrikethroughRetailPrice:$product->productRetailPrice;
			$price_before_discount = $product->productRetailPrice;
            if (isset($product->productStrikethroughRetailPrice) && $product->productStrikethroughRetailPrice!=0 && $product->productStrikethroughRetailPrice> $price_before_discount){
                $price_before_discount=$product->productStrikethroughRetailPrice;
            }
			return $price_before_discount;
        }
        return 0;
	}
	private function getPriceBuy(){
		$product=$this->productData;

        if ($this->_user->config->get('product_price_before_discount')) {
        	// $price_before_discount = isset($product->productStrikethroughRetailPrice)?$product->productStrikethroughRetailPrice:$product->productRetailPrice;
			$price_before_discount = $product->productRetailPrice;
			return $price_before_discount;
        }
        return 0;
	}
	private function getPriceWholesale(){
        return $this->productData->productWholesalePrice;
	}
	private function getProductUrl(){
		$product=$this->productData;
		$productName=$this->getTitle();
		$productSlug = str_replace(Product::getReplaceFrom(), Product::getReplaceTo(), $productName);
        $productSlug = str_replace('--', '-', $productSlug);
		$productUrl=$this->_user->url.'/product-pol-'.$product->productId.'-'.$productSlug.'.html';
        return $productUrl;
	}
	private function getTitle(){
		if ($this->productName){
			return $this->productName;
		}
		$product=$this->productData;
		$productName='empty';
        $productDescription='empty';
        foreach ($product->productDescriptionsLangData as $langData){
            if ($langData->langId!=$this->getLanguage()){
                continue;
            }
            $this->productName = htmlspecialchars($langData->productName);     
            $this->productDescription = htmlspecialchars($langData->productDescription);     
        }
        return isset($this->productName)?$this->productName:'undefined';
	}
	private function getDescription(){
		if ($this->productDescription){
			return $this->productDescription;
		}
		$product=$this->productData;
		$productName='empty';
        $productDescription='empty';
        foreach ($product->productDescriptionsLangData as $langData){
            if ($langData->langId!=$this->getLanguage()){
                continue;
            }
            $this->productName = htmlspecialchars($langData->productName);     
            $this->productDescription = htmlspecialchars($langData->productDescription);     
        }
        return $this->productDescription;
	}

	public function getLanguage(){
		$selectedLanguage=$this->_user->config->get('selected_language');
        if (!$selectedLanguage){
            $selectedLanguage='pol';
        }
        return $selectedLanguage;
	}

	public function getBrand(){
		$product=$this->productData;
		if ($this->_user->config->get('product_brand')) {
            return htmlspecialchars($product->producerName);
        }
        return '-';
	}
	public function getImage(){
		$product=$this->productData;
		if ($this->_user->config->get('product_image')) {
            return $product->productIcon->productIconLargeUrl;
        }
        return '-';
	}
	public function getCategoryText(){
		$product=$this->productData;
		if ($this->_user->config->get('product_categorytext')) {
            return $product->categoryName ?htmlspecialchars($product->categoryName): "brak";
        }
        return '-';
	}
	public function getIsVisible(){
		$product=$this->productData;
		return $product->productIsVisible == 'y' ? 'TRUE' : 'FALSE';
	}
	public function getParentId(){
        $product=$this->productData;
        if (!isset($product->productVersion)){
            return 0;
        }
        if ($product->productVersion->versionParentId){
            return $product->productVersion->versionParentId;
        }
        return 0;
    }
    public function getVariantNames(){
        $product=$this->productData;
        if (!isset($product->productVersion->versionGroupNames)){
            return '';
        }
        foreach ($product->productVersion->versionGroupNames->versionGroupNamesLangData as $g){
            if ($g->langId==$this->getLanguage()){
                return $g->versionGroupName;
            }
        }
        return '';
    }
    public function getVariantVals(){
        $product=$this->productData;
        if (!isset($product->productVersion->versionNames)){
            return '';
        }
        foreach ($product->productVersion->versionNames->versionNamesLangData as $g){
            if ($g->langId==$this->getLanguage()){
                return $g->versionName;
            }
        }
        return '';
    }
    public function getProductLine(){
		$product=$this->productData;


        try {
            if ($this->_user->config->get('product_line')) {
                return $product->productSeries->seriesPanelName ?: "brak";
            }
        } catch (\yii\base\ErrorException $e) {
            return "brak";
        }
        return "brak";
	}

	public function getParameters(){
		$product=$this->productData;

		$parameters = [];
		try {
            if (isset($product->productParameters)) {
                
                
                foreach ($product->productParameters as $paramData) {

                    // print_r($paramData);

                    if (isset($paramData->parameterDescriptionsLangData)){
                        foreach ($paramData->parameterDescriptionsLangData as $langData) {
                            if ($langData->langId==$this->getLanguage()){
                                $parameterName=htmlspecialchars($langData->parameterName);
                            }
                        }
                    }

                    // echo " PARAMETER NAME ".$parameterName.PHP_EOL;
                    if (isset($paramData->parameterValues)){
                        foreach ($paramData->parameterValues as $val){
                            foreach ($val->parameterValueDescriptionsLangData as $langData){
                                if ($langData->langId==$this->getLanguage()){
                                    $parameterValue=htmlspecialchars($langData->parameterValueName);
                                }
                            }

                            // echo " PARAMETER VALUE ".$parameterValue.PHP_EOL;
                            $param['NAME']=htmlspecialchars($parameterName);
                            $param['VALUE']=htmlspecialchars($parameterValue);
                            $parameters[] = $param;

                        }
                    }
                    
                    
                }


            }
                return $parameters;
        } catch (\Exception $e) {
            echo "PARAMETERS ERROR".PHP_EOL;
            echo $e->getMessage().PHP_EOL;
            return $parameters;
        }


	}

	public function getVariants(){
        $this->stock=0;
		$product=$this->productData;
		$variants = [];
		try {

            if (isset($product->productStocksData->productStocksQuantities)) {

                $allowedStockIds = $this->_user->config->getStockIdsArray();
                
                $i = 0;
                $stock=0;
                foreach ($product->productStocksData->productStocksQuantities as $productStockQuantity) {
                    if ((in_array($productStockQuantity->stockId, $allowedStockIds)) || count($allowedStockIds) == 0) {
                        foreach ($productStockQuantity->productSizesData as $stockData) {
                        	$variant['PRODUCT_ID']=$product->productId;
                        	$variant['TITLE']=substr($this->getTitle() . htmlspecialchars($stockData->sizePanelName), 0,249);
                        	$variant['IMAGE']=$product->productIcon->productIconLargeUrl;
                        	$variant['PRICE_BEFORE_DISCOUNT']=isset($product->productRetailPrice)?$product->productRetailPrice:0;
                        	$variant['DESCRIPTION']=$this->getDescription();

                        	$variant['PARAMETERS']=[];


                            $param = [];
                            $param['NAME']='Size';
                            $param['VALUE']=htmlspecialchars($stockData->sizePanelName);
                            $variant['PARAMETERS'][]=$param;

                            $variant['STOCK']=$stockData->productSizeQuantity;
                            $variant['PRICE']=isset($stockData->productRetailPrice)?$stockData->productRetailPrice:0;
                            $variant['PRICE_BUY']=isset($stockData->productRetailPrice)?$stockData->productRetailPrice:0;

                            if($stockData->productSizeQuantity < 0) {
                                $stockData->productSizeQuantity = 999;
                            }

                            if ($stockData->productSizeQuantity > 0) {
                                $stock += $stockData->productSizeQuantity;
                                echo "**** add ".$stockData->productSizeQuantity.PHP_EOL;
                            }
                            $variants[]=$variant;
                        }
                    }
                }
                $this->stock=$stock;
                return $variants;
            }
        } catch (\Exception $e) {
            echo "no variants ";
            echo $e->getMessage().PHP_EOL;
            return $variants;
        }
	}

	public function prepareFromApi($force=false){

		// TODO hash
		$hash=md5(serialize(get_object_vars($this->productData)));
		

		$productModel=Product::find()->where(['PRODUCT_ID'=>$this->productData->productId, 'user_id' => $this->_user->id])->one();

		if (!$productModel){
            $productModel=new Product();
            $productModel->from_api_page=$this->productData->from_api_page;
        }

        if (!$force && $productModel->PRICE!=$this->getPrice()){
            $force=true;
        }
        if (!$force && $productModel->PRICE_BEFORE_DISCOUNT!= $this->getPriceBeforeDiscount() ){
            $force=true;
        }
        if (!$force && $productModel->PRICE_BUY!=$this->getPriceBuy()){
            $force=true;
        }
        if (!$force && $productModel->PRICE_WHOLESALE!= $this->getPriceWholesale() ){
            $force=true;
        }
        $pricesDummy=$this->getPrices();
        $variants=$this->getVariants();
        if (!$force && $productModel->STOCK!= $this->stock ){
            $force=true;
        }
        if ($productModel->PRICES != '-'){
            if (!$force && unserialize($productModel->PRICES) != $this->getPrices() ){
                $force=true;
            }
        }else{
            $force=true;
        }
        if (!$force && $productModel->parent_id != $this->getParentId() ){
            $force=true;
        }

        if ($hash==$productModel->params_hash && !$force){ // chceck if changed since last save
            echo "hash same ".PHP_EOL;
            return true;
        }

        $productModel->PRODUCT_ID = $this->productData->productId; 
        $productModel->URL=$this->getProductUrl();
        $productModel->TITLE=$this->getTitle();
        $productModel->PRICE=$this->getPrice();
        $productModel->PRICE_WHOLESALE=$this->getPriceWholesale();
        $productModel->BRAND=$this->getBrand();
        $productModel->DESCRIPTION=$this->getDescription();
        $productModel->PRICE_BEFORE_DISCOUNT=$this->getPriceBeforeDiscount();
        $productModel->PRICE_BUY = $this->getPriceBuy();
        $productModel->PRICES = serialize($this->getPrices());
        $productModel->IMAGE=$this->getImage();
        $productModel->CATEGORYTEXT=$this->getCategoryText();
        $productModel->SHOW= $this->getIsVisible();
        $productModel->PRODUCT_LINE = $this->getProductLine();
        // var_dump($this->getParameters());
        // die ("SZTOP SZKOP");
        $productModel->PARAMETERS=serialize($this->getParameters());
        $productModel->VARIANT=serialize($variants);
        $productModel->STOCK=(int) $this->stock;
        $productModel->response='-';
        
        $productModel->params_hash=$hash;
        $productModel->user_id = $this->_user->id;
        $productModel->translation = $this->getLanguage();
        if ($this->getParentId() && $this->getVariantNames() && $this->getVariantVals()){
            $productModel->parent_id=$this->getParentId();
            $productModel->variants_names=$this->getVariantNames();            
            $productModel->variants_values=$this->getVariantVals();            
        }

        if ($productModel->save()){
            return true;
        }
        // echo 'app\models\Product::'.PHP_EOL;
        // var_dump($productModel->getAttributes());
        // var_dump($productModel->CATEGORYTEXT);
        var_dump($productModel->getErrors());
        return false;

	}
}
