<?php

namespace tei187\QR_image2svg\Converter;
use \tei187\QR_image2svg\Converter as Converter;

class ImageMagick extends Converter {
    private $withPrefix = false;
    private $pixelData = null;
    
    function __construct(string $file = null, string $session = null, int $steps = null, int $threshold = null, bool $prefix = false) {
        if(!is_null($file)) $this->setFile($file);
        if(!is_null($steps)) $this->setParamsStep($steps);
        if(!is_null($threshold)) $this->setParamsThreshold($threshold);
        $this->setPrefix($prefix);
    }

    public function setPrefix(bool $prefix = false) : self {
        $this->withPrefix = $prefix;
        return $this;
    }

    private function checkPrefix() : string {
        $prefix = $this->withPrefix ? "magick " : "";
        return $prefix;
    }

    protected function findColorAtIndex($x, $y, $img = null) : array {
        preg_match("/^$x,$y,[a-zA-Z()-,0-9]+/m", $this->pixelData, $pixel);
        preg_match("/(\d+)[,](\d+)[,](\d+)/", $pixel[0], $pixel);
        $color = [
            'red' => $pixel[1],
            'green' => $pixel[2],
            'blue' => $pixel[3],
        ];
        return $color;
    }

    /**
     * @todo SECURE FILE PATH INSERTION
     */

    private function getPixelData() {
        $cmd = $this->checkPrefix() . "convert " . $this->getPath() . " sparse-color:";
        $this->pixelData = str_replace(
            " ", 
            "\r\n", 
            shell_exec($cmd)
        );
    }

    protected function getDimensions() : void {
        $cmd = $this->checkPrefix() . "identify -format \"%wx%h\" ". $this->getPath();
        list(
            $this->image['w'], 
            $this->image['h']
        ) = explode("x", shell_exec($cmd));
    }

    public function output() {
        $this->getPixelData();
        $this->getDimensions();
        $this->setMaxSteps();
        $this->setMiddlePositions();
        $this->setFillByBlockMiddlePositions();

        return $this->generateSVG();
    }
}


?>