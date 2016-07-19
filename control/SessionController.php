<?php


// Require parent class.
require_once(__DIR__ . '/Controller.php');


/**
 * A class for managing PHP sessions in a MySQL database. Plug and play:
 * creating a new instance of Session automatically manages sessions for you.
 */
class SessionController extends Controller implements SessionHandlerInterface {


    /**
     * Paramaters to use when setting cookies.
     * @var array
     */
    private $_cookieParams = Array(
        'name'     => 'session_id',
        'lifetime' => (60 * 60 * 24 * 30),
        'path'     => '/',
        'domain'   => '',
        'secure'   => false,
        'httpOnly' => true
    );


    /**
     * Returns the server name. Called by constructor.
     * @return string
     */
    private function _getServerName() {
        return getenv('SERVER_NAME');
    }


    /**
     * Returns true if HTTPS protocol is detected and false if not. Called by
     * constructor.
     * @return boolean
     */
    private function _isSecure() {
        $https = getenv('HTTPS');

        if (!empty($https) && $https !== 'off') {
            return true;
        }

        return false;
    }


    /**
     * Calls the parent object constructor. Sets some cookie parameters and
     * starts a session, using $this as the new session save handler.
     * @param ModelCollection $models
     */
    public function __construct(\ModelCollection $models) {
        parent::__construct($models);

        // Initialize instance properties.
        $this->_cookieParams['domain'] = $this->_getServerName();
        $this->_cookieParams['secure'] = $this->_isSecure();

        // Set the session name.
        session_name($this->_cookieParams['name']);

        // Set the default session cookie parameters.
        session_set_cookie_params(
            $this->_cookieParams['lifetime'],
            $this->_cookieParams['path'],
            $this->_cookieParams['domain'],
            $this->_cookieParams['secure'],
            $this->_cookieParams['httpOnly']
        );

        // Change some ini settings for additional security.
        // Should probably not be done in the actual php.ini file instead.
        ini_set('session.entropy_file', '/dev/urandom');
        ini_set('session.entropy_length', '1024');
        ini_set('session.gc_divisor', '100');
        ini_set('session.gc_probability', '1');
        ini_set('session.gc_maxlifetime', "{$this->_cookieParams['lifetime']}");
        ini_set('session.hash_bits_per_character', '6');
        ini_set('session.hash_function', 'sha512');
        ini_set('session.use_only_cookies', '1');

        // Override the default session handler with the current instance.
        session_set_save_handler($this, true);

        // Start a session.
        session_start();
    }


    /**
     * Session doesn't actually interact with files like PHP's built-in
     * session objects do, so this function is just a placeholder that unsets
     * the instance's database handler and returns true.
     * @return boolean
     */
    public function close() {
        $sessionModel = $this->_models->get('session');
        $sessionModel->setDatabase(null);
        return true;
    }


    /**
     * Destroys a specified session record, removing it from the database and
     * expiring the session cookie on the client side.
     * @param  string $key The identifier for the session to destroy.
     * @return boolean
     */
    public function destroy($key) {
        $sessionModel = $this->_models->get('session');

        if ($sessionModel->delete($key)) {
            setcookie(
                $this->_cookieParams['name'],
                '',
                time() - 3600,
                $this->_cookieParams['path'],
                $this->_cookieParams['domain'],
                $this->_cookieParams['secure'],
                $this->_cookieParams['httpOnly']
            );

            return true;
        }

        return false;
    }


    /**
     * Clears old sessions from the database.
     * @param  integer $maxlifetime
     * @return boolean
     */
    public function gc($maxlifetime) {
        $expiry = time() - $maxlifetime;
        $sessionModel = $this->_models->get('session');

        return $sessionModel->expire($expiry);
    }


    /**
     * Session doesn't actually interact with files like PHP's built-in
     * session functions do, so this function is just a placeholder that
     * returns true if the instance's database handler exists and isn't null.
     * @param  string $save_path
     * @param  string $session_name
     * @return boolean
     */
    public function open($save_path, $session_name) {
        $sessionModel = $this->_models->get('session');

        if(!is_null($sessionModel->getDatabase())) {
            return true;
        }

        return false;
    }


    /**
     * Updates and reads a given session from the database, creating it if it
     * does not already exist. Always returns a blank string to meet the
     * expectations of SessionHandlerInterface::read.
     * @param  string $key The identifier of the session record to read.
     * @return string
     */
    public function read($key) {
        // Update the session record with any new data, then load the updated
        // session record into the session model.
        $sessionModel = $this->_models->get('session');
        $sessionModel->update($key);
        $sessionModel->load($key);

        // Because session information is stored in the object instance, no
        // data needs to be returned by this function. However, according to
        // documentation SessionHandlerInterface::read must return an empty
        // string if it finds no data in the session it read, so this is
        // necessary:
        return '';
    }


    /**
     * Session has no need to write to $_SESSION, so this function is
     * just a placeholder that always returns true. Normally this indicates
     * that the specified key has been written to successfully.
     * @param  string $key
     * @param  string $value
     * @return boolean
     */
    public function write($key, $value) {
        return true;
    }
}