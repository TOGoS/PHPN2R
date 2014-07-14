<?php

interface TOGoS_PHPN2R_Repository
{
	/** @return Nife_Blob */
	public function getBlob( $urn );
	
	/** @return URN of the put stream */
	public function putStream( $stream, $sector='uploaded', $expectedUrn=null );
}
