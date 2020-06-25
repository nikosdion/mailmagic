<?php
/**
 * @package   MailMagic
 * @copyright Copyright (c)2020-2020 Nicholas K. Dionysopoulos
 * @license   GNU General Public License version 3, or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Mail\Mail;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\User;
use Joomla\Registry\Registry;
use Soundasleep\Html2Text;
use Soundasleep\Html2TextException;

/**
 * @package     MailMagic
 *
 * Processes plain text emails, converting the to HTML before Joomla sends them.
 *
 * @since       1.0.0
 */
final class plgSystemMailmagicProcess
{
	/**
	 * Plugin parameters
	 *
	 * @var   Registry
	 * @since 1.0.0
	 */
	private static $pluginParams;

	/**
	 * HTML email templates root folder
	 *
	 * @var   string|null
	 * @since 1.0.0
	 */
	private static $templateRoot;

	/**
	 * Allowed image file extensions to inline in sent emails
	 *
	 * @var   array
	 * @since 1.0.0
	 */
	private static $allowedImageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg'];

	public static function initialize(?Registry $params = null, ?string $templateRoot = null)
	{
		self::$pluginParams = $params ?? (new Registry());
		self::$templateRoot = $templateRoot ?? realpath(__DIR__ . '/..') . '/templates';
	}

	/**
	 * Process an email, converting it from plain old text to beautiful HTML
	 *
	 * @param   Mail  $mailer
	 *
	 * @since   1.0.0
	 */
	public static function processEmail(Mail $mailer): void
	{
		$isHtml    = $mailer->ContentType != 'text/plain';
		$html2text = self::$pluginParams->get('html2text', 1);

		if ($isHtml && $html2text)
		{
			self::makeAlternateText($mailer);
		}

		if ($isHtml)
		{
			return;
		}

		// Use the original text as the alternate (non-HTML) body.
		$body            = $mailer->Body;
		$mailer->AltBody = $body;

		$recipients      = $mailer->getToAddresses();
		$recipient       = array_shift($recipients);
		$fakeUser        = new Joomla\CMS\User\User();
		$fakeUser->name  = isset($recipient[1]) ? $recipient[1] : $recipient[0];
		$fakeUser->email = $recipient[0];
		$user            = self::getUserFromEmail($fakeUser->email) ?: $fakeUser;

		// Create a replacements array
		$replacements = [
			'SUBJECT'          => $mailer->Subject,
			'FULLNAME'         => $user->name,
			'USERNAME'         => $user->username,
			'EMAIL'            => $user->email,
			'CONTENT'          => $body,
			'CONTENT_HTMLIZED' => self::htmlize($body),
			'SITENAME'         => self::getSiteName(),
			'SITEURL'          => self::getSiteURL(),
		];

		// Get the template
		$template = self::loadTemplate();

		// Replace variables into the template
		$keys = array_map(function ($var) {
			return '[' . strtoupper($var) . ']';
		}, array_keys($replacements));

		$mailer->setBody(str_replace($keys, array_values($replacements), $template));

		self::inlineImages($mailer);
	}

	/**
	 * Returns the base URL of the site
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	private static function getSiteURL(): string
	{
		// TODO Handle the case of a CLI application
		return Uri::base(false);
	}

	/**
	 * Returns the name of the site
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	private static function getSiteName(): string
	{
		try
		{
			$app = Factory::getApplication();

			return $app->get('sitename', '');
		}
		catch (Exception $e)
		{
			// Fall through to the next approach
		}

		try
		{
			$config = Factory::getConfig();

			return $config->get('sitename', '');
		}
		catch (Exception $e)
		{
			return '';
		}
	}

	/**
	 * This can turn partially HTML text into a passable HTML document.
	 *
	 * @param   string  $text  Messy text.
	 *
	 * @return  string  Actual HTML code we can send in an email.
	 */
	private static function flatTextToHtml(string $text): string
	{
		$text = trim($text);

		// Do I have a paragraph tag in the beginning of the comment?
		if (in_array(strtolower(substr($text, 0, 3)), ['<p>', '<p ']))
		{
			return $text;
		}

		// Do I have a DIV tag in the beginning of the comment?
		if (in_array(strtolower(substr($text, 0, 5)), ['<div>', '<div ']))
		{
			return $text;
		}

		$paragraphs = explode("\n\n", $text);

		return implode("\n", array_map(function (string $text) {
			// Do I have a p tag?
			if (in_array(strtolower(substr($text, 0, 3)), ['<p>', '<p ']))
			{
				return $text;
			}

			// Do I have a div tag?
			if (in_array(strtolower(substr($text, 0, 5)), ['<div>', '<div ']))
			{
				return $text;
			}

			return "<p>" . $text . "</p>";
		}, $paragraphs));
	}

	/**
	 * Returns a Joomla user object given their email address.
	 *
	 * Email address comparison is performed in a case-insensitive manner.
	 *
	 * @param   string|null  $email  The email to look for
	 *
	 * @return  User|null  The corresponding user object. Null if no user is found.
	 *
	 * @since   1.0.0
	 */
	private static function getUserFromEmail(?string $email): ?User
	{
		$email = is_string($email) ? trim($email) : '';

		if (empty($email))
		{
			return null;
		}

		$db     = Factory::getDbo();
		$query  = $db->getQuery(true)
			->select($db->qn('id'))
			->from($db->qn('#__users'))
			->where('LOWER(' . $db->qn('email') . ') = ' . $db->q($email));
		$userId = $db->setQuery($query)->loadResult();

		if (empty($userId))
		{
			return null;
		}

		return Factory::getUser($userId);
	}

	/**
	 * Loads the configured email template's contents
	 *
	 * @return  string
	 * @since        1.0.0
	 *
	 * @noinspection HtmlRequiredLangAttribute
	 */
	private static function loadTemplate(): string
	{
		$templateName = self::$pluginParams->get('template', 'default.html');
		$path         = self::$templateRoot . '/' . $templateName;

		if (!is_file($path))
		{
			$path = self::$templateRoot . '/default.html';
		}

		$text = @file_get_contents($path);

		if ($text === false)
		{
			$text = <<< HTML
<html>
<head>
	<title>[SUBJECT]</title>
</head>
<body>
[CONTENT_HTMLIZED]
</body>
</html>
HTML;

		}

		return $text;
	}

	/**
	 * Converts a plain text email to a passable HTML representation
	 *
	 * @param   string  $body  Plain text email
	 *
	 * @return  string  HTML verion of the email
	 *
	 * @since   1.0.0
	 */
	private static function htmlize(string $body)
	{
		$body = self::flatTextToHtml($body);
		$body = self::wpMakeClickable($body);

		return $body;
	}

	// region Imported from WordPress

	/**
	 * Convert plaintext URI to HTML links.
	 *
	 * Converts URI, www and ftp, and email addresses. Finishes by fixing links
	 * within links.
	 *
	 * This method has been adapted from WordPress.
	 *
	 * @param   string  $text  Content to convert URIs.
	 *
	 * @return  string  Content with converted URIs.
	 * @since   1.0.0
	 */
	private static function wpMakeClickable(string $text): string
	{
		$r = '';
		// split out HTML tags
		$textarr = preg_split('/(<[^<>]+>)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
		// Keep track of how many levels link is nested inside <pre> or <code>
		$nested_code_pre = 0;

		foreach ($textarr as $piece)
		{

			if (preg_match('|^<code[\s>]|i', $piece) || preg_match('|^<pre[\s>]|i', $piece) || preg_match('|^<script[\s>]|i', $piece) || preg_match('|^<style[\s>]|i', $piece))
			{
				$nested_code_pre++;
			}
			elseif ($nested_code_pre && ('</code>' === strtolower($piece) || '</pre>' === strtolower($piece) || '</script>' === strtolower($piece) || '</style>' === strtolower($piece)))
			{
				$nested_code_pre--;
			}

			if ($nested_code_pre || empty($piece) || ($piece[0] === '<' && !preg_match('|^<\s*[\w]{1,20}+://|', $piece)))
			{
				$r .= $piece;
				continue;
			}

			// Long strings might contain expensive edge cases ...
			if (10000 < strlen($piece))
			{
				// ... break it up
				foreach (self::wpSplitStringByWhitespace($piece, 2100) as $chunk)
				{ // 2100: Extra room for scheme and leading and trailing paretheses
					if (2101 < strlen($chunk))
					{
						$r .= $chunk; // Too big, no whitespace: bail.
					}
					else
					{
						$r .= self::wpMakeClickable($chunk);
					}
				}
			}
			else
			{
				$ret = " $piece "; // Pad with whitespace to simplify the regexes

				$url_clickable = '~
				([\\s(<.,;:!?])                                        # 1: Leading whitespace, or punctuation
				(                                                      # 2: URL
					[\\w]{1,20}+://                                # Scheme and hier-part prefix
					(?=\S{1,2000}\s)                               # Limit to URLs less than about 2000 characters long
					[\\w\\x80-\\xff#%\\~/@\\[\\]*(+=&$-]*+         # Non-punctuation URL character
					(?:                                            # Unroll the Loop: Only allow puctuation URL character if followed by a non-punctuation URL character
						[\'.,;:!?)]                            # Punctuation URL character
						[\\w\\x80-\\xff#%\\~/@\\[\\]*(+=&$-]++ # Non-punctuation URL character
					)*
				)
				(\)?)                                                  # 3: Trailing closing parenthesis (for parethesis balancing post processing)
			~xS';
				// The regex is a non-anchored pattern and does not have a single fixed starting character.
				// Tell PCRE to spend more time optimizing since, when used on a page load, it will probably be used several times.

				$ret = preg_replace_callback($url_clickable, [__CLASS__, 'wpMakeURLClickableCallback'], $ret);

				$ret = preg_replace_callback('#([\s>])((www|ftp)\.[\w\\x80-\\xff\#$%&~/.\-;:=,?@\[\]+]+)#is', [
					__CLASS__, 'wpMakeWebFTPClickableCallback',
				], $ret);
				$ret = preg_replace_callback('#([\s>])([.0-9a-z_+-]+)@(([0-9a-z-]+\.)+[0-9a-z]{2,})#i', [
					__CLASS__, 'wpMakeEmailClickableCallback',
				], $ret);

				$ret = substr($ret, 1, -1); // Remove our whitespace padding.
				$r   .= $ret;
			}
		}

		// Cleanup of accidental links within links
		return preg_replace('#(<a([ \r\n\t]+[^>]+?>|>))<a [^>]+?>([^>]+?)</a></a>#i', '$1$3</a>', $r);
	}

	/**
	 * Perform a deep string replace operation to ensure the values in $search are no longer present
	 *
	 * Repeats the replacement operation until it no longer replaces anything so as to remove "nested" values
	 * e.g. $subject = '%0%0%0DDD', $search ='%0D', $result ='' rather than the '%0%0DD' that
	 * str_replace would return
	 *
	 * This method has been adapted from WordPress.
	 *
	 * @param   string|array  $search   Needle.
	 * @param   string        $subject  Haystack.
	 *
	 * @return  string  The string with the replaced values.
	 * @since   1.0.0
	 */
	private static function wpDeepReplace($search, string $subject): string
	{
		$subject = (string) $subject;
		$count   = 1;

		while ($count)
		{
			$subject = str_replace($search, '', $subject, $count);
		}

		return $subject;
	}


	/**
	 * Checks and cleans a URL.
	 *
	 * This method has been adapted from WordPress.
	 *
	 * @param   string  $url        The URL to be cleaned.
	 * @param   array   $protocols  Optional. An array of acceptable protocols.
	 *
	 * @return  string  The cleaned $url
	 * @since   1.0.0
	 */
	private static function wpEscapeURL(string $url, ?array $protocols = null): string
	{
		if (empty($url))
		{
			return $url;
		}

		$url = str_replace(' ', '%20', ltrim($url));
		$url = preg_replace('|[^a-z0-9-~+_.?#=!&;,/:%@$\|*\'()\[\]\\x80-\\xff]|i', '', $url);

		if (empty($url))
		{
			return $url;
		}

		if (stripos($url, 'mailto:') !== 0)
		{
			$strip = ['%0d', '%0a', '%0D', '%0A'];
			$url   = self::wpDeepReplace($strip, $url);
		}

		$url = str_replace(';//', '://', $url);

		/* If the URL doesn't appear to contain a scheme, we
		 * presume it needs http:// prepended (unless a relative
		 * link starting with /, # or ? or a php file).
		 */

		if (strpos($url, ':') === false && !in_array($url[0], ['/', '#', '?']) &&
			!preg_match('/^[a-z0-9-]+?\.php/i', $url))
		{
			$url = 'http://' . $url;
		}

		if ((false !== strpos($url, '[')) || (false !== strpos($url, ']')))
		{
			$uri       = Uri::getInstance($url);
			$front     = $uri->toString(['scheme', 'user', 'pass', 'host', 'port']);
			$end_dirty = str_replace($front, '', $url);
			$end_clean = str_replace(['[', ']'], ['%5B', '%5D'], $end_dirty);
			$url       = str_replace($end_dirty, $end_clean, $url);

		}

		if ('/' !== $url[0])
		{
			$protocols = !empty($protocols) ? $protocols : [
				'http', 'https', 'ftp', 'ftps', 'mailto', 'news', 'irc', 'gopher', 'nntp', 'feed', 'telnet', 'mms',
				'rtsp', 'sms', 'svn', 'tel', 'fax', 'xmpp', 'webcal', 'urn',
			];

			$uri = Uri::getInstance($url);

			if (!in_array($uri->getScheme(), $protocols))
			{
				return '';
			}
		}

		return $url;
	}


	/**
	 * Callback to convert URI match to HTML A element.
	 *
	 * This method has been adapted from WordPress.
	 *
	 * @param   array  $matches  Single Regex Match.
	 *
	 * @return  string  HTML A element with URI address.
	 * @since   1.0.0
	 */
	private static function wpMakeURLClickableCallback(array $matches): string
	{
		$url = $matches[2];

		if (')' == $matches[3] && strpos($url, '('))
		{
			// If the trailing character is a closing parethesis, and the URL has an opening parenthesis in it, add the closing parenthesis to the URL.
			// Then we can let the parenthesis balancer do its thing below.
			$url    .= $matches[3];
			$suffix = '';
		}
		else
		{
			$suffix = $matches[3];
		}

		// Include parentheses in the URL only if paired
		while (substr_count($url, '(') < substr_count($url, ')'))
		{
			$suffix = strrchr($url, ')') . $suffix;
			$url    = substr($url, 0, strrpos($url, ')'));
		}

		$url = self::wpEscapeURL($url);

		if (empty($url))
		{
			return $matches[0];
		}

		return $matches[1] . "<a href=\"$url\">$url</a>" . $suffix;
	}

	/**
	 * Breaks a string into chunks by splitting at whitespace characters.
	 *
	 * The length of each returned chunk is as close to the specified length goal as possible,
	 * with the caveat that each chunk includes its trailing delimiter.
	 * Chunks longer than the goal are guaranteed to not have any inner whitespace.
	 *
	 * Joining the returned chunks with empty delimiters reconstructs the input string losslessly.
	 *
	 * Input string must have no null characters (or eventual transformations on output chunks must not care about null
	 * characters)
	 *
	 *     _split_str_by_whitespace( "1234 67890 1234 67890a cd 1234   890 123456789 1234567890a    45678   1 3 5 7 90
	 *     ", 10 ) == array (
	 *         0 => '1234 67890 ',  // 11 characters: Perfect split
	 *         1 => '1234 ',        //  5 characters: '1234 67890a' was too long
	 *         2 => '67890a cd ',   // 10 characters: '67890a cd 1234' was too long
	 *         3 => '1234   890 ',  // 11 characters: Perfect split
	 *         4 => '123456789 ',   // 10 characters: '123456789 1234567890a' was too long
	 *         5 => '1234567890a ', // 12 characters: Too long, but no inner whitespace on which to split
	 *         6 => '   45678   ',  // 11 characters: Perfect split
	 *         7 => '1 3 5 7 90 ',  // 11 characters: End of $string
	 *     );
	 *
	 * This method has been adapted from WordPress.
	 *
	 * @param   string  $string  The string to split.
	 * @param   int     $goal    The desired chunk length.
	 *
	 * @return  array  Numeric array of chunks.
	 * @since   1.0.0
	 *
	 */
	private static function wpSplitStringByWhitespace(string $string, int $goal): array
	{
		$chunks = [];

		$string_nullspace = strtr($string, "\r\n\t\v\f ", "\000\000\000\000\000\000");

		while ($goal < strlen($string_nullspace))
		{
			$pos = strrpos(substr($string_nullspace, 0, $goal + 1), "\000");

			if (false === $pos)
			{
				$pos = strpos($string_nullspace, "\000", $goal + 1);
				if (false === $pos)
				{
					break;
				}
			}

			$chunks[]         = substr($string, 0, $pos + 1);
			$string           = substr($string, $pos + 1);
			$string_nullspace = substr($string_nullspace, $pos + 1);
		}

		if ($string)
		{
			$chunks[] = $string;
		}

		return $chunks;
	}

	/**
	 * Callback to convert URL match to HTML A element.
	 *
	 * This method has been adapted from WordPress.
	 *
	 * @param   array  $matches  Single Regex Match.
	 *
	 * @return  string  HTML A element with URL address.
	 * @since   1.0.0
	 *
	 */
	private static function wpMakeWebFTPClickableCallback(array $matches): string
	{
		$ret  = '';
		$dest = $matches[2];
		$dest = 'http://' . $dest;

		// removed trailing [.,;:)] from URL
		if (in_array(substr($dest, -1), ['.', ',', ';', ':', ')']))
		{
			$ret  = substr($dest, -1);
			$dest = substr($dest, 0, strlen($dest) - 1);
		}

		$dest = self::wpEscapeURL($dest);

		if (empty($dest))
		{
			return $matches[0];
		}

		return $matches[1] . "<a href=\"$dest\">$dest</a>$ret";
	}

	/**
	 * Callback to convert email address match to HTML A element.
	 *
	 * This method has been adapted from WordPress.
	 *
	 * @param   array  $matches  Single Regex Match.
	 *
	 * @return  string  HTML A element with email address.
	 * @since   1.0.0
	 */
	private static function wpMakeEmailClickableCallback(array $matches): string
	{
		$email = $matches[2] . '@' . $matches[3];

		return $matches[1] . "<a href=\"mailto:$email\">$email</a>";
	}

	// endregion

	// region Image inlining, imported from Akeeba Ticket System

	/**
	 * Attach and inline the referenced images in the email message
	 *
	 * @param   Mail  $mailer
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	private static function inlineImages(Mail $mailer): string
	{
		$bodyText = $mailer->Body;

		// RegEx patterns to detect images
		$patterns = [
			// srcset="**URL**" e.g. source tags
			'/srcset=\"?([^"]*)\"?/i',
			// src="**URL**" e.g. img tags
			'/src=\"?([^"]*)\"?/i',
			// url(**URL**) nad url("**URL**") i.e. inside CSS
			'/url\(\"?([^"\(\)]*)\"?\)/i',
		];

		// Cache of images so we don't inline them multiple times
		$foundImages = [];
		// Running counter of images, used to create the attachment IDs in the message
		$imageIndex = 0;

		// Run a RegEx search & replace for each pattern
		foreach ($patterns as $pattern)
		{
			// $matches[0]: the entire string matched by RegEx; $matches[1]: just the path / URL
			$bodyText = preg_replace_callback($pattern, function (array $matches) use ($mailer, &$foundImages, &$imageIndex): string {
				// Abort if it's not a file type we can inline
				if (!self::isInlineableFileExtension($matches[1]))
				{
					return $matches[0];
				}

				// Try to get the local absolute filesystem path of the referenced media file
				$localPath = self::getLocalAbsolutePath(self::normalizeURL($matches[1]));

				// Abort if this was not a relative / absolute URL pointing to our own site
				if (empty($localPath))
				{
					return $matches[0];
				}

				// Abort if the referenced file does not exist
				if (!@file_exists($localPath) || !@is_file($localPath))
				{
					return $matches[0];
				}

				// Make sure the inlined image is cached; prevent inlining the same file multiple times
				if (!array_key_exists($localPath, $foundImages))
				{
					$imageIndex++;
					$mailer->AddEmbeddedImage($localPath, 'img' . $imageIndex, basename($localPath));
					$foundImages[$localPath] = $imageIndex;
				}

				return str_replace($matches[1], $toReplace = 'cid:img' . $foundImages[$localPath], $matches[0]);
			}, $bodyText);
		}

		// Return the processed email content
		return $bodyText;
	}

	/**
	 * Does this file / URL have an allowed image extension for inlining?
	 *
	 * @param   string  $fileOrUri
	 *
	 * @return  bool
	 *
	 * @since   1.0.0
	 */
	private static function isInlineableFileExtension(string $fileOrUri): bool
	{
		$dot = strrpos($fileOrUri, '.');

		if ($dot === false)
		{
			return false;
		}

		$extension = substr($fileOrUri, $dot + 1);

		return in_array(strtolower($extension), self::$allowedImageExtensions);
	}

	/**
	 * Normalizes an image relative or absolute URL as an absolute URL
	 *
	 * @param   string  $fileOrUri
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	private static function normalizeURL(string $fileOrUri): string
	{
		// Empty file / URIs are returned as-is (obvious screw up)
		if (empty($fileOrUri))
		{
			return $fileOrUri;
		}

		// Remove leading / trailing slashes
		$fileOrUri = trim($fileOrUri, '/');

		// HTTPS URLs are returned as-is
		if (substr($fileOrUri, 0, 8) == 'https://')
		{
			return $fileOrUri;
		}

		// HTTP URLs are returned upgraded to HTTPS
		if (substr($fileOrUri, 0, 7) == 'http://')
		{
			return 'https://' . substr($fileOrUri, 7);
		}

		// Normalize URLs with a partial schema as HTTPS
		if (substr($fileOrUri, 0, 3) == '://')
		{
			return 'https://' . substr($fileOrUri, 3);
		}

		// This is a file. We assume it's relative to the site's root
		return rtrim(self::getSiteURL(), '/') . '/' . $fileOrUri;
	}

	/**
	 * Return the path to the local file referenced by the URL, provided it's internal.
	 *
	 * @param   string  $url
	 *
	 * @return  string|null  The local file path. NULL if the URL is not internal.
	 *
	 * @since   1.0.0
	 */
	private static function getLocalAbsolutePath(string $url): ?string
	{
		$base = rtrim(self::getSiteURL(), '/');

		if (strpos($url, $base) !== 0)
		{
			return null;
		}

		return JPATH_ROOT . '/' . ltrim(substr($url, strlen($base) + 1), '/');
	}

	// endregion

	/**
	 * Add an alternate, plain-text representation to an HTML-only email
	 *
	 * @param   Mail  $mailer  The mailer object to process
	 *
	 * @since  1.0.0
	 */
	private static function makeAlternateText(Mail $mailer): void
	{
		$altBody = $mailer->AltBody;
		$altBody = !empty($altBody) ? trim($altBody) : '';

		if (!empty($altBody))
		{
			return;
		}

		require_once __DIR__ . '/../vendor/autoload.php';

		try
		{
			$mailer->AltBody = Html2Text::convert($mailer->Body, [
				'ignore_errors' => true,
			]);
		}
		catch (Html2TextException $e)
		{
			// It's not the end of the world...
		}
	}
}