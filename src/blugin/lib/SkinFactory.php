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

namespace blugin\lib;

use pocketmine\entity\InvalidSkinException;
use pocketmine\entity\Skin;
use pocketmine\network\mcpe\protocol\types\SkinData;
use pocketmine\network\mcpe\protocol\types\SkinImage;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\SingletonTrait;
use pocketmine\utils\TextFormat;
use pocketmine\utils\VersionString;

class SkinFactory extends PluginBase{
    use SingletonTrait;

    /** @var int[][] */
    public const ACCEPTED_SKIN_SIZE_MAP = [
        64 * 32 * 4 => [64, 32],
        64 * 64 * 4 => [64, 64],
        128 * 128 * 4 => [128, 128]
    ];

    /** @var SkinData[] */
    private $skinData = [];

    /**
     * Called when the plugin is loaded, before calling onEnable()
     */
    public function onLoad() : void{
        $this->getServer()->getLogger()->debug(TextFormat::AQUA . "[Blugin/lib] SkinManagerLib is loaded");
    }

    /**
     * @param string $key
     * @param string $skinData
     * @param string $geometryData
     */
    public function registerFromData(string $key, string $skinData, string $geometryData) : void{
        $resourcePatch = json_encode(["geometry" => ["default" => self::getGeometryNameFromData(json_decode($geometryData, true))]]);
        $this->register($key, new SkinData($key, $resourcePatch, SkinImage::fromLegacy($skinData), [], null, $geometryData));
    }

    /**
     * @param string   $key
     * @param SkinData $skinData
     */
    public function register(string $key, SkinData $skinData) : void{
        $this->skinData[$key] = $skinData;
    }

    /**
     * @param string $key
     *
     * @return null|SkinData
     */
    public function get(string $key) : ?SkinData{
        return $this->skinData[$key] ?? null;
    }

    /**
     * @param string $filename
     * @param bool   $checkValid = false
     *
     * @return string Skindata
     *
     * @throws InvalidSkinException
     */
    public function png2skindata(string $filename, bool $checkValid = false) : string{
        $image = imagecreatefrompng($filename);
        $width = imagesx($image);
        $height = imagesy($image);
        $size = $width * $height * 4;
        if($checkValid && !isset(self::ACCEPTED_SKIN_SIZE_MAP[$size]))
            throw new InvalidSkinException("Invalid skin data size $size bytes (allowed sizes: " . implode(", ", Skin::ACCEPTED_SKIN_SIZES) . ")");

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
        return $skinData;
    }

    /**
     * @param string $skinData
     * @param int    $width
     * @param int    $height
     * @param bool   $checkValid = false
     *
     * @return resource|null
     */
    public function skindata2png(string $skinData, int $width, int $height, bool $checkValid = false){
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
        imagesavealpha($image, true);

        $firstChunk = null;
        $valid = false;
        foreach(array_chunk(array_map(function($val){
            return ord($val);
        }, str_split($skinData)), 4) as $index => $colorChunk){
            if($checkValid){
                if($firstChunk === null){
                    $firstChunk = $colorChunk;
                }else if($firstChunk !== $colorChunk){
                    $valid = true;
                }
            }
            $colorChunk[] = 127 - intdiv(array_pop($colorChunk), 2);
            imagesetpixel($image, $index % $width, (int) ($index / $width), imagecolorallocatealpha($image, ...$colorChunk));
        }
        return !$checkValid || $valid ? $image : null;
    }

    /**
     * @param array $data
     *
     * @return string
     *
     * @throw InvalidSkinException
     */
    public function getGeometryNameFromData(array $data) : string{
        $formatVersion = $data["format_version"] ?? null;
        $keys = array_keys($data);
        if($formatVersion === null){
            return $keys[0] ?? "undefined";
        }else{
            unset($data["format_version"]);
        }

        try{
            $version = new VersionString($formatVersion);
            if($version->compare(new VersionString("1.12.0")) === 1){ //format version < 1.12.0
                return $keys[0];
            }else{
                return $data["minecraft:geometry"][0]["description"]["identifier"];
            }
        }catch(\Exception $e){
            throw new InvalidSkinException("Invalid geometry data (format_version: $formatVersion)");
        }
    }
}
