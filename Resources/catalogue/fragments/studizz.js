tarteaucitron.services.studizz = {
    "key": "studizz",
    "type": "support",
    "name": "Studizz Chatbot",
    "uri": "https://group.studizz.fr/",
    "needConsent": true,
    "cookies": [],
    "js": function () {
        "use strict";

        if (tarteaucitron.user.studizzToken === undefined) {
            return;
        }

        tarteaucitron.addScript('https://webchat.studizz.fr/webchat.js?token=' + tarteaucitron.user.studizzToken);
    }
};
