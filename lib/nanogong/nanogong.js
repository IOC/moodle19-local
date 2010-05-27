
function nanogong_get(id) {
    var recorder = document.getElementById(id);
    if (recorder == null) {
        alert("S'ha produït un error en inicialitzar el NanoGong.");
        return false;
    }
    return recorder;
}

function nanogong_check_duration(id) {
    var recorder = document.getElementById(id);
    if (recorder == null) {
	return false;
    }
    var duration = recorder.sendGongRequest("GetMediaDuration", "audio");
    if (duration == null || duration == "" ||
	isNaN(duration) || parseInt(duration) <= 0) {
	alert("No s'ha enregistrat res.");
	return false;
    }
    return true;
}

function nanogong_load(id, url) {
    var recorder = document.getElementById(id);
    if (recorder == null) {
	return false;
    }
    recorder.sendGongRequest('LoadFromURL', url);
    return true;
}

function nanogong_is_modified(id) {
    var recorder = document.getElementById(id);
    if (recorder == null) {
	return false;
    }
    var modified = recorder.getModified();
    return !(modified == null || modified != "1");
}

function nanogong_upload(id, url) {
    var recorder = document.getElementById(id);
    if (recorder == null) {
	return false;
    }
    var path = recorder.sendGongRequest('PostToForm', url, 'newfile', '', 'temp');
    if (path == null || path == "") {
        alert("S'ha produït un error: " + recorder.getError());
        return false;
    }
    return true;
}

function nanogong_submit(id, upload_url) {
    var recorder = nanogong_get(id);

    if (!recorder) {
	return false;
    }

    if (!nanogong_check_duration(id)) {
	return false;
    }

    if (!nanogong_is_modified(id)) {
        alert("No s'ha modificat l'enregistrament.");
        return false;
    }

    if (!nanogong_upload(id, upload_url)) {
	return false;
    }

    return true;
}
