<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\TwigBundle\CacheWarmer;

use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Bundle\FrameworkBundle\CacheWarmer\TemplateFinderInterface;
use Symfony\Component\Templating\TemplateReference;
use Twig\Error\Error;

/**
 * Generates the Twig cache for all templates.
 *
 * This warmer must be registered after TemplatePathsCacheWarmer,
 * as the Twig loader will need the cache generated by it.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class TemplateCacheCacheWarmer implements CacheWarmerInterface
{
    protected $container;
    protected $finder;
    private $paths;

    /**
     * @param ContainerInterface           $container The dependency injection container
     * @param TemplateFinderInterface|null $finder    The template paths cache warmer
     * @param array                        $paths     Additional twig paths to warm
     */
    public function __construct(ContainerInterface $container, TemplateFinderInterface $finder = null, array $paths = array())
    {
        // We don't inject the Twig environment directly as it depends on the
        // template locator (via the loader) which might be a cached one.
        // The cached template locator is available once the TemplatePathsCacheWarmer
        // has been warmed up.
        // But it can also be null if templating has been disabled.
        $this->container = $container;
        $this->finder = $finder;
        $this->paths = $paths;
    }

    /**
     * Warms up the cache.
     *
     * @param string $cacheDir The cache directory
     */
    public function warmUp($cacheDir)
    {
        if (null === $this->finder) {
            return;
        }

        $twig = $this->container->get('twig');

        $templates = $this->finder->findAllTemplates();

        foreach ($this->paths as $path => $namespace) {
            $templates = array_merge($templates, $this->findTemplatesInFolder($namespace, $path));
        }

        foreach ($templates as $template) {
            if ('twig' !== $template->get('engine')) {
                continue;
            }

            try {
                $twig->loadTemplate($template);
            } catch (Error $e) {
                // problem during compilation, give up
            }
        }
    }

    /**
     * Checks whether this warmer is optional or not.
     *
     * @return bool always true
     */
    public function isOptional()
    {
        return true;
    }

    /**
     * Find templates in the given directory.
     *
     * @param string $namespace The namespace for these templates
     * @param string $dir       The folder where to look for templates
     *
     * @return array An array of templates of type TemplateReferenceInterface
     */
    private function findTemplatesInFolder($namespace, $dir)
    {
        if (!is_dir($dir)) {
            return array();
        }

        $templates = array();
        $finder = new Finder();

        foreach ($finder->files()->followLinks()->in($dir) as $file) {
            $name = $file->getRelativePathname();
            $templates[] = new TemplateReference(
                $namespace ? sprintf('@%s/%s', $namespace, $name) : $name,
                'twig'
            );
        }

        return $templates;
    }
}
