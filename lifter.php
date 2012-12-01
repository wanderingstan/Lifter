<?php
/*
Lifter

Quick'n dirty script to scrape a user's page at lift.do and 
create a csv file of a user's habit check-ins.

E.g. 
lift.do/users/5046d263bf6a2411642a/
becomes                 
lifter.wanderingstan.com/users/5046d263bf6a2411642a/
returns a csv file.
This can be dynamically linked to a google spreadsheet by putting this 
in a cell:
=ImportData("http://lifter.wanderingstan.com/users/5046d263bf6a2411642a")

Results are cached for 6 hours to prevent server overloading.
Optionally add &extract_numbers=1 to URL to extract any numerical 
information from comments. E.g. "Slept 6 hours" becomes "6".

By Stan James http://wanderingstan.com 
with help from Joel Longtine <jlongtine@gmail.com>
Thanks to the team at Lift.do for the great tool!
*/

$extract_numbers = $_GET['extract_numbers'];

class LiftUser {

	public $user_id;
	public $habits_array;
	public $habit_matrix = array(); // multi-dimensional array of results from all habits

	function __construct($user_id) {
		$this->user_id = $user_id;
		$user_url = 'http://lift.do/users/' . $user_id;
		$user_html = file_get_contents($user_url);

		$success = preg_match_all('/href="(\/users\/'.$this->user_id.'\/([0-9]+))/', $user_html, $matches, PREG_SET_ORDER);
		foreach ($matches as $val) {
		    $this->habits_array[] = new Habit('http://lift.do' . $val[1]);
		}

		// merge into our master matrix
		foreach($this->habits_array as $column_id=>$habit) {
			foreach($habit->checkin_array as $date=>$did_it) {
				$this->habit_matrix[$date][$habit->name]=$did_it;
			}
		}
		// sort it by date (the array key)
		ksort($this->habit_matrix);
	}

	public function print_csv() {
		global $extract_numbers;

		// First column is always date
		$out="Date";
		// Habit names as column headers
		foreach($this->habits_array as $id=>$habit) {
			$out.=",".str_replace(","," ",$habit->name); 
		}
		$out.="\n";

		// Habit results
		foreach($this->habit_matrix as $date=>$habit_checkins) {
			$out.=$date;
			foreach($this->habits_array as $id=>$habit) {
				$out.=",";
				if ($extract_numbers) {
					// extract_numbers option enabled, so
					// print numeric comments, or "X" for commentless check-in
					if ($habit_checkins[$habit->name]=="0") {
						// checked in, but no numeric comment
						$out.=" 0";
					}
					elseif (!$habit_checkins[$habit->name]) {
						// did not check in
						$out.="  ";
					}
					else {
						// checked in with numeric comment
						$out.=str_pad($habit_checkins[$habit->name],2);
					}
				}
				else {
					// Normal case, just print 1 or 0
					$out.= $habit_checkins[$habit->name] ? "1":" ";
				}
			}
			$out.="\n";
		}
		return $out;
	}
}


class Habit {

	public $name;
	public $url;
	public $checkin_array = array(); // array of checkins

	function __construct($url) {

		// Load user's page to get list of all habits
		$this->url = $url;
		$habit_html = file_get_contents($this->url);

		// Get habit name
		$success = preg_match_all('/\<div id="header_info_challenge_name"\>(.*?)\<\/div\>/',$habit_html,$matches);
		$this->name = $matches[1][0];

		// Get habit check-ins
		$success = preg_match_all('/<span popup_content="[^"]*?([0-9]*)[^"]*?" class="calendar_day".*?current_day="(....-..-..).*?".*?(calendar_day_interior +calendar_day_checked|calendar_day_interior)/s', $habit_html, $matches, PREG_SET_ORDER);

		// now load the results of each habit
		foreach ($matches as $val) {
		    if (strpos($val[3],"calendar_day_checked")===FALSE)	{
		    	// no check-in
		    	$x="";
		    }
		    elseif ($val[1]) {
		    	// checked in with comment
		    	$x=$val[1];
		    }
		    else {
		    	// checked in without comment
		    	$x="X";
		    }
		    $this->checkin_array[$val[2]] = $x;
		}
	}
}


// $uid = "5046d263bf6a2411642a";
$uid = $_GET['uid'];

$cache_file = $uid . ($extract_numbers ? "_extract_numbers":"") .".csv";
$cache_life = 21600; // Caching time, in seconds. 21600 secs=6 hours.

$filemtime = @filemtime($cache_file);  // returns FALSE if file does not exist
if (!$filemtime or (time() - $filemtime >= $cache_life)) {
	ob_start();
	$my_user = new LiftUser($uid);
	print ($my_user->print_csv()); // HERE IS WHERE THE WORK HAPPENS
	file_put_contents($cache_file,ob_get_flush());
}
else {
	readfile($cache_file);
}

?>