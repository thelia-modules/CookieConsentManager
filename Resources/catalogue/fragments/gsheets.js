tarteaucitron.services.gsheets = {
    "key": "gsheets",
    "type": "other",
    "name": "Google Sheets",
    "needConsent": true,
    "cookies": ["CONSENT", "NID"],
    "uri": "https://policies.google.com/privacy",
    "js": function () {
        "use strict";
        tarteaucitron.fallback(['gsheets_embed'], function (x) {
            var frame_title = (tarteaucitron.getElemAttr(x,"title")) ? tarteaucitron.getElemAttr(x,"title") : 'Google sheets iframe',
                id = tarteaucitron.getElemAttr(x, 'id'),
                width = tarteaucitron.getElemAttr(x, 'width'),
                height = tarteaucitron.getElemAttr(x, 'height'),
                headers = tarteaucitron.getElemAttr(x, 'headers');

            var styleAttr = (width !== "" ? "width:" + tarteaucitron.getStyleSize(width) + ";" : "") + (height !== "" ? "height:" + tarteaucitron.getStyleSize(height) + ";" : "");

            return "<iframe title=\"" + frame_title + "\" style=\"" + styleAttr + "\" src=\"https://docs.google.com/spreadsheets/d/e/" + id + "/pubhtml?widget=true&amp;headers=" + headers + "\"></iframe>";
        });
    },
    "fallback": function () {
        "use strict";
        var id = "gsheets";
        tarteaucitron.fallback(["gsheets_embed"], tarteaucitron.engage(id));
    }
};
