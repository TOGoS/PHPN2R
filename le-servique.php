<?php

class Le_Repo {
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

function server_la_contenteaux( $urn, $filenameHint ) {
	$repo = new Le_Repo( "/home/stevens/datastore/ccouch/data" );
	if( ($file = $repo->findFile($urn)) ) {
		$size = filesize($file);
		
		$ct = null;
		$enc = null;
		if( preg_match('/.ogg$/',$filenameHint) ) {
			// finfo will report the skeleton type, application/ogg :(
			$ct = 'audio/ogg';
		} else {
			if( $finfo = finfo_open(FILEINFO_MIME_TYPE|FILEINFO_MIME_ENCODING) ) {
				$ct = finfo_file( $finfo, $file );
				finfo_close($finfo);
			}
		}
		if( $ct == null ) $ct = 'application/octet-stream';
		
		header("Content-Type: $ct");
		header('Cache-Control: cache');
		
		readfile($file);
	} else {
		header('HTTP/1.0 404 Blob not found');
		header('Content-Type: text/plain');
		echo "I coulnd't find $urn, bro.\n";
	}
}
