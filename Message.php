<?php

/**
 * Message.php
 * @author Saranga Abeykoon http://nterms.com
 */

namespace nterms\mailqueue;

use Yii;
use nterms\mailqueue\models\Queue;

/**
 * Extends `yii\swiftmailer\Message` to enable queuing.
 *
 * @see http://www.yiiframework.com/doc-2.0/yii-swiftmailer-message.html
 */
class Message extends \yii\swiftmailer\Message {

    private $db;

    /**
     * Method to set db instance to use. It is going to be passed down to Queue.
     */
    public function setDb(\yii\db\Connection $db) {
        $this->db = $db;
        return $this;
    }

    /**
     * Method to get db instance.
     */
    public function getDb() {
        return $this->db ? $this->db : Yii::$app->db;
    }

    /**
     * Enqueue the message storing it in database.
     *
     * @param timestamp $time_to_send
     * @return boolean true on success, false otherwise
     */
    public function queue($time_to_send = 'now') {
        if ($time_to_send == 'now') {
            $time_to_send = time();
        }

        $item = new Queue();
        $item->setDb($this->getDb());

        $item->subject = $this->getSubject();
        $item->attempts = 0;
        $item->swift_message = base64_encode(serialize($this));
        $item->time_to_send = date('Y-m-d H:i:s', $time_to_send);

        return $item->save();
    }
}
