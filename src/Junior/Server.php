<?php

namespace Junior;

use Junior\Serverside\Request;

const ERROR_INVALID_REQUEST  = -32600;
const ERROR_METHOD_NOT_FOUND = -32601;
const ERROR_INVALID_PARAMS   = -32602;
const ERROR_EXCEPTION        = -32099;


class Server
{
    public $exposedInstance, $input;

    /**
     * create new server
     *
     * @param $exposedInstance
     * @throws Serverside\Exception
     */
    public function __construct($exposedInstance)
    {
        if (!is_object($exposedInstance)) {
            throw new Serverside\Exception("Server requires an object");
        }

        $this->exposedInstance = $exposedInstance;
        $this->input           = 'php://input';
    }

    /**
     * check for method existence
     *
     * @param $methodName
     * @return bool
     */
    public function methodExists($methodName)
    {
        return method_exists($this->exposedInstance, $methodName);
    }

    /**
     * attempt to invoke the method with params
     *
     * @param $method
     * @param $params
     * @return mixed
     * @throws Serverside\Exception
     */
    public function invokeMethod($method, $params)
    {
        // for named parameters, convert from object to assoc array
        if (is_object($params)) {
            $array = array();
            foreach ($params as $key => $val) {
                $array[$key] = $val;
            }
            $params = array($array);
        }
        // for no params, pass in empty array
        if ($params === null) {
            $params = array();
        }
        $reflection = new \ReflectionMethod($this->exposedInstance, $method);

        // only allow calls to public functions
        if (!$reflection->isPublic()) {
            throw new Serverside\Exception("Called method is not publically accessible.");
        }

        // enforce correct number of arguments
        $num_required_params = $reflection->getNumberOfRequiredParameters();
        if ($num_required_params > count($params)) {
            throw new Serverside\Exception("Too few parameters passed.");
        }

        return $reflection->invokeArgs($this->exposedInstance, $params);
    }

    /**
     * process json-rpc request
     *
     * @throws Serverside\Exception
     */
    public function process()
    {
        // try to read input
        try {
            $json = file_get_contents($this->input);
        } catch (\Exception $e) {
            $message = "Server unable to read request body.";
            $message .= PHP_EOL . $e->getMessage();
            throw new Serverside\Exception($message);
        }

        // handle communication errors
        if ($json === false) {
            throw new Serverside\Exception("Server unable to read request body.");
        }

        // create request object
        $request = $this->makeRequest($json);

        // set content type to json if not testing
        if (!(defined('ENV') && ENV == 'TEST')) {
            header('Content-type: application/json');
        }

        // handle json parse error and empty batch
        if ($request->errorCode && $request->errorMessage) {
            echo $request->toResponseJSON();

            return;
        }

        // respond with json
        echo $this->handleRequest($request);
    }

    /**
     * create new request (used for test mocking purposes)
     *
     * @param $json
     * @return Request
     */
    public function makeRequest($json)
    {
        return new Request($json);
    }

    /**
     * handle request object / return response json
     *
     * @param Request $request
     * @return null|string
     */
    public function handleRequest($request)
    {
        // recursion for batch
        if ($request->isBatch()) {
            $batch = array();
            foreach ($request->requests as $req) {
                $batch[] = $this->handleRequest($req);
            }
            $responses = implode(',', array_filter($batch, function ($a) {
                return $a !== null;
            }));
            if ($responses != null) {
                return "[{$responses}]";
            } else {
                return null;
            }
        }

        // check validity of request
        if ($request->checkValid()) {
            // check for method existence
            if (!$this->methodExists($request->method)) {
                $request->errorCode    = ERROR_METHOD_NOT_FOUND;
                $request->errorMessage = "Method not found.";

                return $request->toResponseJSON();
            }

            // try to call method with params
            try {
                $response = $this->invokeMethod($request->method, $request->params);
                if (!$request->isNotify()) {
                    $request->result = $response;
                } else {
                    return null;
                }
                // handle exceptions
            } catch (\Exception $e) {
                $request->errorCode    = ERROR_EXCEPTION;
                $request->errorMessage = $e->getMessage();
            }
        }

        // return whatever we got
        return $request->toResponseJSON();
    }

}
