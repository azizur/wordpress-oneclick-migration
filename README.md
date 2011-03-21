WordPress OneClick Migration
=============
This script will update site information on your new domain.


Pre-preparation
-------
Before you can make use of this script you need to have done.

* export/mysql dump file from you source site

* import your mysql dump file on your target server
* complete your wp-config.php file on your target server


Script usage
-------
 1. Upload this script to you WordPress root directory (where wp-config.php file is located)
 2. Browse to http://your-newly-migrated.com/migrate.php
 3. Follow the on screen instructions


History
-------
1.1
* Migration Confirmation message when completed
* Ability to self destruct if server has permission to do so else show error message
* Auto redirect to new site if we detect migration is already complete

1.0
* Initial Release
