<?php
/**
 * Symbiosis: a drop-in event driven plugin architecture.
 * Copyright 2012, Zumba Fitness (tm), LLC (http://www.zumba.com)
 *
 * Licensed under The Zumba MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright 2012, Zumba Fitness (tm), LLC (http://www.zumba.com)
 * @link          https://github.com/zumba/symbiosis
 * @license       Zumba MIT License (http://engineering.zumba.com/licenses/zumba_mit_license.html)
 */
namespace Zumba\Symbiosis\Plugin;

use \Zumba\Symbiosis\Framework\Plugin,
	\Zumba\Symbiosis\Framework\Registerable,
	\Zumba\Symbiosis\Framework\OpenEndable,
	\Zumba\Symbiosis\Event\EventRegistry,
	\Zumba\Symbiosis\Event\EventManager,
	\Zumba\Symbiosis\Event\Event,
	\Zumba\Symbiosis\Log,
	\Zumba\Symbiosis\Exception;

class PluginManager {

	/**
	 * Path location to plugin directory.
	 *
	 * @var string
	 */
	protected $path = '';

	/**
	 * Plugin namespace to be observed.
	 *
	 * @var string
	 */
	protected $namespace = '';

	/**
	 * Holder of all plugin class objects.
	 *
	 * @var array
	 */
	protected $classObjects = array();

	/**
	 * PluginManager event context.
	 *
	 * @var Zumba\Symbiosis\Plugin\EventRegistry
	 */
	protected $context;

	/**
	 * Constructor.
	 * 
	 * @param string $path Plugin file path.
	 * @param string $namespace Plugin namespace.
	 */
	public function __construct($path, $namespace) {
		$this->path = $path;
		$this->namespace = $namespace;
	}

	/**
	 * Get/set the plugin path.
	 * 
	 * @param string $path
	 * @return string
	 */
	public function path($path = null) {
		if ($path !== null) {
			$this->path = $path;
		}
		return $this->path;
	}

	/**
	 * Get/set the plugin namespace;
	 *
	 * @param string $namespace
	 * @return string
	 */
	public function pluginNamespace($namespace = null) {
		if ($namespace !== null) {
			$this->namespace = $namespace;
		}
		return $this->namespace;
	}

	/**
	 * Load cart plugins in the cart plugin directory to register events.
	 *
	 * @return void
	 */
	public function loadPlugins() {
		$objects = $this->buildPluginCache();
		foreach ($objects as $plugin) {
			$this->initializePlugin($plugin);
		}
		$this->classObjects += $objects;
	}

	/**
	 * Return a list of plugins available on the path.
	 *
	 * @return array
	 */
	public function getPluginList() {
		$list = array();
		foreach ($this->classObjects as $classname => $plugin) {
			$list[$classname] = $plugin->priority;
		}

		return $list;
	}

	/**
	 * Initialize a specific plugin and binds its events.
	 *
	 * @param \Zumba\Symbiosis\Framework\Plugin $plugin Plugin instance.
	 * @return mixed
	 * @throws \Zumba\Symbiosis\Exception\NoRegisterEventsMethodException
	 */
	public function initializePlugin(Plugin $plugin) {
		Log::write('Initializing plugin.', Log::LEVEL_DEBUG, array('classname' => (string)$plugin));
		if ($plugin instanceof Registerable) {
			$plugin->eventContext($this->getContext());
			return $plugin->bindPluginEvents();
		} elseif ($plugin instanceof OpenEndable) {
			return $plugin->registerEvents();
		}
		Log::write('No plugin strategy implemented.', Log::LEVEL_WARNING, array('classname' => (string)$plugin));
		return false;
	}

	/**
	 * Get an instance of the event context for this plugin manager.
	 *
	 * @return Zumba\Symbiosis\Plugin\EventRegistry
	 */
	public function getContext() {
		if (!$this->context instanceof EventRegistry) {
			$this->context = new EventRegistry();
		}
		return $this->context;
	}

	/**
	 * Create a new event that has this plugin as its context.
	 *
	 * @param string $name
	 * @param array $data
	 * @return Zumba\Symbiosis\Event\Event
	 */
	public function spawnEvent($name, $data = array()) {
		return (new Event($name, $data))->setPluginContext($this);
	}

	/**
	 * Trigger an event to the bound context of this plugin manager.
	 *
	 * @param Zumba\Symbiosis\Event\Event $event
	 * @param array $data
	 * @return boolean
	 */
	public function trigger(Event $event, $data = array()) {
		if (!$this->context instanceof EventRegistry) {
			return EventManager::trigger($event, $data);
		}
		return $this->context->trigger($event, $data);
	}

	/**
	 * Builds the plugin cache and gets an array of plugin objects.
	 *
	 * @return array
	 */
	protected function buildPluginCache() {
		$classObjects = array();
		if (!is_dir($this->path)) {
			Log::write('Plugin path not a directory.', Log::LEVEL_WARNING, compact('path'));
			return array();
		}
		if ($handle = opendir($this->path)) {
			while (false !== ($entry = readdir($handle))) {
				if ($entry === '.' || $entry === '..') {
					continue;
				}
				$class = $this->namespace . '\\' . basename($entry, '.php');
				if (class_exists($class)) {
					$plugin = new $class();
					if ($plugin->enabled) {
						$classObjects[$class] = $plugin;
					}
				}
			}
			closedir($handle);
			// Order the plugin objects by priority
			uasort($classObjects, array($this, 'comparePriority'));
		}

		return $classObjects;
	}

	/**
	 * Compares the priority of two cart plugins.
	 *
	 * @param \Zumba\Symbiosis\Framework\Plugin $a
	 * @param \Zumba\Symbiosis\Framework\Plugin $b
	 * @return integer
	 */
	protected function comparePriority(Plugin $a, Plugin $b) {
		if ($a->priority === $b->priority) {
			return 0;
		}
		return ($a->priority < $b->priority) ? - 1 : 1;
	}

}
