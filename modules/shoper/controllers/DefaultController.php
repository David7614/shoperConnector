<?php

namespace app\modules\shoper\controllers;

use yii\web\Controller;
use app\models\User;
use yii\helpers\Url;
use app\models\RegisterForm;
use app\models\IntegrationData;
use app\modules\shoper\models\Integrator;
use app\modules\shoper\library\App;
use yii;



use DreamCommerce\ShopAppstoreLib\Resource\Category;
use DreamCommerce\ShopAppstoreLib\Resource\CategoriesTree;
use DreamCommerce\ShopAppstoreLib\Resource\Product;
use DreamCommerce\ShopAppstoreLib\Resource\Attribute;
use DreamCommerce\ShopAppstoreLib\Resource\Producer;
use DreamCommerce\ShopAppstoreLib\Resource\Subscriber;
use DreamCommerce\ShopAppstoreLib\Resource\User as ShoperUser;
use DreamCommerce\ShopAppstoreLib\Resource\UserAddress;
use DreamCommerce\ShopAppstoreLib\Resource\UserTag;
use DreamCommerce\ShopAppstoreLib\Resource\Order;
use DreamCommerce\ShopAppstoreLib\Resource\OrderProduct;
use DreamCommerce\ShopAppstoreLib\Resource\Status;
use DreamCommerce\ShopAppstoreLib\Resource\Metafield;
use DreamCommerce\ShopAppstoreLib\Resource\MetafieldValue;

/**
 * Default controller for the `shoper` module
 */
class DefaultController extends ShoperController
{
    /**
     * Renders the index view for the module
     * @return string
     */
    public function actionTest(){
        chdir(__DIR__);
        $config = require '../library/Config.php';
        // die ("!!");
        $app = new App($config);
        $app->bootstrap();

        $client = $app->getClient();

        $resource = new Metafield($client);
        $object = "system";
        $data = array(
            'namespace' => 'M2ITSolutions',
            'key' => 'integration_version1',
            'description' => 'what about',
            'type' => Metafield::TYPE_STRING
        );
        // $result = $resource->post($object, $data);

        $id = 3;
        // $result = $resource->delete($object, $id);

        // if($result){
        //     echo 'Metafield registered';
        // }


        
        // $categoriesResource = new Category($client);
        // $categoriesResource->page(2);
        echo "<pre>";
        print_r($_GET);
        print_r($resource->get());
        $resource = new MetafieldValue($client);
        print_r($resource->get());
        die();
    }
    public function actionAddnewsletter(){
        $secret_key = 'sambamamba33512';
        $data = file_get_contents("php://input");
        $log="Log start ".PHP_EOL;
        $log.= "Data: " . date("Y-m-d H:i:s") . PHP_EOL;
        $log.= "Suma kontrolna: " . sha1($_SERVER['HTTP_X_WEBHOOK_ID'] . ':' . $secret_key . ':' . $data) . PHP_EOL;

        if (empty($data) || !isset($_SERVER['HTTP_X_WEBHOOK_ID']) || !isset($_SERVER['HTTP_X_WEBHOOK_SHA1']) || sha1($_SERVER['HTTP_X_WEBHOOK_ID'] . ':' . $secret_key . ':' . $data) !== $_SERVER['HTTP_X_WEBHOOK_SHA1'])
        {
        $log.= "Invalid Hash" . PHP_EOL;
        }

        $log.= 'SHOP VERSION: ' . $_SERVER['HTTP_X_SHOP_VERSION'] . PHP_EOL;
        $log.= 'SHOP HOSTNAME: ' . $_SERVER['HTTP_X_SHOP_DOMAIN'] . PHP_EOL;
        $log.= 'SHOP LICENSE: ' . $_SERVER['HTTP_X_SHOP_LICENSE'] . PHP_EOL;
        $log.= 'WEBHOOK ID: ' . $_SERVER['HTTP_X_WEBHOOK_ID'] . PHP_EOL;
        $log.= 'WEBHOOK NAME: ' . $_SERVER['HTTP_X_WEBHOOK_NAME'] . PHP_EOL;
        $log.= 'WEBHOOK SHA1: ' . $_SERVER['HTTP_X_WEBHOOK_SHA1'] . PHP_EOL;
        $log.= 'USERAGENT: ' . $_SERVER['HTTP_USER_AGENT'] . PHP_EOL;
        preg_match('/^(.*); charset=(.*)$/', $_SERVER['CONTENT_TYPE'], $matches);
        $log.= 'CONTENT TYPE: ' . $matches[1] . PHP_EOL;
        $log.= 'ENCODING: ' . $matches[2] . PHP_EOL;
        $log.= 'DATA: ' . $data . PHP_EOL;

    }
    public function actionIndex()
    {

        if (!$this->user) { // nie ma usera z tej domeny, moszna się zarejestrować
            return $this->redirect(Url::toRoute(['/shoper/register']));
        }
        $user = $this->user;     
        


        $Integrator=Integrator::findOne(['shop'=>$_GET['shop']]);  
        // $Integrator->

        if(Yii::$app->request->isPost) {
            if (Yii::$app->request->post('trackpoint')){
                $trackpoint = Yii::$app->request->post('trackpoint');
                if ($user->getConfig()->get('trackpoint') != $trackpoint){
                    $user->getConfig()->set('trackpoint', $trackpoint);
                    $Integrator->setMetafield('trackpoint', $trackpoint, Metafield::TYPE_STRING);
                    // die("TEST");
                    Yii::$app->session->addFlash('success', 'Udało się zapisać ' . Yii::$app->request->post('trackpoint'));
                }
                $smartpoint = Yii::$app->request->post('smartpoint');
                if ($user->getConfig()->get('smartpoint') != $smartpoint){
                    $user->getConfig()->set('smartpoint', $smartpoint);
                    $Integrator->setMetafield('smartpoint', $smartpoint, Metafield::TYPE_INT);
                    Yii::$app->session->addFlash('success', 'Udało się zapisać ' . Yii::$app->request->post('smartpoint'));
                }
            }
            if (Yii::$app->request->post('Settings')){                
                
                list($ok, $errors) = $this->user->saveSettings(Yii::$app->request->post('Settings'));
                
                if(!$ok) {
                    Yii::$app->session->addFlash('error', 'Podczas przetwarzania zapytania wystąpiły błędy: <br>'.implode('<br>', $errors));
                    return $this->redirect(Url::toRoute(['/shoper']+ Yii::$app->request->get() ));
                }
                Yii::$app->session->addFlash('success', 'Wysyłanie zapytania powiodło się');
                return $this->redirect(Url::toRoute(['/shoper']+ Yii::$app->request->get() ));

            }
            return $this->redirect(Url::toRoute(['/shoper']+ Yii::$app->request->get() ));
        }


        return $this->render('index', [
            'user' => $user]);
    }

    

    public function actionRegister(){
        if (!Yii::$app->user->isGuest) { // raczej zbędne bo się nie zaloginujemy
            return $this->redirect(Url::toRoute(['/shoper']));
        }

        $model = new RegisterForm();
        $model->username=$this->referer;
        $model->shop_type=self::SHOP_TYPE;
        try {
            if ($model->load(Yii::$app->request->post()) && $model->register()) {
                $model->login();

                IntegrationData::setIsNew('ORDER', true, Yii::$app->user->id);
                IntegrationData::setIsNew('CUSTOMER', true, Yii::$app->user->id);
                return $this->redirect(Url::toRoute(['/shoper']));
            }
        } catch(\Exception $e) {
            Yii::$app->session->addFlash('error', $e->getMessage());
            return $this->redirect(Url::toRoute(['/shoper/register']));
        }

        return $this->render('register', [
            'model' => $model
        ]);
    }

    public function actionBilling(){
        // $_POST['shop_url']=isset($_POST['shop_url'])?$_POST['shop_url']:$this->referer;

        

        if (empty($_POST['shop_url']) || empty($_POST['action'])) {
            die;
        }

        Yii::debug('DATA');
        Yii::debug(json_encode($_POST));

        chdir(__DIR__);
        $config = require '../library/Config.php';
        if (isset($_GET['test'])){
            // echo "<pre>";
            // var_dump($config);
            // die ("TEST");
        }
        $billingSystem = new \app\modules\shoper\library\BillingSystem\App($_POST['shop_url'], $config);
        $billingSystem->dispatch();
        echo "!";
        die ("!!!");
    }
}
