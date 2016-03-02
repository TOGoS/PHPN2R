<?php

/*
 * Initialize the environment for standalone scripts.
 * - autoloader
 * - error handling
 *
 * and return a Server object that can be used to kick off the rest of
 * the work.
 */

define('PHPN2R_Project_Root', __DIR__);

error_reporting(E_ALL|E_STRICT);
ini_set('html_errors', false);
ini_set('display_errors','On');

function send_error_headers( $status ) {
	 header("HTTP/1.0 $status");
	 header("Status: $status");
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
	exit(1);
}

function server_la_error( $message ) {
	if( !headers_sent() ) send_error_headers("500 PHP Error");
	echo "Error!\n\n", $message, "\n";
	exit(1);
}

set_error_handler('server_la_php_error');

if( file_exists($composerAutoloadFile = __DIR__.'/vendor/autoload.php') ) {
	require_once $composerAutoloadFile;
} else {
	// We can mostly get by without it.
	function __autoload($className) {
		$libDirs = array( __DIR__.'/lib', __DIR__.'/ext-lib' );
		foreach( $libDirs as $libDir ) {
			$filename = $libDir.'/'.strtr($className, array('_'=>'/')).'.php';
			if( file_exists($filename) ) {
				require_once $filename;
				return;
			}
		}
	}
}

class TOGoS_PHPN2R_Registry {
	protected $n2rRootDir;
	
	public function __construct($n2rRootDir) {
		$this->n2rRootDir = $n2rRootDir;
	}
	
	protected function loadConfig() {
		$configFile = $this->n2rRootDir.'/config.php';
		if( !file_exists($configFile) ) {
			send_error_headers("500 No config.php present");
			echo "'config.php' does not exist or is returning false.\n";
			echo "\n";
			echo "Copy config.php.example to config.php and fix.\n";
			exit(1);
		}
		return include($configFile);
	}
	
	protected function loadRepositories() {
		$repos = array();
		$config = $this->config;
		foreach( $config['repositories'] as $repoPath ) {
			$repos[] = new TOGoS_PHPN2R_FSSHA1Repository( $repoPath );
		}
		return $repos;
	}
	
	protected $components = [];
	protected $cachedComponents = [];
	
	public function __get($attrName) {
		// If something's been explicitly overridden, return that.
		if( isset($this->components[$attrName]) ) {
			return $this->components[$attrName];
		}
		
		// If there's a getter, call it and immediately return.
		$ucfAttrName = ucfirst($attrName);
		$getterMethodName = "get{$ucfAttrName}";
		if( method_exists($this, $getterMethodName) ) { 
			return $this->$getterMethodName();
		}

		// Check the cache.
		if( isset($this->cachedComponents[$attrName]) ) {
			return $this->cachedComponents[$attrName];
		}

		// If there's a loadX method, use it and cache the result.
		$creatorMethodName = "load{$ucfAttrName}";
		if( method_exists($this, $creatorMethodName) ) { 
			return $this->cachedComponents[$attrName] = $this->$creatorMethodName();
		}
		
		foreach( self::$funnilyCasedComponentNames as $n) {
			$n = trim($n);
			if( EarthIT_Schema_WordUtil::toCamelCase($n) == $attrName ) {
				// Ooh, this is what they want!
				$ucfAttrName = EarthIT_Schema_WordUtil::toPascalCase($n);
				break;
			}
		}
		
		// If there's a class with a matching name, instantiate it and cache the instance.
		$className = "PHPTemplateProjectNS_{$ucfAttrName}";
		if( class_exists($className,true) ) {
			return $this->cachedComponents[$attrName] = new $className($this);
		}
		
		throw new Exception("Undefined property: ".get_class($this)."#$attrName");
	}
}

// Depending how this was invoked (mod_php, cgi, PHP's built-in
// server, etc) PATH_INFO may be set in various ways.  This code
// attempts to catch some of them.
// 
// For environments that are more wildly different, you might want
// to just have separate bootstrap scripts.

if( isset($_SERVER['PATH_INFO']) ) {
	$path = $_SERVER['PATH_INFO'];
} else {
	preg_match('/^([^?]*)(?:\?(.*))?$/',$_SERVER['REQUEST_URI'],$bif);
	$path = $bif[1];
	if(!isset($_SERVER['QUERY_STRING'])) {
		$_SERVER['QUERY_STRING'] = isset($bif[2]) ? $bif[2] : '';
	}
}

return $PHPN2R_Registry = new TOGoS_PHPN2R_Registry();
$request = TOGoS_PHPN2R_Request::fromEnvironment();
$response = $PHPN2R_Registry->router->handleRequest($request);
Nife_Util::outputResponse($response);
