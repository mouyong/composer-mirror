<?php

/*
|--------------------------------------------------------------------------
| 清理过期文件，包括远程清理
|--------------------------------------------------------------------------
*/

namespace ZenCodex\ComposerMirror\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use ZenCodex\ComposerMirror\App;
use ZenCodex\ComposerMirror\Cloud;
use ZenCodex\ComposerMirror\Log;
use ZenCodex\ComposerMirror\Rainbow;

class ClearCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName("app:clear")
            ->setDescription('清理过期文件或又拍云反向清理');

        $this
            ->addOption('--expired', null, InputOption::VALUE_OPTIONAL, '清理过期文件', 'json')
            ->addOption('--diff', null, InputOption::VALUE_NONE, '又拍云反向清理，根据 app:rainbow 缓存的远程文件列表')
            ->addOption('--all', null, InputOption::VALUE_NONE, '清除 cache 下的文件');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($input->getOption('all')) {
            $this->clearCacheDir();
            return;
        }

        if ($input->getOption('diff')) {
            $this->clearCloudDiffFiles();
        } else if ($input->getOption('expired') === 'json') {
            $this->clearJsonOutdatedFiles();
        } else if ($input->getOption('expired') === 'dist') {
            $this->clearDistOutdatedFiles();
        }
    }

    function clearJsonOutdatedFiles()
    {
        $config = App::getConfig();
        $cloud = new Cloud($config);

        $allFiles = Finder::create()->files()->followLinks()->in($config->cachedir . 'p');
        $packages = json_decode(file_get_contents($config->cachedir . 'packages.json'));
        $basetime = strtotime($packages->update_at ?? time());

        foreach ($allFiles as $f) {
            // skip "p/provider-xxx%hash%.json
            // if (strpos($file, '/p/provider-')) continue;

            $realFileName = $f->getRealPath();
            // default: 2 hour
            if ($basetime - filemtime($realFileName) > $config->expireMinutes * 60) {
                // remove remote json file
                if (App::getInstance()->isPush2Cloud) {
                    $cloud->removeRemoteFile($f);
                }

                $f->isLink() && unlink($f);
                unlink($realFileName);
                Log::warn("removed file => " . $realFileName);
            }
        }
    }

    public function clearDistOutdatedFiles()
    {
        $config = App::getConfig();
        $cloud = new Cloud($config);

        if (!file_exists($config->dbdir . 'touchall.log')) {
            Log::error('cannot find touall.log, please run app:scan first');
            return;
        }

        $allFiles = Finder::create()->files()->followLinks()->in($config->distdir);
        $lines = file($config->dbdir . 'touchall.log', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        [$basetime] = explode(',', array_pop($lines));

        foreach ($allFiles as $f) {
            $realFileName = $f->getRealPath();
            // default: 2.5 day
            if ($basetime - filemtime($realFileName) > 600) {
                // remove remote json file
                if ($config->cloudsync) {
                    $cloud->removeRemoteFile($f);
                }

                $f->isLink() && unlink($f);
                unlink($realFileName);
                Log::warn("removed file => " . $realFileName);
            }
        }
    }

    public function clearCloudDiffFiles()
    {
        $config = App::getConfig();
        $cloud = new Cloud($config);

        $i = $index = $startfrom = 0;
        if (file_exists($config->dbdir . Rainbow::DIST_URI_MAP . '.log')) {
            $startfrom = intval(file_get_contents($config->dbdir . Rainbow::DIST_URI_MAP . '.log'));
        }

        $mapUris = file($config->dbdir . Rainbow::DIST_URI_MAP, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($mapUris as $uri) {
            $index++;
            if ($index < $startfrom) continue;

            $zipFile = $config->distdir . ltrim($uri, '/');
            if (!file_exists($zipFile)) {
                Log::info('local not found => ' . $zipFile);
                $cloud->removeRemoteFile($zipFile);
                file_put_contents($config->dbdir . Rainbow::DIST_URI_MAP . '.log', $index);
                ++$i;
            }
        }

        Log::warn("$i files removed from cloud");
    }

    public function clearCacheDir()
    {
        $config = App::getConfig();

        $allFiles = Finder::create()->files()->followLinks()->in($config->cachedir);

        $keepFiles = ['index.php', 'submit.php', 'current_sync_packages.php'];
        foreach ($allFiles as $f) {
            if (in_array($f->getFilename(), $keepFiles)) {
                continue;
            }

            unlink($f->getRealPath());
        }
    }
}
