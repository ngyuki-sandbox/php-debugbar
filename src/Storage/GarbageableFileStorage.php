<?php
declare(strict_types = 1);

namespace App\Component\DebugBar\Storage;

use DebugBar\Storage\FileStorage;
use FilesystemIterator;
use SplFileInfo;

class GarbageableFileStorage extends FileStorage
{
    /**
     * @var int
     */
    private $maxLifetime = 300;

    /**
     * @var int
     */
    private $divisor = 100;

    /**
     * @var int
     */
    private $probability = 4;

    /**
     * {@inheritdoc}
     */
    public function save($id, $data)
    {
        if (!file_exists($this->dirname)) {
            mkdir($this->dirname, 0777, true);
        }
        if (mt_rand(1, $this->divisor) < $this->probability) {
            $this->gc();
        }
        file_put_contents($this->makeFilename($id), json_encode($data));
    }

    public function find(array $filters = array(), $max = 20, $offset = 0)
    {
        // Deprecated:
        //   usort(): Returning bool from comparison function is deprecated, return an integer less than, equal to, or greater than zero
        //   in vendor/maximebf/debugbar/src/DebugBar/Storage/FileStorage.php on line 66
        return @parent::find($filters, $max, $offset);
    }

    public function setGcParam(int $maxLifetime, int $divisor, int $probability)
    {
        $this->maxLifetime = $maxLifetime;
        $this->divisor = $divisor;
        $this->probability = $probability;
    }

    public function gc()
    {
        $limit = time() - $this->maxLifetime;
        foreach (new FilesystemIterator($this->dirname) as $file) {
            assert($file instanceof SplFileInfo);
            if ($file->getMTime() < $limit) {
                unlink($file->getPathname());
            }
        }
    }
}
