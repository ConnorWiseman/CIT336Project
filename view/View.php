<?php


// Require Partial subclass.
require_once('Partial.php');


/**
 * A templating class, used to represent files to be rendered as views. Performs
 * key-value replacement when rendering, which is intended to help keep PHP code
 * out of the raw view files. Supports basic if/if else and foreach loops in
 * template files to further segregate markup from PHP.
 *
 * The resulting HTML output of the render method is indented strangely, but
 * other than that this works pretty well.
 */
class View {


    /**
     * The current template's filename. Set in the constructor.
     * @var string
     */
    private $_filename = null;


    /**
     * When the file the template is based on is read, its contents will be
     * stored here. This helps with partials intended to be used more than once
     * in the same view; they don't have to be read from the filesystem multiple
     * times.
     * @var string
     */
    private $_fileContents;


    /**
     * An array of registered partials.
     * @var array
     */
    private $_partials = Array();


    /**
     * A settings model instance. Used to determine what kind of URL schema to
     * use in links generated by the application.
     * @var Settings
     */
    private $_settings = null;


    /**
     * An array of key-value pairs to be rendered onto a template. Set either
     * in the constructor or by the render method.
     * @var array
     */
    private $_values = Array();


    /**
     * The subdirectory where view templates are located.
     * @var string
     */
    protected $_subdirectory = 'templates';


    /**
     * Parses a string for foreach blocks, and performs content replacement
     * accordingly. Called by _parseTemplate method.
     * @param  string $string A string to parse.
     * @param  string $values Values to use for comparison and replacement.
     * @return string
     */
    private function _parseForEachBlocks($string, Array $values) {
        // Acquire all if and foreach blocks in the template.
        // Thank heaven for Regex101.
        $forEachRegex = '/(?:{{#foreach:)([a-zA-Z_-]*?)}}(?:\s)*?(.*?)?(?:\s)*?(?:{{#endforeach}})/s';
        $blocks = Array();
        preg_match_all($forEachRegex, $string, $blocks);

        // Alias the indexed arrays in $blocks, so they're easier to read.
        $matches = $blocks[0]; // Complete substring to be replaced.
        $items   = $blocks[1]; // Key to check against values array.
        $forEach = $blocks[2]; // Content used in each foreach loop.

        // Iterate over each foreach block, as an index and a name. The name is
        // what gets used in the templates, and corresponds to an array of
        // arrays being passed in to the view object for rendering.
        foreach($items as $itemIndex => $itemName) {
            // If no array key with the given name was passed in to render,
            // then skip the rest of the loop.
            if (!array_key_exists($itemName, $values)) {
                $string = str_replace($matches[$itemIndex], '', $string);
                continue;
            }

            // Alias the actual items being passed in to be iterated over and
            // create a fragments array to hold the swapped content.
            $actualItems = $values[$itemName];
            $fragments = Array();

            // Iterate over each of the data items.
            foreach($actualItems as $currentItem) {
                // Create a new fragment matching the original string. We do
                // this here so the "parent" string isn't overwritten later.
                $fragment = $forEach[$itemIndex];

                // Iterage over everything in the current data item.
                foreach($currentItem as $key => $value) {
                    // Alias the key, then perform replacement.
                    $key = "{{{$key}}}";
                    $fragment = str_replace($key, $value, $fragment);
                }

                array_push($fragments, $fragment);
            }

            // Join each of the fragments into a single string.
            $result = implode("\r\n", $fragments);

            // Replace the original substring with the joined fragments.
            $string = str_replace($matches[$itemIndex], $result, $string);
        }

        // Return the parsed string.
        return $string;
    }


    /**
     * Parses a string for if and if/else blocks, and performs content
     * replacement accordingly. Called by _parseTemplate method.
     * @param  string $string A string to parse.
     * @param  string $values Values to use for comparison and replacement.
     * @return string
     */
    private function _parseIfBlocks($string, Array $values) {
        // Acquire all if and if/else blocks in the template.
        // Thank heaven for Regex101.
        $ifRegex = '/(?:{{#if:)([a-zA-Z_-]*?)(?:}})(?:\s)*?([\s\S]*?)(?:(?:\s)*?{{#else}}(?:\s)*?([\s\S]*?))?(?:\s)*?(?:{{#endif}})/';
        $blocks = Array();
        preg_match_all($ifRegex, $string, $blocks);

        // Alias the indexed arrays in $blocks, so they're easier to read.
        $matches    = $blocks[0]; // Complete substring to be replaced.
        $conditions = $blocks[1]; // Key to check against values array.
        $if         = $blocks[2]; // Content used if condition is true.
        $else       = $blocks[3]; // Content used if condition is not true.

        // For each condition, working from an index and a condition...
        foreach($conditions as $index => $condition) {

            // If the condition exists in the array of values and the condition
            // is truthy, replace the if block with the if-conditional content.
            if (array_key_exists($condition, $values) && $values[$condition]) {
                $string = str_replace($matches[$index], $if[$index], $string);
                continue;
            }

            // Otherwise, if else content exists at the same index, replace the
            // if block with the else-conditional content.
            if ($else[$index]) {
                $string = str_replace($matches[$index], $else[$index], $string);
                continue;
            }

            // If neither case is true, remove the if-else block entirely.
            $string = str_replace($matches[$index], '', $string);
        }

        // Return the parsed string.
        return $string;
    }


    /**
     * Parses the application-defined links in a view template or partial,
     * replacing them with the appropriate equivalent based on the pretty URL
     * setting defined in the database.
     * @param  string $string A string to parse.
     * @return string
     */
    private function _parseLinks($string) {
        // Acquire all application-defined links in the template.
        // Thank heaven for Regex101.
        $linksRegex = '/(?:{{#link:)(.*?)(?:}})/';
        $links = Array();
        preg_match_all($linksRegex, $string, $links);

        // Alias the indexed arrays in $links, so they're easier to read.
        $keys = $links[0];
        $urls = $links[1];

        // For each link URL, working from an index and a URL...
        foreach($urls as $index => $url) {
            // If the pretty links setting is enabled, perform some extra logic.
            if ($this->_settings && $this->_settings->get('pretty_links')) {
                // Split the URL by the query string delimiter, ?.
                $url = explode('?', $url)[1];

                // Parse the query string into an array.
                $urlParts = Array();
                parse_str($url, $urlParts);

                // Reunite the bits and pieces, joined by forward slashes.
                $url = './' . implode('/', $urlParts);
            }

            // Replace the key with the appropriate URL.
            $string = str_replace($keys[$index], $url, $string);
        }

        return $string;
    }


    /**
     * Parses all partial templates in a given string. Called by _parseTemplate
     * method.
     * @param  string $string A string to parse.
     * @return string
     */
    private function _parsePartials($string) {
        // Acquire all partials in the template.
        // Thank heaven for Regex101.
        $partialRegex = '/(?:{{>)([a-zA-Z_\-\/]*?)(?:}})/';
        $partials = Array();
        preg_match_all($partialRegex, $string, $partials);

        // Alias the indexed arrays in $partials, so they're easier to read.
        $keys    = $partials[0]; // Complete substring to be replaced.
        $names   = $partials[1]; // Name of the partial to load.

        // For each partial name, working from an index and a name...
        foreach($names as $index => $name) {
            // If the partial has already been used in the current template,
            // pull it out of the array of registered partials instead of
            // reading it from disk again.
            $partial;
            if (array_key_exists($name, $this->_partials)) {
                $partial = $this->_partials[$name];
            }
            else {
                $partial = new Partial($name);
                $this->_partials[$name] = $partial;
            }

            // Perform string replacement on the partial.
            $string = str_replace(
                $keys[$index],
                $partial->getRenderString(),
                $string
            );
        }

        // Return the parsed string.
        return $string;
    }


    /**
     * Sequentially parses a given string with the various parsing functions.
     * Called by _renderString method.
     * @param  string $string A string to parse.
     * @param  array  $values Values to use when parsing.
     * @return string
     */
    private function _parseTemplate($string, Array $values) {
        // Always parse the contents of partials, so that partials can
        // recursively reference other partials.
        $string = $this->_parsePartials($string);

        // Only perform parsing and replacement if we're rendering a view.
        // Partials should "inherit" their values from their parent template,
        // even if they have their own set defined.
        if (get_class($this) === 'View') {
            $string = $this->_parseIfBlocks($string, $values);
            $string = $this->_parseForEachBlocks($string, $values);
            $string = $this->_parseValues($string, $values);
            $string = $this->_parseLinks($string);
            $string = $this->_removeExtras($string);
            $string = $this->_replaceWhitespace($string);
        }

        // Return the completely parsed template.
        return $string;
    }


    /**
     * Parses all template placeholders in a given string. Called by
     * _parseTemplate and _parseForEachBlocks methods.
     * @param  string $string A string to parse.
     * @param  string $values Values to use for replacement.
     * @return string
     */
    private function _parseValues($string, Array $values) {
        // For each key-value pair in $values...
        foreach($values as $key => $value) {
            // If the current value is an object or an array, ignore it.
            if (is_object($value) || is_array($value)) {
                continue;
            }

            // Perform basic string replacement on $string.
            if (array_key_exists($key, $values)) {
                $key = "{{{$key}}}";
                $string = str_replace($key, $value, $string);
            }
        }

        // Return the parsed string.
        return $string;
    }


    /**
     * Opens and returns the contents of a specified file. Called by
     * _renderString method.
     * @param  string $file The name of the file to open.
     * @return string The contents of the file.
     * @throws Exception If the file is not found.
     */
    private function _readFile($file) {
        // If the file has already been read, don't read it again- just return
        // the contents from last time.
        if (isset($this->_fileContents)) {
            return $this->_fileContents;
        }

        // If the file name doesn't include the .template file extension used
        // by the View and Partial classes, tack it into the end.
        if (!strpos($file, '.template')) {
            $file = "{$file}.template";
        }

        // Set the path of the file relative to the View/Partial class files,
        // so we don't have to worry about where we might include them from.
        $relativePath = dirname(__FILE__) . "/{$this->_subdirectory}/{$file}";

        // Read the file in. If the file couldn't be read, throw an error.
        $fileContents = file_get_contents($relativePath);
        if (!$fileContents) {
            $class = get_class($this);
            throw new Exception("{$class} {$file} not found.");
        }

        // Set this instance's file contents to avoid re-reading them in future.
        $this->_fileContents = $fileContents;

        // Return the file contents.
        return $fileContents;
    }


    /**
     * Removes any extra view formatting from a specified string. Called by
     * _parseTemplate method.
     * @param  string $string A string to remove extra view formatting from.
     * @return string
     */
    private function _removeExtras($string) {
        return preg_replace('/({{(?:\>|#)?[a-z:]*?}})/', '', $string);
    }


    /**
     * Given a specified string, replace instances of more than two line break
     * with only two. Not strictly necessary; it helps keep the HTML source from
     * looking like Rexburg in winter. Called by _parseTemplate method.
     * @param  string $string A string to replace excess whitespace in.
     * @return string
     */
    private function _replaceWhitespace($string) {
        return preg_replace('/(\r?\n){2,}/', "\r\n\r\n", $string);
    }



    /**
     * Renders a specified template into a string. Called by render method.
     * @return string
     */
    protected function _renderString() {
        // Read the template file.
        $string = $this->_readFile($this->_filename);

        // Substitute any values that need replacement.
        $string = $this->_parseTemplate($string, $this->_values);

        // Remove excess whitespace from the beginning and end, then return.
        $string = trim($string);

        return $string;
    }


    /**
     * Initializes the instance's filename and values.
     * @param string [$filename] The filename of the View. Optional.
     * @param array  [$values]   An array of key-value pairs. Optional.
     */
    public function __construct($filename = null, Array $values = Array()) {
        $this->_filename = $filename;
        $this->_values = $values;
    }


    /**
     * Renders the specified template using a set of specified values.
     * @param string [$filename] The filename of the View. Optional.
     * @param array  [$values]   An array of key-value pairs. Optional.
     */
    public function render($filename = null, Array $values = Array()) {
        // If another $filename has been specified and it's a string, swap it
        // out for whatever the instance currently has its file name set to.
        if (!is_null($filename) && is_string($filename)) {
            $this->_filename = $filename;
        }
        // Otherwise, if the filename is an array, update the contents of
        // $values so we're not stuck with a blank array. This is ugly but it
        // gets the job done and lets us use some lovely optional function
        // parameters.
        else if (is_array($filename)) {
            $values = $filename;
        }

        // If no filename is specified, throw an error.
        if (!$this->_filename) {
            throw new Exception('No template to render has been specified.');
        }

        // If the $values argument has anything in it, exchange the instance's
        // values array for the new $values argument.
        if (count($values)) {
            $this->_values = $values;
        }

        // Render the template.
        ob_start();
        echo $this->_renderString();
        ob_end_flush();
    }


    /**
     * Provides access to the application's Settings model. Used to determine
     * which type of URL schema to use in links generated by the application.
     * @param  Settings $settings
     */
    public function useSettings(Settings $settings) {
        $this->_settings = $settings;
    }
}