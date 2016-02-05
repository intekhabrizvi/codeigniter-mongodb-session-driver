<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$config['session_mongo_location'] = 'localhost';
$config['session_mongo_port'] = '27017';
$config['session_mongo_db'] = 'test';
$config['session_mongo_user'] = 'test';
$config['session_mongo_password'] = 'testpassword';
$config['session_mongo_collection'] = 'mongo_session';
$config['session_mongo_write_concerns'] = (int)1;
$config['session_mongo_write_journal'] = true;