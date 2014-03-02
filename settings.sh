#!/bin/sh

# This file contains settings specific to your setup

# These are pretty sane defaults for connecting to rtorrents rpc mechanism
export RTORRENT_RPC_TIME_OUT="15"
export RTORRENT_SCGI_HOST="127.0.0.1"
export RTORRENT_SCGI_PORT="5000"

# This is a pretty sane default for the path to python
export PYTHON_PATH="/usr/bin/python"

# The directory where sickbeard video files are put by rtorrent after they finish downloading
#export SICKBEARD_SRC_DIR="/storage/downloads/SickBeard"

# The directory to put the video files for sickbeard to pick them up and move them
#export SICKBEARD_PICKUP_DIR="/storage/downloads/.sickbeard_pickup"

# The path to the sickbeard python script to execute that will notify sickbeard that there are things to move
#export SICKBEARD_AUTOSCRIPT="/var/www/sickbeard/autoProcessTV/sabToSickBeard.py"

# The directory where couchpotato video files are put by rtorrent after they finish downloading
#export COUCHPOTATO_SRC_DIR="/storage/downloads/CouchPotato"

# The directory to put the video files for couchpotato to pick them up and move them
# export COUCHPOTATO_DEST_DIR="/storage/downloads/.couchpotato_pickup"