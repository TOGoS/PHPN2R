<?php

class TOGoS_PHPN2R_MultiRepositoryTest extends PHPUnit_Framework_TestCase
{
	protected $repo;
	
	public function setUp() {
		$this->repo = new TOGoS_PHPN2R_MultiRepository( array(
			new TOGoS_PHPN2R_FSSHA1Repository(__DIR__.'/../../test-fake-repo'),
			new TOGoS_PHPN2R_FSSHA1Repository(__DIR__.'/../../test-data-repo'),
		));
	}
	
	protected function getString($uri) {
		$blob = $this->repo->getBlob($uri);
		return $blob === null ? null : (string)$blob;
	}
	
	public function testGetSq5() {
		$this->assertEquals( "Hello, world!", $this->getString('urn:sha1:SQ5HALIG6NCZTLXB7DNI56PXFFQDDVUZ') );
	}
}
