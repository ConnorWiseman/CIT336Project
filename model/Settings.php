<?php


// Require parent class.
require_once('Model.php');


/**
 * A model for retrieving application settings from the database. Nothing
 * special.
 */
final class Settings extends Model {


    /**
     * Default settings to be overridden.
     * @var array
     */
    private $_info = Array(
        'title'          => null,
        'description'    => null,
        'colophon'       => null,
        'pretty_links'   => false,
        'posts_per_page' => 1
    );


    /**
     * The query used to create the model's associated database table.
     * @var string
     */
    protected $_createQuery = 'CREATE TABLE IF NOT EXISTS settings (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `title` VARCHAR(255) NOT NULL,
        `description` VARCHAR(255) NOT NULL,
        `colophon` TEXT DEFAULT NULL,
        `pretty_links` BOOLEAN NOT NULL DEFAULT 0,
        `posts_per_page` INT UNSIGNED NOT NULL DEFAULT 5,
        PRIMARY KEY(`id`)
    ) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci;';


    /**
     * Defers to the parent constructor, then loads settings from the database.
     * @param DatabaseHandler $dbh
     */
    public function __construct(DatabaseHandler $dbh) {
        parent::__construct($dbh);
        $this->load();
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
     * Retrieves application settings from the database and stores them locally.
     */
    public function load() {
        $query = $this->_dbh->executeQuery('SELECT
            `title`, `description`, `colophon`, `pretty_links`, `posts_per_page`
            FROM settings
            WHERE `id` = \'1\'
            LIMIT 1;'
        );

        // Do something with the error?
        $results = $query->getResults();
        $error = $this->_dbh->getErrorMessage();

        if ($results && is_null($error)) {
            $this->_info = $results[0];
        }
    }


    /**
     * Initially populates the database with some necessary data.
     */
    public function populate() {
        $this->_dbh->executeQuery('INSERT INTO settings
            (`title`, `description`, `colophon`)
            VALUES
            (\'CIT336 Project\', \'A very simple blogging application.\', \'Nam commodo ultricies mollis. Cum sociis natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus. Duis at aliquet justo, ac egestas eros.\');'
        );
    }


    /**
     * Updates the application settings.
     * @param  string $title
     * @param  string $description
     * @param  int    $prettyLinks
     * @param  string $colophon
     * @return boolean
     */
    public function update($title, $description, $prettyLinks, $colophon, $postsPerPage) {
        $query = $this->_dbh->prepareStatement('UPDATE settings
            SET `title` = :title, `description` = :description, `colophon` = :colophon, `pretty_links` = :pretty_links, `posts_per_page` = :posts_per_page
            WHERE `id` = \'1\'
        ');
        $query->bind(Array(
            'title'          => Array($title, PDO::PARAM_STR),
            'description'    => Array($description, PDO::PARAM_STR),
            'colophon'       => Array($colophon, PDO::PARAM_STR),
            'pretty_links'   => Array($prettyLinks, PDO::PARAM_INT),
            'posts_per_page' => Array($postsPerPage, PDO::PARAM_INT)
        ));
        $query->executePreparedStatement();

        // Do something with the error?
        $error = $this->_dbh->getErrorMessage();

        var_dump($error);

        if (is_null($error)) {
            // Update at least the value of pretty_links, so the settings form
            // redirects to the appropriate URL schema when the pretty links
            // setting is changed.
            $this->_info['pretty_links'] = $prettyLinks;
            return true;
        }

        return false;
    }
}