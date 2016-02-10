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

// get request var
$version = 1;
if (!empty($_REQUEST['ver']))
    $version = $_REQUEST['ver'];

// change command if we explicitly set version to 2
if ($version == 2)
    $cmd = "gearman_top2 -b";
else
    $cmd = "gearman_top -b";

// simply prints the output of $cmd to the inside of some pre tags
echo "<pre>";
system($cmd);
echo "</pre>";

exit();
?>