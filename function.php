<?php

$list_video_filename=["mp4","mov","f4v","mkv","avi","wmv","mpg","flv","webm","m4v" ];
/*
* Listed dir and subdir folder
*
*param @var $dir
*
*/


/**
 * Listed directory and subdirectory folder.
 *
 * @param $dir string
 * @return $results array
 */
function getDirContents($dir, &$results = array()): array|string {
    if(!is_dir($dir)){
        return 'Qovluq yoxdur--';
    }
    $files = scandir($dir);
    natsort($files);
    foreach ($files as $key => $value) {
        $path = realpath($dir . DIRECTORY_SEPARATOR . $value);
        if (!is_dir($path)) {
            $results[] = $path;
        }else if ($value != "." && $value != "..") {
            getDirContents($path, $results);
            if (is_dir($path)){
                continue;
            }
            $results[] = $path;
        }
    }
    return $results;
}
/**
 * Convert Folder name russion to english.
 *
 * @param $str string
 * @return $results string
 */
function post_slug($str): string{
    $cyr = [
        'Љ', 'Њ', 'Џ', 'џ', 'ш', 'ђ', 'ч', 'ћ', 'ж', 'љ', 'њ', 'Ш', 'Ђ', 'Ч', 'Ћ', 'Ж','Ц','ц',
        'а','б','в','г','д','е','ё','ж','з','и','й','к','л','м','н','о','п', 'р','с','т','у','ф',
        'х','ц','ч','ш','щ','ъ','ы','ь','э','ю','я', 'А','Б','В','Г','Д','Е','Ё','Ж','З','И','Й',
        'К','Л','М','Н','О','П', 'Р','С','Т','У','Ф','Х','Ц','Ч','Ш','Щ','Ъ','Ы','Ь','Э','Ю','Я'
    ];
    $lat = [
        'Lj', 'Nj', 'Dž', 'dž', 'š', 'đ', 'č', 'ć', 'ž', 'lj', 'nj', 'Š', 'Đ', 'Č', 'Ć', 'Ž','C',
        'c', 'a','b','v','g','d','e','io','zh','z','i','y','k','l','m','n','o','p', 'r','s','t','u',
        'f','h','ts','ch','sh','sht','a','i','y','e','yu','ya', 'A','B','V','G','D','E','Io','Zh','Z',
        'I','Y','K','L','M','N','O','P', 'R','S','T','U','F','H','Ts','Ch','Sh','Sht','A','I','Y','e','Yu','Ya'
    ];
    $str = str_replace($cyr, $lat, $str);
    return strtolower(
        string: preg_replace(
            array('/[^A-Za-z0-9 -.]/', '/[!@#$%:\&*(),\' -]+/', '/^-|-$/'),
            array('', '-', ''),
            $str
        )
    );
}

/**
 * Listed directory and subdirectory folder spacevication file name.
 *
 * @param $str string
 * @return $results string
 */
function file_listed($dir2,&$files = array(),&$results = array()): array{
    $k=1;
    $getID3 = new getID3;
    $duration=null;
    foreach ($files as $i => $value) {
        if(	strtolower(substr($value,-3))=="mp4" || strtolower(substr($value,-3))=="mov" || strtolower(substr($value,-3))=="f4v" ||
            strtolower(substr($value,-3))=="mkv" || strtolower(substr($value,-3))=="avi" || strtolower(substr($value,-3))=="wmv" ||
            strtolower(substr($value,-3))=="mpg" || strtolower(substr($value,-3))=="flv" || strtolower(substr($value,-4))=="webm" ||
            strtolower(substr($value,-3))=="m4v"
        ){
            @$duration_sec=$getID3->analyze($value)['playtime_seconds'];
            $duration+=$duration_sec;
            $results[]= array(
                'old'=>$value,
                'new'=>$dir2."\\".$k."-".post_slug(substr(strrchr($value, "\\"), 1))."1",
                'new1'=>$dir2."\\".$k.".mp4",
                'duration_sec'=>$duration,
                'duration_sec1'=>$duration_sec,
                'duration_time'=>time_elapsed_A($duration-$duration_sec),
            );
            $k++;
        }
    }
    return $results;
}
/**
 * SUM all video time .
 *
 * @param $secs int
 * @return $results int
 */
function time_elapsed_A($secs) : string{
    if($secs<>0){
        $bit = array(
            'h' => (floor(fmod($secs/3600,24))<10?'0':'').floor(fmod($secs/3600,24)),
            'm' => (floor(fmod($secs/60,60))<10?'0':'').floor(fmod($secs/60,60)),
            's' => (floor(fmod($secs,60))<10?'0':'').floor(fmod($secs,60))
        );
        foreach($bit as $k => $v)
            if($v > 0){
                $ret[] = $v;
            }else if($v == 0){
                $ret[] ='00';
            }
        return join(':', $ret);
    }
    return '00:00:00';
}
/**
 * -------------------
 *
 * @param $secs int
 * @return $results int
 */
function file_write($dir0,$old_file,$new_file,&$results = array()) : string{
    $old_content = "";$new_content = "";$cmd_content = "";
    /*
        Video settings
        -c:v libx264	Encoder H.264
        -s 1920x1080	Resolution
        -r 30			Frame Rate
        -b:v 3M			Bit Rate
    */
    $video_codec="-c:v libx264 -s 1920x1080 -r 30 -b:v 2M";
    /*
        Audio settings
        -c:a aac	Encoder AAC
        -ac 2		Channel
        -ar 44100	Sample Rate
        -b:a 192k	Bit Rate
    */
    $audio_codec="-c:a aac -ac 2 -ar 44100 -b:a 192k";
    foreach ($results as $key => $value) {
        $old_content .= str_ireplace(array('D:\IDM\IDM2\tt\\'),array(''),$value['old']) . "\n";
        $new_content .= "file " . str_ireplace(array('D:\IDM\IDM2\tt\\','\\'),array('','/'),$value['new1']) . "\n";
        $cmd_content .= "E:\\DevOps\\OpenServer\\domains\\videomerge.azp\\old\\ffmpeg\bin\\ffmpeg.exe -i " . str_ireplace(array('D:\IDM\IDM2\tt\\','\\'),array('','/'),$value['new']) .
            " -cpu-used 32 -map_metadata -1 ".$video_codec." ".$audio_codec." -preset ultrafast -profile:v main -pix_fmt yuv420p -movflags +faststart " . str_ireplace(array('D:\IDM\IDM2\tt\\','\\'),array('','/'),$value['new1']) . "\n";
    }
    file_put_contents($dir0."/".$old_file.mt_rand().".txt", $old_content);
    file_put_contents($dir0."/".$new_file.".txt", $new_content);
    return trim($cmd_content)."\n";
}
function action_file($action,&$results = array()){
    foreach ($results as $key => $value) {
        switch ($action) {
            case "copy":
                if (!copy($value['old'],$value['new'])) {
                    die('Nese seflik var faylin yerdeyismesinde.');
                }
                break;
            case "rename":
                if (!rename($value['old'],$value['new'])) {
                    die('Nese seflik var faylin ad deyismesinde.');
                }
                break;
            case "unlink":
                unlink($value['old']);
                break;
        }
    }
}
function runProccess($dir1s,$dir0,$dir4){
    foreach($dir1s as $dir1){
        $dir2=post_slug($dir1);
        $dir3=$dir4.$dir2;


        if (!is_dir($dir3)){
            mkdir($dir3,0777,true);
        }

        $getarray=getDirContents($dir0."\\".$dir1);
        $getarray2=file_listed($dir3,$getarray);
        if(!is_array($getarray2)){
            echo 'Qovluqda "mp4","mov","f4v","mkv","avi","wmv","mpg","flv","m4v","webm" fayillar yoxdur.\n';
            continue;
        }
        @$twelve=intval(end($getarray2)['duration_sec'])/43199;
        $convert0='mkdir '.$dir3.'\1 && move '.$dir3.'\*.*1 '.$dir3.'\1\ '."\n";
        $convert0.='cd '.$dir3.'\1\ '."\n";
        $convert0.='rename *.mp41 *.mp4'."\n";
        $convert0.='cd '.$dir3.'\ '."\n";
        //E:\\DevOps\\OpenServer\\domains\\videomerge.azp\\old\\ffmpeg
        //$convert0='del /S /F /Q '.$dir3.'\*.mp41 && del /S /F /Q '.$dir3.'\*.webm1 && del /S /F /Q '.$dir3.'\*.mkv1 && del /S /F /Q '.$dir3.'\*.mov1 && del /S /F /Q '.$dir3.'\*.f4v1 '."\n";
        $convert0.='E:\\DevOps\\OpenServer\\domains\\videomerge.azp\\old\\ffmpeg\\bin\\ffmpeg.exe -safe 0 -f concat -segment_time_metadata 1 -i '.$dir0.$dir2.'.txt ';//-bsf:v h264_mp4toannexb
        if($twelve<1){
            $convert0.='-c copy '.$dir3.'.mp4 ';
        }else{
            $time_stmp=array('00:00:00','11:59:59','23:59:59','35:59:59','47:59:59','59:59:59','71:59:59','83:59:59','95:59:59','107:59:59','119:59:59',
                '131:59:59','143:59:59','155:59:59','167:59:59','179:59:59','191:59:59','203:59:59','215:59:59','227:59:59','239:59:59','251:59:59',
                '263:59:59','275:59:59','287:59:59','299:59:59','311:59:59','323:59:59','335:59:59','347:59:59','359:59:59','371:59:59','383:59:59');
            for ($l=0;$l<=$twelve;$l++){
                $convert0.='-c copy -ss '.$time_stmp[$l].' -t 11:59:59 '.$dir3.'-'.$l.'.mp4  ';
            }
        }
        $convert0.=' && exit';

        $tt=$dir0.$dir1."\\";
        $youtube="";
        foreach($getarray2 as $k=>$v){
            $hrs=explode(":",$v['duration_time']);
            if($hrs[0]>11){
                $hrs[0]=(($hrs[0]-12<10)?'0':'').($hrs[0]-12);
                $dr_tm=implode(":",$hrs);
            }else{
                $dr_tm=$v['duration_time'];
            }
            $youtube.=$dr_tm.' - '.str_replace(array($tt,".mp4",".mov",".m4v",".f4v",".mkv",".avi",".wmv",".mpg",".flv",".webm","--- [ FreeCourseWeb.com ] ---",
                    '--- [ DevCourseWeb.com ] ---','DevCourseWeb.com','---[TutFlix.ORG]---','[TutFlix(dot)ORG]. ','--[TutFlix.ORG]--','[Udemycourses.me]',
                    '[TutFlix.ORG]','[TutFlix.org]','TutFlix.ORG','FreeCourseWeb.com','_Downloadly.ir','Downloadly.ir','Lesson','lesson','[HowToFree.Org] ',
                    '[HowToFree.Org]','[TG @coursenav] ','---'),'',$v['old'])."\n";
        }
        file_put_contents($dir0."/youtube-".$dir1.mt_rand().".txt", $youtube);
        echo $youtube."<br>".$convert0."<br><pre>";
        print_r($getarray2);
        $flie_rt=file_write($dir0,$dir1,$dir2,$getarray2).$convert0;
        file_put_contents($dir0."/bat-".$dir2.".bat", $flie_rt);
        action_file("rename",$getarray2);
        //move "E:\Video\zip\Udemy - Discord Clone - Learn MERN Stack with WebRTC and SocketIO 2022-1.7z" "C:\Users\Rasim\Desktop\Udemy - Discord Clone - Learn MERN Stack with WebRTC and SocketIO 2022-1.7z"
        system("cmd /c ".$dir0."/bat-".$dir2.".bat");
        echo "Finished this directory :" .$dir1."<br>";
    }
}