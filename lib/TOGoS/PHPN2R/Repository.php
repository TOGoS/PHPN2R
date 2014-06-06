<?php

class TOGoS_PHPN2R_Repository {
	protected $dir;
	
	public function __construct( $dir ) {
		$this->dir = $dir;
	}
	
	function urnToBasename( $urn ) {
		if( preg_match( '/^urn:(?:sha1|bitprint):([0-9A-Z]{32})/', $urn, $bif ) ) {
			return $bif[1];
		}
		return null;
	}
	
	// It might make more sense for this to be in server instead of
	// repository so that the latest head can be found across multiple
	// repositories.
	protected function findHead( $path ) {
		// Allow colons in place of slashes to work
		// around Apache/PHP getting confused about them.
		$path = strtr($path, array(':'=>'/'));
		// No dot-dots allowed!
		if( strpos($path, '..') !== false) return null;
		if( $path[0] == '/' ) return null;
		
		$headsDir = $this->dir.'/heads';
		if( preg_match('#(.+?)/latest$#',$path,$bif) ) {
			$headDir = $headsDir.'/'.$bif[1];
			if( !is_dir($headDir) ) return null;
			$dh = opendir($headDir);
			if( $dh === false ) return null;
			$headNumbers = array();
			while( ($en = readdir($dh)) !== false ) {
				if( $en[0] == '.' ) continue;
				$headNumbers[] = $en;
			}
			closedir($dh);
			natsort($headNumbers);
			if( count($headNumbers) == 0 ) return null;
			$latest = array_pop($headNumbers);
			$file = "{$headDir}/{$latest}";
		} else {
			$file = $headsDir.'/'.$path;
		}
		if( file_exists($file) ) {
			return new TOGoS_PHPN2R_FileBlob($file);
		}
		return null;
	}
	
	public function findBlob( $urn ) {
		if( preg_match('/^(?:x-)ccouch-head:(.*)$/', $urn, $bif) ) {
			return $this->findHead($bif[1]);
		}
		
		$basename = $this->urnToBasename($urn);
		if( $basename === null ) return null;
		
		$first2 = substr($basename,0,2);
		
		$dataDir = $this->dir.'/data';
		if( !is_dir($dataDir) ) {
			// This may be due to something not being mounted,
			// or it may be a configuration error.
			// It might be good to log this somewhere,
			// but for now we'll just let it slide.
			return null;
		}
		$dir = opendir( $dataDir );
		$fil = null;
		while( $dir !== false and ($en = readdir($dir)) !== false ) {
			$fil = "$dataDir/$en/$first2/$basename";
			if( is_file($fil) ) break;
			else $fil = null;
		}
		closedir($dir);
		return $fil ? new TOGoS_PHPN2R_FileBlob($fil) : null;
	}
}
