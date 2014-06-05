<?php

function __autoload($className) {
	$filename = __DIR__.'/lib/'.strtr($className, array('_'=>'/')).'.php';
	require_once $filename;
}

function send_error_headers( $status ) {
	 header("HTTP/1.0 $status");
	 header("Cache-Control: no-cache");
	 header("Content-Type: text/plain");
}

function server_la_php_error( $errlev, $errstr, $errfile=null, $errline=null ) {
	if( ($errlev & error_reporting()) == 0 ) return;
	if( !headers_sent() ) send_error_headers("500 PHP Error");
	echo "HTTP 500!  Server error!\n";
	echo "Error (level $errlev): $errstr\n";
	if( $errfile or $errline ) {
		echo "\n";
		echo "at $errfile:$errline\n";
	}
	exit;
}

function init_environament() {
	ini_set('html_errors', false);
	set_error_handler('server_la_php_error');
	
	$config = include('config.php');
	if( $config === false ) {
		send_error_headers("500 No config.php present");
		echo "'config.php' does not exist or is returning false.\n";
		echo "\n";
		echo "Copy config.php.example to config.php and fix.\n";
		exit;
	}
	return $config;
}

function get_server() {
	$config = init_environament();
	$repos = array();
	foreach( $config['repositories'] as $repoPath ) {
		$repos[] = new TOGoS_PHPN2R_Repository( "$repoPath/data" );
	}
	if( count($repos) == 0 ) {
		send_error_headers("500 No repositories configured");
		echo "No repositories configured!\n";
		exit;
	}
	return new TOGoS_PHPN2R_Server( $repos );
}

function server_la_contenteaux( $urn, $filenameHint ) {
	$serv = get_server();
	
	$availableMethods = array("GET", "HEAD", "OPTIONS");
	
	switch( ($meth = $_SERVER['REQUEST_METHOD']) ) {
	case 'GET':
		$serv->serveBlob( $urn, $filenameHint );
		return;
	case 'HEAD':
		$serv->serveBlobHeaders( $urn, $filenameHint );
		return;
	case 'OPTIONS':
		send_error_headers("200 No repositories configured");
		echo implode("\n", $availableMethods), "\n";
		return;
	default:
		send_error_headers("405 Method not supported");
		echo "Method '$meth' is not supported by this service.\n";
		echo "\n";
		echo "Allowed methods: ".implode(', ', $availableMethods), "\n";
	}
}

function server_la_brows( $urn, $filenameHint, $rp ) {
	$serv = get_server();
	$serv->serveBrowse( $urn, $filenameHint, true, $rp );
}
