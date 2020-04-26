Place your email templates in this folder.

Each email template is an HTML document with a .html extension, e.g. example.html.

You can use the following shortcodes in your email template (they MUST be uppercase):

[SUBJECT]
    The subject of the email being sent. You should use it in the <title> tag of your HTML document.

[CONTENT]
    The plain text email content, as-is

[CONTENT_HTMLIZED]
    The plain text email content with newlines converted to line breaks (<br/> tags)

[EMAIL]
    The email recipient's email address.

[FULLNAME]
    The email recipient's full name. If none was specified and there is no corresponding Joomla user for the email
    address of the recipient this will be replaced with the recipient's email address (just like [EMAIL]).

[USERNAME]
    The email recipient's Joomla username. If there is no corresponding Joomla user for the email address of the
    recipient this is blank.

[SITENAME]
    The name of your site.

[SITEURL]
    The URL of your site.