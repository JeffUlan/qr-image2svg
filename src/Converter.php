<?php
    namespace tei187\QrImage2Svg;
    use tei187\QrImage2Svg\Resources\MIME as MIME;

    abstract class Converter {
        /** @var null|string Path leading to file. */
        protected $inputPath;
        /** @var null|string Output path. */
        protected $outputDir;
        /**
         * Parameters used for processing.
         *  $this->params = [
         *   'steps'     => (int) Amount of tiles per axis.
         *   'threshold' => (int) Threshold level.
         *   'channel'   => (string) Specific channel to check threshold on.
         *  ]
         * @var int[]|string[]
         */
        protected $params = [
            'steps' => 21,
            'threshold' => 127,
            'channel' => 'red'
        ];
        /** @var null[]|int[]|\GdImage[]|resource[] Image-specific information and objects. */
        protected $image = [
            'x' => null,
            'y' => null,
            'obj' => null,
            'optimized' => false,
        ];
        /**
         * @var int[] Information concerning each QR tile. Keys: `'renderAt'`, `'tileMiddle'`, `'values'`.
         * `renderAt` being a 1:1-ratio-based position in new SVG. `tileMiddle` holds data of the middles of each QR tile. `values` holds data about the value found in the middle of each QR tile.
         */
        protected $tilesData = [];
        /** @var int[] Holds positions to render new QR tiles. */
        protected $filledTileMatrix = [];
        /**
         * @var null|int Width/height of the QR tile in pixels.
         */
        protected $pixelsPerTile = null;

        /**
         * Class constructor.
         *
         * @param string|null $inputPath Path leading to a image file.
         * @param string|null $outputDir Path to output the results.
         * @param integer|null $paramSteps Steps describing the quantity of tiles per axis in QR code.
         * @param integer $paramThreshold Threshold used to differentiate filled and empty QR tiles.
         * @param string|null $paramThresholdChannel Currently not used.
         */
        function __construct(?string $inputPath, ?string $outputDir, ?int $paramSteps = null, int $paramThreshold = 127, ?string $paramThresholdChannel = null) {
            $this->_setInputPath($inputPath); // 1. check if input path is proper and exists (if it is file)
            $this->_setOutputDir($outputDir); // 2. check if output dir is proper, in app scope and exists (if it is directory)
            $this->_setParamSteps($paramSteps); // 3. assign parameter for steps (can't be lower than smallest QR)
            $this->_setParamThreshold($paramThreshold); // 4. assign parameter for threshold (must be 0-255)
            
            if($this->inputPath !== null && $paramSteps !== null) $this->_optimizeSizePerPixelsPerTile();
        }

        /**
         * Checks if passed path is proper. If `isFile` flag is TRUE, also checks if the files MIME type is supported.
         * 
         * @param string $path Path to file.
         * @param boolean $isFile If TRUE, checks if the file is supported by the class.
         * @return boolean
         * @static
         */
        static protected function checkPath(string $path, bool $isFile = false) : bool {
            if(realpath($path) !== false) {
                return $isFile ? self::checkMIME($path) : is_dir($path);
            }
            return false;
        }

        /**
         * Checks MIME type of the file path specified by the argument. Returns TRUE if supported, FALSE if otherwise or on error.
         *
         * @param string $path Path to file.
         * @return boolean
         * @static
         */
        static protected function checkMIME(string $path) : bool {
            if(MIME::check($path) !== false) {
                return true;
            }
            return false;
        }

        /**
         * Assigns `$this->inputPath`, if `self::checkPath` returns TRUE. Otherwise, sets to `null`.
         *
         * @param string|null $inputPath
         * @return boolean
         */
        protected function _setInputPath(?string $inputPath = null) : bool {
            $this->image['optimized'] = false;
            if(!self::checkPath($inputPath, true)) {
                $this->inputPath = null;
                return false;
            }

            $this->inputPath = $inputPath;
            return true;
        }

        /**
         * Assigns `$this->outputDir`, if `self::checkPath` returns TRUE. Otherwise, sets to `null`.
         *
         * @param string|null $outputDir
         * @return boolean
         */
        protected function _setOutputDir(?string $outputDir = null) : bool {
            if(!self::checkPath($outputDir)) {
                $this->outputDir = null;
                return false;
            }

            $this->outputDir = $outputDir;
            return true;
        }

        /**
         * Checks if value is proper, and then assigns to `$this->params['steps']`.
         *
         * @param integer|null $steps
         * @return boolean
         */
        protected function _setParamSteps(?int $steps) : bool {
            if(!is_int($steps) || $steps < 21) return false;
            
            $this->params['steps'] = $steps;
            return true;
        }

        /**
         * Checks if value is proper, and then assigns to `$this->params['threshold']`.
         *
         * @param integer|null $steps
         * @return boolean
         */
        protected function _setParamThreshold(?int $v) : bool {
            // check if int: use average of pixel
            // check if array: channel key => pixel color by channel
            if(!is_int($v) || ($v < 0 || $v > 255)) return false;

            $this->params['threshold'] = $v;
            return true;
        }

        /**
         * Sets image dimensions in `$this->image`, if proper.
         *
         * @param array|null $dimensions
         * @return boolean
         */
        protected function _setImageDimensions(?array $dimensions) : bool {
            if(!is_null($dimensions) && is_array($dimensions) && count($dimensions) == 2) {
                $this->image['x'] = intval($dimensions[0]);
                $this->image['y'] = intval($dimensions[1]);
                return true;
            }

            // on fail
            list( $this->image['x'], $this->image['y'] ) = null;
            return false;
        }

        /**
         * Optimizes the image per the calculation of current dimensions and number of QR tiles per axis. 
         * 
         * If the outcome is not integer (or has a modulo of 1 higher than 0), resizes the image to optimal dimensions, so it is easier to process further on.
         *
         * @return void
         */
        protected function _optimizeSizePerPixelsPerTile() : void {
            if(!is_null($this->image['x']) && !is_null($this->image['y'])) {
                // calc pixels per tile
                $perTile = $this->image['x'] / $this->params['steps'];
                $this->pixelsPerTile = 
                  fmod($perTile, 1) !== 0 
                    ? intval(round($perTile, 0, PHP_ROUND_HALF_EVEN))
                    : intval($perTile);

                // rescale if values end up different
                if($perTile != $this->pixelsPerTile) {
                    $this->_rescaleImage(
                        $this->params['steps'] * $this->pixelsPerTile, 
                        $this->params['steps'] * $this->pixelsPerTile
                    );
                }
                $this->image['optimized'] = true;
            }
        }

        /**
         * Sets initial data of each tile, namely it's render position in new QR, tiles middle points and blank for values.
         *
         * @return void
         */
        protected function _setTilesData() : void {
            $this->tilesData = [];
            for($y = 0; $y < $this->params['steps']; $y++) {
                // each y axis
                for($x = 0; $x < $this->params['steps']; $x++) {
                    // each x axis
                    $this->tilesData[] = [
                        'renderAt' => [
                            'x' => $x, 
                            'y' => $y
                          ],
                        'tileMiddle' => [
                            'x' => intval(floor(($x * $this->pixelsPerTile) + ($this->pixelsPerTile / 2))),
                            'y' => intval(floor(($y * $this->pixelsPerTile) + ($this->pixelsPerTile / 2))),
                          ],
                        'values' => null
                    ];
                }
            }
        }

        /**
         * Checks if tile has a value not exceeding the threshold level. If so, passes render position to `filledTileMatrix` parameter as a filled QR tile.
         * @deprecated 1.0
         * @return void
         */
        protected function _createMatrix() : void {
            foreach($this->tilesData as $tile) {
                $val = is_array($tile['values']) 
                    ? array_sum($tile['values']) / count($tile['values']) 
                    : $tile['values'];
                
                if($val <= $this->threshold) 
                    $this->filledTileMatrix[] = $tile['renderAt'];
            }
        }

        /**
         * Generates final SVG file.
         *
         * @return string SVG
         */
        public function generateSVG() : string {
            $w = $this->params['steps'];
            $h = $w;
    
            $svgStr = NULL;
            $svgStr .= "<svg id='svg-drag' version=\"1.2\" baseProfile=\"full\" viewbox=\"0 0 $w $h\" style=\"shape-rendering: optimizespeed; shape-rendering: crispedges; min-width: ".($w*2)."px;\">\r\n";
            $svgStr .= "\t<g fill=\"#000000\">\r\n";
            foreach($this->filledTileMatrix as $tile) {
                $svgStr .= "\t\t<rect x=\"{$tile['x']}\" y=\"{$tile['y']}\" width=\"1\" height=\"1\" />\r\n";
            }
            $svgStr .= "\t</g>\r\n";
            $svgStr .= "</svg>";
        
            $path = $this->getOutputDir() !== null ? $this->getOutputDir() : null;
            file_put_contents($path."/output.svg", $svgStr);
            return $svgStr;
        }

        /** Returns input path. */
        public function getInputPath() { return $this->inputPath; }
        /** Returns output path. */
        public function getOutputDir() { return $this->outputDir; }

        abstract protected function _setTilesValues();
        abstract protected function _probeTilesForColor();
        abstract protected function _retrieveImageSize();
        abstract protected function _rescaleImage(int $w, int $h);
    }

?>