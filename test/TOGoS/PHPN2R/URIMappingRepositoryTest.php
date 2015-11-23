<?php

class TOGoS_PHPN2R_URIMappingRepositoryTest extends PHPUnit_Framework_TestCase
{
	protected $repo;
	
	public function setUp() {
		$this->repo = new TOGoS_PHPN2R_URIMappingRepository(
			new TOGoS_PHPN2R_FSSHA1Repository(__DIR__.'/../../test-data-repo'),
			array(),
			array(__DIR__.'/urn-map.txt'));
	}
	
	protected function getString($uri) {
		$blob = $this->repo->getBlob($uri);
		return $blob === null ? null : (string)$blob;
	}
	
	public function testGetHelloTxt() {
		$this->assertEquals( "Hello, world!", $this->getString('fake-uri:hello-world.txt') );
	}
	
	public function testGetEmptyTxt() {
		$this->assertEquals( "", $this->getString('fake-uri:empty.txt') );
	}
	
	public function testGetSq5() {
		$this->assertEquals( "Hello, world!", $this->getString('urn:sha1:SQ5HALIG6NCZTLXB7DNI56PXFFQDDVUZ') );
	}
}
