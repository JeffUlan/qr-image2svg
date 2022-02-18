# QR image 2 SVG converter

## Description
Library meant to easily convert bitmap image-based QR codes to SVG equivalent form. Supports GD extension and ImageMagick.

## Story behind it
The main reason to create this solution was my day-to-day job, where I have to handle graphic files for prepress matters. Sadly, many graphic designers still do not seem to understand that, often enough, bitmap images will offer a lower quality than their vector alternatives in print (it has to do with how images are processed by RIP systems). In the company I work for, we often introduce amendments to graphic files in order to receive the best possible quality in print. However, converting QR codes from bitmap to vector objects is not as easy or as fun as it should, so we were in need of yet another in-house solution to speed up yet another process. Came up with this package.

## Usage
### Parameters
Let's assume we have a few params as following:
* `$inputFile` - the accessible path leading to the image you want to convert,
* `$outputDir` - the accessible path to directory in which output will be saved,
* `$stepsPerAxis` - integer value describing how many tiles are there in a given axis of QR code,
* `$threshold` - integer value describing the limit over which the tile will be treated as blank.
### GD
```php
<?php
  use tei187\QrImage2Svg\Converter\GD as GD;
  $t = new GD($inputFile, $outputDir, $stepsPerAxis, $threshold);
  echo $t->output();
  // saves the file to specified directory and also returns string with SVG.
?>
```
### ImageMagick
```php
<?php
  use tei187\QrImage2Svg\Converter\ImageMagick as ImageMagick;
  $t = new ImageMagick($inputFile, $outputDir, $stepsPerAxis, $threshold, null, true);
  echo $t->output();
  // saves the file to specified directory and also returns string with SVG.
?>
```
IM's class has more parameters for constructor, the 5th being `channel` (not supported currently, can be left `null`) and 6th being a prefix flag - if true it adds `'magick '` string to each command (found it to be environment specific thing, but by default left `TRUE`).

## Requirements
* **PHP 7.3** or higher
* one of the following processors:
  * **GD** extension
  * **ImageMagick** *(tested on 7.1.0-2 Q16 x64)*