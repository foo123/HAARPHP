<?php
/**
*
* HAARPHP Feature Detection Library based on Viola-Jones Haar Detection algorithm
* port of jViolaJones  for Java (http://code.google.com/p/jviolajones/) to PHP
*
* https://github.com/foo123/HAARPHP
* @version: 0.4
*
*
**/

if (!class_exists('HAARDetector'))
{
//
//
//
// HAAR Detector Class with the haar cascade data
class HAARDetector
{
    CONST  VERSION="0.4";
    
    public $haardata = null;
    public $objects = null;
    public $Selection = null;
    
    protected $Canvas = null;
    protected $scaledSelection = null;
    protected $Ratio = 0.5;
    protected $width = 0;
    protected $height = 0;
    protected $origWidth = 0;
    protected $origHeight = 0;
    
    /*protected $scale = 0;
    protected $min_neighbors = 0;
    protected $scale_inc = 0;
    protected $increment = 0;
    protected $maxScale = 0;*/
    
    protected $cannyLow = 20;
    protected $cannyHigh = 100;
    //protected $doCannyPruning = true;
    protected $canny = null;
    
    protected $integral = null;
    protected $squares = null;
    protected $tilted = null;
    
    // static "factory" method
    public static function getDetector($haardata=null) 
    { 
        return new self( $haardata );  
    }
    
    // constructor
    public function __construct($haardata=null) 
    { 
        $this->haardata = $haardata; 
    }
    
    // clear the image and detector data
    // reload the image to re-compute the needed image data (.image method)
    // and re-set the detector haar data (.cascade method)
    public function clearCache() 
    {
        $this->haardata = null; 
        $this->canny = null;
        $this->integral = null;
        $this->squares = null;
        $this->tilted = null;
        return $this;
    }
    
    // set haardata for detector
    public function cascade($haardata) 
    { 
        $this->haardata = $haardata; 
        return $this;  
    }
    
    // set image for detector along with scaling
    public function image(&$image, $scale=0.5)
    {
        if ($image)
        {
            if ( null != $this->Canvas) 
            { 
                @imagedestroy( $this->Canvas );  
                $this->Canvas = null; 
            }
            $r = $this->Ratio = $scale;
            $w = $this->origWidth = imagesx($image);
            $h = $this->origHeight = imagesy($image);
            $sw = $this->width = round($r * $w);
            $sh = $this->height = round($r * $h);
            
            // copy image data
            $this->Canvas = imagecreatetruecolor($sw, $sh);
            imagecopyresampled($this->Canvas, $image, 0, 0, 0, 0, $sw, $sh, $w, $h);
            
            // compute image data now, once per image change
            $gray =& $this->integralImage($this->Canvas, $sw, $sh);
            $this->integralCanny($gray, $sw, $sh);
        }
        return $this;
    }
    
    // customize canny prunign thresholds for best results
    public function cannyThreshold($thres) 
    {
        if ($thres && is_array($thres))
        {
            if (isset($thres['low'])) $this->cannyLow = floatval($thres['low']);
            if (isset($thres['high'])) $this->cannyHigh = floatval($thres['high']);
        }
        return $this;
    }
    
    // set custom detection region as selection
    public function selection(/* ..variable args here.. */) 
    { 
        $args=func_get_args(); 
        $argslength=count($args);
        if ((1==$argslength && 'auto'==$args[0]) || 0==$argslength) 
        {
            $this->Selection = null;
        }
        else 
        { 
            $this->Selection = new HAARFeature(); 
            call_user_func_array(array($this->Selection, 'data'), $args); 
        }
        return $this; 
    }
    
    // Detector detect method to start detection
    // NOTE: currently cannyPruning does not give expected results (so use false in detect method) (maybe fix in the future)
    public function detect($baseScale=1.0, $scale_inc=1.25, $increment=0.5, $min_neighbors=1, $doCannyPruning=false)
    {
        if (!$this->Selection) $this->Selection = new HAARFeature(0, 0, $this->origWidth, $this->origHeight);
        
        $this->Selection->x = ('auto'==$this->Selection->x) ? 0 : $this->Selection->x;
        $this->Selection->y = ('auto'==$this->Selection->y) ? 0 : $this->Selection->y;
        $this->Selection->width = ('auto'==$this->Selection->width) ? $this->origWidth : $this->Selection->width;
        $this->Selection->height = ('auto'==$this->Selection->height) ? $this->origHeight : $this->Selection->height;
        
        $this->scaledSelection = $this->Selection->cloneit()->scale($this->Ratio)->round();
        
        $this->doCannyPruning = $doCannyPruning;
        
        $sizex = intval($this->haardata['size1']); 
        $sizey = intval($this->haardata['size2']);
        /*$this->min_neighbors = $min_neighbors; 
        $this->scale_inc = $scale_inc; 
        $this->increment = $increment; */
        $w = $this->scaledSelection->width; 
        $h = $this->scaledSelection->height; 
        $starti = $this->scaledSelection->x; 
        $startj = $this->scaledSelection->y;
        $cL = $this->cannyLow; 
        $cH = $this->cannyHigh;
        
        $maxScale = /*$this->maxScale =*/ min($this->width/$sizex, $this->height/$sizey); 
        $scale = /*$this->scale =*/ $baseScale; 
        $sl = count($this->haardata['stages']);

        $ret = array();   
        $gfound = false;
        
        // detect loop
        while ($scale<=$maxScale)
        {
            $ret = array_merge($ret, $this->detectSingleStep($scale, $sizex, $sizey, $increment, $doCannyPruning, $cL, $cH, $starti, $startj, $w, $h, $sl) );
            $scale *= $scale_inc;
        }
        
        // return results
        if (!empty($ret))
        {
            $this->objects = $this->merge($ret, $min_neighbors, $this->Ratio, $this->Selection);
            $gfound = true;
        }
        else
        {
            $this->objects=array();
            $gfound = false;
        }
        //$ret=null; unset($ret);
        
        return $gfound;  // returns true/false whether found at least sth
    }

    protected function detectSingleStep($scale, $sizex, $sizey, $increment, $doCanny, $cL, $cH, $startx, $starty, $w, $h, $sl)
    {
        $ret = array();
        $haar =& $this->haardata;
        //$scaledSelection = $this->scaledSelection;
        $imArea = $w*$h; $imArea1 = $imArea-1;
        
        $canny =& $this->canny; 
        $integral =& $this->integral; 
        $squares =& $this->squares; 
        $tilted =& $this->tilted;
        
        $bx1=0; $bx2=$w-1; $by1=0; $by2=$imArea-$w;
        
        $xsize = floor($scale * $sizex); 
        $xstep = floor($xsize * $increment); 
        $ysize = floor($scale * $sizey); 
        $ystep = floor($ysize * $increment);
        $tyw = $ysize*$w; 
        $tys = $ystep*$w; 
        $startty = $starty*$tys; 
        $xl = $w-$xsize; 
        $yl = $h-$ysize;
        $swh = $xsize*$ysize; 
        $inv_area = 1.0/$swh;
        
        for ($y=$starty, $ty=$startty; $y<$yl; $y+=$ystep, $ty+=$tys) 
        {
            for ($x=$startx; $x<$xl; $x+=$xstep) 
            {
                $p0 = $x-1 + $ty-$w;    $p1 = $p0 + $xsize;
                $p2 = $p0 + $tyw;    $p3 = $p2 + $xsize;
                
                // clamp
                $p0 = ($p0<0) ? 0 : (($p0>$imArea1) ? $imArea1 : $p0);
                $p1 = ($p1<0) ? 0 : (($p1>$imArea1) ? $imArea1 : $p1);
                $p2 = ($p2<0) ? 0 : (($p2>$imArea1) ? $imArea1 : $p2);
                $p3 = ($p3<0) ? 0 : (($p3>$imArea1) ? $imArea1 : $p3);
                
                if ($doCanny) 
                {
                    // avoid overflow
                    $edges_density = $inv_area * ($canny[$p3] - $canny[$p2] - $canny[$p1] + $canny[$p0]);
                    if ($edges_density < $cL || $edges_density > $cH) continue;
                }
                
                // pre-compute some values for speed
                
                // avoid overflow
                $total_x = $inv_area * ($integral[$p3] - $integral[$p2] - $integral[$p1] + $integral[$p0]);
                // avoid overflow
                $total_x2 = $inv_area * ($squares[$p3] - $squares[$p2] - $squares[$p1] + $squares[$p0]);
                
                $vnorm = $total_x2 - $total_x * $total_x;
                $vnorm = ($vnorm > 1) ? sqrt($vnorm) : /*$vnorm*/  1 ;  
                
                $pass = true;
                for ($s = 0; $s < $sl; $s++) 
                {
                    // Viola-Jones HAAR-Stage evaluator
                    $stage =& $haar['stages'][$s];
                    $threshold = $stage['thres'];
                    $trees =& $stage['trees']; $tl = count($trees);
                    $sum=0;
                    
                    for ($t = 0; $t < $tl; $t++) 
                    { 
                        //
                        // inline the tree and leaf evaluators to avoid function calls per-loop (faster)
                        //
                        
                        // Viola-Jones HAAR-Tree evaluator
                        $features =& $trees[$t]['feats']; 
                        $cur_node_ind = 0;
                        while (true) 
                        {
                            $feature =& $features[$cur_node_ind]; 
                            
                            // Viola-Jones HAAR-Leaf evaluator
                            $rects =& $feature['rects']; 
                            $nb_rects = count($rects); 
                            $thresholdf = $feature['thres']; 
                            $rect_sum = 0;
                            
                            if (isset($feature['tilt']) && $feature['tilt'])
                            {
                                // tilted rectangle feature, Lienhart et al. extension
                                for ($kr = 0; $kr < $nb_rects; $kr++) 
                                {
                                    $r = $rects[$kr];
                                    
                                    // this produces better/larger features, possible rounding effects??
                                    $x1 = $x + floor($scale * $r[0]);
                                    $y1 = ($y-1 + floor($scale * $r[1])) * $w;
                                    $x2 = $x + floor($scale * ($r[0] + $r[2]));
                                    $y2 = ($y-1 + floor($scale * ($r[1] + $r[2]))) * $w;
                                    $x3 = $x + floor($scale * ($r[0] - $r[3]));
                                    $y3 = ($y-1 + floor($scale * ($r[1] + $r[3]))) * $w;
                                    $x4 = $x + floor($scale * ($r[0] + $r[2] - $r[3]));
                                    $y4 = ($y-1 + floor($scale * ($r[1] + $r[2] + $r[3]))) * $w;
                                    
                                    // clamp
                                    $x1 = ($x1<$bx1) ? $bx1 : (($x1>$bx2) ? $bx2 : $x1);
                                    $x2 = ($x2<$bx1) ? $bx1 : (($x2>$bx2) ? $bx2 : $x2);
                                    $x3 = ($x3<$bx1) ? $bx1 : (($x3>$bx2) ? $bx2 : $x3);
                                    $x4 = ($x4<$bx1) ? $bx1 : (($x4>$bx2) ? $bx2 : $x4);
                                    $y1 = ($y1<$by1) ? $by1 : (($y1>$by2) ? $by2 : $y1);
                                    $y2 = ($y2<$by1) ? $by1 : (($y2>$by2) ? $by2 : $y2);
                                    $y3 = ($y3<$by1) ? $by1 : (($y3>$by2) ? $by2 : $y3);
                                    $y4 = ($y4<$by1) ? $by1 : (($y4>$by2) ? $by2 : $y4);
                                    
                                    // RSAT(x–h+w,y+w+h–1)+RSAT(x,y–1)–RSAT(x–h,y+h–1)–RSAT(x+w,y+w–1)
                                    //      x4     y4           x1  y1      x3   y3          x2  y2
                                    $rect_sum+= $r[4] * ($tilted[$x4 + $y4] - $tilted[$x3 + $y3] - $tilted[$x2 + $y2] + $tilted[$x1 + $y1]);
                                }
                            }
                            else
                            {
                                // orthogonal rectangle feature, Viola-Jones original
                                for ($kr = 0; $kr < $nb_rects; $kr++) 
                                {
                                    $r = $rects[$kr];
                                    
                                    // this produces better/larger features, possible rounding effects??
                                    $x1 = $x-1 + floor($scale * $r[0]); 
                                    $x2 = $x-1 + floor($scale * ($r[0] + $r[2]));
                                    $y1 = ($w) * ($y-1 + floor($scale * $r[1])); 
                                    $y2 = ($w) * ($y-1 + floor($scale * ($r[1] + $r[3])));
                                    
                                    // clamp
                                    $x1 = ($x1<$bx1) ? $bx1 : (($x1>$bx2) ? $bx2 : $x1);
                                    $x2 = ($x2<$bx1) ? $bx1 : (($x2>$bx2) ? $bx2 : $x2);
                                    $y1 = ($y1<$by1) ? $by1 : (($y1>$by2) ? $by2 : $y1);
                                    $y2 = ($y2<$by1) ? $by1 : (($y2>$by2) ? $by2 : $y2);
                                    
                                    // SAT(x–1,y–1)+SAT(x+w–1,y+h–1)–SAT(x–1,y+h–1)–SAT(x+w–1,y–1)
                                    //     x1  y1        x2    y2         x1  y2         x2   y1
                                    $rect_sum+= $r[4] * ($integral[$x2 + $y2]  - $integral[$x1 + $y2] - $integral[$x2 + $y1] + $integral[$x1 + $y1]);
                                }
                            }
                            
                            $where = ($rect_sum * $inv_area < $thresholdf * $vnorm) ? 0 : 1;
                            // END Viola-Jones HAAR-Leaf evaluator
                            
                            if ($where) 
                            {
                                if ($feature['has_r']) { $sum += $feature['r_val']; break; } 
                                else { $cur_node_ind = $feature['r_node']; }
                            } 
                            else 
                            {
                                if ($feature['has_l']) { $sum += $feature['l_val']; break; } 
                                else { $cur_node_ind = $feature['l_node']; }
                            }
                        }
                        // END Viola-Jones HAAR-Tree evaluator
                    
                    }
                    $pass = ($sum > $threshold) ? true : false;
                    // END Viola-Jones HAAR-Stage evaluator
                    
                    if (!$pass) break;
                }
                
                if ($pass) 
                {
                    array_push($ret, new HAARFeature($x, $y, $xsize, $ysize));
                }
            }
        }
        
        // return any features found in this step
        return $ret;
    }
    
    // auxilliary protected methods
    // compute gray-scale image, integral image and square image (Viola-Jones)
    protected function &integralImage(&$canvas, $w, $h) 
    {
        $count=$w*$h;
        $gray = array_fill(0, $count, 0); 
        $integral = array_fill(0, $count, 0); 
        $squares = array_fill(0, $count, 0); 
        $tilted = array_fill(0, $count, 0); 
        
        //$rgb=imagecolorat($canvas, $i, $j);
        //$red=($rgb >> 16) & 0xFF; $green=($rgb >> 8) & 0xFF; $blue=$rgb & 0xFF;
        
        // first row
        $i=0; $sum=$sum2=0; 
        while ($i<$w)
        {
            $rgb = imagecolorat($canvas, $i, 0);
            $r = ($rgb >> 16) & 255; 
            $g = ($rgb >> 8) & 255; 
            $b = $rgb & 255;
            // 0,29901123046875  0,58697509765625  0,114013671875 with roundoff
            $g = 0.299 * $r + 0.587 * $g + 0.114 * $b;
            
            $sum += $g;  
            $sum2 += $g*$g;
            
            // SAT(–1,y)=SAT(x,–1)=SAT(–1,–1)=0
            // SAT(x,y)=SAT(x,y–1)+SAT(x–1,y)+I(x,y)–SAT(x–1,y–1)  <-- integral image
            
            // RSAT(–1,y)=RSAT(x,–1)=RSAT(x,–2)=0 RSAT(–1,–1)=RSAT(–1,–2)=0
            // RSAT(x,y)=RSAT(x–1,y–1)+RSAT(x+1,y–1)–RSAT(x,y–2)+I(x,y)+I(x,y–1) <-- rotated(tilted) integral image at 45deg
            $gray[$i] = $g;
            $integral[$i] = $sum;
            $squares[$i] = $sum2;
            $tilted[$i] = $g;
            
            $i++;
        }
        // other rows
        $i=0; $j=1; $k=$w; $sum=$sum2=0; 
        while ($k<$count)
        {
            $rgb = imagecolorat($canvas, $i, $j);
            $r = ($rgb >> 16) & 255; 
            $g = ($rgb >> 8) & 255; 
            $b = $rgb & 255;
            // 0,29901123046875  0,58697509765625  0,114013671875 with roundoff
            $g = 0.299 * $r + 0.587 * $g + 0.114 * $b;
            
            $sum += $g;  
            $sum2 += $g*$g;
            
            // SAT(–1,y)=SAT(x,–1)=SAT(–1,–1)=0
            // SAT(x,y)=SAT(x,y–1)+SAT(x–1,y)+I(x,y)–SAT(x–1,y–1)  <-- integral image
            
            // RSAT(–1,y)=RSAT(x,–1)=RSAT(x,–2)=0 RSAT(–1,–1)=RSAT(–1,–2)=0
            // RSAT(x,y)=RSAT(x–1,y–1)+RSAT(x+1,y–1)–RSAT(x,y–2)+I(x,y)+I(x,y–1) <-- rotated(tilted) integral image at 45deg
            $gray[$k] = $g;
            $integral[$k] = $integral[$k-$w] + $sum;
            $squares[$k] = $squares[$k-$w] + $sum2;
            $tilted[$k] = $tilted[$k+1-$w] + ($g + $gray[$k-$w]) + (($j>1) ? $tilted[$k-$w-$w] : 0) + (($i>0) ? $tilted[$k-1-$w] : 0);
            
            $k++; $i++; if ($i>=$w) { $i=0; $j++; $sum=$sum2=0; }
        }
        $this->integral =& $integral;
        $this->squares =& $squares;
        $this->tilted =& $tilted;
        
        return $gray;
    }
    
    // compute Canny edges on gray-scale image to speed up detection if possible
    protected function integralCanny(&$gray, $w, $h) 
    {
        $count = $w*$h;
        $lowpass = array_fill(0, $count, 0);
        $canny = array_fill(0, $count, 0);
        $factor = 0.00628930817610062893081761006289;// 1/159;
        // gauss lowpass
        for ($i=2; $i<$w-2; $i++)
        {
            $sum=0;
            for ($j=2, $k=($w<<1); $j<$h-2; $j++, $k+=$w) 
            {
                // compute coords using simple add/subtract arithmetic (faster)
                $ind0 = $i+$k;
                $ind1 = $ind0+$w; 
                $ind2 = $ind1+$w; 
                $ind_1 = $ind0-$w; 
                $ind_2 = $ind_1-$w; 
                
                $sum = (0
                        + ($gray[$ind_2-2] << 1) + ($gray[$ind_1-2] << 2) + ($gray[$ind0-2] << 2) + ($gray[$ind0-2])
                        + ($gray[$ind1-2] << 2) + ($gray[$ind2-2] << 1) + ($gray[$ind_2-1] << 2) + ($gray[$ind_1-1] << 3)
                        + ($gray[$ind_1-1]) + ($gray[$ind0-1] << 4) - ($gray[$ind0-1] << 2) + ($gray[$ind1-1] << 3)
                        + ($gray[$ind1-1]) + ($gray[$ind2-1] << 2) + ($gray[$ind_2] << 2) + ($gray[$ind_2]) + ($gray[$ind_1] << 4)
                        - ($gray[$ind_1] << 2) + ($gray[$ind0] << 4) - ($gray[$ind0]) + ($gray[$ind1] << 4) - ($gray[$ind1] << 2)
                        + ($gray[$ind2] << 2) + ($gray[$ind2]) + ($gray[$ind_2+1] << 2) + ($gray[$ind_1+1] << 3) + ($gray[$ind_1+1])
                        + ($gray[$ind0+1] << 4) - ($gray[$ind0+1] << 2) + ($gray[$ind1+1] << 3) + ($gray[$ind1+1]) + ($gray[$ind2+1] << 2)
                        + ($gray[$ind_2+2] << 1) + ($gray[$ind_1+2] << 2) + ($gray[$ind0+2] << 2) + ($gray[$ind0+2])
                        + ($gray[$ind1+2] << 2) + ($gray[$ind2+2] << 1)
                        );
                
                $lowpass[$ind0] = $factor*$sum;
            }
        }
        
        // sobel gradient
        for ($i=1; $i<$w-1 ; $i++)
        {
            for ($j=1, $k=$w; $j<$h-1; $j++, $k+=$w) 
            {
                // compute coords using simple add/subtract arithmetic (faster)
                $ind0=$k+$i;
                $ind1=$ind0+$w; 
                $ind_1=$ind0-$w; 
                
                $grad_x = (0
                        - $lowpass[$ind_1-1] 
                        + $lowpass[$ind_1+1] 
                        - $lowpass[$ind0-1] - $lowpass[$ind0-1]
                        + $lowpass[$ind0+1] + $lowpass[$ind0+1]
                        - $lowpass[$ind1-1] 
                        + $lowpass[$ind1+1]
                        )
                        ;
                $grad_y = (0
                        + $lowpass[$ind_1-1] 
                        + $lowpass[$ind_1] + $lowpass[$ind_1]
                        + $lowpass[$ind_1+1] 
                        - $lowpass[$ind1-1] 
                        - $lowpass[$ind1] - $lowpass[$ind1]
                        - $lowpass[$ind1+1]
                        )
                        ;
                
                $canny[$ind0] = abs($grad_x) + abs($grad_y);
           }
        }
        
        // integral canny
        // first row
        $i=0; $sum=0;
        while ($i<$w)
        {
            $sum += $canny[$i];
            $canny[$i] = $sum;
            $i++;
        }
        // other rows
        $i=$w; $k=0; $sum=0;
        while ($i<$count)
        {
            $sum += $canny[$i];
            $canny[$i] = $canny[$i-$w] + $sum;
            $i++; $k++; if ($k>=$w) { $k=0; $sum=0; }
        }
        $this->canny =& $canny;
    }
    
    // merge the detected features if needed
    protected function merge($rects, $min_neighbors, $ratio, $selection) 
    {
        $rlen = count($rects); 
        $feats = array(); 
        $nb_classes = 0; 
        $found = false;
        
        // original code
        // find number of neighbour classes
        $ref = array_fill(0, $rlen, 0); 
        for ($i = 0; $i < $rlen; $i++)
        {
            $found = false;
            for ($j = 0; $j < $i; $j++)
            {
                if ( $rects[$j]->almostEqual($rects[$i]) )
                {
                    $found = true;
                    $ref[$i] = $ref[$j];
                }
            }
            
            if (!$found)
            {
                $ref[$i] = $nb_classes;
                $nb_classes++;
            }
        }        
        
        // merge neighbor classes
        $neighbors = array_fill(0, $nb_classes, 0);
        $r = array_fill(0, $nb_classes, 0);
        for ($i = 0; $i < $nb_classes; $i++) $r[$i] = new HAARFeature();
        for ($i = 0; $i < $rlen; $i++) 
        { 
            $ri = $ref[$i]; 
            $neighbors[$ri]++; 
            $r[$ri]->add($rects[$i]); 
        }
        for ($i = 0; $i < $nb_classes; $i++) 
        {
            $n = $neighbors[$i];
            if ($n >= $min_neighbors) 
            {
                $t = 1/($n + $n);
                $ri = new HAARFeature(
                    $t*($r[$i]->x * 2 + $n),  $t*($r[$i]->y * 2 + $n),
                    $t*($r[$i]->width * 2 + $n),  $t*($r[$i]->height * 2 + $n)
                );
                
                array_push($feats, $ri);
            }
        }
        
        if ($ratio != 1) { $ratio = 1.0/$ratio; }
        
        // filter inside rectangles
        $rlen = count($feats);
        for ($i=0; $i<$rlen; $i++)
        {
            for ($j=$i+1; $j<$rlen; $j++)
            {
                if (!$feats[$i]->isInside && $feats[$i]->inside($feats[$j])) 
                { 
                    $feats[$i]->isInside = true; 
                }
                elseif (!$feats[$j]->isInside && $feats[$j]->inside($feats[$i])) 
                { 
                    $feats[$j]->isInside = true; 
                }
            }
        }
        $i=$rlen;
        while (--$i >= 0) 
        { 
            if ($feats[$i]->isInside) 
            {
                array_splice($feats, $i, 1); 
            }
            else 
            {
                // scaled down, scale them back up
                if ($ratio != 1)  $feats[$i]->scale($ratio); 
                $feats[$i]->round()->computeArea(); 
            }
        }
        
        // sort according to size 
        // (a deterministic way to present results under different cases)
        usort($feats, array($this, 'byArea'));
        return $feats;
    }
    
    public function byArea($a, $b) { return $a->area-$b->area; }
}

//
//
//
// HAAR Feature/Rectangle Class
class HAARFeature
{
    public $index = 0;
    public $x = 0;
    public $y = 0;
    public $width = 0;
    public $height = 0;
    public $area = 0;
    public $isInside = false;
    
    public function __construct($x=0, $y=0, $w=0, $h=0, $i=0) 
    { 
        $this->data($x, $y, $w, $h, $i);
    }
    
    public function data($x, $y, $w, $h, $i) 
    {
        if ($x && ($x instanceof HAARFeature)) 
        {
            $this->copy($x);
        }
        elseif ($x && (is_array($x)))
        {
            $this->x = (isset($x['x'])) ? floatval($x['x']) : 0;
            $this->y = (isset($x['y'])) ? floatval($x['y']) : 0;
            $this->width = (isset($x['width'])) ? floatval($x['width']) : 0;
            $this->height=(isset($x['height'])) ? floatval($x['height']) : 0;
            $this->index = (isset($x['index'])) ? intval($x['index']) : 0;
            $this->area = (isset($x['area'])) ? floatval($x['area']) : 0;
            $this->isInside = (isset($x['isInside'])) ? $x['isInside'] : false;
        }
        else
        {
            $this->x = $x;
            $this->y = $y;
            $this->width = $w;
            $this->height = $h;
            $this->index = $i;
            $this->area = 0;
            $this->isInside = false;
        }
        return $this;
    }
    
    public function add($f) 
    { 
        $this->x += $f->x; 
        $this->y += $f->y; 
        $this->width += $f->width; 
        $this->height += $f->height; 
        return $this; 
    }
    
    public function scale($s) 
    { 
        $this->x *= $s; 
        $this->y *= $s; 
        $this->width *= $s; 
        $this->height *= $s; 
        return $this; 
    }
    
    public function round() 
    { 
        $this->x = round($this->x); 
        $this->y = round($this->y); 
        $this->width = round($this->width); 
        $this->height = round($this->height); 
        return $this; 
    }
    
    public function computeArea() 
    { 
        $this->area = $this->width*$this->height; 
        return $this->area; 
    } 
    
    public function inside($f) 
    { 
        return (
            ($this->x >= $f->x) && 
            ($this->y >= $f->y) && 
            ($this->x+$this->width <= $f->x+$f->width) && 
            ($this->y+$this->height <= $f->y+$f->height)
        ) ? true : false; 
    }
    
    public function contains($f) 
    { 
        return $f->inside($this); 
    }
    
    public function equal($f) 
    { 
        return (
            ($f->x==$this->x) && 
            ($f->y==$this->y) && 
            ($f->width==$this->width) && 
            ($f->height=$this->height)
        ) ? true : false; 
    }
    
    public function almostEqual($f) 
    { 
        $d1 = max($f->width, $this->width)*0.2;
        $d2 = max($f->height, $this->height)*0.2;
        //$d1 = max($f->width, $this->width)*0.5;
        //$d2 = max($f->height, $this->height)*0.5;
        //$d2 = $d1 = max($f->width, $this->width, $f->height, $this->height)*0.4;
        return ( 
            abs($this->x-$f->x) <= $d1 && 
            abs($this->y-$f->y) <= $d2 && 
            abs($this->width-$f->width) <= $d1 && 
            abs($this->height-$f->height) <= $d2 
            ) ? true : false; 
    }
    
    public function cloneit() 
    {
        $f = new self();
        $f->x = $this->x; 
        $f->y = $this->y; 
        $f->width = $this->width; 
        $f->height = $this->height; 
        $f->index = $this->index; 
        $f->area = $this->area; 
        $f->isInside = $this->isInside;
        return $f;
    }
    
    public function copy($f) 
    {
        if ($f && ($f instanceof HAARFeature))
        {
            $this->x = $f->x; 
            $this->y = $f->y; 
            $this->width = $f->width; 
            $this->height = $f->height; 
            $this->index = $f->index; 
            $this->area = $f->area; 
            $this->isInside = $f->isInside;
        }
        return $this;
    }
    
    public function __toString() 
    {
        return '[ x: ' . $this->x . ', y: ' . $this->y . ', width: ' . $this->width . ', height: ' . $this->height . ' ]';
    }
    
}
}