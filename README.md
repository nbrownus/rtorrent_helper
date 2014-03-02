About
=====

This is a simple script that helps get downloaded torrents into the right place for couchpotato and sickbeard

Basically what happens is when a torrent completes in rtorrent it executes `rename.sh` with the torrent hash. The
files in the torrent are then hard linked to a destination specific to the torrents label. Services like CouchPotato or
SickBeard will then move those hard links and rename them. This allows you to still seed the torrent while having
your files named the way you want them to all while avoiding issues with processing the same thing multiple times.

TODO
====

- Document the rtorrent execution setup
- Link to couch potato and sickbeard
- Add more info around the rtorrent labels, autotool in rutorrent, etc