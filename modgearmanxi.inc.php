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

// include the component helper
require_once(dirname(__FILE__) . "/../componenthelper.inc.php");

$modgearmanxi_component_name = "modgearmanxi";

modgearmanxi_component_init();

function modgearmanxi_component_init() {

	global $modgearmanxi_component_name;

	$args = array(
		COMPONENT_NAME => 			$modgearmanxi_component_name,
		COMPONENT_AUTHOR => 		"Bryan Heden <b.heden@gmail.com>",
		COMPONENT_DESCRIPTION => 	"Manage ModGearman daemon and workers from a central location from within Nagios XI.",
		COMPONENT_TITLE => 			"ModGearman XI Manager",
		COMPONENT_VERSION => 		0.1,
		COMPONENT_DATE => 			"02/09/2016");

	register_component($modgearmanxi_component_name, $args);

	register_callback(CALLBACK_MENUS_INITIALIZED, "modgearmanxi_component_addmenu");
}

function modgearmanxi_component_addmenu($arg = null) {

	global $modgearmanxi_component_name;

	$urlbase = get_component_url_base($modgearmanxi_component_name);

	$mi = find_menu_item(MENU_ADMIN, "menu-admin-managesystemconfig", "id");
	if ($mi == null)
		return;

	$order = grab_array_var($mi, "order","");
	if ($order == "")
		return;

	$neworder = $order + 1;

	add_menu_item(MENU_ADMIN, array(
		"type" => 	"link",
		"title" => 	"ModGearman XI Manager",
		"id" => 	"menu-admin-modgearmanxi",
		"order" => 	$neworder,
		"opts" => 	array(
			"href" =>	$urlbase . "/modgearmanxi.php",
			)
		));
}

?>