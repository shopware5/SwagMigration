<?php

namespace Shopware\SwagMigration\Commands;

use Shopware\Commands\ShopwareCommand;
use Shopware\SwagMigration\Components\Migration\Cleanup;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CleanDataCommand extends ShopwareCommand
{
    /**
     * List of all possible data categories.
     */
    const DATA_CATEGORIES = [
        'clear_customers',
        'clear_orders',
        'clear_votes',
        'clear_articles',
        'clear_categories',
        'clear_supplier',
        'clear_properties',
        'clear_mappings',
        'clear_images',
        'clear_article_downloads',
        'clear_esd_article_downloads',
    ];

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('sw:migration:clean:data')
            ->setDescription('Clears all data by selected categories')
            ->addArgument(
                'categories',
                InputArgument::IS_ARRAY,
                'Categories of data to be cleared'
            )
            ->addOption(
                'all',
                'a',
                InputOption::VALUE_NONE,
                'Use this to clear all data'
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        if ($input->getOption('all')) {
            $categories = self::DATA_CATEGORIES;
        } else {
            $inputCategories = $input->getArgument('categories');
            $categories = array_intersect($inputCategories, self::DATA_CATEGORIES);
            if (empty($categories)) {
                throw new RuntimeException('Not enough arguments (missing: "categories")');
            }
        }

        $cleanup = new Cleanup();
        $cleanup->cleanUpByArray(array_fill_keys($categories, true));

        $io->success(sprintf('Data categories(%s) where successfully cleared.', implode(', ', $categories)));
    }
}
