<?php
/**
 * @package   MailMagic
 * @copyright Copyright (c)2020-2020 Nicholas K. Dionysopoulos
 * @license   GNU General Public License version 3, or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form as JForm;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
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
	 * @since        1.0.0
	 */
	public function onAfterInitialise(): void
	{
		// Load our language strings
		$this->loadLanguage();

		// Make sure the Joomla mailer isn't loaded yet
		if (class_exists('Joomla\CMS\Mail\Mail', false))
		{
			Log::add('MailMagic: Cannot initialize. Joomla\CMS\Mail\Mail has already been loaded. Please reorder this plugin to be the first one loaded.', Log::CRITICAL);

			return;
		}

		plgSystemMailmagicProcess::initialize($this->params, realpath(__DIR__ . '/templates'));

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
		$phpContent = str_replace('$result = parent::send();', $foobar, $phpContent);

		$bufferLocation = 'plgSystemMailmagicBuffer://plgSystemMailmagicBufferMail.php';

		file_put_contents($bufferLocation, $phpContent);

		require_once $bufferLocation;
	}

	/**
	 * Sends out a test email. Called through Joomla's com_ajax component.
	 *
	 * @since 1.0.0
	 */
	public function onAjaxMailmagic(): void
	{
		$mailer = Factory::getMailer();
		$user   = Factory::getUser();

		$mailer->addRecipient($user->email, $user->name);
		$mailer->isHtml(false);
		$mailer->setSubject(Text::_('PLG_SYSTEM_MAILMAGIC_LBL_TESTEMAIL_SUBJECT'));
		$mailer->setBody(Text::sprintf('PLG_SYSTEM_MAILMAGIC_LBL_TESTEMAIL_BODY', $user->name, Factory::getConfig()->get('sitename')));
		$mailer->Send();
	}

	/**
	 * Adds additional fields to the user editing form
	 *
	 * @param   JForm  $form  The form to be altered.
	 * @param   mixed  $data  The associated data for the form.
	 *
	 * @return  boolean
	 *
	 * @throws  Exception
	 */
	public function onContentPrepareForm($form, $data)
	{
		//option=com_plugins&view=plugin&layout=edit
		$input  = Factory::getApplication()->input;
		$option = $input->getCmd('option');
		$view   = $input->getCmd('view');
		$layout = $input->getCmd('layout');

		if (($option != 'com_plugins') || ($view != 'plugin') || ($layout != 'edit'))
		{
			return true;
		}

		// Make sure this is the com_plugins component
		if ($form->getName() != 'com_plugins.plugin')
		{
			return true;
		}

		// We need to have data to proceed
		if (!is_object($data))
		{
			return true;
		}

		// Make sure we're editing our plugin
		$type    = property_exists($data, 'type') ? $data->type : '';
		$element = property_exists($data, 'element') ? $data->element : '';
		$folder  = property_exists($data, 'folder') ? $data->folder : '';

		if (($type != 'plugin') || ($element != 'mailmagic') || ($folder != 'system'))
		{
			return true;
		}

		// Add a mail test button
		$this->addMailTestButton();

		return true;
	}

	private function addMailTestButton(): void
	{
		static $alreadyAdded = false;
		
		if ($alreadyAdded)
		{
			return;
		}

		$alreadyAdded = true;
		
		JToolbarHelper::link('#', 'PLG_SYSTEM_MAILMAGIC_LBL_TESTEMAIL', 'mail');

		Factory::getApplication()->getDocument()->addScriptOptions('plg_system_mailmagic', [
			'ajax_url' => Joomla\CMS\Uri\Uri::base(true) . '/index.php?option=com_ajax&plugin=mailmagic&group=system&format=raw'
		]);

		Text::script('PLG_SYSTEM_MAILMAGIC_LBL_TESTEMAIL_SENT');

		HTMLHelper::_('script', 'plg_system_mailmagic/testmail.js', [
			'relative'  => true,
		]);
	}
}