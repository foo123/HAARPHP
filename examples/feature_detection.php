<?php
error_reporting(E_ALL);

define('ABSPATH', dirname(dirname(__FILE__)));

global $error, $origImageHtml, $numFeatures, $detectedImageHtml, $haarcascade_frontalface_alt;

require(ABSPATH.'/cascades/haarcascade_frontalface_alt.php');
require(ABSPATH.'/src/haar-detector.class.php');


/* ------------------------------------------------
| UPLOAD FORM - validate form and handle submission
-------------------------------------------------- */
$error = false;
$origImageHtml = $detectedImageHtml = '';
$numFeatures = 0;
$uploadedImage = false;

if (isset($_POST['upload_form_submitted'])) 
{
    if (!isset($_FILES['img_upload']) || empty($_FILES['img_upload']['name'])) 
    {
        $error = "<strong>Error:</strong> You didn't upload a file";
    } 
    else 
    {
        $allowedExtensions = array('jpg', 'jpeg', 'gif', 'png');
        preg_match('/\.('.implode($allowedExtensions, '|').')$/', $_FILES['img_upload']['name'], $fileExt);
        if (!in_array(substr($fileExt[0], 1), $allowedExtensions)) 
        {
            $error = '<strong>Error:</strong> Invalid file format - please upload a picture file';
        } 
        $uploadedImage = $_FILES['img_upload']['tmp_name'];
    }
}


if ($uploadedImage && !$error) 
{

    // read image
    switch($fileExt[1]) 
    {
        case 'jpg': case 'jpeg':
            $origImage = imagecreatefromjpeg($uploadedImage);
            break;
        
        case 'gif':
            $origImage = imagecreatefromgif($uploadedImage);
            break;
        
        case 'png':
            $origImage = imagecreatefrompng($uploadedImage);
            break;
    }
    
    // detect face/feature
    $faceDetector = HAARDetector::getDetector($haarcascade_frontalface_alt);
    // cannyPruning sometimes depends on the image scaling, small image scaling seems to make canny pruning fail (if doCannyPruning is true)
    // optionally different canny thresholds can be set to overcome this limitation
    $found = $faceDetector
                ->image($origImage, 0.5)
                ->detect(1, 1.25, 0.5, 1, true)
            ;
    
    // if detected
    if ($found)
    {
        $numFeatures = count($faceDetector->objects);
        $feature = $faceDetector->objects[0]; // take first found (largest) feature
        // create feature image from original image
        $detectedImage = imagecreatetruecolor($feature->width, $feature->height);
        imagecopy($detectedImage, $origImage, 0, 0, $feature->x, $feature->y, $feature->width, $feature->height);
        
        // display images
        switch($fileExt[1]) 
        {
            case 'jpg': case 'jpeg':
                
                ob_start();
                imagejpeg($origImage);
                $origImageHtml='<img src="data:image/jpeg;base64,' . base64_encode(ob_get_clean()) . '" />';
                
                ob_start();
                imagejpeg($detectedImage);
                $detectedImageHtml='<img src="data:image/jpeg;base64,' . base64_encode(ob_get_clean()) . '" />';
                break;
            
            case 'gif':
                
                ob_start();
                imagegif($origImage);
                $origImageHtml='<img src="data:image/gif;base64,' . base64_encode(ob_get_clean()) . '" />';
                
                ob_start();
                imagegif($detectedImage);
                $detectedImageHtml='<img src="data:image/gif;base64,' . base64_encode(ob_get_clean()) . '" />';
                break;
            
            case 'png':
                
                ob_start();
                imagepng($origImage);
                $origImageHtml='<img src="data:image/png;base64,' . base64_encode(ob_get_clean()) . '" />';
                
                ob_start();
                imagepng($detectedImage);
                $detectedImageHtml='<img src="data:image/png;base64,' . base64_encode(ob_get_clean()) . '" />';
                break;
        }
    }
    else
    {
        $error .= "<br /><strong>Nothing Found!</strong>";
        
        // display image
        switch($fileExt[1]) 
        {
            case 'jpg': case 'jpeg':
                
                ob_start();
                imagejpeg($origImage);
                $origImageHtml='<img src="data:image/jpeg;base64,' . base64_encode(ob_get_clean()) . '" />';
                break;
            
            case 'gif':
                
                ob_start();
                imagegif($origImage);
                $origImageHtml='<img src="data:image/gif;base64,' . base64_encode(ob_get_clean()) . '" />';
                break;
            
            case 'png':
                
                ob_start();
                imagepng($origImage);
                $origImageHtml='<img src="data:image/png;base64,' . base64_encode(ob_get_clean()) . '" />';
                break;
        }
    }
}
