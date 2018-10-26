 <?php
 
if($argc<2) {
	die("Usage: php ".basename(__FILE__)."  <access log path> [--update <update interval in seconds>] [--time-limit <time limit in seconds for listing request>] [--clients-limit <show limit clients>]\n");
}
$accesslog = $argv[1];
if(cmdline::get('update'))
	$sleeptime = ((int)$agv[2]);
else
	$sleeptime = 10;
if(!file_exists($accesslog) OR !is_readable($accesslog)) {
	die("Could not read $accesslog\n");
}
clear_console();
$cache = array();
$samerequests = array();
$colors = new Colors();
while(true) {
	$serverload = get_server_load();
	if($serverload<30) {
		$color = "green";
	} elseif ($serverload<80) {
		$color = "yellow";
	} else {
		$color = "red";
	}
	$load_str = "Load: <".
		str_repeat('=', $serverload)
		.">";
	$pad_len = floor((152-strlen($load_str))/2);
	$pad_str = str_repeat(' ', $pad_len);
	echo $colors->getColoredString($pad_str.$load_str.$pad_str, $color)."\n\n";
	$log = rev_file($accesslog, cmdline::get('clients-limit', 32));
	foreach ($log as $key => $value) {
		$echo = str_pad(($key+1).':', 5);
		$value = parse_log($value);
		if(time()-$value['date'] <= cmdline::get('time-limit', 300)) {
			// generate request id
			$request_id = md5($value['ip'].$value['request'].$value['status'].$value['bytes']);
			if (array_key_exists($request_id, $samerequests)) {
				if(!in_array($value['date'], $samerequests[$request_id]['times'])) {
					$samerequests[$request_id]['count']++;
				}
			} else {
				$samerequests[$request_id] = array(
					'count' => 0,
					'times' => array()
					);
			}
			$date = date("H:i:s y/m/d", $value['date']);
			$reverse_ip = gethostbyaddr($value['ip']);
			$echo .= str_pad($reverse_ip, 30);
			$echo .= str_pad('('.$value['ip'].')', 20);
			$echo .= '-> ';
			$echo .= str_pad($value['request'], 70);
			$echo .= str_pad('('.$value['status'].')', 6);
			$echo .= str_pad($date, 19);
			$color = (time()-$value['date']<=30) ? 'yellow' : 'green';
			echo $colors->getColoredString($echo."\n", $color);
		} else break;
	}
	sleep(cmdline::get('update', 5));
	clear_console();
}
// ========= internal use ==========
function clear_console() {
	if(DIRECTORY_SEPARATOR=='\\') {
		system("cls");
	} else {
		system("clear");
	}
}
function rev_file($file, $lines_count = false) {
	$fp = fopen($file, 'r');
	$pos = -2;
	$lines = array();
	$currentLine = '';
	while (-1 !== fseek($fp, $pos, SEEK_END)) {
		if($lines_count AND sizeof($lines)>=$lines_count) break;
	    $char = fgetc($fp);
	    if (PHP_EOL == $char) {
	            $lines[] = $currentLine;
	            $currentLine = '';
	    } else {
	            $currentLine = $char . $currentLine;
	    }
	    $pos--;
	}
	return $lines;
}
function parse_log($ln) {
	$return = array();
	$cache = explode(" - - ", $ln);
	$return['ip'] = $cache[0];
	$cache = array();
	preg_match("/\[(.+)\]/", $ln, $cache);
	$return['date'] = strtotime($cache[1]);
	$cache = array();
	preg_match("/\"(.+)\"/", $ln, $cache);
	$return['request'] = $cache[1];
	$cache = trim(end(explode(".", $ln)));
	$cache = explode(" ", $cache);
	$return['status'] = $cache[1];
	$return['bytes'] = $cache[2];
	return $return;
}
class cmdline {
	function get($sKey, $mDefault = Null) {
		global $argv,$argc;
		for($i = 1; $i <= ($argc-1); $i++) {
			if($argv[$i]=="/".$sKey OR $argv[$i]=="-".$sKey OR $argv[$i]=="--".$sKey) {
				if($argc>=$i+1) {
					return $argv[$i+1];
				}
			}
		}
		return $mDefault;
	}
	
	function keyexists($sKey) {
		global $argv,$argc;
		for($i = 1; $i <= ($argc-1); $i++) {
			if($argv[$i]=="/".$sKey OR $argv[$i]=="-".$sKey OR $argv[$i]=="--".$sKey) {
				return true;
			}
		}
		return false;
	}
	
	function valueexists($sValue) {
		global $argv,$argc;
		for($i = 1; $i <= ($argc-1); $i++) {
			if($argv[$i]==$sValue) return true;
		}
		return false;
	}
	
	function flagenabled($sKey) {
		global $argv,$argc;
		for($i = 1; $i <= ($argc-1); $i++) {
			if(preg_match("/\+([a-zA-Z]*)".$sKey."([a-zA-Z]*)/", $argv[$i])) {
				return true;
			}
		}
		return false;
	}
	
	function flagdisabled($sKey) {
		global $argv,$argc;
		for($i = 1; $i <= ($argc-1); $i++) {
			if(preg_match("/\-([a-zA-Z]*)".$sKey."([a-zA-Z]*)/", $argv[$i])) {
				return true;
			}
		}
		return false;
	}
	
	function flagexists($sKey) {
		global $argv,$argc;
		for($i = 1; $i <= ($argc-1); $i++) {
			if(preg_match("/(\+|\-)([a-zA-Z]*)".$sKey."([a-zA-Z]*)/", $argv[$i])) {
				return true;
			}
		}
		return false;
	}
	
	function getvalbyindex($iIndex, $mDefault = null) {
		global $argv,$argc;
		if(($argc-1)>=$iIndex) {
			return $argv[$iIndex];
		} else {
			return $mDefault;
		}
	}
}
 class Colors {
 private $foreground_colors = array();
 private $background_colors = array();
 
 public function __construct() {
 // Set up shell colors
 $this->foreground_colors['black'] = '0;30';
 $this->foreground_colors['dark_gray'] = '1;30';
 $this->foreground_colors['blue'] = '0;34';
 $this->foreground_colors['light_blue'] = '1;34';
 $this->foreground_colors['green'] = '0;32';
 $this->foreground_colors['light_green'] = '1;32';
 $this->foreground_colors['cyan'] = '0;36';
 $this->foreground_colors['light_cyan'] = '1;36';
 $this->foreground_colors['red'] = '0;31';
 $this->foreground_colors['light_red'] = '1;31';
 $this->foreground_colors['purple'] = '0;35';
 $this->foreground_colors['light_purple'] = '1;35';
 $this->foreground_colors['brown'] = '0;33';
 $this->foreground_colors['yellow'] = '1;33';
 $this->foreground_colors['light_gray'] = '0;37';
 $this->foreground_colors['white'] = '1;37';
 
 $this->background_colors['black'] = '40';
 $this->background_colors['red'] = '41';
 $this->background_colors['green'] = '42';
 $this->background_colors['yellow'] = '43';
 $this->background_colors['blue'] = '44';
 $this->background_colors['magenta'] = '45';
 $this->background_colors['cyan'] = '46';
 $this->background_colors['light_gray'] = '47';
 }
 
 // Returns colored string
 public function getColoredString($string, $foreground_color = null, $background_color = null) {
 $colored_string = "";
 
 // Check if given foreground color found
 if (isset($this->foreground_colors[$foreground_color])) {
 $colored_string .= "\033[" . $this->foreground_colors[$foreground_color] . "m";
 }
 // Check if given background color found
 if (isset($this->background_colors[$background_color])) {
 $colored_string .= "\033[" . $this->background_colors[$background_color] . "m";
 }
 
 // Add string and end coloring
 $colored_string .=  $string . "\033[0m";
 
 return $colored_string;
 }
 
 // Returns all foreground color names
 public function getForegroundColors() {
 return array_keys($this->foreground_colors);
 }
 
 // Returns all background color names
 public function getBackgroundColors() {
 return array_keys($this->background_colors);
 }
 }
 function get_server_ram() {
 	if(!stristr(PHP_OS, 'win')) {
 		$free = shell_exec('free');
		$free = (string)trim($free);
		$free_arr = explode("\n", $free);
		$mem = explode(" ", $free_arr[1]);
		$mem = array_filter($mem);
		$mem = array_merge($mem);
		$memory_usage = $mem[2]/$mem[1]*100;
		return $memory_usage;
 	} else {
 	}
 }
 function get_server_load() {
    
        if (stristr(PHP_OS, 'win')) {
        
            $wmi = new COM("Winmgmts://");
            $server = $wmi->execquery("SELECT LoadPercentage FROM Win32_Processor");
            
            $cpu_num = 0;
            $load_total = 0;
            
            foreach($server as $cpu){
                $cpu_num++;
                $load_total += $cpu->loadpercentage;
            }
            
            $load = round($load_total/$cpu_num);
            
        } else {
        
            $sys_load = sys_getloadavg();
            $load = $sys_load[0];
        
        }
        
        return (int) $load;
    
    } 
