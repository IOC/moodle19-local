(function() {
    tinymce.PluginManager.requireLangPack('nanogong');

    tinymce.create('tinymce.plugins.NanoGongPlugin', {
        init : function(ed, url) {
            ed.addCommand('mceNanoGong', function() {
                ed.windowManager.open({
                    file : url + '/dialog.php?id=' + ed.getParam('moodle_courseid'),
	            width : 300 + parseInt(ed.getLang('nanogong.delta_width', 0)),
	            height : 200 + parseInt(ed.getLang('nanogong.delta_height', 0)),
	            inline : 1
                }, {
                    plugin_url : url,
                });
            });

	    ed.addButton('nanogong', {
	        title : 'nanogong.desc',
	        cmd : 'mceNanoGong',
                image : ed.getParam('moodle_wwwroot') + '/local/lib/nanogong/icon.gif'
	    });
        },

        createControl : function(n, cm) {
	    return null;
        },

        getInfo : function() {
	    return {
	        longname : 'NanoGong plugin',
	        author : 'Albert Gasset',
	        authorurl : '',
	        infourl : '',
	        version : "0.0"
	    };
        }
    });

     tinymce.PluginManager.add('nanogong', tinymce.plugins.NanoGongPlugin);
})();
