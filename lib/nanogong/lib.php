<?php

function nanogong_applet($id=false, $sound_url=false, $can_record=false,
                         $can_save=false, $use_speex=false)
{
    global $CFG;

    require_js($CFG->wwwroot . '/local/lib/nanogong/nanogong.js');

    $width = 120 + ($can_record ? 30 : 0) + ($can_save ? 30 : 0);
    $html = '<applet archive="' . $CFG->wwwroot . '/local/lib/nanogong/nanogong.jar"';
    if ($id) {
        $html .=  ' id="' . $id . '"';
    }
    $html .= ' code="gong.NanoGong" width="' . $width . '" height="40">';
    if ($sound_url) {
        $html .= '<param name="SoundFileURL" value="' . $sound_url . '"/>';
    }
    $html .= '<param name="ShowRecordButton" value="'
        . ($can_record ? 'true' : 'false') . '" />'
        . '<param name="ShowSaveButton" value="'
        . ($can_save ? 'true' : 'false') . '" />';
    if ($can_record) {
        $sampling_rate = '8000';
        $audio_format = 'ImaADPCM';
        if ($use_speex) {
            $sampling_rate = '16000';
            $audio_format = 'Speex';
        }
        $html .= '<param name="SamplingRate" value="' . $sampling_rate . '"/>'
            . '<param name="AudioFormat" value="' . $audio_format . '" />';
    }

    $html .= '</applet>';

    return $html;
}
