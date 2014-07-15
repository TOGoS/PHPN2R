<?php

class TOGoS_PHPN2R_Server {
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
	
	protected function make404Response( $urn, $filenameHint ) {
		return Nife_Util::httpResponse("404 Blob not found", "I coulnd't find $urn, bro.\n");
	}
	
	public function serveBlob( $urn, $filenameHint=null ) {
		if( ($blob = $this->getBlob($urn)) ) {
			$ct = null;
			if( $blob instanceof TOGoS_PHPN2R_FileBlob ) {
				$ct = $this->guessFileType( $blob->getFile(), $filenameHint );
			}
			if( $ct == null ) $ct = 'application/octet-stream';

			if( preg_match( '/^urn:(?:sha1|bitprint):([0-9A-Z]{32})/', $urn, $bif ) ) {
				$etag = $bif[1];
			} else {
				$etag = null;
			}
			
			$headers = array(
				'Date' => gmdate(self::HTTP_DATE_FORMAT, time()),
				'Expires' => gmdate(self::HTTP_DATE_FORMAT, time() + (3600*24*365)),
				'Cache-Control' => 'public',
				'Content-Type' => $ct
			);
			if( $etag) $headers['ETag'] = "\"$etag\"";
			return Nife_Util::httpResponse(
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
			$browseSizeLimit = 1024*1024*10;
			$blobSize = $blob->getLength();
			$tooBig = $blobSize === null || $blobSize > $browseSizeLimit;
			
			$linkMaker = new TOGoS_PHPN2R_LinkMaker($rp);
			$title = ($filenameHint ? "$filenameHint ($urn)" : $urn).' - PHPN2R blob browser';
			
			if( $tooBig ) {
				$pageContent = "<p>This file is too big (> $browseSizeLimit bytes) to analyze.</p>\n";
			} else {
				$content = (string)$blob;
				$contentHtml = htmlspecialchars($content);
				$contentHtml = preg_replace_callback(
					'#(?:urn|(?:x-ccouch-head|x-parse-rdf|(?:(?:x-)?rdf-)?subject(?:-of)?)):(?:[A-Za-z0-9:_%+.-]+)#',
					array($linkMaker,'urnHtmlLinkReplacementCallback'), $contentHtml
				);
				$pageContent =
					"<hr />\n".
					"<pre>".
					$contentHtml.
					"</pre>\n";
			}

			$rawUrl = $rp . ($filenameHint === null ? 'N2R?'.$urn : 'raw/'.$urn.'/'.$filenameHint);
			$links = array();
			if($filenameHint) $links[] = $linkMaker->htmlLinkForUrn($urn,$filenameHint,'Raw');
			$links[] = $linkMaker->rawHtmlLinkForUrn($urn,null,'N2R');
			
			$html =
				"<html>\n".
				"<head>\n".
				"<title>$title</title>\n".
				"</head><body>\n".
				"<p>$blobSize bytes | ".implode(' | ',$links)."</p>\n".
				$pageContent.
				"</body>\n</html>\n";
			
			return Nife_Util::httpResponse(200, $html, "text/html; charset=utf-8");
		} else {
			return $this->make404Response($urn, $filenameHint);
		}
	}
	
	protected function extractParams($pathInfo, $method, $queryString) {
		if( $pathInfo == '/N2R' ) {
			return $queryString ? array(
				'service' => 'raw',
				'method' => $method,
				'URN' => $queryString,
				'filename hint' => null,
				'root prefix' => '',
				'error message' => null
			) : array(
				'service' => 'bad-request',
				'error message' => 'No query string given'
			);
		} else if( preg_match( '#^ / (raw|browse) / ([^/]+) (?:/ (.*))? $#x', $pathInfo, $bif ) ) {
			$fn = isset($bif[3]) ? $bif[3] : null;
			return array(
				'service' => $bif[1],
				'method' => $method,
				'URN' => $bif[2],
				'filename hint' => $fn,
				'root prefix' => $fn === null ? '../' : '../../'
			);
		} else {
			return array(
				'service' => 'bad-request',
				'error message' => "Unrecongized path requested: $pathInfo"
			);
		}
	}
	
	public function handleReekQuest( array $params ) {
		if( $params['service'] == 'bad-request' ) {
			return Nife_Util::httpResponse("404 Unrecognized path", $params['error message']);
		} else if( $params['method'] == 'GET' ) {
			if( $params['service'] == 'browse' ) {
				return $this->browse($params['URN'], $params['filename hint'], $params['root prefix']);
			} else {
				return $this->serveBlob($params['URN'], $params['filename hint']);
			}
		} else {
			return Nife_Util::httpResponse("405 Method not allowed", "Method {$params['method']} not allowed");
		}
	}
	
	public function handleRequest( $pathInfo ) {
		$params = $this->extractParams($pathInfo, $_SERVER['REQUEST_METHOD'], $_SERVER['QUERY_STRING']);
		return $this->handleReekQuest($params);
	}
}
