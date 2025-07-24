M.supervideo_filepicker = {};
M.supervideo_filepicker.Y = null;
M.supervideo_filepicker.instances = [];

M.supervideo_filepicker.callback = function (params) {
    console.log(params);

    let elementid = M.core_filepicker.instances[params['client_id']].options.elementid

    let element_elementid = document.getElementById(elementid);
    element_elementid.value = params.url;

    M.supervideo_filepicker.instances[elementid].fileadded = true;
    M.supervideo_filepicker.Y.one('#'+elementid).simulate('change');
};

/**
 * This fucntion is called for each file picker on page.
 */
M.supervideo_filepicker.init = function (Y, options) {
    console.log(options);
    //Keep reference of YUI, so that it can be used in callback.
    M.supervideo_filepicker.Y = Y;

    //For client side validation, initialize file status for this filepicker
    M.supervideo_filepicker.instances[options.elementid] = {};
    M.supervideo_filepicker.instances[options.elementid].fileadded = false;

    //Set filepicker callback
    options.formcallback = M.supervideo_filepicker.callback;

    if (!M.core_filepicker.instances[options.client_id]) {
        M.core_filepicker.init(Y, options);
    }
    Y.on('click', function (e, client_id) {
        e.preventDefault();
        if (this.ancestor('.fitem.disabled') == null) {
            M.core_filepicker.instances[client_id].show();
        }
    }, '#filepicker-button-' + options.elementid, null, options.client_id);

    var button = document.getElementById('filepicker-button-' + options.elementid);
    if (button) {
        button.style.display = '';
    }
};
