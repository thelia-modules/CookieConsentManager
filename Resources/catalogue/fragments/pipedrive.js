tarteaucitron.services.pipedrive = {
    "key": "pipedrive",
    "type": "support",
    "name": "Pipedrive",
    "uri": "https://www.pipedrive.com/en/cookie-notice",
    "needConsent": true,
    "cookies": [],
    "js": function () {
        "use strict";

        if (tarteaucitron.user.pipedriveCompany === undefined || tarteaucitron.user.pipedrivePlaybook === undefined) {
            return;
        }

        window.pipedriveLeadboosterConfig = {base: 'leadbooster-chat.pipedrive.com', companyId: tarteaucitron.user.pipedriveCompany, playbookUuid: tarteaucitron.user.pipedrivePlaybook, version: 2};

        if (!window.LeadBooster) {
            window.LeadBooster = {
                q: [],
                on: function(n, h) {
                    this.q.push({
                        t: 'o',
                        n: n,
                        h: h
                    });
                },
                trigger: function(n) {
                    this.q.push({
                        t: 't',
                        n: n
                    });
                },
            };
        }

        tarteaucitron.addScript('https://leadbooster-chat.pipedrive.com/assets/loader.js');
    },
    "fallback": function () {
        "use strict";
        var id = '';
        tarteaucitron.fallback(['proactiveChat'], function (elem) {
            return tarteaucitron.engage(id);
        });
    }
};
