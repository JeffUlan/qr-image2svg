<?php

namespace tei187\QR_image2svg\Resources;

/**
 * Utility class for handling MIME type restrictions and support.
 */
class MIME {
    /**
     * List of supported MIME types.
     *
     * @var array
     */
    static public $supportedTypes = [
        "image/gif",
        "image/jpeg",
        "image/jp2",
        "image/png",
        "image/bmp",
        "image/webp",
    ];

    /**
     * Retrieves MIME type from specific path.
     * 
     * @return false|string
     */
    static function getType(string $file) {
        if(file_exists($file)) {
            return mime_content_type($file);
        }
        return false;
    }

    /**
     * Checks support via self::$supportedTypes for specific MIME type.
     *
     * @param string $mime
     * @return boolean
     */
    static function checkSupport(string $mime) {
        if(!is_null($mime) && strlen(trim($mime)) != 0 && strlen(trim($mime)) !== false) {
            if(in_array(trim($mime), self::$supportedTypes)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Process file for MIME type support.
     *
     * @param string $file
     * @return boolean
     */
    static function check(string $file) : bool {
        $type = self::getType($file);
        if(!$type) {
            return false;
        }
        return self::checkSupport($type);
    }
    
}

?>