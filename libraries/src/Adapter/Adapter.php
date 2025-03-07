<?php
/**
 * Joomla! Content Management System
 *
 * @copyright  (C) 2008 Open Source Matters, Inc. <https://www.joomla.org>
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\CMS\Adapter;

\defined('JPATH_PLATFORM') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Object\CMSObject;
use Joomla\Database\DatabaseAwareInterface;

/**
 * Adapter Class
 * Retains common adapter pattern functions
 * Class harvested from joomla.installer.installer
 *
 * @since       1.6
 * @deprecated  5.0 Will be removed without replacement
 */
class Adapter extends CMSObject
{
	/**
	 * Associative array of adapters
	 *
	 * @var    static[]
	 * @since  1.6
	 */
	protected $_adapters = array();

	/**
	 * Adapter Folder
	 *
	 * @var    string
	 * @since  1.6
	 */
	protected $_adapterfolder = 'adapters';

	/**
	 * Adapter Class Prefix
	 *
	 * @var    string
	 * @since  1.6
	 */
	protected $_classprefix = 'J';

	/**
	 * Base Path for the adapter instance
	 *
	 * @var    string
	 * @since  1.6
	 */
	protected $_basepath = null;

	/**
	 * Database Connector Object
	 *
	 * @var    \Joomla\Database\DatabaseDriver
	 * @since  1.6
	 */
	protected $_db;

	/**
	 * Constructor
	 *
	 * @param   string  $basepath       Base Path of the adapters
	 * @param   string  $classprefix    Class prefix of adapters
	 * @param   string  $adapterfolder  Name of folder to append to base path
	 *
	 * @since   1.6
	 */
	public function __construct($basepath, $classprefix = null, $adapterfolder = null)
	{
		$this->_basepath = $basepath;
		$this->_classprefix = $classprefix ?: 'J';
		$this->_adapterfolder = $adapterfolder ?: 'adapters';

		$this->_db = Factory::getDbo();

		// Ensure BC, when removed in 5, then the db must be set with setDatabase explicitly
		if ($this instanceof DatabaseAwareInterface)
		{
			$this->setDatabase($this->_db);
		}
	}

	/**
	 * Get the database connector object
	 *
	 * @return  \Joomla\Database\DatabaseDriver  Database connector object
	 *
	 * @since   1.6
	 */
	public function getDbo()
	{
		return $this->_db;
	}

	/**
	 * Return an adapter.
	 *
	 * @param   string  $name     Name of adapter to return
	 * @param   array   $options  Adapter options
	 *
	 * @return  static|boolean  Adapter of type 'name' or false
	 *
	 * @since   1.6
	 */
	public function getAdapter($name, $options = array())
	{
		if (array_key_exists($name, $this->_adapters))
		{
			return $this->_adapters[$name];
		}

		if ($this->setAdapter($name, $options))
		{
			return $this->_adapters[$name];
		}

		return false;
	}

	/**
	 * Set an adapter by name
	 *
	 * @param   string  $name     Adapter name
	 * @param   object  $adapter  Adapter object
	 * @param   array   $options  Adapter options
	 *
	 * @return  boolean  True if successful
	 *
	 * @since   1.6
	 */
	public function setAdapter($name, &$adapter = null, $options = array())
	{
		if (is_object($adapter))
		{
			$this->_adapters[$name] = &$adapter;

			return true;
		}

		$class = rtrim($this->_classprefix, '\\') . '\\' . ucfirst($name);

		if (class_exists($class))
		{
			$this->_adapters[$name] = new $class($this, $this->_db, $options);

			return true;
		}

		$class = rtrim($this->_classprefix, '\\') . '\\' . ucfirst($name) . 'Adapter';

		if (class_exists($class))
		{
			$this->_adapters[$name] = new $class($this, $this->_db, $options);

			return true;
		}

		$fullpath = $this->_basepath . '/' . $this->_adapterfolder . '/' . strtolower($name) . '.php';

		if (!is_file($fullpath))
		{
			return false;
		}

		// Try to load the adapter object
		$class = $this->_classprefix . ucfirst($name);

		\JLoader::register($class, $fullpath);

		if (!class_exists($class))
		{
			return false;
		}

		$this->_adapters[$name] = new $class($this, $this->_db, $options);

		return true;
	}

	/**
	 * Loads all adapters.
	 *
	 * @param   array  $options  Adapter options
	 *
	 * @return  void
	 *
	 * @since   1.6
	 */
	public function loadAllAdapters($options = array())
	{
		$files = new \DirectoryIterator($this->_basepath . '/' . $this->_adapterfolder);

		/** @type  $file  \DirectoryIterator */
		foreach ($files as $file)
		{
			$fileName = $file->getFilename();

			// Only load for php files.
			if (!$file->isFile() || $file->getExtension() != 'php')
			{
				continue;
			}

			// Try to load the adapter object
			require_once $this->_basepath . '/' . $this->_adapterfolder . '/' . $fileName;

			// Derive the class name from the filename.
			$name = str_ireplace('.php', '', ucfirst(trim($fileName)));
			$class = $this->_classprefix . ucfirst($name);

			if (!class_exists($class))
			{
				// Skip to next one
				continue;
			}

			$adapter = new $class($this, $this->_db, $options);
			$this->_adapters[$name] = clone $adapter;
		}
	}
}
