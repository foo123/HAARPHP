<?php 
error_reporting(E_ALL);
require dirname(__FILE__).'/feature_detection.php'; 
?>
<!DOCTYPE html>
<html>
	<head>
		<title>HAAR Feature Detection with PHP GD</title>
	    <link rel="stylesheet" type="text/css" href="css.css" />
	</head>
	<body>
		<h1>HAAR Face Detection with PHP GD</h1>
		<?php if ($error) echo "<p id='error'>$error</p>"; ?>
		<form method='POST' id='imgForm' enctype='multipart/form-data'>
			<label for='img_upload'>Image File: </label>
			<input type='file' name='img_upload' id='img_upload'>
			<label for='img_name'>Image Name: </label>
			<input type='text' name='img_name' id='img_name'>
			<input type='submit' value="Upload and Detect" name='upload_form_submitted'>
		</form>
		<?php if ($newImage) { ?>
			<h2>Detected Features</h2>
			<img id='uploaded_image' src='<?php echo $newImage.'?'.rand(0, 100000); ?>' />
		<?php } ?>
	</body>
</html>