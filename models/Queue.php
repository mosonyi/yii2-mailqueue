<?php

namespace nterms\mailqueue\models;

error_reporting(E_ALL & ~E_NOTICE);
ini_set("unserialize_callback_func", "self::callback_no_spl");

use Yii;
use yii\db\ActiveRecord;
use nterms\mailqueue\MailQueue;
use nterms\mailqueue\Message;

/**
 * This is the model class for table "{{%mail_queue}}".
 *
 * @property string $subject
 * @property integer $created_at
 * @property integer $attempts
 * @property integer $last_attempt_time
 * @property integer $sent_time
 * @property string $time_to_send
 * @property string $swift_message
 */
class Queue extends ActiveRecord {

    private static $db;

    public static function callback_no_spl($classname) {
        throw new \Exception("The class $classname needs to be available.");
    }

    /**
     * Method to set db instance to use.
     */
    public function setDb(\yii\db\Connection $db) {
        self::$db = $db;
    }

    /**
     * This method is overriden, because we have to be able to use a different database connection too.
     * It has to be static to overwrite the inherited method.
     */
    public static function getDb() {
        return self::$db ? self::$db : Yii::$app->db;
    }

    /**
     * @inheritdoc
     */
    public static function tableName() {
        return Yii::$app->get(MailQueue::NAME)->table;
    }

    /**
     * @inheritdoc
     */
    public function behaviors() {
        return [
            'timestamp' => [
                'class' => 'yii\behaviors\TimestampBehavior',
                'attributes' => [
                    ActiveRecord::EVENT_BEFORE_INSERT => ['created_at'],
                    ActiveRecord::EVENT_BEFORE_UPDATE => ['last_attempt_time'],
                ],
                'value' => new \yii\db\Expression('NOW()'),
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function rules() {
        return [
            [['created_at', 'attempts', 'last_attempt_time', 'sent_time'], 'integer'],
            [['time_to_send', 'swift_message'], 'required'],
            [['subject'], 'safe'],
        ];
    }

    public function toMessage() {
        $ret = null;
        try {
            $ret = unserialize(base64_decode($this->swift_message));
            return $ret;
        } catch (\Exception $ex) {
            throw new \Exception($ex->getMessage());
        }
    }
}
