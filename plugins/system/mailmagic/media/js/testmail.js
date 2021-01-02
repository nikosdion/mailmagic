/*!
 *  @package   MailMagic
 *  @copyright Copyright (c)2020-2021 Nicholas K. Dionysopoulos
 *  @license   GNU General Public License version 3, or later
 */

if (typeof akeeba == "undefined")
{
    var akeeba = {};
}

if (typeof akeeba.MailMagic == "undefined")
{
    akeeba.MailMagic = {};
}

akeeba.MailMagic.sendTestEmail = function ()
{
    Joomla.request({
        url: Joomla.getOptions('plg_system_mailmagic').ajax_url,
        method: "GET",
        onSuccess: function(response, xhr){
            alert(Joomla.Text._('PLG_SYSTEM_MAILMAGIC_LBL_TESTEMAIL_SENT'));
        }
    });
}

document.addEventListener('DOMContentLoaded', function () {
    var buttonElements = document.querySelectorAll('#toolbar-mail>button');

    for (var i = 0; i < buttonElements.length; i++)
    {
        /** @type {HTMLElement} elButton */
        var elButton = buttonElements[i];

        elButton.addEventListener('click', function (e) {
            /** @type {MouseEvent} e */
            e.preventDefault();

            akeeba.MailMagic.sendTestEmail();

            return false;
        });
    }
});