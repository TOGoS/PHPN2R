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
	public function putStream( $stream, $sector='uploaded', $expectedUrn=null );
}
