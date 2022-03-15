<?php

    namespace tei187\QrImage2Svg\Converter;
    use \tei187\QrImage2Svg\Converter as Converter;

    class ImageMagick extends Converter {
        private $usePrefix = true;

        /**
         * Class constructor.
         *
         * @param string|null $inputPath Path leading to a image file.
         * @param string|null $outputDir Path to output the results.
         * @param integer|null $paramSteps Steps describing the quantity of tiles per axis in QR code.
         * @param integer $paramThreshold Threshold used to differentiate filled and empty QR tiles.
         * @param string|null $paramThresholdChannel Currently not used.
         * @param boolean $usePrefix Boolean flag if `magick` command prefix should be used (environment specific).
         */
        function __construct(?string $inputPath, ?string $outputDir, ?int $paramSteps = null, int $paramThreshold = 127, bool $trimImage = false, bool $usePrefix = true) {
            $this->_setInputPath($inputPath); // 1. check if input path is proper and exists (if it is file)
            $this->_setOutputDir($outputDir); // 2. check if output dir is proper, in app scope and exists (if it is directory)
            $this->_setParamSteps($paramSteps); // 3. assign parameter for steps (can't be lower than smallest QR)
            $this->_setParamThreshold($paramThreshold); // 4. assign parameter for threshold (must be 0-255)

            if($usePrefix) $this->usePrefix = true;

            // 5. check and assign dimensions
            if($this->inputPath !== null) {
                if($trimImage) {
                    $this->_trimImage();
                }
                $this->_setImageDimensions( $this->_retrieveImageSize() ); // set image dimensions
                $paramSteps !== null && $this->_setParamSteps($paramSteps)  // optimize on constructor
                    ? $this->_optimizeSizePerPixelsPerTile() 
                    : null;
            }
        }

        /**
         * Check file for image size.
         *
         * @return array|null `0` being width, `1` being height.
         */
        protected function _retrieveImageSize(string $path = null) {
            if($path == null) {
                $path = $this->inputPath;
            }

            list( $w, $h ) = 
                explode("x", shell_exec(
                    $this->_getPrefix() .
                    "identify -format \"%wx%h\" " . 
                    $path
                ), 2);
                
            $output = strlen(trim($w)) != 0 && strlen(trim($h)) != 0 
                ? [ $w, $h ] 
                : null;

            return $output;
        }

        /**
         * Set flag wether the `magick` command prefix should be used (environment specific).
         *
         * @param boolean $flag
         * @return self
         */
        public function _setPrefixUse(bool $flag = true) : self {
            $this->usePrefix = $flag;
            return $this;
        }

        /**
         * Returns prefix or lack of it.
         *
         * @return string
         */
        private function _getPrefix() : string {
            return $this->usePrefix ? "magick " : "";
        }

        /**
         * Queries each tile's middle point color values.
         *
         * @return void
         */
        protected function _setTilesValues() : void {
            // generate IM-specific syntax for each tile
            $commandParts = [];
            foreach($this->tilesData as $k => $values) {
                $commandParts[] = "%[pixel:s.p{" . $values['tileMiddle']['x'] . "," . $values['tileMiddle']['y'] . "}]";
            }
            // chunk IM-specific syntax array (by low value of 50, due to shell limit)
            $commandChunks = array_chunk($commandParts, 50);
            unset($commandParts);
            
            // getting output per chunk and merging it to $output variable
            $output = "";
            foreach($commandChunks as $chunk) {
                $part = implode("..", $chunk);
                $output .= shell_exec($this->_getPrefix() . "identify -format \(" . $part . "\) " . $this->inputPath) ."..";
            }
            unset($commandChunks, $chunk);

            // mapping output
            $temp = array_map(
                function($v) {
                    if($v !== null && strlen(trim($v)) > 0) {
                        preg_match_all("/\d+/", $v, $match);
                        return $match[0];
                    }
                }, explode("..", trim($output, "."))
            );
            unset($output, $match);

            // save values
            foreach($temp as $k => $v) {
                $this->tilesData[$k]['values'] = $v;
            }
            unset($temp, $k, $v);
        }

        /**
         * Checks whether the tile should be filled or blank.
         *
         * @return void
         */
        protected function _probeTilesForColor() {
            $colorType = $this->_getColorType();
            $passedAs = strpos($colorType, "rgb")  !== false ? "rgb"  : "undefined";
            $passedAs = strpos($colorType, "gray") !== false ? "gray" : $passedAs;
            $passedAs = strpos($colorType, "cmyk") !== false ? "cmyk" : $passedAs;

            foreach($this->tilesData as $tile) {
                switch( $passedAs ) {
                    case 'gray': // assume grayscale
                        $color = $tile['values'][0];
                        $avg = round($color, 1);
                        break;
                    case 'rgb': // assume rgb
                        $color = [
                            'red'   => $tile['values'][0],
                            'green' => $tile['values'][1],
                            'blue'  => $tile['values'][2]
                        ];
                        $avg = round(array_sum($color) / 3, 1);
                        break;
                    case 'cmyk':
                        $color = [
                            'cyan'    => $tile['values'][0],
                            'magenta' => $tile['values'][1],
                            'yellow'  => $tile['values'][2],
                            'black'   => $tile['values'][3],
                        ];
                        $colorRGB = [
                            'red'   => 255 * (1 - ($color['cyan'] / 100))    * (1 - ($color['black'] / 100)),
                            'green' => 255 * (1 - ($color['magenta'] / 100)) * (1 - ($color['black'] / 100)),
                            'blue'  => 255 * (1 - ($color['yellow'] / 100))  * (1 - ($color['black'] / 100))
                        ];
                        $avg = round(array_sum($colorRGB) / 3, 1);
                        break;
                    default:
                        // some fail here...
                        $color = false;
                        $avg = 255;
                }

                $avg <= $this->params['threshold'] 
                    ? $this->filledTileMatrix[] = $tile['renderAt']
                    : null;
                
            }
        }

        /**
         * Queries the image for palette type.
         *
         * @return string
         */
        protected function _getColorType() : string {
            $t = explode(
                "(",
                shell_exec($this->_getPrefix()."identify -format %[pixel:s.p{1,1}] ".$this->inputPath)
            )[0];
        
            return $t;
        }

        /**
         * Rescales image per passed arguments. For QR it should always be the same image.
         *
         * @param integer $w
         * @param integer $h
         * @return void
         */
        protected function _rescaleImage(int $w, int $h) {
            shell_exec($this->_getPrefix()."convert {$this->inputPath} -resize {$w}x{$h} -colorspace RGB {$this->inputPath}");
            $this->_setImageDimensions([$w, $h]);
        }

        /**
         * Trims white image border, based on a simulated 200-255 RGB threshold.
         *
         * @return boolean
         */
        protected function _trimImage() : bool {
            $g = shell_exec($this->_getPrefix()."convert {$this->inputPath} -color-threshold \"RGB(200,200,200)-RGB(255,255,255)\" -format \"%@\" info:");
            $c = explode("+", $g);
            $c = array_merge( 
                explode("x", $c[0]),
                array($c[1], $c[2]) 
            );
            if(count($c) == 4 && ($c[0] != 0 && $c[1] != 0)) {
                shell_exec($this->_getPrefix()."convert {$this->inputPath} -crop {$c[0]}x{$c[1]}+{$c[2]}+{$c[3]} {$this->inputPath}");
                $this->_setImageDimensions( $this->_retrieveImageSize() );
                return true;
            }
            return false;
        }

        /**
         * Return suggested tiles quantity for tile grid. Should be treated more as a relative number, rather than absolute.
         *
         * @return false|int
         */
        public function suggestTilesQuantity() {
            // set threshold image
            $path = rtrim(trim($this->outputDir), "\\/") . "/temp.png";
            shell_exec($this->_getPrefix()."convert {$this->inputPath} -color-threshold \"RGB({$this->params['threshold']},{$this->params['threshold']},{$this->params['threshold']})-RGB(255,255,255)\" -trim {$path}");

            // get dimensions
            $dims = $this->_retrieveImageSize($path);
            $dims = is_array($dims) 
                ? $dims 
                : [ 0, 0 ];
            sort( $dims, SORT_ASC );

            if($dims[0] == 0)
                return false;
            
            $maxTileLength = ceil($dims[0] / 20);
            $maxMarkerLength = ($maxTileLength * 7) + 1;
            $minimalTile = floor($dims[0] / 177);

            // find corner
            $found = false;
            $f = 0;
            for($y = 0; $found == false; $y++) {
                if($y == $dims[0]) {
                    break;
                }

                $data = $this->__probeImageRow($path, $y, $maxMarkerLength);
                $border = $this->__seekBorderEnd($data, $minimalTile);
                if($border[0]) {
                    $f = $border[1];
                    $found = true;
                    break;
                }
            }
            
            if($f == 0 || !$found)
                return false;
            else {
                $j = abs($y - ceil($f - (($f / 7) / 2)));
                $k = $f;
                $points = [];

                for($k; $k < $dims[0]; $k++) { $points[] = "%[pixel:s.p{" . $k . "," . $j . "}]"; }
                $pointsChunks = array_chunk($points, 50); unset($points); $output = "";
                foreach($pointsChunks as $chunk) {
                    $part = implode("..", $chunk);
                    $output .= shell_exec($this->_getPrefix() . "identify -format \(" . $part . "\) ". $path) . "..";
                }
                unset($pointsChunks, $chunk);
                $temp = array_map(
                    function($v) {
                        if($v !== null && strlen(trim($v)) > 0) {
                            preg_match_all("/\d+/", $v, $match);
                            return $match[0];
                        }
                    }, explode("..", trim($output, "."))
                );
                unset($output, $match);

                $interruptions = 0;
                $last = null;
                $current = null;
                foreach($temp as $k => $v) {
                    if(is_null($last) && is_null($current)) {
                        $last = $v[0] < 127 ? null : $v[0];
                        continue;
                    }

                    $current = $v[0];
                    if($last !== $current) {
                        $interruptions++;
                    }
                    $last = $current;
                }

                if($last > 127) {
                    $interruptions--;
                }

                $check = [
                    round($dims[0] / ($interruptions + 14)),
                    round($f / 7)
                ];

                if($check[0] != $check[1]) {
                    return false;
                }

                $result = ($interruptions + 14) > 177 
                    ? 177 
                    : $interruptions + 14;
                $result = $result < 21 
                    ? 21 
                    : $result;
                return $result;
            }
        }

        /**
         * Trims white image border, based on a simulated 200-255 RGB threshold. Overwrites input.
         *
         * @param string $path Path to image.
         * @param boolean $prefix Use "magick" prefix on `TRUE`, null on `FALSE`.
         * @return boolean
         * @static
         */
        static function trimImage(string $path, bool $prefix = true) : bool {
            $prefix = $prefix === true ? "magick " : null;
            $g = shell_exec($prefix."convert {$path} -color-threshold \"RGB(200,200,200)-RGB(255,255,255)\" -format \"%@\" info:");
            $c = explode("+", $g);
            $c = array_merge( 
                explode("x", $c[0]),
                array($c[1], $c[2]) 
            );
            if(count($c) == 4 && ($c[0] != 0 && $c[1] != 0)) {
                shell_exec($prefix."convert {$path} -crop {$c[0]}x{$c[1]}+{$c[2]}+{$c[3]} {$path}");
                return true;
            }
            return false;
        }

        /**
         * Generates SVG image per input parameters.
         *
         * @return false|string
         */
        public function output() {
            if($this->inputPath == null) 
                return false;
            if(!$this->image['optimized']) 
                $this->_optimizeSizePerPixelsPerTile();
            $this->_setTilesData();
            $this->_setTilesValues();
            $this->_probeTilesForColor();
            $this->tilesData = null;
            return $this->generateSVG();
        }

        /**
         * Find border length of corner marker.
         *
         * @param array $data Pixel data for specific row.
         * @param integer $minimalTile Minimal tile length ~(width / 177)
         * @return array [bool,int]
         */
        private function __seekBorderEnd(array $data, int $minimalTile) {
            $started = false;
            $found = false;
            $w = 0;
            $b = 0;

            foreach($data as $x => $v) {
                if($v[0] == 0 && !$started)
                    $started = true;
                if($v[0] == 0)
                    $b++;
                if($v[0] > 127) {
                    $w++;
                    if($started && $x >= $minimalTile * 7) {
                        if($w <= $b) {
                            $found = true;
                            break;
                        } else {
                            break;
                        }
                    }
                }
            }

            return [ $found, $x ];
        }

        /**
         * Returns specific row's pixels data.
         *
         * @param string $path Path to image file after threshold application.
         * @param int $y Height of a row to parse.
         * @param int $maxMarkerLength Max corner marker length.
         * @return array
         */
        private function __probeImageRow(string $path, int $y, int $maxMarkerLength) {
            // - list cmd parts
            $points = [];
            for($x = 0; $x <= $maxMarkerLength; $x++) {
                $points[] = "%[pixel:s.p{". $x ."," . $y . "}]";
            }
            $pointsChunks = array_chunk($points, 50);
            unset($points);

            // - chunk to limit
            $output = "";
            foreach($pointsChunks as $chunk) {
                $part = implode("..", $chunk);
                $output .= shell_exec($this->_getPrefix() . "identify -format \(" . $part . "\) " . $path) ."..";
            }
            unset($pointsChunks, $chunk);

            // - parse output
            $row = array_map(
                function($v) {
                    if($v !== null && strlen(trim($v)) > 0) {
                        preg_match_all("/\d+/", $v, $match);
                        return $match[0];
                    }
                }, explode("..", trim($output, "."))
            );
            unset($output, $match);

            return $row;
        }
    }

?>