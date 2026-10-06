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
use yii\db\Expression;
use yii\db\Query;
use app\models\User;
use SoapClient;
use app\models\Customers;
use app\models\Product;
use app\modules\shoper\models\ShoperCategories;
use app\modules\shoper\models\ShoperCategoriesLanguage;
use app\modules\shoper\models\ShoperShops;
use app\services\QueueRunnerService;


/*

integration_types:
category
customer
order
product
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
    }

    public function actionGenerateTags($forceId=0)
    {
        return (new QueueRunnerService())->run(XmlFeed::TAGS, ['forceId'=>$forceId]);
    }
    public function actionGenerateProducts($forceId=0, $forcePage=null)
    {
        return (new QueueRunnerService())->run(XmlFeed::PRODUCT, ['forceId'=>$forceId, 'forcePage'=>$forcePage]);
    }

    /**
     * Diagnostyka filtra incremental: sprawdza, ktore pole daty realnie zaweza
     * wynik zasobu w API Shopera. Jesli liczba stron jest taka sama jak bez
     * filtra, to znaczy ze Shoper dane pole ignoruje.
     *
     * php yii xml-generator/test-api-filter <userId> [resource] [date]
     *
     * resource: product (domyslnie) | user | order
     */
    public function actionTestApiFilter($userId, $resource = 'product', $date = null)
    {
        $date = $date ?: date('Y-m-d', strtotime('-7 days'));

        $user = User::findOne((int)$userId);
        if (!$user) {
            echo "Nie ma uzytkownika #$userId" . PHP_EOL;
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $integrator = Integrator::findOne(['shop_url' => 'https://' . $user->username]);
        if (!$integrator) {
            echo "Nie ma integratora dla " . $user->username . PHP_EOL;
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $resourceClasses = [
            'product' => \DreamCommerce\ShopAppstoreLib\Resource\Product::class,
            'user'    => \DreamCommerce\ShopAppstoreLib\Resource\User::class,
            'order'   => \DreamCommerce\ShopAppstoreLib\Resource\Order::class,
        ];

        if (!isset($resourceClasses[$resource])) {
            echo "Nieznany zasob '$resource'. Dostepne: " . implode(', ', array_keys($resourceClasses)) . PHP_EOL;
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $resourceClass = $resourceClasses[$resource];
        $client        = $integrator->prepareConnection()->getClient();

        echo "sklep:  " . $user->username . PHP_EOL;
        echo "zasob:  " . $resource . PHP_EOL;
        echo "data:   >= " . $date . PHP_EOL . PHP_EOL;

        foreach ([null, 'updated_at', 'edit_date', 'add_date', 'date'] as $field) {
            $label = str_pad($field ?: '(bez filtra)', 14);

            try {
                $api = new $resourceClass($client);
                if ($field !== null) {
                    $api->filters([$field => ['>=' => $date]]);
                }
                $response = $api->get();
                echo $label . ' -> stron: ' . $response->pages . ', rekordow: ' . $response->count . PHP_EOL;
            } catch (\Throwable $e) {
                echo $label . ' -> BLAD: ' . $e->getMessage() . PHP_EOL;
            }
        }

        echo PHP_EOL . "Pole dziala, jesli zwraca wyraznie mniej stron niz '(bez filtra)'." . PHP_EOL;

        return ExitCode::OK;
    }

    /**
     * Przelicza product.CATEGORYTEXT ze sciezki kategorii w bazie, bez odpytywania API.
     *
     * CATEGORYTEXT jest cache'em zapisywanym tylko przy imporcie produktu, a import
     * pomija produkty o niezmienionym params_hash (i tak ich nie pobiera przy
     * exporcie inkrementalnym). Wpisy zapisane przed naprawa tlumaczen kategorii
     * ("4237 no pl_PLtranslation |...") nigdy same sie wiec nie odswieza.
     *
     * Najpierw trzeba przepuscic import kategorii (kolejka 'category', leci co noc),
     * zeby w shoper_categories_language byly wiersze we wszystkich jezykach - inaczej
     * getFullPath() podstawi nazwy z jezyka, ktory akurat jest w bazie.
     *
     * php yii xml-generator/rebuild-category-text <userId|0 = wszystkie sklepy> [apply=0]
     */
    public function actionRebuildCategoryText($userId, $apply = 0)
    {
        $apply = (int) $apply;

        $users = (int) $userId
            ? User::find()->where(['id' => (int) $userId])->all()
            : User::find()->where(['shop_type' => 'shoper'])->all();

        if (!$users) {
            echo "Nie ma takiego uzytkownika" . PHP_EOL;
            return ExitCode::UNSPECIFIED_ERROR;
        }

        if (!$apply) {
            echo "TRYB PODGLADU - nic nie zapisuje. Zeby zapisac: dopisz 1 jako drugi argument." . PHP_EOL . PHP_EOL;
        }

        $totalChanged = 0;

        foreach ($users as $user) {
            $integrator = Integrator::findOne(['shop_url' => 'https://' . $user->username]);
            if (!$integrator) {
                echo "[{$user->id}] {$user->username} - brak integratora, pomijam" . PHP_EOL;
                continue;
            }

            $categoryMap = [];
            foreach (ShoperCategories::find()->where(['shoper_shops_id' => $integrator->id])->all() as $c) {
                $categoryMap[$c->category_id] = $c;
            }

            // getFullPath() chodzi po drzewie zapytanie po zapytaniu (rodzic + tlumaczenie
            // na kazdym poziomie). Przy zdalnej bazie to godziny, wiec tlumaczenia
            // wczytujemy raz i sciezki skladamy w pamieci.
            $translations = [];
            $fallbacks    = [];
            $langRows = (new Query())
                ->select(['l.shoper_categories_id', 'l.translation', 'l.name'])
                ->from(['l' => ShoperCategoriesLanguage::tableName()])
                ->innerJoin(['c' => ShoperCategories::tableName()], 'c.id = l.shoper_categories_id')
                ->where(['c.shoper_shops_id' => $integrator->id])
                ->orderBy(['l.isdefault' => SORT_DESC, 'l.id' => SORT_ASC])
                ->all();
            foreach ($langRows as $r) {
                $translations[$r['shoper_categories_id']][$r['translation']] = $r['name'];
                // pierwszy wiersz w tej kolejnosci to dokladnie to, co zwraca
                // ShoperCategories::getTranslated() przy braku zadanego locale
                if (!isset($fallbacks[$r['shoper_categories_id']])) {
                    $fallbacks[$r['shoper_categories_id']] = $r['name'];
                }
            }
            unset($langRows);

            // Bez kompletu tlumaczen getFullPath() podstawi nazwy z jezyka zapasowego.
            // Liczba kategorii z wiecej niz jednym jezykiem mowi, czy import kategorii
            // z poprawka juz przeszedl: przed poprawka kazda kategoria miala dokladnie
            // jeden wiersz, niezaleznie od tego ile jezykow ma sklep.
            $totalCategories = count($categoryMap);
            $locales = Product::find()->select('translation')->distinct()
                ->where(['user_id' => $user->id])->column();

            $coverage = [];
            foreach ($locales as $locale) {
                $coverage[$locale] = (int) ShoperCategoriesLanguage::find()->alias('l')
                    ->innerJoin(['c' => ShoperCategories::tableName()], 'c.id = l.shoper_categories_id')
                    ->where(['c.shoper_shops_id' => $integrator->id, 'l.translation' => $locale])
                    ->count();
            }

            $multiLang = (new Query())
                ->select('l.shoper_categories_id')
                ->from(['l' => ShoperCategoriesLanguage::tableName()])
                ->innerJoin(['c' => ShoperCategories::tableName()], 'c.id = l.shoper_categories_id')
                ->where(['c.shoper_shops_id' => $integrator->id])
                ->groupBy('l.shoper_categories_id')
                ->having('COUNT(*) > 1')
                ->count();

            $coverageText = [];
            foreach ($coverage as $locale => $have) {
                $coverageText[] = "$locale $have/$totalCategories";
            }

            echo "[{$user->id}] kategorie: $totalCategories, pokrycie tlumaczen: "
                . implode(', ', $coverageText) . PHP_EOL;
            echo "[{$user->id}] kategorii z wiecej niz jednym jezykiem: $multiLang" . PHP_EOL;

            if (count($locales) > 1 && $multiLang == 0) {
                echo "[{$user->id}] UWAGA: import kategorii z poprawka jeszcze nie przeszedl calosci."
                    . " Jedno wywolanie generate-categories przerabia JEDNA strone API,"
                    . " cale przejscie robi loop-categories." . PHP_EOL;
            } elseif (array_sum($coverage) < $totalCategories * count($locales)) {
                echo "[{$user->id}] Kategorie bez wiersza w danym jezyku nie maja tam nazwy"
                    . " po stronie Shopera - dostana nazwe z jezyka zapasowego." . PHP_EOL;

                foreach ($coverage as $locale => $have) {
                    if ($have >= $totalCategories) {
                        continue;
                    }
                    $missing = (new Query())
                        ->select('c.category_id')
                        ->from(['c' => ShoperCategories::tableName()])
                        ->leftJoin(['l' => ShoperCategoriesLanguage::tableName()],
                            'l.shoper_categories_id = c.id AND l.translation = :loc', [':loc' => $locale])
                        ->where(['c.shoper_shops_id' => $integrator->id])
                        ->andWhere(['l.id' => null])
                        ->limit(30)
                        ->column();
                    echo "[{$user->id}]   bez $locale (" . ($totalCategories - $have) . "): "
                        . implode(', ', $missing) . PHP_EOL;
                }
            }

            $pathCache      = [];
            $checked        = 0;
            $changed        = 0;
            $skipped        = 0;
            $examples       = [];
            $lastId         = 0;
            $reportedAt     = 0;
            $pendingUpdates = [];

            // response potrafi miec po 7 kB na wiersz (dla 394 to 357 MB lacznie) - przez
            // siec do zdalnej bazy skan trwalby wieki, wiec category_id wycinamy w MySQL
            $categoryIdExpr = new Expression(
                "CASE WHEN LOCATE('s:11:\"category_id\";', response) > 0"
                . " THEN SUBSTRING(response, LOCATE('s:11:\"category_id\";', response) + 19, 32)"
                . " ELSE '' END"
            );

            while (true) {
                $batch = (new Query())
                    ->select(['ID', 'PRODUCT_ID', 'translation', 'CATEGORYTEXT', 'cat_raw' => $categoryIdExpr])
                    ->from(Product::tableName())
                    ->where(['user_id' => $user->id])
                    ->andWhere(['>', 'ID', $lastId])
                    ->orderBy(['ID' => SORT_ASC])
                    ->limit(1000)
                    ->all();

                if (!$batch) {
                    break;
                }

                foreach ($batch as $row) {
                    $lastId = $row['ID'];
                    $checked++;

                    $categoryId = $this->extractCategoryId($row['cat_raw'], (int) $row['ID']);
                    if ($categoryId === null) {
                        $skipped++;
                        continue;
                    }

                    $key = $categoryId . '|' . $row['translation'];

                    if (!isset($pathCache[$key])) {
                        $pathCache[$key] = isset($categoryMap[$categoryId])
                            ? $this->buildCategoryPath($categoryId, $row['translation'], $categoryMap, $translations, $fallbacks)
                            : 'brak';
                    }

                    if ($pathCache[$key] === $row['CATEGORYTEXT']) {
                        continue;
                    }

                    $changed++;
                    if (count($examples) < 3) {
                        $examples[] = "  #{$row['PRODUCT_ID']} ({$row['translation']})" . PHP_EOL
                            . "    bylo:  " . $row['CATEGORYTEXT'] . PHP_EOL
                            . "    bedzie: " . $pathCache[$key];
                    }

                    // wiele produktow dzieli te sama sciezke - grupujemy, zeby zamiast
                    // dziesiatek tysiecy pojedynczych UPDATE poszlo kilka na partie
                    $pendingUpdates[$pathCache[$key]][] = $row['ID'];
                }

                if ($apply && $pendingUpdates) {
                    foreach ($pendingUpdates as $value => $ids) {
                        Product::updateAll(['CATEGORYTEXT' => (string) $value], ['ID' => $ids]);
                    }
                }
                $pendingUpdates = [];

                if ($checked - $reportedAt >= 5000) {
                    $reportedAt = $checked;
                    echo "[{$user->id}] ... sprawdzonych $checked, do poprawy $changed" . PHP_EOL;
                }
            }

            echo "[{$user->id}] {$user->username}: sprawdzonych $checked, do poprawy $changed"
                . ($skipped ? ", pominietych (brak response) $skipped" : '') . PHP_EOL;
            foreach ($examples as $example) {
                echo $example . PHP_EOL;
            }

            $totalChanged += $changed;
        }

        echo PHP_EOL . ($apply ? "Zapisano: $totalChanged" : "Do poprawy lacznie: $totalChanged") . PHP_EOL;

        return ExitCode::OK;
    }

    /**
     * Przebudowuje sam plik feedu z tego, co juz jest w bazie - bez importu z API.
     *
     * Feed to statyczny plik w MinIO, budowany dopiero gdy kolejka dojdzie do
     * ostatniej strony. Po recznej poprawce danych (np. rebuild-category-text)
     * XML zostaje stary az do nastepnego pelnego przebiegu kolejki.
     *
     * php yii xml-generator/rebuild-feed-file <userId> [product|category|customer|order]
     */
    public function actionRebuildFeedFile($userId, $type = 'product')
    {
        $user = User::findOne((int) $userId);
        if (!$user) {
            echo "Nie ma uzytkownika #$userId" . PHP_EOL;
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $integrator = Integrator::findOne(['shop_url' => 'https://' . $user->username]);
        if (!$integrator) {
            echo "Nie ma integratora dla " . $user->username . PHP_EOL;
            return ExitCode::UNSPECIFIED_ERROR;
        }

        // prepareFile() potrzebuje kolejki tylko po to, zeby poznac usera i typ
        $queue = Queue::find()
            ->where(['current_integrate_user' => $user->id, 'integration_type' => $type])
            ->orderBy(['id' => SORT_DESC])
            ->one();

        if (!$queue) {
            echo "Nie ma kolejki typu '$type' dla uzytkownika #{$user->id}" . PHP_EOL;
            return ExitCode::UNSPECIFIED_ERROR;
        }

        echo "Buduje feed '$type' dla " . $user->username . PHP_EOL;
        $start = microtime(true);

        if (!$integrator->prepareFile($queue)) {
            echo "Nie udalo sie zbudowac pliku" . PHP_EOL;
            return ExitCode::UNSPECIFIED_ERROR;
        }

        echo sprintf("Gotowe w %.1fs", microtime(true) - $start) . PHP_EOL;

        return ExitCode::OK;
    }

    /**
     * Odpowiednik ShoperCategories::getFullPath(), ale bez zapytan - na mapach
     * zbudowanych raz dla calego sklepu.
     */
    private function buildCategoryPath($categoryId, $locale, array $categoryMap, array $translations, array $fallbacks): string
    {
        $chain = [];
        $current = $categoryId;

        while (isset($categoryMap[$current]) && count($chain) < 50) {
            $category = $categoryMap[$current];
            $chain[]  = $category;

            $parentId = (int) $category->parent_id;
            if (!$parentId) {
                break;
            }
            $current = $parentId;
        }

        $parts = [];
        foreach (array_reverse($chain) as $category) {
            $name = $translations[$category->id][$locale] ?? ($fallbacks[$category->id] ?? null);
            $parts[] = $name !== null
                ? $name
                : $category->category_id . ' no ' . $locale . 'translation ';
        }

        return implode('|', $parts);
    }

    /**
     * Wyciaga category_id z wycinka serializowanego response (s:4:"1621" albo i:1621).
     * Gdy format jest inny, dociaga response tylko dla tego jednego wiersza.
     */
    private function extractCategoryId($raw, int $productRowId): ?int
    {
        $raw = explode(';', (string) $raw)[0];

        if (preg_match('/^s:\d+:"(\d+)"$/', $raw, $m) || preg_match('/^i:(\d+)$/', $raw, $m)) {
            return (int) $m[1];
        }

        $response = Product::find()->select('response')->where(['ID' => $productRowId])->scalar();
        $res      = @unserialize((string) $response);

        return (is_object($res) && isset($res->category_id)) ? (int) $res->category_id : null;
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

    public function actionLoopCategories(int $limitSeconds = 540)
    {
        return $this->loopQueue(XmlFeed::CATEGORY, [], $limitSeconds);
    }

    public function actionLoopTags(int $limitSeconds = 540)
    {
        return $this->loopQueue(XmlFeed::TAGS, [], $limitSeconds);
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
            } catch (\Throwable $e) {
                $this->printThrowable($e);

                if ($e->getMessage() == 'HTTP request failed') {
                    $queue->setShoperApiDelay();
                    $queue->setPendingStatus();
                    return ExitCode::UNSPECIFIED_ERROR;
                }

                if ($e->getMessage() == 'Retries count exceeded') {
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
        } catch (\Throwable $e) {
            $this->printThrowable($e);

            $queue->raiseCountErrors();
            if ($queue->getCountErrors()<30){
                $queue->setPendingStatus();
            }else{
                $queue->setErrorStatus($e->getMessage());
            }

            return ExitCode::UNSPECIFIED_ERROR;
        }
    }

    private function printThrowable(\Throwable $e): void
    {
        echo PHP_EOL;
        echo '=== ' . get_class($e) . ' ===' . PHP_EOL;
        echo 'Message : ' . $e->getMessage() . PHP_EOL;
        echo 'File    : ' . $e->getFile() . ':' . $e->getLine() . PHP_EOL;
        echo 'Code    : ' . $e->getCode() . PHP_EOL;
        echo 'Trace   :' . PHP_EOL . $e->getTraceAsString() . PHP_EOL;
        if ($e->getPrevious()) {
            echo '--- Caused by ---' . PHP_EOL;
            $this->printThrowable($e->getPrevious());
        }
    }
}
