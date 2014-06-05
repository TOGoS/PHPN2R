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
	
	protected function serve404( $urn, $filenameHint, $sendContent ) {
		send_error_headers('404 Blob not found');
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
			$linkMaker = new TOGoS_PHPN2R_LinkMaker($rp);

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
