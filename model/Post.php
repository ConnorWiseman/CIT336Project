<?php


// Require parent class.
require_once('Model.php');


/**
 * A model for retrieving application settings from the database. Nothing
 * special.
 */
final class Post extends Model {


    /**
     * The query used to create the model's associated database table.
     * @var string
     */
    protected $_createQuery = 'CREATE TABLE IF NOT EXISTS posts (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `title` VARCHAR(255) NOT NULL,
        `slug` VARCHAR(191) NOT NULL,
        `description` VARCHAR(255) NOT NULL,
        `contents` TEXT NOT NULL,
        `author_id` INT UNSIGNED NOT NULL,
        `date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY(`id`),
        CONSTRAINT `slug` UNIQUE(`slug`),
        FOREIGN KEY(`author_id`) REFERENCES authors(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=dynamic;';


    /**
     * Defers to the parent constructor.
     * @param DatabaseHandler $dbh
     */
    public function __construct(DatabaseHandler $dbh) {
        parent::__construct($dbh);
    }


    /**
     * Counts the total number of posts in the database. Used for pagination.
     * @return integer
     */
    public function count() {
        $query = $this->_dbh->executeQuery('SELECT COUNT(*) FROM posts;');
        $results = $query->getResults();

        // Cast the results to an integer, then return.
        return (int) $results[0]['COUNT(*)'];
    }


    public function getPostsByOffset($offset, $limit) {
        $this->_dbh->prepareStatement('SELECT
            posts.`slug` AS `postSlug`,
            posts.`title`,
            posts.`description`,
            LEFT(posts.`contents`, 500) AS `contents`,
            DATE_FORMAT(`date`, \'%l&#58;%i%p, %M %D, %Y\') AS `formattedDate`,
            authors.`name`,
            authors.`slug` AS `authorSlug`
            FROM posts
            LEFT JOIN authors
                ON posts.`author_id` = authors.`id`
            ORDER BY posts.`date` DESC
            LIMIT :limit
            OFFSET :offset;
        ');
        $this->_dbh->bind(Array(
            'limit' => Array($limit, PDO::PARAM_INT),
            'offset' => Array($offset, PDO::PARAM_INT)
        ));
        $this->_dbh->executePreparedStatement();

        $results = $this->_dbh->getResults();
        $error   = $this->_dbh->getErrorMessage();

        if ($results && is_null($error)) {
            return $results;
        }

        return null;
    }


    /**
     * Creates a new post.
     * @param  string $title
     * @param  string $description
     * @param  string $contents
     * @param  string $author_id
     * @return boolean
     */
    public function createPost($title, $description, $contents, $author_id) {
        // Create a URL slug from the post title.
        $slug = $this->_slugify($title);

        // Prepare, bind values to, and execute a query.
        $query = $this->_dbh->prepareStatement('INSERT INTO posts
            (`title`, `slug`, `description`, `contents`, `author_id`)
            VALUES
            (:title, :slug, :description, :contents, :author_id);
        ');
        $query->bind(Array(
            'title' => Array($title, PDO::PARAM_STR),
            'slug' => Array($slug, PDO::PARAM_STR),
            'description' => Array($description, PDO::PARAM_STR),
            'contents' => Array($contents, PDO::PARAM_STR),
            'author_id' => Array($author_id, PDO::PARAM_INT)
        ));
        $query->executePreparedStatement();

        // Return depending on the result of the query.
        $results = $query->getResults();
        $error = $query->getErrorMessage();
        return (is_null($results) && is_null($error));
    }


    /**
     * Deletes a post with the specified slug.
     * @param  string $slug
     * @return boolean
     */
    public function deletePost($slug) {
        $this->_dbh->prepareStatement('DELETE FROM posts
            WHERE `slug` = :slug
            LIMIT 1;
        ');
        $this->_dbh->bind(Array(
            'slug' => Array($slug, PDO::PARAM_STR)
        ));
        $this->_dbh->executePreparedStatement();

        $results = $this->_dbh->getResults();
        $error   = $this->_dbh->getErrorMessage();
        var_dump($error);
        return (is_null($results) && is_null($error));
    }


    /**
     * Retrieves a post with the specified URL slug.
     * @param  string $post
     * @return array|null
     */
    public function getPost($post) {
        // $post will always be a string, and so we use it as the slug in our
        // query. However, if it can successfully be cast to an integer, we
        // can also include it in our query as an id.
        $slug = $post;
        $id = 0;

        if (is_int((int) $post)) {
            $id = (int) $post;
        }

        $query = $this->_dbh->prepareStatement('SELECT
                posts.`id`,
                posts.`slug` AS postSlug,
                posts.`title`,
                posts.`description`,
                posts.`contents`,
                posts.`date`,
                authors.`name`,
                authors.`slug` AS authorSlug
            FROM posts
            LEFT JOIN authors
                ON posts.`author_id` = authors.`id`
            WHERE posts.`slug` = :slug
                OR posts.`id` = :id
            LIMIT 1;
        ');
        $query->bind(Array(
            'slug' => Array($slug, PDO::PARAM_STR),
            'id'   => Array($id, PDO::PARAM_INT)
        ));
        $query->executePreparedStatement();

        $results = $query->getResults();
        $error = $query->getErrorMessage();

        if (is_null($error)) {
            return $results[0];
        }

        return null;
    }


    /**
     * Retrieves all the posts made by an author with a specific author_id.
     * @param  string $author_id
     * @return array|null
     */
    public function getPostsByAuthor($author_id) {
        // Prepare, bind values to, and execute a query.
        // Apologies for the weirdness with that date format in some code
        // editors, but the wrapper I wrote around PDO doesn't like additional
        // colons inside queries.
        $query = $this->_dbh->prepareStatement('SELECT
            `title`, `slug`, `description`, LEFT(`contents`, 500) AS `contents`, DATE_FORMAT(`date`, \'%l&#58;%i%p, %M %D, %Y\') AS `formattedDate` FROM posts
            WHERE author_id = :author_id
            ORDER BY date DESC;
        ');
        $query->bind(Array(
            'author_id' => Array($author_id, PDO::PARAM_INT)
        ));
        $query->executePreparedStatement();

        $results = $query->getResults();
        $error = $query->getErrorMessage();

        // Don't check the results; it's possible for an author to have no posts.
        if (is_null($error)) {
            return $results;
        }

        return null;
    }


    /**
     * Updates the post with a specified URL slug.
     * @param  string $slug
     * @param  string $title
     * @param  string $description
     * @param  string $contents
     * @return boolean
     */
    public function updatePost($slug, $title, $description, $contents) {
        // Create a new URL slug based on the post title.
        $newSlug = $this->_slugify($title);

        // Prepare, bind values to, and execute a query.
        $query = $this->_dbh->prepareStatement('UPDATE posts
            SET `title` = :title, `slug` = :newSlug, `description` = :description, `contents` = :contents
            WHERE `slug` = :oldSlug;
        ');
        $query->bind(Array(
            'title'       => Array($title, PDO::PARAM_STR),
            'description' => Array($description, PDO::PARAM_STR),
            'oldSlug'     => Array($slug, PDO::PARAM_STR),
            'newSlug'     => Array($newSlug, PDO::PARAM_STR),
            'contents'    => Array($contents, PDO::PARAM_STR)
        ));
        $query->executePreparedStatement();

        // Return depending on the result of the query.
        $result = $this->_dbh->getResults();
        $error = $this->_dbh->getErrorMessage();
        return (is_null($result) && is_null($error));
    }
}