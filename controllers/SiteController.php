<?php

namespace app\controllers;

use app\models\Accesstokens;
use app\models\User;
use app\modules\api\src\Connection;
use app\modules\IAI\Application\Config;
use Yii;
use yii\filters\AccessControl;
use yii\helpers\Url;
use yii\web\Controller;
use yii\web\Response;
use yii\filters\VerbFilter;
use app\models\LoginForm;
use app\models\ContactForm;
use app\modules\xml_generator\src\XmlFeed;

class SiteController extends Controller
{
    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'only' => ['index'],
                'rules' => [
                    [
                        'actions' => ['index'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'index' => ['get'],
                ],
            ],
        ];
    }
    
    public function beforeAction($action){
        $this->enableCsrfValidation = false; 
        return parent::beforeAction($action); 
    }

    /**
     * {@inheritdoc}
     */
    public function actions()
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
            'captcha' => [
                'class' => 'yii\captcha\CaptchaAction',
                'fixedVerifyCode' => YII_ENV_TEST ? 'testme' : null,
            ],
        ];
    }

    /**
     * Displays homepage.
     *
     * @return string
     */
    public function actionIndex()
    {
        if (Yii::$app->user->can('admin')) {
            return $this->redirect(Url::toRoute(['admin/index']));
        }

        $user = User::findIdentity(Yii::$app->user->id);

        // $connection = new Connection($user);
        // $gate='http://'.$user->username.'/api/?gate=systemconfig/get/162/soap/wsdl&lang=eng';
        // $client=new \app\modules\xml_generator\src\IdioselClient($gate, $connection->getToken()->getToken());
        // $request=new \app\modules\xml_generator\src\SoapRequest();
        // $response = $client->get($request->getRequest());

        return $this->render('index', [
            'client_id' => $user->client_id,
            'secret_key' => $user->client_secret,
            'user' => $user,
            // 'shops' => $response->shops,
            // 'languages' => $response->languages,
            // 'stocks' => isset($response->stocks)?$response->stocks:null
        ]);
    }


    public function actionPanel()
    {
        
        if(!isset($_SERVER['HTTP_REFERER'])) return 'You are not allowed to see this site.';

        if(Yii::$app->user->getIdentity() === null) {

            $domain = preg_match('/(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9][a-z0-9-]{0,61}[a-z0-9]/', $_SERVER['HTTP_REFERER'], $matches);
            file_put_contents(__DIR__."/logfromlogin.txt", $matches[0]);
            $user = User::findByUsername($matches[0]);
            if ($user === null) {
                return 'You cannot be authorize. Please create an account <a href="http://sambaprod.m2itsolutions.pl"> here</a> or check domain '.$matches[0];
            }

            Yii::$app->user->login($user, 0);
            
            return $this->redirect(Url::toRoute(['site/panel']));
        }

        $user = User::findIdentity(Yii::$app->user->id);
        $domain = preg_match('/(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9][a-z0-9-]{0,61}[a-z0-9]/', $_SERVER['HTTP_REFERER'], $matches);
        if ($user->username!=$matches[0] && $matches[0] != 'samba.m2itsolutions.pl' && $matches[0] != 'sambaprod.m2itsolutions.pl'){
            $user->username=$matches[0];
            $user->save();
        }
        // echo $matches[0]."<br>";
        // echo $user->username."<br>";
        // die();

        if (isset($_GET['error'])){
            echo "Błąd autoryzacji w idiosell <br>";
            echo $_GET['error']."<br>";
            echo $_GET['message']."<br>";
            echo $_GET['hint']."<br>";
            die();
        }

        if(!Accesstokens::isLoggedIn(Yii::$app->user->id)) {
            $connection = new Connection($user);
            try {

                $state = Yii::$app->request->get('state');
                $code = Yii::$app->request->get('code');

                $connection->getAccessToken($code, $state);
            } catch (\Exception $e) {
                return $e->getMessage();
            }
        }


        // if ($user->username=='print24.com.pl'){
        //     // var_dump($user);
        //     $connection = new Connection($user);
        //     $state = Yii::$app->request->get('state');
        //     $code = Yii::$app->request->get('code');
        //     echo "State ".$state."<br>";
        //     echo "code ".$code."<br>";
            
        //     die ("debug mode on");
        // }


        if(Yii::$app->request->isPost) {
            $trackpoint = Yii::$app->request->post('trackpoint');
            var_dump($user->getConfig()->set('trackpoint', $trackpoint));
            
            $selected_language = Yii::$app->request->post('selected_language');
            var_dump($user->getConfig()->set('selected_language', $selected_language));
            
            $aggregate_groups_as_variants = Yii::$app->request->post('aggregate_groups_as_variants');
            var_dump($user->getConfig()->set('aggregate_groups_as_variants', $aggregate_groups_as_variants));

            Yii::$app->session->addFlash('success', 'Ustawienia głowne zapisane');
            // customer_set_shop_id
            if ($customer_set_shop_id = Yii::$app->request->post('customer_set_shop_id')){
                $user->config->set('customer_set_shop_id', $customer_set_shop_id);
            }
            return $this->redirect(Url::toRoute(['site/panel']));
        }

        

        $xml_generator = new XmlFeed();
        $xml_generator->setType('product');
        $xml_generator->setUser($user);
        $urls = [];
        $urls['products'] = $xml_generator->getFile(true, false);
        $xml_generator->setType('customer');
        $urls['customer'] = $xml_generator->getFile(true, false);
        $xml_generator->setType('order');
        $urls['order'] = $xml_generator->getFile(true, false);
        $xml_generator->setType('category');
        $urls['category'] = $xml_generator->getFile(true, false);

        foreach ($urls as $type=>$fileName){
            // echo "**** TYP ".$type.PHP_EOL;
            // echo "plik ".$fileName.PHP_EOL;
            // echo "Elementów w bazie: ".$user->countDatabaseElements($type).PHP_EOL;
            $filesInfo[$type]=[];
            $filesInfo[$type]['status']='gotowy';
            if (!is_file($fileName)){
                $filesInfo[$type]['status']='Nie gotowy';
                $filesInfo[$type]['elements']=0;
                // echo "BRAK PLIKU ".$fileName.PHP_EOL;
            }else{
                $xml=file_get_contents($fileName);
                $tagName=strtoupper($type);
                if ($type=='products'){
                    $tagName='PRODUCT';
                }
                if ($type=='category'){
                    $tagName='ITEM';
                }
                $tag_count = substr_count($xml, "<".$tagName.">");
                $filesInfo[$type]['elements']=$tag_count;

            }
        }
        $urls = [];
        $urls['products'] = Url::home(true).'xml/'.$user->uuid.'/products.xml';
        $urls['customers'] = Url::home(true).'xml/'.$user->uuid.'/customers.xml';
        $urls['orders'] = Url::home(true).'xml/'.$user->uuid.'/orders.xml';
        $urls['categories'] = Url::home(true).'xml/'.$user->uuid.'/categories.xml';
        // if (!isset($connection) && !$connection){
            $connection = new Connection($user);
        // }
        $gate='http://'.$user->username.'/api/?gate=systemconfig/get/162/soap/wsdl&lang=eng';
        $client=new \app\modules\xml_generator\src\IdioselClient($gate, $connection->getToken()->getToken());    
        $request=new \app\modules\xml_generator\src\SoapRequest();    
        $response = $client->get($request->getRequest());

        // echo "<pre>";
        // print_r($response->languages);
        // // print_r($response->shops);
        // print_r($response->stocks);
        // print_r($filesInfo);
        // echo "</pre>";

        return $this->render('panel', [
            'urls' => $urls,
            'user' => $user,
            'shops' => $response->shops,
            'languages' => $response->languages,
            'stocks' => isset($response->stocks)?$response->stocks:null,
            'filesInfo' => $filesInfo
        ]);
    }

    public function actionSaveProductFeed()
    {
        if(Yii::$app->request->isPost) {
            $settings = Yii::$app->request->post('Settings');

            $product_settings = [
                'product_image',
                'product_description',
                'product_brand',
                'product_stock',
                'product_price_before_discount',
                'product_price_buy',
                'product_categorytext',
                'product_line',
                'product_variant',
                'product_parameter',
                'stock_ids'
            ];
            if (isset($settings['stock_ids_array'])){
                $settings['stock_ids']=implode(',', $settings['stock_ids_array']);
            }
            unset ($settings['stock_ids_array']);
            // var_dump($settings);
            // die();

            // stock_ids_array
            
            list($ok, $errors) = $this->saveSettings($product_settings, $settings);
            
            if(!$ok) {
                Yii::$app->session->addFlash('error', 'Podczas przetwarzania zapytania wystąpiły błędy: <br>'.implode('<br>', $errors));
                return $this->redirect(Url::toRoute(['site/panel']));
            }
            
            Yii::$app->session->addFlash('success', 'Wysyłanie zapytania powiodło się');
            return $this->redirect(Url::toRoute(['site/panel']));
        }

        return $this->redirect(Url::toRoute(['site/panel']));
    }

    public function actionSaveCustomerFeed()
    {
        if(Yii::$app->request->isPost) {
            $settings = Yii::$app->request->post('Settings');

            $product_settings = [
                'customer_feed_email',
                'customer_feed_registration',
                'customer_feed_first_name',
                'customer_feed_last_name',
                'customer_zip_code',
                'customer_phone',
                'customer_tags',
                'customer_default_approvals_shop_id'
            ];

            list($ok, $errors) = $this->saveSettings($product_settings, $settings);

            if(!$ok) {
                Yii::$app->session->addFlash('error', 'Podczas przetwarzania zapytania wystąpiły błędy: <br>'.implode('<br>', $errors));
                return $this->redirect(Url::toRoute(['site/panel']));
            }

            Yii::$app->session->addFlash('success', 'Wysyłanie zapytania powiodło się');
            return $this->redirect(Url::toRoute(['site/panel']));
        }

        return $this->redirect(Url::toRoute(['site/panel']));
    }

    protected function saveSettings($settings_array, $settings_post)
    {
        $user = User::findIdentity(Yii::$app->user->id);
        $ok = true;

        if($user == null) {
            $errors = ['User is not logged in'];

            return [$ok, $errors];
        }

        $selected_product_settings = [];
        $errors = [];
        
        if(!is_array($settings_post)) {
            foreach($settings_array as $item) {
                if(!$user->config->set($item, '')) {
                    $ok = false;
                    $errors[] = "Dodawanie konfiguracji '{$item}' nie powiodło się";
                    continue;
                }
            }
            
            return [$ok, $errors];
        }
        
        foreach( $settings_post as $key => $value ) {
            if(!$user->config->set($key, $value)) {
                $ok = false;
                $errors[] = "Dodawanie konfiguracji '{$key}' nie powiodło się";
                continue;
            }
            $selected_product_settings[] = $key;
        }

        $difference = array_diff($settings_array, $selected_product_settings);
        foreach ($difference as $key=>$value) {
            if(!$user->config->set($value, '')) {
                $ok = false;
                $errors[] = "Dodawanie konfiguracji '{$value}' nie powiodło się";
                continue;
            }
        }

        return [$ok, $errors];
    }
}
