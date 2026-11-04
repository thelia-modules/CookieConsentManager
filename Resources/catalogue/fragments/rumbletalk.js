tarteaucitron.services.rumbletalk = {
    "key": "rumbletalk",
    "type": "social",
    "name": "RumbleTalk",
    "needConsent": true,
    "cookies": ['AWSALB'],
    "js": function () {
        "use strict";
        if (tarteaucitron.user.rumbletalkid === undefined) {
            return;
        }

        tarteaucitron.addScript('https://rumbletalk.com/client/?' + tarteaucitron.user.rumbletalkid);

        tarteaucitron.fallback(['rumbletalk'], function (x) {
            var width = tarteaucitron.getElemWidth(x),
                height = tarteaucitron.getElemHeight(x),
                id = tarteaucitron.getElemAttr(x, "data-id");

            return '<div style="height: ' + tarteaucitron.getStyleSize(height) + '; width: ' + tarteaucitron.getStyleSize(width) + ';"><div id="' + id + '"></div></div>';
        });
    },
    "fallback": function () {
        "use strict";
        var id = 'rumbletalk';
        tarteaucitron.fallback(['rumbletalk'], function (elem) {
            elem.style.width = tarteaucitron.getStyleSize(tarteaucitron.getElemWidth(elem));
            elem.style.height = tarteaucitron.getStyleSize(tarteaucitron.getElemHeight(elem));

            return tarteaucitron.engage(id);
        });
    }
};
