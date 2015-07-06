<?php

class TOGoS_PHPN2R_Browser
{
	const RDF_TYPE    = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type';
	const CC_NAME     = 'http://ns.nuke24.net/ContentCouch/name';
	const CC_DIRECTORY= 'http://ns.nuke24.net/ContentCouch/Directory';
	const CC_BLOB     = 'http://ns.nuke24.net/ContentCouch/Blob';
	const CC_TARGET   = 'http://ns.nuke24.net/ContentCouch/target';
	const CC_ENTRIES  = 'http://ns.nuke24.net/ContentCouch/entries';
	const CC_FILESIZE = 'http://bitzi.com/xmlns/2002/01/bz-core#fileLength';
	const CC_MODIFIED = 'http://purl.org/dc/terms/modified';
	
	const MAGNET_ICON_URN = 'urn:bitprint:OXZQH7646W7BZNKAVICRY3OLXVT6YCXY.OQMVOR7HILTKLCMPGB44Y7T2YXZAI2S2BAODXHY';
	
	protected static function rdfObjectToValue( TOGoS_XMLRDFParser_RDF_RDFObject $rdfObj ) {
		switch( $rdfObj->getRdfTypeName() ) {
		case TOGoS_XMLRDFParser_RDF_Namespaces::RDF_DATA:
			return $rdfObj->getDataValue();
		case TOGoS_XMLRDFParser_RDF_Namespaces::RDF_COLLECTION:
			$values = [];
			foreach( $rdfObj->getItems() as $item ) $values[] = self::rdfObjectToValue($item);
			return $values;
		default:
			$val = [];
			if( ($uri = $rdfObj->getUri()) !== null ) {
				$val['uri'] = $uri;
			}
			foreach( $rdfObj->getProperties() as $k=>$vals ) {
				foreach($vals as $v) $val[$k] = self::rdfObjectToValue($v);
			}
			return $val;
		}
	}
	
	protected function browseRdfDirectory( $directoryXml, $linkMaker ) {
		if( !class_exists('TOGoS_XMLRDFParser_RDF_XMLRDFifier') ) {
			return "<p>TOGoS_XMLRDFParser_RDF_XMLRDFifier doesn't exist, or I would do something cool with all this.</p>";
		}
		
		$rdfifier = new TOGoS_XMLRDFParser_RDF_XMLRDFifier();
		$directory = $rdfifier->parse( $directoryXml );
		$dVal = self::rdfObjectToValue($directory);
		
		if( empty($dVal[self::CC_ENTRIES]) ) {
			return "<p>An empty directory.</p>";
		}
		
		$lines = [];
		$lines[] = "<table>";
		$lines[] = "<tr><th>Filename</th><th></th><th></th><th>Size</th><th>Modified</th></tr>";
		foreach( $dVal[self::CC_ENTRIES] as $entry ) {
			$target = $entry[self::CC_TARGET];
			if( preg_match('/^(?:x-rdf-subject|x-parse-rdf):(.*)$/',$target['uri'],$bif) ) {
				$uri = $bif[1];
				$defaultService = 'browse';
			} else {
				$uri = $target['uri'];
				$defaultService = 'raw';
			}
			$name = $entry[self::CC_NAME];
			$isDirectory = $target[self::RDF_TYPE]['uri'] === self::CC_DIRECTORY;
			$text = $name.($isDirectory ? '/' : '');
			$modified = isset($entry[self::CC_MODIFIED]) ? $entry[self::CC_MODIFIED] : null;
			$size = isset($target[self::CC_FILESIZE]) ? $target[self::CC_FILESIZE] : null;
			$browseUrl = $linkMaker->componentUrl('browse', $uri, $name);
			$magnetUrl = "magnet:?xt=".urlencode($uri)."&dn=".urlencode($name);
			
			$lines[] = "<tr>".
				"<td>".$linkMaker->serviceHtmlLinkForUrn($defaultService, $uri,$name,$text)."</td>".
				($isDirectory ?
				 ("<td></td></td></td>") :
				 ("<td><a class=\"browse-link\" href=\"".htmlspecialchars($browseUrl)."\">b</a></td>".
				  "<td><a class=\"magnet-link\" href=\"".htmlspecialchars($magnetUrl)."\"></a></td>")).
				"<td>$size</td><td>".htmlspecialchars($modified)."</td>".
				"</tr>";
		}
		$lines[] = "</table>";
		
		return implode("\n", $lines);
	}
	
	protected function normalBrowsePageContent( $content, $linkMaker ) {
		$contentHtml = htmlspecialchars($content);
		// Since we're not using it in an attribute value, we can switch the quotes back:
		$contentHtml = str_replace('&quot;','"',$contentHtml);
		$contentHtml = preg_replace_callback(
			'#(?:urn|(?:x-ccouch-head|x-parse-rdf|(?:(?:x-)?rdf-)?subject(?:-of)?)):(?:[A-Za-z0-9:_%+.-]+)#',
			array($linkMaker,'urnHtmlLinkReplacementCallback'), $contentHtml
		);
		// This replacement is useful when browing JSON documents that
		// use base32-encoded SHA-1s (with no urn: prefix) as 'blob IDs'
		$contentHtml = preg_replace_callback(
			//'#(?<=")[A-Z2-7]{32}(?=")#', // Match any blob ID surrounded by double-quotes
			'#(?<!\w)[A-Z2-7]{32}(?!\w)#', // Match a blob ID surrounded by anything other than word chars
			array($linkMaker,'base32Sha1HtmlBrowseLinkReplacementCallback'),
			$contentHtml
		);
		return
			"<pre>".
			$contentHtml.
			"</pre>\n";
	}
	
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
			$sections = [];
			
			if( preg_match('#<Directory xmlns="http://ns.nuke24.net/ContentCouch/"[^>]*>(.*)</Directory>\s*#s', $content, $bif) ) {
				$sections[] = $this->browseRdfDirectory( $content, $linkMaker );
			}
		
			$sections[] = $this->normalBrowsePageContent( $content, $linkMaker );
			
			$pageContent = "<hr />\n".implode("\n\n<hr />\n\n", array_filter($sections));
		}
		
		$rawUrl = $rp . ($filenameHint === null ? 'N2R?'.$urn : 'raw/'.$urn.'/'.$filenameHint);
		$links = array();
		if($filenameHint) $links[] = $linkMaker->htmlLinkForUrn($urn,$filenameHint,'Raw');
		$links[] = $linkMaker->rawHtmlLinkForUrn($urn,null,'N2R');
		
		$magnetIconUrl = $linkMaker->componentUrl('raw', self::MAGNET_ICON_URN, 'Magnet-icon.gif');
		
		$html =
			"<html>\n".
			"<head>\n".
			"<title>$title</title>\n".
			"<style>\n".
			"@media screen { body { font-family: sans-serif } }\n".
			"a.magnet-link:after { content: url($magnetIconUrl); }\n".
			"table { border-collapse: collapse; }\n".
			"table td { padding: 2px 6px; }\n".
			"table tr:nth-child(2n+2) { background-color: rgba(0,0,255,0.07); }\n".
			"table td:nth-child(2n+2) { background-color: rgba(0,0,255,0.07); }\n".
			"</style>\n".
			"</head><body>\n".
			"<p>$blobSize bytes | ".implode(' | ',$links)."</p>\n".
			$pageContent.
			"</body>\n</html>\n";
		
		return Nife_Util::httpResponse(200, $html, "text/html; charset=utf-8");
  }
}
