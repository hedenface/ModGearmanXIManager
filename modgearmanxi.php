<?php
/*
ModGearman XI Manager
Version 0.1
2016-02-09
---------------------
Bryan Heden
b.heden@gmail.com

This file is part of "ModGearman XI Manager".

    "ModGearman XI Manager" is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    "ModGearman XI Manager" is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with "ModGearman XI Manager".  If not, see <http://www.gnu.org/licenses/>.
*/

/////////////////////////////////////////////////////////////////////
// gearmanxi config variables 									/////
// NOTE: YOU NEED TO CHANGE THESE TO MATCH YOUR ENVIRONMENT 	/////
/////////////////////////////////////////////////////////////////////
$gearmanxi_cfg = array(

	'workers' => array(

		'host_name' => array(
			'ip' =>		'ip_address',										// the ip address you configured in the setup.sh script
			'user' =>	'username',											// the username you used to connect to that ip address
			'cfg' =>	'/path/to/this/workers/modgearman_worker.conf',		// the configuration file you'd like to control with this component
			'initd' =>	'/etc/init.d/mod_gearman_worker'					// the service control script of the worker on the server
			),

		'example1' => array(
			'ip' =>		'192.168.1.21',
			'user' => 	'nagios',
			'cfg' =>	'/etc/mod_gearman/mod_gearman_worker.conf',
			'initd' =>	'/etc/init.d/mod_gearman_worker'
			),

		'example2.fqdn.com' => array(
			'ip' =>		'192.168.1.22',
			'user' =>	'naemon',
			'cfg' =>	'/etc/mod_gearman2/worker.conf',
			'initd' =>	'/etc/init.d/mod-gearman2-worker'
			),
		),

	// where can apache safely store the remote configuration files?
	'apache_safe_dir' => '/tmp/nagiostemp/mod_gearman',

	// which version of ModGearman is the server running?
	'mod_gearman_version' => 2,
	);
/////////////////////////////////////////////////////////////////////
// End of configuration variables 								/////
/////////////////////////////////////////////////////////////////////

// include the component helper
require_once(dirname(__FILE__) . "/../componenthelper.inc.php");

// initialization stuff
pre_init();

// start session
init_session();

// grab GET or POST variables 
grab_request_vars();

// check prereqs
check_prereqs();

// check authentication
check_authentication();

// readability's sake!
$apache_safe_dir = $gearmanxi_cfg["apache_safe_dir"];
	
// attempt to create gearman_apache_safe_dir
if (!@mkdir($apache_safe_dir, 0777, true))
	$error_msg .= "Unable to create directory: $apache_safe_dir<br />";

// attempt to delete all current configuration files (the backups on this server, not on each of the hosts)
foreach ($gearmanxi_cfg["workers"] as $worker_name => $worker)
	if (!@unlink("$apache_safe_dir/$worker_name.conf"))
		$error_msg .= "Unable to delete tempfile: $apache_safe_dir/$worker_name.conf<br />";

// handle post data
$update = grab_request_var("update", "");
$restart = grab_request_var("restart", "");
$restart_active = grab_request_var("restart_active", "");
$disconnect_worker = false;
$connect_worker = false;

// cycle through the workers and check for any connects/disconnects
foreach ($gearmanxi_cfg["workers"] as $worker_name => $worker) {
	
	// we have to replace the dots with underscores because of post (this comes up later, too)
	$safe_worker_name = str_replace(".", "_", $worker_name);

	$disconnect = grab_request_var("disconnect_$safe_worker_name", "");
	$connect = grab_request_var("connect_$safe_worker_name", "");

	if ($disconnect != "")
		control_server($worker_name, "stop");

	if ($connect != "")
		control_server($worker_name, "sart");
}

// update worker configuration files
if ($update != "") {
	
	// cycle through each worker to check for configuration
	foreach ($gearmanxi_cfg["workers"] as $worker_name => $worker) {

		// for readability
		$worker_ip = $worker["ip"];
		$worker_user = $worker["user"];
		$worker_cfg = $worker["cfg"];
		
		// replace dots with underscores
		$safe_worker_name = str_replace(".", "_", $worker_name);
		$conf_file = grab_request_var("conf_$safe_worker_name", "");


		if ($conf_file != "") {
			
			// replace the \r (dos format) with blank (nix)
			$conf_file = str_replace("\r", "", $conf_file);
			
			// write the conf file, and check for errors in the meantime
			// but we don't check for errors IF the file is written successfully, because since we've got this far, we can (not safely)
			// assume that the configuration file textarea was present to begin with, which means that the original ssh command was executed.
			if (@file_put_contents("$apache_safe_dir/$worker_name.conf.new", $conf_file) !== false) {
				
				// backup the worker's current configuration file
				// note that we don't use ssh2_* commands here
				$ssh_cmd = "ssh $worker_user@$worker_ip \"cp $worker_cfg $worker_cfg.backup_`date +%F_%H%m`\"";
				exec($ssh_cmd);
				
				// copy the new file to the server
				$ssh_cmd = "scp $apache_safe_dir/$worker_name.conf.new $worker_user@$worker_ip:$worker_cfg";
				exec($ssh_cmd);
				
				// delete our .conf.new file!
				if (!unlink("$gearman_apache_safe_dir/$worker_name.conf.new"))
					$error_msg .= "Unable to delete tempfile: $apache_safe_dir/$worker_name.conf.new<br />";
					
			} else
				$error_msg .= "Unable to create tempfile: $apache_safe_dir/$worker_name.conf.new<br />";
		}
	}
	
	// we want to restart all of the workers we just made a change to
	$restart_active = "Restart ACTIVE Workers";
}

// restart ALL client workers
// THIS IS POTENTIALLY STUPID AND DANGEROUS BUT WHATEVER
if ($restart != "") {

	// cycle through each worker to restart!
	foreach ($gearmanxi_cfg["workers"] as $worker_name => $worker)
		control_server($worker_name, "restart");
}

// restart only active client workers
if ($restart_active != "") {
	
	// cycle through each worker to restart!
	foreach ($gearmanxi_cfg["workers"] as $worker_name => $worker) {
	
		// replace dots with underscores, and check if we have an active_$worker_name
		// only proceed if we do!
		$safe_worker_name = str_replace(".", "_", $worker_name);
		$active = grab_request_var("active_$safe_worker_name", "");
		if ($active != "")
			control_server($worker_name, "restart");
	}
}

// get worker information
$gearman_worker_html = "";
$ssh_output = array();
foreach ($gearmanxi_cfg["workers"] as $worker_name => $worker) {

	// for readability
	$worker_ip = $worker["ip"];
	$worker_user = $worker["user"];
	$worker_cfg = $worker["cfg"];
	$worker_initd = $worker["initd"];

	// the scp(ssh) command to pull this gearmans conf over
	$ssh_cmd = "scp $worker_user@$worker_ip:$worker_cfg $apache_safe_dir/$worker_name.conf 2>&1";
		
	// execute the ssh command and get each line that we checked for in its own array
	$ssh_output[$worker_name] = array();
	exec($ssh_cmd, $ssh_output_exec);
			
	// check if we have any error output from the ssh command
	if (preg_match("/^ssh:/", $ssh_output_exec)) {

		// this is unexpected maintenance, can we handle the errors gracefully?
		$ssh_output[$worker_name][] = "UNEXPECTED_ERROR";
	}
	
	// execute a check_gearman command to check and see if this worker is disconnected!
	// this only works if the $worker_name variable is the fqdn name that gearmand expects
	// AND it only works if our gearman server is running on localhost
	// good output starts with: "check_gearman OK -"
	// bad output starts with: "check_gearman WARNING -" - THIS IS THE ONE WE'RE WORRIED ABOUT RUH-ROH RAGGY
	$check_array = array();
	$check_cmd = "ssh $worker_user@$worker_ip \"$worker_initd status\"";
	exec($check_cmd, $check_array);
	foreach ($check_array as $check_array_line) {
		if (strpos($check_array_line, "mod_gearman_worker is not running") !== false || strpos($check_array_line, "mod_gearman2_worker is not running") !== false) {
			$ssh_output[$worker_name][] = "DISCONNECTED";
		}
	}
            
	// build our gearman_worker_html that contains the information for each worker
	if (in_array("UNEXPECTED_ERROR", $ssh_output[$worker_name])) {
		$gearman_worker_html .= <<<HTML
			<div class="error worker">
				<div class="name">{$worker_name}</div>
				<div class="ip">{$worker_ip}</div>
				<div class="message">Unable to open SSH connection</div>
				<div class="clear"></div>
			</div>
			<div class="clear"></div>
HTML;

	} elseif (in_array("DISCONNECTED", $ssh_output[$worker_name])) {
		$conf_file_data = @file_get_contents("$apache_safe_dir/$worker_name.conf");
		$gearman_worker_html .= <<<HTML
			<div class="disconnected worker">
				<div class="name">{$worker_name} NOT CONNECTED</div>
				<div class="ip">{$worker_ip}</div>
				<input type="submit" name="connect_{$worker_name}" value="Connect this Worker" />
				<textarea name="conf_$worker_name" id="conf_$worker_name" class="conftext">
					{$conf_file_data}
				</textarea>
				<div class="clear"></div>
			</div>
			<div class="clear"></div>
HTML;

	} else {
		$conf_file_data = @file_get_contents("$apache_safe_dir/$worker_name.conf");
		$gearman_worker_html .= <<<HTML
			<div class="conf worker">
				<input type="hidden" name="active_$worker_name" value="true" />
				<div class="name">$worker_name</div>
				<div class="ip">$worker_ip</div>
				<input type="submit" name="disconnect_{$worker_name}" value="Disconnect this Worker" />
				<textarea name="conf_$worker_name" id="conf_$worker_name" class="conftext">
					{$conf_file_data}
				</textarea>
				<div class="clear"></div>
			</div>
			<div class="clear"></div>
HTML;
	}
}
	

?>
<!DOCTYPE html>
<html>
<head>
	<title>ModGearman XI Manager</title>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<?php do_page_head_links(); ?>
	<style type="text/css">
#gearman_top {
	margin: 10px 20px 25px;
	border: 1px solid black;
	display: inline-block;
	padding: 10px;
}
.worker {
	margin: 10px 20px 25px;
	clear: both;
	border: 1px solid black;
	background-color: #00ff00;
	display: inline-block;
	padding: 10px;
}
textarea.conftext {
	width: 50em;
	height: 20em;
}
.worker .name {
	font-size: 1.5em;
	font-weight: bold;
	text-decoration: underline;
}
.worker .ip {
	font-size: 1.25em;
	font-style: italic;
}
.error {
	background-color: #fe2e2e;
}
.disconnected {
	background-color: #ff8000;
}
.worker textarea {
	float: left;
	clear: both;
}
.worker input {
	float: left;
	clear: both;
}
.clear {
	clear: both;
}
	</style>
	<script type="text/javascript">
		window.setInterval(function() {
			$.ajax("modgearmanxi.ajax.php?ver=<?php echo $gearmanxi_cfg["mod_gearman_version"]; ?>").done(function(html) {
				$("#gearman_top").empty().append(html);
			});
		}, 1000);
	</script>
</head>
<body>
	<h2>gearman_top Output</h2>
	<div id="gearman_top"></div>
	<h2>ModGearman Workers</h2>
	<div id="workers">
		<form method="post">
			<input type="submit" name="update" value="Update Worker Configuration" />
			<input type="submit" name="restart" value="Restart ALL Workers" />
			<input type="submit" name="restart_active" value="Restart ACTIVE Workers" />
			<div class="clear"></div>
			<?php
			// show worker status/config
			echo $gearman_worker_html; ?>
		</form>
		<div class="clear"></div>
	</div>
</body>
</html>
<?php

// control a server
function control_server($worker_name, $cmd = "restart") {

	global $gearmanxi_cfg;

	$worker_ip = $gearmanxi_cfg["workers"][$worker_name]["ip"];
	$worker_user = $gearmanxi_cfg["workers"][$worker_name]["user"];
	$worker_cfg = $gearmanxi_cfg["workers"][$worker_name]["cfg"];
	$worker_initd = $gearmanxi_cfg["workers"][$worker_name]["initd"];

	// only accept start/stop/restart
	if (($cmd != "start") &&
		($cmd != "stop"))
		$cmd = "restart";

	// the restart command to execute
	$ssh_cmd = "ssh $worker_user@$worker_ip \"/etc/init.d/mod_gearman_worker $cmd\" 2>&1";
	$ssh_output = array();
	exec($ssh_cmd, $ssh_output);
	
	// check for /NO:ssh errors/ and for gearman errors 
	foreach ($ssh_output as $output) {
		if (strpos($output, "[ERROR]") !== false) {
			echo "$worker_name [ $worker_ip ]: " . substr($output, strpos($output, "[ERROR]")) . "\n" .
				strtoupper($cmd) . " FAILED - (Correct your error and try again?)\n\n";
		}
	}
}
?>
