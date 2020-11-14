<?php
/**
*
* HaarDetector: Feature Detection Library based on Viola-Jones / Lienhart et al. Haar Detection algorithm
* modified port of jViolaJones for Java (http://code.google.com/p/jviolajones/) and OpenCV for C++ (https://github.com/opencv/opencv) to PHP
*
* https://github.com/foo123/HAARPHP
* @version: 1.0.2
*
**/

if (! class_exists('HaarDetector'))
{
class HaarDetector
{
    CONST  VERSION = "1.0.2";

    public $haardata = null;
    public $objects = null;

    private $canvas = null;
    private $origSelection = null;
    private $scaledSelection = null;
    private $ratio = 0.5;
    private $width = 0;
    private $height = 0;
    private $origWidth = 0;
    private $origHeight = 0;

    private $cannyLow = 20;
    private $cannyHigh = 100;
    private $canny = null;
    private $integral = null;
    private $squares = null;
    private $tilted = null;

    public function __construct($haardata = null)
    {
        $this->cascade($haardata);
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
    public function image(&$image, $scale = 1.0)
    {
        if ($image)
        {
            if (null != $this->canvas)
            {
                @imagedestroy($this->canvas);
                $this->canvas = null;
            }
            $r = $this->ratio = floatval($scale);
            $w = $this->origWidth = imagesx($image);
            $h = $this->origHeight = imagesy($image);
            $sw = $this->width = round($r * $w);
            $sh = $this->height = round($r * $h);

            // copy image data
            $this->canvas = imagecreatetruecolor($sw, $sh);
            imagecopyresampled($this->canvas, $image, 0, 0, 0, 0, $sw, $sh, $w, $h);

            // compute image data now, once per image change
            $gray =& $this->integralImage($this->canvas, $sw, $sh);
            $this->integralCanny($gray, $sw, $sh);
        }
        return $this;
    }

    // customize canny pruning thresholds for best results
    public function cannyThreshold($thres)
    {
        if ($thres && is_array($thres))
        {
            if (isset($thres['low'])) $this->cannyLow = floatval($thres['low']);
            if (isset($thres['high'])) $this->cannyHigh = floatval($thres['high']);
        }
        return $this;
    }

    // get/set custom detection region as selection
    public function selection(/* ..variable args here.. */)
    {
        $args = func_get_args();
        $argslength = count($args);
        if (0 == $argslength)
        {
            return $this->origSelection;
        }
        else if (1 == $argslength && (false === $args[0] || 'auto' === $args[0]))
        {
            $this->origSelection = null;
        }
        else
        {
            $this->origSelection = new HaarFeature();
            call_user_func_array(array($this->origSelection, 'data'), $args);
        }
        return $this;
    }

    // Detector detect method to start detection
    public function detect($baseScale = 1.0, $scale_inc = 1.25, $increment = 0.5, $min_neighbors = 1, $epsilon = 0.2, $doCannyPruning = false)
    {
        if (! $this->origSelection)
            $this->origSelection = new HaarFeature(0, 0, $this->origWidth, $this->origHeight);

        $this->origSelection->x = 'auto' === $this->origSelection->x ? 0 : $this->origSelection->x;
        $this->origSelection->y = 'auto' === $this->origSelection->y ? 0 : $this->origSelection->y;
        $this->origSelection->width = 'auto' === $this->origSelection->width ? $this->origWidth : $this->origSelection->width;
        $this->origSelection->height = 'auto' === $this->origSelection->height ? $this->origHeight : $this->origSelection->height;

        $this->scaledSelection = $this->origSelection->clone()->scale($this->ratio)->round();

        $haar =& $this->haardata;
        $haar_stages = $haar['stages'];

        $canny =& $this->canny;
        $integral =& $this->integral;
        $squares =& $this->squares;
        $tilted =& $this->tilted;

        $w = $this->width;
        $h = $this->height;

        $startx = $this->scaledSelection->x;
        $starty = $this->scaledSelection->y;
        $selw = $this->scaledSelection->width;
        $selh = $this->scaledSelection->height;
        $imArea = $w * $h;
        $imArea1 = $imArea - 1;
        $sizex = intval($haar['size1']);
        $sizey = intval($haar['size2']);

        $maxScale = min($this->scaledSelection->width/$sizex, $this->scaledSelection->height/$sizey);
        $scale = $baseScale;

        $sl = count($haar_stages);
        $cL = $this->cannyLow;
        $cH = $this->cannyHigh;

        $bx1 = 0;
        $bx2 = $w - 1;
        $by1 = 0;
        $by2 = $imArea - $w;

        $ret = array();

        // main detect loop
        while ($scale <= $maxScale)
        {
            $xsize = floor($scale * $sizex);
            $xstep = floor($xsize * $increment);
            $ysize = floor($scale * $sizey);
            $ystep = floor($ysize * $increment);
            $tyw = $ysize * $w;
            $tys = $ystep * $w;
            $startty = $starty * $tys;
            $xl = $selw - $xsize;
            $yl = $selh - $ysize;
            $swh = $xsize * $ysize;

            for ($y = $starty, $ty = $startty; $y < $yl; $y += $ystep, $ty += $tys)
            {
                for ($x = $startx; $x < $xl; $x += $xstep)
                {
                    $p0 = $x - 1 + $ty - $w;
                    $p1 = $p0 + $xsize;
                    $p2 = $p0 + $tyw;
                    $p3 = $p2 + $xsize;

                    // clamp
                    $p0 = $p0 < 0 ? 0 : ($p0 > $imArea1 ? $imArea1 : $p0);
                    $p1 = $p1 < 0 ? 0 : ($p1 > $imArea1 ? $imArea1 : $p1);
                    $p2 = $p2 < 0 ? 0 : ($p2 > $imArea1 ? $imArea1 : $p2);
                    $p3 = $p3 < 0 ? 0 : ($p3 > $imArea1 ? $imArea1 : $p3);

                    if ($doCannyPruning)
                    {
                        // avoid overflow
                        $edges_density = ($canny[$p3] - $canny[$p2] - $canny[$p1] + $canny[$p0]) / $swh;
                        if ($edges_density < $cL || $edges_density > $cH) continue;
                    }

                    // pre-compute some values for speed

                    // avoid overflow
                    $total_x = ($integral[$p3] - $integral[$p2] - $integral[$p1] + $integral[$p0]) / $swh;
                    // avoid overflow
                    $total_x2 = ($squares[$p3] - $squares[$p2] - $squares[$p1] + $squares[$p0]) / $swh;

                    $vnorm = $total_x2 - $total_x * $total_x;
                    $vnorm = $vnorm > 1 ? sqrt($vnorm) : /*$vnorm*/  1;

                    $pass = true;
                    for ($s = 0; $s < $sl; $s++)
                    {
                        // Viola-Jones HAAR-Stage evaluator
                        $stage =& $haar_stages[$s];
                        $threshold = $stage['thres'];
                        $trees =& $stage['trees'];
                        $tl = count($trees);
                        $sum = 0;

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

                                if (!empty($feature['tilt']))
                                {
                                    // tilted rectangle feature, Lienhart et al. extension
                                    for ($kr = 0; $kr < $nb_rects; $kr++)
                                    {
                                        $r =& $rects[$kr];

                                        // this produces better/larger features, possible rounding effects??
                                        $x1 = $x + floor($scale * $r[0]);
                                        $y1 = ($y - 1 + floor($scale * $r[1])) * $w;
                                        $x2 = $x + floor($scale * ($r[0] + $r[2]));
                                        $y2 = ($y - 1 + floor($scale * ($r[1] + $r[2]))) * $w;
                                        $x3 = $x + floor($scale * ($r[0] - $r[3]));
                                        $y3 = ($y - 1 + floor($scale * ($r[1] + $r[3]))) * $w;
                                        $x4 = $x + floor($scale * ($r[0] + $r[2] - $r[3]));
                                        $y4 = ($y - 1 + floor($scale * ($r[1] + $r[2] + $r[3]))) * $w;

                                        // clamp
                                        $x1 = $x1 < $bx1 ? $bx1 : ($x1 > $bx2 ? $bx2 : $x1);
                                        $x2 = $x2 < $bx1 ? $bx1 : ($x2 > $bx2 ? $bx2 : $x2);
                                        $x3 = $x3 < $bx1 ? $bx1 : ($x3 > $bx2 ? $bx2 : $x3);
                                        $x4 = $x4 < $bx1 ? $bx1 : ($x4 > $bx2 ? $bx2 : $x4);
                                        $y1 = $y1 < $by1 ? $by1 : ($y1 > $by2 ? $by2 : $y1);
                                        $y2 = $y2 < $by1 ? $by1 : ($y2 > $by2 ? $by2 : $y2);
                                        $y3 = $y3 < $by1 ? $by1 : ($y3 > $by2 ? $by2 : $y3);
                                        $y4 = $y4 < $by1 ? $by1 : ($y4 > $by2 ? $by2 : $y4);

                                        // RSAT(x-h+w, y+w+h-1) + RSAT(x, y-1) - RSAT(x-h, y+h-1) - RSAT(x+w, y+w-1)
                                        //        x4     y4            x1  y1          x3   y3            x2   y2
                                        $rect_sum += $r[4] * ($tilted[$x4 + $y4] - $tilted[$x3 + $y3] - $tilted[$x2 + $y2] + $tilted[$x1 + $y1]);
                                    }
                                }
                                else
                                {
                                    // orthogonal rectangle feature, Viola-Jones original
                                    for ($kr = 0; $kr < $nb_rects; $kr++)
                                    {
                                        $r =& $rects[$kr];

                                        // this produces better/larger features, possible rounding effects??
                                        $x1 = $x - 1 + floor($scale * $r[0]);
                                        $x2 = $x - 1 + floor($scale * ($r[0] + $r[2]));
                                        $y1 = $w * ($y - 1 + floor($scale * $r[1]));
                                        $y2 = $w * ($y - 1 + floor($scale * ($r[1] + $r[3])));

                                        // clamp
                                        $x1 = $x1 < $bx1 ? $bx1 : ($x1 > $bx2 ? $bx2 : $x1);
                                        $x2 = $x2 < $bx1 ? $bx1 : ($x2 > $bx2 ? $bx2 : $x2);
                                        $y1 = $y1 < $by1 ? $by1 : ($y1 > $by2 ? $by2 : $y1);
                                        $y2 = $y2 < $by1 ? $by1 : ($y2 > $by2 ? $by2 : $y2);

                                        // SAT(x-1, y-1) + SAT(x+w-1, y+h-1) - SAT(x-1, y+h-1) - SAT(x+w-1, y-1)
                                        //      x1   y1         x2      y2          x1   y1            x2    y1
                                        $rect_sum += $r[4] * ($integral[$x2 + $y2]  - $integral[$x1 + $y2] - $integral[$x2 + $y1] + $integral[$x1 + $y1]);
                                    }
                                }

                                $where = $rect_sum / $swh < $thresholdf * $vnorm ? 0 : 1;
                                // END Viola-Jones HAAR-Leaf evaluator

                                if ($where)
                                {
                                    if (!empty($feature['has_r']))
                                    {
                                        $sum += $feature['r_val'];
                                        break;
                                    }
                                    else
                                    {
                                        $cur_node_ind = $feature['r_node'];
                                    }
                                }
                                else
                                {
                                    if (!empty($feature['has_l']))
                                    {
                                        $sum += $feature['l_val'];
                                        break;
                                    }
                                    else
                                    {
                                        $cur_node_ind = $feature['l_node'];
                                    }
                                }
                            }
                            // END Viola-Jones HAAR-Tree evaluator

                        }
                        $pass = $sum > $threshold;
                        // END Viola-Jones HAAR-Stage evaluator

                        if (! $pass) break;
                    }

                    if ($pass)
                    {
                        $ret[] = new HaarFeature($x, $y, $xsize, $ysize);
                    }
                }
            }

            $scale *= $scale_inc;
        }

        // return results
        if (! empty($ret))
        {
            $this->objects = HaarFeature::groupRectangles($ret, $min_neighbors, $epsilon);
        }
        else
        {
            $this->objects = array();
        }

        $ratio = 1.0 / $this->ratio;
        foreach($this->objects as $obj) $obj->scale($ratio)->round()->computeArea();
        // sort according to size
        // (a deterministic way to present results under different cases)
        usort($this->objects, array('HaarFeature', 'byArea'));


        return 0 < count($this->objects);
    }

    // compute gray-scale image, integral image and square image (Viola-Jones)
    private function &integralImage(&$canvas, $w, $h)
    {
        $count = $w*$h;
        $gray = array_fill(0, $count, 0);
        $integral = array_fill(0, $count, 0);
        $squares = array_fill(0, $count, 0);
        $tilted = array_fill(0, $count, 0);

        // first row
        $i = 0;
        $sum = $sum2 = 0;
        while ($i < $w)
        {
            $rgb = imagecolorat($canvas, $i, 0);
            $r = ($rgb >> 16) & 255;
            $g = ($rgb >> 8) & 255;
            $b = $rgb & 255;
            // 0,29901123046875  0,58697509765625  0,114013671875 with roundoff
            $g = 0.299 * $r + 0.587 * $g + 0.114 * $b;

            $sum += $g;
            $sum2 += $g*$g;

            // SAT(-1, y) = SAT(x, -1) = SAT(-1, -1) = 0
            // SAT(x, y) = SAT(x, y-1) + SAT(x-1, y) + I(x, y) - SAT(x-1, y-1)  <-- integral image

            // RSAT(-1, y) = RSAT(x, -1) = RSAT(x, -2) = RSAT(-1, -1) = RSAT(-1, -2) = 0
            // RSAT(x, y) = RSAT(x-1, y-1) + RSAT(x+1, y-1) - RSAT(x, y-2) + I(x, y) + I(x, y-1)    <-- rotated(tilted) integral image at 45deg
            $gray[$i] = $g;
            $integral[$i] = $sum;
            $squares[$i] = $sum2;
            $tilted[$i] = $g;

            $i++;
        }
        // other rows
        $i = 0;
        $j = 1;
        $k = $w;
        $sum = $sum2 = 0;
        while ($k < $count)
        {
            $rgb = imagecolorat($canvas, $i, $j);
            $r = ($rgb >> 16) & 255;
            $g = ($rgb >> 8) & 255;
            $b = $rgb & 255;
            // 0,29901123046875  0,58697509765625  0,114013671875 with roundoff
            $g = 0.299 * $r + 0.587 * $g + 0.114 * $b;

            $sum += $g;
            $sum2 += $g*$g;

            // SAT(-1, y) = SAT(x, -1) = SAT(-1, -1) = 0
            // SAT(x, y) = SAT(x, y-1) + SAT(x-1, y) + I(x, y) - SAT(x-1, y-1)  <-- integral image

            // RSAT(-1, y) = RSAT(x, -1) = RSAT(x, -2) = RSAT(-1, -1) = RSAT(-1, -2) = 0
            // RSAT(x, y) = RSAT(x-1, y-1) + RSAT(x+1, y-1) - RSAT(x, y-2) + I(x, y) + I(x, y-1)    <-- rotated(tilted) integral image at 45deg
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
    private function integralCanny(&$gray, $w, $h)
    {
        $count = $w*$h;
        $lowpass = array_fill(0, $count, 0);
        $canny = array_fill(0, $count, 0);
        $factor = 0.00628930817610062893081761006289;// 1/159;
        // gauss lowpass
        for ($i = 2; $i < $w-2; $i++)
        {
            $sum = 0;
            for ($j = 2, $k = ($w<<1); $j < $h-2; $j++, $k+=$w)
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
        for ($i = 1; $i < $w-1 ; $i++)
        {
            for ($j = 1, $k = $w; $j < $h-1; $j++, $k+=$w)
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
        $i = 0;
        $sum = 0;
        while ($i < $w)
        {
            $sum += $canny[$i];
            $canny[$i] = $sum;
            $i++;
        }
        // other rows
        $i = $w;
        $k = 0;
        $sum = 0;
        while ($i<$count)
        {
            $sum += $canny[$i];
            $canny[$i] = $canny[$i-$w] + $sum;
            $i++; $k++; if ($k>=$w) { $k=0; $sum=0; }
        }
        $this->canny =& $canny;
    }
}

// HAAR Feature/Rectangle Class
class HaarFeature
{
    public $index = 0;
    public $x = 0;
    public $y = 0;
    public $width = 0;
    public $height = 0;
    public $area = 0;
    public $isInside = false;

    public static function groupRectangles($rects, $min_neighbors, $epsilon = 0.2)
    {
        // merge the detected features if needed
        $rlen = count($rects);
        $ref = array_fill(0, $rlen, 0);
        $feats = array();
        $nb_classes = 0;
        $found = false;

        // original code
        // find number of neighbour classes
        for ($i = 0; $i < $rlen; $i++)
        {
            $found = false;
            for ($j = 0; $j < $i; $j++)
            {
                if ($rects[$j]->equals($rects[$i], $epsilon))
                {
                    $found = true;
                    $ref[$i] = $ref[$j];
                }
            }

            if (! $found)
            {
                $ref[$i] = $nb_classes;
                $nb_classes++;
            }
        }

        // merge neighbor classes
        $neighbors = array_fill(0, $nb_classes, 0);
        $r = array();
        for ($i = 0; $i < $nb_classes; $i++)
        {
            $r[] = new HaarFeature();
        }
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
                $t = $n + $n;
                $ri = new HaarFeature(
                    ($r[$i]->x * 2 + $n)/$t,  ($r[$i]->y * 2 + $n)/$t,
                    ($r[$i]->width * 2 + $n)/$t,  ($r[$i]->height * 2 + $n)/$t
                );

                $feats[] = $ri;
            }
        }

        // filter inside rectangles
        $rlen = count($feats);
        for ($i = 0; $i < $rlen; $i++)
        {
            for ($j = $i+1; $j < $rlen; $j++)
            {
                if (! $feats[$i]->isInside && $feats[$i]->inside($feats[$j]))
                {
                    $feats[$i]->isInside = true;
                }
                elseif (! $feats[$j]->isInside && $feats[$j]->inside($feats[$i]))
                {
                    $feats[$j]->isInside = true;
                }
            }
        }
        $i = $rlen;
        while (--$i >= 0)
        {
            if ($feats[$i]->isInside)
            {
                array_splice($feats, $i, 1);
            }
        }

        return $feats;
    }

    public static function byArea($a, $b)
    {
        return $b->area - $a->area;
    }

    public function __construct($x = 0, $y = 0, $w = 0, $h = 0, $i = 0)
    {
        $this->data($x, $y, $w, $h, $i);
    }

    public function data($x, $y, $w, $h, $i = 0)
    {
        if ($x instanceof HaarFeature)
        {
            $this->copy($x);
        }
        elseif (is_array($x))
        {
            $this->x = isset($x['x']) ? $x['x'] : 0;
            $this->y = isset($x['y']) ? $x['y'] : 0;
            $this->width = isset($x['width']) ? $x['width'] : 0;
            $this->height = isset($x['height']) ? $x['height'] : 0;
            $this->index = isset($x['index']) ? intval($x['index']) : 0;
            $this->area = isset($x['area']) ? $x['area'] : 0;
            $this->isInside = !empty($x['isInside']);
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
        $x = $this->x + $f->x;
        $y = $this->y + $f->y;
        $w = $this->width + $f->width;
        $h = $this->height + $f->height;
        $this->x = $x;
        $this->y = $y;
        $this->width = $w;
        $this->height = $h;
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
        $this->area = $this->width * $this->height;
        return $this->area;
    }

    public function inside($f, $eps = 0.1)
    {
        if (0 > $eps) $eps = 0;
        $dx = $f->width * $eps;
        $dy = $f->height * $eps;
        return ($this->x >= $f->x - $dx) &&
            ($this->y >= $f->y - $dy) &&
            ($this->x + $this->width <= $f->x + $f->width + $dx) &&
            ($this->y + $this->height <= $f->y + $f->height + $dy);
    }

    public function contains($f, $eps = 0.1)
    {
        return $f->inside($this, $eps);
    }

    public function equals($f, $eps = 0.2)
    {
        if (0 > $eps) $eps = 0;
        $delta = $eps * (min($this->width, $f->width) + min($this->height, $f->height)) * 0.5;
        return abs($this->x - $f->x) <= $delta &&
            abs($this->y - $f->y) <= $delta &&
            abs($this->x + $this->width - $f->x - $f->width) <= $delta &&
            abs($this->y + $this->height - $f->y - $f->height) <= $delta;
    }

    public function clone()
    {
        $f = new static();
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
        if ($f instanceof HaarFeature)
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
        return '[x: ' . $this->x . ', y: ' . $this->y . ', width: ' . $this->width . ', height: ' . $this->height . ']';
    }
}
}