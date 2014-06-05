<?php

class TOGoS_PHPN2R_FileBlob implements Nife_Blob
{
	protected $filePath;
	public function __construct( $filePath ) {
		$this->filePath = $filePath;
	}
	
	public function __toString() {
		return file_get_contents($this->filePath);
	}
	
	public function getLength() {
		return filesize($filePath);
	}
	
	public function writeTo( $callback ) {
		$fh = fopen($this->filePath,'rb');
		if( $fh === false ) {
			throw new Exception("Failed to open {$this->filePath} for reading");
		}
		while( $data = fread($fh, 65536) ) {
			call_user_func($callback, $data);
		}
		fclose($fh);
	}
}
