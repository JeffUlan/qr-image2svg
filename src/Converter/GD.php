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
         * @param string|null $paramThresholdChannel Currently not used.
         */
        function __construct(?string $inputPath, ?string $outputDir, ?int $paramSteps = null, int $paramThreshold = 127, ?string $paramThresholdChannel = null) {
            $this->_setInputPath($inputPath); // 1. check if input path is proper and exists (if it is file)
            $this->_setOutputDir($outputDir); // 2. check if output dir is proper, in app scope and exists (if it is directory)
            $this->_setParamSteps($paramSteps); // 3. assign parameter for steps (can't be lower than smallest QR)
            $this->_setParamThreshold($paramThreshold); // 4. assign parameter for threshold (must be 0-255)

            // 5. check and assign dimensions
            if($this->inputPath !== null) {
                $this->_setImageDimensions( $this->_retrieveImageSize() ); // set image dimensions
                $paramSteps !== null && $this->_setParamSteps($paramSteps)  // optimize on constructor
                    ? $this->_optimizeSizePerPixelsPerTile() 
                    : null;
            }
        }

        private function _createImage(?string $path = null) {
            $path = is_null($path) && !is_null($this->inputPath) ? $this->inputPath : trim($path);

            $ext = pathinfo($this->inputPath, PATHINFO_EXTENSION);
            switch(strtolower($ext)) {
                case "png":   $img =  imagecreatefrompng($this->inputPath); break;
                case "jpg":   $img = imagecreatefromjpeg($this->inputPath); break;
                case "jpeg":  $img = imagecreatefromjpeg($this->inputPath); break;
                case "gif":   $img =  imagecreatefromgif($this->inputPath); break;
                case "webp":  $img = imagecreatefromwebp($this->inputPath); break;
                default;      $img = false;
            }
            $this->image['obj'] = $img;
            return $img;
        }

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
         * Undocumented function
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
                
                $ext = pathinfo($this->inputPath, PATHINFO_EXTENSION);
                switch(strtolower($ext)) {
                    case "png":   imagepng($this->image['obj'], $this->inputPath); break;
                    case "jpg":   imagejpeg($this->image['obj'], $this->inputPath); break;
                    case "jpeg":  imagejpeg($this->image['obj'], $this->inputPath); break;
                    case "gif":   imagegif($this->image['obj'], $this->inputPath); break;
                    case "webp":  imagewebp($this->image['obj'], $this->inputPath); break;
                    default;      return false;
                }

                return $this->image['obj'];
            }
            return false;            
        }

        public function output() {
            if($this->inputPath == null) return false;
            $this->_createImage();
            if(!$this->image['optimized']) $this->_optimizeSizePerPixelsPerTile();
            $this->_setTilesData();
            $this->_setTilesValues();
            $this->_probeTilesForColor();
            //$this->tilesData = null;
            return $this->generateSVG();
        }
    }

?>