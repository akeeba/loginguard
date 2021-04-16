"use strict";function _toConsumableArray(a){return _arrayWithoutHoles(a)||_iterableToArray(a)||_unsupportedIterableToArray(a)||_nonIterableSpread()}function _nonIterableSpread(){throw new TypeError("Invalid attempt to spread non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.")}function _unsupportedIterableToArray(a,b){if(a){if("string"==typeof a)return _arrayLikeToArray(a,b);var c=Object.prototype.toString.call(a).slice(8,-1);return"Object"===c&&a.constructor&&(c=a.constructor.name),"Map"===c||"Set"===c?Array.from(a):"Arguments"===c||/^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(c)?_arrayLikeToArray(a,b):void 0}}function _iterableToArray(a){if("undefined"!=typeof Symbol&&Symbol.iterator in Object(a))return Array.from(a)}function _arrayWithoutHoles(a){if(Array.isArray(a))return _arrayLikeToArray(a)}function _arrayLikeToArray(a,b){(null==b||b>a.length)&&(b=a.length);for(var c=0,d=Array(b);c<b;c++)d[c]=a[c];return d}var akeeba=akeeba||{};akeeba.LoginGuard=akeeba.LoginGuard||{},akeeba.LoginGuard.webauthn=akeeba.LoginGuard.webauthn||{authData:null},akeeba.LoginGuard.webauthn.arrayToBase64String=function(b){return btoa(String.fromCharCode.apply(String,_toConsumableArray(b)))},akeeba.LoginGuard.webauthn.base64url2base64=function(a){var b=a.replace(/-/g,"+").replace(/_/g,"/"),c=b.length%4;if(c){if(1===c)throw new Error("InvalidLengthError: Input base64url string is the wrong length to determine padding");b+=Array(5-c).join("=")}return b},akeeba.LoginGuard.webauthn.setUp=function(a){if(a.preventDefault(),!("credentials"in navigator))return alert(Joomla.JText._("PLG_LOGINGUARD_WEBAUTHN_ERR_NOTAVAILABLE_HEAD")),console.log("This browser does not support Webauthn"),!1;var b=document.forms["loginguard-method-edit"].querySelectorAll("input[name=\"pkRequest\"]")[0].value,c=JSON.parse(atob(b));c.challenge=Uint8Array.from(window.atob(akeeba.LoginGuard.webauthn.base64url2base64(c.challenge)),function(a){return a.charCodeAt(0)}),c.user.id=Uint8Array.from(window.atob(c.user.id),function(a){return a.charCodeAt(0)}),c.excludeCredentials&&(c.excludeCredentials=c.excludeCredentials.map(function(a){return a.id=Uint8Array.from(window.atob(akeeba.LoginGuard.webauthn.base64url2base64(a.id)),function(a){return a.charCodeAt(0)}),a})),navigator.credentials.create({publicKey:c}).then(function(a){var b={id:a.id,type:a.type,rawId:akeeba.LoginGuard.webauthn.arrayToBase64String(new Uint8Array(a.rawId)),response:{clientDataJSON:akeeba.LoginGuard.webauthn.arrayToBase64String(new Uint8Array(a.response.clientDataJSON)),attestationObject:akeeba.LoginGuard.webauthn.arrayToBase64String(new Uint8Array(a.response.attestationObject))}};document.getElementById("loginguard-method-code").value=btoa(JSON.stringify(b)),document.forms["loginguard-method-edit"].submit()},function(a){akeeba.LoginGuard.webauthn.handle_error(a)})},akeeba.LoginGuard.webauthn.handle_error=function(a){try{document.getElementById("plg_loginguard_webauthn_validate_button").style.disabled="null"}catch(a){}alert(a),console.log(a)},akeeba.LoginGuard.webauthn.validate=function(){if(!("credentials"in navigator))return alert(Joomla.JText._("PLG_LOGINGUARD_WEBAUTHN_ERR_NOTAVAILABLE_HEAD")),void console.log("This browser does not support Webauthn");var a=akeeba.LoginGuard.webauthn.authData;return a.challenge?void(a.challenge=Uint8Array.from(window.atob(akeeba.LoginGuard.webauthn.base64url2base64(a.challenge)),function(a){return a.charCodeAt(0)}),a.allowCredentials&&(a.allowCredentials=a.allowCredentials.map(function(a){return a.id=Uint8Array.from(window.atob(akeeba.LoginGuard.webauthn.base64url2base64(a.id)),function(a){return a.charCodeAt(0)}),a})),navigator.credentials.get({publicKey:a}).then(function(a){var b={id:a.id,type:a.type,rawId:akeeba.LoginGuard.webauthn.arrayToBase64String(new Uint8Array(a.rawId)),response:{authenticatorData:akeeba.LoginGuard.webauthn.arrayToBase64String(new Uint8Array(a.response.authenticatorData)),clientDataJSON:akeeba.LoginGuard.webauthn.arrayToBase64String(new Uint8Array(a.response.clientDataJSON)),signature:akeeba.LoginGuard.webauthn.arrayToBase64String(new Uint8Array(a.response.signature)),userHandle:a.response.userHandle?akeeba.LoginGuard.webauthn.arrayToBase64String(new Uint8Array(a.response.userHandle)):null}};document.getElementById("loginGuardCode").value=btoa(JSON.stringify(b)),document.forms["loginguard-captive-form"].submit()},function(a){console.log(a),akeeba.LoginGuard.webauthn.handle_error(a)})):void akeeba.LoginGuard.webauthn.handle_error(Joomla.JText._("PLG_LOGINGUARD_WEBAUTHN_ERR_NO_STORED_CREDENTIAL"))},akeeba.LoginGuard.webauthn.onValidateClick=function(a){return a.preventDefault(),akeeba.LoginGuard.webauthn.authData=JSON.parse(window.atob(Joomla.getOptions("com_loginguard.authData"))),document.getElementById("plg_loginguard_webauthn_validate_button").style.disabled="disabled",akeeba.LoginGuard.webauthn.validate(),!1},document.getElementById("loginguard-webauthn-missing").style.display="none","undefined"==typeof navigator.credentials&&(document.getElementById("loginguard-webauthn-missing").style.display="block",document.getElementById("loginguard-webauthn-controls").style.display="none"),akeeba.Loader.add(["akeeba.System"],function(){"validate"===Joomla.getOptions("com_loginguard.pagetype")?(akeeba.System.addEventListener("plg_loginguard_webauthn_validate_button","click",akeeba.LoginGuard.webauthn.onValidateClick),akeeba.System.addEventListener("loginguard-captive-button-submit","click",akeeba.LoginGuard.webauthn.onValidateClick)):akeeba.System.addEventListener("plg_loginguard_webauthn_register_button","click",akeeba.LoginGuard.webauthn.setUp),akeeba.System.forEach(document.querySelectorAll(".loginguard_webauthn_setup"),function(a,b){akeeba.System.addEventListener(b,"click",akeeba.LoginGuard.webauthn.setUp)})});
//# sourceMappingURL=webauthn.js.map