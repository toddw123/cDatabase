<?php

//Class to handle a CSV database file
//Databases are arrays serialized and saved as plain text

//


class cDatabase
{
	//dbFile is the name of the file containing the data
	private $dbFile;
	//dbLoaded is a boolean representing if the database has been loaded and is ready for other actions
	private $dbLoaded = false;
	//dbCrypt is boolean for encryption in the database or just raw text
	private $dbCrypt = true;

	//dbContent is the content of the database file in a string
	public $dbContent;
	//dbArray is the contents unserialized 
	public $dbArray;


	function __construct($db = NULL, $encrypt = true)
	{
		if(is_null($db)) //If $db is null, we dont need to do anything else in here. Will need to call setDatabase() function later
			return true;
		$this->dbCrypt = $encrypt;

		return $this->loadDatabase($db);
	}

	//Loads the content of the database file
	public function loadDatabase($db)
	{
		if(!file_exists($db))
			return $this->createDatabase($db);

		$this->dbFile = $db;

		if(($this->dbContent = file_get_contents($this->dbFile)) === FALSE)
		{
			throw new Exception("failed to read the contents of the datebase '{$db}'");
		}

		if($this->dbCrypt)
			$this->dbArray = unserialize(base64_decode($this->dbContent));
		else
			$this->dbArray = unserialize($this->dbContent);	

		$this->dbLoaded = true;

		return true;
	}

	//Create empty database
	public function createDatabase($db, $overwrite = false, $table = NULL, $value = NULL)
	{
		if(file_exists($db) && $overwrite == FALSE)
		{
			throw new Exception("there is already a databasse called '{$db}'");
		}

		if(($fp = fopen($db, "w+")) === FALSE)
		{
			throw new Exception("can not create database file. check the folder's permisions to make sure you can create a file");	
		}

		//Close the file so it saves. fopen/fclose are used only for that purpose. 
		fclose($fp);

		$this->dbFile = $db;

		if(!is_null($table))
		{
			unset($this->dbArray); //Lets clear $dbArray just to be safe
			$this->dbArray = array();

			$this->dbArray[$table] = (is_null($value)) ? array() : $value; //If $value is null, we add an empty array. Else we set it to $value
		}

		$this->dbLoaded = true; //We are now loaded and ready to perform other actions
	}


	//uploadToFTP("ftp.hipdash.org", "webinaradmin@hipdash.org", "P3ngu!ns", "/join-tomeeting/registration/WebinarDB.txt");
	public function uploadToFTP($server, $user, $pass, $path)
	{
		//FTP upload info
    	$conn_id = ftp_connect ($server);
    	if(!$conn_id)
    		throw new Exception("failed to connect to server {$server}"); 
  
    	$login_result = ftp_login($conn_id, $user, $pass); 
    	if((!$conn_id) || (!$login_result))
    		throw new Exception("login failed for FTP server");

    	if(is_array($path))
    	{
    		foreach($path as $p)
    		{
    			ftp_put($conn_id, $p . $this->dbFile, $this->dbFile, FTP_ASCII);
	    	}
	    	return true;
    	}
    	else
    	{
	    	if(ftp_put($conn_id, $path . $this->dbFile, $this->dbFile, FTP_ASCII))
	    	{
	    		ftp_close($conn_id);
	    		return true;
	    	}
	    	else
	    	{
	    		ftp_close($conn_id);
	    		throw new Exception("failed to upload the databse to '{$path}'");
	    		return false;
	    	}
	    }

	}

	//Saves the database
	public function saveDatabase($db = NULL)
	{
		//If the database hasnt been loaded yet, function will fail
		if(!$this->dbLoaded)
		{
			throw new Exception("no database loaded");
		}

		$saveAs = "";
		
		//If $db is set, then it will save the database as whatever $db is set to, else it will just save it to the original file
		if(!is_null($db))
			$saveAs = $db;
		else
			$saveAs = $this->dbFile;

		if($this->dbCrypt)
			$data = base64_encode(serialize($this->dbArray));
		else
			$data = serialize($this->dbArray);
		
		file_put_contents($saveAs, $data);

		return true;
	}

	//creates a new table in the database
	public function createTable($key, $value = array())
	{
		//If the database hasnt been loaded yet, function will fail
		if(!$this->dbLoaded)
		{
			throw new Exception("no database loaded");
		}

		//Check to see if row already exists in the database
		if(array_key_exists($key, $this->dbArray))
		{
			throw new Exception("this database already has a row by the name '{$key}");	
		}

		$this->dbArray[$key] = $value;

		return true;
	}

	//returns the table requested
	private function &getTable($tr)
	{
		//If the database hasnt been loaded yet, function will fail
		if(!$this->dbLoaded)
		{
			throw new Exception("no database loaded");
		}

		//If the row doesnt exist
		if(isset($this->dbArray[$tr]) === FALSE)
		{
			throw new Exception("can not locate table row '{$tr}'");	
		}

		return $this->dbArray[$tr];
	}

	public function getTableArray($table)
	{
		//If the database hasnt been loaded yet, function will fail
		if(!$this->dbLoaded)
		{
			throw new Exception("no database loaded");
		}

		//If the row doesnt exist
		if(isset($this->dbArray[$table]) === FALSE)
		{
			throw new Exception("can not locate table row '{$tr}'");	
		}

		return $this->dbArray[$table];	
	}

	//updates the database with $array, auto-overwrite duplicates 
	public function updateFromArray($table, $array, $overwrite = true)
	{
		//If the database hasnt been loaded yet, function will fail
		if(!$this->dbLoaded)
		{
			throw new Exception("no database loaded");
		}

		$t = $this->getTable($table);
		foreach($array as $a)
		{
			$key = $a[0]; 
			//That entry already exists
			if(array_key_exists($key, $this->getTable($table)))
			{
				if($overwrite) //Overwrite the entry with the new data
					$t[$key] = $a;
			}
			else
			{
				$t[$key] = $a;
			}
		}
	}

	//function to sort the column by date
	private function dateSort($a, $b)
	{
    	if ($a[0] == $b[0]) {
        	return 0;
    	}
    	return ($a[0] < $b[0]) ? -1 : 1;
	}
	
	//call the sorting function
	public function sortTable($table)
	{
		//If the database hasnt been loaded yet, function will fail
		if(!$this->dbLoaded)
		{
			throw new Exception("no database loaded");
		}

		return usort($this->getTable($table), array($this, "dateSort"));
	}
}

?>