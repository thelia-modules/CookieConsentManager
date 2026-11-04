tarteaucitron.services.clarity = {
    "key": "clarity",
    "type": "analytic",
    "name": "Clarity",
    "uri": "https://clarity.microsoft.com/",
    "needConsent": true,
    "cookies": ['_clck', '_clsk', 'CLID', 'ANONCHK', 'MR', 'MUID', 'SM'],
    "js": function () {
        "use strict";

        if (tarteaucitron.user.clarity === undefined) {
            return;
        }

        window["clarity"] = window["clarity"] || function () { (window["clarity"].q = window["clarity"].q || []).push(arguments) };

        tarteaucitron.addScript('https://www.clarity.ms/tag/' + tarteaucitron.user.clarity, '', function() {
            window["clarity"]("consent");
        });
    },
    "fallback": function () {
        if (tarteaucitron.parameters.bingConsentMode === true) {
            if (tarteaucitron.parameters.softConsentMode === false) {
                this.js();
            }
        }
    }
};
