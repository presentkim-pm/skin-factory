<?php

/**
 *
 *  ____                           _   _  ___
 * |  _ \ _ __ ___  ___  ___ _ __ | |_| |/ (_)_ __ ___
 * | |_) | '__/ _ \/ __|/ _ \ '_ \| __| ' /| | '_ ` _ \
 * |  __/| | |  __/\__ \  __/ | | | |_| . \| | | | | | |
 * |_|   |_|  \___||___/\___|_| |_|\__|_|\_\_|_| |_| |_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the MIT License. see <https://opensource.org/licenses/MIT>.
 *
 * @author  PresentKim (debe3721@gmail.com)
 * @link    https://github.com/PresentKim
 * @license https://opensource.org/licenses/MIT MIT License
 *
 *   (\ /)
 *  ( . .) ♥
 *  c(")(")
 *
 * @noinspection PhpUnused
 */

declare(strict_types=1);

namespace kim\present\factory\skin;

use Exception;
use kim\present\converter\png\PngConverter;
use pocketmine\entity\InvalidSkinException;
use pocketmine\network\mcpe\protocol\types\skin\SkinData;
use pocketmine\network\mcpe\protocol\types\skin\SkinImage;
use pocketmine\utils\SingletonTrait;
use pocketmine\utils\VersionString;

class SkinFactory{
    use SingletonTrait;

    /** @var array<string, SkinImage> key => skin image */
    private array $skinImages = [];

    /** @var array<string, string> key => geometry data */
    private array $geometries = [];

    /** @var array<string, SkinData> key => cached skin data instance */
    private array $caches = [];

    /** Called when the plugin is loaded, before calling onEnable() */
    public function onLoad() : void{
        self::$instance = $this;
    }

    /**
     * @param string    $key
     * @param SkinImage $skinImage
     */
    public function registerImage(string $key, SkinImage $skinImage) : void{
        $this->skinImages[$key] = $skinImage;

        //Remove cached skin data
        $haystack = array_keys($this->caches);
        $needle = "$key\\";
        for($i = 0, $count = count($haystack), $length = strlen($needle); $i < $count; ++$i){
            if(substr($haystack[$i], 0, $length) === $needle){
                unset($this->caches[$haystack[$i]]);
                break;
            }
        }
    }

    /**
     * @param string $key
     * @param string $filepath
     */
    public function registerImageFromFile(string $key, string $filepath) : void{
        $this->registerImage($key, PngConverter::toSkinImage(imagecreatefrompng($filepath)));
    }

    /**
     * @param string $key
     *
     * @return SkinImage|null
     */
    public function getImage(string $key) : ?SkinImage{
        return $this->skinImages[$key] ?? null;
    }

    /**
     * @param string $key
     * @param string $geometryData
     */
    public function registerGeometry(string $key, string $geometryData) : void{
        $this->geometries[$key] = $geometryData;

        //Remove cached skin data
        $haystack = array_keys($this->caches);
        $needle = "\\$key";
        for($i = 0, $count = count($haystack), $length = strlen($needle); $i < $count; ++$i){
            if(substr($haystack[$i], -$length) === $needle){
                unset($this->caches[$haystack[$i]]);
                break;
            }
        }
    }

    /**
     * @param string $key
     * @param string $filepath
     */
    public function registerGeometryFromFile(string $key, string $filepath) : void{
        $this->registerGeometry($key, file_get_contents($filepath));
    }

    /**
     * @param string $key
     *
     * @return string|null
     */
    public function getGeometry(string $key) : ?string{
        return $this->geometries[$key] ?? null;
    }

    /**
     * @param string      $skinImageKey
     * @param null|string $geometryDataKey = null
     *
     * @return null|SkinData
     */
    public function get(string $skinImageKey, ?string $geometryDataKey = null) : ?SkinData{
        $skinImage = $this->skinImages[$skinImageKey] ?? null;
        if(!$skinImage)
            return null;

        if(!$geometryDataKey){
            $geometryDataKey = $skinImageKey;
        }
        $geometryData = $this->geometries[$geometryDataKey] ?? null;
        if(!$geometryData)
            return null;

        $cacheKey = "$skinImageKey\\$geometryDataKey";
        if(!isset($this->caches[$cacheKey])){
            $resourcePatch = json_encode(["geometry" => ["default" => self::getGeometryNameFromData(json_decode($geometryData, true))]]);
            $this->caches[$cacheKey] = new SkinData($skinImageKey, "", $resourcePatch, $skinImage, [], null, $geometryData);
        }
        return clone $this->caches[$cacheKey];
    }

    /**
     * @param array $data
     *
     * @return string
     *
     * @throw InvalidSkinException
     */
    public static function getGeometryNameFromData(array $data) : string{
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
        }catch(Exception){
            throw new InvalidSkinException("Invalid geometry data (format_version: $formatVersion)");
        }
    }
}
