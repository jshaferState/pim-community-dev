<?php

namespace Oro\Bundle\AsseticBundle\Command;

use Assetic\Asset\AssetInterface;
use Assetic\Util\VarUtils;
use Oro\Bundle\AsseticBundle\Factory\OroAssetManager;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class OroAsseticDumpCommand extends ContainerAwareCommand
{
    private const URL_PATTERN = '`url\((["\']?)(?P<url>.*?)(\\1)\)`';

    /** @var string */
    protected $basePath;

    /** @var OroAssetManager */
    protected $am;

    protected function configure()
    {
        $this
            ->setName('oro:assetic:dump')
            ->setDescription('Dumps oro assetics')
            ->addArgument('show-groups', InputArgument::OPTIONAL, 'Show list of css groups')
            ->addArgument('write_to', InputArgument::OPTIONAL, 'Override the configured asset root');
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->basePath = $input->getArgument('write_to') ? : $this->getContainer()->getParameter('assetic.write_to');
        $this->am = $this->getContainer()->get('oro_assetic.asset_manager');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($input->getArgument('show-groups') !== null) {
            $output->writeln('Get list of css groups');
            $this->getGroupList($output);
        } else {
            $output->writeln(sprintf('Dumping all <comment>%s</comment> assets.', $input->getOption('env')));
            $output->writeln(sprintf('Debug mode is <comment>%s</comment>.', 'off'));
            $output->writeln('');

            $this->dumpAssets($output);
        }
    }

    protected function getGroupList($output)
    {
        $assets = $this->am->getAssetGroups();
        $compiledGroups = $this->am->getCompiledGroups();

        $output->writeln('');
        $output->writeln('<comment>Css</comment> groups:');
        $this->writeGroups($assets['css'], $compiledGroups['css'], $output);
    }

    protected function writeGroups($groups, $compiledGroups, $output)
    {
        foreach ($groups as $group) {
            if (in_array($group, $compiledGroups)) {
                $output->writeln(
                    sprintf(
                        '<comment>%s</comment> (compiled)',
                        $group
                    )
                );
            } else {
                $output->writeln(
                    sprintf(
                        '<info>%s</info>',
                        $group
                    )
                );
            }
        }
    }

    /**
     * Dump files
     * @param $output
     */
    protected function dumpAssets($output)
    {
        foreach ($this->am->getAssets() as $node) {
            foreach ($node->getCompressAssets() as $asset) {
                $this->doDump($asset, $output);
            }
        }
    }

    /**
     * @param AssetInterface $asset
     * @param OutputInterface $output
     * @throws \RuntimeException
     */
    private function doDump(AssetInterface $asset, OutputInterface $output)
    {
        foreach ($this->getAssetVarCombinations($asset) as $combination) {
            $asset->setValues($combination);

            // resolve the target path
            $target = rtrim($this->basePath, '/') . '/' . $asset->getTargetPath();
            $target = str_replace('_controller/', '', $target);
            $target = VarUtils::resolve($target, $asset->getVars(), $asset->getValues());

            if (!is_dir($dir = dirname($target))) {
                $output->writeln(
                    sprintf(
                        '<comment>%s</comment> <info>[dir+]</info> %s',
                        date('H:i:s'),
                        $dir
                    )
                );

                if (false === @mkdir($dir, 0777, true)) {
                    throw new \RuntimeException('Unable to create directory ' . $dir);
                }
            }

            $output->writeln(
                sprintf(
                    '<comment>%s</comment> <info>[file+]</info> %s',
                    date('H:i:s'),
                    $target
                )
            );

            $fileContent = $asset->dump();
            $this->checkResourcePaths($fileContent, $output);

            if (false === @file_put_contents($target, $fileContent)) {
                throw new \RuntimeException('Unable to write file ' . $target);
            }
        }
    }

    private function getAssetVarCombinations(AssetInterface $asset)
    {
        return VarUtils::getCombinations(
            $asset->getVars(),
            $this->getContainer()->getParameter('assetic.variables')
        );
    }

    private function checkResourcePaths(string $fileContent, OutputInterface $output): void
    {
        preg_match_all(self::URL_PATTERN, $fileContent, $matches);

        foreach ($matches['url'] as $url) {
            if (0 === strpos($url, 'data:')) {
                continue;
            }

            if (0 !== strpos($url, '/')) {
                $output->writeln(
                    sprintf(
                        '<comment>Please use absolute resource paths to avoid wrong path resolution during less files import in other bundles ("%s" given).</comment>',
                        $url
                    )
                );

                continue;
            }

            $path = $this->basePath.$url;
            $cleanedPath = $this->cleanResourcePath($path);

            if (!file_exists($cleanedPath)) {
                $output->writeln(
                    sprintf(
                        '<comment>Resource path "%s" does not point to any file inside web directory.</comment>',
                        $url
                    )
                );

                continue;
            }
        }
    }

    private function cleanResourcePath(string $path): string
    {
        if (false !== strpos($path, '?')) {
            $path = substr($path, 0, strpos($path, '?'));
        }

        if (false !== strpos($path, '#')) {
            $path = substr($path, 0, strpos($path, '#'));
        }

        return $path;
    }
}
