<?php


// Use the dashboard namespace.
namespace Dashboard;


// Require parent class.
require_once(__DIR__ . '/../Controller.php');


/**
 * A controller for displaying the application's dashboard.
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
     * Displays the author settings form.
     * @param  array $params  Any URL parameters. Unused, in this case.
     * @param  array $request The raw $_GET request. Unused, in this case.
     * @param  View  $view    A View object to render with.
     */
    public function displayAuthorForm($params, $request, $view) {
        // Ensure the user is authorized to access the dashboard.
        $sessionModel = $this->_models->get('session');
        if (!$sessionModel->get('author_id')) {
            return $this->_redirect('/?action=dashboard');
        }

        // Access data to be displayed in the view.
        $settingsModel = $this->_models->get('settings');
        $authorModel = $this->_models->get('author');
        $authorDetails = $authorModel->getById($sessionModel->get('author_id'));

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

        // Render the view.
        $view->render('dashboard/author-form', Array(
            'title'     => $settingsModel->get('title'),
            'pageTitle' => 'Author Details',
            'baseHref'  => $this->_getBaseHref(),
            'errors'    => $errors,
            'message'   => $message,
            'email'     => $authorDetails['email'],
            'name'      => $authorDetails['name'],
            'biography' => $authorDetails['biography'],
            'authorId'  => $sessionModel->get('author_id'),
            'authToken' => $sessionModel->get('auth_token')
        ));
    }


    /**
     * Displays the change password form.
     * @param  array $params  Any URL parameters. Unused, in this case.
     * @param  array $request The raw $_GET request. Unused, in this case.
     * @param  View  $view    A View object to render with.
     */
    public function displayPasswordForm($params, $request, $view) {
        // Ensure the user is authorized to access the dashboard.
        $sessionModel = $this->_models->get('session');
        if (!$sessionModel->get('author_id')) {
            return $this->_redirect('/?action=dashboard');
        }

        // Acquire any needed data from the database.
        $settingsModel = $this->_models->get('settings');

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

        // Render the view.
        $view->render('dashboard/password-form', Array(
            'title'     => $settingsModel->get('title'),
            'pageTitle' => 'Change Password',
            'baseHref'  => $this->_getBaseHref(),
            'errors'    => $errors,
            'message'   => $message,
            'authorId'  => $sessionModel->get('author_id'),
            'authToken' => $sessionModel->get('auth_token')
        ));
    }


    /**
     * Submits the author settings form.
     * @param  array $params  Any URL parameters. Unused, in this case.
     * @param  array $request The raw $_POST request.
     * @param  View  $view    A View object to render with.
     */
    public function submitAuthorForm($params, $request, $view) {
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
            return $this->displayAuthorForm($params, $request, $view);
        }

        // Validate input.
        $email = filter_var($request['email'], FILTER_VALIDATE_EMAIL);
        if (!$email) {
            $params['errors'] = Array(
                Array('message' => 'Email must be a valid email address.')
            );
            // Return the author settings form view.
            return $this->displayAuthorForm($params, $request, $view);
        }

        // Filter input.
        $email = filter_var($email, FILTER_SANITIZE_SPECIAL_CHARS);
        $name = filter_var($request['name'], FILTER_SANITIZE_SPECIAL_CHARS);
        $biography = filter_var($request['biography'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);

        // Acquire the author model.
        $authorModel = $this->_models->get('author');

        // This shouldn't ever happen, but just in case...
        if (!$authorModel->update($sessionModel->get('author_id'), $email, $name, $biography)) {
            $params['errors'] = Array(
                Array('message' => 'Could not update author information.')
            );

            // Return the author settings form view.
            return $this->displayAuthorForm($params, $request, $view);
        }

        // Renew the session's auth_token.
        $sessionModel->renewAuthToken();

        // Set a success message.
        $params['message'] = 'Information successfully updated.';

        // Redirect back to the settings form.
        return $this->displayAuthorForm($params, $request, $view);
    }


    /**
     * Submits the change password form.
     * @param  array $params  Any URL parameters. Unused, in this case.
     * @param  array $request The raw $_POST request.
     * @param  View  $view    A View object to render with.
     */
    public function submitPasswordForm($params, $request, $view) {
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
            return $this->displayPasswordForm($params, $request, $view);
        }

        // There's no need to filter input. Passwords are never printed to the
        // page; ergo, users can put whatever they want into them and they
        // should be safe, assuming PHP's password_hash function doesn't choke
        // on weird character sets.
        $password1 = $request['password1'];
        $password2 = $request['password2'];

        // If the passwords aren't the same, display an error.
        if ($password1 !== $password2) {
            $params['errors'] = Array(
                Array('message' => 'Passwords must match.')
            );

            // Return the change password form view.
            return $this->displayPasswordForm($params, $request, $view);
        }

        // Acquire needed models for database interaction.
        $authorModel = $this->_models->get('author');

        // This shouldn't ever happen, but just in case...
        if (!$authorModel->changePassword($sessionModel->get('author_id'), $password1)) {
            $params['errors'] = Array(
                Array('message' => 'Could not change password.')
            );

            // Return the change password form view.
            return $this->displayPasswordForm($params, $request, $view);
        }

        // Renew the session's auth_token.
        $sessionModel->renewAuthToken();

        // Set a success message.
        $params['message'] = 'Password successfully changed.';

        // Redirect back to the change password form.
        return $this->displayPasswordForm($params, $request, $view);
    }
}