<?php

class TOGoS_PHPN2R_MultiRepository implements TOGoS_PHPN2R_Repository
{
	protected $backingRepos;
	protected $primaryRepo;
	
	public function __construct( array $backingRepos, $primaryRepo=null ) {
		$this->backingRepos = $backingRepos;
		$this->primaryRepo = $primaryRepo;
	}
	
	public function getBlob( $urn ) {
		foreach( $this->backingRepos as $repo ) {
			$blob = $repo->getBlob($urn);
			if( $blob !== null ) return $blob;
		}
		return null;
	}
	
	public function putStream( $stream, $sector='uploaded', $expectedUrn=null ) {
		if( $this->primaryRepo === null ) {
			throw new Exception("Can't store; no primary repository");
		}
		return $this->primaryRepo->putStream($stream, $sector, $expectedUrn);
	}
	
	public function putBlob( Nife_Blob $blob, array $options=array() ) {
		if( $this->primaryRepo === null ) {
			throw new Exception("Can't store; no primary repository");
		}
		return $this->primaryRepo->putBlob( $blob, $options );
	}
}
