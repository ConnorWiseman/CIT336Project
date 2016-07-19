<?php


/**
 * A base controller class. I'm assuming we'll need a collection of models in
 * every controller, and a handful of utility functions we might want to make
 * accessible in descended classes.
 */
class Controller {


    /**
     * A collection of models.
     * @var ModelCollection
     */
    protected $_models;


    protected function _getBaseHref() {
        return $this->_getPath() . '/';
    }


    /**
     * Gets the path of the currently executing script. Important to know if
     * you're going to be performing header redirects and the app is installed
     * in a subdirectory.
     * @return string
     */
    protected function _getPath() {
        $path = explode('/', getenv('SCRIPT_NAME'));
        array_pop($path);
        $path = implode('/', $path);
        return $path;
    }


    /**
     * Formats a string so that line breaks are replaced by paragraph and line
     * break tags. Used when displaying the contents of a post from the
     * database, or an author's biography. Ideally we'd want some kind of HTML
     * or Markdown parser here instead, but this is the best this project's
     * going to be getting.
     * @param  string $string A string to be formatted.
     * @return string
     * {@link http://stackoverflow.com/a/14762548/2301088}
     */
    protected function _paragraph($string) {
        // Normalize carriage returns and line feeds. PHP's SANITIZE filters
        // replace these with regular HTML entities, so perform replacement on
        // both raw characters and HTML entities just in case.
        $string = str_replace("\r\n", "\n", $string);
        $string = str_replace("&#13;&#10;", "\n", $string);
        $string = str_replace("\r", "\n", $string);
        $string = str_replace("&#13;", "\n", $string);
        $string = preg_replace("/\n{2,}/", "\n\n", $string);

        // Replace multiple line breaks with paragraphs and single line breaks
        // with... well, line breaks.
        $string = preg_replace('/\n(\s*\n)+/', "</p><p>", $string);
        $string = preg_replace('/\n/', '<br>', $string);
        $string = '<p>' . $string . '</p>';

        // Return the result.
        return $string;
    }


    /**
     * Redirects to a specific location.
     * @param  string $location
     */
    protected function _redirect($location) {
        $settingsModel = $this->_models->get('settings');

        if ($settingsModel->get('pretty_links')) {
            // Split the URL by the query string delimiter, ?.
            $location = explode('?', $location)[1];

            // Parse the query string into an array.
            $locationParts = Array();
            parse_str($location, $locationParts);

            // Reunite the bits and pieces, joined by forward slashes.
            $location = '/' . implode('/', $locationParts);
        }

        // Redirect and then exit.
        header("Location: {$this->_getPath()}{$location}");
        exit();
    }


    /**
     * Initializes the instance's list of models.
     * @param ModelCollection $models
     */
    public function __construct(ModelCollection $models) {
        $this->_models = $models;
    }
}