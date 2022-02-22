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
        function __construct(?string $inputPath, ?string $outputDir, ?int $paramSteps = null, int $paramThreshold = 127, bool $trimImage = null) {
            $this->_setInputPath($inputPath); // 1. check if input path is proper and exists (if it is file)
            $this->_setOutputDir($outputDir); // 2. check if output dir is proper, in app scope and exists (if it is directory)
            $this->_setParamSteps($paramSteps); // 3. assign parameter for steps (can't be lower than smallest QR)
            $this->_setParamThreshold($paramThreshold); // 4. assign parameter for threshold (must be 0-255)

            // 5. check and assign dimensions
            if($this->inputPath !== null) {
                if($trimImage) {
                    $this->_trimImage;
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
                $avg = round(($c['red'] + $c['green'] + $c['blue']) / 3, 1);
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
            $dst = imagecropauto($this->image['obj'], IMG_CROP_THRESHOLD, .78);
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
            $dst = imagecropauto($img, IMG_CROP_THRESHOLD, .78, 16777215);
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
    }

?>