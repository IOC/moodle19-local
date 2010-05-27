tinyMCEPopup.requireLangPack();

var DragMathDialog = {
    init : function() {
	var codebase = tinyMCEPopup.getParam('moodle_wwwroot') + '/local/lib/dragmath/';
        var applet = '<applet width="540" height="300" '
            + 'archive="DragMath.jar,lib/AbsoluteLayout.jar,lib/swing-layout-1.0.jar,lib/jdom.jar,lib/jep.jar" '
            + 'code="Display.MainApplet.class" codebase="' + codebase + '" '
            + 'name="dragmath">'
            + '<param name=language value="ca" />'
            + '<param name=outputFormat value="MoodleTex" />'
            + '<param name=showOutputToolBar value="false" />'
            + '</applet>';
            document.getElementById('dragmath_div').innerHTML = applet;
    },

    insert : function() {
	// Insert the contents from the input into the document
        var text = document.dragmath.getMathExpression();
        tinyMCEPopup.editor.execCommand('mceInsertContent', false, text);
	tinyMCEPopup.close();
    }
};

tinyMCEPopup.onInit.add(DragMathDialog.init, DragMathDialog);
