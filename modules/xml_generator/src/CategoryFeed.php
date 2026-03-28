<?php

namespace app\modules\xml_generator\src;

use SoapClient;

class CategoryFeed extends XmlFeed
{
    /**
     * @param null $what
     * @return bool
     *
     */
    const API_RESULT_COUNT=1000;

    public function generate($what = null): int
    {
        $temp = $this->getFile(true, true);
        $file = $this->getFile(true, false);

        $gate = "https://{$this->_user->username}/api/?gate=productscategories/get/129/soap/wsdl&lang=pol";    
        $this->_client=new IdioselClient($gate, $this->_token);    
        $this->_request=new SoapRequest();  

        if (!$this->isFinished()) {
            $created = $this->createOrAddCategoryTempXml($temp);
        } else {
            $created = $this->createCategoryXml($file, $temp);
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
        
        if ($this->_queue->max_page>0){
            return false; // no need every time    
        }
        $request=$this->_request;
        $request->addParam('results_limit', 1);   
        $response = $this->_client->get($request->getRequest());
        // var_dump($response);
        // die();
        if (!$response->results_number_all){
            echo "no results".PHP_EOL;
            return false;    
        }
        
        $maxPage=ceil($response->results_number_all/self::API_RESULT_COUNT);
        if ($this->_queue->max_page<$maxPage){
            $this->_queue->max_page=$maxPage;
            $this->_queue->save();
        }
        
        return true; 
    }

    public function createOrAddCategoryTempXml($temp)
    {
        
        try {
            //building request
            // $request = [
            //     'authenticate' => [
            //         //leaving empty - authenticating using OAuth access token
            //         'system_key' => '',
            //         'system_login' => ''
            //     ],
            //     'get' => [
            //         'params' => [
            //             'returnProducts' => 'active',
            //         ]
            //     ]
            // ];
            $this->checkQueueConstraints();
            $this->_request->addParam('results_limit', self::API_RESULT_COUNT);
            $request = $this->_request;
            $request->addParam('returnProducts', 'active');

            $page = 0;
            $categories = new \SimpleXMLElement('<CATEGORIES/>');
            $request->addParam('resultsPage', $this->_queue->page);
            // $request['params']['resultsPage'] = $this->_queue->page;
            $response = $this->_client->get($request->getRequest());
            // var_dump($response); die;
            // $results_page = property_exists($response, 'resultsNumberPage') ? $response->resultsNumberPage : 1;
            // $this->_queue->setMaxPages($results_page);

            if ($this->_queue->page >= $this->_queue->max_page) {
                echo "max page exceded";
                return true;
            }
            // print_r($response->categories); die;
            if ($response->errors && !empty($response->errors->faultString)) {
                echo $response->errors->faultString;
                return false;
            }
            $categories_array = [];

            $i = 0;
            $replace_from = ['/', ' ', '”', '″', ',', 'ą', 'ę', 'ź', 'ć', 'ż', 'ł', 'ó', 'ń'];
            $replace_to = ['-', '-', '-', '-', '-', 'a', 'e', 'z', 'c', 'z', 'l', 'o', 'n'];

            foreach ($response->categories as $category) {

                $prepared_data = strtolower($category->lang_data[0]->plural_name);
                $prepared_data = str_replace($replace_from, $replace_to, $prepared_data);
                $prepared_data = str_replace('--', '-', $prepared_data);

                $url = 'https://' . $this->_user->username . '/pl/categories/' . $prepared_data . '-' . $category->id . '.html';

                $categories_array[$category->id] = [
                    'id' => $category->id,
                    'parent' => $category->parent_id,
                    'TITLE' => $category->lang_data[0]->plural_name,
                    'URL' => $url,
                ];
                $i++;
            }

            $catOrdered=$this->makeRecursive($categories_array);
            if (count($catOrdered) > 0) {
                foreach ($catOrdered as $entry) {
                    $child = $categories->addChild('ITEM');
                    $child->addChild('TITLE', htmlspecialchars($entry['TITLE']));
                    $child->addChild('URL', htmlspecialchars($entry['URL']));
                    // var_dump($entry['children']);
                    if (isset($entry['children']) && !empty($entry['children'])) {
                        $child->addChild('ITEM', $this->makeXml($entry['children'], $child));
                    }
                    $file_handle = fopen($temp, 'a+');

                    fwrite($file_handle, $child->asXml());
                    fclose($file_handle);
                }
            }



            

            $this->_queue->increasePage();
            return true;

        } catch (\Exception $e) {
            echo $e->getMessage();
            $this->_queue->setErrorStatus($e->getMessage());
            die();
            return null;
        }
    }

    public function createCategoryXml($file, $temp)
    {
        $category = new \SimpleXMLElement('<CATEGORIES/>');
        $category->addChild('ITEM');

        file_put_contents($file, str_replace('<ITEM/>', file_get_contents($temp), $category->asXml()));
        file_put_contents($temp, '');
        return is_file($file)?10:0;



    }

    /**
     * @param $d
     * @param int $r
     * @param string $pk
     * @param string $k
     * @param string $c
     * @return array|mixed
     */
    private function makeRecursive($d, $r = 0, $pk = 'parent', $k = 'id', $c = 'children')
    {
        $m = array();
        foreach ($d as $e) {
            isset($m[$e[$pk]]) ?: $m[$e[$pk]] = array();
            isset($m[$e[$k]]) ?: $m[$e[$k]] = array();
            $m[$e[$pk]][] = array_merge($e, array($c => &$m[$e[$k]]));
        }

        return $m[$r];
    }

    /**
     * @param $data
     * @param $node
     * @return mixed
     */
    private function makeXml($data, $node)
    {
        if (count($data) > 0) {
            foreach ($data as $entry) {
                    print_r($entry);
                $child = $node->addChild('ITEM');
                $child->addChild('TITLE', htmlspecialchars($entry['TITLE']));
                $child->addChild('URL', htmlspecialchars($entry['URL']));
                // var_dump($entry['children']);
                if (isset($entry['children']) && !empty($entry['children'])) {
                    $child->addChild('ITEM', $this->makeXml($entry['children'], $child));
                }
            }
        }
        return $node;
    }
}
