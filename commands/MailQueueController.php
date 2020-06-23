<?php

/**
 * Mail Command Controller
 * 
 * @author Rochdi B. <rochdi80tn@gmail.com>
 */

namespace nterms\mailqueue\commands;

use yii\console\Controller;
use yii\console\widgets\Table;

/**
 * This command processes the mail queue
 *
 * @author Rochdi B. <rochdi80tn@gmail.com>
 * @since 0.0.6
 */
class MailQueueController extends Controller {

    public $defaultAction = 'process';
    public $debug = false;

    public function options($actionID) {
        return array_merge(parent::options($actionID), [
            'debug'
        ]);
    }

    /**
     * This command processes the mail queue     
     */
    public function actionProcess() {
        $stat = \Yii::$app->mailqueue->process();
        if ($this->debug) {
            echo Table::widget([
                'headers' => ['Messages being sent from queue', 'Not sent', 'Failed to read (unserialize)', 'Other error'],
                'rows' => [
                    [$stat['items_count'], $stat['notSent'], $stat['unserializeFailure'], $stat['otherFailure']],
                ],
            ]);
        }

        if (!empty($stat['error_messages'])) {
            echo Table::widget([
                'headers' => ['Most common error messages'],
                'rows' => [
                    [$stat['error_messages']],
                ],
            ]);
        }
    }

    public function actionStat() {
        $stat = \Yii::$app->mailqueue->stat();
        echo Table::widget([
            'headers' => ['Messages in queue', 'More > 0 attempts', 'Failed to read (unserialize)', 'Other error'],
            'rows' => [
                [$stat['items_count'], $stat['items_attempts_count'], $stat['unserializeFailure'], $stat['otherFailure']],
            ],
        ]);

        if (!empty($stat['error_messages'])) {
            echo Table::widget([
                'headers' => ['Most common error messages'],
                'rows' => [
                    [$stat['error_messages']],
                ],
            ]);
        }
    }

}
