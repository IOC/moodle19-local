<?php
require_once('../../../../../config.php');
require_once($CFG->dirroot . '/local/lib/nanogong/lib.php');

$id = required_param('id', PARAM_INT);
require_login($id);
require_capability('moodle/course:managefiles',
                   get_context_instance(CONTEXT_COURSE, $id));

function _directories() {
    global $CFG, $COURSE;

    $dirs = array();

    $dirs[] = (object) array(
        'label' => get_string('maindirectory', 'resource'),
        'materials' => '',
        'wdir' => '/',
    );

    $rawdirs = get_directory_list($CFG->dataroot.'/'.$COURSE->id,
                                  array($CFG->moddata, 'backupdata', 'email'),
                                  true, true, false);

    foreach ($rawdirs as $rawdir) {
        $dirs[] = (object) array(
            'label' => $rawdir,
            'materials' => '',
            'wdir' => $rawdir,
        );
    }

    if ($records = get_records('local_materials', 'course', $COURSE->id, 'path')) {
        foreach ($records as $record) {
            $materials = trim($record->path, '/');
            $dirs[] = (object) array(
                'label' => 'Materials: ' . $materials,
                'materials' => $materials,
                'wdir' => '/',
            );

            $rawdirs = get_directory_list($CFG->dataroot . '/materials/'
                                          . $materials, '', true, true, false);
            foreach ($rawdirs as $rawdir) {
                $dirs[] = (object) array(
                    'label' => 'Materials: ' . $materials . '.' . $rawdir,
                    'materials' => $materials,
                    'wdir' => $rawdir,
                );
            }
        }
    }

    return $dirs;
}

function _print_directories_menu() {
    global $CFG, $COURSE;

    $dirs = _directories();

    $menu = array();
    foreach ($dirs as $index => $dir) {
        foreach (array('materials', 'wdir') as $param) {
            $name = $param . '-' . $index;
            $value = $dir->$param;
            echo "<input type=\"hidden\" id=\"$name\" "
                . "name=\"$name\" value=\"$value\" />";
        }
        $menu[$index] = $dir->label;
    }

    choose_from_menu($menu, 'directory', '0', false);
}

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
  <head>
    <title>{#nanogong_dlg.title}</title>
    <script type="text/javascript" src="../../tiny_mce_popup.js"></script>
    <script type="text/javascript" src="../../utils/mctabs.js"></script>
    <script type="text/javascript" src="../../utils/form_utils.js"></script>
    <script type="text/javascript" <?php echo "src=\"{$CFG->wwwroot}/local/lib/nanogong/nanogong.js\"";?> ></script>
    <script type="text/javascript" src="js/dialog.js"></script>
    <link href="css/dialog.css" rel="stylesheet" type="text/css" />
  </head>
  <body>
    <div class="tabs">
      <ul>
	<li id="select_tab" class="current">
          <span>
            <a href="javascript:mcTabs.displayTab('select_tab','select_panel');"
               onmousedown="return false;">{#nanogong_dlg.select_tab}</a>
          </span>
        </li>
	<li id="record_tab">
          <span>
            <a href="javascript:mcTabs.displayTab('record_tab','record_panel');"
               onmousedown="return false;">{#nanogong_dlg.record_tab}</a>
          </span>
        </li>
      </ul>
    </div>

    <div class="panel_wrapper">

      <div id="select_panel" class="panel current">
        <table cellspacing="0" cellpadding="4" border="0">
          <tr>
	    <td class="nowrap"><label id="hreflabel" for="href">{#nanogong_dlg.url}</label></td>
            <td><input id="href" name="href" type="text" class="mceFocus"
                       value="" onchange="nanogong_load('player', this.value);" /></td>
            <td id="hrefbrowsercontainer">&nbsp;</td>
          </tr>
          <tr>
            <td>&nbsp;</td>
            <td>
              <?php echo nanogong_applet('player'); ?>
            </td>
            <td>&nbsp;</td>
          </tr>
        </table>
      </div>

      <div id="record_panel" class="panel">
        <table cellspacing="0" cellpadding="4" border="0">
          <tr>
            <td>&nbsp;</td>
            <td>
              <?php echo nanogong_applet('recorder', false, true); ?>
            </td>
          </tr>
          <tr>
	    <td class="nowrap"><label id="filenamelabel" for="filename">{#nanogong_dlg.name}</label></td>
            <td><input id="filename" name="filename" type="text" class="mceFocus" value="" /></td>
          </tr>
          <tr>
            <td class="nowrap"><label id="directorylabel" for="directory">{#nanogong_dlg.directory}</label></td>
            <td><?php _print_directories_menu(); ?></td>
          </tr>
        </table>
      </div>

    </div>

    <div class="mceActionPanel">
      <div style="float: left">
	<input type="button" id="insert" name="insert" value="{#insert}" onclick="NanoGongDialog.insert();" />
      </div>
      <div style="float: right">
	<input type="button" id="cancel" name="cancel" value="{#cancel}" onclick="tinyMCEPopup.close();" />
      </div>
    </div>
  </body>
</html>
