<?php

class ServerRequestTest extends PHPUnit_Extensions_OutputTestCase {

    public function getEmptyRequest()
    {
        return $this->getMock('Junior\Server\Request',
                               null,
                               array(),
                               '',
                               false);
    }

    public function testNewRequest()
    {
        $json_rpc = '2.0';
        $method = 'testmethod';
        $params = array('foo', 'bar');
        $id = 10;
        $json = "{\"jsonrpc\":\"{$json_rpc}\", \"method\":\"{$method}\", \"params\":[\"{$params[0]}\", \"{$params[1]}\"], \"id\": {$id}}";
        $request = new Junior\Server\Request($json);
        $this->assertEquals($json_rpc, $request->json_rpc);
        $this->assertEquals($method, $request->method);
        $this->assertEquals($params, $request->params);
        $this->assertEquals($id, $request->id);
    }

    public function testNewRequestInvalidRequest()
    {
        $request = new Junior\Server\Request('');
        $this->assertEquals(constant('Junior\Server\JSON_RPC_VERSION'), $request->json_rpc);
        $this->assertEquals(constant('Junior\Server\ERROR_INVALID_REQUEST'), $request->error_code);
        $this->assertEquals("Invalid request.", $request->error_message);

        $request = new Junior\Server\Request('[]');
        $this->assertEquals(constant('Junior\Server\JSON_RPC_VERSION'), $request->json_rpc);
        $this->assertEquals(constant('Junior\Server\ERROR_INVALID_REQUEST'), $request->error_code);
        $this->assertEquals("Invalid request.", $request->error_message);
    }

    public function testNewRequestParseError()
    {
        $request = new Junior\Server\Request('[bad:json::]');
        $this->assertEquals(constant('Junior\Server\JSON_RPC_VERSION'), $request->json_rpc);
        $this->assertEquals(constant('Junior\Server\ERROR_PARSE_ERROR'), $request->error_code);
        $this->assertEquals("Parse error.", $request->error_message);
    }

    public function testNewRequestBatch()
    {
        $json_rpc = '2.0';
        $method = 'testmethod';
        $params = array('foo', 'bar');
        $id = 10;
        $json = "{\"jsonrpc\":\"{$json_rpc}\", \"method\":\"{$method}\", \"params\":[\"{$params[0]}\", \"{$params[1]}\"], \"id\": {$id}}";
        $batch_json = "[$json,$json,$json]";
        $requests = new Junior\Server\Request($batch_json);
        foreach ($requests->requests as $request) {
            $this->assertEquals($json_rpc, $request->json_rpc);
            $this->assertEquals($method, $request->method);
            $this->assertEquals($params, $request->params);
            $this->assertEquals($id, $request->id);
        }
    }

    public function testCheckValidGood()
    {
        $request = $this->getEmptyRequest();
        $request->json_rpc = '2.0';
        $request->method = 'testMethod';
        $request->id = 10;
        $this->assertTrue($request->checkValid());
    }

    public function testCheckValidErrorAlreadySet()
    {
        $request = $this->getEmptyRequest();
        $request->json_rpc = '2.0';
        $request->method = 'testMethod';
        $request->error_code = 10;
        $request->error_message = 'Error!';
        $request->id = 10;
        $this->assertFalse($request->checkValid());
    }

    public function testCheckValidInvalidRequest()
    {
        $error_code = constant('Junior\Server\ERROR_INVALID_REQUEST');
        $error_message = 'Invalid request.';

        $request = $this->getEmptyRequest();
        $request->json_rpc = null;
        $request->method = 'testMethod';
        $request->id = 10;
        $this->assertFalse($request->checkValid());
        $this->assertEquals($error_code, $request->error_code);
        $this->assertEquals($error_message, $request->error_message);

        $request = $this->getEmptyRequest();
        $request->json_rpc = '2.0';
        $request->method = null;
        $request->id = 10;
        $this->assertFalse($request->checkValid());
        $this->assertEquals($error_code, $request->error_code);
        $this->assertEquals($error_message, $request->error_message);

        $request = $this->getEmptyRequest();
        $request->json_rpc = '2.0';
        $request->method = '!!!function';
        $request->id = 10;
        $this->assertFalse($request->checkValid());
        $this->assertEquals($error_code, $request->error_code);
        $this->assertEquals($error_message, $request->error_message);
    }

    public function testCheckValidReservedPrefix()
    {
        $error_code = constant('Junior\Server\ERROR_RESERVED_PREFIX');
        $error_message = 'Illegal method name; Method cannot start with \'rpc.\'';

        $request = $this->getEmptyRequest();
        $request->json_rpc = '2.0';
        $request->method = 'rpc.notvalid';
        $request->id = 10;
        $this->assertFalse($request->checkValid());
        $this->assertEquals($error_code, $request->error_code);
        $this->assertEquals($error_message, $request->error_message);
    }

    public function testCheckValidMismatchedVersion()
    {
        $error_code = constant('Junior\Server\ERROR_MISMATCHED_VERSION');
        $error_message = 'Client/Server JSON-RPC version mismatch; Expected \'2.0\'';

        $request = $this->getEmptyRequest();
        $request->json_rpc = '1.0';
        $request->method = 'method';
        $request->id = 10;
        $this->assertFalse($request->checkValid());
        $this->assertEquals($error_code, $request->error_code);
        $this->assertEquals($error_message, $request->error_message);
    }

    public function testIsBatch()
    {
        $request = $this->getEmptyRequest();
        $request->batch = true;

        $this->assertTrue($request->isBatch());
    }

    public function testIsNotify()
    {
        $request = $this->getEmptyRequest();
        $request->id = null;

        $this->assertTrue($request->isNotify());

        $request->id = 10;

        $this->assertFalse($request->isNotify());
    }

    public function testResponseJSON()
    {
        $request = $this->getEmptyRequest();
        $json_version = constant('Junior\Server\JSON_RPC_VERSION');
        $request->error_code = 10;
        $request->error_message = 'Error!';
        $request->id = 1;

        $json = "{\"jsonrpc\":\"{$json_version}\",\"error\":{\"code\":{$request->error_code},\"message\":\"{$request->error_message}\"},\"id\":{$request->id}}";
        $this->assertEquals($json, $request->toResponseJSON());

        $request->result = 'foo';

        $json = "{\"jsonrpc\":\"{$json_version}\",\"result\":\"{$request->result}\",\"id\":{$request->id}}";
        $this->assertEquals($json, $request->toResponseJSON());
    }

}
?>