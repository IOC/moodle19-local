tinyMCEPopup.requireLangPack();

var NanoGongDialog = {
    init : function() {
        document.getElementById('hrefbrowsercontainer').innerHTML = getBrowserHTML('hrefbrowser','href','file','nanogong');
    },


    insert : function() {
        if (document.getElementById('record_tab').className == 'current') {

            if (!nanogong_check_duration('recorder')) {
	        return false;
            }

            var filename = document.getElementById('filename');

            if (filename.value == null || filename.value == '') {
                filename.focus();
                alert("No heu indicat el nom del fitxer!");
                return false;
            }

            if (!filename.value.toLowerCase().match(/\.wav$/)) {
                filename.value += ".wav";
            }

            var wwwroot = tinyMCEPopup.getParam('moodle_wwwroot');
            var wwwmat = tinyMCEPopup.getParam('moodle_wwwmat');
            var courseid = tinyMCEPopup.getParam('moodle_courseid');
            var sesskey = tinyMCEPopup.getParam('moodle_sesskey');
            var directory = document.getElementById('menudirectory').value;
            var materials = document.getElementById('materials-' + directory).value;
            var wdir = document.getElementById('wdir-' + directory).value;

            var upload_url = wwwroot + '/files/index.php?id=' + courseid
                + '&action=uploadnanogong&sesskey=' + sesskey
                + '&materials=' + encodeURIComponent(materials)
                + '&wdir=' + encodeURIComponent(wdir)
                + '&file=' + encodeURIComponent(filename.value);

            if (!nanogong_upload('recorder', upload_url)) {
                return false;
            }

            if (materials) {
                var url = wwwmat + '/' + materials + '/'
                    + (wdir == '/' ? '' : wdir + '/') + filename.value;
            } else {
                var url = wwwroot + '/file.php/' + courseid + '/'
                    + (wdir == '/' ? '' : wdir + '/') + filename.value;
            }

        } else {
            var  url = document.getElementById('href').value;
        }

        if (url) {
            var text = "$nanogong:" + url + "$";
            tinyMCEPopup.editor.execCommand('mceInsertContent', false, text);
        }
        tinyMCEPopup.close();
    }
};

tinyMCEPopup.onInit.add(NanoGongDialog.init, NanoGongDialog);
