<?php

/**
 * Attempts to load blobs based on alternate mapped URIs
 * which may be loaded as needed from tab-separated URI equivalence files.
 */
class TOGoS_PHPN2R_GitRepository implements TOGoS_PHPN2R_Repository
{
	protected $gitDir;
	
	protected function gitSys(array $args) {
		array_unshift($args, '--git-dir='.$this->gitDir);
		array_unshift($args, 'git');
		foreach( $args as &$arg ) $arg = escapeshellarg($arg);
		unset($arg);
		$cmd = implode(' ',$args).' 2>&1';
		//echo "$ $cmd\n";
		ob_start();
		passthru($cmd, $status);
		$output = ob_get_clean();
		if( $status === 128 ) return null;
		if( $status ) {
			throw new Exception("Command failed with exit status $status: $cmd\n".$output);
		}
		return $output;
	}
	
	public function __construct($gitDir) {
		$this->gitDir = $gitDir;
	}
	
	public function getBlob($urn) {
		if( preg_match('/^x-git-object:([0-9a-f]{40})\b/', $urn, $bif) ) {
			return $this->gitSys(array('cat-file', 'blob', $bif[1]));
		}
		return null;
	}
	
	public function putStream($stream, $sector='uploaded', $expectedUrn=null) {
		throw new Exception(get_class($this).'#putStream not implemented');
	}

	public function putBlob(Nife_Blob $blob, $sector='uploaded', $expectedUrn=null) {
		$string = (string)$blob;
		$hashedThing = "blob ".strlen($string)."\0".$string;
		$hashHex = hash('sha1',$hashedThing,false);
		$urn = "x-git-object:$hashHex";
		if( $expectedUrn !== null and $expectedUrn !== $urn ) {
			throw new HashMismatchException("Generated URN did not match expected: $urn != $expectedUrn");
		}
		$deflated = gzcompress($hashedThing); // Equivalent to Zlib::Deflate.deflate in Ruby.
		$outputDir = "{$this->gitDir}/objects/".substr($hashHex,0,2);
		if( !is_dir($outputDir) ) mkdir($outputDir,0755,true);
		$outputFile = $outputDir.'/'.substr($hashHex,2);
		file_put_contents($outputFile, $deflated);
		return $urn;
	}
}
