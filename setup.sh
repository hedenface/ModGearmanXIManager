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

# introduction, etc.
echo ""
echo "*************************"
echo " ModGearman XI Manager"
echo " Version 0.1"
echo "*************************"
echo " Bryan Heden"
echo " b.heden@gmail.com"
echo "*************************"
echo ""
echo ""
echo "This script will:"
echo "  1) Create an SSH Key for your Apache user"
echo "  2) Connect to a series of ModGearman Workers to copy that SSH Key to the"
echo "     authorized_keys file for the user you specify"
echo ""
echo ""
echo "This script is making the following assumptions:"
echo "  * You're running this script from your Nagios/ModGearman Server"
echo "  * You're running this script as root"
echo "  * You don't have an SSH key for apache set up"
echo "    (*OR* you don't care if I set up another one)"
echo "  * That whatever user mod_gearman_worker or mod-gearman2-worker is"
echo "    running as has a login shell available for ssh"
echo ""
echo ""
echo "NOTICE! This software has been distributed WITHOUT ANY WARRANTY; without even"
echo "the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE."
echo ""
echo "Continue at your own risk..."

# wait for input if the user is actually serious about running this script
while true; do
	read -n 1 -p "Press [enter] to continue or any other key to exit... " CONTINUE_OR_NAH
	case $CONTINUE_OR_NAH in
		"" ) break;;
		* ) echo ""; exit;;
	esac
done

# get the apache user
while true; do
	echo ""
	read -p "Who is the apache user on this server? [apache]: " APACHE_USER
	case $APACHE_USER in
		"" ) APACHE_USER="apache"; break;;
		* ) break;;
	esac
done

# get the apache user's home dir
APACHE_HOMEDIR=$(eval echo "~$APACHE_USER")

# get the apache user's original shell, so we can put it back how we found it when we're done
APACHE_SHELL=$(getent passwd $APACHE_USER | awk -F: '{ print $NF }')

# if its not already /bin/bash, then we need to change it to that
if [ ! "$APACHE_SHELL" == "/bin/bash" ]; then
	echo ""
	echo "Change shell for apache user ($APACHE_USER)..."
	echo "chsh -s /bin/bash $APACHE_USER"
	chsh -s /bin/bash $APACHE_USER
fi

# create an .ssh directory under apaches homedir
echo ""
echo "Create SSH directory for apache user ($APACHE_USER)..."
echo "mkdir -p $APACHE_HOMEDIR/.ssh"
mkdir -p $APACHE_HOMEDIR/.ssh
echo "chown -R $APACHE_USER $APACHE_HOMEDIR/.ssh"
chown -R $APACHE_USER $APACHE_HOMEDIR/.ssh

# set up the ssh-keys and change permissions for the apache user
echo ""
echo "Create SSH key for apache user ($APACHE_USER)..."
echo ""
echo "*************************"
echo "NOTE: The script assumes you choose defaults here, so I would recommend it.."
echo ""
echo "su $APACHE_USER -c 'ssh-keygen -t dsa'"
su $APACHE_USER -c 'ssh-keygen -t dsa'
echo "su $APACHE_USER -c 'chmod 0500 $APACHE_HOMEDIR/.ssh/id_dsa'"
su $APACHE_USER -c 'chmod 0500 ~/.ssh/id_dsa'

# get list of modgearman workers/servers
echo ""
echo "Please enter a comma seperated list of all ModGearman Worker IPs or Hostnames: "
read GEARMAN_LIST_ALL
IFS="," read -a GEARMAN_LIST <<< "${GEARMAN_LIST_ALL}"

# cycle through that list of servers
for GEARMAN_WORKER in "${GEARMAN_LIST[@]}"
do
	echo ""
	echo ""
	echo "Working on $GEARMAN_WORKER..."

	# get the user to connect to
	while true; do
		read -p "Who is the user you want to connect as on $GEARMAN_WORKER? [nagios]: " GEARMAN_USER
		case $GEARMAN_USER in
			"" ) GEARMAN_USER="nagios"; break;;
			* ) break;;
		esac
	done
	
	# copy ssh key from apache to this gearman server
	echo ""
	echo "Copying SSH Keys to $GEARMAN_WORKER..."
	echo "ssh-copy-id -i $APACHE_HOMEDIR/.ssh/id_dsa.pub $GEARMAN_USER@$GEARMAN_WORKER"
	ssh-copy-id -i $APACHE_HOMEDIR/.ssh/id_dsa.pub $GEARMAN_USER@$GEARMAN_WORKER

	# give some additional instructions
	echo ""
	echo "*************************"
	echo "NOTE: You're going to need to login to $GEARMAN_WORKER and ensure that"
	echo "  $GEARMAN_USER can read/write the config files you specify in"
	echo "  the configuration array inside of modgearmanxi.php!"

	# lets just make sure our ssh is set up properly and print out a warning message if something went wrong
	SSH_TEST=$(su $APACHE_USER -c "ssh -o StrictHostKeyChecking=no -o PasswordAuthentication=no $GEARMAN_USER@$GEARMAN_WORKER 'echo success'")
	if [ ! "$SSH_TEST" == "success" ]; then
		echo ""
		echo "*************************"
		echo "*************************"
		echo "WARNING!"
		echo ""
		echo "This script attempted to connect automatically to $GEARMAN_USER@$GEARMAN_WORKER from the $APACHE_USER account, and something went wrong!"
		echo "You'll need to manually troubleshoot and resolve the issue in order for ModGearman XI Manager to manage this ModGearman Worker instance."
		echo ""
		read -n 1 -s -p "Press any key to confirm that you've read the above message thoroughly: "
		echo ""
		echo "*************************"
		echo "*************************"
	fi
done

# change the shell back to what it was before we started
if [ ! "$APACHE_SHELL" == "/bin/bash" ]; then
	echo ""
	echo "Changing apache shell back to $APACHE_SHELL"
	echo "chsh -s $APACHE_SHELL $APACHE_USER"
	chsh -s $APACHE_SHELL $APACHE_USER
fi

# goodbye!
echo ""
echo ""
echo "Thats it. You're all set!"
echo ""