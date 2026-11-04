tarteaucitron.services.gallica = {
    "key": "gallica",
    "type": "other",
    "name": "Gallica",
    "uri": "https://gallica.bnf.fr/edit/und/conditions-dutilisation-des-contenus-de-gallica",
    "needConsent": true,
    "cookies": ['dtCookie', 'dtLatC', 'dtPC', 'dtSa', 'rxVisitor', 'rxvt', 'xtvrn'],
    "js": function () {
        "use strict";
        tarteaucitron.fallback(['gallica_player'], function (x) {
            var frame_title = (tarteaucitron.getElemAttr(x,"title")) ? tarteaucitron.getElemAttr(x,"title") : 'Gallica iframe',
                src = tarteaucitron.getElemAttr(x, "data-src"),
                style = tarteaucitron.getElemAttr(x, "data-style"),
                frame;
            if (src === "") {
                return "";
            }
            frame = '<iframe title="' + frame_title + '" style="'+ style + '" src="' + src + '"></iframe>';
            return frame;
        });
    },
    "fallback": function () {
        "use strict";
        var id = 'gallica';
        tarteaucitron.fallback(['gallica_player'], function (elem) {
            elem.style = tarteaucitron.getElemAttr(elem,'data-style');
            return tarteaucitron.engage(id);
        });
    }
};
