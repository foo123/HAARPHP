# HAARPHP 

__Feature Detection Library for PHP__

Based on [Viola-Jones Feature Detection Algorithm using Haar Cascades](http://www.cs.cmu.edu/~efros/courses/LBMV07/Papers/viola-cvpr-01.pdf)

This is a port of [OpenCV C++ Haar Detection](http://opencv.willowgarage.com/wiki/) (actually a port of [JViolaJones](http://code.google.com/p/jviolajones/) which is a port of OpenCV for Java) to PHP.

You can use the __existing openCV cascades__ to build your detectors.

To do this just transform the opencv xml file to PHP
using the haartophp (php or java) tool (in cascades folder)

example:
( to use opencv's haarcascades_frontalface_alt.xml  run following command)
```bash
haartophp haarcascades_frontalface_alt
```

this creates a php file: *haarcascades_frontalface_alt.php*
which you can include in your php application

the variable to use in php is similarly  
*$haarcascades_frontalface_alt*

###Where to find Haar Cascades xml files to use for feature detection

* [OpenCV](http://opencv.org/)
* [This resource](http://alereimondo.no-ip.org/OpenCV/34)
* search the web :)
* [Train your own](http://docs.opencv.org/doc/user_guide/ug_traincascade.html)

__Examples included with face detection__


*HAARPHP* is also part of PHP classes http://www.phpclasses.org/package/7393-PHP-Detect-features-on-images-such-as-faces-or-mouths.html

####ChangeLog:
__0.2__
* add haartophp tool in php (all-php solution)
* optimize array operations, refactor, etc..

__0.1__
* initial release

####TODO

####Issues/Notes
cannyPruning seems to fail depending on (a small) image scaling factor (if canny is true)


*Contributor* Nikos M.  
*URL* [Nikos Web Development](http://nikos-web-development.netai.net/ "Nikos Web Development")  
*URL* [Haar.php blog post](http://nikos-web-development.netai.net/blog/haarphp-feature-detection-with-haar-cascades-in-php/ "Haar.php blog post")  
*URL* [WorkingClassCode](http://workingclasscode.uphero.com/ "Working Class Code")  
