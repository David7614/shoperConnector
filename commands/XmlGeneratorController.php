<?php
namespace app\commands;

use app\models\Queue;
use app\modules\api\src\Connection;
use app\modules\shoper\models\Integrator;
use app\modules\xml_generator\src\IdioselClient;
use app\modules\xml_generator\src\Magazine;
use app\modules\xml_generator\src\SoapRequest;
use app\modules\xml_generator\src\XmlFeed;
use app\modules\xml_generator\src\OrderFeed;
use Exception;
use InvalidArgumentException;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use app\models\User;
use SoapClient;
use app\models\Customers;
use app\modules\shoper\models\ShoperShops;
use app\services\QueueRunnerService;


/*

integration_types:
category
countries
customer
order
product
subscribers
tags

*/

class XmlGeneratorController extends Controller
{
    public $what;

    public function options($actionsID) 
    {
        return ['what'];
    }

    public function actionPrepareQueue()
    {
        Queue::prepareQueue(XmlFeed::CUSTOMER);
        Queue::prepareQueue(XmlFeed::PRODUCT);
        Queue::prepareQueue(XmlFeed::CATEGORY);
        Queue::prepareQueue(XmlFeed::ORDER);
        Queue::prepareQueue(XmlFeed::TAGS);        
        Queue::prepareQueue('countries');
        Queue::prepareQueue('subscribers');
    }

    public function actionGenerateTags($forceId=0)
    {
        return (new QueueRunnerService())->run(XmlFeed::TAGS, ['forceId'=>$forceId]);
    }
    public function actionGenerateCountries($forceId=0)
    {
        $type='countries';
        $what = (!isset($this->what) || $this->what == null) ? $this->what : null;
        $queue = Queue::findLastForType($type, ['forceId'=>$forceId]);

        if($queue == null) {
            echo "nothing to do for type ".$type.PHP_EOL;
            return ExitCode::OK;
        }
        $queue->setRunningStatus(); // back to pending
        echo "QUEUE ID ".$queue->id.PHP_EOL;
        $user = $queue->getCurrentUser();
        if ($user->shop_type=='shoper'){
            $queue->setExecutedStatus();
            $queue->setCountErrors(0);
            return true;
            die ("Shoper off");
        }
        echo $user->id;
        $connection = new Connection($user);

        $customersList= Customers::find()->where(['user_id'=>$user->id, 'country'=>''])
            ->andWhere(['!=','email', ''])
            ->limit(100)->all();

        if (!$customersList){
            $queue->setExecutedStatus();
            $queue->setCountErrors(0);
            return true;
        }

        $gate='http://'.$user->username.'/api/?gate=clients/getDeliveryAddress/169/soap/wsdl&lang=pol';
        $client=new IdioselClient($gate, $connection->getToken()->getToken());
        foreach ($customersList as $customer){
            echo "ID ".$customer->id.PHP_EOL;
            // var_dump($customer);
            $queue->page++;
            echo $customer->email.PHP_EOL;
            $request=new SoapRequest();
            $request->addParam('clientLogin', $customer->email);  
            $response = $client->getDeliveryAddress($request->getRequest());
            // echo count($response->clientDeliveryAddressesResults);
            $lastIndex=count($response->clientDeliveryAddressesResults)-1;
            if ($lastIndex<0){
                echo "no data ".PHP_EOL;
                $customer->country='no data';
                $customer->save();
                var_dump($customer->getErrors());
                continue;
            }
            var_dump($response->clientDeliveryAddressesResults[$lastIndex]->clientDeliveryAddressCountry);
            $customer->country=$response->clientDeliveryAddressesResults[$lastIndex]->clientDeliveryAddressCountry;
            $customer->save();
            var_dump($customer->getErrors());
        }
        $queue->setPendingStatus();
    }


    public function actionGenerateProducts($forceId=0, $forcePage=null)
    {
        return (new QueueRunnerService())->run(XmlFeed::PRODUCT, ['forceId'=>$forceId, 'forcePage'=>$forcePage]);
    }

    public function actionGenerateCategories($forceId=0)
    {
        return (new QueueRunnerService())->run(XmlFeed::CATEGORY, ['forceId'=>$forceId]);
    }

    public function actionGenerateOrders($forceId=0)
    {
        return (new QueueRunnerService())->run(XmlFeed::ORDER, ['forceId'=>$forceId]);
    }

    public function actionOrdersObjects()
    {
        return (new QueueRunnerService())->run(XmlFeed::ORDER, ['what' => 'objects']);
    }


    public function actionGenerateSubscribers($forceId=0)
    {
        return (new QueueRunnerService())->run('subscribers', ['forceId'=>$forceId]);
    }
    public function actionGenerateCustomers($forceId=0)
    {
        return (new QueueRunnerService())->run(XmlFeed::CUSTOMER, ['forceId'=>$forceId]);
    }

    public function actionGenerateMagazines()
    {
        $magazine = new Magazine();
        $magazine->getMagazines();
    }

    public function actionResetIntegration()
    {
        Queue::resetLongRunning();
        Queue::resetAllDone();
        // Queue::resetAllException();
    }
    
    public function actionResetExceptions()
    {
        die ("DISABLED");
        // Queue::resetAllException();
    }

    public function actionLoopProducts(int $limitSeconds = 540)
    {
        return $this->loopQueue(XmlFeed::PRODUCT, [], $limitSeconds);
    }

    public function actionLoopOrders(int $limitSeconds = 540)
    {
        return $this->loopQueue(XmlFeed::ORDER, [], $limitSeconds);
    }

    public function actionLoopCustomers(int $limitSeconds = 540)
    {
        return $this->loopQueue(XmlFeed::CUSTOMER, [], $limitSeconds);
    }

    public function actionLoopSubscribers(int $limitSeconds = 540)
    {
        return $this->loopQueue('subscribers', [], $limitSeconds);
    }

    public function actionLoopCategories(int $limitSeconds = 540)
    {
        return $this->loopQueue(XmlFeed::CATEGORY, [], $limitSeconds);
    }

    private function loopQueue(string $type, array $config = [], int $limitSeconds = 540): int
    {
        $start     = time();
        $runner    = new QueueRunnerService();
        $iteration = 0;

        echo "LOOP START — type: $type, limit: {$limitSeconds}s" . PHP_EOL;

        while (true) {
            $elapsed = time() - $start;

            if ($elapsed >= $limitSeconds) {
                echo "TIME LIMIT reached ({$elapsed}s) — stopping." . PHP_EOL;
                break;
            }

            $iteration++;
            echo "--- iteration #{$iteration} [{$elapsed}s elapsed] ---" . PHP_EOL;

            $result = $runner->run($type, $config);

            if ($result === QueueRunnerService::QUEUE_EMPTY) {
                echo "Queue empty — stopping early." . PHP_EOL;
                break;
            }
        }

        echo "LOOP END — total: " . (time() - $start) . "s, iterations: $iteration" . PHP_EOL;
        return ExitCode::OK;
    }

    private function establishQueue(string $type, array $config = [])
    {
        if (!isset($config['forceId'])){
            $config['forceId']=0;
        }
        if ($config['forceId'] != 0){
            $queue = Queue::findOne($config['forceId']);
        }else{
            if (isset($config['shop_type'])){
                $queue = Queue::findLastForTypeAndShop($type, $config['shop_type']);
            }else{
                $queue = Queue::findLastForType($type);
            }
        }

        if($queue == null) {
            echo "nothing to do for type ".$type.PHP_EOL;
            return ExitCode::OK;
        }

        Integrator::shoperLog('', $queue->id);
        Integrator::shoperLog('1.1 Establish Queue - ID: ' . $queue->id, $queue->id);

        echo '- - - Establish Queue - ID: ' . $queue->id . PHP_EOL;

        $user = $queue->getCurrentUser();
        $parameters=$queue->additionalParameters;
                $filePrepare = isset($parameters['objects_done']) ? true : false;

        // if ($queue->integrated == Queue::RUNNING && ($user->shop_type != 'shoper' || $filePrepare)) { // prevent double run
        if ($queue->integrated == Queue::RUNNING && $config['forceId'] == 0) { // prevent double run
            echo " job still in progress ".PHP_EOL;
            echo "from ".$queue->executed_at.PHP_EOL;
            $date = new \DateTime( $queue->executed_at );
            $date2 = new \DateTime( date('Y-m-d H:i:s') );
            $diffInSeconds = $date2->getTimestamp() - $date->getTimestamp();
            echo "in seconds: ".$diffInSeconds.PHP_EOL;
            if ($diffInSeconds>3600){
                echo 'over hour';
                $queue->setPendingStatus();
                return ExitCode::OK;
            }
            return ExitCode::OK;
        }
 
        if (!$queue->checkQueueConstraints()) {
            echo " job should not run ".PHP_EOL;
            $queue->setErrorStatus('job disabled');
            return ExitCode::OK;
        }

        // die ("STOP FOR NOW");

        $queue->setRunningStatus();

        if (!$user) {
            $queue->delete();
            return ExitCode::ERR;
        }

        $xml_generator = new XmlFeed();
        $xml_generator->setType($type);
        if (isset($config['forcePage'])){
            $queue->page=$config['forcePage'];
        }
        $xml_generator->setQueue($queue);
        $xml_generator->setUser($user);

        if ($user->shop_type == 'shoper') {
            Integrator::shoperLog('-- 1.2 Integration: Shoper', $queue->id);
            Integrator::shoperLog('1.3 Step: Start', $queue->id);

            // var_dump($queue->additionalParameters);
            

            try {
                // if ($queue->page == 0) {
                    // $queue->copyLastQueueSettings();
                    // skip for now
                // }
                echo $queue->integration_type." !!!# ".PHP_EOL;
                if ($queue->integration_type == 'subscribers'){
                    echo "NOT FOR SHOPER ".PHP_EOL;
                    $queue->setExecutedStatus();
                    return ExitCode::OK;
                }

                


                $integrator = Integrator::findOne(['shop_url' => 'https://' . $user->username]);

                if ($filePrepare){

                    // die ("PREPARE FILE NEW TYPE");
                    $res=$integrator->prepareDiversedFile($queue);
                    if ($res==10){
                        $queue->setExecutedStatus();
                        $queue->setCountErrors(0);
                        return true;
                    }
                    $queue->setPendingStatus();
                    die ("STOP HERE");
                }

                Integrator::shoperLog('1.4 Step: Generating', $queue->id);
                $functionResult = $integrator->{'generate' . ucfirst($queue->integration_type)}($queue);

                if ($functionResult && $queue->max_page <= $queue->page) {
                    Integrator::shoperLog('1.5 Step: Making XML', $queue->id);

                    if ($integrator->prepareFile($queue)) {
                        echo "set executed";
                        $queue->setExecutedStatus();
                        return ExitCode::OK;
                    }
                }

                $queue->setPendingStatus(); // back to pending

                Integrator::shoperLog('1.6 Step: End', $queue->id);
                Integrator::shoperLog('-- 1.7 Integration Result: OK', $queue->id);
                return ExitCode::OK;
            } catch (Exception $e) {
                Integrator::shoperLog('-- 1.8 Integration Result: ERROR:', $queue->id);
                Integrator::shoperLog(print_r($e->getCode(), true), $queue->id);
                Integrator::shoperLog(print_r($e->getMessage(), true), $queue->id);
                Integrator::shoperLog('-- 1.9 Integration Result: ERROR', $queue->id);
                echo $e->getMessage();

                // Prevention of queue blocking by http request failed error
                if ($e->getMessage() == 'HTTP request failed') {
                    $queue->setShoperApiDelay();
                    $queue->setPendingStatus();
                    return ExitCode::UNSPECIFIED_ERROR;
                }

                if ($e->getMessage() == 'Retries count exceeded') {
                    // $queue->setShoperApiDelay('+5 min');
                    $queue->setPendingStatus();
                    return ExitCode::UNSPECIFIED_ERROR;
                }

                if ($e->getCode() == 23000) {
                    $queue->setPendingStatus();
                    return ExitCode::UNSPECIFIED_ERROR;
                }

                $queue->setErrorStatus($e->getMessage());
                return ExitCode::UNSPECIFIED_ERROR;
            }
        }

        try {
            $connection = new Connection($user);

            if($connection->getToken() == null) {
                $queue->setErrorStatus('ERR no token');
                echo "ERR1 - no token";
                $queue->setPendingStatus();
                $queue->setErrorStatus('no token');
                return ExitCode::UNSPECIFIED_ERROR;
            }

            try {
                $xml_generator->setToken($connection->getToken()->getToken());
            } catch (InvalidArgumentException $e) {
                $queue->raiseCountErrors();
                if ($queue->getCountErrors()<30){
                    $queue->setPendingStatus();
                }else{
                    $queue->setErrorStatus($e->getMessage());
                }
                return ExitCode::UNSPECIFIED_ERROR;
            }

            $parameters=$queue->additionalParameters;

            $what = isset($parameters['objects_done']) ? null : 'objects';
                echo "RUN with what ".$what.PHP_EOL;    
            $generated = $xml_generator->generate($what);

            if(!$generated) {
                var_dump($generated);
                $queue->setErrorStatus();
                throw new Exception('Cannot generate '.$type.' feed. Cannot save file');
            }
            // if($xml_generator->isFinished()) {
            if($generated===10) { // czyli skończone
                // die ("SET EXECUTED");
                $queue->setExecutedStatus();
                $queue->setCountErrors(0);
                return true;
                /*

                $xml_generator->generate();
                // if($type == XmlFeed::PRODUCT || $type == XmlFeed::CATEGORY) {
                //     $queue->setExecutedStatus();
                // }
                file_put_contents($xml_generator->getFile(true, true), '');
                echo "finished ".$xml_generator->getFile(true, true);
                $queue->setExecutedStatus();
                */
            }

            $queue->setPendingStatus(); // back to pending
            $queue->setCountErrors(0);

            return ExitCode::OK;
        } catch (Exception $e) {
            echo "ERROR::".PHP_EOL;
            echo $e->getMessage();
            // $queue->setErrorStatus($e->getMessage());

            $queue->raiseCountErrors();
            if ($queue->getCountErrors()<30){
                $queue->setPendingStatus();
            }else{
                $queue->setErrorStatus($e->getMessage());
            }
            
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }
}
