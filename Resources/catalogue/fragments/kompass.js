tarteaucitron.services.kompass = {
    "key": "kompass",
    "type": "analytic",
    "name": "Kompass",
    "uri": "https://fr.kompass.com/l/cookie-use-policy",
    "needConsent": true,
    "cookies": ["kompass","gq_lead","_first_pageview","eqy_sessionid","eqy_siteid","cluid","eqy_company","gq_utm","_jsuid"],
    "js": function () {
        "use strict";

        if (tarteaucitron.user.kompassId === undefined) {
            return;
        }

        tarteaucitron.addScript('https://fr.kompass.com/leads/script.js?id=' + tarteaucitron.user.kompassId);
    }
};
