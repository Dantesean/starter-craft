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
     * @return array|false
     */
    protected function getFile($path)
    {
        $key = $this->applyPathPrefix($path);

        foreach($this->collection->all() as $item) {
            if($item->key == $key) {
                return $item->data;
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
        $source = $this->applyPathPrefix($path);
        $destination = $this->applyPathPrefix($newpath);

        return $this->archive->renameName($source, $destination);
    }

    /**
     * {@inheritdoc}
     */
    public function delete($path)
    {
        $location = $this->applyPathPrefix($path);

        return $this->archive->deleteName($location);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteDir($dirname)
    {
        // This is needed to ensure the right number of
        // files are set to the $numFiles property.
        $this->reopenArchive();

        $location = $this->applyPathPrefix($dirname);
        $path = Util::normalizePrefix($location, '/');
        $length = strlen($path);

        for($i = 0; $i < $this->archive->numFiles; $i++) {
            $info = $this->archive->statIndex($i);

            if(substr($info['name'], 0, $length) === $path) {
                $this->archive->deleteIndex($i);
            }
        }

        return $this->archive->deleteName($dirname);
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
        $this->reopenArchive();
        $location = $this->applyPathPrefix($path);

        if(!$contents = $this->archive->getFromName($location)) {
            return false;
        }

        return compact('contents');
    }

    /**
     * {@inheritdoc}
     */
    public function readStream($path)
    {

        $file = $this->getFile($path);
        de($file);
        if(!$file) {
            return false;
        }

        $url = preg_replace('/^\/\//', 'https://', $file['url']);

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
        $result = $this->collection->all();

        $pathPrefix = $this->getPathPrefix();
        $prefixLength = strlen($pathPrefix);

        $res = array_filter(array_map(function($item) use ($pathPrefix, $prefixLength) {
            if($pathPrefix && (substr($item['name'], 0, $prefixLength) !== $pathPrefix || $item['name'] === $pathPrefix)) {
                return false;
            }

            return $item->data;
        }, $result));

        dd(json_encode($res));

        return $res;
    }

    /**
     * {@inheritdoc}
     */
    public function getMetadata($path)
    {
        $location = $this->applyPathPrefix($path);

        $items = $this->collection->all();
        foreach($items as $item) {
            if($item->local_path == $location || $item->local_path == $path || $item->key == $location) {
                return $item->data;
            }
        }

        return false;
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
        // TODO: Implement copy() method.
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
        // TODO: Implement setVisibility() method.
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
        // TODO: Implement getVisibility() method.
    }
}
