<?php

class TOGoS_PHPN2R_Server {
	protected $repos;
	protected $config;
	
	public function __construct( $repos, $config=array() ) {
		$this->repos = $repos;
		$this->config = $config;
	}
	
	protected $componentCache = array();
	
	public function __get($thing) {
		if( $thing == 'interChunkTimeoutReset' ) return isset($this->config['inter-chunk-timeout-reset']) ? $this->config['inter-chunk-timeout-reset'] : 10;
		
		if( isset($this->componentCache[$thing]) ) return $this->componentCache[$thing];
		
		$getMeth = 'get'.ucfirst($thing);
		if( method_exists($this,$getMeth) ) return $this->$getMeth();
		
		$loadMeth = 'load'.ucfirst($thing);
		if( method_exists($this,$loadMeth) ) return $this->componentCache[$thing] = $this->$loadMeth();
		
		if( class_exists($className = 'TOGoS_PHPN2R_'.ucfirst($thing)) ) {
			return $this->componentCache[$thing] = new $className($this);
		}
		
		throw new Exception(get_class($this)." doesn't have a #$thing.");
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
	
	protected function collectFiles( $dir, $as, array &$dest ) {
		if( is_dir($dir) ) {
			$dh = @opendir($dir);
			if( $dh === false ) return;
			while( ($en = readdir($dh)) !== false ) {
				if( $en[0] == '.' ) continue;
				$this->collectFiles( "$dir/$en", $as ? "$as/$en" : $en, $dest );
			}
			closedir($dh);
		} else if( is_file($dir) ) {
			$dest[] = $as;
		}
	}
	
	public function getHeadList() {
		$heads = array();
		foreach( $this->repos as $repo ) {
			$this->collectFiles( $repo->getDir()."/heads", '', $heads );
		}
		return $heads;
	}
	
	protected function makeHeadListBlob() {
		$headList = array();
		$latestHeads = array();
		foreach( $this->getHeadList() as $h ) {
			$urn = 'x-ccouch-head:'.strtr($h, array('/'=>':'));
			if( preg_match('#^(.+?):(\d+)$#',$urn,$bif) ) {
				if( !isset($latestHeads[$bif[1]]) or (int)$bif[2] > (int)$latestHeads[$bif[1]] ) {
					$latestHeads[$bif[1]] = $bif[2];
				}
			}
			$headList[] = $urn;
		}
		
		$data = '';
		if( count($latestHeads) ) {
			$names = array_keys($latestHeads);
			natsort($names);
			$data .= "# Latest heads\n";
			foreach( $names as $n ) {
				$data .= $n.':'.$latestHeads[$n] . "\n";
			}
			$data .= "\n";
		}
		
		natsort($headList);
		$data .= "# All heads\n";
		$data .= implode("\n", $headList)."\n";
		return new Nife_StringBlob($data);
	}
	
	public function getBlob($urn) {
		if( $urn == 'head-list' ) {
			return $this->makeHeadListBlob();
		}
		foreach( $this->repos as $repo ) {
			if( ($blob = $repo->getBlob($urn)) ) {
				return $blob;
			}
		}
		return null;
	}

	const HTTP_DATE_FORMAT = "D, d M Y H:i:s e";
	
	static function combineHeader() {
		$l = func_get_args();
		$mix = array();
		foreach( $l as $lz ) {
			foreach( explode(', ', $lz) as $lzv ) {
				$mix[$lzv] = $lzv;
			}
		}
		return implode(', ', $mix);
	}
	
	static function mixHeaders() {
		$headers = array();
		$l = func_get_args();
		foreach( $l as $lh ) {
			foreach( $lh as $k=>$v ) {
				if( isset($headers[$k]) ) {
					$headers[$k] = self::combineHeader($headers[$k], $v);
				} else $headers[$k] = $v;
			}
		}
		return $headers;
	}
	
	protected function getConfiguredResponseHeaders( $headers=array() ) {
		return isset($this->config['http-response-headers']) ?
			$this->config['http-response-headers'] : array();
	}
	
	protected function getRegularResponseHeaders() {
		$headers = array();
		$headers['access-control-allow-headers'] = 'x-ccouch-sector'; // Header's always allowed; we may ignore it.
		return $headers;
	}
	
	protected function httpResponse($status, $content=null, $headers=array() ) {
		if( is_string($headers) ) $headers = array('content-type'=>$headers);
		$headers = self::mixHeaders($headers, $this->getConfiguredResponseHeaders(), $this->getRegularResponseHeaders());
		return Nife_Util::httpResponse($status, $content, $headers);
	}
	
	protected function make404Response( $urn, $filenameHint ) {
		return $this->httpResponse("404 Blob not found", "I coulnd't find $urn, bro.\n");
	}
	
	public function serveBlob( $urn, $filenameHint=null, $typeOverride=null ) {
		if( ($blob = $this->getBlob($urn)) ) {
			$ct = $typeOverride;
			if( $ct === null and $blob instanceof Nife_FileBlob ) {
				$ct = $this->guessFileType( $blob->getFile(), $filenameHint );
			}
			if( $ct === null ) $ct = 'application/octet-stream';

			if( preg_match( '/^urn:(?:sha1|bitprint):([0-9A-Z]{32})/', $urn, $bif ) ) {
				$etag = $bif[1];
			} else {
				$etag = null;
			}
			
			$headers = array(
				'Date' => gmdate(self::HTTP_DATE_FORMAT, time()),
				'Expires' => gmdate(self::HTTP_DATE_FORMAT, time() + (3600*24*365)),
				'Cache-Control' => 'public',
				'Access-Control-Allow-Origin' => '*',
				'Content-Type' => $ct
			);
			if( $etag) $headers['ETag'] = "\"$etag\"";
			return $this->httpResponse(
				200,
				$blob,
				$headers
			);
		} else {
			return $this->make404Response($urn, $filenameHint);
		}
	}
	
	public function browse( $urn, $filenameHint, $rp ) {
		if( ($blob = $this->getBlob($urn)) ) {
			return $this->browser->browseBlob($blob, $urn, $filenameHint, $rp);
		} else {
			return $this->make404Response($urn, $filenameHint);
		}
	}
	
	/**
	 * @deprecated - this may all get moved into parseRequest.
	 */
	protected function extractParams($pathInfo, $method, $queryString) {
		if( $pathInfo == '/N2R' ) {
			return $queryString ? array(
				'service' => 'raw',
				'method' => $method,
				'URN' => urldecode($queryString),
				'filename hint' => null,
				'root prefix' => '',
				'type override' => null,
			) : array(
				'service' => 'bad-request',
				'error message' => 'No query string given'
			);
		} else if( preg_match( '#^ / (raw|browse) / ([^/]+) (?:/ (.*))? $#x', $pathInfo, $bif ) ) {
			$fn = isset($bif[3]) ? $bif[3] : null;
			$params = array();
			if( $queryString ) parse_str($queryString, $params);
			$typeOverride = isset($params['type']) ? $params['type'] : null;
			return array(
				'service' => $bif[1],
				'method' => $method,
				'URN' => urldecode($bif[2]),
				'filename hint' => urldecode($fn),
				'root prefix' => $fn === null ? '../' : '../../',
				'type override' => $typeOverride,
			);
		} else {
			return array(
				'service' => 'bad-request',
				'error message' => "Unrecongized path requested: $pathInfo"
			);
		}
	}
	
	protected function handleUpload( $urn ) {
		if( !isset($this->config['upload-repository']) ) {
			return $this->httpResponse(
				"500 No upload-repository configured",
				"Cannot handle uploads because 'upload-repository' is not configured.");
		}
		$repo = isset($this->repos[$this->config['upload-repository']]) ?
				$this->repos[$this->config['upload-repository']] : null;
		if( $repo === null ) {
			return $this->httpResponse(
				"500 upload repository misconfigured",
				"Configured upload-repository, '{$this->config['upload-repository']}',\n".
				"is not itself configured.");
		}
		if( !isset($this->config['upload-sector']) ) {
			return $this->httpResponse(
				"500 No upload-sector configured",
				"Cannot handle uploads because 'upload-sector' is not configured.");
		}
		// TODO: Allow sector override if configured to
		$inputStream = fopen('php://input', 'rb');
		try {
			$repo->putStream($inputStream, $this->config['upload-sector'], $urn);
		} catch( TOGoS_PHPN2R_IdentifierFormatException $e ) {
			return $this->httpResponse( "409 Unparseable URN", $e->getMessage()."\n" );
		} catch( TOGoS_PHPN2R_HashMismatchException $e ) {
			return $this->httpResponse( "409 Hash mismatch", $e->getMessage()."\n" );
		}
		if( $inputStream !== null ) @fclose($inputStream);
		return $this->httpResponse( "204 Uploaded" );
	}
	
	protected function getAvailableMethodsForRaw() {
		$methods = array('HEAD','GET','OPTIONS');
		if( !empty($this->config['allow-uploads']) ) {
			$methods[] = 'PUT';
		}
		return $methods;
	}
	
	protected function optionsResponse( $methods ) {
		return $this->httpResponse(
			"200 Okay here are your methods",
			"Available methods for this resource: ".implode(', ',$methods)."\n",
			array('access-control-allow-methods' => implode(', ',$methods))
		);
	}
	
	/**
	 * @deprecated - call handleRequest instead
	 */
	public function handleReekQuest( array $params, array $ctx=array() ) {
		if( $params['service'] == 'bad-request' ) {
			return $this->httpResponse("404 Unrecognized path", $params['error message']);
		}
		
		if( $params['method'] == 'OPTIONS' ) {
			switch( $params['service'] ) {
			case 'browse':
				return $this->optionsResponse( array('HEAD','GET','OPTIONS') );
			case 'raw':
				return $this->optionsResponse( $this->getAvailableMethodsForRaw() );
			default:
				return $this->httpResponse("500 invalid service", "Invalid service, {$params['service']}");
			}
		}
		
		if( $params['method'] == 'GET' or $params['method'] == 'HEAD' ) {
			if( $params['service'] == 'browse' ) {
				return $this->browse($params['URN'], $params['filename hint'], $params['root prefix']);
			} else {
				return $this->serveBlob($params['URN'], $params['filename hint'], $params['type override']);
			}
		}
		
		if( $params['method'] === 'PUT' and !empty($this->config['allow-uploads']) ) {
			$allowed = false;
			if( $this->config['allow-uploads'] === true ) {
				$allowed = true;
			} else if( $this->config['allow-uploads'] === 'for-authorized-users' ) {
				//$allowed = !empty( username ); // wherever that comes from
			}
			
			if( $allowed ) {
				return $this->handleUpload( $params['URN'] );
			}
		}
		
		// Otherwise it's just not allowed at all
		return $this->httpResponse("405 Method not allowed", "Method {$params['method']} not allowed");
	}
	
	public function parseRequest( $method, $pathInfo, $queryString='' ) {
		return $this->extractParams( $pathInfo, $method, $queryString );
	}
	
	protected function authenticate( array $request ) {
		if( !isset($request['pre-auth username']) ) {
			// That's fine, they can be nobody!
			unset($request['pre-auth username']);
			unset($request['pre-auth password']);
			return $request;
		}
		
		if( !isset($this->config['users'][$request['pre-auth username']]) ) {
			return false;
		}
		
		$passhashes = $this->config['users'][$request['pre-auth username']]['passhashes'];
		foreach( $passhashes as $ph ) {
			if( password_verify($request['pre-auth password'], $ph) ) {
				// Success!
				$request['username'] = $request['pre-auth username'];
				unset($request['pre-auth username']);
				unset($request['pre-auth password']);
				return $request;
			}
		}
		
		return false;
	}
	
	public function handleRequest( $request ) {
		if( is_string($request) ) {
			$request = $this->parseRequest($_SERVER['REQUEST_METHOD'], $request, $_SERVER['QUERY_STRING']);
			$request['pre-auth username'] = isset($_SERVER['PHP_AUTH_USER']) ? $_SERVER['PHP_AUTH_USER'] : null;
			$request['pre-auth password'] = isset($_SERVER['PHP_AUTH_PW']) ? $_SERVER['PHP_AUTH_PW'] : null;
		}
		if( !is_array($request) ) {
			throw new Exception(
				"Argument to ".get_class($this)."#handleRequest must be a pathinfo ".
				"string or request information array as returned by #parseRequest.");
		}
		$request = $this->authenticate($request);
		if( $request === false ) {
			return $this->httpResponse("401 Authentication Failed", "Bad username or password!");
		}
		return $this->handleReekQuest($request);
	}
	
	public function makeOutputter() {
		return Nife_Util::makeTimeoutResettingOutputter($this->interChunkTimeoutReset);
	}
}
