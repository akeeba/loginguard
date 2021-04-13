/*!
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

if (typeof akeeba == "undefined")
{
    var akeeba = {};
}

akeeba.LoginGuard = akeeba.LoginGuard || {};
akeeba.LoginGuard.fingerprint = akeeba.LoginGuard.fingerprint || {
    browserId: null
};

/**
 * Initialize the fingerprinting process.
 *
 * Waits until the JS we depend on is loaded. Then it calls FingerprintJS2 to do the actual fingerprinting and calls the
 * akeeba.LoginGuard.fingerprint.callback method which populates akeeba.LoginGuard.fingerprint.browserId.
 */
akeeba.LoginGuard.fingerprint.init = function() {
    // If the required JS has not been loaded yet check again in 100 msec
    let hasFingerprintJS2 = window.hasOwnProperty('Fingerprint2');
    let hasMurmur3 = window.hasOwnProperty('murmurHash3');

    if (!hasFingerprintJS2 || !hasMurmur3)
    {
        window.setTimeout(akeeba.LoginGuard.fingerprint.init, 100);

        return;
    }

    // Prefer requestIdleCallback (may take less than 500msec to run it)
    if (window.requestIdleCallback) {
        requestIdleCallback(function () {
            Fingerprint2.get(akeeba.LoginGuard.fingerprint.callback);
        }, {
            timeout: 500
        });

        return;
    }

    // Fallback to setTimeout (always takes 500msec to run it)
    setTimeout(function () {
        Fingerprint2.get(akeeba.LoginGuard.fingerprint.callback);
    }, 500)

};

/**
 * A FingerprintJS2 callback to populate akeeba.LoginGuard.fingerprint.browserId
 *
 * FingerprintJS2 calls this method with the components object that lists all detected browser properties. We pass this
 * object (serialized) to MurmurHash3 to get our Browser ID string.
 *
 * @param   {Object}  components
 */
akeeba.LoginGuard.fingerprint.callback = function (components) {
    let encodedString = JSON.stringify(components);
    akeeba.LoginGuard.fingerprint.browserId = murmurHash3.x64.hash128(encodedString);
};

// Initialize immediately
akeeba.LoginGuard.fingerprint.init();

var akeebaLoginGuardCaptiveCheckingCounter = 0;
var akeebaLoginGuardCaptiveCheckingTimer = setInterval(function () {
    // Wait until we have a browser ID or we've been here for more than 4 seconds
    var notYet =
            (typeof akeeba.LoginGuard.fingerprint.browserId === 'undefined') ||
            (akeeba.LoginGuard.fingerprint.browserId === null);

    if (++akeebaLoginGuardCaptiveCheckingCounter >= 16)
    {
        document.forms.akeebaLoginguardForm.submit();
    }

    if (notYet)
    {
        return;
    }

    // Unset this timer
    clearInterval(akeebaLoginGuardCaptiveCheckingTimer);

    // Set the browser ID in the form and submit the form.
    document.getElementById('akeebaLoginguardFormBrowserId').value = akeeba.LoginGuard.fingerprint.browserId;
    document.forms.akeebaLoginguardForm.submit();
}, 250);