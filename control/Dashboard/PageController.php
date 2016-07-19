<?php


// Use the dashboard namespace.
namespace Dashboard;


// Require parent class.
require_once(__DIR__ . '/../Controller.php');


/**
 * A controller for displaying the application's dashboard.
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
     * Displays the delete page form.
     * @param  array $params  Any URL parameters.
     * @param  array $request The raw $_GET request. Unused, in this case.
     * @param  View  $view    A View object to render with.
     */
    public function displayDeletePageForm($params, $request, $view) {
        // Ensure the user is authorized to access the dashboard.
        $sessionModel = $this->_models->get('session');
        if (!$sessionModel->get('author_id')) {
            return $this->_redirect('/?action=dashboard');
        }

        // Acquire any necessary data from the database.
        $settingsModel = $this->_models->get('settings');

        // Check for error messages.
        $errors = null;
        if (isset($params['errors'])) {
            $errors = $params['errors'];
        }

        // Render the view.
        $view->render('dashboard/delete-page-form', Array(
            'title'     => $settingsModel->get('title'),
            'baseHref'  => $this->_getBaseHref(),
            'pageTitle' => 'Delete Page',
            'formSlug'  => $params['page'],
            'errors'    => $errors,
            'authorId'  => $sessionModel->get('author_id'),
            'authToken' => $sessionModel->get('auth_token')
        ));
    }


    /**
     * Displays the create/edit page form.
     * @param  array $params  Any URL parameters.
     * @param  array $request The raw $_GET request. Unused, in this case.
     * @param  View  $view    A View object to render with.
     */
    public function displayPageForm($params, $request, $view) {
        // Ensure the user is authorized to access the dashboard.
        $sessionModel = $this->_models->get('session');
        if (!$sessionModel->get('author_id')) {
            return $this->_redirect('/?action=dashboard');
        }

        // Check for errors passed back by the form submiion.
        $errors = null;
        if (isset($params['errors'])) {
            $errors = $params['errors'];
        }

        // Also check for success messages.
        $message = null;
        if (isset($params['message'])) {
            $message = $params['message'];
        }

        // Acquire any necessary data from the database.
        $settingsModel = $this->_models->get('settings');
        $pagesModel = $this->_models->get('page');

        // Set some variables to be rendered in the view.
        $formTitle = null;
        $formSlug = null;
        $formDescription = null;
        $formDisplay = null;
        $formContents = null;
        $pageTitle = 'New Page';

        // If the parameters object contains anything, update those variables.
        if (isset($params['page'])) {
            $page = $pagesModel->getPage($params['page']);
            $pageTitle = 'Edit Page';
            $formTitle = $page['title'];
            $formSlug = $params['page'];
            $formDescription = $page['description'];
            $formDisplay = $page['menu_display'];
            $formContents = $page['contents'];
        }

        // Render the view.
        $view->render('dashboard/page-form', Array(
            'title'           => $settingsModel->get('title'),
            'baseHref'        => $this->_getBaseHref(),
            'pageTitle'       => $pageTitle,
            'errors'          => $errors,
            'message'         => $message,
            'formTitle'       => $formTitle,
            'formSlug'        => $formSlug,
            'formDescription' => $formDescription,
            'formDisplay'     => $formDisplay,
            'formContents'    => $formContents,
            'authorId'        => $sessionModel->get('author_id'),
            'authToken'       => $sessionModel->get('auth_token')
        ));
    }


    /**
     * Displays the page manager.
     * @param  array $params  Any URL parameters.
     * @param  array $request The raw $_GET request. Unused, in this case.
     * @param  View  $view    A View object to render with.
     */
    public function displayPageManager($params, $request, $view) {
        // Ensure the user is authorized to access the dashboard.
        $sessionModel = $this->_models->get('session');
        if (!$sessionModel->get('author_id')) {
            return $this->_redirect('/?action=dashboard');
        }

        // Acquire any data from the database.
        $settingsModel = $this->_models->get('settings');
        $pagesModel = $this->_models->get('page');
        $pages = $pagesModel->getAllPages();

        // Render the view.
        $view->render('dashboard/pages', Array(
            'title'     => $settingsModel->get('title'),
            'baseHref'  => $this->_getBaseHref(),
            'pageTitle' => 'Manage Pages',
            'pages'     => $pages,
            'authorId'  => $sessionModel->get('author_id'),
            'authToken' => $sessionModel->get('auth_token')
        ));
    }


    /**
     * Handles requests POSTed through the delete page form.
     * @param  array $params  Any URL parameters.
     * @param  array $request The raw $_POST request.
     * @param  View  $view    A View object to render with.
     */
    public function submitDeletePageForm($params, $request, $view) {
        // Ensure the user is authorized to access the dashboard.
        $sessionModel = $this->_models->get('session');
        if (!$sessionModel->get('author_id')) {
            return $this->_redirect('/?action=dashboard');
        }

        // Sanitize the auth token. Shouldn't be necessary, but just in case.
        $authToken = filter_var($request['auth_token'], FILTER_SANITIZE_STRING);

        // If the auth token doesn't match, set an error and redirect the user
        // to the sign out form view.
        if (!$sessionModel->checkAuthToken($authToken)) {
            $params['errors'] = Array(
                Array('message' => 'Auth token mismatch. Please try again.')
            );
            return $this->displayDeletePageForm($params, $request, $view);
        }

        // Acquire any data from the database.
        $pageModel = $this->_models->get('page');

        // If we can't delete the page, which shouldn't happen, display an error.
        if (!$pageModel->deletePage($params['page'])) {
            $params['errors'] = Array(
                Array('message' => 'Could not delete page.')
            );
            return $this->displayDeletePageForm($params, $request, $view);
        }

        // Renew the session's auth_token.
        $sessionModel->renewAuthToken();

        // Redirect to the page manager view. We opt for a redirect as opposed
        // to displaying the same form, because it wouldn't make sense to show
        // a form for a page that has already been deleted.
        return $this->_redirect('/?action=dashboard&form=pages');
    }


    /**
     * Handles requests POSTed through the edit page form.
     * @param  array $params  Any URL parameters.
     * @param  array $request The raw $_POST request.
     * @param  View  $view    A View object to render with.
     */
    public function submitEditPageForm($params, $request, $view) {
        // Ensure the user is authorized to access the dashboard.
        $sessionModel = $this->_models->get('session');
        if (!$sessionModel->get('author_id')) {
            return $this->_redirect('/?action=dashboard');
        }

        // Sanitize the auth token. Shouldn't be necessary, but just in case.
        $authToken = filter_var($request['auth_token'], FILTER_SANITIZE_STRING);

        // If the auth token doesn't match, set an error and redirect the user
        // to the sign out form view.
        if (!$sessionModel->checkAuthToken($authToken)) {
            $params['errors'] = Array(
                Array('message' => 'Auth token mismatch. Please try again.')
            );
            return $this->displayPageForm($params, $request, $view);
        }

        // Set up an array to hold all potential errors.
        $params['errors'] = Array();

        // Acquire any data from the database.
        $settingsModel = $this->_models->get('settings');
        $pageModel = $this->_models->get('page');

        // Sanitize input before storing it in the database.
        $slug = $params['page'];
        $title = filter_var($request['title'], FILTER_SANITIZE_SPECIAL_CHARS);
        $description = filter_var($request['description'], FILTER_SANITIZE_SPECIAL_CHARS);
        // We deliberately don't sanitize the contents, so the user can write their
        // own HTML on these pages. Obviously unsafe if they try something cute
        // like </textarea>, but at this point, they're only attacking themselves.
        $contents = $request['contents'];
        $shouldDisplay = isset($request['display']);
        $display = 0;
        if ($shouldDisplay) {
            $display = 1;
        }

        // Handle error cases.
        if (empty($title)) {
            array_push($params['errors'], Array(
                'message' => 'Title must not be blank.'
            ));
        }
        if (empty($description)) {
            array_push($params['errors'], Array(
                'message' => 'Description must not be blank.'
            ));
        }
        if (empty($contents)) {
            array_push($params['errors'], Array(
                'message' => 'Contents must not be blank.'
            ));
        }
        if (count($params['errors'])) {
            return $this->displayPageForm($params, $request, $view);
        }

        // Get the id of the post being edited.
        $id = $pageModel->getPage($slug)['id'];

        // If the page could not be updated, display an error message.
        if (!$pageModel->updatePage($slug, $title, $description, $contents, $display)) {
            $params['errors'] = Array(
                Array('message' => 'Could not update page.')
            );
            return $this->displayPageForm($params, $request, $view);
        }

        // Set the post param to the slug of the edited post, in case it was
        // changed.
        $newSlug = $pageModel->getPage($id)['slug'];
        $params['page'] = $newSlug;

        // Set a success message.
        $params['message'] = 'Page updated.';

        // Redirect back to the edit page form.
        return $this->displayPageForm($params, $request, $view);
    }


    /**
     * Handles requests POSTed through the create page form.
     * @param  array $params  Any URL parameters.
     * @param  array $request The raw $_POST request.
     * @param  View  $view    A View object to render with.
     */
    public function submitPageForm($params, $request, $view) {
        // Ensure the user is authorized to access the dashboard.
        $sessionModel = $this->_models->get('session');
        if (!$sessionModel->get('author_id')) {
            return $this->_redirect('/?action=dashboard');
        }

        // Sanitize the auth token. Shouldn't be necessary, but just in case.
        $authToken = filter_var($request['auth_token'], FILTER_SANITIZE_STRING);

        // If the auth token doesn't match, set an error and redirect the user
        // to the sign out form view.
        if (!$sessionModel->checkAuthToken($authToken)) {
            $params['errors'] = Array(
                'message' => 'Auth token mismatch. Please try again.'
            );
            return $this->displayPageForm($params, $request, $view);
        }

        // Set up an array to hold all potential errors.
        $params['errors'] = Array();

        // Acquire any data from the database.
        $pageModel = $this->_models->get('page');

        // Sanitize or otherwise prepare data for insertion.
        $title = filter_var($request['title'], FILTER_SANITIZE_SPECIAL_CHARS);
        $description = filter_var($request['description'], FILTER_SANITIZE_SPECIAL_CHARS);
        // We deliberately don't sanitize the contents, so the user can write their
        // own HTML on these pages. Obviously unsafe if they try something cute
        // like </textarea>, but at this point, they're only attacking themselves.
        $contents = $request['contents'];
        $display = 0;
        if (isset($request['display'])) {
            $display = 1;
        }


        // Handle error cases.
        if (empty($title)) {
            array_push($params['errors'], Array(
                'message' => 'Title must not be blank.'
            ));
        }
        if (empty($description)) {
            array_push($params['errors'], Array(
                'message' => 'Description must not be blank.'
            ));
        }
        if (empty($contents)) {
            array_push($params['errors'], Array(
                'message' => 'Contents must not be blank.'
            ));
        }
        if (count($params['errors'])) {
            return $this->displayPageForm($params, $request, $view);
        }

        // If we can't create the page, which shouldn't happen, display an error.
        if (!$pageModel->createPage($title, $description, $contents, $display)) {
            array_push($params['errors'], Array(
                'message' => 'Could not create page.'
            ));
            return $this->displayPageForm($params, $request, $view);
        }

        // Renew the session's auth_token.
        $sessionModel->renewAuthToken();

        // Redirect to the page manager view. We opt for a redirect as opposed
        // to displaying the same form, because it wouldn't make sense to show
        // a form for a page that has already been created.
        return $this->_redirect('/?action=dashboard&form=pages');
    }
}