# MailMagic – User's Guide

## Installation

Download the MailMagic plugin's ZIP file from [my GitHub repository's Releases page](https://github.com/nikosdion/mailmagic/releases). Do not unzip!

Go to Joomla's Extensions, Install page. Use the Upload & Install tab to upload and install the ZIP file you just downloaded.

Go to Joomla's Extensions, Manage, Plugins page and publish the “System – MailMagic” plugin.

## Configuration

You can configure MailMagic by editing the “System – MailMagic” plugin. You get the following options:

* **Template**. Chooses one of the HTML email templates in the `plugins/system/mailmagic/templates` folder. The template must have a `.html` extension.

* **Inline images**. Set to Yes to attach the images to the email being sent. The email size will be bigger but the images are more likely to be displayed. Set to No to leave images as-is. In this case you _must_ use absolute URLs for your images or they won't load in the mail client!

* **Site URL**. When empty, MailMagic asks Joomla to provide the URL of your site. This may not be desirable in two cases. One, you can access your site through multiple domains or subdomains but you want your emails to only reference your ‘canonical’ domain name. In this case please enter the ‘canonical’ URL to your site in this box. Two, you have a CLI script / CRON job which sends out emails. In this case Joomla cannot report your site's URL and you **must** provide it here.

* **Alternate plaintext for HTML emails**. When enabled, HTML-only email will automatically get a plain-text transcription. This is a good idea since some people prefer to receive plain-text email for security and privacy reasons but many Joomla components don't provide a plain-text alternative at all.

## Advanced topics

### Creating HTML email templates

Every MailMagic template is an HTML document. It needs to have a `.html` extension, in all lowercase, for Joomla to display it when you're editing the plugin settings. 

The template needs to have both a `head` and a `body`, otherwise spam filters will flag the message as spam. However, you _must not_ include CSS in the `head` because mail client software and web mail providers will ignore it.

Please remember that designing HTML and CSS for email is _not_ the same as designing for the web. Mail clients tend to filter out certain HTML elements and attributes, simplify or interpret CSS in a different way etc. In most cases element positioning code will not work at all and you will have to use `table` elements like it's 1998 all over again. Before assuming that MailMagic didn't work or has a bug check the source of the message received by your mail client (NOT the source of the HTML displayed by the mail client, these are _two entirely different_ things, especially when it comes to Gmail, Outlook on the web etc).

When it comes to email templates, MailMagic can include some information about your site and the recipient (if they are a Joomla! user) to further customize the email experience. These variables are in the form of text that looks like `[VARIABLE]` i.e. square brackets with _uppercase_ text in them. You can find more about them by reading the README.txt file in the `plugins/system/mailmagic/templates` folder.

### How image inlining works

When you enable image inlining (on by default) MailMagic will go through your HTML template and look for the following patterns:

* `srcset="something"`
* `src="something"`
* `url("something")`

The double quotes are optional. `something` is an image path relative to your site's root or an image URL.

`something` is considered a possible image URL when its extension is one of `.jpg`, `.jpeg`, `.png`, `.gif`, `.bmp`, or `svg` – this is case-insesitive i.e. `.JPG` and `.Jpg` will also be recognized. If it's not a possible image URL the image will not be inlined.

The second check performed is whether it looks like a relative path to your site's root or an absolute URL pointing back to your site. If your site's URL is `https://www.example.com` the following are valid relative and absolute paths: `https://www.example.com/images/example.png`, `/images/example.png`, `images/example.png`. The following are **INVALID** URLs and won't be considered: `http://www.example.com/images/example.png` (using http:// instead of https://), `https://example.com/images/example.png` (example.com does not have the same subdomain as www.example.com) or `https://en.example.com/images/example.png` (subdomain en is not the same as www). If the URL / path is invalid the image will not be inlined. 

At this point MailMagic strips the site's URL (if present) to find out the relative path of the image file in your site. If and only if this file exists and is readable it will be added as an inline attachment to the mail message. The URL in the HTML will be updated to reference the inline attachment instead of the file on your server.

A sticking point is how is the site URL determined. MailMagic asks Joomla _unless_ you've configured a site URL. Asking Joomla is not a problem unless your site can be accessed by different URLs. A typical site is usually accessible as `http://example.com`, `https://example.com`, `http://www.example.com` and `https://www.example.com`. These are four different URLs and can cause quite the confusion with regards to image inlining. Depending on how your site is accessed your images may or may not be inlined. Please, do use the Site URL option in the plugin to avoid this problem. That's one of the two reasons this option is there.

### Smart conversion of plain text and links

MailMagic will convert plain text into HTML block by block. The block limits are newline characters. Every block which does not start with a `<p>` or `<div>` tag is converted to HTML. Conversely, blocks which start with a `<p>` or `<div>` tag are considered to already be HTML and are output as-is.

Furthermore, MailMagic uses a trick lifted straight out of WordPress (sorry!) to convert URLs and bare email addresses in the plain text to clickable links. URL conversion actually works with a few different protocols: `ftp`, `ftps`, `mailto`, `news`, `irc`, `gopher`, `nntp`, `feed`, `telnet`, `mms`, `rtsp`, `sms`, `svn`, `tel`, `fax`, `xmpp`, `webcal`, `urn`. So it's quite a lot of things that will be automatically converted to links besides what you obviously think of a (web) URL and an email address.

### If you do not receive HTML emails no matter what you do...

...and you've checked that MailMagic is enabled and published: you should complain to another third party developer who hasn't updated their code for Joomla 3.8 or later.

The technical explanation is that Joomla versions before 3.8 were using a class named `JMail` to create the system mailer. Joomla 3.8 and later use the namespaced class `Joomla\CMS\Mail\Mail`. To provide backwards compatibility, Joomla creates an alias for `JMail` pointing to `Joomla\CMS\Mail\Mail`. The `Factory::getMailer()` method returns a static instance of the `Joomla\CMS\Mail\Mail` class.


Some third party software needed to essentially override the `JMail` class to extend its functionality. Traditionally this wasn't possible without a core hack. Instead, they were extending a class from the core `JMail` class, create an object and manipulate Joomla's Factory class to return their object instead of the core mailer object.

This trick is wrong in Joomla 3.8 because the core mailer object is now a subclass of a concrete `JMail` class which breaks a lot of things in Joomla itself and third party extensions. Moreover, if this trick runs before MailMagic then my code in MailMagic effectively never runs.   

That's because MailMagic does things the **One True Joomla Way**. It does not replace Joomla's core objects. It loads the code of the `Joomla\CMS\Mail\Mail` class, patches it in memory and tells PHP to use it instead of the original file on disk. This happens _before_ Joomla creates its core mailer. Therefore, when Joomla tries to create a core mailer object it will use the patched code that's already loaded by PHP. If a third party plugin has already created a mailer object OR it uses a JMail subclass this trick won't work.

The first thing you should try is publishing MailMagic before any other system plugin.

If this doesn't help find which system plugin is breaking your site and talk to its developer. They need to update their code for Joomla 3.8.