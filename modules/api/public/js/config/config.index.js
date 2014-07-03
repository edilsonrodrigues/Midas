// MIDAS Server. Copyright Kitware SAS. Licensed under the Apache License 2.0.

var midas = midas || {};
midas.api = midas.api || {};

midas.api.validateConfig = function (formData, jqForm, options) {

}

midas.api.successConfig = function (responseText, statusText, xhr, form) {
    try {
        var jsonResponse = jQuery.parseJSON(responseText);
    }
    catch (e) {
        midas.createNotice("An error occured. Please check the logs.", 4000, 'error');
        return false;
    }
    if (jsonResponse == null) {
        midas.createNotice('Error', 4000, 'error');
        return;
    }
    if (jsonResponse[0]) {
        midas.createNotice(jsonResponse[1], 4000);
    }
    else {
        midas.createNotice(jsonResponse[1], 4000, 'error');
    }
}

$(document).ready(function () {
    $('#configForm').ajaxForm({
        beforeSubmit: midas.api.validateConfig,
        success: midas.api.successConfig
    });
});
