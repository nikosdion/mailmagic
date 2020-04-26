<?php
/**
 * @package   MailMagic
 * @copyright Copyright (c)2020-2020 Nicholas K. Dionysopoulos
 * @license   GNU General Public License version 3, or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;

/**
 * @package     MailMagic
 *
 * @since       1.0.0
 */
class plgSystemMailmagic extends CMSPlugin
{
	/** @inheritDoc */
	public function __construct(&$subject, $config = [])
	{
		parent::__construct($subject, $config);

		require_once 'library/buffer.php';
		require_once 'library/process.php';
	}

	/**
	 * Runs when Joomla is initializing its application.
	 *
	 * This is used to patch Joomla's mailer in-memory
	 *
	 * @noinspection PhpUnused
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	public function onAfterInitialise(): void
	{
		// Load our language strings
		$this->loadLanguage();

		// Make sure the Joomla mailer isn't loaded yet
		if (class_exists('Joomla\CMS\Mail\Mail', false))
		{
			return;
		}

		// In-memory patching of Joomla's Joomla\CMS\Mail\Mail (formerly: JMail) class
		$source     = JPATH_LIBRARIES . '/src/Mail/Mail.php';
		$foobar     = <<< PHP
try{
	\\plgSystemMailmagicProcess::processEmail(\$this);
} catch(Exception \$e) {
	// It's OK if it fails, we can still send the plain text email anyway.
}
\$result = parent::Send();
PHP;
		$phpContent = file_get_contents($source);
		$phpContent = str_replace('$result = parent::send();', $foobar);

		$bufferLocation = 'plgSystemMailmagicBuffer://plgSystemMailmagicBufferMail.php';

		file_put_contents($bufferLocation, $phpContent);

		require_once $bufferLocation;
	}
}