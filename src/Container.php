<?php

namespace Example;

class Container
{
    /**
     * Collection of stored instances.
     *
     * @var array
     */
    protected $instances = [];

    /**
     * Collection of stored bindings.
     *
     * @var array
     */
    protected $bindings = [];

    /**
     * Register a new instance within the container.
     *
     * @param string $key
     * @param mixed  $value
     * @return $this
     */
    public function instance($key, $value)
    {
        $this->instances[$key] = $value;

        return $this;
    }

    /**
     * Bind a new instance construction blueprint within the container.
     *
     * @param string $key
     * @param mixed  $value
     * @return $this
     */
    public function bind($key, $value)
    {
        $this->bindings[$key] = $value;

        return $this;
    }

    /**
     * Resolve a service instance from the container.
     *
     * @param string $key
     * @return bool|mixed|object
     * @throws \Exception
     */
    public function make($key)
    {
        // If we have an instance stored, return that.
        if (array_key_exists($key, $this->instances)) {
            return $this->instances[$key];
        }

        // If we have a binding, execute it and return the result.
        if (array_key_exists($key, $this->bindings)) {
            $resolver = $this->bindings[$key];
            return $this->instances[$key] = $resolver();
        }

        // Next, try to autoresolve the instance. Return if found.
        if ($instance = $this->autoResolve($key)) {
            return $instance;
        }

        // Otherwise, let the user know that the instance can't be created.
        throw new \Exception('Unable to resolve binding from container.');
    }

    /**
     * Attempt to auto resolve the dependency chain.
     *
     * @param string $key
     * @return bool|object
     * @throws \Exception
     */
    public function autoResolve($key)
    {
        // If the class doesn't exist, just quit early.
        if (!class_exists($key)) {
            return false;
        }

        // Wrap in a reflection class to inspect the class we want to instantiate.
        $reflectionClass = new \ReflectionClass($key);

        // If it can't be instantiated, give up!
        if (!$reflectionClass->isInstantiable()) {
            return false;
        }

        // Check to see if the class has a constructor, if it doesn't then
        // it's probably a simple class, and we can just instantiate it directly.
        if (!$constructor = $reflectionClass->getConstructor()) {
            return new $key;
        }

        // Get an array of constructor parameters.
        $params = $constructor->getParameters();

        // Create a buffer to store resolved parameters.
        $args = [];

        // We'll want to quite on any errors resolving parameters.
        try {

            // Iterate the 'ReflectionParameter' items in the array.
            foreach($params as $param) {

                // Get a string representing the type hinting of the parameter.
                $paramClass = $param->getClass()->getName();

                // Use a recursive call to make() to try and get the parameter.
                // Add it to the collection of resolved parameters.
                $args[] = $this->make($paramClass);
            }
        } catch (\Exception $e) {
            throw new \Exception('Unable to resolve complex dependencies.');
        }

        // Use our array of parameters to instantiate and return the class instance.
        return $reflectionClass->newInstanceArgs($args);
    }
}
