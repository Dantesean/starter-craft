<?php

/**
 * Copyright 2018 Includable
 * Created by Thomas Schoffelen
 */

namespace Includable\CraftCompatibility;

use CDN;
use League\Flysystem\Adapter\AbstractAdapter;
use League\Flysystem\Config;
use League\Flysystem\Util;
use Sandbox\Storage;

/**
 * Class IncludableAdapter
 *
 * @package Includable\CraftCompatibility
 */
class IncludableAdapter extends AbstractAdapter
{

    /**
     * @var array
     */
    protected static $resultMap = [
        'size' => 'size',
        'mtime' => 'timestamp',
        'name' => 'path',
    ];

    /**
     * @var Storage
     */
    protected $client;

    /**
     * @var Storage\Collection
     */
    protected $collection;

    /**
     * @var CDN
     */
    protected $cdn;

    /**
     * IncludableAdapter constructor.
     *
     * @param Storage $storage
     * @param string $prefix
     */
    public function __construct($storage, $prefix = '')
    {
        $this->client = $storage;
        $this->collection = $storage->collection('flysystem_storage');
        $this->cdn = CDN::instance();
        $this->setPathPrefix($prefix);
    }

    /**
     * Upload an object.
     *
     * @param string $path
     * @param string|resource $body
     * @param Config $config
     * @param bool $is_stream
     * @return array|false
     */
    protected function upload($path, $body, Config $config, $is_stream = false)
    {
        $key = $this->applyPathPrefix($path);

        if(!$is_stream) {
            $mime = $config->get('mimetype') ?: Util::guessMimeType($path, $body);
            $token = $this->cdn->upload($body, 'flysystem_upload', 'x', 'Untitled', basename($path), $mime);
        } else {
            $tmp_file = tempnam('/tmp', 'flysystem_upload');
            $stream = fopen($tmp_file, 'w+b');
            if(!$stream) {
                return false;
            }
            stream_copy_to_stream($body, $stream);
            if(!fclose($stream)) {
                return false;
            }
            $token = $this->cdn->upload($tmp_file, 'x', 'Untitled', basename($path));
        }
        $url = $this->cdn->get($token);
        $remote_path = parse_url($url, PHP_URL_PATH);

        $info = array_merge([
            'local_name' => $path,
            'name' => basename($path),
            'key' => $key,
            'type' => 'file',
            'token' => $token,
            'url' => $url,
            'path' => $remote_path,
            'visibility' => 'public',
            'timestamp' => time()
        ], Util::pathinfo($key));

        $this->collection->add($info);

        return $info;
    }

    /**
     * @param string $path
     * @return Storage\CollectionItem|false
     */
    protected function getFile($path)
    {
        $key = $this->applyPathPrefix($path);

        foreach($this->collection->all() as $item) {
            if($item->data['key'] == $key) {
                return $item;
            }
        }

        return false;
    }

    /**
     * Write a new file.
     *
     * @param string $path
     * @param string $contents
     * @param Config $config Config object
     *
     * @return array|false false on failure file meta data on success
     */
    public function write($path, $contents, Config $config)
    {
        return $this->upload($path, $contents, $config);
    }

    /**
     * {@inheritdoc}
     */
    public function update($path, $contents, Config $config)
    {
        return $this->upload($path, $contents, $config);
    }

    /**
     * {@inheritdoc}
     */
    public function rename($path, $newpath)
    {
        $file = $this->getFile($path);
        if(!$file) {
            return false;
        }

        $newkey = $this->applyPathPrefix($newpath);
        $newfile = array_merge($file->data, [
            'local_name' => $newpath,
            'key' => $newkey
        ]);

        return (bool)$this->collection->update($file->id, $newfile);
    }

    /**
     * {@inheritdoc}
     */
    public function delete($path)
    {
        $file = $this->getFile($path);
        if(!$file) {
            return false;
        }

        return (bool)$this->collection->delete($file->id);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteDir($dirname)
    {
        $path_prefix = $this->applyPathPrefix($dirname);
        $prefix_length = strlen($path_prefix);

        foreach($this->collection->all() as $item) {
            if(substr($item->data['name'], 0, $prefix_length) === $path_prefix) {
                $this->collection->delete($item->id);
            }
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function createDir($dirname, Config $config)
    {
        if(!$this->has($dirname)) {
            $location = $this->applyPathPrefix($dirname);
            $this->archive->addEmptyDir($location);
        }

        return ['path' => $dirname];
    }

    /**
     * {@inheritdoc}
     */
    public function has($path)
    {
        return $this->getMetadata($path);
    }

    /**
     * {@inheritdoc}
     */
    public function read($path)
    {
        $file = $this->getFile($path);
        if(!$file) {
            return false;
        }

        $url = preg_replace('/^\/\//', 'https://', $file->data['url']);
        $contents = file_get_contents($url);
        if(empty($contents) || !$contents) {
            return false;
        }

        return [
            'contents' => $contents
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function readStream($path)
    {
        $file = $this->getFile($path);
        if(!$file) {
            return false;
        }

        $url = preg_replace('/^\/\//', 'https://', $file->data['url']);
        $fp = fopen($url, 'r');

        return [
            'stream' => $fp
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function listContents($dirname = '', $recursive = false)
    {
        $path_prefix = $this->applyPathPrefix($dirname);
        $prefix_length = strlen($path_prefix);

        return array_filter(array_map(function($item) use ($path_prefix, $prefix_length) {
            return $path_prefix && (substr($item['name'], 0, $prefix_length) !== $path_prefix)
                ? false : $item->data;
        }, $this->collection->all()));
    }

    /**
     * {@inheritdoc}
     */
    public function getMetadata($path)
    {
        $file = $this->getFile($path);
        if(!$file) {
            return false;
        }

        return $file->data;
    }

    /**
     * {@inheritdoc}
     */
    public function getSize($path)
    {
        return $this->getMetadata($path);
    }

    /**
     * {@inheritdoc}
     */
    public function getMimetype($path)
    {
        if(!$data = $this->read($path)) {
            return false;
        }

        if(empty($data['mimetype'])) {
            $data['mimetype'] = Util::guessMimeType($path, $data['contents']);
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function getTimestamp($path)
    {
        return $this->getMetadata($path);
    }

    /**
     * Write a new file using a stream.
     *
     * @param string $path
     * @param resource $resource
     * @param Config $config Config object
     *
     * @return array|false false on failure file meta data on success
     */
    public function writeStream($path, $resource, Config $config)
    {
        return $this->upload($path, $resource, $config, true);
    }

    /**
     * Update a file using a stream.
     *
     * @param string $path
     * @param resource $resource
     * @param Config $config Config object
     *
     * @return array|false false on failure file meta data on success
     */
    public function updateStream($path, $resource, Config $config)
    {
        return $this->upload($path, $resource, $config, true);
    }

    /**
     * Copy a file.
     *
     * @param string $path
     * @param string $newpath
     *
     * @return bool
     */
    public function copy($path, $newpath)
    {
        $file = $this->getFile($path);
        if(!$file) {
            return false;
        }

        $newkey = $this->applyPathPrefix($newpath);
        $newfile = array_merge($file->data, [
            'local_name' => $newpath,
            'key' => $newkey
        ]);

        return (bool)$this->collection->add($newfile);
    }

    /**
     * Set the visibility for a file.
     *
     * @param string $path
     * @param string $visibility
     *
     * @return array|false file meta data
     */
    public function setVisibility($path, $visibility)
    {
        $file = $this->getFile($path);
        if(!$file) {
            return false;
        }

        return array_merge($file->data, [
            'visibility' => $visibility
        ]);
    }

    /**
     * Get the visibility of a file.
     *
     * @param string $path
     *
     * @return array|false
     */
    public function getVisibility($path)
    {
        return $this->getMetadata($path);
    }
}
