# Mail Magic

Convert plain text Joomla emails into beautiful, HTML emails

## What does it do?

Converts your Joomla plain text emails in rich, HTML emails using your custom templates.

## Features

* Convert plain old text emails into HTML.
* Custom templates using HTML files. No more fooling around with the WYSIWYG editor.
* Image inlining.
* Tag substitution.

## Download

I primarily wrote this for my own, personal use. I do publish occasional pre-built Joomla installation packages and make them available through [my GitHub repository's Releases page](https://github.com/nikosdion/mailmagic/releases).

## Support and contributions

This is a project I maintain on my spare time. Having a family and business doesn't leave me with much spare time. Please try to be concise and considerate when filing a GitHub issue.

If you are making a feature request please keep in mind not just that my time is limited but also that your unique use case may not be as universal as you think. What is an obvious omission for you may not be something most people want, or it might require changes to an extent that it makes the software hard to use for everyone else.

If you want to contribute code feel free to make a Pull Request (PR) on this repository. Kindly note that making a PR does not guarantee acceptance without changes or even at all. Please file a GitHub issue beforehand to let me know that you want to contribute code and give me a short explanation of what and why you want to implement. I'd rather spend five more minutes replying to you than you wasting half a day to implement a feature I am not willing to merge.

Finally, be advised that I am rather direct in my communication. I am neither angry nor aggressive towards you. I do make an effort not to come across as an a-hole but after a certain point it's just the way my brain is wired. Thank you for your understanding!

## Building the package

### Quick'n'dirty build

In the simplest form you can ZIP the `plugins/system/mailmagic` folder's contents and install it on your site.

### Full build process

If you want to go through the build process I use you will need to have the following tools:

* A command line environment. Using Bash under Linux / Mac OS X works best.
* A PHP CLI binary in your path
* Phing installed account-wide on your machine
* Command line Git executables

You will also need the following path structure inside a folder on your system

* **mailmagic** This repository
* **buildfiles** [Akeeba Build Tools](https://github.com/akeeba/buildfiles)

You will need to use the exact folder names specified here.

Go into the `build` directory of this repository.

Create a dev release installation package with

		phing git
		
The installable ZIP file is stored in the `release` directory which will be created inside the repository's root.