<?php
// force reply forces all to reply !!!!
// how to reply a message ?
// save in Telegram URLs
// using MPEG4-gif insteed of mp4
// دلیل کار نکردن زبان فارسی در قسمت جستجو
// ارسال اینلاین مود به صورت ویرایش متن پیام

define('API_KEY', '1497141769:AAGIWCHGlLAzxFqNdY9Ch-WF0YBcBgRZBGY');
$bot_url =      "https://api.telegram.org/bot" . API_KEY;
$bot_dl_url =   "https://api.telegram.org/file/bot" . API_KEY;
$gif_saving_path = "https://botbasige.cptele.ir/mohamad_sharifi/former-php/gif";
# DB variables :
$DB_driver = "mysql";
$DB_name = "botbasig_sharifi";
$DB_username = "botbasig_mhmed";
$DB_password = "sc0Z&FVLB}8e";
$DNS = "$DB_driver:host=localhost;dbname=$DB_name;charset=utf8mb4";


$update = file_get_contents("php://input");
$update_array = json_decode($update);
    
$please_send_us_gifname = "لطفا نام پیشنهادی برای گیف را برای ما بفرستید:";
$please_send_us_the_gif = "لطفا فایل gif مد نظر رو برای ما بفرستید :";
$mg_file_recieved = "فایل gif مد نظر شما با موفقیت بارگذاری شد";

if( isset($update_array->message) ) {
    $user_id = $update_array->message->from->id;
    $chat_id = $update_array->message->chat->id;
    $type2 = $update_array->message->chat->type; // != update
    $textmessage = isset($update_array->message->text) ? $update_array->message->text : "";
    // issue the step
    $connection = connect_to_db();
    try {
        $result     = $connection -> prepare("SELECT * FROM users WHERE userID = :user_id");
        $result -> bindParam(":user_id", $user_id);
        // $user_id    = $chat_id;
        $result ->execute();
        
        // the reasult of the fetch is an array
		$row        = $result -> fetch(); 
        $status       = $row['step'];
    } catch (Exception $e) { // SQLException 
        echo 'Caught exception: ',  $e->getMessage(), "\n";                 
    }

    $text_replied = isset($update_array->message->reply_to_message) ? 
                    $update_array->message->reply_to_message->text : "";
    if (isset ($update_array->message->animation)) {
        $file_id        = $update_array->message->animation->file_id;
        $mime_type	      = $update_array->message->animation->mime_type;
    }
    
	// reply_to_message 
	if ($textmessage == "/start") {
		
		$post_params    = [ 'chat_id' =>  $chat_id , 'text' => 'سلام! چه کاری از دستم بر میاد؟ :)'];
		send_reply("sendMessage" , $post_params);
		initial_user();
	}
	
	if ($textmessage == '/newGIF' && $status < 30 && ($type2 == "supergroup" || $type2 == "group") ) { 
		// starter of the function 
		get_gifs_details($status);
	} else if ($textmessage == '/newGIF') {
	    $post_params    = [ 'chat_id' =>  $chat_id , 'text' => 'قابلیت ذخیره گیف فقط در داخل گروه ها امکان پذیره'];
	    send_reply("sendMessage" , $post_params);
	}
	else if ($textmessage != "/start") {
		// continuer
		step_manager($status);
	}
} else if (isset($update_array->inline_query)) {
    
    $user_id          = $update_array->inline_query->from->id;
    $inline_query     = $update_array->inline_query->query;
    $inline_query_id  = $update_array->inline_query->id;
    
	$post_params = request_gif( $inline_query_id , $inline_query , $user_id);
	send_reply('answerInlineQuery', $post_params);
}

// continue
// we impliment this function just for get_gifs_details() but
// we'll have no problem on adding more similar functions in the future
function step_manager ($step) { 
	if ($step < 60)
		$dummy = 12;
    else if ($step < 70) // 60 - 69
        get_gifs_details($step);
}

function initial_user() {
	// zero step
    // save chat id for everyuser
	$connection = connect_to_db();
	$user_id = $GLOBALS['user_id']; 
	try {
		$result = $connection -> prepare("INSERT INTO users (userID) VALUES (:user_id)");  
		$result -> bindParam(":user_id", $user_id); // is our primary key column
		$result -> execute();
	} catch (Exception $e) { 
		echo 'Caught exception: ',  $e->getMessage(), "\n";               
	}
	$result = $connection -> prepare("UPDATE users SET step = 0 WHERE userID = :user_id"); 
	$result -> bindParam(":user_id" , $user_id);
	$result -> execute();
}


function get_gifs_details($step) {
	$user_id   = $GLOBALS['user_id'];
    $chat_id   = $GLOBALS['chat_id'];
	if ( isset($GLOBALS['textmessage'])) 
	    $text      = $GLOBALS['textmessage'];
	if ($step != 0 && $GLOBALS['text_replied'] != null ) { 
		$text_replied   = $GLOBALS['text_replied'];
		$DB_id      = strtok ($text_replied , "\n");
	}

    // if we're at the first step, we'll specify the $step for our own agenda
	if ($step == 0) {
		$step = 60;
	}

	$connection = connect_to_db();
	$force_reply = ['force_reply' => true];

	switch ($step) {
		case 60 :
			$reply = $GLOBALS['please_send_us_gifname'];
			$step = 61; 
			break;
		case 61 :  // $recieve_the_gif_name  
			$result = $connection -> prepare("INSERT INTO gif_table (gif_name , chat_id) VALUES (:text , :chat_id)");
			$result -> bindParam(':text', $text);
			$result -> bindParam(':chat_id', $chat_id);
			$result -> execute();
			
			$reply = $connection ->lastInsertId()  . "\n" . $GLOBALS['please_send_us_the_gif'];
			$step = 62;
			break;
		case 62 :  // $recive_the_file
			if ($GLOBALS['mime_type'] == "video/mp4") { 
				save_gif($GLOBALS['file_id'] , $DB_id , $connection);
				$reply = $GLOBALS['mg_file_recieved'];
				$force_reply = ['force_reply' => false];
				$step = 0;
			}
			else {
				$warning = "لطفا یک فایل gif بفرستید 😤";
				$post_params = [ 'chat_id' =>  $chat_id , 'text' => $warning];
				send_reply("sendMessage", $post_params);
				$reply = $DB_id . "\n" . $GLOBALS['please_send_us_the_gif'];
			}
			break;			
	}
    $json_fr        = json_encode($force_reply); 
    $post_params    = [ 'chat_id' =>  $chat_id , 'text' => $reply, 'reply_markup' => $json_fr];
    
    send_reply("sendMessage", $post_params);


	// update step in DB
	try {
		$result = $connection -> prepare("UPDATE users SET step = :step WHERE userID = :user_id");
		$result -> bindParam(':step', $step);
		$result -> bindParam(':user_id', $user_id);
		$result -> execute();
	} catch (Exception $e) { // SQLException 
		echo 'Caught exception: ',  $e->getMessage(), "\n";                 
	}
}

function save_gif($file_id , $DB_id , $connection) {
	$post_params = ['file_id' => $file_id];
	$file_instance = send_reply('getFile' , $post_params);
	$file_instance_array = json_decode($file_instance , true);
	
	$gif_path = $file_instance_array["result"]["file_path"];
	
	$url = $GLOBALS['bot_dl_url'] . "/$gif_path";
	
	$file_data = file_get_contents($url);
	
	$gif_path = "gif/$DB_id.mp4";
	$new_file = fopen($gif_path , 'w');
	fwrite($new_file , $file_data);
	fclose($new_file);
	
	$gif_url = $GLOBALS['gif_saving_path'] . "/$DB_id.mp4";
	
	$result = $connection -> prepare("UPDATE gif_table SET url = :gif_path WHERE id = :DB_id"); 
	$result -> bindParam(":gif_path" , $gif_url);
	$result -> bindParam(":DB_id" , $DB_id);
	$result -> execute();
}


function request_gif($inline_query_id , $inline_query , $user_id) {
    // chetori user ha ro ba ham ghati nemikone?
    // va /start robat haye mokhtalef ro?
    $gif_name = $inline_query;
	
    $result = search_gif ($user_id , $gif_name);
    $result_json = json_encode($result);
    
    $post_params = ["inline_query_id" => $inline_query_id , 'results' => $result_json ];
    return $post_params;
}

function search_gif ($user_id , $gif_name) {
    $connection = connect_to_db();
    
	$result = $connection -> prepare ("SELECT * FROM gif_table WHERE  gif_name = :gif_name"); 
//different between chatId and fromId -> chat_id = :chat_id AND
// if we send a msg and then change it, it'll use user_id then.
// 	$result -> bindParam(":user_id" , $user_id);
	$result -> bindParam(":gif_name" , $gif_name);
	$result -> execute();
    
    $result_array = build_result_array ( $result);
    return $result_array;
}

function build_result_array ( $result ) { 
    $result_array = array();   
    $row = $result -> fetch();
    if (isset($row['id'])) {
        do {
            $result_array[] = 
                [
                    'type'        => "video" ,
                    'id'          => $row['id']  ,        
                    'video_url'   => $row['url'] ,
                    'mime_type'   => "video/mp4" ,
                    'thumb_url'   => $GLOBALS['gif_saving_path'] . "/image2.jpg" ,
                    'title'       => $row['gif_name']  ,
                    'description' => "" ,
                ];
        } while ($row = $result -> fetch());
    }
    
    return $result_array;
}


function send_reply($method, $post_params) {
    $url = $GLOBALS['bot_url'] . "/" . $method;

    $cu = curl_init();
    curl_setopt($cu, CURLOPT_URL, $url);
    curl_setopt($cu, CURLOPT_POSTFIELDS, $post_params);
    curl_setopt($cu, CURLOPT_RETURNTRANSFER, true);  // gets the result
    $result = curl_exec($cu);

    curl_close($cu);
    return $result;
}

function connect_to_db() {
    $DB_username    = $GLOBALS['DB_username'];
    $DB_password    = $GLOBALS['DB_password'];
    $DNS            = $GLOBALS['DNS']        ;
    
	$options = [
                    // از این به بعد همیشه باید ترای کچ بزنم
					PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
					PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
					PDO::ATTR_EMULATE_PREPARES   => false,
				];
	$connection = new PDO(	$DNS, $DB_username, $DB_password , $options);
	$connection -> query("SET NAMES utf8mb4"); // and not utf8mb4
	return $connection;
}


?>