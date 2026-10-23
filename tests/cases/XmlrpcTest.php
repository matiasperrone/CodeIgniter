<?php

function _xmlrpc()
{
	$CI = get_instance();
	$CI->load->library('xmlrpc');
	return $CI->xmlrpc;
}

/** Wrap an XML body in an HTTP response and expose it as a stream */
function _http_stream($xml)
{
	$fp = fopen('php://temp', 'r+');
	fwrite($fp, "HTTP/1.1 200 OK\r\nContent-Type: text/xml\r\nContent-Length: ".strlen($xml)."\r\n\r\n".$xml);
	rewind($fp);
	return $fp;
}

test('xmlrpc: scalar values serialise to the XML-RPC wire format', function ()
{
	_xmlrpc();
	$v = new XML_RPC_Values('hello', 'string');
	assert_same("<value>\n<string>hello</string>\n</value>\n", $v->serialize_class());
	$v = new XML_RPC_Values(7, 'int');
	assert_contains('<int>7</int>', $v->serialize_class());
	$v = new XML_RPC_Values(TRUE, 'boolean');
	assert_contains('<boolean>1</boolean>', $v->serialize_class());
	$v = new XML_RPC_Values(2.5, 'double');
	assert_contains('<double>2.5</double>', $v->serialize_class());
	$v = new XML_RPC_Values('a<b&c', 'string');
	assert_contains('<string>a&lt;b&amp;c</string>', $v->serialize_class());
});

test('xmlrpc: structs and arrays serialise recursively', function ()
{
	_xmlrpc();
	$s = new XML_RPC_Values(array(
		'a' => new XML_RPC_Values(1, 'int'),
		'b' => new XML_RPC_Values(array(new XML_RPC_Values('x', 'string'), new XML_RPC_Values(TRUE, 'boolean')), 'array'),
	), 'struct');
	$xml = $s->serialize_class();
	assert_contains('<struct>', $xml);
	assert_contains('<name>a</name>', $xml);
	assert_contains('<name>b</name>', $xml);
	assert_contains('<array>', $xml);
	assert_contains('<string>x</string>', $xml);
	assert_same('struct', $s->kindOf());
});

test('xmlrpc: dateTime.iso8601 encoding keeps the date and hour', function ()
{
	_xmlrpc();
	$v = new XML_RPC_Values(0, 'string');
	// 2023-11-14 22:13:20 UTC. Only the stable prefix is part of the contract.
	assert_same('20231114T22:', substr($v->iso8601_encode(1700000000, 1), 0, 12));
	assert_same('20231114T22:', substr($v->iso8601_encode(1700000000, 0), 0, 12));
});

test('xmlrpc: iso8601_decode round-trips a well-formed timestamp', function ()
{
	_xmlrpc();
	$r = new XML_RPC_Response(new XML_RPC_Values('x', 'string'));
	assert_same(1700000000, $r->iso8601_decode('20231114T22:13:20', 1));
});

test('xmlrpc: message builds a methodCall payload', function ()
{
	_xmlrpc();
	$m = new XML_RPC_Message('foo.bar', array(new XML_RPC_Values('x', 'string'), new XML_RPC_Values(2.5, 'double')));
	$m->createPayload();
	assert_contains('<methodCall>', $m->payload);
	assert_contains('<methodName>foo.bar</methodName>', $m->payload);
	assert_contains('<string>x</string>', $m->payload);
	assert_contains('<double>2.5</double>', $m->payload);
});

test('xmlrpc: a response can be prepared, re-parsed and decoded', function ()
{
	_xmlrpc();
	$value = new XML_RPC_Values(array(
		'name'  => new XML_RPC_Values('Ada', 'string'),
		'age'   => new XML_RPC_Values(36, 'int'),
		'langs' => new XML_RPC_Values(array(new XML_RPC_Values('en', 'string'), new XML_RPC_Values('fr', 'string')), 'array'),
	), 'struct');
	$resp = new XML_RPC_Response($value);
	$body = $resp->prepare_response();
	assert_contains('<methodResponse>', $body);

	$msg = new XML_RPC_Message('x');
	$parsed = $msg->parseResponse(_http_stream($body));
	assert_equals(0, $parsed->faultCode());
	$decoded = $parsed->decode();
	assert_same('Ada', $decoded['name']);
	assert_equals(36, $decoded['age']);
	assert_same(array('en', 'fr'), $decoded['langs']);
});

test('xmlrpc: a fault response is parsed into code and string', function ()
{
	_xmlrpc();
	$resp = new XML_RPC_Response(0, 42, 'Something broke');
	$body = $resp->prepare_response();
	$msg = new XML_RPC_Message('x');
	$parsed = $msg->parseResponse(_http_stream($body));
	assert_equals(42, $parsed->faultCode());
	assert_same('Something broke', $parsed->faultString());
});

test('xmlrpc: client configuration helpers', function ()
{
	$x = _xmlrpc();
	$x->server('http://example.com/rpc', 8080);
	$x->method('do.thing');
	$x->request(array(array('a', 'string'), array(5, 'int')));
	assert_same('do.thing', $x->method);
	assert_equals(2, count($x->data));
	assert_contains('<string>a</string>', $x->data[0]->serialize_class());
	assert_contains('<int>5</int>', $x->data[1]->serialize_class());
});
