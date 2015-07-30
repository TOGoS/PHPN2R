<?php

class TOGoS_PHPN2R_LinkMaker {
	protected $rp;
	/**
	 * @param string $rp Root path prefix; relative URI, including trailing slash, to uri-res/
	 */
	public function __construct( $rp ) {
		$this->rp = $rp;
	}
	public function componentUrl( $comp, $urn, $filenameHint=null ) {
		if( $comp == 'raw' and $filenameHint === null ) {
			return $this->rp.'N2R?'.$urn;
		} else {
			return $this->rp.$comp.'/'.$urn.($filenameHint === null ? '' : '/'.$filenameHint);
		}
	}
	public function htmlLink( $url, $text ) {
		return "<a href=\"".htmlspecialchars($url)."\">".htmlspecialchars($text)."</a>";
	}
	public function serviceHtmlLinkForUrn( $service, $urn, $filenameHint, $text ) {
		return $this->htmlLink($this->componentUrl($service, $urn, $filenameHint), $text);
	}
	public function rawHtmlLinkForUrn( $urn, $filenameHint, $text ) {
		return $this->serviceHtmlLinkForUrn('raw', $urn, $filenameHint, $text);
	}
	public function htmlLinkForUrn( $urn, $filenameHint, $text ) {
		if( $text === null ) $text = $urn;
		if( preg_match('/^(x-parse-rdf|(?:x-)?(?:rdf-)?subject(?:-of)?):(.*)$/',$urn,$bif) ) {
			$subjectScheme = $bif[1];
			$blobUrn = $bif[2];
			if( $text == $urn ) {
				return
					$this->htmlLink($this->componentUrl('browse', $blobUrn, $filenameHint), $subjectScheme).':'.
					$this->rawHtmlLinkForUrn($blobUrn, $filenameHint, $blobUrn);
			} else {
				return $this->htmlLink($this->componentUrl('browse', $blobUrn, $filenameHint), $text);
			}
		} else {
			return
				$this->htmlLink($this->componentUrl('raw', $urn, $filenameHint), $text).'['.
				$this->htmlLink($this->componentUrl('browse', $urn, $filenameHint), 'browse').']';
		}
	}
	public function urnHtmlLinkReplacementCallback( $matches ) {
		return $this->htmlLinkForUrn( $matches[0], null, $matches[0] );
	}
	public function base32Sha1HtmlBrowseLinkReplacementCallback( $matches ) {
		$urn = "urn:sha1:".$matches[0];
		return $this->serviceHtmlLinkForUrn( 'browse', $urn, null, $matches[0] );
	}
	public function genericBrowseLinkReplacementCallback( $matches ) {
		$thing = $matches[0];
		if( preg_match('/^[A-Z2-7]{32}$/', $thing) ) {
			return $this->base32Sha1HtmlBrowseLinkReplacementCallback($matches);
		} else {
			return $this->urnHtmlLinkReplacementCallback($matches);
		}
	}
}
