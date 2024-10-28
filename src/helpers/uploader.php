<?php

namespace hyper\helpers;

use hyper\application;
use hyper\utils\uploader as utilsUploader;

/**
 * Trait uploader
 * 
 * Provides functionality for handling file uploads within a PHP application.
 * Includes methods for uploading, managing, and removing files, as well as handling
 * various upload configurations defined in the implementing class.
 * 
 * @package hyper\helpers
 * @author Shahin Moyshan <shahin.moyshan2@gmail.com>
 */
trait uploader
{
    /**
     * Method to define upload configurations for the implementing class.
     * Should be overridden to specify the file upload settings.
     * 
     * @return array
     */
    protected function uploader(): array
    {
        return [];
    }

    /**
     * Processes and handles file uploads, updating the provided data array
     * with the paths of the uploaded files.
     * 
     * @param array $data The data array containing file input fields.
     * @return array The updated data array with the paths of the uploaded files.
     */
    protected function uploadChanges(array $data): array
    {
        foreach ($this->uploads() as $upload) {
            $name = $upload['name'];
            $upload['uploadDir'] = env('upload_dir') . '/' . ($upload['uploadTo'] ?? '');
            unset($upload['name'], $upload['uploadTo']);

            $data[$name] = $this->__doUpload($name, $upload, $data[$name]);
        }
        return $data;
    }

    /**
     * Performs the actual file upload operation based on the given configuration.
     * 
     * @param string $name The name of the file input field.
     * @param array $upload The upload configuration array.
     * @param array|null $files The files to be uploaded.
     * @return null|string|array The path(s) of the uploaded file(s) or null if no upload occurred.
     */
    protected function __doUpload(string $name, array $upload, ?array $files): null|string|array
    {
        $saved = null;
        $oldFiles = explode(',', application::$app->request->post('_' . $name, ''));
        if (isset($files) && (((array)$files['size'] ?? [])[0] ?? 0) > 0) {
            $uploader = new utilsUploader(...$upload);
            $saved = $this->clearSavedPath($uploader->upload($files), env('upload_dir'));
            $this->removeFiles($oldFiles);
        } else {
            $saved = count($oldFiles) == 1 ? $oldFiles[0] : $oldFiles;
        }
        return $saved;
    }

    /**
     * Retrieves the registered upload configurations for the implementing class.
     * 
     * @return array An array of registered upload configurations.
     */
    public function getRegisteredUploads(): array
    {
        $registeredUploads = $this->uploads();
        return $registeredUploads;
    }

    /**
     * Removes previously uploaded files specified in the provided data array.
     * 
     * @param array $data The data array containing paths of files to be removed.
     */
    protected function removeUploaded(array $data): void
    {
        foreach ($this->uploads() as $upload) {
            $this->removeFiles($data[$upload['name']] ?? []);
        }
    }

    /**
     * Retrieves and processes the upload configurations defined in the uploader method.
     * Ensures the configurations are properly structured for processing.
     * 
     * @return array The processed upload configurations.
     */
    protected function uploads(): array
    {
        $uploads = $this->uploader();
        if (!empty($uploads) && !(isset($uploads[0]) && is_array($uploads[0]))) {
            $uploads = [$uploads];
        }
        return $uploads;
    }

    /**
     * Removes specified files from the file system.
     * 
     * @param string|array $files The file(s) to be removed.
     */
    protected function removeFiles(string|array $files): void
    {
        foreach ((array) $files as $file) {
            if (is_file($filePath = env('upload_dir') . '/' . $file) && file_exists($filePath)) {
                unlink($filePath);
            }
        }
    }

    /**
     * Cleans and normalizes the saved file path by removing the base upload directory prefix.
     * 
     * @param array|string $path The path(s) to be cleaned.
     * @param string $basePath The base directory path to be removed.
     * @return array|string
     */
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

        // The cleaned path(s).
        return $path;
    }
}
