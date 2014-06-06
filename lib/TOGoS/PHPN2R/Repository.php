<?php

class TOGoS_PHPN2R_Repository {
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
	
	public function findBlob( $urn ) {
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
		return $fil ? new TOGoS_PHPN2R_FileBlob($fil) : null;
	}
}
