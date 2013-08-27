<?php
/**
*
* HAARPHP Feature Detection Library based on Viola-Jones Haar Detection algorithm
* Port of jviolajones (Java) which is a port of openCV C++ Haar Detector
*
* version: 0.3
*
* IMPORTANT: Requires PHP 5 and GD Library for image manipulation
*
* @author Nikos M.  (http://nikos-web-development.netai.net/)
* https://github.com/foo123/HAARPHP
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
	public $haardata=null;
	public $objects=null;
	public $Selection=null;
	public $Ready=false;
	
	private $Canvas=null;
	private $scaledSelection=null;
	private $Ratio=0.5;
	private $width=0;
	private $height=0;
	private $origWidth=0;
	private $origHeight=0;
	private $cannyLow=20;
	private $cannyHigh=100;
	private $doCannyPruning=true;
	private $scale=0;
	private $min_neighbors=0;
	private $scale_inc=0;
	private $increment=0;
	private $maxScale=0;
	private $canny=null;
	private $integral=null;
	private $squares=null;
	private $ImageChanged=false;
	
	// factory method
    public static function getDetector($haardata=null) { return new self($haardata);  }
    
    // constructor
	public function __construct($haardata=null) { $this->haardata=$haardata; }
    
    public function cascade($haardata) { $this->haardata=$haardata; return $this;  }
    
    // set image for detector along with scaling
	public function image(&$image, $scale=0.5)
	{
		if ($image)
        {
            $this->Ratio=$scale;
            $this->origWidth=imagesx($image);
            $this->origHeight=imagesy($image);
            if (null!=$this->Canvas) { @imagedestroy($this->Canvas);  $this->Canvas=null; }
            $this->width=round($this->Ratio*$this->origWidth);
            $this->height=round($this->Ratio*$this->origHeight);
            $this->Canvas=imagecreatetruecolor($this->width, $this->height);
            imagecopyresampled($this->Canvas, $image, 0, 0, 0, 0, $this->width, $this->height, $this->origWidth, $this->origHeight);
            $this->ImageChanged=true;
        }
        return $this;
	}
	
    // customize canny prunign thresholds for best results
    public function cannyThreshold($thres) 
    {
        if ($thres)
        {
            if (isset($thres['low'])) $this->cannyLow=$thres['low'];
            if (isset($thres['high'])) $this->cannyHigh=$thres['high'];
        }
        return $this;
    }
    
    // set custom detection region as selection
    public function selection(/* ..variable args here.. */) 
    { 
        $args=func_get_args(); $argslength=count($args);
        if (1==$argslength && 'auto'==$args[0] || 0==$argslength) $this->Selection=null;
        else { $this->Selection=new HAARFeature(); call_user_func_array(array($this->Selection, 'data'), $args); }
        return $this; 
    }
    
    // Detector detect method to start detection
    // NOTE: currently cannyPruning does not give expected results (so use false in detect method) (maybe fix in the future)
	public function detect($baseScale=1.0, $scale_inc=1.25, $increment=0.1, $min_neighbors=1, $doCannyPruning=false)
	{
        if (!$this->Selection) $this->Selection = new HAARFeature(0, 0, $this->origWidth, $this->origHeight);
        $this->Selection->x=('auto'==$this->Selection->x) ? 0 : $this->Selection->x;
        $this->Selection->y=('auto'==$this->Selection->y) ? 0 : $this->Selection->y;
        $this->Selection->width=('auto'==$this->Selection->width) ? $this->origWidth : $this->Selection->width;
        $this->Selection->height=('auto'==$this->Selection->height) ? $this->origHeight : $this->Selection->height;
        $this->scaledSelection=$this->Selection->copy()->scale($this->Ratio)->round();
        
        $this->doCannyPruning=$doCannyPruning;
        if ($this->ImageChanged) // allow to use cached image data with same image/different selection
        {
            $gray = $this->integralImage($this->Canvas/*, $this->scaledSelection*/);
            if ($this->doCannyPruning)
                $this->integralCanny($gray, $this->width, $this->height/*, $this->scaledSelection->width, $this->scaledSelection->height*/);
            else 
                $this->canny=null;
            $gray=null; unset($gray);
        }
        $this->ImageChanged=false;
        
		$ret=array();	$gfound=false;
        $sizex=(int)$this->haardata['size1']; $sizey=(int)$this->haardata['size2'];
        $this->maxScale=min($this->width/$sizex, $this->height/$sizey); $this->scale=$baseScale; $this->min_neighbors=$min_neighbors; 
        $this->scale_inc=$scale_inc; $this->increment=$increment; $this->Ready=false;
        $w=$this->scaledSelection->width; $h=$this->scaledSelection->height; 
        $starti=$this->scaledSelection->x; $startj=$this->scaledSelection->y;
        $cL=$this->cannyLow; $cH=$this->cannyHigh;
        
        // detect loop
		while ($this->scale<=$this->maxScale)
		{
            $step=floor($this->scale*$sizex*$this->increment);
			$size=floor($this->scale*$sizex);
			$inv_size=1.0/($size*$size); $kw=$size*$w; $ks=$step*$w; $startk=($startj) ? $startj*$ks : 0;
            // pre-compute some values for speed
            $sw=$size; $sh=floor($this->scale * $sizey); $swh=$sw*$sh; $wh=$w*$sh; $inv_area=1.0/$swh;
			
            for($i=$starti;$i<$w-$size;$i+=$step)
			{
                $k=$startk;
                for($j=$startj;$j<$h-$size;$j+=$step)
				{
                    $ind2=$i+$k; $ind1=$ind2+$kw; $k+=$ks;
					// NOTE: currently cannyPruning does not give expected results (so use false in detect method) (maybe fix in the future)
					if($this->doCannyPruning)
					{
                        $edges_density=$this->canny[$ind1+$size] + $this->canny[$ind2] - $this->canny[$ind1] - $this->canny[$ind2+$size];
						$d=$edges_density*$inv_size;
						if($d<$cL || $d>$cH)	continue;
					}
                    // pre-compute some values for speed
                    $ii=$ind2; $iih=$ii+$wh;
                    $total_x = $this->integral[$sw+$iih] + $this->integral[$ii] - $this->integral[$iih] - $this->integral[$sw+$ii];
                    $total_x2 = $this->squares[$sw+$iih] + $this->squares[$ii] - $this->squares[$iih] - $this->squares[$sw+$ii];
                    $mu = $total_x * $inv_area; $vnorm = $total_x2 * $inv_area - $mu * $mu;
                    $vnorm = ($vnorm > 1) ? sqrt($vnorm) : 1;
					$slen=count($this->haardata['stages']); $pass=true;
					for($s=0; $s<$slen; $s++)
					{
						$pass=$this->evalStage($s, $i, $j, $this->scale, $vnorm, $inv_area);
						if (false==$pass)  break;
					}
					if ($pass) 
					{
						$gfound=true;
						$ret[]=new HAARFeature($i, $j, $size, $size);
					}
				}
			}
			$this->scale*=$this->scale_inc;
		}
        // return results
		if (isset($ret[0]))
            $this->objects=$this->merge($ret, $this->min_neighbors, $this->Ratio, $this->Selection);
        else
            $this->objects=array();
        $ret=null; unset($ret);
		return $gfound;  // returns true/false whether found at least sth
	}

    // Viola-Jones HAAR-Stage evaluator
    protected function evalStage($s, $i, $j, $scale, $vnorm, $inv_area) 
    {
        $stage=$this->haardata['stages'][$s]; $threshold=(float)$stage['thres']; $trees=$stage['trees']; $tl=count($trees);
        $ww=$this->scaledSelection->width; $hh=$this->scaledSelection->height;
        $sum=0.0;
        
        for ($t=0; $t<$tl; $t++) 
        { 
            //
            // inline the tree and leaf evaluators to avoid function calls per-loop (faster)
            //
            
            // Viola-Jones HAAR-Tree evaluator
            $features=$trees[$t]['feats']; $cur_node_ind=0;
            while (true) 
            {
                $feature=$features[$cur_node_ind]; 
                // Viola-Jones HAAR-Leaf evaluator
                $rects=$feature['rects']; $nb_rects=count($rects); $thresholdf=$feature['thres']; $rect_sum=0;
                for ($k = 0; $k < $nb_rects; $k++) 
                {
                    $r = $rects[$k];
                    $rx1 = $i + floor($scale * (float)$r['x1']); $rx2 = $i + floor($scale * ((float)$r['x1'] + (float)$r['y1']));
                    $ry1 = $ww*($j + floor($scale * (float)$r['x2'])); $ry2 = $ww*($j + floor($scale * ((float)$r['x2'] + (float)$r['y2'])));
                    $rect_sum+= /*floor*/((float)$r['f'] * ($this->integral[$rx2+$ry2] - $this->integral[$rx1+$ry2] - $this->integral[$rx2+$ry1] + $this->integral[$rx1+$ry1]));
                }
                $where = ($rect_sum * $inv_area < $thresholdf * $vnorm) ? 0 : 1;
                // END Viola-Jones HAAR-Leaf evaluator
                
                if (0 == $where) 
                {
                    if ((true==$feature['has_l']) || (1==$feature['has_l'])) { $sum+=(float)$feature['l_val']; break; } 
                    else { $cur_node_ind=$feature['l_node']; }
                } 
                else 
                {
                    if ((true==$feature['has_r']) || (1==$feature['has_r'])) { $sum+=(float)$feature['r_val']; break; } 
                    else { $cur_node_ind=$feature['r_node']; }
                }
            }
            // END Viola-Jones HAAR-Tree evaluator
        }
        return (bool)($sum > $threshold);
        // END Viola-Jones HAAR-Stage evaluator
    }
	
    // auxilliary private methods
    // compute gray-scale image, integral image and square image (Viola-Jones)
    protected function integralImage($canvas/*, selection*/) 
    {
        $w=$this->width; $h=$this->height; $count=$w*$h;
        $gray = array_fill(0, $count, 0); 
        $this->integral = array_fill(0, $count, 0); 
        $this->squares = array_fill(0, $count, 0); 
        
        for ($i=0; $i<$w; $i++) 
        {
            $col=$col2=0; $k=0;
            for ($j=0; $j<$h; $j++) 
            {
                // compute coords using simple add/subtract arithmetic (faster)
                $ind=$k+$i; $k+=$w;
                $rgb=imagecolorat($canvas, $i, $j);
				$red=($rgb >> 16) & 0xFF; $green=($rgb >> 8) & 0xFF; $blue=$rgb & 0xFF;
                // use fixed-point gray-scale transform, close to openCV transform
                // 0,29901123046875  0,58697509765625  0,114013671875 with roundoff
                $gray[$ind] = (((4899 * $red + 9617 * $green + 1868 * $blue) + 8192) >> 14)&0xFF;
                $col += $gray[$ind];  $col2 += $gray[$ind]*$gray[$ind];
                if ($i)
                {
                    $this->integral[$ind] = $this->integral[$ind-1] + $col;
                    $this->squares[$ind] = $this->squares[$ind-1] + $col2;
                }
                else
                {
                    $this->integral[$ind] = $col;
                    $this->squares[$ind] = $col2;
                }
            }
        }
        return $gray;
    }
	
    // compute Canny edges on gray-scale image to speed up detection if possible
    protected function integralCanny($gray, $w, $h) 
    {
        $count=$w*$h;
        $grad = array_fill(0, $count, 0);
        $this->canny = array_fill(0, $count, 0);
        
        for ($i = 0; $i < $w; $i++)
        {
            $k=0; $sum=0;
            for ($j = 0; $j < $h; $j++) 
            {
                // compute coords using simple add/subtract arithmetic (faster)
                $ind0=$k+$i; $k+=$w;
                
                if ($i<2 || $i>=$w-2 || $j<2 || $j>=$h-2) { $grad[$ind0]=0; continue; }
                
                $ind1=$ind0+$w; $ind2=$ind1+$w; $ind_1=$ind0-$w; $ind_2=$ind_1-$w; 
                
                /*
                 Original Code
                 
				$sum=0.0;
				$sum+=2.0*$grayImage[$i-2+($j-2)*$w];
				$sum+=4.0*$grayImage[$i-2+($j-1)*$w];
				$sum+=5.0*$grayImage[$i-2+($j+0)*$w];
				$sum+=4.0*$grayImage[$i-2+($j+1)*$w];
				$sum+=2.0*$grayImage[$i-2+($j+2)*$w];
				$sum+=4.0*$grayImage[$i-1+($j-2)*$w];
				$sum+=9.0*$grayImage[$i-1+($j-1)*$w];
				$sum+=12.0*$grayImage[$i-1+($j+0)*$w];
				$sum+=9.0*$grayImage[$i-1+($j+1)*$w];
				$sum+=4.0*$grayImage[$i-1+($j+2)*$w];
				$sum+=5.0*$grayImage[$i+0+($j-2)*$w];
				$sum+=12.0*$grayImage[$i+0+($j-1)*$w];
				$sum+=15.0*$grayImage[$i+0+($j+0)*$w];
				$sum+=12.0*$grayImage[$i+0+($j+1)*$w];
				$sum+=5.0*$grayImage[$i+0+($j+2)*$w];
				$sum+=4.0*$grayImage[$i+1+($j-2)*$w];
				$sum+=9.0*$grayImage[$i+1+($j-1)*$w];
				$sum+=12.0*$grayImage[$i+1+($j+0)*$w];
				$sum+=9.0*$grayImage[$i+1+($j+1)*$w];
				$sum+=4.0*$grayImage[$i+1+($j+2)*$w];
				$sum+=2.0*$grayImage[$i+2+($j-2)*$w];
				$sum+=4.0*$grayImage[$i+2+($j-1)*$w];
				$sum+=5.0*$grayImage[$i+2+($j+0)*$w];
				$sum+=4.0*$grayImage[$i+2+($j+1)*$w];
				$sum+=2.0*$grayImage[$i+2+($j+2)*$w];

				$canny[$i+$j*$w]=($sum/159.0);
                */
                
                // use as simple fixed-point arithmetic as possible (only addition/subtraction and binary shifts)
                // http://php.net/manual/en/language.operators.bitwise.php
                // http://board.phpbuilder.com/showthread.php?10366408-Bitwise-Unsigned-Right-Shift
                $sum = ((0
                        + ((int)$gray[$ind_2-2] << 1) + ((int)$gray[$ind_1-2] << 2) + ((int)$gray[$ind0-2] << 2) + ((int)$gray[$ind0-2])
                        + ((int)$gray[$ind1-2] << 2) + ((int)$gray[$ind2-2] << 1) + ((int)$gray[$ind_2-1] << 2) + ((int)$gray[$ind_1-1] << 3)
                        + ((int)$gray[$ind_1-1]) + ((int)$gray[$ind0-1] << 4) - ((int)$gray[$ind0-1] << 2) + ((int)$gray[$ind1-1] << 3)
                        + ((int)$gray[$ind1-1]) + ((int)$gray[$ind2-1] << 2) + ((int)$gray[$ind_2] << 2) + ((int)$gray[$ind_2]) + ((int)$gray[$ind_1] << 4)
                        - ((int)$gray[$ind_1] << 2) + ((int)$gray[$ind0] << 4) - ((int)$gray[$ind0]) + ((int)$gray[$ind1] << 4) - ((int)$gray[$ind1] << 2)
                        + ((int)$gray[$ind2] << 2) + ((int)$gray[$ind2]) + ((int)$gray[$ind_2+1] << 2) + ((int)$gray[$ind_1+1] << 3) + ((int)$gray[$ind_1+1])
                        + ((int)$gray[$ind0+1] << 4) - ((int)$gray[$ind0+1] << 2) + ((int)$gray[$ind1+1] << 3) + ((int)$gray[$ind1+1]) + ((int)$gray[$ind2+1] << 2)
                        + ((int)$gray[$ind_2+2] << 1) + ((int)$gray[$ind_1+2] << 2) + ((int)$gray[$ind0+2] << 2) + ((int)$gray[$ind0+2])
                        + ((int)$gray[$ind1+2] << 2) + ((int)$gray[$ind2+2] << 1)
                        ) );
                
                $grad[$ind0] = $sum*0.0062893081761006; // 1/159
            }
        }
        
        for ($i = 0; $i < $w; $i++)
        {
            $k=0; $sum=0; 
            for ($j = 0; $j < $h; $j++) 
            {
                // compute coords using simple add/subtract arithmetic (faster)
                $ind0=$k+$i; $k+=$w;
                
                if ($i<1 || $i>=$w-1 || $j<1 || $j>=$h-1) 
                { 
                    $sum=0;
                }
                else
                {
                    $ind1=$ind0+$w; $ind_1=$ind0-$w; 
                    
                    $grad_x = ((0
                            - $grad[$ind_1-1] 
                            + $grad[$ind_1+1] 
                            - $grad[$ind0-1] - $grad[$ind0-1]
                            + $grad[$ind0+1] + $grad[$ind0+1]
                            - $grad[$ind1-1] 
                            + $grad[$ind1+1]
                            ))
                            ;
                    $grad_y = ((0
                            + $grad[$ind_1-1] 
                            + $grad[$ind_1] + $grad[$ind_1]
                            + $grad[$ind_1+1] 
                            - $grad[$ind1-1] 
                            - $grad[$ind1] - $grad[$ind1]
                            - $grad[$ind1+1]
                            ))
                            ;
                    $sum+=(abs($grad_x) + abs($grad_y));
                }
                $this->canny[$ind0] = ($i) ? ($this->canny[$ind0-1]+$sum) : $sum;
           }
        }
    }
	
    // merge the detected features if needed
    protected function merge($rects, $min_neighbors, $ratio, $selection) 
    {
        $rlen=count($rects); $ref=array_fill(0, $rlen, 0); $feats=array(); 
        $nb_classes = 0; $found=false;
        
        for ($i = 0; $i < $rlen; $i++) 
        {
            $found = false;
            for ($j = 0; $j < $i; $j++) { if ($rects[$j]->almostEqual($rects[$i])) { $found = true; $ref[$i] = $ref[$j]; }  }
            if (!$found) { $ref[$i] = $nb_classes;  $nb_classes++; }
        }
        
        $neighbors = array_fill(0,$nb_classes, 0 );  $r = array_fill(0,$nb_classes, 0 );
        for ($i = 0; $i < $nb_classes; $i++) { $r[$i] = new HAARFeature(); }
        for ($i = 0; $i < $rlen; $i++) { $ri=$ref[$i]; $neighbors[$ri]++; $r[$ri]->add($rects[$i]); }
        
        // merge neighbor classes
        for ($i = 0; $i < $nb_classes; $i++) 
        {
            $n = $neighbors[$i];
            if ($n >= $min_neighbors) 
            {
                $t=1/($n + $n);
                $ri = new HAARFeature(
                    $t*($r[$i]->x * 2 + $n),  $t*($r[$i]->y * 2 + $n),
                    $t*($r[$i]->width * 2 + $n),  $t*($r[$i]->height * 2 + $n)
                );
                
                $feats[]=$ri;
            }
        }
        
        if ($ratio != 1) { $ratio=1.0/$ratio; }
        // filter inside rectangles
        $rlen=count($feats);
        for ($i=0; $i<$rlen; $i++)
        {
            for ($j=$i+1; $j<$rlen; $j++)
            {
                if (!$feats[$i]->isInside && $feats[$i]->inside($feats[$j])) { $feats[$i]->isInside=true; }
                elseif (!$feats[$j]->isInside && $feats[$j]->inside($feats[$i])) { $feats[$j]->isInside=true; }
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
                //$feats[$i]->x+=$selection->x; $feats[$i]->y+=$selection->y;
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
    public $index=0;
    public $x=0;
    public $y=0;
    public $width=0;
    public $height=0;
    public $area=0;
    public $isInside=false;
    
    public function __construct($x=0, $y=0, $w=0, $h=0, $i=0) 
    { 
        $this->data($x, $y, $w, $h, $i);
    }
    
    public function data($x, $y, $w, $h, $i) 
    {
        if ($x && ($x instanceof HAARFeature)) 
        {
            $x->copy($this);
        }
        elseif ($x && (is_array($x)))
        {
            $this->x=(isset($x['x'])) ? $x['x'] : 0;
            $this->y=(isset($x['y'])) ? $x['y'] : 0;
            $this->width=(isset($x['width'])) ? $x['width'] : 0;
            $this->height=(isset($x['height'])) ? $x['height'] : 0;
            $this->index=(isset($x['index'])) ? $x['index'] : 0;
            $this->area=(isset($x['area'])) ? $x['area'] : 0;
            $this->isInside=(isset($x['isInside'])) ? $x['isInside'] : false;
        }
        else
        {
            $this->x=$x;
            $this->y=$y;
            $this->width=$w;
            $this->height=$h;
            $this->index=$i;
            $this->area=0;
            $this->isInside=false;
        }
        return $this;
    }
    
    public function add($f) { $this->x+=$f->x; $this->y+=$f->y; $this->width+=$f->width; $this->height+=$f->height; return $this; }
    
    public function scale($s) { $this->x*=$s; $this->y*=$s; $this->width*=$s; $this->height*=$s; return $this; }
    
    public function round() 
    { 
        $this->x=round($this->x); $this->y=round($this->y); $this->width=round($this->width); $this->height=round($this->height); return $this; 
    }
    
    public function computeArea() { $this->area=$this->width*$this->height; return $this->area; } 
    
    public function inside($f) 
    { 
        return (($this->x>=$f->x) && ($this->y>=$f->y) && ($this->x+$this->width<=$f->x+$f->width) && ($this->y+$this->height<=$f->y+$f->height)) ? true : false; 
    }
    
    public function contains($f) { return $f->inside($this); }
    
    public function equal($f) { return (($f->x==$this->x) && ($f->y==$this->y) && ($f->width==$this->width) && ($f->height=$this->height)) ? true : false; }
    
    public function almostEqual($f) 
    { 
        $d1=max($f->width, $this->width)*0.18; $d2=max($f->height, $this->height)*0.18;
        return ( abs($this->x-$f->x) <= $d1 && abs($this->y-$f->y) <= $d2 && abs($this->width-$f->width) <= $d1 && abs($this->height-$f->height) <= $d2 ) ? true : false; 
    }
    
    public function copy($f=null) 
    {
        $f=(isset($f)) ? $f : new self();
        $f->x=$this->x; $f->y=$this->y; $f->width=$this->width; $f->height=$this->height; 
        $f->index=$this->index; $f->area=$this->area; $f->isInside=$this->isInside;
        return $f;
    }
}
}