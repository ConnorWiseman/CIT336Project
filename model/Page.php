<?php


// Require parent class.
require_once('Model.php');


/**
 * A model for retrieving application settings from the database. Nothing
 * special.
 */
final class Page extends Model {


    /**
     * The query used to create the model's associated database table.
     * @var string
     */
    protected $_createQuery = 'CREATE TABLE IF NOT EXISTS pages (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `title` VARCHAR(255) NOT NULL,
        `slug` VARCHAR(191) NOT NULL,
        `description` VARCHAR(255) NOT NULL,
        `contents` TEXT NOT NULL,
        `menu_display` BOOLEAN NOT NULL DEFAULT 0,
        PRIMARY KEY(`id`),
        CONSTRAINT `slug` UNIQUE(`slug`),
        INDEX (`menu_display`)
    ) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=dynamic;';


    /**
     * Defers to the parent constructor.
     * @param DatabaseHandler $dbh
     */
    public function __construct(DatabaseHandler $dbh) {
        parent::__construct($dbh);
    }


    /**
     * Creates a new page with the specified data.
     * @param  string $title         [description]
     * @param  string $description   [description]
     * @param  string $contents      [description]
     * @param  string $shouldDisplay [description]
     * @return boolean
     */
    public function createPage($title, $description, $contents, $display) {
        $slug = $this->_slugify($title);

        // Prepare, bind values to, and execute a query.
        $query = $this->_dbh->prepareStatement('INSERT INTO pages
            (`title`, `description`, `slug`, `contents`, `menu_display`)
            VALUES
            (:title, :description, :slug, :contents, :display);
        ');
        $query->bind(Array(
            'title' => Array($title, PDO::PARAM_STR),
            'description' => Array($description, PDO::PARAM_STR),
            'slug' => Array($slug, PDO::PARAM_STR),
            'contents' => Array($contents, PDO::PARAM_STR),
            'display' => Array($display, PDO::PARAM_INT)
        ));
        $query->executePreparedStatement();

        // Return depending on the query execution.
        $result = $this->_dbh->getResults();
        $error = $this->_dbh->getErrorMessage();
        return (is_null($result) && is_null($error));
    }


    /**
     * Deletes the page with the specified slug.
     * @param  string $slug
     * @return boolean
     */
    public function deletePage($slug) {
        // Prepare, bind values to, and execute a query.
        $this->_dbh->prepareStatement('DELETE FROM pages
            WHERE `slug` = :slug
            LIMIT 1;
        ');
        $this->_dbh->bind(Array(
            'slug' => Array($slug, PDO::PARAM_STR)
        ));
        $this->_dbh->executePreparedStatement();

        // Return depending on the query execution.
        $results = $this->_dbh->getResults();
        $error   = $this->_dbh->getErrorMessage();
        return (is_null($results) && is_null($error));
    }


    /**
     * Retrieves the pages intended to be displayed in the navigation.
     * @return array|null
     */
    public function getNavigationPages() {
        $query = $this->_dbh->executeQuery('SELECT
            `title`, `slug`
            FROM pages
            WHERE `menu_display` = 1;
        ');

        return $query->getResults();
    }


    /**
     * Retrieves all pages from the database.
     * @return array|null
     */
    public function getAllPages() {
        $query = $this->_dbh->executeQuery('SELECT
            `title`, `slug`
            FROM pages;
        ');

        return $query->getResults();
    }


    /**
     * Gets the data for the page specified by the argument. Argument may be
     * either an id or a URL slug.
     * @param  string $page
     * @return array|null
     */
    public function getPage($page) {
        // $page will always be a string, and so we use it as the slug in our
        // query. However, if it can successfully be cast to an integer, we
        // can also include it in our query as an id.
        $slug = $page;
        $id = 0;

        if (is_int((int) $page)) {
            $id = (int) $page;
        }

        // Prepare a statement, bind values to the placeholders, and execute.
        $query = $this->_dbh->prepareStatement('SELECT
            `id`, `title`, `description`, `contents`, `menu_display`, `slug`
            FROM pages
            WHERE `slug` = :slug OR `id` = :id
            LIMIT 1;'
        );
        $query->bind(Array(
            'slug' => Array($page, PDO::PARAM_STR),
            'id'   => Array($id, PDO::PARAM_INT)
        ));
        $query->executePreparedStatement();

        // Get and return the results, if possible.
        $results = $query->getResults();
        // Do something with the error?
        $error = $this->_dbh->getErrorMessage();

        if ($results && is_null($error)) {
            return $results[0];
        }

        // Return null, otherwise.
        return null;
    }


    /**
     * Updates the page with the specified slug. Returns true on success, false
     * on failure.
     * @param  string $slug
     * @param  string $title
     * @param  string $description
     * @param  string $contents
     * @param  string $display
     * @return boolean
     */
    public function updatePage($slug, $title, $description, $contents, $display) {
        // If the title has changed, we'll need to generate a new URL slug, too.
        $newSlug = $this->_slugify($title);

        // Prepare, bind values to, and execute a query.
        $query = $this->_dbh->prepareStatement('UPDATE pages
            SET `title` = :title, `slug` = :newSlug, `description` = :description, `contents` = :contents, `menu_display` = :display
            WHERE `slug` = :oldSlug;
        ');
        $query->bind(Array(
            'title'       => Array($title, PDO::PARAM_STR),
            'description' => Array($description, PDO::PARAM_STR),
            'oldSlug'     => Array($slug, PDO::PARAM_STR),
            'newSlug'     => Array($newSlug, PDO::PARAM_STR),
            'contents'    => Array($contents, PDO::PARAM_STR),
            'display'     => Array($display, PDO::PARAM_INT)
        ));
        $query->executePreparedStatement();

        // Return depending on the success of the query.
        $result = $this->_dbh->getResults();
        $error  = $this->_dbh->getErrorMessage();
        return (is_null($result) && is_null($error));
    }
}