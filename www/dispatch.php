<?php

// This script is provided as an alternate way
// (alternative to the 'raw', 'browse', and 'N2R' scripts)
// to handle any browse/..., raw/..., N2R/... requests.
// 
// It also demonstrates the handleRequest function,
// which may be useful if you're using this project
// as a library from another.
//
// This is designed to work either as a mod_rewritten-to script
// or as a standalone PHP web server router script.

// If everything goes hunky-dory then this will be overridden later.
// This helps make fatal errors more obvious.
header('HTTP/1.0 500 Error By Default');

$res = require '../setup.php';

if( !isset($_SERVER['PATH_INFO']) ) {
	// Standalone router mode
	if( preg_match( '#^/uri-res(/[^\?]*)\??(.*)#', $_SERVER['REQUEST_URI'], $bif ) ) {
		$_SERVER['PATH_INFO'] = $bif[1];
		$_SERVER['QUERY_STRING'] = $bif[2];
	} else {
		send_error_headers("404 What");
		echo "Don't know how to handle URL: {$_SERVER['REQUEST_URI']}\n";
		exit;
	}
}

Nife_Util::outputResponse($res->handleRequest($_SERVER['PATH_INFO']));
