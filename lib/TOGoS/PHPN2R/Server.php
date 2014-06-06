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
	
	protected function findFile($urn) {
		foreach( $this->repos as $repo ) {
			if( ($file = $repo->findFile($urn)) ) {
				return $file;
			}
		}
		return null;
	}

	const HTTP_DATE_FORMAT = "D, d M Y H:i:s e";
	
	protected function make404Response( $urn, $filenameHint ) {
		return Nife_Util::httpResponse("404 Blob not found", "I coulnd't find $urn, bro.\n");
	}
	
	protected function serveBlob( $urn, $filenameHint ) {
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
			
			$headers = array(
				'Date' => gmdate(self::HTTP_DATE_FORMAT, time()),
				'Expires' => gmdate(self::HTTP_DATE_FORMAT, time() + (3600*24*365)),
				'Cache-Control' => 'public',
				'Content-Type' => $ct
			);
			if( $etag) $headers['ETag'] = "\"$etag\"";
			return Nife_Util::httpResponse(
				200,
				new TOGoS_PHPN2R_FileBlob($file),
				$headers
			);
		} else {
			return $this->make404Response($urn, $filenameHint);
		}
	}
	
	public function browse( $urn, $filenameHint, $rp ) {
		if( ($file = $this->findFile($urn)) ) {
			$browseSizeLimit = 1024*1024*10;
			$blobSize = filesize($file);
			$tooBig = $blobSize > $browseSizeLimit;

			$linkMaker = new TOGoS_PHPN2R_LinkMaker($rp);
			$title = ($filenameHint ? "$filenameHint ($urn)" : $urn).' - PHPN2R blob browser';

			if( $tooBig ) {
				$pageContent = "<p>This file is too big (> $browseSizeLimit bytes) to analyze.</p>\n";
			} else {
				$content = file_get_contents($file);
				$contentHtml = htmlspecialchars($content);
				$contentHtml = preg_replace_callback(
					'#(?:urn|(?:x-parse-rdf|(?:(?:x-)?rdf-)?subject(?:-of)?)):(?:[A-Za-z0-9:_%+.-]+)#',
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
			$links[] = $linkMaker->htmlLinkForUrn($urn,null,'N2R');
			
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
	
	public function browseFromPathInfo() {
		$pathInfo = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '';
		
		if( preg_match( '#^ / ([^/]+) (?:/ (.*))? $#x', $pathInfo, $bif ) ) {
			$fn = isset($bif[2]) ? $bif[2] : null;
			return $this->browse( $bif[1], $fn, $fn === null ? '../' : '../../' );
		} else {
			return Nife_Util::httpResponse(
				"404 Unrecognised URN/path",
				"Expected a URL of the form '.../browse/<urn>/<filename>'\n".
				($pathInfo ? "But got '{$pathInfo}'\n" : "But no path given.\n")
			);
		}
	}
	
	public function rawFromPathInfo() {
		$pathInfo = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '';
		
		if( preg_match( '#^/([^/]+)/?(.*)$#', $pathInfo, $bif ) ) {
			return $this->serveBlob( $bif[1], $bif[2] );
		} else {
			return Nife_Util::httpResponse(
				"404 Unrecognised URN/path",
				"Expected a URL of the form '.../raw/<urn>/<filename>'\n".
				($pathInfo ? "But got '{$pathInfo}'\n" : "But no path given.\n")
			);
		}
	}
	
	public function rawFromN2r() {
		$qs = $_SERVER['QUERY_STRING'];
		if( $qs ) {
			return $this->serveBlob( $qs, null );
		} else {
			return Nife_Util::httpResponse(
				"404 Unrecognised URN/path",
				"Expected a URN in the query string, but it is empty.\n"
			);
		}
	}
}
