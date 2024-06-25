<?php

namespace hyper\helpers;

use hyper\application;
use hyper\utils\uploader as utilsUploader;

trait uploader
{
    protected function uploader(): array
    {
        return [];
    }

    protected function uploadChanges(array $data): array
    {
        foreach ($this->uploads() as $upload) {
            $name = $upload['name'];
            $oldFiles = explode(',', application::$app->request->post('_' . $name, ''));
            if (isset($data[$name]) && (((array)$data[$name]['size'] ?? [])[0] ?? 0) > 0) {
                $upload['uploadDir'] = env('upload_dir') . '/' . ($upload['uploadTo'] ?? '');
                unset($upload['name'], $upload['uploadTo']);
                $uploader = new utilsUploader(...$upload);
                $data[$name] = $this->clearSavedPath($uploader->upload($data[$name]), env('upload_dir'));
                $this->removeFiles($oldFiles);
            } else {
                $data[$name] = count($oldFiles) == 1 ? $oldFiles[0] : $oldFiles;
            }
        }
        return $data;
    }

    public function getRegisteredUploads(): array
    {
        $registeredUploads = $this->uploads();
        return $registeredUploads;
    }

    protected function removeUploaded(array $data): void
    {
        foreach ($this->uploads() as $upload) {
            $this->removeFiles($data[$upload['name']] ?? []);
        }
    }

    protected function uploads(): array
    {
        $uploads = $this->uploader();
        if (!empty($uploads) && !(isset($uploads[0]) && is_array($uploads[0]))) {
            $uploads = [$uploads];
        }
        return $uploads;
    }

    protected function removeFiles(string|array $files): void
    {
        foreach ((array) $files as $file) {
            if (file_exists($filePath = env('upload_dir') . '/' . $file)) {
                unlink($filePath);
            }
        }
    }

    private function clearSavedPath(array|string $path, string $basePath): array|string
    {
        if (is_array($path)) {
            $paths = [];
            foreach ($path as $p) {
                $paths[] = $this->clearSavedPath($p, $basePath);
            }
            return $paths;
        }

        $path = str_ireplace($basePath, '', $path);
        $path = trim($path, DIRECTORY_SEPARATOR);
        $path = trim($path, '/');
        return $path;
    }
}
