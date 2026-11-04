tarteaucitron.services.pardot = {
    "key": "pardot",
    "type": "analytic",
    "name": "Pardot",
    "uri": "https://www.salesforce.com/company/privacy/full_privacy/",
    "needConsent": true,
    "cookies": ['visitor_id'],
    "js": function () {
        "use strict";
        if (tarteaucitron.user.piAId === undefined || tarteaucitron.user.piCId === undefined) {
            return;
        }

        window.piAId = tarteaucitron.user.piAId;
        window.piCId = tarteaucitron.user.piCId;
        window.piHostname = 'pi.pardot.com';

        tarteaucitron.addScript('https://pi.pardot.com/pd.js');
    }
};
