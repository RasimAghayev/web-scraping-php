<?php
include 'vendor/autoload.php';
ini_set('max_execution_time', 3600); // 1 house

use Goutte\Client;
$client = new Client();
$arr=array();
$listed_linkinfo=array();
$crawler = $client->request('GET', 'https://downloadly.ir/download/elearning/video-tutorials/');
//$crawler = $client->request('GET', 'https://downloadly.ir/tag/easy-Learning/');
echo "<pre>";
$arr=[];
$crawler->filter('.pagination navigation')->each(function($node){
    global $arr;
    $arr[] = $node->filter('a')->attr('href');
});
//print_r($arr);

$details = [];
$crawler->filter('article')->each(function ($node) use(&$details) {
    global $client;
    $description = $node->filter('a')->each(function($node) {
        return $node->html();
    })[1];
    $link = $node->filter('a')->attr('href');
    $get_file = $client->request('GET', $link);
    $file_list = [];
    $get_file->evaluate('//p/a')->each(function($file) use(&$file_list){
        $file_list_description = $file->filter('a')->text();
        $searchVal = array("مگابایت", "گیگابایت",'دانلود بخش','دانلود');
        $replaceVal = array("MB", "GB",'Part','Download');
        $res = str_replace($searchVal, $replaceVal, $file_list_description);
        $file_list_link = $file->filter('a')->attr('href');
        if(strpos($file_list_link,'.rar')>0){
            $file_list[]=[$res,$file_list_link];
        }
    });
    $details[] = [
        'description' => $description,
        'link' => $link,
        'file_list' => $file_list,
    ];
});
//print_r($details);
echo json_encode(['next_link'=>$arr,'running_link'=>$details]);