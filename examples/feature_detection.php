<?php
require_once('../cascades/haarcascade_frontalface_alt.php');
require_once('../src/haar-detector.class.php');

/* -----------------
| UPLOAD FORM - validate form and handle submission
----------------- */
$error='';
if (isset($_POST['upload_form_submitted'])) {
	if (!isset($_FILES['img_upload']) || empty($_FILES['img_upload']['name'])) {
		$error = "Error: You didn't upload a file";
	} else if (!isset($_POST['img_name']) || empty($_POST['img_name'])) {
		$error = "Error: You didn't specify a file name";
	} else {
		$allowedExtensions = array('jpg', 'jpeg', 'gif', 'png');
		preg_match('/\.('.implode($allowedExtensions, '|').')$/', $_FILES['img_upload']['name'], $fileExt);
		$newPath = 'imgs/'.$_POST['img_name'].'.'.$fileExt[1];
		/*if (file_exists($newPath)) {
			$error = "Error: A file with that name already exists";
		} else*/ if (!in_array(substr($fileExt[0], 1), $allowedExtensions)) {
			$error = 'Error: Invalid file format - please upload a picture file';
		} else if (!copy($_FILES['img_upload']['tmp_name'], $newPath)) {
			$error = 'Error: Could not save file to server';
		} else {
			$_SESSION['newPath'] = $newPath;
			$_SESSION['fileExt'] = $fileExt;
		}
	}
}


/* -----------------
| CROP saved image
----------------- */
if (isset($_POST['upload_form_submitted'])) {

	switch($_SESSION['fileExt'][1]) {
		case 'jpg': case 'jpeg':
			$source_img = imagecreatefromjpeg($_SESSION['newPath']);
			break;
		case 'gif':
			$source_img = imagecreatefromgif($_SESSION['newPath']);
			break;
		case 'png':
			$source_img = imagecreatefrompng($_SESSION['newPath']);
			break;
	}
	$facedetector=new HAARDetector($haarcascade_frontalface_alt);
	$facedetector->setImage($source_img,0.25);
	// cannyPruning set to false since it does not produce expected results (maybe fix in the future)
	$foundsth=$facedetector->detect(1, 1.25, 0.1, 1, false);
	if ($foundsth)
	{
		$found=$facedetector->objects[0]; // take first found feature
		$dest_img = imagecreatetruecolor($found['width'], $found['height']);
		imagecopy($dest_img, $source_img, 0, 0, $found['x'], $found['y'], $found['width'], $found['height']);
		switch($_SESSION['fileExt'][1]) {
			case 'jpg': case 'jpeg':
				imagejpeg($dest_img, $_SESSION['newPath']); break;
			case 'gif':
				imagegif($dest_img, $_SESSION['newPath']); break;
			case 'png':
				imagepng($dest_img, $_SESSION['newPath']); break;
		}
		header('Location: index.php');
	}
	else
	{
		$error .= '<br />Error: Nothing Found';
	}
}
?>