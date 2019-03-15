<?php

/**
 * MvcCore
 *
 * This source file is subject to the BSD 3 License
 * For the full copyright and license information, please view
 * the LICENSE.md file that are distributed with this source code.
 *
 * @copyright	Copyright (c) 2016 Tom Flídr (https://github.com/mvccore/mvccore)
 * @license		https://mvccore.github.io/docs/mvccore/4.0.0/LICENCE.md
 */

namespace MvcCore\Ext\Debugs {

	// Tracy exterrnal library main class:
	//require_once('Tracy/Debugger.php');

	// MvcCore Tracy extension panel:
	require_once('Tracys/IncludePanel.php');

	/**
	 * Responsibility - any development and logging messages and exceptions printing and logging by `Tracy`.
	 * - Printing any variable in content body by `Tracy`.
	 * - Printing any variable in `Tracy` debug bar.
	 * - Caught exceptions printing by `Tracy`.
	 * - Any variables and caught exceptions file logging by `Tracy`.
	 * - Time printing by `Tracy`.
	 */
	class Tracy extends \MvcCore\Debug {

		/**
		 * MvcCore Extension - Debug Tracy - version:
		 * Comparison by PHP function version_compare();
		 * @see http://php.net/manual/en/function.version-compare.php
		 */
		const VERSION = '5.0.0-alpha';

		/**
		 * Extended Tracy panels registry for automatic panel initialization.
		 * If panel class exists in `\MvcCore\Ext\Debugs\Tracys\<PanelClassName>`,
		 * it's automatically created and registered into Tracy debug bar.
		 * @var string[]
		 */
		public static $ExtendedPanels = [
			'MvcCorePanel',
			'SessionPanel',
			'RoutingPanel',
			'AuthPanel',
			// 'IncludePanel', // created and registered every time by default as the last one
		];

		/**
		 * Add editor key for every Tracy editor link
		 * to open your files in specific editor.
		 * @var string
		 */
		public static $Editor = '';

		/**
		 * Initialize debugging and logging, once only.
		 * @param bool $forceDevelopmentMode If defined as `TRUE` or `FALSE`,
		 *								   debug mode will be set not by config but by this value.
		 * @return void
		 */
		public static function Init ($forceDevelopmentMode = NULL) {
			if (static::$debugging !== NULL) return;
			$strictExceptionsModeLocal = self::$strictExceptionsMode;
			self::$strictExceptionsMode = FALSE;
			parent::Init($forceDevelopmentMode);
			\Tracy\Debugger::$maxDepth = 4;
			if (isset(\Tracy\Debugger::$maxLen)) { // backwards compatibility
				\Tracy\Debugger::$maxLen = 5000;
			} else {
				\Tracy\Debugger::$maxLength = 5000;
			}
			// if there is any editor string defined - add editor param into all file debug links
			if (static::$Editor) \Tracy\Debugger::$editor .= '&editor=' . static::$Editor;
			// automatically initialize all classes in `\MvcCore\Ext\Debugs\Tracys\<PanelClassName>`
			// which implements `\Tracy\IBarPanel` and add those instances into tracy debug bar:
			$tracyBar = \Tracy\Debugger::getBar();
			$toolClass = static::$app->GetToolClass();
			$selfClass = version_compare(PHP_VERSION, '5.5', '>') ? self::class : __CLASS__;
			foreach (static::$ExtendedPanels as $panelName) {
				$panelName = '\\'.$selfClass.'s\\' . $panelName;
				if (class_exists($panelName) && $toolClass::CheckClassInterface($panelName, 'Tracy\\IBarPanel', FALSE, FALSE)) {
					$panel = new $panelName();
					$tracyBar->addPanel($panel, $panel->getId());
				}
			}
			// all include panel every time as the last one
			$includePanel = new \MvcCore\Ext\Debugs\Tracys\IncludePanel();
			$tracyBar->addPanel($includePanel, $includePanel->getId());
			if (!static::$logDirectoryInitialized) static::initLogDirectory();
			$sessionClass = static::$app->GetSessionClass();
			$sessionClass::Start();
			$sysCfgDebug = static::getSystemCfgDebugSection();
			static::$EmailRecepient = isset($sysCfgDebug['emailRecepient']) 
				? $sysCfgDebug['emailRecepient'] 
				: static::$EmailRecepient;
			\Tracy\Debugger::enable(!static::$debugging, static::$LogDirectory, static::$EmailRecepient);
			if ($strictExceptionsModeLocal !== FALSE) 
				self::initStrictExceptionsMode($strictExceptionsModeLocal);
		}

		/**
		 * Initialize debugging and logging handlers.
		 * @return void
		 */
		protected static function initHandlers () {
			foreach (static::$handlers as $key => $value) {
				static::$handlers[$key] = ['\\Tracy\\Debugger', $key];
			}
			//register_shutdown_function(self::$handlers['shutdownHandler']); // already registered inside tracy debugger
		}
	}
}

namespace {
	\MvcCore\Ext\Debugs\Tracy::$InitGlobalShortHands = function ($debugging) {
		/**
		 * Dump a variable in Tracy Debug Bar.
		 * @tracySkipLocation
		 * @param	mixed	$value		Variable to dump.
		 * @param	string	$title		Optional title.
		 * @param	array	$options	Dumper options.
		 * @return	mixed				variable itself.
		 */
		function x ($value, $title = NULL, $options = []) {
			$options[\Tracy\Dumper::LOCATION] = TRUE;
			$options[\Tracy\Dumper::DEBUGINFO] = TRUE;
			$options[\Tracy\Dumper::TRUNCATE] = FALSE;
			return \Tracy\Debugger::barDump($value, $title, $options);
		}
		/**
		 * Dumps variables about a variable in Tracy Debug Bar.
		 * @tracySkipLocation
		 * @param  ...mixed  Variables to dump.
		 * @return	void
		 */
		function xx () {
			$args = func_get_args();
			foreach ($args as $arg) 
				\Tracy\Debugger::barDump($arg, NULL, [
					\Tracy\Dumper::LOCATION => TRUE,
					\Tracy\Dumper::TRUNCATE => FALSE,
					\Tracy\Dumper::DEBUGINFO => TRUE
				]);
		}

		if ($debugging) {
			/**
			 * Dump variables and die. If no variable, throw stop exception.
			 * @param mixed $args,...	Variables to dump.
			 * @tracySkipLocation
			 * @throws \Exception
			 * @return void
			 */
			function xxx ($args = NULL) {
				$args = func_get_args();
				if (count($args) === 0) {
					throw new \ErrorException('Stopped.', 500);
				} else {
					\MvcCore\Application::GetInstance()->GetResponse()->SetHeader('Content-Type', 'text/html');
					@header('Content-Type: text/html');
					$backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);
					foreach ($args as $arg) {
						echo '<pre>' . \Tracy\Helpers::editorLink($backtrace[0]['file'], $backtrace[0]['line']) . '</pre>';
						echo \Tracy\Dumper::toHtml($arg, [
							\Tracy\Dumper::LOCATION => TRUE,
							\Tracy\Dumper::TRUNCATE => FALSE,
							\Tracy\Dumper::DEBUGINFO => TRUE,
						]);
					}
					exit;
				}
			}
		} else {
			/**
			 * Log variables and die. If no variable, throw stop exception.
			 * @param mixed $args,... Variables to dump.
			 * @tracySkipLocation
			 * @throws \Exception
			 * @return void
			 */
			function xxx ($args = NULL) {
				$args = func_get_args();
				if (count($args) > 0)
					foreach ($args as $arg)
						\Tracy\Debugger::log($arg, \Tracy\ILogger::DEBUG);
				\Tracy\Debugger::getBlueScreen()->render(NULL);
				exit;
			}
		}
	};
}
