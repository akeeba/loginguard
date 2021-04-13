"use strict";

function _toConsumableArray(arr) { return _arrayWithoutHoles(arr) || _iterableToArray(arr) || _unsupportedIterableToArray(arr) || _nonIterableSpread(); }

function _nonIterableSpread() { throw new TypeError("Invalid attempt to spread non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method."); }

function _unsupportedIterableToArray(o, minLen) { if (!o) return; if (typeof o === "string") return _arrayLikeToArray(o, minLen); var n = Object.prototype.toString.call(o).slice(8, -1); if (n === "Object" && o.constructor) n = o.constructor.name; if (n === "Map" || n === "Set") return Array.from(o); if (n === "Arguments" || /^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(n)) return _arrayLikeToArray(o, minLen); }

function _iterableToArray(iter) { if (typeof Symbol !== "undefined" && Symbol.iterator in Object(iter)) return Array.from(iter); }

function _arrayWithoutHoles(arr) { if (Array.isArray(arr)) return _arrayLikeToArray(arr); }

function _arrayLikeToArray(arr, len) { if (len == null || len > arr.length) len = arr.length; for (var i = 0, arr2 = new Array(len); i < len; i++) { arr2[i] = arr[i]; } return arr2; }

/*!
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */
// Namespace
var akeeba = akeeba || {};
akeeba.LoginGuard = akeeba.LoginGuard || {};
akeeba.LoginGuard.webauthn = akeeba.LoginGuard.webauthn || {
  authData: null
};
/**
 * Utility function to convert array data to base64 strings
 */

akeeba.LoginGuard.webauthn.arrayToBase64String = function (a) {
  return btoa(String.fromCharCode.apply(String, _toConsumableArray(a)));
};

akeeba.LoginGuard.webauthn.base64url2base64 = function (input) {
  var output = input.replace(/-/g, '+').replace(/_/g, '/');
  var pad = output.length % 4;

  if (pad) {
    if (pad === 1) {
      throw new Error('InvalidLengthError: Input base64url string is the wrong length to determine padding');
    }

    output += new Array(5 - pad).join('=');
  }

  return output;
};
/**
 * Ask the user to link an authenticator using the provided public key (created server-side).
 */


akeeba.LoginGuard.webauthn.setUp = function (e) {
  e.preventDefault(); // Make sure the browser supports Webauthn

  if (!('credentials' in navigator)) {
    alert(Joomla.JText._('PLG_LOGINGUARD_WEBAUTHN_ERR_NOTAVAILABLE_HEAD'));
    console.log('This browser does not support Webauthn');
    return false;
  }

  var rawPKData = document.forms['loginguard-method-edit'].querySelectorAll('input[name="pkRequest"]')[0].value;
  var publicKey = JSON.parse(atob(rawPKData)); // Convert the public key infomration to a format usable by the browser's credentials managemer

  publicKey.challenge = Uint8Array.from(window.atob(akeeba.LoginGuard.webauthn.base64url2base64(publicKey.challenge)), function (c) {
    return c.charCodeAt(0);
  });
  publicKey.user.id = Uint8Array.from(window.atob(publicKey.user.id), function (c) {
    return c.charCodeAt(0);
  });

  if (publicKey.excludeCredentials) {
    publicKey.excludeCredentials = publicKey.excludeCredentials.map(function (data) {
      data.id = Uint8Array.from(window.atob(akeeba.LoginGuard.webauthn.base64url2base64(data.id)), function (c) {
        return c.charCodeAt(0);
      });
      return data;
    });
  } // Ask the browser to prompt the user for their authenticator


  navigator.credentials.create({
    publicKey: publicKey
  }).then(function (data) {
    var publicKeyCredential = {
      id: data.id,
      type: data.type,
      rawId: akeeba.LoginGuard.webauthn.arrayToBase64String(new Uint8Array(data.rawId)),
      response: {
        clientDataJSON: akeeba.LoginGuard.webauthn.arrayToBase64String(new Uint8Array(data.response.clientDataJSON)),
        attestationObject: akeeba.LoginGuard.webauthn.arrayToBase64String(new Uint8Array(data.response.attestationObject))
      }
    }; // Store the WebAuthn reply

    document.getElementById('loginguard-method-code').value = btoa(JSON.stringify(publicKeyCredential)); // Submit the form

    document.forms['loginguard-method-edit'].submit();
  }, function (error) {
    // An error occurred: timeout, request to provide the authenticator refused, hardware / software error...
    akeeba.LoginGuard.webauthn.handle_error(error);
  });
};

akeeba.LoginGuard.webauthn.handle_error = function (message) {
  try {
    document.getElementById('loginguard-webauthn-button').style.display = '';
  } catch (e) {}

  ;
  alert(message);
  console.log(message);
};

akeeba.LoginGuard.webauthn.validate = function () {
  // Make sure the browser supports Webauthn
  if (!('credentials' in navigator)) {
    alert(Joomla.JText._('PLG_LOGINGUARD_WEBAUTHN_ERR_NOTAVAILABLE_HEAD'));
    console.log('This browser does not support Webauthn');
    return;
  }

  var publicKey = akeeba.LoginGuard.webauthn.authData;

  if (!publicKey.challenge) {
    akeeba.LoginGuard.webauthn.handle_error(Joomla.JText._('PLG_LOGINGUARD_WEBAUTHN_ERR_NO_STORED_CREDENTIAL'));
    return;
  }

  publicKey.challenge = Uint8Array.from(window.atob(akeeba.LoginGuard.webauthn.base64url2base64(publicKey.challenge)), function (c) {
    return c.charCodeAt(0);
  });

  if (publicKey.allowCredentials) {
    publicKey.allowCredentials = publicKey.allowCredentials.map(function (data) {
      data.id = Uint8Array.from(window.atob(akeeba.LoginGuard.webauthn.base64url2base64(data.id)), function (c) {
        return c.charCodeAt(0);
      });
      return data;
    });
  }

  navigator.credentials.get({
    publicKey: publicKey
  }).then(function (data) {
    var publicKeyCredential = {
      id: data.id,
      type: data.type,
      rawId: akeeba.LoginGuard.webauthn.arrayToBase64String(new Uint8Array(data.rawId)),
      response: {
        authenticatorData: akeeba.LoginGuard.webauthn.arrayToBase64String(new Uint8Array(data.response.authenticatorData)),
        clientDataJSON: akeeba.LoginGuard.webauthn.arrayToBase64String(new Uint8Array(data.response.clientDataJSON)),
        signature: akeeba.LoginGuard.webauthn.arrayToBase64String(new Uint8Array(data.response.signature)),
        userHandle: data.response.userHandle ? akeeba.LoginGuard.webauthn.arrayToBase64String(new Uint8Array(data.response.userHandle)) : null
      }
    };
    document.getElementById('loginGuardCode').value = btoa(JSON.stringify(publicKeyCredential));
    document.forms['loginguard-captive-form'].submit();
  }, function (error) {
    // Example: timeout, interaction refused...
    console.log(error);
    akeeba.LoginGuard.webauthn.handle_error(error);
  });
};

akeeba.LoginGuard.webauthn.onValidateClick = function (event) {
  event.preventDefault();
  akeeba.LoginGuard.webauthn.authData = JSON.parse(window.atob(Joomla.getOptions('com_loginguard.authData')));
  document.getElementById('loginguard-webauthn-button').style.display = 'none';
  akeeba.LoginGuard.webauthn.validate();
  return false;
};

document.getElementById('loginguard-webauthn-missing').style.display = 'none';

if (typeof navigator.credentials == 'undefined') {
  document.getElementById('loginguard-webauthn-missing').style.display = 'block';
  document.getElementById('loginguard-webauthn-controls').style.display = 'none';
}

akeeba.Loader.add(['akeeba.System'], function () {
  if (Joomla.getOptions('com_loginguard.pagetype') === 'validate') {
    akeeba.System.addEventListener('plg_loginguard_webauthn_validate_button', 'click', akeeba.LoginGuard.webauthn.onValidateClick);
    akeeba.System.addEventListener('loginguard-captive-button-submit', 'click', akeeba.LoginGuard.webauthn.onValidateClick);
  } else {
    akeeba.System.addEventListener('plg_loginguard_webauthn_register_button', 'click', akeeba.LoginGuard.webauthn.setUp);
  }

  akeeba.System.forEach(document.querySelectorAll('.loginguard_webauthn_setup'), function (i, btn) {
    akeeba.System.addEventListener(btn, 'click', akeeba.LoginGuard.webauthn.setUp);
  });
});
//# sourceMappingURL=webauthn.js.map