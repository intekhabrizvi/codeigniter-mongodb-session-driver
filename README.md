# Codeigniter MongoDB Session Driver
##If you want to use MongoDB collection to hold your Codeigniter based session data then this driver is for you.

By default codeigniter comes with inbuild support for 4 different type of session storage those are files, database (mySQL and Postgres), Redis, memcached.

To store session data in MongoDB collection, you can use this driver.

###Prerequisite
1. Make sure you have PECL Mongo driver installed and enabled in PHP ini file. Please check https://pecl.php.net/package/mongo

###Installation
1. Download the repo
2. put session_mongo.php file into YOUR_PROJECT_FOLDER/application/config/ folder
3. update login details inside session_mongo.php file.
4. put Session_mongo_driver.php inside folder YOUR_PROJECT_FOLDER/application/libraries/Session/drivers folder, if you do not have folder then do create. (Pay attention to folder name, its case sensitive)

###Usage
Simply use Codeiginiter's standard functions to access session data, driver will take care the rest.
