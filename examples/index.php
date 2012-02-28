<?php 
//set_time_limit ( 300 );
error_reporting(E_ALL);
session_start(); 
require_once 'feature_detection.php'; 
?>
<!DOCTYPE html>
<html>
	<head>
		<title>HAAR Feature Detection with PHP GD</title>
	    <link rel="stylesheet" type="text/css" href="css.css" />
	</head>
	<body>
		<h1>HAAR Face Detection with PHP GD</h1>
		<?php if (isset($error)) echo "<p id='error'>".$error."</p>"; ?>
		<?php //if (!isset($_SESSION['newPath']) || isset($_GET['new'])) { ?>
		<form method='POST' action='index.php' id='imgForm' enctype='multipart/form-data'>
			<label for='img_upload'>Image File: </label>
			<input type='file' name='img_upload' id='img_upload'>
			<label for='img_name'>Image Name: </label>
			<input type='text' name='img_name' id='img_name'>
			<input type='submit' value="Upload and Detect" name='upload_form_submitted'>
		</form>
		<?php //} else { ?>
			<h2>Detected Features</h2>
			<img id='uploaded_image' src='<?php echo $_SESSION['newPath'].'?'.rand(0, 100000); ?>' />
		<?php //} ?>
	</body>
</html>