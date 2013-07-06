<?php
global $error, $newImage, $haarcascade_frontalface_alt;

require(dirname(dirname(__FILE__)).'/cascades/haarcascade_frontalface_alt.php');
require(dirname(dirname(__FILE__)).'/src/haar-detector.class.php');


/* ------------------------------------------------
| UPLOAD FORM - validate form and handle submission
-------------------------------------------------- */
$error=false;
$newImage=false;

if (isset($_POST['upload_form_submitted'])) 
{
	if (!isset($_FILES['img_upload']) || empty($_FILES['img_upload']['name'])) 
    {
		$error = "<strong>Error:</strong> You didn't upload a file";
	} 
    elseif (!isset($_POST['img_name']) || empty($_POST['img_name'])) 
    {
		$error = "<strong>Error:</strong> You didn't specify a file name";
	} 
    else 
    {
		$allowedExtensions = array('jpg', 'jpeg', 'gif', 'png');
		preg_match('/\.('.implode($allowedExtensions, '|').')$/', $_FILES['img_upload']['name'], $fileExt);
		$newPath = dirname(__FILE__).'/imgs/'.$_POST['img_name'].'.'.$fileExt[1];
        if (!in_array(substr($fileExt[0], 1), $allowedExtensions)) 
        {
			$error = '<strong>Error:</strong> Invalid file format - please upload a picture file';
		} 
		/*elseif (file_exists($newPath)) 
        {
			$error = "Error: A file with that name already exists";
		}*/ 
        elseif (!copy($_FILES['img_upload']['tmp_name'], $newPath)) 
        {
			$error = '<strong>Error:</strong> Could not save file to server';
		} 
	}
}


/* -----------------
| CROP saved image
----------------- */
if (isset($_POST['upload_form_submitted']) && !$error) 
{

	switch($fileExt[1]) 
    {
		case 'jpg': 
        case 'jpeg':
			$source_img = imagecreatefromjpeg($newPath);
			break;
		case 'gif':
			$source_img = imagecreatefromgif($newPath);
			break;
		case 'png':
			$source_img = imagecreatefrompng($newPath);
			break;
	}
    
	$facedetector=HAARDetector::getDetector($haarcascade_frontalface_alt);
    $foundsth=$facedetector
            // cannyPruning sometimes depends on the image scaling, small image scaling seems to make canny pruning fail (if canny is true)
            ->setImage($source_img, 0.25)
            ->detect(1, 1.25, 0.1, 1, false);
	
    if ($foundsth)
	{
		$found=$facedetector->objects[0]; // take first found feature
		$dest_img = imagecreatetruecolor($found['width'], $found['height']);
		imagecopy($dest_img, $source_img, 0, 0, $found['x'], $found['y'], $found['width'], $found['height']);
		switch($fileExt[1]) 
        {
			case 'jpg': case 'jpeg':
				imagejpeg($dest_img, $newPath); break;
			case 'gif':
				imagegif($dest_img, $newPath); break;
			case 'png':
				imagepng($dest_img, $newPath); break;
		}
        $newImage='imgs/'.$_POST['img_name'].'.'.$fileExt[1];
	}
	else
	{
		$error .= "<br /><strong>Nothing Found!</strong>";
	}
}
