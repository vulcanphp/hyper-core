<?php

namespace hyper\utils;

use RuntimeException;

class image
{
    protected $image;
    protected array $info;

    public function __construct(protected string $imageSource)
    {
        if (!extension_loaded('gd')) {
            throw new RuntimeException('Extension: GD is required to create image');
        }
        $requiredFunctions = [
            'getimagesize', 'imagecreatefromjpeg', 'imagecreatefrompng', 'imagejpeg',
            'imagecreatetruecolor', 'imagecopyresampled', 'imagecreatefromgif', 'imagepng', 'imagegif', 'imagerotate'
        ];
        foreach ($requiredFunctions as $func) {
            if (!function_exists($func)) {
                throw new RuntimeException('Required function: ' . $func . '() is not found.');
            }
        }
        if (!file_exists($this->imageSource)) {
            throw new RuntimeException('Image file: ' . $this->imageSource . ' does not exist.');
        }
        $this->info = array_merge(getimagesize($this->imageSource), pathinfo($this->imageSource));
    }

    public function getInfo(?string $key = null, $default = null)
    {
        return $key !== null ? ($this->info[$key] ?? $default) : $this->info;
    }

    public function getImage()
    {
        if (!isset($this->image)) {
            $this->image = match ($this->getInfo('mime')) {
                'image/jpeg', 'image/jpg' => imagecreatefromjpeg($this->imageSource),
                'image/png' => imagecreatefrompng($this->imageSource),
                'image/gif' => imagecreatefromgif($this->imageSource),
                default => throw new RuntimeException('Unsupported image type for: ' . $this->imageSource),
            };
        }
        return $this->image;
    }

    public function compress(int $quality = 75, $destination = null): bool
    {
        $destination = $destination ?? $this->imageSource;
        $image = $this->getImage();
        if (file_exists($destination)) {
            unlink($destination);
        }
        return match ($this->getInfo('mime')) {
            'image/png' => imagepng($image, $destination, round(9 * ($quality / 100))),
            default => imagejpeg($image, $destination, $quality),
        };
    }

    public function resize(int $imgWidth, int $imgHeight, ?string $destination = null): bool
    {
        $destination = $destination ?? $this->imageSource;
        [$width, $height] = $this->getInfo();

        $image = $this->getImage();
        $aspectRatio = $width / $height;
        $imgAspectRatio = $imgWidth / $imgHeight;

        [$newWidth, $newHeight] = $aspectRatio >= $imgAspectRatio
            ? [$width / ($height / $imgHeight), $imgHeight]
            : [$imgWidth, $height / ($width / $imgWidth)];

        $photo = imagecreatetruecolor($imgWidth, $imgHeight);
        if ($this->getInfo('mime') === 'image/png') {
            imagealphablending($photo, false);
            imagesavealpha($photo, true);
            $transparent = imagecolorallocatealpha($photo, 255, 255, 255, 127);
            imagefilledrectangle($photo, 0, 0, $imgWidth, $imgHeight, $transparent);
        }
        if (file_exists($destination)) {
            unlink($destination);
        }
        imagecopyresampled(
            $photo,
            $image,
            intval(0 - ($newWidth - $imgWidth) / 2),
            intval(0 - ($newHeight - $imgHeight) / 2),
            0,
            0,
            intval($newWidth),
            intval($newHeight),
            intval($width),
            intval($height)
        );
        return match ($this->getInfo('mime')) {
            'image/png' => imagepng($photo, $destination),
            default => imagejpeg($photo, $destination),
        };
    }

    public function bulkResize(array $sizes): array
    {
        $saved = [];
        foreach ($sizes as $width => $height) {
            $savePath = sprintf(
                '%s/%s-%sx%s.%s',
                $this->getInfo('dirname'),
                $this->getInfo('filename'),
                $width,
                $height,
                $this->getInfo('extension')
            );
            if ($this->resize($width, $height, $savePath)) {
                $saved[] = $savePath;
            }
        }
        return $saved;
    }

    public function rotate(float $degrees): bool
    {
        $image = $this->getImage();
        if ($this->getInfo('mime') === 'image/png') {
            imagesavealpha($image, true);
        }
        $rotated = imagerotate($image, $degrees, 0);
        return match ($this->getInfo('mime')) {
            'image/png' => imagepng($rotated, $this->imageSource),
            default => imagejpeg($rotated, $this->imageSource),
        };
    }
}
