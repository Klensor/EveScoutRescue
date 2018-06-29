<?php 

// Mark all entry pages with this definition. Includes need check check if this is defined
// and stop processing if called direct for security reasons.
define('ESRC', TRUE);

// for debug only
/*
 error_reporting(E_ALL);
 ini_set('display_errors', 'on');
*/

include_once '../includes/auth-inc.php';
include_once '../class/users.class.php';
include_once '../class/config.class.php';
require_once '../class/db.class.php';
require_once '../class/leaderboard.class.php';
require_once '../class/caches.class.php';
require_once '../class/systems.class.php';
require_once '../class/output.class.php';
require_once '../class/rescue.class.php';

// check if the user is alliance member
if (!Users::isAllianceUserSession())
{
	// check if last login was already a non auth user
	if (isset($_SESSION['AUTH_NOALLIANCE']))
	{
		// set redirect to root path
		$_redirect_uri = Config::ROOT_PATH;
	}
	else
	{
		// set redirect to requested path
		$_redirect_uri = $_SERVER['REQUEST_URI'];
	}

	// void the session entries on 'attack'
	session_unset();
	// save the redirect URL to current page
	$_SESSION['auth_redirect']=$_redirect_uri;
	// set a flag for alliance user failure
	$_SESSION['AUTH_NOALLIANCE'] = 1;
	// no, redirect to home page
	header("Location: ".Config::ROOT_PATH."auth/login.php");
	// stop processing
	exit;
}

// create object instances
$database = new Database();
$users = new Users($database);
$caches = new Caches($database);
$systems = new Systems($database);
$rescue = new Rescue($database);

// set character name
if (!isset($charname))
{
	// no, set a dummy char name
	$charname = 'charname_not_set';
}

// check for SAR Coordinator login
$isCoord = ($users->isSARCoordinator($charname) || $users->isAdmin($charname));

if(isset($_REQUEST['errmsg'])) { $errmsg = $_REQUEST['errmsg']; }

// set stats type to display
$stat_type = 'participants';
if (isset($_REQUEST['stat_type'])) { $stat_type = $_REQUEST['stat_type']; }

// set Records timeframe
if ($stat_type == 'records') {
	$timeframe = 'All-Time';
	if (isset($_REQUEST['timeframe'])) { $timeframe = $_REQUEST['timeframe']; }
}

// if start and end dates are not set, set them to default values
if (!isset($_REQUEST['start'])) {
	$start = gmdate('Y-m-d', strtotime("- 7 day"));
	$startPD = gmdate('Y-M-d', strtotime("- 7 day")); // formatted for Pikaday widget
}
if (!isset($_REQUEST['end'])) {
	$end = gmdate('Y-m-d', strtotime("now"));
	$endPD = gmdate('Y-M-d', strtotime("now")); // formatted for Pikaday widget
}

// set start and end dates to submitted values (GET or POST)
if (isset($_REQUEST['start']) && isset($_REQUEST['end'])) {
	// start date
	$arrStart = explode('-', $_REQUEST['start']);
	$startYear = intval(substr($arrStart[0], -3)) + 1898;
	$startMonth = intval(date('m', strtotime($arrStart[1])));
	$startDay = intval($arrStart[2]);
	$start = gmdate('Y-m-d', strtotime($startYear. '-' . $startMonth. '-' . $startDay));
	
	// end date
	$arrEnd = explode('-', $_REQUEST['end']);
	$endYear = intval(substr($arrEnd[0], -3)) + 1898;
	$endMonth = intval(date('m', strtotime($arrEnd[1])));
	$endDay = intval($arrEnd[2]);
	$end = gmdate('Y-m-d', strtotime($endYear. '-' . $endMonth. '-' . $endDay));
	
	// special string for Pikaday widget
	$startPD = htmlspecialchars_decode(date("Y-M-d", strtotime($startYear. '-' . $startMonth. '-' . $startDay)));
	$endPD = htmlspecialchars_decode(date("Y-M-d", strtotime($endYear. '-' . $endMonth. '-' . $endDay)));
}
?>
<html>

<head>
	<?php 
	$pgtitle = 'ESR Stats';
	include_once '../includes/head.php'; 
	?>
	<style>
	<!--
		table {
			table-layout: fixed;
			word-wrap: break-word;
		}
		a,
		a:visited,
		a:hover {
			color: aqua;
		}
		.btn-info a,
		a:visited,
		a:hover {
			color: white;
		}
	-->
	</style>
	
	<!--Load the AJAX API-->
	<script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
	<script type="text/javascript" src="//ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js"></script>
	<script type="text/javascript">
		// Load the Visualization API and the piechart package.
		google.charts.load('current', {'packages':['corechart']});
	
		<?php 
		// load only the charts we need for the specified $stat_type
		switch ($stat_type) {
			case 'caches':
		?>
		// Draw charts on page load
		google.charts.setOnLoadCallback(drawEsrcCachesChart);
		
		function drawEsrcCachesChart(type) {
			if (typeof type === "undefined") { type = 'Daily'; }
			var jsonData = $.ajax({
				url: "../stats/stats_data_esrc_caches.php?start=<?=$start?>&end=<?=$end?>&type=" + type,
				dataType: "json", async: false }).responseText;
				// Create our data table out of JSON data loaded from server.
				var data = new google.visualization.DataTable(jsonData);
				// set chart options
				var options = { title: 'Rescue Cache Activity (' + type + ')', titleTextStyle: { color: 'white', fontSize: 16 },
				legendTextStyle: { color: 'white' }, backgroundColor: 'black', 
				hAxis: { textStyle: {color: 'white'} },	vAxis: { textStyle: {color: 'white'} }, isStacked: true,
				series: {0: {targetAxisIndex:0}, 1: {targetAxisIndex:0}, 
					2: {type:'line', color: 'limegreen', targetAxisIndex:1, interpolateNulls: true} },
				vAxes: {0: {title: 'Sows/Tends', titleTextStyle: {color:'white'}, logScale: false}, 
					1: {title: 'Active Caches', titleTextStyle: {color:'white'}, logScale: false} }
				 };
				// Instantiate and draw our chart, passing in some options.
				var chart = new google.visualization.ColumnChart(document.getElementById('esrcCaches'));
				chart.draw(data, options);
			}
		<?php 
				break;
			case 'participants':
		?>
		google.charts.setOnLoadCallback(drawEsrcParticipationChart);
		google.charts.setOnLoadCallback(drawSarParticipationChart);
		
		function drawEsrcParticipationChart(type) {
		  if (typeof type === "undefined") { type = 'Overall'; }
		  var jsonData = $.ajax({
		      url: "../stats/stats_data_esrc_participation.php?start=<?=$start?>&end=<?=$end?>&type=" + type, 
		      dataType: "json", async: false }).responseText;		          
		  var data = new google.visualization.DataTable(jsonData);
		  var options = { title: 'Most Active ' + type, titleTextStyle: { color: 'white', fontSize: 16 }, 
			  legendTextStyle: { color: 'white' }, backgroundColor: 'black', chartArea: {width: '90%'}};
		  var chart = new google.visualization.PieChart(document.getElementById('esrcParticipation'));
		  chart.draw(data, options);
		}

		function drawSarParticipationChart(type) {
		  if (typeof type === "undefined") { type = 'Rescuers'; }
		  var jsonData = $.ajax({
		      url: "../stats/stats_data_sar_participation.php?start=<?=$start?>&end=<?=$end?>&type=" + type, 
		      dataType: "json", async: false }).responseText;		          
		  var data = new google.visualization.DataTable(jsonData);
		  var options = { title: 'Most Active ' + type, titleTextStyle: { color: 'white', fontSize: 16 }, 
			  legendTextStyle: { color: 'white' }, backgroundColor: 'black', chartArea: {width: '90%'}};
		  var chart = new google.visualization.PieChart(document.getElementById('sarParticipation'));
		  chart.draw(data, options);
		}
		<?php 
				break;
			case 'records':
		?>
		// prep datatable (?)
		<?php 
				break;
			case 'personal':
				// do stuff
				break;
		}
		?>
	</script>
</head>

<body class="white" style="background-color: black;">
<div class="container">

<div class="row" id="header" style="padding-top: 10px;">
<?php 	
// top left
include_once '../includes/top-left.php'; 
// top middle
// show a different header form for Records display
if ($stat_type == 'records') {
?>
	<div class="col-sm-8 black" style="text-align: center;">
		<div class="row">
			<br />
			<div class="col-sm-6" style="text-align: center;">
				<form method="post" class="form-inline" action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>">
					<div class="form-group" id="timeframe" style="margin-bottom: 5px;">
						<select class="form-control" id="timeframe" name="timeframe">
							<option value="Daily"<?php echo ($timeframe == 'Daily') ? ' selected="selected"' : '';?>>
								In a Single Day</option>
							<option value="Weekly"<?php echo ($timeframe == 'Weekly') ? ' selected="selected"' : '';?>>
								In a Single Week</option>
							<option value="Monthly"<?php echo ($timeframe == 'Monthly') ? ' selected="selected"' : '';?>>
								In a Single Month</option>
							<option value="All-Time"<?php echo ($timeframe == 'All-Time') ? ' selected="selected"' : '';?>>
								Greatest Of All Time</option>
						</select>
						<input type="hidden" name="stat_type" value="records" />
						<button type="submit" class="btn btn-sm">Get Records</button>
					</div>
				</form>
			</div>
			<div class="col-sm-6" style="text-align: center;">
				<a href="stats.php?stat_type=caches" class="btn btn-info" role="button">Caches</a> &nbsp;&nbsp;&nbsp;
				<a href="stats.php?stat_type=participants" class="btn btn-info" role="button">Participants</a> &nbsp;&nbsp;&nbsp;
				<a href="#" class="btn btn-info" role="button">Personal</a>
			</div>
		</div>
	</div>
<?php 
}
else {
	// get rescue counts
	$ctrESRCrescues = $rescue->getRescueCount('closed-esrc', $start, $end);
	$ctrSARrescues = $rescue->getRescueCount('closed-rescued', $start, $end);
	$ctrAllRescues = intval($ctrESRCrescues) + intval($ctrSARrescues);
?>
	<div class="col-sm-8 black" style="text-align: center;">
		<div class="row">
			<div class="col-sm-8" style="text-align: center;">
				<form method="post" class="form-inline" action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>">
					<div class="input-daterange input-group" id="datepicker" style="margin-bottom: 5px;">
						<input type="text" class="input-sm form-control" name="start" id="start" 
							value="<?php echo isset($startPD) ? $startPD : '' ?>" />
						<span class="input-group-addon">to</span>
						<input type="text" class="input-sm form-control" name="end" id="end" 
							value="<?php echo isset($endPD) ? $endPD : '' ?>" />
					</div>
					<div class="input-group">
						<label class="radio-inline white"><input type="radio" name="stat_type" 
							value="caches"<?php echo ($stat_type == 'caches' ? 'checked="checked"' : '') ?>>
							Caches</label>
						<label class="radio-inline white"><input type="radio" name="stat_type" 
							value="participants"<?php echo ($stat_type == 'participants' ? 'checked="checked"' : '') ?>>
							Participants</label>
						<label class="radio-inline white"><input type="radio" name="stat_type" 
							value="records"<?php echo ($stat_type == 'records' ? 'checked="checked"' : '') ?>>
							Records</label>
						<label class="radio-inline white"><input type="radio" name="stat_type" 
							value="personal"<?php echo ($stat_type == 'personal' ? 'checked="checked"' : '') ?>
							disabled="disabled">Personal</label>
					</div>
					<div style="margin-top: 5px;">
						<button type="submit" class="btn btn-sm">Get Stats</button>
					</div>
				</form>
			</div>
			<div class="col-sm-4" style="text-align: center;">
				<div class="sechead white" style="font-weight: bold;">RESCUES:&nbsp; 
					<span style="color: gold;"><?php echo $ctrAllRescues; ?></span>
				</div>
				<div class="white text-center" style="font-weight: bold;">ESRC:&nbsp; 
					<span style="color: gold;"><?php echo $ctrESRCrescues; ?></span>
					&nbsp;&nbsp;&nbsp;&nbsp; SAR:&nbsp; 
					<span style="color: gold;"><?php echo $ctrSARrescues; ?></span>
				</div>
			</div>
		</div>
	</div>
<?php 
}
// top right
include_once '../includes/top-right.php'; ?>
</div>
<div class="ws"></div>
<!-- NAVIGATION TABS -->
<?php include_once 'navtabs.php'; ?>
<div class="ws"></div>
 
<?php
// display error message if there is one
if (!empty($errmsg)) {
?>
	<div class="row" id="errormessage" style="background-color: #ff9999;">
		<div class="col-sm-12 message">
			<?php echo nl2br($errmsg); ?>
		</div>
	</div>
	<div class="ws"></div>
<?php
}
?>
	<!-- STATS TITLE -->
	<div class="row">
		<div class="col-sm-12">
			<div class="sechead white text-center" style="font-weight: bold;">
				<?php echo strtoupper($stat_type);?>
			</div>
		</div>
	</div>
	<div class="ws"></div>
	<div class="row">
	<!-- STATS DISPLAY BEGINS -->
	<?php 
	switch ($stat_type) {
		case 'caches':
	?>
		<div class="col-sm-12">
			<div class="white text-center">
				<a href="javascript:drawEsrcCachesChart('Daily')">Daily</a> &nbsp;&nbsp;&nbsp; 
				<a href="javascript:drawEsrcCachesChart('Weekly')">Weekly</a> &nbsp;&nbsp;&nbsp; 
				<a href="javascript:drawEsrcCachesChart('Monthly')">Monthly</a>
			</div>
			<div class="ws"></div>
			<div id="esrcCaches" class="text-center" style="width: 900px; height:400px; margin: 0 auto"></div>
		</div>
	<?php 
			break;
		case 'participants':
	?>
		<div class="col-sm-6">
			<div class="sechead white text-center" style="font-weight: bold;">ESRC</div>
			<!-- ESRC PARTICIPANT COUNTS BEGIN -->
			<?php 
			// get unique participants from db
			$database->query("SELECT DISTINCT(Pilot) FROM activity 
								WHERE ActivityDate BETWEEN :start AND :end");
			$database->bind(":start", $start);
			$database->bind(":end", $end);
			$arrEsrcUniqueParticipants = $database->resultset();
			$database->closeQuery();
			// set participant counter
			$ctrEsrcUniqueParticipants = count($arrEsrcUniqueParticipants);
			?>
			
			<div class="white text-center"><strong>
				<span style="color: gold;"><?=$ctrEsrcUniqueParticipants?></span> Participants</strong>
				<br />
				<a href="javascript:drawEsrcParticipationChart('Overall')">Overall</a> &nbsp;&nbsp;&nbsp; 
				<a href="javascript:drawEsrcParticipationChart('Agents')">Agents</a> &nbsp;&nbsp;&nbsp; 
				<a href="javascript:drawEsrcParticipationChart('Sowers')">Sowers</a> &nbsp;&nbsp;&nbsp; 
				<a href="javascript:drawEsrcParticipationChart('Tenders')">Tenders</a>
			</div>
			<div class="ws"></div>
			<div id="esrcParticipation" class="text-center" style="width: 450px; height:300px;"></div>
			<!-- ESRC PARTICIPANT COUNTS END -->
		</div>
		<div class="col-sm-6">
			<div class="sechead white text-center" style="font-weight: bold;">SAR</div>
			<!-- SAR PARTICIPANT COUNTS BEGIN -->
			<?php 
			// get unique participants from db
			// Dispatchers
			$database->query("SELECT startagent FROM rescuerequest 
								WHERE requestdate BETWEEN :start AND :end
								GROUP BY startagent ORDER BY COUNT(startagent) DESC");
			$database->bind(":start", $start);
			$database->bind(":end", $end);
			$arrSarUniqueDispatchers = $database->resultset();
			$database->closeQuery();
			$i = 0;
			foreach ($arrSarUniqueDispatchers as $row) {
				$arrSarUD[$i] = $row['startagent'];
				$i++;
			}
			
			// Locators
			$database->query("SELECT locateagent FROM rescuerequest
								WHERE lastcontact BETWEEN :start AND :end
								GROUP BY locateagent  ORDER BY COUNT(locateagent) DESC");
			$database->bind(":start", $start);
			$database->bind(":end", $end);
			$arrSarUniqueLocators = $database->resultset();
			$database->closeQuery();
			$i = 0;
			foreach ($arrSarUniqueLocators as $row) {
				$arrSarUL[$i] = $row['locateagent'];
				$i++;
			}
			
			// Rescuers
			$database->query("SELECT pilot FROM rescueagents  
								WHERE entrytime BETWEEN :start AND :end
								GROUP BY pilot ORDER BY COUNT(pilot) DESC");
			$database->bind(":start", $start);
			$database->bind(":end", $end);
			$arrSarUniqueRescuers = $database->resultset();
			$database->closeQuery();
			$i = 0;
			foreach ($arrSarUniqueRescuers as $row) {
				$arrSarUR[$i] = $row['pilot'];
				$i++;
			}
			
			// merge arrays of SAR participants
			$arrArrays = array();
			$arrArrays[0] = $arrSarUD;
			$arrArrays[1] = $arrSarUL;
			$arrArrays[2] = $arrSarUR;
			$arrSarParticipants = array();
			foreach ($arrArrays as $arr) {
				if (is_array($arr)) {
					$arrSarParticipants = array_merge($arrSarParticipants, $arr);
				}
			}
			// de-dupe list to get uniques
			if (is_array($arrSarParticipants)) {
				$arrSarUniqueParticipants = array_unique($arrSarParticipants);
				// set unique SAR participant counter
				$ctrSarUniqueParticipants = count($arrSarUniqueParticipants);
			}
			else {
				$ctrSarUniqueParticipants = 0;
			}
			?>
			
			<div class="white text-center"><strong>
				<span style="color: gold;"><?=$ctrSarUniqueParticipants?></span> Participants</strong>
				<br /> 
				<a href="javascript:drawSarParticipationChart('Dispatchers')">Dispatchers</a> &nbsp;&nbsp;&nbsp; 
				<a href="javascript:drawSarParticipationChart('Locators')">Locators</a> &nbsp;&nbsp;&nbsp; 
				<a href="javascript:drawSarParticipationChart('Rescuers')">Rescuers</a>
			</div>
			<div class="ws"></div>
			<div id="sarParticipation" style="width: 450px; height:300px;"></div>
			<!-- SAR PARTICIPANT COUNTS END -->
		</div>
	<?php 
			break;
		case 'records':
	?>
		<div class="col-sm-12">
			<div class="sechead white" style="font-weight: bold; color: gold;">OVERALL</div>
			<div class="ws"></div>
			<table class="table">
				<thead>
					<tr>
						<th>By Corp</th>
						<th>Count</th>
						<th>Date</th>
					</tr>
				</thead>
				<tbody>
					<tr>
			<?php 
			// get Record rescue counts
			switch ($timeframe) {
				case 'Daily':
					// most rescues on a single day
					$sql = 'SELECT startagent, count(*) AS Rescues, DATE(lastcontact)
							FROM rescuerequest
							WHERE Status = "closed-esrc"
							GROUP BY startagent, DATE(lastcontact)
							ORDER BY Rescues DESC';
					// maybe run these by hand and just have this be updated every so often?
					// very difficult to automate this!
					break;
				case 'Weekly':
					
					break;
				case 'Monthly':
					
					break;
				case 'All-Time':
				default:
					$ctrESRCrescues = $rescue->getRescueCount('closed-esrc', '2017-03-18', $end);
					$ctrSARrescues = $rescue->getRescueCount('closed-rescued', '2017-03-18', $end);
					$ctrAllRescues = intval($ctrESRCrescues) + intval($ctrSARrescues);
					$dateAllRescues = 'N/A';
					break;
			}
			?>
						<td>Most Rescues</td>
						<td><?=$ctrAllRescues?></td>
						<td><?=$dateAllRescues?></td>
					</tr>
				</tbody>
			</table>
			<div class="ws"></div>
			<table class="table">
				<thead>
					<tr>
						<th>By Pilot</th>
						<th>Count</th>
						<th>Name</th>
						<th>Date</th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td>Most Rescues</td>
						<td></td>
						<td></td>
						<td></td>
					</tr>
				</tbody>
			</table>
		</div>
		<div class="col-sm-12">
			<div class="sechead white" style="font-weight: bold; color: gold;">ESRC</div>
			<div class="ws"></div>
			<table class="table">
				<thead>
					<tr>
						<th>By Corp</th>
						<th>Count</th>
						<th>Date</th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td>Most Strandees Rescued</td>
						<td></td>
						<td></td>
					</tr>
					<tr>
						<td>Most Strandees Served</td>
						<td></td>
						<td></td>
					</tr>
					<tr>
						<td>Most Participants</td>
						<td></td>
						<td></td>
					</tr>
					<tr>
						<td>Most ESRC Activity</td>
						<td></td>
						<td></td>
					</tr>
					<tr>
						<td>Most Caches Tended</td>
						<td></td>
						<td></td>
					</tr>
					<tr>
						<td>Most Caches Sown</td>
						<td></td>
						<td></td>
					</tr>
				</tbody>
			</table>
			<br/>
			<table class="table">
				<thead>
					<tr>
						<th>By Pilot</th>
						<th>Count</th>
						<th>Name</th>
						<th>Date</th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td>Most Strandees Rescued</td>
						<td></td>
						<td></td>
						<td></td>
					</tr>
					<tr>
						<td>Most Strandees Served</td>
						<td></td>
						<td></td>
						<td></td>
					</tr>
					<tr>
						<td>Most Participants</td>
						<td></td>
						<td></td>
						<td></td>
					</tr>
					<tr>
						<td>Most ESRC Activity</td>
						<td></td>
						<td></td>
						<td></td>
					</tr>
					<tr>
						<td>Most Caches Tended</td>
						<td></td>
						<td></td>
						<td></td>
					</tr>
					<tr>
						<td>Most Caches Sown</td>
						<td></td>
						<td></td>
						<td></td>
					</tr>
				</tbody>
			</table>
		</div>
		<div class="col-sm-12">
			<div class="sechead white text-center" style="font-weight: bold;">SAR</div>
		</div>
	<?php 
			break;
		case 'personal':
			// do stuff
			break;
	}
	?>
	<!-- SAR STATS END -->
<!-- STATS DISPLAY END -->
	</div>
	<div class="ws"></div>
</div>

<script type="text/javascript">
	// datepicker
    const monthNames = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];

    var startDate,
    endDate,
    updateStartDate = function() {
        startPicker.setStartRange(startDate);
        endPicker.setStartRange(startDate);
        endPicker.setMinDate(startDate);
    },
    updateEndDate = function() {
        startPicker.setEndRange(endDate);
        startPicker.setMaxDate(endDate);
        endPicker.setEndRange(endDate);
    },
    startPicker = new Pikaday({
        field: document.getElementById('start'),
        minDate: new Date('03/18/2017'),
        showMonthAfterYear: true,
        format: 'YYYY-MMM-DD',
        toString(date, format) {
            const day = ("0" + date.getDate()).slice(-2);
            const month = monthNames[date.getMonth()];
            const year = date.getFullYear() - 1898;
            return `YC${year}-${month}-${day}`;
        },
        onSelect: function() {
            startDate = this.getDate();
            updateStartDate();
        }
    }),
    endPicker = new Pikaday({
        field: document.getElementById('end'),
        minDate: new Date('03/18/2017'),
        showMonthAfterYear: true,
        format: 'YYYY-MMM-DD',
        toString(date, format) {
            const day = ("0" + date.getDate()).slice(-2);
            const month = monthNames[date.getMonth()];
            const year = date.getFullYear() - 1898;
            return `YC${year}-${month}-${day}`;
        },
        onSelect: function() {
            endDate = this.getDate();
            updateEndDate();
        }
    }),
    _startDate = startPicker.getDate(),
    _endDate = endPicker.getDate();

    if (_startDate) {
        startDate = _startDate;
        updateStartDate();
    }

    if (_endDate) {
        endDate = _endDate;
        updateEndDate();
    }
</script>

<?php 
function debug($variable){
	if(is_array($variable)){
		echo "<pre>";
		print_r($variable);
		echo "</pre>";
		exit();
	}
	else{
		echo ($variable);
		exit();
	}
}
?>

</body>
</html>