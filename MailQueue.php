<?php

/**
 * MailQueue.php
 * @author Saranga Abeykoon http://nterms.com
 */

namespace nterms\mailqueue;

use Exception;
use nterms\mailqueue\models\Queue;
use nterms\mailqueue\Message;
use Throwable;
use Yii;
use yii\swiftmailer\Mailer;

/**
 * MailQueue is a sub class of [yii\switmailer\Mailer](https://github.com/yiisoft/yii2-swiftmailer/blob/master/Mailer.php)
 * which intends to replace it.
 *
 * Configuration is the same as in `yii\switmailer\Mailer` with some additional properties to control the mail queue
 *
 * ~~~
 * 	'components' => [
 * 		...
 * 		'mailqueue' => [
 * 			'class' => 'nterms\mailqueue\MailQueue',
 * 			'table' => '{{%mail_queue}}',
 * 			'mailsPerRound' => 10,
 * 			'maxAttempts' => 3,
 * 			'transport' => [
 * 				'class' => 'Swift_SmtpTransport',
 * 				'host' => 'localhost',
 * 				'username' => 'username',
 * 				'password' => 'password',
 * 				'port' => '587',
 * 				'encryption' => 'tls',
 * 			],
 * 		],
 * 		...
 * 	],
 * ~~~
 *
 * @see http://www.yiiframework.com/doc-2.0/yii-swiftmailer-mailer.html
 * @see http://www.yiiframework.com/doc-2.0/ext-swiftmailer-index.html
 *
 * This extension replaces `yii\switmailer\Message` with `nterms\mailqueue\Message'
 * to enable queuing right from the message.
 *
 */
class MailQueue extends Mailer {

    const NAME = 'mailqueue';

    /**
     * @var string message default class name.
     */
    public $messageClass = 'nterms\mailqueue\Message';

    /**
     * @var string the name of the database table to store the mail queue.
     */
    public $table = '{{%mail_queue}}';

    /**
     * @var integer the default value for the number of mails to be sent out per processing round.
     */
    public $mailsPerRound = 10;

    /**
     * @var integer maximum number of attempts to try sending an email out.
     */
    public $maxAttempts = 3;

    /**
     * @var boolean Purges messages from queue after sending
     */
    public $autoPurge = true;

    /**
     * @var string override email_to with dev_email to in DEV mode
     */
    public $dev_email_to = "";

    /**
     * Initializes the MailQueue component.
     */
    public function init() {
        parent::init();
    }

    /**
     * Sends out the messages in email queue and update the database.
     *
     * @return boolean true if all messages are successfully sent out
     */
    public function process() {
        if (Yii::$app->db->getTableSchema($this->table) == null) {
            throw new \yii\base\InvalidConfigException('"' . $this->table . '" not found in database. Make sure the db migration is properly done and the table is created.');
        }

        $items_count = 0;
        $unserializeFailure = 0;
        $otherFailure = 0;
        $notSent = 0;

        $error_messages = array();

        $items = Queue::find()->where(['and', ['sent_time' => NULL], ['<', 'attempts', $this->maxAttempts], ['<=', 'time_to_send', date('Y-m-d H:i:s')]])->orderBy(['created_at' => SORT_ASC, 'attempts' => SORT_ASC])->limit($this->mailsPerRound);
        foreach ($items->each() as $item) {
            $attributes = ['attempts', 'last_attempt_time'];
            $item->attempts++;
            $item->last_attempt_time = new \yii\db\Expression('NOW()');

            $items_count++;
            try {
                if ($message = $item->toMessage()) {
                    if (getenv('YII_ENV') == 'dev' && !empty($this->dev_email_to)) {
                        $message->setTo($this->dev_email_to);
                    }

                    if ($this->send($message)) {
                        $item->sent_time = new \yii\db\Expression('NOW()');
                        $attributes[] = 'sent_time';
                    } else {
                        $notSent++;
                    }
                }
            } catch (Exception $e) {
                if (!in_array($e->getMessage(), $error_messages)) {
                    $error_messages[] = $e->getMessage();
                }
                $unserializeFailure++;
            } catch (Throwable $t) {
                if (!in_array($t->getMessage(), $error_messages)) {
                    $error_messages[] = $t->getMessage();
                }
                $otherFailure++;
            }

            $item->updateAttributes($attributes);
        }

        // Purge messages now?
        if ($this->autoPurge) {
            $this->purge();
        }

        $stat['items_count'] = $items_count;
        $stat['unserializeFailure'] = $unserializeFailure;
        $stat['otherFailure'] = $otherFailure;
        $stat['notSent'] = $notSent;
        $stat['error_messages'] = $error_messages;

        return $stat;
    }

    public function stat() {
        if (Yii::$app->db->getTableSchema($this->table) == null) {
            throw new \yii\base\InvalidConfigException('"' . $this->table . '" not found in database. Make sure the db migration is properly done and the table is created.');
        }

        $unserializeFailure = 0;
        $otherFailure = 0;

        $items_count = Queue::find()->count();
        $items_attempts_count = Queue::find()->where(['>', 'attempts', 0])->count();

        $error_messages = array();

        $items = Queue::find(['sent_time' => NULL]);
        foreach ($items->each() as $item) {
            try {
                if ($message = $item->toMessage()) {
                    if ($message instanceof Message) {
                        /**
                         * try to read message
                         */
                        $message->toString();
                        $message->getSubject();
                        $message->getTo();
                        $message->getFrom();
                    }
                }
            } catch (Exception $e) {
                if (!in_array($e->getMessage(), $error_messages)) {
                    $error_messages[] = $e->getMessage();
                }
                $unserializeFailure++;
            } catch (Throwable $t) {
                if (!in_array($t->getMessage(), $error_messages)) {
                    $error_messages[] = $t->getMessage();
                }
                $otherFailure++;
            }
        }

        $stat['items_count'] = $items_count;
        $stat['items_attempts_count'] = $items_attempts_count;
        $stat['unserializeFailure'] = $unserializeFailure;
        $stat['otherFailure'] = $otherFailure;
        $stat['error_messages'] = $error_messages;

        return $stat;
    }

    /**
     * Deletes sent messages from queue.
     *
     * @return int Number of rows deleted
     */
    public function purge() {
        return Queue::deleteAll(['or', ['sent_time' => 'IS NOT NULL'], ['>=', 'attempts', $this->maxAttempts]]);
    }

}
