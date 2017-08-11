<?php
namespace ComposerExtendClass;

use Composer\Composer;
use Composer\Config;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Composer\Autoload\ClassLoader;

/**
 * Class Plugin.
 * The entry point of the plugin.
 *
 * @package Bubach\ComposerPluginExtendClass
 * @author  Christoffer Bubach
 * @license Nope
 */
class Plugin implements PluginInterface, EventSubscriberInterface
{

    /**
     * The console Input/Output object
     *
     * @var IOInterface
     */
    protected $io;

    /**
     * The Composer configuration
     *
     * @var Config
     */
    protected $config;

    /**
     * Apply plugin modifications to composer
     *
     * @param Composer    $composer The Composer object
     * @param IOInterface $io       The console Input/Output object
     *
     * @return void
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        $this->config = $composer->getConfig();
        $this->io     = $io;
    }

    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * The array keys are event names and the value can be:
     *
     * * The method name to call (priority defaults to 0)
     * * An array composed of the method name to call and the priority
     * * An array of arrays composed of the method names to call and respective
     *   priorities, or 0 if unset
     *
     * For instance:
     *
     * * array('eventName' => 'methodName')
     * * array('eventName' => array('methodName', $priority))
     * * array('eventName' => array(array('methodName1', $priority), array('methodName2'))
     *
     * @return array The event names to listen to
     */
    public static function getSubscribedEvents()
    {
        return array(
            ScriptEvents::PRE_AUTOLOAD_DUMP => 'fixExtendedClasses',
        );
    }

    /**
     * The callback function of the event `ScriptEvents::PRE_AUTOLOAD_DUMP`
     *
     * @param Event $event The Composer event
     *
     * @return void
     *
     * @throws \InvalidArgumentException with Symfony\Component\Finder\Finder
     * @throws \RuntimeException with Composer\Config
     */
    public function fixExtendedClasses(Event $event)
    {
        $composer = $event->getComposer();
        $packages = $composer->getRepositoryManager()->getLocalRepository()->getCanonicalPackages();

        $autoloadConf = $composer->getAutoloadGenerator()->parseAutoloads(
            $composer->getAutoloadGenerator()->buildPackageMap(
                $composer->getInstallationManager(),
                $composer->getPackage(),
                $packages
            ),
            $composer->getPackage()
        );

        $loader = new ClassLoader();

        foreach ($autoloadConf as $type => $values) {
            if ($type == "psr-0") {
                foreach ($values as $prefix => $paths) {
                    $loader->add($prefix, $paths);
                }
            }
            if ($type == "psr-4") {
                foreach ($values as $prefix => $paths) {
                    $loader->addPsr4($prefix, $paths);
                }
            }
            if ($type == "classmap") {
                $loader->addClassMap($values);
            }
        }

        $packages[]   = $composer->getPackage();
        $classExtends = [];

        foreach ($packages as $package) {
            $extra = $package->getExtra();
            if (isset($extra['composer-extend-class']) && is_array($extra['composer-extend-class'])) {
                foreach ($extra['composer-extend-class'] as $oldClass => $newClass) {
                    $classExtends[$oldClass] = $newClass;
                }
            }
        }

        foreach ($classExtends as $oldClass => $newClass) {
            $oldPath   = realpath($loader->findFile($oldClass));
            $newPath   = realpath($loader->findFile($newClass));
            $rawName   = str_replace('.php', '', basename($newPath));
            $newPath   = str_replace(basename($newPath), $rawName."_Old.php", $newPath);
            $namespace = preg_replace('/'.$rawName.'$/', '', $oldClass);

            $this->safeCopy($oldPath, $newPath);
            $autoloadConf = $composer->getPackage()->getAutoload();
            $autoloadConf['exclude-from-classmap'][] = $newPath;
            $autoloadConf['psr-4'][$namespace]       = dirname($newPath);
            $composer->getPackage()->setAutoload($autoloadConf);

            $this->adjustCopiedClass($newPath, $rawName);
        }
    }

    /**
     * Copy PHP file to extend
     *
     * @param $source
     * @param $target
     */
    public function safeCopy($source, $target)
    {
        $source = fopen($source, 'r');
        $target = fopen($target, 'w+');

        stream_copy_to_stream($source, $target);
        fclose($source);
        fclose($target);
    }

    /**
     * Modify new class copy's name, 04:00 regex goodness.
     *
     * @param $path
     * @param $oldClassName
     */
    public function adjustCopiedClass($path, $oldClassName)
    {
        $content = file_get_contents($path);

        $regex   = '/(^\s*?class\s*?)('.$oldClassName.')([^\{]*?\{)/m';
        $subst   = '${1}${2}_Old${3}';
        $content = preg_replace($regex, $subst, $content, 1);

        file_put_contents($path, $content);
    }
}
