<?php


// Require parent class.
require_once('View.php');


/**
 * A partial template, representing a reusable HTML fragment.
 *
 * The only real difference between Partials and Views is the directory where
 * they're located. Views are intended to be used to represent single entities;
 * partials are HTML fragments intended for re-use. In that way, they're
 * comparable to CSS id and class selectors.
 *
 * For this reason, you shouldn't use views where partials would do. If this
 * code changes in the future, things might misbehave.
 */
final class Partial extends View {


    /**
     * The subdirectory where partials are located.
     * @var string
     */
    protected $_subdirectory = 'partials';


    /**
     * Calls the parent object constructor.
     * @param string $filename The filename of the Partial.
     * @param array  [$values] An array of key-value pairs. Optional.
     */
    public function __construct($filename, $values = Array()) {
        parent::__construct($filename, $values);
    }


    /**
     * Returns the partial's render string.
     *
     * Partials are unique in that they're able to flaunt their render string
     * in public. Views aren't meant to do that, because they're not meant to
     * be included inside other templates. The only way to access a view's
     * render string is to- well- render it.
     * @return string
     */
    public function getRenderString() {
        return $this->_renderString();
    }
}