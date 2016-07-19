<?php


// Use the public-facing namespace.
namespace Application;


// Require parent class.
require_once(__DIR__ . '/../Controller.php');


/**
 * A controller for displaying project pages.
 */
class AuthorController extends \Controller {


    /**
     * Calls the parent object constructor.
     * @param ModelCollection $models
     */
    public function __construct(\ModelCollection $models) {
        parent::__construct($models);
    }


    /**
     * Displays a specific page.
     * @param  array $params  Any URL parameters.
     * @param  array $request The raw $_GET request.
     * @param  View  $view    A View object to render with.
     */
    public function displayOneAuthor($params, $request, $view) {
        // Access any necessary data from the database.
        $settingsModel = $this->_models->get('settings');
        $pageModel = $this->_models->get('page');
        $postModel = $this->_models->get('post');
        $authorModel = $this->_models->get('author');
        $pages = $pageModel->getNavigationPages();
        $authorDetails = $authorModel->getAuthor($params['author']);

        // If no information for the specified page can be found in the
        // database, render an error page.
        if (is_null($authorDetails)) {
            return $view->render('error', Array(
                'title' => $settingsModel->get('title'),
                'baseHref'  => $this->_getBaseHref(),
                'metaDesc' => 'Author not found.',
                'errorMessage' => 'No author with this name was found.'
            ));
        }

        $postsToDisplay = $postModel->getPostsByAuthor($authorDetails['id']);
        if (count($postsToDisplay)) {
            foreach ($postsToDisplay as $index => $post) {
                $postsToDisplay[$index]['contents'] = $this->_paragraph($postsToDisplay[$index]['contents']);
            }
        }

        // Render the view.
        $view->render('author', Array(
            'title' => $settingsModel->get('title'),
            'baseHref'  => $this->_getBaseHref(),
            'pageTitle' => $authorDetails['name'],
            'metaDesc' => "{$authorDetails['name']}'s author profile",
            'pages' => $pages,
            'biography' => $authorDetails['biography'],
            'postList' => $postsToDisplay,
            'colophon' => $settingsModel->get('colophon')
        ));
    }
}