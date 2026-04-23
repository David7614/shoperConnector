<?php
namespace app\modules\xml_generator\controllers;

use app\models\User;
use app\modules\xml_generator\src\XmlFeed;
use app\services\FeedStorageService;
use yii\web\Controller;

class OrdersController extends Controller
{
    public function actionGenerate($uuid)
    {
        if(($_user = User::findByUUID($uuid)) === null)
        {
            return 'User not found';
        }

        if ($_user->shop_type=='shoper'){
            $integrator=\app\modules\shoper\models\Integrator::findOne(['shop_url'=>'https://'.$_user->username]);
            if (FeedStorageService::isConfigured()) {
                $storage = FeedStorageService::create();
                $key = $integrator->getMinioOrdersKey();
                if (!$storage->exists($key)) {
                    return 'Not ready yet';
                }
                header('Content-type: application/xml; charset=utf-8');
                $storage->stream($key);
                die;
            }
            if (is_file($integrator->getOrdersFile())) {
                header('Content-type: application/xml; charset=utf-8');
                readfile($integrator->getOrdersFile());
                die;
            }
            return 'Not ready yet';
        }

        try {
            $orders = new XmlFeed();
            $orders->setType(XmlFeed::ORDER);
            $orders->setUser($_user);
            $orders_file_path = $orders->getFile(true);
        } catch (\Exception $e) {
            return $e->getMessage();
        }

        $filename='orders.xml';
        header('Content-type: application/xml; charset=utf-8');
        header("Content-Length: ".filesize(trim($orders_file_path)));
        header("Content-Disposition: attachment; filename=\"$filename\"");
        header("Content-Transfer-Encoding: binary");
        @readfile($orders_file_path);
        die;
    }
}
