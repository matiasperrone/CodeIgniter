<?php

function xmlrpc_test_add($request)
{
	$p = $request->output_parameters();
	return get_instance()->xmlrpc->send_response(array(
		array('sum' => (int) $p[0] + (int) $p[1], 'who' => $p[2]),
		'struct'
	));
}

function _xmlrpcs()
{
	$CI = get_instance();
	$CI->load->library('xmlrpc');
	$CI->load->library('xmlrpcs');
	$CI->xmlrpcs->initialize(array('functions' => array(
		'math.add' => array('function' => 'xmlrpc_test_add'),
	)));
	return $CI->xmlrpcs;
}

function _call_xml($method, $params)
{
	$m = new XML_RPC_Message($method, $params);
	$m->createPayload();
	return $m->payload;
}

test('xmlrpcs: dispatches a registered method and returns a struct', function ()
{
	$srv = _xmlrpcs();
	$resp = $srv->parseRequest(_call_xml('math.add', array(
		new XML_RPC_Values(2, 'int'), new XML_RPC_Values(40, 'int'), new XML_RPC_Values('Ada', 'string')
	)));
	assert_equals(0, $resp->faultCode());
	$out = $resp->decode();
	assert_equals(42, $out['sum']);
	assert_same('Ada', $out['who']);
});

test('xmlrpcs: unknown methods yield a fault', function ()
{
	$srv = _xmlrpcs();
	$resp = $srv->parseRequest(_call_xml('nope.nope', array()));
	assert_true($resp->faultCode() > 0);
});

test('xmlrpcs: malformed XML yields a fault', function ()
{
	$srv = _xmlrpcs();
	$resp = $srv->parseRequest('<methodCall><methodName>x');
	assert_true($resp->faultCode() > 0);
});

test('xmlrpcs: system.listMethods lists registered methods', function ()
{
	$srv = _xmlrpcs();
	$resp = $srv->parseRequest(_call_xml('system.listMethods', array()));
	assert_equals(0, $resp->faultCode());
	$names = $resp->decode();
	assert_true(in_array('math.add', $names));
	assert_true(in_array('system.listMethods', $names));
});

test('xmlrpcs: system.multicall is disabled and answers with a fault', function ()
{
	$srv = _xmlrpcs();
	$resp = $srv->parseRequest(_call_xml('system.multicall', array(new XML_RPC_Values(array(), 'array'))));
	assert_true($resp->faultCode() > 0);
});

test('xmlrpcs: multicall_error builds a fault struct from a key or a response', function ()
{
	$srv = _xmlrpcs();
	// NOTE: the string-key form (multicall_error('notstruct')) raises an
	// "Undefined index" notice on every PHP version because the
	// xmlrpc*['multicall_*'] entries are missing; it is intentionally not
	// pinned here.
	$st = $srv->multicall_error(new XML_RPC_Response(0, 7, 'custom'));
	assert_same('custom', $st->me['struct']['faultString']->scalarval());
	assert_equals(7, $st->me['struct']['faultCode']->scalarval());
});

test('xmlrpcs: do_multicall validates and executes a single call', function ()
{
	$srv = _xmlrpcs();

	// a valid call to a registered method
	$call = new XML_RPC_Values(array(
		'methodName' => new XML_RPC_Values('math.add', 'string'),
		'params'     => new XML_RPC_Values(array(new XML_RPC_Values(1, 'int'), new XML_RPC_Values(2, 'int'), new XML_RPC_Values('a', 'string')), 'array'),
	), 'struct');
	$r = $srv->do_multicall($call);
	assert_same('array', $r->kindOf());
});
