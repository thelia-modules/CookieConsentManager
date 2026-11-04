tarteaucitron.services.webtvnu = {
    "key": "webtvnu",
    "type": "video",
    "name": "WebTV Normandie Université",
    "uri": "https://docs.google.com/document/d/1tpVclj4QBoAq1meSZgYrpNECwp7dbmb_IhICY3sTl9c/edit",
    "needConsent": true,
    "cookies": [],
    "js": function () {
        "use strict";
        tarteaucitron.fallback(['webtvnu_player'], function (x) {
            var frame_title = (tarteaucitron.getElemAttr(x,"title")) ? tarteaucitron.getElemAttr(x,"title") : 'WebTV Normandie Université iframe',
                frame_url = 'https://webtv.normandie-univ.fr/permalink/' + tarteaucitron.getElemAttr(x, "videoID") + '/iframe/',
                width = tarteaucitron.getElemAttr(x, "width"),
                height = tarteaucitron.getElemAttr(x, "height");

            var styleAttr = (width !== "" ? "width:" + tarteaucitron.getStyleSize(width) + ";" : "") + (height !== "" ? "height:" + tarteaucitron.getStyleSize(height) + ";" : "");

            return '<iframe title="' + frame_title + '" style="' + styleAttr + '" src="' + frame_url + '" allowfullscreen allow="autoplay"></iframe>';
        });
    },
    "fallback": function () {
        "use strict";
        tarteaucitron.fallback(['webtvnu_player'], function (elem) {
            return tarteaucitron.engage('webtvnu');
        });
    }
};
