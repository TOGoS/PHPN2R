<?php

class TOGoS_PHPN2R_Browser
{
	public function browseBlob( $blob, $urn, $filenameHint, $rp ) {
		$browseSizeLimit = 1024*1024*10;
		$blobSize = $blob->getLength();
		
		$tooBig = $blobSize === null || $blobSize > $browseSizeLimit;
		
		$linkMaker = new TOGoS_PHPN2R_LinkMaker($rp);
		$title = ($filenameHint ? "$filenameHint ($urn)" : $urn).' - PHPN2R blob browser';
		
		if( $tooBig ) {
			$pageContent = "<p>This file is too big (> $browseSizeLimit bytes) to analyze.</p>\n";
		} else {
			$content = (string)$blob;
			$contentHtml = htmlspecialchars($content);
			$contentHtml = preg_replace_callback(
				'#(?:urn|(?:x-ccouch-head|x-parse-rdf|(?:(?:x-)?rdf-)?subject(?:-of)?)):(?:[A-Za-z0-9:_%+.-]+)#',
				array($linkMaker,'urnHtmlLinkReplacementCallback'), $contentHtml
			);
			$pageContent =
				"<hr />\n".
				"<pre>".
				$contentHtml.
				"</pre>\n";
		}
		
		$rawUrl = $rp . ($filenameHint === null ? 'N2R?'.$urn : 'raw/'.$urn.'/'.$filenameHint);
		$links = array();
		if($filenameHint) $links[] = $linkMaker->htmlLinkForUrn($urn,$filenameHint,'Raw');
		$links[] = $linkMaker->rawHtmlLinkForUrn($urn,null,'N2R');
		
		$html =
			"<html>\n".
			"<head>\n".
			"<title>$title</title>\n".
			"</head><body>\n".
			"<p>$blobSize bytes | ".implode(' | ',$links)."</p>\n".
			$pageContent.
			"</body>\n</html>\n";
		
		return Nife_Util::httpResponse(200, $html, "text/html; charset=utf-8");
  }
}
