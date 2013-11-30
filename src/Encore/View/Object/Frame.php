<?php

namespace Encore\View\Object;

class Frame extends Object
{
    protected $xml;

    public function bind($name, $event, $callback)
    {
        $event = $this->findEvent($event);

        $objectId = wxXmlResource::Get()->GetXRCID($name);

        if ($objectId === false) {
            throw new \RuntimeException("Object with name '{$name}' was not found");
        }

        if ($callback instanceof \Closure) {
            $callbackId = uniqid();
            $this->closures[$callbackId] = $callback;

            $callback = array($this, "call_{$callbackId}");
        }

        $this->object->Connect($objectId, $event, $callback);

        return $this;
    }

    public function __call($method, $args)
    {
        $closure = str_replace('call_', '', $method);

        if ( ! array_key_exists($closure, $this->closures)) {
            parent::__call($method, $args);
        }

        return $this->closures[$closure]();
    }

    protected function findEvent($event)
    {
        // If the correct constant has been passed over already, use that
        if (defined($event)) return constant($event);

        // Make the event name uppercase
        $eventUpper = strtoupper($event);

        // Try to find the directly from the constant
        if (defined("wxEVT_COMMAND_{$eventUpper}")) return constant("wxEVT_COMMAND_{$eventUpper}");

        // Couldn't find it. Throw an exception
        throw new \RuntimeException("'{$event}' is not a valid event");
    }
}