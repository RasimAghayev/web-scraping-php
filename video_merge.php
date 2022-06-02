
<form method="post">
    <input type="submit" name="prRun" id="prRun" value="RUN" /><br/>
</form>

<?php
error_reporting(E_ALL);
set_time_limit(0);
ini_set('memory_limit', '10240M');
include_once('getID3/getid3/getid3.php');
include_once 'functions.php';
$dir0='D:\Video\zip\\';
$dir4='D:\Video\Dn\\';
$dir1s=[
    'MSK JavaScript Bootcamp',
];

if(array_key_exists('prRun',$_POST)){
    runProccess($dir1s,$dir0,$dir4);
}
exit();
?>