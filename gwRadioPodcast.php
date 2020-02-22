<?php
/* *************************************************************************************
' Script Name: gwRadioPodcast.php
' **************************************************************************************
' @(#)    Purpose:
' @(#)    Read a subset of RSS/Podcast Feeds from an OPML file and output
' @(#)    as one single feed
' **************************************************************************************
'  Written By: Brad Detchevery
              2274 RTE 640, Hanwell NB
'
' Created:     2020-02-20 - Initial Version
' 
' **************************************************************************************
'Note: Changing this routine effects all programs that change system settings
'-------------------------------------------------------------------------------*/
//for 32-bit windows?
function getTopItem($feedURL)
{
// return the top item in feedurl
$found=false;
try {
$feed=curl_get_contents($feedURL);
//$feed=file_get_contents("akimbo");
}
catch (Exception $e)
{
return false;
}

$feed = preg_replace('/&(?![A-Za-z0-9#]{1,7};)/','&amp;',$feed);
$xml = simplexml_load_string($feed, 'SimpleXMLElement', LIBXML_NOCDATA);
$json = json_encode($xml);
$array = json_decode($json,TRUE);
$item  = $array["channel"]["item"];
$title = $array["channel"]["title"];
$i=0;
$done = false;
while (!$done)
{
if ($i >= count($item))  $done=true;
else
{
if ($item[$i] !== null)
if (array_key_exists("enclosure",$item[$i])) {$found = true; $done=true;}
}
if (!$done) $i++;
}
if ($found)
 {
$lineItem=Array();
$lineItem["itemtitle"] = decode_html($item[$i]["title"]);
$lineItem["descr"] =decode_html( $item[$i]["description"]);
$lineItem["pubDate"] = $item[$i]["pubDate"];
$lineItem["enc"] = str_replace("&","&amp;",$item[$i]["enclosure"]["@attributes"]["url"]);
$feed = null;
$xml = null;
$json = null;
return $lineItem;
}
return false;
}

function appendFeed($OpmlFile)
{
$sz=0;
$opmlFile = new SimpleXMLElement($OpmlFile,null,true);
$rsslist=opmlRead($opmlFile->body);
$rssItems=Array();
foreach($rsslist as $rssitem){
//echo "Size is $sz\n";
$sz++;
$i=0;
$l = getTopItem($rssitem);
//gc_collect_cycles();
//sleep (2);
$c = count($l);
if ($l !== false && $c > 0)  
{
$d=strtotime($l["pubDate"]);
$rssItems[$d]=$l;
}
}
return $rssItems;
}

if ($argc < 3)
 {
 echo "Missing Command Line Arguments!\n";
 echo "Usage: " . $argv[0] . " /path/to/your.opml /path/to/makefeed.xml";
 die();
 }
$retval="";
$feedopml = $argv[1];
$outputxml = $argv[2];
$tmpdir=sys_get_temp_dir();
if ($tmpdir == "/tmp") $tmpdir = dirname($feedopml);

$gwtopItem="https://geekwisdom.org/level1.html";
if (file_exists($outputxml))
 {
$gwfeed = implode(file($outputxml));
$gwfeedFix = preg_replace('/&(?![A-Za-z0-9#]{1,7};)/','&amp;',$gwfeed);
$gwfeedFix = str_replace("&nbsp;"," ",$gwfeedFix);

$gwxml = simplexml_load_string($gwfeedFix);
$gwjson = json_encode($gwxml);
$gwarray = json_decode($gwjson,TRUE);
$gwitem=$gwarray["channel"]["item"];
if (isAssoc($gwitem))
 {
$gwtopItem= $gwitem["enclosure"]["@attributes"]["url"];
 }
else
 {
$gwtopItem= $gwitem[0]["enclosure"]["@attributes"]["url"];
 }

}

if (file_exists("./feed-top.txt"))
 {

$top = file_get_contents("./feed-top.txt");
$bottom="";
$bottom = @file_get_contents($tmpdir . "/items.xml");
$total_items=substr_count($bottom,"</item>");
if ($total_items > 200)
 {
$pos = strposX($bottom, '<\/item>', 200);

$bottom = substr($bottom,0,$pos) . "</item>";
}
$newrsstr="";
$newitems=appendFeed($feedopml);
krsort($newitems,1);
$keys=array_keys($newitems);
$topkey=$keys[0];
$rsstopItem=$newitems[$topkey]["enc"];

}
else
{
$newitems=appendFeed($feedopml);
$rsstopItem=$newitems[$topkey]["enc"];

}
if ($rsstopItem === $gwtopItem)
 {
 echo "Nothing new!\n";
// print_r($newitems[$topkey]);
// print_r($gwtopItem);
 die();
}
foreach($newitems as $rssitem)
{

$newrsstr = $newrsstr . "<item>\n<title>" . $rssitem["itemtitle"] . "</title>\n<description>" . $rssitem["descr"] . "</description>\n<pubDate>" . $rssitem["pubDate"] . "</pubDate>\n<enclosure url=\"" . $rssitem["enc"] ."\"/>\n</item>";
}
$fp=fopen($tmpdir . "/items.xml","w");
fwrite($fp,$newrsstr);
fwrite($fp,$bottom);
fclose($fp);
$fullfeed = $top . $newrsstr . $bottom . "\n</channel>\n</rss>";
file_put_contents($outputxml,$fullfeed);
echo $fullfeed;

function GUIDv4 ($trim = true)
{
    // if windows
    if (function_exists('com_create_guid') === true) {
        if ($trim === true)
            return trim(com_create_guid(), '{}');
        else
            return com_create_guid();
    }

    // OSX/Linux
    if (function_exists('openssl_random_pseudo_bytes') === true) {
        $data = openssl_random_pseudo_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);    // set version to 0100
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);    // set bits 6-7 to 10
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    // Fallback (PHP 4.2+)
    mt_srand((double)microtime() * 10000);
    $charid = strtolower(md5(uniqid(rand(), true)));
    $hyphen = chr(45);                  // "-"
    $lbrace = $trim ? "" : chr(123);    // "{"
    $rbrace = $trim ? "" : chr(125);    // "}"
    $guidv4 = $lbrace.
              substr($charid,  0,  8).$hyphen.
              substr($charid,  8,  4).$hyphen.
              substr($charid, 12,  4).$hyphen.
              substr($charid, 16,  4).$hyphen.
              substr($charid, 20, 12).
              $rbrace;
    return $guidv4;
}

function opmlRead($xmlObj,$depth=1)
{
static $retval=Array();
        if (count($xmlObj->children()) > 0) {  }
        foreach($xmlObj->children() as $child)
          {
                if (isset($child['xmlUrl']) && isset($child['text']))
                      {
                        $index1 = (string) $child['text'];
                        $index=trim($index1);
                        $retval[$index]=trim((string) $child['xmlUrl']);
                      }
         opmlRead($child,$depth+1);
          }
return $retval;

}
function decode_html($inputstr)
{
$orig=strip_tags($inputstr);
$a = html_entity_decode($orig);
$a = str_replace("&apos;","'",$a);
$a = str_replace("&nbsp;"," ",$a);
$a = preg_replace_callback("/(&#[0-9]+;)/", function($m) { return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES"); }, $a);
$a= preg_replace('/&(?![A-Za-z0-9#]{1,7};)/','&amp;',$a);
return $a;
}

function strposX($haystack, $needle, $number)
{
    // decode utf8 because of this behaviour: https://bugs.php.net/bug.php?id=37391
//    preg_match_all("/$needle/", utf8_decode($haystack), $matches, PREG_OFFSET_CAPTURE);
    preg_match_all("/$needle/", $haystack, $matches, PREG_OFFSET_CAPTURE);
    return $matches[0][$number-1][1];
}


function isAssoc(array $arr)
{
    if (array() === $arr) return false;
    return array_keys($arr) !== range(0, count($arr) - 1);
}
function memory_usage() {
	$mem_usage = memory_get_usage(true);
	if ($mem_usage < 1024) {
		$mem_usage .= ' bytes';
	} elseif ($mem_usage < 1048576) {
		$mem_usage = round($mem_usage/1024,2) . ' kilobytes';
	} else {
		$mem_usage = round($mem_usage/1048576,2) . ' megabytes';
	}
	return $mem_usage;
}

function curl_get_contents($filename)
{
$tmpdir=sys_get_temp_dir();
$DLFile = tempnam($tmpdir, "gwRTmp");
$DLURL= $filename;
$fp = fopen ($DLFile, 'w+');
$ch = curl_init($DLURL);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_FILE, $fp);
curl_exec($ch);
curl_close($ch);
fclose($fp);
$nfo=file_get_contents($DLFile);
unlink($DLFile);
return $nfo;
}

