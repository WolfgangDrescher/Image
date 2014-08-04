<?php

/*!
---
Image.php
Wolfgang Drescher - wolfgangdrescher.ch
This class allows you to manipulate (e.g. resizing and rotating for thumbnails) output or saving images.
...
*/

class Image {
	
	private $file = null;
	private $img = null;
	private $data = array();
	private $chmod = 0775;
	
	public function __construct($file) {
		try {
			if(file_exists($file) AND is_file($file)) {
				$this->file = $file;
				$this->loadImage();
			} else {
				throw new Exception('File does not exist.');
			}
		} catch (Exception $e) {
			echo $e->getMessage();
		}
	}
	
	public function __destruct() {
		if(!$this->isError()) {
			if(is_resource($this->img)) {
				imagedestroy($this->img);
			}
			$this->img = null;
			unset($this->img);
			$this->tmp = null;
			unset($this->tmp);
		}
	}
	
	public function loadImage() {
		if($gis = getimagesize($this->file)) {
			$this->data['width'] = $gis[0];
			$this->data['height'] = $gis[1];
			$this->data['type'] = $gis[2];
			$this->data['mime'] = $gis['mime'];
			$this->data['channels'] = $gis['channels'];
			$this->data['bits'] = $gis['bits'];
			switch($this->data['type']) {
				case 1: $icf = 'imagecreatefromgif'; break;
				case 3: $icf = 'imagecreatefrompng'; break;
				case 2: default: $icf = 'imagecreatefromjpeg'; break;
			}
			ini_set('memory_limit', '128M');
			$this->img = $icf($this->file);
		} else {
			throw new Exception('File is not an Image.');
		}
		return $this;
	}
	
	public function getWidth() {
		return imagesx($this->img);
	}
	
	public function getHeight() {
		return imagesy($this->img);
	}
	
	public function outputJPEG() {
		return call_user_func_array('self::outputJPG', func_get_args());
	}
	
	public function outputJPG($quality = 100) {
		header('Content-Type: image/jpeg');
		@imagejpeg($this->img, null, $quality);
		return $this;
	}
	
	public function outputPNG($compression = 0) {
		header('Content-Type: image/png');
		@imagepng($this->img, null, $compression);
		return $this;
	}
	
	public function outputGIF() {
		header('Content-Type: image/gif');
		@imagegif($this->img);
		return $this;
	}
	
	public function saveJPEG() {
		return call_user_func_array('self::saveJPG', func_get_args());
	}
	
	public function saveJPG($path, $quality = 100) {
		@imagejpeg($this->img, $path, $quality);
		chmod($path, $this->chmod);
		return $this;
	}
	
	public function savePNG($path, $compression = 0) {
		@imagepng($this->img, $path, $compression);
		chmod($path, $this->chmod);
		return $this;
	}
	
	public function saveGIF($path) {
		@imagegif($this->img, $path);
		chmod($path, $this->chmod);
		return $this;
	}
	
	private function resize($w, $h, $tmpX, $tmpY, $tmpW, $tmpH, $bg = array()) {
		$tmp = imagecreatetruecolor($w, $h);
		if(!empty($bg)) imagefill($tmp, 0, 0, imagecolorallocate($tmp, $bg[0], $bg[1], $bg[2]));
		imagecopyresampled($tmp, $this->img, $tmpX, $tmpY, 0, 0, $tmpW, $tmpH, $this->getWidth(), $this->getHeight());
		$this->img = $tmp;
		return $this;
	}
	
	public function resizeDeform($width, $height) {
		return $this->resize($width, $height, 0, 0, $width, $height);
	}
	
	public function resizeFill($width, $height) {
		$cWidth = $this->getWidth() / $width;
		$cHeight = $this->getHeight() / $height;
		$c = ($cHeight < $cWidth)? $cHeight : $cWidth;
		$tmpWidth = $this->getWidth() / $c;
		$tmpHeight = $this->getHeight() / $c;
		$this->resize($width, $height, ($width-$tmpWidth)/2, ($height-$tmpHeight)/2, $tmpWidth, $tmpHeight);
		return $this;
	}
	
	public function resizeFit($width, $height, $bg = array(0, 0, 0)) {
		$cWidth = $this->getWidth() / $width;
		$cHeight = $this->getHeight() / $height;
		$c = ($cHeight > $cWidth)? $cHeight : $cWidth;
		$tmpWidth = $this->getWidth() / $c;
		$tmpHeight = $this->getHeight() / $c;
		$this->resize($width, $height, ($width-$tmpWidth)/2, ($height-$tmpHeight)/2, $tmpWidth, $tmpHeight, $bg);
		return $this;
	}
	
	public function resizeWidth($width) {
		$height = $width * $this->getHeight() / $this->getWidth();
		$this->resize($width, $height, 0, 0, $width, $height);
		return $this;
	}
	
	public function resizeHeight($height) {
		$width = $height * $this->getWidth() / $this->getHeight();
		$this->resize($width, $height, 0, 0, $width, $height);
		return $this;
	}
	
	public function resizeMax($width, $height) {
		$cWidth = $this->getWidth() / $width;
		$cHeight = $this->getHeight() / $height;
		$c = ($cHeight > $cWidth)? $cHeight : $cWidth;
		$width = $this->getWidth() / $c;
		$height = $this->getHeight() / $c;
		$this->resize($width, $height, 0, 0, $width, $height);
		return $this;
	}
	
	public function resizeLongEdge($length) {
		$cWidth = $this->getWidth() / $length;
		$cHeight = $this->getHeight() / $length;
		$c = ($this->getWidth() > $this->getHeight())? $cWidth : $cHeight;
		$width = $this->getWidth() / $c;
		$height = $this->getHeight() / $c;
		$this->resize($width, $height, 0, 0, $width, $height);
		return $this;
	}
	
	public function resizeScale($percent) {
		$width = $this->getWidth() * $percent / 100;
		$height = $this->getHeight() * $percent / 100;
		$this->resize($width, $height, 0, 0, $width, $height);
		return $this;
	}
	
	public function rotate($degrees, $bg = array(0,0,0)) {
		$degrees = $degrees * (-1);
		$this->img = imagerotate($this->img, $degrees, imagecolorallocate($this->img, $bg[0], $bg[1], $bg[2]));
		return $this;
	}
	
	public function rotateClockwise() {
		return $this->rotate(90);
	}
	
	public function rotateCounterClockwise() {
		return $this->rotate(-90);
	}
	
	public function rotateRight() {
		return $this->rotateClockwise();
	}
	
	public function rotateLeft() {
		return $this->rotateCounterclockwise();
	}
	
	public function rotateCw() {
		return $this->rotateClockwise();
	}
	
	public function rotateCCw() {
		return $this->rotateCounterclockwise();
	}
	
}