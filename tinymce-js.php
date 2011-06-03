<?php
if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');
}

global $CFG, $COURSE;
$wwwmat = isset($CFG->local_materials_url) ? $CFG->local_materials_url : '';
$context = get_context_instance(CONTEXT_COURSE, $COURSE->id);
$managefiles = has_capability('moodle/course:managefiles', $context);
$url = "{$CFG->httpswwwroot}/files/index.php?id={$COURSE->id}&choose=";
?>

var managefiles = <?php echo $managefiles ? "true" : "false"; ?>;
var file_browser_callback = function (field_name, url, type, win) {
    var w = window.open("<?php echo $url; ?>" + field_name, "",
                        "menubar=0,location=0,scrollbars,resizable,width=750,height=500");
    w.opener.tinymce3_window = win;
    return false;
};

tinyMCE.init({
    mode: 'none',
    relative_urls: false,
    language: "ca",
    theme: "advanced",
    skin: "o2k7",
    skin_variant: "silver",
    remove_script_host: false,
    entity_encoding: "raw",
    extended_valid_elements: "embed[type|width|height|src|*]",
    theme_advanced_fonts: "Trebuchet=Trebuchet MS,Arial,Helvetica,sans-serif;"
        + "Arial=Arial,Helvetica,sans-serif;"
        + "Courier New=Courier New,Courier,monospace;"
        + "Georgia=Georgia,Times New Roman,Times,serif;"
        + "Tahoma=Tahoma,Arial,Helvetica,sans-serif;"
        + "Times New Roman=Times New Roman,Times,serif;"
        + "Verdana=Verdana,Arial,Helvetica,sans-serif;"
        + "Impact=Impact;"
        + "Wingdings=wingdings",
    plugins: "table,emotions,searchreplace,fullscreen,paste,advimage,"
        + "inlinepopups,dragmath,nanogong",
    gecko_spellcheck: true,
    theme_advanced_toolbar_location: "top",
    theme_advanced_toolbar_align: "left",
    theme_advanced_statusbar_location: "bottom",
    theme_advanced_resizing: true,
    theme_advanced_buttons1: "fontselect,fontsizeselect,"
        + "formatselect,|,bold,italic,underline,strikethrough,|,sub,sup,|,"
        + "removeformat,cleanup,|,search,|,undo,redo",
    theme_advanced_buttons2: "justifyleft,justifycenter,"
        + "justifyright,justifyfull,|,bullist,numlist,indent,outdent,|,"
        + "forecolor,backcolor,|,hr,anchor,link,unlink,|,image,table,"
        + "emotions,dragmath,nanogong,charmap,|,code,|,fullscreen",
    theme_advanced_buttons3: "",
    fullscreen_settings: {
        theme_advanced_buttons3: "tablecontrols"
    },
    font_size_style_values: "8pt,10pt,12pt,14pt,18pt,24pt,36pt",
    file_browser_callback: managefiles ? file_browser_callback : null,
    paste_preprocess: function(pl, o) {
        if (/<img[^>]+src="data:/.test(o.content)) {
            o.content = "";
        }
    },
    moodle_courseid: <?php echo $COURSE->id; ?>,
    moodle_wwwroot: "<?php echo $CFG->wwwroot; ?>",
    moodle_wwwmat: "<?php echo $wwwmat;?>",
    moodle_sesskey: "<?php echo sesskey(); ?>"
});
