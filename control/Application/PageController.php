<?php


// Use the public-facing namespace.
namespace Application;


// Require parent class.
require_once(__DIR__ . '/../Controller.php');


/**
 * A controller for displaying project pages.
 */
class PageController extends \Controller {


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
    public function displayAllPages($params, $request, $view) {
        // Acquie any necessary data from the database.
        $settingsModel = $this->_models->get('settings');
        $pagesModel = $this->_models->get('page');
        $pages = $pagesModel->getNavigationPages();
        $pageList = $pagesModel->getAllPages();

        // Render the view.
        $view->render('pages', Array(
            'title'     => $settingsModel->get('title'),
            'baseHref'  => $this->_getBaseHref(),
            'metaDesc'  => 'This blog\'s pages.',
            'pages'     => $pages,
            'pageTitle' => 'Pages',
            'pageList'  => $pageList,
            'colophon'  => $settingsModel->get('colophon')
        ));
    }


    /**
     * Displays a specific page.
     * @param  array $params  Any URL parameters.
     * @param  array $request The raw $_GET request.
     * @param  View  $view    A View object to render with.
     */
    public function displayOnePage($params, $request, $view) {
        // Access any necessary data from the database.
        $settingsModel = $this->_models->get('settings');
        $pageModel = $this->_models->get('page');
        $pages = $pageModel->getNavigationPages();
        $pageDetails = $pageModel->getPage($params['page']);

        // If no information for the specified page can be found in the
        // database, render an error page.
        if (is_null($pageDetails)) {
            return $view->render('error', Array(
                'title' => $settingsModel->get('title'),
                'baseHref'  => $this->_getBaseHref(),
                'metaDesc' => 'Page not found.',
                'errorMessage' => 'No page with this name was found.'
            ));
        }

        // Render the view.
        $view->render('page', Array(
            'title'        => $settingsModel->get('title'),
            'baseHref'     => $this->_getBaseHref(),
            'pageTitle'    => $pageDetails['title'],
            'metaDesc'     => $pageDetails['description'],
            'pageContents' => $pageDetails['contents'],
            'pages'        => $pages,
            'colophon'     => $settingsModel->get('colophon')
        ));
    }
}