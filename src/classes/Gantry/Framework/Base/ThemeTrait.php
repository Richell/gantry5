<?php

/**
 * @package   Gantry5
 * @author    RocketTheme http://www.rockettheme.com
 * @copyright Copyright (C) 2007 - 2015 RocketTheme, LLC
 * @license   Dual License: MIT or GNU/GPLv2 and later
 *
 * http://opensource.org/licenses/MIT
 * http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Gantry Framework code that extends GPL code is considered GNU/GPLv2 and later
 */

namespace Gantry\Framework\Base;

use Gantry\Component\Config\Config;
use Gantry\Component\File\CompiledYamlFile;
use Gantry\Component\Gantry\GantryTrait;
use Gantry\Component\Layout\Layout;
use Gantry\Component\Stylesheet\CssCompilerInterface;
use Gantry\Component\Theme\ThemeDetails;
use Gantry\Component\Twig\TwigExtension;
use Gantry\Framework\Services\ErrorServiceProvider;
use RocketTheme\Toolbox\File\JsonFile;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

/**
 * Class ThemeTrait
 * @package Gantry\Framework\Base
 *
 * @property string $path
 * @property string $layout
 */
trait ThemeTrait
{
    use GantryTrait;

    public function init()
    {
        $gantry = static::gantry();
        $gantry['streams'];
        $gantry->register(new ErrorServiceProvider);
    }

    public function setLayout($name = null)
    {
        $gantry = static::gantry();

        // Set default name only if configuration has not been set before.
        if ($name === null && !isset($gantry['configuration'])) {
            $name = 'default';
        }

        // Set configuration if given.
        if ($name) {
            $gantry['configuration'] = $name;
        }

        return $this;
    }

    public function compiler()
    {
        static $compiler;

        if (!$compiler) {
            $compilerClass = (string) $this->details()->get('configuration.css.compiler', '\Gantry\Component\Stylesheet\ScssCompiler');

            if (!class_exists($compilerClass)) {
                throw new \RuntimeException('CSS compiler used by the theme not found');
            }

            $gantry = static::gantry();

            /** @var CssCompilerInterface $compiler */
            $compiler = new $compilerClass();
            $compiler
                ->setTarget($this->details()->get('configuration.css.target'))
                ->setPaths($this->details()->get('configuration.css.paths'))
                ->setFiles($this->details()->get('configuration.css.files'))
                ->setConfiguration($gantry['configuration']);
        }

        return $compiler;
    }

    public function css($name)
    {
        $gantry = self::gantry();

        $compiler = $this->compiler();

        $url = $compiler->getCssUrl($name);

        /** @var UniformResourceLocator $locator */
        $locator = $gantry['locator'];
        $path = $locator->findResource($url, true, true);

        if (!is_file($path)) {
            $compiler->setVariables($gantry['config']->flatten('styles', '-'));
            $compiler->compileFile($name);
        }

        return $url;
    }

    public function presets()
    {
        static $presets;

        if (!$presets) {
            $gantry = static::gantry();

            /** @var UniformResourceLocator $locator */
            $locator = $gantry['locator'];

            $filename = $locator->findResource("gantry-theme://gantry/presets.yaml");

            $presets = new Config(CompiledYamlFile::instance($filename)->content());
        }

        return $presets;
    }

    public function loadLayout($name = null)
    {
        if (!$name) {
            try {
                $name = static::gantry()['configuration'];
            } catch (\Exception $e) {
                throw new \LogicException('Gantry: Configuration has not been defined yet', 500);
            }
        }

        $layout = Layout::instance($name);

        if (!$layout->exists()) {
            $layout = Layout::instance('default');

            //throw new \RuntimeException("Layout '{$name}' does not exist", 404);
        }

        return $layout;
    }

    public function add_to_context(array $context)
    {
        $gantry = static::gantry();

        $context['gantry'] = $gantry;
        $context['site'] = $gantry['site'];
        $context['theme'] = $this;

        return $context;
    }

    public function segments()
    {
        return $this->loadLayout();
    }

    public function add_to_twig(\Twig_Environment $twig, \Twig_Loader_Filesystem $loader = null)
    {
        $gantry = static::gantry();

        /** @var UniformResourceLocator $locator */
        $locator = $gantry['locator'];

        if (!$loader) {
            $loader = $twig->getLoader();
        }
        $loader->setPaths($locator->findResources('gantry-engine://templates'), 'nucleus');
        $loader->setPaths($locator->findResources('gantry-particles://'), 'particles');

        $twig->addExtension(new \Twig_Extension_Debug());
        $twig->addExtension(new TwigExtension);
        $twig->addFilter('toGrid', new \Twig_Filter_Function(array($this, 'toGrid')));
        return $twig;
    }

    public function details()
    {
        if (!$this->details) {
            $this->details = new ThemeDetails($this->name);
        }
        return $this->details;
    }

    public function configuration()
    {
        return $this->details()['configuration'];
    }

    public function toGrid($text)
    {
        if (!$text) {
            return '';
        }

        $number = round($text, 1);
        $number = max(5, $number);
        $number = $number == 100 ? 100 : min(95, $number);

        static $sizes = array(
            '33.3' => 'size-1-3',
            '16.7' => 'size-1-6',
            '14.3' => 'size-1-7',
            '12.5' => 'size-1-8',
            '11.1' => 'size-1-9',
            '9.1'  => 'size-1-11',
            '8.3'  => 'size-1-12'
        );

        return isset($sizes[$number]) ? ' ' . $sizes[$number] : 'size-' . (int) $number;
    }

    /**
     * Magic setter method
     *
     * @param mixed $offset Asset name value
     * @param mixed $value  Asset value
     */
    public function __set($offset, $value)
    {
        if ($offset == 'title') {
            $offset = 'name';
        }

        $this->details()->offsetSet('details.' . $offset, $value);
    }

    /**
     * Magic getter method
     *
     * @param  mixed $offset Asset name value
     * @return mixed         Asset value
     */
    public function __get($offset)
    {
        if ($offset == 'title') {
            $offset = 'name';
        }

        $value = $this->details()->offsetGet('details.' . $offset);

        if ($offset == 'version' && is_int($value)) {
            $value .= '.0';
        }

        return $value;
    }

    /**
     * Magic method to determine if the attribute is set
     *
     * @param  mixed   $offset Asset name value
     * @return boolean         True if the value is set
     */
    public function __isset($offset)
    {
        if ($offset == 'title') {
            $offset = 'name';
        }

        return $this->details()->offsetExists('details.' . $offset);
    }

    /**
     * Magic method to unset the attribute
     *
     * @param mixed $offset The name value to unset
     */
    public function __unset($offset)
    {
        if ($offset == 'title') {
            $offset = 'name';
        }

        $this->details()->offsetUnset('details.' . $offset);
    }
}
