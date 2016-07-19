<?php


/**
 * A utility class for more easily constructing PDO objects. Also has some
 * convenience methods on it; inherits PDO's methods and can be used in place
 * of a regular PDO object once initialized.
 *
 * Class is final; it's not meant to be extended any further.
 *
 * Tested and working rather nicely in PHP 5.6.20.
 */
final class DatabaseHandler extends PDO {


    /**
     * Default database credentials to use.
     * @var array
     */
    private $_config = Array(
        'dbhost' => 'localhost',
        'dbname' => '',
        'dbuser' => '',
        'dbpass' => ''
    );


    /**
     * A queue of any errors thrown by DatabaseHandler's own methods.
     * @var array
     */
    private $_errorMessages = Array();


    /**
     * Options to use in the PDO constructor.
     * @var array
     */
    private $_options = Array(
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false
    );


    /**
     * An array queue of placeholders from DatabaseHandler's own methods.
     * @var array
     */
    private $_placeholders = Array();


    /**
     * An array queue of prepared statements from DatabaseHandler's own methods.
     * @var array
     */
    private $_preparedStatements = Array();


    /**
     * Executed query results queue from DatabaseHandler's own methods.
     * @var array
     */
    private $_results = Array();


    /**
     * An array of PDO's valid fetch method names.
     * @var array
     */
    const FETCH_METHODS = Array(
        'fetch',
        'fetchAll'
    );


    /**
     * Adds a message to the array of error messages.
     * @param string $message
     */
    private function _addErrorMessage($message) {
        // Add a null value to the array of results for consistency's sake.
        array_push($this->_results, null);

        // Add the error message to the array of error messages.
        array_push($this->_errorMessages, $message);
    }


    /**
     * Constructs a MySQL dsn string for use in a PDO object constructor. Used
     * in the class constructor.
     * @return string
     */
    private function _dsn($config) {
        $dsn =  "mysql:host={$config['dbhost']};";
        $dsn .= "dbname={$config['dbname']};";
        $dsn .= "charset=utf8mb4";
        return $dsn;
    }


    /**
     * Shifts the first element off the specified array and returns it. Array
     * argument is passed by reference, so the array will be modified. Called by
     * getErrorMessage and getResults methods.
     *
     * DatabaseHandler only ever uses this method on arrays of arrays, so the
     * return type will always either be an array or null.
     * @param  array  $array An array
     * @return array|null
     */
    private function _getFirstElement(Array &$array) {
        // Set the element to null initially.
        $element = null;

        // If the array has anything in it, shift the first element off.
        if (count($array)) {
            $element = array_shift($array);
        }

        return $element;
    }


    /**
     * Attempts to create a PDO object, using default configuration options or
     * function arguments as configuration options, depending. All defaults can
     * be overridden by passing arguments in to the constructor in the expected
     * order.
     * @param  string $dbhost
     * @param  string $dbname
     * @param  string $dbuser
     * @param  string $dbpass
     * @param  array  $options
     */
    public function __construct() {
        // Acquire the arguments and the number of arguments.
        $args = func_get_args();
        $numArgs = func_num_args();

        // Acquire the config keys and the number of config keys.
        $configKeys = array_keys($this->_config);
        $numKeys = count($configKeys);

        // Iterate over each argument provided to the constructor.
        for ($i = 0; $i < $numArgs; $i++) {
            // If the iterator is greater than the number of keys in the
            // configuration object, the user got smart and provided more
            // than five arguments to deal with. Skip them all from this
            // point onward.
            if ($i > $numKeys) {
                continue;
            }

            // If the iterator is equal to the number of keys in the
            // configuration object and it's also an array, assume the user
            // is attempting to override the default options used when
            // connecting to the database. But don't add it to the array of
            // configuration settings. That would be bad.
            if ($i == $numKeys) {
                if (gettype($args[$i]) == 'array') {
                    $this->_options = $args[$i];
                }

                continue;
            }

            // Upate the current configuration setting with the key at the
            // current index, using the value of the current argument.
            $this->_config[$configKeys[$i]] = $args[$i];
        }

        // Create a new connection using the updated configuration by calling
        // the parent's constructor.
        try {
            parent::__construct(
                $this->_dsn($this->_config),
                $this->_config['dbuser'],
                $this->_config['dbpass'],
                $this->_options
            );
        } catch (PDOException $e) {
            // The object failed to instantiate. Relying on internal error
            // messages is not an option at this point, since the object
            // instance will be null. Errors connecting to the database
            // need to be explicitly handled here.

            // This ain't the greatest way, but eh.
            $error = $e->getMessage();
            echo "An error occurred while connecting to the database: {$error}";
            exit();
        }
    }


    /**
     * Adds a set of key-value pairs to the array of queued placeholders.
     * @param  array $placeholders Key-value pairs representing placeholders and
     *                             values to bind to them.
     * @return $this
     */
    public function bind(Array $placeholders) {
        if (!count($this->_preparedStatements)) {
            $errorMessage = 'No prepared queries to bind to.';
            array_push($this->_errorMessages, $errorMessage);
        } else {
            array_push($this->_placeholders, $placeholders);
        }

        return $this;
    }


    /**
     * Executes the first prepared statement in the queue, using the first set
     * of placeholder values in the queue as values to be bound.
     * @param  string  $method 'fetch' or 'fetchAll'. Defaults to 'fetchAll'.
     * @param  integer $style  Defaults to PDO::FETCH_ASSOC.
     * @return $this
     */
    public function executePreparedStatement(
        $method = 'fetchAll',
        $style = PDO::FETCH_ASSOC
    ) {
        try {
            // If there are no prepared queries in the queue, throw an error.
            if (!count($this->_preparedStatements)) {
                $errorMessage = 'No prepared queries to execute.';
                throw new Exception($errorMessage);
            }

            // Acquire the first prepared statement from the queue.
            $stmt = $this->_getFirstElement($this->_preparedStatements);


            // If there are no placeholders to be bound in the queue, throw
            // an error.
            if (!count($this->_placeholders)) {
                $errorMessage = 'No placeholders to bind.';
                throw new Exception($errorMessage);
            }

            // Acquire the first set of placeholders from the queue.
            $placeholders = $this->_getFirstElement($this->_placeholders);

            // Count the number of placeholders the statement expects to have.
            $numPlaceholders = substr_count($stmt->queryString, ':');

            // If the number of placeholders in the statement doesn't match the
            // number of placeholders in the placeholder set, throw an error.
            if ($numPlaceholders !== count($placeholders)) {
                $errorMessage = 'Placeholder and value counts don\'t match.';
                throw new Exception($errorMessage);
            }

            // For each placeholder as a key-arguments pair...
            foreach ($placeholders as $key => $arguments) {
                // If the placeholder doesn't begin with a colon, add one.
                if ($key[0] !== ':') {
                    $key = ":{$key}";
                }

                // If the arguments are not an array, make them one.
                if (!is_array($arguments)) {
                    $arguments = Array($arguments);
                }

                // Add the placeholder key to the beginning of the arguments.
                array_unshift($arguments, $key);

                // Bind each placeholder key to its value, using any other
                // arguments that might have been defined for each placeholder.
                call_user_func_array(array($stmt, 'bindValue'), $arguments);
            }

            // Grab the query string.
            $query = $stmt->queryString;

            // Execute the prepared statement.
            $stmt->execute();

            // If we're not running an UPDATE or INSERT query...
            if (strpos($query, 'UPDATE') === false &&
                strpos($query, 'INSERT') === false &&
                strpos($query, 'DELETE') === false) {
                // echo $query;
                // While there's data to be retrieved, fetch it. It doesn't matter
                // which fetch style we're using, since fetchAll will finish after
                // one pass. Also, variable interpolation is awesome.
                while ($result = $stmt->{$method}($style)) {
                    // Add the result set to the array of results.
                    array_push($this->_results, $result);
                }
            }

            // Close the cursor.
            $stmt->closeCursor();

        } catch (PDOException $e) {
            // Add any PDOException's message to the array of error messages.
            $this->_addErrorMessage($e->getMessage());

        } catch (Exception $e) {
            // Evidently throwing PDOExceptions by hand is bad practice,
            // hence this second catch block for regular Exceptions.
            $this->_addErrorMessage($e->getMessage());

        }

        return $this;
    }


    /**
     * A convenience method for rapidly executing a given query.
     * Less flexible, but quick and dirty.
     * @param  string  $query  A query to execute.
     * @param  string  $method 'fetch' or 'fetchAll'. Defaults to 'fetchAll'.
     * @param  integer $style  Defaults to PDO::FETCH_ASSOC.
     * @return $this
     */
    public function executeQuery(
        $query,
        $method = 'fetchAll',
        $style = PDO::FETCH_ASSOC
    ) {
        try {
            // Prepare the query and execute the prepared statement.
            $stmt = $this->prepare($query);
            $stmt->execute();

            // If the fetch type isn't in the array of valid fetch methods,
            // throw an error.
            if (!in_array($method, DatabaseHandler::FETCH_METHODS, true)) {
                throw new Exception('Invalid PDO fetch method specified.');
            }

            // While there's data to be retrieved, fetch it. It doesn't matter
            // which fetch style we're using, since fetchAll will finish after
            // one pass. Also, variable interpolation is awesome.
            while ($result = $stmt->{$method}($style)) {
                // Add the result set to the array of results.
                array_push($this->_results, $result);
            }

            // Close the cursor.
            $stmt->closeCursor();

        } catch (PDOException $e) {
            // Add any PDOException's message to the array of error messages.
            $this->_addErrorMessage($e->getMessage());

        } catch (Exception $e) {
            // Evidently throwing PDOExceptions by hand is bad practice,
            // hence this second catch block for regular Exceptions.
            $this->_addErrorMessage($e->getMessage());

        }

        return $this;
    }


    /**
     * Retrieves the oldest error from the array of error messages if it exists.
     * @return array|null
     */
    public function getErrorMessage() {
        return $this->_getFirstElement($this->_errorMessages);
    }


    /**
     * Retrieves the oldest result set from the array of results if it exists.
     * @return array|null
     */
    public function getResults() {
        return $this->_getFirstElement($this->_results);
    }


    /**
     * Creates a prepared statement from a given query string and adds it to
     * the array of queued prepared statements.
     * @param  string  $query  A query to prepare.
     * @return $this
     */
    public function prepareStatement($query) {
        $stmt = $this->prepare($query);
        array_push($this->_preparedStatements, $stmt);
        return $this;
    }
}