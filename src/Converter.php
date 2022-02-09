<?php
namespace tei187\QR_image2svg;
use tei187\QR_image2svg\Resources\MIME as MIME;

abstract class Converter {
    /*protected $file = null;
    protected $session = null;
    protected $params = [
        'step' => 1,
        'threshold' => 127
    ];*/

    protected $file = "QR3.png";
    protected $session = "1";
    protected $params = [
        'step' => 24,
        'threshold' => 127,
    ];

    protected $image = [
        'w' => 0,
        'h' => 0,
        'obj' => null,
    ];
    protected $calculated = [
        'stepsInAxis' => [
            'x' => 0,
            'y' => 0,
        ],
        'blockMiddlePositions' => [

        ],
    ];
    protected $matrix = [];

    /**
     * Constructor.
     *
     * @param string|null $file Filename with extension that is going to be processed.
     * @param string|null $session Session directory.
     * @param integer|null $steps Steps equaling pixels width or height of one tile in QR code.
     * @param integer|null $threshold Threshold (of FF value) over which the tile is considered blank.
     */
    function __construct(string $file = null, string $session = null, int $steps = null, int $threshold = null) {
        if(!is_null($file)) $this->setFile($file);
        if(!is_null($steps)) $this->setParamsStep($steps);
        if(!is_null($threshold)) $this->setParamsThreshold($threshold);
    }

    /**
     * Checks if session folder exists.
     *
     * @return boolean
     */
    protected function checkSession() : bool {
        if(!is_null($this->session)) {
            if(file_exists("./process/" . $this->session)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns expected path to file.
     *
     * @return boolean|string
     */
    protected function getPath() {
        if(!is_null($this->file) && $this->checkSession()) {
            return "./process/" . $this->session . "/" . $this->file;
        }
        return false;
    }

    /**
     * Returns expected path to directory.
     *
     * @return boolean|string
     */
    protected function getDirPath() {
        if($this->checkSession()) {
            return "./process/" . $this->session . "/";
        }
        return false;
    }

    /**
     * Assigns file name to parameters.
     *
     * @param string $name Filename with extension. (case sensitive)
     * @return bool|\tei187\QR_image2svg\Converter\GD
     */
    protected function setFile(string $name) {
        if(!is_null($name)) {
            $this->file = $name;
            if(MIME::check($this->getPath()) !== false) {
                return $this;
            }
        }
        $this->file = null;
        return false;
    }

    /**
     * Calculates how many steps will be taken by each axis to probe the file (dimension / step length).
     *
     * @return void
     */
    protected function setMaxSteps() : void {
        $this->calculated['stepsInAxis']['x'] = floor($this->image['w'] / $this->params['step']);
        $this->calculated['stepsInAxis']['y'] = floor($this->image['h'] / $this->params['step']);
    }

    /**
     * Calculates middle position of each tile (based on dimension and max steps).
     *
     * @return void
     */
    protected function setMiddlePositions() {
        for($y = 1; $y <= $this->calculated['stepsInAxis']['y']; $y++) {
            for($x = 1; $x <= $this->calculated['stepsInAxis']['x']; $x++) {
                $this->calculated['blockMiddlePositions'][] = [
                    floor(($x * $this->params['step']) - ($this->params['step'] / 2)),
                    floor(($y * $this->params['step']) - ($this->params['step'] / 2))
                ];
            }
        }
    }

    /**
     * Assigns session ID.
     *
     * @param string|null $session
     * @return void
     */
    protected function setSession(string $session = null) {
        if(!is_null($session) && strlen(trim($session)) != 0) {
            $this->session = $session;
        } else {
            $this->session = null;
        }
        return $this;
    }

    /**
     * Sets parameters.
     *
     * @param integer|null $step
     * @param integer|null $threshold
     * @return self
     */
    public function setParams(int $step = null, int $threshold = null) : self {
        if(!is_null($step))      $this->setParamsStep($step);
        if(!is_null($threshold)) $this->setParamsThreshold($threshold);
        return $this;
    }

    /**
     * Sets steps parameter.
     *
     * @param integer $v
     * @return void
     */
    protected function setParamsStep(int $v) : void { $this->params['step'] = $v; } 

    /**
     * Sets threshold parameter.
     *
     * @param null|integer $v Value [0-255]. If null, substitutes for default 127.
     * @return void
     */
    protected function setParamsThreshold(int $v = null) : void { $this->params['threshold'] = !is_null($v) ? $v : 127; }

    /**
     * Sets tiles to be filled with color.
     *
     * @return void
     */
    protected function setFillByBlockMiddlePositions() : void {
        $this->matrix = [];
        if($this->image['obj'] !== false) {
            foreach($this->calculated['blockMiddlePositions'] as $c) {
                $rgb = $this->findColorAtIndex($c[0], $c[1], $this->image['obj']);
                if($this->decideOnColor($rgb)) {
                    $this->matrix[] = ( floor($c[0] / $this->params['step']) ) . "," . ( floor($c[1] / $this->params['step']) );
                }
            }
        }
    }

    /**
     * Generates SVG, based on input.
     *
     * @return string
     */
    protected function generateSVG() : string {
        $w = $this->calculated['stepsInAxis']['x'];
        $h = $this->calculated['stepsInAxis']['y'];

        $svgStr = NULL;
        $svgStr .= "<svg id='svg-drag' version=\"1.2\" baseProfile=\"full\" viewbox=\"0 0 $w $h\" style=\"shape-rendering: optimizespeed; shape-rendering: crispedges; min-width: ".($w*2)."px;\">\r\n";
        $svgStr .= "\t<g fill=\"#000000 icc-color(cmyk, 0, 0, 0, 1)\">\r\n";
        foreach($this->matrix as $fill) {
            $coords = explode(",", $fill, 2);
            $svgStr .= "\t\t<rect x=\"$coords[0]\" y=\"$coords[1]\" width=\"1\" height=\"1\" />\r\n";
        }
        $svgStr .= "\t</g>\r\n";
        $svgStr .= "</svg>";
    
        $path = $this->getDirPath() !== false ? $this->getDirPath() : null;
        file_put_contents($path."output.svg", $svgStr);
        //echo $svgStr;
        return $svgStr;
    }

    /**
     * Checks against threshold parameter, in order to qualify wether the position resembles a QR tile.
     *
     * @param array $color Array holding color values per channel ['red', 'green', 'blue'].
     * @param string|null $channel Channel to check against.
     * @return boolean
     */
    protected function decideOnColor(array $color, string $channel = null) : bool {
        if(is_null($channel)) {
            $avg = floor(( $color['red'] + $color['green'] + $color['blue'] ) / 3);
            if($avg >= $this->params['threshold']) {
                return false;
            }
            return true;
        } else {
            if($color[$channel] >= $this->params['threshold']) {
                return false;
            }
            return true;
        }
    }

    abstract protected function findColorAtIndex(int $x, int $y, ?object $img);
    abstract protected function getDimensions();
}

?>