<?php
namespace wcf\system\event\listener;
use wcf\data\bbcode\BBCode;
use wcf\system\application\ApplicationHandler;
use wcf\system\event\IEventListener;
use wcf\system\Regex;
use wcf\system\WCF;
use wcf\util\HTTPRequest;
use wcf\util\StringUtil;

/**
 * Replace URLs by their names
 * 
 * @author	Sascha Greuel <sascha@softcreatr.de>
 * @copyright	2010-2013 Sascha Greuel
 * @license	Creative Commons BY-SA <http://creativecommons.org/licenses/by-sa/3.0/>
 * @package	de.softcreatr.wcf2.replaceurlbyname
 * @subpackage	system.event.listener
 * @category	Community Framework
 */
class ReplaceURLByNameListener implements IEventListener {
	/**
	 * @see	wcf\system\event\IEventListener::execute()
	 */
	public function execute($eventObj, $className, $eventName) {
		if (!$eventObj->text) {
			return;
		}
		
		// check if needed url BBCode is allowed
		if ($eventObj->allowedBBCodes !== null && !BBCode::isAllowedBBCode('url', $eventObj->allowedBBCodes)) {
			return;
		}
		
		$regex = new Regex('\[url(?|=[\'"]?+([^]"\']++)[\'"]?+]([^[]++)|](([^[]++)))\[/url\]', Regex::CASE_INSENSITIVE);
		if ($regex->match($eventObj->text, true, 8)) {			
			foreach ($regex->getMatches() as $match) {
				if (!isset($match[2])) {
					return;
				}
				
				if (empty($match[2]) || $match[1] == $match[2]) {
					$title = $this->fetchTitle($match[2]);
					
					if ($title) {
						$eventObj->text = StringUtil::replaceIgnoreCase($match[0], "[url='" . $match[2] . "']" . $title . "[/url]", $eventObj->text);
					}
				}
			}
		}
	}
	
	/**
	 * Returns the content of the Title-Tag from a given URL
	 */	
	private function fetchTitle($url) {
		$title = array();
		
		// add protocol if necessary
		if (!preg_match('/[a-z]:\/\//si', $url)) {
			$url = 'http://' . $url;
		}
		
		// do not continue, if url is an internal one
		if (ApplicationHandler::getInstance()->isInternalURL($url)) {
			return false;
		}
		
		// request
		try {
			$request = new HTTPRequest($url);
			$request->addHeader('User-Agent', 'HTTP.PHP (ReplaceURLByName; WoltLab Community Framework/' . WCF_VERSION . '; ' . WCF::getLanguage()->languageCode . ')');
			$request->execute();
			
			$reply = $request->getReply();
		}
		catch (\Exception $e) {
			return false;
		}
		
		// determine mime-type
		$mimeType = (isset($reply['headers']['Content-Type']) && !empty($reply['headers']['Content-Type']) ? $reply['headers']['Content-Type'] : false);
		
		// title tags should just appear in text/html documents
		if ($reply['statusCode'] > 0 && strpos($mimeType, 'text/html') !== false) {	
			$content = $reply['body'];
			preg_match('/\<title\>(.*)\<\/title\>/is', $content, $title);
		} else if ($mimeType) {
			// use the filename, if file exists but mime-type is not text/html
			$title[1] = basename(urldecode($url));
		}
		
		// return
		return (isset($title[1]) ? $this->niceTitle($title[1]) : false);
	}
	
	/**
	 * Convert bad formatted titles into good formatted titles
	 */    
	private function niceTitle($badTitle) {
		$encodings = array(
			// ascii
			'ASCII',
			
			// unicode
			'UTF-8',
			'UTF-16',
			
			//chinese
			'EUC-CN',	// gb2312
			'CP936',	// gbk
			'EUC-TW',	// big5
			
			// japanese
			'EUC-JP',
			'SJIS',
			'eucJP-win',
			'SJIS-win',
			'JIS',
			'ISO-2022-JP'
		);
		
		$niceTitle = StringUtil::decodeHTML(StringUtil::trim($badTitle));
		return StringUtil::convertEncoding(mb_detect_encoding($niceTitle, $encodings), 'UTF-8', $niceTitle);
	}
}
