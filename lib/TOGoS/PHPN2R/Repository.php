<?php

interface TOGoS_PHPN2R_Repository
{
	/**
	 * @return Nife_Blob, or null if the named blob wasn't found
	 * 
	 * Should throw an exception if anything goes wrong other than the
	 * blob being not found for regular reasons.  What 'regular
	 * reasons' are, however, is up to the implementation.
	 */
	public function getBlob( $urn );
	
	/**
	 * While this could technically do ~anything~ when putting a blob,
	 * its generally expected that getBlob($urnReturnedByPutStream) will
	 * return the thing that was stored.  If storage fails for some reason
	 * this should throw an exception.
	 *
	 * @return URN of the put stream
	 */
	public function putStream( $stream, $sector=null, $expectedUrn=null );

	/** Value names desired repository sector in which to store any new data */
	const OPT_SECTOR = 'sector';
	/** If true, implementation is free to move files instead of copying. */
	const OPT_ALLOW_SOURCE_REMOVAL = 'allowSourceRemoval';
	/** Value is the SHA-1 (20 bytes; not encoded) that the caller expects the blob to have. */
	const OPT_EXPECTED_SHA1 = 'expectedSha1';
	
	public function putBlob( Nife_Blob $blob, array $options=array() );
}
