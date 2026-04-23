<?php
namespace app\modules\xml_generator\controllers;

use app\models\User;
use app\modules\xml_generator\src\XmlFeed;
use app\services\FeedStorageService;
use yii\web\Controller;

class CategoriesController extends Controller
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
                $key = $integrator->getMinioCategoriesKey();
                if (!$storage->exists($key)) {
                    return 'Not ready yet';
                }
                header('Content-type: application/xml; charset=utf-8');
                $storage->stream($key);
                die;
            }
            if (is_file($integrator->getCategoriesFile())) {
                header('Content-type: application/xml; charset=utf-8');
                readfile($integrator->getCategoriesFile());
                die;
            }
            return 'Not ready yet';
        }

        try {
            $customers = new XmlFeed();
            $customers->setType(XmlFeed::CATEGORY);
            $customers->setUser($_user);
            $customers_file = $customers->getFile();
        } catch (\Exception $e) {
            return $e;
        }

        header('Content-type: application/xml; charset=utf-8');
        echo $customers_file;
        die;
    }
}
