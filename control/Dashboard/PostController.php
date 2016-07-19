<?php


// Use the dashboard namespace.
namespace Dashboard;


// Require parent class.
require_once(__DIR__ . '/../Controller.php');


/**
 * A controller for displaying the application's dashboard.
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
     * Displays the delete post form.
     * @param  array $params  Any URL parameters.
     * @param  array $request The raw $_GET request. Unused, in this case.
     * @param  View  $view    A View object to render with.
     */
    public function displayDeletePostForm($params, $request, $view) {
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
        $view->render('dashboard/delete-post-form', Array(
            'title'     => $settingsModel->get('title'),
            'baseHref'  => $this->_getBaseHref(),
            'pageTitle' => 'Delete Post',
            'formSlug'  => $params['post'],
            'errors'    => $errors,
            'authorId'  => $sessionModel->get('author_id'),
            'authToken' => $sessionModel->get('auth_token')
        ));
    }


    /**
     * Displays the create/edit post form.
     * @param  array $params  Any URL parameters.
     * @param  array $request The raw $_GET request. Unused, in this case.
     * @param  View  $view    A View object to render with.
     */
    public function displayPostForm($params, $request, $view) {
        // Ensure the user is authorized to access the dashboard.
        $sessionModel = $this->_models->get('session');
        if (!$sessionModel->get('author_id')) {
            return $this->_redirect('/?action=dashboard');
        }

        // Acquire any necessary data from the database.
        $settingsModel = $this->_models->get('settings');
        $postModel = $this->_models->get('post');

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

        $formTitle = null;
        $formSlug = null;
        $formDescription = null;
        $formContents = null;
        $pageTitle = 'New Post';

        if (isset($params['post'])) {
            $post = $postModel->getPost($params['post']);
            $pageTitle = 'Edit Post';
            $formTitle = filter_var($post['title'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
            $formSlug = $params['post'];
            $formDescription = filter_var($post['description'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
            $formContents = filter_var($post['contents'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
        }

        // Render the view.
        $view->render('dashboard/post-form', Array(
            'title'           => $settingsModel->get('title'),
            'baseHref'        => $this->_getBaseHref(),
            'pageTitle'       => $pageTitle,
            'errors'          => $errors,
            'message'         => $message,
            'formTitle'       => $formTitle,
            'formSlug'        => $formSlug,
            'formDescription' => $formDescription,
            'formContents'    => $formContents,
            'authorId'        => $sessionModel->get('author_id'),
            'authToken'       => $sessionModel->get('auth_token')
        ));
    }


    /**
     * Displays the post manager view.
     * @param  array $params  Any URL parameters.
     * @param  array $request The raw $_GET request. Unused, in this case.
     * @param  View  $view    A View object to render with.
     */
    public function displayPostManager($params, $request, $view) {
        // Ensure the user is authorized to access the dashboard.
        $sessionModel = $this->_models->get('session');
        if (!$sessionModel->get('author_id')) {
            return $this->_redirect('/?action=dashboard');
        }

        // Acquire any necessary data from the database.
        $settingsModel = $this->_models->get('settings');
        $postsModel = $this->_models->get('post');
        $posts = $postsModel->getPostsByAuthor($sessionModel->get('author_id'));

        // Render the view.
        $view->render('dashboard/post-manager', Array(
            'title'     => $settingsModel->get('title'),
            'baseHref'  => $this->_getBaseHref(),
            'pageTitle' => 'Manage Posts',
            'posts'     => $posts,
            'authorId'  => $sessionModel->get('author_id'),
            'authKey'   => $sessionModel->get('auth_token')
        ));
    }


    /**
     * Handles requests POSTed to the delete post form.
     * @param  array $params  Any URL parameters.
     * @param  array $request The raw $_POST request.
     * @param  View  $view    A View object to render with.
     */
    public function submitDeletePostForm($params, $request, $view) {
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
            return $this->displayDeletePostForm($params, $request, $view);
        }

        // Acquire any data from the database.
        $postModel = $this->_models->get('post');

        // Sanitize the slug to use when deleting..
        $slug = filter_var($params['post'], FILTER_SANITIZE_STRING);

        // If we can't delete the page, which shouldn't happen, display an error.
        if (!$postModel->deletePost($slug)) {
            $params['errors'] = Array(
                Array('message' => 'Could not delete post.')
            );
            return $this->displayDeletePostForm($params, $request, $view);
        }

        // Renew the session's auth_token.
        $sessionModel->renewAuthToken();

        // Redirect to the post manager view. We opt for a redirect as opposed
        // to displaying the same form, because it wouldn't make sense to show
        // a form for a post that has already been deleted.
        return $this->_redirect('/?action=dashboard&form=posts');
    }


    /**
     * Handles requests POSTed to the edit post form.
     * @param  array $params  Any URL parameters.
     * @param  array $request The raw $_POST request.
     * @param  View  $view    A View object to render with.
     */
    public function submitEditPostForm($params, $request, $view) {
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
            return $this->displayPostForm($params, $request, $view);
        }

        // Set up an array to hold all potential errors.
        $params['errors'] = Array();

        // Acquire any data from the database.
        $settingsModel = $this->_models->get('settings');
        $postModel = $this->_models->get('post');

        // Filter inputs.
        $slug = $params['post']; // Everything provided to $params as part of the route is filtered already
        $title = filter_var($request['title'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
        $description = filter_var($request['description'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
        $contents = filter_var($request['contents'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);

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
        $id = $postModel->getPost($slug)['id'];

        // If the post could not be updated, display an error.
        if (!$postModel->updatePost($slug, $title, $description, $contents)) {
            $params['errors'] = Array(
                Array('message' => 'Could not update post.')
            );
            return $this->displayPostForm($params, $request, $view);
        }

        // Set the post param to the slug of the edited post, in case it was
        // changed.
        $newSlug = $postModel->getPost($id)['postSlug'];
        $params['post'] = $newSlug;

        // Set a success message.
        $params['message'] = 'Post updated.';

        // Redirect back to the post form.
        return $this->displayPostForm($params, $request, $view);
    }


    /**
     * Handles requests POSTed to the create post form.
     * @param  array $params  Any URL parameters.
     * @param  array $request The raw $_POST request.
     * @param  View  $view    A View object to render with.
     */
    public function submitPostForm($params, $request, $view) {
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
            return $this->displayPostForm($params, $request, $view);
        }

        // Set up an array to hold all potential errors.
        $params['errors'] = Array();

        // Acquire any data from the database.
        $postModel = $this->_models->get('post');
        $author_id = $sessionModel->get('author_id');

        // Filter inputs.
        $title = filter_var($request['title'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
        $description = filter_var($request['description'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
        $contents = filter_var($request['contents'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);

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

        // If we can't create the post, which shouldn't happen, display an error.
        if (!$postModel->createPost($title, $description, $contents, $author_id)) {
            $params['errors'] = Array(
                Array('message' => 'Could not create post.')
            );
            return $this->displayPostForm($params, $request, $view);
        }

        // Renew the session's auth_token.
        $sessionModel->renewAuthToken();

        // Redirect to the post manager view. We opt for a redirect as opposed
        // to displaying the same form, because it wouldn't make sense to show
        // a form for a post that has already been created.
        return $this->_redirect('/?action=dashboard&form=posts');
    }
}