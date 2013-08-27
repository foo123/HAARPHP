<?php require dirname(__FILE__).'/feature_detection.php'; ?>
<!DOCTYPE html>
<html>
	<head>
		<title>HAAR Feature Detection with PHP GD</title>
	    <link rel="stylesheet" type="text/css" href="css.css" />
	</head>
	<body>
        <a href="https://github.com/foo123/HAARPHP">
        <img style="position: absolute; top: 0; right: 0; border: 0;" src="https://s3.amazonaws.com/github/ribbons/forkme_right_red_aa0000.png" alt="Fork me on GitHub">
        </a>
        
		<h1>HAAR Face Detection with PHP GD</h1>
		<?php if ($error) echo "<p id='error'>$error</p>"; ?>
		<form method='POST' id='imgForm' enctype='multipart/form-data'>
			<label for='img_upload'>Image File: </label>
			<input type='file' name='img_upload' id='img_upload'>
			<input type='submit' value="Upload and Detect" name='upload_form_submitted'>
		</form>
        <h2>Original Image</h2>
        <?php echo $origImageHtml; ?>
        <h2>Detected Features</h2>
        <?php echo $detectedImageHtml; ?>
	</body>
</html>