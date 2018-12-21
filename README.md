# MongoDB 3 based high performance, non-blocking session driver for Codeigniter 3.1.5 or higher

### This is high-performance, non-blocking (at-least not on PHP level), fast session driver build to support Codeigniter 3.1.5 or higher.

#### Features
1. Concurrency : Locking is managed by MongoDB's internal WiredTiger storage engine hence no need of PHP level locking. [more read](https://docs.mongodb.com/manual/faq/concurrency/)
2. PHP Garbage Collector : instead of leting PHP GC to clean expired sessions, MongoDB's TTL based collection do this job to clean expired session hence less responsibility on PHP making code execution faster. [more read](https://docs.mongodb.com/manual/tutorial/expire-data/)

#### Prerequisite
1. Make sure you have PECL MongoDB driver installed and enabled in PHP ini file. Please check https://pecl.php.net/package/mongodb

#### Installation
1. Download the repo
2. put Session_mongo_driver.php inside folder `YOUR_PROJECT_FOLDER/application/libraries/Session/drivers` folder, if you didn't have those folders then please do create. (Pay attention to folder name, its case sensitive)
3. Setting MongoDB login details in `YOUR_PROJECT_FOLDER/application/config.php` file, find variable name `$config['sess_save_path']` and set its value exactly like below (don't forgot to change the value with correct one)
```
$config['sess_save_path'] = 'mongodb://USER_ID:PASSWORD@HOST:PORT/DATABASE_NAME|DATABASE_NAME|COLLECTION_NAME';
```
e.g
```
$config['sess_save_path'] = 'mongodb://session_user:123456@localhost:27017/my_app|my_app|sessions_collection';
```
4. Set TTL based indexed in your MongoDB's Collection. Default value is 1 hour, you can set whatever you want in below query
```
db.YOUR_COLELCTION_NAME.createIndex( { "timestamp": 1 }, { expireAfterSeconds: 3600 } )
```

#### Usage
Codeiginiter's standard functions `$this->session->` to access session data, driver will take care the rest.

#### License 
Creative Commons Attribution 3.0 License.
Codes are provided AS IS basis, i am not responsible for anything.
