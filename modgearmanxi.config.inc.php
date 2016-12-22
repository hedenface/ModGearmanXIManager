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

/////////////////////////////////////////////////////////////////////
// gearmanxi config variables                                   /////
// NOTE: YOU NEED TO CHANGE THESE TO MATCH YOUR ENVIRONMENT     /////
///////////////////////////////////////////////////////////////////*/

// initd must contain the syntax that returns status - these examples should work
// initd systems
// 'initd' =>'/etc/init.d/mod-gearman2-worker status'
// 'initd' =>'sudo /etc/init.d/mod-gearman2-worker status'

// systems using systemctl
// 'initd' =>'systemctl status mod-gearman-worker.service'
// 'initd' =>'sudo systemctl status mod-gearman-worker.service'

// If you use the Nagios script manage_services.sh then we guess you are using systemctl
// 'initd' =>'/usr/local/nagiosxi/scripts/manage_services.sh status mod-gearman-worker'
// 'initd' =>'sudo /usr/local/nagiosxi/scripts/manage_services.sh status mod-gearman-worker'
// for this to work you need to edit /usr/local/nagiosxi/scripts/manage_services.sh and add to the 'second' line
// and make it match the command you use. Add the the end of the line and include the new service you wish to control
// second=("postgresql" "httpd" <snipped> "mod-gearman-worker")

$gearmanxi_cfg = array(

  'worker' => array(

  //  'host_name' => array(
  //    'ip' =>'ip_address',                                    // the ip address you configured in the setup.sh script
  //    'user' =>'username',                                    // the username you used to connect to that ip address
  //    'cfg' =>'/path/to/this/workers/modgearman_worker.conf', // the configuration file you'd like to control with this component
  //    'initd' =>'/etc/init.d/mod_gearman_worker status'       // the service control script of the worker on the server
  //  ),

    'example1' => array(
      'ip' =>'192.168.1.21',
      'user' =>'nagios',
      'cfg' =>'/etc/mod_gearman/mod_gearman_worker.conf',
      'initd' =>'/etc/init.d/mod_gearman_worker status'
    ),

    'example2.fqdn.com' => array(
      'ip' =>'192.168.1.22',
      'user' =>'naemon',
      'cfg' =>'/etc/mod_gearman2/worker.conf',
      'initd' =>'/etc/init.d/mod-gearman2-worker status'
    ),
  ),

  // where can apache safely store the remote configuration files?
  'apache_safe_dir' => '/tmp/nagiostemp/mod_gearman',

  // which version of ModGearman is the server running?
  'mod_gearman_version' => 2,
);
