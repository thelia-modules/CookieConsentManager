tarteaucitron.services.ubib = {
    "key": "ubib",
    "type": "support",
    "name": "Ubib Chatbot",
    "uri": "https://ubib.libanswers.com/",
    "needConsent": true,
    "cookies": [],
    "js": function () {
        "use strict";

        if (tarteaucitron.user.ubibId === undefined || tarteaucitron.user.ubibHash === undefined) {
            return;
        }

        tarteaucitron.addScript('https://' + tarteaucitron.user.ubibId + '.libanswers.com/load_chat.php?hash=' + tarteaucitron.user.ubibHash);
    }
};
