<?php

namespace Zitec\EmagMarketplace\Setup\Patch\Data;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * AWB PDFs used to be cached in pub/media/awb/<id>_<format>.pdf (public, guessable URLs, customer data).
 * They are now kept in var/zitec_emkp/awb/; the old public copies are deleted (they are downloaded again
 * from eMAG on demand).
 */
class RemovePublicAwbFiles implements DataPatchInterface
{
    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @param Filesystem $filesystem
     */
    public function __construct(Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    /**
     * @return $this
     */
    public function apply()
    {
        $media = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);

        if (!$media->isDirectory('awb')) {
            return $this;
        }

        foreach ($media->read('awb') as $path) {
            if (preg_match('#^awb/[0-9]+_A[456]\.pdf$#', $path) && $media->isFile($path)) {
                $media->delete($path);
            }
        }

        return $this;
    }

    /**
     * @return string[]
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * @return string[]
     */
    public function getAliases()
    {
        return [];
    }
}
