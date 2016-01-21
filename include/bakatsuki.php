<?php

require 'simple_html_dom.php';
require 'mangaupdates.php';
//require '../db.php';

function getList(){
$titles = array();
$html = file_get_html("http://www.baka-tsuki.org/project/index.php?title=Category:Light_novel_(English)");
	foreach($html->find('html body div div div table tr td ul li a') as $element) {
	$titles[]['title']=$element->plaintext;
	}
return $titles;
}

function getVolumeData($title){
$ln_title=$title;
$title = str_replace(" ","_",$title);
$html = file_get_contents("http://www.baka-tsuki.org/project/index.php?title=".$title."&action=edit");
	$data = preg_split('/<textarea readonly="" accesskey="," id="wpTextbox1" cols="80" rows="25" style="" lang="en" dir="ltr" name="wpTextbox1">/', $html);
    $data = preg_split( '/<\/textarea/', $data[1] );
	$data = preg_split( '/(==.* by .*==)/', $data[0] ); //print_r($data);
	$data = preg_split( '/(==.*Project Staff.*==|==.*Translators.*==)/', $data[1] ); //print_r($data);
	$data = trim($data[0]);

//print_r($data);

$volumes = preg_split('/(\n|\r\n?){2,}/', $data);
//print_r($volumes);
$out = array();
$i=0;
foreach($volumes as $volume){
//print_r($volume);
$res = preg_match('/(==.*Volume.*\(\[)|(.*==.*Volume.*==)/', trim($volume), $title);
	if($res){
	$vol_title = $title[0];
	$vol_title = str_replace('=','',$vol_title);
	$vol_title = trim(str_replace('([','',$vol_title));
	//echo $vol_title.PHP_EOL;
	$out[$i]['title']=$vol_title;
	} else {
	continue;
	}
$res = preg_match_all('/(::\*.*)/', trim($volume), $chps);
	if($res){
	foreach($chps as $chap){
	$chaps = preg_replace('/(::\*|]]|\[\[)/','',$chap);
	$chapters = array();
	foreach($chaps as $chap) {
	$chap = explode('|',$chap); 
	if(count($chap)>1){
		$chap_title = $chap[1];
		$chap_url = $chap[0];
		$chap_url= "http://www.baka-tsuki.org/project/index.php?title=".str_replace(' ','_',$chap_url);
	$chapters[]=array('title'=>$chap_title,'url'=>$chap_url);	
	}
	$out[$i]['chapters'] = $chapters;
	}
	}
	}
$i++;
}
 
$output = array('title'=>$ln_title,'count'=>count($out),'result'=>$out);
return $output;
}

function contains_all($str,array $words) {
    if(!is_string($str))
        { return false; }

    foreach($words as $word) {
        if(!is_string($word) || stripos($str,$word)===false)
            { return false; }
    }
    return true;
}

function parseThumbnail($thumbnail){
if(!strpos($thumbnail,"/thumb/")){
return $thumbnail;
}
$thumbnail = str_replace("/thumb","",$thumbnail);
$basename = basename($thumbnail);
$thumbnail = str_replace("/$basename","",$thumbnail);
return $thumbnail;
}

//Get LN Desc
function getDescForTitle($title){
	$title = str_replace(" ","_",$title);
	$html = file_get_html("http://www.baka-tsuki.org/project/index.php?title=$title");
	$desc = $html->find('html body div div div p',0)->plaintext;
	//$descTest = explode(' ',trim($desc));
	$words = explode (' ',"This project has been");
	if(contains_all($desc,$words)){
	$desc = $html->find('html body div div div p',1)->plaintext;
	}
	if(contains_all($desc,explode(' ',"series is also available in the following"))){
	$desc = "";
	}
	if(contains_all($desc,explode(' ',"revive this project by joining the translation"))){
	$desc = "";
	}
	if(contains_all($desc,explode(' ',"Light Novel Translation Project."))){
	$desc = "";
	}
	if(contains_all($desc,explode(' ',"is available in the following languages:"))){
	$desc = "";
	}
	/*if(in_array("This",$descTest)) {
	$desc = $html->find('html body div div div p',1)->plaintext;
	}*/
    return $desc;
}

//Get LN image
function getImageForTitle($title){
	$title = str_replace(" ","_",$title);
	$images = array();
	$html = file_get_html("http://www.baka-tsuki.org/project/index.php?title=$title");
	foreach($html->find('html body div div div a.image img') as $element) {
	$images[]=parseThumbnail("http://www.baka-tsuki.org".$element->src);
	}
	return $images;
}

//Get LN synopsis
function getSynopsisForTitle($title){
	$title = str_replace(" ","_",$title);
	/*$html = file_get_html("http://www.baka-tsuki.org/project/index.php?title=$title&action=edit");
	$data = $html->find('html body div div div textarea',0)->plaintext;
	$data = preg_split( "/(== Story Synopsis ==|==Story Synopsis==|==Synopsis==)/", $data );
	$data = preg_split( "/(==|==)/", $data[1] );
	$data = $data[0];
	$synopsis = strip_tags(html_entity_decode($data));
	$synopsis = str_replace("&amp;mdash;","-",$synopsis);
	if(strpos($synopsis,"http://")){
	$synopsis = preg_replace("/('''\[.*?\]''')|(\[.*?\])|('''.*?]''')/", '', $synopsis);
	}*/
    
    $html = file_get_html("http://www.baka-tsuki.org/project/index.php?title=$title");
    $container=$html->find("div#mw-content-text *");
    $synopsis="";
    
    $nextItemIsDesc=false;
    foreach($container as $content)
    {
        if($nextItemIsDesc)
        {
            $synopsis=$content->plaintext;
            break;
        }
        if($content->tag=="h2" && $content->first_child()->id=="Story_Synopsis")
        {
            $nextItemIsDesc=true;
        }
    }
	return $synopsis;
}

function getChapterListForVolumeForTitle($volume,$title)
{
    $volumeInfo=array();
    $title=str_replace(" ","_",$title);
    $html=file_get_html("http://www.baka-tsuki.org/project/index.php?title=$title");
    $innerElements=$html->find("div#mw-content-text *");
    
    
    $volume=str_replace(" ","_",$volume);
    $volEle=false;//check for the three tags which follows the volume header tag
    $countNextThree=1;
    
    foreach($innerElements as $element)
    {
        if($volEle)
        {
            if($countNextThree<=3)
            {
                if($element->tag=="div" && $element->class=="thumb tright")//volume image
                {
                    $volImgLink=$element->first_child()->first_child()->href;
                    $volumeInfo['volumeImage']=$volImgLink;
                    $countNextThree++;
                    continue;
                }
                
                if($element->tag=="dl")//main list of chapters
                {
                    $chapterList=$element->find('ul li');
                    $chapters=array();
                    foreach($chapterList as $chapter)
                    {
                        $tempArr=array();
                        $chapterTitle=$chapter->first_child()->innertext;
                        $chapterLink=$chapter->first_child()->href;
                        
                        $tempArr['chapterTitle']=$chapterTitle;
                        $tempArr['chapterLink']=$chapterLink;
                        $chapters[]=$tempArr;
                    }
                    
                    
                    $volumeInfo['chapterList']=$chapters;
                    $countNextThree++;
                    continue;
                }
                
                if($element->tag=='p')
                {
                    $countNextThree++;
                    continue;
                }
                
            }else
            {
                $volEle=false;
                $countNextThree=1;
                break;
            }
        }
        
        if($element->tag=="h3" && strpos($element->first_child()->id,$volume)===0)//compares $element id atttribute and $volume name
        {
            $volEle=true;
        }
    }
    return $volumeInfo;
    /*
        Return Type Format:
        Array(
        "volumeImage"="http://image Link",
        "chapterList"=Array(
                            Array(
                                "chapterTitle"="title",
                                "chapterLink"="http://link"
                            )
                    )
        )
    */
}

function getChapterContentForChapterLink($link)
{
    
    $html=file_get_html($link);
    $data=$html->find('html body div#mw-content-text',0);
    
    $chapConArr=array();
    foreach($data->childNodes() as $element)
    {
        if($element->tag=='h2' || $element->tag=='comment' || $element->tag=='table') continue; //removes extras from the main content 
        
        if($element->tag=='h3') //For Part Titles
        {
            if($element->children() && $element->firstChild()->tag=='span')
            {
                $chapConArr[]['h3']=$element->firstChild()->innertext;
                continue;
            }
            $chapConArr[]['h3']=$element->innertext;
            continue;
        }
        
        if($element->tag=='p')//The main text of the chapter
        {
            if($element->children())
            {
                $innerText=str_replace("<br>","\n",$element->innertext);
                $chapConArr[]['p']=$innerText;
                continue;
            }
            
            $chapConArr[]['p']=$element->innertext;
            continue;
        }
        
        if($element->tag=='div' && $element->class=='thumb tright')//image thunmnail shown in between chapter contents
        {
            $arr=array();
            $thumbCon=$element->firstChild()->firstChild();
            if($thumbCon->tag=='a' && $thumbCon->class='image')
            {
                $arr['imgLink']=$thumbCon->href;
                $img=$thumbCon->firstChild();
                $arr['imgThumbLink']=$img->src;
                $arr['imgTitle']=$img->alt;
                $arr['imgSrcSet']=$img->srcset;
            }
            $chapConArr[]['image']=$arr;
        }
    }
    return $chapConArr;
        /*
        Return Type Format: As per the position crawled from the page
        Array(
            Array(
                'h3'='text'//for title header (Part Titles)
            )
            Array(
                'p'='text' //for main text
            )
            Array(
                'image'=Array(
                            'imgLink'='http:link', //main link tag from <a href>attribute
                            'imgThumbLink'='http:link', //image link for the thumb shown in the page
                            'imgTitle'='title', //title of image taken from alt
                            'imgSrcSet'='links' //alternate image links
                        )
            )
        )
        */
}


?>