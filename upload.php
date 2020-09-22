<?php

  	function trim_trailing_slash_local($str) {
        return rtrim($str, "/");
    }
    
    function add_trailing_slash_local($str) {
        //Remove and then add
        return rtrim($str, "/") . '/';
    }
    
  if(!isset($medimage_config)) {
		  //Get global plugin config - but only once
		  $data = file_get_contents (dirname(__FILE__) . "/config/config.json");
		  if($data) {
			   $medimage_config = json_decode($data, true);
			   if(!isset($medimage_config)) {
			       echo "Error: MedImage config/config.json is not valid JSON.";
			       exit(0);
			   }
		  } else {
			   echo "Error: MedImage config/config.json in medimage_export plugin.";
			   exit(0);
		  }
	 }

	$start_path = add_trailing_slash_local($medimage_config['serverPath']);
	$notify = false;
	if(isset($argv[5])) { 		//This is the layer name
		//Set the global layer val, so that this is the correct database to add this message on
		$_REQUEST['passcode'] = $argv[5];
	}
	
	if(isset($argv[6])) {      //allow for a staging flag
	    $staging = true;
	}
	include_once($start_path . 'config/db_connect.php');	
	
    $define_classes_path = $start_path;     //This flag ensures we have access to the typical classes, before the cls.pluginapi.php is included
	require($start_path . "classes/cls.pluginapi.php");

    $api = new cls_plugin_api();

 
    sleep(2);		//TODO: simulated
    
    error_log("About to post to the group with success transfer.");
    
    //TODO: After a successful receipt event
	 $new_message = "Successfully sent the photo to the MedImage Server: 'image'";		//TODO: get the latest ID entered here
	 $recipient_ip_colon_id = "";		//No recipient, so the whole group. 
	 $sender_name_str = "MedImage";
	 $sender_email = "info@medimage.co.nz";
	 $sender_ip = "111.111.111.111";
	 $options = array('allow_plugins' => false);
	 $message_forum_id = $argv[4];
	 error_log("About to post to the group:" . $message_forum_id);
	 $api->new_message($sender_name_str, $new_message, $recipient_ip_colon_id, $sender_email, $sender_ip, $message_forum_id, $options);
    
       

?>