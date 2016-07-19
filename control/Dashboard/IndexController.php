<?php


// Use the dashboard namespace.
namespace Dashboard;


// Require parent class.
require_once(__DIR__ . '/../Controller.php');


/**
 * A controller for displaying and handling POST requests to the dashboard's
 * sign in and sign out forms. Called the "index" because it handles the
 * default view unauthorized users see before they sign in.
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
     * Displays the form used to change application settings.
     * @param  array $params  Any URL parameters.
     * @param  array $request The raw $_GET request.
     * @param  View  $view    A View object to render with.
     */
    public function displaySettingsForm($params, $request, $view) {
        // Ensure the user is authorized to access the dashboard. This one is
        // unique; it must absolutely return a view, not a simple redirect, or
        // attempting to access any view in the dashboard while unauthorized
        // will result in an endless redirect loop and, therefore, a broken
        // application and poor user experience.
        $sessionModel = $this->_models->get('session');
        if (!$sessionModel->get('author_id')) {
            return $this->displaySignInForm($params, $request, $view);
        }

        // Check for errors passed back by the form submiion.
        $errors = null;
        if (isset($params['errors'])) {
            $errors = $params['errors'];
        }

        // Acquire any necessary data from the database.
        $settingsModel = $this->_models->get('settings');

        // If one of these is set, all of them are set. If not, grab the raw
        // values from the settings model object.
        if (isset($params['title'])) {
            $title = $params['title'];
            $description = $params['description'];
            $prettyLinks = $params['pretty_links'];
            $postsPerPage = $params['posts_per_page'];
            $colophon = $params['colophon'];
        }
        else {
            $title = $settingsModel->get('title');
            $description = $settingsModel->get('description');
            $prettyLinks = $settingsModel->get('pretty_links');
            $postsPerPage = $settingsModel->get('posts_per_page');
            $colophon = $settingsModel->get('colophon');
        }

        // Render the view.
        $view->render('dashboard/settings-form', Array(
            'title' => $settingsModel->get('title'),
            'baseHref'  => $this->_getBaseHref(),
            'settingsTitle' => $title,
            'settingsDescription' => $description,
            'settingsPretty' => $prettyLinks,
            'settingsPosts' => $postsPerPage,
            'settingsColophon' => $colophon,
            'pageTitle' => 'Settings',
            'errors' => $errors,
            'authorId' => $sessionModel->get('author_id'),
            'authToken' => $sessionModel->get('auth_token')
        ));
    }


    /**
     * Displays the sign in form.
     * @param  array $params  Any URL parameters. Unused, in this case.
     * @param  array $request The raw $_GET request.
     * @param  View  $view    A View object to render with.
     */
    public function displaySignInForm($params, $request, $view) {
        // Access any necessary data from the database.
        $sessionModel = $this->_models->get('session');
        $settingsModel = $this->_models->get('settings');
        $authorsModel = $this->_models->get('author');

        // In this case, if no users exist in the database we want to adjust
        // the form from the default "sign in" form to a "register" form.
        $buttonLabel = 'Sign In';
        if ($authorsModel->count() === 0) {
            $buttonLabel = 'Register';
        }

        // If any email was passed along to be "autofilled" for the user's
        // convenience, make sure to set it here.
        $email = null;
        if (isset($params['email'])) {
            $email = $params['email'];
        }

        // We do the same thing for any errors passed back by the sign in form
        // submission.
        $errors = null;
        if (isset($params['errors'])) {
            $errors = $params['errors'];
        }

        // Render the view.
        $view->render('dashboard/signin-form', Array(
            'title' => $settingsModel->get('title'),
            'baseHref'  => $this->_getBaseHref(),
            'pageTitle' => 'Dashboard',
            'errors' => $errors,
            'email' => $email,
            'buttonLabel' => $buttonLabel,
            'authToken' => $sessionModel->get('auth_token')
        ));
    }


    /**
     * Displays the sign out form.
     * @param  array $params  Any URL parameters. Unused, in this case.
     * @param  array $request The raw $_GET request.
     * @param  View  $view    A View object to render with.
     */
    public function displaySignOutForm($params, $request, $view) {
        // Ensure the user is authorized to access the dashboard.
        $sessionModel = $this->_models->get('session');
        if (!$sessionModel->get('author_id')) {
            return $this->_redirect('/?action=dashboard');
        }

        // Access any necessary data from the database.
        $settingsModel = $this->_models->get('settings');

        // If there are any errors, make sure we render them in the view.
        $errors = null;
        if (isset($params['errors'])) {
            $errors = $params['errors'];
        }

        // Render the view.
        $view->render('dashboard/signout-form', Array(
            'title' => $settingsModel->get('title'),
            'baseHref'  => $this->_getBaseHref(),
            'pageTitle' => 'Sign Out',
            'errors' => $errors,
            'authToken' => $sessionModel->get('auth_token'),
            'authorId' => $sessionModel->get('author_id')
        ));
    }


    /**
     * Handles requests POSTed to the settings form.
     * @param  array $params  Any URL parameters. Unused, in this case.
     * @param  array $request The raw $_POST request.
     * @param  View  $view    A View object to render with.
     */
    public function submitSettingsForm($params, $request, $view) {
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
            return $this->displaySettingsForm($params, $request, $view);
        }

        // Filter input.
        $title = filter_var($request['title'], FILTER_SANITIZE_SPECIAL_CHARS);
        $description = filter_var($request['description'], FILTER_SANITIZE_SPECIAL_CHARS);
        $prettyLinks = '0';
        $colophon = filter_var($request['colophon'], FILTER_SANITIZE_SPECIAL_CHARS);
        if (array_key_exists('pretty', $request)) {
            $prettyLinks = '1';
        }
        $postsPerPage = filter_var($request['posts_per_page'], FILTER_VALIDATE_INT);

        if (!$postsPerPage) {
            $params['errors'] = Array(
                Array('message' => 'Posts per page must be an integer.')
            );

            // Set the parameters, so the changes the user made are reflected
            // in the settings form.
            $params['title'] = $title;
            $params['description'] = $description;
            $params['pretty_links'] = $prettyLinks;
            $params['postsPerPage'] = $postsPerPage;
            $params['colophon'] = $colophon;

            // Return the settings form view.
            return $this->displaySettingsForm($params, $request, $view);
        }

        // Acquire the settings model.
        $settingsModel = $this->_models->get('settings');

        // This shouldn't ever happen, but just in case...
        if (!$settingsModel->update($title, $description, $prettyLinks, $colophon, $postsPerPage)) {
            $params['errors'] = Array(
                Array('message' => 'Could not update settings.')
            );

            // Set the parameters, so the changes the user made are reflected
            // in the settings form.
            $params['title'] = $title;
            $params['description'] = $description;
            $params['pretty_links'] = $prettyLinks;
            $params['postsPerPage'] = $postsPerPage;
            $params['colophon'] = $colophon;

            // Return the settings form view.
            return $this->displaySettingsForm($params, $request, $view);
        }

        // Redirect back to the settings form.
        return $this->_redirect('/?action=dashboard');
    }


    /**
     * Handles requests POSTed to the sign in form.
     * @param  array $params  Any URL parameters. Unused, in this case.
     * @param  array $request The raw $_POST request.
     * @param  View  $view    A View object to render with. Unused.
     */
    public function submitSignInForm($params, $request, $view) {
        $sessionModel = $this->_models->get('session');

        // Sanitize the auth token. Shouldn't be necessary, but just in case.
        $authToken = filter_var($request['auth_token'], FILTER_SANITIZE_STRING);

        // If the auth token doesn't match, set an error and redirect the user
        // to the sign out form view.
        if (!$sessionModel->checkAuthToken($authToken)) {
            $params['errors'] = Array(
                Array('message' => 'Auth token mismatch. Please try again.')
            );
            return $this->displaySignInForm($params, $request, $view);
        }

        // Validate the data submitted to the form.
        if (!filter_var($request['email'], FILTER_VALIDATE_EMAIL)) {
            $params['errors'] = Array(
                Array('message' => 'You must provide a valid email address.')
            );
            return $this->displaySignInForm($params, $request, $view);
        }

        // Filter the email, but not the password. Passwords are never exposed
        // to the client and are hashed prior to storage in the database, so
        // unless PHP's password_hash function chokes on strange character sets
        // there's no reason to perform filtering on the password.
        $email = filter_var(
            $request['email'],
            FILTER_SANITIZE_FULL_SPECIAL_CHARS
        );
        $password = $request['password'];

        // Access any necessary data from the database.
        $authorsModel = $this->_models->get('author');
        $sessionsModel = $this->_models->get('session');

        // In this case, if no users exist in the database we want to adjust
        // the action from signing in to registering.
        if ($authorsModel->count() === 0) {
            // If we can't successfully create a new user, show the user an
            // error. This shouldn't ever happen, but better safe than sorry.
            if (!$authorsModel->create($email, $password)) {
                $params['errors'] = Array(
                    Array('message' => 'Could not create user.')
                );
                return $this->displaySignInForm($params, $request, $view);
            }
        }

        // If the author's credentials don't check out, prompt the author to
        // sign in again.
        if (!$authorsModel->checkCredentials($email, $password)) {
            $params['errors'] = Array(
                Array('message' => 'Credential mismatch. Please try again.')
            );
            $params['email'] = $email;
            return $this->displaySignInForm($params, $request, $view);
        }

        // If they do, authorize the author's session so they can access the
        // dashboard. Then, redirect them back to the index- this time, the
        // blog settings form.
        $authorDetails = $authorsModel->getByEmail($email);
        $sessionsModel->authorize($authorDetails['id']);
        return $this->_redirect('/?action=dashboard');
    }


    /**
     * Processes POST requests submitted to the sign out form.
     * @param  array $params  Any URL parameters. Unused, in this case.
     * @param  array $request The raw $_POST request.
     * @param  View  $view    A View object to render with.
     */
    public function submitSignOutForm($params, $request, $view) {
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
            return $this->displaySignOutForm($params, $request, $view);
        }

        // Deauthorize the session and redirect the user to the project root.
        $sessionModel->deauthorize();
        return $this->_redirect('./');
    }
}