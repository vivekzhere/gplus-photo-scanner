<?php
	/*
	 * Copyright 2011 Google Inc.
	 *
	 * Licensed under the Apache License, Version 2.0 (the "License");
	 * you may not use this file except in compliance with the License.
	 * You may obtain a copy of the License at
	 *
	 *     http://www.apache.org/licenses/LICENSE-2.0
	 *
	 * Unless required by applicable law or agreed to in writing, software
	 * distributed under the License is distributed on an "AS IS" BASIS,
	 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
	 * See the License for the specific language governing permissions and
	 * limitations under the License.
	 */
	if (ini_get('register_globals') === "1") {
		die("register_globals must be turned off before using the starter application");
	}

	require_once 'google-api-php-client/src/apiClient.php';
	require_once 'google-api-php-client/src/contrib/apiPlusService.php';

	session_start();

	$client = new apiClient();
	$client->setApplicationName("Google+ PHP Starter Application");
	$client->setClientId('583952610464-skm0ipo4hms1qleut4jinp106fnf0daf.apps.googleusercontent.com');
	$client->setClientSecret('FijubFKkMgXvH-UQV4SfEypV');
	$client->setRedirectUri('http://localhost/googleplus/index.php');
	$client->setDeveloperKey('AIzaSyDidZKyQlgizNL3lgPRHDyucL5sOxqGqXg');
	$client->setScopes(array('https://www.googleapis.com/auth/plus.me', 'https://picasaweb.google.com/data/'));
	$plus = new apiPlusService($client);

	if (isset($_REQUEST['logout'])) {
		unset($_SESSION['access_token']);
	}

	if (isset($_GET['code'])) {
		$client->authenticate();
		$_SESSION['access_token'] = $client->getAccessToken();
		$redirect = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'];
		header('Location: ' . filter_var($redirect, FILTER_SANITIZE_URL));
	}

	if (isset($_SESSION['access_token'])) {
		$client->setAccessToken($_SESSION['access_token']);
	}

	if ($client->getAccessToken()) 
	{
		$me = $plus->people->get('me');
		$url = filter_var($me['url'], FILTER_VALIDATE_URL);
		$img = filter_var($me['image']['url'], FILTER_VALIDATE_URL);
		$name = filter_var($me['displayName'], FILTER_SANITIZE_SPECIAL_CHARS);
		$personMarkup = "<a rel='me' href='$url'>$name</a><div><img src='$img'></div>";
		$optParams = array('maxResults' => 100);
		$activities = $plus->activities->listActivities('109152392103989095686', 'public', $optParams);
		$activityMarkup = '';


		$users = array();
		$handle = @fopen("friends.txt", "r");
		if ($handle)
		{
			while (($buffer = fgets($handle, 4096)) !== false) 
			{
				array_push($users, trim($buffer));
			}
			if (!feof($handle)) 
			{
				echo "Error: unexpected fgets() fail\n";
		 	}
			fclose($handle);
		}


		//Getting albums and displaying the photos
		foreach($users as $user_id)
		{

			
			//$user_id="default";
			$oToken = json_decode($client->getAccessToken());
			$cAccessToken = $oToken->access_token; 


			// Sending CURL request to get list of Albums for user
			$album_feed = 'https://picasaweb.google.com/data/feed/api/user/'.$user_id.'/';
			$header = array( "Host: picasaweb.google.com","Gdata-version: 2", "Content-length: 0", "Authorization: OAuth ".$cAccessToken );
			$ch = curl_init($album_feed);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
			$data = curl_exec($ch);
			curl_close($ch);

			// Downloading images in all Google Plus Albums
			$albums  = simplexml_load_string($data);	
			
			
			foreach ($albums->entry as $album) :
				// get the ID of the current album	
				$album_type =  $album->children('http://schemas.google.com/photos/2007')->albumType;
				// get photos for this album if its google plus
				if($album_type == "Buzz")
				{
					$album_id = $album->children('http://schemas.google.com/photos/2007')->id;	
					echo "Album ID : ".$album_id." <br/>";
					$photo_feed = 'https://picasaweb.google.com/data/feed/api/user/'.$user_id.'/albumid/'.$album_id.'?imgmax=d';
					$pch = curl_init($photo_feed);
					curl_setopt($pch, CURLOPT_RETURNTRANSFER, true);
					curl_setopt($pch, CURLOPT_HTTPHEADER, $header);
					$photo_data = curl_exec($pch);
					curl_close($pch);
					$photos  = simplexml_load_string($photo_data);
					foreach ($photos->entry as $photo) :
						//print $photo->children('http://schemas.google.com/photos/2007');
						$media = $photo->children('http://search.yahoo.com/mrss/');
						$mediagroup =  $media->group;
						$loc =  $mediagroup->content->attributes()->{'url'} ;
						echo "<img src='".$mediagroup->thumbnail[0]->attributes()->{'url'}."' /><br/><br/>";
						$filename =  basename($loc);
						file_put_contents("images/".$filename, file_get_contents($loc));
					endforeach;
				}
			endforeach;
			// The access token may have been updated lazily.
			$_SESSION['access_token'] = $client->getAccessToken();
		}
	} 
	else 
	{
		$authUrl = $client->createAuthUrl();
	}

?>

<!doctype html>
<html>
<head><link rel='stylesheet' href='style.css' /></head>
<body>
<header><h1>Google+ PhotoScanner</h1></header>
<div class="box">

<?php if(isset($personMarkup)): ?>
<div class="me"><?php print $personMarkup ?></div>
<?php endif ?>

<?php if(isset($activityMarkup)): ?>
<div class="activities"> <?php

?>
</div>
<?php endif ?>
<?php
if(isset($authUrl)) {
	print "<a class='login' href='$authUrl'>Connect Me!</a>";
} else {
	print "<a class='logout' href='?logout'>Logout</a>";
}
?>
</div>
</body>
</html>