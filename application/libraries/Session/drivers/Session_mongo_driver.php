<?php defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * CodeIgniter Session Mongo Driver
 *
 * @package	CodeIgniter
 * @subpackage	Libraries
 * @category	Sessions
 * @author	Intekhab Rizvi
 * @link	https://codeigniter.com/user_guide/libraries/sessions.html
 */

class CI_Session_mongo_driver extends CI_Session_driver implements SessionHandlerInterface
{
    /**
     * DB object
     *
     * @var	object
     */
    protected $_mongo;
    protected $mongo_connect;

    /**
     * Private variable to hold MongoDB database configs
     *
     * @var	array
     */
    protected $_mongo_config;

    /**
     * Class constructor
     *
     * @param	array	$params	Configuration parameters
     * @return	void
     */
    public function __construct(&$params)
    {
        // DO NOT forget this
        parent::__construct($params);

        // Configuration & other initializations
        $CI =& get_instance();

        $CI->config->load('session_mongo');

        $this->_build_config($CI);

    }

    /**
     * private method to prepare all mongodb related configs
     *
     * @param	object	CI instance
     * @return	boolean
     */
    private function _build_config($CI)
    {
        if(!empty($CI->config->item('session_mongo_location')))
        {
            $this->_mongo_config['location'] = $CI->config->item('session_mongo_location');
        }
        else
        {
            throw new Exception('MongoDB config missing, check session_mongo_location value.');
        }

        if(!empty($CI->config->item('session_mongo_port')))
        {
            $this->_mongo_config['port'] = $CI->config->item('session_mongo_port');
        }
        else
        {
            throw new Exception('MongoDB config missing, check session_mongo_port value.');
        }

        if(!empty($CI->config->item('session_mongo_db')))
        {
            $this->_mongo_config['db'] = $CI->config->item('session_mongo_db');
        }
        else
        {
            throw new Exception('MongoDB config missing, check session_mongo_db value.');
        }

        if(!empty($CI->config->item('session_mongo_user')))
        {
            $this->_mongo_config['username'] = $CI->config->item('session_mongo_user');
        }
        else
        {
            throw new Exception('MongoDB config missing, check session_mongo_user value.');
        }

        if(!empty($CI->config->item('session_mongo_password')))
        {
            $this->_mongo_config['password'] = $CI->config->item('session_mongo_password');
        }
        else
        {
            throw new Exception('MongoDB config missing, check session_mongo_password value.');
        }

        if(!empty($CI->config->item('session_mongo_collection')))
        {
            $this->_mongo_config['table'] = $CI->config->item('session_mongo_collection');
        }
        else
        {
            throw new Exception('MongoDB config missing, check session_mongo_collection value.');
        }

        if(!empty($CI->config->item('session_mongo_write_concerns')))
        {
            $this->_mongo_config['w'] = $CI->config->item('session_mongo_write_concerns');
        }
        else
        {
            throw new Exception('MongoDB config missing, check session_mongo_write_concerns value.');
        }

        if(!empty($CI->config->item('session_mongo_write_journal')))
        {
            $this->_mongo_config['j'] = $CI->config->item('session_mongo_write_journal');
        }
        else
        {
            throw new Exception('MongoDB config missing, check session_mongo_write_journal value.');
        }
    }

    public function open($save_path, $name)
    {
        // Initialize storage mechanism (connection)
        //prepare mongodb connection string
        $dns = "mongodb://{$this->_mongo_config['location']}:{$this->_mongo_config['port']}/{$this->_mongo_config['db']}";

        //perform connection.
        $this->mongo_connect = new MongoClient($dns,
            array('username'=>$this->_mongo_config['username'], 'password'=>$this->_mongo_config['password'])
        );
        if(empty($this->mongo_connect) || ! $this->mongo_connect)
        {
            return $this->_failure;
        }
        //when connected successfully, selected the database.
        $this->_mongo = $this->mongo_connect->selectDB($this->_mongo_config['db']);
        $this->_mongo = $this->mongo_connect->{$this->_mongo_config['db']};

        return $this->_success;

    }

    /**
     * Read
     *
     * Reads session data and acquires a lock
     *
     * @param	string	$session_id	Session ID
     * @return	string	Serialized session data
     */
    public function read($session_id)
    {
        // Needed by write() to detect session_regenerate_id() calls
        $this->_session_id = $session_id;

        $wheres['_id'] = $session_id;

        $select['data'] = true;
        $select['_id'] = true;

        if ($this->_config['match_ip'])
        {
            $wheres['ip_address'] = $_SERVER['REMOTE_ADDR'];
        }

        $result = $this->_mongo->{$this->_mongo_config['table']}
            ->findOne($wheres, $select);

        if ($result == null || count($result) == 0)
        {
            // PHP7 will reuse the same SessionHandler object after
            // ID regeneration, so we need to explicitly set this to
            // FALSE instead of relying on the default ...
            $this->_row_exists = FALSE;
            $this->_fingerprint = md5('');
            return '';
        }

        $this->_fingerprint = md5($result['data']);
        $this->_row_exists = TRUE;
        return $result['data'];

    }

    public function write($session_id, $session_data)
    {
        // Was the ID regenerated?
        if ($session_id !== $this->_session_id)
        {
             $this->_row_exists = FALSE;
            $this->_session_id = $session_id;
        }

        if ($this->_row_exists === FALSE)
        {
            $insert_data = array(
                '_id' => $session_id,
                'ip_address' => $_SERVER['REMOTE_ADDR'],
                'timestamp' => time(),
                'data' => $session_data
            );

            if ($this->_mongo->{$this->_mongo_config['table']}->insert($insert_data, array('w' => $this->_mongo_config['w'], 'j'=>$this->_mongo_config['j'])))
            {
                $this->_fingerprint = md5($session_data);
                $this->_row_exists = TRUE;
                return $this->_success;
            }

            return $this->_failure;
        }

        $wheres['_id'] = $session_id;

        if ($this->_config['match_ip'])
        {
            $wheres['ip_address'] = $_SERVER['REMOTE_ADDR'];
        }

        $update_data = array('$set'=>array('timestamp' => time()));
        if ($this->_fingerprint !== md5($session_data))
        {
            $update_data['data'] = $session_data;
        }

        if ($this->_mongo->{$this->_mongo_config['table']}->update($wheres, $update_data, array('w' => $this->_mongo_config['w'], 'j'=>$this->_mongo_config['j'])))
        {
            $this->_fingerprint = md5($session_data);
            return $this->_success;
        }

        return $this->_failure;
    }

    public function close()
    {
        return ($this->mongo_connect->close())
            ? $this->_success
            : $this->_failure;
    }

    public function destroy($session_id)
    {
        $wheres['_id'] = $session_id;
        if ($this->_config['match_ip'])
        {
            $wheres['ip_address'] = $_SERVER['REMOTE_ADDR'];
        }

        if ( ! $this->_mongo->{$this->_mongo_config['table']}->delete($wheres))
        {
            return $this->_failure;
        }

        if ($this->close() === $this->_success)
        {
            $this->_cookie_destroy();
            return $this->_success;
        }

        return $this->_failure;
    }

    public function gc($maxlifetime)
    {

        return (
            $this->_mongo->{$this->_mongo_config['table']}->remove(
                array('timestamp' => array('$lte' =>(int)time() - 1)),
                array('w' => $this->_mongo_config['w'], 'j'=>$this->_mongo_config['j'])
            )
        )
            ? $this->_success
            : $this->_failure;
    }

}

// application/libraries/Session/drivers/Session_mongo_driver.php: