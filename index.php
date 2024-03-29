<?php
    include_once("classes/cls.pluginapi.php");
    
	
    
    
    class plugin_medimage_export
    {
        public $verbose = false;
     	
     	private function trim_trailing_slash_local($str) {
        	return rtrim($str, "/");
    	}
    
    	private function add_trailing_slash_local($str) {
        	//Remove and then add
        	return rtrim($str, "/") . '/';
   		}  
     
     
     
        
        private function get_medimage_config() {
				 if(!isset($medimage_config)) {
					  //Get global plugin config - but only once
					  $data = file_get_contents (dirname(__FILE__) . "/config/config.json");
					  if($data) {
						   $medimage_config = json_decode($data, true);
						   if(!isset($medimage_config)) {
						       $msg = "Error: MedImage config/config.json is not valid JSON.";
						       error_log($msg);
						       echo $msg;
						       exit(0);
						   }
					  } else {
					  		$msg = "Error: MedImage config/config.json in medimage_export plugin.";
					  		error_log($msg);
						   echo $msg;
						   exit(0);
					  }
				 }
       		
        		return $medimage_config;
        }
        
        
        

        
        public function get_current_id($api, $message_forum_id)
        {
        	//This query will find the latest on this forum. May need some refinements e.g. around user id
        	//With var_shouted example value:
        	//MedImage: Switched MedImage patient to ID: 'nhi123 arm'
        	
        	$sql = "SELECT * from tbl_ssshout where int_layer_id = " . $message_forum_id . " AND var_shouted like 'MedImage: Switched%' order by int_ssshout_id desc limit 1";
			$result = $api->db_select($sql);
			
						
			$row = $api->db_fetch_array($result);
			if($row) {
				$message = $row['var_shouted'];
				$between = explode("'", $message);
				if($between[1]) {
					return $between[1];
				
				}
			}
			
        	return false;
        
        }
        
        
        
        public function check_switched_on($initiate)
        {
        	if($this->verbose == true) error_log("Check switched on:" . $_COOKIE['medimage-switched-on'] . "  initiate:" . $initiate);
        	if(isset($_COOKIE['medimage-switched-on'])) {
        		if($_COOKIE['medimage-switched-on'] == "true") {
        			return true;
        		} else {
        			return false;
        		}
        	} else {
        		//Not even registered yet. Set the cookie to the default status in the config file
        		$medimage_config = $this->get_medimage_config();
        		if($this->verbose == true) error_log("Check switched on:" . $_COOKIE['medimage-switched-on'] . "  initiate:" . $initiate);
        		if(isset($medimage_config['startSwitchedOn'])) {
        			if($medimage_config['startSwitchedOn'] == true) {
        				if($initiate == true) {
        					setcookie("medimage-switched-on", "true");
        				}   
        				return true;	
        			} else {
        				if($initiate == true) {
        					setcookie("medimage-switched-on", "false");
        				}  
        				return false;
        			}
        		
        		} else {
        			//No start switched on option. Assume not switched on on starting.
        			if($initiate == true) {
        				setcookie("medimage-switched-on", "false");
        			}
        			return false;
        		}
        	}
        }
        
        
        public function check_and_start_medimage($uc_message, $actual_message, $sender_id, 
        					$sender_name_str, $new_message, $recipient_ip_colon_id, $sender_email, $sender_ip, $message_forum_id, $options, $api) 
        {
        	if((strpos($uc_message, "START MEDIMAGE") === 0)||
				(strpos($uc_message, "ENABLE MEDIMAGE") === 0)||
				(strpos($uc_message, "MEDIMAGE ON") === 0)) {
				
				
				 $message_1 = "You have started the MedImage service in this browser. Uploaded photos will be sent to your desktop MedImage software, once you pair up. To switch off the service enter 'stop medimage'";
				 $message_2 = "You should now pair with your MedImage desktop. Click one of the large pairing buttons on the MedImage desktop, and then type 'pair [your 4 digit code]' into this app, with the 4 digit code that MedImage gives you. http://medimage.co.nz/how-to/#pair";
				
				 if($this->check_switched_on(false) == true) {
				 	//We were already switched on.
				 	$message_1 = "The MedImage service is already enabled in this browser. Uploaded photos will be sent to your desktop MedImage software. To switch off the service enter 'stop medimage'";
				 	if(isset($_COOKIE['medimage-server'])) {
				 		//We already have a MedImage Server pairing
				 		$message_2 = "You may now want to change your MedImage patient ID, by entering e.g. 'id NHI1234 arm'.";
				 	} else {
				 		//We need to pair up still. Keep the same message 2
				 	}
				 }
				
				  //Check for messages starting like 'start medimage', which will enable the service on this browser
				  $id = substr($actual_message[1], 3);
				  $id = str_replace("\\r","", $id);
				  $id = str_replace("\\n","", $id);
				  $id = preg_replace('/\s+/', ' ', trim($id));
		  
				  setcookie("medimage-switched-on", "true");
						  
				  $new_message = $message_1;
				  $recipient_ip_colon_id =  "123.123.123.123:" . $sender_id;		//Send privately to the original sender
				  $sender_name_str = "MedImage";
				  $sender_email = "info@medimage.co.nz";
				  $sender_ip = "111.111.111.111";
				  $options = array('notification' => false, 'allow_plugins' => false);
				  $api->new_message($sender_name_str, $new_message, $recipient_ip_colon_id, $sender_email, $sender_ip, $message_forum_id, $options);
			
				$new_message = $message_2;
				  $api->new_message($sender_name_str, $new_message, $recipient_ip_colon_id, $sender_email, $sender_ip, $message_forum_id, $options);
			} else {
				//Not a start message
				return false;		
			}
			
			return true;
        
        }
        
       
 		public function on_message($message_forum_id, $message, $message_id, $sender_id, $recipient_id, $sender_name, $sender_email, $sender_phone)
        {
            global $cnf;
            $api = new cls_plugin_api();
            
            if($this->check_switched_on(true) == false) {
            	//We're not switched on - so check if we are being switched on
            	
            	$actual_message = explode(": ", $message);			//Remove name of sender
            	
				if($actual_message[1]) {
					$uc_message = strtoupper($actual_message[1]);
					
					return $this->check_and_start_medimage($uc_message, $actual_message, $sender_id, 
        					$sender_name_str, $new_message, $recipient_ip_colon_id, $sender_email, $sender_ip, $message_forum_id, $options, $api);
        			//We exit this process, either way - if we were off, and 'start' was entered we are now on, but awaiting new commands. If we were off and 'start' was not entered, we don't want to process anything below.
				} else {
					//No message, so not a start message
					return true;		//Early out of here, if we aren't switch on.
				}
            }
            
            
            //Check for existence of photo in message and initiate a sending process for that photo
            //Check if we don't have a paired MedImage Server stored, and warn user with a message
            //Check for a pairing with the MedImage Server i.e 'pair aBc1' or 'pr aBc1'
            //TODO: generalise languages here
 
			$url_matching = "atomjump";		//Works with based jpgs on atomjump which include e.g. 'atomjump' in their strings.
			if($cnf['uploads']['replaceHiResURLMatch']) $url_matching = $cnf['uploads']['replaceHiResURLMatch'];			
			$preg_search = "/.*?" . $url_matching ."(.*?)\.jpg/i";
			if($this->verbose == true) error_log($preg_search);
			if($this->verbose == true) error_log($message);
			preg_match_all($preg_search, $message, $matches);
			if($this->verbose == true) error_log(json_encode($matches));
			
			
			if(count($matches[0]) > 0) {
					//Yes we have at least one image
					
					//Check if we have a pairing
					if(isset($_COOKIE['medimage-server'])) {
					
						//Check if we already have an ID, and if not send a message to say we have sent the image as 'image', but you 
						//should set the id with 'id [patientId] [optional description tags]'
						$id_text = $this->get_current_id($api, $message_forum_id);
						if(!$id_text) {
							$id_text = "image";
							$append_message = " Note: you can name your photo by entering e.g. 'id nhi1234 arm'";
						} else {
							$append_message = "";
						}
						
						$tags = str_replace(" ", "-", $id_text);
						
						if($this->verbose == true) error_log("Tags:" . $tags);
						
						$medimage_config = $this->get_medimage_config();
						
					
					
						for($cnt = 0; $cnt < count($matches[1]); $cnt++) {
							$between_slashes = explode( "/", $matches[1][$cnt]);
							$len = count($between_slashes) - 1;
							$image_name = $between_slashes[$len] . ".jpg";
							$image_hi_name = $between_slashes[$len] . "_HI.jpg";
			
							$image_folder = $this->add_trailing_slash_local($medimage_config['serverPath']) . "images/im/";
														
							$new_message = "Sending photo to the MedImage Server: '" . $id_text . "'" . $append_message;		
							$recipient_ip_colon_id =  "123.123.123.123:" . $sender_id;		//Send privately to the original sender
							$sender_name_str = "MedImage";
							$sender_email = "info@medimage.co.nz";
							$sender_ip = "111.111.111.111";
							$options = array('notification' => false, 'allow_plugins' => false);
							$api->new_message($sender_name_str, $new_message, $recipient_ip_colon_id, $sender_email, $sender_ip, $message_forum_id, $options);
					
							
							//Now start a parallel process, that waits until the photo has been sent, before sending a confirmation message.       
						
					
							//Get the layer name, if available. Used to ensure we have selected the correct database in our process child.
							$layer_name = "";
							if(isset($_REQUEST['passcode'])) {
								$layer_name = $_REQUEST['passcode'];			
							}

							if(isset($_REQUEST['uniqueFeedbackId'])) {
								$layer_name = $_REQUEST['uniqueFeedbackId'];
							}
					
							$command = $medimage_config['phpPath'] . " " . dirname(__FILE__) . "/upload.php " . $image_folder . " " . $image_hi_name . " " . $message_id . " " . $message_forum_id . " " . $layer_name . " " . $_COOKIE['medimage-server'] . " " . $tags . " " . $sender_id;
							global $staging;
							if($staging == true) {
								$command = $command . " staging";   //Ensure this works on a staging server  
							}
							if($this->verbose == true) error_log("Running: " . $command);
							
							$api->parallel_system_call($command, "linux");
							
											
						
						}
						
					} else {
						//Sorry, no medimage server detected. Give the option via a return private message, and syntax for setting the MedImage Server, with 'pair aBc1' or 'pr aBc1'
						 $new_message = "You have uploaded a photo to the group, but you haven't paired with your MedImage desktop yet. Click one of the large pairing buttons on the MedImage desktop, and then type 'pair [your 4 digit code]' into this app, with the 4 digit code that MedImage gives you. http://medimage.co.nz/how-to/#pair";
						 $recipient_ip_colon_id = "123.123.123.123:" . $sender_id;		//Private to the sender of the original message
						 $sender_name_str = "MedImage";
						 $sender_email = "info@medimage.co.nz";
						 $sender_ip = "111.111.111.111";
						 $options = array('notification' => false, 'allow_plugins' => false);
						 $api->new_message($sender_name_str, $new_message, $recipient_ip_colon_id, $sender_email, $sender_ip, $message_forum_id, $options);
					}
			}
 
 
            $actual_message = explode(": ", $message);			//Remove name of sender         
            if($actual_message[1]) {
            	$uc_message = strtoupper($actual_message[1]);
            	if($this->verbose == true) error_log($uc_message);
		         
		        $this->check_and_start_medimage($uc_message, $actual_message, $sender_id, 
        					$sender_name_str, $new_message, $recipient_ip_colon_id, $sender_email, $sender_ip, $message_forum_id, $options, $api); 
		         
		         	
		        if(strpos($uc_message, "ID ") === 0) {
				      //Check for messages starting with 'id [patientid] [keywords]', which switch the id to send this to on the
				      //backend MedImage Server
				      $id = substr($actual_message[1], 3);
				      $id = str_replace("\\r","", $id);
				      $id = str_replace("\\n","", $id);
				      $id = preg_replace('/\s+/', ' ', trim($id));
				      
				    			      
				      $new_message = "Switched MedImage patient to ID: '" . $id . "'";
				      $recipient_ip_colon_id = "";		//No recipient, so the whole group. 123.123.123.123:" . $recipient_id;
				      $sender_name_str = "MedImage";
				      $sender_email = "info@medimage.co.nz";
				      $sender_ip = "111.111.111.111";
				      $options = array('notification' => false, 'allow_plugins' => false);
				   	$api->new_message($sender_name_str, $new_message, $recipient_ip_colon_id, $sender_email, $sender_ip, $message_forum_id, $options);
				   }
				   
				
				
				
				if((strpos($uc_message, "STOP MEDIMAGE") === 0)||
					(strpos($uc_message, "DISABLE MEDIMAGE") === 0)||
					(strpos($uc_message, "MEDIMAGE OFF") === 0)) {
				      //Check for messages starting like 'start medimage', which will enable the service on this browser
				      $id = substr($actual_message[1], 3);
				      $id = str_replace("\\r","", $id);
				      $id = str_replace("\\n","", $id);
				      $id = preg_replace('/\s+/', ' ', trim($id));
				     
				      setcookie("medimage-switched-on", "false"); 
				    			      
				      $new_message = "You have stopped the MedImage service in this browser. Uploaded photos will no longer be sent to your desktop MedImage software. To switch this on again, enter 'start medimage'";
				      $recipient_ip_colon_id =  "123.123.123.123:" . $sender_id;		//Send privately to the original sender
				      $sender_name_str = "MedImage";
				      $sender_email = "info@medimage.co.nz";
				      $sender_ip = "111.111.111.111";
				      $options = array('notification' => false, 'allow_plugins' => false);
				   	$api->new_message($sender_name_str, $new_message, $recipient_ip_colon_id, $sender_email, $sender_ip, $message_forum_id, $options);
				}
				   
				   
				if((strpos($uc_message, "PAIR ") === 0)||
				   	(strpos($uc_message, "PR ") === 0)) {
				   	//A pairing request.
				   	//See: http://medimage.co.nz/building-an-alternative-client-to-medimage/
				   	//https://medimage-pair.atomjump.com/med-genid.php?compare=
				   	$ids = explode(" ", $actual_message[1]);
				   	$id = str_replace("\\r","", $ids[1]);
				    $id = str_replace("\\n","", $id);
				    $id = preg_replace('/\s+/', ' ', trim($id));
				   	$pairing_string = "https://medimage-pair.atomjump.com/med-genid.php?compare=" . $id;
				   	$paired = file_get_contents($pairing_string);		//TODO could implement POST here for security + timeouts
				   	
				if($paired && (trim($paired) !== "nomatch")) {
					  		$new_message = "You have successfully paired with your MedImage Server! To unpair, enter 'unpair'. Now enter a patient ID with e.g. 'id NHI1234 tags' before sending a photo.";
					  		setcookie("medimage-server", trim($paired));   	
					   
					   } else {
					   	$new_message = "Sorry, that was an invalid pairing code. Please try again.";
					   }
					   
				      $recipient_ip_colon_id = "123.123.123.123:" . $sender_id;		//Private to the sender of the original message
				      $sender_name_str = "MedImage";
				      $sender_email = "info@medimage.co.nz";
				      $sender_ip = "111.111.111.111";
				      $options = array('notification' => false, 'allow_plugins' => false);
				   	  $api->new_message($sender_name_str, $new_message, $recipient_ip_colon_id, $sender_email, $sender_ip, $message_forum_id, $options);			
				   		
				   		
				}
				   
				if(strpos($uc_message, "UNPAIR") === 0) {
				   	//An unpairing request.
					   $new_message = "You have successfully unpaired with your MedImage Server!";
				      $recipient_ip_colon_id = "123.123.123.123:" . $sender_id;		//Private to the sender of the original message
				      $sender_name_str = "MedImage";
				      $sender_email = "info@medimage.co.nz";
				      $sender_ip = "111.111.111.111";
				      $options = array('notification' => false, 'allow_plugins' => false);
				   	  $api->new_message($sender_name_str, $new_message, $recipient_ip_colon_id, $sender_email, $sender_ip, $message_forum_id, $options);					
				   	  setcookie("medimage-server", "", time()-3600);	
				}
				   
			}
            
            return true;
            
        }
		

        
        public function on_msg_buttons($message_id)
        {
        	global $root_server_url;
        	
            //Do your thing in here. Here is a sample.
            $api = new cls_plugin_api();
          
            if($this->check_switched_on(false) == false) return "";		//Early out of here, if we aren't switch on.

            
           $layer_name = "";
			if(isset($_REQUEST['passcode'])) {
				$layer_name = $_REQUEST['passcode'];			
			}

			if(isset($_REQUEST['uniqueFeedbackId'])) {
				$layer_name = $_REQUEST['uniqueFeedbackId'];
			} 
            
            $sender_id = $api->get_current_user_id();
            
           $ret_text = "<script>function medimageExport(msgId) { data = { msg_id: " . $message_id . ", layer_name: '" . $layer_name . "', sender_id: " . $sender_id . " };  jQuery.ajax({ url: \"" . $root_server_url . "/plugins/medimage_export/export.php\", data: data, type: 'POST', cache: false 		}).done(function(response) {  doSearch(); return closeSingleMsg();	}); }</script><a class=\"comment-msg-button\" href=\"javascript:\" onclick=\"medimageExport(" . $message_id . ");\"><img width=\"48\" src='" . $root_server_url . "/plugins/medimage_export/images/medimage_logo.png'></a>";  
            return $ret_text;
            
        }
        
        public function on_upload_screen()
        {
        	global $root_server_url;
        	
            //Do your thing in here. Here is a sample.
            $api = new cls_plugin_api();
          
            $layer_name = "";
			if(isset($_REQUEST['passcode'])) {
				$layer_name = $_REQUEST['passcode'];			
			}

			if(isset($_REQUEST['uniqueFeedbackId'])) {
				$layer_name = $_REQUEST['uniqueFeedbackId'];
			} 
			
			$sender_id = $api->get_current_user_id();
			if(!$sender_id) $sender_id = 0;		//A default null user if not logged in.
           
            ?>
            	<br/>
            	            
            <?php
           
           	//MedImage pdf export
            $ret_text = "<script>function medimagePDFExport(msgId) { jQuery('#pdf-waiting').show(); var userDate = new Date(); var strUserDate = userDate.toString(); data = { uniqueFeedbackId: '" . $layer_name . "', userDateTime: strUserDate, sender_id: " . $sender_id . ", send_medimage: true };  jQuery.ajax({ url: \"" . $root_server_url . "/plugins/medimage_export/pdf-export/export.php\", data: data, type: 'POST', cache: false 		}).done(function(response) { jQuery('#pdf-waiting').hide(); jQuery('#comment-popup-content').show(); jQuery('#comment-upload').hide(); doSearch();  }); }; ";
            
            //General pdf export
             $ret_text .= "function PDFExport(msgId) { var userDate = new Date(); var strUserDate = encodeURIComponent(userDate.toString()); var url=\"" . $root_server_url . "/plugins/medimage_export/pdf-export/export.php?uniqueFeedbackId=" . $layer_name . "&userDateTime=\" + strUserDate + \"&sender_id=" . $sender_id . "\"; window.open(url); }</script>";
             
            
            //Visible buttons
            $ret_text  .= "<a class=\"comment-msg-button\" href=\"javascript:\" onclick=\"return PDFExport();\"><img width=\"48\" src='" . $root_server_url . "/plugins/medimage_export/images/pdf128.png'></a><a class=\"comment-msg-button\" href=\"javascript:\" onclick=\"return medimagePDFExport();\"><img width=\"48\" src='" . $root_server_url . "/plugins/medimage_export/images/medimage_logo.png'></a><img id='pdf-waiting' src='images/ajax-loader.gif' width='16' height='16' style='display: none;'>";
            
            
            echo $ret_text;
            
            return true;
            
        }
        
        
    }
?>
