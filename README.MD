![QR image-2-svg converter](https://repository-images.githubusercontent.com/457393906/2829ff00-7eb6-45f5-871f-be9eec7e0576)

# **QR image 2 SVG converter**

[DEMO APP](https://qrgen.xowergs.wirt16.bhlink.pl) *(not user friendly at the moment, looks messy, lots of reclicking)*

---

## **About**
Library meant to easily convert bitmap image-based QR codes to SVG equivalent form. Supports GD extension and ImageMagick.

### **Mechanics**
The concept here is to assign a grid over the QR image, find the center position of each generated tile and read the color value of the corresponding pixel. Then, basing on the assigned threshold value, resolve the SVG render with gathered data.

### **Story behind it**
The main reason to create this solution was my day-to-day job, where I have to handle graphic files for prepress matters. Sadly, many graphic designers still do not seem to understand that, often enough, bitmap images will offer a lower quality than their vector alternatives in print, not matter how far you will crank up the image resolution (it has to do with how images are processed by RIP systems to raster form). In the company I work for, we often introduce amendments to graphic files in order to receive the best possible quality in print. However, converting QR codes from bitmap to vector objects is not as easy or as fun as it should (no, contour tracing in many cases is not a viable option), so we were in need of yet another in-house solution to speed up yet another process. Came up with this package.

---

## **Usage**

* ### **Installation**
  * Composer
  ```sh
  composer require tei187/qr-image2svg:dev-master
  ```
  * Manual (package download)
  ```php
  <?php
    require_once( PATH_TO_EXTRACTED_PACKAGE . "/index.php" );
  ?>
  ```

* ### **Parameters**
  Let's assume we have a few params as following:
  * `$inputFile` - the accessible path leading to the image you want to convert,
  * `$outputDir` - the accessible path to directory in which output will be saved,
  * `$stepsPerAxis` - integer value describing how many tiles are there in a given axis of QR code,
  * `$threshold` - integer value describing the limit over which the tile will be treated as blank.
  * `$trim` - boolean value describing whether or not the image should be trimmed (remove white border from around QR).

* ### **GD**
  ```php
  <?php
    use tei187\QrImage2Svg\Converter\GD as GD;
    $t = new GD($inputFile, $outputDir, $stepsPerAxis, $threshold, $trim);
    echo $t->output();
    // saves the file to specified directory and also returns string with SVG.
  ?>
  ```
* ### **ImageMagick**
  ```php
  <?php
    use tei187\QrImage2Svg\Converter\ImageMagick as ImageMagick;
    $t = new ImageMagick($inputFile, $outputDir, $stepsPerAxis, $threshold, $trim, $prefix);
    echo $t->output();
    // saves the file to specified directory and also returns string with SVG.
  ?>
  ```
  IM's class has one more parameter in the constructor: `$prefix` being a prefix flag - if true it adds `'magick '` string to each command (I've found it to be environment specific thing, especially with older installments, but by default left `TRUE`). Also, do have in mind that in many cases IM can be slower than GD, because in GD version the image resource becomes assigned as a member in the class (`$this->image['obj']`), while IM continuously refers to the file.

---

## **Parameters & input requirements**
* ### **File**

  Input image files should be trimmed around the of QR code, so the image does not have any additional margin for quiet zone. Package supports JPG, GIF, PNG, BMP and WEBP file types.
  
  Furthermore, images should be prepared in standard fashion: filled/black tiles having average color values lower than threshold, blank/white tiles having higher.

* ### **Steps**

  Steps describe the amount of matrix tiles per X or Y axis of the QR code. In this scope, a tile is considered a single position in QR code's matrix. It's length (in bitmap image scope) is best described as the pixels width of vertical border in the top left part of the code. The amount of steps translates to `{image width} / {tile length}`, rounded up to integer value. **However**, it does not have to be this exact value - it is largely dependent on the input image's parameters and rendering, like antialiasing of the edges, compression level, etc. As such, **this value may have to be adjusted manually**.

  And additional mechanism is introduced within `suggestTilesQuantity()` method, where the processor looks up the width of the top left marker, using top border width as a designator. This length will always be equivalent to 7 QR tiles, so `{marker length} / 7` will return average length of a single tile expressed in pixels (float, for calculation). From there, the value is sent to parent method `_calculateVersion` where it's being checked and optimized.

  Using the marker's top border width gives a better estimate on the pixel-wise average length of a single tile. Using just the width of marker's left border may turn out to be insufficient and inexact.

  This method returns more solid, automatic assignment of steps, and perhaps may replace manual assignment in the future. For now, it has not been thorougly tested - all I can say is that a testing sample pool passed with flying colors, where the same pool did fail in some samples while using previous methodology.

* ### **Threshold**

  The probing of tiles is done by averaging color channel's values (average being `(R + G + B) / 3`). It should not matter with black and white QR codes, since filled and blank tiles should be very distinctly different. However, it may be more tricky with different hues, saturation and ligthness settings. Needs some manual input. For the time being, I suggest processing the images through by a CLUT or some such, until I expand a bit on the package.

* ### **Trimming**

  Trimming of the border is done through simulating a threshold of `127-255` and removing white margin. This may become an issue if your QR is inverted (bright fills with darker background) or both, tiles and background fills, have higher threshold than 127. In the second option, the method will return `FALSE` and not trim the image.

---

## **Future plans:**
* ~suggest grid step length automatically~ *(done, lookup `suggestTilesQuantity()` public method)*
* allow specific channels thresholds

## **Requirements**
* **PHP 7.4** or higher
* one of the following processors:
  * **GD** extension
  * **ImageMagick** 
  
  *(tested on **7.0.8-7 Q16 x64** and **7.1.0-2 Q16 x64**, did not seem to work properly on versions lower than 7.0 and any version of GraphicsMagick)*

## **Links**
* [QR codes article @ Wikipedia](https://en.wikipedia.org/wiki/QR_code)
* [ImageMagick Homepage](https://imagemagick.org/)
