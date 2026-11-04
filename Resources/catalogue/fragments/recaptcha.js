tarteaucitron.services.recaptcha = {
    "key": "recaptcha",
    "type": "api",
    "name": "reCAPTCHA",
    "uri": "https://policies.google.com/privacy",
    "needConsent": true,
    "cookies": ['nid'],
    "js": function () {
        "use strict";
        window.tacRecaptchaOnLoad = tarteaucitron.user.recaptchaOnLoad || function () { };
        tarteaucitron.fallback(['g-recaptcha'], '');

        let url = 'https://www.google.com/recaptcha/api.js?onload=tacRecaptchaOnLoad';
        if (tarteaucitron.user.recaptchaapi !== undefined) {
            url += '&render=' + tarteaucitron.user.recaptchaapi;
        }
        if (tarteaucitron.user.recaptcha_hl !== undefined) {
            url += '&hl=' + tarteaucitron.user.recaptcha_hl;
        }
        tarteaucitron.addScript(url);
    },
    "fallback": function () {
        "use strict";
        var id = 'recaptcha';
        tarteaucitron.fallback(['g-recaptcha'], tarteaucitron.engage(id));
    }
};
