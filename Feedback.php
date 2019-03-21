<?php
/*
The MIT License (MIT)

Copyright (c) 2016 by the University of Alberta

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated 
documentation files (the "Software"), to deal in the Software without restriction, including without limitation the 
rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit 
persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE 
WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR 
COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR 
OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
*/

/**
 * This file contains the Component\Feedback class
 */

class Feedback extends \Phalcon\Mvc\User\Component
{
    /** @var array $messages The stored messages */
    public static $messages = [];

    /** @var string $session_key    Contains the $_SESSION key under which flashed messages will be stored */
    private static $session_key = 'feedback';

    /**
     * Used to determine if a particular type & namespace of message exists.  Passing nothing
     * checks if ANY message exists
     *
     * @param string $selector **[OPTIONAL]** The type of message to check. If provided, must be in the format: TYPE[(.|!)NAMESPACE] _(ie: error.user)_
     * @return boolean Whether messages of the passed type & namespace exist.
     */
    public static function has(string $selector = null)
    {
        return (count(self::getMessage($selector)) > 0);
    }

    /**
     * Stores the messages in $_SESSION
     *
     * @sets array $_SESSION
     */
    public static function flash()
    {
        if (session_status() != PHP_SESSION_ACTIVE) {
            throw new \Exception('Feedback cannot be flashed because session has not been started');
        } else  {
            self::getSessionObject()->set(self::$session_key, serialize(self::$messages));
        }
    }

    /**
     * Used to retrieve flashed messages
     *
     * Retrieves flashed messages from $_SESSION. Overwrites self::$messages
     *
     * @param boolean $flush    Whether or not to remove the flashed messages from $_SESSION.  Defaults to true
     *
     * @sets self::$messages
     */
    public static function setToFlashed($flush = true)
    {
        $Session = self::getSessionObject();

        if ($Session->has(self::$session_key)) {
            self::$messages = unserialize($Session->get(self::$session_key));
        }

        if ($flush) {
            $Session->remove(self::$session_key);
        }
    }

    /**
     *
     * Imports text from an array of \Phalcon\Mvc\Message objects
     *
     * @param array $Model A model that has messages
     * @param string $type **[OPTIONAL]** The type of message to import as.  Can be namespaced.  Defaults to `error`
     * @uses self::setMessage() to set each message
     */
    public static function importMessages($Model,$type = 'error')
    {
        foreach ($Model->getMessages() as $message) {
            self::setMessage($type, $message);
        }
    }


    /**
     *
     * Sets a new message.
     *
     * @param string $type      The type of message.  Must be in the format: TYPE[.NAMESPACE]
     * @param string $message   The actual message
     * @uses self::$messages
     */
    private static function setMessage($type, $message)
    {
        $type = strtolower($type);

        if (!isset(self::$messages[$type])) {
            self::$messages[$type] = [];
        }

        self::$messages[$type][] = $message;
    }


    /**
     * Retrieve messages.  Not called directly but used by `get([selectory])` and `get[Type](namespace)` calls
     *
     * @param string $selector The selector of message to retrieve.  Leave empty to retrieve all messages.
     *
     * ####Selector examples
     * * `error`: retrieve all "error" messages
     * * `error.email`: retrieve all "error" messages with the "email" namespace
     * * `.email`: retrieve all messages with the "email" namespace
     * * `error!email`: retrieve all "error" messages that aren't in the "email" namespace
     * * `!email`: retrieve all messages that aren't in the "email" namespace
     *
     * @return array The requested messages
     */
    private static function getMessage($selector = null)
    {

        /* If no selector is passed, return all messages */
        if ($selector == null) {
            return self::combine(self::$messages);
        }

        /* Determine the message type and namespace */
        $selector = strtolower($selector);

        $type      = '';
        $namespace = null;

        if (strpos($selector,'.') !== false || strpos($selector,'!') !== false) {
            list($type, $namespace) = preg_split('/[\!\.]/',$selector);
        } else {
            $type = $selector;
            $namespace = null;
        }

        /* Find all messages of the passed type */
        $filtered = (strlen($type))
                        ?   array_filter(self::$messages,
                                function ($key) use ($type) {
                                    return (strpos($key, $type) !== false);
                                },
                                ARRAY_FILTER_USE_KEY
                            )
                        :   self::$messages;



        /* If necessary, filter further to get just those of the passed namespace */
        if($namespace !== null) {
            $ofType = $filtered;
            $ofNamespace = array_filter($filtered,
                                function ($key) use ($namespace) {
                                	if($namespace == '*' && strpos($key,'.') !== false) {
                                		return true;
                                	}
                                	else {
                                    	return (strpos($key, $namespace) !== false);
                                    }
                                },
                                ARRAY_FILTER_USE_KEY
            );

            /* If necessary, invert the selection to those messages of the right $type, that are NOT in the passed $namespace */
            $filtered = (strpos($selector,'!') !== false)
                            ? array_diff_key($ofType, $ofNamespace)
                            : $ofNamespace;
        }
        return self::combine($filtered);
    }

    /**
     * Combines messages that may have been stored under different keys, into one array
     * @param  array $messages A subsection of `self::$messages`
     * @return array All string messages in `$messages`, combined into 1 array
     */
    private static function combine($messages)
    {
        $combined = [];
        foreach ($messages as $key=>$messages) {
            $combined = array_merge($combined, $messages);
        }

        return $combined;
    }

    /**
     * Magic method enables arbitrary static method calls
     *
     * @param string    $type   The name of the method being called
     * @param mixed[]   $params The parameters passed to the magic method
     * @return mixed[]  If setting, returns nothing.  If getting, returns an array of strings
     */
    public static function __callStatic($type, $params)
    {
        return self::callBody($type, $params);
    }

    /**
     * Magic method enables arbitrary non-static method calls
     *
     * @param string    $type   The name of the method being called
     * @param mixed[]   $params The parameters passed to the magic method
     * @return mixed    If setting, returns nothing.  If getting, returns an array of strings
     */
    public function __call($type, $params)
    {
        return self::callBody($type, $params);
    }

    /**
     * The logic of the magic call methods.
     * Retrieves a message if a get*() method is called.  Sets a message if a set*() method is called.
     *
     * @param  string $type The name of the method being called
     * @param  array $params The parameters of the method being called
     */
    private static function callBody($type, $params)
    {
        # Check if we're retrieving a value
        if (strpos($type,'get') === 0){
            // If just get() was called, pass along the selector
            if($type === 'get'){
                $type = $params[0];
            }
            // Otherwise, format the selector using part of the called method name
            else{
                $type = substr($type, 3);
                if (count($params) !== 0) {
                    if ($params[0][0] === '!' || $params[0][0] === '.') {
                        $type .= $params[0];
                    } else {
                        $type .= '.'.$params[0];
                    }
                }
            }

            return self::getMessage($type);
        }

        # Otherwise we're setting a value
        if (count($params) == 2) {
            $message = $params[1];
            $type    .= '.'.$params[0];
        } else {
            $message = $params[0];
        }
        self::setMessage($type, $message);
    }



    /**
     * Helper function to simplify retrieval of the Phalcon Session object
     */
    private static function getSessionObject()
    {
        return \Phalcon\DI::getDefault()->getSession();
    }
}
