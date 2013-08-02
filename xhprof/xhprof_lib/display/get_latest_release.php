<?php
//header("Content-Type:text/json");
$doc_root = "/var/www/html";
$core_include = $doc_root . "/zperfmon/include/";

set_include_path(get_include_path() . ":$core_include");

if ( !isset($_GET['game'])){
	echo "unknown";
	return ;
}

include_once 'setup_page.php';
$timestamp= $_GET['timestamp'];
$GLOBALS['$game_cfg_version']=$game_cfg;
print_r(extract_current_release_from_db($timestamp));
function extract_current_release_from_db($timestamp)
{
        // Database connection variables
		$game_cfg=$GLOBALS['$game_cfg_version'];
        $dbServer = $game_cfg["db_host"];
        $dbDatabase = $game_cfg["db_name"];
        $dbUser = $game_cfg["db_user"];
        $dbPass = $game_cfg["db_pass"];
        $sConn = mysql_connect($dbServer, $dbUser, $dbPass)
                or die("Couldn't connect to database server");

        $dConn = mysql_select_db($dbDatabase, $sConn)
                or die("Couldn't connect to database $dbDatabase");



	$query = " select text from events where unix_timestamp(start) <= ".$timestamp." order by start desc limit 1;";
	$result = mysql_query($query) or die("Couldn't get file list");
	$rows = mysql_fetch_row($result);		
	if($rows)
	{
		return ($rows[0]);
	}
	return "unknown";
}


?>

