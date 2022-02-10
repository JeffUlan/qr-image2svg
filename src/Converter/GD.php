<?php
namespace tei187\QrImage2Svg\Converter;
use tei187\QrImage2Svg\Converter as Converter;

class GD extends Converter {
    /**
     * Sets properties for image dimensions and returns as array.
     *
     * @return bool|array
     */
    protected function getDimensions() {
        $path = "process/" . $this->session . "/" . $this->file;
        $d = getimagesize($path);
        if($d !== false) {
            $this->image['w'] = $d[0];
            $this->image['h'] = $d[1];
            return $d;
        }
        return false;
    }

    /**
     * Creates image resource.
     *
     * @return bool|object
     */
    protected function createImage() {
        $ext = pathinfo($this->getPath(), PATHINFO_EXTENSION);
        switch(strtolower($ext)) {
            case "png":     $i =  imagecreatefrompng($this->getPath()); break;
            case "jpg":     $i = imagecreatefromjpeg($this->getPath()); break;
            case "jpeg":    $i = imagecreatefromjpeg($this->getPath()); break;
            case "gif":     $i =  imagecreatefromgif($this->getPath()); break;
            case "webp":    $i = imagecreatefromwebp($this->getPath()); break;
            default;        $i = false;
        }
        $this->image['obj'] = $i;
        return $i;
    }

    /**
     * Resizes image, just not sure why I did that...
     *
     * @return void
     */
    private function resizeImage() {
        $r = $this->image['w'] / $this->image['h'];
        if( $this->image['w'] / $this->image['h'] > $r) {
            $new = [
                'w' => ceil($this->image['h'] * $r),
                'h' => $this->image['h']
            ];
        } else {
            $new = [
                'w' => $this->image['w'],
                'h' => ceil($this->image['w'] / $r)
            ];
        }

        $i = $this->createImage();

        if($i !== false) {
            $dst = imagecreatetruecolor($new['w'], $new['h']);
            imagecopyresampled(
                $dst, $i, 
                0, 0, 
                0, 0, 
                $new['w'], $new['h'], 
                $this->image['w'], $this->image['h']
            );
            $this->image = $new;
            return $dst;
        }
        return false;
    }

    /**
     * Find color values of a specific pixel in the image.
     *
     * @param integer $x Horizontal position.
     * @param integer $y Vertical position.
     * @param object $img Image resource.
     * @return array
     */
    protected function findColorAtIndex(int $x, int $y, ?object $img) : array {
        return imagecolorsforindex($img, imagecolorat($img, $x, $y));
    }

    /**
     * Dummy output method.
     *
     * @return string SVG formatted QR code.
     */
    public function output() : string {
        $this->createImage();
        $this->getDimensions();
        $this->setMaxSteps();
        $this->setMiddlePositions();
        $this->setFillByBlockMiddlePositions();
        return $this->generateSVG();
    }
}

?>