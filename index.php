<?php


/**
 * This blog application was written for my CIT336 course, and requires the
 * following PHP extensions (bundled with PHP since 5.3 or so) enabled to
 * function as expected:
 *
 * php_intl
 * php_openssl
 * php_pdo_mysql
 *
 * These are enabled the same way the php_pdo_mysql extension was enabled in
 * the beginning of the course- through "PHP config" in your host's cPanel.
 */


// Set the timezone to use.
date_default_timezone_set('America/Denver');


// Set the content type header, just in case.
header('Content-Type: text/html; charset=utf-8');


// Require model class definitions.
require_once('model/Author.php');
require_once('model/DatabaseHandler.php');
require_once('model/ModelCollection.php');
require_once('model/Page.php');
require_once('model/Post.php');
require_once('model/Session.php');
require_once('model/Settings.php');


// Require view class definitions.
require_once('view/View.php');


// Require controller class definitions.
require_once('control/Application/AuthorController.php');
require_once('control/Application/ErrorController.php');
require_once('control/Application/IndexController.php');
require_once('control/Application/PageController.php');
require_once('control/Application/PostController.php');
require_once('control/Dashboard/AuthorController.php');
require_once('control/Dashboard/IndexController.php');
require_once('control/Dashboard/PageController.php');
require_once('control/Dashboard/PostController.php');
require_once('control/SessionController.php');
require_once('control/Router.php');


// Connect to the database.
$dbh = new DatabaseHandler();


// Create model object instances. Pass the active database connection to each.
$author   = new Author($dbh);
$page     = new Page($dbh);
$post     = new Post($dbh);
$session  = new Session($dbh);
$settings = new Settings($dbh);


// Register all models with a collection of models so they're accessible from
// within each of the controller objects as a complete set. You never know
// which piece of data will come in handy or where.
$models = new ModelCollection();
$models->registerModels(Array(
    'author'   => $author,
    'page'     => $page,
    'post'     => $post,
    'session'  => $session,
    'settings' => $settings
));


// Create view object instances. We really only need the one, but it will
// want access to the application settings to determine how to render links
// embedded in view templates and partial view templates. Make sure to pass
// the appropriate model in.
$view = new View();
$view->useSettings($settings);


// Create controller object instances. Ideally these would all be static
// classes or something, but PHP doesn't support those out of the box and I
// couldn't finagle a decent way to pass all the models to every controller
// without creating instances of each. Win some, lose some.
//
// In my case, controller objects are just sets of related functions that
// perform some kind of logic, potentially access data from the database using
// models, and then use a predefined view object to render.
$authors = new Application\AuthorController($models);
$error   = new Application\ErrorController($models);
$index   = new Application\IndexController($models);
$pages   = new Application\PageController($models);
$posts   = new Application\PostController($models);
$dashboardIndex   = new Dashboard\IndexController($models);
$dashboardAuthors = new Dashboard\AuthorController($models);
$dashboardPages   = new Dashboard\PageController($models);
$dashboardPosts   = new Dashboard\PostController($models);


// The session controller is special. It's only ever used within the dashboard,
// but I chose not to put it inside the same namespace. Instantiating it will
// automatically begin a new session (session_start) and handle everything else
// without serving any content to the client. Might as well have it active on
// every view; at least that way implementing anything to do with the session
// on the publicly exposed pages is a cinch.
$session = new SessionController($models);


// Create a new Router object.
$router = new Router();


// Assign controller routes to the Router instance.
// We'll assign "publicly" exposed routes first.
$router->get('/', Array($index, 'displayIndex'));
$router->get('/author/:author', Array($authors, 'displayOneAuthor'));
$router->get('/page/:page', Array($pages, 'displayOnePage'));
$router->get('/pages', Array($pages, 'displayAllPages'));
$router->get('/post/:post', Array($posts, 'displayOnePost'));
$router->get('/posts', Array($posts, 'displayPaginatedPosts'));
$router->get('/posts/:page', Array($posts, 'displayPaginatedPosts'));


// The "private" dashboard routes expect authorization, so I grouped them
// together below based on how related their functionality is. The dashboard
// index page should render the settings form; this is by design, not a typo.
//
// Ideally a proper router should implement some kind of middleware stack so
// you don't have to keep manually checking authorization separately in each
// route callback, but I never got around to implementing it, so there's some
// duplicate code at the beginning of all of the functions below.
//
// Note that not every route is intended to accept GET requests. There are
// POST routes in the dashboard, too. The two different methods are accounted
// for by the Router object automatically, which is nice for me!
$router->get('/dashboard', Array($dashboardIndex, 'displaySettingsForm'));
$router->get('/dashboard/settings', Array($dashboardIndex, 'displaySettingsForm'));
$router->post('/dashboard/settings', Array($dashboardIndex, 'submitSettingsForm'));
$router->get('/dashboard/signin', Array($dashboardIndex, 'displaySignInForm'));
$router->post('/dashboard/signin', Array($dashboardIndex, 'submitSignInForm'));
$router->get('/dashboard/signout', Array($dashboardIndex, 'displaySignOutForm'));
$router->post('/dashboard/signout', Array($dashboardIndex, 'submitSignOutForm'));
$router->get('/dashboard/deletepost/:post', Array($dashboardPosts, 'displayDeletePostForm'));
$router->post('/dashboard/deletepost/:post', Array($dashboardPosts, 'submitDeletePostForm'));
$router->get('/dashboard/post', Array($dashboardPosts, 'displayPostForm'));
$router->post('/dashboard/post', Array($dashboardPosts, 'submitPostForm'));
$router->get('/dashboard/post/:post', Array($dashboardPosts, 'displayPostForm'));
$router->post('/dashboard/post/:post', Array($dashboardPosts, 'submitEditPostForm'));
$router->get('/dashboard/posts', Array($dashboardPosts, 'displayPostManager'));
$router->get('/dashboard/author', Array($dashboardAuthors, 'displayAuthorForm'));
$router->post('/dashboard/author', Array($dashboardAuthors, 'submitAuthorForm'));
$router->get('/dashboard/password', Array($dashboardAuthors, 'displayPasswordForm'));
$router->post('/dashboard/password', Array($dashboardAuthors, 'submitPasswordForm'));
$router->get('/dashboard/deletepage/:page', Array($dashboardPages, 'displayDeletePageForm'));
$router->post('/dashboard/deletepage/:page', Array($dashboardPages, 'submitDeletePageForm'));
$router->get('/dashboard/page', Array($dashboardPages, 'displayPageForm'));
$router->post('/dashboard/page', Array($dashboardPages, 'submitPageForm'));
$router->get('/dashboard/page/:page', Array($dashboardPages, 'displayPageForm'));
$router->post('/dashboard/page/:page', Array($dashboardPages, 'submitEditPageForm'));
$router->get('/dashboard/pages', Array($dashboardPages, 'displayPageManager'));


// Set up a route to handle errors- undefined routes and so on.
$router->error(Array($error, 'displayErrorPage'));


// Use our Router instance to handle the current request.
$router->handleCurrentRequest($_GET, $_POST, $view);