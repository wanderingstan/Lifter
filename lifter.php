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
		// Doesn't matter what timezone, as we only handle dates, but PHP insists.
		date_default_timezone_set('America/Los_Angeles');

		// Load habit page to get check-ins
		$this->url = $url;
		$habit_html = file_get_contents($this->url);

		// Get habit name
		$success = preg_match_all('/\<h1 class="profile-habit-name"\>(.*?)\<\/h1\>/',$habit_html,$matches);
		$this->name = $matches[1][0];

		// Split HTML into month-chunks
		$habit_month_split_regex = '/\<h3\>(?P<month_year>[a-zA-Z]+ \d\d\d\d)<\/h3>\W\<table class="cal-month"\>/';
		$habit_months_html_chunks = preg_split($habit_month_split_regex, $habit_html, -1, PREG_SPLIT_DELIM_CAPTURE);
		array_shift($habit_months_html_chunks); // get rid of non-calendar chunk at start
		while (count($habit_months_html_chunks)>0) {
			$month_year = array_shift($habit_months_html_chunks); // e.g. "January 1912"
			$month_html = array_shift($habit_months_html_chunks); // HTML of calendar for January 1912

			// Get habit check-ins for the month
			$habit_regex = '/\<div class="cal-day\W(?P<checked>checked)?\W"\>.*?(?P<day>\d+)/s';
			$success = preg_match_all($habit_regex, $month_html, $matches, PREG_SET_ORDER);

			// Put the checkins into our array, with date as key
			foreach ($matches as $val) {
				$iso_date = date('Y-m-d',strtotime($val['day'].' '.$month_year));
				$this->checkin_array[$iso_date] = ($val['checked']) ? 'X' : '';
			}
		}
	}
}

//$uid = "5046d263bf6a2411642a";
// $test_habit = new Habit("http://lift.do/users/5046d263bf6a2411642a/2417");
// print_r($test_habit);
// exit();

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