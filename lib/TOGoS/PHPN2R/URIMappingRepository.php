<?php

/**
 * Attempts to load blobs based on alternate mapped URIs
 * which may be loaded as needed from tab-separated URI equivalence files.
 */
class TOGoS_PHPN2R_URIMappingRepository implements TOGoS_PHPN2R_Repository
{
	protected $notYetLoadedMapFiles = array();
	protected $loadedMapFiles = array();
	protected $backingRepo;
	protected $mappings = array();

	protected static function set(array $a) {
		$s = array();
		foreach( $a as $v ) $s[$v] = $v;
		return $s;
	}
	
	protected function addMapping(array $equivalentUris) {
		$equivalentUris = self::set($equivalentUris);
		// Collect any existing mappings...
		foreach( $equivalentUris as $uri ) {
			if( isset($this->mappings[$uri]) ) {
				$equivalentUris += $this->mappings[$uri];
			}
		}
		// And then store them again
		foreach( $equivalentUris as $uri ) {
			$this->mappings[$uri] = $equivalentUris;
		}
	}
	
	public function __construct(
		TOGoS_PHPN2R_Repository $backingRepo, array $mapping=array(), array $mappingFiles=array()
	) {
		$this->backingRepo = $backingRepo;
		foreach( $mapping as $k=>$v ) {
			$vals = array();
			if( is_string($k) ) $vals[$k] = $k;
			if( is_string($v) ) $v = array($v);
			foreach( $v as $_v ) $vals[$_v] = $_v;
			$this->addMapping($vals);
		}
		foreach( $mappingFiles as $f ) {
			$this->addMappingFile($f);
		}
	}
	
	protected function loadMappingsFromFile($mappingFile) {
		$fh = fopen($mappingFile,'r');
		if( $fh === false ) return false;
		$anythingLoaded = false;
		while( ($line = fgets($fh)) !== false ) {
			$line = trim($line);
			if( $line === '' or $line[0] === '#' ) continue;
			$this->addMapping(preg_split("/[ \t]+/",$line));
			$anythingLoaded = true;
		}
		fclose($fh);
		return $anythingLoaded;
	}
	
	public function addMappingFile($f) {
		if( isset($this->loadedMapFiles[$f]) ) return;
		$this->notYetLoadedMapFiles[$f] = $f;
	}
	
	/**
	 * Return true if any new mappings were loaded.
	 */
	protected function loadNewMappings() {
		$anythingLoaded = false;
		while( !$anythingLoaded and count($this->notYetLoadedMapFiles) ) {
			$first = null;
			$newNotYetLoadedList = array();
			foreach( $this->notYetLoadedMapFiles as $f ) {
				if( $first === null ) {
					$first = $f;
				} else {
					$newNotYetLoadedList[$f] = $f;
				}
			}
			$anythingLoaded = $this->loadMappingsFromFile($first);
			$this->loadedMapFiles[$first] = $first;
			$this->notYetLoadedMapFiles = $newNotYetLoadedList;
		}
		return $anythingLoaded;
	}
	
	/** @return Nife_Blob */
	public function getBlob( $urn ) {
		$blob = $this->backingRepo->getBlob($urn);
		if( $blob !== null ) return $blob;

		$attemptedUrns = array($urn=>$urn);
		do {
			if( isset($this->mappings[$urn]) ) foreach( $this->mappings[$urn] as $altUrn ) {
				if( !isset($attemptedUrns[$altUrn]) ) {
					$blob = $this->backingRepo->getBlob($altUrn);
					if( $blob !== null ) return $blob;
				}
				$attemptedUrns[$altUrn] = $altUrn;
			}
		} while( $this->loadNewMappings() );
		return null;
	}
	
	/** @return URN of the put stream */
	public function putStream( $stream, $sector='uploaded', $expectedUrn=null ) {
		return $this->backingRepo->putStream( $stream, $sector, $expectedUrn );
	}

	public function putBlob( Nife_Blob $blob, array $options=array() ) {
		return $this->backingRepo->putBlob( $blob, $options );
	}
}
