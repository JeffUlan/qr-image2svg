<?php

namespace tei187\QR_image2svg\Converter;
use \tei187\QR_image2svg\Converter as Converter;

class ImageMagick extends Converter {
    private $withPrefix = true;
    private $pixelData = null;
    
    /**
     * Constructor.
     *
     * @param string|null $file Filename with extension that is going to be processed.
     * @param string|null $session Session directory.
     * @param integer|null $steps Steps equaling pixels width or height of one tile in QR code.
     * @param integer|null $threshold Threshold (of FF value) over which the tile is considered blank.
     * @param boolean $prefix Switch. On true places "magick" prefix in commands (enviroment-specific).
     */
    function __construct(string $file = null, string $session = null, int $steps = null, int $threshold = null, bool $prefix = true) {
        if(!is_null($file)) $this->setFile($file);
        if(!is_null($steps)) $this->setParamsStep($steps);
        if(!is_null($threshold)) $this->setParamsThreshold($threshold);
        $this->setPrefix($prefix);
    }

    /**
     * Sets prefix on true.
     *
     * @param boolean $prefix
     * @return self
     */
    public function setPrefix(bool $prefix = true) : self {
        $this->withPrefix = $prefix;
        return $this;
    }

    /**
     * Checks wether prefix is to be applied.
     *
     * @return string
     */
    private function checkPrefix() : string {
        $prefix = $this->withPrefix ? "magick " : "";
        return $prefix;
    }

    /**
     * Looks up color value of a pixel per coordinates.
     *
     * @param integer $x X coordinate.
     * @param integer $y Y coordinate.
     * @param null $img Leftover from different class.
     * @return array
     */
    protected function findColorAtIndex(int $x, int $y, $img = null) : array {
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

     /**
      * Uses ImageMagick's `sparse-color:` option to get info on each pixel's values.
      *
      * @return void
      */
    private function getPixelData() {
        $cmd = $this->checkPrefix() . "convert " . $this->getPath() . " sparse-color:";
        $this->pixelData = str_replace(
            " ", 
            "\r\n", 
            shell_exec($cmd)
        );
    }

    /**
     * Sets properties for image dimensions and returns as array.
     *
     * @return void
     */
    protected function getDimensions() : void {
        $cmd = $this->checkPrefix() . "identify -format \"%wx%h\" ". $this->getPath();
        list(
            $this->image['w'], 
            $this->image['h']
        ) = explode("x", shell_exec($cmd));
    }

    /**
     * Dummy output method.
     *
     * @return string SVG formatted QR code.
     */
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