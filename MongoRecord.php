<?php

/**
 * Basic Mongo Class base on native php mongo driver
 * Base on ORM design patterm
 *
 * @author Phong Pham Hong <phongbro1805@gmail.com>
 * @date 07.24.2014
 */
/**
 * store connect object with mongo
 * @var Mongo
 */
global $mongoConnect;
$mongoConnect = null;
class MongoRecord {

    const SORT_DESC = -1;
    const SORT_ASC = 1;

    /**
     * store attributes of model ORM
     * @var array
     */
    private $_attributes;

    /**
     * store error message
     * @var array
     */
    private $_errors = array();

    /**
     *
     * @var Mongo
     */
    private $_connect;

    /**
     * config host
     * 
     * @var array
     */
    private $hostinfo = array(
        'host' => '192.168.4.51',
        'port' => '27017',
        'username' => '',
        'password' => '',
        'db' => 'service'
    );

    /**
     * store object for singeleton design pattern
     * 
     * @var MongoRecord 
     */
    protected static $instance;

    /**
     *
     * @var MongoDB
     */
    protected $_db;

    /**
     *
     * @var MongoCollection
     */
    protected $_collection;
    protected $_id;
    protected $_document;

    /**
     *
     * @var String set collection name
     */
    protected $collectionName;

    /**
     * default timezone
     * @var string
     */
    public $timezone = 'UTC';

    /**
     * array|object that is used for find,...
     * @var mixed
     */
    public $criteria = array();

    /**
     * enable persisten connection or not
     * 
     * @var boolen 
     */
    public $persistentConnection = true;

    /**
     * 
     * @return type
     */
    public function getAttributes() {
        return $this->_attributes;
    }

    /**
     * 
     * @param type $attributes
     * @throws Exception
     */
    public function setAttributes($attributes) {
        $this->mapper($attributes);
    }

    /**
     * empty all attributes
     */
    public function clearAttributes() {
        $this->defineAttributes(array_keys($this->getAttributes()));
    }

    /**
     * need define attributes firstly when run init() method
     * @param array $atts
     */
    protected function defineAttributes($atts) {
        foreach ($atts as $item) {
            $this->_attributes[$item] = null;
        }
    }

    /**
     * get config of service
     * 
     * @return array
     */
    public function getHostinfo() {
        return $this->hostinfo;
    }

    /**
     * get connect
     * 
     * @return MongoClient 
     */
    public function getConnect() {
        return $this->_connect;
    }

    /**
     * 
     * @return MongoDB
     */
    public function getDb() {
        return $this->_db;
    }

    /**
     * 
     * @return MongoCollection
     */
    public function getCollection() {
        return $this->_collection;
    }

    /**
     * 
     * @return MongoCursor
     */
    public function getDocument() {
        return $this->_document;
    }

    /**
     * 
     * @return MongoId
     */
    public function getID() {
        return isset($this->_attributes['_id']) ? $this->_attributes['_id'] : null;
    }

    /**
     * 
     * @param type $id
     * @return \MongoRecord
     */
    protected function setID($id) {
        if (get_class($id) == 'MongoId') {
            $this->_attributes['_id'] = $id;
        } else {
            $this->_attributes['_id'] = new MongoId($id);
        }
        return $this;
    }

    /**
     * 
     * @param type $name
     * @return type
     * @throws Exception
     */
    public function __get($name) {
        if (property_exists($this, $name)) {
            return $this->$name;
        } else if (array_key_exists($name, $this->getAttributes())) {
            return $this->_attributes[$name];
        } else {
            throw new Exception("Property $name does not exist.");
        }
    }

    /**
     * 
     * @param type $name
     * @param type $value
     * @throws Exception
     */
    public function __set($name, $value) {
        if (property_exists($this, $name)) {
            $this->$name = $value;
        } else if (array_key_exists($name, $this->getAttributes())) {
            $this->_attributes[$name] = $value;
        } else {
            throw new Exception("Property $name does not exist.");
        }
    }

    /**
     * contrutor of this class
     * 
     * Create connection with Mongo Class
     * Create database with MongoDb Class
     * @throws Exception
     */
    public function __construct() {
        # if you dont use it in Yii Framework, remove them
        $configYii = \Yii::$app->params;
        if (isset($configYii['mongodb'])) {
            $this->hostinfo = array_merge($this->hostinfo, $configYii['mongodb']);
            $this->timezone = isset($configYii['mongodb']['timezone']) ? $configYii['mongodb']['timezone'] : $this->timezone;
        }
        # setup connect mongo, mongoDB
        $this->_connectMongo();
        # run init method first
        $this->init();
    }

    /**
     * closes connection if option is enabled
     * @return void
     */
    public function __destruct() {
        if ($this->persistentConnection) {
            global $mongoConnect;
            $mongoConnect = NULL;
            $this->_connect = null;
        }
    }

    /**
     * connect mongo
     * @param interger $retry times connect mongo when connect fail
     * @throws Exception cant not connect mongodb
     */
    private function _connectMongo($retry = 3) {
        global $mongoConnect;
        $username = $this->hostinfo['username'];
        $password = $this->hostinfo['password'];
        $dbname = $this->hostinfo['db'];
        $host = $this->hostinfo['host'];

        # check connect object is stored in global variable or not
        # retry connect if connect is false
        # if over three times, throw exception
        if (!is_object($mongoConnect) || get_class($mongoConnect) != 'MongoClient') {
            try {
                $stringConnect = $username != '' && $password != '' ? "{$username}:{$password}@{$host}" : "{$host}";
                $this->_connect = new MongoClient("mongodb://$stringConnect");
                $mongoConnect = $this->_connect;
            } catch (Exception $e) {
                if ($retry > 0) {
                    $this->_connectMongo( --$retry);
                }
            }
        } else {    
            $this->_connect = $mongoConnect;
        }

        if (!$this->_connect || $dbname == '') {
            throw new Exception("could not connect to mongdoDb: " . $host . " or database config is not setted");
        }
        $this->_db = $this->_connect->selectDB($dbname);
    }

    /**
     * Get instance of this class by static method
     * 
     * @return MongoRecord
     */
    public static function instance() {
        if (!self::$instance) {
            $class = __CLASS__;
            self::$instance = new $class;
        }
        return self::$instance;
    }

    /**
     * Initializes the class.
     * If you override this method, make sure you call the parent implementation first.
     * In this method, you must do these things:
     *  + define attribute by $this->defineAttributes method
     *  + set collectionName
     */
    public function init() {
        $this->clearAttributes();
    }

    /**
     * define rule for validation like Yii rule in model. 
     * Just has 1 option: require
     * Example:
     *  return array(
     *      array('propery A,B,C','required)
     * )
     * case validate: 
     *  + required
     *  + int
     *  + float
     *  + email
     *  + min
     *  + max
     * @return array
     */
    public function rules() {
        return array();
    }

    /**
     * validate data 
     * @return boolen
     */
    public function validate() {
        $rules = $this->rules();
        foreach ($rules as $item) {
            $properties = isset($item[0]) ? $item[0] : '';
            $rules = isset($item[1]) ? $item[1] : '';
            $compareValue = isset($item[2]) ? $item[2] : null;
            if ($properties && $rules) {
                $properties = explode(',', $properties);
                foreach ($properties as $prot) {
                    if ($prot) {
                        $value = $this->$prot = !is_array($this->$prot) ? trim($this->$prot) : $this->$prot;
                        $mess = '';
                        switch ($rules) {
                            case 'required':
                                if ($value == '' || !$value) {
                                    $mess = "$prot is required";
                                }
                                break;
                            case 'int':
                                if (!is_numeric($value)) {
                                    $mess = "$prot is not an interger";
                                }
                                break;
                            case 'float':
                                if (!is_float($value)) {
                                    $mess = "$prot is not a float";
                                }
                                break;
                            case 'email':
                                if (filter_var($value, FILTER_VALIDATE_EMAIL) === FALSE) {
                                    $mess = "$prot is not an email";
                                }
                                break;
                            case 'min':
                                if ($value < $compareValue) {
                                    $mess = "$prot must be greater than $compareValue";
                                }
                                break;
                            case 'max':
                                if ($value > $compareValue) {
                                    $mess = "$prot must be less than $compareValue";
                                }
                                break;
                            default:
                                break;
                        }
                        if ($mess) {
                            $this->_errors[$prot] = $mess;
                        }
                    }
                }
            }
        }
        if (count($this->_errors)) {
            return false;
        }
        return true;
    }

    /**
     * get error after validation
     * @return mixed
     */
    public function getErrors($property = '') {
        if ($property != '') {
            return isset($this->_errors[$property]) ? $this->_errors[$property] : array();
        }
        return $this->_errors;
    }

    /**
     * 
     * @param type $name
     */
    public function setCollection($name = '') {
        $s = $name != '' ? $name : $this->collectionName;
        $this->collectionName = $s;
        $this->_collection = $this->_db->$s;
    }

    /**
     * mapper datas to properties of object
     * 
     * @param array $datas
     * @return MongoRecord
     */
    public function mapper($datas) {
        $datas = !is_array($datas) ? array() : $datas;
        $intersect = array_intersect_key($datas, $this->getAttributes());
        $this->_attributes = array_merge($this->getAttributes(), $intersect);
        return $this;
    }

    /**
     * run before save or insert, update data
     * if this method return false, stop insert, update, save data
     * @return boolean
     */
    public function beforeSave() {
        return true;
    }

    /**
     * run some function after save
     * @return void
     */
    public function afterSave() {
        
    }

    /**
     * set condition for criteria base attributes
     * set default value for attributes
     */
    public function setCriteriaBaseAttribute() {
        //prepare criteria
        $attributes = $this->getAttributes();
        foreach ($attributes as $attr => $item) {
            if ($item) {
                $this->criteria[$attr] = is_numeric($item) ? intval($item) : $item;
            }
        }
        return $this->criteria;
    }

    /**
     * If the object is from the database, update the existing database object, otherwise insert this object.
     * Update by primary key if primary key (_id) is setted
     * 
     * @param type $value

     * @param type $options 
     * Options for the save.
      "fsync"
      Boolean, defaults to FALSE. If journaling is enabled, it works exactly like "j". If journaling is not enabled, the write operation blocks until it is synced to database files on disk. If TRUE, an acknowledged insert is implied and this option will override setting "w" to 0.
      Note: If journaling is enabled, users are strongly encouraged to use the "j" option instead of "fsync". Do not use "fsync" and "j" simultaneously, as that will result in an error.
      "j"
      Boolean, defaults to FALSE. Forces the write operation to block until it is synced to the journal on disk. If TRUE, an acknowledged write is implied and this option will override setting "w" to 0.
      Note: If this option is used and journaling is disabled, MongoDB 2.6+ will raise an error and the write will fail; older server versions will simply ignore the option.
      "socketTimeoutMS"
      Integer, defaults to MongoCursor::$timeout. If acknowledged writes are used, this sets how long (in milliseconds) for the client to wait for a database response. If the database does not respond within the timeout period, a MongoCursorTimeoutException will be thrown.
      "w"
      See Write Concerns. The default value for MongoClient is 1.
      "wtimeout"
      Deprecated alias for "wTimeoutMS".
      "wTimeoutMS"
      How long to wait for write concern acknowledgement. The default value for MongoClient is 10000 milliseconds.
      "safe"
      Deprecated. Please use the write concern "w" option.
      "timeout"
      Deprecated alias for "socketTimeoutMS".
     * @return If w was set, returns an array containing the status of the save. Otherwise, returns a boolean representing if the array was not empty (an empty array will not be inserted).
     */
    public function save($value = array(), $options = array()) {
        $this->mapper($value);
        if ($this->beforeSave()) {
            $attrs = $this->getAttributes();
            if ($this->getID() === null) {
                $result = $this->getCollection()->save($attrs, $options);
            } else {
                $result = $this->update(array("_id" => $this->getID()), array('$set' => $attrs));
            }
            $this->afterSave();
            return $result;
        }
        return false;
    }

    /**
     * MongoCollection::insert — Inserts a document into the collection
     * 
     * @param array $value
     * @param type $options 
     * An array of options for the insert operation. Currently available options include:
      "fsync"
      Boolean, defaults to FALSE. If journaling is enabled, it works exactly like "j". If journaling is not enabled, the write operation blocks until it is synced to database files on disk. If TRUE, an acknowledged insert is implied and this option will override setting "w" to 0.
      Note: If journaling is enabled, users are strongly encouraged to use the "j" option instead of "fsync". Do not use "fsync" and "j" simultaneously, as that will result in an error.
      "j"
      Boolean, defaults to FALSE. Forces the write operation to block until it is synced to the journal on disk. If TRUE, an acknowledged write is implied and this option will override setting "w" to 0.
      Note: If this option is used and journaling is disabled, MongoDB 2.6+ will raise an error and the write will fail; older server versions will simply ignore the option.
      "socketTimeoutMS"
      Integer, defaults to MongoCursor::$timeout. If acknowledged writes are used, this sets how long (in milliseconds) for the client to wait for a database response. If the database does not respond within the timeout period, a MongoCursorTimeoutException will be thrown.
      "w"
      See Write Concerns. The default value for MongoClient is 1.
      "wTimeoutMS"
      How long to wait for write concern acknowledgement. The default value for MongoClient is 10000 milliseconds.
      The following options are deprecated and should no longer be used:
      "safe"
      Deprecated. Please use the write concern "w" option.
      "timeout"
      Deprecated alias for "socketTimeoutMS".
      "wtimeout"
      Deprecated alias for "wTimeoutMS".
     * @return
     *  Returns an array containing the status of the insertion if the "w" option is set. Otherwise, returns TRUE if the inserted array is not empty (a MongoException will be thrown if the inserted array is empty).
      If an array is returned, the following keys may be present:
      ok
      This should almost always be 1 (unless last_error itself failed).
      err
      If this field is non-null, an error occurred on the previous operation. If this field is set, it will be a string describing the error that occurred.
      code
      If a database error occurred, the relevant error code will be passed back to the client.
      errmsg
      This field is set if something goes wrong with a database command. It is coupled with ok being 0. For example, if w is set and times out, errmsg will be set to "timed out waiting for slaves" and ok will be 0. If this field is set, it will be a string describing the error that occurred.
      n
      If the last operation was an update, upsert, or a remove, the number of documents affected will be returned. For insert operations, this value is always 0.
      wtimeout
      If the previous option timed out waiting for replication.
      waited
      How long the operation waited before timing out.
      wtime
      If w was set and the operation succeeded, how long it took to replicate to w servers.
      upserted
      If an upsert occurred, this field will contain the new record's _id field. For upserts, either this field or updatedExisting will be present (unless an error occurred).
      updatedExisting
      If an upsert updated an existing element, this field will be true. For upserts, either this field or upserted will be present (unless an error occurred).
     */
    public function insert($value = array(), $options = array()) {
        $this->mapper($value);
        if ($this->beforeSave()) {
            $result = $this->getCollection()->insert($this->getAttributes(), $options);
            $this->afterSave();
            return $result;
        }
        return false;
    }

    /**
     * update mongoDb
     * using MongoCollection::update    
     * 
     * @param type $condition Query criteria for the documents to update.
     * @param type $value The object used to update the matched documents. This may either contain update operators (for modifying specific fields) or be a replacement document.
     * @param type $option An array of options for the update operation. Currently available options include:
      "upsert"
      If no document matches $criteria, a new document will be inserted.
      If a new document would be inserted and $new_object contains atomic modifiers (i.e. $ operators), those operations will be applied to the $criteria parameter to create the new document. If $new_object does not contain atomic modifiers, it will be used as-is for the inserted document. See the upsert examples below for more information.
      "multiple"
      All documents matching $criteria will be updated. MongoCollection::update() has exactly the opposite behavior of MongoCollection::remove(): it updates one document by default, not all matching documents. It is recommended that you always specify whether you want to update multiple documents or a single document, as the database may change its default behavior at some point in the future.
      "fsync"
      Boolean, defaults to FALSE. If journaling is enabled, it works exactly like "j". If journaling is not enabled, the write operation blocks until it is synced to database files on disk. If TRUE, an acknowledged insert is implied and this option will override setting "w" to 0.
      Note: If journaling is enabled, users are strongly encouraged to use the "j" option instead of "fsync". Do not use "fsync" and "j" simultaneously, as that will result in an error.
      "j"
      Boolean, defaults to FALSE. Forces the write operation to block until it is synced to the journal on disk. If TRUE, an acknowledged write is implied and this option will override setting "w" to 0.
      Note: If this option is used and journaling is disabled, MongoDB 2.6+ will raise an error and the write will fail; older server versions will simply ignore the option.
      "socketTimeoutMS"
      Integer, defaults to MongoCursor::$timeout. If acknowledged writes are used, this sets how long (in milliseconds) for the client to wait for a database response. If the database does not respond within the timeout period, a MongoCursorTimeoutException will be thrown.
      "w"
      See Write Concerns. The default value for MongoClient is 1.
      "wTimeoutMS"
      How long to wait for write concern acknowledgement. The default value for MongoClient is 10000 milliseconds.
      The following options are deprecated and should no longer be used:
      "safe"
      Deprecated. Please use the write concern "w" option.
      "timeout"
      Deprecated alias for "socketTimeoutMS".
      "wtimeout"
     * @return returns an array containing the status of the update if the "w" option is set. Otherwise, returns TRUE.
      Fields in the status array are described in the documentation for MongoCollection::insert().
     */
    public function update($condition, $value = array(), $option = array()) {
        if ($this->beforeSave() && is_array($value) && $value) {
            if (!isset($option['multiple'])) {
                $option['multiple'] = true;
            }
            $result = $this->getCollection()->update($condition, array('$set' => $value), $option);
            $this->afterSave();
            return $result;
        }
        return false;
    }

    /**
     * 
     * MongoCollection::batchInsert — Inserts multiple documents into this collection
     * 
     * @param type $datas 
     * @param type $option
     * An array of options for the batch of insert operations. Currently available options include:
      "continueOnError"
      Boolean, defaults to FALSE. If set, the database will not stop processing a bulk insert if one fails (eg due to duplicate IDs). This makes bulk insert behave similarly to a series of single inserts, except that calling MongoDB::lastError() will have an error set if any insert fails, not just the last one. If multiple errors occur, only the most recent will be reported by MongoDB::lastError().
      Note:
      Please note that continueOnError affects errors on the database side only. If you try to insert a document that has errors (for example it contains a key with an empty name), then the document is not even transferred to the database as the driver detects this error and bails out. continueOnError has no effect on errors detected in the documents by the driver.
      "fsync"
      Boolean, defaults to FALSE. Forces the insert to be synced to disk before returning success. If TRUE, an acknowledged insert is implied and will override setting w to 0.
      "j"
      Boolean, defaults to FALSE. Forces the write operation to block until it is synced to the journal on disk. If TRUE, an acknowledged write is implied and this option will override setting "w" to 0.
      Note: If this option is used and journaling is disabled, MongoDB 2.6+ will raise an error and the write will fail; older server versions will simply ignore the option.
      "socketTimeoutMS"
      Integer, defaults to MongoCursor::$timeout. If acknowledged writes are used, this sets how long (in milliseconds) for the client to wait for a database response. If the database does not respond within the timeout period, a MongoCursorTimeoutException will be thrown.
      "w"
      See WriteConcerns. The default value for MongoClient is 1.
      "wTimeoutMS"
      How long to wait for write concern acknowledgement. The default value for MongoClient is 10000 milliseconds.
      The following options are deprecated and should no longer be used:
      "safe"
      Deprecated. Please use the WriteConcern w option.
      "timeout"
      Integer, defaults to MongoCursor::$timeout. If "safe" is set, this sets how long (in milliseconds) for the client to wait for a database response. If the database does not respond within the timeout period, a MongoCursorTimeoutException will be thrown.
      "wtimeout"
      Deprecated alias for "wTimeoutMS".
     */
    public function multiInsert($datas, $option = array()) {
        if ($this->beforeSave() && $datas) {
            $result = $this->getCollection()->batchInsert($datas, $option);
            $this->afterSave();
            return $result;
        }
        return false;
    }

    /**
     * Queries this collection, returning a MongoCursor for the result set
     * 
     * @param type $query The fields for which to search. MongoDB's query language is quite extensive. The PHP driver will in almost all cases pass the query straight through to the server, so reading the MongoDB core docs on » find is a good idea.
      Warning
      Please make sure that for all special query operators (starting with $) you use single quotes so that PHP doesn't try to replace "$exists" with the value of the variable $exists.
     * @param type $fields Fields of the results to return. The array is in the format array('fieldname' => true, 'fieldname2' => true). The _id field is always returned.
     * @return MongoCursor
     */
    public function find($query = array(), $fields = array()) {
        return $this->getCollection()->find($query, $fields);
    }

    /**
     * Update a document and return it
     *
     * @param array $query <p>
     * The query criteria to search for.
     * </p>
     * @param array $update [optional] <p>
     * The update criteria.
     * </p>
     * @param array $fields [optional] <p>
     * Optionally only return these fields.
     * </p>
     * @param array $options [optional] <p>
     * An array of options to apply, such as remove the match document from the
     * DB and return it.
     * <tr valign="top">
     * <td>Option</td>
     * <td>Description</td>
     * </tr>
     * <tr valign="top">
     * <td>sort array</td>
     * <td>
     * Determines which document the operation will modify if the
     * query selects multiple documents. findAndModify will modify the
     * first document in the sort order specified by this argument.
     * </td>
     * </tr>
     * <tr valign="top">
     * <td>remove boolean</td>
     * <td>
     * Optional if update field exists. When <b>TRUE</b>, removes the selected
     * document. The default is <b>FALSE</b>.
     * </td>
     * </tr>
     * <tr valign="top">
     * <td>update array</td>
     * <td>
     * Optional if remove field exists.
     * Performs an update of the selected document.
     * </td>
     * </tr>
     * <tr valign="top">
     * <td>new boolean</td>
     * <td>
     * Optional. When <b>TRUE</b>, returns the modified document rather than the
     * original. The findAndModify method ignores the new option for
     * remove operations. The default is <b>FALSE</b>.
     * </td>
     * </tr>
     * <tr valign="top">
     * <td>upsert boolean</td>
     * <td>
     * Optional. Used in conjunction with the update field. When <b>TRUE</b>, the
     * findAndModify command creates a new document if the query returns
     * no documents. The default is false. In MongoDB 2.2, the
     * findAndModify command returns <b>NULL</b> when upsert is <b>TRUE</b>.
     * </td>
     * </tr>
     * <tr valign="top">
     * <td></td>
     * <td>
     * </td>
     * </tr>
     * </p>
     * @return array the original document, or the modified document when
     * new is set.
     */
    public function findAndModify(array $query, array $update = null, array $fields = null, array $options = null) {
        return $this->getCollection()->findAndModify($query, $update, $fields, $options);
    }

    /**
     * Querys this collection, returning a single element
     * @link http://php.net/manual/en/mongocollection.findone.php
     * @param array $query [optional] <p>
     * The fields for which to search. MongoDB's query language is quite
     * extensive. The PHP driver will in almost all cases pass the query
     * straight through to the server, so reading the MongoDB core docs on
     * find is a good idea.
     * </p>
     * <p>
     * Please make sure that for all special query operaters (starting with
     * $) you use single quotes so that PHP doesn't try to
     * replace "$exists" with the value of the variable
     * $exists.
     * </p>
     * @param array $fields [optional] <p>
     * Fields of the results to return. The array is in the format
     * array('fieldname' => true, 'fieldname2' => true).
     * The _id field is always returned.
     * </p>
     * @return array record matching the search or <b>NULL</b>.
     */
    public function findOne(array $query = array(), array $fields = array()) {
        return $this->getCollection()->findOne($query, $fields);
    }

    /**
     * Counts the number of documents in this collection
     * @link http://php.net/manual/en/mongocollection.count.php
     * @param array $query [optional] <p>
     * Associative array or object with fields to match.
     * </p>
     * @param int $limit [optional] <p>
     * Specifies an upper limit to the number returned.
     * </p>
     * @param int $skip [optional] <p>
     * Specifies a number of results to skip before starting the count.
     * </p>
     * @return int the number of documents matching the query.
     */
    public function count(array $query = array(), $limit = 0, $skip = 0) {
        return $this->getCollection()->count($query, $limit, $skip);
    }

    /**
     * get max value of one field
     * @param String $field
     * @return array();
     */
    public function getMaxValue($field) {
        $query = $this->find(array(), array($field => true))->sort(array($field => -1))->limit(1);
        foreach ($query as $item) {
            return $item[$field];
        }
        return null;
    }

    /**
     * get max min of one field
     * @param String $field
     * @return array();
     */
    public function getMinValue($field) {
        $query = $this->find(array(), array($field => true))->sort(array($field => 1))->limit(1);
        foreach ($query as $item) {
            return $item[$field];
        }
        return null;
    }

    /**
     * delete an document by _id 
     * 
     * @param String $id Value of _id
     * @return boolen
     */
    public function deleteByPk($id) {
        return $this->getCollection()->remove(array('_id' => new MongoID($id)));
    }

    /**
     * find an document by _id
     * 
     * @param String $id Value of _id
     * @return MongoRecord
     */
    public function findByPk($id) {
        $data = $this->findOne(array('_id' => new MongoId($id)));
        if ($data) {
            $this->setID($data['_id']);
            return $this->mapper($data);
        }
        return null;
    }

    /**
     * get current time by default timezone
     * 
     * @return interger
     */
    public function getTimeNow() {
        $date = new \DateTime();
        $date->setTimezone(new \DateTimeZone($this->timezone));
        return $date->getTimestamp();
    }

    /**
     * get last elements of mongoCursor query result
     * 
     * For example if you wanna get last of query $query = $this->find($condition)->sort($sort)->limit($limit). Call $this->getLastElementOfFind($query) to get last element of query $query
     * @param MongoCursor $lastQuery
     * @return array array(first,last)
     */
    public function getFistAndLastElementOfFind($lastQuery) {
        $lastQuery->reset();
        $first = $lastQuery->getNext();
        $lastQuery->reset();
        // get condition
        $getQuery = $lastQuery->info()['query'];
        $queryInfor = isset($getQuery['$query']) ? $getQuery['$query'] : array();
        $sortInfor = isset($getQuery['$orderby']) ? $getQuery['$orderby'] : array();
        $count = $lastQuery->count(true) > 1 ? $lastQuery->count(true) - 1 : 1;
        return array($first, $this->find($queryInfor)->sort($sortInfor)->skip($count)->limit(1)->getNext());
    }

    /**
     * get _id of an item from mongoCursor
     * Ex: $query = $model->find();
     * foreach($query as $item){
     *   get _id of item by this method
     * }
     * 
     * @param array $item
     * @return MongoId
     */
    public static function getIdOfDataGiven($item) {
        return isset($item['_id']) ? $item['_id'] : null;
    }

    /**
     * convert data to array from an query
     * Ex: $query = $model->find();
     * it returns an array data of $query
     * 
     * @param MongoCursor $query
     * @param boolen $mapping 
     *  - true return array objects 
     *  - false return array data
     * 
     * @return array $result
     */
    public function fetchDataArray($query, $mapping = false) {
        $result = array();
        if ($mapping === true) {
            foreach ($query as $item) {
                $result[] = $this->mapper($item);
            }
        } else {
            foreach ($query as $item) {
                $result[] = $item;
            }
        }
        return $result;
    }
    /**
     * Map-Reduce code
     * 
     * @param array $options [optional] <p>
     * This parameter is an associative array of the form
     * array("optionname" => &lt;boolean&gt;, ...). Currently
     * supported options are:
     * <p>"timeout"</p><p>Integer, defaults to MongoCursor::$timeout. If acknowledged writes are used, this sets how long (in milliseconds) for the client to wait for a database response. If the database does not respond within the timeout period, a <b>MongoCursorTimeoutException</b> will be thrown.</p>
     * </p>
     */
    public function mapReduce($options = []) {
        $default = [
            "mapreduce" => $this->collectionName,
            "out" => array("inline" => TRUE)
        ];
        return $this->getDb()->command(array_merge($default, $options));
    }

    /**
     * Excute code
     * 
     * (PECL mongo &gt;=0.9.3)<br/>
     * Runs JavaScript code on the database server.
     * @link http://php.net/manual/en/mongodb.execute.php
     * @param mixed $code <p>
     * <b>MongoCode</b> or string to execute.
     * </p>
     * @param array $args [optional] <p>
     * Arguments to be passed to code.
     * </p>
     * @return array the result of the evaluation.
     */
    public function execute($code, array $args = null) {
        $this->getDb()->execute($code, $args);
    }
}
