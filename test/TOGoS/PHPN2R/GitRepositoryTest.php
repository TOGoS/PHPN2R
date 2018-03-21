<?php

class TOGoS_PHPN2R_GitRepositoryTest extends TOGoS_SimplerTest_TestCase
{
	protected $repo;
	
	public function setUp() {
		$this->repo = new TOGoS_PHPN2R_GitRepository(__DIR__.'/../../../.git');
	}
	
	protected function getString($uri) {
		$blob = $this->repo->getBlob($uri);
		return $blob === null ? null : (string)$blob;
	}
	
	public function testGetHelloTxt() {
		$expectedText =
			"Hello, world!\n".
			"But this file also has a second line.\n";
		$this->assertEquals( $expectedText, $this->getString('x-git-object:3b9b4a035b1044993d9d4fbdc907bfa94e192a92') );
	}
	
	public function testGetDoesntExist() {
		$this->assertNull( $this->getString('x-git-object:187aa86ca9a4277664d227183eec11981b201537') );
	}
	
	public function testStore() {
		$rand = rand(1000000,3999999)."-".rand(1000000,3999999);
		$urn = $this->repo->putBlob( Nife_Util::blob($rand) );
		$this->assertTrue( (bool)preg_match('/^x-git-object:[a-f0-9]{40}$/',$urn) );
		$this->assertEquals( $rand, $this->getString($urn), "'$rand' should have been stored and retrievable as '$urn'" );
	}
}
