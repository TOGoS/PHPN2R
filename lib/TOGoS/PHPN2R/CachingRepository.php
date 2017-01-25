<?php

/**
 */
class TOGoS_PHPN2R_CachingRepository implements TOGoS_PHPN2R_Repository
{
	protected $repositories;
	
	/**
	 * Check these first; if a blob is found it is returned without any caching
	 */
	protected $nearRepoNames;
	/**
	 *	Check these if nothing is found in nearRepos; if a blob is found, store it into cache repos.
	 */
	protected $farRepoNames;
	/**
	 * When a blob is fetched from a far repo, it will be stored into each of these.
	 * It would make sense for this to be some subset of nearRepoNames.
	 */
	protected $cacheRepoNames;
	/**
	 * putBlob will store things into each of these; if null, storage attempts result in an error
	 */
	protected $storeRepoNames;
	
	protected static function ag( array $arr, $k, $default=null ) {
		return isset($arr[$k]) ? $arr[$k] : $default;
	}
	
	public function __construct( array $repositories, array $config ) {
		$this->repositories = $repositories;
		$this->nearRepoNames = self::ag($config, 'nearRepoNames', array());
		$this->farRepoNames = self::ag($config, 'farRepoNames', array());
		$this->cacheRepoNames = self::ag($config, 'cacheRepoNames', array());
		$this->storeRepoNames = self::ag($config, 'storeRepoNames', null);
	}
	
	protected function getRepos( $names ) {
		$r = array();
		foreach( $names as $n ) {
			if( !isset($this->repositories[$n]) ) {
				throw new Exception(
					"Referenced to repository '$n', which isn't defined!\n".
					"Valid repo names are: ".array_keys($this->repositories));
			}
			$r[$n] = $this->repositories[$n];
		}
		return $r;
	}
	
	/** Return true if the named object can be found in any of the local repositories */
	public function isLocal( $urn ) {
		foreach( $this->getRepos( $this->nearRepoNames ) as $repo ) {
			if( $repo->getBlob($urn) !== null ) return true;
		}
		return false;
	}
	
	public function getBlob( $urn ) {
		foreach( $this->getRepos( $this->nearRepoNames ) as $repo ) {
			$blob = $repo->getBlob( $urn );
			if( $blob !== null ) return $blob;
		}
		
		foreach( $this->getRepos( $this->farRepoNames ) as $repo ) {
			$blob = $repo->getBlob( $urn );
			if( $blob !== null ) break;
		}
		
		if( $blob === null ) return null;
		
		foreach( $this->getRepos( $this->cacheRepoNames ) as $repo ) {
			$repo->putBlob( $blob );
		}
		
		return $blob;
	}
	
	public function putStream( $stream, $sector=null, $expectedUrn=null ) {
		if( empty($this->storeRepoNames) ) {
			throw new Exception("This CachingRepository is not set up to store!");
		}

		$stored = false;
		foreach( $this->getRepos( $this->storeRepoNames ) as $repo ) {
			$urn = $repo->putStream($stream, $sector, $expectedUrn);
			$stored = true;
		}
		if( !$stored ) throw new Exception("No store repos configured for this CachingRepository!");
		return $urn;
	}
	
	public function putBlob( Nife_Blob $blob, array $options=array() ) {
		if( empty($this->storeRepoNames) ) {
			throw new Exception("This CachingRepository is not set up to store!");
		}
		
		$stored = false;
		foreach( $this->getRepos( $this->storeRepoNames ) as $repo ) {
			$urn = $repo->putBlob($blob);
			$stored = true;
		}
		if( !$stored ) throw new Exception("No store repos configured for this CachingRepository!");
		return $urn;
	}
}
