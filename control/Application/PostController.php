<?php


// Use the public-facing namespace.
namespace Application;


// Require parent class.
require_once(__DIR__ . '/../Controller.php');


/**
 * A controller for displaying posts.
 */
class PostController extends \Controller {


    /**
     * Calls the parent object constructor.
     * @param ModelCollection $models
     */
    public function __construct(\ModelCollection $models) {
        parent::__construct($models);
    }


    /**
     * Displays a list of all pages.
     * @param  array $params  Any URL parameters. Unused, in this case.
     * @param  array $request The raw $_GET request.
     * @param  View  $view    A View object to render with.
     */
    public function displayAllPosts($params, $request, $view) {
        $settingsModel = $this->_models->get('settings');
        $pageModel = $this->_models->get('page');
        $postModel = $this->_models->get('post');

        $pages = $pageModel->getNavigationPages();
        $postList = $postModel->getAllPosts();

        $view->render('posts-index', Array(
            'title' => $settingsModel->get('title'),
            'baseHref'  => $this->_getBaseHref(),
            'metaDesc' => 'This blog\'s pages.',
            'pages' => $pages,
            'postList' => $postList,
            'colophon' => $settingsModel->get('colophon')
        ));
    }


    /**
     * Used to display paginated posts.
     * @param  array $params  Any URL parameters.
     * @param  array $request The raw $_GET request. Not used, in this case.
     * @param  View  $view    A View object to render with.
     */
    public function displayPaginatedPosts($params, $request, $view) {
        // Determine which page of posts to display.
        $page = 1;
        if (isset($params['page'])) {
            $page = $params['page'];
        }

        // Acquire any necessary data from the database.
        $settingsModel = $this->_models->get('settings');
        $postModel = $this->_models->get('post');
        $pageModel = $this->_models->get('page');
        $pages = $pageModel->getNavigationPages();
        $totalPosts = $postModel->count();
        $postsPerPage = $settingsModel->get('posts_per_page');

        // Calculate values used in pagination.
        $totalPages = ceil($totalPosts / $postsPerPage);
        $offset = ($page - 1)  * $postsPerPage;

        // Acquire and format the posts to be displayed in the view.
        $postsToDisplay = $postModel->getPostsByOffset($offset, $postsPerPage);
        if (count($postsToDisplay)) {
            foreach ($postsToDisplay as $index => $post) {
                $postsToDisplay[$index]['contents'] = $this->_paragraph($postsToDisplay[$index]['contents']);
            }
        }

        // Set our previous and next pagination link values.
        $prevLink = null;
        if ($page > 1) {
            $prevLink = $page - 1;
        }
        $nextLink = null;
        if ($offset + $postsPerPage < $totalPosts) {
            $nextLink = $page + 1;
        }

        // Render the view.
        $view->render('posts', Array(
            'title' => $settingsModel->get('title'),
            'baseHref'  => $this->_getBaseHref(),
            'pageTitle' => 'Posts',
            'metaDesc' => "Posts on {$settingsModel->get('title')}",
            'pages' => $pages,
            'prevLink' => $prevLink,
            'nextLink' => $nextLink,
            'postList' => $postsToDisplay,
            'colophon' => $settingsModel->get('colophon')
        ));
    }


    /**
     * Displays a specific page.
     * @param  array $params  Any URL parameters.
     * @param  array $request The raw $_GET request.
     * @param  View  $view    A View object to render with.
     */
    public function displayOnePost($params, $request, $view) {
        // Access any necessary data from the database.
        $settingsModel = $this->_models->get('settings');
        $postModel = $this->_models->get('post');
        $pageModel = $this->_models->get('page');
        $pages = $pageModel->getNavigationPages();
        $postDetails = $postModel->getPost($params['post']);

        // If no information for the specified page can be found in the
        // database, render an error page.
        if (is_null($postDetails)) {
            return $view->render('error', Array(
                'title' => $settingsModel->get('title'),
                'baseHref'  => $this->_getBaseHref(),
                'metaDesc' => 'Post not found.',
                'errorMessage' => 'The specified post could not be found.'
            ));
        }

        // Render the view.
        $view->render('post', Array(
            'title' => $settingsModel->get('title'),
            'baseHref'  => $this->_getBaseHref(),
            'pageTitle' => $postDetails['title'],
            'metaDesc' => $postDetails['description'],
            'pubDate' => $postDetails['date'],
            'postAuthor' => $postDetails['name'],
            'postAuthorSlug' => $postDetails['authorSlug'],
            'postContents' => $this->_paragraph($postDetails['contents']),
            'pages' => $pages,
            'colophon' => $settingsModel->get('colophon')
        ));
    }
}