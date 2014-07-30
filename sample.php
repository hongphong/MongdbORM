<?php

Yii::import('common.lib.mongorecord.MongoRecord');

/**
 * This is the model class for collection "sys_notification".
 *
 * 
 * @property $notification_id
 * @property $notification_title
 * @property $notification_content
 * @property $notification_url
 * @property $to_id
 * @property $to_type
 * @property $from_id
 * @property $from_type
 * @property $user_id
 * @property $read_status
 * @property $real_time_sent
 * @property $is_new
 * @property $status
 * @property $priority
 * @property $public_date
 * @property $expired_date
 * @property $created_time
 * @property $type
 * @property $created_by
 */
class SysNotification extends MongoRecord {

    // object type
    const USER_TYPE = 1;
    const ADVERTISE_TYPE = 2;
    // read_status
    const STATUS_UNREAD = 1;
    const STATUS_READ = 2;
    // const for new status
    const IS_NEW = 1;
    const IS_UNNEW = 0;
    // const check send notification realtime
    const IS_ENABLE_REAL_TIME = 1;
    const IS_DISABLE_REAL_TIME = 0;

    // current page
    public $curretnPage = 0;
    // limit when get data
    public $limit = 10;
    public $sort = array('public_date' => -1);

    /**
     * create properties
     * need define collection name and attributes
     */
    public function init() {
        # define collection
        $this->setCollection('sys_notification');
        # define attributes
        $this->defineAttributes(
                array(
                    'notification_id',
                    'notification_title',
                    'notification_content',
                    'notification_url',
                    'to_id',
                    'to_type',
                    'from_id',
                    'from_type',
                    'user_id',
                    'read_status',
                    'is_new',
                    'real_time_sent',
                    'status',
                    'priority',
                    'public_date',
                    'expired_date',
                    'created_time',
                    'type',
                    'created_by')
        );
    }

    /**
     * validate
     */
    public function rules() {
        return array(
            array('notification_title,notification_content,notification_url', 'required')
        );
    }

    /**
     * after save
     */
    public function afterSave() {
        // get max notification_id;
        parent::afterSave();
    }

    /**
     * get notification
     * 
     * To set condition with attributes, just only set value for one attribute such as: $this->read_status = 1, condition will be set: read_status = 1
     * Set limit, currentPage to set limit, skip
     * 
     * @default get notification of only user who is logging
     * @param interger $limit 
     * @return MongoCursor
     */
    public function getNotification($limit = null) {

        //prepare criteria
        $this->setCriteriaBaseAttribute();
        if ($this->to_id === null) {
            $this->to_id = Yii::app()->user->id;
        }
        // query to find data
        $limit = $limit !== null ? intval($limit) : $this->limit;
        $result = array();
        $skip = $limit * intval($this->curretnPage);
        if ($limit) {
            $result = $this->find($this->criteria)->limit($limit)->skip($skip)->sort($this->sort);
        } else {
            $result = $this->find($this->criteria)->sort($this->sort);
        }
        return $result;
    }

    /**
     * count new notifications
     * @default count notifications of only user who is logging
     */
    public function countNewNotification() {
        //prepare criteria
        $this->setCriteriaBaseAttribute();
        if ($this->to_id === null) {
            $this->to_id = Yii::app()->user->id;
        }
        return $this->count($this->criteria);
    }

    /**
     * create default datas to insert
     * @return \SysNotification
     */
    public function defaultDatasInsert() {
        $default['read_status'] = self::STATUS_UNREAD;
        $default['is_new'] = self::IS_NEW;
        $default['real_time_sent'] = self::IS_ENABLE_REAL_TIME;
        $default['public_date'] = ClaDateTime::getIntTime();
        $default['created_time'] = $default['public_date'];
        $default['created_by'] = intval(Yii::app()->user->id);
        $default['from_id'] = intval(Yii::app()->user->id);
        $default['from_type'] = self::USER_TYPE;
        $default['to_type'] = self::USER_TYPE;
        foreach ($default as $key => $item) {
            if ($this->$key === null) {
                $this->$key = $item;
            }
        }
        unset($default);
        return $this;
    }

    /**
     * add notification 
     * 
     * make notification_id is auto_increament
     * @param array $toIds array that includes user_id: array(1,2,3)
     * @param type $send_real_time send notification directly or not
     *  + if $send_real_time is true, set property real_time_sent = self::IS_REAL_TIME_SEND
     * @return boolean
     */
    public function addNotifications($toIds = array(), $send_real_time = true) {
        if (!is_array($toIds) || !$toIds) {
            return false;
        }
        $toIds = array_unique($toIds);
        $this->defaultDatasInsert();
        if (!$this->validate()) {
            return false;
        }
        $prepare = array();
        $data = $this->getAttributes();
        $real_time = $send_real_time === true ? self::IS_DISABLE_REAL_TIME : self::IS_ENABLE_REAL_TIME;
        foreach ($toIds as $userId) {
            $data['to_id'] = intval($userId);
            $data['real_time_sent'] = $real_time;
            $prepare[] = $data;
        }
        $this->multiInsert($prepare);
        # send notification if this param is setted
        if ($send_real_time === true) {
            $xmpp = new RealTimeXmpp();
            $xmpp->setSendTo($toIds);
            $xmpp->message = $data;
            $xmpp->sendNotification();
        } else {
            $queue = new SysNotificationQueue();
            $queue->setAttributes($data);
            $queue->to_id = implode(',', $toIds);
            $queue->insert();
        }
        return false;
    }

}
