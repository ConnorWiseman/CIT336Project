<?php


final class ModelCollection {
    /**
     * An array of registered model objects. These instances are shared between
     * all application controllers.
     * @var array
     */
    private $_registeredModels = Array();

    /**
     * Retrieves a model object with the specified name if it exists. Returns
     * null if no model with a matching name can be found.
     * @param  string $model
     * @return Object|null
     */
    public function get($model) {
        if (array_key_exists($model, $this->_registeredModels)) {
            return $this->_registeredModels[$model];
        }

        return null;
    }

    public function getAll() {
        return $this->_registeredModels;
    }


    public function registerModel($key, $model) {
        // Only register the model if it hasn't been registered previously.
        if (!array_key_exists($key, $this->_registeredModels)) {
            $this->_registeredModels[$key] = $model;
        }

        return $this;
    }


    /**
     * Registers an associative array of model object instances with the
     * DatabaseHandler instance so they can be easily accessed by any
     * controller objects that might want them.
     * @param  Array  $models
     * @return $this
     */
    public function registerModels(Array $models) {
        foreach ($models as $key => $value) {
            $this->registerModel($key, $value);
        }

        return $this;
    }
}