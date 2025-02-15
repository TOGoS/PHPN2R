<?php

/*
 * Initialize the environment for standalone scripts.
 * - autoloader
 * - error handling
 *
 * and return a Server object that can be used to kick off the rest of
 * the work.
 */

error_reporting(E_ALL|E_STRICT);
ini_set('html_errors', false);
ini_set('display_errors','On');

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

set_error_handler('server_la_php_error');

if( file_exists($composerAutoloadFile = __DIR__.'/vendor/autoload.php') ) {
	require_once $composerAutoloadFile;
} else {
	// We can mostly get by without it.
	
	function PHPN2R_autoload($className) {
		$libDirs = array( __DIR__.'/lib', __DIR__.'/ext-lib' );
		foreach( $libDirs as $libDir ) {
			$filename = $libDir.'/'.strtr($className, array('_'=>'/')).'.php';
			if( file_exists($filename) ) {
				require_once $filename;
				return;
			}
		}
	}
	
	spl_autoload_register('PHPN2R_autoload');
}

$config = include('config.php');
if( $config === false ) {
	send_error_headers("500 No config.php present");
	echo "'config.php' does not exist or is returning false.\n";
	echo "\n";
	echo "Copy config.php.example to config.php and fix.\n";
	exit;
}

// This is here mainly so that you can set max_execution_time to a high value
// allowing streaming of long audio/video files.
// TODO: Investigate repeated calls to set_time_limit while streaming
// Nife_Blobs as an alternative and maybe safer option.
if( isset($config['php-ini']) ) {
	foreach( $config['php-ini'] as $k => $v ) {
		ini_set($k, $v);
	}
}

$repos = array();
foreach( $config['repositories'] as $name=>$repoPath ) {
	$repos[$name] = new TOGoS_PHPN2R_FSSHA1Repository( $repoPath );
}
if( count($repos) == 0 ) {
	send_error_headers("500 No repositories configured");
	echo "No repositories configured!\n";
	exit;
}

return new TOGoS_PHPN2R_Server( $repos, $config );
