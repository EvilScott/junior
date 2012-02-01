<?php
class ClientTest extends PHPUnit_Framework_TestCase {

    public function setUp()
    {
        $this->fake_uri = vfsStream::url('test');
        $this->fake_file = vfsStream::newFile('test');
        $root = new vfsStreamDirectory('root');
        $root->addChild($this->fake_file);
        vfsStreamWrapper::register();
        vfsStreamWrapper::setRoot($root);
    }

    public function getEmptyRequest()
    {
        $request = $this->getMock('Object', array('getJSON','getArray'));
        $request->expects($this->any())
                ->method('getJSON')
                ->will($this->returnValue(null));
        $request->expects($this->any())
                ->method('getArray')
                ->will($this->returnValue(null));
        return $request;
    }

    public function getEmptyResponse()
    {
        return $this->getMock('Object');
    }

    public function getMockClient($methods = array(), $returns_once = null)
    {
        $client = $this->getMock('Junior\Client\Client',
                                 $methods,
                                 array(),
                                 '',
                                 false);

        if ($returns_once !== null) {
            $client->expects($this->once())
                   ->method('send')
                   ->will($this->returnValue($returns_once));
        }

        return $client;
    }

    public function testSendRequestGoodId()
    {
        $request = $this->getEmptyRequest();
        $request->id = 10;

        $response = $this->getEmptyResponse();
        $response->id = 10;

        $client = $this->getMockClient(array('send'), $response);

        $this->assertEquals($response, $client->sendRequest($request));
    }

    public function testSendRequestBadId()
    {
        $request = $this->getEmptyRequest();
        $request->id = 10;

        $response = $this->getEmptyResponse();
        $response->id = 11;

        $client = $this->getMockClient(array('send'), $response);

        $this->setExpectedException('Junior\Client\Exception');

        $client->sendRequest($request);
    }

    public function testSendNotifyGood()
    {
        $request = $this->getEmptyRequest();
        $request->id = null;

        $client = $this->getMockClient(array('send'), true);

        $this->assertTrue($client->sendNotify($request));
    }

    public function testSendNotifyBad()
    {
        $request = $this->getEmptyRequest();
        $request->id = 10;

        $client = $this->getMockClient(array('send'));
        $client->expects($this->never())->method('send');

        $this->setExpectedException('Junior\Client\Exception');

        $client->sendNotify($request);
    }

    public function testSendBatchGood()
    {
        $requests = array();
        for($i=10;$i<=15;$i++) {
            $request = $this->getEmptyRequest();
            $request->id = $i;
            $requests[] = $request;
        }

        $responses = array();
        for($i=10;$i<=15;$i++) {
            $response = $this->getEmptyResponse();
            $response->id = $i;
            $responses[$i] = $response;
        }

        $client = $this->getMockClient(array('send'), $responses);

        $client->sendBatch($requests);
    }

    public function testSendBatchBadTooFew()
    {
        $requests = array();
        for($i=10;$i<=15;$i++) {
            $request = $this->getEmptyRequest();
            $request->id = $i;
            $requests[] = $request;
        }

        $responses = array();
        for($i=10;$i<=13;$i++) {
            $response = $this->getEmptyResponse();
            $response->id = $i;
            $responses[$i] = $response;
        }

        $client = $this->getMockClient(array('send'), $responses);

        $this->setExpectedException('Junior\Client\Exception');

        $client->sendBatch($requests);
    }

    public function testSendBatchBadTooMany()
    {
        $requests = array();
        for($i=10;$i<=15;$i++) {
            $request = $this->getEmptyRequest();
            $request->id = $i;
            $requests[] = $request;
        }

        $responses = array();
        for($i=10;$i<=17;$i++) {
            $response = $this->getEmptyResponse();
            $response->id = $i;
            $responses[$i] = $response;
        }

        $client = $this->getMockClient(array('send'), $responses);

        $this->setExpectedException('Junior\Client\Exception');

        $client->sendBatch($requests);
    }


    public function testSendBatchNotify()
    {
        $requests = array();
        for($i=10;$i<=15;$i++) {
            $request = $this->getEmptyRequest();
            $request->id = null;
            $requests[] = $request;
        }

        $client = $this->getMockClient(array('send'), true);

        $this->assertTrue($client->sendBatch($requests));
    }

    public function testSendGood()
    {
        $this->fake_file->setContent('{"good":"json"}');
        $client = $this->getMock('Junior\Client\Client',
                                 array('handleResponse'),
                                 array($this->fake_uri));
        $client->expects($this->once())
               ->method('handleResponse')
               ->will($this->returnArgument(0));

        $client->send('foo');
    }

    public function testSendGoodNotify()
    {
        $this->fake_file->setContent('foo');
        $client = $this->getMock('Junior\Client\Client',
                                 array('handleResponse'),
                                 array($this->fake_uri));
        $client->expects($this->never())->method('handleResponse');

        $this->assertTrue($client->send('foo', true));
    }

    public function testSendBadConnect()
    {
        $client = $this->getMock('Junior\Client\Client',
                                 array('handleResponse'),
                                 array('not.there'));
        $client->expects($this->never())->method('handleResponse');

        $this->setExpectedException('Junior\Client\Exception');

        $client->send('foo');
    }

    public function testSendBadResponseJSON()
    {
        $this->fake_file->setContent('{bad:json,}');
        $client = $this->getMock('Junior\Client\Client',
                                 array('handleResponse'),
                                 array($this->fake_uri));
        $client->expects($this->never())->method('handleResponse');

        $this->setExpectedException('Junior\Client\Exception');

        $client->send('foo');
    }

    public function testHandleResponseGood()
    {
        $test_response = $this->getEmptyResponse();
        $test_response->result = 'foo';
        $test_response->id = 1;

        $client = $this->getMockClient(null);
        $client_response = $client->handleResponse($test_response);

        $this->assertEquals($test_response->result, $client_response->result);
        $this->assertEquals($test_response->id, $client_response->id);
    }

    public function testHandleResponseError()
    {
        $test_response = $this->getEmptyResponse();
        $test_response->error->code = 1;
        $test_response->error->message = 'foo';
        $test_response->id = 1;

        $client = $this->getMockClient(null);
        $client_response = $client->handleResponse($test_response);

        $this->assertEquals($test_response->error->code, $client_response->error_code);
        $this->assertEquals($test_response->error->message, $client_response->error_message);
        $this->assertEquals($test_response->id, $client_response->id);
    }

    public function testHandleResponseBatch()
    {
        $good_response = $this->getEmptyResponse();
        $good_response->result = 'foo';
        $good_response->id = 1;

        $error_response = $this->getEmptyResponse();
        $error_response->error->code = 1;
        $error_response->error->message = 'foo';
        $error_response->id = 2;

        $responses = array($good_response, $error_response);

        $client = $this->getMockClient(null);
        $client_response = $client->handleResponse($responses);

        $this->assertEquals($good_response->result, $client_response[1]->result);
        $this->assertEquals($good_response->id, $client_response[1]->id);

        $this->assertEquals($error_response->error->code, $client_response[2]->error_code);
        $this->assertEquals($error_response->error->message, $client_response[2]->error_message);
        $this->assertEquals($error_response->id, $client_response[2]->id);
    }

}

?>