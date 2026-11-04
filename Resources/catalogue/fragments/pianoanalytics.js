tarteaucitron.services.pianoanalytics = {
    "key": "pianoanalytics",
    "type": "analytic",
    "name": "Piano Analytics",
    "uri": "https://piano.io/privacy-policy/",
    "needConsent": true,
    "cookies": ['_pcid','_pctx','_pctx','pa_user', 'pa_privacy'],
    "js": function () {
        "use strict";

        if (tarteaucitron.user.pianoCollectDomain === undefined || tarteaucitron.user.pianoSite === undefined) {
            return;
        }

        tarteaucitron.addScript("https://tag.aticdn.net/piano-analytics.js", '', function () {

            pa.setConfigurations({
                site: tarteaucitron.user.pianoSite,
                collectDomain: tarteaucitron.user.pianoCollectDomain
            });

            if (tarteaucitron.user.pianoSendData !== false) {
                pa.sendEvent('page.display', {
                    'page': document.title
                });
            }
        });
    },
    "fallback": function () {
        if (tarteaucitron.parameters.pianoConsentMode === true) {
            if (tarteaucitron.parameters.softConsentMode === false) {
                this.js();
            }
        }
    }
};
