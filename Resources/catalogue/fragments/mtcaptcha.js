tarteaucitron.services.mtcaptcha = {
    "key": "mtcaptcha",
    "type": "api",
    "name": "MTcaptcha",
    "uri": "https://www.mtcaptcha.com",
    "readmoreLink": "https://www.mtcaptcha.com/faq-cookie-declaration",
    "needConsent": true,
    "cookies": ['mtv1Pulse','mtv1ConfSum','mtv1Pong'],

    "js": function () {

        if (tarteaucitron.user.mtcaptchaSitekey === undefined) {
            return;
        }

        window.mtcaptchaConfig = {
            "sitekey": tarteaucitron.user.mtcaptchaSitekey
        };

        tarteaucitron.addScript('https://service.mtcaptcha.com/mtcv1/client/mtcaptcha.min.js');
        tarteaucitron.addScript('https://service2.mtcaptcha.com/mtcv1/client/mtcaptcha2.min.js');
    }
};
