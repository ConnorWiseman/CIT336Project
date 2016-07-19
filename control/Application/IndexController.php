<?php


// Use the public-facing namespace.
namespace Application;


// Require parent class.
require_once(__DIR__ . '/../Controller.php');


/**
 * A controller for displaying the project index.
 */
class IndexController extends \Controller {


    /**
     * Calls the parent object constructor.
     * @param ModelCollection $models
     */
    public function __construct(\ModelCollection $models) {
        parent::__construct($models);
    }


    /**
     * Displays the index.
     * @param  array $params  Any URL parameters. Unused, in this case.
     * @param  array $request The raw $_GET request.
     * @param  View  $view    A View object to render with.
     */
    public function displayIndex($params, $request, $view) {
        // Access any necessary data from the database.
        $settingsModel = $this->_models->get('settings');
        $pageModel = $this->_models->get('page');
        $postModel = $this->_models->get('post');
        $pages = $pageModel->getNavigationPages();
        $postsPerPage = $settingsModel->get('posts_per_page');

        // Acquire and format the posts to be displayed in the view.
        $postsToDisplay = $postModel->getPostsByOffset(0, $postsPerPage);
        if (count($postsToDisplay)) {
            foreach ($postsToDisplay as $index => $post) {
                $postsToDisplay[$index]['contents'] = $this->_paragraph($postsToDisplay[$index]['contents']);
            }
        }

        // Render the view.
        $view->render('index', Array(
            'splashPage' => true,
            'title' => $settingsModel->get('title'),
            'baseHref'  => $this->_getBaseHref(),
            'metaDesc' => $settingsModel->get('description'),
            'description' => $settingsModel->get('description'),
            'pages' => $pages,
            'postList' => $postsToDisplay,
            'colophon' => $settingsModel->get('colophon')
        ));
    }
}