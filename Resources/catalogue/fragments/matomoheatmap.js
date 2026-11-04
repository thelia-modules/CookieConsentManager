tarteaucitron.services.matomoheatmap = {
    "key": "matomoheatmap",
    "type": "analytic",
    "name": "Matomo Cloud (heatmap)",
    "uri": "https://matomo.org/guide/manage-matomo/privacy/",
    "needConsent": true,
    "cookies": [],
    "js": function () {
        "use strict";

        window._paq = window._paq || [];
        _paq.push(['HeatmapSessionRecording::enable']);
    },
    "fallback": function () {
        "use strict";

        window._paq = window._paq || [];
        _paq.push(['HeatmapSessionRecording::disable']);
    }
};
