# Joomla-CB-LDAP-sync
 Cronjob Script to sync LDAP with Joomla and Community Builder (CB).
 
 The script is based on https://samjlevy.com/mydap-v4/ which is no longer online.
 
 # what does it do?
 
 - Create account if it does not exist
 - Update joomla and CB fields
 - Disable accounts that are disabled in LDAP
 - Check if there is an avatar photo in the

# Setup

- line 138 LDAP login
- line 151, Path to ldap users OU
- line 156, LDAP Fields to retrieve
- Line 161 Joomla database connection
- Line 309 complete path to  images\comprofiler\ folder
- line 358 OU path to additional disabled users which should also be disbaled in joomla.

For inserting customfields you can look at
line 194 to 215 how to retrieve them and update the SQL update code at line 322 accordingly.

# why on github
Purpose is to help others and improving the script with others. 
 
