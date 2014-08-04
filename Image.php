<?php

/*!
---
Image.php
Wolfgang Drescher - wolfgangdrescher.ch
This class allows you to manipulate (e.g. resizing and rotating for thumbnails) output or saving images.
...
*/

class ImageException extends Exception {}

class Image {
	
	public static $throwExceptions = true;
	public static $chmod = 0755;
	
	private $error = false;
	private $file = null;
	private $img = null;
	private $data = array();
	
	public function __construct($file) {
		try {
			if(file_exists($file) AND is_file($file)) {
				$this->file = $file;
				$this->loadImage();
			} else {
				throw new ImageException('File `' . $file . '` does not exist.');
			}
		} catch (ImageException $e) {
			$this->error = true;
			if(self::$throwExceptions === true) {
				echo '<div class="alert alert-danger"><span class="glyphicon glyphicon-warning-sign fa fa-bug fa-spin"></span> '.htmlentities($e->getMessage()).'</div>';
			}
		}
	}
	
	public function __destruct() {
		if(!$this->isError()) {
			if(is_resource($this->img)) {
				imagedestroy($this->img);
			}
			$this->img = null;
			unset($this->img);
		}
	}
	
	public function isError() {
		return $this->error === false ? false : true;
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
			throw new ImageException('File `' . $this->file . '` is not an image.');
		}
		return $this;
	}
	
	public function getWidth() {
		return $this->isError() ? false : imagesx($this->img);
	}
	
	public function getHeight() {
		return $this->isError() ? false : imagesy($this->img);
	}
	
	public function outputJPEG() {
		return call_user_func_array('self::outputJPG', func_get_args());
	}
	
	public function outputJPG($quality = 100) {
		if($this->isError()) return false;
		header('Content-Type: image/jpeg');
		@imagejpeg($this->img, null, $quality);
		return $this;
	}
	
	public function outputPNG($compression = 0) {
		if($this->isError()) return false;
		header('Content-Type: image/png');
		imagesavealpha($this->img, true);
		@imagepng($this->img, null, $compression);
		return $this;
	}
	
	public function outputGIF() {
		if($this->isError()) return false;
		header('Content-Type: image/gif');
		@imagegif($this->img);
		return $this;
	}
	
	public function saveJPEG() {
		return call_user_func_array('self::saveJPG', func_get_args());
	}
	
	public function saveJPG($path, $quality = 100) {
		if($this->isError()) return false;
		@imagejpeg($this->img, $path, $quality);
		chmod($path, self::$chmod);
		return $this;
	}
	
	public function savePNG($path, $compression = 0) {
		if($this->isError()) return false;
		imagesavealpha($this->img, true);
		@imagepng($this->img, $path, $compression);
		chmod($path, self::$chmod);
		return $this;
	}
	
	public function saveGIF($path) {
		if($this->isError()) return false;
		@imagegif($this->img, $path);
		chmod($path, self::$chmod);
		return $this;
	}
	
	private function resize($w, $h, $tmpX, $tmpY, $tmpW, $tmpH, $bg = array()) {
		if($this->isError()) return false;
		$tmp = imagecreatetruecolor($w, $h);
		$bg = array_values($bg);
		if(count($bg) >= 3) {
			$num = isset($bg[3]) ? ($bg[3] > 1 ? $bg[3] / 100 : $bg[3]) : 1;
			$alpha = max(0, min(127, intval(floatval($num) * (-127) + 127)));
			imagefill($tmp, 0, 0, imagecolorallocatealpha($tmp, $bg[0], $bg[1], $bg[2], $alpha));
		}
		imagecopyresampled($tmp, $this->img, $tmpX, $tmpY, 0, 0, $tmpW, $tmpH, $this->getWidth(), $this->getHeight());
		$this->img = $tmp;
		return $this;
	}
	
	public function resizeDeform($width, $height) {
		if($this->isError()) return false;
		return $this->resize($width, $height, 0, 0, $width, $height);
	}
	
	public function resizeFill($width, $height) {
		if($this->isError()) return false;
		$cWidth = $this->getWidth() / $width;
		$cHeight = $this->getHeight() / $height;
		$c = ($cHeight < $cWidth)? $cHeight : $cWidth;
		$tmpWidth = $this->getWidth() / $c;
		$tmpHeight = $this->getHeight() / $c;
		return $this->resize($width, $height, ($width-$tmpWidth)/2, ($height-$tmpHeight)/2, $tmpWidth, $tmpHeight);
	}
	
	public function resizeFit($width, $height, $bg = array(0, 0, 0)) {
		if($this->isError()) return false;
		$cWidth = $this->getWidth() / $width;
		$cHeight = $this->getHeight() / $height;
		$c = ($cHeight > $cWidth)? $cHeight : $cWidth;
		$tmpWidth = $this->getWidth() / $c;
		$tmpHeight = $this->getHeight() / $c;
		return $this->resize($width, $height, ($width-$tmpWidth)/2, ($height-$tmpHeight)/2, $tmpWidth, $tmpHeight, $bg);
	}
	
	public function resizeWidth($width) {
		if($this->isError()) return false;
		$height = $width * $this->getHeight() / $this->getWidth();
		return $this->resize($width, $height, 0, 0, $width, $height);
	}
	
	public function resizeHeight($height) {
		if($this->isError()) return false;
		$width = $height * $this->getWidth() / $this->getHeight();
		return $this->resize($width, $height, 0, 0, $width, $height);
	}
	
	public function resizeMax($width, $height) {
		if($this->isError()) return false;
		$cWidth = $this->getWidth() / $width;
		$cHeight = $this->getHeight() / $height;
		$c = ($cHeight > $cWidth)? $cHeight : $cWidth;
		$width = $this->getWidth() / $c;
		$height = $this->getHeight() / $c;
		return $this->resize($width, $height, 0, 0, $width, $height);
	}
	
	public function resizeLongEdge($length) {
		if($this->isError()) return false;
		$cWidth = $this->getWidth() / $length;
		$cHeight = $this->getHeight() / $length;
		$c = ($this->getWidth() > $this->getHeight())? $cWidth : $cHeight;
		$width = $this->getWidth() / $c;
		$height = $this->getHeight() / $c;
		return $this->resize($width, $height, 0, 0, $width, $height);
	}
	
	public function resizeScale($percent) {
		if($this->isError()) return false;
		$width = $this->getWidth() * $percent / 100;
		$height = $this->getHeight() * $percent / 100;
		return $this->resize($width, $height, 0, 0, $width, $height);
	}
	
	public function rotate($degrees, $bg = array(0,0,0)) {
		if($this->isError()) return false;
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