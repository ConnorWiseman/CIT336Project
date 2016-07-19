<?php


// Use the public-facing namespace.
namespace Application;


// Require parent class.
require_once(__DIR__ . '/../Controller.php');


/**
 * A controller for displaying error pages.
 */
class ErrorController extends \Controller {


    /**
     * Calls the parent object constructor.
     * @param ModelCollection $models
     */
    public function __construct(\ModelCollection $models) {
        parent::__construct($models);
    }


    /**
     * Displays an error page.
     * @param  array $params  Any URL parameters. Unused, in this case.
     * @param  array $request The raw $_GET request.
     * @param  View  $view    A View object to render with.
     */
    public function displayErrorPage($params, $request, $view) {
        // Access any necessary data from the database.
        $settingsModel = $this->_models->get('settings');

        // Render the view.
        $view->render('error', Array(
            'title' => $settingsModel->get('title'),
            'baseHref'  => $this->_getBaseHref(),
            'pageTitle' => 'Error',
            'metaDesc' => 'An error has occurred.',
            'errorMessage' => 'Sorry, something somewhere went wrong. We\'re looking into it, promise!'
        ));
    }
}