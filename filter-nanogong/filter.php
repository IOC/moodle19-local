<?php

function nanogong_filter($course, $text) {
    global $CFG;
    $match = '/\$nanogong:(.*?)\$/';
    if (preg_match($match, $text)) {
        require_once($CFG->dirroot . '/local/lib/nanogong/lib.php');
        $div = '<div id="overDiv" style="position:absolute; visibility:hidden; z-index:1000;"></div>';
        $applet = s(nanogong_applet('nanogong', "$1"));
        $html = '<img style="cursor: pointer; display: inline; vertical-align: middle;"'
            . ' src="' . $CFG->wwwroot . '/local/lib/nanogong/icon.gif"'
            . ' alt="Enregistrament" title="Enregistrament"'
            . ' onclick="return overlib(\'' . $applet . '\', STICKY, CAPTION, \'Enregistrament\', CLOSECLICK, CLOSETEXT, \'Tanca\', CLOSETITLE, \'Tanca\', WIDTH, 150);" />';
        return $div . preg_replace($match, $html, $text);
    }
    return $text;
}
