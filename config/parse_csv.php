<?php

/*

This script is v1.1 for an automatic update of end user entitlements for ingestion via kaltura's bulk upload API.
It requires the API client library as well as an updated config.ini in the root directory

Dependencies

- The directory, as well as each file here, must have read and write access
- Kaltura PHP Client Library 
- config.ini file to configure library to partner ID

Corner Cases

1) this looks line by line through the csv to find the differences in the csv's and create a new csv from the differences. So, if you have a csv that is identical in content but re-ordered, then it will log them as "different" since line by line they are different even if they are really the same. We can try to add a sort function but becuase we don't know if the array will be strings or numbers, it might be hard to sort

Fail Cases

none ( yet :) )

*/

$csv = array(); //new csv
$csv2 = array(); //old csv
$file = fopen('entitlements.csv', 'r'); //open new csv
$file2 = fopen('entitlements2.csv', 'r'); //open old csv
$newheading = ""; 
$i=0;

//Get associative array of newly uploaded csv
while (($result = fgetcsv($file)) !== false)
{
    $csv[] = $result;
}


//Get associative array of old uploaded csv for comparison
while (($result2 = fgetcsv($file2)) !== false)
{
    $csv2[] = $result2;
}

//write a function to find differences between old and new and place into new array 
function findDiff($a, $b) {
    
    $new_csv = []; //create empty array
    $new_csv[0] = $a[0]; //set array header to new csv's header
    $counta = count($a); //length of new array
    $countb = count($b); //length of old array
    $i = 1; //set counter var to 1 to avoid comparing the headers since we already set it on line 45
    
    //check if the new csv has more entries than the older one
    if ($counta < $countb) {
    
        while ($i < $counta){

          $res = array_diff($a[$i], $b[$i]);

            if(empty($res)){

                $i++; //if empty, means there is no difference in old/new arrays so move to next line

            }

            else {

                $new_csv[] = $a[$i]; //store the updated/new data in new array
                $i++;
            }
        }
    }
    
    //if new csv DOES have more, compare $a to $b until $b is empty then add all remaining lines from $a
    else {
        
        while ($i < $counta ){
            
            if($i < $countb){    

              $res = array_diff($a[$i], $b[$i]);

                if(empty($res)){

                    $i++; //if empty, means there is no difference in old/new arrays so move to next line

                }

                else {

                    $new_csv[] = $a[$i]; //store the updated/new data in new array
                    $i++;
                }
            }
            else {
                
                $new_csv[] = $a[$i];
                $i++;
            }
        }
    }
        
    return $new_csv; //return the array of differences 
}

//call our function
$csvn = findDiff($csv, $csv2); 

fclose($file2);//close out of date entitlements
unlink('entitlements2.csv'); //delete out of date entitlements
fclose($file);//save new csv
rename('entitlements.csv', 'entitlements2.csv'); //rename the file so the script will run again next time

//check if any changes were made, if not, don't update changes.csv
if(count($csvn) > 1){
    
    //check if changes file exists - if so, delete it and recreatea a new one that
    if(file_exists('changes.csv')){

        unlink("changes.csv","r");
        sleep(2);
        $changes = fopen("changes.csv","w");
        //currently being written as "read-only"
    }
    
    //if file does not exist, write one
    else {

        $changes = fopen("changes.csv","w");
        //currently being written as read-only
    }

    //iterate over all options in associative array
    foreach ($csvn as $row) {
        
       fputcsv($changes, $row);
        
    }

    fclose($changes); //close file to and write to server

}

//begin connection to Kaltura Client
// ===================================================================================================
//                           _  __     _ _
//                          | |/ /__ _| | |_ _  _ _ _ __ _
//                          | ' </ _` | |  _| || | '_/ _` |
//                          |_|\_\__,_|_|\__|\_,_|_| \__,_|
//
// This file is part of the Kaltura Collaborative Media Suite which allows users
// to do with audio, video, and animation what Wiki platfroms allow them to do with
// text.
//
// Copyright (C) 2006-2011  Kaltura Inc.
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU Affero General Public License as
// published by the Free Software Foundation, either version 3 of the
// License, or (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU Affero General Public License for more details.
//
// You should have received a copy of the GNU Affero General Public License
// along with this program.  If not, see <http://www.gnu.org/licenses/>.
//
// @ignore
// ===================================================================================================

require_once(dirname(__FILE__) . '/../KalturaClient.php');

class TestMain implements IKalturaLogger
{
	const CONFIG_FILE = 'config.ini';
	const CONFIG_TEMPLATE_FILE = 'config.template.ini';
	
	const CONFIG_ITEM_PARTNER_ID = 'partnerId';
	const CONFIG_ITEM_ADMIN_SECRET = 'adminSecret';
	const CONFIG_ITEM_SERVICE_URL = 'serviceUrl';
	const CONFIG_ITEM_UPLOAD_FILE = 'uploadFile';
	const CONFIG_ITEM_TIMEZONE = 'timezone';
	
	/**
	 * @var array
	 */
	private $config;
	
	public function log($message)
	{
	}

	public static function run()
	{   
		$test = new TestMain();
		$test->loadConfig();
		$test->listActions();
	}
	
	private function loadConfig()
	{
		$filename = dirname(__FILE__) . DIRECTORY_SEPARATOR . self::CONFIG_FILE;
		if(!file_exists($filename)){
			$template = dirname(__FILE__) . DIRECTORY_SEPARATOR . self::CONFIG_TEMPLATE_FILE;
			throw new Exception("Configuration file [$filename] not found, Use template file [$template].");
		}
		
		$this->config = parse_ini_file($filename);
		
		date_default_timezone_set($this->config[self::CONFIG_ITEM_TIMEZONE]);
	}
	
	private function getKalturaClient($partnerId, $adminSecret, $isAdmin)
	{
		$kConfig = new KalturaConfiguration();
		$kConfig->serviceUrl = $this->config[self::CONFIG_ITEM_SERVICE_URL];
		$kConfig->setLogger($this);
		$client = new KalturaClient($kConfig);
		
		$userId = "SomeUser";
		$sessionType = ($isAdmin)? KalturaSessionType::ADMIN : KalturaSessionType::USER; 
		try
		{
			$ks = $client->generateSession($adminSecret, $userId, $sessionType, $partnerId);
			$client->setKs($ks);
		}
		catch(Exception $ex)
		{
			throw new Exception("Could not start session - check configurations in config.ini");
		}
		
		return $client;
	}
	
	public function listActions()
	{
        
		$client = $this->getKalturaClient($this->config[self::CONFIG_ITEM_PARTNER_ID], $this->config[self::CONFIG_ITEM_ADMIN_SECRET], true);
    
        //UPDATE CSV CONTENT
        
        $fileData = 'changes.csv';
        $bulkUploadData = new KalturaBulkUploadJobData();
        $bulkUploadData->fileName = "UpdatedFile";
        $bulkUploadCategoryUserData = new KalturaBulkUploadCategoryUserData();
        $result = $client->categoryuser->addFromBulkUpload($fileData, $bulkUploadData, $bulkUploadCategoryUserData);

	}
}

TestMain::run();

?>