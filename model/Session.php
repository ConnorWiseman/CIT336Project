<?php


// Require parent class.
require_once('Model.php');


/**
 * A model for creating, updating, and removing session records from the
 * database.
 */
final class Session extends Model {


    /**
     * Information in the current session.
     * @var array
     */
    private $_info = Array(
        'id'         => null,
        'auth_token' => null,
        'author_id'  => null,
        'ip_address' => null,
        'date'       => null
    );


    /**
     * The query used to create the model's associated database table.
     * @var string
     */
    protected $_createQuery = 'CREATE TABLE IF NOT EXISTS sessions (
        `id` CHAR(86) NOT NULL,
        `auth_token` CHAR(32) NOT NULL,
        `author_id` INT UNSIGNED,
        `ip_address` INT UNSIGNED NOT NULL,
        `date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY(`id`),
        FOREIGN KEY(`author_id`) REFERENCES authors(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci;';


    /**
     * Generates 32 characters of random data to use as an authorization key.
     * Auth tokenss are tokens passed in alongside form submissions to help
     * guard against CSRF attacks. Called by read method.
     * @return string
     */
    private function _generateAuthToken() {
        return bin2hex(openssl_random_pseudo_bytes(16));
    }


    /**
     * Obtains the client's IP address. Called by read method.
     * @return string
     */
    private function _getIPAddress() {
        $address = getenv('HTTP_CLIENT_IP')?:
                   getenv('HTTP_X_FORWARDED_FOR')?:
                   getenv('HTTP_X_FORWARDED')?:
                   getenv('HTTP_FORWARDED_FOR')?:
                   getenv('HTTP_FORWARDED')?:
                   getenv('REMOTE_ADDR');
        return $address;
    }


    /**
     * Defers to the parent constructor.
     * @param DatabaseHandler $dbh
     */
    public function __construct(DatabaseHandler $dbh) {
        parent::__construct($dbh);
    }


    /**
     * Authorizes the current session with a given author_id.
     * @param  string $session_id
     * @param  string $author_id
     */
    public function authorize($author_id) {
        // Regenerate the session identifier to prevent any kind of session
        // hijacking attacks.
        session_regenerate_id(true);

        // Acquire the new session's identifier.
        $session_id = session_id();

        // Set the author identifier to be updated.
        $this->_info['author_id'] = $author_id;

        // Update the session information, then load the changes into the local
        // information store.
        $this->update($session_id);
        $this->load($session_id);
    }


    /**
     * Compares a given auth_token to the current session's auth_token.
     * @param  string $authToken
     * @return boolean
     */
    public function checkAuthToken($authToken) {
        if ($authToken === $this->get('auth_token')) {
            return true;
        }
        return false;
    }


    /**
     * Deauthorizes a session, setting the associated author_id to null.
     */
    public function deauthorize() {
        // Regenerate the session identifier.
        session_regenerate_id(true);

        // Acquire the new session's identifier.
        $session_id = session_id();

        // Set the author id to be updated.
        $this->_info['author_id'] = null;

        // Update the session information, then load the changes into the local
        // information store.
        $this->update($session_id);
        $this->load($session_id);
    }


    /**
     * Deletes a specified record from the database.
     * @param  string $id
     * @return boolean
     */
    public function delete($id) {
        // Prepare, bind values to, and execute a prepared query to delete
        // the specified session record.
        $this->_dbh->prepareStatement('DELETE FROM `sessions`
            WHERE `id` = :id
            LIMIT 1;
        ');
        $this->_dbh->bind(Array(
            'id' => Array($id, PDO::PARAM_STR)
        ));
        $this->_dbh->executePreparedStatement();

        $results = $this->_dbh->getResults();
        $error   = $this->_dbh->getErrorMessage();
        return (is_null($results) && is_null($error));
    }


    /**
     * Deletes all records from the database older than a specific timestamp.
     * @param  integer $expiry
     * @return boolean
     */
    public function expire($expiry) {
        // Prepare, bind values to, and execute a prepared query to delete
        // all matching session records.
        $this->_dbh->prepareStatement('DELETE FROM `sessions`
            WHERE `date` < :lifetime;
        ');
        $this->_dbh->bind(Array(
            'lifetime' => Array($expiry, PDO::PARAM_INT)
        ));
        $this->_dbh->executePreparedStatement();

        $results = $this->_dbh->getResults();
        $error   = $this->_dbh->getErrorMessage();
        return (is_null($results) && is_null($error));
    }


    /**
     * Retrieves the value of the specified key from the instance's session
     * information array, if it exists. Returns null if no such key is found.
     * @param  string $key The key to retrieve.
     * @return string|null
     */
    public function get($key) {
        if (array_key_exists($key, $this->_info)) {
            return $this->_info[$key];
        }

        return null;
    }


    /**
     * Returns the instance's DatabaseHandler instance.
     * @return DatabaseHandler
     */
    public function getDatabase() {
        return $this->_dbh;
    }


    /**
     * Loads a specified record from the database into the object instance's
     * session information array.
     * @param  string $id
     * @return boolean
     */
    public function load($id) {
        $this->_dbh->prepareStatement('SELECT
            `id`, `auth_token`, `author_id`, `ip_address`, `date`
                FROM `sessions`
                WHERE `id` = :id
                LIMIT 1;
        ');
        $this->_dbh->bind(Array(
            'id' => Array($id, PDO::PARAM_STR)
        ));
        $this->_dbh->executePreparedStatement();

        // Get the results of the query.
        $results = $this->_dbh->getResults();
        $error   = $this->_dbh->getErrorMessage();

        // Merge the results of the select query into this instance's local
        // array of session information.
        if ($results && is_null($error)) {
            $this->_info = array_merge($this->_info, $results[0]);
            return true;
        }
        return false;
    }


    /**
     * Renews the current session's auth_token.
     * @return boolean
     */
    public function renewAuthToken() {
        $authToken = $this->_generateAuthToken();
        $sessionId = session_id();

        $this->_dbh->prepareStatement('UPDATE sessions
            SET `auth_token` = :auth_token
            WHERE `id` = :id;
        ');
        $this->_dbh->bind(Array(
            'auth_token' => Array($authToken, PDO::PARAM_STR),
            'id'         => Array($sessionId, PDO::PARAM_STR)
        ));
        $this->_dbh->executePreparedStatement();

        $results = $this->_dbh->getResults();
        $error   = $this->_dbh->getErrorMessage();

        // If the query was a success, update the instance's auth_token.
        if (is_null($results) && is_null($error)) {
            $this->_info['auth_token'] = $authToken;
            return true;
        }
        return false;
    }


    /**
     * Sets the instance's DatabaseHandler property.
     * @param * $dbh
     */
    public function setDatabase($dbh) {
        return $this->_dbh = $dbh;
    }


    /**
     * "Upserts" a record into the sessions table, updating the IP address and
     * last accessed date if a duplicate record is already found.
     * @param  string $id
     * @param  string $auth_token
     * @param  string $ip_address
     * @param  string $date
     * @return boolean
     */
    public function update($id) {
        // Generate a new authorization token.
        $auth_token = $this->_generateAuthToken();

        // Acquire the client's IP address.
        $ip_address = $this->_getIPAddress();

        // Create a new timestamp.
        $date = date('Y-m-d H:i:s', time());

        // Attempt to create a new session record. If a record matching the
        // current id already exists, update the IP address and the access
        // date.
        $this->_dbh->prepareStatement('INSERT INTO `sessions`
                (`id`, `auth_token`, `author_id`, `ip_address`)
            VALUES
                (:id, :auth_token, :author_id, :ip_address1)
            ON DUPLICATE KEY UPDATE
                `ip_address` = :ip_address2, `date` = :date;
        ');
        $this->_dbh->bind(Array(
            'id'          => Array($id, PDO::PARAM_STR),
            'auth_token'  => Array($auth_token, PDO::PARAM_STR),
            'author_id'   => Array($this->_info['author_id'], PDO::PARAM_INT),
            'ip_address1' => Array($ip_address, PDO::PARAM_INT),
            'ip_address2' => Array($ip_address, PDO::PARAM_INT),
            'date'        => Array($date, PDO::PARAM_INT)
        ));
        $this->_dbh->executePreparedStatement();

        $results = $this->_dbh->getResults();
        $error   = $this->_dbh->getErrorMessage();
        return (is_null($results) && is_null($error));
    }
}