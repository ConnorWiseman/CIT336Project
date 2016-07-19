<?php


/**
 * A basic model class that all other models extend.
 */
class Model {


    /**
     * A reference to a shared DatabaseHandler object.
     * @var DatabaseHandler
     */
    protected $_dbh;


    /**
     * The query used to create the model's associated database table.
     * @var string
     */
    protected $_createQuery;


    /**
     * Transliterates a raw string into a URL-friendly slug.
     * @param  string $string A raw string.
     * @return string
     * @link {http://stackoverflow.com/a/13331948/2301088}
     */
    protected function _slugify($string) {
        $string = transliterator_transliterate("Any-Latin; NFD; [:Nonspacing Mark:] Remove; NFC; [:Punctuation:] Remove; Lower();", $string);
        $string = str_replace(' ', '-', $string);
        return $string;
    }


    /**
     * Initializes the model with a database handler instance.
     * @param DatabaseHandler $dbh
     * @throws Exception If no DatabaseHandler is specified.
     */
    public function __construct(DatabaseHandler $dbh) {
        // DatabaseHandler is a required argument. If one was not provided,
        // throw an error.
        if (!$dbh) {
            throw new Exception('No DatabaseHandler specified.');
        }

        $this->_dbh = $dbh;
    }


    /**
     * Creates the current model's database table, if a CREATE TABLE query is
     * specified in the model.
     * @return boolean
     */
    public function createTable() {
        // Only proceed if a table creation query is set.
        if (isset($this->_createQuery)) {
            echo $this->_createQuery;
            
            // Execute the query.
            $this->_dbh->executeQuery($this->_createQuery);

            // Do something with the error message?
            $result = $this->_dbh->getResults();
            $error = $this->_dbh->getErrorMessage();

            return (is_null($result) && is_null($error));
        }

        return false;
    }


    /**
     * A function run to populate the model's associated database table with
     * some initial data.
     */
    public function populate() {
        return;
    }
}