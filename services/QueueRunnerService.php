<?php

namespace app\services;

use app\models\AppConfig;
use app\models\Queue;
use app\models\QueueExecutionLog;
use app\modules\shoper\models\Integrator;
use app\services\FeedDisabledException;
use Exception;
use yii\console\ExitCode;

class QueueRunnerService
{
    const QUEUE_EMPTY = 2;

    public function runById(int $queueId): int
    {
        $queue = Queue::findOne($queueId);

        if (!$queue) {
            echo "Queue #$queueId not found." . PHP_EOL;
            return ExitCode::UNSPECIFIED_ERROR;
        }

        return $this->run($queue->integration_type, ['forceId' => $queueId]);
    }

    public function run(string $type, array $config = []): int
    {
        if (!isset($config['forceId'])) {
            $config['forceId'] = 0;
        }

        $queue = $this->determineQueue($type, $config);

        if ($queue === null) {
            echo "nothing to do for type " . $type . PHP_EOL;
            return self::QUEUE_EMPTY;
        }

        Integrator::shoperLog('', $queue->id);
        Integrator::shoperLog('1.1 Establish Queue - ID: ' . $queue->id, $queue->id);

        echo '- - - Establish Queue - ID: ' . $queue->id . PHP_EOL;

        $user        = $queue->getCurrentUser();
        $parameters  = $queue->additionalParameters;
        $filePrepare = isset($parameters['objects_done']);

        if ($queue->integrated === Queue::RUNNING && $config['forceId'] == 0) {
            echo " job still in progress " . PHP_EOL;
            echo "from " . $queue->executed_at . PHP_EOL;
            if ($queue->executed_at) {
                $date          = new \DateTime($queue->executed_at);
                $date2         = new \DateTime(date('Y-m-d H:i:s'));
                $diffInSeconds = $date2->getTimestamp() - $date->getTimestamp();
                echo "in seconds: " . $diffInSeconds . PHP_EOL;
                if ($diffInSeconds > 3600) {
                    echo 'over hour - resetting' . PHP_EOL;
                    $queue->setPendingStatus();
                }
            }
            return ExitCode::OK;
        }

        if (!$queue->checkQueueConstraints()) {
            echo " job should not run " . PHP_EOL;
            $queue->setErrorStatus('job disabled');
            return ExitCode::OK;
        }

        $queue->setRunningStatus();

        if (!$user) {
            $queue->delete();
            return ExitCode::UNSPECIFIED_ERROR;
        }

        if (AppConfig::isTypeStopped($type)) {
            echo "Feed type '$type' is globally stopped — skipping." . PHP_EOL;
            $queue->setPendingStatus();
            return ExitCode::OK;
        }

        try {
            echo $queue->integration_type . " !!!# " . PHP_EOL;

            if ($queue->integration_type === 'subscribers') {
                echo "NOT FOR SHOPER " . PHP_EOL;
                $queue->setExecutedStatus();
                return ExitCode::OK;
            }

            $integrator = Integrator::findOne(['shop_url' => 'https://' . $user->username]);

            if ($filePrepare) {
                $res = $integrator->prepareDiversedFile($queue);
                if ($res == 10) {
                    $queue->setExecutedStatus();
                    $queue->setCountErrors(0);
                    return ExitCode::OK;
                }
                $queue->setPendingStatus();
                return ExitCode::OK;
            }

            Integrator::shoperLog('1.4 Step: Generating', $queue->id);
            $timeStart      = microtime(true);
            $functionResult = $integrator->{'generate' . ucfirst($queue->integration_type)}($queue);
            $elapsed        = microtime(true) - $timeStart;
            echo sprintf("--- TIME: %.3fs ---", $elapsed) . PHP_EOL;

            QueueExecutionLog::record(
                $queue->id,
                $user->id,
                $type,
                'generate',
                $elapsed,
                (int) $queue->page,
                (int) $queue->max_page
            );

            if ($functionResult && $queue->max_page <= $queue->page) {
                Integrator::shoperLog('1.5 Step: Making XML', $queue->id);

                if ($integrator->prepareFile($queue)) {
                    echo "set executed";
                    $queue->setExecutedStatus();
                    return ExitCode::OK;
                }
            }

            $queue->setPendingStatus();

            Integrator::shoperLog('1.6 Step: End', $queue->id);
            Integrator::shoperLog('-- 1.7 Integration Result: OK', $queue->id);
            return ExitCode::OK;

        } catch (FeedDisabledException $e) {
            echo "FEED DISABLED — cancelling queue and removing future entries." . PHP_EOL;
            $queue->setDisabledStatus($e->getMessage());
            Queue::deleteFutureQueuesForUser($queue->current_integrate_user, $type);
            return ExitCode::OK;

        } catch (Exception $e) {
            Integrator::shoperLog('-- 1.8 Integration Result: ERROR:', $queue->id);
            Integrator::shoperLog(print_r($e->getCode(), true), $queue->id);
            Integrator::shoperLog(print_r($e->getMessage(), true), $queue->id);
            Integrator::shoperLog('-- 1.9 Integration Result: ERROR', $queue->id);
            echo $e->getMessage();

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

    public function determineQueue(string $type, array $config = []): ?Queue
    {
        if ($config['forceId'] != 0) {
            return Queue::findOne($config['forceId']);
        }

        if (isset($config['pararel_processing']) && $config['pararel_processing']) {
            return Queue::findPararelForType($type, $config['offset']);
        }

        if (isset($config['shop_type'])) {
            return Queue::findLastForTypeAndShop($type, $config['shop_type']);
        }

        return Queue::findLastForType($type);
    }
}
