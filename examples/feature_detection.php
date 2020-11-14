<?php

error_reporting(E_ALL);
set_time_limit(0);

define('ABSPATH', dirname(dirname(__FILE__)));

global $error, $origImageHtml, $numFeatures, $detectedImagesHtml, $haarcascade_frontalface_alt;

require(ABSPATH.'/cascades/haarcascade_frontalface_alt.php');
require(ABSPATH.'/src/HaarDetector.php');


/* ------------------------------------------------
| UPLOAD FORM - validate form and handle submission
-------------------------------------------------- */
$error = false;
$origImageHtml = '';
$detectedImagesHtml = array();
$numFeatures = 0;
$uploadedImage = false;

if (isset($_POST['upload_form_submitted']))
{
    if (! isset($_FILES['img_upload']) || empty($_FILES['img_upload']['name']))
    {
        $error = "<strong>Error:</strong> You didn't upload a file";
    }
    else
    {
        $allowedExtensions = array('jpg', 'jpeg', 'gif', 'png');
        preg_match('/\\.(' . implode('|', $allowedExtensions) . ')$/i', $_FILES['img_upload']['name'], $fileExt);
        if (! in_array(strtolower(substr($fileExt[0], 1)), $allowedExtensions))
        {
            $error = '<strong>Error:</strong> Invalid file format - please upload a picture (.jpg, .jpeg, .gif, .png) file';
        }
        $uploadedImage = $_FILES['img_upload']['tmp_name'];
    }
}


if ($uploadedImage && !$error)
{

    // read image
    switch(strtolower($fileExt[1]))
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
    $faceDetector = new HaarDetector($haarcascade_frontalface_alt);
    // cannyPruning sometimes depends on the image scaling, small image scaling seems to make canny pruning fail (if doCannyPruning is true)
    // optionally different canny thresholds can be set to overcome this limitation
    $found = $faceDetector
                /* normalise image to some standard dimensions eg. 150 px width so that detection parameters below remain relatively standard as well */
                ->image($origImage, 150 / imagesx($origImage))
                ->cannyThreshold(array('low'=>80, 'high'=>200))
                ->detect(1, 1.1 /*1.25*/, 0.12 /*0.2*/, 1, 0.2, false)
            ;

    // if detected
    if ($found)
    {
        $numFeatures = count($faceDetector->objects);
        // create feature images from original image
        $detectedImages = array_map(function($feature) use($origImage) {
            $detectedImage = imagecreatetruecolor($feature->width, $feature->height);
            imagecopy($detectedImage, $origImage, 0, 0, $feature->x, $feature->y, $feature->width, $feature->height);
            return $detectedImage;
        }, $faceDetector->objects);

        // display images
        switch(strtolower($fileExt[1]))
        {
            case 'jpg': case 'jpeg':

                ob_start();
                imagejpeg($origImage);
                $origImageHtml='<img src="data:image/jpeg;base64,' . base64_encode(ob_get_clean()) . '" />';

                $detectedImagesHtml = array_map(function($detectedImage){
                    ob_start();
                    imagejpeg($detectedImage);
                    return '<img src="data:image/jpeg;base64,' . base64_encode(ob_get_clean()) . '" />';
                }, $detectedImages);
                break;

            case 'gif':

                ob_start();
                imagegif($origImage);
                $origImageHtml='<img src="data:image/gif;base64,' . base64_encode(ob_get_clean()) . '" />';

                $detectedImagesHtml = array_map(function($detectedImage){
                    ob_start();
                    imagegif($detectedImage);
                    return '<img src="data:image/gif;base64,' . base64_encode(ob_get_clean()) . '" />';
                }, $detectedImages);
                break;

            case 'png':

                ob_start();
                imagepng($origImage);
                $origImageHtml='<img src="data:image/png;base64,' . base64_encode(ob_get_clean()) . '" />';

                $detectedImagesHtml = array_map(function($detectedImage){
                    ob_start();
                    imagepng($detectedImage);
                    return '<img src="data:image/png;base64,' . base64_encode(ob_get_clean()) . '" />';
                }, $detectedImages);
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
                $origImageHtml = '<img src="data:image/jpeg;base64,' . base64_encode(ob_get_clean()) . '" />';
                break;

            case 'gif':

                ob_start();
                imagegif($origImage);
                $origImageHtml = '<img src="data:image/gif;base64,' . base64_encode(ob_get_clean()) . '" />';
                break;

            case 'png':

                ob_start();
                imagepng($origImage);
                $origImageHtml = '<img src="data:image/png;base64,' . base64_encode(ob_get_clean()) . '" />';
                break;
        }
    }
}
