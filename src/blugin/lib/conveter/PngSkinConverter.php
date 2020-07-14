<?php

/*
 *
 *  ____  _             _         _____
 * | __ )| |_   _  __ _(_)_ __   |_   _|__  __ _ _ __ ___
 * |  _ \| | | | |/ _` | | '_ \    | |/ _ \/ _` | '_ ` _ \
 * | |_) | | |_| | (_| | | | | |   | |  __/ (_| | | | | | |
 * |____/|_|\__,_|\__, |_|_| |_|   |_|\___|\__,_|_| |_| |_|
 *                |___/
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author  Blugin team
 * @link    https://github.com/Blugin
 * @license https://www.gnu.org/licenses/lgpl-3.0 LGPL-3.0 License
 *
 *   (\ /)
 *  ( . .) ♥
 *  c(")(")
 */

declare(strict_types=1);

namespace blugin\lib\conveter;

use pocketmine\entity\InvalidSkinException;
use pocketmine\network\mcpe\protocol\types\SkinData;
use pocketmine\network\mcpe\protocol\types\SkinImage;
use pocketmine\utils\SingletonTrait;

class PngSkinConverter{
    use SingletonTrait;

    /**
     * @param resource $image
     *
     * @return SkinImage
     *
     * @throws InvalidSkinException
     */
    public function toSkinImage($image) : SkinImage{
        $height = imagesy($image);
        $width = imagesx($image);

        $skinData = "";
        for($y = 0; $y < $height; $y++){
            for($x = 0; $x < $width; $x++){
                $rgba = imagecolorat($image, $x, $y);
                $a = (127 - (($rgba >> 24) & 0x7F)) * 2;
                $r = ($rgba >> 16) & 0xff;
                $g = ($rgba >> 8) & 0xff;
                $b = $rgba & 0xff;
                $skinData .= chr($r) . chr($g) . chr($b) . chr($a);
            }
        }
        imagedestroy($image);
        return new SkinImage($height, $width, $skinData);
    }

    /**
     * @param SkinImage $skinImage
     *
     * @return resource|null
     */
    public function fromSkinImage(SkinImage $skinImage){
        $width = $skinImage->getWidth();
        $height = $skinImage->getHeight();
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
        imagesavealpha($image, true);

        foreach(array_chunk(array_map(function($val){
            return ord($val);
        }, str_split($skinImage->getData())), 4) as $index => $colorChunk){
            $colorChunk[] = 127 - intdiv(array_pop($colorChunk), 2);
            imagesetpixel($image, $index % $width, (int) ($index / $width), imagecolorallocatealpha($image, ...$colorChunk));
        }
        return $image;
    }

    /**
     * @param SkinData $data
     *
     * @return resource|null
     */
    public function fromSkinData(SkinData $data){
        return $this->fromSkinImage($data->getSkinImage());
    }
}