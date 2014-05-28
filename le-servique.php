<?php

class PHPN2R_Repository {
	protected $dataDir;
	
	public function __construct( $dataDir ) {
		$this->dataDir = $dataDir;
	}
	
	function urnToBasename( $urn ) {
		if( preg_match( '/^urn:(?:sha1|bitprint):([0-9A-Z]{32})/', $urn, $bif ) ) {
			return $bif[1];
		}
		return null;
	}
	
	public function findFile( $urn ) {
		$basename = $this->urnToBasename($urn);
		if( $basename === null ) return null;
		
		$first2 = substr($basename,0,2);
		
		if( !is_dir($this->dataDir) ) {
			// This may be due to something not being mounted,
			// or it may be a configuration error.
			// It might be good to log this somewhere,
			// but for now we'll just let it slide.
			return null;
		}
		$dir = opendir( $this->dataDir );
		$fil = null;
		while( $dir !== false and ($en = readdir($dir)) !== false ) {
			$fil = "{$this->dataDir}/$en/$first2/$basename";
			if( is_file($fil) ) break;
			else $fil = null;
		}
		closedir($dir);
		return $fil;
	}
}

class PHPN2R_LinkMaker {
	/** Root path prefix; relative URI, including trailing slash, to uri-res/ */
	protected $rp;
	public function __construct( $rp ) {
		$this->rp = $rp;
	}
	public function componentUrl( $comp, $urn, $filenameHint=null ) {
		if( $comp == 'raw' and $filenameHint === null ) {
			return $this->rp.'N2R?'.$urn;
		} else {
			return $this->rp.$comp.'/'.$urn.($filenameHint === null ? '' : '/'.$filenameHint);
		}
	}
	public function htmlLink( $url, $text ) {
		return "<a href=\"".htmlspecialchars($url)."\">".htmlspecialchars($text)."</a>";
	}
	public function htmlLinkForUrn( $urn, $filenameHint, $text ) {
		if( $text === null ) $text = $urn;
		if( preg_match('/^(x-parse-rdf|(?:x-)?(?:rdf-)?subject(?:-of)?):(.*)$/',$urn,$bif) ) {
			$subjectScheme = $bif[1];
			$blobUrn = $bif[2];
			if( $text == $urn ) {
				return
					$this->htmlLink($this->componentUrl('browse', $blobUrn, $filenameHint), $subjectScheme).':'.
					$this->htmlLinkForUrn($blobUrn, $filenameHint, $blobUrn);
			} else {
				return $this->htmlLink($this->componentUrl('browse', $blobUrn, $filenameHint), $text);
			}
		} else {
			return $this->htmlLink($this->componentUrl('raw', $urn, $filenameHint), $text);
		}
	}
	public function urnHtmlLinkReplacementCallback( $matches ) {
		return $this->htmlLinkForUrn( $matches[0], null, $matches[0] );
	}
}

class PHPN2R_Server {
	protected $repos;
	
	public function __construct( $repos ) {
		$this->repos = $repos;
	}
	
	protected function guessFileType( $file, $filenameHint ) {
		if( preg_match('/.ogg$/',$filenameHint) ) {
			// finfo will report the skeleton type, application/ogg :(
			return 'audio/ogg';
		} else if( function_exists('finfo_open') and $finfo = finfo_open(FILEINFO_MIME_TYPE|FILEINFO_MIME_ENCODING) ) {
			$ct = finfo_file( $finfo, $file );
			finfo_close($finfo);
			return $ct;
		} else if( preg_match('/.html?$/i',$filenameHint) ) {
			return 'text/html';
		} else if( preg_match('/.jpe?g$/i',$filenameHint) ) {
			return 'image/jpeg';
		} else if( preg_match('/.png$/i',$filenameHint) ) {
			return 'image/png';
		} else {
			return null;
		}
	}
	
	protected function findFile($urn) {
		foreach( $this->repos as $repo ) {
			if( ($file = $repo->findFile($urn)) ) {
				return $file;
			}
		}
		return null;
	}

	const HTTP_DATE_FORMAT = "D, d M Y H:i:s e";
	
	protected function serve404( $urn, $filenameHint, $sendContent ) {
		header('HTTP/1.0 404 Blob not found');
		header('Content-Type: text/plain');
		if( $sendContent ) {
			echo "I coulnd't find $urn, bro.\n";
		}
	}
	
	protected function _serveBlob( $urn, $filenameHint, $sendContent ) {
		if( ($file = $this->findFile($urn)) ) {
			$size = filesize($file);
			
			$ct = null;
			$enc = null;
			$ct = $this->guessFileType( $file, $filenameHint );
			if( $ct == null ) $ct = 'application/octet-stream';

			if( preg_match( '/^urn:(?:sha1|bitprint):([0-9A-Z]{32})/', $urn, $bif ) ) {
				$etag = $bif[1];
			} else {
				$etag = null;
			}
			
			header('Date: '.gmdate(self::HTTP_DATE_FORMAT, time()));
			header('Expires: '.gmdate(self::HTTP_DATE_FORMAT, time() + (3600*24*365)));
			header('Cache-Control: public');
			if( $etag ) header("ETag: \"$etag\"");
			if( is_int($size) ) header("Content-Length: $size");
			header("Content-Type: $ct");
			
			if( $sendContent ) {
				readfile($file);
			}
		} else {
			$this->serve404($urn, $filenameHint, $sendContent);
		}
	}
	
	public function serveBlob( $urn, $filenameHint ) {
		$this->_serveBlob( $urn, $filenameHint, true );
	}

	public function serveBlobHeaders( $urn, $filenameHint ) {
		$this->_serveBlob( $urn, $filenameHint, false );
	}
	
	public function serveBrowse( $urn, $filenameHint, $sendContent, $rp ) {
		if( ($file = $this->findFile($urn)) ) {
			$browseSizeLimit = 1024*1024*10;
			$blobSize = filesize($file);
			$tooBig = $blobSize > $browseSizeLimit;
			
			echo "<html>\n";
			echo "<head>\n";
			$title = ($filenameHint ? "$filenameHint ($urn)" : $urn).' - PHPN2R blob browser';
			echo "<title>$title</title>\n";
			echo "</head><body>\n";
			if( $tooBig ) {
				echo "<p>This file is too big (> $browseSizeLimit bytes) to analyze.</p>\n"; 
			}
			$linkMaker = new PHPN2R_LinkMaker($rp);

			$rawUrl = $rp . ($filenameHint === null ? 'N2R?'.$urn : 'raw/'.$urn.'/'.$filenameHint);
			$links = array();
			if($filenameHint) $links[] = $linkMaker->htmlLinkForUrn($urn,$filenameHint,'Raw');
			$links[] = $linkMaker->htmlLinkForUrn($urn,null,'N2R');
			echo "<p>$blobSize bytes | ", implode(' | ',$links), "</p>\n";
			if( !$tooBig ) {
				$content = file_get_contents($file);
				$contentHtml = htmlspecialchars($content);
				$contentHtml = preg_replace_callback(
					'#(?:urn|(?:x-parse-rdf|(?:(?:x-)?rdf-)?subject(?:-of)?)):(?:[A-Za-z0-9:_%+.-]+)#',
					array($linkMaker,'urnHtmlLinkReplacementCallback'), $contentHtml
				);
				echo "<hr />\n";
				echo "<pre>";
				echo $contentHtml;
				echo "</pre>\n";
			}
			echo "</body>\n</html>\n";
		} else {
			$this->serve404($urn, $filenameHint, $sendContent);
		}
	}
}

function server_la_php_error( $errlev, $errstr, $errfile=null, $errline=null ) {
	if( ($errlev & error_reporting()) == 0 ) return;
	if( !headers_sent() ) {
		header('HTTP/1.0 500 Erreaux');
		header('Content-Type: text/plain');
	}
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
		header('HTTP/1.0 500 No config.php present');
		header('Content-Type: text/plain');
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
		$repos[] = new PHPN2R_Repository( "$repoPath/data" );
	}
	if( count($repos) == 0 ) {
		header('HTTP/1.0 404 No repositories configured');
		header('Content-Type: text/plain');
		echo "No repositories configured!\n";
		exit;
	}
	return new PHPN2R_Server( $repos );
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
		header('HTTP/1.0 200 No repositories configured');
		header('Content-Type: text/plain');
		echo implode("\n", $availableMethods), "\n";
		return;
	default:
		header('HTTP/1.0 405 Method not supported');
		header('Content-Type: text/plain');
		echo "Method '$meth' is not supported by this service.\n";
		echo "\n";
		echo "Allowed methods: ".implode(', ', $availableMethods), "\n";
	}
}

function server_la_brows( $urn, $filenameHint, $rp ) {
	$serv = get_server();
	$serv->serveBrowse( $urn, $filenameHint, true, $rp );
}
