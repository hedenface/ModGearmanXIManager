# ModGearmanXIManager
Nagios XI Component for managing ModGearman installation

## Overview
This is a component used to manage a variety of ModGearman Servers/Workers, possibly running different versions of ModGearman, from a central location - within the Nagios XI interface. 

ModGearman XI Manager is a fork of an earlier work, ModGearman Manager (https://exchange.nagios.org/directory/Addons/Components/ModGearman-Manager/details).

## How to Use
1. Download the ZIP file (http://heden.consulting/nagios/modgearmanxi.zip)
2. Upload it to your Nagios XI instance (Admin -> Manage Components -> Upload)
3. Run the included setup.sh file, which sets up necessary permissions on the workers
4. Update the $gearmanxi_cfg array in modgearmanxi.php to reflect your environment
5. Click on Admin -> ModGearman XI Manager and enjoy managing your ModGearman instances!

## Changelog

### version 0.1
* Cleaned up code and information to reflect new version
* Accounted for different versions of ModGearman running
* Updated README with better instructions

## ModGearman
You can find out more about ModGearman at https://labs.consol.de/nagios/mod-gearman/index.html

You can easily setup ModGearman to be used with your Nagios XI (or Core) instance at https://assets.nagios.com/downloads/nagiosxi/docs/Integrating_Mod_Gearman_with_Nagios_XI.pdf
