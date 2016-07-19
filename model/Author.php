<?php


// Require parent class.
require_once('Model.php');


/**
 * A model for retrieving application settings from the database. Nothing
 * special.
 */
final class Author extends Model {


    /**
     * The query used to create the model's associated database table.
     * @var string
     */
    protected $_createQuery = 'CREATE TABLE IF NOT EXISTS authors (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `email` VARCHAR(191) NOT NULL,
        `password` CHAR(60) NOT NULL,
        `name` VARCHAR(255),
        `slug` VARCHAR(255),
        `biography` TEXT,
        `created` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY(`id`),
        CONSTRAINT `email` UNIQUE(`email`)
    ) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=dynamic;';


    /**
     * Hashes a password. Uses PHP's bcrypt implementation by default.
     * @param  string $password A plaintext password to hash.
     * @return string
     */
    private function _hash($password) {
        return password_hash($password, PASSWORD_DEFAULT, ['cost' => 8]);
    }


    /**
     * Defers to the parent constructor.
     * @param DatabaseHandler $dbh
     */
    public function __construct(DatabaseHandler $dbh) {
        parent::__construct($dbh);
    }


    /**
     * Checks the database to see if the password hash for a given email address
     * can be verified. Returns true if a match is detected and false if not.
     * @param  string $email
     * @param  string $password
     * @return boolean
     */
    public function checkCredentials($email, $password) {
        $query = $this->_dbh->prepareStatement('SELECT
            `password`
            FROM authors
            WHERE `email` = :email
            LIMIT 1;'
        );
        $query->bind(Array(
            'email' => Array($email, PDO::PARAM_STR)
        ));
        $query->executePreparedStatement();

        // Do something with the error?
        $results = $query->getResults();
        $error = $query->getErrorMessage();

        if ($results && is_null($error)) {
            return password_verify($password, $results[0]['password']);
        }

        return false;
    }


    /**
     * Counts the number of users in the database. Really only used to switch
     * the dashboard's sign in behavior to register if no users are detected.
     * @return int The number of user records in the database.
     */
    public function count() {
        $query = $this->_dbh->executeQuery('SELECT COUNT(*) FROM authors;');
        $results = $query->getResults();

        // Cast the results to an integer, then return.
        return (int) $results[0]['COUNT(*)'];
    }


    /**
     * Creates a new user with the specified email and password. Returns true
     * on success and false on failure.
     * @param  string $email
     * @param  string $password
     * @return boolean
     */
    public function create($email, $password) {
        $query = $this->_dbh->prepareStatement('INSERT INTO authors
            (`email`, `password`)
            VALUES
            (:email, :password);
        ');
        $query->bind(Array(
            'email' => Array($email, PDO::PARAM_STR),
            'password' => Array($this->_hash($password), PDO::PARAM_STR)
        ));
        $query->executePreparedStatement();

        // INSERT queries don't give any results back, so there's no point in
        // trying to handle them here. Instead, just check to make sure no
        // errors happened.
        return is_null($query->getErrorMessage());
    }

    public function getAuthor($author) {
        $this->_dbh->prepareStatement('SELECT *
            FROM authors
            WHERE `slug` = :author;
        ');
        $this->_dbh->bind(Array(
            'author' => Array($author, PDO::PARAM_STR)
        ));
        $this->_dbh->executePreparedStatement();

        $results = $this->_dbh->getResults();
        $error   = $this->_dbh->getErrorMessage();

        if ($results && is_null($error)) {
            return $results[0];
        }

        return null;
    }


    public function getAllAuthors() {
        $query = $this->_dbh->executeQuery('SELECT
            `name`, `slug`
            FROM authors;
        ');

        return $query->getResults();
    }


    /**
     * Retrieves the data of a record matching a specified email address.
     * @param  string $email
     * @return array|null
     */
    public function getByEmail($email) {
        $query = $this->_dbh->prepareStatement('SELECT
            `id`, `email`, `name`, `slug`
            FROM authors
            WHERE `email` = :email
            LIMIT 1;'
        );
        $query->bind(Array(
            'email' => Array($email, PDO::PARAM_STR)
        ));
        $query->executePreparedStatement();

        // Do something with the error?
        $results = $query->getResults();
        $error = $this->_dbh->getErrorMessage();

        if ($results && is_null($error)) {
            return $results[0];
        }

        return null;
    }


    /**
     * Retrieves the data of a record matching a specified id.
     * @param  string $email
     * @return array|null
     */
    public function getById($id) {
        $query = $this->_dbh->prepareStatement('SELECT
            `id`, `email`, `name`, `slug`, `biography`
            FROM authors
            WHERE `id` = :id
            LIMIT 1;'
        );
        $query->bind(Array(
            'id' => Array($id, PDO::PARAM_INT)
        ));
        $query->executePreparedStatement();

        // Do something with the error?
        $results = $query->getResults();
        $error = $query->getErrorMessage();

        if ($results && is_null($error)) {
            return $results[0];
        }

        return null;
    }

    public function update($id, $email, $name, $biography) {
        $slug = $this->_slugify($name);
        $query = $this->_dbh->prepareStatement('UPDATE authors
            SET `email` = :email, `name` = :name, `slug` = :slug, `biography` = :biography
            WHERE `id` = :id;
        ');
        $query->bind(Array(
            'email'     => Array($email, PDO::PARAM_STR),
            'name'      => Array($name, PDO::PARAM_STR),
            'slug'      => Array($slug, PDO::PARAM_STR),
            'biography' => Array($biography, PDO::PARAM_STR),
            'id'        => Array($id, PDO::PARAM_INT)
        ));
        $query->executePreparedStatement();

        // Do something with the error?
        $results = $query->getResults();
        $error = $query->getErrorMessage();

        return (is_null($results) && is_null($error));
    }

    public function changePassword($id, $password) {
        $password = $this->_hash($password);
        $query = $this->_dbh->prepareStatement('UPDATE authors
            SET `password` = :password
            WHERE `id` = :id;
        ');
        $query->bind(Array(
            'password' => Array($password, PDO::PARAM_STR),
            'id' => Array($id, PDO::PARAM_INT)
        ));
        $query->executePreparedStatement();

        // Do something with the error?
        $results = $query->getResults();
        $error = $query->getErrorMessage();

        return (is_null($results) && is_null($error));
    }
}