#!/bin/bash

# ModGearman XI Manager
# Version 0.1
# 2016-02-09
# ---------------------
# Bryan Heden
# b.heden@gmail.com

# This file is part of "ModGearman XI Manager".

# "ModGearman XI Manager" is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.

# "ModGearman XI Manager" is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.

# You should have received a copy of the GNU General Public License
# along with "ModGearman XI Manager".  If not, see <http://www.gnu.org/licenses/>.

# intro / warning stuff
echo -e "\n\nModGearman XI Manager"
echo "Version 0.1"
echo "*************************"
echo "Bryan Heden"
echo "*************************"
echo "b.heden@gmail.com"
echo "*************************"

echo -e "\nWARNING! This script doesn't do any sanity checking."
echo "I'm a lazy man, and I'm making assumptions such as:"
echo "  * You're running this script from your Nagios/ModGearman Server"
echo "  * You're running this script as root"
echo "  * You don't have an SSH key for apache set up"
echo "    (*OR* you don't care if I set up another one)"
echo "  * All of your ModGearman Workers were set up to run as nagios user"
echo "  * That last one is pretty important, you might want to check"
echo "  * AND that you know that nagios user password"

echo -e "\nThis script will:"
echo "  * Create SSH key for apache user on Nagios/ModGearman Server"
echo "  * Set permissions for nagios user on ModGearman configuration directories"
echo "  * Copy apache SSH key to ModGearman Worker nagios user"

echo -e "\nThis software has been distributed WITHOUT ANY WARRANTY; without even"
echo "the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE."

echo -e "\nContinue at your own risk...\n"

read -p "Press [enter] to continue..."

echo -e "\n\nFollow the prompts!\n\n"

# create ssh key for apache user
echo -e "\nCreate SSH key for apache user"
echo "chsh -s /bin/bash apache"
chsh -s /bin/bash apache
echo -e "\nThe script assumes you choose defaults here, so I would recommend it..\n"
echo "su apache -c 'ssh-keygen -t dsa'"
su apache -c 'ssh-keygen -t dsa'
echo "su apache -c 'chmod 0500 ~/.ssh/id_dsa'"
su apache -c 'chmod 0500 ~/.ssh/id_dsa'

# get list of servers
echo -e "\nGet list of ModGearman Workers"
echo "Please enter a comma seperated list of"
echo "ModGearman Worker IPs or Hostnames"
read GEARMANLISTALL
IFS="," read -a GEARMANLIST <<< "${GEARMANLISTALL}"

# cycle through that list of servers
for GEARMANSERVER in "${GEARMANLIST[@]}"
do
	echo -e "\nWorking on $GEARMANSERVER"
	
	# copy ssh key from apache to this gearman server
	echo "scp /var/www/.ssh/id_dsa.pub root@$GEARMANSERVER:/tmp/apache.pub"
	scp /var/www/.ssh/id_dsa.pub root@$GEARMANSERVER:/tmp/apache.pub
	
	# set permissions for nagios user on modgearman configuration directories
	echo "ssh root@$GEARMANSERVER \"chgrp -R nagios /etc/mod_gearman/; chmod -R g+w /etc/mod_gearman/\"; mkdir /home/nagios/.ssh; cat /tmp/apache.pub >> /home/nagios/.ssh/authorized_keys"
	ssh root@$GEARMANSERVER "chgrp -R nagios /etc/mod_gearman/; chmod -R g+w /etc/mod_gearman/; mkdir /home/nagios/.ssh; cat /tmp/apache.pub >> /home/nagios/.ssh/authorized_keys"
	echo -e "\n"
	
	# now login to each nagios@worker and make sure we get rid of one-time connect issues
	#echo "su apache -c \"ssh nagios@$GEARMANSERVER 'exit'\""
	#su apache -c "ssh nagios@$GEARMANSERVER 'exit'"
done

echo "Changing apache shell back to nologin"
echo "chsh -s /sbin/nologin apache"
chsh -s /sbin/nologin apache

echo -e "\nHopefully everything worked out for you!"