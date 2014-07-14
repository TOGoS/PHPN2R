<?php

class TOGoS_PHPN2R_FSSHA1Repository implements TOGoS_PHPN2R_Repository
{
	protected $dir;
	
	public function __construct( $dir ) {
		$this->dir = $dir;
	}
	
	public static function urnToBasename( $urn ) {
		if( preg_match( '/^urn:(?:sha1|bitprint):([0-9A-Z]{32})/', $urn, $bif ) ) {
			return $bif[1];
		}
		return null;
	}
	
	public function getDir() {
		return $this->dir;
	}

	/**
	 * Extract an SHA1 hash from a sha1/bitprint URN, hex-encoded
	 * string, or non-encoded string (i.e. the has itself)
	 */
	public static function extractSha1( $string ) {
		if( preg_match( '/^(?:(?:urn:)?(?:sha1|bitprint):)?([0-9A-Z]{32})(?:$|\W)/i', $string, $bif ) ) {
			return TOGoS_PHPN2R_Base32::decode($bif[1]);
		} else if( preg_match('/^[0-9a-f]{40}$/i', $string) ) {
			return hex2bin($string);
		} else if( strlen($string) == 20 ) {
			return $string;
		} else {
			throw new Exception("Unable to extract SHA-1 from string '$string'");
		}
	}
	
	// It might make more sense for this to be in server instead of
	// repository so that the latest head can be found across multiple
	// repositories.
	protected function getHeadBlob( $path ) {
		// Allow colons in place of slashes to work
		// around Apache/PHP getting confused about them.
		$path = strtr($path, array(':'=>'/'));
		// No dot-dots allowed!
		if( strpos($path, '..') !== false) return null;
		if( $path[0] == '/' ) return null;
		
		$headsDir = $this->dir.'/heads';
		if( preg_match('#(.+?)/latest$#',$path,$bif) ) {
			$headDir = $headsDir.'/'.$bif[1];
			if( !is_dir($headDir) ) return null;
			$dh = opendir($headDir);
			if( $dh === false ) return null;
			$headNumbers = array();
			while( ($en = readdir($dh)) !== false ) {
				if( $en[0] == '.' ) continue;
				$headNumbers[] = $en;
			}
			closedir($dh);
			natsort($headNumbers);
			if( count($headNumbers) == 0 ) return null;
			$latest = array_pop($headNumbers);
			$file = "{$headDir}/{$latest}";
		} else {
			$file = $headsDir.'/'.$path;
		}
		if( file_exists($file) ) {
			return new TOGoS_PHPN2R_FileBlob($file);
		}
		return null;
	}
	
	public function getBlob( $urn ) {
		if( preg_match('/^(?:x-)ccouch-head:(.*)$/', $urn, $bif) ) {
			return $this->getHeadBlob($bif[1]);
		}
		
		$basename = self::urnToBasename($urn);
		if( $basename === null ) return null;
		
		$first2 = substr($basename,0,2);
		
		$dataDir = $this->dir.'/data';
		if( !is_dir($dataDir) ) {
			// This may be due to something not being mounted,
			// or it may be a configuration error.
			// It might be good to log this somewhere,
			// but for now we'll just let it slide.
			return null;
		}
		$dir = opendir( $dataDir );
		$fil = null;
		while( $dir !== false and ($en = readdir($dir)) !== false ) {
			$fil = "$dataDir/$en/$first2/$basename";
			if( is_file($fil) ) break;
			else $fil = null;
		}
		closedir($dir);
		return $fil ? new TOGoS_PHPN2R_FileBlob($fil) : null;
	}
	
	/**
	 * Moves a file to its proper location in the repository.
	 * Hash must already have been calculated and verified.
	 * @return string the destination path
	 */
	protected function insertTempFile( $tempFile, $sector, $hash ) {
		$basename = TOGoS_PHPN2R_Base32::encode($hash);
		$first2 = substr($basename,0,2);
		$dataDir = $this->dir.'/data';
		$destDir = "$dataDir/$sector/$first2";
		$destFile = "$destDir/$basename";
		if( !is_dir($destDir) ) mkdir( $destDir, 0755, true );
		if( !is_dir($destDir) ) throw new Exception("Failed to create directory: $destDir");
		rename( $tempFile, $destFile );
		return $destFile;
	}
	
	public function putTempFile( $tempFile, $sector='uploaded', $expectedSha1=null ) {
		if( $expectedSha1 !== null) $expectedSha1 = self::extractSha1($expectedSha1);
		
		$tempFr = fopen($tempFile,'rb');
		$hash = hash_init('sha1');
		while( !feof($tempFr) ) {
			$data = fread( $tempFr, 1024*1024 );
			hash_update( $hash, $data );
		}
		fclose( $tempFr );
		$hash = hash_final( $hash, true );
		
		if( $expectedSha1 !== null and $hash != $expectedSha1 ) {
			throw new Exception(
				"Hash of temp file '$tempFile' does not match expected: ".
				TOGoS_PHPN2R_Base32::encode($hash)." != ".
				TOGoS_PHPN2R_Base32::encode($expectedSha1)
			);
		}
		
		$this->insertTempFile( $tempFile, $sector, $hash );
		return "urn:sha1:".TOGoS_PHPN2R_Base32::encode($hash);
	}

	public function putStream( $stream, $sector='uploaded', $expectedSha1=null ) {
		if( $expectedSha1 !== null) $expectedSha1 = self::extractSha1($expectedSha1);
		
		$dataDir = $this->dir.'/data';
		$tempDir = "{$dataDir}/{$sector}";
		$tempFile = "{$tempDir}/.temp-".rand(1000000,9999999).'-'.rand(1000000,9999999);
		if( !is_dir($tempDir) ) mkdir($tempDir,0755,true);
		$tempFw = fopen($tempFile,'wb');
		if( $tempFw === null ) {
			throw new Exception("Unable to open temp file '{$tempFile}' in 'wb' mode");
		}
				
		$hash = hash_init('sha1');
		while( !feof($stream) ) {
			$data = fread( $stream, 1024*1024 );
			hash_update( $hash, $data );
			fwrite( $tempFw, $data );
		}
		fclose( $tempFw );
		$hash = hash_final( $hash, true );
		
		if( $expectedSha1 !== null and $hash != $expectedSha1 ) {
			unlink( $tempFile );
			throw new Exception(
				"Hash of uploaded data does not match expected: ".
				TOGoS_PHPN2R_Base32::encode($hash)." != ".
				TOGoS_PHPN2R_Base32::encode($expectedSha1)
			);
		}
		
		$this->insertTempFile( $tempFile, $sector, $hash );
		return "urn:sha1:".TOGoS_PHPN2R_Base32::encode($hash);
	}
}
