<?php

namespace MongoQB;

class Exception extends \Exception {}

class Builder
{
    /**
     * Config file data
     *
     * @var array
     * @access protected
     */
    protected $_configData = array(
        'hostname'  =>  'mongodb://localhost:27017/admin',
        'persist'   =>  true,
        'persist_key'   =>  'mongoqb',
        'replica_set'   =>  false,
        'query_safety'  =>  'safe'
    );

    /**
     * Connection resource.
     *
     * @var mixed
     * @access protected
     */
    protected $_connection = null;

    /**
     * Database handle.
     *
     * @var resource
     * @access protected
     */
    protected $_dbhandle = null;

    /**
     * Database host.
     *
     * @var mixed
     * @access protected
     */
    private $_dsn = '';

    /**
     * Database name.
     *
     * @var strings
     * @access protected
     */
    protected $_dbname = '';

    /**
     * Persist connection.
     *
     * @var boolean
     * @access protected
     */
    protected $_persist = true;

    /**
     * Persist key.
     *
     * @var string
     * @access protected
     */
    protected $_persist_key = 'mongoqb';

    /**
     * Use replica set.
     *
     * @var false|string
     * @access protected
     */
    protected $_replicaSet = false;

    /**
     * Query safety value.
     *
     * @var string
     * @access protected
     */
    protected $_querySafety = 'safe';

    /**
     * Selects array.
     *
     * @var array
     * @access protected
     */
    protected $_selects = array();

    /**
     * Wheres array.
     *
     * Public to make debugging easier.
     *
     * @var array
     * @access public
     */
    public $wheres = array();

    /**
     * Sorts array.
     *
     * @var array
     * @access protected
     */
    protected $_sorts = array();

    /**
     * Updates array.
     *
     * Public to make debugging easier
     *
     * @var array
     * @access public
     */
    public $updates = array();

    /**
     * Results limit.
     *
     * @var integer
     * @access protected
     */
    protected $_limit = 999999;

    /**
     * Query log.
     *
     * @var integer
     * @access protected
     */
    protected $_queryLog = array();

    /**
     * Result offset.
     *
     * @var integer
     * @access protected
     */
    protected $_offset = 0;

    /**
     * Constructor
     *
     * Automatically check if the Mongo PECL extension has been
     *  installed/enabled.
     *
     * @access public
     * @return void
     */
    public function __construct(array $config, $connect = true)
    {
        if ( ! class_exists('\Mongo')) {
            throw new Exception('The MongoDB PECL extension has not been
             installed or enabled');
        }

        $this->setConfig($config, $connect);
    }

    /**
     * Set the configuation.
     *
     * @param mixed $config Array of configuration parameters
     *
     * @access public
     * @return void
     */
    public function setConfig(array $config, $connect = true)
    {
        if (is_array($config)) {
            $this->_configData = array_merge($config, $this->_configData);
        } else {
            throw new Exception('No config variables passed');
        }

        $this->_connectionString();

        if ($connect) {
            $this->_connect();
        }
    }

    /**
     * Switch database.
     *
     * @param string $database Database name
     *
     * @access public
     * @return boolean
     */
    public function switchDb($dsn = '')
    {
        if (empty($dsn)) {
            throw new Exception('To switch MongoDB databases, a
             DSN must be specified');
        }

        try {
            // Regenerate the connection string and reconnect
            $this->_configData['dsn'] = $dsn;
            $this->_connectionString();
            $this->_connect();
        } catch (Exception $Exception) {
            throw new Exception('Unable to switch Mongo Databases: ' .
             $Exception->getMessage());
        }
    }

    /**
    * Drop a database.
    *
    * @param string $database Database name
    *
    * @access public
    * @return boolean
    */
    public function dropDb($database = '')
    {
        if (empty($database)) {

           throw new Exception('Failed to drop MongoDB database because
            name is empty');

        } else {
            try {
                $this->_connection->{$database}->drop();

                return true;
            } catch (Exception $Exception) {
                throw new Exception('Unable to drop Mongo database `' .
                 $database . '`: ' . $Exception->getMessage());
            }

        }
    }

    /**
     * Drop a collection.
     *
     * @param string $database   Database name
     * @param string $collection Collection name
     *
     * @access public
     * @return boolean
     */
    public function dropCollection($database = '', $collection = '')
    {
        if (empty($database)) {
            $this->_show_error('Failed to drop MongoDB collection because
             database name is empty', 500);
        }

        if (empty($collection)) {
            $this->_show_error('Failed to drop MongoDB collection because
             collection name is empty', 500);
        } else {
            try {
                $this->_connection->{$database}->{$collection}->drop();

                return true;
            } catch (Exception $Exception) {
                $this->_show_error('Unable to drop Mongo collection `' .
                 $collection . '`: ' . $Exception->getMessage(), 500);
            }
        }
    }

    /**
     * Set select parameters.
     *
     * Determine which fields to include OR which to exclude during the query
     *  process. Currently, including and excluding at the same time is not
     *  available, so the $includes array will take precedence over the
     *  $excludes array.  If you want to only choose fields to exclude, leave
     *  $includes an empty array().
     *
     * @param array $includes Fields to include in the returned result
     * @param array $excludes Fields to exclude from the returned result
     *
     * @access public
     * @return object
     */
    public function select($includes = array(), $excludes = array())
    {
        if ( ! is_array($includes)) {
            $includes = array();
        }

        if ( ! is_array($excludes)) {
            $excludes = array();
        }

        if ( ! empty($includes)) {
            foreach ($includes as $include) {
                $this->_selects[$include] = 1;
            }
        } else {
            foreach ($excludes as $exclude) {
                $this->_selects[$exclude] = 0;
            }
        }

        return $this;
    }

    /**
     * Set where paramaters
     *
     * Get the documents based on these search parameters.  The $wheres array
     *  should be an associative array with the field as the key and the value
     *  as the search criteria.
     *
     * @param array|string $wheres Array of where conditions. If string, $value
     *  must be set
     * @param mixed $value Value of $wheres if $wheres is a string
     *
     * @access public
     * @return object
     */
    public function where($wheres = array(), $value = null)
    {
        if (is_array($wheres)) {
            foreach ($wheres as $where => $value) {
                $this->wheres[$where] = $value;
            }
        } else {
            $this->wheres[$wheres] = $value;
        }

        return $this;
    }

    /**
     * or_where.
     *
     * Get the documents where the value of a $field may be something else
     *
     * @param array $wheres Array of where conditions
     *
     * @access public
     * @return object
     */
    public function orWhere($wheres = array())
    {
        if (count($wheres) > 0) {
            if ( ! isset($this->wheres['$or']) OR !
             is_array($this->wheres['$or'])) {
                $this->wheres['$or'] = array();
            }

            foreach ($wheres as $where => $value) {
                $this->wheres['$or'][] = array($where => $value);
            }
        }

        return $this;
    }

    /**
     * Where in array.
     *
     * Get the documents where the value of a $field is in a given $in array().
     *
     * @param string $field     Name of the field
     * @param array  $in_values Array of values that $field could be
     *
     * @access public
     * @return object
     */
    public function whereIn($field = '', $in_values = array())
    {
        $this->_whereInit($field);
        $this->wheres[$field]['$in'] = $in_values;

        return $this;
    }

    /**
     * Where all are in array.
     *
     * Get the documents where the value of a $field is in all of a given $in
     *  array().
     *
     * @param string $field     Name of the field
     * @param array  $in_values Array of values that $field must be
     *
     * @access public
     * @return object
     */
    public function whereInAll($field = '', $in_values = array())
    {
        $this->_whereInit($field);
        $this->wheres[$field]['$all'] = $in_values;

        return $this;
    }

    /**
     * Where not in
     *
     * Get the documents where the value of a $field is not in a given $in
     *  array().
     *
     * @param string $field     Name of the field
     * @param array  $in_values Array of values that $field isnt
     *
     * @access public
     * @return object
     */
    public function whereNotIn($field = '', $in_values = array())
    {
        $this->_whereInit($field);
        $this->wheres[$field]['$nin'] = $in_values;

        return $this;
    }

    /**
     * Where greater than
     *
     * Get the documents where the value of a $field is greater than $value.
     *
     * @param string $field Name of the field
     * @param mixed  $value Value that $field is greater than
     *
     * @access public
     * @return object
     */
    public function whereGt($field = '', $value = null)
    {
        $this->_whereInit($field);
        $this->wheres[$field]['$gt'] = $value;

        return $this;
    }

    /**
     * Where greater than or equal to
     *
     * Get the documents where the value of a $field is greater than or equal to
     *  $value.
     *
     * @param string $field Name of the field
     * @param mixed  $value Value that $field is greater than or equal to
     *
     * @access public
     * @return object
     */
    public function whereGte($field = '', $value = null)
    {
        $this->_whereInit($field);
        $this->wheres[$field]['$gte'] = $value;

        return $this;
    }

    /**
     * Where less than.
     *
     * Get the documents where the value of a $field is less than $x
     *
     * @param string $field Name of the field
     * @param mixed  $value Value that $field is less than
     *
     * @access public
     * @return object
     */
    public function whereLt($field = '', $value = null)
    {
        $this->_whereInit($field);
        $this->wheres[$field]['$lt'] = $value;

        return $this;
    }

    /**
     * Where less than or equal to
     *
     * Get the documents where the value of a $field is less than or equal to $x
     *
     * @param string $field Name of the field
     * @param mixed  $value Value that $field is less than or equal to
     *
     * @access public
     * @return object
     */
    public function whereLte($field = '', $value = null)
    {
        $this->_whereInit($field);
        $this->wheres[$field]['$lte'] = $value;

        return $this;
    }

    /**
     * Where between two values
     *
     * Get the documents where the value of a $field is between $x and $y
     *
     * @param string $field   Name of the field
     * @param int    $value_x Value that $field is greater than or equal to
     * @param int    $value_y Value that $field is less than or equal to
     *
     * @access public
     * @return object
     */
    public function whereBetween($field = '', $value_x = 0, $value_y = 0)
    {
        $this->_whereInit($field);
        $this->wheres[$field]['$gte'] = $value_x;
        $this->wheres[$field]['$lte'] = $value_y;

        return $this;
    }

    /**
     * Where between two values but not equal to
     *
     * Get the documents where the value of a $field is between but not equal to
     *  $x and $y
     *
     * @param string $field   Name of the field
     * @param int    $value_x Value that $field is greater than or equal to
     * @param int    $value_y Value that $field is less than or equal to
     *
     * @access public
     * @return object
     */
    public function whereBetweenNe($field = '', $value_x, $value_y)
    {
        $this->_whereInit($field);
        $this->wheres[$field]['$gt'] = $value_x;
        $this->wheres[$field]['$lt'] = $value_y;

        return $this;
    }

    /**
     * Where not equal to
     *
     * Get the documents where the value of a $field is not equal to $x
     *
     * @param string $field Name of the field
     * @param mixed  $value Value that $field is not equal to
     *
     * @access public
     * @return object
     */
    public function whereNe($field = '', $value)
    {
        $this->_whereInit($field);
        $this->wheres[$field]['$ne'] = $value;

        return $this;
    }

    /**
     * Where near
     *
     * Get the documents nearest to an array of coordinates (your collection
     *  must have a geospatial index)
     *
     * @param string  $field     Name of the field
     * @param array   $coords    Array of coordinates
     * @param integer $distance  Value of the maximum distance to search
     * @param boolean $spherical Treat the Earth as spherical instead of flat
     *  (useful when searching over large distances)
     *
     * @access public
     * @return object
     */
    public function whereNear($field = '', $coords = array(), $distance = null,
     $spherical = false)
    {
        $this->_whereInit($field);

        if ($spherical) {
            $this->wheres[$field]['$nearSphere'] = $coords;
        } else {
            $this->wheres[$field]['$near'] = $coords;
        }

        if ($distance !== null) {
            $this->wheres[$field]['$maxDistance'] = $distance;
        }

        return $this;
    }

    /**
     * Where like
     *
     * Get the documents where the (string) value of a $field is like a value.
     *  The defaults
     * allow for a case-insensitive search.
     *
     * @param string $field The field
     * @param string $value The value to match against
     * @param string $flags Allows for the typical regular
     *  expression flags:<br>i = case insensitive<br>m = multiline<br>x = can
     *  contain comments<br>l = locale<br>s = dotall, "." matches everything,
     *  including newlines<br>u = match unicode
     * @param boolean $enable_start_wildcard If set to anything other than true,
     *  a starting line character "^" will be prepended to the search value,
     *  representing only searching for a value at the start of a new line.
     * @param boolean $enable_end_wildcard If set to anything other than true,
     *  an ending line character "$" will be appended to the search value,
     *  representing only searching for a value at the end of a line.
     *
     * @access public
     * @return object
     */
    public function whereLike($field = '', $value = '', $flags = 'i',
     $enable_start_wildcard = true, $enable_end_wildcard = true)
    {
        $field = (string) trim($field);
        $this->_whereInit($field);
        $value = (string) trim($value);
        $value = quotemeta($value);

        if ($enable_start_wildcard !== true) {
            $value = '^' . $value;
        }

        if ($enable_end_wildcard !== true) {
            $value .= '$';
        }

        $regex = '/' . $value . '/' . $flags;
        $this->wheres[$field] = new \MongoRegex($regex);

        return $this;
    }

    /**
     * Order results by
     *
     * Sort the documents based on the parameters passed. To set values to
     *  descending order, you must pass values of either -1, false, 'desc', or
     *  'DESC', else they will be set to 1 (ASC).
     *
     * @param array $fields Array of fields with their sort type (asc or desc)
     *
     * @access public
     * @return object
     */
    public function orderBy($fields = array())
    {
        foreach ($fields as $field => $order) {
            if ($order === -1 OR $order === false OR strtolower($order) ===
             'desc') {
                $this->_sorts[$field] = -1;
            } else {
                $this->_sorts[$field] = 1;
            }
        }

        return $this;
    }

    /**
     * Limit the number of results
     *
     * Limit the result set to $limit number of documents
     *
     * @param int $limit The maximum number of documents that will be returned
     *
     * @access public
     * @return object
     */
    public function limit($limit = 99999)
    {
        if ($limit !== null AND is_numeric($limit) AND $limit >= 1) {
            $this->_limit = (int) $limit;
        }

        return $this;
    }

    /**
     * Offset results
     *
     * Offset the result set to skip $x number of documents
     *
     * @param int $offset The number of documents to offset the search by
     *
     * @access public
     * @return object
     */
    public function offset($offset = 0)
    {
        if ($offset !== null AND is_numeric($offset) AND $offset >= 1) {
            $this->_offset = (int) $offset;
        }

        return $this;
    }

    /**
    * Get where.
    *
    * Get the documents based upon the passed parameters
    *
    * @param string $collection Name of the collection
    * @param array  $where      Array of where conditions
    *
    * @access public
    * @return array
    */
    public function getWhere($collection = '', $where = array())
    {
        return $this->where($where)->get($collection);
    }

    /**
    * Get results
    *
    * Return the found documents
    *
    * @param string $collection    Name of the collection
    * @param bool   $return_cursor Return the native document cursor
    *
    * @access public
    * @return array
    */
    public function get($collection = '', $return_cursor = false)
    {
        if (empty($collection)) {
            throw new Exception('In order to retrieve documents from
             MongoDB, a collection name must be passed');
        }

        $cursor = $this->_dbhandle
                            ->{$collection}
                            ->find($this->wheres, $this->_selects)
                            ->limit($this->_limit)
                            ->skip($this->_offset)
                            ->sort($this->_sorts);

        // Clear
        $this->_clear($collection, 'get');

        // Return the raw cursor if wanted
        if ($return_cursor === true) {
            return $cursor;
        }

        $documents = array();

        while ($cursor->hasNext()) {
            try {
                $documents[] = $cursor->getNext();
            } catch (MongoCursorException $Exception) {
                throw new Exception($Exception->getMessage());
            }
        }

        return $documents;
    }

    /**
    * Count.
    *
    * Count the number of found documents
    *
    * @param string $collection Name of the collection
    *
    * @access public
    * @return int
    */
    public function count($collection = '')
    {
        if (empty($collection)) {
            throw new Exception('In order to retrieve a count of
             documents from MongoDB, a collection name must be passed');
        }

        $count = $this->_dbhandle
                        ->{$collection}
                        ->find($this->wheres)
                        ->limit($this->_limit)
                        ->skip($this->_offset)
                        ->count();

        $this->_clear($collection, 'count');

        return $count;
    }

    /**
     * Insert.
     *
     * Insert a new document
     *
     * @param string $collection Name of the collection
     * @param array  $insert     The document to be inserted
     * @param array  $options    Array of options
     *
     * @access public
     * @return boolean
     */
    public function insert($collection = '', $insert = array(),
     $options = array())
    {
        if (empty($collection)) {
            throw new Exception('No Mongo collection selected to insert
             into');
        }

        if (count($insert) === 0 OR ! is_array($insert)) {
            throw new Exception('Nothing to insert into Mongo collection
             or insert is not an array');
        }

        $options = array_merge(
                    array(
                        $this->_query_safety => true
                    ),
                    $options
                );

        try {
            $this->_dbhandle
                ->{$collection}
                ->insert($insert, $options);

            if (isset($insert['_id'])) {
                return $insert['_id'];
            } else {
                return false;
            }
        } catch (MongoCursorException $Exception) {
            throw new Exception('Insert of data into MongoDB failed: ' .
             $Exception->getMessage());
        }
    }

    /**
     * Insert.
     *
     * Insert a new document
     *
     * @param string $collection Name of the collection
     * @param array  $insert     The document to be inserted
     * @param array  $options    Array of options
     *
     * @access public
     * @return boolean
     */
    public function batchInsert($collection = '', $insert = array(),
     $options = array())
    {
        if (empty($collection)) {
            throw new Exception('No Mongo collection selected to insert
             into');
        }

        if (count($insert) === 0 || ! is_array($insert)) {
            throw new Exception('Nothing to insert into Mongo collection
             or insert is not an array');
        }

        $options = array_merge(
                    array(
                        $this->_query_safety => true
                    ),
                    $options
                );

        try {
            return $this->_dbhandle
                            ->{$collection}
                            ->batchInsert($insert, $options);
        } catch (MongoCursorException $Exception) {
            throw new Exception('Insert of data into MongoDB failed: ' .
             $Exception->getMessage());
        }
    }

    /**
     * Update a document
     *
     * @param string $collection Name of the collection
     * @param array  $options    Array of update options
     *
     * @access public
     * @return boolean
     */
    public function update($collection = '', $options = array())
    {
        if (empty($collection)) {
            throw new Exception('No Mongo collection selected to
             update');
        }

        if (count($this->updates) === 0) {
            throw new Exception('Nothing to update in Mongo collection or
             update is not an array');
        }

        try {
            $options = array_merge(array($this->_query_safety => true,
             'multiple' => false), $options);
            $result = $this->_dbhandle->{$collection}->update($this->wheres,
             $this->updates, $options);
            $this->_clear($collection, 'update');

            if ($result['updatedExisting'] > 0) {
                return $result['updatedExisting'];
            }

            return false;
        } catch (MongoCursorException $Exception) {
            throw new Exception('Update of data into MongoDB failed: ' .
             $Exception->getMessage());
        }
    }

    /**
     * Update all documents.
     *
     * Updates a document
     *
     * @param string $collection Name of the collection
     * @param array  $options    Array of update options
     *
     * @access public
     * @return boolean
     */
    public function updateAll($collection = '', $options = array())
    {
        if (empty($collection)) {
            throw new Exception('No Mongo collection selected to
             update');
        }

        if (count($this->updates) === 0) {
            throw new Exception('Nothing to update in Mongo collection or
             update is not an array');
        }

        try {
            $options = array_merge(array($this->_query_safety => true,
             'multiple' => true), $options);
            $result = $this->_dbhandle->{$collection}->update($this->wheres,
             $this->updates, $options);
            $this->_clear($collection, 'update_all');

            if ($result['updatedExisting'] > 0) {
                return $result['updatedExisting'];
            }

            return false;
        } catch (MongoCursorException $Exception) {
            throw new Exception('Update of data into MongoDB failed: ' .
             $Exception->getMessage());
        }
    }

    /**
     * Inc.
     *
     * Increments the value of a field
     *
     * @param array|string $fields Array of field names (or a single string
     *  field name) to be incremented
     * @param int $value Value that the field(s) should be incremented
     *  by
     *
     * @access public
     * @return object
     */
    public function inc($fields = array(), $value = 0)
    {
        $this->_updateInit§('$inc');

        if (is_string($fields)) {
            $this->updates['$inc'][$fields] = $value;
        } elseif (is_array($fields)) {
            foreach ($fields as $field => $value) {
                $this->updates['$inc'][$field] = $value;
            }
        }

        return $this;
    }

    /**
     * Dec.
     *
     * Decrements the value of a field
     *
     * @param array|string $fields Array of field names (or a single string
     *  field name) to be decremented
     * @param int $value Value that the field(s) should be decremented
     *  by
     *
     * @access public
     * @return object
     */
    public function dec($fields = array(), $value = 0)
    {
        $this->_updateInit('$inc');

        if (is_string($fields)) {
            $this->updates['$inc'][$fields] = $value;
        } elseif (is_array($fields)) {
            foreach ($fields as $field => $value) {
                $this->updates['$inc'][$field] = $value;
            }
        }

        return $this;
    }

    /**
     * Set.
     *
     * Sets a field to a value
     *
     * @param array|string $fields Array of field names (or a single string
     *  field name)
     * @param mixed $value Value that the field(s) should be set to
     *
     * @access public
     * @return object
     */
    public function set($fields, $value = null)
    {
        $this->_updateInit('$set');

        if (is_string($fields)) {
            $this->updates['$set'][$fields] = $value;
        } elseif (is_array($fields)) {
            foreach ($fields as $field => $value) {
                $this->updates['$set'][$field] = $value;
            }
        }

        return $this;
    }

    /**
     * Unset.
     *
     * Unsets a field (or fields)
     *
     * @param array|string $fields Array of field names (or a single string
     *  field name) to be unset
     *
     * @access public
     * @return object
     */
    public function unsetField($fields)
    {
        $this->_updateInit('$unset');

        if (is_string($fields)) {
            $this->updates['$unset'][$fields] = 1;
        } elseif (is_array($fields)) {
            foreach ($fields as $field) {
                $this->updates['$unset'][$field] = 1;
            }
        }

        return $this;
    }

    /**
     * Add to set.
     *
     * Adds value to the array only if its not in the array already
     *
     * @param string       $field  Name of the field
     * @param string|array $values Value of the field(s)
     *
     * @access public
     * @return object
     */
    public function addToSet($field, $values)
    {
        $this->_updateInit('$addToSet');

        if (is_string($values)) {
            $this->updates['$addToSet'][$field] = $values;
        } elseif (is_array($values)) {
            $this->updates['$addToSet'][$field] = array('$each' => $values);
        }

        return $this;
    }

    /**
     * Push.
     *
     * Pushes values into a field (field must be an array)
     *
     * @param array|string $fields Array of field names (or a single string
     *  field name)
     * @param mixed $value Value of the field(s) to be pushed into an
     *  array or object
     *
     * @access public
     * @return object
     */
    public function push($fields, $value = array())
    {
        $this->_updateInit('$push');

        if (is_string($fields)) {
            $this->updates['$push'][$fields] = $value;
        } elseif (is_array($fields)) {
            foreach ($fields as $field => $value) {
                $this->updates['$push'][$field] = $value;
            }
        }

        return $this;
    }

    /**
     * Pop.
     *
     * Pops the last value from a field (field must be an array)
     *
     * @param string $field Name of the field to be popped
     *
     * @access public
     * @return object
     */
    public function pop($field)
    {
        $this->_updateInit('$pop');

        if (is_string($field)) {
            $this->updates['$pop'][$field] = -1;
        } elseif (is_array($field)) {
            foreach ($field as $pop_field) {
                $this->updates['$pop'][$pop_field] = -1;
            }
        }

        return $this;
    }

    /**
     * Pull.
     *
     * Removes by an array by the value of a field
     *
     * @param string $field Name of the field
     * @param array  $value Array of identifiers to remove $field
     *
     * @access public
     * @return object
     */
    public function pull($field = '', $value = array())
    {
        $this->_updateInit('$pull');

        $this->updates['$pull'] = array($field => $value);

        return $this;
    }

    /**
     * Rename field.
     *
     * Renames a field
     *
     * @param string $old_name Name of the field to be renamed
     * @param string $new_name New name for $old_name
     *
     * @access public
     * @return object
     */
    public function renameField($old_name, $new_name)
    {
        $this->_updateInit('$rename');
        $this->updates['$rename'][] = array($old_name => $new_name);

        return $this;
    }

    /**
     * Delete.
     *
     * delete document from the passed collection based upon certain criteria
     *
     * @param string $collection Name of the collection
     *
     * @access public
     * @return object
     */
    public function delete($collection = '')
    {
        if (empty($collection)) {
            throw new Exception('No Mongo collection selected to delete
             from');
        }

        try {
            $this->_dbhandle->{$collection}->remove($this->wheres,
             array($this->_query_safety => true, 'justOne' => true));
            $this->_clear($collection, 'delete');

            return true;
        } catch (MongoCursorException $Exception) {
            throw new Exception('Delete of data into MongoDB failed: ' .
             $Exception->getMessage());
        }
    }

    /**
     * Delete all.
     *
     * Delete all documents from the passed collection based upon certain
     *  criteria
     *
     * @param string $collection Name of the collection
     *
     * @access public
     * @return object
     */
    public function deleteAll($collection = '')
    {
        if (empty($collection)) {
            throw new Exception('No Mongo collection selected to delete
             from');
        }

        if (isset($this->wheres['_id']) AND
         ! ($this->wheres['_id'] instanceof MongoId)) {
            $this->wheres['_id'] = new \MongoId($this->wheres['_id']);
        }

        try {
            $this->_dbhandle->{$collection}->remove($this->wheres,
             array($this->_query_safety => true, 'justOne' => false));
            $this->_clear($collection, 'delete_all');

            return true;
        } catch (MongoCursorException $Exception) {
            throw new Exception('Delete of data into MongoDB failed: ' .
             $Exception->getMessage());
        }
    }

    /**
     * Command.
     *
     * Runs a MongoDB command (such as GeoNear). See the MongoDB documentation
     *  for more usage scenarios - http://dochub.mongodb.org/core/commands
     *
     * @param array $query The command query
     *
     * @access public
     * @return object
     */
    public function command($query = array())
    {
        try {
            $execute = $this->_dbhandle->command($query);

            return $execute;
        } catch (MongoCursorException $Exception) {
            throw new Exception('MongoDB command failed to execute: ' .
             $Exception->getMessage());
        }
    }

    /**
     * Add indexes.
     *
     * Ensure an index of the keys in a collection with optional parameters.
     *  To set values to descending order, you must pass values of either -1,
     *  false, 'desc', or 'DESC', else they will be set to 1 (ASC).
     *
     * @param string $collection Name of the collection
     * @param array  $fields     Array of fields to be indexed. Key should be
     *  the field name, value should be index type
     * @param array $options Array of options
     *
     * @access public
     * @return object
     */
    public function addIndex($collection = '', $fields = array(),
     $options = array())
    {
        if (empty($collection)) {
            throw new Exception('No Mongo collection specified to add
             index to');
        }

        if (empty($fields) OR ! is_array($fields)) {
            throw new Exception('Index could not be created to MongoDB
             Collection because no keys were specified');
        }

        foreach ($fields as $field => $value) {
            if($value === -1 OR $value === false OR
             strtolower($value) === 'desc') {
                $keys[$field] = -1;
            } else {
                $keys[$field] = 1;
            }
        }

        if ($this->_dbhandle->{$collection}->ensureIndex($fields, $options)
         === true) {
            $this->_clear($collection, 'add_index');

            return $this;
        } else {
            throw new Exception('An error occurred when trying to add an
             index to MongoDB Collection');
        }
    }

    /**
     * Remove indexes.
     *
     * Remove an index of the keys in a collection. To set values to descending
     *  order, you must pass values of either -1, false, 'desc', or 'DESC', else
     *  they will be set to 1 (ASC).
     *
     * @param string $collection Name of the collection
     * @param array  $keys       Array of index keys to be removed. Array key
     *  should be the field name, the value should be -1
     *
     * @access public
     * @return object
     */
    public function removeIndex($collection = '', $keys = array())
    {
        if (empty($collection)) {
            throw new Exception('No Mongo collection specified to remove
             index from');
        }

        if (empty($keys) OR ! is_array($keys)) {
            throw new Exception('Index could not be removed from MongoDB
             Collection because no keys were specified');
        }

        if ($this->_dbhandle->{$collection}->deleteIndex($keys, $options)
         === true) {
            $this->_clear($collection, 'remove_index');

            return $this;
        } else {
            throw new Exception('An error occurred when trying to remove
             an index from MongoDB Collection');
        }
    }

    /**
     * Remove all indexes
     *
     * Remove all indexes from a collection.
     *
     * @param string $collection Name of the collection
     *
     * @access public
     * @return array|object
     */
    public function removeAllIndexes($collection = '')
    {
        if (empty($collection)) {
            throw new Exception('No Mongo collection specified to remove
             all indexes from');
        }
        $this->_dbhandle->{$collection}->deleteIndexes();
        $this->_clear($collection, 'remove_all_indexes');

        return $this;
    }

    /**
     * List indexes.
     *
     * Lists all indexes in a collection.
     *
     * @param string $collection Name of the collection
     *
     * @access public
     * @return array|object
     */
    public function listIndexes($collection = '')
    {
        if (empty($collection)) {
            throw new Exception('No Mongo collection specified to remove
             all indexes from');
        }

        return $this->_dbhandle->{$collection}->getIndexInfo();
    }

    /**
     * Mongo Date.
     *
     * Create new MongoDate object from current time or pass timestamp to create
     *  mongodate.
     *
     * @param int|null $timestamp A unix timestamp (or null to return a
     *  MongoDate relative to time()
     *
     * @access public
     * @return array|object
     */
    public function date($timestamp = null)
    {
        if ($timestamp === null) {
            return new \MongoDate();
        }

        return new \MongoDate($timestamp);
    }

    /**
     * Get database reference
     *
     * Get mongo object from database reference using MongoDBRef
     *
     * @param object $object A dbref object
     *
     * @access public
     * @return array|object
     */
    public function getDbref($object)
    {
        if (empty($object) || ! isset($object)) {
            throw new Exception('To use MongoDBRef::get() ala get_dbref()
             you must pass a valid reference object');
        }

            return MongoDBRef::get($this->_dbhandle, $object);
    }

    /**
     * Create database reference.
     *
     * Create mongo dbref object to store later
     *
     * @param string $collection Collection name
     * @param string $field      Field name
     * @param string $db_name    Database name
     *
     * @access public
     * @return array|object
     */
    public function createDbref($collection = '', $field = '', $db_name = '')
    {
        if (empty($collection)) {
            throw new Exception('In order to retrieve documents from
             MongoDB, a collection name must be passed');
        }

        if (empty($field) || ! isset($field)) {
            throw new Exception('To use MongoDBRef::create() ala
             create_dbref() you must pass a valid field id of the object which
              to link');
        }

        $database = ($db_name !== '') ? $db_name : $this->_dbhandle;

        return MongoDBRef::create($collection, $field, $database);
    }

    /**
     * last_query.
     *
     * Return the last query
     *
     * @access public
     * @return array
     */
    public function lastQuery()
    {
        return $this->_queryLog;
    }

    /**
     * Connect to MongoDB
     *
     * Establish a connection to MongoDB using the connection string generated
     *  in the connection_string() method.  If 'mongo_persist_key' was set to
     *  true in the config file, establish a persistent connection.  We allow
     *  for only the 'persist' option to be set because we want to establish a
     *  connection immediately.
     *
     * @return object
     * @access private
     */
    private function _connect()
    {
        $options = array();

        if ($this->_persist === true) {
            $options['persist'] = $this->_persist_key;
        }

        if ($this->_replica_set !== false) {
            $options['replicaSet'] = $this->_replica_set;
        }

        try {
            $this->_connection = new \Mongo($this->_dsn, $options);
            $this->_dbhandle = $this->_connection->{$this->_dbname};
            return $this;
        } catch (MongoConnectionException $Exception) {
                throw new Exception('Unable to connect to MongoDB: ' .
                 $Exception->getMessage());
        }
    }

    /**
     * Build connectiong string.
     *
     * @access private
     * @return void
     */
    private function _connectionString()
    {
        $this->_dsn = trim($this->_configData['dsn']);
        $this->_persist = $this->_configData['persist'];
        $this->_persist_key = trim($this->_configData['persist_key']);
        $this->_replica_set = $this->_configData['replica_set'];
        $this->_query_safety = trim($this->_configData['query_safety']);
        $dbname = explode('/', $this->_dsn);
        $this->_dbname = end($dbname);

        if (empty($this->_dsn)) {
            throw new Exception('The DSN is empty');
        }

        if (empty($this->_dbname)) {
            throw new Exception('The database name must be set in the DSN string');
        }
    }

    /**
     * Reset the class variables to default settings.
     *
     * @access private
     * @return void
     */
    private function _clear($collection, $action)
    {
        $this->_queryLog = array(
            'collection'    => $collection,
            'action'        => $action,
            'wheres'        => $this->wheres,
            'updates'       => $this->updates,
            'selects'       => $this->_selects,
            'limit'         => $this->_limit,
            'offset'        => $this->_offset,
            'sorts'         => $this->_sorts
        );

        $this->_selects = array();
        $this->updates  = array();
        $this->wheres   = array();
        $this->_limit   = 999999;
        $this->_offset  = 0;
        $this->_sorts   = array();
    }

    /**
     * Where initializer.
     *
     * Prepares parameters for insertion in $wheres array().
     *
     * @param string $field Field name
     *
     * @access private
     * @return void
     */
    private function _whereInit($field)
    {
        if ( ! isset($this->wheres[$field])) {
            $this->wheres[$field] = array();
        }
    }

    /**
     * Update initializer.
     *
     * Prepares parameters for insertion in $updates array().
     *
     * @param string $field Field name
     *
     * @access private
     * @return void
     */
    private function _updateInit($field = '')
    {
        if ( ! isset($this->updates[$field])) {
            $this->updates[$field] = array();
        }
    }
}
