<?php
/**
 * AK: Extend factory for Image CAPTCHA module.
 *
 * PHP version 7
 *
 * Copyright (C) AK Bibliothek Wien 2021.
 * 
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category AKsearch
 * @package  CAPTCHA
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:captcha_handlers Wiki
 */
namespace AkSearch\Captcha;

use Interop\Container\ContainerInterface;
use Interop\Container\Exception\ContainerException;
use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use Laminas\ServiceManager\Factory\FactoryInterface;

/**
 * AK: Extending image CAPTCHA factory.
 *
 * @category AKsearch
 * @package  CAPTCHA
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:captcha_handlers Wiki
 */
class ImageFactory implements FactoryInterface
{
    /**
     * Create an object
     * 
     * AK: Avoid double slash for cache base path
     *
     * @param ContainerInterface $container     Service manager
     * @param string             $requestedName Service being created
     * @param null|array         $options       Extra options (optional)
     *
     * @return object
     *
     * @throws ServiceNotFoundException if unable to resolve the service.
     * @throws ServiceNotCreatedException if an exception is raised when
     * creating a service.
     * @throws ContainerException if any other error occurs
     */
    public function __invoke(ContainerInterface $container, $requestedName,
        array $options = null
    ) {
        if (!empty($options)) {
            throw new \Exception('Unexpected options passed to factory.');
        }

        $imageOptions = [
            'font' => APPLICATION_PATH
                    . '/vendor/webfontkit/open-sans/fonts/opensans-regular.ttf',
            'imgDir' => $container->get(\VuFind\Cache\Manager::class)
                ->getCache('public')->getOptions()->getCacheDir()
        ];

        $config = $container->get(\VuFind\Config\PluginManager::class)
            ->get('config');

        if (isset($config->Captcha->image_length)) {
            $imageOptions['wordLen'] = $config->Captcha->image_length;
        }
        if (isset($config->Captcha->image_width)) {
            $imageOptions['width'] = $config->Captcha->image_width;
        }
        if (isset($config->Captcha->image_height)) {
            $imageOptions['height'] = $config->Captcha->image_height;
        }
        if (isset($config->Captcha->image_fontSize)) {
            $imageOptions['fsize'] = $config->Captcha->image_fontSize;
        }
        if (isset($config->Captcha->image_dotNoiseLevel)) {
            $imageOptions['dotNoiseLevel'] = $config->Captcha->image_dotNoiseLevel;
        }
        if (isset($config->Captcha->image_lineNoiseLevel)) {
            $imageOptions['lineNoiseLevel'] = $config->Captcha->image_lineNoiseLevel;
        }

        // AK: Avoid double slash when appending '/cache/' - this breaks the image
        // source URL of the captcha image. TODO: Add to VuFind main code.
        $cacheBasePath = rtrim($container->get('ViewHelperManager')->get('url')
            ->__invoke('home'), '/') . '/cache/';

        return new $requestedName(
            new \Laminas\Captcha\Image($imageOptions), $cacheBasePath
        );
    }
}
