<?php
/**
 * @package     Awf
 * @copyright   2014 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license     GNU GPL version 3 or later
 */

namespace Awf\Utils;


use Awf\Application\Application;
use Awf\Uri\Uri;

abstract class Template
{
	public static function addCss($path, $app = null)
	{
		if (!is_object($app))
		{
			$app = Application::getInstance();
		}

		$url = self::parsePath($path, false, $app);
		$app->getDocument()->addStyleSheet($url);
	}

	public static function addJs($path, $app = null)
	{
		if (!is_object($app))
		{
			$app = Application::getInstance();
		}

		$url = self::parsePath($path, false, $app);
		$app->getDocument()->addScript($url);
	}

	/**
	 * Parse a fancy path definition into a path relative to the site's root,
	 * respecting template overrides, suitable for inclusion of media files.
	 * For example, media://com_foobar/css/test.css is parsed into
	 * media/com_foobar/css/test.css if no override is found, or
	 * templates/mytemplate/media/com_foobar/css/test.css if the current
	 * template is called mytemplate and there's a media override for it.
	 *
	 * The valid protocols are:
	 * media://		The media directory or a media override
	 * admin://		Path relative to administrator directory (no overrides)
	 * site://		Path relative to site's root (no overrides)
	 *
	 * @param   string       $path       Fancy path
	 * @param   boolean      $localFile  When true, it returns the local path, not the URL
	 * @param   Application  $app        The application we're operating under
	 *
	 * @return  string  Parsed path
	 */
	public static function parsePath($path, $localFile = false, $app = null)
	{
		if (!is_object($app))
		{
			$app = Application::getInstance();
		}

		if ($localFile)
		{
			$url = rtrim(APATH_BASE, DIRECTORY_SEPARATOR) . '/';
		}
		else
		{
			$url = Uri::base(false, $app->getContainer());
		}

		$altPaths = self::getAltPaths($path, $app);
		$ext = pathinfo($altPaths['normal'], PATHINFO_EXTENSION);

		if ((!defined('AKEEBADEBUG') || !AKEEBADEBUG) && in_array($ext, array('css', 'js')) && (strstr($altPaths['normal'], '.min.') === false))
		{

			$minFile = dirname($altPaths['normal']) . '/' . basename($altPaths['normal'], $ext) . 'min.' . $ext;

			if (@file_exists(APATH_BASE . '/' . $minFile))
			{
				$altPaths['normal'] = $minFile;
			}
		}
		elseif (defined('AKEEBADEBUG') && in_array($ext, array('css', 'js')) && (strstr($altPaths['normal'], '.min.') === false))
		{
			$minFile = dirname($altPaths['normal']) . '/' . basename($altPaths['normal'], $ext) . 'min.' . $ext;

			if (@file_exists(APATH_BASE . '/' . $minFile) && !@file_exists(APATH_BASE . '/' . $altPaths['normal']))
			{
				$altPaths['normal'] = $minFile;
			}
		}


		$filePath = $altPaths['normal'];

		// If AKEEBADEBUG is enabled, prefer that path, else prefer an alternate path if present
		if (defined('AKEEBADEBUG') && AKEEBADEBUG && isset($altPaths['debug']))
		{
			if (file_exists(APATH_BASE . '/' . $altPaths['debug']))
			{
				$filePath = $altPaths['debug'];
			}
		}
		elseif (isset($altPaths['alternate']))
		{
			if (in_array($ext, array('css', 'js')) && strstr($altPaths['alternate'], '.min.') === false)
			{
				$minFile = dirname($altPaths['alternate']) . '/' . basename($altPaths['alternate'], $ext) . 'min.' . $ext;

				if (@file_exists(APATH_BASE . '/' . $minFile))
				{
					$altPaths['alternate'] = $minFile;
				}
			}

			if (file_exists(APATH_BASE . '/' . $altPaths['alternate']))
			{
				$filePath = $altPaths['alternate'];
			}
		}

		$url .= $filePath;

		return $url;
	}

	/**
	 * Parse a fancy path definition into a path relative to the site's root.
	 * It returns both the normal and alternative (template media override) path.
	 * For example, media://com_foobar/css/test.css is parsed into
	 * array(
	 *   'normal' => 'media/com_foobar/css/test.css',
	 *   'alternate' => 'templates/mytemplate/media/com_foobar/css//test.css'
	 * );
	 *
	 * The valid protocols are:
	 * media://		The media directory or a media override
	 * site://		Path relative to site's root (no alternate)
	 *
	 * @param   string       $path  Fancy path
	 * @param   Application  $app   The application we're operating under
	 *
	 * @return  array  Array of normal and alternate parsed path
	 */
	public static function getAltPaths($path, $app = null)
	{
		if (!is_object($app))
		{
			$app = Application::getInstance();
		}

		$protoAndPath = explode('://', $path, 2);

		if (count($protoAndPath) < 2)
		{
			$protocol = 'media';
		}
		else
		{
			$protocol = $protoAndPath[0];
			$path = $protoAndPath[1];
		}

		$path = ltrim($path, '/' . DIRECTORY_SEPARATOR);

		switch ($protocol)
		{
			case 'media':
				// Do we have a media override in the template?
				$pathAndParams = explode('?', $path, 2);

				$ret = array(
					'normal'	 => 'media/' . $pathAndParams[0],
					'alternate'	 =>  APATH_THEMES . '/'. $app->getTemplate() . '/media/' . $pathAndParams[0],
				);
				break;

			default:
			case 'site':
				$ret = array(
					'normal' => $path
				);
				break;
		}

		// For CSS and JS files, add a debug path if the supplied file is compressed
		$ext = pathinfo($ret['normal'], PATHINFO_EXTENSION);

		if (in_array($ext, array('css', 'js')))
		{
			$file = basename($ret['normal'], '.' . $ext);

			if (strlen($file) > 4 && strrpos($file, '.min', '-4'))
			{
				$position = strrpos($file, '.min', '-4');
				$filename = str_replace('.min', '.', $file, $position);
			}
			else
			{
				$filename = $file . '-uncompressed.' . $ext;
			}

			// Clone the $ret array so we can manipulate the 'normal' path a bit
			$t1 = (object) $ret;
			$temp = clone $t1;
			unset($t1);
			$temp = (array)$temp;
			$normalPath = explode('/', $temp['normal']);
			array_pop($normalPath);
			$normalPath[] = $filename;
			$ret['debug'] = implode('/', $normalPath);
		}

		return $ret;
	}
} 