<?php require dirname(__FILE__).'/feature_detection.php'; ?>
<!DOCTYPE html>
<html>

<head>
    <title>HAAR Feature Detection with PHP GD</title>
    <link rel="stylesheet" type="text/css" href="css.css" />
</head>

<body>

    <style>#forkongithub a{background:#aa0000;color:#fff;text-decoration:none;font-family:arial, sans-serif;text-align:center;font-weight:bold;padding:5px 40px;font-size:0.9rem;line-height:1.4rem;position:relative;transition:0.5s;}#forkongithub a:hover{background:#aa0000;color:#fff;}#forkongithub a::before,#forkongithub a::after{content:"";width:100%;display:block;position:absolute;z-index:100;top:1px;left:0;height:1px;background:#fff;}#forkongithub a::after{bottom:1px;top:auto;}@media screen and (min-width:800px){#forkongithub{position:absolute;display:block;z-index:100;top:0;right:0;width:200px;overflow:hidden;height:200px;}#forkongithub a{width:200px;position:absolute;top:60px;right:-60px;transform:rotate(45deg);-webkit-transform:rotate(45deg);box-shadow:4px 4px 10px rgba(0,0,0,0.8);}}</style><span id="forkongithub"><a href="https://github.com/foo123/HAARPHP">Cook me on GitHub</a></span>


    <h1>HAAR Face Detection with PHP GD</h1>

    <?php if ($error) echo "<p id='error'>$error</p>"; ?>

    <form method='POST' id='imgForm' enctype='multipart/form-data'>
        <label for='img_upload'>Image File: </label>
        <input type='file' name='img_upload' id='img_upload'>
        <input type='submit' value="Upload and Detect" name='upload_form_submitted'>
    </form>

    <h2>Original Image</h2>
    <?php echo $origImageHtml; ?>

    <h2>Detected Features ( <?php echo $numFeatures; ?> )</h2>
    <ul style="list-style-type:none">
    <?php foreach ($detectedImagesHtml as $img) { ?>
        <li><?php echo $img; ?></li>
    <?php } ?>
    </ul>

</body>

</html>