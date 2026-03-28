<?php
namespace app\modules\xml_generator\src;

use SoapClient;

use app\models\Customers;
use app\models\IntegrationData;

class Tags extends XmlFeed
{
    private $package = 200; // 20000 wtf

    public function generate($what = null): int
    {

        if ($what===null){
            return 10;
        }

        $doTags = $this->_user->config->get('customer_tags');

        if (!$doTags){
            return 10;
        }

        $gate = "https://{$this->_user->username}/api/?gate=clientstags/get/162/soap/wsdl&lang=pol";
        $this->_client=new IdioselClient($gate, $this->_token);    
        $this->_request=new SoapRequest();   
        


        $customers_query = Customers::find()
            ->where(['user_id' => $this->_user->id]);


        if($this->_queue->max_page == 0) {
            $all = $customers_query->count();

            $pages = ceil($all / $this->package);
            $this->_queue->setMaxPages($pages);
        }


 

        $customers = $customers_query
            ->limit($this->package)
            ->offset($this->_queue->page*$this->package)
            ->all();


        $i=0;    
        foreach($customers as $customer) {
            try{ 
                $request=$this->_request;
                $request->addParam('clientId', $customer->customer_id);
                $response = $this->_client->get($request->getRequest());
                var_dump($request->getRequest());
                var_dump($response);
                if(!isset($response->results)) { 

                    if($customer->tags == null) {
                        $customer->tags = serialize([]);
                    }
                    
                    if(strpos($response->errors->faultString, "login") !== false) {
                        file_put_contents(__DIR__ . '/test.txt', "[ ".date('H:i:s d-m-Y')." ] Błędny login lub hasło na produkcie:".$response->errors->faultString, FILE_APPEND);
                    }

                    $customer->server_response = serialize($response);

                    if(strpos($customer->server_response, "login") !== false) {
                        $customer->error = "login_error";
                        $this->_queue->page = $this->_queue->max_page;
                        $this->_queue->integrated = 2;
                        $this->_queue->save();
                        IntegrationData::setLastIntegrationDate($customer->last_modification_date, $this->_user->id);
                        return true;
                    } else {
                        $customer->error = "";
                    }

                    $customer->save(false);
                    $i++;

                    continue;
                }

                $tags = [];
                foreach($response->results as $result) {
                    $tags[] = [
                        'tagName' => $result->tagName,
                        'tagId' => $result->tagId,
                        'tagValue' => $result->tagValue
                    ];
                }

                $customer->server_response = null;
                $customer->error = null;
                $customer->tags = serialize($tags);
                $customer->save(false);
            } catch (\Exception $e) {
                echo $e->getMessage();
            }

            $i++;
        }

        $this->_queue->increasePage();

        if($this->_queue->page >= $this->_queue->max_page) {
            IntegrationData::setLastIntegrationDate(date('Y-m-d'), $this->_user->id);
            return 10;
        }
        return true;
    }

    public function getFile(bool $get_file_path = false, bool $temp = false): string
    {
        return "";
    }
}
