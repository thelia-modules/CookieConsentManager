tarteaucitron.services.userlike = {
    "key": "userlike",
    "type": "support",
    "name": "Userlike",
    "uri": "https://www.userlike.com/en/terms#privacy-policy",
    "needConsent": true,
    "cookies": ['uslk_s', 'uslk_e'],
    "js": function () {
        "use strict";
        if (tarteaucitron.user.userlikeKey === undefined) {
            return;
        }
        tarteaucitron.addScript('//userlike-cdn-widgets.s3-eu-west-1.amazonaws.com/' + tarteaucitron.user.userlikeKey);
    }
};
