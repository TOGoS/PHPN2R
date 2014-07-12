<?php

class TOGoS_PHPN2R_FSSHA1RepositoryTest extends PHPUnit_Framework_TestCase
{
	public function setUp() {
		$this->repo = new TOGoS_PHPN2R_FSSHA1Repository("/tmp/test-repo");
		$this->helloWorldText = "Hello, world!\n";
		$this->helloWorldUrn = 'urn:sha1:BH5MRW75E66ZWTJDUAHLMSFKOULYSU3N';
		$this->wrongUrn = 'urn:sha1:ZZZMRW75E66ZWTJDUAHLMSFKOULYSZZZ';
	}
	
	protected function newHelloWorldTempFile() {
		$tempFile = tempnam("/tmp", "test-blob");
		file_put_contents($tempFile, $this->helloWorldText);
		return $tempFile;
	}
	
	protected function newHelloWorldStream() {
		return fopen($this->newHelloWorldTempFile(), "rb");
	}
	
	public function testPutTempFile() {
		$this->assertEquals(
			$this->helloWorldUrn,
			$this->repo->putTempFile($this->newHelloWorldTempFile(), 'blah')
		);
	}
	
	public function testPutTempFileWithExpectedUrn() {
		$this->assertEquals(
			$this->helloWorldUrn,
			$this->repo->putTempFile($this->newHelloWorldTempFile(), 'blah',
				$this->helloWorldUrn)
		);
	}
	
	public function testPutTempFileWithBadUrn() {
		try {
			$this->repo->putTempFile($this->newHelloWorldTempFile(), 'blah',
				$this->wrongUrn);
			$this->fail("Should've thrown a hash-mismatch exception.");
		} catch( Exception $e ) {
		}
	}
	
	public function testPutStream() {
		$this->assertEquals(
			$this->helloWorldUrn,
			$this->repo->putStream( $this->newHelloWorldStream(), 'blah')
		);
	}

	public function testPutStreamWithExpectedUrn() {
		$this->assertEquals(
			$this->helloWorldUrn,
			$this->repo->putStream( $this->newHelloWorldStream(), 'blah', $this->helloWorldUrn )
		);
	}

	public function testPutStreamWithBadUrn() {
		try {
			$this->repo->putStream( $this->newHelloWorldStream(), 'blah', $this->wrongUrn );
			$this->fail("Should've thrown a hash-mismatch exception.");
		} catch( Exception $e ) {
		}
	}
}
