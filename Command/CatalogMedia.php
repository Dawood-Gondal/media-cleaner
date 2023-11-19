<?php
/**
 * @category    M2Commerce Enterprise
 * @package     M2Commerce_MediaCleaner
 * @copyright   Copyright (c) 2023 M2Commerce Enterprise
 * @author      dawoodgondaldev@gmail.com
 */

namespace M2Commerce\MediaCleaner\Command;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Console\Cli;
use Magento\Framework\DB\Select;
use Magento\Framework\Filesystem;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Catalog\Model\ResourceModel\Product\Gallery;
use Symfony\Component\Console\Input\InputOption;

/**
 * Class CatalogMedia
 */
class CatalogMedia extends Command
{
    const INPUT_KEY_REMOVE_UNUSED = 'remove_unused';
    const INPUT_KEY_LIST_UNUSED = 'list_unused';
    const INPUT_KEY_LIST_DUPES = 'list_dupes';
    const INPUT_KEY_REMOVE_DUPES = 'remove_dupes';

    /**
     * @var Filesystem
     */
    public $filesystem;

    /**
     * @var ResourceConnection
     */
    private $resource;

    /**
     * @param ResourceConnection $resource
     * @param Filesystem $filesystem
     */
    public function __construct(
        ResourceConnection $resource,
        Filesystem $filesystem
    ) {
        $this->resource = $resource;
        $this->filesystem = $filesystem;
        parent::__construct();
    }

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this->setName('m2commerce:cleanmedia')
            ->setDescription('Get information about catalog product media, remove duplicate & unused catalog media')
            ->addOption(
                self::INPUT_KEY_REMOVE_UNUSED,
                'r',
                InputOption::VALUE_NONE,
                'Remove unused product images'
            )->addOption(
                self::INPUT_KEY_REMOVE_DUPES,
                'x',
                InputOption::VALUE_NONE,
                'Remove duplicated files and update database'
            )->addOption(
                self::INPUT_KEY_LIST_UNUSED,
                'u',
                InputOption::VALUE_NONE,
                'List unused media files'
            )->addOption(
                self::INPUT_KEY_LIST_DUPES,
                'd',
                InputOption::VALUE_NONE,
                'List duplicated files'
            );
        parent::configure();
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $productMediaPath = $this->getProductMediaPath();
        if (!is_dir($productMediaPath)) {
            $output->writeln(sprintf('Cannot find "%s" folder.', $productMediaPath));
            $output->writeln('It appears there are no product images to analyze.');
            return Cli::RETURN_FAILURE;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $productMediaPath,
                \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::FOLLOW_SYMLINKS
            )
        );

        $files = [];
        $hashes = [];
        $unusedFiles = 0;
        $cachedFiles = 0;
        $duplicatedFiles = 0;
        $removedUnusedFiles = 0;
        $removedDuplicateFiles = 0;
        $bytesFreed = 0;
        $updatedVarcharRows = 0;
        $updatedGalleryRows = 0;

        $mediaGalleryPaths = $this->getMediaGalleryPaths();

        /** @var $info \SplFileInfo */
        foreach ($iterator as $info) {
            $filePath = str_replace($this->getProductMediaPath(), '', $info->getPathname());
            if (strpos($filePath, '/cache/') === 0) {
                $cachedFiles++;
                continue;
            }
            $files[] = $filePath;

            if (!in_array($filePath, $mediaGalleryPaths)) {
                $unusedFiles++;
                if ($input->getOption(self::INPUT_KEY_LIST_UNUSED)) {
                    $output->writeln('Unused file: ' . $filePath);
                }
                if ($input->getOption(self::INPUT_KEY_REMOVE_UNUSED)) {
                    $bytesFreed += filesize($info->getPathname());
                    $removedUnusedFiles += unlink($info->getPathname());
                    $output->writeln(sprintf('Unused "%s" was removed', $filePath));
                    continue;
                }
            }

            $hash = md5_file($info->getPathname());
            if (isset($hashes[$hash])) {
                $duplicatedFiles++;
                if ($input->getOption(self::INPUT_KEY_LIST_DUPES)) {
                    $output->writeln(sprintf('Duplicate "%s" to "%s"', $filePath, $hashes[$hash]));
                }
                if ($input->getOption(self::INPUT_KEY_REMOVE_DUPES)) {
                    $bytesFreed += filesize($info->getPathname());
                    list($updatedVarcharRows, $updatedGalleryRows) = $this->updateDatabaseForRemovedDuplicates($hashes[$hash], $filePath);
                    $output->writeln(sprintf('Duplicate "%s" was removed', $filePath));
                }
            } else {
                $hashes[$hash] = $filePath;
            }
        }

        $output->writeln('');
        $output->writeln(sprintf('Media Gallery entries: %s.', count($mediaGalleryPaths)));
        $output->writeln(sprintf('Files in directory: %s.', count($files)));
        $output->writeln(sprintf('Cached images: %s.', $cachedFiles));
        $output->writeln(sprintf('Unused files: %s.', $unusedFiles));
        $output->writeln(sprintf('Duplicated files: %s.', $duplicatedFiles));
        $output->writeln('');
        if ($input->getOption(self::INPUT_KEY_REMOVE_UNUSED)) {
            $output->writeln(sprintf('Removed unused files: %s.', $removedUnusedFiles));
        }
        if ($input->getOption(self::INPUT_KEY_REMOVE_DUPES)) {
            $output->writeln(sprintf('Removed duplicated files: %s.', $duplicatedFiles));
            $output->writeln(sprintf('Updated catalog_product_entity_varchar rows: %s', $updatedVarcharRows));
            $output->writeln(sprintf('Updated catalog_product_entity_media_gallery rows: %s', $updatedGalleryRows));
        }
        if ($input->getOption(self::INPUT_KEY_REMOVE_UNUSED) || $input->getOption(self::INPUT_KEY_REMOVE_DUPES)) {
            $output->writeln(sprintf('Disk space freed: %s Mb', round($bytesFreed / 1024 / 1024)));
        }
        return Cli::RETURN_SUCCESS;
    }

    /**
     * @return array
     */
    private function getMediaGalleryPaths()
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTableName(Gallery::GALLERY_TABLE))
            ->reset(Select::COLUMNS)->columns('value');

        return $connection->fetchCol($select);
    }

    /**
     * @return string
     */
    private function getMediaPath(): string
    {
        return $this->filesystem->getDirectoryRead(DirectoryList::MEDIA)->getAbsolutePath();
    }

    /**
     * @return string
     */
    private function getProductMediaPath(): string
    {
        return $this->getMediaPath() . 'catalog/product';
    }

    /**
     * @param string $originalPath
     * @param string $duplicatePath
     * @return array
     */
    private function updateDatabaseForRemovedDuplicates(string $originalPath, string $duplicatePath): array
    {
        $connection = $this->resource->getConnection();
        $resultVarchar = $connection->update(
            $this->resource->getTableName('catalog_product_entity_varchar'),
            ['value' => $originalPath],
            $connection->quoteInto('value = ?', $duplicatePath)
        );
        $resultGallery = $connection->update(
            $this->resource->getTableName('catalog_product_entity_media_gallery'),
            ['value' => $originalPath],
            $connection->quoteInto('value = ?', $duplicatePath)
        );
        return [$resultVarchar, $resultGallery];
    }
}
