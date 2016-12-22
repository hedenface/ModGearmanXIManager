<?php
/*
ModGearman XI Manager
---------------------
Version 1.0
2016-02-15
Initial release
Bryan Heden
b.heden@gmail.com
---------------------
Version 1.0.1
2016-12-12
Steven Beauchemin
SBeauchemin@gmail.com
Minor modifications for systemctl awareness
apologies for any unnecessary code reformatting :(
---------------------

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

// do the needful nagiosxi stuff
require_once(dirname(__FILE__) . "/../componenthelper.inc.php");

pre_init();
init_session();
grab_request_vars();
check_prereqs();
check_authentication();

// lets grab our configuration array
require_once(dirname(__FILE__) . "/modgearmanxi.config.inc.php");

$errors = array();


route_request();
function route_request() {

$cmd = grab_request_var("cmd", "");

switch($cmd) {

  case "Update Configuration":
    update_worker_cfg();
    break;
  
  case "Start Worker":
    control_worker("start");
    break;
  
  case "Restart Worker":
    control_worker("restart");
    break;
  
  case "Attempt to Start Worker":
    control_worker("start");
    break;
  
  case "Stop Worker":
    control_worker("stop");
    break;
  
  case "top":
    show_gearman_top();
    break;
  
  default:
    break;
  }
  
  // always show page
  show_page();
}


// print gearman top output and exit
function show_gearman_top() {

  // SLB 2016-12-13 - gearman_top is not called gearman_top2 in the rpm from labs-consol-stable.rhel7.noarch.rpm
  // but lets be flexible
  if (is_executable('/bin/gearman_top2')) {
    $cmd = "/bin/gearman_top2 -b";
  } elseif (is_executable('/bin/gearman_top')) {
    $cmd = "/bin/gearman_top -b";
  } else {
    $cmd = "/bin/gearman_top2 -b";
  }
  
  echo "<pre>";
  system($cmd);
  echo "</pre>";
  exit();
}


// build the main page and tabs
function show_page() {

  global $gearmanxi_cfg;
  global $errors;
  $make_worker_html = true;
  
  // make the apache_safe_dir if we need to
  if (!file_exists($gearmanxi_cfg["apache_safe_dir"]))
  if (!mkdir($gearmanxi_cfg["apache_safe_dir"], 0777, true))
  $errors[] .= "Unable to create apache safe directory: " . $gearmanxi_cfg["apache_safe_dir"];
  
  // rudimentary check to see if user has set up the config array
  if (!empty($gearmanxi_cfg["worker"]["example1"]) && !empty($gearmanxi_cfg["worker"]["example2.fqdn.com"])) {
    $errors[] = "It looks like you haven't set up the configuration array in " . dirname(__FILE__) . "/modgearmanxi.config.inc.php";
    $make_worker_html = false;
  }
  
  do_page_start(array("page_title" => "ModGearman XI Manager"), true);
  echo "<h1>ModGearman XI Manager</h1>";
  show_errors($errors);
  
  // build the correlating list of tabs and div content for that div - all while building a portion of the overview status table
  $worker_tabs = "";
  $worker_divs = "";
  $overview_status_table = "<table class='infotable table table-condensed table-bordered' style='width: 60%;'>";
  if ($make_worker_html) {
    foreach($gearmanxi_cfg["worker"] as $worker_name => $worker) {
      
      $worker_id = base64_encode($worker_name);
      
      // build the tabs section
      $worker_tabs .= "<li><a href='#$worker_name' title='$worker_name'><span>$worker_name</span></a></li>\n";
      
      // get the info we need to build the rest of the html
      $worker_can_connect = test_ssh_connectivity($worker["user"], $worker["ip"]);
      $worker_gearman_cfg_readable = test_cfg_writable($worker["user"], $worker["ip"], $worker["cfg"]);
      $worker_gearman_cfg_writable = test_cfg_writable($worker["user"], $worker["ip"], $worker["cfg"]);
      $worker_gearman_dir_writable = test_dir_writable($worker["user"], $worker["ip"], dirname($worker["cfg"]));
      
      // copy the remote file so we can edit it if we need to
      $worker_local_conf = local_copy_cfg($worker["user"], $worker["ip"], $worker["cfg"]);
      $worker_copy_conf_successful = scp_remote_file($worker["user"], $worker["ip"], $worker["cfg"], $worker_local_conf);
      
      // service control stuff
      $worker_gearman_running = service_status($worker["user"], $worker["ip"], $worker["initd"], $worker_gearman_running_text);
      $action_value = "Restart Worker";
      $stop_button = "<input type='submit' name='cmd' value='Stop Worker' style='margin-left: 5px;' />";
      if ($worker_gearman_running == false) {
        $stop_button = "";
        if (strpos($worker_gearman_running_text, "is not running") !== false)
          $action_value = "Start Worker";
        else
          $action_value = "Attempt to Start Worker";
      }
    
      // build this workers div
      $worker_divs .= 
      "<div id='$worker_name' style='width: 60%;'>" .
      "<form method='post'>" .
      "<input type='hidden' name='worker_id' value='$worker_id' />" .
      "<table class='infotable table table-condensed table-striped table-bordered'>" .
      "<tr><td>Username:</td><td>" . $worker["user"] . "</td></tr>" .
      "<tr><td>IP Address:</td><td>" . $worker["ip"] . "</td></tr>" .
      "<tr><td>Configuration File:</td><td>" . $worker["cfg"] . "</td></tr>" .
      "<tr><td>Service Control:</td><td>" . $worker["initd"] . "</td></tr>" .
      "</table>" .
      worker_status_table("infotable table table-condensed table-striped table-bordered", $worker_can_connect, 
                           $worker_gearman_cfg_readable, $worker_gearman_cfg_writable, $worker_gearman_dir_writable, 
                           $worker_gearman_running, $worker_gearman_running_text) .
      "<input type='submit' name='cmd' value='$action_value' />" .
      $stop_button .
      "<input type='submit' name='cmd' value='Update Configuration' style='float: right;' />" .
      "<textarea name='conf' rows='24' style='width: 100%; margin-top: 5px;'>" .
      file_get_contents($worker_local_conf) .
      "</textarea>" .
      "</form>" .
      "</div>";
      
      // build the status table for overview
      $overview_status_table .= 
      "<tr>" .
      "<td><strong>$worker_name</strong>&nbsp;" . $worker["user"] . "@" . $worker["ip"] . ":" . $worker["cfg"] . " (" . $worker["initd"] . ")</td>" .
      "</tr><tr>" .
      "<td align='right'>" .
      worker_status_table("infotable table table-condensed table-striped table-bordered", $worker_can_connect, 
                           $worker_gearman_cfg_readable, $worker_gearman_cfg_writable, $worker_gearman_dir_writable, 
                           $worker_gearman_running, $worker_gearman_running_text) .
      "<form method='post'>" .
      "<input type='hidden' name='worker_id' value='$worker_id' />" .
      "<div style='float: right; display: inline-block; margin-top: 5px;'>" .
      "<input type='submit' name='cmd' value='$action_value' />" .
      $stop_button .
      "</div>" .
      "</form>" .
      "</td>" .
      "</tr>";
      
    }
  }
  $overview_status_table .= "</table>";
  ?>
  <script>
  $(function () {
    $("#tabs").tabs().show();
  });
  window.setInterval(function() {
    $.ajax("modgearmanxi.php?cmd=top&ver=<?php echo $gearmanxi_cfg["mod_gearman_version"]; ?>").done(function(html) {
      $("#gearman_top").empty().append(html);
    });
  }, 1000);
  </script>
  <p>Manage all of your remote ModGearman Workers from a central location!</p>
  <p>In order to make full use of this component, you'll need to make sure that 
     this servers apache user can connect remotely - without password authentication -
     to each of the worker servers you want to manage. The user that you connect as to 
     each of those servers needs to have read/write access to the configuration 
     files listed in modgearmanxi.config.inc.php.</p>
  <p>There is a script that you can run (it isn't necessary as long as you've met the 
     previous listed requirements) to make all that easier. Its located at 
  <strong style="font-family: courier;"><?php echo dirname(__FILE__) . "/setup.sh" ?></strong>.</p>
  <div id="tabs" class="hide">
  <ul class="tabnavigation">
  <li><a href="#overview" title="Overview"><span>Overview</span></a></li>
  <?php echo $worker_tabs; ?>
  </ul>
  <div id="overview" class="ui-tabs-hide">
  <h5 class="ul">gearman_top Output</h5>
  <div id="gearman_top" style="display: inline-block;"></div>
  <h5 class="ul">ModGearman Workers</h5>
  <?php echo $overview_status_table; ?>
  </div>
  <?php echo $worker_divs; ?>
  </div>
  <?php
  
}


// quick and drity status table, so we don't have to repeat this code multiple times when we build the page
function worker_status_table($table_class, $worker_can_connect, $worker_gearman_cfg_readable, 
                             $worker_gearman_cfg_writable, $worker_gearman_dir_writable, 
                             $worker_gearman_running, $worker_gearman_running_text) {
  return
  "<table class='$table_class'>" .
  "  <tr>" .
  "    <td style='width: 32px;' align='center'>" . nagiosxi_img($worker_can_connect) . "</td>" .
  "    <td>SSH Connectivity</td>" .
  "  </tr>" .
  "  <tr>" .
  "    <td align='center'>" . nagiosxi_img($worker_gearman_cfg_readable) . "</td>" .
  "    <td>Configuration File Readable</td>" .
  "  </tr>" .
  "  <tr>" .
  "    <td align='center'>" . nagiosxi_img($worker_gearman_cfg_writable) . "</td>" .
  "    <td>Configuration File Writable</td>" .
  "  </tr>" .
  "  <tr>" .
  "    <td align='center'>" . nagiosxi_img($worker_gearman_dir_writable) . "</td>" .
  "    <td>Configuration Directory Writable</td>" .
  "  </tr>" .
  "  <tr>" .
  "    <td align='center'>" . nagiosxi_img($worker_gearman_running) . "</td>" .
  "    <td>$worker_gearman_running_text</td>" .
  "  </tr>" .
  "</table>";
}


// basic function for error display on the main page
function show_errors($errors = array()) {
  if (count($errors) > 0) { ?>
    <div id="message">
    <ul class="errorMessage" style="padding: 10px 0 10px 30px;">
    <?php foreach ($errors as $k => $msg) { ?>
    <li><?php echo $msg; ?></li>
    <?php } ?>
    </ul>
    </div>
  <?php }	
}


// test ssh connectivity to a server (from apache obviously)
function test_ssh_connectivity($user, $host) {
  exec_ssh_command($user, $host, "echo ''", $output, $return_var);
  if ($return_var == 0)
    return true;
  return false;
}


// test if a given file is writable via ssh
function test_cfg_readable($user, $host, $file) {
  exec_ssh_command($user, $host, "cat $file", $output, $return_var);
  if ($return_var == 0)
    return true;
  return false;
}


// test if a given file is writable via ssh
function test_cfg_writable($user, $host, $file) {
  exec_ssh_command($user, $host, "touch $file", $output, $return_var);
  if ($return_var == 0)
    return true;
  return false;
}


// check if a given dir is writable via ssh
function test_dir_writable($user, $host, $dir) {
  exec_ssh_command($user, $host, "touch $dir/test", $output, $return_var);
  if ($return_var == 0) {
    exec_ssh_command($user, $host, "rm -f $dir/test");
    return true;
  }
  return false;
}


// the options we're going to use for all of our ssh calls
function ssh_options() {
  $ssh_options =
    "-o PasswordAuthentication=no " .
    "-o StrictHostKeyChecking=no " .
    "-o GSSAPIAuthentication=no ";
  return $ssh_options;
}


// basic ssh command functionality used throughout
function exec_ssh_command($user, $host, $cmd, &$output, &$return_var) {
  $ssh_options = ssh_options();
  exec("ssh $ssh_options $user@$host \"$cmd\"", $output, $return_var);
}


function service_status($user, $host, $script, &$return_output) {
  // this syntax requires that 'status' be part of the initd variable
  exec_ssh_command($user, $host, "$script", $output, $return_var);

  // syntax using older /etc/inet.d
  if (strpos($script, "init.d") !== false) {
    $response = $output[0];
    if ($return_var == 0) {
      $return_output = $response;
      if (strpos($response, "is running") !== false)
      return true;
    } else {
      if (strpos($response, "is not running") !== false)
      $return_output = $response;
      else
      $return_output = "Unknown error running service control script";
    }
    return false;
  // syntax using manage_services - as the nagios user type 'sudo -l' to see...
  // if using Nagios script to "manage_services" then we guess you are in systemctl turf
  } elseif (strpos($script, "manage_services") !== false) {

    // parse the input and pull the important pieces
    foreach ($output as $badline) {
      $line = trim($badline);
      if (strpos($line, "Active") !== false) {
        $state = $line;
      }
      if (strpos($line, "Main PID") !== false) {
        $pid = $line;
      }
    }
    // construct an output response
    $response = $pid . " - " . $state;

    // use the responses and return state to update the display
    if ($return_var == 0) {
      $return_output = $response;
      if (strpos($response, "running") !== false)
      return true;

    } else {
      if (strpos($response, "dead") !== false)
      $return_output = $response;
      else
      $return_output = "Unknown error running service control script";
    }
    return false;
  // syntax using systemctl - same as above but here so you have options to treat it differently
  } elseif (strpos($script, "systemctl") !== false) {

    // parse the input and pull the important pieces
    foreach ($output as $badline) {
      $line = trim($badline);
      if (strpos($line, "Active") !== false) {
        $state = $line;
      }
      if (strpos($line, "Main PID") !== false) {
        $pid = $line;
      }
    }
    // construct an output response
    $response = $pid . " - " . $state;

    // use the responses and return state to update the display
    if ($return_var == 0) {
      $return_output = $response;
      if (strpos($response, "running") !== false)
      return true;
    } else {
      if (strpos($response, "dead") !== false)
      $return_output = $response;
      else
      $return_output = "Unknown error running service control script";
    }
    return false;
  }
}


// copy the specified file over from remote to local
function scp_remote_file($user, $host, $remote_file, $local_file) {
  $ssh_options = ssh_options();
  exec("scp $ssh_options $user@$host:/$remote_file $local_file", $output, $return_var);
  if ($return_var == 0)
    return true;
  return false;
}


// copy the specified file over from local to remote
function scp_local_file($local_file, $user, $host, $remote_file) {
  $ssh_options = ssh_options();
  exec("scp $ssh_options $local_file $user@$host:/$remote_file", $output, $return_var);
  if ($return_var == 0)
    return true;
  return false;
}


// get a common theme for building local configuration files
function local_copy_cfg($user, $host, $file) {
  global $gearmanxi_cfg;
  $safe_dir = $gearmanxi_cfg['apache_safe_dir'];
  $hash = md5($user . $host . $file);
  $file = rtrim($safe_dir, "/") . "/" . $hash;
  return $file;
}


// if val is false, show red !, if true show green check
function nagiosxi_img($val) {
  $image_dir = get_base_url() . "/images/";
  if ($val) {
    $src = $image_dir . "ok_small.png";
    $title = $alt = "Ok";
  } else {
    $src = $image_dir . "critical_small.png";
    $title = $alt = "Critical";
  }
  return "<img src='$src' alt='$alt' title='$title' />";
}


// control a worker with their specified initd
// action = restart, start, stop
function control_worker($action) {
  global $gearmanxi_cfg;
  global $errors;
  $worker_id = base64_decode(grab_request_var("worker_id", ""));
  if ($worker_id === false) {
    $errors[] = "Attempted to control worker with no worker specified";
    return false;
  }
  
  // make sure user input sane
  if ($action !== "start" && $action !== "restart" && $action !== "stop") {
    $errors[] = "Available options for service control: start/restart/stop";
    return false;
  }
  
  $worker = $gearmanxi_cfg["worker"][$worker_id];

  // deal with 'status' as it can be in the middle or the end of the $worker["initd"] definition
  // so just change it wherever it is to the desired action
  $param = preg_replace('/status/', $action, $worker["initd"]);
  
  exec_ssh_command($worker["user"], $worker["ip"], "$param", $output, $return_var);
  if ($return_var == 0)
    return true;
  
  return false;
}


// upload configuration
function update_worker_cfg() {

  global $gearmanxi_cfg;
  global $errors;
  $worker_id = base64_decode(grab_request_var("worker_id", ""));
  $cfg_data = grab_request_var("conf", "");
  
  if ($worker_id === false) {
    $errors[] = "Attempted to upload configuration file to unidentified worker";
    return false;
  }
  
  $worker = $gearmanxi_cfg["worker"][$worker_id];
  $local_file = local_copy_cfg($worker["user"], $worker["ip"], $worker["cfg"]);
  $timestamp = date('Y-m-d-H-i-s');
  $tmp_file = $local_file . $timestamp . ".tmp";
  
  // copy the post data to our tmp file
  file_put_contents($tmp_file, $cfg_data);
  
  // backup users existing configuration file, as long as the directory is writable
  if (test_dir_writable($worker["user"], $worker["ip"], dirname($worker["cfg"]))) {
    exec_ssh_command($worker["user"], $worker["ip"], "cp " . $worker["cfg"] . " " . $worker["cfg"] . ".$timestamp.bak", $output, $return_var);
    if ($return_var !== 0) {
      $errors[] = "Something went wrong! Unable to create backup of worker configuration";
    }
  } else {
    $errors[] = "Configuration directory not writable by " . $worker["user"] . "@" . $worker["ip"] . "! Backup may exist at $local_file";
  }
  
  // scp this file over to our server
  if (!scp_local_file($tmp_file, $worker["user"], $worker["ip"], $worker["cfg"]))
    $errors[] = "Something went wrong! Unable to copy $tmp_file to " . $worker["user"] . "@" . $worker["ip"] . ":" . $worker["cfg"];
}

?>
