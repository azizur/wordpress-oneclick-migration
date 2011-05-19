WordPress OneClick Migration
=============
This script will update site information when moving WordPress sites from server/site to server/site.


Preparation
-------
Before you can make use of this script you need to have done.

* export/mysql dump file from you source site/server

* import your mysql dump file on your target site/server
* complete your wp-config.php file on your target site/server


Script usage
-------
 1. Upload this script to you WordPress root directory (where wp-config.php file is located)
 2. Browse to http://your-newly-migrated.com/migrate.php
 3. Follow the on screen instructions


Want to run the script again?
Browse to http://your-newly-migrated.com/migrate.php?forced=1


Release History
-------
1.4.1

* Don't force the forward slashes to be there, just replaced the url as given. props rtgibbons (Ryan Gibbons)

1.4

* fixes issue #2
* update user meta in case we have database prefix changes
* update user roles options in case we have database prefix changes


1.3

* Fixed missing parameters on a recursive call props perecedero (Ivan Lansky)


1.2

* Added GNU General Public License

* Added manual force - now you can append ?forced=1 to run the script again


1.1

* Migration Confirmation message when completed

* Ability to self destruct if server has permission to do so else show error message

* Auto redirect to new site if we detect migration is already complete


1.0

* Initial Release
