<?php

namespace hyper\utils;

use RuntimeException;

class uploader
{
    public function __construct(
        public string $uploadDir,
        public array $extensions = [],
        public bool $multiple = false,
        public int $maxSize = 1048576,
        public ?array $resize = null,
        public ?array $resizes = null,
        public ?int $compress = null,
    ) {
        // Ensure the upload directory exists and is writable
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0777, true);
        } elseif (!is_writable($this->uploadDir)) {
            throw new RuntimeException("Upload directory is not writable.");
        }
    }

    public function upload(array $files): string|array
    {
        if ($this->multiple) {
            $uploadedFiles = [];
            foreach ($files['name'] as $key => $name) {
                $file = [
                    'name' => $files['name'][$key],
                    'type' => $files['type'][$key],
                    'tmp_name' => $files['tmp_name'][$key],
                    'error' => $files['error'][$key],
                    'size' => $files['size'][$key],
                ];
                $uploadedFiles = array_merge($uploadedFiles, (array) $this->processUpload($file));
            }
            return $uploadedFiles;
        } else {
            return $this->processUpload($files);
        }
    }

    private function processUpload(array $file): array|string
    {
        // Validate file size
        if ($file['size'] > $this->maxSize) {
            throw new RuntimeException("File size exceeds the maximum limit.");
        }
        // Validate file extension
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        if (!in_array(strtolower($extension), $this->extensions)) {
            throw new RuntimeException("Invalid file extension.");
        }
        // Create a unique file name
        $uniqueName = $this->generateUniqueFileName($file['name']);
        $destination = $this->uploadDir . DIRECTORY_SEPARATOR . $uniqueName;
        // Move the uploaded file to the destination
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            throw new RuntimeException("Failed to move uploaded file.");
        }
        // compress, resize and bulk resize image
        if ((isset($this->compress) || isset($this->resize) || isset($this->resizes)) && in_array($extension, ['jpg', 'jpeg', 'png'])) {
            $image = new image($destination);
            if (isset($this->compress)) {
                $image->compress($this->compress);
            }
            if (isset($this->resize)) {
                $image->resize(array_keys($this->resize)[0], array_values($this->resize)[0]);
            }
            if (isset($this->resizes)) {
                $destination = array_merge([$destination], $image->bulkResize($this->resizes));
            }
        }
        return $destination;
    }

    private function generateUniqueFileName(string $fileName): string
    {
        $extension = pathinfo($fileName, PATHINFO_EXTENSION);
        $baseName = pathinfo($fileName, PATHINFO_FILENAME);
        $uniqueName = preg_replace('/[^a-zA-z0-9]+/', '-', $baseName) . '_' . uniqid('', true) . '.' . $extension;
        return $uniqueName;
    }
}
