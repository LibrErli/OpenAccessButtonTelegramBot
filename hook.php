<?php
/**
 * Telegram Bot Example whitout WebHook.
 * It uses getUpdates Telegram's API.
 * @author Gabriele Grillo <gabry.grillo@alice.it>
 */

include("Telegram.php");

$bot_id = ''; #Telegram-API-Key



/* Using REST-API of openaccessbutton.org
DOI-Example: http://dx.doi.org/10.1080/13614533.2017.1281827

*/

class GoobiTelegram extends Telegram {
		
	public function setProcessID($processID){
		$this->processID = $processID;
	}
	
	public function setTinyURLAPI($tinyurl_api){
		$this->tinyurl_api = $tinyurl_api;
	}
	public function setReplyText($string){
		$this->text = $string;
	}
	
	
	public function sendResult($chat_id,$result){
		$option = array();
		foreach($result["response"]["docs"] as $item){
			#$item['MD_AUTHOR'][0].", ".$item['MD_TITLE'][0].$item['SORT_YEARPUBLISH'];
			array_push($option,array($this->buildInlineKeyboardButton($text=$item['MD_CREATOR'][0].": ".$item['MD_TITLE'][0].", ".$item['SORT_YEARPUBLISH'], "", "{\"PI_ID\": \"".$item['PI_TOPSTRUCT']."\" }","")));		
		}
		
		/*Registering all TinyURL takes a few seconds, the first ten results are able to display immediately, so we will send them to user now */
		$reply='We found '.$result["response"]["numFound"].' Documents searching for <i>'.$result["responseHeader"]["params"]["q"].'</i>';
		$keyb = $this->buildInlineKeyBoard($option);
		$content = array('chat_id' => $chat_id, 'text' => $reply, 'parse_mode' => 'HTML', 'reply_markup' => $keyb); 
		$this->sendMessage($content);
		
		$option = array();
		$reply='Browse through the Resultset';  # An empty Message isn't allowed...
		
		/*Creating the Paginator-Tool, if the resultset is greater then 10 */
		$solrURL = "https://emedien.arbeiterkammer.at/solr/collection1/select?q=".urlencode($result["responseHeader"]["params"]["q"])."&indent=true&wt=json&rows=".$result["responseHeader"]["params"]["rows"];
		#Enter Result-Set-Paginator
		$pages = paginator($result["responseHeader"]["params"]["start"],$result["response"]["numFound"],$result["responseHeader"]["params"]["rows"]);
		if(!empty($pages)){ 
			$pages = json_decode($pages,true); 
			$nav_option = array();
			for($i=0;$i<count($pages);$i++){
				#Buildling an seperat Array for each Navigation-Button.
				#The callback_data field is limited to 64 bytes, so the solrURL has to be converted to a tinyURL first.
				$longurl = $solrURL."&start=".$pages[$i]["start"];
				$tinyurl = registerTinyURL($longurl,$this->tinyurl_api);
				array_push($nav_option,$this->buildInlineKeyboardButton($text=$pages[$i]["label"],"", "{\"solrURL\": \"".$tinyurl."\" }",""));
				#$content = array('chat_id' => $chat_id, 'text' => $longurl."\n\n".$tinyurl, 'parse_mode' => 'HTML', 'reply_markup' => $keyb); 
				#$this->sendMessage($content);
			}
			
			array_push($option,$nav_option);
		$keyb = $this->buildInlineKeyBoard($option);
		
		#if(!empty($search_field)){ $reply .= trim($command[0])." "; }
		#$reply .= $search_string;
		$content = array('chat_id' => $chat_id, 'text' => $reply, 'parse_mode' => 'HTML', 'reply_markup' => $keyb); 
		$this->sendMessage($content);
		}
		}
		
		
	
	public function sendPDF($chat_id){
		
		
		
		if(!empty($this->processID)){
			$file_path=shell_exec('bash /home/cerlingeadmin/bot/getPDF.sh '.$this->processID);
			$reply=$this->text." is found @ <a href='https://resolver.obvsg.at/".$this->text."'>emedien.arbeiterkammer.at</a> ";
			#$reply=$text." is found in Goobi-Process#".$processID."\nDatei: ".$file_path;
			$content = array('chat_id' => $chat_id, 'text' => $reply, 'parse_mode' => 'HTML');
			$this->sendMessage($content);
		
			
			/* 
			#Photo-Versand
			$img = curl_file_create('image.jpg','image/jpg'); 
			$content = array('chat_id' => $chat_id, 'photo' => $img );
			$telegram->sendPhoto($content); */
			
			$doc = curl_file_create(trim($file_path),'application/pdf'); 
			$content = array('chat_id' => $chat_id, 'document' => $doc );
			$this->sendDocument($content);
		}
		else {
			$reply = "Unfortunately ".$this->text." is not found automatically.\nPlease visit <a href='https://emedien.arbeiterkammer.at'>emedien.arbeiterkammer.at</a>";
			$content = array('chat_id' => $chat_id, 'text' => $reply, 'parse_mode' => 'HTML');
			$this->sendMessage($content);
		}
	}
	
}



$telegram = new GoobiTelegram($bot_id);
$telegram->setTinyURLAPI($tinyurl_api);

$result = $telegram->getData();
	
	$text = $result["message"] ["text"];
	$chat_id = $result["message"] ["chat"]["id"];
	$user_name = $result["message"] ["from"]["first_name"];
	
	if($text=='Hallo' or $text =='Hi' or $text == '/start' or $text == '/help'){
		$reply="Hello ".$user_name."!\n";
	}
	else {
		$reply="";
	}
	
	

$callback_query = $telegram->Callback_Query();
	

if(!empty($callback_query)){
	/* Callback_Data should be encoded as JSON to optimize Callback-Handling in this section */
	#$reply = "Callback data value: ".$telegram->Callback_Data();
	
	$str = json_decode($telegram->Callback_Data(),true);
	

	if(array_key_exists('solrURL',$str)){
		$callback_text = $str['solrURL'];	
			
		$result = getSolrResponseViaURL($callback_text);		
		$telegram->sendResult($telegram->Callback_ChatID(),$result);
		$chat_id = $telegram->Callback_ChatID();
		#$content = array('chat_id' => $chat_id, 'text' => "solrURL exists", 'parse_mode' => 'Markdown', 'reply_markup' => $keyb); 
		#$telegram->sendMessage($content);	
	}
	else {
		if(array_key_exists('URN',$str)){
			$callback_text = $str['URN'];	
		}
		
		if(array_key_exists('PI_ID',$str)){
			$callback_text = $str['PI_ID'];
		}
		
		$telegram->setProcessID(getProcessID($callback_text,$db));
		
		$telegram->getMetaXML();
		$urn = $telegram->xml->getNodeValue("(//goobi:goobi/goobi:metadata[@name='_urn'])[1]");
		$telegram->setReplyText($urn);
			
		$telegram->sendPDF($telegram->Callback_ChatID());
		$chat_id = $telegram->Callback_ChatID();
	}

	
}
else {
	#Processing of Users-Text-Input
	/*if(preg_match('/^\/doi/',$text,$match)){	
		#If an URN is given, return a PDF if exists.
		#$telegram->setProcessID(getProcessID($text,$db));	
		#$telegram->setReplyText("DOI");
		
		$content = array('chat_id' => $chat_id, 'text' => $match[0], 'parse_mode' => 'HTML'); 
		$telegram->sendMessage($content);	
		
	}*/	
	#elseif($text=='Hallo' or $text =='Hi' or $text == '/start' or $text == '/help') {
	if($text=='Hallo' or $text =='Hi' or $text == '/start' or $text == '/help') {
		#Introduction, Start or Help Information
		$reply .= "*This is the Open Access TelegramBot.*
		Send a permanent identifier (e.g. DOI, Handle, URN) or an URL of your desired article and we will look for it in different Open Access Repositories.
		
		If you send the /start or /help command you will receive this message one more again.";
		#$option = array(array($telegram->buildInlineKeyboardButton($text = 'Test: urn:nbn:at:at-akw:g-781021',"","{ \"URN\": \"urn:nbn:at:at-akw:g-781021\" }","")));
		#$keyb = $telegram->buildInlineKeyBoard($option);
		#$content = array('chat_id' => $chat_id, 'text' => $reply, 'parse_mode' => 'Markdown', 'reply_markup' => $keyb); 
		$content = array('chat_id' => $chat_id, 'text' => $reply, 'parse_mode' => 'Markdown'); 
		$telegram->sendMessage($content);	
	}			
	else {
		#Main-Element of Bot-Interaction: Search the Repository
		
		#Preparing Searchstring and Index-Field if given
		if(preg_match('/^\//',$text)){
			$repl_str = "command found";
			preg_match('/^\/[\w].+?[\s]/',$text,$command);
			preg_match('/(^\/[\w].+?[\s])(.+)/',$text,$string);
			switch(trim($command[0])){
				case '/doi':
					$search_field = "doi";
					break;
				case '/title':
					$search_field = "title";
					break;
				case '/author':
					$search_field = "MD_AUTHOR";
					break;
				case '/ddc':
					$search_field = "MD_DDC";
					break;
				case '/keyword':
					$search_field = "MD_KEYWORDS";
					break;
				default:
					$search_field = "url";
					break;
			}
			$repl_str .= " ".$command[0];
			if(array_key_exists(2,$string)) { $search_string = $string[2]; } else {$search_string = "*"; }
		}
		else {
			$repl_str = "no command found";
			$search_string = $text;
			$search_field = "url";
		}
			
		$oabuttonavail=OAButtonAvail($search_string,$search_field);	
		$pdfURL = getPdfFromHtmlMeta($oabuttonavail[1]);
		#Test-Message to display Command and Search-String
		#$content = array('chat_id' => $chat_id, 'text' => $oabuttonavail[0].$oabuttonavail[3], 'parse_mode' => 'Markdown'); 
		$content = array('chat_id' => $chat_id, 'text' => $oabuttonavail[0]."*".$oabuttonavail[2]."*\n".$oabuttonavail[1], 'parse_mode' => 'Markdown'); 
		#$content = array('chat_id' => $chat_id, 'text' => $repl_str." ".$search_field." ".$search_string, 'parse_mode' => 'HTML'); 
		$telegram->sendMessage($content);	
		
		if(!empty($pdfURL)){
			#$doc = curl_file_create(trim($pdfURL),'application/pdf'); 
			$content = array('chat_id' => $chat_id, 'document' => $pdfURL );
			$telegram->sendDocument($content);
		}
				
		#$result = getSolrResponse($search_string,$search_field);		
		#$telegram->sendResult($chat_id,$result);
		
	}	
}


function OAButtonAvail($search_string,$search_field){
	
	#https://api.openaccessbutton.org/availability?doi=10.1080/13614533.2017.1281827
	$oaBurl = "https://api.openaccessbutton.org/availability?".$search_field."=".urlencode($search_string);
	
	$json = file_get_contents($oaBurl);
	$res = json_decode($json,true);
	
	#var_dump($res);
	#var_dump($res["data"]["availability"][0]["url"]);
	
	$url = $res["data"]["availability"][0]["url"];
	$title = $res["data"]["meta"]["article"]["title"];
	
	if(!empty($url)){ $oabuttonavail=array("This article is available!\n",$url,$title,$oaBurl); }
	else { $oabuttonavail=array("This article is unfortunately not available open access!\n*".$title."*\nRequest it from the author:\n https://openaccessbutton.org/request?type=article&url=".$search_string."\n","","",$oaBurl); }
	
	return $oabuttonavail;
	
}

function getPdfFromHtmlMeta($url){
	
	if(!empty($url)){
		$doc = new DOMDocument();
		$doc->loadHTML(file_get_contents($url));
		
		$xp = new DOMXPath($doc);
		#foreach($xp->query("//meta[@name='DC.identifier' and contains(@content,'.pdf')]") as $metatag){
		foreach($xp->query("//meta[ contains(@content,'.pdf') or contains(@content,'?pdf=')]") as $metatag){
			#var_dump($metatag->getAttribute('content'))."<br/>";
			return $metatag->getAttribute('content');
			#echo $metatag["nodeValue"];
		}
	}
}


?>