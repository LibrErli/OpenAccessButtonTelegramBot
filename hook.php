<?php
/**
Telegram-Bot Webhook for OpenAccessButton
https://telegram.me/bOAt_oabot
*/

/* Using REST-API of openaccessbutton.org
DOI-Example: http://dx.doi.org/10.1080/13614533.2017.1281827

*/

include("Telegram.php");

$bot_id = ''; #Telegram-API-Key



$telegram = new Telegram($bot_id);

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

	
}
else {
	
	if($text=='Hallo' or $text =='Hi' or $text == '/start' or $text == '/help') {
		#Introduction, Start or Help Information
		$reply .= "*This is the Open Access TelegramBot.*
		Send a permanent identifier (e.g. DOI, Handle, URN) or an URL of your desired article and we will look for it in different Open Access Repositories.
		
		If you send the /start or /help command you will receive this message one more again.";
		 
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
