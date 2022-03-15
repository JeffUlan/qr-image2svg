<?php
    namespace tei187\QrImage2Svg\Converter;
    use tei187\QrImage2Svg\Converter;

    class GD extends Converter {

        /**
         * Class constructor.
         *
         * @param string|null $inputPath Path leading to a image file.
         * @param string|null $outputDir Path to output the results.
         * @param integer|null $paramSteps Steps describing the quantity of tiles per axis in QR code.
         * @param integer $paramThreshold Threshold used to differentiate filled and empty QR tiles.
         * @param string|null $trimImage Trims white border on `TRUE`.
         */
        function __construct(?string $inputPath, ?string $outputDir, ?int $paramSteps = null, int $paramThreshold = 127, bool $trimImage = false) {
            $this->_setInputPath($inputPath); // 1. check if input path is proper and exists (if it is file)
            $this->_setOutputDir($outputDir); // 2. check if output dir is proper, in app scope and exists (if it is directory)
            $this->_setParamSteps($paramSteps); // 3. assign parameter for steps (can't be lower than smallest QR)
            $this->_setParamThreshold($paramThreshold); // 4. assign parameter for threshold (must be 0-255)

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
         * GD image creation per function or class param.
         *
         * @param string|null $path Path to file. If `null` takes value from `$this->inputPath`.
         * @return false|resource|\GdImage;
         */
        private function _createImage(?string $path = null) {
            $path = is_null($path) && !is_null($this->inputPath) ? $this->inputPath : trim($path);
            $img = self::_createFrom($path);
            $this->image['obj'] = $img;
            return $img;
        }

        static protected function _createFrom($path) {
            $ext = pathinfo($path, PATHINFO_EXTENSION);
            switch(strtolower($ext)) {
                case "png":   $img =  imagecreatefrompng($path); break;
                case "jpg":   $img = imagecreatefromjpeg($path); break;
                case "jpeg":  $img = imagecreatefromjpeg($path); break;
                case "gif":   $img =  imagecreatefromgif($path); break;
                case "webp":  $img = imagecreatefromwebp($path); break;
                default;      $img = false;
            }
            return $img;
        }

        /**
         * Queries each tile's middle point color values.
         *
         * @return void
         */
        protected function _setTilesValues() : void {
            foreach($this->tilesData as $k => $values) {
                $this->tilesData[$k]['values'] = imagecolorsforindex(
                    $this->image['obj'], 
                    imagecolorat(
                        $this->image['obj'], 
                        $values['tileMiddle']['x'], 
                        $values['tileMiddle']['y']
                    )
                );
            }
        }

        /**
         * Checks whether the tile should be filled or blank.
         *
         * @return void
         */
        protected function _probeTilesForColor() : void {
            foreach($this->tilesData as $k => $tile) {
                $c = $tile['values'];
                $avg = round(($c['red'] + $c['green'] + $c['blue']) / 3, 0);
                if($avg <= $this->params['threshold']) {
                    $this->filledTileMatrix[] = $tile['renderAt'];
                }
            }
        }

        /**
         * Check file for image size.
         *
         * @return array|null `0` being width, `1` being height.
         */
        protected function _retrieveImageSize() : ?array {
            $data = getimagesize($this->inputPath);
            if(!$data) return null;
            return [ $data[0], $data[1] ];
        }

         /**
         * Rescales image per passed arguments. For QR it should always be the same image.
         *
         * @param integer $w
         * @param integer $h
         * @return false|resource|\GdImage
         */
        protected function _rescaleImage(int $w, int $h) {
            $i = is_null($this->image['obj']) || $this->image['obj'] === false 
                ? $this->_createImage() 
                : $this->image['obj'];
            
            if($i !== false) {
                $dst = imagecreatetruecolor($w, $h);
                imagecopyresampled(
                    $dst, $i,
                    0, 0, 
                    0, 0, 
                    $w, $h,
                    $this->image['x'], $this->image['y']
                );
                $this->image['x'] = $w;
                $this->image['y'] = $h;
                $this->image['obj'] = $dst;
                
                $this->_saveImage();   

                return $this->image['obj'];
            }
            return false;            
        }

        /**
         * Saves image to disk.
         *
         * @return void|false
         */
        protected function _saveImage() {
            $ext = pathinfo($this->inputPath, PATHINFO_EXTENSION);
            switch(strtolower($ext)) {
                case "png":    imagepng($this->image['obj'], $this->inputPath); break;
                case "jpg":   imagejpeg($this->image['obj'], $this->inputPath); break;
                case "jpeg":  imagejpeg($this->image['obj'], $this->inputPath); break;
                case "gif":    imagegif($this->image['obj'], $this->inputPath); break;
                case "webp":  imagewebp($this->image['obj'], $this->inputPath); break;
                default;      return false;
            }
        }

        /**
         * Trims white image border, based on a simulated 200-255 RGB threshold.
         *
         * @return void
         */
        protected function _trimImage() {
            $dst = imagecropauto($this->image['obj'], IMG_CROP_THRESHOLD, .78, 16777215);
            if($dst !== false) {
                $this->image['obj'] = $dst;
                $this->_saveImage();
                $this->_setImageDimensions( $this->_retrieveImageSize() );
            }
        }

        /**
         * Trims white image border, based on a simulated 200-255 RGB threshold.
         *
         * @param string $path Path to image.
         * @return resource|\GdImage|false `FALSE` if filetype unsupported. GD Image `resource` or `GdImage` object if correct.
         * @static
         */
        static function trimImage($path) {
            $img = self::_createFrom($path);
            if(!imageistruecolor($img)) {
                $dims = [
                    'x' => imagesx($img),
                    'y' => imagesy($img),
                ];
                $dst = imagecreatetruecolor($dims['x'], $dims['y']);
                imagecopy($dst, $img, 0, 0, 0, 0, $dims['x'], $dims['y']);
                $dst = imagecropauto($dst, IMG_CROP_THRESHOLD, .78, 16777215);
            } else {
                $dst = imagecropauto($img, IMG_CROP_THRESHOLD, .78, 16777215);
            }

            if($dst !== false) {
                $ext = pathinfo($path, PATHINFO_EXTENSION);
                switch(strtolower($ext)) {
                    case "png":    imagepng($dst, $path); break;
                    case "jpg":   imagejpeg($dst, $path); break;
                    case "jpeg":  imagejpeg($dst, $path); break;
                    case "gif":    imagegif($dst, $path); break;
                    case "webp":  imagewebp($dst, $path); break;
                    default;      return false;
                }
            }
            return $dst;
        }


        private function _applyThreshold($threshold) {
            $img = $this->image['obj'];

            for($x = 0; $x < $this->image['x']; $x++) {
                for($y = 0; $y < $this->image['y']; $y++) {
                    $c = imagecolorsforindex($img, imagecolorat($img, $x, $y));
                    unset($c['alpha']);
                    $avg = round(array_sum($c) / count($c), 0);
                    if($avg > $threshold) {
                        imagesetpixel($img, $x, $y, imagecolorallocate($img, 255, 255, 255));
                    } else {
                        imagesetpixel($img, $x, $y, imagecolorallocate($img, 0, 0, 0));
                    }
                }
            }

            $img = imagecropauto($img, IMG_CROP_WHITE);
            return $img;
        }

        /**
         * Return suggested tiles quantity for tile grid. Should be treated more as a relative number, rather than absolute.
         *
         * @return false|int `Integer` with grid tiles per axis. `FALSE` if threshold did not work, outcome is invalid by QR specification or there is a mathematic discrepancy.
         */
        public function suggestTilesQuantity() {
            // preparation
            if(is_null($this->image['obj']))
                $this->_createImage($this->inputPath);

            imagefilter($this->image['obj'], IMG_FILTER_GRAYSCALE);
            
            $img = $this->_applyThreshold(127);
            
            if($img === false)
                return false;

            $dims = [ 
                imagesx($img), 
                imagesy($img),
            ];
            sort( $dims, SORT_ASC );
            
            if($dims[0] == 0)
                return false;

            $maxTileLength = ceil($dims[0] / 20); // smallest QR can have 21 tiles per axis (version 1). Dividing by 20 instead in order to have so margin for antialiasing.
            $maxMarkerLength = ($maxTileLength * 7) + 1; // marker is 7x7 tiles, so multiply length of minimal by 7 and add one pixel for change
            // its done this way so the script will stop iterating the for-loop after a certain point, meaning:
            // if the threshold limit is not found by then, the image is corrupt, not a standard QR or threshold parameter was not properly assigned

            // seeking marker edge
            $minimalTile = floor($dims[0] / 177); // minimal tile of a border
            $found = false;
            $i = 0;

            for($y = 0; $found == false; $y++) {
                if($y == $dims[0]) {
                    break;
                }
                
                $p = $this->__seekBorderEnd($img, $y, $minimalTile, $maxMarkerLength);
                if($p[0]) {
                    $i = $p[1];
                    $found = true;
                    break;
                }
            }
            
            if($i == 0 || !$found) {
                return false;
            } else {
                // count interruptions on timing line
                $j = $y + ceil($i - (($i / 7) / 2));
                //$j = ceil($i - (($i / 7) / 2)); // middle height of right-bottom corner of marker in top-left corner
                $k = $i; // x position outside the marker on right side border
                $interruptions = 0;
                $last = null;
                $current = null;

                for( $k; $k < $dims[0]; $k++) {
                    $c = imagecolorsforindex($img, imagecolorat($img, $k, $j));
                    $c['alpha'] = 0;
                    $sum = array_sum($c);

                    if(is_null($last) && is_null($current)) {
                        $last = ($sum / 3) < 127 ? null : $sum / 3; // prevents counting border-background on first rounds as an interruption
                        continue;
                    }

                    $current = $sum / 3;
                    if($last !== $current) {
                        $interruptions++;
                    }
                    $last = $current;
                }

                if($last == 255) {
                    $interruptions--; // prevent white untrimmed area on right side treated as interruption, last has to be filled with black
                }

                // validate marker length
                $check = [
                    round($dims[0] / ($interruptions + 14)),
                    round($i / 7)
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
         * Generates SVG image per input parameters.
         *
         * @return false|string
         */
        public function output() {
            if($this->inputPath == null) return false;
            $this->_createImage();
            if(!$this->image['optimized']) $this->_optimizeSizePerPixelsPerTile();
            $this->_setTilesData();
            $this->_setTilesValues();
            $this->_probeTilesForColor();
            $this->tilesData = null;
            return $this->generateSVG();
        }

        /**
         * Find border length of corner marker.
         *
         * @param resource|\GdImage $img Image resource to test.
         * @param integer $y Height of a row to parse.
         * @param integer $minimalTile Minimal tile length ~(width / 177);
         * @param integer $maxMarkerLength Max corner marker length.
         * @return array [bool,int]
         */
        private function __seekBorderEnd($img, int $y, int $minimalTile, int $maxMarkerLength ) {
            $started = false;
            $found = false;
            $w = 0;
            $b = 0;

            for( $x = 0; $x <= $maxMarkerLength; $x++ ) {
                $c = imagecolorsforindex($img, imagecolorat($img, $x, $y));
                $c['alpha'] = 0;
                if(round(array_sum($c) / 3, 0) == 0 && !$started) {
                    $started = true;
                }
                if(round(array_sum($c) / 3, 0) == 0) {
                    $b++;
                }
                if(round(array_sum($c) / 3, 0) > 127) {
                    $w++;
                    if($started && $x >= $minimalTile * 7) {
                        if($w <= $b) {
                            $found = true;
                            unset($c);
                            break;
                        } else {
                            break;
                        }
                    }
                }
            }

            return [ $found, $x ];
        }
    }

?>