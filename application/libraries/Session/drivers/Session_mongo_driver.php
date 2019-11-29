<?php defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * CodeIgniter Session MongoDB Driver
 *
 * @package CodeIgniter
 * @subpackage  Libraries
 * @category    Sessions
 * @author  Intekhab Rizvi
 * @link    https://codeigniter.com/user_guide/libraries/sessions.html
 */
class CI_Session_mongo_driver extends CI_Session_driver implements SessionHandlerInterface {

    /**
     * DB object
     *
     * @var object
     */
    protected $_db;

    /**
     * Name of MongoDB database & collection holding all session data
     * @var string
     */
    protected $db_name;
    protected $collection;

    /**
     * Row exists flag
     *
     * @var bool
     */
    protected $_row_exists = FALSE;

    // ------------------------------------------------------------------------

    /**
     * Class constructor
     *
     * @param   array   $params Configuration parameters
     * @return  void
     */
    public function __construct(&$params)
    {

        parent::__construct($params);

        if ( ! isset($this->_config['save_path']))
        {
            throw new Exception('Missing sess_save_path setting in application/config.php file.');
            
        }

        $dns = explode("|",$this->_config['save_path']);

        if ( ! is_array($dns) || count($dns) != 3)
        {
            throw new Exception('sess_save_path config setting has invalid value.');
            
        }

        try
        {
            $this->_db = new MongoDB\Driver\Manager($dns[0]);
            $this->db_name = $dns[1];
            $this->collection = $dns[2];
        }
        catch (MongoDB\Driver\Exception\Exception $e)
        {
            throw new Exception("Unable to connect to MongoDB Server: {$e->getMessage()}");
        }
    }

    // ------------------------------------------------------------------------

    /**
     * Open
     *
     * Initializes the database connection
     *
     * @param   string  $save_path  Table name
     * @param   string  $name       Session cookie name, unused
     * @return  bool
     */
    public function open($save_path, $name)
    {
        if (empty($this->_db))
        {
            return $this->_fail();
        }

        return $this->_success;
    }

    // ------------------------------------------------------------------------

    /**
     * Read
     *
     * Reads session data and acquires a lock
     *
     * @param   string  $session_id Session ID
     * @return  string  Serialized session data
     */
    public function read($session_id)
    {
        // Needed by write() to detect session_regenerate_id() calls
            $this->_session_id = $session_id;

            $where['_id'] = $this->_session_id;

            if ($this->_config['match_ip'])
            {
                $where['ip_address'] = $_SERVER['REMOTE_ADDR'];
            }

            $options = array();
            $options['projection'] = array('data'=>1, '_id'=>0);
            $options['limit'] = 1;

            $query = new MongoDB\Driver\Query($where, $options);
            $cursor = $this->_db->executeQuery($this->db_name.".".$this->collection, $query);
            $result = $cursor->toArray();
            
            if ( count($result) === 0)
            {
                // PHP7 will reuse the same SessionHandler object after
                // ID regeneration, so we need to explicitly set this to
                // FALSE instead of relying on the default ...
                $this->_row_exists = FALSE;
                $this->_fingerprint = md5('');
                return '';
            }

            $this->_fingerprint = md5($result[0]->data);
            $this->_row_exists = TRUE;
            unset($where, $options, $query, $cursor);
            return $result[0]->data;
    }

    // ------------------------------------------------------------------------

    /**
     * Write
     *
     * Writes (create / update) session data
     *
     * @param   string  $session_id Session ID
     * @param   string  $session_data   Serialized session data
     * @return  bool
     */
    public function write($session_id, $session_data)
    {

        // Was the ID regenerated?
        if (isset($this->_session_id) && $session_id !== $this->_session_id)
        {
            $this->_row_exists = FALSE;
            $this->_session_id = $session_id;
        }

        $bulk = new MongoDB\Driver\BulkWrite();
        $writeConcern = new MongoDB\Driver\WriteConcern(MongoDB\Driver\WriteConcern::MAJORITY, 100);

        if ($this->_row_exists === FALSE)
        {
            $insert_data = array(
                '_id' => $session_id,
                'ip_address' => $_SERVER['REMOTE_ADDR'],
                'timestamp' => new MongoDB\BSON\UTCDateTime(),
                'data' => $session_data
            );

            $bulk->insert($insert_data);
            
            $write = $this->_db->executeBulkWrite($this->db_name.".".$this->collection, $bulk, $writeConcern);

            if ($write->getInsertedCount() == 1)
            {
                $this->_fingerprint = md5($session_data);
                $this->_row_exists = TRUE;
                return $this->_success;
            }

            return $this->_fail();
        }

        $where['_id'] = $session_id;
        if ($this->_config['match_ip'])
        {
            $where['ip_address'] = $_SERVER['REMOTE_ADDR'];
        }

        $update_data = array('timestamp' => new MongoDB\BSON\UTCDateTime());
        if ($this->_fingerprint !== md5($session_data))
        {
            $update_data['data'] = $session_data;
        }


        $bulk->update($where, array('$set'=>$update_data), array('multi' => false));
        $write = $this->_db->executeBulkWrite($this->db_name.".".$this->collection, $bulk, $writeConcern);

        if ($write->getModifiedCount() == 1)
        {
            $this->_fingerprint = md5($session_data);
            return $this->_success;
        }

        return $this->_fail();
    }

    // ------------------------------------------------------------------------

    /**
     * Close
     *
     * Releases locks
     *
     * @return  bool
     */
    public function close()
    {
        return $this->_success;
    }

    // ------------------------------------------------------------------------

    /**
     * Destroy
     *
     * Destroys the current session.
     *
     * @param   string  $session_id Session ID
     * @return  bool
     */
    public function destroy($session_id)
    {
        $where['_id'] = $session_id;
        if ($this->_config['match_ip'])
        {
            $where['ip_address'] = $_SERVER['REMOTE_ADDR'];
        }   
        $options = array('limit'=>true);
        $bulk = new MongoDB\Driver\BulkWrite();
        $bulk->delete($where, $options);

        $write = $this->_db->executeBulkWrite($this->db_name.".".$this->collection, $bulk);

        /*if ( $write->getDeletedCount() == 0 )
        {
            return $this->_fail();
        }*/
        
        if ($this->close() === $this->_success)
        {
            $this->_cookie_destroy();
            return $this->_success;
        }

        return $this->_success;
    }

    // ------------------------------------------------------------------------

    /**
     * Garbage Collector
     *
     * Deletes expired sessions
     * Not required as document expiry will be taken by MongoDB collection TTL
     *
     * @param   int     $maxlifetime    Maximum lifetime of sessions
     * @return  bool
     */
    public function gc($maxlifetime)
    {

        return $this->_success;
    }

    // --------------------------------------------------------------------

    /**
     * Validate ID
     *
     * Checks whether a session ID record exists server-side,
     * to enforce session.use_strict_mode.
     *
     * @param   string  $id
     * @return  bool
     */
    public function validateId($id)
    {
        $where['_id'] = $id;

        if ($this->_config['match_ip'])
        {
            $where['ip_address'] = $_SERVER['REMOTE_ADDR'];
        }

        $options = array();
        $options['projection'] = array('_id'=>1);
        $options['limit'] = 1;

        $query = new MongoDB\Driver\Query($where, $options);
        $cursor = $this->_db->executeQuery($this->db_name.".".$this->collection, $query);
        $result = $cursor->toArray();

        if ( count($result) === 1)
        {
            return true;
        }
        return false;
    }

    // ------------------------------------------------------------------------

    /**
     * Get lock
     *
     * Acquires a lock, depending on the underlying platform.
     * Not required, MongoDB's WiredTiger storage engine maintain read/write lock
     * pretty well.
     *
     * @param   string  $session_id Session ID
     * @return  bool
     */
    protected function _get_lock($session_id)
    {
        return true;
    }

    // ------------------------------------------------------------------------

    /**
     * Release lock
     *
     * Releases a previously acquired lock
     * Not required, MongoDB's WiredTiger storage engine maintain read/write lock
     * pretty well.
     *
     * @return  bool
     */
    protected function _release_lock()
    {
        return true;
    }
}
