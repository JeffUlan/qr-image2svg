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
        function __construct(?string $inputPath, ?string $outputDir, ?int $paramSteps = null, int $paramThreshold = 127, ?string $paramThresholdChannel = null, bool $usePrefix = true) {
            $this->_setInputPath($inputPath); // 1. check if input path is proper and exists (if it is file)
            $this->_setOutputDir($outputDir); // 2. check if output dir is proper, in app scope and exists (if it is directory)
            $this->_setParamSteps($paramSteps); // 3. assign parameter for steps (can't be lower than smallest QR)
            $this->_setParamThreshold($paramThreshold); // 4. assign parameter for threshold (must be 0-255)

            if($usePrefix) $this->usePrefix = true;

            // 5. check and assign dimensions
            if($this->inputPath !== null) {
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
        protected function _retrieveImageSize() : array {
            list( $w, $h ) = 
                explode("x", shell_exec(
                    $this->_getPrefix() .
                    "identify -format \"%wx%h\" " . 
                    $this->inputPath
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
            $this->image['x'] = $w;
            $this->image['y'] = $h;
            shell_exec($this->_getPrefix()."convert {$this->path} -resize {$w}x{$h} -colorspace RGB {$this->inputPath}");
        }

        /**
         * Generates SVG image per input parameters.
         *
         * @return false|string
         */
        public function output() {
            if($this->inputPath == null) return false;
            if(!$this->image['optimized']) $this->_optimizeSizePerPixelsPerTile();
            $this->_setTilesData();
            $this->_setTilesValues();
            $this->_probeTilesForColor();
            $this->tilesData = null;
            return $this->generateSVG();
        }
    }

?>