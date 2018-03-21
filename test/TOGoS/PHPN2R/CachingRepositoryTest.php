<?php

class TOGoS_PHPN2R_CachingRepositoryTest extends TOGoS_SimplerTest_TestCase
{
	protected $backingRepos;
	protected $cachingRepo;
	public function setUp() {
		$backingRepoSpecs = array(
			'localX' => array('isNear'=>true, 'isCache'=>true , 'isStore'=>true ),
			'localY' => array('isNear'=>true, 'isCache'=>false, 'isStore'=>true ),
			'localZ' => array('isNear'=>true, 'isCache'=>true , 'isStore'=>false),
			'localQ' => array('isNear'=>true, 'isCache'=>false, 'isStore'=>false),
			'farA'  => array('isNear'=>false, 'isCache'=>false, 'isStore'=>true ),
			'farB'  => array('isNear'=>false, 'isCache'=>false, 'isStore'=>false),
		);
		$this->backingRepos = array();
		$config = array();
		foreach( $backingRepoSpecs as $k => $rs ) {
			$this->backingRepos[$k] = new TOGoS_PHPN2R_FSSHA1Repository(".test-repos/{$k}");
			if( $rs['isNear'] )  $config[ 'nearRepoNames'][$k] = $k;
			else                 $config[  'farRepoNames'][$k] = $k;
			if( $rs['isCache'] ) $config['cacheRepoNames'][$k] = $k;
			if( $rs['isStore'] ) $config['storeRepoNames'][$k] = $k;
		}
		$this->cachingRepo = new TOGoS_PHPN2R_CachingRepository( $this->backingRepos, $config );
	}
	
	protected static function generateBlob() {
		return Nife_Util::blob(rand(100000,999999)."-".rand(100000,999999)."-".rand(100000,999999));
	}
	
	protected static function mkSet(array $arr) {
		$set = array(); foreach($arr as $v) $set[$v] = $v; return $set;
	}
	
	protected function assertBlobPresence( $urn, $repoList ) {
		if( is_scalar($repoList) ) $repoList = self::mkSet(explode(',',$repoList));
		foreach( $this->backingRepos as $k=>$repo ) {
			$blob = $repo->getBlob($urn);
			if( isset($repoList[$k]) ) {
				$this->assertNotNull( $blob, "Expected repository '$k' to contain thing, but it did not" );
			} else {
				$this->assertNull( $blob, "Expected repository '$k' not to contain thing, but it did" );
			}
		}
	}
	
	public function testFetch() {
		$blob = self::generateBlob();
		
		$urn = $this->backingRepos['farA']->putBlob( $blob );
		$this->assertBlobPresence( $urn, 'farA' );
		
		$this->cachingRepo->getBlob($urn);
		// Now it should be in farA and also any 'iscache' repos
		$this->assertBlobPresence( $urn, 'farA,localX,localZ' );
	}
	
	public function testStore() {
		$blob = self::generateBlob();
		
		$urn = $this->cachingRepo->putBlob( $blob );
		// Now it should be in all 'isstore' repos
		$this->assertBlobPresence( $urn, 'farA,localX,localY' );

		$this->cachingRepo->getBlob($urn);
		// Since it was already in a local one, nothing should have changed
		$this->assertBlobPresence( $urn, 'farA,localX,localY' );
	}
}
