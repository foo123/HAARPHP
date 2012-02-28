<?php
/**************************************************************************************
** HAARPHP Feature Detection Library based on Viola-Jones Haar Detection algorithm
** Port of jviolajones (Java) which is a port of openCV C++ Haar Detector
**
** IMPORTANT: Requires PHP 5 and GD Library for image manipulation
**
** Author Nikos M.
** url http://nikos-web-development.netai.net/
**************************************************************************************/

// Detector Class with the haar cascade data
class HAARDetector
{
	public $image=null;
	public $objects=null;
	public $haardata=null;
	
	private $width=0;
	private $height=0;
	private $doCannyPruning=true;
	private $ratio=0.5;
	private $canvas=null;
	private $ret=null;
	private $scale=0;
	private $min_neighbors=0;
	private $scale_inc=0;
	private $increment=0;
	private $maxScale=0;
	private $canny=null;
	private $gray=null;
	private $img=null;
	private $squares=null;
	
	// constructor
	public function HAARDetector($haardata=null)
	{
		$this->haardata=$haardata;
	}
	
	// set image for detector along with scaling
	public function setImage(&$image,$scale=0.5)
	{
		$this->image=$image;
		// scale image
		$w=imagesx($image);
		$h=imagesy($image);
		$nw=floor($scale*$w);
		$nh=floor($scale*$h);
		if ($this->canvas!=null)
		{
			@imagedestroy($this->canvas);
			$this->canvas=null;
		}
		$this->canvas=imagecreatetruecolor($nw, $nh);
		imagecopyresampled($this->canvas, $this->image, 0, 0, 0, 0, $nw, $nh, $w, $h);
		$this->ratio=$scale;
		//return $this;
	}
	// Detector detect method to start detection
	public function detect($baseScale, $scale_inc, $increment, $min_neighbors, $doCannyPruning=false)
	{
		$this->doCannyPruning=$doCannyPruning;
		$this->ret=array();
		$gfound=false;
		$sizex=(int)$this->haardata['size1'];
		$sizey=(int)$this->haardata['size2'];
		$this->computeGray($this->canvas);
		$w=$this->width;
		$h=$this->height;
		$this->maxScale = min(($w)/$sizex,($h)/$sizey);
		$this->canny = null;
		if($this->doCannyPruning)
			$this->canny = $this->IntegralCanny($this->img);
		$this->scale=$baseScale;
		$this->min_neighbors=$min_neighbors;
		$this->scale_inc=$scale_inc;
		$this->increment=$increment;
		// detect loop
		while ($this->scale<=$this->maxScale)
		{
			$step=floor($this->scale*$sizex*$this->increment);
			$size=floor($this->scale*$sizex);
			$inv_size=1/($size*$size);
			for($i=0;$i<$w-$size;$i+=$step)
			{
				for($j=0;$j<$h-$size;$j+=$step)
				{
					// NOTE: currently cannyPruning does not give expected results (so use false in detect method) (maybe fix in the future)
					if($this->doCannyPruning)
					{
						$edges_density = $this->canny[$i+$size+($j+$size)*$w]+$this->canny[$i+$j*$w]-$this->canny[$i+($j+$size)*$w]-$this->canny[$i+$size+$j*$w];
						$d = $edges_density*$inv_size;
						//echo "$d,  ";
						if($d<20||$d>100)
							continue;
					}
					$pass=true;
					$slen=count($this->haardata['stages']);
					//echo "($i,$j) - ";
					for($s=0; $s<$slen;$s++)
					{
						$pass=$this->evalStage($s,$i,$j,$this->scale);
						if ($pass==false)
							break;
					}
					if ($pass) 
					{
						$gfound=true;
						$this->ret[]=array('x'=>$i,'y'=>$j,'width'=>$size,'height'=>$size);
					}
				}
			}
			$this->scale*=$this->scale_inc;
		}
		$this->objects=$this->merge($this->ret,$this->min_neighbors);
		return $gfound;  // returns true/false whether found at least sth
	}

	// Private functions for detection
	private function computeGray($image)
	{
		$this->gray=array();
		$this->img=array();
		$this->squares=array();
		$this->width=imagesx($image);
		$this->height=imagesy($image);
		$w=$this->width;
		$h=$this->height;
		$rm=30/100;
		$gm=59/100;
		$bm=11/100;
		for($i=0;$i<$w;$i++)
		{
			$col=0;
			$col2=0;
			for($j=0;$j<$h;$j++)
			{
				
				$rgb=imagecolorat($image,$i,$j);
				$ind=($j*$w+$i);
				$red = ($rgb >> 16) & 0xFF;
				$green = ($rgb >> 8) & 0xFF;
				$blue = $rgb & 0xFF;
				$grayc=($rm*$red +$gm*$green +$bm*$blue);
				$grayc2=$grayc*$grayc;
				$this->img[$ind]=$grayc;
				$this->gray[$ind]=($i>0?$this->gray[$i-1+$j*$w]:0)+$col+$grayc;
				$this->squares[$ind]=($i>0?$this->squares[$i-1+$j*$w]:0)+$col2+$grayc2;
				$col+=$grayc;
				$col2+=$grayc2;
			}
		}
	}
	
	private function IntegralCanny($grayImage)
	{
		$w=$this->width;
		$h=$this->height;
		
		// initialize array
		$canny = array();
		for($i=0;$i<$w;$i++)
			for($j=0;$j<$h;$j++)
				$canny[$i+$j*$w]=0;
				
		for($i=2;$i<$w-2;$i++)
			for($j=2;$j<$h-2;$j++)
			{
				$sum =0;
				$sum+=2*$grayImage[$i-2+($j-2)*$w];
				$sum+=4*$grayImage[$i-2+($j-1)*$w];
				$sum+=5*$grayImage[$i-2+($j+0)*$w];
				$sum+=4*$grayImage[$i-2+($j+1)*$w];
				$sum+=2*$grayImage[$i-2+($j+2)*$w];
				$sum+=4*$grayImage[$i-1+($j-2)*$w];
				$sum+=9*$grayImage[$i-1+($j-1)*$w];
				$sum+=12*$grayImage[$i-1+($j+0)*$w];
				$sum+=9*$grayImage[$i-1+($j+1)*$w];
				$sum+=4*$grayImage[$i-1+($j+2)*$w];
				$sum+=5*$grayImage[$i+0+($j-2)*$w];
				$sum+=12*$grayImage[$i+0+($j-1)*$w];
				$sum+=15*$grayImage[$i+0+($j+0)*$w];
				$sum+=12*$grayImage[$i+0+($j+1)*$w];
				$sum+=5*$grayImage[$i+0+($j+2)*$w];
				$sum+=4*$grayImage[$i+1+($j-2)*$w];
				$sum+=9*$grayImage[$i+1+($j-1)*$w];
				$sum+=12*$grayImage[$i+1+($j+0)*$w];
				$sum+=9*$grayImage[$i+1+($j+1)*$w];
				$sum+=4*$grayImage[$i+1+($j+2)*$w];
				$sum+=2*$grayImage[$i+2+($j-2)*$w];
				$sum+=4*$grayImage[$i+2+($j-1)*$w];
				$sum+=5*$grayImage[$i+2+($j+0)*$w];
				$sum+=4*$grayImage[$i+2+($j+1)*$w];
				$sum+=2*$grayImage[$i+2+($j+2)*$w];

				$canny[$i+$j*$w]=($sum/159);
		}
		
		// initialize array
		$grad = array();
		for($i=0;$i<$w;$i++)
			for($j=0;$j<$h;$j++)
				$grad[$i+$j*$w]=0;
		
		for($i=1;$i<$w-1;$i++)
			for($j=1;$j<$h-1;$j++)
			{
				$grad_x =-$canny[$i-1+($j-1)*$w]+$canny[$i+1+($j-1)*$w]-2*$canny[$i-1+$j*$w]+2*$canny[$i+1+$j*$w]-$canny[$i-1+($j+1)*$w]+$canny[$i+1+($j+1)*$w];
				$grad_y = $canny[$i-1+($j-1)*$w]+2*$canny[$i+($j-1)*$w]+$canny[$i+1+($j-1)*$w]-$canny[$i-1+($j+1)*$w]-2*$canny[$i+($j+1)*$w]-$canny[$i+1+($j+1)*$w];
				$grad[$i+$j*$w]=abs($grad_x)+abs($grad_y);
			}
		for($i=0;$i<$w;$i++)
		{
			$col=0;
			for($j=0;$j<$h;$j++)
			{
				$value= $grad[$i+$j*$w];
				$canny[$i+$j*$w]=($i>0?$canny[$i-1+$j*$w]:0)+$col+$value;
				$col+=$value;
			}
		}
		return $canny;
	}
	
	private function merge($rects, $min_neighbors)
	{
		$ret=array();
		$len=count($rects);
		for ($r=0;$r<$len;$r++)
			$ret[$r]=0;
		$nb_classes=0;
		$retour=array();
		for($i=0;$i<$len;$i++)
		{
			$found=false;
			for($j=0;$j<$i;$j++)
			{
				if($this->equals($rects[$j],$rects[$i]))
				{
					$found=true;
					$ret[$i]=$ret[$j];
				}
			}
			if(!$found)
			{
				$ret[$i]=$nb_classes;
				$nb_classes++;
			}
		}
		$neighbors=array();//new Array(nb_classes);
		$rect=array();//new Array(nb_classes);
		for($i=0;$i<$nb_classes;$i++)
		{
			$neighbors[$i]=0;
			$rect[$i]=array('x'=>0,'y'=>0,'width'=>0,'height'=>0);
		}
		for($i=0;$i<$len;$i++)
		{
			$neighbors[$ret[$i]]++;
			$rect[$ret[$i]]['x']+=$rects[$i]['x'];
			$rect[$ret[$i]]['y']+=$rects[$i]['y'];
			$rect[$ret[$i]]['height']+=$rects[$i]['height'];
			$rect[$ret[$i]]['width']+=$rects[$i]['width'];
		}
		for($i = 0; $i < $nb_classes; $i++ )
		{
			$n = $neighbors[$i];
			if( $n >= $min_neighbors)
			{
				$r=array('x'=>0,'y'=>0,'width'=>0,'height'=>0);
				$r['x'] = ($rect[$i]['x']*2 + $n)/(2*$n);
				$r['y'] = ($rect[$i]['y']*2 + $n)/(2*$n);
				$r['width'] = ($rect[$i]['width']*2 + $n)/(2*$n);
				$r['height'] = ($rect[$i]['height']*2 + $n)/(2*$n);
				$retour[]=$r;
			}
		}
		if ($this->ratio!=1) // scaled down, scale them back up
		{
			$ratio=1/$this->ratio;
			$len2=count($retour);
			for ($i=0;$i<$len2;$i++)
			{
				$rr=$retour[$i];
				$rr=array('x'=>$rr['x']*$ratio,'y'=>$rr['y']*$ratio,'width'=>$rr['width']*$ratio,'height'=>$rr['height']*$ratio);
				$retour[$i]=$rr;
			}
		}
		return $retour;
	}
	
	private function equals($r1, $r2)
	{
		$distance = floor($r1['width']*0.2);

		if($r2['x'] <= $r1['x'] + $distance &&
			   $r2['x'] >= $r1['x'] - $distance &&
			   $r2['y'] <= $r1['y'] + $distance &&
			   $r2['y'] >= $r1['y'] - $distance &&
			   $r2['width'] <= floor( $r1['width'] * 1.2 ) &&
			   floor( $r2['width'] * 1.2 ) >= $r1['width']) return true;
		if($r1['x']>=$r2['x']&&$r1['x']+$r1['width']<=$r2['x']+$r2['width']&&$r1['y']>=$r2['y']&&$r1['y']+$r1['height']<=$r2['y']+$r2['height']) return true;
		return false;
	}
	
	private function evalStage($s,$i,$j,$scale)
	{
		$sum=0.0;
		$threshold=(float)$this->haardata['stages'][$s]['thres'];
		$trees=$this->haardata['stages'][$s]['trees'];
		$tl=count($trees);
		for($t=0;$t<$tl;$t++)
		{
			$sum+=$this->evalTree($s,$t,$i,$j,$scale);
		}
		return (bool)($sum>$threshold);
	}
	
	private function evalTree($s,$t,$i,$j,$scale)
	{
		$features=$this->haardata['stages'][$s]['trees'][$t]['feats'];
		$cur_node_ind=0;
		$cur_node = $features[$cur_node_ind];
		while(true)
		{
			$where = $this->getLeftOrRight($s,$t,$cur_node_ind, $i, $j, $scale);
			if ($where==0)
			{
				if($cur_node['has_l']==true || $cur_node['has_l']==1)
				{
					return (float)$cur_node['l_val'];
				}
				else
				{
					$cur_node_ind=$cur_node['l_node'];
					$cur_node = $features[$cur_node_ind];
				}
			}
			else
			{
				if($cur_node['has_r']==true || $cur_node['has_r']==1)
				{

					return (float)$cur_node['r_val'];
				}
				else
				{
					$cur_node_ind=$cur_node['r_node'];
					$cur_node = $features[$cur_node_ind];
				}
			}
		}
	}
	
	private function getLeftOrRight($s,$t,$f, $i, $j, $scale) 
	{
		$sizex=(int)$this->haardata['size1'];
		$sizey=(int)$this->haardata['size2'];
		$w=floor($scale*$sizex);
		$h=floor($scale*$sizey);
		$ww=$this->width;
		$hh=$this->height;
		$inv_area=1/($w*$h);
		$grayImage=$this->gray;
		$squares=$this->squares;
		$total_x=$grayImage[$i+$w+($j+$h)*$ww]+$grayImage[$i+$j*$ww]-$grayImage[$i+($j+$h)*$ww]-$grayImage[$i+$w+$j*$ww];
		$total_x2=$squares[$i+$w+($j+$h)*$ww]+$squares[$i+$j*$ww]-$squares[$i+($j+$h)*$ww]-$squares[$i+$w+$j*$ww];
		$moy=$total_x*$inv_area;
		$vnorm=$total_x2*$inv_area-$moy*$moy;
		$feature=$this->haardata['stages'][$s]['trees'][$t]['feats'][$f];
		$rects=$feature['rects'];
		$nb_rects=count($rects);
		$threshold=(float)$feature['thres'];
		$vnorm=($vnorm>1)?sqrt($vnorm):1;

		$rect_sum=0.0;
		for($k=0;$k<$nb_rects;$k++)
		{
			$r = $rects[$k];
			$rx1=$i+floor($scale*(float)$r['x1']);
			$rx2=$i+floor($scale*((float)$r['x1']+(float)$r['y1']));
			$ry1=$j+floor($scale*(float)$r['x2']);
			$ry2=$j+floor($scale*((float)$r['x2']+(float)$r['y2']));
			$rect_sum+=floor((float)($grayImage[$rx2+$ry2*$ww]-$grayImage[$rx1+$ry2*$ww]-$grayImage[$rx2+$ry1*$ww]+$grayImage[$rx1+$ry1*$ww])*(float)$r['f']);
		}
		$rect_sum2=$rect_sum*$inv_area;
		return ($rect_sum2<$threshold*$vnorm)?0:1;
	}
}
?>