<?php

class TOGoS_PHPN2R_HashingBlobStreamWriter
{
	protected $stream;
	protected $hashing;
	public function __construct($stream, $hashing) {
		$this->stream = $stream;
		$this->hashing = $hashing;
	}
	public function write($chunk) {
		fwrite($this->stream, $chunk);
		hash_update( $this->hashing, $chunk );
	}
	public function __invoke($chunk) {
		$this->write($chunk);
	}
	public function closeAndDigest() {
		fclose($this->stream);
		return hash_final($this->hashing, true);
	}
}

class TOGoS_PHPN2R_FSSHA1Repository implements TOGoS_PHPN2R_Repository
{
	protected $dir;
	public $tempFilenamePrefix = ".temp";
	public $mkdirMode = 0755;
	public $verifyRenames = true;
	public $defaultStoreSector = 'uploaded';
	
	public function __construct( $dir ) {
		$this->dir = $dir;
	}
	
	public static function urnToBasename( $urn ) {
		if( preg_match( '/^urn:(?:sha1|bitprint):([0-9A-Z]{32})/', $urn, $bif ) ) {
			return $bif[1];
		}
		return null;
	}
	
	public function __toString() {
		return "FSSHA1Repository(".var_export($this->dir,true).")";
	}
	
	public function getDir() {
		return $this->dir;
	}
	
	protected function cleanReadDirEntry($en) {
		// Google Cloud Storage ('gs://...' handler) adds slashes on the
		// ends of directory names returned by readdir($parentdir) but
		// doesn't like dir//file paths, so strip them off:
		return substr($en,strlen($en)-1) == '/' ? substr($en,0,strlen($en)-1) : $en;
	}

	/**
	 * Extract an SHA1 hash from a sha1/bitprint URN, hex-encoded
	 * string, or non-encoded string (i.e. the has itself)
	 */
	public static function extractSha1( $string ) {
		if( preg_match( '/^(?:(?:urn:)?(?:sha1|bitprint):)?([0-9A-Z]{32})(?:$|\W)/i', $string, $bif ) ) {
			return TOGoS_Base32::decode($bif[1]);
		} else if( preg_match('/^[0-9a-f]{40}$/i', $string) ) {
			return hex2bin($string);
		} else if( strlen($string) == 20 ) {
			return $string;
		} else {
			throw new TOGoS_PHPN2R_IdentifierFormatException("Unable to extract SHA-1 from string '$string'");
		}
	}
	
	public static function sha1Urn( $hash ) {
		if( strlen($hash) != 20 ) {
			throw new TOGoS_PHPN2R_IdentifierFormatException("SHA-1 hash given should be a 20-byte string; got ".strlen($hash)." bytes");
		}
		return "urn:sha1:".TOGoS_Base32::encode($hash);
	}
	
	protected function tempFileInSector($sector) {
		$dataDir = $this->dir.'/data';
		$tempDir = "{$dataDir}/{$sector}";
		$tempFile = "{$tempDir}/{$this->tempFilenamePrefix}-".rand(1000000,9999999).'-'.rand(1000000,9999999);
		if( !is_dir($tempDir) ) {
			if( @mkdir($tempDir, $this->mkdirMode, true) === false ) {
				throw new Exception("Failed to mkdir {$tempDir}; might need to chmod 0777 it");
			}
		}
		return $tempFile;
	}
	
	// It might make more sense for this to be in server instead of
	// repository so that the latest head can be found across multiple
	// repositories.
	protected function getHeadFile( $path ) {
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
		return file_exists($file) ? $file : null;
	}
	
	public function getFile( $urn ) {
		if( preg_match('/^(?:x-)ccouch-head:(.*)$/', $urn, $bif) ) {
			return $this->getHeadFile($bif[1]);
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
			$en = $this->cleanReadDirEntry($en);
			$fil = "$dataDir/$en/$first2/$basename";
			if( is_file($fil) ) break;
			else $fil = null;
		}
		closedir($dir);
		return $fil;
	}
	
	public function getBlob( $urn ) {
		$file = $this->getFile($urn);
		return $file === null ? null : new Nife_FileBlob($file);
	}
	
	/**
	 * Moves a file to its proper location in the repository.
	 * Hash must already have been calculated and verified.
	 * @return string the destination path
	 */
	protected function insertTempFile( $tempFile, $sector, $hash ) {
		$basename = TOGoS_Base32::encode($hash);
		$first2 = substr($basename,0,2);
		$dataDir = $this->dir.'/data';
		$destDir = "$dataDir/$sector/$first2";
		$destFile = "$destDir/$basename";
		if( !is_dir($destDir) ) mkdir( $destDir, $this->mkdirMode, true );
		if( !is_dir($destDir) ) throw new Exception("Failed to create directory: $destDir");
		if( !rename( $tempFile, $destFile ) ) {
			throw new Exception("Failed to rename '$tempFile' to '$destFile'");
		}
		if( $this->verifyRenames and !file_exists($destFile) ) {
			throw new Exception("'$destFile' does not exist after renaming temp file.");
		}
		return $destFile;
	}
	
	public function putTempFile( $tempFile, $sector=null, $expectedSha1=null ) {
		if( $sector === null ) $sector = $this->defaultStoreSector;
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
			throw new TOGoS_PHPN2R_HashMismatchException(
				"Hash of temp file '$tempFile' does not match expected: ".
				TOGoS_Base32::encode($hash)." != ".
				TOGoS_Base32::encode($expectedSha1)
			);
		}
		
		$this->insertTempFile( $tempFile, $sector, $hash );
		return self::sha1Urn($hash);
	}
	
	public function putStream( $stream, $sector=null, $expectedSha1=null ) {
		if( $sector === null ) $sector = $this->defaultStoreSector;
		if( $expectedSha1 !== null) $expectedSha1 = self::extractSha1($expectedSha1);
		
		$tempFile = $this->tempFileInSector($sector);
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
			throw new TOGoS_PHPN2R_HashMismatchException(
				"Hash of uploaded data does not match expected: ".
				TOGoS_Base32::encode($hash)." != ".
				TOGoS_Base32::encode($expectedSha1)
			);
		}
		
		$this->insertTempFile( $tempFile, $sector, $hash );
		return self::sha1Urn($hash);
	}
	
	public function putString( $data, $sector=null, $expectedSha1=null ) {
		if( $sector === null ) $sector = $this->defaultStoreSector;
		if( $expectedSha1 !== null) $expectedSha1 = self::extractSha1($expectedSha1);
		
		$hash = sha1($data, true);
		if( $expectedSha1 !== null and $hash != $expectedSha1 ) {
			throw new TOGoS_PHPN2R_HashMismatchException(
				"Hash of uploaded data does not match expected: ".
				TOGoS_Base32::encode($hash)." != ".
				TOGoS_Base32::encode($expectedSha1)
			);
		}
		
		$tempFile = $this->tempFileInSector($sector);
		$tempFw = fopen($tempFile,'wb');
		if( $tempFw === false ) {
			throw new Exception("Failed to open temporary file {$tempFile} for writing");
		}
		fwrite($tempFw, $data);
		fclose($tempFw);
		$this->insertTempFile($tempFile, $sector, $hash);
		return self::sha1Urn($hash);
	}

	public function newTempFile(array $options=array()) {
		$sector = isset($options[TOGoS_PHPN2R_Repository::OPT_SECTOR]) ?
			$options[TOGoS_PHPN2R_Repository::OPT_SECTOR] : $this->defaultStoreSector;
		return $this->tempFileInSector($sector);
	}
	
	public function putBlob( Nife_Blob $blob, array $options=array() ) {
		$sector = isset($options[TOGoS_PHPN2R_Repository::OPT_SECTOR]) ?
			$options[TOGoS_PHPN2R_Repository::OPT_SECTOR] : $this->defaultStoreSector;
		$expectedSha1 = isset($options[TOGoS_PHPN2R_Repository::OPT_EXPECTED_SHA1]) ?
			$options[TOGoS_PHPN2R_Repository::OPT_EXPECTED_SHA1] : null;
		if( $blob instanceof Nife_FileBlob && !empty($options[TOGoS_PHPN2R_Repository::OPT_ALLOW_SOURCE_REMOVAL]) ) {
			$expectedUrn = $expectedSha1 ? self::sha1Urn($expectedSha1) : null;
			return $this->putTempFile( $blob->getFile(), $sector, $expectedUrn );
		} else {
			$tempFile = $this->tempFileInSector($sector);
			$tempFw = fopen($tempFile,'wb');
			if( $tempFw === null ) {
				throw new Exception("Unable to open temp file '{$tempFile}' in 'wb' mode");
			}
			
			$hash = hash_init('sha1');
			$HBSW = new TOGoS_PHPN2R_HashingBlobStreamWriter($tempFw, $hash);
			$blob->writeTo(array($HBSW,'write'));
			$hash = $HBSW->closeAndDigest();
			
			if( $expectedSha1 !== null and $hash != $expectedSha1 ) {
				unlink( $tempFile );
				throw new TOGoS_PHPN2R_HashMismatchException(
					"Hash of uploaded data does not match expected: ".
					TOGoS_Base32::encode($hash)." != ".
					TOGoS_Base32::encode($expectedSha1)
				);
			}
			
			$this->insertTempFile( $tempFile, $sector, $hash );
			return self::sha1Urn($hash);
		}
	}
}
